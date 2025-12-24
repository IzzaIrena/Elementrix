<?php
session_start();
include("../koneksi_mysql.php"); // koneksi MySQL ($conn)

// Pastikan siswa sudah login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'siswa') {
    header("Location: loginSiswa.php");
    exit;
}

$siswa_id = $_SESSION['user_id'];

// Ambil data siswa dari MySQL
$sqlSiswa = "SELECT s.*, u.email AS email_user 
             FROM siswa s 
             LEFT JOIN user u ON u.id = s.user_id 
             WHERE s.user_id = ?";
$stmt = mysqli_prepare($conn, $sqlSiswa);
mysqli_stmt_bind_param($stmt, "i", $siswa_id);
mysqli_stmt_execute($stmt);
$resSiswa = mysqli_stmt_get_result($stmt);

if (!$resSiswa || mysqli_num_rows($resSiswa) == 0) {
    die("Data siswa tidak ditemukan di database.");
}

$siswaData = mysqli_fetch_assoc($resSiswa);
$nama_lengkap = $siswaData['nama_lengkap'] ?? "Siswa";

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Data Sekolah - PPDB</title>
<link rel="stylesheet" href="../css/dashboardSiswa.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
    .main {
        padding: 30px;
        color: #2a3f54;
    }

    .search-box {
        background: rgba(255, 255, 255, 0.1);
        padding: 15px;
        border-radius: 12px;
        margin-bottom: 20px;
    }

    .search-box input {
        width: 100%;
        padding: 10px;
        border-radius: 8px;
        border: none;
        outline: none;
        font-size: 15px;
    }

    .hasil-item {
        background: rgba(255,255,255,0.08);
        border-radius: 10px;
        padding: 12px;
        margin-bottom: 15px;
        backdrop-filter: blur(8px);
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }

    .hasil-item h4 {
        margin: 0 0 8px 0;
        color: #ffd166;
    }

    .tabel-batas {
        width: 100%;
        border-collapse: collapse;
        margin-top: 8px;
    }

    .tabel-batas th, .tabel-batas td {
        border: 1px solid rgba(255,255,255,0.3);
        padding: 8px;
        text-align: center;
        font-size: 14px;
    }

    .tabel-batas th {
        background: rgba(255,255,255,0.15);
    }
</style>
</head>
<body>
<?php include('headerSiswa.php'); ?>
<?php include('sidebarSiswa.php'); ?>

<div class="main">
    <h2><i class="fa-solid fa-chart-line"></i> Data Sekolah & Ambang Batas</h2>
    <p>Lihat ambang batas nilai (akademik) dan jarak (zonasi) untuk setiap sekolah yang sudah menerima siswa.</p>

    <div class="search-box">
        <input type="text" id="searchSekolah" placeholder="🔍 Cari sekolah..." onkeyup="cariSekolah()">
    </div>

    <div id="hasilBatas"></div>
</div>

<script>
function cariSekolah(){
  let s = document.getElementById('searchSekolah').value;
  if(s.length < 2){
      document.getElementById('hasilBatas').innerHTML = '<p style="opacity:0.7;">Ketik minimal 2 huruf untuk mencari sekolah.</p>';
      return;
  }
  fetch('batas_penerimaan.php?search=' + encodeURIComponent(s))
    .then(res => res.text())
    .then(data => {
        document.getElementById('hasilBatas').innerHTML = data || '<p>Tidak ditemukan data sekolah dengan kata tersebut.</p>';
    });
}
</script>

</body>
</html>
