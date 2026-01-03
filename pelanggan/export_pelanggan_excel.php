<?php
require_once '../config/database.php';
check_login();
check_role(['admin', 'pemilik']);

// Ambil parameter filter
$search = $_GET['search'] ?? '';
$filter_tanggal = $_GET['tanggal'] ?? '';

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

// Set headers untuk download Excel
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename="data_pelanggan_' . date('Ymd') . '.xls"');
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

echo '<table border="1">';
echo '<tr><th colspan="5">DATA PELANGGAN - APOTEK DEKA MEDIKA</th></tr>';
echo '<tr><td colspan="5"><b>Tanggal Export: ' . date('d/m/Y H:i:s') . '</b></td></tr>';

if (!empty($search) || !empty($filter_tanggal)) {
    echo '<tr><td colspan="5"><b>Filter: ';
    if (!empty($search)) echo 'Pencarian: "' . htmlspecialchars($search) . '"';
    if (!empty($filter_tanggal)) {
        if (!empty($search)) echo ' dan ';
        echo 'Tanggal: ' . date('d/m/Y', strtotime($filter_tanggal));
    }
    echo '</b></td></tr>';
}

echo '<tr><td colspan="5">&nbsp;</td></tr>';
echo '<tr class="summary">';
echo '<td colspan="3">Total Pelanggan</td>';
echo '<td colspan="2">' . count($pelanggan) . ' pelanggan</td>';
echo '</tr>';

if (!empty($pelanggan)) {
    echo '<tr><td colspan="5">&nbsp;</td></tr>';
    echo '<tr><th colspan="5">DETAIL PELANGGAN</th></tr>';
    echo '<tr>';
    echo '<th>No</th>';
    echo '<th>Nama Pelanggan</th>';
    echo '<th>No HP</th>';
    echo '<th>Alamat</th>';
    echo '<th>Tanggal Daftar</th>';
    echo '</tr>';
    
    $no = 1;
    foreach ($pelanggan as $row) {
        echo '<tr>';
        echo '<td class="center">' . $no++ . '</td>';
        echo '<td>' . htmlspecialchars($row['nama']) . '</td>';
        echo '<td>' . htmlspecialchars($row['no_hp']) . '</td>';
        echo '<td>' . htmlspecialchars($row['alamat']) . '</td>';
        echo '<td class="center">' . date('d/m/Y H:i', strtotime($row['created_at'])) . '</td>';
        echo '</tr>';
    }
} else {
    echo '<tr><td colspan="5" style="text-align: center; color: #666; font-style: italic;">Tidak ada data pelanggan</td></tr>';
}

echo '</table>';
echo '</body></html>';
?>