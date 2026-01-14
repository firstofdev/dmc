<?php
require 'config.php';
if($_POST){
    check_csrf();
    $username = trim($_POST['user'] ?? '');
    $password = (string) ($_POST['pass'] ?? '');
    if ($username === '' || $password === '') {
        $err = "يرجى إدخال اسم المستخدم وكلمة المرور.";
    } else {
        $ip = get_client_ip();
        $ipKey = 'login_ip_' . $ip;
        $userKey = 'login_user_' . strtolower($username);
        $ipLimit = rate_limit_check($ipKey, 10, 600, 900);
        $userLimit = rate_limit_check($userKey, 5, 600, 900);
        if (!$ipLimit['allowed'] || !$userLimit['allowed']) {
            $retryAfter = max($ipLimit['retry_after'], $userLimit['retry_after']);
            $minutes = max(1, (int) ceil($retryAfter / 60));
            $err = "محاولات كثيرة، حاول مرة أخرى بعد {$minutes} دقيقة.";
            log_activity($pdo, "تم حظر محاولات تسجيل الدخول من {$ip} للمستخدم {$username}", 'auth_blocked');
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username=?");
            $stmt->execute([$username]); $u = $stmt->fetch();
            if($u && password_verify($password, $u['password'])){ 
                if (password_needs_rehash($u['password'], PASSWORD_DEFAULT)) {
                    try {
                        $newHash = password_hash($password, PASSWORD_DEFAULT);
                        $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $updateStmt->execute([$newHash, $u['id']]);
                        log_activity($pdo, "تم تحديث تجزئة كلمة المرور للمستخدم: ".$u['username'], 'auth_rehash');
                    } catch (Exception $e) {
                        log_activity($pdo, "تعذر تحديث تجزئة كلمة المرور للمستخدم: ".$u['username'], 'auth_rehash_failed');
                    }
                }
                session_regenerate_id(true);
                $_SESSION['uid'] = $u['id'];
                $_SESSION['user_name'] = $u['full_name'] ?: $u['username'];
                $_SESSION['role'] = $u['role'] ?? 'staff';
                initialize_session_security();
                rate_limit_clear($ipKey);
                rate_limit_clear($userKey);
                log_activity($pdo, "تسجيل دخول ناجح للمستخدم: ".$u['username'], 'auth_success');
                header("Location: index.php"); exit; 
            } 
            log_activity($pdo, "فشل تسجيل الدخول للمستخدم: ".$username, 'auth_failed');
            usleep(250000);
            $err="خطأ في البيانات";
        }
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    <style>
        :root {
            --login-bg: radial-gradient(circle at top, rgba(56, 189, 248, 0.2), transparent 55%),
                        radial-gradient(circle at 20% 20%, rgba(99, 102, 241, 0.25), transparent 45%),
                        #0b1120;
            --text-color: #e2e8f0;
            --aside-bg: linear-gradient(145deg, rgba(30, 41, 59, 0.95), rgba(15, 23, 42, 0.9));
            --aside-border: rgba(148, 163, 184, 0.2);
            --aside-shadow: 0 20px 55px rgba(15, 23, 42, 0.35);
            --card-bg: rgba(15, 23, 42, 0.92);
            --card-border: rgba(148, 163, 184, 0.25);
            --card-shadow: 0 24px 60px rgba(2, 8, 23, 0.5);
            --badge-bg: rgba(15, 23, 42, 0.8);
            --badge-border: rgba(148, 163, 184, 0.35);
            --input-bg: #0f172a;
            --input-border: rgba(148, 163, 184, 0.25);
            --input-text: #e2e8f0;
            --muted-text: #94a3b8;
            --feature-bg: rgba(15, 23, 42, 0.6);
            --feature-border: rgba(148, 163, 184, 0.2);
        }
        
        body.light-theme {
            --login-bg: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 50%, #f5f3ff 100%);
            --text-color: #0f172a;
            --aside-bg: linear-gradient(145deg, rgba(255, 255, 255, 0.95), rgba(248, 250, 252, 0.9));
            --aside-border: rgba(148, 163, 184, 0.3);
            --aside-shadow: 0 20px 55px rgba(0, 0, 0, 0.1);
            --card-bg: rgba(255, 255, 255, 0.95);
            --card-border: rgba(148, 163, 184, 0.3);
            --card-shadow: 0 24px 60px rgba(0, 0, 0, 0.1);
            --badge-bg: rgba(248, 250, 252, 0.9);
            --badge-border: rgba(148, 163, 184, 0.4);
            --input-bg: #f8fafc;
            --input-border: rgba(148, 163, 184, 0.4);
            --input-text: #0f172a;
            --muted-text: #64748b;
            --feature-bg: rgba(248, 250, 252, 0.8);
            --feature-border: rgba(148, 163, 184, 0.3);
        }
        
        body {
            background: var(--login-bg);
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
            font-family: "Tajawal", "Segoe UI", sans-serif;
            padding: 32px 16px;
            transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }
        
        /* Theme Toggle Button */
        .theme-toggle-wrapper {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1000;
        }
        #loginThemeToggle {
            position: relative;
            background: linear-gradient(135deg, rgba(99,102,241,0.2), rgba(168,85,247,0.15));
            border: 2px solid rgba(99,102,241,0.4);
            color: var(--text-color);
            overflow: hidden;
            width: 52px;
            height: 52px;
            border-radius: 50%;
            transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(10px);
        }
        #loginThemeToggle::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at center, rgba(255,255,255,0.2), transparent 70%);
            opacity: 0;
            transition: opacity 0.5s ease;
        }
        #loginThemeToggle:hover::before {
            opacity: 1;
        }
        #loginThemeToggle:hover {
            transform: scale(1.1) rotate(180deg);
            border-color: #6366f1;
            box-shadow: 0 0 30px rgba(99,102,241,0.6), 0 0 60px rgba(99,102,241,0.3);
            background: linear-gradient(135deg, rgba(99,102,241,0.4), rgba(168,85,247,0.3));
        }
        #loginThemeToggle i {
            font-size: 20px;
            transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
            z-index: 1;
        }
        #loginThemeToggle:hover i {
            transform: scale(1.2);
            filter: drop-shadow(0 4px 12px rgba(99,102,241,0.8));
        }
        #loginThemeToggle.theme-switching {
            animation: themeSwitchPulse 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        #loginThemeToggle.theme-switching i {
            animation: iconSpinScale 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        @keyframes themeSwitchPulse {
            0%, 100% { transform: scale(1) rotate(0deg); }
            25% { transform: scale(1.15) rotate(90deg); }
            50% { transform: scale(0.95) rotate(180deg); }
            75% { transform: scale(1.1) rotate(270deg); }
        }
        @keyframes iconSpinScale {
            0% { transform: scale(1) rotate(0deg); opacity: 1; }
            50% { transform: scale(0.5) rotate(180deg); opacity: 0.3; }
            100% { transform: scale(1) rotate(360deg); opacity: 1; }
        }
        body.light-theme #loginThemeToggle {
            background: linear-gradient(135deg, rgba(251,191,36,0.3), rgba(245,158,11,0.2));
            border-color: rgba(251,191,36,0.5);
        }
        body.light-theme #loginThemeToggle:hover {
            background: linear-gradient(135deg, rgba(251,191,36,0.5), rgba(245,158,11,0.4));
            box-shadow: 0 0 30px rgba(251,191,36,0.6), 0 0 60px rgba(251,191,36,0.3);
        }
        
        .login-shell{
            display:grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 28px;
            width: min(95vw, 980px);
            align-items: stretch;
            color: var(--text-color);
            transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .login-aside{
            position:relative;
            overflow:hidden;
            border-radius: 26px;
            padding: 36px;
            background: var(--aside-bg);
            border: 1px solid var(--aside-border);
            box-shadow: var(--aside-shadow);
            transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
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
            background: var(--badge-bg);
            display: grid;
            place-items: center;
            margin-bottom: 20px;
            border: 1px solid var(--badge-border);
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.4);
            position: relative;
            z-index: 1;
            transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
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
            color: var(--muted-text);
            margin-bottom: 1.5rem;
            position: relative;
            z-index: 1;
            transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
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
            background: var(--feature-bg);
            border: 1px solid var(--feature-border);
            border-radius: 14px;
            padding: 10px 14px;
            font-size: 0.95rem;
            color: var(--text-color);
            transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .login-card{
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            box-shadow: var(--card-shadow);
            border-radius: 24px;
            padding: 34px;
            backdrop-filter: blur(8px);
            transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .login-card .logo{
            height: 150px;
            width: 150px;
            border-radius: 24px;
            background: var(--badge-bg);
            display: grid;
            place-items: center;
            margin: 0 auto 20px;
            border: 1px solid var(--badge-border);
            box-shadow: 0 18px 38px rgba(15, 23, 42, 0.4);
            transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
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
            color: var(--muted-text);
            margin-bottom: 1.5rem;
            transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .login-card .form-control{
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            color: var(--input-text);
            padding: 0.75rem 1rem;
            border-radius: 12px;
            transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .login-card .form-control::placeholder{
            color: var(--muted-text);
        }
        .login-card .form-control:focus{
            border-color: #38bdf8;
            box-shadow: 0 0 0 0.2rem rgba(56, 189, 248, 0.2);
        }
        .login-card .form-label {
            color: var(--text-color);
            transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
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
    <div class="theme-toggle-wrapper">
        <button id="loginThemeToggle" type="button" title="تبديل المظهر">
            <i class="fa-solid fa-sun"></i>
        </button>
    </div>
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
    <script>
        // Login Page Theme Switcher
        (function() {
            var themeToggle = document.getElementById('loginThemeToggle');
            var body = document.body;
            
            // Check localStorage for saved theme
            var savedTheme = localStorage.getItem('loginTheme') || 'dark';
            if (savedTheme === 'light') {
                body.classList.add('light-theme');
                themeToggle.querySelector('i').className = 'fa-solid fa-moon';
            }
            
            themeToggle.addEventListener('click', function() {
                var isLight = body.classList.contains('light-theme');
                var newTheme = isLight ? 'dark' : 'light';
                
                // Add animation class
                themeToggle.classList.add('theme-switching');
                setTimeout(function() {
                    themeToggle.classList.remove('theme-switching');
                }, 600);
                
                if (isLight) {
                    body.classList.remove('light-theme');
                } else {
                    body.classList.add('light-theme');
                }
                
                // Update icon with smooth transition
                var icon = themeToggle.querySelector('i');
                icon.style.opacity = '0';
                setTimeout(function() {
                    icon.className = 'fa-solid fa-' + (isLight ? 'sun' : 'moon');
                    icon.style.opacity = '1';
                }, 200);
                
                // Save to localStorage
                localStorage.setItem('loginTheme', newTheme);
            });
        })();
    </script>
</body>
</html>
