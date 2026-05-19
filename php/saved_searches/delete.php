<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../includes/config/database.php';
require_once __DIR__ . '/../../includes/auth/check_auth.php';
require_once __DIR__ . '/../../includes/auth/csrf_json.php';
require_once __DIR__ . '/../../includes/saved_searches/SavedSearchService.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false]);
    exit;
}
requireJsonCsrf();
$input = json_decode((string)file_get_contents('php://input'), true) ?: [];
$id = (int)($input['id'] ?? 0);
$ok = SavedSearchService::delete(getDBConnection(), getCurrentUserId(), $id);
echo json_encode(['success' => $ok]);
