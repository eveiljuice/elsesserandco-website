<?php
/**
 * Verify Email — обработка ссылки из письма.
 */

require_once __DIR__ . '/includes/config/database.php';
require_once __DIR__ . '/includes/auth/check_auth.php';
require_once __DIR__ . '/includes/auth/email_verification.php';

$token = trim($_GET['token'] ?? '');
$userId = consumeEmailVerification($token);
$ok = $userId !== null;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Подтверждение email | Elsesser & Co.</title>
    <link rel="icon" type="image/png" href="images/favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="css/reset.css">
    <link rel="stylesheet" href="css/variables.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/auth.css">
</head>
<body class="auth-page">
    <header class="header header--solid">
        <div class="container"><div class="header__inner"><a href="index.php" class="header__logo"><span class="header__logo-text">Elsesser & Co.</span></a></div></div>
    </header>
    <section class="auth-section">
        <div class="container" style="max-width:560px;margin:0 auto;text-align:center;padding:48px 16px;">
            <?php if ($ok): ?>
                <div style="font-size:64px;color:#10b981;margin-bottom:16px;"><i class="fas fa-check-circle"></i></div>
                <h1 style="font-family:'Playfair Display',serif;">Email подтверждён</h1>
                <p style="color:#666;margin:16px 0 32px;">Спасибо! Все возможности сайта теперь вам доступны.</p>
                <a href="dashboard.php" class="btn btn--primary btn--lg">Перейти в личный кабинет</a>
            <?php else: ?>
                <div style="font-size:64px;color:#ef4444;margin-bottom:16px;"><i class="fas fa-times-circle"></i></div>
                <h1 style="font-family:'Playfair Display',serif;">Ссылка недействительна</h1>
                <p style="color:#666;margin:16px 0 32px;">Возможно, она устарела или уже использована. Войдите в аккаунт и запросите новое письмо в личном кабинете.</p>
                <a href="login.php" class="btn btn--primary btn--lg">Войти</a>
            <?php endif; ?>
        </div>
    </section>
</body>
</html>
