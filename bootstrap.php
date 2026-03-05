<?php
// บังคับให้ PHP ใช้การตรวจสอบ type แบบเข้มงวด (ตั้งแต่ PHP 7+)
declare(strict_types=1);

session_start();

// ฟังก์ชันช่วย escape string สำหรับแสดงผลใน HTML เพื่อป้องกัน XSS
// ใช้ ENT_QUOTES เพื่อ escape ทั้ง ' และ " และ ENT_SUBSTITUTE เพื่อจัดการตัวอักษรที่ไม่รู้จัก
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ฟังก์ชันเชื่อมต่อฐานข้อมูล SQLite แบบ singleton (เรียกครั้งเดียวก็ใช้ซ้ำได้)
function db(): PDO {
    // ตัวแปร static เพื่อเก็บ instance PDO ไว้ ไม่ต้องสร้างใหม่ทุกครั้ง
    static $pdo = null;
    // ถ้ามี instance แล้ว ให้คืนค่ากลับเลย
    if ($pdo) return $pdo;

    // ดึง path ของฐานข้อมูลจาก environment variable หรือใช้ค่า default
    $dbPath = getenv('DB_PATH') ?: (__DIR__ . '/../data/app.sqlite');
    // ถ้าโฟลเดอร์ของ db ยังไม่มี ให้สร้างขึ้นมาก่อน (permission 0775)
    if (!is_dir(dirname($dbPath))) @mkdir(dirname($dbPath), 0775, true);

    // สร้าง PDO instance ใหม่ โดยใช้ driver sqlite
    $pdo = new PDO('sqlite:' . $dbPath);
    // ตั้งค่าให้ PDO โยน exception เมื่อเกิด error (ง่ายต่อการ debug)
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // คืนค่า PDO instance กลับไปใช้
    return $pdo;
}

// คืน path รากของโฟลเดอร์ที่เก็บข้อมูลผู้ใช้ทั้งหมด (/var/www/storage/users)
function storage_users_root(): string {
    // ดึงค่า STORAGE_ROOT จาก env หรือใช้ default
    $root = getenv('STORAGE_ROOT') ?: '/var/www/storage';
    // สร้าง path เต็มของโฟลเดอร์ users (ตัด / หรือ \ ท้ายออกก่อน แล้วต่อด้วย /users)
    $usersRoot = rtrim($root, "/\\") . DIRECTORY_SEPARATOR . 'users';
    // ถ้ายังไม่มีโฟลเดอร์ users ให้สร้างขึ้นมา
    if (!is_dir($usersRoot)) @mkdir($usersRoot, 0775, true);
    return $usersRoot;
}

// ตรวจสอบและทำความสะอาด username ให้อยู่ในรูปแบบที่ระบบยอมรับ
function normalize_username(string $u): string {
    // ตัดช่องว่างข้างหน้า-ข้างหลัง
    $u = trim($u);
    // ถ้าว่าง หรือไม่ตรงกับ pattern ที่กำหนด (A-Z a-z 0-9 . _ - ความยาว 3-32 ตัว) ให้โยน error
    if ($u === '' || !preg_match('/^[A-Za-z0-9._-]{3,32}$/', $u)) {
        throw new RuntimeException("Invalid username format.");
    }
    // คืน username ที่ผ่านการตรวจสอบแล้ว
    return $u;
}

// สร้างโฟลเดอร์สำหรับผู้ใช้คนนั้นถ้ายังไม่มี และคืน path โฟลเดอร์นั้น
function ensure_user_dir(string $username): string {
    // ดึง root ของ users ทั้งหมด
    $usersRoot = storage_users_root();
    // ทำความสะอาด username ก่อน
    $username = normalize_username($username);
    // สร้าง path เต็มของโฟลเดอร์ผู้ใช้นี้
    $dir = $usersRoot . DIRECTORY_SEPARATOR . $username;
    // ถ้ายังไม่มีโฟลเดอร์ ให้สร้าง (recursive = true เผื่อมี subfolder ในอนาคต)
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    return $dir;
}

