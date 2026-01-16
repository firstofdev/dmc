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
$portfolioMetrics = [
    'vacant_units' => 0,
    'vacancy_rate' => 0,
    'paid_30' => 0,
    'paid_per_rented' => 0,
    'avg_payment' => 0,
    'renewals_30' => 0,
    'renewals_60' => 0,
    'overdue_ratio' => 0,
];
$dataQuality = [
    'tenants_missing_contact' => 0,
    'units_missing_price' => 0,
    'contracts_missing_payments' => 0,
    'properties_without_units' => 0,
    'score' => 100,
];
$qualityActions = [];
$paymentAging = [
    'current' => 0,
    'late_1_30' => 0,
    'late_31_60' => 0,
    'late_61_plus' => 0,
    'overdue_total' => 0,
];
$propertyPerformance = [];
$smartAlerts = [];
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

        $paidDateColumn = table_has_column($pdo, 'payments', 'paid_date') ? 'paid_date' : 'due_date';
        $portfolioMetrics['paid_30'] = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='paid' AND {$paidDateColumn} BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND CURDATE()")->fetchColumn() ?: 0;
        $portfolioMetrics['avg_payment'] = $pdo->query("SELECT COALESCE(AVG(amount),0) FROM payments")->fetchColumn() ?: 0;
        $portfolioMetrics['renewals_30'] = $pdo->query("SELECT COUNT(*) FROM contracts WHERE end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)")->fetchColumn() ?: 0;
        $portfolioMetrics['renewals_60'] = $pdo->query("SELECT COUNT(*) FROM contracts WHERE end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY)")->fetchColumn() ?: 0;

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
        $propertyPerformance = $pdo->query("SELECT p.name,
            COUNT(u.id) AS units_total,
            SUM(CASE WHEN u.status='rented' THEN 1 ELSE 0 END) AS units_rented
            FROM properties p
            LEFT JOIN units u ON u.property_id = p.id
            GROUP BY p.id
            ORDER BY units_total DESC
            LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
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
        $portfolioMetrics['vacant_units'] = max($stats['units'] - $stats['rented'], 0);
        $portfolioMetrics['vacancy_rate'] = $stats['units'] > 0 ? round(($portfolioMetrics['vacant_units'] / $stats['units']) * 100, 1) : 0;
        $portfolioMetrics['paid_per_rented'] = $stats['rented'] > 0 ? round($portfolioMetrics['paid_30'] / $stats['rented'], 1) : 0;
        $portfolioMetrics['overdue_ratio'] = $totalInvoiced > 0 ? round(($cashflow['overdue'] / $totalInvoiced) * 100, 1) : 0;
        $insights['occupancy_gap'] = round($insights['occupancy_rate'] - $targetOccupancy, 1);
        $insights['collection_gap'] = round($insights['collection_rate'] - $targetCollection, 1);

        $phoneMissing = "(phone IS NULL OR phone = '')";
        $contactConditions = [$phoneMissing];
        if (table_has_column($pdo, 'tenants', 'email')) {
            $contactConditions[] = "(email IS NULL OR email = '')";
        }
        $dataQuality['tenants_missing_contact'] = $pdo->query("SELECT COUNT(*) FROM tenants WHERE " . implode(' OR ', $contactConditions))->fetchColumn() ?: 0;
        $dataQuality['units_missing_price'] = $pdo->query("SELECT COUNT(*) FROM units WHERE yearly_price IS NULL OR yearly_price <= 0")->fetchColumn() ?: 0;
        $dataQuality['contracts_missing_payments'] = $pdo->query("SELECT COUNT(*) FROM contracts c LEFT JOIN payments p ON p.contract_id = c.id WHERE p.id IS NULL")->fetchColumn() ?: 0;
        $dataQuality['properties_without_units'] = $pdo->query("SELECT COUNT(*) FROM properties p LEFT JOIN units u ON u.property_id = p.id WHERE u.id IS NULL")->fetchColumn() ?: 0;
        $issueScore = min(100, ($dataQuality['tenants_missing_contact'] * 3)
            + ($dataQuality['units_missing_price'] * 4)
            + ($dataQuality['contracts_missing_payments'] * 8)
            + ($dataQuality['properties_without_units'] * 5));
        $dataQuality['score'] = max(0, 100 - $issueScore);

        if ($dataQuality['tenants_missing_contact'] > 0) {
            $qualityActions[] = 'استكمال بيانات الاتصال للمستأجرين لتسهيل التنبيهات والتحصيل.';
        }
        if ($dataQuality['units_missing_price'] > 0) {
            $qualityActions[] = 'تحديث أسعار الوحدات لضمان صحة تقارير الدخل.';
        }
        if ($dataQuality['contracts_missing_payments'] > 0) {
            $qualityActions[] = 'إنشاء جداول الدفعات للعقود غير المولدة لضبط التدفق النقدي.';
        }
        if ($dataQuality['properties_without_units'] > 0) {
            $qualityActions[] = 'إضافة وحدات للعقارات الخالية لزيادة فرص الإشغال.';
        }

        $paymentAging['current'] = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status!='paid' AND due_date >= CURDATE()")->fetchColumn() ?: 0;
        $paymentAging['late_1_30'] = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status!='paid' AND due_date < CURDATE() AND due_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn() ?: 0;
        $paymentAging['late_31_60'] = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status!='paid' AND due_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND due_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)")->fetchColumn() ?: 0;
        $paymentAging['late_61_plus'] = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status!='paid' AND due_date < DATE_SUB(CURDATE(), INTERVAL 60 DAY)")->fetchColumn() ?: 0;
        $paymentAging['overdue_total'] = $paymentAging['late_1_30'] + $paymentAging['late_31_60'] + $paymentAging['late_61_plus'];

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
        if ($dataQuality['score'] < 85) {
            $recommendations[] = 'تحسين جودة البيانات لرفع كفاءة التنبيهات والتقارير الذكية.';
        }

        if ($portfolioMetrics['vacancy_rate'] >= 20) {
            $smartAlerts[] = 'نسبة الشواغر مرتفعة، فعّل عروض التأجير السريع للوحدات الشاغرة.';
        }
        if ($portfolioMetrics['renewals_30'] > 0) {
            $smartAlerts[] = 'يوجد عقود تنتهي خلال 30 يوماً، جهّز عروض تجديد مخصصة.';
        }
        if ($portfolioMetrics['overdue_ratio'] >= 15) {
            $smartAlerts[] = 'نسبة المتأخرات مرتفعة، راجع خطط التحصيل وجدولة المدفوعات.';
        }
        if ($maintenancePulse['emergency'] ?? 0) {
            $smartAlerts[] = 'هناك طلبات صيانة عاجلة، رتب الأولويات مع المقاولين.';
        }
        if ($paymentAging['late_61_plus'] > 0) {
            $smartAlerts[] = 'جزء من المتأخرات تجاوز 60 يوماً، فعّل خطة تحصيل خاصة.';
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
    
    <div class="card" style="padding:20px; display:flex; justify-content:space-between; align-items:center; position:relative; overflow:hidden;">
        <div style="position:absolute; top:-20px; right:-20px; width:120px; height:120px; background:radial-gradient(circle, rgba(79,70,229,0.15), transparent); border-radius:50%; filter:blur(25px);"></div>
        <div style="position:relative; z-index:1;">
            <h2 style="margin:0; font-size:32px; font-weight:800; background:linear-gradient(135deg, #6366f1, #a855f7); -webkit-background-clip:text; -webkit-text-fill-color:transparent;"><?= $stats['units'] ?></h2>
            <span class="theme-text-muted" style="font-size:13px; font-weight:600; letter-spacing:0.5px;">إجمالي الوحدات</span>
        </div>
        <div style="width:60px; height:60px; background:linear-gradient(135deg, #4f46e5, #6366f1); border-radius:16px; display:flex; align-items:center; justify-content:center; color:white; font-size:28px; box-shadow:0 10px 30px rgba(79,70,229,0.4); transition:all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1); position:relative; z-index:1;">
            <i class="fa-solid fa-house-laptop" style="animation:iconFloat 3s ease-in-out infinite;"></i>
        </div>
    </div>

    <div class="card" style="padding:20px; display:flex; justify-content:space-between; align-items:center; position:relative; overflow:hidden;">
        <div style="position:absolute; top:-20px; right:-20px; width:120px; height:120px; background:radial-gradient(circle, rgba(14,165,233,0.15), transparent); border-radius:50%; filter:blur(25px);"></div>
        <div style="position:relative; z-index:1;">
            <h2 style="margin:0; font-size:32px; font-weight:800; background:linear-gradient(135deg, #0ea5e9, #06b6d4); -webkit-background-clip:text; -webkit-text-fill-color:transparent;"><?= $stats['rented'] ?></h2>
            <span class="theme-text-muted" style="font-size:13px; font-weight:600; letter-spacing:0.5px;">وحدات مؤجرة</span>
        </div>
        <div style="width:60px; height:60px; background:linear-gradient(135deg, #0ea5e9, #06b6d4); border-radius:16px; display:flex; align-items:center; justify-content:center; color:white; font-size:28px; box-shadow:0 10px 30px rgba(14,165,233,0.4); transition:all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1); position:relative; z-index:1;">
            <i class="fa-solid fa-house-circle-check" style="animation:iconFloat 3s ease-in-out infinite 0.2s;"></i>
        </div>
    </div>

    <div class="card" style="padding:20px; display:flex; justify-content:space-between; align-items:center; position:relative; overflow:hidden;">
        <div style="position:absolute; top:-20px; right:-20px; width:120px; height:120px; background:radial-gradient(circle, rgba(16,185,129,0.15), transparent); border-radius:50%; filter:blur(25px);"></div>
        <div style="position:relative; z-index:1;">
            <h2 style="margin:0; font-size:32px; font-weight:800; background:linear-gradient(135deg, #10b981, #059669); -webkit-background-clip:text; -webkit-text-fill-color:transparent;"><?= $stats['contracts'] ?></h2>
            <span class="theme-text-muted" style="font-size:13px; font-weight:600; letter-spacing:0.5px;">عقود نشطة</span>
        </div>
        <div style="width:60px; height:60px; background:linear-gradient(135deg, #10b981, #059669); border-radius:16px; display:flex; align-items:center; justify-content:center; color:white; font-size:28px; box-shadow:0 10px 30px rgba(16,185,129,0.4); transition:all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1); position:relative; z-index:1;">
            <i class="fa-solid fa-file-signature" style="animation:iconFloat 3s ease-in-out infinite 0.4s;"></i>
        </div>
    </div>

    <div class="card" style="padding:20px; display:flex; justify-content:space-between; align-items:center; position:relative; overflow:hidden;">
        <div style="position:absolute; top:-20px; right:-20px; width:120px; height:120px; background:radial-gradient(circle, rgba(245,158,11,0.15), transparent); border-radius:50%; filter:blur(25px);"></div>
        <div style="position:relative; z-index:1;">
            <h2 style="margin:0; font-size:32px; font-weight:800; background:linear-gradient(135deg, #f59e0b, #d97706); -webkit-background-clip:text; -webkit-text-fill-color:transparent;"><?= $stats['tenants'] ?></h2>
            <span class="theme-text-muted" style="font-size:13px; font-weight:600; letter-spacing:0.5px;">المستأجرين</span>
        </div>
        <div style="width:60px; height:60px; background:linear-gradient(135deg, #f59e0b, #d97706); border-radius:16px; display:flex; align-items:center; justify-content:center; color:white; font-size:28px; box-shadow:0 10px 30px rgba(245,158,11,0.4); transition:all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1); position:relative; z-index:1;">
            <i class="fa-solid fa-user-group" style="animation:iconFloat 3s ease-in-out infinite 0.6s;"></i>
        </div>
    </div>
</div>

<style>
@keyframes iconFloat {
    0%, 100% { transform: translateY(0) scale(1); }
    50% { transform: translateY(-6px) scale(1.05); }
}
</style>

<!-- Late Payments Alert Section -->
<?php 
$overduePayments = $pdo->query("
    SELECT p.*, c.id as contract_id, t.name as tenant_name, t.phone as tenant_phone,
           u.unit_name, pr.name as property_name,
           DATEDIFF(CURDATE(), p.due_date) as days_overdue
    FROM payments p
    JOIN contracts c ON p.contract_id = c.id
    JOIN tenants t ON c.tenant_id = t.id
    JOIN units u ON c.unit_id = u.id
    JOIN properties pr ON u.property_id = pr.id
    WHERE p.status != 'paid' AND p.due_date < CURDATE()
    ORDER BY p.due_date ASC
    LIMIT 10
")->fetchAll();

if (count($overduePayments) > 0):
?>
<div class="card" style="margin-bottom:30px; background:linear-gradient(135deg, rgba(239,68,68,0.1), rgba(220,38,38,0.05)); border-right:4px solid #ef4444;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
        <h3 style="margin:0; display:flex; align-items:center; gap:10px; color:#ef4444;">
            <i class="fa-solid fa-exclamation-triangle"></i> تنبيه: دفعات متأخرة (<?= count($overduePayments) ?>)
        </h3>
        <a href="index.php?p=payments&status=pending" class="btn btn-danger btn-sm">
            <i class="fa-solid fa-eye"></i> عرض الكل
        </a>
    </div>
    
    <div style="display:grid; gap:12px;">
        <?php foreach ($overduePayments as $op): 
            $urgencyClass = $op['days_overdue'] > 30 ? 'critical' : ($op['days_overdue'] > 14 ? 'high' : 'medium');
            $urgencyColor = $op['days_overdue'] > 30 ? '#dc2626' : ($op['days_overdue'] > 14 ? '#ea580c' : '#f59e0b');
        ?>
        <div style="background:rgba(0,0,0,0.2); padding:15px; border-radius:10px; display:flex; justify-content:space-between; align-items:center; border-right:3px solid <?= $urgencyColor ?>;">
            <div style="flex:1;">
                <div style="font-weight:bold; font-size:15px; margin-bottom:5px;">
                    <?= htmlspecialchars($op['tenant_name']) ?> - <?= htmlspecialchars($op['unit_name']) ?>
                </div>
                <div style="color:#94a3b8; font-size:13px; margin-bottom:5px;">
                    <i class="fa-solid fa-building"></i> <?= htmlspecialchars($op['property_name']) ?>
                </div>
                <div style="display:flex; gap:15px; font-size:13px;">
                    <span style="color:#fbbf24;">
                        <i class="fa-solid fa-calendar-xmark"></i> متأخر <?= $op['days_overdue'] ?> يوم
                    </span>
                    <span style="color:#a78bfa;">
                        <i class="fa-solid fa-money-bill"></i> <?= number_format($op['amount'], 2) ?> ر.س
                    </span>
                </div>
            </div>
            <div style="display:flex; gap:8px;">
                <?php if (!empty($op['tenant_phone']) && is_whatsapp_configured()): ?>
                <button onclick="sendWhatsAppReminder(<?= $op['id'] ?>, '<?= htmlspecialchars($op['tenant_phone']) ?>', '<?= htmlspecialchars($op['tenant_name']) ?>', <?= $op['amount'] ?>)" 
                        class="btn btn-success btn-sm" title="إرسال تذكير واتساب">
                    <i class="fa-brands fa-whatsapp"></i>
                </button>
                <?php endif; ?>
                <a href="index.php?p=payments" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-dollar-sign"></i> دفع
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
function sendWhatsAppReminder(paymentId, phone, tenantName, amount) {
    if (confirm('هل تريد إرسال تذكير واتساب إلى ' + tenantName + '؟')) {
        const message = `مرحباً ${tenantName},\n\nهذا تذكير بدفعة الإيجار المتأخرة:\n\nالمبلغ: ${amount.toFixed(2)} ر.س\n\nيرجى سداد المبلغ في أقرب وقت ممكن.\n\nشكراً لتعاونكم`;
        
        // Send via AJAX
        fetch('routes/whatsapp_send.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                phone: phone,
                message: message,
                payment_id: paymentId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('تم إرسال التذكير بنجاح عبر واتساب');
            } else {
                alert('حدث خطأ: ' + data.error);
            }
        })
        .catch(error => {
            alert('حدث خطأ في الإرسال');
        });
    }
}
</script>
<?php endif; ?>

