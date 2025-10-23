<?php
$host = "localhost";
$user = "root";       // sesuaikan dengan server MySQL kamu
$pass = "";           // isi kalau ada password MySQL
$db   = "ppdbElementrix";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}
?>
