<?php
session_start();
include "../koneksi_mysql.php"; // koneksi MySQL

// Cek login dinas
if (!isset($_SESSION['dinas_id'])) {
    header("Location: loginDinas.php");
    exit;
}

$nama_dinas = $_SESSION['nama_dinas'];

// Ambil semua data sekolah dari MySQL
$sekolahData = [];
$q = mysqli_query($conn, "SELECT * FROM sekolah ORDER BY nama_sekolah ASC");
while($r = mysqli_fetch_assoc($q)) {
    $sekolahData[] = $r;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Kelola Sekolah</title>
<link rel="stylesheet" href="../css/dashboardDinas.css">
<link rel="stylesheet" href="../css/kelolaSekolah.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<?php include("sidebarDinas.php"); ?>

<div class="main-content">
  <?php include("headerDinas.php"); ?>

<main>
<table class="tabel-data">
  <thead>
    <tr>
      <th>No</th>
      <th>Nama Sekolah</th>
      <th>NPSN</th>
      <th>Email</th>
      <th>Alamat</th>
      <th>Kontak</th>
      <th>Kuota</th>
      <th>Aksi</th>
    </tr>
  </thead>
  <tbody>
    <?php
    if (!empty($sekolahData)) {
        $no = 1;
        foreach ($sekolahData as $s) {
            $id = $s['id'];
            echo "<tr>
                <td>{$no}</td>
                <td>" . htmlspecialchars($s['nama_sekolah'] ?? '-') . "</td>
                <td>" . htmlspecialchars($s['npsn'] ?? '-') . "</td>
                <td>" . htmlspecialchars($s['email'] ?? '-') . "</td>
                <td>" . htmlspecialchars($s['alamat'] ?? '-') . "</td>
                <td>" . htmlspecialchars($s['kontak'] ?? '-') . "</td>
                <td>" . htmlspecialchars($s['kuota'] ?? '-') . "</td>
                <td>
                    <a href='editSekolah.php?id={$id}' class='btn-edit'><i class='fa-solid fa-pen'></i> Edit</a>
                </td>
            </tr>";
            $no++;
        }
    } else {
        echo "<tr><td colspan='8'>Belum ada data sekolah.</td></tr>";
    }
    ?>
  </tbody>
</table>
</main>
</div>
</body>
</html>
