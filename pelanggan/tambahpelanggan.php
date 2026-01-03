<?php
// pelanggan/tambahpelanggan.php
require_once '../config/database.php';
check_login();
check_role(['admin', 'pemilik']);

// Proses tambah data pelanggan
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah'])) {
    $nama = clean_input($_POST['nama']);
    $no_hp = clean_input($_POST['no_hp']);
    $alamat = clean_input($_POST['alamat']);
    
    try {
        // Generate ID pelanggan baru
        $sql_id = "SELECT MAX(id_pelanggan) as max_id FROM data_pelanggan";
        $stmt_id = $pdo->query($sql_id);
        $max_id = $stmt_id->fetchColumn();
        
        // Format: PLG-001, PLG-002, dst
        $next_number = 1;
        if ($max_id) {
            $last_number = (int) substr($max_id, 4); // Ambil angka setelah "PLG-"
            $next_number = $last_number + 1;
        }
        $id_pelanggan = 'PLG-' . str_pad($next_number, 3, '0', STR_PAD_LEFT);
        
        // Insert data baru
        $sql = "INSERT INTO data_pelanggan (id_pelanggan, nama, no_hp, alamat, created_at) VALUES (?, ?, ?, ?, NOW())";
        $stmt = executeQuery($sql, [$id_pelanggan, $nama, $no_hp, $alamat]);
        
        $_SESSION['success'] = "✅ Data pelanggan berhasil ditambahkan!";
        log_activity("Menambah pelanggan baru: $nama (ID: $id_pelanggan)");
        
        // Set flag untuk modal success
        $show_success_modal = true;
        
    } catch (PDOException $e) {
        $_SESSION['error'] = "❌ Error: " . $e->getMessage();
        header("Location: tambahpelanggan.php");
        exit();
    }
}

