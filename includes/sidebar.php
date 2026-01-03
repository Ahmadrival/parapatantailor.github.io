<?php
// includes/sidebar.php

// Cek session status
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_page = basename($_SERVER['PHP_SELF']);

// Deteksi base path berdasarkan lokasi file
$base_path = '';
if (strpos($_SERVER['PHP_SELF'], 'pelanggan/') !== false || 
    strpos($_SERVER['PHP_SELF'], 'pesanan/') !== false || 
    strpos($_SERVER['PHP_SELF'], 'transaksi/') !== false || 
    strpos($_SERVER['PHP_SELF'], 'karyawan/') !== false || 
    strpos($_SERVER['PHP_SELF'], 'laporan/') !== false || 
    strpos($_SERVER['PHP_SELF'], 'users/') !== false) {
    $base_path = '../';
}

// Deteksi path ke config/database.php berdasarkan lokasi file yang memanggil sidebar
$database_included = false;

// Coba multiple paths untuk include database
$possible_paths = [
    __DIR__ . '/../config/database.php',
    __DIR__ . '/../../config/database.php',
    'config/database.php',
    '../config/database.php',
    '../../config/database.php'
];

foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $database_included = true;
        break;
    }
}

// Set default logo
$logo_path = 'assets/images/logoTailor.png';
$logo_name = 'Parapatan Tailor';

// Jika database berhasil diinclude, ambil logo dari database
if ($database_included && isset($pdo) && $pdo) {
    try {
        // Cek apakah tabel logo_settings ada
        $check_table = $pdo->query("SHOW TABLES LIKE 'logo_settings'");
        if ($check_table->rowCount() > 0) {
            $stmt = $pdo->query("SELECT logo_path, logo_name FROM logo_settings ORDER BY id DESC LIMIT 1");
            if ($row = $stmt->fetch()) {
                $logo_path = $row['logo_path'] ?? 'assets/images/logoTailor.png';
                $logo_name = $row['logo_name'] ?? 'Parapatan Tailor';
            }
        }
    } catch (PDOException $e) {
        // Gunakan default jika error
        error_log("Error fetching logo: " . $e->getMessage());
    }
}

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: " . $base_path . "login.php");
    exit();
}

// Cek role untuk keamanan
$allowed_roles = ['admin', 'pemilik', 'pegawai'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    // Jika role tidak valid, logout
    header("Location: " . $base_path . "logout.php");
    exit();
}
?>

<!-- Toggle Button untuk Mobile -->
<button class="sidebar-toggle" id="sidebarToggle">
    <i class="fas fa-bars"></i>
</button>

<!-- Overlay untuk mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo-container">
            <!-- Logo dengan fitur preview dan edit -->
            <div class="logo-wrapper">
                <div class="logo-clickable" id="logoPreview">
                    <img src="<?php echo $base_path . $logo_path; ?>" 
                         alt="<?php echo htmlspecialchars($logo_name); ?> Logo" 
                         class="logo" 
                         onerror="this.style.display='none'; document.getElementById('logoFallback').style.display='flex';">
                    <div id="logoFallback" class="logo-fallback">
                        <span class="logo-text">P</span>
                    </div>
                    
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
                    <!-- Tombol edit logo (hanya untuk admin) -->
                    <div class="logo-edit-overlay">
                        <i class="fas fa-edit"></i>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="brand-text">
                <span class="brand-name">PARAPATAN</span>
                <span class="brand-subtitle">TAILOR</span>
            </div>
            <button class="sidebar-close" id="sidebarClose">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    
    <div class="sidebar-content">
        <ul class="sidebar-menu">
            <!-- Dashboard - Semua Role -->
            <li>
                <a href="<?php echo $base_path; ?>dashboard.php" 
                   class="sidebar-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                   <div class="sidebar-icon">
                       <i class="fas fa-tachometer-alt"></i>
                   </div>
                   <span class="sidebar-text">Dashboard</span>
                </a>
            </li>
            
            <!-- Data Pelanggan - Hanya Admin -->
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
            <li>
                <a href="<?php echo $base_path; ?>pelanggan/pelanggan.php" 
                   class="sidebar-link <?php echo $current_page == 'pelanggan.php' ? 'active' : ''; ?>">
                   <div class="sidebar-icon">
                       <i class="fas fa-users"></i>
                   </div>
                   <span class="sidebar-text">Data Pelanggan</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Data Pesanan - Admin dan Pegawai -->
            <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'pegawai'])): ?>
            <li>
                <a href="<?php echo $base_path; ?>pesanan/pesanan.php" 
                   class="sidebar-link <?php echo $current_page == 'pesanan.php' ? 'active' : ''; ?>">
                   <div class="sidebar-icon">
                       <i class="fas fa-clipboard-list"></i>
                   </div>
                   <span class="sidebar-text">Data Pesanan</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Data Transaksi - Hanya Admin -->
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
            <li>
                <a href="<?php echo $base_path; ?>transaksi/transaksi.php" 
                   class="sidebar-link <?php echo $current_page == 'transaksi.php' ? 'active' : ''; ?>">
                   <div class="sidebar-icon">
                       <i class="fas fa-money-bill-wave"></i>
                   </div>
                   <span class="sidebar-text">Data Keuangan</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Data Karyawan - Hanya Admin -->
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
            <li>
                <a href="<?php echo $base_path; ?>karyawan/karyawan.php" 
                   class="sidebar-link <?php echo $current_page == 'karyawan.php' ? 'active' : ''; ?>">
                   <div class="sidebar-icon">
                       <i class="fas fa-user-tie"></i>
                   </div>
                   <span class="sidebar-text">Data Karyawan</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Laporan - Admin dan Pemilik -->
            <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'pemilik'])): ?>
            <li>
                <a href="<?php echo $base_path; ?>laporan/laporan.php" 
                   class="sidebar-link <?php echo $current_page == 'laporan.php' ? 'active' : ''; ?>">
                   <div class="sidebar-icon">
                       <i class="fas fa-chart-bar"></i>
                   </div>
                   <span class="sidebar-text">Laporan</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Manajemen User - Hanya Admin -->
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
            <li>
                <a href="<?php echo $base_path; ?>users/users.php" 
                   class="sidebar-link <?php echo $current_page == 'users.php' ? 'active' : ''; ?>">
                   <div class="sidebar-icon">
                       <i class="fas fa-user-cog"></i>
                   </div>
                   <span class="sidebar-text">Manajemen User</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Profil - Semua Role -->
            <li>
                <a href="<?php echo $base_path; ?>profile.php" 
                   class="sidebar-link <?php echo $current_page == 'profile.php' ? 'active' : ''; ?>">
                   <div class="sidebar-icon">
                       <i class="fas fa-user-circle"></i>
                   </div>
                   <span class="sidebar-text">Profil Saya</span>
                </a>
            </li>
            
            <!-- Logout - Semua Role -->
            <li>
                <a href="<?php echo $base_path; ?>logout.php" class="sidebar-link logout-link">
                    <div class="sidebar-icon">
                        <i class="fas fa-sign-out-alt"></i>
                    </div>
                    <span class="sidebar-text">Logout</span>
                </a>
            </li>
        </ul>
    </div>
    
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar">
                <?php 
                // Tampilkan avatar berdasarkan inisial nama
                $nama_lengkap = $_SESSION['nama_lengkap'] ?? 'User';
                $initials = strtoupper(substr($nama_lengkap, 0, 1));
                echo $initials;
                ?>
            </div>
            <div class="user-details">
                <span class="user-name"><?= htmlspecialchars($_SESSION['nama_lengkap'] ?? 'User'); ?></span>
                <span class="user-role"><?= ucfirst($_SESSION['role'] ?? 'user'); ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Modal Preview Logo -->
