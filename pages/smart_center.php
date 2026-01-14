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

$portfolio = [
    'vacant_units' => 0,
    'vacancy_rate' => 0,
    'net_cash_30' => 0,
    'avg_unit_price' => 0,
    'renewal_60' => 0,
    'risk_tenants' => 0,
    'top_property' => '—',
    'top_property_revenue' => 0,
    'health_score' => 0,
    'maintenance_ratio_90' => 0,
];

$smartSignals = [
    'occupancy_gap' => 0,
    'collection_gap' => 0,
];

$actionPlan = [];

$readinessScore = 0;
$currency = get_setting('currency', 'SAR');


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

        $portfolio['vacant_units'] = (int) $pdo->query("SELECT COUNT(*) FROM units WHERE status='available'")->fetchColumn();
        $portfolio['vacancy_rate'] = $coreStats['units'] > 0 ? round(($portfolio['vacant_units'] / $coreStats['units']) * 100, 1) : 0;
        $portfolio['avg_unit_price'] = (float) $pdo->query("SELECT COALESCE(AVG(yearly_price),0) FROM units")->fetchColumn();
        $portfolio['renewal_60'] = (int) $pdo->query("SELECT COUNT(*) FROM contracts WHERE end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY)")->fetchColumn();
        $portfolio['risk_tenants'] = (int) $pdo->query("SELECT COUNT(DISTINCT t.id)
            FROM payments p
            JOIN contracts c ON p.contract_id=c.id
            JOIN tenants t ON c.tenant_id=t.id
            WHERE p.status!='paid' AND p.due_date < CURDATE()")->fetchColumn();

        $paidDateColumn = table_has_column($pdo, 'payments', 'paid_date') ? 'paid_date' : 'due_date';
        $paidLast30 = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='paid' AND $paidDateColumn >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
        $paidLast90 = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='paid' AND $paidDateColumn >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)")->fetchColumn();
        $maintenanceCost30 = 0;
        $maintenanceCost90 = 0;
        if (table_has_column($pdo, 'maintenance', 'cost') && table_has_column($pdo, 'maintenance', 'request_date')) {
            $maintenanceCost30 = (float) $pdo->query("SELECT COALESCE(SUM(cost),0) FROM maintenance WHERE request_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
            $maintenanceCost90 = (float) $pdo->query("SELECT COALESCE(SUM(cost),0) FROM maintenance WHERE request_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)")->fetchColumn();
        }
        $portfolio['net_cash_30'] = $paidLast30 - $maintenanceCost30;
        $portfolio['maintenance_ratio_90'] = $paidLast90 > 0 ? round(($maintenanceCost90 / $paidLast90) * 100, 1) : 0;

        $topProperty = $pdo->query("SELECT p.name, COALESCE(SUM(pay.amount),0) AS total
            FROM properties p
            LEFT JOIN units u ON u.property_id = p.id
            LEFT JOIN contracts c ON c.unit_id = u.id
            LEFT JOIN payments pay ON pay.contract_id = c.id AND pay.status='paid' AND pay.$paidDateColumn >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
            GROUP BY p.id
            ORDER BY total DESC
            LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($topProperty) {
            $portfolio['top_property'] = $topProperty['name'] ?: '—';
            $portfolio['top_property_revenue'] = (float) $topProperty['total'];
        }

        $healthInputs = [
            min(100, $insights['occupancy_rate']),
            min(100, $insights['collection_rate']),
            max(0, 100 - min(100, $automation['pending_maintenance'] * 8)),
            max(0, 100 - min(100, $insights['overdue_count'] * 5)),
        ];
        $portfolio['health_score'] = (int) round(array_sum($healthInputs) / max(1, count($healthInputs)));
    }
} catch (Exception $e) {
    // نستخدم القيم الافتراضية
}

$forceSmart = smart_features_force_enabled();
$integrations['whatsapp'] = $forceSmart ? true : is_whatsapp_configured();
$integrations['ocr'] = $forceSmart ? true : is_ocr_configured();
$integrations['payment_portal'] = $forceSmart ? true : is_payment_portal_configured();
$integrations['admin_whatsapp'] = $forceSmart ? true : is_admin_whatsapp_configured();


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

