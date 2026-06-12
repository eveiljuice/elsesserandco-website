<?php
/**
 * Общая логика фильтрации каталога properties.php и AJAX-фрагмента.
 */

declare(strict_types=1);

/**
 * Превращает пользовательский запрос в boolean-строку для MATCH AGAINST.
 *  - слова длиной < 3 отбрасываем (ft_min_token_len всё равно их не возьмёт)
 *  - каждому оставшемуся слову добавляем префиксный wildcard '*' на конце,
 *    чтобы "малыш" находило "малышева", "малышева 10" и т.д.
 *  - минус-слова (начинающиеся с "-") сохраняем как есть для исключений
 *  - фразы в кавычках сохраняем без wildcard
 */
function catalogBuildBooleanQuery(string $search): string
{
    $tokens = preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $out = [];
    foreach ($tokens as $tok) {
        $tok = trim($tok);
        if ($tok === '') continue;
        $isNegative = $tok[0] === '-';
        $word = $isNegative ? substr($tok, 1) : $tok;
        // фразу в кавычках оставляем как есть
        if (strlen($word) >= 2 && $word[0] === '"' && substr($word, -1) === '"') {
            $out[] = ($isNegative ? '-' : '') . $word;
            continue;
        }
        // очень короткие слова (<3) FULLTEXT всё равно игнорирует — пропускаем
        if (mb_strlen($word) < 3) continue;
        // экранируем wildcard-спецсимволы внутри слова, оставляя только хвостовой '*'
        $wordSafe = preg_replace('/[+\-><()~*"@]+/u', ' ', $word);
        $wordSafe = trim($wordSafe);
        if ($wordSafe === '') continue;
        $out[] = ($isNegative ? '-' : '+') . $wordSafe . '*';
    }
    return $out ? implode(' ', $out) : $search;
}

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
    $useFulltext = false;
    if ($search !== '') {
        // FULLTEXT работает для слов длиной от ft_min_token_len (по умолчанию 3).
        // Для очень коротких запросов (1-2 символа) делаем LIKE-фоллбек,
        // иначе MATCH AGAINST вернёт 0 строк.
        $tokens = preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $hasLongToken = false;
        foreach ($tokens as $t) {
            if (mb_strlen($t) >= 3) { $hasLongToken = true; break; }
        }
        $useFulltext = $hasLongToken && mb_strlen($search) >= 3;

        if ($useFulltext) {
            $boolQuery = catalogBuildBooleanQuery($search);
            $where[] = "MATCH(p.title_ru, p.street, p.location, p.building_name, p.description_ru, p.title, p.description)
                        AGAINST(? IN BOOLEAN MODE)";
            $params[] = $boolQuery;
        } else {
            $where[] = '(p.title_ru LIKE ? OR p.street LIKE ? OR p.location LIKE ? OR p.building_name LIKE ?)';
            $term = '%' . $search . '%';
            array_push($params, $term, $term, $term, $term);
        }
    }

    $sortBy = $get['sort'] ?? 'newest';
    // Если был поиск и сортировка «сначала новые» — добавим релевантность первым приоритетом
    $orderByRelevance = $useFulltext && $sortBy === 'newest';
    $orderSql = match ($sortBy) {
        'price_asc'  => 'p.price ASC',
        'price_desc' => 'p.price DESC',
        'area_asc'   => 'p.area_total ASC',
        'area_desc'  => 'p.area_total DESC',
        default      => 'p.created_at DESC',
    };
    if ($orderByRelevance) {
        $orderSql = "MATCH(p.title_ru, p.street, p.location, p.building_name, p.description_ru, p.title, p.description)
                     AGAINST(? IN BOOLEAN MODE) DESC, " . $orderSql;
    }

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
    $whereParams = $filters['params'];
    // Если в $filters['orderSql'] есть второй плейсхолдер ? для релевантности —
    // его параметр должен идти сразу после WHERE-параметров (и после fav-параметра, если он есть).
    $needsRelevanceParam = str_contains($filters['orderSql'], 'MATCH(');

    $countSql = "SELECT COUNT(*) FROM properties p WHERE {$whereSql}";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($whereParams);
    $total = (int)$countStmt->fetchColumn();

    $favJoin = '';
    $favSelect = '0 AS is_favorite';
    $favParam = null;
    if ($userId) {
        $favJoin = 'LEFT JOIN favorites fav ON fav.property_id = p.id AND fav.user_id = ?';
        $favSelect = 'IF(fav.id IS NOT NULL, 1, 0) AS is_favorite';
        $favParam = $userId;
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

    // Порядок плейсхолдеров в $sql:
    // 1) fav.user_id (если залогинен)
    // 2) WHERE-параметры ($whereParams)
    // 3) Параметр релевантности для ORDER BY (тот же boolQuery, что в WHERE)
    // 4) LIMIT
    // 5) OFFSET
    $execParams = [];
    if ($favParam !== null) $execParams[] = $favParam;
    foreach ($whereParams as $p) $execParams[] = $p;
    if ($needsRelevanceParam) {
        // boolQuery — последний WHERE-параметр (добавляется при useFulltext)
        $execParams[] = end($whereParams);
    }
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
