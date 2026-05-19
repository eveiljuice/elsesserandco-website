<?php
/**
 * Remove from Favorites - Elsesser & Co.
 * AJAX endpoint для удаления из избранного
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/check_auth.php';
require_once __DIR__ . '/../auth/csrf_json.php';

// Проверка авторизации
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'error' => 'Необходимо авторизоваться',
        'require_login' => true
    ]);
    exit;
}

// Только POST/DELETE запросы
if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'DELETE'])) {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Метод не разрешён']);
    exit;
}

requireJsonCsrf();

// Получение данных
$input = json_decode(file_get_contents('php://input'), true);
$propertyId = isset($input['property_id']) ? (int)$input['property_id'] : (int)($_POST['property_id'] ?? 0);

if ($propertyId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Некорректный ID объекта']);
    exit;
}

$userId = getCurrentUserId();

try {
    $pdo = getDBConnection();
    
    // Удаляем из избранного
    $stmt = $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND property_id = ?");
    $stmt->execute([$userId, $propertyId]);
    
    if ($stmt->rowCount() === 0) {
        echo json_encode([
            'success' => false, 
            'error' => 'Объект не найден в избранном'
        ]);
        exit;
    }
    
    // Получаем новое количество избранного
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = ?");
    $stmt->execute([$userId]);
    $count = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Удалено из избранного',
        'favorites_count' => $count
    ]);
    
} catch (PDOException $e) {
    error_log("Remove favorite error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Ошибка сервера']);
}
?>
