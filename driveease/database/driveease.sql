-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 05, 2026 at 05:51 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `driveease`
--

-- --------------------------------------------------------

--
-- Table structure for table `gambar_mobil`
--

CREATE TABLE `gambar_mobil` (
  `id_gambar` int(11) NOT NULL,
  `id_mobil` int(11) NOT NULL,
  `nama_file` varchar(255) NOT NULL,
  `is_utama` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `gambar_mobil`
--

INSERT INTO `gambar_mobil` (`id_gambar`, `id_mobil`, `nama_file`, `is_utama`) VALUES
(1, 3, 'avanza.jpg', 1),
(2, 1, 'rush.jpg', 1),
(9, 5, 'c33dc5827b7f4610d5461fc9a38d3468.jpg', 1);

-- --------------------------------------------------------

--
-- Table structure for table `kategori_mobil`
--

CREATE TABLE `kategori_mobil` (
  `id_kategori` int(11) NOT NULL,
  `nama_kategori` varchar(80) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `kategori_mobil`
--

INSERT INTO `kategori_mobil` (`id_kategori`, `nama_kategori`) VALUES
(1, 'City Car'),
(2, 'MPV'),
(3, 'SUV');

-- --------------------------------------------------------

--
-- Table structure for table `mobil`
--

CREATE TABLE `mobil` (
  `id_mobil` int(11) NOT NULL,
  `id_kategori` int(11) DEFAULT NULL,
  `nama_mobil` varchar(100) NOT NULL,
  `nomor_plat` varchar(20) NOT NULL,
  `merk` varchar(80) NOT NULL,
  `tahun` year(4) DEFAULT NULL,
  `transmisi` enum('manual','matic') NOT NULL,
  `kapasitas` int(11) NOT NULL,
  `bahan_bakar` varchar(40) DEFAULT NULL,
  `harga_per_hari` decimal(12,2) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `status` enum('tersedia','nonaktif','perawatan') DEFAULT 'tersedia',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `mobil`
--

INSERT INTO `mobil` (`id_mobil`, `id_kategori`, `nama_mobil`, `nomor_plat`, `merk`, `tahun`, `transmisi`, `kapasitas`, `bahan_bakar`, `harga_per_hari`, `deskripsi`, `status`, `created_at`) VALUES
(1, 3, 'Toyota Rush', 'BH 123 AB', 'Toyota', '2024', 'manual', 7, 'Bensin', 450000.00, 'Toyota Rush merupakan mobil SUV yang dirancang untuk memberikan kenyamanan dan keamanan selama perjalanan. Memiliki desain eksterior yang sporty dan modern, serta interior yang luas.', 'tersedia', '2026-11-05 13:57:59'),
(3, 2, 'Avanza', 'BH 123 BC', 'Toyota', '2023', 'matic', 7, 'Bensin', 350000.00, 'Mobil keluarga yang nyaman untuk perjalanan.', 'tersedia', '2026-11-05 13:46:12'),
(5, 2, 'Kijang Innova', 'BH 123 CD', 'Toyota', '2022', 'matic', 7, 'Diesel', 600000.00, 'Mobil keluarga yang elegan, luas, dan nyaman untuk perjalanan jauh maupun kebutuhan bisnis.', 'tersedia', '2026-11-05 15:53:08');

-- --------------------------------------------------------

--
-- Table structure for table `pembayaran`
--

CREATE TABLE `pembayaran` (
  `id_pembayaran` int(11) NOT NULL,
  `id_booking` int(11) NOT NULL,
  `metode_pembayaran` varchar(60) DEFAULT NULL,
  `nominal` decimal(12,2) NOT NULL,
  `bukti_pembayaran` varchar(255) DEFAULT NULL,
  `status` enum('menunggu_verifikasi','berhasil','ditolak') DEFAULT 'menunggu_verifikasi',
  `tanggal_bayar` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pemesanan`
--

CREATE TABLE `pemesanan` (
  `id_booking` int(11) NOT NULL,
  `kode_booking` varchar(30) NOT NULL,
  `id_user` int(11) NOT NULL,
  `id_mobil` int(11) NOT NULL,
  `tanggal_mulai` date NOT NULL,
  `tanggal_selesai` date NOT NULL,
  `jumlah_hari` int(11) NOT NULL,
  `harga_per_hari` decimal(12,2) NOT NULL,
  `total_harga` decimal(12,2) NOT NULL,
  `status` enum('menunggu_pembayaran','menunggu_konfirmasi','dikonfirmasi','berlangsung','selesai','dibatalkan') DEFAULT 'menunggu_pembayaran',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pemesanan`
--

INSERT INTO `pemesanan` (`id_booking`, `kode_booking`, `id_user`, `id_mobil`, `tanggal_mulai`, `tanggal_selesai`, `jumlah_hari`, `harga_per_hari`, `total_harga`, `status`, `created_at`) VALUES
(5, 'DE-F3F756CF29', 3, 1, '2026-11-05', '2026-11-09', 4, 450000.00, 1800000.00, 'selesai', '2026-11-05 16:46:18');

-- --------------------------------------------------------

--
-- Table structure for table `pengembalian`
--

CREATE TABLE `pengembalian` (
  `id_pengembalian` int(11) NOT NULL,
  `id_booking` int(11) NOT NULL,
  `tanggal_pengembalian` datetime DEFAULT NULL,
  `jumlah_hari_aktual` int(11) NOT NULL DEFAULT 0,
  `kondisi_mobil` enum('baik','ada_kerusakan') DEFAULT NULL,
  `status_pengembalian` enum('menunggu_konfirmasi','disetujui','ditolak') NOT NULL DEFAULT 'menunggu_konfirmasi',
  `biaya_tambahan` decimal(12,2) DEFAULT 0.00,
  `total_biaya_akhir` decimal(12,2) NOT NULL DEFAULT 0.00,
  `catatan` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pengembalian`
--

INSERT INTO `pengembalian` (`id_pengembalian`, `id_booking`, `tanggal_pengembalian`, `jumlah_hari_aktual`, `kondisi_mobil`, `status_pengembalian`, `biaya_tambahan`, `total_biaya_akhir`, `catatan`) VALUES
(3, 5, '2026-11-05 23:47:24', 1, 'baik', 'disetujui', 5000.00, 455000.00, 'Uji coba pengembalian mobil 1');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id_user` int(11) NOT NULL,
  `nama_lengkap` varchar(120) NOT NULL,
  `no_sim` varchar(30) NOT NULL,
  `no_hp` varchar(25) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','pelanggan') DEFAULT 'pelanggan',
  `alamat` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id_user`, `nama_lengkap`, `no_sim`, `no_hp`, `password`, `role`, `alamat`, `created_at`) VALUES
(2, 'admin', 'admin', '082185088535', '$2y$10$xY/grbH77rnZjU.jEhiL1.16oe2s.kwYNErWGPVB1rGdJNksXwa.a', 'admin', 'Griya asri', '2026-11-05 15:42:17'),
(3, 'Ardi Satam Wardhana', '123', '081234567890', '$2y$10$QtquPNzIF2Jn4/RWY/PsLOqR27L8zq6w/YdY/T5pNd1iR7ncXcuYq', 'pelanggan', 'Jambi', '2026-11-05 16:45:59');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `gambar_mobil`
--
ALTER TABLE `gambar_mobil`
  ADD PRIMARY KEY (`id_gambar`),
  ADD KEY `id_mobil` (`id_mobil`);

--
-- Indexes for table `kategori_mobil`
--
ALTER TABLE `kategori_mobil`
  ADD PRIMARY KEY (`id_kategori`);

--
-- Indexes for table `mobil`
--
ALTER TABLE `mobil`
  ADD PRIMARY KEY (`id_mobil`),
  ADD UNIQUE KEY `nomor_plat` (`nomor_plat`),
  ADD KEY `id_kategori` (`id_kategori`);

--
-- Indexes for table `pembayaran`
--
ALTER TABLE `pembayaran`
  ADD PRIMARY KEY (`id_pembayaran`),
  ADD KEY `id_booking` (`id_booking`);

--
-- Indexes for table `pemesanan`
--
ALTER TABLE `pemesanan`
  ADD PRIMARY KEY (`id_booking`),
  ADD UNIQUE KEY `kode_booking` (`kode_booking`),
  ADD KEY `id_user` (`id_user`),
  ADD KEY `id_mobil` (`id_mobil`);

--
-- Indexes for table `pengembalian`
--
ALTER TABLE `pengembalian`
  ADD PRIMARY KEY (`id_pengembalian`),
  ADD UNIQUE KEY `id_booking` (`id_booking`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id_user`),
  ADD UNIQUE KEY `email` (`no_sim`),
  ADD UNIQUE KEY `no_sim` (`no_sim`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `gambar_mobil`
--
ALTER TABLE `gambar_mobil`
  MODIFY `id_gambar` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `kategori_mobil`
--
ALTER TABLE `kategori_mobil`
  MODIFY `id_kategori` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `mobil`
--
ALTER TABLE `mobil`
  MODIFY `id_mobil` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `pembayaran`
--
ALTER TABLE `pembayaran`
  MODIFY `id_pembayaran` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pemesanan`
--
ALTER TABLE `pemesanan`
  MODIFY `id_booking` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `pengembalian`
--
ALTER TABLE `pengembalian`
  MODIFY `id_pengembalian` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id_user` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `gambar_mobil`
--
ALTER TABLE `gambar_mobil`
  ADD CONSTRAINT `gambar_mobil_ibfk_1` FOREIGN KEY (`id_mobil`) REFERENCES `mobil` (`id_mobil`) ON DELETE CASCADE;

--
-- Constraints for table `mobil`
--
ALTER TABLE `mobil`
  ADD CONSTRAINT `mobil_ibfk_1` FOREIGN KEY (`id_kategori`) REFERENCES `kategori_mobil` (`id_kategori`);

--
-- Constraints for table `pembayaran`
--
ALTER TABLE `pembayaran`
  ADD CONSTRAINT `pembayaran_ibfk_1` FOREIGN KEY (`id_booking`) REFERENCES `pemesanan` (`id_booking`);

--
-- Constraints for table `pemesanan`
--
ALTER TABLE `pemesanan`
  ADD CONSTRAINT `pemesanan_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`),
  ADD CONSTRAINT `pemesanan_ibfk_2` FOREIGN KEY (`id_mobil`) REFERENCES `mobil` (`id_mobil`);

--
-- Constraints for table `pengembalian`
--
ALTER TABLE `pengembalian`
  ADD CONSTRAINT `pengembalian_ibfk_1` FOREIGN KEY (`id_booking`) REFERENCES `pemesanan` (`id_booking`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
