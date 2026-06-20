<?php
/**
 * VK OAuth — Callback. Меняет code на access_token и логинит пользователя.
 *
 * Поддерживает два потока:
 *  1) Стандартный redirect-flow (oauth/vk/start.php → /authorize → callback)
 *  2) OneTap SDK v3 — после успешного входа SDK редиректит пользователя
 *     на этот же callback с параметрами code и state в URL.
 *
 * Используется PKCE: code_verifier хранится в сессии после /start или
 * при AJAX-обмене через /oauth/vk/exchange.php.
 */

require_once __DIR__ . '/../../includes/config/database.php';
require_once __DIR__ . '/../../includes/auth/oauth_helper.php';

$code  = trim($_GET['code']  ?? '');
$state = trim($_GET['state'] ?? '');
$deviceId = trim($_GET['device_id'] ?? ''); // для OneTap потока

$saved = OAuthHelper::consumeState('vk', $state);
$codeVerifier = OAuthHelper::consumeCodeVerifier('vk');

if ($code === '' || !$saved) {
    http_response_code(400);
    die('Invalid OAuth state. Возможно, ссылка устарела или уже использована. Попробуйте войти снова.');
}

$clientId     = Config::get('VK_CLIENT_ID');
$clientSecret = Config::get('VK_CLIENT_SECRET');
$redirect     = Config::get('VK_REDIRECT_URI');

if (!$clientId || !$clientSecret) {
    http_response_code(500);
    die('VK OAuth не настроен на сервере.');
}

// === Шаг 1: обмен code → access_token (OAuth 2.1 + PKCE) ===
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
    error_log('VK OAuth token exchange failed: ' . json_encode($tokenData));
    http_response_code(500);
    die('VK OAuth failed: ' . htmlspecialchars($tokenData['error_description'] ?? $tokenData['error'] ?? 'unknown'));
}

$accessToken = $tokenData['access_token'];

// === Шаг 2: получение профиля пользователя ===
// Новый endpoint (OAuth 2.1): id.vk.com/oauth2/user_info
$profile = OAuthHelper::httpGet(
    'https://id.vk.com/oauth2/user_info?access_token=' . urlencode($accessToken)
        . '&client_id=' . urlencode($clientId)
);

$vkUserId = '';
$email    = '';
$first    = '';
$last     = '';
$avatar   = '';

if (!empty($profile['user'])) {
    $u = $profile['user'];
    $vkUserId = (string)($u['user_id'] ?? $u['sub'] ?? '');
    $email    = (string)($u['email'] ?? '');
    $first    = (string)($u['first_name'] ?? '');
    $last     = (string)($u['last_name'] ?? '');
    $avatar   = (string)($u['avatar'] ?? $u['photo_200'] ?? '');
} else {
    // Fallback на старый API VK, если /user_info не вернул данные
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
    error_log('VK OAuth no user_id in response: ' . json_encode($profile));
    http_response_code(500);
    die('Не удалось получить профиль пользователя VK.');
}

OAuthHelper::loginOrRegister('vk', $vkUserId, $email, $first, $last, $avatar);

header('Location: ' . OAuthHelper::safeRedirect($saved['redirect']));
exit;