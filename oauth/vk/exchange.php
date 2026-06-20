<?php
/**
 * VK OAuth — AJAX-обмен (для OneTap SDK v3).
 *
 * Принимает JSON { code, device_id } от фронтенда после успешного
 * входа через VK ID OneTap. Делает server-to-server обмен code → token,
 * получает профиль пользователя и логинит через OAuthHelper.
 *
 * Преимущества по сравнению с redirect-flow:
 *  - токен VK ID не появляется в URL (безопаснее);
 *  - пользователь остаётся на той же странице (без редиректа);
 *  - проще обрабатывать ошибки на клиенте.
 *
 * Использование с фронта (после VKID.Auth.exchangeCode):
 *   fetch('/oauth/vk/exchange.php', {
 *     method: 'POST',
 *     headers: { 'Content-Type': 'application/json' },
 *     body: JSON.stringify({ code: data.code, device_id: data.device_id })
 *   }).then(r => r.json()).then(...)
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/config/database.php';
require_once __DIR__ . '/../../includes/auth/oauth_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

// CSRF: фронтенд шлёт токен из meta[name=csrf-token] + state из сессии
$raw = file_get_contents('php://input');
$input = $raw !== false ? (json_decode($raw, true) ?: []) : [];

$code     = trim((string)($input['code'] ?? ''));
$deviceId = trim((string)($input['device_id'] ?? ''));
$state    = trim((string)($input['state'] ?? ''));

if ($code === '' || $state === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'missing_code_or_state']);
    exit;
}

// Проверяем state (CSRF)
$saved = OAuthHelper::consumeState('vk', $state);
if (!$saved) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_state']);
    exit;
}

$codeVerifier = OAuthHelper::consumeCodeVerifier('vk');

$clientId     = Config::get('VK_CLIENT_ID');
$clientSecret = Config::get('VK_CLIENT_SECRET');
$redirect     = Config::get('VK_REDIRECT_URI');

if (!$clientId || !$clientSecret) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'oauth_not_configured']);
    exit;
}

// Обмен code → access_token
$tokenData = OAuthHelper::httpPost('https://id.vk.com/oauth2/auth', array_filter([
    'grant_type'    => 'authorization_code',
    'code'          => $code,
    'redirect_uri'  => $redirect,
    'client_id'     => $clientId,
    'client_secret' => $clientSecret,
    'code_verifier' => $codeVerifier,
    'device_id'     => $deviceId,
], fn($v) => $v !== null && $v !== ''));

if (empty($tokenData['access_token'])) {
    error_log('VK exchange failed: ' . json_encode($tokenData));
    http_response_code(400);
    echo json_encode([
        'ok'    => false,
        'error' => 'token_exchange_failed',
        'vk_error' => $tokenData['error'] ?? null,
        'vk_error_description' => $tokenData['error_description'] ?? null,
    ]);
    exit;
}

$accessToken = $tokenData['access_token'];

// Профиль
$profile = OAuthHelper::httpGet(
    'https://id.vk.com/oauth2/user_info?access_token=' . urlencode($accessToken)
        . '&client_id=' . urlencode($clientId)
);

$vkUserId = $email = $first = $last = $avatar = '';
if (!empty($profile['user'])) {
    $u = $profile['user'];
    $vkUserId = (string)($u['user_id'] ?? $u['sub'] ?? '');
    $email    = (string)($u['email'] ?? '');
    $first    = (string)($u['first_name'] ?? '');
    $last     = (string)($u['last_name'] ?? '');
    $avatar   = (string)($u['avatar'] ?? $u['photo_200'] ?? '');
} else {
    $fallback = OAuthHelper::httpGet(
        'https://api.vk.com/method/users.get?fields=photo_200,first_name,last_name'
        . '&access_token=' . urlencode($accessToken) . '&v=5.131'
    );
    if (!empty($fallback['response'][0])) {
        $r = $fallback['response'][0];
        $vkUserId = (string)($r['id'] ?? '');
        $first    = (string)($r['first_name'] ?? '');
        $last     = (string)($r['last_name'] ?? '');
        $avatar   = (string)($r['photo_200'] ?? '');
    }
    if (!$email && !empty($tokenData['email'])) {
        $email = $tokenData['email'];
    }
}

if ($vkUserId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'no_user_id']);
    exit;
}

$userId = OAuthHelper::loginOrRegister('vk', $vkUserId, $email, $first, $last, $avatar);

echo json_encode([
    'ok'         => true,
    'user_id'    => $userId,
    'redirect'   => OAuthHelper::safeRedirect($saved['redirect']),
]);