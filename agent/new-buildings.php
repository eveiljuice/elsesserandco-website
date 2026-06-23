<?php
/**
 * Agent New Buildings Management - Elsesser & Co.
 * Управление новостройками (ЖК) агента
 */

require_once __DIR__ . '/../includes/config/database.php';
require_once __DIR__ . '/../includes/auth/check_auth.php';

requireAgent();

$pdo = getDBConnection();
$userId = getCurrentUserId();
$message = '';
$messageType = '';

// Обработка действий
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $buildingId = (int)($_POST['building_id'] ?? 0);
    
    // Проверяем, что ЖК принадлежит агенту
    $stmt = $pdo->prepare("SELECT id FROM new_buildings WHERE id = ? AND agent_id = ?");
    $stmt->execute([$buildingId, $userId]);
    $canEdit = $stmt->fetch() || isAdmin();
    
    if ($canEdit) {
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
    } else {
        $message = 'Нет доступа к этому объекту';
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

// Построение SQL - только ЖК агента
$where = ["nb.agent_id = ?"];
$params = [$userId];

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
           dev.name as developer_name,
           (SELECT COUNT(*) FROM new_building_layouts WHERE new_building_id = nb.id) as layouts_count
    FROM new_buildings nb
    LEFT JOIN new_building_images nbi ON nb.id = nbi.new_building_id AND nbi.is_primary = 1
    LEFT JOIN ekb_districts d ON nb.district_id = d.id
    LEFT JOIN developers dev ON nb.developer_id = dev.id
    $whereClause
    ORDER BY nb.created_at DESC
    LIMIT $perPage OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$buildings = $stmt->fetchAll();

// Справочники для фильтров
$districts = $pdo->query("SELECT * FROM ekb_districts ORDER BY sort_order")->fetchAll();
$developers = $pdo->query("SELECT * FROM developers WHERE is_active = 1 ORDER BY name")->fetchAll();

$pageTitle = 'Мои новостройки';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | Elsesser & Co.</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <link rel="stylesheet" href="../css/reset.css">
    <link rel="stylesheet" href="../css/variables.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/agent-dashboard.css">
</head>
<body class="admin-body">
    <?php include __DIR__ . '/includes/agent-header.php'; ?>
    
    <div class="admin-container">
        <?php include __DIR__ . '/includes/agent-sidebar.php'; ?>
        
        <main class="admin-main">
            <div class="admin-header">
                <div>
                    <h1 class="admin-title"><?= $pageTitle ?></h1>
                    <div class="admin-breadcrumb">
                        <a href="dashboard.php">Dashboard</a> / Новостройки
                    </div>
                </div>
                <a href="new-building-edit.php" class="btn btn--primary">
                    <i class="fas fa-plus"></i> Добавить ЖК
                </a>
            </div>
            
            <?php if ($message): ?>
            <div class="alert alert--<?= $messageType ?>">
                <?= escape($message) ?>
            </div>
            <?php endif; ?>
            
            <!-- Фильтры -->
            <div class="admin-card">
                <div class="admin-card__body">
                    <form method="GET" class="filter-form">
                        <div class="filter-row">
                            <div class="filter-group">
                                <input type="text" name="search" class="form-input" 
                                       placeholder="Поиск по названию..." value="<?= escape($search) ?>">
                            </div>
                            
                            <div class="filter-group">
                                <select name="status" class="form-select">
                                    <option value="">Все статусы</option>
                                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Активные</option>
                                    <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Сданы</option>
                                    <option value="suspended" <?= $status === 'suspended' ? 'selected' : '' ?>>Приостановлены</option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <select name="district" class="form-select">
                                    <option value="">Все районы</option>
                                    <?php foreach ($districts as $d): ?>
                                    <option value="<?= $d['id'] ?>" <?= $district == $d['id'] ? 'selected' : '' ?>>
                                        <?= escape($d['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <select name="developer" class="form-select">
                                    <option value="">Все застройщики</option>
                                    <?php foreach ($developers as $dev): ?>
                                    <option value="<?= $dev['id'] ?>" <?= $developer == $dev['id'] ? 'selected' : '' ?>>
                                        <?= escape($dev['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn--primary">
                                <i class="fas fa-search"></i> Найти
                            </button>
                            
                            <a href="new-buildings.php" class="btn btn--secondary">Сброс</a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Таблица ЖК -->
            <div class="admin-card">
                <div class="admin-card__header">
                    <h2 class="admin-card__title">
                        Найдено: <?= $totalCount ?> ЖК
                    </h2>
                </div>
                <div class="admin-card__body admin-card__body--no-padding">
                    <?php if (empty($buildings)): ?>
                    <div class="empty-state">
                        <i class="fas fa-city"></i>
                        <p>Нет новостроек</p>
                        <a href="new-building-edit.php" class="btn btn--primary">Добавить ЖК</a>
                    </div>
                    <?php else: ?>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Фото</th>
                                <th>Название</th>
                                <th>Застройщик</th>
                                <th>Район</th>
                                <th>Цена от</th>
                                <th>Срок сдачи</th>
                                <th>Статус</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($buildings as $building): ?>
                            <tr>
                                <td>
                                    <div class="table-image">
                                        <img src="<?= imgSrc($building['primary_image']?? 'https://via.placeholder.com/60x40?text=ЖК') ?>" 
                                             alt="<?= escape($building['name']) ?>">
                                    </div>
                                </td>
                                <td>
                                    <strong><?= escape($building['name']) ?></strong>
                                    <?php if ($building['featured']): ?>
                                    <span class="badge badge--warning"><i class="fas fa-star"></i></span>
                                    <?php endif; ?>
                                    <br>
                                    <small class="text-muted"><?= escape($building['address']) ?></small>
                                </td>
                                <td><?= escape($building['developer_name'] ?? '-') ?></td>
                                <td><?= escape($building['district_name'] ?? '-') ?></td>
                                <td>
                                    <?php if ($building['min_price']): ?>
                                    от <?= formatPrice($building['min_price']) ?>
                                    <?php else: ?>
                                    -
                                    <?php endif; ?>
                                </td>
                                <td><?= escape($building['completion_date'] ?? '-') ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="change_status">
                                        <input type="hidden" name="building_id" value="<?= $building['id'] ?>">
                                        <select name="status" class="form-select form-select--sm" onchange="this.form.submit()">
                                            <option value="active" <?= $building['status'] === 'active' ? 'selected' : '' ?>>Активен</option>
                                            <option value="completed" <?= $building['status'] === 'completed' ? 'selected' : '' ?>>Сдан</option>
                                            <option value="suspended" <?= $building['status'] === 'suspended' ? 'selected' : '' ?>>Приостановлен</option>
                                        </select>
                                    </form>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <a href="new-building-edit.php?id=<?= $building['id'] ?>" class="btn btn--sm btn--secondary" title="Редактировать">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Удалить этот ЖК?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="building_id" value="<?= $building['id'] ?>">
                                            <button type="submit" class="btn btn--sm btn--danger" title="Удалить">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
                
                <!-- Пагинация -->
                <?php if ($totalPages > 1): ?>
                <div class="admin-card__footer">
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>&status=<?= $status ?>&district=<?= $district ?>&developer=<?= $developer ?>&search=<?= urlencode($search) ?>" class="pagination__link">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?page=<?= $i ?>&status=<?= $status ?>&district=<?= $district ?>&developer=<?= $developer ?>&search=<?= urlencode($search) ?>" 
                           class="pagination__link <?= $i === $page ? 'pagination__link--active' : '' ?>">
                            <?= $i ?>
                        </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>&status=<?= $status ?>&district=<?= $district ?>&developer=<?= $developer ?>&search=<?= urlencode($search) ?>" class="pagination__link">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>

