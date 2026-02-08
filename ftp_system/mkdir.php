<?php
session_start();
$ftp_server = "127.0.0.1";
$ftp_port = 21;

$conn = ftp_ssl_connect($ftp_server, $ftp_port, 10);
ftp_login($conn, $_SESSION['user'], $_SESSION['pass']);
ftp_pasv($conn, true);

$path = $_POST['path'];
$folder = $_POST['folder'];

ftp_mkdir($conn, $path . '/' . $folder);

header("Location: dashboard.php?path=" . urlencode($path));
?>