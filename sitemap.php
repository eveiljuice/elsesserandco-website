<?php
/**
 * sitemap.php — динамическая генерация sitemap.xml.
 *
 * .htaccess уже включает mod_rewrite — добавьте правило:
 *   RewriteRule ^sitemap\.xml$ /sitemap.php [L]
 *
 * Или поставьте крону:
 *   php sitemap.php > sitemap.xml
 */

require_once __DIR__ . '/includes/config/database.php';

$pdo = getDBConnection();
$base = getBaseUrl();

$urls = [
    ['loc' => $base . '/',                              'priority' => '1.0', 'changefreq' => 'daily'],
    ['loc' => $base . '/properties.php?category=sale',  'priority' => '0.9', 'changefreq' => 'daily'],
    ['loc' => $base . '/properties.php?category=rent',  'priority' => '0.9', 'changefreq' => 'daily'],
    ['loc' => $base . '/new-buildings.php',             'priority' => '0.9', 'changefreq' => 'daily'],
    ['loc' => $base . '/about.php',                    'priority' => '0.5', 'changefreq' => 'monthly'],
    ['loc' => $base . '/contact.php',                  'priority' => '0.5', 'changefreq' => 'monthly'],
];

// Properties
try {
    $stmt = $pdo->query("
        SELECT id, updated_at
        FROM properties
        WHERE status = 'available'
        ORDER BY updated_at DESC
        LIMIT 5000
    ");
    foreach ($stmt as $row) {
        $urls[] = [
            'loc'     => $base . '/property.php?id=' . (int)$row['id'],
            'lastmod' => substr((string)$row['updated_at'], 0, 10),
            'priority'   => '0.8',
            'changefreq' => 'weekly',
        ];
    }
} catch (Throwable $e) { error_log('sitemap props: ' . $e->getMessage()); }

// New buildings
try {
    $stmt = $pdo->query("
        SELECT id, COALESCE(updated_at, created_at) AS updated_at
        FROM new_buildings
        WHERE status = 'active'
        ORDER BY updated_at DESC
        LIMIT 2000
    ");
    foreach ($stmt as $row) {
        $urls[] = [
            'loc'     => $base . '/new-building.php?id=' . (int)$row['id'],
            'lastmod' => substr((string)$row['updated_at'], 0, 10),
            'priority'   => '0.7',
            'changefreq' => 'weekly',
        ];
    }
} catch (Throwable $e) { /* таблицы может не быть */ }

header('Content-Type: application/xml; charset=UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo "  <url>\n";
    echo '    <loc>' . htmlspecialchars($u['loc'], ENT_XML1) . "</loc>\n";
    if (!empty($u['lastmod']))    echo '    <lastmod>'    . $u['lastmod']    . "</lastmod>\n";
    if (!empty($u['changefreq'])) echo '    <changefreq>' . $u['changefreq'] . "</changefreq>\n";
    if (!empty($u['priority']))   echo '    <priority>'   . $u['priority']   . "</priority>\n";
    echo "  </url>\n";
}
echo '</urlset>' . "\n";
