<?php
session_start();
include("../koneksi_mysql.php"); // koneksi MySQL ($conn)

$success = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nisn            = str_pad(trim($_POST['nisn']), 10, "0", STR_PAD_LEFT);
    $nama            = trim($_POST['nama']);
    $email           = trim($_POST['email']);
    $password        = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];

    if ($password !== $confirmPassword) {
        $error = "Password dan konfirmasi tidak sama";
    } else {
        // Cek NISN unik di siswa
        $cekNisn = mysqli_query($conn, "SELECT id FROM siswa WHERE nisn='$nisn'");
        if (mysqli_num_rows($cekNisn) > 0) {
            $error = "NISN sudah terdaftar";
        }

        // Cek email unik di user
        $cekEmail = mysqli_query($conn, "SELECT id FROM user WHERE email='$email'");
        if (mysqli_num_rows($cekEmail) > 0) {
            $error = "Email sudah terdaftar";
        }
    }

    if (empty($error)) {
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        mysqli_begin_transaction($conn);

        try {
            // Insert ke tabel user
            $sqlUser = "INSERT INTO user (nama, email, password, role, created_at)
                        VALUES ('$nama', '$email', '$hashedPassword', 'siswa', NOW())";
            mysqli_query($conn, $sqlUser);
            $userId = mysqli_insert_id($conn);

            // Insert ke tabel siswa
            $sqlSiswa = "INSERT INTO siswa (user_id, nama_lengkap, nisn, created_at)
                         VALUES ($userId, '$nama', '$nisn', NOW())";
            mysqli_query($conn, $sqlSiswa);

            mysqli_commit($conn);

            // Buat session langsung setelah registrasi
            $_SESSION['user_id']  = $userId;
            $_SESSION['username'] = $nama;
            $_SESSION['role']     = 'siswa';
            $_SESSION['nisn']     = $nisn;

            // Redirect ke dashboard
            header("Location: dashboardSiswa.php");
            exit;

        } catch (\Throwable $e) {
            mysqli_rollback($conn);
            $error = "Terjadi kesalahan: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Register Siswa</title>
<link rel="stylesheet" href="../css/register_login.css">
</head>
<body>
<div class="container">
  <div class="left">
    <img src="../images/logo-ppdb.png" alt="Logo PPDB" class="logo">
    <p>Daftar akun siswa untuk PPDB Online 2025!</p>
    <img src="../images/siswa.png" alt="Ilustrasi Siswa" class="students">
  </div>

  <div class="right">
    <div class="login-box">
      <img src="../images/logo-kemendikbud.png" alt="Logo Kemendikbud">
      <h2>Register Siswa</h2>

      <?php if ($error): ?>
        <div class="alert error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST">
        <input type="text" name="nama" placeholder="Nama Lengkap" required value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>">
        <input type="text" name="nisn" placeholder="NISN (10 digit)" required value="<?= htmlspecialchars($_POST['nisn'] ?? '') ?>">
        <input type="email" name="email" placeholder="Email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        <input type="password" name="password" placeholder="Password" required>
        <input type="password" name="confirm_password" placeholder="Konfirmasi Password" required>
        <button type="submit">Daftar</button>
      </form>

      <div class="register-link">
        Sudah punya akun? <a href="loginSiswa.php">Login</a>
      </div>
    </div>
  </div>
</div>
</body>
</html>
