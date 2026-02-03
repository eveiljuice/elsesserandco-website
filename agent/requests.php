<?php
/**
 * Agent Requests - Elsesser & Co.
 * Управление заявками агента
 */

require_once __DIR__ . '/../includes/config/database.php';
require_once __DIR__ . '/../includes/auth/check_auth.php';

requireAgent();

$pdo = getDBConnection();
$userId = getCurrentUserId();
$message = '';

// Обработка обновления статуса
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inquiry_id'])) {
    if (validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $inquiryId = intval($_POST['inquiry_id']);
        $newStatus = $_POST['status'] ?? '';
        
        if (in_array($newStatus, ['new', 'contacted', 'scheduled', 'completed', 'cancelled'])) {
            // Проверяем, что заявка относится к объекту этого агента
            $stmt = $pdo->prepare("
                SELECT i.id FROM inquiries i
                JOIN properties p ON i.property_id = p.id
                WHERE i.id = ? AND p.agent_id = ?
            ");
            $stmt->execute([$inquiryId, $userId]);
            
            if ($stmt->fetch()) {
                $stmt = $pdo->prepare("UPDATE inquiries SET status = ? WHERE id = ?");
                $stmt->execute([$newStatus, $inquiryId]);
                $message = 'Статус заявки обновлён';
            }
        }
    }
}

