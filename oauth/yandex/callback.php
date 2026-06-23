<?php
/**
 * Yandex OAuth — Callback.
 */

require_once __DIR__ . '/../../includes/config/database.php';
require_once __DIR__ . '/../../includes/auth/oauth_helper.php';

$code      = trim($_GET['code']      ?? '');
$state     = trim($_GET['state']     ?? '');
$error     = trim($_GET['error']     ?? '');
$errorDesc = trim($_GET['error_description'] ?? '');
$saved     = OAuthHelper::consumeState('yandex', $state);

// Провайдер вернул ошибку до выдачи кода (например, не подтверждённые доступы)
if ($error !== '') {
    http_response_code(400);
    if ($error === 'invalid_scope') {
        die('Яндекс: у приложения не подключены нужные доступы (email / info / avatar). '
            . 'Зайдите в https://oauth.yandex.ru/ → "Мои приложения" → "' . htmlspecialchars(Config::get('YANDEX_CLIENT_ID')) . '" '
            . '→ включите галки "Доступ к email", "Доступ к информации о пользователе", "Доступ к аватару".');
    }
    die('Яндекс OAuth: ' . htmlspecialchars($errorDesc ?: $error));
}

if ($code === '' || !$saved) {
    http_response_code(400);
    die('Invalid OAuth state');
}

$tokenData = OAuthHelper::httpPost('https://oauth.yandex.ru/token', [
    'grant_type'    => 'authorization_code',
    'code'          => $code,
    'client_id'     => Config::get('YANDEX_CLIENT_ID'),
    'client_secret' => Config::get('YANDEX_CLIENT_SECRET'),
    'redirect_uri'  => Config::get('YANDEX_REDIRECT_URI'),
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
