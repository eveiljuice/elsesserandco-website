<?php
/**
 * Telegram Login Widget callback.
 *
 * Виджет добавляется на login.php / register.php тэгом:
 *   <script async src="https://telegram.org/js/telegram-widget.js?22"
 *           data-telegram-login="<BOT_USERNAME>"
 *           data-size="large"
 *           data-userpic="false"
 *           data-radius="8"
 *           data-auth-url="/oauth/telegram/callback.php"
 *           data-request-access="write"></script>
 *
 * Telegram редиректит сюда с GET-параметрами: id, first_name, last_name, username, photo_url, auth_date, hash.
 * Проверяем hash по HMAC-SHA256 с ключом = sha256(bot_token).
 */

require_once __DIR__ . '/../../includes/config/database.php';
require_once __DIR__ . '/../../includes/auth/oauth_helper.php';

$botToken = Config::get('TELEGRAM_BOT_TOKEN');
if (!$botToken) {
    http_response_code(500);
    die('Telegram Login не настроен. TELEGRAM_BOT_TOKEN отсутствует в .env.');
}

$data = $_GET;
$hash = $data['hash'] ?? '';
unset($data['hash']);

if ($hash === '') {
    http_response_code(400);
    die('Bad request');
}

// Проверка auth_date — не старше 24 часов
if (!empty($data['auth_date']) && (time() - (int)$data['auth_date']) > 86400) {
    http_response_code(401);
    die('Auth data outdated');
}

// Собираем data_check_string
ksort($data);
$pairs = [];
foreach ($data as $k => $v) {
    $pairs[] = $k . '=' . $v;
}
$dataCheckString = implode("\n", $pairs);
$secretKey = hash('sha256', $botToken, true);
$calcHash  = hash_hmac('sha256', $dataCheckString, $secretKey);

if (!hash_equals($calcHash, $hash)) {
    http_response_code(403);
    die('Invalid Telegram signature');
}

$tgId  = (string)($data['id'] ?? '');
$first = $data['first_name'] ?? '';
$last  = $data['last_name']  ?? '';
$user  = $data['username']   ?? '';

if ($tgId === '') {
    http_response_code(400);
    die('No Telegram ID');
}

// Привязываем telegram_id если такого юзера ещё нет
$pdo = getDBConnection();
$stmt = $pdo->prepare("SELECT id FROM users WHERE telegram_id = ? OR (oauth_provider='telegram' AND oauth_id=?) LIMIT 1");
$stmt->execute([$tgId, $tgId]);
$row = $stmt->fetch();

if ($row) {
    // Существующий — логин через общий хелпер
    OAuthHelper::loginOrRegister('telegram', $tgId, '', $first, $last, $data['photo_url'] ?? '');
} else {
    // Новый
    $userId = OAuthHelper::loginOrRegister('telegram', $tgId, '', $first, $last, $data['photo_url'] ?? '');
    $pdo->prepare("UPDATE users SET telegram_id = ?, telegram_username = ? WHERE id = ?")
        ->execute([$tgId, $user, $userId]);
}

$redirect = OAuthHelper::safeRedirect($_GET['redirect_to'] ?? null);
header('Location: ' . $redirect);
exit;
