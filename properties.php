<?php
/**
 * Properties Catalog - Elsesser & Co.
 * Каталог готового жилья (Екатеринбург)
 */

require_once __DIR__ . '/includes/config/database.php';
require_once __DIR__ . '/includes/auth/check_auth.php';

$user = getUserData();
$pdo = getDBConnection();

// Параметры фильтрации
$category = $_GET['category'] ?? 'sale';
if (!in_array($category, ['sale', 'rent'])) $category = 'sale';

$districtId = (int)($_GET['district'] ?? 0);
$rooms = $_GET['rooms'] ?? '';
$minPrice = (int)($_GET['min_price'] ?? 0);
$maxPrice = (int)($_GET['max_price'] ?? 0);
$minArea = (int)($_GET['min_area'] ?? 0);
$maxArea = (int)($_GET['max_area'] ?? 0);
$floorFrom = (int)($_GET['floor_from'] ?? 0);
$floorTo = (int)($_GET['floor_to'] ?? 0);
$houseType = $_GET['house_type'] ?? '';
$renovation = $_GET['renovation'] ?? '';
$metro = $_GET['metro'] ?? '';
$search = trim($_GET['search'] ?? '');
$sortBy = $_GET['sort'] ?? 'newest';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Построение SQL
$where = ["p.status = 'available'", "p.category = ?"];
$params = [$category];

if ($districtId > 0) {
    $where[] = "p.district_id = ?";
    $params[] = $districtId;
}

if ($rooms !== '' && $rooms !== 'any') {
    if ($rooms === '4+') {
        $where[] = "p.bedrooms >= 4";
    } else {
        $where[] = "p.bedrooms = ?";
        $params[] = (int)$rooms;
    }
}

if ($minPrice > 0) {
    $where[] = "p.price >= ?";
    $params[] = $minPrice;
}
if ($maxPrice > 0) {
    $where[] = "p.price <= ?";
    $params[] = $maxPrice;
}

if ($minArea > 0) {
    $where[] = "(p.area_total >= ? OR p.area_sqft >= ?)";
    $params[] = $minArea;
    $params[] = $minArea;
}
if ($maxArea > 0) {
    $where[] = "(p.area_total <= ? OR p.area_sqft <= ?)";
    $params[] = $maxArea;
    $params[] = $maxArea;
}

if ($floorFrom > 0) {
    $where[] = "p.floor_number >= ?";
    $params[] = $floorFrom;
}
if ($floorTo > 0) {
    $where[] = "p.floor_number <= ?";
    $params[] = $floorTo;
}

if (!empty($houseType)) {
    $where[] = "p.house_type = ?";
    $params[] = $houseType;
}

if (!empty($renovation)) {
    $where[] = "p.renovation = ?";
    $params[] = $renovation;
}

if (!empty($metro)) {
    $where[] = "p.metro_station = ?";
    $params[] = $metro;
}

if (!empty($search)) {
    $where[] = "(p.title_ru LIKE ? OR p.street LIKE ? OR p.location LIKE ? OR p.building_name LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$orderBy = match($sortBy) {
    'price_asc' => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'area_asc' => 'COALESCE(p.area_total, p.area_sqft) ASC',
    'area_desc' => 'COALESCE(p.area_total, p.area_sqft) DESC',
    default => 'p.featured DESC, p.created_at DESC'
};

$whereClause = 'WHERE ' . implode(' AND ', $where);

// Подсчёт
$countSql = "SELECT COUNT(*) FROM properties p $whereClause";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalCount = $stmt->fetchColumn();
$totalPages = ceil($totalCount / $perPage);

// Получение объектов
$sql = "
    SELECT p.*, 
           pi.image_url as primary_image,
           d.name as district_name
    FROM properties p
    LEFT JOIN property_images pi ON p.id = pi.property_id AND pi.is_primary = 1
    LEFT JOIN ekb_districts d ON p.district_id = d.id
    $whereClause
    ORDER BY $orderBy
    LIMIT $perPage OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$properties = $stmt->fetchAll();

