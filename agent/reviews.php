<?php
/**
 * Agent Reviews - Elsesser & Co.
 * Отзывы на объекты агента
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
    $reviewId = (int)($_POST['review_id'] ?? 0);
    
    // Проверяем, что отзыв на объект агента
    $stmt = $pdo->prepare("
        SELECT r.id FROM reviews r 
        JOIN properties p ON r.property_id = p.id 
        WHERE r.id = ? AND p.agent_id = ?
    ");
    $stmt->execute([$reviewId, $userId]);
    $canEdit = $stmt->fetch();
    
    if ($canEdit) {
        try {
            switch ($action) {
                case 'approve':
                    $stmt = $pdo->prepare("UPDATE reviews SET is_approved = 1 WHERE id = ?");
                    $stmt->execute([$reviewId]);
                    $message = 'Отзыв одобрен';
                    $messageType = 'success';
                    break;
                    
                case 'reject':
                    $stmt = $pdo->prepare("UPDATE reviews SET is_approved = 0 WHERE id = ?");
                    $stmt->execute([$reviewId]);
                    $message = 'Отзыв отклонён';
                    $messageType = 'warning';
                    break;
            }
        } catch (PDOException $e) {
            $message = 'Ошибка: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Фильтр
$filter = $_GET['filter'] ?? 'all';

$where = "p.agent_id = ?";
$params = [$userId];

if ($filter === 'pending') {
    $where .= " AND r.is_approved = 0";
} elseif ($filter === 'approved') {
    $where .= " AND r.is_approved = 1";
}

// Получаем отзывы на объекты агента
$stmt = $pdo->prepare("
    SELECT r.*, 
           p.title as property_title, p.id as property_id,
           u.first_name, u.last_name, u.email as user_email
    FROM reviews r
    JOIN properties p ON r.property_id = p.id
    LEFT JOIN users u ON r.user_id = u.id
    WHERE $where
    ORDER BY r.created_at DESC
");
$stmt->execute($params);
$reviews = $stmt->fetchAll();

// Счётчики
$stmt = $pdo->prepare("SELECT COUNT(*) FROM reviews r JOIN properties p ON r.property_id = p.id WHERE p.agent_id = ?");
$stmt->execute([$userId]);
$totalCount = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM reviews r JOIN properties p ON r.property_id = p.id WHERE p.agent_id = ? AND r.is_approved = 0");
$stmt->execute([$userId]);
$pendingCount = $stmt->fetchColumn();

$pageTitle = 'Отзывы';
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
                        <a href="dashboard.php">Dashboard</a> / Отзывы
                    </div>
                </div>
            </div>
            
            <?php if ($message): ?>
            <div class="alert alert--<?= $messageType ?>">
                <?= escape($message) ?>
            </div>
            <?php endif; ?>
            
            <div class="admin-card">
                <div class="admin-card__header">
                    <h2 class="admin-card__title">
                        <i class="fas fa-star"></i> Отзывы на мои объекты
                        <?php if ($pendingCount > 0): ?>
                        <span class="badge badge--warning"><?= $pendingCount ?> ожидают</span>
                        <?php endif; ?>
                    </h2>
                    <div class="status-filters">
                        <a href="?filter=all" class="status-filter <?= $filter === 'all' ? 'active' : '' ?>">Все (<?= $totalCount ?>)</a>
                        <a href="?filter=pending" class="status-filter <?= $filter === 'pending' ? 'active' : '' ?>">Ожидают (<?= $pendingCount ?>)</a>
                        <a href="?filter=approved" class="status-filter <?= $filter === 'approved' ? 'active' : '' ?>">Одобрены</a>
                    </div>
                </div>
                <div class="admin-card__body">
                    <?php if (empty($reviews)): ?>
                    <div class="empty-state">
                        <i class="fas fa-star"></i>
                        <p>Нет отзывов</p>
                    </div>
                    <?php else: ?>
                    <div class="reviews-list">
                        <?php foreach ($reviews as $review): ?>
                        <div class="review-card <?= !$review['is_approved'] ? 'review-card--pending' : '' ?>">
                            <div class="review-card__header">
                                <div class="review-card__author">
                                    <strong><?= escape($review['author_name'] ?? ($review['first_name'] . ' ' . $review['last_name'])) ?></strong>
                                    <?php if (!empty($review['user_email'])): ?>
                                    <span class="text-muted">(<?= escape($review['user_email']) ?>)</span>
                                    <?php endif; ?>
                                </div>
                                <div class="review-card__rating">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?= $i <= $review['rating'] ? 'text-warning' : 'text-muted' ?>"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            
                            <div class="review-card__property">
                                <a href="../property.php?id=<?= $review['property_id'] ?>" target="_blank">
                                    <i class="fas fa-building"></i> <?= escape($review['property_title']) ?>
                                </a>
                            </div>
                            
                            <div class="review-card__content">
                                <?= nl2br(escape($review['comment'])) ?>
                            </div>
                            
                            <div class="review-card__footer">
                                <div class="review-card__date">
                                    <?= date('d.m.Y H:i', strtotime($review['created_at'])) ?>
                                </div>
                                <div class="review-card__actions">
                                    <?php if (!$review['is_approved']): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="review_id" value="<?= $review['id'] ?>">
                                        <button type="submit" class="btn btn--sm btn--success">
                                            <i class="fas fa-check"></i> Одобрить
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <span class="badge badge--success">
                                        <i class="fas fa-check"></i> Одобрен
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <style>
    .reviews-list {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    .review-card {
        background: var(--color-bg-secondary);
        border-radius: 8px;
        padding: 1rem;
        border-left: 3px solid var(--color-success);
    }
    .review-card--pending {
        border-left-color: var(--color-warning);
        background: rgba(var(--color-warning-rgb), 0.05);
    }
    .review-card__header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.5rem;
    }
    .review-card__property {
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
    }
    .review-card__property a {
        color: var(--color-primary);
    }
    .review-card__content {
        margin-bottom: 1rem;
        line-height: 1.5;
    }
    .review-card__footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.875rem;
    }
    .review-card__date {
        color: var(--color-text-muted);
    }
    .text-warning {
        color: #f59e0b;
    }
    </style>
</body>
</html>

