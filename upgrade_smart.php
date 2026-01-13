<?php
require 'config.php';
echo "<h1>جاري ترقية النظام إلى النسخة الذكية...</h1>";

$updates = [
    // جدول لطلبات الصيانة الذكية (مع الصور)
    "ALTER TABLE maintenance ADD COLUMN ai_analysis TEXT",
    "ALTER TABLE maintenance ADD COLUMN priority ENUM('low','medium','high','emergency') DEFAULT 'medium'",
    
    // جدول المستأجرين (تفعيل الدخول لهم)
    "ALTER TABLE tenants ADD COLUMN password VARCHAR(255)",
    "ALTER TABLE tenants ADD COLUMN last_login TIMESTAMP NULL",
    "ALTER TABLE tenants ADD COLUMN document_data JSON", // لحفظ بيانات OCR

    // سجل النشاطات
    "CREATE TABLE IF NOT EXISTS activity_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        description TEXT,
        type VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )"
];

foreach ($updates as $sql) {
    try {
        $pdo->exec($sql);
        echo "<p style='color:green'>✔ تم تنفيذ أمر SQL بنجاح.</p>";
    } catch (PDOException $e) {
        echo "<p style='color:gray'>⚠ ملاحظة: " . $e->getMessage() . "</p>";
    }
}
echo "<h3>تمت الترقية! احذف هذا الملف الآن.</h3>";
?>
