<?php
// pesanan/editpesanan.php
require_once '../config/database.php';
check_login();
check_role(['admin', 'pemilik', 'pegawai']);

// Inisialisasi variabel
$errors = [];
$success = '';
$pesanan = null;

// Ambil ID pesanan dari URL
$id_pesanan = isset($_GET['edit']) ? clean_input($_GET['edit']) : '';

// Validasi ID pesanan
if (empty($id_pesanan)) {
    $_SESSION['error'] = "❌ ID pesanan tidak valid! Silakan pilih pesanan yang akan diedit.";
    header("Location: pesanan.php");
    exit();
}

// ============================================================
// FUNGSI SYNC OTOMATIS (SAMA DENGAN DETAIL_PESANAN.PHP)
// ============================================================
function checkAndSyncTransaction($id_pesanan) {
    global $pdo;
    
    try {
        // Cek apakah pesanan sudah ada pembayaran tapi belum ada transaksi
        $sql_check = "SELECT p.jumlah_bayar, p.total_harga, 
                             (SELECT COUNT(*) FROM data_transaksi WHERE id_pesanan = p.id_pesanan) as transaksi_count
                      FROM data_pesanan p 
                      WHERE p.id_pesanan = ?";
        
        $data_pesanan = getSingle($sql_check, [$id_pesanan]);
        
        if ($data_pesanan && $data_pesanan['jumlah_bayar'] > 0 && $data_pesanan['transaksi_count'] == 0) {
            // Tentukan status pembayaran
            $status_pembayaran = getPaymentStatus($data_pesanan['jumlah_bayar'], $data_pesanan['total_harga']);
            
            // Insert transaksi otomatis
            $sql_transaksi = "INSERT INTO data_transaksi 
                             (id_pesanan, tgl_transaksi, jumlah_bayar, metode_bayar, keterangan, status_pembayaran, created_at) 
                             VALUES (?, NOW(), ?, 'Auto System', 'Pembayaran otomatis dari sistem', ?, NOW())";
            
            executeQuery($sql_transaksi, [
                $id_pesanan, 
                $data_pesanan['jumlah_bayar'],
                $status_pembayaran
            ]);
            
            log_activity("Auto-sync transaksi untuk pesanan ID: " . $id_pesanan);
            return true;
        }
        
        return false;
        
    } catch (PDOException $e) {
        error_log("Gagal sync transaksi untuk pesanan {$id_pesanan}: " . $e->getMessage());
        return false;
    }
}

// Fungsi untuk menentukan status pembayaran
function getPaymentStatus($jumlah_bayar, $total_harga) {
    if ($jumlah_bayar == 0) {
        return 'belum_bayar';
    } elseif ($jumlah_bayar >= $total_harga) {
        return 'lunas';
    } elseif ($jumlah_bayar > 0 && $jumlah_bayar < $total_harga) {
        $persentase = ($jumlah_bayar / $total_harga) * 100;
        if ($persentase < 50) {
            return 'dp';
        } else {
            return 'cicilan';
        }
    }
    return 'belum_bayar';
}

// Jalankan sync otomatis
$synced = checkAndSyncTransaction($id_pesanan);
if ($synced) {
    $_SESSION['sync_info'] = "✅ Transaksi berhasil disinkronkan secara otomatis";
}

// Ambil data pesanan utama
try {
    $sql_pesanan = "SELECT p.*, 
                   pel.nama AS nama_pelanggan, 
                   pel.alamat AS alamat_pelanggan,
                   pel.no_hp AS telepon_pelanggan,
                   u.nama_lengkap AS nama_karyawan
            FROM data_pesanan p
            LEFT JOIN data_pelanggan pel ON p.id_pelanggan = pel.id_pelanggan
            LEFT JOIN users u ON p.id_karyawan = u.id_user
            WHERE p.id_pesanan = ?";
    
    $pesanan = getSingle($sql_pesanan, [$id_pesanan]);
    
    if (!$pesanan) {
        $_SESSION['error'] = "❌ Pesanan tidak ditemukan";
        header("Location: pesanan.php");
        exit();
    }
    
} catch (PDOException $e) {
    $_SESSION['error'] = "❌ Gagal memuat data pesanan: " . $e->getMessage();
    header("Location: pesanan.php");
    exit();
}

// AMBIL SEMUA ITEMS PESANAN DARI TABEL pesanan_items (UNTUK MULTIPLE ITEMS)
try {
    $sql_items = "SELECT * FROM pesanan_items WHERE id_pesanan = ? ORDER BY id_item ASC";
    $pesanan_items = getAll($sql_items, [$id_pesanan]);
} catch (PDOException $e) {
    // Fallback: Jika tabel pesanan_items belum ada, gunakan data dari kolom lama
    $pesanan_items = [];
    
    // Buat satu item dari data lama untuk kompatibilitas
    if (!empty($pesanan['jenis_pakaian'])) {
        $pesanan_items[] = [
            'id_item' => 1,
            'jenis_pakaian' => $pesanan['jenis_pakaian'],
            'bahan' => $pesanan['bahan'] ?? '',
            'jumlah' => 1,
            'harga_satuan' => $pesanan['total_harga'] ?? 0,
            'catatan' => $pesanan['catatan'] ?? ''
        ];
    }
    error_log("Error mengambil items pesanan: " . $e->getMessage());
}

// Ambil data ukuran atasan dan bawahan
try {
    $ukuran_atasan = getAll("SELECT * FROM ukuran_atasan WHERE id_pesanan = ? ORDER BY id_ukuran_atasan ASC", [$id_pesanan]);
    $ukuran_bawahan = getAll("SELECT * FROM ukuran_bawahan WHERE id_pesanan = ? ORDER BY id_ukuran_bawahan ASC", [$id_pesanan]);
} catch (PDOException $e) {
    $ukuran_atasan = [];
    $ukuran_bawahan = [];
}

// Hitung total ukuran untuk ringkasan
$total_atasan = count($ukuran_atasan);
$total_bawahan = count($ukuran_bawahan);
$total_ukuran = $total_atasan + $total_bawahan;

// Ambil data pelanggan dan karyawan untuk dropdown
try {
    $pelanggan = getAll("SELECT id_pelanggan, nama, no_hp FROM data_pelanggan ORDER BY nama ASC");
    $karyawan = getAll("SELECT id_user, nama_lengkap FROM users WHERE role IN ('admin', 'pegawai') ORDER BY nama_lengkap ASC");
} catch (PDOException $e) {
    $_SESSION['error'] = "Gagal memuat data: " . $e->getMessage();
    $pelanggan = [];
    $karyawan = [];
}

