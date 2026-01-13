<?php
require 'config.php';
if($_POST){
    check_csrf();
    $attempts = $_SESSION['login_attempts'] ?? ['count' => 0, 'time' => time()];
    if (time() - $attempts['time'] > 600) {
        $attempts = ['count' => 0, 'time' => time()];
    }
    if ($attempts['count'] >= 5) {
        $err = "محاولات كثيرة، حاول لاحقاً.";
    } else {
        $attempts['count']++;
        $_SESSION['login_attempts'] = $attempts;

        $stmt = $pdo->prepare("SELECT * FROM users WHERE username=?");
        $stmt->execute([$_POST['user']]); $u = $stmt->fetch();
        if($u && password_verify($_POST['pass'], $u['password'])){ 
            session_regenerate_id(true);
            $_SESSION['uid'] = $u['id'];
            $_SESSION['user_name'] = $u['full_name'] ?: $u['username'];
            $_SESSION['role'] = $u['role'] ?? 'staff';
            unset($_SESSION['login_attempts']);
            log_activity($pdo, "تسجيل دخول ناجح للمستخدم: ".$u['username'], 'auth_success');
            header("Location: index.php"); exit; 
        } 
        log_activity($pdo, "فشل تسجيل الدخول للمستخدم: ".$_POST['user'], 'auth_failed');
        $err="خطأ في البيانات";
    }
}
?>
<!DOCTYPE html>
<html dir="rtl">
<head>
    <title>تسجيل الدخول</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body{
            background: radial-gradient(circle at top, #1f2937, #0f172a);
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
            font-family: "Tajawal", "Segoe UI", sans-serif;
        }
        .login-card{
            background: #0b1220;
            border: 1px solid rgba(148, 163, 184, 0.2);
            box-shadow: 0 20px 50px rgba(2, 8, 23, 0.5);
            border-radius: 20px;
            padding: 32px;
            width: min(92vw, 420px);
            color: #e2e8f0;
        }
        .login-card .logo{
            height: 72px;
            width: 72px;
            border-radius: 18px;
            background: rgba(15, 23, 42, 0.8);
            display: grid;
            place-items: center;
            margin: 0 auto 20px;
        }
        .login-card .logo img{
            max-width: 70%;
        }
        .login-card h2{
            font-size: 1.4rem;
            margin-bottom: 0.5rem;
        }
        .login-card p{
            color: #94a3b8;
            margin-bottom: 1.5rem;
        }
        .login-card .form-control{
            background: #0f172a;
            border: 1px solid rgba(148, 163, 184, 0.25);
            color: #e2e8f0;
            padding: 0.75rem 1rem;
            border-radius: 12px;
        }
        .login-card .form-control::placeholder{
            color: #94a3b8;
        }
        .login-card .form-control:focus{
            border-color: #38bdf8;
            box-shadow: 0 0 0 0.2rem rgba(56, 189, 248, 0.2);
        }
        .login-card .btn-primary{
            background: linear-gradient(135deg, #38bdf8, #6366f1);
            border: none;
            padding: 0.75rem;
            border-radius: 12px;
            font-weight: 600;
            letter-spacing: 0.3px;
        }
        .login-card .btn-primary:hover{
            filter: brightness(1.05);
        }
        .login-card .error-message{
            background: rgba(248, 113, 113, 0.15);
            border: 1px solid rgba(248, 113, 113, 0.4);
            color: #fecaca;
            padding: 0.6rem 0.9rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-card text-center">
        <div class="logo"><img src="logo.png" alt="الشعار"></div>
        <h2><?= $name ?></h2>
        <p>منصة إدارة الأملاك الذكية</p>
        <?php if(isset($err)) echo "<div class='error-message'>$err</div>"; ?>
        <form method="POST" class="text-start">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="mb-3">
                <label class="form-label text-light">اسم المستخدم</label>
                <input class="form-control" type="text" name="user" placeholder="ادخل اسم المستخدم" required>
            </div>
            <div class="mb-4">
                <label class="form-label text-light">كلمة المرور</label>
                <input class="form-control" type="password" name="pass" placeholder="ادخل كلمة المرور" required>
            </div>
            <button class="btn btn-primary w-100">تسجيل الدخول</button>
        </form>
    </div>
</body>
</html>
