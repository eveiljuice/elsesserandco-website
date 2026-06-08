# Управление пользователями в админке — Implementation Plan

> **For agentic workers:** Этот план рассчитан на ручное выполнение разработчиком с базовым знанием PHP/MySQL и пониманием контекста проекта. PHPUnit удалён, поэтому задачи проверяются через ручной smoke-test. Чекбоксы (`- [ ]`) — для отслеживания прогресса.

**Goal:** Добавить в `admin/` две страницы: список пользователей с фильтрами/поиском/inline-действиями и страницу создания/редактирования пользователя. Доступ — только админам. 0 миграций.

**Architecture:** vanilla PHP-страницы по образцу существующих `admin/properties.php` и `admin/inquiries.php`. POST-обработчики внутри тех же файлов, что и GET-рендеринг (action-параметр). Защита: `requireAdmin()`, CSRF через существующий `validateCSRFToken()`, проверки «не сам себя» и «не последний админ». Сброс пароля — через email-ссылку, без раскрытия пароля админу. Flash-сообщения — простой helper в `includes/flash.php`.

**Tech Stack:** PHP 8.1, MySQL 8.0, PDO, vanilla JS, существующий `css/admin.css` (без правок), существующие helper'ы `escape()`, `generateCSRFToken()`, `validateCSRFToken()`.

---

## Структура файлов

| Файл | Тип | Ответственность |
|------|-----|-----------------|
| `includes/flash.php` | новый | Функции `flashSet($type, $msg)` / `flashGet()` / `flashRender()` — короткие одноразовые сообщения в `$_SESSION` |
| `admin/users.php` | новый | Список пользователей (GET) + POST-обработчики inline-действий (бан, подтверждение email, отправка ссылки на сброс) |
| `admin/user-edit.php` | новый | Создание (без `?id`) и редактирование (`?id=N`) пользователя (GET + POST) + действия «отправить ссылку на сброс» и «удалить» |
| `admin/includes/admin-sidebar.php` | правка | Новая секция «Администрирование» → пункт «Пользователи» |
| `docs/superpowers/test-plans/2026-06-08-admin-user-management.md` | новый | Чек-лист ручного smoke-теста (20 шагов) |

**0 миграций, 0 правок CSS, 0 новых npm-зависимостей.**

---

## Task 0: Подготовка — helper для flash-сообщений

**Files:**
- Create: `includes/flash.php`

- [ ] **Step 1: Создать файл `includes/flash.php`**

```php
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
```

- [ ] **Step 2: Проверить синтаксис**

