<?php
$competitors = [
    [
        'name' => 'Buildium',
        'tagline' => 'منصة تشغيلية للعقارات السكنية والتجارية.',
        'features' => [
            'بوابات للمستأجرين والملاك مع دفع إلكتروني.',
            'تنبيهات تلقائية للدفعات المتأخرة.',
            'إدارة صيانة مرتبطة بالموردين.',
            'تقارير مالية قابلة للتخصيص.'
        ]
    ],
    [
        'name' => 'AppFolio Property Manager',
        'tagline' => 'تشغيل ذكي مع أتمتة قوية وتجربة جوال متقدمة.',
        'features' => [
            'لوحة مؤشرات أداء فورية (KPIs).',
            'تطبيقات جوال للملاك والمستأجرين وفرق الصيانة.',
            'أتمتة الإشعارات وجدولة التحصيل.',
            'تسويق الوحدات وربطها بقنوات العرض.'
        ]
    ],
    [
        'name' => 'Yardi',
        'tagline' => 'منصة مؤسسية للمحافظ الكبيرة.',
        'features' => [
            'محاسبة عقارية متقدمة وتسويات بنكية.',
            'إدارة مستندات مع موافقات داخلية.',
            'تكاملات واسعة مع أنظمة خارجية.',
            'حوكمة وصلاحيات دقيقة.'
        ]
    ],
    [
        'name' => 'Rent Manager',
        'tagline' => 'مرونة عالية في تخصيص النماذج وسير العمل.',
        'features' => [
            'إدارة عقود ووثائق إلكترونية.',
            'تذكيرات تحصيل واسترداد متأخرات.',
            'تقارير مرنة ومنشئ تقارير بصري.',
            'قابلية تخصيص الحقول وسير العمل.'
        ]
    ]
];

$focusAreas = [
    [
        'title' => 'بوابات المستأجرين والملاك',
        'desc' => 'تحويل النظام إلى تجربة ذاتية الخدمة عبر بوابات مخصصة لكل فئة.',
        'actions' => [
            'إنشاء صفحة مستأجرين للدفع وتتبع العقود والمستندات.',
            'تقرير دوري للملاك مع مؤشرات إشغال وتحويلات مالية.',
            'تفعيل إشعارات لحظية عبر واتساب والبريد.'
        ],
        'link' => 'index.php?p=tenants',
        'link_text' => 'الانتقال للمستأجرين'
    ],
    [
        'title' => 'التحصيل الذكي والدفعات',
        'desc' => 'أتمتة دورة التحصيل بالكامل مع سياسات واضحة.',
        'actions' => [
            'جدولة دفعات مرنة وربطها بالتنبيهات.',
            'تنبيهات متعددة القنوات قبل موعد الاستحقاق.',
            'لوحة تحصيل تعرض المتأخرات حسب المخاطر.'
        ],
        'link' => 'index.php?p=alerts',
        'link_text' => 'متابعة التنبيهات'
    ],
    [
        'title' => 'الصيانة وسلسلة الموردين',
        'desc' => 'تنظيم الصيانة عبر تذاكر وقياس الأداء.',
        'actions' => [
            'إضافة أولويات وساعات استجابة (SLA).',
            'تقييم المقاولين وربطهم بطلبات محددة.',
            'تحليل الأعطال المتكررة لوضع خطة وقائية.'
        ],
        'link' => 'index.php?p=maintenance',
        'link_text' => 'إدارة الصيانة'
    ],
    [
        'title' => 'العقود والمستندات الرقمية',
        'desc' => 'نقل العقود إلى تجربة رقمية بالكامل.',
        'actions' => [
            'توقيع إلكتروني وملاحق رقمية.',
            'أرشفة وتصنيف تلقائي للمستندات.',
            'سجل تدقيق للتغييرات والموافقات.'
        ],
        'link' => 'index.php?p=contracts',
        'link_text' => 'إدارة العقود'
    ],
    [
        'title' => 'التحليلات ولوحات التحكم',
        'desc' => 'تحويل البيانات إلى قرارات عبر مؤشرات واضحة.',
        'actions' => [
            'لوحة تنفيذية للإشغال والتحصيل والمصروفات.',
            'توقعات ذكية لتأخر السداد وانخفاض الإشغال.',
            'تقارير قابلة للتخصيص لكل مالك أو منطقة.'
        ],
        'link' => 'index.php?p=dashboard',
        'link_text' => 'فتح لوحة القيادة'
    ],
    [
        'title' => 'التكاملات والامتثال',
        'desc' => 'توسيع النظام عبر ربطه بمنصات خارجية.',
        'actions' => [
            'تكامل بوابات الدفع المحلية والدولية.',
            'ربط الرسائل SMS وواتساب والبريد.',
            'تفعيل نسخ احتياطي تلقائي وصلاحيات دقيقة.'
        ],
        'link' => 'index.php?p=settings',
        'link_text' => 'إعدادات النظام'
    ]
];

