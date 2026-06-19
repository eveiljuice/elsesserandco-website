<?php
/**
 * Политика конфиденциальности и согласие на обработку персональных данных.
 *
 * По 152-ФЗ: пользователь должен иметь возможность прочитать документ
 * ДО отправки формы регистрации, поставить обязательную отметку и только
 * после этого продолжить. Источник паттерна — novator-group.ru/page/ppd.
 *
 * Принимает ?return=/some/url — куда редиректить после согласия.
 * По умолчанию возвращает на /register.php?pd_consent=1.
 */

require_once __DIR__ . '/includes/config/Config.php';
require_once __DIR__ . '/includes/auth/check_auth.php';

session_start();

$returnTo = (string)($_GET['return'] ?? '/register.php');
// safe relative URL
if (!str_starts_with($returnTo, '/') || str_starts_with($returnTo, '//')) {
    $returnTo = '/register.php';
}

$pdfPath = __DIR__ . '/docs/personal-data-consent.pdf';
$pdfExists = is_file($pdfPath) && filesize($pdfPath) > 200;

// Если уже было согласие в этой сессии — сразу редиректим обратно
if (!empty($_SESSION['pd_consent']) && isset($_GET['return'])) {
    header('Location: ' . $returnTo . (str_contains($returnTo, '?') ? '&' : '?') . 'pd_consent=1');
    exit;
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Сессия истекла. Перезагрузите страницу.';
    } elseif (empty($_POST['pd_agree'])) {
        $error = 'Чтобы продолжить, нужно подтвердить согласие.';
    } else {
        $_SESSION['pd_consent'] = true;
        header('Location: ' . $returnTo . (str_contains($returnTo, '?') ? '&' : '?') . 'pd_consent=1');
        exit;
    }
}

