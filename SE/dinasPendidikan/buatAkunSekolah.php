<?php
session_start();
include "../koneksi.php";

// Cek apakah dinas sudah login
if (!isset($_SESSION['dinas_id'])) {
    header("Location: loginDinas.php");
    exit;
}

$nama_dinas = $_SESSION['nama_dinas'];
$success = "";
$error = "";

function getCoordinates($alamat) {
    $encoded = urlencode($alamat);
    $url = "https://nominatim.openstreetmap.org/search?q=$encoded&format=json&limit=1";

    $options = [
        "http" => [
            "header" => "User-Agent: PPDB-Zonasi-App/1.0\r\n"
        ]
    ];
    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);

    if ($response !== false) {
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_sekolah = trim($_POST['nama_sekolah']);
    $npsn         = trim($_POST['npsn']);
    $alamat       = trim($_POST['alamat']);
    $kontak       = trim($_POST['kontak']);
    $kuota        = intval($_POST['kuota']);
    $email        = trim($_POST['email']);
    $password     = trim($_POST['password']);

    if ($nama_sekolah && $npsn && $email && $password) {
        // cek email di tabel sekolah
        $cek = $conn->prepare("SELECT id FROM sekolah WHERE email = ?");
        $cek->bind_param("s", $email);
        $cek->execute();
        $cek->store_result();

        if ($cek->num_rows > 0) {
            $error = "Email sudah digunakan!";
        } else {
            // simpan user dulu
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmtUser = $conn->prepare("INSERT INTO user (username, password, role) VALUES (?, ?, 'sekolah')");
            $stmtUser->bind_param("ss", $npsn, $hashed); // username bisa NPSN

            if ($stmtUser->execute()) {
                $user_id = $stmtUser->insert_id;

                // simpan sekolah
                $lat = isset($_POST['latitude']) ? floatval($_POST['latitude']) : null;
                $lon = isset($_POST['longitude']) ? floatval($_POST['longitude']) : null;

                $stmtSekolah = $conn->prepare("
                    INSERT INTO sekolah 
                    (user_id, nama_sekolah, npsn, email, alamat, kontak, kuota, latitude, longitude) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmtSekolah->bind_param("isssssidd", $user_id, $nama_sekolah, $npsn, $email, $alamat, $kontak, $kuota, $lat, $lon);

                if ($stmtSekolah->execute()) {
                    $success = "Akun sekolah berhasil dibuat!";
                } else {
                    $error = "Gagal simpan sekolah: " . $stmtSekolah->error;
                }
                $stmtSekolah->close();
            } else {
                $error = "Gagal simpan user: " . $stmtUser->error;
            }
            $stmtUser->close();
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
  <aside class="sidebar">
    <h2><i class="fa-solid fa-school"></i> Dinas</h2>
    <ul>
      <li><a href="dashboardDinas.php"><i class="fa-solid fa-gauge"></i> Dashboard</a></li>
      <li><a href="buatAkunSekolah.php"><i class="fa-solid fa-user-plus"></i> Buat Akun Sekolah</a></li>
      <li><a href="kelolaSekolah.php"><i class="fa-solid fa-school"></i> Kelola Sekolah</a></li>
      <li><a href="kelolaTahunAkademik.php"><i class="fa-solid fa-calendar-days"></i> Tahun Akademik</a></li>
      <li><a href="aturanSeleksi.php"><i class="fa-solid fa-scale-balanced"></i> Aturan Seleksi</a></li>
      <li><a href="kelolaPendaftaranDinas.php" class="active"><i class="fa-solid fa-users"></i> Kelola Pendaftaran</a></li>
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
      <?php if ($success): ?>
        <div class="alert success"><?php echo $success; ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert error"><?php echo $error; ?></div>
      <?php endif; ?>

      <form method="POST" class="form-box">
        <h3>📍 Pilih Lokasi Sekolah:</h3>
        <div id="map" style="height: 300px; border-radius:10px;"></div>

        <!-- input tersembunyi untuk menyimpan koordinat -->
        <input type="hidden" name="latitude" id="latitude" value="<?= isset($lat) ? $lat : '' ?>">
        <input type="hidden" name="longitude" id="longitude" value="<?= isset($lon) ? $lon : '' ?>">

        <script>
          // Koordinat awal: bisa default kota Parepare
          var lat = <?= isset($lat) ? $lat : '-4.0167' ?>;  // latitude Parepare
          var lon = <?= isset($lon) ? $lon : '119.6200' ?>; // longitude Parepare
          var map = L.map('map').setView([lat, lon], 13);

          L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
              maxZoom: 18,
              attribution: '© OpenStreetMap'
          }).addTo(map);

          // buat marker awal jika sudah ada koordinat
          var marker = null;
          if(document.getElementById('latitude').value && document.getElementById('longitude').value){
              marker = L.marker([lat, lon]).addTo(map)
                        .bindPopup("<?= htmlspecialchars($nama_sekolah ?? '') ?>").openPopup();
          }

          // klik peta untuk memilih lokasi
          map.on('click', function(e) {
              var clickedLat = e.latlng.lat;
              var clickedLon = e.latlng.lng;

              // hapus marker lama
              if(marker) map.removeLayer(marker);

              // buat marker baru
              marker = L.marker([clickedLat, clickedLon]).addTo(map)
                        .bindPopup("Lokasi Sekolah").openPopup();

              // update input tersembunyi
              document.getElementById('latitude').value = clickedLat;
              document.getElementById('longitude').value = clickedLon;
          });
        </script>

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

