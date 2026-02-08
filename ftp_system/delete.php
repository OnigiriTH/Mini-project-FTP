<?php
session_start();
$ftp_server = "127.0.0.1";
$ftp_port = 21;

$file = $_GET['file'];

$conn = ftp_ssl_connect($ftp_server, $ftp_port, 10);
ftp_login($conn, $_SESSION['user'], $_SESSION['pass']);
ftp_pasv($conn, true);

if (!ftp_delete($conn, $file)) {
    ftp_rmdir($conn, $file);
}

header("Location: dashboard.php");
?>