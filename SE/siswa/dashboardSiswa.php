<?php
session_start();
include("../koneksi_mysql.php"); // koneksi MySQL ($conn)

// ============================
// CEK LOGIN SISWA
// ============================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'siswa') {
    header("Location: loginSiswa.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// ============================
// AMBIL DATA SISWA
// ============================
$sql = "SELECT s.*, u.nama AS nama_user FROM siswa s 
        LEFT JOIN user u ON u.id = s.user_id
        WHERE s.user_id = $user_id";
$res = mysqli_query($conn, $sql);
if (!$res || mysqli_num_rows($res) == 0) {
    die("Data siswa tidak ditemukan.");
}
$siswa = mysqli_fetch_assoc($res);
$nama_lengkap = $siswa['nama_lengkap'] ?? $siswa['nama_user'] ?? "Siswa";

$siswa_id = $siswa['id']; // ambil id siswa dari tabel siswa

// ============================
// AMBIL DATA PENDAFTARAN
// ============================
$pendaftaran = [];
$sql = "SELECT p.*,
               v.status_verifikasi,
               v.tanggal_verifikasi,
               s1.nama_sekolah AS pilihan1, 
               s2.nama_sekolah AS pilihan2, 
               s3.nama_sekolah AS pilihan3,
               s_diterima.nama_sekolah AS sekolah_diterima
        FROM pendaftaran p
        LEFT JOIN verifikasi_pendaftaran v 
               ON v.pendaftaran_id = p.id
               AND v.sekolah_id = p.pilihan_ke1   
        LEFT JOIN sekolah s1 ON s1.id = p.pilihan_ke1
        LEFT JOIN sekolah s2 ON s2.id = p.pilihan_ke2
        LEFT JOIN sekolah s3 ON s3.id = p.pilihan_ke3
        LEFT JOIN sekolah s_diterima ON s_diterima.id = p.sekolah_diterima
        WHERE p.siswa_id = $siswa_id
        ORDER BY p.id DESC";
$res = mysqli_query($conn, $sql);
while($row = mysqli_fetch_assoc($res)){
    $pendaftaran[] = $row;
}

// ============================
// AMBIL ATURAN ZONASI & SELEKSI
// ============================
$aturanZonasi = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM aturan_zonasi ORDER BY id DESC LIMIT 1")) ?? [];
$aturanSeleksi = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM aturan_seleksi ORDER BY id DESC LIMIT 1")) ?? [];

// ============================
// AMBIL JALUR AKTIF (aturan seleksi & zonasi)
// ============================
$today = date('Y-m-d');
$jalurAktif = "Belum Dibuka";

$mulaiZonasi = $aturanZonasi['tanggal_mulai'] ?? null;
$mulaiSeleksi = $aturanSeleksi['tanggal_mulai'] ?? null;
$akhirSeleksi = $aturanSeleksi['tanggal_masuk'] ?? $aturanSeleksi['tanggal_selesai'] ?? null;

if ($mulaiZonasi && $mulaiSeleksi && $today >= $mulaiZonasi && $today < $mulaiSeleksi) {
    $jalurAktif = "Zonasi";
} elseif ($mulaiSeleksi && $akhirSeleksi && $today >= $mulaiSeleksi && $today <= $akhirSeleksi) {
    $jalurAktif = "Akademik";
} elseif ($today < $mulaiZonasi) {
    $jalurAktif = "Belum Dibuka";
} else {
    $jalurAktif = "Selesai"; // opsional, kalau mau menandai semua tahap selesai
}

// ============================
// TENTUKAN STATUS PENDAFTARAN
// ============================
$status_pendaftaran = "Belum Lengkap";
$nama_sekolah = "";

if(!empty($pendaftaran)){
    $p = $pendaftaran[0]; // ambil pendaftaran terakhir
    
    // ambil tanggal hari ini
    $today = date('Y-m-d');

    // ambil tanggal pengumuman sesuai jalur
    $tgl_pengumuman = $aturanZonasi['tanggal_pengumuman'] ?? null;
    if ($jalurAktif == "Akademik") {
        $tgl_pengumuman = $aturanSeleksi['tanggal_pengumuman'] ?? null;
    }

// cek sekolah diterima
if(!empty($p['sekolah_diterima'])){
    $status_pendaftaran = "Diterima";
    $nama_sekolah = $p['sekolah_diterima'];
} else {

    // ===============================
    // HILANGKAN INFO DITOLAK UNTUK MODE ZONASI SAAT SUDAH MULAI AKADEMIK
    // ===============================
    if (
        $p['jalur'] == "zonasi" &&
        empty($p['sekolah_diterima']) &&
        $jalurAktif == "Akademik" &&
        $today <= $tgl_pengumuman
    ) {
        $status_pendaftaran = "Menunggu Seleksi Akademik";
        goto skip_penolakan_zonasi;
    }

    // cek status verifikasi
    $status_verif = $p['status_verifikasi'] ?? "menunggu";
    if($status_verif == "menunggu"){
        $status_pendaftaran = "Pending";
    } elseif($status_verif == "diterima"){
        $status_pendaftaran = "Terverifikasi";
    } elseif($status_verif == "ditolak"){
        $status_pendaftaran = "Verifikasi Ditolak";
    }

    // jika sekolah_diterima null dan sudah lewat tanggal pengumuman, maka Tidak Diterima
    if(empty($p['sekolah_diterima']) && $tgl_pengumuman && $today > $tgl_pengumuman){
        $status_pendaftaran = "Tidak Diterima";
    }

}

skip_penolakan_zonasi:

}

// ============================
// FUNGSI FORMAT TANGGAL
// ============================
function formatTanggal($tgl){
    return $tgl ? date("d F Y", strtotime($tgl)) : "-";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Siswa - PPDB</title>
<link rel="stylesheet" href="../css/dashboardSiswa.css">
<link rel="stylesheet" href="../css/timeline.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<?php include('headerSiswa.php'); ?>
<?php include('sidebarSiswa.php'); ?>

<div class="main">
<!-- 🔹 Info Jalur Aktif -->
<div class="alert-section alert-info" style="background:#e6f2ff;border-left:4px solid #007bff;margin-bottom:15px;">
  <i class="fa-solid fa-info-circle alert-icon"></i>
  <div class="alert-content">
    <span class="alert-title">Jalur PPDB Saat Ini: <b><?= htmlspecialchars($jalurAktif) ?></b></span>
  </div>
</div>

<!-- 🔹 Timeline -->
<div class="timeline-horizontal">
<h2><i class="fa-solid fa-calendar-alt"></i> Tahapan PPDB</h2>
<?php
$timelineZonasi = [
  ["label"=>"Pendaftaran",
   "start"=>$aturanZonasi['tanggal_mulai'],
   "end"=>$aturanZonasi['tanggal_selesai'],
   "icon"=>"fa-calendar-day",
   "range"=>true
  ],
  ["label"=>"Pengumuman",
   "start"=>$aturanZonasi['tanggal_pengumuman'],
   "icon"=>"fa-bullhorn"
  ],
  ["label"=>"Daftar Ulang",
   "start"=>$aturanZonasi['tanggal_daftar_ulang'],
   "icon"=>"fa-clipboard-check"
  ],
  ["label"=>"MOS",
   "start"=>$aturanSeleksi['tanggal_mos'],
   "icon"=>"fa-people-group"
  ],
  ["label"=>"Masuk Sekolah",
   "start"=>$aturanSeleksi['tanggal_masuk'],
   "icon"=>"fa-school"
  ]
];

$timelineAkademik = [
  ["label"=>"Pendaftaran",
   "start"=>$aturanSeleksi['tanggal_mulai'],
   "end"=>$aturanSeleksi['tanggal_selesai'],
   "icon"=>"fa-calendar-day",
   "range"=>true
  ],
  ["label"=>"Pengumuman",
   "start"=>$aturanSeleksi['tanggal_pengumuman'],
   "icon"=>"fa-bullhorn"
  ],
  ["label"=>"Daftar Ulang",
   "start"=>$aturanSeleksi['tanggal_daftar_ulang'],
   "icon"=>"fa-clipboard-check"
  ],
  ["label"=>"MOS",
   "start"=>$aturanSeleksi['tanggal_mos'],
   "icon"=>"fa-people-group"
  ],
  ["label"=>"Masuk Sekolah",
   "start"=>$aturanSeleksi['tanggal_masuk'],
   "icon"=>"fa-school"
  ]
];

// =============================
// PILIH TIMELINE BERDASARKAN JALUR AKTIF
// =============================
if ($jalurAktif == "Zonasi") {
    $stages = $timelineZonasi;
} elseif ($jalurAktif == "Akademik") {
    $stages = $timelineAkademik;
} else {
    // jika belum dibuka, default pakai zonasi
    $stages = $timelineZonasi;
}
?>
<div class="timeline-track">
<?php foreach ($stages as $stage): 
    $start = $stage['start'];
    $end = $stage['end'] ?? $start;
    $isRange = $stage['range'] ?? false;

    if(!$start){ $status="inactive"; }
    elseif($today < $start){ $status="upcoming"; }
    elseif($today >= $start && $today <= $end){ $status="active"; }
    else{ $status="done"; }
?>
<div class="timeline-step <?= $status; ?>">
  <div class="timeline-icon"><i class="fa-solid <?= $stage['icon']; ?>"></i></div>
  <p class="timeline-label"><?= $stage['label']; ?></p>
  <span class="timeline-date">
    <?php if($isRange): ?>
      <?= formatTanggal($start); ?> – <?= formatTanggal($end); ?>
    <?php else: ?>
      <?= formatTanggal($start); ?>
    <?php endif; ?>
  </span>
</div>
<?php endforeach; ?>

<div class="timeline-progress"></div>
</div>
</div>

<!-- 🔹 Status Pendaftaran -->
<?php if($status_pendaftaran=="Pending"): ?>
<div class="alert-section alert-success">
  <i class="fa-solid fa-circle-check alert-icon"></i>
  <div class="alert-content">
    <span class="alert-title">Data pendaftaran sudah diisi.</span>
    <span class="alert-text">Silakan tunggu proses seleksi.</span>
  </div>
</div>

<?php elseif($status_pendaftaran=="Terverifikasi"): ?>
<div class="alert-section alert-info">
  <i class="fa-solid fa-circle-check alert-icon"></i>
  <div class="alert-content">
    <span class="alert-title">Data pendaftaran sudah terverifikasi!</span>
    <span class="alert-text">Silakan tunggu hasil seleksi untuk melihat apakah diterima atau tidak.</span>
  </div>
</div>

<?php elseif($status_pendaftaran=="Verifikasi Ditolak"): ?>
<div class="alert-section alert-warning">
  <i class="fa-solid fa-circle-xmark alert-icon"></i>
  <div class="alert-content">
    <span class="alert-title">Verifikasi pendaftaran ditolak!</span>
    <!-- <span class="alert-text">Periksa kembali data dan dokumen Anda.</span> -->
  </div>
</div>

<?php elseif($status_pendaftaran=="Diterima"): ?>
<div class="alert-section alert-success">
  <i class="fa-solid fa-circle-check alert-icon"></i>
  <div class="alert-content">
    <span class="alert-title">Selamat, Anda diterima!</span>
    <span class="alert-text">
    Di sekolah: <?= htmlspecialchars($nama_sekolah);?>
    </span>
  </div>
</div>

<?php elseif($status_pendaftaran=="Tidak Diterima"): ?>
<div class="alert-section alert-danger">
  <i class="fa-solid fa-circle-xmark alert-icon"></i>
  <div class="alert-content">
    <span class="alert-title">Maaf, Anda tidak diterima.</span>
    <span class="alert-text">Semoga bisa mencoba pada jalur berikutnya atau tahun depan.</span>
  </div>
</div>
<?php endif; ?>

<!-- 🔹 Daftar Sekolah Pilihan -->
<div class="school-section">
  <h3><i class="fa-solid fa-school"></i> Pilihan Sekolah</h3>
  <table class="school-table" style="width:100%; border-collapse: collapse;">
    <thead>
      <tr style="background:#f0f0f0;">
        <th class="no" style="width:50px; text-align:center;">No</th>
        <th>Nama Sekolah</th>
        <th>NPSN</th>
        <th>Alamat</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $no = 1;
      foreach($pendaftaran as $p):
          // ambil data sekolah dari database berdasarkan id pilihan
          $pilihan_ids = [$p['pilihan_ke1'], $p['pilihan_ke2'], $p['pilihan_ke3']];
          foreach($pilihan_ids as $pid):
              if($pid):
                  $sekolah = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nama_sekolah, npsn, alamat FROM sekolah WHERE id=$pid"));
      ?>
        <tr style="border-bottom:1px solid #ccc;">
          <td class="no" style="text-align:center;"><?= $no++; ?></td>
          <td><?= htmlspecialchars($sekolah['nama_sekolah']); ?></td>
          <td><?= htmlspecialchars($sekolah['npsn']); ?></td>
          <td><?= htmlspecialchars($sekolah['alamat']); ?></td>
        </tr>
      <?php
              endif;
          endforeach;
      endforeach;
      ?>
      <?php if(empty($pendaftaran)): ?>
      <tr>
        <td colspan="4" style="text-align:center; color:gray;">Belum memilih sekolah</td>
      </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
</div>
</body>
</html>
