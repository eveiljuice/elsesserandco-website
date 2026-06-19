<?php
/**
 * Resend Email Verification — отправляет письмо ещё раз для текущего юзера.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/config/Config.php';
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

// Rate-limit: 1 запрос в 60 секунд.
$now = time();
$last = $_SESSION['last_resend_ts'] ?? 0;
if ($now - (int)$last < 60) {
    $remaining = 60 - ($now - (int)$last);
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'error'   => 'rate_limited',
        'message' => "Подождите {$remaining} сек. до повторной отправки.",
    ]);
    exit;
}

$userId = (int)getCurrentUserId();
$ok = sendEmailVerification($userId);
$_SESSION['last_resend_ts'] = $now;

$response = ['success' => $ok];
if (!Config::isProd() && !empty($_SESSION['last_email_verification_url'])) {
    $response['dev_verify_url'] = $_SESSION['last_email_verification_url'];
}

// Sanity-check: если в проде остался log-транспорт — предупреждаем, что письмо не дойдёт.
if (Config::isProd() && strtolower((string)Config::get('MAIL_TRANSPORT', '')) === 'log') {
    $response['warning'] = 'mail_disabled_in_production';
}

echo json_encode($response);
