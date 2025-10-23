<?php
session_start();
include "../koneksi.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email    = trim($_POST['email']);
    $password = trim($_POST['password']);

    // Ambil data dinas + password user
    $sql = "SELECT d.*, u.password 
            FROM dinas d
            JOIN user u ON d.user_id = u.id
            WHERE d.email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();

        // Verifikasi password dari tabel user
        if (password_verify($password, $row['password'])) {
            $_SESSION['dinas_id'] = $row['id'];
            $_SESSION['nama_dinas'] = $row['nama_dinas'];

            header("Location: dashboardDinas.php");
            exit;
        } else {
            $error = "Password salah!";
        }
    } else {
        $error = "Email tidak ditemukan!";
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
        
        <?php if (isset($error)) : ?>
          <p style="color: red;"><?php echo $error; ?></p>
        <?php endif; ?>

        <form method="POST">
          <input type="email" name="email" placeholder="Email Dinas" required>
          <input type="password" name="password" placeholder="Password" required>
          <button type="submit">Login</button>
        </form>

      </div>
    </div>
  </div>
</body>
</html>
