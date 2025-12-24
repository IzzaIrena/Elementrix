<?php
session_start();

// Ambil role sebelum session dihancurkan
$role = $_SESSION['role'] ?? null;

// Hapus semua data session
session_unset();
session_destroy();

// Arahkan sesuai role
switch ($role) {
    case 'siswa':
        header("Location: siswa/loginSiswa.php");
        break;
    case 'sekolah':
        header("Location: sekolah/loginSekolah.php");
        break;
    case 'dinas':
        header("Location: dinasPendidikan/loginDinas.php");
        break;
    default:
        // Jika tidak diketahui, arahkan ke halaman utama
        header("Location: index.php");
        break;
}
exit;
?>
