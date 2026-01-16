<?php
/**
 * صفحة إدارة الدفعات
 * Payments Management Page
 */

// Handle payment status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_payment'])) {
    $paymentId = $_POST['payment_id'];
    $amountPaid = isset($_POST['amount_paid']) ? floatval($_POST['amount_paid']) : 0;
    $paymentMethod = $_POST['payment_method'] ?? 'cash';
    $paidDate = $_POST['paid_date'] ?? date('Y-m-d');
    $notes = $_POST['notes'] ?? '';
    
    // Get original payment details
    $stmt = $pdo->prepare("SELECT amount, original_amount, remaining_amount FROM payments WHERE id = ?");
    $stmt->execute([$paymentId]);
    $payment = $stmt->fetch();
    
    if ($payment) {
        $originalAmount = $payment['original_amount'] ?? $payment['amount'];
        $currentRemaining = $payment['remaining_amount'] ?? $originalAmount;
        
        // Calculate new remaining amount
        $newRemaining = max(0, $currentRemaining - $amountPaid);
        $status = $newRemaining <= 0.01 ? 'paid' : 'pending';
        $paymentType = $newRemaining > 0.01 ? 'partial' : 'full';
        
        // Update payment
        $stmt = $pdo->prepare("UPDATE payments SET 
            paid_date = ?, 
            payment_method = ?, 
            note = ?, 
            status = ?,
            original_amount = COALESCE(original_amount, amount),
            remaining_amount = ?,
            payment_type = ?
            WHERE id = ?");
        $stmt->execute([$paidDate, $paymentMethod, $notes, $status, $newRemaining, $paymentType, $paymentId]);
        
        // Log transaction
        $stmt = $pdo->prepare("INSERT INTO transactions (payment_id, amount_paid, payment_method, transaction_date, notes) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$paymentId, $amountPaid, $paymentMethod, $paidDate, $notes]);
        
        echo "<script>window.location='index.php?p=payments';</script>";
    }
}

// Handle deferred payment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['defer_payment'])) {
    $paymentId = $_POST['payment_id'];
    $newDueDate = $_POST['new_due_date'];
    $notes = $_POST['defer_notes'] ?? '';
    
    $stmt = $pdo->prepare("UPDATE payments SET due_date = ?, note = CONCAT(COALESCE(note, ''), '\nتأجيل الدفع إلى: ', ?, ' - ', ?) WHERE id = ?");
    $stmt->execute([$newDueDate, $newDueDate, $notes, $paymentId]);
    
    echo "<script>window.location='index.php?p=payments';</script>";
}

// Get filter parameters
$filterStatus = $_GET['status'] ?? 'all';
$filterContract = $_GET['contract'] ?? '';
$filterMonth = $_GET['month'] ?? '';

// Build query
$query = "SELECT p.*, c.id as contract_id, t.name as tenant_name, u.unit_name, pr.name as property_name 
          FROM payments p 
          JOIN contracts c ON p.contract_id = c.id 
          JOIN tenants t ON c.tenant_id = t.id 
          JOIN units u ON c.unit_id = u.id
          JOIN properties pr ON u.property_id = pr.id
          WHERE 1=1";

$params = [];
if ($filterStatus != 'all') {
    $query .= " AND p.status = ?";
    $params[] = $filterStatus;
}
if ($filterContract) {
    $query .= " AND p.contract_id = ?";
    $params[] = $filterContract;
}
if ($filterMonth) {
    $query .= " AND DATE_FORMAT(p.due_date, '%Y-%m') = ?";
    $params[] = $filterMonth;
}

$query .= " ORDER BY p.due_date ASC, p.id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$payments = $stmt->fetchAll();

// Calculate statistics
$totalExpected = 0;
$totalPaid = 0;
$totalPending = 0;
$overdueCount = 0;
$today = date('Y-m-d');

foreach ($payments as $payment) {
    $amount = $payment['remaining_amount'] ?? $payment['amount'];
    $totalExpected += $payment['amount'];
    if ($payment['status'] == 'paid') {
        $totalPaid += $payment['amount'];
    } else {
        $totalPending += $amount;
        if ($payment['due_date'] < $today) {
            $overdueCount++;
        }
    }
}

