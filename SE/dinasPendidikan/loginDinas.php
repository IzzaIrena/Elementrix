<?php
session_start();
include("../koneksi_mysql.php"); // koneksi MySQL Anda

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email    = trim($_POST['email']);
    $password = trim($_POST['password']);

    // ================================
    // 1. CARI USER BERDASARKAN EMAIL
    // ================================
    $stmt = $conn->prepare("SELECT id, nama, email, password, role FROM user WHERE email = ? AND role = 'dinas' LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $resultUser = $stmt->get_result();

    if ($resultUser->num_rows === 0) {
        $error = "Email tidak ditemukan atau bukan akun dinas!";
    } else {
        $user = $resultUser->fetch_assoc();

        // ================================
        // 2. CEK PASSWORD
        // ================================
        if (!password_verify($password, $user['password'])) {
            $error = "Password salah!";
        } else {

            // ================================
            // 3. AMBIL PROFIL DINAS
            // ================================
            $stmt2 = $conn->prepare("SELECT * FROM dinas WHERE user_id = ? LIMIT 1");
            $stmt2->bind_param("i", $user['id']);
            $stmt2->execute();
            $resultDinas = $stmt2->get_result();

            if ($resultDinas->num_rows === 0) {
                $error = "Data profil dinas tidak ditemukan dalam tabel dinas!";
            } else {
                $dinas = $resultDinas->fetch_assoc();

                // ================================
                // 4. SET SESSION
                // ================================
                $_SESSION['user_id']     = $user['id'];
                $_SESSION['role']        = 'dinas';
                $_SESSION['nama_dinas']  = $dinas['nama_dinas'];
                $_SESSION['dinas_id']    = $dinas['id'];  // <--- FK dari tabel dinas

                // ================================
                // 5. REDIRECT
                // ================================
                header("Location: dashboardDinas.php");
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Login Dinas</title>
  <link rel="stylesheet" href="../css/register_login.css">
</head>
<body>
  <div class="container">
    <div class="right">
      <div class="login-box">
        <h2>Login Dinas Pendidikan</h2>
        
        <?php if (!empty($error)) : ?>
          <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <?php if (isset($_GET['success'])) : ?>
          <p style="color: green;"><?php echo htmlspecialchars($_GET['success']); ?></p>
        <?php endif; ?>

        <form method="POST">
          <input type="email" name="email" placeholder="Email Dinas" required>
          <input type="password" name="password" placeholder="Password" required>
          <button type="submit">Login</button>
        </form>

        <!-- <div class="register-link">
          Belum punya akun? <a href="registerDinas.php">Daftar</a>
        </div> -->
      </div>
    </div>
  </div>
</body>
</html>
