<?php
/**
 * Agent Calendar - Elsesser & Co.
 * Календарь просмотров агента
 */

require_once __DIR__ . '/../includes/config/database.php';
require_once __DIR__ . '/../includes/auth/check_auth.php';

requireAgent();

$pdo = getDBConnection();
$userId = getCurrentUserId();
$message = '';
$error = '';

// Обработка действий
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Ошибка безопасности';
    } else {
        $viewingId = intval($_POST['viewing_id'] ?? 0);
        
        switch ($_POST['action']) {
            case 'add':
                $propertyId = intval($_POST['property_id'] ?? 0);
                $clientName = trim($_POST['client_name'] ?? '');
                $clientPhone = trim($_POST['client_phone'] ?? '');
                $clientEmail = trim($_POST['client_email'] ?? '');
                $viewingDate = $_POST['viewing_date'] ?? '';
                $viewingTime = $_POST['viewing_time'] ?? '';
                $notes = trim($_POST['notes'] ?? '');
                
                if (empty($propertyId) || empty($clientName) || empty($viewingDate) || empty($viewingTime)) {
                    $error = 'Заполните обязательные поля';
                } else {
                    // Проверяем, что объект принадлежит агенту
                    $stmt = $pdo->prepare("SELECT id FROM properties WHERE id = ? AND agent_id = ?");
                    $stmt->execute([$propertyId, $userId]);
                    
                    if ($stmt->fetch()) {
                        $stmt = $pdo->prepare("
                            INSERT INTO viewings (property_id, agent_id, client_name, client_phone, client_email, viewing_date, viewing_time, notes)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$propertyId, $userId, $clientName, $clientPhone ?: null, $clientEmail ?: null, $viewingDate, $viewingTime, $notes ?: null]);
                        $message = 'Просмотр добавлен';
                    } else {
                        $error = 'Объект не найден';
                    }
                }
                break;
                
            case 'update_status':
                $newStatus = $_POST['status'] ?? '';
                if (in_array($newStatus, ['scheduled', 'completed', 'cancelled', 'no-show'])) {
                    $stmt = $pdo->prepare("UPDATE viewings SET status = ? WHERE id = ? AND agent_id = ?");
                    $stmt->execute([$newStatus, $viewingId, $userId]);
                    $message = 'Статус обновлён';
                }
                break;
                
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM viewings WHERE id = ? AND agent_id = ?");
                $stmt->execute([$viewingId, $userId]);
                $message = 'Просмотр удалён';
                break;
        }
    }
}

// Текущая неделя/месяц
$currentDate = $_GET['date'] ?? date('Y-m-d');
$weekStart = date('Y-m-d', strtotime('monday this week', strtotime($currentDate)));
$weekEnd = date('Y-m-d', strtotime('sunday this week', strtotime($currentDate)));

