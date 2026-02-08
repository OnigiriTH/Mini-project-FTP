<?php
session_start();
if (!isset($_SESSION['user']))
    exit;

$ftp_server = "127.0.0.1";
$ftp_port = 21;

$user = $_SESSION['user'];
$pass = $_SESSION['pass'];

$file = $_GET['file'];

$conn = ftp_ssl_connect($ftp_server, $ftp_port, 10);
ftp_login($conn, $user, $pass);
ftp_pasv($conn, true);

header("Content-Type: application/octet-stream");
header("Content-Disposition: attachment; filename=\"$file\"");

ftp_get($conn, "php://output", $file, FTP_BINARY);
ftp_close($conn);
?>