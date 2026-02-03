<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$currentCategory = $_GET['category'] ?? '';
$pdo = getDBConnection();
$userId = getCurrentUserId();

// Количество новых заявок на объекты агента
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM inquiries i 
    JOIN properties p ON i.property_id = p.id 
    WHERE p.agent_id = ? AND i.status = 'new'
");
$stmt->execute([$userId]);
$newInquiriesCount = $stmt->fetchColumn();

// Количество ожидающих отзывов на объекты агента
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM reviews r 
    JOIN properties p ON r.property_id = p.id 
    WHERE p.agent_id = ? AND r.is_approved = 0
");
$stmt->execute([$userId]);
$pendingReviewsCount = $stmt->fetchColumn();
?>
<aside class="admin-sidebar" id="adminSidebar">
    <nav class="admin-nav">
        <a href="dashboard.php" class="admin-nav__item <?= $currentPage === 'dashboard.php' && empty($currentCategory) ? 'admin-nav__item--active' : '' ?>">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
        
        <div class="admin-nav__divider">Готовое жильё</div>
        
        <a href="dashboard.php?category=sale" class="admin-nav__item <?= $currentCategory === 'sale' ? 'admin-nav__item--active' : '' ?>">
            <i class="fas fa-home"></i>
            <span>Продажа</span>
        </a>
        
        <a href="dashboard.php?category=rent" class="admin-nav__item <?= $currentCategory === 'rent' ? 'admin-nav__item--active' : '' ?>">
            <i class="fas fa-key"></i>
            <span>Аренда</span>
        </a>
        
        <a href="add-property.php" class="admin-nav__item <?= $currentPage === 'add-property.php' ? 'admin-nav__item--active' : '' ?>">
            <i class="fas fa-plus-circle"></i>
            <span>Добавить объект</span>
        </a>
        
        <div class="admin-nav__divider">Новостройки</div>
        
        <a href="new-buildings.php" class="admin-nav__item <?= $currentPage === 'new-buildings.php' ? 'admin-nav__item--active' : '' ?>">
            <i class="fas fa-city"></i>
            <span>Список ЖК</span>
        </a>
        
        <a href="new-building-edit.php" class="admin-nav__item <?= $currentPage === 'new-building-edit.php' && !isset($_GET['id']) ? 'admin-nav__item--active' : '' ?>">
            <i class="fas fa-plus-circle"></i>
            <span>Добавить ЖК</span>
        </a>
        
        <div class="admin-nav__divider">Справочники</div>
        
        <a href="developers.php" class="admin-nav__item <?= $currentPage === 'developers.php' ? 'admin-nav__item--active' : '' ?>">
            <i class="fas fa-hard-hat"></i>
            <span>Застройщики</span>
        </a>
        
        <a href="districts.php" class="admin-nav__item <?= $currentPage === 'districts.php' ? 'admin-nav__item--active' : '' ?>">
            <i class="fas fa-map-marker-alt"></i>
            <span>Районы</span>
        </a>
        
        <div class="admin-nav__divider">Заявки и клиенты</div>
        
        <a href="requests.php" class="admin-nav__item <?= $currentPage === 'requests.php' ? 'admin-nav__item--active' : '' ?>">
            <i class="fas fa-envelope"></i>
            <span>Заявки</span>
            <?php if ($newInquiriesCount > 0): ?>
            <span class="admin-nav__badge"><?= $newInquiriesCount ?></span>
            <?php endif; ?>
        </a>
        
        <a href="reviews.php" class="admin-nav__item <?= $currentPage === 'reviews.php' ? 'admin-nav__item--active' : '' ?>">
            <i class="fas fa-star"></i>
            <span>Отзывы</span>
            <?php if ($pendingReviewsCount > 0): ?>
            <span class="admin-nav__badge"><?= $pendingReviewsCount ?></span>
            <?php endif; ?>
        </a>
        
        <a href="calendar.php" class="admin-nav__item <?= $currentPage === 'calendar.php' ? 'admin-nav__item--active' : '' ?>">
            <i class="fas fa-calendar-alt"></i>
            <span>Календарь</span>
        </a>
        
        <a href="/chat.php" class="admin-nav__item">
            <i class="fas fa-comments"></i>
            <span>Сообщения</span>
        </a>
    </nav>
    
    <div class="admin-sidebar__footer">
        <div class="admin-sidebar__version">
            v3.0 - Екатеринбург
        </div>
    </div>
</aside>
