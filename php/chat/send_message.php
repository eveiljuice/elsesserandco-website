<?php
/**
 * Send Message API - Elsesser & Co.
 * Отправка сообщения в чат
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/config/database.php';
require_once __DIR__ . '/../../includes/auth/check_auth.php';
require_once __DIR__ . '/../../includes/auth/csrf_json.php';
require_once __DIR__ . '/../../includes/push/Notifier.php';

// Только POST запросы
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Требуем авторизацию
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

requireJsonCsrf();

$pdo = getDBConnection();
$senderId = getCurrentUserId();

// Получаем данные
$input = json_decode(file_get_contents('php://input'), true);
$receiverId = intval($input['receiver_id'] ?? 0);
$message = trim($input['message'] ?? '');
$propertyId = !empty($input['property_id']) ? intval($input['property_id']) : null;

// Валидация
if (empty($receiverId) || empty($message)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

// Проверяем, что получатель существует
$stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND is_active = 1");
$stmt->execute([$receiverId]);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Receiver not found']);
    exit;
}

// Ограничение частоты отправки (10 сообщений в минуту)
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM messages 
    WHERE sender_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
");
$stmt->execute([$senderId]);
$recentCount = $stmt->fetchColumn();

if ($recentCount >= 10) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Too many messages. Please wait.']);
    exit;
}

// Ограничение длины (XSS-экранирование делается на выводе через escape())
if (mb_strlen($message) > 2000) {
    $message = mb_substr($message, 0, 2000);
}

try {
    // Сохраняем сообщение
    $stmt = $pdo->prepare("
        INSERT INTO messages (sender_id, receiver_id, property_id, message, is_read, created_at)
        VALUES (?, ?, ?, ?, 0, NOW())
    ");
    $stmt->execute([$senderId, $receiverId, $propertyId, $message]);
    
    $messageId = $pdo->lastInsertId();
    
    // Получаем созданное сообщение
    $stmt = $pdo->prepare("
        SELECT m.*, u.first_name, u.last_name 
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.id = ?
    ");
    $stmt->execute([$messageId]);
    $newMessage = $stmt->fetch();
    
    // Web Push для получателя
    $senderName = trim(($newMessage['first_name'] ?? '') . ' ' . ($newMessage['last_name'] ?? ''));
    $preview = mb_substr($message, 0, 120) . (mb_strlen($message) > 120 ? '…' : '');
    Notifier::push($receiverId, [
        'title' => $senderName !== '' ? $senderName : 'Новое сообщение',
        'body'  => $preview,
        'url'   => '/chat.php?user=' . $senderId,
        'tag'   => 'chat-' . $senderId,
        'renotify' => true,
    ]);

    // Email-уведомление (если есть send_notification.php)
    $emailFile = __DIR__ . '/../email/send_notification.php';
    if (file_exists($emailFile)) {
        require_once $emailFile;

        $stmt = $pdo->prepare("SELECT email, first_name FROM users WHERE id = ?");
        $stmt->execute([$receiverId]);
        $receiver = $stmt->fetch();

        if ($receiver && function_exists('sendMessageNotification')) {
            sendMessageNotification(
                $receiver['email'],
                $receiver['first_name'],
                $senderName,
                $message
            );
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => [
            'id' => $newMessage['id'],
            'sender_id' => $newMessage['sender_id'],
            'receiver_id' => $newMessage['receiver_id'],
            'message' => $newMessage['message'],
            'created_at' => $newMessage['created_at'],
            'sender_name' => $newMessage['first_name'] . ' ' . $newMessage['last_name']
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Chat error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
