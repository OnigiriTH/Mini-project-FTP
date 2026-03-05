<?php
// โหลดไฟล์ bootstrap เพื่อใช้งานฟังก์ชันพื้นฐานทั้งหมด (session, db, require_login ฯลฯ)
require __DIR__ . '/bootstrap.php';

// ถ้าผู้ใช้ล็อกอินอยู่แล้ว ให้ redirect ไปหน้าแรกทันที (ไม่ต้องแสดงหน้า login ซ้ำ)
if (is_logged_in()) {
  header("Location: /index.php");
  exit;
}

// ตัวแปรสำหรับเก็บข้อความ error (เริ่มต้นเป็นค่าว่าง)
$error = '';

// ตรวจสอบว่ามีการส่งฟอร์ม POST เข้ามาหรือไม่ (คือมีการพยายาม login)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // ดึง username จากฟอร์ม แล้วตัดช่องว่างข้างหน้า-ข้างหลัง
  $u = trim($_POST['username'] ?? '');
  // ดึง password จากฟอร์ม (เป็น string เต็มรูปแบบ ไม่ trim เพราะรหัสผ่านอาจมีช่องว่างได้)
  $p = (string)($_POST['password'] ?? '');

  // เตรียม query เพื่อค้นหาผู้ใช้จาก username
  $stmt = db()->prepare("SELECT username, password_hash, role FROM users WHERE username = :u");
  // รัน query โดยส่ง username เข้าไป
  $stmt->execute([':u' => $u]);
  // ดึงข้อมูลผู้ใช้ 1 แถว (ถ้ามี)
  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  // ตรวจสอบว่าพบผู้ใช้ และรหัสผ่านตรงกันหรือไม่ (ใช้ password_verify เพื่อความปลอดภัย)
  if ($row && password_verify($p, $row['password_hash'])) {
    // เก็บชื่อผู้ใช้ลง session
    $_SESSION['user'] = $row['username'];
    // เก็บ role ลง session (ถ้าไม่มีให้ default เป็น 'user')
    $_SESSION['role'] = $row['role'] ?? 'user';

    // พยายามสร้างโฟลเดอร์ของผู้ใช้ (เผื่อเพิ่งสร้างผู้ใช้ใหม่)
    try {
      ensure_user_dir($_SESSION['user']);
    } catch (Throwable $e) {
    }
    // ไม่ต้องจัดการ error เพราะถ้าสร้างไม่ได้ก็ไม่กระทบการ login

    // redirect ไปหน้าแรกหลัง login สำเร็จ
    header("Location: /index.php");
    exit;
  } else {
    // ถ้า login ไม่สำเร็จ ให้ตั้งข้อความ error
    $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
  }
}
?>
<!doctype html>
<!-- กำหนดภาษาเป็นไทยเพื่อรองรับการแสดงผลและ SEO -->
<html lang="th">

<head>
  <!-- ตั้ง charset เป็น UTF-8 เพื่อรองรับภาษาไทย -->
  <meta charset="utf-8">
  <!-- ทำให้ responsive บนมือถือ -->
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>IT Storage - Login</title>
  <style>
    /* CSS ทั้งหมดสำหรับจัดหน้า login ให้ดูดี */
    *,
    *::before,
    *::after {
      box-sizing: border-box;
    }

    input,
    button {
      width: 100%;
      box-sizing: border-box;
      display: block;
    }

    body {
      font-family: system-ui, sans-serif;
      background: #0b1220;
      color: #e7eefc;
      margin: 0
    }

    .card {
      max-width: 420px;
      margin: 10vh auto;
      background: #111b2f;
      padding: 24px;
      border-radius: 16px
    }

    input {
      width: 100%;
      padding: 12px;
      border-radius: 10px;
      border: 1px solid #2a3a5f;
      background: #0b1220;
      color: #e7eefc
    }

    button {
      width: 100%;
      padding: 12px;
      border-radius: 10px;
      border: 0;
      background: #3b82f6;
      color: #fff;
      font-weight: 600;
      cursor: pointer
    }

    .muted {
      color: #9fb3d9;
      font-size: 14px
    }

    .err {
      background: #3a1b1b;
      border: 1px solid #7f1d1d;
      padding: 10px;
      border-radius: 10px;
      margin: 12px 0
    }

    .img {
      margin: 0 0 14px
    }

    .img img {
      width: 100%;
      height: 250px;
      object-fit: cover;
      border-radius: 14px;
      display: block;
      border: 1px solid #223153;
    }
  </style>
</head>

<body>
  <!-- กล่องหลักของหน้า login -->
  <div class="card">
    <!-- ส่วนรูปภาพต้อนรับ -->
    <div class="img">
      <img
        src="https://images.meme-arsenal.com/d8d3c16b10d2c34b898cddfc00b5c149.jpg"
        alt="Welcome"
        loading="lazy"
        referrerpolicy="no-referrer">
    </div>

    <!-- หัวข้อระบบ -->
    <h2 style="margin:0 0 8px;">IT Storage</h2>
    <!-- คำอธิบายสั้น -->
    <div class="muted">เข้าสู่ระบบเพื่อจัดการไฟล์</div>

    <!-- แสดง error ถ้ามี -->
    <?php if ($error): ?>
      <div class="err"><?= h($error) ?></div>
    <?php endif; ?>

    <!-- ฟอร์ม login -->
    <form method="post" style="margin-top:16px;">
      <label class="muted">Username</label>
      <!-- ช่องใส่ username -->
      <input name="username" autocomplete="username" required>

      <div style="height:10px;"></div>

      <label class="muted">Password</label>
      <!-- ช่องใส่ password (type=password เพื่อซ่อนตัวอักษร) -->
      <input name="password" type="password" autocomplete="current-password" required>

      <div style="height:14px;"></div>

      <!-- ปุ่ม submit -->
      <button type="submit">Login</button>
    </form>

    <!-- ช่องว่างด้านล่าง (อาจใส่ลิงก์ลืมรหัสผ่านในอนาคต) -->
    <div class="muted" style="margin-top:12px;">

    </div>
  </div>
</body>

</html>