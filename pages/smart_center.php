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

$coverage = [];
$actionItems = [];

$readinessScore = 0;
$quickActions = [
    [
        'label' => 'ضبط التكاملات',
        'href' => 'index.php?p=settings',
        'icon' => 'fa-solid fa-gear',
    ],
    [
        'label' => 'متابعة الصيانة',
        'href' => 'index.php?p=maintenance',
        'icon' => 'fa-solid fa-screwdriver-wrench',
    ],
    [
        'label' => 'مراجعة التنبيهات',
        'href' => 'index.php?p=alerts',
        'icon' => 'fa-solid fa-bell',
    ],
    [
        'label' => 'تجديد العقود',
        'href' => 'index.php?p=contracts',
        'icon' => 'fa-solid fa-file-contract',
    ],
];


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

$integrations['whatsapp'] = WHATSAPP_TOKEN && WHATSAPP_TOKEN !== 'your_token_here';
$integrations['ocr'] = OCR_API_URL && OCR_API_KEY;
$integrations['payment_portal'] = (bool) PAYMENT_PORTAL_URL;
$integrations['admin_whatsapp'] = (bool) ADMIN_WHATSAPP;

$coverage = [
    [
        'title' => 'إدارة التشغيل الأساسية',
        'status' => ($coreStats['properties'] && $coreStats['units'] && $coreStats['contracts'] && $coreStats['tenants']) ? 'جاهز' : 'يحتاج بيانات',
        'hint' => 'العقارات والوحدات والعقود والمستأجرون.',
    ],
    [
        'title' => 'التحصيل المالي والفواتير',
        'status' => $coreStats['payments'] ? 'جاهز' : 'غير مفعل',
        'hint' => 'متابعة الدفعات، الغرامات، وخطط السداد.',
    ],
    [
        'title' => 'الصيانة الذكية',
        'status' => $coreStats['maintenance'] ? 'قيد التشغيل' : 'غير مفعل',
        'hint' => 'تصنيف البلاغات وتحديد الأولويات تلقائياً.',
    ],
    [
        'title' => 'التحليلات والتقارير',
        'status' => ($coreStats['payments'] || $coreStats['contracts']) ? 'مفعّل' : 'يحتاج بيانات',
        'hint' => 'مؤشرات إشغال وتحصيل وتوقعات نقدية.',
    ],
    [
        'title' => 'الأمان والصلاحيات',
        'status' => $coreStats['users'] > 1 ? 'مفعل' : 'يحتاج ضبط أدوار',
        'hint' => 'أدوار متعددة وسجل تدقيق آمن.',
    ],
    [
        'title' => 'التكاملات',
        'status' => ($integrations['whatsapp'] || $integrations['ocr'] || $integrations['payment_portal']) ? 'جزئي' : 'غير مفعل',
        'hint' => 'واتساب، OCR، بوابة دفع.',
    ],
    [
        'title' => 'الأتمتة والتنبيهات',
        'status' => ($automation['open_alerts'] || $automation['pending_payments']) ? 'مفعل' : 'يحتاج تفعيل',
        'hint' => 'تنبيهات تلقائية، رسائل متابعة، مهام وقائية.',
    ],
    [
        'title' => 'الجاهزية للتوسع',
        'status' => 'مدعوم',
        'hint' => 'هيكل بيانات قابل للتوسع ومراقبة الأداء.',
    ],
];

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
$readinessScore = (int) round((array_sum(array_map('intval', $readinessInputs)) / max(1, count($readinessInputs))) * 100);


if (!$integrations['whatsapp']) {
    $actionItems[] = 'تفعيل تكامل واتساب لإرسال التذكيرات والتحصيل.';
}
if (!$integrations['ocr']) {
    $actionItems[] = 'ضبط خدمة OCR لاستخراج بيانات الوثائق تلقائياً.';
}
if (!$integrations['payment_portal']) {
    $actionItems[] = 'ربط بوابة دفع لتسهيل التحصيل الإلكتروني.';
}
if ($coreStats['users'] <= 1) {
    $actionItems[] = 'إضافة أدوار متعددة (تحصيل، صيانة، إدارة) لضمان الحوكمة.';
}
if ($coreStats['payments'] === 0) {
    $actionItems[] = 'تسجيل الدفعات لتفعيل التنبؤ المالي والتحصيل الذكي.';
}
if ($coreStats['maintenance'] === 0) {
    $actionItems[] = 'إضافة طلبات صيانة لتفعيل التحليل التنبؤي.';
}
?>

