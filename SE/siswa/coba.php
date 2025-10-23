<?php
session_start();
require '../koneksi.php';

// Cek login dan role
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'sekolah') {
    header("Location: login.php");
    exit();
}

$sekolah_id = $_SESSION['sekolah_id'];

$query = "
    SELECT 
        p.id AS pendaftaran_id,
        s.nama_lengkap,
        s.nisn,
        s.email,
        s.no_hp,
        p.status,
        p.tanggal_daftar
    FROM pendaftaran p
    JOIN siswa s ON p.siswa_id = s.id
    WHERE p.sekolah_id = ?
    ORDER BY p.tanggal_daftar DESC
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $sekolah_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Data Pendaftar</title>
  <link rel="stylesheet" href="../css/dashboardSekolah.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    .table-section { background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 3px 8px rgba(0,0,0,0.1); margin-top: 20px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 10px; border-bottom: 1px solid #ddd; }
    th { background: #007bff; color: white; }
    tr:hover { background: #f9f9f9; }
    .status.pending { color: orange; font-weight: bold; }
    .status.diterima { color: green; font-weight: bold; }
    .status.ditolak { color: red; font-weight: bold; }
    .btn-detail {
      background: #007bff; color: #fff; padding: 6px 12px;
      border-radius: 6px; text-decoration: none;
    }
    .btn-detail:hover { background: #0056b3; }
  </style>
</head>
<body>
  <div class="sidebar">
    <h2><?php echo $_SESSION['nama_sekolah']; ?></h2>
    <ul>
      <li><a href="dashboardSekolah.php"><i class="fa fa-home"></i> Beranda</a></li>
      <li><a href="dataPendaftar.php" class="active"><i class="fa fa-users"></i> Data Pendaftar</a></li>
      <li><a href="jadwalDaftarUlang.php"><i class="fa fa-calendar"></i> Jadwal Daftar Ulang</a></li>
      <li><a href="daftarUlang.php"><i class="fa fa-clipboard-check"></i> Data Daftar Ulang</a></li>
      <li><a href="logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a></li>
    </ul>
  </div>

  <div class="main-content">
    <header>
      <h1>Data Pendaftar</h1>
      <p>Daftar pendaftar ke sekolah Anda</p>
    </header>

    <section class="table-section">
      <h2>Daftar Pendaftar</h2>
      <table>
        <thead>
          <tr>
            <th>No</th>
            <th>Nama Lengkap</th>
            <th>NISN</th>
            <th>Email</th>
            <th>No HP</th>
            <th>Tanggal Daftar</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php 
          if ($result->num_rows > 0) {
              $no = 1;
              while ($row = $result->fetch_assoc()) {
                  echo "<tr>
                      <td>{$no}</td>
                      <td>".htmlspecialchars($row['nama_lengkap'])."</td>
                      <td>".htmlspecialchars($row['nisn'])."</td>
                      <td>".htmlspecialchars($row['email'])."</td>
                      <td>".htmlspecialchars($row['no_hp'])."</td>
                      <td>".date('d M Y', strtotime($row['tanggal_daftar']))."</td>
                      <td><span class='status {$row['status']}'>".ucfirst($row['status'])."</span></td>
                      <td><a href='detailPendaftar.php?id={$row['pendaftaran_id']}' class='btn-detail'><i class='fa fa-eye'></i> Lihat</a></td>
                  </tr>";
                  $no++;
              }
          } else {
              echo "<tr><td colspan='8' style='text-align:center;'>Belum ada pendaftar</td></tr>";
          }
          ?>
        </tbody>
      </table>
    </section>
  </div>
</body>
</html>
