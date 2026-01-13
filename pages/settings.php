<?php
if(isset($_POST['save_settings'])){
    check_csrf();
    $keys = [
        'company_name','phone','email','address','currency','currency_code','vat_no','vat_percent','cr_no',
        'invoice_prefix','invoice_terms','alert_days','target_occupancy','target_collection',
        'overdue_threshold','whatsapp_number','reporting_email',
        'whatsapp_token','whatsapp_api_url','ocr_api_url','ocr_api_key','admin_whatsapp','payment_portal_url',
        'smart_features_mode','timezone','date_format','maintenance_mode','maintenance_message',
        'alerts_digest','auto_backup','backup_frequency','invoice_grace_days','default_payment_method',
        'tenant_portal_url','support_phone','support_email'
    ];
    foreach($keys as $k){ if(isset($_POST[$k])) { $pdo->prepare("REPLACE INTO settings (k,v) VALUES (?,?)")->execute([$k, $_POST[$k]]); } }
    if(!empty($_FILES['logo']['name'])){
        $path = upload($_FILES['logo']);
        $pdo->prepare("REPLACE INTO settings (k,v) VALUES ('logo',?)")->execute([$path]);
    }
    echo "<script>window.location='index.php?p=settings';</script>";
}
$sets=[]; $q=$pdo->query("SELECT * FROM settings"); while($r=$q->fetch()) $sets[$r['k']]=$r['v'];
$logo = $sets['logo'] ?? 'logo.png';
$smartMode = $sets['smart_features_mode'] ?? 'force';
$smartModeLabel = $smartMode === 'force' ? 'تمكين شامل' : 'حسب التكاملات';
$smartModeHint = $smartMode === 'force' ? 'جميع المميزات فعالة' : 'يعتمد على الاتصالات المتاحة';
$alertChannelReady = !empty($sets['whatsapp_number']) || !empty($sets['reporting_email']);
$companyReady = !empty($sets['company_name']) && !empty($sets['phone']) && !empty($sets['email']);
$brandReady = !empty($sets['logo']);
$maintenanceEnabled = ($sets['maintenance_mode'] ?? 'off') === 'on';
$maintenanceLabel = $maintenanceEnabled ? 'مفعّل' : 'غير مفعّل';
$timezoneValue = $sets['timezone'] ?? 'Asia/Riyadh';
$dateFormatValue = $sets['date_format'] ?? 'Y-m-d';
$dateFormatExample = date($dateFormatValue);
?>

