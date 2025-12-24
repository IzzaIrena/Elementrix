<?php
include("../koneksi_mysql.php");
session_start();

$siswa_id   = $_POST['siswa_id'] ?? '';
$status     = $_POST['status'] ?? '';
$sekolah_id = $_SESSION['sekolah_id'] ?? '';

if (!$siswa_id || !$status || !$sekolah_id) {
    echo "Data tidak valid.";
    exit;
}

/* ============================================================
   Mapping status dari tombol
   ============================================================ */
if ($status == "Terverifikasi") {
    $status_lower = "diterima";
} 
elseif ($status == "Verifikasi_Ditolak") {
    $status_lower = "ditolak";
} 
else {
    $status_lower = strtolower($status);
}

/* ============================================================
   1. Ambil pendaftaran_id berdasarkan siswa_id + sekolah_id
   ============================================================ */
$stmt = $conn->prepare("
    SELECT id 
    FROM pendaftaran 
    WHERE siswa_id = ?
    AND (pilihan_ke1 = ? OR pilihan_ke2 = ? OR pilihan_ke3 = ?)
");
$stmt->bind_param("iiii", $siswa_id, $sekolah_id, $sekolah_id, $sekolah_id);
$stmt->execute();
$result = $stmt->get_result();
$pendaftaran = $result->fetch_assoc();
$stmt->close();

if (!$pendaftaran) {
    echo "❌ Pendaftar tidak ditemukan untuk sekolah ini.";
    exit;
}

$pendaftaran_id = $pendaftaran['id'];

/* ============================================================
   2. Cek apakah sudah ada record verifikasi sebelumnya
   ============================================================ */
$stmt = $conn->prepare("
    SELECT id 
    FROM verifikasi_pendaftaran 
    WHERE pendaftaran_id = ? AND sekolah_id = ?
");
$stmt->bind_param("ii", $pendaftaran_id, $sekolah_id);
$stmt->execute();
$result = $stmt->get_result();
$cek = $result->fetch_assoc();
$stmt->close();

/* ============================================================
   3A. Jika BELUM ADA → INSERT verifikasi baru
   ============================================================ */
if (!$cek) {

    $stmt = $conn->prepare("
        INSERT INTO verifikasi_pendaftaran
        (pendaftaran_id, sekolah_id, status_verifikasi, tanggal_verifikasi)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->bind_param("iis", $pendaftaran_id, $sekolah_id, $status_lower);
    $stmt->execute();
    $stmt->close();

    echo "✅ Verifikasi tersimpan: $status_lower.";
}

/* ============================================================
   3B. Jika SUDAH ADA → UPDATE status_verifikasi
   ============================================================ */
else {

    $verifikasi_id = $cek['id'];

    $stmt = $conn->prepare("
        UPDATE verifikasi_pendaftaran
        SET status_verifikasi = ?, tanggal_verifikasi = NOW()
        WHERE id = ?
    ");
    $stmt->bind_param("si", $status_lower, $verifikasi_id);
    $stmt->execute();
    $stmt->close();

    echo "✅ Status verifikasi diperbarui menjadi: $status_lower.";
}

$conn->close();
?>
