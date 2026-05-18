<?php
/**
 * test_db.php - диагностика env-конфига и подключения к БД.
 */

echo PHP_SAPI === 'cli' ? '' : '<pre>';

$configFile = __DIR__ . '/includes/config/database.php';
$configSource = file_get_contents($configFile);

if ($configSource === false) {
    fail('Не удалось прочитать includes/config/database.php');
}

if (preg_match('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', $configSource)) {
    fail('В DB-конфиге остался захардкоженный IP-адрес');
}

require_once $configFile;

$requiredConstants = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_CHARSET'];
foreach ($requiredConstants as $constant) {
    if (!defined($constant)) {
        fail("Не определена константа {$constant}");
    }
}

echo "OK: DB config загружен\n";
echo "DB_HOST=" . maskValue(DB_HOST) . "\n";
echo "DB_NAME=" . DB_NAME . "\n";
echo "DB_USER=" . maskValue(DB_USER) . "\n";

try {
    $pdo = getDBConnection();
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo "OK: подключение к БД успешно\n";
    echo "Таблиц в базе: " . count($tables) . "\n";
} catch (Throwable $e) {
    fail('Ошибка подключения к БД: ' . $e->getMessage());
}

echo PHP_SAPI === 'cli' ? '' : '</pre>';

function fail(string $message): void
{
    http_response_code(500);
    echo "ERROR: {$message}\n";
    echo PHP_SAPI === 'cli' ? '' : '</pre>';
    exit(1);
}

function maskValue(string $value): string
{
    if ($value === '') {
        return '(empty)';
    }

    return substr($value, 0, 1) . str_repeat('*', max(strlen($value) - 2, 1)) . substr($value, -1);
}
