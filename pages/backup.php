<?php
// backup.php - سكربت النسخ الاحتياطي الآمن
require 'config.php';

if(!isset($_SESSION['uid']) || $_SESSION['role'] !== 'admin') {
    die("عفواً، هذه الميزة للمدراء فقط.");
}

// إعدادات الباك أب
$filename = 'backup_' . date('Y-m-d_H-i') . '.sql';
$sqlScript = generate_backup_sql($pdo);

// تحميل الملف
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename=' . $filename);
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . strlen($sqlScript));
echo $sqlScript;
exit;
?>
