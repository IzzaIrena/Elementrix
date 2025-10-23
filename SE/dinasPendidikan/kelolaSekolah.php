<?php
session_start();
include "../koneksi.php";

// cek login
if (!isset($_SESSION['dinas_id'])) {
    header("Location: loginDinas.php");
    exit;
}
$nama_dinas = $_SESSION['nama_dinas'];

// ambil daftar sekolah
$query = "SELECT s.id, s.nama_sekolah, s.npsn, s.email, s.alamat, s.kontak, s.kuota 
          FROM sekolah s ORDER BY s.nama_sekolah ASC";
$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Kelola Sekolah</title>
<link rel="stylesheet" href="../css/dashboardDinas.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<aside class="sidebar">
  <h2><i class="fa-solid fa-school"></i> Dinas</h2>
  <ul>
    <li><a href="dashboardDinas.php"><i class="fa-solid fa-gauge"></i> Dashboard</a></li>
    <li><a href="buatAkunSekolah.php"><i class="fa-solid fa-user-plus"></i> Buat Akun Sekolah</a></li>
    <li><a href="kelolaSekolah.php" class="active"><i class="fa-solid fa-school"></i> Kelola Sekolah</a></li>
    <li><a href="kelolaTahunAkademik.php"><i class="fa-solid fa-calendar-days"></i> Tahun Akademik</a></li>
    <li><a href="aturanSeleksi.php"><i class="fa-solid fa-scale-balanced"></i> Aturan Seleksi</a></li>
    <li><a href="kelolaPendaftaranDinas.php"><i class="fa-solid fa-users"></i> Kelola Pendaftaran</a></li>
    <li><a href="monitoring.php"><i class="fa-solid fa-chart-line"></i> Monitoring</a></li>
    <li><a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
  </ul>
</aside>

<div class="main-content">
<header>
<h1>Kelola Sekolah</h1>
<div class="user-info">
<span><i class="fa-solid fa-user-tie"></i> <?= htmlspecialchars($nama_dinas) ?></span>
</div>
</header>

<main>
<table class="tabel-data">
  <thead>
    <tr>
      <th>No</th>
      <th>Nama Sekolah</th>
      <th>NPSN</th>
      <th>Email</th>
      <th>Alamat</th>
      <th>Kontak</th>
      <th>Kuota</th>
      <th>Aksi</th>
    </tr>
  </thead>
  <tbody>
    <?php
    if($result->num_rows > 0){
        $no = 1;
        while($row = $result->fetch_assoc()){
            echo "<tr>
                <td>".$no++."</td>
                <td>".htmlspecialchars($row['nama_sekolah'])."</td>
                <td>".htmlspecialchars($row['npsn'])."</td>
                <td>".htmlspecialchars($row['email'])."</td>
                <td>".htmlspecialchars($row['alamat'])."</td>
                <td>".htmlspecialchars($row['kontak'])."</td>
                <td>".htmlspecialchars($row['kuota'])."</td>
                <td>
                    <a href='editSekolah.php?id=".$row['id']."' class='btn-edit'><i class='fa-solid fa-pen'></i> Edit</a>
                </td>
            </tr>";
        }
    } else {
        echo "<tr><td colspan='8'>Belum ada sekolah</td></tr>";
    }
    ?>
  </tbody>
</table>
</main>
</div>
</body>
</html>
