<?php
session_start();
include "../koneksi_mysql.php"; // 🔹 Koneksi MySQL

// Cek apakah dinas sudah login
if (!isset($_SESSION['dinas_id'])) {
    header("Location: loginDinas.php");
    exit;
}

$nama_dinas = $_SESSION['nama_dinas'] ?? "Dinas Pendidikan";
$success = "";
$error = "";

// ===============================
// FUNGSI OPSIONAL GEOLOCATION
// ===============================
function getCoordinates($alamat) {
    $encoded = urlencode($alamat);
    $url = "https://nominatim.openstreetmap.org/search?q=$encoded&format=json&limit=1";

    $options = [
        "http" => [
            "header" => "User-Agent: PPDB-Zonasi-App/1.0\r\n"
        ]
    ];
    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);

    if ($response) {
        $data = json_decode($response, true);
        if (!empty($data)) {
            return [
                'lat' => $data[0]['lat'],
                'lon' => $data[0]['lon']
            ];
        }
    }
    return ['lat' => null, 'lon' => null];
}

// ===========================================================
//                PROSES SUBMIT FORM
// ===========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nama_sekolah = trim($_POST['nama_sekolah']);
    $npsn         = trim($_POST['npsn']);
    $alamat       = trim($_POST['alamat']);
    $kontak       = trim($_POST['kontak']);
    $kuota        = intval($_POST['kuota']);
    $email        = trim($_POST['email']);
    $password     = trim($_POST['password']);
    $lat          = $_POST['latitude'] ?: null;
    $lon          = $_POST['longitude'] ?: null;

    if ($nama_sekolah && $npsn && $email && $password) {

        // Cek email apakah sudah dipakai
        $cek = $conn->prepare("SELECT id FROM user WHERE email = ?");
        $cek->bind_param("s", $email);
        $cek->execute();
        $hasil = $cek->get_result();

        if ($hasil->num_rows > 0) {
            $error = "Email sudah digunakan!";
        } else {

            // SIMPAN DATA USER
            $hashedPass = password_hash($password, PASSWORD_DEFAULT);

            $insertUser = $conn->prepare("
                INSERT INTO user (nama, email, password, role, created_at)
                VALUES (?, ?, ?, 'sekolah', NOW())
            ");
            $insertUser->bind_param("sss", $nama_sekolah, $email, $hashedPass);
            $insertUser->execute();

            $user_id = $insertUser->insert_id;

            // SIMPAN DATA SEKOLAH
            $insertSekolah = $conn->prepare("
                INSERT INTO sekolah 
                (user_id, nama_sekolah, npsn, email, alamat, kontak, kuota, latitude, longitude, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $insertSekolah->bind_param(
                "isssssidd",
                $user_id, $nama_sekolah, $npsn, $email, $alamat, $kontak, $kuota, $lat, $lon
            );
            $insertSekolah->execute();

            $success = "Akun sekolah berhasil dibuat!";
        }

        $cek->close();
    } else {
        $error = "Mohon isi semua data wajib!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Buat Akun Sekolah</title>
  <link rel="stylesheet" href="../css/dashboardDinas.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
</head>
<body>

  <?php include("sidebarDinas.php"); ?>
  <div class="main-content">
    <?php include("headerDinas.php"); ?>

    <main>
      <?php if ($success): ?>
        <div class="alert success"><?= $success ?></div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="alert error"><?= $error ?></div>
      <?php endif; ?>

      <form method="POST" class="form-box">

        <!-- ========================= MAP ========================= -->
        <h3>📍 Pilih Lokasi Sekolah:</h3>
        <div id="map" style="height: 300px; border-radius:10px;"></div>

        <input type="hidden" name="latitude" id="latitude">
        <input type="hidden" name="longitude" id="longitude">

        <script>
          var map = L.map('map').setView([-4.0167, 119.6200], 13);
          L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
              maxZoom: 18,
              attribution: '© OpenStreetMap'
          }).addTo(map);

          var marker = null;

          map.on('click', function(e) {
              var lat = e.latlng.lat;
              var lon = e.latlng.lng;

              if (marker) map.removeLayer(marker);
              marker = L.marker([lat, lon]).addTo(map)
                        .bindPopup("Lokasi Sekolah").openPopup();

              document.getElementById('latitude').value = lat;
              document.getElementById('longitude').value = lon;
          });
        </script>

        <!-- ========================= FORM ========================= -->
        <label>Nama Sekolah</label>
        <input type="text" name="nama_sekolah" required>

        <label>NPSN</label>
        <input type="text" name="npsn" required>

        <label>Email (Login)</label>
        <input type="email" name="email" required>

        <label>Password</label>
        <input type="password" name="password" required>

        <label>Alamat</label>
        <textarea name="alamat"></textarea>

        <label>Kontak</label>
        <input type="text" name="kontak">

        <label>Kuota</label>
        <input type="number" name="kuota" min="0" value="0">

        <button type="submit"><i class="fa-solid fa-save"></i> Simpan</button>
      </form>
    </main>

  </div>
</body>
</html>
