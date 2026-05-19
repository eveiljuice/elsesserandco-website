<?php
/**
 * CSRF для JSON/AJAX эндпоинтов (заголовок X-CSRF-Token).
 */
declare(strict_types=1);

require_once __DIR__ . '/check_auth.php';

function requireJsonCsrf(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? '';

    if ($token === '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode((string)file_get_contents('php://input'), true);
        $token = is_array($input) ? (string)($input['csrf_token'] ?? '') : '';
    }

    if (!validateCSRFToken($token)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Bad CSRF'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
