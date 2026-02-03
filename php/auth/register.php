<?php
/**
 * User Registration Handler - Elsesser & Co.
 * Обработка регистрации нового пользователя
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/check_auth.php';

// Редирект если уже авторизован
if (isLoggedIn()) {
    header("Location: /dashboard.php");
    exit;
}

$errors = [];
$success = false;
$formData = [
    'email' => '',
    'first_name' => '',
    'last_name' => '',
    'phone' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF проверка
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Ошибка безопасности. Пожалуйста, попробуйте снова.";
    } else {
        // Получение и валидация данных
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        
        // Сохраняем данные для формы
        $formData = compact('email', 'first_name', 'last_name', 'phone');
        
        // Валидация email
        if (empty($email)) {
            $errors[] = "Email обязателен для заполнения";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Некорректный формат email";
        }
        
        // Валидация имени
        if (empty($first_name)) {
            $errors[] = "Имя обязательно для заполнения";
        } elseif (mb_strlen($first_name) < 2) {
            $errors[] = "Имя должно содержать минимум 2 символа";
        }
        
        // Валидация фамилии
        if (empty($last_name)) {
            $errors[] = "Фамилия обязательна для заполнения";
        } elseif (mb_strlen($last_name) < 2) {
            $errors[] = "Фамилия должна содержать минимум 2 символа";
        }
        
        // Валидация пароля
        if (empty($password)) {
            $errors[] = "Пароль обязателен для заполнения";
        } elseif (strlen($password) < 8) {
            $errors[] = "Пароль должен содержать минимум 8 символов";
        } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $errors[] = "Пароль должен содержать буквы и цифры";
        }
        
        // Проверка совпадения паролей
        if ($password !== $password_confirm) {
            $errors[] = "Пароли не совпадают";
        }
        
        // Валидация телефона (опционально)
        if (!empty($phone) && !preg_match('/^[\+]?[0-9\s\-\(\)]{7,20}$/', $phone)) {
            $errors[] = "Некорректный формат телефона";
        }
        
        // Если нет ошибок - регистрируем
        if (empty($errors)) {
            try {
                $pdo = getDBConnection();
                
                // Проверка существующего email
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                
                if ($stmt->fetch()) {
                    $errors[] = "Пользователь с таким email уже зарегистрирован";
                } else {
                    // Хеширование пароля
                    $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                    
                    // Вставка нового пользователя
                    $stmt = $pdo->prepare("
                        INSERT INTO users (email, password_hash, first_name, last_name, phone, role) 
                        VALUES (?, ?, ?, ?, ?, 'user')
                    ");
                    $stmt->execute([
                        $email,
                        $password_hash,
                        htmlspecialchars($first_name, ENT_QUOTES, 'UTF-8'),
                        htmlspecialchars($last_name, ENT_QUOTES, 'UTF-8'),
                        !empty($phone) ? htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') : null
                    ]);
                    
                    $success = true;
                    
                    // Автоматический вход после регистрации
                    $userId = $pdo->lastInsertId();
                    session_regenerate_id(true);
                    
                    $_SESSION['user_id'] = $userId;
                    $_SESSION['user_name'] = $first_name;
                    $_SESSION['user_email'] = $email;
                    $_SESSION['user_role'] = 'user';
                    $_SESSION['logged_in'] = true;
                    
                    // Редирект в личный кабинет
                    header("Location: /dashboard.php?welcome=1");
                    exit;
                }
                
            } catch (PDOException $e) {
                error_log("Registration error: " . $e->getMessage());
                $errors[] = "Ошибка при регистрации. Пожалуйста, попробуйте позже.";
            }
        }
    }
}

// Возвращаем данные для использования в шаблоне
return [
    'errors' => $errors,
    'success' => $success,
    'formData' => $formData,
    'csrf_token' => generateCSRFToken()
];
?>
