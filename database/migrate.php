<?php
/**
 * Простой миграционный runner.
 *
 * Запуск:
 *   php database/migrate.php          — применить все непрокаченные миграции
 *   php database/migrate.php --status — показать статус (что применено, что нет)
 *   php database/migrate.php --mark <name.sql>   — пометить миграцию применённой
 *                                                   без выполнения (для первого
 *                                                   запуска на проде, где уже
 *                                                   накатано вручную)
 *
 * Миграции лежат в database/migrations/*.sql и применяются в алфавитном
 * порядке. Имя файла = первичный ключ в таблице `migrations`.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Run from CLI only\n");
}

require_once __DIR__ . '/../includes/config/database.php';

global $pdo;

$pdo->exec("CREATE TABLE IF NOT EXISTS `migrations` (
    `name` VARCHAR(255) NOT NULL PRIMARY KEY,
    `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

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
        $pdo->beginTransaction();
        $pdo->exec($sql);
        $stmt = $pdo->prepare("INSERT INTO migrations (name) VALUES (?)");
        $stmt->execute([$name]);
        $pdo->commit();
        echo "OK\n";
        $applied_count++;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
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
