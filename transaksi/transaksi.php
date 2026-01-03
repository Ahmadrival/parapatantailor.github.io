<?php
// transaksi/transaksi.php
require_once '../config/database.php';
check_login();
check_role(['admin', 'pemilik','pegawai']);

// ============================================================
// FUNGSI SYNC OTOMATIS - DIPANGGIL SETIAP KALI HALAMAN DIMUAT
// ============================================================
function syncAutoTransactions() {
    global $pdo;
    
    try {
        // Cari semua pesanan yang sudah ada pembayaran tapi belum ada transaksi
        $sql_pesanan_untuk_sync = "SELECT p.id_pesanan, p.jumlah_bayar, p.total_harga, p.metode_pembayaran 
                                  FROM data_pesanan p 
                                  WHERE p.jumlah_bayar > 0 
                                  AND NOT EXISTS (
                                      SELECT 1 FROM data_transaksi t 
                                      WHERE t.id_pesanan = p.id_pesanan
                                  )";
        $pesanan_untuk_sync = getAll($sql_pesanan_untuk_sync);
        
        $count_synced = 0;
        $count_errors = 0;
        
        foreach ($pesanan_untuk_sync as $pesanan) {
            try {
                // Tentukan status pembayaran
                $status_pembayaran = getPaymentStatus($pesanan['jumlah_bayar'], $pesanan['total_harga']);
                
                // Insert transaksi otomatis DENGAN metode_pembayaran dari data_pesanan
                $sql_transaksi = "INSERT INTO data_transaksi 
                                 (id_pesanan, tgl_transaksi, jumlah_bayar, metode_bayar, keterangan, status_pembayaran, created_at) 
                                 VALUES (?, NOW(), ?, ?, 'Pembayaran otomatis dari sistem', ?, NOW())";
                
                executeQuery($sql_transaksi, [
                    $pesanan['id_pesanan'], 
                    $pesanan['jumlah_bayar'],
                    $pesanan['metode_pembayaran'], // Gunakan metode_pembayaran dari data_pesanan
                    $status_pembayaran
                ]);
                
                $count_synced++;
                log_activity("Auto-sync transaksi untuk pesanan ID: " . $pesanan['id_pesanan']);
                
            } catch (Exception $e) {
                error_log("Gagal sync transaksi untuk pesanan {$pesanan['id_pesanan']}: " . $e->getMessage());
                $count_errors++;
            }
        }
        
        // Simpan hasil sync ke session untuk ditampilkan nanti
        if ($count_synced > 0) {
            $_SESSION['sync_info'] = "✅ Berhasil sync otomatis $count_synced transaksi dari pesanan";
        }
        
        if ($count_errors > 0) {
            $_SESSION['sync_warning'] = "⚠️ Terjadi $count_errors error saat sync, cek log untuk detail";
        }
        
        return ['success' => $count_synced, 'errors' => $count_errors];
        
    } catch (PDOException $e) {
        error_log("Gagal melakukan sync otomatis: " . $e->getMessage());
        return ['success' => 0, 'errors' => 1];
    }
}

// ============================================================
// JALANKAN SYNC OTOMATIS SETIAP KALI HALAMAN DIMUAT
// ============================================================
// Hanya jalankan jika bukan request sync manual
if (!isset($_GET['sync']) && !isset($_POST['tambah_transaksi']) && !isset($_POST['update_transaksi'])) {
    syncAutoTransactions();
}

// Fungsi untuk membuat transaksi otomatis dari pesanan (untuk manual sync)
function createAutoTransaction($id_pesanan) {
    global $pdo;
    
    try {
        // Cek apakah sudah ada transaksi untuk pesanan ini
        $sql_check = "SELECT COUNT(*) FROM data_transaksi WHERE id_pesanan = ?";
        $existing_count = executeQuery($sql_check, [$id_pesanan])->fetchColumn();
        
        if ($existing_count == 0) {
            // Ambil data pesanan termasuk metode_pembayaran
            $sql_pesanan = "SELECT p.*, pel.nama AS nama_pelanggan 
                           FROM data_pesanan p 
                           LEFT JOIN data_pelanggan pel ON p.id_pelanggan = pel.id_pelanggan 
                           WHERE p.id_pesanan = ?";
            $pesanan = getSingle($sql_pesanan, [$id_pesanan]);
            
            if ($pesanan && $pesanan['jumlah_bayar'] > 0) {
                // Tentukan status pembayaran berdasarkan jumlah yang sudah dibayar
                $status_pembayaran = getPaymentStatus($pesanan['jumlah_bayar'], $pesanan['total_harga']);
                
                // Insert transaksi otomatis untuk pembayaran yang sudah ada
                // Gunakan metode_pembayaran dari data_pesanan
                $sql_transaksi = "INSERT INTO data_transaksi 
                                 (id_pesanan, tgl_transaksi, jumlah_bayar, metode_bayar, keterangan, status_pembayaran, created_at) 
                                 VALUES (?, NOW(), ?, ?, 'Pembayaran otomatis dari sistem', ?, NOW())";
                executeQuery($sql_transaksi, [
                    $id_pesanan, 
                    $pesanan['jumlah_bayar'],
                    $pesanan['metode_pembayaran'], // Gunakan metode_pembayaran dari data_pesanan
                    $status_pembayaran
                ]);
                
                return true;
            }
        }
        return false;
    } catch (PDOException $e) {
        error_log("Gagal membuat transaksi otomatis: " . $e->getMessage());
        return false;
    }
}

// Hapus transaksi
if (isset($_GET['hapus'])) {
    $id = clean_input($_GET['hapus']);
    try {
        // Mulai transaction
        $pdo->beginTransaction();
        
        // 1. Dapatkan data transaksi untuk update pesanan
        $sql_get_transaksi = "SELECT id_pesanan, jumlah_bayar FROM data_transaksi WHERE id_transaksi = ?";
        $transaksi_data = getSingle($sql_get_transaksi, [$id]);
        $id_pesanan = $transaksi_data['id_pesanan'] ?? null;
        $jumlah_bayar = $transaksi_data['jumlah_bayar'] ?? 0;
        
        // 2. Hapus data transaksi
        $sql_delete_transaksi = "DELETE FROM data_transaksi WHERE id_transaksi = ?";
        executeQuery($sql_delete_transaksi, [$id]);
        
        // 3. Update data pesanan - kurangi jumlah_bayar dan update sisa_bayar
        if ($id_pesanan) {
            // Hitung ulang total bayar dari transaksi yang tersisa
            $sql_total_bayar = "SELECT COALESCE(SUM(jumlah_bayar), 0) as total_bayar 
                               FROM data_transaksi 
                               WHERE id_pesanan = ?";
            $total_bayar_baru = executeQuery($sql_total_bayar, [$id_pesanan])->fetchColumn();
            
            // Update data pesanan berdasarkan total bayar yang baru
            $sql_update_pesanan = "UPDATE data_pesanan 
                                  SET jumlah_bayar = ?,
                                      sisa_bayar = GREATEST(0, total_harga - ?)
                                  WHERE id_pesanan = ?";
            executeQuery($sql_update_pesanan, [$total_bayar_baru, $total_bayar_baru, $id_pesanan]);
            
            // Update status pembayaran pesanan
            updatePesananPaymentStatus($id_pesanan);
        }
        
        // Commit transaction
        $pdo->commit();
        
        $_SESSION['success'] = "✅ Transaksi berhasil dihapus";
        log_activity("Menghapus transaksi ID: $id");
    } catch (PDOException $e) {
        // Rollback transaction jika ada error
        $pdo->rollBack();
        $_SESSION['error'] = "❌ Gagal menghapus transaksi: " . $e->getMessage();
    }
    header("Location: transaksi.php");
    exit();
}

// Hapus pengeluaran
if (isset($_GET['hapus_pengeluaran'])) {
    $id = clean_input($_GET['hapus_pengeluaran']);
    try {
        $sql_delete = "DELETE FROM data_pengeluaran WHERE id_pengeluaran = ?";
        executeQuery($sql_delete, [$id]);
        
        $_SESSION['success'] = "✅ Pengeluaran berhasil dihapus";
        log_activity("Menghapus pengeluaran ID: $id");
    } catch (PDOException $e) {
        $_SESSION['error'] = "❌ Gagal menghapus pengeluaran: " . $e->getMessage();
    }
    header("Location: transaksi.php?tab=pengeluaran");
    exit();
}

// Update transaksi pembayaran
if (isset($_POST['update_transaksi'])) {
    $id_transaksi = clean_input($_POST['id_transaksi']);
    $jumlah_bayar = clean_input($_POST['jumlah_bayar']);
    $metode_bayar = clean_input($_POST['metode_bayar']);
    $keterangan = clean_input($_POST['keterangan']);
    
    try {
        // Mulai transaction
        $pdo->beginTransaction();
        
        // 1. Dapatkan data transaksi lama dan data pesanan
        $sql_get_data = "SELECT t.id_pesanan, t.jumlah_bayar as old_jumlah, p.total_harga, p.jumlah_bayar as total_bayar_pesanan
                        FROM data_transaksi t
                        JOIN data_pesanan p ON t.id_pesanan = p.id_pesanan
                        WHERE t.id_transaksi = ?";
        $old_data = getSingle($sql_get_data, [$id_transaksi]);
        $id_pesanan = $old_data['id_pesanan'];
        $old_jumlah = $old_data['old_jumlah'];
        $total_harga = $old_data['total_harga'];
        $total_bayar_pesanan = $old_data['total_bayar_pesanan'];
        
        // 2. Hitung total bayar baru untuk pesanan
        $total_bayar_baru = ($total_bayar_pesanan - $old_jumlah) + $jumlah_bayar;
        
        // 3. Tentukan status pembayaran untuk transaksi ini
        $status_pembayaran = getPaymentStatus($jumlah_bayar, $total_harga);
        
        // 4. Update transaksi
        $sql_update_transaksi = "UPDATE data_transaksi 
                                SET jumlah_bayar = ?, metode_bayar = ?, keterangan = ?, status_pembayaran = ?
                                WHERE id_transaksi = ?";
        executeQuery($sql_update_transaksi, [$jumlah_bayar, $metode_bayar, $keterangan, $status_pembayaran, $id_transaksi]);
        
        // 5. Update data pesanan
        $sql_update_pesanan = "UPDATE data_pesanan 
                              SET jumlah_bayar = ?,
                                  sisa_bayar = GREATEST(0, total_harga - ?)
                              WHERE id_pesanan = ?";
        executeQuery($sql_update_pesanan, [$total_bayar_baru, $total_bayar_baru, $id_pesanan]);
        
        // 6. Update status pembayaran semua transaksi untuk pesanan ini
        updateAllTransactionStatus($id_pesanan);
        
        // Commit transaction
        $pdo->commit();
        
        $_SESSION['success'] = "✅ Transaksi berhasil diupdate";
        log_activity("Mengupdate transaksi ID: $id_transaksi");
    } catch (PDOException $e) {
        // Rollback transaction jika ada error
        $pdo->rollBack();
        $_SESSION['error'] = "❌ Gagal mengupdate transaksi: " . $e->getMessage();
    }
    header("Location: transaksi.php");
    exit();
}