$csrf = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Политика конфиденциальности и согласие на обработку персональных данных Elsesser &amp; Co.">
    <title>Политика конфиденциальности | Elsesser &amp; Co.</title>

    <link rel="icon" type="image/png" href="images/favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="css/reset.css">
    <link rel="stylesheet" href="css/variables.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/auth.css">
    <link rel="stylesheet" href="css/responsive.css">
    <style>
        .ppd-section { padding: 48px 0 80px; background: #f8f9fa; min-height: calc(100vh - 80px); }
        .ppd-container { max-width: 920px; margin: 0 auto; background: #fff; padding: 40px 48px; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,.04); }
        .ppd-container h1 { font-family: 'Playfair Display', serif; font-size: 30px; margin: 0 0 12px; color: #1a2447; }
        .ppd-container .ppd-lead { color: #555; font-size: 15px; margin: 0 0 28px; line-height: 1.6; }
        .ppd-container .ppd-meta { display: flex; flex-wrap: wrap; gap: 24px; margin: 0 0 28px; padding: 16px 20px; background: #f5f7fa; border-radius: 8px; font-size: 13px; color: #555; }
        .ppd-container .ppd-meta strong { color: #1a2447; display: block; margin-bottom: 2px; }
        .ppd-pdf-wrap { position: relative; width: 100%; height: 600px; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden; background: #f5f5f5; }
        .ppd-pdf-wrap iframe, .ppd-pdf-wrap object { width: 100%; height: 100%; border: 0; }
        .ppd-pdf-fallback { padding: 40px 24px; text-align: center; color: #888; font-size: 14px; line-height: 1.6; }
        .ppd-pdf-fallback i { font-size: 36px; margin-bottom: 12px; display: block; color: #ccc; }
        .ppd-download { display: inline-flex; align-items: center; gap: 6px; margin-top: 12px; color: #00736c; text-decoration: underline; font-size: 14px; }
        .ppd-form { margin-top: 32px; padding: 24px; background: #fafbfc; border: 1px solid #e8eaee; border-radius: 8px; }
        .ppd-form .ppd-checkbox { display: flex; align-items: flex-start; gap: 12px; cursor: pointer; font-size: 15px; color: #1a2447; }
        .ppd-form .ppd-checkbox input[type="checkbox"] { width: 22px; height: 22px; margin-top: 2px; flex-shrink: 0; cursor: pointer; }
        .ppd-form .ppd-submit { margin-top: 18px; padding: 12px 28px; background: #00736c; color: #fff; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; transition: background .15s ease; }
        .ppd-form .ppd-submit:disabled { background: #c2c8d0; cursor: not-allowed; }
        .ppd-form .ppd-submit:not(:disabled):hover { background: #005852; }
        .ppd-error { padding: 12px 16px; background: #fee; color: #c00; border-radius: 6px; margin-bottom: 16px; font-size: 14px; }
        .ppd-back { display: inline-flex; align-items: center; gap: 6px; margin-bottom: 24px; color: #555; text-decoration: none; font-size: 14px; }
        .ppd-back:hover { color: #00736c; }
        @media (max-width: 720px) {
            .ppd-container { padding: 24px 20px; }
            .ppd-pdf-wrap { height: 480px; }
            .ppd-container h1 { font-size: 24px; }
        }
    </style>
</head>
<body class="auth-page">
    <!-- Header -->
    <header class="header header--solid" id="header">
        <div class="container">
            <div class="header__inner">
                <a href="index.php" class="header__logo">
                    <span class="header__logo-text">Elsesser &amp; Co.</span>
                </a>
                <nav class="nav">
                    <ul class="nav__list">
                        <li><a href="properties.php?category=sale" class="nav__link">Купить</a></li>
                        <li><a href="properties.php?category=rent" class="nav__link">Аренда</a></li>
                        <li><a href="new-buildings.php" class="nav__link">Новостройки</a></li>
                        <li><a href="about.html" class="nav__link">О нас</a></li>
                    </ul>
                    <a href="login.php" class="btn btn--secondary">Войти</a>
                </nav>
                <button class="hamburger" id="hamburger" aria-label="Открыть меню">
                    <span class="hamburger__line"></span>
                    <span class="hamburger__line"></span>
                    <span class="hamburger__line"></span>
                </button>
            </div>
        </div>
    </header>

    <?php include __DIR__ . '/includes/mobile-menu.php'; ?>

    <section class="ppd-section">
        <div class="container">
            <div class="ppd-container">
                <a href="<?= htmlspecialchars($returnTo) ?>" class="ppd-back">
                    <i class="fas fa-arrow-left"></i> Назад
                </a>

                <h1>Политика конфиденциальности<br>и согласие на обработку персональных данных</h1>
                <p class="ppd-lead">
                    В соответствии с Федеральным законом № 152-ФЗ «О персональных данных» мы запрашиваем
                    ваше явное согласие на обработку предоставленных данных. Пожалуйста, прочитайте документ
                    ниже и подтвердите согласие, чтобы продолжить регистрацию.
                </p>

                <div class="ppd-meta">
                    <div>
                        <strong>Оператор</strong>
                        ООО «Эльсессер и Ко»
                    </div>
                    <div>
                        <strong>Цель обработки</strong>
                        Регистрация аккаунта, оказание услуг по подбору недвижимости, обратная связь.
                    </div>
                    <div>
                        <strong>Срок хранения</strong>
                        До отзыва согласия или удаления аккаунта.
                    </div>
                </div>

                <div class="ppd-pdf-wrap">
                    <?php if ($pdfExists): ?>
                        <iframe src="/docs/personal-data-consent.pdf" title="Согласие на обработку персональных данных — PDF" loading="lazy"></iframe>
                    <?php else: ?>
                        <div class="ppd-pdf-fallback">
                            <i class="fas fa-file-pdf"></i>
                            <p>Документ временно недоступен.<br>
                            Свяжитесь с нами по адресу <a href="mailto:info@elsesserandco.com">info@elsesserandco.com</a>,
                            чтобы получить актуальную версию согласия.</p>
                            <a href="/docs/personal-data-consent.pdf" target="_blank" class="ppd-download">
                                <i class="fas fa-download"></i> Открыть файл
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($error): ?>
                    <div class="ppd-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" class="ppd-form" id="pdForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <label class="ppd-checkbox">
                        <input type="checkbox" name="pd_agree" id="pdAgree" required>
                        <span>
                            Я ознакомлен и согласен с условиями обработки персональных данных,
                            изложенными в документе выше.
                        </span>
                    </label>
                    <button type="submit" class="ppd-submit" id="pdSubmit" disabled>
                        <i class="fas fa-check"></i> Принять и продолжить
                    </button>
                </form>
            </div>
        </div>
    </section>

    <script src="js/navigation.js"></script>
    <script>
        (function () {
            var cb = document.getElementById('pdAgree');
            var btn = document.getElementById('pdSubmit');
            if (!cb || !btn) return;
            function sync() { btn.disabled = !cb.checked; }
            cb.addEventListener('change', sync);
            sync();
        })();
    </script>
    <?php include __DIR__ . '/includes/cookie-banner.php'; ?>
</body>
</html>