// Get contracts for filter
$contractsQuery = $pdo->query("SELECT c.id, t.name as tenant_name, u.unit_name FROM contracts c JOIN tenants t ON c.tenant_id = t.id JOIN units u ON c.unit_id = u.id WHERE c.status='active' ORDER BY t.name");
$contracts = $contractsQuery->fetchAll();
?>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px">
        <h3><i class="fa-solid fa-money-bill-wave" style="margin-left:10px;color:var(--primary)"></i> إدارة الدفعات</h3>
    </div>
    
    <!-- Statistics Cards -->
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:15px; margin-bottom:25px;">
        <div style="background:linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding:20px; border-radius:10px; color:white;">
            <div style="font-size:14px; opacity:0.9; margin-bottom:5px;">إجمالي المتوقع</div>
            <div style="font-size:24px; font-weight:bold;"><?= number_format($totalExpected, 2) ?> ر.س</div>
        </div>
        <div style="background:linear-gradient(135deg, #11998e 0%, #38ef7d 100%); padding:20px; border-radius:10px; color:white;">
            <div style="font-size:14px; opacity:0.9; margin-bottom:5px;">المدفوع</div>
            <div style="font-size:24px; font-weight:bold;"><?= number_format($totalPaid, 2) ?> ر.س</div>
        </div>
        <div style="background:linear-gradient(135deg, #f093fb 0%, #f5576c 100%); padding:20px; border-radius:10px; color:white;">
            <div style="font-size:14px; opacity:0.9; margin-bottom:5px;">المتبقي</div>
            <div style="font-size:24px; font-weight:bold;"><?= number_format($totalPending, 2) ?> ر.س</div>
        </div>
        <div style="background:linear-gradient(135deg, #fa709a 0%, #fee140 100%); padding:20px; border-radius:10px; color:white;">
            <div style="font-size:14px; opacity:0.9; margin-bottom:5px;">الدفعات المتأخرة</div>
            <div style="font-size:24px; font-weight:bold;"><?= $overdueCount ?></div>
        </div>
    </div>
    
    <!-- Filters -->
    <div style="background:#1a1a1a; padding:20px; border-radius:10px; margin-bottom:20px;">
        <form method="GET" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:15px;">
            <input type="hidden" name="p" value="payments">
            
            <div>
                <label style="display:block; margin-bottom:5px; color:#aaa; font-size:14px;">الحالة</label>
                <select name="status" class="inp" style="width:100%;">
                    <option value="all" <?= $filterStatus == 'all' ? 'selected' : '' ?>>الكل</option>
                    <option value="pending" <?= $filterStatus == 'pending' ? 'selected' : '' ?>>معلق</option>
                    <option value="paid" <?= $filterStatus == 'paid' ? 'selected' : '' ?>>مدفوع</option>
                </select>
            </div>
            
            <div>
                <label style="display:block; margin-bottom:5px; color:#aaa; font-size:14px;">العقد</label>
                <select name="contract" class="inp" style="width:100%;">
                    <option value="">كل العقود</option>
                    <?php foreach ($contracts as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $filterContract == $c['id'] ? 'selected' : '' ?>>
                        <?= $c['tenant_name'] ?> - <?= $c['unit_name'] ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label style="display:block; margin-bottom:5px; color:#aaa; font-size:14px;">الشهر</label>
                <input type="month" name="month" class="inp" value="<?= $filterMonth ?>" style="width:100%;">
            </div>
            
            <div style="display:flex; align-items:flex-end;">
                <button type="submit" class="btn btn-primary" style="width:100%;">
                    <i class="fa-solid fa-filter"></i> تصفية
                </button>
            </div>
        </form>
    </div>
    
    <!-- Payments Table -->
    <?php if (count($payments) == 0): ?>
        <div style="text-align:center; padding:50px; color:#777; border:2px dashed #333; border-radius:10px;">
            لا توجد دفعات
        </div>
    <?php else: ?>
        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; min-width:900px;">
                <thead>
                    <tr style="background:#222; text-align:right;">
                        <th style="padding:12px;">تاريخ الاستحقاق</th>
                        <th style="padding:12px;">المستأجر</th>
                        <th style="padding:12px;">العقار/الوحدة</th>
                        <th style="padding:12px;">المبلغ</th>
                        <th style="padding:12px;">المتبقي</th>
                        <th style="padding:12px;">الحالة</th>
                        <th style="padding:12px;">إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $p): 
                        $isOverdue = $p['status'] == 'pending' && $p['due_date'] < $today;
                        $remaining = $p['remaining_amount'] ?? $p['amount'];
                    ?>
                    <tr style="border-bottom:1px solid #333; <?= $isOverdue ? 'background:#2d1a1a;' : '' ?>">
                        <td style="padding:12px;">
                            <?= date('Y-m-d', strtotime($p['due_date'])) ?>
                            <?php if ($isOverdue): ?>
                                <span style="display:inline-block; background:#dc3545; color:white; padding:2px 8px; border-radius:4px; font-size:11px; margin-right:5px;">
                                    متأخر
                                </span>
                            <?php endif; ?>
                        </td>
                        <td style="padding:12px; font-weight:bold;"><?= $p['tenant_name'] ?></td>
                        <td style="padding:12px;">
                            <div><?= $p['property_name'] ?></div>
                            <div style="color:#999; font-size:13px;"><?= $p['unit_name'] ?></div>
                        </td>
                        <td style="padding:12px;"><?= number_format($p['amount'], 2) ?> ر.س</td>
                        <td style="padding:12px; font-weight:bold; color:<?= $p['status'] == 'paid' ? '#28a745' : '#ffc107' ?>;">
                            <?= number_format($remaining, 2) ?> ر.س
                        </td>
                        <td style="padding:12px;">
                            <?php if ($p['status'] == 'paid'): ?>
                                <span style="background:#28a745; color:white; padding:4px 12px; border-radius:20px; font-size:12px;">مدفوع</span>
                            <?php else: ?>
                                <span style="background:#ffc107; color:#000; padding:4px 12px; border-radius:20px; font-size:12px;">معلق</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding:12px;">
                            <div style="display:flex; gap:5px;">
                                <?php if ($p['status'] != 'paid'): ?>
                                <button onclick="openPaymentModal(<?= $p['id'] ?>, '<?= $p['tenant_name'] ?>', <?= $remaining ?>)" class="btn btn-success btn-sm">
                                    <i class="fa-solid fa-dollar-sign"></i> دفع
                                </button>
                                <button onclick="openDeferModal(<?= $p['id'] ?>, '<?= $p['tenant_name'] ?>')" class="btn btn-warning btn-sm">
                                    <i class="fa-solid fa-clock"></i> تأجيل
                                </button>
                                <?php endif; ?>
                                <a href="invoice_print.php?payment_id=<?= $p['id'] ?>" target="_blank" class="btn btn-dark btn-sm">
                                    <i class="fa-solid fa-print"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Payment Modal -->
