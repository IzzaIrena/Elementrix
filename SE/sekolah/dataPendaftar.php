<?php
session_start();
require '../koneksi.php';

// Cek login dan role
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'sekolah') {
    header("Location: login.php");
    exit();
}

$sekolah_id = $_SESSION['sekolah_id'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Data Pendaftar</title>
  <link rel="stylesheet" href="../css/dashboardSekolah.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

  <style>
    .table-section { background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 3px 8px rgba(0,0,0,0.1); margin-top: 20px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 10px; border-bottom: 1px solid #ddd; text-align: left; }
    th { background: #007bff; color: white; }
    tr:hover { background: #f9f9f9; }
    .status.pending { color: orange; font-weight: bold; }
    .status.diterima { color: green; font-weight: bold; }
    .status.ditolak { color: red; font-weight: bold; }
    .btn-detail { background: #007bff; color: #fff; padding: 6px 12px; border-radius: 6px; text-decoration: none; }
    .btn-detail:hover { background: #0056b3; }
  </style>
</head>
<body>
  <div class="sidebar">
    <h2><?php echo $_SESSION['nama_sekolah']; ?></h2>
    <ul>
      <li><a href="dashboardSekolah.php"><i class="fa fa-home"></i> Beranda</a></li>
      <li><a href="dataPendaftar.php" class="active"><i class="fa fa-users"></i> Data Pendaftar</a></li>
      <li><a href="pengumumanSeleksi.php"><i class="fa fa-bullhorn"></i> Pengumuman Seleksi</a></li>
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
      <h2>Daftar Pendaftar (Urut Nilai Tertinggi)</h2>
      <div id="data-pendaftar">
        <!-- Data akan dimuat lewat AJAX -->
        <p>Memuat data...</p>
      </div>
    </section>
  </div>

  <script>
    function loadData() {
      $.ajax({
        url: "fetchPendaftar.php",
        type: "GET",
        success: function(data) {
          $("#data-pendaftar").html(data);
        },
        error: function() {
          $("#data-pendaftar").html("<p style='color:red;'>Gagal memuat data.</p>");
        }
      });
    }

    // Muat pertama kali
    loadData();

    // Refresh tiap 5 detik
    setInterval(loadData, 5000);
  </script>
</body>
</html>