$smartSignals['occupancy_gap'] = round($insights['occupancy_rate'] - 90, 1);
$smartSignals['collection_gap'] = round($insights['collection_rate'] - 95, 1);
if ($portfolio['vacancy_rate'] >= 15) {
    $actionPlan[] = 'رفع إشغال الوحدات الشاغرة عبر حملات تسويق موجهة وأسعار مرنة.';
}
if ($insights['overdue_count'] > 0) {
    $actionPlan[] = 'إرسال تذكيرات تحصيل ذكية للمستأجرين المتأخرين وربطها بروابط دفع فورية.';
}
if ($portfolio['renewal_60'] > 0) {
    $actionPlan[] = 'إعداد عروض تجديد قبل 60 يوماً مع خيارات ترقية للوحدات.';
}
if ($automation['pending_maintenance'] > 0) {
    $actionPlan[] = 'تجميع طلبات الصيانة ووضع أولويات حسب التكلفة وخطورة البلاغ.';
}
if ($portfolio['maintenance_ratio_90'] >= 20) {
    $actionPlan[] = 'تفعيل خطة صيانة وقائية لتقليل نسبة تكاليف الصيانة من الدخل.';
}
if ($portfolio['risk_tenants'] > 0) {
    $actionPlan[] = 'تصنيف المستأجرين مرتفعي المخاطر وجدولة خطط سداد مرنة.';
}

$smartFeatures = [
    ['label' => 'رسائل واتساب آلية', 'active' => $integrations['whatsapp']],
    ['label' => 'OCR لقراءة الهويات', 'active' => $integrations['ocr']],
    ['label' => 'بوابة دفع إلكترونية', 'active' => $integrations['payment_portal']],
    ['label' => 'تنبيه المدير عبر واتساب', 'active' => $integrations['admin_whatsapp']],
    ['label' => 'تحليلات ذكية للسوق', 'active' => true],
    ['label' => 'مركز متابعة تلقائي', 'active' => true],
];
?>

<div class="card" style="margin-bottom:30px;">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <div>
            <h2 style="margin:0; font-size:24px"><i class="fa-solid fa-microchip" style="margin-left:10px;color:var(--primary)"></i> مركز التمكين الذكي الشامل</h2>
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

<div class="card" style="margin-bottom:30px; padding:22px;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px;">
        <div>
            <h3 style="margin:0"><i class="fa-solid fa-crown"></i> موجز مالك العقار الذكي</h3>
            <p style="margin:8px 0 0; color:var(--muted);">ملخص أداء استراتيجي يساعد على اتخاذ قرارات سريعة.</p>
        </div>
        <div style="background:var(--input-bg); border:1px solid var(--border); border-radius:16px; padding:10px 16px; font-weight:800; color:#22c55e;">
            صحة المحفظة: <?= $portfolio['health_score'] ?>%
        </div>
    </div>
    <div style="margin-top:18px; display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:14px;">
        <div style="background:var(--input-bg); border:1px solid var(--border); border-radius:16px; padding:16px;">
            <div style="font-size:12px; color:var(--muted)">صافي التدفق النقدي 30 يوماً</div>
            <div style="font-size:22px; font-weight:800; margin-top:8px;"><?= number_format($portfolio['net_cash_30'], 2) ?> <span style="font-size:12px; color:var(--muted)"><?= $currency ?></span></div>
            <div style="font-size:12px; color:#94a3b8; margin-top:6px;">بعد خصم تكاليف الصيانة</div>
        </div>
        <div style="background:var(--input-bg); border:1px solid var(--border); border-radius:16px; padding:16px;">
            <div style="font-size:12px; color:var(--muted)">معدل الشغور</div>
            <div style="font-size:22px; font-weight:800; margin-top:8px;"><?= $portfolio['vacancy_rate'] ?>%</div>
            <div style="font-size:12px; color:#94a3b8; margin-top:6px;"><?= $portfolio['vacant_units'] ?> وحدة شاغرة</div>
        </div>
        <div style="background:var(--input-bg); border:1px solid var(--border); border-radius:16px; padding:16px;">
            <div style="font-size:12px; color:var(--muted)">فرص تجديد خلال 60 يوم</div>
            <div style="font-size:22px; font-weight:800; margin-top:8px;"><?= $portfolio['renewal_60'] ?></div>
            <div style="font-size:12px; color:#94a3b8; margin-top:6px;">دفعة تجديد مبكرة</div>
        </div>
        <div style="background:var(--input-bg); border:1px solid var(--border); border-radius:16px; padding:16px;">
            <div style="font-size:12px; color:var(--muted)">متوسط سعر الوحدة السنوي</div>
            <div style="font-size:22px; font-weight:800; margin-top:8px;"><?= number_format($portfolio['avg_unit_price'], 2) ?> <span style="font-size:12px; color:var(--muted)"><?= $currency ?></span></div>
            <div style="font-size:12px; color:#94a3b8; margin-top:6px;">مؤشر تسعيري للمحفظة</div>
        </div>
    </div>
