</div> <script>
    // إغلاق المودال عند النقر في الخارج
    window.onclick = function(e){ if(e.target.classList.contains('modal')) e.target.style.display='none'; }
    
    // وظيفة البحث في الجداول
    function searchTable() {
        var input = document.getElementById("tableSearch");
        if(!input) return;
        var filter = input.value.toUpperCase();
        var table = document.querySelector("table");
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
