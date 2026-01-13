<?php
// cron_alerts.php
require 'config.php';
require 'SmartSystem.php';

echo "Start Job...\n";

function should_run_schedule(string $mode, ?string $lastRun): bool {
    if ($mode === 'off') {
        return false;
    }
    if ($lastRun === null || $lastRun === '') {
        return true;
    }
    try {
        $last = new DateTime($lastRun);
    } catch (Exception $e) {
        return true;
    }

    $now = new DateTime();
    if ($mode === 'daily') {
        return $last->format('Y-m-d') !== $now->format('Y-m-d');
    }
    if ($mode === 'weekly') {
        return $last->format('oW') !== $now->format('oW');
    }
    if ($mode === 'monthly') {
        return $last->format('Y-m') !== $now->format('Y-m');
    }
    if ($mode === 'instant') {
        return true;
    }
    return false;
}

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
// 4. إرسال ملخص التنبيهات بحسب تكرار الملخص
$digestMode = get_setting('alerts_digest', 'weekly');
$digestLastSent = get_setting('alerts_digest_last_sent', '');
$shouldDigest = should_run_schedule($digestMode, $digestLastSent);

if ($shouldDigest) {
    $alertDays = get_setting_int('alert_days', 30);
    $overdueCount = (int) $pdo->query("SELECT COUNT(*) FROM payments WHERE status != 'paid' AND due_date < CURDATE()")->fetchColumn();
    $overdueAmount = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status != 'paid' AND due_date < CURDATE()")->fetchColumn();
    $pendingPayments = (int) $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn();
    $upcomingContracts = (int) $pdo->query("SELECT COUNT(*) FROM contracts WHERE end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL {$alertDays} DAY)")->fetchColumn();
    $pendingMaintenance = (int) $pdo->query("SELECT COUNT(*) FROM maintenance WHERE status = 'pending'")->fetchColumn();

    $cashflow = $AI->getCashflowForecast();
    $summary = "ملخص التنبيهات:\n";
    $summary .= "دفعات متأخرة: {$overdueCount} (".number_format($overdueAmount).")\n";
    $summary .= "دفعات معلقة: {$pendingPayments}\n";
    $summary .= "عقود تنتهي خلال {$alertDays} يوم: {$upcomingContracts}\n";
    $summary .= "طلبات صيانة معلقة: {$pendingMaintenance}\n";
    $summary .= "تحصيل 30 يوم: ".number_format($cashflow['in_30'])."\n";
    $summary .= "معدل التحصيل (90 يوم): {$cashflow['collection_trend']}%\n";

    $sentAny = false;
    if (is_admin_whatsapp_configured()) {
        $sent = $AI->sendWhatsApp(admin_whatsapp_number(), $summary);
        if ($sent) {
            log_activity($pdo, 'تم إرسال ملخص التنبيهات عبر واتساب', 'automation');
            $sentAny = true;
        }
    }

    $reportingEmail = get_setting('reporting_email', '');
    if ($reportingEmail !== '') {
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/plain; charset=UTF-8',
        ];
        $subject = 'ملخص التنبيهات الذكي';
        $mailSent = mail($reportingEmail, $subject, $summary, implode("\r\n", $headers));
        if ($mailSent) {
            log_activity($pdo, 'تم إرسال ملخص التنبيهات بالبريد الإلكتروني', 'automation');
            $sentAny = true;
        } else {
            log_activity($pdo, 'تعذر إرسال ملخص التنبيهات بالبريد الإلكتروني', 'automation');
        }
    }

    if ($sentAny) {
        set_setting('alerts_digest_last_sent', date('Y-m-d H:i:s'));
    }
}

// 5. النسخ الاحتياطي التلقائي
$autoBackup = get_setting('auto_backup', 'off');
$autoBackupLast = get_setting('auto_backup_last_run', '');
$shouldBackup = should_run_schedule($autoBackup, $autoBackupLast);

if ($shouldBackup) {
    $backupDir = __DIR__ . '/backups';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    $filePath = $backupDir . '/' . $filename;

    try {
        $sqlScript = generate_backup_sql($pdo);
        file_put_contents($filePath, $sqlScript);
        log_activity($pdo, 'تم إنشاء نسخة احتياطية تلقائية: ' . $filename, 'automation');
        set_setting('auto_backup_last_run', date('Y-m-d H:i:s'));
    } catch (Exception $e) {
        log_activity($pdo, 'تعذر إنشاء نسخة احتياطية تلقائية', 'automation');
    }

    $retainWeeks = max(1, get_setting_int('backup_frequency', 8));
    $retainSeconds = $retainWeeks * 7 * 24 * 60 * 60;
    $cutoff = time() - $retainSeconds;
    foreach (glob($backupDir . '/backup_*.sql') as $backupFile) {
        if (filemtime($backupFile) < $cutoff) {
            @unlink($backupFile);
        }
    }
}

echo "Done.";
?>