// TAMBAH TRANSAKSI BARU - SELALU BUAT TRANSAKSI BARU
if (isset($_POST['tambah_transaksi'])) {
    $id_pesanan = clean_input($_POST['id_pesanan']);
    $jumlah_bayar = clean_input($_POST['jumlah_bayar']);
    $metode_bayar = clean_input($_POST['metode_bayar']);
    $keterangan = clean_input($_POST['keterangan']);
    
    try {
        // Mulai transaction
        $pdo->beginTransaction();
        
        // 1. Dapatkan data pesanan untuk validasi
        $sql_pesanan = "SELECT total_harga, jumlah_bayar, sisa_bayar, metode_pembayaran FROM data_pesanan WHERE id_pesanan = ?";
        $pesanan_data = getSingle($sql_pesanan, [$id_pesanan]);
        
        if (!$pesanan_data) {
            throw new Exception("Pesanan tidak ditemukan");
        }
        
        $total_harga = $pesanan_data['total_harga'];
        $current_bayar = $pesanan_data['jumlah_bayar'];
        $sisa_bayar = $pesanan_data['sisa_bayar'];
        $metode_pesanan = $pesanan_data['metode_pembayaran'];
        
        // 2. Validasi jumlah bayar tidak melebihi sisa bayar
        if ($jumlah_bayar > $sisa_bayar) {
            throw new Exception("Jumlah bayar (Rp " . number_format($jumlah_bayar, 0, ',', '.') . ") melebihi sisa bayar (Rp " . number_format($sisa_bayar, 0, ',', '.') . ")");
        }
        
        // 3. Tentukan status pembayaran untuk transaksi ini
        $status_pembayaran = getPaymentStatus($jumlah_bayar, $total_harga);
        
        // 4. Insert transaksi BARU (selalu buat transaksi baru)
        // Gunakan metode_bayar dari input form, jika kosong gunakan dari pesanan
        $metode_bayar_final = !empty($metode_bayar) ? $metode_bayar : $metode_pesanan;
        
        $sql_transaksi = "INSERT INTO data_transaksi 
                         (id_pesanan, tgl_transaksi, jumlah_bayar, metode_bayar, keterangan, status_pembayaran, created_at) 
                         VALUES (?, NOW(), ?, ?, ?, ?, NOW())";
        executeQuery($sql_transaksi, [$id_pesanan, $jumlah_bayar, $metode_bayar_final, $keterangan, $status_pembayaran]);
        
        // 5. Hitung total bayar baru (current + baru)
        $total_bayar_baru = $current_bayar + $jumlah_bayar;
        
        // 6. Update data pesanan
        $sql_update_pesanan = "UPDATE data_pesanan 
                              SET jumlah_bayar = ?,
                                  sisa_bayar = GREATEST(0, total_harga - ?)
                              WHERE id_pesanan = ?";
        executeQuery($sql_update_pesanan, [$total_bayar_baru, $total_bayar_baru, $id_pesanan]);
        
        // 7. Update status pembayaran semua transaksi untuk pesanan ini
        updateAllTransactionStatus($id_pesanan);
        
        // Commit transaction
        $pdo->commit();
        
        $_SESSION['success'] = "✅ Pembayaran berhasil ditambahkan";
        log_activity("Menambah pembayaran untuk pesanan ID: $id_pesanan - Rp " . number_format($jumlah_bayar, 0, ',', '.'));
    } catch (Exception $e) {
        // Rollback transaction jika ada error
        $pdo->rollBack();
        $_SESSION['error'] = "❌ Gagal menambah pembayaran: " . $e->getMessage();
    }
    header("Location: transaksi.php");
    exit();
}

// Tambah transaksi pengeluaran
if (isset($_POST['tambah_pengeluaran'])) {
    $jumlah_pengeluaran = clean_input($_POST['jumlah_pengeluaran']);
    $kategori_pengeluaran = clean_input($_POST['kategori_pengeluaran']);
    $keterangan_pengeluaran = clean_input($_POST['keterangan_pengeluaran']);
    $tgl_pengeluaran = clean_input($_POST['tgl_pengeluaran']);
    
    try {
        // Insert ke tabel pengeluaran
        $sql_pengeluaran = "INSERT INTO data_pengeluaran 
                           (kategori_pengeluaran, jumlah_pengeluaran, keterangan, tgl_pengeluaran, created_at) 
                           VALUES (?, ?, ?, ?, NOW())";
        executeQuery($sql_pengeluaran, [$kategori_pengeluaran, $jumlah_pengeluaran, $keterangan_pengeluaran, $tgl_pengeluaran]);
        
        $_SESSION['success'] = "✅ Pengeluaran berhasil dicatat";
        log_activity("Menambah pengeluaran: $kategori_pengeluaran - Rp " . number_format($jumlah_pengeluaran, 0, ',', '.'));
    } catch (PDOException $e) {
        $_SESSION['error'] = "❌ Gagal mencatat pengeluaran: " . $e->getMessage();
    }
    header("Location: transaksi.php?tab=pengeluaran");
    exit();
}

// FUNGSI BARU: Update status semua transaksi untuk sebuah pesanan
function updateAllTransactionStatus($id_pesanan) {
    global $pdo;
    
    // Dapatkan data pesanan terbaru
    $sql_pesanan = "SELECT total_harga, jumlah_bayar FROM data_pesanan WHERE id_pesanan = ?";
    $data_pesanan = getSingle($sql_pesanan, [$id_pesanan]);
    
    $total_bayar = $data_pesanan['jumlah_bayar'] ?? 0;
    $total_harga = $data_pesanan['total_harga'] ?? 0;
    
    // Tentukan status keseluruhan
    $overall_status = getPaymentStatus($total_bayar, $total_harga);
    
    // Update semua transaksi untuk pesanan ini dengan status yang sama
    $sql_update = "UPDATE data_transaksi SET status_pembayaran = ? WHERE id_pesanan = ?";
    executeQuery($sql_update, [$overall_status, $id_pesanan]);
}

// Fungsi untuk menentukan status pembayaran (DIUBAH: menghapus status cicilan)
function getPaymentStatus($jumlah_bayar, $total_harga) {
    if ($jumlah_bayar == 0) {
        return 'belum_bayar';
    } elseif ($jumlah_bayar >= $total_harga) {
        return 'lunas';
    } elseif ($jumlah_bayar > 0 && $jumlah_bayar < $total_harga) {
        // Hanya ada dua status: DP atau Lunas
        // Semua pembayaran yang belum lunas dianggap DP
        return 'dp';
    }
    return 'belum_bayar';
}

// Fungsi untuk update status pembayaran pesanan
function updatePesananPaymentStatus($id_pesanan) {
    global $pdo;
    
    // Dapatkan data pesanan terbaru
    $sql_pesanan = "SELECT total_harga, jumlah_bayar FROM data_pesanan WHERE id_pesanan = ?";
    $data_pesanan = getSingle($sql_pesanan, [$id_pesanan]);
    
    $total_bayar = $data_pesanan['jumlah_bayar'] ?? 0;
    $total_harga = $data_pesanan['total_harga'] ?? 0;
    
    // Update status pembayaran di semua transaksi
    updateAllTransactionStatus($id_pesanan);
}

// Konfigurasi pagination
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Filter pencarian dan tanggal
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$filter_tanggal = isset($_GET['tanggal']) ? clean_input($_GET['tanggal']) : '';
$filter_status = isset($_GET['status']) ? clean_input($_GET['status']) : '';
$filter_bulan = isset($_GET['bulan']) ? clean_input($_GET['bulan']) : '';
$current_tab = isset($_GET['tab']) ? clean_input($_GET['tab']) : 'transaksi';

// Query dasar untuk transaksi
$sql_where = "";
$params = [];
$where_conditions = [];

if (!empty($search)) {
    $where_conditions[] = "(pel.nama LIKE ? OR p.jenis_pakaian LIKE ? OR p.bahan LIKE ? OR t.metode_bayar LIKE ? OR t.keterangan LIKE ? OR t.id_transaksi LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($filter_tanggal)) {
    $where_conditions[] = "DATE(t.created_at) = ?";
    $params[] = $filter_tanggal;
}

if (!empty($filter_status)) {
    $where_conditions[] = "t.status_pembayaran = ?";
    $params[] = $filter_status;
}

if (!empty($filter_bulan)) {
    $where_conditions[] = "DATE_FORMAT(t.created_at, '%Y-%m') = ?";
    $params[] = $filter_bulan;
}

if (count($where_conditions) > 0) {
    $sql_where = "WHERE " . implode(" AND ", $where_conditions);
}

