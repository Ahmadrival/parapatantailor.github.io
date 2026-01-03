<?php
// export_pdf.php - VERSION SUPER CLEAN WITH PURPLE THEME

// ============================================================================
// PHASE 1: CLEAN OUTPUT BUFFER DAN CHECK SESSION
// ============================================================================

// Clean semua output buffer
while (ob_get_level()) {
    ob_end_clean();
}

// Start fresh output buffer
ob_start();

// Include database first - pastikan tidak ada spasi/echo sebelum ini
require_once '../config/database.php';

// Start session dengan cara yang clean
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check login dan role
check_login();
check_role(['admin', 'pemilik']);

// ============================================================================
// PHASE 2: AMBIL DATA USER DARI SESSION ATAU DATABASE
// ============================================================================

// Ambil user_id dari session
$user_id = $_SESSION['user_id'] ?? null;

// Ambil nama lengkap user dari database jika user_id tersedia
$nama_cetak = 'System'; // default

if ($user_id) {
    try {
        // Query untuk mendapatkan nama lengkap user
        $sql_user = "SELECT nama_lengkap FROM users WHERE id = ?";
        $stmt = $pdo->prepare($sql_user);
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user_data && !empty($user_data['nama_lengkap'])) {
            $nama_cetak = $user_data['nama_lengkap'];
        } else {
            // Fallback ke username jika nama_lengkap tidak ada
            $nama_cetak = $_SESSION['username'] ?? 'System';
        }
    } catch (PDOException $e) {
        // Jika error, gunakan nama dari session jika ada
        $nama_cetak = $_SESSION['nama_lengkap'] ?? ($_SESSION['username'] ?? 'System');
    }
} else {
    // Fallback ke session jika user_id tidak ada
    $nama_cetak = $_SESSION['nama_lengkap'] ?? ($_SESSION['nama'] ?? ($_SESSION['username'] ?? 'System'));
}

// ============================================================================
// PHASE 3: INCLUDE TCPDF DENGAN ERROR HANDLING
// ============================================================================

$tcpdf_paths = [
    '../TCPDF/tcpdf.php',
    '../../TCPDF/tcpdf.php', 
    'TCPDF/tcpdf.php',
    '../tcpdf/tcpdf.php',
    '../../tcpdf/tcpdf.php',
    '../../../TCPDF/tcpdf.php'
];

$tcpdf_loaded = false;
foreach ($tcpdf_paths as $path) {
    if (file_exists($path)) {
        // Clean buffer sebelum include
        ob_clean();
        require_once $path;
        $tcpdf_loaded = true;
        break;
    }
}

if (!$tcpdf_loaded) {
    // Clean output sebelum error message
    ob_clean();
    header('Content-Type: text/plain; charset=utf-8');
    die("ERROR: TCPDF library tidak ditemukan.\n\nPath yang dicoba:\n- " . implode("\n- ", $tcpdf_paths) . "\n\nPastikan folder TCPDF ada di project Anda.");
}

// ============================================================================
// PHASE 4: AMBIL PARAMETER DAN DATA
// ============================================================================

// Ambil parameter dengan sanitization
$type = isset($_GET['type']) ? clean_input($_GET['type']) : 'keuangan';
$start_date = isset($_GET['start_date']) ? clean_input($_GET['start_date']) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? clean_input($_GET['end_date']) : date('Y-m-t');

// Validasi jenis laporan
$allowed_types = ['keuangan', 'pesanan', 'pelanggan'];
if (!in_array($type, $allowed_types)) {
    $type = 'keuangan';
}

// Get data dari database
$data = getReportData($type, $start_date, $end_date);

// ============================================================================
// PHASE 5: BUAT PDF - FINAL CLEAN BEFORE OUTPUT
// ============================================================================

// Final clean sebelum membuat PDF
ob_clean();

// Create PDF instance
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Parapatan Tailor');
$pdf->SetAuthor('Parapatan Tailor');
$pdf->SetTitle('Laporan ' . ucfirst($type));
$pdf->SetSubject('Laporan ' . ucfirst($type));

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set margins
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(TRUE, 15);

// Add a page
$pdf->AddPage();

// ============================================================================
// PHASE 6: GENERATE CONTENT BERDASARKAN JENIS LAPORAN DENGAN THEME UNGU
// ============================================================================

// Define purple color scheme
$primary_purple = array(102, 51, 153);    // #663399 - Dark Purple
$secondary_purple = array(147, 112, 219); // #9370DB - Medium Purple
$light_purple = array(216, 191, 216);     // #D8BFD8 - Light Purple
$accent_purple = array(186, 85, 211);     // #BA55D3 - Orchid
$very_light_purple = array(248, 245, 250); // #F8F5FA - Very Light Purple

