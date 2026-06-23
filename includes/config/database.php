<?php
/**
 * Database Configuration - Elsesser & Co.
 * PDO подключение к MySQL
 */

loadEnvironmentFile(__DIR__ . '/../../.env.local');
loadEnvironmentFile(__DIR__ . '/../../.env');

require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/../image.php';

define('DB_HOST', getEnvironmentValue('REALESTATE_DB_HOST', 'localhost'));
define('DB_NAME', getEnvironmentValue('REALESTATE_DB_NAME', 'realestate_db'));
define('DB_USER', getEnvironmentValue('REALESTATE_DB_USER', 'root'));
define('DB_PASS', getEnvironmentValue('REALESTATE_DB_PASS', ''));
define('DB_CHARSET', getEnvironmentValue('REALESTATE_DB_CHARSET', 'utf8mb4'));

/**
 * Загрузить локальные переменные окружения без Composer-зависимостей.
 */
function loadEnvironmentFile(string $path): void {
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if ($key === '' || getenv($key) !== false) {
            continue;
        }

        $isQuoted = (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"));
        if (!$isQuoted && str_contains($value, '#')) {
            $value = trim(explode('#', $value, 2)[0]);
        }
        $value = trim($value, "\"'");
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

/**
 * Получить значение окружения с безопасным fallback.
 */
function getEnvironmentValue(string $key, string $default = ''): string {
    $value = getenv($key);

    return $value === false ? $default : $value;
}

/**
 * Получить PDO соединение с базой
 * @return PDO
 */
function getDBConnection(): PDO {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = sprintf(
                "mysql:host=%s;dbname=%s;charset=%s",
                DB_HOST,
                DB_NAME,
                DB_CHARSET
            );
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
        } catch (PDOException $e) {
            // В продакшене логировать ошибку, не показывать детали
            error_log("Database connection failed: " . $e->getMessage());
            die("Ошибка подключения к базе данных. Пожалуйста, попробуйте позже.");
        }
    }
    
    return $pdo;
}

/**
 * Безопасное форматирование вывода
 * @param string|null $value
 * @return string
 */
function escape($value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Форматирование цены
 * @param float $price
 * @param string $currency
 * @return string
 */
function formatPrice(float $price, string $currency = 'RUB'): string {
    return number_format($price, 0, '.', ' ') . ' ₽';
}

/**
 * Получить URL превью изображения
 * @param string|null $originalUrl
 * @param string $size '400x300' или '800x600'
 * @return string|null
 */
function getImageThumb(?string $originalUrl, string $size = '400x300'): ?string {
    if (empty($originalUrl)) return null;
    $base = pathinfo($originalUrl, PATHINFO_FILENAME);
    return str_replace('/uploads/properties/', '/uploads/properties/thumbs/', dirname($originalUrl))
        . '/' . $base . '_' . $size . '.webp';
}

/**
 * Получить базовый URL сайта
 * @return string
 */
function getBaseUrl(): string {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . '://' . $host;
}

// Закомментировано: классы FileCache и PropertyRepository пока не реализованы.
// Когда появятся файлы includes/cache/FileCache.php и includes/repository/PropertyRepository.php
// — раскомментировать соответствующие хелперы.
