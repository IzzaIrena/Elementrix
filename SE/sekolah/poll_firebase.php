<?php
date_default_timezone_set('Asia/Makassar');
include "../koneksi_mysql.php";

/* ===============================
   URL Firebase
   =============================== */
$firebase_url = "https://ptc-yh-default-rtdb.asia-southeast1.firebasedatabase.app/scan_logs.json";
$json = file_get_contents($firebase_url);
$data = json_decode($json, true);

if (!$data) {
    echo "no-data<br>";
    exit;
}

/* =================================================
   1️⃣ AMBIL SCAN TERBARU PER ID + SEKOLAH
   (ID bisa siswa_id ATAU booking_id)
   ================================================= */
$latestScan = [];

foreach ($data as $scan) {
    if (empty($scan['ID']) || empty($scan['Sekolah']) || empty($scan['timestamp'])) {
        continue;
    }

    $key = $scan['ID'] . '_' . $scan['Sekolah'];

    if (!isset($latestScan[$key]) || $scan['timestamp'] > $latestScan[$key]['timestamp']) {
        $latestScan[$key] = $scan;
    }
}

/* =================================================
   2️⃣ PROSES SCAN
   ================================================= */
$log = [];

foreach ($latestScan as $scan) {

    $scan_id    = (int)$scan['ID'];          // bisa siswa_id atau booking_id
    $sekolah_id = (int)$scan['Sekolah'];

    if (!$scan_id || !$sekolah_id) continue;

    $scan_time = date("Y-m-d H:i:s");

    /* =================================================
       3️⃣ CEK APAKAH ID INI BOOKING_ID?
       ================================================= */
    $stmt = $conn->prepare("
        SELECT *
        FROM booking_daftar_ulang
        WHERE id = ? AND sekolah_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $scan_id, $sekolah_id);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    /* =================================================
       4️⃣ JIKA BUKAN BOOKING_ID → ANGGAP SISWA_ID
       ================================================= */
    if (!$booking) {

        $siswa_id = $scan_id;

        // ❗ HANYA booking AKTIF (BUKAN ditunda)
        $stmt = $conn->prepare("
            SELECT *
            FROM booking_daftar_ulang
            WHERE siswa_id = ?
            AND sekolah_id = ?
            AND timestamp_scan IS NULL
            AND status <> 'ditunda'
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->bind_param("ii", $siswa_id, $sekolah_id);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$booking) {
            $log[] = "Scan siswa_id=$siswa_id diabaikan (tidak ada booking aktif)";
            continue;
        }
    }

 /* =================================================
5️⃣ LOGIKA KHUSUS BOOKING
================================================= */
$scan_time = date("Y-m-d H:i:s"); // waktu scan nyata sekarang

// Cek apakah booking pernah ditunda
$ever_ditunda = ($booking['status'] === 'ditunda'); // bisa juga pakai field khusus jika ada

if ($ever_ditunda) {
    // Scan ulang booking yang ditunda → otomatis Telat
    // Pastikan scan pakai BOOKING ID yang sama
    if ($scan_id != $booking['id']) {
        $log[] = "Scan lama diabaikan (booking ditunda)";
        continue;
    }
    $status_keterangan = 'Telat';
}
else {
    // booking normal → cek Hadir/Telat sesuai jam booking (per jam)
    if (!empty($booking['tanggal_booking']) && !empty($booking['jam_booking'])) {
        $booking_time = strtotime($booking['tanggal_booking'] . " " . $booking['jam_booking']);
        $scan_ts      = strtotime($scan_time);

        // Hadir jika scan berada di jam yang sama dengan jam booking
        $jadwal_start = strtotime(date('Y-m-d H:00:00', $booking_time)); // jam mulai
        $jadwal_end   = strtotime(date('Y-m-d H:59:59', $booking_time)); // jam selesai

        $status_keterangan = ($scan_ts >= $jadwal_start && $scan_ts <= $jadwal_end) ? 'Hadir' : 'Telat';
    } else {
        // jika tanggal/jam booking kosong → tetap Telat
        $status_keterangan = 'Telat';
    }
}

/* =================================================
7️⃣ UPDATE BOOKING
================================================= */
$stmt = $conn->prepare("
    UPDATE booking_daftar_ulang
    SET timestamp_scan = ?, status_keterangan = ?
    WHERE id = ?
");
$stmt->bind_param("ssi", $scan_time, $status_keterangan, $booking['id']);
$stmt->execute();
$stmt->close();

$log[] = "Scan OK booking_id={$booking['id']} → $status_keterangan";

}

/* =================================================
   LOG OUTPUT
   ================================================= */
echo implode("<br>", $log);
