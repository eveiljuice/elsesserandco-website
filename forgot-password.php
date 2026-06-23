<?php
/**
 * Forgot Password — запрос на сброс пароля (одобряет админ вручную).
 *
 * Поток:
 *  - Пользователь вводит email.
 *  - Если у него есть одобренная заявка (status=approved) и не использована
 *    (status!=used) — пропускаем на /reset-password.php (там вводит новый пароль).
 *  - Если есть pending — показываем «ожидайте одобрения».
 *  - Иначе — создаём новую заявку (status=pending).
 *  - Email НЕ отправляется.
 */

require_once __DIR__ . '/includes/config/database.php';
require_once __DIR__ . '/includes/auth/check_auth.php';

// Если пользователь уже залогинен — отправляем в ЛК (смена пароля там)
if (isLoggedIn()) {
    header('Location: /dashboard.php');
    exit;
}

$pdo = getDBConnection();

$errors = [];
$view   = 'form'; // 'form' | 'pending' | 'approved' | 'rejected' | 'unknown'
$formEmail = '';
$existingRequest = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Ошибка безопасности. Обновите страницу.';
    } else {
        $email = trim($_POST['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Введите корректный email';
        } else {
            // Ищем пользователя
            $stmt = $pdo->prepare("SELECT id, email, is_active FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !$user['is_active']) {
                // Не раскрываем, существует ли email. Но для UX показываем "ожидайте"
                $view = 'unknown';
            } else {
                $formEmail = $email;

                // Проверяем существующую активную заявку
                $stmt = $pdo->prepare("
                    SELECT * FROM password_reset_requests
                    WHERE user_id = ?
                      AND status IN ('pending','approved')
                    ORDER BY created_at DESC LIMIT 1
                ");
                $stmt->execute([$user['id']]);
                $existingRequest = $stmt->fetch();

                if ($existingRequest) {
                    if ($existingRequest['status'] === 'approved') {
                        $view = 'approved';
                        // Помечаем в сессии, что пользователь — одобренный владелец заявки.
                        // Это «слабый» токен — привязан к сессии, к IP, действует пока
                        // пользователь не нажмёт submit или не выйдет.
                        $_SESSION['password_reset_user_id'] = (int)$user['id'];
                        $_SESSION['password_reset_request_id'] = (int)$existingRequest['id'];
                    } else {
                        $view = 'pending';
                    }
                } else {
                    // Создаём заявку
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO password_reset_requests (user_id, email, status)
                            VALUES (?, ?, 'pending')
                        ");
                        $stmt->execute([$user['id'], $email]);
                        $view = 'pending';
                    } catch (PDOException $e) {
                        // Уникальный ключ uniq_user_pending мог сработать, если заявка уже есть
                        $view = 'pending';
                    }
                }
            }
        }
    }
} else {
    // GET: если в сессии уже есть одобренный сброс — показать форму
    if (!empty($_SESSION['password_reset_user_id']) && !empty($_SESSION['password_reset_request_id'])) {
        $stmt = $pdo->prepare("
            SELECT * FROM password_reset_requests
            WHERE id = ? AND user_id = ? AND status = 'approved'
            LIMIT 1
        ");
        $stmt->execute([$_SESSION['password_reset_request_id'], $_SESSION['password_reset_user_id']]);
        $existingRequest = $stmt->fetch();
        if ($existingRequest) {
            $view = 'approved';
            $formEmail = $existingRequest['email'];
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
    <title>Восстановление пароля | Elsesser & Co.</title>
    <link rel="icon" type="image/png" href="images/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
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
                        <h1 class="auth-form__title">Восстановление пароля</h1>

                        <?php if (!empty($errors)): ?>
                        <div class="alert alert--error">
                            <i class="fas fa-exclamation-circle"></i>
                            <ul><?php foreach ($errors as $e): ?><li><?= escape($e) ?></li><?php endforeach; ?></ul>
                        </div>
                        <?php endif; ?>

                        <?php if ($view === 'pending'): ?>
                            <div class="alert alert--info">
                                <i class="fas fa-hourglass-half"></i>
                                <div>
                                    <strong>Заявка отправлена администратору</strong>
                                    <p style="margin: 6px 0 0;">Мы получили ваш запрос на сброс пароля для <code><?= escape($formEmail) ?></code>. После одобрения администратором вы сможете задать новый пароль — просто вернитесь на эту страницу и введите тот же email.</p>
                                </div>
                            </div>
                            <form method="POST" action="forgot-password.php" class="form" style="margin-top: 16px;">
                                <input type="hidden" name="csrf_token" value="<?= escape($csrf) ?>">
                                <div class="form-group">
                                    <label for="email" class="form-label">Проверить статус заявки</label>
                                    <div class="form-input-wrapper">
                                        <i class="fas fa-envelope"></i>
                                        <input type="email" id="email" name="email" class="form-input" required
                                               value="<?= escape($formEmail) ?>" placeholder="your@email.com">
                                    </div>
                                </div>
                                <button type="submit" class="btn btn--secondary btn--lg btn--full">
                                    <i class="fas fa-rotate"></i> Проверить
                                </button>
                            </form>
                            <p class="auth-footer">
                                Одобрение обычно занимает от нескольких минут до 1 рабочего дня.<br>
                                <a href="login.php">Вспомнили пароль? Войти</a>
                            </p>

                        <?php elseif ($view === 'approved'): ?>
                            <div class="alert alert--success">
                                <i class="fas fa-check-circle"></i>
                                <div>
                                    <strong>Заявка одобрена!</strong>
                                    <p style="margin: 6px 0 0;">Администратор одобрил сброс пароля для <code><?= escape($formEmail) ?></code>. Задайте новый пароль — ссылка одноразовая.</p>
                                </div>
                            </div>
                            <p class="auth-footer" style="margin-top: 16px;">
                                <a href="reset-password.php" class="btn btn--primary btn--lg btn--full">
                                    <i class="fas fa-key"></i> Задать новый пароль
                                </a>
                            </p>

                        <?php elseif ($view === 'rejected'): ?>
                            <div class="alert alert--error">
                                <i class="fas fa-ban"></i>
                                <div>
                                    <strong>Заявка отклонена</strong>
                                    <?php if (!empty($existingRequest['rejection_reason'])): ?>
                                    <p style="margin: 6px 0 0;">Причина: <?= escape($existingRequest['rejection_reason'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>
                                    <p style="margin: 6px 0 0;">Вы можете подать заявку повторно.</p>
                                </div>
                            </div>
                            <form method="POST" action="forgot-password.php" class="form" style="margin-top: 16px;">
                                <input type="hidden" name="csrf_token" value="<?= escape($csrf) ?>">
                                <div class="form-group">
                                    <label for="email" class="form-label">Email</label>
                                    <div class="form-input-wrapper">
                                        <i class="fas fa-envelope"></i>
                                        <input type="email" id="email" name="email" class="form-input" required
                                               value="<?= escape($formEmail) ?>" placeholder="your@email.com">
                                    </div>
                                </div>
                                <button type="submit" class="btn btn--primary btn--lg btn--full">
                                    <i class="fas fa-paper-plane"></i> Подать новую заявку
                                </button>
                            </form>

                        <?php elseif ($view === 'unknown'): ?>
                            <div class="alert alert--info">
                                <i class="fas fa-hourglass-half"></i>
                                <div>
                                    <strong>Заявка отправлена</strong>
                                    <p style="margin: 6px 0 0;">Если такой email зарегистрирован, заявка на сброс пароля отправлена администратору. Вернитесь сюда позже, чтобы проверить статус.</p>
                                </div>
                            </div>

                        <?php else: ?>
                            <p class="auth-form__subtitle">Укажите email — администратор рассмотрит заявку и разрешит смену пароля</p>
                            <form method="POST" action="forgot-password.php" class="form">
                                <input type="hidden" name="csrf_token" value="<?= escape($csrf) ?>">
                                <div class="form-group">
                                    <label for="email" class="form-label">Email</label>
                                    <div class="form-input-wrapper">
                                        <i class="fas fa-envelope"></i>
                                        <input type="email" id="email" name="email" class="form-input" required autofocus
                                               placeholder="your@email.com" value="<?= escape($formEmail) ?>">
                                    </div>
                                </div>
                                <button type="submit" class="btn btn--primary btn--lg btn--full">
                                    <i class="fas fa-paper-plane"></i> Подать заявку
                                </button>
                            </form>
                            <p class="auth-footer">
                                <a href="login.php">Вспомнили пароль? Войти</a>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="auth-image" style="background-image:url('https://images.unsplash.com/photo-1512453979798-5ea266f8880c?w=1920&q=80');">
                    <div class="auth-image__content">
                        <h2>Сброс пароля</h2>
                        <p>Администратор рассмотрит заявку и разрешит смену пароля. Обычно это занимает несколько минут в рабочее время.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php include __DIR__ . '/includes/cookie-banner.php'; ?>
</body>
</html>
