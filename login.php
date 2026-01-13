<?php
require 'config.php';
if($_POST){
    check_csrf();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username=?");
    $stmt->execute([$_POST['user']]); $u = $stmt->fetch();
    if($u && password_verify($_POST['pass'], $u['password'])){ 
        $_SESSION['uid'] = $u['id'];
        log_activity($pdo, "تسجيل دخول ناجح للمستخدم: ".$u['username'], 'auth_success');
        header("Location: index.php"); exit; 
    } 
    log_activity($pdo, "فشل تسجيل الدخول للمستخدم: ".$_POST['user'], 'auth_failed');
    $err="خطأ في البيانات";
}
?>
<!DOCTYPE html>
<html dir="rtl">
<head>
    <title>تسجيل الدخول</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>body{background:#1e293b;height:100vh;display:flex;align-items:center;justify-content:center}</style>
</head>
<body>
<div class="box">
        <div class="logo"><img src="logo.png" style="max-width:70%"></div>
        <h2 style="color:white; margin:0 0 10px"><?= $name ?></h2>
        <p style="color:#888; margin-bottom:40px">منصة إدارة الأملاك الذكية</p>
        <?php if(isset($err)) echo "<p style='color:#f87171'>$err</p>"; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="text" name="user" placeholder="اسم المستخدم" required>
            <input type="password" name="pass" placeholder="كلمة المرور" required>
            <button>دخول</button>
        </form>
    </div>
</body>
</html>
