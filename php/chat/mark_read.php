<?php
/**
 * Mark Messages as Read - Elsesser & Co.
 * Пометить сообщения как прочитанные
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/config/database.php';
require_once __DIR__ . '/../../includes/auth/check_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$pdo = getDBConnection();
$userId = getCurrentUserId();

$input = json_decode(file_get_contents('php://input'), true);
$senderId = intval($input['sender_id'] ?? 0);

if (empty($senderId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Sender ID required']);
    exit;
}

try {
    // Помечаем все сообщения от этого пользователя как прочитанные
    $stmt = $pdo->prepare("
        UPDATE messages 
        SET is_read = 1 
        WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
    ");
    $stmt->execute([$senderId, $userId]);
    
    $updatedCount = $stmt->rowCount();
    
    echo json_encode([
        'success' => true,
        'updated' => $updatedCount
    ]);
    
} catch (PDOException $e) {
    error_log("Mark read error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
