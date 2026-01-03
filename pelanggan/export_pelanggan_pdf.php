<?php
// export_pelanggan_pdf.php - VERSION SUPER CLEAN WITH PURPLE THEME

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
// PHASE 2: INCLUDE TCPDF DENGAN ERROR HANDLING
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
// PHASE 3: AMBIL DATA USER DARI SESSION ATAU DATABASE
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
// PHASE 4: AMBIL PARAMETER DAN DATA PELANGGAN
// ============================================================================

// Ambil parameter dengan sanitization - GUNAKAN FUNCTION YANG SUDAH ADA DI database.php
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$filter_tanggal = isset($_GET['tanggal']) ? clean_input($_GET['tanggal']) : '';

// Query data dengan filter yang sama
$sql_where = "";
$params = [];
$where_conditions = [];

if (!empty($search)) {
    $where_conditions[] = "(nama LIKE ? OR no_hp LIKE ? OR alamat LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($filter_tanggal)) {
    $where_conditions[] = "DATE(created_at) = ?";
    $params[] = $filter_tanggal;
}

if (count($where_conditions) > 0) {
    $sql_where = "WHERE " . implode(" AND ", $where_conditions);
}

// Ambil semua data pelanggan tanpa pagination
try {
    $sql = "SELECT * FROM data_pelanggan $sql_where ORDER BY nama ASC";
    $pelanggan = getAll($sql, $params);
} catch (PDOException $e) {
    $pelanggan = [];
}

// Hitung total
$total_pelanggan = count($pelanggan);

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
$pdf->SetTitle('Laporan Data Pelanggan');
$pdf->SetSubject('Laporan Data Pelanggan');

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set margins
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(TRUE, 15);

// Add a page
$pdf->AddPage();

// ============================================================================
// PHASE 6: GENERATE CONTENT PELANGGAN DENGAN THEME UNGU
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
$pdf->Cell(0, 12, 'LAPORAN DATA PELANGGAN', 0, 1, 'C', true);
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

// Informasi cetak - PERBAIKAN DI SINI
$pdf->SetTextColor(80, 80, 80);
$pdf->Cell(0, 5, 'Dicetak pada: ' . date('d/m/Y H:i:s'), 0, 1, 'C');
$pdf->Cell(0, 5, 'Oleh: ' . $nama_cetak, 0, 1, 'C');

// Informasi Filter dengan style khusus
if (!empty($search) || !empty($filter_tanggal)) {
    $pdf->Ln(3);
    $pdf->SetFillColor($light_purple[0], $light_purple[1], $light_purple[2]);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', 'I', 9);
    
    $filter_text = 'Filter Terapkan: ';
    if (!empty($search)) {
        $filter_text .= 'Pencarian "' . $search . '"';
    }
    if (!empty($filter_tanggal)) {
        if (!empty($search)) $filter_text .= ' dan ';
        $filter_text .= 'Tanggal ' . date('d/m/Y', strtotime($filter_tanggal));
    }
    
    $pdf->Cell(0, 6, $filter_text, 0, 1, 'C', true);
}

$pdf->Ln(8);

// Detail Pelanggan Section
if (!empty($pelanggan)) {
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetTextColor($primary_purple[0], $primary_purple[1], $primary_purple[2]);
    $pdf->Cell(0, 10, 'DETAIL PELANGGAN', 0, 1, 'L');
    
    // Header table dengan gradient purple
    $pdf->SetFillColor($primary_purple[0], $primary_purple[1], $primary_purple[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 9);
    
    // Table header
    $pdf->Cell(10, 8, 'No', 1, 0, 'C', true);
    $pdf->Cell(50, 8, 'Nama Pelanggan', 1, 0, 'C', true);
    $pdf->Cell(35, 8, 'No HP', 1, 0, 'C', true);
    $pdf->Cell(70, 8, 'Alamat', 1, 0, 'C', true);
    $pdf->Cell(25, 8, 'Tanggal Daftar', 1, 1, 'C', true);
    
    // Data rows dengan alternating colors
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 8);
    $no = 1;
    
    foreach ($pelanggan as $row) {
        // Alternate row color dengan nuansa ungu muda
        if ($no % 2 == 0) {
            $pdf->SetFillColor($very_light_purple[0], $very_light_purple[1], $very_light_purple[2]);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }
        
        $pdf->Cell(10, 7, $no++, 1, 0, 'C', true);
        $pdf->Cell(50, 7, substr($row['nama'], 0, 25), 1, 0, 'L', true);
        $pdf->Cell(35, 7, $row['no_hp'], 1, 0, 'C', true);
        $pdf->Cell(70, 7, substr($row['alamat'], 0, 45), 1, 0, 'L', true);
        $pdf->Cell(25, 7, date('d/m/Y', strtotime($row['created_at'])), 1, 1, 'C', true);
    }
    
    // Total row dengan accent color
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor($accent_purple[0], $accent_purple[1], $accent_purple[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(95, 8, 'TOTAL PELANGGAN:', 1, 0, 'R', true);
    $pdf->Cell(35, 8, $total_pelanggan . ' pelanggan', 1, 0, 'C', true);
    $pdf->Cell(70, 8, '', 1, 1, 'L', true);
    
} else {
    // Style untuk empty state
    $pdf->SetFillColor($very_light_purple[0], $very_light_purple[1], $very_light_purple[2]);
    $pdf->SetDrawColor($light_purple[0], $light_purple[1], $light_purple[2]);
    $pdf->RoundedRect(15, $pdf->GetY(), 180, 20, 3, '1111', 'DF');
    $pdf->SetFont('helvetica', 'I', 11);
    $pdf->SetTextColor($secondary_purple[0], $secondary_purple[1], $secondary_purple[2]);
    $pdf->Cell(0, 20, 'Tidak ada data pelanggan yang ditemukan', 0, 1, 'C');
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
$pdf->Rotate(15, 105, 150);

// Tulisan "PARAPATAN" di atas
$pdf->Text(30, 120, 'PARAPATAN');

// Tulisan "TAILOR" di bawah (dengan jarak yang cukup)
$pdf->Text(60, 150, 'TAILOR');

// Kembalikan rotasi ke normal
$pdf->Rotate(0);
$pdf->SetAlpha(1);

// ============================================================================
// PHASE 8: OUTPUT PDF - FINAL STEP
// ============================================================================

// Clean buffer terakhir kali sebelum output
ob_clean();

// Output PDF ke browser
$pdf->Output('Laporan_Pelanggan_' . date('Y-m-d') . '.pdf', 'I');

// Exit untuk memastikan tidak ada output tambahan
exit;
?>