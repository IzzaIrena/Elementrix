<?php
// ==========================
// tampilkan_pendaftaran.php
// ==========================
if (!isset($user_id)) {
    echo "<p style='color:red;text-align:center;'>⚠️ Data tidak tersedia.</p>";
    return;
}

// Ambil data siswa
$siswaQuery = mysqli_query($conn, "SELECT * FROM siswa WHERE id='$siswa_id_db' LIMIT 1");
$siswa = mysqli_fetch_assoc($siswaQuery);

$nama = $siswa['nama_lengkap'] ?? '-';
$nisn = $siswa['nisn'] ?? '-';
$nik = $siswa['nik'] ?? '-';
$alamat = $siswa['alamat'] ?? '-';
$no_hp = $siswa['no_hp'] ?? '-';
$tempat_lahir = $siswa['tempat_lahir'] ?? '-';
$tgl_lahir = $siswa['tanggal_lahir'] ?? null;
$jk = $siswa['jk'] ?? '-';
$latitude = $siswa['latitude'] ?? null;
$longitude = $siswa['longitude'] ?? null;

// Ambil data ortu/wali
$ortuQuery = mysqli_query($conn, "SELECT * FROM ortu_wali WHERE siswa_id='$siswa_id_db' LIMIT 1");
$dataOrtu = mysqli_fetch_assoc($ortuQuery);

// Ambil sekolah asal
$sekolahAsalQuery = mysqli_query($conn, "SELECT * FROM sekolah_asal WHERE siswa_id='$siswa_id_db' LIMIT 1");
$sekolahAsal = mysqli_fetch_assoc($sekolahAsalQuery);

$nama_sekolah_asal = $sekolahAsal['nama_sekolah_asal'] ?? '-';
$npsn_sekolah_asal = $sekolahAsal['npsn_sekolah_asal'] ?? '-';
$alamat_sekolah_asal = $sekolahAsal['alamat_sekolah_asal'] ?? '-';

// Ambil nilai rapor
$nilaiSiswa = [];
$mapelList = [];
$nilaiQuery = mysqli_query($conn, "SELECT n.*, m.nama_mapel FROM nilai_akademik n JOIN aturan_mapel m ON n.kode_mapel=m.kode_mapel WHERE n.siswa_id='$siswa_id_db'");
$adaNilai = false;
while($row = mysqli_fetch_assoc($nilaiQuery)){
    $nilaiSiswa[$row['semester']][$row['kode_mapel']] = $row['nilai'];
    $mapelList[$row['kode_mapel']] = ['nama_mapel'=>$row['nama_mapel']];
    $adaNilai = true;
}

// Ambil dokumen siswa
$dokData = [];
$dokQuery = mysqli_query($conn, "SELECT * FROM dokumen_siswa WHERE siswa_id='$siswa_id_db'");
while($row = mysqli_fetch_assoc($dokQuery)) $dokData[] = $row;

// Ambil daftar sekolah
$sekolahList = [];
$sekolahQuery = mysqli_query($conn, "SELECT * FROM sekolah");
while($row = mysqli_fetch_assoc($sekolahQuery)) $sekolahList[$row['id']] = $row;

// Ambil pendaftaran siswa
$pendaftaranData = [];
$pendaftaranQuery = mysqli_query($conn, "SELECT * FROM pendaftaran WHERE siswa_id='$siswa_id_db'");
while($row = mysqli_fetch_assoc($pendaftaranQuery)) $pendaftaranData[] = $row;

$mode = null;

if (!empty($pendaftaranData)) {
    // ambil jalur dari pendaftaran pertama (jalur sama untuk 1 siswa)
    $mode = strtolower($pendaftaranData[0]['jalur']);
}

?>

<!-- ===================== CSS untuk header fixed & map ===================== -->
<style>
/* Header fixed contoh */
header {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: 70px; /* sesuaikan tinggi header */
    background-color: #fff;
    z-index: 1000;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

/* Body content offset agar tidak tertutup header */
body {
    padding-top: 70px; /* sama dengan tinggi header */
}

/* Map container */
#map {
    position: relative; /* agar scroll normal */
    z-index: 1; /* lebih rendah dari header */
    height: 300px;
    border-radius: 10px;
    margin-bottom: 10px;
}

/* Table responsive */
.table-container {
    overflow-x: auto;
    margin-bottom: 20px;
}

.number {
    text-align: center;
}
</style>

<h4 style="text-align:center;">
  Data Pendaftaran <?= ucfirst(htmlspecialchars($mode)) ?>
</h4>

<!-- Data Pribadi -->
<h3>Data Pribadi</h3>
<div class="table-container">
  <table class="table-detail">
    <tr><th>Nama Lengkap</th><td><?= htmlspecialchars($nama) ?></td></tr>
    <tr><th>NISN</th><td><?= htmlspecialchars($nisn) ?></td></tr>
    <tr><th>NIK</th><td><?= htmlspecialchars($nik) ?></td></tr>
    <tr><th>Alamat</th><td><?= htmlspecialchars($alamat) ?></td></tr>
    <tr><th>No HP</th><td><?= htmlspecialchars($no_hp) ?></td></tr>
    <tr><th>Tempat, Tanggal Lahir</th>
        <td><?= htmlspecialchars($tempat_lahir) ?>, <?= $tgl_lahir ? date('d M Y', strtotime($tgl_lahir)) : '-' ?></td></tr>
    <tr><th>Jenis Kelamin</th><td><?= $jk=='L'?'Laki-laki':($jk=='P'?'Perempuan':'-') ?></td></tr>
  </table>
</div>

<?php if ($mode === 'zonasi' && is_numeric($latitude) && is_numeric($longitude)): ?>
<h4>📍 Titik Lokasi Rumah:</h4>
<div id="map"></div>

