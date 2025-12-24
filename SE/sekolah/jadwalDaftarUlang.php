<?php
session_start();
include("../koneksi_mysql.php"); // $conn harus terhubung ke MySQL

// ============================
// CEK LOGIN SEKOLAH
// ============================
if (!isset($_SESSION['sekolah_id']) || $_SESSION['role'] !== 'sekolah') {
    header("Location: loginSekolah.php");
    exit;
}

$sekolah_id = $_SESSION['sekolah_id'];

// ============================
// TENTUKAN JALUR AKTIF (ZONASI / AKADEMIK)
// Bisa diganti dari aturan seleksi atau input admin
// ============================
$jalur_aktif = $_GET['jalur'] ?? 'zonasi';

if (!in_array($jalur_aktif, ['zonasi', 'akademik'])) {
    $jalur_aktif = 'zonasi';
}

// ============================
// AMBIL TAHUN AKADEMIK
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

// tahun terpilih
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

// ============================
// TAMBAH JADWAL
// ============================
if (isset($_POST['tambah_jadwal'])) {
    $tanggal = $_POST['tanggal'];
    $jam_mulai = $_POST['jam_mulai'];
    $jam_selesai = $_POST['jam_selesai'];
    $kuota_per_jam = intval($_POST['kuota_per_jam']);
    $jalur = $_POST['jalur'] ?? $jalur_aktif;

    if (!$tanggal || !$jam_mulai || !$jam_selesai || $kuota_per_jam <= 0 || !$jalur) {
        $error = "Semua kolom wajib diisi dengan benar!";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO jadwal_daftar_ulang
            (sekolah_id, tahun_id, tanggal, jam_mulai, jam_selesai, kuota_per_jam, jalur)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "iisssis",
            $sekolah_id,
            $selected_tahun,
            $tanggal,
            $jam_mulai,
            $jam_selesai,
            $kuota_per_jam,
            $jalur
        );
        if ($stmt->execute()) {
            $success = "Jadwal daftar ulang berhasil ditambahkan!";
        } else {
            $error = "Gagal menambahkan jadwal: " . $stmt->error;
        }
        $stmt->close();
    }
}

// ============================
// HAPUS JADWAL (AMAN PER TAHUN)
// ============================
if (isset($_GET['hapus']) && isset($selected_tahun)) {

    $hapus_id = intval($_GET['hapus']);

    $stmt = $conn->prepare("
        DELETE FROM jadwal_daftar_ulang
        WHERE id = ?
          AND sekolah_id = ?
          AND tahun_id = ?
    ");

    $stmt->bind_param("iii", $hapus_id, $sekolah_id, $selected_tahun);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $success = "Jadwal berhasil dihapus!";
        } else {
            $error = "Jadwal tidak ditemukan atau bukan milik tahun akademik ini.";
        }
    } else {
        $error = "Gagal menghapus jadwal: " . $stmt->error;
    }

    $stmt->close();
}

