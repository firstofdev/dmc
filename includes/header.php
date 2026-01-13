<?php
if (session_status() === PHP_SESSION_NONE) session_start();
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
    'settings' => 'الإعدادات'
];
$page_title = $page_titles[$p] ?? 'لوحة القيادة';

// جلب الشعار
$stmt = $pdo->prepare("SELECT v FROM settings WHERE k='logo'"); $stmt->execute();
$db_logo = $stmt->fetchColumn();
$logo_src = $db_logo && file_exists($db_logo) ? $db_logo : 'logo.png';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>دار الميار - النظام المتكامل</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* GEMINI ULTIMATE DARK THEME */
        :root {
            --bg:#050505;
            --card:#0f0f0f;
            --border:#222;
            --primary:#6366f1;
            --accent:#a855f7;
            --text:#fff;
            --muted:#64748b;
            --success:#10b981;
            --danger:#ef4444;
            --sidebar-bg:#080808;
            --sidebar-shadow:5px 0 40px rgba(0,0,0,0.5);
            --logo-bg:radial-gradient(circle at center, #1e1e2e, #000);
            --nav-hover-bg:rgba(99,102,241,0.08);
            --nav-hover-text:#ffffff;
            --main-bg:radial-gradient(circle at 10% 10%, #11101f, transparent 30%);
            --table-th:#666;
            --table-td-bg:#141414;
            --table-td-border:#222;
            --btn-dark-bg:#1a1a1a;
            --btn-dark-border:#333;
            --modal-overlay:rgba(0,0,0,0.9);
            --modal-bg:#111;
            --modal-border:#333;
            --input-bg:#080808;
            --input-border:#2a2a2a;
            --scrollbar:#333;
            --close-bg:rgba(239,68,68,0.15);
            --close-hover:#ef4444;
            --tag-bg:rgba(99,102,241,0.1);
        }
        [data-theme="light"] {
            --bg:#f6f7fb;
            --card:#ffffff;
            --border:#e5e7eb;
            --text:#111827;
            --muted:#6b7280;
            --sidebar-bg:#ffffff;
            --sidebar-shadow:5px 0 40px rgba(15,23,42,0.08);
            --logo-bg:radial-gradient(circle at center, #f8fafc, #e2e8f0);
            --nav-hover-bg:rgba(99,102,241,0.12);
            --nav-hover-text:#111827;
            --main-bg:radial-gradient(circle at 10% 10%, #eef2ff, transparent 35%);
            --table-th:#6b7280;
            --table-td-bg:#ffffff;
            --table-td-border:#e5e7eb;
            --btn-dark-bg:#111827;
            --btn-dark-border:#1f2937;
            --modal-overlay:rgba(15,23,42,0.45);
            --modal-bg:#ffffff;
            --modal-border:#e5e7eb;
            --input-bg:#f9fafb;
            --input-border:#d1d5db;
            --scrollbar:#cbd5f5;
            --close-bg:rgba(239,68,68,0.15);
            --close-hover:#ef4444;
            --tag-bg:rgba(99,102,241,0.12);
        }
        * { box-sizing:border-box; outline:none; }
        body { font-family:'Tajawal'; background:var(--bg); color:var(--text); margin:0; display:flex; height:100vh; overflow:hidden; }
        ::-webkit-scrollbar { width:6px; } ::-webkit-scrollbar-thumb { background:var(--scrollbar); border-radius:10px; }

        /* Sidebar */
        .sidebar { width:280px; background:var(--sidebar-bg); border-left:1px solid var(--border); display:flex; flex-direction:column; padding:25px; z-index:20; box-shadow:var(--sidebar-shadow); }
        .logo-wrapper { width: 100px; height: 100px; margin: 0 auto 20px; border-radius: 50%; background: var(--logo-bg); border: 2px solid var(--border); display: flex; align-items: center; justify-content: center; overflow: hidden; padding: 10px; transition: 0.3s; }
        .logo-wrapper:hover { border-color: var(--primary); box-shadow: 0 0 25px rgba(99,102,241,0.4); transform: scale(1.05); }
        .logo-img { max-width: 100%; max-height: 100%; object-fit: contain; }
        .nav-link { display:flex; align-items:center; gap:15px; padding:16px; margin-bottom:6px; border-radius:14px; color:var(--muted); text-decoration:none; font-weight:500; transition:0.3s; }
        .nav-link:hover, .nav-link.active { background:var(--nav-hover-bg); color:var(--nav-hover-text); border-color:rgba(99,102,241,0.2); }
        .nav-link i { width:20px; text-align:center; font-size:18px; color:var(--primary); }

        /* Main Content */
        .main { flex:1; padding:40px; overflow-y:auto; background:var(--main-bg); position:relative; }
        .header { display:flex; justify-content:space-between; align-items:center; margin-bottom:40px; padding-bottom:20px; border-bottom:1px solid var(--border); }
        .header-actions { display:flex; gap:12px; align-items:center; }
        .smart-toolbar { display:flex; flex-wrap:wrap; align-items:center; gap:18px; justify-content:space-between; background:var(--card); border:1px solid var(--border); padding:16px 20px; border-radius:20px; margin-bottom:30px; }
        .smart-search { flex:1; display:flex; align-items:center; gap:12px; background:var(--input-bg); border:1px solid var(--input-border); border-radius:16px; padding:12px 16px; min-width:260px; }
        .smart-search i { color:var(--muted); font-size:16px; }
        .smart-search input { background:transparent; border:none; color:var(--text); width:100%; font-size:15px; font-family:'Tajawal'; }
        .smart-search-hint { background:var(--tag-bg); color:var(--primary); padding:4px 10px; border-radius:12px; font-size:12px; font-weight:bold; }
        .smart-meta { display:flex; align-items:center; gap:10px; color:var(--muted); font-size:14px; }
        .page-pill { background:var(--tag-bg); color:var(--primary); padding:6px 12px; border-radius:14px; font-weight:bold; }

        .btn-icon { width:44px; height:44px; border-radius:14px; display:inline-flex; align-items:center; justify-content:center; }
        .btn-small { padding:10px 14px; font-size:13px; border-radius:12px; }

        body.sidebar-collapsed .sidebar { width:90px; padding:20px 12px; }
        body.sidebar-collapsed .sidebar .nav-link { justify-content:center; gap:0; }
        body.sidebar-collapsed .sidebar .nav-link span { display:none; }
        body.sidebar-collapsed .sidebar .logo-wrapper { width:60px; height:60px; margin-bottom:16px; }
        body.sidebar-collapsed .sidebar h4, body.sidebar-collapsed .sidebar .tagline { display:none; }
        body.sidebar-collapsed .main { padding:35px; }
        
        /* Cards & Tables */
        .card { background:var(--card); border:1px solid var(--border); border-radius:24px; padding:30px; margin-bottom:30px; position:relative; }
        table { width:100%; border-collapse:separate; border-spacing:0 8px; }
        th { text-align:right; color:var(--table-th); font-size:13px; padding:10px 20px; }
        td { background:var(--table-td-bg); padding:20px; border:1px solid var(--table-td-border); border-left:none; border-right:none; }
        td:first-child { border-radius:0 15px 15px 0; border-right:1px solid var(--table-td-border); }
        td:last-child { border-radius:15px 0 0 15px; border-left:1px solid var(--table-td-border); }

        /* Buttons & Badges */
        .btn { padding:15px 24px; border:none; border-radius:14px; font-weight:bold; cursor:pointer; font-size:14px; transition:0.3s; display:inline-flex; align-items:center; gap:10px; color:white; }
        .btn-primary { background:linear-gradient(135deg, var(--primary), var(--accent)); box-shadow:0 5px 15px rgba(99,102,241,0.3); }
        .btn-danger { background:linear-gradient(135deg, #ef4444, #b91c1c); box-shadow:0 5px 15px rgba(239,68,68,0.3); }
        .btn-dark { background:var(--btn-dark-bg); border:1px solid var(--btn-dark-border); color:white; }
        .badge { padding:5px 10px; border-radius:8px; font-size:12px; font-weight:bold; }
        
        /* Modal Styles (موحدة لكل النظام) */
        .modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:var(--modal-overlay); z-index:2000; backdrop-filter:blur(5px); justify-content:center; align-items:center; padding:20px; }
        .modal-content { background:var(--modal-bg); width:100%; max-width:650px; padding:40px; border-radius:30px; border:1px solid var(--modal-border); position:relative; animation:slideUp 0.3s ease; box-shadow:0 20px 60px rgba(0,0,0,0.8); }
        @keyframes slideUp { from{transform:translateY(30px);opacity:0} to{transform:translateY(0);opacity:1} }
        .close-icon { position: absolute; top: 25px; left: 25px; width: 35px; height: 35px; background: var(--close-bg); color: var(--close-hover); border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.3s; font-size: 18px; z-index: 10; }
        .close-icon:hover { background: var(--close-hover); color: white; transform: rotate(90deg); }
        .modal-header { text-align:center; margin-bottom:30px; border-bottom:1px solid var(--table-td-border); padding-bottom:20px; }
        .modal-title { font-size:22px; font-weight:800; color:var(--text); }

        /* Forms */
        .inp { width:100%; padding:18px; background:var(--input-bg); border:1px solid var(--input-border); border-radius:16px; color:var(--text); font-family:'Tajawal'; font-size:16px; margin-bottom:15px; }
        .inp:focus { border-color:var(--primary); box-shadow:0 0 0 3px rgba(99,102,241,0.15); }
        .inp-label { display:block; margin-bottom:8px; color:var(--muted); font-size:14px; font-weight:bold; }
        .inp-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
    </style>
</head>
<body>

<div class="sidebar">
    <div style="text-align:center; margin-bottom:30px">
        <div class="logo-wrapper"><img src="<?= $logo_src ?>" class="logo-img" alt="Logo"></div>
        <h4 style="margin:10px 0 5px; font-weight:800">دار الميار</h4>
        <span class="tagline" style="font-size:12px; color:var(--primary); background:var(--tag-bg); padding:4px 10px; border-radius:20px">نظام الإدارة</span>
    </div>
    <div style="flex:1; overflow-y:auto; padding-left:5px">
        <a href="index.php?p=dashboard" class="nav-link <?= $p=='dashboard'?'active':'' ?>"><i class="fa-solid fa-layer-group"></i> <span>لوحة القيادة</span></a>
        <a href="index.php?p=properties" class="nav-link <?= $p=='properties'?'active':'' ?>"><i class="fa-solid fa-city"></i> <span>العقارات</span></a>
        <a href="index.php?p=units" class="nav-link <?= $p=='units'?'active':'' ?>"><i class="fa-solid fa-door-open"></i> <span>الوحدات</span></a>
        <a href="index.php?p=contracts" class="nav-link <?= $p=='contracts'?'active':'' ?>"><i class="fa-solid fa-file-contract"></i> <span>العقود</span></a>
        <a href="index.php?p=tenants" class="nav-link <?= $p=='tenants'?'active':'' ?>"><i class="fa-solid fa-users"></i> <span>المستأجرين</span></a>
        <a href="index.php?p=alerts" class="nav-link <?= $p=='alerts'?'active':'' ?>"><i class="fa-solid fa-bell"></i> <span>التنبيهات</span></a>
        <a href="index.php?p=maintenance" class="nav-link <?= $p=='maintenance'?'active':'' ?>"><i class="fa-solid fa-screwdriver-wrench"></i> <span>الصيانة</span></a>
        <a href="index.php?p=vendors" class="nav-link <?= $p=='vendors'?'active':'' ?>"><i class="fa-solid fa-helmet-safety"></i> <span>المقاولين</span></a>
        <?php if($role === 'admin'): ?>
        <a href="index.php?p=users" class="nav-link <?= $p=='users'?'active':'' ?>"><i class="fa-solid fa-user-shield"></i> <span>المستخدمين</span></a>
        <?php endif; ?>
        <a href="index.php?p=settings" class="nav-link <?= $p=='settings'?'active':'' ?>"><i class="fa-solid fa-gear"></i> <span>الإعدادات</span></a>
    </div>
    <a href="logout.php" class="nav-link" style="color:#ef4444; margin-top:10px"><i class="fa-solid fa-power-off"></i> <span>خروج</span></a>
</div>

<div class="main">
    <div class="header">
        <div>
            <h1 style="margin:0; font-size:26px; font-weight:800">أهلاً، <?= $user_name ?> 👋</h1>
            <div style="color:var(--muted); font-size:14px; margin-top:5px">إدارة الأملاك الذكية</div>
        </div>
        <div class="header-actions">
            <button class="btn btn-dark btn-icon" id="sidebarToggle" type="button" title="طي/إظهار القائمة">
                <i class="fa-solid fa-bars"></i>
            </button>
            <button class="btn btn-dark" id="themeToggle" type="button">
                الوضع: داكن
            </button>
            <button class="btn btn-dark btn-small">
                <i class="fa-regular fa-calendar"></i> <?= date('Y-m-d') ?>
            </button>
        </div>
    </div>

    <div class="smart-toolbar">
        <div class="smart-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input id="globalSearch" type="search" placeholder="بحث ذكي داخل الصفحة والجداول..." autocomplete="off">
            <span class="smart-search-hint">Ctrl + /</span>
        </div>
        <div class="smart-meta">
            <span>الصفحة الحالية:</span>
            <span class="page-pill"><?= $page_title ?></span>
            <span id="searchCount">كل النتائج ظاهرة</span>
        </div>
    </div>
