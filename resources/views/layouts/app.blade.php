<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>دار الميار للمقاولات</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        body { background-color: #f4f6f9; font-family: 'Tajawal', sans-serif; }
        .sidebar {
            height: 100vh;
            width: 250px;
            position: fixed;
            top: 0;
            right: 0;
            background-color: #1e293b; /* لون غامق كما في الصورة */
            color: white;
            padding-top: 20px;
        }
        .sidebar a {
            padding: 15px 25px;
            text-decoration: none;
            font-size: 16px;
            color: #cbd5e1;
            display: block;
            transition: 0.3s;
        }
        .sidebar a:hover, .sidebar a.active {
            background-color: #4f46e5; /* اللون البنفسجي */
            color: white;
            border-right: 4px solid #fff;
        }
        .sidebar .brand {
            text-align: center;
            margin-bottom: 30px;
            font-weight: bold;
            font-size: 20px;
        }
        .main-content {
            margin-right: 250px; /* مسافة للسايد بار */
            padding: 20px;
        }
        .card-custom {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            background: white;
        }
        .btn-purple {
            background-color: #4f46e5;
            color: white;
        }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="brand">
            <i class="fa-solid fa-building"></i> دار الميار
        </div>
        <a href="/" class="{{ request()->is('/') ? 'active' : '' }}"><i class="fa-solid fa-house ms-2"></i> لوحة التحكم</a>
        <a href="{{ route('properties.index') }}" class="{{ request()->is('properties*') ? 'active' : '' }}"><i class="fa-solid fa-city ms-2"></i> العقارات</a>
        <a href="#"><i class="fa-solid fa-door-open ms-2"></i> الوحدات</a>
        <a href="#"><i class="fa-solid fa-file-contract ms-2"></i> العقود الإيجارية</a>
        <a href="#"><i class="fa-solid fa-users ms-2"></i> المستأجرين</a>
        <a href="#"><i class="fa-solid fa-money-bill ms-2"></i> الفواتير</a>
        <a href="#"><i class="fa-solid fa-gear ms-2"></i> الإعدادات</a>
    </div>

    <div class="main-content">
        <nav class="navbar navbar-light bg-white mb-4 rounded shadow-sm px-3">
            <span class="navbar-brand mb-0 h1">مرحباً، مدير النظام</span>
        </nav>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @yield('content')
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
