<?php
// dashboard.php
require_once 'config/database.php';
check_login();

// Set page title untuk header
$page_title = "Dashboard Sistem Tailor";

// Ambil data user dari database untuk memastikan nama lengkap ada
$user_data = [];
try {
    $stmt = $pdo->prepare("SELECT nama_lengkap, role, id_user FROM users WHERE id_user = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Update session dengan data terbaru dari database
    if ($user_data) {
        $_SESSION['nama_lengkap'] = $user_data['nama_lengkap'];
        $_SESSION['role'] = $user_data['role'];
    }
} catch (PDOException $e) {
    error_log("Error fetching user data: " . $e->getMessage());
}

// Jika user adalah karyawan/pegawai, ambil pesanan yang ditugaskan kepadanya
$assigned_orders = [];
$total_assigned_orders = 0;
$pending_assigned_orders = 0;

if ($_SESSION['role'] == 'pegawai') {
    try {
        $user_id = $_SESSION['user_id'];
        
        $stmt = $pdo->prepare("SELECT p.*, pl.nama as nama_pelanggan, u.nama_lengkap as nama_pegawai 
                              FROM data_pesanan p 
                              LEFT JOIN data_pelanggan pl ON p.id_pelanggan = pl.id_pelanggan 
                              LEFT JOIN users u ON p.id_karyawan = u.id_user 
                              WHERE p.id_karyawan = ? 
                              ORDER BY p.tgl_pesanan DESC 
                              LIMIT 10");
        $stmt->execute([$user_id]);
        $assigned_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM data_pesanan WHERE id_karyawan = ?");
        $stmt->execute([$user_id]);
        $total_assigned_orders = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM data_pesanan WHERE id_karyawan = ? AND status_pesanan != 'selesai'");
        $stmt->execute([$user_id]);
        $pending_assigned_orders = $stmt->fetchColumn();
        
    } catch (PDOException $e) {
        error_log("Error fetching assigned orders: " . $e->getMessage());
    }
}

// Hitung statistik utama
try {
    // Total Pelanggan
    $total_pelanggan = $pdo->query("SELECT COUNT(*) as total FROM data_pelanggan")->fetchColumn();
    
    // Total Pesanan
    $total_pesanan = $pdo->query("SELECT COUNT(*) as total FROM data_pesanan")->fetchColumn();
    
    // Total Pesanan Selesai
    $total_selesai = $pdo->query("SELECT COUNT(*) as total FROM data_pesanan WHERE status_pesanan = 'selesai'")->fetchColumn();
    
    // Total Pesanan Dalam Proses
    $total_dalam_proses = $pdo->query("SELECT COUNT(*) as total FROM data_pesanan WHERE status_pesanan = 'dalam_proses'")->fetchColumn();
    
    // Hitung total pendapatan dari semua transaksi pembayaran
    $total_uang_masuk = $pdo->query("SELECT COALESCE(SUM(jumlah_bayar), 0) as total FROM data_transaksi")->fetchColumn();
    
    // Cek apakah tabel data_pemasukan ada
    $total_pemasukan_lain = 0;
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as exists_table FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'data_pemasukan'");
        $table_exists = $stmt->fetchColumn();
        
        if ($table_exists) {
            $total_pemasukan_lain = $pdo->query("SELECT COALESCE(SUM(jumlah_pemasukan), 0) as total FROM data_pemasukan")->fetchColumn();
        }
    } catch (Exception $e) {
        // Tabel tidak ada, lanjutkan tanpa error
        $total_pemasukan_lain = 0;
    }
    
    // Total Pendapatan
    $total_pendapatan = $total_uang_masuk + $total_pemasukan_lain;

    // Hitung pendapatan bulan ini
    $current_month = date('Y-m');
    $pendapatan_bulan_ini = 0;
    
    // Dari transaksi
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(jumlah_bayar), 0) as total FROM data_transaksi WHERE DATE_FORMAT(created_at, '%Y-%m') = ?");
    $stmt->execute([$current_month]);
    $pendapatan_bulan_ini += $stmt->fetchColumn();
    
    // Dari pemasukan lain jika tabel ada
    if ($total_pemasukan_lain > 0) {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(jumlah_pemasukan), 0) as total FROM data_pemasukan WHERE DATE_FORMAT(tgl_pemasukan, '%Y-%m') = ?");
        $stmt->execute([$current_month]);
        $pendapatan_bulan_ini += $stmt->fetchColumn();
    }

    // Statistik pesanan bulan ini
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM data_pesanan WHERE DATE_FORMAT(tgl_pesanan, '%Y-%m') = ?");
    $stmt->execute([$current_month]);
    $pesanan_bulan_ini = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM data_pesanan WHERE status_pesanan = 'selesai' AND DATE_FORMAT(tgl_pesanan, '%Y-%m') = ?");
    $stmt->execute([$current_month]);
    $selesai_bulan_ini = $stmt->fetchColumn();
    
    $progress_bulan_ini = $pesanan_bulan_ini > 0 ? ($selesai_bulan_ini / $pesanan_bulan_ini) * 100 : 0;

    // Hitung statistik tambahan
    $total_omzet = $pdo->query("SELECT COALESCE(SUM(total_harga), 0) as total FROM data_pesanan")->fetchColumn();
    
    // Pastikan kolom sisa_bayar ada di tabel data_pesanan
    $total_piutang = 0;
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as column_exists FROM information_schema.columns 
                           WHERE table_schema = DATABASE() 
                           AND table_name = 'data_pesanan' 
                           AND column_name = 'sisa_bayar'");
        $column_exists = $stmt->fetchColumn();
        
        if ($column_exists) {
            $total_piutang = $pdo->query("SELECT COALESCE(SUM(sisa_bayar), 0) as total FROM data_pesanan WHERE sisa_bayar > 0")->fetchColumn();
        }
    } catch (Exception $e) {
        $total_piutang = 0;
    }
    
    // Total Pengeluaran
    $total_pengeluaran = 0;
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as exists_table FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'data_pengeluaran'");
        $table_exists = $stmt->fetchColumn();
        
        if ($table_exists) {
            $total_pengeluaran = $pdo->query("SELECT COALESCE(SUM(jumlah_pengeluaran), 0) as total FROM data_pengeluaran")->fetchColumn();
        }
    } catch (Exception $e) {
        $total_pengeluaran = 0;
    }
    
    // Kas bersih
    $kas_bersih = $total_uang_masuk - $total_pengeluaran;

} catch (PDOException $e) {
    // Set default values jika terjadi error
    $total_pelanggan = $total_pesanan = $total_selesai = $total_dalam_proses = 0;
    $total_pendapatan = $pendapatan_bulan_ini = $progress_bulan_ini = 0;
    $pesanan_bulan_ini = $selesai_bulan_ini = 0;
    $total_omzet = $total_piutang = $total_uang_masuk = $total_pemasukan_lain = $total_pengeluaran = $kas_bersih = 0;
    
    error_log("Error fetching dashboard statistics: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SIM Parapatan Tailor</title>
    <link rel="shortcut icon" href="assets/images/logoterakhir.png" type="image/x-icon" />

    <!-- Bootstrap 5.3.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* CSS Custom yang konsisten dengan dashboard.php */
        body {
            background-color: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 0.85rem;
        }
        
        .container {
            max-width: 1800px;
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
            border-radius: 10px;
            box-shadow: 0 2px 7px rgba(0, 0, 0, 0.1);
            padding: 10px;
            background-color: #f8f9fa;
            margin-bottom: 1.5rem;
        }
        
        /* Button Styles - Diperkecil */
        .btn {
            padding: 0.35rem 0.7rem;
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
        
        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
            color: white;
        }
        
        .btn-warning {
            background-color: #ffc107;
            border-color: #ffc107;
            color: #212529;
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
        
        .btn-secondary:hover, .btn-primary:hover, .btn-success:hover, .btn-danger:hover, .btn-warning:hover, .btn-info:hover {
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
        
        .alert-warning { 
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #92400e; 
            border-left: 3px solid #f59e0b;
        }
        
        /* Table Styles - Font lebih kecil dan garis berwarna */
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 1px 8px rgba(0,0,0,0.1);
            background: white;
            font-size: 0.75rem;
            border: 1px solid #e0e7ff;
        }
        
        .table th {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            font-weight: 600;
            padding: 0.6rem;
            text-align: left;
            border: 1px solid #4f46e5;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .table td {
            padding: 0.6rem;
            text-align: left;
            border-bottom: 1px solid #e0e7ff;
            border-right: 1px solid #e0e7ff;
            color: #374151;
            font-size: 0.75rem;
            transition: all 0.3s ease;
        }
        
        .table td:last-child {
            border-right: none;
        }
        
        .table tr:last-child td {
            border-bottom: 1px solid #e0e7ff;
        }
        
        .table tr:hover td {
            background: linear-gradient(90deg, rgba(79, 70, 229, 0.05) 0%, transparent 100%);
            transform: translateX(3px);
        }
        
        .table tr:nth-child(even) {
            background: #fafbff;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.2rem;
            justify-content: center;
        }
        
        .empty-state {
            text-align: center;
            padding: 2rem 1.5rem;
            color: #6b7280;
            background: white;
            border-radius: 10px;
            box-shadow: 0 1px 8px rgba(0,0,0,0.08);
            margin: 1.5rem 0;
            font-size: 0.85rem;
        }
        
        .empty-state i {
            font-size: 2.5rem;
            margin-bottom: 0.8rem;
            color: #d1d5db;
            opacity: 0.7;
        }
        
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding: 1.2rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 6px rgba(0,0,0,0.08);
            border-left: 2px solid #4f46e5;
        }
        
        .header-actions h3 {
            color: #1f2937;
            margin: 0;
            font-size: 1.1rem;
            font-weight: 700;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .no-urut {
            text-align: center;
            font-weight: bold;
            color: #4f46e5;
            width: 40px;
            font-size: 0.75rem;
        }
        
        /* Stats Grid - Konsisten dengan pelanggan.php */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.2rem;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 3px solid;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }
        
        .stat-icon {
            width: 30px;
            height: 30px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            margin-bottom: 0.8rem;
        }
        
        .stat-title {
            font-size: 0.65rem;
            color: #6b7280;
            font-weight: 350;
            margin-bottom: 0.4rem;
        }
        
        .stat-number {
            font-size: 1.4rem;
            font-weight: 600;
            line-height: 1;
            margin-bottom: 0.3rem;
            color: #1f2937;
        }
        
        .stat-trend {
            font-size: 0.7rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.2rem;
        }
        
        .trend-up { color: #10b981; }
        .trend-down { color: #ef4444; }
        
        /* Card Colors */
        .stat-card:nth-child(1) { border-left-color: #4f46e5; }
        .stat-card:nth-child(2) { border-left-color: #3b82f6; }
        .stat-card:nth-child(3) { border-left-color: #10b981; }
        .stat-card:nth-child(4) { border-left-color: #f59e0b; }
        .stat-card:nth-child(5) { border-left-color: #ef4444; }
        .stat-card:nth-child(6) { border-left-color: #8b5cf6; }
        
        .stat-card:nth-child(1) .stat-icon { background: rgba(79, 70, 229, 0.1); color: #4f46e5; }
        .stat-card:nth-child(2) .stat-icon { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        .stat-card:nth-child(3) .stat-icon { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .stat-card:nth-child(4) .stat-icon { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
        .stat-card:nth-child(5) .stat-icon { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
        .stat-card:nth-child(6) .stat-icon { background: rgba(139, 92, 246, 0.1); color: #8b5cf6; }
        
        /* Layout Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        @media (max-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Section Cards */
        .section-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        
        .section-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #e5e7eb;
            background: #f8f9fa;
        }
        
        .section-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .section-body {
            padding: 1.25rem;
        }
        
        /* Status Badges */
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .status-belum { background: #fef3c7; color: #d97706; }
        .status-dalam_proses { background: #dbeafe; color: #1d4ed8; }
        .status-selesai { background: #dcfce7; color: #166534; }
        
        /* Priority Badges */
        .priority-badge {
            padding: 0.15rem 0.4rem;
            border-radius: 6px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        
        .priority-high { background: #fee2e2; color: #dc2626; }
        .priority-medium { background: #fef3c7; color: #d97706; }
        .priority-low { background: #d1fae5; color: #059669; }
        
        /* Quick Actions */
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 0.8rem;
        }
        
        .quick-action-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
            min-height: 80px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .quick-action-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border-color: #4f46e5;
        }
        
        .action-icon {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            margin: 0 auto 0.5rem;
            background: rgba(79, 70, 229, 0.1);
            color: #4f46e5;
        }
        
        .action-title {
            font-weight: 600;
            margin-bottom: 0.2rem;
            font-size: 0.75rem;
            line-height: 1.2;
        }
        
        .action-desc {
            font-size: 0.65rem;
            color: #6b7280;
            line-height: 1.2;
        }
        
        /* Employee Notification */
        .employee-notification {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }
        
        .employee-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .employee-stat {
            background: rgba(255,255,255,0.2);
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            backdrop-filter: blur(10px);
        }
        
        .employee-stat-number {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.3rem;
        }
        
        .employee-stat-label {
            font-size: 0.75rem;
            opacity: 0.9;
        }
        
        /* Welcome Header */
        .welcome-header {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .welcome-header::before {
            content: '';
            position: absolute;
            top: -30%;
            right: -15%;
            width: 150px;
            height: 150px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }
        
        .welcome-content {
            position: relative;
            z-index: 2;
        }
        
        .welcome-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .welcome-subtitle {
            font-size: 0.9rem;
            opacity: 0.9;
            font-weight: 400;
            color: #ffffff !important;
            text-shadow: 0 1px 2px rgba(0,0,0,0.3);
        }
        
        /* Status Form */
        .status-form {
            display: inline-block;
        }

        .status-select {
            padding: 0.3rem 0.5rem;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            font-size: 0.65rem;
            background: white;
            cursor: pointer;
            min-width: 80px;
        }

        .status-select:focus {
            outline: none;
            border-color: #4f46e5;
            box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1);
        }
        
        /* Table Container */
        .table-container {
            border: 1px solid #e0e7ff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .table thead tr {
            border-bottom: 2px solid #4f46e5;
        }

        .table tbody tr {
            border-bottom: 1px solid #e0e7ff;
        }

        .table tbody tr:last-child {
            border-bottom: none;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                padding: 12px;
                margin: 0.8rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.8rem;
            }
            
            .stat-card {
                padding: 1rem;
            }
            
            .stat-number {
                font-size: 1.3rem;
            }
            
            .quick-actions-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .section-body {
                padding: 1rem;
            }
            
            .table {
                font-size: 0.7rem;
            }
            
            .table th,
            .table td {
                padding: 0.5rem 0.6rem;
            }
            
            .welcome-header {
                padding: 1.2rem;
            }
            
            .welcome-title {
                font-size: 1.3rem;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .quick-actions-grid {
                grid-template-columns: 1fr;
            }
            
            .employee-stats {
                grid-template-columns: 1fr;
            }
            
            .dashboard-grid {
                gap: 1rem;
            }
        }
        
        /* Main content spacing for footer */
        .main-content {
            min-height: calc(100vh - 80px);
            display: flex;
            flex-direction: column;
        }
        
        .content-body {
            flex: 1;
        }
        
        /* Info box untuk sinkronisasi */
        .sync-info {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border: 1px solid #bae6fd;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            font-size: 0.75rem;
        }
        
        .sync-info h6 {
            color: #0c4a6e;
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .sync-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 0.8rem;
            margin-top: 0.8rem;
        }
        
        .sync-stat {
            background: white;
            padding: 0.6rem;
            border-radius: 6px;
            border-left: 3px solid #0ea5e9;
        }
        
        .sync-stat-label {
            font-size: 0.65rem;
            color: #6b7280;
            margin-bottom: 0.2rem;
        }
        
        .sync-stat-value {
            font-weight: 600;
            font-size: 0.8rem;
            color: #1f2937;
        }
        
        /* Debug info - bisa dihapus setelah fix */
        .debug-info {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 6px;
            padding: 0.5rem;
            margin-bottom: 0.5rem;
            font-size: 0.7rem;
            color: #991b1b;
            display: none; /* Sembunyikan di production */
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/header.php'; ?>

    <div class="main-content">
        <div class="content-body">
            <div class="container">
                <h2 class="my-3">Dashboard Sistem</h2>

                <!-- Alert Messages -->
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

                <!-- Debug info - Hapus setelah fix -->
                <div class="debug-info">
                    <strong>Debug Info:</strong> 
                    User ID: <?= $_SESSION['user_id'] ?? 'N/A'; ?> | 
                    Role: <?= $_SESSION['role'] ?? 'N/A'; ?> | 
                    Database Connection: <?= isset($pdo) ? 'OK' : 'FAILED'; ?>
                </div>

                <!-- Welcome Header -->
                <div class="welcome-header">
                    <div class="welcome-content">
                        <h1 class="welcome-title">
                            <i class="fas fa-home"></i>
                            Selamat Datang, <?= htmlspecialchars($user_data['nama_lengkap'] ?? $_SESSION['nama_lengkap'] ?? 'User'); ?>!
                        </h1>
                        <p class="welcome-subtitle">
                            Sistem Informasi Management Parapatan Tailor
                        </p>
                    </div>
                </div>

                <!-- Info Sinkronisasi Data -->
                <?php if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'pemilik'): ?>
                <div class="sync-info">
                    <h6><i class="fas fa-sync"></i> Sinkronisasi Data Keuangan</h6>
                    <p style="margin-bottom: 0.5rem; color: #374151;">Data pendapatan dihitung dari semua transaksi pembayaran dan pemasukan lainnya.</p>
                    <div class="sync-stats">
                        <div class="sync-stat">
                            <div class="sync-stat-label">Total Omzet</div>
                            <div class="sync-stat-value">Rp <?= number_format($total_omzet, 0, ',', '.'); ?></div>
                        </div>
                        <div class="sync-stat">
                            <div class="sync-stat-label">Uang Masuk</div>
                            <div class="sync-stat-value">Rp <?= number_format($total_uang_masuk, 0, ',', '.'); ?></div>
                        </div>
                        <div class="sync-stat">
                            <div class="sync-stat-label">Piutang</div>
                            <div class="sync-stat-value">Rp <?= number_format($total_piutang, 0, ',', '.'); ?></div>
                        </div>
                        <div class="sync-stat">
                            <div class="sync-stat-label">Pemasukan Lain</div>
                            <div class="sync-stat-value">Rp <?= number_format($total_pemasukan_lain, 0, ',', '.'); ?></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Notifikasi untuk Pegawai -->
                <?php if ($_SESSION['role'] == 'pegawai' && $total_assigned_orders > 0): ?>
                <div class="employee-notification">
                    <h3 style="margin-bottom: 0.8rem; display: flex; align-items: center; gap: 0.4rem; font-size: 0.9rem;">
                        <i class="fas fa-tasks"></i> Pesanan yang Ditugaskan kepada Anda
                    </h3>
                    <div class="employee-stats">
                        <div class="employee-stat">
                            <div class="employee-stat-number"><?= $total_assigned_orders; ?></div>
                            <div class="employee-stat-label">Total Pesanan</div>
                        </div>
                        <div class="employee-stat">
                            <div class="employee-stat-number"><?= $pending_assigned_orders; ?></div>
                            <div class="employee-stat-label">Perlu Dikerjakan</div>
                        </div>
                        <div class="employee-stat">
                            <div class="employee-stat-number"><?= $total_assigned_orders - $pending_assigned_orders; ?></div>
                            <div class="employee-stat-label">Sudah Selesai</div>
                        </div>
                    </div>
                </div>
                <?php elseif ($_SESSION['role'] == 'pegawai'): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>Info:</strong> Saat ini tidak ada pesanan yang ditugaskan kepada Anda.
                </div>
                <?php endif; ?>

                <!-- Statistik Utama -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-title">Total Pelanggan</div>
                            <div class="stat-number"><?= number_format($total_pelanggan); ?></div>
                            <div class="stat-trend trend-up">
                                <i class="fas fa-arrow-up"></i> Aktif
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-shopping-bag"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-title">Total Pesanan</div>
                            <div class="stat-number"><?= number_format($total_pesanan); ?></div>
                            <div class="stat-trend trend-up">
                                <i class="fas fa-chart-line"></i> All Time
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-title">Pesanan Selesai</div>
                            <div class="stat-number"><?= number_format($total_selesai); ?></div>
                            <div class="stat-trend trend-up">
                                <i class="fas fa-check"></i> <?= $total_pesanan > 0 ? number_format(($total_selesai/$total_pesanan)*100, 0) : 0 ?>%
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-title">Dalam Proses</div>
                            <div class="stat-number"><?= number_format($total_dalam_proses); ?></div>
                            <div class="stat-trend trend-up">
                                <i class="fas fa-sync"></i> Progress
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-title">Total Pendapatan</div>
                            <div class="stat-number">Rp <?= number_format($total_pendapatan, 0, ',', '.'); ?></div>
                            <div class="stat-trend trend-up">
                                <i class="fas fa-wallet"></i> Lifetime
                            </div>
                        </div>
                    </div>
                </div>

                <div class="dashboard-grid">
                    <!-- Left Column -->
                    <div class="left-column">
                        <!-- Pesanan yang Ditugaskan untuk Pegawai -->
                        <?php if ($_SESSION['role'] == 'pegawai' && !empty($assigned_orders)): ?>
                        <div class="section-card">
                            <div class="section-header">
                                <h3 class="section-title">
                                    <i class="fas fa-bullseye"></i>
                                    Pesanan yang Harus Dikerjakan
                                </h3>
                            </div>
                            <div class="section-body">
                                <div class="table-container">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th class="no-urut">No</th>
                                                <th>ID Pesanan</th>
                                                <th>Pelanggan</th>
                                                <th>Jenis Pakaian</th>
                                                <th>Deadline</th>
                                                <th>Status</th>
                                                <th style="text-align: center;">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $no = 1; ?>
                                            <?php foreach ($assigned_orders as $row): ?>
                                            <?php
                                            $status_class = match($row['status_pesanan']) {
                                                'belum' => 'status-belum',
                                                'dalam_proses' => 'status-dalam_proses',
                                                'selesai' => 'status-selesai',
                                                default => 'status-belum',
                                            };
                                            $status_text = match($row['status_pesanan']) {
                                                'belum' => 'Belum',
                                                'dalam_proses' => 'Proses',
                                                'selesai' => 'Selesai',
                                                default => ucfirst($row['status_pesanan']),
                                            };
                                            
                                            $deadline = $row['tgl_selesai'] ?? null;
                                            $priority_class = 'priority-medium';
                                            $priority_text = 'Medium';
                                            
                                            if ($deadline) {
                                                $today = new DateTime();
                                                $deadline_date = new DateTime($deadline);
                                                $days_diff = $today->diff($deadline_date)->days;
                                                
                                                if ($days_diff <= 1) {
                                                    $priority_class = 'priority-high';
                                                    $priority_text = 'High';
                                                } elseif ($days_diff <= 3) {
                                                    $priority_class = 'priority-medium';
                                                    $priority_text = 'Medium';
                                                } else {
                                                    $priority_class = 'priority-low';
                                                    $priority_text = 'Low';
                                                }
                                            }
                                            ?>
                                            <tr>
                                                <td class="no-urut"><?= $no++; ?></td>
                                                <td><strong>#<?= htmlspecialchars($row['id_pesanan']); ?></strong></td>
                                                <td><?= htmlspecialchars($row['nama_pelanggan']); ?></td>
                                                <td><?= htmlspecialchars($row['jenis_pakaian'] ?? '-'); ?></td>
                                                <td>
                                                    <?php if ($deadline): ?>
                                                        <?= date('d/m/Y', strtotime($deadline)); ?>
                                                        <span class="priority-badge <?= $priority_class; ?>"><?= $priority_text; ?></span>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <form method="POST" action="../pesanan/update_status.php" class="status-form">
                                                        <input type="hidden" name="id_pesanan" value="<?= $row['id_pesanan']; ?>">
                                                        <select name="status_pesanan" class="status-select" onchange="this.form.submit()">
                                                            <option value="belum" <?= $row['status_pesanan'] == 'belum' ? 'selected' : ''; ?>>Belum</option>
                                                            <option value="dalam_proses" <?= $row['status_pesanan'] == 'dalam_proses' ? 'selected' : ''; ?>>Proses</option>
                                                            <option value="selesai" <?= $row['status_pesanan'] == 'selesai' ? 'selected' : ''; ?>>Selesai</option>
                                                        </select>
                                                    </form>
                                                </td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <a href="../../pesanan/editpesanan.php?edit=<?= $row['id_pesanan']; ?>" class="btn btn-warning btn-sm" title="Edit pesanan">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="../../pesanan/detail_pesanan.php?id=<?= $row['id_pesanan']; ?>" class="btn btn-info btn-sm" title="Detail pesanan">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Pesanan Terbaru -->
                        <div class="section-card">
                            <div class="section-header">
                                <h3 class="section-title">
                                    <i class="fas fa-history"></i>
                                    Pesanan Terbaru
                                </h3>
                            </div>
                            <div class="section-body">
                                <?php
                                try {
                                    $stmt = $pdo->query("SELECT p.*, pl.nama as nama_pelanggan, u.nama_lengkap as nama_pegawai 
                                                        FROM data_pesanan p 
                                                        LEFT JOIN data_pelanggan pl ON p.id_pelanggan = pl.id_pelanggan 
                                                        LEFT JOIN users u ON p.id_karyawan = u.id_user 
                                                        ORDER BY p.created_at DESC LIMIT 6");
                                    $recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                    if (count($recent_orders) > 0): ?>
                                    <div class="table-container">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th class="no-urut">No</th>
                                                    <th>ID Pesanan</th>
                                                    <th>Pelanggan</th>
                                                    <th>Jenis Pakaian</th>
                                                    <th>Tanggal</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php $no = 1; ?>
                                                <?php foreach ($recent_orders as $row): ?>
                                                <?php
                                                $status_class = match($row['status_pesanan']) {
                                                    'belum' => 'status-belum',
                                                    'dalam_proses' => 'status-dalam_proses',
                                                    'selesai' => 'status-selesai',
                                                    default => 'status-belum',
                                                };
                                                $status_text = match($row['status_pesanan']) {
                                                    'belum' => 'Belum',
                                                    'dalam_proses' => 'Proses',
                                                    'selesai' => 'Selesai',
                                                    default => ucfirst($row['status_pesanan']),
                                                };
                                                ?>
                                                <tr>
                                                    <td class="no-urut"><?= $no++; ?></td>
                                                    <td><strong>#<?= htmlspecialchars($row['id_pesanan']); ?></strong></td>
                                                    <td><?= htmlspecialchars($row['nama_pelanggan']); ?></td>
                                                    <td><?= htmlspecialchars($row['jenis_pakaian'] ?? '-'); ?></td>
                                                    <td><?= date('d/m/Y', strtotime($row['tgl_pesanan'])); ?></td>
                                                    <td><span class="status-badge <?= $status_class; ?>"><?= $status_text; ?></span></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php else: ?>
                                    <div class="empty-state">
                                        <i class="fas fa-inbox"></i>
                                        <p>Belum ada pesanan</p>
                                    </div>
                                    <?php endif;
                                } catch (PDOException $e) {
                                    echo '<div class="alert alert-danger">Error loading recent orders: ' . $e->getMessage() . '</div>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="right-column">
                        <!-- Quick Actions hanya untuk Admin -->
                        <?php if ($_SESSION['role'] == 'admin'): ?>
                        <div class="section-card">
                            <div class="section-header">
                                <h3 class="section-title">
                                    <i class="fas fa-bolt"></i>
                                    Quick Actions
                                </h3>
                            </div>
                            <div class="section-body">
                                <div class="quick-actions-grid">
                                    <a href="pelanggan/tambahpelanggan.php" class="quick-action-card">
                                        <div class="action-icon">
                                            <i class="fas fa-user-plus"></i>
                                        </div>
                                        <div class="action-title">Tambah Pelanggan</div>
                                        <div class="action-desc">Input data baru</div>
                                    </a>
                                    
                                    <a href="pesanan/tambahpesanan.php" class="quick-action-card">
                                        <div class="action-icon">
                                            <i class="fas fa-plus"></i>
                                        </div>
                                        <div class="action-title">Buat Pesanan</div>
                                        <div class="action-desc">Pesanan baru</div>
                                    </a>
                                    
                                    <a href="pesanan/pesanan.php" class="quick-action-card">
                                        <div class="action-icon">
                                            <i class="fas fa-list"></i>
                                        </div>
                                        <div class="action-title">Semua Pesanan</div>
                                        <div class="action-desc">Lihat semua</div>
                                    </a>
                                    
                                    <a href="laporan/laporan.php" class="quick-action-card">
                                        <div class="action-icon">
                                            <i class="fas fa-chart-bar"></i>
                                        </div>
                                        <div class="action-title">Laporan</div>
                                        <div class="action-desc">Analisis data</div>
                                    </a>
                                    
                                    <a href="transaksi/transaksi.php" class="quick-action-card">
                                        <div class="action-icon">
                                            <i class="fas fa-money-bill-wave"></i>
                                        </div>
                                        <div class="action-title">Transaksi</div>
                                        <div class="action-desc">Keluar masuk uang</div>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <?php include 'includes/footer.php'; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Auto submit form status ketika diubah
        document.addEventListener('DOMContentLoaded', function() {
            const statusSelects = document.querySelectorAll('.status-select');
            
            statusSelects.forEach(select => {
                select.addEventListener('change', function() {
                    this.disabled = true;
                    this.style.opacity = '0.7';
                    
                    // Show loading indicator
                    const originalText = this.options[this.selectedIndex].text;
                    this.options[this.selectedIndex].text = 'Menyimpan...';
                    
                    setTimeout(() => {
                        this.form.submit();
                    }, 500);
                });
            });
            
            // Highlight stat cards on hover
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                    this.style.boxShadow = '0 6px 15px rgba(0,0,0,0.15)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = '';
                    this.style.boxShadow = '0 2px 8px rgba(0,0,0,0.08)';
                });
            });
            
            // Quick action cards animation
            const quickActionCards = document.querySelectorAll('.quick-action-card');
            quickActionCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = '';
                });
            });
        });
    </script>
</body>
</html>