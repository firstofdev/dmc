<?php
if(isset($_POST['save_settings'])){
    check_csrf();
    $keys = [
        'company_name','phone','email','address','currency','vat_no','vat_percent','cr_no',
        'invoice_prefix','invoice_terms','alert_days','target_occupancy','target_collection',
        'overdue_threshold','whatsapp_number','reporting_email'
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
?>

<form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="save_settings" value="1">
    
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px">
        <h2 style="font-weight:800">⚙ الإعدادات</h2>
        <button class="btn btn-primary"><i class="fa-solid fa-save"></i> حفظ الإعدادات</button>
    </div>

    <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:20px;">
        
        <div class="card" style="padding:0; overflow:hidden; border:none;">
            <div style="background:#f59e0b; padding:15px; color:white; font-weight:bold"><i class="fa-solid fa-coins"></i> إعدادات العملة</div>
            <div style="padding:20px;">
                <label class="inp-label">رمز العملة</label>
                <input type="text" name="currency" class="inp" value="<?= $sets['currency'] ?? 'SAR' ?>">
                <label class="inp-label">كود العملة</label>
                <input type="text" class="inp" value="ر.س" disabled>
            </div>
        </div>

        <div class="card" style="padding:0; overflow:hidden; border:none;">
            <div style="background:#10b981; padding:15px; color:white; font-weight:bold"><i class="fa-solid fa-percent"></i> إعدادات الضريبة</div>
            <div style="padding:20px;">
                <label class="inp-label">الرقم الضريبي</label>
                <input type="text" name="vat_no" class="inp" value="<?= $sets['vat_no'] ?? '' ?>">
                <label class="inp-label">السجل التجاري</label>
                <input type="text" name="cr_no" class="inp" value="<?= $sets['cr_no'] ?? '' ?>">
                <label class="inp-label">نسبة الضريبة %</label>
                <input type="number" name="vat_percent" class="inp" value="<?= $sets['vat_percent'] ?? '15' ?>">
            </div>
        </div>

        <div class="card" style="padding:0; overflow:hidden; border:none;">
            <div style="background:#4f46e5; padding:15px; color:white; font-weight:bold"><i class="fa-solid fa-building"></i> معلومات الشركة</div>
            <div style="padding:20px;">
                <label class="inp-label">اسم الشركة</label>
                <input type="text" name="company_name" class="inp" value="<?= $sets['company_name'] ?? '' ?>">
                <label class="inp-label">الهاتف</label>
                <input type="text" name="phone" class="inp" value="<?= $sets['phone'] ?? '' ?>">
                <label class="inp-label">البريد الإلكتروني</label>
                <input type="text" name="email" class="inp" value="<?= $sets['email'] ?? '' ?>">
                <label class="inp-label">العنوان</label>
                <input type="text" name="address" class="inp" value="<?= $sets['address'] ?? '' ?>">
                
                <div style="text-align:center; margin-top:15px; border:1px dashed #444; padding:10px; border-radius:10px">
                    <img src="<?= $logo ?>" style="height:50px; display:block; margin:0 auto 5px">
                    <input type="file" name="logo" style="font-size:12px">
                </div>
            </div>
        </div>

        <div class="card" style="padding:0; overflow:hidden; border:none;">
            <div style="background:#ef4444; padding:15px; color:white; font-weight:bold"><i class="fa-solid fa-bell"></i> إعدادات التنبيهات</div>
            <div style="padding:20px;">
                <label class="inp-label">التنبيه قبل موعد المطالبة (يوم)</label>
                <input type="number" name="alert_days" class="inp" value="<?= $sets['alert_days'] ?? '30' ?>">
            </div>
        </div>

        <div class="card" style="padding:0; overflow:hidden; border:none;">
            <div style="background:#0ea5e9; padding:15px; color:white; font-weight:bold"><i class="fa-solid fa-chart-line"></i> مؤشرات الأداء الذكية</div>
            <div style="padding:20px;">
                <label class="inp-label">هدف نسبة الإشغال %</label>
                <input type="number" name="target_occupancy" class="inp" value="<?= $sets['target_occupancy'] ?? '90' ?>" step="0.1">
                <label class="inp-label">هدف معدل التحصيل %</label>
                <input type="number" name="target_collection" class="inp" value="<?= $sets['target_collection'] ?? '95' ?>" step="0.1">
                <label class="inp-label">حد تنبيه الدفعات المتأخرة (عدد)</label>
                <input type="number" name="overdue_threshold" class="inp" value="<?= $sets['overdue_threshold'] ?? '5' ?>">
            </div>
        </div>

        <div class="card" style="padding:0; overflow:hidden; border:none;">
            <div style="background:#22c55e; padding:15px; color:white; font-weight:bold"><i class="fa-solid fa-robot"></i> قنوات التنبيه الذكية</div>
            <div style="padding:20px;">
                <label class="inp-label">رقم واتساب للتنبيهات</label>
                <input type="text" name="whatsapp_number" class="inp" value="<?= $sets['whatsapp_number'] ?? '' ?>" placeholder="+9665XXXXXXX">
                <label class="inp-label">بريد التقارير الذكية</label>
                <input type="email" name="reporting_email" class="inp" value="<?= $sets['reporting_email'] ?? '' ?>" placeholder="reports@example.com">
            </div>
        </div>

        <div class="card" style="padding:0; overflow:hidden; border:none;">
            <div style="background:#8b5cf6; padding:15px; color:white; font-weight:bold"><i class="fa-solid fa-file-invoice"></i> إعدادات الفواتير</div>
            <div style="padding:20px;">
                <label class="inp-label">بادئة رقم الفاتورة</label>
                <input type="text" name="invoice_prefix" class="inp" value="<?= $sets['invoice_prefix'] ?? 'INV-' ?>">
                <label class="inp-label">ملاحظات الفاتورة</label>
                <textarea name="invoice_terms" class="inp" rows="3"><?= $sets['invoice_terms'] ?? '' ?></textarea>
            </div>
        </div>

    </div>
    <h4 style="color:var(--primary); margin:30px 0 15px; border-bottom:1px dashed #333; padding-bottom:10px">4. النسخ الاحتياطي وصيانة النظام</h4>
        <div class="card" style="padding:20px; background:#1a1a1a; margin:0">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <h4 style="margin:0; color:white">نسخة احتياطية كاملة (Backup)</h4>
                    <p style="color:#888; font-size:13px; margin-top:5px">تحميل نسخة كاملة من قاعدة البيانات والملفات للحفاظ على أمان بياناتك.</p>
                </div>
                <a href="backup.php" class="btn btn-primary" target="_blank">
                    <i class="fa-solid fa-download"></i> تحميل النسخة الآن
                </a>
            </div>
        </div>
</form>
