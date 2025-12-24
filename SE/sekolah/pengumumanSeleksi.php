<?php
// pengumumanSeleksi.php
session_start();
include("../koneksi_mysql.php"); // $conn

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'sekolah') {
    header("Location: loginSekolah.php");
    exit;
}

$sekolah_id = (int) ($_SESSION['sekolah_id'] ?? 0);
if (!$sekolah_id) die("Sekolah tidak ditemukan dalam sesi.");

$stmt = $conn->prepare("SELECT id, nama_sekolah, kuota, latitude, longitude FROM sekolah WHERE id=? LIMIT 1");
$stmt->bind_param("i", $sekolah_id);
$stmt->execute();
$sekolah = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$sekolah) die("Data sekolah tidak ditemukan.");

// ============================
// AMBIL TAHUN AKADEMIK DARI PENDAFTARAN
// ============================
$tahunList = [];
$qTahun = $conn->prepare("
    SELECT id AS tahun_id, nama_tahun
    FROM tahun_akademik
    ORDER BY status='aktif' DESC, id DESC
");
$qTahun->execute();
$tahunList = $qTahun->get_result()->fetch_all(MYSQLI_ASSOC);
$qTahun->close();

// tahun terpilih
$selected_tahun = $_GET['tahun_id'] ?? null;

// default → tahun terbaru
if (!$selected_tahun && !empty($tahunList)) {
    $selected_tahun = $tahunList[0]['tahun_id'];
}

$aturan_zonasi = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM aturan_zonasi ORDER BY id DESC LIMIT 1")) ?? [];
$aturan_seleksi = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM aturan_seleksi ORDER BY id DESC LIMIT 1")) ?? [];

$kuota_total = intval($sekolah['kuota'] ?? 0);
$kuota_persen_zonasi = intval($aturan_zonasi['kuota_persen'] ?? 0);
$kuota_zonasi = (int) floor(($kuota_total * $kuota_persen_zonasi) / 100);
$kuota_akademik = $kuota_total - $kuota_zonasi;

function haversine($lat1,$lon1,$lat2,$lon2){
    if ($lat1==''||$lon1==''||$lat2==''||$lon2=='') return null;
    $R = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2)**2 + cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLon/2)**2;
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $R*$c;
}

// ----------------- AUTO SELEKSI -----------------
// jalankan seleksi otomatis
$today = date('Y-m-d');
autoSeleksi($conn, $sekolah_id, $today, $aturan_zonasi, $aturan_seleksi);
isiSekolahDiterimaJikaPengumuman($conn, $today, $sekolah_id, $aturan_zonasi, $aturan_seleksi);

// ----------------- AUTO SELEKSI -----------------
function autoSeleksi($conn, $sekolah_id, $today, $aturan_zonasi, $aturan_seleksi){
    // ===== ZONASI =====
    if($aturan_zonasi){
        if($today >= $aturan_zonasi['seleksi_1'] && $today < $aturan_zonasi['seleksi_2'])
            jalankanSeleksiTahap($conn,$sekolah_id,1,'zonasi',$aturan_zonasi);
        if($today >= $aturan_zonasi['seleksi_2'] && $today < $aturan_zonasi['seleksi_3'])
            jalankanSeleksiTahap($conn,$sekolah_id,2,'zonasi',$aturan_zonasi);
        if($today >= $aturan_zonasi['seleksi_3'] && $today <= $aturan_zonasi['tanggal_pengumuman'])
            jalankanSeleksiTahap($conn,$sekolah_id,3,'zonasi',$aturan_zonasi);
    }

    // ===== AKADEMIK =====
    if($aturan_seleksi){
        if($today >= $aturan_seleksi['seleksi_1'] && $today < $aturan_seleksi['seleksi_2'])
            jalankanSeleksiTahap($conn,$sekolah_id,1,'akademik',$aturan_seleksi);
        if($today >= $aturan_seleksi['seleksi_2'] && $today < $aturan_seleksi['seleksi_3'])
            jalankanSeleksiTahap($conn,$sekolah_id,2,'akademik',$aturan_seleksi);
        if($today >= $aturan_seleksi['seleksi_3'] && $today <= $aturan_seleksi['tanggal_pengumuman'])
            jalankanSeleksiTahap($conn,$sekolah_id,3,'akademik',$aturan_seleksi);
    }
}

