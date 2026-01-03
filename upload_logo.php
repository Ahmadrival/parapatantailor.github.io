<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Logo</title>
    <link rel="shortcut icon" href="../assets/images/logoterakhir.png" type="image/x-icon" />
</head>
<body>
    
</body>
</html>
<?php
// upload_logo.php - PRODUCTION READY VERSION

// ============ ERROR REPORTING & BUFFERING ============
ob_start(); // Buffer semua output
error_reporting(E_ALL);
ini_set('display_errors', 0); // Jangan tampilkan error di browser
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

// ============ SESSION START ============
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============ SECURITY CHECKS ============
header('Content-Type: application/json');

// Cek AJAX request
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    $is_ajax = true;
} else {
    $is_ajax = false;
    // Bukan AJAX request, mungkin user akses langsung
    $response = [
        'success' => false,
        'message' => 'Akses tidak diizinkan'
    ];
    ob_clean();
    echo json_encode($response);
    exit;
}

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    $response = [
        'success' => false,
        'message' => 'Session expired. Silakan login kembali.'
    ];
    ob_clean();
    echo json_encode($response);
    exit;
}

// Cek role (hanya admin dan pemilik)
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'pemilik'])) {
    $response = [
        'success' => false,
        'message' => 'Akses ditolak. Hanya admin dan pemilik yang dapat mengubah logo.'
    ];
    ob_clean();
    echo json_encode($response);
    exit;
}

// Cek method POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response = [
        'success' => false,
        'message' => 'Method tidak diizinkan. Gunakan POST.'
    ];
    ob_clean();
    echo json_encode($response);
    exit;
}

// Cek apakah file diupload
if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
    $error_msg = 'Tidak ada file yang diupload.';
    
    // Cek error code
    if (isset($_FILES['logo']['error'])) {
        switch ($_FILES['logo']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $error_msg = 'Ukuran file terlalu besar.';
                break;
            case UPLOAD_ERR_PARTIAL:
                $error_msg = 'File hanya terupload sebagian.';
                break;
            case UPLOAD_ERR_NO_FILE:
                $error_msg = 'Tidak ada file yang dipilih.';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $error_msg = 'Folder temporary tidak ditemukan.';
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $error_msg = 'Gagal menulis file ke disk.';
                break;
            case UPLOAD_ERR_EXTENSION:
                $error_msg = 'Upload dihentikan oleh ekstensi PHP.';
                break;
        }
    }
    
    $response = [
        'success' => false,
        'message' => $error_msg
    ];
    ob_clean();
    echo json_encode($response);
    exit;
}

// ============ VALIDASI FILE ============
$file = $_FILES['logo'];
$allowed_types = ['image/png', 'image/jpeg', 'image/jpg', 'image/svg+xml'];
$max_size = 2 * 1024 * 1024; // 2MB

// Validasi type MIME
if (!in_array($file['type'], $allowed_types)) {
    $response = [
        'success' => false,
        'message' => 'Format file tidak didukung. Hanya PNG, JPG, JPEG, atau SVG yang diperbolehkan.'
    ];
    ob_clean();
    echo json_encode($response);
    exit;
}

// Validasi size
if ($file['size'] > $max_size) {
    $response = [
        'success' => false,
        'message' => 'Ukuran file terlalu besar. Maksimal 2MB.'
    ];
    ob_clean();
    echo json_encode($response);
    exit;
}

// Validasi ekstensi
$allowed_extensions = ['png', 'jpg', 'jpeg', 'svg'];
$file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($file_extension, $allowed_extensions)) {
    $response = [
        'success' => false,
        'message' => 'Ekstensi file tidak diizinkan.'
    ];
    ob_clean();
    echo json_encode($response);
    exit;
}

// ============ UPLOAD FILE ============
$upload_dir = 'assets/images/';

// Pastikan folder upload ada
if (!file_exists($upload_dir) && !is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        $response = [
            'success' => false,
            'message' => 'Gagal membuat folder upload.'
        ];
        ob_clean();
        echo json_encode($response);
        exit;
    }
}

