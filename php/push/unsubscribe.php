<?php
/**
 * Web Push unsubscribe endpoint.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/config/database.php';
require_once __DIR__ . '/../../includes/auth/check_auth.php';

if (!isLoggedIn()) { http_response_code(401); echo json_encode(['success' => false]); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success' => false]); exit; }
if (!validateCSRFToken($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
    http_response_code(403); echo json_encode(['success' => false]); exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$endpoint = (string)($input['endpoint'] ?? '');
if (!$endpoint) { http_response_code(400); echo json_encode(['success' => false]); exit; }

try {
    $pdo = getDBConnection();
    $pdo->prepare("DELETE FROM push_subscriptions WHERE user_id = ? AND endpoint = ?")
        ->execute([(int)getCurrentUserId(), $endpoint]);
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    error_log('Push unsubscribe: ' . $e->getMessage());
    http_response_code(500); echo json_encode(['success' => false]);
}
