<?php
/**
 * Session Initialization - Elsesser & Co.
 * Инициализация сессии с безопасными настройками
 * 
 * ИСПОЛЬЗОВАНИЕ: Включать В САМОМ НАЧАЛЕ каждой PHP-страницы перед любым выводом:
 * <?php require_once __DIR__ . '/includes/auth/session_init.php'; ?>
 */

// Безопасные настройки сессий (устанавливаются ДО session_start)
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');

// Запуск сессии если еще не запущена
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
