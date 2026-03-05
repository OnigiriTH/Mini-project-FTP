<?php
// โหลดฟังก์ชันพื้นฐาน
require __DIR__ . '/bootstrap.php';
// ต้อง login ก่อน
require_login();

// ดึง root path และ viewUser (สำหรับ admin)
[$baseRoot, $viewUser] = get_base_root_from_request();

// ดึง path ปัจจุบันจาก query string
$p = (string)($_GET['p'] ?? '');

// พยายามหา destination directory ที่ปลอดภัย
try {
  $destDir = safe_path($baseRoot, $p);
} catch (Throwable $e) {
  // ถ้า path ไม่ถูกต้อง ให้ใช้ root และ reset p
  $destDir = $baseRoot;
  $p = '';
}

// ถ้าไม่ใช่ POST (เช่น เข้า URL โดยตรง) ให้ redirect กลับ index
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header("Location: /index.php?" . (is_admin() && $viewUser !== '' ? "u=" . urlencode($viewUser) . "&" : "") . "p=" . urlencode($p));
  exit;
}

// ตรวจสอบว่ามีไฟล์อัปโหลดและไม่มี error
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
  // ถ้าไม่มีไฟล์หรือ error ให้ redirect กลับ
  header("Location: /index.php?" . (is_admin() && $viewUser !== '' ? "u=" . urlencode($viewUser) . "&" : "") . "p=" . urlencode($p));
  exit;
}

// ดึง path ชั่วคราวของไฟล์ที่อัปโหลดมา
$tmp = $_FILES['file']['tmp_name'];
// ดึงชื่อไฟล์เดิม แล้วตัดเฉพาะ basename (ป้องกัน path traversal จากชื่อไฟล์)
$name = basename((string)$_FILES['file']['name']);
// ทำความสะอาดชื่อไฟล์ โดยแทนอักขระที่ไม่ต้องการด้วย _
$name = preg_replace('/[^\p{L}\p{N}\s._-]+/u', '_', $name);

// ถ้า destination directory ยังไม่มี ให้สร้าง (recursive)
if (!is_dir($destDir)) @mkdir($destDir, 0775, true);

// สร้าง path เต็มที่จะบันทึกไฟล์
$target = $destDir . DIRECTORY_SEPARATOR . $name;

// ถ้าชื่อไฟล์ซ้ำ ให้เพิ่มเลขต่อท้าย (file (1).txt, file (2).txt, ...)
if (file_exists($target)) {
  $pi = pathinfo($name);
  $base = $pi['filename'] ?? 'file';
  $ext  = isset($pi['extension']) ? ('.' . $pi['extension']) : '';
  $i = 1;
  do {
    $newName = $base . " ($i)" . $ext;
    $target = $destDir . DIRECTORY_SEPARATOR . $newName;
    $i++;
  } while (file_exists($target));
}

// ย้ายไฟล์จาก temporary ไปยังตำแหน่งจริง
move_uploaded_file($tmp, $target);

// redirect กลับหน้าเดิม (รักษา path และ ?u= ถ้ามี)
header("Location: /index.php?" . (is_admin() && $viewUser !== '' ? "u=" . urlencode($viewUser) . "&" : "") . "p=" . urlencode($p));
exit;
