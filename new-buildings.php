<?php
/**
 * New Buildings Catalog - Elsesser & Co.
 * Каталог новостроек (ЖК) Екатеринбурга
 */

require_once __DIR__ . '/includes/config/database.php';
require_once __DIR__ . '/includes/auth/check_auth.php';

$user = getUserData();
$pdo = getDBConnection();

// Фильтры
$districtId = (int)($_GET['district'] ?? 0);
$developerId = (int)($_GET['developer'] ?? 0);
$rooms = $_GET['rooms'] ?? '';
$completionYear = (int)($_GET['year'] ?? 0);
$finishType = $_GET['finish'] ?? '';
$minPrice = (int)($_GET['min_price'] ?? 0);
$maxPrice = (int)($_GET['max_price'] ?? 0);
$search = trim($_GET['search'] ?? '');
$sortBy = $_GET['sort'] ?? 'featured';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

// Построение SQL
$where = ["nb.status = 'active'"];
$params = [];

if ($districtId > 0) {
    $where[] = "nb.district_id = ?";
    $params[] = $districtId;
}

if ($developerId > 0) {
    $where[] = "nb.developer_id = ?";
    $params[] = $developerId;
}

if ($completionYear > 0) {
    $where[] = "nb.completion_year = ?";
    $params[] = $completionYear;
}

if (!empty($finishType)) {
    $where[] = "FIND_IN_SET(?, nb.finish_type)";
    $params[] = $finishType;
}

if ($minPrice > 0) {
    $where[] = "nb.price_from >= ?";
    $params[] = $minPrice;
}
if ($maxPrice > 0) {
    $where[] = "nb.price_from <= ?";
    $params[] = $maxPrice;
}

if (!empty($search)) {
    $where[] = "(nb.name LIKE ? OR nb.address LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$orderBy = match($sortBy) {
    'price_asc' => 'nb.price_from ASC',
    'price_desc' => 'nb.price_from DESC',
    'completion' => 'nb.completion_year ASC, nb.completion_quarter ASC',
    default => 'nb.featured DESC, nb.created_at DESC'
};

$whereClause = 'WHERE ' . implode(' AND ', $where);

// Подсчёт
$countSql = "SELECT COUNT(*) FROM new_buildings nb $whereClause";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalCount = $stmt->fetchColumn();
$totalPages = ceil($totalCount / $perPage);

// Получение ЖК
$sql = "
    SELECT nb.*, 
           nbi.image_url as primary_image,
           d.name as district_name,
           dev.name as developer_name
    FROM new_buildings nb
    LEFT JOIN new_building_images nbi ON nb.id = nbi.new_building_id AND nbi.is_primary = 1
    LEFT JOIN ekb_districts d ON nb.district_id = d.id
    LEFT JOIN developers dev ON nb.developer_id = dev.id
    $whereClause
    ORDER BY $orderBy
    LIMIT $perPage OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$buildings = $stmt->fetchAll();

// Справочники
$districts = $pdo->query("SELECT id, name FROM ekb_districts ORDER BY sort_order")->fetchAll();
$developers = $pdo->query("SELECT id, name FROM developers WHERE is_active = 1 ORDER BY name")->fetchAll();

