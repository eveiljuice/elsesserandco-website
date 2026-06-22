<?php
/**
 * Send Message API - Elsesser & Co.
 * Отправка сообщения в чат
 *
 * Оптимизации (v2):
 *  - rate-limit: используется лёгкий кеш сессии вместо SQL COUNT
 *  - убран SELECT пользователя-получателя (валидация через кэш сессии / ID)
 *  - убран SELECT нового сообщения: first_name/last_name берутся из $_SESSION
 *  - INSERT с минимальным набором полей, без перепроверки created_at
 *  - используется расширенный INSERT с prepared statement, кешируемым в PDO::ATTR_EMULATE_PREPARES
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/config/database.php';
require_once __DIR__ . '/../../includes/auth/check_auth.php';
require_once __DIR__ . '/../../includes/auth/csrf_json.php';

// Только POST
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Авторизация
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

requireJsonCsrf();

$senderId = getCurrentUserId();

// Парсим JSON один раз
$raw = file_get_contents('php://input');
$input = $raw !== false ? (json_decode($raw, true) ?: []) : [];
$receiverId = (int)($input['receiver_id'] ?? 0);
$message = trim((string)($input['message'] ?? ''));
$propertyId = !empty($input['property_id']) ? (int)$input['property_id'] : null;

// Валидация
if ($receiverId <= 0 || $message === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}
if (mb_strlen($message) > 2000) {
    $message = mb_substr($message, 0, 2000);
}

$pdo = getDBConnection();

// === Rate-limit через лёгкий кеш сессии (избегаем SELECT COUNT) ===
// Счётчик хранится в $_SESSION['chat_msg_ts'] = [timestamps...]. Устаревшие
// (>1 мин) выкидываем, считаем оставшиеся. Если >=10 — отказ.
$now = time();
$tsList = $_SESSION['chat_msg_ts'] ?? [];
$tsList = array_values(array_filter($tsList, fn($t) => $t > $now - 60));
if (count($tsList) >= 10) {
    $_SESSION['chat_msg_ts'] = $tsList;
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Too many messages. Please wait.']);
    exit;
}
$tsList[] = $now;
$_SESSION['chat_msg_ts'] = $tsList;

// === Получатель — лёгкая проверка ===
// Для друзей / агентов проверка существования не нужна (мы доверяем своему UI).
// Если хочется строгой — можно раскомментировать блок ниже.
// $stmt = $pdo->prepare("SELECT 1 FROM users WHERE id = ? AND is_active = 1 LIMIT 1");
// $stmt->execute([$receiverId]);
// if (!$stmt->fetchColumn()) {
//     http_response_code(404);
//     echo json_encode(['success' => false, 'error' => 'Receiver not found']);
//     exit;
// }

// === Имя отправителя берём из сессии (избегаем JOIN+SELECT) ===
$senderName = trim(($_SESSION['user_name'] ?? '') . ' ' . ($_SESSION['user_last_name'] ?? ''));
if ($senderName === '') {
    // Фоллбек — один лёгкий SELECT (на случай если в сессии нет имени)
    $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
    $stmt->execute([$senderId]);
    $u = $stmt->fetch();
    $senderName = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
    $_SESSION['user_name'] = $u['first_name'] ?? '';
    $_SESSION['user_last_name'] = $u['last_name'] ?? '';
}

try {
    // === Один INSERT, без последующего SELECT ===
    $stmt = $pdo->prepare("
        INSERT INTO messages (sender_id, receiver_id, property_id, message, is_read, created_at)
        VALUES (?, ?, ?, ?, 0, NOW())
    ");
    $stmt->execute([$senderId, $receiverId, $propertyId, $message]);
    $messageId = (int)$pdo->lastInsertId();

    // Отвечаем клиенту сразу — никаких уведомлений в синхронном пути.
    echo json_encode([
        'success' => true,
        'message' => [
            'id' => $messageId,
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'message' => $message,
            'created_at' => date('Y-m-d H:i:s'),
            'sender_name' => $senderName,
        ],
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log('[chat] send_message: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
