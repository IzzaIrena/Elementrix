<?php
session_start();
include("../koneksi_mysql.php"); // koneksi MySQL ($conn)

// CEK LOGIN SISWA
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'siswa') {
    header("Location: loginSiswa.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// AMBIL DATA SISWA & USER
$sqlSiswa = "SELECT s.*, u.email AS email_user, u.password AS password_user
             FROM siswa s
             LEFT JOIN user u ON u.id = s.user_id
             WHERE s.user_id = ?";
$stmt = mysqli_prepare($conn, $sqlSiswa);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$resSiswa = mysqli_stmt_get_result($stmt);

if ($resSiswa && mysqli_num_rows($resSiswa) > 0) {
    $siswa = mysqli_fetch_assoc($resSiswa);
    $nama_lengkap = $siswa['nama_lengkap'] ?? 'Siswa';
    $password_hash = $siswa['password_user'] ?? '';
} else {
    die("Data siswa tidak ditemukan di database.");
}

// AMBIL TAHUN AKADEMIK AKTIF
$sqlTahun = "SELECT id FROM tahun_akademik WHERE status = 'aktif' LIMIT 1";
$resTahun = mysqli_query($conn, $sqlTahun);

if (!$resTahun || mysqli_num_rows($resTahun) == 0) {
    die("Tahun akademik aktif tidak ditemukan.");
}

$tahun_id = mysqli_fetch_assoc($resTahun)['id'];

// CEK MASA PENDAFTARAN
$boleh_edit = true;
$sekarang = date("Y-m-d");

// ambil tanggal seleksi
$sqlSeleksi = "
    SELECT tanggal_selesai 
    FROM aturan_seleksi 
    WHERE tahun_akademik_id = ?
    LIMIT 1
";
$stmt = mysqli_prepare($conn, $sqlSeleksi);
mysqli_stmt_bind_param($stmt, "i", $tahun_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$tanggalSelesaiSeleksi = ($res && mysqli_num_rows($res) > 0)
    ? mysqli_fetch_assoc($res)['tanggal_selesai']
    : null;


// ambil tanggal zonasi
$sqlZonasi = "
    SELECT tanggal_selesai 
    FROM aturan_zonasi 
    WHERE tahun_akademik_id = ?
    LIMIT 1
";
$stmt = mysqli_prepare($conn, $sqlZonasi);
mysqli_stmt_bind_param($stmt, "i", $tahun_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$tanggalSelesaiZonasi = ($res && mysqli_num_rows($res) > 0)
    ? mysqli_fetch_assoc($res)['tanggal_selesai']
    : null;


// CEK MASA PENDAFTARAN (HANYA PENDAFTARAN, BUKAN SELEKSI)
// CEK MASA PENDAFTARAN UNTUK ZONASI
$sqlZonasi = "SELECT tanggal_selesai FROM aturan_zonasi WHERE tahun_akademik_id = ? ORDER BY id DESC LIMIT 1";
$stmt = mysqli_prepare($conn, $sqlZonasi);
mysqli_stmt_bind_param($stmt, "i", $tahun_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$tanggalSelesaiZonasi = ($res && mysqli_num_rows($res) > 0) ? mysqli_fetch_assoc($res)['tanggal_selesai'] : null;

// CEK MASA PENDAFTARAN UNTUK AKADEMIK
$sqlAkademik = "SELECT tanggal_selesai FROM aturan_seleksi WHERE tahun_akademik_id = ? ORDER BY id DESC LIMIT 1";
$stmt = mysqli_prepare($conn, $sqlAkademik);
mysqli_stmt_bind_param($stmt, "i", $tahun_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$tanggalSelesaiAkademik = ($res && mysqli_num_rows($res) > 0) ? mysqli_fetch_assoc($res)['tanggal_selesai'] : null;

// CEK APAKAH MASIH BOLEH EDIT (SELAMA MASA PENDAFTARAN DI SALAH SATU JALUR)
$sekarang = date("Y-m-d");
$boleh_edit = false;

if (($tanggalSelesaiZonasi && $sekarang <= $tanggalSelesaiZonasi) ||
    ($tanggalSelesaiAkademik && $sekarang <= $tanggalSelesaiAkademik)) {
    $boleh_edit = true;
}

// Gunakan tanggal_selesai_terakhir untuk info di UI
$tanggal_selesai_terakhir = max($tanggalSelesaiZonasi ?? '0000-00-00', $tanggalSelesaiAkademik ?? '0000-00-00');

// UPDATE PROFIL SISWA
if ($boleh_edit && isset($_POST['update_profil'])) {
    $nama = $_POST['nama_lengkap'] ?? '';
    $nisn = $_POST['nisn'] ?? '';
    $nik = $_POST['nik'] ?? '';
    $email = $_POST['email'] ?? '';
    $no_hp = $_POST['no_hp'] ?? '';
    $tempat_lahir = $_POST['tempat_lahir'] ?? '';
    $tanggal_lahir = $_POST['tanggal_lahir'] ?? '';
    $jk = $_POST['jenis_kelamin'] ?? '';
    $alamat = $_POST['alamat'] ?? '';

    // Upload foto
    $foto = $siswa['foto'] ?? null;
    if (!empty($_FILES['foto']['name'])) {
        $target_dir = "../uploads/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

        $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $new_name = "foto_" . $user_id . "_" . time() . "." . $ext;
        $target_file = $target_dir . $new_name;

        if (move_uploaded_file($_FILES['foto']['tmp_name'], $target_file)) {
            if (!empty($siswa['foto']) && file_exists($target_dir . $siswa['foto'])) unlink($target_dir . $siswa['foto']);
            $foto = $new_name;
        }
    }

    // Update data siswa
    $sqlUpdate = "UPDATE siswa SET
                    nama_lengkap=?, nisn=?, nik=?, no_hp=?, 
                    tempat_lahir=?, tanggal_lahir=?, jk=?, alamat=?, foto=?
                  WHERE user_id=?";
    $stmt = mysqli_prepare($conn, $sqlUpdate);
    mysqli_stmt_bind_param($stmt, "sssssssssi", $nama, $nisn, $nik, $no_hp, $tempat_lahir, $tanggal_lahir, $jk, $alamat, $foto, $user_id);
    mysqli_stmt_execute($stmt);

    // Update email user
    $sqlEmail = "UPDATE user SET email=? WHERE id=?";
    $stmt2 = mysqli_prepare($conn, $sqlEmail);
    mysqli_stmt_bind_param($stmt2, "si", $email, $user_id);
    mysqli_stmt_execute($stmt2);

    echo "<script>alert('Profil berhasil diperbarui!'); window.location='profilSiswa.php';</script>";
    exit;
} elseif (!$boleh_edit && isset($_POST['update_profil'])) {
    echo "<script>alert('Masa pendaftaran telah berakhir, data tidak dapat diubah.');</script>";
}

// GANTI PASSWORD
if (isset($_POST['ubah_password'])) {
    $password_lama = $_POST['password_lama'] ?? '';
    $password_baru = $_POST['password_baru'] ?? '';
    $konfirmasi = $_POST['konfirmasi'] ?? '';

    if (!empty($password_hash) && password_verify($password_lama, $password_hash)) {
        if ($password_baru === $konfirmasi) {
            $hashed = password_hash($password_baru, PASSWORD_DEFAULT);
            $sqlPass = "UPDATE user SET password=? WHERE id=?";
            $stmt3 = mysqli_prepare($conn, $sqlPass);
            mysqli_stmt_bind_param($stmt3, "si", $hashed, $user_id);
            mysqli_stmt_execute($stmt3);
            echo "<script>alert('Password berhasil diubah!'); window.location='profilSiswa.php';</script>";
            exit;
        } else echo "<script>alert('Konfirmasi password baru tidak sama.');</script>";
    } else echo "<script>alert('Password lama salah.');</script>";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Profil Siswa</title>
<link rel="stylesheet" href="../css/dashboardSiswa.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
.main-content { margin-left: 240px; padding: 20px; }
.profil-container {
  background: #fff; padding: 25px 35px; border-radius: 14px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1); max-width: 720px; margin: 40px auto;
}
.profil-container h2 {
  color: #1d4ed8; font-size: 22px;
  margin-bottom: 20px; border-bottom: 2px solid #e5e7eb;
  padding-bottom: 8px; display: flex; align-items: center; gap: 8px;
}
.foto-wrapper { text-align: center; margin-bottom: 25px; position: relative; }
.foto-wrapper .foto-circle {
  width: 140px; height: 140px; border-radius: 50%;
  border: 3px solid #3b82f6; background-color: #f3f4f6;
  margin: 0 auto; display: flex; align-items: center; justify-content: center;
  overflow: hidden;
}
.foto-wrapper img { width: 100%; height: 100%; object-fit: cover; }
.foto-wrapper .default-icon { font-size: 70px; color: #9ca3af; }
.profil-form { display: flex; flex-direction: column; gap: 12px; }
.profil-form label { font-weight: 600; color: #374151; font-size: 14px; }
.profil-form input, .profil-form select, .profil-form textarea {
  padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px;
  font-size: 14px; outline: none; transition: 0.2s;
}
.profil-form input:focus, .profil-form select:focus, .profil-form textarea:focus {
  border-color: #2563eb; box-shadow: 0 0 4px rgba(37,99,235,0.3);
}
.profil-form textarea { resize: vertical; min-height: 70px; }
.profil-form button {
  background: linear-gradient(90deg, #2a6fdb, #1d4ed8);
  color: #fff; border: none; border-radius: 8px;
  padding: 10px 0; font-size: 15px; cursor: pointer;
  transition: 0.3s; margin-top: 5px;
}
.profil-form button:hover { opacity: 0.9; }
.btn-password { background: linear-gradient(90deg, #16a34a, #15803d); }
.disabled { background: #9ca3af !important; cursor: not-allowed; }
hr { margin: 30px 0; border: none; height: 1px; background: #e5e7eb; }
.info-tutup {
  background: #fee2e2; color: #b91c1c;
  border: 1px solid #fecaca; border-radius: 8px;
  padding: 10px 15px; margin-bottom: 15px;
  font-size: 14px; text-align: center;
}
</style>
</head>
<body>
  <?php include('headerSiswa.php'); ?>
  <?php include('sidebarSiswa.php'); ?>

<div class="main-content">
  <div class="profil-container">
    <h2><i class="fa-solid fa-user"></i> Profil Siswa</h2>

    <?php if (!$boleh_edit && $tanggal_selesai_terakhir): ?>
      <div class="info-tutup">
        Masa pendaftaran telah berakhir pada <b><?= date("d M Y", strtotime($tanggal_selesai_terakhir)); ?></b>.  
        Data profil tidak dapat diubah lagi.
      </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="profil-form" id="formProfil">
      <div class="foto-wrapper">
        <div class="foto-circle">
          <?php 
            $foto_path = (!empty($siswa['foto']) && file_exists("../uploads/".$siswa['foto'])) 
                          ? "../uploads/".$siswa['foto'] : null;
            echo $foto_path ? "<img id='preview' src='$foto_path' alt='Foto Profil'>" 
                            : "<i class='fa-solid fa-user default-icon' id='defaultIcon'></i>";
          ?>
        </div>
        <input type="file" name="foto" accept="image/*" onchange="previewImage(event)" <?= !$boleh_edit ? 'disabled class="disabled"' : '' ?>>
      </div>

      <label>Nama Lengkap</label>
      <input type="text" name="nama_lengkap" value="<?= htmlspecialchars($siswa['nama_lengkap'] ?? ''); ?>" required <?= !$boleh_edit ? 'readonly' : '' ?>>

      <label>NISN</label>
      <input type="text" name="nisn" value="<?= htmlspecialchars($siswa['nisn'] ?? ''); ?>" <?= !$boleh_edit ? 'readonly' : '' ?>>

      <label>NIK</label>
      <input type="text" name="nik" value="<?= htmlspecialchars($siswa['nik'] ?? ''); ?>" <?= !$boleh_edit ? 'readonly' : '' ?>>

      <label>Email</label>
      <input type="email" name="email" value="<?= htmlspecialchars($siswa['email_user'] ?? '') ?>" <?= !$boleh_edit ? 'readonly' : '' ?>>

      <label>No HP</label>
      <input type="text" name="no_hp" value="<?= htmlspecialchars($siswa['no_hp'] ?? ''); ?>" <?= !$boleh_edit ? 'readonly' : '' ?>>

      <label>Tempat Lahir</label>
      <input type="text" name="tempat_lahir" value="<?= htmlspecialchars($siswa['tempat_lahir'] ?? ''); ?>" <?= !$boleh_edit ? 'readonly' : '' ?>>

      <label>Tanggal Lahir</label>
      <input type="date" name="tanggal_lahir" value="<?= htmlspecialchars($siswa['tanggal_lahir'] ?? ''); ?>" <?= !$boleh_edit ? 'readonly' : '' ?>>

      <label>Jenis Kelamin</label>
      <select name="jenis_kelamin" <?= !$boleh_edit ? 'disabled' : '' ?>>
          <option value="">Pilih</option>
          <option value="L" <?= (($siswa['jk'] ?? '') == 'L') ? 'selected' : ''; ?>>Laki-laki</option>
          <option value="P" <?= (($siswa['jk'] ?? '') == 'P') ? 'selected' : ''; ?>>Perempuan</option>
      </select>

      <label>Alamat</label>
      <textarea name="alamat" <?= !$boleh_edit ? 'readonly' : '' ?>><?= htmlspecialchars($siswa['alamat'] ?? ''); ?></textarea>

      <button type="submit" name="update_profil" <?= !$boleh_edit ? 'disabled class="disabled"' : '' ?>>Simpan Perubahan</button>
    </form>

    <hr>

    <h2><i class="fa-solid fa-lock"></i> Ubah Password</h2>
    <form method="POST" class="profil-form">
      <label>Password Lama</label>
      <input type="password" name="password_lama" required>

      <label>Password Baru</label>
      <input type="password" name="password_baru" required>

      <label>Konfirmasi Password Baru</label>
      <input type="password" name="konfirmasi" required>

      <button type="submit" name="ubah_password" class="btn-password">Ubah Password</button>
    </form>
  </div>
</div>

<script>
function previewImage(event) {
  const file = event.target.files[0];
  const preview = document.getElementById('preview');
  const defaultIcon = document.getElementById('defaultIcon');

  if (file) {
    const url = URL.createObjectURL(file);
    if (preview) preview.src = url;
    else {
      const img = document.createElement('img');
      img.id = 'preview';
      img.src = url;
      img.style.width = '100%';
      img.style.height = '100%';
      img.style.objectFit = 'cover';
      const circle = document.querySelector('.foto-circle');
      circle.innerHTML = '';
      circle.appendChild(img);
    }
    if (defaultIcon) defaultIcon.style.display = 'none';
  }
}
</script>
</body>
</html>
