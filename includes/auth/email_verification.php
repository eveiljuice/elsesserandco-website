<?php
/**
 * Email Verification — генерация/проверка ссылок подтверждения email.
 */

require_once __DIR__ . '/check_auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../email/Mailer.php';

/**
 * Создать токен подтверждения и отправить пользователю.
 */
function sendEmailVerification(int $userId): bool
{
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT email, first_name, email_verified_at FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user || $user['email_verified_at']) {
        return false;
    }

    $token = bin2hex(random_bytes(32));
    $expires = (new DateTimeImmutable('+24 hours'))->format('Y-m-d H:i:s');

    $pdo->prepare("
        UPDATE users
        SET email_verification_token = ?, email_verification_expires_at = ?
        WHERE id = ?
    ")->execute([hash('sha256', $token), $expires, $userId]);

    $url = Config::appUrl() . '/verify-email.php?token=' . $token;
    $subject = 'Подтверждение email — Elsesser & Co.';
    $body  = '<p>Здравствуйте, ' . htmlspecialchars($user['first_name']) . '.</p>'
           . '<p>Спасибо за регистрацию. Подтвердите ваш email — это нужно, чтобы:</p>'
           . '<ul><li>сохранять избранное и сравнения</li><li>писать агентам</li><li>получать алерты по сохранённым поискам</li></ul>'
           . '<p><a href="' . htmlspecialchars($url) . '" style="display:inline-block;padding:12px 24px;background:#00736c;color:#fff;border-radius:8px;text-decoration:none;">Подтвердить email</a></p>'
           . '<p style="color:#888;font-size:12px;">Ссылка действует 24 часа.</p>';

    $_SESSION['last_email_verification_url'] = $url;
    return Mailer::send($user['email'], $subject, $body);
}

/**
 * Подтвердить email по токену. Возвращает user_id или null.
 */
function consumeEmailVerification(string $token): ?int
{
    if ($token === '' || !ctype_xdigit($token)) {
        return null;
    }
    $pdo = getDBConnection();
    $hash = hash('sha256', $token);
    $stmt = $pdo->prepare("
        SELECT id FROM users
        WHERE email_verification_token = ?
          AND email_verification_expires_at > NOW()
          AND email_verified_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([$hash]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $pdo->prepare("
        UPDATE users
        SET email_verified_at = NOW(),
            email_verification_token = NULL,
            email_verification_expires_at = NULL
        WHERE id = ?
    ")->execute([$row['id']]);

    // Если это текущий залогиненный пользователь — синхронизируем сессию.
    if (!empty($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$row['id']) {
        $_SESSION['user_email_verified'] = true;
    }

    return (int)$row['id'];
}
