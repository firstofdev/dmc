<?php
// index.php
require 'config.php';
require 'SmartSystem.php'; // تأكد من وجود هذا الملف

if(!isset($_SESSION['uid'])) { header("Location: login.php"); exit; }

// قائمة الصفحات المسموح بها (تمت إضافة users)
$p = $_GET['p'] ?? 'dashboard';
$allowed = [
    'dashboard', 'properties', 'units', 'tenants', 'tenant_view',
    'contracts', 'contract_view', 'payments', 'maintenance', 'vendors', 'alerts', 
    'settings', 'users', 'smart_center', 'reports', 'lease_calendar', 'help'
];

include 'includes/header.php';

if(in_array($p, $allowed) && file_exists("pages/$p.php")) {
    include "pages/$p.php";
} else {
    echo "<div style='text-align:center; padding:50px; color:#fff'>
            <h1>404</h1>
            <p>عذراً، الصفحة غير موجودة: pages/$p.php</p>
          </div>";
}

include 'includes/footer.php';
?>
