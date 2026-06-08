<?php
/**
 * Search Autocomplete API - Elsesser & Co.
 * Возвращает результаты поиска для автокомплита
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache');

require_once __DIR__ . '/../../includes/config/database.php';

$query = trim($_GET['q'] ?? '');
$type = $_GET['type'] ?? 'sale'; // sale or rent
$limit = min(10, max(1, (int)($_GET['limit'] ?? 8)));

if (mb_strlen($query) < 2) {
    echo json_encode(['results' => [], 'query' => $query]);
    exit;
}

$pdo = getDBConnection();
$results = [];

try {
    // Категория для фильтрации
    $category = ($type === 'rent') ? 'rent' : 'sale';
    
    // Поиск по properties
    $searchTerm = '%' . $query . '%';
    
    $sql = "
        SELECT DISTINCT
            p.id,
            p.title_ru as title,
            p.price,
            p.bedrooms,
            p.category,
            p.area_total,
            p.area_sqft,
            p.street,
            p.location,
            p.district_id,
            COALESCE(d.name_ru, d.name) as district_name,
            pi.image_url as image,
            'property' as result_type
        FROM properties p
        LEFT JOIN ekb_districts d ON p.district_id = d.id
        LEFT JOIN property_images pi ON p.id = pi.property_id AND pi.is_primary = 1
        WHERE p.status = 'available'
          AND p.category = ?
          AND (
              p.title_ru LIKE ?
              OR p.title LIKE ?
              OR p.description_ru LIKE ?
              OR p.street LIKE ?
              OR p.location LIKE ?
              OR p.building_name LIKE ?
              OR d.name LIKE ?
              OR d.name_ru LIKE ?
          )
        ORDER BY p.featured DESC, p.created_at DESC
        LIMIT ?
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $category,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $limit
    ]);
    
    $properties = $stmt->fetchAll();
    
    foreach ($properties as $p) {
        $area = $p['area_total'] ?? $p['area_sqft'];
        $roomsText = match((int)$p['bedrooms']) {
            0 => 'Студия',
            1 => '1-комн.',
            2 => '2-комн.',
            3 => '3-комн.',
            default => $p['bedrooms'] . '-комн.'
        };
        
        $results[] = [
            'id' => $p['id'],
            'type' => 'property',
            'title' => $p['title'] ?: ($roomsText . ', ' . number_format($area, 1) . ' м²'),
            'subtitle' => $p['street'] ?: $p['location'] ?: $p['district_name'],
            'price' => formatPrice($p['price']),
            'image' => $p['image'] ?? null,
            'url' => '/property.php?id=' . $p['id'],
            'category' => $p['category']
        ];
    }
    
    // Если мало результатов, добавляем поиск по районам
    if (count($results) < $limit) {
        $stmt = $pdo->prepare("
            SELECT id, COALESCE(name_ru, name) AS name,
                   (SELECT COUNT(*) FROM properties WHERE district_id = ekb_districts.id AND status = 'available' AND category = ?) as count
            FROM ekb_districts 
            WHERE name LIKE ?
            ORDER BY sort_order
            LIMIT 3
        ");
        $stmt->execute([$category, $searchTerm]);
        $districts = $stmt->fetchAll();
        
        foreach ($districts as $d) {
            if ($d['count'] > 0) {
                $results[] = [
                    'id' => $d['id'],
                    'type' => 'district',
                    'title' => 'Район: ' . $d['name'],
                    'subtitle' => $d['count'] . ' объектов',
                    'url' => '/properties.php?category=' . $category . '&district=' . $d['id'],
                    'icon' => 'map-marker-alt'
                ];
            }
        }
    }
    
    // Поиск по новостройкам (если запрос похож на название ЖК)
    $stmt = $pdo->prepare("
        SELECT nb.id, nb.name, nb.address, nb.price_from,
               nbi.image_url as image,
               dev.name as developer_name
        FROM new_buildings nb
        LEFT JOIN new_building_images nbi ON nb.id = nbi.new_building_id AND nbi.is_primary = 1
        LEFT JOIN developers dev ON nb.developer_id = dev.id
        WHERE nb.status = 'active'
          AND (nb.name LIKE ? OR nb.address LIKE ? OR dev.name LIKE ?)
        ORDER BY nb.featured DESC
        LIMIT 3
    ");
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $newBuildings = $stmt->fetchAll();
    
    foreach ($newBuildings as $nb) {
        $results[] = [
            'id' => $nb['id'],
            'type' => 'new_building',
            'title' => 'ЖК ' . $nb['name'],
            'subtitle' => $nb['developer_name'] ? ('Застройщик: ' . $nb['developer_name']) : $nb['address'],
            'price' => $nb['price_from'] ? ('от ' . formatPrice($nb['price_from'])) : null,
            'image' => $nb['image'] ?? null,
            'url' => '/new-building.php?id=' . $nb['id'],
            'icon' => 'building'
        ];
    }
    
    // Лимитируем общее количество
    $results = array_slice($results, 0, $limit);
    
    echo json_encode([
        'success' => true,
        'query' => $query,
        'results' => $results,
        'total' => count($results)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Ошибка поиска',
        'results' => []
    ]);
}

