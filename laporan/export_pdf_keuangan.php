<?php
// export_pdf_keuangan.php - VERSION SUPER CLEAN WITH PURPLE THEME

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
$start_date = isset($_GET['start_date']) ? clean_input($_GET['start_date']) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? clean_input($_GET['end_date']) : date('Y-m-t');

// Data untuk Laporan Keuangan
try {
    // Total Pendapatan dari transaksi pembayaran pesanan
    $sql_pendapatan = "SELECT COALESCE(SUM(jumlah_bayar), 0) as total_pendapatan 
                      FROM data_transaksi 
                      WHERE DATE(created_at) BETWEEN ? AND ?";
    $total_pendapatan = getSingle($sql_pendapatan, [$start_date, $end_date])['total_pendapatan'];
    
    // Total Pengeluaran
    $sql_pengeluaran = "SELECT COALESCE(SUM(jumlah_pengeluaran), 0) as total_pengeluaran 
                       FROM data_pengeluaran 
                       WHERE DATE(tgl_pengeluaran) BETWEEN ? AND ?";
    $total_pengeluaran = getSingle($sql_pengeluaran, [$start_date, $end_date])['total_pengeluaran'];
    
    // Total Uang Masuk
    $total_uang_masuk = $total_pendapatan;
    
    // Laba/Rugi
    $laba_rugi = $total_uang_masuk - $total_pengeluaran;
    
    // Detail pengeluaran
    $sql_detail_pengeluaran = "SELECT * FROM data_pengeluaran 
                              WHERE DATE(tgl_pengeluaran) BETWEEN ? AND ?
                              ORDER BY tgl_pengeluaran DESC";
    $pengeluaran = getAll($sql_detail_pengeluaran, [$start_date, $end_date]);
    
    // Data Pemasukan dari transaksi pembayaran
    $sql_pemasukan = "SELECT t.*, pel.nama as nama_pelanggan
                      FROM data_transaksi t
                      LEFT JOIN data_pesanan p ON t.id_pesanan = p.id_pesanan
                      LEFT JOIN data_pelanggan pel ON p.id_pelanggan = pel.id_pelanggan
                      WHERE DATE(t.created_at) BETWEEN ? AND ?
                      ORDER BY t.created_at DESC";
    $pemasukan = getAll($sql_pemasukan, [$start_date, $end_date]);
    
    // Hitung total pemasukan dari transaksi
    $sql_total_pemasukan = "SELECT COALESCE(SUM(t.jumlah_bayar), 0) as total_pemasukan
                           FROM data_transaksi t
                           WHERE DATE(t.created_at) BETWEEN ? AND ?";
    $total_pemasukan_transaksi = getSingle($sql_total_pemasukan, [$start_date, $end_date])['total_pemasukan'];
    
} catch (PDOException $e) {
    $total_pendapatan = 0;
    $total_pengeluaran = 0;
    $total_uang_masuk = 0;
    $laba_rugi = 0;
    $pengeluaran = [];
    $pemasukan = [];
    $total_pemasukan_transaksi = 0;
}

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
$pdf->SetTitle('Laporan Keuangan');
$pdf->SetSubject('Laporan Keuangan');

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set margins
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(TRUE, 15);

// Add a page
$pdf->AddPage();

// ============================================================================
// PHASE 6: GENERATE CONTENT DENGAN THEME UNGU
// ============================================================================

// Define purple color scheme - SAMA PERSIS DENGAN KODE PERTAMA
$primary_purple = array(102, 51, 153);    // #663399 - Dark Purple
$secondary_purple = array(147, 112, 219); // #9370DB - Medium Purple
$light_purple = array(216, 191, 216);     // #D8BFD8 - Light Purple
$accent_purple = array(186, 85, 211);     // #BA55D3 - Orchid
$very_light_purple = array(248, 245, 250); // #F8F5FA - Very Light Purple

// Header dengan gradient background (simulasi)
$pdf->SetFillColor($primary_purple[0], $primary_purple[1], $primary_purple[2]);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 18);
$pdf->Cell(0, 12, 'LAPORAN KEUANGAN', 0, 1, 'C', true);
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

// Informasi cetak - DITAMBAHKAN "OLEH"
$pdf->SetTextColor(80, 80, 80);
$pdf->Cell(0, 5, 'Periode: ' . date('d/m/Y', strtotime($start_date)) . ' - ' . date('d/m/Y', strtotime($end_date)), 0, 1, 'C');
$pdf->Cell(0, 5, 'Dicetak pada: ' . date('d/m/Y H:i:s'), 0, 1, 'C');
$pdf->Cell(0, 5, 'Oleh: ' . $nama_cetak, 0, 1, 'C');

$pdf->Ln(8);

// ============================================================================
// 1. DETAIL PEMASUKAN DARI TRANSAKSI PEMBAYARAN
// ============================================================================

