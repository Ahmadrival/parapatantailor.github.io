<?php
// tambahpesanan.php
require_once '../config/database.php';
check_login();
check_role(['admin', 'pemilik','pegawai']);

$page_title = "Tambah Pesanan Baru";

// ============================================================
// HANDLE NAVIGASI YANG LEBIH BAIK
// ============================================================
if (isset($_GET['back'])) {
    $_SESSION['current_step'] = 1;
    header("Location: tambahpesanan.php");
    exit();
}

if (isset($_GET['reset'])) {
    unset($_SESSION['pesanan_data']);
    unset($_SESSION['detail_pakaian']);
    unset($_SESSION['current_step']);
    header("Location: tambahpesanan.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['step1_submit'])) {
        // Step 1: Simpan data dasar pesanan
        $_SESSION['pesanan_data'] = [
            'id_pelanggan' => clean_input($_POST['id_pelanggan']),
            'id_karyawan' => clean_input($_POST['id_karyawan']),
            'tgl_pesanan' => clean_input($_POST['tgl_pesanan']),
            'tgl_selesai' => clean_input($_POST['tgl_selesai']),
            'kuantitas' => clean_input($_POST['kuantitas'])
        ];
        
        // Validasi kuantitas
        if ($_SESSION['pesanan_data']['kuantitas'] > 20) {
            $_SESSION['error'] = "❌ Kuantitas maksimal adalah 20 item";
            header("Location: tambahpesanan.php");
            exit();
        }
        
        $_SESSION['current_step'] = 2;
        header("Location: tambahpesanan.php");
        exit();
        
    } elseif (isset($_POST['step2_submit'])) {
        // Step 2: Simpan data detail pakaian dengan validasi
        $error_messages = [];
        $detail_pakaian = $_POST['detail_pakaian'];
        $kuantitas = $_SESSION['pesanan_data']['kuantitas'];
        
        // Validasi setiap item
        for ($i = 1; $i <= $kuantitas; $i++) {
            $item = $detail_pakaian[$i] ?? [];
            
            // Validasi data dasar
            if (empty($item['jenis_pakaian'])) {
                $error_messages[] = "Jenis pakaian untuk item $i harus diisi";
            }
            
            if (empty($item['bahan'])) {
                $error_messages[] = "Bahan untuk item $i harus diisi";
            }
            
            if (empty($item['harga']) || floatval($item['harga']) <= 0) {
                $error_messages[] = "Harga untuk item $i harus diisi dengan nilai positif";
            }
            
            // Validasi ukuran atasan jika dipilih
            if (isset($item['ukuran_atasan']) && $item['ukuran_atasan'] == 'ya') {
                $ukuran_atasan_fields = ['krah', 'pundak', 'tangan', 'ld_lp', 'badan', 'pinggang', 'pinggul', 'panjang_atasan'];
                foreach ($ukuran_atasan_fields as $field) {
                    if (empty($item[$field]) || floatval($item[$field]) <= 0) {
                        $error_messages[] = "Semua ukuran atasan untuk item $i harus diisi dengan nilai positif";
                        break; // Keluar dari loop setelah menemukan error pertama
                    }
                }
            }
            
            // Validasi ukuran bawahan jika dipilih
            if (isset($item['ukuran_bawahan']) && $item['ukuran_bawahan'] == 'ya') {
                $ukuran_bawahan_fields = ['pinggang_bawahan', 'pinggul_bawahan', 'kres', 'paha', 'lutut', 'l_bawah', 'panjang_bawahan'];
                foreach ($ukuran_bawahan_fields as $field) {
                    if (empty($item[$field]) || floatval($item[$field]) <= 0) {
                        $error_messages[] = "Semua ukuran bawahan untuk item $i harus diisi dengan nilai positif";
                        break; // Keluar dari loop setelah menemukan error pertama
                    }
                }
            }
            
            // Validasi minimal satu ukuran dipilih
            if (!isset($item['ukuran_atasan']) && !isset($item['ukuran_bawahan'])) {
                $error_messages[] = "Item $i: Pilih minimal satu jenis ukuran (Atasan atau Bawahan)";
            }
        }
        
        // Jika ada error, kembali ke step 2
        if (!empty($error_messages)) {
            $_SESSION['error'] = implode("<br>", $error_messages);
            $_SESSION['detail_pakaian'] = $detail_pakaian;
            header("Location: tambahpesanan.php");
            exit();
        }
        
        // Simpan data jika valid
        $_SESSION['detail_pakaian'] = $detail_pakaian;
        $_SESSION['current_step'] = 3;
        header("Location: tambahpesanan.php");
        exit();
        
    } elseif (isset($_POST['back_to_step1'])) {
        // Tombol kembali ke step 1 dari step 2
        $_SESSION['current_step'] = 1;
        header("Location: tambahpesanan.php");
        exit();
        
    } elseif (isset($_POST['back_to_step2'])) {
        // Tombol kembali ke step 2 dari step 3
        $_SESSION['current_step'] = 2;
        header("Location: tambahpesanan.php");
        exit();
        
    } elseif (isset($_POST['step3_submit'])) {
        // Step 3: Simpan data pembayaran dan simpan ke database
        try {
            $pdo->beginTransaction();
            
            // Data dasar pesanan
            $pesanan_data = $_SESSION['pesanan_data'];
            $detail_pakaian = $_SESSION['detail_pakaian'];
            
            // Hitung total harga dari semua item
            $total_harga = 0;
            
            foreach ($detail_pakaian as $item) {
                $total_harga += floatval($item['harga'] ?? 0);
            }
            
            $jumlah_bayar = floatval(clean_input($_POST['jumlah_bayar']));
            $sisa_bayar = $total_harga - $jumlah_bayar;
            $status_pesanan = clean_input($_POST['status_pesanan']);
            $metode_pembayaran = clean_input($_POST['metode_pembayaran']);
            $total_kuantitas = count($detail_pakaian);
            
            // Tentukan status pembayaran
            if ($jumlah_bayar == 0) {
                $status_pembayaran = 'belum_bayar';
            } elseif ($jumlah_bayar < $total_harga) {
                $status_pembayaran = 'sebagian';
            } else {
                $status_pembayaran = 'lunas';
            }
            
            // Ambil data item pertama untuk backward compatibility
            $first_item = reset($detail_pakaian);
            
            // Insert data pesanan utama (dengan total_kuantitas baru)
            $sql_pesanan = "INSERT INTO data_pesanan 
                           (id_pelanggan, id_karyawan, tgl_pesanan, tgl_selesai, total_harga, 
                            jumlah_bayar, sisa_bayar, total_kuantitas, status_pesanan, 
                            metode_pembayaran, status_pembayaran, jenis_pakaian, bahan) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt_pesanan = $pdo->prepare($sql_pesanan);
            $stmt_pesanan->execute([
                $pesanan_data['id_pelanggan'],
                $pesanan_data['id_karyawan'],
                $pesanan_data['tgl_pesanan'],
                $pesanan_data['tgl_selesai'],
                $total_harga,
                $jumlah_bayar,
                $sisa_bayar,
                $total_kuantitas,
                $status_pesanan,
                $metode_pembayaran,
                $status_pembayaran,
                $first_item['jenis_pakaian'] ?? '',
                $first_item['bahan'] ?? ''
            ]);
            
            $id_pesanan = $pdo->lastInsertId();
            
            // INSERT SEMUA ITEMS KE TABEL pesanan_items
            foreach ($detail_pakaian as $index => $item) {
                $sql_item = "INSERT INTO pesanan_items 
                            (id_pesanan, jenis_pakaian, bahan, jumlah, harga_satuan, catatan) 
                            VALUES (?, ?, ?, ?, ?, ?)";
                
                $stmt_item = $pdo->prepare($sql_item);
                $stmt_item->execute([
                    $id_pesanan,
                    $item['jenis_pakaian'],
                    $item['bahan'],
                    1, // Default 1 untuk setiap item
                    $item['harga'],
                    $item['keterangan_atasan'] ?? '' // Gunakan keterangan atasan sebagai catatan
                ]);
            }
            
            // Insert ukuran untuk semua item
            $item_counter = 1;
            foreach ($detail_pakaian as $index => $item) {
                // Insert ukuran atasan jika ada
                if (isset($item['ukuran_atasan']) && $item['ukuran_atasan'] == 'ya') {
                    $sql_atasan = "INSERT INTO ukuran_atasan 
                                  (id_pesanan, id_pelanggan, krah, pundak, tangan, ld_lp, badan, pinggang, pinggul, panjang, keterangan) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    $stmt_atasan = $pdo->prepare($sql_atasan);
                    $stmt_atasan->execute([
                        $id_pesanan,
                        $pesanan_data['id_pelanggan'],
                        $item['krah'] ?? 0,
                        $item['pundak'] ?? 0,
                        $item['tangan'] ?? 0,
                        $item['ld_lp'] ?? 0,
                        $item['badan'] ?? 0,
                        $item['pinggang'] ?? 0,
                        $item['pinggul'] ?? 0,
                        $item['panjang_atasan'] ?? 0,
                        $item['keterangan_atasan'] ?? ''
                    ]);
                }
                
                // Insert ukuran bawahan jika ada
                if (isset($item['ukuran_bawahan']) && $item['ukuran_bawahan'] == 'ya') {
                    $sql_bawahan = "INSERT INTO ukuran_bawahan 
                                   (id_pesanan, id_pelanggan, pinggang, pinggul, kres, paha, lutut, l_bawah, panjang, keterangan) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    $stmt_bawahan = $pdo->prepare($sql_bawahan);
                    $stmt_bawahan->execute([
                        $id_pesanan,
                        $pesanan_data['id_pelanggan'],
                        $item['pinggang_bawahan'] ?? 0,
                        $item['pinggul_bawahan'] ?? 0,
                        $item['kres'] ?? 0,
                        $item['paha'] ?? 0,
                        $item['lutut'] ?? 0,
                        $item['l_bawah'] ?? 0,
                        $item['panjang_bawahan'] ?? 0,
                        $item['keterangan_bawahan'] ?? ''
                    ]);
                }
                
                $item_counter++;
            }
            
            $pdo->commit();
            
            // Hapus session data
            unset($_SESSION['pesanan_data']);
            unset($_SESSION['detail_pakaian']);
            unset($_SESSION['current_step']);
            
            $_SESSION['success'] = "✅ Pesanan berhasil ditambahkan!";
            log_activity("Menambah pesanan baru ID: $id_pesanan dengan $total_kuantitas items");
            
            header("Location: pesanan.php");
            exit();
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "❌ Gagal menambah pesanan: " . $e->getMessage();
            header("Location: tambahpesanan.php");
            exit();
        }
    }
}