function isiSekolahDiterimaJikaPengumuman($conn, $today, $sekolah_id, $aturan_zonasi, $aturan_seleksi)
{
    // ============= ZONASI =============
    if ($aturan_zonasi && $today == $aturan_zonasi['tanggal_pengumuman']) {

        for ($t=1; $t<=3; $t++) {
            $col_status = "status_seleksi{$t}";
            $col_pilihan = "pilihan_ke{$t}";

            $sql = "UPDATE pendaftaran
                    SET sekolah_diterima = $col_pilihan
                    WHERE $col_status='lolos'
                      AND jalur='zonasi'
                      AND sekolah_diterima IS NULL";
            $conn->query($sql);
        }
        $conn->commit();
    }

    // ============= AKADEMIK =============
    if ($aturan_seleksi && $today == $aturan_seleksi['tanggal_pengumuman']) {

        for ($t=1; $t<=3; $t++) {
            $col_status = "status_seleksi{$t}";
            $col_pilihan = "pilihan_ke{$t}";

            $sql = "UPDATE pendaftaran
                    SET sekolah_diterima = $col_pilihan
                    WHERE $col_status='lolos'
                      AND jalur='akademik'
                      AND sekolah_diterima IS NULL";
            $conn->query($sql);
        }
        $conn->commit();
    }
}

// ----------------- SELEKSI OTOMATIS -----------------
function jalankanSeleksiTahap($conn, $sekolah_id, $tahap, $jalur, $aturan){
    global $sekolah, $kuota_zonasi, $kuota_akademik, $selected_tahun;

    $pilihan_col = "pilihan_ke{$tahap}";
    $status_col  = "status_seleksi{$tahap}";

    // Ambil semua pendaftar untuk tahap & jalur ini beserta status verifikasi
    $sql = "SELECT p.*, s.latitude, s.longitude, v.status_verifikasi
            FROM pendaftaran p
            JOIN siswa s ON p.siswa_id=s.id
            LEFT JOIN verifikasi_pendaftaran v 
                ON v.pendaftaran_id=p.id AND v.sekolah_id=?
            WHERE p.$pilihan_col=? AND p.jalur=? AND p.tahun_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisi", $sekolah_id, $sekolah_id, $jalur, $selected_tahun);
    $stmt->execute();
    $pendaftar = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if(empty($pendaftar)) return;

    // Hitung kuota tersisa berdasarkan semua tahap sampai saat ini
    $kuota = ($jalur==='zonasi') ? $kuota_zonasi : $kuota_akademik;

    // Buat list kolom status seleksi dari tahap 1 sampai tahap ini
    $kolom_seleksi = [];
    for($j=1; $j<=$tahap; $j++){
        $kolom_seleksi[] = "status_seleksi{$j}='lolos'";
    }

    $sqlKuota = "SELECT COUNT(*) AS sudah_lolos
                FROM pendaftaran
                WHERE tahun_id = ?
                AND jalur = ?
                AND (
                    (pilihan_ke1 = ? AND status_seleksi1='lolos') OR
                    (pilihan_ke2 = ? AND status_seleksi2='lolos') OR
                    (pilihan_ke3 = ? AND status_seleksi3='lolos')
                    )";
    $stmtKuota = $conn->prepare($sqlKuota);
    $stmtKuota->bind_param("isiii", $selected_tahun, $jalur, $sekolah_id, $sekolah_id, $sekolah_id);

    $stmtKuota->execute();
    $sudahLolos = $stmtKuota->get_result()->fetch_assoc()['sudah_lolos'] ?? 0;
    $stmtKuota->close();

    $kuotaTersisa = max($kuota - $sudahLolos, 0);
    if($kuotaTersisa <= 0) return; // kuota penuh, tidak perlu seleksi lagi

    $peserta_final = [];

    // Filter peserta sesuai tahap sebelumnya & verifikasi
    foreach($pendaftar as $p){
        // Jika verifikasi ditolak → langsung tidak_lolos
        if(isset($p['status_verifikasi']) && $p['status_verifikasi'] === 'ditolak'){
            $update = $conn->prepare("UPDATE pendaftaran SET $status_col='tidak_lolos' WHERE id=?");
            $update->bind_param("i", $p['id']);
            $update->execute();
            $update->close();
            continue;
        }

        // Cek apakah siswa sudah lolos di tahap sebelumnya
        $sudahLolosSebelumnya = false;
        for($j = 1; $j < $tahap; $j++){
            if(in_array($p["status_seleksi$j"], ['lolos','lulus'])){
                $sudahLolosSebelumnya = true;
                break;
            }
        }

        // Jika sudah lolos di tahap sebelumnya → skip (status tetap "belum")
        if($sudahLolosSebelumnya) continue;

        // Jika sampai sini, ikut seleksi
        $peserta_final[] = $p;
    }

    if(empty($peserta_final)) return;

    // Hitung ranking
    foreach($peserta_final as &$p){
        if($jalur==='zonasi'){
            $p['ranking'] = round(haversine($sekolah['latitude'],$sekolah['longitude'],$p['latitude'],$p['longitude']),5);
        } else {
            $stmt2 = $conn->prepare("SELECT AVG(nilai) AS rata_nilai FROM nilai_akademik WHERE siswa_id=?");
            $stmt2->bind_param("i", $p['siswa_id']);
            $stmt2->execute();
            $rata = $stmt2->get_result()->fetch_assoc()['rata_nilai'] ?? 0;
            $p['ranking'] = round(floatval($rata),5);
            $stmt2->close();
        }
    }
    unset($p);

    // Urutkan ranking
    if($jalur==='zonasi'){
        usort($peserta_final, fn($a,$b) => $a['ranking'] <=> $b['ranking']);
    } else {
        usort($peserta_final, fn($a,$b) => $b['ranking'] <=> $a['ranking']);
    }

    // Update status sesuai kuota tersisa
    foreach($peserta_final as $i => $p){
        $status = ($i < $kuotaTersisa) ? 'lolos' : 'tidak_lolos';
        $update = $conn->prepare("UPDATE pendaftaran SET $status_col=? WHERE id=?");
        $update->bind_param("si", $status, $p['id']);
        $update->execute();
        $update->close();
    }

    $conn->commit();
}

