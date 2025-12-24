<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once("../koneksi_mysql.php"); // koneksi MySQL ($conn)

// Pastikan user sudah login sebagai siswa
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'siswa') {
    header("Location: loginSiswa.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Ambil data siswa dari MySQL
$sql = "SELECT s.*, u.nama AS nama_user 
        FROM siswa s
        LEFT JOIN user u ON u.id = s.user_id
        WHERE s.user_id = $user_id
        LIMIT 1";
$res = mysqli_query($conn, $sql);

// headerSiswa.php
if ($res && mysqli_num_rows($res) > 0) {
    $header_siswa = mysqli_fetch_assoc($res);
    $nama_lengkap = $header_siswa['nama_lengkap'] ?? $header_siswa['nama_user'] ?? "Siswa";
} else {
    $nama_lengkap = "Siswa";
}
?>

<!-- 🔹 HEADER SISWA -->
<header>
  <div class="logo">
    <i class="fa-solid fa-graduation-cap"></i> PPDB
  </div>
  <div class="user-info">
    <span><i class="fa-solid fa-user-circle"></i> Halo, <b><?= htmlspecialchars($nama_lengkap); ?></b></span>
  </div>
</header>
