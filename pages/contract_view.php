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
    SELECT c.*, t.name as tname, t.phone, u.unit_name, u.type 
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
?>

<style>
    .contract-header { background: #222; padding: 20px; border-radius: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
    .nav-tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
    .nav-btn { background: none; border: none; color: #aaa; padding: 10px 20px; cursor: pointer; font-size: 16px; font-weight: bold; }
    .nav-btn.active { color: #6366f1; border-bottom: 3px solid #6366f1; }
    
    /* منطقة التوقيع */
    .sig-wrapper { background: white; border-radius: 10px; padding: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    canvas { width: 100%; height: 250px; border: 2px dashed #ccc; touch-action: none; }
    
    /* منطقة الكاميرا */
    .cam-wrapper { background: black; border-radius: 10px; overflow: hidden; position: relative; height: 300px; }
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

<div class="nav-tabs">
    <button onclick="switchTab('sig')" class="nav-btn active" id="btn-sig">✍️ التوقيع الإلكتروني</button>
    <button onclick="switchTab('in')" class="nav-btn" id="btn-in">📷 صور الاستلام (قبل)</button>
    <button onclick="switchTab('out')" class="nav-btn" id="btn-out">📸 صور التسليم (بعد)</button>
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
            </div>
        <?php else: ?>
            <div class="sig-wrapper">
                <canvas id="signature-pad"></canvas>
            </div>
            <div style="margin-top:15px; display:flex; gap:10px;">
                <button type="button" onclick="saveSignature()" class="btn btn-primary" style="flex:1">حفظ التوقيع</button>
                <button type="button" onclick="clearSignature()" class="btn btn-dark">مسح</button>
            </div>
            <form id="sigForm" method="POST" style="display:none;">
                <input type="hidden" name="save_sig" value="1">
                <input type="hidden" name="sig_data" id="sig-data-input">
            </form>
        <?php endif; ?>
    </div>
</div>

<div id="tab-in" style="display:none;">
    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
        <div class="card">
            <h4><i class="fa-solid fa-camera"></i> التقاط صور (قبل الاستلام)</h4>
            <div class="cam-wrapper">
                <video id="video-in" autoplay playsinline></video>
            </div>
            <button onclick="takeSnapshot('check_in')" class="btn btn-primary" style="width:100%; margin-top:10px;">التقاط وحفظ الصورة</button>
        </div>
        <div class="card">
            <h4>الصور المحفوظة (<?= $pdo->query("SELECT COUNT(*) FROM inspection_photos WHERE contract_id=$id AND photo_type='check_in'")->fetchColumn() ?>/10)</h4>
            <div class="gallery">
                <?php 
                $photos = $pdo->query("SELECT * FROM inspection_photos WHERE contract_id=$id AND photo_type='check_in'");
                while($p = $photos->fetch()) {
                    echo "<a href='{$p['photo_path']}' target='_blank'><img src='{$p['photo_path']}'></a>";
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
            </div>
            <button onclick="takeSnapshot('check_out')" class="btn btn-danger" style="width:100%; margin-top:10px;">التقاط وحفظ الصورة</button>
        </div>
        <div class="card">
            <h4>الصور المحفوظة (<?= $pdo->query("SELECT COUNT(*) FROM inspection_photos WHERE contract_id=$id AND photo_type='check_out'")->fetchColumn() ?>/10)</h4>
            <div class="gallery">
                <?php 
                $photos = $pdo->query("SELECT * FROM inspection_photos WHERE contract_id=$id AND photo_type='check_out'");
                while($p = $photos->fetch()) {
                    echo "<a href='{$p['photo_path']}' target='_blank'><img src='{$p['photo_path']}'></a>";
                }
                ?>
            </div>
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
        
        // إزالة الكلاس النشط
        document.getElementById('btn-sig').classList.remove('active');
        document.getElementById('btn-in').classList.remove('active');
        document.getElementById('btn-out').classList.remove('active');
        
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
    
    if (canvas) {
        // ضبط أبعاد الكانفاس لتكون دقيقة
        function resizeCanvas() {
            var ratio =  Math.max(window.devicePixelRatio || 1, 1);
            canvas.width = canvas.offsetWidth * ratio;
            canvas.height = canvas.offsetHeight * ratio;
            canvas.getContext("2d").scale(ratio, ratio);
        }
        window.onresize = resizeCanvas;
        resizeCanvas();
        
        signaturePad = new SignaturePad(canvas, { backgroundColor: 'rgb(255, 255, 255)' });
    }

    function clearSignature() {
        if(signaturePad) signaturePad.clear();
    }

    function saveSignature() {
        if (!signaturePad || signaturePad.isEmpty()) {
            alert("الرجاء التوقيع أولاً.");
        } else {
            document.getElementById('sig-data-input').value = signaturePad.toDataURL();
            document.getElementById('sigForm').submit();
        }
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

    function takeSnapshot(type) {
        var videoId = (type === 'check_in') ? 'video-in' : 'video-out';
        var video = document.getElementById(videoId);
        
        // إنشاء كانفاس مؤقت للرسم
        var canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        var ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        
        // تحويل الصورة لنص وارسالها
        var dataURI = canvas.toDataURL('image/png');
        document.getElementById('p-type-input').value = type;
        document.getElementById('p-data-input').value = dataURI;
        document.getElementById('photoForm').submit();
    }
</script>
