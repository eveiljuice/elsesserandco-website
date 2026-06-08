<?php
/**
 * Flash-сообщения: одноразовый вывод после редиректа.
 * Используется, когда после POST нужен редирект на GET
 * с коротким сообщением «успех» или «ошибка» вверху страницы.
 */

declare(strict_types=1);

/**
 * Установить flash-сообщение.
 * @param string $type 'success' | 'error' | 'info'
 * @param string $msg
 */
function flashSet(string $type, string $msg): void
{
    if (!isset($_SESSION)) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    $_SESSION['__flash'] = ['type' => $type, 'msg' => $msg];
}

/**
 * Забрать и удалить flash-сообщение.
 * @return array{type:string,msg:string}|null
 */
function flashGet(): ?array
{
    if (!isset($_SESSION['__flash'])) {
        return null;
    }
    $f = $_SESSION['__flash'];
    unset($_SESSION['__flash']);
    return $f;
}

/**
 * Отрендерить flash-сообщение в HTML, если оно есть.
 * Использует классы .alert / .alert--success / .alert--error / .alert--info
 * (уже определены в css/admin.css).
 * @return string HTML
 */
function flashRender(): string
{
    $f = flashGet();
    if ($f === null) {
        return '';
    }
    $type = htmlspecialchars($f['type'], ENT_QUOTES, 'UTF-8');
    $msg  = htmlspecialchars($f['msg'], ENT_QUOTES, 'UTF-8');
    return '<div class="alert alert--' . $type . '">' . $msg . '</div>';
}
