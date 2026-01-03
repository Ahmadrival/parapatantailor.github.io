<?php
// transaksi/pengeluaran.php
require_once '../config/database.php';
check_login();
check_role(['admin', 'pemilik']);

// Tambah pengeluaran
if (isset($_POST['tambah_pengeluaran'])) {
    $keterangan = clean_input($_POST['keterangan']);
    $jumlah = clean_input($_POST['jumlah']);
    $tanggal = clean_input($_POST['tanggal']);
    $kategori = clean_input($_POST['kategori']);
    
    try {
        $sql = "INSERT INTO data_pengeluaran (keterangan, jumlah, tanggal, kategori) 
                VALUES (?, ?, ?, ?)";
        executeQuery($sql, [$keterangan, $jumlah, $tanggal, $kategori]);
        
        $_SESSION['success'] = "✅ Pengeluaran berhasil ditambahkan";
        log_activity("Menambah pengeluaran: $keterangan");
    } catch (PDOException $e) {
        $_SESSION['error'] = "❌ Gagal menambah pengeluaran: " . $e->getMessage();
    }
    header("Location: pengeluaran.php");
    exit();
}

// Edit pengeluaran
if (isset($_POST['edit_pengeluaran'])) {
    $id_pengeluaran = clean_input($_POST['id_pengeluaran']);
    $keterangan = clean_input($_POST['keterangan']);
    $jumlah = clean_input($_POST['jumlah']);
    $tanggal = clean_input($_POST['tanggal']);
    $kategori = clean_input($_POST['kategori']);
    
    try {
        $sql = "UPDATE data_pengeluaran 
                SET keterangan = ?, jumlah = ?, tanggal = ?, kategori = ?, updated_at = NOW()
                WHERE id_pengeluaran = ?";
        executeQuery($sql, [$keterangan, $jumlah, $tanggal, $kategori, $id_pengeluaran]);
        
        $_SESSION['success'] = "✅ Pengeluaran berhasil diupdate";
        log_activity("Mengedit pengeluaran ID: $id_pengeluaran");
    } catch (PDOException $e) {
        $_SESSION['error'] = "❌ Gagal mengupdate pengeluaran: " . $e->getMessage();
    }
    header("Location: pengeluaran.php");
    exit();
}

// Hapus pengeluaran
if (isset($_GET['hapus_pengeluaran'])) {
    $id_pengeluaran = clean_input($_GET['hapus_pengeluaran']);
    
    try {
        $sql = "DELETE FROM data_pengeluaran WHERE id_pengeluaran = ?";
        executeQuery($sql, [$id_pengeluaran]);
        
        $_SESSION['success'] = "✅ Pengeluaran berhasil dihapus";
        log_activity("Menghapus pengeluaran ID: $id_pengeluaran");
    } catch (PDOException $e) {
        $_SESSION['error'] = "❌ Gagal menghapus pengeluaran: " . $e->getMessage();
    }
    header("Location: pengeluaran.php");
    exit();
}

// Ambil data pengeluaran
try {
    $sql = "SELECT * FROM data_pengeluaran 
            ORDER BY tanggal DESC, created_at DESC";
    $pengeluaran = getAll($sql);
    
    // Hitung total pengeluaran
    $sql_total = "SELECT COALESCE(SUM(jumlah), 0) as total FROM data_pengeluaran";
    $total_pengeluaran = getSingle($sql_total)['total'];
    
    // Hitung per kategori
    $sql_kategori = "SELECT kategori, SUM(jumlah) as total 
                    FROM data_pengeluaran 
                    GROUP BY kategori";
    $pengeluaran_kategori = getAll($sql_kategori);
} catch (PDOException $e) {
    $_SESSION['error'] = "❌ Gagal memuat data pengeluaran: " . $e->getMessage();
    $pengeluaran = [];
    $total_pengeluaran = 0;
    $pengeluaran_kategori = [];
}

