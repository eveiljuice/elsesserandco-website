<?php
/**
 * VK OAuth — Старт авторизации.
 * Использует VK ID (новый OAuth 2.1 endpoint).
 */

require_once __DIR__ . '/../../includes/config/database.php';
require_once __DIR__ . '/../../includes/auth/oauth_helper.php';

$clientId = Config::get('VK_CLIENT_ID');
$redirect = Config::get('VK_REDIRECT_URI');

if (!$clientId || !$redirect) {
    http_response_code(500);
    die('VK OAuth не настроен. Заполните VK_CLIENT_ID/VK_REDIRECT_URI в .env');
}

$state = OAuthHelper::generateState('vk');

$params = http_build_query([
    'client_id'     => $clientId,
    'redirect_uri'  => $redirect,
    'response_type' => 'code',
    'scope'         => 'email',
    'state'         => $state,
    'v'             => '5.131',
]);

header('Location: https://oauth.vk.com/authorize?' . $params);
exit;
