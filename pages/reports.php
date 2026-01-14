<?php
/**
 * صفحة التقارير المالية المتقدمة
 * تقارير شاملة للدخل والأداء والمقارنات
 */

// Initialize variables
$reportType = $_GET['type'] ?? 'income';
$dateFrom = $_GET['from'] ?? date('Y-m-01');
$dateTo = $_GET['to'] ?? date('Y-m-t');
$propertyId = isset($_GET['property']) ? (int)$_GET['property'] : 0;

// Income Report Data
$incomeData = [
    'total_collected' => 0,
    'total_expected' => 0,
    'pending_amount' => 0,
    'overdue_amount' => 0,
    'collection_rate' => 0,
    'by_property' => [],
    'by_month' => [],
];

// Property Performance Data
$propertyPerformance = [];

// Tenant Payment History
$tenantPayments = [];

// ROI Calculation
$roiData = [
    'total_investment' => 0,
    'total_revenue' => 0,
    'total_expenses' => 0,
    'net_income' => 0,
    'roi_percentage' => 0,
];

try {
    if (isset($pdo)) {
        // Income Report
        if ($reportType === 'income') {
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(amount), 0) 
                FROM payments 
                WHERE status = 'paid' 
                AND paid_date BETWEEN ? AND ?
            ");
            $stmt->execute([$dateFrom, $dateTo]);
            $incomeData['total_collected'] = (float) $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(amount), 0) 
                FROM payments 
                WHERE due_date BETWEEN ? AND ?
            ");
            $stmt->execute([$dateFrom, $dateTo]);
            $incomeData['total_expected'] = (float) $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(amount), 0) 
                FROM payments 
                WHERE status != 'paid' 
                AND due_date BETWEEN ? AND ?
                AND due_date >= CURDATE()
            ");
            $stmt->execute([$dateFrom, $dateTo]);
            $incomeData['pending_amount'] = (float) $stmt->fetchColumn();
            
            $incomeData['overdue_amount'] = (float) $pdo->query("
                SELECT COALESCE(SUM(amount), 0) 
                FROM payments 
                WHERE status != 'paid' 
                AND due_date < CURDATE()
            ")->fetchColumn();
            
            if ($incomeData['total_expected'] > 0) {
                $incomeData['collection_rate'] = round(($incomeData['total_collected'] / $incomeData['total_expected']) * 100, 2);
            }
            
            // Income by property
            $stmt = $pdo->prepare("
                SELECT 
                    p.name AS property_name,
                    COALESCE(SUM(pay.amount), 0) AS total_income,
                    COUNT(DISTINCT c.id) AS active_contracts,
                    COUNT(DISTINCT u.id) AS total_units
                FROM properties p
                LEFT JOIN units u ON u.property_id = p.id
                LEFT JOIN contracts c ON c.unit_id = u.id AND c.status = 'active'
                LEFT JOIN payments pay ON pay.contract_id = c.id AND pay.status = 'paid' AND pay.paid_date BETWEEN ? AND ?
                GROUP BY p.id
                ORDER BY total_income DESC
            ");
            $stmt->execute([$dateFrom, $dateTo]);
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $incomeData['by_property'][] = $row;
            }
            
            // Income by month (last 6 months)
            for ($i = 5; $i >= 0; $i--) {
                $monthStart = date('Y-m-01', strtotime("-{$i} months"));
                $monthEnd = date('Y-m-t', strtotime("-{$i} months"));
                $monthName = date('M Y', strtotime($monthStart));
                
                $stmt = $pdo->prepare("
                    SELECT COALESCE(SUM(amount), 0) 
                    FROM payments 
                    WHERE status = 'paid' 
                    AND paid_date BETWEEN ? AND ?
                ");
                $stmt->execute([$monthStart, $monthEnd]);
                $monthIncome = (float) $stmt->fetchColumn();
                
                $incomeData['by_month'][] = [
                    'month' => $monthName,
                    'income' => $monthIncome,
                ];
            }
        }
        
        // Property Performance Comparison
        if ($reportType === 'property_performance') {
            $propertyPerformance = $pdo->query("
                SELECT 
                    p.id,
                    p.name,
                    COUNT(DISTINCT u.id) AS total_units,
                    SUM(CASE WHEN u.status = 'rented' THEN 1 ELSE 0 END) AS rented_units,
                    COALESCE(AVG(u.yearly_price), 0) AS avg_price,
                    COALESCE(SUM(pay.amount), 0) AS total_revenue,
                    COALESCE(SUM(CASE WHEN pay.status = 'paid' THEN pay.amount ELSE 0 END), 0) AS collected_revenue,
                    COUNT(DISTINCT m.id) AS maintenance_requests
                FROM properties p
                LEFT JOIN units u ON u.property_id = p.id
                LEFT JOIN contracts c ON c.unit_id = u.id AND c.status = 'active'
                LEFT JOIN payments pay ON pay.contract_id = c.id
                LEFT JOIN maintenance m ON m.property_id = p.id
                GROUP BY p.id
                ORDER BY total_revenue DESC
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($propertyPerformance as &$property) {
                if ($property['total_units'] > 0) {
                    $property['occupancy_rate'] = round(($property['rented_units'] / $property['total_units']) * 100, 2);
                } else {
                    $property['occupancy_rate'] = 0;
                }
                
                if ($property['total_revenue'] > 0) {
                    $property['collection_rate'] = round(($property['collected_revenue'] / $property['total_revenue']) * 100, 2);
                } else {
                    $property['collection_rate'] = 0;
                }
            }
            unset($property);
        }
        
        // Tenant Payment History
        if ($reportType === 'tenant_payments') {
            $tenantNameColumn = tenant_name_column($pdo);
            $tenantPayments = $pdo->query("
                SELECT 
                    t.id,
                    t.{$tenantNameColumn} AS tenant_name,
                    t.phone,
                    COUNT(pay.id) AS total_payments,
                    SUM(CASE WHEN pay.status = 'paid' THEN 1 ELSE 0 END) AS paid_count,
                    SUM(CASE WHEN pay.status != 'paid' AND pay.due_date < CURDATE() THEN 1 ELSE 0 END) AS overdue_count,
                    COALESCE(SUM(pay.amount), 0) AS total_amount,
                    COALESCE(SUM(CASE WHEN pay.status = 'paid' THEN pay.amount ELSE 0 END), 0) AS paid_amount,
                    COALESCE(SUM(CASE WHEN pay.status != 'paid' THEN pay.amount ELSE 0 END), 0) AS pending_amount
                FROM tenants t
                LEFT JOIN contracts c ON c.tenant_id = t.id
                LEFT JOIN payments pay ON pay.contract_id = c.id
                GROUP BY t.id
                HAVING total_payments > 0
                ORDER BY paid_amount DESC
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($tenantPayments as &$tenant) {
                if ($tenant['total_amount'] > 0) {
                    $tenant['payment_rate'] = round(($tenant['paid_amount'] / $tenant['total_amount']) * 100, 2);
                } else {
                    $tenant['payment_rate'] = 0;
                }
                
                // Risk score calculation
                $riskScore = 0;
                if ($tenant['overdue_count'] > 0) {
                    $riskScore += $tenant['overdue_count'] * 20;
                }
                if ($tenant['payment_rate'] < 80) {
                    $riskScore += 20;
                }
                $tenant['risk_score'] = min(100, $riskScore);
                
                if ($tenant['risk_score'] >= 60) {
                    $tenant['risk_level'] = 'عالي';
                    $tenant['risk_color'] = '#ef4444';
                } elseif ($tenant['risk_score'] >= 30) {
                    $tenant['risk_level'] = 'متوسط';
                    $tenant['risk_color'] = '#f59e0b';
                } else {
                    $tenant['risk_level'] = 'منخفض';
                    $tenant['risk_color'] = '#10b981';
                }
            }
            unset($tenant);
        }
    }
} catch (Exception $e) {
    // Use default values
}

$properties = [];
try {
    $properties = $pdo->query("SELECT id, name FROM properties ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Ignore
}
?>

<div class="card" style="margin-bottom:30px;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:20px;">
        <div>
            <h2 style="margin:0; font-size:24px"><i class="fa-solid fa-chart-bar" style="margin-left:10px;color:var(--primary)"></i> التقارير المالية المتقدمة</h2>
            <p style="margin:8px 0 0; color:var(--muted)">تحليلات شاملة للدخل والأداء ومقارنات العقارات</p>
        </div>
        <div style="display:flex; gap:10px;">
            <a href="?p=reports&type=income" class="btn <?= $reportType === 'income' ? 'btn-primary' : 'btn-dark' ?>">
                <i class="fa-solid fa-dollar-sign"></i> تقرير الدخل
            </a>
            <a href="?p=reports&type=property_performance" class="btn <?= $reportType === 'property_performance' ? 'btn-primary' : 'btn-dark' ?>">
                <i class="fa-solid fa-building"></i> أداء العقارات
            </a>
            <a href="?p=reports&type=tenant_payments" class="btn <?= $reportType === 'tenant_payments' ? 'btn-primary' : 'btn-dark' ?>">
                <i class="fa-solid fa-users"></i> سجل المستأجرين
            </a>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom:30px; padding:20px;">
    <form method="GET" style="display:flex; gap:15px; flex-wrap:wrap; align-items:end;">
        <input type="hidden" name="p" value="reports">
        <input type="hidden" name="type" value="<?= htmlspecialchars($reportType) ?>">
        
        <div style="flex:1; min-width:200px;">
            <label class="inp-label">من تاريخ</label>
            <input type="date" name="from" value="<?= htmlspecialchars($dateFrom) ?>" class="inp">
        </div>
        
        <div style="flex:1; min-width:200px;">
            <label class="inp-label">إلى تاريخ</label>
            <input type="date" name="to" value="<?= htmlspecialchars($dateTo) ?>" class="inp">
        </div>
        
        <?php if ($reportType === 'property_performance'): ?>
        <div style="flex:1; min-width:200px;">
            <label class="inp-label">العقار (اختياري)</label>
            <select name="property" class="inp">
                <option value="0">جميع العقارات</option>
                <?php foreach ($properties as $prop): ?>
                    <option value="<?= $prop['id'] ?>" <?= $propertyId == $prop['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($prop['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> تطبيق الفلتر</button>
    </form>
</div>

<?php if ($reportType === 'income'): ?>
<!-- Income Report -->
<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap:20px; margin-bottom:30px;">
    <div class="card" style="padding:20px;">
        <div style="font-size:12px; color:var(--muted)">إجمالي المحصل</div>
        <div style="font-size:28px; font-weight:800; margin-top:8px; color:#10b981;"><?= number_format($incomeData['total_collected'], 2) ?></div>
        <div style="font-size:12px; color:#94a3b8; margin-top:6px;">من <?= $dateFrom ?> إلى <?= $dateTo ?></div>
    </div>
    
    <div class="card" style="padding:20px;">
        <div style="font-size:12px; color:var(--muted)">إجمالي المتوقع</div>
        <div style="font-size:28px; font-weight:800; margin-top:8px;"><?= number_format($incomeData['total_expected'], 2) ?></div>
        <div style="font-size:12px; color:#94a3b8; margin-top:6px;">جميع الفواتير في الفترة</div>
    </div>
    
    <div class="card" style="padding:20px;">
        <div style="font-size:12px; color:var(--muted)">معدل التحصيل</div>
        <div style="font-size:28px; font-weight:800; margin-top:8px; color:#22d3ee;"><?= $incomeData['collection_rate'] ?>%</div>
        <div style="font-size:12px; color:#94a3b8; margin-top:6px;">كفاءة التحصيل</div>
    </div>
    
    <div class="card" style="padding:20px;">
        <div style="font-size:12px; color:var(--muted)">متأخرات حالية</div>
        <div style="font-size:28px; font-weight:800; margin-top:8px; color:#ef4444;"><?= number_format($incomeData['overdue_amount'], 2) ?></div>
        <div style="font-size:12px; color:#94a3b8; margin-top:6px;">تحتاج متابعة</div>
    </div>
</div>

<div class="card" style="margin-bottom:30px;">
    <h3 style="margin-top:0;"><i class="fa-solid fa-building"></i> الدخل حسب العقار</h3>
    <?php if (empty($incomeData['by_property'])): ?>
        <div style="text-align:center; padding:40px; color:#666">لا توجد بيانات للعرض</div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>اسم العقار</th>
                    <th>إجمالي الدخل</th>
                    <th>العقود النشطة</th>
                    <th>إجمالي الوحدات</th>
                    <th>معدل الإشغال</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($incomeData['by_property'] as $prop): 
                    $occupancy = $prop['total_units'] > 0 ? round(($prop['active_contracts'] / $prop['total_units']) * 100, 1) : 0;
                ?>
                <tr>
                    <td style="font-weight:bold"><?= htmlspecialchars($prop['property_name']) ?></td>
                    <td style="color:#10b981"><?= number_format($prop['total_income'], 2) ?></td>
                    <td><?= $prop['active_contracts'] ?></td>
                    <td><?= $prop['total_units'] ?></td>
                    <td>
                        <span style="background:<?= $occupancy >= 80 ? '#10b981' : ($occupancy >= 50 ? '#f59e0b' : '#ef4444') ?>; color:white; padding:4px 10px; border-radius:8px; font-size:12px;">
                            <?= $occupancy ?>%
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="card" style="margin-bottom:30px;">
    <h3 style="margin-top:0;"><i class="fa-solid fa-chart-line"></i> الدخل الشهري (آخر 6 أشهر)</h3>
    <div style="height:300px;">
        <canvas id="monthlyIncomeChart"></canvas>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('monthlyIncomeChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_column($incomeData['by_month'], 'month')) ?>,
                datasets: [{
                    label: 'الدخل الشهري',
                    data: <?= json_encode(array_column($incomeData['by_month'], 'income')) ?>,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: '#94a3b8'
                        },
                        grid: {
                            color: 'rgba(148, 163, 184, 0.1)'
                        }
                    },
                    x: {
                        ticks: {
                            color: '#94a3b8'
                        },
                        grid: {
                            color: 'rgba(148, 163, 184, 0.1)'
                        }
                    }
                },
                plugins: {
                    legend: {
                        labels: {
                            color: '#94a3b8'
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php elseif ($reportType === 'property_performance'): ?>
<!-- Property Performance Report -->
<div class="card" style="margin-bottom:30px;">
    <h3 style="margin-top:0;"><i class="fa-solid fa-building"></i> مقارنة أداء العقارات</h3>
    <?php if (empty($propertyPerformance)): ?>
        <div style="text-align:center; padding:40px; color:#666">لا توجد بيانات للعرض</div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>اسم العقار</th>
                    <th>الوحدات</th>
                    <th>نسبة الإشغال</th>
                    <th>متوسط السعر</th>
                    <th>إجمالي الدخل</th>
                    <th>معدل التحصيل</th>
                    <th>طلبات الصيانة</th>
                    <th>الأداء</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($propertyPerformance as $property): 
                    $performanceScore = ($property['occupancy_rate'] * 0.4) + ($property['collection_rate'] * 0.4) + (max(0, 100 - ($property['maintenance_requests'] * 10)) * 0.2);
                    $performanceScore = min(100, round($performanceScore, 1));
                ?>
                <tr>
                    <td style="font-weight:bold"><?= htmlspecialchars($property['name']) ?></td>
                    <td><?= $property['rented_units'] ?> / <?= $property['total_units'] ?></td>
                    <td>
                        <span style="background:<?= $property['occupancy_rate'] >= 80 ? '#10b981' : ($property['occupancy_rate'] >= 50 ? '#f59e0b' : '#ef4444') ?>; color:white; padding:4px 10px; border-radius:8px; font-size:12px;">
                            <?= $property['occupancy_rate'] ?>%
                        </span>
                    </td>
                    <td><?= number_format($property['avg_price'], 2) ?></td>
                    <td style="color:#10b981"><?= number_format($property['total_revenue'], 2) ?></td>
                    <td>
                        <span style="background:<?= $property['collection_rate'] >= 90 ? '#10b981' : ($property['collection_rate'] >= 70 ? '#f59e0b' : '#ef4444') ?>; color:white; padding:4px 10px; border-radius:8px; font-size:12px;">
                            <?= $property['collection_rate'] ?>%
                        </span>
                    </td>
                    <td><?= $property['maintenance_requests'] ?></td>
                    <td>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <div style="flex:1; background:#333; border-radius:10px; height:8px; overflow:hidden;">
                                <div style="background:<?= $performanceScore >= 80 ? '#10b981' : ($performanceScore >= 60 ? '#f59e0b' : '#ef4444') ?>; height:100%; width:<?= $performanceScore ?>%;"></div>
                            </div>
                            <span style="font-weight:bold; min-width:45px;"><?= $performanceScore ?>%</span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php elseif ($reportType === 'tenant_payments'): ?>
<!-- Tenant Payment History -->
<div class="card" style="margin-bottom:30px;">
    <h3 style="margin-top:0;"><i class="fa-solid fa-users"></i> سجل الدفعات للمستأجرين</h3>
    <?php if (empty($tenantPayments)): ?>
        <div style="text-align:center; padding:40px; color:#666">لا توجد بيانات للعرض</div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>اسم المستأجر</th>
                    <th>الهاتف</th>
                    <th>إجمالي الدفعات</th>
                    <th>المدفوع</th>
                    <th>المتأخر</th>
                    <th>معدل السداد</th>
                    <th>درجة المخاطر</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tenantPayments as $tenant): ?>
                <tr>
                    <td style="font-weight:bold"><?= htmlspecialchars($tenant['tenant_name']) ?></td>
                    <td><?= htmlspecialchars($tenant['phone']) ?></td>
                    <td><?= $tenant['total_payments'] ?> (<?= number_format($tenant['total_amount'], 2) ?>)</td>
                    <td style="color:#10b981"><?= number_format($tenant['paid_amount'], 2) ?></td>
                    <td style="color:#ef4444">
                        <?= $tenant['overdue_count'] ?> (<?= number_format($tenant['pending_amount'], 2) ?>)
                    </td>
                    <td>
                        <span style="background:<?= $tenant['payment_rate'] >= 90 ? '#10b981' : ($tenant['payment_rate'] >= 70 ? '#f59e0b' : '#ef4444') ?>; color:white; padding:4px 10px; border-radius:8px; font-size:12px;">
                            <?= $tenant['payment_rate'] ?>%
                        </span>
                    </td>
                    <td>
                        <span style="background:<?= $tenant['risk_color'] ?>; color:white; padding:4px 10px; border-radius:8px; font-size:12px;">
                            <?= $tenant['risk_level'] ?> (<?= $tenant['risk_score'] ?>)
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php endif; ?>
