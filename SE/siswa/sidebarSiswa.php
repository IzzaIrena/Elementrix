<!-- 🔹 SIDEBAR NAVIGASI SISWA -->
<div class="sidebar">
  <ul>
    <li><a href="dashboardSiswa.php" class="<?= basename($_SERVER['PHP_SELF'])=='dashboardSiswa.php'?'active':'' ?>"><i class="fa-solid fa-gauge"></i> Dashboard</a></li>
    <li><a href="profilSiswa.php" class="<?= basename($_SERVER['PHP_SELF'])=='profilSiswa.php'?'active':'' ?>"><i class="fa-solid fa-user"></i> Profil</a></li>
    <li><a href="dataSekolah.php" class="<?= basename($_SERVER['PHP_SELF'])=='dataSekolah.php'?'active':'' ?>"><i class="fa-solid fa-school"></i> Data Sekolah</a></li>
    <li><a href="pendaftaranSiswa.php" class="<?= basename($_SERVER['PHP_SELF'])=='pendaftaranSiswa.php'?'active':'' ?>"><i class="fa-solid fa-file-pen"></i> Pendaftaran</a></li>
    <li><a href="prediksiMapel.php" class="<?= basename($_SERVER['PHP_SELF'])=='prediksiMapel.php'?'active':'' ?>"><i class="fa-solid fa-book"></i> Rekomendasi Mapel</a></li>
    <li><a href="daftarUlang.php" class="<?= basename($_SERVER['PHP_SELF'])=='daftarUlang.php'?'active':'' ?>"><i class="fa-solid fa-clipboard-check"></i> Daftar Ulang</a></li>

    <!-- 🔹 LOGOUT DENGAN POPUP -->
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
