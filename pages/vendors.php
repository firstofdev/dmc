<?php
// --- معالجة البيانات (حفظ / حذف) ---
if (isset($_POST['delete_id'])) {
    $pdo->prepare("DELETE FROM vendors WHERE id=?")->execute([$_POST['delete_id']]);
    echo "<script>window.location='index.php?p=vendors';</script>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_vendor'])) {
    if(!empty($_POST['vid'])){
        $stmt = $pdo->prepare("UPDATE vendors SET name=?, service_type=?, phone=? WHERE id=?");
        $stmt->execute([$_POST['name'], $_POST['type'], $_POST['phone'], $_POST['vid']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO vendors (name, service_type, phone) VALUES (?,?,?)");
        $stmt->execute([$_POST['name'], $_POST['type'], $_POST['phone']]);
    }
    echo "<script>window.location='index.php?p=vendors';</script>";
    exit;
}

// تحديد هل نحن في وضع "عرض الجدول" أم "تعبئة البيانات"
$action = isset($_GET['act']) ? $_GET['act'] : 'list';
?>

<?php if ($action == 'add' || $action == 'edit'): 
    $e_id = ''; $e_name = ''; $e_type = ''; $e_phone = '';
    $title = 'إضافة مقاول جديد';
    
    if($action == 'edit' && isset($_GET['id'])) {
        $e = $pdo->query("SELECT * FROM vendors WHERE id=".$_GET['id'])->fetch();
        if($e) {
            $e_id = $e['id']; $e_name = $e['name']; $e_type = $e['service_type']; $e_phone = $e['phone'];
            $title = 'تعديل بيانات المقاول';
        }
    }
?>
<div class="card" style="max-width: 600px; margin: 0 auto;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #333; padding-bottom:15px">
        <h3><?= $title ?></h3>
        <a href="index.php?p=vendors" class="btn btn-dark">رجوع <i class="fa-solid fa-arrow-left"></i></a>
    </div>

    <form method="POST" action="index.php?p=vendors">
        <input type="hidden" name="save_vendor" value="1">
        <input type="hidden" name="vid" value="<?= $e_id ?>">
        
        <div style="margin-bottom:15px">
            <label style="color:#aaa; display:block; margin-bottom:5px">الاسم</label>
            <input type="text" name="name" value="<?= $e_name ?>" class="inp" style="width:100%; padding:10px; background:#333; color:white; border:1px solid #555" required>
        </div>
        
        <div style="margin-bottom:15px">
            <label style="color:#aaa; display:block; margin-bottom:5px">التخصص</label>
            <input type="text" name="type" value="<?= $e_type ?>" class="inp" style="width:100%; padding:10px; background:#333; color:white; border:1px solid #555" required>
        </div>
        
        <div style="margin-bottom:25px">
            <label style="color:#aaa; display:block; margin-bottom:5px">الجوال</label>
            <input type="text" name="phone" value="<?= $e_phone ?>" class="inp" style="width:100%; padding:10px; background:#333; color:white; border:1px solid #555" required>
        </div>
        
        <button class="btn btn-primary" style="width:100%; justify-content:center; padding:12px">حفظ البيانات</button>
    </form>
</div>

<?php else: ?>
<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px">
        <h3>👷 إدارة المقاولين</h3>
        <a href="index.php?p=vendors&act=add" class="btn btn-primary" style="text-decoration:none">
            <i class="fa-solid fa-plus"></i> إضافة مقاول
        </a>
    </div>
    
    <table style="width:100%; border-collapse:collapse">
        <thead>
            <tr style="background:#222; text-align:right">
                <th style="padding:15px">الاسم</th>
                <th style="padding:15px">التخصص</th>
                <th style="padding:15px">الجوال</th>
                <th style="padding:15px">إجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php $vendors = $pdo->query("SELECT * FROM vendors ORDER BY id DESC"); while($v = $vendors->fetch()): ?>
            <tr style="border-bottom:1px solid #333">
                <td style="padding:15px"><?= $v['name'] ?></td>
                <td style="padding:15px"><?= $v['service_type'] ?></td>
                <td style="padding:15px"><?= $v['phone'] ?></td>
                <td style="padding:15px; display:flex; gap:10px">
                    <a href="index.php?p=vendors&act=edit&id=<?= $v['id'] ?>" class="btn btn-dark btn-sm"><i class="fa-solid fa-pen"></i></a>
                    
                    <form method="POST" onsubmit="return confirm('هل أنت متأكد من الحذف؟')" style="margin:0">
                        <input type="hidden" name="delete_id" value="<?= $v['id'] ?>">
                        <button class="btn btn-danger btn-sm"><i class="fa-solid fa-trash"></i></button>
                    </form>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
