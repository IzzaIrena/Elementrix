<?php
session_start();
include "../koneksi_mysql.php"; // koneksi MySQL

// ============================
// CEK LOGIN DINAS
// ============================
if (!isset($_SESSION['dinas_id'])) {
    header("Location: loginDinas.php");
    exit;
}

$nama_dinas = $_SESSION['nama_dinas'];
$today = date("Y-m-d");

// ============================
// 1. AMBIL TAHUN AKADEMIK AKTIF
// ============================
$tahun = mysqli_query($conn, "SELECT * FROM tahun_akademik WHERE status='aktif' LIMIT 1");
if (mysqli_num_rows($tahun) == 0) {
    $tahun_id = 0;
    $tahun_aktif = null;
} else {
    $tahun_aktif = mysqli_fetch_assoc($tahun);
    $tahun_id = $tahun_aktif['id'];
}

// ============================
// 2. STATISTIK
// ============================

// Total sekolah
$qSekolah = mysqli_query($conn, "SELECT COUNT(*) AS total FROM sekolah");
$total_sekolah = mysqli_fetch_assoc($qSekolah)['total'];

// Total pendafatar
$qPendaftar = mysqli_query($conn, "
    SELECT COUNT(DISTINCT siswa_id) AS total
    FROM pendaftaran
    WHERE tahun_id = $tahun_id
");
$total_pendaftar = (int) mysqli_fetch_assoc($qPendaftar)['total'];

// Lulus
$qLulus = mysqli_query($conn, "
    SELECT COUNT(*) AS total
    FROM pendaftaran
    WHERE tahun_id = $tahun_id
      AND sekolah_diterima IS NOT NULL
");
$total_lulus = (int) mysqli_fetch_assoc($qLulus)['total'];

// Sudah daftar ulang, hadir
$qDaftarUlang = mysqli_query($conn, "
    SELECT COUNT(DISTINCT b.siswa_id) AS total
    FROM booking_daftar_ulang b
    INNER JOIN pendaftaran p
        ON p.siswa_id = b.siswa_id
    WHERE p.tahun_id = $tahun_id
      AND b.status_keterangan != 'Belum Hadir'
");
$total_daftar_ulang = (int) mysqli_fetch_assoc($qDaftarUlang)['total'];

// ============================
// Pendaftaran tahun ini (ambil semua pendaftaran tahun aktif)
// ============================
$tahun_id = (int)$tahun_id; // pastikan integer

$qPendaftaran = mysqli_query($conn, "
    SELECT * FROM pendaftaran WHERE tahun_id = $tahun_id
");
$pendaftaranList = [];
while ($row = mysqli_fetch_assoc($qPendaftaran)) {
    $pendaftaranList[] = $row;
}

// Hitung siswa belum mendaftar
$qBelum = mysqli_query($conn,
    "SELECT COUNT(s.id) AS total_belum
     FROM siswa s
     LEFT JOIN pendaftaran p
       ON p.siswa_id = s.id AND p.tahun_id = $tahun_id
     WHERE p.id IS NULL"
);

$siswaBelumDaftar = 0;
if ($qBelum && mysqli_num_rows($qBelum) > 0) {
    $siswaBelumDaftar = (int) mysqli_fetch_assoc($qBelum)['total_belum'];
}

// Hitung siswa lulus
$qLulus = mysqli_query($conn, "
    SELECT COUNT(*) AS total 
    FROM pendaftaran 
    WHERE tahun_id = $tahun_id 
      AND sekolah_diterima IS NOT NULL
");
$siswaLulus = mysqli_fetch_assoc($qLulus)['total'];

// ============================
// 3. ATURAN SELEKSI & ZONASI
// ============================
$qSeleksi = mysqli_query($conn, "SELECT * FROM aturan_seleksi ORDER BY id DESC LIMIT 1");
$aturanSeleksi = mysqli_fetch_assoc($qSeleksi) ?? [];

$qZonasi = mysqli_query($conn, "SELECT * FROM aturan_zonasi ORDER BY id DESC LIMIT 1");
$aturanZonasi = mysqli_fetch_assoc($qZonasi) ?? [];

// Hitung jalur aktif
$mulaiZonasi = $aturanZonasi['tanggal_mulai'] ?? null;
$mulaiSeleksi = $aturanSeleksi['tanggal_mulai'] ?? null;

$akhirZonasi = $mulaiSeleksi ? date("Y-m-d", strtotime("$mulaiSeleksi -1 day")) : null;
$akhirSeleksi = $aturanSeleksi['tanggal_daftar_ulang'] ?? ($aturanSeleksi['tanggal_selesai'] ?? null);

$jalurAktif = "Belum Dibuka";

if (!empty($mulaiZonasi) && !empty($akhirZonasi) &&
    $today >= $mulaiZonasi && $today <= $akhirZonasi) {

    $jalurAktif = "Zonasi";

} elseif (!empty($mulaiSeleksi) && !empty($akhirSeleksi) &&
    $today > $akhirZonasi && $today >= $mulaiSeleksi && $today <= $akhirSeleksi) {

    $jalurAktif = "Akademik";
}

// Tentukan timeline aktif
if ($jalurAktif == "Akademik") {
    $aturan = $aturanSeleksi;
} elseif ($jalurAktif == "Zonasi") {
    $aturan = $aturanZonasi;
    $aturan['tanggal_mos'] = $aturanSeleksi['tanggal_mos'] ?? "-";
    $aturan['tanggal_masuk'] = $aturanSeleksi['tanggal_masuk'] ?? "-";
} else {
    $aturan = [];
}

function f($tgl) {
    return $tgl ? date("d F Y", strtotime($tgl)) : "-";
}

$tanggal_mulai = $aturan['tanggal_mulai'] ?? "-";
$tanggal_selesai = $aturan['tanggal_selesai'] ?? "-";
$tanggal_pengumuman = $aturan['tanggal_pengumuman'] ?? "-";
$tanggal_daftar_ulang = $aturan['tanggal_daftar_ulang'] ?? "-";
$tanggal_mos = $aturan['tanggal_mos'] ?? "-";
$tanggal_masuk = $aturan['tanggal_masuk'] ?? "-";

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Dashboard Dinas</title>
<link rel="stylesheet" href="../css/dashboardDinas.css">
<link rel="stylesheet" href="../css/timeline.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<?php include("sidebarDinas.php"); ?>
<div class="main-content">
<?php include("headerDinas.php"); ?>

<main>
<!-- Statistik -->
<div class="cards">
  <div class="card">
    <i class="fa-solid fa-school"></i>
    <h3>Total Sekolah</h3>
    <p><?= $total_sekolah ?></p>
  </div>

  <div class="card">
    <i class="fa-solid fa-users"></i>
    <h3>Total Pendaftar</h3>
    <p><?= $total_pendaftar ?></p>
  </div>

  <div class="card">
    <i class="fa-solid fa-user-check"></i>
    <h3>Lulus</h3>
    <p><?= $total_lulus ?></p>
  </div>

  <div class="card">
    <i class="fa-solid fa-clipboard-check"></i>
    <h3>Sudah Daftar Ulang</h3>
    <p><?= $total_daftar_ulang ?></p>
  </div>
</div>

<!-- (Bagian tombol pengumuman DIHAPUS) -->

<!-- Timeline -->
<div class="timeline-horizontal">
  <h2><i class="fa-solid fa-calendar-alt"></i> Jadwal PPDB <?= $jalurAktif ?></h2>

<?php
$stages = [
  ["label"=>"Pendaftaran","start"=>$tanggal_mulai,"end"=>$tanggal_selesai,"icon"=>"fa-calendar-day","range"=>true],
  ["label"=>"Pengumuman","start"=>$tanggal_pengumuman,"end"=>$tanggal_pengumuman,"icon"=>"fa-bullhorn"],
  ["label"=>"Daftar Ulang","start"=>$tanggal_daftar_ulang,"end"=>$tanggal_daftar_ulang,"icon"=>"fa-clipboard-check"],
  ["label"=>"MOS","start"=>$tanggal_mos,"end"=>$tanggal_mos,"icon"=>"fa-people-group"],
  ["label"=>"Masuk Sekolah","start"=>$tanggal_masuk,"end"=>$tanggal_masuk,"icon"=>"fa-school"]
];
?>

<div class="timeline-track">
<?php foreach ($stages as $stage):
  $start = $stage['start'];
  $end   = $stage['end'];
  $range = $stage['range'] ?? false;

  if(!$start) $status="inactive";
  elseif($today < $start) $status="upcoming";
  elseif($today >= $start && $today <= $end) $status="active";
  else $status="done";
?>
  <div class="timeline-step <?= $status ?>">
    <div class="timeline-icon"><i class="fa-solid <?= $stage['icon'] ?>"></i></div>
    <p><?= $stage['label'] ?></p>
    <span>
      <?php if($range): ?>
        <?= f($start) ?> – <?= f($end) ?>
      <?php else: ?>
        <?= f($start) ?>
      <?php endif; ?>
    </span>
  </div>
<?php endforeach; ?>
</div>

</div>

</main>
</div>

</body>
</html>