<div class="card" style="margin-bottom:30px;">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <div>
            <h2 style="margin:0; font-size:24px"><i class="fa-solid fa-brain"></i> مركز التمكين الذكي الشامل</h2>
            <p style="margin:8px 0 0; color:var(--muted)">واجهة تجمع كل عناصر القوة التشغيلية والتحليلية للنظام.</p>
        </div>
        <div style="text-align:left; color:#22c55e; font-weight:700;">
            <i class="fa-solid fa-sparkles"></i> وضع ذكي نشط
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

<div style="display:grid; grid-template-columns: 1.2fr 1fr; gap:20px; margin-bottom:30px;">
    <div class="card" style="padding:25px;">
        <h3 style="margin-top:0"><i class="fa-solid fa-layer-group"></i> تغطية عناصر القوة</h3>
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:15px;">
            <?php foreach ($coverage as $item): ?>
                <div style="background:var(--input-bg); border:1px solid var(--border); border-radius:16px; padding:16px;">
                    <div style="font-weight:700; margin-bottom:6px;"><?= htmlspecialchars($item['title']) ?></div>
                    <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
                        <span style="font-size:12px; padding:4px 10px; border-radius:12px; background:#1f2937; color:#f8fafc;">
                            <?= htmlspecialchars($item['status']) ?>
                        </span>
                    </div>
                    <div style="font-size:12px; color:var(--muted);"><?= htmlspecialchars($item['hint']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="card" style="padding:25px;">
        <h3 style="margin-top:0"><i class="fa-solid fa-plug"></i> التكاملات الذكية</h3>
        <div style="display:flex; flex-direction:column; gap:12px;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <span>واتساب للأتمتة</span>
                <span style="font-size:12px; padding:4px 10px; border-radius:12px; background:<?= $integrations['whatsapp'] ? '#16a34a' : '#7f1d1d' ?>; color:#fff;">
                    <?= $integrations['whatsapp'] ? 'مفعّل' : 'غير مفعل' ?>
                </span>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <span>OCR للوثائق</span>
                <span style="font-size:12px; padding:4px 10px; border-radius:12px; background:<?= $integrations['ocr'] ? '#16a34a' : '#7f1d1d' ?>; color:#fff;">
                    <?= $integrations['ocr'] ? 'مفعّل' : 'غير مفعل' ?>
                </span>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <span>بوابة الدفع</span>
                <span style="font-size:12px; padding:4px 10px; border-radius:12px; background:<?= $integrations['payment_portal'] ? '#16a34a' : '#7f1d1d' ?>; color:#fff;">
                    <?= $integrations['payment_portal'] ? 'مفعّل' : 'غير مفعل' ?>
                </span>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <span>قناة إشعارات الإدارة</span>
                <span style="font-size:12px; padding:4px 10px; border-radius:12px; background:<?= $integrations['admin_whatsapp'] ? '#16a34a' : '#7f1d1d' ?>; color:#fff;">
                    <?= $integrations['admin_whatsapp'] ? 'مفعّل' : 'غير مفعل' ?>
                </span>
            </div>
        </div>

        <div style="margin-top:20px; background:var(--input-bg); border:1px dashed var(--border); padding:14px; border-radius:16px; font-size:12px; color:var(--muted);">
            التفعيل يتم من خلال متغيرات البيئة في config.php.
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

<div class="card" style="padding:25px; margin-bottom:30px;">
    <h3 style="margin-top:0"><i class="fa-solid fa-forward-fast"></i> الخطوة التالية</h3>
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:12px;">
        <?php foreach ($quickActions as $action): ?>
            <a href="<?= htmlspecialchars($action['href']) ?>" style="text-decoration:none; color:inherit;">
                <div style="background:var(--input-bg); border:1px solid var(--border); border-radius:16px; padding:14px; display:flex; align-items:center; gap:12px; transition:0.2s;">
                    <div style="width:40px; height:40px; border-radius:12px; background:var(--tag-bg); display:flex; align-items:center; justify-content:center; color:var(--primary);">
                        <i class="<?= htmlspecialchars($action['icon']) ?>"></i>
                    </div>
                    <div>
                        <div style="font-weight:700;"><?= htmlspecialchars($action['label']) ?></div>
                        <div style="font-size:12px; color:var(--muted);">افتح الصفحة ذات الصلة</div>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</div>


<div class="card" style="padding:25px;">
    <h3 style="margin-top:0"><i class="fa-solid fa-bolt"></i> خطوات تطبيق القوة الخارقة</h3>
    <?php if (!empty($actionItems)): ?>
        <ul style="margin:0; padding-inline-start:20px; color:#cbd5f5;">
            <?php foreach ($actionItems as $item): ?>
                <li style="margin-bottom:10px;"><?= htmlspecialchars($item) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <div style="color:#94a3b8;">كل عناصر التمكين مفعّلة، استمر بمراقبة الأداء وتحسين الأتمتة.</div>
    <?php endif; ?>
</div>
