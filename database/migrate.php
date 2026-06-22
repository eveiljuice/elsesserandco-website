<?php
/**
 * Миграционный runner (MySQL 8.0).
 * DDL выполняется по одному statement; дубликаты колонок/индексов/таблиц пропускаются.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Run from CLI only\n");
}

require_once __DIR__ . '/../includes/config/database.php';

$pdo = getDBConnection();

$pdo->exec("CREATE TABLE IF NOT EXISTS `migrations` (
    `name` VARCHAR(255) NOT NULL PRIMARY KEY,
    `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

/** @return list<string> */
function migrationSplitStatements(string $sql): array
{
    $sql = preg_replace('/--[^\n]*\n/', "\n", $sql) ?? $sql;
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql) ?? $sql;
    $parts = explode(';', $sql);
    $out = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }
        if (preg_match('/^USE\s+/i', $part)) {
            continue;
        }
        $out[] = $part;
    }
    return $out;
}

function migrationIgnorableError(PDOException $e): bool
{
    $code = (int)($e->errorInfo[1] ?? 0);
    // 1050 table exists, 1060 duplicate column, 1061 duplicate key name, 1062 duplicate entry
    return in_array($code, [1050, 1060, 1061, 1062], true);
}

function migrationApplySql(PDO $pdo, string $sql): void
{
    foreach (migrationSplitStatements($sql) as $statement) {
        try {
            $pdo->exec($statement);
        } catch (PDOException $e) {
            if (!migrationIgnorableError($e)) {
                throw $e;
            }
        }
    }
}

$args = $argv;
array_shift($args);
$cmd = $args[0] ?? 'apply';

$applied = array_flip(
    $pdo->query("SELECT name FROM migrations")->fetchAll(PDO::FETCH_COLUMN)
);

$files = glob(__DIR__ . '/migrations/*.sql') ?: [];
sort($files);

if ($cmd === '--status' || $cmd === 'status') {
    echo "Migrations status:\n";
    foreach ($files as $file) {
        $name = basename($file);
        $mark = isset($applied[$name]) ? '[x]' : '[ ]';
        echo "  {$mark} {$name}\n";
    }
    exit(0);
}

if ($cmd === '--mark' || $cmd === 'mark') {
    $target = $args[1] ?? null;
    if (!$target) {
        fwrite(STDERR, "Usage: php migrate.php --mark <filename.sql>\n");
        exit(2);
    }
    if (!file_exists(__DIR__ . '/migrations/' . $target)) {
        fwrite(STDERR, "File not found: database/migrations/{$target}\n");
        exit(2);
    }
    $stmt = $pdo->prepare("INSERT IGNORE INTO migrations (name) VALUES (?)");
    $stmt->execute([$target]);
    echo "Marked as applied: {$target}\n";
    exit(0);
}

$applied_count = 0;
foreach ($files as $file) {
    $name = basename($file);
    if (isset($applied[$name])) {
        continue;
    }

    echo "Applying {$name} ... ";
    $sql = file_get_contents($file);
    if ($sql === false || trim($sql) === '') {
        echo "SKIP (empty)\n";
        continue;
    }

    try {
        migrationApplySql($pdo, $sql);
        $stmt = $pdo->prepare("INSERT INTO migrations (name) VALUES (?)");
        $stmt->execute([$name]);
        echo "OK\n";
        $applied_count++;
    } catch (Throwable $e) {
        echo "FAIL\n";
        fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
        exit(1);
    }
}

if ($applied_count === 0) {
    echo "Nothing to migrate. All up to date.\n";
} else {
    echo "Applied {$applied_count} migration(s).\n";
}
