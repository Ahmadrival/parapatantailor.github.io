<?php
// get_pesanan_items.php
require_once '../config/database.php';
header('Content-Type: application/json; charset=utf-8');

// Pastikan user telah login
check_login();
check_role(['admin', 'pegawai']);

// Ambil ID pesanan
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID pesanan tidak disediakan']);
    exit();
}

$id_pesanan = clean_input($_GET['id']);

try {
    // Coba ambil items dari tabel pesanan_items
    $sql_items = "SELECT * FROM pesanan_items WHERE id_pesanan = ? ORDER BY id_item ASC";
    $items = getAll($sql_items, [$id_pesanan]);

    // Fallback jika tabel pesanan_items kosong / belum ada data
    if (empty($items)) {
        // Coba ambil data utama pesanan untuk kompatibilitas
        $sql_pesanan = "SELECT jenis_pakaian, bahan, total_harga, jumlah_bayar, catatan FROM data_pesanan WHERE id_pesanan = ?";
        $pesanan = getSingle($sql_pesanan, [$id_pesanan]);

        if ($pesanan && !empty($pesanan['jenis_pakaian'])) {
            $items = [[
                'id_item' => 1,
                'jenis_pakaian' => $pesanan['jenis_pakaian'],
                'bahan' => $pesanan['bahan'] ?? '',
                'jumlah' => 1,
                'harga_satuan' => $pesanan['total_harga'] ?? 0,
                'catatan' => $pesanan['catatan'] ?? ''
            ]];
        }
    }

    // Hitung ringkasan
    $total_kuantitas = 0;
    $total_harga = 0;
    foreach ($items as $it) {
        $qty = isset($it['jumlah']) ? intval($it['jumlah']) : 1;
        $harga = isset($it['harga_satuan']) ? floatval($it['harga_satuan']) : (isset($it['harga']) ? floatval($it['harga']) : 0);
        $total_kuantitas += $qty;
        $total_harga += ($harga * $qty);
    }

    echo json_encode([
        'success' => true,
        'items' => $items,
        'summary' => [
            'total_items' => count($items),
            'total_kuantitas' => $total_kuantitas,
            'total_harga' => $total_harga
        ]
    ]);
    exit();

} catch (PDOException $e) {
    error_log('get_pesanan_items error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Gagal memuat data items']);
    exit();
}
