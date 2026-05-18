<?php
/**
 * Yandex OAuth — Callback.
 */

require_once __DIR__ . '/../../includes/config/database.php';
require_once __DIR__ . '/../../includes/auth/oauth_helper.php';

$code  = trim($_GET['code']  ?? '');
$state = trim($_GET['state'] ?? '');
$saved = OAuthHelper::consumeState('yandex', $state);

if ($code === '' || !$saved) {
    http_response_code(400);
    die('Invalid OAuth state');
}

$tokenData = OAuthHelper::httpPost('https://oauth.yandex.ru/token', [
    'grant_type'    => 'authorization_code',
    'code'          => $code,
    'client_id'     => Config::get('YANDEX_CLIENT_ID'),
    'client_secret' => Config::get('YANDEX_CLIENT_SECRET'),
]);

if (empty($tokenData['access_token'])) {
    error_log('Yandex OAuth error: ' . json_encode($tokenData));
    http_response_code(500);
    die('Yandex OAuth failed');
}

$profile = OAuthHelper::httpGet(
    'https://login.yandex.ru/info?format=json',
    ['Authorization: OAuth ' . $tokenData['access_token']]
);

$yandexId = (string)($profile['id'] ?? '');
$email    = $profile['default_email'] ?? ($profile['emails'][0] ?? '');
$first    = $profile['first_name'] ?? '';
$last     = $profile['last_name']  ?? '';
$avatar   = !empty($profile['default_avatar_id'])
    ? 'https://avatars.yandex.net/get-yapic/' . $profile['default_avatar_id'] . '/islands-200'
    : '';

if ($yandexId === '') {
    http_response_code(500);
    die('Yandex profile fetch failed');
}

OAuthHelper::loginOrRegister('yandex', $yandexId, $email, $first, $last, $avatar);

header('Location: ' . OAuthHelper::safeRedirect($saved['redirect']));
exit;
