<?php
/**
 * WhatsApp Message Sending Route
 * Handles sending WhatsApp reminders for payments
 */

require_once '../config.php';
require_once '../SmartSystem.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'error' => 'Method not allowed']));
}

// Check if user is logged in
if (!isset($_SESSION['uid'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$phone = $input['phone'] ?? '';
$message = $input['message'] ?? '';
$paymentId = $input['payment_id'] ?? 0;

// Validate inputs
if (empty($phone) || empty($message)) {
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'Phone and message are required']));
}

// Clean phone number (remove spaces, dashes, etc.)
$phone = preg_replace('/[^0-9+]/', '', $phone);

// Ensure phone number starts with country code
if (!str_starts_with($phone, '+') && !str_starts_with($phone, '966')) {
    // Assume Saudi Arabia if no country code
    if (str_starts_with($phone, '0')) {
        $phone = '966' . substr($phone, 1);
    } else {
        $phone = '966' . $phone;
    }
}

try {
    // Initialize Smart System
    $smartSystem = new SmartSystem($pdo);
    
    // Send WhatsApp message
    $result = $smartSystem->sendWhatsApp($phone, $message);
    
    // Log the activity
    $description = "إرسال تذكير واتساب للدفعة #$paymentId إلى $phone";
    log_activity($pdo, $description, 'whatsapp');
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'تم إرسال الرسالة بنجاح'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
