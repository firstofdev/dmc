<?php
// backup.php - سكربت النسخ الاحتياطي الآمن
require 'config.php';

if(!isset($_SESSION['uid']) || $_SESSION['role'] !== 'admin') {
    die("عفواً، هذه الميزة للمدراء فقط.");
}

// إعدادات الباك أب
$filename = 'backup_' . date('Y-m-d_H-i') . '.sql';
$tables = [];

// جلب الجداول
$query = $pdo->query('SHOW TABLES');
while($row = $query->fetch(PDO::FETCH_NUM)) {
    $tables[] = $row[0];
}

$sqlScript = "-- DATABASE BACKUP\n-- DATE: " . date('Y-m-d H:i:s') . "\n\n";

foreach($tables as $table) {
    // هيكل الجدول
    $query = $pdo->query('SHOW CREATE TABLE ' . $table);
    $row = $query->fetch(PDO::FETCH_NUM);
    $sqlScript .= "\n\n" . $row[1] . ";\n\n";
    
    // البيانات
    $query = $pdo->query('SELECT * FROM ' . $table);
    $columnCount = $query->columnCount();
    
    while($row = $query->fetch(PDO::FETCH_NUM)) {
        $sqlScript .= "INSERT INTO $table VALUES(";
        for($j = 0; $j < $columnCount; $j++) {
            $row[$j] = addslashes($row[$j]);
            $row[$j] = str_replace("\n","\\n",$row[$j]);
            if (isset($row[$j])) { $sqlScript .= '"' . $row[$j] . '"' ; } else { $sqlScript .= '""'; }
            if ($j < ($columnCount - 1)) { $sqlScript .= ','; }
        }
        $sqlScript .= ");\n";
    }
}

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
