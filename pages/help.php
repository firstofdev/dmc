<?php
/**
 * صفحة المساعدة والدليل الشامل
 * دليل استخدام كامل لأصحاب العقارات
 */
require 'config.php';
if(!isset($_SESSION['uid'])) { header("Location: login.php"); exit; }

include 'includes/header.php';
?>

<style>
.help-container { max-width: 1000px; margin: 0 auto; }
.help-header { 
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 40px;
    border-radius: 20px;
    text-align: center;
    color: white;
    margin-bottom: 30px;
}
.help-section {
    background: var(--card);
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 20px;
    border: 1px solid var(--border);
}
.help-section h3 {
    color: var(--primary);
    margin-top: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}
.help-step {
    background: var(--input-bg);
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 10px;
    border-right: 3px solid var(--primary);
}
.help-step-number {
    display: inline-block;
    background: var(--primary);
    color: white;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    text-align: center;
    line-height: 28px;
    font-weight: bold;
    margin-left: 10px;
}
.help-tip {
    background: rgba(99, 102, 241, 0.1);
    padding: 12px;
    border-radius: 8px;
    margin-top: 10px;
    font-size: 14px;
    color: var(--muted);
}
.feature-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
    margin-top: 15px;
}
.feature-card {
    background: var(--input-bg);
    padding: 15px;
    border-radius: 10px;
    text-align: center;
}
.feature-icon {
    font-size: 32px;
    margin-bottom: 10px;
    color: var(--primary);
}
</style>

