<?php
// pesanan/pesanan.php
require_once '../config/database.php';
check_login();
check_role(['admin', 'pemilik','pegawai']);

// Hapus pesanan
if (isset($_GET['hapus'])) {
    $id = clean_input($_GET['hapus']);
    try {
        // Mulai transaction
        $pdo->beginTransaction();
        
        // 1. Hapus data ukuran khusus yang terkait terlebih dahulu
        $sql_delete_ukuran_atasan = "DELETE FROM ukuran_atasan WHERE id_pesanan = ?";
        executeQuery($sql_delete_ukuran_atasan, [$id]);
        
        $sql_delete_ukuran_bawahan = "DELETE FROM ukuran_bawahan WHERE id_pesanan = ?";
        executeQuery($sql_delete_ukuran_bawahan, [$id]);
        
        // 2. Hapus data items pesanan
        $sql_delete_items = "DELETE FROM pesanan_items WHERE id_pesanan = ?";
        executeQuery($sql_delete_items, [$id]);
        
        // 3. Hapus data transaksi yang terkait terlebih dahulu
        $sql_delete_transaksi = "DELETE FROM data_transaksi WHERE id_pesanan = ?";
        executeQuery($sql_delete_transaksi, [$id]);
        
        // 4. Hapus data pesanan
        $sql_delete_pesanan = "DELETE FROM data_pesanan WHERE id_pesanan = ?";
        executeQuery($sql_delete_pesanan, [$id]);
        
        // Commit transaction
        $pdo->commit();
        
        $_SESSION['success'] = "✅ Pesanan berhasil dihapus";
        log_activity("Menghapus pesanan ID: $id");
    } catch (PDOException $e) {
        // Rollback transaction jika ada error
        $pdo->rollBack();
        $_SESSION['error'] = "❌ Gagal menghapus pesanan: " . $e->getMessage();
    }
    header("Location: pesanan.php");
    exit();
}

// Update status pesanan - PERBAIKAN: Handle dengan method POST yang benar
if (isset($_POST['update_status'])) {
    $id = clean_input($_POST['id_pesanan']);
    $status = clean_input($_POST['status_pesanan']);
    
    // Debug: Cek data yang diterima
    error_log("Update status - ID: $id, Status: $status");
    
    try {
        $sql = "UPDATE data_pesanan SET status_pesanan = ? WHERE id_pesanan = ?";
        $result = executeQuery($sql, [$status, $id]);
        
        if ($result) {
            $_SESSION['success'] = "✅ Status pesanan berhasil diupdate";
            log_activity("Mengupdate status pesanan ID: $id menjadi $status");
        } else {
            $_SESSION['error'] = "❌ Gagal mengupdate status: Tidak ada baris yang terpengaruh";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "❌ Gagal mengupdate status: " . $e->getMessage();
        error_log("Error update status: " . $e->getMessage());
    }
    header("Location: pesanan.php");
    exit();
}

// Konfigurasi pagination
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Filter pencarian dan tanggal
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$filter_tanggal = isset($_GET['tanggal']) ? clean_input($_GET['tanggal']) : '';
$filter_status = isset($_GET['status']) ? clean_input($_GET['status']) : '';

// Query dasar
$sql_where = "";
$params = [];
$where_conditions = [];

if (!empty($search)) {
    $where_conditions[] = "(p.id_pesanan LIKE ? OR pel.nama LIKE ? OR u.nama_lengkap LIKE ? OR p.catatan LIKE ? OR pi.jenis_pakaian LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($filter_tanggal)) {
    $where_conditions[] = "DATE(p.tgl_pesanan) = ?";
    $params[] = $filter_tanggal;
}

if (!empty($filter_status)) {
    $where_conditions[] = "p.status_pesanan = ?";
    $params[] = $filter_status;
}

if (count($where_conditions) > 0) {
    $sql_where = "WHERE " . implode(" AND ", $where_conditions);
}

// Hitung total data
try {
    $sql_count = "SELECT COUNT(DISTINCT p.id_pesanan) 
                  FROM data_pesanan p 
                  LEFT JOIN data_pelanggan pel ON p.id_pelanggan = pel.id_pelanggan
                  LEFT JOIN users u ON p.id_karyawan = u.id_user
                  LEFT JOIN pesanan_items pi ON p.id_pesanan = pi.id_pesanan
                  $sql_where";
    $stmt = executeQuery($sql_count, $params);
    $total_pesanan = $stmt->fetchColumn();
    $total_pages = ceil($total_pesanan / $limit);
} catch (PDOException $e) {
    $total_pesanan = 0;
    $total_pages = 1;
}

// Ambil data pesanan dengan multiple items
try {
    $sql = "SELECT DISTINCT p.*, pel.nama AS nama_pelanggan, pel.alamat, u.nama_lengkap AS nama_karyawan
            FROM data_pesanan p
            LEFT JOIN data_pelanggan pel ON p.id_pelanggan = pel.id_pelanggan
            LEFT JOIN users u ON p.id_karyawan = u.id_user
            LEFT JOIN pesanan_items pi ON p.id_pesanan = pi.id_pesanan
            $sql_where
            ORDER BY p.tgl_pesanan DESC
            LIMIT $limit OFFSET $offset";
    $pesanan = getAll($sql, $params);
    
    // Untuk setiap pesanan, ambil data items-nya
    foreach ($pesanan as &$order) {
        $id_pesanan = $order['id_pesanan'];
        
        // Ambil items pesanan dari tabel pesanan_items
        try {
            $sql_items = "SELECT * FROM pesanan_items WHERE id_pesanan = ? ORDER BY id_item ASC";
            $items = getAll($sql_items, [$id_pesanan]);
            
            if (!empty($items)) {
                $order['items'] = $items;
                $order['total_items'] = count($items);
                
                // Hitung total kuantitas
                $total_kuantitas = 0;
                $item_names = [];
                foreach ($items as $item) {
                    $total_kuantitas += ($item['jumlah'] ?? 1);
                    $item_names[] = $item['jenis_pakaian'];
                }
                $order['total_kuantitas'] = $total_kuantitas;
                $order['item_names'] = $item_names;
                
                // Ambil jenis pakaian pertama sebagai yang ditampilkan di tabel
                $order['jenis_pakaian_tampil'] = $item_names[0] ?? 'Pakaian';
                // HAPUS tampilan jumlah daftar items (+N)
            } else {
                // Fallback untuk data lama
                $order['items'] = [];
                $order['total_items'] = 1;
                $order['total_kuantitas'] = 1;
                $order['item_names'] = [$order['jenis_pakaian'] ?? 'Pakaian'];
                $order['jenis_pakaian_tampil'] = $order['jenis_pakaian'] ?? 'Pakaian';
            }
        } catch (PDOException $e) {
            $order['items'] = [];
            $order['total_items'] = 1;
            $order['total_kuantitas'] = 1;
            $order['item_names'] = [$order['jenis_pakaian'] ?? 'Pakaian'];
            $order['jenis_pakaian_tampil'] = $order['jenis_pakaian'] ?? 'Pakaian';
        }
    }
    unset($order); // Hapus reference terakhir
    
} catch (PDOException $e) {
    $_SESSION['error'] = "❌ Gagal memuat data: " . $e->getMessage();
    $pesanan = [];
}

// Hitung statistik pesanan per status
try {
    $sql_stats = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status_pesanan = 'selesai' THEN 1 ELSE 0 END) as selesai,
                    SUM(CASE WHEN status_pesanan = 'dalam_proses' THEN 1 ELSE 0 END) as dalam_proses,
                    SUM(CASE WHEN status_pesanan = 'belum' THEN 1 ELSE 0 END) as belum
                  FROM data_pesanan";
    $stats = getSingle($sql_stats);
    
    $total_selesai = $stats['selesai'] ?? 0;
    $total_dalam_proses = $stats['dalam_proses'] ?? 0;
    $total_belum = $stats['belum'] ?? 0;
} catch (PDOException $e) {
    $total_selesai = 0;
    $total_dalam_proses = 0;
    $total_belum = 0;
}

