<?php
/**
 * VK OAuth — Callback. Меняет code на access_token и логинит пользователя.
 */

require_once __DIR__ . '/../../includes/config/database.php';
require_once __DIR__ . '/../../includes/auth/oauth_helper.php';

$code  = trim($_GET['code']  ?? '');
$state = trim($_GET['state'] ?? '');
$saved = OAuthHelper::consumeState('vk', $state);

if ($code === '' || !$saved) {
    http_response_code(400);
    die('Invalid OAuth state');
}

$tokenData = OAuthHelper::httpPost('https://oauth.vk.com/access_token', [
    'client_id'     => Config::get('VK_CLIENT_ID'),
    'client_secret' => Config::get('VK_CLIENT_SECRET'),
    'redirect_uri'  => Config::get('VK_REDIRECT_URI'),
    'code'          => $code,
]);

// VK иногда возвращает 200 с JSON-телом независимо от метода
if (empty($tokenData['access_token']) || empty($tokenData['user_id'])) {
    error_log('VK OAuth error: ' . json_encode($tokenData));
    http_response_code(500);
    die('VK OAuth failed');
}

$accessToken = $tokenData['access_token'];
$vkUserId    = (string)$tokenData['user_id'];
$email       = $tokenData['email'] ?? '';

// Получаем профиль
$profile = OAuthHelper::httpGet(
    'https://api.vk.com/method/users.get?fields=photo_200,first_name,last_name'
    . '&access_token=' . urlencode($accessToken) . '&v=5.131'
);

$first = $last = $avatar = '';
if (!empty($profile['response'][0])) {
    $first  = $profile['response'][0]['first_name'] ?? '';
    $last   = $profile['response'][0]['last_name']  ?? '';
    $avatar = $profile['response'][0]['photo_200']  ?? '';
}

OAuthHelper::loginOrRegister('vk', $vkUserId, $email, $first, $last, $avatar);

header('Location: ' . OAuthHelper::safeRedirect($saved['redirect']));
exit;
