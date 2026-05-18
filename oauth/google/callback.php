<?php
/**
 * Google OAuth — Callback.
 */

require_once __DIR__ . '/../../includes/config/database.php';
require_once __DIR__ . '/../../includes/auth/oauth_helper.php';

$code  = trim($_GET['code']  ?? '');
$state = trim($_GET['state'] ?? '');
$saved = OAuthHelper::consumeState('google', $state);

if ($code === '' || !$saved) {
    http_response_code(400);
    die('Invalid OAuth state');
}

$tokenData = OAuthHelper::httpPost('https://oauth2.googleapis.com/token', [
    'code'          => $code,
    'client_id'     => Config::get('GOOGLE_CLIENT_ID'),
    'client_secret' => Config::get('GOOGLE_CLIENT_SECRET'),
    'redirect_uri'  => Config::get('GOOGLE_REDIRECT_URI'),
    'grant_type'    => 'authorization_code',
]);

if (empty($tokenData['access_token'])) {
    error_log('Google OAuth error: ' . json_encode($tokenData));
    http_response_code(500);
    die('Google OAuth failed');
}

$profile = OAuthHelper::httpGet(
    'https://openidconnect.googleapis.com/v1/userinfo',
    ['Authorization: Bearer ' . $tokenData['access_token']]
);

$googleId = (string)($profile['sub'] ?? '');
$email    = $profile['email']         ?? '';
$first    = $profile['given_name']    ?? '';
$last     = $profile['family_name']   ?? '';
$avatar   = $profile['picture']       ?? '';

if ($googleId === '') {
    http_response_code(500);
    die('Google profile fetch failed');
}

OAuthHelper::loginOrRegister('google', $googleId, $email, $first, $last, $avatar);

header('Location: ' . OAuthHelper::safeRedirect($saved['redirect']));
exit;
