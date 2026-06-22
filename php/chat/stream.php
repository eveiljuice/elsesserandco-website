<?php
/**
 * SSE stream для чата (замена polling).
 * GET: user_id, last_id
 */
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config/database.php';
require_once __DIR__ . '/../../includes/auth/check_auth.php';

if (!isLoggedIn()) {
    http_response_code(401);
    exit;
}

$otherUserId = (int)($_GET['user_id'] ?? 0);
$lastId = (int)($_GET['last_id'] ?? 0);
$userId = getCurrentUserId();

if ($otherUserId <= 0) {
    http_response_code(400);
    exit;
}

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', '1');
}
@ini_set('zlib.output_compression', '0');
@ini_set('output_buffering', 'off');
while (ob_get_level()) {
    ob_end_flush();
}

$pdo = getDBConnection();
$deadline = time() + 25;
$currentLast = $lastId;

while (time() < $deadline && !connection_aborted()) {
    $stmt = $pdo->prepare("
        SELECT m.id, m.sender_id, m.message, m.created_at, m.is_read,
               u.first_name AS sender_first_name,
               u.avatar AS sender_avatar
        FROM messages m
        JOIN users u ON u.id = m.sender_id
        WHERE ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
          AND m.id > ?
        ORDER BY m.id ASC
        LIMIT 50
    ");
    $stmt->execute([$userId, $otherUserId, $otherUserId, $userId, $currentLast]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($rows !== []) {
        $currentLast = (int)end($rows)['id'];
        echo 'event: messages' . "\n";
        echo 'data: ' . json_encode(['messages' => $rows, 'last_id' => $currentLast], JSON_UNESCAPED_UNICODE) . "\n\n";
        flush();
        break;
    }

    echo ": ping\n\n";
    flush();
    usleep(500000);
}

echo "event: done\ndata: {}\n\n";
flush();