// Header dengan gradient background (simulasi)
$pdf->SetFillColor($primary_purple[0], $primary_purple[1], $primary_purple[2]);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 18);
$pdf->Cell(0, 12, 'LAPORAN ' . strtoupper($type), 0, 1, 'C', true);
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 8, 'PARAPATAN TAILOR', 0, 1, 'C', true);

// Garis dekoratif
$pdf->SetFillColor($accent_purple[0], $accent_purple[1], $accent_purple[2]);
$pdf->Cell(0, 2, '', 0, 1, 'C', true);
$pdf->Ln(3);

// Informasi perusahaan dengan background ungu muda
$pdf->SetFillColor($very_light_purple[0], $very_light_purple[1], $very_light_purple[2]);
$pdf->SetDrawColor($light_purple[0], $light_purple[1], $light_purple[2]);
$pdf->SetTextColor($primary_purple[0], $primary_purple[1], $primary_purple[2]);
$pdf->SetFont('helvetica', '', 9);

// Background untuk informasi perusahaan
$pdf->RoundedRect(15, $pdf->GetY(), 180, 20, 3, '1111', 'F');
$pdf->SetY($pdf->GetY() + 3);

// Informasi alamat dan kontak
$pdf->Cell(0, 5, 'Jl. Baru Awirarangan No.123, Kecamatan Kuningan, Kabupaten Kuningan, Provinsi Jawa Barat', 0, 1, 'C');
$pdf->Cell(0, 5, 'info@parapatantailor.com', 0, 1, 'C');
$pdf->SetY($pdf->GetY() + 2);

$pdf->Ln(5);

// Informasi cetak - DIPERBAIKI DENGAN $nama_cetak
$pdf->SetTextColor(80, 80, 80);
$pdf->Cell(0, 5, 'Periode: ' . date('d/m/Y', strtotime($start_date)) . ' - ' . date('d/m/Y', strtotime($end_date)), 0, 1, 'C');
$pdf->Cell(0, 5, 'Dicetak pada: ' . date('d/m/Y H:i:s'), 0, 1, 'C');
$pdf->Cell(0, 5, 'Oleh: ' . $nama_cetak, 0, 1, 'C');

$pdf->Ln(8);

// Generate content berdasarkan type
switch($type) {
    case 'keuangan':
        generateKeuanganContent($pdf, $data, $start_date, $end_date);
        break;
    case 'pesanan':
        generatePesananContent($pdf, $data, $start_date, $end_date);
        break;
    case 'pelanggan':
        generatePelangganContent($pdf, $data, $start_date, $end_date);
        break;
}

// Footer dengan style ungu
$pdf->Ln(15);
$pdf->SetFont('helvetica', 'I', 8);
$pdf->SetTextColor(120, 120, 120);

// Garis pemisah footer
$pdf->SetDrawColor($light_purple[0], $light_purple[1], $light_purple[2]);
$pdf->Cell(0, 0, '', 'T', 1);
$pdf->Ln(3);

$pdf->Cell(0, 4, 'Dokumen ini dicetak secara otomatis dari Sistem Parapatan Tailor', 0, 1, 'C');
$pdf->SetTextColor($secondary_purple[0], $secondary_purple[1], $secondary_purple[2]);
$pdf->Cell(0, 4, 'Halaman 1 | ' . date('d/m/Y H:i:s'), 0, 1, 'C');

// ============================================================================
// PHASE 7: WATERMARK YANG DIPERBAIKI - TULISAN ATAS BAWAH
// ============================================================================

// Watermark yang lebih jelas dengan tulisan atas bawah
$pdf->SetAlpha(0.12); // Meningkatkan opacity menjadi 12%
$pdf->SetFont('helvetica', 'B', 65);
$pdf->SetTextColor($primary_purple[0], $primary_purple[1], $primary_purple[2]);

// Rotasi 45 derajat untuk watermark diagonal
$pdf->Rotate(30, 105, 150);

// Tulisan "PARAPATAN" di atas
$pdf->Text(50, 90, 'PARAPATAN');

// Tulisan "TAILOR" di bawah (dengan jarak yang cukup)
$pdf->Text(80, 120, 'TAILOR');

// Kembalikan rotasi ke normal
$pdf->Rotate(0);
$pdf->SetAlpha(1);

// ============================================================================
// PHASE 8: OUTPUT PDF - FINAL STEP
// ============================================================================

// Clean buffer terakhir kali sebelum output
ob_clean();

