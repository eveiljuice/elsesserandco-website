<?php
/**
 * Add to Favorites - Elsesser & Co.
 * AJAX endpoint для добавления в избранное
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/check_auth.php';

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
    
    // Добавляем в избранное
    $stmt = $pdo->prepare("INSERT INTO favorites (user_id, property_id) VALUES (?, ?)");
    $stmt->execute([$userId, $propertyId]);
    
    // Получаем новое количество избранного
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = ?");
    $stmt->execute([$userId]);
    $count = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Добавлено в избранное',
        'favorites_count' => $count
    ]);
    
} catch (PDOException $e) {
    // Если уже в избранном (unique constraint)
    if ($e->getCode() == 23000) {
        echo json_encode([
            'success' => false, 
            'error' => 'Объект уже в избранном',
            'already_favorite' => true
        ]);
    } else {
        error_log("Add favorite error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Ошибка сервера']);
    }
}
?>
