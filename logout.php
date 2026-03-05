<?php
// โหลดฟังก์ชันพื้นฐาน (จำเป็นสำหรับ session)
require __DIR__ . '/bootstrap.php';

// ล้าง session ทั้งหมด (ทำลายข้อมูลการ login)
session_destroy();

// redirect กลับไปหน้า login
header("Location: /login.php");
// จบการทำงานทันที
exit;
