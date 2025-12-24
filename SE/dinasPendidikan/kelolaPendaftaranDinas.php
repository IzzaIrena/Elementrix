<?php
session_start();
include "../koneksi_mysql.php"; // koneksi MySQL ($conn)

// ============================
// CEK LOGIN
// ============================
if (!isset($_SESSION['dinas_id'])) {
    header("Location: loginDinas.php");
    exit;
}

$nama_dinas = $_SESSION['nama_dinas'];

// ============================
// AMBIL DATA TAHUN AKADEMIK
// ============================
$tahun_id = isset($_GET['tahun_id']) ? (int)$_GET['tahun_id'] : 0;

$tahunList = [];
$tahun_aktif_id = null;

$query = $conn->query("SELECT * FROM tahun_akademik ORDER BY id DESC");
while ($row = $query->fetch_assoc()) {
    $tahunList[$row['id']] = $row;

    if ($row['status'] === 'aktif') {
        $tahun_aktif_id = $row['id'];
    }
}

// ============================
// LOGIKA PEMILIHAN TAHUN (BENAR)
// ============================

// jika user BELUM memilih tahun (pertama kali buka halaman)
if (!isset($_GET['tahun_id'])) {
    $tahun_id = $tahun_aktif_id;
}

// jika user memilih tahun tapi ID tidak valid
elseif (!isset($tahunList[$tahun_id])) {
    die("<h3 style='color:red;text-align:center;margin-top:50px;'>
        ⚠️ Tahun akademik tidak valid!
    </h3>");
}

// jika tidak ada tahun aktif sama sekali
if (!$tahun_id) {
    die("<h3 style='color:red;text-align:center;margin-top:50px;'>
        ⚠️ Belum ada tahun akademik aktif!
    </h3>");
}

$nama_tahun_aktif = $tahunList[$tahun_id]['nama_tahun'] ?? '-';

// ============================
// AMBIL DATA PENDAFTARAN
// ============================
$pendaftaranTahun = [];
$sql = "SELECT 
    p.*,
    s.nama_lengkap AS nama_siswa, 
    s.nisn, s.tempat_lahir, s.tanggal_lahir, s.jk,
    sk1.nama_sekolah AS pilihan1,
    sk2.nama_sekolah AS pilihan2,
    sk3.nama_sekolah AS pilihan3,
    skd.nama_sekolah AS sekolah_diterima_nama
  FROM pendaftaran p
  LEFT JOIN siswa s ON p.siswa_id = s.id
  LEFT JOIN sekolah sk1 ON p.pilihan_ke1 = sk1.id
  LEFT JOIN sekolah sk2 ON p.pilihan_ke2 = sk2.id
  LEFT JOIN sekolah sk3 ON p.pilihan_ke3 = sk3.id
  LEFT JOIN sekolah skd ON p.sekolah_diterima = skd.id
  WHERE p.tahun_id = ?
  ORDER BY sk1.nama_sekolah, s.nama_lengkap";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $tahun_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    // ========================================
    // Ambil tanggal pengumuman (zonasi / seleksi)
    // ========================================
    $pengumuman = null;

    // Cek jalur zonasi dulu
    $qZ = $conn->query("SELECT tanggal_pengumuman FROM aturan_zonasi 
                        WHERE tahun_akademik_id = {$tahun_id} LIMIT 1");
    if ($qZ && $qZ->num_rows) {
        $pengumuman = $qZ->fetch_assoc()['tanggal_pengumuman'];
    }

    // Jika jalur akademik
    if (!$pengumuman) {
        $qA = $conn->query("SELECT tanggal_pengumuman FROM aturan_seleksi 
                            WHERE tahun_akademik_id = {$tahun_id} LIMIT 1");
        if ($qA && $qA->num_rows) {
            $pengumuman = $qA->fetch_assoc()['tanggal_pengumuman'];
        }
    }

    $today = date("Y-m-d");

    // ========================================
    // Tentukan hasil seleksi
    // ========================================
    $hasil = "Sedang Diproses";

    // Jika sudah lulus di salah satu pilihan
    if (!empty($row['sekolah_diterima']) && !empty($row['sekolah_diterima_nama'])) {
        $hasil = "Lulus di " . htmlspecialchars($row['sekolah_diterima_nama']);
    }

    // Jika tanggal pengumuman lewat dan belum diterima → TIDAK LULUS
    elseif ($today > $pengumuman && !$row['sekolah_diterima']) {
        $hasil = "Tidak Lulus";
    }
    
    $pendaftaranTahun[] = [
        'id' => $row['id'],
        'nama' => $row['nama_siswa'],
        'nisn' => $row['nisn'],
        'tempat_lahir' => $row['tempat_lahir'],
        'tanggal_lahir' => $row['tanggal_lahir'],
        'jenis_kelamin' => $row['jk'],
        'sekolah_pilihan1' => $row['pilihan1'],
        'sekolah_pilihan2' => $row['pilihan2'],
        'sekolah_pilihan3' => $row['pilihan3'],
        'hasil_seleksi' => $hasil
    ];
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Kelola Pendaftaran | Dinas Pendidikan</title>
<link rel="stylesheet" href="../css/dashboardDinas.css">
<link rel="stylesheet" href="../css/kelolaPendaftaranDinas.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>
  <?php include("sidebarDinas.php"); ?>

  <div class="main-content">
    <?php include("headerDinas.php"); ?>

    <div class="table-container">
      <form method="get" class="filter-form">
        <label for="tahun_id"><b>Pilih Tahun Akademik:</b></label>
        <select name="tahun_id" id="tahun_id" onchange="this.form.submit()">
          <?php foreach ($tahunList as $key => $t): ?>
            <option value="<?= $key ?>" <?= ($key == $tahun_id ? 'selected' : '') ?>>
              <?= htmlspecialchars($t['nama_tahun'] ?? '-') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </form>
<p style="margin-top:10px;color:#555">
Menampilkan data pendaftaran tahun akademik:
<b><?= htmlspecialchars($nama_tahun_aktif) ?></b>
</p>

      <h2>Data Pendaftaran Siswa</h2>
      <?php if (!empty($pendaftaranTahun)): ?>
      <table class="table-data">
        <thead>
          <tr>
            <th>No</th>
            <th>Nama Siswa</th>
            <th>NISN</th>
            <th>TTL</th>
            <th>Jenis Kelamin</th>
            <th>Sekolah Pilihan</th>
            <th>Hasil Seleksi</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php 
        $no = 1;
        foreach ($pendaftaranTahun as $p): 
        ?>
          <tr>
            <td><?= $no++ ?></td>
            <td><?= htmlspecialchars($p['nama']) ?></td>
            <td><?= htmlspecialchars($p['nisn']) ?></td>
            <td><?= htmlspecialchars($p['tempat_lahir']) ?>, <?= htmlspecialchars($p['tanggal_lahir']) ?></td>
            <td>      
              <?= $p['jenis_kelamin'] === 'P' ? 'Perempuan' : ($p['jenis_kelamin'] === 'L' ? 'Laki-laki' : '-') ?>
            </td>
            <td>
              <?php
                $sekolah = array_filter([
                    $p['sekolah_pilihan1'],
                    $p['sekolah_pilihan2'],
                    $p['sekolah_pilihan3']
                ]);
                echo $sekolah ? implode("<br>", array_map('htmlspecialchars', $sekolah)) : '-';
              ?>
            </td>
            <td><?= htmlspecialchars($p['hasil_seleksi']) ?></td>
            <td>
              <a href="detailPendaftaran.php?id=<?= urlencode($p['id']) ?>" class="btn-detail">Detail</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
        <p style="text-align:center;color:gray;">Belum ada data pendaftaran pada tahun akademik ini.</p>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
