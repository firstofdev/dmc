<?php
/**
 * صفحة لوحة القيادة - الوضع الآمن
 * هذا الملف مصمم ليعمل حتى لو كانت قاعدة البيانات فارغة تماماً
 */

// 1. تهيئة المتغيرات بقيم افتراضية (أصفار)
$stats = [
    'contracts' => 0,
    'units' => 0,
    'rented' => 0,
    'tenants' => 0,
    'income' => 0,
    'pending' => 0
];
$lists = [
    'ending' => [],
    'payments' => []
];
$insights = [
    'occupancy_rate' => 0,
    'collection_rate' => 0,
    'expected_30' => 0,
    'overdue_count' => 0,
    'maintenance_pending' => 0,
    'maintenance_high' => 0,
    'avg_paid_3m' => 0,
];
$recommendations = [];
$riskTenants = [];
$recentActivity = [];
$cashflow = [
    'in_30' => 0,
    'in_60' => 0,
    'in_90' => 0,
    'overdue' => 0,
    'collection_trend' => 0,
];
$maintenancePulse = [
    'pending' => 0,
    'avg_cost_90' => 0,
    'repeat_units' => 0,
    'emergency' => 0,
    'risk_score' => 0,
];
$tenantRiskSnapshot = [
    'high_risk_count' => 0,
    'max_overdue_days' => 0,
    'items' => [],
];
$settings = [
    'target_occupancy' => 90,
    'target_collection' => 95,
    'overdue_threshold' => 5,
];
$financeLabels = [];
$financePaid = [];
$financeExpected = [];

