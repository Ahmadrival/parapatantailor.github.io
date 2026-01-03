<?php
// laporan/laporan.php
require_once '../config/database.php';
check_login();
check_role(['admin', 'pemilik']);

// Tanggal default untuk filter
$start_date = isset($_GET['start_date']) ? clean_input($_GET['start_date']) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? clean_input($_GET['end_date']) : date('Y-m-t');
$jenis_laporan = isset($_GET['jenis_laporan']) ? clean_input($_GET['jenis_laporan']) : 'keuangan';

// Data untuk Laporan Keuangan
try {
    // Total Pendapatan dari transaksi pembayaran pesanan
    $sql_pendapatan = "SELECT COALESCE(SUM(jumlah_bayar), 0) as total_pendapatan 
                      FROM data_transaksi 
                      WHERE DATE(created_at) BETWEEN ? AND ?";
    $total_pendapatan = getSingle($sql_pendapatan, [$start_date, $end_date])['total_pendapatan'];
    
    // Total Pengeluaran
    $sql_pengeluaran = "SELECT COALESCE(SUM(jumlah_pengeluaran), 0) as total_pengeluaran 
                       FROM data_pengeluaran 
                       WHERE DATE(tgl_pengeluaran) BETWEEN ? AND ?";
    $total_pengeluaran = getSingle($sql_pengeluaran, [$start_date, $end_date])['total_pengeluaran'];
    
    // Total Uang Masuk (hanya dari pendapatan pesanan)
    $total_uang_masuk = $total_pendapatan;
    
    // Laba/Rugi
    $laba_rugi = $total_uang_masuk - $total_pengeluaran;
    
    // Detail pengeluaran
    $sql_detail_pengeluaran = "SELECT * FROM data_pengeluaran 
                              WHERE DATE(tgl_pengeluaran) BETWEEN ? AND ?
                              ORDER BY tgl_pengeluaran DESC";
    $pengeluaran = getAll($sql_detail_pengeluaran, [$start_date, $end_date]);
    
    // Data Pemasukan dari transaksi pembayaran (DITAMBAHKAN)
    $sql_pemasukan = "SELECT t.*, pel.nama as nama_pelanggan, p.total_harga, p.jenis_pakaian,
                             pel.alamat, pel.no_hp, p.jumlah_bayar as total_bayar_pesanan,
                             p.sisa_bayar
                      FROM data_transaksi t
                      LEFT JOIN data_pesanan p ON t.id_pesanan = p.id_pesanan
                      LEFT JOIN data_pelanggan pel ON p.id_pelanggan = pel.id_pelanggan
                      WHERE DATE(t.created_at) BETWEEN ? AND ?
                      ORDER BY t.created_at DESC";
    $pemasukan = getAll($sql_pemasukan, [$start_date, $end_date]);
    
    // Hitung total pemasukan dari transaksi
    $sql_total_pemasukan = "SELECT COALESCE(SUM(t.jumlah_bayar), 0) as total_pemasukan
                           FROM data_transaksi t
                           WHERE DATE(t.created_at) BETWEEN ? AND ?";
    $total_pemasukan_transaksi = getSingle($sql_total_pemasukan, [$start_date, $end_date])['total_pemasukan'];
    
} catch (PDOException $e) {
    $total_pendapatan = 0;
    $total_pengeluaran = 0;
    $total_uang_masuk = 0;
    $laba_rugi = 0;
    $pengeluaran = [];
    $pemasukan = [];
    $total_pemasukan_transaksi = 0;
}

// Data untuk Laporan Pesanan
try {
    $sql_pesanan = "SELECT p.*, pel.nama as nama_pelanggan, u.nama_lengkap as nama_karyawan,
                           COUNT(*) as total_pesanan,
                           SUM(p.total_harga) as total_omzet
                    FROM data_pesanan p
                    LEFT JOIN data_pelanggan pel ON p.id_pelanggan = pel.id_pelanggan
                    LEFT JOIN users u ON p.id_karyawan = u.id_user
                    WHERE DATE(p.tgl_pesanan) BETWEEN ? AND ?
                    GROUP BY p.id_pesanan
                    ORDER BY p.tgl_pesanan DESC";
    $pesanan = getAll($sql_pesanan, [$start_date, $end_date]);
    
    // Statistik pesanan
    $sql_stat_pesanan = "SELECT 
                         COUNT(*) as total_pesanan,
                         SUM(total_harga) as total_omzet,
                         AVG(total_harga) as rata_rata,
                         status_pesanan,
                         COUNT(*) as jumlah_status
                         FROM data_pesanan 
                         WHERE DATE(tgl_pesanan) BETWEEN ? AND ?
                         GROUP BY status_pesanan";
    $stat_pesanan = getAll($sql_stat_pesanan, [$start_date, $end_date]);
    
} catch (PDOException $e) {
    $pesanan = [];
    $stat_pesanan = [];
}

