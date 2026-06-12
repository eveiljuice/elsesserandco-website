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
                    SELECT id, email, password_hash, first_name, last_name, role, is_active,
                           failed_login_attempts, locked_until
                    FROM users
                    WHERE email = ?
                ");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && !empty($user['locked_until']) && strtotime((string)$user['locked_until']) > time()) {
                    $errors[] = 'Аккаунт временно заблокирован. Попробуйте позже.';
                } elseif ($user && password_verify($password, $user['password_hash'])) {
                    // Проверка активности аккаунта
                    if (!$user['is_active']) {
                        $errors[] = "Ваш аккаунт деактивирован. Обратитесь в поддержку.";
                    } else {
                        $pdo->prepare('UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE id = ?')
                            ->execute([$user['id']]);

                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['first_name'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_role'] = $user['role'];
                        $_SESSION['logged_in'] = true;
                        $_SESSION['login_time'] = time();
                        $_SESSION['last_activity'] = time();

                        $updateStmt = $pdo->prepare("UPDATE users SET updated_at = NOW() WHERE id = ?");
                        $updateStmt->execute([$user['id']]);
                        
                        // Проверка redirect параметра
                        $redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? null;
                        
                        // Безопасность: только относительные URL
                        if ($redirect && (!str_starts_with($redirect, '/') || str_contains($redirect, '//'))) {
                            $redirect = null;
                        }
                        
                        // Определяем куда перенаправлять по роли
                        if (!$redirect) {
                            $redirect = match($user['role']) {
                                'admin' => '/admin/index.php',
                                'agent' => '/agent/dashboard.php',
                                default => '/dashboard.php'
                            };
                        }
                        
                        header("Location: $redirect");
                        exit;
                    }
                } else {
                    sleep(1);
                    if ($user) {
                        $attempts = (int)$user['failed_login_attempts'] + 1;
                        $lockUntil = null;
                        if ($attempts >= 5) {
                            $lockUntil = date('Y-m-d H:i:s', time() + 900);
                            $attempts = 0;
                        }
                        $pdo->prepare('UPDATE users SET failed_login_attempts = ?, locked_until = ? WHERE id = ?')
                            ->execute([$attempts, $lockUntil, $user['id']]);
                    }
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
