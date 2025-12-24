<?php
session_start();
include "../koneksi_mysql.php"; // koneksi ke Firebase

// Cek login dinas
if (!isset($_SESSION['dinas_id'])) {
    header("Location: loginDinas.php");
    exit;
}

$nama_dinas = $_SESSION['nama_dinas'];
$pesan = "";

// SIMPAN MAPEL
if (isset($_POST['simpan_mapel'])) {

    // update mapel lama
    if (!empty($_POST['mapel_id'])) {
        foreach ($_POST['mapel_id'] as $key => $id) {
            $kode = mysqli_real_escape_string($conn, $_POST['mapel_kode'][$key]);
            $nama = mysqli_real_escape_string($conn, $_POST['mapel_nama'][$key]);
            mysqli_query($conn, "UPDATE aturan_mapel SET kode_mapel='$kode', nama_mapel='$nama' WHERE id=$id");
        }
    }

    // tambah mapel baru
    if (!empty($_POST['mapel_baru'])) {
        foreach ($_POST['mapel_baru'] as $key => $nama_baru) {
            $nama_baru = trim($nama_baru);
            if ($nama_baru === '') continue;

            $kode_baru = strtoupper($_POST['kode_baru'][$key]);
            mysqli_query($conn, "INSERT INTO aturan_mapel (kode_mapel,nama_mapel) VALUES ('$kode_baru','$nama_baru')");
        }
    }

    $pesan = "Aturan mapel berhasil disimpan.";
}

// HAPUS MAPEL
if (isset($_POST['hapus_mapel'])) {
    $id = $_POST['hapus_mapel'];
    mysqli_query($conn, "DELETE FROM aturan_mapel WHERE id=$id");
    $pesan = "Mapel berhasil dihapus.";
}


