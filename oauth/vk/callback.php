<?php
/**
 * VK OAuth — Callback. Меняет code на access_token и логинит пользователя.
 *
 * Поддерживает два потока согласно VK ID Web SDK v3 (https://github.com/VKCOM/vkid-web-sdk):
 *  1) Стандартный redirect-flow (oauth/vk/start.php → /authorize → callback)
 *  2) OneTap / Auth.login() — после успешного входа SDK редиректит
 *     пользователя на этот же callback с параметрами:
 *       code, state, device_id, type=code_v2, expires_in
 *     PKCE code_verifier SDK хранит в JS-куке 'vkid_sdk:codeVerifier'.
 *
 * Обмен code → access_token (OAuth 2.1 + PKCE):
 *   POST https://id.vk.com/oauth2/auth
 *     query : grant_type, client_id, redirect_uri, code_verifier, device_id, state
 *     body  : code=<the_code>
 *   response: JSON { access_token, refresh_token, expires_in, state, ... }
 *
 * Затем профиль:
 *   POST https://id.vk.com/oauth2/user_info
 *     body  : access_token=<token>
 *   или GET с параметрами в query (работает оба варианта).
 */

require_once __DIR__ . '/../../includes/config/database.php';
require_once __DIR__ . '/../../includes/auth/oauth_helper.php';

$code      = trim($_GET['code']      ?? '');
$state     = trim($_GET['state']     ?? '');
$deviceId  = trim($_GET['device_id'] ?? '');

// state хранится в PHP-сессии (мы кладём туда в vk-onetap.php / start.php)
$saved = OAuthHelper::consumeState('vk', $state);

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

// PKCE code_verifier:
//  1) Сначала из PHP-сессии (кладётся в vk-onetap.php и start.php).
//  2) Если нет — из JS-куки 'vkid_sdk:codeVerifier', которую ставит сам VK ID SDK v3.
$codeVerifier = OAuthHelper::consumeCodeVerifier('vk');
if ($codeVerifier === null && isset($_COOKIE['vkid_sdk:codeVerifier'])) {
    $codeVerifier = (string)$_COOKIE['vkid_sdk:codeVerifier'];
}

// === Шаг 1: обмен code → access_token (OAuth 2.1, формат как в SDK) ===
// Параметры разделены: query (метаданные) + body (сам code).
$queryParams = array_filter([
    'grant_type'    => 'authorization_code',
    'client_id'     => $clientId,
    'redirect_uri'  => $redirect,
    'code_verifier' => $codeVerifier,
    'device_id'     => $deviceId,
    'state'         => $state,
], fn($v) => $v !== null && $v !== '');

$tokenData = OAuthHelper::httpPostQueryAndBody(
    'https://id.vk.com/oauth2/auth',
    $queryParams,
    ['code' => $code]
);

if (empty($tokenData['access_token'])) {
    error_log('VK OAuth token exchange failed: ' . json_encode($tokenData)
        . ' verifier=' . ($codeVerifier ? 'set' : 'NULL')
        . ' device_id=' . ($deviceId ?: 'NULL'));
    http_response_code(500);
    die('VK OAuth failed: ' . htmlspecialchars($tokenData['error_description'] ?? $tokenData['error'] ?? 'unknown'));
}

$accessToken = $tokenData['access_token'];

// === Шаг 2: получение профиля пользователя ===
// SDK использует POST + access_token в body; но серверный код спокойно
// работает и с GET. Используем POST как в SDK для единообразия.
$profile = OAuthHelper::httpPost(
    'https://id.vk.com/oauth2/user_info',
    ['access_token' => $accessToken, 'client_id' => $clientId]
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
    // Fallback на старый API VK
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