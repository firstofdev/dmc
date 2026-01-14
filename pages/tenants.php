<?php
$tenantNameColumn = tenant_name_column($pdo);
// الحذف
if (isset($_POST['delete_id'])) {
    $pdo->prepare("DELETE FROM tenants WHERE id=?")->execute([$_POST['delete_id']]);
    log_activity($pdo, "حذف مستأجر رقم #{$_POST['delete_id']}", 'tenant');
    echo "<script>window.location='index.php?p=tenants';</script>";
}

// الحفظ (جديد / تعديل)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_tenant'])) {
    if(!empty($_POST['tenant_id'])){
        $stmt = $pdo->prepare("UPDATE tenants SET $tenantNameColumn=?, phone=?, id_number=?, email=?, id_type=?, address=? WHERE id=?");
        $stmt->execute([
            $_POST['name'], 
            $_POST['phone'], 
            $_POST['idn'], 
            $_POST['email'],
            $_POST['id_type'] ?? '',
            $_POST['address'] ?? '',
            $_POST['tenant_id']
        ]);
        log_activity($pdo, "تحديث بيانات المستأجر #{$_POST['tenant_id']}", 'tenant');
    } else {
        $stmt = $pdo->prepare("INSERT INTO tenants ($tenantNameColumn, phone, id_number, email, id_type, address) VALUES (?,?,?,?,?,?)");
        $stmt->execute([
            $_POST['name'], 
            $_POST['phone'], 
            $_POST['idn'], 
            $_POST['email'],
            $_POST['id_type'] ?? '',
            $_POST['address'] ?? ''
        ]);
        $tenantId = $pdo->lastInsertId();
        log_activity($pdo, "إضافة مستأجر جديد: ".$_POST['name'], 'tenant');
        
        // حفظ صورة الهوية إذا تم رفعها
        if (!empty($_FILES['id_photo']['name'])) {
            $idPhotoPath = upload($_FILES['id_photo']);
            if ($idPhotoPath) {
                $pdo->prepare("UPDATE tenants SET id_photo=? WHERE id=?")->execute([$idPhotoPath, $tenantId]);
            }
        }
    }
    echo "<script>window.location='index.php?p=tenants';</script>";
}

