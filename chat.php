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
$stmt = $pdo->prepare("SELECT id, first_name, last_name, role, avatar, email FROM users WHERE id = ?");
$stmt->execute([$userId]);
$currentUser = $stmt->fetch();

// Активный собеседник (унифицированный параметр: ?user= или ?agent_id= для обратной совместимости)
$activeUserId = intval($_GET['user'] ?? $_GET['agent_id'] ?? 0);
$activePropertyId = intval($_GET['property'] ?? $_GET['property_id'] ?? $_GET['building_id'] ?? 0);

// Список диалогов: один проход, без N+1.
// Сначала вытаскиваем (other_user_id, MAX(created_at)) одним запросом по индексу,
// затем подтягиваем последнее сообщение и unread одним JOIN-ом.
$stmt = $pdo->prepare("
    SELECT
        CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END AS other_user_id,
        MAX(m.created_at) AS last_message_time,
        COUNT(*) AS total_messages
    FROM messages m
    WHERE m.sender_id = ? OR m.receiver_id = ?
    GROUP BY other_user_id
    ORDER BY last_message_time DESC
");
$stmt->execute([$userId, $userId, $userId]);
$dialogs = $stmt->fetchAll();

$conversations = [];
if (!empty($dialogs)) {
    $otherIds = array_column($dialogs, 'other_user_id');
    $placeholders = implode(',', array_fill(0, count($otherIds), '?'));
    // SQL использует 6 фиксированных ? + 2N для двух IN(...) → всего 6 + 2N параметров.
    // Порядок: last_msg subquery (? x3) → last_msg join (? x2) → unread subquery (? x1 + N для IN) → WHERE (? xN).
    $bindParams = [
        $userId, $userId, $userId,   // last_msg subquery
        $userId, $userId,            // last_msg join (sender=?, receiver=?)
        $userId,                     // unread subquery: receiver_id = ?
    ];
    $bindParams = array_merge($bindParams, $otherIds, $otherIds);

    // Одним запросом: профили собеседников + последние сообщения + unread count
    $stmt = $pdo->prepare("
        SELECT
            u.id AS other_user_id,
            u.first_name, u.last_name, u.role, u.avatar,
            last_msg.message AS last_message,
            last_msg.created_at AS last_message_time,
            COALESCE(unread.cnt, 0) AS unread_count
        FROM users u
        LEFT JOIN (
            SELECT m1.message, m1.created_at, m1.receiver_id, m1.sender_id
            FROM messages m1
            INNER JOIN (
                SELECT
                    CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END AS other_id,
                    MAX(id) AS max_id
                FROM messages
                WHERE sender_id = ? OR receiver_id = ?
                GROUP BY other_id
            ) m2 ON m2.max_id = m1.id
        ) last_msg ON (
            (last_msg.sender_id = ? AND last_msg.receiver_id = ?)
        )
        LEFT JOIN (
            SELECT sender_id, COUNT(*) AS cnt
            FROM messages
            WHERE receiver_id = ? AND is_read = 0
              AND sender_id IN ($placeholders)
            GROUP BY sender_id
        ) unread ON unread.sender_id = u.id
        WHERE u.id IN ($placeholders)
        ORDER BY last_msg.created_at DESC
    ");
    $stmt->execute($bindParams);
    $conversations = $stmt->fetchAll();
}

// Если есть активный собеседник, получаем его данные (включая avatar)
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

/**
 * Рендер аватара (img если есть avatar, иначе градиентный круг с буквой)
 */
function renderAvatar(?string $avatarUrl, string $firstName, string $extraClass = ''): string {
    $letter = mb_strtoupper(mb_substr($firstName, 0, 1, 'UTF-8'), 'UTF-8');
    $alt = htmlspecialchars($letter, ENT_QUOTES, 'UTF-8');
    if (!empty($avatarUrl)) {
        $safeUrl = htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8');
        return '<img class="chat-avatar-img ' . $extraClass . '" src="' . $safeUrl . '" alt="' . $alt . '" data-letter="' . $alt . '" loading="lazy" onerror="this.style.display=\'none\';this.nextElementSibling&&(this.nextElementSibling.style.display=\'flex\');">'
             . '<span class="chat-avatar-fallback ' . $extraClass . '" data-letter="' . $alt . '">' . $alt . '</span>';
    }
    return '<span class="chat-avatar-fallback ' . $extraClass . '" data-letter="' . $alt . '">' . $alt . '</span>';
}

// Получаем количество непрочитанных
$stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
$stmt->execute([$userId]);
$totalUnread = $stmt->fetchColumn();

/**
 * Закреплённый объект диалога:
 *  - приоритет у ?property= в URL (юзер только что пришёл со страницы объекта),
 *  - иначе — последний объект, который обсуждался с этим собеседником
 *    (MAX(messages.created_at) WHERE property_id IS NOT NULL).
 */
$pinnedProperty = null;
if ($activeUserId) {
    if ($activePropertyId) {
        $stmt = $pdo->prepare("
            SELECT p.id, p.title, p.title_ru, p.price, p.category,
                   pi.image_url AS primary_image,
                   p.bedrooms, p.area_total, p.area_sqft
            FROM properties p
            LEFT JOIN property_images pi ON p.id = pi.property_id AND pi.is_primary = 1
            WHERE p.id = ?
        ");
        $stmt->execute([$activePropertyId]);
        $pinnedProperty = $stmt->fetch() ?: null;
    }
    if (!$pinnedProperty) {
        $stmt = $pdo->prepare("
            SELECT p.id, p.title, p.title_ru, p.price, p.category,
                   pi.image_url AS primary_image,
                   p.bedrooms, p.area_total, p.area_sqft,
                   MAX(m.created_at) AS last_msg_at
            FROM messages m
            JOIN properties p ON p.id = m.property_id
            LEFT JOIN property_images pi ON p.id = pi.property_id AND pi.is_primary = 1
            WHERE ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
              AND m.property_id IS NOT NULL
            GROUP BY p.id, pi.image_url
            ORDER BY last_msg_at DESC
            LIMIT 1
        ");
        $stmt->execute([$userId, $activeUserId, $activeUserId, $userId]);
        $pinnedProperty = $stmt->fetch() ?: null;
    }
}

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
                        <li><a href="properties.php?category=sale" class="nav__link">Купить</a></li>
                        <li><a href="properties.php?category=rent" class="nav__link">Аренда</a></li>
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

                <div class="chat-search">
                    <input type="text" placeholder="Поиск по диалогам" aria-label="Поиск">
                </div>

                <div class="chat-conversations" id="conversationsList">
                    <?php if (empty($conversations)): ?>
                    <div class="chat-empty">
                        <i class="far fa-comments"></i>
                        <p>Нет диалогов</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($conversations as $conv): ?>
                    <a href="?user=<?= $conv['other_user_id'] ?>"
                       class="chat-conversation <?= $conv['other_user_id'] == $activeUserId ? 'chat-conversation--active' : '' ?>"
                       data-user-id="<?= $conv['other_user_id'] ?>">
                        <div class="chat-conversation__avatar">
                            <?= renderAvatar($conv['avatar'] ?? null, $conv['first_name']) ?>
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
                                <?= escape(mb_substr($conv['last_message'] ?? '', 0, 40)) ?>
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
                    <button class="chat-header__back" id="chatBack" aria-label="Назад к диалогам">
                        <i class="fas fa-arrow-left"></i>
                    </button>
                    <div class="chat-header__user">
                        <div class="chat-header__avatar">
                            <?= renderAvatar($activeUser['avatar'] ?? null, $activeUser['first_name']) ?>
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
                    <div class="chat-header__status" title="В сети"></div>
                </div>

                <?php if ($pinnedProperty): ?>
                <a href="/property.php?id=<?= (int)$pinnedProperty['id'] ?>"
                   class="chat-pinned"
                   target="_blank"
                   data-pinned-property-id="<?= (int)$pinnedProperty['id'] ?>">
                    <div class="chat-pinned__icon">
                        <i class="fas fa-home"></i>
                    </div>
                    <div class="chat-pinned__image">
                        <?php if (!empty($pinnedProperty['primary_image'])): ?>
                        <img src="<?= imgSrc($pinnedProperty['primary_image']) ?>" alt="" loading="lazy">
                        <?php else: ?>
                        <div class="chat-pinned__image-placeholder"><i class="fas fa-home"></i></div>
                        <?php endif; ?>
                    </div>
                    <div class="chat-pinned__body">
                        <div class="chat-pinned__label">Обсуждение объекта</div>
                        <div class="chat-pinned__title">
                            <?= escape($pinnedProperty['title_ru'] ?: $pinnedProperty['title']) ?>
                        </div>
                        <div class="chat-pinned__meta">
                            <?php if ((int)$pinnedProperty['bedrooms'] > 0): ?>
                            <span><i class="fas fa-door-open"></i> <?= (int)$pinnedProperty['bedrooms'] ?></span>
                            <?php endif; ?>
                            <span><i class="fas fa-ruler-combined"></i> <?= number_format((float)($pinnedProperty['area_total'] ?? $pinnedProperty['area_sqft'] ?? 0), 1) ?> м²</span>
                            <span class="chat-pinned__price"><?= formatPrice($pinnedProperty['price']) ?><?= $pinnedProperty['category'] === 'rent' ? '/мес' : '' ?></span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right chat-pinned__chevron"></i>
                </a>
                <?php endif; ?>
                
                <div class="chat-messages" id="chatMessages" data-receiver-id="<?= $activeUserId ?>">
                    <div class="chat-messages__empty chat-welcome" data-welcome="1">
                        <div class="empty-icon">
                            <?= renderAvatar($activeUser['avatar'] ?? null, $activeUser['first_name']) ?>
                        </div>
                        <h3>Начните диалог с <?= escape($activeUser['first_name']) ?></h3>
                        <p>Это начало вашей переписки. Напишите первое сообщение — агенту или покупателю оно придёт в реальном времени.</p>
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
                    <?php if ($activePropertyId && $activeUserId): ?>
                    <button type="button" class="chat-form__viewing-btn" id="scheduleViewingBtn" title="Записаться на просмотр">
                        <i class="fas fa-calendar-plus"></i>
                    </button>
                    <?php endif; ?>
                    <button type="submit" class="chat-form__submit" id="sendButton">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </form>
                <?php else: ?>
                <div class="chat-empty-state">
                    <div class="empty-icon"><i class="far fa-comments"></i></div>
                    <h3>Выберите диалог</h3>
                    <p>Откройте существующий диалог из списка слева или начните новый, нажав «Чат с агентом» на странице объекта.</p>
                    <span class="empty-hint"><i class="fas fa-lightbulb"></i> Сообщения синхронизируются в реальном времени</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        const CURRENT_USER_ID = <?= $userId ?>;
        const RECEIVER_ID = <?= $activeUserId ?: 'null' ?>;
        const CURRENT_USER_AVATAR = <?= json_encode($currentUser['avatar'] ?? null) ?>;
        const CURRENT_USER_NAME = <?= json_encode(trim(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? ''))) ?>;
    </script>
    <script src="js/navigation.js"></script>
    <script src="js/chat.js"></script>
    <?php include __DIR__ . '/includes/cookie-banner.php'; ?>
</body>
</html>