<div class="logo-preview-modal" id="logoModal">
    <div class="logo-preview-content">
        <div class="logo-preview-header">
            <h3 id="logoModalTitle"><?php echo htmlspecialchars($logo_name); ?></h3>
            <div class="logo-preview-actions">
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
                <button class="logo-edit-btn" id="logoEditBtn" title="Ubah Logo">
                    <i class="fas fa-edit"></i>
                </button>
                <?php endif; ?>
                <button class="logo-preview-close" id="logoModalClose">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="logo-preview-body">
            <div class="logo-preview-image-container">
                <img src="<?php echo $base_path . $logo_path; ?>" 
                     alt="<?php echo htmlspecialchars($logo_name); ?> Logo Full" 
                     class="logo-preview-image" 
                     id="logoFullImage">
                <div class="logo-preview-fallback" id="logoFallbackFull" style="display: none;">
                    <div class="logo-preview-fallback-content">
                        <span class="logo-preview-text">PARAPATAN<br>TAILOR</span>
                    </div>
                </div>
            </div>
            
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
            <!-- Form Upload Logo (hidden by default) -->
            <div class="logo-upload-container" id="logoUploadContainer" style="display: none;">
                <form id="logoUploadForm" enctype="multipart/form-data" action="<?php echo $base_path; ?>upload_logo.php" method="POST">
                    <div class="upload-area" id="uploadArea">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <h4>Unggah Logo Baru</h4>
                        <p>Seret file atau klik untuk memilih</p>
                        <p class="file-restrictions">Format: PNG, JPG, JPEG, SVG | Maks: 2MB</p>
                        <input type="file" id="logoFile" name="logo" accept=".png,.jpg,.jpeg,.svg" style="display: none;">
                        <div class="file-info" id="fileInfo"></div>
                    </div>
                    <div class="upload-options">
                        <label for="logoName">Nama Logo:</label>
                        <input type="text" id="logoName" name="logo_name" 
                               value="<?php echo htmlspecialchars($logo_name); ?>" 
                               placeholder="Contoh: Logo Parapatan Tailor 2024">
                        
                        <div class="upload-buttons">
                            <button type="button" class="btn-cancel" id="cancelUpload">Batal</button>
                            <button type="submit" class="btn-upload" id="submitUpload">
                                <i class="fas fa-upload"></i> Upload Logo
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>
        <div class="logo-preview-footer">
            <p id="logoModalFooter">Parapatan Tailor - Jasa Jahit Berkualitas</p>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay" style="display: none;">
    <div class="loading-spinner"></div>
    <p>Mengupload logo...</p>
</div>

<style>
/* Sidebar Styles - Warna Original */
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 280px;
    height: 100vh;
    background: linear-gradient(180deg, #4f46e5 0%, #7c3aed 100%);
    color: white;
    transition: all 0.3s ease;
    z-index: 1000;
    display: flex;
    flex-direction: column;
    box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
    transform: translateX(0);
}

.sidebar.collapsed {
    transform: translateX(-280px);
    box-shadow: none;
}

.sidebar-header {
    padding: 20px 15px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(0, 0, 0, 0.1);
}

.logo-container {
    display: flex;
    align-items: center;
    gap: 12px;
    position: relative;
}