// Hitung statistik notifikasi
$total_terlambat = 0;
$total_peringatan = 0;
$pesanan_terlambat = [];
$pesanan_peringatan = [];
$today = date('Y-m-d');

if (!empty($pesanan)) {
    foreach ($pesanan as $item) {
        if (!empty($item['tgl_selesai']) && $item['status_pesanan'] != 'selesai') {
            $tgl_selesai = $item['tgl_selesai'];
            $selisih_hari = floor((strtotime($tgl_selesai) - strtotime($today)) / (60 * 60 * 24));
            
            if ($selisih_hari < 0) {
                // Sudah melewati tanggal selesai
                $total_terlambat++;
                $pesanan_terlambat[] = $item['id_pesanan'];
            } elseif ($selisih_hari <= 3 && $selisih_hari >= 0) {
                // 3 hari atau kurang sebelum tanggal selesai
                $total_peringatan++;
                $pesanan_peringatan[] = $item['id_pesanan'];
            }
        }
    }
}

$page_title = "Data Pesanan";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Pesanan - SIM Parapatan Tailor</title>
    <link rel="shortcut icon" href="../assets/images/logoterakhir.png" type="image/x-icon" />

    <!-- Bootstrap 5.3.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
        max-width: 1800px;
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
    
    /* Stats Cards - Konsisten dengan Dashboard */
    .stats-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 0.8rem;
        margin-bottom: 1.5rem;
    }
    
    .stat-card {
        background: white;
        padding: 1rem;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        border-left: 3px solid;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        text-align: center;
    }
    
    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.12);
    }
    
    .stat-icon {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
        margin: 0 auto 0.75rem;
    }
    
    .stat-number {
        font-size: 1.25rem;
        font-weight: 700;
        line-height: 1;
        margin-bottom: 0.2rem;
        color: var(--dark);
    }
    
    .stat-label {
        font-size: 0.7rem;
        color: var(--gray);
        font-weight: 500;
    }
    
    /* Card Colors - Sama seperti Dashboard */
    .stat-card.total { 
        border-left-color: var(--primary); 
    }
    .stat-card.selesai { 
        border-left-color: var(--success); 
    }
    .stat-card.dalam_proses { 
        border-left-color: var(--warning); 
    }
    .stat-card.belum { 
        border-left-color: var(--danger); 
    }
    .stat-card.terlambat { 
        border-left-color: #dc2626; 
    }
    .stat-card.peringatan { 
        border-left-color: #f59e0b; 
    }
    
    .stat-card.total .stat-icon { 
        background: rgba(79, 70, 229, 0.1); 
        color: var(--primary); 
    }
    .stat-card.selesai .stat-icon { 
        background: rgba(16, 185, 129, 0.1); 
        color: var(--success); 
    }
    .stat-card.dalam_proses .stat-icon { 
        background: rgba(245, 158, 11, 0.1); 
        color: var(--warning); 
    }
    .stat-card.belum .stat-icon { 
        background: rgba(239, 68, 68, 0.1); 
        color: var(--danger); 
    }
    .stat-card.terlambat .stat-icon { 
        background: rgba(220, 38, 38, 0.1); 
        color: #dc2626; 
    }
    .stat-card.peringatan .stat-icon { 
        background: rgba(245, 158, 11, 0.1); 
        color: #f59e0b; 
    }
    
    /* Alert Peringatan */
    .notification-alert {
        background: linear-gradient(135deg, #fef2f2, #fee2e2);
        border: 1px solid #fecaca;
        border-left: 4px solid #dc2626;
        border-radius: 8px;
        padding: 0.8rem 1rem;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: #7f1d1d;
        font-weight: 500;
        font-size: 0.75rem;
        box-shadow: 0 2px 4px rgba(220, 38, 38, 0.1);
    }
    
    .peringatan-alert {
        background: linear-gradient(135deg, #fffbeb, #fef3c7);
        border: 1px solid #fde68a;
        border-left: 4px solid #f59e0b;
        border-radius: 8px;
        padding: 0.8rem 1rem;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: #92400e;
        font-weight: 500;
        font-size: 0.75rem;
        box-shadow: 0 2px 4px rgba(245, 158, 11, 0.1);
    }
    
    .notification-alert i {
        color: #dc2626;
        font-size: 1rem;
    }
    
    .peringatan-alert i {
        color: #f59e0b;
        font-size: 1rem;
    }
    
    .notification-badge {
        background: #dc2626;
        color: white;
        font-size: 0.65rem;
        padding: 0.2rem 0.6rem;
        border-radius: 12px;
        font-weight: 600;
        margin-left: 0.5rem;
    }
    
    .peringatan-badge {
        background: #f59e0b;
        color: white;
        font-size: 0.65rem;
        padding: 0.2rem 0.6rem;
        border-radius: 12px;
        font-weight: 600;
        margin-left: 0.5rem;
    }
    
    /* Card Section */
    .card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        padding: 1.25rem;
        margin-bottom: 1.5rem;
    }
    
    /* Button Styles - Konsisten */
    .btn {
        padding: 0.3rem 0.6rem;
        font-weight: 500;
        border-radius: 6px;
        transition: all 0.2s ease;
        font-size: 0.65rem;
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
    
    .btn-warning {
        background: var(--warning);
        color: white;
    }
    
    .btn-success {
        background: var(--success);
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
    
    /* Alert Styles */
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
    
    /* Table Styles */
    .table-container {
        border: 1px solid var(--border);
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 1px 6px rgba(0,0,0,0.08);
    }
    
    .table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.75rem;
        margin: 0;
    }
    
    .table th {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: white;
        font-weight: 600;
        padding: 0.6rem 0.8rem;
        text-align: left;
        border: none;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }
    
    .table td {
        padding: 0.6rem 0.8rem;
        text-align: left;
        border-bottom: 1px solid var(--border);
        vertical-align: middle;
        color: var(--dark);
        font-size: 0.75rem;
    }
    
    .table tr:hover td {
        background-color: #f8fafc;
    }
    
    .table tr:nth-child(even) {
        background: #fafbff;
    }
    
    /* Row styles untuk pesanan dengan notifikasi */
    .row-terlambat {
        background: linear-gradient(to right, #fef2f2, #fff7ed) !important;
        border-left: 3px solid #dc2626;
        position: relative;
    }
    
    .row-peringatan {
        background: linear-gradient(to right, #fffbeb, #fef3c7) !important;
        border-left: 3px solid #f59e0b;
        position: relative;
    }
    
    .row-terlambat:hover td {
        background: linear-gradient(to right, #fee2e2, #ffedd5) !important;
    }
    
    .row-peringatan:hover td {
        background: linear-gradient(to right, #fef3c7, #fde68a) !important;
    }
    
    .row-terlambat td:first-child,
    .row-peringatan td:first-child {
        position: relative;
    }
    
    .row-terlambat td:first-child::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 3px;
        background: #dc2626;
        border-radius: 3px 0 0 3px;
    }
    
    .row-peringatan td:first-child::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 3px;
        background: #f59e0b;
        border-radius: 3px 0 0 3px;
    }
    
    .action-buttons {
        display: flex;
        gap: 0.2rem;
        justify-content: center;
    }

    /* Styles untuk kolom Jenis Pakaian - PERBAIKAN: Menampilkan 1 jenis saja */
    .jenis-pakaian-simple {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        min-width: 140px;
        max-width: 200px;
    }

    .jenis-pakaian-text {
        font-weight: 600;
        color: #1f2937;
        font-size: 0.75rem;
        line-height: 1.3;
        flex: 1;
    }

    /* PERBAIKAN: Tombol panah atas kanan dengan lingkaran biru */
    .arrow-icon-btn {
        background: var(--info); /* Warna biru untuk lingkaran */
        border: none;
        color: white; /* Warna putih untuk panah */
        padding: 0;
        border-radius: 50%; /* Membuat lingkaran */
        cursor: pointer;
        font-size: 0.6rem;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 22px; /* Ukuran sedikit lebih kecil */
        height: 22px; /* Ukuran sedikit lebih kecil */
        box-shadow: 0 2px 4px rgba(59, 130, 246, 0.2);
    }

    .arrow-icon-btn:hover {
        background: var(--primary-dark); /* Warna biru lebih gelap saat hover */
        transform: scale(1.1);
        box-shadow: 0 4px 8px rgba(59, 130, 246, 0.3);
    }

    .arrow-icon-btn i {
        font-size: 0.7rem;
        transform: rotate(45deg); /* Rotasi untuk membuat panah atas kanan */
    }

    .arrow-icon-btn:active {
        transform: scale(0.95);
    }

    /* Quick View Modal */
    .quick-view-modal .modal-content {
        border: none;
        border-radius: 12px;
        box-shadow: 0 20px 40px rgba(0,0,0,0.15);
    }

    .quick-view-modal .modal-header {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: white;
        border-radius: 12px 12px 0 0;
        padding: 1rem 1.2rem;
        border-bottom: none;
    }

    .quick-view-modal .modal-title {
        font-size: 0.9rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .quick-view-modal .modal-body {
        padding: 1.2rem;
        font-size: 0.75rem;
    }

    /* PERBAIKAN: Hapus bagian informasi pelanggan dari modal */
    .quick-view-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .quick-view-card {
        background: #f8fafc;
        padding: 1rem;
        border-radius: 8px;
        border-left: 3px solid var(--info);
    }

    .quick-view-card h6 {
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--dark);
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .quick-view-card p {
        margin: 0.2rem 0;
        color: var(--gray);
        font-size: 0.7rem;
    }

    .quick-view-items {
        margin-top: 1rem;
    }

    .quick-view-items h6 {
        border-bottom: 1px solid #e5e7eb;
        padding-bottom: 0.5rem;
        margin-bottom: 0.8rem;
    }

    .quick-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.4rem 0;
        border-bottom: 1px solid #f3f4f6;
        font-size: 0.7rem;
    }

    .quick-item:last-child {
        border-bottom: none;
    }

    .quick-item-name {
        font-weight: 500;
        color: var(--dark);
        flex: 1;
    }

    .quick-item-details {
        display: flex;
        gap: 0.8rem;
        color: var(--gray);
    }

    .quick-item-price {
        color: var(--success);
        font-weight: 600;
        min-width: 120px;
        text-align: right;
    }

    /* PERBAIKAN: Item badge untuk nomor urut */
    .item-badge {
        background: var(--info);
        color: white;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.6rem;
        font-weight: 600;
        margin-right: 0.5rem;
    }

    /* Styles untuk kolom Ukuran */
    .ukuran-container {
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
        min-width: 100px;
    }

    .ukuran-item {
        display: flex;
        align-items: center;
        gap: 0.3rem;
        font-size: 0.7rem;
    }

    .ukuran-badge {
        padding: 0.1rem 0.3rem;
        border-radius: 6px;
        font-size: 0.55rem;
        font-weight: 600;
        color: white;
        min-width: 50px;
        text-align: center;
    }

    .ukuran-count {
        font-size: 0.65rem;
        color: var(--gray);
        font-weight: 500;
    }

    /* Progress bar untuk status */
    .progress-container {
        width: 100%;
        height: 4px;
        background: #e5e7eb;
        border-radius: 2px;
        margin-top: 0.3rem;
        overflow: hidden;
    }

    .progress-bar {
        height: 100%;
        border-radius: 2px;
        transition: width 0.3s ease;
    }

    .progress-belum {
        width: 10%;
        background: #f59e0b;
    }

    .progress-dalam_proses {
        width: 60%;
        background: #3b82f6;
    }

    .progress-selesai {
        width: 100%;
        background: #10b981;
    }

    /* Modal styles untuk detail pembayaran */
    .modal-content {
        border: none;
        border-radius: 12px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    }

    .modal-header {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: white;
        border-radius: 12px 12px 0 0;
        padding: 1.2rem;
        border: none;
    }

    .modal-title {
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 1rem;
    }

    .id-badge {
        background: rgba(255,255,255,0.2);
        padding: 0.2rem 0.6rem;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 500;
        margin-left: 0.5rem;
    }

    .modal-body {
        padding: 1.5rem;
    }

    .info-section {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .info-item {
        background: #f8fafc;
        padding: 1rem;
        border-radius: 8px;
        border-left: 3px solid var(--primary);
    }

    .info-label {
        font-weight: 600;
        color: var(--dark);
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.8rem;
    }

    .info-value {
        font-size: 0.75rem;
        line-height: 1.5;
        color: var(--gray);
    }

    .info-value .highlight {
        color: var(--primary);
        font-weight: 600;
    }

    .payment-info {
        background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
        padding: 1.2rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        border: 1px solid #bae6fd;
    }

    .payment-info h6 {
        color: #0c4a6e;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .payment-item {
        display: flex;
        justify-content: between;
        align-items: center;
        padding: 0.5rem 0;
        border-bottom: 1px solid #e0f2fe;
    }

    .payment-item:last-child {
        border-bottom: none;
    }

    .payment-label {
        font-weight: 500;
        color: #0c4a6e;
        font-size: 0.75rem;
        flex: 1;
    }

    .payment-value {
        font-weight: 600;
        font-size: 0.8rem;
    }

    .payment-value.total {
        color: var(--success);
    }

    .payment-value.paid {
        color: var(--info);
    }

    .payment-value.remaining {
        color: var(--warning);
    }
    
    /* Search & Filter */
    .search-box {
        margin-bottom: 1rem;
    }
    
    .search-box input, .search-box select {
        padding: 0.5rem 0.8rem;
        border: 1px solid var(--border);
        border-radius: 6px;
        font-size: 0.7rem;
        transition: all 0.3s ease;
        background: white;
    }
    
    .search-box input:focus, .search-box select:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1);
    }
    
    .empty-state {
        text-align: center;
        padding: 2rem 1.5rem;
        color: var(--gray);
        background: white;
        border-radius: 10px;
        box-shadow: 0 1px 8px rgba(0,0,0,0.08);
        margin: 1.5rem 0;
        font-size: 0.8rem;
    }
    
    .empty-state i {
        font-size: 2rem;
        margin-bottom: 0.75rem;
        color: #d1d5db;
        opacity: 0.5;
    }
    
    .header-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        padding: 1rem 1.25rem;
        background: white;
        border-radius: 10px;
        box-shadow: 0 1px 8px rgba(0,0,0,0.08);
        border-left: 3px solid var(--primary);
    }
    
    .header-actions h3 {
        color: var(--dark);
        margin: 0;
        font-size: 1rem;
        font-weight: 700;
    }
    
    .no-urut {
        text-align: center;
        font-weight: bold;
        color: var(--primary);
        width: 40px;
        font-size: 0.75rem;
    }
    
    .filter-section {
        background: #f8fafc;
        padding: 1rem;
        border-radius: 10px;
        margin-bottom: 1.5rem;
        border: 1px solid var(--border);
    }
    
    .filter-form {
        display: flex;
        gap: 1rem;
        align-items: end;
        flex-wrap: wrap;
    }
    
    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 0.4rem;
        flex: 1;
        min-width: 180px;
    }
    
    .filter-group label {
        font-weight: 600;
        color: var(--dark);
        font-size: 0.7rem;
    }
    
    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        margin-top: 1.5rem;
        gap: 0.4rem;
        flex-wrap: wrap;
    }
    
    .pagination-info {
        margin: 0 1rem;
        color: var(--gray);
        font-size: 0.7rem;
        font-weight: 500;
    }
    
    .page-item {
        display: inline-block;
    }
    
    .page-link {
        padding: 0.4rem 0.7rem;
        border: 1px solid var(--border);
        background: white;
        color: var(--primary);
        text-decoration: none;
        border-radius: 6px;
        transition: all 0.3s ease;
        font-weight: 500;
        min-width: 35px;
        text-align: center;
        font-size: 0.7rem;
    }
    
    .page-link:hover {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
    }
    
    .page-item.active .page-link {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
    }
    
    .page-item.disabled .page-link {
        color: #9ca3af;
        pointer-events: none;
        background: #f9fafb;
    }
    
    /* Search info */
    .search-info {
        background: #e0e7ff;
        padding: 0.8rem 1rem;
        border-radius: 6px;
        margin-bottom: 1rem;
        border-left: 3px solid var(--primary);
        font-size: 0.7rem;
        color: var(--dark);
        font-weight: 500;
    }
    
    .search-info strong {
        color: var(--primary);
    }

    /* Status Styles - PERBAIKAN: Form yang lebih baik */
    .status-container {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .status-form {
        margin: 0;
        display: inline;
    }

    .status-select {
        border: 1px solid var(--border);
        border-radius: 4px;
        padding: 0.3rem 0.5rem;
        font-size: 0.65rem;
        background: white;
        cursor: pointer;
        transition: all 0.2s;
        min-width: 80px;
    }

    .status-select:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1);
    }

    .status-badge {
        padding: 0.25rem 0.5rem;
        border-radius: 12px;
        font-size: 0.65rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        min-width: 70px;
        text-align: center;
    }

    .status-badge.belum {
        background: #fef3c7;
        color: #d97706;
    }

    .status-badge.dalam_proses {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .status-badge.selesai {
        background: #dcfce7;
        color: #166534;
    }

    /* Loading state untuk status update */
    .status-loading {
        opacity: 0.6;
        pointer-events: none;
    }

    .status-updating {
        animation: pulse 1.5s infinite;
    }

    @keyframes pulse {
        0% { opacity: 1; }
        50% { opacity: 0.5; }
        100% { opacity: 1; }
    }

    /* Amount Column */
    .amount-column {
        display: flex;
        align-items: center;
        gap: 0.3rem;
    }

    .amount-value {
        font-weight: 600;
        color: var(--success);
        font-size: 0.7rem;
    }

    /* Styles untuk tanggal selesai dengan notifikasi */
    .tgl-selesai-container {
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
        position: relative;
    }

    .tgl-selesai-value {
        font-weight: 500;
        color: var(--dark);
        font-size: 0.7rem;
    }

    /* Notifikasi Terlambat (Merah) */
    .terlambat-warning {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        background: #fef2f2;
        color: #dc2626;
        padding: 0.2rem 0.4rem;
        border-radius: 4px;
        font-size: 0.6rem;
        font-weight: 600;
        margin-top: 0.1rem;
        animation: pulse-warning 2s infinite;
        border: 1px solid #fecaca;
    }

    /* Notifikasi Peringatan 3 Hari (Kuning/Orange) */
    .peringatan-warning {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        background: #fffbeb;
        color: #d97706;
        padding: 0.2rem 0.4rem;
        border-radius: 4px;
        font-size: 0.6rem;
        font-weight: 600;
        margin-top: 0.1rem;
        animation: pulse-peringatan 2s infinite;
        border: 1px solid #fde68a;
    }

    .terlambat-warning i,
    .peringatan-warning i {
        font-size: 0.6rem;
    }

    @keyframes pulse-warning {
        0% { opacity: 1; }
        50% { opacity: 0.7; }
        100% { opacity: 1; }
    }

    @keyframes pulse-peringatan {
        0% { opacity: 1; }
        50% { opacity: 0.8; }
        100% { opacity: 1; }
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .container {
            padding: 0.8rem;
        }
        
        .stats-container {
            grid-template-columns: repeat(2, 1fr);
            gap: 0.6rem;
        }
        
        .stat-card {
            padding: 0.8rem;
        }
        
        .stat-number {
            font-size: 1.1rem;
        }
        
        .header-actions {
            flex-direction: column;
            gap: 0.8rem;
            align-items: flex-start;
        }
        
        .filter-form {
            flex-direction: column;
            align-items: stretch;
            gap: 0.8rem;
        }
        
        .filter-group {
            min-width: auto;
        }
        
        .table-responsive {
            font-size: 0.7rem;
        }
        
        .table th,
        .table td {
            padding: 0.5rem 0.6rem;
        }
        
        .jenis-pakaian-simple {
            min-width: 120px;
            max-width: 160px;
        }
        
        .status-container {
            flex-direction: column;
            gap: 0.3rem;
        }
        
        .tgl-selesai-container {
            min-width: 100px;
        }
        
        .action-buttons {
            flex-direction: column;
            gap: 0.2rem;
        }
    }

    @media (max-width: 480px) {
        .stats-container {
            grid-template-columns: 1fr;
        }
        
        .header-actions h3 {
            font-size: 0.9rem;
        }
        
        .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.6rem;
        }
        
        .jenis-pakaian-simple {
            min-width: 100px;
            max-width: 140px;
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
    
    /* Quick View Items */
    .quick-items-list {
        max-height: 200px;
        overflow-y: auto;
        padding-right: 5px;
    }
    
    .quick-items-list::-webkit-scrollbar {
        width: 4px;
    }
    
    .quick-items-list::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 2px;
    }
    
    .quick-items-list::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 2px;
    }
