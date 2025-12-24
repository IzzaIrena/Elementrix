<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once("../koneksi_mysql.php");

// cek login sekolah
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'sekolah') {
    header("Location: loginSekolah.php");
    exit;
}

// ambil data sekolah dari MySQL
$sekolah_id = $_SESSION['sekolah_id'] ?? '';

$stmt = $conn->prepare("SELECT nama_sekolah FROM sekolah WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $sekolah_id);
$stmt->execute();
$result = $stmt->get_result();
$sekolahData = $result->fetch_assoc();
$stmt->close();

$nama_sekolah = $sekolahData['nama_sekolah'] ?? "Sekolah";
?>

<div class="sidebar">
  <h2><?= htmlspecialchars($nama_sekolah); ?></h2>
  <ul>
    <li><a href="dashboardSekolah.php" class="active"><i class="fa fa-home"></i> Beranda</a></li>
    <li><a href="dataPendaftar.php"><i class="fa fa-users"></i> Data Pendaftar</a></li>
    <li><a href="pengumumanSeleksi.php"><i class="fa fa-bullhorn"></i> Seleksi & Pengumuman</a></li>
    <li><a href="jadwalDaftarUlang.php"><i class="fa fa-calendar"></i> Jadwal Daftar Ulang</a></li>
    <li><a href="dataDaftarUlang.php"><i class="fa fa-clipboard-check"></i> Data Daftar Ulang</a></li>

    <!-- 🔹 LOGOUT DENGAN POPUP KONFIRMASI -->
    <li class="logout">
      <a href="#" onclick="confirmLogout()">
        <i class="fa-solid fa-right-from-bracket"></i> Logout
      </a>
    </li>
  </ul>
</div>

<script>
function confirmLogout() {
    if (confirm("Apakah Anda yakin ingin logout?")) {
        window.location.href = "../logout.php";
    }
}
</script>