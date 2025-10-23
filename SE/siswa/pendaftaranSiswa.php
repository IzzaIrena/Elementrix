<?php
session_start();
include("../koneksi.php");

// Ambil tahun akademik aktif
$qTahun = $conn->query("SELECT id, nama_tahun FROM tahun_akademik WHERE status='aktif' LIMIT 1");
$tahunAktif = $qTahun->fetch_assoc();

if (!$tahunAktif) {
    die("<h3 style='color:red;text-align:center;margin-top:50px;'>⚠️ Belum ada tahun akademik aktif! Silakan hubungi admin dinas.</h3>");
}

$tahun_id = $tahunAktif['id'];
$nama_tahun_aktif = $tahunAktif['nama_tahun'];

// Cek login siswa
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'siswa') {
    header("Location: loginSiswa.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Ambil data siswa
$sql = "SELECT s.id AS siswa_id, s.nama_lengkap, s.nisn, s.nik, s.alamat, 
               s.no_hp, s.tempat_lahir, s.tanggal_lahir, s.jenis_kelamin
        FROM siswa s
        JOIN user u ON u.id = s.user_id
        WHERE u.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $siswa_id     = $row['siswa_id'];
    $nama_lengkap = $row['nama_lengkap'];
    $nisn         = $row['nisn'];
    $nik          = $row['nik'];
    $alamat       = $row['alamat'];
    $no_hp        = $row['no_hp'];
    $tempat_lahir = $row['tempat_lahir'];
    $tgl_lahir    = $row['tanggal_lahir'];
    $jk           = $row['jenis_kelamin'];
} else {
    die("Data siswa tidak ditemukan.");
}

// Ambil data ortu/wali
$queryOrtu = $conn->prepare("SELECT * FROM ortu_wali WHERE siswa_id = ?");
$queryOrtu->bind_param("i", $siswa_id);
$queryOrtu->execute();
$dataOrtu = $queryOrtu->get_result()->fetch_assoc();

// Ambil data sekolah asal
$qSekolahAsal = $conn->prepare("SELECT * FROM sekolah_asal WHERE siswa_id=?");
$qSekolahAsal->bind_param("i", $siswa_id);
$qSekolahAsal->execute();
$rSekolahAsal = $qSekolahAsal->get_result()->fetch_assoc();
$nama_sekolah_asal  = $rSekolahAsal['nama_sekolah_asal'] ?? '';
$npsn_sekolah_asal  = $rSekolahAsal['npsn_sekolah_asal'] ?? '';
$alamat_sekolah_asal = $rSekolahAsal['alamat_sekolah_asal'] ?? '';

// =======================
// CEK STATUS PENDAFTARAN
// =======================
$cekDaftar = $conn->prepare("SELECT COUNT(*) AS jml FROM pendaftaran WHERE siswa_id=? AND tahun_id=?");
$cekDaftar->bind_param("ii", $siswa_id, $tahun_id);
$cekDaftar->execute();
$res = $cekDaftar->get_result()->fetch_assoc();
$sudahDaftar = $res['jml'] > 0;

// Ambil tanggal_selesai pendaftaran
$qTanggal = $conn->query("SELECT tanggal_selesai FROM aturan_seleksi LIMIT 1"); // asumsikan 1 baris untuk pengaturan pendaftaran
$tglData = $qTanggal->fetch_assoc();
$tanggal_selesai = $tglData['tanggal_selesai'] ?? null;

// Cek apakah pendaftaran sudah lewat
$pendaftaran_tutup = false;
if ($tanggal_selesai && strtotime(date("Y-m-d")) > strtotime($tanggal_selesai)) {
    $pendaftaran_tutup = true;
}

// Ambil daftar sekolah
$sekolahList = [];
$res = $conn->query("SELECT id, nama_sekolah FROM sekolah ORDER BY nama_sekolah ASC");
while($row = $res->fetch_assoc()){
    $sekolahList[] = $row;
}
$res->close();

// Ambil aturan mapel & dokumen
$mapelList = [];
$res = $conn->query("SELECT * FROM aturan_mapel ORDER BY id ASC");
while($row = $res->fetch_assoc()){
    $mapelList[] = $row;
}
$res->close();

$dokumenList = [];
$res = $conn->query("SELECT * FROM aturan_dokumen ORDER BY id ASC");
while($row = $res->fetch_assoc()){
    $dokumenList[] = $row['nama_dokumen'];
}
$res->close();

// =======================
// PROSES PENDAFTARAN
// =======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$sudahDaftar) {

    // --- Data Pribadi ---
    $nama = trim($_POST['nama']);
    $nisn = trim($_POST['nisn']);
    $nik = trim($_POST['nik']);
    $alamat = trim($_POST['alamat']);
    $no_hp = trim($_POST['no_hp']);
    $tempat_lahir = trim($_POST['tempat_lahir']);
    $tgl_lahir = trim($_POST['tgl_lahir']);
    $jk = trim($_POST['jk']);

    $stmt = $conn->prepare("UPDATE siswa SET 
        nama_lengkap = ?, nisn = ?, nik = ?, alamat = ?, 
        no_hp = ?, tempat_lahir = ?, tanggal_lahir = ?, jenis_kelamin = ?
        WHERE id = ?");
    $stmt->bind_param("ssssssssi", 
        $nama, $nisn, $nik, $alamat, $no_hp, $tempat_lahir, $tgl_lahir, $jk, $siswa_id);
    $stmt->execute();
    $stmt->close();

    // --- Sekolah Asal ---
    $nama_sekolah_asal = trim($_POST['nama_sekolah_asal']);
    $npsn_sekolah_asal = trim($_POST['npsn_sekolah_asal']);
    $alamat_sekolah_asal = trim($_POST['alamat_sekolah_asal']);

    $cek = $conn->prepare("SELECT id FROM sekolah_asal WHERE siswa_id=?");
    $cek->bind_param("i", $siswa_id);
    $cek->execute();
    $hasil = $cek->get_result();
    if ($hasil->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE sekolah_asal SET 
            nama_sekolah_asal=?, npsn_sekolah_asal=?, alamat_sekolah_asal=? WHERE siswa_id=?");
        $stmt->bind_param("sssi", $nama_sekolah_asal, $npsn_sekolah_asal, $alamat_sekolah_asal, $siswa_id);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("INSERT INTO sekolah_asal (siswa_id, nama_sekolah_asal, npsn_sekolah_asal, alamat_sekolah_asal) 
                                VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $siswa_id, $nama_sekolah_asal, $npsn_sekolah_asal, $alamat_sekolah_asal);
        $stmt->execute();
    }
    $stmt->close();

    // --- Data Orang Tua & Wali ---
    $nama_ayah = trim($_POST['nama_ayah'] ?? '');
    $no_hp_ayah = trim($_POST['no_hp_ayah'] ?? '');
    $nama_ibu = trim($_POST['nama_ibu'] ?? '');
    $no_hp_ibu = trim($_POST['no_hp_ibu'] ?? '');
    $nama_wali = trim($_POST['nama_wali'] ?? '');
    $no_hp_wali = trim($_POST['no_hp_wali'] ?? '');

    $cekOrtu = $conn->prepare("SELECT id FROM ortu_wali WHERE siswa_id=?");
    $cekOrtu->bind_param("i", $siswa_id);
    $cekOrtu->execute();
    $resOrtu = $cekOrtu->get_result();

    if ($resOrtu->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE ortu_wali SET 
            nama_ayah=?, no_hp_ayah=?, nama_ibu=?, no_hp_ibu=?, nama_wali=?, no_hp_wali=? 
            WHERE siswa_id=?");
        $stmt->bind_param("ssssssi", $nama_ayah, $no_hp_ayah, $nama_ibu, $no_hp_ibu, $nama_wali, $no_hp_wali, $siswa_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO ortu_wali 
            (siswa_id, nama_ayah, no_hp_ayah, nama_ibu, no_hp_ibu, nama_wali, no_hp_wali)
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssss", $siswa_id, $nama_ayah, $no_hp_ayah, $nama_ibu, $no_hp_ibu, $nama_wali, $no_hp_wali);
    }
    $stmt->execute();
    $stmt->close();

    // --- Data Orang Tua & Pilihan Sekolah ---
    $pilihan = $_POST['sekolah'] ?? [];

    $pilihan = array_filter($pilihan);
    if (count($pilihan) == 0 || count($pilihan) > 3) {
        $error = "Pilih minimal 1 dan maksimal 3 sekolah.";
    } else {
        // Simpan data pendaftaran
        $stmt = $conn->prepare("INSERT INTO pendaftaran (siswa_id, tahun_id, sekolah_id, tanggal_daftar, status) VALUES (?, ?, ?, NOW(), 'pending')");
        foreach($pilihan as $idSekolah){
            $stmt->bind_param("iii", $siswa_id, $tahun_id, $idSekolah);
            $stmt->execute();
        }
        $stmt->close();

        // Simpan nilai rapor
        foreach ($_POST['nilai'] as $semester => $mapels) {
            foreach ($mapels as $mapel_id => $nilai) {
                $q = $conn->prepare("SELECT nama_mapel FROM aturan_mapel WHERE id=?");
                $q->bind_param("i", $mapel_id);
                $q->execute();
                $res = $q->get_result();
                $nama_mapel = $res->fetch_assoc()['nama_mapel'] ?? null;

                if ($nama_mapel) {
                  // Pastikan hanya satu baris per mapel
                  $qCek = $conn->prepare("SELECT id FROM nilai_akademik WHERE siswa_id=? AND mapel=?");
                  $qCek->bind_param("is", $siswa_id, $nama_mapel);
                  $qCek->execute();
                  $rCek = $qCek->get_result();

                  if ($rCek->num_rows > 0) {
                      // Sudah ada → update kolom semester yang sesuai
                      $stmt = $conn->prepare("UPDATE nilai_akademik SET semester_$semester=? WHERE siswa_id=? AND mapel=?");
                      $stmt->bind_param("iis", $nilai, $siswa_id, $nama_mapel);
                      $stmt->execute();
                  } else {
                      // Belum ada → buat baris baru
                      $stmt = $conn->prepare("INSERT INTO nilai_akademik (siswa_id, mapel, semester_$semester) VALUES (?, ?, ?)");
                      $stmt->bind_param("isi", $siswa_id, $nama_mapel, $nilai);
                      $stmt->execute();
                  }
                }
            }
        }

        // Upload dokumen
        $uploadDir = "../uploads/dokumen/";

        foreach($dokumenList as $d) {
            if (isset($_FILES['dokumen']['name'][$d]) && $_FILES['dokumen']['error'][$d] == 0) {
                $filename = time() . "_" . basename($_FILES['dokumen']['name'][$d]);
                $targetPath = $uploadDir . $filename;

                // Ambil tipe dokumen dari aturan
                $tipeRes = $conn->prepare("SELECT tipe_dokumen FROM aturan_dokumen WHERE nama_dokumen = ?");
                $tipeRes->bind_param("s", $d);
                $tipeRes->execute();
                $tipeRow = $tipeRes->get_result()->fetch_assoc();
                $tipeDiperbolehkan = strtolower($tipeRow['tipe_dokumen']);

                // Ambil ekstensi file yang diupload
                $fileExt = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                // Validasi tipe file
                $boleh = false;
                if ($tipeDiperbolehkan == 'pdf' && $fileExt == 'pdf') $boleh = true;
                if (in_array($tipeDiperbolehkan, ['png','jpg','jpeg']) && in_array($fileExt, ['png','jpg','jpeg'])) $boleh = true;

                if (!$boleh) {
                    echo "<p style='color:red;'>Tipe file untuk dokumen $d tidak sesuai. Hanya diperbolehkan: $tipeDiperbolehkan</p>";
                    continue; // lewati upload ini
                }

                // Jika sesuai, simpan
                if (move_uploaded_file($_FILES['dokumen']['tmp_name'][$d], $targetPath)) {
                    $stmt = $conn->prepare("INSERT INTO dokumen_siswa (siswa_id, nama_dokumen, file_path, status) VALUES (?,?,?, 'pending')");
                    $stmt->bind_param("iss", $siswa_id, $d, $filename);
                    $stmt->execute();
                }
            }
        }

        $success = "Pendaftaran berhasil disimpan.";
        $sudahDaftar = true;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Pendaftaran Siswa</title>
  <link rel="stylesheet" href="../css/dashboardSiswa.css">
  <link rel="stylesheet" href="../css/pendaftaranSiswa.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    .btn-detail {background:#007bff;color:white;padding:10px 15px;border:none;border-radius:6px;cursor:pointer;}
    .btn-detail:hover {background:#0056b3;}
    .table-detail {border-collapse: collapse; width: 100%; margin-top: 15px;}
    .table-detail td, .table-detail th {border:1px solid #ddd;padding:8px;}
  </style>
</head>
<body>
<header>
  <div class="logo"><i class="fa-solid fa-graduation-cap"></i> PPDB</div>
  <div class="user-info">
    <span><i class="fa-solid fa-user-circle"></i> Halo, <b><?php echo htmlspecialchars($nama_lengkap); ?></b></span>
  </div>
</header>

<div class="sidebar">
  <ul>
    <li><a href="dashboardSiswa.php"><i class="fa-solid fa-gauge"></i> Dashboard</a></li>
    <li><a href="profilSiswa.php"><i class="fa-solid fa-user"></i> Profil</a></li>
    <li><a class="active" href="pendaftaranSiswa.php"><i class="fa-solid fa-file-pen"></i> Pendaftaran</a></li>
    <li><a href="prediksiMapel.php"><i class="fa-solid fa-book"></i> Prediksi Mapel</a></li>
    <li><a href="daftarUlang.php"><i class="fa-solid fa-clipboard-check"></i> Daftar Ulang</a></li>
  </ul>
</div>

<div class="main">
  <h2><i class="fa-solid fa-file-pen"></i> Formulir Pendaftaran</h2>
  <h3 style="text-align:center;">Tahun Akademik Aktif: <span style="color:#007bff;"><?php echo htmlspecialchars($nama_tahun_aktif); ?></span></h3>

  <?php if(isset($success)): ?><div class="alert-section" style="background:#e0ffe0;"><?php echo $success; ?></div><?php endif; ?>
  <?php if(isset($error)): ?><div class="alert-section" style="background:#ffe0e0;"><?php echo $error; ?></div><?php endif; ?>

  <div class="form-container">
    <?php if ($sudahDaftar): ?>
      <!-- DATA PENDAFTARAN -->
      <h2>Data Pendaftaran Siswa</h2>

      <h3>Data Pribadi</h3>
      <table class="table-detail">
        <tr><th>Nama Lengkap</th><td><?= htmlspecialchars($nama_lengkap) ?></td></tr>
        <tr><th>NISN</th><td><?= htmlspecialchars($nisn) ?></td></tr>
        <tr><th>NIK</th><td><?= htmlspecialchars($nik) ?></td></tr>
        <tr><th>Alamat</th><td><?= htmlspecialchars($alamat) ?></td></tr>
        <tr><th>No HP</th><td><?= htmlspecialchars($no_hp) ?></td></tr>
        <tr><th>Tempat, Tanggal Lahir</th><td><?= htmlspecialchars($tempat_lahir) ?>, <?= htmlspecialchars($tgl_lahir) ?></td></tr>
        <tr><th>Jenis Kelamin</th><td><?= ($jk == 'L' ? 'Laki-laki' : 'Perempuan') ?></td></tr>
      </table>

      <?php if ($dataOrtu): ?>
        <h3>Data Orang Tua / Wali</h3>
        <table class="table-detail">
          <tr><th>Nama Ayah</th><td><?= htmlspecialchars($dataOrtu['nama_ayah']) ?></td></tr>
          <tr><th>No HP Ayah</th><td><?= htmlspecialchars($dataOrtu['no_hp_ayah']) ?></td></tr>
          <tr><th>Nama Ibu</th><td><?= htmlspecialchars($dataOrtu['nama_ibu']) ?></td></tr>
          <tr><th>No HP Ibu</th><td><?= htmlspecialchars($dataOrtu['no_hp_ibu']) ?></td></tr>
          <?php if (!empty($dataOrtu['nama_wali'])): ?>
            <tr><th>Nama Wali</th><td><?= htmlspecialchars($dataOrtu['nama_wali']) ?></td></tr>
            <tr><th>No HP Wali</th><td><?= htmlspecialchars($dataOrtu['no_hp_wali']) ?></td></tr>
          <?php endif; ?>
        </table>
      <?php else: ?>
        <p style="color: gray;">Data orang tua/wali belum diisi.</p>
      <?php endif; ?>

      <h3>Sekolah Asal</h3>
      <table class="table-detail">
        <tr><th>Nama Sekolah</th><td><?= htmlspecialchars($nama_sekolah_asal) ?></td></tr>
        <tr><th>NPSN</th><td><?= htmlspecialchars($npsn_sekolah_asal) ?></td></tr>
        <tr><th>Alamat Sekolah</th><td><?= htmlspecialchars($alamat_sekolah_asal) ?></td></tr>
      </table>

      <h3>Nilai Akademik</h3>
      <table class="table-detail">
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
          $nilai = $conn->prepare("SELECT * FROM nilai_akademik WHERE siswa_id=? ORDER BY id ASC");
          $nilai->bind_param("i", $siswa_id);
          $nilai->execute();
          $hasilNilai = $nilai->get_result();
          while($n = $hasilNilai->fetch_assoc()):
          ?>
            <tr>
              <td><?= htmlspecialchars($n['mapel']) ?></td>
              <td><?= $n['semester_1'] ?? '-' ?></td>
              <td><?= $n['semester_2'] ?? '-' ?></td>
              <td><?= $n['semester_3'] ?? '-' ?></td>
              <td><?= $n['semester_4'] ?? '-' ?></td>
              <td><?= $n['semester_5'] ?? '-' ?></td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>

      <h3>Dokumen</h3>
      <table class="table-detail">
        <thead>
          <tr>
            <th>Nama Dokumen</th>
            <th>File</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $docs = $conn->prepare("SELECT * FROM dokumen_siswa WHERE siswa_id=?");
          $docs->bind_param("i", $siswa_id);
          $docs->execute();
          $resDocs = $docs->get_result();
          while($d = $resDocs->fetch_assoc()):
          ?>
            <tr>
              <td><?= htmlspecialchars($d['nama_dokumen']) ?></td>
              <td><a href="../uploads/dokumen/<?= htmlspecialchars($d['file_path']) ?>" target="_blank">Lihat File</a></td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>

      <h3>Pilihan Sekolah</h3>
      <table class="table-detail">
        <thead><tr><th>No</th><th>Nama Sekolah</th></tr></thead>
        <tbody>
          <?php
          $qPilihan = $conn->prepare("SELECT p.id, s.nama_sekolah, p.status 
                                      FROM pendaftaran p 
                                      JOIN sekolah s ON s.id=p.sekolah_id
                                      WHERE p.siswa_id=?");
          $qPilihan->bind_param("i", $siswa_id);
          $qPilihan->execute();
          $hasilPilihan = $qPilihan->get_result();
          $no = 1;
          while($p = $hasilPilihan->fetch_assoc()):
          ?>
            <tr>
              <td><?= $no++ ?></td>
              <td><?= htmlspecialchars($p['nama_sekolah']) ?></td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>

    <?php elseif($pendaftaran_tutup): ?>
      <!-- PENDAFTARAN SUDAH TUTUP -->
      <h3 style="color:red;text-align:center;">Pendaftaran ditutup</h3>

    <?php else: ?>
      <!-- FORM PENDAFTARAN -->
      <form method="POST" enctype="multipart/form-data">
        <h3>Data Pribadi</h3>
        <label>Nama Lengkap</label>
        <input type="text" name="nama" value="<?php echo htmlspecialchars($nama_lengkap); ?>" required>

        <label>NISN</label>
        <input type="text" name="nisn" value="<?php echo htmlspecialchars($nisn); ?>" required>

        <label>NIK</label>
        <input type="text" name="nik" value="<?php echo htmlspecialchars($nik); ?>" required>

        <label>Alamat</label>
        <textarea name="alamat" required><?php echo htmlspecialchars($alamat); ?></textarea>

        <label>No. HP</label>
        <input type="text" name="no_hp" value="<?php echo htmlspecialchars($no_hp); ?>" required>

        <label>Tempat Lahir</label>
        <input type="text" name="tempat_lahir" value="<?php echo htmlspecialchars($tempat_lahir); ?>" required>

        <label>Tanggal Lahir</label>
        <input type="date" name="tgl_lahir" value="<?php echo htmlspecialchars($tgl_lahir); ?>" required>

        <label>Jenis Kelamin</label>
        <select name="jk" required>
          <option value="">-- Pilih --</option>
          <option value="L" <?php echo ($jk === "L") ? "selected" : ""; ?>>Laki-laki</option>
          <option value="P" <?php echo ($jk === "P") ? "selected" : ""; ?>>Perempuan</option>
        </select>

        <h3>Data Orang Tua & Wali</h3>
        <label>Nama Ayah</label>
        <input type="text" name="nama_ayah">

        <label>No HP Ayah</label>
        <input type="text" name="no_hp_ayah">

        <label>Nama Ibu</label>
        <input type="text" name="nama_ibu">

        <label>No HP Ibu</label>
        <input type="text" name="no_hp_ibu">

        <label>Nama Wali</label>
        <input type="text" name="nama_wali">

        <label>No HP Wali</label>
        <input type="text" name="no_hp_wali">

        <h3>Data Sekolah Asal</h3>
        <label>Nama Sekolah Asal</label>
        <input type="text" name="nama_sekolah_asal" value="<?php echo htmlspecialchars($nama_sekolah_asal); ?>" required>

        <label>NPSN Sekolah Asal</label>
        <input type="text" name="npsn_sekolah_asal" value="<?php echo htmlspecialchars($npsn_sekolah_asal); ?>" required>

        <label>Alamat Sekolah Asal</label>
        <textarea name="alamat_sekolah_asal" required><?php echo htmlspecialchars($alamat_sekolah_asal); ?></textarea>

        <h3>Nilai Rapor</h3>
        <?php for($s=1;$s<=5;$s++): ?>
          <fieldset class="semester-group">
            <legend>Semester <?php echo $s; ?></legend>
            <?php foreach($mapelList as $m): ?>
              <div>
                <label><?php echo $m['nama_mapel']; ?></label>
                <input type="number" name="nilai[<?php echo $s; ?>][<?php echo $m['id']; ?>]" min="0" max="100" required>
              </div>
            <?php endforeach; ?>
          </fieldset>
        <?php endfor; ?>

        <h3>Upload Dokumen</h3>
        <?php
        $aturanDocs = $conn->query("SELECT nama_dokumen, tipe_dokumen FROM aturan_dokumen ORDER BY id ASC");
        while ($doc = $aturanDocs->fetch_assoc()):
            $nama = htmlspecialchars($doc['nama_dokumen']);
            $tipe = strtolower($doc['tipe_dokumen']);
            $accept = ($tipe == 'pdf') ? '.pdf' : '.jpg,.jpeg,.png';
        ?>
          <label>
            <?php echo $nama; ?> 
            <small style="color: gray;">(<?php echo strtoupper($tipe); ?>)</small>
          </label>
          <input type="file" name="dokumen[<?php echo $nama; ?>]" accept="<?php echo $accept; ?>" required>
        <?php endwhile; ?>

        <h3>Pilihan Sekolah</h3>
        <?php for($i=1;$i<=3;$i++): ?>
          <label>Pilihan <?php echo $i; ?>:</label>
          <select name="sekolah[]">
            <option value="">Pilih Sekolah</option>
            <?php foreach ($sekolahList as $s): ?>
              <option value="<?php echo $s['id']; ?>"><?php echo $s['nama_sekolah']; ?></option>
            <?php endforeach; ?>
          </select>
        <?php endfor; ?>

        <button type="submit" class="btn-detail">Simpan Pendaftaran</button>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
