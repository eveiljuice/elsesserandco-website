<?php
/**
 * Toggle Favorite - Elsesser & Co.
 * AJAX endpoint для переключения избранного
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

// Только POST запросы
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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
    
    // Проверяем существование объекта
    $stmt = $pdo->prepare("SELECT id FROM properties WHERE id = ?");
    $stmt->execute([$propertyId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Объект не найден']);
        exit;
    }
    
    // Проверяем, есть ли в избранном
    $stmt = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND property_id = ?");
    $stmt->execute([$userId, $propertyId]);
    $exists = $stmt->fetch();
    
    if ($exists) {
        // Удаляем из избранного
        $stmt = $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND property_id = ?");
        $stmt->execute([$userId, $propertyId]);
        $isFavorite = false;
        $message = 'Удалено из избранного';
    } else {
        // Добавляем в избранное
        $stmt = $pdo->prepare("INSERT INTO favorites (user_id, property_id) VALUES (?, ?)");
        $stmt->execute([$userId, $propertyId]);
        $isFavorite = true;
        $message = 'Добавлено в избранное';
    }
    
    // Получаем новое количество избранного
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = ?");
    $stmt->execute([$userId]);
    $count = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true, 
        'message' => $message,
        'is_favorite' => $isFavorite,
        'favorites_count' => $count
    ]);
    
} catch (PDOException $e) {
    error_log("Toggle favorite error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Ошибка сервера']);
}
?>
