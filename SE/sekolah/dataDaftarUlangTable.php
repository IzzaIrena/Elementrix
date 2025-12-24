<?php
session_start();
include("../koneksi_mysql.php");

$sekolah_id = $_SESSION['sekolah_id'] ?? 0;
if (!$sekolah_id) {
    echo "<tr><td colspan='8'>Session sekolah tidak ditemukan.</td></tr>";
    exit;
}

// ============================
// AMBIL FILTER DARI AJAX
// ============================
$tahun_id = isset($_GET['tahun_id']) ? (int)$_GET['tahun_id'] : 0;
$jalur    = $_GET['jalur'] ?? 'zonasi';

if (!in_array($jalur, ['zonasi','akademik'])) {
    $jalur = 'zonasi';
}

// ============================
// QUERY DENGAN FILTER (INI KUNCINYA)
// ============================
$q = mysqli_query($conn, "
    SELECT b.*, s.nama_lengkap
    FROM booking_daftar_ulang b
    INNER JOIN (
        SELECT siswa_id, MAX(id) AS id_terbaru
        FROM booking_daftar_ulang
        GROUP BY siswa_id
    ) last_b ON b.id = last_b.id_terbaru
    LEFT JOIN siswa s ON b.siswa_id = s.id
    LEFT JOIN pendaftaran p 
        ON p.siswa_id = b.siswa_id 
        AND p.sekolah_diterima = b.sekolah_id
    WHERE 
        b.sekolah_id = '$sekolah_id'
        AND p.tahun_id = '$tahun_id'
        AND p.jalur = '$jalur'
    ORDER BY 
        CASE WHEN b.timestamp_scan IS NULL THEN 1 ELSE 0 END,
        b.timestamp_scan DESC
");
$no = 1;
$ada = false;

while ($b = mysqli_fetch_assoc($q)) {
    $ada = true;

    $ket = $b['status_keterangan'] ?? 'Belum Hadir';
    if ($ket == "Hadir")      $color = "green";
    elseif ($ket == "Telat")  $color = "red";
    elseif ($ket == "Menunggu Scan Ulang") $color = "orange";
    else                      $color = "gray";

    echo "<tr>";
    echo "<td>$no</td>";
    echo "<td>".htmlspecialchars($b['nama_lengkap'])."</td>";
    echo "<td>".(!empty($b['tanggal_booking']) ? htmlspecialchars($b['tanggal_booking']) : '-')."</td>";
    echo "<td>".(!empty($b['jam_booking']) ? htmlspecialchars($b['jam_booking']) : '-')."</td>";
    echo "<td>".ucfirst($b['status'])."</td>";
    echo "<td><span style='font-weight:bold;color:$color;'>".htmlspecialchars($ket)."</span></td>";
    echo "<td>";
    if (!empty($b['qr_code'])) {
        echo "<a href='".htmlspecialchars($b['qr_code'])."' target='_blank' class='qr-link'>
              <i class='fa-solid fa-qrcode'></i> Lihat</a>";
    } else {
        echo "-";
    }
    echo "</td>";
    echo "<td><a href='detailPendaftaran.php?siswa_id=".$b['siswa_id']."' 
          class='qr-link' style='color:green;'>
          <i class='fa-solid fa-eye'></i> Detail</a></td>";
    echo "</tr>";
    $no++;
}

if (!$ada) {
    echo "<tr><td colspan='8' style='text-align:center;color:#777'>
          Tidak ada data untuk jalur <b>".ucfirst($jalur)."</b>
          </td></tr>";
}
?>
