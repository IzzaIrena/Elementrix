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
// AMBIL DAFTAR TAHUN AKADEMIK
// ============================
$tahunList = [];
$qTahun = mysqli_query($conn, "
    SELECT id, nama_tahun, status
    FROM tahun_akademik
    ORDER BY created_at DESC
");

while ($row = mysqli_fetch_assoc($qTahun)) {
    $tahunList[] = $row;
}

// ============================
// TAHUN TERPILIH
// ============================
$selected_tahun = $_GET['tahun_id'] ?? null;

// default ke tahun aktif
if (!$selected_tahun && !empty($tahunList)) {
    foreach ($tahunList as $t) {
        if ($t['status'] === 'aktif') {
            $selected_tahun = $t['id'];
            break;
        }
    }
    $selected_tahun ??= $tahunList[0]['id'];
}

$status_filter = $_GET['status_filter'] ?? '';
$filterSQL = "";
switch ($status_filter) {
    case 'belum_booking':
        $filterSQL = " AND b.status IS NULL ";
        break;

    case 'booking_belum_hadir':
        $filterSQL = " AND b.status = 'booking' AND b.status_keterangan = 'Belum Hadir' ";
        break;

    case 'booking_hadir':
        $filterSQL = " AND b.status = 'booking' AND b.status_keterangan = 'Hadir' ";
        break;

    case 'booking_telat':
        $filterSQL = " AND b.status = 'booking' AND b.status_keterangan = 'Telat' ";
        break;

    case 'diterima':
        $filterSQL = " AND b.status = 'diterima' ";
        break;

    case 'ditolak':
        $filterSQL = " AND b.status = 'ditolak' ";
        break;

    case 'ditunda':
        $filterSQL = " AND b.status = 'ditunda' ";
        break;
}

// ============================
// AMBIL DATA SISWA LULUS
// ============================
$stmt = $conn->prepare("
    SELECT 
        p.id,
        s.nama_lengkap,
        s.nisn,
        s.jk,
        p.jalur,
        p.tanggal_daftar,
        b.status,
        b.status_keterangan
    FROM pendaftaran p
    JOIN siswa s ON p.siswa_id = s.id
    LEFT JOIN booking_daftar_ulang b 
        ON b.siswa_id = p.siswa_id 
       AND b.sekolah_id = ?
    WHERE p.tahun_id = ?
      AND p.sekolah_diterima = ?
      $filterSQL
    ORDER BY s.nama_lengkap ASC
");

$stmt->bind_param("iii", $sekolah_id, $selected_tahun, $sekolah_id);
$stmt->execute();
$siswa_lulus = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Daftar Siswa Lulus</title>
<link rel="stylesheet" href="../css/dashboardSekolah.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
    .action-bar {
        margin-top: 20px;
        display: flex;
        gap: 12px;
    }

    /* Base button */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 18px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.25s ease;
        box-shadow: 0 4px 10px rgba(0,0,0,0.08);
    }
    /* Secondary (Kembali) */
    .btn-secondary {
        background: #f1f5f9;
        color: #1e293b;
        border: 1px solid #cbd5e1;
    }

    .btn-secondary:hover {
        background: #e2e8f0;
        transform: translateY(-2px);
        box-shadow: 0 6px 12px rgba(0,0,0,0.12);
    }

    /* Icon kecil biar rapi */
    .btn i {
        font-size: 13px;
    }
</style>
</head>

<body>
<?php include("sidebarSekolah.php"); ?>

<div class="main-content">
<?php include("headerSekolah.php"); ?>

<form method="GET" style="margin-bottom:15px; display:flex; gap:15px; align-items:center;">
  
  <!-- Tahun -->
  <div>
    <label><strong>Tahun Akademik:</strong></label><br>
    <select name="tahun_id" onchange="this.form.submit()" style="padding:8px;border-radius:6px;">
      <?php foreach ($tahunList as $t): ?>
        <option value="<?= $t['id']; ?>"
          <?= ($t['id'] == $selected_tahun) ? 'selected' : '' ?>>
          <?= htmlspecialchars($t['nama_tahun']); ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <!-- Status -->
  <div>
    <label><strong>Status Daftar Ulang:</strong></label><br>
    <select name="status_filter" onchange="this.form.submit()" style="padding:8px;border-radius:6px;">
    <option value="" <?= $status_filter === '' ? 'selected' : '' ?>>Semua</option>
    <option value="belum_booking" <?= $status_filter === 'belum_booking' ? 'selected' : '' ?>>Belum Booking</option>
    <option value="booking_belum_hadir" <?= $status_filter === 'booking_belum_hadir' ? 'selected' : '' ?>>Booking – Belum Hadir</option>
    <option value="booking_hadir" <?= $status_filter === 'booking_hadir' ? 'selected' : '' ?>>Booking – Hadir</option>
    <option value="booking_telat" <?= $status_filter === 'booking_telat' ? 'selected' : '' ?>>Booking – Telat</option>
    <option value="diterima" <?= $status_filter === 'diterima' ? 'selected' : '' ?>>Diterima</option>
    <option value="ditolak" <?= $status_filter === 'ditolak' ? 'selected' : '' ?>>Ditolak</option>
    <option value="ditunda" <?= $status_filter === 'ditunda' ? 'selected' : '' ?>>Ditunda</option>
    </select>
  </div>

</form>


<!-- TABEL SISWA LULUS -->
<section class="table-section">
<h2>Daftar Siswa Lulus</h2>

<?php if($status_filter === 'diterima'): ?>
  <form method="POST" action="export_excel.php" style="margin-bottom:15px;">
      <input type="hidden" name="tahun_id" value="<?= $selected_tahun ?>">
      <input type="hidden" name="sekolah_id" value="<?= $sekolah_id ?>">
      <button type="submit" class="btn btn-secondary">
          <i class="fas fa-file-excel"></i> Export Excel
      </button>
  </form>
<?php endif; ?>

<table>
<thead>
<tr>
  <th>No</th>
  <th>Nama</th>
  <th>NISN</th>
  <th>Jenis Kelamin</th>
  <th>Jalur</th>
  <th>Status Daftar Ulang</th>
</tr>
</thead>
<tbody>

<?php if (!empty($siswa_lulus)): 
    $no = 1;
    foreach ($siswa_lulus as $s): ?>
<tr>
  <td><?= $no++; ?></td>
  <td><?= htmlspecialchars($s['nama_lengkap']); ?></td>
  <td><?= htmlspecialchars($s['nisn']); ?></td>
  <td>
    <?= $s['jk'] === 'L' ? 'Laki-laki' : ($s['jk'] === 'P' ? 'Perempuan' : '-') ?>
  </td>
  <td><?= ucfirst($s['jalur']); ?></td>
    <td>
    <?php
    $status = $s['status'] ?? null;
    $ket    = $s['status_keterangan'] ?? null;

    if (!$status) {
        echo "<span style='color:#999'>Belum Booking</span>";
    }
    elseif ($status === 'booking') {
        if ($ket === 'Belum Hadir') {
            echo "<span style='color:orange'>Booking (Belum Hadir)</span>";
        } elseif ($ket === 'Hadir') {
            echo "<span style='color:blue'>Hadir – Menunggu Verifikasi</span>";
        } elseif ($ket === 'Telat') {
            echo "<span style='color:#b8860b'>Telat – Menunggu Verifikasi</span>";
        } else {
            echo "<span style='color:orange'>Booking</span>";
        }
    }
    elseif ($status === 'diterima') {
        echo "<span style='color:green;font-weight:bold'>Diterima</span>";
    }
    elseif ($status === 'ditolak') {
        echo "<span style='color:red;font-weight:bold'>Ditolak</span>";
    }
    elseif ($status === 'ditunda') {
        echo "<span style='color:#555;font-weight:bold'>Ditunda</span>";
    }
    ?>
    </td>
</tr>
<?php endforeach; else: ?>
<tr>
  <td colspan="6" style="text-align:center;color:gray;">
    Belum ada data
  </td>
</tr>
<?php endif; ?>

</tbody>
</table>

<div class="action-bar">
  <a href="dashboardSekolah.php" class="btn btn-secondary">
    <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
  </a>
</div>

</section>
</div>
</body>
</html>