// Просмотры на неделю
$stmt = $pdo->prepare("
    SELECT v.*, p.title as property_title, p.location
    FROM viewings v
    JOIN properties p ON v.property_id = p.id
    WHERE v.agent_id = ? AND v.viewing_date BETWEEN ? AND ?
    ORDER BY v.viewing_date ASC, v.viewing_time ASC
");
$stmt->execute([$userId, $weekStart, $weekEnd]);
$viewings = $stmt->fetchAll();

// Группируем по дате
$viewingsByDate = [];
foreach ($viewings as $v) {
    $viewingsByDate[$v['viewing_date']][] = $v;
}

// Мои объекты для формы
$stmt = $pdo->prepare("SELECT id, title, title_ru FROM properties WHERE agent_id = ? AND status = 'available' ORDER BY title");
$stmt->execute([$userId]);
$myProperties = $stmt->fetchAll();

$pageTitle = 'Календарь просмотров';
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
                    <h1 class="admin-title">Календарь просмотров</h1>
                    <div class="admin-breadcrumb">
                        <a href="dashboard.php">Dashboard</a> / Календарь
                    </div>
                </div>
                <button class="btn btn--primary" onclick="document.getElementById('addViewingModal').classList.add('active')">
                    <i class="fas fa-plus"></i> Добавить просмотр
                </button>
            </div>
            
            <?php if ($message): ?>
            <div class="alert alert--success"><?= escape($message) ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
            <div class="alert alert--error"><?= escape($error) ?></div>
            <?php endif; ?>
            
            <!-- Week Navigation -->
            <div class="calendar-nav">
                <a href="?date=<?= date('Y-m-d', strtotime('-7 days', strtotime($currentDate))) ?>" class="btn btn--secondary btn--sm">
                    <i class="fas fa-chevron-left"></i> Пред. неделя
                </a>
                <span class="calendar-nav__title">
                    <?= date('d M', strtotime($weekStart)) ?> - <?= date('d M Y', strtotime($weekEnd)) ?>
                </span>
                <a href="?date=<?= date('Y-m-d', strtotime('+7 days', strtotime($currentDate))) ?>" class="btn btn--secondary btn--sm">
                    След. неделя <i class="fas fa-chevron-right"></i>
                </a>
            </div>
            
            <!-- Calendar Grid -->
            <div class="admin-card">
                <div class="calendar-week">
                    <?php 
                    for ($i = 0; $i < 7; $i++):
                        $dayDate = date('Y-m-d', strtotime("+$i days", strtotime($weekStart)));
                        $isToday = $dayDate === date('Y-m-d');
                        $dayViewings = $viewingsByDate[$dayDate] ?? [];
                    ?>
                    <div class="calendar-day <?= $isToday ? 'calendar-day--today' : '' ?>">
                        <div class="calendar-day__header">
                            <span class="calendar-day__name"><?= ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'][$i] ?></span>
                            <span class="calendar-day__date"><?= date('d', strtotime($dayDate)) ?></span>
                        </div>
                        <div class="calendar-day__content">
                            <?php if (empty($dayViewings)): ?>
                            <div class="calendar-day__empty">Нет просмотров</div>
                            <?php else: ?>
                            <?php foreach ($dayViewings as $viewing): ?>
                            <div class="calendar-event calendar-event--<?= $viewing['status'] ?>">
                                <div class="calendar-event__time"><?= date('H:i', strtotime($viewing['viewing_time'])) ?></div>
                                <div class="calendar-event__client"><?= escape($viewing['client_name']) ?></div>
                                <div class="calendar-event__property"><?= escape($viewing['property_title']) ?></div>
                                <div class="calendar-event__actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="viewing_id" value="<?= $viewing['id'] ?>">
                                        <select name="status" class="calendar-event__select" onchange="this.form.submit()">
                                            <option value="scheduled" <?= $viewing['status'] === 'scheduled' ? 'selected' : '' ?>>Запланирован</option>
                                            <option value="completed" <?= $viewing['status'] === 'completed' ? 'selected' : '' ?>>Проведён</option>
                                            <option value="cancelled" <?= $viewing['status'] === 'cancelled' ? 'selected' : '' ?>>Отменён</option>
                                            <option value="no-show" <?= $viewing['status'] === 'no-show' ? 'selected' : '' ?>>Не пришёл</option>
                                        </select>
                                    </form>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Add Viewing Modal -->
    <div class="modal-overlay" id="addViewingModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Добавить просмотр</h3>
                <button class="modal-close" onclick="document.getElementById('addViewingModal').classList.remove('active')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label class="form-label required">Объект</label>
                    <select name="property_id" class="form-select" required>
                        <option value="">Выберите объект</option>
                        <?php foreach ($myProperties as $prop): ?>
                        <option value="<?= $prop['id'] ?>"><?= escape($prop['title_ru'] ?? $prop['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label required">Имя клиента</label>
                    <input type="text" name="client_name" class="form-input" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Телефон</label>
                        <input type="tel" name="client_phone" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="client_email" class="form-input">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">Дата</label>
                        <input type="date" name="viewing_date" class="form-input" required min="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Время</label>
                        <input type="time" name="viewing_time" class="form-input" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Заметки</label>
                    <textarea name="notes" class="form-textarea" rows="2"></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn--primary">Добавить</button>
                    <button type="button" class="btn btn--secondary" onclick="document.getElementById('addViewingModal').classList.remove('active')">Отмена</button>
                </div>
            </form>
        </div>
    </div>
    
    <style>
        .calendar-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: var(--space-6);
        }
        .calendar-nav__title {
            font-size: var(--text-lg);
            font-weight: var(--font-semibold);
        }
        .calendar-week {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background-color: var(--color-border);
        }
        .calendar-day {
            background-color: var(--color-white);
            min-height: 200px;
        }
        .calendar-day--today {
            background-color: #f0fdf4;
        }
        .calendar-day__header {
            padding: var(--space-3);
            border-bottom: 1px solid var(--color-border);
            text-align: center;
        }
        .calendar-day__name {
            display: block;
            font-size: var(--text-xs);
            color: var(--color-text-light);
            text-transform: uppercase;
        }
        .calendar-day__date {
            font-size: var(--text-lg);
            font-weight: var(--font-semibold);
        }
        .calendar-day--today .calendar-day__date {
            color: var(--color-accent);
        }
        .calendar-day__content {
            padding: var(--space-2);
        }
        .calendar-day__empty {
            font-size: var(--text-xs);
            color: var(--color-text-light);
            text-align: center;
            padding: var(--space-4);
        }
        .calendar-event {
            background-color: #dbeafe;
            border-left: 3px solid #3b82f6;
            padding: var(--space-2);
            margin-bottom: var(--space-2);
            border-radius: var(--radius-sm);
            font-size: var(--text-xs);
        }
        .calendar-event--completed {
            background-color: #d1fae5;
            border-left-color: #10b981;
        }
        .calendar-event--cancelled, .calendar-event--no-show {
            background-color: #fee2e2;
            border-left-color: #ef4444;
            opacity: 0.7;
        }
        .calendar-event__time {
            font-weight: var(--font-semibold);
        }
        .calendar-event__client {
            font-weight: var(--font-medium);
        }
        .calendar-event__property {
            color: var(--color-text-light);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .calendar-event__select {
            font-size: 10px;
            padding: 2px 4px;
            margin-top: var(--space-1);
            width: 100%;
        }
        
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay.active {
            display: flex;
        }
        .modal-content {
            background: white;
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-header {
            padding: var(--space-4) var(--space-6);
            border-bottom: 1px solid var(--color-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h3 {
            margin: 0;
        }
        .modal-close {
            background: none;
            border: none;
            font-size: var(--text-xl);
            cursor: pointer;
            color: var(--color-text-light);
        }
        .modal-body {
            padding: var(--space-6);
        }
    </style>
</body>
</html>
