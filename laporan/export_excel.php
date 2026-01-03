<?php
require_once '../config/database.php';
check_login();
check_role(['admin', 'pemilik']);

// Ambil parameter
$type = $_GET['type'] ?? 'keuangan';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Set headers untuk download Excel
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename="laporan_' . $type . '_' . date('Ymd') . '.xls"');
header('Cache-Control: max-age=0');

echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        table { border-collapse: collapse; width: 100%; }
        th { background-color: #4f46e5; color: white; font-weight: bold; padding: 8px; border: 1px solid #ddd; }
        td { padding: 6px; border: 1px solid #ddd; }
        .number { text-align: right; }
        .center { text-align: center; }
        .summary { background-color: #f8f9fa; font-weight: bold; }
    </style>
</head>
<body>';

switch($type) {
    case 'keuangan':
        // Query data keuangan
        $sql_pendapatan = "SELECT COALESCE(SUM(jumlah_bayar), 0) as total_pendapatan 
                          FROM data_transaksi 
                          WHERE status_pesanan = 'lunas' 
                          AND DATE(created_at) BETWEEN ? AND ?";
        $total_pendapatan = getSingle($sql_pendapatan, [$start_date, $end_date])['total_pendapatan'];
        
        $sql_pengeluaran = "SELECT COALESCE(SUM(jumlah), 0) as total_pengeluaran 
                           FROM data_pengeluaran 
                           WHERE DATE(tanggal) BETWEEN ? AND ?";
        $total_pengeluaran = getSingle($sql_pengeluaran, [$start_date, $end_date])['total_pengeluaran'];
        
        $laba_rugi = $total_pendapatan - $total_pengeluaran;
        
        $sql_detail_pengeluaran = "SELECT * FROM data_pengeluaran 
                                  WHERE DATE(tanggal) BETWEEN ? AND ?
                                  ORDER BY tanggal DESC";
        $pengeluaran = getAll($sql_detail_pengeluaran, [$start_date, $end_date]);
        
        echo '<table border="1">';
        echo '<tr><th colspan="2">LAPORAN KEUANGAN</th></tr>';
        echo '<tr><td colspan="2"><b>Periode: ' . date('d/m/Y', strtotime($start_date)) . ' - ' . date('d/m/Y', strtotime($end_date)) . '</b></td></tr>';
        echo '<tr><td colspan="2">&nbsp;</td></tr>';
        
        echo '<tr class="summary">';
        echo '<td>Total Pendapatan</td>';
        echo '<td class="number">Rp ' . number_format($total_pendapatan, 0, ',', '.') . '</td>';
        echo '</tr>';
        
        echo '<tr class="summary">';
        echo '<td>Total Pengeluaran</td>';
        echo '<td class="number">Rp ' . number_format($total_pengeluaran, 0, ',', '.') . '</td>';
        echo '</tr>';
        
        echo '<tr class="summary">';
        echo '<td>Laba/Rugi</td>';
        echo '<td class="number">Rp ' . number_format($laba_rugi, 0, ',', '.') . '</td>';
        echo '</tr>';
        
        if (!empty($pengeluaran)) {
            echo '<tr><td colspan="2">&nbsp;</td></tr>';
            echo '<tr><th colspan="5">DETAIL PENGELUARAN</th></tr>';
            echo '<tr>';
            echo '<th>No</th>';
            echo '<th>Tanggal</th>';
            echo '<th>Kategori</th>';
            echo '<th>Jumlah</th>';
            echo '<th>Keterangan</th>';
            echo '</tr>';
            
            $no = 1;
            $total = 0;
            foreach ($pengeluaran as $p) {
                echo '<tr>';
                echo '<td class="center">' . $no++ . '</td>';
                echo '<td class="center">' . date('d/m/Y', strtotime($p['tanggal'])) . '</td>';
                echo '<td>' . $p['kategori'] . '</td>';
                echo '<td class="number">Rp ' . number_format($p['jumlah'], 0, ',', '.') . '</td>';
                echo '<td>' . $p['keterangan'] . '</td>';
                echo '</tr>';
                $total += $p['jumlah'];
            }
            
            echo '<tr class="summary">';
            echo '<td colspan="3"><b>Total Pengeluaran</b></td>';
            echo '<td class="number"><b>Rp ' . number_format($total, 0, ',', '.') . '</b></td>';
            echo '<td>&nbsp;</td>';
            echo '</tr>';
        }
        echo '</table>';
        break;
        
    case 'pesanan':
        // Query data pesanan
        $sql_pesanan = "SELECT p.*, pel.nama as nama_pelanggan, k.nama_karyawan,
                               COUNT(*) as total_pesanan,
                               SUM(p.total_harga) as total_omzet
                        FROM data_pesanan p
                        LEFT JOIN data_pelanggan pel ON p.id_pelanggan = pel.id_pelanggan
                        LEFT JOIN data_karyawan k ON p.id_karyawan = k.id_karyawan
                        WHERE DATE(p.tgl_pesanan) BETWEEN ? AND ?
                        GROUP BY p.id_pesanan
                        ORDER BY p.tgl_pesanan DESC";
        $pesanan = getAll($sql_pesanan, [$start_date, $end_date]);
        
        // Statistik
        $sql_stat_pesanan = "SELECT 
                             COUNT(*) as total_pesanan,
                             SUM(total_harga) as total_omzet
                             FROM data_pesanan 
                             WHERE DATE(tgl_pesanan) BETWEEN ? AND ?";
        $stat = getSingle($sql_stat_pesanan, [$start_date, $end_date]);
        
        echo '<table border="1">';
        echo '<tr><th colspan="7">LAPORAN PESANAN</th></tr>';
        echo '<tr><td colspan="7"><b>Periode: ' . date('d/m/Y', strtotime($start_date)) . ' - ' . date('d/m/Y', strtotime($end_date)) . '</b></td></tr>';
        echo '<tr><td colspan="7">&nbsp;</td></tr>';
        
        echo '<tr class="summary">';
        echo '<td colspan="3">Total Pesanan</td>';
        echo '<td colspan="4">' . $stat['total_pesanan'] . ' pesanan</td>';
        echo '</tr>';
        
        echo '<tr class="summary">';
        echo '<td colspan="3">Total Omzet</td>';
        echo '<td colspan="4" class="number">Rp ' . number_format($stat['total_omzet'], 0, ',', '.') . '</td>';
        echo '</tr>';
        
        if (!empty($pesanan)) {
            echo '<tr><td colspan="7">&nbsp;</td></tr>';
            echo '<tr><th colspan="7">DETAIL PESANAN</th></tr>';
            echo '<tr>';
            echo '<th>No</th>';
            echo '<th>ID Pesanan</th>';
            echo '<th>Pelanggan</th>';
            echo '<th>Karyawan</th>';
            echo '<th>Jenis Pakaian</th>';
            echo '<th>Tanggal Pesan</th>';
            echo '<th>Total Harga</th>';
            echo '<th>Status</th>';
            echo '</tr>';
            
            $no = 1;
            $total_omzet = 0;
            foreach ($pesanan as $p) {
                $status_text = '';
                switch($p['status_pesanan']) {
                    case 'belum': $status_text = 'Belum Diproses'; break;
                    case 'dalam_proses': $status_text = 'Dalam Proses'; break;
                    case 'selesai': $status_text = 'Selesai'; break;
                }
                
                echo '<tr>';
                echo '<td class="center">' . $no++ . '</td>';
                echo '<td class="center">#' . $p['id_pesanan'] . '</td>';
                echo '<td>' . $p['nama_pelanggan'] . '</td>';
                echo '<td>' . $p['nama_karyawan'] . '</td>';
                echo '<td>' . $p['jenis_pakaian'] . '</td>';
                echo '<td class="center">' . date('d/m/Y', strtotime($p['tgl_pesanan'])) . '</td>';
                echo '<td class="number">Rp ' . number_format($p['total_harga'], 0, ',', '.') . '</td>';
                echo '<td class="center">' . $status_text . '</td>';
                echo '</tr>';
                $total_omzet += $p['total_harga'];
            }
            
            echo '<tr class="summary">';
            echo '<td colspan="6"><b>Total Omzet</b></td>';
            echo '<td class="number"><b>Rp ' . number_format($total_omzet, 0, ',', '.') . '</b></td>';
            echo '<td>&nbsp;</td>';
            echo '</tr>';
        }
        echo '</table>';
        break;
        
    case 'pelanggan':
        // Query data pelanggan
        $sql_pelanggan = "SELECT pel.*, 
                                 COUNT(p.id_pesanan) as total_pesanan,
                                 SUM(p.total_harga) as total_belanja,
                                 AVG(p.total_harga) as rata_rata_belanja
                          FROM data_pelanggan pel
                          LEFT JOIN data_pesanan p ON pel.id_pelanggan = p.id_pelanggan
                          WHERE (p.tgl_pesanan IS NULL OR DATE(p.tgl_pesanan) BETWEEN ? AND ?)
                          GROUP BY pel.id_pelanggan
                          ORDER BY total_belanja DESC";
        $pelanggan = getAll($sql_pelanggan, [$start_date, $end_date]);
        
        echo '<table border="1">';
        echo '<tr><th colspan="6">LAPORAN PELANGGAN</th></tr>';
        echo '<tr><td colspan="6"><b>Periode: ' . date('d/m/Y', strtotime($start_date)) . ' - ' . date('d/m/Y', strtotime($end_date)) . '</b></td></tr>';
        echo '<tr><td colspan="6">&nbsp;</td></tr>';
        
        echo '<tr class="summary">';
        echo '<td colspan="3">Total Pelanggan</td>';
        echo '<td colspan="3">' . count($pelanggan) . ' pelanggan</td>';
        echo '</tr>';
        
        $total_omzet = array_sum(array_column($pelanggan, 'total_belanja'));
        echo '<tr class="summary">';
        echo '<td colspan="3">Total Omzet</td>';
        echo '<td colspan="3" class="number">Rp ' . number_format($total_omzet, 0, ',', '.') . '</td>';
        echo '</tr>';
        
        if (!empty($pelanggan)) {
            echo '<tr><td colspan="6">&nbsp;</td></tr>';
            echo '<tr><th colspan="6">DATA PELANGGAN</th></tr>';
            echo '<tr>';
            echo '<th>No</th>';
            echo '<th>Nama Pelanggan</th>';
            echo '<th>Telepon</th>';
            echo '<th>Alamat</th>';
            echo '<th>Total Pesanan</th>';
            echo '<th>Total Belanja</th>';
            echo '</tr>';
            
            $no = 1;
            foreach ($pelanggan as $plg) {
                echo '<tr>';
                echo '<td class="center">' . $no++ . '</td>';
                echo '<td>' . $plg['nama'] . '</td>';
                echo '<td>' . $plg['no_hp'] . '</td>';
                echo '<td>' . $plg['alamat'] . '</td>';
                echo '<td class="center">' . ($plg['total_pesanan'] ?? 0) . '</td>';
                echo '<td class="number">Rp ' . number_format($plg['total_belanja'] ?? 0, 0, ',', '.') . '</td>';
                echo '</tr>';
            }
        }
        echo '</table>';
        break;
}

echo '</body></html>';
?>