// Ambil data pelanggan dan karyawan
try {
    $pelanggan = getAll("SELECT id_pelanggan, nama, no_hp FROM data_pelanggan ORDER BY nama");
    $karyawan = getAll("SELECT id_user, nama_lengkap FROM users WHERE role IN ('admin', 'pegawai') ORDER BY nama_lengkap");
} catch (PDOException $e) {
    $_SESSION['error'] = "❌ Gagal memuat data: " . $e->getMessage();
    $pelanggan = [];
    $karyawan = [];
}

// Tentukan step saat ini
$current_step = $_SESSION['current_step'] ?? 1;

// Inisialisasi variabel untuk menghindari error
$total_harga = 0;
$kuantitas = 0;

if ($current_step == 3 && isset($_SESSION['detail_pakaian'])) {
    $kuantitas = $_SESSION['pesanan_data']['kuantitas'];
    // Hitung total harga dari semua item
    foreach ($_SESSION['detail_pakaian'] as $item) {
        $total_harga += floatval($item['harga'] ?? 0);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Pesanan - SIM Parapatan Tailor</title>
     <link rel="shortcut icon" href="../assets/images/logoterakhir.png" type="image/x-icon" />

    <!-- Bootstrap 5.3.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="../includes/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
    :root {
        --primary: #4f46e5;
        --primary-dark: #4338ca;
        --success: #10b981;
        --warning: #f59e0b;
        --danger: #ef4444;
        --info: #3b82f6;
        --light: #f8fafc;
        --dark: #1f2937;
        --gray: #6b7280;
        --border: #e5e7eb;
    }
    
    body {
        font-family: 'Segoe UI', system-ui, sans-serif;
        font-size: 0.75rem;
        line-height: 1.4;
        color: var(--dark);
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        min-height: 100vh;
    }
    
    .container {
        max-width: 1500px;
        margin: 0 auto;
        padding: 1rem;
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }
    
    h2 {
        color: var(--dark);
        font-weight: 700;
        margin-bottom: 1.5rem;
        padding-bottom: 0.75rem;
        border-bottom: 2px solid var(--primary);
        font-size: 1.4rem;
    }
    
    .card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        padding: 1.25rem;
        margin-bottom: 1.5rem;
        border: none;
    }
    
    .card-header {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: white;
        border-radius: 10px 10px 0 0 !important;
        padding: 1rem 1.25rem;
        border: none;
        font-weight: 600;
        font-size: 0.9rem;
    }
    
    .btn {
        padding: 0.4rem 0.8rem;
        font-weight: 500;
        border-radius: 6px;
        transition: all 0.2s ease;
        font-size: 0.7rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        border: none;
        cursor: pointer;
    }
    
    .btn-primary {
        background: var(--primary);
        color: white;
    }
    
    .btn-secondary {
        background: var(--gray);
        color: white;
    }
    
    .btn-danger {
        background: var(--danger);
        color: white;
    }
    
    .btn-success {
        background: var(--success);
        color: white;
    }
    
    .btn-warning {
        background: var(--warning);
        color: white;
    }
    
    .btn-info {
        background: var(--info);
        color: white;
    }
    
    .btn:hover {
        opacity: 0.9;
        transform: translateY(-1px);
        box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    }
    
    .form-label {
        font-weight: 600;
        color: var(--dark);
        font-size: 0.7rem;
        margin-bottom: 0.3rem;
    }
    
    .form-control, .form-select {
        padding: 0.4rem 0.6rem;
        border: 1px solid var(--border);
        border-radius: 6px;
        font-size: 0.7rem;
        transition: all 0.3s ease;
        background: white;
    }
    
    .form-control:focus, .form-select:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1);
    }
    
    /* Select2 Custom Styling */
    .select2-container--default .select2-selection--single {
        border: 1px solid var(--border) !important;
        border-radius: 6px !important;
        height: 32px !important;
        padding: 0.2rem !important;
    }
    
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 20px !important;
        font-size: 0.7rem !important;
        padding-left: 0.4rem !important;
    }
    
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 30px !important;
    }
    
    .select2-dropdown {
        border: 1px solid var(--border) !important;
        border-radius: 6px !important;
        font-size: 0.7rem !important;
    }
    
    .select2-results__option {
        padding: 0.4rem 0.6rem !important;
        font-size: 0.7rem !important;
    }
    
    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: var(--primary) !important;
    }
    
    .alert {
        margin-top: 10px;
        border-radius: 8px;
        border: none;
        font-weight: 500;
        font-size: 0.7rem;
        padding: 0.8rem 1rem;
    }
    
    .alert-success { 
        background: #f0fdf4; 
        color: #166534; 
        border-left: 3px solid var(--success);
    }
    
    .alert-danger { 
        background: #fef2f2; 
        color: #991b1b; 
        border-left: 3px solid var(--danger);
    }
    
    .alert-info { 
        background: #f0f9ff; 
        color: #0c4a6e; 
        border-left: 3px solid var(--info);
    }
    
    /* Step Indicator */
    .step-indicator {
        display: flex;
        justify-content: space-between;
        margin-bottom: 2rem;
        position: relative;
    }
    
    .step-indicator::before {
        content: '';
        position: absolute;
        top: 15px;
        left: 0;
        right: 0;
        height: 2px;
        background: #e5e7eb;
        z-index: 1;
    }
    
    .step {
        display: flex;
        flex-direction: column;
        align-items: center;
        position: relative;
        z-index: 2;
    }
    
    .step-number {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: #e5e7eb;
        color: #6b7280;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        margin-bottom: 0.5rem;
        font-size: 0.8rem;
    }
    
    .step.active .step-number {
        background: var(--primary);
        color: white;
    }
    
    .step.completed .step-number {
        background: var(--success);
        color: white;
    }
    
    .step-label {
        font-size: 0.7rem;
        font-weight: 500;
        color: #6b7280;
        text-align: center;
    }
    
    .step.active .step-label {
        color: var(--primary);
    }
    
    .step.completed .step-label {
        color: var(--success);
    }
    
    /* Ukuran Sections */
    .ukuran-section {
        background: #f8fafc;
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 1rem;
        border: 1px solid var(--border);
    }
    
    .ukuran-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 0.8rem;
        margin-top: 0.8rem;
    }
    
    .ukuran-item {
        background: white;
        padding: 0.8rem;
        border-radius: 6px;
        border: 1px solid var(--border);
        transition: all 0.3s ease;
    }
    
    .ukuran-item:focus-within {
        border-color: var(--primary);
        box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1);
    }
    
    .ukuran-label {
        font-weight: 600;
        color: var(--dark);
        font-size: 0.65rem;
        margin-bottom: 0.3rem;
        display: block;
    }
    
    .ukuran-input {
        width: 100%;
        padding: 0.4rem;
        border: 1px solid #d1d5db;
        border-radius: 4px;
        font-size: 0.7rem;
        transition: all 0.3s ease;
    }
    
    .ukuran-input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1);
    }
    
    .ukuran-input.error {
        border-color: var(--danger);
        background-color: #fef2f2;
    }
    
    /* Item Cards */
    .item-card {
        background: white;
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 1rem;
        border: 1px solid var(--border);
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
    }
    
    .item-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    
    .item-header {
        display: flex;
        align-items: center;
        margin-bottom: 1rem;
        padding-bottom: 0.8rem;
        border-bottom: 1px solid var(--border);
    }
    
    .item-counter {
        background: var(--primary);
        color: white;
        border-radius: 50%;
        width: 25px;
        height: 25px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.7rem;
        font-weight: bold;
        margin-right: 0.8rem;
    }
    
    /* Checkbox Group */
    .checkbox-group {
        display: flex;
        gap: 1rem;
        margin-bottom: 1rem;
        flex-wrap: wrap;
    }
    
    .form-check {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        background: #f8f9fa;
        padding: 0.6rem 0.8rem;
        border-radius: 6px;
        border: 1px solid var(--border);
    }
    
    .form-check-input {
        margin-top: 0;
    }
    
    .form-check-label {
        font-size: 0.7rem;
        font-weight: 500;
    }
    
    /* Payment Sections */
    .payment-summary {
        background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
        border-radius: 8px;
        padding: 1.2rem;
        margin: 1.2rem 0;
        border: 1px solid #bae6fd;
    }
    
    .payment-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.5rem 0;
        border-bottom: 1px solid #e0f2fe;
        font-size: 0.75rem;
    }
    
    .payment-item:last-child {
        border-bottom: none;
    }
    
    .payment-label {
        font-weight: 500;
        color: #0c4a6e;
    }
    
    .payment-value {
        font-weight: 600;
    }
    
    .payment-value.total {
        color: var(--success);
        font-size: 0.9rem;
    }
    
    /* Summary Cards */
    .summary-card {
        background: linear-gradient(135deg, #fef3c7, #fde68a);
        border-radius: 8px;
        padding: 1.2rem;
        margin: 1.2rem 0;
        border: 1px solid #fcd34d;
    }
    
    .summary-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.4rem 0;
        font-size: 0.75rem;
    }
    
    .summary-label {
        font-weight: 500;
        color: #92400e;
    }
    
    .summary-value {
        font-weight: 700;
        color: #92400e;
    }
    
    /* Items Summary */
    .items-summary {
        background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
        border-radius: 8px;
        padding: 1.2rem;
        margin: 1.2rem 0;
        border: 1px solid #bae6fd;
    }
    
    .item-summary {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.6rem 0;
        border-bottom: 1px solid #e0f2fe;
        font-size: 0.75rem;
    }
    
    .item-summary:last-child {
        border-bottom: none;
    }
    
    .item-summary-name {
        font-weight: 500;
        color: #0c4a6e;
    }
    
    .item-summary-price {
        font-weight: 600;
        color: var(--success);
    }

    /* 'Item lainnya' quick link */
    .more-items a.show-all-items {
        width: 100%;
        padding: 0.35rem 0.25rem;
        border-radius: 6px;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: var(--gray);
    }

    .more-items a.show-all-items:hover {
        background: rgba(59, 130, 246, 0.06);
        text-decoration: none;
    }
    
    /* Dynamic Items Container */
    .dynamic-items-container {
        max-height: 600px;
        overflow-y: auto;
        padding-right: 0.8rem;
    }
    
    .item-actions {
        display: flex;
        gap: 0.4rem;
        margin-top: 0.8rem;
    }
    
    .btn-sm {
        padding: 0.3rem 0.6rem;
        font-size: 0.65rem;
    }
    
    /* Auto-focus highlight */
    .ukuran-input.auto-focus {
        border-color: var(--primary);
        background-color: #f0f9ff;
        box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.2);
    }
    
    /* Progress indicator untuk ukuran */
    .ukuran-progress {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.8rem;
        font-size: 0.7rem;
        color: var(--gray);
    }
    
    .ukuran-progress-bar {
        flex: 1;
        height: 4px;
        background: #e5e7eb;
        border-radius: 2px;
        overflow: hidden;
    }
    
    .ukuran-progress-fill {
        height: 100%;
        background: var(--primary);
        border-radius: 2px;
        transition: width 0.3s ease;
    }
    
    .ukuran-progress-warning {
        background: var(--warning);
    }
    
    .ukuran-progress-danger {
        background: var(--danger);
    }
    
    /* Required field indicator */
    .required-field::after {
        content: " *";
        color: var(--danger);
    }
    
    /* Validation messages */
    .validation-message {
        font-size: 0.65rem;
        margin-top: 0.25rem;
    }
    
    .validation-message.text-danger {
        color: var(--danger) !important;
    }
    
    .validation-message.text-success {
        color: var(--success) !important;
    }
    
    /* Navigation Buttons */
    .navigation-buttons {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 1.5rem;
        padding-top: 1rem;
        border-top: 1px solid var(--border);
    }
    
    .nav-btn-group {
        display: flex;
        gap: 0.5rem;
    }
    
    /* Error messages */
    .error-message {
        color: var(--danger);
        font-size: 0.7rem;
        margin-top: 0.3rem;
        display: none;
    }
    
    .error-message.show {
        display: block;
    }
    
    /* Validation summary */
    .validation-summary {
        background: #fef2f2;
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 1.5rem;
        border-left: 4px solid var(--danger);
    }
    
    .validation-summary h6 {
        color: var(--danger);
        font-size: 0.8rem;
        margin-bottom: 0.5rem;
    }
    
    .validation-summary ul {
        margin: 0;
        padding-left: 1.2rem;
        font-size: 0.7rem;
    }
    
    .validation-summary li {
        margin-bottom: 0.3rem;
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
        .step-indicator {
            flex-direction: column;
            gap: 1rem;
        }
        
        .step-indicator::before {
            display: none;
        }
        
        .step {
            flex-direction: row;
            gap: 0.8rem;
        }
        
        .ukuran-grid {
            grid-template-columns: 1fr 1fr;
        }
        
        .checkbox-group {
            flex-direction: column;
            gap: 0.6rem;
        }
        
        .dynamic-items-container {
            max-height: 400px;
        }
        
        .container {
            padding: 0.8rem;
        }
        
        .navigation-buttons {
            flex-direction: column;
            gap: 0.8rem;
        }
        
        .nav-btn-group {
            width: 100%;
        }
        
        .nav-btn-group .btn {
            flex: 1;
            justify-content: center;
        }
    }
    
    @media (max-width: 480px) {
        .ukuran-grid {
            grid-template-columns: 1fr;
        }
        
        .btn {
            width: 100%;
            justify-content: center;
        }
        
        .item-actions {
            flex-direction: column;
        }
    }
    
    .main-content {
        min-height: calc(100vh - 60px);
        display: flex;
        flex-direction: column;
    }
    
    .content-body {
        flex: 1;
    }
    </style>
