<?php
// pages/tenant_view.php

// 1. التحقق من وجود المعرف
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<script>window.location='index.php?p=tenants';</script>";
    exit;
}

$id = $_GET['id'];

// 2. جلب بيانات المستأجر
$stmt = $pdo->prepare("SELECT * FROM tenants WHERE id = ?");
$stmt->execute([$id]);
$t = $stmt->fetch();

if (!$t) {
    echo "<div class='alert alert-danger'>عفواً، المستأجر غير موجود.</div>";
    exit;
}

// 3. جلب الإحصائيات المالية الخاصة بالمستأجر
// إجمالي قيمة العقود
$total_contracts_value = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM contracts WHERE tenant_id = $id")->fetchColumn();

// إجمالي المدفوع (من جدول الدفعات المرتبطة بعقود هذا المستأجر)
$total_paid = $pdo->query("
    SELECT COALESCE(SUM(p.amount), 0) 
    FROM payments p 
    JOIN contracts c ON p.contract_id = c.id 
    WHERE c.tenant_id = $id AND p.status = 'paid'
")->fetchColumn();

// عدد العقود النشطة
$active_contracts = $pdo->query("SELECT COUNT(*) FROM contracts WHERE tenant_id = $id AND status = 'active'")->fetchColumn();

// المتبقي
$remaining = $total_contracts_value - $total_paid;
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px">
    <a href="index.php?p=tenants" class="btn btn-dark">
        <i class="fa-solid fa-arrow-right"></i> العودة للقائمة
    </a>
    <button onclick="window.print()" class="btn btn-dark">
        <i class="fa-solid fa-print"></i> طباعة الملف
    </button>
</div>

<div class="card" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border: none; padding: 30px; margin-bottom: 25px;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:20px;">
        <div style="display:flex; align-items:center; gap:20px;">
            <div style="width:80px; height:80px; background:rgba(255,255,255,0.2); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:35px; color:white;">
                <i class="fa-solid fa-user"></i>
            </div>
            <div>
                <h2 style="margin:0; font-size:24px; font-weight:800"><?= htmlspecialchars($t['name']) ?></h2>
                <div style="margin-top:8px; opacity:0.9; font-size:14px;">
                    <i class="fa-solid fa-id-card"></i> الهوية: <?= $t['id_number'] ?: 'غير مسجل' ?>
                    <span style="margin:0 10px">|</span>
                    <i class="fa-solid fa-calendar-check"></i> تاريخ التسجيل: <?= date('Y-m-d', strtotime($t['created_at'])) ?>
                </div>
            </div>
        </div>
        
        <div style="display:flex; gap:10px;">
            <a href="tel:<?= $t['phone'] ?>" class="btn" style="background:rgba(255,255,255,0.2); color:white; border:1px solid rgba(255,255,255,0.3)">
                <i class="fa-solid fa-phone"></i> اتصال
            </a>
            <a href="https://wa.me/966<?= ltrim($t['phone'], '0') ?>" target="_blank" class="btn" style="background:rgba(255,255,255,0.2); color:white; border:1px solid rgba(255,255,255,0.3)">
                <i class="fa-brands fa-whatsapp"></i> واتساب
            </a>
        </div>
    </div>
</div>

<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:20px; margin-bottom:25px;">
    
    <div class="card" style="text-align:center; padding:25px;">
        <div style="width:50px; height:50px; background:#e0e7ff; color:#4f46e5; border-radius:12px; display:flex; align-items:center; justify-content:center; margin:0 auto 15px; font-size:20px;">
            <i class="fa-solid fa-file-contract"></i>
        </div>
        <h3 style="margin:0; font-size:24px; font-weight:bold"><?= $active_contracts ?></h3>
        <span style="color:#888; font-size:13px">عقود نشطة</span>
    </div>

    <div class="card" style="text-align:center; padding:25px;">
        <div style="width:50px; height:50px; background:#dcfce7; color:#10b981; border-radius:12px; display:flex; align-items:center; justify-content:center; margin:0 auto 15px; font-size:20px;">
            <i class="fa-solid fa-coins"></i>
        </div>
        <h3 style="margin:0; font-size:24px; font-weight:bold"><?= number_format($total_contracts_value) ?></h3>
        <span style="color:#888; font-size:13px">إجمالي قيمة العقود</span>
    </div>

    <div class="card" style="text-align:center; padding:25px;">
        <div style="width:50px; height:50px; background:#ecfccb; color:#84cc16; border-radius:12px; display:flex; align-items:center; justify-content:center; margin:0 auto 15px; font-size:20px;">
            <i class="fa-solid fa-check-double"></i>
        </div>
        <h3 style="margin:0; font-size:24px; font-weight:bold"><?= number_format($total_paid) ?></h3>
        <span style="color:#888; font-size:13px">إجمالي المدفوع</span>
    </div>

    <div class="card" style="text-align:center; padding:25px;">
        <div style="width:50px; height:50px; background:#fee2e2; color:#ef4444; border-radius:12px; display:flex; align-items:center; justify-content:center; margin:0 auto 15px; font-size:20px;">
            <i class="fa-solid fa-hand-holding-dollar"></i>
        </div>
        <h3 style="margin:0; font-size:24px; font-weight:bold"><?= number_format($remaining) ?></h3>
        <span style="color:#888; font-size:13px">المبالغ المتبقية</span>
    </div>
</div>

<div style="display:grid; grid-template-columns: 1fr 2fr; gap:25px;">
    
    <div class="card" style="height:fit-content;">
        <h4 style="margin-top:0; border-bottom:1px solid #333; padding-bottom:15px; color:#6366f1;">
            <i class="fa-solid fa-circle-info"></i> البيانات الشخصية
        </h4>
        
        <div style="margin-bottom:15px;">
            <label style="color:#888; font-size:12px; display:block">الاسم الكامل</label>
            <div style="font-weight:bold"><?= htmlspecialchars($t['name']) ?></div>
        </div>
        <div style="margin-bottom:15px;">
            <label style="color:#888; font-size:12px; display:block">رقم الجوال</label>
            <div style="font-weight:bold; font-family:monospace"><?= htmlspecialchars($t['phone']) ?></div>
        </div>
        <div style="margin-bottom:15px;">
            <label style="color:#888; font-size:12px; display:block">البريد الإلكتروني</label>
            <div style="font-weight:bold"><?= $t['email'] ?: '-' ?></div>
        </div>
        <div style="margin-bottom:15px;">
            <label style="color:#888; font-size:12px; display:block">رقم الهوية / السجل</label>
            <div style="font-weight:bold; font-family:monospace"><?= $t['id_number'] ?: '-' ?></div>
        </div>
    </div>

    <div class="card">
        <h4 style="margin-top:0; border-bottom:1px solid #333; padding-bottom:15px; color:#6366f1;">
            <i class="fa-solid fa-folder-open"></i> سجل العقود
        </h4>
        
        <?php
        // جلب عقود هذا المستأجر
        $tenant_contracts = $pdo->query("
            SELECT c.*, u.unit_name, u.type 
            FROM contracts c 
            JOIN units u ON c.unit_id = u.id 
            WHERE c.tenant_id = $id 
            ORDER BY c.id DESC
        ");
        
        if ($tenant_contracts->rowCount() == 0):
        ?>
            <div style="text-align:center; padding:30px; color:#666; border:1px dashed #444; border-radius:10px;">
                لا توجد عقود مسجلة لهذا المستأجر.
                <br><br>
                <a href="index.php?p=contracts" class="btn btn-primary btn-sm">إنشاء عقد جديد</a>
            </div>
        <?php else: ?>
            <table style="width:100%; border-collapse:collapse; font-size:14px;">
                <thead>
                    <tr style="background:#222; text-align:right;">
                        <th style="padding:10px; border-radius:0 5px 5px 0">رقم العقد</th>
                        <th style="padding:10px">الوحدة</th>
                        <th style="padding:10px">الفترة</th>
                        <th style="padding:10px">المبلغ</th>
                        <th style="padding:10px; border-radius:5px 0 0 5px">إجراء</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($c = $tenant_contracts->fetch()): ?>
                    <tr style="border-bottom:1px solid #333;">
                        <td style="padding:10px">#<?= $c['id'] ?></td>
                        <td style="padding:10px">
                            <?= $c['unit_name'] ?> 
                            <span style="font-size:10px; background:#333; padding:2px 5px; border-radius:4px"><?= $c['type'] ?></span>
                        </td>
                        <td style="padding:10px; font-size:12px; color:#aaa">
                            <?= $c['start_date'] ?> <br> إلى <?= $c['end_date'] ?>
                        </td>
                        <td style="padding:10px; font-weight:bold; color:#10b981">
                            <?= number_format($c['total_amount']) ?>
                        </td>
                        <td style="padding:10px">
                            <a href="index.php?p=contract_view&id=<?= $c['id'] ?>" class="btn btn-dark btn-sm" title="عرض العقد">
                                <i class="fa-solid fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
