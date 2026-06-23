<?php
/**
 * Property Comparison Page - Elsesser & Co.
 * Страница сравнения объектов недвижимости
 */

require_once __DIR__ . '/includes/config/database.php';
require_once __DIR__ . '/includes/auth/check_auth.php';

$user = getUserData();
$pdo = getDBConnection();

// Получаем ID объектов из параметра ids
$idsParam = $_GET['ids'] ?? '';
$propertyIds = array_filter(array_map('intval', explode(',', $idsParam)));

// Ограничение: максимум 4 объекта
$propertyIds = array_slice($propertyIds, 0, 4);

$properties = [];
$pageTitle = 'Сравнение объектов';

if (!empty($propertyIds)) {
    // Получаем данные объектов
    $placeholders = implode(',', array_fill(0, count($propertyIds), '?'));
    $sql = "
        SELECT p.*, 
               pi.image_url as primary_image,
               u.first_name as agent_name,
               u.phone as agent_phone
        FROM properties p
        LEFT JOIN property_images pi ON p.id = pi.property_id AND pi.is_primary = 1
        LEFT JOIN users u ON p.agent_id = u.id
        WHERE p.id IN ($placeholders)
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($propertyIds);
    $properties = $stmt->fetchAll();
}

// Характеристики для сравнения
$compareFields = [
    'price' => 'Цена',
    'location' => 'Расположение',
    'community' => 'Район',
    'property_type' => 'Тип',
    'category' => 'Листинг',
    'bedrooms' => 'Спален',
    'bathrooms' => 'Ванных',
    'area_sqft' => 'Площадь (м²)',
    'floor_number' => 'Этаж',
    'parking_spaces' => 'Парковка',
    'furnished' => 'Меблировка',
    'building_name' => 'Здание',
    'status' => 'Статус'
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Сравнение объектов недвижимости в Дубае от Elsesser & Co.">
    <title><?= $pageTitle ?> | Elsesser & Co.</title>

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
                        <li><a href="contact.php" class="nav__link">Продать</a></li>
                        <li><a href="about.php" class="nav__link">О нас</a></li>
                    </ul>
                    <?php if ($user['logged_in']): ?>
                    <div class="user-menu">
                        <a href="favorites.php" class="nav__link">
                            <i class="fas fa-heart"></i>
                        </a>
                        <a href="dashboard.php" class="btn btn--secondary">
                            <i class="fas fa-user"></i> <?= escape($user['name']) ?>
                        </a>
                    </div>
                    <?php else: ?>
                    <a href="login.php" class="btn btn--secondary">Войти</a>
                    <?php endif; ?>
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

    <!-- Page Header -->
    <section class="page-header page-header--small">
        <div class="page-header__content">
            <h1 class="page-header__title"><?= $pageTitle ?></h1>
            <p class="page-header__subtitle">Сравните характеристики объектов недвижимости</p>
        </div>
    </section>

    <!-- Main Content -->
    <main class="section">
        <div class="container">
            <?php if (empty($properties)): ?>
            <!-- Empty State -->
            <div class="empty-state">
                <div class="empty-state__icon">
                    <i class="fas fa-balance-scale"></i>
                </div>
                <h3>Список сравнения пуст</h3>
                <p>Добавьте объекты для сравнения, используя чекбокс на карточках недвижимости</p>
                <a href="properties.php" class="btn btn--primary">
                    <i class="fas fa-search"></i> Перейти к каталогу
                </a>
            </div>
            <?php else: ?>
            <!-- Comparison Table -->
            <div class="compare-table-wrapper">
                <table class="compare-table">
                    <thead>
                        <tr>
                            <th class="compare-table__label">Характеристика</th>
                            <?php foreach ($properties as $property): ?>
                            <th class="compare-table__property">
                                <div class="compare-property">
                                    <div class="compare-property__image">
                                        <img src="<?= imgSrc($property['primary_image']?? 'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?w=400&q=80') ?>" 
                                             alt="<?= escape($property['title']) ?>">
                                    </div>
                                    <h3 class="compare-property__title">
                                        <a href="property.php?id=<?= $property['id'] ?>">
                                            <?= escape($property['title_ru'] ?? $property['title']) ?>
                                        </a>
                                    </h3>
                                    <button class="btn btn--secondary btn--sm" onclick="removeFromCompareAndReload(<?= $property['id'] ?>)">
                                        <i class="fas fa-times"></i> Убрать
                                    </button>
                                </div>
                            </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($compareFields as $field => $label): ?>
                        <tr>
                            <td class="compare-table__label"><?= $label ?></td>
                            <?php foreach ($properties as $property): ?>
                            <td class="compare-table__value">
                                <?php
                                $value = $property[$field] ?? '-';
                                
                                // Format specific fields
                                switch ($field) {
                                    case 'price':
                                        echo formatPrice($value);
                                        if ($property['category'] === 'rent') echo ' /мес';
                                        break;
                                    case 'area_sqft':
                                        echo $value ? number_format($value) . ' м²' : '-';
                                        break;
                                    case 'property_type':
                                        echo ucfirst($value);
                                        break;
                                    case 'category':
                                        echo $value === 'rent' ? 'Аренда' : 'Продажа';
                                        break;
                                    case 'furnished':
                                        echo match($value) {
                                            'furnished' => 'Меблированная',
                                            'semi-furnished' => 'Частично',
                                            'unfurnished' => 'Без мебели',
                                            default => '-'
                                        };
                                        break;
                                    case 'status':
                                        echo match($value) {
                                            'available' => 'Доступно',
                                            'sold' => 'Продано',
                                            'rented' => 'Сдано',
                                            'pending' => 'В процессе',
                                            default => $value
                                        };
                                        break;
                                    case 'parking_spaces':
                                    case 'bedrooms':
                                    case 'bathrooms':
                                        echo $value ?: '0';
                                        break;
                                    default:
                                        echo escape($value) ?: '-';
                                }
                                ?>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Actions -->
            <div class="compare-actions">
                <button type="button" class="btn btn--secondary" onclick="window.print()">
                    <i class="fas fa-print"></i> Распечатать
                </button>
                <button type="button" class="btn btn--secondary" onclick="clearCompare(); window.location.reload();">
                    <i class="fas fa-trash"></i> Очистить всё
                </button>
                <a href="properties.php" class="btn btn--primary">
                    <i class="fas fa-plus"></i> Добавить ещё объекты
                </a>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <!-- Scripts -->
    <script src="js/navigation.js"></script>
    <script src="js/compare.js"></script>
    <script>
        function removeFromCompareAndReload(propertyId) {
            removeFromCompare(propertyId);
            // Reload with updated ids
            const list = getCompareList();
            if (list.length > 0) {
                window.location.href = 'compare.php?ids=' + list.join(',');
            } else {
                window.location.href = 'compare.php';
            }
        }
    </script>

    <style>
        .page-header--small {
            min-height: 200px;
            background-image: url('https://images.unsplash.com/photo-1560518883-ce09059eeffa?w=1920&q=80');
        }
        
        .compare-table-wrapper {
            overflow-x: auto;
            margin-bottom: var(--space-8);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-md);
        }
        
        .compare-table {
            width: 100%;
            border-collapse: collapse;
            background-color: var(--color-white);
            min-width: 800px;
        }
        
        .compare-table thead {
            background-color: var(--color-navy);
            color: var(--color-white);
        }
        
        .compare-table th,
        .compare-table td {
            padding: var(--space-4);
            text-align: left;
            border: 1px solid var(--color-border);
        }
        
        .compare-table__label {
            font-weight: var(--font-semibold);
            background-color: var(--color-light-gray);
            width: 200px;
            vertical-align: middle;
        }
        
        .compare-table__property {
            text-align: center;
            vertical-align: top;
        }
        
        .compare-table__value {
            vertical-align: middle;
            color: var(--color-text);
        }
        
        .compare-property {
            display: flex;
            flex-direction: column;
            gap: var(--space-3);
            align-items: center;
        }
        
        .compare-property__image {
            width: 100%;
            height: 150px;
            border-radius: var(--radius-md);
            overflow: hidden;
        }
        
        .compare-property__image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .compare-property__title {
            font-size: var(--text-base);
            font-weight: var(--font-medium);
            min-height: 40px;
        }
        
        .compare-property__title a {
            color: var(--color-white);
            text-decoration: none;
        }
        
        .compare-property__title a:hover {
            text-decoration: underline;
        }
        
        .compare-actions {
            display: flex;
            justify-content: center;
            gap: var(--space-4);
            flex-wrap: wrap;
        }
        
        .empty-state {
            text-align: center;
            padding: var(--space-20) var(--space-6);
        }
        
        .empty-state__icon {
            width: 120px;
            height: 120px;
            margin: 0 auto var(--space-6);
            background-color: var(--color-light-gray);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: var(--color-text-light);
        }
        
        .empty-state h3 {
            font-size: var(--text-2xl);
            margin-bottom: var(--space-3);
        }
        
        .empty-state p {
            color: var(--color-text-light);
            margin-bottom: var(--space-6);
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }
        
        @media print {
            .header, .footer, .compare-actions {
                display: none;
            }
            
            .compare-table {
                font-size: 12px;
            }
        }
        
        @media (max-width: 768px) {
            .compare-table__label {
                width: 150px;
                font-size: var(--text-sm);
            }
            
            .compare-property__image {
                height: 100px;
            }
            
            .compare-actions {
                flex-direction: column;
            }
            
            .compare-actions .btn {
                width: 100%;
            }
        }
    </style>
    <?php include __DIR__ . '/includes/cookie-banner.php'; ?>
</body>
</html>