/* ===== PERUBAHAN UKURAN LOGO DI SINI ===== */
.logo-wrapper {
    position: relative;
    width: 55px;
    height: 55px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.logo-clickable {
    width: 100%;
    height: 100%;
    cursor: pointer;
    border-radius: 10px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    background: transparent;
    border: 2px solid rgba(255, 255, 255, 0.3);
}

.logo-clickable:hover {
    transform: scale(1.05);
    box-shadow: 0 0 20px rgba(255, 255, 255, 0.4);
    border-color: rgba(255, 255, 255, 0.6);
}

.logo {
    width: 100%;
    height: 100%;
    border-radius: 8px;
    object-fit: contain;
    border: none;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    background: transparent;
    padding: 0;
    transition: all 0.3s ease;
}

.logo-clickable:hover .logo {
    transform: scale(1.05);
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.2);
}

.logo-fallback {
    width: 100%;
    height: 100%;
    border-radius: 8px;
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    display: none;
    align-items: center;
    justify-content: center;
    border: none;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    transition: all 0.3s ease;
}

.logo-clickable:hover .logo-fallback {
    transform: scale(1.05);
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.2);
}

.logo-text {
    font-size: 1.6rem;
    font-weight: bold;
    color: white;
    text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.4);
}

.brand-text {
    display: flex;
    flex-direction: column;
    flex: 1;
}

.brand-name {
    font-size: 1.2rem;
    font-weight: 800;
    color: #ffffffff;
    line-height: 1.2;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
}

.brand-subtitle {
    font-size: 0.85rem;
    font-weight: 600;
    color: #ffffffff;
    opacity: 0.9;
    line-height: 1.2;
}

.sidebar-close {
    display: none;
    background: none;
    border: none;
    color: white;
    font-size: 1.2rem;
    cursor: pointer;
    padding: 5px;
    border-radius: 4px;
    transition: background 0.3s ease;
}

.sidebar-close:hover {
    background: rgba(255, 255, 255, 0.1);
}

.sidebar-content {
    flex: 1;
    overflow-y: auto;
    padding: 15px 0;
}

.sidebar-menu {
    list-style: none;
    padding: 0;
    margin: 0;
}

.sidebar-menu li {
    margin: 5px 0;
}

.sidebar-link {
    display: flex;
    align-items: center;
    padding: 12px 20px;
    color: #ecf0f1;
    text-decoration: none;
    transition: all 0.3s ease;
    border-left: 3px solid transparent;
}

.sidebar-link:hover {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    border-left-color: rgba(255, 255, 255, 0.5);
}

.sidebar-link.active {
    background: rgba(255, 255, 255, 0.15);
    color: white;
    border-left-color: #ffffff;
}

.sidebar-link.logout-link {
    color: #f8d7da;
}

.sidebar-link.logout-link:hover {
    background: rgba(248, 215, 218, 0.1);
    color: #f8d7da;
}

.sidebar-icon {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
    font-size: 1rem;
}

.sidebar-text {
    font-size: 0.9rem;
    font-weight: 600;
    white-space: nowrap;
}

.sidebar-footer {
    padding: 15px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(0, 0, 0, 0.1);
}

.user-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    font-weight: bold;
    color: white;
    border: 2px solid rgba(255, 255, 255, 0.2);
}

.user-details {
    display: flex;
    flex-direction: column;
    flex: 1;
}

.user-name {
    font-size: 0.85rem;
    font-weight: 600;
    color: white;
}

.user-role {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.8);
    text-transform: capitalize;
}

/* ===== PERBAIKAN TOGGLE BUTTON ===== */
/* Style dasar - transparan */
.sidebar-toggle {
    position: fixed;
    top: 20px;
    left: 15px;
    z-index: 999;
    background: rgba(79, 70, 229, 0.15);
    color: rgba(255, 255, 255, 0.7);
    border: none;
    border-radius: 6px;
    width: 45px;
    height: 45px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 1.3rem;
    backdrop-filter: blur(5px);
    -webkit-backdrop-filter: blur(5px);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.1);
    opacity: 0.7;
}

/* Hover effect - menjadi jelas */
.sidebar-toggle:hover {
    background: rgba(79, 70, 229, 0.9);
    color: white;
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    opacity: 1;
    border-color: rgba(255, 255, 255, 0.3);
}

/* Active state */
.sidebar-toggle:active {
    transform: scale(0.95);
    transition: transform 0.1s ease;
}

/* Ketika sidebar collapsed di desktop */
.sidebar.collapsed + .sidebar-toggle {
    left: 15px;
    opacity: 0.7;
}

.sidebar.collapsed + .sidebar-toggle:hover {
    opacity: 1;
}

/* Ketika sidebar tidak collapsed di desktop */
.sidebar:not(.collapsed) + .sidebar-toggle {
    left: 295px;
    opacity: 0.7;
}

.sidebar:not(.collapsed) + .sidebar-toggle:hover {
    opacity: 1;
}

/* Style untuk mobile */
@media (max-width: 1024px) {
    .sidebar-toggle {
        top: 15px;
        left: 15px;
        background: rgba(79, 70, 229, 0.2);
        opacity: 0.8;
    }
    
    .sidebar-toggle:hover {
        background: rgba(79, 70, 229, 0.9);
        opacity: 1;
    }
    
    /* Tombol toggle HILANG ketika sidebar open di mobile */
    .sidebar.open + .sidebar-toggle {
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        transform: scale(0.8);
    }
    
    /* Tombol toggle MUNCUL ketika sidebar closed di mobile */
    .sidebar:not(.open) + .sidebar-toggle {
        opacity: 0.8;
        visibility: visible;
        pointer-events: auto;
        transform: scale(1);
    }
}

