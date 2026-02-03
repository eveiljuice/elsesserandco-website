<?php
/**
 * Admin Inquiries Management - Elsesser & Co.
 * Управление заявками
 */

require_once __DIR__ . '/../includes/config/database.php';
require_once __DIR__ . '/../includes/auth/check_auth.php';

// Только для администраторов и агентов
requireAgent();

$pdo = getDBConnection();
$message = '';
$messageType = '';

// Обработка действий
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $inquiryId = (int)($_POST['inquiry_id'] ?? 0);
    
    try {
        switch ($action) {
            case 'change_status':
                $status = $_POST['status'] ?? 'new';
                $stmt = $pdo->prepare("UPDATE inquiries SET status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$status, $inquiryId]);
                $message = 'Статус заявки изменён';
                $messageType = 'success';
                break;
                
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM inquiries WHERE id = ?");
                $stmt->execute([$inquiryId]);
                $message = 'Заявка удалена';
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
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Построение SQL
$where = [];
$params = [];

if (!empty($status)) {
    $where[] = "i.status = ?";
    $params[] = $status;
}

if (!empty($search)) {
    $where[] = "(i.name LIKE ? OR i.email LIKE ? OR i.phone LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Подсчёт
$countSql = "SELECT COUNT(*) FROM inquiries i $whereClause";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalCount = $stmt->fetchColumn();
$totalPages = ceil($totalCount / $perPage);

// Получение заявок
$sql = "
    SELECT i.*, 
           p.title as property_title,
           u.first_name as user_name
    FROM inquiries i
    LEFT JOIN properties p ON i.property_id = p.id
    LEFT JOIN users u ON i.user_id = u.id
    $whereClause
    ORDER BY i.created_at DESC
    LIMIT $perPage OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$inquiries = $stmt->fetchAll();

$pageTitle = 'Управление заявками';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | Admin</title>
    
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
                <h1 class="admin-title"><?= $pageTitle ?></h1>
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
                            <input type="text" name="search" placeholder="Поиск по имени, email, телефону..." value="<?= escape($search) ?>">
                        </div>
                        
                        <select name="status" class="admin-select">
                            <option value="">Все статусы</option>
                            <option value="new" <?= $status === 'new' ? 'selected' : '' ?>>Новые</option>
                            <option value="contacted" <?= $status === 'contacted' ? 'selected' : '' ?>>Обработаны</option>
                            <option value="scheduled" <?= $status === 'scheduled' ? 'selected' : '' ?>>Запланированы</option>
                            <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Завершены</option>
                            <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Отменены</option>
                        </select>
                        
                        <button type="submit" class="btn btn--primary">
                            <i class="fas fa-filter"></i> Применить
                        </button>
                        <a href="inquiries.php" class="btn btn--secondary">
                            <i class="fas fa-times"></i> Сбросить
                        </a>
                    </form>
                </div>
            </div>
            
            <!-- Results info -->
            <div class="admin-results-info">
                Найдено заявок: <strong><?= number_format($totalCount) ?></strong>
            </div>
            
            <!-- Inquiries Table -->
            <div class="admin-card">
                <div class="admin-table-wrapper">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Дата</th>
                                <th>Контакт</th>
                                <th>Объект</th>
                                <th>Тип</th>
                                <th>Сообщение</th>
                                <th>Статус</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($inquiries)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">Заявки не найдены</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($inquiries as $inquiry): ?>
                            <tr>
                                <td><?= $inquiry['id'] ?></td>
                                <td>
                                    <div><?= date('d.m.Y', strtotime($inquiry['created_at'])) ?></div>
                                    <div class="text-muted text-sm"><?= date('H:i', strtotime($inquiry['created_at'])) ?></div>
                                </td>
                                <td>
                                    <div class="inquiry-contact">
                                        <div class="inquiry-contact__name"><?= escape($inquiry['name']) ?></div>
                                        <div class="inquiry-contact__email">
                                            <i class="fas fa-envelope"></i> 
                                            <a href="mailto:<?= escape($inquiry['email']) ?>"><?= escape($inquiry['email']) ?></a>
                                        </div>
                                        <?php if ($inquiry['phone']): ?>
                                        <div class="inquiry-contact__phone">
                                            <i class="fas fa-phone"></i> 
                                            <a href="tel:<?= escape($inquiry['phone']) ?>"><?= escape($inquiry['phone']) ?></a>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($inquiry['property_title']): ?>
                                    <a href="../property.php?id=<?= $inquiry['property_id'] ?>" target="_blank" class="inquiry-property-link">
                                        <i class="fas fa-home"></i> <?= escape($inquiry['property_title']) ?>
                                    </a>
                                    <?php else: ?>
                                    <span class="text-muted">Общий запрос</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge--secondary">
                                        <?= match($inquiry['inquiry_type']) {
                                            'general' => 'Общий',
                                            'viewing' => 'Просмотр',
                                            'offer' => 'Предложение',
                                            'valuation' => 'Оценка',
                                            default => $inquiry['inquiry_type']
                                        } ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($inquiry['message']): ?>
                                    <div class="inquiry-message-preview" title="<?= escape($inquiry['message']) ?>">
                                        <?= escape(mb_substr($inquiry['message'], 0, 50)) ?><?= mb_strlen($inquiry['message']) > 50 ? '...' : '' ?>
                                    </div>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" class="inquiry-status-form">
                                        <input type="hidden" name="action" value="change_status">
                                        <input type="hidden" name="inquiry_id" value="<?= $inquiry['id'] ?>">
                                        <select name="status" class="admin-select admin-select--sm" onchange="this.form.submit()">
                                            <option value="new" <?= $inquiry['status'] === 'new' ? 'selected' : '' ?>>Новая</option>
                                            <option value="contacted" <?= $inquiry['status'] === 'contacted' ? 'selected' : '' ?>>Обработана</option>
                                            <option value="scheduled" <?= $inquiry['status'] === 'scheduled' ? 'selected' : '' ?>>Запланирована</option>
                                            <option value="completed" <?= $inquiry['status'] === 'completed' ? 'selected' : '' ?>>Завершена</option>
                                            <option value="cancelled" <?= $inquiry['status'] === 'cancelled' ? 'selected' : '' ?>>Отменена</option>
                                        </select>
                                    </form>
                                </td>
                                <td>
                                    <div class="admin-table__actions">
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Удалить эту заявку?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="inquiry_id" value="<?= $inquiry['id'] ?>">
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
</body>
</html>










