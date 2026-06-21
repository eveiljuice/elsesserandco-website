<?php
/**
 * Cron: php /var/www/elsesserandco-site.local/cron/saved_search_alerts.php
 * Schedule example: every 15 minutes -> "*/15 * * * * deploy"
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

require_once __DIR__ . '/../includes/config/database.php';
require_once __DIR__ . '/../includes/email/Mailer.php';
require_once __DIR__ . '/../includes/saved_searches/SavedSearchService.php';

$pdo = getDBConnection();
$appUrl = rtrim(Config::appUrl(), '/');

$searches = $pdo->query("
    SELECT ss.*, u.email, u.first_name
    FROM saved_searches ss
    JOIN users u ON u.id = ss.user_id
    WHERE ss.notify_email = 1 AND u.is_active = 1
")->fetchAll(PDO::FETCH_ASSOC);

$sentTotal = 0;

foreach ($searches as $ss) {
    $matches = SavedSearchService::findNewMatches($pdo, $ss, 15);
    if ($matches === []) {
        continue;
    }

    $lines = [];
    foreach ($matches as $p) {
        $id = (int)$p['id'];
        $slug = $p['slug'] ?? '';
        $path = $slug ? "/property/{$id}-{$slug}" : "/property.php?id={$id}";
        $price = number_format((float)$p['price'], 0, '.', ' ');
        $lines[] = '<li><a href="' . htmlspecialchars($appUrl . $path) . '">'
            . htmlspecialchars((string)$p['title_ru']) . '</a> — ' . $price . ' ₽</li>';

        $ins = $pdo->prepare("INSERT IGNORE INTO saved_search_sent (saved_search_id, property_id) VALUES (?, ?)");
        $ins->execute([(int)$ss['id'], $id]);
    }

    $html = '<p>Здравствуйте, ' . htmlspecialchars((string)$ss['first_name']) . '!</p>'
        . '<p>Новые объекты по сохранённому поиску «' . htmlspecialchars((string)$ss['name']) . '»:</p>'
        . '<ul>' . implode('', $lines) . '</ul>'
        . '<p><a href="' . htmlspecialchars($appUrl . '/properties.php?' . http_build_query(json_decode((string)$ss['filters_json'], true) ?: [])) . '">Открыть каталог</a></p>';

    if (Mailer::send((string)$ss['email'], 'Новые объекты — ' . $ss['name'], $html)) {
        $upd = $pdo->prepare("UPDATE saved_searches SET last_notified_at = NOW(), last_match_count = ? WHERE id = ?");
        $upd->execute([count($matches), (int)$ss['id']]);
        $sentTotal++;
    }
}

echo date('c') . " Alerts sent: {$sentTotal}\n";