// Sync transaksi manual - PERBAIKAN: Sync semua pesanan yang punya pembayaran
if (isset($_GET['sync'])) {
    try {
        $sql_pesanan_untuk_sync = "SELECT p.id_pesanan 
                                  FROM data_pesanan p 
                                  WHERE p.jumlah_bayar > 0 
                                  AND NOT EXISTS (
                                      SELECT 1 FROM data_transaksi t 
                                      WHERE t.id_pesanan = p.id_pesanan
                                  )";
        $pesanan_untuk_sync = getAll($sql_pesanan_untuk_sync);
        
        $count_synced = 0;
        foreach ($pesanan_untuk_sync as $pesanan) {
            if (createAutoTransaction($pesanan['id_pesanan'])) {
                $count_synced++;
            }
        }
        
        if ($count_synced > 0) {
            $_SESSION['success'] = "✅ Berhasil sync $count_synced transaksi dari pesanan";
        } else {
            $_SESSION['info'] = "ℹ️ Tidak ada transaksi baru yang perlu disync";
        }
        
        header("Location: transaksi.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "❌ Gagal sync transaksi: " . $e->getMessage();
        header("Location: transaksi.php");
        exit();
    }
}

// Ambil data pesanan untuk dropdown tambah transaksi (hanya yang belum lunas)
try {
    $sql_pesanan = "SELECT p.id_pesanan, p.jenis_pakaian, p.total_harga, p.jumlah_bayar, p.sisa_bayar, 
                           p.metode_pembayaran, pel.nama AS nama_pelanggan
                    FROM data_pesanan p
                    LEFT JOIN data_pelanggan pel ON p.id_pelanggan = pel.id_pelanggan
                    WHERE p.sisa_bayar > 0
                    ORDER BY p.tgl_pesanan DESC";
    $pesanan_list = getAll($sql_pesanan);
    
    // Untuk setiap pesanan, ambil data items-nya (untuk dropdown)
    foreach ($pesanan_list as &$order) {
        $id_pesanan = $order['id_pesanan'];
        
        // Ambil items pesanan dari tabel pesanan_items
        try {
            $sql_items = "SELECT * FROM pesanan_items WHERE id_pesanan = ? ORDER BY id_item ASC";
            $items = getAll($sql_items, [$id_pesanan]);
            
            if (!empty($items)) {
                $order['items'] = $items;
                $order['total_items'] = count($items);
                
                // Hitung total kuantitas
                $total_kuantitas = 0;
                $item_names = [];
                foreach ($items as $item) {
                    $total_kuantitas += ($item['jumlah'] ?? 1);
                    $item_names[] = $item['jenis_pakaian'];
                }
                $order['item_names'] = $item_names;
            } else {
                // Fallback untuk data lama
                $order['items'] = [];
                $order['total_items'] = 1;
                $order['item_names'] = [$order['jenis_pakaian'] ?? 'Pakaian'];
            }
        } catch (PDOException $e) {
            $order['items'] = [];
            $order['total_items'] = 1;
            $order['item_names'] = [$order['jenis_pakaian'] ?? 'Pakaian'];
        }
    }
    unset($order); // Hapus reference terakhir
    
} catch (PDOException $e) {
    $pesanan_list = [];
}

// Hitung total data transaksi
try {
    $sql_count = "SELECT COUNT(*) FROM data_transaksi t 
                  LEFT JOIN data_pesanan p ON t.id_pesanan = p.id_pesanan
                  LEFT JOIN data_pelanggan pel ON p.id_pelanggan = pel.id_pelanggan
                  $sql_where";
    $stmt = executeQuery($sql_count, $params);
    $total_transaksi = $stmt->fetchColumn();
    $total_pages = ceil($total_transaksi / $limit);
} catch (PDOException $e) {
    $total_transaksi = 0;
    $total_pages = 1;
}

// PERBAIKAN DI SINI: Ambil data transaksi dengan metode_bayar dari transaksi, 
// tapi gunakan metode_pembayaran dari pesanan untuk transaksi yang dibuat otomatis
try {
    $sql = "SELECT t.*, p.jenis_pakaian, p.bahan, p.catatan, p.total_harga, p.status_pesanan as status_pesanan_utama, 
                   p.jumlah_bayar as total_bayar_pesanan, p.sisa_bayar, p.tgl_pesanan, p.tgl_selesai,
                   p.metode_pembayaran as metode_pembayaran_pesanan,  -- Ambil metode_pembayaran dari pesanan
                   pel.nama AS nama_pelanggan, pel.alamat, pel.no_hp,
                   u.nama_lengkap as nama_karyawan
            FROM data_transaksi t
            LEFT JOIN data_pesanan p ON t.id_pesanan = p.id_pesanan
            LEFT JOIN data_pelanggan pel ON p.id_pelanggan = pel.id_pelanggan
            LEFT JOIN users u ON p.id_karyawan = u.id_user
            $sql_where
            ORDER BY t.created_at DESC
            LIMIT $limit OFFSET $offset";
    $transaksi = getAll($sql, $params);
    
    // PERBAIKAN: Untuk setiap transaksi, ambil data items pesanan dari tabel pesanan_items
    foreach ($transaksi as &$trx) {
        $id_pesanan = $trx['id_pesanan'];
        
        // Ambil items pesanan dari tabel pesanan_items
        try {
            $sql_items = "SELECT * FROM pesanan_items WHERE id_pesanan = ? ORDER BY id_item ASC";
            $items = getAll($sql_items, [$id_pesanan]);
            
            if (!empty($items)) {
                $trx['items'] = $items;
                $trx['total_items'] = count($items);
                
                // Hitung total kuantitas
                $total_kuantitas = 0;
                $item_names = [];
                foreach ($items as $item) {
                    $total_kuantitas += ($item['jumlah'] ?? 1);
                    $item_names[] = $item['jenis_pakaian'];
                }
                $trx['item_names'] = $item_names;
            } else {
                // Fallback untuk data lama
                $trx['items'] = [];
                $trx['total_items'] = 1;
                $trx['item_names'] = [$trx['jenis_pakaian'] ?? 'Pakaian'];
            }
        } catch (PDOException $e) {
            $trx['items'] = [];
            $trx['total_items'] = 1;
            $trx['item_names'] = [$trx['jenis_pakaian'] ?? 'Pakaian'];
        }
    }
    unset($trx); // Hapus reference terakhir
    
} catch (PDOException $e) {
    $_SESSION['error'] = "❌ Gagal memuat data transaksi: " . $e->getMessage();
    $transaksi = [];
}

// Ambil data pengeluaran
try {
    $sql_pengeluaran = "SELECT * FROM data_pengeluaran ORDER BY tgl_pengeluaran DESC, created_at DESC";
    $pengeluaran_list = getAll($sql_pengeluaran);
} catch (PDOException $e) {
    $pengeluaran_list = [];
}

// PERBAIKAN: Hitung statistik keuangan dengan informasi lebih detail
try {
    // Total Omzet (total harga semua pesanan yang dibuat)
    $sql_omzet = "SELECT COALESCE(SUM(total_harga), 0) FROM data_pesanan";
    $total_omzet = executeQuery($sql_omzet)->fetchColumn();
    
    // Total Uang Masuk (semua pembayaran yang sudah diterima dari pesanan)
    $sql_uang_masuk = "SELECT COALESCE(SUM(jumlah_bayar), 0) FROM data_transaksi";
    $total_uang_masuk = executeQuery($sql_uang_masuk)->fetchColumn();
    
    // Total Piutang (sisa bayar dari pesanan yang belum lunas)
    $sql_piutang = "SELECT COALESCE(SUM(sisa_bayar), 0) FROM data_pesanan 
                    WHERE sisa_bayar > 0";
    $total_piutang = executeQuery($sql_piutang)->fetchColumn();
    
    // Total Pengeluaran (semua transaksi pengeluaran dari tabel pengeluaran)
    $sql_pengeluaran = "SELECT COALESCE(SUM(jumlah_pengeluaran), 0) FROM data_pengeluaran";
    $total_pengeluaran = executeQuery($sql_pengeluaran)->fetchColumn();
    
    // Hitung Kas Bersih (Uang Masuk - Pengeluaran)
    $kas_bersih = $total_uang_masuk - $total_pengeluaran;
    
    // Statistik detail untuk informasi tooltip
    $statistik_detail = [
        'total_omzet' => $total_omzet,
        'total_uang_masuk' => $total_uang_masuk,
        'total_piutang' => $total_piutang,
        'total_pengeluaran' => $total_pengeluaran,
        'kas_bersih' => $kas_bersih
    ];
    
    // Hitung informasi tambahan untuk tooltip
    $sql_pesanan_belum_bayar = "SELECT COUNT(*) FROM data_pesanan WHERE jumlah_bayar = 0";
    $count_pesanan_belum_bayar = executeQuery($sql_pesanan_belum_bayar)->fetchColumn();
    
    $sql_pesanan_dp = "SELECT COUNT(*) FROM data_pesanan WHERE jumlah_bayar > 0 AND jumlah_bayar < total_harga";
    $count_pesanan_dp = executeQuery($sql_pesanan_dp)->fetchColumn();
    
    $sql_pesanan_lunas = "SELECT COUNT(*) FROM data_pesanan WHERE jumlah_bayar >= total_harga";
    $count_pesanan_lunas = executeQuery($sql_pesanan_lunas)->fetchColumn();
    
    $sql_total_transaksi = "SELECT COUNT(*) FROM data_transaksi";
    $count_total_transaksi = executeQuery($sql_total_transaksi)->fetchColumn();
    
    $sql_avg_transaksi = "SELECT COALESCE(AVG(jumlah_bayar), 0) FROM data_transaksi";
    $avg_transaksi = executeQuery($sql_avg_transaksi)->fetchColumn();
    
    $sql_kategori_pengeluaran = "SELECT kategori_pengeluaran, SUM(jumlah_pengeluaran) as total 
                                 FROM data_pengeluaran 
                                 GROUP BY kategori_pengeluaran 
                                 ORDER BY total DESC";
    $kategori_pengeluaran = getAll($sql_kategori_pengeluaran);
    
} catch (PDOException $e) {
    $total_omzet = 0;
    $total_piutang = 0;
    $total_pengeluaran = 0;
    $total_uang_masuk = 0;
    $kas_bersih = 0;
    $statistik_detail = [];
    $count_pesanan_belum_bayar = 0;
    $count_pesanan_dp = 0;
    $count_pesanan_lunas = 0;
    $count_total_transaksi = 0;
    $avg_transaksi = 0;
    $kategori_pengeluaran = [];
}

// ============================================================
// TAMPILKAN INFORMASI SYNC OTOMATIS JIKA ADA
// ============================================================
if (isset($_SESSION['sync_info'])) {
    $_SESSION['info'] = $_SESSION['sync_info'];
    unset($_SESSION['sync_info']);
}

if (isset($_SESSION['sync_warning'])) {
    if (!isset($_SESSION['warning'])) {
        $_SESSION['warning'] = $_SESSION['sync_warning'];
    } else {
        $_SESSION['warning'] .= " | " . $_SESSION['sync_warning'];
    }
    unset($_SESSION['sync_warning']);
}

$page_title = "Data Transaksi";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Transaksi SIM Parapatan Tailor</title>
     <link rel="shortcut icon" href="../assets/images/logoterakhir.png" type="image/x-icon" />

    <!-- Bootstrap 5.3.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../includes/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* CSS Custom yang konsisten */
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
        
        /* Button Styles */
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
        
        .alert-info { 
            background: linear-gradient(135deg, #dbeafe 0%, #93c5fd 100%);
            color: #1e40af; 
            border-left: 3px solid #3b82f6;
        }
        
        .alert-warning { 
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #92400e; 
            border-left: 3px solid #f59e0b;
        }
        
        /* Table Styles */
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
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: white;
            padding: 1.1rem;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 3px solid #4f46e5;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.15);
        }

        .stat-card.omzet {
            border-left-color: #10b981;
        }

        .stat-card.uang-masuk {
            border-left-color: #3b82f6;
        }

        .stat-card.piutang {
            border-left-color: #f59e0b;
        }

        .stat-card.pengeluaran {
            border-left-color: #ef4444;
        }

        .stat-card.kas-bersih {
            border-left-color: #8b5cf6;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.4rem;
        }

        .stat-label {
            font-size: 0.65rem;
            color: #6b7280;
            font-weight: 400;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .stat-icon {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            margin: 0 auto 0.8rem;
        }

        .stat-icon.omzet {
            background: linear-gradient(135deg, #dcfce7, #bbf7d0);
            color: #059669;
        }

        .stat-icon.uang-masuk {
            background: linear-gradient(135deg, #dbeafe, #93c5fd);
            color: #2563eb;
        }

        .stat-icon.piutang {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: #d97706;
        }

        .stat-icon.pengeluaran {
            background: linear-gradient(135deg, #fee2e2, #fca5a5);
            color: #dc2626;
        }

        .stat-icon.kas-bersih {
            background: linear-gradient(135deg, #ede9fe, #ddd6fe);
            color: #7c3aed;
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

        /* Status Pembayaran Styles */
        .status-container {
            display: flex;
            align-items: center;
            gap: 0.5rem;
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

        .status-badge.belum_bayar {
            background: #fef3c7;
            color: #d97706;
            border: 1px solid #fcd34d;
        }

        .status-badge.dp {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #93c5fd;
        }

        .status-badge.lunas {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }

        /* Amount Column */
        .amount-column {
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .amount-value {
            font-weight: 600;
            font-size: 0.7rem;
        }

        .amount-value.positive {
            color: #059669;
        }

        .amount-value.negative {
            color: #dc2626;
        }

        /* Badge untuk status pesanan utama */
        .status-pesanan-badge {
            font-size: 0.6rem;
            padding: 0.15rem 0.4rem;
            border-radius: 8px;
            margin-left: 0.3rem;
            font-weight: 600;
        }

        .badge-selesai {
            background: linear-gradient(135deg, #10b981, #34d399);
            color: white;
        }

        .badge-dalam_proses {
            background: linear-gradient(135deg, #3b82f6, #60a5fa);
            color: white;
        }

        .badge-belum {
            background: linear-gradient(135deg, #f59e0b, #fbbf24);
            color: white;
        }

        /* Modal Styles */
        .modal-content {
            border: none;
            border-radius: 12px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .modal-header {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            border-radius: 12px 12px 0 0;
            padding: 1.2rem;
            border: none;
        }

        .modal-title {
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1rem;
        }

        .id-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .modal-body {
            padding: 1.2rem;
            font-size: 0.8rem;
        }

        /* Detail Transaksi Modal */
        .info-section {
            margin-bottom: 1.2rem;
        }

        .info-item {
            background: #f8fafc;
            border-radius: 8px;
            padding: 0.8rem;
            margin-bottom: 0.8rem;
            border-left: 4px solid #3b82f6;
        }

        .info-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.4rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8rem;
        }

        .info-value {
            color: #6b7280;
            line-height: 1.5;
            font-size: 0.75rem;
        }

        .info-value strong {
            color: #374151;
            font-weight: 600;
        }

        .highlight {
            color: #3b82f6;
            font-weight: 600;
        }

        .payment-info {
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
            border-radius: 8px;
            padding: 1.2rem;
            margin: 1.2rem 0;
            border: 1px solid #bae6fd;
        }

        .payment-info h6 {
            color: #0c4a6e;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            margin-bottom: 0.8rem;
        }

        .payment-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e0f2fe;
            font-size: 0.75rem;
        }

        .payment-item:last-child {
            border-bottom: none;
        }

        .payment-label {
            font-weight: 500;
            color: #0c4a6e;
        }

        .payment-value {
            font-weight: 700;
        }

        .payment-value.total {
            color: #059669;
        }

        .payment-value.paid {
            color: #2563eb;
        }

        .payment-value.remaining {
            color: #dc2626;
        }

        /* Form Edit Transaksi */
        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
            font-size: 0.8rem;
        }

        .pesanan-info {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 8px;
            padding: 0.8rem;
            margin-top: 0.5rem;
            font-size: 0.75rem;
        }

        .pesanan-info p {
            margin: 0.25rem 0;
        }

        .pesanan-info strong {
            color: #0c4a6e;
        }

        /* Delete Modal Styling */
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

        /* Warning Icon */
        .warning-icon {
            font-size: 2.5rem;
            color: #dc3545;
            margin-bottom: 0.8rem;
        }

        /* Tab Navigation */
        .nav-tabs {
            border-bottom: 2px solid #e5e7eb;
            margin-bottom: 1.5rem;
        }

        .nav-tabs .nav-link {
            border: none;
            color: #6b7280;
            font-weight: 500;
            padding: 0.8rem 1.2rem;
            border-radius: 8px 8px 0 0;
            transition: all 0.3s ease;
            font-size: 0.8rem;
        }

        .nav-tabs .nav-link:hover {
            color: #374151;
            background: #f9fafb;
        }

        .nav-tabs .nav-link.active {
            color: #4f46e5;
            background: white;
            border-bottom: 3px solid #4f46e5;
            font-weight: 600;
        }

        /* Progress bar untuk status pembayaran */
        .payment-progress {
            height: 6px;
            background: #e5e7eb;
            border-radius: 3px;
            margin-top: 0.3rem;
            overflow: hidden;
        }

        .payment-progress-bar {
            height: 100%;
            border-radius: 3px;
            transition: width 0.3s ease;
        }

        .progress-belum_bayar { background: #f59e0b; width: 0%; }
        .progress-dp { background: #3b82f6; }
        .progress-lunas { background: #10b981; width: 100%; }
        
        /* Statistik Detail Tooltip */
        .stat-tooltip {
            position: fixed;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 1.2rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            z-index: 9999;
            font-size: 0.75rem;
            width: 320px;
            max-width: 90vw;
            display: none;
            pointer-events: auto;
            animation: fadeIn 0.2s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .stat-tooltip h6 {
            color: #4f46e5;
            margin-bottom: 0.8rem;
            font-size: 0.85rem;
            font-weight: 600;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 0.5rem;
        }
        
        .stat-tooltip-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.6rem;
            padding-bottom: 0.6rem;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .stat-tooltip-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .stat-tooltip-label {
            color: #6b7280;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        
        .stat-tooltip-label i {
            color: #9ca3af;
            font-size: 0.7rem;
        }
        
        .stat-tooltip-value {
            font-weight: 600;
            color: #374151;
            text-align: right;
            font-size: 0.8rem;
        }
        
        .stat-tooltip-value.positive {
            color: #059669;
        }
        
        .stat-tooltip-value.negative {
            color: #dc2626;
        }
        
        .stat-tooltip-value.neutral {
            color: #6b7280;
        }
        
        .stat-tooltip-divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, #e5e7eb, transparent);
            margin: 0.8rem 0;
        }
        
        .stat-tooltip-footer {
            margin-top: 0.8rem;
            padding-top: 0.8rem;
            border-top: 2px solid #e5e7eb;
            font-size: 0.7rem;
            color: #9ca3af;
            text-align: center;
        }
        
        .stat-tooltip-kategori {
            background: #f9fafb;
            border-radius: 6px;
            padding: 0.6rem;
            margin-top: 0.5rem;
            font-size: 0.7rem;
        }
        
        .stat-tooltip-kategori-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.3rem;
        }
        
        .stat-tooltip-kategori-item:last-child {
            margin-bottom: 0;
        }
        
        /* Sync Notification */
        .sync-notification {
            background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
            border-left: 4px solid #0ea5e9;
            padding: 0.8rem 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .sync-notification i {
            color: #0ea5e9;
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

            .status-container {
                flex-direction: column;
                gap: 0.3rem;
            }

            .status-badge {
                width: 100%;
            }

            .nav-tabs .nav-link {
                padding: 0.6rem 0.8rem;
                font-size: 0.75rem;
            }
            
            .stat-tooltip {
                width: 280px;
                left: 50% !important;
                transform: translateX(-50%) !important;
                top: 120px !important;
            }
        }

        @media (max-width: 480px) {
            .header-actions h3 {
                font-size: 1rem;
            }
            
            .stat-card {
                padding: 1.2rem;
            }
            
            .stats-number {
                font-size: 1.6rem;
            }
            
            .btn {
                padding: 0.3rem 0.5rem;
                font-size: 0.7rem;
            }
            
            .stat-tooltip {
                width: 250px;
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

        /* Compact table cell styles */
        .table td, .table th {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Specific column widths untuk layout yang lebih baik */
        .table td:nth-child(1) { width: 40px; } /* No */
        .table td:nth-child(2) { width: 120px; } /* ID Transaksi */
        .table td:nth-child(3) { width: 150px; } /* Pelanggan */
        .table td:nth-child(4) { width: 180px; } /* Jenis Pakaian - LEBIH SEDERHANA */
        .table td:nth-child(5) { width: 90px; } /* Tanggal Transaksi */
        .table td:nth-child(6) { width: 100px; } /* Metode Bayar */
        .table td:nth-child(7) { width: 100px; } /* Jumlah */
        .table td:nth-child(8) { width: 120px; } /* Status Pembayaran */
        .table td:nth-child(9) { width: 100px; } /* Aksi */

        /* Ensure no horizontal scroll */
        body, .main-content, .content-body, .container {
            max-width: 100%;
            overflow-x: hidden;
        }

        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        /* Info tooltip untuk statistik */
        .stat-info {
            position: absolute;
            top: 5px;
            right: 5px;
            color: #9ca3af;
            font-size: 0.7rem;
            cursor: help;
        }
        
        /* ============================================================
           STYLE BARU YANG SEDERHANA UNTUK KOLOM JENIS PAKAIAN
           ============================================================ */
        
        /* Styles untuk kolom Jenis Pakaian - SEDERHANA VERSION */
        .jenis-pakaian-simple {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            min-width: 160px;
        }

        .jenis-pakaian-main {
            font-weight: 600;
            color: #1f2937;
            font-size: 0.75rem;
            line-height: 1.3;
            flex: 1;
        }

        .view-items-btn {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            color: #0c4a6e;
            padding: 0.2rem 0.4rem;
            border-radius: 6px;
            font-size: 0.65rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.2rem;
            transition: all 0.2s ease;
        }

        .view-items-btn:hover {
            background: #e0f2fe;
            transform: translateY(-1px);
            box-shadow: 0 1px 4px rgba(0,0,0,0.1);
        }

        /* PERBAIKAN: Ganti ikon mata dengan panah kanan atas */
        .view-items-btn i {
            font-size: 0.65rem;
        }

        /* Hapus items-count-badge */
        .items-count-badge {
            display: none !important;
        }

        /* Modal Quick View untuk Transaksi - SEDERHANA */
        .quick-view-modal .modal-content {
            border: none;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }

        .quick-view-modal .modal-header {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            border-radius: 12px 12px 0 0;
            padding: 1rem 1.2rem;
            border-bottom: none;
        }

        .quick-view-modal .modal-title {
            font-size: 0.9rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .quick-view-modal .modal-body {
            padding: 1.2rem;
            font-size: 0.75rem;
        }

        .quick-view-items {
            margin-top: 1rem;
        }

        .quick-view-items h6 {
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 0.5rem;
            margin-bottom: 0.8rem;
            font-size: 0.8rem;
            font-weight: 600;
            color: #1f2937;
        }

        .quick-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.4rem 0;
            border-bottom: 1px solid #f3f4f6;
            font-size: 0.7rem;
        }

        .quick-item:last-child {
            border-bottom: none;
        }

        .quick-item-name {
            font-weight: 500;
            color: #1f2937;
        }

        /* Items list dalam modal */
        .items-list-modal {
            max-height: 300px;
            overflow-y: auto;
            padding-right: 5px;
        }
        
        .items-list-modal::-webkit-scrollbar {
            width: 4px;
        }
        
        .items-list-modal::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 2px;
        }
        
        .items-list-modal::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 2px;
        }
        
        .items-list-modal::-webkit-scrollbar-thumb:hover {
            background: #a1a1a1;
        }

        .item-detail-row {
            display: flex;
            padding: 0.5rem;
            background: #f9fafb;
            border-radius: 6px;
            margin-bottom: 0.5rem;
            font-size: 0.7rem;
        }

        .item-detail-label {
            font-weight: 600;
            color: #374151;
            min-width: 120px;
        }

        .item-detail-value {
            color: #6b7280;
            flex: 1;
        }
        
        /* SIMPLE ITEMS MODAL - PERBAIKAN */
        .simple-items-modal .modal-header {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            border-radius: 12px 12px 0 0;
            padding: 0.8rem 1rem;
        }
        
        .simple-items-modal .modal-title {
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .simple-items-modal .modal-body {
            padding: 1rem;
            font-size: 0.7rem;
        }
        
        .simple-items-list {
            max-height: 250px;
            overflow-y: auto;
            padding-right: 5px;
        }
        
        .simple-item {
            display: flex;
            align-items: center;
            padding: 0.5rem;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.7rem;
        }
        
        .simple-item:last-child {
            border-bottom: none;
        }
        
        .simple-item-number {
            width: 25px;
            text-align: center;
            font-weight: 600;
            color: #4f46e5;
            font-size: 0.7rem;
        }
        
        .simple-item-name {
            flex: 1;
            color: #1f2937;
            font-weight: 500;
            font-size: 0.7rem;
        }
        
        .simple-total-items {
            background: #f0f9ff;
            border-radius: 8px;
            padding: 0.6rem;
            margin-top: 1rem;
            text-align: center;
            font-size: 0.7rem;
            font-weight: 600;
            color: #0c4a6e;
        }
    </style>
</head>

<body>
    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/header.php'; ?>

    <div class="main-content">
        <div class="content-body">
            <div class="container">
                <h2 class="my-3">Data Transaksi & Keuangan</h2>

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
                
                <?php if (isset($_SESSION['info'])): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> <?= $_SESSION['info']; unset($_SESSION['info']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['warning'])): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> <?= $_SESSION['warning']; unset($_SESSION['warning']); ?>
                    </div>
                <?php endif; ?>

                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card omzet" id="statOmzet" data-stat="omzet">
                        <div class="stat-info" title="Klik untuk detail">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <div class="stat-icon omzet">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-number">Rp <?= number_format($total_omzet, 0, ',', '.'); ?></div>
                        <div class="stat-label">
                            <i class="fas fa-money-bill-wave"></i> Total Omzet
                        </div>
                    </div>
                    
                    <div class="stat-card uang-masuk" id="statUangMasuk" data-stat="uang-masuk">
                        <div class="stat-info" title="Klik untuk detail">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <div class="stat-icon uang-masuk">
                            <i class="fas fa-hand-holding-usd"></i>
                        </div>
                        <div class="stat-number">Rp <?= number_format($total_uang_masuk, 0, ',', '.'); ?></div>
                        <div class="stat-label">
                            <i class="fas fa-cash-register"></i> Uang Masuk
                        </div>
                    </div>
                    
                    <div class="stat-card kas-bersih" id="statKasBersih" data-stat="kas-bersih">
                        <div class="stat-info" title="Klik untuk detail">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <div class="stat-icon kas-bersih">
                            <i class="fas fa-wallet"></i>
                        </div>
                        <div class="stat-number">Rp <?= number_format($kas_bersih, 0, ',', '.'); ?></div>
                        <div class="stat-label">
                            <i class="fas fa-balance-scale"></i> Kas Bersih
                        </div>
                    </div>
                    
                    <div class="stat-card piutang" id="statPiutang" data-stat="piutang">
                        <div class="stat-info" title="Klik untuk detail">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <div class="stat-icon piutang">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>
                        <div class="stat-number">Rp <?= number_format($total_piutang, 0, ',', '.'); ?></div>
                        <div class="stat-label">
                            <i class="fas fa-clock"></i> Total Piutang
                        </div>
                    </div>
                    
                    <div class="stat-card pengeluaran" id="statPengeluaran" data-stat="pengeluaran">
                        <div class="stat-info" title="Klik untuk detail">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <div class="stat-icon pengeluaran">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="stat-number">Rp <?= number_format($total_pengeluaran, 0, ',', '.'); ?></div>
                        <div class="stat-label">
                            <i class="fas fa-receipt"></i> Total Pengeluaran
                        </div>
                    </div>
                </div>
                
                <!-- Tooltip untuk statistik detail -->
                <div class="stat-tooltip" id="statTooltip"></div>

                <!-- Tab Navigation -->
                <ul class="nav nav-tabs" id="transaksiTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= $current_tab == 'transaksi' ? 'active' : ''; ?>" id="transaksi-tab" data-bs-toggle="tab" data-bs-target="#transaksi" type="button" role="tab" aria-controls="transaksi" aria-selected="true">
                            <i class="fas fa-receipt"></i> Transaksi Pembayaran
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= $current_tab == 'pengeluaran' ? 'active' : ''; ?>" id="pengeluaran-tab" data-bs-toggle="tab" data-bs-target="#pengeluaran" type="button" role="tab" aria-controls="pengeluaran" aria-selected="false">
                            <i class="fas fa-minus-circle"></i> Data Pengeluaran
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="transaksiTabsContent">
                    
                    <!-- Tab Transaksi Pembayaran -->
                    <div class="tab-pane fade <?= $current_tab == 'transaksi' ? 'show active' : ''; ?>" id="transaksi" role="tabpanel" aria-labelledby="transaksi-tab">
                        <!-- Filter Section -->
                        <div class="filter-section">
                            <form method="GET" class="filter-form">
                                <input type="hidden" name="tab" value="transaksi">
                                <div class="filter-group">
                                    <label for="searchInput">Cari Transaksi:</label>
                                    <input type="text" id="searchInput" name="search" placeholder="Cari berdasarkan pelanggan, jenis pakaian, bahan, atau metode bayar..." 
                                           value="<?= htmlspecialchars($search); ?>" 
                                           class="form-control">
                                </div>
                                <div class="filter-group">
                                    <label for="tanggal">Filter Tanggal Transaksi:</label>
                                    <input type="date" id="tanggal" name="tanggal" 
                                           value="<?= $filter_tanggal; ?>" 
                                           class="form-control">
                                </div>
                                <div class="filter-group">
                                    <label for="bulan">Filter Bulan:</label>
                                    <input type="month" id="bulan" name="bulan" 
                                           value="<?= $filter_bulan; ?>" 
                                           class="form-control">
                                </div>
                                <div class="filter-group">
                                    <label for="status">Filter Status Pembayaran:</label>
                                    <select id="status" name="status" class="form-control">
                                        <option value="">Semua Status</option>
                                        <option value="belum_bayar" <?= $filter_status == 'belum_bayar' ? 'selected' : ''; ?>>Belum Bayar</option>
                                        <option value="dp" <?= $filter_status == 'dp' ? 'selected' : ''; ?>>DP</option>
                                        <option value="lunas" <?= $filter_status == 'lunas' ? 'selected' : ''; ?>>Lunas</option>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label>&nbsp;</label>
                                    <div style="display: flex; gap: 0.4rem;">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-search"></i> Terapkan Filter
                                        </button>
                                        <?php if (!empty($search) || !empty($filter_tanggal) || !empty($filter_status) || !empty($filter_bulan)): ?>
                                            <a href="transaksi.php?tab=transaksi" class="btn btn-secondary">
                                                <i class="fas fa-refresh"></i> Reset Filter
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Search Info -->
                        <?php if (!empty($search) || !empty($filter_tanggal) || !empty($filter_status) || !empty($filter_bulan)): ?>
                            <div class="search-info">
                                <i class="fas fa-info-circle"></i> Menampilkan hasil filter:
                                <?php if (!empty($search)): ?>
                                    Pencarian: <strong>"<?= htmlspecialchars($search); ?>"</strong>
                                <?php endif; ?>
                                <?php if (!empty($filter_tanggal)): ?>
                                    <?php if (!empty($search)): ?>, <?php endif; ?>
                                    Tanggal: <strong><?= date('d/m/Y', strtotime($filter_tanggal)); ?></strong>
                                <?php endif; ?>
                                <?php if (!empty($filter_bulan)): ?>
                                    <?php if (!empty($search) || !empty($filter_tanggal)): ?>, <?php endif; ?>
                                    Bulan: <strong><?= date('F Y', strtotime($filter_bulan . '-01')); ?></strong>
                                <?php endif; ?>
                                <?php if (!empty($filter_status)): ?>
                                    <?php if (!empty($search) || !empty($filter_tanggal) || !empty($filter_bulan)): ?>, <?php endif; ?>
                                    Status: <strong>
                                    <?php 
                                    switch($filter_status) {
                                        case 'belum_bayar': echo 'Belum Bayar'; break;
                                        case 'dp': echo 'DP'; break;
                                        case 'lunas': echo 'Lunas'; break;
                                        default: echo ucfirst($filter_status);
                                    }
                                    ?>
                                    </strong>
                                <?php endif; ?>
                                - Ditemukan <strong><?= $total_transaksi; ?></strong> transaksi
                            </div>
                        <?php endif; ?>

                        <!-- Header Actions -->
                        <div class="header-actions">
                            <h3>Daftar Transaksi Pembayaran</h3>
                            <div style="display: flex; gap: 0.8rem; align-items: center; flex-wrap: wrap;">
                                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#tambahTransaksiModal">
                                    <i class="fas fa-plus"></i> Tambah/Update Pembayaran
                                </button>
                            </div>
                        </div>

                        <?php if (empty($transaksi)): ?>
                            <div class="empty-state">
                                <div><i class="fas fa-receipt"></i></div>
                                <p style="font-size: 0.9rem; margin-bottom: 0.4rem;">Belum ada data transaksi</p>
                                <?php if (!empty($search) || !empty($filter_tanggal) || !empty($filter_status) || !empty($filter_bulan)): ?>
                                    <p style="color: #6b7280; font-size: 0.75rem; margin-top: 0.4rem;">
                                        <?php if (!empty($search)): ?>
                                            Pencarian: "<?= htmlspecialchars($search); ?>"
                                        <?php endif; ?>
                                        <?php if (!empty($filter_tanggal)): ?>
                                            <?php if (!empty($search)): ?>, <?php endif; ?>
                                            Filter tanggal: <?= date('d/m/Y', strtotime($filter_tanggal)); ?>
                                        <?php endif; ?>
                                        <?php if (!empty($filter_bulan)): ?>
                                            <?php if (!empty($search) || !empty($filter_tanggal)): ?>, <?php endif; ?>
                                            Filter bulan: <?= date('F Y', strtotime($filter_bulan . '-01')); ?>
                                        <?php endif; ?>
                                        <?php if (!empty($filter_status)): ?>
                                            <?php if (!empty($search) || !empty($filter_tanggal) || !empty($filter_bulan)): ?>, <?php endif; ?>
                                            Filter status: 
                                            <?php 
                                            switch($filter_status) {
                                                case 'belum_bayar': echo 'Belum Bayar'; break;
                                                case 'dp': echo 'DP'; break;
                                                case 'lunas': echo 'Lunas'; break;
                                                default: echo ucfirst($filter_status);
                                            }
                                            ?>
                                        <?php endif; ?>
                                    </p>
                                    <a href="transaksi.php?tab=transaksi" class="btn btn-primary" style="margin-top: 1.2rem;">
                                        <i class="fas fa-list"></i> Tampilkan Semua Data
                                    </a>
                                <?php else: ?>
                                    <div style="display: flex; gap: 0.5rem; justify-content: center; margin-top: 1.2rem; flex-wrap: wrap;">
                                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#tambahTransaksiModal">
                                            <i class="fas fa-plus"></i> Tambah Pembayaran
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="card">
                                <div class="table-responsive">
                                    <table class="table" id="transaksiTable">
                                        <thead>
                                            <tr>
                                                <th class="no-urut">No</th>
                                                <th>ID Transaksi</th>
                                                <th>Pelanggan</th>
                                                <th>Jenis Pakaian</th>
                                                <th>Tanggal Transaksi</th>
                                                <th>Metode Bayar</th>
                                                <th>Jumlah</th>
                                                <th>Status Pembayaran</th>
                                                <th style="text-align: center;">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $nomor_urut = ($page - 1) * $limit + 1;
                                            foreach ($transaksi as $row): 
                                                // PERBAIKAN DI SINI: Gunakan metode_bayar dari transaksi, 
                                                // tapi jika 'Auto System' gunakan metode_pembayaran dari pesanan
                                                $metode_bayar_display = $row['metode_bayar'];
                                                if ($metode_bayar_display == 'Auto System' && isset($row['metode_pembayaran_pesanan'])) {
                                                    $metode_bayar_display = $row['metode_pembayaran_pesanan'];
                                                }
                                                
                                                // Mapping metode bayar untuk tampilan yang lebih user friendly
                                                $metode_bayar_text = $metode_bayar_display;
                                                switch($metode_bayar_display) {
                                                    case 'tunai': $metode_bayar_text = 'Tunai'; break;
                                                    case 'transfer': $metode_bayar_text = 'Transfer Bank'; break;
                                                    case 'qris': $metode_bayar_text = 'QRIS'; break;
                                                    case 'kredit': $metode_bayar_text = 'Kartu Kredit'; break;
                                                    case 'debit': $metode_bayar_text = 'Kartu Debit'; break;
                                                    case 'Transfer': $metode_bayar_text = 'Transfer Bank'; break;
                                                    case 'QRIS': $metode_bayar_text = 'QRIS'; break;
                                                    case 'Kredit': $metode_bayar_text = 'Kartu Kredit'; break;
                                                    case 'Debit': $metode_bayar_text = 'Kartu Debit'; break;
                                                    case 'Tunai': $metode_bayar_text = 'Tunai'; break;
                                                }
                                                
                                                // Status pembayaran text mapping
                                                $status_text = '';
                                                $status_class = '';
                                                $progress_class = '';
                                                switch($row['status_pembayaran']) {
                                                    case 'belum_bayar':
                                                        $status_text = 'Belum Bayar';
                                                        $status_class = 'belum_bayar';
                                                        $progress_class = 'progress-belum_bayar';
                                                        break;
                                                    case 'dp':
                                                        $status_text = 'DP';
                                                        $status_class = 'dp';
                                                        $progress_class = 'progress-dp';
                                                        break;
                                                    case 'lunas':
                                                        $status_text = 'Lunas';
                                                        $status_class = 'lunas';
                                                        $progress_class = 'progress-lunas';
                                                        break;
                                                    default:
                                                        $status_text = $row['status_pembayaran'];
                                                        $status_class = 'belum_bayar';
                                                        $progress_class = 'progress-belum_bayar';
                                                }
                                                
                                                // Status pesanan utama (dari data_pesanan)
                                                $status_pesanan_text = '';
                                                $status_pesanan_class = '';
                                                switch($row['status_pesanan_utama']) {
                                                    case 'belum':
                                                        $status_pesanan_text = 'Belum';
                                                        $status_pesanan_class = 'badge-belum';
                                                        break;
                                                    case 'dalam_proses':
                                                        $status_pesanan_text = 'Proses';
                                                        $status_pesanan_class = 'badge-dalam_proses';
                                                        break;
                                                    case 'selesai':
                                                        $status_pesanan_text = 'Selesai';
                                                        $status_pesanan_class = 'badge-selesai';
                                                        break;
                                                    default:
                                                        $status_pesanan_text = $row['status_pesanan_utama'];
                                                        $status_pesanan_class = 'badge-belum';
                                                }
                                                
                                                // Hitung persentase pembayaran
                                                $persentase = 0;
                                                if ($row['total_harga'] > 0) {
                                                    $persentase = min(100, ($row['total_bayar_pesanan'] / $row['total_harga']) * 100);
                                                }
                                                
                                                // Ambil data items untuk tampilan
                                                $items = $row['items'] ?? [];
                                                $total_items = $row['total_items'] ?? 1;
                                                $item_names = $row['item_names'] ?? [$row['jenis_pakaian'] ?? 'Pakaian'];
                                                $first_item = $item_names[0] ?? 'Pakaian';
                                            ?>
                                            <tr>
                                                <td class="no-urut"><?= $nomor_urut++; ?></td>
                                                <td style="font-weight: 600; color: #4f46e5;">
                                                    <?= htmlspecialchars($row['id_transaksi']); ?>
                                                    <span class="status-pesanan-badge <?= $status_pesanan_class; ?>" title="Status Pesanan: <?= $status_pesanan_text; ?>">
                                                        <?= $status_pesanan_text; ?>
                                                    </span>
                                                </td>
                                                <td style="font-weight: 600; color: #1f2937;">
                                                    <?= htmlspecialchars($row['nama_pelanggan'] ?? '-'); ?>
                                                </td>
                                                <td>
                                                    <!-- KOLOM JENIS PAKAIAN YANG DISEDERHANAKAN -->
                                                    <div class="jenis-pakaian-simple">
                                                        <div class="jenis-pakaian-main">
                                                            <?= htmlspecialchars($first_item); ?>
                                                            <!-- HAPUS NOTIFIKASI +3 ATAU +2 -->
                                                        </div>
                                                        
                                                        <?php if (!empty($items)): ?>
                                                        <button type="button" 
                                                                class="view-items-btn" 
                                                                title="Lihat detail items"
                                                                onclick="showSimpleItemsModal(
                                                                    <?= $row['id_pesanan']; ?>,
                                                                    <?= $total_items; ?>
                                                                )">
                                                            <!-- PERBAIKAN: Ganti ikon mata dengan panah kanan atas -->
                                                            <i class="fas fa-external-link-alt"></i>
                                                        </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td><?= date('d/m/Y H:i', strtotime($row['created_at'])); ?></td>
                                                <td>
                                                    <span class="badge bg-info" style="font-size: 0.65rem;">
                                                        <?= htmlspecialchars($metode_bayar_text); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="amount-column">
                                                        <span class="amount-value positive">Rp <?= number_format($row['jumlah_bayar'], 0, ',', '.'); ?></span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="status-container">
                                                        <span class="status-badge <?= $status_class; ?>"><?= $status_text; ?></span>
                                                        <div class="payment-progress">
                                                            <div class="payment-progress-bar <?= $progress_class; ?>" style="width: <?= $persentase; ?>%"></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <button type="button" 
                                                                class="btn btn-danger btn-sm delete-btn" 
                                                                title="Hapus Transaksi"
                                                                data-id="<?= $row['id_transaksi']; ?>"
                                                                data-pelanggan="<?= htmlspecialchars($row['nama_pelanggan'] ?? '-'); ?>">
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
                                            <a class="page-link" href="?tab=transaksi&page=<?= $page - 1; ?><?= !empty($search) ? '&search=' . urlencode($search) : ''; ?><?= !empty($filter_tanggal) ? '&tanggal=' . $filter_tanggal : ''; ?><?= !empty($filter_bulan) ? '&bulan=' . $filter_bulan : ''; ?><?= !empty($filter_status) ? '&status=' . $filter_status : ''; ?>">
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
                                            <a class="page-link" href="?tab=transaksi&page=<?= $i; ?><?= !empty($search) ? '&search=' . urlencode($search) : ''; ?><?= !empty($filter_tanggal) ? '&tanggal=' . $filter_tanggal : ''; ?><?= !empty($filter_bulan) ? '&bulan=' . $filter_bulan : ''; ?><?= !empty($filter_status) ? '&status=' . $filter_status : ''; ?>"><?= $i; ?></a>
                                        </div>
                                    <?php endfor; ?>

                                    <!-- Next Page -->
                                    <?php if ($page < $total_pages): ?>
                                        <div class="page-item">
                                            <a class="page-link" href="?tab=transaksi&page=<?= $page + 1; ?><?= !empty($search) ? '&search=' . urlencode($search) : ''; ?><?= !empty($filter_tanggal) ? '&tanggal=' . $filter_tanggal : ''; ?><?= !empty($filter_bulan) ? '&bulan=' . $filter_bulan : ''; ?><?= !empty($filter_status) ? '&status=' . $filter_status : ''; ?>">
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
                                        (Total: <?= $total_transaksi; ?> transaksi)
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Tab Data Pengeluaran -->
                    <div class="tab-pane fade <?= $current_tab == 'pengeluaran' ? 'show active' : ''; ?>" id="pengeluaran" role="tabpanel" aria-labelledby="pengeluaran-tab">
                        <!-- Header Actions -->
                        <div class="header-actions">
                            <h3>Data Pengeluaran</h3>
                            <div style="display: flex; gap: 0.8rem; align-items: center; flex-wrap: wrap;">
                                <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#tambahPengeluaranModal">
                                    <i class="fas fa-plus-circle"></i> Input Pengeluaran
                                </button>
                            </div>
                        </div>

                        <?php if (empty($pengeluaran_list)): ?>
                            <div class="empty-state">
                                <div><i class="fas fa-minus-circle"></i></div>
                                <p style="font-size: 0.9rem; margin-bottom: 0.4rem;">Belum ada data pengeluaran</p>
                                <div style="display: flex; gap: 0.5rem; justify-content: center; margin-top: 1.2rem; flex-wrap: wrap;">
                                    <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#tambahPengeluaranModal">
                                        <i class="fas fa-plus-circle"></i> Input Pengeluaran
                                    </button>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="card">
                                <div class="table-responsive">
                                    <table class="table" id="pengeluaranTable">
                                        <thead>
                                            <tr>
                                                <th class="no-urut">No</th>
                                                <th>Tanggal</th>
                                                <th>Kategori</th>
                                                <th>Jumlah</th>
                                                <th>Keterangan</th>
                                                <th style="text-align: center;">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $nomor_urut = 1; ?>
                                            <?php foreach ($pengeluaran_list as $pengeluaran): ?>
                                            <tr>
                                                <td class="no-urut"><?= $nomor_urut++; ?></td>
                                                <td><?= date('d/m/Y', strtotime($pengeluaran['tgl_pengeluaran'])); ?></td>
                                                <td>
                                                    <span class="status-badge belum_bayar"><?= htmlspecialchars($pengeluaran['kategori_pengeluaran']); ?></span>
                                                </td>
                                                <td>
                                                    <span class="amount-value negative">- Rp <?= number_format($pengeluaran['jumlah_pengeluaran'], 0, ',', '.'); ?></span>
                                                </td>
                                                <td><?= htmlspecialchars($pengeluaran['keterangan'] ?? '-'); ?></td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <button type="button" 
                                                                class="btn btn-danger btn-sm delete-pengeluaran-btn" 
                                                                title="Hapus Pengeluaran"
                                                                data-id="<?= $pengeluaran['id_pengeluaran']; ?>"
                                                                data-kategori="<?= htmlspecialchars($pengeluaran['kategori_pengeluaran']); ?>">
                                                           <i class="fas fa-trash"></i> 
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <?php include '../includes/footer.php'; ?>
    </div>

    <!-- Modal Tambah Transaksi -->
    <div class="modal fade" id="tambahTransaksiModal" tabindex="-1" aria-labelledby="tambahTransaksiModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tambahTransaksiModalLabel">
                        <i class="fas fa-plus"></i> Tambah Pembayaran Baru
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="formTambahTransaksi">
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label">Pilih Pesanan</label>
                            <select name="id_pesanan" class="form-control" required id="select_pesanan">
                                <option value="">Pilih Pesanan</option>
                                <?php foreach ($pesanan_list as $pesanan): 
                                    $status_pembayaran = getPaymentStatus($pesanan['jumlah_bayar'], $pesanan['total_harga']);
                                    $status_text = '';
                                    switch($status_pembayaran) {
                                        case 'belum_bayar': $status_text = 'Belum Bayar'; break;
                                        case 'dp': $status_text = 'DP'; break;
                                        case 'lunas': $status_text = 'Lunas'; break;
                                    }
                                    
                                    // Mapping metode pembayaran untuk tampilan
                                    $metode_text = $pesanan['metode_pembayaran'];
                                    switch($pesanan['metode_pembayaran']) {
                                        case 'tunai': $metode_text = 'Tunai'; break;
                                        case 'transfer': $metode_text = 'Transfer Bank'; break;
                                        case 'qris': $metode_text = 'QRIS'; break;
                                        case 'kredit': $metode_text = 'Kartu Kredit'; break;
                                        case 'debit': $metode_text = 'Kartu Debit'; break;
                                    }
                                    
                                    // Ambil items untuk dropdown
                                    $items = $pesanan['items'] ?? [];
                                    $total_items = $pesanan['total_items'] ?? 1;
                                    $item_names = $pesanan['item_names'] ?? [$pesanan['jenis_pakaian'] ?? 'Pakaian'];
                                    $first_item = $item_names[0] ?? 'Pakaian';
                                ?>
                                    <option value="<?= $pesanan['id_pesanan']; ?>" 
                                            data-total-harga="<?= $pesanan['total_harga']; ?>"
                                            data-total-bayar="<?= $pesanan['jumlah_bayar']; ?>"
                                            data-sisa-bayar="<?= $pesanan['sisa_bayar']; ?>"
                                            data-metode-bayar="<?= $pesanan['metode_pembayaran']; ?>"
                                            data-status="<?= $status_pembayaran; ?>"
                                            data-items='<?= json_encode($item_names); ?>'
                                            data-total-items="<?= $total_items; ?>"
                                            data-first-item="<?= htmlspecialchars($first_item); ?>">
                                        <?= htmlspecialchars($pesanan['nama_pelanggan']); ?> - 
                                        <?= htmlspecialchars($first_item); ?>
                                        <?php if ($total_items > 1): ?> (<?= $total_items; ?> items)<?php endif; ?> - 
                                        Status: <?= $status_text; ?> - 
                                        Metode: <?= $metode_text; ?> - 
                                        Sisa: Rp <?= number_format($pesanan['sisa_bayar'], 0, ',', '.'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="pesanan-info" id="info_pesanan" style="display: none;">
                                <p><strong>Total Harga:</strong> Rp <span id="info_total_harga">0</span></p>
                                <p><strong>Total Sudah Dibayar:</strong> Rp <span id="info_total_bayar">0</span></p>
                                <p><strong>Sisa Bayar:</strong> Rp <span id="info_sisa_bayar">0</span></p>
                                <p><strong>Status Saat Ini:</strong> <span id="info_status">-</span></p>
                                <p><strong>Metode Bayar Pesanan:</strong> <span id="info_metode_bayar">-</span></p>
                                <p><strong>Items:</strong> <span id="info_items">-</span></p>
                                <p class="text-info small"><i class="fas fa-info-circle"></i> Pembayaran baru akan ditambahkan ke transaksi yang sudah ada</p>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Jumlah Bayar</label>
                            <input type="number" name="jumlah_bayar" class="form-control" required 
                                   placeholder="Masukkan jumlah bayar" min="1" 
                                   id="input_jumlah_bayar">
                            <small class="form-text text-muted">Maksimal: Rp <span id="max_jumlah_bayar">0</span></small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Metode Pembayaran</label>
                            <select name="metode_bayar" class="form-control" required id="select_metode_bayar">
                                <option value="Tunai">Tunai</option>
                                <option value="Transfer">Transfer Bank</option>
                                <option value="QRIS">QRIS</option>
                                <option value="Kredit">Kartu Kredit</option>
                                <option value="Debit">Kartu Debit</option>

                            </select>
                            <small class="form-text text-muted">Jika dikosongkan, akan menggunakan metode pembayaran dari pesanan</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Keterangan (Opsional)</label>
                            <textarea name="keterangan" class="form-control" rows="3" 
                                      placeholder="Tambahkan keterangan transaksi..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Batal
                        </button>
                        <button type="submit" name="tambah_transaksi" class="btn btn-success">
                            <i class="fas fa-save"></i> Simpan Pembayaran
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Tambah Pengeluaran -->
    <div class="modal fade" id="tambahPengeluaranModal" tabindex="-1" aria-labelledby="tambahPengeluaranModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tambahPengeluaranModalLabel">
                        <i class="fas fa-minus-circle"></i> Input Pengeluaran
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label">Jumlah Pengeluaran</label>
                            <input type="number" name="jumlah_pengeluaran" class="form-control" required 
                                   placeholder="Masukkan jumlah pengeluaran" min="0" step="1000">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Kategori Pengeluaran</label>
                            <select name="kategori_pengeluaran" class="form-control" required>
                                <option value="Operasional">Operasional</option>
                                <option value="Bahan Baku">Bahan Baku</option>
                                <option value="Gaji Karyawan">Gaji Karyawan</option>
                                <option value="Listrik & Air">Listrik & Air</option>
                                <option value="Sewa Tempat">Sewa Tempat</option>
                                <option value="Lainnya">Lainnya</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Tanggal Pengeluaran</label>
                            <input type="date" name="tgl_pengeluaran" class="form-control" required 
                                   value="<?= date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Keterangan (Opsional)</label>
                            <textarea name="keterangan_pengeluaran" class="form-control" rows="3" 
                                      placeholder="Tambahkan keterangan pengeluaran..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Batal
                        </button>
                        <button type="submit" name="tambah_pengeluaran" class="btn btn-warning">
                            <i class="fas fa-save"></i> Simpan Pengeluaran
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Simple Items Modal -->
    <div class="modal fade simple-items-modal" id="simpleItemsModal" tabindex="-1" aria-labelledby="simpleItemsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="simpleItemsModalLabel">
                        <i class="fas fa-tshirt"></i> Daftar Items
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="simple-items-list" id="simpleItemsList">
                        <!-- Items akan dimuat di sini -->
                    </div>
                    <div class="simple-total-items" id="simpleTotalItems">
                        Total: <span id="totalItemsCount">0</span> items
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Tutup
                    </button>
                </div>
            </div>
        </div>
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
                    <p>Apakah Anda yakin ingin menghapus transaksi untuk pelanggan <strong id="deleteCustomerName"></strong>?</p>
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

    <!-- Delete Confirmation Modal for Pengeluaran -->
    <div class="modal fade delete-modal" id="deletePengeluaranModal" tabindex="-1" aria-labelledby="deletePengeluaranModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deletePengeluaranModalLabel">Konfirmasi Penghapusan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="warning-icon">⚠️</div>
                    <h5 class="text-danger mb-2" style="font-size: 1.1rem;">Konfirmasi Penghapusan</h5>
                    <p>Apakah Anda yakin ingin menghapus pengeluaran <strong id="deletePengeluaranKategori"></strong>?</p>
                    <p class="text-muted small">Tindakan ini tidak dapat dibatalkan dan data akan hilang permanen.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <button type="button" class="btn btn-danger" id="confirmDeletePengeluaran">
                        <i class="fas fa-trash"></i> Ya, Hapus
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selectPesanan = document.getElementById('select_pesanan');
            const infoPesanan = document.getElementById('info_pesanan');
            const inputJumlahBayar = document.getElementById('input_jumlah_bayar');
            const maxJumlahBayar = document.getElementById('max_jumlah_bayar');
            const selectMetodeBayar = document.getElementById('select_metode_bayar');
            const formTambahTransaksi = document.getElementById('formTambahTransaksi');
            
            if (selectPesanan) {
                selectPesanan.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    if (selectedOption.value) {
                        const totalHarga = selectedOption.getAttribute('data-total-harga');
                        const totalBayar = selectedOption.getAttribute('data-total-bayar');
                        const sisaBayar = selectedOption.getAttribute('data-sisa-bayar');
                        const status = selectedOption.getAttribute('data-status');
                        const metodeBayar = selectedOption.getAttribute('data-metode-bayar');
                        const itemsJson = selectedOption.getAttribute('data-items');
                        const totalItems = selectedOption.getAttribute('data-total-items');
                        const firstItem = selectedOption.getAttribute('data-first-item');
                        
                        let statusText = '';
                        switch(status) {
                            case 'belum_bayar': statusText = 'Belum Bayar'; break;
                            case 'dp': statusText = 'DP'; break;
                            case 'lunas': statusText = 'Lunas'; break;
                        }
                        
                        // Mapping metode bayar untuk tampilan
                        let metodeText = metodeBayar;
                        switch(metodeBayar) {
                            case 'tunai': metodeText = 'Tunai'; break;
                            case 'transfer': metodeText = 'Transfer Bank'; break;
                            case 'qris': metodeText = 'QRIS'; break;
                            case 'kredit': metodeText = 'Kartu Kredit'; break;
                            case 'debit': metodeText = 'Kartu Debit'; break;
                            case 'Tunai': metodeText = 'Tunai'; break;
                            case 'Transfer': metodeText = 'Transfer Bank'; break;
                            case 'QRIS': metodeText = 'QRIS'; break;
                            case 'Kredit': metodeText = 'Kartu Kredit'; break;
                            case 'Debit': metodeText = 'Kartu Debit'; break;
                        }
                        
                        // Parsing items untuk info
                        let itemsHtml = '';
                        if (firstItem) {
                            itemsHtml = `<strong>${firstItem}</strong>`;
                            if (totalItems > 1) {
                                itemsHtml += ` <span class="text-primary">(${totalItems} items)</span>`;
                            }
                        }
                        
                        document.getElementById('info_total_harga').textContent = formatNumber(totalHarga);
                        document.getElementById('info_total_bayar').textContent = formatNumber(totalBayar);
                        document.getElementById('info_sisa_bayar').textContent = formatNumber(sisaBayar);
                        document.getElementById('info_status').textContent = statusText;
                        document.getElementById('info_metode_bayar').textContent = metodeText;
                        document.getElementById('info_items').innerHTML = itemsHtml;
                        maxJumlahBayar.textContent = formatNumber(sisaBayar);
                        
                        // Set default metode bayar berdasarkan pesanan
                        if (selectMetodeBayar) {
                            // Cari opsi yang sesuai dengan metode bayar pesanan
                            for (let i = 0; i < selectMetodeBayar.options.length; i++) {
                                if (selectMetodeBayar.options[i].value === metodeBayar || 
                                    selectMetodeBayar.options[i].value === metodeText) {
                                    selectMetodeBayar.value = selectMetodeBayar.options[i].value;
                                    break;
                                }
                            }
                        }
                        
                        // Set max value untuk input
                        inputJumlahBayar.max = sisaBayar;
                        inputJumlahBayar.value = sisaBayar > 0 ? sisaBayar : totalHarga;
                        
                        infoPesanan.style.display = 'block';
                    } else {
                        infoPesanan.style.display = 'none';
                    }
                });
            }
            
            // Validasi form sebelum submit
            if (formTambahTransaksi) {
                formTambahTransaksi.addEventListener('submit', function(e) {
                    const jumlahBayar = parseFloat(inputJumlahBayar.value);
                    const sisaBayar = parseFloat(inputJumlahBayar.max);
                    
                    if (jumlahBayar > sisaBayar) {
                        e.preventDefault();
                        alert('Jumlah bayar tidak boleh melebihi sisa bayar!');
                        inputJumlahBayar.focus();
                        return false;
                    }
                    
                    if (jumlahBayar <= 0) {
                        e.preventDefault();
                        alert('Jumlah bayar harus lebih dari 0!');
                        inputJumlahBayar.focus();
                        return false;
                    }
                });
            }
            
            function formatNumber(number) {
                return new Intl.NumberFormat('id-ID').format(number);
            }
        });
        
        // Variabel global untuk menyimpan data hapus
        let currentDeleteId = null;
        let currentDeleteName = null;
        let currentDeletePengeluaranId = null;
        let currentDeletePengeluaranKategori = null;
        let simpleItemsModal = null;

        // Inisialisasi modal
        document.addEventListener('DOMContentLoaded', function() {
            simpleItemsModal = new bootstrap.Modal(document.getElementById('simpleItemsModal'));
        });

        // Format number ke format Indonesia
        function formatNumber(number) {
            return new Intl.NumberFormat('id-ID').format(number);
        }
        
        // Format Rupiah
        function formatRupiah(number) {
            return new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                minimumFractionDigits: 0
            }).format(number);
        }

        // Fungsi untuk menampilkan modal items yang sederhana
        function showSimpleItemsModal(pesananId, totalItems) {
            // Set total items
            document.getElementById('totalItemsCount').textContent = totalItems;
            
            // Tampilkan modal
            simpleItemsModal.show();
            
            // Tampilkan loading
            const itemsContainer = document.getElementById('simpleItemsList');
            itemsContainer.innerHTML = `
                <div class="text-center p-3">
                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                        <span class="visually-hidden">Memuat...</span>
                    </div>
                    <p class="mt-2 text-muted" style="font-size: 0.7rem;">Memuat daftar items...</p>
                </div>
            `;
            
            // Ambil data items via AJAX
            fetch(`get_pesanan_items.php?id=${pesananId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.items && data.items.length > 0) {
                        renderSimpleItems(data.items);
                    } else {
                        // Tidak ada data items, tampilkan informasi
                        itemsContainer.innerHTML = `
                            <div class="text-center p-3">
                                <i class="fas fa-box-open fa-lg text-muted mb-3"></i>
                                <p class="text-muted" style="font-size: 0.7rem;">Tidak ada data items ditemukan</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    itemsContainer.innerHTML = `
                        <div class="text-center p-3">
                            <i class="fas fa-exclamation-triangle fa-lg text-danger mb-3"></i>
                            <p class="text-danger" style="font-size: 0.7rem;">Gagal memuat data items</p>
                        </div>
                    `;
                });
        }

        // Fungsi helper untuk render items sederhana
        function renderSimpleItems(items) {
            const itemsContainer = document.getElementById('simpleItemsList');
            
            // Buat HTML untuk items
            let itemsHTML = '';
            items.forEach((item, index) => {
                itemsHTML += `
                    <div class="simple-item">
                        <div class="simple-item-number">${index + 1}</div>
                        <div class="simple-item-name">
                            ${item.jenis_pakaian || 'Pakaian'}
                        </div>
                    </div>
                `;
            });
            
            // Update items container
            itemsContainer.innerHTML = itemsHTML;
        }
        
        // Tampilkan tooltip statistik detail yang disederhanakan
        function showStatTooltip(statType, element) {
            const tooltip = document.getElementById('statTooltip');
            if (!tooltip) return;
            
            let title = '';
            let content = '';
            
            <?php if (!empty($statistik_detail)): ?>
            const totalOmzet = <?= $statistik_detail['total_omzet'] ?? 0; ?>;
            const totalUangMasuk = <?= $statistik_detail['total_uang_masuk'] ?? 0; ?>;
            const totalPiutang = <?= $statistik_detail['total_piutang'] ?? 0; ?>;
            const totalPengeluaran = <?= $statistik_detail['total_pengeluaran'] ?? 0; ?>;
            const kasBersih = <?= $statistik_detail['kas_bersih'] ?? 0; ?>;
            
            switch(statType) {
                case 'omzet':
                    title = 'Detail Total Omzet';
                    content = `
                        <div class="stat-tooltip-item">
                            <span class="stat-tooltip-label">
                                <i class="fas fa-chart-line"></i> Total Omzet Pesanan
                            </span>
                            <span class="stat-tooltip-value positive">${formatRupiah(totalOmzet)}</span>
                        </div>
                        <div class="stat-tooltip-item">
                            <span class="stat-tooltip-label">
                                <i class="fas fa-hand-holding-usd"></i> Uang Masuk
                            </span>
                            <span class="stat-tooltip-value">${formatRupiah(totalUangMasuk)}</span>
                        </div>
                        <div class="stat-tooltip-item">
                            <span class="stat-tooltip-label">
                                <i class="fas fa-clock"></i> Piutang
                            </span>
                            <span class="stat-tooltip-value negative">${formatRupiah(totalPiutang)}</span>
                        </div>
                        <div class="stat-tooltip-item">
                            <span class="stat-tooltip-label">
                                <i class="fas fa-shopping-cart"></i> Total Pesanan
                            </span>
                            <span class="stat-tooltip-value neutral"><?= $count_pesanan_belum_bayar + $count_pesanan_dp + $count_pesanan_lunas; ?> pesanan</span>
                        </div>
                    `;
                    break;
                    
                case 'uang-masuk':
                    title = 'Detail Uang Masuk';
                    content = `
                        <div class="stat-tooltip-item">
                            <span class="stat-tooltip-label">
                                <i class="fas fa-hand-holding-usd"></i> Total Uang Masuk
                            </span>
                            <span class="stat-tooltip-value positive">${formatRupiah(totalUangMasuk)}</span>
                        </div>
                        <div class="stat-tooltip-divider"></div>
                        <div class="stat-tooltip-item">
                            <span class="stat-tooltip-label">
                                <i class="fas fa-check-circle"></i> Pesanan Lunas
                            </span>
                            <span class="stat-tooltip-value positive"><?= $count_pesanan_lunas; ?> pesanan</span>
                        </div>
                        <div class="stat-tooltip-item">
                            <span class="stat-tooltip-label">
                                <i class="fas fa-money-check-alt"></i> Pesanan DP
                            </span>
                            <span class="stat-tooltip-value neutral"><?= $count_pesanan_dp; ?> pesanan</span>
                        </div>
                    `;
                    break;
                    
                case 'kas-bersih':
                    title = 'Detail Kas Bersih';
                    content = `
                        <div class="stat-tooltip-item">
                            <span class="stat-tooltip-label">
                                <i class="fas fa-hand-holding-usd"></i> Total Uang Masuk
                            </span>
                            <span class="stat-tooltip-value positive">${formatRupiah(totalUangMasuk)}</span>
                        </div>
                        <div class="stat-tooltip-item">
                            <span class="stat-tooltip-label">
                                <i class="fas fa-minus-circle"></i> Total Pengeluaran
                            </span>
                            <span class="stat-tooltip-value negative">${formatRupiah(totalPengeluaran)}</span>
                        </div>
                        <div class="stat-tooltip-divider"></div>
                        <div class="stat-tooltip-item" style="background: ${kasBersih >= 0 ? '#f0fdf4' : '#fef2f2'}; padding: 0.5rem; border-radius: 6px; margin: 0.5rem 0;">
                            <span class="stat-tooltip-label" style="color: ${kasBersih >= 0 ? '#065f46' : '#991b1b'}; font-weight: 700;">
                                <i class="fas fa-balance-scale"></i> <strong>Kas Bersih:</strong>
                            </span>
                            <span class="stat-tooltip-value" style="font-size: 1rem; color: ${kasBersih >= 0 ? '#059669' : '#dc2626'}; font-weight: 700;">
                                ${formatRupiah(kasBersih)}
                            </span>
                        </div>
                        <div class="stat-tooltip-footer">
                            Kas Bersih = Uang Masuk - Pengeluaran
                        </div>
                    `;
                    break;
                    
                case 'piutang':
                    title = 'Detail Piutang';
                    content = `
                        <div class="stat-tooltip-item">
                            <span class="stat-tooltip-label">
                                <i class="fas fa-file-invoice-dollar"></i> Total Piutang
                            </span>
                            <span class="stat-tooltip-value negative">${formatRupiah(totalPiutang)}</span>
                        </div>
                        <div class="stat-tooltip-divider"></div>
                        <div class="stat-tooltip-item">
                            <span class="stat-tooltip-label">
                                <i class="fas fa-info-circle"></i> Status
                            </span>
                            <span class="stat-tooltip-value neutral">${totalPiutang > 0 ? 'Ada Piutang Tertunggak' : 'Tidak Ada Piutang'}</span>
                        </div>
                    `;
                    break;
                    
                case 'pengeluaran':
                    title = 'Detail Pengeluaran';
                    
                    // Kategori pengeluaran
                    let kategoriHtml = '';
                    <?php if (!empty($kategori_pengeluaran)): ?>
                    <?php foreach ($kategori_pengeluaran as $kategori): ?>
                    kategoriHtml += `
                        <div class="stat-tooltip-kategori-item">
                            <span>${'<?= htmlspecialchars($kategori['kategori_pengeluaran']); ?>'}</span>
                            <span>${formatRupiah(<?= $kategori['total'] ?? 0; ?>)}</span>
                        </div>
                    `;
                    <?php endforeach; ?>
                    <?php endif; ?>
                    
                    content = `
                        <div class="stat-tooltip-item">
                            <span class="stat-tooltip-label">
                                <i class="fas fa-receipt"></i> Total Pengeluaran
                            </span>
                            <span class="stat-tooltip-value negative">${formatRupiah(totalPengeluaran)}</span>
                        </div>
                        ${kategoriHtml ? `
                        <div class="stat-tooltip-kategori">
                            <div style="font-weight: 600; color: #374151; margin-bottom: 0.5rem; font-size: 0.75rem;">
                                <i class="fas fa-folder-open"></i> Kategori Pengeluaran
                            </div>
                            ${kategoriHtml}
                        </div>
                        ` : ''}
                    `;
                    break;
            }
            <?php endif; ?>
            
            if (content) {
                tooltip.innerHTML = `<h6>${title}</h6>${content}`;
                tooltip.style.display = 'block';
                
                // Posisi tooltip
                const rect = element.getBoundingClientRect();
                const viewportWidth = window.innerWidth;
                const viewportHeight = window.innerHeight;
                const tooltipWidth = 320;
                const tooltipHeight = tooltip.offsetHeight;
                
                // Hitung posisi optimal
                let left = rect.left + rect.width / 2 - tooltipWidth / 2;
                let top = rect.bottom + 10;
                
                // Cek jika tooltip keluar dari viewport di kanan
                if (left + tooltipWidth > viewportWidth - 10) {
                    left = viewportWidth - tooltipWidth - 10;
                }
                
                // Cek jika tooltip keluar dari viewport di kiri
                if (left < 10) {
                    left = 10;
                }
                
                // Cek jika tooltip keluar dari viewport di bawah
                if (top + tooltipHeight > viewportHeight - 10) {
                    top = rect.top - tooltipHeight - 10;
                }
                
                tooltip.style.left = left + 'px';
                tooltip.style.top = top + 'px';
            }
        }
        
        // Sembunyikan tooltip
        function hideStatTooltip() {
            const tooltip = document.getElementById('statTooltip');
            if (tooltip) {
                tooltip.style.display = 'none';
            }
        }

        // Tampilkan modal konfirmasi hapus
        document.addEventListener('DOMContentLoaded', function() {
            // Setup tooltip untuk statistik
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach(card => {
                // Klik untuk tampilkan tooltip
                card.addEventListener('click', function(e) {
                    const statType = this.getAttribute('data-stat');
                    showStatTooltip(statType, this);
                });
                
                // Hover untuk highlight
                card.addEventListener('mouseenter', function(e) {
                    // Highlight efek saja, tidak menampilkan tooltip
                    this.style.transform = 'translateY(-3px)';
                    this.style.boxShadow = '0 6px 15px rgba(0,0,0,0.15)';
                });
                
                card.addEventListener('mouseleave', function(e) {
                    // Cek jika mouse masuk ke tooltip
                    const tooltip = document.getElementById('statTooltip');
                    const isHoveringTooltip = tooltip && tooltip.matches(':hover');
                    
                    if (!isHoveringTooltip) {
                        this.style.transform = '';
                        this.style.boxShadow = '';
                        hideStatTooltip();
                    }
                });
            });
            
            // Sembunyikan tooltip jika mouse keluar dari tooltip
            const tooltip = document.getElementById('statTooltip');
            if (tooltip) {
                tooltip.addEventListener('mouseleave', function(e) {
                    hideStatTooltip();
                    // Reset transform stat cards
                    statCards.forEach(card => {
                        card.style.transform = '';
                        card.style.boxShadow = '';
                    });
                });
            }
            
            // Delete transaksi
            const deleteButtons = document.querySelectorAll('.delete-btn');
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            const deleteCustomerNameElement = document.getElementById('deleteCustomerName');
            
            deleteButtons.forEach(button => {
                button.addEventListener('click', function() {
                    currentDeleteId = this.getAttribute('data-id');
                    currentDeleteName = this.getAttribute('data-pelanggan');
                    
                    deleteCustomerNameElement.textContent = currentDeleteName !== '-' ? currentDeleteName : 'ini';
                    deleteModal.show();
                });
            });

            // Delete pengeluaran
            const deletePengeluaranButtons = document.querySelectorAll('.delete-pengeluaran-btn');
            const deletePengeluaranModal = new bootstrap.Modal(document.getElementById('deletePengeluaranModal'));
            const deletePengeluaranKategoriElement = document.getElementById('deletePengeluaranKategori');
            
            deletePengeluaranButtons.forEach(button => {
                button.addEventListener('click', function() {
                    currentDeletePengeluaranId = this.getAttribute('data-id');
                    currentDeletePengeluaranKategori = this.getAttribute('data-kategori');
                    
                    deletePengeluaranKategoriElement.textContent = currentDeletePengeluaranKategori;
                    deletePengeluaranModal.show();
                });
            });

            // Konfirmasi hapus transaksi
            document.getElementById('confirmDelete').addEventListener('click', function() {
                window.location.href = `transaksi.php?hapus=${currentDeleteId}`;
            });

            // Konfirmasi hapus pengeluaran
            document.getElementById('confirmDeletePengeluaran').addEventListener('click', function() {
                window.location.href = `transaksi.php?hapus_pengeluaran=${currentDeletePengeluaranId}&tab=pengeluaran`;
            });

            // Highlight table rows on hover
            const tableRows = document.querySelectorAll('#transaksiTable tbody tr, #pengeluaranTable tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#f8faff';
                });
                row.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = '';
                });
            });
        });
    </script>
</body>
</html>