<?php
session_start();
include "../koneksi.php";

// Cek login dinas
if (!isset($_SESSION['dinas_id'])) {
    header("Location: loginDinas.php");
    exit;
}

$nama_dinas = $_SESSION['nama_dinas'];

// --- SIMPAN MAPEL ---
if (isset($_POST['simpan_mapel'])) {
    // Update mapel lama
    if (!empty($_POST['mapel_id'])) {
        foreach ($_POST['mapel_id'] as $key => $id) {
            $nama = trim($_POST['mapel_nama'][$key]);
            $kode = strtoupper(trim($_POST['mapel_kode'][$key]));

            if ($kode == "") {
                // Jika kosong, buat kode otomatis dari 4 huruf pertama
                $kode = strtoupper(substr(preg_replace('/\s+/', '', $nama), 0, 4));
            }

            $stmt = $conn->prepare("UPDATE aturan_mapel SET nama_mapel=?, kode_mapel=? WHERE id=?");
            $stmt->bind_param("ssi", $nama, $kode, $id);
            $stmt->execute();
        }
    }

    // Tambah mapel baru
    if (!empty($_POST['mapel_baru'])) {
        foreach ($_POST['mapel_baru'] as $key => $nama_baru) {
            $nama_baru = trim($nama_baru);
            $kode_baru = strtoupper(trim($_POST['kode_baru'][$key] ?? ''));

            if ($nama_baru === '') continue;
            if ($kode_baru == '') {
                $kode_baru = strtoupper(substr(preg_replace('/\s+/', '', $nama_baru), 0, 4));
            }

            $stmt = $conn->prepare("INSERT INTO aturan_mapel (kode_mapel, nama_mapel, aktif) VALUES (?, ?, 1)");
            $stmt->bind_param("ss", $kode_baru, $nama_baru);
            $stmt->execute();
        }
    }

    $pesan = "Aturan mapel berhasil disimpan.";
}

