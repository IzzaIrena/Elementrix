<?php
session_start();
include "../koneksi_mysql.php"; // koneksi MySQL

// Cek login dinas
if (!isset($_SESSION['dinas_id'])) {
    header("Location: loginDinas.php");
    exit;
}

$nama_dinas = $_SESSION['nama_dinas'];
$success = "";
$error = "";

// Ambil ID sekolah dari URL
$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: kelolaSekolah.php");
    exit;
}

// Ambil data sekolah dari MySQL
$q = mysqli_query($conn, "SELECT * FROM sekolah WHERE id='$id'");
$snapshot = mysqli_fetch_assoc($q);

if (!$snapshot) {
    die("Data sekolah tidak ditemukan.");
}

// Saat form dikirim
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_sekolah = trim($_POST['nama_sekolah']);
    $npsn         = trim($_POST['npsn']);
    $email        = trim($_POST['email']);
    $alamat       = trim($_POST['alamat']);
    $kontak       = trim($_POST['kontak']);
    $kuota        = intval($_POST['kuota']);
    $lat          = isset($_POST['latitude']) ? floatval($_POST['latitude']) : null;
    $lon          = isset($_POST['longitude']) ? floatval($_POST['longitude']) : null;

    if ($nama_sekolah && $npsn && $email) {
        $updateQuery = "
            UPDATE sekolah SET
                nama_sekolah = '".mysqli_real_escape_string($conn, $nama_sekolah)."',
                npsn         = '".mysqli_real_escape_string($conn, $npsn)."',
                email        = '".mysqli_real_escape_string($conn, $email)."',
                alamat       = '".mysqli_real_escape_string($conn, $alamat)."',
                kontak       = '".mysqli_real_escape_string($conn, $kontak)."',
                kuota        = '$kuota',
                latitude     = ".($lat !== null ? "'$lat'" : "NULL").",
                longitude    = ".($lon !== null ? "'$lon'" : "NULL").",
                updated_at   = NOW()
            WHERE id='$id'
        ";

        if (mysqli_query($conn, $updateQuery)) {
            $success = "✅ Data sekolah berhasil diperbarui!";
            $q = mysqli_query($conn, "SELECT * FROM sekolah WHERE id='$id'");
            $snapshot = mysqli_fetch_assoc($q); // Refresh data
        } else {
            $error = "Gagal memperbarui data: " . mysqli_error($conn);
        }
    } else {
        $error = "Mohon isi semua data wajib!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Edit Sekolah</title>
  <link rel="stylesheet" href="../css/dashboardDinas.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<style>
.btn-kembali {
    background: #ffffff;
    color: #334155;
    border: 1px solid #cbd5e1;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 16px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    transition: all .2s ease;
}

.btn-kembali:hover {
    background: #f8fafc;
    border-color: #94a3b8;
    color: #0f172a;
}

.btn-kembali i {
    font-size: 13px;
}
</style>

</head>
<body>
  <?php include("sidebarDinas.php"); ?>

  <div class="main-content">
    <?php include("headerDinas.php"); ?>

    <div style="padding:20px 20px 0 20px;">
        <a href="kelolaSekolah.php" class="btn-kembali">
            <i class="fa-solid fa-arrow-left"></i> Kembali
        </a>
    </div>

    <main>
      <?php if ($success): ?><div class="alert success"><?= $success ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert error"><?= $error ?></div><?php endif; ?>

      <form method="POST" class="form-box">
        <h3>📍 Lokasi Sekolah</h3>
        <div id="map" style="height: 300px; border-radius:10px;"></div>

        <input type="hidden" name="latitude" id="latitude" value="<?= $snapshot['latitude'] ?? '' ?>">
        <input type="hidden" name="longitude" id="longitude" value="<?= $snapshot['longitude'] ?? '' ?>">

        <script>
          var lat = <?= isset($snapshot['latitude']) ? $snapshot['latitude'] : '-4.0167' ?>;
          var lon = <?= isset($snapshot['longitude']) ? $snapshot['longitude'] : '119.6200' ?>;
          var map = L.map('map').setView([lat, lon], 13);

          L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
              maxZoom: 18,
              attribution: '© OpenStreetMap'
          }).addTo(map);

          var marker = L.marker([lat, lon]).addTo(map)
                        .bindPopup("<?= htmlspecialchars($snapshot['nama_sekolah']) ?>").openPopup();

          map.on('click', function(e) {
              var clickedLat = e.latlng.lat;
              var clickedLon = e.latlng.lng;

              if(marker) map.removeLayer(marker);

              marker = L.marker([clickedLat, clickedLon]).addTo(map)
                        .bindPopup("Lokasi Baru").openPopup();

              document.getElementById('latitude').value = clickedLat.toFixed(8);
              document.getElementById('longitude').value = clickedLon.toFixed(8);
          });
        </script>

        <label>Nama Sekolah</label>
        <input type="text" name="nama_sekolah" value="<?= htmlspecialchars($snapshot['nama_sekolah']) ?>" required>

        <label>NPSN</label>
        <input type="text" name="npsn" value="<?= htmlspecialchars($snapshot['npsn']) ?>" required>

        <label>Email (Login)</label>
        <input type="email" name="email" value="<?= htmlspecialchars($snapshot['email']) ?>" required>

        <label>Alamat</label>
        <textarea name="alamat"><?= htmlspecialchars($snapshot['alamat']) ?></textarea>

        <label>Kontak</label>
        <input type="text" name="kontak" value="<?= htmlspecialchars($snapshot['kontak']) ?>">

        <label>Kuota</label>
        <input type="number" name="kuota" value="<?= htmlspecialchars($snapshot['kuota']) ?>" min="0">

        <button type="submit"><i class="fa-solid fa-save"></i> Simpan Perubahan</button>
      </form>
    </main>
  </div>
</body>
</html>
