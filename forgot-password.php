<?php
/**
 * Forgot Password — запрос ссылки на сброс пароля.
 */

require_once __DIR__ . '/includes/config/database.php';
require_once __DIR__ . '/includes/auth/check_auth.php';
require_once __DIR__ . '/includes/auth/password_reset.php';

$errors = [];
$sent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Ошибка безопасности. Обновите страницу.';
    } else {
        $email = trim($_POST['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Введите корректный email';
        } else {
            $result = requestPasswordReset($email);
            $sent = true;
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
                        <p class="auth-form__subtitle">Укажите email — пришлём ссылку для смены пароля</p>

                        <?php if (!empty($errors)): ?>
                        <div class="alert alert--error">
                            <i class="fas fa-exclamation-circle"></i>
                            <ul><?php foreach ($errors as $e): ?><li><?= escape($e) ?></li><?php endforeach; ?></ul>
                        </div>
                        <?php endif; ?>

                        <?php if ($sent): ?>
                        <div class="alert alert--success">
                            <i class="fas fa-check-circle"></i>
                            Если такой email зарегистрирован — мы отправили на него письмо со ссылкой.
                            Ссылка действует 1 час.
                        </div>
                        <p class="auth-footer"><a href="login.php">← Вернуться ко входу</a></p>
                        <?php else: ?>
                        <form method="POST" action="forgot-password.php" class="form">
                            <input type="hidden" name="csrf_token" value="<?= escape($csrf) ?>">
                            <div class="form-group">
                                <label for="email" class="form-label">Email</label>
                                <div class="form-input-wrapper">
                                    <i class="fas fa-envelope"></i>
                                    <input type="email" id="email" name="email" class="form-input" required autofocus placeholder="your@email.com">
                                </div>
                            </div>
                            <button type="submit" class="btn btn--primary btn--lg btn--full">
                                <i class="fas fa-paper-plane"></i> Отправить ссылку
                            </button>
                        </form>
                        <p class="auth-footer"><a href="login.php">Вспомнили пароль? Войти</a></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="auth-image" style="background-image:url('https://images.unsplash.com/photo-1512453979798-5ea266f8880c?w=1920&q=80');">
                    <div class="auth-image__content">
                        <h2>Безопасный сброс пароля</h2>
                        <p>Ссылка одноразовая и действует час — даже если письмо перехватят, навредить не получится.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</body>
</html>
