<?php
/**
 * OAuth helper — общая логика для VK / Yandex / Google.
 * Без зависимостей. Использует curl + Config + БД.
 *
 * Telegram Login удалён в v2.2: код колбэка и БД-колонки были выпилены
 * миграцией 023_cleanup.sql, виджет из login/register снят.
 */

require_once __DIR__ . '/check_auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/Config.php';

final class OAuthHelper
{
    /**
     * Сгенерировать и сохранить state (защита от CSRF в OAuth callback).
     * Хранится в сессии на 10 минут.
     */
    public static function generateState(string $provider, ?string $redirect = null): string
    {
        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state'] = [
            'provider' => $provider,
            'value'    => $state,
            'expires'  => time() + 600,
            'redirect' => $redirect ?? ($_GET['redirect'] ?? '/dashboard.php'),
            'mode'     => $_SESSION['oauth_state']['mode'] ?? 'redirect', // 'redirect' | 'onetap'
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
     * Сгенерировать PKCE code_verifier (43-128 символов) и code_challenge (S256).
     * Нужен для OAuth 2.1 (OneTap SDK v3) и PKCE-flow на сервере.
     *
     * @return array{verifier: string, challenge: string}
     */
    public static function generatePkce(): array
    {
        $verifier = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        return ['verifier' => $verifier, 'challenge' => $challenge];
    }

    /**
     * Сохранить code_verifier в сессии (нужен при обмене code → token в callback).
     */
    public static function storeCodeVerifier(string $provider, string $verifier): void
    {
        $_SESSION['oauth_code_verifier'] = [
            'provider' => $provider,
            'verifier' => $verifier,
            'expires'  => time() + 600,
        ];
    }

    public static function consumeCodeVerifier(string $provider): ?string
    {
        $saved = $_SESSION['oauth_code_verifier'] ?? null;
        unset($_SESSION['oauth_code_verifier']);
        if (!$saved || $saved['provider'] !== $provider) {
            return null;
        }
        if ($saved['expires'] < time()) {
            return null;
        }
        return $saved['verifier'];
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

    /**
     * POST с разделением параметров на query string и body.
     * Нужно для VK ID OAuth 2.1: SDK шлёт параметры авторизации (grant_type,
     * code_verifier, device_id, state, client_id, redirect_uri) в query, а
     * сам code — в body как application/x-www-form-urlencoded.
     *
     * @param array<string,string> $queryParams
     * @param array<string,string> $bodyParams
     */
    public static function httpPostQueryAndBody(string $url, array $queryParams, array $bodyParams): array
    {
        $full = $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($queryParams);
        $ch = curl_init($full);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($bodyParams),
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
     * @param string $provider vk|yandex|google
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
        $_SESSION['user_email_verified'] = !empty($user['email_verified_at']);
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
