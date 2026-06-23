<?php
/**
 * Admin New Buildings Management - Elsesser & Co.
 * Управление новостройками (ЖК)
 */

require_once __DIR__ . '/../includes/config/database.php';
require_once __DIR__ . '/../includes/auth/check_auth.php';

requireAdmin();

$pdo = getDBConnection();
$message = '';
$messageType = '';

// Обработка действий
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $buildingId = (int)($_POST['building_id'] ?? 0);
    
    try {
        switch ($action) {
            case 'toggle_featured':
                $stmt = $pdo->prepare("UPDATE new_buildings SET featured = NOT featured WHERE id = ?");
                $stmt->execute([$buildingId]);
                $message = 'Статус "Рекомендуемое" изменён';
                $messageType = 'success';
                break;
                
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM new_buildings WHERE id = ?");
                $stmt->execute([$buildingId]);
                $message = 'ЖК удалён';
                $messageType = 'success';
                break;
                
            case 'change_status':
                $status = $_POST['status'] ?? 'active';
                $stmt = $pdo->prepare("UPDATE new_buildings SET status = ? WHERE id = ?");
                $stmt->execute([$status, $buildingId]);
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
$district = $_GET['district'] ?? '';
$developer = $_GET['developer'] ?? '';
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Построение SQL
$where = [];
$params = [];

if (!empty($status)) {
    $where[] = "nb.status = ?";
    $params[] = $status;
}

if (!empty($district)) {
    $where[] = "nb.district_id = ?";
    $params[] = $district;
}

if (!empty($developer)) {
    $where[] = "nb.developer_id = ?";
    $params[] = $developer;
}

if (!empty($search)) {
    $where[] = "(nb.name LIKE ? OR nb.address LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

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
           dev.name as developer_name,
           (SELECT COUNT(*) FROM new_building_layouts nbl WHERE nbl.new_building_id = nb.id AND nbl.is_available = 1) as layouts_count
    FROM new_buildings nb
    LEFT JOIN new_building_images nbi ON nb.id = nbi.new_building_id AND nbi.is_primary = 1
    LEFT JOIN ekb_districts d ON nb.district_id = d.id
    LEFT JOIN developers dev ON nb.developer_id = dev.id
    $whereClause
    ORDER BY nb.featured DESC, nb.created_at DESC
    LIMIT $perPage OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$buildings = $stmt->fetchAll();

// Справочники для фильтров
$districts = $pdo->query("SELECT id, name FROM ekb_districts ORDER BY sort_order")->fetchAll();
$developers = $pdo->query("SELECT id, name FROM developers WHERE is_active = 1 ORDER BY name")->fetchAll();

$pageTitle = 'Новостройки';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | Admin CRM</title>
    
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
                <h1 class="admin-title">
                    <i class="fas fa-city"></i> <?= $pageTitle ?>
                </h1>
                <a href="new-building-edit.php" class="btn btn--primary">
                    <i class="fas fa-plus"></i> Добавить ЖК
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
                        <div class="admin-filters__search">
                            <i class="fas fa-search"></i>
                            <input type="text" name="search" placeholder="Поиск ЖК..." value="<?= escape($search) ?>">
                        </div>
                        
                        <select name="district" class="admin-select">
                            <option value="">Все районы</option>
                            <?php foreach ($districts as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= $district == $d['id'] ? 'selected' : '' ?>>
                                <?= escape($d['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <select name="developer" class="admin-select">
                            <option value="">Все застройщики</option>
                            <?php foreach ($developers as $dev): ?>
                            <option value="<?= $dev['id'] ?>" <?= $developer == $dev['id'] ? 'selected' : '' ?>>
                                <?= escape($dev['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <select name="status" class="admin-select">
                            <option value="">Все статусы</option>
                            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Активен</option>
                            <option value="hidden" <?= $status === 'hidden' ? 'selected' : '' ?>>Скрыт</option>
                            <option value="sold-out" <?= $status === 'sold-out' ? 'selected' : '' ?>>Распродан</option>
                        </select>
                        
                        <button type="submit" class="btn btn--primary">
                            <i class="fas fa-filter"></i> Применить
                        </button>
                        <a href="new-buildings.php" class="btn btn--secondary">
                            <i class="fas fa-times"></i> Сбросить
                        </a>
                    </form>
                </div>
            </div>
            
            <!-- Results info -->
            <div class="admin-results-info">
                Найдено ЖК: <strong><?= number_format($totalCount) ?></strong>
            </div>
            
            <!-- Buildings Grid -->
            <?php if (empty($buildings)): ?>
            <div class="admin-card">
                <div class="admin-card__body text-center text-muted">
                    <i class="fas fa-city fa-3x" style="opacity: 0.3; margin-bottom: var(--space-4);"></i>
                    <p>Новостройки не найдены</p>
                </div>
            </div>
            <?php else: ?>
            <div class="buildings-grid">
                <?php foreach ($buildings as $b): ?>
                <div class="building-card">
                    <div class="building-card__image">
                        <img src="<?= imgSrc($b['primary_image']?? 'https://via.placeholder.com/400x250') ?>" alt="<?= escape($b['name']) ?>">
                        <?php if ($b['featured']): ?>
                        <span class="building-card__badge">⭐ Рекомендуем</span>
                        <?php endif; ?>
                        <span class="building-card__status building-card__status--<?= $b['status'] ?>">
                            <?= match($b['status']) {
                                'active' => 'Активен',
                                'hidden' => 'Скрыт',
                                'sold-out' => 'Распродан',
                                default => $b['status']
                            } ?>
                        </span>
                    </div>
                    
                    <div class="building-card__body">
                        <h3 class="building-card__title">
                            <a href="new-building-edit.php?id=<?= $b['id'] ?>"><?= escape($b['name']) ?></a>
                        </h3>
                        
                        <div class="building-card__meta">
                            <?php if ($b['developer_name']): ?>
                            <span><i class="fas fa-hard-hat"></i> <?= escape($b['developer_name']) ?></span>
                            <?php endif; ?>
                            <?php if ($b['district_name']): ?>
                            <span><i class="fas fa-map-marker-alt"></i> <?= escape($b['district_name']) ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="building-card__info">
                            <div class="building-card__info-item">
                                <span class="label">Сдача</span>
                                <span class="value">
                                    <?php if ($b['is_completed']): ?>
                                    <span class="text-success">Сдан</span>
                                    <?php elseif ($b['completion_quarter'] && $b['completion_year']): ?>
                                    Q<?= $b['completion_quarter'] ?> <?= $b['completion_year'] ?>
                                    <?php else: ?>
                                    —
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="building-card__info-item">
                                <span class="label">Цена от</span>
                                <span class="value"><?= $b['price_from'] ? formatPrice($b['price_from']) : '—' ?></span>
                            </div>
                            <div class="building-card__info-item">
                                <span class="label">Планировок</span>
                                <span class="value"><?= $b['layouts_count'] ?></span>
                            </div>
                        </div>
                        
                        <div class="building-card__actions">
                            <a href="new-building-edit.php?id=<?= $b['id'] ?>" class="btn btn--sm btn--secondary">
                                <i class="fas fa-edit"></i> Редактировать
                            </a>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="toggle_featured">
                                <input type="hidden" name="building_id" value="<?= $b['id'] ?>">
                                <button type="submit" class="btn btn--sm btn--<?= $b['featured'] ? 'warning' : 'secondary' ?>" title="В рекомендуемые">
                                    <i class="fas fa-star"></i>
                                </button>
                            </form>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Удалить ЖК?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="building_id" value="<?= $b['id'] ?>">
                                <button type="submit" class="btn btn--sm btn--danger" title="Удалить">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
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
        .buildings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: var(--space-6);
        }
        
        .building-card {
            background: var(--color-white);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: all var(--transition-fast);
        }
        
        .building-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }
        
        .building-card__image {
            position: relative;
            height: 200px;
            overflow: hidden;
        }
        
        .building-card__image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .building-card__badge {
            position: absolute;
            top: var(--space-3);
            left: var(--space-3);
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: white;
            padding: var(--space-1) var(--space-3);
            border-radius: var(--radius-full);
            font-size: var(--text-xs);
            font-weight: var(--font-semibold);
        }
        
        .building-card__status {
            position: absolute;
            top: var(--space-3);
            right: var(--space-3);
            padding: var(--space-1) var(--space-3);
            border-radius: var(--radius-full);
            font-size: var(--text-xs);
            font-weight: var(--font-medium);
        }
        
        .building-card__status--active {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .building-card__status--hidden {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .building-card__status--sold-out {
            background-color: #fee2e2;
            color: #991b1b;
        }
        
        .building-card__body {
            padding: var(--space-5);
        }
        
        .building-card__title {
            font-size: var(--text-lg);
            font-weight: var(--font-semibold);
            margin-bottom: var(--space-2);
        }
        
        .building-card__title a {
            color: var(--color-navy);
        }
        
        .building-card__title a:hover {
            color: var(--color-accent);
        }
        
        .building-card__meta {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-3);
            font-size: var(--text-sm);
            color: var(--color-text-light);
            margin-bottom: var(--space-4);
        }
        
        .building-card__meta span {
            display: flex;
            align-items: center;
            gap: var(--space-1);
        }
        
        .building-card__meta i {
            color: var(--color-accent);
        }
        
        .building-card__info {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: var(--space-3);
            padding: var(--space-4) 0;
            border-top: 1px solid var(--color-border);
            border-bottom: 1px solid var(--color-border);
            margin-bottom: var(--space-4);
        }
        
        .building-card__info-item {
            text-align: center;
        }
        
        .building-card__info-item .label {
            display: block;
            font-size: var(--text-xs);
            color: var(--color-text-light);
            margin-bottom: var(--space-1);
        }
        
        .building-card__info-item .value {
            font-weight: var(--font-semibold);
            color: var(--color-navy);
        }
        
        .building-card__actions {
            display: flex;
            gap: var(--space-2);
        }
        
        .building-card__actions .btn {
            flex: 1;
        }
        
        .building-card__actions form {
            flex: 0;
        }
        
        .btn--warning {
            background-color: #fbbf24;
            border-color: #fbbf24;
            color: #78350f;
        }
        
        .text-success {
            color: #059669;
        }
    </style>
</body>
</html>

