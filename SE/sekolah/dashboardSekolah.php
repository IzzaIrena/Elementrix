<?php
session_start();
include "../koneksi.php"; // pastikan file koneksi sudah benar

// Cek apakah user login dan role sekolah
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'sekolah') {
    header("Location: login.php");
    exit();
}

// Ambil ID sekolah dari session
// Diasumsikan disimpan saat login sekolah berdasarkan tabel `sekolah.id`
$sekolah_id = $_SESSION['sekolah_id'];

// Ambil data ringkasan dari database
// Total pendaftar
$sql_total = "SELECT COUNT(*) FROM pendaftaran WHERE sekolah_id = ?";
$stmt = $conn->prepare($sql_total);
$stmt->bind_param("i", $sekolah_id);
$stmt->execute();
$stmt->bind_result($total_pendaftar);
$stmt->fetch();
$stmt->close();

// Total sudah daftar ulang
$sql_sudah = "SELECT COUNT(*) 
              FROM daftar_ulang du 
              JOIN pendaftaran p ON du.siswa_id = p.siswa_id 
              WHERE p.sekolah_id = ?";
$stmt = $conn->prepare($sql_sudah);
$stmt->bind_param("i", $sekolah_id);
$stmt->execute();
$stmt->bind_result($total_daftar_ulang);
$stmt->fetch();
$stmt->close();

// Belum daftar ulang = total - sudah
$belum_hadir = $total_pendaftar - $total_daftar_ulang;

// Ambil data pendaftar terbaru
$sql_latest = "SELECT s.nama_lengkap, s.nisn, s.email, p.status
               FROM pendaftaran p
               JOIN siswa s ON p.siswa_id = s.id
               WHERE p.sekolah_id = ?
               ORDER BY p.tanggal_daftar DESC
               LIMIT 5";
$stmt = $conn->prepare($sql_latest);
$stmt->bind_param("i", $sekolah_id);
$stmt->execute();
$result_latest = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Sekolah</title>
  <link rel="stylesheet" href="../css/dashboardSekolah.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
  <!-- Sidebar -->
  <div class="sidebar">
    <h2><?php echo htmlspecialchars($_SESSION['nama_sekolah']); ?></h2>
    <ul>
      <li><a href="dashboardSekolah.php"><i class="fa fa-home"></i> Beranda</a></li>
      <li><a href="dataPendaftar.php" class="active"><i class="fa fa-users"></i> Data Pendaftar</a></li>
      <li><a href="pengumumanSeleksi.php" class="active"><i class="fa fa-bullhorn"></i> Pengumuman Seleksi</a></li>
      <li><a href="jadwalDaftarUlang.php"><i class="fa fa-calendar"></i> Jadwal Daftar Ulang</a></li>
      <li><a href="daftarUlang.php"><i class="fa fa-clipboard-check"></i> Data Daftar Ulang</a></li>
      <li><a href="logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a></li>
    </ul>
  </div>

  <!-- Main Content -->
  <div class="main-content">
    <header>
      <h1>Dashboard Sekolah</h1>
      <p>NPSN: <?php echo htmlspecialchars($_SESSION['username']); ?></p>
    </header>

    <!-- Cards Ringkasan -->
    <div class="cards">
      <div class="card">
        <h3>Total Pendaftar</h3>
        <p><?php echo $total_pendaftar; ?></p>
      </div>
      <div class="card">
        <h3>Sudah Daftar Ulang</h3>
        <p><?php echo $total_daftar_ulang; ?></p>
      </div>
      <div class="card">
        <h3>Belum Hadir</h3>
        <p><?php echo $belum_hadir; ?></p>
      </div>
    </div>

    <!-- Tabel Data Pendaftar Terbaru -->
    <section class="table-section">
      <h2>Data Pendaftar Terbaru</h2>
      <table>
        <thead>
          <tr>
            <th style="width:50px;">No</th>
            <th>Nama</th>
            <th>NISN</th>
            <th>Email</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php 
          $no = 1;
          if ($result_latest->num_rows > 0) {
              while ($row = $result_latest->fetch_assoc()) {
                  echo "<tr>
                          <td>{$no}</td>
                          <td>".htmlspecialchars($row['nama_lengkap'])."</td>
                          <td>".htmlspecialchars($row['nisn'])."</td>
                          <td>".htmlspecialchars($row['email'])."</td>
                          <td>".ucfirst($row['status'])."</td>
                        </tr>";
                  $no++;
              }
          } else {
              echo "<tr><td colspan='5' style='text-align:center;'>Belum ada pendaftar</td></tr>";
          }
          ?>
        </tbody>
      </table>
    </section>
  </div>
</body>
</html>
