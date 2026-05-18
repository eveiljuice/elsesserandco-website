<?php
/**
 * Reset Password — установка нового пароля по токену.
 */

require_once __DIR__ . '/includes/config/database.php';
require_once __DIR__ . '/includes/auth/check_auth.php';
require_once __DIR__ . '/includes/auth/password_reset.php';

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$errors = [];
$done = false;
$tokenValid = $token !== '' && verifyPasswordResetToken($token) !== null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Ошибка безопасности. Обновите страницу.';
    } elseif (!$tokenValid) {
        $errors[] = 'Ссылка недействительна или истекла';
    } else {
        $pwd = $_POST['password'] ?? '';
        $confirm = $_POST['password_confirm'] ?? '';
        if ($pwd !== $confirm) {
            $errors[] = 'Пароли не совпадают';
        } else {
            $res = applyPasswordReset($token, $pwd);
            if ($res['ok']) {
                $done = true;
            } else {
                $errors[] = $res['error'];
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
                                <i class="fas fa-check-circle"></i> Пароль обновлён. Можете войти с новым паролем.
                            </div>
                            <p class="auth-footer"><a href="login.php" class="btn btn--primary btn--lg btn--full">Войти</a></p>
                        <?php elseif (!$tokenValid): ?>
                            <div class="alert alert--error">
                                <i class="fas fa-exclamation-circle"></i>
                                Ссылка недействительна или истекла. Запросите новую.
                            </div>
                            <p class="auth-footer"><a href="forgot-password.php">Запросить новую ссылку</a></p>
                        <?php else: ?>
                            <?php if ($errors): ?>
                                <div class="alert alert--error">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <ul><?php foreach ($errors as $e): ?><li><?= escape($e) ?></li><?php endforeach; ?></ul>
                                </div>
                            <?php endif; ?>
                            <form method="POST" action="reset-password.php" class="form">
                                <input type="hidden" name="csrf_token" value="<?= escape($csrf) ?>">
                                <input type="hidden" name="token" value="<?= escape($token) ?>">
                                <div class="form-group">
                                    <label class="form-label" for="password">Новый пароль</label>
                                    <div class="form-input-wrapper">
                                        <i class="fas fa-lock"></i>
                                        <input type="password" id="password" name="password" class="form-input" minlength="8" required autofocus>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="password_confirm">Повторите пароль</label>
                                    <div class="form-input-wrapper">
                                        <i class="fas fa-lock"></i>
                                        <input type="password" id="password_confirm" name="password_confirm" class="form-input" minlength="8" required>
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
</body>
</html>
