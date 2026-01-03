<?php
// login.php

// Include config yang sudah menangani session
include 'config/database.php';

// Cek jika user sudah login, redirect ke dashboard
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

// Hapus session yang mungkin tersisa dari login sebelumnya
if (isset($_SESSION['error']) || isset($_SESSION['success'])) {
    unset($_SESSION['error']);
    unset($_SESSION['success']);
}

// Variabel untuk menyimpan input sebelumnya
$previous_input = [
    'username' => '',
    'role' => ''
];

$error = '';
$validation_errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = clean_input($_POST['username'] ?? '');
    $password = clean_input($_POST['password'] ?? '');
    $role = clean_input($_POST['role'] ?? '');
    
    // Simpan input sebelumnya untuk ditampilkan kembali
    $previous_input['username'] = $username;
    $previous_input['role'] = $role;
    
    // Validasi input
    $has_error = false;
    
    // Cek jika username kosong
    if (empty($username)) {
        $validation_errors['username'] = 'Username belum diisi!';
        $has_error = true;
    }
    
    // Cek jika password kosong
    if (empty($password)) {
        $validation_errors['password'] = 'Password belum diisi!';
        $has_error = true;
    }
    
    // Cek jika role kosong
    if (empty($role)) {
        $validation_errors['role'] = 'Role harus dipilih!';
        $has_error = true;
    }
    
    // Jika ada error validasi, tampilkan pesan
    if ($has_error) {
        if (empty($username) && empty($password) && empty($role)) {
            $error = "Username dan password belum diisi, dan role belum dipilih!";
        } else if (empty($username) && empty($password)) {
            $error = "Username dan password belum diisi!";
        } else if (empty($username) && empty($role)) {
            $error = "Username belum diisi dan role belum dipilih!";
        } else if (empty($password) && empty($role)) {
            $error = "Password belum diisi dan role belum dipilih!";
        } else if (empty($username)) {
            $error = "Username belum diisi!";
        } else if (empty($password)) {
            $error = "Password belum diisi!";
        } else if (empty($role)) {
            $error = "Role belum dipilih!";
        }
    } else {
        // Jika validasi OK, lanjutkan proses login
        try {
            // Query langsung ke tabel users tanpa JOIN dengan data_karyawan
            $query = "SELECT * FROM users WHERE username = :username AND role = :role AND status = 'aktif'";
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':role', $role);
            $stmt->execute();
            
            if ($stmt->rowCount() == 1) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Verifikasi password (asumsi password disimpan sebagai plain text)
                // Jika menggunakan password hash, gunakan password_verify()
                if ($user['password'] === $password) {
                    // Regenerate session ID untuk mencegah session fixation
                    session_regenerate_id(true);
                    
                    $_SESSION['user_id'] = $user['id_user'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['nama_lengkap'] = $user['nama_lengkap'] ? $user['nama_lengkap'] : $user['username'];
                    $_SESSION['login_time'] = time();
                    
                    // Set session timeout (8 jam)
                    $_SESSION['session_expire'] = time() + (8 * 60 * 60);
                    
                    // Log activity
                    log_activity("User " . $user['username'] . " berhasil login");
                    
                    header("Location: dashboard.php");
                    exit();
                } else {
                    // Password salah
                    $error = "Password salah!";
                    $validation_errors['password'] = 'Password salah!';
                    log_activity("Login gagal - Password salah untuk username: " . $username);
                }
            } else {
                // Cek apakah username ada tapi role salah
                $query_check_user = "SELECT * FROM users WHERE username = :username AND status = 'aktif'";
                $stmt_check = $pdo->prepare($query_check_user);
                $stmt_check->bindParam(':username', $username);
                $stmt_check->execute();
                
                if ($stmt_check->rowCount() == 1) {
                    // Username benar, role salah
                    $error = "Role yang dipilih tidak sesuai dengan akun Anda!";
                    $validation_errors['role'] = 'Role tidak sesuai!';
                    log_activity("Login gagal - Role tidak sesuai untuk username: " . $username . " dengan role: " . $role);
                } else {
                    // Cek apakah username ada tapi tidak aktif
                    $query_check_inactive = "SELECT * FROM users WHERE username = :username";
                    $stmt_inactive = $pdo->prepare($query_check_inactive);
                    $stmt_inactive->bindParam(':username', $username);
                    $stmt_inactive->execute();
                    
                    if ($stmt_inactive->rowCount() == 1) {
                        // Username ada tapi tidak aktif
                        $error = "Akun Anda tidak aktif!";
                        $validation_errors['username'] = 'Akun tidak aktif!';
                        log_activity("Login gagal - Akun tidak aktif: " . $username);
                    } else {
                        // Username tidak ditemukan
                        $error = "Username salah!";
                        $validation_errors['username'] = 'Username salah!';
                        log_activity("Login gagal - User tidak ditemukan: " . $username);
                    }
                }
            }
        } catch (PDOException $e) {
            $error = "Terjadi kesalahan sistem. Silakan coba lagi nanti.";
            log_activity("Error login: " . $e->getMessage());
        }
    }
}

// Ambil logo dari database untuk konsistensi dengan sidebar
$logo_path = 'assets/images/logoTailor.png';
$logo_name = 'Parapatan Tailor';

