<?php
require_role(['admin']);

if(isset($_POST['add_user'])){
    check_csrf();
    $pass = password_hash($_POST['pass'], PASSWORD_DEFAULT);
    try {
        $pdo->prepare("INSERT INTO users (full_name, username, email, password, role) VALUES (?,?,?,?,?)")
            ->execute([$_POST['name'], $_POST['user'], $_POST['email'], $pass, $_POST['role']]);
        echo "<script>window.location='index.php?p=users';</script>";
    } catch(Exception $e) { echo "<script>alert('خطأ: المستخدم موجود مسبقاً');</script>"; }
}

if(isset($_GET['del']) && $_GET['del'] != $_SESSION['uid']){
    $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$_GET['del']]);
    echo "<script>window.location='index.php?p=users';</script>";
}
?>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px">
        <h3>🛡️ إدارة المستخدمين</h3>
        <button onclick="document.getElementById('userModal').style.display='flex'" class="btn btn-primary">
            <i class="fa-solid fa-user-plus"></i> إضافة مستخدم جديد
        </button>
    </div>
    
    <table>
        <thead><tr><th>الاسم</th><th>اسم المستخدم</th><th>البريد</th><th>الصلاحية</th><th>إجراء</th></tr></thead>
        <tbody>
            <?php $users = $pdo->query("SELECT * FROM users"); while($u = $users->fetch()): ?>
            <tr>
                <td style="font-weight:bold"><?= $u['full_name'] ?></td>
                <td><?= $u['username'] ?></td>
                <td><?= $u['email'] ?></td>
                <td>
                    <?php if($u['role']=='admin'): ?><span class="badge" style="background:rgba(99,102,241,0.2); color:#a5b4fc">مدير عام</span>
                    <?php else: ?><span class="badge" style="background:rgba(16,185,129,0.2); color:#6ee7b7">موظف</span><?php endif; ?>
                </td>
                <td>
                    <?php if($u['id'] != $_SESSION['uid']): ?>
                    <a href="index.php?p=users&del=<?= $u['id'] ?>" onclick="return confirm('حذف المستخدم؟')" class="btn btn-danger" style="padding:5px 10px; font-size:12px">حذف</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<div id="userModal" class="modal">
    <div class="modal-content">
        <div class="close-icon" onclick="document.getElementById('userModal').style.display='none'"><i class="fa-solid fa-xmark"></i></div>
        <div class="modal-header"><div class="modal-title">إضافة مستخدم جديد</div></div>
        
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="add_user" value="1">
            
            <div class="inp-group">
                <label class="inp-label">الاسم الكامل</label>
                <input type="text" name="name" class="inp" required>
            </div>

            <div class="inp-grid">
                <div><label class="inp-label">اسم المستخدم</label><input type="text" name="user" class="inp" required></div>
                <div><label class="inp-label">البريد الإلكتروني</label><input type="email" name="email" class="inp" required></div>
            </div>

            <div class="inp-grid">
                <div><label class="inp-label">كلمة المرور</label><input type="password" name="pass" class="inp" required></div>
                <div>
                    <label class="inp-label">الصلاحية</label>
                    <select name="role" class="inp">
                        <option value="staff">موظف</option>
                        <option value="admin">مدير عام</option>
                    </select>
                </div>
            </div>

            <button class="btn btn-primary" style="width:100%; justify-content:center; margin-top:10px">
                <i class="fa-solid fa-save"></i> حفظ المستخدم
            </button>
        </form>
    </div>
</div>
