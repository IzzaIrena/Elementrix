<?php
session_start();
include("../koneksi_mysql.php"); // koneksi MySQL ($conn)

// === CEK LOGIN SISWA ===
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'siswa') {
    header("Location: loginSiswa.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// === AMBIL DATA SISWA ===
$sqlSiswa = "SELECT * FROM siswa WHERE user_id = '$user_id' LIMIT 1";
$resSiswa = mysqli_query($conn, $sqlSiswa);
if (!$rowSiswa = mysqli_fetch_assoc($resSiswa)) {
    die("❌ Siswa tidak ditemukan di database.");
}
$siswa_id = $rowSiswa['id']; // id internal siswa
$nama_lengkap = $rowSiswa['nama_lengkap'];

// === AMBIL NILAI AKADEMIK SISWA ===
$sqlNilai = "SELECT * FROM nilai_akademik WHERE siswa_id = '$siswa_id'";
$resNilai = mysqli_query($conn, $sqlNilai);

$nilaiSiswa = [];
while ($row = mysqli_fetch_assoc($resNilai)) {
    $kode = strtoupper(trim($row['kode_mapel']));
    $semester = (int)$row['semester'];
    $nilai = (float)$row['nilai'];

    if ($kode === '') continue;
    if (!isset($nilaiSiswa[$kode])) $nilaiSiswa[$kode] = [0,0,0,0,0];
    $nilaiSiswa[$kode][$semester - 1] = $nilai;
}

// === CEK APA ADA NILAI ===
if (empty($nilaiSiswa)) {
    echo "<script>
            alert('Prediksi hanya bisa diakses setelah Anda melakukan pendaftaran nilai.');
            window.location.href='pendaftaranSiswa.php';
          </script>";
    exit;
}

// === HITUNG RATA-RATA PER MAPEL ===
$avgNorm = [];
foreach ($nilaiSiswa as $kode => $semester) {
    $isi = array_filter($semester, fn($v) => $v !== null && $v !== '' && $v > 0);
    $avgNorm[$kode] = count($isi) ? array_sum($isi)/count($isi) : 0;
}

// Pastikan semua mapel utama ada
$mapelPrediksi = ['MTK','IPA','IPS','BINDO','BING'];
foreach ($mapelPrediksi as $kode) {
    if (!isset($avgNorm[$kode])) $avgNorm[$kode] = 0;
}

// === BACA DATASET CSV ===
$dataset = [];
if (($handle = fopen("../dataset_nilai_siswa_300_dengan_mapel.csv", "r")) !== false) {
    $header = fgetcsv($handle, 0, ";");
    $headerNorm = [];
    foreach($header as $col) $headerNorm[] = strtolower(str_replace(" ","_",$col));
    while(($row = fgetcsv($handle,0,";")) !== false){
        $data = [];
        foreach($headerNorm as $i=>$col){
            $val = trim($row[$i]);
            $data[$col] = is_numeric($val) ? (float)$val : $val;
        }
        $dataset[] = $data;
    }
    fclose($handle);
}

// === FUNGSI BANTU ===
function sigmoid($z){ return 1.0/(1.0+exp(-$z)); }
function normalize($arr){
    $sum = array_sum($arr);
    $res = [];
    foreach($arr as $k=>$v) $res[$k] = $sum>0 ? $v/$sum : 0;
    return $res;
}
function hitungBobotLinear($dataset,$fiturX,$fiturY){
    $sumX1=$sumX2=$sumY=$sumX1Y=$sumX2Y=0;
    $n=0;
    foreach($dataset as $row){
        if(isset($row[$fiturX[0]]) && isset($row[$fiturX[1]]) && isset($row[$fiturY])){
            $x1=(float)$row[$fiturX[0]];
            $x2=(float)$row[$fiturX[1]];
            $y =(float)$row[$fiturY];
            $sumX1+=$x1; $sumX2+=$x2; $sumY+=$y;
            $sumX1Y+=$x1*$y; $sumX2Y+=$x2*$y;
            $n++;
        }
    }
    if($n==0) return [0.5,0.5];
    $w1 = $sumX1Y/($sumX1*$sumY + 1e-6);
    $w2 = $sumX2Y/($sumX2*$sumY + 1e-6);
    $total = $w1+$w2;
    if($total>0){ $w1/=$total; $w2/=$total; } else { $w1=$w2=0.5; }
    return [$w1,$w2];
}

// === MODEL REGRESI ===
list($wIPA_Kimia,$wMTK_Kimia) = hitungBobotLinear($dataset, ["ipa","matematika"],"kimia");
list($wMTK_Fisika,$wIPA_Fisika) = hitungBobotLinear($dataset, ["matematika","ipa"],"fisika");
list($wIPS_Ekonomi,$wMTK_Ekonomi) = hitungBobotLinear($dataset, ["ips","matematika"],"ekonomi");
list($wIPS_Geografi,$wIPA_Geografi) = hitungBobotLinear($dataset, ["ips","ipa"],"geografi");

// === TRAIN LOGISTIC REGRESSION ===
$labels = ["IPA","IPS","Bahasa"];
$features = ["matematika","ipa","ips","bahasa_indonesia","bahasa_inggris"];

function trainLogReg($dataset,$features,$targetLabel,$alpha=0.01,$iter=400){
    $weights=array_fill(0,count($features),0.01);
    $bias=0.0;
    for($i=0;$i<$iter;$i++){
        foreach($dataset as $row){
            $x=array_map(fn($f)=>$row[$f]/100.0,$features);
            $y = ($row['jurusan']==$targetLabel)?1:0;
            $z = $bias;
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
foreach($labels as $label) $models[$label] = trainLogReg($dataset,$features,$label);

// === PREDIKSI JURUSAN ===
$baru = [
    "matematika"=>$avgNorm["MTK"]/100,
    "ipa"=>$avgNorm["IPA"]/100,
    "ips"=>$avgNorm["IPS"]/100,
    "bahasa_indonesia"=>$avgNorm["BINDO"]/100,
    "bahasa_inggris"=>$avgNorm["BING"]/100
];
$x = array_map(fn($f)=>$baru[$f],$features);

$prob=[];
foreach($labels as $label){
    list($w,$b)=$models[$label];
    $prob[$label]=predictLogReg($x,$w,$b);
}
$sumProb=array_sum($prob);
foreach($prob as $label=>$p) $prob[$label]=$sumProb>0?$p/$sumProb:0;
arsort($prob);
$prediksiJurusan = $prob;
$jurusan_dominan = array_key_first($prediksiJurusan);

// === PREDIKSI MAPEL ===
$ipa_scores = ["Biologi"=>$avgNorm["IPA"], "Kimia"=>$wIPA_Kimia*$avgNorm["IPA"]+$wMTK_Kimia*$avgNorm["MTK"], "Fisika"=>$wMTK_Fisika*$avgNorm["MTK"]+$wIPA_Fisika*$avgNorm["IPA"]];
$ips_scores = ["Sosiologi"=>$avgNorm["IPS"], "Ekonomi"=>$wIPS_Ekonomi*$avgNorm["IPS"]+$wMTK_Ekonomi*$avgNorm["MTK"], "Geografi"=>$wIPS_Geografi*$avgNorm["IPS"]+$wIPA_Geografi*$avgNorm["IPA"]];
$bahasa_scores = ["Bahasa Jerman"=>$avgNorm["BINDO"], "Bahasa Inggris Lanjutan"=>$avgNorm["BING"]];

$ipa_probs = normalize($ipa_scores);
$ips_probs = normalize($ips_scores);
$bahasa_probs = normalize($bahasa_scores);

$prediksiMapel=[];
if($jurusan_dominan=="IPA"){
    arsort($ipa_probs); $prediksiMapel += array_slice($ipa_probs,0,2,true);
    arsort($ips_probs); $prediksiMapel += array_slice($ips_probs,0,1,true);
    arsort($bahasa_probs); $prediksiMapel += array_slice($bahasa_probs,0,1,true);
} elseif($jurusan_dominan=="IPS"){
    arsort($ips_probs); $prediksiMapel += array_slice($ips_probs,0,2,true);
    arsort($ipa_probs); $prediksiMapel += array_slice($ipa_probs,0,1,true);
    arsort($bahasa_probs); $prediksiMapel += array_slice($bahasa_probs,0,1,true);
} else {
    arsort($bahasa_probs); $prediksiMapel += array_slice($bahasa_probs,0,2,true);
    arsort($ipa_probs); $prediksiMapel += array_slice($ipa_probs,0,1,true);
    arsort($ips_probs); $prediksiMapel += array_slice($ips_probs,0,1,true);
}

// Simpan hasil prediksi ke MySQL
$prediksiJurusanStr = [];
foreach($prediksiJurusan as $jurusan => $p){
    $prediksiJurusanStr[] = $jurusan . " (" . round($p*100,2) . "%)";
}
$predJurusanStr = implode(", ", $prediksiJurusanStr);

$prediksiMapelStr = [];
foreach($prediksiMapel as $mapel => $p){
    $prediksiMapelStr[] = $mapel . " (" . round($p*100,2) . "%)";
}
$predMapelStr = implode(", ", $prediksiMapelStr);

// Insert / update
$sqlCheck = "SELECT id FROM prediksi_siswa WHERE siswa_id = '$siswa_id'";
$resCheck = mysqli_query($conn, $sqlCheck);

if(mysqli_num_rows($resCheck) > 0){
    $sqlSave = "UPDATE prediksi_siswa 
                SET prediksi_jurusan='$predJurusanStr', prediksi_mapel='$predMapelStr', tanggal=NOW()
                WHERE siswa_id='$siswa_id'";
}else{
    $sqlSave = "INSERT INTO prediksi_siswa (siswa_id, prediksi_jurusan, prediksi_mapel)
                VALUES ('$siswa_id', '$predJurusanStr', '$predMapelStr')";
}

mysqli_query($conn, $sqlSave);

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
<?php include('headerSiswa.php'); ?>
<?php include('sidebarSiswa.php'); ?>

<div class="main">
<div class="container">
  <h1>Rekomendasi untuk <?= htmlspecialchars($nama_lengkap) ?></h1>

  <div class="info-box">
    <p>
      <strong>Catatan:</strong> Prediksi ini hanya perkiraan dan bisa saja keliru.
    </p>
  </div>

  <!-- ===== PREDIKSI JURUSAN DALAM LINGKARAN ===== -->
  <?php 
    $percentJurusan = !empty($prediksiJurusan[$jurusan_dominan]) ? round($prediksiJurusan[$jurusan_dominan] * 100, 2) : 0;
  ?>
  <h2>Rekomendasi Jurusan</h2>
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
    <p>👉 Berdasarkan analisis, Anda paling cocok untuk jurusan 
      <strong><?= htmlspecialchars($jurusan_dominan) ?></strong>. 
    </p>
  </div>

  <!-- ===== PREDIKSI MAPEL DALAM KARTU ===== -->
  <h2>Rekomendasi 4 Mata Pelajaran Pilihan</h2>
  <div class="prediksi-mapel">
    <p>Mata pelajaran berikut direkomendasikan berdasarkan kecocokan dengan hasil prediksi jurusan Anda.</p>
    <div class="mapel-list">
      <?php 
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