// สร้างหรืออัปเดตข้อมูลผู้ใช้ในฐานข้อมูล (upsert = update or insert)
function upsert_user(PDO $pdo, string $username, string $password, string $role): void {
    // ตรวจสอบและทำความสะอาด username
    $username = normalize_username($username);
    // กำหนด role ให้ชัดเจน (เฉพาะ admin หรือ user เท่านั้น)
    $role = ($role === 'admin') ? 'admin' : 'user';

    // เข้ารหัส password ด้วย bcrypt (PASSWORD_DEFAULT)
    $hash = password_hash($password, PASSWORD_DEFAULT);

    // ตรวจว่ามี username นี้ในระบบหรือยัง
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :u");
    $stmt->execute([':u' => $username]);
    $exists = (int)$stmt->fetchColumn();

    // ถ้ายังไม่มี → INSERT ข้อมูลใหม่
    if ($exists === 0) {
        $ins = $pdo->prepare("INSERT INTO users(username, password_hash, role) VALUES(:u, :p, :r)");
        $ins->execute([':u' => $username, ':p' => $hash, ':r' => $role]);
    } else {
        // ถ้ามีแล้ว → อัปเดต password และ role (ทำให้ .env เปลี่ยนแล้วมีผลทันที)
        $up = $pdo->prepare("UPDATE users SET password_hash=:p, role=:r WHERE username=:u");
        $up->execute([':u' => $username, ':p' => $hash, ':r' => $role]);
    }

    // สร้างโฟลเดอร์ผู้ใช้ให้ด้วย (ไม่ว่าจะ insert หรือ update)
    ensure_user_dir($username);
}

// ฟังก์ชันเริ่มต้นฐานข้อมูลและผู้ใช้ครั้งแรก (หรือทุกครั้งที่โหลดหน้า)
function init_db_if_needed(): void {
    // ดึง PDO instance
    $pdo = db();

    // สร้างตาราง users ถ้ายังไม่มี
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'user'
    )");

    // ตรวจสอบว่ามี column role หรือยัง (สำหรับ migration ถ้าเคยใช้เวอร์ชันเก่า)
    $cols = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
    $hasRole = false;
    foreach ($cols as $c) {
        if (($c['name'] ?? '') === 'role') { $hasRole = true; break; }
    }
    // ถ้ายังไม่มี column role → เพิ่มเข้าไป
    if (!$hasRole) {
        $pdo->exec("ALTER TABLE users ADD COLUMN role TEXT NOT NULL DEFAULT 'user'");
    }

    // ดึงค่า admin จาก environment variable หรือใช้ default
    $adminUser = getenv('ADMIN_USER') ?: 'admin';
    $adminPass = getenv('ADMIN_PASS') ?: 'admin1234';
    // อัปเดต/สร้าง admin
    upsert_user($pdo, $adminUser, $adminPass, 'admin');

    // รายชื่อผู้ใช้คงที่ 3 คน พร้อมรหัสผ่านจาก env หรือ default
    $fixed = [
        ['user1', getenv('USER1_PASS') ?: 'user1234'],
        ['user2', getenv('USER2_PASS') ?: 'user1234'],
        ['user3', getenv('USER3_PASS') ?: 'user1234'],
    ];
    // สร้าง/อัปเดตผู้ใช้ทั้ง 3 คนนี้
    foreach ($fixed as [$u, $pw]) {
        upsert_user($pdo, $u, $pw, 'user');
    }

    // เตรียมโฟลเดอร์หลักของ users (เผื่อยังไม่มี)
    storage_users_root();
}

// ตรวจว่าผู้ใช้ล็อกอินอยู่หรือไม่
function is_logged_in(): bool {
    return !empty($_SESSION['user']);
}

// คืนชื่อผู้ใช้ปัจจุบัน (string ว่างถ้ายังไม่ login)
function current_user(): string {
    return (string)($_SESSION['user'] ?? '');
}

