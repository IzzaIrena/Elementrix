<?php
session_start();
include "../koneksi.php";

// Cek login dan role sekolah
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'sekolah') {
    header("Location: login.php");
    exit();
}

$sekolah_id = $_SESSION['sekolah_id'];

// Handle form tambah jadwal
if(isset($_POST['tambah_jadwal'])){
    $tanggal = $_POST['tanggal'];
    $jam_mulai = $_POST['jam_mulai'];
    $jam_selesai = $_POST['jam_selesai'];
    $kuota = $_POST['kuota'];

    $stmt = $conn->prepare("INSERT INTO jadwal_daftar_ulang (sekolah_id, tanggal, jam_mulai, jam_selesai, kuota_per_jam) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isssi", $sekolah_id, $tanggal, $jam_mulai, $jam_selesai, $kuota);
    $stmt->execute();
    $stmt->close();
    header("Location: jadwalDaftarUlang.php");
    exit();
}

// Ambil semua jadwal daftar ulang sekolah
$sqlJadwal = "SELECT * FROM jadwal_daftar_ulang WHERE sekolah_id = ? ORDER BY tanggal, jam_mulai";
$stmt = $conn->prepare($sqlJadwal);
$stmt->bind_param("i", $sekolah_id);
$stmt->execute();
$result_jadwal = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Jadwal Daftar Ulang - Sekolah</title>
<link rel="stylesheet" href="../css/dashboardSekolah.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
table{width:100%; border-collapse:collapse; margin-top:10px;}
th, td{border:1px solid #ccc; padding:8px; text-align:center;}
th{background:#f2f2f2;}
button{padding:5px 10px; cursor:pointer;}
</style>
</head>
<body>
<div class="sidebar">
    <h2><?php echo htmlspecialchars($_SESSION['nama_sekolah']); ?></h2>
    <ul>
      <li><a href="dashboardSekolah.php"><i class="fa fa-home"></i> Beranda</a></li>
      <li><a href="dataPendaftar.php"><i class="fa fa-users"></i> Data Pendaftar</a></li>
      <li><a href="pengumumanSeleksi.php"><i class="fa fa-bullhorn"></i> Pengumuman Seleksi</a></li>
      <li><a href="jadwalDaftarUlang.php" class="active"><i class="fa fa-calendar"></i> Jadwal Daftar Ulang</a></li>
      <li><a href="daftarUlang.php"><i class="fa fa-clipboard-check"></i> Data Daftar Ulang</a></li>
      <li><a href="logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</div>

<div class="main-content">
<header>
    <h1>Jadwal Daftar Ulang</h1>
</header>

<section class="table-section">
    <h2>Tambah Jadwal Baru</h2>
    <form method="POST">
        <label>Tanggal: <input type="date" name="tanggal" required></label>
        <label>Jam Mulai: <input type="time" name="jam_mulai" required></label>
        <label>Jam Selesai: <input type="time" name="jam_selesai" required></label>
        <label>Kuota per Jam: <input type="number" name="kuota" value="0" min="0" required></label>
        <button type="submit" name="tambah_jadwal"><i class="fa fa-plus"></i> Tambah</button>
    </form>

    <h2>Daftar Jadwal Sekolah</h2>
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>Jam Mulai</th>
                <th>Jam Selesai</th>
                <th>Kuota per Jam</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $no = 1;
            if($result_jadwal->num_rows > 0){
                while($row = $result_jadwal->fetch_assoc()){
                    echo "<tr>
                            <td>{$no}</td>
                            <td>".date("d F Y", strtotime($row['tanggal']))."</td>
                            <td>{$row['jam_mulai']}</td>
                            <td>{$row['jam_selesai']}</td>
                            <td>{$row['kuota_per_jam']}</td>
                          </tr>";
                    $no++;
                }
            } else {
                echo "<tr><td colspan='5' style='text-align:center;'>Belum ada jadwal</td></tr>";
            }
            ?>
        </tbody>
    </table>

    <h2>Slot Per Jam</h2>
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>Jam Slot</th>
                <th>Kuota</th>
                <th>Terisi</th>
                <th>Sisa Kuota</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Ambil ulang jadwal untuk slot
            $stmt->execute();
            $result_jadwal = $stmt->get_result();
            $no = 1;
            if($result_jadwal->num_rows > 0){
                while($row = $result_jadwal->fetch_assoc()){
                    $tanggal = $row['tanggal'];
                    $jam_mulai = strtotime($row['jam_mulai']);
                    $jam_selesai = strtotime($row['jam_selesai']);
                    $kuota = $row['kuota_per_jam'];

                    // Buat slot per jam
                    for($jam = $jam_mulai; $jam < $jam_selesai; $jam += 3600){
                        $slot = date("H:i", $jam)." - ".date("H:i", $jam+3600);

                        // Hitung terisi berdasarkan slot
                        $sql_terisi = "SELECT COUNT(*) as terisi FROM booking_daftar_ulang WHERE jadwal_id = ? AND jam_slot = ?";
                        $stmt2 = $conn->prepare($sql_terisi);
                        $stmt2->bind_param("is", $row['id'], $slot);
                        $stmt2->execute();
                        $terisi = $stmt2->get_result()->fetch_assoc()['terisi'];
                        $stmt2->close();

                        $sisa = $kuota - $terisi;

                        echo "<tr>
                                <td>{$no}</td>
                                <td>".date("d F Y", strtotime($tanggal))."</td>
                                <td>{$slot}</td>
                                <td>{$kuota}</td>
                                <td>{$terisi}</td>
                                <td>{$sisa}</td>
                              </tr>";
                        $no++;
                    }
                }
            } else {
                echo "<tr><td colspan='6' style='text-align:center;'>Belum ada jadwal</td></tr>";
            }
            ?>
        </tbody>
    </table>
</section>
</div>
</body>
</html>
