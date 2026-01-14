<?php
// config.php
ob_start(); // تفعيل التخزين المؤقت لمنع مشاكل الـ Header

function is_https_request(): bool {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        return true;
    }
    return isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443;
}

function send_security_headers(): void {
    if (headers_sent()) {
        return;
    }
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('X-Download-Options: noopen');
    header('X-Permitted-Cross-Domain-Policies: none');
    header('X-DNS-Prefetch-Control: off');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    if (is_https_request()) {
        header('Strict-Transport-Security: max-age=63072000; includeSubDomains; preload');
    }
    $csp = [
        "default-src 'self'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'none'",
        "object-src 'none'",
        "img-src 'self' data:",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net",
        "font-src 'self' https://fonts.gstatic.com",
        "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
        "connect-src 'self'",
    ];
    if (is_https_request()) {
        $csp[] = "upgrade-insecure-requests";
        $csp[] = "block-all-mixed-content";
    }
    header('Content-Security-Policy: ' . implode('; ', $csp));
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', is_https_request() ? '1' : '0');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', '28800');
    ini_set('session.sid_length', '48');
    ini_set('session.sid_bits_per_character', '6');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => is_https_request(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

send_security_headers();

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
define('ADMIN_WHATSAPP', getenv('ADMIN_WHATSAPP') ?: '');
define('PAYMENT_PORTAL_URL', getenv('PAYMENT_PORTAL_URL') ?: '');
define('SMART_FEATURES_MODE', getenv('SMART_FEATURES_MODE') ?: 'force');
define('SESSION_IDLE_TIMEOUT', (int) (getenv('SESSION_IDLE_TIMEOUT') ?: 1800));
define('SESSION_MAX_LIFETIME', (int) (getenv('SESSION_MAX_LIFETIME') ?: 28800));
define('SESSION_ROTATE_INTERVAL', (int) (getenv('SESSION_ROTATE_INTERVAL') ?: 900));

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

function log_activity(PDO $pdo, string $description, string $type = 'info'): void {
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_log (description, type) VALUES (?, ?)");
        $stmt->execute([$description, $type]);
    } catch (Exception $e) {
        // تجاهل الأخطاء في حال عدم وجود الجدول أو أي مشكلة في الاتصال
    }
}

