<?php
// معالجة الحفظ
$hasPriority = isset($pdo) ? table_has_column($pdo, 'maintenance', 'priority') : false;
$hasAnalysis = isset($pdo) ? table_has_column($pdo, 'maintenance', 'ai_analysis') : false;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_maint'])) {
    $u = $pdo->query("SELECT property_id FROM units WHERE id=".$_POST['uid'])->fetch();
    $pid = $u ? $u['property_id'] : 0;
    
    try {
        $analysis = isset($AI) ? $AI->analyzeMaintenance($_POST['desc'], (float) $_POST['cost']) : null;
        $columns = "property_id, unit_id, vendor_id, description, cost, request_date, status";
        $placeholders = "?,?,?,?,?, CURDATE(), 'pending'";
        $params = [$pid, $_POST['uid'], $_POST['vid'], $_POST['desc'], $_POST['cost']];

        if ($hasPriority) {
            $columns .= ", priority";
            $placeholders .= ", ?";
            $params[] = $analysis['priority'] ?? null;
        }
        if ($hasAnalysis) {
            $columns .= ", ai_analysis";
            $placeholders .= ", ?";
            $params[] = $analysis['analysis'] ?? null;
        }

        $pdo->prepare("INSERT INTO maintenance ($columns) VALUES ($placeholders)")->execute($params);
        if (isset($pdo)) {
            $priorityNote = $analysis['priority'] ?? null;
            $logMessage = $priorityNote
                ? "إضافة طلب صيانة للوحدة #{$_POST['uid']} بالأولوية {$priorityNote}"
                : "إضافة طلب صيانة للوحدة #{$_POST['uid']} بدون تحليل ذكي";
            log_activity($pdo, $logMessage, 'maintenance');
        }
        echo "<script>window.location='index.php?p=maintenance';</script>";
        exit;
    } catch(Exception $e) {
        echo "<div style='background:red; padding:10px; color:white'>خطأ: ".$e->getMessage()."</div>";
    }
}

// تحديد العرض (جدول أو فورم)
$action = isset($_GET['act']) ? $_GET['act'] : 'list';
?>

<?php if ($action == 'add'): ?>
<div class="card" style="max-width: 600px; margin: 0 auto;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #333; padding-bottom:15px">
        <h3>تسجيل طلب صيانة جديد</h3>
        <a href="index.php?p=maintenance" class="btn btn-dark">رجوع <i class="fa-solid fa-arrow-left"></i></a>
    </div>

    <form method="POST" action="index.php?p=maintenance">
        <input type="hidden" name="save_maint" value="1">
        
        <div style="margin-bottom:15px">
            <label style="color:#aaa; display:block; margin-bottom:5px">الوحدة المتضررة</label>
            <select name="uid" class="inp" style="width:100%; padding:10px; background:#333; color:white; border:1px solid #555" required>
                <option value="">-- اختر الوحدة --</option>
                <?php $us=$pdo->query("SELECT * FROM units"); while($u=$us->fetch()) echo "<option value='{$u['id']}'>{$u['unit_name']}</option>"; ?>
            </select>
        </div>
        
        <div style="margin-bottom:15px">
            <label style="color:#aaa; display:block; margin-bottom:5px">المقاول (اختياري)</label>
            <select name="vid" class="inp" style="width:100%; padding:10px; background:#333; color:white; border:1px solid #555">
                <option value="0">-- اختر --</option>
                <?php $vs=$pdo->query("SELECT * FROM vendors"); while($v=$vs->fetch()) echo "<option value='{$v['id']}'>{$v['name']}</option>"; ?>
            </select>
        </div>
        
        <div style="margin-bottom:15px">
            <label style="color:#aaa; display:block; margin-bottom:5px">وصف المشكلة</label>
            <textarea name="desc" class="inp" style="width:100%; padding:10px; background:#333; color:white; border:1px solid #555; height:100px" required></textarea>
        </div>
        
        <div style="margin-bottom:25px">
            <label style="color:#aaa; display:block; margin-bottom:5px">التكلفة التقديرية (ريال)</label>
            <input type="number" name="cost" class="inp" style="width:100%; padding:10px; background:#333; color:white; border:1px solid #555">
        </div>
        
        <button class="btn btn-primary" style="width:100%; justify-content:center; padding:12px">حفظ الطلب</button>
    </form>
