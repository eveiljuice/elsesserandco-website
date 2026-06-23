<?php
/**
 * Reset Password — установка нового пароля.
 *
 * Пользователь попадает сюда после одобрения заявки админом
 * (на /forgot-password.php). Авторизация — через сессию
 * (password_reset_user_id + password_reset_request_id).
 *
 * После успешной смены пароля:
 *  - заявка получает status='used', used_at=NOW()
 *  - сессионные флаги сбрасываются
 *  - все активные сессии пользователя остаются (пароль меняется, токены сессий — нет)
 */

require_once __DIR__ . '/includes/config/database.php';
require_once __DIR__ . '/includes/auth/check_auth.php';

if (isLoggedIn()) {
    header('Location: /dashboard.php');
    exit;
}

if (empty($_SESSION['password_reset_user_id']) || empty($_SESSION['password_reset_request_id'])) {
    // Нет одобренной заявки в сессии — отправляем на forgot
    header('Location: /forgot-password.php');
    exit;
}

$pdo = getDBConnection();
$userId  = (int)$_SESSION['password_reset_user_id'];
$reqId   = (int)$_SESSION['password_reset_request_id'];

// Проверяем, что заявка всё ещё approved и не использована
$stmt = $pdo->prepare("
    SELECT r.*, u.email, u.first_name
    FROM password_reset_requests r
    JOIN users u ON u.id = r.user_id
    WHERE r.id = ? AND r.user_id = ? AND r.status = 'approved'
    LIMIT 1
");
$stmt->execute([$reqId, $userId]);
$request = $stmt->fetch();

if (!$request) {
    // Заявка уже использована, отклонена, или удалена
    unset($_SESSION['password_reset_user_id'], $_SESSION['password_reset_request_id']);
    header('Location: /forgot-password.php');
    exit;
}

$errors = [];
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Ошибка безопасности. Обновите страницу.';
    } else {
        $pwd     = $_POST['password'] ?? '';
        $confirm = $_POST['password_confirm'] ?? '';

        if (empty($pwd)) {
            $errors[] = 'Введите новый пароль';
        } elseif (strlen($pwd) < 8) {
            $errors[] = 'Пароль должен содержать минимум 8 символов';
        } elseif (!preg_match('/[A-Za-z]/', $pwd) || !preg_match('/[0-9]/', $pwd)) {
            $errors[] = 'Пароль должен содержать буквы и цифры';
        } elseif ($pwd !== $confirm) {
            $errors[] = 'Пароли не совпадают';
        } else {
            try {
                $pdo->beginTransaction();

                // Обновляем пароль
                $hash = password_hash($pwd, PASSWORD_BCRYPT, ['cost' => 12]);
                $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
                    ->execute([$hash, $userId]);

                // Помечаем заявку использованной
                $pdo->prepare("
                    UPDATE password_reset_requests
                    SET status = 'used', used_at = NOW()
                    WHERE id = ? AND status = 'approved'
                ")->execute([$reqId]);

                $pdo->commit();

                // Сбрасываем сессионные флаги
                unset($_SESSION['password_reset_user_id'], $_SESSION['password_reset_request_id']);

                $done = true;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('reset password error: ' . $e->getMessage());
                $errors[] = 'Не удалось обновить пароль. Попробуйте позже.';
            }
        }
    }
}

$csrf = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Новый пароль | Elsesser & Co.</title>
    <link rel="icon" type="image/png" href="images/favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="css/reset.css">
    <link rel="stylesheet" href="css/variables.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/auth.css">
    <link rel="stylesheet" href="css/responsive.css">
</head>
<body class="auth-page">
    <header class="header header--solid">
        <div class="container">
            <div class="header__inner">
                <a href="index.php" class="header__logo">
                    <span class="header__logo-text">Elsesser & Co.</span>
                </a>
            </div>
        </div>
    </header>

    <section class="auth-section">
        <div class="container">
            <div class="auth-container">
                <div class="auth-form-wrapper">
                    <div class="auth-form">
                        <h1 class="auth-form__title">Новый пароль</h1>

                        <?php if ($done): ?>
                            <div class="alert alert--success">
                                <i class="fas fa-check-circle"></i>
                                Пароль обновлён. Можете войти с новым паролем.
                            </div>
                            <p class="auth-footer">
                                <a href="login.php" class="btn btn--primary btn--lg btn--full">
                                    <i class="fas fa-sign-in-alt"></i> Войти
                                </a>
                            </p>
                        <?php else: ?>
                            <p class="auth-form__subtitle">
                                Заявка для <code><?= escape($request['email']) ?></code> одобрена. Задайте новый пароль.
                            </p>

                            <?php if ($errors): ?>
                                <div class="alert alert--error">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <ul><?php foreach ($errors as $e): ?><li><?= escape($e) ?></li><?php endforeach; ?></ul>
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="reset-password.php" class="form" autocomplete="off">
                                <input type="hidden" name="csrf_token" value="<?= escape($csrf) ?>">
                                <div class="form-group">
                                    <label class="form-label" for="password">Новый пароль</label>
                                    <div class="form-input-wrapper">
                                        <i class="fas fa-lock"></i>
                                        <input type="password" id="password" name="password" class="form-input"
                                               minlength="8" required autofocus autocomplete="new-password"
                                               placeholder="Минимум 8 символов, буквы и цифры">
                                        <button type="button" class="password-toggle" onclick="togglePassword('password')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="password_confirm">Повторите пароль</label>
                                    <div class="form-input-wrapper">
                                        <i class="fas fa-lock"></i>
                                        <input type="password" id="password_confirm" name="password_confirm" class="form-input"
                                               minlength="8" required autocomplete="new-password">
                                        <button type="button" class="password-toggle" onclick="togglePassword('password_confirm')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn--primary btn--lg btn--full">
                                    <i class="fas fa-key"></i> Установить пароль
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="auth-image" style="background-image:url('https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=1920&q=80');">
                    <div class="auth-image__content">
                        <h2>Сильный пароль = спокойствие</h2>
                        <p>Минимум 8 символов, должны быть буквы и цифры.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script>
    function togglePassword(id) {
        var input = document.getElementById(id);
        var btn = input.parentElement.querySelector('.password-toggle i');
        if (input.type === 'password') {
            input.type = 'text';
            btn.classList.remove('fa-eye');
            btn.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            btn.classList.remove('fa-eye-slash');
            btn.classList.add('fa-eye');
        }
    }
    </script>

    <?php include __DIR__ . '/includes/cookie-banner.php'; ?>
</body>
</html>
