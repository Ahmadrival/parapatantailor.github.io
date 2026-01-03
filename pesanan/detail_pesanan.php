<?php
// detail_pesanan.php
require_once '../config/database.php';
check_login();
check_role(['admin', 'pegawai']); // HANYA Admin dan Pegawai yang bisa akses

$page_title = "Detail Pesanan";

// ============================================================
// FUNGSI SYNC OTOMATIS UNTUK DETAIL PESANAN
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

// Fungsi untuk menentukan status pembayaran (digunakan oleh sync)
function getPaymentStatus($jumlah_bayar, $total_harga) {
    if ($jumlah_bayar == 0) {
        return 'belum_bayar';
    } elseif ($jumlah_bayar >= $total_harga) {
        return 'lunas';
    } elseif ($jumlah_bayar > 0 && $jumlah_bayar < $total_harga) {
        // Tentukan apakah DP atau cicilan berdasarkan persentase
        $persentase = ($jumlah_bayar / $total_harga) * 100;
        if ($persentase < 50) {
            return 'dp';
        } else {
            return 'cicilan';
        }
    }
    return 'belum_bayar';
}

// Cek apakah parameter ID ada
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "❌ ID pesanan tidak valid";
    header("Location: pesanan.php");
    exit();
}

$id_pesanan = clean_input($_GET['id']);

// ============================================================
// JALANKAN SYNC OTOMATIS SEBELUM MENGAMBIL DATA
// ============================================================
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

// Ambil data ukuran atasan
try {
    $sql_ukuran_atasan = "SELECT * FROM ukuran_atasan WHERE id_pesanan = ?";
    $ukuran_atasan = getAll($sql_ukuran_atasan, [$id_pesanan]);
} catch (PDOException $e) {
    $ukuran_atasan = [];
    error_log("Error mengambil ukuran atasan: " . $e->getMessage());
}

// Ambil data ukuran bawahan
try {
    $sql_ukuran_bawahan = "SELECT * FROM ukuran_bawahan WHERE id_pesanan = ?";
    $ukuran_bawahan = getAll($sql_ukuran_bawahan, [$id_pesanan]);
} catch (PDOException $e) {
    $ukuran_bawahan = [];
    error_log("Error mengambil ukuran bawahan: " . $e->getMessage());
}

// Format status text
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

// Hitung total kuantitas dari semua items
$total_kuantitas = 0;
$total_harga_items = 0;
foreach ($pesanan_items as $item) {
    $total_kuantitas += ($item['jumlah'] ?? 1);
    $total_harga_items += ($item['harga_satuan'] ?? 0) * ($item['jumlah'] ?? 1);
}

// Hitung total kuantitas ukuran
$total_atasan = count($ukuran_atasan);
$total_bawahan = count($ukuran_bawahan);
$total_ukuran = $total_atasan + $total_bawahan;

// Tentukan hak akses berdasarkan role
$role = $_SESSION['role'];
$is_admin = ($role == 'admin');
$is_pegawai = ($role == 'pegawai');

// Tentukan hak akses spesifik
$can_delete = $is_admin;         // Hanya Admin bisa hapus
$can_edit = $is_admin || $is_pegawai;  // Admin dan Pegawai bisa edit

