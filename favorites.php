<?php
/**
 * Favorites Page - Elsesser & Co.
 * Страница избранного пользователя
 */

require_once __DIR__ . '/includes/config/database.php';
require_once __DIR__ . '/includes/auth/check_auth.php';

// Требуем авторизацию
requireLogin('/favorites.php');

$user = getUserData();
$userId = $user['id'];
$pdo = getDBConnection();

// Получаем избранные объекты
$stmt = $pdo->prepare("
    SELECT p.*, 
           pi.image_url as primary_image,
           u.first_name as agent_name,
           f.created_at as favorited_at
    FROM favorites f
    JOIN properties p ON f.property_id = p.id
    LEFT JOIN property_images pi ON p.id = pi.property_id AND pi.is_primary = 1
    LEFT JOIN users u ON p.agent_id = u.id
    WHERE f.user_id = ?
    ORDER BY f.created_at DESC
");
$stmt->execute([$userId]);
$favorites = $stmt->fetchAll();
$favoritesCount = count($favorites);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Избранное | Elsesser & Co.</title>

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
                        <li><a href="properties.php?category=sale" class="nav__link">Купить</a></li>
                        <li><a href="properties.php?category=rent" class="nav__link">Аренда</a></li>
                        <li><a href="favorites.php" class="nav__link nav__link--active">
                            <i class="fas fa-heart"></i> Избранное
                            <?php if ($favoritesCount > 0): ?>
                            <span class="badge"><?= $favoritesCount ?></span>
                            <?php endif; ?>
                        </a></li>
                    </ul>
                    <?php include __DIR__ . '/includes/nav-compare-link.php'; ?>
                    <div class="user-menu">
                        <a href="dashboard.php" class="btn btn--secondary">
                            <i class="fas fa-user"></i> <?= escape($user['name']) ?>
                        </a>
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

    <!-- Main Content -->
    <main class="favorites-page">
        <div class="container">
            <div class="favorites-header">
                <div>
                    <h1>Избранное</h1>
                    <p><?= $favoritesCount ?> <?= $favoritesCount === 1 ? 'объект' : ($favoritesCount < 5 ? 'объекта' : 'объектов') ?></p>
                </div>
                <a href="properties.php" class="btn btn--secondary">
                    <i class="fas fa-plus"></i> Добавить ещё
                </a>
            </div>

            <?php if (empty($favorites)): ?>
            <div class="empty-state">
                <div class="empty-state__icon">
                    <i class="far fa-heart"></i>
                </div>
                <h3>Избранное пусто</h3>
                <p>Добавляйте понравившиеся объекты в избранное, чтобы не потерять их</p>
                <a href="properties.php" class="btn btn--primary btn--lg">Найти недвижимость</a>
            </div>
            <?php else: ?>
            <div class="properties-grid favorites-grid-full">
                <?php foreach ($favorites as $property): ?>
                <article class="property-card property-card--compact" data-id="<?= $property['id'] ?>" data-property-id="<?= $property['id'] ?>">
                    <div class="property-card__image">
                        <a href="property.php?id=<?= $property['id'] ?>">
                            <img src="<?= escape($property['primary_image'] ?? 'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?w=600&q=80') ?>" 
                                 alt="<?= escape($property['title']) ?>" 
                                 class="property-card__img"
                                 loading="lazy">
                        </a>
                        <button class="property-card__favorite favorite-btn favorite-btn--active" 
                                data-property-id="<?= $property['id'] ?>"
                                title="Удалить из избранного">
                            <i class="fas fa-heart"></i>
                        </button>
                        <div class="property-card__type">
                            <?= $property['category'] === 'rent' ? 'Аренда' : 'Продажа' ?>
                        </div>
                    </div>
                    <div class="property-card__body">
                        <div class="property-card__price">
                            <?= formatPrice($property['price']) ?>
                            <?php if ($property['category'] === 'rent'): ?>
                            <span class="property-card__period">/мес</span>
                            <?php endif; ?>
                        </div>
                        <h3 class="property-card__title">
                            <a href="property.php?id=<?= $property['id'] ?>">
                                <?= escape($property['title_ru'] ?? $property['title']) ?>
                            </a>
                        </h3>
                        <div class="property-card__location">
                            <i class="fas fa-map-marker-alt"></i>
                            <?= escape($property['location']) ?>
                        </div>
                        <div class="property-card__footer">
                            <div class="property-card__specs">
                                <span class="property-card__spec"><i class="fas fa-bed"></i> <?= $property['bedrooms'] ?></span>
                                <span class="property-card__spec"><i class="fas fa-bath"></i> <?= $property['bathrooms'] ?></span>
                                <span class="property-card__spec"><i class="fas fa-vector-square"></i> <?= number_format($property['area_sqft']) ?> м²</span>
                            </div>
                            <a href="chat.php?user=<?= $property['agent_id'] ?>&property=<?= $property['id'] ?>" 
                               class="property-card__whatsapp">
                                <i class="fas fa-comment"></i>
                                Чат с агентом
                            </a>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <!-- Scripts -->
    <script src="js/navigation.js"></script>
    <script src="js/favorites.js"></script>
    <?php include __DIR__ . '/includes/compare-bar.php'; ?>

</body>
</html>