$roadmap = [
    [
        'phase' => 'المرحلة 1 (سريعة التأثير)',
        'items' => [
            'إطلاق بوابة المستأجر والدفع الإلكتروني.',
            'تنبيهات تحصيل متعددة القنوات.',
            'لوحة مؤشرات أساسية للإشغال والتحصيل.'
        ]
    ],
    [
        'phase' => 'المرحلة 2 (تعزيز التنافسية)',
        'items' => [
            'نظام صيانة متقدم مع SLA وربط الموردين.',
            'توقيع إلكتروني وإدارة مستندات ذكية.',
            'تقارير مالية مرنة للملاك.'
        ]
    ],
    [
        'phase' => 'المرحلة 3 (تميّز)',
        'items' => [
            'تحليلات تنبؤية وتوصيات ذكية.',
            'تطبيقات جوال متكاملة.',
            'تكاملات مؤسسية مع أنظمة مالية.'
        ]
    ]
];
?>

<style>
    .competitive-hero {
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:25px;
        padding:25px;
        border-radius:22px;
        background:linear-gradient(135deg, rgba(99,102,241,0.2), rgba(168,85,247,0.15));
        border:1px solid var(--border);
        margin-bottom:30px;
        box-shadow:0 18px 40px rgba(2,6,23,0.35);
    }
    .competitive-hero h2 { margin:0 0 10px; font-size:26px; }
    .competitive-hero p { margin:0; color:var(--muted); line-height:1.7; }
    .competitive-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:20px; }
    .competitive-card { background:var(--card); border:1px solid var(--border); border-radius:20px; padding:22px; box-shadow:0 18px 40px rgba(2,6,23,0.2); }
    .competitive-card h3 { margin-top:0; margin-bottom:8px; font-size:20px; }
    .competitive-card p { margin:0 0 15px; color:var(--muted); }
    .competitive-card ul { margin:0; padding-right:18px; color:var(--text); line-height:1.8; }
    .focus-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(270px, 1fr)); gap:22px; }
    .focus-card { background:var(--card); border:1px solid var(--border); border-radius:24px; padding:24px; position:relative; overflow:hidden; }
    .focus-card h4 { margin:0 0 10px; font-size:18px; }
    .focus-card p { margin:0 0 15px; color:var(--muted); }
    .focus-card ul { margin:0 0 15px; padding-right:18px; line-height:1.8; }
    .focus-link { display:inline-flex; align-items:center; gap:8px; color:white; text-decoration:none; }
    .roadmap-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(250px, 1fr)); gap:18px; }
    .roadmap-card { background:var(--card); border:1px solid var(--border); border-radius:20px; padding:20px; }
    .roadmap-card h5 { margin:0 0 12px; font-size:17px; }
    .roadmap-card ul { margin:0; padding-right:18px; line-height:1.8; }
</style>

<div class="competitive-hero">
    <div>
        <h2>خارطة المنافسة وتطوير البرنامج</h2>
        <p>تم تحويل أبرز أفكار المنافسين العالميين إلى مبادرات عملية داخل النظام، مع تحديد خطوات قابلة للتنفيذ وربطها بأقسامك الحالية لتسريع التطوير.</p>
    </div>
    <div>
        <span class="badge" style="background:rgba(99,102,241,0.2); color:var(--primary)">تحليل منافسين</span>
    </div>
</div>

<div class="card">
    <h3 style="margin-top:0">المنافسون العالميون الأقوى</h3>
    <div class="competitive-grid" style="margin-top:20px">
        <?php foreach ($competitors as $competitor): ?>
            <div class="competitive-card">
                <h3><?= htmlspecialchars($competitor['name']) ?></h3>
                <p><?= htmlspecialchars($competitor['tagline']) ?></p>
                <ul>
                    <?php foreach ($competitor['features'] as $feature): ?>
                        <li><?= htmlspecialchars($feature) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="card">
    <h3 style="margin-top:0">تطبيق أفكار المنافسين على نظامك الحالي</h3>
    <p style="color:var(--muted); margin-top:8px">هذه المبادرات مرتبطة مباشرة بصفحات النظام، ويمكن تنفيذها تدريجياً مع قياس الأثر.</p>
    <div class="focus-grid" style="margin-top:20px">
        <?php foreach ($focusAreas as $area): ?>
            <div class="focus-card">
                <h4><?= htmlspecialchars($area['title']) ?></h4>
                <p><?= htmlspecialchars($area['desc']) ?></p>
                <ul>
                    <?php foreach ($area['actions'] as $action): ?>
                        <li><?= htmlspecialchars($action) ?></li>
                    <?php endforeach; ?>
                </ul>
                <a class="btn btn-primary focus-link" href="<?= htmlspecialchars($area['link']) ?>">
                    <i class="fa-solid fa-arrow-left"></i>
                    <?= htmlspecialchars($area['link_text']) ?>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="card">
    <h3 style="margin-top:0">خريطة طريق تنفيذية</h3>
    <div class="roadmap-grid" style="margin-top:20px">
        <?php foreach ($roadmap as $phase): ?>
            <div class="roadmap-card">
                <h5><?= htmlspecialchars($phase['phase']) ?></h5>
                <ul>
                    <?php foreach ($phase['items'] as $item): ?>
                        <li><?= htmlspecialchars($item) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="card" style="text-align:center">
    <h3 style="margin-top:0">الخطوة التالية</h3>
    <p style="color:var(--muted)">عند رغبتك يمكننا تحويل هذه الخريطة إلى متطلبات تفصيلية وربطها بخطة تنفيذ زمنية لكل فريق.</p>
    <a class="btn btn-dark" href="index.php?p=smart_center"><i class="fa-solid fa-brain"></i> مركز التمكين الذكي</a>
</div>