<div id="paymentModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:999; justify-content:center; align-items:center;">
    <div class="modal-content" style="background:#1a1a1a; padding:25px; border-radius:15px; width:500px; max-width:90%;">
        <div style="display:flex; justify-content:space-between; margin-bottom:20px;">
            <h3 id="paymentModalTitle" style="margin:0">تسجيل دفعة</h3>
            <div style="cursor:pointer" onclick="closePaymentModal()"><i class="fa-solid fa-xmark"></i></div>
        </div>
        
        <form method="POST">
            <input type="hidden" name="update_payment" value="1">
            <input type="hidden" name="payment_id" id="payment_id">
            
            <div style="margin-bottom:15px">
                <label style="display:block; margin-bottom:5px; color:#aaa">المبلغ المدفوع</label>
                <input type="number" name="amount_paid" id="amount_paid" class="inp" step="0.01" min="0" required style="width:100%; padding:10px; background:#333; border:1px solid #444; color:white; border-radius:5px;">
                <small style="color:#999;" id="remainingInfo"></small>
            </div>
            
            <div style="margin-bottom:15px">
                <label style="display:block; margin-bottom:5px; color:#aaa">تاريخ الدفع</label>
                <input type="date" name="paid_date" class="inp" value="<?= date('Y-m-d') ?>" required style="width:100%; padding:10px; background:#333; border:1px solid #444; color:white; border-radius:5px;">
            </div>
            
            <div style="margin-bottom:15px">
                <label style="display:block; margin-bottom:5px; color:#aaa">طريقة الدفع</label>
                <select name="payment_method" class="inp" required style="width:100%; padding:10px; background:#333; border:1px solid #444; color:white; border-radius:5px;">
                    <option value="cash">نقدي</option>
                    <option value="bank_transfer">تحويل بنكي</option>
                    <option value="card">بطاقة</option>
                    <option value="online">بوابة دفع إلكترونية</option>
                </select>
            </div>
            
            <div style="margin-bottom:15px">
                <label style="display:block; margin-bottom:5px; color:#aaa">ملاحظات</label>
                <textarea name="notes" class="inp" rows="3" style="width:100%; padding:10px; background:#333; border:1px solid #444; color:white; border-radius:5px;"></textarea>
            </div>

            <button class="btn btn-success" style="width:100%; justify-content:center; padding:12px;">
                <i class="fa-solid fa-check"></i> تأكيد الدفع
            </button>
        </form>
    </div>
