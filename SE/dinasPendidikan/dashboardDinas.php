<?php
session_start();
include "../koneksi.php";

// Cek apakah dinas sudah login
if (!isset($_SESSION['dinas_id'])) {
    header("Location: loginDinas.php");
    exit;
}

$nama_dinas = $_SESSION['nama_dinas'];

// Ambil tahun akademik aktif
$sql_tahun_aktif = "SELECT id, nama_tahun FROM tahun_akademik WHERE status = 'aktif' LIMIT 1";
$res_tahun = $conn->query($sql_tahun_aktif);
$tahun_aktif = ($res_tahun && $res_tahun->num_rows > 0) ? $res_tahun->fetch_assoc() : null;
$tahun_id = $tahun_aktif ? $tahun_aktif['id'] : 0;

// -------- Statistik --------
$sql_sekolah = "SELECT COUNT(*) as total FROM sekolah";
$res_sekolah = $conn->query($sql_sekolah);
$total_sekolah = ($res_sekolah && $res_sekolah->num_rows > 0) ? $res_sekolah->fetch_assoc()['total'] : 0;

// Statistik berdasarkan tahun akademik aktif
$sql_siswa = "SELECT COUNT(DISTINCT siswa_id) as total FROM pendaftaran WHERE tahun_id = '$tahun_id'";
$res_siswa = $conn->query($sql_siswa);
$total_siswa = ($res_siswa && $res_siswa->num_rows > 0) ? $res_siswa->fetch_assoc()['total'] : 0;

$sql_daftarulang = "SELECT COUNT(*) as total FROM daftar_ulang du 
                    JOIN pendaftaran p ON du.siswa_id = p.siswa_id 
                    WHERE p.tahun_id = '$tahun_id' AND du.status = 'selesai'";
$res_daftarulang = $conn->query($sql_daftarulang);
$total_daftarulang = ($res_daftarulang && $res_daftarulang->num_rows > 0) ? $res_daftarulang->fetch_assoc()['total'] : 0;

$sql_belum_hadir = "SELECT COUNT(*) as total FROM daftar_ulang du 
                    JOIN pendaftaran p ON du.siswa_id = p.siswa_id 
                    WHERE p.tahun_id = '$tahun_id' AND du.status = 'belum_hadir'";
$res_belum_hadir = $conn->query($sql_belum_hadir);
$total_belum_hadir = ($res_belum_hadir && $res_belum_hadir->num_rows > 0) ? $res_belum_hadir->fetch_assoc()['total'] : 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Dashboard Dinas Pendidikan</title>
  <link rel="stylesheet" href="../css/dashboardDinas.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
  <!-- Sidebar -->
  <aside class="sidebar">
    <h2><i class="fa-solid fa-school-flag"></i> Dinas</h2>
    <ul>
      <li><a href="dashboardDinas.php"><i class="fa-solid fa-gauge"></i> Dashboard</a></li>
      <li><a href="buatAkunSekolah.php"><i class="fa-solid fa-user-plus"></i> Buat Akun Sekolah</a></li>
      <li><a href="kelolaSekolah.php"><i class="fa-solid fa-school"></i> Kelola Sekolah</a></li>
      <li><a href="kelolaTahunAkademik.php"><i class="fa-solid fa-calendar-days"></i> Tahun Akademik</a></li>
      <li><a href="aturanSeleksi.php"><i class="fa-solid fa-scale-balanced"></i> Aturan Seleksi</a></li>
      <li><a href="kelolaPendaftaranDinas.php" class="active"><i class="fa-solid fa-users"></i> Kelola Pendaftaran</a></li>
      <li><a href="monitoring.php"><i class="fa-solid fa-chart-line"></i> Monitoring</a></li>
      <li><a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
    </ul>
  </aside>

  <!-- Main Content -->
  <div class="main-content">
    <header>
      <h1>Dashboard Dinas Pendidikan</h1>
      <div class="user-info">
        <span><i class="fa-solid fa-user-tie"></i> <?php echo htmlspecialchars($nama_dinas); ?></span><br>
        <?php if ($tahun_aktif): ?>
          <small><i class="fa-solid fa-calendar-days"></i> Tahun Akademik Aktif: <?= htmlspecialchars($tahun_aktif['nama_tahun']); ?></small>
        <?php else: ?>
          <small style="color:red;">⚠️ Belum ada tahun akademik aktif</small>
        <?php endif; ?>
      </div>
    </header>

    <main>
      <div class="cards">
        <div class="card">
          <i class="fa-solid fa-school"></i>
          <h3>Total Sekolah</h3>
          <p><?php echo $total_sekolah; ?></p>
        </div>
        <div class="card">
          <i class="fa-solid fa-users"></i>
          <h3>Total Siswa Terdaftar</h3>
          <p><?php echo $total_siswa; ?></p>
        </div>
        <div class="card">
          <i class="fa-solid fa-user-check"></i>
          <h3>Siswa Daftar Ulang</h3>
          <p><?php echo $total_daftarulang; ?></p>
        </div>
        <div class="card">
          <i class="fa-solid fa-user-clock"></i>
          <h3>Siswa Belum Hadir</h3>
          <p><?php echo $total_belum_hadir; ?></p>
        </div>
      </div>
    </main>
  </div>
</body>
</html>
