<?php
session_start();
include("../koneksi_mysql.php"); // koneksi MySQL ($conn)

// ============================
// CEK LOGIN SEKOLAH
// ============================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'sekolah') {
    header("Location: loginSekolah.php");
    exit;
}

$sekolah_id = $_SESSION['sekolah_id'];

// ambil tanggal_selesai dari aturan zonasi terbaru
$rz = mysqli_fetch_assoc(mysqli_query($conn, "SELECT tanggal_selesai FROM aturan_zonasi ORDER BY id DESC LIMIT 1"));
$rs = mysqli_fetch_assoc(mysqli_query($conn, "SELECT tanggal_selesai FROM aturan_seleksi ORDER BY id DESC LIMIT 1"));

$batas_zonasi = $rz['tanggal_selesai'] ?? null;      // format: YYYY-MM-DD
$batas_seleksi = $rs['tanggal_selesai'] ?? null;

// Ambil data sekolah dari MySQL
$stmt = $conn->prepare("SELECT * FROM sekolah WHERE id = ?");
$stmt->bind_param("i", $sekolah_id);
$stmt->execute();
$result = $stmt->get_result();
$sekolahData = $result->fetch_assoc();
$nama_sekolah = $sekolahData['nama_sekolah'] ?? 'Sekolah Tidak Dikenal';
$stmt->close();

// ============================
// AMBIL DAFTAR TAHUN AKADEMIK
// ============================
$tahunList = [];
$qTahun = mysqli_query($conn, "
  SELECT id, nama_tahun 
  FROM tahun_akademik
  ORDER BY created_at DESC
");

while ($row = mysqli_fetch_assoc($qTahun)) {
    $tahunList[] = $row;
}

// default tahun (jika belum pilih)
$selected_tahun = $_GET['tahun_id'] ?? ($tahunList[0]['id'] ?? '');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Data Pendaftar</title>
  <link rel="stylesheet" href="../css/dashboardSekolah.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background: #f6f8fc;
      margin: 0;
      padding: 0;
    }

    .tabs {
      display: flex;
      margin-top: 10px;
      gap: 10px;
    }

    .tab-btn {
      background: #007bff;
      color: #fff;
      border: none;
      padding: 10px 15px;
      border-radius: 6px;
      cursor: pointer;
      transition: 0.3s;
    }

    .tab-btn:hover {
      background: #0056b3;
    }

    .tab-btn.active {
      background: #0056b3;
    }

    .table-section {
      background: #fff;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 3px 8px rgba(0,0,0,0.1);
      margin-top: 20px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 14px;
    }

    th, td {
      padding: 10px;
      border-bottom: 1px solid #ddd;
      text-align: left;
    }

    th {
      background: #007bff;
      color: white;
    }

    tr:hover {
      background: #f9f9f9;
    }

    .aksi-btn {
      background: #28a745;
      color: white;
      border: none;
      padding: 6px 10px;
      border-radius: 6px;
      cursor: pointer;
      font-size: 13px;
      transition: 0.3s;
    }

    .aksi-btn:hover {
      background: #1e7e34;
    }

    header {
      display: flex;
      flex-direction: column;
      margin-bottom: 10px;
    }

    header h1 {
      margin: 0;
    }

    /* Popup Detail */
    .popup {
      display: none;
      position: fixed;
      top: 0; left: 0;
      width: 100%; height: 100%;
      background: rgba(0,0,0,0.6);
      justify-content: center;
      align-items: center;
    }

    .popup-content {
      background: #fff;
      width: 80%;
      max-height: 90vh;
      overflow-y: auto;
      padding: 25px;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.2);
      position: relative;
    }

    .close-btn {
      position: absolute;
      top: 10px; right: 15px;
      font-size: 22px;
      color: #333;
      cursor: pointer;
    }

    .detail-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 15px;
    }

    .detail-table td {
      padding: 8px;
      border-bottom: 1px solid #eee;
    }

    .verif-btn {
      padding: 8px 14px;
      border: none;
      border-radius: 6px;
      color: #fff;
      cursor: pointer;
      margin-top: 15px;
      transition: 0.3s;
    }

    .verif {
      background: #28a745;
    }

    .tolak {
      background: #dc3545;
      margin-left: 10px;
    }

    .verif:hover {
      background: #1e7e34;
    }

    .tolak:hover {
      background: #c82333;
    }
  </style>