// Generate nama file unik
$timestamp = time();
$unique_id = uniqid();
$new_filename = 'logo_custom_' . $timestamp . '.' . $file_extension;
$upload_path = $upload_dir . $new_filename;

// Upload file
if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
    $response = [
        'success' => false,
        'message' => 'Gagal mengupload file. Cek permission folder.'
    ];
    ob_clean();
    echo json_encode($response);
    exit;
}

// ============ SIMPAN KE DATABASE ============
try {
    // Include database config
    $config_paths = [
        'config/database.php',
        '../config/database.php',
        dirname(__DIR__) . '/config/database.php'
    ];
    
    $db_included = false;
    foreach ($config_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $db_included = true;
            break;
        }
    }
    
    if (!$db_included) {
        throw new Exception('Konfigurasi database tidak ditemukan');
    }
    
    // Cek koneksi
    if (!isset($pdo) || !$pdo) {
        throw new Exception('Koneksi database tidak tersedia');
    }
    
    // Ambil nama logo dari form
    $logo_name = isset($_POST['logo_name']) ? trim($_POST['logo_name']) : 'Parapatan Tailor';
    $logo_name = strip_tags($logo_name);
    $logo_name = htmlspecialchars($logo_name, ENT_QUOTES, 'UTF-8');
    
    if (empty($logo_name)) {
        $logo_name = 'Parapatan Tailor';
    }
    
    // Cek apakah tabel logo_settings ada
    $table_exists = $pdo->query("SHOW TABLES LIKE 'logo_settings'")->rowCount() > 0;
    
    if (!$table_exists) {
        // Buat tabel jika belum ada
        $create_table_sql = "CREATE TABLE logo_settings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            logo_path VARCHAR(255) NOT NULL,
            logo_name VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($create_table_sql);
    }
    
    // Simpan ke database
    $sql = "INSERT INTO logo_settings (logo_path, logo_name) VALUES (?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$upload_path, $logo_name]);
    
    $last_insert_id = $pdo->lastInsertId();
    
    // Hapus logo lama (simpan maksimal 5 terakhir)
    // 1. Hapus file fisik kecuali default dan 5 terbaru
    $sql = "SELECT logo_path FROM logo_settings 
            WHERE logo_path NOT LIKE 'assets/images/logoTailor.png'
            AND id NOT IN (
                SELECT id FROM (
                    SELECT id FROM logo_settings 
                    ORDER BY id DESC 
                    LIMIT 5
                ) AS temp
            )";
    
    $stmt = $pdo->query($sql);
    $old_logos = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($old_logos as $old_logo) {
        if (file_exists($old_logo) && $old_logo !== $upload_path) {
            @unlink($old_logo);
        }
    }
    
    // 2. Hapus record dari database kecuali 5 terbaru
    $sql = "DELETE FROM logo_settings 
            WHERE id NOT IN (
                SELECT id FROM (
                    SELECT id FROM logo_settings 
                    ORDER BY id DESC 
                    LIMIT 5
                ) AS temp2
            )";
    $pdo->exec($sql);
    
    // ============ RESPONSE SUKSES ============
    $response = [
        'success' => true,
        'message' => 'Logo berhasil diubah!',
        'data' => [
            'logo_path' => $upload_path,
            'logo_name' => $logo_name,
            'logo_id' => $last_insert_id
        ]
    ];
    
} catch (PDOException $e) {
    // Hapus file yang sudah diupload jika gagal save ke database
    if (file_exists($upload_path)) {
        @unlink($upload_path);
    }
    
    $response = [
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ];
    
    // Log error
    error_log('Upload Logo PDO Error: ' . $e->getMessage());
    
} catch (Exception $e) {
    // Hapus file yang sudah diupload jika ada error
    if (file_exists($upload_path)) {
        @unlink($upload_path);
    }
    
    $response = [
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ];
    
    // Log error
    error_log('Upload Logo Error: ' . $e->getMessage());
}

// ============ SEND RESPONSE ============
// Clear semua output sebelum mengirim response
while (ob_get_level() > 0) {
    ob_end_clean();
}

// Kirim response JSON
header('Content-Type: application/json; charset=utf-8');
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
?>