// Избранное
$userFavorites = [];
if ($user['logged_in']) {
    $stmt = $pdo->prepare("SELECT property_id FROM favorites WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $userFavorites = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Справочники для фильтров
$districts = $pdo->query("SELECT id, name FROM ekb_districts ORDER BY sort_order")->fetchAll();

$metroStations = ['Ботаническая', 'Чкаловская', 'Геологическая', 'Площадь 1905 года', 'Динамо', 'Уральская', 'Машиностроителей', 'Уралмаш', 'Проспект Космонавтов'];

$pageTitle = $category === 'rent' ? 'Аренда квартир' : 'Купить квартиру';
$pageSubtitle = $category === 'rent' ? 'Снять квартиру в Екатеринбурге' : 'Квартиры на продажу в Екатеринбурге';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= $pageTitle ?> в Екатеринбурге. <?= $totalCount ?> объектов. Elsesser & Co.">
    <title><?= $pageTitle ?> в Екатеринбурге | Elsesser & Co.</title>

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
                        <li><a href="properties.php?category=sale" class="nav__link <?= $category === 'sale' ? 'nav__link--active' : '' ?>">Купить</a></li>
                        <li><a href="properties.php?category=rent" class="nav__link <?= $category === 'rent' ? 'nav__link--active' : '' ?>">Аренда</a></li>
                        <li><a href="new-buildings.php" class="nav__link">Новостройки</a></li>
                        <li><a href="about.html" class="nav__link">О нас</a></li>
                    </ul>
                    <?php include __DIR__ . '/includes/nav-compare-link.php'; ?>
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

    <!-- Page Header -->
    <section class="page-header" style="background: linear-gradient(135deg, var(--color-navy), #1e3a5f);">
        <div class="page-header__content">
            <h1 class="page-header__title"><?= $pageTitle ?></h1>
            <p class="page-header__subtitle"><?= $pageSubtitle ?></p>
            
            <!-- Category Tabs -->
            <div class="category-tabs">
                <a href="?category=sale" class="category-tab <?= $category === 'sale' ? 'category-tab--active' : '' ?>">
                    <i class="fas fa-home"></i> Купить
                </a>
                <a href="?category=rent" class="category-tab <?= $category === 'rent' ? 'category-tab--active' : '' ?>">
                    <i class="fas fa-key"></i> Снять
                </a>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <main class="catalog">
        <div class="container">
            <!-- Filters -->
            <div class="filters-panel">
                <form action="properties.php" method="GET" class="filters-form" id="filterForm">
                    <input type="hidden" name="page" value="<?= (int)$page ?>">
                    <input type="hidden" name="category" value="<?= escape($category) ?>">
                    
                    <div class="filters-row filters-row--main">
                        <div class="filter-group filter-group--search">
                            <i class="fas fa-search"></i>
                            <input type="text" name="search" class="filter-input" 
                                   placeholder="Адрес, район, ЖК..." value="<?= escape($search) ?>">
                        </div>
                        
                        <div class="filter-group">
                            <select name="district" class="filter-select">
                                <option value="">Район</option>
                                <?php foreach ($districts as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= $districtId == $d['id'] ? 'selected' : '' ?>>
                                    <?= escape($d['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <select name="rooms" class="filter-select">
                                <option value="">Комнаты</option>
                                <option value="0" <?= $rooms === '0' ? 'selected' : '' ?>>Студия</option>
                                <option value="1" <?= $rooms === '1' ? 'selected' : '' ?>>1 комната</option>
                                <option value="2" <?= $rooms === '2' ? 'selected' : '' ?>>2 комнаты</option>
                                <option value="3" <?= $rooms === '3' ? 'selected' : '' ?>>3 комнаты</option>
                                <option value="4+" <?= $rooms === '4+' ? 'selected' : '' ?>>4+ комнаты</option>
                            </select>
                        </div>
                        
                        <div class="filter-group filter-group--price">
                            <input type="number" name="min_price" class="filter-input" 
                                   placeholder="Цена от" value="<?= $minPrice ?: '' ?>">
                            <span class="filter-sep">—</span>
                            <input type="number" name="max_price" class="filter-input" 
                                   placeholder="до" value="<?= $maxPrice ?: '' ?>">
                        </div>
                        
                        <button type="submit" class="btn btn--primary">
                            <i class="fas fa-search"></i> Найти
                        </button>
                    </div>
                    
                    <div class="filters-row filters-row--advanced" id="advancedFilters">
                        <div class="filter-group filter-group--area">
                            <label class="filter-label">Площадь, м²</label>
                            <div class="filter-range">
                                <input type="number" name="min_area" class="filter-input" 
                                       placeholder="от" value="<?= $minArea ?: '' ?>">
                                <span class="filter-sep">—</span>
                                <input type="number" name="max_area" class="filter-input" 
                                       placeholder="до" value="<?= $maxArea ?: '' ?>">
                            </div>
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-label">Этаж</label>
                            <div class="filter-range">
                                <input type="number" name="floor_from" class="filter-input" 
                                       placeholder="от" value="<?= $floorFrom ?: '' ?>">
                                <span class="filter-sep">—</span>
                                <input type="number" name="floor_to" class="filter-input" 
                                       placeholder="до" value="<?= $floorTo ?: '' ?>">
                            </div>
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-label">Тип дома</label>
                            <select name="house_type" class="filter-select">
                                <option value="">Любой</option>
                                <option value="panel" <?= $houseType === 'panel' ? 'selected' : '' ?>>Панельный</option>
                                <option value="brick" <?= $houseType === 'brick' ? 'selected' : '' ?>>Кирпичный</option>
                                <option value="monolith" <?= $houseType === 'monolith' ? 'selected' : '' ?>>Монолитный</option>
                                <option value="monolith-brick" <?= $houseType === 'monolith-brick' ? 'selected' : '' ?>>Монолит-кирпич</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-label">Ремонт</label>
                            <select name="renovation" class="filter-select">
                                <option value="">Любой</option>
                                <option value="designer" <?= $renovation === 'designer' ? 'selected' : '' ?>>Дизайнерский</option>
                                <option value="euro" <?= $renovation === 'euro' ? 'selected' : '' ?>>Евроремонт</option>
                                <option value="cosmetic" <?= $renovation === 'cosmetic' ? 'selected' : '' ?>>Косметический</option>
                                <option value="needs-repair" <?= $renovation === 'needs-repair' ? 'selected' : '' ?>>Требует ремонта</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-label">Метро</label>
                            <select name="metro" class="filter-select">
                                <option value="">Любое</option>
                                <?php foreach ($metroStations as $st): ?>
                                <option value="<?= $st ?>" <?= $metro === $st ? 'selected' : '' ?>><?= $st ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="filters-actions">
                        <button type="button" class="filters-toggle" onclick="toggleAdvancedFilters()">
                            <i class="fas fa-sliders-h"></i> Расширенный поиск
                        </button>
                        <button type="button" class="btn btn--secondary" id="saveSearchBtn" <?= isLoggedIn() ? '' : 'disabled title="Войдите, чтобы сохранить поиск"' ?>>Сохранить поиск</button>
                        <a href="?category=<?= $category ?>" class="filters-reset">
                            <i class="fas fa-times"></i> Сбросить фильтры
                        </a>
                    </div>
                </form>
            </div>

            <!-- Results Bar -->
            <div class="results-bar">
                <div class="results-bar__count">
                    Найдено: <strong><?= number_format($totalCount) ?></strong> объектов
                </div>
                <div class="results-bar__sort">
                    <label>Сортировка:</label>
                    <select class="filter-select" id="catalogSort" data-current="<?= escape($sortBy) ?>">
                        <option value="newest" <?= $sortBy === 'newest' ? 'selected' : '' ?>>Сначала новые</option>
                        <option value="price_asc" <?= $sortBy === 'price_asc' ? 'selected' : '' ?>>Дешевле</option>
                        <option value="price_desc" <?= $sortBy === 'price_desc' ? 'selected' : '' ?>>Дороже</option>
                        <option value="area_desc" <?= $sortBy === 'area_desc' ? 'selected' : '' ?>>По площади</option>
                    </select>
                </div>
            </div>

            <?php $yandexKey = (string)Config::get('YANDEX_MAPS_API_KEY', ''); ?>
            <?php if ($yandexKey !== ''): ?>
            <div id="yandexMap" class="yandex-map" data-mode="list" style="height:360px;margin-bottom:1.5rem;border-radius:12px;"></div>
            <?php endif; ?>

            <!-- Properties Grid -->
            <div id="catalogResults">
            <?php if (empty($properties)): ?>
            <div class="empty-state">
                <i class="fas fa-home"></i>
                <h3>Объекты не найдены</h3>
                <p>Попробуйте изменить параметры поиска</p>
                <a href="?category=<?= $category ?>" class="btn btn--secondary">Сбросить фильтры</a>
            </div>
            <?php else: ?>
            <div class="properties-grid properties-grid--auto">
                <?php foreach ($properties as $property): 
                    $isFavorite = in_array($property['id'], $userFavorites);
                    $roomsText = match($property['bedrooms']) {
                        0 => 'Студия',
                        1 => '1-комн.',
                        2 => '2-комн.',
                        3 => '3-комн.',
                        default => $property['bedrooms'] . '-комн.'
                    };
                ?>
                <article class="property-card property-card--compact">
                    <div class="property-card__image">
                        <a href="property.php?id=<?= $property['id'] ?>">
                            <img src="<?= escape($property['primary_image'] ?? 'https://via.placeholder.com/600x400') ?>" 
                                 alt="<?= escape($roomsText) ?>" class="property-card__img" loading="lazy">
                        </a>
                        <?php if ($property['featured']): ?>
                        <span class="property-card__badge">Рекомендуем</span>
                        <?php endif; ?>
                        <button class="property-card__favorite favorite-btn <?= $isFavorite ? 'favorite-btn--active' : '' ?>"
                                data-property-id="<?= $property['id'] ?>">
                            <i class="<?= $isFavorite ? 'fas' : 'far' ?> fa-heart"></i>
                        </button>
                        <label class="compare-checkbox" aria-label="Добавить в сравнение">
                            <input type="checkbox" class="compare-checkbox__input" data-property-id="<?= (int)$property['id'] ?>" onchange="toggleCompare(<?= (int)$property['id'] ?>)">
                            <i class="fas fa-balance-scale compare-checkbox__icon"></i>
                        </label>
                        <?php if ($property['is_new_building']): ?>
                        <span class="property-card__tag">Новостройка</span>
                        <?php endif; ?>
                    </div>
                    <div class="property-card__body">
                        <div class="property-card__price">
                            <?= formatPrice($property['price']) ?>
                            <?php if ($category === 'rent'): ?><span class="property-card__period">/мес</span><?php endif; ?>
                        </div>
                        <?php if (!empty($property['title_ru'])): ?>
                        <div class="property-card__name"><?= escape($property['title_ru']) ?></div>
                        <?php endif; ?>
                        <h3 class="property-card__title">
                            <a href="property.php?id=<?= $property['id'] ?>">
                                <?= $roomsText ?>, <?= number_format($property['area_total'] ?? $property['area_sqft'], 1) ?> м²
                                <?php if ($property['floor_number']): ?>, <?= $property['floor_number'] ?>/<?= $property['total_floors'] ?: '?' ?> эт.<?php endif; ?>
                            </a>
                        </h3>
                        <div class="property-card__address">
                            <i class="fas fa-map-marker-alt"></i>
                            <?php if ($property['street']): ?>
                            <?= escape($property['street']) ?><?php if ($property['house_number']): ?>, <?= escape($property['house_number']) ?><?php endif; ?>
                            <?php else: ?>
                            <?= escape($property['location']) ?>
                            <?php endif; ?>
                        </div>
                        <?php if ($property['district_name']): ?>
                        <div class="property-card__district"><?= escape($property['district_name']) ?></div>
                        <?php endif; ?>
                        <div class="property-card__specs">
                            <span class="property-card__spec">
                                <i class="fas fa-door-open"></i>
                                <?= $property['bedrooms'] == 0 ? 'Ст' : $property['bedrooms'] ?>
                            </span>
                            <span class="property-card__spec">
                                <i class="fas fa-ruler-combined"></i>
                                <?= number_format($property['area_total'] ?? $property['area_sqft'], 1) ?> м²
                            </span>
                            <?php if ($property['floor_number']): ?>
                            <span class="property-card__spec">
                                <i class="fas fa-building"></i>
                                <?= $property['floor_number'] ?>/<?= $property['total_floors'] ?: '?' ?>
                            </span>
                            <?php endif; ?>
                            <?php if ($property['metro_station']): ?>
                            <span class="property-card__spec property-card__spec--metro">
                                <i class="fas fa-subway"></i>
                                <?= $property['metro_minutes'] ? $property['metro_minutes'] . ' мин' : escape($property['metro_station']) ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <a href="chat.php?user=<?= $property['agent_id'] ?>&property=<?= $property['id'] ?>" class="property-card__chat-btn">
                            <i class="fas fa-comment"></i> Чат с агентом
                        </a>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <nav class="pagination">
                <?php if ($page > 1): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="pagination__btn">
                    <i class="fas fa-chevron-left"></i> Назад
                </a>
                <?php endif; ?>
                
                <div class="pagination__pages">
                    <?php 
                    $start = max(1, $page - 2);
                    $end = min($totalPages, $page + 2);
                    
                    if ($start > 1) {
                        echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => 1])) . '" class="pagination__page">1</a>';
                        if ($start > 2) echo '<span class="pagination__dots">...</span>';
                    }
                    
                    for ($i = $start; $i <= $end; $i++) {
                        $active = $i === $page ? 'pagination__page--active' : '';
                        echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => $i])) . '" class="pagination__page ' . $active . '">' . $i . '</a>';
                    }
                    
                    if ($end < $totalPages) {
                        if ($end < $totalPages - 1) echo '<span class="pagination__dots">...</span>';
                        echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => $totalPages])) . '" class="pagination__page">' . $totalPages . '</a>';
                    }
                    ?>
                </div>
                
                <?php if ($page < $totalPages): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="pagination__btn">
                    Вперёд <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </nav>
            <?php endif; ?>
            <?php endif; ?>
            </div><!-- catalogResults -->
        </div>
    </main>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <?php include __DIR__ . '/includes/compare-bar.php'; ?>

    <?php if ((string)Config::get('YANDEX_MAPS_API_KEY', '') !== ''): ?>
    <script>window.YANDEX_MAPS_KEY = <?= json_encode(Config::get('YANDEX_MAPS_API_KEY', '')) ?>;</script>
    <script src="js/yandex-maps.js" defer></script>
    <?php endif; ?>
    <script src="js/api.js"></script>
    <script src="js/navigation.js"></script>
    <script src="js/favorites.js"></script>
    <script src="js/properties-ajax.js" defer></script>
    <script src="js/pwa.js" defer></script>
    <script>
        function toggleAdvancedFilters() {
            const filters = document.getElementById('advancedFilters');
            const btn = document.querySelector('.filters-toggle');
            filters.classList.toggle('filters-row--open');
            btn.classList.toggle('active');
        }
        
        // Auto-show if any advanced filter is set
        document.addEventListener('DOMContentLoaded', function() {
            const hasAdvanced = <?= ($minArea || $maxArea || $floorFrom || $floorTo || $houseType || $renovation || $metro) ? 'true' : 'false' ?>;
            if (hasAdvanced) {
                document.getElementById('advancedFilters').classList.add('filters-row--open');
                document.querySelector('.filters-toggle').classList.add('active');
            }

            // Sort select: чистая смена ?sort= без потери остальных параметров
            const sortSel = document.getElementById('catalogSort');
            if (sortSel) {
                sortSel.addEventListener('change', function(e) {
                    const url = new URL(window.location.href);
                    url.searchParams.set('sort', e.target.value);
                    window.location.href = url.toString();
                });
            }
        });
    </script>
    <?php include __DIR__ . '/includes/cookie-banner.php'; ?>
</body>
</html>
