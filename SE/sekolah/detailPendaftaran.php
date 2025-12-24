<?php
session_start();
include("../koneksi_mysql.php");

// Cek login sekolah
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'sekolah') {
    header("Location: loginSekolah.php");
    exit;
}

// =========================
// UPDATE STATUS DAFTAR ULANG
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {

    $newStatus = $_POST['update_status'];
    $siswaId   = $_GET['siswa_id'];

    $qUpdate = $conn->prepare("
        UPDATE booking_daftar_ulang 
        SET status = ? 
        WHERE siswa_id = ?
    ");

    $qUpdate->bind_param("si", $newStatus, $siswaId);

    if ($qUpdate->execute()) {
        echo "<script>alert('Status berhasil diubah menjadi: $newStatus'); 
        window.location.href = '?siswa_id=$siswaId';</script>";
        exit;
    } else {
        echo "<script>alert('Gagal memperbarui status');</script>";
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Detail Pendaftaran Siswa</title>
<link rel="stylesheet" href="../css/dashboardSekolah.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
.detail-table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
    margin-bottom: 20px;
}
.detail-table td {
    padding: 10px;
    border: 1px solid #ddd;
}
h3 {
    margin-top: 25px;
}

.btn-aksi {
    padding: 10px 18px;
    border: none;
    cursor: pointer;
    font-size: 14px;
    border-radius: 6px;
    margin-right: 8px;
    color: #fff;
}

.btn-terima {
    background: #28a745;
}

.btn-tolak {
    background: #dc3545;
}

.btn-tunda {
    background: #ffc107;
    color: #000;
}

.btn-aksi:hover {
    opacity: .85;
}

.btn-kembali {
    background: #ffffff;
    color: #334155;
    border: 1px solid #cbd5e1;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 16px;
    border-radius: 8px;
    font-weight: 500;
    text-decoration: none;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    transition: all 0.2s ease;
}

/* Hover */
.btn-kembali:hover {
    background: #f8fafc;
    border-color: #94a3b8;
    color: #0f172a;
    opacity: 1;
}

/* Ikon */
.btn-kembali i {
    font-size: 13px;
}
</style>
</head>
<body>

<?php include("sidebarSekolah.php"); ?>
<div class="main-content">
<?php include("headerSekolah.php"); ?>

<div style="padding:0px 20px 10px 20px;">
    <a href="dataDaftarUlang.php" class="btn-aksi btn-kembali">
        <i class="fa-solid fa-arrow-left"></i> Kembali
    </a>
</div>

<div>
<h2>Detail Pendaftaran Siswa</h2>
<hr><br>
<?php
// ======================================
// MASUKKAN SCRIPT FETCH DETAIL DI SINI
// ======================================
include("fetchDetailPendaftar.php"); // atau paste langsung script kamu
?>

<?php
// Ambil data booking daftar ulang
$qBooking = $conn->prepare("SELECT * FROM booking_daftar_ulang WHERE siswa_id = ?");
$qBooking->bind_param("i", $siswa_id);
$qBooking->execute();
$booking = $qBooking->get_result()->fetch_assoc();

if ($booking) {
    echo "<h3><i class='fa-solid fa-calendar-check'></i> Status Daftar Ulang</h3>";

    echo "<table class='detail-table'>";
    echo "<tr><td><b>Tanggal Booking</b></td><td>{$booking['tanggal_booking']}</td></tr>";
    echo "<tr><td><b>Jam Booking</b></td><td>{$booking['jam_booking']}</td></tr>";
    echo "<tr><td><b>Status</b></td><td><b style='text-transform:uppercase;'>{$booking['status']}</b></td></tr>";
    echo "<tr><td><b>QR Code</b></td><td><img src='{$booking['qr_code']}' width='150'></td></tr>";
    echo "</table>";
}
?>

</div>
    <!-- Tombol aksi -->
    <form method="POST" style="margin-top:15px;">
        <button name="update_status" value="diterima" class="btn-aksi btn-terima">
            <i class="fa-solid fa-check"></i> Terima
        </button>

        <button name="update_status" value="ditolak" class="btn-aksi btn-tolak">
            <i class="fa-solid fa-xmark"></i> Tolak
        </button>

        <!-- TOMBOL BARU -->
        <button type="button" onclick="tundaDanBuatQR()" class="btn-aksi btn-tunda">
            <i class="fa-solid fa-clock"></i> Tunda & Buat QR Baru
        </button>
    </form>
</div>
    <script>
    function tundaDanBuatQR() {
        if (!confirm("Status akan diubah ke DITUNDA dan QR BARU dibuat. Lanjutkan?")) {
            return;
        }

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'buatQrUlang.php';

        form.innerHTML = `
            <input type="hidden" name="siswa_id" value="<?= $siswa_id ?>">
        `;

        document.body.appendChild(form);
        form.submit();
    }
    </script>
</body>
</html>
