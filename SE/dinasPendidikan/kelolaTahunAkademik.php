<?php
session_start();
include "../koneksi.php";

// Cek login dinas
if (!isset($_SESSION['dinas_id'])) {
    header("Location: loginDinas.php");
    exit;
}

$nama_dinas = $_SESSION['nama_dinas'];

// Tambah tahun akademik baru
if (isset($_POST['tambah_tahun'])) {
    $nama_tahun = trim($_POST['nama_tahun']);
    if ($nama_tahun !== '') {
        $stmt = $conn->prepare("INSERT INTO tahun_akademik (nama_tahun, status) VALUES (?, 'nonaktif')");
        $stmt->bind_param("s", $nama_tahun);
        $stmt->execute();
        $pesan = "Tahun akademik baru berhasil ditambahkan.";
    } else {
        $pesan = "Nama tahun akademik tidak boleh kosong.";
    }
}

// Aktifkan tahun akademik
if (isset($_POST['aktifkan'])) {
    $id = intval($_POST['aktifkan']);

    // Nonaktifkan semua dulu
    $conn->query("UPDATE tahun_akademik SET status='nonaktif'");

    // Aktifkan yang dipilih
    $stmt = $conn->prepare("UPDATE tahun_akademik SET status='aktif' WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $pesan = "Tahun akademik berhasil diaktifkan.";
}

// Hapus tahun akademik (jika tidak aktif)
if (isset($_POST['hapus'])) {
    $id = intval($_POST['hapus']);
    $cek = $conn->query("SELECT status FROM tahun_akademik WHERE id=$id")->fetch_assoc();
    if ($cek['status'] == 'aktif') {
        $pesan = "Tidak dapat menghapus tahun akademik yang sedang aktif.";
    } else {
        $stmt = $conn->prepare("DELETE FROM tahun_akademik WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $pesan = "Tahun akademik berhasil dihapus.";
    }
}

// Ambil data tahun akademik
$tahun_list = $conn->query("SELECT * FROM tahun_akademik ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Kelola Tahun Akademik</title>
  <link rel="stylesheet" href="../css/dashboardDinas.css">
  <link rel="stylesheet" href="../css/aturanSeleksi.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<aside class="sidebar">
  <h2>Dinas</h2>
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
    <h1>Dashboard Dinas Pendidikan</h1>
    <div class="user-info">
      <span><i class="fa-solid fa-user-tie"></i> <?php echo htmlspecialchars($nama_dinas); ?></span>
    </div>
  </header>

  <main>
    <?php if (isset($pesan)) echo "<p class='notif'>$pesan</p>"; ?>

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
        while ($row = $tahun_list->fetch_assoc()): 
        ?>
        <tr>
          <td><?= $no++; ?></td>
          <td><?= htmlspecialchars($row['nama_tahun']); ?></td>
          <td>
            <?php if ($row['status'] == 'aktif'): ?>
              <span style="color:green;font-weight:bold;">Aktif</span>
            <?php else: ?>
              <span style="color:gray;">Nonaktif</span>
            <?php endif; ?>
          </td>
          <td><?= $row['created_at']; ?></td>
          <td class="aksi">
            <?php if ($row['status'] == 'nonaktif'): ?>
              <form method="POST" style="display:inline;">
                <button type="submit" name="aktifkan" value="<?= $row['id']; ?>" class="btn-edit" title="Aktifkan">
                  <i class="fa-solid fa-check"></i>
                </button>
              </form>
              <form method="POST" style="display:inline;">
                <button type="submit" name="hapus" value="<?= $row['id']; ?>" class="btn-delete" 
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
