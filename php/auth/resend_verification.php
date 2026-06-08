<?php
/**
 * Resend Email Verification — отправляет письмо ещё раз для текущего юзера.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/config/database.php';
require_once __DIR__ . '/../../includes/auth/check_auth.php';
require_once __DIR__ . '/../../includes/auth/email_verification.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!validateCSRFToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Bad CSRF']);
    exit;
}

$userId = (int)getCurrentUserId();
$ok = sendEmailVerification($userId);

$response = ['success' => $ok];
if (!Config::isProd() && !empty($_SESSION['last_email_verification_url'])) {
    $response['dev_verify_url'] = $_SESSION['last_email_verification_url'];
}

echo json_encode($response);
