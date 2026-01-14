<?php
// --- معالجة البيانات (حفظ / حذف) ---
if (isset($_POST['delete_id'])) {
    $pdo->prepare("DELETE FROM vendors WHERE id=?")->execute([$_POST['delete_id']]);
    echo "<script>window.location='index.php?p=vendors';</script>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_vendor'])) {
    if(!empty($_POST['vid'])){
        $stmt = $pdo->prepare("UPDATE vendors SET name=?, service_type=?, phone=? WHERE id=?");
        $stmt->execute([$_POST['name'], $_POST['type'], $_POST['phone'], $_POST['vid']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO vendors (name, service_type, phone) VALUES (?,?,?)");
        $stmt->execute([$_POST['name'], $_POST['type'], $_POST['phone']]);
    }
    echo "<script>window.location='index.php?p=vendors';</script>";
    exit;
}

// تحديد هل نحن في وضع "عرض الجدول" أم "تعبئة البيانات"
$action = isset($_GET['act']) ? $_GET['act'] : 'list';
?>

<?php if ($action == 'add' || $action == 'edit'): 
    $e_id = ''; $e_name = ''; $e_type = ''; $e_phone = '';
    $title = 'إضافة مقاول جديد';
    
    if($action == 'edit' && isset($_GET['id'])) {
        $e = $pdo->query("SELECT * FROM vendors WHERE id=".$_GET['id'])->fetch();
        if($e) {
            $e_id = $e['id']; $e_name = $e['name']; $e_type = $e['service_type']; $e_phone = $e['phone'];
            $title = 'تعديل بيانات المقاول';
        }
    }
?>
<div class="card" style="max-width: 600px; margin: 0 auto;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #333; padding-bottom:15px">
        <h3><?= $title ?></h3>
        <a href="index.php?p=vendors" class="btn btn-dark">رجوع <i class="fa-solid fa-arrow-left"></i></a>
    </div>

    <form method="POST" action="index.php?p=vendors">
        <input type="hidden" name="save_vendor" value="1">
        <input type="hidden" name="vid" value="<?= $e_id ?>">
        
        <div style="margin-bottom:15px">
            <label style="color:#aaa; display:block; margin-bottom:5px">الاسم</label>
            <input type="text" name="name" value="<?= $e_name ?>" class="inp" style="width:100%; padding:10px; background:#333; color:white; border:1px solid #555" required>
        </div>
        
        <div style="margin-bottom:15px">
            <label style="color:#aaa; display:block; margin-bottom:5px">التخصص</label>
            <input type="text" name="type" value="<?= $e_type ?>" class="inp" style="width:100%; padding:10px; background:#333; color:white; border:1px solid #555" required>
        </div>
        
        <div style="margin-bottom:25px">
            <label style="color:#aaa; display:block; margin-bottom:5px">الجوال</label>
            <input type="text" name="phone" value="<?= $e_phone ?>" class="inp" style="width:100%; padding:10px; background:#333; color:white; border:1px solid #555" required>
        </div>
        
        <button class="btn btn-primary" style="width:100%; justify-content:center; padding:12px">حفظ البيانات</button>
    </form>
</div>

<?php else: ?>
<?php
    $vendorSummary = $pdo->query("SELECT COUNT(*) AS total, COUNT(DISTINCT service_type) AS types FROM vendors")->fetch();
    $activeVendors = $pdo->query("SELECT COUNT(DISTINCT vendor_id) FROM maintenance WHERE vendor_id IS NOT NULL AND vendor_id != 0")->fetchColumn();
?>
<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; gap:20px; flex-wrap:wrap">
        <div>
            <p style="margin:0; color:#94a3b8; font-size:13px">شبكة المقاولين الذكية</p>
            <h3 style="margin:6px 0 0"><i class="fa-solid fa-people-carry-box" style="margin-left:8px;color:var(--primary)"></i> إدارة المقاولين</h3>
        </div>

        <a href="index.php?p=vendors&act=add" id="openVendorModal" class="btn btn-primary" style="text-decoration:none; display:inline-flex; align-items:center; gap:8px">
            <i class="fa-solid fa-user-plus"></i> إضافة مقاول
        </a>
    </div>

    <div class="vendor-summary">
        <div class="summary-card">
            <p>إجمالي المقاولين</p>
            <h4><?= (int) $vendorSummary['total'] ?></h4>
            <span>شبكة الموردين</span>
        </div>
        <div class="summary-card summary-card--info">
            <p>التخصصات المتاحة</p>
            <h4><?= (int) $vendorSummary['types'] ?></h4>
            <span>تغطية شاملة للخدمات</span>
        </div>
        <div class="summary-card summary-card--success">
            <p>مقاولون نشطون</p>
            <h4><?= (int) $activeVendors ?></h4>
            <span>تم تنفيذ أعمال صيانة</span>
        </div>
    </div>

    <div id="vendorModal" class="modal-backdrop" style="display:none">
        <div class="modal-card modal-card--glow">
            <div class="modal-header">
                <div>
                    <p class="modal-kicker">مقاول جديد</p>
                    <h3>إضافة مقاول جديد</h3>
                </div>
                <button type="button" id="closeVendorModal" class="btn btn-dark">إغلاق <i class="fa-solid fa-xmark"></i></button>
            </div>

            <form method="POST" action="index.php?p=vendors">
                <input type="hidden" name="save_vendor" value="1">
                <input type="hidden" name="vid" value="">
                
                <div style="margin-bottom:15px">
                    <label style="color:#aaa; display:block; margin-bottom:5px">الاسم</label>
                    <input type="text" name="name" class="inp modal-input" style="width:100%; padding:10px; background:#222; color:white; border:1px solid #3a3a3a" required>
                </div>
                
                <div style="margin-bottom:15px">
                    <label style="color:#aaa; display:block; margin-bottom:5px">التخصص</label>
                    <input type="text" name="type" class="inp modal-input" style="width:100%; padding:10px; background:#222; color:white; border:1px solid #3a3a3a" required>
                </div>
                
                <div style="margin-bottom:25px">
                    <label style="color:#aaa; display:block; margin-bottom:5px">الجوال</label>
                    <input type="text" name="phone" class="inp modal-input" style="width:100%; padding:10px; background:#222; color:white; border:1px solid #3a3a3a" required>
                </div>
                
                <button class="btn btn-primary modal-submit" style="width:100%; justify-content:center; padding:12px">حفظ البيانات</button>
            </form>
        </div>
    </div>
    
    <table class="vendor-table">
        <thead>
            <tr>
                <th style="padding:15px">الاسم</th>
                <th style="padding:15px">التخصص</th>
                <th style="padding:15px">الجوال</th>
                <th style="padding:15px">الخبرة</th>
                <th style="padding:15px">إجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php $vendors = $pdo->query("SELECT v.*, COUNT(m.id) AS jobs FROM vendors v LEFT JOIN maintenance m ON m.vendor_id = v.id GROUP BY v.id ORDER BY v.id DESC"); while($v = $vendors->fetch()): ?>
            <?php
                $jobs = (int) $v['jobs'];
                $experienceLabel = $jobs >= 10 ? 'خبير' : ($jobs >= 5 ? 'متوسط' : 'جديد');
                $experienceClass = $jobs >= 10 ? 'exp-pill--high' : ($jobs >= 5 ? 'exp-pill--mid' : 'exp-pill--low');
            ?>
            <tr>
                <td data-label="الاسم" style="padding:15px">
                    <strong><?= $v['name'] ?></strong>
                    <div class="row-meta">معرف #<?= $v['id'] ?></div>
                </td>
                <td data-label="التخصص" style="padding:15px">
                    <span class="type-chip"><?= $v['service_type'] ?></span>
                </td>
                <td data-label="الجوال" style="padding:15px">
                    <span class="contact-chip"><i class="fa-solid fa-phone"></i> <?= $v['phone'] ?></span>
                </td>
                <td data-label="الخبرة" style="padding:15px">
                    <div class="exp-box">
                        <span class="exp-pill <?= $experienceClass ?>"><?= $experienceLabel ?></span>
                        <div class="row-meta"><?= $jobs ?> طلب صيانة</div>
                    </div>
                </td>
                <td data-label="إجراءات" style="padding:15px; display:flex; gap:10px">
                    <a href="index.php?p=vendors&act=edit&id=<?= $v['id'] ?>" class="btn btn-dark btn-sm"><i class="fa-solid fa-pen"></i></a>
                    
                    <form method="POST" onsubmit="return confirm('هل أنت متأكد من الحذف؟')" style="margin:0">
                        <input type="hidden" name="delete_id" value="<?= $v['id'] ?>">
                        <button class="btn btn-danger btn-sm"><i class="fa-solid fa-trash"></i></button>
                    </form>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
<style>
    .vendor-summary {
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
    .summary-card--success {
        border-color: rgba(16,185,129,0.35);
        background: linear-gradient(140deg, rgba(16,185,129,0.18), rgba(30,41,59,0.95));
    }
    .summary-card--info {
        border-color: rgba(56,189,248,0.35);
        background: linear-gradient(140deg, rgba(56,189,248,0.18), rgba(30,41,59,0.95));
    }
    .vendor-table {
        width: 100%;
        border-collapse: collapse;
        border-radius: 14px;
        overflow: hidden;
        background: rgba(15,23,42,0.6);
    }
    .vendor-table thead tr {
        background: rgba(30,41,59,0.8);
        text-align: right;
    }
    .vendor-table tbody tr {
        border-bottom: 1px solid rgba(51,65,85,0.8);
    }
    .vendor-table tbody tr:hover {
        background: rgba(30,41,59,0.35);
    }
    .type-chip {
        display: inline-flex;
        padding: 6px 10px;
        border-radius: 999px;
        background: rgba(56,189,248,0.2);
        color: #bae6fd;
        font-size: 12px;
        border: 1px solid rgba(56,189,248,0.4);
    }
    .contact-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 10px;
        border-radius: 10px;
        background: rgba(51,65,85,0.6);
        color: #e2e8f0;
        font-size: 12px;
    }
    .exp-box {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .exp-pill {
        display: inline-flex;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
        border: 1px solid transparent;
    }
    .exp-pill--high {
        background: rgba(16,185,129,0.2);
        border-color: rgba(16,185,129,0.4);
        color: #6ee7b7;
    }
    .exp-pill--mid {
        background: rgba(250,204,21,0.2);
        border-color: rgba(250,204,21,0.4);
        color: #fde68a;
    }
    .exp-pill--low {
        background: rgba(148,163,184,0.2);
        border-color: rgba(148,163,184,0.4);
        color: #e2e8f0;
    }
    @media (max-width: 900px) {
        .vendor-table thead {
            display: none;
        }
        .vendor-table tbody tr {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            padding: 12px;
        }
        .vendor-table td {
            padding: 8px 0 !important;
        }
        .vendor-table td::before {
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
        background: radial-gradient(circle at top, rgba(56, 189, 248, 0.18), rgba(0,0,0,0.75));
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
        background: radial-gradient(circle, rgba(14,165,233,0.35), transparent 70%);
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
        color: #7dd3fc;
        font-size: 12px;
        letter-spacing: 1px;
        text-transform: uppercase;
    }
    .modal-input:focus {
        border-color: #38bdf8;
        box-shadow: 0 0 0 3px rgba(56,189,248,0.18);
        outline: none;
    }
    .modal-submit {
        box-shadow: 0 12px 24px rgba(14,116,144,0.25);
    }
    @keyframes modalFadeIn {
        from { opacity: 0; transform: translateY(12px) scale(0.98); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }
</style>
<script>
    const vendorModal = document.getElementById('vendorModal');
    const openVendorModal = document.getElementById('openVendorModal');
    const closeVendorModal = document.getElementById('closeVendorModal');
    const closeVendorModalHandler = () => {
        vendorModal.style.display = 'none';
    };

    if (vendorModal && openVendorModal && closeVendorModal) {
        openVendorModal.addEventListener('click', (event) => {
            event.preventDefault();
            vendorModal.style.display = 'flex';
        });

        closeVendorModal.addEventListener('click', closeVendorModalHandler);

        vendorModal.addEventListener('click', (event) => {
            if (event.target === vendorModal) {
                closeVendorModalHandler();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && vendorModal.style.display === 'flex') {
                closeVendorModalHandler();
            }
        });
    }

</script>
<?php endif; ?>