// 2. جلب البيانات بأمان
try {
    if(isset($pdo)) {
        $settingsQuery = $pdo->query("SELECT * FROM settings");
        while ($row = $settingsQuery->fetch()) {
            $settings[$row['k']] = $row['v'];
        }

        // الإحصائيات العلوية
        $stats['contracts'] = $pdo->query("SELECT COUNT(*) FROM contracts WHERE status='active'")->fetchColumn() ?: 0;
        $stats['units'] = $pdo->query("SELECT COUNT(*) FROM units")->fetchColumn() ?: 0;
        $stats['rented'] = $pdo->query("SELECT COUNT(*) FROM units WHERE status='rented'")->fetchColumn() ?: 0;
        $stats['tenants'] = $pdo->query("SELECT COUNT(*) FROM tenants")->fetchColumn() ?: 0;
        $stats['income'] = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='paid'")->fetchColumn() ?: 0;
        $stats['pending'] = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status!='paid'")->fetchColumn() ?: 0;

        // القوائم السفلية
        $lists['ending'] = $pdo->query("SELECT c.*, t.name as tenant_name FROM contracts c JOIN tenants t ON c.tenant_id=t.id WHERE c.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
        $lists['payments'] = $pdo->query("SELECT p.*, c.id as contract_id FROM payments p JOIN contracts c ON p.contract_id=c.id WHERE p.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND p.status='pending' LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

        $totalInvoiced = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments")->fetchColumn() ?: 0;
        $insights['collection_rate'] = $totalInvoiced > 0 ? round(($stats['income'] / $totalInvoiced) * 100, 1) : 0;
        $insights['occupancy_rate'] = $stats['units'] > 0 ? round(($stats['rented'] / $stats['units']) * 100, 1) : 0;
        $insights['expected_30'] = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status!='paid' AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)")->fetchColumn() ?: 0;
        $insights['overdue_count'] = $pdo->query("SELECT COUNT(*) FROM payments WHERE status!='paid' AND due_date < CURDATE()")->fetchColumn() ?: 0;
        $insights['maintenance_pending'] = $pdo->query("SELECT COUNT(*) FROM maintenance WHERE status='pending'")->fetchColumn() ?: 0;
        $hasPriority = table_has_column($pdo, 'maintenance', 'priority');
        if ($hasPriority) {
            $insights['maintenance_high'] = $pdo->query("SELECT COUNT(*) FROM maintenance WHERE status='pending' AND priority IN ('high','emergency')")->fetchColumn() ?: 0;
        }
        $avgPaid = $pdo->query("SELECT COALESCE(AVG(month_total),0) FROM (
            SELECT SUM(amount) AS month_total
            FROM payments
            WHERE status='paid' AND paid_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
            GROUP BY DATE_FORMAT(paid_date, '%Y-%m')
        ) t")->fetchColumn();
        $insights['avg_paid_3m'] = $avgPaid ?: 0;

        $startDate = (new DateTime('first day of this month'))->modify('-5 months')->format('Y-m-d');
        $paidRows = $pdo->prepare("SELECT DATE_FORMAT(paid_date,'%Y-%m') AS ym, COALESCE(SUM(amount),0) AS total
            FROM payments
            WHERE status='paid' AND paid_date >= ?
            GROUP BY DATE_FORMAT(paid_date,'%Y-%m')");
        $paidRows->execute([$startDate]);
        $paidMap = [];
        foreach ($paidRows->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $paidMap[$row['ym']] = (float) $row['total'];
        }

        $expectedRows = $pdo->prepare("SELECT DATE_FORMAT(due_date,'%Y-%m') AS ym, COALESCE(SUM(amount),0) AS total
            FROM payments
            WHERE status!='paid' AND due_date >= ?
            GROUP BY DATE_FORMAT(due_date,'%Y-%m')");
        $expectedRows->execute([$startDate]);
        $expectedMap = [];
        foreach ($expectedRows->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $expectedMap[$row['ym']] = (float) $row['total'];
        }

        $monthNames = [
            1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل', 5 => 'مايو', 6 => 'يونيو',
            7 => 'يوليو', 8 => 'أغسطس', 9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'
        ];
        $periodCursor = new DateTime($startDate);
        for ($i = 0; $i < 6; $i++) {
            $ym = $periodCursor->format('Y-m');
            $monthIndex = (int) $periodCursor->format('n');
            $financeLabels[] = $monthNames[$monthIndex] . ' ' . $periodCursor->format('Y');
            $financePaid[] = $paidMap[$ym] ?? 0;
            $financeExpected[] = $expectedMap[$ym] ?? 0;
            $periodCursor->modify('+1 month');
        }

        $riskTenants = $pdo->query("SELECT t.name, t.phone,
            COUNT(p.id) as overdue_count,
            COALESCE(SUM(p.amount),0) AS overdue_amount,
            COALESCE(MAX(DATEDIFF(CURDATE(), p.due_date)),0) AS max_overdue_days
            FROM payments p
            JOIN contracts c ON p.contract_id=c.id
            JOIN tenants t ON c.tenant_id=t.id
            WHERE p.status!='paid' AND p.due_date < CURDATE()
            GROUP BY t.id
            ORDER BY overdue_count DESC
            LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

        $recentActivity = get_recent_activity($pdo, 6);
        if (isset($AI)) {
            $cashflow = $AI->getCashflowForecast();
            $maintenancePulse = $AI->getMaintenancePulse();
            $tenantRiskSnapshot = $AI->getTenantRiskSnapshot();
        } else {
            $cashflow['in_30'] = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status!='paid' AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)")->fetchColumn() ?: 0;
            $cashflow['in_60'] = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status!='paid' AND due_date BETWEEN DATE_ADD(CURDATE(), INTERVAL 31 DAY) AND DATE_ADD(CURDATE(), INTERVAL 60 DAY)")->fetchColumn() ?: 0;
            $cashflow['in_90'] = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status!='paid' AND due_date BETWEEN DATE_ADD(CURDATE(), INTERVAL 61 DAY) AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)")->fetchColumn() ?: 0;
            $cashflow['overdue'] = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status!='paid' AND due_date < CURDATE()")->fetchColumn() ?: 0;
            $current30 = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='paid' AND paid_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND CURDATE()")->fetchColumn() ?: 0;
            $prev30 = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='paid' AND paid_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 60 DAY) AND DATE_SUB(CURDATE(), INTERVAL 31 DAY)")->fetchColumn() ?: 0;
            $cashflow['collection_trend'] = $prev30 > 0 ? round((($current30 - $prev30) / $prev30) * 100, 1) : ($current30 > 0 ? 100 : 0);
        }

        $targetOccupancy = (float) ($settings['target_occupancy'] ?? 90);
        $targetCollection = (float) ($settings['target_collection'] ?? 95);
        $overdueThreshold = (int) ($settings['overdue_threshold'] ?? 5);
        $insights['occupancy_gap'] = round($insights['occupancy_rate'] - $targetOccupancy, 1);
        $insights['collection_gap'] = round($insights['collection_rate'] - $targetCollection, 1);

        if ($insights['occupancy_rate'] < $targetOccupancy && $stats['units'] > 0) {
            $recommendations[] = 'رفع نسبة الإشغال عبر حملات تسويق أو تحسين التسعير.';
        }
        if ($insights['overdue_count'] > $overdueThreshold) {
            $recommendations[] = 'متابعة الدفعات المتأخرة وإرسال تذكيرات واتساب مخصصة.';
        }
        if ($insights['maintenance_pending'] > 3) {
            $recommendations[] = 'تجميع طلبات الصيانة وترتيبها حسب الأولوية لتقليل التكاليف.';
        }
        if (!empty($lists['ending'])) {
            $recommendations[] = 'بدء إجراءات تجديد العقود المنتهية قريباً.';
        }
        if ($insights['collection_rate'] < $targetCollection && $totalInvoiced > 0) {
            $recommendations[] = 'تحسين التحصيل عبر تذكيرات مبكرة وجدولة خطط سداد.';
        }
        if ($maintenancePulse['risk_score'] >= 60) {
            $recommendations[] = 'تفعيل خطة صيانة وقائية للوحدات المتكررة الأعطال.';
        }
        if ($cashflow['overdue'] > 0) {
            $recommendations[] = 'إطلاق حملة تحصيل مركزة للدفعات المتأخرة عبر واتساب.';
        }
        if ($tenantRiskSnapshot['high_risk_count'] > 0) {
            $recommendations[] = 'متابعة المستأجرين مرتفعي المخاطر بجدولة خطط سداد.';
        }
    }
} catch (Exception $e) {
    // في حال حدوث خطأ، سيتم استخدام القيم الصفرية الافتراضية ولن تتوقف الصفحة
}

if (empty($financeLabels)) {
    $monthNames = [
        1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل', 5 => 'مايو', 6 => 'يونيو',
        7 => 'يوليو', 8 => 'أغسطس', 9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'
    ];
    $periodCursor = (new DateTime('first day of this month'))->modify('-5 months');
    for ($i = 0; $i < 6; $i++) {
        $monthIndex = (int) $periodCursor->format('n');
        $financeLabels[] = $monthNames[$monthIndex] . ' ' . $periodCursor->format('Y');
        $financePaid[] = 0;
        $financeExpected[] = 0;
        $periodCursor->modify('+1 month');
    }
}
?>

<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:20px; margin-bottom:30px;">
    
    <div class="card" style="padding:20px; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <h2 style="margin:0; font-size:28px; font-weight:800"><?= $stats['units'] ?></h2>
            <span style="color:#888; font-size:13px">إجمالي الوحدات</span>
        </div>
        <div style="width:50px; height:50px; background:#4f46e5; border-radius:12px; display:flex; align-items:center; justify-content:center; color:white; font-size:24px;">
            <i class="fa-solid fa-building"></i>
        </div>
    </div>

    <div class="card" style="padding:20px; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <h2 style="margin:0; font-size:28px; font-weight:800"><?= $stats['rented'] ?></h2>
            <span style="color:#888; font-size:13px">وحدات مؤجرة</span>
        </div>
        <div style="width:50px; height:50px; background:#0ea5e9; border-radius:12px; display:flex; align-items:center; justify-content:center; color:white; font-size:24px;">
            <i class="fa-solid fa-key"></i>
        </div>
    </div>

    <div class="card" style="padding:20px; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <h2 style="margin:0; font-size:28px; font-weight:800"><?= $stats['contracts'] ?></h2>
            <span style="color:#888; font-size:13px">عقود نشطة</span>
        </div>
        <div style="width:50px; height:50px; background:#10b981; border-radius:12px; display:flex; align-items:center; justify-content:center; color:white; font-size:24px;">
            <i class="fa-solid fa-check-circle"></i>
        </div>
    </div>

    <div class="card" style="padding:20px; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <h2 style="margin:0; font-size:28px; font-weight:800"><?= $stats['tenants'] ?></h2>
            <span style="color:#888; font-size:13px">المستأجرين</span>
        </div>
        <div style="width:50px; height:50px; background:#f59e0b; border-radius:12px; display:flex; align-items:center; justify-content:center; color:white; font-size:24px;">
            <i class="fa-solid fa-users"></i>
        </div>
    </div>
</div>

<div style="display:grid; grid-template-columns: 1.2fr 1fr; gap:20px; margin-bottom:30px;">
    <div class="card" style="padding:20px;">
        <h3 style="margin-top:0"><i class="fa-solid fa-wand-magic-sparkles"></i> لوحة التحكم الذكية للمالك</h3>
        <p style="color:#94a3b8; margin-top:6px;">مسار سريع لإدارة الأملاك بذكاء: تحديث الوحدات، متابعة العقود، وضبط الفواتير في أقل وقت.</p>
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:10px; margin-top:15px;">
            <a href="index.php?p=properties" class="btn btn-dark" style="justify-content:center; width:100%;"><i class="fa-solid fa-city"></i> إدارة العقارات</a>
            <a href="index.php?p=units" class="btn btn-dark" style="justify-content:center; width:100%;"><i class="fa-solid fa-building"></i> إدارة الوحدات</a>
            <a href="index.php?p=contracts" class="btn btn-dark" style="justify-content:center; width:100%;"><i class="fa-solid fa-file-signature"></i> العقود الذكية</a>
            <a href="index.php?p=payments" class="btn btn-dark" style="justify-content:center; width:100%;"><i class="fa-solid fa-money-check-dollar"></i> متابعة الدفعات</a>
            <a href="index.php?p=maintenance" class="btn btn-dark" style="justify-content:center; width:100%;"><i class="fa-solid fa-screwdriver-wrench"></i> طلبات الصيانة</a>
            <a href="index.php?p=smart_center" class="btn btn-primary" style="justify-content:center; width:100%;"><i class="fa-solid fa-brain"></i> مركز التمكين الذكي</a>
        </div>
    </div>
    <div class="card" style="padding:20px;">
        <h3 style="margin-top:0"><i class="fa-solid fa-shield-heart"></i> مساعد الإدارة الذكي</h3>
        <div style="display:grid; gap:10px;">
            <div style="background:#0f172a; padding:12px; border-radius:12px; color:#cbd5f5;">
                ركّز اليوم على تجديد العقود القريبة والانتباه للدفعات المتأخرة.
            </div>
            <div style="background:#0f172a; padding:12px; border-radius:12px; color:#cbd5f5;">
                راقب عدادات الكهرباء والماء لضبط الاستهلاك وتقليل الفاقد.
            </div>
            <div style="background:#0f172a; padding:12px; border-radius:12px; color:#cbd5f5;">
                فعّل تقارير واتساب الذكية للتذكير التلقائي بالمستحقات.
            </div>
        </div>
    </div>
</div>

<div style="display:grid; grid-template-columns: 2fr 1fr; gap:20px; margin-bottom:30px;">
    <div class="card" style="height:350px; padding:20px;">
        <h3 style="margin-top:0"><i class="fa-solid fa-chart-line"></i> الأداء المالي</h3>
        <div style="height:280px; width:100%">
            <canvas id="financeChart"></canvas>
        </div>
    </div>
    
    <div class="card" style="height:350px; padding:20px;">
        <h3 style="margin-top:0"><i class="fa-solid fa-chart-pie"></i> توزيع الوحدات</h3>
        <div style="height:280px; width:100%; display:flex; align-items:center; justify-content:center">
            <canvas id="unitsChart"></canvas>
        </div>
    </div>
</div>

<div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:20px; margin-bottom:30px;">
    
    <div class="card">
        <div style="display:flex; justify-content:space-between; margin-bottom:15px; border-bottom:1px solid #333; padding-bottom:10px;">
            <h4 style="margin:0; color:#6366f1"><i class="fa-solid fa-clock-rotate-left"></i> آخر النشاطات</h4>
        </div>
        <div style="font-size:13px; color:#aaa;">
            <?php if (!empty($recentActivity)): ?>
                <?php foreach ($recentActivity as $log): ?>
                    <div style="padding:10px; border-bottom:1px dashed #333">
                        <i class="fa-solid fa-circle-check" style="color:#10b981"></i>
                        <?= htmlspecialchars($log['description']) ?>
                        <div style="font-size:11px; color:#666; margin-top:2px"><?= $log['created_at'] ?></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="padding:10px; text-align:center; margin-top:20px; color:#666">
                    لا توجد نشاطات أخرى
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div style="display:flex; justify-content:space-between; margin-bottom:15px; border-bottom:1px solid #333; padding-bottom:10px;">
            <h4 style="margin:0; color:#6366f1"><i class="fa-solid fa-clock"></i> عقود تنتهي قريباً</h4>
            <span style="font-size:11px; background:#333; padding:2px 8px; border-radius:4px">عرض الكل</span>
        </div>
        
        <?php if(empty($lists['ending'])): ?>
            <div style="text-align:center; padding:30px; color:#666">
                <i class="fa-solid fa-check-circle" style="font-size:30px; margin-bottom:10px; display:block"></i>
                لا توجد عقود تنتهي قريباً
            </div>
        <?php else: ?>
            <?php foreach($lists['ending'] as $c): ?>
                <div style="padding:10px; border-bottom:1px dashed #333; display:flex; justify-content:space-between">
                    <span><?= htmlspecialchars($c['tenant_name']) ?></span>
                    <span style="color:#ef4444; font-size:12px"><?= $c['end_date'] ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="card">
        <div style="display:flex; justify-content:space-between; margin-bottom:15px; border-bottom:1px solid #333; padding-bottom:10px;">
            <h4 style="margin:0; color:#6366f1"><i class="fa-solid fa-calendar-days"></i> دفعات قادمة</h4>
            <span style="font-size:11px; background:#333; padding:2px 8px; border-radius:4px">عرض الكل</span>
        </div>

        <?php if(empty($lists['payments'])): ?>
            <div style="text-align:center; padding:30px; color:#666">
                <i class="fa-solid fa-check-circle" style="font-size:30px; margin-bottom:10px; display:block"></i>
                لا توجد دفعات قادمة خلال 30 يوم
            </div>
        <?php else: ?>
            <?php foreach($lists['payments'] as $p): ?>
                <div style="padding:10px; border-bottom:1px dashed #333; display:flex; justify-content:space-between">
                    <span>دفعة عقد #<?= $p['contract_id'] ?></span>
                    <span style="color:#10b981; font-weight:bold"><?= number_format($p['amount']) ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="margin-bottom:30px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px">
        <h3 style="margin:0"><i class="fa-solid fa-brain"></i> مركز الذكاء التشغيلي</h3>
        <span style="font-size:11px; background:#312e81; padding:2px 8px; border-radius:4px">WhatsApp فقط للتكاملات</span>
    </div>

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:15px; margin-bottom:20px;">
        <div style="background:#111827; padding:15px; border-radius:12px;">
            <div style="font-size:12px; color:#9ca3af">نسبة الإشغال</div>
            <div style="font-size:24px; font-weight:700"><?= $insights['occupancy_rate'] ?>%</div>
        </div>
        <div style="background:#111827; padding:15px; border-radius:12px;">
            <div style="font-size:12px; color:#9ca3af">هدف الإشغال</div>
            <div style="font-size:24px; font-weight:700"><?= number_format((float) ($settings['target_occupancy'] ?? 90), 1) ?>%</div>
            <div style="font-size:12px; color:<?= ($insights['occupancy_gap'] ?? 0) >= 0 ? '#10b981' : '#f97316' ?>">فرق <?= $insights['occupancy_gap'] ?? 0 ?>%</div>
        </div>
        <div style="background:#111827; padding:15px; border-radius:12px;">
            <div style="font-size:12px; color:#9ca3af">معدل التحصيل</div>
            <div style="font-size:24px; font-weight:700"><?= $insights['collection_rate'] ?>%</div>
        </div>
        <div style="background:#111827; padding:15px; border-radius:12px;">
            <div style="font-size:12px; color:#9ca3af">هدف التحصيل</div>
            <div style="font-size:24px; font-weight:700"><?= number_format((float) ($settings['target_collection'] ?? 95), 1) ?>%</div>
            <div style="font-size:12px; color:<?= ($insights['collection_gap'] ?? 0) >= 0 ? '#10b981' : '#f97316' ?>">فرق <?= $insights['collection_gap'] ?? 0 ?>%</div>
        </div>
        <div style="background:#111827; padding:15px; border-radius:12px;">
            <div style="font-size:12px; color:#9ca3af">توقع تحصيل 30 يوماً</div>
            <div style="font-size:24px; font-weight:700"><?= number_format($insights['expected_30']) ?></div>
        </div>
        <div style="background:#111827; padding:15px; border-radius:12px;">
            <div style="font-size:12px; color:#9ca3af">متوسط التحصيل الشهري (3 أشهر)</div>
            <div style="font-size:24px; font-weight:700"><?= number_format($insights['avg_paid_3m']) ?></div>
        </div>
        <div style="background:#111827; padding:15px; border-radius:12px;">
            <div style="font-size:12px; color:#9ca3af">توقع تحصيل 90 يوماً</div>
            <div style="font-size:24px; font-weight:700"><?= number_format($cashflow['in_90']) ?></div>
        </div>
        <div style="background:#111827; padding:15px; border-radius:12px;">
            <div style="font-size:12px; color:#9ca3af">المتأخرات الحالية</div>
            <div style="font-size:24px; font-weight:700"><?= number_format($cashflow['overdue']) ?></div>
        </div>
        <div style="background:#111827; padding:15px; border-radius:12px;">
            <div style="font-size:12px; color:#9ca3af">اتجاه التحصيل (30 يوم)</div>
            <div style="font-size:24px; font-weight:700"><?= number_format($cashflow['collection_trend'], 1) ?>%</div>
        </div>
        <div style="background:#111827; padding:15px; border-radius:12px;">
            <div style="font-size:12px; color:#9ca3af">مؤشر مخاطر الصيانة</div>
            <div style="font-size:24px; font-weight:700"><?= $maintenancePulse['risk_score'] ?>/100</div>
        </div>
        <div style="background:#111827; padding:15px; border-radius:12px;">
            <div style="font-size:12px; color:#9ca3af">مستأجرون عالي المخاطر</div>
            <div style="font-size:24px; font-weight:700"><?= $tenantRiskSnapshot['high_risk_count'] ?></div>
        </div>
    </div>

    <div style="display:grid; grid-template-columns: 1.2fr 1fr; gap:20px;">
        <div style="background:#0f172a; padding:15px; border-radius:12px;">
            <h4 style="margin-top:0; color:#a5b4fc"><i class="fa-solid fa-list-check"></i> توصيات فورية</h4>
            <?php if (!empty($recommendations)): ?>
                <ul style="padding-inline-start:18px; color:#cbd5f5; margin:0">
                    <?php foreach ($recommendations as $rec): ?>
                        <li style="margin-bottom:8px;"><?= htmlspecialchars($rec) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div style="color:#94a3b8">كل المؤشرات ضمن الحدود الطبيعية.</div>
            <?php endif; ?>
        </div>
    <div style="background:#0f172a; padding:15px; border-radius:12px;">
        <h4 style="margin-top:0; color:#a5b4fc"><i class="fa-solid fa-triangle-exclamation"></i> أعلى مخاطر التعثر</h4>
        <?php if (!empty($riskTenants)): ?>
            <?php foreach ($riskTenants as $tenant): ?>
                <div style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px dashed #1f2937;">
                    <span><?= htmlspecialchars($tenant['name']) ?></span>
                    <span style="color:#f97316">متأخر <?= (int) $tenant['overdue_count'] ?> (<?= (int) $tenant['max_overdue_days'] ?> يوم)</span>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="color:#94a3b8">لا توجد حالات تعثر حالياً.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // التحقق من وجود الكانفاس قبل الرسم
    const financeCtx = document.getElementById('financeChart');
    const unitsCtx = document.getElementById('unitsChart');

    if (financeCtx) {
        const financeLabels = <?= json_encode($financeLabels, JSON_UNESCAPED_UNICODE) ?>;
        const financePaid = <?= json_encode($financePaid) ?>;
        const financeExpected = <?= json_encode($financeExpected) ?>;
        new Chart(financeCtx, {
            type: 'line',
            data: {
                labels: financeLabels,
                datasets: [{
                    label: 'التحصيل الشهري',
                    data: financePaid,
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99,102,241,0.1)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 3
                }, {
                    label: 'المتوقع تحصيله',
                    data: financeExpected,
                    borderColor: '#22c55e',
                    backgroundColor: 'rgba(34,197,94,0.08)',
                    borderDash: [6, 6],
                    fill: false,
                    tension: 0.35,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { color: '#e5e7eb', padding: 16 } } },
                scales: {
                    y: { grid: { color: '#333' }, ticks: { color: '#888' } },
                    x: { grid: { display: false }, ticks: { color: '#888' } }
                }
            }
        });
    }

    if (unitsCtx) {
        new Chart(unitsCtx, {
            type: 'doughnut',
            data: {
                labels: ['مؤجر', 'شاغر'],
                datasets: [{
                    data: [<?= $stats['rented'] ?>, <?= ($stats['units'] - $stats['rented']) ?>],
                    backgroundColor: ['#10b981', '#1f2937'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: { position: 'bottom', labels: { color: '#fff', padding: 20 } }
                }
            }
        });
    }
});
</script>
