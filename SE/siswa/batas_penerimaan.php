<?php
include("../koneksi_mysql.php"); // koneksi $conn

// ============================
// TAHUN AKADEMIK AKTIF
// ============================
$qTahun = mysqli_query($conn,
    "SELECT id, nama_tahun 
     FROM tahun_akademik 
     WHERE status = 'aktif' 
     LIMIT 1"
);

if (!$qTahun || mysqli_num_rows($qTahun) == 0) {
    exit("<p>Tahun akademik aktif belum ditentukan.</p>");
}

$tahunAktif = mysqli_fetch_assoc($qTahun);
$tahun_aktif_id = (int)$tahunAktif['id'];

$search = strtolower(trim($_GET['search'] ?? ''));

// Ambil data aturan zonasi & akademik terbaru
$aturanZonasi = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT * FROM aturan_zonasi 
     WHERE tahun_akademik_id = $tahun_aktif_id
     ORDER BY id DESC 
     LIMIT 1"
)) ?? [];

$aturanAkademik = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT * FROM aturan_seleksi 
     WHERE tahun_akademik_id = $tahun_aktif_id
     ORDER BY id DESC 
     LIMIT 1"
)) ?? [];

// Ambil data sekolah sesuai kata kunci
$sqlSekolah = "SELECT * FROM sekolah";
if ($search) {
    $sqlSekolah .= " WHERE LOWER(nama_sekolah) LIKE ?";
    $stmtSekolah = mysqli_prepare($conn, $sqlSekolah);
    $likeSearch = "%$search%";
    mysqli_stmt_bind_param($stmtSekolah, "s", $likeSearch);
    mysqli_stmt_execute($stmtSekolah);
    $resSekolah = mysqli_stmt_get_result($stmtSekolah);
} else {
    $resSekolah = mysqli_query($conn, $sqlSekolah);
}

while ($s = mysqli_fetch_assoc($resSekolah)) {
    $zonaMax = null;
    $nilaiMin = null;
    $totalZonasi = 0;
    $totalAkademik = 0;

    $kuota_total = intval($s['kuota'] ?? 0);
    $kuota_persen_zonasi = intval($aturanZonasi['kuota_persen'] ?? 0);
    $kuota_persen_akademik = intval($aturanAkademik['kuota_persen'] ?? 0);

    $kuotaZonasi = (int) floor(($kuota_total * $kuota_persen_zonasi) / 100);
    $kuotaAkademik = $kuota_total - $kuotaZonasi;

    // Ambil data pendaftaran terverifikasi untuk sekolah ini
    $sqlPendaftaran = "
        SELECT p.*, v.status_verifikasi
        FROM pendaftaran p
        JOIN verifikasi_pendaftaran v ON p.id = v.pendaftaran_id
        WHERE v.sekolah_id = ?
        AND p.tahun_id = ?
        AND v.status_verifikasi = 'diterima'
    ";
    $stmtP = mysqli_prepare($conn, $sqlPendaftaran);
    mysqli_stmt_bind_param($stmtP, "ii", $s['id'], $tahun_aktif_id);
    mysqli_stmt_execute($stmtP);
    $resP = mysqli_stmt_get_result($stmtP);

    while ($p = mysqli_fetch_assoc($resP)) {
        // Mode jalur di sini diasumsikan tersimpan di pendaftaran, misal kolom 'jalur_ke' atau 'mode'
        $mode = strtolower($p['jalur'] ?? ''); // sesuaikan dengan nama kolom di database

        if ($mode === 'zonasi') {
            // Ambil koordinat siswa
            $idSiswa = $p['siswa_id'];
            $siswa = mysqli_fetch_assoc(mysqli_query($conn, 
                "SELECT latitude, longitude FROM siswa WHERE id = $idSiswa"
            ));

            $latS = floatval($siswa['latitude']);
            $lonS = floatval($siswa['longitude']);

            // Koordinat sekolah
            $latK = floatval($s['latitude']);
            $lonK = floatval($s['longitude']);

            // Hitung jarak Haversine
            $earthRadius = 6371; // km
            $dLat = deg2rad($latK - $latS);
            $dLon = deg2rad($lonK - $lonS);

            $a = sin($dLat/2) * sin($dLat/2) +
                cos(deg2rad($latS)) * cos(deg2rad($latK)) *
                sin($dLon/2) * sin($dLon/2);

            $c = 2 * atan2(sqrt($a), sqrt(1-$a));
            $jarak = $earthRadius * $c;

            // update ambang batas
            if ($zonaMax === null || $jarak > $zonaMax) $zonaMax = $jarak;
            $totalZonasi++;
        }
        if ($mode === 'akademik') {
            $idSiswa = $p['siswa_id'];

            // Ambil rata-rata nilai siswa
            $resNilai = mysqli_query($conn, "SELECT AVG(nilai) AS rata FROM nilai_akademik WHERE siswa_id = $idSiswa");
            $rowNilai = mysqli_fetch_assoc($resNilai);
            $rata = floatval($rowNilai['rata'] ?? 0);

            if ($nilaiMin === null || $rata < $nilaiMin) $nilaiMin = $rata;
            $totalAkademik++;
        }
    }

    echo "<div class='hasil-item'>";
    echo "<h4>🏫 " . htmlspecialchars($s['nama_sekolah']) . "</h4>";

    // Zonasi
    echo "<h5>Jalur Zonasi</h5>";
    echo "<table class='tabel-batas'>
            <tr><th>Kuota</th><th>Jumlah Terverifikasi</th><th>Ambang Jarak Terjauh</th></tr>
            <tr><td>$kuotaZonasi</td><td>$totalZonasi</td><td>" . ($zonaMax !== null ? number_format($zonaMax,2)." km" : "-") . "</td></tr>
          </table>";

    // Akademik
    echo "<h5>Jalur Akademik</h5>";
    echo "<table class='tabel-batas'>
            <tr><th>Kuota</th><th>Jumlah Terverifikasi</th><th>Ambang Nilai Terendah</th></tr>
            <tr><td>$kuotaAkademik</td><td>$totalAkademik</td><td>" . ($nilaiMin !== null ? number_format($nilaiMin,2) : "-") . "</td></tr>
          </table>";

    echo "</div>";
}
?>
