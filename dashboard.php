<?php
/**
 * Dashboard Page - Elsesser & Co.
 * Личный кабинет пользователя
 */

require_once __DIR__ . '/includes/config/database.php';
require_once __DIR__ . '/includes/auth/check_auth.php';

// Требуем авторизацию
requireLogin('/dashboard.php');

$user = getUserData();
$userId = $user['id'];

// Получаем данные пользователя из БД
$pdo = getDBConnection();

// Полные данные пользователя
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userData = $stmt->fetch();

// Избранные объекты
$stmt = $pdo->prepare("
    SELECT p.*, pi.image_url as primary_image
    FROM favorites f
    JOIN properties p ON f.property_id = p.id
    LEFT JOIN property_images pi ON p.id = pi.property_id AND pi.is_primary = 1
    WHERE f.user_id = ?
    ORDER BY f.created_at DESC
    LIMIT 6
");
$stmt->execute([$userId]);
$favorites = $stmt->fetchAll();

// Количество избранного
$stmt = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = ?");
$stmt->execute([$userId]);
$favoritesCount = $stmt->fetchColumn();

// Запросы пользователя (все для истории)
$stmt = $pdo->prepare("
    SELECT i.*, p.title as property_title, p.id as property_id
    FROM inquiries i
    LEFT JOIN properties p ON i.property_id = p.id
    WHERE i.user_id = ?
    ORDER BY i.created_at DESC
");
$stmt->execute([$userId]);
$inquiries = $stmt->fetchAll();
$totalInquiries = count($inquiries);

// Непрочитанные сообщения
$stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
$stmt->execute([$userId]);
$unreadMessages = $stmt->fetchColumn();

// Мои отзывы
$stmt = $pdo->prepare("
    SELECT r.*, p.title as property_title, p.id as property_id
    FROM reviews r
    LEFT JOIN properties p ON r.property_id = p.id
    WHERE r.user_id = ?
    ORDER BY r.created_at DESC
    LIMIT 5
");
$stmt->execute([$userId]);
$myReviews = $stmt->fetchAll();

// Проверяем, является ли пользователь агентом
$isAgentUser = isAgent();

$welcomeMessage = isset($_GET['welcome']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Личный кабинет | Elsesser & Co.</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="images/favicon.png">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Styles -->
    <link rel="stylesheet" href="css/reset.css">
    <link rel="stylesheet" href="css/variables.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/responsive.css">
</head>
<body>
    <!-- Header -->
    <header class="header header--solid" id="header">
        <div class="container">
            <div class="header__inner">
                <a href="index.php" class="header__logo">
                    <span class="header__logo-text">Elsesser & Co.</span>
                </a>
                
                <nav class="nav">
                    <ul class="nav__list">
                        <li><a href="properties.php?type=sale" class="nav__link">Купить</a></li>
                        <li><a href="properties.php?type=rent" class="nav__link">Аренда</a></li>
                        <li><a href="favorites.php" class="nav__link">
                            <i class="fas fa-heart"></i> Избранное
                            <?php if ($favoritesCount > 0): ?>
                            <span class="badge"><?= $favoritesCount ?></span>
                            <?php endif; ?>
                        </a></li>
                    </ul>
                    <div class="user-menu">
                        <button class="user-menu__toggle" id="userMenuToggle">
                            <div class="user-menu__avatar">
                                <?= strtoupper(substr($userData['first_name'], 0, 1)) ?>
                            </div>
                            <span class="user-menu__name"><?= escape($userData['first_name']) ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="user-menu__dropdown" id="userMenuDropdown">
                            <a href="dashboard.php" class="user-menu__item user-menu__item--active">
                                <i class="fas fa-home"></i> Личный кабинет
                            </a>
                            <a href="favorites.php" class="user-menu__item">
                                <i class="fas fa-heart"></i> Избранное
                            </a>
                            <a href="profile.php" class="user-menu__item">
                                <i class="fas fa-user"></i> Профиль
                            </a>
                            <?php if (isAdmin()): ?>
                            <hr>
                            <a href="admin/index.php" class="user-menu__item user-menu__item--admin">
                                <i class="fas fa-shield-alt"></i> Админ-панель
                            </a>
                            <?php endif; ?>
                            <hr>
                            <a href="includes/auth/logout.php" class="user-menu__item user-menu__item--logout">
                                <i class="fas fa-sign-out-alt"></i> Выйти
                            </a>
                        </div>
                    </div>
                </nav>
                
                <button class="hamburger" id="hamburger" aria-label="Открыть меню">
                    <span class="hamburger__line"></span>
                    <span class="hamburger__line"></span>
                    <span class="hamburger__line"></span>
                </button>
            </div>
        </div>
    </header>

    <?php include __DIR__ . '/includes/mobile-menu.php'; ?>

    <!-- Dashboard Content -->
    <main class="dashboard">
        <div class="container">
            <?php if ($welcomeMessage): ?>
            <div class="alert alert--success">
                <i class="fas fa-check-circle"></i>
                <span>Добро пожаловать, <?= escape($userData['first_name']) ?>! Ваш аккаунт успешно создан.</span>
            </div>
            <?php endif; ?>

            <!-- Welcome Section -->
            <div class="dashboard__header">
                <div class="dashboard__welcome">
                    <h1>Добро пожаловать, <?= escape($userData['first_name']) ?>!</h1>
                    <p>Управляйте избранным и отслеживайте ваши запросы</p>
                </div>
                <div class="dashboard__actions">
                    <a href="properties.php" class="btn btn--primary">
                        <i class="fas fa-search"></i> Найти недвижимость
                    </a>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card__icon stat-card__icon--heart">
                        <i class="fas fa-heart"></i>
                    </div>
                    <div class="stat-card__content">
                        <div class="stat-card__value"><?= $favoritesCount ?></div>
                        <div class="stat-card__label">В избранном</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__icon stat-card__icon--envelope">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="stat-card__content">
                        <div class="stat-card__value"><?= $totalInquiries ?></div>
                        <div class="stat-card__label">Запросов</div>
                    </div>
                </div>
                <a href="chat.php" class="stat-card stat-card--link">
                    <div class="stat-card__icon stat-card__icon--message">
                        <i class="fas fa-comments"></i>
                    </div>
                    <div class="stat-card__content">
                        <div class="stat-card__value"><?= $unreadMessages ?></div>
                        <div class="stat-card__label">Сообщения</div>
                    </div>
                    <?php if ($unreadMessages > 0): ?>
                    <span class="stat-card__badge"><?= $unreadMessages ?> новых</span>
                    <?php endif; ?>
                </a>
                <div class="stat-card">
                    <div class="stat-card__icon stat-card__icon--star">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="stat-card__content">
                        <div class="stat-card__value"><?= count($myReviews) ?></div>
                        <div class="stat-card__label">Отзывов</div>
                    </div>
                </div>
            </div>
            
            <?php if (isAdmin()): ?>
            <div class="agent-banner agent-banner--admin">
                <div class="agent-banner__content">
                    <i class="fas fa-shield-alt"></i>
                    <div>
                        <h3>Админ-панель</h3>
                        <p>Управление пользователями, объектами и настройками сайта</p>
                    </div>
                </div>
                <a href="admin/index.php" class="btn btn--primary">
                    <i class="fas fa-cog"></i> Открыть админку
                </a>
            </div>
            <?php endif; ?>
            
            <?php if ($isAgentUser): ?>
            <div class="agent-banner">
                <div class="agent-banner__content">
                    <i class="fas fa-briefcase"></i>
                    <div>
                        <h3>Панель агента</h3>
                        <p>Управляйте своими объектами, заявками и календарём</p>
                    </div>
                </div>
                <a href="agent/dashboard.php" class="btn btn--primary">
                    <i class="fas fa-arrow-right"></i> Открыть CRM
                </a>
            </div>
            <?php endif; ?>

            <!-- Main Grid -->
            <div class="dashboard__grid">
                <!-- Favorites Section -->
                <section class="dashboard__section">
                    <div class="dashboard__section-header">
                        <h2><i class="fas fa-heart"></i> Избранные объекты</h2>
                        <?php if ($favoritesCount > 0): ?>
                        <a href="favorites.php" class="dashboard__section-link">Смотреть все</a>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (empty($favorites)): ?>
                    <div class="empty-state">
                        <div class="empty-state__icon">
                            <i class="far fa-heart"></i>
                        </div>
                        <h3>Избранное пусто</h3>
                        <p>Добавляйте понравившиеся объекты в избранное, чтобы не потерять их</p>
                        <a href="properties.php" class="btn btn--secondary">Найти недвижимость</a>
                    </div>
                    <?php else: ?>
                    <div class="favorites-grid">
                        <?php foreach ($favorites as $property): ?>
                        <div class="favorite-card">
                            <a href="property.php?id=<?= $property['id'] ?>" class="favorite-card__image">
                                <img src="<?= escape($property['primary_image'] ?? 'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?w=400&q=80') ?>" 
                                     alt="<?= escape($property['title']) ?>">
                            </a>
                            <div class="favorite-card__body">
                                <div class="favorite-card__price">
                                    <?= formatPrice($property['price']) ?>
                                    <?php if ($property['listing_type'] === 'rent'): ?>
                                    <span class="favorite-card__period">/год</span>
                                    <?php endif; ?>
                                </div>
                                <h3 class="favorite-card__title">
                                    <a href="property.php?id=<?= $property['id'] ?>">
                                        <?= escape($property['title_ru'] ?? $property['title']) ?>
                                    </a>
                                </h3>
                                <div class="favorite-card__location">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?= escape($property['location']) ?>
                                </div>
                                <div class="favorite-card__specs">
                                    <span><i class="fas fa-bed"></i> <?= $property['bedrooms'] ?></span>
                                    <span><i class="fas fa-bath"></i> <?= $property['bathrooms'] ?></span>
                                    <span><i class="fas fa-vector-square"></i> <?= number_format($property['area_sqft']) ?> м²</span>
                                </div>
                            </div>
                            <button class="favorite-card__remove" 
                                    onclick="removeFavorite(<?= $property['id'] ?>)"
                                    title="Удалить из избранного">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </section>

                <!-- Sidebar -->
                <aside class="dashboard__sidebar">
                    <!-- Profile Card -->
                    <div class="profile-card">
                        <div class="profile-card__avatar">
                            <?= strtoupper(substr($userData['first_name'], 0, 1) . substr($userData['last_name'], 0, 1)) ?>
                        </div>
                        <h3 class="profile-card__name">
                            <?= escape($userData['first_name'] . ' ' . $userData['last_name']) ?>
                        </h3>
                        <p class="profile-card__email"><?= escape($userData['email']) ?></p>
                        <p class="profile-card__date">
                            Зарегистрирован: <?= date('d.m.Y', strtotime($userData['created_at'])) ?>
                        </p>
                        <a href="profile.php" class="btn btn--secondary btn--sm">Редактировать профиль</a>
                    </div>

                    <!-- Quick Links -->
                    <div class="quick-links">
                        <h3>Быстрые ссылки</h3>
                        <ul>
                            <li>
                                <a href="chat.php">
                                    <i class="fas fa-comments"></i> Мои сообщения
                                    <?php if ($unreadMessages > 0): ?>
                                    <span class="badge"><?= $unreadMessages ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                            <li>
                                <a href="properties.php?type=sale">
                                    <i class="fas fa-home"></i> Купить недвижимость
                                </a>
                            </li>
                            <li>
                                <a href="properties.php?type=rent">
                                    <i class="fas fa-key"></i> Арендовать
                                </a>
                            </li>
                            <li>
                                <a href="contact.html">
                                    <i class="fas fa-tag"></i> Продать недвижимость
                                </a>
                            </li>
                        </ul>
                    </div>

                    <!-- My Reviews -->
                    <?php if (!empty($myReviews)): ?>
                    <div class="my-reviews">
                        <h3>Мои отзывы</h3>
                        <ul>
                            <?php foreach ($myReviews as $review): ?>
                            <li>
                                <div class="review-mini">
                                    <div class="review-mini__rating">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="<?= $i <= $review['rating'] ? 'fas' : 'far' ?> fa-star"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <?php if ($review['property_title']): ?>
                                    <a href="property.php?id=<?= $review['property_id'] ?>" class="review-mini__title">
                                        <?= escape($review['property_title']) ?>
                                    </a>
                                    <?php endif; ?>
                                    <span class="review-mini__status <?= $review['is_approved'] ? 'approved' : 'pending' ?>">
                                        <?= $review['is_approved'] ? 'Опубликован' : 'На модерации' ?>
                                    </span>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <!-- Request History -->
                    <?php if (!empty($inquiries)): ?>
                    <div class="recent-inquiries">
                        <h3>История заявок</h3>
                        <ul>
                            <?php foreach (array_slice($inquiries, 0, 5) as $inquiry): ?>
                            <li>
                                <div class="inquiry-item">
                                    <div class="inquiry-item__status inquiry-item__status--<?= $inquiry['status'] ?>">
                                        <?= match($inquiry['status']) {
                                            'new' => 'Новая',
                                            'contacted' => 'Связались',
                                            'scheduled' => 'Назначен просмотр',
                                            'completed' => 'Завершена',
                                            'cancelled' => 'Отменена',
                                            default => ucfirst($inquiry['status'])
                                        } ?>
                                    </div>
                                    <?php if ($inquiry['property_title']): ?>
                                    <a href="property.php?id=<?= $inquiry['property_id'] ?>" class="inquiry-item__title">
                                        <?= escape($inquiry['property_title']) ?>
                                    </a>
                                    <?php else: ?>
                                    <p class="inquiry-item__title">Общий запрос</p>
                                    <?php endif; ?>
                                    <span class="inquiry-item__date">
                                        <?= date('d.m.Y', strtotime($inquiry['created_at'])) ?>
                                    </span>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if (count($inquiries) > 5): ?>
                        <p class="text-center text-sm" style="margin-top: var(--space-3);">
                            И ещё <?= count($inquiries) - 5 ?> заявок
                        </p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </aside>
            </div>
        </div>
    </main>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <!-- Scripts -->
    <script src="js/navigation.js"></script>
    <script src="js/favorites.js"></script>
    <script>
        // User menu toggle
        const userMenuToggle = document.getElementById('userMenuToggle');
        const userMenuDropdown = document.getElementById('userMenuDropdown');
        
        if (userMenuToggle && userMenuDropdown) {
            userMenuToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                userMenuDropdown.classList.toggle('active');
            });
            
            document.addEventListener('click', function(e) {
                if (!userMenuDropdown.contains(e.target)) {
                    userMenuDropdown.classList.remove('active');
                }
            });
        }
    </script>
</body>
</html>