</head>
<body>
<?php include("sidebarSekolah.php"); ?>

  <div class="main-content">
    <?php include("headerSekolah.php"); ?>

    <div style="margin-bottom:15px;">
      <label><strong>Tahun Akademik:</strong></label>
      <select id="filterTahun" style="padding:8px;border-radius:6px;">
        <?php foreach ($tahunList as $t): ?>
          <option value="<?= $t['id']; ?>"
            <?= ($t['id'] == $selected_tahun) ? 'selected' : '' ?>>
            <?= htmlspecialchars($t['nama_tahun']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <label><strong>Status Verifikasi:</strong></label><br>
      <select id="filterStatus" style="padding:8px;border-radius:6px;">
        <option value="all">Semua</option>
        <option value="pending">Pending</option>
        <option value="diterima">Diterima</option>
        <option value="ditolak">Ditolak</option>
      </select>
    </div>

    <div class="tabs">
      <button class="tab-btn active" onclick="showTab('zonasi')"><i class="fa fa-location-dot"></i> Jalur Zonasi</button>
      <button class="tab-btn" onclick="showTab('akademik')"><i class="fa fa-graduation-cap"></i> Jalur Akademik</button>
    </div>

    <section id="zonasi" class="table-section">
      <h2>Daftar Pendaftar (Urut Jarak Terdekat)</h2>
      <div id="data-zonasi"><p>Memuat data...</p></div>
    </section>

    <section id="akademik" class="table-section" style="display:none;">
      <h2>Daftar Pendaftar (Urut Nilai Tertinggi)</h2>
      <div id="data-akademik"><p>Memuat data...</p></div>
    </section>
  </div>

  <!-- POPUP DETAIL -->
  <div class="popup" id="popupDetail">
    <div class="popup-content">
      <span class="close-btn" onclick="closePopup()">&times;</span>
      <h2>Detail Pendaftar</h2>
      <div id="detailContent"><p>Memuat data...</p></div>
      <button class="verif-btn verif" id="btnVerif"><i class="fa fa-check"></i> Verifikasi</button>
      <button class="verif-btn tolak" id="btnTolak"><i class="fa fa-times"></i> Tolak</button>
    </div>
  </div>

<script>
function showTab(tab) {
  $(".tab-btn").removeClass("active");
  $(".table-section").hide();
  if(tab === "akademik") {
    $("button:contains('Akademik')").addClass("active");
    $("#akademik").show();
  } else {
    $("button:contains('Zonasi')").addClass("active");
    $("#zonasi").show();
  }
}

function loadData() {
  const tahun_id = $("#filterTahun").val();
  const status   = $("#filterStatus").val();

  $.get("fetchPendaftarMySQL.php", {
      mode: "akademik",
      tahun_id: tahun_id,
      status: status
  }, function(data){
      $("#data-akademik").html(data);
  });

  $.get("fetchPendaftarMySQL.php", {
      mode: "zonasi",
      tahun_id: tahun_id,
      status: status
  }, function(data){
      $("#data-zonasi").html(data);
  });
}

$("#filterTahun").on("change", function(){
  loadData();
});
$("#filterStatus").on("change", function(){
  loadData();
});

function lihatDetail(siswa_id, jalur = "zonasi") {
    $("#popupDetail").fadeIn();
    $("#detailContent").html("<p>Memuat data...</p>");

    // minta detail (tetap pakai siswa_id / pendaftaran_id sesuai implementasimu)
    $.get("fetchDetailPendaftar.php?siswa_id=" + siswa_id, function(data) {
        $("#detailContent").html(data);

        // atur onclick untuk tombol (kirim siswa_id atau pendaftaran_id sesuai updateStatus impl)
        $("#btnVerif").off('click').on('click', function(){
            updateStatus(siswa_id, 'Terverifikasi');
        });
        $("#btnTolak").off('click').on('click', function(){
            updateStatus(siswa_id, 'Verifikasi_Ditolak');
        });

        // Pilih batas yang sesuai berdasarkan jalur
        let batas = (jalur === "akademik" || jalur === "seleksi") ? batasAkademik : batasZonasi;

        // Jika batas tidak tersedia → anggap belum dibuka (biarkan tombol aktif).
        if (batas && today > batas) {
            // nonaktifkan tombol (disabled + style)
            $("#btnVerif").prop("disabled", true).css({"opacity":"0.5","cursor":"not-allowed"});
            $("#btnTolak").prop("disabled", true).css({"opacity":"0.5","cursor":"not-allowed"});
        } else {
            // pastikan tombol aktif jika belum melewati batas
            $("#btnVerif").prop("disabled", false).css({"opacity":"1","cursor":"pointer"});
            $("#btnTolak").prop("disabled", false).css({"opacity":"1","cursor":"pointer"});
        }

        // jalankan script di dalam detailContent (jika ada)
        $("#detailContent script").each(function() {
            eval($(this).text());
        });
    });
}

function closePopup() { $("#popupDetail").fadeOut(); }

function updateStatus(siswa_id, status) {
    $.post("updateStatusPendaftar.php",
    {
        siswa_id: siswa_id,
        status: status
    },
    function(response) {
        // Tampilkan notifikasi
        alert(response);

        // Tutup popup
        $("#popupDetail").fadeOut();

        // Refresh ulang data tabel
        loadData();
    });
}

loadData();
showTab('zonasi');
</script>
<script type="text/javascript">
  // tanggal batas (format ISO YYYY-MM-DD). Bila null, set ke empty string.
  const batasZonasi = "<?= $batas_zonasi ?? '' ?>";
  const batasAkademik = "<?= $batas_seleksi ?? '' ?>";
  // today dalam format YYYY-MM-DD berdasarkan waktu browser
  const today = new Date().toISOString().slice(0,10);
</script>
</body>
</html>
