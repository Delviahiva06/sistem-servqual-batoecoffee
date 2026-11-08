<?php
if (!isset($pageTitle)) {
    $pageTitle = 'Admin Panel';
}
?>
<header class="admin-header">
    <h2 class="admin-header-title"><?php echo htmlspecialchars($pageTitle); ?></h2>
    <div class="admin-header-meta">
        <span id="adminDatetime"></span>
    </div>
</header>
<script>
    (function () {
        function updateAdminTime() {
            var el = document.getElementById('adminDatetime');
            if (!el) return;
            var now = new Date();
            var opts = { weekday: 'short', year: 'numeric', month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit' };
            el.textContent = now.toLocaleString('id-ID', opts);
        }
        updateAdminTime();
        setInterval(updateAdminTime, 60000);
    })();
</script>