<form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="save_settings" value="1">
    
    <style>
        .settings-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; gap:16px; }
        .settings-title { font-weight:800; margin:0; display:flex; align-items:center; gap:10px; }
        .settings-actions { display:flex; gap:12px; align-items:center; flex-wrap:wrap; }
        .settings-overview { display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:18px; margin-bottom:26px; }
        .settings-overview-card { background:linear-gradient(135deg, rgba(99,102,241,0.16), rgba(168,85,247,0.1)); border:1px solid var(--border); border-radius:20px; padding:18px 20px; display:flex; align-items:center; gap:14px; box-shadow:0 15px 35px rgba(2,6,23,0.2); }
        .settings-overview-icon { width:48px; height:48px; border-radius:16px; background:rgba(99,102,241,0.18); display:flex; align-items:center; justify-content:center; color:var(--primary); font-size:20px; }
        .settings-overview-title { font-size:13px; color:var(--muted); margin-bottom:6px; }
        .settings-overview-value { font-size:16px; font-weight:700; }
        .settings-badge { display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:999px; font-size:12px; font-weight:700; background:rgba(34,211,238,0.12); color:var(--accent-2); }
        .settings-badge.is-warning { background:rgba(239,68,68,0.12); color:#f87171; }
        .settings-badge.is-neutral { background:rgba(148,163,184,0.12); color:#cbd5f5; }
        .settings-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:20px; }
        .settings-card { padding:0; overflow:hidden; border:none; }
        .settings-card-header { display:flex; align-items:center; gap:10px; padding:16px 18px; color:white; font-weight:700; background:linear-gradient(135deg, var(--primary), var(--accent)); }
        .settings-card-header.secondary { background:linear-gradient(135deg, rgba(34,211,238,0.85), rgba(14,165,233,0.85)); }
        .settings-card-header.success { background:linear-gradient(135deg, rgba(16,185,129,0.85), rgba(34,197,94,0.85)); }
        .settings-card-header.danger { background:linear-gradient(135deg, rgba(239,68,68,0.9), rgba(185,28,28,0.9)); }
        .settings-card-header.slate { background:linear-gradient(135deg, rgba(15,23,42,0.9), rgba(71,85,105,0.9)); }
        .settings-card-body { padding:20px; }
        .settings-tip { margin-top:12px; padding:12px 14px; border-radius:14px; background:rgba(99,102,241,0.08); color:var(--muted); font-size:13px; line-height:1.6; }
        .settings-section-title { color:var(--primary); margin:30px 0 15px; border-bottom:1px dashed var(--border); padding-bottom:10px; display:flex; align-items:center; gap:8px; }
        .settings-backup { padding:20px; background:var(--card); margin:0; border:1px solid var(--border); border-radius:20px; }
        .settings-backup p { color:var(--muted); font-size:13px; margin-top:6px; }
        .settings-logo-box { text-align:center; margin-top:15px; border:1px dashed var(--border); padding:12px; border-radius:14px; background:rgba(15,23,42,0.4); }
        .settings-logo-box img { height:50px; display:block; margin:0 auto 6px; }
        .settings-row { display:flex; gap:12px; align-items:center; justify-content:space-between; margin-top:10px; flex-wrap:wrap; }
        .settings-inline-badge { padding:6px 12px; border-radius:999px; background:rgba(99,102,241,0.15); color:var(--primary); font-size:12px; font-weight:700; }
    </style>

    <div class="settings-header">
        <h2 class="settings-title">⚙ الإعدادات</h2>
        <div class="settings-actions">
            <span class="settings-badge <?= $smartMode === 'force' ? '' : 'is-warning' ?>">
                <i class="fa-solid fa-wand-magic-sparkles"></i> <?= $smartModeLabel ?>
            </span>
            <button class="btn btn-primary"><i class="fa-solid fa-save"></i> حفظ الإعدادات</button>
        </div>
    </div>

    <div class="settings-overview">
        <div class="settings-overview-card">
            <div class="settings-overview-icon"><i class="fa-solid fa-brain"></i></div>
            <div>
                <div class="settings-overview-title">حالة التمكين الذكي</div>
                <div class="settings-overview-value"><?= $smartModeLabel ?></div>
                <div class="settings-tip" style="margin-top:8px"><?= $smartModeHint ?></div>
            </div>
        </div>
        <div class="settings-overview-card">
            <div class="settings-overview-icon"><i class="fa-solid fa-bell"></i></div>
            <div>
                <div class="settings-overview-title">قنوات التنبيه</div>
                <div class="settings-overview-value"><?= $alertChannelReady ? 'جاهزة' : 'غير مكتملة' ?></div>
                <span class="settings-badge <?= $alertChannelReady ? '' : 'is-warning' ?>">
                    <i class="fa-solid fa-signal"></i> <?= $alertChannelReady ? 'متصلة' : 'يلزم الإعداد' ?>
                </span>
            </div>
        </div>
        <div class="settings-overview-card">
            <div class="settings-overview-icon"><i class="fa-solid fa-building"></i></div>
            <div>
                <div class="settings-overview-title">بيانات المنشأة</div>
                <div class="settings-overview-value"><?= $companyReady ? 'مكتملة' : 'بحاجة مراجعة' ?></div>
                <span class="settings-badge <?= $companyReady ? '' : 'is-warning' ?>">
                    <i class="fa-solid fa-check"></i> <?= $companyReady ? 'جاهزة للطباعة' : 'أكمل البيانات' ?>
                </span>
            </div>
        </div>
        <div class="settings-overview-card">
            <div class="settings-overview-icon"><i class="fa-solid fa-pen-nib"></i></div>
            <div>
                <div class="settings-overview-title">الهوية البصرية</div>
                <div class="settings-overview-value"><?= $brandReady ? 'شعار مرفوع' : 'بدون شعار' ?></div>
                <span class="settings-badge <?= $brandReady ? '' : 'is-warning' ?>">
                    <i class="fa-solid fa-image"></i> <?= $brandReady ? 'محدثة' : 'ارفع الشعار' ?>
                </span>
            </div>
        </div>
        <div class="settings-overview-card">
            <div class="settings-overview-icon"><i class="fa-solid fa-shield-halved"></i></div>
            <div>
                <div class="settings-overview-title">وضع الصيانة</div>
                <div class="settings-overview-value"><?= $maintenanceLabel ?></div>
                <span class="settings-badge <?= $maintenanceEnabled ? 'is-warning' : 'is-neutral' ?>">
                    <i class="fa-solid fa-triangle-exclamation"></i> <?= $maintenanceEnabled ? 'تنبيه ظاهر' : 'تشغيل طبيعي' ?>
                </span>
            </div>
        </div>
    </div>

    <div class="settings-grid">
        <div class="card settings-card">
            <div class="settings-card-header"><i class="fa-solid fa-coins"></i> إعدادات العملة</div>
            <div class="settings-card-body">
                <label class="inp-label">رمز العملة</label>
                <input type="text" name="currency" class="inp" value="<?= $sets['currency'] ?? 'SAR' ?>">
                <label class="inp-label">كود العملة</label>
                <input type="text" name="currency_code" class="inp" value="<?= $sets['currency_code'] ?? 'ر.س' ?>">
                <div class="settings-tip">يظهر رمز العملة في الفواتير والتقارير تلقائياً.</div>
            </div>
        </div>

        <div class="card settings-card">
            <div class="settings-card-header success"><i class="fa-solid fa-percent"></i> إعدادات الضريبة</div>
            <div class="settings-card-body">
                <label class="inp-label">الرقم الضريبي</label>
                <input type="text" name="vat_no" class="inp" value="<?= $sets['vat_no'] ?? '' ?>">
                <label class="inp-label">السجل التجاري</label>
                <input type="text" name="cr_no" class="inp" value="<?= $sets['cr_no'] ?? '' ?>">
                <label class="inp-label">نسبة الضريبة %</label>
                <input type="number" name="vat_percent" class="inp" value="<?= $sets['vat_percent'] ?? '15' ?>">
                <div class="settings-tip">اضبط النسبة لتنعكس على كل الفواتير الجديدة تلقائياً.</div>
            </div>
        </div>

        <div class="card settings-card">
            <div class="settings-card-header"><i class="fa-solid fa-building"></i> معلومات الشركة</div>
            <div class="settings-card-body">
                <label class="inp-label">اسم الشركة</label>
                <input type="text" name="company_name" class="inp" value="<?= $sets['company_name'] ?? '' ?>">
                <label class="inp-label">الهاتف</label>
                <input type="text" name="phone" class="inp" value="<?= $sets['phone'] ?? '' ?>">
                <label class="inp-label">البريد الإلكتروني</label>
                <input type="text" name="email" class="inp" value="<?= $sets['email'] ?? '' ?>">
                <label class="inp-label">العنوان</label>
                <input type="text" name="address" class="inp" value="<?= $sets['address'] ?? '' ?>">
                
                <div class="settings-logo-box">
                    <img src="<?= $logo ?>" style="height:50px; display:block; margin:0 auto 5px">
                    <input type="file" name="logo" style="font-size:12px">
                </div>
                <div class="settings-tip">الشعار يظهر في الفواتير ولوحة التحكم والواجهة الرئيسية.</div>
            </div>
        </div>

        <div class="card settings-card">
            <div class="settings-card-header danger"><i class="fa-solid fa-bell"></i> إعدادات التنبيهات</div>
            <div class="settings-card-body">
                <label class="inp-label">التنبيه قبل موعد المطالبة (يوم)</label>
                <input type="number" name="alert_days" class="inp" value="<?= $sets['alert_days'] ?? '30' ?>">
                <div class="settings-tip">كلما كان الرقم أصغر زادت سرعة التنبيه قبل موعد الاستحقاق.</div>
            </div>
        </div>

        <div class="card settings-card">
            <div class="settings-card-header secondary"><i class="fa-solid fa-chart-line"></i> مؤشرات الأداء الذكية</div>
            <div class="settings-card-body">
                <label class="inp-label">هدف نسبة الإشغال %</label>
                <input type="number" name="target_occupancy" class="inp" value="<?= $sets['target_occupancy'] ?? '90' ?>" step="0.1">
                <label class="inp-label">هدف معدل التحصيل %</label>
                <input type="number" name="target_collection" class="inp" value="<?= $sets['target_collection'] ?? '95' ?>" step="0.1">
                <label class="inp-label">حد تنبيه الدفعات المتأخرة (عدد)</label>
                <input type="number" name="overdue_threshold" class="inp" value="<?= $sets['overdue_threshold'] ?? '5' ?>">
                <div class="settings-tip">يتم مقارنة الأداء الفعلي بالأهداف داخل لوحة القيادة الذكية.</div>
            </div>
        </div>

        <div class="card settings-card">
            <div class="settings-card-header success"><i class="fa-solid fa-robot"></i> قنوات التنبيه الذكية</div>
            <div class="settings-card-body">
                <label class="inp-label">رقم واتساب للتنبيهات</label>
                <input type="text" name="whatsapp_number" class="inp" value="<?= $sets['whatsapp_number'] ?? '' ?>" placeholder="+9665XXXXXXX">
                <label class="inp-label">بريد التقارير الذكية</label>
                <input type="email" name="reporting_email" class="inp" value="<?= $sets['reporting_email'] ?? '' ?>" placeholder="reports@example.com">
                <div class="settings-tip">يتم استخدام هذه القنوات لإرسال إشعارات التعثر والتقارير الأسبوعية.</div>
            </div>
        </div>

        <div class="card settings-card">
            <div class="settings-card-header slate"><i class="fa-solid fa-sliders"></i> التفضيلات العامة</div>
            <div class="settings-card-body">
                <label class="inp-label">المنطقة الزمنية</label>
                <select name="timezone" class="inp">
                    <?php
                    $timezones = ['Asia/Riyadh' => 'الرياض', 'Asia/Dubai' => 'دبي', 'Africa/Cairo' => 'القاهرة', 'Europe/Istanbul' => 'اسطنبول', 'UTC' => 'UTC'];
                    foreach ($timezones as $value => $label):
                    ?>
                        <option value="<?= $value ?>" <?= $timezoneValue === $value ? 'selected' : '' ?>><?= $label ?> (<?= $value ?>)</option>
                    <?php endforeach; ?>
                </select>
                <label class="inp-label">تنسيق التاريخ</label>
                <select name="date_format" class="inp">
                    <?php
                    $formats = ['Y-m-d' => '2024-01-31', 'd/m/Y' => '31/01/2024', 'd-m-Y' => '31-01-2024', 'M d, Y' => 'Jan 31, 2024'];
                    foreach ($formats as $format => $label):
                    ?>
                        <option value="<?= $format ?>" <?= $dateFormatValue === $format ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="settings-row">
                    <span class="settings-inline-badge">المثال الحالي: <?= $dateFormatExample ?></span>
                </div>
                <div class="settings-tip">تنعكس المنطقة الزمنية على التنبيهات والتقارير وتوقيتات النظام.</div>
            </div>
        </div>

        <div class="card settings-card">
            <div class="settings-card-header slate"><i class="fa-solid fa-satellite-dish"></i> تكاملات التمكين الذكي</div>
            <div class="settings-card-body">
                <label class="inp-label">وضع التمكين الذكي</label>
                <select name="smart_features_mode" class="inp">
                    <option value="force" <?= $smartMode === 'force' ? 'selected' : '' ?>>تمكين شامل (تفعيل جميع المميزات)</option>
                    <option value="auto" <?= $smartMode === 'auto' ? 'selected' : '' ?>>حسب التكاملات الفعلية</option>
                </select>
                <label class="inp-label">رابط بوابة الدفع</label>
                <input type="url" name="payment_portal_url" class="inp" value="<?= $sets['payment_portal_url'] ?? '' ?>" placeholder="https://payments.example.com">
                <label class="inp-label">رقم واتساب للإدارة</label>
                <input type="text" name="admin_whatsapp" class="inp" value="<?= $sets['admin_whatsapp'] ?? '' ?>" placeholder="+9665XXXXXXX">
                <label class="inp-label">رابط واجهة واتساب</label>
                <input type="url" name="whatsapp_api_url" class="inp" value="<?= $sets['whatsapp_api_url'] ?? '' ?>" placeholder="https://api.ultramsg.com/instance/messages/chat">
                <label class="inp-label">توكن واتساب</label>
                <input type="password" name="whatsapp_token" class="inp" value="<?= $sets['whatsapp_token'] ?? '' ?>" placeholder="••••••••">
                <label class="inp-label">رابط OCR</label>
                <input type="url" name="ocr_api_url" class="inp" value="<?= $sets['ocr_api_url'] ?? '' ?>" placeholder="https://ocr.example.com">
                <label class="inp-label">مفتاح OCR</label>
                <input type="password" name="ocr_api_key" class="inp" value="<?= $sets['ocr_api_key'] ?? '' ?>" placeholder="••••••••">
                <div class="settings-tip">أضف روابط التكامل لتفعيل الإشعارات الآلية والتعرف على المستندات.</div>
            </div>
        </div>

        <div class="card settings-card">
            <div class="settings-card-header"><i class="fa-solid fa-file-invoice"></i> إعدادات الفواتير</div>
            <div class="settings-card-body">
                <label class="inp-label">بادئة رقم الفاتورة</label>
                <input type="text" name="invoice_prefix" class="inp" value="<?= $sets['invoice_prefix'] ?? 'INV-' ?>">
                <label class="inp-label">ملاحظات الفاتورة</label>
                <textarea name="invoice_terms" class="inp" rows="3"><?= $sets['invoice_terms'] ?? '' ?></textarea>
                <label class="inp-label">مهلة السداد بعد الاستحقاق (يوم)</label>
                <input type="number" name="invoice_grace_days" class="inp" value="<?= $sets['invoice_grace_days'] ?? '5' ?>" min="0">
                <label class="inp-label">طريقة الدفع الافتراضية</label>
                <select name="default_payment_method" class="inp">
                    <?php $paymentMethod = $sets['default_payment_method'] ?? 'bank_transfer'; ?>
                    <option value="bank_transfer" <?= $paymentMethod === 'bank_transfer' ? 'selected' : '' ?>>تحويل بنكي</option>
                    <option value="card" <?= $paymentMethod === 'card' ? 'selected' : '' ?>>بطاقة</option>
                    <option value="cash" <?= $paymentMethod === 'cash' ? 'selected' : '' ?>>نقداً</option>
                    <option value="online" <?= $paymentMethod === 'online' ? 'selected' : '' ?>>بوابة دفع إلكترونية</option>
                </select>
                <div class="settings-tip">يمكنك إضافة شروط الدفع أو تعليمات التحويل البنكي.</div>
            </div>
        </div>

        <div class="card settings-card">
            <div class="settings-card-header danger"><i class="fa-solid fa-triangle-exclamation"></i> وضع الصيانة والإشعارات</div>
            <div class="settings-card-body">
                <label class="inp-label">تفعيل وضع الصيانة</label>
                <select name="maintenance_mode" class="inp">
                    <option value="off" <?= !$maintenanceEnabled ? 'selected' : '' ?>>غير مفعّل</option>
                    <option value="on" <?= $maintenanceEnabled ? 'selected' : '' ?>>مفعّل</option>
                </select>
                <label class="inp-label">رسالة الصيانة المعروضة</label>
                <input type="text" name="maintenance_message" class="inp" value="<?= $sets['maintenance_message'] ?? 'النظام تحت صيانة مجدولة، قد تتأخر بعض الخدمات.' ?>">
                <label class="inp-label">تكرار ملخص التنبيهات</label>
                <select name="alerts_digest" class="inp">
                    <?php $digest = $sets['alerts_digest'] ?? 'weekly'; ?>
                    <option value="instant" <?= $digest === 'instant' ? 'selected' : '' ?>>فوري</option>
                    <option value="daily" <?= $digest === 'daily' ? 'selected' : '' ?>>يومي</option>
                    <option value="weekly" <?= $digest === 'weekly' ? 'selected' : '' ?>>أسبوعي</option>
                </select>
                <div class="settings-tip">يظهر وضع الصيانة كتنبيه واضح للمستخدمين داخل النظام.</div>
            </div>
        </div>

        <div class="card settings-card">
            <div class="settings-card-header secondary"><i class="fa-solid fa-headset"></i> بوابات العملاء والدعم</div>
            <div class="settings-card-body">
                <label class="inp-label">رابط بوابة المستأجرين</label>
                <input type="url" name="tenant_portal_url" class="inp" value="<?= $sets['tenant_portal_url'] ?? '' ?>" placeholder="https://tenants.example.com">
                <label class="inp-label">هاتف الدعم</label>
                <input type="text" name="support_phone" class="inp" value="<?= $sets['support_phone'] ?? '' ?>" placeholder="+9665XXXXXXX">
                <label class="inp-label">بريد الدعم</label>
                <input type="email" name="support_email" class="inp" value="<?= $sets['support_email'] ?? '' ?>" placeholder="support@example.com">
                <div class="settings-tip">يساعد ذلك الفرق التشغيلية على مشاركة قنوات التواصل الرسمية بسرعة.</div>
            </div>
        </div>

        <div class="card settings-card">
            <div class="settings-card-header success"><i class="fa-solid fa-cloud-arrow-down"></i> نسخ احتياطي وأتمتة</div>
            <div class="settings-card-body">
                <label class="inp-label">نسخ احتياطي تلقائي</label>
                <select name="auto_backup" class="inp">
                    <?php $autoBackup = $sets['auto_backup'] ?? 'weekly'; ?>
                    <option value="off" <?= $autoBackup === 'off' ? 'selected' : '' ?>>غير مفعل</option>
                    <option value="daily" <?= $autoBackup === 'daily' ? 'selected' : '' ?>>يومي</option>
                    <option value="weekly" <?= $autoBackup === 'weekly' ? 'selected' : '' ?>>أسبوعي</option>
                    <option value="monthly" <?= $autoBackup === 'monthly' ? 'selected' : '' ?>>شهري</option>
                </select>
                <label class="inp-label">الاحتفاظ بالنسخ (بالأسابيع)</label>
                <input type="number" name="backup_frequency" class="inp" value="<?= $sets['backup_frequency'] ?? '8' ?>" min="1">
                <div class="settings-tip">يساعدك هذا على تنظيم الاحتفاظ بالنسخ الاحتياطية وخطط الأتمتة.</div>
            </div>
        </div>

    </div>
    <h4 class="settings-section-title"><i class="fa-solid fa-shield-halved"></i> النسخ الاحتياطي وصيانة النظام</h4>
    <div class="card settings-backup">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap;">
            <div>
                <h4 style="margin:0; color:white">نسخة احتياطية كاملة (Backup)</h4>
                <p>تحميل نسخة كاملة من قاعدة البيانات والملفات للحفاظ على أمان بياناتك.</p>
            </div>
            <a href="backup.php" class="btn btn-primary" target="_blank">
                <i class="fa-solid fa-download"></i> تحميل النسخة الآن
            </a>
        </div>
    </div>
</form>