// Output PDF ke browser
$pdf->Output('Laporan_' . ucfirst($type) . '_' . date('Y-m-d') . '.pdf', 'I');

// Exit untuk memastikan tidak ada output tambahan
exit;

// ============================================================================
// FUNCTION DEFINITIONS
// ============================================================================

function getReportData($type, $start_date, $end_date) {
    global $pdo; // Ganti dari $db menjadi $pdo untuk konsistensi
    
    try {
        switch($type) {
            case 'keuangan':
                // Total Pendapatan dari transaksi pembayaran pesanan
                $sql_pendapatan = "SELECT COALESCE(SUM(jumlah_bayar), 0) as total_pendapatan 
                                  FROM data_transaksi 
                                  WHERE DATE(created_at) BETWEEN ? AND ?";
                $total_pendapatan = getSingle($sql_pendapatan, [$start_date, $end_date])['total_pendapatan'];
                
                // Total Pemasukan dari tabel pemasukan (non-pesanan)
                $sql_pemasukan = "SELECT COALESCE(SUM(jumlah_pemasukan), 0) as total_pemasukan 
                                 FROM data_pemasukan 
                                 WHERE DATE(tgl_pemasukan) BETWEEN ? AND ?";
                $total_pemasukan = getSingle($sql_pemasukan, [$start_date, $end_date])['total_pemasukan'];
                
                // Total Pengeluaran
                $sql_pengeluaran = "SELECT COALESCE(SUM(jumlah_pengeluaran), 0) as total_pengeluaran 
                                   FROM data_pengeluaran 
                                   WHERE DATE(tgl_pengeluaran) BETWEEN ? AND ?";
                $total_pengeluaran = getSingle($sql_pengeluaran, [$start_date, $end_date])['total_pengeluaran'];
                
                // Detail pengeluaran
                $sql_detail_pengeluaran = "SELECT * FROM data_pengeluaran 
                                          WHERE DATE(tgl_pengeluaran) BETWEEN ? AND ?
                                          ORDER BY tgl_pengeluaran DESC";
                $pengeluaran = getAll($sql_detail_pengeluaran, [$start_date, $end_date]);
                
                // Detail pemasukan (non-pesanan)
                $sql_detail_pemasukan = "SELECT * FROM data_pemasukan 
                                        WHERE DATE(tgl_pemasukan) BETWEEN ? AND ?
                                        ORDER BY tgl_pemasukan DESC";
                $pemasukan = getAll($sql_detail_pemasukan, [$start_date, $end_date]);
                
                return [
                    'total_pendapatan' => $total_pendapatan,
                    'total_pemasukan' => $total_pemasukan,
                    'total_pengeluaran' => $total_pengeluaran,
                    'pengeluaran' => $pengeluaran,
                    'pemasukan' => $pemasukan
                ];
                
            case 'pesanan':
                $sql_pesanan = "SELECT p.*, pel.nama as nama_pelanggan, k.nama_karyawan
                                FROM data_pesanan p
                                LEFT JOIN data_pelanggan pel ON p.id_pelanggan = pel.id_pelanggan
                                LEFT JOIN data_karyawan k ON p.id_karyawan = k.id_karyawan
                                WHERE DATE(p.tgl_pesanan) BETWEEN ? AND ?
                                ORDER BY p.tgl_pesanan DESC";
                $pesanan = getAll($sql_pesanan, [$start_date, $end_date]);
                
                $sql_stat_pesanan = "SELECT 
                                     COUNT(*) as total_pesanan,
                                     SUM(total_harga) as total_omzet
                                     FROM data_pesanan 
                                     WHERE DATE(tgl_pesanan) BETWEEN ? AND ?";
                $stat = getSingle($sql_stat_pesanan, [$start_date, $end_date]);
                
                return [
                    'pesanan' => $pesanan,
                    'statistics' => $stat
                ];
                
            case 'pelanggan':
                // Query untuk mendapatkan data pelanggan dengan total pesanan dan total belanja
                $sql_pelanggan = "SELECT 
                                 pel.*, 
                                 COUNT(p.id_pesanan) as total_pesanan,
                                 COALESCE(SUM(p.total_harga), 0) as total_belanja
                                 FROM data_pelanggan pel
                                 LEFT JOIN data_pesanan p ON pel.id_pelanggan = p.id_pelanggan
                                 GROUP BY pel.id_pelanggan
                                 ORDER BY total_belanja DESC, pel.nama ASC";
                $pelanggan = getAll($sql_pelanggan, []);
                
                // Hitung statistik keseluruhan
                $total_semua_pelanggan = count($pelanggan);
                $total_semua_pesanan = array_sum(array_column($pelanggan, 'total_pesanan'));
                $total_semua_belanja = array_sum(array_column($pelanggan, 'total_belanja'));
                
                return [
                    'pelanggan' => $pelanggan,
                    'statistics' => [
                        'total_pelanggan' => $total_semua_pelanggan,
                        'total_pesanan' => $total_semua_pesanan,
                        'total_belanja' => $total_semua_belanja
                    ]
                ];
        }
    } catch (Exception $e) {
        // Return empty data jika error
        return [];
    }
    
    return [];
}

