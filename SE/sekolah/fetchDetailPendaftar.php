<?php
include("../koneksi_mysql.php"); // $conn

$siswa_id = $_GET['siswa_id'] ?? '';
if (!$siswa_id) {
    echo "<p>ID siswa tidak ditemukan.</p>";
    exit;
}

// =========================
// Ambil data siswa
// =========================
$qSiswa = $conn->prepare("
    SELECT s.*, u.email 
    FROM siswa s
    JOIN user u ON u.id = s.user_id
    WHERE s.id = ?
");
$qSiswa->bind_param("i", $siswa_id);
$qSiswa->execute();
$siswa = $qSiswa->get_result()->fetch_assoc();

if (!$siswa) {
    echo "<p>Data siswa tidak ditemukan.</p>";
    exit;
}

// =========================
// Ambil data pendaftaran
// =========================
$qDaftar = $conn->prepare("SELECT * FROM pendaftaran WHERE siswa_id = ?");
$qDaftar->bind_param("i", $siswa_id);
$qDaftar->execute();
$pendaftaran = $qDaftar->get_result()->fetch_assoc();

if (!$pendaftaran) {
    echo "<p>Data pendaftaran tidak ditemukan.</p>";
    exit;
}

// =========================
// Ambil sekolah asal
// =========================
$qAsal = $conn->prepare("SELECT * FROM sekolah_asal WHERE siswa_id = ?");
$qAsal->bind_param("i", $siswa_id);
$qAsal->execute();
$sekolahAsal = $qAsal->get_result()->fetch_assoc();

// ========================
// Ambil data Orang Tua / Wali
// ========================
$qOrtu = $conn->prepare("SELECT * FROM ortu_wali WHERE siswa_id = ?");
$qOrtu->bind_param("s", $siswa_id);
$qOrtu->execute();
$ortu = $qOrtu->get_result()->fetch_assoc();

// ========================
// Bagian 1: Biodata Siswa
// ========================
echo "<h3><i class='fa-solid fa-id-card'></i> Biodata Siswa</h3>";
echo "<table class='detail-table'>";

$biodata = [
    'NISN' => $siswa['nisn'] ?? '-',
    'Nama Lengkap' => $siswa['nama_lengkap'] ?? '-',
    'Email' => $siswa['email'] ?? '-',
    'Jenis Kelamin' => ($siswa['jk'] == 'L' ? 'Laki-Laki' : ($siswa['jk'] == 'P' ? 'Perempuan' : '-')),
    'Tempat Lahir' => $siswa['tempat_lahir'] ?? '-',
    'Tanggal Lahir' => $siswa['tanggal_lahir'] ?? '-',
    'Alamat Rumah' => $siswa['alamat'] ?? '-',
    'No. HP' => $siswa['no_hp'] ?? '-',
    'Jalur Pendaftaran' => ucfirst($pendaftaran['jalur'] ?? '-'),
    'Tanggal Daftar' => $pendaftaran['tanggal_daftar'] ?? '-'
];

foreach ($biodata as $label => $value) {
    echo "<tr><td><b>{$label}</b></td><td>" . htmlspecialchars($value) . "</td></tr>";
}
echo "</table>";

// ========================
// Bagian 2: Asal Sekolah
// ========================
if ($sekolahAsal) {
    echo "<h3 style='margin-top:20px;'><i class='fa-solid fa-school'></i> Asal Sekolah</h3>";
    echo "<table class='detail-table'>";
    echo "<tr><td><b>Nama Sekolah</b></td><td>" . htmlspecialchars($sekolahAsal['nama_sekolah_asal']) . "</td></tr>";
    echo "<tr><td><b>NPSN</b></td><td>" . htmlspecialchars($sekolahAsal['npsn_sekolah_asal']) . "</td></tr>";
    echo "<tr><td><b>Alamat Sekolah</b></td><td>" . htmlspecialchars($sekolahAsal['alamat_sekolah_asal']) . "</td></tr>";
    echo "</table>";
}

// ========================
// Bagian 3: Data Orang Tua / Wali
// ========================
if ($ortu) {
    echo "<h3 style='margin-top:20px;'><i class='fa-solid fa-users'></i> Data Orang Tua / Wali</h3>";
    echo "<table class='detail-table'>";

    echo "<tr><td><b>Nama Ayah</b></td><td>" . htmlspecialchars($ortu['nama_ayah'] ?? '-') . "</td></tr>";
    echo "<tr><td><b>No. HP Ayah</b></td><td>" . htmlspecialchars($ortu['no_hp_ayah'] ?? '-') . "</td></tr>";

    echo "<tr><td><b>Nama Ibu</b></td><td>" . htmlspecialchars($ortu['nama_ibu'] ?? '-') . "</td></tr>";
    echo "<tr><td><b>No. HP Ibu</b></td><td>" . htmlspecialchars($ortu['no_hp_ibu'] ?? '-') . "</td></tr>";

    echo "<tr><td><b>Nama Wali</b></td><td>" . htmlspecialchars($ortu['nama_wali'] ?? '-') . "</td></tr>";
    echo "<tr><td><b>No. HP Wali</b></td><td>" . htmlspecialchars($ortu['no_hp_wali'] ?? '-') . "</td></tr>";

    echo "</table>";
}

// ========================
// Bagian 4: Nilai Rapor
// ========================
$stmt = $conn->prepare("SELECT kode_mapel, semester, nilai 
                        FROM nilai_akademik 
                        WHERE siswa_id = ? 
                        ORDER BY kode_mapel, semester");
$stmt->bind_param("s", $siswa_id);
$stmt->execute();
$result = $stmt->get_result();

$nilaiRapor = [];

// Susun array format: nilaiRapor[mapel]['semester1'] = nilai
while ($row = $result->fetch_assoc()) {
    $mapel = $row['kode_mapel'];
    $sem = (int)$row['semester'];

    if (!isset($nilaiRapor[$mapel])) {
        $nilaiRapor[$mapel] = [
            'semester1' => '-',
            'semester2' => '-',
            'semester3' => '-',
            'semester4' => '-',
            'semester5' => '-',
        ];
    }

    if ($sem >= 1 && $sem <= 5) {
        $nilaiRapor[$mapel]["semester$sem"] = $row['nilai'];
    }
}

if (!empty($nilaiRapor)) {
    echo "<h3 style='margin-top:20px;'><i class='fa-solid fa-book'></i> Nilai Rapor</h3>";
    echo "<p style='font-style:italic;color:#666;'>*Nilai rapor tidak digunakan dalam seleksi zonasi.</p>";

    echo "<table class='detail-table' border='1' style='border-collapse:collapse;text-align:center;width:100%;'>
            <tr style='background:#f8f9fa;'>
                <th>Mata Pelajaran</th>
                <th>Semester 1</th>
                <th>Semester 2</th>
                <th>Semester 3</th>
                <th>Semester 4</th>
                <th>Semester 5</th>
            </tr>";

    foreach ($nilaiRapor as $mapel => $nilai) {
        echo "<tr>
                <td style='text-align:left;'>".htmlspecialchars($mapel)."</td>
                <td>{$nilai['semester1']}</td>
                <td>{$nilai['semester2']}</td>
                <td>{$nilai['semester3']}</td>
                <td>{$nilai['semester4']}</td>
                <td>{$nilai['semester5']}</td>
              </tr>";
    }

    echo "</table>";
}

// ========================
// Bagian 5: Dokumen Pendukung
// ========================
$qDocs = $conn->prepare("SELECT * FROM dokumen_siswa WHERE siswa_id = ?");
$qDocs->bind_param("i", $siswa_id);
$qDocs->execute();
$docs = $qDocs->get_result();

if ($docs->num_rows > 0) {
    echo "<h3 style='margin-top:20px;'><i class='fa-solid fa-folder-open'></i> Dokumen Pendukung</h3>";
    echo "<div style='display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:15px;'>";

    while ($doc = $docs->fetch_assoc()) {
        $nama = htmlspecialchars($doc['nama_dokumen']);
        $path = htmlspecialchars($doc['file_path']);
        $status = ucfirst($doc['status'] ?? 'Pending');
        $url = "../uploads/dokumen/" . $path;

        echo "<div style='text-align:center;border:1px solid #ddd;padding:10px;border-radius:10px;background:#fafafa;'>
                <p><b>{$nama}</b><br><small>Status: {$status}</small></p>
                <a href='{$url}' target='_blank'>
                    <img src='{$url}' alt='{$nama}' style='width:100%;max-height:150px;object-fit:cover;border-radius:6px;'>
                </a>
              </div>";
    }

    echo "</div>";
}

// ========================
// Bagian 6: Zonasi
// ========================
if (
    isset($pendaftaran['jalur']) &&
    strtolower($pendaftaran['jalur']) === 'zonasi' &&
    !empty($siswa['latitude']) &&
    !empty($siswa['longitude'])
) {
    echo "<h3 style='margin-top:20px;'><i class='fa-solid fa-map-location-dot'></i> Titik Lokasi Siswa</h3>";
    echo "<div id='map' style='width:100%;height:300px;border-radius:10px;margin-bottom:2px;'></div>";

    $lat = $siswa['latitude'];
    $lon = $siswa['longitude'];

    echo "
    <script>
    setTimeout(function() {
        if (typeof L !== 'undefined') {
            var map = L.map('map').setView([$lat, $lon], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 18,
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);
            L.marker([$lat, $lon]).addTo(map).bindPopup('Lokasi Siswa').openPopup();
        }
    }, 300);
    </script>";

    // Ambil lat/lon sekolah
    if (!empty($pendaftaran['sekolah_id'])) {
        $sid = $pendaftaran['sekolah_id'];
        $qS = $conn->query("SELECT * FROM sekolah WHERE id = $sid");
        $sekolah = $qS->fetch_assoc();

        if ($sekolah && $sekolah['latitude'] && $sekolah['longitude']) {
            $jarak = haversine($sekolah['latitude'], $sekolah['longitude'], $lat, $lon);
            echo "<p><b>Jarak ke sekolah:</b> " . number_format($jarak, 2) . " km</p>";
        }
    }
}

// ========================
// Fungsi Haversine
// ========================
function haversine($lat1, $lon1, $lat2, $lon2)
{
    $R = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2)**2 + cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLon/2)**2;
    return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

?>