$pageTitle = 'Новостройки Екатеринбурга';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Новостройки Екатеринбурга. <?= $totalCount ?> жилых комплексов от застройщиков. Elsesser & Co.">
    <title><?= $pageTitle ?> | Elsesser & Co.</title>

    <link rel="icon" type="image/png" href="images/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
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

    <!-- Page Header -->
    <section class="page-header" style="background: linear-gradient(135deg, var(--color-navy), #1e3a5f);">
        <div class="page-header__content">
            <h1 class="page-header__title"><?= $pageTitle ?></h1>
            <p class="page-header__subtitle">Жилые комплексы от проверенных застройщиков</p>
        </div>
    </section>

    <!-- Main Content -->
    <main class="catalog">
        <div class="container">
            <!-- Filters -->
            <div class="filters-panel">
                <form action="new-buildings.php" method="GET" class="filters-form" id="filterForm">
                    <div class="filters-row filters-row--main">
                        <div class="filter-group filter-group--search">
                            <i class="fas fa-search"></i>
                            <input type="text" name="search" class="filter-input" 
                                   placeholder="Название ЖК или адрес..." value="<?= escape($search) ?>">
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
                            <select name="developer" class="filter-select">
                                <option value="">Застройщик</option>
                                <?php foreach ($developers as $dev): ?>
                                <option value="<?= $dev['id'] ?>" <?= $developerId == $dev['id'] ? 'selected' : '' ?>>
                                    <?= escape($dev['name']) ?>
                                </option>
                                <?php endforeach; ?>
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
                        <div class="filter-group">
                            <label class="filter-label">Срок сдачи</label>
                            <select name="year" class="filter-select">
                                <option value="">Любой</option>
                                <option value="2024" <?= $completionYear == 2024 ? 'selected' : '' ?>>Сдан</option>
                                <option value="2025" <?= $completionYear == 2025 ? 'selected' : '' ?>>2025</option>
                                <option value="2026" <?= $completionYear == 2026 ? 'selected' : '' ?>>2026</option>
                                <option value="2027" <?= $completionYear == 2027 ? 'selected' : '' ?>>2027+</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-label">Отделка</label>
                            <select name="finish" class="filter-select">
                                <option value="">Любая</option>
                                <option value="rough" <?= $finishType === 'rough' ? 'selected' : '' ?>>Черновая</option>
                                <option value="pre-finish" <?= $finishType === 'pre-finish' ? 'selected' : '' ?>>Предчистовая</option>
                                <option value="turnkey" <?= $finishType === 'turnkey' ? 'selected' : '' ?>>Под ключ</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="filters-actions">
                        <button type="button" class="filters-toggle" onclick="toggleAdvancedFilters()">
                            <i class="fas fa-sliders-h"></i> Расширенный поиск
                        </button>
                        <a href="new-buildings.php" class="filters-reset">
                            <i class="fas fa-times"></i> Сбросить фильтры
                        </a>
                    </div>
                </form>
            </div>

            <!-- Results Bar -->
            <div class="results-bar">
                <div class="results-bar__count">
                    Найдено: <strong><?= $totalCount ?></strong> жилых комплексов
                </div>
                <div class="results-bar__sort">
                    <label>Сортировка:</label>
                    <select class="filter-select" onchange="location.href='?<?= http_build_query(array_merge($_GET, ['sort' => ''])) ?>&sort=' + this.value">
                        <option value="featured" <?= $sortBy === 'featured' ? 'selected' : '' ?>>Рекомендуемые</option>
                        <option value="price_asc" <?= $sortBy === 'price_asc' ? 'selected' : '' ?>>Дешевле</option>
                        <option value="price_desc" <?= $sortBy === 'price_desc' ? 'selected' : '' ?>>Дороже</option>
                        <option value="completion" <?= $sortBy === 'completion' ? 'selected' : '' ?>>По сроку сдачи</option>
                    </select>
                </div>
            </div>

            <!-- Buildings Grid -->
            <?php if (empty($buildings)): ?>
            <div class="empty-state">
                <i class="fas fa-city"></i>
                <h3>ЖК не найдены</h3>
                <p>Попробуйте изменить параметры поиска</p>
                <a href="new-buildings.php" class="btn btn--secondary">Сбросить фильтры</a>
            </div>
            <?php else: ?>
            <div class="properties-grid">
                <?php foreach ($buildings as $b): ?>
                <article class="property-card">
                    <div class="property-card__image">
                        <a href="new-building.php?id=<?= $b['id'] ?>">
                            <img src="<?= escape($b['primary_image'] ?? 'https://via.placeholder.com/600x400') ?>" 
                                 alt="<?= escape($b['name']) ?>" class="property-card__img" loading="lazy">
                        </a>
                        <?php if ($b['featured']): ?>
                        <span class="property-card__badge">Рекомендуем</span>
                        <?php endif; ?>
                        <?php if ($b['is_completed']): ?>
                        <span class="property-card__tag property-card__tag--completed">Сдан</span>
                        <?php elseif ($b['completion_quarter'] && $b['completion_year']): ?>
                        <span class="property-card__tag">Q<?= $b['completion_quarter'] ?> <?= $b['completion_year'] ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="property-card__body">
                        <div class="property-card__price">
                            <?php if ($b['price_from']): ?>
                            от <?= formatPrice($b['price_from']) ?>
                            <?php if ($b['price_per_sqm_from']): ?>
                            <span class="property-card__period"><?= formatPrice($b['price_per_sqm_from']) ?>/м²</span>
                            <?php endif; ?>
                            <?php else: ?>
                            <span>Цена по запросу</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($b['developer_name']): ?>
                        <div class="property-card__name">
                            <i class="fas fa-hard-hat"></i> <?= escape($b['developer_name']) ?>
                        </div>
                        <?php endif; ?>
                        <h3 class="property-card__title">
                            <a href="new-building.php?id=<?= $b['id'] ?>">ЖК «<?= escape($b['name']) ?>»</a>
                        </h3>
                        <div class="property-card__address">
                            <i class="fas fa-map-marker-alt"></i>
                            <?= escape($b['address']) ?>
                        </div>
                        <?php if ($b['district_name']): ?>
                        <div class="property-card__district"><?= escape($b['district_name']) ?></div>
                        <?php endif; ?>
                        <div class="property-card__specs">
                            <?php if ($b['house_type']): ?>
                            <span class="property-card__spec">
                                <i class="fas fa-city"></i>
                                <?= match($b['house_type']) {
                                    'panel' => 'Панель',
                                    'brick' => 'Кирпич',
                                    'monolith' => 'Монолит',
                                    'monolith-brick' => 'Монолит-кирпич',
                                    default => $b['house_type']
                                } ?>
                            </span>
                            <?php endif; ?>
                            <?php if ($b['floors_max']): ?>
                            <span class="property-card__spec">
                                <i class="fas fa-layer-group"></i>
                                <?= $b['floors_min'] ? $b['floors_min'] . '-' : '' ?><?= $b['floors_max'] ?> эт.
                            </span>
                            <?php endif; ?>
                            <?php if ($b['finish_type']): ?>
                            <span class="property-card__spec">
                                <i class="fas fa-paint-roller"></i>
                                <?php
                                $finishes = explode(',', $b['finish_type']);
                                $finishNames = array_map(fn($f) => match(trim($f)) {
                                    'rough' => 'Черн.',
                                    'pre-finish' => 'Предчист.',
                                    'turnkey' => 'Под ключ',
                                    default => $f
                                }, $finishes);
                                echo implode(', ', array_slice($finishNames, 0, 2));
                                ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <a href="contact.html?building=<?= $b['id'] ?>" class="property-card__chat-btn">
                            <i class="fas fa-phone"></i> Связаться
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
        </div>
    </main>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <script src="js/navigation.js"></script>
    <script>
        function toggleAdvancedFilters() {
            const filters = document.getElementById('advancedFilters');
            const btn = document.querySelector('.filters-toggle');
            filters.classList.toggle('filters-row--open');
            btn.classList.toggle('active');
        }
        
        // Auto-show if any advanced filter is set
        document.addEventListener('DOMContentLoaded', function() {
            const hasAdvanced = <?= ($completionYear || $finishType) ? 'true' : 'false' ?>;
            if (hasAdvanced) {
                document.getElementById('advancedFilters').classList.add('filters-row--open');
                document.querySelector('.filters-toggle').classList.add('active');
            }
        });
    </script>

    <style>
        .page-header {
            padding: calc(var(--header-height) + var(--space-12)) 0 var(--space-12);
            text-align: center;
            color: white;
        }
        .page-header__title {
            font-size: var(--text-4xl);
            font-weight: var(--font-bold);
            margin-bottom: var(--space-2);
        }
        .page-header__subtitle {
            font-size: var(--text-lg);
            opacity: 0.8;
        }
        
        .catalog { padding: var(--space-10) 0; }
        
        .filters-panel {
            background: white;
            border-radius: var(--radius-lg);
            padding: var(--space-6);
            box-shadow: var(--shadow-md);
            margin-bottom: var(--space-6);
        }
        .filters-row {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-4);
            align-items: flex-end;
        }
        .filters-row--advanced {
            display: none;
            margin-top: var(--space-4);
            padding-top: var(--space-4);
            border-top: 1px solid var(--color-border);
        }
        .filters-row--open { display: flex; }
        
        .filter-group { display: flex; flex-direction: column; gap: var(--space-1); }
        .filter-group--search {
            flex: 2;
            min-width: 200px;
            position: relative;
        }
        .filter-group--search i {
            position: absolute;
            left: var(--space-4);
            bottom: 12px;
            color: var(--color-text-light);
        }
        .filter-group--search .filter-input {
            padding-left: var(--space-10);
        }
        .filter-group--price {
            flex-direction: row;
            align-items: center;
        }
        .filter-label {
            font-size: var(--text-sm);
            color: var(--color-text-light);
        }
        .filter-input,
        .filter-select {
            padding: var(--space-3) var(--space-4);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            font-size: var(--text-sm);
            min-width: 120px;
        }
        .filter-input:focus,
        .filter-select:focus {
            outline: none;
            border-color: var(--color-accent);
        }
        .filter-sep { color: var(--color-text-light); padding: 0 var(--space-1); }
        
        .filters-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: var(--space-4);
        }
        .filters-toggle {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            background: none;
            border: 1px solid var(--color-border);
            padding: var(--space-2) var(--space-4);
            border-radius: var(--radius-md);
            font-size: var(--text-sm);
            color: var(--color-text-light);
            cursor: pointer;
        }
        .filters-toggle:hover,
        .filters-toggle.active {
            border-color: var(--color-accent);
            color: var(--color-accent);
        }
        .filters-reset {
            font-size: var(--text-sm);
            color: var(--color-text-light);
        }
        .filters-reset:hover { color: var(--color-accent); }
        
        .results-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-6);
            flex-wrap: wrap;
            gap: var(--space-4);
        }
        .results-bar__count { font-size: var(--text-lg); }
        .results-bar__sort {
            display: flex;
            align-items: center;
            gap: var(--space-3);
        }
        .results-bar__sort label {
            font-size: var(--text-sm);
            color: var(--color-text-light);
        }
        
        .properties-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: var(--space-6);
        }
        
        .property-card {
            background: white;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: all var(--transition-fast);
        }
        .property-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-4px);
        }
        .property-card__image {
            position: relative;
            height: 200px;
            overflow: hidden;
        }
        .property-card__img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform var(--transition-base);
        }
        .property-card:hover .property-card__img {
            transform: scale(1.05);
        }
        .property-card__badge {
            position: absolute;
            top: var(--space-3);
            left: var(--space-3);
            background: var(--color-accent);
            color: white;
            padding: var(--space-1) var(--space-3);
            border-radius: var(--radius-sm);
            font-size: var(--text-xs);
            font-weight: var(--font-semibold);
        }
        .property-card__tag {
            position: absolute;
            bottom: var(--space-3);
            left: var(--space-3);
            background: rgba(0,0,0,0.7);
            color: white;
            padding: var(--space-1) var(--space-2);
            border-radius: var(--radius-sm);
            font-size: var(--text-xs);
        }
        .property-card__tag--completed {
            background: #10b981;
        }
        
        .property-card__body { padding: var(--space-5); }
        .property-card__price {
            font-size: var(--text-xl);
            font-weight: var(--font-bold);
            color: var(--color-navy);
            margin-bottom: var(--space-2);
        }
        .property-card__period {
            display: block;
            font-size: var(--text-sm);
            font-weight: var(--font-normal);
            color: var(--color-text-light);
        }
        .property-card__name {
            font-size: var(--text-sm);
            color: var(--color-accent);
            font-weight: var(--font-medium);
            margin-bottom: var(--space-1);
        }
        .property-card__name i { margin-right: var(--space-1); }
        .property-card__title {
            font-size: var(--text-base);
            font-weight: var(--font-semibold);
            margin-bottom: var(--space-2);
        }
        .property-card__title a { color: var(--color-text); }
        .property-card__title a:hover { color: var(--color-accent); }
        .property-card__address {
            font-size: var(--text-sm);
            color: var(--color-text-light);
            margin-bottom: var(--space-1);
        }
        .property-card__address i {
            color: var(--color-accent);
            margin-right: var(--space-1);
        }
        .property-card__district {
            display: inline-block;
            font-size: var(--text-xs);
            color: var(--color-accent);
            background: rgba(212, 175, 55, 0.1);
            padding: var(--space-1) var(--space-2);
            border-radius: var(--radius-sm);
            margin-bottom: var(--space-3);
        }
        .property-card__specs {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-3);
            padding-top: var(--space-3);
            border-top: 1px solid var(--color-border);
        }
        .property-card__spec {
            display: flex;
            align-items: center;
            gap: var(--space-1);
            font-size: var(--text-sm);
            color: var(--color-text-light);
        }
        .property-card__spec i { color: var(--color-accent); }
        .property-card__chat-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-2);
            margin-top: var(--space-4);
            padding: var(--space-3);
            background: var(--color-accent);
            color: white;
            border-radius: var(--radius-md);
            font-weight: var(--font-medium);
            font-size: var(--text-sm);
            transition: all var(--transition-fast);
        }
        .property-card__chat-btn:hover {
            background: var(--color-navy);
        }
        
        .empty-state {
            text-align: center;
            padding: var(--space-16);
        }
        .empty-state i {
            font-size: 64px;
            color: var(--color-border);
            margin-bottom: var(--space-4);
        }
        .empty-state h3 { margin-bottom: var(--space-2); }
        .empty-state p {
            color: var(--color-text-light);
            margin-bottom: var(--space-6);
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: var(--space-4);
            margin-top: var(--space-12);
        }
        .pagination__btn {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-3) var(--space-5);
            background: white;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            font-size: var(--text-sm);
        }
        .pagination__btn:hover {
            border-color: var(--color-accent);
            color: var(--color-accent);
        }
        .pagination__pages {
            display: flex;
            gap: var(--space-1);
        }
        .pagination__page {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-md);
            font-size: var(--text-sm);
        }
        .pagination__page:hover { background: var(--color-light-gray); }
        .pagination__page--active {
            background: var(--color-accent);
            color: white;
        }
        .pagination__dots { padding: 0 var(--space-2); color: var(--color-text-light); }
        
        .sidebar__nav-link--logout {
            color: #dc2626;
        }
        
        @media (max-width: 768px) {
            .filters-row--main { flex-direction: column; }
            .filter-group { width: 100%; }
            .filter-group--price { flex-direction: column; align-items: stretch; }
            .properties-grid { grid-template-columns: 1fr; }
        }
    </style>
</body>
</html>

