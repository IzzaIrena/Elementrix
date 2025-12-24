<?php
header("Content-Type: text/html; charset=UTF-8");
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");

include("../koneksi_mysql.php"); // koneksi MySQL
session_start();

// ==============================
// CEK LOGIN SEKOLAH
// ==============================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'sekolah') {
    exit("Akses ditolak");
}

$sekolah_id = $_SESSION['sekolah_id'];
$mode = $_GET['mode'] ?? 'akademik';
$tahun_id = (int) ($_GET['tahun_id'] ?? 0);
$status_filter = $_GET['status'] ?? 'all';
if ($tahun_id <= 0) {
    echo "<p style='padding:10px;background:#fff3cd;color:#856404;border-radius:6px;'>
            Tahun akademik belum dipilih.
          </p>";
    exit;
}

// ==============================
// AMBIL DATA SEKOLAH (UNTUK JARAK)
// ==============================
$stmt = $conn->prepare("SELECT latitude, longitude FROM sekolah WHERE id = ?");
$stmt->bind_param("i", $sekolah_id);
$stmt->execute();
$result = $stmt->get_result();
$sekolah = $result->fetch_assoc();
$latSekolah = $sekolah['latitude'] ?? null;
$lonSekolah = $sekolah['longitude'] ?? null;
$stmt->close();

// ==============================
// AMBIL DATA PENDAFTARAN & SISWA
// ==============================
$sql = "SELECT p.*, 
               s.nisn, s.nama_lengkap, 
               s.latitude AS s_lat, s.longitude AS s_lon,
               v.status_verifikasi,
               ROUND(IFNULL(AVG(n.nilai),0),2) AS nilai
        FROM pendaftaran p
        JOIN siswa s ON s.id = p.siswa_id
        LEFT JOIN verifikasi_pendaftaran v 
            ON v.pendaftaran_id = p.id AND v.sekolah_id = ?
        LEFT JOIN nilai_akademik n 
            ON n.siswa_id = s.id
        WHERE p.tahun_id = ?
          AND (p.pilihan_ke1 = ? OR p.pilihan_ke2 = ? OR p.pilihan_ke3 = ?)
          AND p.jalur = ?
        GROUP BY p.id";

$stmt = $conn->prepare($sql);
$stmt->bind_param(
    "iiiiis",
    $sekolah_id,    // untuk join verifikasi
    $tahun_id,      // filter tahun
    $sekolah_id,
    $sekolah_id,
    $sekolah_id,
    $mode
);
$stmt->execute();
$result = $stmt->get_result();

$pendaftaranArray = [];
while ($row = $result->fetch_assoc()) {
    $jarak = ($row['s_lat'] && $row['s_lon'] && $latSekolah && $lonSekolah)
        ? haversine($latSekolah, $lonSekolah, $row['s_lat'], $row['s_lon'])
        : null;
        
    // Tentukan pilihan ke berapa
    $pilihan = 0;
    if ($row['pilihan_ke1'] == $sekolah_id) $pilihan = 1;
    elseif ($row['pilihan_ke2'] == $sekolah_id) $pilihan = 2;
    elseif ($row['pilihan_ke3'] == $sekolah_id) $pilihan = 3;

    $pendaftaranArray[] = [
        'siswa_id' => $row['siswa_id'],
        'nisn'     => $row['nisn'] ?? '-',
        'nama'     => $row['nama_lengkap'] ?? '-',
        'nilai'    => $row['nilai'] ?? 0,
        'jarak'    => $jarak,
        'tanggal'  => $row['tanggal_daftar'] ?? '-',
        'status' => $row['status_verifikasi'] 
                        ? strtolower($row['status_verifikasi']) 
                        : 'pending',
        'pilihan'  => $pilihan
    ];
}
$stmt->close();

// ==============================
// FILTER STATUS (JIKA DIPILIH)
// ==============================
if ($status_filter !== 'all') {
    $pendaftaranArray = array_filter($pendaftaranArray, function($item) use ($status_filter) {
        return $item['status'] === $status_filter;
    });

    // reset index array
    $pendaftaranArray = array_values($pendaftaranArray);
}

// ==============================
// JIKA BELUM ADA PENDAFTAR
// ==============================
if (empty($pendaftaranArray)) {
    echo "<p style='padding:10px;background:#fff3cd;color:#856404;border-radius:6px;'>
            Tidak ada pendaftar dengan status <strong>".htmlspecialchars($status_filter)."</strong>.
          </p>";
    exit;
}

// ==============================
// URUT SESUAI MODE
// ==============================
if ($mode === 'akademik') {
    usort($pendaftaranArray, fn($a,$b)=>($b['nilai']??0)<=>($a['nilai']??0));
    echo "<table>
        <thead>
            <tr>
                <th>No</th>
                <th>NISN</th>
                <th>Nama</th>
                <th>Nilai</th>
                <th>Tanggal Daftar</th>
                <th>Pilihan</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead><tbody>";
} else {
    usort($pendaftaranArray, fn($a,$b)=>($a['jarak']??99999)<=>($b['jarak']??99999));
    echo "<table>
        <thead>
            <tr>
                <th>No</th>
                <th>NISN</th>
                <th>Nama</th>
                <th>Jarak (km)</th>
                <th>Tanggal Daftar</th>
                <th>Pilihan</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead><tbody>";
}

// ==============================
// TAMPILKAN DATA PENDAFTAR
// ==============================
$no = 1;
foreach ($pendaftaranArray as $row) {
    $statusBadge = match ($row['status']) {
        'diterima' => "<span style='color:#28a745;font-weight:600;'>Diterima</span>",
        'ditolak'  => "<span style='color:#dc3545;font-weight:600;'>Ditolak</span>",
        default    => "<span style='color:#ff9800;font-weight:600;'>Pending</span>"
    };

    if ($mode === 'akademik') {
        echo "<tr>
            <td>{$no}</td>
            <td>{$row['nisn']}</td>
            <td>{$row['nama']}</td>
            <td>{$row['nilai']}</td>
            <td>{$row['tanggal']}</td>
            <td>{$row['pilihan']}</td>
            <td>{$statusBadge}</td>
            <td>
                <button class='aksi-btn' onclick=\"lihatDetail('{$row['siswa_id']}', 'akademik')\">
                    <i class='fa fa-eye'></i> Lihat
                </button>
            </td>
        </tr>";
    } else {
        $jarakText = $row['jarak'] ? number_format($row['jarak'], 2).' km' : '-';
        echo "<tr>
            <td>{$no}</td>
            <td>{$row['nisn']}</td>
            <td>{$row['nama']}</td>
            <td>{$jarakText}</td>
            <td>{$row['tanggal']}</td>
            <td>{$row['pilihan']}</td>
            <td>{$statusBadge}</td>
            <td>
                <button class='aksi-btn' onclick=\"lihatDetail('{$row['siswa_id']}', 'zonasi')\">
                    <i class='fa fa-eye'></i> Lihat
                </button>
            </td>
        </tr>";
    }
    $no++;
}
echo "</tbody></table>";

// ==============================
// FUNGSI HAVERSINE UNTUK JARAK
// ==============================
function haversine($lat1, $lon1, $lat2, $lon2){
    $R = 6371; // radius bumi (km)
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2)*sin($dLat/2) + cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLon/2)*sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $R*$c;
}
?>
