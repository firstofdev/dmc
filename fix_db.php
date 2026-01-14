<?php
// fix_db.php - أداة إصلاح وتحديث قاعدة البيانات
require 'config.php';

echo "<body style='font-family:tahoma; background:#f1f5f9; padding:40px;'>";
echo "<div style='max-width:600px; margin:auto; background:white; padding:30px; border-radius:20px; box-shadow:0 10px 30px rgba(0,0,0,0.1);'>";
echo "<h2>🛠️ جاري تحديث قاعدة البيانات...</h2>";

try {
    // 1. إصلاح جدول المستخدمين (users)
    // إضافة عمود username
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN username VARCHAR(50) UNIQUE AFTER id");
        echo "<p style='color:green'>✅ تم إضافة عمود اسم المستخدم (username).</p>";
    } catch (PDOException $e) { echo "<p style='color:orange'>⚠️ عمود username موجود مسبقاً.</p>"; }

    // إضافة عمود full_name
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN full_name VARCHAR(100) AFTER username");
        // نقل الأسماء القديمة للعمود الجديد
        $pdo->exec("UPDATE users SET full_name = name WHERE full_name IS NULL");
        echo "<p style='color:green'>✅ تم إضافة عمود الاسم الكامل (full_name).</p>";
    } catch (PDOException $e) { echo "<p style='color:orange'>⚠️ عمود full_name موجود مسبقاً.</p>"; }

    // إضافة عمود phone
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(20) AFTER full_name");
        echo "<p style='color:green'>✅ تم إضافة عمود الجوال (phone).</p>";
    } catch (PDOException $e) { echo "<p style='color:orange'>⚠️ عمود phone موجود مسبقاً.</p>"; }

    // 2. تحديث بيانات المستخدمين القدامى
    // أي مستخدم قديم ليس لديه username سنقوم بتوليده من بريده الإلكتروني
    $users = $pdo->query("SELECT id, email FROM users WHERE username IS NULL")->fetchAll();
    foreach ($users as $u) {
        $parts = explode('@', $u['email']);
        $newUser = $parts[0];
        
        // التأكد من عدم تكرار الاسم
        $cnt = 0;
        $finalUser = $newUser;
        while($pdo->query("SELECT count(*) FROM users WHERE username='$finalUser'")->fetchColumn() > 0) {
            $cnt++;
            $finalUser = $newUser . $cnt;
        }
        
        $pdo->prepare("UPDATE users SET username=? WHERE id=?")->execute([$finalUser, $u['id']]);
        echo "<p style='color:blue'>🔄 تم تحديث حساب: {$u['email']} ⬅️ أصبح اسم المستخدم: <b>$finalUser</b></p>";
    }

    // 3. إصلاح جدول الوحدات (units) - إضافة الأنواع والعدادات
    try {
        $pdo->exec("ALTER TABLE units ADD COLUMN type ENUM('shop','apartment','villa','land','office','warehouse') DEFAULT 'apartment'");
        $pdo->exec("ALTER TABLE units ADD COLUMN elec_meter_no VARCHAR(50)");
        $pdo->exec("ALTER TABLE units ADD COLUMN water_meter_no VARCHAR(50)");
        $pdo->exec("ALTER TABLE units ADD COLUMN notes TEXT");
        echo "<p style='color:green'>✅ تم تحديث جدول الوحدات (إضافة الأنواع والعدادات).</p>";
    } catch (PDOException $e) {}

    // 3.1 تحديث جدول المستأجرين (tenants) لإضافة الاسم الكامل وتاريخ الإنشاء
    $tenantHasName = table_has_column($pdo, 'tenants', 'name');
    $tenantHasFullName = table_has_column($pdo, 'tenants', 'full_name');
    if (!$tenantHasFullName) {
        try {
            $pdo->exec("ALTER TABLE tenants ADD COLUMN full_name VARCHAR(255) AFTER id");
            echo "<p style='color:green'>✅ تم إضافة عمود الاسم الكامل للمستأجرين (full_name).</p>";
            $tenantHasFullName = true;
        } catch (PDOException $e) {}
    }
    if ($tenantHasFullName && $tenantHasName) {
        try {
            $pdo->exec("UPDATE tenants SET full_name = name WHERE (full_name IS NULL OR full_name = '')");
            echo "<p style='color:green'>✅ تم ترحيل أسماء المستأجرين إلى full_name.</p>";
        } catch (PDOException $e) {}
    }
    if (!table_has_column($pdo, 'tenants', 'created_at')) {
        try {
            $pdo->exec("ALTER TABLE tenants ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
            echo "<p style='color:green'>✅ تم إضافة تاريخ إنشاء للمستأجرين (created_at).</p>";
        } catch (PDOException $e) {}
    }

    // 4. إصلاح جدول العقود (contracts) - إضافة التوقيع
    try {
        $pdo->exec("ALTER TABLE contracts ADD COLUMN signature_img LONGTEXT");
        echo "<p style='color:green'>✅ تم تحديث جدول العقود (إضافة التوقيع الإلكتروني).</p>";
    } catch (PDOException $e) {}

    // 4.2 إضافة أعمدة الضريبة للعقود
    if (!table_has_column($pdo, 'contracts', 'tax_included')) {
        try {
            $pdo->exec("ALTER TABLE contracts ADD COLUMN tax_included TINYINT(1) DEFAULT 0 AFTER total_amount");
            echo "<p style='color:green'>✅ تم إضافة عمود حالة الضريبة (tax_included) للعقود.</p>";
        } catch (PDOException $e) { echo "<p style='color:orange'>⚠️ تعذر إضافة عمود tax_included (قد يكون موجوداً).</p>"; }
    }
    if (!table_has_column($pdo, 'contracts', 'tax_percent')) {
        try {
            $pdo->exec("ALTER TABLE contracts ADD COLUMN tax_percent DECIMAL(5,2) DEFAULT 0.00 AFTER tax_included");
            echo "<p style='color:green'>✅ تم إضافة نسبة الضريبة (tax_percent) للعقود.</p>";
        } catch (PDOException $e) { echo "<p style='color:orange'>⚠️ تعذر إضافة عمود tax_percent (قد يكون موجوداً).</p>"; }
    }
    if (!table_has_column($pdo, 'contracts', 'tax_amount')) {
        try {
            $pdo->exec("ALTER TABLE contracts ADD COLUMN tax_amount DECIMAL(15,2) DEFAULT 0.00 AFTER tax_percent");
            echo "<p style='color:green'>✅ تم إضافة مبلغ الضريبة (tax_amount) للعقود.</p>";
        } catch (PDOException $e) { echo "<p style='color:orange'>⚠️ تعذر إضافة عمود tax_amount (قد يكون موجوداً).</p>"; }
    }

    // 4.1 إضافة جدول قراءات العدادات
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS meter_readings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            contract_id INT,
            unit_id INT,
            reading_type ENUM('check_in','check_out','periodic') DEFAULT 'periodic',
            elec_reading DECIMAL(12,2) DEFAULT NULL,
            water_reading DECIMAL(12,2) DEFAULT NULL,
            reading_date DATE,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
            FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE
        )");
        echo "<p style='color:green'>✅ تم إضافة جدول قراءات العدادات.</p>";
    } catch (PDOException $e) {}

    // 5. تحديث جدول المدفوعات (payments) لإضافة الحقول الذكية
    try {
        $pdo->exec("ALTER TABLE payments ADD COLUMN uuid VARCHAR(64) NULL AFTER id");
        $pdo->exec("ALTER TABLE payments ADD COLUMN payment_method VARCHAR(30) NULL AFTER amount");
        $pdo->exec("ALTER TABLE payments ADD COLUMN note TEXT AFTER payment_method");
        $pdo->exec("ALTER TABLE payments ADD COLUMN paid_date DATE NULL AFTER due_date");
        echo "<p style='color:green'>✅ تم تحديث جدول المدفوعات (الحقول الذكية).</p>";
    } catch (PDOException $e) {}

    // 6. التأكد من وجود مستخدم Admin
    $pass = password_hash('12345678910', PASSWORD_DEFAULT);

    $adminByUsername = $pdo->query("SELECT id FROM users WHERE username='admin101' LIMIT 1")->fetchColumn();
    if ($adminByUsername) {
        $pdo->exec("UPDATE users SET password='$pass', role='admin' WHERE username='admin101'");
        echo "<p style='color:blue'>ℹ️ تم إعادة تعيين كلمة مرور (admin101) إلى 12345678910.</p>";
    } else {
        $adminByRole = $pdo->query("SELECT id FROM users WHERE role='admin' ORDER BY id ASC LIMIT 1")->fetchColumn();
        if ($adminByRole) {
            $stmt = $pdo->prepare("UPDATE users SET username='admin101', password=?, role='admin' WHERE id=?");
            $stmt->execute([$pass, $adminByRole]);
            echo "<p style='color:blue'>ℹ️ تم إصلاح بيانات المدير وتعيين اسم المستخدم (admin101) مع كلمة المرور 12345678910.</p>";
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES ('admin101', ?, 'المدير العام', 'admin@system.com', 'admin')");
            $stmt->execute([$pass]);
            echo "<p style='color:green'>✅ تم إنشاء حساب المدير (admin101 / 12345678910).</p>";
        }
    }

    echo "<hr><div style='background:#dcfce7; color:#166534; padding:20px; border-radius:10px; text-align:center;'>
            <h1>🎉 تمت الصيانة بنجاح!</h1>
            <p>تم تحديث قاعدة البيانات لتتوافق مع نظام Gemini Quantum.</p>
            <a href='index.php' style='background:#166534; color:white; padding:10px 20px; text-decoration:none; border-radius:5px; font-weight:bold;'>الدخول للنظام الآن</a>
          </div>";

} catch (PDOException $e) {
    echo "<h3 style='color:red'>خطأ: " . $e->getMessage() . "</h3>";
}
echo "</div></body>";
?>