</div>

<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:20px; margin-bottom:30px;">
    <div class="card" style="padding:22px;">
        <h4 style="margin-top:0"><i class="fa-solid fa-satellite"></i> رادار الفرص والمخاطر</h4>
        <div style="display:grid; gap:12px; font-size:14px;">
            <div style="background:var(--input-bg); border:1px dashed var(--border); border-radius:14px; padding:12px;">
                أعلى عقار أداءً: <strong><?= htmlspecialchars($portfolio['top_property']) ?></strong>
                <span style="display:block; color:var(--muted); font-size:12px; margin-top:4px;">
                    دخل 90 يوماً: <?= number_format($portfolio['top_property_revenue'], 2) ?> <?= $currency ?>
                </span>
            </div>
            <div style="background:var(--input-bg); border:1px dashed var(--border); border-radius:14px; padding:12px;">
                نسبة تكلفة الصيانة: <strong><?= $portfolio['maintenance_ratio_90'] ?>%</strong>
                <span style="display:block; color:var(--muted); font-size:12px; margin-top:4px;">
                    مقارنة بالدخل المدفوع آخر 90 يوماً
                </span>
            </div>
            <div style="background:var(--input-bg); border:1px dashed var(--border); border-radius:14px; padding:12px;">
                المستأجرون مرتفعو المخاطر: <strong><?= $portfolio['risk_tenants'] ?></strong>
                <span style="display:block; color:var(--muted); font-size:12px; margin-top:4px;">
                    يحتاجون متابعة وجدولة تحصيل
                </span>
            </div>
            <div style="background:var(--input-bg); border:1px dashed var(--border); border-radius:14px; padding:12px;">
                فجوة الإشغال: <strong><?= $smartSignals['occupancy_gap'] ?>%</strong> | فجوة التحصيل: <strong><?= $smartSignals['collection_gap'] ?>%</strong>
                <span style="display:block; color:var(--muted); font-size:12px; margin-top:4px;">
                    مقارنة بالهدف التشغيلي (90% إشغال / 95% تحصيل)
                </span>
            </div>
        </div>
    </div>
    <div class="card" style="padding:22px;">
        <h4 style="margin-top:0"><i class="fa-solid fa-list-check"></i> خطة العمل الذكية</h4>
        <?php if (empty($actionPlan)): ?>
            <p style="color:var(--muted); margin:0;">لا توجد مهام حرجة حالياً، الأداء متوازن.</p>
        <?php else: ?>
            <ul style="margin:0; padding-right:18px; color:var(--text); line-height:1.9;">
                <?php foreach ($actionPlan as $item): ?>
                    <li><?= htmlspecialchars($item) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <div class="card" style="padding:22px;">
        <h4 style="margin-top:0"><i class="fa-solid fa-microchip"></i> حالة المميزات الذكية</h4>
        <div style="display:grid; gap:10px;">
            <?php foreach ($smartFeatures as $feature): ?>
                <div style="display:flex; align-items:center; justify-content:space-between; background:var(--input-bg); border:1px solid var(--border); border-radius:14px; padding:10px 12px;">
                    <span><?= htmlspecialchars($feature['label']) ?></span>
                    <span style="font-size:12px; font-weight:700; color:<?= $feature['active'] ? '#22c55e' : '#f97316' ?>;">
                        <?= $feature['active'] ? 'مفعلة' : 'غير مفعلة' ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
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
