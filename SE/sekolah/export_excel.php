<?php
session_start();
include("../koneksi_mysql.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'sekolah') {
    header("Location: loginSekolah.php");
    exit;
}

$sekolah_id = $_POST['sekolah_id'] ?? 0;
$tahun_id = $_POST['tahun_id'] ?? 0;

if (!$sekolah_id || !$tahun_id) {
    die("Data tidak lengkap.");
}

// Ambil data siswa diterima
$stmt = $conn->prepare("
    SELECT 
        s.nama_lengkap,
        s.nisn,
        s.jk,
        p.jalur,
        p.tanggal_daftar
    FROM pendaftaran p
    JOIN siswa s ON p.siswa_id = s.id
    LEFT JOIN booking_daftar_ulang b 
        ON b.siswa_id = p.siswa_id 
       AND b.sekolah_id = ?
    WHERE p.tahun_id = ?
      AND p.sekolah_diterima = ?
      AND b.status = 'diterima'
    ORDER BY s.nama_lengkap ASC
");

$stmt->bind_param("iii", $sekolah_id, $tahun_id, $sekolah_id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Header untuk download Excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=siswa_diterima.xls");

// Buat tabel HTML
echo "<table border='1'>";
echo "<tr>
        <th>No</th>
        <th>Nama</th>
        <th>NISN</th>
        <th>Jenis Kelamin</th>
        <th>Jalur</th>
        <th>Tanggal Daftar</th>
      </tr>";

$no = 1;
foreach($data as $s){
    $jk = $s['jk'] === 'L' ? 'Laki-laki' : ($s['jk'] === 'P' ? 'Perempuan' : '-');
    echo "<tr>
            <td>{$no}</td>
            <td>{$s['nama_lengkap']}</td>
            <td>{$s['nisn']}</td>
            <td>{$jk}</td>
            <td>{$s['jalur']}</td>
            <td>{$s['tanggal_daftar']}</td>
          </tr>";
    $no++;
}

echo "</table>";
exit;
?>