// ============================
// AMBIL SEMUA JADWAL SEKOLAH SESUAI JALUR AKTIF
// ============================
$stmt = $conn->prepare("
    SELECT * FROM jadwal_daftar_ulang
    WHERE sekolah_id = ?
      AND tahun_id = ?
      AND jalur = ?
    ORDER BY tanggal, jam_mulai
");
$stmt->bind_param("iis", $sekolah_id, $selected_tahun, $jalur_aktif);
$stmt->execute();
$result = $stmt->get_result();
$allJadwal = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Jadwal Daftar Ulang - PPDB Sekolah</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../css/dashboardSekolah.css">
<style>
.main-content {
    padding: 20px;
    margin-left: 250px;
    background: #f4f6f9;
    min-height: 100vh;
}
form {
    background: #fff; padding: 15px; border-radius: 8px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1); margin-bottom: 20px;
    display: flex; flex-wrap: wrap; gap: 10px; align-items: center;
}
form select, form input {
    padding: 8px;
    border: 1px solid #ccc;
    border-radius: 4px;
    flex: 1 1 180px;
}
form button {
    background: #007bff;
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 5px;
    cursor: pointer;
}
form button:hover { background: #0056b3; }
.alert {
    background: #d4edda;
    color: #155724;
    padding: 10px;
    border-radius: 5px;
    margin-bottom: 15px;
}
.alert.error {
    background: #f8d7da;
    color: #721c24;
}
table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    border-radius: 8px;
}
th, td {
    padding: 10px;
    border: 1px solid #ddd;
    text-align: center;
}
th { background: #f8f9fa; font-weight: bold; }
a.hapus {
    background: #dc3545; color: white;
    padding: 6px 10px; border-radius: 4px;
    text-decoration: none;
}
a.hapus:hover { background: #b02a37; }
</style>
</head>
<body>
<?php include("sidebarSekolah.php"); ?>

<div class="main-content">
    <?php include("headerSekolah.php"); ?>

    <form method="GET" style="margin-bottom:15px;">
    <label><strong>Tahun Akademik:</strong></label>
    <select name="tahun_id" onchange="this.form.submit()">
        <?php foreach ($tahunList as $t): ?>
        <option value="<?= $t['id']; ?>"
            <?= ($t['id'] == $selected_tahun) ? 'selected' : '' ?>>
            <?= htmlspecialchars($t['nama_tahun']); ?>
        </option>
        <?php endforeach; ?>
    </select>
    </form>

    <?php if (isset($success)): ?>
      <div class="alert"><?= htmlspecialchars($success) ?></div>
    <?php elseif (isset($error)): ?>
      <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <h2>Tambah Jadwal Daftar Ulang</h2>
    <form method="POST">
        <input type="date" name="tanggal" required>
        <input type="time" name="jam_mulai" required>
        <input type="time" name="jam_selesai" required>
        <input type="number" name="kuota_per_jam" min="1" placeholder="Kuota per Jam" required>
        <select name="jalur" required>
            <option value="">Pilih Jalur</option>
            <option value="zonasi" <?= $jalur_aktif=='zonasi'?'selected':'' ?>>Zonasi</option>
            <option value="akademik" <?= $jalur_aktif=='akademik'?'selected':'' ?>>Akademik</option>
        </select>
        <button type="submit" name="tambah_jadwal"><i class="fa fa-plus"></i> Tambah</button>
    </form>

    <form method="GET" style="margin-bottom:15px;">
        <input type="hidden" name="tahun_id" value="<?= $selected_tahun ?>">

        <label><strong>Lihat Jalur:</strong></label>
        <select name="jalur" onchange="this.form.submit()">
            <option value="zonasi" <?= $jalur_aktif=='zonasi'?'selected':'' ?>>Zonasi</option>
            <option value="akademik" <?= $jalur_aktif=='akademik'?'selected':'' ?>>Akademik</option>
        </select>
    </form>

    <h2>Daftar Jadwal Anda (Jalur: <?= htmlspecialchars(ucfirst($jalur_aktif)) ?>)</h2>
    <table>
        <tr>
            <th>No</th>
            <th>Tanggal</th>
            <th>Jam Mulai</th>
            <th>Jam Selesai</th>
            <th>Kuota / Jam</th>
            <th>Jalur</th>
            <th>Aksi</th>
        </tr>
        <?php
        if ($allJadwal):
            $no = 1;
            foreach ($allJadwal as $jd):
        ?>
        <tr>
            <td><?= $no++ ?></td>
            <td><?= htmlspecialchars($jd['tanggal']) ?></td>
            <td><?= htmlspecialchars($jd['jam_mulai']) ?></td>
            <td><?= htmlspecialchars($jd['jam_selesai']) ?></td>
            <td><?= htmlspecialchars($jd['kuota_per_jam']) ?></td>
            <td><?= htmlspecialchars(ucfirst($jd['jalur'])) ?></td>
            <td>
                <a href="?hapus=<?= $jd['id'] ?>&tahun_id=<?= $selected_tahun ?>&jalur=<?= $jalur_aktif ?>"class="hapus"onclick="return confirm('Hapus jadwal ini?')">
                    <i class="fa fa-trash"></i> Hapus
                </a>
            </td>
        </tr>
        <?php
            endforeach;
        else:
            echo "<tr><td colspan='7'>Belum ada jadwal dibuat.</td></tr>";
        endif;
        ?>
    </table>
</div>
</body>
</html>
