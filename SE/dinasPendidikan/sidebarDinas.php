<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once("../koneksi_mysql.php");

// Cek login dinas
if (!isset($_SESSION['dinas_id'])) {
    header("Location: loginDinas.php");
    exit;
}

// Ambil nama dinas dari session
$nama_dinas = $_SESSION['nama_dinas'] ?? "Dinas Pendidikan";

// Fungsi untuk mengetahui halaman aktif
$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar">
  <h2><i class="fa-solid fa-school-flag"></i> <?= htmlspecialchars($nama_dinas); ?></h2>

  <ul>

    <li>
      <a href="dashboardDinas.php" class="<?= $current_page=='dashboardDinas.php' ? 'active' : '' ?>">
        <i class="fa-solid fa-gauge"></i> Dashboard
      </a>
    </li>

    <li>
      <a href="buatAkunSekolah.php" class="<?= $current_page=='buatAkunSekolah.php' ? 'active' : '' ?>">
        <i class="fa-solid fa-user-plus"></i> Buat Akun Sekolah
      </a>
    </li>

    <li>
      <a href="kelolaSekolah.php" class="<?= $current_page=='kelolaSekolah.php' ? 'active' : '' ?>">
        <i class="fa-solid fa-school"></i> Kelola Sekolah
      </a>
    </li>

    <li>
      <a href="kelolaTahunAkademik.php" class="<?= $current_page=='kelolaTahunAkademik.php' ? 'active' : '' ?>">
        <i class="fa-solid fa-calendar-days"></i> Tahun Akademik
      </a>
    </li>

    <li>
      <a href="aturanSeleksi.php" class="<?= $current_page=='aturanSeleksi.php' ? 'active' : '' ?>">
        <i class="fa-solid fa-scale-balanced"></i> Aturan Seleksi
      </a>
    </li>

    <li>
      <a href="kelolaPendaftaranDinas.php" class="<?= $current_page=='kelolaPendaftaranDinas.php' ? 'active' : '' ?>">
        <i class="fa-solid fa-users"></i> Kelola Pendaftaran
      </a>
    </li>

    <!-- Logout -->
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
