<?php
session_start();
require '../koneksi.php';

if (!isset($_SESSION['sekolah_id'])) {
    exit("Unauthorized");
}

$sekolah_id = $_SESSION['sekolah_id'];

$query = "
    SELECT 
        p.id AS pendaftaran_id,
        s.id AS siswa_id,
        s.nama_lengkap,
        s.nisn,
        s.email,
        s.no_hp,
        p.status,
        p.tanggal_daftar,
        COALESCE(SUM(n.semester_1 + n.semester_2 + n.semester_3 + n.semester_4 + n.semester_5), 0) AS total_nilai
    FROM pendaftaran p
    JOIN siswa s ON p.siswa_id = s.id
    LEFT JOIN nilai_akademik n ON n.siswa_id = s.id
    WHERE p.sekolah_id = ?
    GROUP BY p.id, s.id, s.nama_lengkap, s.nisn, s.email, s.no_hp, p.status, p.tanggal_daftar
    ORDER BY total_nilai DESC, p.tanggal_daftar ASC
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $sekolah_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "<table>
            <thead>
              <tr>
                <th>No</th>
                <th>Nama Lengkap</th>
                <th>NISN</th>
                <th>Email</th>
                <th>No HP</th>
                <th>Total Nilai</th>
                <th>Tanggal Daftar</th>
                <th>Status</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>";

    $no = 1;
    while ($row = $result->fetch_assoc()) {
        echo "<tr>
          <td>{$no}</td>
          <td>".htmlspecialchars($row['nama_lengkap'])."</td>
          <td>".htmlspecialchars($row['nisn'])."</td>
          <td>".htmlspecialchars($row['email'])."</td>
          <td>".htmlspecialchars($row['no_hp'])."</td>
          <td>{$row['total_nilai']}</td>
          <td>".date('d M Y', strtotime($row['tanggal_daftar']))."</td>
          <td><span class='status {$row['status']}'>".ucfirst($row['status'])."</span></td>
          <td><a href='detailPendaftar.php?id={$row['pendaftaran_id']}' class='btn-detail'><i class='fa fa-eye'></i> Lihat</a></td>
        </tr>";
        $no++;
    }

    echo "</tbody></table>";
} else {
    echo "<p style='text-align:center;'>Belum ada pendaftar</p>";
}
?>
