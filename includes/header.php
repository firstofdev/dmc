<?php
if (session_status() === PHP_SESSION_NONE) session_start();
enforce_session_security();
if(!isset($_SESSION['uid'])) { header("Location: login.php"); exit; }
$user_name = $_SESSION['user_name'] ?? 'المدير';
$role = $_SESSION['role'] ?? 'admin'; // نحتاج الصلاحية هنا
$p = $_GET['p'] ?? 'dashboard';
$page_titles = [
    'dashboard' => 'لوحة القيادة',
    'properties' => 'العقارات',
    'units' => 'الوحدات',
    'contracts' => 'العقود',
    'tenants' => 'المستأجرين',
    'alerts' => 'التنبيهات',
    'maintenance' => 'الصيانة',
    'vendors' => 'المقاولين',
    'users' => 'المستخدمين',
    'settings' => 'الإعدادات',
    'smart_center' => 'مركز التمكين الذكي',
    'reports' => 'التقارير المالية',
    'lease_calendar' => 'تقويم العقود و ROI',
];
$page_key = array_key_exists($p, $page_titles) ? $p : 'dashboard';
$page_title = $page_titles[$page_key] ?? 'لوحة القيادة';

// جلب الشعار
$settingsMap = [];
try {
    $stmt = $pdo->prepare("SELECT k, v FROM settings WHERE k IN ('logo','company_name','timezone','date_format','maintenance_mode','maintenance_message')");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settingsMap[$row['k']] = $row['v'];
    }
} catch (Exception $e) {
}
$db_logo = $settingsMap['logo'] ?? null;
$logo_src = $db_logo && file_exists($db_logo) ? $db_logo : 'logo.png';
$company_name = $settingsMap['company_name'] ?? 'اسم الشركة غير محدد';
$timezone = $settingsMap['timezone'] ?? 'Asia/Riyadh';
if (!@date_default_timezone_set($timezone)) {
    date_default_timezone_set('Asia/Riyadh');
}
$dateFormat = $settingsMap['date_format'] ?? 'Y-m-d';
$displayDate = date($dateFormat);
$maintenanceEnabled = ($settingsMap['maintenance_mode'] ?? 'off') === 'on';
$maintenanceMessage = $settingsMap['maintenance_message'] ?? 'النظام تحت صيانة مجدولة، قد تتأخر بعض الخدمات.';
$company_name_safe = htmlspecialchars($company_name);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title><?= $company_name_safe ?> - النظام المتكامل</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* GEMINI ULTIMATE DARK THEME */
        :root {
            --bg:#05050a;
            --card:rgba(15, 15, 24, 0.9);
            --border:rgba(148, 163, 184, 0.18);
            --primary:#6366f1;
            --accent:#a855f7;
            --accent-2:#22d3ee;
            --text:#fff;
            --muted:#64748b;
            --success:#10b981;
            --danger:#ef4444;
            --warning:#f59e0b;
            --sidebar-bg:rgba(8, 8, 14, 0.9);
            --sidebar-shadow:8px 0 40px rgba(0,0,0,0.45);
            --logo-bg:radial-gradient(circle at center, rgba(99,102,241,0.25), rgba(0,0,0,0.9));
            --nav-hover-bg:rgba(99,102,241,0.08);
            --nav-hover-text:#ffffff;
            --main-bg:radial-gradient(circle at 10% 10%, rgba(99,102,241,0.12), transparent 45%);
            --table-th:#666;
            --table-td-bg:rgba(18, 18, 28, 0.9);
            --table-td-border:rgba(148, 163, 184, 0.15);
            --btn-dark-bg:#1a1a1a;
            --btn-dark-border:#333;
            --modal-overlay:rgba(2,6,23,0.78);
            --modal-bg:rgba(17, 17, 28, 0.95);
            --modal-border:rgba(148, 163, 184, 0.2);
            --input-bg:rgba(15, 23, 42, 0.7);
            --input-border:rgba(148, 163, 184, 0.2);
            --scrollbar:rgba(99,102,241,0.4);
            --close-bg:rgba(239,68,68,0.15);
            --close-hover:#ef4444;
            --tag-bg:rgba(99,102,241,0.12);
            --glow:0 0 25px rgba(99,102,241,0.25);
            --glow-strong:0 0 40px rgba(99,102,241,0.4);
        }
        * { box-sizing:border-box; outline:none; }
        body { font-family:'Tajawal'; background:var(--bg); color:var(--text); margin:0; display:flex; height:100vh; overflow:hidden; position:relative; }
        body::before {
            content:'';
            position:fixed;
            inset:0;
            background:
                radial-gradient(circle at 15% 15%, rgba(99,102,241,0.18), transparent 35%),
                radial-gradient(circle at 85% 20%, rgba(168,85,247,0.18), transparent 35%),
                radial-gradient(circle at 50% 80%, rgba(34,211,238,0.12), transparent 40%);
            z-index:-1;
        }
        ::-webkit-scrollbar { width:6px; } ::-webkit-scrollbar-thumb { background:var(--scrollbar); border-radius:10px; }

        /* Sidebar */
        .sidebar { width:280px; background:var(--sidebar-bg); border-left:1px solid var(--border); display:flex; flex-direction:column; padding:25px; z-index:20; box-shadow:var(--sidebar-shadow); backdrop-filter: blur(18px); }
        .logo-wrapper { width: 150px; height: 150px; margin: 0 auto 22px; border-radius: 50%; background: var(--logo-bg); border: 2px solid var(--border); display: flex; align-items: center; justify-content: center; overflow: hidden; padding: 14px; transition: 0.3s; animation:floatLogo 6s ease-in-out infinite; }
        .logo-wrapper:hover { border-color: var(--primary); box-shadow: var(--glow); transform: scale(1.05); }
        .logo-img { width: 100%; height: 100%; object-fit: contain; image-rendering: -webkit-optimize-contrast; }
        .nav-link { display:flex; align-items:center; gap:15px; padding:16px; margin-bottom:6px; border-radius:14px; color:var(--muted); text-decoration:none; font-weight:500; transition:0.3s; position:relative; overflow:hidden; }
        .nav-link::after { content:''; position:absolute; inset:0; background:linear-gradient(135deg, rgba(99,102,241,0.15), rgba(168,85,247,0.08)); opacity:0; transition:0.3s; }
        .nav-link:hover::after, .nav-link.active::after { opacity:1; }
        .nav-link:hover, .nav-link.active { color:var(--nav-hover-text); transform:translateX(-4px); }
        .nav-link i { width:20px; text-align:center; font-size:18px; color:var(--primary); position:relative; z-index:1; }
        .nav-link span { position:relative; z-index:1; }

        /* Main Content */
        .main { flex:1; padding:40px; overflow-y:auto; background:var(--main-bg); position:relative; }
        .header { display:flex; justify-content:space-between; align-items:center; margin-bottom:40px; padding-bottom:20px; border-bottom:1px solid var(--border); }
        .header-actions { display:flex; gap:12px; align-items:center; }
        .smart-toolbar { display:flex; flex-wrap:wrap; align-items:center; gap:18px; justify-content:space-between; background:var(--card); border:1px solid var(--border); padding:16px 20px; border-radius:20px; margin-bottom:30px; box-shadow:0 12px 35px rgba(15,23,42,0.25); backdrop-filter: blur(16px); }
        .smart-search { flex:1; display:flex; align-items:center; gap:12px; background:var(--input-bg); border:1px solid var(--input-border); border-radius:16px; padding:12px 16px; min-width:260px; transition:0.3s; }
        .smart-search i { color:var(--muted); font-size:16px; }
        .smart-search input { background:transparent; border:none; color:var(--text); width:100%; font-size:15px; font-family:'Tajawal'; }
        .smart-search-hint { background:var(--tag-bg); color:var(--primary); padding:4px 10px; border-radius:12px; font-size:12px; font-weight:bold; display:flex; align-items:center; gap:6px; }
        .search-clear { width:38px; height:38px; border-radius:12px; border:1px solid var(--input-border); background:rgba(0,0,0,0.15); color:var(--muted); display:inline-flex; align-items:center; justify-content:center; cursor:pointer; transition:0.2s; }
        .search-clear:hover { border-color:var(--primary); color:var(--primary); box-shadow:var(--glow); }
        .smart-meta { display:flex; align-items:center; gap:10px; color:var(--muted); font-size:14px; }
        .page-pill { background:var(--tag-bg); color:var(--primary); padding:6px 12px; border-radius:14px; font-weight:bold; }
        .smart-assist { display:flex; align-items:center; gap:14px; background:var(--card); border:1px solid var(--border); padding:14px 18px; border-radius:18px; margin-bottom:24px; box-shadow:0 12px 35px rgba(15,23,42,0.18); }
        .assist-icon { width:46px; height:46px; border-radius:14px; display:grid; place-items:center; background:var(--tag-bg); color:var(--primary); box-shadow:var(--glow); }
        .assist-title { font-size:15px; font-weight:800; color:var(--text); }
        .assist-body { color:var(--muted); font-size:14px; }
        .smart-assist.pulse { box-shadow:0 18px 45px rgba(99,102,241,0.25); }
        .maintenance-banner { background:linear-gradient(135deg, rgba(239,68,68,0.18), rgba(248,113,113,0.12)); border:1px solid rgba(239,68,68,0.45); color:#fecaca; padding:14px 18px; border-radius:16px; display:flex; align-items:center; gap:12px; margin-bottom:24px; box-shadow:0 12px 24px rgba(15,23,42,0.25); }
        .maintenance-banner i { color:#f87171; }

        .btn-icon { width:44px; height:44px; border-radius:14px; display:inline-flex; align-items:center; justify-content:center; }
        .btn-small { padding:10px 14px; font-size:13px; border-radius:12px; }

        body.sidebar-collapsed .sidebar { width:90px; padding:20px 12px; }
        body.sidebar-collapsed .sidebar .nav-link { justify-content:center; gap:0; }
        body.sidebar-collapsed .sidebar .nav-link span { display:none; }
        body.sidebar-collapsed .sidebar .logo-wrapper { width:90px; height:90px; margin-bottom:16px; }
        body.sidebar-collapsed .sidebar h4, body.sidebar-collapsed .sidebar .tagline { display:none; }
        body.sidebar-collapsed .main { padding:35px; }
        
        /* Cards & Tables */
        .card { background:var(--card); border:1px solid var(--border); border-radius:24px; padding:30px; margin-bottom:30px; position:relative; box-shadow:0 20px 45px rgba(2,6,23,0.2); transition:all 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
        .card::after { content:''; position:absolute; inset:0; border-radius:24px; background:linear-gradient(135deg, rgba(99,102,241,0.05), rgba(168,85,247,0.05)); opacity:0; transition:opacity 0.4s ease; pointer-events:none; }
        .card:hover { transform:translateY(-8px) scale(1.01); box-shadow:0 30px 70px rgba(2,6,23,0.4), 0 0 40px rgba(99,102,241,0.15); border-color:rgba(99,102,241,0.3); }
        .card:hover::after { opacity:1; }
        table { width:100%; border-collapse:separate; border-spacing:0 8px; }
        th { text-align:right; color:var(--table-th); font-size:13px; padding:10px 20px; }
        td { background:var(--table-td-bg); padding:20px; border:1px solid var(--table-td-border); border-left:none; border-right:none; transition:all 0.3s ease; }
        td:first-child { border-radius:0 15px 15px 0; border-right:1px solid var(--table-td-border); }
        td:last-child { border-radius:15px 0 0 15px; border-left:1px solid var(--table-td-border); }
        tbody tr:hover td { background:rgba(99,102,241,0.08); border-color:rgba(99,102,241,0.2); }

        /* Buttons & Badges */
        .btn { padding:15px 24px; border:none; border-radius:14px; font-weight:bold; cursor:pointer; font-size:14px; transition:all 0.3s ease; display:inline-flex; align-items:center; gap:10px; color:white; position:relative; overflow:hidden; text-decoration:none; }
        .btn::before { content:''; position:absolute; inset:0; background:linear-gradient(120deg, rgba(255,255,255,0), rgba(255,255,255,0.25), rgba(255,255,255,0)); transform:translateX(-100%); transition:0.6s; }
        .btn:hover::before { transform:translateX(100%); }
        .btn:hover { transform:translateY(-3px) scale(1.02); box-shadow:0 15px 35px rgba(0,0,0,0.3); }
        .btn:active { transform:translateY(-1px) scale(0.98); }
        .btn-primary { background:linear-gradient(135deg, var(--primary), var(--accent)); box-shadow:0 10px 25px rgba(99,102,241,0.35); }
        .btn-primary:hover { box-shadow:0 15px 35px rgba(99,102,241,0.5); }
        .btn-danger { background:linear-gradient(135deg, #ef4444, #b91c1c); box-shadow:0 10px 25px rgba(239,68,68,0.3); }
        .btn-danger:hover { box-shadow:0 15px 35px rgba(239,68,68,0.45); }
        .btn-dark { background:var(--btn-dark-bg); border:1px solid var(--btn-dark-border); color:white; }
        .btn-dark:hover { border-color:var(--primary); background:rgba(99,102,241,0.08); }
        .btn-sm { padding:8px 16px; font-size:12px; }
        .badge { padding:5px 10px; border-radius:8px; font-size:12px; font-weight:bold; }
        
        /* Modal Styles (موحدة لكل النظام) */
        .modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:var(--modal-overlay); z-index:2000; backdrop-filter:blur(8px); justify-content:center; align-items:center; padding:20px; }
        .modal-content { background:var(--modal-bg); width:100%; max-width:650px; padding:40px; border-radius:30px; border:1px solid var(--modal-border); position:relative; animation:slideUp 0.3s ease; box-shadow:0 30px 70px rgba(2,6,23,0.6); }
        @keyframes slideUp { from{transform:translateY(30px) scale(0.98);opacity:0} to{transform:translateY(0) scale(1);opacity:1} }
        .close-icon { position: absolute; top: 25px; left: 25px; width: 35px; height: 35px; background: var(--close-bg); color: var(--close-hover); border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.3s; font-size: 18px; z-index: 10; }
        .close-icon:hover { background: var(--close-hover); color: white; transform: rotate(90deg); }
        .modal-header { text-align:center; margin-bottom:30px; border-bottom:1px solid var(--table-td-border); padding-bottom:20px; }
        .modal-title { font-size:22px; font-weight:800; color:var(--text); }

        /* Forms */
        .inp { width:100%; padding:18px; background:var(--input-bg); border:1px solid var(--input-border); border-radius:16px; color:var(--text); font-family:'Tajawal'; font-size:16px; margin-bottom:15px; transition:0.3s; }
        .inp:focus { border-color:var(--primary); box-shadow:0 0 0 4px rgba(99,102,241,0.2); }
        .inp-label { display:block; margin-bottom:8px; color:var(--muted); font-size:14px; font-weight:bold; }
        .inp-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
        @keyframes floatLogo { 0%, 100% { transform:translateY(0); } 50% { transform:translateY(-6px); } }
    </style>
</head>
<body data-page="<?= htmlspecialchars($page_key) ?>">

    <div class="sidebar">
    <div style="text-align:center; margin-bottom:30px">
        <div class="logo-wrapper"><img src="<?= $logo_src ?>" class="logo-img" alt="Logo"></div>
        <h4 style="margin:10px 0 5px; font-weight:800"><?= $company_name_safe ?></h4>
        <span class="tagline" style="font-size:12px; color:var(--primary); background:var(--tag-bg); padding:4px 10px; border-radius:20px">نظام الإدارة</span>
    </div>
    <div style="flex:1; overflow-y:auto; padding-left:5px">
        <a href="index.php?p=dashboard" class="nav-link <?= $p=='dashboard'?'active':'' ?>"><i class="fa-solid fa-chart-line"></i> <span>لوحة القيادة</span></a>
        <a href="index.php?p=properties" class="nav-link <?= $p=='properties'?'active':'' ?>"><i class="fa-solid fa-building"></i> <span>العقارات</span></a>
        <a href="index.php?p=units" class="nav-link <?= $p=='units'?'active':'' ?>"><i class="fa-solid fa-house-laptop"></i> <span>الوحدات</span></a>
        <a href="index.php?p=contracts" class="nav-link <?= $p=='contracts'?'active':'' ?>"><i class="fa-solid fa-file-signature"></i> <span>العقود</span></a>
        <a href="index.php?p=tenants" class="nav-link <?= $p=='tenants'?'active':'' ?>"><i class="fa-solid fa-user-group"></i> <span>المستأجرين</span></a>
        <a href="index.php?p=alerts" class="nav-link <?= $p=='alerts'?'active':'' ?>"><i class="fa-solid fa-bell-concierge"></i> <span>التنبيهات</span></a>
        <a href="index.php?p=maintenance" class="nav-link <?= $p=='maintenance'?'active':'' ?>"><i class="fa-solid fa-toolbox"></i> <span>الصيانة</span></a>
        <a href="index.php?p=vendors" class="nav-link <?= $p=='vendors'?'active':'' ?>"><i class="fa-solid fa-people-carry-box"></i> <span>المقاولين</span></a>
        <a href="index.php?p=reports" class="nav-link <?= $p=='reports'?'active':'' ?>"><i class="fa-solid fa-file-invoice-dollar"></i> <span>التقارير المالية</span></a>
        <a href="index.php?p=smart_center" class="nav-link <?= $p=='smart_center'?'active':'' ?>"><i class="fa-solid fa-microchip"></i> <span>التمكين الذكي</span></a>
        <?php if($role === 'admin'): ?>
        <a href="index.php?p=users" class="nav-link <?= $p=='users'?'active':'' ?>"><i class="fa-solid fa-user-shield"></i> <span>المستخدمين</span></a>
        <?php endif; ?>
        <a href="index.php?p=settings" class="nav-link <?= $p=='settings'?'active':'' ?>"><i class="fa-solid fa-gear"></i> <span>الإعدادات</span></a>
    </div>
    <a href="logout.php" class="nav-link" style="color:#ef4444; margin-top:10px"><i class="fa-solid fa-power-off"></i> <span>خروج</span></a>
</div>

<div class="main">
    <?php if ($maintenanceEnabled): ?>
        <div class="maintenance-banner">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <div>
                <strong>وضع الصيانة مفعّل</strong>
                <div style="font-size:13px; color:#fecaca; margin-top:4px;"><?= htmlspecialchars($maintenanceMessage) ?></div>
            </div>
        </div>
    <?php endif; ?>
    <div class="header">
        <div>
            <h1 style="margin:0; font-size:26px; font-weight:800">أهلاً، <?= $user_name ?> 👋</h1>
            <div style="color:var(--muted); font-size:14px; margin-top:5px">إدارة الأملاك الذكية</div>
        </div>
        <div class="header-actions">
            <button class="btn btn-dark btn-icon" id="sidebarToggle" type="button" title="طي/إظهار القائمة">
                <i class="fa-solid fa-bars"></i>
            </button>
            <button class="btn btn-dark btn-small">
                <i class="fa-regular fa-calendar"></i> <?= $displayDate ?>
            </button>
        </div>
    </div>

    <div class="smart-toolbar">
        <div class="smart-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input id="globalSearch" type="search" placeholder="بحث ذكي داخل الصفحة والجداول..." autocomplete="off" aria-label="البحث في محتوى الصفحة والجداول">
            <button class="search-clear" type="button" id="clearSearch" title="مسح البحث">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <span class="smart-search-hint"><i class="fa-solid fa-wand-magic-sparkles"></i> Ctrl + /</span>
        </div>
        <div class="smart-meta">
            <span>الصفحة الحالية:</span>
            <span class="page-pill"><i class="fa-regular fa-compass"></i> <?= $page_title ?></span>
            <span id="searchCount"><i class="fa-solid fa-list-check"></i> كل النتائج ظاهرة</span>
        </div>
    </div>
    <div class="smart-assist" id="smartAssist">
        <div class="assist-icon"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
        <div>
            <div class="assist-title">اقتراح ذكي</div>
            <div class="assist-body" id="smartHintText">ابدأ بالبحث للوصول السريع للمحتوى.</div>
        </div>
        <button class="btn btn-dark btn-icon" id="refreshHint" type="button" title="اقتراح آخر">
            <i class="fa-solid fa-rotate"></i>
        </button>
    </div>
