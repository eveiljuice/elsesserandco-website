<?php
/**
 * Get Reviews API - Elsesser & Co.
 * Получение отзывов на объект или агента
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/config/database.php';
require_once __DIR__ . '/../../includes/auth/check_auth.php';

$pdo = getDBConnection();

$propertyId = intval($_GET['property_id'] ?? 0);
$agentId = intval($_GET['agent_id'] ?? 0);
$page = max(1, intval($_GET['page'] ?? 1));
$limit = min(20, max(5, intval($_GET['limit'] ?? 10)));
$offset = ($page - 1) * $limit;

if (!$propertyId && !$agentId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Укажите property_id или agent_id']);
    exit;
}

try {
    // Построение запроса
    $where = "r.is_approved = 1";
    $params = [];
    
    if ($propertyId) {
        $where .= " AND r.property_id = ?";
        $params[] = $propertyId;
    }
    
    if ($agentId) {
        $where .= " AND r.agent_id = ?";
        $params[] = $agentId;
    }
    
    // Общее количество
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reviews r WHERE $where");
    $stmt->execute($params);
    $totalCount = $stmt->fetchColumn();
    
    // Средний рейтинг
    $stmt = $pdo->prepare("SELECT AVG(rating) as avg_rating FROM reviews r WHERE $where");
    $stmt->execute($params);
    $avgRating = round($stmt->fetchColumn() ?? 0, 1);
    
    // Получаем отзывы
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare("
        SELECT r.*, u.first_name, u.last_name
        FROM reviews r
        JOIN users u ON r.user_id = u.id
        WHERE $where
        ORDER BY r.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute($params);
    $reviews = $stmt->fetchAll();
    
    // Форматируем отзывы
    $formattedReviews = [];
    foreach ($reviews as $review) {
        $formattedReviews[] = [
            'id' => $review['id'],
            'rating' => (int)$review['rating'],
            'comment' => $review['comment'],
            'author_name' => $review['first_name'] . ' ' . mb_substr($review['last_name'], 0, 1) . '.',
            'created_at' => $review['created_at'],
            'created_at_formatted' => date('d.m.Y', strtotime($review['created_at']))
        ];
    }
    
    echo json_encode([
        'success' => true,
        'reviews' => $formattedReviews,
        'total' => (int)$totalCount,
        'avg_rating' => $avgRating,
        'page' => $page,
        'pages' => ceil($totalCount / $limit)
    ]);
    
} catch (PDOException $e) {
    error_log("Get reviews error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Ошибка при получении отзывов']);
}
