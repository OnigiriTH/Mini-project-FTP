<?php
session_start();

$ftp_server = "127.0.0.1";
$ftp_port = 21;

$user = $_POST['username'];
$pass = $_POST['password'];

$conn = ftp_ssl_connect($ftp_server, $ftp_port, 10);

if (!$conn) {
    die("เชื่อมต่อ FTP Server ไม่ได้");
}

if (!ftp_login($conn, $user, $pass)) {
    die("Username หรือ Password ไม่ถูกต้อง");
}

ftp_pasv($conn, true);

$_SESSION['user'] = $user;
$_SESSION['pass'] = $pass;

ftp_close($conn);

header("Location: dashboard.php");
?>