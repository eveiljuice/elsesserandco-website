<?php
/**
 * Moderate Reviews - Admin Panel
 * Модерация отзывов
 */

require_once __DIR__ . '/../includes/config/database.php';
require_once __DIR__ . '/../includes/auth/check_auth.php';

requireAdmin();

$pdo = getDBConnection();
$message = '';
$error = '';

// Обработка действий
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reviewId = intval($_POST['review_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    
    if ($reviewId && in_array($action, ['approve', 'reject', 'delete'])) {
        try {
            switch ($action) {
                case 'approve':
                    $stmt = $pdo->prepare("UPDATE reviews SET is_approved = 1 WHERE id = ?");
                    $stmt->execute([$reviewId]);
                    $message = 'Отзыв одобрен';
                    break;
                    
                case 'reject':
                    $stmt = $pdo->prepare("UPDATE reviews SET is_approved = 0 WHERE id = ?");
                    $stmt->execute([$reviewId]);
                    $message = 'Отзыв отклонён';
                    break;
                    
                case 'delete':
                    $stmt = $pdo->prepare("DELETE FROM reviews WHERE id = ?");
                    $stmt->execute([$reviewId]);
                    $message = 'Отзыв удалён';
                    break;
            }
        } catch (PDOException $e) {
            $error = 'Ошибка: ' . $e->getMessage();
        }
    }
}

// Фильтр
$filter = $_GET['filter'] ?? 'pending';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = match($filter) {
    'approved' => 'r.is_approved = 1',
    'rejected' => 'r.is_approved = 0',
    default => 'r.is_approved = 0'
};

// Получаем отзывы
$stmt = $pdo->prepare("SELECT COUNT(*) FROM reviews r WHERE $where");
$stmt->execute();
$totalCount = $stmt->fetchColumn();
$totalPages = ceil($totalCount / $perPage);

$stmt = $pdo->prepare("
    SELECT r.*, 
           u.first_name, u.last_name, u.email,
           p.title as property_title,
           a.first_name as agent_first_name, a.last_name as agent_last_name
    FROM reviews r
    JOIN users u ON r.user_id = u.id
    LEFT JOIN properties p ON r.property_id = p.id
    LEFT JOIN users a ON r.agent_id = a.id
    WHERE $where
    ORDER BY r.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$perPage, $offset]);
$reviews = $stmt->fetchAll();

// Статистика
$stmt = $pdo->query("SELECT COUNT(*) FROM reviews WHERE is_approved = 0");
$pendingCount = $stmt->fetchColumn();

$pageTitle = 'Модерация отзывов';
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
</head>
<body class="admin-body">
    <?php include __DIR__ . '/includes/admin-header.php'; ?>
    
    <div class="admin-container">
        <?php include __DIR__ . '/includes/admin-sidebar.php'; ?>
        
        <main class="admin-main">
            <div class="admin-header">
                <div>
                    <h1 class="admin-title">Модерация отзывов</h1>
                    <div class="admin-breadcrumb">
                        <a href="index.php">Dashboard</a> / Отзывы
                    </div>
                </div>
                <?php if ($pendingCount > 0): ?>
                <span class="badge badge--warning" style="font-size: 14px; padding: 8px 16px;">
                    <?= $pendingCount ?> на модерации
                </span>
                <?php endif; ?>
            </div>
            
            <?php if ($message): ?>
            <div class="alert alert--success"><?= escape($message) ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
            <div class="alert alert--error"><?= escape($error) ?></div>
            <?php endif; ?>
            
            <div class="admin-card">
                <div class="admin-card__header">
                    <div class="admin-filters">
                        <a href="?filter=pending" class="btn btn--sm <?= $filter === 'pending' ? 'btn--primary' : 'btn--secondary' ?>">
                            На модерации
                        </a>
                        <a href="?filter=approved" class="btn btn--sm <?= $filter === 'approved' ? 'btn--primary' : 'btn--secondary' ?>">
                            Одобренные
                        </a>
                        <a href="?filter=rejected" class="btn btn--sm <?= $filter === 'rejected' ? 'btn--primary' : 'btn--secondary' ?>">
                            Отклонённые
                        </a>
                    </div>
                </div>
                
                <div class="admin-card__body">
                    <?php if (empty($reviews)): ?>
                    <p class="text-muted text-center">Нет отзывов для отображения</p>
                    <?php else: ?>
                    <div class="reviews-list">
                        <?php foreach ($reviews as $review): ?>
                        <div class="review-item">
                            <div class="review-item__header">
                                <div class="review-item__author">
                                    <strong><?= escape($review['first_name'] . ' ' . $review['last_name']) ?></strong>
                                    <span class="text-muted"><?= escape($review['email']) ?></span>
                                </div>
                                <div class="review-item__rating">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="<?= $i <= $review['rating'] ? 'fas' : 'far' ?> fa-star" style="color: #fbbf24;"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            
                            <div class="review-item__target">
                                <?php if ($review['property_title']): ?>
                                <i class="fas fa-home"></i> <?= escape($review['property_title']) ?>
                                <?php elseif ($review['agent_first_name']): ?>
                                <i class="fas fa-user"></i> Агент: <?= escape($review['agent_first_name'] . ' ' . $review['agent_last_name']) ?>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($review['comment']): ?>
                            <div class="review-item__comment">
                                <?= escape($review['comment']) ?>
                            </div>
                            <?php endif; ?>
                            
                            <div class="review-item__footer">
                                <span class="text-muted text-sm">
                                    <?= date('d.m.Y H:i', strtotime($review['created_at'])) ?>
                                </span>
                                
                                <div class="review-item__actions">
                                    <?php if ($filter !== 'approved'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="review_id" value="<?= $review['id'] ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="btn btn--sm btn--primary" title="Одобрить">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    
                                    <?php if ($filter !== 'rejected'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="review_id" value="<?= $review['id'] ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="btn btn--sm btn--secondary" title="Отклонить">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Удалить отзыв?')">
                                        <input type="hidden" name="review_id" value="<?= $review['id'] ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="btn btn--sm btn--danger" title="Удалить">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if ($totalPages > 1): ?>
                    <div class="admin-pagination">
                        <?php if ($page > 1): ?>
                        <a href="?filter=<?= $filter ?>&page=<?= $page - 1 ?>" class="admin-pagination__btn">
                            <i class="fas fa-chevron-left"></i> Назад
                        </a>
                        <?php endif; ?>
                        
                        <span class="admin-pagination__pages">
                            Страница <?= $page ?> из <?= $totalPages ?>
                        </span>
                        
                        <?php if ($page < $totalPages): ?>
                        <a href="?filter=<?= $filter ?>&page=<?= $page + 1 ?>" class="admin-pagination__btn">
                            Далее <i class="fas fa-chevron-right"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <style>
        .reviews-list {
            display: flex;
            flex-direction: column;
            gap: var(--space-4);
        }
        .review-item {
            padding: var(--space-5);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
        }
        .review-item__header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: var(--space-3);
        }
        .review-item__author {
            display: flex;
            flex-direction: column;
            gap: var(--space-1);
        }
        .review-item__rating {
            font-size: var(--text-lg);
        }
        .review-item__target {
            font-size: var(--text-sm);
            color: var(--color-accent);
            margin-bottom: var(--space-3);
        }
        .review-item__comment {
            padding: var(--space-3);
            background-color: var(--color-light-gray);
            border-radius: var(--radius-sm);
            margin-bottom: var(--space-3);
            line-height: 1.6;
        }
        .review-item__footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .review-item__actions {
            display: flex;
            gap: var(--space-2);
        }
    </style>
</body>
</html>
