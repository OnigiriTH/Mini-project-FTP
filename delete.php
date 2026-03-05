<?php
// โหลดฟังก์ชันพื้นฐาน
require __DIR__ . '/bootstrap.php';
// ต้อง login
require_login();

// ดึง root และ viewUser
[$baseRoot, $viewUser] = get_base_root_from_request();

// ฟังก์ชันช่วย redirect กลับหน้าเดิม (รักษา path และ ?u=)
function redirect_back(string $p, string $viewUser): void
{
  $q = [];
  if (is_admin() && $viewUser !== '') $q[] = 'u=' . urlencode($viewUser);
  $q[] = 'p=' . urlencode($p);
  header('Location: /index.php?' . implode('&', $q));
  exit;
}

// ฟังก์ชันลบโฟลเดอร์แบบ recursive (ลบทั้งต้นไม้)
function rrmdir(string $dir): void
{
  // ถ้าไม่ใช่ directory ให้หยุด
  if (!is_dir($dir)) return;
  // อ่านรายการภายในโฟลเดอร์
  $items = scandir($dir);
  if ($items === false) return;

  foreach ($items as $item) {
    // ข้าม . และ ..
    if ($item === '.' || $item === '..') continue;
    $path = $dir . DIRECTORY_SEPARATOR . $item;

    // ถ้าเป็น directory (และไม่ใช่ symlink) → ลบ recursive
    if (is_dir($path) && !is_link($path)) {
      rrmdir($path);
    } else {
      // ถ้าเป็นไฟล์หรือ symlink → ลบ
      @unlink($path);
    }
  }
  // ลบตัวโฟลเดอร์เองหลังจากลบข้างในหมดแล้ว
  @rmdir($dir);
}

// ถ้าไม่ใช่ POST (เช่น เข้า URL โดยตรง) ให้ redirect กลับ
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  $p = trim((string)($_GET['p'] ?? ''));
  redirect_back($p, $viewUser);
}

// ดึง path ปัจจุบัน
$p = trim((string)($_GET['p'] ?? ''));
// ดึงชื่อสิ่งที่จะลบ
$name = trim((string)($_POST['name'] ?? ''));

// ตรวจสอบชื่อไม่ให้เป็นอันตรายหรือพยายาม traversal
if ($name === '' || $name === '.' || $name === '..' || preg_match('/[\/\\\\]/', $name)) {
  redirect_back($p, $viewUser);
}

// สร้าง relative path ที่จะลบ
$targetRel = ltrim(($p !== '' ? $p . '/' : '') . $name, '/');

try {
  // แปลงเป็น full path ที่ปลอดภัย
  $targetPath = safe_path($baseRoot, $targetRel);
} catch (Throwable $e) {
  // ถ้า path ไม่ถูกต้อง → กลับไปหน้าเดิม
  redirect_back($p, $viewUser);
}

// หา realpath ของ root และ target เพื่อเปรียบเทียบ
$realBase = realpath($baseRoot) ?: $baseRoot;
$realTarget = realpath($targetPath) ?: $targetPath;

// ห้ามลบตัว root ของผู้ใช้คนนั้น (ป้องกันการลบทั้งโฟลเดอร์ผู้ใช้)
if ($realTarget === $realBase) {
  redirect_back($p, $viewUser);
}

// ดำเนินการลบจริง
if (is_file($targetPath) || is_link($targetPath)) {
  // ถ้าเป็นไฟล์หรือ symlink → ลบธรรมดา
  @unlink($targetPath);
} elseif (is_dir($targetPath)) {
  // ถ้าเป็น directory → ใช้ฟังก์ชัน recursive
  rrmdir($targetPath);
}

// กลับไปหน้าเดิมหลังลบเสร็จ
redirect_back($p, $viewUser);
