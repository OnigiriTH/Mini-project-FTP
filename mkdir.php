<?php
// โหลดฟังก์ชันพื้นฐาน
require __DIR__ . '/bootstrap.php';
// ต้อง login
require_login();

// ดึง root path และ viewUser
[$baseRoot, $viewUser] = get_base_root_from_request();

// ดึง path ปัจจุบัน
$p = (string)($_GET['p'] ?? '');

// หา base directory ที่ปลอดภัย
try {
  $baseDir = safe_path($baseRoot, $p);
} catch (Throwable $e) {
  $baseDir = $baseRoot;
  $p = '';
}

// ถ้าไม่ใช่ POST → redirect กลับ
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header("Location: /index.php?" . (is_admin() && $viewUser !== '' ? "u=" . urlencode($viewUser) . "&" : "") . "p=" . urlencode($p));
  exit;
}

// ดึงชื่อโฟลเดอร์จากฟอร์ม
$folder = trim((string)($_POST['folder'] ?? ''));

// ตรวจสอบชื่อโฟลเดอร์ไม่ให้เป็นอันตราย
if ($folder === '' || preg_match('/[\/\\\\]/', $folder) || $folder === '.' || $folder === '..') {
  // ถ้าไม่ถูกต้อง → redirect กลับโดยไม่ทำอะไร
  header("Location: /index.php?" . (is_admin() && $viewUser !== '' ? "u=" . urlencode($viewUser) . "&" : "") . "p=" . urlencode($p));
  exit;
}
// ทำความสะอาดชื่อโฟลเดอร์ (แทนอักขระแปลกด้วย _)
$folder = preg_replace('/[^\p{L}\p{N}\s._-]+/u', '_', $folder);

// สร้าง path เต็มของโฟลเดอร์ใหม่
$full = $baseDir . DIRECTORY_SEPARATOR . $folder;

try {
  // ตรวจ path ใหม่ด้วย safe_path เพื่อความแน่นอน
  safe_path($baseRoot, ltrim(($p ? $p . '/' : '') . $folder, '/'));
  // สร้างโฟลเดอร์ (recursive = true เผื่อ parent ยังไม่มี)
  @mkdir($full, 0775, true);
} catch (Throwable $e) {
  // ถ้าทำไม่ได้ก็ไม่ต้องแจ้งอะไร แค่ redirect กลับ
}

// redirect กลับหน้าเดิม
header("Location: /index.php?" . (is_admin() && $viewUser !== '' ? "u=" . urlencode($viewUser) . "&" : "") . "p=" . urlencode($p));
exit;
