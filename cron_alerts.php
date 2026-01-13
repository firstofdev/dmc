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
    $msg = "مرحباً {$r['full_name']}، نذكرك باستحقاق دفعة إيجار لوحدة {$r['unit_name']} غداً بمبلغ {$r['amount']}";
    $AI->sendWhatsApp($r['phone'], $msg);
    echo "Sent to {$r['full_name']}\n";
}

echo "Done.";

// 2. أتمتة تصنيف أولويات الصيانة بناءً على التحليل الذكي
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
?>