try {
    $stmt = $pdo->query("SELECT logo_path, logo_name FROM logo_settings ORDER BY id DESC LIMIT 1");
    if ($row = $stmt->fetch()) {
        $logo_path = $row['logo_path'] ?? 'assets/images/logoTailor.png';
        $logo_name = $row['logo_name'] ?? 'Parapatan Tailor';
    }
} catch (PDOException $e) {
    // Gunakan default jika error
    error_log("Error fetching logo: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SIM Parapatan Tailor</title>
    <link rel="shortcut icon" href="../parapatan_tailor/assets/images/logoterakhir.png" type="image/x-icon" />

    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
    :root {
        --sidebar-purple: #4f46e5;        /* Warna ungu sidebar utama */
        --sidebar-purple-light: #7c3aed;  /* Warna ungu sidebar sekunder */
        --sidebar-purple-dark: #4338ca;   /* Warna ungu lebih gelap */
        --light-purple: #e0e7ff;          /* Ungu muda untuk background */
        --bright-purple: #8b5cf6;         /* Ungu cerah untuk aksen */
        --vibrant-purple: #a78bfa;        /* Ungu lebih terang */
        --white: #ffffff;
        --light-gray: #f8fafc;
        --medium-gray: #e2e8f0;
        --dark-gray: #64748b;
        --text-dark: #1e293b;
        --text-light: #ffffff;
        --error-red: #dc3545;
        --error-light: #f8d7da;
        --error-border: #f5c6cb;
        --warning-orange: #ffc107;
        --warning-light: #fff3cd;
        --warning-border: #ffeaa7;
        --glow-purple: 0 0 20px rgba(79, 70, 229, 0.4),
                     0 0 40px rgba(124, 58, 237, 0.2),
                     0 0 60px rgba(79, 70, 229, 0.1);
    }
    
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Segoe UI', 'Arial', sans-serif;
    }
    
    body {
        background-color: #f1f5f9;
        background-image: 
            radial-gradient(circle at 90% 10%, rgba(79, 70, 229, 0.08) 0%, transparent 25%),
            radial-gradient(circle at 10% 90%, rgba(124, 58, 237, 0.08) 0%, transparent 25%);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        position: relative;
        overflow-x: hidden;
    }
    
    /* Animated background pattern */
    .pattern-background {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-image: 
            radial-gradient(circle at 25% 25%, rgba(79, 70, 229, 0.05) 0%, transparent 50%),
            radial-gradient(circle at 75% 75%, rgba(124, 58, 237, 0.05) 0%, transparent 50%);
        z-index: -1;
        opacity: 0.6;
    }
    
    /* Decorative elements */
    .decoration {
        position: fixed;
        width: 100%;
        height: 100%;
        pointer-events: none;
        z-index: -1;
    }
    
    .deco-circle {
        position: absolute;
        border-radius: 50%;
        background: radial-gradient(circle, var(--sidebar-purple), transparent);
        opacity: 0.08;
        animation: float 25s infinite linear;
    }
    
    @keyframes float {
        0%, 100% {
            transform: translate(0, 0) rotate(0deg);
        }
        33% {
            transform: translate(30px, -30px) rotate(120deg);
        }
        66% {
            transform: translate(-20px, 20px) rotate(240deg);
        }
    }
    
    /* Login Container - Matching dengan sidebar */
    .login-container {
        display: flex;
        width: 100%;
        max-width: 900px;
        min-height: 520px;
        background: linear-gradient(145deg, var(--light-purple), var(--white));
        border-radius: 16px; /* Sama dengan sidebar */
        overflow: hidden;
        position: relative;
        box-shadow: 
            0 20px 40px rgba(79, 70, 229, 0.15),
            0 10px 25px rgba(124, 58, 237, 0.1),
            0 5px 15px rgba(79, 70, 229, 0.05),
            var(--glow-purple);
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        border: 1px solid rgba(79, 70, 229, 0.1);
    }
    
    .login-container:hover {
        box-shadow: 
            0 25px 50px rgba(79, 70, 229, 0.2),
            0 15px 35px rgba(124, 58, 237, 0.15),
            0 8px 20px rgba(79, 70, 229, 0.1),
            0 0 30px rgba(79, 70, 229, 0.3);
        transform: translateY(-5px);
    }
    
    /* Left Panel - Form */
    .login-form {
        flex: 1.2;
        padding: 40px 35px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        position: relative;
        background: linear-gradient(135deg, 
            rgba(255, 255, 255, 0.95) 0%, 
            rgba(255, 255, 255, 0.98) 100%);
        backdrop-filter: blur(20px);
        border-right: 1px solid rgba(79, 70, 229, 0.15);
    }
    
    .login-form::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, 
            transparent, 
            var(--sidebar-purple), 
            var(--sidebar-purple-light), 
            var(--sidebar-purple), 
            transparent);
        animation: scanline 4s linear infinite;
        box-shadow: 0 0 12px rgba(79, 70, 229, 0.5);
    }
    
    @keyframes scanline {
        0% {
            background-position: -200px 0;
        }
        100% {
            background-position: 200px 0;
        }
    }
    
    /* Right Panel - Welcome Message (gradient sidebar) */
    .welcome-panel {
        flex: 1;
        background: linear-gradient(135deg, 
            var(--sidebar-purple) 0%, 
            var(--sidebar-purple-light) 100%);
        padding: 40px 35px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        text-align: center;
        position: relative;
        overflow: hidden;
    }
    
    .welcome-panel::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, 
            rgba(255, 255, 255, 0.1) 0%, 
            transparent 70%);
        animation: rotate 40s linear infinite;
        z-index: 0;
    }
    
    .welcome-content {
        position: relative;
        z-index: 2;
        max-width: 95%;
    }
    
    /* Logo Container */
    .logo-container {
        text-align: center;
        margin-bottom: 35px;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 15px;
    }
    
    .logo-image {
        width: 85px;
        height: 85px;
        border-radius: 12px; /* Sama dengan sidebar */
        background: linear-gradient(135deg, var(--sidebar-purple), var(--sidebar-purple-light));
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 15px;
        border: 2px solid rgba(255, 255, 255, 0.3); /* Sama dengan sidebar */
        box-shadow: 
            0 8px 25px rgba(79, 70, 229, 0.4),
            0 5px 15px rgba(124, 58, 237, 0.3),
            inset 0 1px 0 rgba(255, 255, 255, 0.5);
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        position: relative;
        overflow: hidden;
    }
    
    .logo-image:hover {
        transform: scale(1.08) rotate(5deg);
        box-shadow: 
            0 12px 35px rgba(79, 70, 229, 0.6),
            0 8px 20px rgba(124, 58, 237, 0.5),
            var(--glow-purple),
            inset 0 1px 0 rgba(255, 255, 255, 0.7);
    }
    
    .logo-image img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
        border-radius: 8px;
        filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
    }
    
    .logo-fallback {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, var(--sidebar-purple), var(--sidebar-purple-light));
        border-radius: 8px;
    }
    
    .logo-text {
        font-size: 2.5rem;
        font-weight: 900;
        color: white;
        text-shadow: 
            2px 2px 4px rgba(0, 0, 0, 0.2),
            0 0 10px rgba(255, 255, 255, 0.3);
    }
    
    .logo-title {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 6px;
    }
    
    .brand-name {
        color: var(--sidebar-purple);
        font-size: 26px;
        font-weight: 800;
        letter-spacing: 2.5px;
        text-transform: uppercase;
        text-shadow: 
            0 2px 4px rgba(79, 70, 229, 0.2);
        position: relative;
        display: inline-block;
        padding-bottom: 8px;
    }
    
    .brand-name::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 50%;
        transform: translateX(-50%);
        width: 50px;
        height: 3px;
        background: linear-gradient(90deg, 
            transparent, 
            var(--sidebar-purple), 
            transparent);
        border-radius: 2px;
    }
    
    .brand-subtitle {
        color: var(--dark-gray);
        font-size: 14px;
        font-weight: 500;
        letter-spacing: 1.2px;
        opacity: 0.8;
    }
    
    /* Error Message */
    .error-message {
        background: linear-gradient(135deg, rgba(255, 50, 50, 0.15), rgba(255, 80, 80, 0.1));
        border: 1px solid rgba(255, 50, 50, 0.3);
        border-radius: 10px;
        padding: 15px 18px;
        margin-bottom: 25px;
        color: #dc3545;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 12px;
        animation: errorShake 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        box-shadow: 
            0 6px 15px rgba(220, 53, 69, 0.15),
            inset 0 1px 0 rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(10px);
    }
    
    @keyframes errorShake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-8px); }
        75% { transform: translateX(8px); }
    }
    
    /* Warning Message */
    .warning-message {
        background: linear-gradient(135deg, rgba(255, 193, 7, 0.15), rgba(255, 200, 50, 0.1));
        border: 1px solid rgba(255, 193, 7, 0.3);
        border-radius: 10px;
        padding: 15px 18px;
        margin-bottom: 25px;
        color: #856404;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 12px;
        animation: errorShake 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        box-shadow: 
            0 6px 15px rgba(255, 193, 7, 0.15),
            inset 0 1px 0 rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(10px);
    }
    
    /* Form Styles */
    .form-group {
        margin-bottom: 25px;
        position: relative;
    }
    
    .form-label {
        display: flex;
        align-items: center;
        gap: 10px;
        color: var(--sidebar-purple);
        margin-bottom: 10px;
        font-weight: 700;
        font-size: 14px;
        text-transform: uppercase;
        letter-spacing: 1.2px;
    }
    
    .input-container {
        position: relative;
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    
    .input-icon {
        position: absolute;
        left: 18px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--sidebar-purple);
        font-size: 18px;
        z-index: 2;
        transition: all 0.3s ease;
    }
    
    .form-input {
        width: 100%;
        padding: 15px 20px 15px 55px;
        background: var(--white);
        border: 2px solid rgba(79, 70, 229, 0.2);
        border-radius: 12px;
        color: var(--text-dark);
        font-size: 15px;
        font-weight: 500;
        transition: all 0.3s ease;
        box-shadow: 
            inset 0 2px 4px rgba(0, 0, 0, 0.05),
            0 4px 15px rgba(79, 70, 229, 0.1);
    }
    
    /* Input Error State */
    .input-error {
        border-color: var(--error-red) !important;
        background: var(--error-light) !important;
        box-shadow: 
            0 4px 15px rgba(220, 53, 69, 0.2),
            inset 0 2px 4px rgba(0, 0, 0, 0.05) !important;
    }
    
    /* Input Warning State */
    .input-warning {
        border-color: var(--warning-orange) !important;
        background: var(--warning-light) !important;
        box-shadow: 
            0 4px 15px rgba(255, 193, 7, 0.2),
            inset 0 2px 4px rgba(0, 0, 0, 0.05) !important;
    }
    
    .form-input:focus {
        outline: none;
        border-color: var(--sidebar-purple);
        box-shadow: 
            0 6px 20px rgba(79, 70, 229, 0.3),
            0 3px 12px rgba(124, 58, 237, 0.2),
            inset 0 2px 4px rgba(0, 0, 0, 0.05);
        background: var(--white);
        transform: translateY(-2px);
    }
    
    .form-input:focus + .input-icon {
        color: var(--sidebar-purple-light);
        transform: translateY(-50%) scale(1.1);
    }
    
    .form-input::placeholder {
        color: rgba(100, 116, 139, 0.6);
        font-weight: 400;
    }
    
    /* Error text under input */
    .error-text {
        color: var(--error-red);
        font-size: 12px;
        margin-top: 5px;
        display: flex;
        align-items: center;
        gap: 5px;
        animation: fadeIn 0.3s ease;
        padding-left: 5px;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-5px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    /* Password Toggle */
    .password-toggle {
        position: absolute;
        right: 18px;
        top: 50%;
        transform: translateY(-50%);
        background: transparent;
        border: none;
        color: var(--sidebar-purple);
        cursor: pointer;
        font-size: 18px;
        padding: 8px;
        transition: all 0.3s ease;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .password-toggle:hover {
        color: var(--sidebar-purple-light);
        background: rgba(79, 70, 229, 0.1);
        transform: translateY(-50%) scale(1.1);
    }
    
    /* Role Select */
    .form-select {
        width: 100%;
        padding: 15px 20px 15px 55px;
        background: var(--white);
        border: 2px solid rgba(79, 70, 229, 0.2);
        border-radius: 12px;
        color: var(--text-dark);
        font-size: 15px;
        font-weight: 500;
        cursor: pointer;
        appearance: none;
        transition: all 0.3s ease;
        box-shadow: 
            inset 0 2px 4px rgba(0, 0, 0, 0.05),
            0 4px 15px rgba(79, 70, 229, 0.1);
    }
    
    .form-select:focus {
        outline: none;
        border-color: var(--sidebar-purple);
        box-shadow: 
            0 6px 20px rgba(79, 70, 229, 0.3),
            0 3px 12px rgba(124, 58, 237, 0.2),
            inset 0 2px 4px rgba(0, 0, 0, 0.05);
        transform: translateY(-2px);
    }
    
    .form-select option {
        background: var(--white);
        color: var(--text-dark);
        padding: 10px;
        font-weight: 500;
    }
    
    .select-icon {
        position: absolute;
        right: 20px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--sidebar-purple);
        font-size: 16px;
        pointer-events: none;
        transition: all 0.3s ease;
    }
    
    .form-select:focus ~ .select-icon {
        color: var(--sidebar-purple-light);
        transform: translateY(-50%) rotate(180deg);
    }
    
    /* Login Button - Gradient sama dengan sidebar */
    .btn-login {
        width: 100%;
        padding: 16px;
        background: linear-gradient(135deg, 
            var(--sidebar-purple) 0%, 
            var(--sidebar-purple-light) 100%);
        border: none;
        border-radius: 12px;
        color: var(--text-light);
        font-size: 16px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 2px;
        cursor: pointer;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        position: relative;
        overflow: hidden;
        margin-top: 15px;
        box-shadow: 
            0 6px 20px rgba(79, 70, 229, 0.4),
            0 3px 12px rgba(124, 58, 237, 0.3),
            inset 0 1px 0 rgba(255, 255, 255, 0.3);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        z-index: 1;
    }
    
    .btn-login:hover {
        background: linear-gradient(135deg, 
            var(--sidebar-purple-dark) 0%, 
            var(--sidebar-purple) 100%);
        transform: translateY(-3px);
        box-shadow: 
            0 12px 30px rgba(79, 70, 229, 0.6),
            0 8px 20px rgba(124, 58, 237, 0.5),
            var(--glow-purple),
            inset 0 1px 0 rgba(255, 255, 255, 0.4);
        letter-spacing: 3px;
    }
    
    .btn-login:active {
        transform: translateY(-1px);
    }
    
    .btn-login::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, 
            transparent, 
            rgba(255, 255, 255, 0.4), 
            transparent);
        transition: left 0.8s;
        z-index: -1;
    }
    
    .btn-login:hover::before {
        left: 100%;
    }
    
    /* Welcome Message */
    .welcome-title {
        color: var(--text-light);
        font-size: 36px;
        font-weight: 300;
        margin-bottom: 20px;
        text-shadow: 
            0 2px 8px rgba(0, 0, 0, 0.2);
        position: relative;
        font-family: 'Segoe UI', sans-serif;
        letter-spacing: 1.5px;
    }
    
    .welcome-title::after {
        content: '';
        position: absolute;
        bottom: -10px;
        left: 50%;
        transform: translateX(-50%);
        width: 80px;
        height: 3px;
        background: linear-gradient(90deg, 
            transparent, 
            rgba(255, 255, 255, 0.7), 
            transparent);
        border-radius: 2px;
        box-shadow: 0 0 10px rgba(255, 255, 255, 0.5);
    }
    
    .welcome-subtitle {
        color: rgba(255, 255, 255, 0.9);
        font-size: 16px;
        line-height: 1.6;
        max-width: 90%;
        margin: 0 auto 25px;
        text-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
    }
    
    /* Sign Up Link */
    .signup-link {
        text-align: center;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid rgba(79, 70, 229, 0.2);
    }
    
    .signup-link a {
        color: var(--sidebar-purple);
        text-decoration: none;
        font-size: 14px;
        font-weight: 600;
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        border-radius: 8px;
        background: rgba(79, 70, 229, 0.1);
    }
    
    .signup-link a:hover {
        color: var(--sidebar-purple-light);
        background: rgba(79, 70, 229, 0.2);
        padding: 8px 20px;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
    }
    
    /* Copyright */
    .copyright {
        position: absolute;
        bottom: 20px;
        left: 0;
        right: 0;
        text-align: center;
        color: rgba(255, 255, 255, 0.7);
        font-size: 12px;
        z-index: 3;
    }
    
    /* Loading Animation */
    .btn-loading {
        position: relative;
        color: transparent !important;
    }
    
    .btn-loading::after {
        content: '';
        position: absolute;
        width: 22px;
        height: 22px;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        border: 2px solid rgba(255, 255, 255, 0.3);
        border-top-color: var(--text-light);
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
    }
    
    /* Success Animation */
    .success-animation {
        animation: successPulse 1.5s ease-in-out;
    }
    
    @keyframes successPulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.02); box-shadow: 0 0 30px rgba(40, 167, 69, 0.4); }
        100% { transform: scale(1); }
    }
    
    /* Responsive Design */
    @media (max-width: 1200px) {
        .login-container {
            max-width: 800px;
        }
    }
    
    @media (max-width: 992px) {
        .login-container {
            max-width: 700px;
        }
        
        .login-form, .welcome-panel {
            padding: 35px 30px;
        }
        
        .brand-name {
            font-size: 24px;
        }
        
        .welcome-title {
            font-size: 32px;
        }
    }
    
    @media (max-width: 768px) {
        .login-container {
            flex-direction: column;
            max-width: 450px;
            min-height: auto;
        }
        
        .welcome-panel {
            border-right: none;
            border-top: 1px solid rgba(79, 70, 229, 0.2);
            padding: 35px 30px;
        }
        
        .login-form, .welcome-panel {
            padding: 30px 25px;
        }
        
        .welcome-title {
            font-size: 28px;
        }
        
        .brand-name {
            font-size: 22px;
        }
        
        .logo-image {
            width: 75px;
            height: 75px;
        }
        
        .btn-login {
            padding: 14px;
            font-size: 15px;
        }
    }
    
    @media (max-width: 576px) {
        body {
            padding: 15px;
        }
        
        .login-container {
            border-radius: 16px;
            max-width: 100%;
        }
        
        .login-form, .welcome-panel {
            padding: 25px 20px;
        }
        
        .brand-name {
            font-size: 20px;
            letter-spacing: 2px;
        }
        
        .logo-image {
            width: 70px;
            height: 70px;
        }
        
        .logo-text {
            font-size: 2rem;
        }
        
        .welcome-title {
            font-size: 26px;
        }
        
        .form-input, .form-select {
            padding: 14px 18px 14px 50px;
            font-size: 14px;
        }
        
        .btn-login {
            padding: 14px;
            font-size: 14px;
        }
    }
    
    @media (max-width: 480px) {
        .login-form, .welcome-panel {
            padding: 20px 15px;
        }
        
        .brand-name {
            font-size: 18px;
        }
        
        .logo-image {
            width: 60px;
            height: 60px;
            padding: 12px;
        }
        
        .form-input, .form-select {
            padding: 12px 15px 12px 45px;
        }
        
        .btn-login {
            padding: 12px;
            font-size: 13px;
        }
    }
    
    /* Keyframes untuk animasi spin */
    @keyframes spin {
        to { transform: translate(-50%, -50%) rotate(360deg); }
    }
    
    /* Custom scrollbar untuk select */
    .form-select::-webkit-scrollbar {
        width: 6px;
    }
    
    .form-select::-webkit-scrollbar-track {
        background: var(--light-purple);
        border-radius: 3px;
    }
    
    .form-select::-webkit-scrollbar-thumb {
        background: linear-gradient(135deg, var(--sidebar-purple), var(--sidebar-purple-light));
        border-radius: 3px;
    }
    
    .form-select::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(135deg, var(--sidebar-purple-light), var(--sidebar-purple));
    }
    
    /* Animation untuk logo */
    @keyframes logoFloat {
        0%, 100% {
            transform: translateY(0) rotate(0deg);
        }
        50% {
            transform: translateY(-8px) rotate(5deg);
        }
    }
    
    .logo-image {
        animation: logoFloat 6s ease-in-out infinite;
    }
    
    /* Fade in animation */
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .login-container {
        animation: fadeIn 0.8s ease-out;
    }
