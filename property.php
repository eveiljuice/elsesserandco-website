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

// Похожие объекты — взвешенный скоринг (тот же район + комнаты + цена ±25%),
// без ORDER BY RAND() (антипаттерн на больших таблицах).
$priceMin = (float)$property['price'] * 0.75;
$priceMax = (float)$property['price'] * 1.25;
$stmt = $pdo->prepare("
    SELECT p.*, pi.image_url as primary_image,
           (
               (p.district_id <=> ?) * 3
             + (p.bedrooms = ?) * 2
             + (p.price BETWEEN ? AND ?) * 2
           ) AS similarity_score
    FROM properties p
    LEFT JOIN property_images pi ON p.id = pi.property_id AND pi.is_primary = 1
    WHERE p.id != ?
      AND p.status = 'available'
      AND p.category = ?
    ORDER BY similarity_score DESC, p.featured DESC, p.created_at DESC
    LIMIT 3
");
$stmt->execute([
    $property['district_id'],
    $property['bedrooms'],
    $priceMin,
    $priceMax,
    $propertyId,
    $property['category'],
]);
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

// ===== JSON-LD Schema.org =====
$canonicalUrl = getBaseUrl() . '/property.php?id=' . $propertyId;
$jsonLd = [
    '@context'  => 'https://schema.org',
    '@type'     => $isRent ? 'Apartment' : 'Residence',
    'name'      => $pageTitle,
    'description' => $property['description_ru'] ?? $property['description'] ?? $pageTitle,
    'url'       => $canonicalUrl,
    'image'     => array_map(fn($im) => $im['image_url'], $images),
    'floorSize' => [
        '@type'    => 'QuantitativeValue',
        'value'    => (float)($property['area_total'] ?? $property['area_sqft']),
        'unitText' => 'm2',
    ],
    'address' => [
        '@type'           => 'PostalAddress',
        'addressLocality' => 'Екатеринбург',
        'addressRegion'   => 'Свердловская область',
        'addressCountry'  => 'RU',
        'streetAddress'   => $property['street'] ?? $property['location'] ?? '',
    ],
    'offers' => [
        '@type'         => 'Offer',
        'price'         => (float)$property['price'],
        'priceCurrency' => 'RUB',
        'availability'  => $property['status'] === 'available' ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
        'businessFunction' => $isRent ? 'https://schema.org/LeaseOut' : 'https://schema.org/Sell',
        'url'           => $canonicalUrl,
    ],
];
// Schema.org не допускает numberOfRooms: 0 (для студий поле опускаем)
if ((int)$property['bedrooms'] > 0) {
    $jsonLd['numberOfRooms'] = (int)$property['bedrooms'];
}
if (!empty($property['latitude']) && !empty($property['longitude'])) {
    $jsonLd['geo'] = [
        '@type'     => 'GeoCoordinates',
        'latitude'  => (float)$property['latitude'],
        'longitude' => (float)$property['longitude'],
    ];
}
if ($reviewsCount > 0 && $avgRating > 0) {
    $jsonLd['aggregateRating'] = [
        '@type'       => 'AggregateRating',
        'ratingValue' => $avgRating,
        'reviewCount' => (int)$reviewsCount,
        'bestRating'  => 5,
        'worstRating' => 1,
    ];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= escape($pageTitle) ?> - <?= formatPrice($property['price']) ?>. <?= escape($property['street'] ?? $property['location']) ?>">
    <title><?= escape($pageTitle) ?> | Elsesser & Co.</title>
    <link rel="canonical" href="<?= escape($canonicalUrl) ?>">

    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:locale" content="ru_RU">
    <meta property="og:title" content="<?= escape($pageTitle) ?> | Elsesser & Co.">
    <meta property="og:description" content="<?= escape($property['description_ru'] ?? $pageTitle) ?>">
    <meta property="og:url" content="<?= escape($canonicalUrl) ?>">
    <?php if (!empty($primaryImage)): ?>
    <meta property="og:image" content="<?= escape($primaryImage) ?>">
    <?php endif; ?>

    <!-- JSON-LD Schema.org -->
    <script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

    <link rel="icon" type="image/png" href="images/favicon.png">
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#00736c">
    <meta name="vapid-public-key" content="<?= escape((string)Config::get('VAPID_PUBLIC_KEY', '')) ?>">
    <meta name="csrf-token" content="<?= escape(generateCSRFToken()) ?>">
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

            <!-- Mortgage Calculator (только для продажи) -->
            <?php if (!$isRent): ?>
            <section class="mortgage" data-mortgage>
                <h2 class="mortgage__title">Ипотечный калькулятор</h2>
                <div class="mortgage__row">
                    <div>
                        <label>Стоимость объекта, ₽</label>
                        <input type="number" data-mortgage-price min="100000" step="10000"
                               value="<?= (int)$property['price'] ?>">
                    </div>
                    <div>
                        <label>Первоначальный взнос: <span data-mortgage-down-out>20 %</span></label>
                        <input type="range" data-mortgage-down min="0" max="90" step="5" value="20">
                    </div>
                </div>
                <div class="mortgage__row">
                    <div>
                        <label>Ставка: <span data-mortgage-rate-out>16 %</span></label>
                        <input type="range" data-mortgage-rate min="3" max="30" step="0.1" value="16">
                    </div>
                    <div>
                        <label>Срок: <span data-mortgage-years-out>20 лет</span></label>
                        <input type="range" data-mortgage-years min="1" max="30" step="1" value="20">
                    </div>
                </div>
                <div class="mortgage__result">
                    <div>
                        <div class="mortgage__result-label">Ежемесячный платёж</div>
                        <div class="mortgage__result-value" data-mortgage-monthly>—</div>
                    </div>
                    <div>
                        <div class="mortgage__result-label">Общая сумма</div>
                        <div class="mortgage__result-value" data-mortgage-total>—</div>
                    </div>
                    <div>
                        <div class="mortgage__result-label">Переплата</div>
                        <div class="mortgage__result-value" data-mortgage-overpay>—</div>
                    </div>
                </div>
                <p style="margin-top:12px;color:var(--color-text-light);font-size:var(--text-xs);">
                    Расчёт носит ознакомительный характер. Точные условия уточняйте у банка.
                </p>
            </section>
            <script src="js/mortgage.js" defer></script>
            <?php endif; ?>

            <section class="utilities-calc" data-utilities-calc>
                <h2 class="mortgage__title">Расходы на содержание</h2>
                <div class="mortgage__row">
                    <label>Площадь, м² <input type="number" data-u-area value="<?= (float)($property['area_total'] ?? $property['area_sqft'] ?? 50) ?>" min="10"></label>
                    <label>ЖКХ, ₽/м² <input type="number" data-u-hoa value="45" min="0"></label>
                    <label>Налог, ₽/год <input type="number" data-u-tax value="0" min="0"></label>
                </div>
                <p class="utilities-calc__out" data-u-total></p>
            </section>
            <script src="js/utilities.js" defer></script>

            <?php if (!empty($property['latitude']) && !empty($property['longitude'])): ?>
            <section class="property-map">
                <h2>На карте</h2>
                <div id="yandexMap" class="yandex-map" style="height:320px;border-radius:12px;"
                     data-lat="<?= (float)$property['latitude'] ?>"
                     data-lng="<?= (float)$property['longitude'] ?>"
                     data-title="<?= escape($property['title_ru'] ?? $property['title']) ?>"></div>
            </section>
            <script>window.YANDEX_MAPS_KEY = <?= json_encode(Config::get('YANDEX_MAPS_API_KEY', '')) ?>;</script>
            <script src="js/yandex-maps.js" defer></script>
            <?php endif; ?>

            <!-- Similar Properties -->
            <?php if (!empty($similarProperties)): ?>
            <section class="similar-section">
                <h2 class="similar-section__title">Похожие объекты</h2>
                <div class="similar-slider" data-similar-slider>
                    <div class="similar-slider__track">
                        <?php foreach ($similarProperties as $similar): ?>
                        <article class="property-card property-card--compact similar-slider__slide">
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
                    <div class="similar-slider__dots" role="tablist" aria-label="Похожие объекты"></div>
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
    <script src="js/pwa.js" defer></script>
    <script src="js/similar-carousel.js" defer></script>
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
</body>
</html>
