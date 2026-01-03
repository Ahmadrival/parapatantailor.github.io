<?php
// profile.php
require_once 'config/database.php';
check_login();

// Ambil data user yang sedang login
$user_id = $_SESSION['user_id'];
try {
    $sql = "SELECT * FROM users WHERE id_user = ?";
    $user = getSingle($sql, [$user_id]);
    
    if (!$user) {
        $_SESSION['error'] = "❌ User tidak ditemukan";
        header("Location: logout.php");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "❌ Gagal memuat data profil";
    header("Location: dashboard.php");
    exit();
}

// Update last_login saat user mengakses profile
try {
    $update_sql = "UPDATE users SET last_login = NOW() WHERE id_user = ? AND last_login IS NULL";
    executeQuery($update_sql, [$user_id]);
    
    // Refresh data user untuk mendapatkan last_login yang terbaru
    $user = getSingle($sql, [$user_id]);
} catch (PDOException $e) {
    error_log("Error updating last login: " . $e->getMessage());
}

// Update profil
if (isset($_POST['update_profile'])) {
    $nama_lengkap = clean_input($_POST['nama_lengkap']);
    $email = clean_input($_POST['email']);
    $telepon = clean_input($_POST['telepon']);
    $alamat = clean_input($_POST['alamat']);
    
    try {
        $sql = "UPDATE users SET nama_lengkap = ?, email = ?, telepon = ?, alamat = ?, updated_at = NOW() WHERE id_user = ?";
        executeQuery($sql, [$nama_lengkap, $email, $telepon, $alamat, $user_id]);
        
        // Update session nama
        $_SESSION['nama_lengkap'] = $nama_lengkap;
        
        $_SESSION['success'] = "✅ Profil berhasil diupdate";
        log_activity("Mengupdate profil");
        
        // Refresh data user
        $user = getSingle("SELECT * FROM users WHERE id_user = ?", [$user_id]);
    } catch (PDOException $e) {
        $_SESSION['error'] = "❌ Gagal mengupdate profil: " . $e->getMessage();
    }
    header("Location: profile.php");
    exit();
}

// PERBAIKAN: Update password untuk sistem PLAIN TEXT
if (isset($_POST['update_password'])) {
    $current_password = clean_input($_POST['current_password']);
    $new_password = clean_input($_POST['new_password']);
    $confirm_password = clean_input($_POST['confirm_password']);
    
    try {
        // PERBAIKAN: Verifikasi password saat ini dengan PLAIN TEXT
        // Karena di sistem users.php password disimpan sebagai plain text
        if ($current_password !== $user['password']) {
            $_SESSION['error'] = "❌ Password saat ini salah";
        } elseif ($new_password !== $confirm_password) {
            $_SESSION['error'] = "❌ Password baru tidak cocok";
        } elseif (strlen($new_password) < 6) {
            $_SESSION['error'] = "❌ Password baru minimal 6 karakter";
        } else {
            // PERBAIKAN: Update password dengan PLAIN TEXT (tidak di-hash)
            // Untuk konsistensi dengan sistem users.php
            $sql = "UPDATE users SET password = ?, updated_at = NOW() WHERE id_user = ?";
            executeQuery($sql, [$new_password, $user_id]);
            
            $_SESSION['success'] = "✅ Password berhasil diubah";
            log_activity("Mengubah password");
            
            // Refresh data user untuk mendapatkan password yang baru
            $user = getSingle("SELECT * FROM users WHERE id_user = ?", [$user_id]);
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "❌ Gagal mengubah password: " . $e->getMessage();
    }
    header("Location: profile.php#password");
    exit();
}

// Update foto profil
if (isset($_POST['update_photo'])) {
    if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === UPLOAD_ERR_OK) {
        $foto = $_FILES['foto_profil'];
        
        // Validasi file
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB
        
        if (!in_array($foto['type'], $allowed_types)) {
            $_SESSION['error'] = "❌ Hanya file JPEG, PNG, dan GIF yang diizinkan";
        } elseif ($foto['size'] > $max_size) {
            $_SESSION['error'] = "❌ Ukuran file maksimal 2MB";
        } else {
            // Generate nama file unik
            $ext = pathinfo($foto['name'], PATHINFO_EXTENSION);
            $filename = 'profile_' . $user_id . '_' . time() . '.' . $ext;
            $upload_path = '../uploads/profiles/' . $filename;
            
            // Create directory jika belum ada
            if (!is_dir('../uploads/profiles')) {
                mkdir('../uploads/profiles', 0777, true);
            }
            
            if (move_uploaded_file($foto['tmp_name'], $upload_path)) {
                // Hapus foto lama jika ada
                if (!empty($user['foto_profil']) && file_exists('../uploads/profiles/' . $user['foto_profil'])) {
                    unlink('../uploads/profiles/' . $user['foto_profil']);
                }
                
                // Update database
                $sql = "UPDATE users SET foto_profil = ?, updated_at = NOW() WHERE id_user = ?";
                executeQuery($sql, [$filename, $user_id]);
                
                $_SESSION['success'] = "✅ Foto profil berhasil diupdate";
                log_activity("Mengupdate foto profil");
                
                // Refresh data user
                $user = getSingle("SELECT * FROM users WHERE id_user = ?", [$user_id]);
            } else {
                $_SESSION['error'] = "❌ Gagal mengupload foto";
            }
        }
    } else {
        $_SESSION['error'] = "❌ Silakan pilih file foto";
    }
    header("Location: profile.php#photo");
    exit();
}

$page_title = "Profil Pengguna";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Pengguna - SIM Parapatan Tailor</title>
    
    <!-- Bootstrap 5.3.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="includes/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="shortcut icon" href="../parapatan_tailor/assets/images/logoterakhir.png" type="image/x-icon" />

    <style>
        /* CSS Custom yang KONSISTEN dengan editpesanan.php */
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
        
        /* Info Section - SEPERTI EDITPESANAN */
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
        
        /* Profile Photo - DIKECILKAN */
        .profile-photo-container {
            text-align: center;
            margin-bottom: 1rem;
        }
        
        .profile-photo {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #4f46e5;
            margin-bottom: 0.5rem;
        }
        
        .profile-name {
            font-weight: 600;
            color: #1f2937;
            font-size: 0.9rem;
            margin-bottom: 0.2rem;
        }
        
        .profile-role {
            font-size: 0.7rem;
            color: #6b7280;
            background: #e5e7eb;
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            display: inline-block;
        }
        
        /* Header Actions - SEPERTI EDITPESANAN */
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
        
        .status-badge.active {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }
        
        .status-badge.inactive {
            background: #fef3c7;
            color: #d97706;
            border: 1px solid #fcd34d;
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
        
        /* Tabs Navigation - DIKECILKAN */
        .nav-tabs {
            border-bottom: 2px solid #e0e7ff;
            margin-bottom: 1rem;
        }

        .nav-tabs .nav-link {
            border: none;
            color: #6b7280;
            font-weight: 500;
            padding: 0.6rem 0.8rem;
            border-radius: 6px 6px 0 0;
            margin-right: 0.4rem;
            transition: all 0.2s;
            font-size: 0.75rem;
        }

        .nav-tabs .nav-link:hover {
            color: #374151;
            background: #f8faff;
        }

        .nav-tabs .nav-link.active {
            color: #4f46e5;
            background: white;
            border-bottom: 2px solid #4f46e5;
            font-weight: 600;
        }
        
        /* Password input group */
        .password-input-group {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6b7280;
            cursor: pointer;
            padding: 0.2rem;
            border-radius: 4px;
            transition: color 0.2s;
            font-size: 0.7rem;
        }

        .password-toggle:hover {
            color: #374151;
            background: #f3f4f6;
        }
        
        /* Photo Upload - DIKECILKAN */
        .photo-upload {
            text-align: center;
            padding: 1rem;
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            background: #f9fafb;
            cursor: pointer;
            transition: all 0.3s;
        }

        .photo-upload:hover {
            border-color: #4f46e5;
            background: #f0f4ff;
        }

        .photo-upload i {
            font-size: 1.5rem;
            color: #9ca3af;
            margin-bottom: 0.6rem;
        }

        .photo-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 0.6rem;
            display: none;
            border: 2px solid #e0e7ff;
        }
        
        /* Password Strength */
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
        
        /* Last login */
        .last-login {
            font-size: 0.7rem;
            color: #6b7280;
            font-style: italic;
            margin-top: 0.5rem;
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
            
            .form-actions {
                flex-direction: column;
            }
            
            .form-actions .btn {
                width: 100%;
                justify-content: center;
            }
            
            .nav-tabs .nav-link {
                padding: 0.5rem 0.6rem;
                font-size: 0.7rem;
            }
        }
        
        @media (max-width: 480px) {
            .container {
                padding: 10px;
            }
            
            .card {
                padding: 15px;
            }
            
            .profile-photo {
                width: 80px;
                height: 80px;
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
        
        /* Tab Content */
        .tab-content {
            background: white;
            border-radius: 0 0 8px 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        
        /* Stats Grid - SEPERTI EDITPESANAN */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border-left: 4px solid #4f46e5;
            text-align: center;
        }

        .stat-card.login {
            border-left-color: #4f46e5;
        }

        .stat-card.role {
            border-left-color: #dc2626;
        }

        .stat-card.status {
            border-left-color: #059669;
        }

        .stat-card.member {
            border-left-color: #d97706;
        }

        .stat-number {
            font-size: 1rem;
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

        .stat-icon.login {
            background: linear-gradient(135deg, #ede9fe, #ddd6fe);
            color: #4f46e5;
        }

        .stat-icon.role {
            background: linear-gradient(135deg, #fee2e2, #fca5a5);
            color: #dc2626;
        }

        .stat-icon.status {
            background: linear-gradient(135deg, #dcfce7, #bbf7d0);
            color: #059669;
        }

        .stat-icon.member {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: #d97706;
        }
    </style>
</head>

<body>
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/header.php'; ?>

    <div class="main-content">
        <div class="content-body">
            <div class="container">
                <h2 class="my-3">Profil Pengguna</h2>

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

                <!-- Header Actions -->
                <div class="header-actions">
                    <div>
                        <h3>
                            <i class="fas fa-user"></i> Profil Saya
                        </h3>
                        <div class="mt-2">
                            <span class="status-badge active">
                                <i class="fas fa-circle" style="font-size: 0.6rem;"></i>
                                Aktif
                            </span>
                            <span class="ms-2 profile-role">
                                <?= ucfirst($user['role']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="action-buttons">
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Kembali
                        </a>
                    </div>
                </div>

                <!-- Info Box Edit -->
                <div class="info-box-edit">
                    <p><i class="fas fa-info-circle"></i> Perbarui informasi profil Anda. Untuk mengubah password, isi kolom password baru.</p>
                </div>

                <div class="row">
                    <!-- Kolom Kiri - Informasi Profil -->
                    <div class="col-lg-8">
                        <!-- Tabs Navigation -->
                        <ul class="nav nav-tabs" id="profileTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="edit-tab" data-bs-toggle="tab" data-bs-target="#edit" type="button" role="tab">
                                    <i class="fas fa-edit"></i> Edit Profil
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="password-tab" data-bs-toggle="tab" data-bs-target="#password" type="button" role="tab">
                                    <i class="fas fa-key"></i> Ubah Password
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="photo-tab" data-bs-toggle="tab" data-bs-target="#photo" type="button" role="tab">
                                    <i class="fas fa-camera"></i> Foto Profil
                                </button>
                            </li>
                        </ul>

                        <!-- Tab Content -->
                        <div class="tab-content" id="profileTabContent">
                            
                            <!-- Tab Edit Profil -->
                            <div class="tab-pane fade show active" id="edit" role="tabpanel">
                                <div class="edit-form-container">
                                    <h5><i class="fas fa-user-edit"></i> Informasi Profil</h5>
                                    
                                    <form method="POST">
                                        <div class="info-grid">
                                            <div class="info-card">
                                                <div class="info-header">
                                                    <i class="fas fa-user"></i> Akun
                                                </div>
                                                <div class="info-content">
                                                    <div class="info-row">
                                                        <span class="info-label">Username:</span>
                                                        <span class="info-value"><?= htmlspecialchars($user['username']); ?></span>
                                                    </div>
                                                    <div class="info-row">
                                                        <span class="info-label">Role:</span>
                                                        <span class="info-value"><?= ucfirst($user['role']); ?></span>
                                                    </div>
                                                    <div class="info-row">
                                                        <span class="info-label">Status:</span>
                                                        <span class="info-value"><?= ucfirst($user['status']); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="info-card">
                                                <div class="info-header">
                                                    <i class="fas fa-calendar"></i> Waktu
                                                </div>
                                                <div class="info-content">
                                                    <div class="info-row">
                                                        <span class="info-label">Bergabung:</span>
                                                        <span class="info-value"><?= date('d/m/Y', strtotime($user['created_at'])); ?></span>
                                                    </div>
                                                    <div class="info-row">
                                                        <span class="info-label">Diupdate:</span>
                                                        <span class="info-value"><?= !empty($user['updated_at']) ? date('d/m/Y', strtotime($user['updated_at'])) : '-'; ?></span>
                                                    </div>
                                                    <div class="info-row">
                                                        <span class="info-label">Login Terakhir:</span>
                                                        <span class="info-value">
                                                            <?php if (!empty($user['last_login'])): ?>
                                                                <?= date('d/m/Y H:i', strtotime($user['last_login'])); ?>
                                                            <?php else: ?>
                                                                Sekarang
                                                            <?php endif; ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-3">
                                            <div class="row g-2">
                                                <div class="col-md-12">
                                                    <label class="form-label">Nama Lengkap <span class="required">*</span></label>
                                                    <input type="text" name="nama_lengkap" class="form-control form-control-sm" 
                                                           value="<?= htmlspecialchars($user['nama_lengkap']); ?>" required>
                                                </div>
                                            </div>
                                            
                                            <div class="row g-2 mt-2">
                                                <div class="col-md-6">
                                                    <label class="form-label">Email</label>
                                                    <input type="email" name="email" class="form-control form-control-sm" 
                                                           value="<?= htmlspecialchars($user['email'] ?? ''); ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Telepon</label>
                                                    <input type="text" name="telepon" class="form-control form-control-sm" 
                                                           value="<?= htmlspecialchars($user['telepon'] ?? ''); ?>">
                                                </div>
                                            </div>
                                            
                                            <div class="mt-2">
                                                <label class="form-label">Alamat</label>
                                                <textarea name="alamat" class="form-control form-control-sm" rows="2"><?= htmlspecialchars($user['alamat'] ?? ''); ?></textarea>
                                            </div>
                                        </div>
                                        
                                        <div class="form-actions">
                                            <button type="submit" name="update_profile" class="btn btn-success">
                                                <i class="fas fa-save"></i> Simpan Perubahan
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <!-- Tab Ubah Password -->
                            <div class="tab-pane fade" id="password" role="tabpanel">
                                <div class="edit-form-container">
                                    <h5><i class="fas fa-lock"></i> Keamanan Akun</h5>
                                    
                                    <form method="POST">
                                        <div class="mb-3">
                                            <label class="form-label">Password Saat Ini <span class="required">*</span></label>
                                            <div class="password-input-group">
                                                <input type="password" name="current_password" class="form-control form-control-sm" required>
                                                <button type="button" class="password-toggle" onclick="togglePassword(this)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Password Baru <span class="required">*</span></label>
                                            <div class="password-input-group">
                                                <input type="password" name="new_password" id="newPassword" class="form-control form-control-sm" required>
                                                <button type="button" class="password-toggle" onclick="togglePassword(this)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                            <div class="password-strength" id="passwordStrength"></div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Konfirmasi Password Baru <span class="required">*</span></label>
                                            <div class="password-input-group">
                                                <input type="password" name="confirm_password" class="form-control form-control-sm" required>
                                                <button type="button" class="password-toggle" onclick="togglePassword(this)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <!-- PERBAIKAN: Update pesan info untuk sistem plain text -->
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle"></i> Password disimpan sebagai plain text. Pastikan password Anda aman dan tidak mudah ditebak.
                                        </div>
                                        
                                        <div class="form-actions">
                                            <button type="submit" name="update_password" class="btn btn-warning">
                                                <i class="fas fa-key"></i> Ubah Password
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <!-- Tab Foto Profil -->
                            <div class="tab-pane fade" id="photo" role="tabpanel">
                                <div class="edit-form-container">
                                    <h5><i class="fas fa-camera"></i> Foto Profil</h5>
                                    
                                    <form method="POST" enctype="multipart/form-data">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <!-- Foto Saat Ini -->
                                                <div class="text-center mb-4">
                                                    <h6 class="form-label mb-2">Foto Saat Ini</h6>
                                                    <?php if (!empty($user['foto_profil'])): ?>
                                                        <img src="../uploads/profiles/<?= htmlspecialchars($user['foto_profil']); ?>" 
                                                             alt="Foto Profil" class="profile-photo">
                                                    <?php else: ?>
                                                        <div class="profile-photo bg-light d-flex align-items-center justify-content-center mx-auto" style="background: linear-gradient(135deg, #e0e7ff, #c7d2fe) !important;">
                                                            <i class="fas fa-user fa-2x text-muted"></i>
                                                        </div>
                                                        <p class="text-muted mt-1">Belum ada foto profil</p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <!-- Upload Foto Baru -->
                                                <div class="photo-upload mb-3" onclick="document.getElementById('fotoInput').click()">
                                                    <i class="fas fa-cloud-upload-alt"></i>
                                                    <h6 class="mt-1 mb-1">Klik untuk Upload Foto Baru</h6>
                                                    <p class="text-muted">Format: JPG, PNG, GIF (Maks. 2MB)</p>
                                                    <img id="photoPreview" class="photo-preview" alt="Preview">
                                                </div>
                                                <input type="file" id="fotoInput" name="foto_profil" accept="image/*" style="display: none;" onchange="previewPhoto(this)">
                                            </div>
                                        </div>
                                        
                                        <div class="form-actions">
                                            <button type="submit" name="update_photo" class="btn btn-success">
                                                <i class="fas fa-upload"></i> Upload Foto
                                            </button>
                                            
                                            <?php if (!empty($user['foto_profil'])): ?>
                                                <button type="button" class="btn btn-outline-danger" onclick="confirmDeletePhoto()">
                                                    <i class="fas fa-trash"></i> Hapus Foto
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Kolom Kanan - Sidebar Info -->
                    <div class="col-lg-4">
                        <!-- Foto Profil -->
                        <div class="edit-form-container">
                            <h5><i class="fas fa-user-circle"></i> Foto Profil</h5>
                            
                            <div class="profile-photo-container">
                                <?php if (!empty($user['foto_profil'])): ?>
                                    <img src="../uploads/profiles/<?= htmlspecialchars($user['foto_profil']); ?>" 
                                         alt="Foto Profil" class="profile-photo">
                                <?php else: ?>
                                    <div class="profile-photo bg-light d-flex align-items-center justify-content-center mx-auto" style="background: linear-gradient(135deg, #e0e7ff, #c7d2fe) !important;">
                                        <i class="fas fa-user fa-2x text-muted"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="profile-name"><?= htmlspecialchars($user['nama_lengkap']); ?></div>
                                <div class="profile-role mb-2"><?= ucfirst($user['role']); ?></div>
                            </div>
                        </div>

                        <!-- Hak Akses -->
                        <div class="edit-form-container">
                            <h5><i class="fas fa-shield-alt"></i> Hak Akses</h5>
                            
                            <div class="info-card">
                                <div class="info-content">
                                    <?php if ($user['role'] == 'admin'): ?>
                                        <div class="info-row">
                                            <span class="info-label">Manajemen User</span>
                                            <span class="info-value"><i class="fas fa-check text-success"></i></span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Manajemen Karyawan</span>
                                            <span class="info-value"><i class="fas fa-check text-success"></i></span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Semua Laporan</span>
                                            <span class="info-value"><i class="fas fa-check text-success"></i></span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Konfigurasi Sistem</span>
                                            <span class="info-value"><i class="fas fa-check text-success"></i></span>
                                        </div>
                                    <?php elseif ($user['role'] == 'pemilik'): ?>
                                        <div class="info-row">
                                            <span class="info-label">Lihat Laporan</span>
                                            <span class="info-value"><i class="fas fa-check text-success"></i></span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Monitoring Bisnis</span>
                                            <span class="info-value"><i class="fas fa-check text-success"></i></span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Data Keuangan</span>
                                            <span class="info-value"><i class="fas fa-check text-success"></i></span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Manajemen User</span>
                                            <span class="info-value"><i class="fas fa-times text-danger"></i></span>
                                        </div>
                                    <?php else: ?>
                                        <div class="info-row">
                                            <span class="info-label">Input Pesanan</span>
                                            <span class="info-value"><i class="fas fa-check text-success"></i></span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Input Transaksi</span>
                                            <span class="info-value"><i class="fas fa-check text-success"></i></span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Akses Laporan</span>
                                            <span class="info-value"><i class="fas fa-times text-danger"></i></span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Manajemen User</span>
                                            <span class="info-value"><i class="fas fa-times text-danger"></i></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
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
        // Password toggle function
        function togglePassword(button) {
            const input = button.parentElement.querySelector('input');
            const icon = button.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }

        // Password strength indicator
        const passwordInput = document.getElementById('newPassword');
        const strengthBar = document.getElementById('passwordStrength');
        
        if (passwordInput && strengthBar) {
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;
                
                if (password.length >= 6) strength++;
                if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
                if (password.match(/\d/)) strength++;
                if (password.match(/[^a-zA-Z\d]/)) strength++;
                
                strengthBar.className = 'password-strength';
                if (strength <= 1) {
                    strengthBar.classList.add('password-weak');
                } else if (strength <= 2) {
                    strengthBar.classList.add('password-medium');
                } else {
                    strengthBar.classList.add('password-strong');
                }
            });
        }

        // Photo preview
        function previewPhoto(input) {
            const preview = document.getElementById('photoPreview');
            const uploadArea = input.previousElementSibling;
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    uploadArea.querySelector('i').style.display = 'none';
                    uploadArea.querySelector('h6').style.display = 'none';
                    uploadArea.querySelector('p').style.display = 'none';
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Confirm delete photo
        function confirmDeletePhoto() {
            if (confirm('Apakah Anda yakin ingin menghapus foto profil?')) {
                window.location.href = 'profile.php?delete_photo=1';
            }
        }

        // Form validation for password change
        const passwordForm = document.querySelector('form[action*="update_password"]');
        if (passwordForm) {
            passwordForm.addEventListener('submit', function(e) {
                const newPassword = this.querySelector('input[name="new_password"]').value;
                const confirmPassword = this.querySelector('input[name="confirm_password"]').value;
                
                if (newPassword !== confirmPassword) {
                    e.preventDefault();
                    alert('Password baru dan konfirmasi password tidak cocok!');
                    return;
                }
                
                if (newPassword.length < 6) {
                    e.preventDefault();
                    alert('Password baru minimal 6 karakter!');
                    return;
                }
            });
        }

        // Auto-activate tab from URL hash
        document.addEventListener('DOMContentLoaded', function() {
            const hash = window.location.hash;
            if (hash) {
                const tab = document.querySelector(`[data-bs-target="${hash}"]`);
                if (tab) {
                    new bootstrap.Tab(tab).show();
                }
            }
            
            // Auto-dismiss alerts after 5 seconds
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        });
    </script>

    <?php
    // Handle delete photo
    if (isset($_GET['delete_photo'])) {
        if (!empty($user['foto_profil']) && file_exists('../uploads/profiles/' . $user['foto_profil'])) {
            unlink('../uploads/profiles/' . $user['foto_profil']);
        }
        
        $sql = "UPDATE users SET foto_profil = NULL, updated_at = NOW() WHERE id_user = ?";
        executeQuery($sql, [$user_id]);
        
        $_SESSION['success'] = "✅ Foto profil berhasil dihapus";
        header("Location: profile.php#photo");
        exit();
    }
    ?>
</body>
</html>