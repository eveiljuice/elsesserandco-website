<?php
/**
 * Get Messages API - Elsesser & Co.
 * Получение сообщений диалога
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/config/database.php';
require_once __DIR__ . '/../../includes/auth/check_auth.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$pdo = getDBConnection();
$userId = getCurrentUserId();

$otherUserId = intval($_GET['user_id'] ?? 0);
$lastMessageId = intval($_GET['last_id'] ?? 0);
$limit = min(50, max(10, intval($_GET['limit'] ?? 50)));

if (empty($otherUserId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'User ID required']);
    exit;
}

try {
    // Получаем сообщения между двумя пользователями
    $whereLastId = $lastMessageId > 0 ? "AND m.id > ?" : "";
    
    $sql = "
        SELECT m.*,
               u.first_name as sender_first_name,
               u.last_name as sender_last_name,
               u.avatar as sender_avatar,
               p.title as property_title
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        LEFT JOIN properties p ON m.property_id = p.id
        WHERE ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
        $whereLastId
        ORDER BY m.created_at ASC
        LIMIT ?
    ";
    
    $params = [$userId, $otherUserId, $otherUserId, $userId];
    if ($lastMessageId > 0) {
        $params[] = $lastMessageId;
    }
    $params[] = $limit;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $messages = $stmt->fetchAll();
    
    // Форматируем сообщения
    $formattedMessages = [];
    foreach ($messages as $msg) {
        $formattedMessages[] = [
            'id' => $msg['id'],
            'sender_id' => $msg['sender_id'],
            'receiver_id' => $msg['receiver_id'],
            'message' => $msg['message'],
            'property_id' => $msg['property_id'],
            'property_title' => $msg['property_title'],
            'is_read' => (bool)$msg['is_read'],
            'created_at' => $msg['created_at'],
            'sender_name' => $msg['sender_first_name'] . ' ' . $msg['sender_last_name'],
            'sender_avatar' => $msg['sender_avatar'] ?? null,
            'is_mine' => $msg['sender_id'] == $userId
        ];
    }
    
    // Помечаем сообщения как прочитанные
    if (!empty($messages)) {
        $stmt = $pdo->prepare("
            UPDATE messages 
            SET is_read = 1 
            WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
        ");
        $stmt->execute([$otherUserId, $userId]);
    }
    
    echo json_encode([
        'success' => true,
        'messages' => $formattedMessages,
        'count' => count($formattedMessages)
    ]);
    
} catch (PDOException $e) {
    error_log("Get messages error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
