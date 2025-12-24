<?php
session_start();
include("../koneksi_mysql.php"); // hanya MySQL

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_dinas = trim($_POST['nama_dinas']);
    $alamat     = trim($_POST['alamat']);
    $kontak     = trim($_POST['kontak']);
    $email      = trim($_POST['email']);
    $password   = $_POST['password'];

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // 1. Cek apakah email sudah terdaftar
    $check = mysqli_query($conn, "SELECT * FROM user WHERE email='$email'");
    if (mysqli_num_rows($check) > 0) {
        $error = "Email sudah terdaftar!";
    } else {

        // 2. Masukkan ke tabel user (nama + email)
        $insertUser = mysqli_query($conn, "
            INSERT INTO user (nama, email, password, role, created_at)
            VALUES ('$nama_dinas', '$email', '$hashedPassword', 'dinas', NOW())
        ");

        if ($insertUser) {
            $user_id = mysqli_insert_id($conn);

            // 3. Simpan profil lengkap dinas
            $insertDinas = mysqli_query($conn, "
                INSERT INTO dinas (user_id, nama_dinas, alamat, kontak, email, created_at)
                VALUES ('$user_id', '$nama_dinas', '$alamat', '$kontak', '$email', NOW())
            ");

            if ($insertDinas) {
                header("Location: loginDinas.php?success=Registrasi berhasil, silakan login");
                exit;
            } else {
                $error = "Gagal menyimpan data dinas: " . mysqli_error($conn);
            }
        } else {
            $error = "Gagal membuat akun user: " . mysqli_error($conn);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Registrasi Dinas</title>
  <link rel="stylesheet" href="../css/register_login.css">
</head>
<body>
<div class="container">
    <div class="left">
        <h1>PPDB Online</h1>
        <p>Registrasi akun Dinas Pendidikan sebagai pengelola PPDB.</p>
    </div>
    <div class="right">
        <div class="login-box">
            <h2>Register Dinas</h2>

            <?php if (!empty($error)): ?>
              <p style="color:red;"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>

            <form method="POST">
              <input type="text" name="nama_dinas" placeholder="Nama Dinas" required>
              <input type="text" name="alamat" placeholder="Alamat" required>
              <input type="text" name="kontak" placeholder="Kontak" required>
              <input type="email" name="email" placeholder="Email Dinas" required>
              <input type="password" name="password" placeholder="Password" required>
              <button type="submit">Daftar</button>
            </form>

            <div class="register-link">
              Sudah punya akun? <a href="loginDinas.php">Login</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>

