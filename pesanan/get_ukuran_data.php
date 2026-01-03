<?php
// pesanan/get_ukuran_data.php
require_once '../config/database.php';
check_login();
check_role(['admin', 'pemilik']);

header('Content-Type: application/json');

if (!isset($_GET['id_pesanan']) || !isset($_GET['jenis'])) {
    echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap']);
    exit();
}

$id_pesanan = clean_input($_GET['id_pesanan']);
$jenis_ukuran = clean_input($_GET['jenis']);

try {
    $html = '';
    
    if ($jenis_ukuran === 'standar') {
        // Ambil data ukuran standar
        $sql = "SELECT p.*, u.nama_ukuran, u.deskripsi 
                FROM data_pesanan p 
                LEFT JOIN data_ukuran u ON p.id_ukuran = u.id_ukuran 
                WHERE p.id_pesanan = ?";
        $pesanan = getSingle($sql, [$id_pesanan]);
        
        if ($pesanan && $pesanan['id_ukuran']) {
            $html = '
            <div class="ukuran-section">
                <h5 class="section-title-ukuran">
                    <i class="fas fa-ruler-combined"></i> Ukuran Standar
                </h5>
                <div class="ukuran-grid">
                    <div class="ukuran-item">
                        <div class="ukuran-label">Nama Ukuran</div>
                        <div class="ukuran-value">' . htmlspecialchars($pesanan['nama_ukuran']) . '</div>
                    </div>
                </div>';
                
            if (!empty($pesanan['deskripsi'])) {
                $html .= '
                <div class="keterangan-ukuran">
                    <div class="ukuran-label"><i class="fas fa-info-circle"></i> Deskripsi</div>
                    <div>' . htmlspecialchars($pesanan['deskripsi']) . '</div>
                </div>';
            }
            
            $html .= '</div>';
        } else {
            $html = '<div class="empty-custom-ukuran">Tidak menggunakan ukuran standar</div>';
        }
        
    } elseif ($jenis_ukuran === 'atasan') {
        // Ambil data ukuran atasan
        $sql = "SELECT * FROM ukuran_atasan WHERE id_pesanan = ?";
        $ukuran = getSingle($sql, [$id_pesanan]);
        
        if ($ukuran) {
            $html = '
            <div class="ukuran-custom-section">
                <span class="ukuran-type-badge">Ukuran Atasan (Kustom)</span>
                <div class="ukuran-custom-grid">';
            
            $fields = [
                'krah' => 'Krah (cm)',
                'pundak' => 'Pundak (cm)',
                'tangan' => 'Tangan (cm)',
                'ld_lp' => 'LD/LP (cm)',
                'badan' => 'Badan (cm)',
                'pinggang' => 'Pinggang (cm)',
                'pinggul' => 'Pinggul (cm)',
                'panjang' => 'Panjang (cm)'
            ];
            
            foreach ($fields as $field => $label) {
                if (!empty($ukuran[$field])) {
                    $html .= '
                    <div class="ukuran-custom-item">
                        <div class="ukuran-custom-label">' . $label . '</div>
                        <div class="ukuran-custom-value">' . $ukuran[$field] . '</div>
                    </div>';
                }
            }
            
            $html .= '</div>';
            
            if (!empty($ukuran['keterangan'])) {
                $html .= '
                <div class="keterangan-ukuran">
                    <div class="ukuran-label"><i class="fas fa-sticky-note"></i> Keterangan</div>
                    <div>' . htmlspecialchars($ukuran['keterangan']) . '</div>
                </div>';
            }
            
            $html .= '</div>';
        } else {
            $html = '<div class="empty-custom-ukuran">Data ukuran atasan tidak ditemukan</div>';
        }
        
    } elseif ($jenis_ukuran === 'bawahan') {
        // Ambil data ukuran bawahan
        $sql = "SELECT * FROM ukuran_bawahan WHERE id_pesanan = ?";
        $ukuran = getSingle($sql, [$id_pesanan]);
        
        if ($ukuran) {
            $html = '
            <div class="ukuran-custom-section">
                <span class="ukuran-type-badge">Ukuran Bawahan (Kustom)</span>
                <div class="ukuran-custom-grid">';
            
            $fields = [
                'pinggang' => 'Pinggang (cm)',
                'pinggul' => 'Pinggul (cm)',
                'kres' => 'Kres (cm)',
                'paha' => 'Paha (cm)',
                'lutut' => 'Lutut (cm)',
                'l_bawah' => 'L. Bawah (cm)',
                'panjang' => 'Panjang (cm)'
            ];
            
            foreach ($fields as $field => $label) {
                if (!empty($ukuran[$field])) {
                    $html .= '
                    <div class="ukuran-custom-item">
                        <div class="ukuran-custom-label">' . $label . '</div>
                        <div class="ukuran-custom-value">' . $ukuran[$field] . '</div>
                    </div>';
                }
            }
            
            $html .= '</div>';
            
            if (!empty($ukuran['keterangan'])) {
                $html .= '
                <div class="keterangan-ukuran">
                    <div class="ukuran-label"><i class="fas fa-sticky-note"></i> Keterangan</div>
                    <div>' . htmlspecialchars($ukuran['keterangan']) . '</div>
                </div>';
            }
            
            $html .= '</div>';
        } else {
            $html = '<div class="empty-custom-ukuran">Data ukuran bawahan tidak ditemukan</div>';
        }
        
    } else {
        $html = '<div class="empty-custom-ukuran">Tidak ada data ukuran yang tersedia</div>';
    }
    
    echo json_encode(['success' => true, 'html' => $html]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>