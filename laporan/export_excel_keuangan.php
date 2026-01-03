<?php
// export_excel_keuangan.php
require_once '../config/database.php';
check_login();
check_role(['admin', 'pemilik']);

// Tanggal default untuk filter
$start_date = isset($_GET['start_date']) ? clean_input($_GET['start_date']) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? clean_input($_GET['end_date']) : date('Y-m-t');
$type = isset($_GET['type']) ? clean_input($_GET['type']) : 'keuangan';

// Set header untuk Excel
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename="Laporan_Keuangan_' . date('Y-m-d', strtotime($start_date)) . '_sd_' . date('Y-m-d', strtotime($end_date)) . '.xls"');
header('Cache-Control: max-age=0');

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
    
    // Total Uang Masuk (hanya dari pendapatan pesanan)
    $total_uang_masuk = $total_pendapatan;
    
    // Laba/Rugi
    $laba_rugi = $total_uang_masuk - $total_pengeluaran;
    
    // Detail pengeluaran
    $sql_detail_pengeluaran = "SELECT * FROM data_pengeluaran 
                              WHERE DATE(tgl_pengeluaran) BETWEEN ? AND ?
                              ORDER BY tgl_pengeluaran DESC";
    $pengeluaran = getAll($sql_detail_pengeluaran, [$start_date, $end_date]);
    
    // Data Pemasukan dari transaksi pembayaran
    $sql_pemasukan = "SELECT t.*, pel.nama as nama_pelanggan, p.total_harga, p.jenis_pakaian,
                             pel.alamat, pel.no_hp, p.jumlah_bayar as total_bayar_pesanan,
                             p.sisa_bayar
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

