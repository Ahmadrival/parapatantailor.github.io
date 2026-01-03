<?php
// ajax_tambah_pelanggan.php
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    header('Content-Type: application/json');
    
    $nama = clean_input($_POST['nama'] ?? '');
    $telepon = clean_input($_POST['telepon'] ?? '');
    $alamat = clean_input($_POST['alamat'] ?? '');
    
    if (empty($nama)) {
        echo json_encode(['success' => false, 'message' => 'Nama pelanggan harus diisi']);
        exit;
    }
    
    try {
        $sql = "INSERT INTO data_pelanggan (nama, no_hp, alamat, created_at) VALUES (?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nama, $telepon, $alamat]);
        
        $id_pelanggan = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'id' => $id_pelanggan,
            'nama' => $nama,
            'telepon' => $telepon
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan pelanggan: ' . $e->getMessage()]);
    }
}
?>