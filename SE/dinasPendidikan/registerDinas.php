<?php
include "../koneksi.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_dinas = trim($_POST['nama_dinas']);
    $alamat     = trim($_POST['alamat']);
    $kontak     = trim($_POST['kontak']);
    $email      = trim($_POST['email']);
    $password   = password_hash($_POST['password'], PASSWORD_BCRYPT);

    // Cek apakah username sudah ada (username = nama dinas)
    $check = $conn->prepare("SELECT * FROM user WHERE username=? AND role='dinas'");
    $check->bind_param("s", $nama_dinas);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        $error = "Nama dinas sudah terdaftar!";
    } else {
        // Simpan ke tabel user
        $sql_user = "INSERT INTO user (username, password, role, created_at) 
                     VALUES (?, ?, 'dinas', NOW())";
        $stmt_user = $conn->prepare($sql_user);
        $stmt_user->bind_param("ss", $nama_dinas, $password);

        if ($stmt_user->execute()) {
            $user_id = $stmt_user->insert_id;

            // Simpan ke tabel dinas
            $sql_dinas = "INSERT INTO dinas (user_id, nama_dinas, alamat, kontak, email) 
                          VALUES (?, ?, ?, ?, ?)";
            $stmt_dinas = $conn->prepare($sql_dinas);
            $stmt_dinas->bind_param("issss", $user_id, $nama_dinas, $alamat, $kontak, $email);
            $stmt_dinas->execute();

            header("Location: loginDinas.php?success=Registrasi berhasil, silakan login");
            exit;
        } else {
            $error = "Gagal menyimpan data!";
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
