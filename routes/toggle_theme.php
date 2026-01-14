<?php
/**
 * API endpoint لتبديل المظهر
 */
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['uid'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $theme = $data['theme'] ?? 'dark';
    
    // Validate theme value
    if (!in_array($theme, ['light', 'dark'])) {
        $theme = 'dark';
    }
    
    // Update theme in database
    $stmt = $pdo->prepare("INSERT INTO settings (k, v) VALUES ('theme', ?) ON DUPLICATE KEY UPDATE v = ?");
    $stmt->execute([$theme, $theme]);
    
    log_activity($pdo, "تم تغيير المظهر إلى: $theme", 'settings');
    
    echo json_encode(['success' => true, 'theme' => $theme]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
?>