// ----------------- AMBIL DATA PENDAFTAR -----------------
function getPendaftarByJalur($conn,$sekolah_id,$n,$jalur){
    global $sekolah, $selected_tahun;
    $col = "pilihan_ke{$n}";

    $syaratLulusSebelumnya = "";
    if($jalur==='zonasi'){
        if($n==2) $syaratLulusSebelumnya="AND (p.status_seleksi1 IS NULL OR p.status_seleksi1 NOT IN ('lolos','lulus'))";
        if($n==3) $syaratLulusSebelumnya="AND (p.status_seleksi1 IS NULL OR p.status_seleksi1 NOT IN ('lolos','lulus')) 
                                          AND (p.status_seleksi2 IS NULL OR p.status_seleksi2 NOT IN ('lolos','lulus'))";
    }

    if($jalur==='akademik'){
        if($n==2) $syaratLulusSebelumnya="AND (p.status_seleksi1 IS NULL OR p.status_seleksi1 NOT IN ('lolos','lulus'))";
        if($n==3) $syaratLulusSebelumnya="AND (p.status_seleksi1 IS NULL OR p.status_seleksi1 NOT IN ('lolos','lulus'))
                                        AND (p.status_seleksi2 IS NULL OR p.status_seleksi2 NOT IN ('lolos','lulus'))";
    }

    $sql = "SELECT p.*, s.nama_lengkap, s.nisn, s.latitude AS s_lat, s.longitude AS s_lon, u.email
            FROM pendaftaran p
            JOIN siswa s ON s.id=p.siswa_id
            LEFT JOIN user u ON u.id=s.user_id
            WHERE p.$col=? AND p.jalur=? AND p.tahun_id=? $syaratLulusSebelumnya
            ORDER BY p.id ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isi", $sekolah_id, $jalur, $selected_tahun);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if($jalur==='akademik'){
        foreach($data as &$d){
            $stmt2 = $conn->prepare("SELECT AVG(nilai) AS rata_nilai FROM nilai_akademik WHERE siswa_id=?");
            $stmt2->bind_param("i",$d['siswa_id']);
            $stmt2->execute();
            $rata = $stmt2->get_result()->fetch_assoc()['rata_nilai'] ?? 0;
            $d['rata_nilai'] = number_format($rata, 2); // 2 angka di belakang koma
            $stmt2->close();
        }
    } else {
        foreach($data as &$d){
            $d['jarak_hitung'] = haversine($sekolah['latitude'],$sekolah['longitude'],$d['s_lat'],$d['s_lon']);
        }
        usort($data,function($a,$b){ return $a['jarak_hitung'] <=> $b['jarak_hitung']; });
    }
    return $data;
}

