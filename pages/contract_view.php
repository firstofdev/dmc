<?php
// منع الصفحة البيضاء وعرض الأخطاء إن وجدت
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// التأكد من المجلد
if (!is_dir('uploads')) { mkdir('uploads', 0777, true); }

$id = $_GET['id'] ?? 0;

// جلب البيانات بأمان (LEFT JOIN لتجنب الأخطاء إذا كانت بعض البيانات ناقصة)
$stmt = $pdo->prepare("
    SELECT c.*, t.name as tname, t.phone, u.unit_name, u.type, u.elec_meter_no, u.water_meter_no
    FROM contracts c 
    LEFT JOIN tenants t ON c.tenant_id = t.id 
    LEFT JOIN units u ON c.unit_id = u.id 
    WHERE c.id = ?
");
$stmt->execute([$id]);
$c = $stmt->fetch();

if (!$c) {
    die("<div class='alert alert-danger'>عفواً، العقد غير موجود أو تم حذفه. <a href='index.php?p=contracts'>عودة</a></div>");
}

// معالجة حفظ التوقيع
if (isset($_POST['save_sig'])) {
    $img = $_POST['sig_data'];
    $img = str_replace('data:image/png;base64,', '', $img);
    $data = base64_decode($img);
    $file = 'uploads/sig_' . $id . '_' . time() . '.png';
    file_put_contents($file, $data);
    $pdo->prepare("UPDATE contracts SET signature_img = ? WHERE id = ?")->execute([$file, $id]);
    echo "<script>window.location.href='index.php?p=contract_view&id=$id';</script>";
}

// معالجة حفظ الصور
if (isset($_POST['save_photo'])) {
    $type = $_POST['photo_type']; // check_in or check_out
    
    // التحقق من العدد (10 صور كحد أقصى)
    $count = $pdo->query("SELECT COUNT(*) FROM inspection_photos WHERE contract_id=$id AND photo_type='$type'")->fetchColumn();
    
    if ($count >= 10) {
        echo "<script>alert('تنبيه: الحد الأقصى 10 صور فقط لهذه المرحلة.'); window.location.href='index.php?p=contract_view&id=$id';</script>";
    } else {
        $img = $_POST['photo_data'];
        $img = str_replace('data:image/png;base64,', '', $img);
        $data = base64_decode($img);
        $file = 'uploads/insp_' . $type . '_' . uniqid() . '.png';
        file_put_contents($file, $data);
        
        $pdo->prepare("INSERT INTO inspection_photos (contract_id, photo_type, photo_path) VALUES (?, ?, ?)")->execute([$id, $type, $file]);
        echo "<script>window.location.href='index.php?p=contract_view&id=$id';</script>";
    }
}

$meterError = '';
if (isset($_POST['save_meter'])) {
    $readingType = $_POST['reading_type'] ?? 'periodic';
    $allowedTypes = ['check_in', 'check_out', 'periodic'];
    if (!in_array($readingType, $allowedTypes, true)) {
        $readingType = 'periodic';
    }
    $readingDate = $_POST['reading_date'] ?: date('Y-m-d');
    $elecReading = trim($_POST['elec_reading'] ?? '');
    $waterReading = trim($_POST['water_reading'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $elecValue = $elecReading === '' ? null : (float) $elecReading;
    $waterValue = $waterReading === '' ? null : (float) $waterReading;

    try {
        $stmt = $pdo->prepare("INSERT INTO meter_readings (contract_id, unit_id, reading_type, elec_reading, water_reading, reading_date, notes) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([$id, $c['unit_id'], $readingType, $elecValue, $waterValue, $readingDate, $notes]);
        echo "<script>window.location.href='index.php?p=contract_view&id=$id';</script>";
    } catch (Exception $e) {
        $meterError = 'تعذر حفظ قراءة العداد. تأكد من إنشاء جدول القراءات.';
    }
}

$meterRows = [];
try {
    $meterStmt = $pdo->prepare("SELECT * FROM meter_readings WHERE contract_id = ? ORDER BY reading_date DESC, id DESC");
    $meterStmt->execute([$id]);
    $meterRows = $meterStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $meterRows = [];
}

$amountParts = contract_amount_parts($c);
$taxIncluded = $amountParts['tax_included'];
$taxAmount = $amountParts['tax_amount'];
$taxPercent = $amountParts['tax_percent'];
$baseAmount = $amountParts['base_amount'];
$currencyCode = get_setting('currency_code', 'ر.س');
?>

<style>
    .contract-header { background: #222; padding: 20px; border-radius: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
    .nav-tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
    .nav-btn { background: none; border: none; color: #aaa; padding: 10px 20px; cursor: pointer; font-size: 16px; font-weight: bold; }
    .nav-btn.active { color: #6366f1; border-bottom: 3px solid #6366f1; }
    
    /* منطقة التوقيع */
    .sig-wrapper { background: white; border-radius: 10px; padding: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    .sig-tools { display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-top:12px; }
    .sig-meta { display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:10px; margin-top:12px; }
    .sig-meta input { width:100%; padding:8px 10px; border-radius:8px; border:1px solid #d1d5db; }
    .sig-consent { display:flex; align-items:center; gap:8px; margin-top:10px; font-size:14px; color:#374151; }
    .sig-strength { margin-top:10px; display:flex; align-items:center; gap:10px; }
    .sig-strength-bar { flex:1; height:8px; background:#e5e7eb; border-radius:999px; overflow:hidden; }
    .sig-strength-bar span { display:block; height:100%; width:0%; background:linear-gradient(90deg, #f59e0b, #10b981); transition:width 0.3s ease; }
    .sig-strength-text { font-size:13px; color:#6b7280; min-width:110px; text-align:end; }
    .sig-tips { margin-top:10px; font-size:13px; color:#6b7280; line-height:1.6; }
    canvas { width: 100%; height: 250px; border: 2px dashed #ccc; touch-action: none; }
    
    /* منطقة الكاميرا */
    .cam-wrapper { background: black; border-radius: 10px; overflow: hidden; position: relative; height: 300px; }
    .cam-overlay { position:absolute; inset:0; pointer-events:none; background-image: linear-gradient(to right, rgba(255,255,255,0.2) 1px, transparent 1px), linear-gradient(to bottom, rgba(255,255,255,0.2) 1px, transparent 1px); background-size: 33.333% 33.333%; mix-blend-mode: soft-light; }
    .cam-badge { position:absolute; bottom:10px; left:10px; padding:6px 10px; border-radius:999px; font-size:12px; background:rgba(17,24,39,0.75); color:#fff; }
    .cam-hint { margin-top:8px; font-size:13px; color:#6b7280; }
    .quality-chip { display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; font-size:12px; background:#111827; color:#fff; margin-top:10px; }
    .quality-chip strong { color:#fbbf24; }
    video { width: 100%; height: 100%; object-fit: cover; }
    
    /* شبكة الصور */
    .gallery { display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 10px; margin-top: 15px; }
    .gallery img { width: 100%; height: 80px; object-fit: cover; border-radius: 5px; border: 1px solid #444; cursor: pointer; }
</style>

<div class="contract-header">
    <div>
        <h2 style="margin:0">عقد إيجار #<?= $c['id'] ?></h2>
        <div style="color:#aaa; font-size:14px; margin-top:5px">
            <i class="fa-solid fa-user"></i> <?= $c['tname'] ?> &nbsp;|&nbsp; 
            <i class="fa-solid fa-building"></i> <?= $c['unit_name'] ?> (<?= $c['type'] ?>)
        </div>
    </div>
    <a href="index.php?p=contracts" class="btn btn-dark"><i class="fa-solid fa-arrow-right"></i> رجوع</a>
</div>

<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:12px; margin-bottom:18px;">
    <div class="card" style="padding:14px;">
        <div style="font-size:12px; color:#9ca3af;">قيمة العقد الأساسية</div>
        <div style="font-size:22px; font-weight:800; margin-top:6px; color:#a5b4fc;">
            <?= number_format($baseAmount, 2) ?> <?= htmlspecialchars($currencyCode) ?>
        </div>
    </div>
    <div class="card" style="padding:14px;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <span style="font-size:12px; color:#9ca3af;">الضريبة</span>
            <span style="padding:6px 10px; border-radius:999px; font-size:12px; background:<?= $taxIncluded ? '#14532d' : '#1f2937' ?>; color:<?= $taxIncluded ? '#bbf7d0' : '#e5e7eb' ?>;">
                <?= $taxIncluded ? 'مشمول بالضريبة' : 'بدون ضريبة' ?>
            </span>
        </div>
        <div style="font-size:22px; font-weight:800; margin-top:6px; color:#facc15;">
            <?= $taxIncluded ? number_format($taxAmount, 2) . ' (' . $taxPercent . '%)' : '0.00' ?> <?= htmlspecialchars($currencyCode) ?>
        </div>
    </div>
    <div class="card" style="padding:14px; background:linear-gradient(135deg, #6366f1, #0ea5e9); color:white;">
        <div style="font-size:12px; opacity:0.8;">الإجمالي النهائي</div>
        <div style="font-size:24px; font-weight:900; margin-top:6px;">
            <?= number_format($c['total_amount'], 2) ?> <?= htmlspecialchars($currencyCode) ?>
        </div>
    </div>
</div>

<div class="nav-tabs">
    <button onclick="switchTab('sig')" class="nav-btn active" id="btn-sig">✍️ التوقيع الإلكتروني</button>
    <button onclick="switchTab('in')" class="nav-btn" id="btn-in">📷 صور الاستلام (قبل)</button>
    <button onclick="switchTab('out')" class="nav-btn" id="btn-out">📸 صور التسليم (بعد)</button>
    <button onclick="switchTab('meters')" class="nav-btn" id="btn-meters">⚡ عدادات الكهرباء والماء</button>
</div>

<div id="tab-sig" style="display:block;">
    <div class="card">
        <h3>توقيع المستأجر</h3>
        <?php if ($c['signature_img']): ?>
            <div style="text-align:center; padding:20px; background:#fff; border-radius:10px;">
                <img src="<?= $c['signature_img'] ?>" style="max-height:150px; border:1px solid #ccc;">
                <div style="color:green; margin-top:10px; font-weight:bold;">
                    <i class="fa-solid fa-circle-check"></i> تم التوقيع والحفظ
                </div>
                <button type="button" class="btn btn-dark" style="margin-top:12px;" onclick="toggleSignatureEdit(true)">توقيع جديد</button>
            </div>
        <?php endif; ?>
        <div id="sig-editor" style="<?= $c['signature_img'] ? 'display:none;' : 'display:block;' ?>">
            <div class="sig-wrapper">
                <canvas id="signature-pad"></canvas>
            </div>
            <div class="sig-strength">
                <div class="sig-strength-bar"><span id="sig-strength-bar"></span></div>
                <div class="sig-strength-text" id="sig-strength-text">قوة التوقيع: --</div>
            </div>
            <div class="sig-meta">
                <input type="text" id="sig-name" placeholder="اسم الموقّع" autocomplete="name">
                <input type="text" id="sig-id" placeholder="رقم الهوية / الإقامة">
            </div>
            <div class="sig-consent">
                <input type="checkbox" id="sig-consent">
                <label for="sig-consent">أقر بأن هذا التوقيع إلكتروني ويعبّر عن موافقتي.</label>
            </div>
            <div class="sig-tools">
                <button type="button" onclick="saveSignature()" class="btn btn-primary" style="flex:1">حفظ التوقيع الذكي</button>
                <button type="button" onclick="undoSignature()" class="btn btn-dark">تراجع خطوة</button>
                <button type="button" onclick="clearSignature()" class="btn btn-dark">مسح</button>
                <?php if ($c['signature_img']): ?>
                    <button type="button" onclick="toggleSignatureEdit(false)" class="btn btn-dark">إلغاء</button>
                <?php endif; ?>
            </div>
            <div class="sig-tips">
                <strong>نصائح ذكية:</strong> حرّك يدك بشكل طبيعي، املأ مساحة معقولة من اللوحة، وتأكد من وضوح الاسم والهوية لتقوية التوقيع.
            </div>
            <form id="sigForm" method="POST" style="display:none;">
                <input type="hidden" name="save_sig" value="1">
                <input type="hidden" name="sig_data" id="sig-data-input">
            </form>
        </div>
    </div>
</div>

<div id="tab-in" style="display:none;">
    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
        <div class="card">
            <h4><i class="fa-solid fa-camera"></i> التقاط صور (قبل الاستلام)</h4>
            <div class="cam-wrapper">
                <video id="video-in" autoplay playsinline></video>
                <div class="cam-overlay"></div>
                <div class="cam-badge">وضع التحقق الذكي: قبل الاستلام</div>
            </div>
            <div class="quality-chip" id="quality-in">جودة الصورة: <strong>--</strong></div>
            <div class="cam-hint">نصيحة: التقط زوايا الغرف والأرضيات والأسقف، وتأكد من الإضاءة الكافية.</div>
            <button onclick="takeSnapshot('check_in')" class="btn btn-primary" style="width:100%; margin-top:10px;">التقاط وحفظ الصورة</button>
        </div>
        <div class="card">
            <h4>الصور المحفوظة (<?= $pdo->query("SELECT COUNT(*) FROM inspection_photos WHERE contract_id=$id AND photo_type='check_in'")->fetchColumn() ?>/10)</h4>
            <div class="gallery">
                <?php 
                $photos = $pdo->query("SELECT * FROM inspection_photos WHERE contract_id=$id AND photo_type='check_in'");
                $photoRows = $photos->fetchAll();
                foreach ($photoRows as $p) {
                    echo "<a href='{$p['photo_path']}' target='_blank'><img src='{$p['photo_path']}'></a>";
                }
                if (count($photoRows) === 0) {
                    echo "<div style='color:#6b7280; font-size:13px;'>لا توجد صور محفوظة بعد.</div>";
                }
                ?>
            </div>
        </div>
    </div>
</div>

<div id="tab-out" style="display:none;">
    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
        <div class="card">
            <h4><i class="fa-solid fa-camera"></i> التقاط صور (بعد التسليم)</h4>
            <div class="cam-wrapper">
                <video id="video-out" autoplay playsinline></video>
                <div class="cam-overlay"></div>
                <div class="cam-badge">وضع التحقق الذكي: بعد التسليم</div>
            </div>
            <div class="quality-chip" id="quality-out">جودة الصورة: <strong>--</strong></div>
            <div class="cam-hint">نصيحة: ركّز على أي أضرار أو تغييرات، وصوّر نفس الزوايا السابقة للمقارنة.</div>
            <button onclick="takeSnapshot('check_out')" class="btn btn-danger" style="width:100%; margin-top:10px;">التقاط وحفظ الصورة</button>
        </div>
        <div class="card">
            <h4>الصور المحفوظة (<?= $pdo->query("SELECT COUNT(*) FROM inspection_photos WHERE contract_id=$id AND photo_type='check_out'")->fetchColumn() ?>/10)</h4>
            <div class="gallery">
                <?php 
                $photos = $pdo->query("SELECT * FROM inspection_photos WHERE contract_id=$id AND photo_type='check_out'");
                $photoRows = $photos->fetchAll();
                foreach ($photoRows as $p) {
                    echo "<a href='{$p['photo_path']}' target='_blank'><img src='{$p['photo_path']}'></a>";
                }
                if (count($photoRows) === 0) {
                    echo "<div style='color:#6b7280; font-size:13px;'>لا توجد صور محفوظة بعد.</div>";
                }
                ?>
            </div>
        </div>
    </div>
</div>

<div id="tab-meters" style="display:none;">
    <div style="display:grid; grid-template-columns: 1.1fr 1fr; gap:20px;">
        <div class="card">
            <h4><i class="fa-solid fa-gauge-high"></i> تسجيل قراءة جديدة</h4>
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:12px; margin-bottom:15px;">
                <div style="background:#0f172a; padding:10px; border-radius:10px;">
                    <div style="font-size:12px; color:#94a3b8;">عداد الكهرباء</div>
                    <div style="font-size:18px; font-weight:700;"><?= htmlspecialchars($c['elec_meter_no'] ?? '-') ?></div>
                </div>
                <div style="background:#0f172a; padding:10px; border-radius:10px;">
                    <div style="font-size:12px; color:#94a3b8;">عداد المياه</div>
                    <div style="font-size:18px; font-weight:700;"><?= htmlspecialchars($c['water_meter_no'] ?? '-') ?></div>
                </div>
            </div>
            <?php if ($meterError): ?>
                <div style="background:#fee2e2; color:#991b1b; padding:10px; border-radius:8px; margin-bottom:12px;">
                    <?= htmlspecialchars($meterError) ?>
                </div>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="save_meter" value="1">
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:12px;">
                    <div>
                        <label class="inp-label">نوع القراءة</label>
                        <select name="reading_type" class="inp" required>
                            <option value="check_in">استلام</option>
                            <option value="check_out">تسليم</option>
                            <option value="periodic">دورية</option>
                        </select>
                    </div>
                    <div>
                        <label class="inp-label">تاريخ القراءة</label>
                        <input type="date" name="reading_date" class="inp" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div>
                        <label class="inp-label">قراءة الكهرباء</label>
                        <input type="number" step="0.01" name="elec_reading" class="inp" placeholder="مثال: 1250.5">
                    </div>
                    <div>
                        <label class="inp-label">قراءة المياه</label>
                        <input type="number" step="0.01" name="water_reading" class="inp" placeholder="مثال: 320.2">
                    </div>
                </div>
                <div style="margin-top:12px;">
                    <label class="inp-label">ملاحظات</label>
                    <textarea name="notes" class="inp" rows="3" placeholder="أي ملاحظات حول الاستهلاك أو حالة العدادات"></textarea>
                </div>
                <button class="btn btn-primary" style="margin-top:12px; width:100%; justify-content:center;">
                    حفظ القراءة الذكية
                </button>
            </form>
        </div>
        <div class="card">
            <h4><i class="fa-solid fa-bolt"></i> سجل القراءات</h4>
            <?php if (!empty($meterRows)): ?>
                <div style="overflow:auto;">
                    <table style="width:100%; border-collapse:collapse;">
                        <thead>
                            <tr style="background:#111827; text-align:right;">
                                <th style="padding:8px;">التاريخ</th>
                                <th style="padding:8px;">النوع</th>
                                <th style="padding:8px;">الكهرباء</th>
                                <th style="padding:8px;">المياه</th>
                                <th style="padding:8px;">الاستهلاك</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for ($i = 0; $i < count($meterRows); $i++): ?>
                                <?php
                                    $row = $meterRows[$i];
                                    $nextRow = $meterRows[$i + 1] ?? null;
                                    $elecDelta = ($nextRow && $row['elec_reading'] !== null && $nextRow['elec_reading'] !== null)
                                        ? $row['elec_reading'] - $nextRow['elec_reading'] : null;
                                    $waterDelta = ($nextRow && $row['water_reading'] !== null && $nextRow['water_reading'] !== null)
                                        ? $row['water_reading'] - $nextRow['water_reading'] : null;
                                ?>
                                <tr style="border-bottom:1px solid #1f2937;">
                                    <td style="padding:8px;"><?= htmlspecialchars($row['reading_date']) ?></td>
                                    <td style="padding:8px;">
                                        <?= $row['reading_type'] === 'check_in' ? 'استلام' : ($row['reading_type'] === 'check_out' ? 'تسليم' : 'دورية') ?>
                                    </td>
                                    <td style="padding:8px;"><?= $row['elec_reading'] !== null ? number_format((float) $row['elec_reading'], 2) : '-' ?></td>
                                    <td style="padding:8px;"><?= $row['water_reading'] !== null ? number_format((float) $row['water_reading'], 2) : '-' ?></td>
                                    <td style="padding:8px; color:#38bdf8;">
                                        <?php
                                            $parts = [];
                                            if ($elecDelta !== null) {
                                                $parts[] = '⚡ ' . number_format($elecDelta, 2);
                                            }
                                            if ($waterDelta !== null) {
                                                $parts[] = '💧 ' . number_format($waterDelta, 2);
                                            }
                                            echo $parts ? implode(' | ', $parts) : '-';
                                        ?>
                                    </td>
                                </tr>
                                <?php if (!empty($row['notes'])): ?>
                                    <tr>
                                        <td colspan="5" style="padding:8px; color:#94a3b8; font-size:12px;"><?= htmlspecialchars($row['notes']) ?></td>
                                    </tr>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="color:#94a3b8;">لا توجد قراءات محفوظة بعد. ابدأ بتسجيل أول قراءة.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<form id="photoForm" method="POST" style="display:none;">
    <input type="hidden" name="save_photo" value="1">
    <input type="hidden" name="photo_type" id="p-type-input">
    <input type="hidden" name="photo_data" id="p-data-input">
</form>

<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
<script>
    // 1. التحكم في التبويبات
    function switchTab(tabId) {
        // إخفاء الكل
        document.getElementById('tab-sig').style.display = 'none';
        document.getElementById('tab-in').style.display = 'none';
        document.getElementById('tab-out').style.display = 'none';
        document.getElementById('tab-meters').style.display = 'none';
        
        // إزالة الكلاس النشط
        document.getElementById('btn-sig').classList.remove('active');
        document.getElementById('btn-in').classList.remove('active');
        document.getElementById('btn-out').classList.remove('active');
        document.getElementById('btn-meters').classList.remove('active');
        
        // تفعيل المطلوب
        document.getElementById('tab-'+tabId).style.display = 'block';
        document.getElementById('btn-'+tabId).classList.add('active');
        
        // تشغيل الكاميرا إذا لزم الأمر
        if(tabId === 'in') startCamera('video-in');
        if(tabId === 'out') startCamera('video-out');
    }

    // 2. إعداد التوقيع
    var canvas = document.getElementById('signature-pad');
    var signaturePad;
    var sigRatio = 1;
    var sigStrengthBar = document.getElementById('sig-strength-bar');
    var sigStrengthText = document.getElementById('sig-strength-text');

    if (canvas) {
        // ضبط أبعاد الكانفاس لتكون دقيقة
        function resizeCanvas() {
            sigRatio = Math.max(window.devicePixelRatio || 1, 1);
            canvas.width = canvas.offsetWidth * sigRatio;
            canvas.height = canvas.offsetHeight * sigRatio;
            canvas.getContext("2d").scale(sigRatio, sigRatio);
        }
        window.onresize = resizeCanvas;
        resizeCanvas();
        
        signaturePad = new SignaturePad(canvas, { 
            backgroundColor: 'rgb(255, 255, 255)',
            minWidth: 1.2,
            maxWidth: 3.2,
            velocityFilterWeight: 0.7
        });
        signaturePad.onEnd = updateSignatureStrength;
        updateSignatureStrength();
    }

    function toggleSignatureEdit(show) {
        var editor = document.getElementById('sig-editor');
        if (!editor) return;
        editor.style.display = show ? 'block' : 'none';
        if (show && signaturePad) {
            clearSignature();
            updateSignatureStrength();
        }
    }

    function clearSignature() {
        if(signaturePad) signaturePad.clear();
        updateSignatureStrength();
    }

    function undoSignature() {
        if (!signaturePad) return;
        var data = signaturePad.toData();
        if (data.length) {
            data.pop();
            signaturePad.fromData(data);
        }
        updateSignatureStrength();
    }

    function updateSignatureStrength() {
        if (!signaturePad || !sigStrengthBar || !sigStrengthText) return;
        var data = signaturePad.toData();
        if (!data.length) {
            sigStrengthBar.style.width = '0%';
            sigStrengthText.textContent = 'قوة التوقيع: --';
            return;
        }
        var metrics = calculateSignatureMetrics(data);
        var score = metrics.score;
        var label = 'ضعيف';
        if (score >= 70) {
            label = 'قوي';
        } else if (score >= 40) {
            label = 'متوسط';
        }
        sigStrengthBar.style.width = score + '%';
        sigStrengthText.textContent = 'قوة التوقيع: ' + label + ' (' + score + '%)';
    }

    function calculateSignatureMetrics(data) {
        var minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
        var totalLength = 0;
        var pointCount = 0;
        data.forEach(function (stroke) {
            var points = stroke.points || [];
            for (var i = 0; i < points.length; i++) {
                var x = points[i].x / sigRatio;
                var y = points[i].y / sigRatio;
                minX = Math.min(minX, x);
                minY = Math.min(minY, y);
                maxX = Math.max(maxX, x);
                maxY = Math.max(maxY, y);
                pointCount++;
                if (i > 0) {
                    var prev = points[i - 1];
                    var dx = (points[i].x - prev.x) / sigRatio;
                    var dy = (points[i].y - prev.y) / sigRatio;
                    totalLength += Math.sqrt(dx * dx + dy * dy);
                }
            }
        });
        var canvasWidth = canvas.offsetWidth || 1;
        var canvasHeight = canvas.offsetHeight || 1;
        var area = Math.max(0, (maxX - minX) * (maxY - minY));
        var areaRatio = Math.min(area / (canvasWidth * canvasHeight), 1);
        var lengthScore = Math.min(totalLength / (canvasWidth * 1.5), 1);
        var strokeScore = Math.min(data.length / 6, 1);
        var score = Math.round((areaRatio * 50) + (lengthScore * 35) + (strokeScore * 15));
        score = Math.min(Math.max(score, 0), 100);
        return { score: score, points: pointCount };
    }

    function buildSignatureImage(name, idNumber) {
        var metaHeight = 70 * sigRatio;
        var exportCanvas = document.createElement('canvas');
        exportCanvas.width = canvas.width;
        exportCanvas.height = canvas.height + metaHeight;
        var ctx = exportCanvas.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, exportCanvas.width, exportCanvas.height);
        ctx.drawImage(canvas, 0, 0);
        ctx.strokeStyle = '#e5e7eb';
        ctx.lineWidth = 1 * sigRatio;
        ctx.beginPath();
        ctx.moveTo(20 * sigRatio, canvas.height + 10 * sigRatio);
        ctx.lineTo(exportCanvas.width - 20 * sigRatio, canvas.height + 10 * sigRatio);
        ctx.stroke();
        ctx.fillStyle = '#111827';
        ctx.font = (14 * sigRatio) + 'px Arial';
        ctx.textAlign = 'left';
        ctx.fillText('الاسم: ' + name, 20 * sigRatio, canvas.height + 32 * sigRatio);
        ctx.fillText('الهوية: ' + idNumber, 20 * sigRatio, canvas.height + 52 * sigRatio);
        ctx.textAlign = 'right';
        ctx.fillText('التاريخ: ' + new Date().toLocaleString('ar-SA'), exportCanvas.width - 20 * sigRatio, canvas.height + 52 * sigRatio);
        ctx.textAlign = 'left';
        return exportCanvas.toDataURL('image/png');
    }

    function saveSignature() {
        if (!signaturePad || signaturePad.isEmpty()) {
            alert("الرجاء التوقيع أولاً.");
            return;
        }
        var name = document.getElementById('sig-name').value.trim();
        var idNumber = document.getElementById('sig-id').value.trim();
        var consent = document.getElementById('sig-consent').checked;
        if (!name || !idNumber) {
            alert("فضلاً أدخل اسم الموقّع ورقم الهوية.");
            return;
        }
        if (!consent) {
            alert("يرجى الموافقة على الإقرار لإتمام الحفظ.");
            return;
        }
        document.getElementById('sig-data-input').value = buildSignatureImage(name, idNumber);
        document.getElementById('sigForm').submit();
    }

    // 3. إعداد الكاميرا
    function startCamera(videoId) {
        var video = document.getElementById(videoId);
        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } })
            .then(function (stream) {
                video.srcObject = stream;
                video.play();
            })
            .catch(function (err) {
                console.log("Error accessing camera: " + err);
            });
        }
    }

    function updatePhotoQualityBadge(type, analysis) {
        var badgeId = type === 'check_in' ? 'quality-in' : 'quality-out';
        var badge = document.getElementById(badgeId);
        if (!badge) return;
        var label = analysis.label || '--';
        badge.innerHTML = 'جودة الصورة: <strong>' + label + '</strong>';
    }

    function analyzeFrame(sourceCanvas) {
        var targetWidth = 200;
        var scale = targetWidth / sourceCanvas.width;
        var targetHeight = Math.max(1, Math.round(sourceCanvas.height * scale));
        var sample = document.createElement('canvas');
        sample.width = targetWidth;
        sample.height = targetHeight;
        var sctx = sample.getContext('2d');
        sctx.drawImage(sourceCanvas, 0, 0, targetWidth, targetHeight);
        var image = sctx.getImageData(0, 0, targetWidth, targetHeight);
        var data = image.data;
        var gray = new Float32Array(targetWidth * targetHeight);
        var sum = 0;
        for (var i = 0, j = 0; i < data.length; i += 4, j++) {
            var g = data[i] * 0.299 + data[i + 1] * 0.587 + data[i + 2] * 0.114;
            gray[j] = g;
            sum += g;
        }
        var avg = sum / gray.length;
        var lapSum = 0;
        var lapSq = 0;
        for (var y = 1; y < targetHeight - 1; y++) {
            for (var x = 1; x < targetWidth - 1; x++) {
                var idx = y * targetWidth + x;
                var lap = -4 * gray[idx] + gray[idx - 1] + gray[idx + 1] + gray[idx - targetWidth] + gray[idx + targetWidth];
                lapSum += lap;
                lapSq += lap * lap;
            }
        }
        var count = (targetWidth - 2) * (targetHeight - 2);
        var variance = count ? (lapSq / count) - Math.pow(lapSum / count, 2) : 0;
        var brightnessScore = 1 - Math.min(Math.abs(avg - 140) / 140, 1);
        var sharpnessScore = Math.min(variance / 500, 1);
        var score = Math.round((brightnessScore * 0.5 + sharpnessScore * 0.5) * 100);
        var label = 'بحاجة لتحسين';
        if (score >= 75) {
            label = 'ممتازة';
        } else if (score >= 55) {
            label = 'جيدة';
        }
        return { brightness: avg, sharpness: variance, score: score, label: label };
    }

    function getLocation() {
        return new Promise(function (resolve) {
            if (!navigator.geolocation) {
                resolve(null);
                return;
            }
            navigator.geolocation.getCurrentPosition(
                function (pos) {
                    resolve({ lat: pos.coords.latitude, lng: pos.coords.longitude });
                },
                function () { resolve(null); },
                { enableHighAccuracy: true, timeout: 5000 }
            );
        });
    }

    function addPhotoStamp(photoCanvas, meta) {
        var ctx = photoCanvas.getContext('2d');
        var baseHeight = meta.location ? 70 : 50;
        var barHeight = Math.round(baseHeight);
        ctx.fillStyle = 'rgba(17,24,39,0.6)';
        ctx.fillRect(0, photoCanvas.height - barHeight, photoCanvas.width, barHeight);
        ctx.fillStyle = '#ffffff';
        ctx.font = '16px Arial';
        ctx.textAlign = 'left';
        ctx.fillText(meta.line1, 16, photoCanvas.height - barHeight + 30);
        if (meta.location) {
            ctx.font = '14px Arial';
            ctx.fillText('الموقع: ' + meta.location.lat.toFixed(5) + ', ' + meta.location.lng.toFixed(5), 16, photoCanvas.height - 12);
        }
    }

    async function takeSnapshot(type) {
        var videoId = (type === 'check_in') ? 'video-in' : 'video-out';
        var video = document.getElementById(videoId);
        if (!video || !video.videoWidth) {
            alert('يرجى انتظار تشغيل الكاميرا أولاً.');
            return;
        }
        if (video.videoWidth < 640 || video.videoHeight < 480) {
            alert('دقة الكاميرا منخفضة. يفضل استخدام دقة أعلى لتحسين جودة التوثيق.');
            return;
        }
        
        // إنشاء كانفاس مؤقت للرسم
        var shotCanvas = document.createElement('canvas');
        shotCanvas.width = video.videoWidth;
        shotCanvas.height = video.videoHeight;
        var ctx = shotCanvas.getContext('2d');
        ctx.drawImage(video, 0, 0, shotCanvas.width, shotCanvas.height);

        var analysis = analyzeFrame(shotCanvas);
        updatePhotoQualityBadge(type, analysis);
        var warnings = [];
        if (analysis.brightness < 70) warnings.push('الإضاءة منخفضة');
        if (analysis.brightness > 220) warnings.push('الإضاءة عالية جداً');
        if (analysis.sharpness < 120) warnings.push('الصورة غير حادة');
        if (warnings.length) {
            var proceed = confirm('تنبيه الجودة: ' + warnings.join('، ') + '. هل تريد المتابعة؟');
            if (!proceed) return;
        }

        var location = await getLocation();
        var typeLabel = type === 'check_in' ? 'قبل الاستلام' : 'بعد التسليم';
        var line1 = 'عقد #' + <?= json_encode($c['id']) ?> + ' • ' + typeLabel + ' • ' + new Date().toLocaleString('ar-SA');
        addPhotoStamp(shotCanvas, { line1: line1, location: location });
        
        // تحويل الصورة لنص وارسالها
        var dataURI = shotCanvas.toDataURL('image/png');
        document.getElementById('p-type-input').value = type;
        document.getElementById('p-data-input').value = dataURI;
        document.getElementById('photoForm').submit();
    }
</script>