/* Overlay untuk Mobile */
.sidebar-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 998;
    backdrop-filter: blur(2px);
}

.sidebar-overlay.active {
    display: block;
    animation: fadeIn 0.3s ease;
}

/* Modal Preview Logo */
.logo-preview-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.85);
    z-index: 2000;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(8px);
    animation: fadeIn 0.3s ease;
}

.logo-preview-modal.active {
    display: flex;
}

.logo-preview-content {
    background: white;
    border-radius: 12px;
    width: 90%;
    max-width: 500px;
    overflow: hidden;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5);
    animation: slideUp 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    transform-origin: center;
}

.logo-preview-header {
    padding: 20px 25px;
    background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.logo-preview-header h3 {
    margin: 0;
    font-size: 1.4rem;
    font-weight: 600;
}

.logo-preview-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

.logo-edit-btn {
    background: rgba(255, 255, 255, 0.2);
    border: none;
    color: white;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.logo-edit-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: scale(1.1);
}

.logo-preview-close {
    background: rgba(255, 255, 255, 0.2);
    border: none;
    color: white;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 1.1rem;
    transition: all 0.3s ease;
}

.logo-preview-close:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: rotate(90deg);
}

.logo-preview-body {
    padding: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8fafc;
    min-height: 300px;
    flex-direction: column;
}

.logo-preview-image-container {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 20px;
}

.logo-preview-image {
    max-width: 100%;
    max-height: 300px;
    object-fit: contain;
    border-radius: 8px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    border: none;
    background: transparent;
    padding: 0;
}

.logo-preview-fallback {
    width: 100%;
    height: 100%;
    display: none;
    align-items: center;
    justify-content: center;
}

.logo-preview-fallback-content {
    padding: 40px;
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    min-width: 300px;
    min-height: 200px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.logo-preview-text {
    font-size: 2.2rem;
    font-weight: bold;
    color: white;
    line-height: 1.3;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
    letter-spacing: 1px;
}

.logo-preview-footer {
    padding: 15px 25px;
    background: #f1f5f9;
    text-align: center;
    border-top: 1px solid #e2e8f0;
}

.logo-preview-footer p {
    margin: 0;
    color: #64748b;
    font-size: 0.9rem;
    font-weight: 500;
}

/* Logo Edit Overlay */
.logo-edit-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
    cursor: pointer;
}

.logo-clickable:hover .logo-edit-overlay {
    opacity: 1;
}

.logo-edit-overlay i {
    color: white;
    font-size: 1.2rem;
    background: rgba(79, 70, 229, 0.8);
    padding: 8px;
    border-radius: 50%;
}

/* Upload Container */
.logo-upload-container {
    width: 100%;
    margin-top: 20px;
    padding: 20px;
    background: #f8fafc;
    border-radius: 8px;
    border: 2px dashed #cbd5e1;
}