<div style="display:grid; grid-template-columns: 1.2fr 1fr; gap:20px; margin-bottom:30px;">
    <div class="card" style="padding:20px; background:linear-gradient(135deg, rgba(99,102,241,0.05), rgba(168,85,247,0.03));">
        <h3 style="margin-top:0; display:flex; align-items:center; gap:10px;"><i class="fa-solid fa-wand-magic-sparkles" style="color:#a855f7;"></i> لوحة التحكم الذكية للمالك</h3>
        <p class="theme-text-secondary" style="margin-top:6px;">مسار سريع لإدارة الأملاك بذكاء: تحديث الوحدات، متابعة العقود، وضبط الفواتير في أقل وقت.</p>
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:10px; margin-top:15px;">
            <a href="index.php?p=properties" class="btn btn-dark" style="justify-content:center; width:100%;"><i class="fa-solid fa-building"></i> <span>إدارة العقارات</span></a>
            <a href="index.php?p=units" class="btn btn-dark" style="justify-content:center; width:100%;"><i class="fa-solid fa-house-laptop"></i> <span>إدارة الوحدات</span></a>
            <a href="index.php?p=contracts" class="btn btn-dark" style="justify-content:center; width:100%;"><i class="fa-solid fa-file-signature"></i> <span>العقود الذكية</span></a>
            <a href="index.php?p=reports" class="btn btn-dark" style="justify-content:center; width:100%;"><i class="fa-solid fa-file-invoice-dollar"></i> <span>التقارير المالية</span></a>
            <a href="index.php?p=lease_calendar" class="btn btn-dark" style="justify-content:center; width:100%;"><i class="fa-solid fa-calendar-check"></i> <span>تقويم العقود</span></a>
            <a href="index.php?p=smart_center" class="btn btn-primary" style="justify-content:center; width:100%;"><i class="fa-solid fa-microchip"></i> <span>التمكين الذكي</span></a>
        </div>
    </div>
    <div class="card" style="padding:20px; background:linear-gradient(135deg, rgba(34,211,238,0.05), rgba(14,165,233,0.03));">
        <h3 style="margin-top:0; display:flex; align-items:center; gap:10px;"><i class="fa-solid fa-lightbulb" style="color:#22d3ee;"></i> مساعد الإدارة الذكي</h3>
        <div style="display:grid; gap:10px;">
            <div class="theme-bg-card" style="padding:14px; border-radius:12px; border-left:3px solid #6366f1; display:flex; align-items:center; gap:10px; transition:all 0.3s ease;">
                <i class="fa-solid fa-sync-alt" style="color:#6366f1; font-size:18px;"></i>
                <span>ركّز اليوم على تجديد العقود القريبة والانتباه للدفعات المتأخرة.</span>
            </div>
            <div class="theme-bg-card" style="padding:14px; border-radius:12px; border-left:3px solid #10b981; display:flex; align-items:center; gap:10px; transition:all 0.3s ease;">
                <i class="fa-solid fa-bolt" style="color:#10b981; font-size:18px;"></i>
                <span>راقب عدادات الكهرباء والماء لضبط الاستهلاك وتقليل الفاقد.</span>
            </div>
            <div class="theme-bg-card" style="padding:14px; border-radius:12px; border-left:3px solid #22d3ee; display:flex; align-items:center; gap:10px; transition:all 0.3s ease;">
                <i class="fa-brands fa-whatsapp" style="color:#22d3ee; font-size:18px;"></i>
                <span>فعّل تقارير واتساب الذكية للتذكير التلقائي بالمستحقات.</span>
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

