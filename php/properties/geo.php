<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../includes/config/database.php';
require_once __DIR__ . '/../../includes/properties/catalog_query.php';

$filters = catalogParseFilters($_GET);
$whereSql = implode(' AND ', $filters['where']);
$params = $filters['params'];

$sql = "
    SELECT p.id, p.title_ru, p.price, p.latitude, p.longitude, p.slug, p.category,
           pi.image_url AS primary_image
    FROM properties p
    LEFT JOIN property_images pi ON p.id = pi.property_id AND pi.is_primary = 1
    WHERE {$whereSql}
      AND p.latitude IS NOT NULL AND p.longitude IS NOT NULL
    LIMIT 500
";
$stmt = getDBConnection()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$features = [];
foreach ($rows as $r) {
    $features[] = [
        'type'       => 'Feature',
        'id'         => (int)$r['id'],
        'geometry'   => ['type' => 'Point', 'coordinates' => [(float)$r['longitude'], (float)$r['latitude']]],
        'properties' => [
            'id'    => (int)$r['id'],
            'title' => $r['title_ru'],
            'price' => (float)$r['price'],
            'image' => $r['primary_image'],
            'url'   => !empty($r['slug']) ? '/property/' . $r['id'] . '-' . $r['slug'] : '/property.php?id=' . $r['id'],
        ],
    ];
}

echo json_encode(['type' => 'FeatureCollection', 'features' => $features], JSON_UNESCAPED_UNICODE);
