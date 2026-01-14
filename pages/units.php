<?php
// معالجة الحذف
if (isset($_POST['delete_id'])) {
    $pdo->prepare("DELETE FROM units WHERE id=?")->execute([$_POST['delete_id']]);
    echo "<script>window.location='index.php?p=units';</script>";
}

// معالجة الحفظ
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_unit'])) {
    if(!empty($_POST['unit_id'])){
        // تعديل
        $stmt = $pdo->prepare("UPDATE units SET property_id=?, unit_name=?, type=?, yearly_price=?, elec_meter_no=?, water_meter_no=?, shop_name=?, shop_logo=? WHERE id=?");
        
        // معالجة رفع شعار المحل
        $shop_logo = $_POST['existing_shop_logo'] ?? null;
        if (!empty($_FILES['shop_logo']['name'])) {
            $uploaded_logo = upload($_FILES['shop_logo']);
            if ($uploaded_logo) {
                $shop_logo = $uploaded_logo;
            }
        }
        
        $stmt->execute([$_POST['pid'], $_POST['name'], $_POST['type'], $_POST['price'], $_POST['elec'], $_POST['water'], $_POST['shop_name'] ?? null, $shop_logo, $_POST['unit_id']]);
    } else {
        // جديد
        $shop_logo = null;
        if (!empty($_FILES['shop_logo']['name'])) {
            $uploaded_logo = upload($_FILES['shop_logo']);
            if ($uploaded_logo) {
                $shop_logo = $uploaded_logo;
            }
        }
        
        $stmt = $pdo->prepare("INSERT INTO units (property_id, unit_name, type, yearly_price, elec_meter_no, water_meter_no, shop_name, shop_logo) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([$_POST['pid'], $_POST['name'], $_POST['type'], $_POST['price'], $_POST['elec'], $_POST['water'], $_POST['shop_name'] ?? null, $shop_logo]);
    }
    echo "<script>window.location='index.php?p=units';</script>";
}