// Output Excel untuk Laporan Keuangan
echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Laporan Keuangan</title>
    <style>
        body { font-family: Arial, sans-serif; }
        table { border-collapse: collapse; width: 100%; }
        th { background-color: #4f46e5; color: white; padding: 10px; text-align: center; }
        td { padding: 8px; border: 1px solid #ddd; }
        .header { font-size: 18px; font-weight: bold; text-align: center; }
        .subheader { font-size: 14px; text-align: center; }
        .total-row { font-weight: bold; background-color: #f8faff; }
        .pemasukan { color: #059669; font-weight: bold; }
        .pengeluaran { color: #dc2626; font-weight: bold; }
        .laba { color: #059669; font-weight: bold; }
        .rugi { color: #dc2626; font-weight: bold; }
        .even-row { background-color: #f8faff; }
        .odd-row { background-color: #ffffff; }
    </style>
</head>
<body>';

echo '<table border="1">';
    
// Header Laporan
echo '<tr>';
echo '<th colspan="6" style="font-size:20px;padding:15px;background:#4f46e5;color:white;">LAPORAN KEUANGAN</th>';
echo '</tr>';

echo '<tr>';
echo '<td colspan="6" style="text-align:center;padding:10px;font-size:16px;font-weight:bold;">PARAPATAN TAILOR</td>';
echo '</tr>';

echo '<tr>';
echo '<td colspan="6" style="text-align:center;padding:8px;">Jl. Baru Awirarangan No.123, Kecamatan Kuningan, Kabupaten Kuningan, Provinsi Jawa Barat</td>';
echo '</tr>';

echo '<tr>';
echo '<td colspan="6" style="text-align:center;padding:8px;">Periode: ' . date('d F Y', strtotime($start_date)) . ' - ' . date('d F Y', strtotime($end_date)) . '</td>';
echo '</tr>';

echo '<tr>';
echo '<td colspan="6" style="text-align:center;padding:8px;">Dicetak pada: ' . date('d/m/Y H:i:s') . '</td>';
echo '</tr>';

echo '<tr><td colspan="6" style="padding:10px;"></td></tr>';

// ============================================================================
// 1. DATA PEMASUKAN (TRANSAKSI PEMBAYARAN) - DITAMPILKAN PERTAMA
// ============================================================================

echo '<tr>';
echo '<td colspan="6" style="background:#e0e7ff;padding:10px;font-weight:bold;font-size:14px;text-align:center;">DATA PEMASUKAN (TRANSAKSI PEMBAYARAN)</td>';
echo '</tr>';

if (!empty($pemasukan)) {
    // Info jumlah data
    echo '<tr>';
    echo '<td colspan="6" style="padding:5px;font-style:italic;color:#666;">Total ' . count($pemasukan) . ' transaksi ditemukan</td>';
    echo '</tr>';
    
    // Header Tabel Pemasukan
    echo '<tr style="background:#4f46e5;color:white;">';
    echo '<th style="padding:10px;text-align:center;width:5%;">No</th>';
    echo '<th style="padding:10px;text-align:center;width:35%;">Nama Pelanggan</th>';
    echo '<th style="padding:10px;text-align:center;width:25%;">Tanggal Transaksi</th>';
    echo '<th style="padding:10px;text-align:center;width:35%;">Jumlah Pembayaran</th>';
    echo '</tr>';
    
    $no = 1;
    $total_pemasukan_display = 0;
    foreach ($pemasukan as $p) {
        $row_class = ($no % 2 == 0) ? 'even-row' : 'odd-row';
        
        echo '<tr class="' . $row_class . '">';
        echo '<td style="padding:8px;text-align:center;">' . $no++ . '</td>';
        echo '<td style="padding:8px;">' . htmlspecialchars($p['nama_pelanggan'] ?? '-') . '</td>';
        echo '<td style="padding:8px;text-align:center;">' . date('d/m/Y H:i', strtotime($p['created_at'])) . '</td>';
        echo '<td style="padding:8px;text-align:right;" class="pemasukan">Rp ' . number_format($p['jumlah_bayar'] ?? 0, 0, ',', '.') . '</td>';
        echo '</tr>';
        
        $total_pemasukan_display += $p['jumlah_bayar'] ?? 0;
    }
    
    // Total Pemasukan
    echo '<tr class="total-row">';
    echo '<td colspan="3" style="padding:10px;text-align:right;font-weight:bold;">TOTAL PEMASUKAN DARI TRANSAKSI:</td>';
    echo '<td style="padding:10px;text-align:right;font-weight:bold;" class="pemasukan">Rp ' . number_format($total_pemasukan_display, 0, ',', '.') . '</td>';
    echo '</tr>';
    
} else {
    echo '<tr>';
    echo '<td colspan="6" style="padding:20px;text-align:center;font-style:italic;color:#666;">Tidak ada data pemasukan untuk periode ini</td>';
    echo '</tr>';
}

echo '<tr><td colspan="6" style="padding:15px;"></td></tr>';

// ============================================================================
// 2. RINGKASAN KEUANGAN - DITAMPILKAN KEDUA
// ============================================================================

echo '<tr>';
echo '<td colspan="6" style="background:#e0e7ff;padding:10px;font-weight:bold;font-size:14px;text-align:center;">RINGKASAN KEUANGAN</td>';
echo '</tr>';

echo '<tr class="total-row">';
echo '<td colspan="3" style="padding:10px;text-align:left;font-weight:bold;">Total Pemasukan</td>';
echo '<td colspan="3" style="padding:10px;text-align:right;font-weight:bold;" class="pemasukan">Rp ' . number_format($total_pemasukan_transaksi, 0, ',', '.') . '</td>';
echo '</tr>';

echo '<tr class="total-row">';
echo '<td colspan="3" style="padding:10px;text-align:left;font-weight:bold;">Total Pengeluaran</td>';
echo '<td colspan="3" style="padding:10px;text-align:right;font-weight:bold;" class="pengeluaran">Rp ' . number_format($total_pengeluaran, 0, ',', '.') . '</td>';
echo '</tr>';

echo '<tr class="total-row">';
echo '<td colspan="3" style="padding:10px;text-align:left;font-weight:bold;">' . ($laba_rugi >= 0 ? 'LABA BERSIH' : 'RUGI BERSIH') . '</td>';
echo '<td colspan="3" style="padding:10px;text-align:right;font-weight:bold;" class="' . ($laba_rugi >= 0 ? 'laba' : 'rugi') . '">Rp ' . number_format(abs($laba_rugi), 0, ',', '.') . '</td>';
echo '</tr>';

echo '<tr><td colspan="6" style="padding:15px;"></td></tr>';

// ============================================================================
// 3. DATA PENGELUARAN - DITAMPILKAN KETIGA
// ============================================================================

echo '<tr>';
echo '<td colspan="6" style="background:#e0e7ff;padding:10px;font-weight:bold;font-size:14px;text-align:center;">DATA PENGELUARAN</td>';
echo '</tr>';

if (!empty($pengeluaran)) {
    // Info jumlah data
    echo '<tr>';
    echo '<td colspan="6" style="padding:5px;font-style:italic;color:#666;">Total ' . count($pengeluaran) . ' pengeluaran ditemukan</td>';
    echo '</tr>';
    
    // Header Tabel Pengeluaran
    echo '<tr style="background:#4f46e5;color:white;">';
    echo '<th style="padding:10px;text-align:center;width:5%;">No</th>';
    echo '<th style="padding:10px;text-align:center;width:15%;">Tanggal</th>';
    echo '<th style="padding:10px;text-align:center;width:25%;">Kategori</th>';
    echo '<th style="padding:10px;text-align:center;width:25%;">Jumlah Pengeluaran</th>';
    echo '<th style="padding:10px;text-align:center;width:30%;">Keterangan</th>';
    echo '</tr>';
    
    $no = 1;
    $total_pengeluaran_display = 0;
    foreach ($pengeluaran as $p) {
        $row_class = ($no % 2 == 0) ? 'even-row' : 'odd-row';
        
        echo '<tr class="' . $row_class . '">';
        echo '<td style="padding:8px;text-align:center;">' . $no++ . '</td>';
        echo '<td style="padding:8px;text-align:center;">' . date('d/m/Y', strtotime($p['tgl_pengeluaran'])) . '</td>';
        echo '<td style="padding:8px;">' . htmlspecialchars($p['kategori_pengeluaran']) . '</td>';
        echo '<td style="padding:8px;text-align:right;" class="pengeluaran">Rp ' . number_format($p['jumlah_pengeluaran'], 0, ',', '.') . '</td>';
        echo '<td style="padding:8px;">' . htmlspecialchars($p['keterangan']) . '</td>';
        echo '</tr>';
        
        $total_pengeluaran_display += $p['jumlah_pengeluaran'];
    }
    
    // Total Pengeluaran
    echo '<tr class="total-row">';
    echo '<td colspan="4" style="padding:10px;text-align:right;font-weight:bold;">TOTAL PENGELUARAN:</td>';
    echo '<td style="padding:10px;text-align:right;font-weight:bold;" class="pengeluaran">Rp ' . number_format($total_pengeluaran_display, 0, ',', '.') . '</td>';
    echo '</tr>';
    
} else {
    echo '<tr>';
    echo '<td colspan="6" style="padding:20px;text-align:center;font-style:italic;color:#666;">Tidak ada data pengeluaran untuk periode ini</td>';
    echo '</tr>';
}

echo '<tr><td colspan="6" style="padding:15px;"></td></tr>';

// ============================================================================
// 4. ANALISIS DAN CATATAN - DITAMPILKAN TERAKHIR
// ============================================================================

echo '<tr>';
echo '<td colspan="6" style="background:#e0e7ff;padding:10px;font-weight:bold;font-size:14px;text-align:center;">ANALISIS & CATATAN</td>';
echo '</tr>';

echo '<tr>';
echo '<td colspan="6" style="padding:10px;">';
echo '• Total Pemasukan: <span class="pemasukan">Rp ' . number_format($total_pemasukan_transaksi, 0, ',', '.') . '</span><br>';
echo '• Total Pengeluaran: <span class="pengeluaran">Rp ' . number_format($total_pengeluaran, 0, ',', '.') . '</span><br>';
echo '• ' . ($laba_rugi >= 0 ? 'Laba Bersih' : 'Rugi Bersih') . ': <span class="' . ($laba_rugi >= 0 ? 'laba' : 'rugi') . '">Rp ' . number_format(abs($laba_rugi), 0, ',', '.') . '</span>';
echo '</td>';
echo '</tr>';

echo '<tr><td colspan="6" style="padding:10px;"></td></tr>';

// Catatan Kaki
echo '<tr>';
echo '<td colspan="6" style="padding:10px;font-size:11px;color:#666;font-style:italic;">';
echo 'Catatan:<br>';
echo '1. Laporan ini dihasilkan otomatis dari Sistem Parapatan Tailor dan sah sebagai dokumen resmi.<br>';
echo '2. Data pemasukan dihitung dari transaksi pembayaran yang telah dilakukan pelanggan.<br>';
echo '3. Data pengeluaran mencakup semua biaya operasional dan non-operasional yang tercatat.';
echo '</td>';
echo '</tr>';

echo '</table>';

// Footer
echo '<div style="margin-top:20px;text-align:center;color:#666;font-size:12px;font-style:italic;">';
echo 'Jl. Baru Awirarangan No.123, Kecamatan Kuningan, Kabupaten Kuningan, Provinsi Jawa Barat | Telp: (0232) 123456 | Email: info@parapatantailor.com';
echo '</div>';

echo '</body></html>';

exit;
?>