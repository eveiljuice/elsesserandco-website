<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$currentCategory = $_GET['category'] ?? '';
?>
<aside class="admin-sidebar" id="adminSidebar">
    <nav class="admin-nav">
        <a href="index.php" class="admin-nav__item <?= $currentPage === 'index.php' ? 'admin-nav__item--active' : '' ?>">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
        
        <div class="admin-nav__divider">Готовое жильё</div>
        
        <a href="properties.php?category=sale" class="admin-nav__item <?= $currentPage === 'properties.php' && $currentCategory === 'sale' ? 'admin-nav__item--active' : '' ?>">
            <i class="fas fa-home"></i>
            <span>Продажа</span>
        </a>
        
        <a href="properties.php?category=rent" class="admin-nav__item <?= $currentPage === 'properties.php' && $currentCategory === 'rent' ? 'admin-nav__item--active' : '' ?>">
            <i class="fas fa-key"></i>
            <span>Аренда</span>
        </a>
        
        <a href="property-edit.php" class="admin-nav__item <?= $currentPage === 'property-edit.php' && !isset($_GET['id']) ? 'admin-nav__item--active' : '' ?>">
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
        
        <a href="developer-applications.php" class="admin-nav__item <?= $currentPage === 'developer-applications.php' ? 'admin-nav__item--active' : '' ?>">
            <i class="fas fa-briefcase"></i>
            <span>Заявки застройщиков</span>
            <?php
            $pendingDevelopersStmt = $pdo->query("SELECT COUNT(*) FROM developer_applications WHERE status IN ('pending', 'reviewing')");
            $pendingDevelopers = $pendingDevelopersStmt->fetchColumn();
            if ($pendingDevelopers > 0):
            ?>
            <span class="admin-nav__badge"><?= $pendingDevelopers ?></span>
            <?php endif; ?>
        </a>
        
        <a href="inquiries.php" class="admin-nav__item <?= $currentPage === 'inquiries.php' ? 'admin-nav__item--active' : '' ?>">
            <i class="fas fa-envelope"></i>
            <span>Заявки клиентов</span>
            <?php if (isset($newCount) && $newCount > 0): ?>
            <span class="admin-nav__badge"><?= $newCount ?></span>
            <?php endif; ?>
        </a>
        
        <a href="moderate_reviews.php" class="admin-nav__item <?= $currentPage === 'moderate_reviews.php' ? 'admin-nav__item--active' : '' ?>">
            <i class="fas fa-star"></i>
            <span>Отзывы</span>
            <?php
            $pendingStmt = $pdo->query("SELECT COUNT(*) FROM reviews WHERE is_approved = 0");
            $pendingReviews = $pendingStmt->fetchColumn();
            if ($pendingReviews > 0):
            ?>
            <span class="admin-nav__badge"><?= $pendingReviews ?></span>
            <?php endif; ?>
        </a>

        <div class="admin-nav__divider">Администрирование</div>

        <a href="users.php" class="admin-nav__item <?= $currentPage === 'users.php' ? 'admin-nav__item--active' : '' ?>">
            <i class="fas fa-users"></i>
            <span>Пользователи</span>
            <?php
            $newUsersStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= NOW() - INTERVAL 7 DAY");
            $newUsersCount = (int)$newUsersStmt->fetchColumn();
            if ($newUsersCount > 0):
            ?>
            <span class="admin-nav__badge"><?= $newUsersCount ?></span>
            <?php endif; ?>
        </a>
    </nav>
    
    <div class="admin-sidebar__footer">
    </div>
</aside>
