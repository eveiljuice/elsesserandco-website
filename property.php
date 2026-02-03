<?php
/**
 * Property Detail Page - Elsesser & Co.
 * Детальная карточка объекта недвижимости (Екатеринбург)
 */

require_once __DIR__ . '/includes/config/database.php';
require_once __DIR__ . '/includes/auth/check_auth.php';

$user = getUserData();
$pdo = getDBConnection();

$propertyId = (int)($_GET['id'] ?? 0);

if ($propertyId <= 0) {
    header("Location: properties.php");
    exit;
}

// Получаем данные объекта
$stmt = $pdo->prepare("
    SELECT p.*, 
           u.first_name as agent_name, u.last_name as agent_last_name, 
           u.phone as agent_phone, u.email as agent_email,
           d.name as district_name
    FROM properties p
    LEFT JOIN users u ON p.agent_id = u.id
    LEFT JOIN ekb_districts d ON p.district_id = d.id
    WHERE p.id = ?
");
$stmt->execute([$propertyId]);
$property = $stmt->fetch();

if (!$property) {
    header("Location: properties.php");
    exit;
}

// Увеличиваем счётчик просмотров
$pdo->prepare("UPDATE properties SET views_count = views_count + 1 WHERE id = ?")->execute([$propertyId]);

// Изображения
$stmt = $pdo->prepare("SELECT * FROM property_images WHERE property_id = ? ORDER BY is_primary DESC, sort_order ASC");
$stmt->execute([$propertyId]);
$images = $stmt->fetchAll();

// Удобства
$stmt = $pdo->prepare("
    SELECT a.* FROM amenities a
    JOIN property_amenities pa ON a.id = pa.amenity_id
    WHERE pa.property_id = ?
");
$stmt->execute([$propertyId]);
$amenities = $stmt->fetchAll();

// Избранное
$isFavorite = false;
if ($user['logged_in']) {
    $stmt = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND property_id = ?");
    $stmt->execute([$user['id'], $propertyId]);
    $isFavorite = (bool)$stmt->fetch();
}

// Похожие объекты
$stmt = $pdo->prepare("
    SELECT p.*, pi.image_url as primary_image
    FROM properties p
    LEFT JOIN property_images pi ON p.id = pi.property_id AND pi.is_primary = 1
    WHERE p.id != ? 
      AND p.status = 'available'
      AND p.category = ?
      AND (p.district_id = ? OR p.bedrooms = ?)
    ORDER BY RAND()
    LIMIT 3
");
$stmt->execute([$propertyId, $property['category'], $property['district_id'], $property['bedrooms']]);
$similarProperties = $stmt->fetchAll();

// Отзывы об объекте
$stmt = $pdo->prepare("
    SELECT r.*, u.first_name, u.last_name
    FROM reviews r
    LEFT JOIN users u ON r.user_id = u.id
    WHERE r.property_id = ? AND r.is_approved = 1
    ORDER BY r.created_at DESC
    LIMIT 10
");
$stmt->execute([$propertyId]);
$reviews = $stmt->fetchAll();

// Средний рейтинг
$stmt = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as count FROM reviews WHERE property_id = ? AND is_approved = 1");
$stmt->execute([$propertyId]);
$ratingData = $stmt->fetch();
$avgRating = round($ratingData['avg_rating'] ?? 0, 1);
$reviewsCount = $ratingData['count'] ?? 0;

$primaryImage = $images[0]['image_url'] ?? 'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?w=1200&q=80';

// Форматируем заголовок
$roomsText = match($property['bedrooms']) {
    0 => 'Студия',
    1 => '1-комн. кв.',
    2 => '2-комн. кв.',
    3 => '3-комн. кв.',
    4 => '4-комн. кв.',
    default => $property['bedrooms'] . '-комн. кв.'
};

$pageTitle = $property['title_ru'] ?: ($roomsText . ', ' . number_format($property['area_total'] ?? $property['area_sqft'], 1) . ' м²');
$isRent = $property['category'] === 'rent';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= escape($pageTitle) ?> - <?= formatPrice($property['price']) ?>. <?= escape($property['street'] ?? $property['location']) ?>">
    <title><?= escape($pageTitle) ?> | Elsesser & Co.</title>

    <link rel="icon" type="image/png" href="images/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <link rel="stylesheet" href="css/reset.css">
    <link rel="stylesheet" href="css/variables.css">
    <link rel="stylesheet" href="css/style.css">
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
                        <li><a href="new-buildings.php" class="nav__link">Новостройки</a></li>
                        <li><a href="about.html" class="nav__link">О нас</a></li>
                    </ul>
                    <?php if ($user['logged_in']): ?>
                    <a href="favorites.php" class="nav__link"><i class="fas fa-heart"></i></a>
                    <a href="dashboard.php" class="btn btn--secondary"><?= escape($user['name']) ?></a>
                    <?php else: ?>
                    <a href="login.php" class="btn btn--secondary">Войти</a>
                    <?php endif; ?>
                </nav>
                
                <button class="hamburger" id="hamburger" aria-label="Меню">
                    <span class="hamburger__line"></span>
                    <span class="hamburger__line"></span>
                    <span class="hamburger__line"></span>
                </button>
            </div>
        </div>
    </header>

    <?php include __DIR__ . '/includes/mobile-menu.php'; ?>

    <!-- Property Detail -->
    <main class="property-page">
        <!-- Gallery -->
        <section class="property-gallery">
            <div class="property-gallery__main">
                <img src="<?= escape($primaryImage) ?>" alt="<?= escape($pageTitle) ?>" id="mainImage">
                <?php if (count($images) > 1): ?>
                <button class="property-gallery__nav property-gallery__nav--prev" onclick="prevImage()">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button class="property-gallery__nav property-gallery__nav--next" onclick="nextImage()">
                    <i class="fas fa-chevron-right"></i>
                </button>
                <div class="property-gallery__counter">
                    <span id="currentImageIndex">1</span> / <?= count($images) ?>
                </div>
                <?php endif; ?>
                <button class="property-gallery__favorite favorite-btn <?= $isFavorite ? 'favorite-btn--active' : '' ?>" 
                        data-property-id="<?= $property['id'] ?>">
                    <i class="<?= $isFavorite ? 'fas' : 'far' ?> fa-heart"></i>
                    <?= $isFavorite ? 'В избранном' : 'В избранное' ?>
                </button>
            </div>
            <?php if (count($images) > 1): ?>
            <div class="property-gallery__thumbs">
                <?php foreach ($images as $index => $image): ?>
                <img src="<?= escape($image['image_url']) ?>" 
                     alt="Фото <?= $index + 1 ?>" 
                     class="property-gallery__thumb <?= $index === 0 ? 'active' : '' ?>"
                     data-index="<?= $index ?>"
                     onclick="goToImage(<?= $index ?>)">
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>

        <div class="container">
            <div class="property-layout">
                <!-- Main Content -->
                <div class="property-main">
                    
                    <!-- Header -->
                    <div class="property-header">
                        <div class="property-header__info">
                            <span class="property-tag property-tag--<?= $isRent ? 'rent' : 'sale' ?>">
                                <?= $isRent ? 'Аренда' : 'Продажа' ?>
                            </span>
                            <?php if ($property['is_new_building']): ?>
                            <span class="property-tag property-tag--new">Новостройка</span>
                            <?php endif; ?>
                            
                            <h1 class="property-title"><?= escape($pageTitle) ?></h1>
                            
                            <div class="property-address">
                                <i class="fas fa-map-marker-alt"></i>
                                <?php if ($property['street']): ?>
                                <?= escape($property['street']) ?><?php if ($property['house_number']): ?>, <?= escape($property['house_number']) ?><?php endif; ?>
                                <?php else: ?>
                                <?= escape($property['location']) ?>
                                <?php endif; ?>
                                <?php if ($property['district_name']): ?>
                                <span class="property-address__district"><?= escape($property['district_name']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="property-price-box">
                            <div class="property-price"><?= formatPrice($property['price']) ?></div>
                            <?php if ($isRent): ?>
                            <span class="property-price__period">/мес</span>
                            <?php endif; ?>
                            <?php if ($property['area_total']): ?>
                            <div class="property-price__sqm">
                                <?= formatPrice(round($property['price'] / $property['area_total'])) ?>/м²
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Key Metrics -->
                    <div class="property-metrics">
                        <div class="property-metric">
                            <i class="fas fa-door-open"></i>
                            <div class="property-metric__value"><?= $property['bedrooms'] == 0 ? 'Студия' : $property['bedrooms'] ?></div>
                            <div class="property-metric__label"><?= $property['bedrooms'] == 0 ? '' : 'комнат' ?></div>
                        </div>
                        <div class="property-metric">
                            <i class="fas fa-ruler-combined"></i>
                            <div class="property-metric__value"><?= number_format($property['area_total'] ?? $property['area_sqft'], 1) ?></div>
                            <div class="property-metric__label">м² общая</div>
                        </div>
                        <?php if ($property['area_living']): ?>
                        <div class="property-metric">
                            <i class="fas fa-couch"></i>
                            <div class="property-metric__value"><?= number_format($property['area_living'], 1) ?></div>
                            <div class="property-metric__label">м² жилая</div>
                        </div>
                        <?php endif; ?>
                        <?php if ($property['area_kitchen']): ?>
                        <div class="property-metric">
                            <i class="fas fa-utensils"></i>
                            <div class="property-metric__value"><?= number_format($property['area_kitchen'], 1) ?></div>
                            <div class="property-metric__label">м² кухня</div>
                        </div>
                        <?php endif; ?>
                        <?php if ($property['floor_number']): ?>
                        <div class="property-metric">
                            <i class="fas fa-building"></i>
                            <div class="property-metric__value"><?= $property['floor_number'] ?>/<?= $property['total_floors'] ?: '?' ?></div>
                            <div class="property-metric__label">этаж</div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Description -->
                    <?php if ($property['description_ru'] || $property['description']): ?>
                    <section class="property-section">
                        <h2 class="property-section__title">Описание</h2>
                        <div class="property-description">
                            <?= nl2br(escape($property['description_ru'] ?? $property['description'])) ?>
                        </div>
                    </section>
                    <?php endif; ?>

                    <!-- Apartment Details -->
                    <section class="property-section">
                        <h2 class="property-section__title">Характеристики квартиры</h2>
                        <div class="property-specs">
                            <div class="property-spec">
                                <span class="property-spec__icon"><i class="fas fa-ruler-combined"></i></span>
                                <span class="property-spec__label">Общая площадь</span>
                                <span class="property-spec__value"><?= number_format($property['area_total'] ?? $property['area_sqft'], 1) ?> м²</span>
                            </div>
                            <?php if ($property['area_living']): ?>
                            <div class="property-spec">
                                <span class="property-spec__icon"><i class="fas fa-couch"></i></span>
                                <span class="property-spec__label">Жилая площадь</span>
                                <span class="property-spec__value"><?= number_format($property['area_living'], 1) ?> м²</span>
                            </div>
                            <?php endif; ?>
                            <?php if ($property['area_kitchen']): ?>
                            <div class="property-spec">
                                <span class="property-spec__icon"><i class="fas fa-utensils"></i></span>
                                <span class="property-spec__label">Площадь кухни</span>
                                <span class="property-spec__value"><?= number_format($property['area_kitchen'], 1) ?> м²</span>
                            </div>
                            <?php endif; ?>
                            <div class="property-spec">
                                <span class="property-spec__icon"><i class="fas fa-door-open"></i></span>
                                <span class="property-spec__label">Комнат</span>
                                <span class="property-spec__value"><?= $property['bedrooms'] == 0 ? 'Студия' : $property['bedrooms'] ?></span>
                            </div>
                            <?php if ($property['rooms_type']): ?>
                            <div class="property-spec">
                                <span class="property-spec__icon"><i class="fas fa-th-large"></i></span>
                                <span class="property-spec__label">Планировка</span>
                                <span class="property-spec__value"><?= match($property['rooms_type']) {
                                    'isolated' => 'Изолированные',
                                    'adjacent' => 'Смежные',
                                    'mixed' => 'Смешанные',
                                    default => $property['rooms_type']
                                } ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($property['floor_number']): ?>
                            <div class="property-spec">
                                <span class="property-spec__icon"><i class="fas fa-building"></i></span>
                                <span class="property-spec__label">Этаж</span>
                                <span class="property-spec__value"><?= $property['floor_number'] ?> из <?= $property['total_floors'] ?: '?' ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($property['bathrooms']): ?>
                            <div class="property-spec">
                                <span class="property-spec__icon"><i class="fas fa-bath"></i></span>
                                <span class="property-spec__label">Санузел</span>
                                <span class="property-spec__value">
                                    <?= $property['bathrooms'] ?>
                                    <?php if ($property['bathroom_type']): ?>
                                    (<?= match($property['bathroom_type']) {
                                        'combined' => 'совмещённый',
                                        'separate' => 'раздельный',
                                        'multiple' => '2 и более',
                                        default => $property['bathroom_type']
                                    } ?>)
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php endif; ?>
                            <?php if ($property['balcony'] && $property['balcony'] !== 'none'): ?>
                            <div class="property-spec">
                                <span class="property-spec__icon"><i class="fas fa-archway"></i></span>
                                <span class="property-spec__label">Балкон/лоджия</span>
                                <span class="property-spec__value"><?= match($property['balcony']) {
                                    'balcony' => 'Балкон',
                                    'loggia' => 'Лоджия',
                                    'both' => 'Балкон + Лоджия',
                                    default => $property['balcony']
                                } ?><?= $property['balcony_count'] > 1 ? ' (' . $property['balcony_count'] . ')' : '' ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($property['window_view']): ?>
                            <div class="property-spec">
                                <span class="property-spec__icon"><i class="fas fa-eye"></i></span>
                                <span class="property-spec__label">Вид из окон</span>
                                <span class="property-spec__value"><?= match($property['window_view']) {
                                    'yard' => 'Во двор',
                                    'street' => 'На улицу',
                                    'park' => 'На парк',
                                    'river' => 'На реку',
                                    'city' => 'Панорамный',
                                    'both' => 'Двусторонний',
                                    default => $property['window_view']
                                } ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($property['renovation']): ?>
                            <div class="property-spec">
                                <span class="property-spec__icon"><i class="fas fa-paint-roller"></i></span>
                                <span class="property-spec__label">Ремонт</span>
                                <span class="property-spec__value"><?= match($property['renovation']) {
                                    'designer' => 'Дизайнерский',
                                    'euro' => 'Евроремонт',
                                    'cosmetic' => 'Косметический',
                                    'needs-repair' => 'Требует ремонта',
                                    'turnkey' => 'Под ключ',
                                    'pre-finish' => 'Предчистовая',
                                    'rough-finish' => 'Черновая',
                                    default => $property['renovation']
                                } ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="property-spec">
                                <span class="property-spec__icon"><i class="fas fa-couch"></i></span>
                                <span class="property-spec__label">Мебель</span>
                                <span class="property-spec__value"><?= match($property['furnished']) {
                                    'furnished' => 'С мебелью',
                                    'semi-furnished' => 'Частично',
                                    default => 'Без мебели'
                                } ?></span>
                            </div>
                            <?php if ($property['ceiling_height']): ?>
                            <div class="property-spec">
                                <span class="property-spec__icon"><i class="fas fa-arrows-alt-v"></i></span>
                                <span class="property-spec__label">Высота потолков</span>
                                <span class="property-spec__value"><?= number_format($property['ceiling_height'], 2) ?> м</span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </section>

                    <!-- House Details -->
                    <section class="property-section">
                        <h2 class="property-section__title">О доме</h2>
                        <div class="property-specs">
                            <?php if ($property['house_type']): ?>
                            <div class="property-spec">
                                <span class="property-spec__icon"><i class="fas fa-city"></i></span>
                                <span class="property-spec__label">Тип дома</span>
                                <span class="property-spec__value"><?= match($property['house_type']) {
                                    'panel' => 'Панельный',
                                    'brick' => 'Кирпичный',
                                    'monolith' => 'Монолитный',
                                    'monolith-brick' => 'Монолит-кирпич',
                                    'block' => 'Блочный',
                                    'wood' => 'Деревянный',
                                    'stalin' => 'Сталинка',
                                    'khrushchev' => 'Хрущёвка',
                                    default => $property['house_type']
                                } ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($property['build_year']): ?>
                            <div class="property-spec">
                                <span class="property-spec__icon"><i class="fas fa-calendar-alt"></i></span>
                                <span class="property-spec__label">Год постройки</span>
                                <span class="property-spec__value"><?= $property['build_year'] ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($property['total_floors']): ?>
                            <div class="property-spec">
                                <span class="property-spec__icon"><i class="fas fa-layer-group"></i></span>
                                <span class="property-spec__label">Этажей в доме</span>
                                <span class="property-spec__value"><?= $property['total_floors'] ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($property['has_elevator'] !== null): ?>
                            <div class="property-spec">
                                <span class="property-spec__icon"><i class="fas fa-elevator"></i></span>
                                <span class="property-spec__label">Лифт</span>
                                <span class="property-spec__value"><?= $property['has_elevator'] ? 'Есть' : 'Нет' ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($property['has_garbage_chute'] !== null): ?>
                            <div class="property-spec">
                                <span class="property-spec__icon"><i class="fas fa-trash-alt"></i></span>
                                <span class="property-spec__label">Мусоропровод</span>
                                <span class="property-spec__value"><?= $property['has_garbage_chute'] ? 'Есть' : 'Нет' ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($property['building_name']): ?>
                            <div class="property-spec">
                                <span class="property-spec__icon"><i class="fas fa-building"></i></span>
                                <span class="property-spec__label">ЖК/Здание</span>
                                <span class="property-spec__value"><?= escape($property['building_name']) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </section>

                    <!-- Transport -->
                    <?php if ($property['metro_station'] || $property['transport_info']): ?>
                    <section class="property-section">
                        <h2 class="property-section__title">Транспортная доступность</h2>
                        <?php if ($property['metro_station']): ?>
                        <div class="property-metro">
                            <i class="fas fa-subway"></i>
                            <span class="property-metro__station"><?= escape($property['metro_station']) ?></span>
                            <?php if ($property['metro_minutes']): ?>
                            <span class="property-metro__time">
                                <?= $property['metro_minutes'] ?> мин. <?= $property['metro_walk_type'] === 'walk' ? 'пешком' : 'на транспорте' ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($property['transport_info']): ?>
                        <p class="property-transport-info"><?= nl2br(escape($property['transport_info'])) ?></p>
                        <?php endif; ?>
                    </section>
                    <?php endif; ?>

                    <!-- Amenities -->
                    <?php if (!empty($amenities)): ?>
                    <section class="property-section">
                        <h2 class="property-section__title">Удобства</h2>
                        <div class="property-amenities">
                            <?php foreach ($amenities as $amenity): ?>
                            <div class="property-amenity">
                                <i class="fas <?= escape($amenity['icon'] ?? 'fa-check') ?>"></i>
                                <?= escape($amenity['name_ru'] ?? $amenity['name']) ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                    <?php endif; ?>

                    <!-- Rent Conditions -->
                    <?php if ($isRent && ($property['deposit'] || $property['living_conditions'])): ?>
                    <section class="property-section">
                        <h2 class="property-section__title">Условия аренды</h2>
                        <div class="property-specs">
                            <?php if ($property['deposit']): ?>
                            <div class="property-spec">
                                <span class="property-spec__icon"><i class="fas fa-money-bill"></i></span>
                                <span class="property-spec__label">Залог</span>
                                <span class="property-spec__value"><?= formatPrice($property['deposit']) ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="property-spec">
                                <span class="property-spec__icon"><i class="fas fa-calendar-check"></i></span>
                                <span class="property-spec__label">Предоплата</span>
                                <span class="property-spec__value"><?= $property['prepayment_months'] ?? 1 ?> мес.</span>
                            </div>
                            <div class="property-spec">
                                <span class="property-spec__icon"><i class="fas fa-file-invoice"></i></span>
                                <span class="property-spec__label">Коммунальные</span>
                                <span class="property-spec__value"><?= $property['utilities_included'] ? 'Включены' : 'Отдельно' ?></span>
                            </div>
                            <?php if ($property['living_conditions']): ?>
                            <div class="property-spec property-spec--full">
                                <span class="property-spec__icon"><i class="fas fa-exclamation-circle"></i></span>
                                <span class="property-spec__label">Условия</span>
                                <span class="property-spec__value">
                                    <?php 
                                    $conditions = explode(',', $property['living_conditions']);
                                    $conditionsText = [];
                                    foreach ($conditions as $c) {
                                        $conditionsText[] = match(trim($c)) {
                                            'no_animals' => 'Без животных',
                                            'no_children' => 'Без детей',
                                            'families_only' => 'Только семьям',
                                            'couples_only' => 'Только парам',
                                            default => $c
                                        };
                                    }
                                    echo implode(', ', $conditionsText);
                                    ?>
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </section>
                    <?php endif; ?>

                </div>

                <!-- Sidebar -->
                <aside class="property-sidebar">
                    <!-- Agent Card -->
                    <div class="agent-card">
                        <img src="https://images.unsplash.com/photo-1560250097-0b93528c311a?w=200&q=80" 
                             alt="<?= escape($property['agent_name'] ?? 'Агент') ?>" 
                             class="agent-card__photo">
                        <h3 class="agent-card__name">
                            <?= escape(($property['agent_name'] ?? 'Консультант') . ' ' . ($property['agent_last_name'] ?? '')) ?>
                        </h3>
                        <p class="agent-card__role">Эксперт по недвижимости</p>
                        
                        <div class="agent-card__actions">
                            <a href="chat.php?agent_id=<?= $property['agent_id'] ?>&property_id=<?= $property['id'] ?>" 
                               class="btn btn--primary btn--full">
                                <i class="fas fa-comments"></i> Чат с агентом
                            </a>
                        </div>
                    </div>
                </aside>
            </div>

            <!-- Similar Properties -->
            <?php if (!empty($similarProperties)): ?>
            <section class="similar-section">
                <h2 class="similar-section__title">Похожие объекты</h2>
                <div class="properties-grid properties-grid--3">
                    <?php foreach ($similarProperties as $similar): ?>
                    <article class="property-card">
                        <div class="property-card__image">
                            <a href="property.php?id=<?= $similar['id'] ?>">
                                <img src="<?= escape($similar['primary_image'] ?? 'https://via.placeholder.com/600x400') ?>" 
                                     alt="" class="property-card__img" loading="lazy">
                            </a>
                        </div>
                        <div class="property-card__body">
                            <div class="property-card__price">
                                <?= formatPrice($similar['price']) ?>
                                <?php if ($similar['category'] === 'rent'): ?><span>/мес</span><?php endif; ?>
                            </div>
                            <h3 class="property-card__title">
                                <a href="property.php?id=<?= $similar['id'] ?>">
                                    <?= escape($similar['title_ru'] ?? $similar['title']) ?>
                                </a>
                            </h3>
                            <div class="property-card__specs">
                                <span><i class="fas fa-door-open"></i> <?= $similar['bedrooms'] ?></span>
                                <span><i class="fas fa-ruler-combined"></i> <?= number_format($similar['area_total'] ?? $similar['area_sqft'], 1) ?> м²</span>
                                <?php if ($similar['floor_number']): ?>
                                <span><i class="fas fa-building"></i> <?= $similar['floor_number'] ?>/<?= $similar['total_floors'] ?: '?' ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>
            
            <!-- Reviews Section -->
            <section class="reviews-section" id="reviews">
                <div class="reviews-header">
                    <h2 class="reviews-section__title">
                        Отзывы
                        <?php if ($reviewsCount > 0): ?>
                        <span class="reviews-count">(<?= $reviewsCount ?>)</span>
                        <?php endif; ?>
                    </h2>
                    <?php if ($avgRating > 0): ?>
                    <div class="reviews-rating">
                        <div class="reviews-rating__stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="<?= $i <= round($avgRating) ? 'fas' : 'far' ?> fa-star"></i>
                            <?php endfor; ?>
                        </div>
                        <span class="reviews-rating__value"><?= $avgRating ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Review Form -->
                <?php if ($user['logged_in']): ?>
                <div class="review-form-container">
                    <h3>Оставить отзыв</h3>
                    <form id="reviewForm" class="review-form">
                        <input type="hidden" name="property_id" value="<?= $propertyId ?>">
                        
                        <div class="form-group">
                            <label class="form-label">Ваша оценка</label>
                            <div class="star-rating" id="starRating">
                                <input type="hidden" name="rating" id="ratingInput" value="5">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star star-rating__star" data-rating="<?= $i ?>"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="reviewName" class="form-label">Ваше имя</label>
                            <input type="text" id="reviewName" name="name" class="form-input" 
                                   value="<?= escape($user['name']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="reviewText" class="form-label">Текст отзыва</label>
                            <textarea id="reviewText" name="comment" class="form-textarea" rows="4" 
                                      placeholder="Поделитесь своим мнением об этом объекте..." required></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn--primary" id="submitReviewBtn">
                            <i class="fas fa-paper-plane"></i> Отправить отзыв
                        </button>
                        <div id="reviewMessage" class="review-message" style="display: none;"></div>
                    </form>
                </div>
                <?php else: ?>
                <div class="review-login-prompt">
                    <p>Чтобы оставить отзыв, <a href="login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>">войдите</a> или <a href="register.php">зарегистрируйтесь</a></p>
                </div>
                <?php endif; ?>
                
                <!-- Reviews List -->
                <div class="reviews-list">
                    <?php if (empty($reviews)): ?>
                    <div class="reviews-empty">
                        <i class="far fa-comment"></i>
                        <p>Пока нет отзывов. Будьте первым!</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($reviews as $review): ?>
                    <div class="review-item">
                        <div class="review-item__header">
                            <div class="review-item__avatar">
                                <?= strtoupper(substr($review['first_name'] ?? $review['reviewer_name'] ?? 'A', 0, 1)) ?>
                            </div>
                            <div class="review-item__info">
                                <div class="review-item__name">
                                    <?= escape($review['first_name'] ?? $review['reviewer_name'] ?? 'Аноним') ?>
                                    <?php if ($review['last_name']): ?>
                                    <?= escape(mb_substr($review['last_name'], 0, 1)) ?>.
                                    <?php endif; ?>
                                </div>
                                <div class="review-item__date">
                                    <?= date('d.m.Y', strtotime($review['created_at'])) ?>
                                </div>
                            </div>
                            <div class="review-item__rating">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="<?= $i <= $review['rating'] ? 'fas' : 'far' ?> fa-star"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="review-item__content">
                            <?= nl2br(escape($review['comment'])) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </main>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <script src="js/navigation.js"></script>
    <script src="js/favorites.js"></script>
    <script>
        const galleryImages = <?= json_encode(array_column($images, 'image_url')) ?>;
        let currentIndex = 0;
        
        function goToImage(index) {
            if (index < 0) index = galleryImages.length - 1;
            if (index >= galleryImages.length) index = 0;
            currentIndex = index;
            document.getElementById('mainImage').src = galleryImages[index];
            document.getElementById('currentImageIndex').textContent = index + 1;
            document.querySelectorAll('.property-gallery__thumb').forEach((t, i) => {
                t.classList.toggle('active', i === index);
            });
        }
        
        function nextImage() { goToImage(currentIndex + 1); }
        function prevImage() { goToImage(currentIndex - 1); }
        
        document.addEventListener('keydown', e => {
            if (e.key === 'ArrowLeft') prevImage();
            if (e.key === 'ArrowRight') nextImage();
        });
        
        // Star rating
        const starRating = document.getElementById('starRating');
        if (starRating) {
            const stars = starRating.querySelectorAll('.star-rating__star');
            const ratingInput = document.getElementById('ratingInput');
            
            function setRating(value) {
                ratingInput.value = value;
                stars.forEach((star, idx) => {
                    star.classList.toggle('active', idx < value);
                });
            }
            
            // Set initial rating
            setRating(5);
            
            stars.forEach(star => {
                star.addEventListener('click', function() {
                    setRating(parseInt(this.dataset.rating));
                });
                star.addEventListener('mouseover', function() {
                    const hoverRating = parseInt(this.dataset.rating);
                    stars.forEach((s, idx) => {
                        s.style.color = idx < hoverRating ? '#fbbf24' : '#d1d5db';
                    });
                });
                star.addEventListener('mouseout', function() {
                    stars.forEach((s, idx) => {
                        s.style.color = idx < parseInt(ratingInput.value) ? '#fbbf24' : '#d1d5db';
                    });
                });
            });
        }
        
        // Review form submission
        const reviewForm = document.getElementById('reviewForm');
        if (reviewForm) {
            reviewForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                const btn = document.getElementById('submitReviewBtn');
                const msg = document.getElementById('reviewMessage');
                const formData = new FormData(this);
                
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Отправка...';
                
                try {
                    const response = await fetch('/php/reviews/add_review.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();
                    
                    if (data.success) {
                        msg.className = 'review-message success';
                        msg.textContent = data.message || 'Отзыв отправлен на модерацию!';
                        msg.style.display = 'block';
                        this.reset();
                        document.getElementById('ratingInput').value = 5;
                        document.querySelectorAll('.star-rating__star').forEach((s, idx) => {
                            s.classList.toggle('active', idx < 5);
                        });
                    } else {
                        msg.className = 'review-message error';
                        msg.textContent = data.error || 'Ошибка при отправке отзыва';
                        msg.style.display = 'block';
                    }
                } catch (err) {
                    msg.className = 'review-message error';
                    msg.textContent = 'Произошла ошибка. Попробуйте позже.';
                    msg.style.display = 'block';
                } finally {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-paper-plane"></i> Отправить отзыв';
                    setTimeout(() => { msg.style.display = 'none'; }, 5000);
                }
            });
        }
    </script>

    <style>
        .property-page { padding-top: var(--header-height); }
        
        .property-gallery { background: var(--color-navy); }
        .property-gallery__main {
            position: relative;
            height: 500px;
            max-width: 1400px;
            margin: 0 auto;
        }
        .property-gallery__main img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .property-gallery__nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 50px;
            height: 50px;
            background: rgba(255,255,255,0.9);
            border: none;
            border-radius: 50%;
            cursor: pointer;
            font-size: var(--text-xl);
            color: var(--color-navy);
            z-index: 3;
        }
        .property-gallery__nav--prev { left: var(--space-6); }
        .property-gallery__nav--next { right: var(--space-6); }
        .property-gallery__counter {
            position: absolute;
            bottom: var(--space-6);
            left: 50%;
            transform: translateX(-50%);
            padding: var(--space-2) var(--space-4);
            background: rgba(0,0,0,0.6);
            color: white;
            border-radius: var(--radius-full);
            font-size: var(--text-sm);
        }
        .property-gallery__favorite {
            position: absolute;
            top: var(--space-6);
            right: var(--space-6);
            display: flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-3) var(--space-5);
            background: rgba(255,255,255,0.95);
            border-radius: var(--radius-full);
            font-size: var(--text-sm);
            font-weight: var(--font-medium);
            color: var(--color-text);
            cursor: pointer;
        }
        .property-gallery__favorite:hover,
        .property-gallery__favorite.favorite-btn--active { color: #dc2626; }
        .property-gallery__thumbs {
            display: flex;
            gap: var(--space-2);
            padding: var(--space-4);
            max-width: 1400px;
            margin: 0 auto;
            overflow-x: auto;
        }
        .property-gallery__thumb {
            width: 100px;
            height: 70px;
            object-fit: cover;
            border-radius: var(--radius-sm);
            cursor: pointer;
            opacity: 0.6;
            transition: opacity var(--transition-fast);
        }
        .property-gallery__thumb:hover,
        .property-gallery__thumb.active { opacity: 1; }
        
        .property-layout {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: var(--space-10);
            padding: var(--space-10) 0;
        }
        
        .property-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: var(--space-6);
            margin-bottom: var(--space-8);
            flex-wrap: wrap;
        }
        .property-tag {
            display: inline-block;
            padding: var(--space-1) var(--space-3);
            border-radius: var(--radius-sm);
            font-size: var(--text-xs);
            font-weight: var(--font-semibold);
            text-transform: uppercase;
            margin-bottom: var(--space-2);
            margin-right: var(--space-2);
        }
        .property-tag--sale { background: #dbeafe; color: #1e40af; }
        .property-tag--rent { background: #fef3c7; color: #92400e; }
        .property-tag--new { background: #d1fae5; color: #065f46; }
        .property-title {
            font-size: var(--text-3xl);
            font-weight: var(--font-bold);
            color: var(--color-navy);
            margin-bottom: var(--space-2);
        }
        .property-address {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            color: var(--color-text-light);
            flex-wrap: wrap;
        }
        .property-address i { color: var(--color-accent); }
        .property-address__district {
            padding: var(--space-1) var(--space-2);
            background: var(--color-light-gray);
            border-radius: var(--radius-sm);
            font-size: var(--text-sm);
        }
        .property-price-box { text-align: right; }
        .property-price {
            font-size: var(--text-3xl);
            font-weight: var(--font-bold);
            color: var(--color-navy);
        }
        .property-price__period {
            font-size: var(--text-lg);
            color: var(--color-text-light);
        }
        .property-price__sqm {
            font-size: var(--text-sm);
            color: var(--color-text-light);
        }
        
        .property-metrics {
            display: flex;
            gap: var(--space-6);
            padding: var(--space-6);
            background: var(--color-light-gray);
            border-radius: var(--radius-lg);
            margin-bottom: var(--space-8);
            flex-wrap: wrap;
        }
        .property-metric {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            min-width: 70px;
        }
        .property-metric i {
            font-size: var(--text-2xl);
            color: var(--color-accent);
            margin-bottom: var(--space-2);
        }
        .property-metric__value {
            font-size: var(--text-xl);
            font-weight: var(--font-bold);
            color: var(--color-navy);
        }
        .property-metric__label {
            font-size: var(--text-sm);
            color: var(--color-text-light);
        }
        
        .property-section {
            margin-bottom: var(--space-8);
        }
        .property-section__title {
            font-size: var(--text-xl);
            font-weight: var(--font-semibold);
            color: var(--color-navy);
            margin-bottom: var(--space-4);
            padding-bottom: var(--space-2);
            border-bottom: 2px solid var(--color-accent);
            display: inline-block;
        }
        .property-description {
            color: var(--color-text-light);
            line-height: 1.8;
        }
        
        .property-specs {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: var(--space-1);
        }
        .property-spec {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-3) var(--space-4);
            background: var(--color-light-gray);
            border-radius: var(--radius-sm);
        }
        .property-spec--full {
            grid-column: span 2;
        }
        .property-spec__icon {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            border-radius: var(--radius-sm);
            color: var(--color-accent);
        }
        .property-spec__label {
            flex: 1;
            color: var(--color-text-light);
            font-size: var(--text-sm);
        }
        .property-spec__value {
            font-weight: var(--font-medium);
            color: var(--color-navy);
        }
        
        .property-metro {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-4);
            background: linear-gradient(135deg, #ef4444, #dc2626);
            border-radius: var(--radius-md);
            color: white;
            margin-bottom: var(--space-4);
        }
        .property-metro i { font-size: var(--text-2xl); }
        .property-metro__station { font-weight: var(--font-semibold); font-size: var(--text-lg); }
        .property-metro__time {
            margin-left: auto;
            background: rgba(255,255,255,0.2);
            padding: var(--space-1) var(--space-3);
            border-radius: var(--radius-full);
            font-size: var(--text-sm);
        }
        .property-transport-info { color: var(--color-text-light); }
        
        .property-amenities {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: var(--space-3);
        }
        .property-amenity {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            font-size: var(--text-sm);
        }
        .property-amenity i {
            color: var(--color-accent);
            width: 20px;
        }
        
        /* Sidebar */
        .property-sidebar { position: sticky; top: calc(var(--header-height) + var(--space-6)); }
        .agent-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: var(--space-6);
            text-align: center;
            box-shadow: var(--shadow-md);
            margin-bottom: var(--space-6);
        }
        .agent-card__photo {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: var(--space-4);
        }
        .agent-card__name {
            font-size: var(--text-lg);
            font-weight: var(--font-semibold);
            margin-bottom: var(--space-1);
        }
        .agent-card__role {
            font-size: var(--text-sm);
            color: var(--color-text-light);
            margin-bottom: var(--space-4);
        }
        .agent-card__actions { display: flex; flex-direction: column; gap: var(--space-3); }
        .similar-section {
            padding: var(--space-12) 0;
            border-top: 1px solid var(--color-border);
        }
        .similar-section__title {
            font-size: var(--text-2xl);
            margin-bottom: var(--space-6);
        }
        .properties-grid--3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: var(--space-6);
        }
        
        @media (max-width: 1024px) {
            .property-layout { grid-template-columns: 1fr; }
            .property-sidebar { order: -1; position: static; }
            .property-gallery__main { height: 350px; }
            .properties-grid--3 { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .property-metrics { gap: var(--space-4); }
            .property-metric { min-width: 60px; }
            .property-specs { grid-template-columns: 1fr; }
            .property-spec--full { grid-column: span 1; }
            .property-amenities { grid-template-columns: repeat(2, 1fr); }
            .properties-grid--3 { grid-template-columns: 1fr; }
        }
        
        /* Reviews Section */
        .reviews-section {
            padding: var(--space-12) 0;
            border-top: 1px solid var(--color-border);
        }
        .reviews-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-8);
            flex-wrap: wrap;
            gap: var(--space-4);
        }
        .reviews-section__title {
            font-size: var(--text-2xl);
            margin: 0;
        }
        .reviews-count {
            font-weight: var(--font-normal);
            color: var(--color-text-light);
        }
        .reviews-rating {
            display: flex;
            align-items: center;
            gap: var(--space-2);
        }
        .reviews-rating__stars {
            color: #fbbf24;
            font-size: var(--text-lg);
        }
        .reviews-rating__value {
            font-size: var(--text-xl);
            font-weight: var(--font-bold);
            color: var(--color-navy);
        }
        
        .review-form-container {
            background: var(--color-light-gray);
            padding: var(--space-6);
            border-radius: var(--radius-lg);
            margin-bottom: var(--space-8);
        }
        .review-form-container h3 {
            margin-bottom: var(--space-4);
        }
        .star-rating {
            display: flex;
            gap: var(--space-1);
            font-size: var(--text-2xl);
        }
        .star-rating__star {
            cursor: pointer;
            color: #d1d5db;
            transition: color var(--transition-fast);
        }
        .star-rating__star.active,
        .star-rating__star:hover {
            color: #fbbf24;
        }
        .review-message {
            margin-top: var(--space-4);
            padding: var(--space-3);
            border-radius: var(--radius-md);
            font-size: var(--text-sm);
        }
        .review-message.success {
            background: #d1fae5;
            color: #065f46;
        }
        .review-message.error {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .review-login-prompt {
            background: var(--color-light-gray);
            padding: var(--space-6);
            border-radius: var(--radius-lg);
            margin-bottom: var(--space-8);
            text-align: center;
        }
        .review-login-prompt a {
            color: var(--color-accent);
            font-weight: var(--font-medium);
        }
        
        .reviews-list {
            display: flex;
            flex-direction: column;
            gap: var(--space-6);
        }
        .reviews-empty {
            text-align: center;
            padding: var(--space-12);
            color: var(--color-text-light);
        }
        .reviews-empty i {
            font-size: 48px;
            margin-bottom: var(--space-4);
            display: block;
        }
        
        .review-item {
            background: white;
            padding: var(--space-6);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
        }
        .review-item__header {
            display: flex;
            align-items: center;
            gap: var(--space-4);
            margin-bottom: var(--space-4);
        }
        .review-item__avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--color-navy);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: var(--font-bold);
            font-size: var(--text-lg);
        }
        .review-item__info {
            flex: 1;
        }
        .review-item__name {
            font-weight: var(--font-semibold);
        }
        .review-item__date {
            font-size: var(--text-sm);
            color: var(--color-text-light);
        }
        .review-item__rating {
            color: #fbbf24;
        }
        .review-item__content {
            color: var(--color-text);
            line-height: 1.6;
        }
    </style>
</body>
</html>
