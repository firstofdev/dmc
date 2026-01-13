<?php
// cron_alerts.php
require 'config.php';
require 'SmartSystem.php';

echo "Start Job...\n";

// 1. تذكير بالدفعات المستحقة غداً
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$stmt = $pdo->prepare("SELECT p.*, t.full_name, t.phone, u.unit_name 
                       FROM payments p 
                       JOIN contracts c ON p.contract_id=c.id
                       JOIN tenants t ON c.tenant_id=t.id
                       JOIN units u ON c.unit_id=u.id
                       WHERE p.due_date = ? AND p.status = 'pending'");
$stmt->execute([$tomorrow]);

while($r = $stmt->fetch()){
    $paymentLink = $AI->buildPaymentLink(isset($r['id']) ? (int) $r['id'] : null);
    $linkText = $paymentLink ? " رابط السداد: $paymentLink" : '';
    $msg = "مرحباً {$r['full_name']}، نذكرك باستحقاق دفعة إيجار لوحدة {$r['unit_name']} غداً بمبلغ {$r['amount']}.$linkText";
    $sent = $AI->sendWhatsApp($r['phone'], $msg);
    if ($sent) {
        echo "Sent to {$r['full_name']}\n";
    }
}

// 2. تذكير بالدفعات المتأخرة (بعد 3 أيام)
$stmt = $pdo->query("SELECT p.*, t.full_name, t.phone, u.unit_name, DATEDIFF(CURDATE(), p.due_date) AS overdue_days
                     FROM payments p 
                     JOIN contracts c ON p.contract_id=c.id
                     JOIN tenants t ON c.tenant_id=t.id
                     JOIN units u ON c.unit_id=u.id
                     WHERE p.status = 'pending' AND p.due_date < CURDATE()");
while ($r = $stmt->fetch()) {
    if ((int) $r['overdue_days'] === 3) {
        $paymentLink = $AI->buildPaymentLink(isset($r['id']) ? (int) $r['id'] : null);
        $linkText = $paymentLink ? " رابط السداد: $paymentLink" : '';
        $msg = "مرحباً {$r['full_name']}، لديك دفعة متأخرة منذ {$r['overdue_days']} أيام لوحدة {$r['unit_name']} بمبلغ {$r['amount']}.$linkText";
        $sent = $AI->sendWhatsApp($r['phone'], $msg);
        if ($sent) {
            echo "Overdue reminder sent to {$r['full_name']}\n";
        }
    }
}

// 3. أتمتة تصنيف أولويات الصيانة بناءً على التحليل الذكي
$hasPriority = table_has_column($pdo, 'maintenance', 'priority');
$hasAnalysis = table_has_column($pdo, 'maintenance', 'ai_analysis');

if ($hasPriority || $hasAnalysis) {
    $stmt = $pdo->query("SELECT id, description, cost FROM maintenance WHERE status = 'pending'");
    while ($row = $stmt->fetch()) {
        $analysis = $AI->analyzeMaintenance($row['description'], (float) $row['cost']);
        $updates = [];
        $params = [];

        if ($hasPriority) {
            $updates[] = "priority = ?";
            $params[] = $analysis['priority'];
        }
        if ($hasAnalysis) {
            $updates[] = "ai_analysis = ?";
            $params[] = $analysis['analysis'];
        }
        if ($updates) {
            $params[] = $row['id'];
            $pdo->prepare("UPDATE maintenance SET ".implode(', ', $updates)." WHERE id = ?")->execute($params);
        }
    }
    log_activity($pdo, 'تم تحديث أولويات الصيانة تلقائياً', 'automation');
}

// 4. إشعار إدارة النظام بملخص تشغيلي عبر واتساب
if (is_admin_whatsapp_configured()) {
    $cashflow = $AI->getCashflowForecast();
    $maintenancePulse = $AI->getMaintenancePulse();
    $tenantRisk = $AI->getTenantRiskSnapshot();
    $summary = "ملخص تشغيلي:\n";
    $summary .= "تحصيل 30 يوم: ".number_format($cashflow['in_30'])."\n";
    $summary .= "متأخرات: ".number_format($cashflow['overdue'])."\n";
    $summary .= "معدل التحصيل (90 يوم): {$cashflow['collection_trend']}%\n";
    $summary .= "طلبات صيانة معلقة: {$maintenancePulse['pending']}\n";
    $summary .= "مخاطر الصيانة: {$maintenancePulse['risk_score']}/100\n";
    $summary .= "مستأجرون عالي المخاطر: {$tenantRisk['high_risk_count']}\n";

    $sent = $AI->sendWhatsApp(admin_whatsapp_number(), $summary);
    if ($sent) {
        log_activity($pdo, 'تم إرسال ملخص تشغيلي عبر واتساب', 'automation');
    }
}

echo "Done.";
?>
