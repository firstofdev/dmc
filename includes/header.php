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
    $stmt = $pdo->prepare("SELECT k, v FROM settings WHERE k IN ('logo','company_name','timezone','date_format','maintenance_mode','maintenance_message','theme')");
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
$current_theme = $settingsMap['theme'] ?? 'dark';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title><?= $company_name_safe ?> - النظام المتكامل</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    <?php
    // استخدام FontAwesome المحلي إذا كان متوفراً، وإلا استخدم CDN
    $localFA = __DIR__ . '/../resources/fontawesome/css/all.min.css';
    $fallbackFA = __DIR__ . '/../resources/fontawesome/css/fallback.css';
    $localFAPath = 'resources/fontawesome/css/all.min.css';
    $fallbackFAPath = 'resources/fontawesome/css/fallback.css';
    
    if (file_exists($localFA)): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($localFAPath, ENT_QUOTES, 'UTF-8') ?>">
    <?php else: ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css" crossorigin="anonymous">
    <?php endif;
    
    // Always load fallback CSS as a safety net
    if (file_exists($fallbackFA)): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($fallbackFAPath, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* ENHANCED MODERN DARK THEME */
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
            --sidebar-bg:rgba(8, 8, 14, 0.95);
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
        
        /* LIGHT THEME */
        body.light-theme {
            --bg:#f8fafc;
            --card:#ffffff;
            --border:#e2e8f0;
            --text:#0f172a;
            --muted:#64748b;
            --sidebar-bg:#ffffff;
            --sidebar-shadow:8px 0 40px rgba(0,0,0,0.08);
            --logo-bg:radial-gradient(circle at center, rgba(99,102,241,0.1), rgba(255,255,255,0.9));
            --nav-hover-bg:rgba(99,102,241,0.08);
            --nav-hover-text:#0f172a;
            --main-bg:linear-gradient(135deg, rgba(99,102,241,0.03), rgba(168,85,247,0.02));
            --table-th:#475569;
            --table-td-bg:#ffffff;
            --table-td-border:#e2e8f0;
            --btn-dark-bg:#f1f5f9;
            --btn-dark-border:#cbd5e1;
            --modal-overlay:rgba(0,0,0,0.4);
            --modal-bg:#ffffff;
            --modal-border:#e2e8f0;
            --input-bg:#f8fafc;
            --input-border:#cbd5e1;
            --scrollbar:rgba(99,102,241,0.3);
            --close-bg:rgba(239,68,68,0.1);
            --tag-bg:rgba(99,102,241,0.1);
            --glow:0 0 20px rgba(99,102,241,0.15);
            --glow-strong:0 0 30px rgba(99,102,241,0.25);
        }
        
        body.light-theme::before {
            background:
                radial-gradient(circle at 15% 15%, rgba(99,102,241,0.08), transparent 35%),
                radial-gradient(circle at 85% 20%, rgba(168,85,247,0.08), transparent 35%),
                radial-gradient(circle at 50% 80%, rgba(34,211,238,0.05), transparent 40%);
        }
        
        body.light-theme .sidebar::before {
            background:var(--sidebar-bg);
            border:1px solid var(--border);
        }
        
        body.light-theme .nav-link i {
            color:var(--muted);
        }
        
        body.light-theme .nav-link:hover i,
        body.light-theme .nav-link.active i {
            color:var(--primary);
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

        /* Revolutionary Floating Sidebar Design */
        .sidebar { 
            width:300px; 
            background:transparent; 
            border:none; 
            display:flex; 
            flex-direction:column; 
            padding:40px 25px; 
            z-index:20; 
            position:relative;
            overflow:visible;
        }
        .sidebar::before {
            content:'';
            position:absolute;
            top:20px;
            right:15px;
            bottom:20px;
            left:15px;
            background:var(--sidebar-bg); 
            border-radius:30px;
            border:1px solid var(--border); 
            box-shadow:var(--sidebar-shadow); 
            backdrop-filter: blur(18px);
            z-index:-1;
        }
        .sidebar::after {
            content:'';
            position:absolute;
            inset:0;
            background:linear-gradient(180deg, rgba(99,102,241,0.05) 0%, transparent 50%, rgba(168,85,247,0.05) 100%);
            opacity:0.6;
            animation:pulseGlow 8s ease-in-out infinite;
            pointer-events:none;
            border-radius:30px;
        }
        @keyframes pulseGlow {
            0%, 100% { opacity:0.4; }
            50% { opacity:0.7; }
        }
        .logo-wrapper { 
            width: 140px; 
            height: 140px; 
            margin: 0 auto 20px; 
            border-radius: 50%; 
            background: var(--logo-bg); 
            border: 3px solid var(--border); 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            overflow: hidden; 
            padding: 15px; 
            transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1); 
            animation:floatLogo 6s ease-in-out infinite; 
            position:relative;
            cursor:pointer;
            box-shadow: 0 10px 40px rgba(0,0,0,0.4), inset 0 2px 10px rgba(255,255,255,0.1);
        }
        .logo-wrapper::before { 
            content:''; 
            position:absolute; 
            inset:-3px; 
            border-radius:50%; 
            background:linear-gradient(135deg, var(--primary), var(--accent), var(--accent-2)); 
            opacity:0; 
            transition:opacity 0.5s ease; 
            z-index:0; 
            animation:rotateBorder 8s linear infinite; 
        }
        .logo-wrapper:hover::before { opacity:1; }
        .logo-wrapper:hover { 
            border-color: var(--primary); 
            box-shadow: var(--glow-strong), 0 0 80px rgba(99,102,241,0.5), inset 0 0 30px rgba(99,102,241,0.15); 
            transform: scale(1.15) rotate(-8deg); 
        }
        .logo-img { 
            width: 100%; 
            height: 100%; 
            object-fit: contain; 
            image-rendering: -webkit-optimize-contrast; 
            position:relative; 
            z-index:1;
            filter: drop-shadow(0 4px 16px rgba(99,102,241,0.4));
            transition: all 0.5s ease;
        }
        .logo-wrapper:hover .logo-img {
            transform: scale(1.08);
            filter: drop-shadow(0 8px 24px rgba(99,102,241,0.7)) brightness(1.15);
        }
        /* Revolutionary Navigation Links - No Boxes, Pure Icons */
        .nav-link { 
            display:flex; 
            align-items:center; 
            gap:18px; 
            padding:16px 20px; 
            margin-bottom:10px; 
            border-radius:18px; 
            color:var(--muted); 
            text-decoration:none; 
            font-weight:500; 
            transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1); 
            position:relative; 
            overflow:visible;
            opacity:0;
            animation: slideInRight 0.6s ease forwards;
            background:transparent;
        }
        @keyframes slideInRight {
            from {
                opacity:0;
                transform:translateX(30px);
            }
            to {
                opacity:1;
                transform:translateX(0);
            }
        }
        .nav-link:nth-child(1) { animation-delay: 0.05s; }
        .nav-link:nth-child(2) { animation-delay: 0.1s; }
        .nav-link:nth-child(3) { animation-delay: 0.15s; }
        .nav-link:nth-child(4) { animation-delay: 0.2s; }
        .nav-link:nth-child(5) { animation-delay: 0.25s; }
        .nav-link:nth-child(6) { animation-delay: 0.3s; }
        .nav-link:nth-child(7) { animation-delay: 0.35s; }
        .nav-link:nth-child(8) { animation-delay: 0.4s; }
        .nav-link:nth-child(9) { animation-delay: 0.45s; }
        .nav-link:nth-child(10) { animation-delay: 0.5s; }
        .nav-link:nth-child(11) { animation-delay: 0.55s; }
        .nav-link:nth-child(12) { animation-delay: 0.6s; }
        .nav-link:nth-child(13) { animation-delay: 0.65s; }
        .nav-link::before {
            content:'';
            position:absolute;
            inset:-2px;
            background:linear-gradient(135deg, rgba(99,102,241,0.25), rgba(168,85,247,0.15)); 
            opacity:0; 
            transition:all 0.4s ease; 
            border-radius:20px;
            z-index:-1;
        }
        .nav-link:hover::before, .nav-link.active::before { 
            opacity:1; 
        }
        .nav-link::after {
            content:'';
            position:absolute;
            left:0;
            top:50%;
            transform:translateY(-50%);
            width:4px;
            height:0;
            background:linear-gradient(180deg, var(--primary), var(--accent));
            border-radius:0 4px 4px 0;
            transition:height 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .nav-link:hover::after, .nav-link.active::after {
            height:60%;
        }
        .nav-link:hover, .nav-link.active { 
            color:var(--nav-hover-text); 
            transform:translateX(-8px); 
            background:rgba(99,102,241,0.08);
            box-shadow: 0 8px 30px rgba(99,102,241,0.25), inset 0 1px 0 rgba(255,255,255,0.1);
        }
        /* Pure Icons - NO Background Boxes! */
        .nav-link i { 
            font-size:20px; 
            color:var(--muted); 
            position:relative; 
            z-index:1; 
            transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
            filter: drop-shadow(0 2px 8px rgba(0,0,0,0.3));
            width:24px;
            text-align:center;
        }
        .nav-link:hover i, .nav-link.active i { 
            color:var(--primary); 
            transform:scale(1.3) rotate(-8deg); 
            filter: drop-shadow(0 4px 16px rgba(99,102,241,0.6)) drop-shadow(0 0 20px rgba(99,102,241,0.8));
        }
        .nav-link:active i {
            transform:scale(1.2) rotate(-4deg);
        }
        .nav-link span { 
            position:relative; 
            z-index:1;
            letter-spacing:0.4px;
            font-size:15px;
            line-height:1.4;
            font-weight:600;
        }

        /* Main Content */
        .main { flex:1; padding:40px; overflow-y:auto; background:var(--main-bg); position:relative; }
        .header { display:flex; justify-content:space-between; align-items:center; margin-bottom:40px; padding-bottom:20px; border-bottom:1px solid var(--border); }
        .header-actions { display:flex; gap:12px; align-items:center; }
        /* Enhanced Smart Toolbar with Modern Effects */
        .smart-toolbar { 
            display:flex; 
            flex-wrap:wrap; 
            align-items:center; 
            gap:18px; 
            justify-content:space-between; 
            background:var(--card); 
            border:1px solid var(--border); 
            padding:16px 20px; 
            border-radius:20px; 
            margin-bottom:30px; 
            box-shadow:0 12px 35px rgba(15,23,42,0.25); 
            backdrop-filter: blur(16px); 
            position:relative;
            transition: all 0.4s ease;
        }
        .smart-toolbar::before { 
            content:''; 
            position:absolute; 
            inset:0; 
            border-radius:20px; 
            background:linear-gradient(135deg, rgba(99,102,241,0.05), rgba(168,85,247,0.05)); 
            opacity:0.5;
        }
        .smart-toolbar:hover {
            box-shadow:0 16px 45px rgba(15,23,42,0.35), 0 0 25px rgba(99,102,241,0.1);
            border-color: rgba(99,102,241,0.3);
        }
        .smart-toolbar > * { position:relative; z-index:1; }
        .smart-search { 
            flex:1; 
            display:flex; 
            align-items:center; 
            gap:12px; 
            background:var(--input-bg); 
            border:1px solid var(--input-border); 
            border-radius:16px; 
            padding:12px 16px; 
            min-width:260px; 
            transition:all 0.4s ease; 
        }
        .smart-search:focus-within { 
            border-color:var(--primary); 
            box-shadow:0 0 0 4px rgba(99,102,241,0.2), 0 12px 28px rgba(99,102,241,0.25);
            transform: translateY(-2px);
        }
        .smart-search i { 
            color:var(--muted); 
            font-size:16px;
            transition: all 0.3s ease;
        }
        .smart-search:focus-within i {
            color:var(--primary);
            transform: scale(1.2) rotate(10deg);
        }
        .smart-search input { 
            background:transparent; 
            border:none; 
            color:var(--text); 
            width:100%; 
            font-size:15px; 
            font-family:'Tajawal'; 
        }
        .smart-search-hint { 
            background:var(--tag-bg); 
            color:var(--primary); 
            padding:4px 10px; 
            border-radius:12px; 
            font-size:12px; 
            font-weight:bold; 
            display:flex; 
            align-items:center; 
            gap:6px; 
            animation:pulse 2s ease-in-out infinite;
            box-shadow: 0 4px 12px rgba(99,102,241,0.2);
        }
        @keyframes pulse { 
            0%, 100% { opacity:1; transform: scale(1); } 
            50% { opacity:0.7; transform: scale(0.95); } 
        }
        .search-clear { 
            width:38px; 
            height:38px; 
            border-radius:12px; 
            border:1px solid var(--input-border); 
            background:rgba(0,0,0,0.15); 
            color:var(--muted); 
            display:inline-flex; 
            align-items:center; 
            justify-content:center; 
            cursor:pointer; 
            transition:all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1); 
        }
        .search-clear:hover { 
            border-color:var(--primary); 
            color:var(--primary); 
            box-shadow:var(--glow); 
            transform:rotate(180deg) scale(1.1); 
        }
        .smart-meta { 
            display:flex; 
            align-items:center; 
            gap:10px; 
            color:var(--muted); 
            font-size:14px; 
        }
        .page-pill { 
            background:var(--tag-bg); 
            color:var(--primary); 
            padding:6px 12px; 
            border-radius:14px; 
            font-weight:bold; 
            display:inline-flex; 
            align-items:center; 
            gap:8px; 
            border:1px solid rgba(99,102,241,0.2); 
            box-shadow:0 4px 12px rgba(99,102,241,0.15); 
            position:relative; 
            overflow:hidden;
            transition: all 0.3s ease;
        }
        .page-pill:hover {
            transform: scale(1.05);
            box-shadow:0 6px 16px rgba(99,102,241,0.25);
        }
        .page-pill::before { 
            content:''; 
            position:absolute; 
            inset:0; 
            background:linear-gradient(90deg, transparent, rgba(255,255,255,0.15), transparent); 
            background-size:200% 100%; 
            transform:translateX(-100%); 
            animation:shimmer 3s infinite; 
        }
        .page-pill i { 
            animation:float 2s ease-in-out infinite; 
        }
        @keyframes float { 
            0%, 100% { transform:translateY(0); } 
            50% { transform:translateY(-4px); } 
        }
        @keyframes shimmer { 
            0% { background-position: -200% center; } 
            100% { background-position: 200% center; } 
        }
        /* Enhanced Smart Assist with Modern Animations */
        .smart-assist { 
            display:flex; 
            align-items:center; 
            gap:14px; 
            background:var(--card); 
            border:1px solid var(--border); 
            padding:14px 18px; 
            border-radius:18px; 
            margin-bottom:24px; 
            box-shadow:0 12px 35px rgba(15,23,42,0.18); 
            position:relative; 
            overflow:hidden;
            transition: all 0.4s ease;
        }
        .smart-assist::before { 
            content:''; 
            position:absolute; 
            inset:0; 
            background:linear-gradient(135deg, rgba(99,102,241,0.08), rgba(168,85,247,0.05)); 
        }
        .smart-assist:hover {
            box-shadow:0 16px 45px rgba(15,23,42,0.28), 0 0 30px rgba(99,102,241,0.15);
            border-color: rgba(99,102,241,0.3);
            transform: translateY(-2px);
        }
        .smart-assist > * { position:relative; z-index:1; }
        .assist-icon { 
            width:46px; 
            height:46px; 
            border-radius:14px; 
            display:grid; 
            place-items:center; 
            background:var(--tag-bg); 
            color:var(--primary); 
            box-shadow:var(--glow); 
            position:relative;
            transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .assist-icon::before { 
            content:''; 
            position:absolute; 
            inset:-2px; 
            border-radius:14px; 
            background:linear-gradient(135deg, var(--primary), var(--accent)); 
            opacity:0; 
            transition:opacity 0.4s ease; 
            z-index:-1; 
        }
        .smart-assist:hover .assist-icon {
            transform: scale(1.15) rotate(-10deg);
            box-shadow:0 12px 30px rgba(99,102,241,0.4);
        }
        .smart-assist:hover .assist-icon::before { 
            opacity:0.6; 
        }
        .assist-icon i { 
            animation:wiggle 2s ease-in-out infinite;
            transition: transform 0.3s ease;
        }
        .smart-assist:hover .assist-icon i {
            animation: none;
            transform: scale(1.2);
        }
        @keyframes wiggle { 
            0%, 100% { transform:rotate(0deg); } 
            25% { transform:rotate(-8deg); } 
            75% { transform:rotate(8deg); } 
        }
        .assist-title { 
            font-size:15px; 
            font-weight:800; 
            color:var(--text);
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        .assist-body { 
            color:var(--muted); 
            font-size:14px; 
        }
        .smart-assist.pulse { 
            box-shadow:0 18px 45px rgba(99,102,241,0.25);
            animation: softPulse 1s ease-out;
        }
        @keyframes softPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.02); }
        }
        .maintenance-banner { background:linear-gradient(135deg, rgba(239,68,68,0.18), rgba(248,113,113,0.12)); border:1px solid rgba(239,68,68,0.45); color:#fecaca; padding:14px 18px; border-radius:16px; display:flex; align-items:center; gap:12px; margin-bottom:24px; box-shadow:0 12px 24px rgba(15,23,42,0.25); }
        .maintenance-banner i { color:#f87171; }

        .btn-icon { width:44px; height:44px; border-radius:14px; display:inline-flex; align-items:center; justify-content:center; }
        .btn-small { padding:10px 14px; font-size:13px; border-radius:12px; }
        
        /* Sidebar Divider */
        .nav-divider { 
            height:1px; 
            background:linear-gradient(90deg, transparent, var(--border) 20%, var(--border) 80%, transparent); 
            margin:20px 0; 
            opacity:0.6;
            transition: all 0.3s ease;
            position:relative;
        }
        .nav-divider::before {
            content:'';
            position:absolute;
            left:50%;
            top:50%;
            transform:translate(-50%, -50%);
            width:6px;
            height:6px;
            background:var(--primary);
            border-radius:50%;
            box-shadow:0 0 10px rgba(99,102,241,0.6);
        }

        body.sidebar-collapsed .sidebar { width:90px; padding:20px 12px; }
        body.sidebar-collapsed .sidebar::before { right:10px; left:10px; }
        body.sidebar-collapsed .sidebar .nav-link { justify-content:center; gap:0; padding:14px; }
        body.sidebar-collapsed .sidebar .nav-link span { display:none; }
        body.sidebar-collapsed .sidebar .nav-link i { font-size:22px; }
        body.sidebar-collapsed .sidebar .logo-wrapper { width:70px; height:70px; margin-bottom:16px; padding:10px; }
        body.sidebar-collapsed .sidebar h4, body.sidebar-collapsed .sidebar .tagline { display:none; }
        body.sidebar-collapsed .sidebar .nav-divider { margin:12px auto; width:40px; }
        body.sidebar-collapsed .main { padding:35px; }
        
        /* Enhanced Cards & Tables with Advanced Effects */
        .card { 
            background:var(--card); 
            border:1px solid var(--border); 
            border-radius:24px; 
            padding:30px; 
            margin-bottom:30px; 
            position:relative; 
            box-shadow:0 20px 45px rgba(2,6,23,0.2); 
            transition:all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1); 
            backdrop-filter:blur(20px);
            overflow:hidden;
        }
        .card::before { 
            content:''; 
            position:absolute; 
            inset:0; 
            border-radius:24px; 
            background:radial-gradient(circle at top right, rgba(99,102,241,0.1), transparent 50%), radial-gradient(circle at bottom left, rgba(168,85,247,0.08), transparent 50%); 
            opacity:0; 
            transition:opacity 0.5s ease; 
            pointer-events:none; 
        }
        .card::after { 
            content:''; 
            position:absolute; 
            inset:0; 
            border-radius:24px; 
            background:linear-gradient(135deg, rgba(99,102,241,0.05), rgba(168,85,247,0.05)); 
            opacity:0; 
            transition:opacity 0.5s ease; 
            pointer-events:none; 
        }
        .card:hover { 
            transform:translateY(-10px) scale(1.015); 
            box-shadow:0 35px 80px rgba(2,6,23,0.45), 0 0 60px rgba(99,102,241,0.2), inset 0 1px 0 rgba(255,255,255,0.05); 
            border-color:rgba(99,102,241,0.4); 
        }
        .card:hover::before { opacity:1; }
        .card:hover::after { opacity:1; }
        .card h2, .card h3 { 
            position:relative; 
            z-index:1;
            text-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }
        .card table, .card form, .card > div { position:relative; z-index:1; }
        table { width:100%; border-collapse:separate; border-spacing:0 8px; }
        th { text-align:right; color:var(--table-th); font-size:13px; padding:10px 20px; font-weight:600; letter-spacing:0.5px; }
        td { 
            background:var(--table-td-bg); 
            padding:20px; 
            border:1px solid var(--table-td-border); 
            border-left:none; 
            border-right:none; 
            transition:all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1); 
        }
        td:first-child { border-radius:0 15px 15px 0; border-right:1px solid var(--table-td-border); }
        td:last-child { border-radius:15px 0 0 15px; border-left:1px solid var(--table-td-border); }
        tbody tr { transition: all 0.3s ease; }
        tbody tr:hover td { 
            background:rgba(99,102,241,0.12); 
            border-color:rgba(99,102,241,0.3); 
            transform:translateX(-3px);
            box-shadow: 0 8px 20px rgba(99,102,241,0.15);
        }

        /* Enhanced Buttons & Badges with Advanced Animations */
        .btn { 
            padding:15px 24px; 
            border:none; 
            border-radius:14px; 
            font-weight:bold; 
            cursor:pointer; 
            font-size:14px; 
            transition:all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1); 
            display:inline-flex; 
            align-items:center; 
            gap:10px; 
            color:white; 
            position:relative; 
            overflow:hidden; 
            text-decoration:none;
            letter-spacing:0.3px;
        }
        .btn::before { 
            content:''; 
            position:absolute; 
            inset:0; 
            background:linear-gradient(120deg, rgba(255,255,255,0), rgba(255,255,255,0.3), rgba(255,255,255,0)); 
            transform:translateX(-100%); 
            transition:transform 0.6s ease; 
        }
        .btn::after { 
            content:''; 
            position:absolute; 
            inset:-2px; 
            border-radius:14px; 
            background:linear-gradient(45deg, transparent, rgba(255,255,255,0.15), transparent); 
            opacity:0; 
            transition:opacity 0.3s ease; 
            pointer-events:none; 
        }
        .btn:hover::before { transform:translateX(100%); }
        .btn:hover::after { opacity:1; }
        .btn:hover { 
            transform:translateY(-4px) scale(1.03); 
            box-shadow:0 18px 40px rgba(0,0,0,0.35); 
            filter:brightness(1.15); 
        }
        .btn:active { 
            transform:translateY(-2px) scale(0.98); 
            box-shadow:0 8px 20px rgba(0,0,0,0.3); 
        }
        .btn i { 
            position:relative; 
            z-index:1;
            transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .btn:hover i {
            transform: scale(1.2) rotate(10deg);
        }
        .btn span { 
            position:relative; 
            z-index:1; 
        }
        .btn-primary { 
            background:linear-gradient(135deg, var(--primary), var(--accent)); 
            box-shadow:0 10px 25px rgba(99,102,241,0.35), inset 0 1px 0 rgba(255,255,255,0.2); 
        }
        .btn-primary:hover { 
            box-shadow:0 18px 40px rgba(99,102,241,0.55), 0 0 30px rgba(99,102,241,0.4), inset 0 1px 0 rgba(255,255,255,0.3); 
        }
        .btn-danger { 
            background:linear-gradient(135deg, #ef4444, #b91c1c); 
            box-shadow:0 10px 25px rgba(239,68,68,0.3), inset 0 1px 0 rgba(255,255,255,0.15); 
        }
        .btn-danger:hover { 
            box-shadow:0 18px 40px rgba(239,68,68,0.5), 0 0 25px rgba(239,68,68,0.3); 
        }
        .btn-dark { 
            background:var(--btn-dark-bg); 
            border:1px solid var(--btn-dark-border); 
            color:white; 
        }
        .btn-dark:hover { 
            border-color:var(--primary); 
            background:rgba(99,102,241,0.15); 
            box-shadow: 0 8px 24px rgba(99,102,241,0.2);
        }
        .btn-sm { padding:8px 16px; font-size:12px; }
        .badge { 
            padding:5px 10px; 
            border-radius:8px; 
            font-size:12px; 
            font-weight:bold; 
            display:inline-flex; 
            align-items:center; 
            gap:6px;
            transition: all 0.3s ease;
        }
        .badge:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
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
        
        /* Advanced Keyframe Animations */
        @keyframes floatLogo { 
            0%, 100% { transform:translateY(0); } 
            50% { transform:translateY(-8px); } 
        }
        @keyframes rotateBorder { 
            0% { transform:rotate(0deg); } 
            100% { transform:rotate(360deg); } 
        }
        @keyframes shimmer { 
            0% { background-position: -200% center; } 
            100% { background-position: 200% center; } 
        }
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        @keyframes scaleIn {
            from {
                opacity: 0;
                transform: scale(0.9);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        /* Page Load Animations */
        .main { animation: fadeInUp 0.6s ease; }
        .header { animation: fadeInUp 0.7s ease 0.1s both; }
        .smart-toolbar { animation: fadeInUp 0.8s ease 0.2s both; }
        .smart-assist { animation: fadeInUp 0.9s ease 0.3s both; }
        .card { animation: fadeInUp 1s ease 0.4s both; }
        .card:nth-child(2) { animation-delay: 0.5s; }
        .card:nth-child(3) { animation-delay: 0.6s; }
        .card:nth-child(4) { animation-delay: 0.7s; }
        
        /* Icon Bounce Effect on Hover */
        .btn:hover i, .nav-link:hover i {
            animation: iconBounce 0.6s ease;
        }
        @keyframes iconBounce {
            0%, 100% { transform: scale(1) rotate(0deg); }
            25% { transform: scale(1.2) rotate(-10deg); }
            50% { transform: scale(1.15) rotate(5deg); }
            75% { transform: scale(1.25) rotate(-5deg); }
        }
    </style>
</head>
<body data-page="<?= htmlspecialchars($page_key) ?>" class="<?= $current_theme === 'light' ? 'light-theme' : '' ?>">

    <div class="sidebar">
    <div style="text-align:center; margin-bottom:25px">
        <div class="logo-wrapper"><img src="<?= $logo_src ?>" class="logo-img" alt="Logo"></div>
        <h4 style="margin:12px 0 6px; font-weight:800; font-size:16px"><?= $company_name_safe ?></h4>
        <span class="tagline" style="font-size:11px; color:var(--primary); background:var(--tag-bg); padding:5px 12px; border-radius:20px; display:inline-block">نظام الإدارة</span>
    </div>
    <div style="flex:1; overflow-y:auto; padding:0 2px">
        <a href="index.php?p=dashboard" class="nav-link <?= $p=='dashboard'?'active':'' ?>"><i class="fa-solid fa-gauge-high"></i> <span>لوحة القيادة</span></a>
        <a href="index.php?p=properties" class="nav-link <?= $p=='properties'?'active':'' ?>"><i class="fa-solid fa-building"></i> <span>العقارات</span></a>
        <a href="index.php?p=units" class="nav-link <?= $p=='units'?'active':'' ?>"><i class="fa-solid fa-house-circle-check"></i> <span>الوحدات</span></a>
        <a href="index.php?p=contracts" class="nav-link <?= $p=='contracts'?'active':'' ?>"><i class="fa-solid fa-file-contract"></i> <span>العقود</span></a>
        <a href="index.php?p=tenants" class="nav-link <?= $p=='tenants'?'active':'' ?>"><i class="fa-solid fa-users"></i> <span>المستأجرين</span></a>
        <a href="index.php?p=lease_calendar" class="nav-link <?= $p=='lease_calendar'?'active':'' ?>"><i class="fa-solid fa-calendar-days"></i> <span>تقويم العقود</span></a>
        
        <div class="nav-divider"></div>
        
        <a href="index.php?p=alerts" class="nav-link <?= $p=='alerts'?'active':'' ?>"><i class="fa-solid fa-bell"></i> <span>التنبيهات</span></a>
        <a href="index.php?p=maintenance" class="nav-link <?= $p=='maintenance'?'active':'' ?>"><i class="fa-solid fa-wrench"></i> <span>الصيانة</span></a>
        <a href="index.php?p=vendors" class="nav-link <?= $p=='vendors'?'active':'' ?>"><i class="fa-solid fa-user-tie"></i> <span>المقاولين</span></a>
        
        <div class="nav-divider"></div>
        
        <a href="index.php?p=reports" class="nav-link <?= $p=='reports'?'active':'' ?>"><i class="fa-solid fa-chart-line"></i> <span>التقارير المالية</span></a>
        <a href="index.php?p=smart_center" class="nav-link <?= $p=='smart_center'?'active':'' ?>"><i class="fa-solid fa-brain"></i> <span>التمكين الذكي</span></a>
        

        <a href="index.php?p=help" class="nav-link <?= $p=='help'?'active':'' ?>"><i class="fa-solid fa-book-open"></i> <span>المساعدة والدليل</span></a>

      <?php if($role === 'admin'): ?>
        <div class="nav-divider"></div>
        <a href="index.php?p=users" class="nav-link <?= $p=='users'?'active':'' ?>"><i class="fa-solid fa-user-gear"></i> <span>المستخدمين</span></a>
        <?php endif; ?>
        
        <a href="index.php?p=settings" class="nav-link <?= $p=='settings'?'active':'' ?>"><i class="fa-solid fa-sliders"></i> <span>الإعدادات</span></a>
    </div>
    <div style="padding-top:15px; border-top:1px solid var(--border); margin-top:15px">
        <a href="logout.php" class="nav-link" style="color:#ef4444"><i class="fa-solid fa-power-off"></i> <span>خروج</span></a>
    </div>
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
            <button class="btn btn-dark btn-icon" id="themeToggle" type="button" title="تبديل المظهر">
                <i class="fa-solid fa-<?= $current_theme === 'light' ? 'moon' : 'sun' ?>"></i>
            </button>
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
