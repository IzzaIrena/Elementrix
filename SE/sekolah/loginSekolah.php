<?php
session_start();
include("../koneksi_mysql.php"); // koneksi MySQL ($conn)

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']);
    $password = trim($_POST['password']);

    if ($email && $password) {
        // 1️⃣ Ambil data sekolah berdasarkan email
        $stmt = $conn->prepare("SELECT s.*, u.id AS user_id, u.nama, u.password, u.role 
                                FROM sekolah s 
                                LEFT JOIN user u ON s.user_id = u.id 
                                WHERE s.email = ? 
                                LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $sekolah = $result->fetch_assoc();

        if ($sekolah) {
            // 2️⃣ Pastikan sekolah punya user_id
            if (!$sekolah['user_id']) {
                $error = "Akun sekolah belum terhubung ke user.";
            } else {
                // 3️⃣ Verifikasi password
                if (password_verify($password, $sekolah['password'])) {
                    // ✅ Login berhasil
                    $_SESSION['user_id'] = $sekolah['user_id'];
                    $_SESSION['sekolah_id'] = $sekolah['id'];
                    $_SESSION['username'] = $sekolah['username'];
                    $_SESSION['nama_sekolah'] = $sekolah['nama_sekolah'];
                    $_SESSION['role'] = $sekolah['role'];

                    header("Location: dashboardSekolah.php");
                    exit;
                } else {
                    $error = "Password salah!";
                }
            }
        } else {
            $error = "Email sekolah tidak ditemukan!";
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

        <?php if (isset($_GET['success'])): ?>
          <p style="color: green;"><?php echo htmlspecialchars($_GET['success']); ?></p>
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
