<?php
/**
 * Tax Invoice Print Page
 * صفحة طباعة الفاتورة الضريبية
 */
require 'config.php';

$paymentId = $_GET['payment_id'] ?? 0;

// Get payment details with contract and tenant info
$stmt = $pdo->prepare("
    SELECT p.*, c.id as contract_id, c.start_date, c.end_date, c.total_amount as contract_total,
           c.tax_included, c.tax_percent, c.tax_amount,
           t.name as tenant_name, t.phone as tenant_phone, t.id_number as tenant_id,
           u.unit_name, u.type as unit_type,
           pr.name as property_name, pr.address as property_address
    FROM payments p
    JOIN contracts c ON p.contract_id = c.id
    JOIN tenants t ON c.tenant_id = t.id
    JOIN units u ON c.unit_id = u.id
    JOIN properties pr ON u.property_id = pr.id
    WHERE p.id = ?
");
$stmt->execute([$paymentId]);
$payment = $stmt->fetch();

if (!$payment) {
    die('الدفعة غير موجودة');
}

// Get company settings
$companyName = get_setting('company_name', 'اسم الشركة');
$companyAddress = get_setting('address', '');
$vatNo = get_setting('vat_no', '');
$crNo = get_setting('cr_no', '');
$currencyCode = get_setting('currency_code', 'ر.س');

// Calculate VAT for this payment
$paymentAmount = $payment['amount'];
$taxIncluded = $payment['tax_included'];
$taxPercent = $payment['tax_percent'] ?? 0;

if ($taxIncluded && $taxPercent > 0) {
    $baseAmount = $paymentAmount / (1 + ($taxPercent / 100));
    $taxAmount = $paymentAmount - $baseAmount;
} else {
    $baseAmount = $paymentAmount;
    $taxAmount = 0;
}

// Invoice number
$invoiceNo = 'INV-' . $payment['id'] . '-' . date('Y');
$invoiceDate = $payment['paid_date'] ?? date('Y-m-d');

// QR Code data for ZATCA
$qrData = implode('|', [
    $companyName,
    $vatNo,
    $invoiceDate,
    number_format($paymentAmount, 2),
    number_format($taxAmount, 2)
]);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فاتورة ضريبية - <?= $invoiceNo ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Tahoma', 'Arial', sans-serif;
            background: #f5f5f5;
            padding: 20px;
            line-height: 1.6;
        }
        .invoice {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .header {
            border-bottom: 3px solid #6366f1;
            padding-bottom: 20px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: start;
        }
        .header h1 {
            color: #6366f1;
            font-size: 24px;
            margin-bottom: 5px;
        }
        .header .company-info {
            flex: 1;
        }
        .header .qr-section {
            text-align: center;
        }
        .invoice-title {
            text-align: center;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 30px;
            font-size: 20px;
            font-weight: bold;
        }
        .info-section {
            margin-bottom: 30px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        .info-box {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            border-right: 4px solid #6366f1;
        }
        .info-box h3 {
            color: #1e293b;
            font-size: 14px;
            margin-bottom: 10px;
            font-weight: bold;
        }
        .info-box p {
            color: #475569;
            font-size: 13px;
            margin: 5px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: right;
            border: 1px solid #e2e8f0;
        }
        th {
            background: #f1f5f9;
            color: #1e293b;
            font-weight: bold;
        }
        .totals-table {
            margin-top: 20px;
            width: 400px;
            margin-right: auto;
        }
        .totals-table td {
            padding: 10px 15px;
        }
        .totals-table .total-row {
            background: #6366f1;
            color: white;
            font-weight: bold;
            font-size: 16px;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #e2e8f0;
            text-align: center;
            color: #64748b;
            font-size: 12px;
        }
        .stamp-section {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
        }
        .stamp-box {
            text-align: center;
            padding: 20px;
            border: 2px dashed #cbd5e1;
            border-radius: 8px;
            width: 200px;
        }
        @media print {
            body { background: white; padding: 0; }
            .invoice { box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="invoice">
        <!-- Header -->
        <div class="header">
            <div class="company-info">
                <h1><?= htmlspecialchars($companyName) ?></h1>
                <p style="color: #64748b; margin-top: 5px;">
                    <?php if ($companyAddress): ?>
                        <?= htmlspecialchars($companyAddress) ?><br>
                    <?php endif; ?>
                    <?php if ($vatNo): ?>
                        الرقم الضريبي: <?= htmlspecialchars($vatNo) ?><br>
                    <?php endif; ?>
                    <?php if ($crNo): ?>
                        السجل التجاري: <?= htmlspecialchars($crNo) ?>
                    <?php endif; ?>
                </p>
            </div>
            <div class="qr-section">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=<?= urlencode($qrData) ?>" 
                     alt="QR Code" style="width: 120px; height: 120px;">
            </div>
        </div>

        <!-- Invoice Title -->
        <div class="invoice-title">
            فاتورة ضريبية مبسطة<br>
            Simplified Tax Invoice
        </div>

        <!-- Invoice Info -->
        <div class="info-grid">
            <div class="info-box">
                <h3>بيانات الفاتورة</h3>
                <p><strong>رقم الفاتورة:</strong> <?= $invoiceNo ?></p>
                <p><strong>تاريخ الإصدار:</strong> <?= $invoiceDate ?></p>
                <p><strong>تاريخ الاستحقاق:</strong> <?= $payment['due_date'] ?></p>
            </div>
            <div class="info-box">
                <h3>بيانات المستأجر</h3>
                <p><strong>الاسم:</strong> <?= htmlspecialchars($payment['tenant_name']) ?></p>
                <p><strong>رقم الهوية:</strong> <?= htmlspecialchars($payment['tenant_id'] ?? '-') ?></p>
                <p><strong>الجوال:</strong> <?= htmlspecialchars($payment['tenant_phone'] ?? '-') ?></p>
            </div>
        </div>

        <div class="info-box" style="margin-bottom: 20px;">
            <h3>بيانات العقار</h3>
            <p><strong>العقار:</strong> <?= htmlspecialchars($payment['property_name']) ?></p>
            <p><strong>الوحدة:</strong> <?= htmlspecialchars($payment['unit_name']) ?> (<?= htmlspecialchars($payment['unit_type']) ?>)</p>
            <p><strong>العقد:</strong> #<?= $payment['contract_id'] ?> | من <?= $payment['start_date'] ?> إلى <?= $payment['end_date'] ?></p>
        </div>

        <!-- Items Table -->
        <table>
            <thead>
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>البيان</th>
                    <th style="width: 120px;">المبلغ</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1</td>
                    <td>
                        <?= htmlspecialchars($payment['title'] ?? 'دفعة إيجار') ?>
                        <br>
                        <small style="color: #64748b;">
                            <?php if (!empty($payment['note'])): ?>
                                <?= htmlspecialchars($payment['note']) ?>
                            <?php endif; ?>
                        </small>
                    </td>
                    <td style="text-align: left;">
                        <?= number_format($baseAmount, 2) ?> <?= $currencyCode ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Totals -->
        <table class="totals-table">
            <tr>
                <td><strong>المبلغ الأساسي:</strong></td>
                <td style="text-align: left;"><?= number_format($baseAmount, 2) ?> <?= $currencyCode ?></td>
            </tr>
            <?php if ($taxIncluded && $taxAmount > 0): ?>
            <tr>
                <td><strong>ضريبة القيمة المضافة (<?= $taxPercent ?>%):</strong></td>
                <td style="text-align: left;"><?= number_format($taxAmount, 2) ?> <?= $currencyCode ?></td>
            </tr>
            <?php endif; ?>
            <tr class="total-row">
                <td><strong>الإجمالي الواجب الدفع:</strong></td>
                <td style="text-align: left;"><strong><?= number_format($paymentAmount, 2) ?> <?= $currencyCode ?></strong></td>
            </tr>
        </table>

        <?php if ($payment['status'] == 'paid'): ?>
        <div style="background: #dcfce7; border: 2px solid #16a34a; color: #166534; padding: 15px; border-radius: 8px; text-align: center; margin-top: 20px;">
            <strong>✓ تم الدفع</strong>
            <?php if ($payment['paid_date']): ?>
                <br>تاريخ الدفع: <?= $payment['paid_date'] ?>
            <?php endif; ?>
            <?php if ($payment['payment_method']): ?>
                <br>طريقة الدفع: <?= payment_method_label($payment['payment_method']) ?>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div style="background: #fef3c7; border: 2px solid #f59e0b; color: #92400e; padding: 15px; border-radius: 8px; text-align: center; margin-top: 20px;">
            <strong>⚠ في انتظار الدفع</strong>
        </div>
        <?php endif; ?>

        <!-- Stamp Section -->
        <div class="stamp-section">
            <div class="stamp-box">
                <p style="color: #64748b; font-size: 12px;">ختم الشركة</p>
            </div>
            <div class="stamp-box">
                <p style="color: #64748b; font-size: 12px;">توقيع المستلم</p>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>هذه فاتورة إلكترونية صادرة من نظام إدارة العقارات</p>
            <p>للاستفسار: يرجى التواصل مع إدارة العقار</p>
            <p style="margin-top: 10px; font-size: 11px;">تم الإصدار بتاريخ: <?= date('Y-m-d H:i') ?></p>
        </div>
    </div>

    <script>
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>
