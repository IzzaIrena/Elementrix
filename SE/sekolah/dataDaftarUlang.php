<?php
session_start();
include("../koneksi_mysql.php");

// ============================
// CEK LOGIN SEKOLAH
// ============================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'sekolah') {
    header("Location: loginSekolah.php");
    exit;
}

$sekolah_id = $_SESSION['sekolah_id'];

// ============================
// AMBIL DATA SEKOLAH
// ============================
$qSekolah = mysqli_query($conn, "SELECT * FROM sekolah WHERE id='$sekolah_id'");
if (mysqli_num_rows($qSekolah) == 0) {
    die("Data sekolah tidak ditemukan.");
}
$sekolahData = mysqli_fetch_assoc($qSekolah);
$nama_sekolah = $sekolahData['nama_sekolah'] ?? "Sekolah";
$npsn = $sekolahData['npsn'] ?? "-";

// ============================
// AMBIL SEMUA TAHUN AKADEMIK
// ============================
$qTahun = mysqli_query(
    $conn,
    "SELECT id, nama_tahun 
     FROM tahun_akademik
     ORDER BY nama_tahun DESC"
);

$tahunList = [];
while ($row = mysqli_fetch_assoc($qTahun)) {
    $tahunList[] = $row;
}

// tahun terpilih (WAJIB ADA, default: tahun aktif)
$tahun_id = isset($_GET['tahun_id'])
    ? intval($_GET['tahun_id'])
    : ($tahunList[0]['id'] ?? 0);

// ============================
// JALUR SELEKSI (ZONASI / AKADEMIK)
// ============================
$jalur = isset($_GET['jalur']) ? $_GET['jalur'] : 'zonasi';

// validasi sederhana
if (!in_array($jalur, ['zonasi', 'akademik'])) {
    $jalur = 'zonasi';
}

// ============================
// AMBIL DATA DAFTAR ULANG (BOOKING)
// ============================
$whereTahun = "";
if ($tahun_id > 0) {
    $whereTahun = "AND p.tahun_id = '$tahun_id'";
}

$qBooking = mysqli_query(
    $conn,
    "SELECT 
        b.id,
        b.siswa_id,
        b.nama,
        b.tanggal_booking,
        b.jam_booking,
        b.status,
        b.status_keterangan,
        b.qr_code,
        s.nama_lengkap,
        p.tahun_id
     FROM booking_daftar_ulang b
     INNER JOIN (
        SELECT siswa_id, MAX(id) AS id_terbaru
        FROM booking_daftar_ulang
        GROUP BY siswa_id
     ) last_b ON b.id = last_b.id_terbaru
     LEFT JOIN siswa s ON s.id = b.siswa_id
     LEFT JOIN pendaftaran p 
        ON p.siswa_id = b.siswa_id 
        AND p.sekolah_diterima = b.sekolah_id
     WHERE 
        b.sekolah_id = '$sekolah_id'
        AND p.tahun_id = '$tahun_id'
        AND p.jalur = '$jalur'
     ORDER BY 
        CASE WHEN b.timestamp_scan IS NULL THEN 1 ELSE 0 END,
        b.timestamp_scan DESC"
);

$bookings = [];
while ($row = mysqli_fetch_assoc($qBooking)) {
    $bookings[] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Data Daftar Ulang - <?= htmlspecialchars($nama_sekolah) ?></title>
  <link rel="stylesheet" href="../css/dashboardSekolah.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    .main-content table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 20px;
      background: white;
      border-radius: 10px;
      overflow: hidden;
    }
    table th, table td {
      padding: 12px 14px;
      border-bottom: 1px solid #ddd;
      text-align: center;
    }
    table th {
      background: #2b5dff;
      color: white;
    }
    .no-data {
      text-align: center;
      padding: 40px;
      color: #666;
      background: #fff;
      border-radius: 10px;
      margin-top: 20px;
    }
    .qr-link {
      color: #2b5dff;
      text-decoration: none;
      font-weight: bold;
    }
    .qr-link:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>
<?php include("sidebarSekolah.php"); ?>

  <!-- Main Content -->
  <div class="main-content">
    <?php include("headerSekolah.php"); ?>

    <form method="GET" style="margin-bottom:15px; display:flex; gap:15px; align-items:center;">
      
      <label><strong>Tahun Akademik:</strong></label>
      <select name="tahun_id" onchange="this.form.submit()" style="padding:8px;border-radius:6px;" required>
        <?php foreach ($tahunList as $t): ?>
          <option value="<?= $t['id'] ?>" <?= ($tahun_id == $t['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($t['nama_tahun']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <label><strong>Jalur:</strong></label>
      <select name="jalur" onchange="this.form.submit()" style="padding:8px;border-radius:6px;">
        <option value="zonasi" <?= ($jalur == 'zonasi') ? 'selected' : '' ?>>Zonasi</option>
        <option value="akademik" <?= ($jalur == 'akademik') ? 'selected' : '' ?>>Akademik</option>
      </select>

    </form>

    <section class="table-section">
      <?php if (!empty($bookings)): ?>
      <table>
        <thead>
          <tr>
            <th>No</th>
            <th>Nama Siswa</th>
            <th>Tanggal</th>
            <th>Jam</th>
            <th>Status</th>
            <th>Keterangan</th>
            <th>QR Code</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody id="table-body">
        <?php 
          $no = 1;
          foreach ($bookings as $b):
        ?>
          <tr>
            <td><?= $no++ ?></td>
            <td><?= htmlspecialchars($b['nama_lengkap']) ?></td>
            <td>
              <?= $b['tanggal_booking'] ? htmlspecialchars($b['tanggal_booking']) : '-' ?>
            </td>
            <td>
              <?= $b['jam_booking'] ? htmlspecialchars($b['jam_booking']) : '-' ?>
            </td>
            <td><?= htmlspecialchars(ucfirst($b['status'])) ?></td>
            <td>
              <?php 
                $ket = $b['status_keterangan'] ?? 'Belum Hadir';

                // Warna otomatis
                if ($ket == "Hadir")      $color = "green";
                elseif ($ket == "Telat")  $color = "red";
                elseif ($ket == "Menunggu Scan Ulang") $color = "orange";   
                else                      $color = "gray";
              ?>
              <span style="font-weight:bold; color:<?= $color ?>;">
                <?= htmlspecialchars($ket) ?>
              </span>
            </td>
            <td>
              <?php if (!empty($b['qr_code'])): ?>
                <a href="<?= htmlspecialchars($b['qr_code']) ?>" target="_blank" class="qr-link">
                  <i class="fa-solid fa-qrcode"></i> Lihat
                </a>
              <?php else: ?>
                -
              <?php endif; ?>
            </td>
            <td>
              <a href="detailPendaftaran.php?siswa_id=<?= $b['siswa_id'] ?>" 
                class="qr-link" 
                style="color:green;">
                <i class="fa-solid fa-eye"></i> Detail
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>

      <?php else: ?>
      <div class="no-data">
        <i class="fa-solid fa-circle-info"></i><br>
        Belum ada siswa yang melakukan daftar ulang.
      </div>
      <?php endif; ?>
    </section>
  </div>

<script>
  const tahunId = "<?= $tahun_id ?>";
  const jalur   = "<?= $jalur ?>";

  setInterval(() => {
      fetch("poll_firebase.php");

      fetch(`dataDaftarUlangTable.php?tahun_id=${tahunId}&jalur=${jalur}`)
          .then(response => response.text())
          .then(html => {
              document.getElementById("table-body").innerHTML = html;
          });
  }, 2000);
</script>

</body>
</html>
