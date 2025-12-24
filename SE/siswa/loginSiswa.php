<?php
session_start();
include("../koneksi_mysql.php"); // koneksi MySQL ($conn)

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nisn     = str_pad(trim($_POST['nisn']), 10, "0", STR_PAD_LEFT);
    $password = trim($_POST['password']);

    // Ambil data siswa beserta user
    $sql = "SELECT siswa.user_id, siswa.nama_lengkap, user.password, user.role, user.email
            FROM siswa
            JOIN user ON siswa.user_id = user.id
            WHERE siswa.nisn = '$nisn'";

    $result = mysqli_query($conn, $sql);

    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);

        if (password_verify($password, $row['password'])) {
            // Login berhasil
            $_SESSION['user_id']  = $row['user_id'];
            $_SESSION['username'] = $row['nama_lengkap'];
            $_SESSION['role']     = $row['role'];
            $_SESSION['nisn']     = $nisn;
            $_SESSION['email']    = $row['email'];

            header("Location: dashboardSiswa.php");
            exit;
        } else {
            $error = "Password salah!";
        }
    } else {
        $error = "NISN tidak ditemukan!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Login Siswa</title>
<link rel="stylesheet" href="../css/register_login.css">
</head>
<body>
<div class="container">
  <div class="left">
    <img src="../images/logo-ppdb.png" alt="Logo PPDB" class="logo">
    <p>Selamat datang kembali di PPDB Online 2025!</p>
    <img src="../images/siswa.png" alt="Ilustrasi Siswa" class="students">
  </div>

  <div class="right">
    <div class="login-box">
      <img src="../images/logo-kemendikbud.png" alt="Logo Kemendikbud">
      <h2>Login Siswa</h2>

      <?php if (!empty($error)): ?>
        <p style="color:red;"><?= htmlspecialchars($error) ?></p>
      <?php endif; ?>

      <?php if (isset($_GET['success'])): ?>
        <p style="color:green;"><?= htmlspecialchars($_GET['success']) ?></p>
      <?php endif; ?>

      <form method="POST">
        <input type="text" name="nisn" placeholder="NISN (10 digit)" required value="<?= htmlspecialchars($_POST['nisn'] ?? '') ?>">
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Masuk</button>
      </form>

      <div class="register-link">
        Belum punya akun? <a href="registerSiswa.php">Daftar</a>
      </div>
    </div>
  </div>
</div>
</body>
</html>
