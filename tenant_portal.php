<?php
/**
 * بوابة دفع المستأجر
 * صفحة عامة تسمح للمستأجرين بعرض دفعاتهم والدفع
 */
require 'config.php';

// التحقق من وجود payment_id في الرابط
$paymentId = isset($_GET['payment_id']) ? (int) $_GET['payment_id'] : 0;
$payment = null;
$tenant = null;
$contract = null;
$unit = null;
$error = '';
$success = '';

if ($paymentId > 0) {
    try {
        $stmt = $pdo->prepare("SELECT p.*, c.tenant_id, c.unit_id, c.start_date, c.end_date,
                              t.full_name, t.phone, t.email,
                              u.unit_name, pr.name AS property_name
                              FROM payments p
                              JOIN contracts c ON p.contract_id = c.id
                              JOIN tenants t ON c.tenant_id = t.id
                              JOIN units u ON c.unit_id = u.id
                              LEFT JOIN properties pr ON u.property_id = pr.id
                              WHERE p.id = ?");
        $stmt->execute([$paymentId]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$payment) {
            $error = 'الدفعة غير موجودة أو الرابط غير صحيح.';
        } else {
            $tenant = [
                'full_name' => $payment['full_name'],
                'phone' => $payment['phone'],
                'email' => $payment['email']
            ];
            $unit = [
                'unit_name' => $payment['unit_name'],
                'property_name' => $payment['property_name']
            ];
        }
    } catch (Exception $e) {
        $error = 'حدث خطأ أثناء جلب بيانات الدفعة.';
    }
}

// معالجة الدفع (محاكاة - يجب الربط مع بوابة دفع حقيقية)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    check_csrf();
    $payId = (int) ($_POST['payment_id'] ?? 0);
    $paymentMethod = trim($_POST['payment_method'] ?? 'online');
    
    if ($payId > 0) {
        try {
            // التحقق من ملكية الدفعة
            $stmt = $pdo->prepare("SELECT p.*, c.tenant_id FROM payments p 
                                   JOIN contracts c ON p.contract_id = c.id 
                                   WHERE p.id = ? AND p.status != 'paid'");
            $stmt->execute([$payId]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$payment) {
                $error = 'الدفعة غير موجودة أو تم دفعها مسبقاً.';
            } else {
                // ⚠️ تحذير أمني: في بيئة إنتاجية حقيقية، يجب التكامل مع بوابة دفع معتمدة
                // مثل: Stripe, Tap, HyperPay, Moyasar
                // يجب التحقق من نجاح الدفع الفعلي قبل تحديث الحالة
                
                // مثال: $paymentResult = processPaymentGateway($payId, $payment['amount']);
                // if ($paymentResult['success']) { ... }
                
                // هذا الكود للتجربة فقط - لا تستخدمه في بيئة إنتاجية
                $pdo->prepare("UPDATE payments SET status='paid', paid_date=CURDATE(), paid_amount=amount WHERE id=?")->execute([$payId]);
                
                // تسجيل المعاملة
                $pdo->prepare("INSERT INTO transactions (payment_id, amount_paid, payment_method, transaction_date, notes)
                              VALUES (?, ?, ?, CURDATE(), ?)")
                    ->execute([$payId, $payment['amount'], $paymentMethod, 'دفع عبر بوابة المستأجر (للتجربة)']);
                
                log_activity($pdo, "تم الدفع عبر البوابة - دفعة رقم: {$payId} (وضع تجريبي)", 'payment');
                
                $success = 'تم تسجيل الدفع بنجاح! (وضع تجريبي)';
                
                // إرسال تأكيد عبر واتساب
                if (isset($AI) && isset($tenant['phone'])) {
                    $msg = "تم تأكيد دفع إيجار وحدة {$unit['unit_name']} بمبلغ {$payment['amount']}. شكراً لك!";
                    $AI->sendWhatsApp($tenant['phone'], $msg);
                }
            }
        } catch (Exception $e) {
            $error = 'حدث خطأ أثناء معالجة الدفع. يرجى المحاولة لاحقاً.';
            log_activity($pdo, "خطأ في معالجة الدفع: {$e->getMessage()}", 'error');
        }
    }
}

$currency = get_setting('currency', 'SAR');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بوابة دفع المستأجر</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
            padding: 40px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #667eea;
            font-size: 28px;
            margin-bottom: 10px;
        }
        .header p {
            color: #666;
            font-size: 14px;
        }
        .payment-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
        }
        .payment-card h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 18px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px dashed #ddd;
        }
        .info-row:last-child { border-bottom: none; }
        .info-label {
            color: #666;
            font-size: 14px;
        }
        .info-value {
            color: #333;
            font-weight: bold;
            font-size: 14px;
        }
        .amount-display {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            margin-bottom: 25px;
        }
        .amount-display .label {
            font-size: 14px;
            margin-bottom: 10px;
            opacity: 0.9;
        }
        .amount-display .amount {
            font-size: 36px;
            font-weight: bold;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        .btn {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        .alert-success {
            background: #efe;
            color: #3c3;
            border: 1px solid #cfc;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-paid {
            background: #d4edda;
            color: #155724;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        .icon { margin-left: 8px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fa-solid fa-credit-card icon"></i>بوابة دفع الإيجار</h1>
            <p>ادفع إيجارك بسهولة وأمان</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-exclamation-triangle icon"></i><?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-check-circle icon"></i><?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($payment && !$success): ?>
            <div class="payment-card">
                <h3><i class="fa-solid fa-user icon"></i>معلومات المستأجر</h3>
                <div class="info-row">
                    <span class="info-label">الاسم:</span>
                    <span class="info-value"><?= htmlspecialchars($tenant['full_name']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">رقم الهاتف:</span>
                    <span class="info-value"><?= htmlspecialchars($tenant['phone']) ?></span>
                </div>
            </div>

            <div class="payment-card">
                <h3><i class="fa-solid fa-home icon"></i>معلومات الوحدة</h3>
                <div class="info-row">
                    <span class="info-label">الوحدة:</span>
                    <span class="info-value"><?= htmlspecialchars($unit['unit_name']) ?></span>
                </div>
                <?php if ($unit['property_name']): ?>
                <div class="info-row">
                    <span class="info-label">العقار:</span>
                    <span class="info-value"><?= htmlspecialchars($unit['property_name']) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <div class="payment-card">
                <h3><i class="fa-solid fa-file-invoice-dollar icon"></i>تفاصيل الدفعة</h3>
                <div class="info-row">
                    <span class="info-label">رقم الدفعة:</span>
                    <span class="info-value">#<?= $payment['id'] ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">عنوان الدفعة:</span>
                    <span class="info-value"><?= htmlspecialchars($payment['title']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">تاريخ الاستحقاق:</span>
                    <span class="info-value"><?= format_date($payment['due_date']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">الحالة:</span>
                    <span class="info-value">
                        <span class="status-badge status-<?= $payment['status'] === 'paid' ? 'paid' : 'pending' ?>">
                            <?= $payment['status'] === 'paid' ? 'مدفوعة' : 'معلقة' ?>
                        </span>
                    </span>
                </div>
            </div>

            <div class="amount-display">
                <div class="label">المبلغ المطلوب</div>
                <div class="amount"><?= number_format($payment['amount'], 2) ?> <?= $currency ?></div>
            </div>

            <?php if ($payment['status'] !== 'paid'): ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="payment_id" value="<?= $payment['id'] ?>">
                    
                    <div class="form-group">
                        <label><i class="fa-solid fa-wallet icon"></i>طريقة الدفع</label>
                        <select name="payment_method" required>
                            <option value="online">بطاقة ائتمان / خصم</option>
                            <option value="bank_transfer">تحويل بنكي</option>
                            <option value="cash">نقداً</option>
                        </select>
                    </div>

                    <button type="submit" name="confirm_payment" class="btn btn-primary">
                        <i class="fa-solid fa-lock icon"></i>تأكيد الدفع
                    </button>
                </form>
            <?php else: ?>
                <div class="alert alert-success">
                    <i class="fa-solid fa-check-circle icon"></i>تم دفع هذه الدفعة مسبقاً
                </div>
            <?php endif; ?>

        <?php elseif (!$paymentId): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-exclamation-triangle icon"></i>
                يرجى استخدام الرابط المرسل إليك عبر الواتساب أو البريد الإلكتروني.
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
