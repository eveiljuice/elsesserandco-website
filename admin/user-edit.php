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