<input type="hidden" name="latitude" id="latitude" value="<?= htmlspecialchars($latitude); ?>">
<input type="hidden" name="longitude" id="longitude" value="<?= htmlspecialchars($longitude); ?>">

<script>
// Inisialisasi map setelah DOM siap
document.addEventListener('DOMContentLoaded', function() {
    var lat = <?= is_numeric($latitude) ? $latitude : -4.0167 ?>;
    var lng = <?= is_numeric($longitude) ? $longitude : 119.6200 ?>;

    var map = L.map('map').setView([lat, lng], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '© OpenStreetMap'
    }).addTo(map);

    L.marker([lat, lng], {draggable:false}).addTo(map);

    map.off('click');

    setTimeout(function(){ map.invalidateSize(); }, 200);
});
</script>
<?php endif; ?>

<!-- Data Orang Tua / Wali -->
<h3>Data Orang Tua / Wali</h3>
<?php if($dataOrtu): ?>
<div class="table-container">
  <table class="table-detail">
    <tr><th>Nama Ayah</th><td><?= htmlspecialchars($dataOrtu['nama_ayah'] ?? '-') ?></td></tr>
    <tr><th>No HP Ayah</th><td><?= htmlspecialchars($dataOrtu['no_hp_ayah'] ?? '-') ?></td></tr>
    <tr><th>Nama Ibu</th><td><?= htmlspecialchars($dataOrtu['nama_ibu'] ?? '-') ?></td></tr>
    <tr><th>No HP Ibu</th><td><?= htmlspecialchars($dataOrtu['no_hp_ibu'] ?? '-') ?></td></tr>
    <?php if(!empty($dataOrtu['nama_wali'])): ?>
    <tr><th>Nama Wali</th><td><?= htmlspecialchars($dataOrtu['nama_wali']) ?></td></tr>
    <tr><th>No HP Wali</th><td><?= htmlspecialchars($dataOrtu['no_hp_wali'] ?? '-') ?></td></tr>
    <?php endif; ?>
  </table>
</div>
<?php else: ?>
<p style="color: gray;">Data orang tua/wali belum diisi.</p>
<?php endif; ?>

<!-- Sekolah Asal -->
<h3>Sekolah Asal</h3>
<div class="table-container">
  <table class="table-detail">
    <tr><th>Nama Sekolah</th><td><?= htmlspecialchars($nama_sekolah_asal) ?></td></tr>
    <tr><th>NPSN</th><td><?= htmlspecialchars($npsn_sekolah_asal) ?></td></tr>
    <tr><th>Alamat Sekolah</th><td><?= htmlspecialchars($alamat_sekolah_asal) ?></td></tr>
  </table>
</div>

<!-- Nilai Rapor -->
<h3>Nilai Rapor</h3>
<div class="table-container">
  <table class="table-detail table-nilai">
    <thead>
      <tr>
        <th>Mapel</th>
        <th>Semester 1</th>
        <th>Semester 2</th>
        <th>Semester 3</th>
        <th>Semester 4</th>
        <th>Semester 5</th>
      </tr>
    </thead>
    <tbody>
    <?php
    if($adaNilai){
        foreach($mapelList as $kode=>$m){
            echo "<tr><td>".htmlspecialchars($m['nama_mapel'])."</td>";
            for($s=1;$s<=5;$s++){
                echo "<td>".htmlspecialchars($nilaiSiswa[$s][$kode] ?? '-')."</td>";
            }
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='6' style='text-align:center;color:gray;'>Belum ada data nilai</td></tr>";
    }
    ?>
    </tbody>
  </table>
</div>

<!-- Dokumen -->
<h3>Dokumen</h3>
<div class="table-container">
  <table class="table-detail">
    <thead><tr><th>Nama Dokumen</th><th>File</th></tr></thead>
    <tbody>
    <?php
    if(!empty($dokData)){
        foreach($dokData as $d){
            echo "<tr>
                <td>".htmlspecialchars($d['nama_dokumen'])."</td>
                <td><a href='../uploads/dokumen/".htmlspecialchars($d['file_path'])."' target='_blank'>Lihat File</a></td>
            </tr>";
        }
    } else {
        echo "<tr><td colspan='3' style='text-align:center;color:gray;'>Belum ada dokumen diunggah</td></tr>";
    }
    ?>
    </tbody>
  </table>
</div>

<!-- Pilihan Sekolah -->
<h3>Pilihan Sekolah</h3>
<div class="table-container">
  <table class="table-detail">
    <thead>
        <tr>
            <th>No</th>
            <th>Nama Sekolah</th>
            <th>Tanggal Daftar</th>
        </tr>
    </thead>
    <tbody>
    <?php
    $no = 1;
    if(!empty($pendaftaranData)){
        foreach($pendaftaranData as $p){
            for($i=1; $i<=3; $i++){
                $sekolah_id = $p['pilihan_ke'.$i] ?? null;
                if(!$sekolah_id) continue;
                $nama_sekolah = $sekolahList[$sekolah_id]['nama_sekolah'] ?? '-';
                $tglDaftar = isset($p['tanggal_daftar']) ? date('d M Y', strtotime($p['tanggal_daftar'])) : '-';
                echo "<tr>
                    <td class='number'>".$no++."</td>
                    <td>".htmlspecialchars($nama_sekolah)."</td>
                    <td>".htmlspecialchars($tglDaftar)."</td>
                </tr>";
            }
        }
    } else {
        echo "<tr><td colspan='4' style='text-align:center;color:gray;'>Belum memilih sekolah</td></tr>";
    }
    ?>
    </tbody>
  </table>
</div>