</style>
</head>

<body>
    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/header.php'; ?>

    <div class="main-content">
        <div class="content-body">
            <div class="container">
                <h2 class="my-3">Data Pesanan</h2>

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

                <!-- Stats Cards -->
                <div class="stats-container">                   
                    <div class="stat-card selesai">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-number"><?= $total_selesai; ?></div>
                        <div class="stat-label">Pesanan Selesai</div>
                    </div>
                    
                    <div class="stat-card dalam_proses">
                        <div class="stat-icon">
                            <i class="fas fa-spinner"></i>
                        </div>
                        <div class="stat-number"><?= $total_dalam_proses; ?></div>
                        <div class="stat-label">Dalam Proses</div>
                    </div>
                    
                    <div class="stat-card belum">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-number"><?= $total_belum; ?></div>
                        <div class="stat-label">Belum Diproses</div>
                    </div>
                    
                    <!-- Statistik Pesanan Terlambat -->
                    <?php if ($total_terlambat > 0): ?>
                    <div class="stat-card terlambat">
                        <div class="stat-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stat-number"><?= $total_terlambat; ?></div>
                        <div class="stat-label">Pesanan Terlambat</div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Statistik Peringatan 3 Hari -->
                    <?php if ($total_peringatan > 0): ?>
                    <div class="stat-card peringatan">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-number"><?= $total_peringatan; ?></div>
                        <div class="stat-label">Hampir Jatuh Tempo</div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Alert Peringatan Pesanan Terlambat -->
                <?php if ($total_terlambat > 0): ?>
                    <div class="notification-alert">
                        <i class="fas fa-exclamation-triangle"></i>
                        Terdapat <span class="notification-badge"><?= $total_terlambat; ?> pesanan</span> yang telah melewati tanggal selesai
                        <button type="button" class="btn btn-sm btn-outline-danger ms-2" style="font-size: 0.6rem; padding: 0.2rem 0.5rem;" 
                                onclick="filterNotifikasi('terlambat')">
                            <i class="fas fa-filter"></i> Tampilkan Terlambat
                        </button>
                    </div>
                <?php endif; ?>

                <!-- Alert Peringatan 3 Hari Sebelum Jatuh Tempo -->
                <?php if ($total_peringatan > 0): ?>
                    <div class="peringatan-alert">
                        <i class="fas fa-clock"></i>
                        Terdapat <span class="peringatan-badge"><?= $total_peringatan; ?> pesanan</span> yang akan jatuh tempo dalam 3 hari
                        <button type="button" class="btn btn-sm btn-outline-warning ms-2" style="font-size: 0.6rem; padding: 0.2rem 0.5rem;" 
                                onclick="filterNotifikasi('peringatan')">
                            <i class="fas fa-filter"></i> Tampilkan Peringatan
                        </button>
                    </div>
                <?php endif; ?>

                <!-- Filter Section -->
                <div class="filter-section">
                    <form method="GET" class="filter-form">
                        <div class="filter-group">
                            <label for="searchInput">Cari Pesanan:</label>
                            <input type="text" id="searchInput" name="search" placeholder="Cari berdasarkan pelanggan, jenis pakaian, atau catatan..." 
                                   value="<?= htmlspecialchars($search); ?>" 
                                   class="form-control">
                        </div>
                        <div class="filter-group">
                            <label for="tanggal">Filter Tanggal Pesanan:</label>
                            <input type="date" id="tanggal" name="tanggal" 
                                   value="<?= $filter_tanggal; ?>" 
                                   class="form-control">
                        </div>
                        <div class="filter-group">
                            <label for="status">Filter Status:</label>
                            <select id="status" name="status" class="form-control">
                                <option value="">Semua Status</option>
                                <option value="belum" <?= $filter_status == 'belum' ? 'selected' : ''; ?>>Belum Diproses</option>
                                <option value="dalam_proses" <?= $filter_status == 'dalam_proses' ? 'selected' : ''; ?>>Dalam Proses</option>
                                <option value="selesai" <?= $filter_status == 'selesai' ? 'selected' : ''; ?>>Selesai</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>&nbsp;</label>
                            <div style="display: flex; gap: 0.4rem;">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Terapkan Filter
                                </button>
                                <?php if (!empty($search) || !empty($filter_tanggal) || !empty($filter_status)): ?>
                                    <a href="pesanan.php" class="btn btn-secondary">
                                        <i class="fas fa-refresh"></i> Reset Filter
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Search Info -->
                <?php if (!empty($search) || !empty($filter_tanggal) || !empty($filter_status)): ?>
                    <div class="search-info">
                        <i class="fas fa-info-circle"></i> Menampilkan hasil dengan filter:
                        <?php if (!empty($search)): ?>
                            <strong>"<?= htmlspecialchars($search); ?>"</strong>
                        <?php endif; ?>
                        <?php if (!empty($filter_tanggal)): ?>
                            <?php if (!empty($search)): ?>, <?php endif; ?>
                            tanggal: <strong><?= date('d/m/Y', strtotime($filter_tanggal)); ?></strong>
                        <?php endif; ?>
                        <?php if (!empty($filter_status)): ?>
                            <?php if (!empty($search) || !empty($filter_tanggal)): ?>, <?php endif; ?>
                            status: <strong>
                                <?= $filter_status == 'belum' ? 'Belum Diproses' : 
                                    ($filter_status == 'dalam_proses' ? 'Dalam Proses' : 'Selesai'); ?>
                            </strong>
                        <?php endif; ?>
                        - Ditemukan <strong><?= $total_pesanan; ?></strong> pesanan
                    </div>
                <?php endif; ?>

                <!-- Header Actions -->
                <div class="header-actions">
                    <h3>Daftar Pesanan</h3>
                    <div style="display: flex; gap: 0.8rem; align-items: center;">
                        <a href="tambahpesanan.php" class="btn btn-success">
                            <i class="fas fa-plus"></i> Tambah Pesanan Baru
                        </a>
                    </div>
                </div>

                <?php if (empty($pesanan)): ?>
                    <div class="empty-state">
                        <div><i class="fas fa-inbox"></i></div>
                        <p style="font-size: 0.9rem; margin-bottom: 0.4rem;">Belum ada data pesanan</p>
                        <?php if (!empty($search) || !empty($filter_tanggal) || !empty($filter_status)): ?>
                            <p style="color: #6b7280; font-size: 0.75rem; margin-top: 0.4rem;">
                                <?php if (!empty($search)): ?>
                                    Pencarian: "<?= htmlspecialchars($search); ?>"
                                <?php endif; ?>
                                <?php if (!empty($filter_tanggal)): ?>
                                    <?php if (!empty($search)): ?> dan <?php endif; ?>
                                    Filter tanggal: <?= date('d/m/Y', strtotime($filter_tanggal)); ?>
                                <?php endif; ?>
                                <?php if (!empty($filter_status)): ?>
                                    <?php if (!empty($search) || !empty($filter_tanggal)): ?> dan <?php endif; ?>
                                    Filter status: <?= $filter_status == 'belum' ? 'Belum Diproses' : 
                                        ($filter_status == 'dalam_proses' ? 'Dalam Proses' : 'Selesai'); ?>
                                <?php endif; ?>
                            </p>
                            <a href="pesanan.php" class="btn btn-primary" style="margin-top: 1.2rem;">
                                <i class="fas fa-list"></i> Tampilkan Semua Data
                            </a>
                        <?php else: ?>
                            <a href="tambahpesanan.php" class="btn btn-success" style="margin-top: 1.2rem;">
                                <i class="fas fa-plus"></i> Tambah Pesanan Pertama
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="table-responsive">
                            <table class="table" id="pesananTable">
                                <thead>
                                    <tr>
                                        <th class="no-urut">No</th>
                                        <th>Pelanggan</th>
                                        <th>Karyawan</th>
                                        <th>Jenis Pakaian</th>
                                        <th>Ukuran</th>
                                        <th>Tanggal Pesan</th>
                                        <th>Tanggal Selesai</th>
                                        <th>Status</th>
                                        <th>Total</th>
                                        <th style="text-align: center;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $nomor_urut = ($page - 1) * $limit + 1;
                                    $today = date('Y-m-d');
                                    
                                    foreach ($pesanan as $row): 
                                        // Status text mapping
                                        $status_text = '';
                                        $status_class = '';
                                        $progress_class = '';
                                        switch($row['status_pesanan']) {
                                            case 'belum':
                                                $status_text = 'Belum Diproses';
                                                $status_class = 'belum';
                                                $progress_class = 'progress-belum';
                                                break;
                                            case 'dalam_proses':
                                                $status_text = 'Dalam Proses';
                                                $status_class = 'dalam_proses';
                                                $progress_class = 'progress-dalam_proses';
                                                break;
                                            case 'selesai':
                                                $status_text = 'Selesai';
                                                $status_class = 'selesai';
                                                $progress_class = 'progress-selesai';
                                                break;
                                            default:
                                                $status_text = $row['status_pesanan'];
                                                $status_class = 'belum';
                                                $progress_class = 'progress-belum';
                                        }
                                        
                                        // Cek status notifikasi tanggal selesai
                                        $notifikasi_class = '';
                                        $notifikasi_text = '';
                                        $notifikasi_icon = '';
                                        $row_class = '';
                                        
                                        if (!empty($row['tgl_selesai']) && $row['status_pesanan'] != 'selesai') {
                                            $tgl_selesai = $row['tgl_selesai'];
                                            $selisih_hari = floor((strtotime($tgl_selesai) - strtotime($today)) / (60 * 60 * 24));
                                            
                                            if ($selisih_hari < 0) {
                                                // Sudah melewati tanggal selesai
                                                $notifikasi_class = 'terlambat-warning';
                                                $notifikasi_text = 'Terlambat';
                                                $notifikasi_icon = 'exclamation-triangle';
                                                $row_class = 'row-terlambat';
                                            } elseif ($selisih_hari <= 3 && $selisih_hari >= 0) {
                                                // 3 hari atau kurang sebelum tanggal selesai
                                                $notifikasi_class = 'peringatan-warning';
                                                $notifikasi_text = $selisih_hari == 0 ? 'Hari ini' : ($selisih_hari == 1 ? '1 hari lagi' : $selisih_hari . ' hari lagi');
                                                $notifikasi_icon = 'clock';
                                                $row_class = 'row-peringatan';
                                            }
                                        }
                                        
                                        // Query untuk mendapatkan jumlah kuantitas dan jenis ukuran
                                        try {
                                            // Hitung total kuantitas atasan dan bawahan
                                            $sql_atasan_count = "SELECT COUNT(*) as total FROM ukuran_atasan WHERE id_pesanan = ?";
                                            $atasan_count = getSingle($sql_atasan_count, [$row['id_pesanan']]);
                                            $total_atasan = $atasan_count ? $atasan_count['total'] : 0;
                                            
                                            $sql_bawahan_count = "SELECT COUNT(*) as total FROM ukuran_bawahan WHERE id_pesanan = ?";
                                            $bawahan_count = getSingle($sql_bawahan_count, [$row['id_pesanan']]);
                                            $total_bawahan = $bawahan_count ? $bawahan_count['total'] : 0;
                                            
                                            $total_kuantitas = $total_atasan + $total_bawahan;
                                            
                                            // Tentukan jenis ukuran
                                            if ($total_atasan > 0 && $total_bawahan > 0) {
                                                $jenis_ukuran = 'keduanya';
                                                $ukuran_text = "Atasan ($total_atasan) & Bawahan ($total_bawahan)";
                                            } elseif ($total_atasan > 0) {
                                                $jenis_ukuran = 'atasan';
                                                $ukuran_text = "Atasan ($total_atasan)";
                                            } elseif ($total_bawahan > 0) {
                                                $jenis_ukuran = 'bawahan';
                                                $ukuran_text = "Bawahan ($total_bawahan)";
                                            } else {
                                                $jenis_ukuran = 'tidak_ada';
                                                $ukuran_text = 'Tidak ada data ukuran';
                                            }
                                        } catch (PDOException $e) {
                                            $jenis_ukuran = 'error';
                                            $ukuran_text = 'Error memuat data';
                                            $total_atasan = 0;
                                            $total_bawahan = 0;
                                            $total_kuantitas = 0;
                                        }
                                        
                                        // Ambil items pesanan
                                        $items = $row['items'] ?? [];
                                        $total_items = $row['total_items'] ?? 1;
                                        $total_kuantitas_items = $row['total_kuantitas'] ?? 1;
                                        $item_names = $row['item_names'] ?? ['Pakaian'];
                                    ?>
                                    <tr class="<?= $row_class; ?>">
                                        <td class="no-urut"><?= $nomor_urut++; ?></td>
                                        <td style="font-weight: 600; color: #1f2937;"><?= htmlspecialchars($row['nama_pelanggan'] ?? '-'); ?></td>
                                        <td><?= htmlspecialchars($row['nama_karyawan'] ?? '-'); ?></td>
                                        <td>
                                            <!-- PERBAIKAN: Tampilkan hanya 1 jenis pakaian utama di tabel -->
                                            <div class="jenis-pakaian-simple">
                                                <div class="jenis-pakaian-text" title="<?= htmlspecialchars($row['jenis_pakaian_tampil']); ?>">
                                                    <?= htmlspecialchars($row['jenis_pakaian_tampil']); ?>
                                                </div>
                                                <!-- PERBAIKAN: Tombol panah atas kanan dengan lingkaran biru -->
                                                <button type="button" 
                                                        class="arrow-icon-btn" 
                                                        title="Lihat Semua Jenis Pakaian"
                                                        onclick="showQuickView(<?= $row['id_pesanan']; ?>, <?= $total_items; ?>)">
                                                    <i class="fas fa-arrow-up"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="ukuran-container">
                                                <?php if ($total_atasan > 0): ?>
                                                <div class="ukuran-item">
                                                    <span class="ukuran-badge" style="background: var(--info);">Atasan</span>
                                                    <span class="ukuran-count"><?= $total_atasan ?> set</span>
                                                </div>
                                                <?php endif; ?>
                                                <?php if ($total_bawahan > 0): ?>
                                                <div class="ukuran-item">
                                                    <span class="ukuran-badge" style="background: #8b5cf6;">Bawahan</span>
                                                    <span class="ukuran-count"><?= $total_bawahan ?> set</span>
                                                </div>
                                                <?php endif; ?>
                                                <?php if ($total_atasan == 0 && $total_bawahan == 0): ?>
                                                <span style="color: var(--gray); font-size: 0.7rem; font-style: italic;">Tidak ada data</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td><?= date('d/m/Y', strtotime($row['tgl_pesanan'])); ?></td>
                                        <td>
                                            <div class="tgl-selesai-container">
                                                <div class="tgl-selesai-value" style="color: <?= !empty($notifikasi_class) && $notifikasi_class == 'terlambat-warning' ? '#dc2626' : ($notifikasi_class == 'peringatan-warning' ? '#d97706' : 'inherit'); ?>; font-weight: <?= !empty($notifikasi_class) ? '600' : '500'; ?>;">
                                                    <?= !empty($row['tgl_selesai']) ? date('d/m/Y', strtotime($row['tgl_selesai'])) : '-'; ?>
                                                </div>
                                                <?php if (!empty($notifikasi_class)): ?>
                                                    <div class="<?= $notifikasi_class; ?>">
                                                        <i class="fas fa-<?= $notifikasi_icon; ?>"></i>
                                                        <?= $notifikasi_text; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="status-container">
                                                <!-- PERBAIKAN: Form yang lebih baik untuk update status -->
                                                <form method="POST" class="status-form" id="statusForm-<?= $row['id_pesanan']; ?>">
                                                    <input type="hidden" name="id_pesanan" value="<?= $row['id_pesanan']; ?>">
                                                    <input type="hidden" name="update_status" value="1">
                                                    <select name="status_pesanan" class="status-select" 
                                                            onchange="updateStatus('<?= $row['id_pesanan']; ?>')"
                                                            id="statusSelect-<?= $row['id_pesanan']; ?>">
                                                        <option value="belum" <?= $row['status_pesanan'] == 'belum' ? 'selected' : ''; ?>>Belum</option>
                                                        <option value="dalam_proses" <?= $row['status_pesanan'] == 'dalam_proses' ? 'selected' : ''; ?>>Dalam Proses</option>
                                                        <option value="selesai" <?= $row['status_pesanan'] == 'selesai' ? 'selected' : ''; ?>>Selesai</option>
                                                    </select>
                                                </form>
                                                <span class="status-badge <?= $status_class; ?>" id="statusBadge-<?= $row['id_pesanan']; ?>">
                                                    <?= $status_text; ?>
                                                </span>
                                            </div>
                                            <div class="progress-container">
                                                <div class="progress-bar <?= $progress_class; ?>" id="progressBar-<?= $row['id_pesanan']; ?>"></div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="amount-column">
                                                <span class="amount-value">Rp <?= number_format($row['total_harga'], 0, ',', '.'); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <!-- Tombol detail pesanan -->
                                                <a href="detail_pesanan.php?id=<?= $row['id_pesanan']; ?>" 
                                                   class="btn btn-info btn-sm" 
                                                   title="Lihat Detail Lengkap">
                                                   <i class="fas fa-eye"></i>
                                                </a>
                                                
                                                <a href="editpesanan.php?edit=<?= $row['id_pesanan']; ?>" 
                                                   class="btn btn-warning btn-sm" 
                                                   title="Edit Pesanan">
                                                   <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" 
                                                        class="btn btn-danger btn-sm delete-btn" 
                                                        title="Hapus Pesanan"
                                                        data-id="<?= $row['id_pesanan']; ?>"
                                                        data-pelanggan="<?= htmlspecialchars($row['nama_pelanggan'] ?? '-'); ?>">
                                                   <i class="fas fa-trash"></i> 
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <!-- Previous Page -->
                            <?php if ($page > 1): ?>
                                <div class="page-item">
                                    <a class="page-link" href="?page=<?= $page - 1; ?><?= !empty($search) ? '&search=' . urlencode($search) : ''; ?><?= !empty($filter_tanggal) ? '&tanggal=' . $filter_tanggal : ''; ?><?= !empty($filter_status) ? '&status=' . $filter_status : ''; ?>">
                                        <i class="fas fa-chevron-left"></i> Sebelumnya
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="page-item disabled">
                                    <span class="page-link"><i class="fas fa-chevron-left"></i> Sebelumnya</span>
                                </div>
                            <?php endif; ?>

                            <!-- Page Numbers -->
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            for ($i = $start_page; $i <= $end_page; $i++): 
                            ?>
                                <div class="page-item <?= $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?= $i; ?><?= !empty($search) ? '&search=' . urlencode($search) : ''; ?><?= !empty($filter_tanggal) ? '&tanggal=' . $filter_tanggal : ''; ?><?= !empty($filter_status) ? '&status=' . $filter_status : ''; ?>"><?= $i; ?></a>
                                </div>
                            <?php endfor; ?>

                            <!-- Next Page -->
                            <?php if ($page < $total_pages): ?>
                                <div class="page-item">
                                    <a class="page-link" href="?page=<?= $page + 1; ?><?= !empty($search) ? '&search=' . urlencode($search) : ''; ?><?= !empty($filter_tanggal) ? '&tanggal=' . $filter_tanggal : ''; ?><?= !empty($filter_status) ? '&status=' . $filter_status : ''; ?>">
                                        Berikutnya <i class="fas fa-chevron-right"></i>
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="page-item disabled">
                                    <span class="page-link">Berikutnya <i class="fas fa-chevron-right"></i></span>
                                </div>
                            <?php endif; ?>

                            <!-- Page Info -->
                            <div class="pagination-info">
                                Halaman <?= $page; ?> dari <?= $total_pages; ?> 
                                (Total: <?= $total_pesanan; ?> pesanan)
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Footer -->
        <?php include '../includes/footer.php'; ?>
    </div>

    <!-- Quick View Modal -->
    <div class="modal fade quick-view-modal" id="quickViewModal" tabindex="-1" aria-labelledby="quickViewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="quickViewModalLabel">
                        <i class="fas fa-box"></i> Jenis Pakaian
                        <span id="quickViewId" class="id-badge"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- PERBAIKAN: Hapus bagian informasi pelanggan -->
                    <div class="quick-view-grid">
                        <div class="quick-view-card">
                            <h6><i class="fas fa-list"></i> Ringkasan Items</h6>
                            <p id="quickViewSummary">Memuat...</p>
                        </div>
                    </div>
                    
                    <div class="quick-view-items">
                        <h6><i class="fas fa-box-open"></i> Daftar Items</h6>
                        <div class="quick-items-list" id="quickViewItems">
                            <!-- Items akan dimuat di sini via JavaScript -->
                            <div class="text-center p-3">
                                <div class="spinner-border spinner-border-sm text-primary" role="status">
                                    <span class="visually-hidden">Memuat...</span>
                                </div>
                                <p class="mt-2 text-muted">Memuat data items...</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="#" id="quickViewDetailLink" class="btn btn-primary" target="_blank">
                        <i class="fas fa-external-link-alt"></i> Lihat Detail Lengkap
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade delete-modal" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Konfirmasi Penghapusan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="warning-icon">⚠️</div>
                    <h5 class="text-danger mb-2" style="font-size: 1.1rem;">Konfirmasi Penghapusan</h5>
                    <p>Apakah Anda yakin ingin menghapus pesanan untuk pelanggan <strong id="customerName"></strong>?</p>
                    <p class="text-muted small">Tindakan ini tidak dapat dibatalkan dan data akan hilang permanen.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">
                        <i class="fas fa-trash"></i> Ya, Hapus
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Variabel global untuk menyimpan data hapus
        let currentDeleteId = null;
        let currentDeleteName = null;
        let quickViewModal = null;

        // Inisialisasi modal quick view
        document.addEventListener('DOMContentLoaded', function() {
            quickViewModal = new bootstrap.Modal(document.getElementById('quickViewModal'));
        });

        // PERBAIKAN: Fungsi untuk update status dengan AJAX-like behavior
        function updateStatus(pesananId) {
            const form = document.getElementById('statusForm-' + pesananId);
            const select = document.getElementById('statusSelect-' + pesananId);
            const badge = document.getElementById('statusBadge-' + pesananId);
            const progressBar = document.getElementById('progressBar-' + pesananId);
            
            const selectedValue = select.value;
            let statusText = '';
            let statusClass = '';
            let progressClass = '';
            
            // Tentukan teks dan class berdasarkan nilai yang dipilih
            switch(selectedValue) {
                case 'belum':
                    statusText = 'Belum Diproses';
                    statusClass = 'belum';
                    progressClass = 'progress-belum';
                    break;
                case 'dalam_proses':
                    statusText = 'Dalam Proses';
                    statusClass = 'dalam_proses';
                    progressClass = 'progress-dalam_proses';
                    break;
                case 'selesai':
                    statusText = 'Selesai';
                    statusClass = 'selesai';
                    progressClass = 'progress-selesai';
                    break;
            }
            
            // Update UI terlebih dahulu untuk feedback langsung
            badge.textContent = statusText;
            badge.className = 'status-badge ' + statusClass;
            progressBar.className = 'progress-bar ' + progressClass;
            
            // Tambah efek loading
            select.classList.add('status-loading');
            badge.classList.add('status-updating');
            
            // Submit form
            form.submit();
        }

        // PERBAIKAN: Fungsi untuk menampilkan quick view items (tanpa parameter nama pelanggan)
        function showQuickView(pesananId, totalItems) {
            // Update modal header
            document.getElementById('quickViewId').textContent = '#' + pesananId;
            
            // Set link detail
            const detailLink = document.getElementById('quickViewDetailLink');
            detailLink.href = `detail_pesanan.php?id=${pesananId}`;
            
            // Tampilkan modal
            quickViewModal.show();
            
            // Ambil data items via AJAX
            fetch(`get_pesanan_items.php?id=${pesananId}`)
                .then(response => response.json())
                .then(data => {
                    const itemsContainer = document.getElementById('quickViewItems');
                    
                    if (data.success && data.items && data.items.length > 0) {
                        const items = data.items;
                        let totalKuantitas = 0;
                        let totalHarga = 0;
                        
                        // Buat HTML untuk items - TANPA MENAMPILKAN JUMLAH
                        let itemsHTML = '';
                        items.forEach((item, index) => {
                            totalKuantitas += parseInt(item.jumlah || 1);
                            totalHarga += parseFloat(item.harga_satuan || 0) * parseInt(item.jumlah || 1);
                            
                            itemsHTML += `
                                <div class="quick-item">
                                    <div class="quick-item-name">
                                        <span class="item-badge">${index + 1}</span>
                                        ${item.jenis_pakaian}
                                    </div>
                                    <div class="quick-item-details">
                                        <!-- PERBAIKAN: Hanya tampilkan harga, tanpa jumlah -->
                                        ${item.harga_satuan ? `
                                            <span class="quick-item-price">Rp ${parseInt(item.harga_satuan).toLocaleString('id-ID')}</span>
                                        ` : ''}
                                    </div>
                                </div>
                            `;
                        });
                        
                        // Update items container
                        itemsContainer.innerHTML = itemsHTML;
                        
                        // Update summary - TANPA MENAMPILKAN JUMLAH ITEMS
                        const summaryElement = document.getElementById('quickViewSummary');
                        summaryElement.innerHTML = `
                            <p><strong>${items.length}</strong> jenis pakaian</p>
                            <p><strong>Rp ${totalHarga.toLocaleString('id-ID')}</strong> total harga</p>
                        `;
                    } else {
                        itemsContainer.innerHTML = `
                            <div class="text-center p-3">
                                <i class="fas fa-box-open fa-2x text-muted mb-3"></i>
                                <p class="text-muted">Tidak ada data items ditemukan</p>
                            </div>
                        `;
                        document.getElementById('quickViewSummary').textContent = 'Tidak ada data items';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('quickViewItems').innerHTML = `
                        <div class="text-center p-3">
                            <i class="fas fa-exclamation-triangle fa-2x text-danger mb-3"></i>
                            <p class="text-danger">Gagal memuat data items</p>
                        </div>
                    `;
                });
        }

        // Fungsi untuk filter notifikasi
        function filterNotifikasi(jenis) {
            const rows = document.querySelectorAll('#pesananTable tbody tr');
            const searchInfo = document.querySelector('.search-info');
            let count = 0;
            
            rows.forEach(row => {
                if (jenis === 'terlambat' && row.classList.contains('row-terlambat')) {
                    row.style.display = '';
                    count++;
                } else if (jenis === 'peringatan' && row.classList.contains('row-peringatan')) {
                    row.style.display = '';
                    count++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Buat info filter baru
            const infoText = jenis === 'terlambat' 
                ? 'Menampilkan pesanan yang telah melewati tanggal selesai' 
                : 'Menampilkan pesanan yang akan jatuh tempo dalam 3 hari';
            
            const infoDiv = document.createElement('div');
            infoDiv.className = 'search-info';
            infoDiv.innerHTML = `
                <i class="fas fa-${jenis === 'terlambat' ? 'exclamation-triangle' : 'clock'}"></i> 
                ${infoText} (${count} pesanan ditemukan)
                <button type="button" class="btn btn-sm btn-outline-primary ms-2" style="font-size: 0.6rem; padding: 0.2rem 0.5rem;" 
                        onclick="resetFilter()">
                    <i class="fas fa-times"></i> Reset Filter
                </button>
            `;
            
            // Hapus info filter yang ada
            if (searchInfo) {
                searchInfo.remove();
            }
            
            // Tambah info filter baru
            const card = document.querySelector('.card');
            card.insertBefore(infoDiv, card.firstChild);
        }

        // Fungsi untuk reset filter
        function resetFilter() {
            // Tampilkan semua baris
            const rows = document.querySelectorAll('#pesananTable tbody tr');
            rows.forEach(row => {
                row.style.display = '';
            });
            
            // Hapus info filter
            const searchInfo = document.querySelector('.search-info');
            if (searchInfo) {
                searchInfo.remove();
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Tampilkan modal konfirmasi hapus
            const deleteButtons = document.querySelectorAll('.delete-btn');
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            const customerNameElement = document.getElementById('customerName');
            
            deleteButtons.forEach(button => {
                button.addEventListener('click', function() {
                    currentDeleteId = this.getAttribute('data-id');
                    currentDeleteName = this.getAttribute('data-pelanggan');
                    
                    customerNameElement.textContent = currentDeleteName;
                    deleteModal.show();
                });
            });

            // Konfirmasi hapus
            document.getElementById('confirmDelete').addEventListener('click', function() {
                window.location.href = `pesanan.php?hapus=${currentDeleteId}`;
            });

            // Highlight table rows on hover
            const tableRows = document.querySelectorAll('#pesananTable tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    if (!this.classList.contains('row-terlambat') && !this.classList.contains('row-peringatan')) {
                        this.style.backgroundColor = '#f8faff';
                    }
                });
                row.addEventListener('mouseleave', function() {
                    if (!this.classList.contains('row-terlambat') && !this.classList.contains('row-peringatan')) {
                        this.style.backgroundColor = '';
                    }
                });
            });

            // Auto focus search input
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.focus();
            }
            
            // Otomatis scroll ke pesanan dengan notifikasi jika ada
            const totalNotifikasi = <?= $total_terlambat + $total_peringatan; ?>;
            if (totalNotifikasi > 0) {
                const firstNotifikasi = document.querySelector('.row-terlambat') || document.querySelector('.row-peringatan');
                if (firstNotifikasi) {
                    setTimeout(() => {
                        firstNotifikasi.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });
                        
                        // Highlight efek
                        const notifikasiClass = firstNotifikasi.classList.contains('row-terlambat') ? 'row-terlambat' : 'row-peringatan';
                        const highlightColor = notifikasiClass === 'row-terlambat' ? 'rgba(220, 38, 38, 0.3)' : 'rgba(245, 158, 11, 0.3)';
                        
                        firstNotifikasi.style.boxShadow = `0 0 0 3px ${highlightColor}`;
                        setTimeout(() => {
                            firstNotifikasi.style.boxShadow = '';
                        }, 2000);
                    }, 1000);
                }
            }

            // Hover effects untuk tombol panah
            const arrowButtons = document.querySelectorAll('.arrow-icon-btn');
            arrowButtons.forEach(button => {
                button.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.1)';
                    this.style.transition = 'transform 0.2s ease';
                    this.style.backgroundColor = 'var(--primary-dark)';
                });
                
                button.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                    this.style.backgroundColor = 'var(--info)';
                });
                
                // Tambah efek klik
                button.addEventListener('mousedown', function() {
                    this.style.transform = 'scale(0.95)';
                });
                
                button.addEventListener('mouseup', function() {
                    this.style.transform = 'scale(1.1)';
                });
            });
        });
    </script>
</body>
</html>