.upload-area {
    text-align: center;
    padding: 30px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.upload-area:hover {
    background: #f1f5f9;
    border-color: #4f46e5;
}

.upload-area i {
    font-size: 3rem;
    color: #94a3b8;
    margin-bottom: 15px;
}

.upload-area h4 {
    margin: 10px 0;
    color: #334155;
}

.upload-area p {
    color: #64748b;
    margin-bottom: 5px;
}

.upload-area .file-restrictions {
    font-size: 0.8rem;
    color: #94a3b8;
    font-style: italic;
    margin-top: 10px;
}

.file-info {
    padding: 10px;
    background: #e2e8f0;
    border-radius: 4px;
    margin-top: 10px;
    display: none;
    font-size: 0.9rem;
}

.file-info.active {
    display: block;
}

.upload-options {
    margin-top: 20px;
}

.upload-options label {
    display: block;
    margin-bottom: 8px;
    color: #334155;
    font-weight: 500;
}

.upload-options input[type="text"] {
    width: 100%;
    padding: 10px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    margin-bottom: 15px;
    font-size: 0.9rem;
}

.upload-options input[type="text"]:focus {
    outline: none;
    border-color: #4f46e5;
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

.upload-buttons {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.btn-cancel, .btn-upload {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s ease;
    font-size: 0.9rem;
}

.btn-cancel {
    background: #e2e8f0;
    color: #475569;
}

.btn-cancel:hover {
    background: #cbd5e1;
}

.btn-upload {
    background: #4f46e5;
    color: white;
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-upload:hover {
    background: #4338ca;
}

/* Loading Overlay */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    display: none;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    z-index: 3000;
    backdrop-filter: blur(5px);
}

.loading-overlay.active {
    display: flex;
}

.loading-spinner {
    width: 50px;
    height: 50px;
    border: 5px solid #f3f3f3;
    border-top: 5px solid #4f46e5;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 20px;
}

.loading-overlay p {
    color: white;
    font-size: 1.1rem;
}

/* Success/Error Messages */
.logo-message {
    padding: 12px 15px;
    border-radius: 6px;
    margin: 15px 0;
    display: none;
    align-items: center;
    gap: 10px;
}

.logo-message i {
    font-size: 1.2rem;
}

.logo-message.success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
    display: flex;
}

.logo-message.error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
    display: flex;
}

/* Animations */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideUp {
    from { 
        opacity: 0;
        transform: translateY(30px) scale(0.95);
    }
    to { 
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* ============ RESPONSIVE DESIGN ============ */

/* Main content adjustment - DINAMIS */
.main-content {
    margin-left: 280px;
    padding: 20px;
    transition: all 0.3s ease;
    width: calc(100% - 280px);
}

/* Ketika sidebar collapsed */
.sidebar.collapsed ~ .main-content {
    margin-left: 0;
    width: 100%;
    padding: 20px;
}

/* Desktop Toggle Button */
@media (min-width: 1025px) {
    .sidebar-toggle {
        display: flex;
        top: 20px;
        left: 295px;
        transition: all 0.3s ease;
        z-index: 1001;
    }
    
    .sidebar.collapsed + .sidebar-toggle {
        left: 15px;
    }
    
    .sidebar:not(.collapsed) + .sidebar-toggle {
        left: 295px;
    }
    
    .sidebar-close {
        display: none;
    }
    
    .sidebar-overlay {
        display: none !important;
    }
    
    /* Sidebar bisa di-collapse di desktop */
    .sidebar {
        transform: translateX(0);
        transition: transform 0.3s ease;
    }
    
    .sidebar.collapsed {
        transform: translateX(-280px);
    }
}

/* Tablet & Mobile */
@media (max-width: 1024px) {
    .sidebar {
        width: 280px;
        transform: translateX(-100%);
        z-index: 1000;
        transition: transform 0.3s ease;
    }
    
    .sidebar.open {
        transform: translateX(0);
        box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
    }
    
    .sidebar.collapsed {
        transform: translateX(-100%);
    }
    
    .sidebar-toggle {
        display: flex;
        left: 15px;
        top: 15px;
        z-index: 999;
        transition: all 0.3s ease;
    }
    
    /* Tombol toggle HILANG ketika sidebar open di mobile */
    .sidebar.open + .sidebar-toggle {
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
    }
    
    /* Tombol toggle MUNCUL ketika sidebar closed di mobile */
    .sidebar:not(.open) + .sidebar-toggle {
        opacity: 0.8;
        visibility: visible;
        pointer-events: auto;
    }
    
    .sidebar-close {
        display: block;
    }
    
    .sidebar-overlay.active {
        display: block;
        animation: fadeIn 0.3s ease;
    }
    
    /* Main content full width di mobile */
    .main-content {
        margin-left: 0;
        width: 100%;
        padding: 70px 15px 20px;
    }
    
    .sidebar ~ .main-content {
        margin-left: 0;
        width: 100%;
    }
}

/* ===== PERUBAHAN UKURAN LOGO UNTUK RESPONSIVE ===== */
@media (max-width: 768px) {
    .sidebar {
        width: 100%;
        max-width: 300px;
    }
    
    .brand-name {
        font-size: 1rem;
    }
    
    .brand-subtitle {
        font-size: 0.75rem;
    }
    
    .sidebar-link {
        padding: 15px 20px;
    }
    
    .sidebar-text {
        font-size: 0.95rem;
    }
    
    /* Logo lebih kecil di tablet */
    .logo-wrapper {
        width: 50px;
        height: 50px;
    }
    
    .logo-clickable {
        border: 1.5px solid rgba(255, 255, 255, 0.3);
    }
    
    .logo-text {
        font-size: 1.4rem;
    }
    
    .logo-preview-content {
        width: 95%;
        max-width: 450px;
    }
    
    .logo-preview-body {
        padding: 30px;
        min-height: 250px;
    }
    
    .logo-preview-image {
        max-height: 250px;
    }
    
    .logo-preview-text {
        font-size: 1.8rem;
    }
    
    .logo-upload-container {
        padding: 15px;
    }
    
    .upload-area {
        padding: 20px;
    }
    
    .main-content {
        padding: 70px 15px 20px;
    }
    
    .sidebar-toggle {
        width: 42px;
        height: 42px;
        font-size: 1.2rem;
        background: rgba(79, 70, 229, 0.25);
    }
    
    .sidebar-toggle:hover {
        background: rgba(79, 70, 229, 0.9);
    }
}

@media (max-width: 480px) {
    .sidebar {
        max-width: 280px;
    }
    
    /* Logo lebih kecil di mobile */
    .logo-wrapper {
        width: 45px;
        height: 45px;
    }
    
    .logo-clickable {
        border: 1px solid rgba(255, 255, 255, 0.3);
    }
    
    .brand-name {
        font-size: 0.95rem;
    }
    
    .sidebar-link {
        padding: 12px 15px;
    }
    
    .logo-text {
        font-size: 1.2rem;
    }
    
    .logo-preview-content {
        width: 95%;
        max-width: 350px;
    }
    
    .logo-preview-header {
        padding: 15px 20px;
    }
    
    .logo-preview-header h3 {
        font-size: 1.2rem;
    }
    
    .logo-preview-body {
        padding: 20px;
        min-height: 200px;
    }
    
    .logo-preview-image {
        max-height: 200px;
    }
    
    .logo-preview-fallback-content {
        padding: 25px;
        min-width: 250px;
        min-height: 150px;
    }
    
    .logo-preview-text {
        font-size: 1.5rem;
    }
    
    .logo-preview-footer {
        padding: 12px 15px;
    }
    
    .logo-preview-footer p {
        font-size: 0.8rem;
    }
    
    .upload-area {
        padding: 15px;
    }
    
    .upload-area i {
        font-size: 2.5rem;
    }
    
    .upload-area h4 {
        font-size: 1rem;
    }
    
    .upload-area p {
        font-size: 0.85rem;
    }
    
    .btn-cancel, .btn-upload {
        padding: 8px 15px;
        font-size: 0.85rem;
    }
    
    .sidebar-toggle {
        width: 40px;
        height: 40px;
        font-size: 1.1rem;
        background: rgba(79, 70, 229, 0.3);
    }
    
    .sidebar-toggle:hover {
        background: rgba(79, 70, 229, 0.9);
    }
    
    /* Logo edit overlay lebih kecil di mobile */
    .logo-edit-overlay i {
        font-size: 1rem;
        padding: 6px;
    }
}

/* Scrollbar Styling */
.sidebar-content::-webkit-scrollbar {
    width: 4px;
}

.sidebar-content::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
}

.sidebar-content::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.3);
    border-radius: 2px;
}