function get_client_ip(): string {
    $candidates = [
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
        $_SERVER['HTTP_X_REAL_IP'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? '',
    ];

    foreach ($candidates as $candidate) {
        if ($candidate === '') {
            continue;
        }
        $parts = array_map('trim', explode(',', $candidate));
        foreach ($parts as $part) {
            if (filter_var($part, FILTER_VALIDATE_IP)) {
                return $part;
            }
        }
    }

    return '0.0.0.0';
}

function session_fingerprint(): string {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ip = get_client_ip();
    return hash('sha256', $userAgent . '|' . $ip);
}

function initialize_session_security(): void {
    $now = time();
    if (empty($_SESSION['created_at'])) {
        $_SESSION['created_at'] = $now;
    }
    $_SESSION['last_activity'] = $now;
    $_SESSION['last_rotate'] = $now;
    $_SESSION['fingerprint'] = session_fingerprint();
}

function enforce_session_security(): void {
    if (session_status() === PHP_SESSION_NONE) {
        return;
    }
    if (empty($_SESSION['uid'])) {
        return;
    }
    $now = time();
    $createdAt = (int) ($_SESSION['created_at'] ?? $now);
    $lastActivity = (int) ($_SESSION['last_activity'] ?? $now);
    if (($now - $lastActivity) > SESSION_IDLE_TIMEOUT || ($now - $createdAt) > SESSION_MAX_LIFETIME) {
        session_unset();
        session_destroy();
        header('Location: login.php');
        exit;
    }
    $currentFingerprint = session_fingerprint();
    $storedFingerprint = (string) ($_SESSION['fingerprint'] ?? '');
    if ($storedFingerprint !== '' && !hash_equals($storedFingerprint, $currentFingerprint)) {
        session_unset();
        session_destroy();
        header('Location: login.php');
        exit;
    }
    $_SESSION['last_activity'] = $now;
    if ($storedFingerprint === '') {
        $_SESSION['fingerprint'] = $currentFingerprint;
    }
    $lastRotate = (int) ($_SESSION['last_rotate'] ?? 0);
    if ($lastRotate === 0 || ($now - $lastRotate) > SESSION_ROTATE_INTERVAL) {
        session_regenerate_id(true);
        $_SESSION['last_rotate'] = $now;
    }
}

function rate_limit_check(string $key, int $maxAttempts, int $windowSeconds, int $blockSeconds = 900): array {
    $now = time();
    $path = sys_get_temp_dir() . '/dmc_rate_' . hash('sha256', $key) . '.json';
    $state = [
        'count' => 0,
        'reset' => $now + $windowSeconds,
        'blocked_until' => 0,
    ];

    $fp = fopen($path, 'c+');
    if ($fp !== false) {
        if (flock($fp, LOCK_EX)) {
            $contents = stream_get_contents($fp);
            if ($contents !== false && $contents !== '') {
                $decoded = json_decode($contents, true);
                if (is_array($decoded)) {
                    $state = array_merge($state, $decoded);
                }
            }
            if (!empty($state['blocked_until']) && $now < (int) $state['blocked_until']) {
                $retryAfter = (int) $state['blocked_until'] - $now;
                flock($fp, LOCK_UN);
                fclose($fp);
                return ['allowed' => false, 'retry_after' => $retryAfter, 'remaining' => 0];
            }
            if ($now >= (int) $state['reset']) {
                $state['count'] = 0;
                $state['reset'] = $now + $windowSeconds;
                $state['blocked_until'] = 0;
            }
            $state['count']++;
            if ($state['count'] > $maxAttempts) {
                $state['blocked_until'] = $now + $blockSeconds;
            }
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($state));
            fflush($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }

    if (!empty($state['blocked_until']) && $now < (int) $state['blocked_until']) {
        return ['allowed' => false, 'retry_after' => (int) $state['blocked_until'] - $now, 'remaining' => 0];
    }

    return [
        'allowed' => true,
        'retry_after' => 0,
        'remaining' => max(0, $maxAttempts - (int) $state['count']),
    ];
}

function rate_limit_clear(string $key): void {
    $path = sys_get_temp_dir() . '/dmc_rate_' . hash('sha256', $key) . '.json';
    if (is_file($path)) {
        @unlink($path);
    }
}

function get_setting(string $key, string $default = ''): string {
    if (!isset($GLOBALS['pdo'])) {
        return $default;
    }

    try {
        $stmt = $GLOBALS['pdo']->prepare("SELECT v FROM settings WHERE k = ?");
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        if ($value === false || $value === null || $value === '') {
            return $default;
        }
        return (string) $value;
    } catch (Exception $e) {
        return $default;
    }
}

function set_setting(string $key, string $value): void {
    if (!isset($GLOBALS['pdo'])) {
        return;
    }

    try {
        $stmt = $GLOBALS['pdo']->prepare("REPLACE INTO settings (k, v) VALUES (?, ?)");
        $stmt->execute([$key, $value]);
    } catch (Exception $e) {
        // تجاهل الأخطاء المؤقتة في حال عدم توفر الجدول
    }
}

function getSet(string $key, string $default = ''): string {
    return get_setting($key, $default);
}

function get_setting_int(string $key, int $default = 0): int {
    $value = get_setting($key, '');
    if ($value === '') {
        return $default;
    }
    return (int) $value;
}

function secure($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function format_date(string $date, string $fallback = ''): string {
    if ($date === '') {
        return $fallback;
    }
    $format = get_setting('date_format', 'Y-m-d');
    try {
        $dt = new DateTime($date);
        return $dt->format($format);
    } catch (Exception $e) {
        return $date;
    }
}

function payment_method_label(string $method): string {
    $map = [
        'bank_transfer' => 'تحويل بنكي',
        'card' => 'بطاقة',
        'cash' => 'نقدي',
        'online' => 'بوابة دفع إلكترونية',
    ];
    return $map[$method] ?? 'غير محدد';
}

function runtime_setting(string $key, string $envValue = '', string $default = ''): string {
    $setting = get_setting($key, '');
    if ($setting !== '') {
        return $setting;
    }
    if ($envValue !== '') {
        return (string) $envValue;
    }
    return $default;
}

function whatsapp_token(): string {
    return runtime_setting('whatsapp_token', WHATSAPP_TOKEN);
}

function whatsapp_api_url(): string {
    return runtime_setting('whatsapp_api_url', WHATSAPP_API_URL);
}

function ocr_api_url(): string {
    return runtime_setting('ocr_api_url', OCR_API_URL);
}

function ocr_api_key(): string {
    return runtime_setting('ocr_api_key', OCR_API_KEY);
}

function admin_whatsapp_number(): string {
    return runtime_setting('admin_whatsapp', ADMIN_WHATSAPP);
}

function payment_portal_url(): string {
    return runtime_setting('payment_portal_url', PAYMENT_PORTAL_URL);
}

function smart_features_mode(): string {
    return runtime_setting('smart_features_mode', SMART_FEATURES_MODE, 'force');
}

function is_whatsapp_configured(): bool {
    $token = whatsapp_token();
    return $token !== '' && $token !== 'your_token_here';
}

function is_ocr_configured(): bool {
    return ocr_api_url() !== '' && ocr_api_key() !== '';
}

function is_payment_portal_configured(): bool {
    return payment_portal_url() !== '';
}

function is_admin_whatsapp_configured(): bool {
    return admin_whatsapp_number() !== '';
}

function require_role(array $roles): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $role = $_SESSION['role'] ?? null;
    if (!$role || !in_array($role, $roles, true)) {
        http_response_code(403);
        die("غير مصرح بالدخول.");
    }
}

function get_recent_activity(PDO $pdo, int $limit = 5): array {
    try {
        $stmt = $pdo->prepare("SELECT * FROM activity_log ORDER BY created_at DESC, id DESC LIMIT ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function table_has_column(PDO $pdo, string $table, string $column): bool {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$column]);
        return (bool) $stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
}

function tenant_name_column(PDO $pdo): string {
    return table_has_column($pdo, 'tenants', 'full_name') ? 'full_name' : 'name';
}

function tenant_created_at_column(PDO $pdo): ?string {
    return table_has_column($pdo, 'tenants', 'created_at') ? 'created_at' : null;
}

function smart_features_force_enabled(): bool {
    return smart_features_mode() === 'force';
}

function generate_backup_sql(PDO $pdo): string {
    $tables = [];
    $query = $pdo->query('SHOW TABLES');
    while ($row = $query->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    $sqlScript = "-- DATABASE BACKUP\n-- DATE: " . date('Y-m-d H:i:s') . "\n\n";

    foreach ($tables as $table) {
        $query = $pdo->query('SHOW CREATE TABLE ' . $table);
        $row = $query->fetch(PDO::FETCH_NUM);
        if (!empty($row[1])) {
            $sqlScript .= "\n\n" . $row[1] . ";\n\n";
        }

        $query = $pdo->query('SELECT * FROM ' . $table);
        $columnCount = $query->columnCount();

        while ($row = $query->fetch(PDO::FETCH_NUM)) {
            $sqlScript .= "INSERT INTO $table VALUES(";
            for ($j = 0; $j < $columnCount; $j++) {
                $row[$j] = addslashes($row[$j]);
                $row[$j] = str_replace("\n", "\\n", $row[$j]);
                $sqlScript .= isset($row[$j]) ? '"' . $row[$j] . '"' : '""';
                if ($j < ($columnCount - 1)) {
                    $sqlScript .= ',';
                }
            }
            $sqlScript .= ");\n";
        }
    }

    return $sqlScript;
}

/**
 * يحسب تفاصيل المبلغ للعقد (أساسي / ضريبة / إجمالي) مع حماية من القيم السالبة.
 */
function contract_amount_parts(array $row): array {
    $total = isset($row['total_amount']) ? (float) $row['total_amount'] : 0.0;
    $taxIncluded = isset($row['tax_included']) && (int) $row['tax_included'] === 1;
    $taxAmountRaw = isset($row['tax_amount']) ? (float) $row['tax_amount'] : 0.0;
    $taxPercentRaw = isset($row['tax_percent']) ? (float) $row['tax_percent'] : 0.0;
    $taxAmount = $taxAmountRaw > 0 ? $taxAmountRaw : 0.0;
    $taxPercent = $taxPercentRaw > 0 ? min($taxPercentRaw, 100.0) : 0.0;

    if ($taxIncluded && $taxPercent > 0) {
        $base = $total / (1 + ($taxPercent / 100));
        $taxAmount = max($taxAmount, round($total - $base, 2));
    } else {
        $base = $taxIncluded ? ($total - $taxAmount) : $total;
    }
    if ($base < 0) { $base = 0.0; }

    return [
        'total' => $total,
        'tax_included' => $taxIncluded,
        'tax_amount' => $taxAmount,
        'tax_percent' => $taxPercent,
        'base_amount' => $base,
    ];
}
?>
