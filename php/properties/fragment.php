<?php
/**
 * AJAX: HTML-фрагмент сетки каталога + пагинация.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../includes/config/database.php';
require_once __DIR__ . '/../../includes/auth/check_auth.php';
require_once __DIR__ . '/../../includes/properties/catalog_query.php';

$userId = isLoggedIn() ? getCurrentUserId() : null;
$filters = catalogParseFilters($_GET);
$result = catalogFetchProperties(getDBConnection(), $filters, $userId);

ob_start();
include __DIR__ . '/../../includes/properties/_grid_partial.php';
$html = ob_get_clean();

echo json_encode([
    'success'    => true,
    'html'       => $html,
    'total'      => $result['total'],
    'page'       => $result['page'],
    'totalPages' => $result['totalPages'],
], JSON_UNESCAPED_UNICODE);
