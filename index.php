<?php
// โหลดฟังก์ชันพื้นฐานทั้งหมด
require __DIR__ . '/bootstrap.php';
// บังคับต้อง login ก่อน
require_login();

// ดึง root path และชื่อผู้ใช้ที่กำลังดู (สำหรับ admin สามารถดูคนอื่นได้)
[$baseRoot, $viewUser] = get_base_root_from_request();

// ดึง path ปัจจุบันจาก query string ถ้าไม่มีให้เป็น string ว่าง
$p = (string)($_GET['p'] ?? '');
$p = trim($p);

// พยายามแปลง path ให้ปลอดภัย ถ้าไม่ถูกต้องให้ reset กลับ root
try {
  $currentDir = safe_path($baseRoot, $p);
} catch (Throwable $e) {
  $p = '';
  $currentDir = $baseRoot;
}

// หา realpath ของทั้ง root และ current เพื่อคำนวณ relative path ที่ถูกต้อง
$realBase = realpath($baseRoot) ?: $baseRoot;
$realCur  = realpath($currentDir) ?: $currentDir;

// คำนวณ relative path (ส่วนที่เหลือหลัง root) สำหรับ breadcrumb และ URL
$rel = ltrim(str_replace(str_replace('\\', '/', $realBase), '', str_replace('\\', '/', $realCur)), '/');
// แยกเป็น array สำหรับแสดง breadcrumb
$breadcrumbParts = $rel === '' ? [] : explode('/', $rel);

// เตรียม array สำหรับเก็บรายการไฟล์/โฟลเดอร์
$items = [];
// ถ้าเป็น directory จริง ให้อ่านเนื้อหา
if (is_dir($currentDir)) {
  // อ่านทุกไฟล์/โฟลเดอร์ใน directory
  foreach (scandir($currentDir) as $name) {
    // ข้าม . และ ..
    if ($name === '.' || $name === '..') continue;
    $full = $currentDir . DIRECTORY_SEPARATOR . $name;
    $isDir = is_dir($full);
    // เก็บข้อมูลแต่ละรายการ
    $items[] = [
      'name' => $name,
      'is_dir' => $isDir,
      'size' => $isDir ? '-' : (string)filesize($full),
      'mtime' => (string)date('Y-m-d H:i', filemtime($full)),
    ];
  }
  // เรียงลำดับ: โฟลเดอร์มาก่อน แล้วเรียงชื่อแบบไม่สน case
  usort($items, function ($a, $b) {
    if ($a['is_dir'] !== $b['is_dir']) return $a['is_dir'] ? -1 : 1;
    return strcasecmp($a['name'], $b['name']);
  });
}

// ฟังก์ชันช่วยต่อ path โดยตัด / ซ้ำซ้อน
function path_join(string $base, string $next): string
{
  $base = trim($base, "/");
  $next = trim($next, "/");
  if ($base === '') return $next;
  if ($next === '') return $base;
  return $base . '/' . $next;
}

// หา parent path สำหรับปุ่มย้อนกลับ
$parent = '';
if ($rel !== '') {
  $parts = explode('/', $rel);
  array_pop($parts);
  $parent = implode('/', $parts);
}

// สร้าง prefix สำหรับ query string เมื่อ admin ดูของคนอื่น
$uqs = '';
if (is_admin() && $viewUser !== '') {
  $uqs = 'u=' . urlencode($viewUser) . '&';
}

// ข้อความแสดงสถานะด้านบนสุด
$scopeText = is_admin()
  ? ($viewUser !== '' ? "กำลังดูไฟล์ของ: $viewUser" : "กำลังดูรวม (ทุก user)")
  : "พื้นที่ของคุณ: " . current_user();
?>
<!doctype html>
<html lang="th">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>IT Storage</title>
  <style>
    /* CSS สำหรับจัดหน้า index ให้ดูทันสมัยและ responsive */
    body {
      font-family: system-ui, sans-serif;
      background: #0b1220;
      color: #e7eefc;
      margin: 0
    }

    .top {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 16px 20px;
      background: #0f1a30;
      position: sticky;
      top: 0
    }

    a {
      color: #93c5fd;
      text-decoration: none
    }

    .wrap {
      max-width: 1000px;
      margin: 0 auto;
      padding: 18px 20px
    }

    .row {
      display: flex;
      gap: 12px;
      flex-wrap: wrap
    }

    .card {
      background: #111b2f;
      border: 1px solid #223153;
      border-radius: 16px;
      padding: 14px
    }

    input,
    button {
      padding: 10px;
      border-radius: 10px;
      border: 1px solid #2a3a5f;
      background: #0b1220;
      color: #e7eefc
    }

    button {
      background: #3b82f6;
      border: 0;
      font-weight: 600;
      cursor: pointer
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 12px
    }

    th,
    td {
      padding: 10px;
      border-bottom: 1px solid #223153;
      text-align: left
    }

    .badge {
      display: inline-block;
      padding: 2px 8px;
      border-radius: 999px;
      font-size: 12px;
      background: #1f2a44;
      border: 1px solid #2a3a5f
    }

    .muted {
      color: #9fb3d9
    }

    .pill {
      display: inline-block;
      padding: 2px 8px;
      border-radius: 999px;
      font-size: 12px;
      border: 1px solid #2a3a5f;
      background: #1f2a44
    }
  </style>
</head>

