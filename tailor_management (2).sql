-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 23 Des 2025 pada 17.40
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `tailor_management`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `data_karyawan`
--

CREATE TABLE `data_karyawan` (
  `id_karyawan` int(11) NOT NULL,
  `nama_karyawan` varchar(100) NOT NULL,
  `jabatan` varchar(50) DEFAULT NULL,
  `no_hp` varchar(15) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `gaji` decimal(12,2) DEFAULT NULL,
  `tanggal_masuk` date DEFAULT NULL,
  `status` enum('aktif','nonaktif') DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `data_karyawan`
--

INSERT INTO `data_karyawan` (`id_karyawan`, `nama_karyawan`, `jabatan`, `no_hp`, `alamat`, `gaji`, `tanggal_masuk`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Andi Wijaya', 'pegawai', '081234567890', 'Jl. Baru Awirarangan RT/RW 03/05 Kelurahan Awirarangan', 500000.00, '2023-01-15', 'aktif', '2025-11-06 14:35:35', '2025-12-17 07:26:59'),
(2, 'Iip Zaenal Aripin', 'pemilik', '087386448286', 'Jl.Awirarangan No1234', NULL, '2025-11-01', 'aktif', '2025-12-23 12:02:49', '2025-12-23 12:02:49'),
(3, 'Maman Nur Abdurrohman', 'pegawai', '081234567890', 'Jln.Baru Awirarangan', 400000.00, '2025-11-02', 'aktif', '2025-12-17 06:52:39', '2025-12-23 12:03:08');

-- --------------------------------------------------------

--
-- Struktur dari tabel `data_pelanggan`
--

CREATE TABLE `data_pelanggan` (
  `id_pelanggan` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `no_hp` varchar(15) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `data_pelanggan`
--

INSERT INTO `data_pelanggan` (`id_pelanggan`, `nama`, `no_hp`, `alamat`, `created_at`) VALUES
(4, 'Sinta Ayu', '082211110004', 'Jl. Melati No.11', '2025-10-30 07:53:05'),
(5, 'Yoga Pratama', '082211110005', 'Jl. Kamboja No.12', '2025-10-30 07:53:05'),
(6, 'Rudi Hartono', '082211110006', 'Jl. Cempaka No.13', '2025-10-30 07:53:05'),
(7, 'Anita Sarii', '082211110007', 'Jl. Lingkar Timur Blok E RT/02/RW03 Kelurahan Sukamaju', '2025-10-30 07:53:05'),
(8, 'Bayu Saputra', '082211110008', 'Jl. Flamboyan No.15', '2025-10-30 07:53:05'),
(9, 'Cindy Oktaviani', '082211110009', 'Jl. Merpati No.16', '2025-10-30 07:53:05'),
(10, 'Doni Wahyu', '082211110010', 'Jl. Rajawali No.17', '2025-10-30 07:53:05'),
(32, 'Jamaludin', '089897878784', 'jdknkckdkj33', '2025-10-31 05:30:58'),
(33, 'Kosim', '019282977327', 'mcdcdyyu7388', '2025-10-31 05:31:29'),
(34, 'Lord suroso', '08983273675653', 'jalan jalannnnnnkjdg', '2025-10-31 06:32:44'),
(36, 'jajang', '0930893878863', 'kuningan', '2025-10-31 16:43:35'),
(37, 'Tiaz sakes', '089998867675', 'kuningan los angelse', '2025-10-31 16:58:41'),
(38, 'Silmi Nur Afifah', '085694267123', 'Awirarangan', '2025-11-10 06:35:46'),
(39, 'Rival', '073836775265623', 'kadatuanjhsajhsa', '2025-11-13 08:50:36'),
(40, 'fruikaa', '089876756654', 'jghghghgfgfgfgf', '2025-12-02 15:30:51');

-- --------------------------------------------------------

--
-- Struktur dari tabel `data_pengeluaran`
--

CREATE TABLE `data_pengeluaran` (
  `id_pengeluaran` int(11) NOT NULL,
  `kategori_pengeluaran` varchar(100) NOT NULL,
  `jumlah_pengeluaran` decimal(15,2) NOT NULL,
  `keterangan` text DEFAULT NULL,
  `tgl_pengeluaran` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `data_pengeluaran`
--

INSERT INTO `data_pengeluaran` (`id_pengeluaran`, `kategori_pengeluaran`, `jumlah_pengeluaran`, `keterangan`, `tgl_pengeluaran`, `created_at`) VALUES
(12, 'Operasional', 100000.00, 'keperluan bahan bahan campuran', '2025-12-23', '2025-12-23 12:41:55');

-- --------------------------------------------------------

--
-- Struktur dari tabel `data_pesanan`
--

CREATE TABLE `data_pesanan` (
  `id_pesanan` int(11) NOT NULL,
  `id_pelanggan` int(11) NOT NULL,
  `id_ukuran` int(11) DEFAULT NULL,
  `id_karyawan` int(11) DEFAULT NULL,
  `tgl_pesanan` date NOT NULL,
  `tgl_selesai` date DEFAULT NULL,
  `jenis_pakaian` varchar(100) DEFAULT NULL,
  `bahan` varchar(100) DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `total_harga` decimal(12,2) NOT NULL,
  `jumlah_bayar` decimal(12,2) DEFAULT 0.00,
  `sisa_bayar` decimal(12,2) DEFAULT 0.00,
  `total_kuantitas` int(11) DEFAULT 1,
  `status_pesanan` enum('belum','dalam_proses','selesai') DEFAULT 'belum',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `metode_pembayaran` varchar(20) DEFAULT 'tunai',
  `status_pembayaran` varchar(20) DEFAULT 'belum_bayar'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `data_pesanan`
--

INSERT INTO `data_pesanan` (`id_pesanan`, `id_pelanggan`, `id_ukuran`, `id_karyawan`, `tgl_pesanan`, `tgl_selesai`, `jenis_pakaian`, `bahan`, `catatan`, `total_harga`, `jumlah_bayar`, `sisa_bayar`, `total_kuantitas`, `status_pesanan`, `created_at`, `metode_pembayaran`, `status_pembayaran`) VALUES
(92, 8, NULL, 3, '2025-12-23', '2025-12-24', 'Kemeja', 'katun', '', 400000.00, 400000.00, 0.00, 2, 'selesai', '2025-12-23 12:31:34', 'qris', 'sebagian'),
(94, 40, NULL, 3, '2025-12-21', '2025-12-21', 'Setelan', 'katun', '', 900000.00, 900000.00, 0.00, 4, 'belum', '2025-12-23 13:38:49', 'tunai', 'sebagian'),
(95, 36, NULL, 3, '2025-12-23', '2025-12-26', 'Kemeja', 'katun', NULL, 100000.00, 100000.00, 0.00, 1, 'belum', '2025-12-23 15:02:02', 'transfer', 'sebagian');

-- --------------------------------------------------------

--
-- Struktur dari tabel `data_transaksi`
--

CREATE TABLE `data_transaksi` (
  `id_transaksi` int(11) NOT NULL,
  `id_pesanan` int(11) DEFAULT NULL,
  `tgl_transaksi` date NOT NULL,
  `jenis_transaksi` enum('pembayaran','pengembalian') DEFAULT 'pembayaran',
  `jumlah_bayar` decimal(15,2) NOT NULL,
  `metode_bayar` varchar(50) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `status_pembayaran` enum('belum_bayar','dp','lunas') DEFAULT 'belum_bayar',
  `status_pesanan` enum('pending','lunas','gagal') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `data_transaksi`
--

INSERT INTO `data_transaksi` (`id_transaksi`, `id_pesanan`, `tgl_transaksi`, `jenis_transaksi`, `jumlah_bayar`, `metode_bayar`, `keterangan`, `status_pembayaran`, `status_pesanan`, `created_at`) VALUES
(100, 92, '2025-12-23', 'pembayaran', 200000.00, 'Auto System', 'Pembayaran otomatis dari sistem', 'lunas', 'pending', '2025-12-23 12:31:53'),
(102, 94, '2025-12-23', 'pembayaran', 400000.00, 'Auto System', 'Pembayaran otomatis dari sistem', 'lunas', 'pending', '2025-12-23 13:39:06'),
(103, 92, '2025-12-23', 'pembayaran', 200000.00, 'Tunai', '', 'lunas', 'pending', '2025-12-23 14:19:06'),
(104, 94, '2025-12-23', 'pembayaran', 500000.00, 'Debit', '', 'lunas', 'pending', '2025-12-23 14:53:03'),
(105, 95, '2025-12-23', 'pembayaran', 30000.00, 'Auto System', 'Pembayaran otomatis dari sistem', 'lunas', 'pending', '2025-12-23 15:02:07'),
(106, 95, '2025-12-23', 'pembayaran', 70000.00, 'QRIS', '', 'lunas', 'pending', '2025-12-23 15:05:13');

-- --------------------------------------------------------

--
-- Struktur dari tabel `logo_settings`
--

CREATE TABLE `logo_settings` (
  `id` int(11) NOT NULL,
  `logo_path` varchar(255) NOT NULL,
  `logo_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `logo_settings`
--

INSERT INTO `logo_settings` (`id`, `logo_path`, `logo_name`, `created_at`, `updated_at`) VALUES
(1, 'assets/images/logo_custom_1765642094.png', 'Parapatan Tailor', '2025-12-13 16:08:14', '2025-12-13 16:08:14');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pesanan_items`
--

CREATE TABLE `pesanan_items` (
  `id_item` int(11) NOT NULL,
  `id_pesanan` int(11) NOT NULL,
  `jenis_pakaian` varchar(255) NOT NULL,
  `bahan` varchar(100) DEFAULT NULL,
  `jumlah` int(11) DEFAULT 1,
  `harga_satuan` decimal(15,2) DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data untuk tabel `pesanan_items`
--

INSERT INTO `pesanan_items` (`id_item`, `id_pesanan`, `jenis_pakaian`, `bahan`, `jumlah`, `harga_satuan`, `catatan`, `created_at`) VALUES
(27, 95, 'Kemeja', 'katun', 1, 100000.00, 'wkwkwkkw', '2025-12-23 15:02:02'),
(28, 94, 'Setelan', 'katun', 1, 200000.00, 'hahahaha', '2025-12-23 15:16:22'),
(29, 94, 'Setelan', 'WOLL', 1, 400000.00, 'ggggg', '2025-12-23 15:16:22'),
(30, 94, 'Baju Muslim', 'jas', 1, 100000.00, 'ssss', '2025-12-23 15:16:22'),
(31, 94, 'Jas', 'muslim', 1, 200000.00, 'ddddddddd', '2025-12-23 15:16:22'),
(40, 92, 'Kemeja', 'katun', 1, 100000.00, 'tangan panjang bermotif', '2025-12-23 16:02:36'),
(41, 92, 'Setelan', 'WOLL', 1, 300000.00, 'tangan pendek', '2025-12-23 16:02:36');

-- --------------------------------------------------------

--
-- Struktur dari tabel `ukuran_atasan`
--

CREATE TABLE `ukuran_atasan` (
  `id_ukuran_atasan` int(11) NOT NULL,
  `id_pesanan` int(11) NOT NULL,
  `id_pelanggan` int(11) NOT NULL,
  `krah` decimal(8,2) DEFAULT NULL,
  `pundak` decimal(8,2) DEFAULT NULL,
  `tangan` decimal(8,2) DEFAULT NULL,
  `ld_lp` decimal(8,2) DEFAULT NULL,
  `badan` decimal(8,2) DEFAULT NULL,
  `pinggang` decimal(8,2) DEFAULT NULL,
  `pinggul` decimal(8,2) DEFAULT NULL,
  `panjang` decimal(8,2) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data untuk tabel `ukuran_atasan`
--

INSERT INTO `ukuran_atasan` (`id_ukuran_atasan`, `id_pesanan`, `id_pelanggan`, `krah`, `pundak`, `tangan`, `ld_lp`, `badan`, `pinggang`, `pinggul`, `panjang`, `keterangan`, `created_at`, `updated_at`) VALUES
(106, 95, 36, 22.00, 22.00, 22.00, 22.00, 11.00, 11.00, 11.00, 11.00, 'wkwkwkkw', '2025-12-23 15:02:02', '2025-12-23 15:02:02'),
(107, 94, 40, 33.00, 22.00, 22.00, 44.00, 33.00, 22.00, 22.00, 33.00, 'hahahaha', '2025-12-23 15:16:22', '2025-12-23 15:16:22'),
(108, 94, 40, 22.00, 22.00, 22.00, 22.00, 22.00, 22.00, 22.00, 22.00, 'ggggg', '2025-12-23 15:16:22', '2025-12-23 15:16:22'),
(109, 94, 40, 22.00, 22.00, 33.00, 22.00, 22.00, 22.00, 22.00, 22.00, 'ssss', '2025-12-23 15:16:22', '2025-12-23 15:16:22'),
(110, 94, 40, 22.00, 22.00, 22.00, 22.00, 22.00, 22.00, 22.00, 22.00, 'ddddddddd', '2025-12-23 15:16:22', '2025-12-23 15:16:22'),
(119, 92, 8, 33.00, 22.00, 11.00, 22.00, 44.00, 55.00, 66.00, 77.00, 'tangan panjang bermotif', '2025-12-23 16:02:36', '2025-12-23 16:02:36'),
(120, 92, 8, 22.00, 33.00, 22.00, 22.00, 22.00, 22.00, 22.00, 22.00, 'jjjjjjjjjjkl;lklkmk', '2025-12-23 16:02:36', '2025-12-23 16:02:36');

-- --------------------------------------------------------

--
-- Struktur dari tabel `ukuran_bawahan`
--

CREATE TABLE `ukuran_bawahan` (
  `id_ukuran_bawahan` int(11) NOT NULL,
  `id_pesanan` int(11) NOT NULL,
  `id_pelanggan` int(11) NOT NULL,
  `pinggang` decimal(8,2) DEFAULT NULL,
  `pinggul` decimal(8,2) DEFAULT NULL,
  `kres` decimal(8,2) DEFAULT NULL,
  `paha` decimal(8,2) DEFAULT NULL,
  `lutut` decimal(8,2) DEFAULT NULL,
  `l_bawah` decimal(8,2) DEFAULT NULL,
  `panjang` decimal(8,2) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data untuk tabel `ukuran_bawahan`
--

INSERT INTO `ukuran_bawahan` (`id_ukuran_bawahan`, `id_pesanan`, `id_pelanggan`, `pinggang`, `pinggul`, `kres`, `paha`, `lutut`, `l_bawah`, `panjang`, `keterangan`, `created_at`, `updated_at`) VALUES
(79, 94, 40, 44.00, 33.00, 22.00, 22.00, 33.00, 22.00, 22.00, 'mdkmk', '2025-12-23 15:16:22', '2025-12-23 15:16:22'),
(80, 94, 40, 33.00, 22.00, 22.00, 22.00, 22.00, 22.00, 22.00, '', '2025-12-23 15:16:22', '2025-12-23 15:16:22'),
(85, 92, 8, 33.00, 44.00, 66.00, 55.00, 77.00, 88.00, 99.00, 'celana panjang pensil', '2025-12-23 16:02:36', '2025-12-23 16:02:36'),
(86, 92, 8, 33.00, 33.00, 33.00, 33.00, 33.00, 33.00, 33.00, 'awwwww', '2025-12-23 16:02:36', '2025-12-23 16:02:36');

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id_user` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `foto_profil` varchar(255) DEFAULT NULL,
  `nama_lengkap` varchar(100) DEFAULT NULL,
  `role` enum('admin','pemilik','pegawai') NOT NULL,
  `status` enum('aktif','non-aktif') DEFAULT 'aktif',
  `id_karyawan` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id_user`, `username`, `password`, `email`, `no_hp`, `alamat`, `foto_profil`, `nama_lengkap`, `role`, `status`, `id_karyawan`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin123', 'andiwijaya12@gmail.com', '081234567890', 'Jln. Baru Awirarangan Kel.Awirarangan Kec.Kuningan Kab.Kuningan', 'profile_1_1766373233.png', 'Andi Wijaya', 'admin', 'aktif', NULL, '2025-10-30 06:51:56', '2025-12-22 03:13:53'),
(2, 'pemilik', 'pemilik123', 'iipzaenalaripin@gmail.com', '081234567890', 'Jln.Baru Awirarangan', NULL, 'Iip Zaenal Aripin', 'pemilik', 'aktif', NULL, '2025-10-30 06:51:56', '2025-11-13 07:19:47'),
(3, 'pegawai', 'pegawai123', 'mamannurabdurrohman@gmail.com', '081234567890', 'Jln.Baru Awirarangan', 'profile_3_1766012445.png', 'Maman Nur Abdurrohman', 'pegawai', 'aktif', 1, '2025-10-30 06:51:56', '2025-12-17 23:00:45');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `data_karyawan`
--
ALTER TABLE `data_karyawan`
  ADD PRIMARY KEY (`id_karyawan`);

--
-- Indeks untuk tabel `data_pelanggan`
--
ALTER TABLE `data_pelanggan`
  ADD PRIMARY KEY (`id_pelanggan`);

--
-- Indeks untuk tabel `data_pengeluaran`
--
ALTER TABLE `data_pengeluaran`
  ADD PRIMARY KEY (`id_pengeluaran`);

--
-- Indeks untuk tabel `data_pesanan`
--
ALTER TABLE `data_pesanan`
  ADD PRIMARY KEY (`id_pesanan`),
  ADD KEY `id_pelanggan` (`id_pelanggan`),
  ADD KEY `id_ukuran` (`id_ukuran`),
  ADD KEY `id_karyawan` (`id_karyawan`);

--
-- Indeks untuk tabel `data_transaksi`
--
ALTER TABLE `data_transaksi`
  ADD PRIMARY KEY (`id_transaksi`),
  ADD KEY `id_pesanan` (`id_pesanan`);

--
-- Indeks untuk tabel `logo_settings`
--
ALTER TABLE `logo_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `pesanan_items`
--
ALTER TABLE `pesanan_items`
  ADD PRIMARY KEY (`id_item`),
  ADD KEY `idx_pesanan_items_id_pesanan` (`id_pesanan`);

--
-- Indeks untuk tabel `ukuran_atasan`
--
ALTER TABLE `ukuran_atasan`
  ADD PRIMARY KEY (`id_ukuran_atasan`),
  ADD KEY `id_pesanan` (`id_pesanan`),
  ADD KEY `id_pelanggan` (`id_pelanggan`);

--
-- Indeks untuk tabel `ukuran_bawahan`
--
ALTER TABLE `ukuran_bawahan`
  ADD PRIMARY KEY (`id_ukuran_bawahan`),
  ADD KEY `id_pesanan` (`id_pesanan`),
  ADD KEY `id_pelanggan` (`id_pelanggan`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id_user`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `id_karyawan` (`id_karyawan`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `data_karyawan`
--
ALTER TABLE `data_karyawan`
  MODIFY `id_karyawan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT untuk tabel `data_pelanggan`
--
ALTER TABLE `data_pelanggan`
  MODIFY `id_pelanggan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT untuk tabel `data_pengeluaran`
--
ALTER TABLE `data_pengeluaran`
  MODIFY `id_pengeluaran` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT untuk tabel `data_pesanan`
--
ALTER TABLE `data_pesanan`
  MODIFY `id_pesanan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=96;

--
-- AUTO_INCREMENT untuk tabel `data_transaksi`
--
ALTER TABLE `data_transaksi`
  MODIFY `id_transaksi` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=107;

--
-- AUTO_INCREMENT untuk tabel `logo_settings`
--
ALTER TABLE `logo_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `pesanan_items`
--
ALTER TABLE `pesanan_items`
  MODIFY `id_item` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT untuk tabel `ukuran_atasan`
--
ALTER TABLE `ukuran_atasan`
  MODIFY `id_ukuran_atasan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=121;

--
-- AUTO_INCREMENT untuk tabel `ukuran_bawahan`
--
ALTER TABLE `ukuran_bawahan`
  MODIFY `id_ukuran_bawahan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=87;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id_user` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `data_pesanan`
--
ALTER TABLE `data_pesanan`
  ADD CONSTRAINT `data_pesanan_ibfk_1` FOREIGN KEY (`id_pelanggan`) REFERENCES `data_pelanggan` (`id_pelanggan`),
  ADD CONSTRAINT `fk_pesanan_karyawan_user` FOREIGN KEY (`id_karyawan`) REFERENCES `users` (`id_user`);

--
-- Ketidakleluasaan untuk tabel `data_transaksi`
--
ALTER TABLE `data_transaksi`
  ADD CONSTRAINT `data_transaksi_ibfk_1` FOREIGN KEY (`id_pesanan`) REFERENCES `data_pesanan` (`id_pesanan`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `pesanan_items`
--
ALTER TABLE `pesanan_items`
  ADD CONSTRAINT `pesanan_items_ibfk_1` FOREIGN KEY (`id_pesanan`) REFERENCES `data_pesanan` (`id_pesanan`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `ukuran_atasan`
--
ALTER TABLE `ukuran_atasan`
  ADD CONSTRAINT `ukuran_atasan_ibfk_1` FOREIGN KEY (`id_pesanan`) REFERENCES `data_pesanan` (`id_pesanan`) ON DELETE CASCADE,
  ADD CONSTRAINT `ukuran_atasan_ibfk_2` FOREIGN KEY (`id_pelanggan`) REFERENCES `data_pelanggan` (`id_pelanggan`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `ukuran_bawahan`
--
ALTER TABLE `ukuran_bawahan`
  ADD CONSTRAINT `ukuran_bawahan_ibfk_1` FOREIGN KEY (`id_pesanan`) REFERENCES `data_pesanan` (`id_pesanan`) ON DELETE CASCADE,
  ADD CONSTRAINT `ukuran_bawahan_ibfk_2` FOREIGN KEY (`id_pelanggan`) REFERENCES `data_pelanggan` (`id_pelanggan`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
