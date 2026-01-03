<?php
// karyawan/karyawan.php
require_once '../config/database.php';
check_login();
check_role(['admin', 'pemilik']);

// Tambah karyawan (dari form manual)
if (isset($_POST['tambah_karyawan'])) {
    $nama_karyawan = clean_input($_POST['nama_karyawan']);
    $no_hp = clean_input($_POST['no_hp']);
    $alamat = clean_input($_POST['alamat']);
    $jabatan = clean_input($_POST['jabatan']);
    $gaji = clean_input($_POST['gaji']);
    $status = clean_input($_POST['status']);
    $tanggal_masuk = isset($_POST['tanggal_masuk']) && !empty($_POST['tanggal_masuk']) ? clean_input($_POST['tanggal_masuk']) : null;
    
    try {
        // Cari user dengan nama yang sama di tabel users (hanya role pegawai atau pemilik)
        $sql_find_user = "SELECT id_user, username, role FROM users WHERE (nama_lengkap = ? OR username LIKE ?) AND role IN ('pegawai', 'pemilik')";
        $user_data = getSingle($sql_find_user, [$nama_karyawan, "%$nama_karyawan%"]);
        
        if ($user_data) {
            // Jika user ditemukan (role pegawai/pemilik), gunakan id_user yang sama
            $id_user = $user_data['id_user'];
            $username = $user_data['username'];
            $role = $user_data['role'];
            
            $sql = "INSERT INTO data_karyawan (id_karyawan, nama_karyawan, no_hp, alamat, jabatan, gaji, status, tanggal_masuk) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            executeQuery($sql, [$id_user, $nama_karyawan, $no_hp, $alamat, $jabatan, $gaji, $status, $tanggal_masuk]);
            
            $_SESSION['success'] = "✅ Karyawan berhasil ditambahkan (terhubung dengan user: $username)";
            log_activity("Menambah karyawan: $nama_karyawan (terhubung dengan user ID: $id_user)");
        } else {
            // Jika user tidak ditemukan, buat record baru dengan auto-increment
            $sql = "INSERT INTO data_karyawan (nama_karyawan, no_hp, alamat, jabatan, gaji, status, tanggal_masuk) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            executeQuery($sql, [$nama_karyawan, $no_hp, $alamat, $jabatan, $gaji, $status, $tanggal_masuk]);
            
            $_SESSION['success'] = "✅ Karyawan berhasil ditambahkan (tanpa user terkait)";
            log_activity("Menambah karyawan: $nama_karyawan");
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "❌ Gagal menambah karyawan: " . $e->getMessage();
    }
    header("Location: karyawan.php");
    exit();
}

// Edit karyawan
if (isset($_POST['edit_karyawan'])) {
    $id = clean_input($_POST['id_karyawan']);
    $nama_karyawan = clean_input($_POST['nama_karyawan']);
    $no_hp = clean_input($_POST['no_hp']);
    $alamat = clean_input($_POST['alamat']);
    $jabatan = clean_input($_POST['jabatan']);
    $gaji = clean_input($_POST['gaji']);
    $status = clean_input($_POST['status']);
    $tanggal_masuk = isset($_POST['tanggal_masuk']) && !empty($_POST['tanggal_masuk']) ? clean_input($_POST['tanggal_masuk']) : null;
    
    try {
        // Cek apakah karyawan terhubung dengan user
        $sql_check_user = "SELECT u.id_user, u.username, u.role FROM users u 
                          INNER JOIN data_karyawan k ON u.id_user = k.id_karyawan 
                          WHERE k.id_karyawan = ?";
        $user_check = getSingle($sql_check_user, [$id]);
        
        $sql = "UPDATE data_karyawan 
                SET nama_karyawan = ?, no_hp = ?, alamat = ?, jabatan = ?, gaji = ?, status = ?, tanggal_masuk = ?
                WHERE id_karyawan = ?";
        executeQuery($sql, [$nama_karyawan, $no_hp, $alamat, $jabatan, $gaji, $status, $tanggal_masuk, $id]);
        
        // Jika terhubung dengan user, update juga nama di tabel users
        if ($user_check) {
            $sql_update_user = "UPDATE users SET nama_lengkap = ? WHERE id_user = ?";
            executeQuery($sql_update_user, [$nama_karyawan, $id]);
        }
        
        $message = "✅ Data karyawan berhasil diupdate";
        if ($user_check) {
            $message .= " (dan nama user juga diupdate)";
        }
        
        $_SESSION['success'] = $message;
        log_activity("Mengupdate karyawan: $nama_karyawan (ID: $id)");
    } catch (PDOException $e) {
        $_SESSION['error'] = "❌ Gagal mengupdate karyawan: " . $e->getMessage();
    }
    header("Location: karyawan.php");
    exit();
}

// Hapus karyawan
if (isset($_GET['hapus'])) {
    $id = clean_input($_GET['hapus']);
    
    try {
        // Cek apakah karyawan memiliki pesanan
        $sql_check = "SELECT COUNT(*) as total_pesanan FROM data_pesanan WHERE id_karyawan = ?";
        $check_result = getSingle($sql_check, [$id]);
        
        // Cek apakah terhubung dengan user
        $sql_check_user = "SELECT COUNT(*) as total_user FROM users WHERE id_user = ?";
        $check_user = getSingle($sql_check_user, [$id]);
        
        if ($check_result['total_pesanan'] > 0) {
            $_SESSION['error'] = "❌ Tidak dapat menghapus karyawan karena memiliki data pesanan terkait";
        } elseif ($check_user['total_user'] > 0) {
            // Jika terhubung dengan user, hanya hapus dari data_karyawan tapi biarkan user tetap ada
            $sql = "DELETE FROM data_karyawan WHERE id_karyawan = ?";
            executeQuery($sql, [$id]);
            
            $_SESSION['success'] = "✅ Karyawan berhasil dihapus dari data karyawan (user tetap ada)";
            log_activity("Menghapus karyawan dari data_karyawan ID: $id (user tetap ada)");
        } else {
            // Jika tidak terhubung dengan user, hapus sepenuhnya
            $sql = "DELETE FROM data_karyawan WHERE id_karyawan = ?";
            executeQuery($sql, [$id]);
            
            $_SESSION['success'] = "✅ Karyawan berhasil dihapus";
            log_activity("Menghapus karyawan ID: $id");
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "❌ Gagal menghapus karyawan: " . $e->getMessage();
    }
    header("Location: karyawan.php");
    exit();
}

// Tambah karyawan dari user (dari modal)
if (isset($_POST['tambah_dari_user'])) {
    $id_user = clean_input($_POST['id_user']);
    $nama_karyawan = clean_input($_POST['nama_karyawan']);
    $no_hp = clean_input($_POST['no_hp']);
    $alamat = clean_input($_POST['alamat']);
    $jabatan = clean_input($_POST['jabatan']);
    $gaji = clean_input($_POST['gaji']);
    $status = clean_input($_POST['status']);
    $tanggal_masuk = isset($_POST['tanggal_masuk']) && !empty($_POST['tanggal_masuk']) ? clean_input($_POST['tanggal_masuk']) : null;
    
    try {
        // Cek apakah sudah ada di data_karyawan
        $sql_check = "SELECT COUNT(*) as total FROM data_karyawan WHERE id_karyawan = ?";
        $check_result = getSingle($sql_check, [$id_user]);
        
        if ($check_result['total'] > 0) {
            $_SESSION['error'] = "❌ User ini sudah terdaftar sebagai karyawan";
        } else {
            // Tambahkan ke data_karyawan
            $sql = "INSERT INTO data_karyawan (id_karyawan, nama_karyawan, no_hp, alamat, jabatan, gaji, status, tanggal_masuk) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            executeQuery($sql, [$id_user, $nama_karyawan, $no_hp, $alamat, $jabatan, $gaji, $status, $tanggal_masuk]);
            
            $_SESSION['success'] = "✅ User berhasil ditambahkan sebagai karyawan";
            log_activity("Menambah karyawan dari user: $nama_karyawan (User ID: $id_user)");
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "❌ Gagal menambahkan karyawan: " . $e->getMessage();
    }
    header("Location: karyawan.php");
    exit();
}

// Konfigurasi pagination
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Filter pencarian
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$filter_status = isset($_GET['status']) ? clean_input($_GET['status']) : '';

// Query dasar untuk mengambil data karyawan (HANYA data_karyawan)
$sql_where = "";
$params = [];
$where_conditions = [];

// MODIFIKASI: TAMBAHKAN KONDISI UNTUK TIDAK MENAMPILKAN JABATAN "PEMILIK"
$where_conditions[] = "k.jabatan != 'pemilik'";

if (!empty($search)) {
    $where_conditions[] = "(k.nama_karyawan LIKE ? OR k.no_hp LIKE ? OR k.alamat LIKE ? OR k.jabatan LIKE ? OR u.username LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($filter_status)) {
    $where_conditions[] = "k.status = ?";
    $params[] = $filter_status;
}

if (count($where_conditions) > 0) {
    $sql_where = "WHERE " . implode(" AND ", $where_conditions);
}

// Hitung total data
try {
    $sql_count = "SELECT COUNT(*) as total 
                  FROM data_karyawan k
                  LEFT JOIN users u ON k.id_karyawan = u.id_user
                  $sql_where";
    
    $stmt = executeQuery($sql_count, $params);
    $total_karyawan = $stmt->fetchColumn();
    $total_pages = ceil($total_karyawan / $limit);
} catch (PDOException $e) {
    $total_karyawan = 0;
    $total_pages = 1;
}

// Ambil data karyawan (dari data_karyawan dengan join ke users)
try {
    $sql = "SELECT k.*, 
                   u.username, 
                   u.email, 
                   u.role as user_role,
                   CASE 
                       WHEN u.id_user IS NOT NULL THEN 'terhubung'
                       ELSE 'tidak_terhubung'
                   END as status_user
            FROM data_karyawan k
            LEFT JOIN users u ON k.id_karyawan = u.id_user
            $sql_where
            ORDER BY k.nama_karyawan ASC
            LIMIT $limit OFFSET $offset";
    
    $karyawan = getAll($sql, $params);
} catch (PDOException $e) {
    $_SESSION['error'] = "❌ Gagal memuat data: " . $e->getMessage();
    $karyawan = [];
}

// Ambil data user pegawai yang belum ada di data_karyawan (untuk modal tambah dari user)
try {
    $sql_users_not_karyawan = "SELECT u.id_user, u.username, u.nama_lengkap, u.email, u.role, u.status
                              FROM users u
                              WHERE u.role IN ('pegawai', 'pemilik')
                              AND u.id_user NOT IN (SELECT id_karyawan FROM data_karyawan WHERE id_karyawan IS NOT NULL)
                              ORDER BY u.nama_lengkap ASC";
    $users_not_karyawan = getAll($sql_users_not_karyawan);
} catch (PDOException $e) {
    $users_not_karyawan = [];
}

// Ambil data untuk statistik
try {
    // Total karyawan dari data_karyawan (KECUALI PEMILIK)
    $sql_total_karyawan = "SELECT COUNT(*) FROM data_karyawan WHERE jabatan != 'pemilik'";
    $total_data_karyawan = executeQuery($sql_total_karyawan)->fetchColumn();
    
    // Total karyawan aktif (KECUALI PEMILIK)
    $sql_aktif = "SELECT COUNT(*) FROM data_karyawan WHERE status = 'aktif' AND jabatan != 'pemilik'";
    $total_aktif = executeQuery($sql_aktif)->fetchColumn();
    
    // Total karyawan non-aktif (KECUALI PEMILIK)
    $sql_non_aktif = "SELECT COUNT(*) FROM data_karyawan WHERE status = 'nonaktif' AND jabatan != 'pemilik'";
    $total_non_aktif = executeQuery($sql_non_aktif)->fetchColumn();
    
} catch (PDOException $e) {
    $total_data_karyawan = 0;
    $total_aktif = 0;
    $total_non_aktif = 0;
}

$page_title = "Data Karyawan";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Karyawan - SIM Parapatan Tailor</title>
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
        }
        
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: white;
            padding: 1.2rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid #4f46e5;
            text-align: center;
        }

        .stat-card.total {
            border-left-color: #4f46e5;
        }

        .stat-card.aktif {
            border-left-color: #2563eb;
        }

        .stat-card.non-aktif {
            border-left-color: #6b7280;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.4rem;
        }

        .stat-label {
            font-size: 0.75rem;
            color: #6b7280;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .stat-icon {
            width: 35px;
            height: 35px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            margin: 0 auto 0.8rem;
        }

        .stat-icon.total {
            background: linear-gradient(135deg, #ede9fe, #ddd6fe);
            color: #4f46e5;
        }

        .stat-icon.aktif {
            background: linear-gradient(135deg, #dbeafe, #93c5fd);
            color: #2563eb;
        }

        .stat-icon.non-aktif {
            background: linear-gradient(135deg, #f3f4f6, #d1d5db);
            color: #6b7280;
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

        .status-badge.aktif {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }

        .status-badge.nonaktif {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fca5a5;
        }

        .jabatan-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 8px;
            font-size: 0.65rem;
            font-weight: 600;
            text-align: center;
        }

        .jabatan-badge.penjahit {
            background: linear-gradient(135deg, #3b82f6, #60a5fa);
            color: white;
        }

        .jabatan-badge.admin {
            background: linear-gradient(135deg, #059669, #10b981);
            color: white;
        }

        .jabatan-badge.pemilik {
            background: linear-gradient(135deg, #8b5cf6, #a78bfa);
            color: white;
        }

        .jabatan-badge.pegawai {
            background: linear-gradient(135deg, #f59e0b, #fbbf24);
            color: white;
        }

        .date-badge {
            background: linear-gradient(135deg, #f3e8ff, #e9d5ff);
            color: #7c3aed;
            padding: 0.2rem 0.5rem;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.65rem;
            display: inline-block;
            border: 1px solid #a855f7;
        }

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
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .stat-number {
                font-size: 1.8rem;
            }
            
            .container {
                padding: 12px;
                margin: 0.8rem;
            }
            
            .table th:nth-child(6),
            .table td:nth-child(6),
            .table th:nth-child(8),
            .table td:nth-child(8) {
                display: none;
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
            
            .table th:nth-child(4),
            .table td:nth-child(4),
            .table th:nth-child(5),
            .table td:nth-child(5),
            .table th:nth-child(7),
            .table td:nth-child(7) {
                display: none;
            }
        }
        
        .main-content {
            min-height: calc(100vh - 80px);
            display: flex;
            flex-direction: column;
        }
        
        .content-body {
            flex: 1;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
            font-size: 0.8rem;
        }

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
    </style>
</head>

<body>
    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/header.php'; ?>

    <div class="main-content">
        <div class="content-body">
            <div class="container">
                <h2 class="my-3">Data Karyawan</h2>

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

                <!-- Statistik Karyawan -->
                <div class="stats-grid mb-4">
                    <div class="stat-card total">
                        <div class="stat-icon total">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-number"><?= $total_data_karyawan; ?></div>
                        <div class="stat-label">Total Karyawan</div>
                    </div>
                    
                    <div class="stat-card aktif">
                        <div class="stat-icon aktif">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="stat-number"><?= $total_aktif; ?></div>
                        <div class="stat-label">Karyawan Aktif</div>
                    </div>
                    
                    <div class="stat-card non-aktif">
                        <div class="stat-icon non-aktif">
                            <i class="fas fa-user-slash"></i>
                        </div>
                        <div class="stat-number"><?= $total_non_aktif; ?></div>
                        <div class="stat-label">Karyawan Non-Aktif</div>
                    </div>
                </div>

                <!-- Filter Section -->
                <div class="filter-section">
                    <div class="header-actions">
                        <h3><i class="fas fa-filter"></i> Filter Data Karyawan</h3>
                        <!-- Button tambah karyawan dihapus sesuai permintaan -->
                    </div>
                    <form method="GET" class="filter-form">
                        <div class="filter-group">
                            <label for="searchInput">Cari Karyawan:</label>
                            <input type="text" id="searchInput" name="search" placeholder="Cari berdasarkan nama, no_hp, atau alamat..." 
                                   value="<?= htmlspecialchars($search); ?>" 
                                   class="form-control">
                        </div>
                        <div class="filter-group">
                            <label for="status">Filter Status:</label>
                            <select id="status" name="status" class="form-control">
                                <option value="">Semua Status</option>
                                <option value="aktif" <?= $filter_status == 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                                <option value="nonaktif" <?= $filter_status == 'nonaktif' ? 'selected' : ''; ?>>Non-Aktif</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>&nbsp;</label>
                            <div style="display: flex; gap: 0.4rem;">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Terapkan Filter
                                </button>
                                <?php if (!empty($search) || !empty($filter_status)): ?>
                                    <a href="karyawan.php" class="btn btn-secondary">
                                        <i class="fas fa-refresh"></i> Reset Filter
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Search Info -->
                <?php if (!empty($search) || !empty($filter_status)): ?>
                    <div class="search-info">
                        <i class="fas fa-info-circle"></i> Menampilkan hasil filter:
                        <?php if (!empty($search)): ?>
                            Pencarian: <strong>"<?= htmlspecialchars($search); ?>"</strong>
                        <?php endif; ?>
                        <?php if (!empty($filter_status)): ?>
                            <?php if (!empty($search)): ?>, <?php endif; ?>
                            Status: <strong><?= ucfirst($filter_status); ?></strong>
                        <?php endif; ?>
                        - Ditemukan <strong><?= $total_karyawan; ?></strong> karyawan
                    </div>
                <?php endif; ?>

                <?php if (empty($karyawan)): ?>
                    <div class="empty-state">
                        <div><i class="fas fa-users"></i></div>
                        <p style="font-size: 0.9rem; margin-bottom: 0.4rem;">Belum ada data karyawan</p>
                        <?php if (!empty($search) || !empty($filter_status)): ?>
                            <p style="color: #6b7280; font-size: 0.75rem; margin-top: 0.4rem;">
                                <?php if (!empty($search)): ?>
                                    Pencarian: "<?= htmlspecialchars($search); ?>"
                                <?php endif; ?>
                                <?php if (!empty($filter_status)): ?>
                                    <?php if (!empty($search)): ?>, <?php endif; ?>
                                    Filter status: <?= ucfirst($filter_status); ?>
                                <?php endif; ?>
                            </p>
                            <a href="karyawan.php" class="btn btn-primary" style="margin-top: 1.2rem;">
                                <i class="fas fa-list"></i> Tampilkan Semua Data
                            </a>
                        <?php else: ?>
                            <!-- Tidak ada tombol tambah karyawan -->
                            <p style="color: #6b7280; font-size: 0.75rem; margin-top: 0.4rem;">
                                Silakan hubungi administrator untuk menambahkan karyawan
                            </p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="table-responsive">
                            <table class="table" id="karyawanTable">
                                <thead>
                                    <tr>
                                        <th class="no-urut">No</th>
                                        <th>Nama Karyawan</th>
                                        <th>No HP</th>
                                        <th>Alamat</th>
                                        <th>Jabatan</th>
                                        <th>Gaji</th>
                                        <th>Tanggal Masuk</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $nomor_urut = ($page - 1) * $limit + 1;
                                    foreach ($karyawan as $row): 
                                        // Format tanggal masuk
                                        $tanggal_masuk_formatted = isset($row['tanggal_masuk']) && $row['tanggal_masuk'] ? date('d-m-Y', strtotime($row['tanggal_masuk'])) : '-';
                                    ?>
                                    <tr>
                                        <td class="no-urut"><?= $nomor_urut++; ?></td>
                                        <td style="font-weight: 600; color: #1f2937;">
                                            <?= htmlspecialchars($row['nama_karyawan']); ?>
                                            <?php if ($row['status_user'] == 'terhubung'): ?>
                                                <br>
                                                <span style="font-size: 0.6rem; color: #6b7280;">
                                                    <i class="fas fa-user"></i> User: <?= htmlspecialchars($row['username'] ?? '-'); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($row['no_hp'] ?? '-'); ?></td>
                                        <td><?= htmlspecialchars($row['alamat'] ?? '-'); ?></td>
                                        <td>
                                            <span class="jabatan-badge <?= strtolower($row['jabatan'] ?? ''); ?>">
                                                <?= htmlspecialchars(ucfirst($row['jabatan'] ?? '')); ?>
                                            </span>
                                        </td>
                                        <td style="font-weight: 600; color: #d97706;">
                                            <?php if (isset($row['gaji']) && $row['gaji'] > 0): ?>
                                                Rp <?= number_format($row['gaji'], 0, ',', '.'); ?>
                                            <?php else: ?>
                                                <span style="color: #6b7280; font-style: italic;">Belum diatur</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (isset($row['tanggal_masuk']) && $row['tanggal_masuk']): ?>
                                                <span class="date-badge"><?= $tanggal_masuk_formatted; ?></span>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-badge <?= $row['status']; ?>">
                                                <?= ucfirst($row['status']); ?>
                                            </span>
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
                                    <a class="page-link" href="?page=<?= $page - 1; ?><?= !empty($search) ? '&search=' . urlencode($search) : ''; ?><?= !empty($filter_status) ? '&status=' . $filter_status : ''; ?>">
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
                                    <a class="page-link" href="?page=<?= $i; ?><?= !empty($search) ? '&search=' . urlencode($search) : ''; ?><?= !empty($filter_status) ? '&status=' . $filter_status : ''; ?>"><?= $i; ?></a>
                                </div>
                            <?php endfor; ?>

                            <!-- Next Page -->
                            <?php if ($page < $total_pages): ?>
                                <div class="page-item">
                                    <a class="page-link" href="?page=<?= $page + 1; ?><?= !empty($search) ? '&search=' . urlencode($search) : ''; ?><?= !empty($filter_status) ? '&status=' . $filter_status : ''; ?>">
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
                                (Total: <?= $total_karyawan; ?> karyawan)
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

    <!-- Modal Tambah Karyawan - DIHAPUS -->
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Highlight table rows on hover
            const tableRows = document.querySelectorAll('#karyawanTable tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#f8faff';
                });
                row.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = '';
                });
            });
        });

        function searchTable() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('karyawanTable');
            const tr = table.getElementsByTagName('tr');
            
            let visibleRowCount = 0;
            
            for (let i = 1; i < tr.length; i++) {
                const td = tr[i].getElementsByTagName('td');
                let found = false;
                
                for (let j = 1; j < td.length - 1; j++) {
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
                    
                    // Update nomor urut
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
                
                if (searchInput.value !== '') {
                    searchTable();
                }
            }
        });

        // Client-side search
        document.getElementById('searchInput').addEventListener('input', function(e) {
            searchTable();
        });

        // Reset nomor urut ketika search dihapus
        document.getElementById('searchInput').addEventListener('input', function(e) {
            if (this.value === '') {
                const table = document.getElementById('karyawanTable');
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