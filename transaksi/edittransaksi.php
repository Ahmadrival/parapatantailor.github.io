<?php
// transaksi/edittransaksi.php
require_once '../config/database.php';
check_login();
check_role(['admin', 'pemilik']);

$id_transaksi = isset($_GET['edit']) ? clean_input($_GET['edit']) : '';

if (empty($id_transaksi)) {
    $_SESSION['error'] = "❌ ID Transaksi tidak valid";
    header("Location: transaksi.php");
    exit();
}

// Ambil data transaksi yang akan diedit
try {
    $sql = "SELECT t.*, p.jenis_pakaian, p.bahan, p.total_harga, p.sisa_bayar, 
                   p.jumlah_bayar as total_bayar_pesanan, p.tgl_pesanan,
                   pel.nama AS nama_pelanggan
            FROM data_transaksi t
            LEFT JOIN data_pesanan p ON t.id_pesanan = p.id_pesanan
            LEFT JOIN data_pelanggan pel ON p.id_pelanggan = pel.id_pelanggan
            WHERE t.id_transaksi = ?";
    $transaksi = getSingle($sql, [$id_transaksi]);
    
    if (!$transaksi) {
        $_SESSION['error'] = "❌ Transaksi tidak ditemukan";
        header("Location: transaksi.php");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "❌ Gagal memuat data transaksi: " . $e->getMessage();
    header("Location: transaksi.php");
    exit();
}

// Update transaksi
if (isset($_POST['update_transaksi'])) {
    $jumlah_bayar = clean_input($_POST['jumlah_bayar']);
    $metode_bayar = clean_input($_POST['metode_bayar']);
    $keterangan = clean_input($_POST['keterangan']);
    $status_pesanan = clean_input($_POST['status_pesanan']);
    
    try {
        // Mulai transaction
        $pdo->beginTransaction();
        
        // Dapatkan data lama
        $old_jumlah_bayar = $transaksi['jumlah_bayar'];
        $id_pesanan = $transaksi['id_pesanan'];
        
        // 1. Update data transaksi
        $sql_update_transaksi = "UPDATE data_transaksi 
                                SET jumlah_bayar = ?, metode_bayar = ?, keterangan = ?, status_pesanan = ?
                                WHERE id_transaksi = ?";
        executeQuery($sql_update_transaksi, [$jumlah_bayar, $metode_bayar, $keterangan, $status_pesanan, $id_transaksi]);
        
        // 2. Hitung selisih pembayaran
        $selisih_bayar = $jumlah_bayar - $old_jumlah_bayar;
        
        // 3. Update data pesanan
        if ($selisih_bayar != 0) {
            $sql_update_pesanan = "UPDATE data_pesanan 
                                  SET jumlah_bayar = jumlah_bayar + ?, 
                                      sisa_bayar = total_harga - (jumlah_bayar + ?)
                                  WHERE id_pesanan = ?";
            executeQuery($sql_update_pesanan, [$selisih_bayar, $selisih_bayar, $id_pesanan]);
            
            // 4. Update status pesanan berdasarkan pembayaran
            $sql_check_pesanan = "SELECT total_harga, jumlah_bayar FROM data_pesanan WHERE id_pesanan = ?";
            $data_pesanan = getSingle($sql_check_pesanan, [$id_pesanan]);
            
            $total_bayar = $data_pesanan['jumlah_bayar'] ?? 0;
            $total_harga = $data_pesanan['total_harga'] ?? 0;
            
            if ($total_bayar >= $total_harga) {
                $sql_update_status = "UPDATE data_pesanan SET status_pesanan = 'selesai' WHERE id_pesanan = ?";
                executeQuery($sql_update_status, [$id_pesanan]);
            } elseif ($total_bayar > 0 && $total_bayar < $total_harga) {
                $sql_update_status = "UPDATE data_pesanan SET status_pesanan = 'dalam_proses' WHERE id_pesanan = ?";
                executeQuery($sql_update_status, [$id_pesanan]);
            } else {
                $sql_update_status = "UPDATE data_pesanan SET status_pesanan = 'belum' WHERE id_pesanan = ?";
                executeQuery($sql_update_status, [$id_pesanan]);
            }
        }
        
        // Commit transaction
        $pdo->commit();
        
        $_SESSION['success'] = "✅ Transaksi berhasil diupdate";
        log_activity("Mengupdate transaksi ID: $id_transaksi");
        header("Location: transaksi.php");
        exit();
    } catch (PDOException $e) {
        // Rollback transaction jika ada error
        $pdo->rollBack();
        $_SESSION['error'] = "❌ Gagal mengupdate transaksi: " . $e->getMessage();
        header("Location: edittransaksi.php?edit=$id_transaksi");
        exit();
    }
}

$page_title = "Edit Transaksi";
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Transaksi - Sistem Tailor</title>
 <link rel="shortcut icon" href="../assets/images/logoterakhir.png" type="image/x-icon" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../includes/sidebar.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
.main-content {
    padding: 20px;
}

.card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

.form-section {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    margin-bottom: 2rem;
}

.info-card {
    background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
    border: 1px solid #bae6fd;
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.info-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.5rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #e0f2fe;
}

.info-item:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.info-label {
    font-weight: 600;
    color: #0c4a6e;
}

.info-value {
    font-weight: 600;
    color: #059669;
}

.form-label {
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.5rem;
}

.btn-back {
    background: #6b7280;
    border: none;
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s;
}

.btn-back:hover {
    background: #4b5563;
    color: white;
    transform: translateY(-1px);
}
</style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/header.php'; ?>

    <div class="main-content">
        <div class="content-body">
            <div class="container">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Edit Transaksi</h2>
                    <a href="transaksi.php" class="btn-back">
                        <i class="fas fa-arrow-left"></i> Kembali ke Data Transaksi
                    </a>
                </div>

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

                <div class="card">
                    <div class="card-body">
                        <!-- Informasi Pesanan -->
                        <div class="info-card">
                            <h5 class="mb-3"><i class="fas fa-info-circle"></i> Informasi Pesanan</h5>
                            <div class="info-item">
                                <span class="info-label">ID Transaksi:</span>
                                <span class="info-value"><?= htmlspecialchars($transaksi['id_transaksi']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Pelanggan:</span>
                                <span class="info-value"><?= htmlspecialchars($transaksi['nama_pelanggan'] ?? '-'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Jenis Pakaian:</span>
                                <span class="info-value"><?= htmlspecialchars($transaksi['jenis_pakaian'] ?? '-'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Bahan:</span>
                                <span class="info-value"><?= htmlspecialchars($transaksi['bahan'] ?? '-'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Total Harga:</span>
                                <span class="info-value">Rp <?= number_format($transaksi['total_harga'], 0, ',', '.'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Total Sudah Dibayar:</span>
                                <span class="info-value">Rp <?= number_format($transaksi['total_bayar_pesanan'], 0, ',', '.'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Sisa Bayar:</span>
                                <span class="info-value">Rp <?= number_format($transaksi['sisa_bayar'], 0, ',', '.'); ?></span>
                            </div>
                        </div>

                        <!-- Form Edit Transaksi -->
                        <form method="POST" class="form-section">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Jumlah Bayar</label>
                                        <input type="number" name="jumlah_bayar" class="form-control" required 
                                               value="<?= htmlspecialchars($transaksi['jumlah_bayar']); ?>"
                                               min="1" max="<?= $transaksi['sisa_bayar'] + $transaksi['jumlah_bayar']; ?>"
                                               placeholder="Masukkan jumlah bayar">
                                        <small class="form-text text-muted">
                                            Maksimal: Rp <?= number_format($transaksi['sisa_bayar'] + $transaksi['jumlah_bayar'], 0, ',', '.'); ?>
                                        </small>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Metode Pembayaran</label>
                                        <select name="metode_bayar" class="form-control" required>
                                            <option value="Tunai" <?= $transaksi['metode_bayar'] == 'Tunai' ? 'selected' : ''; ?>>Tunai</option>
                                            <option value="Transfer" <?= $transaksi['metode_bayar'] == 'Transfer' ? 'selected' : ''; ?>>Transfer Bank</option>
                                            <option value="QRIS" <?= $transaksi['metode_bayar'] == 'QRIS' ? 'selected' : ''; ?>>QRIS</option>
                                            <option value="Kredit" <?= $transaksi['metode_bayar'] == 'Kredit' ? 'selected' : ''; ?>>Kartu Kredit</option>
                                            <option value="Debit" <?= $transaksi['metode_bayar'] == 'Debit' ? 'selected' : ''; ?>>Kartu Debit</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Status Transaksi</label>
                                        <select name="status_pesanan" class="form-control" required>
                                            <option value="pending" <?= $transaksi['status_pesanan'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="lunas" <?= $transaksi['status_pesanan'] == 'lunas' ? 'selected' : ''; ?>>Lunas</option>
                                            <option value="gagal" <?= $transaksi['status_pesanan'] == 'gagal' ? 'selected' : ''; ?>>Gagal</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Keterangan (Opsional)</label>
                                <textarea name="keterangan" class="form-control" rows="3" 
                                          placeholder="Tambahkan keterangan transaksi..."><?= htmlspecialchars($transaksi['keterangan'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" name="update_transaksi" class="btn btn-success">
                                    <i class="fas fa-save"></i> Update Transaksi
                                </button>
                                <a href="transaksi.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Batal
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <?php include '../includes/footer.php'; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validasi jumlah bayar
        document.addEventListener('DOMContentLoaded', function() {
            const jumlahBayarInput = document.querySelector('input[name="jumlah_bayar"]');
            const maxAmount = <?= $transaksi['sisa_bayar'] + $transaksi['jumlah_bayar']; ?>;
            
            jumlahBayarInput.addEventListener('change', function() {
                if (parseInt(this.value) > maxAmount) {
                    alert('Jumlah bayar tidak boleh melebihi sisa bayar + jumlah bayar saat ini');
                    this.value = maxAmount;
                }
            });
        });
    </script>
</body>
</html>