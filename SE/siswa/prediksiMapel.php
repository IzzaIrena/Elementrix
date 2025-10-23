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

?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Prediksi Jurusan & Mapel</title>
<link rel="stylesheet" href="../css/dashboardSiswa.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
.container{background:#fff;padding:20px;border-radius:10px;max-width:900px;margin:auto;}
table{width:100%;border-collapse:collapse;margin-top:15px;}
th,td{border:1px solid #ccc;padding:8px;text-align:center;}
th{background:#007bff;color:#fff;}
h3{margin-top:25px;}
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

    <!-- <h2>Akurasi Model</h2>
    <p><b><?= round($akurasi, 2) ?>%</b> dari total <?= $totalTest ?> data uji</p> -->


    <h2>Prediksi Jurusan</h2>
    <table>
        <tr><th>Jurusan</th><th>Probabilitas (%)</th></tr>
        <?php foreach($prediksiJurusan as $jur => $p): ?>
        <tr class="<?= ($jur == $jurusan_dominan ? 'highlight' : '') ?>">
            <td><?= htmlspecialchars($jur) ?></td>
            <td><?= round($p*100,2) ?>%</td>
        </tr>
        <?php endforeach; ?>
    </table>

    <h2>Prediksi 4 Mata Pelajaran Pilihan</h2>
    <table>
        <tr><th>Mata Pelajaran</th><th>Skor Prediksi (%)</th></tr>
        <?php foreach($prediksiMapel as $mapel => $p): ?>
        <tr>
            <td><?= htmlspecialchars($mapel) ?></td>
            <td><?= round($p*100,2) ?>%</td>
        </tr>
        <?php endforeach; ?>
    </table>
  </div>
</div>
</body>
</html>
