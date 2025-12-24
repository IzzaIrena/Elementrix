<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once("../koneksi_mysql.php"); // GANTI Firebase → MySQL

// ==========================
// CEK LOGIN DINAS
// ==========================
if (!isset($_SESSION['dinas_id'])) {
    header("Location: loginDinas.php");
    exit;
}

$nama_dinas = $_SESSION['nama_dinas'] ?? "Dinas Pendidikan";

// ==========================
// AMBIL TAHUN AKADEMIK AKTIF (HANYA UNTUK DITAMPILKAN)
// ==========================
$qTahun = mysqli_query($conn, "
    SELECT id, nama_tahun 
    FROM tahun_akademik 
    WHERE status='aktif' 
    LIMIT 1
");

if (mysqli_num_rows($qTahun) > 0) {
    $tahun = mysqli_fetch_assoc($qTahun);
    $tahun_aktif_id   = $tahun['id'];
    $tahun_aktif_nama = $tahun['nama_tahun'];
} else {
    $tahun_aktif_id   = null;
    $tahun_aktif_nama = null;
}

?>

<header class="header-dinas">
  <h1>Dinas Pendidikan</h1>

  <div class="user-info">
    <span>
      <i class="fa-solid fa-user-tie"></i> 
      <?= htmlspecialchars($nama_dinas); ?>
    </span>
    <br>

    <?php if ($tahun_aktif_nama): ?>
      <small>
        <i class="fa-solid fa-calendar-days"></i>
        Tahun Akademik Aktif:
        <?= htmlspecialchars($tahun_aktif_nama); ?>
      </small>
    <?php else: ?>
      <small style="color:red;">
        ⚠️ Belum ada tahun akademik aktif
      </small>
    <?php endif; ?>
  </div>
</header>
