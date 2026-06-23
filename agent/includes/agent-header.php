<?php
$pdo = getDBConnection();
$userId = getCurrentUserId();

// Получаем количество непрочитанных сообщений
$stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
$stmt->execute([$userId]);
$unreadMessages = $stmt->fetchColumn();

// Получаем количество новых заявок на мои объекты
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM inquiries i 
    JOIN properties p ON i.property_id = p.id 
    WHERE p.agent_id = ? AND i.status = 'new'
");
$stmt->execute([$userId]);
$newInquiries = $stmt->fetchColumn();
?>
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
        <a href="/chat.php" class="admin-header-icon" title="Сообщения">
            <i class="fas fa-comments"></i>
            <?php if ($unreadMessages > 0): ?>
            <span class="admin-header-badge"><?= $unreadMessages ?></span>
            <?php endif; ?>
        </a>
        
        <a href="/agent/requests.php" class="admin-header-icon" title="Заявки">
            <i class="fas fa-envelope"></i>
            <?php if ($newInquiries > 0): ?>
            <span class="admin-header-badge"><?= $newInquiries ?></span>
            <?php endif; ?>
        </a>
        
        <div class="admin-user-menu">
            <button class="admin-user-menu__toggle" id="userMenuToggle">
                <i class="fas fa-user-circle"></i>
                <span><?= escape(getCurrentUserName()) ?></span>
                <i class="fas fa-chevron-down"></i>
            </button>
            <div class="admin-user-menu__dropdown" id="userMenuDropdown">
                <a href="/agent/profile.php" class="admin-user-menu__item">
                    <i class="fas fa-user"></i> Мой профиль
                </a>
                <a href="/properties.php" class="admin-user-menu__item">
                    <i class="fas fa-eye"></i> Просмотр сайта
                </a>
                <?php if (isAdmin()): ?>
                <a href="/admin/index.php" class="admin-user-menu__item">
                    <i class="fas fa-cog"></i> Админ-панель
                </a>
                <?php endif; ?>
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
