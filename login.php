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
$company_name = 'اسم الشركة غير محدد';
$logo_src = 'logo.png';
try {
    $stmt = $pdo->prepare("SELECT v FROM settings WHERE k='company_name'");
    $stmt->execute();
    $company_name = $stmt->fetchColumn() ?: $company_name;
    $stmt = $pdo->prepare("SELECT v FROM settings WHERE k='logo'");
    $stmt->execute();
    $logo_candidate = $stmt->fetchColumn();
    if ($logo_candidate && file_exists($logo_candidate)) {
        $logo_src = $logo_candidate;
    }
} catch (Exception $e) {
}
?>
<!DOCTYPE html>
<html dir="rtl">
<head>
    <title>تسجيل الدخول | <?= htmlspecialchars($company_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        body{
            background: radial-gradient(circle at top, rgba(56, 189, 248, 0.2), transparent 55%),
                        radial-gradient(circle at 20% 20%, rgba(99, 102, 241, 0.25), transparent 45%),
                        #0b1120;
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
            font-family: "Tajawal", "Segoe UI", sans-serif;
            padding: 32px 16px;
        }
        .login-shell{
            display:grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 28px;
            width: min(95vw, 980px);
            align-items: stretch;
            color: #e2e8f0;
        }
        .login-aside{
            position:relative;
            overflow:hidden;
            border-radius: 26px;
            padding: 36px;
            background: linear-gradient(145deg, rgba(30, 41, 59, 0.95), rgba(15, 23, 42, 0.9));
            border: 1px solid rgba(148, 163, 184, 0.2);
            box-shadow: 0 20px 55px rgba(15, 23, 42, 0.35);
        }
        .login-aside::before{
            content:"";
            position:absolute;
            inset:-40% auto auto -20%;
            width: 320px;
            height: 320px;
            background: radial-gradient(circle, rgba(56, 189, 248, 0.25), transparent 70%);
        }
        .login-aside::after{
            content:"";
            position:absolute;
            inset:auto -30% -35% auto;
            width: 280px;
            height: 280px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.25), transparent 70%);
        }
        .brand-badge{
            height: 160px;
            width: 160px;
            border-radius: 28px;
            background: rgba(15, 23, 42, 0.8);
            display: grid;
            place-items: center;
            margin-bottom: 20px;
            border: 1px solid rgba(148, 163, 184, 0.35);
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.4);
            position: relative;
            z-index: 1;
        }
        .brand-badge img{
            max-width: 85%;
            height: auto;
            image-rendering: -webkit-optimize-contrast;
        }
        .brand-title{
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }
        .brand-subtitle{
            color: #cbd5f5;
            margin-bottom: 1.5rem;
            position: relative;
            z-index: 1;
        }
        .brand-features{
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid;
            gap: 12px;
            position: relative;
            z-index: 1;
        }
        .brand-features li{
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 14px;
            padding: 10px 14px;
            font-size: 0.95rem;
            color: #e2e8f0;
        }
        .login-card{
            background: rgba(15, 23, 42, 0.92);
            border: 1px solid rgba(148, 163, 184, 0.25);
            box-shadow: 0 24px 60px rgba(2, 8, 23, 0.5);
            border-radius: 24px;
            padding: 34px;
            backdrop-filter: blur(8px);
        }
        .login-card .logo{
            height: 150px;
            width: 150px;
            border-radius: 24px;
            background: rgba(15, 23, 42, 0.8);
            display: grid;
            place-items: center;
            margin: 0 auto 20px;
            border: 1px solid rgba(148, 163, 184, 0.35);
            box-shadow: 0 18px 38px rgba(15, 23, 42, 0.4);
        }
        .login-card .logo img{
            max-width: 85%;
            height: auto;
            image-rendering: -webkit-optimize-contrast;
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
        @media (max-width: 768px){
            .login-aside{
                order: 2;
            }
            .login-card{
                order: 1;
            }
        }
    </style>
</head>
<body>
    <div class="login-shell">
        <div class="login-aside">
            <div class="brand-badge"><img src="<?= htmlspecialchars($logo_src) ?>" alt="الشعار"></div>
            <h1 class="brand-title"><?= htmlspecialchars($company_name) ?></h1>
            <p class="brand-subtitle">منصة ذكية وقوية لإدارة الأملاك والمقاولات بكفاءة عالية.</p>
            <ul class="brand-features">
                <li>لوحات متابعة شاملة للعقارات والوحدات والعقود.</li>
                <li>تنبيهات ذكية للدفعات والصيانة والمواعيد.</li>
                <li>تقارير دقيقة لدعم قرارات الإدارة.</li>
            </ul>
        </div>
        <div class="login-card text-center">
            <div class="logo"><img src="<?= htmlspecialchars($logo_src) ?>" alt="الشعار"></div>
            <h2><?= htmlspecialchars($company_name) ?></h2>
            <p>تسجيل دخول آمن لإدارة الأملاك</p>
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
    </div>
</body>
</html>
