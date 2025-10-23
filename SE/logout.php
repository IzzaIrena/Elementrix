<?php
session_start();
session_unset();
session_destroy();
header("Location: loginSiswa.php");
exit;
?>
