<?php
/**
 * ملف ترقية قاعدة البيانات
 * يضيف الأعمدة والحقول الجديدة المطلوبة
 * 
 * تشغيل هذا الملف مرة واحدة فقط
 */

require 'config.php';

echo "<!DOCTYPE html>
<html lang='ar' dir='rtl'>
<head>
    <meta charset='UTF-8'>
    <title>ترقية قاعدة البيانات</title>
    <style>
        body { font-family: 'Tajawal', Arial, sans-serif; background: #0a0a0a; color: #fff; padding: 40px; }
        .success { color: #22c55e; padding: 10px; margin: 5px 0; }
        .error { color: #ef4444; padding: 10px; margin: 5px 0; }
        .info { color: #3b82f6; padding: 10px; margin: 5px 0; }
        h2 { color: #6366f1; }
    </style>
</head>
<body>";

echo "<h2>🚀 ترقية قاعدة البيانات</h2>";

$migrations = [
    // إضافة أعمدة جدول المستأجرين
    "ALTER TABLE tenants ADD COLUMN id_type VARCHAR(50) DEFAULT NULL COMMENT 'نوع الهوية'",
    "ALTER TABLE tenants ADD COLUMN address TEXT DEFAULT NULL COMMENT 'العنوان'",
    "ALTER TABLE tenants ADD COLUMN id_photo VARCHAR(255) DEFAULT NULL COMMENT 'صورة الهوية'",
    
    // إضافة أعمدة جدول الوحدات للمحلات
    "ALTER TABLE units ADD COLUMN shop_name VARCHAR(200) DEFAULT NULL COMMENT 'اسم المحل'",
    "ALTER TABLE units ADD COLUMN shop_logo VARCHAR(255) DEFAULT NULL COMMENT 'شعار المحل'",
    "ALTER TABLE units ADD COLUMN tenant_name VARCHAR(200) DEFAULT NULL COMMENT 'اسم المستأجر الحالي'",
    
    // إضافة حقل المظهر في الإعدادات
    "INSERT IGNORE INTO settings (k, v) VALUES ('theme', 'dark')",
];

$successCount = 0;
$errorCount = 0;

foreach ($migrations as $sql) {
    try {
        $pdo->exec($sql);
        echo "<div class='success'>✔ تم تنفيذ: " . htmlspecialchars(substr($sql, 0, 100)) . "...</div>";
        $successCount++;
    } catch (PDOException $e) {
        // قد يكون العمود موجوداً مسبقاً
        if (strpos($e->getMessage(), 'Duplicate') !== false || strpos($e->getMessage(), 'exists') !== false) {
            echo "<div class='info'>ℹ موجود مسبقاً: " . htmlspecialchars(substr($sql, 0, 100)) . "...</div>";
        } else {
            echo "<div class='error'>✖ خطأ: " . htmlspecialchars($e->getMessage()) . "</div>";
            $errorCount++;
        }
    }
}

try {
    log_activity($pdo, "تم ترقية قاعدة البيانات - نجح: {$successCount}", 'system');
} catch (Exception $e) {
    // تجاهل إذا كان جدول activity_log غير موجود
}

echo "<h3 style='color:#22c55e'>✅ تمت عملية الترقية</h3>";
echo "<p>نجح: {$successCount} | أخطاء: {$errorCount}</p>";
echo "<p><strong>⚠️ يمكنك الآن حذف هذا الملف (upgrade_schema.php) لأسباب أمنية</strong></p>";
echo "<p><a href='index.php' style='color:#6366f1; text-decoration:underline; font-weight:bold'>الرجوع للنظام</a></p>";

echo "</body></html>";
?>
