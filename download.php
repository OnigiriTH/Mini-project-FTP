<?php
// โหลดฟังก์ชันพื้นฐาน
require __DIR__ . '/bootstrap.php';
// ต้อง login ก่อน
require_login();

// ดึง root path และ viewUser
[$baseRoot, $viewUser] = get_base_root_from_request();

// ดึง path ปัจจุบัน
$p = (string)($_GET['p'] ?? '');
// ดึงชื่อไฟล์ที่ต้องการดาวน์โหลด (ใช้ basename เพื่อความปลอดภัย)
$f = (string)($_GET['f'] ?? '');
$f = basename($f);

// พยายามสร้าง full path ที่ปลอดภัย
try {
  $path = safe_path($baseRoot, ltrim(($p ? $p . '/' : '') . $f, '/'));
} catch (Throwable $e) {
  // ถ้า path ไม่ถูกต้อง → 400 Bad Request
  http_response_code(400);
  exit("Invalid path");
}

// ตรวจว่าเป็นไฟล์จริง ๆ หรือไม่
if (!is_file($path)) {
  // ถ้าไม่พบ → 404 Not Found
  http_response_code(404);
  exit("Not found");
}

// ส่ง header สำหรับให้ browser ดาวน์โหลดไฟล์
header('Content-Type: application/octet-stream');
// ตั้งชื่อไฟล์ในการดาวน์โหลด (ใช้ rawurlencode เพื่อรองรับภาษาไทย/อักขระพิเศษ)
header('Content-Disposition: attachment; filename="' . rawurlencode($f) . '"');
// บอกขนาดไฟล์
header('Content-Length: ' . filesize($path));
// ส่งเนื้อหาไฟล์ทั้งหมดไปยัง output
readfile($path);
// จบการทำงานทันที
exit;