<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:20px; margin-bottom:30px;">
    <div class="card" style="padding:20px;">
        <h4 class="theme-text-accent" style="margin-top:0; display:flex; align-items:center; gap:8px;"><i class="fa-solid fa-gauge-high"></i> نبض المحفظة</h4>
        <div class="theme-text-light" style="display:grid; gap:12px;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <span style="display:flex; align-items:center; gap:8px;"><i class="fa-solid fa-house-crack" style="color:#6366f1; font-size:14px;"></i> وحدات شاغرة</span>
                <strong><?= $portfolioMetrics['vacant_units'] ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <span style="display:flex; align-items:center; gap:8px;"><i class="fa-solid fa-chart-pie" style="color:#a855f7; font-size:14px;"></i> معدل الشواغر</span>
                <strong><?= $portfolioMetrics['vacancy_rate'] ?>%</strong>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <span style="display:flex; align-items:center; gap:8px;"><i class="fa-solid fa-money-bill-trend-up" style="color:#10b981; font-size:14px;"></i> تحصيل آخر 30 يوم</span>
                <strong><?= number_format($portfolioMetrics['paid_30']) ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <span style="display:flex; align-items:center; gap:8px;"><i class="fa-solid fa-calculator" style="color:#22d3ee; font-size:14px;"></i> متوسط التحصيل للوحدة</span>
                <strong><?= number_format($portfolioMetrics['paid_per_rented']) ?></strong>
            </div>
        </div>
    </div>
    <div class="card" style="padding:20px;">
        <h4 class="theme-text-accent" style="margin-top:0; display:flex; align-items:center; gap:8px;"><i class="fa-solid fa-rotate"></i> دوران العقود</h4>
        <div class="theme-text-light" style="display:grid; gap:12px;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <span style="display:flex; align-items:center; gap:8px;"><i class="fa-solid fa-calendar-days" style="color:#f59e0b; font-size:14px;"></i> تجديد خلال 30 يوم</span>
                <strong><?= $portfolioMetrics['renewals_30'] ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <span style="display:flex; align-items:center; gap:8px;"><i class="fa-solid fa-calendar-week" style="color:#f59e0b; font-size:14px;"></i> تجديد خلال 60 يوم</span>
                <strong><?= $portfolioMetrics['renewals_60'] ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <span style="display:flex; align-items:center; gap:8px;"><i class="fa-solid fa-money-check-dollar" style="color:#10b981; font-size:14px;"></i> متوسط قيمة الدفعة</span>
                <strong><?= number_format($portfolioMetrics['avg_payment']) ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <span style="display:flex; align-items:center; gap:8px;"><i class="fa-solid fa-clock-rotate-left" style="color:#ef4444; font-size:14px;"></i> نسبة المتأخرات</span>
                <strong><?= $portfolioMetrics['overdue_ratio'] ?>%</strong>
            </div>
        </div>
    </div>
    <div class="card" style="padding:20px;">
        <h4 class="theme-text-accent" style="margin-top:0; display:flex; align-items:center; gap:8px;"><i class="fa-solid fa-sliders"></i> إجراءات سريعة</h4>
        <div style="display:grid; gap:10px;">
            <a href="index.php?p=contracts" class="btn btn-dark" style="justify-content:center;"><i class="fa-solid fa-file-pen"></i> <span>متابعة التجديدات</span></a>
            <a href="index.php?p=tenants" class="btn btn-dark" style="justify-content:center;"><i class="fa-solid fa-user-check"></i> <span>مراجعة المستأجرين</span></a>
            <a href="index.php?p=maintenance" class="btn btn-dark" style="justify-content:center;"><i class="fa-solid fa-toolbox"></i> <span>تنظيم الصيانة</span></a>
            <a href="index.php?p=alerts" class="btn btn-primary" style="justify-content:center;"><i class="fa-solid fa-bell-concierge"></i> <span>تفعيل التنبيهات</span></a>
        </div>
    </div>