</style>

</head>
<body>
    <!-- Background Pattern -->
    <div class="pattern-background"></div>
    
    <!-- Decorative Elements -->
    <div class="decoration" id="decoration"></div>
    
    <!-- Main Login Container -->
    <div class="login-container">
        <!-- Left Panel - Login Form -->
        <div class="login-form">
            <div class="logo-container">
                <div class="logo-image" id="logoImage">
                    <img src="<?php echo $logo_path; ?>" 
                         alt="<?php echo htmlspecialchars($logo_name); ?> Logo"
                         onerror="this.style.display='none'; document.getElementById('logoFallback').style.display='flex';">
                    <div id="logoFallback" class="logo-fallback" style="display: none;">
                        <span class="logo-text">P</span>
                    </div>
                </div>
                <div class="logo-title">
                    <h1 class="brand-name">PARAPATAN TAILOR</h1>
                    <p class="brand-subtitle">Sistem Informasi Manajemen</p>
                </div>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="loginForm">
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-user"></i>
                        <span>Username</span>
                    </label>
                    <div class="input-container">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" 
                               class="form-input <?php echo isset($validation_errors['username']) ? 'input-error' : ''; ?>" 
                               name="username" 
                               placeholder="Masukkan username" 
                               required 
                               autofocus
                               value="<?php echo htmlspecialchars($previous_input['username']); ?>">
                    </div>
                    <?php if (isset($validation_errors['username'])): ?>
                        <div class="error-text">
                            <i class="fas fa-exclamation-circle"></i>
                            <span><?php echo htmlspecialchars($validation_errors['username']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-lock"></i>
                        <span>Password</span>
                    </label>
                    <div class="input-container">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" 
                               class="form-input <?php echo isset($validation_errors['password']) ? 'input-error' : ''; ?>" 
                               id="password" 
                               name="password" 
                               placeholder="Masukkan password" 
                               required>
                        <button type="button" class="password-toggle" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <?php if (isset($validation_errors['password'])): ?>
                        <div class="error-text">
                            <i class="fas fa-exclamation-circle"></i>
                            <span><?php echo htmlspecialchars($validation_errors['password']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-user-tag"></i>
                        <span>Role</span>
                    </label>
                    <div class="input-container">
                        <i class="fas fa-user-tag input-icon"></i>
                        <select class="form-select <?php echo isset($validation_errors['role']) ? 'input-error' : ''; ?>" name="role" required>
                            <option value="">Pilih Role</option>
                            <option value="admin" <?php echo ($previous_input['role'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                            <option value="pemilik" <?php echo ($previous_input['role'] == 'pemilik') ? 'selected' : ''; ?>>Pemilik</option>
                            <option value="pegawai" <?php echo ($previous_input['role'] == 'pegawai') ? 'selected' : ''; ?>>Pegawai</option>
                        </select>
                        <i class="fas fa-chevron-down select-icon"></i>
                    </div>
                    <?php if (isset($validation_errors['role'])): ?>
                        <div class="error-text">
                            <i class="fas fa-exclamation-circle"></i>
                            <span><?php echo htmlspecialchars($validation_errors['role']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <button type="submit" class="btn-login" id="loginButton">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Login</span>
                </button>
                
                <div class="signup-link">
                    <a href="#" onclick="showSupport()">
                        <i class="fas fa-question-circle"></i>
                        <span>Butuh Bantuan?</span>
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Right Panel - Welcome Message -->
        <div class="welcome-panel">
            <div class="welcome-content">
                <h2 class="welcome-title">Welcome Back</h2>
                <p class="welcome-subtitle">
                    Selamat datang di sistem Parapatan Tailor.
                    Silakan masuk untuk membuka dashboard dan mengelola pesanan, 
                    pelanggan, dan transaksi jahit dengan mudah dan cepat.
                </p>
            </div>
            
            <div class="copyright">
                <p>&copy; <?php echo date('Y'); ?> Parapatan Tailor. All rights reserved.</p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Create decorative circles
            const decoration = document.getElementById('decoration');
            for (let i = 0; i < 8; i++) {
                const circle = document.createElement('div');
                circle.className = 'deco-circle';
                const size = Math.random() * 200 + 50;
                circle.style.width = size + 'px';
                circle.style.height = size + 'px';
                circle.style.left = Math.random() * 100 + '%';
                circle.style.top = Math.random() * 100 + '%';
                circle.style.animationDelay = Math.random() * 20 + 's';
                circle.style.animationDuration = (Math.random() * 20 + 20) + 's';
                decoration.appendChild(circle);
            }
            
            // Password toggle
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            let passwordVisible = false;
            
            togglePassword.addEventListener('click', function() {
                passwordVisible = !passwordVisible;
                passwordInput.type = passwordVisible ? 'text' : 'password';
                this.innerHTML = passwordVisible ? 
                    '<i class="fas fa-eye-slash"></i>' : 
                    '<i class="fas fa-eye"></i>';
                
                // Add click effect
                this.style.transform = 'translateY(-50%) scale(1.2)';
                this.style.color = passwordVisible ? '#a259ff' : '#8a4bff';
                setTimeout(() => {
                    this.style.transform = 'translateY(-50%) scale(1)';
                }, 200);
            });
            
            // Form submission
            const loginForm = document.getElementById('loginForm');
            const loginButton = document.getElementById('loginButton');
            
            loginForm.addEventListener('submit', function(e) {
                // Reset error states
                resetErrors();
                
                // Basic validation
                const username = loginForm.querySelector('[name="username"]').value.trim();
                const password = loginForm.querySelector('[name="password"]').value.trim();
                const role = loginForm.querySelector('[name="role"]').value;
                
                let hasError = false;
                let errorMessages = [];
                
                // Validate username
                if (!username) {
                    showInputError('username', 'Username belum diisi!');
                    hasError = true;
                    errorMessages.push('username_empty');
                }
                
                // Validate password
                if (!password) {
                    showInputError('password', 'Password belum diisi!');
                    hasError = true;
                    errorMessages.push('password_empty');
                }
                
                // Validate role
                if (!role) {
                    showInputError('role', 'Role belum dipilih!');
                    hasError = true;
                    errorMessages.push('role_empty');
                }
                
                if (hasError) {
                    e.preventDefault();
                    
                    // Create combined error message
                    let combinedMessage = '';
                    if (errorMessages.includes('username_empty') && 
                        errorMessages.includes('password_empty') && 
                        errorMessages.includes('role_empty')) {
                        combinedMessage = 'Username dan password belum diisi, dan role belum dipilih!';
                    } else if (errorMessages.includes('username_empty') && 
                               errorMessages.includes('password_empty')) {
                        combinedMessage = 'Username dan password belum diisi!';
                    } else if (errorMessages.includes('username_empty') && 
                               errorMessages.includes('role_empty')) {
                        combinedMessage = 'Username belum diisi dan role belum dipilih!';
                    } else if (errorMessages.includes('password_empty') && 
                               errorMessages.includes('role_empty')) {
                        combinedMessage = 'Password belum diisi dan role belum dipilih!';
                    } else if (errorMessages.includes('username_empty')) {
                        combinedMessage = 'Username belum diisi!';
                    } else if (errorMessages.includes('password_empty')) {
                        combinedMessage = 'Password belum diisi!';
                    } else if (errorMessages.includes('role_empty')) {
                        combinedMessage = 'Role belum dipilih!';
                    }
                    
                    // Show error message at the top
                    if (combinedMessage) {
                        showErrorMessage(combinedMessage);
                    }
                    
                    // Shake form for attention
                    shakeForm();
                    return;
                }
                
                // Show loading state
                loginButton.classList.add('btn-loading');
                loginButton.disabled = true;
                
                // Add glow effect on submit
                const loginContainer = document.querySelector('.login-container');
                loginContainer.style.animation = 'none';
                setTimeout(() => {
                    loginContainer.style.boxShadow = 
                        '0 30px 60px rgba(138, 75, 255, 0.4), ' +
                        '0 20px 40px rgba(162, 89, 255, 0.3), ' +
                        '0 0 50px rgba(138, 75, 255, 0.6)';
                    setTimeout(() => {
                        loginContainer.style.boxShadow = '';
                    }, 1000);
                }, 10);
            });
            
            // Input focus effects
            const inputs = document.querySelectorAll('.form-input, .form-select');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'translateY(-3px)';
                    this.parentElement.style.boxShadow = '0 10px 30px rgba(138, 75, 255, 0.25)';
                    // Remove error state on focus
                    if (this.classList.contains('input-error')) {
                        this.classList.remove('input-error');
                        removeErrorText(this.name);
                    }
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'translateY(0)';
                    this.parentElement.style.boxShadow = 'none';
                });
                
                // Add keyup effect
                input.addEventListener('keyup', function() {
                    if (this.value.trim() !== '') {
                        this.style.borderColor = '#8a4bff';
                        // Remove error if user starts typing
                        if (this.classList.contains('input-error')) {
                            this.classList.remove('input-error');
                            removeErrorText(this.name);
                        }
                    } else {
                        this.style.borderColor = 'rgba(138, 75, 255, 0.2)';
                    }
                });
            });
            
            // Add hover effect to form inputs
            const inputContainers = document.querySelectorAll('.input-container');
            inputContainers.forEach(container => {
                container.addEventListener('mouseenter', function() {
                    if (!this.querySelector('.form-input:focus') && 
                        !this.querySelector('.form-select:focus')) {
                        this.style.transform = 'translateY(-2px)';
                        this.style.boxShadow = '0 8px 20px rgba(138, 75, 255, 0.2)';
                    }
                });
                
                container.addEventListener('mouseleave', function() {
                    if (!this.querySelector('.form-input:focus') && 
                        !this.querySelector('.form-select:focus')) {
                        this.style.transform = 'translateY(0)';
                        this.style.boxShadow = 'none';
                    }
                });
            });
            
            // Prevent form resubmission
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }
            
            // Auto-focus username after page load
            setTimeout(() => {
                const usernameInput = document.querySelector('[name="username"]');
                if (usernameInput) {
                    usernameInput.focus();
                    
                    // Add glow effect on focus
                    setTimeout(() => {
                        usernameInput.parentElement.style.transform = 'translateY(-3px)';
                        usernameInput.parentElement.style.boxShadow = '0 10px 30px rgba(138, 75, 255, 0.25)';
                    }, 100);
                }
            }, 400);
            
            // Handle logo image error
            const logoImage = document.getElementById('logoImage');
            const logoImg = logoImage.querySelector('img');
            const logoFallback = document.getElementById('logoFallback');
            
            if (logoImg) {
                logoImg.addEventListener('error', function() {
                    this.style.display = 'none';
                    if (logoFallback) logoFallback.style.display = 'flex';
                });
                
                // Check if image loaded successfully
                if (logoImg.complete && logoImg.naturalHeight === 0) {
                    logoImg.style.display = 'none';
                    if (logoFallback) logoFallback.style.display = 'flex';
                }
            }
            
            // Function to show input error
            function showInputError(fieldName, message, isWarning = false) {
                const input = loginForm.querySelector(`[name="${fieldName}"]`);
                if (!input) return;
                
                // Add error class
                input.classList.add(isWarning ? 'input-warning' : 'input-error');
                
                // Remove existing error text
                removeErrorText(fieldName);
                
                // Create error text element
                const errorText = document.createElement('div');
                errorText.className = 'error-text';
                errorText.id = `error-${fieldName}`;
                errorText.innerHTML = `
                    <i class="fas ${isWarning ? 'fa-exclamation-triangle' : 'fa-exclamation-circle'}"></i>
                    <span>${message}</span>
                `;
                
                // Insert after input container
                const inputContainer = input.closest('.form-group');
                inputContainer.appendChild(errorText);
                
                // Shake the specific input
                shakeElement(input);
            }
            
            // Function to remove error text
            function removeErrorText(fieldName) {
                const existingError = document.getElementById(`error-${fieldName}`);
                if (existingError) {
                    existingError.remove();
                }
            }
            
            // Function to reset all errors
            function resetErrors() {
                // Remove error classes
                inputs.forEach(input => {
                    input.classList.remove('input-error');
                    input.classList.remove('input-warning');
                });
                
                // Remove all error texts
                const errorTexts = document.querySelectorAll('.error-text');
                errorTexts.forEach(error => error.remove());
                
                // Remove main error message
                const mainError = document.querySelector('.error-message');
                if (mainError) {
                    mainError.remove();
                }
            }
            
            // Function to show error message at top
            function showErrorMessage(message) {
                // Remove existing error messages
                const existingError = document.querySelector('.error-message');
                if (existingError) existingError.remove();
                
                // Create new error message
                const errorDiv = document.createElement('div');
                errorDiv.className = 'error-message';
                errorDiv.innerHTML = `
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>${message}</span>
                `;
                
                // Insert after logo container
                const logoContainer = document.querySelector('.logo-container');
                if (logoContainer) {
                    logoContainer.parentNode.insertBefore(errorDiv, logoContainer.nextSibling);
                }
                
                // Auto remove after 5 seconds
                setTimeout(() => {
                    if (errorDiv.parentNode) {
                        errorDiv.remove();
                    }
                }, 5000);
            }
            
            // Function to shake form
            function shakeForm() {
                const loginContainer = document.querySelector('.login-container');
                loginContainer.style.animation = 'none';
                setTimeout(() => {
                    loginContainer.style.animation = 'errorShake 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
                    setTimeout(() => {
                        loginContainer.style.animation = '';
                    }, 500);
                }, 10);
            }
            
            // Function to shake specific element
            function shakeElement(element) {
                element.style.animation = 'none';
                setTimeout(() => {
                    element.style.animation = 'errorShake 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
                    setTimeout(() => {
                        element.style.animation = '';
                    }, 300);
                }, 10);
            }
            
            // Function to show support info
            window.showSupport = function() {
                const supportMessage = `
                    <div style="
                        background: linear-gradient(135deg, #8a4bff, #a259ff);
                        color: white;
                        padding: 25px;
                        border-radius: 15px;
                        max-width: 400px;
                        margin: 0 auto;
                        box-shadow: 0 15px 40px rgba(138, 75, 255, 0.4);
                        text-align: center;
                    ">
                        <h3 style="margin-bottom: 15px; font-size: 20px;">
                            <i class="fas fa-headset"></i> Bantuan Login
                        </h3>
                        <div style="text-align: left; line-height: 1.8; margin-bottom: 20px;">
                            <p><strong>Jika mengalami masalah login:</strong></p>
                            <p>1. Pastikan username dan password benar</p>
                            <p>2. Pilih role yang sesuai dengan akun Anda</p>
                            <p>3. Pastikan caps lock tidak aktif</p>
                            <p>4. Coba hapus cache browser</p>
                            <br>
                            <p><strong>Admin Sistem:</strong> (021) 1234-5678</p>
                            <p><strong>Email:</strong> support@parapatantailor.com</p>
                        </div>
                        <button onclick="this.parentElement.parentElement.close()" 
                                style="
                                    background: white;
                                    color: #8a4bff;
                                    border: none;
                                    padding: 10px 25px;
                                    border-radius: 8px;
                                    font-weight: bold;
                                    cursor: pointer;
                                    transition: all 0.3s ease;
                                "
                                onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 5px 15px rgba(0,0,0,0.1)'"
                                onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                            Tutup
                        </button>
                    </div>
                `;
                
                const modal = document.createElement('div');
                modal.style.cssText = `
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.7);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 9999;
                    backdrop-filter: blur(5px);
                    animation: fadeIn 0.3s ease;
                `;
                modal.innerHTML = supportMessage;
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        document.body.removeChild(this);
                    }
                });
                document.body.appendChild(modal);
                
                return false;
            }
            
            // Add keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Ctrl+Enter to submit form
                if (e.ctrlKey && e.key === 'Enter') {
                    e.preventDefault();
                    loginForm.submit();
                }
                
                // Esc to clear form or close modal
                if (e.key === 'Escape') {
                    const modal = document.querySelector('div[style*="position: fixed"]');
                    if (modal) {
                        document.body.removeChild(modal);
                    } else {
                        loginForm.reset();
                        resetErrors();
                        const inputs = loginForm.querySelectorAll('input, select');
                        inputs.forEach(input => {
                            input.style.transform = 'scale(0.98)';
                            setTimeout(() => {
                                input.style.transform = 'scale(1)';
                            }, 200);
                        });
                    }
                }
            });
            
            // Add ripple effect to login button
            loginButton.addEventListener('click', function(e) {
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                ripple.style.cssText = `
                    position: absolute;
                    border-radius: 50%;
                    background: radial-gradient(circle, rgba(255,255,255,0.8), transparent);
                    transform: scale(0);
                    animation: ripple 0.6s linear;
                    width: ${size}px;
                    height: ${size}px;
                    top: ${y}px;
                    left: ${x}px;
                    pointer-events: none;
                    z-index: 0;
                `;
                
                this.appendChild(ripple);
                
                setTimeout(() => {
                    if (ripple.parentNode) {
                        ripple.remove();
                    }
                }, 600);
            });
            
            // Add ripple animation style
            const rippleStyle = document.createElement('style');
            rippleStyle.textContent = `
                @keyframes fadeIn {
                    from { opacity: 0; }
                    to { opacity: 1; }
                }
                
                @keyframes ripple {
                    to {
                        transform: scale(4);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(rippleStyle);
            
            // Add form validation styling
            const formInputs = loginForm.querySelectorAll('input, select');
            formInputs.forEach(input => {
                input.addEventListener('invalid', function(e) {
                    e.preventDefault();
                    this.style.borderColor = '#dc3545';
                    this.style.boxShadow = '0 0 0 0.2rem rgba(220, 53, 69, 0.25)';
                });
            });
        });
    </script>
</body>
</html>