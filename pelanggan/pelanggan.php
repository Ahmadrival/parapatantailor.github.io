<?php
// pelanggan/pelanggan.php
require_once '../config/database.php';
check_login();
check_role(['admin', 'pemilik','pegawai']);

// Hapus pelanggan
if (isset($_GET['hapus'])) {
    $id = clean_input($_GET['hapus']);
    
    try {
        // Cek apakah pelanggan memiliki pesanan
        $check_sql = "SELECT COUNT(*) as total_pesanan FROM data_pesanan WHERE id_pelanggan = ?";
        $check_stmt = executeQuery($check_sql, [$id]);
        $has_orders = $check_stmt->fetchColumn();
        
        if ($has_orders > 0) {
            $_SESSION['error'] = "❌ Tidak dapat menghapus pelanggan karena memiliki pesanan terkait";
        } else {
            $sql = "DELETE FROM data_pelanggan WHERE id_pelanggan = ?";
            $stmt = executeQuery($sql, [$id]);
            
            $_SESSION['success'] = "✅ Pelanggan berhasil dihapus";
            log_activity("Menghapus pelanggan ID: $id");
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "❌ Error: " . $e->getMessage();
    }
    header("Location: pelanggan.php");
    exit();
}

// Konfigurasi pagination
$limit = 10; // Jumlah data per halaman
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Filter pencarian dan tanggal
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$filter_tanggal = isset($_GET['tanggal']) ? clean_input($_GET['tanggal']) : '';

// Query dasar dengan filter
$sql_where = "";
$params = [];
$where_conditions = [];

if (!empty($search)) {
    $where_conditions[] = "(nama LIKE ? OR no_hp LIKE ? OR alamat LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($filter_tanggal)) {
    $where_conditions[] = "DATE(created_at) = ?";
    $params[] = $filter_tanggal;
}

if (count($where_conditions) > 0) {
    $sql_where = "WHERE " . implode(" AND ", $where_conditions);
}

// Hitung total data untuk pagination
try {
    $sql_count = "SELECT COUNT(*) as total FROM data_pelanggan $sql_where";
    $stmt_count = executeQuery($sql_count, $params);
    $total_pelanggan = $stmt_count->fetchColumn();
    $total_pages = ceil($total_pelanggan / $limit);
} catch (PDOException $e) {
    $total_pelanggan = 0;
    $total_pages = 1;
}

// Ambil data pelanggan dengan PDO - diurutkan berdasarkan nama A-Z dengan pagination
try {
    $sql = "SELECT * FROM data_pelanggan $sql_where ORDER BY nama ASC LIMIT $limit OFFSET $offset";
    $pelanggan = getAll($sql, $params);
} catch (PDOException $e) {
    $_SESSION['error'] = "❌ Error loading data: " . $e->getMessage();
    $pelanggan = [];
}

// Set page title untuk header
$page_title = "Data Pelanggan";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Pelanggan - SIM Parapatan Tailor</title>
     <link rel="shortcut icon" href="../assets/images/logoterakhir.png" type="image/x-icon" />

    <!-- Bootstrap 5.3.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../includes/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* CSS Custom yang konsisten dengan editpelanggan.php */
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
            padding: 15px;
            background-color: #f8f9fa;
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
        
        .stats-card {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 50%, #a855f7 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            text-align: center;
            box-shadow: 0 6px 20px rgba(79, 70, 229, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .stats-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: pulse 4s ease-in-out infinite;
        }
        
        .stats-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.4rem;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.2);
            position: relative;
            z-index: 2;
        }
        
        .stats-label {
            font-size: 0.85rem;
            opacity: 0.9;
            font-weight: 500;
            position: relative;
            z-index: 2;
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

        /* Export Buttons - Baru: Kecil dan di sebelah button filter */
        .export-buttons {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .btn-export-small {
            padding: 0.4rem 0.6rem;
            font-size: 0.7rem;
            border-radius: 6px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            transition: all 0.2s ease;
            border: 1px solid;
            position: relative;
        }

        .btn-pdf-small {
            background: #dc3545;
            color: white;
            border-color: #dc3545;
        }

        .btn-pdf-small:hover {
            background: #c82333;
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 2px 6px rgba(220, 53, 69, 0.3);
        }

        .btn-excel-small {
            background: #198754;
            color: white;
            border-color: #198754;
        }

        .btn-excel-small:hover {
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
            width: 180px;
            background-color: #333;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 8px;
            position: absolute;
            z-index: 1000;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 0.7rem;
            font-weight: 400;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .tooltip-text::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: #333 transparent transparent transparent;
        }

        .tooltip-container:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }

        /* Export Label */
        .export-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #374151;
            margin-right: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        /* Modal Delete Confirmation Styling */
        .delete-modal .modal-content {
            border-radius: 12px;
            border: none;
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
            margin: 0 auto;
        }
        
        .delete-modal .modal-header {
            border-bottom: none;
            padding: 1.2rem 1.2rem 0;
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            border-radius: 12px 12px 0 0;
            justify-content: center;
        }
        
        .delete-modal .modal-body {
            padding: 1.2rem;
            text-align: center;
            font-size: 0.85rem;
        }
        
        .delete-modal .modal-footer {
            border-top: none;
            padding: 0 1.2rem 1.2rem;
            justify-content: center;
            gap: 0.8rem;
        }
        
        /* Loading Container */
        .loading-container {
            position: relative;
            width: 70px;
            height: 70px;
            margin: 0 auto 1.5rem;
        }
        
        /* Circle Loading */
        .circle-loading {
            width: 60px;
            height: 60px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #dc3545;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }
        
        /* Checkmark */
        .checkmark {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: block;
            stroke-width: 2;
            stroke: #fff;
            stroke-miterlimit: 10;
            margin: 0 auto;
            box-shadow: inset 0px 0px 0px #dc3545;
            animation: fill .4s ease-in-out .4s forwards, scale .3s ease-in-out .9s both;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            opacity: 0;
        }
        
        .checkmark.show {
            opacity: 1;
        }
        
        .checkmark__circle {
            stroke-dasharray: 166;
            stroke-dashoffset: 166;
            stroke-width: 2;
            stroke-miterlimit: 10;
            stroke: #dc3545;
            fill: none;
            animation: stroke 0.6s cubic-bezier(0.65, 0, 0.45, 1) forwards;
        }
        
        .checkmark__check {
            transform-origin: 50% 50%;
            stroke-dasharray: 48;
            stroke-dashoffset: 48;
            animation: stroke 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.8s forwards;
        }
        
        /* Line Loading - Dipindah ke bawah */
        .line-loading-container {
            width: 100%;
            height: 2px;
            background: #f3f3f3;
            border-radius: 2px;
            margin: 1.5rem auto 0;
            overflow: hidden;
            position: relative;
        }
        
        .line-loading {
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, #dc3545, #ef4444, #dc3545);
            animation: lineLoading 2s infinite;
            border-radius: 2px;
        }
        
        /* Success Message */
        .success-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #dc3545;
            margin-bottom: 0.8rem;
            opacity: 0;
            transform: translateY(15px);
            transition: all 0.5s ease 0.5s;
        }
        
        .success-title.show {
            opacity: 1;
            transform: translateY(0);
        }
        
        .redirect-message {
            color: #6c757d;
            font-size: 0.8rem;
            opacity: 0;
            transform: translateY(8px);
            transition: all 0.5s ease 1s;
        }
        
        .redirect-message.show {
            opacity: 1;
            transform: translateY(0);
        }

        /* Warning Icon */
        .warning-icon {
            font-size: 2.5rem;
            color: #dc3545;
            margin-bottom: 0.8rem;
        }

        /* Animations */
        @keyframes spin {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }
        
        @keyframes lineLoading {
            0% { left: -100%; }
            50% { left: 0%; }
            100% { left: 100%; }
        }
        
        @keyframes stroke {
            100% {
                stroke-dashoffset: 0;
            }
        }
        
        @keyframes scale {
            0%, 100% {
                transform: translate(-50%, -50%) scale(1);
            }
            50% {
                transform: translate(-50%, -50%) scale(1.1);
            }
        }
        
        @keyframes fill {
            100% {
                box-shadow: inset 0px 0px 0px 30px #dc3545;
            }
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(15px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
            
            .stats-number {
                font-size: 1.8rem;
            }
            
            .container {
                padding: 12px;
                margin: 0.8rem;
            }

            .export-buttons {
                flex-direction: column;
                align-items: stretch;
                gap: 0.5rem;
                margin-top: 0.8rem;
            }

            .export-label {
                margin-bottom: 0.3rem;
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .header-actions h3 {
                font-size: 1rem;
            }
            
            .stats-card {
                padding: 1.2rem;
            }
            
            .stats-number {
                font-size: 1.6rem;
            }
            
            .btn {
                padding: 0.3rem 0.5rem;
                font-size: 0.7rem;
            }

            .btn-export-small {
                padding: 0.35rem 0.5rem;
                font-size: 0.65rem;
            }

            .tooltip-text {
                width: 150px;
                font-size: 0.65rem;
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

        /* Ensure delete modal is centered */
        .delete-modal .modal-dialog {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100%;
        }

        /* Compact table cell styles */
        .table td, .table th {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 120px;
        }

        /* Specific column widths for better layout */
        .table td:nth-child(1) { width: 40px; } /* No */
        .table td:nth-child(2) { width: 100px; } /* Nama */
        .table td:nth-child(3) { width: 90px; } /* No HP */
        .table td:nth-child(4) { width: 150px; } /* Alamat */
        .table td:nth-child(5) { width: 100px; } /* Tanggal Daftar */
        .table td:nth-child(6) { width: 70px; } /* Aksi */

        /* Ensure no horizontal scroll */
        body, .main-content, .content-body, .container {
            max-width: 100%;
            overflow-x: hidden;
        }

        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
    </style>
</head>

<body>
    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/header.php'; ?>

    <div class="main-content">
        <div class="content-body">
            <div class="container">
                <h2 class="my-3">Data Pelanggan</h2>

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

                <!-- Filter Section dengan Export Buttons di samping -->
                <div class="filter-section">
                    <form method="GET" class="filter-form">
                        <div class="filter-group">
                            <label for="searchInput">Cari Pelanggan:</label>
                            <input type="text" id="searchInput" name="search" placeholder="Cari berdasarkan nama, no HP, alamat..." 
                                   value="<?= htmlspecialchars($search); ?>" 
                                   class="form-control">
                        </div>
                        <div class="filter-group">
                            <label for="tanggal">Filter Tanggal Daftar:</label>
                            <input type="date" id="tanggal" name="tanggal" 
                                   value="<?= $filter_tanggal; ?>" 
                                   class="form-control">
                        </div>
                        <div class="filter-group">
                            <label>&nbsp;</label>
                            <div style="display: flex; gap: 0.4rem; align-items: center; flex-wrap: wrap;">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Terapkan Filter
                                </button>
                                <?php if (!empty($search) || !empty($filter_tanggal)): ?>
                                    <a href="pelanggan.php" class="btn btn-secondary">
                                        <i class="fas fa-refresh"></i> Reset Filter
                                    </a>
                                <?php endif; ?>
                                
                                <!-- Export Buttons - Baru: di sebelah button filter -->
                                <div class="export-buttons">
                                    <div class="tooltip-container">
                                        <a href="export_pelanggan_pdf.php?search=<?= urlencode($search) ?>&tanggal=<?= $filter_tanggal ?>" 
                                           class="btn-export-small btn-pdf-small" target="_blank" title="Ekspor ke PDF">
                                            <i class="fas fa-file-pdf"></i> PDF
                                        </a>
                                        <div class="tooltip-text">
                                            <strong>Ekspor ke PDF</strong><br>
                                            Unduh data Pelanggan dalam format PDF untuk dicetak atau disimpan sebagai dokumen
                                        </div>
                                    </div>
                                    <div class="tooltip-container">
                                        <a href="export_pelanggan_excel.php?search=<?= urlencode($search) ?>&tanggal=<?= $filter_tanggal ?>" 
                                           class="btn-export-small btn-excel-small" target="_blank" title="Ekspor ke Excel">
                                            <i class="fas fa-file-excel"></i> Excel
                                        </a>
                                        <div class="tooltip-text">
                                            <strong>Ekspor ke Excel</strong><br>
                                            Unduh data Pelanggan dalam format Excel untuk analisis lebih lanjut atau pengolahan data
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Search Info -->
                <?php if (!empty($search)): ?>
                    <div class="search-info">
                        <i class="fas fa-info-circle"></i> Menampilkan hasil pencarian untuk: <strong>"<?= htmlspecialchars($search); ?>"</strong>
                        <?php if (!empty($filter_tanggal)): ?>
                            dan filter tanggal: <strong><?= date('d/m/Y', strtotime($filter_tanggal)); ?></strong>
                        <?php endif; ?>
                        - Ditemukan <strong><?= $total_pelanggan; ?></strong> pelanggan
                    </div>
                <?php elseif (!empty($filter_tanggal)): ?>
                    <div class="search-info">
                        <i class="fas fa-info-circle"></i> Menampilkan data dengan filter tanggal: <strong><?= date('d/m/Y', strtotime($filter_tanggal)); ?></strong>
                        - Ditemukan <strong><?= $total_pelanggan; ?></strong> pelanggan
                    </div>
                <?php endif; ?>

                <!-- Header Actions -->
                <div class="header-actions">
                    <h3>Daftar Pelanggan</h3>
                    <div style="display: flex; gap: 0.8rem; align-items: center;">
                        <a href="tambahpelanggan.php" class="btn btn-success">
                            <i class="fas fa-plus"></i> Tambah Pelanggan Baru
                        </a>
                    </div>
                </div>

                <?php if (empty($pelanggan)): ?>
                    <div class="empty-state">
                        <div><i class="fas fa-users"></i></div>
                        <p style="font-size: 1rem; margin-bottom: 0.4rem;">Belum ada data pelanggan</p>
                        <?php if (!empty($search) || !empty($filter_tanggal)): ?>
                            <p style="color: #6b7280; font-size: 0.75rem; margin-top: 0.4rem;">
                                <?php if (!empty($search)): ?>
                                    Pencarian: "<?= htmlspecialchars($search); ?>"
                                <?php endif; ?>
                                <?php if (!empty($filter_tanggal)): ?>
                                    <?php if (!empty($search)): ?> dan <?php endif; ?>
                                    Filter tanggal: <?= date('d/m/Y', strtotime($filter_tanggal)); ?>
                                <?php endif; ?>
                            </p>
                            <a href="pelanggan.php" class="btn btn-primary" style="margin-top: 1.2rem;">
                                <i class="fas fa-list"></i> Tampilkan Semua Data
                            </a>
                        <?php else: ?>
                            <a href="tambahpelanggan.php" class="btn btn-success" style="margin-top: 1.2rem;">
                                <i class="fas fa-user-plus"></i> Tambah Pelanggan Pertama
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="table-responsive">
                            <table class="table" id="pelangganTable">
                                <thead>
                                    <tr>
                                        <th class="no-urut">No</th>
                                        <th>Nama</th>
                                        <th>No HP</th>
                                        <th>Alamat</th>
                                        <th>Tanggal Daftar</th>
                                        <th style="text-align: center;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $nomor_urut = ($page - 1) * $limit + 1;
                                    foreach ($pelanggan as $row): 
                                    ?>
                                    <tr>
                                        <td class="no-urut"><?= $nomor_urut++; ?></td>
                                        <td style="font-weight: 600; color: #1f2937;"><?= htmlspecialchars($row['nama']); ?></td>
                                        <td><?= htmlspecialchars($row['no_hp']); ?></td>
                                        <td><?= htmlspecialchars($row['alamat']); ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($row['created_at'])); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="editpelanggan.php?edit=<?= $row['id_pelanggan']; ?>" 
                                                   class="btn btn-warning btn-sm" 
                                                   title="Edit pelanggan">
                                                   <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-danger btn-sm delete-btn" 
                                                        data-id="<?= $row['id_pelanggan']; ?>"
                                                        data-nama="<?= htmlspecialchars($row['nama']); ?>"
                                                        title="Hapus pelanggan">
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
                                    <a class="page-link" href="?page=<?= $page - 1; ?><?= !empty($search) ? '&search=' . urlencode($search) : ''; ?><?= !empty($filter_tanggal) ? '&tanggal=' . $filter_tanggal : ''; ?>">
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
                                    <a class="page-link" href="?page=<?= $i; ?><?= !empty($search) ? '&search=' . urlencode($search) : ''; ?><?= !empty($filter_tanggal) ? '&tanggal=' . $filter_tanggal : ''; ?>"><?= $i; ?></a>
                                </div>
                            <?php endfor; ?>

                            <!-- Next Page -->
                            <?php if ($page < $total_pages): ?>
                                <div class="page-item">
                                    <a class="page-link" href="?page=<?= $page + 1; ?><?= !empty($search) ? '&search=' . urlencode($search) : ''; ?><?= !empty($filter_tanggal) ? '&tanggal=' . $filter_tanggal : ''; ?>">
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
                                (Total: <?= $total_pelanggan; ?> pelanggan)
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
                    <p>Apakah Anda yakin ingin menghapus pelanggan <strong id="customerName"></strong>?</p>
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

    <!-- Success Modal -->
    <div class="modal fade delete-modal" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Loading Container dengan Circle dan Checkmark -->
                    <div class="loading-container">
                        <div class="circle-loading" id="circleLoading"></div>
                        <svg class="checkmark" id="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                            <circle class="checkmark__circle" cx="26" cy="26" r="25" fill="none"/>
                            <path class="checkmark__check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
                        </svg>
                    </div>
                    
                    <!-- Pesan Sukses -->
                    <div class="success-title" id="successTitle">
                        Data berhasil dihapus
                    </div>
                    
                    <!-- Pesan Redirect -->
                    <div class="redirect-message" id="redirectMessage">
                        Mengalihkan ke halaman pelanggan...
                    </div>
                    
                    <!-- Line Loading di Bawah -->
                    <div class="line-loading-container">
                        <div class="line-loading"></div>
                    </div>
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

        // Animasi loading ke centang
        function startSuccessAnimation() {
            const circleLoading = document.getElementById('circleLoading');
            const checkmark = document.getElementById('checkmark');
            const successTitle = document.getElementById('successTitle');
            const redirectMessage = document.getElementById('redirectMessage');
            
            // Sembunyikan circle loading dan tampilkan checkmark setelah 0.5 detik
            setTimeout(() => {
                circleLoading.style.display = 'none';
                checkmark.classList.add('show');
                successTitle.classList.add('show');
            }, 500);
            
            // Tampilkan pesan redirect setelah 1 detik
            setTimeout(() => {
                redirectMessage.classList.add('show');
            }, 1000);
        }

        // Tampilkan modal konfirmasi hapus
        document.addEventListener('DOMContentLoaded', function() {
            const deleteButtons = document.querySelectorAll('.delete-btn');
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            const customerNameElement = document.getElementById('customerName');
            
            deleteButtons.forEach(button => {
                button.addEventListener('click', function() {
                    currentDeleteId = this.getAttribute('data-id');
                    currentDeleteName = this.getAttribute('data-nama');
                    
                    customerNameElement.textContent = currentDeleteName;
                    deleteModal.show();
                });
            });

            // Konfirmasi hapus
            document.getElementById('confirmDelete').addEventListener('click', function() {
                deleteModal.hide();
                
                // Tampilkan modal success
                const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                successModal.show();
                
                // Mulai animasi
                startSuccessAnimation();
                
                // Set timer untuk redirect setelah 3 detik
                setTimeout(function() {
                    window.location.href = `pelanggan.php?hapus=${currentDeleteId}`;
                }, 3000);
            });
        });

        function searchTable() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('pelangganTable');
            const tr = table.getElementsByTagName('tr');
            
            let visibleRowCount = 0;
            
            for (let i = 1; i < tr.length; i++) {
                const td = tr[i].getElementsByTagName('td');
                let found = false;
                
                for (let j = 1; j < td.length - 1; j++) { // Exclude No and Action columns
                    if (td[j]) {
                        if (td[j].textContent.toLowerCase().indexOf(filter) > -1) {
                            found = true;
                            break;
                        }
                    }
                }
                
                if (found) {
                    tr[i].style.display = '';
                    visibleRowCount++;
                    
                    // Update nomor urut untuk baris yang visible
                    const noCell = tr[i].cells[0];
                    noCell.textContent = visibleRowCount;
                } else {
                    tr[i].style.display = 'none';
                }
            }
        }

        // Auto focus search input
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.focus();
                
                // Jika ada nilai search, jalankan pencarian client-side juga
                if (searchInput.value !== '') {
                    searchTable();
                }
            }
        });

        // Highlight table rows on hover
        document.addEventListener('DOMContentLoaded', function() {
            const tableRows = document.querySelectorAll('#pelangganTable tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#f8faff';
                });
                row.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = '';
                });
            });
        });

        // Client-side search untuk UX yang lebih baik
        document.getElementById('searchInput').addEventListener('input', function(e) {
            searchTable();
        });

        // Reset nomor urut ketika search dihapus
        document.getElementById('searchInput').addEventListener('input', function(e) {
            if (this.value === '') {
                const table = document.getElementById('pelangganTable');
                const tr = table.getElementsByTagName('tr');
                
                for (let i = 1; i < tr.length; i++) {
                    const noCell = tr[i].cells[0];
                    noCell.textContent = i;
                }
            }
        });
    </script>
</body>
</html>