<?php
// config.php
ob_start(); // تفعيل التخزين المؤقت لمنع مشاكل الـ Header
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    session_start();
}

// إعدادات قاعدة البيانات
define('DB_HOST', 'db5019378605.hosting-data.io'); // عدل البيانات هنا
define('DB_NAME', 'dbs15162823');
define('DB_USER', 'dbu2244961');
define('DB_PASS', 'kuqteg-ginbak-myKga7');

// 🔑 مفاتيح API
define('WHATSAPP_API_URL', 'https://api.ultramsg.com/instance/messages/chat');
define('WHATSAPP_TOKEN', 'your_token_here');

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

function upload($f){
    if($f['error']==0){
        $ext = pathinfo($f['name'], PATHINFO_EXTENSION);
        $n = uniqid().'.'.$ext;
        if (!is_dir('uploads')) mkdir('uploads');
        move_uploaded_file($f['tmp_name'], 'uploads/'.$n);
        return 'uploads/'.$n;
    }
    return null;
}
?>
