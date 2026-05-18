<?php
/**
 * Yandex OAuth — Start.
 */

require_once __DIR__ . '/../../includes/config/database.php';
require_once __DIR__ . '/../../includes/auth/oauth_helper.php';

$clientId = Config::get('YANDEX_CLIENT_ID');
$redirect = Config::get('YANDEX_REDIRECT_URI');

if (!$clientId || !$redirect) {
    http_response_code(500);
    die('Yandex OAuth не настроен. Заполните YANDEX_CLIENT_ID/YANDEX_REDIRECT_URI в .env');
}

$state = OAuthHelper::generateState('yandex');

$params = http_build_query([
    'response_type' => 'code',
    'client_id'     => $clientId,
    'redirect_uri'  => $redirect,
    'state'         => $state,
    'scope'         => 'login:email login:info login:avatar',
]);

header('Location: https://oauth.yandex.ru/authorize?' . $params);
exit;
