<?php
/**
 * Public Price Analytics — Elsesser & Co.
 * Аналитика цен по районам Екатеринбурга, графики, тренды.
 */

require_once __DIR__ . '/includes/config/database.php';
require_once __DIR__ . '/includes/auth/check_auth.php';

$user = getUserData();
$pdo  = getDBConnection();
$category = ($_GET['category'] ?? 'sale') === 'rent' ? 'rent' : 'sale';

// Цены по районам (используем area_total с фолбеком на area_sqft)
$stmt = $pdo->prepare("
    SELECT
        d.id,
        d.name AS district,
        COUNT(p.id) AS total,
        ROUND(AVG(p.price))                                                 AS avg_price,
        ROUND(AVG(p.price / NULLIF(COALESCE(p.area_total, p.area_sqft), 0))) AS avg_price_sqm,
        ROUND(MIN(p.price))                                                 AS min_price,
        ROUND(MAX(p.price))                                                 AS max_price
    FROM ekb_districts d
    LEFT JOIN properties p
           ON p.district_id = d.id
          AND p.status = 'available'
          AND p.category = ?
    GROUP BY d.id, d.name
    HAVING total > 0
    ORDER BY avg_price_sqm DESC
");
$stmt->execute([$category]);
$rows = $stmt->fetchAll();

// Тренд за 12 месяцев (среднее объявленной цены за м² по дате создания)
$stmt = $pdo->prepare("
    SELECT
        DATE_FORMAT(created_at, '%Y-%m') AS month,
        ROUND(AVG(price / NULLIF(COALESCE(area_total, area_sqft), 0))) AS price_sqm,
        COUNT(*) AS listings
    FROM properties
    WHERE category = ?
      AND created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY month
    ORDER BY month ASC
");
$stmt->execute([$category]);
$trend = $stmt->fetchAll();

// Цены по комнатам
$stmt = $pdo->prepare("
    SELECT bedrooms,
           COUNT(*) AS cnt,
           ROUND(AVG(price)) AS avg_price,
           ROUND(AVG(price / NULLIF(COALESCE(area_total, area_sqft), 0))) AS avg_price_sqm
    FROM properties
    WHERE status = 'available' AND category = ?
    GROUP BY bedrooms
    ORDER BY bedrooms
");
$stmt->execute([$category]);
$byRooms = $stmt->fetchAll();

$totalAvgSqm = 0;
$totalCount = 0;
foreach ($rows as $r) { $totalAvgSqm += (int)$r['avg_price_sqm'] * (int)$r['total']; $totalCount += (int)$r['total']; }
$cityAvg = $totalCount > 0 ? round($totalAvgSqm / $totalCount) : 0;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Аналитика цен по районам Екатеринбурга | Elsesser & Co.</title>
    <meta name="description" content="Средние цены за м², статистика и тренды по районам Екатеринбурга. <?= $category === 'rent' ? 'Аренда' : 'Покупка' ?>.">
    <link rel="canonical" href="<?= getBaseUrl() ?>/analytics.php?category=<?= $category ?>">

    <link rel="icon" type="image/png" href="images/favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="css/reset.css">
    <link rel="stylesheet" href="css/variables.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/responsive.css">
    <style>
        .analytics-hero { background: linear-gradient(135deg, #1a2447 0%, #00736c 100%); color: #fff; padding: calc(var(--header-height) + 48px) 0 40px; }
        .analytics-hero__title { font-family: var(--font-heading); font-size: var(--text-4xl); margin-bottom: 8px; }
        .analytics-hero__subtitle { opacity: .9; max-width: 720px; }
        .analytics-tabs { display: inline-flex; background: rgba(255,255,255,.12); border-radius: 999px; padding: 4px; margin-top: 24px; }
        .analytics-tabs a { padding: 8px 24px; border-radius: 999px; color: #fff; text-decoration: none; font-weight: 500; }
        .analytics-tabs a.active { background: #fff; color: var(--color-accent); }
        .analytics-stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-top: 24px; }
        .analytics-stat-card { background: rgba(255,255,255,.10); border: 1px solid rgba(255,255,255,.18); border-radius: 12px; padding: 16px 20px; }
        .analytics-stat-card__label { opacity: .75; font-size: var(--text-sm); }
        .analytics-stat-card__value { font-size: var(--text-3xl); font-weight: 700; margin-top: 4px; }
        .analytics-section { padding: 48px 0; }
        .analytics-section h2 { font-family: var(--font-heading); font-size: var(--text-3xl); margin-bottom: 24px; }
        .district-row { display: grid; grid-template-columns: minmax(160px, 1.5fr) minmax(110px, 1fr) minmax(110px, 1fr) minmax(150px, 1fr) 80px; gap: 16px; align-items: center; padding: 12px 16px; border-bottom: 1px solid var(--color-border); }
        .district-row--head { font-weight: 600; color: var(--color-text-light); font-size: var(--text-xs); text-transform: uppercase; letter-spacing: .04em; border-bottom: 2px solid var(--color-border); }
        .district-bar { height: 8px; background: var(--color-light-gray); border-radius: 4px; overflow: hidden; margin-top: 4px; }
        .district-bar__fill { height: 100%; background: linear-gradient(90deg, var(--color-accent), var(--color-cta)); }
        .chart-wrap { background: #fff; border-radius: 16px; padding: 24px; box-shadow: var(--shadow-md); overflow-x: auto; }
        canvas { max-width: 100%; height: 320px !important; }
        @media (max-width: 768px) {
            .district-row { grid-template-columns: 1fr 1fr; row-gap: 4px; }
            .district-row--head { display: none; }
        }
    </style>
</head>
<body>
<header class="header header--solid">
    <div class="container">
        <div class="header__inner">
            <a href="index.php" class="header__logo"><span class="header__logo-text">Elsesser & Co.</span></a>
            <nav class="nav">
                <ul class="nav__list">
                    <li><a href="properties.php?category=sale" class="nav__link">Купить</a></li>
                    <li><a href="properties.php?category=rent" class="nav__link">Аренда</a></li>
                    <li><a href="new-buildings.php" class="nav__link">Новостройки</a></li>
                    <li><a href="analytics.php" class="nav__link nav__link--active">Аналитика</a></li>
                </ul>
                <?php if ($user['logged_in']): ?>
                    <a href="dashboard.php" class="btn btn--secondary"><?= escape($user['name']) ?></a>
                <?php else: ?>
                    <a href="login.php" class="btn btn--secondary">Войти</a>
                <?php endif; ?>
            </nav>
        </div>
    </div>
</header>

<section class="analytics-hero">
    <div class="container">
        <h1 class="analytics-hero__title">Аналитика рынка недвижимости Екатеринбурга</h1>
        <p class="analytics-hero__subtitle">
            Средние цены по районам, тренды за год и распределение по типам жилья. Данные обновляются автоматически по нашему каталогу.
        </p>
        <div class="analytics-tabs">
            <a href="?category=sale" class="<?= $category === 'sale' ? 'active' : '' ?>">Покупка</a>
            <a href="?category=rent" class="<?= $category === 'rent' ? 'active' : '' ?>">Аренда</a>
        </div>
        <div class="analytics-stat-grid">
            <div class="analytics-stat-card">
                <div class="analytics-stat-card__label">Средняя цена за м²</div>
                <div class="analytics-stat-card__value"><?= number_format($cityAvg, 0, '.', ' ') ?> ₽</div>
            </div>
            <div class="analytics-stat-card">
                <div class="analytics-stat-card__label">Объектов в выборке</div>
                <div class="analytics-stat-card__value"><?= number_format($totalCount, 0, '.', ' ') ?></div>
            </div>
            <div class="analytics-stat-card">
                <div class="analytics-stat-card__label">Районов с данными</div>
                <div class="analytics-stat-card__value"><?= count($rows) ?></div>
            </div>
        </div>
    </div>
</section>

<section class="analytics-section">
    <div class="container">
        <h2>Цены по районам</h2>
        <div class="chart-wrap">
            <?php
                $maxSqm = 1;
                foreach ($rows as $r) { $maxSqm = max($maxSqm, (int)$r['avg_price_sqm']); }
            ?>
            <div class="district-row district-row--head">
                <div>Район</div><div>Цена за м²</div><div>Средняя цена</div><div>Диапазон</div><div>Объектов</div>
            </div>
            <?php foreach ($rows as $r): $fill = round(((int)$r['avg_price_sqm'] / $maxSqm) * 100); ?>
            <div class="district-row">
                <div><strong><?= escape($r['district']) ?></strong>
                    <div class="district-bar"><div class="district-bar__fill" style="width: <?= $fill ?>%"></div></div>
                </div>
                <div><?= number_format((int)$r['avg_price_sqm'], 0, '.', ' ') ?> ₽/м²</div>
                <div><?= number_format((int)$r['avg_price'], 0, '.', ' ') ?> ₽</div>
                <div style="color:var(--color-text-light);font-size:var(--text-sm);">
                    <?= number_format((int)$r['min_price'], 0, '.', ' ') ?>–<?= number_format((int)$r['max_price'], 0, '.', ' ') ?>
                </div>
                <div><?= (int)$r['total'] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php if ($trend): ?>
<section class="analytics-section" style="background:var(--color-light-gray);">
    <div class="container">
        <h2>Тренд за 12 месяцев</h2>
        <div class="chart-wrap">
            <canvas id="trendChart"></canvas>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($byRooms): ?>
<section class="analytics-section">
    <div class="container">
        <h2>Распределение по количеству комнат</h2>
        <div class="chart-wrap">
            <canvas id="roomsChart"></canvas>
        </div>
    </div>
</section>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    if (!window.Chart) {
        // подождём подгрузки Chart.js
        const id = setInterval(() => { if (window.Chart) { clearInterval(id); init(); } }, 50);
    } else { init(); }

    function init() {
        <?php if ($trend): ?>
        new Chart(document.getElementById('trendChart'), {
            type: 'line',
            data: {
                labels: <?= json_encode(array_column($trend, 'month')) ?>,
                datasets: [{
                    label: 'Средняя цена за м², ₽',
                    data: <?= json_encode(array_map('intval', array_column($trend, 'price_sqm'))) ?>,
                    borderColor: '#00736c',
                    backgroundColor: 'rgba(0,115,108,0.12)',
                    tension: 0.3,
                    fill: true
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });
        <?php endif; ?>

        <?php if ($byRooms): ?>
        new Chart(document.getElementById('roomsChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_map(fn($r) => $r['bedrooms'] == 0 ? 'Студия' : ($r['bedrooms'] . '-комн.'), $byRooms)) ?>,
                datasets: [
                    { label: 'Средняя цена, ₽',  data: <?= json_encode(array_map('intval', array_column($byRooms, 'avg_price'))) ?>, backgroundColor: '#00736c', yAxisID: 'y' },
                    { label: 'Объектов',          data: <?= json_encode(array_map('intval', array_column($byRooms, 'cnt'))) ?>, backgroundColor: '#d97644', yAxisID: 'y1' }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                scales: {
                    y:  { type: 'linear', position: 'left' },
                    y1: { type: 'linear', position: 'right', grid: { display: false } }
                }
            }
        });
        <?php endif; ?>
    }
});
</script>
</body>
</html>