</div>

<?php else: ?>
<?php
    $summary = $pdo->query("SELECT COUNT(*) AS total, SUM(status='pending') AS pending, SUM(status!='pending') AS completed, AVG(cost) AS avg_cost FROM maintenance")->fetch();
    $analysisCount = 0;
    if ($hasAnalysis) {
        $analysisCount = (int) $pdo->query("SELECT COUNT(*) FROM maintenance WHERE ai_analysis IS NOT NULL AND ai_analysis != ''")->fetchColumn();
    }
    $priorityCounts = [
        'عالية' => 0,
        'متوسطة' => 0,
        'منخفضة' => 0,
    ];
    if ($hasPriority) {
        $priorityRows = $pdo->query("SELECT priority, COUNT(*) AS total FROM maintenance WHERE priority IS NOT NULL AND priority != '' GROUP BY priority")->fetchAll();
        foreach ($priorityRows as $row) {
            $priorityCounts[$row['priority']] = (int) $row['total'];
        }
    }
?>
<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; gap:20px; flex-wrap:wrap">
        <div>
            <p style="margin:0; color:#94a3b8; font-size:13px">لوحة متابعة الصيانة الذكية</p>
            <h3 style="margin:6px 0 0">🛠️ سجلات الصيانة</h3>
        </div>

        <a href="index.php?p=maintenance&act=add" id="openMaintModal" class="btn btn-primary" style="text-decoration:none; display:inline-flex; align-items:center; gap:8px">
            <i class="fa-solid fa-plus"></i> تسجيل طلب جديد
        </a>
    </div>

    <div class="maintenance-summary">
        <div class="summary-card">
            <p>إجمالي الطلبات</p>
            <h4><?= (int) $summary['total'] ?></h4>
            <span>نشاط اليوم الذكي</span>
        </div>
        <div class="summary-card summary-card--warn">
            <p>طلبات معلّقة</p>
            <h4><?= (int) $summary['pending'] ?></h4>
            <span>تحتاج متابعة فورية</span>
        </div>
        <div class="summary-card summary-card--success">
            <p>طلبات منجزة</p>
            <h4><?= (int) $summary['completed'] ?></h4>
            <span>تحسين جودة الخدمة</span>
        </div>
        <div class="summary-card summary-card--info">
            <p>تحليل ذكي</p>
            <h4><?= $hasAnalysis ? $analysisCount : 0 ?></h4>
            <span>تقييم أولويات آلي</span>
        </div>
    </div>

    <div class="maintenance-toolbar">
        <div class="toolbar-left">
            <span class="smart-tag"><i class="fa-solid fa-sparkles"></i> توصية النظام</span>
            <p>متوسط التكلفة: <?= $summary['avg_cost'] ? number_format((float) $summary['avg_cost'], 2) : '0.00' ?> ريال</p>
        </div>
        <?php if ($hasPriority): ?>
        <div class="priority-badges">
            <span class="priority-chip priority-chip--high">عالية: <?= $priorityCounts['عالية'] ?></span>
            <span class="priority-chip priority-chip--mid">متوسطة: <?= $priorityCounts['متوسطة'] ?></span>
            <span class="priority-chip priority-chip--low">منخفضة: <?= $priorityCounts['منخفضة'] ?></span>
        </div>
        <?php endif; ?>
    </div>

    <div id="maintModal" class="modal-backdrop" style="display:none">
        <div class="modal-card modal-card--glow">
            <div class="modal-header">
                <div>
                    <p class="modal-kicker">طلب جديد</p>
                    <h3>تسجيل طلب صيانة جديد</h3>
                </div>
                <button type="button" id="closeMaintModal" class="btn btn-dark">إغلاق <i class="fa-solid fa-xmark"></i></button>
            </div>

            <form method="POST" action="index.php?p=maintenance">
                <input type="hidden" name="save_maint" value="1">
                
                <div style="margin-bottom:15px">
                    <label style="color:#aaa; display:block; margin-bottom:5px">الوحدة المتضررة</label>
                    <select name="uid" class="inp modal-input" style="width:100%; padding:10px; background:#222; color:white; border:1px solid #3a3a3a" required>
                        <option value="">-- اختر الوحدة --</option>
                        <?php $us=$pdo->query("SELECT * FROM units"); while($u=$us->fetch()) echo "<option value='{$u['id']}'>{$u['unit_name']}</option>"; ?>
                    </select>
                </div>
                
                <div style="margin-bottom:15px">
                    <label style="color:#aaa; display:block; margin-bottom:5px">المقاول (اختياري)</label>
                    <select name="vid" class="inp modal-input" style="width:100%; padding:10px; background:#222; color:white; border:1px solid #3a3a3a">
                        <option value="0">-- اختر --</option>
                        <?php $vs=$pdo->query("SELECT * FROM vendors"); while($v=$vs->fetch()) echo "<option value='{$v['id']}'>{$v['name']}</option>"; ?>
                    </select>
                </div>
                
                <div style="margin-bottom:15px">
                    <label style="color:#aaa; display:block; margin-bottom:5px">وصف المشكلة</label>
                    <textarea name="desc" class="inp modal-input" style="width:100%; padding:10px; background:#222; color:white; border:1px solid #3a3a3a; height:100px" required></textarea>
                </div>
                
                <div style="margin-bottom:25px">
                    <label style="color:#aaa; display:block; margin-bottom:5px">التكلفة التقديرية (ريال)</label>
                    <input type="number" name="cost" class="inp modal-input" style="width:100%; padding:10px; background:#222; color:white; border:1px solid #3a3a3a">
                </div>
                
                <button class="btn btn-primary modal-submit" style="width:100%; justify-content:center; padding:12px">حفظ الطلب</button>
            </form>
        </div>
    </div>
    
    <table class="maintenance-table">
        <thead>
            <tr>
                <th style="padding:15px">#</th>
                <th style="padding:15px">الوحدة</th>
                <th style="padding:15px">الوصف</th>
                <th style="padding:15px">التكلفة</th>
                <th style="padding:15px">التاريخ</th>
                <?php if ($hasPriority): ?>
                    <th style="padding:15px">الأولوية الذكية</th>
                <?php endif; ?>
                <th style="padding:15px">المقاول</th>
                <?php if ($hasAnalysis): ?>
                    <th style="padding:15px">تحليل ذكي</th>
                <?php endif; ?>
                <th style="padding:15px">الحالة</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $reqs = $pdo->query("SELECT m.*, u.unit_name, v.name as vname FROM maintenance m JOIN units u ON m.unit_id=u.id LEFT JOIN vendors v ON m.vendor_id=v.id ORDER BY m.id DESC");
            while($r = $reqs->fetch()): 
            ?>
            <tr>
                <td data-label="#" style="padding:15px"><?= $r['id'] ?></td>
                <td data-label="الوحدة" style="padding:15px">
                    <strong><?= $r['unit_name'] ?></strong>
                    <div class="row-meta">رقم الطلب #<?= $r['id'] ?></div>
                </td>
                <td data-label="الوصف" style="padding:15px">
                    <?= $r['description'] ?>
                    <div class="row-meta">آخر تحديث: <?= $r['request_date'] ?></div>
                </td>
                <td data-label="التكلفة" style="padding:15px">
                    <span class="cost-chip"><?= $r['cost'] ? number_format((float) $r['cost'], 2) : '—' ?> ريال</span>
                </td>
                <td data-label="التاريخ" style="padding:15px"><?= $r['request_date'] ?></td>
                <?php if ($hasPriority): ?>
                <td data-label="الأولوية الذكية" style="padding:15px">
                    <?php
                        $priorityValue = $r['priority'] ? htmlspecialchars($r['priority']) : 'غير محدد';
                        $priorityClass = 'priority-chip--neutral';
                        if ($priorityValue === 'عالية') { $priorityClass = 'priority-chip--high'; }
                        if ($priorityValue === 'متوسطة') { $priorityClass = 'priority-chip--mid'; }
                        if ($priorityValue === 'منخفضة') { $priorityClass = 'priority-chip--low'; }
                    ?>
                    <span class="priority-chip <?= $priorityClass ?>"><?= $priorityValue ?></span>
                </td>
                <?php endif; ?>
                <td data-label="المقاول" style="padding:15px">
                    <?= $r['vname'] ?: '-' ?>
                    <div class="row-meta">جهة التنفيذ</div>
                </td>
                <?php if ($hasAnalysis): ?>
                <td data-label="تحليل ذكي" style="padding:15px">
                    <div class="analysis-box">
                        <span><?= $r['ai_analysis'] ? htmlspecialchars($r['ai_analysis']) : 'لا يوجد تحليل حتى الآن' ?></span>
                        <span class="analysis-pill"><?= $r['ai_analysis'] ? 'تم التحليل' : 'بانتظار البيانات' ?></span>
                    </div>
                </td>
                <?php endif; ?>
                <td data-label="الحالة" style="padding:15px">
                    <span class="status-pill <?= $r['status']=='pending'?'status-pill--pending':'status-pill--done' ?>">
                        <?= $r['status']=='pending' ? 'قيد المعالجة' : 'مكتمل' ?>
                    </span>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