// Proses form edit pesanan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil dan sanitasi data utama
    $id_pelanggan = clean_input($_POST['id_pelanggan'] ?? '');
    $id_karyawan = clean_input($_POST['id_karyawan'] ?? '');
    $tgl_pesanan = clean_input($_POST['tgl_pesanan'] ?? '');
    $tgl_selesai = clean_input($_POST['tgl_selesai'] ?? '');
    $total_harga = clean_input($_POST['total_harga'] ?? '0');
    $jumlah_bayar = clean_input($_POST['jumlah_bayar'] ?? '0');
    $status_pesanan = clean_input($_POST['status_pesanan'] ?? 'belum');
    
    // Hitung sisa bayar otomatis
    $sisa_bayar = $total_harga - $jumlah_bayar;

    // ============================================================
    // DATA ITEMS PESANAN (MULTIPLE ITEMS)
    // ============================================================
    $pesanan_items_data = [];
    if (isset($_POST['items']) && is_array($_POST['items'])) {
        foreach ($_POST['items'] as $index => $item) {
            if (!empty(trim($item['jenis_pakaian'] ?? ''))) {
                $pesanan_items_data[] = [
                    'jenis_pakaian' => clean_input($item['jenis_pakaian']),
                    'bahan' => clean_input($item['bahan'] ?? ''),
                    'jumlah' => clean_input($item['jumlah'] ?? '1'),
                    'harga_satuan' => clean_input($item['harga_satuan'] ?? '0'),
                    'catatan_item' => clean_input($item['catatan_item'] ?? '')
                ];
            }
        }
    }
    
    // Validasi minimal satu item
    if (empty($pesanan_items_data)) {
        $errors[] = "Minimal harus ada satu item pakaian";
    }

    // Data ukuran atasan
    $ukuran_atasan_data = [];
    if (isset($_POST['ukuran_atasan']) && is_array($_POST['ukuran_atasan'])) {
        foreach ($_POST['ukuran_atasan'] as $index => $atasan) {
            $ukuran_atasan_data[] = [
                'krah' => clean_input($atasan['krah'] ?? '0'),
                'pundak' => clean_input($atasan['pundak'] ?? '0'),
                'tangan' => clean_input($atasan['tangan'] ?? '0'),
                'ld_lp' => clean_input($atasan['ld_lp'] ?? '0'),
                'badan' => clean_input($atasan['badan'] ?? '0'),
                'pinggang' => clean_input($atasan['pinggang'] ?? '0'),
                'pinggul' => clean_input($atasan['pinggul'] ?? '0'),
                'panjang' => clean_input($atasan['panjang'] ?? '0'),
                'keterangan' => clean_input($atasan['keterangan'] ?? '')
            ];
        }
    }

    // Data ukuran bawahan
    $ukuran_bawahan_data = [];
    if (isset($_POST['ukuran_bawahan']) && is_array($_POST['ukuran_bawahan'])) {
        foreach ($_POST['ukuran_bawahan'] as $index => $bawahan) {
            $ukuran_bawahan_data[] = [
                'pinggang' => clean_input($bawahan['pinggang'] ?? '0'),
                'pinggul' => clean_input($bawahan['pinggul'] ?? '0'),
                'kres' => clean_input($bawahan['kres'] ?? '0'),
                'paha' => clean_input($bawahan['paha'] ?? '0'),
                'lutut' => clean_input($bawahan['lutut'] ?? '0'),
                'l_bawah' => clean_input($bawahan['l_bawah'] ?? '0'),
                'panjang' => clean_input($bawahan['panjang'] ?? '0'),
                'keterangan' => clean_input($bawahan['keterangan'] ?? '')
            ];
        }
    }

    // Validasi lainnya
    if (empty($id_pelanggan)) {
        $errors[] = "Pelanggan harus dipilih";
    }
    if (empty($id_karyawan)) {
        $errors[] = "Karyawan harus dipilih";
    }
    if (empty($tgl_pesanan)) {
        $errors[] = "Tanggal pesanan harus diisi";
    }
    if (empty($total_harga) || $total_harga <= 0) {
        $errors[] = "Total harga harus diisi dan lebih dari 0";
    }
    if ($jumlah_bayar < 0) {
        $errors[] = "Jumlah bayar tidak boleh negatif";
    }
    if ($sisa_bayar < 0) {
        $errors[] = "Jumlah bayar tidak boleh melebihi total harga";
    }

    // Jika tidak ada error, update ke database
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Update data pesanan utama
            $check_columns = $pdo->query("SHOW COLUMNS FROM data_pesanan LIKE 'updated_at'")->fetch();
            $has_updated_at = $check_columns !== false;
            
            if ($has_updated_at) {
                $sql = "UPDATE data_pesanan SET 
                        id_pelanggan = ?, id_karyawan = ?, 
                        tgl_pesanan = ?, tgl_selesai = ?, 
                        total_harga = ?, jumlah_bayar = ?, 
                        sisa_bayar = ?, status_pesanan = ?, updated_at = NOW()
                        WHERE id_pesanan = ?";
            } else {
                $sql = "UPDATE data_pesanan SET 
                        id_pelanggan = ?, id_karyawan = ?, 
                        tgl_pesanan = ?, tgl_selesai = ?, 
                        total_harga = ?, jumlah_bayar = ?, 
                        sisa_bayar = ?, status_pesanan = ?
                        WHERE id_pesanan = ?";
            }
            
            $params = [
                $id_pelanggan, $id_karyawan, $tgl_pesanan, $tgl_selesai,
                $total_harga, $jumlah_bayar,
                $sisa_bayar, $status_pesanan, $id_pesanan
            ];
            
            executeQuery($sql, $params);

            // ============================================================
            // UPDATE ITEMS PESANAN (MULTIPLE ITEMS)
            // ============================================================
            executeQuery("DELETE FROM pesanan_items WHERE id_pesanan = ?", [$id_pesanan]);
            
            if (!empty($pesanan_items_data)) {
                foreach ($pesanan_items_data as $item) {
                    $sql_item = "INSERT INTO pesanan_items 
                                (id_pesanan, jenis_pakaian, bahan, jumlah, harga_satuan, catatan) 
                                VALUES (?, ?, ?, ?, ?, ?)";
                    
                    executeQuery($sql_item, [
                        $id_pesanan,
                        $item['jenis_pakaian'],
                        $item['bahan'],
                        $item['jumlah'],
                        $item['harga_satuan'],
                        $item['catatan_item']
                    ]);
                }
            }

            // Update ukuran atasan jika ada data
            executeQuery("DELETE FROM ukuran_atasan WHERE id_pesanan = ?", [$id_pesanan]);
            
            if (!empty($ukuran_atasan_data)) {
                foreach ($ukuran_atasan_data as $atasan) {
                    // Hanya insert jika minimal ada satu ukuran yang diisi
                    if (!empty($atasan['krah']) || !empty($atasan['pundak']) || !empty($atasan['tangan']) ||
                        !empty($atasan['ld_lp']) || !empty($atasan['badan']) || !empty($atasan['pinggang']) ||
                        !empty($atasan['pinggul']) || !empty($atasan['panjang'])) {
                        
                        $sql_atasan = "INSERT INTO ukuran_atasan 
                                      (id_pesanan, id_pelanggan, krah, pundak, tangan, ld_lp, badan, pinggang, pinggul, panjang, keterangan) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        
                        executeQuery($sql_atasan, [
                            $id_pesanan, $id_pelanggan,
                            $atasan['krah'] ?: 0, $atasan['pundak'] ?: 0, $atasan['tangan'] ?: 0,
                            $atasan['ld_lp'] ?: 0, $atasan['badan'] ?: 0, $atasan['pinggang'] ?: 0,
                            $atasan['pinggul'] ?: 0, $atasan['panjang'] ?: 0, $atasan['keterangan']
                        ]);
                    }
                }
            }

            // Update ukuran bawahan jika ada data
            executeQuery("DELETE FROM ukuran_bawahan WHERE id_pesanan = ?", [$id_pesanan]);
            
            if (!empty($ukuran_bawahan_data)) {
                foreach ($ukuran_bawahan_data as $bawahan) {
                    // Hanya insert jika minimal ada satu ukuran yang diisi
                    if (!empty($bawahan['pinggang']) || !empty($bawahan['pinggul']) || !empty($bawahan['kres']) ||
                        !empty($bawahan['paha']) || !empty($bawahan['lutut']) || !empty($bawahan['l_bawah']) ||
                        !empty($bawahan['panjang'])) {
                        
                        $sql_bawahan = "INSERT INTO ukuran_bawahan 
                                       (id_pesanan, id_pelanggan, pinggang, pinggul, kres, paha, lutut, l_bawah, panjang, keterangan) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        
                        executeQuery($sql_bawahan, [
                            $id_pesanan, $id_pelanggan,
                            $bawahan['pinggang'] ?: 0, $bawahan['pinggul'] ?: 0, $bawahan['kres'] ?: 0,
                            $bawahan['paha'] ?: 0, $bawahan['lutut'] ?: 0, $bawahan['l_bawah'] ?: 0,
                            $bawahan['panjang'] ?: 0, $bawahan['keterangan']
                        ]);
                    }
                }
            }
            
            $pdo->commit();
            
            $_SESSION['success'] = "✅ Data pesanan <strong>$id_pesanan</strong> berhasil diperbarui!";
            log_activity("Mengedit pesanan ID: $id_pesanan");
            
            // Jalankan sync lagi setelah update
            checkAndSyncTransaction($id_pesanan);
            
            header("Location: detail_pesanan.php?id=$id_pesanan");
            exit();
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "❌ Gagal mengupdate pesanan: " . $e->getMessage();
            error_log("Error update pesanan: " . $e->getMessage());
        }
    }
}

// Format status text untuk tampilan
$status_text = '';
$status_class = '';
switch($pesanan['status_pesanan']) {
    case 'belum':
        $status_text = 'Belum Diproses';
        $status_class = 'belum';
        break;
    case 'dalam_proses':
        $status_text = 'Dalam Proses';
        $status_class = 'dalam_proses';
        break;
    case 'selesai':
        $status_text = 'Selesai';
        $status_class = 'selesai';
        break;
    default:
        $status_text = $pesanan['status_pesanan'];
        $status_class = 'belum';
}

// Hitung total harga dari items untuk display
$total_harga_items = 0;
foreach ($pesanan_items as $item) {
    $total_harga_items += ($item['harga_satuan'] ?? 0) * ($item['jumlah'] ?? 1);
}

