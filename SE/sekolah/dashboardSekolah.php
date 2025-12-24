<?php
session_start();
include("../koneksi_mysql.php"); // koneksi MySQL ($conn)

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
$stmt = $conn->prepare("SELECT * FROM sekolah WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $sekolah_id);
$stmt->execute();
$result = $stmt->get_result();
$sekolahData = $result->fetch_assoc();
$stmt->close();

if (!$sekolahData) {
    die("Data sekolah tidak ditemukan di database.");
}

$nama_sekolah = $sekolahData['nama_sekolah'] ?? "Sekolah";
$npsn         = $sekolahData['npsn'] ?? "-";
$alamat       = $sekolahData['alamat'] ?? "-";

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

// Tahun terpilih (default: tahun aktif / terbaru)
$selected_tahun = $_GET['tahun_id'] ?? null;

if (!$selected_tahun && !empty($tahunList)) {
    foreach ($tahunList as $t) {
        if ($t['status'] === 'aktif') {
            $selected_tahun = $t['id'];
            break;
        }
    }
    $selected_tahun ??= $tahunList[0]['id'];
}

// ============================
// AMBIL DATA PENDAFTAR
// ============================
$stmt = $conn->prepare("
    SELECT *
    FROM pendaftaran
    WHERE tahun_id = ?
      AND (pilihan_ke1 = ? OR pilihan_ke2 = ? OR pilihan_ke3 = ?)
");
$stmt->bind_param("iiii", $selected_tahun, $sekolah_id, $sekolah_id, $sekolah_id);

$stmt->execute();
$result = $stmt->get_result();
$pendaftaranData = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$total_pendaftar = count($pendaftaranData);

// ============================
// HITUNG TOTAL SISWA LULUS
// ============================
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total_lulus
    FROM pendaftaran
    WHERE tahun_id = ?
      AND sekolah_diterima = ?
");
$stmt->bind_param("ii", $selected_tahun, $sekolah_id);

$stmt->execute();
$result = $stmt->get_result();
$rowLulus = $result->fetch_assoc();
$total_lulus = $rowLulus['total_lulus'] ?? 0;

$stmt->close();

// ============================
// HITUNG TOTAL BOOKING DAFTAR ULANG (UNIK PER SISWA)
// ============================
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT b.siswa_id) AS total_booking
    FROM booking_daftar_ulang b
    JOIN pendaftaran p ON b.siswa_id = p.siswa_id
    WHERE p.tahun_id = ?
      AND b.sekolah_id = ?
      AND b.status = 'booking'
");
$stmt->bind_param("ii", $selected_tahun, $sekolah_id);
$stmt->execute();
$total_booking = $stmt->get_result()->fetch_assoc()['total_booking'] ?? 0;
$stmt->close();

// ============================
// HITUNG TOTAL SUDAH DAFTAR ULANG (UNIK PER SISWA)
// ============================
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT b.siswa_id) AS total_du
    FROM booking_daftar_ulang b
    JOIN pendaftaran p ON b.siswa_id = p.siswa_id
    WHERE p.tahun_id = ?
      AND b.sekolah_id = ?
      AND b.status_keterangan != 'Belum Hadir'
");
$stmt->bind_param("ii", $selected_tahun, $sekolah_id);
$stmt->execute();
$total_daftar_ulang = $stmt->get_result()->fetch_assoc()['total_du'] ?? 0;
$stmt->close();

// ============================
// TIMELINE PPDB (SESUI TAHUN DIPILIH)
// ============================

$today = date("Y-m-d");
$selected_tahun = (int)$selected_tahun;

