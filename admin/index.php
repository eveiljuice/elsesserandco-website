<?php
/**
 * Admin Panel - Elsesser & Co.
 * Панель управления администратора
 */

require_once __DIR__ . '/../includes/config/database.php';
require_once __DIR__ . '/../includes/auth/check_auth.php';

// Только для администраторов
requireAdmin();

$pdo = getDBConnection();

// Получаем статистику одним запросом на таблицу (3 запроса вместо 7).
$stats = [];

$row = $pdo->query("
    SELECT
        COUNT(*) AS properties_total,
        SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) AS properties_available,
        SUM(CASE WHEN featured = 1 THEN 1 ELSE 0 END) AS properties_featured
    FROM properties
")->fetch();
$stats['properties_total']     = (int)($row['properties_total'] ?? 0);
$stats['properties_available'] = (int)($row['properties_available'] ?? 0);
$stats['properties_featured']  = (int)($row['properties_featured'] ?? 0);

$row = $pdo->query("
    SELECT
        COUNT(*) AS inquiries_total,
        SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) AS inquiries_new,
        SUM(CASE WHEN status = 'contacted' THEN 1 ELSE 0 END) AS inquiries_contacted
    FROM inquiries
")->fetch();
$stats['inquiries_total']     = (int)($row['inquiries_total'] ?? 0);
$stats['inquiries_new']       = (int)($row['inquiries_new'] ?? 0);
$stats['inquiries_contacted'] = (int)($row['inquiries_contacted'] ?? 0);

$stats['users_total'] = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

// Последние заявки
$stmt = $pdo->query("
    SELECT i.*, p.title as property_title
    FROM inquiries i
    LEFT JOIN properties p ON i.property_id = p.id
    ORDER BY i.created_at DESC
    LIMIT 10
");
$recentInquiries = $stmt->fetchAll();

// Последние объекты
$stmt = $pdo->query("
    SELECT p.*, pi.image_url as primary_image
    FROM properties p
    LEFT JOIN property_images pi ON p.id = pi.property_id AND pi.is_primary = 1
    ORDER BY p.created_at DESC
    LIMIT 6
");
$recentProperties = $stmt->fetchAll();

$pageTitle = 'Панель администратора';
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
</head>
<body class="admin-body">
    <?php include __DIR__ . '/includes/admin-header.php'; ?>
    
    <div class="admin-container">
        <?php include __DIR__ . '/includes/admin-sidebar.php'; ?>
        
        <main class="admin-main">
            <div class="admin-header">
                <h1 class="admin-title">Панель управления администратора</h1>
                <div class="admin-breadcrumb">
                    <i class="fas fa-home"></i> Главная
                </div>
            </div>
            
            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card stat-card--primary">
                    <div class="stat-card__icon">
                        <i class="fas fa-building"></i>
                    </div>
                    <div class="stat-card__content">
                        <div class="stat-card__value"><?= number_format($stats['properties_total']) ?></div>
                        <div class="stat-card__label">Всего объектов</div>
                        <div class="stat-card__meta">
                            <?= $stats['properties_available'] ?> доступно
                        </div>
                    </div>
                </div>
                
                <div class="stat-card stat-card--success">
                    <div class="stat-card__icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="stat-card__content">
                        <div class="stat-card__value"><?= number_format($stats['inquiries_total']) ?></div>
                        <div class="stat-card__label">Всего заявок</div>
                        <div class="stat-card__meta">
                            <?= $stats['inquiries_new'] ?> новых
                        </div>
                    </div>
                </div>
                
                <div class="stat-card stat-card--warning">
                    <div class="stat-card__icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="stat-card__content">
                        <div class="stat-card__value"><?= number_format($stats['properties_featured']) ?></div>
                        <div class="stat-card__label">Рекомендуемые</div>
                        <div class="stat-card__meta">
                            избранные объекты
                        </div>
                    </div>
                </div>
                
                <div class="stat-card stat-card--info">
                    <div class="stat-card__icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-card__content">
                        <div class="stat-card__value"><?= number_format($stats['users_total']) ?></div>
                        <div class="stat-card__label">Пользователей</div>
                        <div class="stat-card__meta">
                            всего зарегистрировано
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Content -->
            <div class="admin-grid">
                <!-- Recent Inquiries -->
                <div class="admin-card">
                    <div class="admin-card__header">
                        <h2 class="admin-card__title">
                            <i class="fas fa-envelope"></i> Последние заявки
                        </h2>
                        <a href="inquiries.php" class="btn btn--sm btn--secondary">
                            Все заявки <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                    <div class="admin-card__body">
                        <?php if (empty($recentInquiries)): ?>
                        <p class="text-muted">Нет заявок</p>
                        <?php else: ?>
                        <div class="inquiry-list">
                            <?php foreach ($recentInquiries as $inquiry): ?>
                            <div class="inquiry-item">
                                <div class="inquiry-item__content">
                                    <div class="inquiry-item__name"><?= escape($inquiry['name']) ?></div>
                                    <div class="inquiry-item__email"><?= escape($inquiry['email']) ?></div>
                                    <?php if ($inquiry['property_title']): ?>
                                    <div class="inquiry-item__property">
                                        <i class="fas fa-home"></i> <?= escape($inquiry['property_title']) ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="inquiry-item__meta">
                                    <span class="badge badge--<?= $inquiry['status'] === 'new' ? 'success' : 'secondary' ?>">
                                        <?= match($inquiry['status']) {
                                            'new' => 'Новая',
                                            'contacted' => 'Обработана',
                                            'scheduled' => 'Запланирована',
                                            'completed' => 'Завершена',
                                            'cancelled' => 'Отменена',
                                            default => $inquiry['status']
                                        } ?>
                                    </span>
                                    <div class="inquiry-item__date">
                                        <?= date('d.m.Y H:i', strtotime($inquiry['created_at'])) ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Recent Properties -->
                <div class="admin-card">
                    <div class="admin-card__header">
                        <h2 class="admin-card__title">
                            <i class="fas fa-building"></i> Последние объекты
                        </h2>
                        <a href="properties.php" class="btn btn--sm btn--secondary">
                            Все объекты <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                    <div class="admin-card__body">
                        <?php if (empty($recentProperties)): ?>
                        <p class="text-muted">Нет объектов</p>
                        <?php else: ?>
                        <div class="property-list">
                            <?php foreach ($recentProperties as $property): ?>
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
                                        <?= $property['bedrooms'] ?> спален •
                                        <?= number_format($property['area_sqft']) ?> м²
                                    </div>
                                </div>
                                <div class="property-item__actions">
                                    <a href="property-edit.php?id=<?= $property['id'] ?>" class="btn btn--sm btn--secondary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
