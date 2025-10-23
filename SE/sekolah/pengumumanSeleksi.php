<?php
session_start();
require '../koneksi.php';

// Pastikan hanya sekolah yang bisa akses
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'sekolah') {
    header("Location: login.php");
    exit();
}

$sekolah_id = $_SESSION['sekolah_id'];
$pesan = "";

// ✅ Ambil status pengumuman dari tabel pendaftaran
$cek = $conn->prepare("
    SELECT MAX(pengumuman_dibuat) AS sudah_diumumkan, 
           MAX(tanggal_pengumuman) AS tanggal_pengumuman
    FROM pendaftaran 
    WHERE sekolah_id = ?
");
$cek->bind_param("i", $sekolah_id);
$cek->execute();
$hasil = $cek->get_result()->fetch_assoc();

$status_pengumuman = ($hasil['sudah_diumumkan'] == 1) ? 'sudah_diumumkan' : 'belum_diumumkan';
$tanggal_pengumuman = $hasil['tanggal_pengumuman'] ?? null;

// Hitung jumlah siswa diterima dan ditolak
$count = $conn->prepare("
    SELECT 
      SUM(CASE WHEN status='diterima' THEN 1 ELSE 0 END) AS diterima,
      SUM(CASE WHEN status='ditolak' THEN 1 ELSE 0 END) AS ditolak
    FROM pendaftaran
    WHERE sekolah_id=?
");
$count->bind_param("i", $sekolah_id);
$count->execute();
$jumlah = $count->get_result()->fetch_assoc();

// Ambil daftar siswa diterima dan ditolak
$data = $conn->prepare("
    SELECT p.id, s.nama_lengkap, s.nisn, p.status
    FROM pendaftaran p
    JOIN siswa s ON p.siswa_id = s.id
    WHERE p.sekolah_id=?
    ORDER BY s.nama_lengkap ASC
");
$data->bind_param("i", $sekolah_id);
$data->execute();
$list_siswa = $data->get_result();

// Jika tombol Buat Pengumuman ditekan
if (isset($_POST['buat_pengumuman'])) {

    // Periksa dan pastikan hanya diterima 1 sekolah per siswa
    $query_siswa = mysqli_query($conn, "
        SELECT siswa_id 
        FROM pendaftaran 
        WHERE status IN ('diterima', 'ditolak') 
        GROUP BY siswa_id
    ");

    while ($row_siswa = mysqli_fetch_assoc($query_siswa)) {
        $siswa_id = $row_siswa['siswa_id'];

        // Ambil semua pendaftaran siswa ini (urutkan misal berdasarkan sekolah_id terkecil atau prioritas)
        $query_pendaftaran = mysqli_query($conn, "
            SELECT * FROM pendaftaran 
            WHERE siswa_id = '$siswa_id' 
            ORDER BY sekolah_id ASC
        ");

        $pertama_diterima = false;

        while ($row_daftar = mysqli_fetch_assoc($query_pendaftaran)) {
            $id_pendaftaran = $row_daftar['id'];
            $status = $row_daftar['status'];

            if ($status === 'diterima') {
                if (!$pertama_diterima) {
                    $pertama_diterima = true; // pertahankan yang pertama
                } else {
                    mysqli_query($conn, "UPDATE pendaftaran SET status='ditolak' WHERE id='$id_pendaftaran'");
                }
            }
        }
    }

    // Tandai pengumuman sudah dibuat
    $now = date('Y-m-d H:i:s');
    mysqli_query($conn, "UPDATE pendaftaran SET pengumuman_dibuat=1, tanggal_pengumuman='$now' WHERE sekolah_id='$sekolah_id' AND status IN ('diterima','ditolak')");

    echo "<script>alert('Pengumuman seleksi telah dibuat dan final.'); window.location.href='pengumumanSeleksi.php';</script>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Pengumuman Seleksi</title>
<link rel="stylesheet" href="../css/dashboardSekolah.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
body { font-family: 'Segoe UI', sans-serif; margin: 0; background: #f4f6f9; }
.main-content { margin-left: 250px; padding: 30px; }
.container { background:#fff; padding:20px; border-radius:12px; box-shadow:0 3px 8px rgba(0,0,0,0.1); margin-top:20px; }
.btn { padding:10px 20px; border:none; border-radius:8px; cursor:pointer; font-weight:500; }
.btn-primary { background:#007bff; color:#fff; }
.btn-primary:hover { background:#0056b3; }
.status { font-weight:bold; text-transform:capitalize; }
.table { border-collapse:collapse; width:100%; margin-top:20px; }
.table th, .table td { border:1px solid #ddd; padding:10px; text-align:left; }
.table th { background:#f2f2f2; }
.badge { padding:6px 10px; border-radius:6px; color:#fff; font-size:13px; }
.badge-diterima { background:#28a745; }
.badge-ditolak { background:#dc3545; }
</style>
</head>
<body>
<!-- SIDEBAR -->
<div class="sidebar">
  <h2><?php echo $_SESSION['nama_sekolah']; ?></h2>
    <ul>
      <li><a href="dashboardSekolah.php"><i class="fa fa-home"></i> Beranda</a></li>
      <li><a href="dataPendaftar.php"><i class="fa fa-users"></i> Data Pendaftar</a></li>
      <li><a href="pengumumanSeleksi.php" class="active"><i class="fa fa-bullhorn"></i> Pengumuman Seleksi</a></li>
      <li><a href="jadwalDaftarUlang.php"><i class="fa fa-calendar"></i> Jadwal Daftar Ulang</a></li>
      <li><a href="daftarUlang.php"><i class="fa fa-clipboard-check"></i> Data Daftar Ulang</a></li>
      <li><a href="logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</div>

<!-- MAIN CONTENT -->
<div class="main-content">
  <header>
    <h1>📢 Pengumuman Seleksi</h1>
    <p>Kelola hasil seleksi dan umumkan hasil akhir kepada siswa</p>
  </header>

  <div class="container">
    <p><b>Status Pengumuman:</b> 
      <span class="status" style="color:<?php echo $status_pengumuman=='sudah_diumumkan'?'green':'orange'; ?>">
        <?php echo ucfirst(str_replace('_',' ',$status_pengumuman)); ?>
      </span>
    </p>

    <p>Jumlah siswa diterima: <b><?php echo $jumlah['diterima'] ?? 0; ?></b></p>
    <p>Jumlah siswa ditolak: <b><?php echo $jumlah['ditolak'] ?? 0; ?></b></p>

    <?php if ($status_pengumuman == 'belum_diumumkan'): ?>
      <form method="post" onsubmit="return confirm('Yakin ingin mengumumkan hasil seleksi sekarang?')">
        <button type="submit" name="buat_pengumuman" class="btn btn-primary">📢 Buat Pengumuman Sekarang</button>
      </form>
    <?php else: ?>
      <p style="color:green;">🎉 Pengumuman sudah dikirim ke siswa pada 
        <b><?= !empty($tanggal_pengumuman) ? date('d M Y H:i', strtotime($tanggal_pengumuman)) : '-' ?></b>
      </p>
    <?php endif; ?>
  </div>

  <div class="container">
    <h3>Daftar Hasil Seleksi</h3>
    <table class="table">
      <thead>
        <tr>
          <th>No</th>
          <th>Nama Lengkap</th>
          <th>NISN</th>
          <th>Status Seleksi</th>
        </tr>
      </thead>
      <tbody>
        <?php 
        $no = 1;
        while ($row = $list_siswa->fetch_assoc()): ?>
          <tr>
            <td><?= $no++; ?></td>
            <td><?= htmlspecialchars($row['nama_lengkap']); ?></td>
            <td><?= htmlspecialchars($row['nisn']); ?></td>
            <td>
              <?php if ($row['status'] == 'diterima'): ?>
                <span class="badge badge-diterima">Diterima</span>
              <?php elseif ($row['status'] == 'ditolak'): ?>
                <span class="badge badge-ditolak">Ditolak</span>
              <?php else: ?>
                <span class="badge" style="background:#6c757d;">Pending</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
