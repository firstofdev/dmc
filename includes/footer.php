</div> <script>
    // Theme Switcher
    (function() {
        var themeToggle = document.getElementById('themeToggle');
        if (themeToggle) {
            themeToggle.addEventListener('click', function() {
                var body = document.body;
                var isLight = body.classList.contains('light-theme');
                var newTheme = isLight ? 'dark' : 'light';
                
                if (isLight) {
                    body.classList.remove('light-theme');
                } else {
                    body.classList.add('light-theme');
                }
                
                // Update icon
                var icon = themeToggle.querySelector('i');
                icon.className = 'fa-solid fa-' + (isLight ? 'sun' : 'moon');
                
                // Save to server
                fetch('routes/toggle_theme.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({theme: newTheme})
                }).catch(function(error) {
                    console.error('Error saving theme:', error);
                });
            });
        }
    })();

    (function() {
        var body = document.body;
        var toggle = document.getElementById('sidebarToggle');
        var storedState = localStorage.getItem('sidebarCollapsed');
        if (storedState === 'true') {
            body.classList.add('sidebar-collapsed');
        }
        if (toggle) {
            toggle.addEventListener('click', function() {
                body.classList.toggle('sidebar-collapsed');
                localStorage.setItem('sidebarCollapsed', body.classList.contains('sidebar-collapsed'));
            });
        }
    })();

    // إغلاق المودال عند النقر في الخارج
    window.onclick = function(e){ if(e.target.classList.contains('modal')) e.target.style.display='none'; }

    // بحث ذكي شامل للجداول
    (function() {
        var searchInput = document.getElementById('globalSearch');
        var resultLabel = document.getElementById('searchCount');
        var clearButton = document.getElementById('clearSearch');
        var pageContext = document.body.dataset.page || 'dashboard';
        var smartHintText = document.getElementById('smartHintText');
        var smartAssist = document.getElementById('smartAssist');
        var refreshHint = document.getElementById('refreshHint');
        var PULSE_DURATION = 350;
        var smartHints = {
            dashboard: [
                'راقب العقود المنتهية هذا الشهر وابدأ بتجديدها مبكراً.',
                'راجع تحصيل الشهر الحالي لمعرفة اتجاه التدفق النقدي.'
            ],
            properties: [
                'استخدم زر التعديل لملء نموذج العقار تلقائياً ثم حدّث البيانات.',
                'أضف العنوان ورقم المشرف لتسهيل الوصول الميداني.'
            ],
            units: [
                'ابحث باسم العقار أو حالة الوحدة لفرز الشاغرة سريعاً.',
                'حدّث سعر الإيجار الفارغ لتظهر مؤشرات الإشغال بدقة.'
            ],
            contracts: [
                'ابحث برقم العقد أو اسم المستأجر لإيجاد البنود الحرجة.',
                'تأكد من إنشاء جدول دفعات لكل عقد جديد.'
            ],
            tenants: [
                'فلترة بالاسم أو الهاتف لتحديد الحسابات التي تحتاج تحديث بيانات الاتصال.',
                'أضف البريد والجوال لإرسال تذكيرات تحصيل أسرع.'
            ],
            alerts: [
                'ابدأ بالتنبيهات ذات الأولوية لمعالجة المتأخرات سريعاً.',
                'استخدم البحث لتصفية التنبيهات حسب المستأجر أو العقار.'
            ],
            maintenance: [
                'رتّب المهام بحسب الأولوية واغلق المكتملة لتصفية القائمة.',
                'اربط كل بلاغ بوحدة محددة لسهولة التتبع.'
            ],
            vendors: [
                'سجل أرقام التواصل للمقاولين لتسريع الإحالات.',
                'أضف تخصص المقاول ليسهل إيجاده في البلاغات.'
            ],
            users: [
                'تحقق من صلاحيات المستخدمين قبل إضافة حساب جديد.',
                'امنح صلاحية المشاهدة فقط للحسابات التجريبية.'
            ],
            settings: [
                'حدّث الشعار واسم الشركة ليظهر في جميع الصفحات.',
                'اضبط المنطقة الزمنية لضمان دقة التذكيرات.'
            ],
            smart_center: [
                'استخدم مركز التمكين لمراجعة المؤشرات الذكية والتوصيات.',
                'جرب البحث الذكي للوصول لأي عنصر قبل فتحه.'
            ],
            default: [
                'استخدم البحث الذكي لتصفية أي جدول فوراً.',
                'اضغط Ctrl + / للانتقال مباشرة لحقل البحث.'
            ]
        };
        var hintIndex = 0;
        if (!searchInput) return;

        function updateTables() {
            var filter = searchInput.value.trim().toLowerCase();
            var tables = document.querySelectorAll('table');
            var totalVisible = 0;
            var totalRows = 0;

            tables.forEach(function(table) {
                var rows = Array.from(table.querySelectorAll('tr')).slice(1);
                var visibleRows = 0;
                totalRows += rows.length;

                rows.forEach(function(row) {
                    var cells = Array.from(row.querySelectorAll('td'));
                    var text = cells.map(function(cell) { return cell.textContent.toLowerCase(); }).join(' ');
                    var match = filter === '' || text.indexOf(filter) > -1;
                    row.style.display = match ? '' : 'none';
                    if (match) visibleRows++;
                });

                var emptyRow = table.querySelector('.no-results-row');
                if (!emptyRow) {
                    emptyRow = document.createElement('tr');
                    emptyRow.className = 'no-results-row';
                    var cell = document.createElement('td');
                    cell.colSpan = table.querySelectorAll('th').length || 1;
                    cell.style.textAlign = 'center';
                    cell.style.color = 'var(--muted)';
                    cell.style.padding = '20px';
                    cell.textContent = 'لا توجد نتائج مطابقة.';
                    emptyRow.appendChild(cell);
                    table.appendChild(emptyRow);
                }
                emptyRow.style.display = visibleRows === 0 && rows.length > 0 ? '' : 'none';
                totalVisible += visibleRows;
            });

            if (resultLabel) {
                if (totalRows === 0) {
                    resultLabel.innerHTML = '<i class="fa-solid fa-circle-info"></i> لا توجد جداول للبحث';
                } else if (filter === '') {
                    resultLabel.innerHTML = '<i class="fa-solid fa-list-check"></i> كل النتائج ظاهرة';
                } else {
                    resultLabel.innerHTML = '<i class="fa-solid fa-list-check"></i> نتائج مطابقة: ' + totalVisible + ' من ' + totalRows;
                }
            }
        }

        searchInput.addEventListener('input', updateTables);
        if (clearButton) {
            clearButton.addEventListener('click', function() {
                searchInput.value = '';
                updateTables();
                searchInput.focus();
            });
        }
        document.addEventListener('keydown', function(event) {
            if (event.ctrlKey && event.key === '/') {
                event.preventDefault();
                searchInput.focus();
            }
        });

        function setSmartHint() {
            if (!smartHintText) return;
            var list = smartHints[pageContext] || smartHints.default || [];
            if (!list.length) return;
            var idx = hintIndex % list.length;
            smartHintText.textContent = list[idx];
            var nextIndex = (hintIndex + 1) % list.length;
            hintIndex = nextIndex;
            if (smartAssist) {
                smartAssist.classList.add('pulse');
                setTimeout(function(){ smartAssist.classList.remove('pulse'); }, PULSE_DURATION);
            }
        }
        setSmartHint();
        if (refreshHint) {
            refreshHint.addEventListener('click', setSmartHint);
        }
    })();

    // وظيفة البحث القديمة (للتوافق إن وُجدت حقول بحث مخصصة)
    function searchTable() {
        var input = document.getElementById("tableSearch");
        if(!input) return;
        var filter = input.value.toUpperCase();
        var table = document.querySelector("table");
        if (!table) return;
        var tr = table.getElementsByTagName("tr");
        for (var i = 1; i < tr.length; i++) {
            var td = tr[i].getElementsByTagName("td")[0];
            if (td) {
                var txtValue = td.textContent || td.innerText;
                tr[i].style.display = txtValue.toUpperCase().indexOf(filter) > -1 ? "" : "none";
            }
        }
    }
</script>
</body>
</html>