Run: `php -l D:\OSPanel\home\elsesserandco-site.local\includes\flash.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Коммит**

```bash
git add includes/flash.php
git commit -m "feat(admin): add flash message helper"
```

---

## Task 1: Создать `admin/users.php` (только GET-рендеринг списка, без POST-обработчиков)

**Files:**
- Create: `admin/users.php`

- [ ] **Step 1: Создать `admin/users.php` с GET-логикой**

```php
<?php
/**
 * Admin: список пользователей.
 * GET  — рендер списка с фильтрами/поиском/пагинацией.
 * POST — inline-действия: бан/разбан, подтверждение email, отправка ссылки на сброс.
 * Доступ: только админы.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/config/database.php';
require_once __DIR__ . '/../includes/auth/check_auth.php';
require_once __DIR__ . '/../includes/flash.php';

requireAdmin();

$pdo = getDBConnection();

// === POST-обработчики (inline-действия) ===

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token   = $_POST['csrf_token'] ?? '';
    $action  = $_GET['action'] ?? '';
    $userId  = (int)($_GET['id'] ?? 0);

    if (!validateCSRFToken($token)) {
        http_response_code(403);
        exit('Invalid CSRF token');
    }

    if ($userId <= 0) {
        flashSet('error', 'Некорректный ID пользователя.');
        header('Location: users.php');
        exit;
    }

    // Запрет действий над самим собой
    if ($userId === (int)$_SESSION['user_id']) {
        flashSet('error', 'Нельзя выполнить это действие над собственным аккаунтом.');
        header('Location: users.php');
        exit;
    }

    // Загрузить пользователя
    $stmt = $pdo->prepare("SELECT id, role, is_active FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $target = $stmt->fetch();

    if (!$target) {
        flashSet('error', 'Пользователь не найден.');
        header('Location: users.php');
        exit;
    }

    // Защита «не последний админ» (для бан/разбан и смены роли)
    $isLastAdmin = (function (PDO $pdo) use ($target): bool {
        $count = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND is_active=1")->fetchColumn();
        if ($count > 1) return false;
        return $target['role'] === 'admin' && (int)$target['is_active'] === 1;
    })($pdo);

    switch ($action) {
        case 'toggle-active':
            if ($isLastAdmin) {
                flashSet('error', 'Нельзя заблокировать единственного активного администратора.');
                header('Location: users.php');
                exit;
            }
            $newActive = (int)$target['is_active'] === 1 ? 0 : 1;
            $upd = $pdo->prepare("UPDATE users SET is_active = ?, failed_login_attempts = 0, locked_until = NULL WHERE id = ?");
            $upd->execute([$newActive, $userId]);
            flashSet('success', $newActive ? 'Пользователь разблокирован.' : 'Пользователь заблокирован.');
            break;

        case 'verify-email':
            $upd = $pdo->prepare("UPDATE users SET email_verified_at = NOW() WHERE id = ? AND email_verified_at IS NULL");
            $upd->execute([$userId]);
            if ($upd->rowCount() > 0) {
                flashSet('success', 'Email подтверждён вручную.');
            } else {
                flashSet('error', 'Email уже подтверждён или пользователь не найден.');
            }
            break;

        case 'send-reset':
            // Генерируем 64-символьный hex-токен
            $plain = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $plain);
            $ins = $pdo->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, NOW() + INTERVAL 1 HOUR)");
            $ins->execute([$userId, $tokenHash]);

            // Загрузить email для отправки
            $stmtE = $pdo->prepare("SELECT email, first_name FROM users WHERE id = ?");
            $stmtE->execute([$userId]);
            $u = $stmtE->fetch();
            if ($u) {
                $resetUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/reset-password.php?token=' . urlencode($plain);
                $html = '<h2>Здравствуйте, ' . escape($u['first_name']) . '!</h2>'
                      . '<p>По вашему запросу создана ссылка для сброса пароля.</p>'
                      . '<p><a href="' . escape($resetUrl) . '">Сбросить пароль</a></p>'
                      . '<p>Ссылка действительна 1 час. Если вы не запрашивали сброс — проигнорируйте письмо.</p>';
                Mailer::send($u['email'], 'Сброс пароля в Elsesser & Co.', $html);
                flashSet('success', 'Ссылка на сброс пароля отправлена на email пользователя.');
            }
            break;

        default:
            flashSet('error', 'Неизвестное действие.');
    }

    header('Location: users.php');
    exit;
}

// === GET: рендер списка ===

// Параметры фильтрации
$q      = trim((string)($_GET['q'] ?? ''));
$role   = (string)($_GET['role'] ?? 'all');
$status = (string)($_GET['status'] ?? 'all');
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset  = ($page - 1) * $perPage;

// Построить WHERE
$where = [];
$params = [];

if ($q !== '') {
    $where[] = '(email LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR phone LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
}
if (in_array($role, ['admin', 'agent', 'user'], true)) {
    $where[] = 'role = ?';
    $params[] = $role;
}
if ($status === 'active')     { $where[] = 'is_active = 1'; }
elseif ($status === 'blocked') { $where[] = 'is_active = 0'; }
elseif ($status === 'verified')   { $where[] = 'email_verified_at IS NOT NULL'; }
elseif ($status === 'unverified') { $where[] = 'email_verified_at IS NULL'; }

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Подсчёт общего числа
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

// Список
$listStmt = $pdo->prepare("
    SELECT id, email, first_name, last_name, phone, role, is_active, email_verified_at, created_at
    FROM users
    $whereSql
    ORDER BY created_at DESC
    LIMIT $perPage OFFSET $offset
");
$listStmt->execute($params);
$users = $listStmt->fetchAll();

// Пометить «сам себя» для UI
$currentUserId = (int)$_SESSION['user_id'];
foreach ($users as &$u) {
    $u['is_self'] = ((int)$u['id'] === $currentUserId);
}
unset($u);

$pageTitle = 'Пользователи';
$csrf = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($pageTitle) ?> | Elsesser & Co.</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../css/reset.css">
    <link rel="stylesheet" href="../css/variables.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body class="admin-body">
    <?php include __DIR__ . '/includes/admin-header.php'; ?>
    <div class="admin-container">
        <?php include __DIR__ . '/includes/admin-sidebar.php'; ?>
        <main class="admin-main">
            <?= flashRender() ?>

            <div class="admin-header">
                <div>
                    <h1 class="admin-title">Пользователи</h1>
                    <div class="admin-breadcrumb"><i class="fas fa-users"></i> Всего: <?= $total ?></div>
                </div>
                <a href="user-edit.php" class="btn btn--primary">
                    <i class="fas fa-plus"></i> Создать пользователя
                </a>
            </div>

            <form method="GET" action="users.php" class="admin-filters" id="filtersForm">
                <div class="admin-filters__search">
                    <i class="fas fa-search"></i>
                    <input type="text" name="q" value="<?= escape($q) ?>" placeholder="Email, имя, фамилия или телефон">
                </div>
                <select name="role" class="admin-select" onchange="document.getElementById('filtersForm').submit()">
                    <option value="all"      <?= $role === 'all'   ? 'selected' : '' ?>>Роль: все</option>
                    <option value="admin"    <?= $role === 'admin' ? 'selected' : '' ?>>Админ</option>
                    <option value="agent"    <?= $role === 'agent' ? 'selected' : '' ?>>Агент</option>
                    <option value="user"     <?= $role === 'user'  ? 'selected' : '' ?>>Пользователь</option>
                </select>
                <select name="status" class="admin-select" onchange="document.getElementById('filtersForm').submit()">
                    <option value="all"         <?= $status === 'all'         ? 'selected' : '' ?>>Статус: все</option>
                    <option value="active"      <?= $status === 'active'      ? 'selected' : '' ?>>Активные</option>
                    <option value="blocked"     <?= $status === 'blocked'     ? 'selected' : '' ?>>Заблокированные</option>
                    <option value="verified"    <?= $status === 'verified'    ? 'selected' : '' ?>>Email подтверждён</option>
                    <option value="unverified"  <?= $status === 'unverified'  ? 'selected' : '' ?>>Email не подтверждён</option>
                </select>
                <?php if ($q !== '' || $role !== 'all' || $status !== 'all'): ?>
                    <a href="users.php" class="admin-pagination__btn"><i class="fas fa-times"></i> Сбросить</a>
                <?php endif; ?>
                <button type="submit" class="admin-pagination__btn" style="display:none">Найти</button>
            </form>

            <div class="admin-card">
                <div class="admin-card__body admin-table-wrapper">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Email</th>
                                <th>ФИО</th>
                                <th>Роль</th>
                                <th>Статус</th>
                                <th>Email</th>
                                <th>Зарегистрирован</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($users)): ?>
                            <tr><td colspan="8" class="text-center text-muted">Пользователи не найдены.</td></tr>
                        <?php else: ?>
                            <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?= (int)$u['id'] ?></td>
                                <td><?= escape($u['email']) ?></td>
                                <td><?= escape(trim($u['first_name'] . ' ' . $u['last_name'])) ?: '—' ?></td>
                                <td>
                                    <?php if ($u['role'] === 'admin'): ?>
                                        <span class="badge badge--secondary">admin</span>
                                    <?php elseif ($u['role'] === 'agent'): ?>
                                        <span class="badge badge--success">agent</span>
                                    <?php else: ?>
                                        <span class="badge badge--secondary">user</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ((int)$u['is_active'] === 1): ?>
                                        <span class="badge badge--success">Активен</span>
                                    <?php else: ?>
                                        <span class="badge badge--warning">Заблокирован</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($u['email_verified_at']): ?>
                                        <span class="badge badge--success">Подтверждён</span>
                                    <?php else: ?>
                                        <span class="badge badge--warning">Не подтверждён</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-sm text-muted"><?= escape(date('d.m.Y', strtotime($u['created_at']))) ?></td>
                                <td>
                                    <div class="admin-table__actions">
                                        <a href="user-edit.php?id=<?= (int)$u['id'] ?>" class="btn-icon" title="Редактировать">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                        <?php if (!$u['is_self']): ?>
                                            <form method="POST" action="users.php?action=toggle-active&id=<?= (int)$u['id'] ?>" style="display:inline" onsubmit="return confirm('<?= (int)$u['is_active'] === 1 ? 'Заблокировать' : 'Разблокировать' ?> пользователя <?= escape(addslashes($u['email'])) ?>?')">
                                                <input type="hidden" name="csrf_token" value="<?= escape($csrf) ?>">
                                                <button type="submit" class="btn-icon" title="<?= (int)$u['is_active'] === 1 ? 'Заблокировать' : 'Разблокировать' ?>">
                                                    <i class="fas fa-<?= (int)$u['is_active'] === 1 ? 'lock' : 'lock-open' ?>"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if (!$u['email_verified_at']): ?>
                                            <form method="POST" action="users.php?action=verify-email&id=<?= (int)$u['id'] ?>" style="display:inline">
                                                <input type="hidden" name="csrf_token" value="<?= escape($csrf) ?>">
                                                <button type="submit" class="btn-icon" title="Подтвердить email вручную">
                                                    <i class="fas fa-envelope-open-text"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" action="users.php?action=send-reset&id=<?= (int)$u['id'] ?>" style="display:inline" onsubmit="return confirm('Отправить пользователю <?= escape(addslashes($u['email'])) ?> ссылку на сброс пароля?')">
                                            <input type="hidden" name="csrf_token" value="<?= escape($csrf) ?>">
                                            <button type="submit" class="btn-icon" title="Отправить ссылку на сброс пароля">
                                                <i class="fas fa-key"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ($totalPages > 1): ?>
            <div class="admin-pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="admin-pagination__btn">
                        <i class="fas fa-chevron-left"></i> Назад
                    </a>
                <?php endif; ?>
                <div class="admin-pagination__pages">Страница <?= $page ?> из <?= $totalPages ?></div>
                <?php if ($page < $totalPages): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="admin-pagination__btn">
                        Вперёд <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
```

- [ ] **Step 2: Проверить синтаксис**

Run: `php -l D:\OSPanel\home\elsesserandco-site.local\admin\users.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Smoke-test в браузере**

1. Войти админом → перейти на `admin/users.php` — список отображается.
2. Ввести в поиск часть email — список фильтруется.
3. Изменить фильтр «Роль» — автосабмит, URL содержит `?role=agent`.
4. Изменить фильтр «Статус» — автосабмит.
5. Кнопка «Сбросить» появляется только при активных фильтрах.
6. На текущем админе нет кнопки «🔒» (бан).
7. Пагинация внизу (если > 25 пользователей).

Ожидаемо: всё работает, нет ошибок PHP в error_log.

- [ ] **Step 4: Коммит**

```bash
git add admin/users.php
git commit -m "feat(admin): user list with filters, search, pagination, and inline actions"
```

---

## Task 2: Создать `admin/user-edit.php` (GET + POST)

**Files:**
- Create: `admin/user-edit.php`

- [ ] **Step 1: Создать `admin/user-edit.php`**

```php
<?php
/**
 * Admin: создание/редактирование пользователя.
 * GET  ?id=N   — форма редактирования
 * GET  без id  — форма создания
 * POST ?action=update  — сохранить изменения
 * POST ?action=send-reset — отправить ссылку на сброс
 * POST ?action=delete  — удалить пользователя
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/config/database.php';
require_once __DIR__ . '/../includes/auth/check_auth.php';
require_once __DIR__ . '/../includes/flash.php';

requireAdmin();

$pdo = getDBConnection();
$csrf = generateCSRFToken();

$userId = (int)($_GET['id'] ?? 0);
$isEdit = $userId > 0;
$currentUserId = (int)$_SESSION['user_id'];

// === POST-обработчики ===

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($token)) {
        http_response_code(403);
        exit('Invalid CSRF token');
    }

    $action = $_GET['action'] ?? '';
    $postId = (int)($_GET['id'] ?? 0);
    if ($postId <= 0 && $action !== 'create') {
        flashSet('error', 'Некорректный запрос.');
        header('Location: users.php');
        exit;
    }

    // === DELETE ===
    if ($action === 'delete') {
        if ($postId === $currentUserId) {
            flashSet('error', 'Нельзя удалить собственный аккаунт.');
            header('Location: user-edit.php?id=' . $postId);
            exit;
        }
        $stmt = $pdo->prepare("SELECT role, is_active FROM users WHERE id = ?");
        $stmt->execute([$postId]);
        $t = $stmt->fetch();
        if (!$t) {
            flashSet('error', 'Пользователь не найден.');
            header('Location: users.php');
            exit;
        }
        $isLastAdmin = (function (PDO $pdo) use ($t): bool {
            $count = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND is_active=1")->fetchColumn();
            if ($count > 1) return false;
            return $t['role'] === 'admin' && (int)$t['is_active'] === 1;
        })($pdo);
        if ($isLastAdmin) {
            flashSet('error', 'Нельзя удалить единственного активного администратора.');
            header('Location: user-edit.php?id=' . $postId);
            exit;
        }
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$postId]);
        flashSet('success', 'Пользователь удалён.');
        header('Location: users.php');
        exit;
    }

    // === SEND RESET ===
    if ($action === 'send-reset' && $postId > 0) {
        $plain = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plain);
        $pdo->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, NOW() + INTERVAL 1 HOUR)")
            ->execute([$postId, $tokenHash]);
        $stmt = $pdo->prepare("SELECT email, first_name FROM users WHERE id = ?");
        $stmt->execute([$postId]);
        $u = $stmt->fetch();
        if ($u) {
            $resetUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/reset-password.php?token=' . urlencode($plain);
            $html = '<h2>Здравствуйте, ' . escape($u['first_name']) . '!</h2>'
                  . '<p>По вашему запросу создана ссылка для сброса пароля.</p>'
                  . '<p><a href="' . escape($resetUrl) . '">Сбросить пароль</a></p>'
                  . '<p>Ссылка действительна 1 час. Если вы не запрашивали сброс — проигнорируйте письмо.</p>';
            Mailer::send($u['email'], 'Сброс пароля в Elsesser & Co.', $html);
            flashSet('success', 'Ссылка на сброс отправлена на email пользователя.');
        }
        header('Location: user-edit.php?id=' . $postId);
        exit;
    }

    // === UPDATE / CREATE ===
    $errors = [];
    $email      = trim((string)($_POST['email'] ?? ''));
    $firstName  = trim((string)($_POST['first_name'] ?? ''));
    $lastName   = trim((string)($_POST['last_name'] ?? ''));
    $phone      = trim((string)($_POST['phone'] ?? ''));
    $role       = (string)($_POST['role'] ?? 'user');
    $isActive   = isset($_POST['is_active']) ? 1 : 0;
    $password   = (string)($_POST['password'] ?? '');
    $sendEmail  = !empty($_POST['send_email']);

    // Валидация
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Некорректный email.';
    } else {
        // Уникальность
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $postId]);
        if ($stmt->fetch()) {
            $errors[] = 'Пользователь с таким email уже существует.';
        }
    }
    if (mb_strlen($firstName) < 2 || mb_strlen($firstName) > 100) {
        $errors[] = 'Имя должно быть от 2 до 100 символов.';
    }
    if (mb_strlen($lastName) < 2 || mb_strlen($lastName) > 100) {
        $errors[] = 'Фамилия должна быть от 2 до 100 символов.';
    }
    if ($phone !== '' && !preg_match('/^[+\d\s()-]{6,20}$/', $phone)) {
        $errors[] = 'Телефон имеет недопустимый формат.';
    }
    if (!in_array($role, ['user', 'agent', 'admin'], true)) {
        $errors[] = 'Недопустимая роль.';
    }
    if ($action === 'create') {
        if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            $errors[] = 'Пароль должен быть не короче 8 символов и содержать буквы и цифры.';
        }
    }

    // Доп. защиты для редактирования
    if ($action === 'update' && $postId > 0) {
        if ($postId === $currentUserId && !$isActive) {
            $errors[] = 'Нельзя заблокировать собственный аккаунт.';
        }
        // Защита «не последний админ»
        $stmt = $pdo->prepare("SELECT role, is_active FROM users WHERE id = ?");
        $stmt->execute([$postId]);
        $t = $stmt->fetch();
        if ($t && $t['role'] === 'admin' && (int)$t['is_active'] === 1) {
            $count = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND is_active=1")->fetchColumn();
            if ($count === 1 && ($role !== 'admin' || !$isActive)) {
                $errors[] = 'Нельзя понизить/заблокировать единственного активного администратора.';
            }
        }
    }

    if (!empty($errors)) {
        $_SESSION['__form_errors'] = $errors;
        $_SESSION['__form_data']   = compact('email', 'firstName', 'lastName', 'phone', 'role', 'isActive', 'password');
        $redirect = $action === 'create' ? 'user-edit.php' : 'user-edit.php?id=' . $postId;
        header('Location: ' . $redirect);
        exit;
    }

    if ($action === 'create') {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $ins = $pdo->prepare("
            INSERT INTO users (email, password_hash, first_name, last_name, phone, role, is_active, email_verified_at, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $ins->execute([$email, $hash, $firstName, $lastName, $phone ?: null, $role, $isActive]);
        $newId = (int)$pdo->lastInsertId();

        if ($sendEmail) {
            $html = '<h2>Здравствуйте, ' . escape($firstName) . '!</h2>'
                  . '<p>Для вас создан аккаунт в Elsesser & Co.</p>'
                  . '<p><b>Email для входа:</b> ' . escape($email) . '</p>'
                  . '<p><b>Временный пароль:</b> ' . escape($password) . '</p>'
                  . '<p style="color:#dc2626">⚠ Рекомендуем сменить пароль после первого входа. Удалите это письмо после использования.</p>'
                  . '<p>Ссылка для входа: <a href="https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/login.php">войти</a>.</p>';
            Mailer::send($email, 'Добро пожаловать в Elsesser & Co.', $html);
        }

        flashSet('success', 'Пользователь создан.' . ($sendEmail ? ' Письмо с паролем отправлено.' : ''));
        header('Location: users.php');
        exit;
    }

    if ($action === 'update' && $postId > 0) {
        $upd = $pdo->prepare("
            UPDATE users SET first_name = ?, last_name = ?, phone = ?, role = ?, is_active = ?
            WHERE id = ?
        ");
        $upd->execute([$firstName, $lastName, $phone ?: null, $role, $isActive, $postId]);
        flashSet('success', 'Сохранено.');
        header('Location: users.php');
        exit;
    }

    flashSet('error', 'Неизвестное действие.');
    header('Location: users.php');
    exit;
}

// === GET: рендер формы ===

$user = null;
if ($isEdit) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) {
        http_response_code(404);
        echo '<h1>Пользователь не найден</h1><p><a href="users.php">← Назад</a></p>';
        exit;
    }
}

// Форма с ошибками
$formErrors = $_SESSION['__form_errors'] ?? null;
$formData   = $_SESSION['__form_data']   ?? null;
unset($_SESSION['__form_errors'], $_SESSION['__form_data']);

// Заполнение полей: либо из БД, либо из POST (если были ошибки)
if ($user) {
    $email     = $user['email'];
    $firstName = $user['first_name'];
    $lastName  = $user['last_name'];
    $phone     = $user['phone'] ?? '';
    $role      = $user['role'];
    $isActive  = (int)$user['is_active'];
    $created   = $user['created_at'];
    $verified  = $user['email_verified_at'];
    $oauth     = $user['oauth_provider'] ?? null;
    $failed    = (int)($user['failed_login_attempts'] ?? 0);
    $locked    = $user['locked_until'] ?? null;
    $userIdD   = (int)$user['id'];
}
if ($formData) {
    $email     = $formData['email'];
    $firstName = $formData['firstName'];
    $lastName  = $formData['lastName'];
    $phone     = $formData['phone'];
    $role      = $formData['role'];
    $isActive  = $formData['isActive'];
}

$pageTitle = $isEdit ? 'Редактирование пользователя #' . $userId : 'Создание пользователя';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($pageTitle) ?> | Elsesser & Co.</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../css/reset.css">
    <link rel="stylesheet" href="../css/variables.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body class="admin-body">
    <?php include __DIR__ . '/includes/admin-header.php'; ?>
    <div class="admin-container">
        <?php include __DIR__ . '/includes/admin-sidebar.php'; ?>
        <main class="admin-main">
            <?= flashRender() ?>

            <?php if (!empty($formErrors)): ?>
                <div class="alert alert--error">
                    <ul>
                        <?php foreach ($formErrors as $e): ?>
                            <li><?= escape($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="admin-header">
                <div>
                    <h1 class="admin-title"><?= escape($pageTitle) ?></h1>
                    <?php if ($isEdit): ?>
                        <div class="admin-breadcrumb">
                            <a href="users.php"><i class="fas fa-arrow-left"></i> К списку пользователей</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <form method="POST" action="user-edit.php?action=<?= $isEdit ? 'update&id=' . $userId : 'create' ?>" class="admin-card">
                <div class="admin-card__body">
                    <div class="admin-form-grid">
                        <div class="admin-form-column">
                            <div class="form-group">
                                <label class="form-label required">Email</label>
                                <input type="email" name="email" class="form-input" value="<?= escape($email ?? '') ?>" <?= $isEdit ? 'readonly' : 'required' ?>>
                                <?php if ($isEdit): ?><div class="form-help">Email нельзя изменить (связан с OAuth-аккаунтом и верификацией).</div><?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label class="form-label required">Имя</label>
                                <input type="text" name="first_name" class="form-input" value="<?= escape($firstName ?? '') ?>" required minlength="2" maxlength="100">
                            </div>
                            <div class="form-group">
                                <label class="form-label required">Фамилия</label>
                                <input type="text" name="last_name" class="form-input" value="<?= escape($lastName ?? '') ?>" required minlength="2" maxlength="100">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Телефон</label>
                                <input type="tel" name="phone" class="form-input" value="<?= escape($phone ?? '') ?>" placeholder="+7 912 345-67-89">
                            </div>
                        </div>
                        <div class="admin-form-column">
                            <div class="form-group">
                                <label class="form-label required">Роль</label>
                                <select name="role" class="form-select">
                                    <option value="user"  <?= ($role ?? 'user') === 'user'  ? 'selected' : '' ?>>Пользователь</option>
                                    <option value="agent" <?= ($role ?? 'user') === 'agent' ? 'selected' : '' ?>>Агент</option>
                                    <option value="admin" <?= ($role ?? 'user') === 'admin' ? 'selected' : '' ?>>Администратор</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="is_active" <?= ($isActive ?? 1) ? 'checked' : '' ?> <?= $isEdit && $userIdD === $currentUserId ? 'disabled' : '' ?>>
                                    <span>Активен (можно войти)</span>
                                </label>
                            </div>
                            <?php if (!$isEdit): ?>
                            <div class="form-group">
                                <label class="form-label required">Временный пароль</label>
                                <div style="display:flex; gap:8px;">
                                    <input type="text" name="password" id="passwordField" class="form-input" value="<?= escape($password ?? '') ?>" required minlength="8">
                                    <button type="button" class="btn btn--secondary btn--sm" onclick="document.getElementById('passwordField').value=generatePassword()">
                                        <i class="fas fa-sync"></i> Сгенерировать
                                    </button>
                                </div>
                                <div class="form-help">Минимум 8 символов, буквы и цифры.</div>
                            </div>
                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="send_email" checked>
                                    <span>Отправить пользователю письмо с логином и паролем</span>
                                </label>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="admin-card__header" style="justify-content:flex-end; gap: var(--space-3);">
                    <a href="users.php" class="btn btn--secondary">Отмена</a>
                    <button type="submit" class="btn btn--primary"><i class="fas fa-save"></i> <?= $isEdit ? 'Сохранить' : 'Создать' ?></button>
                </div>
            </form>

            <?php if ($isEdit): ?>
            <div class="admin-card">
                <div class="admin-card__header">
                    <div class="admin-card__title"><i class="fas fa-shield-alt"></i> Безопасность</div>
                </div>
                <div class="admin-card__body">
                    <div class="admin-form-grid">
                        <div class="admin-form-column">
                            <form method="POST" action="user-edit.php?action=send-reset&id=<?= $userId ?>" onsubmit="return confirm('Отправить пользователю ссылку на сброс пароля?')">
                                <input type="hidden" name="csrf_token" value="<?= escape($csrf) ?>">
                                <p class="text-sm text-muted">Пользователь получит email со ссылкой для сброса пароля (действует 1 час).</p>
                                <button type="submit" class="btn btn--secondary">
                                    <i class="fas fa-key"></i> Отправить ссылку на сброс пароля
                                </button>
                            </form>
                        </div>
                        <div class="admin-form-column">
                            <form method="POST" action="user-edit.php?action=delete&id=<?= $userId ?>" onsubmit="return confirm('Удалить пользователя <?= escape(addslashes($user['email'])) ?>? Это действие необратимо.')">
                                <input type="hidden" name="csrf_token" value="<?= escape($csrf) ?>">
                                <p class="text-sm text-muted">Пользователь и все его данные (заявки, избранное, отзывы) будут удалены.</p>
                                <button type="submit" class="btn btn--danger" <?= $userIdD === $currentUserId ? 'disabled' : '' ?>>
                                    <i class="fas fa-trash"></i> Удалить пользователя
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="admin-card">
                <div class="admin-card__header">
                    <div class="admin-card__title"><i class="fas fa-info-circle"></i> Информация</div>
                </div>
                <div class="admin-card__body">
                    <div class="admin-form-grid">
                        <div class="admin-form-column">
                            <p class="text-sm text-muted"><b>Зарегистрирован:</b> <?= escape($created) ?></p>
                            <p class="text-sm text-muted"><b>Email подтверждён:</b> <?= $verified ? escape($verified) : '<span class="text-muted">— нет —</span>' ?></p>
                            <p class="text-sm text-muted"><b>OAuth-провайдер:</b> <?= $oauth ? escape($oauth) : '—' ?></p>
                        </div>
                        <div class="admin-form-column">
                            <p class="text-sm text-muted"><b>Неудачных попыток входа:</b> <?= $failed ?></p>
                            <p class="text-sm text-muted"><b>locked_until:</b> <?= $locked ? escape($locked) : '—' ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
    function generatePassword() {
        const chars = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        let pwd = '';
        const arr = new Uint32Array(12);
        crypto.getRandomValues(arr);
        for (let i = 0; i < 12; i++) pwd += chars[arr[i] % chars.length];
        return pwd;
    }
    </script>
</body>
</html>
```

- [ ] **Step 2: Проверить синтаксис**

Run: `php -l D:\OSPanel\home\elsesserandco-site.local\admin\user-edit.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Smoke-test**

1. С `admin/users.php` нажать «Создать пользователя» → форма создания.
2. Заполнить все поля (Email — уникальный, не занятый), сгенерировать пароль кнопкой → создать.
3. В списке появился новый пользователь.
4. Кликнуть «✎ Редактировать» → форма редактирования.
5. Изменить имя → сохранить → flash «Сохранено» → редирект на список.
6. В режиме редактирования кнопка «Отправить ссылку на сброс» — после подтверждения flash «Ссылка отправлена». Проверить `logs/mail.log` (если `MAIL_TRANSPORT=log`).
7. Бан пользователя через список → редирект → flash «Заблокирован». Попытка входа под этим email → отказ.
8. Разбан через список → вход работает.
9. Подтверждение email через список (если `email_verified_at IS NULL`) → flash «Email подтверждён».
10. Создание с дублем email → форма с ошибкой «уже существует».
11. Создание с коротким паролем «123» → ошибка валидации.
12. Попытка сменить роль единственного админа на user → ошибка.
13. Попытка заблокировать самого себя (чекбокс `is_active` должен быть disabled на собственной странице; в списке — кнопка бана не отображается).
14. Попытка удалить самого себя → кнопка disabled.
15. Удаление обычного пользователя → flash «Удалён» → нет его в списке, нет его заявок/избранного/отзывов.

- [ ] **Step 4: Коммит**

```bash
git add admin/user-edit.php
git commit -m "feat(admin): user create/edit page with safety checks"
```

---

## Task 3: Добавить пункт «Пользователи» в sidebar

**Files:**
- Modify: `admin/includes/admin-sidebar.php`

- [ ] **Step 1: Добавить новую секцию**

В `admin/includes/admin-sidebar.php` найти блок `<!-- Sidebar -->` → сразу **перед** `<!-- Main Content -->` добавить новую секцию после текущего `</nav>` или встроить в существующий `<nav class="admin-nav">`.

Открыть файл и найти место перед `</nav>`. Перед `</nav>` (строка 86 в текущей версии) добавить:

```php
        <div class="admin-nav__divider">Администрирование</div>

        <a href="users.php" class="admin-nav__item <?= $currentPage === 'users.php' ? 'admin-nav__item--active' : '' ?>">
            <i class="fas fa-users"></i>
            <span>Пользователи</span>
            <?php
            $newUsersStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= NOW() - INTERVAL 7 DAY");
            $newUsersCount = (int)$newUsersStmt->fetchColumn();
            if ($newUsersCount > 0):
            ?>
            <span class="admin-nav__badge"><?= $newUsersCount ?></span>
            <?php endif; ?>
        </a>
```

**Важно:** `$pdo` уже доступен в `admin-sidebar.php` (его вызывает `admin/index.php` через `$pdo = getDBConnection();`). Если sidebar подключается до `$pdo` — нужно перенести подключение БД в `admin-header.php` (как уже сделано в `admin/includes/admin-header.php`, см. строку 16: `$pdo = getDBConnection();`). Подтверждено: `admin-header.php` уже объявляет `$pdo`, sidebar подключается после header, поэтому переменная доступна.

- [ ] **Step 2: Проверить синтаксис**

Run: `php -l D:\OSPanel\home\elsesserandco-site.local\admin\includes\admin-sidebar.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Smoke-test**

1. Войти админом → на любой странице админки → в sidebar появилась секция «Администрирование» с пунктом «Пользователи».
2. Кликнуть «Пользователи» → переход на `users.php`, пункт подсвечен как активный.
3. Если за последние 7 дней были регистрации — справа от «Пользователи» бейдж с числом.

- [ ] **Step 4: Коммит**

```bash
git add admin/includes/admin-sidebar.php
git commit -m "feat(admin): add 'Users' link to sidebar"
```

---

## Task 4: Финальный ручной smoke-тест

**Files:**
- Create: `docs/superpowers/test-plans/2026-06-08-admin-user-management.md`

- [ ] **Step 1: Создать чек-лист тест-плана**

```markdown
# Smoke-test: Управление пользователями в админке

**Дата:** 2026-06-08
**Сборка:** post-merge `feat/admin-user-management`

## Предусловия

- Локальный сервер `elsesserandco-site.local` запущен.
- БД `realestate_db` с применёнными миграциями 020–023.
- Тестовый админ: `admin@elsesserandco.com` (role=admin, is_active=1).
- Тестовый обычный пользователь: создан ранее через регистрацию (role=user).

## Чек-лист

| # | Шаг | Ожидаемо | ✓/✗ |
|---|-----|----------|------|
| 1 | Войти админом → `admin/users.php` | Список пользователей отображается, счётчик «N всего» верен | |
| 2 | Ввести в поиск часть email | Список фильтруется, URL содержит `?q=` | |
| 3 | Фильтр «Роль: Агент» | URL содержит `?role=agent`, в списке только агенты | |
| 4 | Фильтр «Статус: Заблокированные» | URL содержит `?status=blocked`, в списке только `is_active=0` | |
| 5 | Фильтр «Email: Не подтверждён» | В списке только пользователи с NULL `email_verified_at` | |
| 6 | Кнопка «Сбросить» при активном фильтре | Возвращает на чистый `users.php` | |
| 7 | Клик «Создать пользователя» | Переход на `user-edit.php` (без `?id`) | |
| 8 | Создание валидного пользователя | Flash «Пользователь создан», в списке новая строка, в `logs/mail.log` есть запись (если `MAIL_TRANSPORT=log`) | |
| 9 | Создание с дублем email | Форма повторно, ошибка «уже существует» | |
| 10 | Создание с паролем «123» | Ошибка валидации «Пароль должен быть не короче 8…» | |
| 11 | Создание с пустым обязательным полем | HTML5-валидация не пускает submit | |
| 12 | Редактирование: сменить имя | Flash «Сохранено», в списке новое имя | |
| 13 | Бан пользователя через список | Flash «Заблокирован», строка показывает «Заблокирован» | |
| 14 | Попытка входа под заблокированным | `login.php` отказывает (проверить текст ошибки) | |
| 15 | Разбан через список | Flash «Разблокирован», вход работает | |
| 16 | Подтверждение email через список | Flash «Email подтверждён», бейдж «Подтверждён» | |
| 17 | Отправка ссылки на сброс | Flash «Ссылка отправлена», в `password_resets` появилась запись, в `logs/mail.log` есть письмо | |
| 18 | Перейти по ссылке из письма (или скопировать токен из `password_resets` — там хэш, нужно из `mail.log`) | Открывается `reset-password.php?token=…`, форма смены пароля | |
| 19 | Бан самого себя | Кнопка «🔒» в списке отсутствует на текущем админе | |
| 20 | Снять «Активен» с самого себя в форме редактирования | Чекбокс `disabled` | |
| 21 | Попытка удалить самого себя | Кнопка «Удалить» `disabled` | |
| 22 | Создать временного второго админа → попробовать сменить ему роль на user → вернуть admin → попробовать заблокировать → снять флаг is_active на самом себе | Все попытки работают, т.к. админов > 1 | |
| 23 | Удалить временного второго админа → стать единственным → попробовать сменить себе роль | Ошибка «Нельзя понизить… единственного администратора» | |
| 24 | Удалить обычного пользователя | Flash «Удалён», пользователь и его заявки/избранное/отзывы исчезли | |
| 25 | POST без CSRF-токена (`curl -X POST …`) | 403, текст «Invalid CSRF token» | |
| 26 | SQL-инъекция в `?q=' OR 1=1--` | Нет эффекта, prepared statements работают | |
| 27 | Открыть `users.php` НЕ-админом (войти как user) | 403 «Доступ запрещён» (из `requireAdmin()`) | |
| 28 | Адаптивность: DevTools, viewport 1024×768 | Sidebar схлопывается, таблица имеет горизонтальный скролл | |
| 29 | Адаптивность: viewport 375×667 (iPhone SE) | Таблица скроллится горизонтально, кнопки не обрезаны | |
| 30 | Проверить, что в sidebar есть пункт «Пользователи» с бейджом (если есть новые за 7 дней) | Бейдж отображается | |

## Результат

**Все 30 шагов пройдены — фича готова к мерджу в main и деплою.**
```

- [ ] **Step 2: Пройти все 30 шагов по чек-листу**

Открыть в браузере, выполнять по порядку, проставлять ✓/✗.

- [ ] **Step 3: Коммит чек-листа**

```bash
git add docs/superpowers/test-plans/2026-06-08-admin-user-management.md
git commit -m "test(admin): smoke-test checklist for user management"
```

---

## Task 5: Финальный коммит + push (если попросят)

**Files:** (нет, это операция git)

- [ ] **Step 1: Проверить, что все задачи 0-4 закоммичены**

```bash
git log --oneline -5
```

Должны быть видны:
- `feat(admin): flash message helper`
- `feat(admin): user list with filters…`
- `feat(admin): user create/edit page…`
- `feat(admin): add 'Users' link to sidebar`
- `test(admin): smoke-test checklist…`

- [ ] **Step 2: Пуш (по запросу пользователя)**

```bash
git push origin main
```

---

## Сводка

| Что | Где | Строк (примерно) |
|-----|-----|------------------|
| Helper flash | `includes/flash.php` | 50 |
| Список | `admin/users.php` | 240 |
| Создание/редактирование | `admin/user-edit.php` | 320 |
| Sidebar | `admin/includes/admin-sidebar.php` | +14 |
| Чек-лист теста | `docs/superpowers/test-plans/…` | 60 |
| **Миграций** | — | **0** |
| **Правок CSS** | — | **0** |

Общий объём: **5 коммитов, ~680 строк нового кода, 0 правок существующего кода кроме sidebar.**