$page_title = "Data Pengeluaran";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Pengeluaran - SIM Parapatan Taoilor</title>

    <!-- Bootstrap 5.3.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../includes/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* Styles sama seperti pemasukan.php, dengan warna yang berbeda */
        .stats-card {
            background: linear-gradient(135deg, #ef4444 0%, #f87171 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            text-align: center;
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.3);
        }
        
        .kategori-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .bahan_baku { background: #dcfce7; color: #166534; }
        .operasional { background: #dbeafe; color: #1e40af; }
        .gaji_karyawan { background: #fef3c7; color: #92400e; }
        .listrik_air { background: #e9d5ff; color: #7c3aed; }
        .pemeliharaan { background: #fecaca; color: #dc2626; }
        .lainnya { background: #d1d5db; color: #374151; }
    </style>
</head>

<body>
    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/header.php'; ?>

    <div class="main-content">
        <div class="content-body">
            <div class="container">
                <h2 class="my-3">Data Pengeluaran</h2>

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

                <!-- Stats Card -->
                <div class="stats-card">
                    <div class="stats-number">Rp <?= number_format($total_pengeluaran, 0, ',', '.'); ?></div>
                    <div class="stats-label">Total Pengeluaran</div>
                </div>

                <!-- Ringkasan per Kategori -->
                <?php if (!empty($pengeluaran_kategori)): ?>
                <div class="form-section">
                    <h4><i class="fas fa-chart-pie text-info"></i> Ringkasan per Kategori</h4>
                    <div class="row">
                        <?php foreach ($pengeluaran_kategori as $kategori): ?>
                        <div class="col-md-4 mb-3">
                            <div class="d-flex justify-content-between align-items-center p-2 border rounded">
                                <span class="kategori-badge <?= $kategori['kategori']; ?>">
                                    <?= ucfirst(str_replace('_', ' ', $kategori['kategori'])); ?>
                                </span>
                                <strong>Rp <?= number_format($kategori['total'], 0, ',', '.'); ?></strong>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Form Tambah Pengeluaran -->
                <div class="form-section">
                    <h4><i class="fas fa-plus-circle text-danger"></i> Tambah Pengeluaran Baru</h4>
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Keterangan Pengeluaran</label>
                                <input type="text" name="keterangan" class="form-control" 
                                       placeholder="Contoh: Beli bahan baku, bayar listrik, dll" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Kategori</label>
                                <select name="kategori" class="form-control" required>
                                    <option value="bahan_baku">Bahan Baku</option>
                                    <option value="operasional">Operasional</option>
                                    <option value="gaji_karyawan">Gaji Karyawan</option>
                                    <option value="listrik_air">Listrik & Air</option>
                                    <option value="pemeliharaan">Pemeliharaan</option>
                                    <option value="lainnya">Lainnya</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Jumlah (Rp)</label>
                                <input type="number" name="jumlah" class="form-control" 
                                       min="0" step="1000" placeholder="0" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Tanggal</label>
                                <input type="date" name="tanggal" class="form-control" 
                                       value="<?= date('Y-m-d'); ?>" required>
                            </div>
                            <div class="form-group" style="display: flex; align-items: end;">
                                <button type="submit" name="tambah_pengeluaran" class="btn btn-danger">
                                    <i class="fas fa-plus"></i> Tambah Pengeluaran
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Daftar Pengeluaran -->
                <div class="form-section">
                    <h4><i class="fas fa-list text-primary"></i> Daftar Pengeluaran</h4>
                    
                    <?php if (empty($pengeluaran)): ?>
                        <div class="empty-state">
                            <div><i class="fas fa-receipt"></i></div>
                            <p style="font-size: 0.9rem; margin-bottom: 0.4rem;">Belum ada data pengeluaran</p>
                            <p style="color: #6b7280; font-size: 0.75rem; margin-top: 0.4rem;">
                                Tambahkan pengeluaran menggunakan form di atas
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Tanggal</th>
                                        <th>Keterangan</th>
                                        <th>Kategori</th>
                                        <th>Jumlah</th>
                                        <th>Dibuat</th>
                                        <th style="text-align: center;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $no = 1; ?>
                                    <?php foreach ($pengeluaran as $item): ?>
                                    <tr>
                                        <td><?= $no++; ?></td>
                                        <td><?= date('d/m/Y', strtotime($item['tanggal'])); ?></td>
                                        <td><?= htmlspecialchars($item['keterangan']); ?></td>
                                        <td>
                                            <span class="kategori-badge <?= $item['kategori']; ?>">
                                                <?= ucfirst(str_replace('_', ' ', $item['kategori'])); ?>
                                            </span>
                                        </td>
                                        <td style="font-weight: 600; color: #dc2626;">
                                            Rp <?= number_format($item['jumlah'], 0, ',', '.'); ?>
                                        </td>
                                        <td><?= date('d/m/Y H:i', strtotime($item['created_at'])); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button type="button" 
                                                        class="btn btn-warning btn-sm edit-pengeluaran-btn" 
                                                        title="Edit Pengeluaran"
                                                        data-id="<?= $item['id_pengeluaran']; ?>"
                                                        data-keterangan="<?= htmlspecialchars($item['keterangan']); ?>"
                                                        data-jumlah="<?= $item['jumlah']; ?>"
                                                        data-tanggal="<?= $item['tanggal']; ?>"
                                                        data-kategori="<?= $item['kategori']; ?>">
                                                   <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" 
                                                        class="btn btn-danger btn-sm delete-pengeluaran-btn" 
                                                        title="Hapus Pengeluaran"
                                                        data-id="<?= $item['id_pengeluaran']; ?>"
                                                        data-keterangan="<?= htmlspecialchars($item['keterangan']); ?>">
                                                   <i class="fas fa-trash"></i> 
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <?php include '../includes/footer.php'; ?>
    </div>

    <!-- Modal Edit Pengeluaran -->
    <div class="modal fade" id="editPengeluaranModal" tabindex="-1" aria-labelledby="editPengeluaranModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editPengeluaranModalLabel">
                        <i class="fas fa-edit"></i> Edit Pengeluaran
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="id_pengeluaran" id="edit_id_pengeluaran">
                        
                        <div class="form-group">
                            <label class="form-label">Keterangan Pengeluaran</label>
                            <input type="text" name="keterangan" class="form-control" 
                                   id="edit_keterangan" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Kategori</label>
                            <select name="kategori" class="form-control" id="edit_kategori" required>
                                <option value="bahan_baku">Bahan Baku</option>
                                <option value="operasional">Operasional</option>
                                <option value="gaji_karyawan">Gaji Karyawan</option>
                                <option value="listrik_air">Listrik & Air</option>
                                <option value="pemeliharaan">Pemeliharaan</option>
                                <option value="lainnya">Lainnya</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Jumlah (Rp)</label>
                            <input type="number" name="jumlah" class="form-control" 
                                   id="edit_jumlah" min="0" step="1000" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Tanggal</label>
                            <input type="date" name="tanggal" class="form-control" 
                                   id="edit_tanggal" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Batal
                        </button>
                        <button type="submit" name="edit_pengeluaran" class="btn btn-success">
                            <i class="fas fa-save"></i> Update Pengeluaran
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Konfirmasi Hapus -->
    <div class="modal fade" id="deletePengeluaranModal" tabindex="-1" aria-labelledby="deletePengeluaranModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deletePengeluaranModalLabel">
                        <i class="fas fa-exclamation-triangle text-danger"></i> Konfirmasi Hapus
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Apakah Anda yakin ingin menghapus pengeluaran:</p>
                    <p><strong id="delete_keterangan"></strong>?</p>
                    <p class="text-muted small">Tindakan ini tidak dapat dibatalkan.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <a href="#" class="btn btn-danger" id="confirmDeletePengeluaran">
                        <i class="fas fa-trash"></i> Ya, Hapus
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Modal Edit Pengeluaran
            const editButtons = document.querySelectorAll('.edit-pengeluaran-btn');
            const editModal = new bootstrap.Modal(document.getElementById('editPengeluaranModal'));
            
            editButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const keterangan = this.getAttribute('data-keterangan');
                    const jumlah = this.getAttribute('data-jumlah');
                    const tanggal = this.getAttribute('data-tanggal');
                    const kategori = this.getAttribute('data-kategori');
                    
                    document.getElementById('edit_id_pengeluaran').value = id;
                    document.getElementById('edit_keterangan').value = keterangan;
                    document.getElementById('edit_jumlah').value = jumlah;
                    document.getElementById('edit_tanggal').value = tanggal;
                    document.getElementById('edit_kategori').value = kategori;
                    
                    editModal.show();
                });
            });

            // Modal Hapus Pengeluaran
            const deleteButtons = document.querySelectorAll('.delete-pengeluaran-btn');
            const deleteModal = new bootstrap.Modal(document.getElementById('deletePengeluaranModal'));
            
            deleteButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const keterangan = this.getAttribute('data-keterangan');
                    
                    document.getElementById('delete_keterangan').textContent = keterangan;
                    document.getElementById('confirmDeletePengeluaran').href = `pengeluaran.php?hapus_pengeluaran=${id}`;
                    
                    deleteModal.show();
                });
            });
        });
    </script>
</body>
</html>