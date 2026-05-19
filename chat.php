<?php
/**
 * Chat Page - Elsesser & Co.
 * Внутренний чат между пользователями и агентами
 */

require_once __DIR__ . '/includes/config/database.php';
require_once __DIR__ . '/includes/auth/check_auth.php';

// Сохраняем текущий URL с параметрами для редиректа после логина
$currentUrl = $_SERVER['REQUEST_URI'];
requireLogin($currentUrl);

$pdo = getDBConnection();
$userId = getCurrentUserId();
$userRole = getCurrentUserRole();

// Получаем данные текущего пользователя
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$currentUser = $stmt->fetch();

// Активный собеседник
$activeUserId = intval($_GET['user'] ?? 0);
$activePropertyId = intval($_GET['property'] ?? 0);

// Получаем список диалогов (уникальные собеседники)
$stmt = $pdo->prepare("
    SELECT 
        CASE 
            WHEN m.sender_id = ? THEN m.receiver_id 
            ELSE m.sender_id 
        END as other_user_id,
        u.first_name, u.last_name, u.role, u.avatar,
        MAX(m.created_at) as last_message_time,
        (SELECT message FROM messages WHERE 
            (sender_id = ? AND receiver_id = other_user_id) OR 
            (sender_id = other_user_id AND receiver_id = ?)
            ORDER BY created_at DESC LIMIT 1
        ) as last_message,
        (SELECT COUNT(*) FROM messages WHERE 
            sender_id = other_user_id AND receiver_id = ? AND is_read = 0
        ) as unread_count
    FROM messages m
    JOIN users u ON u.id = CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END
    WHERE m.sender_id = ? OR m.receiver_id = ?
    GROUP BY other_user_id, u.first_name, u.last_name, u.role, u.avatar
    ORDER BY last_message_time DESC
");
$stmt->execute([$userId, $userId, $userId, $userId, $userId, $userId, $userId]);
$conversations = $stmt->fetchAll();

// Если есть активный собеседник, получаем его данные
$activeUser = null;
if ($activeUserId) {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, role, avatar FROM users WHERE id = ?");
    $stmt->execute([$activeUserId]);
    $activeUser = $stmt->fetch();
}

// Если нет активного, берём первого из списка
if (!$activeUser && !empty($conversations)) {
    $activeUserId = $conversations[0]['other_user_id'];
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, role, avatar FROM users WHERE id = ?");
    $stmt->execute([$activeUserId]);
    $activeUser = $stmt->fetch();
}

// Получаем количество непрочитанных
$stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
$stmt->execute([$userId]);
$totalUnread = $stmt->fetchColumn();

$pageTitle = 'Сообщения';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= escape(generateCSRFToken()) ?>">
    <title><?= $pageTitle ?> | Elsesser & Co.</title>

    <link rel="icon" type="image/png" href="images/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <link rel="stylesheet" href="css/reset.css">
    <link rel="stylesheet" href="css/variables.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/chat.css">
    <link rel="stylesheet" href="css/responsive.css">
</head>
<body>
    <!-- Header -->
    <header class="header header--solid" id="header">
        <div class="container">
            <div class="header__inner">
                <a href="index.php" class="header__logo">
                    <span class="header__logo-text">Elsesser & Co.</span>
                </a>
                
                <nav class="nav">
                    <ul class="nav__list">
                        <li><a href="properties.php?type=sale" class="nav__link">Купить</a></li>
                        <li><a href="properties.php?type=rent" class="nav__link">Аренда</a></li>
                        <li><a href="favorites.php" class="nav__link"><i class="fas fa-heart"></i> Избранное</a></li>
                    </ul>
                    <a href="dashboard.php" class="btn btn--primary">
                        <i class="fas fa-user"></i> <?= escape($currentUser['first_name']) ?>
                    </a>
                </nav>
                
                <button class="hamburger" id="hamburger" aria-label="Открыть меню">
                    <span class="hamburger__line"></span>
                    <span class="hamburger__line"></span>
                    <span class="hamburger__line"></span>
                </button>
            </div>
        </div>
    </header>

    <?php include __DIR__ . '/includes/mobile-menu.php'; ?>

    <!-- Chat Container -->
    <main class="chat-page">
        <div class="chat-container">
            <!-- Conversations List -->
            <aside class="chat-sidebar" id="chatSidebar">
                <div class="chat-sidebar__header">
                    <h2>Сообщения</h2>
                    <?php if ($totalUnread > 0): ?>
                    <span class="chat-badge"><?= $totalUnread ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="chat-conversations" id="conversationsList">
                    <?php if (empty($conversations)): ?>
                    <div class="chat-empty">
                        <i class="fas fa-comments"></i>
                        <p>Нет диалогов</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($conversations as $conv): ?>
                    <a href="?user=<?= $conv['other_user_id'] ?>" 
                       class="chat-conversation <?= $conv['other_user_id'] == $activeUserId ? 'chat-conversation--active' : '' ?>"
                       data-user-id="<?= $conv['other_user_id'] ?>">
                        <div class="chat-conversation__avatar">
                            <?= strtoupper(substr($conv['first_name'], 0, 1)) ?>
                            <?php if ($conv['role'] === 'agent'): ?>
                            <span class="chat-conversation__badge-agent" title="Агент"><i class="fas fa-check"></i></span>
                            <?php endif; ?>
                        </div>
                        <div class="chat-conversation__content">
                            <div class="chat-conversation__header">
                                <span class="chat-conversation__name">
                                    <?= escape($conv['first_name'] . ' ' . $conv['last_name']) ?>
                                </span>
                                <span class="chat-conversation__time">
                                    <?= date('H:i', strtotime($conv['last_message_time'])) ?>
                                </span>
                            </div>
                            <div class="chat-conversation__preview">
                                <?= escape(mb_substr($conv['last_message'] ?? '', 0, 40)) ?>...
                            </div>
                        </div>
                        <?php if ($conv['unread_count'] > 0): ?>
                        <span class="chat-conversation__unread"><?= $conv['unread_count'] ?></span>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </aside>
            
            <!-- Chat Window -->
            <div class="chat-main">
                <?php if ($activeUser): ?>
                <div class="chat-header">
                    <button class="chat-header__back" id="chatBack">
                        <i class="fas fa-arrow-left"></i>
                    </button>
                    <div class="chat-header__user">
                        <div class="chat-header__avatar">
                            <?= strtoupper(substr($activeUser['first_name'], 0, 1)) ?>
                        </div>
                        <div class="chat-header__info">
                            <span class="chat-header__name">
                                <?= escape($activeUser['first_name'] . ' ' . $activeUser['last_name']) ?>
                            </span>
                            <span class="chat-header__role">
                                <?= $activeUser['role'] === 'agent' ? 'Агент' : ($activeUser['role'] === 'admin' ? 'Администратор' : 'Пользователь') ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="chat-messages" id="chatMessages" data-receiver-id="<?= $activeUserId ?>">
                    <div class="chat-messages__loader">
                        <i class="fas fa-spinner fa-spin"></i>
                    </div>
                </div>
                
                <form class="chat-form" id="chatForm">
                    <input type="hidden" name="receiver_id" value="<?= $activeUserId ?>">
                    <?php if ($activePropertyId): ?>
                    <input type="hidden" name="property_id" value="<?= $activePropertyId ?>">
                    <?php endif; ?>
                    <div class="chat-form__input-wrapper">
                        <textarea class="chat-form__input" 
                                  name="message" 
                                  placeholder="Введите сообщение..." 
                                  rows="1"
                                  id="messageInput"></textarea>
                    </div>
                    <button type="submit" class="chat-form__submit" id="sendButton">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </form>
                <?php else: ?>
                <div class="chat-empty-state">
                    <i class="fas fa-comments"></i>
                    <h3>Выберите диалог</h3>
                    <p>Выберите диалог из списка слева или начните новый</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        const CURRENT_USER_ID = <?= $userId ?>;
        const RECEIVER_ID = <?= $activeUserId ?: 'null' ?>;
    </script>
    <script src="js/navigation.js"></script>
    <script src="js/chat.js"></script>
</body>
</html>