function pisahkanLulusTidak(array $rows,int $n){
    $status_col = "status_seleksi{$n}";
    $lulus=[]; $tidak=[];
    foreach($rows as $r){
        $val = strtolower($r[$status_col] ?? '');
        if($val==='lolos' || $val==='lulus') $lulus[]=$r;
        else $tidak[]=$r;
    }
    return [$lulus,$tidak];
}

function renderRow($row, $sekolahLat, $sekolahLon, $jalur='zonasi'){
    $nama = htmlspecialchars($row['nama_lengkap'] ?? '-');
    $nisn = htmlspecialchars($row['nisn'] ?? '-');
    $email = htmlspecialchars($row['email'] ?? '-');
    $tanggal = htmlspecialchars($row['tanggal_daftar'] ?? '-');

    if($jalur==='zonasi'){
        $lat = $row['s_lat'] ?? null;
        $lon = $row['s_lon'] ?? null;
        $jarak = ($lat && $lon && $sekolahLat && $sekolahLon) ? haversine($sekolahLat,$sekolahLon,$lat,$lon) : null;
        $jarakText = ($jarak!==null) ? number_format($jarak,3)." km" : "-";
        $nilaiText = "-";
        $tampil = $jarakText;
    } else {
        $nilai = $row['rata_nilai'] ?? $row['total_nilai'] ?? 0;
        $nilaiText = number_format($nilai, 2); // 2 angka di belakang koma
        $tampil = $nilaiText;
    }

    return "<tr>
        <td>{$nisn}</td>
        <td>{$nama}</td>
        <td>{$email}</td>
        <td>{$tampil}</td>
        <td>{$tanggal}</td>
    </tr>";
}

// ambil data tiap tahap
$sel=[]; $lulus=[]; $tidak=[];
foreach([1,2,3] as $n){
    foreach(['zonasi','akademik'] as $jalur){
        $data = getPendaftarByJalur($conn,$sekolah_id,$n,$jalur);
        $sel[$n][$jalur] = $data;
        list($l,$t) = pisahkanLulusTidak($data,$n);
        $lulus[$n][$jalur] = $l;
        $tidak[$n][$jalur] = $t;
    }
}

// pengumuman akhir
$showPengumumanZonasi   = !empty($aturan_zonasi['tanggal_pengumuman']) && $today >= $aturan_zonasi['tanggal_pengumuman'];
$showPengumumanAkademik = !empty($aturan_seleksi['tanggal_pengumuman']) && $today >= $aturan_seleksi['tanggal_pengumuman'];

