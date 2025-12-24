<?php
session_start();
require "../koneksi_mysql.php"; // koneksi MySQL

// Cek login dinas
if (!isset($_SESSION['dinas_id'])) {
    header("Location: loginDinas.php");
    exit;
}

$nama_dinas = $_SESSION['nama_dinas'];
$pesan = "";

// -----------------------------
// TAMBAH TAHUN AKADEMIK
// -----------------------------
if (isset($_POST['tambah_tahun'])) {
    $nama_tahun = trim($_POST['nama_tahun']);

    if ($nama_tahun != "") {

        $stmt = $conn->prepare("
            INSERT INTO tahun_akademik (nama_tahun, status, created_at) 
            VALUES (?, 'nonaktif', NOW())
        ");
        $stmt->bind_param("s", $nama_tahun);
        $stmt->execute();
        $stmt->close();

        $pesan = "Tahun akademik baru berhasil ditambahkan.";
    } else {
        $pesan = "Nama tahun akademik tidak boleh kosong.";
    }
}

// -----------------------------
// AKTIFKAN TAHUN AKADEMIK
// -----------------------------
if (isset($_POST['aktifkan'])) {
    $id = $_POST['aktifkan'];

    // Nonaktifkan semua tahun dulu
    $conn->query("UPDATE tahun_akademik SET status = 'nonaktif'");

    // Aktifkan tahun yang dipilih
    $stmt = $conn->prepare("UPDATE tahun_akademik SET status = 'aktif' WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    $pesan = "Tahun akademik berhasil diaktifkan.";
}

// -----------------------------
// HAPUS TAHUN AKADEMIK
// -----------------------------
if (isset($_POST['hapus'])) {
    $id = $_POST['hapus'];

    // Cek status tahun
    $cek = $conn->prepare("SELECT status FROM tahun_akademik WHERE id = ?");
    $cek->bind_param("i", $id);
    $cek->execute();
    $result = $cek->get_result()->fetch_assoc();
    $cek->close();

    if ($result['status'] === 'aktif') {
        $pesan = "Tidak dapat menghapus tahun akademik yang sedang aktif.";
    } else {
        $del = $conn->prepare("DELETE FROM tahun_akademik WHERE id = ?");
        $del->bind_param("i", $id);
        $del->execute();
        $del->close();

        $pesan = "Tahun akademik berhasil dihapus.";
    }
}

// -----------------------------
// AMBIL LIST TAHUN AKADEMIK
// -----------------------------
$tahunList = $conn->query("SELECT * FROM tahun_akademik ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Kelola Tahun Akademik</title>
<link rel="stylesheet" href="../css/dashboardDinas.css">
<link rel="stylesheet" href="../css/kelolaTahunAkademik.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<?php include("sidebarDinas.php"); ?>
<div class="main-content">
<?php include("headerDinas.php"); ?>

<main>
    <?php if($pesan != ""): ?>
        <p class="notif"><?= htmlspecialchars($pesan); ?></p>
    <?php endif; ?>

    <h3>Kelola Tahun Akademik</h3>

    <form method="POST" class="tahun-form">
        <input type="text" name="nama_tahun" placeholder="Contoh: 2025/2026" required>
        <button type="submit" name="tambah_tahun">
            <i class="fa-solid fa-plus"></i> Tambah Tahun
        </button>
    </form>

    <table class="item-table">
        <thead>
            <tr>
                <th>No</th>
                <th>Nama Tahun Akademik</th>
                <th>Status</th>
                <th>Dibuat Pada</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            while($tahun = $tahunList->fetch_assoc()):
            ?>
            <tr>
                <td><?= $no++; ?></td>
                <td><?= htmlspecialchars($tahun['nama_tahun']); ?></td>
                <td>
                    <?php if($tahun['status'] === 'aktif'): ?>
                        <span style="color:green;font-weight:bold;">Aktif</span>
                    <?php else: ?>
                        <span style="color:gray;">Nonaktif</span>
                    <?php endif; ?>
                </td>
                <td><?= $tahun['created_at']; ?></td>
                <td class="aksi">

                    <?php if($tahun['status'] === 'nonaktif'): ?>

                        <form method="POST" style="display:inline;">
                            <button type="submit" name="aktifkan" value="<?= $tahun['id']; ?>" class="btn-edit" title="Aktifkan">
                                <i class="fa-solid fa-check"></i>
                            </button>
                        </form>

                        <form method="POST" style="display:inline;">
                            <button type="submit" name="hapus" value="<?= $tahun['id']; ?>" class="btn-delete"
                                    onclick="return confirm('Yakin ingin menghapus tahun akademik ini?')" title="Hapus">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </form>

                    <?php else: ?>
                        <i class="fa-solid fa-lock" style="color:gray;" title="Sedang Aktif"></i>
                    <?php endif; ?>

                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</main>

</div>
</body>
</html>
