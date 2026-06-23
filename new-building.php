<?php
/**
 * New Building Detail Page - Elsesser & Co.
 * Детальная карточка новостройки (ЖК) Екатеринбурга
 */

require_once __DIR__ . '/includes/config/database.php';
require_once __DIR__ . '/includes/auth/check_auth.php';

$user = getUserData();
$pdo = getDBConnection();

$buildingId = (int)($_GET['id'] ?? 0);

if ($buildingId <= 0) {
    header("Location: new-buildings.php");
    exit;
}

// Получаем данные новостройки
$stmt = $pdo->prepare("
    SELECT nb.*, 
           d.name as district_name,
           dev.name as developer_name,
           dev.website as developer_website,
           dev.logo as developer_logo,
           u.first_name as agent_name, 
           u.last_name as agent_last_name,
           u.phone as agent_phone,
           u.email as agent_email
    FROM new_buildings nb
    LEFT JOIN ekb_districts d ON nb.district_id = d.id
    LEFT JOIN developers dev ON nb.developer_id = dev.id
    LEFT JOIN users u ON nb.agent_id = u.id
    WHERE nb.id = ?
");
$stmt->execute([$buildingId]);
$building = $stmt->fetch();

if (!$building) {
    header("Location: new-buildings.php");
    exit;
}

// Увеличиваем счётчик просмотров
$pdo->prepare("UPDATE new_buildings SET views_count = views_count + 1 WHERE id = ?")->execute([$buildingId]);

// Изображения
$stmt = $pdo->prepare("SELECT * FROM new_building_images WHERE new_building_id = ? ORDER BY is_primary DESC, sort_order ASC");
$stmt->execute([$buildingId]);
$images = $stmt->fetchAll();

// Планировки
$stmt = $pdo->prepare("
    SELECT * FROM new_building_layouts 
    WHERE new_building_id = ? AND is_available = 1 
    ORDER BY rooms ASC
");
$stmt->execute([$buildingId]);
$layouts = $stmt->fetchAll();

// Похожие новостройки
$stmt = $pdo->prepare("
    SELECT nb.*, nbi.image_url as primary_image
    FROM new_buildings nb
    LEFT JOIN new_building_images nbi ON nb.id = nbi.new_building_id AND nbi.is_primary = 1
    WHERE nb.id != ? 
      AND nb.status = 'active'
      AND (nb.district_id = ? OR nb.developer_id = ?)
    ORDER BY RAND()
    LIMIT 3
");
$stmt->execute([$buildingId, $building['district_id'], $building['developer_id']]);
$similarBuildings = $stmt->fetchAll();

$primaryImage = $images[0]['image_url'] ?? 'https://via.placeholder.com/1200x600';
$pageTitle = 'ЖК «' . $building['name'] . '»';

// Формат срока сдачи
$completionText = '';
if ($building['is_completed']) {
    $completionText = 'Сдан';
} elseif ($building['completion_quarter'] && $building['completion_year']) {
    $completionText = 'Q' . $building['completion_quarter'] . ' ' . $building['completion_year'];
} elseif ($building['completion_year']) {
    $completionText = $building['completion_year'];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= escape($pageTitle) ?> - новостройка в Екатеринбурге. <?= $building['price_from'] ? 'От ' . formatPrice($building['price_from']) : 'Цена по запросу' ?>. <?= escape($building['address']) ?>">
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
                        <li><a href="new-buildings.php" class="nav__link nav__link--active">Новостройки</a></li>
                        <li><a href="about.php" class="nav__link">О нас</a></li>
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

    <!-- Building Detail -->
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
            </div>
            <?php if (count($images) > 1): ?>
            <div class="property-gallery__thumbs">
                <?php foreach ($images as $index => $image): ?>
                <img src="<?= imgSrc($image['image_url']) ?>" 
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
                            <span class="property-tag property-tag--new">Новостройка</span>
                            <?php if ($building['featured']): ?>
                            <span class="property-tag" style="background: #fbbf24; color: #92400e;">Рекомендуем</span>
                            <?php endif; ?>
                            <?php if ($completionText): ?>
                            <span class="property-tag" style="background: #dbeafe; color: #1e40af;">
                                <?= $completionText ?>
                            </span>
                            <?php endif; ?>
                            
                            <h1 class="property-title"><?= escape($pageTitle) ?></h1>
                            
                            <div class="property-address">
                                <i class="fas fa-map-marker-alt"></i>
                                <?= escape($building['address']) ?>
                                <?php if ($building['district_name']): ?>
                                <span class="property-address__district"><?= escape($building['district_name']) ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($building['developer_name']): ?>
                            <div class="developer-badge">
                                <i class="fas fa-hard-hat"></i>
                                <span>Застройщик: <strong><?= escape($building['developer_name']) ?></strong></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="property-price-box">
                            <?php if ($building['price_from']): ?>
                            <div class="property-price">от <?= formatPrice($building['price_from']) ?></div>
                            <?php if ($building['price_per_sqm_from']): ?>
                            <div class="property-price__sqm">
                                <?= formatPrice($building['price_per_sqm_from']) ?>/м²
                            </div>
                            <?php endif; ?>
                            <?php else: ?>
                            <div class="property-price" style="font-size: var(--text-xl);">Цена по запросу</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Key Metrics -->
                    <div class="property-metrics">
                        <?php if ($building['house_type']): ?>
                        <div class="property-metric">
                            <i class="fas fa-city"></i>
                            <div class="property-metric__value">
                                <?= match($building['house_type']) {
                                    'panel' => 'Панель',
                                    'brick' => 'Кирпич',
                                    'monolith' => 'Монолит',
                                    'monolith-brick' => 'Монолит-кирпич',
                                    'block' => 'Блок',
                                    default => $building['house_type']
                                } ?>
                            </div>
                            <div class="property-metric__label">Тип дома</div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($building['floors_max']): ?>
                        <div class="property-metric">
                            <i class="fas fa-layer-group"></i>
                            <div class="property-metric__value">
                                <?= $building['floors_min'] ? $building['floors_min'] . '-' : '' ?><?= $building['floors_max'] ?>
                            </div>
                            <div class="property-metric__label">этажей</div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($building['sections_count']): ?>
                        <div class="property-metric">
                            <i class="fas fa-building"></i>
                            <div class="property-metric__value"><?= $building['sections_count'] ?></div>
                            <div class="property-metric__label">секций</div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($building['apartments_count']): ?>
                        <div class="property-metric">
                            <i class="fas fa-door-open"></i>
                            <div class="property-metric__value"><?= $building['apartments_count'] ?></div>
                            <div class="property-metric__label">квартир</div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($building['ceiling_height']): ?>
                        <div class="property-metric">
                            <i class="fas fa-arrows-alt-v"></i>
                            <div class="property-metric__value"><?= $building['ceiling_height'] ?> м</div>
                            <div class="property-metric__label">потолки</div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Description -->
                    <?php if ($building['description']): ?>
                    <section class="property-section">
                        <h2 class="property-section__title">О жилом комплексе</h2>
                        <div class="property-description">
                            <?= nl2br(escape($building['description'])) ?>
                        </div>
                    </section>
                    <?php endif; ?>

                    <!-- Планировки -->
                    <?php if (!empty($layouts)): ?>
                    <section class="property-section">
                        <h2 class="property-section__title">Планировки и цены</h2>
                        <div class="layouts-grid">
                            <?php foreach ($layouts as $layout): ?>
                            <div class="layout-card">
                                <?php if (!empty($layout['layout_image'])): ?>
                                <div class="layout-card__image">
                                    <img src="<?= escape($layout['layout_image']) ?>" alt="<?= isset($layout['rooms']) ? $layout['rooms'] : '0' ?>-комн">
                                </div>
                                <?php endif; ?>
                                <div class="layout-card__body">
                                    <h3 class="layout-card__title">
                                        <?= (isset($layout['rooms']) && $layout['rooms'] == 0) ? 'Студия' : (isset($layout['rooms']) ? $layout['rooms'] . '-комн. кв.' : 'Планировка') ?>
                                    </h3>
                                    <?php if (!empty($layout['area_total'])): ?>
                                    <div class="layout-card__area">
                                        <?= number_format($layout['area_total'], 1) ?> м²
                                        <?php if (!empty($layout['floor_min']) && !empty($layout['floor_max'])): ?>
                                        <span style="color: var(--color-text-light); font-size: var(--text-xs);">
                                            (<?= $layout['floor_min'] ?>-<?= $layout['floor_max'] ?> этаж)
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($layout['price'])): ?>
                                    <div class="layout-card__price">
                                        от <?= formatPrice($layout['price']) ?>
                                    </div>
                                    <?php else: ?>
                                    <div class="layout-card__price" style="color: var(--color-text-light); font-size: var(--text-sm);">
                                        Цена по запросу
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                    <?php elseif ($building['area_studio_from'] || $building['area_1room_from'] || $building['area_2room_from'] || $building['area_3room_from']): ?>
                    <!-- Альтернативное отображение планировок из полей new_buildings -->
                    <section class="property-section">
                        <h2 class="property-section__title">Планировки и цены</h2>
                        <div class="layouts-grid">
                            <?php if ($building['area_studio_from']): ?>
                            <div class="layout-card">
                                <div class="layout-card__body">
                                    <h3 class="layout-card__title">Студия</h3>
                                    <div class="layout-card__area">
                                        <?= number_format($building['area_studio_from'], 1) ?>
                                        <?php if ($building['area_studio_to'] && $building['area_studio_to'] != $building['area_studio_from']): ?>
                                        - <?= number_format($building['area_studio_to'], 1) ?>
                                        <?php endif; ?>
                                        м²
                                    </div>
                                    <?php if ($building['price_from']): ?>
                                    <div class="layout-card__price">от <?= formatPrice($building['price_from']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($building['area_1room_from']): ?>
                            <div class="layout-card">
                                <div class="layout-card__body">
                                    <h3 class="layout-card__title">1-комн. кв.</h3>
                                    <div class="layout-card__area">
                                        <?= number_format($building['area_1room_from'], 1) ?>
                                        <?php if ($building['area_1room_to'] && $building['area_1room_to'] != $building['area_1room_from']): ?>
                                        - <?= number_format($building['area_1room_to'], 1) ?>
                                        <?php endif; ?>
                                        м²
                                    </div>
                                    <?php if ($building['price_from']): ?>
                                    <div class="layout-card__price">от <?= formatPrice($building['price_from']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($building['area_2room_from']): ?>
                            <div class="layout-card">
                                <div class="layout-card__body">
                                    <h3 class="layout-card__title">2-комн. кв.</h3>
                                    <div class="layout-card__area">
                                        <?= number_format($building['area_2room_from'], 1) ?>
                                        <?php if ($building['area_2room_to'] && $building['area_2room_to'] != $building['area_2room_from']): ?>
                                        - <?= number_format($building['area_2room_to'], 1) ?>
                                        <?php endif; ?>
                                        м²
                                    </div>
                                    <?php if ($building['price_from']): ?>
                                    <div class="layout-card__price">от <?= formatPrice($building['price_from']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($building['area_3room_from']): ?>
                            <div class="layout-card">
                                <div class="layout-card__body">
                                    <h3 class="layout-card__title">3-комн. кв.</h3>
                                    <div class="layout-card__area">
                                        <?= number_format($building['area_3room_from'], 1) ?>
                                        <?php if ($building['area_3room_to'] && $building['area_3room_to'] != $building['area_3room_from']): ?>
                                        - <?= number_format($building['area_3room_to'], 1) ?>
                                        <?php endif; ?>
                                        м²
                                    </div>
                                    <?php if ($building['price_from']): ?>
                                    <div class="layout-card__price">от <?= formatPrice($building['price_from']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($building['area_4room_from']): ?>
                            <div class="layout-card">
                                <div class="layout-card__body">
                                    <h3 class="layout-card__title">4-комн. кв.</h3>
                                    <div class="layout-card__area">
                                        <?= number_format($building['area_4room_from'], 1) ?>
                                        <?php if ($building['area_4room_to'] && $building['area_4room_to'] != $building['area_4room_from']): ?>
                                        - <?= number_format($building['area_4room_to'], 1) ?>
                                        <?php endif; ?>
                                        м²
                                    </div>
                                    <?php if ($building['price_from']): ?>
                                    <div class="layout-card__price">от <?= formatPrice($building['price_from']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </section>
                    <?php endif; ?>

                    <!-- Building Details -->
                    <section class="property-section">
                        <h2 class="property-section__title">Характеристики ЖК</h2>
                        <div class="property-specs">
                            <?php if ($building['house_type']): ?>
                            <div class="property-spec">
                                <span class="property-spec__icon"><i class="fas fa-city"></i></span>
                                <span class="property-spec__label">Тип дома</span>
                                <span class="property-spec__value"><?= match($building['house_type']) {
                                    'panel' => 'Панельный',
                                    'brick' => 'Кирпичный',
                                    'monolith' => 'Монолитный',
                                    'monolith-brick' => 'Монолит-кирпич',
                                    'block' => 'Блочный',
                                    default => $building['house_type']
                                } ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($building['floors_max']): ?>
                            <div class="property-spec">
                                <span class="property-spec__icon"><i class="fas fa-layer-group"></i></span>
                                <span class="property-spec__label">Этажность</span>
                                <span class="property-spec__value">
                                    <?= $building['floors_min'] ? $building['floors_min'] . '-' : '' ?><?= $building['floors_max'] ?> этажей
                                </span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($building['sections_count']): ?>
                            <div class="property-spec">
                                <span class="property-spec__icon"><i class="fas fa-building"></i></span>
                                <span class="property-spec__label">Секций</span>
                                <span class="property-spec__value"><?= $building['sections_count'] ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($building['apartments_count']): ?>
                            <div class="property-spec">
                                <span class="property-spec__icon"><i class="fas fa-door-open"></i></span>
                                <span class="property-spec__label">Квартир</span>
                                <span class="property-spec__value"><?= $building['apartments_count'] ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($building['ceiling_height']): ?>
                            <div class="property-spec">
                                <span class="property-spec__icon"><i class="fas fa-arrows-alt-v"></i></span>
                                <span class="property-spec__label">Высота потолков</span>
                                <span class="property-spec__value"><?= $building['ceiling_height'] ?> м</span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($building['finish_type']): ?>
                            <div class="property-spec">
                                <span class="property-spec__icon"><i class="fas fa-paint-roller"></i></span>
                                <span class="property-spec__label">Отделка</span>
                                <span class="property-spec__value">
                                    <?php
                                    $finishes = explode(',', $building['finish_type']);
                                    $finishNames = array_map(fn($f) => match(trim($f)) {
                                        'rough' => 'Черновая',
                                        'pre-finish' => 'Предчистовая',
                                        'white-box' => 'White box',
                                        'turnkey' => 'Под ключ',
                                        'design' => 'Дизайнерская',
                                        default => $f
                                    }, $finishes);
                                    echo implode(', ', $finishNames);
                                    ?>
                                </span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($building['parking_type']): ?>
                            <div class="property-spec">
                                <span class="property-spec__icon"><i class="fas fa-parking"></i></span>
                                <span class="property-spec__label">Паркинг</span>
                                <span class="property-spec__value">
                                    <?php
                                    $parkingTypes = explode(',', $building['parking_type']);
                                    $parkingNames = array_map(fn($p) => match(trim($p)) {
                                        'underground' => 'Подземный',
                                        'ground' => 'Наземный',
                                        'multilevel' => 'Многоуровневый',
                                        'open' => 'Открытый',
                                        default => $p
                                    }, $parkingTypes);
                                    echo implode(', ', $parkingNames);
                                    ?>
                                    <?php if ($building['parking_price_from']): ?>
                                    (от <?= formatPrice($building['parking_price_from']) ?>)
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($building['construction_stage']): ?>
                            <div class="property-spec">
                                <span class="property-spec__icon"><i class="fas fa-hard-hat"></i></span>
                                <span class="property-spec__label">Стадия строительства</span>
                                <span class="property-spec__value"><?= match($building['construction_stage']) {
                                    'project' => 'Проект',
                                    'foundation' => 'Фундамент',
                                    'construction' => 'Строительство',
                                    'finishing' => 'Отделка',
                                    'completed' => 'Сдан',
                                    default => $building['construction_stage']
                                } ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </section>

                    <!-- About House -->
                    <?php if ($building['about_house']): ?>
                    <section class="property-section">
                        <h2 class="property-section__title">О доме</h2>
                        <div class="property-description">
                            <?= nl2br(escape($building['about_house'])) ?>
                        </div>
                    </section>
                    <?php endif; ?>

                    <!-- About Area -->
                    <?php if ($building['about_area']): ?>
                    <section class="property-section">
                        <h2 class="property-section__title">О районе</h2>
                        <div class="property-description">
                            <?= nl2br(escape($building['about_area'])) ?>
                        </div>
                    </section>
                    <?php endif; ?>

                    <!-- Transport -->
                    <?php if ($building['metro_station'] || $building['transport_info']): ?>
                    <section class="property-section">
                        <h2 class="property-section__title">Транспортная доступность</h2>
                        <?php if ($building['metro_station']): ?>
                        <div class="property-metro">
                            <i class="fas fa-subway"></i>
                            <span class="property-metro__station"><?= escape($building['metro_station']) ?></span>
                            <?php if ($building['metro_minutes']): ?>
                            <span class="property-metro__time">
                                <?= $building['metro_minutes'] ?> мин.
                            </span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($building['transport_info']): ?>
                        <p class="property-transport-info"><?= nl2br(escape($building['transport_info'])) ?></p>
                        <?php endif; ?>
                    </section>
                    <?php endif; ?>

                    <!-- Purchase Conditions -->
                    <?php if ($building['purchase_conditions']): ?>
                    <section class="property-section">
                        <h2 class="property-section__title">Условия покупки</h2>
                        <div class="property-description">
                            <?= nl2br(escape($building['purchase_conditions'])) ?>
                        </div>
                    </section>
                    <?php endif; ?>

                    <!-- Video & 3D Tour -->
                    <?php if ($building['video_url'] || $building['virtual_tour_url']): ?>
                    <section class="property-section">
                        <h2 class="property-section__title">Медиа</h2>
                        <div class="media-links">
                            <?php if ($building['video_url']): ?>
                            <a href="<?= escape($building['video_url']) ?>" target="_blank" class="media-link">
                                <i class="fas fa-video"></i> Видео презентация
                            </a>
                            <?php endif; ?>
                            <?php if ($building['virtual_tour_url']): ?>
                            <a href="<?= escape($building['virtual_tour_url']) ?>" target="_blank" class="media-link">
                                <i class="fas fa-vr-cardboard"></i> 3D-тур
                            </a>
                            <?php endif; ?>
                            <?php if ($building['webcam_url']): ?>
                            <a href="<?= escape($building['webcam_url']) ?>" target="_blank" class="media-link">
                                <i class="fas fa-camera"></i> Веб-камера стройки
                            </a>
                            <?php endif; ?>
                        </div>
                    </section>
                    <?php endif; ?>

                </div>

                <!-- Sidebar -->
                <aside class="property-sidebar">
                    <!-- Contact Card -->
                    <div class="agent-card">
                        <div class="agent-card__header">
                            <i class="fas fa-building" style="font-size: 48px; color: var(--color-accent); margin-bottom: var(--space-4);"></i>
                            <h3 class="agent-card__name">
                                <?= escape($building['developer_name'] ?? 'Застройщик') ?>
                            </h3>
                            <p class="agent-card__role">Застройщик</p>
                        </div>
                        
                        <?php if ($building['agent_id']): ?>
                        <div class="agent-card__actions">
                            <a href="chat.php?user=<?= $building['agent_id'] ?>&property=<?= $building['id'] ?>"
                               class="btn btn--primary btn--full">
                                <i class="fas fa-comments"></i> Чат с агентом
                            </a>
                            <a href="contact.php?building=<?= $building['id'] ?>" 
                               class="btn btn--secondary btn--full">
                                <i class="fas fa-phone"></i> Позвонить
                            </a>
                        </div>
                        <?php else: ?>
                        <div class="agent-card__actions">
                            <a href="contact.php?building=<?= $building['id'] ?>" 
                               class="btn btn--primary btn--full">
                                <i class="fas fa-phone"></i> Связаться
                            </a>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($building['agent_phone'] || $building['agent_email'] || $building['developer_website']): ?>
                        <div class="agent-card__contacts">
                            <?php if ($building['agent_phone']): ?>
                            <div class="agent-card__contact">
                                <i class="fas fa-phone"></i>
                                <a href="tel:<?= escape($building['agent_phone']) ?>">
                                    <?= escape($building['agent_phone']) ?>
                                </a>
                            </div>
                            <?php endif; ?>
                            <?php if ($building['agent_email']): ?>
                            <div class="agent-card__contact">
                                <i class="fas fa-envelope"></i>
                                <a href="mailto:<?= escape($building['agent_email']) ?>">
                                    <?= escape($building['agent_email']) ?>
                                </a>
                            </div>
                            <?php endif; ?>
                            <?php if ($building['developer_website']): ?>
                            <div class="agent-card__contact">
                                <i class="fas fa-globe"></i>
                                <a href="<?= escape($building['developer_website']) ?>" target="_blank">
                                    Сайт застройщика
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </aside>
            </div>

            <!-- Similar Buildings -->
            <?php if (!empty($similarBuildings)): ?>
            <section class="similar-section">
                <h2 class="similar-section__title">Похожие новостройки</h2>
                <div class="properties-grid properties-grid--3">
                    <?php foreach ($similarBuildings as $similar): ?>
                    <article class="property-card">
                        <div class="property-card__image">
                            <a href="new-building.php?id=<?= $similar['id'] ?>">
                                <img src="<?= imgSrc($similar['primary_image']?? 'https://via.placeholder.com/600x400') ?>" 
                                     alt="ЖК <?= escape($similar['name']) ?>" class="property-card__img" loading="lazy">
                            </a>
                            <?php if ($similar['featured']): ?>
                            <span class="property-card__badge">Рекомендуем</span>
                            <?php endif; ?>
                        </div>
                        <div class="property-card__body">
                            <?php if ($similar['price_from']): ?>
                            <div class="property-card__price">
                                от <?= formatPrice($similar['price_from']) ?>
                            </div>
                            <?php endif; ?>
                            <h3 class="property-card__title">
                                <a href="new-building.php?id=<?= $similar['id'] ?>">
                                    ЖК «<?= escape($similar['name']) ?>»
                                </a>
                            </h3>
                            <div class="property-card__address">
                                <i class="fas fa-map-marker-alt"></i>
                                <?= escape($similar['address']) ?>
                            </div>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>
        </div>
    </main>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <script src="js/navigation.js"></script>
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
            margin-bottom: var(--space-3);
        }
        .property-address i { color: var(--color-accent); }
        .property-address__district {
            padding: var(--space-1) var(--space-2);
            background: var(--color-light-gray);
            border-radius: var(--radius-sm);
            font-size: var(--text-sm);
        }
        .developer-badge {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-3) var(--space-4);
            background: var(--color-light-gray);
            border-radius: var(--radius-md);
            font-size: var(--text-sm);
        }
        .developer-badge i { color: var(--color-accent); }
        .property-price-box { text-align: right; }
        .property-price {
            font-size: var(--text-3xl);
            font-weight: var(--font-bold);
            color: var(--color-navy);
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
        
        .layouts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: var(--space-4);
        }
        .layout-card {
            background: white;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: all var(--transition-fast);
        }
        .layout-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }
        .layout-card__image {
            height: 160px;
            overflow: hidden;
        }
        .layout-card__image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .layout-card__body {
            padding: var(--space-4);
        }
        .layout-card__title {
            font-size: var(--text-base);
            font-weight: var(--font-semibold);
            margin-bottom: var(--space-2);
        }
        .layout-card__area {
            font-size: var(--text-sm);
            color: var(--color-text-light);
            margin-bottom: var(--space-2);
        }
        .layout-card__price {
            font-size: var(--text-lg);
            font-weight: var(--font-bold);
            color: var(--color-accent);
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
        
        .media-links {
            display: flex;
            flex-direction: column;
            gap: var(--space-3);
        }
        .media-link {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-4);
            background: var(--color-light-gray);
            border-radius: var(--radius-md);
            color: var(--color-text);
            font-weight: var(--font-medium);
            transition: all var(--transition-fast);
        }
        .media-link:hover {
            background: var(--color-accent);
            color: white;
            transform: translateX(4px);
        }
        .media-link i {
            font-size: var(--text-xl);
            color: var(--color-accent);
        }
        .media-link:hover i { color: white; }
        
        /* Sidebar */
        .property-sidebar { position: sticky; top: calc(var(--header-height) + var(--space-6)); }
        .agent-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: var(--space-6);
            text-align: center;
            box-shadow: var(--shadow-md);
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
        .agent-card__actions { 
            display: flex; 
            flex-direction: column; 
            gap: var(--space-3);
            margin-bottom: var(--space-4);
        }
        .agent-card__contacts {
            padding-top: var(--space-4);
            border-top: 1px solid var(--color-border);
        }
        .agent-card__contact {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-2);
            font-size: var(--text-sm);
        }
        .agent-card__contact i { color: var(--color-accent); }
        .agent-card__contact a { color: var(--color-text); }
        .agent-card__contact a:hover { color: var(--color-accent); }
        
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
            .layouts-grid { grid-template-columns: 1fr; }
            .properties-grid--3 { grid-template-columns: 1fr; }
        }
    </style>
    <?php include __DIR__ . '/includes/cookie-banner.php'; ?>
</body>
</html>