$page_title = "Tambah Pelanggan";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Pelanggan - Sistem Apotek Deka Medika</title>
     <link rel="shortcut icon" href="../assets/images/logoterakhir.png" type="image/x-icon" />

    <!-- Bootstrap 5.3.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../includes/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* CSS Custom dari editpelanggan.php */
        body {
            background-color: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        
        h2 {
            font-family: 'Arial', sans-serif;
            color: #2e59d9;
            text-align: left;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #3498db;
            font-weight: bold;
        }
        
        .card {
            border-radius: 15px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            background-color: #f8f9fa;
        }
        
        .form-control {
            width: 100%;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 10px;
            transition: all 0.2s ease-in-out;
        }
        
        .form-control:focus {
            border-color: #007BFF;
            box-shadow: 0 0 0 3px rgba(0,123,255,0.25);
        }
        
        .form-group label {
            font-weight: bold;
            margin-bottom: 8px;
            color: #495057;
        }
        
        .btn-primary {
            background-color: #007BFF;
            border-color: #007BFF;
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: bold;
        }
        
        .btn-secondary {
            border-radius: 8px;
            padding: 10px 20px;
            background-color: #6c757d;
        }
        
        .btn-success {
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        
        .btn-secondary:hover, .btn-primary:hover, .btn-success:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        .alert {
            margin-top: 10px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            border-radius: 8px;
            padding: 12px;
            font-size: 14px;
        }
        
        .form-group textarea {
            resize: none;
            min-height: 100px;
        }
        
        .text-end {
            text-align: right;
        }
        
        .info-box {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            border-left: 4px solid #007bff;
        }
        
        .info-box p {
            margin: 0;
            color: #495057;
            line-height: 1.5;
        }
        
        label {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .form-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        
        .btn-success:hover { background-color: #218838; transform: translateY(-1px); }
        .btn-secondary:hover { background-color: #545b62; transform: translateY(-1px); }
        .btn-warning:hover { background-color: #e0a800; transform: translateY(-1px); }

        /* Modal Success Custom Styling */
        .success-modal .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        .success-modal .modal-header {
            border-bottom: none;
            padding: 2rem 2rem 0;
        }
        
        .success-modal .modal-body {
            padding: 2rem;
            text-align: center;
        }
        
        /* Loading Container */
        .loading-container {
            position: relative;
            width: 100px;
            height: 100px;
            margin: 0 auto 2rem;
        }
        
        /* Circle Loading */
        .circle-loading {
            width: 80px;
            height: 80px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #7c3aed;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }
        
        /* Checkmark */
        .checkmark {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: block;
            stroke-width: 2;
            stroke: #fff;
            stroke-miterlimit: 10;
            margin: 0 auto;
            box-shadow: inset 0px 0px 0px #7c3aed;
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
            stroke: #7c3aed;
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
            height: 4px;
            background: #f3f3f3;
            border-radius: 2px;
            margin: 2rem auto 0;
            overflow: hidden;
            position: relative;
        }
        
        .line-loading {
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, #7c3aed, #20c997, #7c3aed);
            animation: lineLoading 2s infinite;
            border-radius: 2px;
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
                box-shadow: inset 0px 0px 0px 40px #7c3aed;
            }
        }
        
        /* Success Message */
        .success-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #7c3aed;
            margin-bottom: 1rem;
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.5s ease 0.5s;
        }
        
        .success-title.show {
            opacity: 1;
            transform: translateY(0);
        }
        
        .redirect-message {
            color: #6c757d;
            font-size: 0.9rem;
            opacity: 0;
            transform: translateY(10px);
            transition: all 0.5s ease 1s;
        }
        
        .redirect-message.show {
            opacity: 1;
            transform: translateY(0);
        }

        /* Custom styling untuk halaman tambah */
        .info-box-tambah {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            border-left: 4px solid #2196f3;
            box-shadow: 0 2px 8px rgba(33, 150, 243, 0.1);
        }
        
        .info-box-tambah h5 {
            color: #1976d2;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .info-box-tambah p {
            color: #455a64;
            margin: 0;
            font-size: 0.9rem;
            line-height: 1.5;
        }
        
        .form-section {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
            border: 1px solid #e3f2fd;
        }
        
        .form-section h4 {
            color: #1976d2;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e3f2fd;
            font-weight: 600;
        }

        /* Penataan untuk responsif pada layar kecil */
        @media (max-width: 768px) {
            .container {
                padding: 10px;
                margin: 1rem;
            }
            .text-end {
                text-align: center;
            }
            .form-actions { 
                flex-direction: column; 
            }
            .btn { 
                width: 100%; 
            }
            
            .success-modal .modal-body {
                padding: 1.5rem;
            }
            
            .loading-container {
                width: 80px;
                height: 80px;
            }
            
            .circle-loading {
                width: 60px;
                height: 60px;
            }
            
            .checkmark {
                width: 60px;
                height: 60px;
            }
            
            .success-title {
                font-size: 1.3rem;
            }
            
            .form-section {
                padding: 1.5rem;
            }
        }
    </style>
</head>

<body>
    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/header.php'; ?>

    <div class="main-content">
        <div class="content-body">
            <div class="container">
                <h2 class="my-4">Tambah Data Pelanggan</h2>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger">
                        <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <!-- Info Box untuk Tambah Pelanggan -->
                    <div class="info-box-tambah">
                        <h5><i class="fas fa-info-circle"></i> Informasi Tambah Pelanggan</h5>
                        <p>Silakan lengkapi form di bawah ini untuk menambahkan data pelanggan baru. Pastikan semua data yang dimasukkan valid dan akurat.</p>
                    </div>

                    <form method="POST" class="needs-validation" novalidate>
                        <div class="form-section">
                            <h4><i class="fas fa-user-plus"></i> Data Pribadi Pelanggan</h4>
                            
                            <div class="row">
                                <div class="col-md-6 form-group mb-3">
                                    <label for="nama" class="form-label">Nama Pelanggan <span class="text-danger">*</span></label>
                                    <input type="text" id="nama" name="nama" class="form-control" 
                                           placeholder="Masukkan nama lengkap pelanggan" 
                                           value="<?= isset($_POST['nama']) ? htmlspecialchars($_POST['nama']) : ''; ?>" required>
                                    <div class="invalid-feedback">Nama pelanggan wajib diisi.</div>
                                    <div class="form-text">Masukkan nama lengkap pelanggan tanpa gelar.</div>
                                </div>

                                <div class="col-md-6 form-group mb-3">
                                    <label for="no_hp" class="form-label">Nomor HP <span class="text-danger">*</span></label>
                                    <input type="text" id="no_hp" name="no_hp" class="form-control" 
                                           placeholder="Contoh: 081234567890" 
                                           value="<?= isset($_POST['no_hp']) ? htmlspecialchars($_POST['no_hp']) : ''; ?>" required>
                                    <div class="invalid-feedback">Nomor HP tidak boleh kosong dan harus valid.</div>
                                    <div class="form-text">Format: 08xxxxxxxxxx (minimal 10 digit).</div>
                                </div>
                            </div>

                            <div class="form-group mb-3">
                                <label for="alamat" class="form-label">Alamat Lengkap <span class="text-danger">*</span></label>
                                <textarea id="alamat" name="alamat" rows="4" class="form-control" 
                                          placeholder="Masukkan alamat lengkap pelanggan" required><?= isset($_POST['alamat']) ? htmlspecialchars($_POST['alamat']) : ''; ?></textarea>
                                <div class="invalid-feedback">Alamat wajib diisi.</div>
                                <div class="form-text">Sertakan detail alamat seperti jalan, RT/RW, kelurahan, kecamatan, dan kota.</div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="tambah" class="btn btn-success">
                                <i class="fas fa-save"></i> Simpan Data
                            </button>
                            <a href="pelanggan.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Kembali
                            </a>
                            <button type="reset" class="btn btn-warning">
                                <i class="fas fa-redo"></i> Reset Form
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal - SAMA PERSIS DENGAN editpelanggan.php -->
    <div class="modal fade success-modal" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true" data-bs-backdrop="static">
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
                        Data berhasil ditambahkan
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
        // Validasi form Bootstrap - SAMA PERSIS DENGAN editpelanggan.php
        (() => {
            'use strict';
            const forms = document.querySelectorAll('.needs-validation');
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        })();

        // Filter input nomor HP - SAMA PERSIS DENGAN editpelanggan.php
        document.getElementById('no_hp').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9+]/g, '');
            
            // Validasi panjang nomor HP
            if (this.value.length < 10 && this.value.length > 0) {
                this.setCustomValidity('Nomor HP minimal 10 digit');
            } else {
                this.setCustomValidity('');
            }
        });

        // Validasi real-time untuk nama
        document.getElementById('nama').addEventListener('input', function(e) {
            if (this.value.trim().length < 2 && this.value.length > 0) {
                this.setCustomValidity('Nama minimal 2 karakter');
            } else {
                this.setCustomValidity('');
            }
        });

        // Validasi real-time untuk alamat
        document.getElementById('alamat').addEventListener('input', function(e) {
            if (this.value.trim().length < 10 && this.value.length > 0) {
                this.setCustomValidity('Alamat minimal 10 karakter');
            } else {
                this.setCustomValidity('');
            }
        });

        // Animasi loading ke centang - SAMA PERSIS DENGAN editpelanggan.php
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

        // Tampilkan modal success setelah tambah berhasil - SAMA PERSIS DENGAN editpelanggan.php
        <?php if (isset($show_success_modal) && $show_success_modal): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const successModal = new bootstrap.Modal(document.getElementById('successModal'));
            successModal.show();
            
            // Mulai animasi
            startSuccessAnimation();
            
            // Set timer untuk redirect setelah 3 detik
            setTimeout(function() {
                window.location.href = 'pelanggan.php';
            }, 3000);
        });
        <?php endif; ?>

        // Juga tampilkan modal jika ada session success dari redirect
        <?php if (isset($_SESSION['success'])): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const successModal = new bootstrap.Modal(document.getElementById('successModal'));
            successModal.show();
            
            // Mulai animasi
            startSuccessAnimation();
            
            // Set timer untuk redirect setelah 3.5 detik
            setTimeout(function() {
                window.location.href = 'pelanggan.php';
            }, 3500);
            
            // Hapus session success setelah ditampilkan
            <?php unset($_SESSION['success']); ?>
        });
        <?php endif; ?>

        // Auto-focus pada field nama saat halaman dimuat
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('nama').focus();
        });

        // Reset form confirmation
        document.querySelector('button[type="reset"]').addEventListener('click', function(e) {
            if (!confirm('Apakah Anda yakin ingin mengosongkan semua field?')) {
                e.preventDefault();
            }
        });

        // Character counter untuk textarea
        document.getElementById('alamat').addEventListener('input', function(e) {
            const charCount = this.value.length;
            const minChars = 10;
            
            if (charCount > 0 && charCount < minChars) {
                this.setCustomValidity(`Alamat minimal ${minChars} karakter (${charCount}/${minChars})`);
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>