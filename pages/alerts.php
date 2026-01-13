<div class="card">
    <h2 style="margin-bottom:30px; border-bottom:1px solid #222; padding-bottom:15px">🔔 مركز التنبيهات</h2>

    <?php $tenantNameColumn = tenant_name_column($pdo); ?>

    <h4 style="color:#ef4444; margin:20px 0 10px"><i class="fa-solid fa-circle-exclamation"></i> دفعات متأخرة السداد</h4>
    <table>
        <thead><tr><th>المستأجر</th><th>رقم العقد</th><th>المبلغ المستحق</th><th>تاريخ الاستحقاق</th><th>إجراء</th></tr></thead>
        <tbody>
            <?php
            $late = $pdo->query("SELECT p.*, t.$tenantNameColumn AS full_name, t.phone, c.id as cid FROM payments p JOIN contracts c ON p.contract_id=c.id JOIN tenants t ON c.tenant_id=t.id WHERE p.status!='paid' AND p.due_date < CURRENT_DATE");
            if($late->rowCount() == 0) echo "<tr><td colspan='5' style='text-align:center; color:#666'>لا توجد دفعات متأخرة</td></tr>";
            while($r=$late->fetch()): ?>
            <tr>
                <td style="font-weight:bold"><?= $r['full_name'] ?></td>
                <td>#<?= $r['cid'] ?></td>
                <td style="color:#ef4444"><?= number_format($r['amount']) ?></td>
                <td><?= $r['due_date'] ?></td>
                <td>
                    <a href="https://wa.me/<?= $r['phone'] ?>?text=عزيزي <?= $r['full_name'] ?> نرجو سداد الدفعة المستحقة" target="_blank" class="btn btn-primary" style="padding:8px 15px; font-size:12px; background:#25D366"><i class="fa-brands fa-whatsapp"></i> تذكير</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <h4 style="color:#f59e0b; margin:40px 0 10px"><i class="fa-solid fa-clock"></i> عقود تنتهي خلال 30 يوم</h4>
    <table>
        <thead><tr><th>رقم العقد</th><th>المستأجر</th><th>تاريخ الانتهاء</th><th>الحالة</th></tr></thead>
        <tbody>
            <?php
            $exp = $pdo->query("SELECT c.*, t.$tenantNameColumn AS full_name FROM contracts c JOIN tenants t ON c.tenant_id=t.id WHERE c.end_date BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY)");
            if($exp->rowCount() == 0) echo "<tr><td colspan='4' style='text-align:center; color:#666'>لا توجد عقود تنتهي قريباً</td></tr>";
            while($r=$exp->fetch()): ?>
            <tr>
                <td>#<?= $r['id'] ?></td>
                <td><?= $r['full_name'] ?></td>
                <td><?= $r['end_date'] ?></td>
                <td><span style="color:#f59e0b; background:rgba(245,158,11,0.1); padding:5px 10px; border-radius:10px">ينتهي قريباً</span></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