function generateKeuanganContent($pdf, $data, $start_date, $end_date) {
    // Define purple color scheme
    $primary_purple = array(102, 51, 153);
    $secondary_purple = array(147, 112, 219);
    $light_purple = array(216, 191, 216);
    $accent_purple = array(186, 85, 211);
    $very_light_purple = array(248, 245, 250);
    
    $total_pendapatan = $data['total_pendapatan'] ?? 0;
    $total_pemasukan = $data['total_pemasukan'] ?? 0;
    $total_pengeluaran = $data['total_pengeluaran'] ?? 0;
    $pengeluaran = $data['pengeluaran'] ?? [];
    $pemasukan = $data['pemasukan'] ?? [];
    $total_uang_masuk = $total_pendapatan + $total_pemasukan;
    $laba_rugi = $total_uang_masuk - $total_pengeluaran;
    
    // Detail Pemasukan Section
    if (!empty($pemasukan)) {
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetTextColor($primary_purple[0], $primary_purple[1], $primary_purple[2]);
        $pdf->Cell(0, 10, 'DETAIL PEMASUKAN', 0, 1, 'L');
        
        // Header table dengan gradient purple
        $pdf->SetFillColor($primary_purple[0], $primary_purple[1], $primary_purple[2]);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 9);
        
        // Table header
        $pdf->Cell(10, 8, 'No', 1, 0, 'C', true);
        $pdf->Cell(25, 8, 'Tanggal', 1, 0, 'C', true);
        $pdf->Cell(35, 8, 'Sumber', 1, 0, 'C', true);
        $pdf->Cell(35, 8, 'Metode Bayar', 1, 0, 'C', true);
        $pdf->Cell(40, 8, 'Jumlah', 1, 0, 'C', true);
        $pdf->Cell(45, 8, 'Keterangan', 1, 1, 'C', true);
        
        // Data rows dengan alternating colors
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 8);
        $no = 1;
        $total_pemasukan_display = 0;
        
        foreach ($pemasukan as $pm) {
            // Alternate row color dengan nuansa ungu muda
            if ($no % 2 == 0) {
                $pdf->SetFillColor($very_light_purple[0], $very_light_purple[1], $very_light_purple[2]);
            } else {
                $pdf->SetFillColor(255, 255, 255);
            }
            
            $pdf->Cell(10, 7, $no++, 1, 0, 'C', true);
            $pdf->Cell(25, 7, date('d/m/Y', strtotime($pm['tgl_pemasukan'])), 1, 0, 'C', true);
            $pdf->Cell(35, 7, substr($pm['sumber_pemasukan'], 0, 20), 1, 0, 'L', true);
            $pdf->Cell(35, 7, substr($pm['metode_bayar'], 0, 15), 1, 0, 'L', true);
            $pdf->SetTextColor(0, 128, 0); // Hijau untuk jumlah pemasukan
            $pdf->Cell(40, 7, 'Rp ' . number_format($pm['jumlah_pemasukan'], 0, ',', '.'), 1, 0, 'R', true);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell(45, 7, substr($pm['keterangan'], 0, 25), 1, 1, 'L', true);
            
            $total_pemasukan_display += $pm['jumlah_pemasukan'];
        }
        
        // Total row dengan accent color
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor($accent_purple[0], $accent_purple[1], $accent_purple[2]);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(105, 8, 'TOTAL PEMASUKAN:', 1, 0, 'R', true);
        $pdf->Cell(40, 8, 'Rp ' . number_format($total_pemasukan_display, 0, ',', '.'), 1, 0, 'R', true);
        $pdf->Cell(45, 8, '', 1, 1, 'L', true);
        
        $pdf->Ln(8);
    } else {
        // Style untuk empty state
        $pdf->SetFillColor($very_light_purple[0], $very_light_purple[1], $very_light_purple[2]);
        $pdf->SetDrawColor($light_purple[0], $light_purple[1], $light_purple[2]);
        $pdf->RoundedRect(15, $pdf->GetY(), 180, 15, 3, '1111', 'DF');
        $pdf->SetFont('helvetica', 'I', 11);
        $pdf->SetTextColor($secondary_purple[0], $secondary_purple[1], $secondary_purple[2]);
        $pdf->Cell(0, 15, 'Tidak ada data pemasukan untuk periode ini', 0, 1, 'C');
        $pdf->Ln(8);
    }
    
    // Detail Pengeluaran Section
    if (!empty($pengeluaran)) {
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetTextColor($primary_purple[0], $primary_purple[1], $primary_purple[2]);
        $pdf->Cell(0, 10, 'DETAIL PENGELUARAN', 0, 1, 'L');
        
        // Header table dengan gradient purple
        $pdf->SetFillColor($primary_purple[0], $primary_purple[1], $primary_purple[2]);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 9);
        
        // Table header
        $pdf->Cell(10, 8, 'No', 1, 0, 'C', true);
        $pdf->Cell(25, 8, 'Tanggal', 1, 0, 'C', true);
        $pdf->Cell(40, 8, 'Kategori', 1, 0, 'C', true);
        $pdf->Cell(40, 8, 'Jumlah', 1, 0, 'C', true);
        $pdf->Cell(75, 8, 'Keterangan', 1, 1, 'C', true);
        
        // Data rows dengan alternating colors
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 8);
        $no = 1;
        $total_pengeluaran_display = 0;
        
        foreach ($pengeluaran as $p) {
            // Alternate row color dengan nuansa ungu muda
            if ($no % 2 == 0) {
                $pdf->SetFillColor($very_light_purple[0], $very_light_purple[1], $very_light_purple[2]);
            } else {
                $pdf->SetFillColor(255, 255, 255);
            }
            
            $pdf->Cell(10, 7, $no++, 1, 0, 'C', true);
            $pdf->Cell(25, 7, date('d/m/Y', strtotime($p['tgl_pengeluaran'])), 1, 0, 'C', true);
            $pdf->Cell(40, 7, substr($p['kategori_pengeluaran'], 0, 25), 1, 0, 'L', true);
            $pdf->SetTextColor(220, 53, 69); // Merah untuk pengeluaran
            $pdf->Cell(40, 7, 'Rp ' . number_format($p['jumlah_pengeluaran'], 0, ',', '.'), 1, 0, 'R', true);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell(75, 7, substr($p['keterangan'], 0, 40), 1, 1, 'L', true);
            
            $total_pengeluaran_display += $p['jumlah_pengeluaran'];
        }
        
        // Total row dengan accent color
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor($accent_purple[0], $accent_purple[1], $accent_purple[2]);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(75, 8, 'TOTAL PENGELUARAN:', 1, 0, 'R', true);
        $pdf->Cell(40, 8, 'Rp ' . number_format($total_pengeluaran_display, 0, ',', '.'), 1, 0, 'R', true);
        $pdf->Cell(75, 8, '', 1, 1, 'L', true);
        
        $pdf->Ln(8);
    } else {
        // Style untuk empty state
        $pdf->SetFillColor($very_light_purple[0], $very_light_purple[1], $very_light_purple[2]);
        $pdf->SetDrawColor($light_purple[0], $light_purple[1], $light_purple[2]);
        $pdf->RoundedRect(15, $pdf->GetY(), 180, 15, 3, '1111', 'DF');
        $pdf->SetFont('helvetica', 'I', 11);
        $pdf->SetTextColor($secondary_purple[0], $secondary_purple[1], $secondary_purple[2]);
        $pdf->Cell(0, 15, 'Tidak ada data pengeluaran untuk periode ini', 0, 1, 'C');
        $pdf->Ln(8);
    }
    
    // Detail Ringkasan Keuangan Section
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetTextColor($primary_purple[0], $primary_purple[1], $primary_purple[2]);
    $pdf->Cell(0, 10, 'RINCIAN KEUANGAN', 0, 1, 'L');
    
    // Table Ringkasan Keuangan dengan style ungu
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor($primary_purple[0], $primary_purple[1], $primary_purple[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(120, 8, 'KETERANGAN', 1, 0, 'C', true);
    $pdf->Cell(50, 8, 'JUMLAH', 1, 1, 'C', true);
    
    // Data rows untuk ringkasan dengan alternating colors
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 9);
    
    // Row 1: Pendapatan Pesanan
    $pdf->SetFillColor(255, 255, 255);
    $pdf->Cell(120, 7, 'Total Pendapatan Pesanan', 1, 0, 'L', true);
    $pdf->SetTextColor(0, 128, 0); // Hijau untuk pemasukan
    $pdf->Cell(50, 7, 'Rp ' . number_format($total_pendapatan, 0, ',', '.'), 1, 1, 'R', true);
    $pdf->SetTextColor(0, 0, 0);
    
    // Row 2: Pemasukan Lainnya
    $pdf->SetFillColor($very_light_purple[0], $very_light_purple[1], $very_light_purple[2]);
    $pdf->Cell(120, 7, 'Total Pemasukan Lainnya', 1, 0, 'L', true);
    $pdf->SetTextColor(0, 128, 0); // Hijau untuk pemasukan
    $pdf->Cell(50, 7, 'Rp ' . number_format($total_pemasukan, 0, ',', '.'), 1, 1, 'R', true);
    $pdf->SetTextColor(0, 0, 0);
    
    // Row 3: Total Uang Masuk
    $pdf->SetFillColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(120, 7, 'Total Uang Masuk', 1, 0, 'L', true);
    $pdf->SetTextColor(0, 128, 0); // Hijau untuk total uang masuk
    $pdf->Cell(50, 7, 'Rp ' . number_format($total_uang_masuk, 0, ',', '.'), 1, 1, 'R', true);
    $pdf->SetTextColor(0, 0, 0);
    
    // Row 4: Pengeluaran
    $pdf->SetFillColor($very_light_purple[0], $very_light_purple[1], $very_light_purple[2]);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(120, 7, 'Total Pengeluaran', 1, 0, 'L', true);
    $pdf->SetTextColor(220, 53, 69); // Merah untuk pengeluaran
    $pdf->Cell(50, 7, 'Rp ' . number_format($total_pengeluaran, 0, ',', '.'), 1, 1, 'R', true);
    $pdf->SetTextColor(0, 0, 0);
    
    // Row 5: Laba/Rugi dengan style khusus
    $pdf->SetFillColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(120, 8, ($laba_rugi >= 0 ? 'LABA BERSIH' : 'RUGI BERSIH'), 1, 0, 'L', true);
    $pdf->SetTextColor($laba_rugi >= 0 ? 0 : 220, $laba_rugi >= 0 ? 128 : 53, $laba_rugi >= 0 ? 0 : 69);
    $pdf->Cell(50, 8, 'Rp ' . number_format(abs($laba_rugi), 0, ',', '.'), 1, 1, 'R', true);
    $pdf->SetTextColor(0, 0, 0);
}

