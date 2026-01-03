<?php
// user/users.php
require_once '../config/database.php';
check_login();
check_role(['admin']); // Hanya admin yang bisa akses

// Fungsi untuk sinkronisasi ke data karyawan
function syncToKaryawan($user_id, $nama, $role, $status, $no_hp = null, $alamat = null, $gaji = null, $tanggal_masuk = null) {
    try {
        // Normalize status (hilangkan dash jika ada)
        $normalized_status = ($status == 'non-aktif') ? 'nonaktif' : $status;
        
        // PERBAIKAN: Admin juga harus masuk ke data karyawan
        if ($role == 'pegawai' || $role == 'pemilik' || $role == 'admin') {
            // Cek apakah sudah ada di data_karyawan
            $sql_check = "SELECT COUNT(*) as total FROM data_karyawan WHERE id_karyawan = ?";
            $check_result = getSingle($sql_check, [$user_id]);
            
            if ($check_result['total'] == 0) {
                // Tambah ke data_karyawan
                $sql_insert = "INSERT INTO data_karyawan (id_karyawan, nama_karyawan, jabatan, status, no_hp, alamat, gaji, tanggal_masuk) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                executeQuery($sql_insert, [$user_id, $nama, $role, $normalized_status, $no_hp, $alamat, $gaji, $tanggal_masuk]);
                return "ditambahkan ke data karyawan";
            } else {
                // Update data karyawan
                $sql_update = "UPDATE data_karyawan 
                              SET nama_karyawan = ?, jabatan = ?, status = ?, no_hp = ?, alamat = ?, gaji = ?, tanggal_masuk = ?
                              WHERE id_karyawan = ?";
                executeQuery($sql_update, [$nama, $role, $normalized_status, $no_hp, $alamat, $gaji, $tanggal_masuk, $user_id]);
                return "data karyawan diupdate";
            }
        } else {
            // Jika bukan pegawai/pemilik/admin, hapus dari data_karyawan jika ada
            $sql_delete = "DELETE FROM data_karyawan WHERE id_karyawan = ?";
            executeQuery($sql_delete, [$user_id]);
            return "dihapus dari data karyawan";
        }
    } catch (PDOException $e) {
        throw $e;
    }
}

// Sinkronisasi manual semua user pegawai ke karyawan
if (isset($_GET['sync_karyawan'])) {
    try {
        // PERBAIKAN: Admin juga harus disinkronkan
        $sql_pegawai = "SELECT u.id_user, u.nama_lengkap, u.role, u.status, dk.no_hp, dk.alamat, dk.gaji, dk.tanggal_masuk 
                       FROM users u 
                       LEFT JOIN data_karyawan dk ON u.id_user = dk.id_karyawan 
                       WHERE u.role IN ('pegawai', 'pemilik', 'admin')";
        $pegawai_list = getAll($sql_pegawai);
        
        $count_added = 0;
        $count_updated = 0;
        
        foreach ($pegawai_list as $pegawai) {
            // Normalize status
            $status_karyawan = ($pegawai['status'] == 'non-aktif') ? 'nonaktif' : $pegawai['status'];
            
            $sql_check = "SELECT COUNT(*) as total FROM data_karyawan WHERE id_karyawan = ?";
            $check_result = getSingle($sql_check, [$pegawai['id_user']]);
            
            if ($check_result['total'] == 0) {
                // Tambah ke data_karyawan
                $sql_insert = "INSERT INTO data_karyawan (id_karyawan, nama_karyawan, jabatan, status, no_hp, alamat, gaji, tanggal_masuk) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                executeQuery($sql_insert, [
                    $pegawai['id_user'], 
                    $pegawai['nama_lengkap'], 
                    $pegawai['role'], 
                    $status_karyawan,
                    $pegawai['no_hp'],
                    $pegawai['alamat'],
                    $pegawai['gaji'],
                    $pegawai['tanggal_masuk']
                ]);
                $count_added++;
            } else {
                // Update data karyawan
                $sql_update = "UPDATE data_karyawan 
                              SET nama_karyawan = ?, jabatan = ?, status = ?, no_hp = ?, alamat = ?, gaji = ?, tanggal_masuk = ?
                              WHERE id_karyawan = ?";
                executeQuery($sql_update, [
                    $pegawai['nama_lengkap'], 
                    $pegawai['role'], 
                    $status_karyawan,
                    $pegawai['no_hp'],
                    $pegawai['alamat'],
                    $pegawai['gaji'],
                    $pegawai['tanggal_masuk'],
                    $pegawai['id_user']
                ]);
                $count_updated++;
            }
        }
        
        $_SESSION['success'] = "✅ Sinkronisasi berhasil: $count_added user ditambahkan, $count_updated user diupdate";
        log_activity("Sinkronisasi data karyawan: $count_added ditambahkan, $count_updated diupdate");
    } catch (PDOException $e) {
        $_SESSION['error'] = "❌ Gagal sinkronisasi: " . $e->getMessage();
    }
    header("Location: users.php");
    exit();
}

