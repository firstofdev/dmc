</div> <script>
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
                    resultLabel.textContent = 'لا توجد جداول للبحث';
                } else if (filter === '') {
                    resultLabel.textContent = 'كل النتائج ظاهرة';
                } else {
                    resultLabel.textContent = 'نتائج مطابقة: ' + totalVisible + ' من ' + totalRows;
                }
            }
        }

        searchInput.addEventListener('input', updateTables);
        document.addEventListener('keydown', function(event) {
            if (event.ctrlKey && event.key === '/') {
                event.preventDefault();
                searchInput.focus();
            }
        });
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
