<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "ppdb_elmx";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Koneksi MySQL gagal: " . mysqli_connect_error());
}