// Data untuk Laporan Pelanggan
try {
    $sql_pelanggan = "SELECT pel.*, 
                             COUNT(p.id_pesanan) as total_pesanan,
                             SUM(p.total_harga) as total_belanja,
                             AVG(p.total_harga) as rata_rata_belanja
                      FROM data_pelanggan pel
                      LEFT JOIN data_pesanan p ON pel.id_pelanggan = p.id_pelanggan
                      WHERE (p.tgl_pesanan IS NULL OR DATE(p.tgl_pesanan) BETWEEN ? AND ?)
                      GROUP BY pel.id_pelanggan
                      ORDER BY total_belanja DESC";
    $pelanggan = getAll($sql_pelanggan, [$start_date, $end_date]);
    
} catch (PDOException $e) {
    $pelanggan = [];
}

$page_title = "Data Laporan";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Laporan - SIM Parapatan Tailor</title>
     <link rel="shortcut icon" href="../assets/images/logoterakhir.png" type="image/x-icon" />

    <!-- Bootstrap 5.3.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../includes/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* CSS Custom yang konsisten dengan transaksi.php */
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
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            padding: 15px;
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
        
        /* Table Styles */
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
        
        .search-box {
            margin-bottom: 1rem;
        }
        
        .search-box input, .search-box select {
            padding: 0.5rem 0.8rem;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.75rem;
            transition: all 0.3s ease;
            background: white;
        }
        
        .search-box input:focus, .search-box select:focus {
            outline: none;
            border-color: #4f46e5;
            box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1);
            background: #fafbff;
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
            border-radius: 10px;
            box-shadow: 0 1px 8px rgba(0,0,0,0.08);
            border-left: 3px solid #4f46e5;
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
        
        .filter-section {
            background: linear-gradient(135deg, #f8faff 0%, #f0f4ff 100%);
            padding: 1.2rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            border: 1px solid #e0e7ff;
            box-shadow: 0 1px 8px rgba(0,0,0,0.05);
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
            color: #374151;
            font-size: 0.75rem;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 2rem;
            gap: 0.4rem;
            flex-wrap: wrap;
        }
        
        .pagination-info {
            margin: 0 1.2rem;
            color: #6b7280;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .page-item {
            display: inline-block;
        }
        
        .page-link {
            padding: 0.5rem 0.8rem;
            border: 1px solid #e5e7eb;
            background: white;
            color: #4f46e5;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
            min-width: 40px;
            text-align: center;
            font-size: 0.75rem;
        }
        
        .page-link:hover {
            background: #4f46e5;
            color: white;
            border-color: #4f46e5;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(79, 70, 229, 0.2);
        }
        
        .page-item.active .page-link {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            border-color: #4f46e5;
            box-shadow: 0 2px 8px rgba(79, 70, 229, 0.3);
        }
        
        .page-item.disabled .page-link {
            color: #9ca3af;
            pointer-events: none;
            background: #f9fafb;
            border-color: #e5e7eb;
        }
        
        /* Search info */
        .search-info {
            background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);
            padding: 0.8rem 1.2rem;
            border-radius: 8px;
            margin-bottom: 1.2rem;
            border-left: 3px solid #4f46e5;
            font-size: 0.75rem;
            color: #374151;
            font-weight: 500;
        }
        
        .search-info strong {
            color: #4f46e5;
        }

        /* Status Badges */
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            min-width: 80px;
            text-align: center;
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

        /* Modal Styles */
        .modal-content {
            border: none;
            border-radius: 12px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .modal-header {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
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

        .modal-body {
            padding: 1.2rem;
            font-size: 0.8rem;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
            font-size: 0.8rem;
        }

        /* Tabs */
        .nav-tabs {
            border-bottom: 2px solid #e0e7ff;
            margin-bottom: 1.5rem;
            background: white;
            border-radius: 10px;
            padding: 0.5rem;
            box-shadow: 0 1px 8px rgba(0,0,0,0.08);
        }

        .nav-tabs .nav-link {
            border: none;
            color: #6b7280;
            font-weight: 500;
            padding: 0.8rem 1.2rem;
            border-radius: 8px;
            margin-right: 0.5rem;
            transition: all 0.2s;
            font-size: 0.8rem;
        }

        .nav-tabs .nav-link:hover {
            color: #374151;
            background: #f8faff;
        }

        .nav-tabs .nav-link.active {
            color: white;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(79, 70, 229, 0.3);
        }

        /* Export Buttons */
        .export-section {
            background: linear-gradient(135deg, #f0fff4 0%, #e6fffa 100%);
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            border: 1px solid #d1fae5;
            box-shadow: 0 1px 8px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        .export-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #065f46;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .export-header i {
            color: #059669;
        }

        .export-buttons {
            display: flex;
            gap: 0.8rem;
            align-items: center;
            margin-left: -20px;
        }

        .btn-export {
            padding: 0.4rem 0.8rem;
            font-size: 0.7rem;
            border-radius: 4px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            transition: all 0.2s ease;
            border: 1px solid;
            position: relative;
        }

        .btn-pdf {
            background: #dc3545;
            color: white;
            border-color: #dc3545;
        }

        .btn-pdf:hover {
            background: #c82333;
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 2px 6px rgba(220, 53, 69, 0.3);
        }

        .btn-excel {
            background: #198754;
            color: white;
            border-color: #198754;
        }

        .btn-excel:hover {
            background: #157347;
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 2px 6px rgba(25, 135, 84, 0.3);
        }

        /* Tooltip Styles */
        .tooltip-container {
            position: relative;
            display: inline-block;
        }

        .tooltip-text {
            visibility: hidden;
            width: 200px;
            background-color: #1f2937;
            color: #fff;
            text-align: center;
            border-radius: 8px;
            padding: 10px 12px;
            position: absolute;
            z-index: 9999;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            opacity: 0;
            transition: opacity 0.3s, visibility 0.3s;
            font-size: 0.7rem;
            line-height: 1.4;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            border: 1px solid #374151;
            pointer-events: none;
        }

        .tooltip-text::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -6px;
            border-width: 6px;
            border-style: solid;
            border-color: #1f2937 transparent transparent transparent;
        }

        .tooltip-container:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .table {
                display: block;
                overflow-x: auto;
                font-size: 0.7rem;
            }
            
            .search-box input, .search-box select {
                width: 100%;
            }
            
            .header-actions {
                flex-direction: column;
                gap: 0.8rem;
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
            
            .no-urut {
                width: 35px;
            }
            
            .filter-form {
                flex-direction: column;
                align-items: stretch;
                gap: 0.8rem;
            }
            
            .filter-group {
                min-width: auto;
            }
            
            .pagination {
                flex-direction: column;
                gap: 0.6rem;
            }
            
            .container {
                padding: 12px;
                margin: 0.8rem;
            }

            .nav-tabs .nav-link {
                padding: 0.6rem 0.8rem;
                font-size: 0.75rem;
                margin-bottom: 0.5rem;
            }

            .export-section {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .export-buttons {
                width: 100%;
                justify-content: space-between;
                margin-left: 0;
            }

            .tooltip-text {
                width: 160px;
                font-size: 0.65rem;
                padding: 8px 10px;
            }
        }

        @media (max-width: 480px) {
            .header-actions h3 {
                font-size: 1rem;
            }
            
            .btn {
                padding: 0.3rem 0.5rem;
                font-size: 0.7rem;
            }

            .nav-tabs {
                flex-direction: column;
            }

            .nav-tabs .nav-link {
                margin-right: 0;
                margin-bottom: 0.5rem;
                text-align: center;
            }

            .export-buttons {
                flex-direction: column;
                width: 100%;
                gap: 0.5rem;
            }

            .btn-export {
                width: 100%;
                justify-content: center;
            }

            .tooltip-text {
                width: 140px;
                font-size: 0.6rem;
                padding: 6px 8px;
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
        
        /* Form controls styling konsisten */
        .form-control {
            width: 100%;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 8px;
            transition: all 0.2s ease-in-out;
            font-size: 0.75rem;
        }
        
        .form-control:focus {
            border-color: #007BFF;
            box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
        }

        /* Tambahan untuk garis tabel berwarna */
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

        /* Center modal content */
        .modal-dialog-centered {
            display: flex;
            align-items: center;
            min-height: calc(100% - 1rem);
        }

        .modal-content {
            margin: auto;
        }

        /* Compact table cell styles */
        .table td, .table th {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Ensure no horizontal scroll */
        body, .main-content, .content-body, .container {
            max-width: 100%;
            overflow-x: hidden;
        }

        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* Table footer styling */
        .table tfoot tr {
            background: linear-gradient(135deg, #f8faff 0%, #f0f4ff 100%);
            font-weight: 600;
        }

        .table tfoot td {
            border-top: 2px solid #4f46e5;
            font-size: 0.8rem;
        }

        /* Amount Column Styles */
        .amount-column {
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .amount-value {
            font-weight: 600;
            font-size: 0.7rem;
        }

        .amount-value.positive {
            color: #059669;
        }

        .amount-value.negative {
            color: #dc2626;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/header.php'; ?>

    <div class="main-content">
        <div class="content-body">
            <div class="container">
                <h2 class="my-3">Data Laporan</h2>

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

                <!-- Filter Section -->
                <div class="filter-section">
                    <form method="GET" class="filter-form">
                        <input type="hidden" name="jenis_laporan" value="<?= $jenis_laporan; ?>">
                        <div class="filter-group">
                            <label for="start_date">Tanggal Mulai:</label>
                            <input type="date" id="start_date" name="start_date" 
                                   value="<?= $start_date; ?>" 
                                   class="form-control">
                        </div>
                        <div class="filter-group">
                            <label for="end_date">Tanggal Selesai:</label>
                            <input type="date" id="end_date" name="end_date" 
                                   value="<?= $end_date; ?>" 
                                   class="form-control">
                        </div>
                        <div class="filter-group">
                            <label>&nbsp;</label>
                            <div style="display: flex; gap: 0.4rem;">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter"></i> Terapkan Filter
                                </button>
                                <a href="laporan.php" class="btn btn-secondary">
                                    <i class="fas fa-refresh"></i> Reset
                                </a>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Tabs Navigation -->
                <ul class="nav nav-tabs" id="laporanTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= $jenis_laporan == 'keuangan' ? 'active' : ''; ?>" 
                                onclick="window.location.href='?jenis_laporan=keuangan&start_date=<?= $start_date; ?>&end_date=<?= $end_date; ?>'"
                                type="button">
                            <i class="fas fa-chart-line"></i> Laporan Keuangan
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= $jenis_laporan == 'pesanan' ? 'active' : ''; ?>" 
                                onclick="window.location.href='?jenis_laporan=pesanan&start_date=<?= $start_date; ?>&end_date=<?= $end_date; ?>'"
                                type="button">
                            <i class="fas fa-shopping-bag"></i> Laporan Pesanan
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= $jenis_laporan == 'pelanggan' ? 'active' : ''; ?>" 
                                onclick="window.location.href='?jenis_laporan=pelanggan&start_date=<?= $start_date; ?>&end_date=<?= $end_date; ?>'"
                                type="button">
                            <i class="fas fa-users"></i> Laporan Pelanggan
                        </button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="laporanTabContent">
                    
                    <!-- Laporan Keuangan -->
                    <?php if ($jenis_laporan == 'keuangan'): ?>
                    <div class="tab-pane fade show active">
                        <!-- Export Section untuk Laporan Keuangan -->
                        <div class="export-section">
                            <div class="export-header">
                                <i class="fas fa-chart-line"></i>
                                <span>Laporan Keuangan</span>
                            </div>
                            <div class="export-buttons">
                                <div class="tooltip-container">
                                    <a href="export_pdf_keuangan.php?type=keuangan&start_date=<?= $start_date; ?>&end_date=<?= $end_date; ?>" 
                                       class="btn-export btn-pdf" target="_blank">
                                        <i class="fas fa-file-pdf"></i> PDF
                                    </a>
                                    <span class="tooltip-text">Untuk cetak & arsip dokumen laporan keuangan</span>
                                </div>
                                <div class="tooltip-container">
                                    <a href="export_excel_keuangan.php?type=keuangan&start_date=<?= $start_date; ?>&end_date=<?= $end_date; ?>" 
                                       class="btn-export btn-excel" target="_blank">
                                        <i class="fas fa-file-excel"></i> Excel
                                    </a>
                                    <span class="tooltip-text">Untuk analisis & olah data keuangan</span>
                                </div>
                            </div>
                        </div>

                        <!-- Data Pemasukan dari Transaksi Pembayaran (DITAMBAHKAN) -->
                        <div class="header-actions">
                            <h3>Data Pemasukan (Transaksi Pembayaran)</h3>
                        </div>

                        <?php if (empty($pemasukan)): ?>
                            <div class="empty-state">
                                <div><i class="fas fa-money-bill-wave"></i></div>
                                <p style="font-size: 0.9rem; margin-bottom: 0.4rem;">Belum ada data pemasukan dari transaksi pembayaran</p>
                                <p style="color: #6b7280; font-size: 0.75rem; margin-top: 0.4rem;">
                                    Periode: <?= date('d/m/Y', strtotime($start_date)); ?> - <?= date('d/m/Y', strtotime($end_date)); ?>
                                </p>
                            </div>
                        <?php else: ?>
                        <div class="card">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th class="no-urut">No</th>
                                            <th>Nama Pelanggan</th>
                                            <th>Tanggal Transaksi</th>
                                            <th>Jumlah Pembayaran</th>
                                            <th>Total Harga Pesanan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $no = 1; ?>
                                        <?php foreach ($pemasukan as $p): ?>
                                        <tr>
                                            <td class="no-urut"><?= $no++; ?></td>
                                            <td style="font-weight: 600; color: #1f2937;">
                                                <?= htmlspecialchars($p['nama_pelanggan'] ?? '-'); ?>
                                            </td>
                                            <td><?= date('d/m/Y H:i', strtotime($p['created_at'])); ?></td>
                                            <td style="font-weight: 600; color: #059669;">
                                                Rp <?= number_format($p['jumlah_bayar'] ?? 0, 0, ',', '.'); ?>
                                            </td>
                                            <td style="font-weight: 600; color: #1f2937;">
                                                Rp <?= number_format($p['total_harga'] ?? 0, 0, ',', '.'); ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="3" style="text-align: right; font-weight: 600;">
                                                Total Pemasukan dari Transaksi:
                                            </td>
                                            <td style="font-weight: 700; color: #059669;">
                                                Rp <?= number_format($total_pemasukan_transaksi, 0, ',', '.'); ?>
                                            </td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Data Pengeluaran -->
                        <div class="header-actions">
                            <h3>Data Pengeluaran</h3>
                        </div>

                        <?php if (empty($pengeluaran)): ?>
                            <div class="empty-state">
                                <div><i class="fas fa-receipt"></i></div>
                                <p style="font-size: 0.9rem; margin-bottom: 0.4rem;">Belum ada data pengeluaran</p>
                                <p style="color: #6b7280; font-size: 0.75rem; margin-top: 0.4rem;">
                                    Periode: <?= date('d/m/Y', strtotime($start_date)); ?> - <?= date('d/m/Y', strtotime($end_date)); ?>
                                </p>
                            </div>
                        <?php else: ?>
                        <div class="card">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th class="no-urut">No</th>
                                            <th>Tanggal</th>
                                            <th>Kategori</th>
                                            <th>Jumlah Pengeluaran</th>
                                            <th>Keterangan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $no = 1; ?>
                                        <?php foreach ($pengeluaran as $p): ?>
                                        <tr>
                                            <td class="no-urut"><?= $no++; ?></td>
                                            <td><?= date('d/m/Y', strtotime($p['tgl_pengeluaran'])); ?></td>
                                            <td>
                                                <span class="badge bg-secondary"><?= htmlspecialchars($p['kategori_pengeluaran']); ?></span>
                                            </td>
                                            <td style="font-weight: 600; color: #dc2626;">
                                                <div class="amount-column">
                                                    <span class="amount-value negative">- Rp <?= number_format($p['jumlah_pengeluaran'], 0, ',', '.'); ?></span>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($p['keterangan']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="3" style="text-align: right; font-weight: 600;">Total Pengeluaran:</td>
                                            <td style="font-weight: 700; color: #dc2626;">
                                                Rp <?= number_format($total_pengeluaran, 0, ',', '.'); ?>
                                            </td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Ringkasan Keuangan -->
                        <div class="header-actions">
                            <h3>Ringkasan Keuangan</h3>
                        </div>
                        <div class="card">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Keterangan</th>
                                            <th>Jumlah</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td style="font-weight: 600;">Total Pemasukan dari Transaksi Pembayaran</td>
                                            <td style="font-weight: 600; color: #059669;">
                                                Rp <?= number_format($total_pemasukan_transaksi, 0, ',', '.'); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="font-weight: 600;">Total Pengeluaran</td>
                                            <td style="font-weight: 600; color: #dc2626;">
                                                Rp <?= number_format($total_pengeluaran, 0, ',', '.'); ?>
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td style="font-weight: 700; font-size: 0.9rem;">LABA / RUGI BERSIH</td>
                                            <td style="font-weight: 800; font-size: 0.9rem; color: <?= $laba_rugi >= 0 ? '#059669' : '#dc2626'; ?>;">
                                                Rp <?= number_format($laba_rugi, 0, ',', '.'); ?>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Laporan Pesanan -->
                    <?php elseif ($jenis_laporan == 'pesanan'): ?>
                    <div class="tab-pane fade show active">
                        <!-- Export Section untuk Laporan Pesanan -->
                        <div class="export-section">
                            <div class="export-header">
                                <i class="fas fa-shopping-bag"></i>
                                <span>Laporan Pesanan</span>
                            </div>
                            <div class="export-buttons">
                                <div class="tooltip-container">
                                    <a href="export_pdf.php?type=pesanan&start_date=<?= $start_date; ?>&end_date=<?= $end_date; ?>" 
                                       class="btn-export btn-pdf" target="_blank">
                                        <i class="fas fa-file-pdf"></i> PDF
                                    </a>
                                    <span class="tooltip-text">Untuk cetak & arsip dokumen pesanan</span>
                                </div>
                                <div class="tooltip-container">
                                    <a href="export_excel.php?type=pesanan&start_date=<?= $start_date; ?>&end_date=<?= $end_date; ?>" 
                                       class="btn-export btn-excel" target="_blank">
                                        <i class="fas fa-file-excel"></i> Excel
                                    </a>
                                    <span class="tooltip-text">Untuk analisis & monitoring pesanan</span>
                                </div>
                            </div>
                        </div>

                        <?php if (empty($pesanan)): ?>
                            <div class="empty-state">
                                <div><i class="fas fa-inbox"></i></div>
                                <p style="font-size: 0.9rem; margin-bottom: 0.4rem;">Belum ada data pesanan</p>
                                <p style="color: #6b7280; font-size: 0.75rem; margin-top: 0.4rem;">
                                    Periode: <?= date('d/m/Y', strtotime($start_date)); ?> - <?= date('d/m/Y', strtotime($end_date)); ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="card">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th class="no-urut">No</th>
                                                <th>ID Pesanan</th>
                                                <th>Pelanggan</th>
                                                <th>Karyawan</th>
                                                <th>Jenis Pakaian</th>
                                                <th>Tanggal Pesan</th>
                                                <th>Total Harga</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $no = 1; ?>
                                            <?php foreach ($pesanan as $p): ?>
                                            <tr>
                                                <td class="no-urut"><?= $no++; ?></td>
                                                <td style="font-weight: 600; color: #4f46e5;">#<?= $p['id_pesanan']; ?></td>
                                                <td style="font-weight: 600; color: #1f2937;"><?= htmlspecialchars($p['nama_pelanggan']); ?></td>
                                                <td><?= htmlspecialchars($p['nama_karyawan']); ?></td>
                                                <td><?= htmlspecialchars($p['jenis_pakaian']); ?></td>
                                                <td><?= date('d/m/Y', strtotime($p['tgl_pesanan'])); ?></td>
                                                <td style="font-weight: 600; color: #059669;">
                                                    Rp <?= number_format($p['total_harga'], 0, ',', '.'); ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $status_class = '';
                                                    $status_text = '';
                                                    switch($p['status_pesanan']) {
                                                        case 'belum':
                                                            $status_class = 'belum';
                                                            $status_text = 'Belum Diproses';
                                                            break;
                                                        case 'dalam_proses':
                                                            $status_class = 'dalam_proses';
                                                            $status_text = 'Dalam Proses';
                                                            break;
                                                        case 'selesai':
                                                            $status_class = 'selesai';
                                                            $status_text = 'Selesai';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="status-badge <?= $status_class; ?>"><?= $status_text; ?></span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Laporan Pelanggan -->
                    <?php elseif ($jenis_laporan == 'pelanggan'): ?>
                    <div class="tab-pane fade show active">
                        <!-- Export Section untuk Laporan Pelanggan -->
                        <div class="export-section">
                            <div class="export-header">
                                <i class="fas fa-users"></i>
                                <span>Laporan Pelanggan</span>
                            </div>
                            <div class="export-buttons">
                                <div class="tooltip-container">
                                    <a href="export_pdf.php?type=pelanggan&start_date=<?= $start_date; ?>&end_date=<?= $end_date; ?>" 
                                       class="btn-export btn-pdf" target="_blank">
                                        <i class="fas fa-file-pdf"></i> PDF
                                    </a>
                                    <span class="tooltip-text">Untuk cetak & arsip data pelanggan</span>
                                </div>
                                <div class="tooltip-container">
                                    <a href="export_excel.php?type=pelanggan&start_date=<?= $start_date; ?>&end_date=<?= $end_date; ?>" 
                                       class="btn-export btn-excel" target="_blank">
                                        <i class="fas fa-file-excel"></i> Excel
                                    </a>
                                    <span class="tooltip-text">Untuk analisis & segmentasi pelanggan</span>
                                </div>
                            </div>
                        </div>

                        <?php if (empty($pelanggan)): ?>
                            <div class="empty-state">
                                <div><i class="fas fa-users"></i></div>
                                <p style="font-size: 0.9rem; margin-bottom: 0.4rem;">Belum ada data pelanggan</p>
                                <p style="color: #6b7280; font-size: 0.75rem; margin-top: 0.4rem;">
                                    Periode: <?= date('d/m/Y', strtotime($start_date)); ?> - <?= date('d/m/Y', strtotime($end_date)); ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="card">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th class="no-urut">No</th>
                                                <th>Nama Pelanggan</th>
                                                <th>Telepon</th>
                                                <th>Alamat</th>
                                                <th>Total Pesanan</th>
                                                <th>Total Belanja</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $no = 1; ?>
                                            <?php foreach ($pelanggan as $plg): ?>
                                            <tr>
                                                <td class="no-urut"><?= $no++; ?></td>
                                                <td style="font-weight: 600; color: #1f2937;"><?= htmlspecialchars($plg['nama']); ?></td>
                                                <td><?= htmlspecialchars($plg['no_hp']); ?></td>
                                                <td><?= htmlspecialchars($plg['alamat']); ?></td>
                                                <td style="text-align: center;">
                                                    <span class="badge bg-primary"><?= $plg['total_pesanan'] ?? 0; ?></span>
                                                </td>
                                                <td style="font-weight: 600; color: #059669;">
                                                    Rp <?= number_format($plg['total_belanja'] ?? 0, 0, ',', '.'); ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <?php include '../includes/footer.php'; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Auto submit form when date changes
        document.getElementById('start_date').addEventListener('change', function() {
            this.form.submit();
        });

        document.getElementById('end_date').addEventListener('change', function() {
            this.form.submit();
        });

        // Highlight table rows on hover
        document.addEventListener('DOMContentLoaded', function() {
            const tables = document.querySelectorAll('.table');
            tables.forEach(table => {
                const tableRows = table.querySelectorAll('tbody tr');
                tableRows.forEach(row => {
                    row.addEventListener('mouseenter', function() {
                        this.style.backgroundColor = '#f8faff';
                    });
                    row.addEventListener('mouseleave', function() {
                        this.style.backgroundColor = '';
                    });
                });
            });
        });

        // Tambahan JavaScript untuk tooltip yang lebih baik
        document.addEventListener('DOMContentLoaded', function() {
            const tooltipContainers = document.querySelectorAll('.tooltip-container');
            
            tooltipContainers.forEach(container => {
                const tooltip = container.querySelector('.tooltip-text');
                
                container.addEventListener('mouseenter', function() {
                    // Pastikan tooltip memiliki z-index tertinggi
                    tooltip.style.zIndex = '9999';
                });
            });
        });
    </script>
</body>
</html>