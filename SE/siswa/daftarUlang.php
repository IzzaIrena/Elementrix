<?php
session_start();
include("../koneksi_mysql.php"); 
require_once '../phpqrcode-master/qrlib.php';

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
$qSiswa = mysqli_query($conn, 
    "SELECT * FROM siswa WHERE user_id='".mysqli_real_escape_string($conn, $user_id)."'"
);
if (mysqli_num_rows($qSiswa) == 0) die("Data siswa tidak ditemukan.");

$siswa = mysqli_fetch_assoc($qSiswa);

$siswa_id = $siswa['id'];                // ← menggunakan ID siswa
$nama_lengkap = $siswa['nama_lengkap'];

// ============================
// AMBIL TAHUN AKADEMIK AKTIF
// ============================
$qTahunAktif = mysqli_query($conn,
    "SELECT id, nama_tahun 
     FROM tahun_akademik 
     WHERE status = 'aktif'
     LIMIT 1"
);

if (mysqli_num_rows($qTahunAktif) == 0) {
    die("Tahun akademik aktif belum ditentukan oleh admin.");
}

$tahunAktif = mysqli_fetch_assoc($qTahunAktif);
$tahun_aktif_id = $tahunAktif['id'];

// ============================
// CARI PENDAFTARAN YANG LOLOS
// ============================
$qPendaftaran = mysqli_query($conn,
    "SELECT * FROM pendaftaran 
     WHERE siswa_id='".mysqli_real_escape_string($conn, $siswa_id)."'
       AND (
             LOWER(status_seleksi1)='lolos' OR
             LOWER(status_seleksi2)='lolos' OR
             LOWER(status_seleksi3)='lolos'
           )"
);

if (mysqli_num_rows($qPendaftaran) == 0) {
    echo "<script>
        alert('Anda belum dinyatakan LOLOS di sekolah manapun.');
        window.location.href='dashboardSiswa.php';
    </script>";
    exit;
}

$pendaftaran = mysqli_fetch_assoc($qPendaftaran);
$sekolah_id = $pendaftaran['sekolah_diterima']; // ← sekolah tujuan
$jalurAktif = $pendaftaran['jalur'];            // ← jalur penting untuk filter jadwal

// ============================
// DATA SEKOLAH
// ============================
$qSekolah = mysqli_query($conn, "SELECT * FROM sekolah WHERE id='".mysqli_real_escape_string($conn, $sekolah_id)."'");
$sekolahData = mysqli_fetch_assoc($qSekolah);
$nama_sekolah = $sekolahData['nama_sekolah'] ?? "Sekolah Anda";

// ============================
// AMBIL JADWAL DAFTAR ULANG SESUAI JALUR
// ============================
$qJadwal = mysqli_query($conn,
    "SELECT * FROM jadwal_daftar_ulang 
     WHERE sekolah_id = '".mysqli_real_escape_string($conn, $sekolah_id)."'
       AND jalur       = '".mysqli_real_escape_string($conn, $jalurAktif)."'
       AND tahun_id    = '".mysqli_real_escape_string($conn, $tahun_aktif_id)."'
     ORDER BY tanggal ASC"
);

if (mysqli_num_rows($qJadwal) == 0) {
    echo "<script>
        alert('Belum ada jadwal daftar ulang untuk jalur $jalurAktif.');
        window.location.href='dashboardSiswa.php';
    </script>";
    exit;
}

$jadwalList = [];
while ($row = mysqli_fetch_assoc($qJadwal)) $jadwalList[] = $row;

// ============================
// FOLDER QR
// ============================
$qrFolder = "../qr_booking/";
if (!is_dir($qrFolder)) mkdir($qrFolder, 0777, true);

// ============================
// CEK BOOKING SEBELUMNYA (untuk tampilan jika sudah booking)
// ============================
$qExist = mysqli_query($conn,
    "SELECT *
     FROM booking_daftar_ulang
     WHERE siswa_id='".mysqli_real_escape_string($conn, $siswa_id)."'
       AND sekolah_id='".mysqli_real_escape_string($conn, $sekolah_id)."'
     ORDER BY id DESC
     LIMIT 1"
);

$existingBooking = mysqli_num_rows($qExist) > 0 ? mysqli_fetch_assoc($qExist) : null;

