<?php
$activeMenu = isset($activeMenu) ? $activeMenu : '';
?>
<aside class="admin-sidebar">
    <div class="sidebar-brand">
        <h1>☕ BATOE COFFEE</h1>
        <p>Admin Panel</p>
    </div>
    <div class="sidebar-divider"></div>
    <nav class="sidebar-menu">
        <a href="index.php" class="<?php echo $activeMenu === 'dashboard' ? 'active' : ''; ?>">
            🏠 <span>Dashboard</span>
        </a>
        <a href="responden.php" class="<?php echo $activeMenu === 'responden' ? 'active' : ''; ?>">
            👥 <span>Data Responden</span>
        </a>
        <a href="servqual.php" class="<?php echo $activeMenu === 'servqual' ? 'active' : ''; ?>">
            📊 <span>Evaluasi Pelayanan</span>
        </a>
        <a href="hasil.php" class="<?php echo $activeMenu === 'hasil' ? 'active' : ''; ?>">
            📈 <span>Hasil &amp; Grafik</span>
        </a>
    </nav>
    <div class="sidebar-footer">
        <a href="logout.php" class="logout-link">🚪 Logout</a>
        <div class="admin-name">
            👤 <?php echo htmlspecialchars(isset($_SESSION['admin_nama']) ? $_SESSION['admin_nama'] : 'Admin'); ?>
        </div>
    </div>
</aside>