</div>

<div style="display:grid; grid-template-columns: 1.2fr 1fr; gap:20px; margin-bottom:30px;">
    <div class="card" style="padding:20px;">
        <h4 class="theme-text-accent" style="margin-top:0; display:flex; align-items:center; gap:8px;"><i class="fa-solid fa-layer-group"></i> توزيع المتأخرات حسب العمر</h4>
        <div class="theme-text-light" style="display:grid; gap:12px;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <span style="display:flex; align-items:center; gap:8px;"><i class="fa-solid fa-check-circle" style="color:#10b981; font-size:14px;"></i> مستحقات حالية</span>
                <strong><?= number_format($paymentAging['current']) ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <span style="display:flex; align-items:center; gap:8px;"><i class="fa-solid fa-clock" style="color:#f59e0b; font-size:14px;"></i> متأخرات 1-30 يوماً</span>
                <strong><?= number_format($paymentAging['late_1_30']) ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <span style="display:flex; align-items:center; gap:8px;"><i class="fa-solid fa-hourglass-half" style="color:#f97316; font-size:14px;"></i> متأخرات 31-60 يوماً</span>
                <strong><?= number_format($paymentAging['late_31_60']) ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <span style="display:flex; align-items:center; gap:8px;"><i class="fa-solid fa-exclamation-triangle" style="color:#ef4444; font-size:14px;"></i> متأخرات أكثر من 60 يوماً</span>
                <strong><?= number_format($paymentAging['late_61_plus']) ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center; padding-top:8px; border-top:1px solid rgba(148,163,184,0.2);">
                <span style="display:flex; align-items:center; gap:8px; font-weight:700;"><i class="fa-solid fa-calculator" style="color:#f97316; font-size:14px;"></i> إجمالي المتأخرات</span>
                <strong style="color:#f97316"><?= number_format($paymentAging['overdue_total']) ?></strong>
            </div>
        </div>
    </div>
    <div class="card" style="padding:20px;">
        <h4 class="theme-text-accent" style="margin-top:0; display:flex; align-items:center; gap:8px;"><i class="fa-solid fa-clipboard-check"></i> جودة البيانات والجاهزية</h4>
        <div class="theme-text-light" style="display:grid; gap:12px;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <span style="display:flex; align-items:center; gap:8px;"><i class="fa-solid fa-user-slash" style="color:#ef4444; font-size:14px;"></i> مستأجرون ببيانات ناقصة</span>
                <strong><?= $dataQuality['tenants_missing_contact'] ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <span style="display:flex; align-items:center; gap:8px;"><i class="fa-solid fa-tag" style="color:#f59e0b; font-size:14px;"></i> وحدات بدون سعر</span>
                <strong><?= $dataQuality['units_missing_price'] ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <span style="display:flex; align-items:center; gap:8px;"><i class="fa-solid fa-file-circle-xmark" style="color:#f97316; font-size:14px;"></i> عقود بلا جداول دفعات</span>
                <strong><?= $dataQuality['contracts_missing_payments'] ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <span style="display:flex; align-items:center; gap:8px;"><i class="fa-solid fa-building-slash" style="color:#64748b; font-size:14px;"></i> عقارات بلا وحدات</span>
                <strong><?= $dataQuality['properties_without_units'] ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center; padding-top:8px; border-top:1px solid rgba(148,163,184,0.2);">
                <span style="display:flex; align-items:center; gap:8px; font-weight:700;"><i class="fa-solid fa-star" style="color:<?= $dataQuality['score'] >= 85 ? '#10b981' : '#f59e0b' ?>; font-size:14px;"></i> مؤشر الجاهزية</span>
                <strong style="color:<?= $dataQuality['score'] >= 85 ? '#10b981' : '#f59e0b' ?>"><?= $dataQuality['score'] ?>/100</strong>
            </div>
        </div>
        <?php if (!empty($qualityActions)): ?>
            <div style="margin-top:12px; background:#111827; border-radius:12px; padding:12px; color:#cbd5f5; font-size:13px; border-left:3px solid #6366f1;">
                <strong style="display:flex; align-items:center; gap:8px; margin-bottom:6px;"><i class="fa-solid fa-list-check"></i> خطوات مقترحة</strong>
                <ul style="padding-inline-start:18px; margin:0;">
                    <?php foreach ($qualityActions as $action): ?>
                        <li style="margin-bottom:6px;"><?= htmlspecialchars($action) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>

