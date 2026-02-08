<?php
session_start();

$ftp_server = "127.0.0.1";
$ftp_port = 21;

$path = $_POST['path'];

$conn = ftp_ssl_connect($ftp_server, $ftp_port, 10);
ftp_login($conn, $_SESSION['user'], $_SESSION['pass']);
ftp_pasv($conn, true);

$tmp = $_FILES['file']['tmp_name'];
$name = $_FILES['file']['name'];

ftp_put($conn, $path . '/' . $name, $tmp, FTP_BINARY);

header("Location: dashboard.php?path=" . urlencode($path));
?>