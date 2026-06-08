<?php
/**
 * Password Reset — токен-генерация, верификация, сброс пароля.
 * Использует таблицу `password_resets`.
 */

require_once __DIR__ . '/check_auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../email/Mailer.php';

/**
 * Создать токен сброса и отправить письмо.
 * @return array{ok:bool, error?:string}
 */
function requestPasswordReset(string $email): array
{
    $email = trim($email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Некорректный email'];
    }

    $pdo = getDBConnection();

    $stmt = $pdo->prepare("SELECT id, first_name FROM users WHERE email = ? AND is_active = 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        // Чтобы не палить существование email — возвращаем "ok" даже если не нашли
        return ['ok' => true];
    }

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = (new DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:s');

    $pdo->prepare("DELETE FROM password_resets WHERE user_id = ? OR expires_at < NOW()")
        ->execute([$user['id']]);

    $stmt = $pdo->prepare("
        INSERT INTO password_resets (user_id, token_hash, expires_at, ip_address)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$user['id'], $tokenHash, $expiresAt, $_SERVER['REMOTE_ADDR'] ?? null]);

    $resetUrl = Config::appUrl() . '/reset-password.php?token=' . $token;

    $subject = 'Восстановление пароля — Elsesser & Co.';
    $body = '<p>Здравствуйте, ' . htmlspecialchars($user['first_name']) . '.</p>'
          . '<p>Кто-то запросил сброс пароля для вашего аккаунта. Если это не вы — просто проигнорируйте это письмо.</p>'
          . '<p><a href="' . htmlspecialchars($resetUrl) . '" style="display:inline-block;padding:12px 24px;background:#00736c;color:#fff;border-radius:8px;text-decoration:none;">Установить новый пароль</a></p>'
          . '<p>Ссылка действует 1 час.</p>'
          . '<p style="color:#888;font-size:12px;">' . htmlspecialchars($resetUrl) . '</p>';

    $_SESSION['last_password_reset_url'] = $resetUrl;
    Mailer::send($email, $subject, $body);

    return ['ok' => true];
}

/**
 * Проверить токен и вернуть user_id, либо null.
 */
function verifyPasswordResetToken(string $token): ?int
{
    if ($token === '' || !ctype_xdigit($token)) {
        return null;
    }
    $hash = hash('sha256', $token);
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT user_id FROM password_resets
        WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([$hash]);
    $row = $stmt->fetch();
    return $row ? (int)$row['user_id'] : null;
}

/**
 * Применить новый пароль и пометить токен использованным.
 */
function applyPasswordReset(string $token, string $newPassword): array
{
    if (strlen($newPassword) < 8 || !preg_match('/[A-Za-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
        return ['ok' => false, 'error' => 'Пароль должен содержать минимум 8 символов, буквы и цифры'];
    }

    $userId = verifyPasswordResetToken($token);
    if (!$userId) {
        return ['ok' => false, 'error' => 'Ссылка недействительна или истекла'];
    }

    $pdo = getDBConnection();
    $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE users SET password_hash = ?, failed_login_attempts = 0, locked_until = NULL WHERE id = ?")
            ->execute([$hash, $userId]);
        $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE token_hash = ?")
            ->execute([hash('sha256', $token)]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('applyPasswordReset: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Внутренняя ошибка'];
    }

    return ['ok' => true];
}
