<?php
/**
 * Admin Properties Management - Elsesser & Co.
 * Управление готовым жильём (продажа/аренда)
 */

require_once __DIR__ . '/../includes/config/database.php';
require_once __DIR__ . '/../includes/auth/check_auth.php';

// Только для администраторов
requireAdmin();

$pdo = getDBConnection();
$message = '';
$messageType = '';

// Категория: sale или rent
$category = $_GET['category'] ?? 'sale';
if (!in_array($category, ['sale', 'rent'])) {
    $category = 'sale';
}

// Обработка действий
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $propertyId = (int)($_POST['property_id'] ?? 0);
    
    try {
        switch ($action) {
            case 'toggle_featured':
                $stmt = $pdo->prepare("UPDATE properties SET featured = NOT featured WHERE id = ?");
                $stmt->execute([$propertyId]);
                $message = 'Статус "Рекомендуемое" изменён';
                $messageType = 'success';
                break;
                
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM properties WHERE id = ?");
                $stmt->execute([$propertyId]);
                $message = 'Объект удалён';
                $messageType = 'success';
                break;
                
            case 'change_status':
                $status = $_POST['status'] ?? 'available';
                $stmt = $pdo->prepare("UPDATE properties SET status = ? WHERE id = ?");
                $stmt->execute([$status, $propertyId]);
                $message = 'Статус изменён';
                $messageType = 'success';
                break;
        }
    } catch (PDOException $e) {
        $message = 'Ошибка: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Фильтры
$status = $_GET['status'] ?? '';
$type = $_GET['type'] ?? '';
$district = $_GET['district'] ?? '';
$rooms = $_GET['rooms'] ?? '';
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Построение SQL
$where = ["p.category = ?"];
$params = [$category];

if (!empty($status)) {
    $where[] = "p.status = ?";
    $params[] = $status;
}

if (!empty($type)) {
    $where[] = "p.property_type = ?";
    $params[] = $type;
}

if (!empty($district)) {
    $where[] = "p.district_id = ?";
    $params[] = $district;
}

if ($rooms !== '') {
    if ($rooms === '4+') {
        $where[] = "p.bedrooms >= 4";
    } else {
        $where[] = "p.bedrooms = ?";
        $params[] = (int)$rooms;
    }
}

if (!empty($search)) {
    $where[] = "(p.title_ru LIKE ? OR p.street LIKE ? OR p.location LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

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
           u.first_name as agent_name,
           d.name as district_name
    FROM properties p
    LEFT JOIN property_images pi ON p.id = pi.property_id AND pi.is_primary = 1
    LEFT JOIN users u ON p.agent_id = u.id
    LEFT JOIN ekb_districts d ON p.district_id = d.id
    $whereClause
    ORDER BY p.created_at DESC
    LIMIT $perPage OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$properties = $stmt->fetchAll();

// Получаем районы для фильтра
$districts = $pdo->query("SELECT id, name FROM ekb_districts ORDER BY sort_order")->fetchAll();

$pageTitle = $category === 'rent' ? 'Аренда' : 'Продажа';
$pageTitleFull = 'Готовое жильё — ' . $pageTitle;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitleFull ?> | Admin CRM</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../css/reset.css">
    <link rel="stylesheet" href="../css/variables.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body class="admin-body">
    <?php include __DIR__ . '/includes/admin-header.php'; ?>
    
    <div class="admin-container">
        <?php include __DIR__ . '/includes/admin-sidebar.php'; ?>
        
        <main class="admin-main">
            <div class="admin-header">
                <div>
                    <h1 class="admin-title"><?= $pageTitleFull ?></h1>
                    <div class="admin-tabs">
                        <a href="?category=sale" class="admin-tab <?= $category === 'sale' ? 'admin-tab--active' : '' ?>">
                            <i class="fas fa-home"></i> Продажа
                        </a>
                        <a href="?category=rent" class="admin-tab <?= $category === 'rent' ? 'admin-tab--active' : '' ?>">
                            <i class="fas fa-key"></i> Аренда
                        </a>
                    </div>
                </div>
                <a href="property-edit.php?category=<?= $category ?>" class="btn btn--primary">
                    <i class="fas fa-plus"></i> Добавить объект
                </a>
            </div>
            
            <?php if ($message): ?>
            <div class="alert alert--<?= $messageType ?>">
                <?= escape($message) ?>
            </div>
            <?php endif; ?>
            
            <!-- Filters -->
            <div class="admin-card">
                <div class="admin-card__body">
                    <form method="GET" class="admin-filters">
                        <input type="hidden" name="category" value="<?= escape($category) ?>">
                        
                        <div class="admin-filters__search">
                            <i class="fas fa-search"></i>
                            <input type="text" name="search" placeholder="Поиск по адресу..." value="<?= escape($search) ?>">
                        </div>
                        
                        <select name="district" class="admin-select">
                            <option value="">Все районы</option>
                            <?php foreach ($districts as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= $district == $d['id'] ? 'selected' : '' ?>>
                                <?= escape($d['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <select name="rooms" class="admin-select">
                            <option value="">Комнаты</option>
                            <option value="0" <?= $rooms === '0' ? 'selected' : '' ?>>Студия</option>
                            <option value="1" <?= $rooms === '1' ? 'selected' : '' ?>>1 комната</option>
                            <option value="2" <?= $rooms === '2' ? 'selected' : '' ?>>2 комнаты</option>
                            <option value="3" <?= $rooms === '3' ? 'selected' : '' ?>>3 комнаты</option>
                            <option value="4+" <?= $rooms === '4+' ? 'selected' : '' ?>>4+ комнаты</option>
                        </select>
                        
                        <select name="status" class="admin-select">
                            <option value="">Все статусы</option>
                            <option value="available" <?= $status === 'available' ? 'selected' : '' ?>>Доступно</option>
                            <option value="sold" <?= $status === 'sold' ? 'selected' : '' ?>>Продано</option>
                            <option value="rented" <?= $status === 'rented' ? 'selected' : '' ?>>Сдано</option>
                            <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>В процессе</option>
                            <option value="off-market" <?= $status === 'off-market' ? 'selected' : '' ?>>Снято</option>
                        </select>
                        
                        <select name="type" class="admin-select">
                            <option value="">Все типы</option>
                            <option value="apartment" <?= $type === 'apartment' ? 'selected' : '' ?>>Квартира</option>
                            <option value="studio" <?= $type === 'studio' ? 'selected' : '' ?>>Студия</option>
                            <option value="room" <?= $type === 'room' ? 'selected' : '' ?>>Комната</option>
                            <option value="house" <?= $type === 'house' ? 'selected' : '' ?>>Дом</option>
                            <option value="townhouse" <?= $type === 'townhouse' ? 'selected' : '' ?>>Таунхаус</option>
                        </select>
                        
                        <button type="submit" class="btn btn--primary">
                            <i class="fas fa-filter"></i> Применить
                        </button>
                        <a href="?category=<?= escape($category) ?>" class="btn btn--secondary">
                            <i class="fas fa-times"></i> Сбросить
                        </a>
                    </form>
                </div>
            </div>
            
            <!-- Results info -->
            <div class="admin-results-info">
                Найдено объектов: <strong><?= number_format($totalCount) ?></strong>
            </div>
            
            <!-- Properties Table -->
            <div class="admin-card">
                <div class="admin-table-wrapper">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Фото</th>
                                <th>Адрес</th>
                                <th>Комн.</th>
                                <th>Площадь</th>
                                <th>Этаж</th>
                                <th>Цена</th>
                                <th>Район</th>
                                <th>Статус</th>
                                <th>⭐</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($properties)): ?>
                            <tr>
                                <td colspan="11" class="text-center text-muted">Объекты не найдены</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($properties as $property): ?>
                            <tr>
                                <td><?= $property['id'] ?></td>
                                <td>
                                    <img src="<?= escape($property['primary_image'] ?? 'https://via.placeholder.com/100x70') ?>" 
                                         alt=""
                                         class="admin-table__image">
                                </td>
                                <td>
                                    <div class="admin-table__property-title">
                                        <a href="../property.php?id=<?= $property['id'] ?>" target="_blank">
                                            <?= escape($property['street'] ? $property['street'] . ', ' . $property['house_number'] : $property['title_ru'] ?? $property['title']) ?>
                                        </a>
                                    </div>
                                    <div class="admin-table__property-meta">
                                        <?= escape($property['location']) ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge--info">
                                        <?= $property['bedrooms'] == 0 ? 'Ст' : $property['bedrooms'] ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?= number_format($property['area_total'] ?? $property['area_sqft'], 1) ?></strong> м²
                                    <?php if ($property['area_kitchen']): ?>
                                    <br><small class="text-muted">кухня <?= $property['area_kitchen'] ?> м²</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($property['floor_number']): ?>
                                    <?= $property['floor_number'] ?>/<?= $property['total_floors'] ?: '?' ?>
                                    <?php else: ?>
                                    <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= formatPrice($property['price']) ?></strong>
                                    <?php if ($category === 'rent'): ?>
                                    <span class="text-muted">/мес</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small><?= escape($property['district_name'] ?? '—') ?></small>
                                </td>
                                <td>
                                    <span class="badge badge--<?= $property['status'] === 'available' ? 'success' : 'secondary' ?>">
                                        <?= match($property['status']) {
                                            'available' => 'Актив',
                                            'sold' => 'Продано',
                                            'rented' => 'Сдано',
                                            'pending' => 'В процессе',
                                            default => $property['status']
                                        } ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle_featured">
                                        <input type="hidden" name="property_id" value="<?= $property['id'] ?>">
                                        <button type="submit" class="btn-icon <?= $property['featured'] ? 'active' : '' ?>" title="В рекомендуемые">
                                            <i class="fas fa-star"></i>
                                        </button>
                                    </form>
                                </td>
                                <td>
                                    <div class="admin-table__actions">
                                        <a href="property-edit.php?id=<?= $property['id'] ?>" class="btn btn--sm btn--secondary" title="Редактировать">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Удалить объект?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="property_id" value="<?= $property['id'] ?>">
                                            <button type="submit" class="btn btn--sm btn--danger" title="Удалить">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="admin-pagination">
                <?php if ($page > 1): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="admin-pagination__btn">
                    <i class="fas fa-chevron-left"></i> Назад
                </a>
                <?php endif; ?>
                
                <div class="admin-pagination__pages">
                    Страница <?= $page ?> из <?= $totalPages ?>
                </div>
                
                <?php if ($page < $totalPages): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="admin-pagination__btn">
                    Вперёд <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </main>
    </div>
    
    <style>
        .admin-tabs {
            display: flex;
            gap: var(--space-2);
            margin-top: var(--space-3);
        }
        .admin-tab {
            padding: var(--space-2) var(--space-4);
            border-radius: var(--radius-md);
            font-size: var(--text-sm);
            font-weight: var(--font-medium);
            color: var(--color-text-light);
            background-color: var(--color-light-gray);
            transition: all var(--transition-fast);
        }
        .admin-tab:hover {
            background-color: var(--color-border);
        }
        .admin-tab--active {
            background-color: var(--color-accent);
            color: white;
        }
        .admin-tab i {
            margin-right: var(--space-2);
        }
    </style>
</body>
</html>