<style>
    .maintenance-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 15px;
        margin-bottom: 18px;
    }
    .summary-card {
        background: linear-gradient(135deg, rgba(30,41,59,0.9), rgba(15,23,42,0.95));
        border: 1px solid rgba(148,163,184,0.2);
        border-radius: 16px;
        padding: 16px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        box-shadow: 0 10px 30px rgba(15,23,42,0.35);
    }
    .summary-card p {
        margin: 0;
        color: #94a3b8;
        font-size: 12px;
    }
    .summary-card h4 {
        margin: 0;
        font-size: 26px;
        color: #f8fafc;
    }
    .summary-card span {
        color: #64748b;
        font-size: 12px;
    }
    .summary-card--warn {
        border-color: rgba(249,115,22,0.4);
        background: linear-gradient(140deg, rgba(249,115,22,0.18), rgba(30,41,59,0.95));
    }
    .summary-card--success {
        border-color: rgba(16,185,129,0.35);
        background: linear-gradient(140deg, rgba(16,185,129,0.18), rgba(30,41,59,0.95));
    }
    .summary-card--info {
        border-color: rgba(129,140,248,0.4);
        background: linear-gradient(140deg, rgba(129,140,248,0.18), rgba(30,41,59,0.95));
    }
    .maintenance-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        padding: 12px 16px;
        background: rgba(15,23,42,0.6);
        border-radius: 14px;
        border: 1px solid rgba(148,163,184,0.15);
        margin-bottom: 18px;
    }
    .toolbar-left {
        display: flex;
        flex-direction: column;
        gap: 4px;
        color: #cbd5f5;
        font-size: 13px;
    }
    .smart-tag {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 999px;
        background: rgba(99,102,241,0.18);
        color: #c7d2fe;
        font-size: 12px;
        font-weight: 600;
    }
    .priority-badges {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    .priority-chip {
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 12px;
        border: 1px solid transparent;
        background: rgba(15,23,42,0.8);
        color: #e2e8f0;
    }
    .priority-chip--high {
        border-color: rgba(239,68,68,0.5);
        color: #fecaca;
        background: rgba(239,68,68,0.12);
    }
    .priority-chip--mid {
        border-color: rgba(250,204,21,0.45);
        color: #fef08a;
        background: rgba(250,204,21,0.12);
    }
    .priority-chip--low {
        border-color: rgba(34,197,94,0.45);
        color: #bbf7d0;
        background: rgba(34,197,94,0.12);
    }
    .priority-chip--neutral {
        border-color: rgba(148,163,184,0.35);
        color: #cbd5f5;
    }
    .maintenance-table {
        width: 100%;
        border-collapse: collapse;
        border-radius: 14px;
        overflow: hidden;
        background: rgba(15,23,42,0.6);
    }
    .maintenance-table thead tr {
        background: rgba(30,41,59,0.8);
        text-align: right;
    }
    .maintenance-table tbody tr {
        border-bottom: 1px solid rgba(51,65,85,0.8);
    }
    .maintenance-table tbody tr:hover {
        background: rgba(30,41,59,0.35);
    }
    .row-meta {
        margin-top: 6px;
        font-size: 11px;
        color: #64748b;
    }
    .cost-chip {
        display: inline-flex;
        padding: 6px 10px;
        border-radius: 10px;
        background: rgba(51,65,85,0.6);
        color: #f8fafc;
        font-size: 12px;
    }
    .analysis-box {
        display: flex;
        flex-direction: column;
        gap: 6px;
        font-size: 12px;
        color: #e2e8f0;
    }
    .analysis-pill {
        align-self: flex-start;
        padding: 4px 10px;
        border-radius: 999px;
        background: rgba(129,140,248,0.2);
        color: #c7d2fe;
        font-size: 11px;
    }
    .status-pill {
        display: inline-flex;
        padding: 6px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
    }
    .status-pill--pending {
        background: rgba(249,115,22,0.2);
        color: #fdba74;
        border: 1px solid rgba(249,115,22,0.4);
    }
    .status-pill--done {
        background: rgba(16,185,129,0.2);
        color: #6ee7b7;
        border: 1px solid rgba(16,185,129,0.4);
    }
    @media (max-width: 900px) {
        .maintenance-table thead {
            display: none;
        }
        .maintenance-table tbody tr {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            padding: 12px;
        }
        .maintenance-table td {
            padding: 8px 0 !important;
        }
        .maintenance-table td::before {
            content: attr(data-label);
            display: block;
            font-size: 11px;
            color: #94a3b8;
            margin-bottom: 4px;
        }
    }
    .modal-backdrop {
        position: fixed;
        inset: 0;
        background: radial-gradient(circle at top, rgba(54, 99, 255, 0.16), rgba(0,0,0,0.75));
        backdrop-filter: blur(4px);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 999;
        padding: 20px;
    }
    .modal-card {
        width: min(650px, 100%);
        background: linear-gradient(160deg, #1e1f24 0%, #171717 100%);
        border: 1px solid rgba(255,255,255,0.06);
        border-radius: 18px;
        padding: 26px;
        box-shadow: 0 25px 60px rgba(0,0,0,0.45);
        animation: modalFadeIn 0.25s ease-out;
    }
    .modal-card--glow {
        position: relative;
        overflow: hidden;
    }
    .modal-card--glow::before {
        content: "";
        position: absolute;
        inset: -40% -40% auto auto;
        width: 200px;
        height: 200px;
        background: radial-gradient(circle, rgba(99,102,241,0.35), transparent 70%);
        pointer-events: none;
    }
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        border-bottom: 1px solid rgba(255,255,255,0.08);
        padding-bottom: 15px;
        gap: 15px;
    }
    .modal-kicker {
        margin: 0 0 6px;
        color: #a5b4fc;
        font-size: 12px;
        letter-spacing: 1px;
        text-transform: uppercase;
    }
    .modal-input:focus {
        border-color: #6366f1;
        box-shadow: 0 0 0 3px rgba(99,102,241,0.15);
        outline: none;
    }
    .modal-submit {
        box-shadow: 0 12px 24px rgba(37,99,235,0.25);
    }
    @keyframes modalFadeIn {
        from { opacity: 0; transform: translateY(12px) scale(0.98); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }
</style>
<script>
    const maintModal = document.getElementById('maintModal');
    const openMaintModal = document.getElementById('openMaintModal');
    const closeMaintModal = document.getElementById('closeMaintModal');
    const closeMaintModalHandler = () => {
        maintModal.style.display = 'none';
    };

    if (maintModal && openMaintModal && closeMaintModal) {
        openMaintModal.addEventListener('click', (event) => {
            event.preventDefault();
            maintModal.style.display = 'flex';
        });

        closeMaintModal.addEventListener('click', closeMaintModalHandler);

        maintModal.addEventListener('click', (event) => {
            if (event.target === maintModal) {
                closeMaintModalHandler();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && maintModal.style.display === 'flex') {
                closeMaintModalHandler();
            }
        });
    }

</script>
<?php endif; ?>
