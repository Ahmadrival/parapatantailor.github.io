<?php
// transaksi/datatransaksi.php
require_once '../config/database.php';
check_login();
check_role(['admin', 'pemilik']);

// Redirect ke transaksi.php untuk konsistensi
header("Location: transaksi.php");
exit();
?>