// Pengumuman akhir Zonasi
if ($showPengumumanZonasi) {
    $stmt = $conn->prepare("
        SELECT * FROM pendaftaran 
        WHERE tahun_id = ?
        AND (
            (pilihan_ke1=? AND status_seleksi1='lolos' AND jalur='zonasi')
         OR (pilihan_ke2=? AND status_seleksi2='lolos' AND jalur='zonasi')
         OR (pilihan_ke3=? AND status_seleksi3='lolos' AND jalur='zonasi')
        )
    ");

    $stmt->bind_param(
        "iiii",
        $selected_tahun,
        $sekolah_id,
        $sekolah_id,
        $sekolah_id
    );

    $stmt->execute();
    $pendaftar = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($pendaftar as $p) {
        $update = $conn->prepare("
            UPDATE pendaftaran 
            SET sekolah_diterima = ?
            WHERE id = ?
            AND tahun_id = ?
            AND (
                    (pilihan_ke1=? AND status_seleksi1='lolos')
                OR (pilihan_ke2=? AND status_seleksi2='lolos')
                OR (pilihan_ke3=? AND status_seleksi3='lolos')
            )
        ");
        $update->bind_param("iiiiii", $sekolah_id, $p['id'], $selected_tahun, $sekolah_id, $sekolah_id, $sekolah_id);
        $update->execute();
        $update->close();
    }
}

// Pengumuman akhir Akademik
if ($showPengumumanAkademik) {
    $stmt = $conn->prepare("
        SELECT * FROM pendaftaran 
        WHERE tahun_id = ?
        AND (
            (pilihan_ke1=? AND status_seleksi1='lolos' AND jalur='akademik')
         OR (pilihan_ke2=? AND status_seleksi2='lolos' AND jalur='akademik')
         OR (pilihan_ke3=? AND status_seleksi3='lolos' AND jalur='akademik')
        )
    ");

    $stmt->bind_param(
        "iiii",
        $selected_tahun,
        $sekolah_id,
        $sekolah_id,
        $sekolah_id
    );

    $stmt->execute();
    $pendaftar = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($pendaftar as $p) {
        $update = $conn->prepare("
            UPDATE pendaftaran 
            SET sekolah_diterima = ?
            WHERE id = ?
            AND tahun_id = ?
        ");
        $update->bind_param("iii", $sekolah_id, $p['id'], $selected_tahun);
        $update->execute();
        $update->close();
    }
}

// ambil pengumuman akhir
$pendaftarAkhir = [];

foreach (['zonasi', 'akademik'] as $jalur) {
    $stmt = $conn->prepare("
        SELECT 
            p.*, 
            s.nama_lengkap, 
            s.nisn, 
            s.latitude AS s_lat,
            s.longitude AS s_lon,
            u.email,
            (
                SELECT AVG(n.nilai) 
                FROM nilai_akademik n
                WHERE n.siswa_id = p.siswa_id
            ) AS rata_nilai
        FROM pendaftaran p
        JOIN siswa s ON s.id = p.siswa_id
        LEFT JOIN user u ON u.id = s.user_id
        WHERE p.sekolah_diterima = ?
        AND p.jalur = ?
        AND p.tahun_id = ?
    ");

    $stmt->bind_param(
        "isi",
        $sekolah_id,
        $jalur,
        $selected_tahun
    );

    $stmt->execute();
    $pendaftarAkhir[$jalur] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

function cekStatusTahap($today, $aturan, $jalur){
    if (!$aturan) return "Aturan $jalur belum diatur.";

    $s1  = $aturan['seleksi_1'];
    $s2  = $aturan['seleksi_2'];
    $s3  = $aturan['seleksi_3'];
    $peng= $aturan['tanggal_pengumuman'];

    // sebelum tahap 1
    if ($today < $s1) {
        return "
            Seleksi Tahap 1 ($jalur) dimulai pada <b>$s1</b>.
        ";
    }

    // tahap 1 sedang berlangsung
    if ($today >= $s1 && $today < $s2) {
        return "
            Seleksi Tahap 1 ($jalur) sedang berlangsung.<br>
            Tahap 2 dimulai pada <b>$s2</b>.
        ";
    }

    // tahap 2 sedang berlangsung
    if ($today >= $s2 && $today < $s3) {
        return "
            Seleksi Tahap 2 ($jalur) sedang berlangsung.<br>
            Tahap 3 dimulai pada <b>$s3</b>.
        ";
    }

    // tahap 3 sedang berlangsung
    if ($today >= $s3 && $today < $peng) {
        return "
            Seleksi Tahap 3 ($jalur) sedang berlangsung.<br>
            Pengumuman akhir dirilis pada <b>$peng</b>.
        ";
    }

    // menunggu pengumuman
    if ($today < $peng) {
        return "
            Menunggu pengumuman akhir ($jalur) pada <b>$peng</b>.
        ";
    }

    // hari H pengumuman
    if ($today == $peng) {
        return "Pengumuman akhir ($jalur) dirilis hari ini.";
    }

    // setelah pengumuman
    return "Seleksi dan pengumuman ($jalur) telah selesai.";
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Pengumuman Seleksi - <?= htmlspecialchars($sekolah['nama_sekolah']) ?></title>
<link rel="stylesheet" href="../css/dashboardSekolah.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
/* Container utama */
.container { 
    max-width:1100px; 
    margin:30px auto; 
    background:#ffffff; 
    padding:25px; 
    border-radius:14px; 
    box-shadow:0 6px 18px rgba(0,0,0,0.08); 
}

/* Tabs tahap */
.tabs { 
    display:flex; 
    gap:10px; 
    margin-bottom:18px; 
}

.tab { 
    padding:10px 16px; 
    border-radius:8px; 
    background:#eef1f5; 
    cursor:pointer; 
    border:1px solid transparent; 
    font-weight:600;
    transition:0.20s ease;
}

.tab:hover { background:#dce6f7; }

.tab.active { 
    background:linear-gradient(135deg,#007bff,#005fcc);
    color:#fff; 
    border-color:#0062cc; 
}

.section { display:none; }
.section.active { display:block; }

/* Tabel */
.table { 
    width:100%; 
    border-collapse:collapse; 
    margin-top:10px; 
    border-radius:6px; 
    overflow:hidden;
}

.table th { 
    background:#f3f6fa; 
    font-size:14px; 
    font-weight:600; 
}

.table th, 
.table td{ 
    padding:10px 12px; 
    border:1px solid #e5e9ef; 
    font-size:14px; 
}

/* Heading Lulus / Tidak Lulus */
h4 { 
    margin-top:18px;
    font-size:17px;
    font-weight:700;
}

.success-icon {
    color:#16a34a; 
    margin-right:6px;
}

.fail-icon {
    color:#dc2626; 
    margin-right:6px;
}

/* Kotak status */
.status-box {
    background:#f8fafc;
    padding:12px 14px;
    border-left:4px solid #3b82f6;
    border-radius:6px;
    margin-bottom:12px;
}

.small { 
    color:#555; 
    font-size:13px; 
}
</style>
</head>

<body>
<?php include("sidebarSekolah.php"); ?>
<div class="main-content">
<?php include("headerSekolah.php"); ?>

<div class="container">
<h2 style="margin-bottom:5px;">Status Peserta</h2>
<div class="small">
    Kuota total: <b><?= $kuota_total ?></b> — Zonasi: <b><?= $kuota_zonasi ?></b>, Akademik: <b><?= $kuota_akademik ?></b>
</div>

<form method="GET" style="margin-bottom:15px;">
    <label><strong>Tahun Akademik:</strong></label>
    <select name="tahun_id" onchange="this.form.submit()" style="padding:8px;border-radius:6px;">
        <?php foreach ($tahunList as $t): ?>
            <option value="<?= $t['tahun_id'] ?>"
                <?= ($t['tahun_id'] == $selected_tahun) ? 'selected' : '' ?>>
                <?= htmlspecialchars($t['nama_tahun']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</form>

<!-- TABS TAHAP UTAMA -->
<div class="tabs">
    <div class="tab active" data-target="sel1">Seleksi Tahap 1</div>
    <div class="tab" data-target="sel2">Seleksi Tahap 2</div>
    <div class="tab" data-target="sel3">Seleksi Tahap 3</div>
    <div class="tab" data-target="sel4">Pengumuman Akhir</div>
</div>

<?php for($t=1;$t<=3;$t++): ?>
<div id="sel<?= $t ?>" class="section <?= $t===1?'active':'' ?>">

    <div class="status-box small">
        <b>Status Zonasi:</b> <?= cekStatusTahap($today,$aturan_zonasi,'Zonasi') ?><br>
        <b>Status Akademik:</b> <?= cekStatusTahap($today,$aturan_seleksi,'Akademik') ?>
    </div>

    <!-- Nested Tabs -->
    <div class="tabs">
      <div class="tab active" data-target="sel<?= $t ?>_z"><i class="fa-solid fa-location-dot"></i> Zonasi</div>
      <div class="tab" data-target="sel<?= $t ?>_a"><i class="fa-solid fa-book"></i> Akademik</div>
    </div>

    <?php foreach(['z'=>'zonasi','a'=>'akademik'] as $k=>$jalur): 
          $lulus_j = $lulus[$t][$jalur];
          $tidak_j = $tidak[$t][$jalur];
    ?>

    <div id="sel<?= $t ?>_<?= $k ?>" class="section <?= $k==='z'?'active':'' ?>">
      <p class="small">
        Total: <b><?= count($sel[$t][$jalur]) ?></b> —
        Lulus: <b><?= count($lulus_j) ?></b> —
        Tidak Lulus: <b><?= count($tidak_j) ?></b>
      </p>

      <!-- Bagian Lulus -->
      <h4><i class="fa-solid fa-circle-check success-icon"></i> Lulus <?= ucfirst($jalur) ?></h4>
      <?php if(count($lulus_j)===0): ?>
          <p class="small">Belum ada siswa lolos.</p>
      <?php else: ?>
          <table class="table"><thead><tr>
              <th>NISN</th><th>Nama</th><th>Email</th>
              <th><?= $jalur==='zonasi'?'Jarak':'Nilai' ?></th><th>Tanggal</th>
          </tr></thead>
          <tbody>
            <?php foreach($lulus_j as $r) echo renderRow($r,$sekolah['latitude'],$sekolah['longitude'],$jalur); ?>
          </tbody></table>
      <?php endif; ?>

      <!-- Bagian Tidak Lulus -->
      <h4><i class="fa-solid fa-circle-xmark fail-icon"></i> Tidak Lulus <?= ucfirst($jalur) ?></h4>
      <?php if(count($tidak_j)===0): ?>
          <p class="small">Belum ada siswa tidak lolos.</p>
      <?php else: ?>
          <table class="table"><thead><tr>
              <th>NISN</th><th>Nama</th><th>Email</th>
              <th><?= $jalur==='zonasi'?'Jarak':'Nilai' ?></th><th>Tanggal</th>
          </tr></thead>
          <tbody>
            <?php foreach($tidak_j as $r) echo renderRow($r,$sekolah['latitude'],$sekolah['longitude'],$jalur); ?>
          </tbody></table>
      <?php endif; ?>
    </div>

    <?php endforeach; ?>
</div>
<?php endfor; ?>

<!-- PENGUMUMAN AKHIR -->
<div id="sel4" class="section">
<h3 style="margin-top:0;">Pengumuman Akhir</h3>

<div class="status-box small">
    <b>Status Zonasi:</b> <?= cekStatusTahap($today,$aturan_zonasi,'Zonasi') ?><br>
    <b>Status Akademik:</b> <?= cekStatusTahap($today,$aturan_seleksi,'Akademik') ?>
</div>

<?php foreach(['zonasi','akademik'] as $jalur):
    $data_final = $pendaftarAkhir[$jalur];
?>
<h4><i class="fa-solid fa-bullhorn"></i> <?= ucfirst($jalur) ?></h4>

<?php if(count($data_final)===0): ?>
    <p class="small">Belum ada pengumuman.</p>
<?php else: ?>
<table class="table"><thead><tr>
    <th>NISN</th><th>Nama</th><th>Email</th>
    <th><?= $jalur==='zonasi'?'Jarak':'Nilai' ?></th><th>Tanggal</th>
</tr></thead>
<tbody>
<?php foreach($data_final as $r) echo renderRow($r,$sekolah['latitude'],$sekolah['longitude'],$jalur); ?>
</tbody></table>
<?php endif; ?>

<?php endforeach; ?>
</div>
</div>
</div>

<script>
let activeJalur = 'z';
function activateTab(tab) {
    const parent = tab.parentElement;
    const parentSection = parent.parentElement;

    parent.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    tab.classList.add('active');

    const targetId = tab.dataset.target;
    parentSection.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
    const el = document.getElementById(targetId);
    if (el) el.classList.add('active');

    if (parent.parentElement.id.startsWith('sel')) {
        activeJalur = tab.dataset.target.endsWith('_z') ? 'z' : 'a';
    }

    if(el){
        const nestedTabs = el.querySelectorAll('.tabs .tab');
        if (nestedTabs.length > 0) {
            const nestedTab = Array.from(nestedTabs).find(t => {
                return (activeJalur === 'z' && t.dataset.target.endsWith('_z')) ||
                       (activeJalur === 'a' && t.dataset.target.endsWith('_a'));
            }) || nestedTabs[0];

            nestedTabs.forEach(t => t.classList.remove('active'));
            nestedTab.classList.add('active');

            el.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
            const nestedSection = document.getElementById(nestedTab.dataset.target);
            if(nestedSection) nestedSection.classList.add('active');
        }
    }
}

document.querySelectorAll('.tabs .tab').forEach(tab=>{
    tab.addEventListener('click',()=>activateTab(tab));
});

document.querySelectorAll('.tabs > .tab.active').forEach(tab => activateTab(tab));
</script>

</body>
</html>
