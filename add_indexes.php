<?php
/**
 * ملف الهجرة لإضافة الفهارس الضرورية
 * يحسن أداء الاستعلامات على الجداول الكبيرة
 * 
 * تشغيل هذا الملف مرة واحدة فقط بعد التثبيت
 */

require 'config.php';

echo "<h2>إضافة الفهارس لتحسين الأداء...</h2>";

$indexes = [
    // فهارس جدول properties
    "CREATE INDEX IF NOT EXISTS idx_properties_type ON properties(type)",
    
    // فهارس جدول units
    "CREATE INDEX IF NOT EXISTS idx_units_property ON units(property_id)",
    "CREATE INDEX IF NOT EXISTS idx_units_status ON units(status)",
    
    // فهارس جدول contracts
    "CREATE INDEX IF NOT EXISTS idx_contracts_tenant ON contracts(tenant_id)",
    "CREATE INDEX IF NOT EXISTS idx_contracts_unit ON contracts(unit_id)",
    "CREATE INDEX IF NOT EXISTS idx_contracts_status ON contracts(status)",
    "CREATE INDEX IF NOT EXISTS idx_contracts_dates ON contracts(start_date, end_date)",
    
    // فهارس جدول payments
    "CREATE INDEX IF NOT EXISTS idx_payments_contract ON payments(contract_id)",
    "CREATE INDEX IF NOT EXISTS idx_payments_status ON payments(status)",
    "CREATE INDEX IF NOT EXISTS idx_payments_due_date ON payments(due_date)",
    "CREATE INDEX IF NOT EXISTS idx_payments_paid_date ON payments(paid_date)",
    
    // فهارس جدول maintenance
    "CREATE INDEX IF NOT EXISTS idx_maintenance_property ON maintenance(property_id)",
    "CREATE INDEX IF NOT EXISTS idx_maintenance_unit ON maintenance(unit_id)",
    "CREATE INDEX IF NOT EXISTS idx_maintenance_status ON maintenance(status)",
    "CREATE INDEX IF NOT EXISTS idx_maintenance_date ON maintenance(request_date)",
    "CREATE INDEX IF NOT EXISTS idx_maintenance_priority ON maintenance(priority)",
    
    // فهارس جدول tenants
    "CREATE INDEX IF NOT EXISTS idx_tenants_phone ON tenants(phone)",
    "CREATE INDEX IF NOT EXISTS idx_tenants_id_number ON tenants(id_number)",
    
    // فهارس جدول activity_log
    "CREATE INDEX IF NOT EXISTS idx_activity_log_type ON activity_log(type)",
    "CREATE INDEX IF NOT EXISTS idx_activity_log_created ON activity_log(created_at)",
    
    // فهارس جدول alerts
    "CREATE INDEX IF NOT EXISTS idx_alerts_status ON alerts(status)",
    "CREATE INDEX IF NOT EXISTS idx_alerts_type ON alerts(alert_type)",
    "CREATE INDEX IF NOT EXISTS idx_alerts_created ON alerts(created_at)",
    
    // فهارس جدول transactions
    "CREATE INDEX IF NOT EXISTS idx_transactions_payment ON transactions(payment_id)",
    "CREATE INDEX IF NOT EXISTS idx_transactions_date ON transactions(transaction_date)",
    
    // فهارس جدول meter_readings
    "CREATE INDEX IF NOT EXISTS idx_meter_contract ON meter_readings(contract_id)",
    "CREATE INDEX IF NOT EXISTS idx_meter_unit ON meter_readings(unit_id)",
    "CREATE INDEX IF NOT EXISTS idx_meter_date ON meter_readings(reading_date)",
];

$successCount = 0;
$errorCount = 0;

foreach ($indexes as $sql) {
    try {
        $pdo->exec($sql);
        echo "<p style='color:green'>✔ تم إنشاء فهرس بنجاح</p>";
        $successCount++;
    } catch (PDOException $e) {
        // قد يكون الفهرس موجوداً مسبقاً
        echo "<p style='color:gray'>⚠ ملاحظة: " . htmlspecialchars($e->getMessage()) . "</p>";
        $errorCount++;
    }
}

log_activity($pdo, "تم إضافة {$successCount} فهرس لتحسين الأداء", 'system');

echo "<h3 style='color:#22c55e'>✅ تمت عملية إضافة الفهارس</h3>";
echo "<p>نجح: {$successCount} | ملاحظات: {$errorCount}</p>";
echo "<p><strong>يمكنك الآن حذف هذا الملف (add_indexes.php)</strong></p>";
echo "<p><a href='index.php' style='color:white; text-decoration:underline'>الرجوع للنظام</a></p>";

?>
