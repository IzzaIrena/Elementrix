<?php 
include '../koneksi.php';
session_start();

// Pastikan sekolah sudah login
if (!isset($_SESSION['sekolah_id'])) {
    header("Location: ../loginSekolah.php");
    exit;
}

if (!isset($_GET['id'])) {
    echo "ID pendaftar tidak ditemukan.";
    exit;
}

$id_pendaftaran = $_GET['id'];
$sekolah_id = $_SESSION['sekolah_id'];

// Ambil data pendaftaran dan siswa
$query = "
SELECT p.id AS id_pendaftaran, p.status, p.tanggal_daftar, p.pengumuman_dibuat,
       s.id AS siswa_id, s.nisn, s.nama_lengkap, s.email, s.no_hp, s.nik, 
       s.tempat_lahir, s.tanggal_lahir, s.jenis_kelamin, s.alamat
FROM pendaftaran p
JOIN siswa s ON p.siswa_id = s.id
WHERE p.id = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id_pendaftaran);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if (!$data) {
    echo "Data pendaftar tidak ditemukan.";
    exit;
}

$siswa_id = $data['siswa_id'];
$pesan = "";

// === PROSES TERIMA / TOLAK ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pendaftaran_id = $id_pendaftaran;

    // Ambil info sekolah dan kuota
    $q_sekolah = $conn->prepare("SELECT kuota FROM sekolah WHERE id=?");
    $q_sekolah->bind_param("i", $sekolah_id);
    $q_sekolah->execute();
    $data_sekolah = $q_sekolah->get_result()->fetch_assoc();
    $kuota = $data_sekolah['kuota'] ?? 0;

    // Hitung total diterima saat ini
    $q_diterima = $conn->prepare("SELECT COUNT(*) AS total FROM pendaftaran WHERE sekolah_id=? AND status='diterima'");
    $q_diterima->bind_param("i", $sekolah_id);
    $q_diterima->execute();
    $total_diterima = $q_diterima->get_result()->fetch_assoc()['total'];

    // === Jika tombol Terima ditekan ===
    if (isset($_POST['terima'])) {
        if ($total_diterima >= $kuota) {
            echo "<script>alert('Kuota sekolah sudah penuh. Tidak dapat menerima siswa lagi.'); history.back();</script>";
            exit;
        }

        // Update status siswa diterima di sekolah ini
        $update = $conn->prepare("UPDATE pendaftaran SET status='diterima', pengumuman_dibuat=0, tanggal_pengumuman=NULL WHERE id=?");
        $update->bind_param("i", $pendaftaran_id);
        $update->execute();

        // 🔥 Tambahkan ini: otomatis tolak di sekolah lain
        $autoReject = $conn->prepare("UPDATE pendaftaran SET status='ditolak', pengumuman_dibuat=0, tanggal_pengumuman=NULL
                                      WHERE siswa_id=? AND sekolah_id != ?");
        $autoReject->bind_param("ii", $siswa_id, $sekolah_id);
        $autoReject->execute();

        $pesan = "✅ Siswa telah diterima di sekolah ini. Pendaftarannya di sekolah lain otomatis ditolak.";

        // Tambah total diterima
        $total_diterima++;

        // Jika sudah mencapai kuota, tolak semua pending
        if ($total_diterima >= $kuota) {
            $conn->query("UPDATE pendaftaran SET status='ditolak' 
                          WHERE sekolah_id='$sekolah_id' AND status='pending'");
        }
    }

    // === Jika tombol Tolak ditekan ===
    if (isset($_POST['tolak'])) {
        $update = $conn->prepare("UPDATE pendaftaran SET status='ditolak', pengumuman_dibuat=0, tanggal_pengumuman=NULL WHERE id=?");
        $update->bind_param("i", $pendaftaran_id);
        $update->execute();
        $pesan = "❌ Siswa telah ditolak.";
    }

    header("Location: detailPendaftar.php?id=$id_pendaftaran&pesan=" . urlencode($pesan));
    exit;
}

// === Ambil ulang data setelah update ===
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

// === Ambil data sekolah asal ===
$q_sekolah_asal = $conn->prepare("SELECT * FROM sekolah_asal WHERE siswa_id=?");
$q_sekolah_asal->bind_param("i", $siswa_id);
$q_sekolah_asal->execute();
$d_sekolah = $q_sekolah_asal->get_result()->fetch_assoc();

// === Ambil data orang tua / wali ===
$q_ortu = $conn->prepare("SELECT * FROM ortu_wali WHERE siswa_id=?");
$q_ortu->bind_param("i", $siswa_id);
$q_ortu->execute();
$d_ortu = $q_ortu->get_result()->fetch_assoc();

// === Ambil nilai akademik ===
$q_nilai = $conn->prepare("SELECT * FROM nilai_akademik WHERE siswa_id=?");
$q_nilai->bind_param("i", $siswa_id);
$q_nilai->execute();
$r_nilai = $q_nilai->get_result();

// === Ambil dokumen siswa ===
$q_dok = $conn->prepare("SELECT * FROM dokumen_siswa WHERE siswa_id=?");
$q_dok->bind_param("i", $siswa_id);
$q_dok->execute();
$r_dok = $q_dok->get_result();

$pesan = $_GET['pesan'] ?? null;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Detail Pendaftar</title>
    <link rel="stylesheet" href="../css/dashboardSekolah.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; margin: 0; background: #f4f6f9; }
        .content { margin-left: 250px; padding: 30px; }
        .card { background: #fff; border-radius: 10px; padding: 20px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .card h3 { color: #2c3e50; border-bottom: 2px solid #007bff; padding-bottom: 5px; margin-bottom: 15px; }
        .table-detail { border-collapse: collapse; width: 100%; margin-bottom: 10px; }
        .table-detail th, .table-detail td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        .table-detail th { background-color: #f2f2f2; width: 30%; }
        .btn { display: inline-block; padding: 8px 15px; text-decoration: none; border-radius: 6px; color: #fff; font-weight: 500; margin-right: 10px; border: none; cursor: pointer; }
        .btn-primary { background: #007bff; }
        .btn-success { background: #28a745; }
        .btn-danger { background: #dc3545; }
        .btn:hover { opacity: 0.9; }
        h2 { color: #34495e; margin-bottom: 10px; }
        .status-info { margin-top: 15px; color: gray; }
        .alert { background: #eaf7ea; padding: 10px 15px; border-left: 5px solid #28a745; color: #2d6a4f; border-radius: 5px; margin-bottom: 20px; }
    </style>
</head>
<body>

<!-- SIDEBAR -->
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

<!-- CONTENT -->
<div class="content">
<h2>Detail Pendaftar</h2>

<?php if ($pesan): ?>
<div class="alert"><?= htmlspecialchars($pesan) ?></div>
<?php endif; ?>

<div class="card">
<h3>Data Pribadi</h3>
<table class="table-detail">
  <tr><th>Nama Lengkap</th><td><?= htmlspecialchars($data['nama_lengkap']) ?></td></tr>
  <tr><th>NISN</th><td><?= htmlspecialchars($data['nisn']) ?></td></tr>
  <tr><th>NIK</th><td><?= htmlspecialchars($data['nik']) ?></td></tr>
  <tr><th>Email</th><td><?= htmlspecialchars($data['email']) ?></td></tr>
  <tr><th>No HP</th><td><?= htmlspecialchars($data['no_hp']) ?></td></tr>
  <tr><th>Tempat, Tanggal Lahir</th><td><?= htmlspecialchars($data['tempat_lahir']) ?>, <?= htmlspecialchars($data['tanggal_lahir']) ?></td></tr>
  <tr><th>Jenis Kelamin</th><td><?= ($data['jenis_kelamin'] == 'L' ? 'Laki-laki' : 'Perempuan') ?></td></tr>
  <tr><th>Alamat</th><td><?= htmlspecialchars($data['alamat']) ?></td></tr>
  <tr><th>Status Pendaftaran</th><td><?= htmlspecialchars($data['status']) ?></td></tr>
  <tr><th>Tanggal Daftar</th><td><?= htmlspecialchars($data['tanggal_daftar']) ?></td></tr>
</table>
</div>

<div class="card">
<h3>Data Sekolah Asal</h3>
<table class="table-detail">
  <tr><th>Nama Sekolah</th><td><?= htmlspecialchars($d_sekolah['nama_sekolah_asal'] ?? '-') ?></td></tr>
  <tr><th>NPSN Sekolah Asal</th><td><?= htmlspecialchars($d_sekolah['npsn_sekolah_asal'] ?? '-') ?></td></tr>
  <tr><th>Alamat Sekolah Asal</th><td><?= htmlspecialchars($d_sekolah['alamat_sekolah_asal'] ?? '-') ?></td></tr>
</table>
</div>

<div class="card">
<h3>Data Orang Tua / Wali</h3>
<table class="table-detail">
  <tr><th>Nama Ayah</th><td><?= htmlspecialchars($d_ortu['nama_ayah'] ?? '-') ?></td></tr>
  <tr><th>No HP Ayah</th><td><?= htmlspecialchars($d_ortu['no_hp_ayah'] ?? '-') ?></td></tr>
  <tr><th>Nama Ibu</th><td><?= htmlspecialchars($d_ortu['nama_ibu'] ?? '-') ?></td></tr>
  <tr><th>No HP Ibu</th><td><?= htmlspecialchars($d_ortu['no_hp_ibu'] ?? '-') ?></td></tr>
  <tr><th>Nama Wali</th><td><?= htmlspecialchars($d_ortu['nama_wali'] ?? '-') ?></td></tr>
  <tr><th>No HP Wali</th><td><?= htmlspecialchars($d_ortu['no_hp_wali'] ?? '-') ?></td></tr>
</table>
</div>

<div class="card">
<h3>Nilai Akademik</h3>
<table class="table-detail">
  <thead>
    <tr>
      <th>Mata Pelajaran</th>
      <th>Semester 1</th>
      <th>Semester 2</th>
      <th>Semester 3</th>
      <th>Semester 4</th>
      <th>Semester 5</th>
    </tr>
  </thead>
  <tbody>
    <?php while ($n = $r_nilai->fetch_assoc()): ?>
    <tr>
      <td><?= htmlspecialchars($n['mapel']) ?></td>
      <td><?= htmlspecialchars($n['semester_1']) ?></td>
      <td><?= htmlspecialchars($n['semester_2']) ?></td>
      <td><?= htmlspecialchars($n['semester_3']) ?></td>
      <td><?= htmlspecialchars($n['semester_4']) ?></td>
      <td><?= htmlspecialchars($n['semester_5']) ?></td>
    </tr>
    <?php endwhile; ?>
  </tbody>
</table>
</div>

<div class="card">
<h3>Dokumen Siswa</h3>
<table class="table-detail">
  <thead>
    <tr>
      <th>Nama Dokumen</th>
      <th>Status</th>
      <th>File</th>
    </tr>
  </thead>
  <tbody>
    <?php if ($r_dok->num_rows > 0): ?>
      <?php while ($d = $r_dok->fetch_assoc()): ?>
      <tr>
        <td><?= htmlspecialchars($d['nama_dokumen']) ?></td>
        <td><?= htmlspecialchars($d['status']) ?></td>
        <td><a href="../uploads/dokumen/<?= htmlspecialchars($d['file_path']) ?>" target="_blank">Lihat File</a></td>
      </tr>
      <?php endwhile; ?>
    <?php else: ?>
      <tr><td colspan="3">Belum ada dokumen diunggah.</td></tr>
    <?php endif; ?>
  </tbody>
</table>
</div>

<a href="dataPendaftar.php" class="btn btn-primary">Kembali ke Data Pendaftar</a>

<?php if ($data['pengumuman_dibuat'] == 0): ?>
<form method="post" style="display:inline;">
  <button type="submit" name="terima" class="btn btn-success" onclick="return confirm('Terima siswa ini?')">Terima</button>
  <button type="submit" name="tolak" class="btn btn-danger" onclick="return confirm('Tolak siswa ini?')">Tolak</button>
</form>
<?php else: ?>
  <p class="status-info">🔒 Pengumuman sudah dibuat. Tidak dapat mengubah hasil seleksi.</p>
<?php endif; ?>

</div>
</div>

</body>
</html>
