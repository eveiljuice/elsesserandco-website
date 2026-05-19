<?php
/**
 * Общая логика фильтрации каталога properties.php и AJAX-фрагмента.
 */

declare(strict_types=1);

/**
 * @return array{where: string[], params: array, category: string, sortBy: string, page: int, perPage: int, offset: int}
 */
function catalogParseFilters(array $get): array
{
    $category = $get['category'] ?? 'sale';
    if (!in_array($category, ['sale', 'rent'], true)) {
        $category = 'sale';
    }

    $page = max(1, (int)($get['page'] ?? 1));
    $perPage = min(50, max(1, (int)($get['per_page'] ?? 20)));
    $offset = ($page - 1) * $perPage;

    $where = ["p.status = 'available'", "p.category = ?"];
    $params = [$category];

    $districtId = (int)($get['district'] ?? 0);
    if ($districtId > 0) {
        $where[] = 'p.district_id = ?';
        $params[] = $districtId;
    }

    $rooms = $get['rooms'] ?? '';
    if ($rooms !== '' && $rooms !== 'any') {
        if ($rooms === '4+') {
            $where[] = 'p.bedrooms >= 4';
        } else {
            $where[] = 'p.bedrooms = ?';
            $params[] = (int)$rooms;
        }
    }

    foreach (['min_price' => '>=', 'max_price' => '<='] as $key => $op) {
        $val = (int)($get[$key] ?? 0);
        if ($val > 0) {
            $where[] = "p.price {$op} ?";
            $params[] = $val;
        }
    }

    foreach (['min_area' => '>=', 'max_area' => '<='] as $key => $op) {
        $val = (int)($get[$key] ?? 0);
        if ($val > 0) {
            $where[] = "(p.area_total {$op} ? OR p.area_sqft {$op} ?)";
            $params[] = $val;
            $params[] = $val;
        }
    }

    foreach (['floor_from' => '>=', 'floor_to' => '<='] as $key => $op) {
        $field = $key === 'floor_from' ? 'floor_from' : 'floor_to';
        $col = 'p.floor_number';
        $val = (int)($get[$field] ?? 0);
        if ($val > 0) {
            $where[] = "{$col} {$op} ?";
            $params[] = $val;
        }
    }

    foreach (['house_type', 'renovation', 'metro'] as $field) {
        $val = trim((string)($get[$field] ?? ''));
        if ($val !== '') {
            $where[] = "p.{$field} = ?";
            $params[] = $val;
        }
    }

    $search = trim((string)($get['search'] ?? ''));
    if ($search !== '') {
        $where[] = '(p.title_ru LIKE ? OR p.street LIKE ? OR p.location LIKE ? OR p.building_name LIKE ?)';
        $term = '%' . $search . '%';
        array_push($params, $term, $term, $term, $term);
    }

    $sortBy = $get['sort'] ?? 'newest';
    $orderSql = match ($sortBy) {
        'price_asc'  => 'p.price ASC',
        'price_desc' => 'p.price DESC',
        'area_asc'   => 'p.area_total ASC',
        'area_desc'  => 'p.area_total DESC',
        default      => 'p.created_at DESC',
    };

    return [
        'category' => $category,
        'where'    => $where,
        'params'   => $params,
        'sortBy'   => $sortBy,
        'orderSql' => $orderSql,
        'page'     => $page,
        'perPage'  => $perPage,
        'offset'   => $offset,
        'search'   => $search,
    ];
}

function catalogFetchProperties(PDO $pdo, array $filters, ?int $userId = null): array
{
    $whereSql = implode(' AND ', $filters['where']);
    $params = $filters['params'];

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM properties p WHERE {$whereSql}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $favJoin = '';
    $favSelect = '0 AS is_favorite';
    if ($userId) {
        $favJoin = 'LEFT JOIN favorites fav ON fav.property_id = p.id AND fav.user_id = ?';
        $favSelect = 'IF(fav.id IS NOT NULL, 1, 0) AS is_favorite';
    }

    $sql = "
        SELECT p.*, d.name_ru AS district_name, pi.image_url AS primary_image,
               {$favSelect}
        FROM properties p
        LEFT JOIN ekb_districts d ON p.district_id = d.id
        LEFT JOIN property_images pi ON p.id = pi.property_id AND pi.is_primary = 1
        {$favJoin}
        WHERE {$whereSql}
        ORDER BY {$filters['orderSql']}
        LIMIT ? OFFSET ?
    ";

    $execParams = $userId ? array_merge([$userId], $params) : $params;
    $execParams[] = $filters['perPage'];
    $execParams[] = $filters['offset'];

    $stmt = $pdo->prepare($sql);
    $stmt->execute($execParams);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'items'      => $items,
        'total'      => $total,
        'page'       => $filters['page'],
        'perPage'    => $filters['perPage'],
        'totalPages' => (int)max(1, ceil($total / $filters['perPage'])),
    ];
}

function catalogFiltersToJson(array $get): string
{
    $keys = ['category', 'district', 'rooms', 'min_price', 'max_price', 'min_area', 'max_area',
        'floor_from', 'floor_to', 'house_type', 'renovation', 'metro', 'search', 'sort'];
    $out = [];
    foreach ($keys as $k) {
        if (isset($get[$k]) && $get[$k] !== '' && $get[$k] !== 'any') {
            $out[$k] = $get[$k];
        }
    }
    return json_encode($out, JSON_UNESCAPED_UNICODE);
}
