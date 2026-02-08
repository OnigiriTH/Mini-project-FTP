<?php
session_start();
if (!isset($_SESSION['user']))
    header("Location: login.php");

$ftp_server = "127.0.0.1";
$ftp_port = 21;
$user = $_SESSION['user'];
$pass = $_SESSION['pass'];

$path = $_GET['path'] ?? ".";

$conn = ftp_ssl_connect($ftp_server, $ftp_port, 10);
ftp_login($conn, $user, $pass);
ftp_pasv($conn, true);

$files = ftp_rawlist($conn, $path);
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Mini Cloud Storage</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

    <div class="container mt-4">
        <h3>📁 IT Storage : <?php echo $user; ?></h3>

        <div class="mb-3">
            <a href="logout.php" class="btn btn-danger btn-sm">Logout</a>
        </div>

        <form action="upload.php" method="post" enctype="multipart/form-data" class="mb-3">
            <input type="hidden" name="path" value="<?php echo $path; ?>">
            <input type="file" name="file" required>
            <button class="btn btn-primary btn-sm">Upload</button>
        </form>

        <form action="mkdir.php" method="post" class="mb-3">
            <input type="hidden" name="path" value="<?php echo $path; ?>">
            <input type="text" name="folder" placeholder="ชื่อโฟลเดอร์ใหม่" required>
            <button class="btn btn-success btn-sm">+ Folder</button>
        </form>

        <table class="table table-bordered table-hover bg-white">
            <tr class="table-dark">
                <th>ชื่อ</th>
                <th>ขนาด</th>
                <th>วันที่</th>
                <th>จัดการ</th>
            </tr>

            <?php
            foreach ($files as $row) {
                $info = preg_split("/\s+/", $row, 9);
                $name = $info[8];
                if ($name == "." || $name == "..")
                    continue;

                $size = $info[4];
                $date = $info[5] . " " . $info[6] . " " . $info[7];
                $is_dir = $info[0][0] === "d";

                echo "<tr>";
                if ($is_dir) {
                    echo "<td>📁 <a href='?path=" . urlencode($path . '/' . $name) . "'>$name</a></td>";
                } else {
                    echo "<td>📄 $name</td>";
                }
                echo "<td>" . ($is_dir ? "-" : round($size / 1024, 2) . " KB") . "</td>";
                echo "<td>$date</td>";
                echo "<td>
        " . (!$is_dir ? "<a class='btn btn-sm btn-info' href='download.php?file=" . urlencode($path . '/' . $name) . "'>Download</a>" : "") . "
        <a class='btn btn-sm btn-danger' href='delete.php?file=" . urlencode($path . '/' . $name) . "'>Delete</a>
    </td>";
                echo "</tr>";
            }
            ftp_close($conn);
            ?>

        </table>
    </div>
</body>

</html>