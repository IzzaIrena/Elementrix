<?php
session_start();
include("../koneksi.php");

// Cek login
if (!isset($_SESSION['dinas_id'])) {
    header("Location: loginDinas.php");
    exit;
}

// Ambil semua tahun akademik
$tahunList = $conn->query("SELECT id, nama_tahun, status FROM tahun_akademik ORDER BY id DESC");

// Tahun yang dipilih dari dropdown (atau default ke tahun aktif)
$tahun_id = isset($_GET['tahun_id']) ? intval($_GET['tahun_id']) : 0;

if ($tahun_id == 0) {
    $qTahun = $conn->query("SELECT id, nama_tahun FROM tahun_akademik WHERE status='aktif' LIMIT 1");
    $tahunAktif = $qTahun->fetch_assoc();
    if (!$tahunAktif) {
        die("<h3 style='color:red;text-align:center;margin-top:50px;'>⚠️ Belum ada tahun akademik aktif! Silakan hubungi admin sistem.</h3>");
    }
    $tahun_id = $tahunAktif['id'];
    $nama_tahun_aktif = $tahunAktif['nama_tahun'];
} else {
    $qTahun = $conn->prepare("SELECT nama_tahun FROM tahun_akademik WHERE id = ?");
    $qTahun->bind_param("i", $tahun_id);
    $qTahun->execute();
    $res = $qTahun->get_result();
    $nama_tahun_aktif = $res->fetch_assoc()['nama_tahun'];
}

// Ambil data pendaftaran
$sql = "SELECT p.id AS pendaftaran_id, s.id AS siswa_id, s.nama_lengkap, s.nisn, s.nik,
               s.alamat, s.no_hp, s.jenis_kelamin, s.tempat_lahir, s.tanggal_lahir,
               sek.nama_sekolah AS sekolah_pilihan, p.status
        FROM pendaftaran p
        JOIN siswa s ON s.id = p.siswa_id
        JOIN sekolah sek ON sek.id = p.sekolah_id
        WHERE p.tahun_id = ?
        ORDER BY s.nama_lengkap ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $tahun_id);
$stmt->execute();
$pendaftaran = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Kelola Pendaftaran | Dinas Pendidikan</title>
<link rel="stylesheet" href="../css/dashboardDinas.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
.main-content {margin-left:230px; padding:20px; background:#f8f9fb; min-height:100vh;}
.main-content header {
  background:#007bff; color:#fff; padding:15px 25px; border-radius:8px;
  margin-bottom:20px; display:flex; justify-content:space-between; align-items:center;
}
.table-container {
  background:white; padding:20px; border-radius:10px; box-shadow:0 2px 6px rgba(0,0,0,0.1);
}
.table-data {width:100%; border-collapse:collapse;}
.table-data th, .table-data td {padding:10px 12px; border:1px solid #ddd; text-align:left;}
.table-data th {background:#007bff; color:white;}
.table-data tr:nth-child(even){background:#f2f6fc;}
.filter-form {margin-bottom:15px;}
.filter-form select {
  padding:8px 10px; border-radius:5px; border:1px solid #ccc; font-size:14px;
}
.filter-form button {
  padding:8px 12px; border:none; background:#007bff; color:white; border-radius:5px; cursor:pointer;
}
.filter-form button:hover {background:#0069d9;}
.btn-detail {
  background:#28a745; color:white; border:none; border-radius:5px;
  padding:6px 10px; cursor:pointer; font-size:13px;
}
.btn-detail:hover {background:#218838;}
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
      <li><a href="monitoring.php"><i class="fa-solid fa-chart-line"></i> Monitoring</a></li>
      <li><a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
    </ul>
  </aside>

  <!-- Main Content -->
  <div class="main-content">
    <header>
      <h1><i class="fa-solid fa-file-lines"></i> Kelola Pendaftaran</h1>
      <span><b>Tahun Aktif:</b> <?= htmlspecialchars($nama_tahun_aktif) ?></span>
    </header>

    <div class="table-container">
      <form method="get" class="filter-form">
        <label for="tahun_id"><b>Pilih Tahun Akademik:</b></label>
        <select name="tahun_id" id="tahun_id" required>
          <?php while($row = $tahunList->fetch_assoc()): ?>
            <option value="<?= $row['id'] ?>" <?= ($row['id']==$tahun_id?'selected':'') ?>>
              <?= htmlspecialchars($row['nama_tahun']) ?> <?= ($row['status']=='aktif'?'(Aktif)':'') ?>
            </option>
          <?php endwhile; ?>
        </select>
        <button type="submit"><i class="fa-solid fa-filter"></i> Tampilkan</button>
      </form>

      <h2>Data Pendaftaran Siswa</h2>
      <?php if ($pendaftaran->num_rows > 0): ?>
      <table class="table-data">
        <thead>
          <tr>
            <th>No</th>
            <th>Nama Siswa</th>
            <th>NISN</th>
            <th>TTL</th>
            <th>Jenis Kelamin</th>
            <th>Sekolah Pilihan</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php $no=1; while($row = $pendaftaran->fetch_assoc()): ?>
          <tr>
            <td><?= $no++ ?></td>
            <td><?= htmlspecialchars($row['nama_lengkap']) ?></td>
            <td><?= htmlspecialchars($row['nisn']) ?></td>
            <td><?= htmlspecialchars($row['tempat_lahir']) ?>, <?= htmlspecialchars($row['tanggal_lahir']) ?></td>
            <td><?= ($row['jenis_kelamin']=='L'?'Laki-laki':'Perempuan') ?></td>
            <td><?= htmlspecialchars($row['sekolah_pilihan']) ?></td>
            <td><b><?= ucfirst($row['status']) ?></b></td>
            <td>
                <a href="lihatDetailPendaftaran.php?id=<?= $row['pendaftaran_id'] ?>" class="btn-detail">
                    <i class="fa-solid fa-eye"></i> Detail
                </a>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
      <?php else: ?>
        <p style="text-align:center;color:gray;">Belum ada data pendaftaran pada tahun akademik ini.</p>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