<div style="display:grid; grid-template-columns: 1.2fr 1fr; gap:20px; margin-bottom:30px;">
    <div class="card" style="padding:20px;">
        <h3 style="margin-top:0; display:flex; align-items:center; gap:10px;"><i class="fa-solid fa-warehouse" style="color:#6366f1;"></i> أداء العقارات الأعلى نشاطاً</h3>
        <?php if (!empty($propertyPerformance)): ?>
            <?php foreach ($propertyPerformance as $property): ?>
                <?php
                $totalUnits = (int) ($property['units_total'] ?? 0);
                $rentedUnits = (int) ($property['units_rented'] ?? 0);
                $rate = $totalUnits > 0 ? round(($rentedUnits / $totalUnits) * 100, 1) : 0;
                ?>
                <div style="padding:14px 0; border-bottom:1px dashed #333; transition:all 0.3s ease;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <strong style="display:flex; align-items:center; gap:8px;"><i class="fa-solid fa-building-circle-check" style="color:#10b981; font-size:14px;"></i> <?= htmlspecialchars($property['name']) ?></strong>
                        <span style="color:#94a3b8; font-size:12px; background:rgba(99,102,241,0.1); padding:4px 10px; border-radius:8px;"><i class="fa-solid fa-chart-simple"></i> <?= $rentedUnits ?>/<?= $totalUnits ?> مؤجر</span>
                    </div>
                    <div style="margin-top:8px; background:#1f2937; border-radius:10px; overflow:hidden; position:relative;">
                        <div style="height:8px; width:<?= $rate ?>%; background:linear-gradient(90deg, #10b981, #059669); border-radius:10px; box-shadow:0 0 10px rgba(16,185,129,0.5); transition:all 0.8s ease;"></div>
                    </div>
                    <div style="margin-top:4px; color:#94a3b8; font-size:11px; text-align:left;"><?= $rate ?>% إشغال</div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="color:#94a3b8; text-align:center; padding:30px; display:flex; flex-direction:column; align-items:center; gap:10px;">
                <i class="fa-solid fa-building-slash" style="font-size:40px; opacity:0.3;"></i>
                <span>أضف عقارات لعرض الأداء التفصيلي.</span>
            </div>
        <?php endif; ?>
    </div>
    <div class="card" style="padding:20px; background:linear-gradient(135deg, rgba(168,85,247,0.05), rgba(99,102,241,0.03));">
        <h3 style="margin-top:0; display:flex; align-items:center; gap:10px;"><i class="fa-solid fa-sparkles" style="color:#a855f7;"></i> تنبيهات المالك الذكية</h3>
        <?php if (!empty($smartAlerts)): ?>
            <ul style="padding-inline-start:0; color:#e2e8f0; margin:0; list-style:none;">
                <?php foreach ($smartAlerts as $idx => $alert): ?>
                    <?php 
                    $icons = ['fa-fire', 'fa-bolt', 'fa-bell', 'fa-exclamation-circle', 'fa-triangle-exclamation'];
                    $colors = ['#ef4444', '#f59e0b', '#22d3ee', '#a855f7', '#6366f1'];
                    $icon = $icons[$idx % count($icons)];
                    $color = $colors[$idx % count($colors)];
                    ?>
                    <li style="margin-bottom:12px; padding:10px; background:rgba(15,23,42,0.6); border-radius:10px; border-left:3px solid <?= $color ?>; display:flex; align-items:start; gap:10px; transition:all 0.3s ease;">
                        <i class="fa-solid <?= $icon ?>" style="color:<?= $color ?>; font-size:16px; margin-top:2px;"></i>
                        <span><?= htmlspecialchars($alert) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div style="color:#94a3b8; text-align:center; padding:30px; display:flex; flex-direction:column; align-items:center; gap:10px;">
                <i class="fa-solid fa-circle-check" style="font-size:40px; color:#10b981; opacity:0.3;"></i>
                <span>لا توجد تنبيهات حرجة حالياً.</span>
            </div>
        <?php endif; ?>
    </div>
</div>