function generatePesananContent($pdf, $data, $start_date, $end_date) {
    // Define purple color scheme
    $primary_purple = array(102, 51, 153);
    $secondary_purple = array(147, 112, 219);
    $light_purple = array(216, 191, 216);
    $accent_purple = array(186, 85, 211);
    $very_light_purple = array(248, 245, 250);
    
    $pesanan = $data['pesanan'] ?? [];
    $stat = $data['statistics'] ?? ['total_pesanan' => 0, 'total_omzet' => 0];
    
    // Detail Pesanan Section
    if (!empty($pesanan)) {
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetTextColor($primary_purple[0], $primary_purple[1], $primary_purple[2]);
        $pdf->Cell(0, 10, 'DETAIL PESANAN', 0, 1, 'L');
        
        // Header table dengan gradient purple
        $pdf->SetFillColor($primary_purple[0], $primary_purple[1], $primary_purple[2]);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 8);
        
        // Table header
        $pdf->Cell(8, 8, 'No', 1, 0, 'C', true);
        $pdf->Cell(25, 8, 'ID Pesanan', 1, 0, 'C', true);
        $pdf->Cell(40, 8, 'Pelanggan', 1, 0, 'C', true);
        $pdf->Cell(30, 8, 'Jenis Pakaian', 1, 0, 'C', true);
        $pdf->Cell(25, 8, 'Tanggal', 1, 0, 'C', true);
        $pdf->Cell(35, 8, 'Total Harga', 1, 0, 'C', true);
        $pdf->Cell(27, 8, 'Status', 1, 1, 'C', true);
        
        // Data rows dengan alternating colors
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 7);
        $no = 1;
        $total_omzet = 0;
        
        foreach ($pesanan as $p) {
            // Alternate row color dengan nuansa ungu muda
            if ($no % 2 == 0) {
                $pdf->SetFillColor($very_light_purple[0], $very_light_purple[1], $very_light_purple[2]);
            } else {
                $pdf->SetFillColor(255, 255, 255);
            }
            
            $pdf->Cell(8, 6, $no++, 1, 0, 'C', true);
            $pdf->Cell(25, 6, '#' . $p['id_pesanan'], 1, 0, 'C', true);
            $pdf->Cell(40, 6, substr($p['nama_pelanggan'], 0, 20), 1, 0, 'L', true);
            $pdf->Cell(30, 6, substr($p['jenis_pakaian'], 0, 15), 1, 0, 'L', true);
            $pdf->Cell(25, 6, date('d/m/Y', strtotime($p['tgl_pesanan'])), 1, 0, 'C', true);
            $pdf->SetTextColor(0, 128, 0);
            $pdf->Cell(35, 6, 'Rp ' . number_format($p['total_harga'], 0, ',', '.'), 1, 0, 'R', true);
            $pdf->SetTextColor(0, 0, 0);
            
            // Status dengan warna
            $status = $p['status_pesanan'];
            if ($status == 'selesai') {
                $pdf->SetFillColor(220, 252, 231);
                $pdf->SetTextColor(22, 101, 52);
            } elseif ($status == 'dalam_proses') {
                $pdf->SetFillColor(219, 234, 254);
                $pdf->SetTextColor(29, 78, 216);
            } else {
                $pdf->SetFillColor(254, 243, 199);
                $pdf->SetTextColor(217, 119, 6);
            }
            
            $status_text = $status == 'belum' ? 'BELUM SELSAI' : ($status == 'dalam_proses' ? 'PROSES' : 'SELESAI');
            $pdf->Cell(27, 6, $status_text, 1, 1, 'C', true);
            $pdf->SetFillColor(255, 255, 255);
            $pdf->SetTextColor(0, 0, 0);
            
            $total_omzet += $p['total_harga'];
        }
        
        // Total row dengan accent color
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetFillColor($accent_purple[0], $accent_purple[1], $accent_purple[2]);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(128, 8, 'TOTAL OMZET:', 1, 0, 'R', true);
        $pdf->Cell(35, 8, 'Rp ' . number_format($total_omzet, 0, ',', '.'), 1, 0, 'R', true);
        $pdf->Cell(27, 8, '', 1, 1, 'C', true);
        
    } else {
        // Style untuk empty state
        $pdf->SetFillColor($very_light_purple[0], $very_light_purple[1], $very_light_purple[2]);
        $pdf->SetDrawColor($light_purple[0], $light_purple[1], $light_purple[2]);
        $pdf->RoundedRect(15, $pdf->GetY(), 180, 15, 3, '1111', 'DF');
        $pdf->SetFont('helvetica', 'I', 11);
        $pdf->SetTextColor($secondary_purple[0], $secondary_purple[1], $secondary_purple[2]);
        $pdf->Cell(0, 15, 'Tidak ada data pesanan untuk periode ini', 0, 1, 'C');
    }
}

