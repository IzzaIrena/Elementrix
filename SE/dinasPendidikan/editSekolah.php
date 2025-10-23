<?php
session_start();
include "../koneksi.php";

if (!isset($_SESSION['dinas_id'])) {
    header("Location: loginDinas.php");
    exit;
}

$nama_dinas = $_SESSION['nama_dinas'];
$success = "";
$error = "";

// Ambil data sekolah yang mau diedit
$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: kelolaSekolah.php");
    exit;
}

$query = $conn->prepare("SELECT * FROM sekolah WHERE id = ?");
$query->bind_param("i", $id);
$query->execute();
$data = $query->get_result()->fetch_assoc();
$query->close();

if (!$data) {
    die("Data sekolah tidak ditemukan.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_sekolah = trim($_POST['nama_sekolah']);
    $npsn         = trim($_POST['npsn']);
    $email        = trim($_POST['email']);
    $alamat       = trim($_POST['alamat']);
    $kontak       = trim($_POST['kontak']);
    $kuota        = intval($_POST['kuota']);
    $lat          = isset($_POST['latitude']) ? floatval($_POST['latitude']) : null;
    $lon          = isset($_POST['longitude']) ? floatval($_POST['longitude']) : null;

    $update = $conn->prepare("
        UPDATE sekolah 
        SET nama_sekolah=?, npsn=?, email=?, alamat=?, kontak=?, kuota=?, latitude=?, longitude=?
        WHERE id=?
    ");
    $update->bind_param("ssssssddi", $nama_sekolah, $npsn, $email, $alamat, $kontak, $kuota, $lat, $lon, $id);


    if ($update->execute()) {
        $success = "Data sekolah berhasil diperbarui!";
        // Refresh data dari DB agar peta juga update
        $query = $conn->prepare("SELECT * FROM sekolah WHERE id = ?");
        $query->bind_param("i", $id);
        $query->execute();
        $data = $query->get_result()->fetch_assoc();
        $query->close();
    } else {
        $error = "Gagal memperbarui data: " . $update->error;
    }
    $update->close();
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
</head>
<body>
  <aside class="sidebar">
    <h2><i class="fa-solid fa-school"></i> Dinas</h2>
    <ul>
      <li><a href="dashboardDinas.php"><i class="fa-solid fa-gauge"></i> Dashboard</a></li>
      <li><a href="buatAkunSekolah.php"><i class="fa-solid fa-user-plus"></i> Buat Akun Sekolah</a></li>
      <li><a href="kelolaSekolah.php"><i class="fa-solid fa-school"></i> Kelola Sekolah</a></li>
      <li><a href="kelolaTahunAkademik.php"><i class="fa-solid fa-calendar-days"></i> Tahun Akademik</a></li>
      <li><a href="aturanSeleksi.php"><i class="fa-solid fa-scale-balanced"></i> Aturan Seleksi</a></li>
      <li><a href="kelolaPendaftaranDinas.php" class="active"><i class="fa-solid fa-users"></i> Kelola Pendaftaran</a></li>
      <li><a href="monitoring.php"><i class="fa-solid fa-chart-line"></i> Monitoring</a></li>
      <li><a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
    </ul>
  </aside>

  <div class="main-content">
    <header>
      <h1>Edit Data Sekolah</h1>
      <div class="user-info">
        <span><i class="fa-solid fa-user-tie"></i> <?= htmlspecialchars($nama_dinas); ?></span>
      </div>
    </header>

    <main>
      <?php if ($success): ?><div class="alert success"><?= $success ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert error"><?= $error ?></div><?php endif; ?>

      <form method="POST" class="form-box">
        <h3>📍 Lokasi Sekolah</h3>
        <div id="map" style="height: 300px; border-radius:10px;"></div>

        <input type="hidden" name="latitude" id="latitude" value="<?= $data['latitude'] ?>">
        <input type="hidden" name="longitude" id="longitude" value="<?= $data['longitude'] ?>">

        <script>
          var lat = <?= $data['latitude'] ?: '-4.0167' ?>;
          var lon = <?= $data['longitude'] ?: '119.6200' ?>;
          var map = L.map('map').setView([lat, lon], 13);

          L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
              maxZoom: 18,
              attribution: '© OpenStreetMap'
          }).addTo(map);

          var marker = L.marker([lat, lon]).addTo(map)
                        .bindPopup("<?= htmlspecialchars($data['nama_sekolah']) ?>").openPopup();

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
        <input type="text" name="nama_sekolah" value="<?= htmlspecialchars($data['nama_sekolah']) ?>" required>

        <label>NPSN</label>
        <input type="text" name="npsn" value="<?= htmlspecialchars($data['npsn']) ?>" required>

        <label>Email (Login)</label>
        <input type="email" name="email" value="<?= htmlspecialchars($data['email']) ?>" required>

        <label>Alamat</label>
        <textarea name="alamat"><?= htmlspecialchars($data['alamat']) ?></textarea>

        <label>Kontak</label>
        <input type="text" name="kontak" value="<?= htmlspecialchars($data['kontak']) ?>">

        <label>Kuota</label>
        <input type="number" name="kuota" value="<?= htmlspecialchars($data['kuota']) ?>" min="0">

        <button type="submit"><i class="fa-solid fa-save"></i> Simpan Perubahan</button>
      </form>
    </main>
  </div>
</body>
</html>
