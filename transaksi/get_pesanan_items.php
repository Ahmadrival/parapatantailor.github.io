<?php
// get_pesanan_items.php - Versi Sederhana (tanpa harga)
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID pesanan tidak valid']);
    exit;
}

$id_pesanan = clean_input($_GET['id']);

try {
    // Ambil data items pesanan - HANYA AMBIL JENIS PAKAIAN SAJA
    $sql_items = "SELECT jenis_pakaian, jumlah FROM pesanan_items WHERE id_pesanan = ? ORDER BY id_item ASC";
    $items = getAll($sql_items, [$id_pesanan]);
    
    // Jika tidak ada items, coba ambil dari data lama
    if (empty($items)) {
        $sql_pesanan = "SELECT jenis_pakaian FROM data_pesanan WHERE id_pesanan = ?";
        $pesanan = getSingle($sql_pesanan, [$id_pesanan]);
        
        if ($pesanan && !empty($pesanan['jenis_pakaian'])) {
            // Buat satu item dari data lama
            $items = [[
                'jenis_pakaian' => $pesanan['jenis_pakaian'],
                'jumlah' => 1
            ]];
        }
    }
    
    // Format sederhana hanya nama item dan jumlah
    $formatted_items = [];
    foreach ($items as $item) {
        $formatted_items[] = [
            'jenis_pakaian' => $item['jenis_pakaian'],
            'jumlah' => $item['jumlah'] ?? 1
        ];
    }
    
    echo json_encode([
        'success' => true,
        'items' => $formatted_items,
        'total' => count($formatted_items)
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Gagal memuat data items: ' . $e->getMessage()
    ]);
}
?>