.sidebar-content::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.5);
}

/* CSS untuk halaman lain agar responsif dengan sidebar collapse */
body {
    transition: padding-left 0.3s ease;
}

/* Untuk halaman dengan tabel/data grid */
.data-table-container,
.table-responsive,
.card,
.container-fluid {
    transition: all 0.3s ease;
}

/* Pastikan tabel/data responsif ketika sidebar collapse */
@media (min-width: 1025px) {
    .sidebar.collapsed ~ .main-content .container-fluid,
    .sidebar.collapsed ~ .main-content .data-table-container,
    .sidebar.collapsed ~ .main-content .table-responsive {
        max-width: 100%;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ============ SIDEBAR FUNCTIONALITY ============
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarClose = document.getElementById('sidebarClose');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const mainContent = document.querySelector('.main-content');
    
    // Cek state sidebar dari localStorage
    let sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    
    // Set initial state
    if (window.innerWidth > 1024) {
        // Desktop mode
        if (sidebarCollapsed) {
            sidebar.classList.add('collapsed');
            if (mainContent) {
                mainContent.style.marginLeft = '0';
                mainContent.style.width = '100%';
            }
        }
        updateToggleButtonPosition();
    } else {
        // Mobile mode - selalu closed
        sidebar.classList.remove('open');
        if (sidebarOverlay) sidebarOverlay.classList.remove('active');
        document.body.style.overflow = '';
        updateToggleButtonPosition();
    }
    
    // Toggle sidebar
    function toggleSidebar() {
        if (window.innerWidth > 1024) {
            // Desktop: collapse/expand
            sidebar.classList.toggle('collapsed');
            sidebarCollapsed = sidebar.classList.contains('collapsed');
            
            // Simpan state ke localStorage
            localStorage.setItem('sidebarCollapsed', sidebarCollapsed);
            
            // Update main content
            if (mainContent) {
                if (sidebarCollapsed) {
                    mainContent.style.marginLeft = '0';
                    mainContent.style.width = '100%';
                } else {
                    mainContent.style.marginLeft = '280px';
                    mainContent.style.width = 'calc(100% - 280px)';
                }
            }
        } else {
            // Mobile: open/close
            const isOpening = !sidebar.classList.contains('open');
            sidebar.classList.toggle('open');
            
            if (sidebarOverlay) {
                if (isOpening) {
                    sidebarOverlay.classList.add('active');
                    document.body.style.overflow = 'hidden';
                } else {
                    sidebarOverlay.classList.remove('active');
                    document.body.style.overflow = '';
                }
            }
        }
        
        // Update toggle button position
        updateToggleButtonPosition();
    }
    
    // Update position toggle button
    function updateToggleButtonPosition() {
        if (window.innerWidth > 1024) {
            // Desktop
            if (sidebar.classList.contains('collapsed')) {
                sidebarToggle.style.left = '15px';
                sidebarToggle.innerHTML = '<i class="fas fa-bars"></i>';
                sidebarToggle.title = 'Buka Sidebar';
                sidebarToggle.setAttribute('aria-label', 'Buka Sidebar');
            } else {
                sidebarToggle.style.left = '295px';
                sidebarToggle.innerHTML = '<i class="fas fa-chevron-left"></i>';
                sidebarToggle.title = 'Tutup Sidebar';
                sidebarToggle.setAttribute('aria-label', 'Tutup Sidebar');
            }
            // Tampilkan selalu di desktop
            sidebarToggle.style.opacity = '0.7';
            sidebarToggle.style.visibility = 'visible';
            sidebarToggle.style.pointerEvents = 'auto';
        } else {
            // Mobile
            if (sidebar.classList.contains('open')) {
                // Tombol toggle HILANG di mobile ketika sidebar open
                sidebarToggle.style.opacity = '0';
                sidebarToggle.style.visibility = 'hidden';
                sidebarToggle.style.pointerEvents = 'none';
                sidebarToggle.style.transform = 'scale(0.8)';
            } else {
                // Tombol toggle MUNCUL di mobile ketika sidebar closed
                sidebarToggle.style.left = '15px';
                sidebarToggle.style.opacity = '0.8';
                sidebarToggle.style.visibility = 'visible';
                sidebarToggle.style.pointerEvents = 'auto';
                sidebarToggle.style.transform = 'scale(1)';
                sidebarToggle.innerHTML = '<i class="fas fa-bars"></i>';
                sidebarToggle.title = 'Buka Sidebar';
                sidebarToggle.setAttribute('aria-label', 'Buka Sidebar');
            }
        }
    }
    
    // Event listeners untuk sidebar
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', toggleSidebar);
        // Tambah efek hover
        sidebarToggle.addEventListener('mouseenter', function() {
            if (window.innerWidth > 1024 || !sidebar.classList.contains('open')) {
                this.style.opacity = '1';
                this.style.transform = 'scale(1.05)';
            }
        });
        
        sidebarToggle.addEventListener('mouseleave', function() {
            if (window.innerWidth > 1024 || !sidebar.classList.contains('open')) {
                this.style.opacity = '0.7';
                this.style.transform = 'scale(1)';
            }
        });
    }
    
    if (sidebarClose) {
        sidebarClose.addEventListener('click', function() {
            toggleSidebar();
        });
    }
    
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function() {
            if (window.innerWidth <= 1024) {
                toggleSidebar();
            }
        });
    }
    
    // Close sidebar ketika mengklik link (mobile)
    const sidebarLinks = document.querySelectorAll('.sidebar-link:not(.logout-link)');
    sidebarLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            if (window.innerWidth <= 1024) {
                // Delay sedikit untuk memberikan feedback visual
                setTimeout(() => {
                    toggleSidebar();
                }, 100);
            }
        });
    });
    
    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 1024) {
            // Desktop: reset mobile state
            sidebar.classList.remove('open');
            if (sidebarOverlay) sidebarOverlay.classList.remove('active');
            document.body.style.overflow = '';
            
            // Apply desktop collapse state
            if (sidebarCollapsed) {
                sidebar.classList.add('collapsed');
                if (mainContent) {
                    mainContent.style.marginLeft = '0';
                    mainContent.style.width = '100%';
                }
            } else {
                sidebar.classList.remove('collapsed');
                if (mainContent) {
                    mainContent.style.marginLeft = '280px';
                    mainContent.style.width = 'calc(100% - 280px)';
                }
            }
        } else {
            // Mobile: reset desktop collapse state dan close sidebar
            sidebar.classList.remove('collapsed');
            sidebar.classList.remove('open');
            if (sidebarOverlay) sidebarOverlay.classList.remove('active');
            document.body.style.overflow = '';
            if (mainContent) {
                mainContent.style.marginLeft = '0';
                mainContent.style.width = '100%';
            }
        }
        
        // Update toggle button position
        updateToggleButtonPosition();
    });
    
    // Initial update toggle button
    updateToggleButtonPosition();
    
    // Add active state to current page
    const currentPage = '<?php echo $current_page; ?>';
    const links = document.querySelectorAll('.sidebar-link');
    links.forEach(link => {
        const href = link.getAttribute('href');
        if (href && href.includes(currentPage)) {
            link.classList.add('active');
        }
    });
    
    // ============ LOGO PREVIEW MODAL ============
    const logoModal = document.getElementById('logoModal');
    const logoModalClose = document.getElementById('logoModalClose');
    const logoPreview = document.getElementById('logoPreview');
    const logoFullImage = document.getElementById('logoFullImage');
    const logoFallbackFull = document.getElementById('logoFallbackFull');
    
    // Toggle logo modal
    function toggleLogoModal() {
        logoModal.classList.toggle('active');
        document.body.style.overflow = logoModal.classList.contains('active') ? 'hidden' : '';
        
        // Handle fallback jika gambar gagal dimuat di modal
        if (logoModal.classList.contains('active')) {
            setTimeout(() => {
                if (logoFullImage.complete && logoFullImage.naturalHeight === 0) {
                    logoFullImage.style.display = 'none';
                    logoFallbackFull.style.display = 'flex';
                }
                logoFullImage.onerror = function() {
                    logoFullImage.style.display = 'none';
                    logoFallbackFull.style.display = 'flex';
                };
            }, 100);
        }
    }
    
    // Event listeners untuk logo preview
    if (logoPreview) {
        logoPreview.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            toggleLogoModal();
        });
    }
    
    // Cegah event bubbling dari overlay edit logo
    const logoEditOverlay = document.querySelector('.logo-edit-overlay');
    if (logoEditOverlay) {
        logoEditOverlay.addEventListener('click', function(e) {
            e.stopPropagation();
            if (logoPreview) {
                logoPreview.click();
                setTimeout(() => {
                    const editBtn = document.getElementById('logoEditBtn');
                    if (editBtn) editBtn.click();
                }, 100);
            }
        });
    }
    
    if (logoModalClose) {
        logoModalClose.addEventListener('click', function(e) {
            e.preventDefault();
            toggleLogoModal();
        });
    }
    
    // Tutup modal saat klik di luar konten modal
    if (logoModal) {
        logoModal.addEventListener('click', function(e) {
            if (e.target === logoModal) {
                toggleLogoModal();
            }
        });
    }
    
    // Tutup modal dengan tombol ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && logoModal && logoModal.classList.contains('active')) {
            toggleLogoModal();
        }
    });
    
    // Handle error pada gambar logo di modal
    if (logoFullImage) {
        logoFullImage.addEventListener('error', function() {
            this.style.display = 'none';
            if (logoFallbackFull) logoFallbackFull.style.display = 'flex';
        });
    }
    
    // ============ FITUR UPLOAD LOGO ============
    const logoEditBtn = document.getElementById('logoEditBtn');
    const logoUploadContainer = document.getElementById('logoUploadContainer');
    const uploadArea = document.getElementById('uploadArea');
    const logoFileInput = document.getElementById('logoFile');
    const fileInfo = document.getElementById('fileInfo');
    const cancelUpload = document.getElementById('cancelUpload');
    const logoUploadForm = document.getElementById('logoUploadForm');
    const loadingOverlay = document.getElementById('loadingOverlay');
    
    // Toggle form upload
    if (logoEditBtn) {
        logoEditBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const isUploadVisible = logoUploadContainer.style.display === 'block';
            logoUploadContainer.style.display = isUploadVisible ? 'none' : 'block';
            this.innerHTML = isUploadVisible ? 
                '<i class="fas fa-edit"></i>' : 
                '<i class="fas fa-times"></i>';
            this.title = isUploadVisible ? 'Ubah Logo' : 'Tutup Form';
            
            if (isUploadVisible) {
                resetForm();
            }
        });
    }
    
    // Drag and drop untuk upload
    if (uploadArea) {
        uploadArea.addEventListener('click', function(e) {
            e.stopPropagation();
            if (logoFileInput) logoFileInput.click();
        });
        
        uploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.style.background = '#f1f5f9';
            this.style.borderColor = '#4f46e5';
        });
        
        uploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.style.background = '';
            this.style.borderColor = '#cbd5e1';
        });
        
        uploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.style.background = '';
            this.style.borderColor = '#cbd5e1';
            
            const files = e.dataTransfer.files;
            if (files.length > 0 && logoFileInput) {
                handleFileSelect(files[0]);
            }
        });
    }
    
    // File input change
    if (logoFileInput) {
        logoFileInput.addEventListener('change', function(e) {
            if (this.files.length > 0) {
                handleFileSelect(this.files[0]);
            }
        });
    }
    
    // Fungsi handle file
    function handleFileSelect(file) {
        const validTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/svg+xml'];
        const maxSize = 2 * 1024 * 1024;
        
        if (!validTypes.includes(file.type)) {
            showMessage('Hanya file PNG, JPG, JPEG, atau SVG yang diperbolehkan', 'error');
            if (logoFileInput) logoFileInput.value = '';
            return;
        }
        
        if (file.size > maxSize) {
            showMessage('Ukuran file maksimal 2MB', 'error');
            if (logoFileInput) logoFileInput.value = '';
            return;
        }
        
        if (fileInfo) {
            fileInfo.innerHTML = `
                <strong>${file.name}</strong><br>
                ${(file.size / 1024).toFixed(2)} KB
            `;
            fileInfo.classList.add('active');
        }
    }
    
    // Cancel upload
    if (cancelUpload) {
        cancelUpload.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (logoUploadContainer) logoUploadContainer.style.display = 'none';
            if (logoEditBtn) {
                logoEditBtn.innerHTML = '<i class="fas fa-edit"></i>';
                logoEditBtn.title = 'Ubah Logo';
            }
            resetForm();
        });
    }
    
    // Submit form upload dengan AJAX
    if (logoUploadForm) {
        logoUploadForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!logoFileInput || !logoFileInput.files.length) {
                showMessage('Pilih file logo terlebih dahulu', 'error');
                return;
            }
            
            const formData = new FormData(this);
            
            if (loadingOverlay) {
                loadingOverlay.classList.add('active');
            }
            
            fetch(this.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                const contentType = response.headers.get("content-type");
                if (!contentType || !contentType.includes("application/json")) {
                    throw new TypeError("Response bukan JSON valid");
                }
                return response.json();
            })
            .then(data => {
                if (loadingOverlay) {
                    loadingOverlay.classList.remove('active');
                }
                
                if (data.success) {
                    showMessage(data.message, 'success');
                    resetForm();
                    
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showMessage(data.message || 'Terjadi kesalahan saat upload', 'error');
                    resetForm();
                }
            })
            .catch(error => {
                if (loadingOverlay) {
                    loadingOverlay.classList.remove('active');
                }
                
                console.error('Upload error:', error);
                
                if (error.name === 'TypeError' && error.message.includes('JSON')) {
                    showMessage('Server mengembalikan response yang tidak valid. Cek console untuk detail.', 'error');
                } else {
                    showMessage('Terjadi kesalahan jaringan: ' + error.message, 'error');
                }
                resetForm();
            });
        });
    }
    
    // Fungsi reset form
    function resetForm() {
        if (logoUploadForm) logoUploadForm.reset();
        if (fileInfo) {
            fileInfo.innerHTML = '';
            fileInfo.classList.remove('active');
        }
    }
    
    // Fungsi tampilkan pesan
    function showMessage(message, type) {
        const existingMsg = document.querySelector('.logo-message');
        if (existingMsg) existingMsg.remove();
        
        const messageDiv = document.createElement('div');
        messageDiv.className = `logo-message ${type}`;
        messageDiv.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
            <span>${message}</span>
        `;
        
        if (logoUploadContainer) {
            logoUploadContainer.appendChild(messageDiv);
            
            setTimeout(() => {
                if (messageDiv.parentNode) {
                    messageDiv.remove();
                }
            }, 5000);
        }
    }
    
    // Tambahkan efek ripple pada logo
    if (logoPreview) {
        logoPreview.addEventListener('click', function(e) {
            const existingRipple = this.querySelector('.ripple');
            if (existingRipple) {
                existingRipple.remove();
            }
            
            const ripple = document.createElement('span');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.cssText = `
                position: absolute;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.4);
                transform: scale(0);
                animation: ripple-animation 0.6s linear;
                width: ${size}px;
                height: ${size}px;
                top: ${y}px;
                left: ${x}px;
                pointer-events: none;
                z-index: 1;
            `;
            
            ripple.classList.add('ripple');
            this.appendChild(ripple);
            
            setTimeout(() => {
                if (ripple.parentNode) {
                    ripple.remove();
                }
            }, 600);
        });
    }
    
    // Style untuk ripple animation
    const rippleStyle = document.createElement('style');
    rippleStyle.textContent = `
        @keyframes ripple-animation {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(rippleStyle);
});
</script>