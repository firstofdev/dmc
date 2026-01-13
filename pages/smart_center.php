<?php
/**
 * صفحة مركز التمكين الذكي
 */

$coreStats = [
    'properties' => 0,
    'units' => 0,
    'contracts' => 0,
    'tenants' => 0,
    'payments' => 0,
    'maintenance' => 0,
    'users' => 0,
];

$insights = [
    'occupancy_rate' => 0,
    'collection_rate' => 0,
    'overdue_count' => 0,
    'expected_30' => 0,
    'avg_paid_3m' => 0,
];

$integrations = [
    'whatsapp' => false,
    'ocr' => false,
    'payment_portal' => false,
    'admin_whatsapp' => false,
];

$automation = [
    'pending_maintenance' => 0,
    'open_alerts' => 0,
    'upcoming_contracts' => 0,
    'pending_payments' => 0,
];

$readinessScore = 0;


try {
    if (isset($pdo)) {
        $coreStats['properties'] = (int) $pdo->query("SELECT COUNT(*) FROM properties")->fetchColumn();
        $coreStats['units'] = (int) $pdo->query("SELECT COUNT(*) FROM units")->fetchColumn();
        $coreStats['contracts'] = (int) $pdo->query("SELECT COUNT(*) FROM contracts")->fetchColumn();
        $coreStats['tenants'] = (int) $pdo->query("SELECT COUNT(*) FROM tenants")->fetchColumn();
        $coreStats['payments'] = (int) $pdo->query("SELECT COUNT(*) FROM payments")->fetchColumn();
        $coreStats['maintenance'] = (int) $pdo->query("SELECT COUNT(*) FROM maintenance")->fetchColumn();
        $coreStats['users'] = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

        $rented = (int) $pdo->query("SELECT COUNT(*) FROM units WHERE status='rented'")->fetchColumn();
        $totalUnits = max(1, $coreStats['units']);
        $insights['occupancy_rate'] = round(($rented / $totalUnits) * 100, 1);

        $paid = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='paid'")->fetchColumn();
        $totalInvoiced = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments")->fetchColumn();
        $insights['collection_rate'] = $totalInvoiced > 0 ? round(($paid / $totalInvoiced) * 100, 1) : 0;

        $insights['overdue_count'] = (int) $pdo->query("SELECT COUNT(*) FROM payments WHERE status!='paid' AND due_date < CURDATE()")->fetchColumn();
        $insights['expected_30'] = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status!='paid' AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
        $avgPaid = $pdo->query("SELECT COALESCE(AVG(month_total),0) FROM (
            SELECT SUM(amount) AS month_total
            FROM payments
            WHERE status='paid' AND paid_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
            GROUP BY DATE_FORMAT(paid_date, '%Y-%m')
        ) t")->fetchColumn();
        $insights['avg_paid_3m'] = (float) ($avgPaid ?: 0);

        $automation['pending_maintenance'] = (int) $pdo->query("SELECT COUNT(*) FROM maintenance WHERE status='pending'")->fetchColumn();
        $automation['open_alerts'] = (int) $pdo->query("SELECT COUNT(*) FROM alerts WHERE status='open'")->fetchColumn();
        $automation['upcoming_contracts'] = (int) $pdo->query("SELECT COUNT(*) FROM contracts WHERE end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
        $automation['pending_payments'] = (int) $pdo->query("SELECT COUNT(*) FROM payments WHERE status='pending'")->fetchColumn();
    }
} catch (Exception $e) {
    // نستخدم القيم الافتراضية
}

$forceSmart = smart_features_force_enabled();
$integrations['whatsapp'] = $forceSmart ? true : (WHATSAPP_TOKEN && WHATSAPP_TOKEN !== 'your_token_here');
$integrations['ocr'] = $forceSmart ? true : (OCR_API_URL && OCR_API_KEY);
$integrations['payment_portal'] = $forceSmart ? true : (bool) PAYMENT_PORTAL_URL;
$integrations['admin_whatsapp'] = $forceSmart ? true : (bool) ADMIN_WHATSAPP;


$readinessInputs = [
    $coreStats['properties'] > 0,
    $coreStats['units'] > 0,
    $coreStats['contracts'] > 0,
    $coreStats['tenants'] > 0,
    $coreStats['payments'] > 0,
    $coreStats['maintenance'] > 0,
    $integrations['whatsapp'],
    $integrations['ocr'],
    $integrations['payment_portal'],
];
$readinessScore = $forceSmart
    ? 100
    : (int) round((array_sum(array_map('intval', $readinessInputs)) / max(1, count($readinessInputs))) * 100);
?>

<div class="card" style="margin-bottom:30px;">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <div>
            <h2 style="margin:0; font-size:24px"><i class="fa-solid fa-brain"></i> مركز التمكين الذكي الشامل</h2>
            <p style="margin:8px 0 0; color:var(--muted)">واجهة تجمع كل عناصر القوة التشغيلية والتحليلية للنظام.</p>
        </div>
        <div style="text-align:left; color:#22c55e; font-weight:700;">
            <i class="fa-solid fa-sparkles"></i> <?= $forceSmart ? 'وضع تمكين شامل (جميع المميزات مفعلة)' : 'وضع تشغيلي فعلي' ?>
        </div>
    </div>
</div>

<div class="card" style="margin-bottom:30px; padding:22px;">
    <div style="display:flex; justify-content:space-between; align-items:center; gap:20px; flex-wrap:wrap;">
        <div>
            <h3 style="margin:0"><i class="fa-solid fa-shield-heart"></i> مؤشر الجاهزية الشاملة</h3>
            <p style="margin:8px 0 0; color:var(--muted);">يعكس اكتمال البيانات والتكاملات الأساسية.</p>
        </div>
        <div style="background:var(--input-bg); border:1px solid var(--border); border-radius:16px; padding:12px 18px; font-size:26px; font-weight:800;">
            <?= $readinessScore ?>%
        </div>
    </div>
    <div style="margin-top:15px; display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:10px;">
        <div style="background:var(--input-bg); border:1px dashed var(--border); border-radius:12px; padding:10px; font-size:12px; color:var(--muted);">
            اكتمال الوحدات والعقارات والعقود.
        </div>
        <div style="background:var(--input-bg); border:1px dashed var(--border); border-radius:12px; padding:10px; font-size:12px; color:var(--muted);">
            تغذية الدفعات والصيانة للتحليلات.
        </div>
        <div style="background:var(--input-bg); border:1px dashed var(--border); border-radius:12px; padding:10px; font-size:12px; color:var(--muted);">
            ربط واتساب، OCR، بوابة الدفع.
        </div>
    </div>
</div>


<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:20px; margin-bottom:30px;">
    <div class="card" style="padding:20px;">
        <div style="font-size:12px; color:var(--muted)">نسبة الإشغال</div>
        <div style="font-size:28px; font-weight:800; margin-top:8px;"><?= $insights['occupancy_rate'] ?>%</div>
        <div style="font-size:12px; color:#94a3b8; margin-top:6px;">جاهزية السوق على مستوى الوحدات</div>
    </div>
    <div class="card" style="padding:20px;">
        <div style="font-size:12px; color:var(--muted)">معدل التحصيل</div>
        <div style="font-size:28px; font-weight:800; margin-top:8px;"><?= $insights['collection_rate'] ?>%</div>
        <div style="font-size:12px; color:#94a3b8; margin-top:6px;">مقارنة التحصيل بالفواتير</div>
    </div>
    <div class="card" style="padding:20px;">
        <div style="font-size:12px; color:var(--muted)">دفعات متوقعة 30 يوماً</div>
        <div style="font-size:28px; font-weight:800; margin-top:8px;"><?= number_format($insights['expected_30']) ?></div>
        <div style="font-size:12px; color:#94a3b8; margin-top:6px;">تقدير التدفق النقدي القريب</div>
    </div>
    <div class="card" style="padding:20px;">
        <div style="font-size:12px; color:var(--muted)">متأخرات حالية</div>
        <div style="font-size:28px; font-weight:800; margin-top:8px;"><?= $insights['overdue_count'] ?></div>
        <div style="font-size:12px; color:#94a3b8; margin-top:6px;">حالات تحتاج متابعة فورية</div>
    </div>
</div>

<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:20px; margin-bottom:30px;">
    <div class="card" style="padding:20px;">
        <div style="font-size:12px; color:var(--muted)">طلبات صيانة معلقة</div>
        <div style="font-size:28px; font-weight:800; margin-top:8px;"><?= $automation['pending_maintenance'] ?></div>
        <div style="font-size:12px; color:#94a3b8; margin-top:6px;">جاهزية معالجة الصيانة</div>
    </div>
    <div class="card" style="padding:20px;">
        <div style="font-size:12px; color:var(--muted)">تنبيهات مفتوحة</div>
        <div style="font-size:28px; font-weight:800; margin-top:8px;"><?= $automation['open_alerts'] ?></div>
        <div style="font-size:12px; color:#94a3b8; margin-top:6px;">تنبيهات تحتاج متابعة</div>
    </div>
    <div class="card" style="padding:20px;">
        <div style="font-size:12px; color:var(--muted)">عقود تنتهي قريباً</div>
        <div style="font-size:28px; font-weight:800; margin-top:8px;"><?= $automation['upcoming_contracts'] ?></div>
        <div style="font-size:12px; color:#94a3b8; margin-top:6px;">فرص تجديد وإشغال</div>
    </div>
    <div class="card" style="padding:20px;">
        <div style="font-size:12px; color:var(--muted)">دفعات معلقة</div>
        <div style="font-size:28px; font-weight:800; margin-top:8px;"><?= $automation['pending_payments'] ?></div>
        <div style="font-size:12px; color:#94a3b8; margin-top:6px;">خطط تحصيل مستهدفة</div>
    </div>
</div>
