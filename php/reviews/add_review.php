<?php
/**
 * Add Review API - Elsesser & Co.
 * Добавление отзыва на объект или агента
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/config/database.php';
require_once __DIR__ . '/../../includes/auth/check_auth.php';

// Только POST запросы
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Требуем авторизацию
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Требуется авторизация']);
    exit;
}

$pdo = getDBConnection();
$userId = getCurrentUserId();

// Получаем данные (поддержка JSON и FormData)
$input = json_decode(file_get_contents('php://input'), true);

// Если JSON пустой, используем $_POST (FormData)
if (empty($input)) {
    $input = $_POST;
}

$propertyId = !empty($input['property_id']) ? intval($input['property_id']) : null;
$agentId = !empty($input['agent_id']) ? intval($input['agent_id']) : null;
$rating = intval($input['rating'] ?? 0);
$comment = trim($input['comment'] ?? '');
$reviewerName = trim($input['name'] ?? '');

// Валидация
$errors = [];

if (!$propertyId && !$agentId) {
    $errors[] = 'Укажите объект или агента для отзыва';
}

if ($rating < 1 || $rating > 5) {
    $errors[] = 'Рейтинг должен быть от 1 до 5';
}

if (mb_strlen($comment) > 1000) {
    $errors[] = 'Комментарий слишком длинный (макс. 1000 символов)';
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

// Проверяем, что пользователь ещё не оставлял отзыв
if ($propertyId) {
    $stmt = $pdo->prepare("SELECT id FROM reviews WHERE user_id = ? AND property_id = ?");
    $stmt->execute([$userId, $propertyId]);
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Вы уже оставили отзыв на этот объект']);
        exit;
    }
    
    // Проверяем, что объект существует
    $stmt = $pdo->prepare("SELECT id FROM properties WHERE id = ?");
    $stmt->execute([$propertyId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Объект не найден']);
        exit;
    }
}

if ($agentId) {
    $stmt = $pdo->prepare("SELECT id FROM reviews WHERE user_id = ? AND agent_id = ?");
    $stmt->execute([$userId, $agentId]);
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Вы уже оставили отзыв на этого агента']);
        exit;
    }
    
    // Проверяем, что агент существует
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'agent'");
    $stmt->execute([$agentId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Агент не найден']);
        exit;
    }
}

// Защита от XSS
$comment = htmlspecialchars($comment, ENT_QUOTES, 'UTF-8');

try {
    $stmt = $pdo->prepare("
        INSERT INTO reviews (user_id, property_id, agent_id, rating, comment, reviewer_name, is_approved, created_at)
        VALUES (?, ?, ?, ?, ?, ?, 0, NOW())
    ");
    $stmt->execute([$userId, $propertyId, $agentId, $rating, $comment ?: null, $reviewerName ?: null]);
    
    $reviewId = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Ваш отзыв отправлен на модерацию',
        'review_id' => $reviewId
    ]);
    
} catch (PDOException $e) {
    error_log("Add review error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Ошибка при сохранении отзыва']);
}
