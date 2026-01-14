# تثبيت FontAwesome محلياً

## المشكلة
الأيقونات لا تظهر بشكل صحيح بسبب مشاكل في تحميل خطوط FontAwesome من CDN.

## الحل

### 1. تحميل FontAwesome
قم بتحميل FontAwesome من الموقع الرسمي:
- زر: https://fontawesome.com/download
- أو استخدم npm: `npm install @fortawesome/fontawesome-free`

### 2. نسخ الملفات
بعد التحميل، انسخ:
- ملفات CSS من مجلد `css/` إلى `resources/fontawesome/css/`
- ملفات الخطوط من مجلد `webfonts/` إلى `resources/fontawesome/webfonts/`

### 3. الملفات المطلوبة
تأكد من وجود:
```
resources/fontawesome/
├── css/
│   ├── all.min.css
│   └── fontawesome.min.css
└── webfonts/
    ├── fa-solid-900.woff2
    ├── fa-solid-900.woff
    ├── fa-solid-900.ttf
    ├── fa-regular-400.woff2
    ├── fa-regular-400.woff
    └── fa-regular-400.ttf
```

### 4. التحديث التلقائي
الملف `includes/header.php` محدث الآن ليستخدم النسخة المحلية تلقائياً عندما تكون متوفرة.
