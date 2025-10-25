<?php
session_start();
include("../koneksi.php");

// === CEK LOGIN SISWA ===
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'siswa') {
    header("Location: loginSiswa.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// === AMBIL DATA SISWA ===
$stmt = $conn->prepare("SELECT s.id, s.nama_lengkap 
                        FROM siswa s 
                        JOIN user u ON u.id=s.user_id 
                        WHERE u.id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($siswa_id, $nama_lengkap);
$stmt->fetch();
$stmt->close();

// === CEK NILAI SUDAH DIINPUT ATAU BELUM ===
$stmt = $conn->prepare("SELECT COUNT(*) FROM nilai_akademik WHERE siswa_id=?");
$stmt->bind_param("i", $siswa_id);
$stmt->execute();
$stmt->bind_result($jumlah_nilai);
$stmt->fetch();
$stmt->close();

if ($jumlah_nilai == 0) {
    echo "<script>
            alert('Prediksi hanya bisa diakses setelah Anda melakukan pendaftaran nilai.');
            window.location.href='pendaftaranSiswa.php';
          </script>";
    exit;
}

// === VARIABEL AWAL ===
$avgNorm = [];
$prediksiJurusan = [];
$jurusan_dominan = null;
$prediksiMapel = [];

// === AMBIL NILAI AKADEMIK SISWA DAN HITUNG RATA-RATA ===
$sql = "SELECT a.kode_mapel, a.nama_mapel,
               n.semester_1, n.semester_2, n.semester_3, n.semester_4, n.semester_5
        FROM nilai_akademik n
        JOIN aturan_mapel a 
        ON LOWER(TRIM(n.mapel)) = LOWER(TRIM(a.nama_mapel))
        WHERE n.siswa_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $siswa_id);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $kode = strtoupper(trim($row['kode_mapel']));
    $nilai = [
        $row['semester_1'],
        $row['semester_2'],
        $row['semester_3'],
        $row['semester_4'],
        $row['semester_5']
    ];
    $isi = array_filter($nilai, fn($v) => $v !== null && $v !== '');
    $avgNorm[$kode] = count($isi) ? array_sum($isi)/count($isi) : 0;
}
$stmt->close();

$mapelPrediksi = ['MTK', 'IPA', 'IPS', 'BINDO', 'BING'];
foreach($mapelPrediksi as $kode){
    if(!isset($avgNorm[$kode])) $avgNorm[$kode]=0;
}

// === LOAD DATASET BARU ===
$dataset = [];
if (($handle = fopen("../dataset_nilai_siswa_300_dengan_mapel.csv", "r")) !== false) {
    $header = fgetcsv($handle, 0, ";");
    $headerNorm = [];
    foreach($header as $col){
        $headerNorm[] = strtolower(str_replace(" ", "_", trim($col)));
    }
    while(($row = fgetcsv($handle, 0, ";")) !== false){
        $data = [];
        foreach($headerNorm as $i => $col){
            $val = trim($row[$i]);
            $data[$col] = is_numeric($val) ? (float)$val : $val;
        }
        $dataset[] = $data;
    }
    fclose($handle);
}

// === FUNGSI BANTU ===
function sigmoid($z){ return 1.0 / (1.0 + exp(-$z)); }
function normalize($arr){
    $sum = array_sum($arr);
    $res = [];
    foreach($arr as $k=>$v){
        $res[$k] = $sum>0 ? $v/$sum : 0;
    }
    return $res;
}

// === FUNGSI: Hitung Bobot Linear Berdasarkan Dataset ===
function hitungBobotLinear($dataset, $fiturX, $fiturY){
    $sumX1 = $sumX2 = $sumY = $sumX1Y = $sumX2Y = 0;
    $n = 0;

    foreach($dataset as $row){
        if(isset($row[$fiturX[0]]) && isset($row[$fiturX[1]]) && isset($row[$fiturY])){
            $x1 = (float)$row[$fiturX[0]];
            $x2 = (float)$row[$fiturX[1]];
            $y  = (float)$row[$fiturY];
            $sumX1 += $x1;
            $sumX2 += $x2;
            $sumY  += $y;
            $sumX1Y += $x1 * $y;
            $sumX2Y += $x2 * $y;
            $n++;
        }
    }

    if($n == 0) return [0.5, 0.5];

    $w1 = $sumX1Y / ($sumX1*$sumY + 1e-6);
    $w2 = $sumX2Y / ($sumX2*$sumY + 1e-6);
    $total = $w1 + $w2;
    if($total > 0){
        $w1 /= $total;
        $w2 /= $total;
    } else {
        $w1 = $w2 = 0.5;
    }

    return [$w1, $w2];
}

// === HITUNG BOBOT ===
list($wIPA_Kimia, $wMTK_Kimia)   = hitungBobotLinear($dataset, ["ipa", "matematika"], "kimia");
list($wMTK_Fisika, $wIPA_Fisika) = hitungBobotLinear($dataset, ["matematika", "ipa"], "fisika");
list($wIPS_Ekonomi, $wMTK_Ekonomi)   = hitungBobotLinear($dataset, ["ips", "matematika"], "ekonomi");
list($wIPS_Geografi, $wIPA_Geografi) = hitungBobotLinear($dataset, ["ips", "ipa"], "geografi");

// === TRAIN MODEL LOGISTIK REGRESI UNTUK PREDIKSI JURUSAN ===
$labels = ["IPA","IPS","Bahasa"];
$features = ["matematika","ipa","ips","bahasa_indonesia", "bahasa_inggris"];

function trainLogReg($dataset,$features,$targetLabel,$alpha=0.01,$iter=400){
    $weights = array_fill(0, count($features), 0.01);
    $bias = 0.0;
    for($i=0;$i<$iter;$i++){
        foreach($dataset as $row){
            $x = array_map(fn($f)=>$row[$f]/100.0, $features);
            $y = ($row['jurusan']==$targetLabel)?1:0;
            $z=$bias;
            foreach($weights as $j=>$w) $z+=$w*$x[$j];
            $pred = sigmoid($z);
            foreach($weights as $j=>$w) $weights[$j]-=$alpha*($pred-$y)*$x[$j];
            $bias -= $alpha*($pred-$y);
        }
    }
    return [$weights,$bias];
}

function predictLogReg($x,$weights,$bias){
    $z=$bias;
    foreach($weights as $j=>$w) $z+=$w*$x[$j];
    return sigmoid($z);
}

$models=[];
foreach($labels as $label){
    $models[$label] = trainLogReg($dataset,$features,$label);
}

// === PREDIKSI JURUSAN SISWA ===
$baru = [
    "matematika"     => $avgNorm["MTK"]/100,
    "ipa"            => $avgNorm["IPA"]/100,
    "ips"            => $avgNorm["IPS"]/100,
    "bahasa_indonesia"  => $avgNorm["BINDO"]/100,
    "bahasa_inggris" => $avgNorm["BING"]/100
];
$x = array_map(fn($f)=>$baru[$f], $features);

$prob=[];
foreach($labels as $label){
    list($w,$b) = $models[$label];
    $prob[$label] = predictLogReg($x,$w,$b);
}
$sumProb = array_sum($prob);
foreach($prob as $label=>$p) $prob[$label] = $sumProb>0 ? $p/$sumProb : 0;
arsort($prob);
$prediksiJurusan = $prob;
$jurusan_dominan = array_key_first($prediksiJurusan);

// === PREDIKSI MAPEL PILIHAN (4 MAPEL) ===
$ipa_scores = [
    "Biologi" => $avgNorm["IPA"],
    "Kimia"   => $wIPA_Kimia*$avgNorm["IPA"] + $wMTK_Kimia*$avgNorm["MTK"],
    "Fisika"  => $wMTK_Fisika*$avgNorm["MTK"] + $wIPA_Fisika*$avgNorm["IPA"]
];
$ips_scores = [
    "Sosiologi" => $avgNorm["IPS"],
    "Ekonomi"   => $wIPS_Ekonomi*$avgNorm["IPS"] + $wMTK_Ekonomi*$avgNorm["MTK"],
    "Geografi"  => $wIPS_Geografi*$avgNorm["IPS"] + $wIPA_Geografi*$avgNorm["IPA"]
];
$bahasa_scores = [
    "Bahasa Jerman"           => $avgNorm["BINDO"],
    "Bahasa Inggris Lanjutan" => $avgNorm["BING"]
];

$ipa_probs = normalize($ipa_scores);
$ips_probs = normalize($ips_scores);
$bahasa_probs = normalize($bahasa_scores);

$prediksiMapel = [];

if($jurusan_dominan=="IPA"){
    arsort($ipa_probs); $prediksiMapel += array_slice($ipa_probs,0,2,true);
    arsort($ips_probs); $prediksiMapel += array_slice($ips_probs,0,1,true);
    arsort($bahasa_probs); $prediksiMapel += array_slice($bahasa_probs,0,1,true);
}
elseif($jurusan_dominan=="IPS"){
    arsort($ips_probs); $prediksiMapel += array_slice($ips_probs,0,2,true);
    arsort($ipa_probs); $prediksiMapel += array_slice($ipa_probs,0,1,true);
    arsort($bahasa_probs); $prediksiMapel += array_slice($bahasa_probs,0,1,true);
}
else {
    arsort($bahasa_probs); $prediksiMapel += array_slice($bahasa_probs,0,2,true);
    arsort($ipa_probs); $prediksiMapel += array_slice($ipa_probs,0,1,true);
    arsort($ips_probs); $prediksiMapel += array_slice($ips_probs,0,1,true);
}

$total = array_sum($prediksiMapel);
if($total>0){
    foreach($prediksiMapel as $m=>$v){
        $prediksiMapel[$m] = $v/$total;
    }
}

// === PEMBAGIAN DATA TRAINING DAN TESTING (80:20) ===
mt_srand(42);
shuffle($dataset);
$totalData = count($dataset);
$trainSize = floor(0.8 * $totalData);
$trainSet = array_slice($dataset, 0, $trainSize);
$testSet  = array_slice($dataset, $trainSize);

// === TRAIN MODEL DENGAN DATA TRAINING ===
$labels = ["IPA","IPS","Bahasa"];
$features = ["matematika","ipa","ips","bahasa_inggris"];

$models = [];
foreach ($labels as $label) {
    $models[$label] = trainLogReg($trainSet, $features, $label);
}

// === UJI MODEL DENGAN DATA TESTING ===
$benar = 0;
$totalTest = 0;

foreach ($testSet as $row) {
    // Ambil fitur dari dataset
    $x = [
        $row['matematika'] / 100,
        $row['ipa'] / 100,
        $row['ips'] / 100,
        $row['bahasa_inggris'] / 100
    ];

    // Hitung probabilitas untuk setiap jurusan
    $predProb = [];
    foreach ($labels as $label) {
        list($w, $b) = $models[$label];
        $predProb[$label] = predictLogReg($x, $w, $b);
    }

    // Normalisasi
    $sumP = array_sum($predProb);
    foreach ($predProb as $label => $p) {
        $predProb[$label] = $sumP > 0 ? $p / $sumP : 0;
    }

    // Ambil hasil prediksi
    arsort($predProb);
    $prediksi = array_key_first($predProb);

    // Bandingkan dengan jurusan sebenarnya
    if (strtolower(trim($prediksi)) == strtolower(trim($row['jurusan']))) {
        $benar++;
    }
    $totalTest++;
}

$akurasi = ($totalTest > 0) ? ($benar / $totalTest) * 100 : 0;

// === UJI MODEL DENGAN DATA TESTING ===
$benar = 0;
$totalTest = 0;

$truePositive = ["IPA"=>0, "IPS"=>0, "Bahasa"=>0];
$falsePositive = ["IPA"=>0, "IPS"=>0, "Bahasa"=>0];
$falseNegative = ["IPA"=>0, "IPS"=>0, "Bahasa"=>0];

foreach ($testSet as $row) {
    $x = [
        $row['matematika'] / 100,
        $row['ipa'] / 100,
        $row['ips'] / 100,
        $row['bahasa_inggris'] / 100
    ];

    $predProb = [];
    foreach ($labels as $label) {
        list($w, $b) = $models[$label];
        $predProb[$label] = predictLogReg($x, $w, $b);
    }

    $sumP = array_sum($predProb);
    foreach ($predProb as $label => $p) {
        $predProb[$label] = $sumP > 0 ? $p / $sumP : 0;
    }

    arsort($predProb);
    $prediksi = array_key_first($predProb);
    $aktual = trim($row['jurusan']);

    if (strtolower($prediksi) == strtolower($aktual)) {
        $benar++;
        $truePositive[$aktual]++;
    } else {
        $falsePositive[$prediksi]++;
        $falseNegative[$aktual]++;
    }

    $totalTest++;
}

$akurasi = ($totalTest > 0) ? ($benar / $totalTest) * 100 : 0;

// === HITUNG PRECISION DAN RECALL PER KELAS ===
$precision = [];
$recall = [];

foreach ($labels as $label) {
    $tp = $truePositive[$label];
    $fp = $falsePositive[$label];
    $fn = $falseNegative[$label];

    $precision[$label] = ($tp + $fp) > 0 ? $tp / ($tp + $fp) : 0;
    $recall[$label] = ($tp + $fn) > 0 ? $tp / ($tp + $fn) : 0;
}

// === RATA-RATA PRECISION DAN RECALL ===
$avgPrecision = array_sum($precision) / count($precision);
$avgRecall = array_sum($recall) / count($recall);

// === SIMPAN HASIL PREDIKSI KE DATABASE ===

// --- Simpan Prediksi Jurusan ---
if (!empty($prediksiJurusan)) {
    // Hapus data lama agar tidak duplikat
    $stmt = $conn->prepare("DELETE FROM prediksi_jurusan WHERE siswa_id=?");
    $stmt->bind_param("i", $siswa_id);
    $stmt->execute();
    $stmt->close();

    // Simpan hasil baru untuk SEMUA jurusan
    $stmt = $conn->prepare("INSERT INTO prediksi_jurusan (siswa_id, jurusan, probabilitas) VALUES (?, ?, ?)");
    foreach ($prediksiJurusan as $jur => $p) {
        $stmt->bind_param("isd", $siswa_id, $jur, $p);
        $stmt->execute();
    }
    $stmt->close();
}

// --- Simpan Prediksi Mata Pelajaran ---
if (!empty($prediksiMapel)) {
    // Hapus data lama agar tidak duplikat
    $stmt = $conn->prepare("DELETE FROM prediksi_mata_pelajaran WHERE siswa_id=?");
    $stmt->bind_param("i", $siswa_id);
    $stmt->execute();
    $stmt->close();

    // Simpan hasil baru (loop tiap mapel)
    $stmt = $conn->prepare("INSERT INTO prediksi_mata_pelajaran (siswa_id, mapel_cocok, skor_prediksi) VALUES (?, ?, ?)");
    foreach ($prediksiMapel as $mapel => $skor) {
        $stmt->bind_param("isd", $siswa_id, $mapel, $skor);
        $stmt->execute();
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Prediksi Jurusan & Mapel</title>
<link rel="stylesheet" href="../css/dashboardSiswa.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* === KONTAINER UTAMA === */
.container {
  background:#fff;
  padding:25px;
  border-radius:15px;
  max-width:900px;
  margin:auto;
  box-shadow:0 4px 20px rgba(0,0,0,0.08);
  font-family: 'Segoe UI', Tahoma, sans-serif;
}
h1 { text-align:center; margin-bottom:15px; color:#1e293b; }
h2 {
  margin-top:30px;
  color:#1e3a8a;
  border-bottom:3px solid #1d4ed8;
  display:inline-block;
  padding-bottom:5px;
}
.info-box {
  background:#eef4ff;
  border-left:5px solid #1d4ed8;
  padding:15px;
  border-radius:8px;
  margin-bottom:25px;
  color:#1e293b;
}

/* === LINGKARAN PREDIKSI JURUSAN === */
.prediksi-jurusan {
  display:flex;
  justify-content:center;
  align-items:center;
  margin:30px 0;
}
.circle {
  position:relative;
  width:180px;
  height:180px;
}
.circle svg {
  width:100%;
  height:100%;
  transform:rotate(-90deg);
}
.bg {
  fill:none;
  stroke:#e5e7eb;
  stroke-width:3.8;
}
.progress {
  fill:none;
  stroke:#1d4ed8;
  stroke-width:3.8;
  stroke-linecap:round;
  animation: progressAnim 2s ease-out;
}
@keyframes progressAnim {
  from { stroke-dasharray:0 100; }
  to { stroke-dasharray:100 100; }
}
.label {
  position:absolute;
  top:50%;
  left:50%;
  transform:translate(-50%, -50%);
  text-align:center;
}
.label h2 {
  font-size:18px;
  color:#1d4ed8;
  margin-bottom:5px;
}
.label p {
  font-size:13px;
  color:#475569;
}
.hasil-utama {
  background:#e8f6e8;
  border-left:5px solid #28a745;
  padding:12px 15px;
  border-radius:6px;
  margin-top:15px;
  font-size:16px;
  color:#1e293b;
}

/* === PREDIKSI MAPEL === */
.prediksi-mapel {
  text-align:center;
  margin-top:20px;
}
.prediksi-mapel p {
  color:#475569;
  margin-bottom:15px;
}
.mapel-list {
  display:flex;
  justify-content:center;
  flex-wrap:wrap;
  gap:12px;
}
.mapel {
  padding:15px 25px;
  border-radius:30px;
  color:white;
  font-weight:500;
  box-shadow:0 3px 10px rgba(0,0,0,0.1);
  transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.mapel.utama { background:#2563EB; }
.mapel.pendukung { background:#60A5FA; }
.mapel.lain { background:#9CA3AF; }
.mapel:hover {
  transform: translateY(-4px);
  box-shadow:0 6px 15px rgba(0,0,0,0.15);
}
</style>
</head>
<body>
<header>
  <div class="logo"><i class="fa-solid fa-graduation-cap"></i> PPDB</div>
  <div class="user-info">
    <span><i class="fa-solid fa-user-circle"></i> Halo, <b><?= htmlspecialchars($nama_lengkap) ?></b></span>
  </div>
</header>

<div class="sidebar">
  <ul>
    <li><a href="dashboardSiswa.php"><i class="fa-solid fa-gauge"></i> Dashboard</a></li>
    <li><a href="profilSiswa.php"><i class="fa-solid fa-user"></i> Profil</a></li>
    <li><a class="active" href="pendaftaranSiswa.php"><i class="fa-solid fa-file-pen"></i> Pendaftaran</a></li>
    <li><a href="prediksiMapel.php"><i class="fa-solid fa-book"></i> Prediksi Mapel</a></li>
    <li><a href="daftarUlang.php"><i class="fa-solid fa-clipboard-check"></i> Daftar Ulang</a></li>
  </ul>
</div>

<div class="main">
<div class="container">
  <h1>Hasil Prediksi untuk <?= htmlspecialchars($nama_lengkap) ?></h1>

  <div class="info-box">
    <p>
      Sistem telah memprediksi jurusan dan mata pelajaran pilihan berdasarkan nilai akademik Anda.
      <br><strong>Catatan:</strong> Prediksi ini bersifat perkiraan dan tidak sepenuhnya benar. 
      Faktor lain seperti minat, bakat, dan hasil seleksi akhir juga memengaruhi hasil sebenarnya.
    </p>
  </div>

  <!-- ===== PREDIKSI JURUSAN DALAM LINGKARAN ===== -->
  <?php 
    $percentJurusan = round($prediksiJurusan[$jurusan_dominan] * 100, 2);
  ?>
  <h2>Prediksi Jurusan</h2>
  <div class="prediksi-jurusan">
    <div class="circle">
      <svg viewBox="0 0 36 36">
        <path class="bg" d="M18 2.0845
            a 15.9155 15.9155 0 0 1 0 31.831
            a 15.9155 15.9155 0 0 1 0 -31.831" />
        <path class="progress" stroke-dasharray="<?= $percentJurusan ?>, 100" d="M18 2.0845
            a 15.9155 15.9155 0 0 1 0 31.831
            a 15.9155 15.9155 0 0 1 0 -31.831" />
      </svg>
      <div class="label">
        <h2><?= htmlspecialchars($jurusan_dominan) ?></h2>
        <p>Prediksi Jurusan</p>
      </div>
    </div>
  </div>

  <div class="hasil-utama">
    <p>Berdasarkan analisis, Anda paling cocok untuk jurusan 
      <strong><?= htmlspecialchars($jurusan_dominan) ?></strong>. 
    </p>
  </div>

  <!-- ===== PREDIKSI MAPEL DALAM KARTU ===== -->
  <h2>Prediksi 4 Mata Pelajaran Pilihan</h2>
  <div class="prediksi-mapel">
    <p>Mata pelajaran berikut direkomendasikan berdasarkan kecocokan dengan hasil prediksi jurusan Anda.</p>
    <div class="mapel-list">
      <?php 
      $warnaMapel = ['utama'=>'#2563EB','pendukung'=>'#60A5FA','lain'=>'#9CA3AF'];
      $i = 0;
      foreach($prediksiMapel as $mapel => $p): 
        $kelas = $i == 0 ? 'utama' : ($i < 2 ? 'pendukung' : 'lain');
        $i++;
      ?>
      <div class="mapel <?= $kelas ?>">
        <span><?= htmlspecialchars($mapel) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

</div>
</body>
</html>