<body>
  <!-- ส่วน header ติดด้านบน (sticky) -->
  <div class="top">
    <div>
      <b>IT Storage</b>
      <!-- แสดงชื่อผู้ใช้ + role + สถานะการดู -->
      <span class="muted">| user: <?= h(current_user()) ?> (<?= h(current_role()) ?>)</span>
      <span class="muted">| <?= h($scopeText) ?></span>
    </div>
    <div class="row" style="align-items:center;">
      <!-- ลิงก์ logout -->
      <a class="pill" href="/logout.php">Logout</a>
    </div>
  </div>

  <!-- ส่วนเนื้อหาหลัก -->
  <div class="wrap">
    <!-- Breadcrumb + ปุ่มย้อนกลับ -->
    <div class="card">
      <div style="display:flex; justify-content:space-between; align-items:center;">
        <div>
          <!-- แสดง breadcrumb -->
          <a href="/index.php?<?= $uqs ?>">🏠</a>
          <?php foreach ($breadcrumbParts as $i => $part): ?>
            <?php $pathSoFar = implode('/', array_slice($breadcrumbParts, 0, $i + 1)); ?>
            / <a href="/index.php?<?= $uqs ?>p=<?= urlencode($pathSoFar) ?>"><?= h($part) ?></a>
          <?php endforeach; ?>
        </div>
        <!-- ปุ่มย้อนกลับถ้าไม่ใช่ root -->
        <?php if ($rel !== ''): ?>
          <a class="badge" href="/index.php?<?= $uqs ?>p=<?= urlencode($parent) ?>">⬅ กลับโฟลเดอร์ก่อนหน้า</a>
        <?php endif; ?>
      </div>
    </div>

    <!-- ฟอร์มอัปโหลดและสร้างโฟลเดอร์ -->
    <div style="height:12px;"></div>
    <div class="row">
      <!-- ฟอร์มอัปโหลดไฟล์ -->
      <div class="card" style="flex:1; min-width:300px;">
        <b>อัปโหลดไฟล์</b>
        <form method="post" action="/upload.php?<?= $uqs ?>p=<?= urlencode($rel) ?>" enctype="multipart/form-data" style="margin-top:10px;">
          <input type="file" name="file" required>
          <button type="submit">Upload</button>
        </form>
        <div class="muted" style="margin-top:8px;">ไฟล์จะถูกเก็บในโฟลเดอร์นี้</div>
      </div>

      <!-- ฟอร์มสร้างโฟลเดอร์ -->
      <div class="card" style="flex:1; min-width:300px;">
        <b>สร้างโฟลเดอร์</b>
        <form method="post" action="/mkdir.php?<?= $uqs ?>p=<?= urlencode($rel) ?>" style="margin-top:10px;">
          <input name="folder" placeholder="เช่น documents_2026" required>
          <button type="submit">Create</button>
        </form>
        <div class="muted" style="margin-top:8px;">ใช้ตัวอักษร/ตัวเลข/ขีด/ขีดล่าง</div>
      </div>
    </div>

    <!-- ตารางแสดงรายการไฟล์ -->
    <div style="height:12px;"></div>
    <div class="card">
      <b>รายการไฟล์</b>
      <table>
        <thead>
          <tr>
            <th>ชื่อ</th>
            <th>ชนิด</th>
            <th>ขนาด</th>
            <th>แก้ไขล่าสุด</th>
            <th>ดาวน์โหลด</th>
            <th>ลบ</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($items)): ?>
            <!-- ถ้าไม่มีไฟล์เลย -->
            <tr>
              <td colspan="6" class="muted">ยังไม่มีไฟล์/โฟลเดอร์</td>
            </tr>
          <?php else: ?>
            <?php foreach ($items as $it): ?>
              <tr>
                <td>
                  <!-- ถ้าเป็นโฟลเดอร์ → ลิงก์เข้าโฟลเดอร์ -->
                  <?php if ($it['is_dir']): ?>
                    <a href="/index.php?<?= $uqs ?>p=<?= urlencode(path_join($rel, $it['name'])) ?>">📁 <?= h($it['name']) ?></a>
                  <?php else: ?>
                    📄 <?= h($it['name']) ?>
                  <?php endif; ?>
                </td>
                <td><?= $it['is_dir'] ? 'Folder' : 'File' ?></td>
                <td class="muted"><?= h($it['size']) ?></td>
                <td class="muted"><?= h($it['mtime']) ?></td>
                <td>
                  <!-- ถ้าเป็นไฟล์ → มีลิงก์ดาวน์โหลด -->
                  <?php if (!$it['is_dir']): ?>
                    <a href="/download.php?<?= $uqs ?>p=<?= urlencode($rel) ?>&f=<?= urlencode($it['name']) ?>">Download</a>
                  <?php else: ?>
                    <span class="muted">-</span>
                  <?php endif; ?>
                </td>
                <!-- ปุ่มลบ (เป็น form POST เพื่อความปลอดภัย) -->
                <td>
                  <form method="post"
                    action="/delete.php?<?= $uqs ?>p=<?= urlencode($rel) ?>"
                    onsubmit="return confirm('ลบ <?= h($it['is_dir'] ? "โฟลเดอร์" : "ไฟล์") ?>: <?= h($it['name']) ?> ?');"
                    style="display:inline;">
                    <input type="hidden" name="name" value="<?= h($it['name']) ?>">
                    <button type="submit"
                      style="padding:8px 10px; border-radius:10px; border:0; background:#ef4444; color:#fff; cursor:pointer;">
                      Delete
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>

</html>