</head>

<body>
    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/header.php'; ?>

    <div class="main-content">
        <div class="content-body">
            <div class="container">
                <h2 class="my-3">
                    <i class="fas fa-plus-circle"></i> Tambah Pesanan Baru
                </h2>

                <!-- Alert Pesan -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <!-- Step Indicator -->
                <div class="step-indicator">
                    <div class="step <?= $current_step >= 1 ? 'active' : ''; ?> <?= $current_step > 1 ? 'completed' : ''; ?>">
                        <div class="step-number">1</div>
                        <div class="step-label">Data Dasar</div>
                    </div>
                    <div class="step <?= $current_step >= 2 ? 'active' : ''; ?> <?= $current_step > 2 ? 'completed' : ''; ?>">
                        <div class="step-number">2</div>
                        <div class="step-label">Detail Pakaian</div>
                    </div>
                    <div class="step <?= $current_step >= 3 ? 'active' : ''; ?>">
                        <div class="step-number">3</div>
                        <div class="step-label">Pembayaran</div>
                    </div>
                </div>

                <!-- Step 1: Data Dasar -->
                <?php if ($current_step == 1): ?>
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-info-circle"></i> Data Dasar Pesanan
                    </div>
                    <div class="card-body">
                        <form method="POST" id="step1Form">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="id_pelanggan" class="form-label required-field">Pelanggan</label>
                                    <select class="form-control select2-pelanggan" id="id_pelanggan" name="id_pelanggan" required>
                                        <option value="">Pilih atau cari pelanggan...</option>
                                        <?php foreach ($pelanggan as $p): ?>
                                            <option value="<?= $p['id_pelanggan']; ?>" 
                                                <?= isset($_SESSION['pesanan_data']['id_pelanggan']) && $_SESSION['pesanan_data']['id_pelanggan'] == $p['id_pelanggan'] ? 'selected' : ''; ?>
                                                data-nama="<?= htmlspecialchars($p['nama']); ?>"
                                                data-telepon="<?= htmlspecialchars($p['no_hp']); ?>">
                                                <?= htmlspecialchars($p['nama']); ?> - <?= htmlspecialchars($p['no_hp']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="validation-message text-muted">
                                        Ketik nama untuk mencari atau pilih pelanggan
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="id_karyawan" class="form-label required-field">Karyawan</label>
                                    <select class="form-control" id="id_karyawan" name="id_karyawan" required>
                                        <option value="">Pilih Karyawan</option>
                                        <?php foreach ($karyawan as $k): ?>
                                            <option value="<?= $k['id_user']; ?>"
                                                <?= isset($_SESSION['pesanan_data']['id_karyawan']) && $_SESSION['pesanan_data']['id_karyawan'] == $k['id_user'] ? 'selected' : ''; ?>>
                                                <?= htmlspecialchars($k['nama_lengkap']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="validation-message text-muted">
                                        Pilih karyawan yang menangani pesanan
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="tgl_pesanan" class="form-label required-field">Tanggal Pesan</label>
                                    <input type="date" class="form-control" id="tgl_pesanan" name="tgl_pesanan" 
                                           value="<?= $_SESSION['pesanan_data']['tgl_pesanan'] ?? date('Y-m-d'); ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="tgl_selesai" class="form-label required-field">Tanggal Selesai</label>
                                    <div class="date-input-container">
                                        <input type="date" class="form-control" id="tgl_selesai" name="tgl_selesai" 
                                               value="<?= $_SESSION['pesanan_data']['tgl_selesai'] ?? ''; ?>" required>
                                    </div>
                                    <div class="validation-message text-muted" id="tgl_selesai_message">
                                        Tanggal selesai harus setelah tanggal pesan
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="kuantitas" class="form-label required-field">Jumlah Item</label>
                                    <input type="number" class="form-control" id="kuantitas" name="kuantitas" 
                                           min="1" max="20" value="<?= $_SESSION['pesanan_data']['kuantitas'] ?? 1; ?>" required>
                                    <div class="validation-message text-muted">
                                        Maksimal 20 item per pesanan
                                    </div>
                                </div>
                            </div>
                            
                            <div class="navigation-buttons">
                                <a href="pesanan.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Kembali ke Daftar
                                </a>
                                <div class="nav-btn-group">
                                    <button type="submit" name="step1_submit" class="btn btn-primary">
                                        Selanjutnya <i class="fas fa-arrow-right"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Step 2: Detail Pakaian -->
                <?php if ($current_step == 2): ?>
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-tshirt"></i> Detail Pakaian</span>
                            <div class="d-flex gap-2">
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 
                            <strong>Petunjuk Pengisian:</strong> 
                            <ul class="mb-0" style="padding-left: 1.2rem; font-size: 0.7rem;">
                                <li>Setelah mengisi field ukuran, tekan <kbd>Tab</kbd> atau <kbd>Enter</kbd> untuk langsung pindah ke field berikutnya</li>
                                <li>Semua field ukuran yang dipilih <strong>harus diisi lengkap</strong> sebelum lanjut ke pembayaran</li>
                                <li>Pilih minimal satu jenis ukuran (Atasan atau Bawahan) untuk setiap item</li>
                            </ul>
                        </div>
                        
                        
                        <form method="POST" id="step2Form">
                            <div class="dynamic-items-container" id="itemsContainer">
                                <?php 
                                $kuantitas = $_SESSION['pesanan_data']['kuantitas'];
                                for ($i = 1; $i <= $kuantitas; $i++): 
                                    $item_data = $_SESSION['detail_pakaian'][$i] ?? [];
                                ?>
                                <div class="item-card" data-item-index="<?= $i; ?>">
                                    <div class="item-header">
                                        <div class="item-counter"><?= $i; ?></div>
                                        <h5 class="mb-0" style="font-size: 0.9rem;">Item <?= $i; ?></h5>
                                        <div class="ms-auto">
                                            <span class="badge bg-info" id="itemStatus_<?= $i; ?>">Belum Lengkap</span>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label required-field">Jenis Pakaian</label>
                                            <select class="form-control jenis-pakaian-select" 
                                                    name="detail_pakaian[<?= $i; ?>][jenis_pakaian]" 
                                                    data-item="<?= $i; ?>"
                                                    required>
                                                <option value="">Pilih Jenis</option>
                                                <option value="Kemeja" <?= ($item_data['jenis_pakaian'] ?? '') == 'Kemeja' ? 'selected' : ''; ?>>Kemeja</option>
                                                <option value="Celana" <?= ($item_data['jenis_pakaian'] ?? '') == 'Celana' ? 'selected' : ''; ?>>Celana</option>
                                                <option value="Jas" <?= ($item_data['jenis_pakaian'] ?? '') == 'Jas' ? 'selected' : ''; ?>>Jas</option>
                                                <option value="Blazer" <?= ($item_data['jenis_pakaian'] ?? '') == 'Blazer' ? 'selected' : ''; ?>>Blazer</option>
                                                <option value="Rok" <?= ($item_data['jenis_pakaian'] ?? '') == 'Rok' ? 'selected' : ''; ?>>Rok</option>
                                                <option value="Baju Muslim" <?= ($item_data['jenis_pakaian'] ?? '') == 'Baju Muslim' ? 'selected' : ''; ?>>Baju Muslim</option>
                                                <option value="Setelan" <?= ($item_data['jenis_pakaian'] ?? '') == 'Setelan' ? 'selected' : ''; ?>>Setelan (Atasan + Bawahan)</option>
                                                <option value="Lainnya">Lainnya</option>
                                            </select>
                                            <div class="error-message" id="jenis_pakaian_error_<?= $i; ?>">
                                                Jenis pakaian harus dipilih
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="bahan_<?= $i; ?>" class="form-label required-field">Bahan</label>
                                            <input type="text" class="form-control" id="bahan_<?= $i; ?>" 
                                                   name="detail_pakaian[<?= $i; ?>][bahan]" 
                                                   value="<?= htmlspecialchars($item_data['bahan'] ?? ''); ?>" 
                                                   required placeholder="Contoh: Katun, Linen, Wol, dll.">
                                            <div class="error-message" id="bahan_error_<?= $i; ?>">
                                                Bahan harus diisi
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="harga_<?= $i; ?>" class="form-label required-field">Harga (Rp)</label>
                                            <input type="number" class="form-control" id="harga_<?= $i; ?>" 
                                                   name="detail_pakaian[<?= $i; ?>][harga]" 
                                                   min="1000" step="1000" 
                                                   value="<?= $item_data['harga'] ?? ''; ?>" 
                                                   placeholder="1000" required>
                                            <div class="error-message" id="harga_error_<?= $i; ?>">
                                                Harga harus diisi (minimal Rp 1.000)
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Jenis Ukuran</label>
                                            <div class="checkbox-group">
                                                <div class="form-check">
                                                    <input class="form-check-input ukuran-atasan-check" type="checkbox" 
                                                           id="ukuran_atasan_<?= $i; ?>" 
                                                           name="detail_pakaian[<?= $i; ?>][ukuran_atasan]" value="ya"
                                                           data-item="<?= $i; ?>"
                                                           <?= isset($item_data['ukuran_atasan']) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="ukuran_atasan_<?= $i; ?>">
                                                        Ukuran Atasan
                                                    </label>
                                                </div>
                                                
                                                <div class="form-check">
                                                    <input class="form-check-input ukuran-bawahan-check" type="checkbox" 
                                                           id="ukuran_bawahan_<?= $i; ?>" 
                                                           name="detail_pakaian[<?= $i; ?>][ukuran_bawahan]" value="ya"
                                                           data-item="<?= $i; ?>"
                                                           <?= isset($item_data['ukuran_bawahan']) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="ukuran_bawahan_<?= $i; ?>">
                                                        Ukuran Bawahan
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="error-message" id="ukuran_error_<?= $i; ?>">
                                                Pilih minimal satu jenis ukuran
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Ukuran Atasan -->
                                    <div class="ukuran-atasan-section ukuran-section" id="atasan_section_<?= $i; ?>" 
                                         style="display: <?= isset($item_data['ukuran_atasan']) ? 'block' : 'none'; ?>;">
                                        <h6 style="font-size: 0.8rem;"><i class="fas fa-tshirt"></i> Ukuran Atasan (cm)</h6>
                                        
                                        <div class="ukuran-progress">
                                            <span>Progress: </span>
                                            <div class="ukuran-progress-bar">
                                                <div class="ukuran-progress-fill" id="atasan_progress_<?= $i; ?>"></div>
                                            </div>
                                            <span id="atasan_progress_text_<?= $i; ?>">0/8</span>
                                        </div>
                                        
                                        <div class="ukuran-grid">
                                            <?php 
                                            $ukuran_fields_atasan = [
                                                'krah' => 'Krah',
                                                'pundak' => 'Pundak', 
                                                'tangan' => 'Tangan',
                                                'ld_lp' => 'Lingkar Dada/Pinggang',
                                                'badan' => 'Badan',
                                                'pinggang' => 'Lingkar Pinggang',
                                                'pinggul' => 'Lingkar Pinggul',
                                                'panjang_atasan' => 'Panjang'
                                            ];
                                            $field_index = 0;
                                            foreach ($ukuran_fields_atasan as $field => $label):
                                                $field_index++;
                                            ?>
                                            <div class="ukuran-item" data-field-index="<?= $field_index; ?>">
                                                <label class="ukuran-label required-field"><?= $label; ?></label>
                                                <input type="number" class="ukuran-input atasan-input" 
                                                       step="0.1" min="0.1"
                                                       name="detail_pakaian[<?= $i; ?>][<?= $field; ?>]" 
                                                       value="<?= $item_data[$field] ?? ''; ?>" 
                                                       placeholder="0.0"
                                                       data-item="<?= $i; ?>"
                                                       data-field="<?= $field; ?>"
                                                       data-next-field="<?= $field_index < count($ukuran_fields_atasan) ? $field_index + 1 : ''; ?>">
                                                <div class="error-message" id="atasan_<?= $field ?>_error_<?= $i; ?>">
                                                    Ukuran <?= strtolower($label); ?> harus diisi
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="mt-2">
                                            <label class="form-label">Keterangan Atasan</label>
                                            <textarea class="form-control" name="detail_pakaian[<?= $i; ?>][keterangan_atasan]" 
                                                      rows="2" placeholder="Keterangan khusus untuk ukuran atasan..."><?= htmlspecialchars($item_data['keterangan_atasan'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                    
                                    <!-- Ukuran Bawahan -->
                                    <div class="ukuran-bawahan-section ukuran-section" id="bawahan_section_<?= $i; ?>" 
                                         style="display: <?= isset($item_data['ukuran_bawahan']) ? 'block' : 'none'; ?>;">
                                        <h6 style="font-size: 0.8rem;"><i class="fas fa-vest"></i> Ukuran Bawahan (cm)</h6>
                                        
                                        <div class="ukuran-progress">
                                            <span>Progress: </span>
                                            <div class="ukuran-progress-bar">
                                                <div class="ukuran-progress-fill" id="bawahan_progress_<?= $i; ?>"></div>
                                            </div>
                                            <span id="bawahan_progress_text_<?= $i; ?>">0/7</span>
                                        </div>
                                        
                                        <div class="ukuran-grid">
                                            <?php 
                                            $ukuran_fields_bawahan = [
                                                'pinggang_bawahan' => 'Pinggang',
                                                'pinggul_bawahan' => 'Pinggul',
                                                'kres' => 'Kres',
                                                'paha' => 'Paha',
                                                'lutut' => 'Lutut',
                                                'l_bawah' => 'L. Bawah',
                                                'panjang_bawahan' => 'Panjang'
                                            ];
                                            $field_index = 0;
                                            foreach ($ukuran_fields_bawahan as $field => $label):
                                                $field_index++;
                                            ?>
                                            <div class="ukuran-item" data-field-index="<?= $field_index; ?>">
                                                <label class="ukuran-label required-field"><?= $label; ?></label>
                                                <input type="number" class="ukuran-input bawahan-input" 
                                                       step="0.1" min="0.1"
                                                       name="detail_pakaian[<?= $i; ?>][<?= $field; ?>]" 
                                                       value="<?= $item_data[$field] ?? ''; ?>" 
                                                       placeholder="0.0"
                                                       data-item="<?= $i; ?>"
                                                       data-field="<?= $field; ?>"
                                                       data-next-field="<?= $field_index < count($ukuran_fields_bawahan) ? $field_index + 1 : ''; ?>">
                                                <div class="error-message" id="bawahan_<?= $field ?>_error_<?= $i; ?>">
                                                    Ukuran <?= strtolower($label); ?> harus diisi
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="mt-2">
                                            <label class="form-label">Keterangan Bawahan</label>
                                            <textarea class="form-control" name="detail_pakaian[<?= $i; ?>][keterangan_bawahan]" 
                                                      rows="2" placeholder="Keterangan khusus untuk ukuran bawahan..."><?= htmlspecialchars($item_data['keterangan_bawahan'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                </div>
                                <?php endfor; ?>
                            </div>
                            
                            <div class="navigation-buttons">
                                <div class="nav-btn-group">
                                    <button type="submit" name="back_to_step1" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left"></i> Kembali ke Data Dasar
                                    </button>
                                </div>
                                <div class="nav-btn-group">
                                    <button type="submit" name="step2_submit" class="btn btn-primary" id="submitStep2">
                                        Selanjutnya <i class="fas fa-arrow-right"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Step 3: Harga & Pembayaran -->
                <?php if ($current_step == 3): ?>
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-money-bill-wave"></i> Pembayaran
                    </div>
                    <div class="card-body">
                        <form method="POST" id="step3Form">
                            <!-- Ringkasan Pesanan -->
                            <div class="summary-card">
                                <h5 class="mb-3" style="font-size: 0.9rem;"><i class="fas fa-clipboard-list"></i> Ringkasan Pesanan</h5>
                                
                                <div class="summary-item">
                                    <span class="summary-label">Pelanggan:</span>
                                    <span class="summary-value">
                                        <?php 
                                        $id_pelanggan = $_SESSION['pesanan_data']['id_pelanggan'];
                                        $pelanggan_data = getSingle("SELECT nama FROM data_pelanggan WHERE id_pelanggan = ?", [$id_pelanggan]);
                                        echo htmlspecialchars($pelanggan_data['nama'] ?? '-');
                                        ?>
                                    </span>
                                </div>
                                
                                <div class="summary-item">
                                    <span class="summary-label">Karyawan:</span>
                                    <span class="summary-value">
                                        <?php 
                                        $id_karyawan = $_SESSION['pesanan_data']['id_karyawan'];
                                        $karyawan_data = getSingle("SELECT nama_lengkap FROM users WHERE id_user = ?", [$id_karyawan]);
                                        echo htmlspecialchars($karyawan_data['nama_lengkap'] ?? '-');
                                        ?>
                                    </span>
                                </div>
                                
                                <div class="summary-item">
                                    <span class="summary-label">Total Item:</span>
                                    <span class="summary-value"><?= $kuantitas; ?> item</span>
                                </div>
                                
                                <div class="summary-item">
                                    <span class="summary-label">Tanggal Pesan:</span>
                                    <span class="summary-value"><?= date('d/m/Y', strtotime($_SESSION['pesanan_data']['tgl_pesanan'])); ?></span>
                                </div>
                                
                                <div class="summary-item">
                                    <span class="summary-label">Tanggal Selesai:</span>
                                    <span class="summary-value"><?= date('d/m/Y', strtotime($_SESSION['pesanan_data']['tgl_selesai'])); ?></span>
                                </div>
                            </div>

                            <!-- Ringkasan Item -->
                            <div class="items-summary">
                                <h5 class="mb-3" style="font-size: 0.9rem;"><i class="fas fa-list"></i> Detail Item</h5>

                                <?php
                                    $items = $_SESSION['detail_pakaian'] ?? [];
                                    $display_limit = min(2, count($items));
                                    
                                    // Tampilkan item mulai dari index 1
                                    $counter = 0;
                                    for ($i = 1; $i <= count($items) && $counter < $display_limit; $i++):
                                        if (isset($items[$i])):
                                            $item = $items[$i];
                                            $counter++;
                                ?>
                                <div class="item-summary">
                                    <div style="flex: 1;">
                                        <div class="item-summary-name">
                                            <strong><?= htmlspecialchars($item['jenis_pakaian'] ?? 'Item ' . $i); ?></strong>
                                            - <?= htmlspecialchars($item['bahan'] ?? ''); ?>
                                        </div>
                                    </div>
                                    <span class="item-summary-price">Rp <?= number_format($item['harga'] ?? 0, 0, ',', '.'); ?></span>
                                </div>
                                <?php 
                                        endif;
                                    endfor; 
                                ?>

                                <?php if (count($items) > $display_limit): ?>
                                <div class="item-summary more-items" style="background: #f8fafc;">
                                    <a href="#" class="show-all-items" data-bs-toggle="modal" data-bs-target="#allItemsModal" style="display:flex;gap:0.6rem;align-items:center;text-decoration:none;color:var(--gray);width:100%;">
                                        <span class="item-badge" style="background: var(--gray);">+</span>
                                        <span class="item-name"><?= count($items) - $display_limit; ?> item lainnya</span>
                                        <span style="margin-left:auto;color:var(--primary);font-weight:600;">Tampilkan Semua</span>
                                    </a>
                                </div>
                                <?php endif; ?>

                                <div class="item-summary" style="border-top: 2px solid #0c4a6e; padding-top: 12px;">
                                    <span class="item-summary-name" style="font-weight: bold;">TOTAL</span>
                                    <span class="item-summary-price" style="font-size: 0.85rem; color: #059669;">Rp <?= number_format($total_harga, 0, ',', '.'); ?></span>
                                </div>
                            </div>

                            <!-- All Items Modal -->
                            <div class="modal fade" id="allItemsModal" tabindex="-1" aria-labelledby="allItemsModalLabel" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered modal-md">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="allItemsModalLabel"><i class="fas fa-list"></i> Semua Items Pesanan</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <?php if (!empty($items)): ?>
                                                <div class="list-group">
                                                    <?php 
                                                    foreach ($items as $idx => $it):
                                                        // Skip jika indeks 0 (karena array dimulai dari 1)
                                                        if ($idx == 0) continue;
                                                    ?>
                                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                                            <div>
                                                                <span class="badge bg-primary me-2"><?= $idx; ?></span>
                                                                <strong><?= htmlspecialchars($it['jenis_pakaian'] ?? 'Item ' . $idx); ?></strong>
                                                                <div class="text-muted small"><?= htmlspecialchars($it['bahan'] ?? ''); ?></div>
                                                            </div>
                                                            <div class="text-end">
                                                                <?php if (!empty($it['jumlah'])): ?><div class="small"><?= $it['jumlah']; ?> pcs</div><?php endif; ?>
                                                                <div class="fw-bold">Rp <?= number_format($it['harga'] ?? 0, 0, ',', '.'); ?></div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                <div class="mt-3 text-end">
                                                    <small class="text-muted">Total Items: <?= count($items) - 1; ?> item • Total Harga: Rp <?= number_format($total_harga, 0, ',', '.'); ?></small>
                                                </div>
                                            <?php else: ?>
                                                <p class="text-muted mb-0">Tidak ada item</p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Informasi Pembayaran -->
                            <div class="payment-summary">
                                <h5 class="mb-3" style="font-size: 0.9rem;"><i class="fas fa-money-bill-wave"></i> Informasi Pembayaran</h5>
                                
                                <div class="payment-item">
                                    <span class="payment-label">Total Harga:</span>
                                    <span class="payment-value total">Rp <?= number_format($total_harga, 0, ',', '.'); ?></span>
                                </div>
                                
                                <div class="row mt-3">
                                    <div class="col-md-6 mb-3">
                                        <label for="jumlah_bayar" class="form-label required-field">Jumlah Bayar (Rp)</label>
                                        <input type="number" class="form-control payment-update" id="jumlah_bayar" name="jumlah_bayar" 
                                               min="0" max="<?= $total_harga; ?>" step="1000" 
                                               value="0" required>
                                        <div class="validation-message text-muted">
                                            Masukkan jumlah yang dibayar sekarang
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="sisa_bayar" class="form-label">Sisa Bayar (Rp)</label>
                                        <input type="text" class="form-control payment-update" id="sisa_bayar" readonly 
                                               value="Rp <?= number_format($total_harga, 0, ',', '.'); ?>">
                                        <div class="validation-message text-muted">
                                            Sisa bayar dihitung otomatis
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="metode_pembayaran" class="form-label required-field">Metode Pembayaran</label>
                                        <select class="form-control" id="metode_pembayaran" name="metode_pembayaran" required>
                                            <option value="tunai">Tunai</option>
                                            <option value="transfer">Transfer Bank</option>
                                            <option value="qris">QRIS</option>
                                            <option value="kredit">Kartu Kredit</option>
                                            <option value="debit">Kartu Debit</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="status_pesanan" class="form-label required-field">Status Pesanan</label>
                                        <select class="form-control" id="status_pesanan" name="status_pesanan" required>
                                            <option value="belum">Belum Diproses</option>
                                            <option value="dalam_proses">Dalam Proses</option>
                                            <option value="selesai">Selesai</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="navigation-buttons">
                                <div class="nav-btn-group">
                                    <button type="submit" name="back_to_step2" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left"></i> Kembali ke Detail Pakaian
                                    </button>
                                </div>
                                <div class="nav-btn-group">
                                    <button type="submit" name="step3_submit" class="btn btn-success">
                                        <i class="fas fa-save"></i> Simpan Pesanan
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Footer -->
        <?php include '../includes/footer.php'; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // ========== STEP 1 FUNCTIONALITY ==========
            
            // ============================================================
            // IMPLEMENTASI SELECT2 UNTUK PELANGGAN
            // ============================================================
            $('.select2-pelanggan').select2({
                placeholder: "Pilih atau cari pelanggan...",
                allowClear: true,
                width: '100%'
            });
            
            // Enhanced date picker functionality
            const tglPesananInput = document.getElementById('tgl_pesanan');
            const tglSelesaiInput = document.getElementById('tgl_selesai');
            const tglSelesaiMessage = document.getElementById('tgl_selesai_message');
            
            if (tglPesananInput && tglSelesaiInput) {
                function validateDates() {
                    const tglPesanan = new Date(tglPesananInput.value);
                    const tglSelesai = new Date(tglSelesaiInput.value);
                    
                    if (tglSelesaiInput.value && tglSelesai <= tglPesanan) {
                        tglSelesaiInput.classList.add('is-invalid');
                        tglSelesaiMessage.classList.remove('text-muted');
                        tglSelesaiMessage.classList.add('text-danger');
                        tglSelesaiMessage.textContent = 'Tanggal selesai harus setelah tanggal pesan';
                        return false;
                    } else {
                        tglSelesaiInput.classList.remove('is-invalid');
                        tglSelesaiMessage.classList.remove('text-danger');
                        tglSelesaiMessage.classList.add('text-muted');
                        tglSelesaiMessage.textContent = 'Tanggal selesai harus setelah tanggal pesan';
                        return true;
                    }
                }
                
                // Enhanced date picker interaction
                tglPesananInput.addEventListener('focus', function() {
                    this.showPicker && this.showPicker();
                });
                
                tglSelesaiInput.addEventListener('focus', function() {
                    this.showPicker && this.showPicker();
                });
                
                // Click anywhere on the container to open date picker
                const dateContainers = document.querySelectorAll('.date-input-container');
                dateContainers.forEach(container => {
                    container.addEventListener('click', function(e) {
                        const input = this.querySelector('input[type="date"]');
                        if (input && e.target !== input) {
                            input.focus();
                            input.showPicker && input.showPicker();
                        }
                    });
                });
                
                tglPesananInput.addEventListener('change', function() {
                    tglSelesaiInput.min = this.value;
                    if (tglSelesaiInput.value && tglSelesaiInput.value < this.value) {
                        tglSelesaiInput.value = this.value;
                    }
                    validateDates();
                });
                
                tglSelesaiInput.addEventListener('change', validateDates);
                
                // Set min date untuk tanggal selesai
                if (tglPesananInput.value) {
                    tglSelesaiInput.min = tglPesananInput.value;
                }
                
                // Initial validation
                validateDates();
            }
            
            // Validasi kuantitas
            const kuantitasInput = document.getElementById('kuantitas');
            if (kuantitasInput) {
                kuantitasInput.addEventListener('input', function() {
                    if (this.value > 20) {
                        this.setCustomValidity('Kuantitas maksimal adalah 20');
                        this.classList.add('is-invalid');
                    } else {
                        this.setCustomValidity('');
                        this.classList.remove('is-invalid');
                    }
                });
            }

            // ========== STEP 2 FUNCTIONALITY ==========
            
            // Auto-detect ukuran berdasarkan jenis pakaian
            function setupJenisPakaianDetection() {
                const jenisPakaianSelects = document.querySelectorAll('.jenis-pakaian-select');
                
                jenisPakaianSelects.forEach(select => {
                    select.addEventListener('change', function() {
                        const itemId = this.getAttribute('data-item');
                        const jenisPakaian = this.value;
                        const atasanCheck = document.getElementById('ukuran_atasan_' + itemId);
                        const bawahanCheck = document.getElementById('ukuran_bawahan_' + itemId);
                        
                        // Reset semua checkbox
                        if (atasanCheck) atasanCheck.checked = false;
                        if (bawahanCheck) bawahanCheck.checked = false;
                        
                        // Auto-check berdasarkan jenis pakaian
                        switch(jenisPakaian) {
                            case 'Kemeja':
                            case 'Jas':
                            case 'Blazer':
                            case 'Baju Muslim':
                                if (atasanCheck) {
                                    atasanCheck.checked = true;
                                    atasanCheck.dispatchEvent(new Event('change'));
                                }
                                break;
                            case 'Celana':
                            case 'Rok':
                                if (bawahanCheck) {
                                    bawahanCheck.checked = true;
                                    bawahanCheck.dispatchEvent(new Event('change'));
                                }
                                break;
                            case 'Setelan':
                                if (atasanCheck && bawahanCheck) {
                                    atasanCheck.checked = true;
                                    bawahanCheck.checked = true;
                                    atasanCheck.dispatchEvent(new Event('change'));
                                    bawahanCheck.dispatchEvent(new Event('change'));
                                }
                                break;
                        }
                        
                        // Validasi setelah perubahan
                        validateItem(itemId);
                    });
                });
            }
            
            // Toggle ukuran sections
            function setupUkuranToggles() {
                const atasanChecks = document.querySelectorAll('.ukuran-atasan-check');
                const bawahanChecks = document.querySelectorAll('.ukuran-bawahan-check');
                
                atasanChecks.forEach(check => {
                    check.addEventListener('change', function() {
                        const itemId = this.getAttribute('data-item');
                        const section = document.getElementById('atasan_section_' + itemId);
                        section.style.display = this.checked ? 'block' : 'none';
                        
                        // Update progress
                        if (this.checked) {
                            updateUkuranProgress('atasan', itemId);
                        }
                        
                        // Validasi setelah perubahan
                        validateItem(itemId);
                    });
                });
                
                bawahanChecks.forEach(check => {
                    check.addEventListener('change', function() {
                        const itemId = this.getAttribute('data-item');
                        const section = document.getElementById('bawahan_section_' + itemId);
                        section.style.display = this.checked ? 'block' : 'none';
                        
                        // Update progress
                        if (this.checked) {
                            updateUkuranProgress('bawahan', itemId);
                        }
                        
                        // Validasi setelah perubahan
                        validateItem(itemId);
                    });
                });
            }
            
            // Auto-focus functionality untuk ukuran inputs
            function setupAutoFocus() {
                // Atasan inputs
                const atasanInputs = document.querySelectorAll('.atasan-input');
                atasanInputs.forEach(input => {
                    input.addEventListener('input', function() {
                        // Update progress
                        const itemId = this.getAttribute('data-item');
                        updateUkuranProgress('atasan', itemId);
                        validateItem(itemId);
                    });
                    
                    input.addEventListener('keydown', function(e) {
                        if (e.key === 'Tab' || e.key === 'Enter') {
                            e.preventDefault();
                            const nextFieldIndex = this.getAttribute('data-next-field');
                            if (nextFieldIndex) {
                                const nextInput = this.closest('.ukuran-grid').querySelector(`[data-field-index="${nextFieldIndex}"] input`);
                                if (nextInput) {
                                    nextInput.focus();
                                    nextInput.classList.add('auto-focus');
                                    setTimeout(() => nextInput.classList.remove('auto-focus'), 1000);
                                }
                            }
                        }
                    });
                });
                
                // Bawahan inputs
                const bawahanInputs = document.querySelectorAll('.bawahan-input');
                bawahanInputs.forEach(input => {
                    input.addEventListener('input', function() {
                        // Update progress
                        const itemId = this.getAttribute('data-item');
                        updateUkuranProgress('bawahan', itemId);
                        validateItem(itemId);
                    });
                    
                    input.addEventListener('keydown', function(e) {
                        if (e.key === 'Tab' || e.key === 'Enter') {
                            e.preventDefault();
                            const nextFieldIndex = this.getAttribute('data-next-field');
                            if (nextFieldIndex) {
                                const nextInput = this.closest('.ukuran-grid').querySelector(`[data-field-index="${nextFieldIndex}"] input`);
                                if (nextInput) {
                                    nextInput.focus();
                                    nextInput.classList.add('auto-focus');
                                    setTimeout(() => nextInput.classList.remove('auto-focus'), 1000);
                                }
                            }
                        }
                    });
                });
            }
            
            // Update progress bar untuk ukuran
            function updateUkuranProgress(type, itemId) {
                const inputs = document.querySelectorAll(`.${type}-input[data-item="${itemId}"]`);
                let filledCount = 0;
                let totalCount = inputs.length;
                
                inputs.forEach(input => {
                    if (input.value && parseFloat(input.value) > 0) {
                        filledCount++;
                    }
                });
                
                const progressFill = document.getElementById(`${type}_progress_${itemId}`);
                const progressText = document.getElementById(`${type}_progress_text_${itemId}`);
                
                if (progressFill && progressText) {
                    const percentage = (filledCount / totalCount) * 100;
                    progressFill.style.width = percentage + '%';
                    progressText.textContent = `${filledCount}/${totalCount}`;
                    
                    // Update warna progress bar berdasarkan kelengkapan
                    if (percentage === 100) {
                        progressFill.classList.remove('ukuran-progress-warning', 'ukuran-progress-danger');
                        progressFill.style.background = 'var(--success)';
                    } else if (percentage >= 50) {
                        progressFill.classList.add('ukuran-progress-warning');
                        progressFill.classList.remove('ukuran-progress-danger');
                        progressFill.style.background = 'var(--warning)';
                    } else {
                        progressFill.classList.add('ukuran-progress-danger');
                        progressFill.classList.remove('ukuran-progress-warning');
                        progressFill.style.background = 'var(--danger)';
                    }
                }
            }
            
            // Validasi individual item
            function validateItem(itemId) {
                const errors = [];
                
                // Validasi jenis pakaian
                const jenisPakaianSelect = document.querySelector(`.jenis-pakaian-select[data-item="${itemId}"]`);
                if (!jenisPakaianSelect || !jenisPakaianSelect.value) {
                    errors.push(`Item ${itemId}: Jenis pakaian harus dipilih`);
                    showError(`jenis_pakaian_error_${itemId}`, true);
                } else {
                    showError(`jenis_pakaian_error_${itemId}`, false);
                }
                
                // Validasi bahan
                const bahanInput = document.getElementById(`bahan_${itemId}`);
                if (!bahanInput || !bahanInput.value.trim()) {
                    errors.push(`Item ${itemId}: Bahan harus diisi`);
                    showError(`bahan_error_${itemId}`, true);
                } else {
                    showError(`bahan_error_${itemId}`, false);
                }
                
                // Validasi harga
                const hargaInput = document.getElementById(`harga_${itemId}`);
                if (!hargaInput || !hargaInput.value || parseFloat(hargaInput.value) < 1000) {
                    errors.push(`Item ${itemId}: Harga harus diisi (minimal Rp 1.000)`);
                    showError(`harga_error_${itemId}`, true);
                } else {
                    showError(`harga_error_${itemId}`, false);
                }
                
                // Validasi jenis ukuran
                const atasanCheck = document.getElementById(`ukuran_atasan_${itemId}`);
                const bawahanCheck = document.getElementById(`ukuran_bawahan_${itemId}`);
                const hasAtasan = atasanCheck && atasanCheck.checked;
                const hasBawahan = bawahanCheck && bawahanCheck.checked;
                
                if (!hasAtasan && !hasBawahan) {
                    errors.push(`Item ${itemId}: Pilih minimal satu jenis ukuran (Atasan atau Bawahan)`);
                    showError(`ukuran_error_${itemId}`, true);
                } else {
                    showError(`ukuran_error_${itemId}`, false);
                }
                
                // Validasi ukuran atasan jika dipilih
                if (hasAtasan) {
                    const atasanFields = ['krah', 'pundak', 'tangan', 'ld_lp', 'badan', 'pinggang', 'pinggul', 'panjang_atasan'];
                    atasanFields.forEach(field => {
                        const input = document.querySelector(`input[name="detail_pakaian[${itemId}][${field}]"]`);
                        if (!input || !input.value || parseFloat(input.value) <= 0) {
                            errors.push(`Item ${itemId}: Ukuran atasan '${field.replace('_', ' ')}' harus diisi`);
                            showError(`atasan_${field}_error_${itemId}`, true);
                        } else {
                            showError(`atasan_${field}_error_${itemId}`, false);
                        }
                    });
                }
                
                // Validasi ukuran bawahan jika dipilih
                if (hasBawahan) {
                    const bawahanFields = ['pinggang_bawahan', 'pinggul_bawahan', 'kres', 'paha', 'lutut', 'l_bawah', 'panjang_bawahan'];
                    bawahanFields.forEach(field => {
                        const input = document.querySelector(`input[name="detail_pakaian[${itemId}][${field}]"]`);
                        if (!input || !input.value || parseFloat(input.value) <= 0) {
                            errors.push(`Item ${itemId}: Ukuran bawahan '${field.replace('_', ' ')}' harus diisi`);
                            showError(`bawahan_${field}_error_${itemId}`, true);
                        } else {
                            showError(`bawahan_${field}_error_${itemId}`, false);
                        }
                    });
                }
                
                // Update status item
                const itemStatus = document.getElementById(`itemStatus_${itemId}`);
                if (itemStatus) {
                    if (errors.length === 0) {
                        itemStatus.textContent = 'Lengkap';
                        itemStatus.className = 'badge bg-success';
                    } else {
                        itemStatus.textContent = 'Belum Lengkap';
                        itemStatus.className = 'badge bg-warning';
                    }
                }
                
                return errors;
            }
            
            // Tampilkan/sembunyikan pesan error
            function showError(errorId, show) {
                const errorElement = document.getElementById(errorId);
                if (errorElement) {
                    if (show) {
                        errorElement.classList.add('show');
                    } else {
                        errorElement.classList.remove('show');
                    }
                }
            }
            
            // Validasi semua item sebelum submit
            function validateAllItems() {
                const allErrors = [];
                const itemCards = document.querySelectorAll('.item-card');
                let allValid = true;
                
                itemCards.forEach(card => {
                    const itemId = card.getAttribute('data-item-index');
                    const errors = validateItem(itemId);
                    allErrors.push(...errors);
                    
                    if (errors.length > 0) {
                        allValid = false;
                    }
                });
                
                // Tampilkan summary error
                const validationSummary = document.getElementById('validationSummary');
                const validationErrors = document.getElementById('validationErrors');
                
                if (allErrors.length > 0) {
                    validationSummary.style.display = 'block';
                    validationErrors.innerHTML = '';
                    
                    // Hanya tampilkan 5 error pertama
                    allErrors.slice(0, 5).forEach(error => {
                        const li = document.createElement('li');
                        li.textContent = error;
                        validationErrors.appendChild(li);
                    });
                    
                    if (allErrors.length > 5) {
                        const li = document.createElement('li');
                        li.textContent = `... dan ${allErrors.length - 5} kesalahan lainnya`;
                        validationErrors.appendChild(li);
                    }
                    
                    // Scroll ke atas
                    validationSummary.scrollIntoView({ behavior: 'smooth' });
                } else {
                    validationSummary.style.display = 'none';
                }
                
                return allValid;
            }
            
            // Setup event listeners untuk validasi real-time
            function setupRealTimeValidation() {
                // Monitor perubahan pada semua input
                const inputs = document.querySelectorAll('input, select, textarea');
                inputs.forEach(input => {
                    if (input.name && input.name.includes('detail_pakaian')) {
                        // Ambil itemId dari name attribute
                        const match = input.name.match(/detail_pakaian\[(\d+)\]/);
                        if (match) {
                            const itemId = match[1];
                            input.addEventListener('input', function() {
                                validateItem(itemId);
                            });
                            input.addEventListener('change', function() {
                                validateItem(itemId);
                            });
                        }
                    }
                });
            }
            
            // Dynamic item management
            let currentItemCount = <?= $kuantitas; ?>;
            const itemsContainer = document.getElementById('itemsContainer');
            
            // Add item
            const addItemBtn = document.getElementById('addItemBtn');
            if (addItemBtn) {
                addItemBtn.addEventListener('click', function() {
                    if (currentItemCount >= 20) {
                        alert('Maksimal 20 item per pesanan');
                        return;
                    }
                    
                    currentItemCount++;
                    const newItem = createItemElement(currentItemCount);
                    itemsContainer.appendChild(newItem);
                    updateKuantitas();
                    setupJenisPakaianDetection();
                    setupUkuranToggles();
                    setupAutoFocus();
                    setupRealTimeValidation();
                });
            }
            
            // Remove item
            const removeItemBtn = document.getElementById('removeItemBtn');
            if (removeItemBtn) {
                removeItemBtn.addEventListener('click', function() {
                    if (currentItemCount <= 1) {
                        alert('Minimal 1 item per pesanan');
                        return;
                    }
                    
                    const lastItem = itemsContainer.lastElementChild;
                    if (lastItem) {
                        itemsContainer.removeChild(lastItem);
                        currentItemCount--;
                        updateKuantitas();
                    }
                });
            }
            
            function createItemElement(index) {
                const template = `
                    <div class="item-card" data-item-index="${index}">
                        <div class="item-header">
                            <div class="item-counter">${index}</div>
                            <h5 class="mb-0" style="font-size: 0.9rem;">Item ${index}</h5>
                            <div class="ms-auto">
                                <span class="badge bg-info" id="itemStatus_${index}">Belum Lengkap</span>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label required-field">Jenis Pakaian</label>
                                <select class="form-control jenis-pakaian-select" 
                                        name="detail_pakaian[${index}][jenis_pakaian]" 
                                        data-item="${index}"
                                        required>
                                    <option value="">Pilih Jenis</option>
                                    <option value="Kemeja">Kemeja</option>
                                    <option value="Celana">Celana</option>
                                    <option value="Jas">Jas</option>
                                    <option value="Blazer">Blazer</option>
                                    <option value="Rok">Rok</option>
                                    <option value="Baju Muslim">Baju Muslim</option>
                                    <option value="Setelan">Setelan (Atasan + Bawahan)</option>
                                    <option value="Lainnya">Lainnya</option>
                                </select>
                                <div class="error-message" id="jenis_pakaian_error_${index}">
                                    Jenis pakaian harus dipilih
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="bahan_${index}" class="form-label required-field">Bahan</label>
                                <input type="text" class="form-control" id="bahan_${index}" 
                                       name="detail_pakaian[${index}][bahan]" 
                                       required placeholder="Contoh: Katun, Linen, Wol, dll.">
                                <div class="error-message" id="bahan_error_${index}">
                                    Bahan harus diisi
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="harga_${index}" class="form-label required-field">Harga (Rp)</label>
                                <input type="number" class="form-control" id="harga_${index}" 
                                       name="detail_pakaian[${index}][harga]" 
                                       min="1000" step="1000" 
                                       placeholder="1000" required>
                                <div class="error-message" id="harga_error_${index}">
                                    Harga harus diisi (minimal Rp 1.000)
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Jenis Ukuran</label>
                                <div class="checkbox-group">
                                    <div class="form-check">
                                        <input class="form-check-input ukuran-atasan-check" type="checkbox" 
                                               id="ukuran_atasan_${index}" 
                                               name="detail_pakaian[${index}][ukuran_atasan]" value="ya"
                                               data-item="${index}">
                                        <label class="form-check-label" for="ukuran_atasan_${index}">
                                            Ukuran Atasan
                                        </label>
                                    </div>
                                    
                                    <div class="form-check">
                                        <input class="form-check-input ukuran-bawahan-check" type="checkbox" 
                                               id="ukuran_bawahan_${index}" 
                                               name="detail_pakaian[${index}][ukuran_bawahan]" value="ya"
                                               data-item="${index}">
                                        <label class="form-check-label" for="ukuran_bawahan_${index}">
                                            Ukuran Bawahan
                                        </label>
                                    </div>
                                </div>
                                <div class="error-message" id="ukuran_error_${index}">
                                    Pilih minimal satu jenis ukuran
                                </div>
                            </div>
                        </div>
                        
                        <!-- Ukuran Atasan -->
                        <div class="ukuran-atasan-section ukuran-section" id="atasan_section_${index}" style="display: none;">
                            <h6 style="font-size: 0.8rem;"><i class="fas fa-tshirt"></i> Ukuran Atasan (cm)</h6>
                            
                            <div class="ukuran-progress">
                                <span>Progress: </span>
                                <div class="ukuran-progress-bar">
                                    <div class="ukuran-progress-fill" id="atasan_progress_${index}"></div>
                                </div>
                                <span id="atasan_progress_text_${index}">0/8</span>
                            </div>
                            
                            <div class="ukuran-grid">
                                <div class="ukuran-item" data-field-index="1">
                                    <label class="ukuran-label required-field">Krah</label>
                                    <input type="number" class="ukuran-input atasan-input" 
                                           step="0.1" min="0.1"
                                           name="detail_pakaian[${index}][krah]" 
                                           placeholder="0.0"
                                           data-item="${index}"
                                           data-field="krah"
                                           data-next-field="2">
                                    <div class="error-message" id="atasan_krah_error_${index}">
                                        Ukuran krah harus diisi
                                    </div>
                                </div>
                                <div class="ukuran-item" data-field-index="2">
                                    <label class="ukuran-label required-field">Pundak</label>
                                    <input type="number" class="ukuran-input atasan-input" 
                                           step="0.1" min="0.1"
                                           name="detail_pakaian[${index}][pundak]" 
                                           placeholder="0.0"
                                           data-item="${index}"
                                           data-field="pundak"
                                           data-next-field="3">
                                    <div class="error-message" id="atasan_pundak_error_${index}">
                                        Ukuran pundak harus diisi
                                    </div>
                                </div>
                                <div class="ukuran-item" data-field-index="3">
                                    <label class="ukuran-label required-field">Tangan</label>
                                    <input type="number" class="ukuran-input atasan-input" 
                                           step="0.1" min="0.1"
                                           name="detail_pakaian[${index}][tangan]" 
                                           placeholder="0.0"
                                           data-item="${index}"
                                           data-field="tangan"
                                           data-next-field="4">
                                    <div class="error-message" id="atasan_tangan_error_${index}">
                                        Ukuran tangan harus diisi
                                    </div>
                                </div>
                                <div class="ukuran-item" data-field-index="4">
                                    <label class="ukuran-label required-field">Lingkar Dada/Pinggang</label>
                                    <input type="number" class="ukuran-input atasan-input" 
                                           step="0.1" min="0.1"
                                           name="detail_pakaian[${index}][ld_lp]" 
                                           placeholder="0.0"
                                           data-item="${index}"
                                           data-field="ld_lp"
                                           data-next-field="5">
                                    <div class="error-message" id="atasan_ld_lp_error_${index}">
                                        Ukuran lingkar dada/pinggang harus diisi
                                    </div>
                                </div>
                                <div class="ukuran-item" data-field-index="5">
                                    <label class="ukuran-label required-field">Badan</label>
                                    <input type="number" class="ukuran-input atasan-input" 
                                           step="0.1" min="0.1"
                                           name="detail_pakaian[${index}][badan]" 
                                           placeholder="0.0"
                                           data-item="${index}"
                                           data-field="badan"
                                           data-next-field="6">
                                    <div class="error-message" id="atasan_badan_error_${index}">
                                        Ukuran badan harus diisi
                                    </div>
                                </div>
                                <div class="ukuran-item" data-field-index="6">
                                    <label class="ukuran-label required-field">Lingkar Pinggang</label>
                                    <input type="number" class="ukuran-input atasan-input" 
                                           step="0.1" min="0.1"
                                           name="detail_pakaian[${index}][pinggang]" 
                                           placeholder="0.0"
                                           data-item="${index}"
                                           data-field="pinggang"
                                           data-next-field="7">
                                    <div class="error-message" id="atasan_pinggang_error_${index}">
                                        Ukuran lingkar pinggang harus diisi
                                    </div>
                                </div>
                                <div class="ukuran-item" data-field-index="7">
                                    <label class="ukuran-label required-field">Lingkar Pinggul</label>
                                    <input type="number" class="ukuran-input atasan-input" 
                                           step="0.1" min="0.1"
                                           name="detail_pakaian[${index}][pinggul]" 
                                           placeholder="0.0"
                                           data-item="${index}"
                                           data-field="pinggul"
                                           data-next-field="8">
                                    <div class="error-message" id="atasan_pinggul_error_${index}">
                                        Ukuran lingkar pinggul harus diisi
                                    </div>
                                </div>
                                <div class="ukuran-item" data-field-index="8">
                                    <label class="ukuran-label required-field">Panjang</label>
                                    <input type="number" class="ukuran-input atasan-input" 
                                           step="0.1" min="0.1"
                                           name="detail_pakaian[${index}][panjang_atasan]" 
                                           placeholder="0.0"
                                           data-item="${index}"
                                           data-field="panjang_atasan"
                                           data-next-field="">
                                    <div class="error-message" id="atasan_panjang_atasan_error_${index}">
                                        Ukuran panjang harus diisi
                                    </div>
                                </div>
                            </div>
                            <div class="mt-2">
                                <label class="form-label">Keterangan Atasan</label>
                                <textarea class="form-control" name="detail_pakaian[${index}][keterangan_atasan]" 
                                          rows="2" placeholder="Keterangan khusus untuk ukuran atasan..."></textarea>
                            </div>
                        </div>
                        
                        <!-- Ukuran Bawahan -->
                        <div class="ukuran-bawahan-section ukuran-section" id="bawahan_section_${index}" style="display: none;">
                            <h6 style="font-size: 0.8rem;"><i class="fas fa-vest"></i> Ukuran Bawahan (cm)</h6>
                            
                            <div class="ukuran-progress">
                                <span>Progress: </span>
                                <div class="ukuran-progress-bar">
                                    <div class="ukuran-progress-fill" id="bawahan_progress_${index}"></div>
                                </div>
                                <span id="bawahan_progress_text_${index}">0/7</span>
                            </div>
                            
                            <div class="ukuran-grid">
                                <div class="ukuran-item" data-field-index="1">
                                    <label class="ukuran-label required-field">Pinggang</label>
                                    <input type="number" class="ukuran-input bawahan-input" 
                                           step="0.1" min="0.1"
                                           name="detail_pakaian[${index}][pinggang_bawahan]" 
                                           placeholder="0.0"
                                           data-item="${index}"
                                           data-field="pinggang_bawahan"
                                           data-next-field="2">
                                    <div class="error-message" id="bawahan_pinggang_bawahan_error_${index}">
                                        Ukuran pinggang harus diisi
                                    </div>
                                </div>
                                <div class="ukuran-item" data-field-index="2">
                                    <label class="ukuran-label required-field">Pinggul</label>
                                    <input type="number" class="ukuran-input bawahan-input" 
                                           step="0.1" min="0.1"
                                           name="detail_pakaian[${index}][pinggul_bawahan]" 
                                           placeholder="0.0"
                                           data-item="${index}"
                                           data-field="pinggul_bawahan"
                                           data-next-field="3">
                                    <div class="error-message" id="bawahan_pinggul_bawahan_error_${index}">
                                        Ukuran pinggul harus diisi
                                    </div>
                                </div>
                                <div class="ukuran-item" data-field-index="3">
                                    <label class="ukuran-label required-field">Kres</label>
                                    <input type="number" class="ukuran-input bawahan-input" 
                                           step="0.1" min="0.1"
                                           name="detail_pakaian[${index}][kres]" 
                                           placeholder="0.0"
                                           data-item="${index}"
                                           data-field="kres"
                                           data-next-field="4">
                                    <div class="error-message" id="bawahan_kres_error_${index}">
                                        Ukuran kres harus diisi
                                    </div>
                                </div>
                                <div class="ukuran-item" data-field-index="4">
                                    <label class="ukuran-label required-field">Paha</label>
                                    <input type="number" class="ukuran-input bawahan-input" 
                                           step="0.1" min="0.1"
                                           name="detail_pakaian[${index}][paha]" 
                                           placeholder="0.0"
                                           data-item="${index}"
                                           data-field="paha"
                                           data-next-field="5">
                                    <div class="error-message" id="bawahan_paha_error_${index}">
                                        Ukuran paha harus diisi
                                    </div>
                                </div>
                                <div class="ukuran-item" data-field-index="5">
                                    <label class="ukuran-label required-field">Lutut</label>
                                    <input type="number" class="ukuran-input bawahan-input" 
                                           step="0.1" min="0.1"
                                           name="detail_pakaian[${index}][lutut]" 
                                           placeholder="0.0"
                                           data-item="${index}"
                                           data-field="lutut"
                                           data-next-field="6">
                                    <div class="error-message" id="bawahan_lutut_error_${index}">
                                        Ukuran lutut harus diisi
                                    </div>
                                </div>
                                <div class="ukuran-item" data-field-index="6">
                                    <label class="ukuran-label required-field">L. Bawah</label>
                                    <input type="number" class="ukuran-input bawahan-input" 
                                           step="0.1" min="0.1"
                                           name="detail_pakaian[${index}][l_bawah]" 
                                           placeholder="0.0"
                                           data-item="${index}"
                                           data-field="l_bawah"
                                           data-next-field="7">
                                    <div class="error-message" id="bawahan_l_bawah_error_${index}">
                                        Ukuran l. bawah harus diisi
                                    </div>
                                </div>
                                <div class="ukuran-item" data-field-index="7">
                                    <label class="ukuran-label required-field">Panjang</label>
                                    <input type="number" class="ukuran-input bawahan-input" 
                                           step="0.1" min="0.1"
                                           name="detail_pakaian[${index}][panjang_bawahan]" 
                                           placeholder="0.0"
                                           data-item="${index}"
                                           data-field="panjang_bawahan"
                                           data-next-field="">
                                    <div class="error-message" id="bawahan_panjang_bawahan_error_${index}">
                                        Ukuran panjang harus diisi
                                    </div>
                                </div>
                            </div>
                            <div class="mt-2">
                                <label class="form-label">Keterangan Bawahan</label>
                                <textarea class="form-control" name="detail_pakaian[${index}][keterangan_bawahan]" 
                                          rows="2" placeholder="Keterangan khusus untuk ukuran bawahan..."></textarea>
                            </div>
                        </div>
                    </div>
                `;
                
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = template;
                return tempDiv.firstElementChild;
            }
            
            function updateKuantitas() {
                // Update counter pada setiap item
                const items = itemsContainer.querySelectorAll('.item-card');
                items.forEach((item, index) => {
                    const itemIndex = index + 1;
                    item.setAttribute('data-item-index', itemIndex);
                    item.querySelector('.item-counter').textContent = itemIndex;
                    item.querySelector('h5').textContent = 'Item ' + itemIndex;
                    
                    // Update semua atribut data-item dan name attributes
                    const inputs = item.querySelectorAll('[data-item], [name*="detail_pakaian"]');
                    inputs.forEach(input => {
                        if (input.hasAttribute('data-item')) {
                            input.setAttribute('data-item', itemIndex);
                        }
                        
                        const name = input.getAttribute('name');
                        if (name && name.includes('detail_pakaian')) {
                            const newName = name.replace(/detail_pakaian\[\d+\]/, `detail_pakaian[${itemIndex}]`);
                            input.setAttribute('name', newName);
                        }
                        
                        const id = input.getAttribute('id');
                        if (id) {
                            const newId = id.replace(/\d+/, itemIndex);
                            input.setAttribute('id', newId);
                        }
                    });
                    
                    // Update section IDs
                    const atasanSection = item.querySelector('.ukuran-atasan-section');
                    const bawahanSection = item.querySelector('.ukuran-bawahan-section');
                    if (atasanSection) atasanSection.id = 'atasan_section_' + itemIndex;
                    if (bawahanSection) bawahanSection.id = 'bawahan_section_' + itemIndex;
                    
                    // Update progress IDs
                    const atasanProgress = item.querySelector('#atasan_progress_\\d+');
                    const bawahanProgress = item.querySelector('#bawahan_progress_\\d+');
                    const atasanProgressText = item.querySelector('#atasan_progress_text_\\d+');
                    const bawahanProgressText = item.querySelector('#bawahan_progress_text_\\d+');
                    
                    if (atasanProgress) atasanProgress.id = 'atasan_progress_' + itemIndex;
                    if (bawahanProgress) bawahanProgress.id = 'bawahan_progress_' + itemIndex;
                    if (atasanProgressText) atasanProgressText.id = 'atasan_progress_text_' + itemIndex;
                    if (bawahanProgressText) bawahanProgressText.id = 'bawahan_progress_text_' + itemIndex;
                });
            }
            
            // ========== STEP 3 FUNCTIONALITY ==========
            
            // Real-time payment calculation
            const jumlahBayarInput = document.getElementById('jumlah_bayar');
            const sisaBayarInput = document.getElementById('sisa_bayar');
            
            if (jumlahBayarInput && sisaBayarInput) {
                function updatePaymentInfo() {
                    const totalHarga = <?= $total_harga; ?>;
                    const jumlahBayar = parseFloat(jumlahBayarInput.value) || 0;
                    const sisaBayar = totalHarga - jumlahBayar;
                    
                    // Format angka ke Rupiah
                    const formatRupiah = (number) => {
                        return new Intl.NumberFormat('id-ID', {
                            style: 'currency',
                            currency: 'IDR',
                            minimumFractionDigits: 0
                        }).format(number);
                    };
                    
                    // Update sisa bayar
                    sisaBayarInput.value = formatRupiah(sisaBayar);
                    
                    // Validasi jumlah bayar tidak melebihi total harga
                    if (jumlahBayar > totalHarga) {
                        jumlahBayarInput.setCustomValidity('Jumlah bayar tidak boleh melebihi total harga');
                        jumlahBayarInput.classList.add('is-invalid');
                    } else {
                        jumlahBayarInput.setCustomValidity('');
                        jumlahBayarInput.classList.remove('is-invalid');
                    }
                    
                    // Add visual feedback
                    const paymentElements = document.querySelectorAll('.payment-update');
                    paymentElements.forEach(element => {
                        element.classList.add('updated');
                        setTimeout(() => {
                            element.classList.remove('updated');
                        }, 1000);
                    });
                }
                
                // Real-time update on every input change
                jumlahBayarInput.addEventListener('input', updatePaymentInfo);
                jumlahBayarInput.addEventListener('change', updatePaymentInfo);
                jumlahBayarInput.addEventListener('keyup', updatePaymentInfo);
                
                // Initial update
                updatePaymentInfo();
            }
            
            // ========== FORM VALIDATION ==========
            
            // Form submission validation for step 2
            const step2Form = document.getElementById('step2Form');
            const submitStep2 = document.getElementById('submitStep2');
            
            if (step2Form && submitStep2) {
                step2Form.addEventListener('submit', function(e) {
                    if (!validateAllItems()) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        // Tampilkan alert
                        alert('❌ Harap lengkapi semua data ukuran sebelum melanjutkan!');
                        
                        // Temukan item pertama yang error
                        const firstErrorItem = document.querySelector('.item-card .badge.bg-warning');
                        if (firstErrorItem) {
                            firstErrorItem.closest('.item-card').scrollIntoView({ 
                                behavior: 'smooth', 
                                block: 'center' 
                            });
                        }
                    }
                });
            }
            
            // ========== INITIAL SETUP ==========
            
            // Initialize all functionality
            setupJenisPakaianDetection();
            setupUkuranToggles();
            setupAutoFocus();
            setupRealTimeValidation();
            
            // Initialize progress bars
            document.querySelectorAll('.ukuran-atasan-check:checked').forEach(check => {
                const itemId = check.getAttribute('data-item');
                updateUkuranProgress('atasan', itemId);
            });
            
            document.querySelectorAll('.ukuran-bawahan-check:checked').forEach(check => {
                const itemId = check.getAttribute('data-item');
                updateUkuranProgress('bawahan', itemId);
            });
            
            // Initial validation for all items
            if (document.querySelectorAll('.item-card').length > 0) {
                validateAllItems();
            }
        });
    </script>
</body>
</html>