// --- HAPUS MAPEL ---
if (isset($_POST['hapus_mapel'])) {
    $id = $_POST['hapus_mapel'];
    $stmt = $conn->prepare("DELETE FROM aturan_mapel WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $pesan = "Mapel berhasil dihapus.";
}

// --- SIMPAN DOKUMEN ---
if (isset($_POST['simpan_dokumen'])) {
    // Update dokumen lama
    if (!empty($_POST['dokumen_id'])) {
        foreach ($_POST['dokumen_id'] as $key => $id) {
            $nama = trim($_POST['dokumen_nama'][$key]);
            $tipe = $_POST['tipe_dokumen'][$key];
            $stmt = $conn->prepare("UPDATE aturan_dokumen SET nama_dokumen=?, tipe_dokumen=? WHERE id=?");
            $stmt->bind_param("ssi", $nama, $tipe, $id);
            $stmt->execute();
        }
    }

    // Tambah dokumen baru
    if (!empty($_POST['dokumen_baru'])) {
        foreach ($_POST['dokumen_baru'] as $key => $nama_baru) {
            $tipe_baru = $_POST['tipe_baru'][$key] ?? 'pdf';
            if(trim($nama_baru) === '') continue;
            $stmt = $conn->prepare("INSERT INTO aturan_dokumen (nama_dokumen, tipe_dokumen, wajib) VALUES (?,?,1)");
            $stmt->bind_param("ss", $nama_baru, $tipe_baru);
            $stmt->execute();
        }
    }
    $pesan = "Aturan dokumen berhasil disimpan.";
}

// --- HAPUS DOKUMEN ---
if (isset($_POST['hapus_dokumen'])) {
    $id = $_POST['hapus_dokumen'];
    $stmt = $conn->prepare("DELETE FROM aturan_dokumen WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $pesan = "Dokumen berhasil dihapus.";
}

// --- SIMPAN TANGGAL PENDAFTARAN ---
if (isset($_POST['simpan_tanggal'])) {
    $tahun_akademik_id  = $_POST['tahun_akademik_id'];
    $tanggal_mulai        = $_POST['tanggal_mulai'];
    $tanggal_selesai      = $_POST['tanggal_selesai'];
    $tanggal_pengumuman   = $_POST['tanggal_pengumuman'];
    $tanggal_daftar_ulang = $_POST['tanggal_daftar_ulang'];
    $tanggal_seleksi      = $_POST['tanggal_seleksi'];
    $tanggal_mos          = $_POST['tanggal_mos'];
    $tanggal_masuk        = $_POST['tanggal_masuk'];

    // Cek apakah sudah ada aturan untuk tahun ini
    $cek = $conn->prepare("SELECT id FROM aturan_seleksi WHERE tahun_akademik_id=? LIMIT 1");
    $cek->bind_param("i", $tahun_akademik_id);
    $cek->execute();
    $hasil = $cek->get_result();

    if ($hasil->num_rows > 0) {
        $id = $hasil->fetch_assoc()['id'];
        $stmt = $conn->prepare("UPDATE aturan_seleksi 
            SET tanggal_mulai=?, tanggal_selesai=?, tanggal_pengumuman=?, 
                tanggal_daftar_ulang=?, tanggal_seleksi=?, tanggal_mos=?, tanggal_masuk=?, 
                tgl_update=NOW() 
            WHERE id=?");
        $stmt->bind_param("sssssssi", 
            $tanggal_mulai, $tanggal_selesai, $tanggal_pengumuman, 
            $tanggal_daftar_ulang, $tanggal_seleksi, $tanggal_mos, $tanggal_masuk, $id);
    } else {
        $stmt = $conn->prepare("INSERT INTO aturan_seleksi 
            (tahun_akademik_id, nama_pendaftaran, tanggal_mulai, tanggal_selesai, tanggal_pengumuman, 
             tanggal_daftar_ulang, tanggal_seleksi, tanggal_mos, tanggal_masuk) 
            VALUES (?, 'PPDB Global', ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssssss", 
            $tahun_akademik_id, $tanggal_mulai, $tanggal_selesai, $tanggal_pengumuman, 
            $tanggal_daftar_ulang, $tanggal_seleksi, $tanggal_mos, $tanggal_masuk);
    }
    $stmt->execute();
    $pesan = "Tanggal pendaftaran berhasil disimpan untuk tahun akademik yang dipilih.";
}

// --- Ambil data untuk ditampilkan ---
$aturan = $conn->query("SELECT * FROM aturan_seleksi ORDER BY id DESC LIMIT 1")->fetch_assoc();

// Ambil daftar tahun akademik
$tahunList = $conn->query("SELECT * FROM tahun_akademik ORDER BY id DESC");

// Ambil tahun akademik aktif
$tahunAktif = $conn->query("SELECT id FROM tahun_akademik WHERE status='aktif' LIMIT 1")->fetch_assoc();
$tahunAktifId = $tahunAktif['id'] ?? null;

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Aturan Seleksi / Pendaftaran Global</title>
    <link rel="stylesheet" href="../css/dashboardDinas.css">
    <link rel="stylesheet" href="../css/aturanSeleksi.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<aside class="sidebar">
    <h2>Dinas</h2>
    <ul>
        <li><a href="dashboardDinas.php"><i class="fa-solid fa-gauge"></i> Dashboard</a></li>
        <li><a href="buatAkunSekolah.php"><i class="fa-solid fa-user-plus"></i> Buat Akun Sekolah</a></li>
        <li><a href="kelolaSekolah.php" class="active"><i class="fa-solid fa-school"></i> Kelola Sekolah</a></li>
        <li><a href="kelolaTahunAkademik.php"><i class="fa-solid fa-calendar-days"></i> Tahun Akademik</a></li>
        <li><a href="aturanSeleksi.php"><i class="fa-solid fa-scale-balanced"></i> Aturan Seleksi</a></li>
        <li><a href="kelolaPendaftaranDinas.php"><i class="fa-solid fa-users"></i> Kelola Pendaftaran</a></li>
        <li><a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
    </ul>
</aside>

<div class="main-content">
    <header>
      <h1>Dashboard Dinas Pendidikan</h1>
      <div class="user-info">
        <span><i class="fa-solid fa-user-tie"></i> <?php echo htmlspecialchars($nama_dinas); ?></span>
      </div>
    </header>

    <main>
        <?php if(isset($pesan)) echo "<p class='notif'>$pesan</p>"; ?>

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
                    $res = $conn->query("SELECT * FROM aturan_mapel ORDER BY id ASC");
                    $no = 1;
                    while($row = $res->fetch_assoc()): ?>
                        <tr>
                            <td><?= $no++; ?></td>
                            <td><input type="text" name="mapel_kode[]" value="<?= htmlspecialchars($row['kode_mapel']); ?>" readonly></td>
                            <td>
                                <input type="hidden" name="mapel_id[]" value="<?= $row['id']; ?>">
                                <input type="text" name="mapel_nama[]" value="<?= htmlspecialchars($row['nama_mapel']); ?>" readonly>
                            </td>
                            <td class="aksi">
                                <button type="button" class="btn-edit" onclick="enableEdit(this)" title="Edit">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <button type="submit" name="hapus_mapel" value="<?= $row['id']; ?>" 
                                        class="btn-delete" 
                                        onclick="return confirm('Yakin ingin menghapus mapel ini?')">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
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
                    $res = $conn->query("SELECT * FROM aturan_dokumen ORDER BY id ASC");
                    $no = 1;
                    while($row = $res->fetch_assoc()): ?>
                        <tr>
                            <td><?= $no++; ?></td>
                            <td>
                                <input type="hidden" name="dokumen_id[]" value="<?= $row['id']; ?>">
                                <input type="text" name="dokumen_nama[]" value="<?= htmlspecialchars($row['nama_dokumen']); ?>" readonly>
                            </td>
                            <td>
                                <select name="tipe_dokumen[]" disabled>
                                    <option value="pdf" <?= $row['tipe_dokumen']=='pdf'?'selected':''; ?>>PDF</option>
                                    <option value="png" <?= $row['tipe_dokumen']=='png'?'selected':''; ?>>PNG</option>
                                    <option value="jpg" <?= $row['tipe_dokumen']=='jpg'?'selected':''; ?>>JPG</option>
                                </select>
                            </td>
                            <td class="aksi">
                                <button type="button" class="btn-edit" onclick="enableEdit(this)" title="Edit">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <button type="submit" name="hapus_dokumen" value="<?= $row['id']; ?>" 
                                        class="btn-delete" 
                                        onclick="return confirm('Yakin ingin menghapus dokumen ini?')">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <button type="button" onclick="addDokumen()"><i class="fa-solid fa-plus"></i> Tambah Dokumen</button>
            <button type="submit" name="simpan_dokumen"><i class="fa-solid fa-floppy-disk"></i> Simpan Dokumen</button>
        </form>

        <!-- Form Tahun -->
        <form method="POST">
            <h3>Atur Tanggal Pendaftaran dan Kegiatan</h3>

            <label>Tahun Akademik</label>
            <select name="tahun_akademik_id" required>
                <option value="">-- Pilih Tahun Akademik --</option>
                <?php while ($t = $tahunList->fetch_assoc()): ?>
                    <option value="<?= $t['id']; ?>" <?= ($t['id']==$tahunAktifId)?'selected':''; ?>>
                        <?= htmlspecialchars($t['nama_tahun']); ?>
                        <?= $t['status']=='aktif'?'⭐':''; ?>
                    </option>
                <?php endwhile; ?>
            </select>

        <!-- Form Tanggal -->
        <form method="POST">
        <h3>Atur Tanggal Pendaftaran dan Kegiatan</h3>

        <label>Tanggal Mulai Pendaftaran</label>
        <input type="date" name="tanggal_mulai" required value="<?= $aturan['tanggal_mulai'] ?? ''; ?>">

        <label>Tanggal Selesai Pendaftaran</label>
        <input type="date" name="tanggal_selesai" required value="<?= $aturan['tanggal_selesai'] ?? ''; ?>">

        <label>Tanggal Seleksi</label>
        <input type="date" name="tanggal_seleksi" required value="<?= $aturan['tanggal_seleksi'] ?? ''; ?>">

        <label>Tanggal Pengumuman</label>
        <input type="date" name="tanggal_pengumuman" required value="<?= $aturan['tanggal_pengumuman'] ?? ''; ?>">

        <label>Tanggal Daftar Ulang</label>
        <input type="date" name="tanggal_daftar_ulang" required value="<?= $aturan['tanggal_daftar_ulang'] ?? ''; ?>">

        <label>Tanggal MOS</label>
        <input type="date" name="tanggal_mos" required value="<?= $aturan['tanggal_mos'] ?? ''; ?>">

        <label>Tanggal Masuk Sekolah</label>
        <input type="date" name="tanggal_masuk" required value="<?= $aturan['tanggal_masuk'] ?? ''; ?>">

        <button type="submit" name="simpan_tanggal">
            <i class="fa-solid fa-calendar-check"></i> Simpan Tanggal
        </button>
        </form>
    </main>
</div>

<script>
function enableEdit(btn){
    const row = btn.closest('tr');
    row.querySelectorAll('input[type="text"], select').forEach(el => el.removeAttribute('readonly'));
    row.querySelectorAll('select').forEach(sel => sel.removeAttribute('disabled'));
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
                <option value="jpg">JPG</option>
            </select>
        </td>
    `;
    container.appendChild(row);
}

</script>
</body>
</html>

