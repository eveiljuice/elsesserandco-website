<?php
/**
 * Web Push subscribe endpoint.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/config/database.php';
require_once __DIR__ . '/../../includes/auth/check_auth.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['success' => false, 'error' => 'Method not allowed']); exit;
}
$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!validateCSRFToken($csrf)) {
    http_response_code(403); echo json_encode(['success' => false, 'error' => 'Bad CSRF']); exit;
}

$input    = json_decode(file_get_contents('php://input'), true) ?: [];
$endpoint = (string)($input['endpoint'] ?? '');
$keys     = $input['keys'] ?? [];
$p256dh   = (string)($keys['p256dh'] ?? '');
$auth     = (string)($keys['auth']   ?? '');

if (!$endpoint || !$p256dh || !$auth) {
    http_response_code(400); echo json_encode(['success' => false, 'error' => 'Bad subscription']); exit;
}

$userId = (int)getCurrentUserId();
$ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

try {
    $pdo = getDBConnection();
    $pdo->prepare("
        INSERT INTO push_subscriptions (user_id, endpoint, p256dh_key, auth_key, user_agent)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE user_id = VALUES(user_id),
                                p256dh_key = VALUES(p256dh_key),
                                auth_key = VALUES(auth_key),
                                user_agent = VALUES(user_agent),
                                last_seen_at = NOW()
    ")->execute([$userId, $endpoint, $p256dh, $auth, $ua]);
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    error_log('Push subscribe: ' . $e->getMessage());
    http_response_code(500); echo json_encode(['success' => false, 'error' => 'Server error']);
}