// ============================
// PROSES BOOKING BARU
// ============================
if (isset($_POST['tanggal']) && isset($_POST['jam_booking']) && !$existingBooking) {

    $tanggal = mysqli_real_escape_string($conn, $_POST['tanggal']);
    $jam_booking = mysqli_real_escape_string($conn, $_POST['jam_booking']);

    // CARI JADWAL SAMA DENGAN TANGGAL
    $jadwalDipilih = null;
    foreach ($jadwalList as $j) {
        if ($j['tanggal'] == $tanggal) {
            $jadwalDipilih = $j;
            break;
        }
    }

    if ($jadwalDipilih) {

        $kuota = (int)$jadwalDipilih['kuota_per_jam'];

        // Hitung kuota terpakai (MySQL)
        $qCount = mysqli_query($conn,
            "SELECT COUNT(*) AS total 
             FROM booking_daftar_ulang
             WHERE sekolah_id='".mysqli_real_escape_string($conn, $sekolah_id)."'
               AND tanggal_booking='".mysqli_real_escape_string($conn, $tanggal)."'
               AND jam_booking='".mysqli_real_escape_string($conn, $jam_booking)."'"
        );

        $count = mysqli_fetch_assoc($qCount)['total'];

        if ($count >= $kuota) {
            $error = "Kuota untuk jam $jam_booking pada tanggal $tanggal sudah penuh.";
        } else {

            // Buat QR
            $qrText =
                "ID:$siswa_id;". 
                "Nama:$nama_lengkap;".
                "Sekolah:$sekolah_id;".
                "Tanggal:$tanggal;".
                "Jam:$jam_booking;";

            $qrPath = $qrFolder . "daftarulang_" . $siswa_id . ".png";
            QRcode::png($qrText, $qrPath, QR_ECLEVEL_L, 6, 2);

            // Simpan booking
            mysqli_query($conn,
                "INSERT INTO booking_daftar_ulang
                (siswa_id, sekolah_id, nama, tanggal_booking, jam_booking, status, qr_code)
                VALUES
                ('".mysqli_real_escape_string($conn, $siswa_id)."', 
                 '".mysqli_real_escape_string($conn, $sekolah_id)."', 
                 '".mysqli_real_escape_string($conn, $nama_lengkap)."', 
                 '".mysqli_real_escape_string($conn, $tanggal)."', 
                 '".mysqli_real_escape_string($conn, $jam_booking)."', 
                 'booking', 
                 '".mysqli_real_escape_string($conn, $qrPath)."')"
            );

            $success = true;

            $existingBooking = [
                'tanggal_booking' => $tanggal,
                'jam_booking' => $jam_booking,
                'qr_code' => $qrPath
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Daftar Ulang - PPDB</title>
<link rel="stylesheet" href="../css/dashboardSiswa.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* (css sama seperti sebelumnya) */
.content { margin-left: 250px; padding: 30px; }
.main { background: #fff; padding: 25px 40px; border-radius: 15px; box-shadow: 0 0 15px rgba(0,0,0,0.1); max-width: 600px; margin: 40px auto; text-align: center; animation: fadeIn 0.6s ease; }
@keyframes fadeIn { from {opacity: 0; transform: translateY(10px);} to {opacity: 1; transform: translateY(0);} }
h2 { color:#007bff; margin-bottom:15px; }
.alert { display:flex; align-items:center; justify-content:center; gap:15px; padding:15px; border-radius:10px; margin-bottom:15px; }
.alert i {font-size:25px;}
.alert.info {background:#e8f4fd; color:#007bff;}
.alert.success {background:#e9fbee; color:#28a745;}
.alert.error {background:#fdeaea; color:#dc3545;}
.btn { display:inline-block; margin-top:10px; padding:8px 16px; background:#007bff; color:white; text-decoration:none; border-radius:8px; }
form select, form button { width:100%; padding:10px; margin:8px 0; border-radius:8px; border:1px solid #ccc; }
form button { background:#007bff; color:#fff; border:none; cursor:pointer; transition:.3s; }
form button:hover {background:#0056b3;}
.card { background:#f5f9ff; padding:25px; border-radius:10px; margin:40px auto; border:1px solid #cde4ff; max-width:400px; text-align:center; display:flex; flex-direction:column; align-items:center; justify-content:center; box-shadow:0 4px 12px rgba(0,0,0,0.1); animation: fadeIn 0.5s ease; }
.qr-container { display:flex; flex-direction:column; align-items:center; justify-content:center; }
.qr-container img { width:200px; margin:15px 0; border-radius:8px; box-shadow: 0 2px 8px rgba(0,0,0,0.15); }
.download-btn { display:inline-block; margin-top:10px; padding:8px 12px; background:#28a745; color:#fff; border-radius:6px; text-decoration:none; }
.download-btn:hover {background:#218838;}
.option-info { color:#555; font-size:14px; }
</style>
</head>
<body>
<?php include('headerSiswa.php'); ?>
<?php include('sidebarSiswa.php'); ?>

<div class="content">
<div class="main">
<h2><i class="fa-solid fa-calendar-check"></i> Jadwal Daftar Ulang</h2>
<p><b>Nama:</b> <?= htmlspecialchars($nama_lengkap) ?></p>
<p><b>Sekolah:</b> <?= htmlspecialchars($nama_sekolah) ?></p>

<?php if (isset($error)): ?>
<div class="alert error"><i class="fa-solid fa-circle-xmark"></i> <?= $error ?></div>
<?php endif; ?>

<?php if (isset($success) && $existingBooking): ?>
<div class="alert success"><i class="fa-solid fa-circle-check"></i> Booking berhasil! Berikut QR Code Anda.</div>
<div class="qr-container">
  <img src="<?= htmlspecialchars($existingBooking['qr_code']) ?>" alt="QR Code">
  <p><b><?= htmlspecialchars($existingBooking['tanggal_booking']) ?></b> | Jam <b><?= htmlspecialchars($existingBooking['jam_booking']) ?></b></p>
  <a href="<?= htmlspecialchars($existingBooking['qr_code']) ?>" download="QR_DaftarUlang_<?= $siswa_id ?>.png" class="download-btn">
    <i class="fa-solid fa-download"></i> Unduh QR
  </a>
</div>
<?php elseif ($existingBooking): ?>
<div class="card">
  <h3>Status Daftar Ulang</h3>

  <?php if ($existingBooking['status'] === 'ditunda'): ?>
    <div class="alert info">
      <i class="fa-solid fa-clock"></i>
      <div><b>DITUNDA</b></div>
    </div>
    <div><h3>Silakan hadir kembali membawa QR terbaru berikut.</h3></div>
  <?php endif; ?>

  <?php if ($existingBooking['status'] !== 'ditunda'): ?>
    <p>
      <b><?= htmlspecialchars($existingBooking['tanggal_booking']) ?></b> |
      Jam <b><?= htmlspecialchars($existingBooking['jam_booking']) ?></b>
    </p>
  <?php endif; ?>

  <div class="qr-container">
    <img src="<?= htmlspecialchars($existingBooking['qr_code']) ?>" alt="QR Code">
    <a href="<?= htmlspecialchars($existingBooking['qr_code']) ?>"
       download="QR_DaftarUlang_<?= $siswa_id ?>.png"
       class="download-btn">
      <i class="fa-solid fa-download"></i> Unduh QR
    </a>
  </div>
</div>
<?php else: ?>
<form method="POST">
  <label><b>Pilih Hari:</b></label>
  <select name="tanggal" required onchange="this.form.submit()">
      <option value="">Pilih Hari</option>
      <?php foreach ($jadwalList as $jadwal): ?>
          <option value="<?= htmlspecialchars($jadwal['tanggal']) ?>" <?= (isset($_POST['tanggal']) && $_POST['tanggal'] == $jadwal['tanggal']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($jadwal['tanggal']) ?> (<?= htmlspecialchars($jadwal['jam_mulai']) ?> - <?= htmlspecialchars($jadwal['jam_selesai']) ?>)
          </option>
      <?php endforeach; ?>
  </select>
</form>

<?php 
if (isset($_POST['tanggal'])):
    // sanitize selectedTanggal before DB use
    $selectedTanggal = mysqli_real_escape_string($conn, $_POST['tanggal']);
    $jadwalDipilih = null;
    foreach ($jadwalList as $jadwal) {
        if ($jadwal['tanggal'] == $selectedTanggal) $jadwalDipilih = $jadwal;
    }

    if ($jadwalDipilih):
        $mulai = strtotime($jadwalDipilih['jam_mulai']);
        $selesai = strtotime($jadwalDipilih['jam_selesai']);
        $jamTersedia = [];
        for ($time = $mulai; $time < $selesai; $time += 3600) {
            $jamTersedia[] = date("H:i", $time);
        }

        $kuota_per_jam = (int)$jadwalDipilih['kuota_per_jam'];

        // Ambil existing bookings untuk tanggal & sekolah ini dari MySQL
        $existingBookings = [];
        $qExistingForDate = mysqli_query($conn,
            "SELECT * FROM booking_daftar_ulang
             WHERE sekolah_id='".mysqli_real_escape_string($conn, $sekolah_id)."'
               AND tanggal_booking='".mysqli_real_escape_string($conn, $selectedTanggal)."'"
        );
        while ($r = mysqli_fetch_assoc($qExistingForDate)) {
            $existingBookings[] = $r;
        }
?>
<form method="POST">
  <input type="hidden" name="tanggal" value="<?= htmlspecialchars($selectedTanggal) ?>">
  <label><b>Pilih Jam:</b></label>
  <select name="jam_booking" required>
      <option value="">Pilih Jam</option>
      <?php foreach ($jamTersedia as $jam): 
          // hitung berapa yang sudah terisi untuk $jam
          $count = 0;
          foreach ($existingBookings as $b) {
              if (($b['jam_booking'] ?? '') == $jam) $count++;
          }
          $sisa = max(0, $kuota_per_jam - $count);
      ?>
          <option value="<?= htmlspecialchars($jam) ?>" <?= $sisa == 0 ? 'disabled' : '' ?>>
              <?= htmlspecialchars($jam) ?> (<?= $count ?>/<?= $kuota_per_jam ?> terisi<?= $sisa == 0 ? ' - Penuh' : '' ?>)
          </option>
      <?php endforeach; ?>
  </select>
  <button type="submit">Booking Jadwal</button>
</form>
<?php endif; endif; ?>
<?php endif; ?>
</div>
</div>
</body>
</html>
