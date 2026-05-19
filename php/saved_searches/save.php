<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../includes/config/database.php';
require_once __DIR__ . '/../../includes/auth/check_auth.php';
require_once __DIR__ . '/../../includes/auth/csrf_json.php';
require_once __DIR__ . '/../../includes/saved_searches/SavedSearchService.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST only']);
    exit;
}

requireJsonCsrf();
$input = json_decode((string)file_get_contents('php://input'), true) ?: [];
$name = trim((string)($input['name'] ?? 'Мой поиск'));
$filters = $input['filters'] ?? $_GET;
if (!is_array($filters)) {
    $filters = [];
}

$id = SavedSearchService::save(getDBConnection(), getCurrentUserId(), $name, $filters);
echo json_encode(['success' => true, 'id' => $id]);