// Tampilkan notifikasi sync jika ada
if (isset($_SESSION['sync_info'])) {
    $_SESSION['info'] = $_SESSION['sync_info'];
    unset($_SESSION['sync_info']);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan - SIM Parapatan Tailor</title>
     <link rel="shortcut icon" href="../assets/images/logoterakhir.png" type="image/x-icon" />

    <!-- Bootstrap 5.3.3 CSS -->
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
        
        .btn {
            padding: 0.5rem 1rem;
            font-weight: 500;
            border-radius: 6px;
            transition: all 0.2s ease;
            font-size: 0.8rem;
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
        
        .btn-warning {
            background-color: #ffc107;
            border-color: #ffc107;
            color: #212529;
        }
        
        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
            color: white;
        }
        
        .btn-success {
            background-color: #28a745;
            border-color: #28a745;
            color: white;
        }
        
        .btn-info {
            background-color: #17a2b8;
            border-color: #17a2b8;
            color: white;
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
        
        /* Simplified Info Section */
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
        
        /* Items Section - NEW STYLES FOR MULTIPLE ITEMS */
        .items-section {
            margin-bottom: 1.5rem;
        }
        
        .items-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .items-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            color: #374151;
            font-size: 0.9rem;
        }
        
        .items-count-badge {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            padding: 0.2rem 0.8rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        /* Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
            margin-bottom: 1rem;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .items-table th {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            font-weight: 600;
            padding: 0.6rem 0.8rem;
            text-align: left;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .items-table td {
            padding: 0.6rem 0.8rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
            color: #374151;
            vertical-align: top;
        }
        
        .items-table tr:hover {
            background: #f8fafc;
        }
        
        .item-badge {
            background: linear-gradient(135deg, #0ea5e9, #38bdf8);
            color: white;
            padding: 0.3rem 0.6rem;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 600;
            margin-right: 0.5rem;
        }
        
        .item-details {
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
        }
        
        .item-name {
            font-weight: 600;
            color: #1f2937;
            font-size: 0.8rem;
        }
        
        .item-props {
            display: flex;
            flex-wrap: wrap;
            gap: 0.8rem;
            font-size: 0.7rem;
            color: #6b7280;
        }
        
        .item-prop {
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        /* Simplified Ukuran Tables - DIKECILKAN */
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
        
        /* TABEL UKURAN YANG DIKECILKAN */
        .ukuran-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.65rem; /* Diperkecil */
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
            padding: 0.4rem 0.6rem; /* Diperkecil */
            text-align: left;
            font-size: 0.6rem; /* Diperkecil */
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .ukuran-table td {
            padding: 0.4rem 0.6rem; /* Diperkecil */
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
            color: #374151;
        }
        
        .ukuran-table tr:hover {
            background: #f8fafc;
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
        
        .keterangan-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 6px;
            padding: 0.6rem;
            margin-top: 0.5rem;
            font-size: 0.65rem;
            color: #856404;
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
        
        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: 1fr;
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
            
            .items-table,
            .ukuran-table {
                font-size: 0.6rem;
            }
            
            .items-table th,
            .items-table td,
            .ukuran-table th,
            .ukuran-table td {
                padding: 0.3rem 0.4rem;
            }
        }
        
        @media (max-width: 480px) {
            .container {
                padding: 10px;
            }
            
            .card {
                padding: 15px;
            }
            
            .items-header,
            .ukuran-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .item-props {
                flex-direction: column;
                gap: 0.3rem;
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
        
        /* Link ke Transaksi */
        .view-transactions-link {
            display: inline-block;
            margin-top: 0.8rem;
            font-size: 0.75rem;
            color: #3b82f6;
            text-decoration: none;
            font-weight: 500;
        }
        
        .view-transactions-link:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/header.php'; ?>

    <div class="main-content">
        <div class="content-body">
            <div class="container">
                <h2 class="my-3">Detail Pesanan</h2>

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
                
                <?php if (isset($_SESSION['info'])): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> <?= $_SESSION['info']; unset($_SESSION['info']); ?>
                    </div>
                <?php endif; ?>

                <!-- Header Actions -->
                <div class="header-actions">
                    <div>
                        <h3>
                            <i class="fas fa-receipt"></i> Pesanan #<?= $pesanan['id_pesanan']; ?>
                        </h3>
                        <div class="mt-2">
                            <span class="status-indicator <?= $status_class; ?>">
                                <i class="fas fa-circle" style="font-size: 0.6rem;"></i>
                                <?= $status_text; ?>
                            </span>
                        </div>
                    </div>
                    <div class="action-buttons">
                        <a href="pesanan.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Kembali
                        </a>
                    </div>
                </div>

                <div class="row">
                    <!-- Kolom Kiri - Informasi Utama -->
                    <div class="col-lg-8">
                        <!-- Informasi Dasar -->
                        <div class="card">
                            <h5 class="mb-3"><i class="fas fa-info-circle"></i> Informasi Pesanan</h5>
                            
                            <div class="info-grid">
                                <div class="info-card">
                                    <div class="info-header">
                                        <i class="fas fa-user"></i> Pelanggan
                                    </div>
                                    <div class="info-content">
                                        <div class="info-row">
                                            <span class="info-label">Nama:</span>
                                            <span class="info-value highlight"><?= htmlspecialchars($pesanan['nama_pelanggan']); ?></span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Alamat:</span>
                                            <span class="info-value"><?= htmlspecialchars($pesanan['alamat_pelanggan'] ?? '-'); ?></span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Telepon:</span>
                                            <span class="info-value"><?= htmlspecialchars($pesanan['telepon_pelanggan'] ?? '-'); ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="info-card">
                                    <div class="info-header">
                                        <i class="fas fa-user-tie"></i> Karyawan
                                    </div>
                                    <div class="info-content">
                                        <div class="info-row">
                                            <span class="info-label">Nama:</span>
                                            <span class="info-value highlight"><?= htmlspecialchars($pesanan['nama_karyawan'] ?? '-'); ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="info-card">
                                    <div class="info-header">
                                        <i class="fas fa-calendar"></i> Waktu
                                    </div>
                                    <div class="info-content">
                                        <div class="info-row">
                                            <span class="info-label">Pesan:</span>
                                            <span class="info-value"><?= date('d/m/Y', strtotime($pesanan['tgl_pesanan'])); ?></span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Selesai:</span>
                                            <span class="info-value"><?= !empty($pesanan['tgl_selesai']) ? date('d/m/Y', strtotime($pesanan['tgl_selesai'])) : '-'; ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="info-card">
                                    <div class="info-header">
                                        <i class="fas fa-box"></i> Ringkasan
                                    </div>
                                    <div class="info-content">
                                        <div class="info-row">
                                            <span class="info-label">Total Items:</span>
                                            <span class="info-value highlight"><?= count($pesanan_items); ?> jenis</span>
                                        </div>

                                        <div class="info-row">
                                            <span class="info-label">Total Ukuran:</span>
                                            <span class="info-value"><?= $total_ukuran; ?> set</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- DAFTAR ITEMS PESANAN (MULTIPLE ITEMS) -->
                        <div class="card items-section">
                            <div class="items-header">
                                <div class="items-title">
                                    <i class="fas fa-tshirt"></i> Items Pesanan
                                </div>
                                <span class="items-count-badge"><?= count($pesanan_items); ?> Items</span>
                            </div>
                            
                            <?php if (!empty($pesanan_items)): ?>
                                <table class="items-table">
                                    <thead>
                                        <tr>
                                            <th width="5%">#</th>
                                            <th width="60%">Jenis Pakaian & Bahan</th>
                                            <th width="35%">Harga</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pesanan_items as $index => $item): ?>
                                        <tr>
                                            <td>
                                                <span class="item-badge"><?= $index + 1; ?></span>
                                            </td>
                                            <td>
                                                <div class="item-details">
                                                    <div class="item-name"><?= htmlspecialchars($item['jenis_pakaian']); ?></div>
                                                    <?php if (!empty($item['bahan'])): ?>
                                                    <div class="item-props">
                                                        <span class="item-prop">
                                                            <i class="fas fa-palette"></i>
                                                            Bahan: <?= htmlspecialchars($item['bahan']); ?>
                                                        </span>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if (isset($item['harga_satuan']) && $item['harga_satuan'] > 0): ?>
                                                <div class="item-prop" style="font-weight: bold; color: #059669;">
                                                    <i class="fas fa-tag"></i>
                                                    Rp <?= number_format($item['harga_satuan'], 0, ',', '.'); ?>
                                                </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                
                                <!-- Summary Total Harga dari Items -->
                                <div class="mt-3 p-3" style="background: linear-gradient(135deg, #f0fdf4, #dcfce7); border-radius: 8px; border: 1px solid #86efac;">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong style="color: #166534; font-size: 0.8rem;">Total Harga Items:</strong>
                                            <div style="color: #6b7280; font-size: 0.7rem;">
                                                <?= count($pesanan_items); ?> jenis item
                                            </div>
                                        </div>
                                        <div style="font-size: 0.9rem; font-weight: bold; color: #059669;">
                                            Rp <?= number_format($total_harga_items, 0, ',', '.'); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-tshirt"></i>
                                    <p>Tidak ada data items pesanan</p>
                                    <?php if ($can_edit): ?>
                                    <a href="editpesanan.php?edit=<?= $pesanan['id_pesanan']; ?>" class="btn btn-sm btn-primary mt-2">
                                        <i class="fas fa-plus-circle"></i> Tambah Items
                                    </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Informasi Ukuran - DIKECILKAN -->
                        <div class="card">
                            <h5 class="mb-3"><i class="fas fa-ruler-combined"></i> Informasi Ukuran</h5>
                            
                            <!-- Ukuran Atasan -->
                            <?php if ($total_atasan > 0): ?>
                                <div class="ukuran-section">
                                    <div class="ukuran-header">
                                        <div class="ukuran-title">
                                            <i class="fas fa-tshirt"></i> Ukuran Atasan
                                        </div>
                                        <span class="kuantitas-badge"><?= $total_atasan ?> set</span>
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
                                                <tr><td>Krah</td><td><?= $atasan['krah'] ?? '0'; ?></td></tr>
                                                <tr><td>Pundak</td><td><?= $atasan['pundak'] ?? '0'; ?></td></tr>
                                                <tr><td>Tangan</td><td><?= $atasan['tangan'] ?? '0'; ?></td></tr>
                                                <tr><td>LD/LP</td><td><?= $atasan['ld_lp'] ?? '0'; ?></td></tr>
                                                <tr><td>Badan</td><td><?= $atasan['badan'] ?? '0'; ?></td></tr>
                                                <tr><td>Pinggang</td><td><?= $atasan['pinggang'] ?? '0'; ?></td></tr>
                                                <tr><td>Pinggul</td><td><?= $atasan['pinggul'] ?? '0'; ?></td></tr>
                                                <tr><td>Panjang</td><td><?= $atasan['panjang'] ?? '0'; ?></td></tr>
                                            </tbody>
                                        </table>
                                        
                                        <?php if (!empty($atasan['keterangan'])): ?>
                                        <div class="keterangan-box">
                                            <strong>Keterangan:</strong> <?= htmlspecialchars($atasan['keterangan']); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Ukuran Bawahan -->
                            <?php if ($total_bawahan > 0): ?>
                                <div class="ukuran-section">
                                    <div class="ukuran-header">
                                        <div class="ukuran-title">
                                            <i class="fas fa-tshirt"></i> Ukuran Bawahan
                                        </div>
                                        <span class="kuantitas-badge"><?= $total_bawahan ?> set</span>
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
                                                <tr><td>Pinggang</td><td><?= $bawahan['pinggang'] ?? '0'; ?></td></tr>
                                                <tr><td>Pinggul</td><td><?= $bawahan['pinggul'] ?? '0'; ?></td></tr>
                                                <tr><td>Kres</td><td><?= $bawahan['kres'] ?? '0'; ?></td></tr>
                                                <tr><td>Paha</td><td><?= $bawahan['paha'] ?? '0'; ?></td></tr>
                                                <tr><td>Lutut</td><td><?= $bawahan['lutut'] ?? '0'; ?></td></tr>
                                                <tr><td>L. Bawah</td><td><?= $bawahan['l_bawah'] ?? '0'; ?></td></tr>
                                                <tr><td>Panjang</td><td><?= $bawahan['panjang'] ?? '0'; ?></td></tr>
                                            </tbody>
                                        </table>
                                        
                                        <?php if (!empty($bawahan['keterangan'])): ?>
                                        <div class="keterangan-box">
                                            <strong>Keterangan:</strong> <?= htmlspecialchars($bawahan['keterangan']); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Tidak ada ukuran -->
                            <?php if ($total_atasan == 0 && $total_bawahan == 0): ?>
                                <div class="empty-state">
                                    <i class="fas fa-ruler-combined"></i>
                                    <p>Tidak ada data ukuran yang tercatat</p>
                                    <?php if ($can_edit): ?>
                                    <a href="editpesanan.php?edit=<?= $pesanan['id_pesanan']; ?>" class="btn btn-sm btn-primary mt-2">
                                        <i class="fas fa-plus-circle"></i> Tambah Ukuran
                                    </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Kolom Kanan - Sidebar Info -->
                    <div class="col-lg-4">
                        <!-- Informasi Pembayaran -->
                        <div class="card">
                            <h5 class="mb-3"><i class="fas fa-money-bill-wave"></i> Pembayaran</h5>
                            
                            <div class="payment-card">
                                <div class="payment-header">
                                    <i class="fas fa-receipt"></i> Ringkasan Pembayaran
                                </div>
                                <div class="payment-item">
                                    <span class="payment-label">Total Harga:</span>
                                    <span class="payment-value total">Rp <?= number_format($pesanan['total_harga'], 0, ',', '.'); ?></span>
                                </div>
                                <div class="payment-item">
                                    <span class="payment-label">Jumlah Bayar:</span>
                                    <span class="payment-value paid">Rp <?= number_format($pesanan['jumlah_bayar'], 0, ',', '.'); ?></span>
                                </div>
                                <div class="payment-item">
                                    <span class="payment-label">Sisa Bayar:</span>
                                    <span class="payment-value remaining">Rp <?= number_format($pesanan['sisa_bayar'], 0, ',', '.'); ?></span>
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

                        <!-- Quick Actions -->
                        <div class="card">
                            <h5 class="mb-3"><i class="fas fa-bolt"></i> Quick Actions</h5>
                            
                            <div class="quick-actions">
                                <?php if ($can_edit): ?>
                                <a href="editpesanan.php?edit=<?= $pesanan['id_pesanan']; ?>" class="btn btn-warning">
                                    <i class="fas fa-edit"></i> Edit Pesanan
                                </a>
                                <?php endif; ?>
                                
                                <a href="cetak_invoice.php?id=<?= $pesanan['id_pesanan']; ?>" class="btn btn-info" target="_blank">
                                    <i class="fas fa-print"></i> Cetak Invoice
                                </a>
                                
                                <?php if ($can_delete): ?>
                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                    <i class="fas fa-trash"></i> Hapus Pesanan
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <?php include '../includes/footer.php'; ?>
    </div>

    <!-- Delete Confirmation Modal - HANYA untuk Admin -->
    <?php if ($can_delete): ?>
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger" id="deleteModalLabel">
                        <i class="fas fa-exclamation-triangle"></i> Konfirmasi Penghapusan
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Apakah Anda yakin ingin menghapus pesanan untuk pelanggan <strong><?= htmlspecialchars($pesanan['nama_pelanggan']); ?></strong>?</p>
                    <p class="text-muted small">
                        <i class="fas fa-info-circle"></i> Tindakan ini akan menghapus semua data terkait termasuk ukuran dan transaksi.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <a href="pesanan.php?hapus=<?= $pesanan['id_pesanan']; ?>" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Ya, Hapus
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Smooth animations
            const cards = document.querySelectorAll('.card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'all 0.4s ease';
                
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Print shortcut
            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey && e.key === 'p') {
                    e.preventDefault();
                    window.open('cetak_invoice.php?id=<?= $pesanan['id_pesanan']; ?>', '_blank');
                }
            });
            
            // Status update animation
            const statusIndicator = document.querySelector('.status-indicator');
            if (statusIndicator) {
                statusIndicator.addEventListener('click', function() {
                    this.style.transform = 'scale(1.05)';
                    setTimeout(() => {
                        this.style.transform = 'scale(1)';
                    }, 200);
                });
            }
            
            // Auto refresh progress bar dengan animasi
            const progressBar = document.querySelector('.payment-progress-bar');
            if (progressBar) {
                const currentWidth = parseFloat(progressBar.style.width) || 0;
                progressBar.style.width = '0%';
                
                setTimeout(() => {
                    progressBar.style.transition = 'width 1s ease-in-out';
                    progressBar.style.width = currentWidth + '%';
                }, 500);
            }
            
            <?php if ($synced): ?>
            setTimeout(() => {
                const paymentCard = document.querySelector('.payment-card');
                if (paymentCard) {
                    paymentCard.style.animation = 'pulse 2s ease-in-out';
                    
                    setTimeout(() => {
                        paymentCard.style.animation = '';
                    }, 2000);
                }
            }, 1000);
            <?php endif; ?>
            
            // Highlight items table rows
            const itemRows = document.querySelectorAll('.items-table tbody tr');
            itemRows.forEach((row, index) => {
                row.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#f0f9ff';
                    this.style.transform = 'translateX(2px)';
                    this.style.transition = 'all 0.2s ease';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = '';
                    this.style.transform = 'translateX(0)';
                });
                
                // Add animation delay for each row
                setTimeout(() => {
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(-10px)';
                    row.style.transition = 'all 0.3s ease';
                    
                    setTimeout(() => {
                        row.style.opacity = '1';
                        row.style.transform = 'translateX(0)';
                    }, index * 100 + 300);
                }, 100);
            });
        });
        
        // CSS untuk animasi pulse
        const style = document.createElement('style');
        style.textContent = `
            @keyframes pulse {
                0% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.7); }
                70% { box-shadow: 0 0 0 10px rgba(59, 130, 246, 0); }
                100% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>