<?php
/**
 * Newsletter Subscription Handler - Elsesser & Co.
 * Подписка на рассылку
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

$pdo = getDBConnection();

// Получаем email
$email = trim($_POST['email'] ?? '');
$preferences = $_POST['preferences'] ?? [];

// Валидация email
if (empty($email)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Email обязателен']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Некорректный email адрес']);
    exit;
}

// Получаем user_id если авторизован
$userId = getCurrentUserId();

try {
    // Проверяем, есть ли уже подписка
    $stmt = $pdo->prepare("SELECT id, is_active FROM newsletter_subscribers WHERE email = ?");
    $stmt->execute([$email]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        if ($existing['is_active']) {
            // Уже подписан
            echo json_encode([
                'success' => true,
                'message' => 'Вы уже подписаны на нашу рассылку!'
            ]);
        } else {
            // Реактивация подписки
            $stmt = $pdo->prepare("
                UPDATE newsletter_subscribers 
                SET is_active = 1, unsubscribed_at = NULL, preferences = ?
                WHERE id = ?
            ");
            $stmt->execute([
                !empty($preferences) ? json_encode($preferences) : null,
                $existing['id']
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Ваша подписка успешно восстановлена!'
            ]);
        }
    } else {
        // Новая подписка
        $stmt = $pdo->prepare("
            INSERT INTO newsletter_subscribers (email, user_id, preferences, is_active, subscribed_at)
            VALUES (?, ?, ?, 1, NOW())
        ");
        $stmt->execute([
            $email,
            $userId,
            !empty($preferences) ? json_encode($preferences) : null
        ]);
        
        // Отправляем приветственное письмо (опционально)
        $emailFile = __DIR__ . '/../email/send_notification.php';
        if (file_exists($emailFile)) {
            require_once $emailFile;
            
            $subject = "Добро пожаловать в рассылку Elsesser & Co.!";
            $body = <<<HTML
<h2 style="margin: 0 0 20px; color: #1a2447; font-size: 24px;">Спасибо за подписку!</h2>
<p style="margin: 0 0 16px; color: #4b5563; font-size: 16px; line-height: 1.6;">
    Теперь вы будете получать актуальные новости о рынке недвижимости Дубая, новые объекты и специальные предложения.
</p>
<p style="margin: 0; color: #6b7280; font-size: 14px;">
    Если вы хотите отписаться, нажмите <a href="#" style="color: #00736c;">здесь</a>.
</p>
HTML;
            sendEmail($email, $subject, $body);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Вы успешно подписались на рассылку!'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Newsletter subscription error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Ошибка при подписке. Попробуйте позже.']);
}
