<?php
session_start();
include "../koneksi.php";

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']);
    $password = trim($_POST['password']);

    if ($email && $password) {
        // Cari sekolah berdasarkan email
        $sql = "SELECT u.id as user_id, u.username, u.password, s.id as sekolah_id, s.nama_sekolah
                FROM user u
                JOIN sekolah s ON u.id = s.user_id
                WHERE s.email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
            if (password_verify($password, $row['password'])) {
                // Login sukses
                $_SESSION['user_id'] = $row['user_id'];
                $_SESSION['sekolah_id'] = $row['sekolah_id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['nama_sekolah'] = $row['nama_sekolah'];
                $_SESSION['role'] = 'sekolah';

                header("Location: dashboardSekolah.php");
                exit;
            } else {
                $error = "Password salah!";
            }
        } else {
            $error = "Email tidak ditemukan!";
        }
        $stmt->close();
    } else {
        $error = "Mohon isi semua field!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Login Sekolah</title>
  <link rel="stylesheet" href="../css/register_login.css">
</head>
<body>
  <div class="container">
    <div class="left">
      <img src="../images/logo-ppdb.png" alt="Logo PPDB" class="logo">
      <p>Selamat datang kembali di PPDB Online 2025!</p>
      <img src="../images/sekolah.png" alt="Ilustrasi Sekolah" class="students">
    </div>

    <div class="right">
      <div class="login-box">
        <img src="../images/logo-kemendikbud.png" alt="Logo Kemendikbud">
        <h2>Login Sekolah</h2>

        <?php if ($error): ?>
          <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <form method="POST">
          <input type="email" name="email" placeholder="Email Sekolah" required>
          <input type="password" name="password" placeholder="Password" required>
          <button type="submit">Masuk</button>
        </form>
      </div>
    </div>
  </div>
</body>
</html>
