<?php
/**
 * User Login Handler - Elsesser & Co.
 * Обработка авторизации пользователя через сессии
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/check_auth.php';

// Редирект если уже авторизован
if (isLoggedIn()) {
    header("Location: /dashboard.php");
    exit;
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF проверка
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Ошибка безопасности. Пожалуйста, попробуйте снова.";
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);
        
        // Базовая валидация
        if (empty($email)) {
            $errors[] = "Email обязателен для заполнения";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Некорректный формат email";
        }
        
        if (empty($password)) {
            $errors[] = "Пароль обязателен для заполнения";
        }
        
        if (empty($errors)) {
            try {
                $pdo = getDBConnection();
                
                $stmt = $pdo->prepare("
                    SELECT id, email, password_hash, first_name, last_name, role, is_active 
                    FROM users 
                    WHERE email = ?
                ");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($password, $user['password_hash'])) {
                    // Проверка активности аккаунта
                    if (!$user['is_active']) {
                        $errors[] = "Ваш аккаунт деактивирован. Обратитесь в поддержку.";
                    } else {
                        // Успешная авторизация
                        // Регенерация session ID для безопасности (против session fixation)
                        session_regenerate_id(true);
                        
                        // Сохранение данных в сессию
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['first_name'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_role'] = $user['role'];
                        $_SESSION['logged_in'] = true;
                        $_SESSION['login_time'] = time();
                        
                        // Обновление времени последнего входа
                        $updateStmt = $pdo->prepare("UPDATE users SET updated_at = NOW() WHERE id = ?");
                        $updateStmt->execute([$user['id']]);
                        
                        // Проверка redirect параметра
                        $redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? '/dashboard.php';
                        
                        // Безопасность: только относительные URL
                        if (!str_starts_with($redirect, '/') || str_contains($redirect, '//')) {
                            $redirect = '/dashboard.php';
                        }
                        
                        header("Location: $redirect");
                        exit;
                    }
                } else {
                    // Задержка для защиты от brute force
                    sleep(1);
                    $errors[] = "Неверный email или пароль";
                }
                
            } catch (PDOException $e) {
                error_log("Login error: " . $e->getMessage());
                $errors[] = "Ошибка авторизации. Пожалуйста, попробуйте позже.";
            }
        }
    }
}

// Возвращаем данные для использования в шаблоне
return [
    'errors' => $errors,
    'email' => $email,
    'csrf_token' => generateCSRFToken()
];
?>
