<?php
session_start();
include("../koneksi.php");
require_once '../phpqrcode-master/qrlib.php';

// Cek login siswa
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'siswa') {
    header("Location: loginSiswa.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Ambil data siswa
$stmtSiswa = $conn->prepare("SELECT id, nama_lengkap FROM siswa WHERE user_id=?");
$stmtSiswa->bind_param("i", $user_id);
$stmtSiswa->execute();
$resSiswa = $stmtSiswa->get_result();
if ($row = $resSiswa->fetch_assoc()) {
    $siswa_id = $row['id'];
    $nama_lengkap = $row['nama_lengkap'];
} else {
    die("Siswa tidak ditemukan.");
}
$stmtSiswa->close();

// Ambil semua jadwal
$sqlJadwal = "SELECT j.*, s.nama_sekolah 
              FROM jadwal_daftar_ulang j
              JOIN sekolah s ON s.id = j.sekolah_id
              ORDER BY j.tanggal, j.jam_mulai";
$resultJadwal = $conn->query($sqlJadwal);

// Folder QR
$qrFolder = "../qr_booking/";
if (!is_dir($qrFolder)) mkdir($qrFolder, 0777, true);

// Handle booking
if (isset($_POST['booking'])) {
    $jadwal_id = $_POST['jadwal_id'];
    $jam_slot = $_POST['jam_slot'];
    $tanggal_booking = $_POST['tanggal_booking'];

    $cek = $conn->prepare("SELECT * FROM booking_daftar_ulang WHERE siswa_id=? AND jadwal_id=? AND jam_slot=?");
    $cek->bind_param("iis", $siswa_id, $jadwal_id, $jam_slot);
    $cek->execute();
    $resCek = $cek->get_result();
    $cek->close();

    if ($resCek->num_rows == 0) {
        $stmt = $conn->prepare("INSERT INTO booking_daftar_ulang (siswa_id, jadwal_id, tanggal_booking, jam_slot, status) VALUES (?,?,?,?,?)");
        $status = "pending";
        $stmt->bind_param("iisss", $siswa_id, $jadwal_id, $tanggal_booking, $jam_slot, $status);
        $stmt->execute();
        $booking_id = $stmt->insert_id;
        $stmt->close();

        // Generate QR
        $qrText = "BookingID:".$booking_id.";SiswaID:".$siswa_id.";JadwalID:".$jadwal_id.";Tanggal:".$tanggal_booking.";JamSlot:".$jam_slot;
        $qrPath = $qrFolder . "booking_{$booking_id}.png";
        QRcode::png($qrText, $qrPath, QR_ECLEVEL_L, 5, 2);

        $stmt2 = $conn->prepare("UPDATE booking_daftar_ulang SET qr_code=? WHERE id=?");
        $stmt2->bind_param("si", $qrPath, $booking_id);
        $stmt2->execute();
        $stmt2->close();

        $success = "Booking berhasil, QR Code telah dibuat!";
        $qr_generated = $qrPath;
    } else {
        $error = "Anda sudah booking slot ini!";
    }
}

// Ambil booking siswa
$sqlBookingSiswa = "SELECT b.id AS booking_id, s.nama_sekolah, b.tanggal_booking, b.jam_slot, b.status, b.qr_code 
                    FROM booking_daftar_ulang b
                    JOIN jadwal_daftar_ulang j ON j.id = b.jadwal_id
                    JOIN sekolah s ON s.id = j.sekolah_id
                    WHERE b.siswa_id=? 
                    ORDER BY b.tanggal_booking DESC";
$stmtBooking = $conn->prepare($sqlBookingSiswa);
$stmtBooking->bind_param("i", $siswa_id);
$stmtBooking->execute();
$resultBooking = $stmtBooking->get_result();

// Hitung jumlah booking siswa
$totalBooking = $resultBooking->num_rows;

// Ambil data terakhir booking (untuk card info)
$lastBooking = $resultBooking->fetch_assoc();
$stmtBooking->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Daftar Ulang - Dashboard</title>
<link rel="stylesheet" href="../css/dashboardSiswa.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* Card */
.card-container {display:flex; gap:15px; flex-wrap:wrap; margin-top:20px;}
.card {background:#fff; padding:15px; border-radius:8px; flex:1; min-width:150px; text-align:center; box-shadow:0 2px 6px rgba(0,0,0,0.1);}
.card i {font-size:24px; margin-bottom:10px; color:#007bff;}

/* Alerts */
.alert-section {display:flex; align-items:center; background:#fff3cd; padding:10px 15px; border-radius:5px; margin:15px 0; color:#856404;}
.alert-section.alert-success {background:#d4edda; color:#155724;}
.alert-icon {margin-right:10px; font-size:20px;}

/* QR */
.qr-container {margin-top:15px; text-align:center;}
.qr-container img {width:180px; height:180px; margin-top:10px;}
.download-btn {display:inline-block; margin-top:8px; padding:6px 12px; background:#28a745; color:white; border-radius:5px; text-decoration:none;}
.download-btn:hover {background:#218838;}

/* Table mirip kode awal */
table {width:100%; border-collapse:collapse; margin-top:20px; background:#fff; box-shadow:0 2px 6px rgba(0,0,0,0.1);}
th, td {border:1px solid #eee; padding:10px; text-align:center;}
th {background:#f8f8f8; font-weight:600;}
button {padding:5px 10px; cursor:pointer; border:none; background:#007bff; color:#fff; border-radius:5px;}
button:hover {background:#0056b3;}
span {color:#888;}
</style>
</head>
<body>

<header>
    <div class="logo"><i class="fa-solid fa-graduation-cap"></i> PPDB</div>
    <div class="user-info"><i class="fa-solid fa-user-circle"></i> Halo, <b><?= htmlspecialchars($nama_lengkap) ?></b></div>
</header>

<div class="sidebar">
    <ul>
      <li><a href="dashboardSiswa.php"><i class="fa-solid fa-gauge"></i> Dashboard</a></li>
      <li><a href="profilSiswa.php"><i class="fa-solid fa-user"></i> Profil</a></li>
      <li><a href="pendaftaranSiswa.php"><i class="fa-solid fa-file-pen"></i> Pendaftaran</a></li>
      <li><a href="prediksiMapel.php"><i class="fa-solid fa-book"></i> Prediksi Mapel</a></li>
      <li><a href="daftarUlang.php" class="active"><i class="fa-solid fa-clipboard-check"></i> Daftar Ulang</a></li>
    </ul>
</div>

<div class="main">
<h2>Daftar Ulang Siswa</h2>

<!-- Card Info Booking Siswa -->
<div class="card-container">
    <div class="card">
        <i class="fa-solid fa-ticket"></i>
        <h3>Total Booking</h3>
        <p><?= $totalBooking ?></p>
    </div>

    <?php if ($lastBooking): ?>
    <div class="card">
        <i class="fa-solid fa-school"></i>
        <h3>Sekolah Terakhir</h3>
        <p><?= htmlspecialchars($lastBooking['nama_sekolah']) ?></p>
    </div>
    <div class="card">
        <i class="fa-solid fa-clock"></i>
        <h3>Slot Terakhir</h3>
        <p><?= htmlspecialchars($lastBooking['jam_slot']) ?>, <?= date("d F Y", strtotime($lastBooking['tanggal_booking'])) ?></p>
    </div>
    <div class="card">
        <i class="fa-solid fa-circle-check"></i>
        <h3>Status</h3>
        <p><?= htmlspecialchars(ucfirst($lastBooking['status'])) ?></p>
    </div>
    <?php else: ?>
    <div class="card" style="flex:3"><p>Belum ada booking</p></div>
    <?php endif; ?>
</div>

<?php if (isset($success)): ?>
<div class="alert-section alert-success">
    <i class="fa-solid fa-circle-check alert-icon"></i>
    <div><?= $success ?></div>
</div>
<div class="qr-container">
    <img src="<?= $qr_generated ?>" alt="QR Code">
    <br>
    <a href="<?= $qr_generated ?>" download class="download-btn"><i class="fa fa-download"></i> Unduh QR Code</a>
</div>
<?php endif; ?>

<?php if (isset($error)): ?>
<div class="alert-section">
    <i class="fa-solid fa-triangle-exclamation alert-icon"></i>
    <div><?= $error ?></div>
</div>
<?php endif; ?>

<!-- Tabel Jadwal -->
<table class="school-table">
<thead>
<tr>
<th class="no">No</th>
<th>Sekolah</th>
<th>Tanggal</th>
<th>Jam Slot</th>
<th>Kuota</th>
<th>Terisi</th>
<th>Sisa</th>
<th class="aksi">Booking</th>
</tr>
</thead>
<tbody>
<?php
$no = 1;
$resultJadwal->data_seek(0); // reset pointer
while ($row = $resultJadwal->fetch_assoc()):
    $tanggal = $row['tanggal'];
    $jam_mulai = strtotime($row['jam_mulai']);
    $jam_selesai = strtotime($row['jam_selesai']);
    $kuota = $row['kuota_per_jam'];

    for ($jam = $jam_mulai; $jam < $jam_selesai; $jam += 3600):
        $jam_slot = date("H:i", $jam) . " - " . date("H:i", $jam + 3600);

        $stmt = $conn->prepare("SELECT COUNT(*) as terisi FROM booking_daftar_ulang WHERE jadwal_id=? AND jam_slot=?");
        $stmt->bind_param("is", $row['id'], $jam_slot);
        $stmt->execute();
        $terisi = $stmt->get_result()->fetch_assoc()['terisi'];
        $stmt->close();

        $sisa = $kuota - $terisi;
?>
<tr>
<td><?= $no++ ?></td>
<td><?= htmlspecialchars($row['nama_sekolah']) ?></td>
<td><?= date("d F Y", strtotime($tanggal)) ?></td>
<td><?= $jam_slot ?></td>
<td><?= $kuota ?></td>
<td><?= $terisi ?></td>
<td><?= $sisa ?></td>

<td>
<?php
// Cek apakah siswa sudah booking di slot ini
$stmtCheckBooking = $conn->prepare("SELECT qr_code, status FROM booking_daftar_ulang WHERE siswa_id=? AND jadwal_id=? AND jam_slot=?");
$stmtCheckBooking->bind_param("iis", $siswa_id, $row['id'], $jam_slot);
$stmtCheckBooking->execute();
$resBooking = $stmtCheckBooking->get_result();
$existingBooking = $resBooking->fetch_assoc();
$stmtCheckBooking->close();

if ($existingBooking && $existingBooking['qr_code']) {
    echo "<div class='qr-container'>
            <img src='" . htmlspecialchars($existingBooking['qr_code']) . "' alt='QR Code'>
            <br>
            <a href='" . htmlspecialchars($existingBooking['qr_code']) . "' download class='download-btn'>
                <i class='fa fa-download'></i> Unduh QR Code
            </a>
          </div>";
} elseif (!$existingBooking && $sisa > 0) {
?>
<form method="POST">
  <input type="hidden" name="jadwal_id" value="<?= $row['id'] ?>">
  <input type="hidden" name="tanggal_booking" value="<?= $tanggal ?>">
  <input type="hidden" name="jam_slot" value="<?= $jam_slot ?>">
  <button type="submit" name="booking"><i class="fa fa-ticket"></i> Booking</button>
</form>
<?php
} else {
    echo "<span>Penuh</span>";
}
?>
</td>

</tr>
<?php
    endfor;
endwhile;
?>
</tbody>
</table>

</div>
</body>
</html>