// جلب العقارات للفلترة
$properties = $pdo->query("SELECT id, name FROM properties ORDER BY name")->fetchAll();
?>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:15px;">
        <h3><i class="fa-solid fa-house-laptop" style="margin-left:10px;color:var(--primary)"></i> إدارة الوحدات</h3>
        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <select id="propertyFilter" class="inp" style="width:200px; padding:10px; margin:0;">
                <option value="">كل العقارات</option>
                <?php foreach($properties as $prop): ?>
                    <option value="<?= $prop['id'] ?>"><?= htmlspecialchars($prop['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="statusFilter" class="inp" style="width:150px; padding:10px; margin:0;">
                <option value="">كل الحالات</option>
                <option value="available">خالي</option>
                <option value="rented">مؤجر</option>
            </select>
            <button onclick="openModal()" class="btn btn-primary"><i class="fa-solid fa-circle-plus"></i> إضافة وحدة</button>
        </div>
    </div>
    
    <?php 
    $units_query = "SELECT u.*, p.name as pname, 
                    CASE 
                        WHEN EXISTS (SELECT 1 FROM contracts c WHERE c.unit_id = u.id AND c.status = 'active') 
                        THEN 'rented' 
                        ELSE 'available' 
                    END as status,
                    (SELECT t.name FROM contracts c 
                     JOIN tenants t ON c.tenant_id = t.id 
                     WHERE c.unit_id = u.id AND c.status = 'active' 
                     LIMIT 1) as tenant_name
                    FROM units u 
                    LEFT JOIN properties p ON u.property_id=p.id 
                    ORDER BY u.id DESC";
    $units = $pdo->query($units_query);
    
    if($units->rowCount() == 0):
    ?>
        <div style="text-align:center; padding:50px; color:#777; border:2px dashed #333; border-radius:10px;">
            لا توجد وحدات. تأكد من إضافة عقار أولاً ثم أضف الوحدات.
        </div>
    <?php else: ?>
        <div id="unitsContainer" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:20px; margin-top:20px;">
            <?php while($u = $units->fetch()): 
                $status = $u['status'];
                $statusColor = $status === 'rented' ? '#10b981' : '#6366f1';
                $statusText = $status === 'rented' ? 'مؤجر' : 'خالي';
                $statusIcon = $status === 'rented' ? 'fa-user-check' : 'fa-door-open';
            ?>
            <div class="unit-card" 
                 data-property="<?= $u['property_id'] ?>" 
                 data-status="<?= $status ?>"
                 style="background:var(--card); border:1px solid var(--border); border-radius:20px; padding:20px; position:relative; transition:all 0.3s ease; cursor:pointer;">
                
                <!-- Status Badge -->
                <div style="position:absolute; top:15px; left:15px; background:<?= $statusColor ?>; color:white; padding:5px 12px; border-radius:20px; font-size:12px; font-weight:bold; display:flex; align-items:center; gap:5px;">
                    <i class="fa-solid <?= $statusIcon ?>"></i>
                    <?= $statusText ?>
                </div>
                
                <!-- Shop Logo or Icon -->
                <div style="width:80px; height:80px; margin:0 auto 15px; border-radius:50%; background:var(--tag-bg); display:flex; align-items:center; justify-content:center; overflow:hidden; border:3px solid var(--border);">
                    <?php if(!empty($u['shop_logo']) && file_exists($u['shop_logo'])): ?>
                        <img src="<?= htmlspecialchars($u['shop_logo']) ?>" alt="شعار" style="width:100%; height:100%; object-fit:cover;">
                    <?php else: ?>
                        <i class="fa-solid fa-<?= $u['type'] === 'shop' ? 'store' : ($u['type'] === 'apartment' ? 'building' : 'home') ?>" style="font-size:32px; color:var(--primary);"></i>
                    <?php endif; ?>
                </div>
                
                <!-- Unit Info -->
                <div style="text-align:center; margin-bottom:15px;">
                    <h4 style="margin:0 0 8px 0; color:var(--text); font-size:18px; font-weight:bold;">
                        <?= htmlspecialchars($u['shop_name'] ?: $u['unit_name']) ?>
                    </h4>
                    <?php if($u['shop_name'] && $u['shop_name'] !== $u['unit_name']): ?>
                        <div style="font-size:13px; color:var(--muted); margin-bottom:5px;">
                            <?= htmlspecialchars($u['unit_name']) ?>
                        </div>
                    <?php endif; ?>
                    <div style="font-size:13px; color:var(--muted);">
                        <i class="fa-solid fa-building"></i> <?= htmlspecialchars($u['pname']) ?>
                    </div>
                </div>
                
                <!-- Tenant Name if rented -->
                <?php if($status === 'rented' && !empty($u['tenant_name'])): ?>
                <div style="background:rgba(16,185,129,0.1); border:1px solid rgba(16,185,129,0.3); border-radius:10px; padding:10px; margin-bottom:15px; text-align:center;">
                    <div style="font-size:12px; color:var(--muted); margin-bottom:3px;">المستأجر</div>
                    <div style="font-size:14px; color:#10b981; font-weight:bold;">
                        <i class="fa-solid fa-user"></i> <?= htmlspecialchars($u['tenant_name']) ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Details -->
                <div style="display:flex; flex-direction:column; gap:8px; font-size:13px; color:var(--muted); margin-bottom:15px;">
                    <div style="display:flex; justify-content:space-between;">
                        <span>النوع:</span>
                        <span style="color:var(--text); font-weight:bold;"><?= $u['type'] ?></span>
                    </div>
                    <div style="display:flex; justify-content:space-between;">
                        <span>السعر السنوي:</span>
                        <span style="color:var(--primary); font-weight:bold;"><?= number_format($u['yearly_price']) ?> ريال</span>
                    </div>
                </div>
                
                <!-- Actions -->
                <div style="display:flex; gap:8px; justify-content:center;">
                    <button class="btn btn-dark btn-sm" 
                        onclick="event.stopPropagation(); editUnit(this)"
                        data-id="<?= $u['id'] ?>"
                        data-pid="<?= $u['property_id'] ?>"
                        data-name="<?= htmlspecialchars($u['unit_name']) ?>"
                        data-shop-name="<?= htmlspecialchars($u['shop_name'] ?? '') ?>"
                        data-shop-logo="<?= htmlspecialchars($u['shop_logo'] ?? '') ?>"
                        data-type="<?= $u['type'] ?>"
                        data-price="<?= $u['yearly_price'] ?>"
                        data-elec="<?= htmlspecialchars($u['elec_meter_no']) ?>"
                        data-water="<?= htmlspecialchars($u['water_meter_no']) ?>"
                        style="flex:1;">
                        <i class="fa-solid fa-pen"></i> تعديل
                    </button>
                    <form method="POST" onsubmit="event.stopPropagation(); return confirm('حذف الوحدة؟');" style="margin:0; flex:1;">
                        <input type="hidden" name="delete_id" value="<?= $u['id'] ?>">
                        <button class="btn btn-danger btn-sm" style="width:100%;">
                            <i class="fa-solid fa-trash"></i> حذف
                        </button>
                    </form>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>
</div>

<div id="unitModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:999; justify-content:center; align-items:center; overflow-y:auto;">
    <div class="modal-content" style="background:#1a1a1a; padding:25px; border-radius:15px; width:500px; max-width:90%; margin:20px auto;">
        <div style="display:flex; justify-content:space-between; margin-bottom:20px;">
            <h3 id="modalTitle" style="margin:0">بيانات الوحدة</h3>
            <div style="cursor:pointer" onclick="document.getElementById('unitModal').style.display='none'"><i class="fa-solid fa-xmark"></i></div>
        </div>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="save_unit" value="1">
            <input type="hidden" name="unit_id" id="unit_id">
            <input type="hidden" name="existing_shop_logo" id="existing_shop_logo">
            
            <div style="margin-bottom:15px">
                <label style="display:block; margin-bottom:5px; color:#aaa">العقار التابع له</label>
                <select name="pid" id="u_pid" class="inp" required style="width:100%; padding:10px; background:#333; border:1px solid #444; color:white; border-radius:5px;">
                    <option value="">-- اختر العقار --</option>
                    <?php $ps=$pdo->query("SELECT * FROM properties"); while($p=$ps->fetch()) echo "<option value='{$p['id']}'>{$p['name']}</option>"; ?>
                </select>
            </div>
            
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:15px">
                <div>
                    <label style="display:block; margin-bottom:5px; color:#aaa">اسم الوحدة</label>
                    <input type="text" name="name" id="u_name" class="inp" required style="width:100%; padding:10px; background:#333; border:1px solid #444; color:white; border-radius:5px;">
                </div>
                <div>
                    <label style="display:block; margin-bottom:5px; color:#aaa">النوع</label>
                    <select name="type" id="u_type" class="inp" style="width:100%; padding:10px; background:#333; border:1px solid #444; color:white; border-radius:5px;">
                        <option value="apartment">شقة</option>
                        <option value="shop">محل</option>
                        <option value="villa">فيلا</option>
                        <option value="office">مكتب</option>
                    </select>
                </div>
            </div>
            
            <div style="margin-bottom:15px">
                <label style="display:block; margin-bottom:5px; color:#aaa">اسم المحل (اختياري)</label>
                <input type="text" name="shop_name" id="u_shop_name" class="inp" style="width:100%; padding:10px; background:#333; border:1px solid #444; color:white; border-radius:5px;">
                <small style="color:#888; font-size:12px;">يظهر بدلاً من اسم الوحدة في البطاقة</small>
            </div>
            
            <div style="margin-bottom:15px">
                <label style="display:block; margin-bottom:5px; color:#aaa">شعار المحل (اختياري)</label>
                <input type="file" name="shop_logo" id="u_shop_logo" accept="image/*" class="inp" style="width:100%; padding:8px; background:#333; border:1px solid #444; color:white; border-radius:5px;">
                <small style="color:#888; font-size:12px;">يظهر في بطاقة الوحدة</small>
                <div id="current_logo_preview" style="margin-top:10px; display:none;">
                    <img id="logo_preview_img" src="" style="max-width:100px; border-radius:8px; border:2px solid #444;">
                </div>
            </div>

            <div style="margin-bottom:15px">
                <label style="display:block; margin-bottom:5px; color:#aaa">السعر السنوي</label>
                <input type="number" name="price" id="u_price" class="inp" required style="width:100%; padding:10px; background:#333; border:1px solid #444; color:white; border-radius:5px;">
            </div>
            
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:15px">
                <div>
                    <label style="display:block; margin-bottom:5px; color:#aaa">عداد كهرباء</label>
                    <input type="text" name="elec" id="u_elec" class="inp" style="width:100%; padding:10px; background:#333; border:1px solid #444; color:white; border-radius:5px;">
                </div>
                <div>
                    <label style="display:block; margin-bottom:5px; color:#aaa">عداد مياه</label>
                    <input type="text" name="water" id="u_water" class="inp" style="width:100%; padding:10px; background:#333; border:1px solid #444; color:white; border-radius:5px;">
                </div>
            </div>

            <button class="btn btn-primary" style="width:100%; justify-content:center; padding:12px;">حفظ</button>
        </form>
    </div>
</div>

<script>
    // Filter units by property and status
    document.getElementById('propertyFilter').addEventListener('change', filterUnits);
    document.getElementById('statusFilter').addEventListener('change', filterUnits);
    
    function filterUnits() {
        const propertyFilter = document.getElementById('propertyFilter').value;
        const statusFilter = document.getElementById('statusFilter').value;
        const cards = document.querySelectorAll('.unit-card');
        
        cards.forEach(card => {
            const property = card.dataset.property;
            const status = card.dataset.status;
            
            let show = true;
            if (propertyFilter && property !== propertyFilter) show = false;
            if (statusFilter && status !== statusFilter) show = false;
            
            card.style.display = show ? 'block' : 'none';
        });
    }
    
    function openModal() {
        document.getElementById('unitModal').style.display = 'flex';
        document.getElementById('modalTitle').innerText = 'إضافة وحدة جديدة';
        document.getElementById('unit_id').value = '';
        document.getElementById('u_name').value = '';
        document.getElementById('u_shop_name').value = '';
        document.getElementById('u_price').value = '';
        document.getElementById('u_pid').value = '';
        document.getElementById('u_type').value = 'apartment';
        document.getElementById('u_elec').value = '';
        document.getElementById('u_water').value = '';
        document.getElementById('existing_shop_logo').value = '';
        document.getElementById('current_logo_preview').style.display = 'none';
    }
    
    function editUnit(btn) {
        document.getElementById('unitModal').style.display = 'flex';
        document.getElementById('modalTitle').innerText = 'تعديل الوحدة';
        
        document.getElementById('unit_id').value = btn.getAttribute('data-id');
        document.getElementById('u_pid').value = btn.getAttribute('data-pid');
        document.getElementById('u_name').value = btn.getAttribute('data-name');
        document.getElementById('u_shop_name').value = btn.getAttribute('data-shop-name') || '';
        document.getElementById('u_type').value = btn.getAttribute('data-type');
        document.getElementById('u_price').value = btn.getAttribute('data-price');
        document.getElementById('u_elec').value = btn.getAttribute('data-elec');
        document.getElementById('u_water').value = btn.getAttribute('data-water');
        
        const shopLogo = btn.getAttribute('data-shop-logo');
        document.getElementById('existing_shop_logo').value = shopLogo || '';
        
        if (shopLogo) {
            document.getElementById('logo_preview_img').src = shopLogo;
            document.getElementById('current_logo_preview').style.display = 'block';
        } else {
            document.getElementById('current_logo_preview').style.display = 'none';
        }
    }
</script>