// Tambah user
if (isset($_POST['tambah_user'])) {
    $username = clean_input($_POST['username']);
    $password = clean_input($_POST['password']); // PLAIN TEXT - TIDAK DIHASH
    $nama_lengkap = clean_input($_POST['nama_lengkap']);
    $email = clean_input($_POST['email']);
    $role = clean_input($_POST['role']);
    $status = clean_input($_POST['status']);
    $gaji = isset($_POST['gaji']) && !empty($_POST['gaji']) ? clean_input($_POST['gaji']) : null;
    $no_hp = isset($_POST['no_hp']) ? clean_input($_POST['no_hp']) : null;
    $alamat = isset($_POST['alamat']) ? clean_input($_POST['alamat']) : null;
    $tanggal_masuk = isset($_POST['tanggal_masuk']) && !empty($_POST['tanggal_masuk']) ? clean_input($_POST['tanggal_masuk']) : null;
    
    try {
        // Cek apakah username sudah ada
        $sql_check = "SELECT COUNT(*) as total FROM users WHERE username = ?";
        $check_result = getSingle($sql_check, [$username]);
        
        if ($check_result['total'] > 0) {
            $_SESSION['error'] = "❌ Username sudah digunakan";
        } else {
            // PERUBAHAN: Simpan password dalam plain text (tidak di-hash)
            // $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert ke tabel users - password disimpan plain text
            $sql = "INSERT INTO users (username, password, nama_lengkap, email, role, status) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            executeQuery($sql, [$username, $password, $nama_lengkap, $email, $role, $status]);
            
            // Dapatkan ID user yang baru dibuat
            $sql_last_id = "SELECT LAST_INSERT_ID() as id";
            $last_id_result = getSingle($sql_last_id);
            $new_user_id = $last_id_result['id'];
            
            $karyawan_message = "";
            // PERBAIKAN: Admin juga harus masuk ke data karyawan
            if ($role == 'pegawai' || $role == 'pemilik' || $role == 'admin') {
                // Normalize status untuk karyawan
                $status_karyawan = ($status == 'non-aktif') ? 'nonaktif' : $status;
                
                $sql_karyawan = "INSERT INTO data_karyawan (id_karyawan, nama_karyawan, jabatan, status, gaji, no_hp, alamat, tanggal_masuk) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                executeQuery($sql_karyawan, [$new_user_id, $nama_lengkap, $role, $status_karyawan, $gaji, $no_hp, $alamat, $tanggal_masuk]);
                $karyawan_message = " (dan otomatis ditambahkan ke data karyawan)";
            }
            
            $_SESSION['success'] = "✅ User berhasil ditambahkan" . $karyawan_message;
            log_activity("Menambah user: $username ($nama_lengkap) - Role: $role");
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "❌ Gagal menambah user: " . $e->getMessage();
    }
    header("Location: users.php");
    exit();
}

// Edit user
if (isset($_POST['edit_user'])) {
    $id = clean_input($_POST['id_user']);
    $username = clean_input($_POST['username']);
    $nama_lengkap = clean_input($_POST['nama_lengkap']);
    $email = clean_input($_POST['email']);
    $role = clean_input($_POST['role']);
    $status = clean_input($_POST['status']);
    $gaji = isset($_POST['gaji']) && !empty($_POST['gaji']) ? clean_input($_POST['gaji']) : null;
    $no_hp = isset($_POST['no_hp']) ? clean_input($_POST['no_hp']) : null;
    $alamat = isset($_POST['alamat']) ? clean_input($_POST['alamat']) : null;
    $tanggal_masuk = isset($_POST['tanggal_masuk']) && !empty($_POST['tanggal_masuk']) ? clean_input($_POST['tanggal_masuk']) : null;
    
    try {
        // Cek apakah username sudah ada (kecuali untuk user yang sama)
        $sql_check = "SELECT COUNT(*) as total FROM users WHERE username = ? AND id_user != ?";
        $check_result = getSingle($sql_check, [$username, $id]);
        
        if ($check_result['total'] > 0) {
            $_SESSION['error'] = "❌ Username sudah digunakan";
        } else {
            // Dapatkan data user lama
            $sql_old_data = "SELECT role, nama_lengkap FROM users WHERE id_user = ?";
            $old_data = getSingle($sql_old_data, [$id]);
            $old_role = $old_data['role'];
            
            // Update tabel users
            $sql = "UPDATE users 
                    SET username = ?, nama_lengkap = ?, email = ?, role = ?, status = ?
                    WHERE id_user = ?";
            executeQuery($sql, [$username, $nama_lengkap, $email, $role, $status, $id]);
            
            $karyawan_message = "";
            // Normalize status untuk karyawan
            $status_karyawan = ($status == 'non-aktif') ? 'nonaktif' : $status;
            
            // PERBAIKAN: Admin juga harus masuk ke data karyawan
            if (($old_role == 'pegawai' || $old_role == 'pemilik' || $old_role == 'admin') && ($role != 'pegawai' && $role != 'pemilik' && $role != 'admin')) {
                // Jika role berubah dari pegawai/pemilik/admin ke bukan
                $sql_delete = "DELETE FROM data_karyawan WHERE id_karyawan = ?";
                executeQuery($sql_delete, [$id]);
                $karyawan_message = " (dihapus dari data karyawan karena role berubah)";
            } elseif (($old_role != 'pegawai' && $old_role != 'pemilik' && $old_role != 'admin') && ($role == 'pegawai' || $role == 'pemilik' || $role == 'admin')) {
                // Jika role berubah dari bukan ke pegawai/pemilik/admin
                $sql_insert = "INSERT INTO data_karyawan (id_karyawan, nama_karyawan, jabatan, status, gaji, no_hp, alamat, tanggal_masuk) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                executeQuery($sql_insert, [$id, $nama_lengkap, $role, $status_karyawan, $gaji, $no_hp, $alamat, $tanggal_masuk]);
                $karyawan_message = " (ditambahkan ke data karyawan)";
            } elseif ($role == 'pegawai' || $role == 'pemilik' || $role == 'admin') {
                // Jika tetap pegawai/pemilik/admin, update data
                // Cek apakah sudah ada di data_karyawan
                $sql_check_karyawan = "SELECT COUNT(*) as total FROM data_karyawan WHERE id_karyawan = ?";
                $check_karyawan = getSingle($sql_check_karyawan, [$id]);
                
                if ($check_karyawan['total'] > 0) {
                    $sql_update_karyawan = "UPDATE data_karyawan 
                                           SET nama_karyawan = ?, jabatan = ?, status = ?, gaji = ?, no_hp = ?, alamat = ?, tanggal_masuk = ?
                                           WHERE id_karyawan = ?";
                    executeQuery($sql_update_karyawan, [$nama_lengkap, $role, $status_karyawan, $gaji, $no_hp, $alamat, $tanggal_masuk, $id]);
                    $karyawan_message = " (data karyawan diupdate)";
                } else {
                    $sql_insert = "INSERT INTO data_karyawan (id_karyawan, nama_karyawan, jabatan, status, gaji, no_hp, alamat, tanggal_masuk) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    executeQuery($sql_insert, [$id, $nama_lengkap, $role, $status_karyawan, $gaji, $no_hp, $alamat, $tanggal_masuk]);
                    $karyawan_message = " (ditambahkan ke data karyawan)";
                }
            }
            
            $_SESSION['success'] = "✅ Data user berhasil diupdate" . $karyawan_message;
            log_activity("Mengupdate user: $username ($nama_lengkap) - Role: $role");
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "❌ Gagal mengupdate user: " . $e->getMessage();
    }
    header("Location: users.php");
    exit();
}

// Reset password user
if (isset($_POST['reset_password'])) {
    $id = clean_input($_POST['id_user']);
    $password = clean_input($_POST['password']); // PLAIN TEXT
    $confirm_password = clean_input($_POST['confirm_password'] ?? '');
    
    try {
        // Validasi password
        if (empty($password)) {
            $_SESSION['error'] = "❌ Password tidak boleh kosong";
        } elseif ($confirm_password && $password !== $confirm_password) {
            $_SESSION['error'] = "❌ Password dan konfirmasi password tidak sama";
        } else {
            // PERUBAHAN: Simpan password dalam plain text (tidak di-hash)
            // $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $sql = "UPDATE users SET password = ? WHERE id_user = ?";
            executeQuery($sql, [$password, $id]); // Simpan plain text
            
            $_SESSION['success'] = "✅ Password berhasil direset";
            log_activity("Reset password user ID: $id");
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "❌ Gagal reset password: " . $e->getMessage();
    }
    header("Location: users.php");
    exit();
}

// Hapus user
if (isset($_GET['hapus'])) {
    $id = clean_input($_GET['hapus']);
    
    try {
        // Cek apakah user sedang login
        if ($id == $_SESSION['user_id']) {
            $_SESSION['error'] = "❌ Tidak dapat menghapus user yang sedang login";
        } else {
            // Hapus dari data_karyawan terlebih dahulu (jika ada)
            $sql_delete_karyawan = "DELETE FROM data_karyawan WHERE id_karyawan = ?";
            executeQuery($sql_delete_karyawan, [$id]);
            
            // Hapus dari users
            $sql = "DELETE FROM users WHERE id_user = ?";
            executeQuery($sql, [$id]);
            
            $_SESSION['success'] = "✅ User berhasil dihapus (dan data karyawan terkait juga dihapus)";
            log_activity("Menghapus user ID: $id");
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "❌ Gagal menghapus user: " . $e->getMessage();
    }
    header("Location: users.php");
    exit();
}

// Konfigurasi pagination
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Filter pencarian
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$filter_role = isset($_GET['role']) ? clean_input($_GET['role']) : '';
$filter_status = isset($_GET['status']) ? clean_input($_GET['status']) : '';

// Query dasar dengan join ke data_karyawan
$sql_where = "";
$params = [];
$where_conditions = [];

if (!empty($search)) {
    $where_conditions[] = "(u.username LIKE ? OR u.nama_lengkap LIKE ? OR u.email LIKE ? OR dk.no_hp LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($filter_role)) {
    $where_conditions[] = "u.role = ?";
    $params[] = $filter_role;
}

if (!empty($filter_status)) {
    $where_conditions[] = "u.status = ?";
    $params[] = $filter_status;
}

if (count($where_conditions) > 0) {
    $sql_where = "WHERE " . implode(" AND ", $where_conditions);
}

// Hitung total data
try {
    $sql_count = "SELECT COUNT(*) FROM users u LEFT JOIN data_karyawan dk ON u.id_user = dk.id_karyawan $sql_where";
    $stmt = executeQuery($sql_count, $params);
    $total_users = $stmt->fetchColumn();
    $total_pages = ceil($total_users / $limit);
} catch (PDOException $e) {
    $total_users = 0;
    $total_pages = 1;
}

// PERBAIKAN: Ambil data users dengan JOIN ke data_karyawan - pastikan semua field tampil
try {
    $sql = "SELECT u.*, 
                   dk.no_hp, 
                   dk.alamat, 
                   dk.gaji, 
                   dk.tanggal_masuk,
                   dk.id_karyawan
            FROM users u
            LEFT JOIN data_karyawan dk ON u.id_user = dk.id_karyawan
            $sql_where 
            ORDER BY u.created_at DESC 
            LIMIT $limit OFFSET $offset";
    $users = getAll($sql, $params);
} catch (PDOException $e) {
    $_SESSION['error'] = "❌ Gagal memuat data: " . $e->getMessage();
    $users = [];
}

// Ambil data untuk statistik
try {
    // Total users
    $sql_total = "SELECT COUNT(*) FROM users";
    $total_all_users = executeQuery($sql_total)->fetchColumn();
    
    // Users by role
    $sql_admin = "SELECT COUNT(*) FROM users WHERE role = 'admin'";
    $total_admin = executeQuery($sql_admin)->fetchColumn();
    
    $sql_pemilik = "SELECT COUNT(*) FROM users WHERE role = 'pemilik'";
    $total_pemilik = executeQuery($sql_pemilik)->fetchColumn();
    
    $sql_pegawai = "SELECT COUNT(*) FROM users WHERE role = 'pegawai'";
    $total_pegawai = executeQuery($sql_pegawai)->fetchColumn();
    
    // Users by status (gunakan format users)
    $sql_aktif = "SELECT COUNT(*) FROM users WHERE status = 'aktif'";
    $total_aktif = executeQuery($sql_aktif)->fetchColumn();
    
    $sql_non_aktif = "SELECT COUNT(*) FROM users WHERE status = 'non-aktif'";
    $total_non_aktif = executeQuery($sql_non_aktif)->fetchColumn();
    
} catch (PDOException $e) {
    $total_all_users = 0;
    $total_admin = 0;
    $total_pemilik = 0;
    $total_pegawai = 0;
    $total_aktif = 0;
    $total_non_aktif = 0;
}

$page_title = "Manajemen User";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen User SIM Parapatan Tailor</title>

    <!-- Bootstrap 5.3.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../includes/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="shortcut icon" href="../assets/images/logoterakhir.png" type="image/x-icon" />

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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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

        .stat-card.admin {
            border-left-color: #dc2626;
        }

        .stat-card.pemilik {
            border-left-color: #059669;
        }

        .stat-card.pegawai {
            border-left-color: #d97706;
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

        .stat-icon.admin {
            background: linear-gradient(135deg, #fee2e2, #fca5a5);
            color: #dc2626;
        }

        .stat-icon.pemilik {
            background: linear-gradient(135deg, #dcfce7, #bbf7d0);
            color: #059669;
        }

        .stat-icon.pegawai {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: #d97706;
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

        .status-badge.non-aktif {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fca5a5;
        }

        .role-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            min-width: 80px;
            text-align: center;
        }

        .role-badge.admin {
            background: linear-gradient(135deg, #dc2626, #ef4444);
            color: white;
        }

        .role-badge.pemilik {
            background: linear-gradient(135deg, #059669, #10b981);
            color: white;
        }

        .role-badge.pegawai {
            background: linear-gradient(135deg, #d97706, #f59e0b);
            color: white;
        }

        .current-user {
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
            border: 2px solid #0ea5e9;
            border-radius: 8px;
        }

        .current-user-badge {
            background: #0ea5e9;
            color: white;
            padding: 0.15rem 0.4rem;
            border-radius: 6px;
            font-size: 0.6rem;
            font-weight: 600;
            margin-left: 0.3rem;
        }

        .user-info-small {
            font-size: 0.6rem;
            color: #6b7280;
            margin-top: 0.1rem;
            display: block;
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

        .password-strength {
            height: 4px;
            border-radius: 2px;
            margin-top: 0.5rem;
            transition: all 0.3s;
        }

        .password-weak {
            background: #dc2626;
            width: 25%;
        }

        .password-medium {
            background: #d97706;
            width: 50%;
        }

        .password-strong {
            background: #059669;
            width: 100%;
        }
        
        .gaji-badge {
            background: linear-gradient(135deg, #dbeafe, #93c5fd);
            color: #1e40af;
            padding: 0.2rem 0.5rem;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.65rem;
            display: inline-block;
            border: 1px solid #60a5fa;
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
        
        .info-small {
            font-size: 0.6rem;
            color: #6b7280;
            display: block;
            margin-top: 0.2rem;
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
            .table th:nth-child(7),
            .table td:nth-child(7) {
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
            .table th:nth-child(8),
            .table td:nth-child(8) {
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
        
        .optional-field {
            color: #6b7280;
            font-style: italic;
        }
        
        .required-pegawai {
            color: #dc2626;
        }
        
        .optional-pegawai {
            color: #059669;
        }
        
        /* Tampilkan Password */
        .password-field {
            position: relative;
        }
        
        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
        }
        
        .toggle-password:hover {
            color: #007BFF;
        }
    </style>
</head>

<body>
    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/header.php'; ?>

    <div class="main-content">
        <div class="content-body">
            <div class="container">
                <h2 class="my-3">Manajemen User</h2>

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

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card total">
                        <div class="stat-icon total">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-number"><?= $total_all_users; ?></div>
                        <div class="stat-label">Total User</div>
                    </div>
                    <div class="stat-card pegawai">
                        <div class="stat-icon pegawai">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <div class="stat-number"><?= $total_pegawai; ?></div>
                        <div class="stat-label">User Pegawai</div>
                    </div>
                    <div class="stat-card admin">
                        <div class="stat-icon admin">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <div class="stat-number"><?= $total_admin; ?></div>
                        <div class="stat-label">User Admin</div>
                    </div>
                    <div class="stat-card aktif">
                        <div class="stat-icon aktif">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="stat-number"><?= $total_aktif; ?></div>
                        <div class="stat-label">User Aktif</div>
                    </div>
                </div>

                <!-- Filter Section -->
                <div class="filter-section">
                    <form method="GET" class="filter-form">
                        <div class="filter-group">
                            <label for="searchInput">Cari User:</label>
                            <input type="text" id="searchInput" name="search" placeholder="Cari berdasarkan username, nama, email, atau no HP..." 
                                   value="<?= htmlspecialchars($search); ?>" 
                                   class="form-control">
                        </div>
                        <div class="filter-group">
                            <label for="role">Filter Role:</label>
                            <select id="role" name="role" class="form-control">
                                <option value="">Semua Role</option>
                                <option value="admin" <?= $filter_role == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                <option value="pemilik" <?= $filter_role == 'pemilik' ? 'selected' : ''; ?>>Pemilik</option>
                                <option value="pegawai" <?= $filter_role == 'pegawai' ? 'selected' : ''; ?>>Pegawai</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="status">Filter Status:</label>
                            <select id="status" name="status" class="form-control">
                                <option value="">Semua Status</option>
                                <option value="aktif" <?= $filter_status == 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                                <option value="non-aktif" <?= $filter_status == 'non-aktif' ? 'selected' : ''; ?>>Non-Aktif</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>&nbsp;</label>
                            <div style="display: flex; gap: 0.4rem;">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Terapkan Filter
                                </button>
                                <?php if (!empty($search) || !empty($filter_role) || !empty($filter_status)): ?>
                                    <a href="users.php" class="btn btn-secondary">
                                        <i class="fas fa-refresh"></i> Reset Filter
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Search Info -->
                <?php if (!empty($search) || !empty($filter_role) || !empty($filter_status)): ?>
                    <div class="search-info">
                        <i class="fas fa-info-circle"></i> Menampilkan hasil filter:
                        <?php if (!empty($search)): ?>
                            Pencarian: <strong>"<?= htmlspecialchars($search); ?>"</strong>
                        <?php endif; ?>
                        <?php if (!empty($filter_role)): ?>
                            <?php if (!empty($search)): ?>, <?php endif; ?>
                            Role: <strong><?= ucfirst($filter_role); ?></strong>
                        <?php endif; ?>
                        <?php if (!empty($filter_status)): ?>
                            <?php if (!empty($search) || !empty($filter_role)): ?>, <?php endif; ?>
                            Status: <strong><?= ucfirst($filter_status); ?></strong>
                        <?php endif; ?>
                        - Ditemukan <strong><?= $total_users; ?></strong> user
                    </div>
                <?php endif; ?>

                <!-- Header Actions -->
                <div class="header-actions">
                    <h3>Daftar User</h3>
                    <div style="display: flex; gap: 0.8rem; align-items: center;">
                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#tambahUserModal">
                            <i class="fas fa-plus"></i> Tambah User
                        </button>
                        <a href="users.php?sync_karyawan=1" class="btn btn-info" onclick="return confirm('Sinkronkan semua user pegawai dan pemilik ke data karyawan?')">
                            <i class="fas fa-sync"></i> Sinkron Karyawan
                        </a>
                    </div>
                </div>

                <?php if (empty($users)): ?>
                    <div class="empty-state">
                        <div><i class="fas fa-users"></i></div>
                        <p style="font-size: 0.9rem; margin-bottom: 0.4rem;">Belum ada data user</p>
                        <?php if (!empty($search) || !empty($filter_role) || !empty($filter_status)): ?>
                            <p style="color: #6b7280; font-size: 0.75rem; margin-top: 0.4rem;">
                                <?php if (!empty($search)): ?>
                                    Pencarian: "<?= htmlspecialchars($search); ?>"
                                <?php endif; ?>
                                <?php if (!empty($filter_role)): ?>
                                    <?php if (!empty($search)): ?>, <?php endif; ?>
                                    Filter role: <?= ucfirst($filter_role); ?>
                                <?php endif; ?>
                                <?php if (!empty($filter_status)): ?>
                                    <?php if (!empty($search) || !empty($filter_role)): ?>, <?php endif; ?>
                                    Filter status: <?= ucfirst($filter_status); ?>
                                <?php endif; ?>
                            </p>
                            <a href="users.php" class="btn btn-primary" style="margin-top: 1.2rem;">
                                <i class="fas fa-list"></i> Tampilkan Semua Data
                            </a>
                        <?php else: ?>
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#tambahUserModal" style="margin-top: 1.2rem;">
                                <i class="fas fa-plus"></i> Tambah User Pertama
                            </button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="table-responsive">
                            <table class="table" id="userTable">
                                <thead>
                                    <tr>
                                        <th class="no-urut">No</th>
                                        <th>Username</th>
                                        <th>Nama Lengkap</th>
                                        <th>Email</th>
                                        <th>No HP</th>
                                        <th>Gaji</th>
                                        <th>Tanggal Masuk</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th style="text-align: center;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $nomor_urut = ($page - 1) * $limit + 1;
                                    foreach ($users as $row): 
                                        $is_current_user = ($row['id_user'] == $_SESSION['user_id']);
                                        // PERBAIKAN: Gunakan nilai dari data_karyawan jika ada, jika tidak gunakan nilai default
                                        $gaji_value = isset($row['gaji']) && $row['gaji'] ? $row['gaji'] : null;
                                        $no_hp_value = isset($row['no_hp']) && $row['no_hp'] ? $row['no_hp'] : null;
                                        $tanggal_masuk_value = isset($row['tanggal_masuk']) && $row['tanggal_masuk'] ? $row['tanggal_masuk'] : null;
                                        $alamat_value = isset($row['alamat']) && $row['alamat'] ? $row['alamat'] : null;
                                        
                                        // Format gaji
                                        $gaji_formatted = $gaji_value ? 'Rp ' . number_format($gaji_value, 0, ',', '.') : '-';
                                        // Format no HP
                                        $no_hp_formatted = $no_hp_value ? htmlspecialchars($no_hp_value) : '-';
                                        // Format tanggal masuk
                                        $tanggal_masuk_formatted = $tanggal_masuk_value ? date('d-m-Y', strtotime($tanggal_masuk_value)) : '-';
                                        // Alamat singkat
                                        $alamat_singkat = $alamat_value ? (strlen($alamat_value) > 30 ? substr(htmlspecialchars($alamat_value), 0, 30) . '...' : htmlspecialchars($alamat_value)) : '-';
                                    ?>
                                    <tr class="<?= $is_current_user ? 'current-user' : ''; ?>">
                                        <td class="no-urut"><?= $nomor_urut++; ?></td>
                                        <td style="font-weight: 600; color: #1f2937;">
                                            <?= htmlspecialchars($row['username']); ?>
                                            <?php if ($is_current_user): ?>
                                                <span class="current-user-badge">Anda</span>
                                            <?php endif; ?>
                                            <br>
                                            <span class="user-info-small">
                                                ID: <?= $row['id_user']; ?>
                                                <?php if (isset($row['id_karyawan']) && $row['id_karyawan']): ?>
                                                    <br><i class="fas fa-link"></i> Terhubung ke karyawan
                                                <?php endif; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($row['nama_lengkap']); ?>
                                            <?php if ($alamat_value): ?>
                                                <br>
                                                <span class="info-small" title="<?= htmlspecialchars($alamat_value); ?>">
                                                    <i class="fas fa-map-marker-alt"></i> <?= $alamat_singkat; ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($row['email']); ?></td>
                                        <td><?= $no_hp_formatted; ?></td>
                                        <td>
                                            <?php if ($gaji_value): ?>
                                                <span class="gaji-badge"><?= $gaji_formatted; ?></span>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($tanggal_masuk_value): ?>
                                                <span class="date-badge"><?= $tanggal_masuk_formatted; ?></span>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="role-badge <?= $row['role']; ?>">
                                                <?= ucfirst($row['role']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge <?= $row['status']; ?>">
                                                <?= ucfirst($row['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button type="button" 
                                                        class="btn btn-warning btn-sm edit-user-btn" 
                                                        title="Edit User"
                                                        data-id="<?= $row['id_user']; ?>"
                                                        data-username="<?= htmlspecialchars($row['username']); ?>"
                                                        data-nama="<?= htmlspecialchars($row['nama_lengkap']); ?>"
                                                        data-email="<?= htmlspecialchars($row['email']); ?>"
                                                        data-role="<?= $row['role']; ?>"
                                                        data-status="<?= $row['status']; ?>"
                                                        data-gaji="<?= $gaji_value; ?>"
                                                        data-no_hp="<?= htmlspecialchars($no_hp_value); ?>"
                                                        data-alamat="<?= htmlspecialchars($alamat_value); ?>"
                                                        data-tanggal_masuk="<?= $tanggal_masuk_value; ?>">
                                                   <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" 
                                                        class="btn btn-info btn-sm reset-password-btn" 
                                                        title="Reset Password"
                                                        data-id="<?= $row['id_user']; ?>"
                                                        data-username="<?= htmlspecialchars($row['username']); ?>">
                                                   <i class="fas fa-key"></i>
                                                </button>
                                                <?php if (!$is_current_user): ?>
                                                <button type="button" 
                                                        class="btn btn-danger btn-sm delete-user-btn" 
                                                        title="Hapus User"
                                                        data-id="<?= $row['id_user']; ?>"
                                                        data-username="<?= htmlspecialchars($row['username']); ?>">
                                                   <i class="fas fa-trash"></i> 
                                                </button>
                                                <?php else: ?>
                                                <button type="button" class="btn btn-secondary btn-sm" disabled title="Tidak dapat menghapus user sendiri">
                                                   <i class="fas fa-trash"></i> 
                                                </button>
                                                <?php endif; ?>
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
                                    <a class="page-link" href="?page=<?= $page - 1; ?><?= !empty($search) ? '&search=' . urlencode($search) : ''; ?><?= !empty($filter_role) ? '&role=' . urlencode($filter_role) : ''; ?><?= !empty($filter_status) ? '&status=' . $filter_status : ''; ?>">
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
                                    <a class="page-link" href="?page=<?= $i; ?><?= !empty($search) ? '&search=' . urlencode($search) : ''; ?><?= !empty($filter_role) ? '&role=' . urlencode($filter_role) : ''; ?><?= !empty($filter_status) ? '&status=' . $filter_status : ''; ?>"><?= $i; ?></a>
                                </div>
                            <?php endfor; ?>

                            <!-- Next Page -->
                            <?php if ($page < $total_pages): ?>
                                <div class="page-item">
                                    <a class="page-link" href="?page=<?= $page + 1; ?><?= !empty($search) ? '&search=' . urlencode($search) : ''; ?><?= !empty($filter_role) ? '&role=' . urlencode($filter_role) : ''; ?><?= !empty($filter_status) ? '&status=' . $filter_status : ''; ?>">
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
                                (Total: <?= $total_users; ?> user)
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

    <!-- Modal Tambah User -->
    <div class="modal fade" id="tambahUserModal" tabindex="-1" aria-labelledby="tambahUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tambahUserModalLabel">
                        <i class="fas fa-plus"></i> Tambah User Baru
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Username <span class="text-danger">*</span></label>
                                    <input type="text" name="username" class="form-control" required 
                                           placeholder="Masukkan username">
                                </div>
                                
                                <div class="form-group password-field">
                                    <label class="form-label">Password <span class="text-danger">*</span></label>
                                    <input type="password" name="password" id="password" class="form-control" required 
                                           placeholder="Masukkan password">
                                    <span class="toggle-password" onclick="togglePassword('password')">
                                        <i class="fas fa-eye"></i>
                                    </span>
                                    <small class="text-muted">Password disimpan sebagai plain text</small>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                    <input type="text" name="nama_lengkap" class="form-control" required 
                                           placeholder="Masukkan nama lengkap">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" name="email" class="form-control" required 
                                           placeholder="Masukkan email">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Role <span class="text-danger">*</span></label>
                                    <select name="role" id="roleSelect" class="form-control" required>
                                        <option value="">-- Pilih Role --</option>
                                        <option value="pegawai">Pegawai</option>
                                        <option value="pemilik">Pemilik</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                    <!-- PERBAIKAN: Update pesan info -->
                                    <small class="text-muted">Note: User dengan role "Pegawai", "Pemilik", atau "Admin" akan otomatis ditambahkan ke data karyawan</small>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Tanggal Masuk</label>
                                    <input type="date" name="tanggal_masuk" class="form-control" 
                                           placeholder="Masukkan tanggal masuk">
                                    <!-- PERBAIKAN: Update pesan info -->
                                    <small class="text-muted optional-field">Opsional, hanya untuk role Pegawai, Pemilik, atau Admin</small>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Gaji</label>
                                    <input type="number" name="gaji" class="form-control" 
                                           placeholder="Masukkan gaji (opsional)" 
                                           min="0" step="1000">
                                    <!-- PERBAIKAN: Update pesan info -->
                                    <small class="text-muted optional-field">Opsional, hanya untuk role Pegawai, Pemilik, atau Admin</small>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">No HP</label>
                                    <input type="text" name="no_hp" class="form-control" 
                                           placeholder="Masukkan nomor HP (opsional)">
                                    <!-- PERBAIKAN: Update pesan info -->
                                    <small class="text-muted optional-field">Opsional, hanya untuk role Pegawai, Pemilik, atau Admin</small>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Alamat</label>
                                    <textarea name="alamat" class="form-control" rows="2" 
                                              placeholder="Masukkan alamat (opsional)"></textarea>
                                    <!-- PERBAIKAN: Update pesan info -->
                                    <small class="text-muted optional-field">Opsional, hanya untuk role Pegawai, Pemilik, atau Admin</small>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Status <span class="text-danger">*</span></label>
                                    <select name="status" class="form-control" required>
                                        <option value="aktif">Aktif</option>
                                        <option value="non-aktif">Non-Aktif</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Batal
                        </button>
                        <button type="submit" name="tambah_user" class="btn btn-success">
                            <i class="fas fa-save"></i> Simpan User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit User -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editUserModalLabel">
                        <i class="fas fa-edit"></i> Edit Data User
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="id_user" id="edit_id_user">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Username <span class="text-danger">*</span></label>
                                    <input type="text" name="username" id="edit_username" class="form-control" required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                    <input type="text" name="nama_lengkap" id="edit_nama_lengkap" class="form-control" required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" name="email" id="edit_email" class="form-control" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Role <span class="text-danger">*</span></label>
                                    <select name="role" id="edit_role" class="form-control" required>
                                        <option value="">-- Pilih Role --</option>
                                        <option value="pegawai">Pegawai</option>
                                        <option value="pemilik">Pemilik</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                    <!-- PERBAIKAN: Update pesan info -->
                                    <small class="text-muted">Note: Perubahan role akan mempengaruhi data karyawan. Admin juga akan masuk ke data karyawan.</small>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Tanggal Masuk</label>
                                    <input type="date" name="tanggal_masuk" id="edit_tanggal_masuk" class="form-control" 
                                           placeholder="Masukkan tanggal masuk">
                                    <!-- PERBAIKAN: Update pesan info -->
                                    <small class="text-muted optional-field">Opsional, hanya untuk role Pegawai, Pemilik, atau Admin</small>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Gaji</label>
                                    <input type="number" name="gaji" id="edit_gaji" class="form-control" 
                                           placeholder="Masukkan gaji (opsional)" 
                                           min="0" step="1000">
                                    <!-- PERBAIKAN: Update pesan info -->
                                    <small class="text-muted optional-field">Opsional, hanya untuk role Pegawai, Pemilik, atau Admin</small>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">No HP</label>
                                    <input type="text" name="no_hp" id="edit_no_hp" class="form-control" 
                                           placeholder="Masukkan nomor HP (opsional)">
                                    <!-- PERBAIKAN: Update pesan info -->
                                    <small class="text-muted optional-field">Opsional, hanya untuk role Pegawai, Pemilik, atau Admin</small>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Alamat</label>
                                    <textarea name="alamat" id="edit_alamat" class="form-control" rows="2" 
                                              placeholder="Masukkan alamat (opsional)"></textarea>
                                    <!-- PERBAIKAN: Update pesan info -->
                                    <small class="text-muted optional-field">Opsional, hanya untuk role Pegawai, Pemilik, atau Admin</small>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Status <span class="text-danger">*</span></label>
                                    <select name="status" id="edit_status" class="form-control" required>
                                        <option value="aktif">Aktif</option>
                                        <option value="non-aktif">Non-Aktif</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Batal
                        </button>
                        <button type="submit" name="edit_user" class="btn btn-success">
                            <i class="fas fa-save"></i> Update User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Reset Password -->
    <div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="resetPasswordModalLabel">
                        <i class="fas fa-key"></i> Reset Password
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="id_user" id="reset_id_user">
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Reset password untuk user: <strong id="reset_username"></strong>
                        </div>
                        
                        <div class="form-group password-field">
                            <label class="form-label">Password Baru</label>
                            <input type="password" name="password" id="reset_password" class="form-control" required 
                                   placeholder="Masukkan password baru">
                            <span class="toggle-password" onclick="togglePassword('reset_password')">
                                <i class="fas fa-eye"></i>
                            </span>
                        </div>
                        
                        <div class="form-group password-field">
                            <label class="form-label">Konfirmasi Password</label>
                            <input type="password" name="confirm_password" id="reset_confirm_password" class="form-control" required 
                                   placeholder="Konfirmasi password baru">
                            <span class="toggle-password" onclick="togglePassword('reset_confirm_password')">
                                <i class="fas fa-eye"></i>
                            </span>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> Password akan disimpan sebagai plain text.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Batal
                        </button>
                        <button type="submit" name="reset_password" class="btn btn-warning">
                            <i class="fas fa-sync"></i> Reset Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Konfirmasi Penghapusan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div style="text-align: center; color: #dc3545; font-size: 2.5rem; margin-bottom: 0.8rem;">⚠️</div>
                    <h5 class="text-danger mb-2" style="font-size: 1.1rem; text-align: center;">Konfirmasi Penghapusan</h5>
                    <p style="text-align: center;">Apakah Anda yakin ingin menghapus user <strong id="deleteUserName"></strong>?</p>
                    <p class="text-muted small" style="text-align: center;">Tindakan ini tidak dapat dibatalkan dan data akan hilang permanen.</p>
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

        // Fungsi untuk toggle password visibility
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const toggleIcon = field.nextElementSibling.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Tampilkan modal konfirmasi hapus
        document.addEventListener('DOMContentLoaded', function() {
            const deleteButtons = document.querySelectorAll('.delete-user-btn');
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            const deleteUserNameElement = document.getElementById('deleteUserName');
            
            deleteButtons.forEach(button => {
                button.addEventListener('click', function() {
                    currentDeleteId = this.getAttribute('data-id');
                    currentDeleteName = this.getAttribute('data-username');
                    
                    deleteUserNameElement.textContent = currentDeleteName;
                    deleteModal.show();
                });
            });

            // Konfirmasi hapus
            document.getElementById('confirmDelete').addEventListener('click', function() {
                deleteModal.hide();
                window.location.href = `users.php?hapus=${currentDeleteId}`;
            });

            // Modal untuk edit user
            const editButtons = document.querySelectorAll('.edit-user-btn');
            const editModal = new bootstrap.Modal(document.getElementById('editUserModal'));
            
            editButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const username = this.getAttribute('data-username');
                    const nama = this.getAttribute('data-nama');
                    const email = this.getAttribute('data-email');
                    const role = this.getAttribute('data-role');
                    const status = this.getAttribute('data-status');
                    const gaji = this.getAttribute('data-gaji');
                    const no_hp = this.getAttribute('data-no_hp');
                    const alamat = this.getAttribute('data-alamat');
                    const tanggal_masuk = this.getAttribute('data-tanggal_masuk');
                    
                    // Set values to edit form
                    document.getElementById('edit_id_user').value = id;
                    document.getElementById('edit_username').value = username;
                    document.getElementById('edit_nama_lengkap').value = nama;
                    document.getElementById('edit_email').value = email;
                    document.getElementById('edit_role').value = role;
                    document.getElementById('edit_status').value = status;
                    document.getElementById('edit_gaji').value = gaji;
                    document.getElementById('edit_no_hp').value = no_hp;
                    document.getElementById('edit_alamat').value = alamat;
                    document.getElementById('edit_tanggal_masuk').value = tanggal_masuk;
                    
                    editModal.show();
                });
            });

            // Modal untuk reset password
            const resetButtons = document.querySelectorAll('.reset-password-btn');
            const resetModal = new bootstrap.Modal(document.getElementById('resetPasswordModal'));
            
            resetButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const username = this.getAttribute('data-username');
                    
                    document.getElementById('reset_id_user').value = id;
                    document.getElementById('reset_username').textContent = username;
                    
                    resetModal.show();
                });
            });

            // Form validation for reset password
            const resetForm = document.querySelector('#resetPasswordModal form');
            if (resetForm) {
                resetForm.addEventListener('submit', function(e) {
                    const password = this.querySelector('input[name="password"]').value;
                    const confirmPassword = this.querySelector('input[name="confirm_password"]').value;
                    
                    if (password !== confirmPassword) {
                        e.preventDefault();
                        alert('Password dan konfirmasi password tidak sama!');
                    }
                });
            }

            // Highlight table rows on hover
            const tableRows = document.querySelectorAll('#userTable tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#f8faff';
                });
                row.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = '';
                });
            });

            // Toggle visibility of optional fields based on role
            const roleSelect = document.getElementById('roleSelect');
            const editRoleSelect = document.getElementById('edit_role');
            
            function toggleOptionalFields(selectElement) {
                const isPegawaiOrPemilikOrAdmin = selectElement.value === 'pegawai' || selectElement.value === 'pemilik' || selectElement.value === 'admin';
                const optionalFields = selectElement.closest('.modal-content').querySelectorAll('.optional-field');
                
                optionalFields.forEach(field => {
                    if (isPegawaiOrPemilikOrAdmin) {
                        field.style.color = '#374151';
                        field.style.fontStyle = 'normal';
                    } else {
                        field.style.color = '#6b7280';
                        field.style.fontStyle = 'italic';
                    }
                });
            }
            
            if (roleSelect) {
                roleSelect.addEventListener('change', function() {
                    toggleOptionalFields(this);
                });
                // Initial state
                toggleOptionalFields(roleSelect);
            }
            
            if (editRoleSelect) {
                editRoleSelect.addEventListener('change', function() {
                    toggleOptionalFields(this);
                });
                // Initial state for edit modal
                const editModalElement = document.getElementById('editUserModal');
                editModalElement.addEventListener('show.bs.modal', function() {
                    setTimeout(() => {
                        toggleOptionalFields(editRoleSelect);
                    }, 100);
                });
            }
        });

        function searchTable() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('userTable');
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

        // Client-side search untuk UX yang lebih baik
        document.getElementById('searchInput').addEventListener('input', function(e) {
            searchTable();
        });

        // Reset nomor urut ketika search dihapus
        document.getElementById('searchInput').addEventListener('input', function(e) {
            if (this.value === '') {
                const table = document.getElementById('userTable');
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