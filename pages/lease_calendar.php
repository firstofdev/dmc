<?php
/**
 * صفحة تقويم انتهاء العقود و حاسبة العائد على الاستثمار
 */

$view = $_GET['view'] ?? 'calendar';

// Calendar data
$upcomingContracts = [];
$expiringThisMonth = [];
$expiringNextMonth = [];
$expiringNext3Months = [];

// ROI Calculator data
$roiCalculations = [];
$selectedProperty = isset($_GET['roi_property']) ? (int)$_GET['roi_property'] : 0;

try {
    if (isset($pdo)) {
        $tenantNameColumn = tenant_name_column($pdo);
        
        // Get contracts expiring in next 90 days
        $upcomingContracts = $pdo->query("
            SELECT 
                c.id,
                c.start_date,
                c.end_date,
                c.total_amount,
                t.{$tenantNameColumn} AS tenant_name,
                t.phone,
                u.unit_name,
                p.name AS property_name,
                DATEDIFF(c.end_date, CURDATE()) AS days_until_expiry
            FROM contracts c
            JOIN tenants t ON c.tenant_id = t.id
            JOIN units u ON c.unit_id = u.id
            JOIN properties p ON u.property_id = p.id
            WHERE c.status = 'active'
            AND c.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)
            ORDER BY c.end_date ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        // Group contracts by expiry period
        foreach ($upcomingContracts as $contract) {
            $daysUntil = $contract['days_until_expiry'];
            
            if ($daysUntil <= 30) {
                $expiringThisMonth[] = $contract;
            } elseif ($daysUntil <= 60) {
                $expiringNextMonth[] = $contract;
            } else {
                $expiringNext3Months[] = $contract;
            }
        }
        
        // ROI Calculations
        if ($view === 'roi') {
            // Get all properties for selection
            $properties = $pdo->query("SELECT id, name FROM properties ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate ROI for selected property or all properties
            $whereClause = $selectedProperty > 0 ? "WHERE p.id = {$selectedProperty}" : "";
            
            $roiQuery = $pdo->query("
                SELECT 
                    p.id,
                    p.name AS property_name,
                    COUNT(DISTINCT u.id) AS total_units,
                    SUM(CASE WHEN u.status = 'rented' THEN 1 ELSE 0 END) AS rented_units,
                    COALESCE(SUM(u.yearly_price), 0) AS total_yearly_value,
                    COALESCE(SUM(CASE WHEN u.status = 'rented' THEN u.yearly_price ELSE 0 END), 0) AS actual_yearly_income,
                    COALESCE(SUM(pay.amount), 0) AS total_collected
                FROM properties p
                LEFT JOIN units u ON u.property_id = p.id
                LEFT JOIN contracts c ON c.unit_id = u.id AND c.status = 'active'
                LEFT JOIN payments pay ON pay.contract_id = c.id AND pay.status = 'paid' 
                    AND pay.paid_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                {$whereClause}
                GROUP BY p.id
                ORDER BY total_collected DESC
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($roiQuery as &$property) {
                // Calculate maintenance costs for the past year
                $maintenanceCosts = (float) $pdo->query("
                    SELECT COALESCE(SUM(cost), 0)
                    FROM maintenance
                    WHERE property_id = {$property['id']}
                    AND request_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                ")->fetchColumn();
                
                $property['maintenance_costs'] = $maintenanceCosts;
                
                // Estimated property value (simplified: yearly_value * 12-15 years)
                $estimatedValue = $property['total_yearly_value'] * 12;
                $property['estimated_value'] = $estimatedValue;
                
                // Net annual income
                $netIncome = $property['total_collected'] - $maintenanceCosts;
                $property['net_annual_income'] = $netIncome;
                
                // ROI Percentage = (Net Annual Income / Estimated Property Value) * 100
                if ($estimatedValue > 0) {
                    $property['roi_percentage'] = round(($netIncome / $estimatedValue) * 100, 2);
                } else {
                    $property['roi_percentage'] = 0;
                }
                
                // Occupancy rate
                if ($property['total_units'] > 0) {
                    $property['occupancy_rate'] = round(($property['rented_units'] / $property['total_units']) * 100, 2);
                } else {
                    $property['occupancy_rate'] = 0;
                }
                
                // Revenue per unit
                if ($property['total_units'] > 0) {
                    $property['revenue_per_unit'] = round($property['total_collected'] / $property['total_units'], 2);
                } else {
                    $property['revenue_per_unit'] = 0;
                }
                
                // Maintenance ratio
                if ($property['total_collected'] > 0) {
                    $property['maintenance_ratio'] = round(($maintenanceCosts / $property['total_collected']) * 100, 2);
                } else {
                    $property['maintenance_ratio'] = 0;
                }
            }
            unset($property);
            
            $roiCalculations = $roiQuery;
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
            <h2 style="margin:0; font-size:24px"><i class="fa-solid fa-calendar-check" style="margin-left:10px;color:var(--primary)"></i> إدارة العقود والاستثمارات</h2>
            <p style="margin:8px 0 0; color:var(--muted)">تقويم انتهاء العقود وحاسبة العائد على الاستثمار</p>
        </div>
        <div style="display:flex; gap:10px;">
            <a href="?p=lease_calendar&view=calendar" class="btn <?= $view === 'calendar' ? 'btn-primary' : 'btn-dark' ?>">
                <i class="fa-solid fa-calendar-days"></i> تقويم العقود
            </a>
            <a href="?p=lease_calendar&view=roi" class="btn <?= $view === 'roi' ? 'btn-primary' : 'btn-dark' ?>">
                <i class="fa-solid fa-calculator"></i> حاسبة ROI
            </a>
        </div>
    </div>
</div>

<?php if ($view === 'calendar'): ?>
<!-- Contract Expiration Calendar -->

<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:20px; margin-bottom:30px;">
    <div class="card" style="padding:20px;">
        <div style="font-size:12px; color:var(--muted)">ينتهي خلال 30 يوماً</div>
        <div style="font-size:32px; font-weight:800; margin-top:8px; color:#ef4444;"><?= count($expiringThisMonth) ?></div>
        <div style="font-size:12px; color:#94a3b8; margin-top:6px;">يحتاج إجراء فوري</div>
    </div>
    
    <div class="card" style="padding:20px;">
        <div style="font-size:12px; color:var(--muted)">ينتهي خلال 60 يوماً</div>
        <div style="font-size:32px; font-weight:800; margin-top:8px; color:#f59e0b;"><?= count($expiringNextMonth) ?></div>
        <div style="font-size:12px; color:#94a3b8; margin-top:6px;">التخطيط للتجديد</div>
    </div>
    
    <div class="card" style="padding:20px;">
        <div style="font-size:12px; color:var(--muted)">ينتهي خلال 90 يوماً</div>
        <div style="font-size:32px; font-weight:800; margin-top:8px; color:#22d3ee;"><?= count($expiringNext3Months) ?></div>
        <div style="font-size:12px; color:#94a3b8; margin-top:6px;">الاستعداد المبكر</div>
    </div>
</div>

<!-- Expiring This Month -->
<?php if (!empty($expiringThisMonth)): ?>
<div class="card" style="margin-bottom:30px; border:2px solid #ef4444;">
    <h3 style="margin-top:0; color:#ef4444;"><i class="fa-solid fa-exclamation-triangle"></i> عقود تنتهي خلال 30 يوماً - إجراء عاجل!</h3>
    <table>
        <thead>
            <tr>
                <th>رقم العقد</th>
                <th>المستأجر</th>
                <th>العقار / الوحدة</th>
                <th>تاريخ الانتهاء</th>
                <th>الأيام المتبقية</th>
                <th>القيمة السنوية</th>
                <th>إجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($expiringThisMonth as $contract): ?>
            <tr>
                <td style="font-weight:bold">#<?= $contract['id'] ?></td>
                <td><?= htmlspecialchars($contract['tenant_name']) ?><br>
                    <small style="color:#888"><?= htmlspecialchars($contract['phone']) ?></small>
                </td>
                <td><?= htmlspecialchars($contract['property_name']) ?><br>
                    <small style="color:#888"><?= htmlspecialchars($contract['unit_name']) ?></small>
                </td>
                <td><?= format_date($contract['end_date']) ?></td>
                <td>
                    <span style="background:#ef4444; color:white; padding:4px 10px; border-radius:8px; font-size:12px; font-weight:bold;">
                        <?= $contract['days_until_expiry'] ?> يوم
                    </span>
                </td>
                <td style="color:#10b981"><?= number_format($contract['total_amount'], 2) ?></td>
                <td>
                    <a href="index.php?p=contract_view&id=<?= $contract['id'] ?>" class="btn btn-primary btn-sm">
                        <i class="fa-solid fa-eye"></i> عرض
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Expiring Next Month -->
<?php if (!empty($expiringNextMonth)): ?>
<div class="card" style="margin-bottom:30px; border:2px solid #f59e0b;">
    <h3 style="margin-top:0; color:#f59e0b;"><i class="fa-solid fa-clock"></i> عقود تنتهي خلال 31-60 يوماً</h3>
    <table>
        <thead>
            <tr>
                <th>رقم العقد</th>
                <th>المستأجر</th>
                <th>العقار / الوحدة</th>
                <th>تاريخ الانتهاء</th>
                <th>الأيام المتبقية</th>
                <th>القيمة السنوية</th>
                <th>إجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($expiringNextMonth as $contract): ?>
            <tr>
                <td style="font-weight:bold">#<?= $contract['id'] ?></td>
                <td><?= htmlspecialchars($contract['tenant_name']) ?><br>
                    <small style="color:#888"><?= htmlspecialchars($contract['phone']) ?></small>
                </td>
                <td><?= htmlspecialchars($contract['property_name']) ?><br>
                    <small style="color:#888"><?= htmlspecialchars($contract['unit_name']) ?></small>
                </td>
                <td><?= format_date($contract['end_date']) ?></td>
                <td>
                    <span style="background:#f59e0b; color:white; padding:4px 10px; border-radius:8px; font-size:12px; font-weight:bold;">
                        <?= $contract['days_until_expiry'] ?> يوم
                    </span>
                </td>
                <td style="color:#10b981"><?= number_format($contract['total_amount'], 2) ?></td>
                <td>
                    <a href="index.php?p=contract_view&id=<?= $contract['id'] ?>" class="btn btn-primary btn-sm">
                        <i class="fa-solid fa-eye"></i> عرض
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Expiring Next 3 Months -->
<?php if (!empty($expiringNext3Months)): ?>
<div class="card" style="margin-bottom:30px;">
    <h3 style="margin-top:0; color:#22d3ee;"><i class="fa-solid fa-calendar"></i> عقود تنتهي خلال 61-90 يوماً</h3>
    <table>
        <thead>
            <tr>
                <th>رقم العقد</th>
                <th>المستأجر</th>
                <th>العقار / الوحدة</th>
                <th>تاريخ الانتهاء</th>
                <th>الأيام المتبقية</th>
                <th>القيمة السنوية</th>
                <th>إجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($expiringNext3Months as $contract): ?>
            <tr>
                <td style="font-weight:bold">#<?= $contract['id'] ?></td>
                <td><?= htmlspecialchars($contract['tenant_name']) ?><br>
                    <small style="color:#888"><?= htmlspecialchars($contract['phone']) ?></small>
                </td>
                <td><?= htmlspecialchars($contract['property_name']) ?><br>
                    <small style="color:#888"><?= htmlspecialchars($contract['unit_name']) ?></small>
                </td>
                <td><?= format_date($contract['end_date']) ?></td>
                <td>
                    <span style="background:#22d3ee; color:white; padding:4px 10px; border-radius:8px; font-size:12px; font-weight:bold;">
                        <?= $contract['days_until_expiry'] ?> يوم
                    </span>
                </td>
                <td style="color:#10b981"><?= number_format($contract['total_amount'], 2) ?></td>
                <td>
                    <a href="index.php?p=contract_view&id=<?= $contract['id'] ?>" class="btn btn-primary btn-sm">
                        <i class="fa-solid fa-eye"></i> عرض
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if (empty($upcomingContracts)): ?>
<div class="card" style="text-align:center; padding:60px;">
    <i class="fa-solid fa-check-circle" style="font-size:60px; color:#10b981; margin-bottom:20px;"></i>
    <h3 style="color:#10b981;">لا توجد عقود تنتهي قريباً</h3>
    <p style="color:var(--muted)">جميع العقود مستقرة حالياً، لا يوجد عقود تنتهي خلال الـ 90 يوماً القادمة</p>
</div>
<?php endif; ?>

<?php elseif ($view === 'roi'): ?>
<!-- ROI Calculator -->

<div class="card" style="margin-bottom:30px; padding:20px;">
    <form method="GET" style="display:flex; gap:15px; align-items:end;">
        <input type="hidden" name="p" value="lease_calendar">
        <input type="hidden" name="view" value="roi">
        
        <div style="flex:1;">
            <label class="inp-label">اختر عقاراً للتحليل</label>
            <select name="roi_property" class="inp">
                <option value="0">جميع العقارات</option>
                <?php foreach ($properties as $prop): ?>
                    <option value="<?= $prop['id'] ?>" <?= $selectedProperty == $prop['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($prop['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-calculator"></i> حساب ROI</button>
    </form>
</div>

<div class="card" style="margin-bottom:30px; background:linear-gradient(135deg, rgba(99,102,241,0.1), rgba(168,85,247,0.1)); border:2px solid var(--primary);">
    <h3 style="margin-top:0;"><i class="fa-solid fa-info-circle"></i> كيف يتم حساب العائد على الاستثمار (ROI)؟</h3>
    <div style="display:grid; gap:15px; font-size:14px; color:var(--muted);">
        <div style="background:var(--input-bg); padding:15px; border-radius:12px;">
            <strong style="color:var(--primary)">صافي الدخل السنوي</strong> = إجمالي المحصل - تكاليف الصيانة
        </div>
        <div style="background:var(--input-bg); padding:15px; border-radius:12px;">
            <strong style="color:var(--primary)">القيمة التقديرية للعقار</strong> = القيمة السنوية × 12 سنة (معامل بسيط)
        </div>
        <div style="background:var(--input-bg); padding:15px; border-radius:12px;">
            <strong style="color:var(--primary)">نسبة ROI</strong> = (صافي الدخل السنوي ÷ القيمة التقديرية) × 100
        </div>
    </div>
</div>

<?php if (empty($roiCalculations)): ?>
<div class="card" style="text-align:center; padding:40px; color:#666">
    لا توجد بيانات كافية لحساب العائد على الاستثمار. تأكد من وجود عقارات ودفعات محصلة.
</div>
<?php else: ?>
<div class="card" style="margin-bottom:30px;">
    <h3 style="margin-top:0;"><i class="fa-solid fa-chart-line"></i> تحليل العائد على الاستثمار</h3>
    <table>
        <thead>
            <tr>
                <th>العقار</th>
                <th>الوحدات</th>
                <th>نسبة الإشغال</th>
                <th>الدخل السنوي</th>
                <th>تكاليف الصيانة</th>
                <th>صافي الدخل</th>
                <th>القيمة التقديرية</th>
                <th>ROI %</th>
                <th>التقييم</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($roiCalculations as $property): 
                $roiColor = '#10b981';
                $roiLabel = 'ممتاز';
                if ($property['roi_percentage'] < 3) {
                    $roiColor = '#ef4444';
                    $roiLabel = 'ضعيف';
                } elseif ($property['roi_percentage'] < 6) {
                    $roiColor = '#f59e0b';
                    $roiLabel = 'متوسط';
                } elseif ($property['roi_percentage'] < 10) {
                    $roiColor = '#22d3ee';
                    $roiLabel = 'جيد';
                }
            ?>
            <tr>
                <td style="font-weight:bold"><?= htmlspecialchars($property['property_name']) ?></td>
                <td><?= $property['rented_units'] ?> / <?= $property['total_units'] ?></td>
                <td>
                    <span style="background:<?= $property['occupancy_rate'] >= 80 ? '#10b981' : ($property['occupancy_rate'] >= 50 ? '#f59e0b' : '#ef4444') ?>; color:white; padding:4px 10px; border-radius:8px; font-size:12px;">
                        <?= $property['occupancy_rate'] ?>%
                    </span>
                </td>
                <td style="color:#10b981"><?= number_format($property['total_collected'], 2) ?></td>
                <td style="color:#ef4444"><?= number_format($property['maintenance_costs'], 2) ?></td>
                <td style="color:#22d3ee; font-weight:bold"><?= number_format($property['net_annual_income'], 2) ?></td>
                <td><?= number_format($property['estimated_value'], 2) ?></td>
                <td>
                    <span style="background:<?= $roiColor ?>; color:white; padding:6px 12px; border-radius:8px; font-size:14px; font-weight:bold;">
                        <?= $property['roi_percentage'] ?>%
                    </span>
                </td>
                <td>
                    <span style="color:<?= $roiColor ?>; font-weight:bold;">
                        <?= $roiLabel ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap:20px; margin-bottom:30px;">
    <?php 
    $totalRevenue = array_sum(array_column($roiCalculations, 'total_collected'));
    $totalMaintenance = array_sum(array_column($roiCalculations, 'maintenance_costs'));
    $totalNetIncome = $totalRevenue - $totalMaintenance;
    $avgROI = count($roiCalculations) > 0 ? round(array_sum(array_column($roiCalculations, 'roi_percentage')) / count($roiCalculations), 2) : 0;
    ?>
    
    <div class="card" style="padding:20px;">
        <div style="font-size:12px; color:var(--muted)">إجمالي الدخل السنوي</div>
        <div style="font-size:28px; font-weight:800; margin-top:8px; color:#10b981;"><?= number_format($totalRevenue, 2) ?></div>
        <div style="font-size:12px; color:#94a3b8; margin-top:6px;">جميع العقارات</div>
    </div>
    
    <div class="card" style="padding:20px;">
        <div style="font-size:12px; color:var(--muted)">تكاليف الصيانة السنوية</div>
        <div style="font-size:28px; font-weight:800; margin-top:8px; color:#ef4444;"><?= number_format($totalMaintenance, 2) ?></div>
        <div style="font-size:12px; color:#94a3b8; margin-top:6px;">آخر 12 شهراً</div>
    </div>
    
    <div class="card" style="padding:20px;">
        <div style="font-size:12px; color:var(--muted)">صافي الدخل السنوي</div>
        <div style="font-size:28px; font-weight:800; margin-top:8px; color:#22d3ee;"><?= number_format($totalNetIncome, 2) ?></div>
        <div style="font-size:12px; color:#94a3b8; margin-top:6px;">بعد المصروفات</div>
    </div>
    
    <div class="card" style="padding:20px;">
        <div style="font-size:12px; color:var(--muted)">متوسط ROI</div>
        <div style="font-size:28px; font-weight:800; margin-top:8px; color:var(--primary);"><?= $avgROI ?>%</div>
        <div style="font-size:12px; color:#94a3b8; margin-top:6px;">أداء المحفظة</div>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>
