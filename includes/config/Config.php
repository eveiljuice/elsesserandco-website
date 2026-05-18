<?php
/**
 * Config — простая обёртка над .env с поддержкой ${VAR} интерполяции.
 * Используется фичами OAuth / Push / Mail.
 */

final class Config
{
    private static array $cache = [];

    public static function get(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        $raw = getenv($key);
        if ($raw === false || $raw === '') {
            return self::$cache[$key] = $default;
        }

        // Поддержка ${OTHER_VAR} внутри значений
        $resolved = preg_replace_callback('/\$\{([A-Z0-9_]+)\}/', function ($m) {
            return self::get($m[1], '') ?? '';
        }, $raw);

        return self::$cache[$key] = $resolved;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);
        if ($value === null) {
            return $default;
        }
        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    public static function isProd(): bool
    {
        return self::get('APP_ENV', 'development') === 'production';
    }

    public static function appUrl(): string
    {
        $url = self::get('APP_URL');
        if ($url) {
            return rtrim($url, '/');
        }
        $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $proto . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
}
