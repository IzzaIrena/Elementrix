<?php
session_start();
include "../koneksi.php";

// Cek login dinas
if (!isset($_SESSION['dinas_id'])) {
    header("Location: loginDinas.php");
    exit;
}

if (!isset($_GET['id'])) {
    die("<h3 style='color:red;text-align:center;margin-top:50px;'>❌ ID pendaftaran tidak ditemukan.</h3>");
}

$id = intval($_GET['id']);

// Ambil detail pendaftaran
$query = "
    SELECT 
        p.id AS pendaftaran_id,
        p.tanggal_daftar,
        p.status,
        s.id AS siswa_id,
        s.nama_lengkap,
        s.nisn,
        s.nik,
        s.tempat_lahir,
        s.tanggal_lahir,
        s.jenis_kelamin,
        s.alamat,
        s.no_hp,
        sk.nama_sekolah AS sekolah_tujuan,
        ta.nama_tahun AS tahun_akademik
    FROM pendaftaran p
    JOIN siswa s ON p.siswa_id = s.id
    JOIN sekolah sk ON p.sekolah_id = sk.id
    JOIN tahun_akademik ta ON p.tahun_id = ta.id
    WHERE p.id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("<h3 style='color:red;text-align:center;margin-top:50px;'>❌ Data pendaftaran tidak ditemukan.</h3>");
}

$data = $result->fetch_assoc();
$nama_dinas = $_SESSION['nama_dinas'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Detail Pendaftaran Siswa</title>
  <link rel="stylesheet" href="../css/dashboardDinas.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
  body {
    font-family: Arial, sans-serif;
    margin: 0;
    background: #f4f6f9;
  }

  /* === Sidebar === */
  .sidebar {
    width: 240px;
    background: #2c3e50;
    position: fixed;
    top: 0;
    bottom: 0;
    padding-top: 70px;
  }
  .sidebar h2 {
    color: white;
    text-align: center;
    margin-top: -50px;
    margin-bottom: 30px;
  }
  .sidebar ul {
    list-style: none;
    padding: 0;
  }
  .sidebar li a {
    display: block;
    padding: 12px 20px;
    color: white;
    text-decoration: none;
    font-size: 15px;
  }
  .sidebar li a:hover,
  .sidebar li a.active {
    background: #34495e;
  }

  /* === Main Content === */
  .main-content {
    margin-left: 240px;
    padding: 25px;
  }

  header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: white;
    padding: 15px 25px;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 25px;
  }

  header h1 {
    color: #2c3e50;
  }

  .user-info {
    color: #333;
    font-weight: bold;
  }

  /* === Detail Box === */
  .detail-box {
    background: white;
    padding: 25px;
    border-radius: 10px;
    box-shadow: 0 3px 8px rgba(0,0,0,0.1);
  }
  .detail-box h2 {
    margin-bottom: 20px;
    color: #007bff;
  }
  .detail-table {
    width: 100%;
    border-collapse: collapse;
  }
  .detail-table td {
    padding: 10px 8px;
    vertical-align: top;
  }
  .detail-table td:first-child {
    font-weight: bold;
    width: 200px;
    color: #333;
  }

  .btn-back {
    margin-top: 20px;
    display: inline-block;
    background: #6c757d;
    color: white;
    padding: 8px 14px;
    border-radius: 6px;
    text-decoration: none;
  }
  .btn-back:hover {
    background: #5a6268;
  }

  .status {
    padding: 6px 10px;
    border-radius: 6px;
    color: white;
    font-weight: bold;
  }
  .status.pending { background: #ffc107; }
  .status.diterima { background: #28a745; }
  .status.ditolak { background: #dc3545; }
  </style>
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
      <li><a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
    </ul>
  </aside>

  <!-- Main -->
  <div class="main-content">
    <header>
      <h1>Dashboard Dinas Pendidikan</h1>
      <div class="user-info">
        <i class="fa-solid fa-user-tie"></i> <?= htmlspecialchars($nama_dinas) ?>
      </div>
    </header>

    <div class="detail-box">
      <h2><i class="fa-solid fa-id-card"></i> Detail Pendaftaran Siswa</h2>
      <table class="detail-table">
        <tr><td>Nama Lengkap</td><td><?= htmlspecialchars($data['nama_lengkap']) ?></td></tr>
        <tr><td>NISN</td><td><?= htmlspecialchars($data['nisn']) ?></td></tr>
        <tr><td>NIK</td><td><?= htmlspecialchars($data['nik']) ?></td></tr>
        <tr><td>Tempat, Tanggal Lahir</td><td><?= htmlspecialchars($data['tempat_lahir']) ?>, <?= htmlspecialchars($data['tanggal_lahir']) ?></td></tr>
        <tr><td>Jenis Kelamin</td><td><?= $data['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan' ?></td></tr>
        <tr><td>Alamat</td><td><?= htmlspecialchars($data['alamat']) ?></td></tr>
        <tr><td>No. HP</td><td><?= htmlspecialchars($data['no_hp']) ?></td></tr>
        <tr><td>Sekolah Tujuan</td><td><?= htmlspecialchars($data['sekolah_tujuan']) ?></td></tr>
        <tr><td>Tahun Akademik</td><td><?= htmlspecialchars($data['tahun_akademik']) ?></td></tr>
        <tr><td>Tanggal Daftar</td><td><?= htmlspecialchars($data['tanggal_daftar']) ?></td></tr>
        <tr><td>Status</td>
          <td>
            <span class="status <?= strtolower($data['status']) ?>">
              <?= ucfirst($data['status']) ?>
            </span>
          </td>
        </tr>
      </table>

      <a href="kelolaPendaftaranDinas.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Kembali</a>
    </div>
  </div>
</body>
</html>

