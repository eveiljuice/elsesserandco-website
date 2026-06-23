<?php
/**
 * Agent Profile - Elsesser & Co.
 * Профиль агента: данные, статистика, контакты
 */

require_once __DIR__ . '/../includes/config/database.php';
require_once __DIR__ . '/../includes/auth/check_auth.php';

requireAgent();

$pdo = getDBConnection();
$userId = getCurrentUserId();

// Полные данные агента
$stmt = $pdo->prepare("
    SELECT id, first_name, last_name, email, phone, avatar, role, is_active, created_at
    FROM users WHERE id = ?
");
$stmt->execute([$userId]);
$agent = $stmt->fetch();

if (!$agent) {
    http_response_code(404);
    die('Профиль не найден');
}

// Статистика по объектам
$stmt = $pdo->prepare("SELECT COUNT(*) FROM properties WHERE agent_id = ?");
$stmt->execute([$userId]);
$totalProperties = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM properties WHERE agent_id = ? AND status = 'available'");
$stmt->execute([$userId]);
$activeProperties = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM properties WHERE agent_id = ? AND status IN ('sold', 'rented')");
$stmt->execute([$userId]);
$closedDeals = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT SUM(views_count) FROM properties WHERE agent_id = ?");
$stmt->execute([$userId]);
$totalViews = $stmt->fetchColumn() ?: 0;

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM inquiries i
    JOIN properties p ON i.property_id = p.id
    WHERE p.agent_id = ?
");
$stmt->execute([$userId]);
$totalInquiries = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT AVG(rating) FROM reviews r
    JOIN properties p ON r.property_id = p.id
    WHERE p.agent_id = ? AND r.is_approved = 1
");
$stmt->execute([$userId]);
$avgRating = $stmt->fetchColumn() ?: 0;

$pageTitle = 'Профиль агента';
$unreadMessages = 0;
$newInquiries = 0;
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
                    <h1 class="admin-title">Мой профиль</h1>
                    <div class="admin-breadcrumb">
                        <i class="fas fa-user"></i> Аккаунт агента
                    </div>
                </div>
                <a href="dashboard.php" class="btn btn--secondary">
                    <i class="fas fa-arrow-left"></i> Назад
                </a>
            </div>

            <div class="admin-profile-grid">
                <!-- Левая колонка: аватар + контакты -->
                <div class="admin-card admin-profile-card">
                    <div class="admin-card__body">
                        <div class="admin-profile-avatar">
                            <?php if (!empty($agent['avatar'])): ?>
                            <img src="<?= htmlspecialchars($agent['avatar']) ?>" alt="<?= htmlspecialchars($agent['first_name']) ?>">
                            <?php else: ?>
                            <div class="admin-profile-avatar__fallback">
                                <?= strtoupper(substr($agent['first_name'], 0, 1) . substr($agent['last_name'] ?? '', 0, 1)) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <h2 class="admin-profile-name"><?= escape($agent['first_name'] . ' ' . $agent['last_name']) ?></h2>
                        <div class="admin-profile-role">
                            <i class="fas fa-briefcase"></i> Агент
                        </div>

                        <div class="admin-profile-info">
                            <div class="admin-profile-info__row">
                                <i class="fas fa-envelope"></i>
                                <a href="mailto:<?= escape($agent['email']) ?>"><?= escape($agent['email']) ?></a>
                            </div>
                            <?php if (!empty($agent['phone'])): ?>
                            <div class="admin-profile-info__row">
                                <i class="fas fa-phone"></i>
                                <a href="tel:<?= escape($agent['phone']) ?>"><?= escape($agent['phone']) ?></a>
                            </div>
                            <?php endif; ?>
                            <div class="admin-profile-info__row">
                                <i class="fas fa-calendar-alt"></i>
                                <span>На сайте с <?= date('d.m.Y', strtotime($agent['created_at'])) ?></span>
                            </div>
                        </div>

                        <a href="/profile.php" class="btn btn--secondary btn--full">
                            <i class="fas fa-edit"></i> Редактировать профиль
                        </a>
                    </div>
                </div>

                <!-- Правая колонка: статистика -->
                <div class="admin-profile-stats">
                    <div class="admin-card">
                        <div class="admin-card__body">
                            <h3 class="admin-card__title"><i class="fas fa-chart-bar"></i> Статистика</h3>
                            <div class="admin-stats-list">
                                <div class="admin-stats-list__row">
                                    <span class="admin-stats-list__label">Всего объектов</span>
                                    <span class="admin-stats-list__value"><?= $totalProperties ?></span>
                                </div>
                                <div class="admin-stats-list__row">
                                    <span class="admin-stats-list__label">Активных</span>
                                    <span class="admin-stats-list__value text-success"><?= $activeProperties ?></span>
                                </div>
                                <div class="admin-stats-list__row">
                                    <span class="admin-stats-list__label">Закрытых сделок</span>
                                    <span class="admin-stats-list__value"><?= $closedDeals ?></span>
                                </div>
                                <div class="admin-stats-list__row">
                                    <span class="admin-stats-list__label">Заявок получено</span>
                                    <span class="admin-stats-list__value"><?= $totalInquiries ?></span>
                                </div>
                                <div class="admin-stats-list__row">
                                    <span class="admin-stats-list__label">Просмотров объектов</span>
                                    <span class="admin-stats-list__value"><?= number_format($totalViews) ?></span>
                                </div>
                                <?php if ($avgRating > 0): ?>
                                <div class="admin-stats-list__row">
                                    <span class="admin-stats-list__label">Средний рейтинг</span>
                                    <span class="admin-stats-list__value">
                                        <i class="fas fa-star" style="color:#fbbf24;"></i> <?= number_format($avgRating, 1) ?> / 5
                                    </span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="admin-card">
                        <div class="admin-card__body">
                            <h3 class="admin-card__title"><i class="fas fa-cog"></i> Действия</h3>
                            <div class="admin-actions-list">
                                <a href="add-property.php" class="admin-action-item">
                                    <i class="fas fa-plus"></i>
                                    <div>
                                        <strong>Добавить объект</strong>
                                        <small>Новое объявление на сайте</small>
                                    </div>
                                </a>
                                <a href="requests.php" class="admin-action-item">
                                    <i class="fas fa-envelope"></i>
                                    <div>
                                        <strong>Заявки</strong>
                                        <small>Запросы от клиентов</small>
                                    </div>
                                </a>
                                <a href="calendar.php" class="admin-action-item">
                                    <i class="fas fa-calendar"></i>
                                    <div>
                                        <strong>Календарь показов</strong>
                                        <small>Запланированные встречи</small>
                                    </div>
                                </a>
                                <a href="/chat.php" class="admin-action-item">
                                    <i class="fas fa-comments"></i>
                                    <div>
                                        <strong>Сообщения</strong>
                                        <small>Чат с клиентами</small>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>