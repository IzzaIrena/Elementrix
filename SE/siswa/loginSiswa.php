<?php
session_start();
include("../koneksi.php");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nisn     = trim($_POST['nisn']);
    $password = trim($_POST['password']);

    // Cari siswa berdasarkan NISN
    $sql = "SELECT u.id, u.username, u.password, u.role, s.nisn 
            FROM user u
            JOIN siswa s ON u.id = s.user_id
            WHERE s.nisn = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $nisn);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();

        // Verifikasi password
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['nisn'] = $row['nisn'];

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
          <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <?php if (isset($_GET['success'])): ?>
          <p style="color: green;"><?php echo htmlspecialchars($_GET['success']); ?></p>
        <?php endif; ?>

        <form method="POST">
          <input type="text" name="nisn" placeholder="NISN" required>
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