if (!empty($pemasukan)) {
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetTextColor($primary_purple[0], $primary_purple[1], $primary_purple[2]);
    $pdf->Cell(0, 10, 'DATA PEMASUKAN (TRANSAKSI PEMBAYARAN)', 0, 1, 'L');
    
    // Header table dengan gradient purple
    $pdf->SetFillColor($primary_purple[0], $primary_purple[1], $primary_purple[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 9);
    
    // Table header
    $pdf->Cell(10, 8, 'No', 1, 0, 'C', true);
    $pdf->Cell(70, 8, 'Nama Pelanggan', 1, 0, 'C', true);
    $pdf->Cell(45, 8, 'Tanggal Transaksi', 1, 0, 'C', true);
    $pdf->Cell(55, 8, 'Jumlah Pembayaran', 1, 1, 'C', true);
    
    // Data rows dengan alternating colors
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 8);
    $no = 1;
    $total_pemasukan_display = 0;
    
    foreach ($pemasukan as $p) {
        // Alternate row color dengan nuansa ungu muda
        if ($no % 2 == 0) {
            $pdf->SetFillColor($very_light_purple[0], $very_light_purple[1], $very_light_purple[2]);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }
        
        $pdf->Cell(10, 7, $no++, 1, 0, 'C', true);
        $pdf->Cell(70, 7, substr($p['nama_pelanggan'] ?? '-', 0, 35), 1, 0, 'L', true);
        $pdf->Cell(45, 7, date('d/m/Y H:i', strtotime($p['created_at'])), 1, 0, 'C', true);
        
        // Jumlah bayar (hijau untuk pemasukan)
        $pdf->SetTextColor(0, 128, 0);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(55, 7, 'Rp ' . number_format($p['jumlah_bayar'] ?? 0, 0, ',', '.'), 1, 1, 'R', true);
        
        $total_pemasukan_display += $p['jumlah_bayar'] ?? 0;
        
        // Reset warna untuk baris berikutnya
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 8);
    }
    
    // Total row dengan accent color
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor($accent_purple[0], $accent_purple[1], $accent_purple[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(125, 8, 'TOTAL PEMASUKAN DARI TRANSAKSI:', 1, 0, 'R', true);
    $pdf->Cell(55, 8, 'Rp ' . number_format($total_pemasukan_display, 0, ',', '.'), 1, 1, 'R', true);
    
    $pdf->Ln(10);
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

// ============================================================================
// 2. DETAIL PENGELUARAN
// ============================================================================

if (!empty($pengeluaran)) {
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetTextColor($primary_purple[0], $primary_purple[1], $primary_purple[2]);
    $pdf->Cell(0, 10, 'DATA PENGELUARAN', 0, 1, 'L');
    
    // Header table dengan gradient purple
    $pdf->SetFillColor($primary_purple[0], $primary_purple[1], $primary_purple[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 9);
    
    // Table header
    $pdf->Cell(10, 8, 'No', 1, 0, 'C', true);
    $pdf->Cell(25, 8, 'Tanggal', 1, 0, 'C', true);
    $pdf->Cell(40, 8, 'Kategori', 1, 0, 'C', true);
    $pdf->Cell(40, 8, 'Jumlah Pengeluaran', 1, 0, 'C', true);
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
        
        // Jumlah pengeluaran (merah)
        $pdf->SetTextColor(220, 53, 69);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(40, 7, 'Rp ' . number_format($p['jumlah_pengeluaran'], 0, ',', '.'), 1, 0, 'R', true);
        
        // Keterangan
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 8);
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

// ============================================================================
// 3. RINGKASAN KEUANGAN
// ============================================================================

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

// Row 1: Total Pemasukan
$pdf->SetFillColor(255, 255, 255);
$pdf->Cell(120, 7, 'Total Pemasukan dari Transaksi', 1, 0, 'L', true);
$pdf->SetTextColor(0, 128, 0); // Hijau untuk pemasukan
$pdf->Cell(50, 7, 'Rp ' . number_format($total_pemasukan_transaksi, 0, ',', '.'), 1, 1, 'R', true);
$pdf->SetTextColor(0, 0, 0);

// Row 2: Total Pengeluaran
$pdf->SetFillColor($very_light_purple[0], $very_light_purple[1], $very_light_purple[2]);
$pdf->Cell(120, 7, 'Total Pengeluaran', 1, 0, 'L', true);
$pdf->SetTextColor(220, 53, 69); // Merah untuk pengeluaran
$pdf->Cell(50, 7, 'Rp ' . number_format($total_pengeluaran, 0, ',', '.'), 1, 1, 'R', true);
$pdf->SetTextColor(0, 0, 0);

// Row 3: Laba/Rugi dengan style khusus
$pdf->SetFillColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(120, 8, ($laba_rugi >= 0 ? 'LABA BERSIH' : 'RUGI BERSIH'), 1, 0, 'L', true);
$pdf->SetTextColor($laba_rugi >= 0 ? 0 : 220, $laba_rugi >= 0 ? 128 : 53, $laba_rugi >= 0 ? 0 : 69);
$pdf->Cell(50, 8, 'Rp ' . number_format(abs($laba_rugi), 0, ',', '.'), 1, 1, 'R', true);
$pdf->SetTextColor(0, 0, 0);

$pdf->Ln(15);

// ============================================================================
// 4. CATATAN DAN FOOTER
// ============================================================================

$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetTextColor($primary_purple[0], $primary_purple[1], $primary_purple[2]);
$pdf->Cell(0, 8, 'CATATAN:', 0, 1, 'L');

$pdf->SetFont('helvetica', 'I', 8);
$pdf->SetTextColor(80, 80, 80);
$pdf->MultiCell(0, 4, '1. Data pemasukan dihitung dari transaksi pembayaran yang telah dilakukan pelanggan.', 0, 'L');
$pdf->MultiCell(0, 4, '2. Data pengeluaran mencakup semua biaya operasional dan non-operasional yang tercatat.', 0, 'L');
$pdf->MultiCell(0, 4, '3. Laporan ini sah sebagai dokumen resmi keuangan Parapatan Tailor.', 0, 'L');

// Footer dengan style ungu
$pdf->Ln(10);
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

// Nama file dengan format yang sesuai
$filename = 'Laporan_Keuangan_' . date('Y-m-d') . '.pdf';

// Output PDF ke browser
$pdf->Output($filename, 'I');

// Exit untuk memastikan tidak ada output tambahan
exit;
?>