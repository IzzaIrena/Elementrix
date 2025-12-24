<?php
// form_input_pendaftaran.php
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Form Pendaftaran</title>
<link rel="stylesheet" href="../css/formPendaftaran.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* tinggi header sesuaikan (misal 70px) */
.content-wrapper {
    padding-top: 80px;
}

/* pastikan map tidak menembus header */
#map {
    z-index: 1;
}

</style>
</head>
<body>

<div class="content-wrapper">
    <form id="formPendaftaran" method="POST" enctype="multipart/form-data">
        <!-- ================= PERINGATAN Data) ================= -->
        <div class="peringatan-rapor" style="
        background: #fffbea;
        border-left: 5px solid #f4b400;
        padding: 15px 20px;
        margin: 25px 0;
        border-radius: 10px;
        font-size: 14px;
        color: #444;
        line-height: 1.6;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        ">
        <p style="margin:0 0 10px 0; font-weight:600; font-size:15px; color:#b36b00;">
            <i class="fa-solid fa-circle-exclamation" style="color:#f4b400;"></i>
            &nbsp; Verifikasi Data
        </p>

        <ul style="margin: 0; padding-left: 20px;">
            <li>Upload <b>data asli</b> merupakan <b>syarat wajib</b> saat memilih sekolah.</li>
            <li>Panitia akan melakukan <b>verifikasi digital cepat</b> terhadap berkas kamu.</li>
            <li><b>Ketidaksesuaian isi data</b> antara file yang diunggah dan dokumen asli akan 
                <b>membatalkan kelulusan</b> secara tegas.</li>
        </ul>

        <p style="margin-top:10px; font-size:13px; color:#555;">
            <i class="fa-solid fa-info-circle" style="color:#007bff;"></i>
            Pastikan seluruh data dan dokumen yang kamu unggah <b>benar & jujur</b>.
        </p>
        </div>
        
        <h3>Data Pribadi</h3>
        <div class="form-group">
            <label>NIK:</label>
            <input type="text" name="nik" value="<?= htmlspecialchars($nik); ?>" required>
        </div>
        <div class="form-group">
            <label>Nama Lengkap:</label>
            <input type="text" name="nama" value="<?= htmlspecialchars($nama); ?>" disabled>
        </div>
        <div class="form-group">
            <label>Jenis Kelamin:</label>
            <select name="jk" required>
                <option value="">Pilih</option>
                <option value="L" <?= $jk=='L'?'selected':''; ?>>Laki-laki</option>
                <option value="P" <?= $jk=='P'?'selected':''; ?>>Perempuan</option>
            </select>
        </div>
        <div class="form-group">
            <label>Tempat Lahir:</label>
            <input type="text" name="tempat_lahir" value="<?= htmlspecialchars($tempat_lahir); ?>" required>
        </div>
        <div class="form-group">
            <label>Tanggal Lahir:</label>
            <input type="date" name="tgl_lahir" value="<?= htmlspecialchars($tgl_lahir); ?>" required>
        </div>
        <div class="form-group">
            <label>Alamat:</label>
            <textarea name="alamat" required><?= htmlspecialchars($alamat); ?></textarea>
        </div>
        <div class="form-group">
            <label>No HP:</label>
            <input type="text" name="no_hp" value="<?= htmlspecialchars($no_hp); ?>" required>
        </div>

        <?php if($mode === 'zonasi'): ?>
        <h3>Koordinat Zonasi</h3>
        <div id="map" style="height:300px;"></div>
        <input type="hidden" name="latitude" id="latitude" value="<?= htmlspecialchars($latitude); ?>">
        <input type="hidden" name="longitude" id="longitude" value="<?= htmlspecialchars($longitude); ?>">
        <script>
            var map = L.map('map').setView([<?= $latitude ?: '-4.0330975'; ?>, <?= $longitude ?: '119.6286303'; ?>], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);

            var marker = L.marker([<?= $latitude ?: '-4.0330975'; ?>, <?= $longitude ?: '119.6286303'; ?>], {draggable:true}).addTo(map);

            // Update koordinat saat marker di-drag
            marker.on('dragend', function(e){
                var latlng = marker.getLatLng();
                document.getElementById('latitude').value = latlng.lat;
                document.getElementById('longitude').value = latlng.lng;
            });

            // **Update koordinat saat klik di map**
            map.on('click', function(e){
                var latlng = e.latlng;
                marker.setLatLng(latlng); // pindahkan marker ke lokasi klik
                document.getElementById('latitude').value = latlng.lat;
                document.getElementById('longitude').value = latlng.lng;
            });
        </script>
        <?php endif; ?>

        <h3>Sekolah Asal</h3>
        <div class="form-group">
            <label>Nama Sekolah Asal:</label>
            <input type="text" name="nama_sekolah_asal" value="<?= htmlspecialchars($nama_sekolah_asal); ?>" required>
        </div>
        <div class="form-group">
            <label>NPSN Sekolah Asal:</label>
            <input type="text" name="npsn_sekolah_asal" value="<?= htmlspecialchars($npsn_sekolah_asal); ?>" required>
        </div>
        <div class="form-group">
            <label>Alamat Sekolah Asal:</label>
            <textarea name="alamat_sekolah_asal" required><?= htmlspecialchars($alamat_sekolah_asal); ?></textarea>
        </div>

        <h3>Orang Tua / Wali</h3>
        <div class="form-group">
            <label>Nama Ayah:</label>
            <input type="text" name="nama_ayah" value="<?= htmlspecialchars($dataOrtu['nama_ayah'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label>No HP Ayah:</label>
            <input type="text" name="no_hp_ayah" value="<?= htmlspecialchars($dataOrtu['no_hp_ayah'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label>Nama Ibu:</label>
            <input type="text" name="nama_ibu" value="<?= htmlspecialchars($dataOrtu['nama_ibu'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label>No HP Ibu:</label>
            <input type="text" name="no_hp_ibu" value="<?= htmlspecialchars($dataOrtu['no_hp_ibu'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label>Nama Wali:</label>
            <input type="text" name="nama_wali" value="<?= htmlspecialchars($dataOrtu['nama_wali'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label>No HP Wali:</label>
            <input type="text" name="no_hp_wali" value="<?= htmlspecialchars($dataOrtu['no_hp_wali'] ?? ''); ?>">
        </div>

        <h3>Pilihan Sekolah</h3>

        <div class="form-group">
            <label>Pilihan 1:</label>
            <select name="sekolah[]" required>
                <option value="">Pilih</option>
                <?php foreach($sekolahList as $id=>$s): ?>
                    <option value="<?= $id; ?>" 
                        <?= ($editMode && $pilihan[0] == $id) ? 'selected' : ''; ?>>
                        <?= htmlspecialchars($s['nama_sekolah']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Pilihan 2:</label>
            <select name="sekolah[]">
                <option value="">Pilih</option>
                <?php foreach($sekolahList as $id=>$s): ?>
                    <option value="<?= $id; ?>" 
                        <?= ($editMode && $pilihan[1] == $id) ? 'selected' : ''; ?>>
                        <?= htmlspecialchars($s['nama_sekolah']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Pilihan 3:</label>
            <select name="sekolah[]">
                <option value="">Pilih</option>
                <?php foreach($sekolahList as $id=>$s): ?>
                    <option value="<?= $id; ?>" 
                        <?= ($editMode && $pilihan[2] == $id) ? 'selected' : ''; ?>>
                        <?= htmlspecialchars($s['nama_sekolah']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if ($mode === 'zonasi'): ?>
        <div class="alert-section alert-info" style="background:#f0f7ff;border-left:4px solid #007bff;margin-bottom:15px;">
        <i class="fa-solid fa-circle-info alert-icon" style="color:#007bff;"></i>
        <div class="alert-content">
            <span class="alert-title">Perhatian Jalur Zonasi</span>
            <span class="alert-text">
            Nilai rapor <b>tidak digunakan</b> dalam penilaian jalur Zonasi.
            Data ini hanya digunakan untuk <b>menampilkan rekomendasi jurusan</b>
            dan <b>mata pelajaran pilihan</b> berdasarkan nilai Anda.
            </span>
        </div>
        </div>
        <?php endif; ?>
        
        <h3>Nilai Akademik</h3>
        <table border="1" cellpadding="5" cellspacing="0">
            <thead>
                <tr>
                    <th>Mata Pelajaran</th>
                    <?php for($sem=1;$sem<=5;$sem++): ?>
                        <th>Semester <?= $sem; ?></th>
                    <?php endfor; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach($mapelList as $kode=>$m): ?>
                <tr>
                    <td><?= htmlspecialchars($m['nama_mapel']); ?></td>
                    <?php for($sem=1;$sem<=5;$sem++): ?>
                    <td>
                        <input type="number" step="0.01" name="nilai[<?= $kode; ?>][<?= $sem; ?>]" 
                            value="<?= $siswa['nilai'][$kode][$sem] ?? ''; ?>" min="0" max="100" style="width:60px;">
                    </td>
                    <?php endfor; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <style>
        table { border-collapse: collapse; margin-bottom: 15px; width:100%; }
        table th, table td { border:1px solid #ccc; text-align:center; padding:5px; }
        table th { background:#f0f0f0; }
        input[type=number] { text-align:center; }
        </style>

        <?php 
        $basePath = "../uploads/dokumen/"; 
        ?>
        <h3>Upload Dokumen</h3>

        <?php foreach($dokumenList as $d): ?>
        <div class="form-group">
            <label>
                <?= htmlspecialchars($d['nama_dokumen']); ?>
                <?php if($d['wajib']==1) echo '<span style="color:red">*</span>'; ?>
                <small style="font-weight:normal; color:#555;">
                    (Format: <?= strtoupper($d['tipe_dokumen']); ?>)
                </small>
            </label>

            <!-- INPUT FILE -->
            <input type="file"
                name="dokumen[<?= htmlspecialchars($d['nama_dokumen']); ?>]"
                accept="<?= $d['tipe_dokumen']=='pdf' ? 'application/pdf' : 'image/png'; ?>">

            <!-- PREVIEW -->
            <?php if(isset($dokumen_siswa[$d['nama_dokumen']]) && $dokumen_siswa[$d['nama_dokumen']] != ''): ?>

                <?php 
                    $fileName = $dokumen_siswa[$d['nama_dokumen']];
                    $fullPath = $basePath . $fileName;
                ?>

                <div style="margin-top:5px;">
                    <strong>File sudah diupload:</strong><br>

                    <?php if($d['tipe_dokumen'] == 'pdf'): ?>
                        <a href="<?= $fullPath; ?>" target="_blank">Lihat PDF</a>
                    <?php else: ?>
                        <a href="<?= $fullPath; ?>" target="_blank">
                            <img src="<?= $fullPath; ?>" style="max-width:120px;margin-top:5px;border:1px solid #ccc;">
                        </a>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <div><small style="color:#888;">Belum diupload</small></div>
            <?php endif; ?>

        </div>
        <?php endforeach; ?>

        <button type="submit" class="btn-submit"><i class="fa-solid fa-floppy-disk"></i> Simpan Pendaftaran</button>
    </form>
</div>


</body>
</html>