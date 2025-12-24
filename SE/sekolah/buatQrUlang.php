<?php
session_start();
include("../koneksi_mysql.php");
require_once '../phpqrcode-master/qrlib.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'sekolah') {
    exit("Akses ditolak");
}

$siswa_id   = intval($_POST['siswa_id'] ?? 0);
$sekolah_id = intval($_SESSION['sekolah_id']);

if (!$siswa_id || !$sekolah_id) {
    exit("Data tidak valid");
}

/* ===============================
   Ambil booking terakhir
   =============================== */
$q = $conn->prepare("
    SELECT * FROM booking_daftar_ulang
    WHERE siswa_id = ? AND sekolah_id = ?
    ORDER BY id DESC LIMIT 1
");
$q->bind_param("ii", $siswa_id, $sekolah_id);
$q->execute();
$old = $q->get_result()->fetch_assoc();
$q->close();

if (!$old) {
    exit("Data booking tidak ditemukan");
}

/* ===============================
   Ambil nama siswa
   =============================== */
$qs = $conn->prepare("SELECT nama_lengkap FROM siswa WHERE id=?");
$qs->bind_param("i", $siswa_id);
$qs->execute();
$nama = $qs->get_result()->fetch_assoc()['nama_lengkap'] ?? 'Siswa';
$qs->close();

/* ===============================
   INSERT booking BARU
   =============================== */
$stmt = $conn->prepare("
    INSERT INTO booking_daftar_ulang
    (siswa_id, sekolah_id, nama, tanggal_booking, jam_booking, status, status_keterangan)
    VALUES (?, ?, ?, NULL, NULL, 'ditunda', 'Menunggu Scan Ulang')
");
$stmt->bind_param(
    "iis",
    $siswa_id,
    $sekolah_id,
    $nama
);
$stmt->execute();
$new_booking_id = $stmt->insert_id;
$stmt->close();

/* ===============================
   Generate QR BARU
   =============================== */
$qrFolder = "../qr_booking/";
if (!is_dir($qrFolder)) mkdir($qrFolder, 0777, true);

$qrText =
    "ID:$new_booking_id;" .
    "Nama:$nama;" .
    "Sekolah:$sekolah_id;" .
    "Tanggal:{$old['tanggal_booking']};" .
    "Jam:{$old['jam_booking']};";

$qrPath = $qrFolder . "daftarulang_" . $new_booking_id . ".png";
QRcode::png($qrText, $qrPath, QR_ECLEVEL_L, 6, 2);

/* ===============================
   Update QR path
   =============================== */
$u = $conn->prepare("
    UPDATE booking_daftar_ulang
    SET qr_code = ?
    WHERE id = ?
");
$u->bind_param("si", $qrPath, $new_booking_id);
$u->execute();
$u->close();

echo "<script>
    alert('QR baru berhasil dibuat. Silakan scan ulang.');
    window.location.href = 'detailPendaftaran.php?siswa_id=$siswa_id';
</script>";
