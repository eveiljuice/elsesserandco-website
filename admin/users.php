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

    // Защита «не последний админ» (для бан/разбан)
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
