<?php
include("../koneksi.php");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nisn            = trim($_POST['nisn']);
    $nama            = trim($_POST['nama']);
    $email           = trim($_POST['email']);
    $password        = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];

    // Validasi password & confirm password
    if ($password !== $confirmPassword) {
        header("Location: registerSiswa.php?error=Password dan konfirmasi password tidak sama");
        exit;
    }

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    // Simpan ke tabel user (username = nama, role = siswa)
    $sql_user = "INSERT INTO user (username, password, role) VALUES (?, ?, 'siswa')";
    $stmt_user = $conn->prepare($sql_user);
    $stmt_user->bind_param("ss", $nama, $hashedPassword);

    if ($stmt_user->execute()) {
        $user_id = $stmt_user->insert_id;

        // Simpan ke tabel siswa (no_hp dikosongkan dulu)
        $sql_siswa = "INSERT INTO siswa (user_id, nisn, nama_lengkap, email, no_hp) VALUES (?, ?, ?, ?, '')";
        $stmt_siswa = $conn->prepare($sql_siswa);
        $stmt_siswa->bind_param("isss", $user_id, $nisn, $nama, $email);
        $stmt_siswa->execute();

        header("Location: loginSiswa.php?success=Registrasi berhasil, silakan login");
        exit;
    } else {
        header("Location: registerSiswa.php?error=NISN sudah terdaftar atau ada kesalahan");
        exit;
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

        <?php if (isset($_GET['error'])): ?>
          <p style="color: red;"><?php echo htmlspecialchars($_GET['error']); ?></p>
        <?php endif; ?>

        <form method="POST">
          <input type="text" name="nama" placeholder="Nama Lengkap" required>
          <input type="text" name="nisn" placeholder="NISN" required>
          <input type="email" name="email" placeholder="Email" required>
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
