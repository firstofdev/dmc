<?php
// config.php
ob_start(); // تفعيل التخزين المؤقت لمنع مشاكل الـ Header
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    session_start();
}

// إعدادات قاعدة البيانات
define('DB_HOST', getenv('DB_HOST') ?: 'db5019378605.hosting-data.io'); // عدل البيانات هنا
define('DB_NAME', getenv('DB_NAME') ?: 'dbs15162823');
define('DB_USER', getenv('DB_USER') ?: 'dbu2244961');
define('DB_PASS', getenv('DB_PASS') ?: 'kuqteg-ginbak-myKga7');

// 🔑 مفاتيح API
define('WHATSAPP_API_URL', getenv('WHATSAPP_API_URL') ?: 'https://api.ultramsg.com/instance/messages/chat');
define('WHATSAPP_TOKEN', getenv('WHATSAPP_TOKEN') ?: 'your_token_here');
define('OCR_API_URL', getenv('OCR_API_URL') ?: '');
define('OCR_API_KEY', getenv('OCR_API_KEY') ?: '');
define('UPLOAD_MAX_BYTES', (int) (getenv('UPLOAD_MAX_BYTES') ?: 5 * 1024 * 1024));

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("خطأ في الاتصال بقاعدة البيانات: " . $e->getMessage());
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

function check_csrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            die("خطأ أمني: رمز CSRF غير صالح.");
        }
    }
}

function upload($f, array $allowedMime = ['image/jpeg', 'image/png', 'application/pdf'], $maxBytes = UPLOAD_MAX_BYTES){
    if ($f['error'] !== 0) {
        return null;
    }
    if ($f['size'] > $maxBytes) {
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($f['tmp_name']);
    if (!in_array($mime, $allowedMime, true)) {
        return null;
    }

    $ext = pathinfo($f['name'], PATHINFO_EXTENSION);
    $safeExt = preg_replace('/[^a-zA-Z0-9]/', '', $ext);
    $n = uniqid('', true).'.'.$safeExt;
    if (!is_dir('uploads')) mkdir('uploads', 0755, true);
    if (!move_uploaded_file($f['tmp_name'], 'uploads/'.$n)) {
        return null;
    }
    return 'uploads/'.$n;
}
?>
