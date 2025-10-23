-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 20, 2025 at 05:33 AM
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
-- Database: `ppdbelementrix`
--

-- --------------------------------------------------------

--
-- Table structure for table `aturan_dokumen`
--

CREATE TABLE `aturan_dokumen` (
  `id` int(11) NOT NULL,
  `nama_dokumen` varchar(100) NOT NULL,
  `wajib` tinyint(1) DEFAULT 1,
  `tipe_dokumen` varchar(10) DEFAULT 'pdf'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `aturan_dokumen`
--

INSERT INTO `aturan_dokumen` (`id`, `nama_dokumen`, `wajib`, `tipe_dokumen`) VALUES
(3, 'Kartu Keluarga', 1, 'png'),
(5, 'Rapor semester 1 - 5', 1, 'pdf');

-- --------------------------------------------------------

--
-- Table structure for table `aturan_mapel`
--

CREATE TABLE `aturan_mapel` (
  `id` int(11) NOT NULL,
  `kode_mapel` varchar(20) DEFAULT NULL,
  `nama_mapel` varchar(100) NOT NULL,
  `aktif` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `aturan_mapel`
--

INSERT INTO `aturan_mapel` (`id`, `kode_mapel`, `nama_mapel`, `aktif`) VALUES
(14, 'AGAMA', 'Pendidikan Agama dan Budi Pekerti', 1),
(15, 'PPKN', 'Pendidikan Pancasila dan Kewarganegaraan', 1),
(16, 'BINDO', 'Bahasa Indonesia', 1),
(17, 'MTK', 'Matematika', 1),
(18, 'IPA', 'Ilmu Pengetahuan Alam', 1),
(19, 'IPS', 'Ilmu Pengetahuan Sosial', 1),
(20, 'BING', 'Bahasa Inggris', 1),
(21, 'SENI', 'Seni Budaya', 1),
(22, 'PJOK', 'PJOK', 1),
(23, 'INFOR', 'Informatika', 1),
(24, 'PRAKARYA', 'Prakarya', 1);

-- --------------------------------------------------------

--
-- Table structure for table `aturan_seleksi`
--

CREATE TABLE `aturan_seleksi` (
  `id` int(11) NOT NULL,
  `tahun_akademik_id` int(11) DEFAULT NULL,
  `nama_pendaftaran` varchar(100) NOT NULL,
  `tanggal_mulai` date NOT NULL,
  `tanggal_selesai` date NOT NULL,
  `tanggal_pengumuman` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tgl_update` timestamp NULL DEFAULT NULL,
  `tanggal_daftar_ulang` date DEFAULT NULL,
  `tanggal_seleksi` date DEFAULT NULL,
  `tanggal_mos` date DEFAULT NULL,
  `tanggal_masuk` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `aturan_seleksi`
--

INSERT INTO `aturan_seleksi` (`id`, `tahun_akademik_id`, `nama_pendaftaran`, `tanggal_mulai`, `tanggal_selesai`, `tanggal_pengumuman`, `created_at`, `tgl_update`, `tanggal_daftar_ulang`, `tanggal_seleksi`, `tanggal_mos`, `tanggal_masuk`) VALUES
(5, 1, 'PPDB Global', '2025-10-15', '2025-10-17', '2025-10-19', '2025-10-14 05:46:03', NULL, '2025-10-20', '2025-10-18', '2025-11-01', '2025-11-07');

-- --------------------------------------------------------

--
-- Table structure for table `booking_daftar_ulang`
--

CREATE TABLE `booking_daftar_ulang` (
  `id` int(11) NOT NULL,
  `siswa_id` int(11) NOT NULL,
  `jadwal_id` int(11) NOT NULL,
  `tanggal_booking` date NOT NULL,
  `jam_slot` varchar(20) DEFAULT NULL,
  `status` enum('pending','hadir') DEFAULT 'pending',
  `qr_code` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `booking_daftar_ulang`
--

INSERT INTO `booking_daftar_ulang` (`id`, `siswa_id`, `jadwal_id`, `tanggal_booking`, `jam_slot`, `status`, `qr_code`) VALUES
(14, 2, 4, '2025-10-25', '07:30 - 08:30', 'pending', '../qr_booking/booking_14.png');

-- --------------------------------------------------------

--
-- Table structure for table `daftar_ulang`
--

CREATE TABLE `daftar_ulang` (
  `id` int(11) NOT NULL,
  `siswa_id` int(11) NOT NULL,
  `sekolah_id` int(11) NOT NULL,
  `jadwal_id` int(11) DEFAULT NULL,
  `tanggal_daftar` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','selesai') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dinas`
--

CREATE TABLE `dinas` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `nama_dinas` varchar(200) NOT NULL,
  `alamat` text DEFAULT NULL,
  `kontak` varchar(50) DEFAULT NULL,
  `email` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dinas`
--

INSERT INTO `dinas` (`id`, `user_id`, `nama_dinas`, `alamat`, `kontak`, `email`) VALUES
(1, 5, 'Dinas Pendidikan Kota Parepare', 'Ujung Sabbang, Kec. Ujung, Kota Parepare, Sulawesi Selatan 91131', '(0421) 21035', 'dinaspendidikan@pareparekota.go.id');

-- --------------------------------------------------------

--
-- Table structure for table `dokumen_siswa`
--

CREATE TABLE `dokumen_siswa` (
  `id` int(11) NOT NULL,
  `siswa_id` int(11) NOT NULL,
  `nama_dokumen` varchar(100) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `status` enum('pending','terverifikasi','kurang') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dokumen_siswa`
--

INSERT INTO `dokumen_siswa` (`id`, `siswa_id`, `nama_dokumen`, `file_path`, `status`) VALUES
(9, 2, 'Kartu Keluarga', '1760421579_PPDB_1.png', 'pending'),
(10, 2, 'Rapor semester 1 - 5', '1760421579_Software_Requirements_Specification_IEEE_Elementrix.pdf', 'pending'),
(11, 4, 'Kartu Keluarga', '1760484555_Screenshot (519).png', 'pending'),
(12, 4, 'Rapor semester 1 - 5', '1760484555_BINATRIX_1.pdf', 'pending');

-- --------------------------------------------------------

--
-- Table structure for table `jadwal_daftar_ulang`
--

CREATE TABLE `jadwal_daftar_ulang` (
  `id` int(11) NOT NULL,
  `sekolah_id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL,
  `kuota_per_jam` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `jadwal_daftar_ulang`
--

INSERT INTO `jadwal_daftar_ulang` (`id`, `sekolah_id`, `tanggal`, `jam_mulai`, `jam_selesai`, `kuota_per_jam`) VALUES
(4, 1, '2025-10-25', '07:30:00', '17:00:00', 10);

-- --------------------------------------------------------

--
-- Table structure for table `nilai_akademik`
--

CREATE TABLE `nilai_akademik` (
  `id` int(11) NOT NULL,
  `siswa_id` int(11) NOT NULL,
  `mapel` varchar(50) NOT NULL,
  `semester_1` int(11) DEFAULT NULL,
  `semester_2` int(11) DEFAULT NULL,
  `semester_3` int(11) DEFAULT NULL,
  `semester_4` int(11) DEFAULT NULL,
  `semester_5` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `nilai_akademik`
--

INSERT INTO `nilai_akademik` (`id`, `siswa_id`, `mapel`, `semester_1`, `semester_2`, `semester_3`, `semester_4`, `semester_5`) VALUES
(42, 2, 'Pendidikan Agama dan Budi Pekerti', 88, 89, 90, 90, 91),
(43, 2, 'Pendidikan Pancasila dan Kewarganegaraan', 85, 86, 87, 88, 88),
(44, 2, 'Bahasa Indonesia', 90, 91, 92, 92, 93),
(45, 2, 'Matematika', 87, 88, 89, 90, 91),
(46, 2, 'Ilmu Pengetahuan Alam', 86, 87, 88, 89, 90),
(47, 2, 'Ilmu Pengetahuan Sosial', 85, 86, 87, 88, 89),
(48, 2, 'Bahasa Inggris', 88, 89, 90, 91, 92),
(49, 2, 'Seni Budaya', 90, 90, 91, 91, 92),
(50, 2, 'PJOK', 92, 93, 93, 94, 95),
(51, 2, 'Informatika', 89, 90, 91, 92, 93),
(52, 2, 'Prakarya', 90, 91, 91, 92, 93),
(53, 4, 'Pendidikan Agama dan Budi Pekerti', 90, 89, 89, 91, 95),
(54, 4, 'Pendidikan Pancasila dan Kewarganegaraan', 92, 89, 88, 89, 90),
(55, 4, 'Bahasa Indonesia', 91, 89, 90, 90, 88),
(56, 4, 'Matematika', 89, 90, 91, 88, 88),
(57, 4, 'Ilmu Pengetahuan Alam', 89, 91, 91, 89, 91),
(58, 4, 'Ilmu Pengetahuan Sosial', 87, 92, 91, 95, 95),
(59, 4, 'Bahasa Inggris', 88, 92, 90, 90, 88),
(60, 4, 'Seni Budaya', 88, 90, 90, 90, 88),
(61, 4, 'PJOK', 91, 87, 90, 90, 88),
(62, 4, 'Informatika', 87, 88, 87, 92, 88),
(63, 4, 'Prakarya', 89, 89, 88, 91, 88);

-- --------------------------------------------------------

--
-- Table structure for table `ortu_wali`
--

CREATE TABLE `ortu_wali` (
  `id` int(11) NOT NULL,
  `siswa_id` int(11) NOT NULL,
  `nama_ayah` varchar(100) DEFAULT NULL,
  `no_hp_ayah` varchar(20) DEFAULT NULL,
  `nama_ibu` varchar(100) DEFAULT NULL,
  `no_hp_ibu` varchar(20) DEFAULT NULL,
  `nama_wali` varchar(100) DEFAULT NULL,
  `no_hp_wali` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ortu_wali`
--

INSERT INTO `ortu_wali` (`id`, `siswa_id`, `nama_ayah`, `no_hp_ayah`, `nama_ibu`, `no_hp_ibu`, `nama_wali`, `no_hp_wali`, `created_at`, `updated_at`) VALUES
(2, 2, 'Wahyudi', '087731242880', 'Hasmawati', '082346931468', '', '', '2025-10-14 05:59:39', '2025-10-14 05:59:39'),
(3, 4, '', '', '', '', 'Walic', '085255307756', '2025-10-14 22:56:57', '2025-10-14 23:29:15');

-- --------------------------------------------------------

--
-- Table structure for table `pendaftaran`
--

CREATE TABLE `pendaftaran` (
  `id` int(11) NOT NULL,
  `siswa_id` int(11) NOT NULL,
  `tahun_id` int(11) NOT NULL,
  `sekolah_id` int(11) NOT NULL,
  `tanggal_daftar` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','diterima','ditolak') DEFAULT 'pending',
  `pengumuman_dibuat` tinyint(1) DEFAULT 0,
  `tanggal_pengumuman` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pendaftaran`
--

INSERT INTO `pendaftaran` (`id`, `siswa_id`, `tahun_id`, `sekolah_id`, `tanggal_daftar`, `status`, `pengumuman_dibuat`, `tanggal_pengumuman`) VALUES
(8, 2, 1, 1, '2025-10-14 05:59:39', 'diterima', 1, '2025-10-20 02:49:58'),
(12, 4, 1, 1, '2025-10-14 23:29:15', 'ditolak', 0, NULL),
(13, 4, 1, 7, '2025-10-14 23:29:15', 'ditolak', 1, '2025-10-20 09:12:24'),
(14, 4, 1, 9, '2025-10-14 23:29:15', 'ditolak', 1, '2025-10-20 09:12:32');

-- --------------------------------------------------------

--
-- Table structure for table `prediksi_jurusan`
--

CREATE TABLE `prediksi_jurusan` (
  `id` int(11) NOT NULL,
  `siswa_id` int(11) DEFAULT NULL,
  `jurusan` varchar(50) DEFAULT NULL,
  `probabilitas` double DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `prediksi_jurusan`
--

INSERT INTO `prediksi_jurusan` (`id`, `siswa_id`, `jurusan`, `probabilitas`) VALUES
(124, 2, 'IPA', 0.7993077541233631),
(125, 2, 'IPS', 0.17558265178609794),
(126, 2, 'Bahasa', 0.02510959409053889),
(133, 4, 'IPA', 0.6851929544005545),
(134, 4, 'IPS', 0.30394421736751176),
(135, 4, 'Bahasa', 0.010862828231933817);

-- --------------------------------------------------------

--
-- Table structure for table `prediksi_mata_pelajaran`
--

CREATE TABLE `prediksi_mata_pelajaran` (
  `id` int(11) NOT NULL,
  `siswa_id` int(11) NOT NULL,
  `mapel_cocok` varchar(100) NOT NULL,
  `skor_prediksi` double DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `prediksi_mata_pelajaran`
--

INSERT INTO `prediksi_mata_pelajaran` (`id`, `siswa_id`, `mapel_cocok`, `skor_prediksi`) VALUES
(97, 2, 'Fisika', 0.2219593068780245),
(98, 2, 'Kimia', 0.22120859896299178),
(99, 2, 'Ekonomi', 0.22222195440113976),
(100, 2, 'Bahasa Jerman', 0.334610139757844),
(109, 4, 'Biologi', 0.22264628436741782),
(110, 4, 'Kimia', 0.2216589394256554),
(111, 4, 'Sosiologi', 0.2230829489507233),
(112, 4, 'Bahasa Jerman', 0.3326118272562034);

-- --------------------------------------------------------

--
-- Table structure for table `sekolah`
--

CREATE TABLE `sekolah` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `nama_sekolah` varchar(200) NOT NULL,
  `npsn` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `alamat` text DEFAULT NULL,
  `kontak` varchar(50) DEFAULT NULL,
  `kuota` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sekolah`
--

INSERT INTO `sekolah` (`id`, `user_id`, `nama_sekolah`, `npsn`, `email`, `alamat`, `kontak`, `kuota`, `created_at`, `latitude`, `longitude`) VALUES
(1, 6, 'SMA Negeri 1 Parepare', '40307693', 'humas@sman1parepare.sch.id', 'Jl. Matahari No.3, Mallusetasi, Kec. Ujung, Kota Parepare, Sulawesi Selatan', '82192082212', 1, '2025-09-25 03:39:49', -4.01385091, 119.62449968),
(7, 16, 'SMA Negeri 4 Parepare', '40307696', 'smanegeri4parepare@gmail.com', 'Jl. Lasiming No.22, Ujung Bulu, Kec. Ujung, Kota Parepare, Sulawesi Selatan', '4212918936', 1, '2025-10-14 12:48:57', -4.01327080, 119.63005453),
(9, 18, 'coba', '222222', 'coba@gmail.com', 'JL. Andi Laetong', '0215725611', 1, '2025-10-14 13:52:03', -4.01116458, 119.62863028);

-- --------------------------------------------------------

--
-- Table structure for table `sekolah_asal`
--

CREATE TABLE `sekolah_asal` (
  `id` int(11) NOT NULL,
  `siswa_id` int(11) NOT NULL,
  `nama_sekolah_asal` varchar(100) DEFAULT NULL,
  `npsn_sekolah_asal` varchar(20) DEFAULT NULL,
  `alamat_sekolah_asal` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sekolah_asal`
--

INSERT INTO `sekolah_asal` (`id`, `siswa_id`, `nama_sekolah_asal`, `npsn_sekolah_asal`, `alamat_sekolah_asal`) VALUES
(1, 2, 'SMP Negeri 1 Parepare', '40307676', 'Jl. Karaeng Burane No. 18. Desa/Kelurahan, : MALLUSETASI. Kecamatan/Kota (LN), : KEC. UJUNG.'),
(2, 4, 'SMP Negeri 1 Parepare', '40307676', 'Jl. Karaeng Burane No. 18.');

-- --------------------------------------------------------

--
-- Table structure for table `siswa`
--

CREATE TABLE `siswa` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `nisn` varchar(20) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `nik` varchar(20) DEFAULT NULL,
  `tempat_lahir` varchar(50) DEFAULT NULL,
  `tanggal_lahir` date DEFAULT NULL,
  `jenis_kelamin` enum('L','P') DEFAULT NULL,
  `alamat` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `siswa`
--

INSERT INTO `siswa` (`id`, `user_id`, `nisn`, `nama_lengkap`, `email`, `no_hp`, `nik`, `tempat_lahir`, `tanggal_lahir`, `jenis_kelamin`, `alamat`) VALUES
(2, 3, '0053578777', 'Izza Irena', '1224irenna@gmail.com', '085955272105', '7372027103050001', 'Parepare', '2005-03-31', 'P', 'Jl. Andi Laetong, RT:003 RW:002'),
(4, 9, '0053578710', 'Contoh1', 'contoh1@gmail.com', '085955272106', '7372027103050005', 'Parepare', '2005-01-01', 'L', 'Jl. Andi Sinta'),
(5, 19, '0053578711', 'Elementrix', 'elementrix@gmail.com', '', NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tahun_akademik`
--

CREATE TABLE `tahun_akademik` (
  `id` int(11) NOT NULL,
  `nama_tahun` varchar(20) NOT NULL,
  `status` enum('aktif','nonaktif') DEFAULT 'nonaktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tahun_akademik`
--

INSERT INTO `tahun_akademik` (`id`, `nama_tahun`, `status`, `created_at`) VALUES
(1, '2025/2026', 'aktif', '2025-10-14 05:43:58');

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('dinas','sekolah','siswa') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`id`, `username`, `password`, `role`, `created_at`) VALUES
(3, 'Izza Irena', '$2y$10$xX96FbaFsI40Z87AD4tYq.ADk9LeL1T2TxkgfXa4KgdwCSUg8MX4.', 'siswa', '2025-09-25 01:10:02'),
(5, 'Dinas Pendidikan Kota Parepare', '$2y$10$gxJfPXjy1U7JbtL3blHKPOqFtAs5bRn.y/4Nf9nejdYyXUPmWw.Zm', 'dinas', '2025-09-25 01:29:26'),
(6, '40307693', '$2y$10$2NF5VeNL5XrMYC/md.qQZuhZVXQqJPrBSrAXAgOw2.8ymnbG9kJv2', 'sekolah', '2025-09-25 03:39:49'),
(9, 'Contoh1', '$2y$10$LYNmnfogcVLuxrmwr0qjVOf3yHzkTatAezQbhXqECR.WwgQuOWRBO', 'siswa', '2025-10-14 06:48:15'),
(16, '40307696', '$2y$10$MQzVwqY6QnZSU.bOcPt55Ob0wUz/8q0SG6maeEV7KYmjmsAWTKmDe', 'sekolah', '2025-10-14 12:48:57'),
(18, '222222', '$2y$10$CyE8RpsMW5olPrmYCpbcFufDih3J1beA5pDIOk5EmhOy5ZQ/L99KO', 'sekolah', '2025-10-14 13:52:03'),
(19, 'Elementrix', '$2y$10$FF8ogTbaNNOSqB3zzDLYfeC1DCudQr7y7O0KvKDVuiYLUQnOfspdG', 'siswa', '2025-10-16 23:53:35');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `aturan_dokumen`
--
ALTER TABLE `aturan_dokumen`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `aturan_mapel`
--
ALTER TABLE `aturan_mapel`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `aturan_seleksi`
--
ALTER TABLE `aturan_seleksi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_tahun_akademik` (`tahun_akademik_id`);

--
-- Indexes for table `booking_daftar_ulang`
--
ALTER TABLE `booking_daftar_ulang`
  ADD PRIMARY KEY (`id`),
  ADD KEY `siswa_id` (`siswa_id`),
  ADD KEY `jadwal_id` (`jadwal_id`);

--
-- Indexes for table `daftar_ulang`
--
ALTER TABLE `daftar_ulang`
  ADD PRIMARY KEY (`id`),
  ADD KEY `siswa_id` (`siswa_id`),
  ADD KEY `sekolah_id` (`sekolah_id`);

--
-- Indexes for table `dinas`
--
ALTER TABLE `dinas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `dokumen_siswa`
--
ALTER TABLE `dokumen_siswa`
  ADD PRIMARY KEY (`id`),
  ADD KEY `siswa_id` (`siswa_id`);

--
-- Indexes for table `jadwal_daftar_ulang`
--
ALTER TABLE `jadwal_daftar_ulang`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sekolah_id` (`sekolah_id`);

--
-- Indexes for table `nilai_akademik`
--
ALTER TABLE `nilai_akademik`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_siswa_mapel` (`siswa_id`,`mapel`);

--
-- Indexes for table `ortu_wali`
--
ALTER TABLE `ortu_wali`
  ADD PRIMARY KEY (`id`),
  ADD KEY `siswa_id` (`siswa_id`);

--
-- Indexes for table `pendaftaran`
--
ALTER TABLE `pendaftaran`
  ADD PRIMARY KEY (`id`),
  ADD KEY `siswa_id` (`siswa_id`),
  ADD KEY `sekolah_id` (`sekolah_id`);

--
-- Indexes for table `prediksi_jurusan`
--
ALTER TABLE `prediksi_jurusan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_siswa_jurusan` (`siswa_id`,`jurusan`),
  ADD KEY `siswa_id` (`siswa_id`);

--
-- Indexes for table `prediksi_mata_pelajaran`
--
ALTER TABLE `prediksi_mata_pelajaran`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_siswa_mapel` (`siswa_id`,`mapel_cocok`);

--
-- Indexes for table `sekolah`
--
ALTER TABLE `sekolah`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `npsn` (`npsn`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `sekolah_asal`
--
ALTER TABLE `sekolah_asal`
  ADD PRIMARY KEY (`id`),
  ADD KEY `siswa_id` (`siswa_id`);

--
-- Indexes for table `siswa`
--
ALTER TABLE `siswa`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nisn` (`nisn`),
  ADD UNIQUE KEY `nik` (`nik`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `tahun_akademik`
--
ALTER TABLE `tahun_akademik`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `aturan_dokumen`
--
ALTER TABLE `aturan_dokumen`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `aturan_mapel`
--
ALTER TABLE `aturan_mapel`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `aturan_seleksi`
--
ALTER TABLE `aturan_seleksi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `booking_daftar_ulang`
--
ALTER TABLE `booking_daftar_ulang`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `daftar_ulang`
--
ALTER TABLE `daftar_ulang`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dinas`
--
ALTER TABLE `dinas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `dokumen_siswa`
--
ALTER TABLE `dokumen_siswa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `jadwal_daftar_ulang`
--
ALTER TABLE `jadwal_daftar_ulang`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `nilai_akademik`
--
ALTER TABLE `nilai_akademik`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT for table `ortu_wali`
--
ALTER TABLE `ortu_wali`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `pendaftaran`
--
ALTER TABLE `pendaftaran`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `prediksi_jurusan`
--
ALTER TABLE `prediksi_jurusan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=145;

--
-- AUTO_INCREMENT for table `prediksi_mata_pelajaran`
--
ALTER TABLE `prediksi_mata_pelajaran`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=125;

--
-- AUTO_INCREMENT for table `sekolah`
--
ALTER TABLE `sekolah`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `sekolah_asal`
--
ALTER TABLE `sekolah_asal`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `siswa`
--
ALTER TABLE `siswa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tahun_akademik`
--
ALTER TABLE `tahun_akademik`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `aturan_seleksi`
--
ALTER TABLE `aturan_seleksi`
  ADD CONSTRAINT `fk_tahun_akademik` FOREIGN KEY (`tahun_akademik_id`) REFERENCES `tahun_akademik` (`id`);

--
-- Constraints for table `booking_daftar_ulang`
--
ALTER TABLE `booking_daftar_ulang`
  ADD CONSTRAINT `booking_daftar_ulang_ibfk_1` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `booking_daftar_ulang_ibfk_2` FOREIGN KEY (`jadwal_id`) REFERENCES `jadwal_daftar_ulang` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `daftar_ulang`
--
ALTER TABLE `daftar_ulang`
  ADD CONSTRAINT `daftar_ulang_ibfk_1` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `daftar_ulang_ibfk_2` FOREIGN KEY (`sekolah_id`) REFERENCES `sekolah` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `dinas`
--
ALTER TABLE `dinas`
  ADD CONSTRAINT `dinas_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `dokumen_siswa`
--
ALTER TABLE `dokumen_siswa`
  ADD CONSTRAINT `dokumen_siswa_ibfk_1` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `jadwal_daftar_ulang`
--
ALTER TABLE `jadwal_daftar_ulang`
  ADD CONSTRAINT `jadwal_daftar_ulang_ibfk_1` FOREIGN KEY (`sekolah_id`) REFERENCES `sekolah` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `nilai_akademik`
--
ALTER TABLE `nilai_akademik`
  ADD CONSTRAINT `nilai_akademik_ibfk_1` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ortu_wali`
--
ALTER TABLE `ortu_wali`
  ADD CONSTRAINT `ortu_wali_ibfk_1` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pendaftaran`
--
ALTER TABLE `pendaftaran`
  ADD CONSTRAINT `pendaftaran_ibfk_1` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pendaftaran_ibfk_2` FOREIGN KEY (`sekolah_id`) REFERENCES `sekolah` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `prediksi_jurusan`
--
ALTER TABLE `prediksi_jurusan`
  ADD CONSTRAINT `prediksi_jurusan_ibfk_1` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sekolah`
--
ALTER TABLE `sekolah`
  ADD CONSTRAINT `sekolah_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sekolah_asal`
--
ALTER TABLE `sekolah_asal`
  ADD CONSTRAINT `sekolah_asal_ibfk_1` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `siswa`
--
ALTER TABLE `siswa`
  ADD CONSTRAINT `siswa_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