// معالجة OCR للهوية
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['scan_id'])) {
    check_csrf();
    $response = ['success' => false];
    
    if (!empty($_FILES['id_image']['name'])) {
        $idImagePath = upload($_FILES['id_image']);
        
        if ($idImagePath && isset($AI)) {
            $ocrResult = $AI->analyzeIDCard(__DIR__ . '/../' . $idImagePath);
            
            if ($ocrResult['success'] && !empty($ocrResult['data'])) {
                $response['success'] = true;
                $response['data'] = $ocrResult['data'];
                // حفظ البيانات المستخرجة - تم إزالة temp_tenant_id لأسباب أمنية
                // يمكن حفظ البيانات فقط عند إنشاء المستأجر فعلياً
            } else {
                $response['error'] = $ocrResult['error'] ?? 'فشل في قراءة البيانات من الهوية';
            }
        } else {
            $response['error'] = 'فشل في رفع صورة الهوية أو OCR غير مفعّل';
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}
?>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px">
        <h3><i class="fa-solid fa-user-group" style="margin-left:10px;color:var(--primary)"></i> إدارة المستأجرين</h3>
        <button onclick="openModal()" class="btn btn-primary"><i class="fa-solid fa-user-plus"></i> إضافة مستأجر</button>
    </div>
    
    <?php 
    $tenants = $pdo->query("SELECT id, $tenantNameColumn AS name, phone, id_number, email, id_type, address FROM tenants ORDER BY id DESC");
    if($tenants->rowCount() == 0): ?>
        <div style="text-align:center; padding:40px; color:#666">لا يوجد مستأجرين حالياً.</div>
    <?php else: ?>
        <table style="width:100%; border-collapse:collapse">
            <thead>
                <tr style="background:#222; text-align:right">
                    <th style="padding:10px">الاسم</th>
                    <th style="padding:10px">الجوال</th>
                    <th style="padding:10px">رقم الهوية</th>
                    <th style="padding:10px">الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php while($t = $tenants->fetch()): ?>
                <tr style="border-bottom:1px solid #333">
                    <td style="padding:10px; font-weight:bold"><?= $t['name'] ?></td>
                    <td style="padding:10px"><?= $t['phone'] ?></td>
                    <td style="padding:10px"><?= $t['id_number'] ?></td>
                    <td style="padding:10px; display:flex; gap:5px">
                        <a href="index.php?p=tenant_view&id=<?= $t['id'] ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-eye"></i></a>
                        
                        <button onclick="editTenant(this)" 
                            data-id="<?= $t['id'] ?>" 
                            data-name="<?= $t['name'] ?>" 
                            data-phone="<?= $t['phone'] ?>" 
                            data-idn="<?= $t['id_number'] ?>" 
                            data-email="<?= $t['email'] ?>" 
                            data-id-type="<?= $t['id_type'] ?? '' ?>" 
                            data-address="<?= htmlspecialchars($t['address'] ?? '') ?>" 
                            class="btn btn-dark btn-sm"><i class="fa-solid fa-pen"></i></button>
                        
                        <form method="POST" onsubmit="return confirm('حذف المستأجر؟');" style="margin:0">
                            <input type="hidden" name="delete_id" value="<?= $t['id'] ?>">
                            <button class="btn btn-danger btn-sm"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div id="tenantModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:999; justify-content:center; align-items:center; overflow-y:auto;">
    <div class="modal-content" style="background:#1a1a1a; padding:25px; border-radius:15px; width:500px; max-width:95%; margin:20px auto;">
        <div style="display:flex; justify-content:space-between; margin-bottom:20px;">
            <h3 id="modalTitle" style="margin:0">مستأجر جديد</h3>
            <div style="cursor:pointer" onclick="document.getElementById('tenantModal').style.display='none'"><i class="fa-solid fa-xmark"></i></div>
        </div>
        
        <?php if (is_ocr_configured()): ?>
        <div style="background:#111827; padding:15px; border-radius:10px; margin-bottom:15px; border:1px dashed #4f46e5;">
            <h4 style="margin:0 0 10px 0; color:#a5b4fc; font-size:14px;">
                <i class="fa-solid fa-id-card"></i> مسح الهوية تلقائياً (OCR)
            </h4>
            <input type="file" id="id_scan_input" accept="image/*" style="margin-bottom:10px; color:#fff;" />
            <button type="button" onclick="scanID()" class="btn btn-dark" style="width:100%; justify-content:center;">
                <i class="fa-solid fa-wand-magic-sparkles"></i> مسح وملء البيانات تلقائياً
            </button>
            <div id="scan_status" style="margin-top:10px; font-size:12px; display:none;"></div>
        </div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="save_tenant" value="1">
            <input type="hidden" name="tenant_id" id="t_id">
            
            <label class="inp-label">الاسم الكامل</label>
            <input type="text" name="name" id="t_name" class="inp" required style="width:100%; margin-bottom:10px">
            
            <label class="inp-label">رقم الجوال</label>
            <input type="text" name="phone" id="t_phone" class="inp" required style="width:100%; margin-bottom:10px">
            
            <label class="inp-label">رقم الهوية / السجل</label>
            <input type="text" name="idn" id="t_idn" class="inp" style="width:100%; margin-bottom:10px">
            
            <label class="inp-label">نوع الهوية</label>
            <select name="id_type" id="t_id_type" class="inp" style="width:100%; margin-bottom:10px">
                <option value="">اختر نوع الهوية</option>
                <option value="national_id">هوية وطنية</option>
                <option value="iqama">إقامة</option>
                <option value="passport">جواز سفر</option>
                <option value="commercial_reg">سجل تجاري</option>
            </select>
            
            <label class="inp-label">البريد الإلكتروني</label>
            <input type="email" name="email" id="t_email" class="inp" style="width:100%; margin-bottom:10px">
            
            <label class="inp-label">العنوان</label>
            <textarea name="address" id="t_address" class="inp" rows="2" style="width:100%; margin-bottom:10px"></textarea>
            
            <label class="inp-label">صورة الهوية (اختياري)</label>
            <input type="file" name="id_photo" accept="image/*" class="inp" style="width:100%; margin-bottom:15px; padding:8px;">
            
            <button class="btn btn-primary" style="width:100%; justify-content:center; padding:10px">حفظ</button>
        </form>
    </div>
</div>

<script>
async function scanID() {
    const fileInput = document.getElementById('id_scan_input');
    const statusDiv = document.getElementById('scan_status');
    
    if (!fileInput.files || !fileInput.files[0]) {
        statusDiv.style.display = 'block';
        statusDiv.style.color = '#f87171';
        statusDiv.innerHTML = '<i class="fa-solid fa-exclamation-triangle"></i> يرجى اختيار صورة الهوية أولاً';
        return;
    }
    
    statusDiv.style.display = 'block';
    statusDiv.style.color = '#60a5fa';
    statusDiv.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> جاري مسح الهوية...';
    
    const formData = new FormData();
    formData.append('scan_id', '1');
    formData.append('csrf_token', '<?= csrf_token() ?>');
    formData.append('id_image', fileInput.files[0]);
    
    try {
        const response = await fetch('index.php?p=tenants', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success && result.data) {
            statusDiv.style.color = '#22c55e';
            statusDiv.innerHTML = '<i class="fa-solid fa-check-circle"></i> تم المسح بنجاح! البيانات تم ملئها تلقائياً';
            
            // ملء البيانات في النموذج
            if (result.data.name) document.getElementById('t_name').value = result.data.name;
            if (result.data.id_number) document.getElementById('t_idn').value = result.data.id_number;
            if (result.data.phone) document.getElementById('t_phone').value = result.data.phone;
            if (result.data.address) document.getElementById('t_address').value = result.data.address;
            
        } else {
            statusDiv.style.color = '#f87171';
            statusDiv.innerHTML = '<i class="fa-solid fa-exclamation-triangle"></i> ' + (result.error || 'فشل في قراءة البيانات');
        }
    } catch (error) {
        statusDiv.style.color = '#f87171';
        statusDiv.innerHTML = '<i class="fa-solid fa-exclamation-triangle"></i> حدث خطأ أثناء المسح';
        console.error(error);
    }
}
</script>

<script>
function openModal() {
    document.getElementById('tenantModal').style.display='flex';
    document.getElementById('modalTitle').innerText='مستأجر جديد';
    document.getElementById('t_id').value='';
    document.getElementById('t_name').value='';
    document.getElementById('t_phone').value='';
    document.getElementById('t_idn').value='';
    document.getElementById('t_email').value='';
    document.getElementById('t_id_type').value='';
    document.getElementById('t_address').value='';
}
function editTenant(btn) {
    document.getElementById('tenantModal').style.display='flex';
    document.getElementById('modalTitle').innerText='تعديل بيانات';
    document.getElementById('t_id').value = btn.getAttribute('data-id');
    document.getElementById('t_name').value = btn.getAttribute('data-name');
    document.getElementById('t_phone').value = btn.getAttribute('data-phone');
    document.getElementById('t_idn').value = btn.getAttribute('data-idn');
    document.getElementById('t_email').value = btn.getAttribute('data-email');
    document.getElementById('t_id_type').value = btn.getAttribute('data-id-type') || '';
    document.getElementById('t_address').value = btn.getAttribute('data-address') || '';
}
</script>
