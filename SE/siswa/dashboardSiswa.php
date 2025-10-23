<?php
session_start();
include("../koneksi.php");

// Cek apakah sudah login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'siswa') {
    header("Location: loginSiswa.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Ambil data siswa
$sql = "SELECT s.id AS siswa_id, s.nama_lengkap 
        FROM siswa s
        JOIN user u ON u.id = s.user_id
        WHERE u.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $nama_lengkap = $row['nama_lengkap'];
    $siswa_id = $row['siswa_id'];
} else {
    $nama_lengkap = "Siswa";
    $siswa_id = 0;
}

// Ambil aturan seleksi terbaru
$aturan = $conn->query("SELECT * FROM aturan_seleksi ORDER BY id DESC LIMIT 1")->fetch_assoc();

// Default status
$status_pendaftaran = "Belum Lengkap";

if ($siswa_id && $aturan) {
    $aturan_id = $aturan['id'];

    // Ambil mapel wajib dari tabel aturan_mapel
    $mapel_wajib = [];
    $resMapel = $conn->query("SELECT nama_mapel FROM aturan_mapel WHERE aktif = 1");
    while($m = $resMapel->fetch_assoc()){
        $mapel_wajib[] = $m['nama_mapel'];
    }

    // Ambil dokumen wajib
    $dokumen_wajib = [];
    $resDok = $conn->query("SELECT nama_dokumen FROM aturan_dokumen WHERE wajib = 1");
    while($d = $resDok->fetch_assoc()){
        $dokumen_wajib[] = $d['nama_dokumen'];
    }

    // Cek kelengkapan nilai & dokumen
    $mapel_terisi = $dokumen_terisi = 0;
    if(count($mapel_wajib) > 0){
        $mapel_terisi = $conn->query("
            SELECT COUNT(*) as total 
            FROM nilai_akademik 
            WHERE siswa_id = $siswa_id 
              AND mapel IN ('".implode("','", $mapel_wajib)."')
        ")->fetch_assoc()['total'];
    }

    if(count($dokumen_wajib) > 0){
        $dokumen_terisi = $conn->query("
            SELECT COUNT(*) as total 
            FROM dokumen_siswa 
            WHERE siswa_id = $siswa_id 
              AND status='terverifikasi' 
              AND nama_dokumen IN ('".implode("','", $dokumen_wajib)."')
        ")->fetch_assoc()['total'];
    }

    if($mapel_terisi == count($mapel_wajib) && $dokumen_terisi == count($dokumen_wajib)){
        $status_pendaftaran = "Lengkap";
    }
}

// Ambil tanggal kegiatan dari aturan
$sqlAturan = "SELECT tanggal_mulai, tanggal_selesai, tanggal_pengumuman, 
                     tanggal_daftar_ulang, tanggal_seleksi, tanggal_mos, tanggal_masuk
              FROM aturan_seleksi 
              ORDER BY id DESC LIMIT 1";
$resultAturan = $conn->query($sqlAturan);

if ($rowAturan = $resultAturan->fetch_assoc()) {
    $tanggal_mulai = $rowAturan['tanggal_mulai'];
    $tanggal_selesai = $rowAturan['tanggal_selesai'];
    $tanggal_pengumuman = $rowAturan['tanggal_pengumuman'];
    $tanggal_daftar_ulang = $rowAturan['tanggal_daftar_ulang'];
    $tanggal_seleksi = $rowAturan['tanggal_seleksi'];
    $tanggal_mos = $rowAturan['tanggal_mos'];
    $tanggal_masuk = $rowAturan['tanggal_masuk'];
} else {
    $tanggal_mulai = $tanggal_selesai = $tanggal_pengumuman = $tanggal_daftar_ulang = $tanggal_seleksi = $tanggal_mos = $tanggal_masuk = null;
}

// Jika pendaftaran belum mulai, ubah status jadi tanggal awal
$tanggal_sekarang = date("Y-m-d");
if ($tanggal_mulai && $tanggal_sekarang < $tanggal_mulai) {
    $status_pendaftaran = "Belum Dibuka (" . date("d F Y", strtotime($tanggal_mulai)) . ")";
}

// Format tanggal agar mudah dibaca
function formatTanggal($tgl) {
    return $tgl ? date("d F Y", strtotime($tgl)) : "-";
}

// Default status
$status_pendaftaran = "Belum Lengkap";
$pengumuman_text = "";
$nama_sekolah = "";

$sqlPendaftaran = "SELECT p.status, p.pengumuman_dibuat, p.tanggal_pengumuman, s.nama_sekolah
                   FROM pendaftaran p
                   JOIN sekolah s ON p.sekolah_id = s.id
                   WHERE p.siswa_id = ?
                   ORDER BY p.id DESC LIMIT 1";
$stmt = $conn->prepare($sqlPendaftaran);
$stmt->bind_param("i", $siswa_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    if ($row['status'] == 'pending' && $row['pengumuman_dibuat'] == 0) {
        $status_pendaftaran = "Pending";
    } elseif ($row['pengumuman_dibuat'] == 1) {
        if ($row['status'] == 'diterima') {
            $status_pendaftaran = "Diterima";
            $nama_sekolah = $row['nama_sekolah'];
        } elseif ($row['status'] == 'ditolak') {
            $status_pendaftaran = "Ditolak";
        }
        $pengumuman_text = $row['tanggal_pengumuman'] ? 
            "Pengumuman: " . date("d F Y", strtotime($row['tanggal_pengumuman'])) : "";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Siswa - PPDB</title>
  <link rel="stylesheet" href="../css/dashboardSiswa.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
  <!-- Header -->
  <header>
    <div class="logo"><i class="fa-solid fa-graduation-cap"></i> PPDB</div>
    <div class="user-info">
      <span><i class="fa-solid fa-user-circle"></i> Halo, <b><?php echo htmlspecialchars($nama_lengkap); ?></b></span>
    </div>
  </header>

  <!-- Sidebar -->
  <div class="sidebar">
    <ul>
      <li><a href="dashboardSiswa.php"><i class="fa-solid fa-gauge"></i> Dashboard</a></li>
      <li><a href="profilSiswa.php"><i class="fa-solid fa-user"></i> Profil</a></li>
      <li><a href="pendaftaranSiswa.php"><i class="fa-solid fa-file-pen"></i> Pendaftaran</a></li>
      <li><a href="prediksiMapel.php"><i class="fa-solid fa-book"></i> Prediksi Mapel</a></li>
      <li><a href="daftarUlang.php"><i class="fa-solid fa-clipboard-check"></i> Daftar Ulang</a></li>
    </ul>
  </div>

<div class="main">
  <div class="cards">
    <div class="card">
      <i class="fa-solid fa-id-card icon"></i>
      <h3>Status Pendaftaran</h3>
      <p><?php echo $status_pendaftaran; ?></p>
    </div>

    <div class="card">
      <i class="fa-solid fa-calendar-day icon"></i>
      <h3>Batas Waktu</h3>
      <p><?php echo formatTanggal($tanggal_selesai); ?></p>
    </div>

    <div class="card">
      <i class="fa-solid fa-user-check icon"></i>
      <h3>Seleksi</h3>
      <p><?php echo formatTanggal($tanggal_seleksi); ?></p>
    </div>

    <div class="card">
      <i class="fa-solid fa-bullhorn icon"></i>
      <h3>Pengumuman</h3>
      <p><?php echo formatTanggal($tanggal_pengumuman); ?></p>
    </div>

    <div class="card">
      <i class="fa-solid fa-clipboard-check icon"></i>
      <h3>Daftar Ulang</h3>
      <p><?php echo formatTanggal($tanggal_daftar_ulang); ?></p>
    </div>

    <div class="card">
      <i class="fa-solid fa-people-group icon"></i>
      <h3>MOS</h3>
      <p><?php echo formatTanggal($tanggal_mos); ?></p>
    </div>

    <div class="card">
      <i class="fa-solid fa-school icon"></i>
      <h3>Masuk Sekolah</h3>
      <p><?php echo formatTanggal($tanggal_masuk); ?></p>
    </div>
  </div>

<?php if ($status_pendaftaran == "Belum Lengkap"): ?>
    <div class="alert-section alert-warning">
        <i class="fa-solid fa-triangle-exclamation alert-icon"></i>
        <div class="alert-content">
            <span class="alert-title">Data pendaftaran belum diisi!</span>
            <span class="alert-text">Harap isi formulir pendaftaran agar dapat mengikuti PPDB.</span>
        </div>
    </div>
    
    <?php elseif ($status_pendaftaran == "Pending"): ?>
        <div class="alert-section alert-success">
            <i class="fa-solid fa-circle-check alert-icon"></i>
            <div class="alert-content">
                <span class="alert-title">Data pendaftaran sudah diisi.</span>
                <span class="alert-text">Silakan tunggu proses seleksi.</span>
            </div>
        </div>
    <?php elseif ($status_pendaftaran == "Diterima"): ?>
        <div class="alert-section alert-success">
            <i class="fa-solid fa-circle-check alert-icon"></i>
            <div class="alert-content">
                <span class="alert-title">Selamat, Anda diterima!</span>
                <span class="alert-text">
                    <?php 
                    echo "Di sekolah: " . htmlspecialchars($nama_sekolah); 
                    if($pengumuman_text) echo "<br>".$pengumuman_text; 
                    ?>
                </span>
            </div>
        </div>
    <?php elseif ($status_pendaftaran == "Ditolak"): ?>
        <div class="alert-section alert-warning">
            <i class="fa-solid fa-circle-xmark alert-icon"></i>
            <div class="alert-content">
                <span class="alert-title">Maaf, pendaftaran ditolak.</span>
                <span class="alert-text"><?php echo $pengumuman_text; ?></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Pilihan Sekolah -->
    <div class="school-section">
      <h3><i class="fa-solid fa-school"></i> Pilihan Sekolah</h3>
      <table class="school-table">
        <thead>
          <tr>
            <th class="no">No</th>
            <th>Nama Sekolah</th>
            <th>NPSN</th>
            <th>Alamat</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $sqlSekolah = "SELECT s.id, s.nama_sekolah, s.npsn, s.alamat 
                         FROM pendaftaran p 
                         JOIN sekolah s ON p.sekolah_id = s.id 
                         WHERE p.siswa_id = $siswa_id";
          $resSekolah = $conn->query($sqlSekolah);
          $no = 1;
          while($sch = $resSekolah->fetch_assoc()):
          ?>
          <tr>
            <td class="no"><?php echo $no++; ?></td>
            <td><?php echo $sch['nama_sekolah']; ?></td>
            <td><?php echo $sch['npsn']; ?></td>
            <td><?php echo $sch['alamat']; ?></td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
