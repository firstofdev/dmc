<?php
// إنشاء عقد جديد
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_contract'])) {
    $start = $_POST['start_date'];
    $end = $_POST['end_date'];
    $amount = $_POST['amount'];
    
    // إدخال العقد
    $stmt = $pdo->prepare("INSERT INTO contracts (tenant_id, unit_id, start_date, end_date, total_amount, status) VALUES (?,?,?,?,?, 'active')");
    $stmt->execute([$_POST['tid'], $_POST['uid'], $start, $end, $amount]);
    $contract_id = $pdo->lastInsertId();
    
    // تحديث حالة الوحدة إلى مؤجرة
    $pdo->prepare("UPDATE units SET status='rented' WHERE id=?")->execute([$_POST['uid']]);
    
    // التوجيه فوراً لصفحة التوقيع والتصوير
    echo "<script>window.location='index.php?p=contract_view&id=$contract_id';</script>";
}

// الحذف
if (isset($_POST['delete_id'])) {
    $c = $pdo->query("SELECT unit_id FROM contracts WHERE id=".$_POST['delete_id'])->fetch();
    if($c) $pdo->prepare("UPDATE units SET status='available' WHERE id=?")->execute([$c['unit_id']]);
    $pdo->prepare("DELETE FROM contracts WHERE id=?")->execute([$_POST['delete_id']]);
    echo "<script>window.location='index.php?p=contracts';</script>";
}
?>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px">
        <h3>📜 العقود الإيجارية</h3>
        <button onclick="document.getElementById('contModal').style.display='flex'" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i> إنشاء عقد جديد
        </button>
    </div>

    <?php 
    $conts = $pdo->query("SELECT c.*, t.name as tname, u.unit_name, u.type FROM contracts c JOIN tenants t ON c.tenant_id=t.id JOIN units u ON c.unit_id=u.id ORDER BY id DESC");
    if($conts->rowCount() == 0): ?>
        <div style="text-align:center; padding:50px; border:2px dashed #333; color:#777">
            لا توجد عقود.. اضغط على "إنشاء عقد جديد" للبدء.
        </div>
    <?php else: ?>
        <table style="width:100%; border-collapse:collapse">
            <thead>
                <tr style="background:#222; text-align:right">
                    <th style="padding:10px">رقم العقد</th>
                    <th style="padding:10px">المستأجر</th>
                    <th style="padding:10px">الوحدة</th>
                    <th style="padding:10px">القيمة</th>
                    <th style="padding:10px">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php while($r = $conts->fetch()): ?>
                <tr style="border-bottom:1px solid #333">
                    <td style="padding:10px">#<?= $r['id'] ?></td>
                    <td style="padding:10px; font-weight:bold"><?= $r['tname'] ?></td>
                    <td style="padding:10px"><?= $r['unit_name'] ?> <small>(<?= $r['type'] ?>)</small></td>
                    <td style="padding:10px"><?= number_format($r['total_amount']) ?></td>
                    <td style="padding:10px; display:flex; gap:5px">
                        <a href="index.php?p=contract_view&id=<?= $r['id'] ?>" class="btn btn-primary btn-sm">التفاصيل والتوقيع</a>
                        <form method="POST" onsubmit="return confirm('حذف العقد؟');" style="margin:0">
                            <input type="hidden" name="delete_id" value="<?= $r['id'] ?>">
                            <button class="btn btn-danger btn-sm"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div id="contModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:999; justify-content:center; align-items:center;">
    <div class="modal-content" style="background:#1a1a1a; padding:25px; border-radius:15px; width:500px;">
        <div style="display:flex; justify-content:space-between; margin-bottom:20px;">
            <h3 style="margin:0">إنشاء عقد جديد</h3>
            <div style="cursor:pointer" onclick="document.getElementById('contModal').style.display='none'"><i class="fa-solid fa-xmark"></i></div>
        </div>
        <form method="POST">
            <input type="hidden" name="create_contract" value="1">
            
            <div style="margin-bottom:15px">
                <label class="inp-label">اختر المستأجر</label>
                <select name="tid" class="inp" required style="width:100%">
                    <option value="">-- اختر --</option>
                    <?php $ts=$pdo->query("SELECT * FROM tenants"); while($t=$ts->fetch()) echo "<option value='{$t['id']}'>{$t['name']}</option>"; ?>
                </select>
            </div>
            
            <div style="margin-bottom:15px">
                <label class="inp-label">اختر الوحدة (المتاحة فقط)</label>
                <select name="uid" class="inp" required style="width:100%">
                    <option value="">-- اختر --</option>
                    <?php 
                    // جلب الوحدات المتاحة بجميع أنواعها (محل، فيلا، مكتب، أرض)
                    $us=$pdo->query("SELECT * FROM units WHERE status='available'"); 
                    while($u=$us->fetch()) echo "<option value='{$u['id']}'>{$u['unit_name']} - {$u['type']} (" . number_format($u['yearly_price']) . ")</option>"; 
                    ?>
                </select>
            </div>
            
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:15px">
                <div><label class="inp-label">تاريخ البدء</label><input type="date" name="start_date" class="inp" required style="width:100%"></div>
                <div><label class="inp-label">تاريخ الانتهاء</label><input type="date" name="end_date" class="inp" required style="width:100%"></div>
            </div>
            
            <div style="margin-bottom:20px">
                <label class="inp-label">قيمة العقد الإجمالية</label>
                <input type="number" name="amount" class="inp" required style="width:100%">
            </div>
            
            <button class="btn btn-primary" style="width:100%; justify-content:center; padding:12px">حفظ ومتابعة للتوقيع <i class="fa-solid fa-arrow-left"></i></button>
        </form>
    </div>
</div>