function generatePelangganContent($pdf, $data, $start_date, $end_date) {
    // Define purple color scheme
    $primary_purple = array(102, 51, 153);
    $secondary_purple = array(147, 112, 219);
    $light_purple = array(216, 191, 216);
    $accent_purple = array(186, 85, 211);
    $very_light_purple = array(248, 245, 250);
    
    $pelanggan = $data['pelanggan'] ?? [];
    $stat = $data['statistics'] ?? ['total_pelanggan' => 0, 'total_pesanan' => 0, 'total_belanja' => 0];
    
    // Ringkasan Pelanggan dengan card style
    $pdf->SetFillColor($very_light_purple[0], $very_light_purple[1], $very_light_purple[2]);
    $pdf->SetDrawColor($secondary_purple[0], $secondary_purple[1], $secondary_purple[2]);
    $pdf->SetLineWidth(0.3);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetTextColor($primary_purple[0], $primary_purple[1], $primary_purple[2]);
    $pdf->Cell(0, 8, ' RINGKASAN PELANGGAN', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColor(0, 0, 0);

    // Card ringkasan
    $pdf->SetFillColor(255, 255, 255);
    $pdf->SetDrawColor($light_purple[0], $light_purple[1], $light_purple[2]);
    $pdf->SetLineWidth(0.5);
    $pdf->RoundedRect(15, $pdf->GetY(), 180, 15, 2, '1111', 'DF');

    // Data ringkasan dalam card
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetTextColor($primary_purple[0], $primary_purple[1], $primary_purple[2]);
    $pdf->Cell(45, 6, 'Total Pelanggan:', 0, 0, 'L');
    $pdf->SetTextColor($accent_purple[0], $accent_purple[1], $accent_purple[2]);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(40, 6, $stat['total_pelanggan'] . ' pelanggan', 0, 0, 'L');

    $pdf->SetTextColor($primary_purple[0], $primary_purple[1], $primary_purple[2]);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(45, 6, 'Total Pesanan:', 0, 0, 'L');
    $pdf->SetTextColor($accent_purple[0], $accent_purple[1], $accent_purple[2]);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(40, 6, $stat['total_pesanan'] . ' pesanan', 0, 1, 'L');

    $pdf->Ln(2);

    $pdf->SetTextColor($primary_purple[0], $primary_purple[1], $primary_purple[2]);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(45, 6, 'Total Belanja:', 0, 0, 'L');
    $pdf->SetTextColor(0, 128, 0); // Hijau untuk total belanja
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(40, 6, 'Rp ' . number_format($stat['total_belanja'], 0, ',', '.'), 0, 0, 'L');

    $pdf->Ln(10);

    // Detail Pelanggan Section
    if (!empty($pelanggan)) {
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetTextColor($primary_purple[0], $primary_purple[1], $primary_purple[2]);
        $pdf->Cell(0, 10, 'DATA PELANGGAN', 0, 1, 'L');
        
        // Header table dengan gradient purple
        $pdf->SetFillColor($primary_purple[0], $primary_purple[1], $primary_purple[2]);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 8);
        
        // Table header
        $pdf->Cell(8, 8, 'No', 1, 0, 'C', true);
        $pdf->Cell(40, 8, 'Nama Pelanggan', 1, 0, 'C', true);
        $pdf->Cell(30, 8, 'No HP', 1, 0, 'C', true);
        $pdf->Cell(45, 8, 'Alamat', 1, 0, 'C', true);
        $pdf->Cell(20, 8, 'Pesanan', 1, 0, 'C', true);
        $pdf->Cell(37, 8, 'Total Belanja', 1, 1, 'C', true);
        
        // Data rows dengan alternating colors
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 7);
        $no = 1;
        
        foreach ($pelanggan as $row) {
            // Alternate row color dengan nuansa ungu muda
            if ($no % 2 == 0) {
                $pdf->SetFillColor($very_light_purple[0], $very_light_purple[1], $very_light_purple[2]);
            } else {
                $pdf->SetFillColor(255, 255, 255);
            }
            
            $pdf->Cell(8, 6, $no++, 1, 0, 'C', true);
            $pdf->Cell(40, 6, substr($row['nama'], 0, 25), 1, 0, 'L', true);
            $pdf->Cell(30, 6, $row['no_hp'], 1, 0, 'C', true);
            $pdf->Cell(45, 6, substr($row['alamat'], 0, 30), 1, 0, 'L', true);
            
            // Total pesanan dengan badge style
            $total_pesanan = $row['total_pesanan'] ?? 0;
            if ($total_pesanan > 0) {
                $pdf->SetFillColor($accent_purple[0], $accent_purple[1], $accent_purple[2]);
                $pdf->SetTextColor(255, 255, 255);
            } else {
                $pdf->SetFillColor(200, 200, 200);
                $pdf->SetTextColor(0, 0, 0);
            }
            $pdf->Cell(20, 6, $total_pesanan, 1, 0, 'C', true);
            
            // Total belanja
            $pdf->SetFillColor(255, 255, 255);
            $pdf->SetTextColor(0, 0, 0);
            $total_belanja = $row['total_belanja'] ?? 0;
            if ($total_belanja > 0) {
                $pdf->SetTextColor(0, 128, 0); // Hijau untuk total belanja
            }
            $pdf->Cell(37, 6, 'Rp ' . number_format($total_belanja, 0, ',', '.'), 1, 1, 'R', true);
            $pdf->SetTextColor(0, 0, 0);
        }
        
        // Total row dengan accent color
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetFillColor($accent_purple[0], $accent_purple[1], $accent_purple[2]);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(123, 8, 'TOTAL KESELURUHAN:', 1, 0, 'R', true);
        $pdf->Cell(20, 8, $stat['total_pesanan'], 1, 0, 'C', true);
        $pdf->SetTextColor(0, 128, 0);
        $pdf->Cell(37, 8, 'Rp ' . number_format($stat['total_belanja'], 0, ',', '.'), 1, 1, 'R', true);
        
    } else {
        // Style untuk empty state
        $pdf->SetFillColor($very_light_purple[0], $very_light_purple[1], $very_light_purple[2]);
        $pdf->SetDrawColor($light_purple[0], $light_purple[1], $light_purple[2]);
        $pdf->RoundedRect(15, $pdf->GetY(), 180, 15, 3, '1111', 'DF');
        $pdf->SetFont('helvetica', 'I', 11);
        $pdf->SetTextColor($secondary_purple[0], $secondary_purple[1], $secondary_purple[2]);
        $pdf->Cell(0, 15, 'Tidak ada data pelanggan yang ditemukan', 0, 1, 'C');
    }
}
?>