<div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:20px; margin-bottom:30px;">
    
    <div class="card">
        <div style="display:flex; justify-content:space-between; margin-bottom:15px; border-bottom:1px solid #333; padding-bottom:10px;">
            <h4 style="margin:0; color:#6366f1; display:flex; align-items:center; gap:8px;"><i class="fa-solid fa-clock-rotate-left"></i> آخر النشاطات</h4>
        </div>
        <div style="font-size:13px; color:#aaa;">
            <?php if (!empty($recentActivity)): ?>
                <?php foreach ($recentActivity as $idx => $log): ?>
                    <?php
                    $activityIcons = ['fa-circle-check', 'fa-pen-to-square', 'fa-trash', 'fa-user-plus', 'fa-file-import', 'fa-sync'];
                    $activityColors = ['#10b981', '#22d3ee', '#ef4444', '#a855f7', '#f59e0b', '#6366f1'];
                    $icon = $activityIcons[$idx % count($activityIcons)];
                    $color = $activityColors[$idx % count($activityColors)];
                    ?>
                    <div style="padding:10px; border-bottom:1px dashed #333; display:flex; align-items:start; gap:10px; transition:all 0.3s ease;">
                        <i class="fa-solid <?= $icon ?>" style="color:<?= $color ?>; font-size:14px; margin-top:2px;"></i>
                        <div style="flex:1;">
                            <?= htmlspecialchars($log['description']) ?>
                            <div style="font-size:11px; color:#666; margin-top:2px; display:flex; align-items:center; gap:4px;">
                                <i class="fa-regular fa-clock" style="font-size:10px;"></i>
                                <?= $log['created_at'] ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="padding:20px; text-align:center; margin-top:20px; color:#666; display:flex; flex-direction:column; align-items:center; gap:10px;">
                    <i class="fa-solid fa-inbox" style="font-size:32px; opacity:0.3;"></i>
                    <span>لا توجد نشاطات أخرى</span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div style="display:flex; justify-content:space-between; margin-bottom:15px; border-bottom:1px solid #333; padding-bottom:10px;">
            <h4 style="margin:0; color:#6366f1; display:flex; align-items:center; gap:8px;"><i class="fa-solid fa-clock"></i> عقود تنتهي قريباً</h4>
            <span style="font-size:11px; background:#333; padding:2px 8px; border-radius:4px; cursor:pointer; transition:all 0.3s ease;" onmouseover="this.style.background='#6366f1'" onmouseout="this.style.background='#333'">عرض الكل</span>
        </div>
        
        <?php if(empty($lists['ending'])): ?>
            <div style="text-align:center; padding:30px; color:#666; display:flex; flex-direction:column; align-items:center; gap:10px;">
                <i class="fa-solid fa-check-circle" style="font-size:36px; color:#10b981; opacity:0.3;"></i>
                <span>لا توجد عقود تنتهي قريباً</span>
            </div>
        <?php else: ?>
            <?php foreach($lists['ending'] as $c): ?>
                <div style="padding:10px; border-bottom:1px dashed #333; display:flex; justify-content:space-between; align-items:center; transition:all 0.3s ease;">
                    <span style="display:flex; align-items:center; gap:8px;">
                        <i class="fa-solid fa-user-clock" style="color:#6366f1; font-size:14px;"></i>
                        <?= htmlspecialchars($c['tenant_name']) ?>
                    </span>
                    <span style="color:#ef4444; font-size:12px; background:rgba(239,68,68,0.1); padding:4px 8px; border-radius:6px; display:flex; align-items:center; gap:4px;">
                        <i class="fa-regular fa-calendar-xmark" style="font-size:11px;"></i>
                        <?= format_date($c['end_date']) ?>
                    </span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="card">
        <div style="display:flex; justify-content:space-between; margin-bottom:15px; border-bottom:1px solid #333; padding-bottom:10px;">
            <h4 style="margin:0; color:#6366f1; display:flex; align-items:center; gap:8px;"><i class="fa-solid fa-calendar-days"></i> دفعات قادمة</h4>
            <span style="font-size:11px; background:#333; padding:2px 8px; border-radius:4px; cursor:pointer; transition:all 0.3s ease;" onmouseover="this.style.background='#6366f1'" onmouseout="this.style.background='#333'">عرض الكل</span>
        </div>

        <?php if(empty($lists['payments'])): ?>
            <div style="text-align:center; padding:30px; color:#666; display:flex; flex-direction:column; align-items:center; gap:10px;">
                <i class="fa-solid fa-check-circle" style="font-size:36px; color:#10b981; opacity:0.3;"></i>
                <span>لا توجد دفعات قادمة خلال 30 يوم</span>
            </div>
        <?php else: ?>
            <?php foreach($lists['payments'] as $p): ?>
                <div style="padding:10px; border-bottom:1px dashed #333; display:flex; justify-content:space-between; align-items:center; transition:all 0.3s ease;">
                    <span style="display:flex; align-items:center; gap:8px;">
                        <i class="fa-solid fa-file-invoice" style="color:#22d3ee; font-size:14px;"></i>
                        دفعة عقد #<?= $p['contract_id'] ?>
                    </span>
                    <span style="color:#10b981; font-weight:bold; background:rgba(16,185,129,0.1); padding:4px 8px; border-radius:6px; display:flex; align-items:center; gap:4px;">
                        <i class="fa-solid fa-coins" style="font-size:11px;"></i>
                        <?= number_format($p['amount']) ?>
                    </span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="margin-bottom:30px; background:linear-gradient(135deg, rgba(99,102,241,0.03), rgba(168,85,247,0.02));">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px">
        <h3 style="margin:0; display:flex; align-items:center; gap:10px;"><i class="fa-solid fa-brain" style="color:#a855f7;"></i> مركز الذكاء التشغيلي</h3>
        <span style="font-size:11px; background:#312e81; padding:4px 12px; border-radius:8px; display:flex; align-items:center; gap:6px;"><i class="fa-brands fa-whatsapp"></i> WhatsApp فقط للتكاملات</span>
    </div>

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:15px; margin-bottom:20px;">
        <div style="background:rgba(17,24,39,0.8); padding:16px; border-radius:14px; border:1px solid rgba(99,102,241,0.2); transition:all 0.3s ease; position:relative; overflow:hidden;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 12px 30px rgba(99,102,241,0.25)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='';">
            <div style="position:absolute; top:-10px; right:-10px; width:60px; height:60px; background:radial-gradient(circle, rgba(99,102,241,0.15), transparent); border-radius:50%;"></div>
            <div style="font-size:12px; color:#9ca3af; display:flex; align-items:center; gap:6px; position:relative; z-index:1;"><i class="fa-solid fa-chart-pie" style="color:#6366f1;"></i> نسبة الإشغال</div>
            <div style="font-size:26px; font-weight:700; position:relative; z-index:1;"><?= $insights['occupancy_rate'] ?>%</div>
        </div>
        <div style="background:rgba(17,24,39,0.8); padding:16px; border-radius:14px; border:1px solid rgba(168,85,247,0.2); transition:all 0.3s ease; position:relative; overflow:hidden;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 12px 30px rgba(168,85,247,0.25)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='';">
            <div style="position:absolute; top:-10px; right:-10px; width:60px; height:60px; background:radial-gradient(circle, rgba(168,85,247,0.15), transparent); border-radius:50%;"></div>
            <div style="font-size:12px; color:#9ca3af; display:flex; align-items:center; gap:6px; position:relative; z-index:1;"><i class="fa-solid fa-bullseye" style="color:#a855f7;"></i> هدف الإشغال</div>
            <div style="font-size:26px; font-weight:700; position:relative; z-index:1;"><?= number_format((float) ($settings['target_occupancy'] ?? 90), 1) ?>%</div>
            <div style="font-size:12px; color:<?= ($insights['occupancy_gap'] ?? 0) >= 0 ? '#10b981' : '#f97316' ?>; position:relative; z-index:1;"><i class="fa-solid fa-<?= ($insights['occupancy_gap'] ?? 0) >= 0 ? 'arrow-up' : 'arrow-down' ?>"></i> فرق <?= $insights['occupancy_gap'] ?? 0 ?>%</div>
        </div>
        <div style="background:rgba(17,24,39,0.8); padding:16px; border-radius:14px; border:1px solid rgba(16,185,129,0.2); transition:all 0.3s ease; position:relative; overflow:hidden;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 12px 30px rgba(16,185,129,0.25)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='';">
            <div style="position:absolute; top:-10px; right:-10px; width:60px; height:60px; background:radial-gradient(circle, rgba(16,185,129,0.15), transparent); border-radius:50%;"></div>
            <div style="font-size:12px; color:#9ca3af; display:flex; align-items:center; gap:6px; position:relative; z-index:1;"><i class="fa-solid fa-hand-holding-dollar" style="color:#10b981;"></i> معدل التحصيل</div>
            <div style="font-size:26px; font-weight:700; position:relative; z-index:1;"><?= $insights['collection_rate'] ?>%</div>
        </div>
        <div style="background:rgba(17,24,39,0.8); padding:16px; border-radius:14px; border:1px solid rgba(34,211,238,0.2); transition:all 0.3s ease; position:relative; overflow:hidden;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 12px 30px rgba(34,211,238,0.25)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='';">
            <div style="position:absolute; top:-10px; right:-10px; width:60px; height:60px; background:radial-gradient(circle, rgba(34,211,238,0.15), transparent); border-radius:50%;"></div>
            <div style="font-size:12px; color:#9ca3af; display:flex; align-items:center; gap:6px; position:relative; z-index:1;"><i class="fa-solid fa-crosshairs" style="color:#22d3ee;"></i> هدف التحصيل</div>
            <div style="font-size:26px; font-weight:700; position:relative; z-index:1;"><?= number_format((float) ($settings['target_collection'] ?? 95), 1) ?>%</div>
            <div style="font-size:12px; color:<?= ($insights['collection_gap'] ?? 0) >= 0 ? '#10b981' : '#f97316' ?>; position:relative; z-index:1;"><i class="fa-solid fa-<?= ($insights['collection_gap'] ?? 0) >= 0 ? 'arrow-up' : 'arrow-down' ?>"></i> فرق <?= $insights['collection_gap'] ?? 0 ?>%</div>
        </div>
        <div style="background:rgba(17,24,39,0.8); padding:16px; border-radius:14px; border:1px solid rgba(245,158,11,0.2); transition:all 0.3s ease; position:relative; overflow:hidden;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 12px 30px rgba(245,158,11,0.25)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='';">
            <div style="position:absolute; top:-10px; right:-10px; width:60px; height:60px; background:radial-gradient(circle, rgba(245,158,11,0.15), transparent); border-radius:50%;"></div>
            <div style="font-size:12px; color:#9ca3af; display:flex; align-items:center; gap:6px; position:relative; z-index:1;"><i class="fa-solid fa-calendar-days" style="color:#f59e0b;"></i> توقع تحصيل 30 يوماً</div>
            <div style="font-size:26px; font-weight:700; position:relative; z-index:1;"><?= number_format($insights['expected_30']) ?></div>
        </div>
        <div style="background:rgba(17,24,39,0.8); padding:16px; border-radius:14px; border:1px solid rgba(99,102,241,0.2); transition:all 0.3s ease; position:relative; overflow:hidden;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 12px 30px rgba(99,102,241,0.25)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='';">
            <div style="position:absolute; top:-10px; right:-10px; width:60px; height:60px; background:radial-gradient(circle, rgba(99,102,241,0.15), transparent); border-radius:50%;"></div>
            <div style="font-size:12px; color:#9ca3af; display:flex; align-items:center; gap:6px; position:relative; z-index:1;"><i class="fa-solid fa-chart-line" style="color:#6366f1;"></i> متوسط التحصيل الشهري (3 أشهر)</div>
            <div style="font-size:26px; font-weight:700; position:relative; z-index:1;"><?= number_format($insights['avg_paid_3m']) ?></div>
        </div>
        <div style="background:rgba(17,24,39,0.8); padding:16px; border-radius:14px; border:1px solid rgba(34,211,238,0.2); transition:all 0.3s ease; position:relative; overflow:hidden;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 12px 30px rgba(34,211,238,0.25)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='';">
            <div style="position:absolute; top:-10px; right:-10px; width:60px; height:60px; background:radial-gradient(circle, rgba(34,211,238,0.15), transparent); border-radius:50%;"></div>
            <div style="font-size:12px; color:#9ca3af; display:flex; align-items:center; gap:6px; position:relative; z-index:1;"><i class="fa-solid fa-hourglass-half" style="color:#22d3ee;"></i> توقع تحصيل 90 يوماً</div>
            <div style="font-size:26px; font-weight:700; position:relative; z-index:1;"><?= number_format($cashflow['in_90']) ?></div>
        </div>
        <div style="background:rgba(17,24,39,0.8); padding:16px; border-radius:14px; border:1px solid rgba(239,68,68,0.2); transition:all 0.3s ease; position:relative; overflow:hidden;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 12px 30px rgba(239,68,68,0.25)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='';">
            <div style="position:absolute; top:-10px; right:-10px; width:60px; height:60px; background:radial-gradient(circle, rgba(239,68,68,0.15), transparent); border-radius:50%;"></div>
            <div style="font-size:12px; color:#9ca3af; display:flex; align-items:center; gap:6px; position:relative; z-index:1;"><i class="fa-solid fa-exclamation-triangle" style="color:#ef4444;"></i> المتأخرات الحالية</div>
            <div style="font-size:26px; font-weight:700; position:relative; z-index:1;"><?= number_format($cashflow['overdue']) ?></div>
        </div>
        <div style="background:rgba(17,24,39,0.8); padding:16px; border-radius:14px; border:1px solid rgba(168,85,247,0.2); transition:all 0.3s ease; position:relative; overflow:hidden;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 12px 30px rgba(168,85,247,0.25)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='';">
            <div style="position:absolute; top:-10px; right:-10px; width:60px; height:60px; background:radial-gradient(circle, rgba(168,85,247,0.15), transparent); border-radius:50%;"></div>
            <div style="font-size:12px; color:#9ca3af; display:flex; align-items:center; gap:6px; position:relative; z-index:1;"><i class="fa-solid fa-arrow-trend-up" style="color:#a855f7;"></i> اتجاه التحصيل (30 يوم)</div>
            <div style="font-size:26px; font-weight:700; position:relative; z-index:1;"><?= number_format($cashflow['collection_trend'], 1) ?>%</div>
        </div>
        <div style="background:rgba(17,24,39,0.8); padding:16px; border-radius:14px; border:1px solid rgba(245,158,11,0.2); transition:all 0.3s ease; position:relative; overflow:hidden;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 12px 30px rgba(245,158,11,0.25)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='';">
            <div style="position:absolute; top:-10px; right:-10px; width:60px; height:60px; background:radial-gradient(circle, rgba(245,158,11,0.15), transparent); border-radius:50%;"></div>
            <div style="font-size:12px; color:#9ca3af; display:flex; align-items:center; gap:6px; position:relative; z-index:1;"><i class="fa-solid fa-wrench" style="color:#f59e0b;"></i> مؤشر مخاطر الصيانة</div>
            <div style="font-size:26px; font-weight:700; position:relative; z-index:1;"><?= $maintenancePulse['risk_score'] ?>/100</div>
        </div>
        <div style="background:rgba(17,24,39,0.8); padding:16px; border-radius:14px; border:1px solid rgba(239,68,68,0.2); transition:all 0.3s ease; position:relative; overflow:hidden;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 12px 30px rgba(239,68,68,0.25)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='';">
            <div style="position:absolute; top:-10px; right:-10px; width:60px; height:60px; background:radial-gradient(circle, rgba(239,68,68,0.15), transparent); border-radius:50%;"></div>
            <div style="font-size:12px; color:#9ca3af; display:flex; align-items:center; gap:6px; position:relative; z-index:1;"><i class="fa-solid fa-user-shield" style="color:#ef4444;"></i> مستأجرون عالي المخاطر</div>
            <div style="font-size:26px; font-weight:700; position:relative; z-index:1;"><?= $tenantRiskSnapshot['high_risk_count'] ?></div>
        </div>
    </div>

    <div style="display:grid; grid-template-columns: 1.2fr 1fr; gap:20px;">
        <div class="theme-bg-section" style=" padding:18px; border-radius:14px; border:1px solid rgba(99,102,241,0.2);">
            <h4 class="theme-text-accent" style="margin-top:0; display:flex; align-items:center; gap:8px;"><i class="fa-solid fa-list-check" style="color:#6366f1;"></i> توصيات فورية</h4>
            <?php if (!empty($recommendations)): ?>
                <ul style="padding-inline-start:0; color:#cbd5f5; margin:0; list-style:none;">
                    <?php foreach ($recommendations as $idx => $rec): ?>
                        <?php 
                        $recIcons = ['fa-rocket', 'fa-bell-concierge', 'fa-wrench', 'fa-sync', 'fa-chart-line', 'fa-shield-halved', 'fa-money-bill-wave', 'fa-users-gear', 'fa-database'];
                        $recColors = ['#6366f1', '#22d3ee', '#f59e0b', '#10b981', '#a855f7', '#ef4444', '#14b8a6', '#8b5cf6', '#06b6d4'];
                        $icon = $recIcons[$idx % count($recIcons)];
                        $color = $recColors[$idx % count($recColors)];
                        ?>
                        <li style="margin-bottom:10px; padding:10px; background:rgba(30,41,59,0.4); border-radius:10px; border-right:3px solid <?= $color ?>; display:flex; align-items:start; gap:10px; transition:all 0.3s ease;" onmouseover="this.style.background='rgba(30,41,59,0.7)'; this.style.transform='translateX(-4px)';" onmouseout="this.style.background='rgba(30,41,59,0.4)'; this.style.transform='translateX(0)';">
                            <i class="fa-solid <?= $icon ?>" style="color:<?= $color ?>; font-size:16px; margin-top:2px;"></i>
                            <span><?= htmlspecialchars($rec) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div style="color:#94a3b8; text-align:center; padding:20px; display:flex; flex-direction:column; align-items:center; gap:10px;">
                    <i class="fa-solid fa-circle-check" style="font-size:36px; color:#10b981; opacity:0.3;"></i>
                    <span>كل المؤشرات ضمن الحدود الطبيعية.</span>
                </div>
            <?php endif; ?>
        </div>
    <div class="theme-bg-section" style=" padding:18px; border-radius:14px; border:1px solid rgba(239,68,68,0.2);">
        <h4 class="theme-text-accent" style="margin-top:0; display:flex; align-items:center; gap:8px;"><i class="fa-solid fa-triangle-exclamation" style="color:#ef4444;"></i> أعلى مخاطر التعثر</h4>
        <?php if (!empty($riskTenants)): ?>
            <?php foreach ($riskTenants as $tenant): ?>
                <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px dashed #1f2937; transition:all 0.3s ease;" onmouseover="this.style.background='rgba(30,41,59,0.4)'; this.style.paddingRight='8px'; this.style.paddingLeft='8px'; this.style.borderRadius='8px';" onmouseout="this.style.background='transparent'; this.style.paddingRight='0'; this.style.paddingLeft='0';">
                    <span style="display:flex; align-items:center; gap:8px;">
                        <i class="fa-solid fa-user-xmark" style="color:#ef4444; font-size:14px;"></i>
                        <?= htmlspecialchars($tenant['name']) ?>
                    </span>
                    <span style="color:#f97316; font-size:12px; background:rgba(249,115,22,0.15); padding:4px 10px; border-radius:8px; display:flex; align-items:center; gap:4px;">
                        <i class="fa-solid fa-clock-rotate-left" style="font-size:11px;"></i>
                        متأخر <?= (int) $tenant['overdue_count'] ?> (<?= (int) $tenant['max_overdue_days'] ?> يوم)
                    </span>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="color:#94a3b8; text-align:center; padding:20px; display:flex; flex-direction:column; align-items:center; gap:10px;">
                <i class="fa-solid fa-shield-check" style="font-size:36px; color:#10b981; opacity:0.3;"></i>
                <span>لا توجد حالات تعثر حالياً.</span>
            </div>
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