</div>

<!-- Defer Modal -->
<div id="deferModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:999; justify-content:center; align-items:center;">
    <div class="modal-content" style="background:#1a1a1a; padding:25px; border-radius:15px; width:500px; max-width:90%;">
        <div style="display:flex; justify-content:space-between; margin-bottom:20px;">
            <h3 id="deferModalTitle" style="margin:0">تأجيل الدفعة</h3>
            <div style="cursor:pointer" onclick="closeDeferModal()"><i class="fa-solid fa-xmark"></i></div>
        </div>
        
        <form method="POST">
            <input type="hidden" name="defer_payment" value="1">
            <input type="hidden" name="payment_id" id="defer_payment_id">
            
            <div style="margin-bottom:15px">
                <label style="display:block; margin-bottom:5px; color:#aaa">تاريخ الاستحقاق الجديد</label>
                <input type="date" name="new_due_date" class="inp" required style="width:100%; padding:10px; background:#333; border:1px solid #444; color:white; border-radius:5px;">
            </div>
            
            <div style="margin-bottom:15px">
                <label style="display:block; margin-bottom:5px; color:#aaa">سبب التأجيل</label>
                <textarea name="defer_notes" class="inp" rows="3" style="width:100%; padding:10px; background:#333; border:1px solid #444; color:white; border-radius:5px;"></textarea>
            </div>

            <button class="btn btn-warning" style="width:100%; justify-content:center; padding:12px;">
                <i class="fa-solid fa-clock"></i> تأجيل الدفعة
            </button>
        </form>
    </div>
</div>

<script>
function openPaymentModal(paymentId, tenantName, remaining) {
    document.getElementById('paymentModal').style.display = 'flex';
    document.getElementById('payment_id').value = paymentId;
    document.getElementById('amount_paid').value = remaining;
    document.getElementById('amount_paid').max = remaining;
    document.getElementById('remainingInfo').textContent = 'المبلغ المتبقي: ' + remaining.toFixed(2) + ' ر.س';
    document.getElementById('paymentModalTitle').textContent = 'تسجيل دفعة - ' + tenantName;
}

function closePaymentModal() {
    document.getElementById('paymentModal').style.display = 'none';
}

function openDeferModal(paymentId, tenantName) {
    document.getElementById('deferModal').style.display = 'flex';
    document.getElementById('defer_payment_id').value = paymentId;
    document.getElementById('deferModalTitle').textContent = 'تأجيل دفعة - ' + tenantName;
}

function closeDeferModal() {
    document.getElementById('deferModal').style.display = 'none';
}
</script>
