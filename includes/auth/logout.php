<?php
/**
 * User Logout Handler - Elsesser & Co.
 * Выход из системы
 */

require_once __DIR__ . '/check_auth.php';

// Очистка всех данных сессии
$_SESSION = [];

// Удаление cookie сессии
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Уничтожение сессии
session_destroy();

// Редирект на главную с сообщением
header("Location: /index.php?logout=1");
exit;
?>
