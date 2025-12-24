<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// gunakan include_once agar tidak duplikat
include_once("../koneksi_mysql.php");

// cek login sekolah
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'sekolah') {
    header("Location: loginSekolah.php");
    exit;
}

// ambil data sekolah dari MySQL
$sekolah_id = $_SESSION['sekolah_id'] ?? '';

$stmt = $conn->prepare("SELECT * FROM sekolah WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $sekolah_id);
$stmt->execute();
$result = $stmt->get_result();
$sekolahData = $result->fetch_assoc();
$stmt->close();

if (!$sekolahData) {
    die("Data sekolah tidak ditemukan di database.");
}

$nama_sekolah = $sekolahData['nama_sekolah'] ?? "Sekolah";
$npsn         = $sekolahData['npsn'] ?? "-";
$alamat       = $sekolahData['alamat'] ?? "-";
?>

<header class="header-sekolah">
  <h1>Dashboard Sekolah</h1>
  <p>NPSN: <?= htmlspecialchars($npsn); ?></p>
</header>
