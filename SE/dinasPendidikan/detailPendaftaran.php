<?php
session_start();
include("../koneksi_mysql.php"); // koneksi MySQL ($conn)

// ============================
// CEK LOGIN DINAS
// ============================
if (!isset($_SESSION['dinas_id'])) {
    header("Location: loginDinas.php");
    exit;
}

// ============================
// CEK PARAMETER ID PENDAFTARAN
// ============================
if (!isset($_GET['id'])) {
    echo "<p>ID pendaftaran tidak ditemukan.</p>";
    exit;
}
$pendaftaran_id = intval($_GET['id']);

// ============================
// AMBIL DATA PENDAFTARAN
// ============================
$q = $conn->prepare("SELECT * FROM pendaftaran WHERE id = ?");
$q->bind_param("i", $pendaftaran_id);
$q->execute();
$pendaftaran = $q->get_result()->fetch_assoc();

if (!$pendaftaran) {
    echo "<p>Data pendaftaran tidak ditemukan.</p>";
    exit;
}

$siswa_id = $pendaftaran['siswa_id'];

// ============================
// AMBIL DATA SISWA
// ============================
$q2 = $conn->prepare("
    SELECT 
        s.*, 
        u.nama AS user_nama, 
        u.email AS user_email
    FROM siswa s
    JOIN user u ON u.id = s.user_id
    WHERE s.id = ?
");
$q2->bind_param("i", $siswa_id);
$q2->execute();
$siswa = $q2->get_result()->fetch_assoc();

// ============================
// AMBIL DATA ORANG TUA / WALI
// ============================
$q5 = $conn->prepare("SELECT * FROM ortu_wali WHERE siswa_id = ?");
$q5->bind_param("i", $siswa_id);
$q5->execute();
$ortu = $q5->get_result()->fetch_assoc();

// ============================
// AMBIL DATA SEKOLAH PILIHAN (pilihan_ke1)
// ============================
$sekolahPilihan = "-";

if (!empty($pendaftaran['pilihan_ke1'])) {
    $sch = $conn->prepare("SELECT nama_sekolah FROM sekolah WHERE id = ?");
    $sch->bind_param("i", $pendaftaran['pilihan_ke1']);
    $sch->execute();
    $sekolahPilihan = $sch->get_result()->fetch_assoc()['nama_sekolah'] ?? "-";
}

// ============================
// AMBIL DATA SEKOLAH ASAL
// ============================
$q3 = $conn->prepare("SELECT * FROM sekolah_asal WHERE siswa_id = ?");
$q3->bind_param("i", $siswa_id);
$q3->execute();
$sekolahAsal = $q3->get_result()->fetch_assoc();

// ============================
// AMBIL NILAI AKADEMIK (RAPOR)
// ============================
$q6 = $conn->prepare("SELECT * FROM nilai_akademik WHERE siswa_id = ? ORDER BY kode_mapel, semester");
$q6->bind_param("i", $siswa_id);
$q6->execute();
$nilaiAkademik = $q6->get_result()->fetch_all(MYSQLI_ASSOC);

// ============================
// AMBIL DOKUMEN SISWA
// ============================
$q4 = $conn->prepare("SELECT * FROM dokumen_siswa WHERE siswa_id = ?");
$q4->bind_param("i", $siswa_id);
$q4->execute();
$filteredDocs = $q4->get_result()->fetch_all(MYSQLI_ASSOC);

// ============================
// FUNGSI Haversine
// ============================
function haversine($lat1, $lon1, $lat2, $lon2) {
    $R = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2)**2 + cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLon/2)**2;
    return 2 * $R * atan2(sqrt($a), sqrt(1-$a));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Detail Pendaftaran Siswa</title>
