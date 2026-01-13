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
?>
