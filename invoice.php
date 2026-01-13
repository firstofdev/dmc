<?php
require 'config.php';

// التحقق من وجود رقم الفاتورة
if(!isset($_GET['uuid'])) die("خطأ: رقم الفاتورة مفقود.");

// جلب بيانات الفاتورة والعقد والوحدة
$stmt = $pdo->prepare("SELECT p.*, c.id as contract_id, t.full_name, u.unit_name 
                       FROM payments p 
                       JOIN contracts c ON p.contract_id = c.id
                       JOIN tenants t ON c.tenant_id = t.id
                       JOIN units u ON c.unit_id = u.id
                       WHERE p.uuid = ?");
$stmt->execute([$_GET['uuid']]);
$inv = $stmt->fetch();

if(!$inv) die("خطأ: الفاتورة غير موجودة.");
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>سند قبض - <?= secure($inv['uuid']) ?></title>
    <style>
        body { font-family: 'Tahoma', sans-serif; background: #525659; padding: 20px; display: flex; justify-content: center; }
        .invoice-box { background: white; width: 21cm; padding: 40px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.5); }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 30px; }
        .title { font-size: 24px; font-weight: bold; color: #333; }
        .info { text-align: left; line-height: 1.6; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 40px; }
        th { text-align: right; background: #f3f4f6; padding: 12px; border: 1px solid #ddd; width: 30%; }
        td { padding: 12px; border: 1px solid #ddd; }
        .total { font-size: 22px; font-weight: bold; text-align: center; background: #e5e7eb; padding: 15px; border: 1px solid #333; }
        .footer { margin-top: 60px; display: flex; justify-content: space-between; text-align: center; }
        
        @media print {
            body { background: white; padding: 0; }
            .invoice-box { box-shadow: none; width: 100%; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="invoice-box">
        <div class="header">
            <div class="title">
                دار الميار للمقاولات<br>
                <small style="font-size:14px; font-weight:normal">إدارة الأملاك والعقارات</small>
            </div>
            <div class="info">
                <strong>رقم السند:</strong> <?= secure($inv['uuid']) ?><br>
                <strong>التاريخ:</strong> <?= $inv['payment_date'] ?>
            </div>
        </div>

        <table>
            <tr><th>استلمنا من السيد</th><td><?= secure($inv['full_name']) ?></td></tr>
            <tr><th>مبلغ وقدره</th><td><?= number_format($inv['amount'], 2) ?> ريال سعودي</td></tr>
            <tr><th>وذلك عن</th><td>دفع إيجار الوحدة: <?= secure($inv['unit_name']) ?> (عقد رقم #<?= $inv['contract_id'] ?>)</td></tr>
            <tr><th>طريقة الدفع</th><td><?= $inv['payment_method'] == 'cash' ? 'نقدي' : 'تحويل بنكي' ?></td></tr>
            <tr><th>ملاحظات</th><td><?= secure($inv['note']) ?></td></tr>
        </table>

        <div class="total">
            المجموع المستلم: <?= number_format($inv['amount'], 2) ?> SAR
        </div>

        <div class="footer">
            <div>
                <strong>المستلم</strong><br><br>
                ...........................
            </div>
            <div>
                <strong>الختم</strong><br><br>
                ...........................
            </div>
        </div>
    </div>
</body>
</html>
