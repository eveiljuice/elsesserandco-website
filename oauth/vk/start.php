<?php
/**
 * VK OAuth — Старт авторизации.
 * Использует VK ID (новый OAuth 2.1 endpoint) + PKCE.
 *
 * Этот endpoint используется для:
 *  1) Классического redirect-flow (старая VK OAuth кнопка)
 *  2) Fallback для OneTap SDK, если AJAX-обмен не сработал
 */

require_once __DIR__ . '/../../includes/config/database.php';
require_once __DIR__ . '/../../includes/auth/oauth_helper.php';

$clientId = Config::get('VK_CLIENT_ID');
$redirect = Config::get('VK_REDIRECT_URI');

if (!$clientId || !$redirect) {
    http_response_code(500);
    die('VK OAuth не настроен. Заполните VK_CLIENT_ID/VK_REDIRECT_URI в .env');
}

// CSRF state
$state = OAuthHelper::generateState('vk');

// PKCE
$pkce = OAuthHelper::generatePkce();
OAuthHelper::storeCodeVerifier('vk', $pkce['verifier']);

// Помечаем mode, чтобы callback знал, откуда пришёл запрос
$_SESSION['oauth_state']['mode'] = 'redirect';

$params = http_build_query([
    'client_id'             => $clientId,
    'redirect_uri'          => $redirect,
    'response_type'         => 'code',
    'scope'                 => 'email',
    'state'                 => $state,
    'code_challenge'        => $pkce['challenge'],
    'code_challenge_method' => 'S256',
    'v'                     => '5.131',
]);

// VK ID — новый endpoint OAuth 2.1
header('Location: https://id.vk.com/authorize?' . $params);
exit;