// Ambil aturan seleksi & zonasi SESUAI TAHUN
$qSeleksi = mysqli_query($conn, "
    SELECT *
    FROM aturan_seleksi
    WHERE tahun_akademik_id = $selected_tahun
    ORDER BY id DESC
    LIMIT 1
");
$aturanSeleksi = mysqli_fetch_assoc($qSeleksi) ?? [];

$qZonasi = mysqli_query($conn, "
    SELECT *
    FROM aturan_zonasi
    WHERE tahun_akademik_id = $selected_tahun
    ORDER BY id DESC
    LIMIT 1
");
$aturanZonasi = mysqli_fetch_assoc($qZonasi) ?? [];

// Tentukan jalur aktif BERDASARKAN TAHUN TERPILIH
$mulaiZonasi   = $aturanZonasi['tanggal_mulai'] ?? null;
$mulaiSeleksi = $aturanSeleksi['tanggal_mulai'] ?? null;

$akhirZonasi = $mulaiSeleksi
    ? date("Y-m-d", strtotime("$mulaiSeleksi -1 day"))
    : ($aturanZonasi['tanggal_selesai'] ?? null);

$akhirSeleksi = $aturanSeleksi['tanggal_daftar_ulang']
    ?? ($aturanSeleksi['tanggal_selesai'] ?? null);

$jalurAktif = "Belum Dibuka";

if ($mulaiZonasi && $akhirZonasi && $today >= $mulaiZonasi && $today <= $akhirZonasi) {
    $jalurAktif = "Zonasi";
} elseif ($mulaiSeleksi && $akhirSeleksi && $today >= $mulaiSeleksi && $today <= $akhirSeleksi) {
    $jalurAktif = "Akademik";
}

// Pilih aturan berdasarkan jalur
if ($jalurAktif === "Akademik") {
    $aturan = $aturanSeleksi;
} elseif ($jalurAktif === "Zonasi") {
    $aturan = $aturanZonasi;
    $aturan['tanggal_mos']   = $aturanSeleksi['tanggal_mos'] ?? null;
    $aturan['tanggal_masuk'] = $aturanSeleksi['tanggal_masuk'] ?? null;
} else {
    $aturan = [];
}

// Helper format tanggal
function f($tgl) {
    return $tgl ? date("d F Y", strtotime($tgl)) : "-";
}

$tanggal_mulai        = $aturan['tanggal_mulai'] ?? null;
$tanggal_selesai      = $aturan['tanggal_selesai'] ?? null;
$tanggal_pengumuman   = $aturan['tanggal_pengumuman'] ?? null;
$tanggal_daftar_ulang = $aturan['tanggal_daftar_ulang'] ?? null;
$tanggal_mos          = $aturan['tanggal_mos'] ?? null;
$tanggal_masuk        = $aturan['tanggal_masuk'] ?? null;

// ============================
// Siswa Lulus (5 terbaru)
// ============================
$stmt = $conn->prepare("
    SELECT 
        p.id,
        s.nama_lengkap,
        s.nisn,
        p.jalur,
        b.status,
        b.status_keterangan
    FROM pendaftaran p
    JOIN siswa s ON p.siswa_id = s.id
    LEFT JOIN booking_daftar_ulang b
      ON b.id = (
          SELECT id
          FROM booking_daftar_ulang
          WHERE siswa_id = p.siswa_id
            AND sekolah_id = ?
          ORDER BY id DESC
          LIMIT 1
      )
    WHERE p.tahun_id = ?
      AND p.sekolah_diterima = ?
    ORDER BY p.tanggal_daftar DESC
    LIMIT 5
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
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Sekolah - PPDB</title>
  <link rel="stylesheet" href="../css/dashboardSekolah.css">
  <link rel="stylesheet" href="../css/timeline.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
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
<body>
<?php include("sidebarSekolah.php"); ?>

  <!-- Main Content -->
  <div class="main-content">
    <?php include("headerSekolah.php"); ?>

  <form method="GET" style="margin-bottom:15px;">
    <label><strong>Tahun Akademik:</strong></label>
    <select name="tahun_id"  style="padding:8px;border-radius:6px;" onchange="this.form.submit()">
      <?php foreach ($tahunList as $t): ?>
        <option value="<?= $t['id']; ?>"
          <?= ($t['id'] == $selected_tahun) ? 'selected' : '' ?>>
          <?= htmlspecialchars($t['nama_tahun']); ?>
        </option>
      <?php endforeach; ?>
    </select>
  </form>

    <!-- Cards Ringkasan -->
    <div class="cards">
      <div class="card">
        <h3>Total Pendaftar</h3>
        <p><?= $total_pendaftar; ?></p>
      </div>
      <div class="card">
        <h3>Siswa Lulus</h3>
        <p><?= $total_lulus; ?></p>
      </div>
      <div class="card">
        <h3>Booking Daftar Ulang</h3>
        <p><?= $total_booking; ?></p>
      </div>
      <div class="card">
        <h3>Sudah Daftar Ulang</h3>
        <p><?= $total_daftar_ulang; ?></p>
      </div>
    </div>

<!-- Timeline PPDB -->
<div class="timeline-horizontal">
  <h2>
    <i class="fa-solid fa-calendar-alt"></i>
    Jadwal PPDB <?= $jalurAktif ?>
  </h2>

<?php
$stages = [
  ["label"=>"Pendaftaran","start"=>$tanggal_mulai,"end"=>$tanggal_selesai,"icon"=>"fa-calendar-day","range"=>true],
  ["label"=>"Pengumuman","start"=>$tanggal_pengumuman,"end"=>$tanggal_pengumuman,"icon"=>"fa-bullhorn"],
  ["label"=>"Daftar Ulang","start"=>$tanggal_daftar_ulang,"end"=>$tanggal_daftar_ulang,"icon"=>"fa-clipboard-check"],
  ["label"=>"MOS","start"=>$tanggal_mos,"end"=>$tanggal_mos,"icon"=>"fa-people-group"],
  ["label"=>"Masuk Sekolah","start"=>$tanggal_masuk,"end"=>$tanggal_masuk,"icon"=>"fa-school"]
];
?>

<div class="timeline-track">
<?php foreach ($stages as $stage):
  $start = $stage['start'];
  $end   = $stage['end'];
  $range = $stage['range'] ?? false;

  if (!$start) $status = "inactive";
  elseif ($today < $start) $status = "upcoming";
  elseif ($today >= $start && $today <= ($end ?? $start)) $status = "active";
  else $status = "done";
?>
  <div class="timeline-step <?= $status ?>">
    <div class="timeline-icon">
      <i class="fa-solid <?= $stage['icon'] ?>"></i>
    </div>
    <p><?= $stage['label'] ?></p>
    <span>
      <?php if ($range): ?>
        <?= f($start) ?> – <?= f($end) ?>
      <?php else: ?>
        <?= f($start) ?>
      <?php endif; ?>
    </span>
  </div>
<?php endforeach; ?>
</div>
</div>

      <section class="table-section">
        <h2>Daftar Siswa Lulus</h2>

        <table>
          <thead>
            <tr>
              <th>No</th>
              <th>Nama</th>
              <th>NISN</th>
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
              <td colspan="5" style="text-align:center;">
                Belum ada siswa lulus
              </td>
            </tr>
          <?php endif; ?>
          </tbody>
        </table>

        <!-- Tombol lihat semua -->
        <?php?>
          <div style="text-align:right;margin-top:10px;">
            <a href="siswaLulus.php" class="btn btn-secondary">
              <i class="fas fa-eye"></i> Lihat Semua
            </a>
          </div>
        <?php?>
      </section>
  </div>
</body>
</html>
