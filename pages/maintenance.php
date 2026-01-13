<?php
// معالجة الحفظ
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_maint'])) {
    $u = $pdo->query("SELECT property_id FROM units WHERE id=".$_POST['uid'])->fetch();
    $pid = $u ? $u['property_id'] : 0;
    
    try {
        $pdo->prepare("INSERT INTO maintenance (property_id, unit_id, vendor_id, description, cost, request_date, status) VALUES (?,?,?,?,?, CURDATE(), 'pending')")->execute([$pid, $_POST['uid'], $_POST['vid'], $_POST['desc'], $_POST['cost']]);
        echo "<script>window.location='index.php?p=maintenance';</script>";
        exit;
    } catch(Exception $e) {
        echo "<div style='background:red; padding:10px; color:white'>خطأ: ".$e->getMessage()."</div>";
    }
}

// تحديد العرض (جدول أو فورم)
$action = isset($_GET['act']) ? $_GET['act'] : 'list';
?>

<?php if ($action == 'add'): ?>
<div class="card" style="max-width: 600px; margin: 0 auto;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #333; padding-bottom:15px">
        <h3>تسجيل طلب صيانة جديد</h3>
        <a href="index.php?p=maintenance" class="btn btn-dark">رجوع <i class="fa-solid fa-arrow-left"></i></a>
    </div>

    <form method="POST" action="index.php?p=maintenance">
        <input type="hidden" name="save_maint" value="1">
        
        <div style="margin-bottom:15px">
            <label style="color:#aaa; display:block; margin-bottom:5px">الوحدة المتضررة</label>
            <select name="uid" class="inp" style="width:100%; padding:10px; background:#333; color:white; border:1px solid #555" required>
                <option value="">-- اختر الوحدة --</option>
                <?php $us=$pdo->query("SELECT * FROM units"); while($u=$us->fetch()) echo "<option value='{$u['id']}'>{$u['unit_name']}</option>"; ?>
            </select>
        </div>
        
        <div style="margin-bottom:15px">
            <label style="color:#aaa; display:block; margin-bottom:5px">المقاول (اختياري)</label>
            <select name="vid" class="inp" style="width:100%; padding:10px; background:#333; color:white; border:1px solid #555">
                <option value="0">-- اختر --</option>
                <?php $vs=$pdo->query("SELECT * FROM vendors"); while($v=$vs->fetch()) echo "<option value='{$v['id']}'>{$v['name']}</option>"; ?>
            </select>
        </div>
        
        <div style="margin-bottom:15px">
            <label style="color:#aaa; display:block; margin-bottom:5px">وصف المشكلة</label>
            <textarea name="desc" class="inp" style="width:100%; padding:10px; background:#333; color:white; border:1px solid #555; height:100px" required></textarea>
        </div>
        
        <div style="margin-bottom:25px">
            <label style="color:#aaa; display:block; margin-bottom:5px">التكلفة التقديرية (ريال)</label>
            <input type="number" name="cost" class="inp" style="width:100%; padding:10px; background:#333; color:white; border:1px solid #555">
        </div>
        
        <button class="btn btn-primary" style="width:100%; justify-content:center; padding:12px">حفظ الطلب</button>
    </form>
</div>

<?php else: ?>
<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px">
        <h3>🛠️ سجلات الصيانة</h3>
        <a href="index.php?p=maintenance&act=add" class="btn btn-primary" style="text-decoration:none">
            <i class="fa-solid fa-plus"></i> تسجيل طلب جديد
        </a>
    </div>
    
    <table style="width:100%; border-collapse:collapse">
        <thead>
            <tr style="background:#222; text-align:right">
                <th style="padding:15px">#</th>
                <th style="padding:15px">الوحدة</th>
                <th style="padding:15px">الوصف</th>
                <th style="padding:15px">المقاول</th>
                <th style="padding:15px">الحالة</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $reqs = $pdo->query("SELECT m.*, u.unit_name, v.name as vname FROM maintenance m JOIN units u ON m.unit_id=u.id LEFT JOIN vendors v ON m.vendor_id=v.id ORDER BY m.id DESC");
            while($r = $reqs->fetch()): 
            ?>
            <tr style="border-bottom:1px solid #333">
                <td style="padding:15px"><?= $r['id'] ?></td>
                <td style="padding:15px"><?= $r['unit_name'] ?></td>
                <td style="padding:15px"><?= $r['description'] ?></td>
                <td style="padding:15px"><?= $r['vname'] ?: '-' ?></td>
                <td style="padding:15px">
                    <span class="badge" style="background:<?= $r['status']=='pending'?'#f59e0b':'#10b981' ?>"><?= $r['status'] ?></span>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
