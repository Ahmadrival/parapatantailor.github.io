<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/parapatan_tailor/assets/images/logoterakhir.png">
    <link rel="shortcut icon" href="/parapatan_tailor/assets/images/logoterakhir.png">
    <link rel="apple-touch-icon" href="/parapatan_tailor/assets/images/logoterakhir.png">
    
    <!-- Judul Halaman -->
    <title>Parapatan Tailor Management System</title>

<?php

// Set timezone ke Indonesia
date_default_timezone_set('Asia/Jakarta');

// Data hari dan bulan dalam Bahasa Indonesia
$hari_indonesia = array(
    'Sunday' => 'Minggu',
    'Monday' => 'Senin', 
    'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu'
);

$bulan_indonesia = array(
    'January' => 'Januari',
    'February' => 'Februari',
    'March' => 'Maret',
    'April' => 'April',
    'May' => 'Mei',
    'June' => 'Juni',
    'July' => 'Juli',
    'August' => 'Agustus',
    'September' => 'September',
    'October' => 'Oktober',
    'November' => 'November',
    'December' => 'Desember'
);

// Ambil data waktu awal
$sekarang = new DateTime();
$hari_inggris = $sekarang->format('l');
$hari = $hari_indonesia[$hari_inggris];
$tanggal = $sekarang->format('d');
$bulan_inggris = $sekarang->format('F');
$bulan = $bulan_indonesia[$bulan_inggris];
$tahun = $sekarang->format('Y');

// Format tanggal lengkap
$tanggal_lengkap = "$hari, $tanggal $bulan $tahun";

// Salam berdasarkan waktu
$jam = (int)$sekarang->format('H');
if ($jam >= 5 && $jam < 12) {
    $salam = 'Selamat Pagi';
} elseif ($jam >= 12 && $jam < 15) {
    $salam = 'Selamat Siang';
} elseif ($jam >= 15 && $jam < 19) {
    $salam = 'Selamat Sore';
} else {
    $salam = 'Selamat Malam';
}

// User info
$nama_user = $_SESSION['nama'] ?? $_SESSION['username'] ?? 'User';
$role_user = ucfirst($_SESSION['role'] ?? 'User');
$initial = strtoupper(substr($nama_user, 0, 1));
?>

<div class="compact-header" style="position: fixed; top: 15px; right: 15px; background: white; padding: 12px 15px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); z-index: 1000; border-left: 3px solid #8a2be2; min-width: 280px;">
    <div class="header-content" style="display: flex; align-items: center; justify-content: space-between; gap: 15px;">
        <!-- Waktu dan Tanggal dalam satu baris -->
        <div class="datetime-compact" style="flex: 1;">
            <div class="datetime-line" style="color: #2c3e50; font-weight: 500; font-size: 0.85rem; line-height: 1.3;">
                <span class="date-part" style="color: #6a0dad;"><?php echo $hari; ?>, <?php echo $tanggal; ?> <?php echo $bulan; ?> <?php echo $tahun; ?></span>
                <span class="time-part" style="color: #28a745; font-weight: 600; margin-left: 8px;">
                    <span id="live-time"><?php echo $sekarang->format('H:i:s'); ?></span> WIB
                </span>
            </div>
            <div class="greeting-line" style="color: #6c757d; font-style: italic; font-size: 0.75rem; margin-top: 2px;">
                <?php echo $salam; ?> | <span style="color: #8a2be2; font-weight: 500;"><?php echo htmlspecialchars($nama_user); ?></span>
            </div>
        </div>
        
        <!-- User Profile Avatar kecil -->
        <div class="user-avatar-compact" style="width: 32px; height: 32px; background: linear-gradient(135deg, #8a2be2, #9370db); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 0.9rem; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2); cursor: pointer;" title="<?php echo htmlspecialchars($nama_user); ?> (<?php echo $role_user; ?>)">
            <?php echo $initial; ?>
        </div>
    </div>
</div>

<script>
// Fungsi untuk update waktu real-time
function updateLiveTime() {
    const now = new Date();
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const seconds = String(now.getSeconds()).padStart(2, '0');
    const timeString = `${hours}:${minutes}:${seconds}`;
    
    const timeElement = document.getElementById('live-time');
    if (timeElement) {
        timeElement.textContent = timeString;
    }
}

// Update waktu setiap detik
setInterval(updateLiveTime, 1000);

// Update salam berdasarkan waktu
function updateGreeting() {
    const hour = new Date().getHours();
    let salam = '';
    
    if (hour >= 5 && hour < 12) {
        salam = 'Selamat Pagi';
    } else if (hour >= 12 && hour < 15) {
        salam = 'Selamat Siang';
    } else if (hour >= 15 && hour < 19) {
        salam = 'Selamat Sore';
    } else {
        salam = 'Selamat Malam';
    }
    
    const greetingElement = document.querySelector('.greeting-line');
    if (greetingElement) {
        const currentText = greetingElement.textContent;
        const userName = currentText.split('|')[1]?.trim() || '';
        greetingElement.textContent = salam + ' | ' + userName;
    }
}

// Update salam setiap 30 detik
setInterval(updateGreeting, 30000);

// Efek hover pada header
document.addEventListener('DOMContentLoaded', function() {
    const header = document.querySelector('.compact-header');
    if (header) {
        header.addEventListener('mouseenter', function() {
            this.style.boxShadow = '0 4px 15px rgba(0, 0, 0, 0.15)';
            this.style.transform = 'translateY(-2px)';
        });
        
        header.addEventListener('mouseleave', function() {
            this.style.boxShadow = '0 2px 10px rgba(0, 0, 0, 0.1)';
            this.style.transform = 'translateY(0)';
        });
    }
});
</script>