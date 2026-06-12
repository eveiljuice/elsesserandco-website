<?php
/**
 * Search Autocomplete API - Elsesser & Co.
 * Возвращает результаты поиска для автокомплита
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache');

require_once __DIR__ . '/../../includes/config/database.php';

/**
 * Превращает запрос пользователя в boolean-строку для MATCH AGAINST.
 *  - токены короче 3 символов выкидываются (ft_min_token_len)
 *  - каждому токену добавляется wildcard '*' на конце для префиксного поиска
 *  - поддерживается минус-нотация: "-слово" исключает совпадения
 *  - фразы в кавычках сохраняются как есть
 */
function autocompleteBuildBooleanQuery(string $query): string
{
    $tokens = preg_split('/\s+/u', $query, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $out = [];
    foreach ($tokens as $tok) {
        $tok = trim($tok);
        if ($tok === '') continue;
        $isNegative = $tok[0] === '-';
        $word = $isNegative ? substr($tok, 1) : $tok;
        if (strlen($word) >= 2 && $word[0] === '"' && substr($word, -1) === '"') {
            $out[] = ($isNegative ? '-' : '') . $word;
            continue;
        }
        if (mb_strlen($word) < 3) continue;
        $wordSafe = preg_replace('/[+\-><()~*"@]+/u', ' ', $word);
        $wordSafe = trim($wordSafe);
        if ($wordSafe === '') continue;
        $out[] = ($isNegative ? '-' : '+') . $wordSafe . '*';
    }
    return $out ? implode(' ', $out) : $query;
}

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

    // FULLTEXT (если хотя бы один токен ≥ 3 символов), иначе — LIKE
    $tokens = preg_split('/\s+/u', $query, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $hasLongToken = false;
    foreach ($tokens as $t) {
        if (mb_strlen($t) >= 3) { $hasLongToken = true; break; }
    }
    $useFulltext = $hasLongToken && mb_strlen($query) >= 3;

    if ($useFulltext) {
        $boolQuery = autocompleteBuildBooleanQuery($query);

        $sql = "
            SELECT
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
                d.name as district_name,
                (SELECT image_url FROM property_images WHERE property_id = p.id AND is_primary = 1 LIMIT 1) as image,
                'property' as result_type,
                MATCH(p.title_ru, p.street, p.location, p.building_name, p.description_ru, p.title, p.description)
                    AGAINST (? IN BOOLEAN MODE) AS rel
            FROM properties p
            LEFT JOIN ekb_districts d ON p.district_id = d.id
            WHERE p.status = 'available'
              AND p.category = ?
              AND MATCH(p.title_ru, p.street, p.location, p.building_name, p.description_ru, p.title, p.description)
                    AGAINST (? IN BOOLEAN MODE)
            ORDER BY rel DESC, p.featured DESC, p.created_at DESC
            LIMIT ?
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$boolQuery, $category, $boolQuery, $limit]);
    } else {
        $searchTerm = '%' . $query . '%';

        $sql = "
            SELECT
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
                d.name as district_name,
                (SELECT image_url FROM property_images WHERE property_id = p.id AND is_primary = 1 LIMIT 1) as image,
                'property' as result_type
            FROM properties p
            LEFT JOIN ekb_districts d ON p.district_id = d.id
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
              )
            ORDER BY p.featured DESC, p.created_at DESC
            LIMIT ?
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $category,
            $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm,
            $searchTerm, $searchTerm,
            $limit
        ]);
    }

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
    
    // Поиск по районам (FULLTEXT если токены длинные, иначе LIKE)
    if (count($results) < $limit) {
        if ($useFulltext) {
            $boolQuery = autocompleteBuildBooleanQuery($query);
            $stmt = $pdo->prepare("
                SELECT id, name AS name,
                       (SELECT COUNT(*) FROM properties WHERE district_id = ekb_districts.id AND status = 'available' AND category = ?) as count
                FROM ekb_districts
                WHERE MATCH(name) AGAINST (? IN BOOLEAN MODE)
                ORDER BY sort_order
                LIMIT 3
            ");
            $stmt->execute([$category, $boolQuery]);
        } else {
            $searchTerm = '%' . $query . '%';
            $stmt = $pdo->prepare("
                SELECT id, name AS name,
                       (SELECT COUNT(*) FROM properties WHERE district_id = ekb_districts.id AND status = 'available' AND category = ?) as count
                FROM ekb_districts
                WHERE name LIKE ?
                ORDER BY sort_order
                LIMIT 3
            ");
            $stmt->execute([$category, $searchTerm]);
        }
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

    // Поиск по новостройкам (FULLTEXT если токены длинные, иначе LIKE)
    if ($useFulltext) {
        $boolQuery = autocompleteBuildBooleanQuery($query);
        $stmt = $pdo->prepare("
            SELECT nb.id, nb.name, nb.address, nb.price_from,
                   nbi.image_url as image,
                   dev.name as developer_name
            FROM new_buildings nb
            LEFT JOIN new_building_images nbi ON nb.id = nbi.new_building_id AND nbi.is_primary = 1
            LEFT JOIN developers dev ON nb.developer_id = dev.id
            WHERE nb.status = 'active'
              AND MATCH(nb.name, nb.address, nb.description) AGAINST (? IN BOOLEAN MODE)
            ORDER BY nb.featured DESC
            LIMIT 3
        ");
        $stmt->execute([$boolQuery]);
    } else {
        $searchTerm = '%' . $query . '%';
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
    }
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
    error_log('[autocomplete] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    echo json_encode([
        'success' => false,
        'error' => 'Ошибка поиска',
        'results' => []
    ]);
}

