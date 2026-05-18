<?php
/**
 * Google OAuth — Start.
 */

require_once __DIR__ . '/../../includes/config/database.php';
require_once __DIR__ . '/../../includes/auth/oauth_helper.php';

$clientId = Config::get('GOOGLE_CLIENT_ID');
$redirect = Config::get('GOOGLE_REDIRECT_URI');

if (!$clientId || !$redirect) {
    http_response_code(500);
    die('Google OAuth не настроен. Заполните GOOGLE_CLIENT_ID/GOOGLE_REDIRECT_URI в .env');
}

$state = OAuthHelper::generateState('google');

$params = http_build_query([
    'response_type' => 'code',
    'client_id'     => $clientId,
    'redirect_uri'  => $redirect,
    'state'         => $state,
    'scope'         => 'openid email profile',
    'access_type'   => 'online',
    'prompt'        => 'select_account',
]);

header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
exit;
