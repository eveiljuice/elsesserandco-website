<?php
/**
 * Inquiry Submission Handler - Elsesser & Co.
 * Обработчик заявок на просмотр/консультацию
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/check_auth.php';

// Только POST запросы
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Получаем и валидируем данные
$propertyId = filter_input(INPUT_POST, 'property_id', FILTER_VALIDATE_INT);
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$message = trim($_POST['message'] ?? '');
$inquiryType = $_POST['inquiry_type'] ?? 'viewing';

// Валидация обязательных полей
$errors = [];

if (empty($name)) {
    $errors[] = 'Введите ваше имя';
} elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
    $errors[] = 'Имя должно содержать от 2 до 100 символов';
}

if (empty($email)) {
    $errors[] = 'Введите email';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Некорректный email адрес';
}

if (!empty($phone) && !preg_match('/^[\d\s\+\-\(\)]{7,20}$/', $phone)) {
    $errors[] = 'Некорректный формат телефона';
}

if (!in_array($inquiryType, ['general', 'viewing', 'offer', 'valuation'])) {
    $inquiryType = 'viewing';
}

// Проверяем существование объекта (если указан)
$pdo = getDBConnection();

if ($propertyId) {
    $stmt = $pdo->prepare("SELECT id, title FROM properties WHERE id = ?");
    $stmt->execute([$propertyId]);
    $property = $stmt->fetch();
    
    if (!$property) {
        $errors[] = 'Объект не найден';
    }
}

// Если есть ошибки — возвращаем их
if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

// Получаем user_id если пользователь авторизован
$userId = getCurrentUserId();

try {
    // Сохраняем заявку
    $stmt = $pdo->prepare("
        INSERT INTO inquiries (property_id, user_id, name, email, phone, message, inquiry_type, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'new', NOW())
    ");
    
    $stmt->execute([
        $propertyId ?: null,
        $userId,
        $name,
        $email,
        $phone ?: null,
        $message ?: null,
        $inquiryType
    ]);
    
    $inquiryId = $pdo->lastInsertId();
    
    // Отправляем email уведомления
    $emailFile = __DIR__ . '/../../php/email/send_notification.php';
    if (file_exists($emailFile)) {
        require_once $emailFile;
        
        // Уведомление агенту (если объект привязан к агенту)
        if ($propertyId) {
            $stmt = $pdo->prepare("
                SELECT p.title, u.email as agent_email 
                FROM properties p 
                JOIN users u ON p.agent_id = u.id 
                WHERE p.id = ?
            ");
            $stmt->execute([$propertyId]);
            $propertyData = $stmt->fetch();
            
            if ($propertyData && $propertyData['agent_email']) {
                sendRequestNotification(
                    $propertyData['agent_email'],
                    $propertyData['title'],
                    $name,
                    $email,
                    $phone,
                    $message
                );
            }
            
            // Подтверждение пользователю
            sendRequestConfirmation($email, $name, $propertyData['title'] ?? 'Объект недвижимости');
        }
    }
    
    // Логируем заявку
    $logMessage = sprintf(
        "[%s] New inquiry #%d | Property: %s | Name: %s | Email: %s | Type: %s\n",
        date('Y-m-d H:i:s'),
        $inquiryId,
        $propertyId ?: 'N/A',
        $name,
        $email,
        $inquiryType
    );
    
    $logDir = __DIR__ . '/../../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    file_put_contents($logDir . '/inquiries.log', $logMessage, FILE_APPEND);
    
    // Успешный ответ
    echo json_encode([
        'success' => true,
        'message' => 'Ваша заявка успешно отправлена! Мы свяжемся с вами в ближайшее время.',
        'inquiry_id' => $inquiryId
    ]);
    
} catch (PDOException $e) {
    error_log("Inquiry submission error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Произошла ошибка при сохранении заявки. Попробуйте позже.']);
}
 