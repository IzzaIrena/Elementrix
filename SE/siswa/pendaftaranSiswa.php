<?php
session_start();
include("../koneksi_mysql.php"); // koneksi MySQL ($conn)

// ==========================
// CEK LOGIN SISWA
// ==========================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'siswa') {
    header("Location: loginSiswa.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// ==========================
// TAHUN AKADEMIK AKTIF
// ==========================
$tahunAktifQuery = mysqli_query($conn, "SELECT * FROM tahun_akademik WHERE status='aktif' LIMIT 1");
if(mysqli_num_rows($tahunAktifQuery) == 0){
    die("<h3 style='color:red;text-align:center;margin-top:50px;'>⚠️ Belum ada tahun akademik aktif! Hubungi admin.</h3>");
}
$tahunAktif = mysqli_fetch_assoc($tahunAktifQuery);
$tahun_id = $tahunAktif['id'];
$nama_tahun_aktif = $tahunAktif['nama_tahun'];

// ==========================
// AMBIL DATA ATURAN SELEKSI & ZONASI
// ==========================
$tglNow = date("Y-m-d");
$mode = "akademik";
$aturanAktif = null;

// Aturan seleksi
$aturanSeleksiQuery = mysqli_query($conn, "SELECT * FROM aturan_seleksi");
while($a = mysqli_fetch_assoc($aturanSeleksiQuery)){
    if($tglNow >= $a['tanggal_mulai'] && $tglNow <= $a['tanggal_selesai']){
        $aturanAktif = $a;
        $mode = "akademik";
        break;
    }
}

// Jika belum ada, cek zonasi
if(!$aturanAktif){
    $aturanZonasiQuery = mysqli_query($conn, "SELECT * FROM aturan_zonasi");
    while($z = mysqli_fetch_assoc($aturanZonasiQuery)){
        if($tglNow >= $z['tanggal_mulai'] && $tglNow <= $z['tanggal_selesai']){
            $aturanAktif = $z;
            $mode = "zonasi";
            break;
        }
    }
}

// Cek pendaftaran tutup
$pendaftaran_tutup = !$aturanAktif;

// ==========================
// AMBIL DATA SISWA
// ==========================
$siswaQuery = mysqli_query($conn, "SELECT * FROM siswa WHERE user_id='$user_id' LIMIT 1");
$siswa = mysqli_fetch_assoc($siswaQuery);

$nama = $siswa['nama_lengkap'] ?? '';
$nisn = $siswa['nisn'] ?? '';
$nik = $siswa['nik'] ?? '';
$alamat = $siswa['alamat'] ?? '';
$no_hp = $siswa['no_hp'] ?? '';
$tempat_lahir = $siswa['tempat_lahir'] ?? '';
$tgl_lahir = $siswa['tanggal_lahir'] ?? '';
$jk = $siswa['jk'] ?? '';
$latitude = $siswa['latitude'] ?? '';
$longitude = $siswa['longitude'] ?? '';
$siswa_id_db = $siswa['id'] ?? '';

// ==========================
// DATA TAMBAHAN (Ortu & Sekolah Asal)
// ==========================
$ortuQuery = mysqli_query($conn, "SELECT * FROM ortu_wali WHERE siswa_id='$siswa_id_db' LIMIT 1");
$dataOrtu = mysqli_fetch_assoc($ortuQuery);

$sekolahAsalQuery = mysqli_query($conn, "SELECT * FROM sekolah_asal WHERE siswa_id='$siswa_id_db' LIMIT 1");
$sekolahAsal = mysqli_fetch_assoc($sekolahAsalQuery);
$nama_sekolah_asal = $sekolahAsal['nama_sekolah_asal'] ?? '';
$npsn_sekolah_asal = $sekolahAsal['npsn_sekolah_asal'] ?? '';
$alamat_sekolah_asal = $sekolahAsal['alamat_sekolah_asal'] ?? '';

// ==========================
// List Sekolah, Mapel, Dokumen
// ==========================
$sekolahList = [];
$res = mysqli_query($conn, "SELECT * FROM sekolah");
while($row = mysqli_fetch_assoc($res)) $sekolahList[$row['id']] = $row;

$mapelList = [];
$res = mysqli_query($conn, "SELECT * FROM aturan_mapel");
while($row = mysqli_fetch_assoc($res)) $mapelList[$row['kode_mapel']] = $row;

$dokumenList = [];
$res = mysqli_query($conn, "SELECT * FROM aturan_dokumen");
while($row = mysqli_fetch_assoc($res)) $dokumenList[] = $row;

// ==========================
// CEK PENDAFTARAN SISWA
// ==========================
$pendaftaranCheck = mysqli_query($conn, "SELECT * FROM pendaftaran WHERE siswa_id='$siswa_id_db' AND tahun_id='$tahun_id'");
$sudahDaftar = mysqli_num_rows($pendaftaranCheck) > 0;

$pendaftaranData = mysqli_fetch_assoc($pendaftaranCheck);

$gagalZonasi_semua = false;

if ($sudahDaftar) {
    // jika jalur awal ZONASI
    // dan TIDAK lulus di semua pilihan (sekolah_diterima NULL)
    // dan periode AKADEMIK sedang berlangsung
    if ($pendaftaranData['jalur'] === 'zonasi' &&
        is_null($pendaftaranData['sekolah_diterima']) &&
        $mode === 'akademik'
    ) {
        $gagalZonasi_semua = true;
    }
}

if (isset($_GET['edit']) && $gagalZonasi_semua) {
    // izinkan pengeditan field pada form
    $mode = "akademik"; // memaksa form tampil dalam mode akademik
}

// ==========================
// MODE EDIT
// ==========================
$editMode = false;
if (isset($_GET['edit']) && $gagalZonasi_semua) {
    $editMode = true;
}

if ($editMode) {

    // ===============================
    // 1. AMBIL siswa_id berdasarkan user_id
    // ===============================
    $qS = $conn->prepare("SELECT id FROM siswa WHERE user_id = ?");
    $qS->bind_param("i", $user_id);
    $qS->execute();
    $resS = $qS->get_result()->fetch_assoc();

    if ($resS) {
        $siswa_id = $resS['id'];   // id dari tabel siswa
    } else {
        $siswa_id = 0; // untuk menghindari error
    }

    // ===============================
    // 2. DATA PENDAFTARAN (PASTIKAN PAKAI siswa_id YANG BENAR)
    // ===============================
    $q = $conn->prepare("SELECT * FROM pendaftaran WHERE siswa_id=?");
    $q->bind_param("i", $siswa_id);
    $q->execute();
    $pendaftaran = $q->get_result()->fetch_assoc();

    if (!$pendaftaran) {
        $pilihan = [null, null, null];
    } else {
        $pilihan = [
            $pendaftaran['pilihan_ke1'],
            $pendaftaran['pilihan_ke2'],
            $pendaftaran['pilihan_ke3']
        ];
    }

    // ===============================
    // 3. DOKUMEN (juga gunakan siswa_id)
    // ===============================
    $q3 = $conn->prepare("
        SELECT nama_dokumen, file_path 
        FROM dokumen_siswa 
        WHERE siswa_id=?
    ");
    $q3->bind_param("i", $siswa_id);
    $q3->execute();

    $dokumen_siswa = [];
    $res3 = $q3->get_result();
    while ($r = $res3->fetch_assoc()) {
        $dokumen_siswa[$r['nama_dokumen']] = $r['file_path'];
    }

    // ===============================
    // 4. NILAI AKADEMIK
    // ===============================
    $siswa['nilai'] = [];

    $qNilai = $conn->prepare("
        SELECT n.kode_mapel, n.semester, n.nilai, m.nama_mapel
        FROM nilai_akademik n
        JOIN aturan_mapel m ON n.kode_mapel = m.kode_mapel
        WHERE n.siswa_id = ?
        ORDER BY m.id ASC
    ");
    $qNilai->bind_param("i", $siswa_id);
    $qNilai->execute();
    $resNilai = $qNilai->get_result();

    while ($row = $resNilai->fetch_assoc()) {

        $kode = $row['kode_mapel'];
        $sem  = $row['semester'];

        $siswa['nilai'][$kode][$sem] = $row['nilai'];

        $mapelList[$kode] = [
            'nama_mapel' => $row['nama_mapel']
        ];
    }
}

// ==========================
// BATAS PENDAFTARAN
// ==========================
$tanggal_selesai = $aturanAktif['tanggal_selesai'] ?? null;
if($tanggal_selesai && strtotime($tglNow) > strtotime($tanggal_selesai)){
    $pendaftaran_tutup = true;
}

if (isset($_POST['daftar_ulang_akademik']) && $gagalZonasi_semua) {

    $idPendaftaran = $pendaftaranData['id'];

    // Update jalur → akademik
    mysqli_query($conn, "
        UPDATE pendaftaran SET 
            jalur='akademik',
            pilihan_ke1 = pilihan_ke1,
            pilihan_ke2 = pilihan_ke2,
            pilihan_ke3 = pilihan_ke3,
            status_seleksi1 = 'belum',
            status_seleksi2 = 'belum',
            status_seleksi3 = 'belum',
            sekolah_diterima = NULL,
            tanggal_daftar = NOW()
        WHERE id='$idPendaftaran'
    ");

    // Reset status verifikasi semua sekolah untuk pendaftaran ini
    mysqli_query($conn, "
        UPDATE verifikasi_pendaftaran SET
            status_verifikasi = 'menunggu',
            tanggal_verifikasi = NULL
        WHERE pendaftaran_id='$idPendaftaran'
    ");

    echo "<script>alert('Pendaftaran berhasil dipindah ke jalur Akademik.');window.location='pendaftaranSiswa.php';</script>";
    exit;
}

// ==========================
// PROSES PENDAFTARAN / UPDATE
// ==========================
if($_SERVER['REQUEST_METHOD'] === 'POST' && !$pendaftaran_tutup){

    // ========================== DATA PRIBADI ==========================
    $nik_post = $_POST['nik'] ?? '';
    $alamat_post = $_POST['alamat'] ?? '';
    $no_hp_post = $_POST['no_hp'] ?? '';
    $tempat_lahir_post = $_POST['tempat_lahir'] ?? '';
    $tgl_lahir_post = $_POST['tgl_lahir'] ?? '';
    $jk_post = $_POST['jk'] ?? '';
    $latitude_post = $_POST['latitude'] ?? null;
    $longitude_post = $_POST['longitude'] ?? null;

    if($siswa_id_db){
        $sqlUpdateSiswa = "UPDATE siswa SET 
            nik='".mysqli_real_escape_string($conn,$nik_post)."',
            alamat='".mysqli_real_escape_string($conn,$alamat_post)."',
            no_hp='".mysqli_real_escape_string($conn,$no_hp_post)."',
            tempat_lahir='".mysqli_real_escape_string($conn,$tempat_lahir_post)."',
            tanggal_lahir='".mysqli_real_escape_string($conn,$tgl_lahir_post)."',
            jk='".mysqli_real_escape_string($conn,$jk_post)."'
            ".($mode==='zonasi'? ", latitude='".mysqli_real_escape_string($conn,$latitude_post)."', longitude='".mysqli_real_escape_string($conn,$longitude_post)."'" : "")."
            WHERE id='$siswa_id_db'";
        mysqli_query($conn, $sqlUpdateSiswa);
    }else{
        mysqli_query($conn, "INSERT INTO siswa(user_id,nama_lengkap,nik,alamat,no_hp,tempat_lahir,tanggal_lahir,jk,latitude,longitude) VALUES(
            '$user_id','".mysqli_real_escape_string($conn,$nama)."','".mysqli_real_escape_string($conn,$nik_post)."','".mysqli_real_escape_string($conn,$alamat_post)."','".mysqli_real_escape_string($conn,$no_hp_post)."','".mysqli_real_escape_string($conn,$tempat_lahir_post)."','".mysqli_real_escape_string($conn,$tgl_lahir_post)."','".mysqli_real_escape_string($conn,$jk_post)."','".mysqli_real_escape_string($conn,$latitude_post)."','".mysqli_real_escape_string($conn,$longitude_post)."'
        )");
        $siswa_id_db = mysqli_insert_id($conn);
    }

    // ========================== SEKOLAH ASAL ==========================
    $nama_sekolah_asal_post = $_POST['nama_sekolah_asal'] ?? '';
    $npsn_sekolah_asal_post = $_POST['npsn_sekolah_asal'] ?? '';
    $alamat_sekolah_asal_post = $_POST['alamat_sekolah_asal'] ?? '';

    $cekSekolahAsal = mysqli_query($conn, "SELECT * FROM sekolah_asal WHERE siswa_id='$siswa_id_db'");
    if(mysqli_num_rows($cekSekolahAsal)>0){
        mysqli_query($conn, "UPDATE sekolah_asal SET 
            nama_sekolah_asal='".mysqli_real_escape_string($conn,$nama_sekolah_asal_post)."',
            npsn_sekolah_asal='".mysqli_real_escape_string($conn,$npsn_sekolah_asal_post)."',
            alamat_sekolah_asal='".mysqli_real_escape_string($conn,$alamat_sekolah_asal_post)."'
            WHERE siswa_id='$siswa_id_db'");
    }else{
        mysqli_query($conn, "INSERT INTO sekolah_asal(siswa_id,nama_sekolah_asal,npsn_sekolah_asal,alamat_sekolah_asal) VALUES(
            '$siswa_id_db','".mysqli_real_escape_string($conn,$nama_sekolah_asal_post)."','".mysqli_real_escape_string($conn,$npsn_sekolah_asal_post)."','".mysqli_real_escape_string($conn,$alamat_sekolah_asal_post)."'
        )");
    }

    // ========================== ORTU/WALI ==========================
    $nama_ayah = $_POST['nama_ayah'] ?? '';
    $no_hp_ayah = $_POST['no_hp_ayah'] ?? '';
    $nama_ibu = $_POST['nama_ibu'] ?? '';
    $no_hp_ibu = $_POST['no_hp_ibu'] ?? '';
    $nama_wali = $_POST['nama_wali'] ?? '';
    $no_hp_wali = $_POST['no_hp_wali'] ?? '';

    $cekOrtu = mysqli_query($conn, "SELECT * FROM ortu_wali WHERE siswa_id='$siswa_id_db'");
    if(mysqli_num_rows($cekOrtu)>0){
        mysqli_query($conn, "UPDATE ortu_wali SET 
            nama_ayah='".mysqli_real_escape_string($conn,$nama_ayah)."',
            no_hp_ayah='".mysqli_real_escape_string($conn,$no_hp_ayah)."',
            nama_ibu='".mysqli_real_escape_string($conn,$nama_ibu)."',
            no_hp_ibu='".mysqli_real_escape_string($conn,$no_hp_ibu)."',
            nama_wali='".mysqli_real_escape_string($conn,$nama_wali)."',
            no_hp_wali='".mysqli_real_escape_string($conn,$no_hp_wali)."'
            WHERE siswa_id='$siswa_id_db'");
    }else{
        mysqli_query($conn, "INSERT INTO ortu_wali(siswa_id,nama_ayah,no_hp_ayah,nama_ibu,no_hp_ibu,nama_wali,no_hp_wali) VALUES(
            '$siswa_id_db','".mysqli_real_escape_string($conn,$nama_ayah)."','".mysqli_real_escape_string($conn,$no_hp_ayah)."','".mysqli_real_escape_string($conn,$nama_ibu)."','".mysqli_real_escape_string($conn,$no_hp_ibu)."','".mysqli_real_escape_string($conn,$nama_wali)."','".mysqli_real_escape_string($conn,$no_hp_wali)."'
        )");
    }

    // ========================== NILAI AKADEMIK ==========================
    if(isset($_POST['nilai'])){
        mysqli_query($conn,"DELETE FROM nilai_akademik WHERE siswa_id='$siswa_id_db'");
        foreach($_POST['nilai'] as $kode_mapel => $semesterData){
            foreach($semesterData as $semester => $nilai){
                mysqli_query($conn,"INSERT INTO nilai_akademik(siswa_id,kode_mapel,semester,nilai) VALUES(
                    '$siswa_id_db','".mysqli_real_escape_string($conn,$kode_mapel)."','".mysqli_real_escape_string($conn,$semester)."','".mysqli_real_escape_string($conn,$nilai)."'
                )");
            }
        }
    }

    // ========================== UPLOAD DOKUMEN ==========================
    $uploadDir = "../uploads/dokumen/";
    if(!is_dir($uploadDir)) mkdir($uploadDir,0777,true);

    foreach($dokumenList as $d){
        $docKey = $d['nama_dokumen'];

        // ========================== ADA FILE BARU DIUPLOAD ==========================
        if(isset($_FILES['dokumen']['name'][$docKey]) && $_FILES['dokumen']['error'][$docKey] == 0){

            $filename = $_FILES['dokumen']['name'][$docKey];
            $fileTmp  = $_FILES['dokumen']['tmp_name'][$docKey];
            $fileExt  = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            // Validasi ekstensi
            $allowedExt = strtolower($d['tipe_dokumen']); 
            if($fileExt != $allowedExt){
                die("File '$docKey' harus berekstensi $allowedExt.");
            }

            // Buat nama file unik
            $newFileName = $siswa_id_db . "_" . time() . "_" . rand(100,999) . "." . $fileExt;
            $targetPath = $uploadDir . $newFileName;

            // 1️⃣ Hapus dokumen lama jika ada
            $old = mysqli_query($conn, 
                "SELECT file_path FROM dokumen_siswa
                WHERE siswa_id='$siswa_id_db' AND nama_dokumen='$docKey'
                LIMIT 1"
            );

            if(mysqli_num_rows($old) > 0){
                $oldFile = mysqli_fetch_assoc($old)['file_path'];
                if(file_exists($uploadDir.$oldFile)){
                    unlink($uploadDir.$oldFile); // hapus file lama
                }

                // Hapus data lama dari database
                mysqli_query($conn,
                    "DELETE FROM dokumen_siswa
                    WHERE siswa_id='$siswa_id_db'
                    AND nama_dokumen='$docKey'"
                );
            }

            // 2️⃣ Upload file baru
            if(move_uploaded_file($fileTmp, $targetPath)){
                mysqli_query($conn,
                    "INSERT INTO dokumen_siswa(siswa_id, nama_dokumen, file_path)
                    VALUES('$siswa_id_db', '".mysqli_real_escape_string($conn,$docKey)."',
                            '".mysqli_real_escape_string($conn,$newFileName)."')"
                );
            }

        } else {
            // ========================== TIDAK ADA FILE BARU ==========================
            // Jika dokumen wajib dan belum tersimpan sebelumnya → ERROR
            if($d['wajib'] == 1){
                $cek = mysqli_query($conn,
                    "SELECT id FROM dokumen_siswa
                    WHERE siswa_id='$siswa_id_db'
                    AND nama_dokumen='$docKey'
                    LIMIT 1");

                if(mysqli_num_rows($cek) == 0){
                    die("Dokumen '$docKey' wajib diupload!");
                }
            }
        }
    }
    
    // ========================== PILIHAN SEKOLAH ==========================
    if($_SERVER['REQUEST_METHOD'] === 'POST' && !$pendaftaran_tutup){

        $pilihan = $_POST['sekolah'] ?? [];
        $pilihan1 = !empty($pilihan[0]) ? mysqli_real_escape_string($conn, $pilihan[0]) : null;
        $pilihan2 = !empty($pilihan[1]) ? mysqli_real_escape_string($conn, $pilihan[1]) : null;
        $pilihan3 = !empty($pilihan[2]) ? mysqli_real_escape_string($conn, $pilihan[2]) : null;

        $cek = mysqli_query($conn, "SELECT * FROM pendaftaran WHERE siswa_id='$siswa_id_db' AND tahun_id='$tahun_id' LIMIT 1");

        if(mysqli_num_rows($cek) > 0){
            // UPDATE
            $sql = "UPDATE pendaftaran SET 
                        pilihan_ke1 = ".($pilihan1 ? "'$pilihan1'" : "NULL").",
                        pilihan_ke2 = ".($pilihan2 ? "'$pilihan2'" : "NULL").",
                        pilihan_ke3 = ".($pilihan3 ? "'$pilihan3'" : "NULL").",
                        jalur = '$mode',
                        status_seleksi1='belum',
                        status_seleksi2='belum',
                        status_seleksi3='belum',
                        tanggal_daftar = '".date("Y-m-d H:i:s")."'
                    WHERE siswa_id='$siswa_id_db' AND tahun_id='$tahun_id'";
            mysqli_query($conn, $sql);

            // Reset status verifikasi
            $pendaftaran_id = mysqli_fetch_assoc($cek)['id'];
            mysqli_query($conn, "DELETE FROM verifikasi_pendaftaran WHERE pendaftaran_id='$pendaftaran_id'");
            
        } else {
            // INSERT
            $sql = "INSERT INTO pendaftaran
                    (siswa_id, tahun_id, pilihan_ke1, pilihan_ke2, pilihan_ke3, jalur, status_seleksi1, status_seleksi2, status_seleksi3, tanggal_daftar)
                    VALUES(
                        '$siswa_id_db',
                        '$tahun_id',
                        ".($pilihan1 ? "'$pilihan1'" : "NULL").",
                        ".($pilihan2 ? "'$pilihan2'" : "NULL").",
                        ".($pilihan3 ? "'$pilihan3'" : "NULL").",
                        '$mode',
                        'belum','belum','belum',
                        '".date("Y-m-d H:i:s")."'
                    )";
            mysqli_query($conn, $sql);
        }
    }

    $success = "Data pendaftaran berhasil disimpan.";
    $response = [
        'success' => true,
        'editMode' => $editMode,
        'message' => $success
    ];
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;

}

// =============================================
// LOGIKA TAMPILAN
// Jika sudah daftar dan tidak edit → tampilkan ringkasan
// Jika klik edit atau belum daftar → tampilkan form pendaftaran
// =============================================
if ($sudahDaftar && !$editMode) {
    $content_page = "tampilkan_pendaftaran.php";
} else {
    $content_page = "form_input_pendaftaran.php";
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Pendaftaran Siswa</title>
<link rel="stylesheet" href="../css/dashboardSiswa.css">
<link rel="stylesheet" href="../css/pendaftaranSiswa.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
<?php include('headerSiswa.php'); ?>
<?php include('sidebarSiswa.php'); ?>

<div class="main">
    <h2><i class="fa-solid fa-file-pen"></i> Formulir Pendaftaran (Mode: <?= strtoupper($mode); ?>)</h2>
    <h3 style="text-align:center;">Tahun Akademik Aktif: 
        <span style="color:#007bff;"><?= htmlspecialchars($nama_tahun_aktif); ?></span>
    </h3>

    <?php if(isset($gagalZonasi_semua) && $gagalZonasi_semua): ?>
        <div style="margin-top:20px">
            <form method="post">
                <button type="submit" name="daftar_ulang_akademik" 
                    style="background:#0066ff;color:white;padding:10px 20px;border-radius:6px;border:none;">
                    Daftar Akademik
                </button>
            </form>

            <form action="" method="get" style="margin-top:10px">
                <button type="submit" name="edit" value="1"
                    style="background:#00aa55;color:white;padding:10px 20px;border-radius:6px;border:none;">
                    Edit Pendaftaran
                </button>
            </form>
        </div>
    <?php endif; ?>

    <?php if(isset($success)): ?>
        <div class="alert-section" style="background:#e0ffe0;"><?= $success; ?></div>
    <?php endif; ?>

    <div class="form-container" id="formContainer">
        <?php
        // Jika sudah daftar namun klik EDIT → tampilkan FORM INPUT
        if ($sudahDaftar && $editMode) {
            include("form_input_pendaftaran.php");

        // Jika sudah daftar & tidak edit → tampilkan ringkasan
        } elseif ($sudahDaftar) {
            include("tampilkan_pendaftaran.php");

        // Jika pendaftaran tutup → tampilkan alert
        } elseif ($pendaftaran_tutup) {
            echo '<div class="alert-section" style="background:#ffe0e0;border-left:5px solid red;padding:15px;">
                    <i class="fa-solid fa-circle-xmark"></i> Pendaftaran telah ditutup.
                </div>';

        // Jika belum daftar → tampilkan form input
        } else {
            include("form_input_pendaftaran.php");
        }
        ?>
    </div>
</div>

<script>
$(document).ready(function(){
    $('#formPendaftaran').on('submit', function(e){
        e.preventDefault();
        let formData = new FormData(this);
        $.ajax({
            url: 'pendaftaranSiswa.php',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            beforeSend: function(){
                $('#formContainer').html('<p style="text-align:center;">⏳ Menyimpan data, mohon tunggu...</p>');
            },
            success: function(response){
                // Response dari PHP sudah JSON
                if(response.success){
                    $('#formContainer').html('<p style="text-align:center;color:green;">✅ ' + response.message + '</p>');

                    if(response.editMode){
                        // Jika mode edit, redirect ke ringkasan
                        setTimeout(function(){
                            window.location.href = 'pendaftaranSiswa.php';
                        }, 1000);
                    } else {
                        // Mode normal, tetap di form (reload untuk membersihkan form)
                        setTimeout(function(){ location.reload(); }, 1000);
                    }
                } else {
                    alert('Terjadi kesalahan: ' + (response.message ?? 'Unknown error'));
                }
            }
        });
    });
});
</script>
</body>
</html>