// ------------------------------
// SIMPAN DOKUMEN
// ------------------------------
if (isset($_POST['simpan_dokumen'])) {

    if (!empty($_POST['dokumen_id'])) {
        foreach ($_POST['dokumen_id'] as $index => $id) {
            $nama = mysqli_real_escape_string($conn, $_POST['dokumen_nama'][$id] ?? '');
            $tipe = $_POST['tipe_dokumen'][$id] ?? ''; // <-- default jika tidak ada

            if ($nama === '' || $tipe === '') continue; // lewati jika data tidak lengkap

            mysqli_query($conn, "
                UPDATE aturan_dokumen 
                SET nama_dokumen='$nama', tipe_dokumen='$tipe'
                WHERE id=$id
            ");
        }
    }

    if (!empty($_POST['dokumen_baru'])) {
        foreach ($_POST['dokumen_baru'] as $key => $nama_baru) {
            if ($nama_baru === '') continue;
            $tipe = $_POST['tipe_baru'][$key];
            mysqli_query($conn, "INSERT INTO aturan_dokumen (nama_dokumen,tipe_dokumen) VALUES ('$nama_baru','$tipe')");
        }
    }

    $pesan = "Aturan dokumen berhasil disimpan.";
}

// ------------------------------
// HAPUS DOKUMEN
// ------------------------------
if (isset($_POST['hapus_dokumen'])) {
    $id = $_POST['hapus_dokumen'];
    mysqli_query($conn, "DELETE FROM aturan_dokumen WHERE id=$id");
    $pesan = "Dokumen berhasil dihapus.";
}

// ------------------------------
// SIMPAN TANGGAL PENDAFTARAN
// ------------------------------
if (isset($_POST['simpan_tanggal'])) {
    $tahun_akademik_id = $_POST['tahun_akademik_id'];

    $tanggal_mulai      = $_POST['tanggal_mulai'];
    $tanggal_selesai    = $_POST['tanggal_selesai'];
    $seleksi_1          = $_POST['seleksi_1'];
    $seleksi_2          = $_POST['seleksi_2'];
    $seleksi_3          = $_POST['seleksi_3'];
    $tanggal_pengumuman = $_POST['tanggal_pengumuman'];
    $tanggal_daftar_ulang = $_POST['tanggal_daftar_ulang'];
    $tanggal_mos        = $_POST['tanggal_mos'];
    $tanggal_masuk      = $_POST['tanggal_masuk'];

    // CEK apakah sudah ada data untuk tahun akademik tersebut
    $cek = mysqli_query($conn, "SELECT id FROM aturan_seleksi WHERE tahun_akademik_id=$tahun_akademik_id");

    if (mysqli_num_rows($cek) > 0) {
        // UPDATE
        mysqli_query($conn, "
            UPDATE aturan_seleksi SET 
                tanggal_mulai='$tanggal_mulai',
                tanggal_selesai='$tanggal_selesai',
                seleksi_1='$seleksi_1',
                seleksi_2='$seleksi_2',
                seleksi_3='$seleksi_3',
                tanggal_pengumuman='$tanggal_pengumuman',
                tanggal_daftar_ulang='$tanggal_daftar_ulang',
                tanggal_mos='$tanggal_mos',
                tanggal_masuk='$tanggal_masuk'
            WHERE tahun_akademik_id=$tahun_akademik_id
        ");
    } else {
        // INSERT
        mysqli_query($conn, "
            INSERT INTO aturan_seleksi (
                tahun_akademik_id, tanggal_mulai, tanggal_selesai,
                seleksi_1, seleksi_2, seleksi_3,
                tanggal_pengumuman, tanggal_daftar_ulang,
                tanggal_mos, tanggal_masuk
            )
            VALUES (
                '$tahun_akademik_id','$tanggal_mulai','$tanggal_selesai',
                '$seleksi_1', '$seleksi_2', '$seleksi_3',
                '$tanggal_pengumuman','$tanggal_daftar_ulang',
                '$tanggal_mos','$tanggal_masuk'
            )
        ");
    }

    $pesan = "Aturan seleksi berhasil disimpan.";
}

// ------------------------------
// Ambil data untuk ditampilkan
// ------------------------------
$mapelData = [];
$q1 = mysqli_query($conn,"SELECT * FROM aturan_mapel");
while($r = mysqli_fetch_assoc($q1)) $mapelData[$r['id']] = $r;

$dokumenData = [];
$q2 = mysqli_query($conn,"SELECT * FROM aturan_dokumen");
while($r = mysqli_fetch_assoc($q2)) $dokumenData[$r['id']] = $r;

$tahunData = [];
$q3 = mysqli_query($conn,"SELECT * FROM tahun_akademik");
while($r = mysqli_fetch_assoc($q3)) $tahunData[$r['id']] = $r;

$tahunAktifId = null;

// Cari tahun aktif
foreach($tahunData as $key => $data){
    if(($data['status'] ?? '') === 'aktif'){
        $tahunAktifId = $key;
        break;
    }
}

// Ambil data aturan seleksi (tanggal) sesuai tahun aktif
$aturanData = [];
$q = mysqli_query($conn, "SELECT * FROM aturan_seleksi WHERE tahun_akademik_id=$tahunAktifId");
if (mysqli_num_rows($q) > 0) {
    $aturanData = mysqli_fetch_assoc($q);
}

// ------------------------------
// SIMPAN TANGGAL ZONASI
// ------------------------------
if (isset($_POST['simpan_zonasi'])) {
    $tahun_akademik_id = $_POST['tahun_akademik_id_zonasi'];

    $tanggal_mulai      = $_POST['zonasi_mulai'];
    $tanggal_selesai    = $_POST['zonasi_selesai'];
    $seleksi_1          = $_POST['zonasi_seleksi_1'];
    $seleksi_2          = $_POST['zonasi_seleksi_2'];
    $seleksi_3          = $_POST['zonasi_seleksi_3'];
    $tanggal_pengumuman = $_POST['zonasi_pengumuman'];
    $tanggal_daftar_ulang = $_POST['zonasi_daftar_ulang'];
    $kuota_persen       = $_POST['kuota_persen'];

    $cek = mysqli_query($conn, "SELECT id FROM aturan_zonasi WHERE tahun_akademik_id=$tahun_akademik_id");

    if (mysqli_num_rows($cek) > 0) {
        mysqli_query($conn, "
            UPDATE aturan_zonasi SET
                tanggal_mulai='$tanggal_mulai',
                tanggal_selesai='$tanggal_selesai',
                seleksi_1='$seleksi_1',
                seleksi_2='$seleksi_2',
                seleksi_3='$seleksi_3',
                tanggal_pengumuman='$tanggal_pengumuman',
                tanggal_daftar_ulang='$tanggal_daftar_ulang',
                kuota_persen='$kuota_persen'
            WHERE tahun_akademik_id=$tahun_akademik_id
        ");
    } else {
        mysqli_query($conn, "
            INSERT INTO aturan_zonasi (
                tahun_akademik_id, tanggal_mulai, tanggal_selesai,
                seleksi_1, seleksi_2, seleksi_3,
                tanggal_pengumuman, tanggal_daftar_ulang,
                kuota_persen
            )
            VALUES (
                '$tahun_akademik_id','$tanggal_mulai','$tanggal_selesai',
                '$seleksi_1', '$seleksi_2', '$seleksi_3',
                '$tanggal_pengumuman','$tanggal_daftar_ulang',
                '$kuota_persen'
            )
        ");
    }

    $pesan = "Aturan zonasi berhasil disimpan.";
}

$zonasiData = [];
$qz = mysqli_query($conn, "SELECT * FROM aturan_zonasi WHERE tahun_akademik_id=$tahunAktifId");
if (mysqli_num_rows($qz) > 0) {
    $zonasiData = mysqli_fetch_assoc($qz);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Aturan Seleksi / Pendaftaran Global</title>
    <link rel="stylesheet" href="../css/dashboardDinas.css">
    <link rel="stylesheet" href="../css/aturanSeleksi.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
.slider-container {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
}

#kuotaSlider {
    -webkit-appearance: none;
    width: 200px;
    height: 8px;
    border-radius: 5px;
    background: #ddd;
    outline: none;
    transition: 0.3s;
}

#kuotaSlider::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #4CAF50;
    cursor: pointer;
    box-shadow: 0 0 5px rgba(0,0,0,0.3);
}

#kuotaSlider::-moz-range-thumb {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #4CAF50;
    cursor: pointer;
    box-shadow: 0 0 5px rgba(0,0,0,0.3);
}

#kuotaLabel {
    font-weight: bold;
    min-width: 40px;
    text-align: center;
}
</style>
</head>
<body>
    <?php include("sidebarDinas.php"); ?>

    <div class="main-content">
    <?php include("headerDinas.php"); ?>

    <main>
        <?php if($pesan != ""): ?>
            <p class="notif"><?= htmlspecialchars($pesan); ?></p>
        <?php endif; ?>

        <!-- Form Mapel -->
        <form method="POST">
            <h3>Mapel yang wajib diisi siswa</h3>
            <table class="item-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Kode Mapel</th>
                        <th>Nama Mapel</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="mapelContainer">
                    <?php 
                    $no = 1;
                    foreach($mapelData as $id => $row): ?>
                        <tr>
                            <td><?= $no++; ?></td>
                            <td><input type="text" name="mapel_kode[]" value="<?= htmlspecialchars($row['kode_mapel']); ?>" readonly></td>
                            <td>
                                <input type="hidden" name="mapel_id[]" value="<?= $id; ?>">
                                <input type="text" name="mapel_nama[]" value="<?= htmlspecialchars($row['nama_mapel']); ?>" readonly>
                            </td>
                            <td class="aksi">
                                <button type="button" class="btn-edit" onclick="enableEdit(this)" title="Edit">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <button type="submit" name="hapus_mapel" value="<?= $id; ?>" 
                                        class="btn-delete" 
                                        onclick="return confirm('Yakin ingin menghapus mapel ini?')">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="table-actions">
                <button type="button" onclick="addMapel()"><i class="fa-solid fa-plus"></i> Tambah Mapel</button>
                <button type="submit" name="simpan_mapel"><i class="fa-solid fa-floppy-disk"></i> Simpan Mapel</button>
            </div>
        </form>

        <!-- Form Dokumen -->
        <form method="POST">
            <h3>Dokumen wajib diunggah siswa</h3>
            <table class="item-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Dokumen</th>
                        <th>Tipe Dokumen</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="dokumenContainer">
                    <?php 
                    $no = 1;
                    foreach($dokumenData as $id => $row): ?>
                        <tr>
                            <td><?= $no++; ?></td>
                            <td>
                                <input type="hidden" name="dokumen_id[]" value="<?= $id; ?>">
                                <input type="text" name="dokumen_nama[<?= $id ?>]" value="<?= htmlspecialchars($row['nama_dokumen']); ?>" readonly>
                            </td>
                            <td>
                                <select name="tipe_dokumen[<?= $id ?>]" disabled>
                                    <option value="pdf" <?= ($row['tipe_dokumen'] ?? '') == 'pdf' ? 'selected' : ''; ?>>PDF</option>
                                    <option value="png" <?= ($row['tipe_dokumen'] ?? '') == 'png' ? 'selected' : ''; ?>>PNG</option>
                                </select>
                            </td>
                            <td class="aksi">
                                <button type="button" class="btn-edit" onclick="enableEdit(this)" title="Edit">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <button type="submit" name="hapus_dokumen" value="<?= $id; ?>" 
                                        class="btn-delete" 
                                        onclick="return confirm('Yakin ingin menghapus dokumen ini?')">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button type="button" onclick="addDokumen()"><i class="fa-solid fa-plus"></i> Tambah Dokumen</button>
            <button type="submit" name="simpan_dokumen"><i class="fa-solid fa-floppy-disk"></i> Simpan Dokumen</button>
        </form>

        <!-- Form Jadwal Zonasi -->
        <form method="POST">
            <h3>Atur Jadwal Jalur Zonasi</h3>

            <label>Tahun Akademik</label>
            <input type="text" readonly value="<?= htmlspecialchars($tahunData[$tahunAktifId]['nama_tahun'] ?? ''); ?>">
            <input type="hidden" name="tahun_akademik_id_zonasi" value="<?= $tahunAktifId; ?>">

            <label>Tanggal Mulai Pendaftaran Zonasi</label>
            <input type="date" name="zonasi_mulai" required value="<?= $zonasiData['tanggal_mulai'] ?? ''; ?>">

            <label>Tanggal Selesai Pendaftaran Zonasi</label>
            <input type="date" name="zonasi_selesai" required value="<?= $zonasiData['tanggal_selesai'] ?? ''; ?>">

            <label>Tanggal Seleksi Zonasi 1</label>
            <input type="date" name="zonasi_seleksi_1" value="<?= $zonasiData['seleksi_1'] ?? ''; ?>">

            <label>Tanggal Seleksi Zonasi 2</label>
            <input type="date" name="zonasi_seleksi_2" value="<?= $zonasiData['seleksi_2'] ?? ''; ?>">

            <label>Tanggal Seleksi Zonasi 3</label>
            <input type="date" name="zonasi_seleksi_3" value="<?= $zonasiData['seleksi_3'] ?? ''; ?>">

            <label>Tanggal Pengumuman Zonasi</label>
            <input type="date" name="zonasi_pengumuman" required value="<?= $zonasiData['tanggal_pengumuman'] ?? ''; ?>">

            <label>Tanggal Daftar Ulang Zonasi</label>
            <input type="date" name="zonasi_daftar_ulang" required value="<?= $zonasiData['tanggal_daftar_ulang'] ?? ''; ?>">

            <label>Persentase Kuota Zonasi (%)</label>
            <div class="slider-container">
                <!-- tetap name="kuota_persen" agar PHP bisa menerima nilai -->
                <input type="range" id="kuotaSlider" name="kuota_persen" min="1" max="100" required 
                    value="<?= $zonasiData['kuota_persen'] ?? 50; ?>" oninput="updateKuotaLabel(this.value)">
                <span id="kuotaLabel"><?= $zonasiData['kuota_persen'] ?? 50; ?>%</span>
            </div>

            <button type="submit" name="simpan_zonasi">
                <i class="fa-solid fa-calendar-check"></i> Simpan Jadwal Zonasi
            </button>
        </form>

        <!-- Form Jadwal Pendaftaran & Kegiatan -->
        <form method="POST">
            <h3>Atur Tanggal Pendaftaran dan Kegiatan</h3>

            <label>Tahun Akademik</label>
            <input type="text" readonly value="<?= htmlspecialchars($tahunData[$tahunAktifId]['nama_tahun'] ?? ''); ?>">
            <input type="hidden" name="tahun_akademik_id" value="<?= $tahunAktifId; ?>">

            <label>Tanggal Mulai Pendaftaran</label>
            <input type="date" name="tanggal_mulai" required value="<?= $aturanData['tanggal_mulai'] ?? ''; ?>">

            <label>Tanggal Selesai Pendaftaran</label>
            <input type="date" name="tanggal_selesai" required value="<?= $aturanData['tanggal_selesai'] ?? ''; ?>">

            <label>Tanggal Seleksi 1</label>
            <input type="date" name="seleksi_1" value="<?= $aturanData['seleksi_1'] ?? ''; ?>">

            <label>Tanggal Seleksi 2</label>
            <input type="date" name="seleksi_2" value="<?= $aturanData['seleksi_2'] ?? ''; ?>">

            <label>Tanggal Seleksi 3</label>
            <input type="date" name="seleksi_3" value="<?= $aturanData['seleksi_3'] ?? ''; ?>">

            <label>Tanggal Pengumuman</label>
            <input type="date" name="tanggal_pengumuman" required value="<?= $aturanData['tanggal_pengumuman'] ?? ''; ?>">

            <label>Tanggal Daftar Ulang</label>
            <input type="date" name="tanggal_daftar_ulang" required value="<?= $aturanData['tanggal_daftar_ulang'] ?? ''; ?>">

            <label>Tanggal MOS</label>
            <input type="date" name="tanggal_mos" required value="<?= $aturanData['tanggal_mos'] ?? ''; ?>">

            <label>Tanggal Masuk Sekolah</label>
            <input type="date" name="tanggal_masuk" required value="<?= $aturanData['tanggal_masuk'] ?? ''; ?>">

            <button type="submit" name="simpan_tanggal">
                <i class="fa-solid fa-calendar-check"></i> Simpan Tanggal
            </button>
        </form>
    </main>
</div>

<script>
function enableEdit(btn) {
    let row = btn.closest("tr");
    let inputs = row.querySelectorAll("input");
    let selects = row.querySelectorAll("select");

    inputs.forEach(i => i.removeAttribute("readonly"));
    selects.forEach(s => s.removeAttribute("disabled"));
}

// Tambah Mapel baru
function addMapel() {
    const container = document.getElementById('mapelContainer');
    const row = document.createElement('tr');
    row.innerHTML = `
        <td>+</td>
        <td><input type="text" name="kode_baru[]" placeholder="Kode Mapel (contoh: MTK)"></td>
        <td><input type="text" name="mapel_baru[]" placeholder="Nama Mapel"></td>
    `;
    container.appendChild(row);
}

// Tambah Dokumen baru
function addDokumen() {
    const container = document.getElementById('dokumenContainer');
    const row = document.createElement('tr');
    row.innerHTML = `
        <td>+</td>
        <td><input type="text" name="dokumen_baru[]" placeholder="Nama Dokumen"></td>
        <td>
            <select name="tipe_baru[]">
                <option value="pdf">PDF</option>
                <option value="png">PNG</option>
            </select>
        </td>
    `;
    container.appendChild(row);
}

function updateKuotaLabel(value) {
    document.getElementById('kuotaLabel').textContent = value + '%';
}

</script>
</body>
</html>
