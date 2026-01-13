<?php
require 'config.php';
$id = $_GET['cid'];
$tenantNameColumn = tenant_name_column($pdo);
$c = $pdo->query("SELECT c.*, t.$tenantNameColumn AS full_name, t.id_number, u.unit_name, u.type, u.elec_meter_no AS elec_meter, u.water_meter_no AS water_meter, p.name as pname 
                  FROM contracts c JOIN tenants t ON c.tenant_id=t.id JOIN units u ON c.unit_id=u.id JOIN properties p ON u.property_id=p.id WHERE c.id=$id")->fetch();
$companyName = get_setting('company_name', 'اسم الشركة غير محدد');
$currencyCode = get_setting('currency_code', 'ر.س');
// رابط للتحقق من العقد (QR Data)
$qrData = "CONTRACT-{$c['id']}-{$c['full_name']}-AMOUNT-{$c['total_amount']}";
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head><meta charset="UTF-8"><title>عقد إيجار #<?= $c['id'] ?></title>
<style>
    body{font-family:'Tahoma';background:#f3f4f6;padding:20px}
    .page{background:white;max-width:850px;margin:auto;padding:60px;border:1px solid #e5e7eb;box-shadow:0 10px 30px rgba(0,0,0,0.05)}
    .header{display:flex;justify-content:space-between;border-bottom:3px solid #1e293b;padding-bottom:20px;margin-bottom:40px}
    table{width:100%;border-collapse:collapse;margin-bottom:20px}
    th,td{border:1px solid #cbd5e1;padding:12px;text-align:right} th{background:#f8fafc;width:150px}
    @media print{body{background:white;padding:0}.page{border:none;box-shadow:none}}
</style>
</head>
<body onload="window.print()">
    <div class="page">
        <div class="header">
            <div><h1 style="margin:0"><?= htmlspecialchars($companyName) ?></h1><p>عقد إيجار إلكتروني موحد</p><p>الرقم الضريبي: <?= getSet('vat_no') ?></p></div>
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=<?= urlencode($qrData) ?>" style="width:100px">
        </div>

        <h2 style="text-align:center; background:#1e293b; color:white; padding:12px; border-radius:5px">عقد إيجار وحدة (<?= $c['type'] ?>)</h2>

        <h3>1. أطراف العقد</h3>
        <table><tr><th>الطرف الأول (المؤجر)</th><td><?= htmlspecialchars($companyName) ?></td></tr><tr><th>الطرف الثاني (المستأجر)</th><td><b><?= $c['full_name'] ?></b> <br> هوية رقم: <?= $c['id_number'] ?></td></tr></table>

        <h3>2. بيانات العين المؤجرة</h3>
        <table>
            <tr><th>العقار</th><td><?= $c['pname'] ?></td><th>الوحدة</th><td><?= $c['unit_name'] ?></td></tr>
            <tr><th>عداد الكهرباء</th><td><?= $c['elec_meter'] ?></td><th>عداد المياه</th><td><?= $c['water_meter'] ?></td></tr>
        </table>

        <h3>3. المدة والقيمة</h3>
        <table>
            <tr><th>مدة العقد</th><td>من: <b><?= $c['start_date'] ?></b> إلى: <b><?= $c['end_date'] ?></b></td></tr>
            <tr><th>القيمة الإجمالية</th><td><b><?= number_format($c['total_amount']) ?></b> <?= htmlspecialchars($currencyCode) ?></td></tr>
        </table>

        <div style="display:flex; justify-content:space-between; margin-top:60px">
            <div style="text-align:center">
                <p>الطرف الأول</p>
                <img src="<?= getSet('logo') ?>" width="100" style="opacity:0.5">
                <p><b><?= htmlspecialchars($companyName) ?></b></p>
            </div>
            <div style="text-align:center">
                <p>الطرف الثاني</p>
                <?php if($c['signature_img']): ?>
                    <img src="<?= $c['signature_img'] ?>" width="180" style="border-bottom:1px solid #000">
                <?php else: ?>
                    <br><br><br>...........................
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
