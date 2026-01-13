<?php
// الحذف
if (isset($_POST['delete_id'])) {
    $pdo->prepare("DELETE FROM tenants WHERE id=?")->execute([$_POST['delete_id']]);
    log_activity($pdo, "حذف مستأجر رقم #{$_POST['delete_id']}", 'tenant');
    echo "<script>window.location='index.php?p=tenants';</script>";
}

// الحفظ (جديد / تعديل)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_tenant'])) {
    if(!empty($_POST['tenant_id'])){
        $stmt = $pdo->prepare("UPDATE tenants SET name=?, phone=?, id_number=?, email=? WHERE id=?");
        $stmt->execute([$_POST['name'], $_POST['phone'], $_POST['idn'], $_POST['email'], $_POST['tenant_id']]);
        log_activity($pdo, "تحديث بيانات المستأجر #{$_POST['tenant_id']}", 'tenant');
    } else {
        $stmt = $pdo->prepare("INSERT INTO tenants (name, phone, id_number, email) VALUES (?,?,?,?)");
        $stmt->execute([$_POST['name'], $_POST['phone'], $_POST['idn'], $_POST['email']]);
        log_activity($pdo, "إضافة مستأجر جديد: ".$_POST['name'], 'tenant');
    }
    echo "<script>window.location='index.php?p=tenants';</script>";
}
?>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px">
        <h3>👥 إدارة المستأجرين</h3>
        <button onclick="openModal()" class="btn btn-primary"><i class="fa-solid fa-plus"></i> إضافة مستأجر</button>
    </div>
    
    <?php 
    $tenants = $pdo->query("SELECT * FROM tenants ORDER BY id DESC");
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

<div id="tenantModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:999; justify-content:center; align-items:center;">
    <div class="modal-content" style="background:#1a1a1a; padding:25px; border-radius:15px; width:400px;">
        <div style="display:flex; justify-content:space-between; margin-bottom:20px;">
            <h3 id="modalTitle" style="margin:0">مستأجر جديد</h3>
            <div style="cursor:pointer" onclick="document.getElementById('tenantModal').style.display='none'"><i class="fa-solid fa-xmark"></i></div>
        </div>
        <form method="POST">
            <input type="hidden" name="save_tenant" value="1">
            <input type="hidden" name="tenant_id" id="t_id">
            
            <label class="inp-label">الاسم الكامل</label>
            <input type="text" name="name" id="t_name" class="inp" required style="width:100%; margin-bottom:10px">
            
            <label class="inp-label">رقم الجوال</label>
            <input type="text" name="phone" id="t_phone" class="inp" required style="width:100%; margin-bottom:10px">
            
            <label class="inp-label">رقم الهوية / السجل</label>
            <input type="text" name="idn" id="t_idn" class="inp" style="width:100%; margin-bottom:10px">
            
            <label class="inp-label">البريد الإلكتروني</label>
            <input type="email" name="email" id="t_email" class="inp" style="width:100%; margin-bottom:10px">
            
            <button class="btn btn-primary" style="width:100%; justify-content:center; padding:10px">حفظ</button>
        </form>
    </div>
</div>

<script>
function openModal() {
    document.getElementById('tenantModal').style.display='flex';
    document.getElementById('modalTitle').innerText='مستأجر جديد';
    document.getElementById('t_id').value='';
    document.getElementById('t_name').value='';
    document.getElementById('t_phone').value='';
    document.getElementById('t_idn').value='';
    document.getElementById('t_email').value='';
}
function editTenant(btn) {
    document.getElementById('tenantModal').style.display='flex';
    document.getElementById('modalTitle').innerText='تعديل بيانات';
    document.getElementById('t_id').value = btn.getAttribute('data-id');
    document.getElementById('t_name').value = btn.getAttribute('data-name');
    document.getElementById('t_phone').value = btn.getAttribute('data-phone');
    document.getElementById('t_idn').value = btn.getAttribute('data-idn');
    document.getElementById('t_email').value = btn.getAttribute('data-email');
}
</script>
