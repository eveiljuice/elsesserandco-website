<?php
/**
 * Agent Panel - Elsesser & Co.
 * Панель управления агента
 */

require_once __DIR__ . '/../includes/config/database.php';
require_once __DIR__ . '/../includes/auth/check_auth.php';

// Только для агентов
requireAgent();

$pdo = getDBConnection();
$userId = getCurrentUserId();

// Получаем статистику агента
$stats = [];

// Количество моих объектов
$stmt = $pdo->prepare("SELECT COUNT(*) FROM properties WHERE agent_id = ?");
$stmt->execute([$userId]);
$stats['total_properties'] = $stmt->fetchColumn();

// По статусам
$stmt = $pdo->prepare("SELECT COUNT(*) FROM properties WHERE agent_id = ? AND status = 'available'");
$stmt->execute([$userId]);
$stats['available'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM properties WHERE agent_id = ? AND status = 'sold'");
$stmt->execute([$userId]);
$stats['sold'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM properties WHERE agent_id = ? AND status = 'rented'");
$stmt->execute([$userId]);
$stats['rented'] = $stmt->fetchColumn();

// Заявки на мои объекты
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM inquiries i 
    JOIN properties p ON i.property_id = p.id 
    WHERE p.agent_id = ?
");
$stmt->execute([$userId]);
$stats['total_inquiries'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM inquiries i 
    JOIN properties p ON i.property_id = p.id 
    WHERE p.agent_id = ? AND i.status = 'new'
");
$stmt->execute([$userId]);
$stats['new_inquiries'] = $stmt->fetchColumn();

// Просмотры объектов
$stmt = $pdo->prepare("SELECT SUM(views_count) FROM properties WHERE agent_id = ?");
$stmt->execute([$userId]);
$stats['total_views'] = $stmt->fetchColumn() ?? 0;

// Просмотры на этой неделе
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM viewings 
    WHERE agent_id = ? AND viewing_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
");
$stmt->execute([$userId]);
$stats['week_viewings'] = $stmt->fetchColumn();

// Фильтр статуса и категории для объектов
$statusFilter = $_GET['status'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$statusWhere = '';
$statusParams = [$userId];

if (!empty($statusFilter) && in_array($statusFilter, ['available', 'sold', 'rented', 'pending'])) {
    $statusWhere .= " AND p.status = ?";
    $statusParams[] = $statusFilter;
}

if (!empty($categoryFilter)) {
    if ($categoryFilter === 'sale') {
        $statusWhere .= " AND (p.category = 'sale' OR p.listing_type = 'sale')";
    } elseif ($categoryFilter === 'rent') {
        $statusWhere .= " AND (p.category = 'rent' OR p.listing_type = 'rent')";
    }
}

// Мои объекты
$stmt = $pdo->prepare("
    SELECT p.*, pi.image_url as primary_image,
           (SELECT COUNT(*) FROM inquiries WHERE property_id = p.id) as inquiry_count,
           d.name as district_name
    FROM properties p
    LEFT JOIN property_images pi ON p.id = pi.property_id AND pi.is_primary = 1
    LEFT JOIN ekb_districts d ON p.district_id = d.id
    WHERE p.agent_id = ? $statusWhere
    ORDER BY p.created_at DESC
    LIMIT 20
");
$stmt->execute($statusParams);
$myProperties = $stmt->fetchAll();

// Последние заявки
$stmt = $pdo->prepare("
    SELECT i.*, p.title as property_title, p.id as property_id
    FROM inquiries i
    JOIN properties p ON i.property_id = p.id
    WHERE p.agent_id = ?
    ORDER BY i.created_at DESC
    LIMIT 5
");
$stmt->execute([$userId]);
$recentInquiries = $stmt->fetchAll();

// Ближайшие просмотры
$stmt = $pdo->prepare("
    SELECT v.*, p.title as property_title, p.location
    FROM viewings v
    JOIN properties p ON v.property_id = p.id
    WHERE v.agent_id = ? AND v.viewing_date >= CURDATE() AND v.status = 'scheduled'
    ORDER BY v.viewing_date ASC, v.viewing_time ASC
    LIMIT 5
");
$stmt->execute([$userId]);
$upcomingViewings = $stmt->fetchAll();

$pageTitle = 'CRM Агента';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | Elsesser & Co.</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Styles -->
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
                    <h1 class="admin-title">Панель управления агента</h1>
                    <div class="admin-breadcrumb">
                        <i class="fas fa-briefcase"></i> Кабинет агента
                    </div>
                </div>
                <a href="add-property.php" class="btn btn--primary">
                    <i class="fas fa-plus"></i> Добавить объект
                </a>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card stat-card--primary">
                    <div class="stat-card__icon">
                        <i class="fas fa-building"></i>
                    </div>
                    <div class="stat-card__content">
                        <div class="stat-card__value"><?= $stats['total_properties'] ?></div>
                        <div class="stat-card__label">Моих объектов</div>
                        <div class="stat-card__meta">
                            <?= $stats['available'] ?> активных
                        </div>
                    </div>
                </div>

                <div class="stat-card stat-card--success">
                    <div class="stat-card__icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="stat-card__content">
                        <div class="stat-card__value"><?= $stats['total_inquiries'] ?></div>
                        <div class="stat-card__label">Заявок</div>
                        <div class="stat-card__meta">
                            <?= $stats['new_inquiries'] ?> новых
                        </div>
                    </div>
                </div>

                <div class="stat-card stat-card--warning">
                    <div class="stat-card__icon">
                        <i class="fas fa-eye"></i>
                    </div>
                    <div class="stat-card__content">
                        <div class="stat-card__value"><?= number_format($stats['total_views']) ?></div>
                        <div class="stat-card__label">Просмотров</div>
                        <div class="stat-card__meta">
                            на всех объектах
                        </div>
                    </div>
                </div>

                <div class="stat-card stat-card--info">
                    <div class="stat-card__icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-card__content">
                        <div class="stat-card__value"><?= $stats['sold'] + $stats['rented'] ?></div>
                        <div class="stat-card__label">Закрытых сделок</div>
                        <div class="stat-card__meta">
                            <?= $stats['sold'] ?> продано, <?= $stats['rented'] ?> в аренду
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Grid -->
            <div class="admin-grid">
                <!-- My Properties -->
                <div class="admin-card">
                    <div class="admin-card__header">
                        <h2 class="admin-card__title">
                            <i class="fas fa-building"></i>
                            <?php if ($categoryFilter === 'sale'): ?>
                                Продажа
                            <?php elseif ($categoryFilter === 'rent'): ?>
                                Аренда
                            <?php else: ?>
                                Мои объекты
                            <?php endif; ?>
                        </h2>
                        <div class="status-filters">
                            <?php $catParam = $categoryFilter ? "&category=$categoryFilter" : ''; ?>
                            <a href="?status=<?= $catParam ?>" class="status-filter <?= empty($statusFilter) ? 'active' : '' ?>">Все</a>
                            <a href="?status=available<?= $catParam ?>" class="status-filter <?= $statusFilter === 'available' ? 'active' : '' ?>">Активные</a>
                            <a href="?status=pending<?= $catParam ?>" class="status-filter <?= $statusFilter === 'pending' ? 'active' : '' ?>">Ожидание</a>
                            <a href="?status=sold<?= $catParam ?>" class="status-filter <?= $statusFilter === 'sold' ? 'active' : '' ?>">Продано</a>
                        </div>
                    </div>
                    <div class="admin-card__body">
                        <?php if (empty($myProperties)): ?>
                        <div class="empty-state">
                            <i class="fas fa-building"></i>
                            <p>У вас пока нет объектов</p>
                            <a href="add-property.php" class="btn btn--primary btn--sm">Добавить первый объект</a>
                        </div>
                        <?php else: ?>
                        <div class="property-list">
                            <?php foreach ($myProperties as $property): ?>
                            <div class="property-item">
                                <div class="property-item__image">
                                    <img src="<?= imgSrc($property['primary_image']?? 'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?w=100&q=80') ?>"
                                         alt="<?= escape($property['title']) ?>">
                                </div>
                                <div class="property-item__content">
                                    <div class="property-item__title">
                                        <a href="../property.php?id=<?= $property['id'] ?>" target="_blank">
                                            <?= escape($property['title_ru'] ?? $property['title']) ?>
                                        </a>
                                    </div>
                                    <div class="property-item__meta">
                                        <?= formatPrice($property['price']) ?> •
                                        <?= $property['bedrooms'] ?> комн. •
                                        <?= number_format($property['area_total'] ?? $property['area_sqft']) ?> м²
                                        <?php if (!empty($property['district_name'])): ?>
                                        • <?= escape($property['district_name']) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="property-item__stats">
                                        <span><i class="fas fa-eye"></i> <?= $property['views_count'] ?></span>
                                        <span><i class="fas fa-envelope"></i> <?= $property['inquiry_count'] ?></span>
                                        <span class="badge badge--<?= $property['status'] === 'available' ? 'success' : 'secondary' ?>">
                                            <?= match($property['status']) {
                                                'available' => 'Активен',
                                                'sold' => 'Продан',
                                                'rented' => 'Арендован',
                                                'pending' => 'Ожидание',
                                                default => $property['status']
                                            } ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="property-item__actions">
                                    <a href="edit-property.php?id=<?= $property['id'] ?>" class="btn btn--sm btn--secondary" title="Редактировать">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Sidebar Content -->
                <div class="dashboard-sidebar">
                    <!-- Recent Inquiries -->
                    <div class="admin-card">
                        <div class="admin-card__header">
                            <h2 class="admin-card__title">
                                <i class="fas fa-envelope"></i> Последние заявки
                            </h2>
                            <a href="requests.php" class="btn btn--sm btn--secondary">Все</a>
                        </div>
                        <div class="admin-card__body">
                            <?php if (empty($recentInquiries)): ?>
                            <p class="text-muted text-sm">Нет заявок</p>
                            <?php else: ?>
                            <div class="inquiry-mini-list">
                                <?php foreach ($recentInquiries as $inquiry): ?>
                                <div class="inquiry-mini">
                                    <div class="inquiry-mini__content">
                                        <div class="inquiry-mini__name"><?= escape($inquiry['name']) ?></div>
                                        <div class="inquiry-mini__property"><?= escape($inquiry['property_title']) ?></div>
                                    </div>
                                    <div class="inquiry-mini__meta">
                                        <span class="badge badge--<?= $inquiry['status'] === 'new' ? 'success' : 'secondary' ?>">
                                            <?= $inquiry['status'] === 'new' ? 'Новая' : 'Обработана' ?>
                                        </span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Upcoming Viewings -->
                    <div class="admin-card">
                        <div class="admin-card__header">
                            <h2 class="admin-card__title">
                                <i class="fas fa-calendar"></i> Ближайшие просмотры
                            </h2>
                            <a href="calendar.php" class="btn btn--sm btn--secondary">Все</a>
                        </div>
                        <div class="admin-card__body">
                            <?php if (empty($upcomingViewings)): ?>
                            <p class="text-muted text-sm">Нет запланированных просмотров</p>
                            <?php else: ?>
                            <div class="viewing-list">
                                <?php foreach ($upcomingViewings as $viewing): ?>
                                <div class="viewing-item">
                                    <div class="viewing-item__date">
                                        <div class="viewing-item__day"><?= date('d', strtotime($viewing['viewing_date'])) ?></div>
                                        <div class="viewing-item__month"><?= date('M', strtotime($viewing['viewing_date'])) ?></div>
                                    </div>
                                    <div class="viewing-item__content">
                                        <div class="viewing-item__time"><?= date('H:i', strtotime($viewing['viewing_time'])) ?></div>
                                        <div class="viewing-item__client"><?= escape($viewing['client_name']) ?></div>
                                        <div class="viewing-item__property"><?= escape($viewing['property_title']) ?></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