// Фильтры
$statusFilter = $_GET['status'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Построение запроса
$where = "p.agent_id = ?";
$params = [$userId];

if (!empty($statusFilter) && in_array($statusFilter, ['new', 'contacted', 'scheduled', 'completed', 'cancelled'])) {
    $where .= " AND i.status = ?";
    $params[] = $statusFilter;
}

// Получаем общее количество
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM inquiries i
    JOIN properties p ON i.property_id = p.id
    WHERE $where
");
$stmt->execute($params);
$totalCount = $stmt->fetchColumn();
$totalPages = ceil($totalCount / $perPage);

// Получаем заявки
$params[] = $perPage;
$params[] = $offset;

$stmt = $pdo->prepare("
    SELECT i.*, p.title as property_title, p.id as property_id, pi.image_url as property_image
    FROM inquiries i
    JOIN properties p ON i.property_id = p.id
    LEFT JOIN property_images pi ON p.id = pi.property_id AND pi.is_primary = 1
    WHERE $where
    ORDER BY i.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute($params);
$inquiries = $stmt->fetchAll();

// Статистика
$stmt = $pdo->prepare("
    SELECT i.status, COUNT(*) as count FROM inquiries i
    JOIN properties p ON i.property_id = p.id
    WHERE p.agent_id = ?
    GROUP BY i.status
");
$stmt->execute([$userId]);
$statusStats = [];
while ($row = $stmt->fetch()) {
    $statusStats[$row['status']] = $row['count'];
}

$pageTitle = 'Заявки';
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
                    <h1 class="admin-title">Заявки на мои объекты</h1>
                    <div class="admin-breadcrumb">
                        <a href="dashboard.php">Dashboard</a> / Заявки
                    </div>
                </div>
            </div>
            
            <?php if ($message): ?>
            <div class="alert alert--success"><?= escape($message) ?></div>
            <?php endif; ?>
            
            <!-- Stats -->
            <div class="stats-grid" style="margin-bottom: var(--space-6);">
                <div class="stat-card stat-card--success" style="padding: var(--space-4);">
                    <div class="stat-card__content">
                        <div class="stat-card__value"><?= $statusStats['new'] ?? 0 ?></div>
                        <div class="stat-card__label">Новые</div>
                    </div>
                </div>
                <div class="stat-card stat-card--primary" style="padding: var(--space-4);">
                    <div class="stat-card__content">
                        <div class="stat-card__value"><?= $statusStats['contacted'] ?? 0 ?></div>
                        <div class="stat-card__label">Обработано</div>
                    </div>
                </div>
                <div class="stat-card stat-card--warning" style="padding: var(--space-4);">
                    <div class="stat-card__content">
                        <div class="stat-card__value"><?= $statusStats['scheduled'] ?? 0 ?></div>
                        <div class="stat-card__label">Запланировано</div>
                    </div>
                </div>
                <div class="stat-card stat-card--info" style="padding: var(--space-4);">
                    <div class="stat-card__content">
                        <div class="stat-card__value"><?= $statusStats['completed'] ?? 0 ?></div>
                        <div class="stat-card__label">Завершено</div>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="admin-card">
                <div class="admin-card__header">
                    <div class="status-filters">
                        <a href="?" class="status-filter <?= empty($statusFilter) ? 'active' : '' ?>">Все (<?= $totalCount ?>)</a>
                        <a href="?status=new" class="status-filter <?= $statusFilter === 'new' ? 'active' : '' ?>">Новые</a>
                        <a href="?status=contacted" class="status-filter <?= $statusFilter === 'contacted' ? 'active' : '' ?>">Обработано</a>
                        <a href="?status=scheduled" class="status-filter <?= $statusFilter === 'scheduled' ? 'active' : '' ?>">Запланировано</a>
                        <a href="?status=completed" class="status-filter <?= $statusFilter === 'completed' ? 'active' : '' ?>">Завершено</a>
                    </div>
                </div>
                
                <div class="admin-card__body">
                    <?php if (empty($inquiries)): ?>
                    <div class="empty-state">
                        <i class="fas fa-envelope"></i>
                        <p>Нет заявок</p>
                    </div>
                    <?php else: ?>
                    <div class="admin-table-wrapper">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Клиент</th>
                                    <th>Объект</th>
                                    <th>Тип</th>
                                    <th>Сообщение</th>
                                    <th>Дата</th>
                                    <th>Статус</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($inquiries as $inquiry): ?>
                                <tr>
                                    <td>
                                        <div class="inquiry-contact">
                                            <div class="inquiry-contact__name"><?= escape($inquiry['name']) ?></div>
                                            <div class="inquiry-contact__email">
                                                <a href="mailto:<?= escape($inquiry['email']) ?>"><?= escape($inquiry['email']) ?></a>
                                            </div>
                                            <?php if ($inquiry['phone']): ?>
                                            <div class="inquiry-contact__phone">
                                                <a href="tel:<?= escape($inquiry['phone']) ?>"><?= escape($inquiry['phone']) ?></a>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="../property.php?id=<?= $inquiry['property_id'] ?>" target="_blank" class="inquiry-property-link">
                                            <?= escape($inquiry['property_title']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="badge badge--secondary">
                                            <?= match($inquiry['inquiry_type']) {
                                                'viewing' => 'Просмотр',
                                                'offer' => 'Предложение',
                                                'valuation' => 'Оценка',
                                                default => 'Общий'
                                            } ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="inquiry-message-preview">
                                            <?= escape(mb_substr($inquiry['message'] ?? '', 0, 50)) ?>...
                                        </div>
                                    </td>
                                    <td>
                                        <?= date('d.m.Y H:i', strtotime($inquiry['created_at'])) ?>
                                    </td>
                                    <td>
                                        <form method="POST" class="status-form">
                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                            <input type="hidden" name="inquiry_id" value="<?= $inquiry['id'] ?>">
                                            <select name="status" class="admin-select admin-select--sm" onchange="this.form.submit()">
                                                <option value="new" <?= $inquiry['status'] === 'new' ? 'selected' : '' ?>>Новая</option>
                                                <option value="contacted" <?= $inquiry['status'] === 'contacted' ? 'selected' : '' ?>>Связались</option>
                                                <option value="scheduled" <?= $inquiry['status'] === 'scheduled' ? 'selected' : '' ?>>Запланировано</option>
                                                <option value="completed" <?= $inquiry['status'] === 'completed' ? 'selected' : '' ?>>Завершено</option>
                                                <option value="cancelled" <?= $inquiry['status'] === 'cancelled' ? 'selected' : '' ?>>Отменено</option>
                                            </select>
                                        </form>
                                    </td>
                                    <td>
                                        <div class="admin-table__actions">
                                            <a href="/chat.php?user=<?= $inquiry['user_id'] ?? '' ?>&property=<?= $inquiry['property_id'] ?>" 
                                               class="btn btn--sm btn--secondary" title="Написать">
                                                <i class="fas fa-comment"></i>
                                            </a>
                                            <a href="mailto:<?= escape($inquiry['email']) ?>" 
                                               class="btn btn--sm btn--secondary" title="Email">
                                                <i class="fas fa-envelope"></i>
                                            </a>
                                            <?php if ($inquiry['phone']): ?>
                                            <a href="tel:<?= escape($inquiry['phone']) ?>" 
                                               class="btn btn--sm btn--secondary" title="Позвонить">
                                                <i class="fas fa-phone"></i>
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if ($totalPages > 1): ?>
                    <div class="admin-pagination">
                        <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>&status=<?= $statusFilter ?>" class="admin-pagination__btn">
                            <i class="fas fa-chevron-left"></i> Назад
                        </a>
                        <?php endif; ?>
                        
                        <span class="admin-pagination__pages">
                            Страница <?= $page ?> из <?= $totalPages ?>
                        </span>
                        
                        <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>&status=<?= $statusFilter ?>" class="admin-pagination__btn">
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
</body>
</html>
