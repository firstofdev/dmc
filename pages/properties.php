<?php
// معالجة الحذف
if (isset($_POST['delete_id'])) {
    $stmt = $pdo->prepare("DELETE FROM properties WHERE id = ?");
    $stmt->execute([$_POST['delete_id']]);
    echo "<script>window.location='index.php?p=properties';</script>";
}

// معالجة الحفظ (جديد وتعديل)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_prop'])) {
    if(!empty($_POST['prop_id'])){
        // تعديل
        $stmt = $pdo->prepare("UPDATE properties SET name=?, manager=?, phone=?, address=? WHERE id=?");
        $stmt->execute([$_POST['name'], $_POST['manager'], $_POST['phone'], $_POST['address'], $_POST['prop_id']]);
    } else {
        // جديد
        $stmt = $pdo->prepare("INSERT INTO properties (name, manager, phone, address) VALUES (?,?,?,?)");
        $stmt->execute([$_POST['name'], $_POST['manager'], $_POST['phone'], $_POST['address']]);
    }
    echo "<script>window.location='index.php?p=properties';</script>";
}
?>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px">
        <h3>🏙️ إدارة العقارات</h3>
        <button onclick="openModal()" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i> إضافة عقار
        </button>
    </div>
    
    <?php 
    $props = $pdo->query("SELECT * FROM properties ORDER BY id DESC");
    if($props->rowCount() == 0):
    ?>
        <div style="text-align:center; padding:50px; color:#777; border:2px dashed #333; border-radius:10px;">
            لا توجد عقارات مضافة. ابدأ بإضافة أول عقار.
        </div>
    <?php else: ?>
        <table style="width:100%; border-collapse:collapse;">
            <thead>
                <tr style="background:#222; text-align:right;">
                    <th style="padding:10px">اسم العقار</th>
                    <th style="padding:10px">العنوان</th>
                    <th style="padding:10px">المدير</th>
                    <th style="padding:10px">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php while($r = $props->fetch()): ?>
                <tr style="border-bottom:1px solid #333;">
                    <td style="padding:10px; font-weight:bold; color:white"><?= $r['name'] ?></td>
                    <td style="padding:10px"><?= $r['address'] ?></td>
                    <td style="padding:10px"><?= $r['manager'] ?></td>
                    <td style="padding:10px; display:flex; gap:10px;">
                        <button 
                            class="btn btn-dark btn-sm"
                            onclick="editProp(this)"
                            data-id="<?= $r['id'] ?>"
                            data-name="<?= htmlspecialchars($r['name']) ?>"
                            data-address="<?= htmlspecialchars($r['address']) ?>"
                            data-manager="<?= htmlspecialchars($r['manager']) ?>"
                            data-phone="<?= htmlspecialchars($r['phone']) ?>"
                        >
                            <i class="fa-solid fa-pen"></i> تعديل
                        </button>
                        
                        <form method="POST" onsubmit="return confirm('هل أنت متأكد من الحذف؟');" style="margin:0">
                            <input type="hidden" name="delete_id" value="<?= $r['id'] ?>">
                            <button class="btn btn-danger btn-sm"><i class="fa-solid fa-trash"></i> حذف</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div id="propModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:999; justify-content:center; align-items:center;">
    <div class="modal-content" style="background:#1a1a1a; padding:25px; border-radius:15px; width:500px; max-width:90%;">
        <div style="display:flex; justify-content:space-between; margin-bottom:20px;">
            <h3 id="modalTitle" style="margin:0">إضافة عقار</h3>
            <div style="cursor:pointer" onclick="document.getElementById('propModal').style.display='none'"><i class="fa-solid fa-xmark"></i></div>
        </div>
        
        <form method="POST">
            <input type="hidden" name="save_prop" value="1">
            <input type="hidden" name="prop_id" id="prop_id">
            
            <div style="margin-bottom:15px">
                <label style="display:block; margin-bottom:5px; color:#aaa">اسم العقار</label>
                <input type="text" name="name" id="p_name" class="inp" required style="width:100%; padding:10px; background:#333; border:1px solid #444; color:white; border-radius:5px;">
            </div>
            
            <div style="margin-bottom:15px">
                <label style="display:block; margin-bottom:5px; color:#aaa">العنوان</label>
                <input type="text" name="address" id="p_address" class="inp" style="width:100%; padding:10px; background:#333; border:1px solid #444; color:white; border-radius:5px;">
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:15px">
                <div>
                    <label style="display:block; margin-bottom:5px; color:#aaa">المدير</label>
                    <input type="text" name="manager" id="p_manager" class="inp" style="width:100%; padding:10px; background:#333; border:1px solid #444; color:white; border-radius:5px;">
                </div>
                <div>
                    <label style="display:block; margin-bottom:5px; color:#aaa">الجوال</label>
                    <input type="text" name="phone" id="p_phone" class="inp" style="width:100%; padding:10px; background:#333; border:1px solid #444; color:white; border-radius:5px;">
                </div>
            </div>

            <button class="btn btn-primary" style="width:100%; justify-content:center; padding:12px;">حفظ البيانات</button>
        </form>
    </div>
</div>

<script>
    // دالة فتح النافذة للإضافة
    function openModal() {
        document.getElementById('propModal').style.display = 'flex';
        document.getElementById('modalTitle').innerText = 'إضافة عقار جديد';
        document.getElementById('prop_id').value = '';
        document.getElementById('p_name').value = '';
        document.getElementById('p_address').value = '';
        document.getElementById('p_manager').value = '';
        document.getElementById('p_phone').value = '';
    }

    // دالة فتح النافذة للتعديل (تأخذ البيانات من الزر مباشرة)
    function editProp(btn) {
        document.getElementById('propModal').style.display = 'flex';
        document.getElementById('modalTitle').innerText = 'تعديل العقار';
        
        // تعبئة الحقول من البيانات المخزنة في الزر
        document.getElementById('prop_id').value = btn.getAttribute('data-id');
        document.getElementById('p_name').value = btn.getAttribute('data-name');
        document.getElementById('p_address').value = btn.getAttribute('data-address');
        document.getElementById('p_manager').value = btn.getAttribute('data-manager');
        document.getElementById('p_phone').value = btn.getAttribute('data-phone');
    }
</script>