// คืนบทบาทปัจจุบัน (default เป็น 'user' ถ้าไม่มีใน session)
function current_role(): string {
    return (string)($_SESSION['role'] ?? 'user');
}

// ตรวจว่าผู้ใช้เป็น admin หรือไม่
function is_admin(): bool {
    return current_role() === 'admin';
}

// บังคับให้ต้อง login ก่อน ถ้าไม่ login จะ redirect ไปหน้า login
function require_login(): void {
    if (!is_logged_in()) {
        header("Location: /login.php");
        exit;
    }
}

// บังคับต้องเป็น admin ถ้าไม่ใช่จะแสดง Forbidden และหยุดการทำงาน
function require_admin(): void {
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        exit("Forbidden");
    }
}

// หา root path และชื่อผู้ใช้ที่กำลังดูอยู่ (สำคัญมากสำหรับ multi-user)
function get_base_root_from_request(): array {
    // ต้อง login ก่อน
    require_login();

    // ถ้าเป็น admin สามารถดูของคนอื่นได้ผ่าน ?u=xxx
    if (is_admin()) {
        $u = trim((string)($_GET['u'] ?? ''));
        // ถ้ามีพารามิเตอร์ u และไม่ว่าง
        if ($u !== '') {
            // ทำความสะอาด username
            $u = normalize_username($u);
            // สร้าง/หาโฟลเดอร์ของผู้ใช้นั้น
            $base = ensure_user_dir($u);
            return [$base, $u];
        }
        // ถ้า admin แต่ไม่มี u → ดู root ของทุกคน
        return [storage_users_root(), ''];
    }

    // ถ้าไม่ใช่ admin → ดูเฉพาะของตัวเอง
    $me = current_user();
    $base = ensure_user_dir($me);
    return [$base, $me];
}

// ฟังก์ชันสำคัญที่สุด: ป้องกัน Path Traversal / Directory Traversal
function safe_path(string $root, string $relative): string {
    // ตัดช่องว่างและลบ null byte (ป้องกันการโจมตีบางรูปแบบ)
    $relative = trim($relative);
    $relative = str_replace(["\0"], '', $relative);
    // ลบ / หรือ \ นำหน้า เพื่อไม่ให้กลายเป็น absolute path
    $relative = ltrim($relative, "/\\");
    // ถ้า relative ว่าง → คืน root เดิมเลย
    if ($relative === '') return $root;

    // สร้าง path เต็มโดยต่อ root + separator + relative
    $full = $root . DIRECTORY_SEPARATOR . $relative;

    // หา realpath ของ root (จัดการ symbolic link และ path จริง)
    $realRoot = realpath($root) ?: $root;
    // หา realpath ของ path ที่ต้องการ
    $realFull = realpath($full);

    // กรณี path ยังไม่มีจริง (เช่น จะอัปโหลดไฟล์ใหม่)
    if ($realFull === false) {
        // แปลงเป็นรูปแบบปกติ (แทน \ และ // ด้วย /)
        $norm = str_replace(['\\', '//'], '/', $full);
        $normRoot = rtrim(str_replace('\\', '/', $realRoot), '/') . '/';
        // ตรวจว่าขึ้นต้นด้วย root หรือไม่
        if (strpos($norm, $normRoot) !== 0) {
            throw new RuntimeException("Invalid path.");
        }
        return $full;
    }

    // กรณี path มีจริง → ตรวจด้วย realpath ว่าอยู่ใน root หรือไม่
    if (strpos($realFull, $realRoot) !== 0) {
        throw new RuntimeException("Invalid path.");
    }
    // คืน path ที่ปลอดภัย (realpath version ถ้ามี)
    return $realFull;
}

// เรียกฟังก์ชันเริ่มต้นระบบ (sync db + ผู้ใช้ + โฟลเดอร์) ทุกครั้งที่ include ไฟล์นี้
init_db_if_needed();