<link rel="stylesheet" href="../css/dashboardDinas.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<style>
    .container {
        width: 95%;        /* gunakan hampir seluruh lebar layar */
        max-width: 1200px; /* batasi maksimal supaya tetap nyaman */
        margin: auto;
        background: #fff;
        border-radius: 15px;
        padding: 20px 30px; /* kurangi padding agar lebih luas */
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    h2,h3 {color:#2c3e50;}
    h3 {border-left:4px solid #3498db; padding-left:10px; margin-top:20px;}
    table {width:100%; border-collapse:collapse; margin-top:10px;}
    td {padding:8px; vertical-align:top;}
    td.label {width:30%; font-weight:600; color:#555;}
    td.value {width:70%; color:#333;}
    .status {display:inline-block; padding:5px 10px; border-radius:15px; color:#fff; text-transform:capitalize;}
    .Terverifikasi, .verifikasi_diterima { background:#27ae60; }
    .Verifikasi_ditolak, .verifikasi_ditolak { background:#e74c3c; }
    .Tidak_lulus, .tidak_lulus { background:#e67e22; }
    .Lulus, .lulus { background:#3498db; }
    .back {display:inline-block; margin-top:20px; background:#3498db; color:#fff; padding:8px 16px; border-radius:8px; text-decoration:none;}
    .back:hover {background:#2c80b4;}
    #map {width:100%; height:300px; border-radius:10px; margin-top:10px;}
    .table-rapor {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
    font-size: 14px;
    border-radius: 10px;
    overflow: hidden;
}

.table-rapor th {
    background: #3498db;
    color: white;
    padding: 10px;
    text-align: left;
    font-weight: bold;
}

.table-rapor td {
    padding: 8px 10px;
    border-bottom: 1px solid #eee;
}

.table-rapor tr:nth-child(even) {
    background: #f8f9fa;
}

.table-rapor tr:hover {
    background: #eaf4ff;
}

</style>
</head>
<body>
<div class="container">
<h2>Detail Pendaftaran Siswa</h2>

<h3><i class="fa-solid fa-id-card"></i> Biodata Siswa</h3>
<table>
<tr><td class="label">Nama Lengkap</td><td class="value"><?= htmlspecialchars($siswa['nama_lengkap'] ?? '-') ?></td></tr>
<tr><td class="label">NISN</td><td class="value"><?= htmlspecialchars($siswa['nisn'] ?? '-') ?></td></tr>
<tr><td class="label">NIK</td><td class="value"><?= htmlspecialchars($siswa['nik'] ?? '-') ?></td></tr>
<tr><td class="label">TTL</td><td class="value"><?= htmlspecialchars(($siswa['tempat_lahir'] ?? '-') . ', ' . ($siswa['tanggal_lahir'] ?? '-')) ?></td></tr>
<tr><td class="label">Jenis Kelamin</td>
<td class="value"><?= ($siswa['jk'] == 'L') ? 'Laki-laki' : (($siswa['jk'] == 'P') ? 'Perempuan' : '-') ?></td></tr>
<tr><td class="label">Alamat</td><td class="value"><?= htmlspecialchars($siswa['alamat'] ?? '-') ?></td></tr>
<tr><td class="label">No. HP</td><td class="value"><?= htmlspecialchars($siswa['no_hp'] ?? '-') ?></td></tr>
<tr><td class="label">Email</td><td class="value"><?= htmlspecialchars($siswa['user_email'] ?? '-') ?></td></tr>
</table>

<?php if($ortu): ?>
<h3><i class="fa-solid fa-users"></i> Orang Tua / Wali</h3>
<table>
<tr><td class="label">Nama Ayah</td><td class="value"><?= htmlspecialchars($ortu['nama_ayah'] ?? '-') ?></td></tr>
<tr><td class="label">No. HP Ayah</td><td class="value"><?= htmlspecialchars($ortu['no_hp_ayah'] ?? '-') ?></td></tr>
<tr><td class="label">Nama Ibu</td><td class="value"><?= htmlspecialchars($ortu['nama_ibu'] ?? '-') ?></td></tr>
<tr><td class="label">No. HP Ibu</td><td class="value"><?= htmlspecialchars($ortu['no_hp_ibu'] ?? '-') ?></td></tr>
<tr><td class="label">Nama Wali</td><td class="value"><?= htmlspecialchars($ortu['nama_wali'] ?? '-') ?></td></tr>
<tr><td class="label">No. HP Wali</td><td class="value"><?= htmlspecialchars($ortu['no_hp_wali'] ?? '-') ?></td></tr>
</table>
<?php endif; ?>

<?php if($sekolahAsal): ?>
<h3><i class="fa-solid fa-school"></i> Asal Sekolah</h3>
<table>
<tr><td class="label">Nama Sekolah</td><td class="value"><?= htmlspecialchars($sekolahAsal['nama_sekolah_asal'] ?? '-') ?></td></tr>
<tr><td class="label">NPSN</td><td class="value"><?= htmlspecialchars($sekolahAsal['npsn_sekolah_asal'] ?? '-') ?></td></tr>
<tr><td class="label">Alamat</td><td class="value"><?= htmlspecialchars($sekolahAsal['alamat_sekolah_asal'] ?? '-') ?></td></tr>
</table>
<?php endif; ?>

<h3><i class="fa-solid fa-file-circle-check"></i> Pendaftaran</h3>
<table>
<tr><td class="label">Sekolah Pilihan</td><td class="value"><?= htmlspecialchars($sekolahPilihan) ?></td></tr>
<tr><td class="label">Tanggal Daftar</td><td class="value"><?= htmlspecialchars($pendaftaran['tanggal_daftar'] ?? '-') ?></td></tr>
<tr>
<td class="label">Status</td>
<td class="value">
    <?php
    // Default status
    $statusText = "Tidak Lulus di Semua Pilihan";
    $statusClass = "tidak_lulus";

    // Jika ada sekolah diterima
    if (!empty($pendaftaran['sekolah_diterima'])) {
        $idSekolah = $pendaftaran['sekolah_diterima'];

        $s = $conn->prepare("SELECT nama_sekolah FROM sekolah WHERE id = ?");
        $s->bind_param("i", $idSekolah);
        $s->execute();
        $namaSekolah = $s->get_result()->fetch_assoc()['nama_sekolah'] ?? "-";

        $statusText = "Lulus di " . $namaSekolah;
        $statusClass = "lulus";

    } else {
        // cek status_seleksi1-3 untuk memastikan tidak ada yang 'lolos'
        if (
            $pendaftaran['status_seleksi1'] === 'lolos' ||
            $pendaftaran['status_seleksi2'] === 'lolos' ||
            $pendaftaran['status_seleksi3'] === 'lolos'
        ) {
            $statusText = "Lulus (Data sekolah tidak ditemukan)";
            $statusClass = "lulus";
        }
    }
    ?>

    <span class="status <?= $statusClass ?>"><?= $statusText ?></span>
</td>
</table>

<h3><i class="fa-solid fa-graduation-cap"></i> Nilai Akademik (Rapor)</h3>
<table class="table-rapor">
    <tr>
        <th>Mata Pelajaran</th>
        <th>Semester 1</th>
        <th>Semester 2</th>
        <th>Semester 3</th>
        <th>Semester 4</th>
        <th>Semester 5</th>
    </tr>

    <?php
    // === Ambil daftar mata pelajaran (aturan_mapel)
    $mapelQuery = $conn->query("SELECT id, kode_mapel, nama_mapel 
                                FROM aturan_mapel 
                                WHERE aktif = 1 
                                ORDER BY id ASC");

    // === Susun nilai akademik menjadi array [kode_mapel][semester] = nilai
    $nilaiMap = [];
    foreach ($nilaiAkademik as $n) {
        $kode = $n['kode_mapel'];
        $sem = $n['semester'];
        $nilaiMap[$kode][$sem] = $n['nilai'];
    }

    // === Tampilkan tabel
    while ($m = $mapelQuery->fetch_assoc()):
        $kode = $m['kode_mapel'];
    ?>
        <tr>
            <td><strong><?= htmlspecialchars($m['nama_mapel']) ?></strong></td>
            <td><?= $nilaiMap[$kode][1] ?? '-' ?></td>
            <td><?= $nilaiMap[$kode][2] ?? '-' ?></td>
            <td><?= $nilaiMap[$kode][3] ?? '-' ?></td>
            <td><?= $nilaiMap[$kode][4] ?? '-' ?></td>
            <td><?= $nilaiMap[$kode][5] ?? '-' ?></td>
        </tr>
    <?php endwhile; ?>
</table>

<?php if(!empty($filteredDocs)): ?>
<h3><i class="fa-solid fa-folder-open"></i> Dokumen Pendukung</h3>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:15px;">
<?php foreach($filteredDocs as $doc):
$nama = htmlspecialchars($doc['nama_dokumen'] ?? 'Dokumen');
$path = htmlspecialchars($doc['file_path'] ?? '');
$url = (strpos($path,'http')===0) ? $path : "../uploads/dokumen/".$path;
$status = ucfirst($doc['status'] ?? 'Pending');
?>
<div style="text-align:center;border:1px solid #ddd;padding:10px;border-radius:10px;background:#fafafa;">
<p><b><?= $nama ?></b><br><small>Status: <?= $status ?></small></p>
<a href="<?= $url ?>" target="_blank"><img src="<?= $url ?>" alt="<?= $nama ?>" style="width:100%;max-height:150px;object-fit:cover;border-radius:6px;"></a>
</div>
<?php endforeach; ?>
</div>
<?php else: ?>
<p style="color:#666;">Tidak ada dokumen diunggah untuk siswa ini.</p>
<?php endif; ?>

<?php if(!empty($siswa['latitude']) && !empty($siswa['longitude'])): ?>
<h3><i class="fa-solid fa-location-dot"></i> Titik Lokasi Rumah Siswa</h3>
<div id="map"></div>
<script>
    var map = L.map('map').setView([<?= $siswa['latitude'] ?>, <?= $siswa['longitude'] ?>], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

    L.marker([<?= $siswa['latitude'] ?>, <?= $siswa['longitude'] ?>]).addTo(map);
</script>
<?php else: ?>
<p style="color: gray;">Lokasi belum tersedia.</p>
<?php endif; ?>

<a href="kelolaPendaftaranDinas.php" class="back"><i class="fa fa-arrow-left"></i> Kembali</a>
</div>
</body>
</html>