$page_title = "Edit Pesanan - " . $id_pesanan;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Pesanan <?= $id_pesanan; ?> - SIM Parapatan Tailor</title>
     <link rel="shortcut icon" href="../assets/images/logoterakhir.png" type="image/x-icon" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../includes/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body {
            background-color: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 0.85rem;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 15px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        h2 {
            font-family: 'Arial', sans-serif;
            color: #2e59d9;
            text-align: left;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #3498db;
            font-weight: bold;
            font-size: 1.3rem;
        }
        
        .card {
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
            background-color: #f8f9fa;
            margin-bottom: 20px;
            border: none;
        }
        
        .form-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
            font-size: 0.8rem;
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            padding: 8px 10px;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            background: white;
        }
        
        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: #4f46e5;
            box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1);
        }
        
        .btn {
            padding: 0.4rem 0.8rem;
            font-weight: 500;
            border-radius: 6px;
            transition: all 0.2s ease;
            font-size: 0.75rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background-color: #007BFF;
            border-color: #007BFF;
            color: white;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
            color: white;
        }
        
        .btn-success {
            background-color: #28a745;
            border-color: #28a745;
            color: white;
        }
        
        .btn-warning {
            background-color: #ffc107;
            border-color: #ffc107;
            color: #212529;
        }
        
        .btn-info {
            background-color: #17a2b8;
            border-color: #17a2b8;
            color: white;
        }
        
        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
            color: white;
        }
        
        .btn-sm {
            padding: 0.2rem 0.4rem;
            font-size: 0.7rem;
        }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
            box-shadow: 0 1px 6px rgba(0,0,0,0.2);
        }
        
        .alert {
            margin-top: 10px;
            border-radius: 8px;
            border: none;
            font-weight: 500;
            font-size: 0.8rem;
            padding: 0.6rem 0.8rem;
        }
        
        .alert-success { 
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #065f46; 
            border-left: 3px solid #10b981;
        }
        
        .alert-danger { 
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #991b1b; 
            border-left: 3px solid #ef4444;
        }
        
        .alert-info { 
            background: linear-gradient(135deg, #dbeafe 0%, #93c5fd 100%);
            color: #1e40af; 
            border-left: 3px solid #3b82f6;
        }
        
        /* Items Section - NEW STYLES */
        .items-section {
            margin-bottom: 1.5rem;
        }
        
        .item-card {
            background: white;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .item-card:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transform: translateY(-1px);
        }
        
        .item-badge {
            display: inline-block;
            width: 24px;
            height: 24px;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            border-radius: 50%;
            text-align: center;
            line-height: 24px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-right: 8px;
        }
        
        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .item-title {
            font-weight: 600;
            color: #374151;
            font-size: 0.85rem;
        }
        
        /* Info Section */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .info-card {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            border-left: 4px solid #3b82f6;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .info-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.8rem;
            font-weight: 600;
            color: #374151;
            font-size: 0.8rem;
        }
        
        .info-content {
            font-size: 0.75rem;
            line-height: 1.5;
            color: #6b7280;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.3rem 0;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 500;
            color: #374151;
        }
        
        .info-value {
            font-weight: 600;
            color: #1f2937;
            text-align: right;
        }
        
        .highlight {
            color: #3b82f6;
            font-weight: 600;
        }
        
        /* Payment Section */
        .payment-card {
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
            border-radius: 8px;
            padding: 1.2rem;
            border: 1px solid #bae6fd;
        }
        
        .payment-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            color: #0c4a6e;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .payment-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            font-size: 0.75rem;
        }
        
        .payment-label {
            font-weight: 500;
            color: #0c4a6e;
        }
        
        .payment-value {
            font-weight: 700;
        }
        
        .payment-value.total {
            color: #059669;
        }
        
        .payment-value.paid {
            color: #2563eb;
        }
        
        .payment-value.remaining {
            color: #dc2626;
        }
        
        /* Status Badge */
        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            display: inline-block;
        }
        
        .status-badge.belum {
            background: #fef3c7;
            color: #d97706;
            border: 1px solid #fcd34d;
        }
        
        .status-badge.dalam_proses {
            background: #dbeafe;
            color: #1d4ed8;
            border: 1px solid #93c5fd;
        }
        
        .status-badge.selesai {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }
        
        /* Ukuran Tables */
        .ukuran-section {
            margin-bottom: 1rem;
        }
        
        .ukuran-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.8rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .ukuran-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            color: #374151;
            font-size: 0.8rem;
        }
        
        .kuantitas-badge {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        
        /* TABEL UKURAN */
        .ukuran-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.65rem;
            margin-bottom: 1rem;
            background: white;
            border-radius: 6px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .ukuran-table th {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            font-weight: 600;
            padding: 0.4rem 0.6rem;
            text-align: left;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .ukuran-table td {
            padding: 0.4rem 0.6rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
            color: #374151;
        }
        
        .ukuran-badge {
            background: linear-gradient(135deg, #0ea5e9, #38bdf8);
            color: white;
            padding: 0.3rem 0.6rem;
            border-radius: 12px;
            font-size: 0.6rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: inline-block;
        }
        
        /* Input ukuran dalam tabel */
        .ukuran-input {
            width: 100%;
            padding: 0.2rem 0.4rem;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 0.65rem;
            background: white;
        }
        
        .keterangan-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 6px;
            padding: 0.6rem;
            margin-top: 0.5rem;
            font-size: 0.65rem;
            color: #856404;
        }
        
        .keterangan-textarea {
            width: 100%;
            padding: 0.4rem;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            font-size: 0.65rem;
            background: #fffdf6;
            resize: vertical;
            min-height: 60px;
        }
        
        .empty-state {
            text-align: center;
            color: #6b7280;
            font-style: italic;
            padding: 2rem;
            background: #f8f9fa;
            border-radius: 8px;
            margin: 1rem 0;
            font-size: 0.75rem;
        }
        
        .empty-state i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: #d1d5db;
        }
        
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border-left: 3px solid #4f46e5;
        }
        
        .header-actions h3 {
            color: #1f2937;
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
        }
        
        .id-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 500;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        /* Progress Bar untuk Pembayaran */
        .payment-progress {
            height: 8px;
            background: #e5e7eb;
            border-radius: 4px;
            margin: 0.8rem 0;
            overflow: hidden;
        }
        
        .payment-progress-bar {
            height: 100%;
            background: linear-gradient(135deg, #10b981, #34d399);
            border-radius: 4px;
            transition: width 0.3s ease;
        }
        
        .payment-percentage {
            text-align: center;
            font-size: 0.7rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        
        .required {
            color: #dc2626;
        }
        
        /* Form actions */
        .form-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.8rem;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #e5e7eb;
        }
        
        /* Kuantitas indicator */
        .kuantitas-indicator {
            display: inline-block;
            width: 18px;
            height: 18px;
            background: #4f46e5;
            color: white;
            border-radius: 50%;
            font-size: 0.6rem;
            font-weight: 600;
            text-align: center;
            line-height: 18px;
            margin-right: 5px;
        }
        
        /* Form untuk edit */
        .edit-form-container {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
        }
        
        .edit-form-section {
            margin-bottom: 1.5rem;
        }
        
        .edit-form-section h5 {
            color: #374151;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        /* Item summary */
        .item-total-display {
            background: #f0f9ff;
            font-weight: 600;
            color: #0c4a6e;
            padding: 0.3rem 0.5rem;
            border-radius: 4px;
            border: 1px solid #bae6fd;
        }
        
        .items-summary {
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
            border: 1px solid #bae6fd;
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            font-size: 0.75rem;
        }
        
        .summary-label {
            font-weight: 500;
            color: #0c4a6e;
        }
        
        .summary-value {
            font-weight: 700;
            color: #0c4a6e;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .header-actions {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
            
            .action-buttons {
                flex-direction: column;
                width: 100%;
            }
            
            .action-buttons .btn {
                justify-content: center;
                width: 100%;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .ukuran-table {
                font-size: 0.6rem;
            }
            
            .ukuran-table th,
            .ukuran-table td {
                padding: 0.3rem 0.4rem;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .form-actions .btn {
                width: 100%;
                justify-content: center;
            }
            
            .item-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
        }
        
        @media (max-width: 480px) {
            .container {
                padding: 10px;
            }
            
            .card {
                padding: 15px;
            }
            
            .ukuran-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .item-card {
                padding: 1rem;
            }
        }
        
        /* Main content spacing */
        .main-content {
            min-height: calc(100vh - 60px);
            display: flex;
            flex-direction: column;
        }
        
        .content-body {
            flex: 1;
        }
        
        /* Status Indicator */
        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.8rem;
        }
        
        .status-indicator.belum {
            background: #fef3c7;
            color: #d97706;
            border: 1px solid #fcd34d;
        }
        
        .status-indicator.dalam_proses {
            background: #dbeafe;
            color: #1d4ed8;
            border: 1px solid #93c5fd;
        }
        
        .status-indicator.selesai {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }
        
        /* Payment Status Badge */
        .payment-status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
            margin-top: 0.5rem;
        }
        
        /* Info box edit */
        .info-box-edit {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border-left: 4px solid #ffc107;
            box-shadow: 0 1px 3px rgba(255, 193, 7, 0.1);
        }
        
        .info-box-edit p {
            color: #856404;
            margin: 0;
            font-size: 0.8rem;
            line-height: 1.4;
        }
        
        /* Remove button di dalam tabel */
        .remove-row-btn {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
            padding: 0.1rem 0.3rem;
            border-radius: 4px;
            font-size: 0.6rem;
            cursor: pointer;
            margin-left: 0.3rem;
        }
        
        .remove-row-btn:hover {
            background: #fecaca;
        }
        
        /* Items container */
        #items-container .item-card:first-child .remove-item-btn {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        #items-container .item-card:first-child .remove-item-btn:hover {
            background-color: #dc3545;
        }
    </style>
</head>

<body>
    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/header.php'; ?>

    <div class="main-content">
        <div class="content-body">
            <div class="container">
                <h2 class="my-3">Edit Data Pesanan</h2>

                <!-- Alert Pesan -->
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['info'])): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> <?= $_SESSION['info']; unset($_SESSION['info']); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> 
                        <strong>Terjadi kesalahan:</strong>
                        <ul class="mb-0 mt-1">
                            <?php foreach ($errors as $error): ?>
                                <li><?= $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Header Actions -->
                <div class="header-actions">
                    <div>
                        <h3>
                            <i class="fas fa-receipt"></i> Edit Pesanan #<?= $pesanan['id_pesanan']; ?>
                        </h3>
                        <div class="mt-2">
                            <span class="status-indicator <?= $status_class; ?>">
                                <i class="fas fa-circle" style="font-size: 0.6rem;"></i>
                                <?= $status_text; ?>
                            </span>
                        </div>
                    </div>
                    <div class="action-buttons">
                        <a href="detail_pesanan.php?id=<?= $pesanan['id_pesanan']; ?>" class="btn btn-info">
                            <i class="fas fa-eye"></i> Lihat Detail
                        </a>
                        <a href="pesanan.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Kembali
                        </a>
                    </div>
                </div>

                <!-- Info Box Edit -->
                <div class="info-box-edit">
                    <p><i class="fas fa-info-circle"></i> Anda sedang mengedit pesanan untuk pelanggan <strong><?= htmlspecialchars($pesanan['nama_pelanggan']); ?></strong>. 
                    Pastikan semua data yang diubah sudah benar sebelum menyimpan.</p>
                </div>

                <!-- Form Edit -->
                <form method="POST" id="pesananForm" class="needs-validation" novalidate>
                    <div class="row">
                        <!-- Kolom Kiri - Informasi Utama -->
                        <div class="col-lg-8">
                            <!-- Informasi Dasar -->
                            <div class="edit-form-container">
                                <h5><i class="fas fa-info-circle"></i> Informasi Pesanan</h5>
                                
                                <div class="info-grid">
                                    <!-- Pelanggan -->
                                    <div class="info-card">
                                        <div class="info-header">
                                            <i class="fas fa-user"></i> Pelanggan
                                        </div>
                                        <div class="info-content">
                                            <div class="info-row">
                                                <span class="info-label">Nama:</span>
                                                <div class="info-value">
                                                    <select class="form-select form-select-sm" id="id_pelanggan" name="id_pelanggan" required>
                                                        <option value="">Pilih Pelanggan</option>
                                                        <?php foreach ($pelanggan as $p): ?>
                                                            <option value="<?= $p['id_pelanggan']; ?>" 
                                                                <?= (isset($_POST['id_pelanggan']) && $_POST['id_pelanggan'] == $p['id_pelanggan']) || 
                                                                    $pesanan['id_pelanggan'] == $p['id_pelanggan'] ? 'selected' : ''; ?>>
                                                                <?= htmlspecialchars($p['nama']); ?> - <?= htmlspecialchars($p['no_hp']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Karyawan -->
                                    <div class="info-card">
                                        <div class="info-header">
                                            <i class="fas fa-user-tie"></i> Karyawan
                                        </div>
                                        <div class="info-content">
                                            <div class="info-row">
                                                <span class="info-label">Nama:</span>
                                                <div class="info-value">
                                                    <select class="form-select form-select-sm" id="id_karyawan" name="id_karyawan" required>
                                                        <option value="">Pilih Karyawan</option>
                                                        <?php foreach ($karyawan as $k): ?>
                                                            <option value="<?= $k['id_user']; ?>"
                                                                <?= (isset($_POST['id_karyawan']) && $_POST['id_karyawan'] == $k['id_user']) || 
                                                                    $pesanan['id_karyawan'] == $k['id_user'] ? 'selected' : ''; ?>>
                                                                <?= htmlspecialchars($k['nama_lengkap']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Ringkasan Items -->
                                    <div class="info-card">
                                        <div class="info-header">
                                            <i class="fas fa-info-circle"></i> Ringkasan Items
                                        </div>
                                        <div class="info-content">
                                            <div class="info-row">
                                                <span class="info-label">Jumlah Items:</span>
                                                <span class="info-value highlight" id="summary-items-count"><?= count($pesanan_items) ?: 1; ?></span>
                                            </div>
                                            <div class="info-row">
                                                <span class="info-label">Total Ukuran:</span>
                                                <span class="info-value" id="summary-ukuran"><?= $total_ukuran; ?> set</span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Waktu -->
                                    <div class="info-card">
                                        <div class="info-header">
                                            <i class="fas fa-calendar"></i> Waktu
                                        </div>
                                        <div class="info-content">
                                            <div class="info-row">
                                                <span class="info-label">Pesan:</span>
                                                <div class="info-value">
                                                    <input type="date" class="form-control form-control-sm" name="tgl_pesanan" 
                                                           value="<?= isset($_POST['tgl_pesanan']) ? $_POST['tgl_pesanan'] : date('Y-m-d', strtotime($pesanan['tgl_pesanan'])); ?>" 
                                                           required>
                                                </div>
                                            </div>
                                            <div class="info-row">
                                                <span class="info-label">Selesai:</span>
                                                <div class="info-value">
                                                    <input type="date" class="form-control form-control-sm" name="tgl_selesai"
                                                           value="<?= isset($_POST['tgl_selesai']) ? $_POST['tgl_selesai'] : (!empty($pesanan['tgl_selesai']) ? date('Y-m-d', strtotime($pesanan['tgl_selesai'])) : ''); ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- DAFTAR ITEMS PESANAN (MULTIPLE ITEMS) -->
                            <div class="edit-form-container items-section">
                                <h5><i class="fas fa-tshirt"></i> Items Pesanan</h5>
                                
                                <div id="items-container">
                                    <?php if (!empty($pesanan_items)): ?>
                                        <?php foreach ($pesanan_items as $index => $item): ?>
                                        <div class="item-card mb-3 p-3" data-index="<?= $index; ?>">
                                            <div class="item-header">
                                                <div class="item-title">
                                                    <span class="item-badge"><?= $index + 1; ?></span>
                                                    Item <?= $index + 1; ?>
                                                </div>
                                                <button type="button" class="btn btn-danger btn-sm remove-item-btn">
                                                    <i class="fas fa-trash"></i> Hapus
                                                </button>
                                            </div>
                                            
                                            <div class="row g-2">
                                                <div class="col-md-6">
                                                    <label class="form-label">Jenis Pakaian <span class="required">*</span></label>
                                                    <input type="text" class="form-control form-control-sm item-jenis" 
                                                           name="items[<?= $index; ?>][jenis_pakaian]" 
                                                           value="<?= htmlspecialchars($item['jenis_pakaian']); ?>" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Bahan</label>
                                                    <input type="text" class="form-control form-control-sm item-bahan" 
                                                           name="items[<?= $index; ?>][bahan]" 
                                                           value="<?= htmlspecialchars($item['bahan']); ?>">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">Jumlah</label>
                                                    <input type="number" class="form-control form-control-sm item-jumlah" 
                                                           name="items[<?= $index; ?>][jumlah]" 
                                                           value="<?= $item['jumlah'] ?? 1; ?>" min="1">
                                                </div>
                                                <div class="col-md-5">
                                                    <label class="form-label">Harga Satuan (Rp)</label>
                                                    <input type="number" class="form-control form-control-sm item-harga" 
                                                           name="items[<?= $index; ?>][harga_satuan]" 
                                                           value="<?= $item['harga_satuan']; ?>" min="0" step="1000">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Total Item (Rp)</label>
                                                    <input type="number" class="form-control form-control-sm item-total" readonly 
                                                           value="<?= ($item['harga_satuan'] ?? 0) * ($item['jumlah'] ?? 1); ?>">
                                                </div>
                                                <div class="col-12">
                                                    <label class="form-label">Catatan Item</label>
                                                    <textarea class="form-control form-control-sm" 
                                                              name="items[<?= $index; ?>][catatan_item]" 
                                                              rows="1"><?= htmlspecialchars($item['catatan'] ?? ''); ?></textarea>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <!-- Item default -->
                                        <div class="item-card mb-3 p-3" data-index="0">
                                            <div class="item-header">
                                                <div class="item-title">
                                                    <span class="item-badge">1</span>
                                                    Item 1
                                                </div>
                                                <button type="button" class="btn btn-danger btn-sm remove-item-btn" disabled>
                                                    <i class="fas fa-trash"></i> Hapus
                                                </button>
                                            </div>
                                            
                                            <div class="row g-2">
                                                <div class="col-md-6">
                                                    <label class="form-label">Jenis Pakaian <span class="required">*</span></label>
                                                    <input type="text" class="form-control form-control-sm item-jenis" 
                                                           name="items[0][jenis_pakaian]" 
                                                           value="<?= isset($_POST['items'][0]['jenis_pakaian']) ? $_POST['items'][0]['jenis_pakaian'] : htmlspecialchars($pesanan['jenis_pakaian'] ?? ''); ?>" 
                                                           required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Bahan</label>
                                                    <input type="text" class="form-control form-control-sm item-bahan" 
                                                           name="items[0][bahan]" 
                                                           value="<?= isset($_POST['items'][0]['bahan']) ? $_POST['items'][0]['bahan'] : htmlspecialchars($pesanan['bahan'] ?? ''); ?>">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">Jumlah</label>
                                                    <input type="number" class="form-control form-control-sm item-jumlah" 
                                                           name="items[0][jumlah]" 
                                                           value="<?= isset($_POST['items'][0]['jumlah']) ? $_POST['items'][0]['jumlah'] : '1'; ?>" min="1">
                                                </div>
                                                <div class="col-md-5">
                                                    <label class="form-label">Harga Satuan (Rp)</label>
                                                    <input type="number" class="form-control form-control-sm item-harga" 
                                                           name="items[0][harga_satuan]" 
                                                           value="<?= isset($_POST['items'][0]['harga_satuan']) ? $_POST['items'][0]['harga_satuan'] : $pesanan['total_harga']; ?>" 
                                                           min="0" step="1000">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Total Item (Rp)</label>
                                                    <input type="number" class="form-control form-control-sm item-total" readonly 
                                                           value="<?= (isset($_POST['items'][0]['harga_satuan']) ? $_POST['items'][0]['harga_satuan'] : $pesanan['total_harga']) * (isset($_POST['items'][0]['jumlah']) ? $_POST['items'][0]['jumlah'] : 1); ?>">
                                                </div>
                                                <div class="col-12">
                                                    <label class="form-label">Catatan Item</label>
                                                    <textarea class="form-control form-control-sm" 
                                                              name="items[0][catatan_item]" 
                                                              rows="1"><?= isset($_POST['items'][0]['catatan_item']) ? $_POST['items'][0]['catatan_item'] : htmlspecialchars($pesanan['catatan'] ?? ''); ?></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <button type="button" class="btn btn-primary btn-sm mt-2" id="add-item-btn">
                                    <i class="fas fa-plus"></i> Tambah Item
                                </button>
                                
                                <!-- Items Summary -->
                                <div class="items-summary mt-3">
                                    <div class="summary-item">
                                        <span class="summary-label">Total Items:</span>
                                        <span class="summary-value" id="total-items-count"><?= count($pesanan_items) ?: 1; ?> items</span>
                                    </div>
                                    
                                    <div class="summary-item">
                                        <span class="summary-label">Total Ukuran:</span>
                                        <span class="summary-value" id="total-ukuran-summary"><?= $total_ukuran; ?> set</span>
                                    </div>
                                    
                                    <div class="summary-item" style="border-top: 2px solid #bae6fd; padding-top: 0.8rem;">
                                        <span class="summary-label">Total Harga Items:</span>
                                        <span class="summary-value" id="total-harga-items">Rp <?= number_format($total_harga_items ?: $pesanan['total_harga'], 0, ',', '.'); ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Informasi Ukuran -->
                            <div class="edit-form-container">
                                <h5><i class="fas fa-ruler-combined"></i> Informasi Ukuran</h5>
                                
                                <!-- Ukuran Atasan -->
                                <?php if (!empty($ukuran_atasan)): ?>
                                    <div class="ukuran-section">
                                        <div class="ukuran-header">
                                            <div class="ukuran-title">
                                                <i class="fas fa-tshirt"></i> Ukuran Atasan
                                            </div>
                                            <span class="kuantitas-badge"><?= count($ukuran_atasan); ?> set</span>
                                        </div>
                                        
                                        <?php foreach ($ukuran_atasan as $index => $atasan): ?>
                                        <div class="mb-3">
                                            <span class="ukuran-badge">Set <?= $index + 1 ?></span>
                                            
                                            <table class="ukuran-table">
                                                <thead>
                                                    <tr>
                                                        <th>Bagian</th>
                                                        <th>Ukuran (cm)</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td>Krah</td>
                                                        <td>
                                                            <input type="number" class="ukuran-input" step="0.01" 
                                                                   name="ukuran_atasan[<?= $index; ?>][krah]" 
                                                                   value="<?= $atasan['krah']; ?>" placeholder="0.00">
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>Pundak</td>
                                                        <td>
                                                            <input type="number" class="ukuran-input" step="0.01" 
                                                                   name="ukuran_atasan[<?= $index; ?>][pundak]" 
                                                                   value="<?= $atasan['pundak']; ?>" placeholder="0.00">
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>Tangan</td>
                                                        <td>
                                                            <input type="number" class="ukuran-input" step="0.01" 
                                                                   name="ukuran_atasan[<?= $index; ?>][tangan]" 
                                                                   value="<?= $atasan['tangan']; ?>" placeholder="0.00">
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>LD/LP</td>
                                                        <td>
                                                            <input type="number" class="ukuran-input" step="0.01" 
                                                                   name="ukuran_atasan[<?= $index; ?>][ld_lp]" 
                                                                   value="<?= $atasan['ld_lp']; ?>" placeholder="0.00">
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>Badan</td>
                                                        <td>
                                                            <input type="number" class="ukuran-input" step="0.01" 
                                                                   name="ukuran_atasan[<?= $index; ?>][badan]" 
                                                                   value="<?= $atasan['badan']; ?>" placeholder="0.00">
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>Pinggang</td>
                                                        <td>
                                                            <input type="number" class="ukuran-input" step="0.01" 
                                                                   name="ukuran_atasan[<?= $index; ?>][pinggang]" 
                                                                   value="<?= $atasan['pinggang']; ?>" placeholder="0.00">
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>Pinggul</td>
                                                        <td>
                                                            <input type="number" class="ukuran-input" step="0.01" 
                                                                   name="ukuran_atasan[<?= $index; ?>][pinggul]" 
                                                                   value="<?= $atasan['pinggul']; ?>" placeholder="0.00">
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>Panjang</td>
                                                        <td>
                                                            <input type="number" class="ukuran-input" step="0.01" 
                                                                   name="ukuran_atasan[<?= $index; ?>][panjang]" 
                                                                   value="<?= $atasan['panjang']; ?>" placeholder="0.00">
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                            
                                            <?php if (!empty($atasan['keterangan'])): ?>
                                            <div class="keterangan-box">
                                                <strong>Keterangan:</strong>
                                                <textarea class="keterangan-textarea" 
                                                          name="ukuran_atasan[<?= $index; ?>][keterangan]" 
                                                          placeholder="Keterangan..."><?= $atasan['keterangan']; ?></textarea>
                                            </div>
                                            <?php else: ?>
                                            <div class="keterangan-box">
                                                <strong>Keterangan:</strong>
                                                <textarea class="keterangan-textarea" 
                                                          name="ukuran_atasan[<?= $index; ?>][keterangan]" 
                                                          placeholder="Keterangan..."></textarea>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($index > 0): ?>
                                            <button type="button" class="btn btn-danger btn-sm remove-ukuran-btn" data-type="atasan" data-index="<?= $index; ?>">
                                                <i class="fas fa-trash"></i> Hapus Set Ini
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                        <?php endforeach; ?>
                                        
                                        <button type="button" class="btn btn-primary btn-sm add-ukuran-btn" data-type="atasan">
                                            <i class="fas fa-plus"></i> Tambah Set Atasan
                                        </button>
                                    </div>
                                <?php endif; ?>

                                <!-- Ukuran Bawahan -->
                                <?php if (!empty($ukuran_bawahan)): ?>
                                    <div class="ukuran-section">
                                        <div class="ukuran-header">
                                            <div class="ukuran-title">
                                                <i class="fas fa-tshirt"></i> Ukuran Bawahan
                                            </div>
                                            <span class="kuantitas-badge"><?= count($ukuran_bawahan); ?> set</span>
                                        </div>
                                        
                                        <?php foreach ($ukuran_bawahan as $index => $bawahan): ?>
                                        <div class="mb-3">
                                            <span class="ukuran-badge">Set <?= $index + 1 ?></span>
                                            
                                            <table class="ukuran-table">
                                                <thead>
                                                    <tr>
                                                        <th>Bagian</th>
                                                        <th>Ukuran (cm)</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td>Pinggang</td>
                                                        <td>
                                                            <input type="number" class="ukuran-input" step="0.01" 
                                                                   name="ukuran_bawahan[<?= $index; ?>][pinggang]" 
                                                                   value="<?= $bawahan['pinggang']; ?>" placeholder="0.00">
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>Pinggul</td>
                                                        <td>
                                                            <input type="number" class="ukuran-input" step="0.01" 
                                                                   name="ukuran_bawahan[<?= $index; ?>][pinggul]" 
                                                                   value="<?= $bawahan['pinggul']; ?>" placeholder="0.00">
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>Kres</td>
                                                        <td>
                                                            <input type="number" class="ukuran-input" step="0.01" 
                                                                   name="ukuran_bawahan[<?= $index; ?>][kres]" 
                                                                   value="<?= $bawahan['kres']; ?>" placeholder="0.00">
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>Paha</td>
                                                        <td>
                                                            <input type="number" class="ukuran-input" step="0.01" 
                                                                   name="ukuran_bawahan[<?= $index; ?>][paha]" 
                                                                   value="<?= $bawahan['paha']; ?>" placeholder="0.00">
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>Lutut</td>
                                                        <td>
                                                            <input type="number" class="ukuran-input" step="0.01" 
                                                                   name="ukuran_bawahan[<?= $index; ?>][lutut]" 
                                                                   value="<?= $bawahan['lutut']; ?>" placeholder="0.00">
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>L. Bawah</td>
                                                        <td>
                                                            <input type="number" class="ukuran-input" step="0.01" 
                                                                   name="ukuran_bawahan[<?= $index; ?>][l_bawah]" 
                                                                   value="<?= $bawahan['l_bawah']; ?>" placeholder="0.00">
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>Panjang</td>
                                                        <td>
                                                            <input type="number" class="ukuran-input" step="0.01" 
                                                                   name="ukuran_bawahan[<?= $index; ?>][panjang]" 
                                                                   value="<?= $bawahan['panjang']; ?>" placeholder="0.00">
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                            
                                            <?php if (!empty($bawahan['keterangan'])): ?>
                                            <div class="keterangan-box">
                                                <strong>Keterangan:</strong>
                                                <textarea class="keterangan-textarea" 
                                                          name="ukuran_bawahan[<?= $index; ?>][keterangan]" 
                                                          placeholder="Keterangan..."><?= $bawahan['keterangan']; ?></textarea>
                                            </div>
                                            <?php else: ?>
                                            <div class="keterangan-box">
                                                <strong>Keterangan:</strong>
                                                <textarea class="keterangan-textarea" 
                                                          name="ukuran_bawahan[<?= $index; ?>][keterangan]" 
                                                          placeholder="Keterangan..."></textarea>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($index > 0): ?>
                                            <button type="button" class="btn btn-danger btn-sm remove-ukuran-btn" data-type="bawahan" data-index="<?= $index; ?>">
                                                <i class="fas fa-trash"></i> Hapus Set Ini
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                        <?php endforeach; ?>
                                        
                                        <button type="button" class="btn btn-primary btn-sm add-ukuran-btn" data-type="bawahan">
                                            <i class="fas fa-plus"></i> Tambah Set Bawahan
                                        </button>
                                    </div>
                                <?php endif; ?>

                                <!-- Jika tidak ada ukuran sama sekali -->
                                <?php if (empty($ukuran_atasan) && empty($ukuran_bawahan)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-ruler-combined"></i>
                                        <p>Belum ada data ukuran untuk pesanan ini</p>
                                        <div class="mt-2">
                                            <button type="button" class="btn btn-primary btn-sm" id="addAtasanEmpty">
                                                <i class="fas fa-plus"></i> Tambah Ukuran Atasan
                                            </button>
                                            <button type="button" class="btn btn-primary btn-sm" id="addBawahanEmpty">
                                                <i class="fas fa-plus"></i> Tambah Ukuran Bawahan
                                            </button>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Kolom Kanan - Sidebar Info -->
                        <div class="col-lg-4">
                            <!-- Informasi Pembayaran -->
                            <div class="edit-form-container">
                                <h5><i class="fas fa-money-bill-wave"></i> Pembayaran</h5>
                                
                                <div class="payment-card">
                                    <div class="payment-header">
                                        <i class="fas fa-receipt"></i> Ringkasan
                                    </div>
                                    <div class="payment-item">
                                        <span class="payment-label">Total Harga:</span>
                                        <span class="payment-value total" id="displayTotal">Rp <?= number_format($pesanan['total_harga'], 0, ',', '.'); ?></span>
                                    </div>
                                    <div class="payment-item">
                                        <span class="payment-label">Jumlah Bayar:</span>
                                        <span class="payment-value paid" id="displayBayar">Rp <?= number_format($pesanan['jumlah_bayar'], 0, ',', '.'); ?></span>
                                    </div>
                                    <div class="payment-item">
                                        <span class="payment-label">Sisa Bayar:</span>
                                        <span class="payment-value remaining" id="displaySisa">Rp <?= number_format($pesanan['sisa_bayar'], 0, ',', '.'); ?></span>
                                    </div>
                                    
                                    <!-- Progress Bar Pembayaran -->
                                    <?php 
                                    $persentase = 0;
                                    if ($pesanan['total_harga'] > 0) {
                                        $persentase = min(100, ($pesanan['jumlah_bayar'] / $pesanan['total_harga']) * 100);
                                    }
                                    ?>
                                    <div class="payment-percentage">
                                        <?= number_format($persentase, 1); ?>% Terbayar
                                    </div>
                                    <div class="payment-progress">
                                        <div class="payment-progress-bar" style="width: <?= $persentase; ?>%"></div>
                                    </div>
                                </div>
                                
                                <!-- Input Pembayaran -->
                                <div class="mt-3">
                                    <div class="mb-2">
                                        <label class="form-label">Total Harga (Rp) <span class="required">*</span></label>
                                        <input type="number" class="form-control form-control-sm" id="total_harga" name="total_harga"
                                               value="<?= isset($_POST['total_harga']) ? $_POST['total_harga'] : $pesanan['total_harga']; ?>" 
                                               min="0" step="1000" required>
                                    </div>
                                    
                                    <div class="mb-2">
                                        <label class="form-label">Jumlah Bayar (Rp)</label>
                                        <input type="number" class="form-control form-control-sm" id="jumlah_bayar" name="jumlah_bayar"
                                               value="<?= isset($_POST['jumlah_bayar']) ? $_POST['jumlah_bayar'] : $pesanan['jumlah_bayar']; ?>" 
                                               min="0" step="1000">
                                    </div>
                                    
                                    <div class="mb-2">
                                        <label class="form-label">Sisa Bayar (Rp)</label>
                                        <input type="number" class="form-control form-control-sm" id="sisa_bayar" name="sisa_bayar"
                                               value="<?= isset($_POST['sisa_bayar']) ? $_POST['sisa_bayar'] : $pesanan['sisa_bayar']; ?>" 
                                               readonly>
                                    </div>
                                </div>
                            </div>

                            <!-- Status Pesanan -->
                            <div class="edit-form-container">
                                <h5><i class="fas fa-tasks"></i> Status Pesanan</h5>
                                
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select form-select-sm" id="status_pesanan" name="status_pesanan">
                                        <option value="belum" 
                                            <?= (isset($_POST['status_pesanan']) && $_POST['status_pesanan'] == 'belum') || 
                                                $pesanan['status_pesanan'] == 'belum' ? 'selected' : ''; ?>>
                                            Belum Diproses
                                        </option>
                                        <option value="dalam_proses"
                                            <?= (isset($_POST['status_pesanan']) && $_POST['status_pesanan'] == 'dalam_proses') || 
                                                $pesanan['status_pesanan'] == 'dalam_proses' ? 'selected' : ''; ?>>
                                            Dalam Proses
                                        </option>
                                        <option value="selesai"
                                            <?= (isset($_POST['status_pesanan']) && $_POST['status_pesanan'] == 'selesai') || 
                                                $pesanan['status_pesanan'] == 'selesai' ? 'selected' : ''; ?>>
                                            Selesai
                                        </option>
                                    </select>
                                </div>
                                
                                <div class="status-badge <?= $status_class; ?>">
                                    Status Saat Ini: <?= $status_text; ?>
                                </div>
                            </div>

                            <!-- Tombol Aksi -->
                            <div class="edit-form-container">
                                <h5><i class="fas fa-bolt"></i> Aksi</h5>
                                
                                <div class="quick-actions">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-save"></i> Update Pesanan
                                    </button>
                                    <button type="reset" class="btn btn-warning">
                                        <i class="fas fa-redo"></i> Reset Form
                                    </button>
                                    <a href="pesanan.php" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Batal
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Footer -->
        <?php include '../includes/footer.php'; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validasi form Bootstrap
        (() => {
            'use strict';
            const forms = document.querySelectorAll('.needs-validation');
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        })();

        // Format angka ke Rupiah
        function formatRupiah(angka) {
            return new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                minimumFractionDigits: 0
            }).format(angka);
        }

        // Hitung sisa bayar secara otomatis
        function hitungSisaBayar() {
            const totalHarga = parseFloat(document.getElementById('total_harga').value) || 0;
            const jumlahBayar = parseFloat(document.getElementById('jumlah_bayar').value) || 0;
            const sisaBayar = totalHarga - jumlahBayar;

            // Update input sisa bayar
            document.getElementById('sisa_bayar').value = sisaBayar;

            // Update display
            document.getElementById('displayTotal').textContent = formatRupiah(totalHarga);
            document.getElementById('displayBayar').textContent = formatRupiah(jumlahBayar);
            document.getElementById('displaySisa').textContent = formatRupiah(sisaBayar);

            // Update warna sisa bayar
            const displaySisa = document.getElementById('displaySisa');
            if (sisaBayar > 0) {
                displaySisa.className = 'payment-value remaining';
            } else if (sisaBayar < 0) {
                displaySisa.className = 'payment-value remaining';
            } else {
                displaySisa.className = 'payment-value paid';
            }
            
            // Update progress bar
            const persentase = totalHarga > 0 ? Math.min(100, (jumlahBayar / totalHarga) * 100) : 0;
            const progressBar = document.querySelector('.payment-progress-bar');
            const percentageText = document.querySelector('.payment-percentage');
            
            if (progressBar) {
                progressBar.style.width = `${persentase}%`;
            }
            
            if (percentageText) {
                percentageText.textContent = `${persentase.toFixed(1)}% Terbayar`;
            }
            
            return sisaBayar;
        }

        // ============================================================
        // FUNGSI UNTUK MULTIPLE ITEMS
        // ============================================================

        // Fungsi untuk menambah item baru
        document.getElementById('add-item-btn').addEventListener('click', function() {
            const container = document.getElementById('items-container');
            const items = container.querySelectorAll('.item-card');
            const newIndex = items.length;
            
            // Enable remove button untuk item pertama jika ada lebih dari satu item
            if (items.length === 1) {
                const firstRemoveBtn = items[0].querySelector('.remove-item-btn');
                if (firstRemoveBtn) {
                    firstRemoveBtn.removeAttribute('disabled');
                    firstRemoveBtn.style.opacity = '1';
                    firstRemoveBtn.style.cursor = 'pointer';
                }
            }
            
            const newItemHTML = `
                <div class="item-card mb-3 p-3" data-index="${newIndex}">
                    <div class="item-header">
                        <div class="item-title">
                            <span class="item-badge">${newIndex + 1}</span>
                            Item ${newIndex + 1}
                        </div>
                        <button type="button" class="btn btn-danger btn-sm remove-item-btn">
                            <i class="fas fa-trash"></i> Hapus
                        </button>
                    </div>
                    
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label">Jenis Pakaian <span class="required">*</span></label>
                            <input type="text" class="form-control form-control-sm item-jenis" 
                                   name="items[${newIndex}][jenis_pakaian]" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Bahan</label>
                            <input type="text" class="form-control form-control-sm item-bahan" 
                                   name="items[${newIndex}][bahan]">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Jumlah</label>
                            <input type="number" class="form-control form-control-sm item-jumlah" 
                                   name="items[${newIndex}][jumlah]" value="1" min="1">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Harga Satuan (Rp)</label>
                            <input type="number" class="form-control form-control-sm item-harga" 
                                   name="items[${newIndex}][harga_satuan]" value="0" min="0" step="1000">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Total Item (Rp)</label>
                            <input type="number" class="form-control form-control-sm item-total" readonly value="0">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Catatan Item</label>
                            <textarea class="form-control form-control-sm" 
                                      name="items[${newIndex}][catatan_item]" rows="1"></textarea>
                        </div>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', newItemHTML);
            
            // Attach event listeners untuk item baru
            const newItem = container.lastElementChild;
            attachItemEventListeners(newItem);
            
            updateItemNumbers();
            calculateAllItemsTotal();
            updateSummary();
        });

        // Fungsi untuk menghitung total per item
        function calculateItemTotal(itemCard) {
            const jumlah = parseFloat(itemCard.querySelector('.item-jumlah').value) || 0;
            const harga = parseFloat(itemCard.querySelector('.item-harga').value) || 0;
            const total = jumlah * harga;
            
            itemCard.querySelector('.item-total').value = total;
            return total;
        }

        // Fungsi untuk menghitung total semua items
        function calculateAllItemsTotal() {
            const items = document.querySelectorAll('.item-card');
            let totalAll = 0;
            
            items.forEach(itemCard => {
                const itemTotal = calculateItemTotal(itemCard);
                totalAll += itemTotal;
            });
            
            // Update total harga form
            const totalHargaInput = document.getElementById('total_harga');
            totalHargaInput.value = totalAll;
            
            // Update summary
            document.getElementById('total-items-count').textContent = `${items.length} items`;
            document.getElementById('total-harga-items').textContent = formatRupiah(totalAll);
            
            // Trigger hitung sisa bayar
            hitungSisaBayar();
            
            return totalAll;
        }

        // Fungsi untuk update nomor item
        function updateItemNumbers() {
            const items = document.querySelectorAll('.item-card');
            
            items.forEach((itemCard, index) => {
                itemCard.setAttribute('data-index', index);
                
                // Update badge
                const badge = itemCard.querySelector('.item-badge');
                if (badge) {
                    badge.textContent = index + 1;
                }
                
                // Update title
                const title = itemCard.querySelector('.item-title');
                if (title) {
                    title.innerHTML = `<span class="item-badge">${index + 1}</span> Item ${index + 1}`;
                }
                
                // Update input names
                const inputs = itemCard.querySelectorAll('input, textarea');
                inputs.forEach(input => {
                    const name = input.getAttribute('name');
                    if (name && name.includes('items')) {
                        const newName = name.replace(/\[items\]\[\d+\]/, `[items][${index}]`);
                        input.setAttribute('name', newName);
                    }
                });
                
                // Update remove button status untuk item pertama
                const removeBtn = itemCard.querySelector('.remove-item-btn');
                if (removeBtn) {
                    if (index === 0 && items.length === 1) {
                        removeBtn.setAttribute('disabled', 'disabled');
                        removeBtn.style.opacity = '0.5';
                        removeBtn.style.cursor = 'not-allowed';
                    } else {
                        removeBtn.removeAttribute('disabled');
                        removeBtn.style.opacity = '1';
                        removeBtn.style.cursor = 'pointer';
                    }
                }
            });
        }

        // Fungsi untuk attach event listeners ke item
        function attachItemEventListeners(itemCard) {
            if (!itemCard) return;
            
            // Remove item button
            const removeBtn = itemCard.querySelector('.remove-item-btn');
            if (removeBtn) {
                removeBtn.addEventListener('click', function() {
                    const items = document.querySelectorAll('.item-card');
                    if (items.length > 1) {
                        if (confirm('Apakah Anda yakin ingin menghapus item ini?')) {
                            itemCard.remove();
                            updateItemNumbers();
                            calculateAllItemsTotal();
                            updateSummary();
                        }
                    }
                });
            }
            
            // Auto-calculate per item
            const jumlahInput = itemCard.querySelector('.item-jumlah');
            const hargaInput = itemCard.querySelector('.item-harga');
            
            if (jumlahInput) {
                jumlahInput.addEventListener('input', function() {
                    calculateItemTotal(itemCard);
                    calculateAllItemsTotal();
                    updateSummary();
                });
            }
            
            if (hargaInput) {
                hargaInput.addEventListener('input', function() {
                    calculateItemTotal(itemCard);
                    calculateAllItemsTotal();
                    updateSummary();
                });
            }
        }

        // Fungsi untuk update summary
        function updateSummary() {
            const items = document.querySelectorAll('.item-card');
            const itemsCount = items.length;
            
            // Update summary card
            document.getElementById('summary-items-count').textContent = itemsCount;
        }

        // ============================================================
        // FUNGSI UNTUK UKURAN
        // ============================================================

        // Fungsi untuk menambah set ukuran
        document.querySelectorAll('.add-ukuran-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const type = this.getAttribute('data-type');
                addUkuranSet(type);
            });
        });

        // Fungsi untuk menambah ukuran atasan jika kosong
        document.getElementById('addAtasanEmpty')?.addEventListener('click', function() {
            addUkuranSet('atasan', true);
            this.parentElement.parentElement.remove(); // Hapus empty state
        });

        // Fungsi untuk menambah ukuran bawahan jika kosong
        document.getElementById('addBawahanEmpty')?.addEventListener('click', function() {
            addUkuranSet('bawahan', true);
            this.parentElement.parentElement.remove(); // Hapus empty state
        });

        // Fungsi untuk menghapus set ukuran
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-ukuran-btn') || 
                e.target.closest('.remove-ukuran-btn')) {
                
                const btn = e.target.classList.contains('remove-ukuran-btn') ? 
                           e.target : e.target.closest('.remove-ukuran-btn');
                
                if (confirm('Apakah Anda yakin ingin menghapus set ukuran ini?')) {
                    const section = btn.closest('.mb-3');
                    section.remove();
                    
                    // Update nomor set
                    updateUkuranNumbers();
                }
            }
        });

        // Fungsi untuk menambah set ukuran
        function addUkuranSet(type, isFirst = false) {
            const ukuranSections = document.querySelectorAll('.ukuran-section');
            let ukuranSection;
            
            // Cari section yang sesuai dengan type
            for (const section of ukuranSections) {
                const addBtn = section.querySelector('.add-ukuran-btn');
                if (addBtn && addBtn.getAttribute('data-type') === type) {
                    ukuranSection = section;
                    break;
                }
            }
            
            // Jika tidak ada section, buat baru
            if (!ukuranSection) {
                const ukuranContainer = document.querySelector('.edit-form-container > h5:contains("Informasi Ukuran")').parentElement;
                const emptyState = ukuranContainer.querySelector('.empty-state');
                
                if (emptyState) {
                    emptyState.remove();
                }
                
                let sectionHTML = '';
                if (type === 'atasan') {
                    sectionHTML = `
                        <div class="ukuran-section">
                            <div class="ukuran-header">
                                <div class="ukuran-title">
                                    <i class="fas fa-tshirt"></i> Ukuran Atasan
                                </div>
                                <span class="kuantitas-badge">1 set</span>
                            </div>
                            <div class="mb-3">
                                <span class="ukuran-badge">Set 1</span>
                                
                                <table class="ukuran-table">
                                    <thead>
                                        <tr>
                                            <th>Bagian</th>
                                            <th>Ukuran (cm)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>Krah</td>
                                            <td>
                                                <input type="number" class="ukuran-input" step="0.01" 
                                                       name="ukuran_atasan[0][krah]" placeholder="0.00">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Pundak</td>
                                            <td>
                                                <input type="number" class="ukuran-input" step="0.01" 
                                                       name="ukuran_atasan[0][pundak]" placeholder="0.00">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Tangan</td>
                                            <td>
                                                <input type="number" class="ukuran-input" step="0.01" 
                                                       name="ukuran_atasan[0][tangan]" placeholder="0.00">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>LD/LP</td>
                                            <td>
                                                <input type="number" class="ukuran-input" step="0.01" 
                                                       name="ukuran_atasan[0][ld_lp]" placeholder="0.00">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Badan</td>
                                            <td>
                                                <input type="number" class="ukuran-input" step="0.01" 
                                                       name="ukuran_atasan[0][badan]" placeholder="0.00">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Pinggang</td>
                                            <td>
                                                <input type="number" class="ukuran-input" step="0.01" 
                                                       name="ukuran_atasan[0][pinggang]" placeholder="0.00">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Pinggul</td>
                                            <td>
                                                <input type="number" class="ukuran-input" step="0.01" 
                                                       name="ukuran_atasan[0][pinggul]" placeholder="0.00">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Panjang</td>
                                            <td>
                                                <input type="number" class="ukuran-input" step="0.01" 
                                                       name="ukuran_atasan[0][panjang]" placeholder="0.00">
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                
                                <div class="keterangan-box">
                                    <strong>Keterangan:</strong>
                                    <textarea class="keterangan-textarea" 
                                              name="ukuran_atasan[0][keterangan]" 
                                              placeholder="Keterangan..."></textarea>
                                </div>
                            </div>
                            <button type="button" class="btn btn-primary btn-sm add-ukuran-btn" data-type="atasan">
                                <i class="fas fa-plus"></i> Tambah Set Atasan
                            </button>
                        </div>
                    `;
                } else {
                    sectionHTML = `
                        <div class="ukuran-section">
                            <div class="ukuran-header">
                                <div class="ukuran-title">
                                    <i class="fas fa-tshirt"></i> Ukuran Bawahan
                                </div>
                                <span class="kuantitas-badge">1 set</span>
                            </div>
                            <div class="mb-3">
                                <span class="ukuran-badge">Set 1</span>
                                
                                <table class="ukuran-table">
                                    <thead>
                                        <tr>
                                            <th>Bagian</th>
                                            <th>Ukuran (cm)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>Pinggang</td>
                                            <td>
                                                <input type="number" class="ukuran-input" step="0.01" 
                                                       name="ukuran_bawahan[0][pinggang]" placeholder="0.00">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Pinggul</td>
                                            <td>
                                                <input type="number" class="ukuran-input" step="0.01" 
                                                       name="ukuran_bawahan[0][pinggul]" placeholder="0.00">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Kres</td>
                                            <td>
                                                <input type="number" class="ukuran-input" step="0.01" 
                                                       name="ukuran_bawahan[0][kres]" placeholder="0.00">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Paha</td>
                                            <td>
                                                <input type="number" class="ukuran-input" step="0.01" 
                                                       name="ukuran_bawahan[0][paha]" placeholder="0.00">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Lutut</td>
                                            <td>
                                                <input type="number" class="ukuran-input" step="0.01" 
                                                       name="ukuran_bawahan[0][lutut]" placeholder="0.00">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>L. Bawah</td>
                                            <td>
                                                <input type="number" class="ukuran-input" step="0.01" 
                                                       name="ukuran_bawahan[0][l_bawah]" placeholder="0.00">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Panjang</td>
                                            <td>
                                                <input type="number" class="ukuran-input" step="0.01" 
                                                       name="ukuran_bawahan[0][panjang]" placeholder="0.00">
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                
                                <div class="keterangan-box">
                                    <strong>Keterangan:</strong>
                                    <textarea class="keterangan-textarea" 
                                              name="ukuran_bawahan[0][keterangan]" 
                                              placeholder="Keterangan..."></textarea>
                                </div>
                            </div>
                            <button type="button" class="btn btn-primary btn-sm add-ukuran-btn" data-type="bawahan">
                                <i class="fas fa-plus"></i> Tambah Set Bawahan
                            </button>
                        </div>
                    `;
                }
                
                ukuranContainer.insertAdjacentHTML('beforeend', sectionHTML);
                
                // Attach event listener untuk tombol add baru
                const newAddBtn = ukuranContainer.querySelector(`.add-ukuran-btn[data-type="${type}"]`);
                if (newAddBtn) {
                    newAddBtn.addEventListener('click', function() {
                        addUkuranSet(type);
                    });
                }
                
                // Update total ukuran di summary
                updateTotalUkuranSummary();
                
                return;
            }
            
            // Tambah set baru ke section yang sudah ada
            const sets = ukuranSection.querySelectorAll('.mb-3');
            const newIndex = sets.length;
            
            let html = '';
            if (type === 'atasan') {
                html = `
                    <div class="mb-3">
                        <span class="ukuran-badge">Set ${newIndex + 1}</span>
                        
                        <table class="ukuran-table">
                            <thead>
                                <tr>
                                    <th>Bagian</th>
                                    <th>Ukuran (cm)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Krah</td>
                                    <td>
                                        <input type="number" class="ukuran-input" step="0.01" 
                                               name="ukuran_atasan[${newIndex}][krah]" placeholder="0.00">
                                    </td>
                                </tr>
                                <tr>
                                    <td>Pundak</td>
                                    <td>
                                        <input type="number" class="ukuran-input" step="0.01" 
                                               name="ukuran_atasan[${newIndex}][pundak]" placeholder="0.00">
                                    </td>
                                </tr>
                                <tr>
                                    <td>Tangan</td>
                                    <td>
                                        <input type="number" class="ukuran-input" step="0.01" 
                                               name="ukuran_atasan[${newIndex}][tangan]" placeholder="0.00">
                                    </td>
                                </tr>
                                <tr>
                                    <td>LD/LP</td>
                                    <td>
                                        <input type="number" class="ukuran-input" step="0.01" 
                                               name="ukuran_atasan[${newIndex}][ld_lp]" placeholder="0.00">
                                    </td>
                                </tr>
                                <tr>
                                    <td>Badan</td>
                                    <td>
                                        <input type="number" class="ukuran-input" step="0.01" 
                                               name="ukuran_atasan[${newIndex}][badan]" placeholder="0.00">
                                    </td>
                                </tr>
                                <tr>
                                    <td>Pinggang</td>
                                    <td>
                                        <input type="number" class="ukuran-input" step="0.01" 
                                               name="ukuran_atasan[${newIndex}][pinggang]" placeholder="0.00">
                                    </td>
                                </tr>
                                <tr>
                                    <td>Pinggul</td>
                                    <td>
                                        <input type="number" class="ukuran-input" step="0.01" 
                                               name="ukuran_atasan[${newIndex}][pinggul]" placeholder="0.00">
                                    </td>
                                </tr>
                                <tr>
                                    <td>Panjang</td>
                                    <td>
                                        <input type="number" class="ukuran-input" step="0.01" 
                                               name="ukuran_atasan[${newIndex}][panjang]" placeholder="0.00">
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <div class="keterangan-box">
                            <strong>Keterangan:</strong>
                            <textarea class="keterangan-textarea" 
                                      name="ukuran_atasan[${newIndex}][keterangan]" 
                                      placeholder="Keterangan..."></textarea>
                        </div>
                        
                        <button type="button" class="btn btn-danger btn-sm remove-ukuran-btn" data-type="atasan">
                            <i class="fas fa-trash"></i> Hapus Set Ini
                        </button>
                    </div>
                `;
            } else {
                html = `
                    <div class="mb-3">
                        <span class="ukuran-badge">Set ${newIndex + 1}</span>
                        
                        <table class="ukuran-table">
                            <thead>
                                <tr>
                                    <th>Bagian</th>
                                    <th>Ukuran (cm)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Pinggang</td>
                                    <td>
                                        <input type="number" class="ukuran-input" step="0.01" 
                                               name="ukuran_bawahan[${newIndex}][pinggang]" placeholder="0.00">
                                    </td>
                                </tr>
                                <tr>
                                    <td>Pinggul</td>
                                    <td>
                                        <input type="number" class="ukuran-input" step="0.01" 
                                               name="ukuran_bawahan[${newIndex}][pinggul]" placeholder="0.00">
                                    </td>
                                </tr>
                                <tr>
                                    <td>Kres</td>
                                    <td>
                                        <input type="number" class="ukuran-input" step="0.01" 
                                               name="ukuran_bawahan[${newIndex}][kres]" placeholder="0.00">
                                    </td>
                                </tr>
                                <tr>
                                    <td>Paha</td>
                                    <td>
                                        <input type="number" class="ukuran-input" step="0.01" 
                                               name="ukuran_bawahan[${newIndex}][paha]" placeholder="0.00">
                                    </td>
                                </tr>
                                <tr>
                                    <td>Lutut</td>
                                    <td>
                                        <input type="number" class="ukuran-input" step="0.01" 
                                               name="ukuran_bawahan[${newIndex}][lutut]" placeholder="0.00">
                                    </td>
                                </tr>
                                <tr>
                                    <td>L. Bawah</td>
                                    <td>
                                        <input type="number" class="ukuran-input" step="0.01" 
                                               name="ukuran_bawahan[${newIndex}][l_bawah]" placeholder="0.00">
                                    </td>
                                </tr>
                                <tr>
                                    <td>Panjang</td>
                                    <td>
                                        <input type="number" class="ukuran-input" step="0.01" 
                                               name="ukuran_bawahan[${newIndex}][panjang]" placeholder="0.00">
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <div class="keterangan-box">
                            <strong>Keterangan:</strong>
                            <textarea class="keterangan-textarea" 
                                      name="ukuran_bawahan[${newIndex}][keterangan]" 
                                      placeholder="Keterangan..."></textarea>
                        </div>
                        
                        <button type="button" class="btn btn-danger btn-sm remove-ukuran-btn" data-type="bawahan">
                            <i class="fas fa-trash"></i> Hapus Set Ini
                        </button>
                    </div>
                `;
            }
            
            // Insert sebelum tombol add
            const addBtn = ukuranSection.querySelector('.add-ukuran-btn');
            addBtn.insertAdjacentHTML('beforebegin', html);
            
            // Update kuantitas badge
            updateUkuranNumbers();
            
            // Update total ukuran di summary
            updateTotalUkuranSummary();
        }

        // Fungsi update nomor set ukuran
        function updateUkuranNumbers() {
            document.querySelectorAll('.ukuran-section').forEach(section => {
                const sets = section.querySelectorAll('.mb-3');
                const badge = section.querySelector('.kuantitas-badge');
                if (badge) {
                    badge.textContent = `${sets.length} set`;
                }
                
                sets.forEach((set, index) => {
                    const setBadge = set.querySelector('.ukuran-badge');
                    if (setBadge) {
                        setBadge.textContent = `Set ${index + 1}`;
                    }
                    
                    // Update input names
                    const inputs = set.querySelectorAll('input, textarea');
                    const type = section.querySelector('.add-ukuran-btn').getAttribute('data-type');
                    inputs.forEach(input => {
                        const name = input.getAttribute('name');
                        if (name) {
                            const newName = name.replace(/\[\d+\]/, `[${index}]`);
                            input.setAttribute('name', newName);
                        }
                    });
                });
            });
        }

        // Fungsi untuk update total ukuran di summary
        function updateTotalUkuranSummary() {
            let totalUkuran = 0;
            
            document.querySelectorAll('.ukuran-section').forEach(section => {
                const sets = section.querySelectorAll('.mb-3');
                totalUkuran += sets.length;
            });
            
            // Update summary card
            document.getElementById('summary-ukuran').textContent = `${totalUkuran} set`;
            document.getElementById('total-ukuran-summary').textContent = `${totalUkuran} set`;
        }

        // Inisialisasi saat load
        document.addEventListener('DOMContentLoaded', function() {
            // Attach event listeners untuk items yang sudah ada
            document.querySelectorAll('.item-card').forEach(itemCard => {
                attachItemEventListeners(itemCard);
            });
            
            // Attach event listeners untuk tombol remove ukuran
            document.querySelectorAll('.remove-ukuran-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    if (confirm('Apakah Anda yakin ingin menghapus set ini?')) {
                        const section = this.closest('.mb-3');
                        section.remove();
                        updateUkuranNumbers();
                        updateTotalUkuranSummary();
                    }
                });
            });
            
            // Hitung total awal
            calculateAllItemsTotal();
            
            // Update summary awal
            updateSummary();
            updateTotalUkuranSummary();
            
            // Event listeners untuk kalkulasi otomatis pembayaran
            document.getElementById('total_harga').addEventListener('input', hitungSisaBayar);
            document.getElementById('jumlah_bayar').addEventListener('input', hitungSisaBayar);
            
            // Set tanggal selesai minimal sama dengan tanggal pesan
            const tglPesanInput = document.querySelector('input[name="tgl_pesanan"]');
            const tglSelesaiInput = document.querySelector('input[name="tgl_selesai"]');
            
            if (tglPesanInput && tglSelesaiInput) {
                tglPesanInput.addEventListener('change', function() {
                    const tglPesan = this.value;
                    
                    if (tglPesan) {
                        tglSelesaiInput.min = tglPesan;
                        
                        // Jika tanggal selesai lebih kecil dari tanggal pesan, reset
                        if (tglSelesaiInput.value && tglSelesaiInput.value < tglPesan) {
                            tglSelesaiInput.value = tglPesan;
                        }
                    }
                });
                
                // Set min date awal jika ada nilai
                if (tglPesanInput.value) {
                    tglSelesaiInput.min = tglPesanInput.value;
                }
            }
            
            // Auto focus ke item pertama jika tidak ada data
            const items = document.querySelectorAll('.item-card');
            if (items.length === 1) {
                const firstJenisInput = items[0].querySelector('.item-jenis');
                if (firstJenisInput && !firstJenisInput.value) {
                    setTimeout(() => {
                        firstJenisInput.focus();
                    }, 100);
                }
            }
        });
    </script>
</body>
</html>