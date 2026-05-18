<?php
/**
 * OAuth helper — общая логика для VK / Yandex / Google / Telegram.
 * Без зависимостей. Использует curl + Config + БД.
 */

require_once __DIR__ . '/check_auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/Config.php';

final class OAuthHelper
{
    /**
     * Сгенерировать и сохранить state (защита от CSRF в OAuth callback).
     */
    public static function generateState(string $provider): string
    {
        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state'] = [
            'provider' => $provider,
            'value'    => $state,
            'expires'  => time() + 600,
            'redirect' => $_GET['redirect'] ?? '/dashboard.php',
        ];
        return $state;
    }

    public static function consumeState(string $provider, string $state): ?array
    {
        $saved = $_SESSION['oauth_state'] ?? null;
        unset($_SESSION['oauth_state']);
        if (!$saved || $saved['provider'] !== $provider || $saved['value'] !== $state) {
            return null;
        }
        if ($saved['expires'] < time()) {
            return null;
        }
        return $saved;
    }

    /**
     * POST к OAuth endpoint, возврат JSON-массива.
     */
    public static function httpPost(string $url, array $params): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_TIMEOUT        => 15,
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        $data = json_decode((string)$body, true);
        return is_array($data) ? $data : [];
    }

    public static function httpGet(string $url, array $headers = []): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => array_merge(['Accept: application/json'], $headers),
            CURLOPT_TIMEOUT        => 15,
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        $data = json_decode((string)$body, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Найти/создать пользователя по OAuth-профилю и залогинить.
     *
     * @param string $provider vk|yandex|google|telegram
     * @param string $oauthId  ID пользователя в провайдере
     * @param string $email
     * @param string $firstName
     * @param string $lastName
     * @param string $avatar
     */
    public static function loginOrRegister(
        string $provider,
        string $oauthId,
        string $email,
        string $firstName = '',
        string $lastName = '',
        string $avatar = ''
    ): int {
        $pdo = getDBConnection();

        // 1) Существующий аккаунт по (provider, oauth_id)
        $stmt = $pdo->prepare("SELECT * FROM users WHERE oauth_provider = ? AND oauth_id = ? LIMIT 1");
        $stmt->execute([$provider, $oauthId]);
        $user = $stmt->fetch();

        // 2) Существующий аккаунт по email — привязываем OAuth
        if (!$user && $email !== '') {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            if ($user) {
                $pdo->prepare("
                    UPDATE users SET oauth_provider = ?, oauth_id = ?,
                                     email_verified_at = COALESCE(email_verified_at, NOW())
                    WHERE id = ?
                ")->execute([$provider, $oauthId, $user['id']]);
            }
        }

        // 3) Создаём нового
        if (!$user) {
            $finalEmail = $email !== ''
                ? $email
                : sprintf('%s_%s@oauth.local', $provider, $oauthId);

            // Заглушка пароля — OAuth-юзер не сможет залогиниться по паролю
            $pwdHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);

            $pdo->prepare("
                INSERT INTO users
                    (email, password_hash, first_name, last_name, avatar, role,
                     is_active, email_verified_at, oauth_provider, oauth_id)
                VALUES (?, ?, ?, ?, ?, 'user', 1, NOW(), ?, ?)
            ")->execute([
                $finalEmail,
                $pwdHash,
                $firstName !== '' ? $firstName : 'Пользователь',
                $lastName,
                $avatar ?: null,
                $provider,
                $oauthId,
            ]);

            $userId = (int)$pdo->lastInsertId();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
        }

        // Логиним
        session_regenerate_id(true);
        $_SESSION['user_id']    = (int)$user['id'];
        $_SESSION['user_name']  = $user['first_name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role']  = $user['role'];
        $_SESSION['logged_in']  = true;
        $_SESSION['login_time'] = time();

        return (int)$user['id'];
    }

    /**
     * Безопасный относительный редирект.
     */
    public static function safeRedirect(?string $url, string $default = '/dashboard.php'): string
    {
        if (!$url) return $default;
        if (!str_starts_with($url, '/') || str_contains($url, '//')) return $default;
        return $url;
    }
}
