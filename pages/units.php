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
        $stmt = $pdo->prepare("UPDATE units SET property_id=?, unit_name=?, type=?, yearly_price=?, elec_meter_no=?, water_meter_no=? WHERE id=?");
        $stmt->execute([$_POST['pid'], $_POST['name'], $_POST['type'], $_POST['price'], $_POST['elec'], $_POST['water'], $_POST['unit_id']]);
    } else {
        // جديد
        $stmt = $pdo->prepare("INSERT INTO units (property_id, unit_name, type, yearly_price, elec_meter_no, water_meter_no) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$_POST['pid'], $_POST['name'], $_POST['type'], $_POST['price'], $_POST['elec'], $_POST['water']]);
    }
    echo "<script>window.location='index.php?p=units';</script>";
}
?>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px">
        <h3><i class="fa-solid fa-house-laptop" style="margin-left:10px;color:var(--primary)"></i> إدارة الوحدات</h3>
        <button onclick="openModal()" class="btn btn-primary"><i class="fa-solid fa-circle-plus"></i> إضافة وحدة</button>
    </div>
    
    <?php 
    $units = $pdo->query("SELECT u.*, p.name as pname FROM units u LEFT JOIN properties p ON u.property_id=p.id ORDER BY u.id DESC");
    if($units->rowCount() == 0):
    ?>
        <div style="text-align:center; padding:50px; color:#777; border:2px dashed #333; border-radius:10px;">
            لا توجد وحدات. تأكد من إضافة عقار أولاً ثم أضف الوحدات.
        </div>
    <?php else: ?>
        <table style="width:100%; border-collapse:collapse;">
            <thead>
                <tr style="background:#222; text-align:right;">
                    <th style="padding:10px">الوحدة</th>
                    <th style="padding:10px">العقار</th>
                    <th style="padding:10px">النوع</th>
                    <th style="padding:10px">السعر</th>
                    <th style="padding:10px">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php while($u = $units->fetch()): ?>
                <tr style="border-bottom:1px solid #333;">
                    <td style="padding:10px; font-weight:bold"><?= $u['unit_name'] ?></td>
                    <td style="padding:10px"><?= $u['pname'] ?></td>
                    <td style="padding:10px"><?= $u['type'] ?></td>
                    <td style="padding:10px"><?= number_format($u['yearly_price']) ?></td>
                    <td style="padding:10px; display:flex; gap:10px;">
                        <button class="btn btn-dark btn-sm"
                            onclick="editUnit(this)"
                            data-id="<?= $u['id'] ?>"
                            data-pid="<?= $u['property_id'] ?>"
                            data-name="<?= htmlspecialchars($u['unit_name']) ?>"
                            data-type="<?= $u['type'] ?>"
                            data-price="<?= $u['yearly_price'] ?>"
                            data-elec="<?= htmlspecialchars($u['elec_meter_no']) ?>"
                            data-water="<?= htmlspecialchars($u['water_meter_no']) ?>"
                        >
                            <i class="fa-solid fa-pen"></i> تعديل
                        </button>
                        <form method="POST" onsubmit="return confirm('حذف الوحدة؟');" style="margin:0">
                            <input type="hidden" name="delete_id" value="<?= $u['id'] ?>">
                            <button class="btn btn-danger btn-sm"><i class="fa-solid fa-trash"></i> حذف</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div id="unitModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:999; justify-content:center; align-items:center;">
    <div class="modal-content" style="background:#1a1a1a; padding:25px; border-radius:15px; width:500px; max-width:90%;">
        <div style="display:flex; justify-content:space-between; margin-bottom:20px;">
            <h3 id="modalTitle" style="margin:0">بيانات الوحدة</h3>
            <div style="cursor:pointer" onclick="document.getElementById('unitModal').style.display='none'"><i class="fa-solid fa-xmark"></i></div>
        </div>
        
        <form method="POST">
            <input type="hidden" name="save_unit" value="1">
            <input type="hidden" name="unit_id" id="unit_id">
            
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
    function openModal() {
        document.getElementById('unitModal').style.display = 'flex';
        document.getElementById('modalTitle').innerText = 'إضافة وحدة جديدة';
        document.getElementById('unit_id').value = '';
        document.getElementById('u_name').value = '';
        document.getElementById('u_price').value = '';
        document.getElementById('u_pid').value = '';
    }
    
    function editUnit(btn) {
        document.getElementById('unitModal').style.display = 'flex';
        document.getElementById('modalTitle').innerText = 'تعديل الوحدة';
        
        document.getElementById('unit_id').value = btn.getAttribute('data-id');
        document.getElementById('u_pid').value = btn.getAttribute('data-pid'); // سيختار العقار تلقائياً
        document.getElementById('u_name').value = btn.getAttribute('data-name');
        document.getElementById('u_type').value = btn.getAttribute('data-type');
        document.getElementById('u_price').value = btn.getAttribute('data-price');
        document.getElementById('u_elec').value = btn.getAttribute('data-elec');
        document.getElementById('u_water').value = btn.getAttribute('data-water');
    }
</script>