<div class="help-container">
    <div class="help-header">
        <h1><i class="fa-solid fa-book-open"></i> دليل نظام إدارة العقارات الذكي</h1>
        <p>دليل شامل لمساعدتك على استخدام جميع مميزات النظام بسهولة</p>
    </div>

    <div class="help-section">
        <h3><i class="fa-solid fa-rocket"></i> البداية السريعة</h3>
        <p>اتبع هذه الخطوات لبدء استخدام النظام:</p>
        
        <div class="help-step">
            <span class="help-step-number">1</span>
            <strong>إضافة العقارات:</strong> ابدأ بإضافة العقارات التي تملكها من صفحة "إدارة العقارات"
        </div>
        
        <div class="help-step">
            <span class="help-step-number">2</span>
            <strong>تسجيل الوحدات:</strong> أضف الوحدات السكنية/التجارية لكل عقار مع تحديد الأسعار
        </div>
        
        <div class="help-step">
            <span class="help-step-number">3</span>
            <strong>إضافة المستأجرين:</strong> سجل بيانات المستأجرين مع إمكانية مسح الهوية تلقائياً
        </div>
        
        <div class="help-step">
            <span class="help-step-number">4</span>
            <strong>إنشاء العقود:</strong> أنشئ عقود الإيجار وحدد شروط الدفع
        </div>
        
        <div class="help-step">
            <span class="help-step-number">5</span>
            <strong>متابعة التحصيل:</strong> راقب الدفعات من لوحة القيادة وأرسل التذكيرات
        </div>
        
        <div class="help-tip">
            <i class="fa-solid fa-lightbulb"></i> <strong>نصيحة:</strong> 
            ابدأ بإدخال بياناتك الأساسية في صفحة "الإعدادات" لضبط النظام حسب احتياجاتك
        </div>
    </div>

    <div class="help-section">
        <h3><i class="fa-solid fa-wand-magic-sparkles"></i> المميزات الذكية</h3>
        
        <div class="feature-grid">
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-brands fa-whatsapp"></i></div>
                <h4>تذكيرات واتساب</h4>
                <p>إرسال تلقائي للتذكيرات قبل استحقاق الدفعات</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-id-card"></i></div>
                <h4>مسح الهويات (OCR)</h4>
                <p>قراءة بيانات المستأجر من صورة الهوية تلقائياً</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-chart-line"></i></div>
                <h4>تحليلات ذكية</h4>
                <p>توقعات مالية وتحليل أداء العقارات</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-credit-card"></i></div>
                <h4>بوابة دفع</h4>
                <p>رابط دفع إلكتروني مباشر للمستأجرين</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-robot"></i></div>
                <h4>أتمتة كاملة</h4>
                <p>تصنيف الصيانة وإرسال التقارير تلقائياً</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-database"></i></div>
                <h4>نسخ احتياطي</h4>
                <p>نسخ احتياطي تلقائي لبياناتك بشكل دوري</p>
            </div>
        </div>
    </div>

    <div class="help-section">
        <h3><i class="fa-solid fa-cog"></i> إعداد التكاملات الذكية</h3>
        
        <h4>1. تفعيل واتساب:</h4>
        <p>لتفعيل إرسال الرسائل التلقائية عبر واتساب:</p>
        <ul>
            <li>انتقل إلى صفحة "الإعدادات"</li>
            <li>في قسم "تكاملات التمكين الذكي"، أدخل رابط API واتساب والتوكن</li>
            <li>يمكنك استخدام خدمات مثل Ultramsg أو Twilio</li>
            <li>أدخل رقم واتساب الإدارة لاستقبال التنبيهات</li>
        </ul>
        
        <h4 style="margin-top: 20px;">2. تفعيل OCR (مسح الهويات):</h4>
        <ul>
            <li>احصل على API Key من خدمة OCR (مثل Google Vision أو AWS Textract)</li>
            <li>أدخل رابط الخدمة والمفتاح في صفحة الإعدادات</li>
            <li>عند إضافة مستأجر جديد، يمكنك مسح الهوية لملء البيانات تلقائياً</li>
        </ul>
        
        <h4 style="margin-top: 20px;">3. إعداد بوابة الدفع:</h4>
        <ul>
            <li>أدخل رابط بوابة الدفع في الإعدادات</li>
            <li>سيتم إنشاء رابط دفع لكل دفعة تلقائياً</li>
            <li>يمكنك إرسال الرابط للمستأجر عبر واتساب أو البريد</li>
        </ul>
        
        <div class="help-tip">
            <i class="fa-solid fa-shield-halved"></i> <strong>أمان:</strong> 
            جميع المفاتيح والتوكنات مشفرة ومحمية. لا تشاركها مع أحد
        </div>
    </div>

    <div class="help-section">
        <h3><i class="fa-solid fa-calendar-check"></i> المهام التلقائية (Cron Jobs)</h3>
        <p>النظام يقوم بالمهام التالية تلقائياً عند تفعيل ملف cron_alerts.php:</p>
        
        <ul>
            <li><strong>تذكيرات يومية:</strong> إرسال تذكير للمستأجرين قبل يوم من استحقاق الدفعة</li>
            <li><strong>متابعة المتأخرات:</strong> تذكيرات للدفعات المتأخرة بعد 3، 7، و14 يوم</li>
            <li><strong>تجديد العقود:</strong> تنبيه عند اقتراب انتهاء العقد (60، 30، 7 أيام)</li>
            <li><strong>تصنيف الصيانة:</strong> تحليل ذكي لأولوية طلبات الصيانة</li>
            <li><strong>تقارير دورية:</strong> إرسال ملخص أسبوعي/شهري للإدارة</li>
            <li><strong>نسخ احتياطي:</strong> نسخ تلقائي للبيانات حسب الجدول المحدد</li>
        </ul>
        
        <div class="help-tip">
            <i class="fa-solid fa-clock"></i> <strong>التفعيل:</strong> 
            أضف هذا السطر إلى Cron Jobs في لوحة الاستضافة:<br>
            <code style="background:#000; padding:5px; display:inline-block; margin-top:5px; border-radius:5px;">
                0 8 * * * php /path/to/cron_alerts.php
            </code>
        </div>
    </div>

    <div class="help-section">
        <h3><i class="fa-solid fa-question-circle"></i> الأسئلة الشائعة</h3>
        
        <h4>كيف أغير كلمة المرور؟</h4>
        <p>انتقل إلى صفحة "المستخدمون" واضغط على زر التعديل لحسابك</p>
        
        <h4>كيف أضيف مستخدمين إضافيين؟</h4>
        <p>من صفحة "المستخدمون" يمكنك إضافة موظفين بصلاحيات محددة</p>
        
        <h4>ماذا لو نسيت إدخال دفعة؟</h4>
        <p>يمكنك إضافة دفعات يدوياً من صفحة تفاصيل العقد في أي وقت</p>
        
        <h4>هل البيانات آمنة؟</h4>
        <p>نعم، النظام يستخدم تشفير متقدم وحماية ضد الاختراقات، مع نسخ احتياطي دوري</p>
        
        <h4>كيف أصدر فاتورة ضريبية؟</h4>
        <p>عند إنشاء عقد، فعّل خيار "شامل الضريبة" وحدد النسبة، سيتم حسابها تلقائياً</p>
    </div>

    <div class="help-section">
        <h3><i class="fa-solid fa-headset"></i> الدعم الفني</h3>
        <p>إذا واجهت أي مشكلة أو كان لديك استفسار:</p>
        <ul>
            <li>راجع سجل النشاطات في لوحة القيادة لمعرفة آخر العمليات</li>
            <li>تحقق من الإعدادات للتأكد من صحة التكاملات</li>
            <li>راجع التقارير المالية للحصول على نظرة شاملة</li>
        </ul>
        
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding:20px; border-radius:10px; color:white; text-align:center; margin-top:20px;">
            <i class="fa-solid fa-heart" style="font-size:24px; margin-bottom:10px;"></i>
            <h4 style="margin:0;">نتمنى لك تجربة ممتعة مع النظام!</h4>
            <p style="margin:10px 0 0;">نظام إدارة عقارات ذكي - مصمم لراحتك</p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
