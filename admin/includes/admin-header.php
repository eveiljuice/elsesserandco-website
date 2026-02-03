<header class="admin-header-bar">
    <div class="admin-header-bar__left">
        <button class="admin-sidebar-toggle" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
        <a href="/index.php" class="admin-logo" target="_blank">
            <i class="fas fa-home"></i>
            <span>Elsesser & Co.</span>
        </a>
    </div>
    
    <div class="admin-header-bar__right">
        <a href="/admin/inquiries.php" class="admin-header-icon" title="Заявки">
            <i class="fas fa-envelope"></i>
            <?php
            $pdo = getDBConnection();
            $stmt = $pdo->query("SELECT COUNT(*) FROM inquiries WHERE status = 'new'");
            $newCount = $stmt->fetchColumn();
            if ($newCount > 0):
            ?>
            <span class="admin-header-badge"><?= $newCount ?></span>
            <?php endif; ?>
        </a>
        
        <div class="admin-user-menu">
            <button class="admin-user-menu__toggle" id="userMenuToggle">
                <i class="fas fa-user-circle"></i>
                <span><?= escape(getCurrentUserName()) ?></span>
                <i class="fas fa-chevron-down"></i>
            </button>
            <div class="admin-user-menu__dropdown" id="userMenuDropdown">
                <a href="/dashboard.php" class="admin-user-menu__item">
                    <i class="fas fa-user"></i> Мой профиль
                </a>
                <a href="/properties.php" class="admin-user-menu__item">
                    <i class="fas fa-eye"></i> Просмотр сайта
                </a>
                <div class="admin-user-menu__divider"></div>
                <a href="/includes/auth/logout.php" class="admin-user-menu__item">
                    <i class="fas fa-sign-out-alt"></i> Выход
                </a>
            </div>
        </div>
    </div>
</header>

<script>
    // Sidebar toggle
    document.getElementById('sidebarToggle')?.addEventListener('click', function() {
        document.querySelector('.admin-sidebar').classList.toggle('admin-sidebar--collapsed');
    });
    
    // User menu toggle
    document.getElementById('userMenuToggle')?.addEventListener('click', function(e) {
        e.stopPropagation();
        document.getElementById('userMenuDropdown').classList.toggle('active');
    });
    
    // Close user menu when clicking outside
    document.addEventListener('click', function() {
        document.getElementById('userMenuDropdown')?.classList.remove('active');
    });
</script>





