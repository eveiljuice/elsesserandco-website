<?php
/**
 * Registration Page - Elsesser & Co.
 * Страница регистрации нового пользователя
 */

require_once __DIR__ . '/includes/config/Config.php';
$data = require_once __DIR__ . '/includes/auth/register.php';
$errors = $data['errors'];
$formData = $data['formData'];
$csrf_token = $data['csrf_token'];

// OAuth-провайдеры, поддерживаемые сайтом (Telegram выпилен).
// Кнопка всегда видна; если ключ не заполнен — disabled с подсказкой.
// 'onetap' = true — означает, что для провайдера есть отдельный OneTap-блок
// (для VK сейчас), и обычную кнопку из основного списка НЕ показываем.
$oauthProviders = [
    'vk' => [
        'label'   => 'VK',
        'icon'    => 'fab fa-vk',
        'url'     => '/oauth/vk/start.php',
        'enabled' => (bool)Config::get('VK_CLIENT_ID'),
        'onetap'  => true,
    ],
    'yandex' => [
        'label'   => 'Яндекс',
        'icon'    => 'fab fa-yandex',
        'url'     => '/oauth/yandex/start.php',
        'enabled' => (bool)Config::get('YANDEX_CLIENT_ID'),
    ],
    'google' => [
        'label'   => 'Google',
        'icon'    => 'fab fa-google',
        'url'     => '/oauth/google/start.php',
        'enabled' => (bool)Config::get('GOOGLE_CLIENT_ID'),
    ],
];

// Если пользователь подтвердил согласие на ПДн на отдельной странице,
// автопроставляем чекбокс agree в форме регистрации.
if (!empty($_GET['pd_consent']) && $_GET['pd_consent'] === '1' && empty($_SESSION['pd_consent'])) {
    $_SESSION['pd_consent'] = true;
}
$pdConsentGiven = !empty($_SESSION['pd_consent']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Регистрация в Elsesser & Co. — создайте аккаунт для управления избранным и получения персональных предложений.">
    <title>Регистрация | Elsesser & Co.</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="images/favicon.png">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Styles -->
    <link rel="stylesheet" href="css/reset.css">
    <link rel="stylesheet" href="css/variables.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/auth.css">
    <link rel="stylesheet" href="css/responsive.css">
</head>
<body class="auth-page">
    <!-- Header -->
    <header class="header header--solid" id="header">
        <div class="container">
            <div class="header__inner">
                <a href="index.php" class="header__logo">
                    <span class="header__logo-text">Elsesser & Co.</span>
                </a>
                
                <nav class="nav">
                    <ul class="nav__list">
                        <li><a href="properties.php?category=sale" class="nav__link">Купить</a></li>
                        <li><a href="properties.php?category=rent" class="nav__link">Аренда</a></li>
                        <li><a href="contact.php" class="nav__link">Продать</a></li>
                        <li><a href="about.php" class="nav__link">О нас</a></li>
                    </ul>
                    <a href="login.php" class="btn btn--secondary">Вход</a>
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

    <!-- Auth Section -->
    <section class="auth-section">
        <div class="container">
            <div class="auth-container">
                <div class="auth-form-wrapper">
                    <div class="auth-form">
                        <h1 class="auth-form__title">Создать аккаунт</h1>
                        <p class="auth-form__subtitle">Зарегистрируйтесь, чтобы сохранять избранное и получать персональные предложения</p>
                        
                        <?php if (!empty($errors)): ?>
                        <div class="alert alert--error">
                            <i class="fas fa-exclamation-circle"></i>
                            <ul>
                                <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="register.php" class="form" id="registerForm">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            
                            <div class="form-group form-group--half">
                                <div>
                                    <label for="first_name" class="form-label">Имя</label>
                                    <div class="form-input-wrapper">
                                        <i class="fas fa-user"></i>
                                        <input type="text" 
                                               id="first_name" 
                                               name="first_name" 
                                               class="form-input" 
                                               placeholder="Иван"
                                               value="<?= htmlspecialchars($formData['first_name']) ?>"
                                               required
                                               minlength="2"
                                               autofocus>
                                    </div>
                                </div>
                                <div>
                                    <label for="last_name" class="form-label">Фамилия</label>
                                    <div class="form-input-wrapper">
                                        <i class="fas fa-user"></i>
                                        <input type="text" 
                                               id="last_name" 
                                               name="last_name" 
                                               class="form-input" 
                                               placeholder="Петров"
                                               value="<?= htmlspecialchars($formData['last_name']) ?>"
                                               required
                                               minlength="2">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="email" class="form-label">Email</label>
                                <div class="form-input-wrapper">
                                    <i class="fas fa-envelope"></i>
                                    <input type="email" 
                                           id="email" 
                                           name="email" 
                                           class="form-input" 
                                           placeholder="your@email.com"
                                           value="<?= htmlspecialchars($formData['email']) ?>"
                                           required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="phone" class="form-label">Телефон <span class="form-label__optional">(необязательно)</span></label>
                                <div class="form-input-wrapper">
                                    <i class="fas fa-phone"></i>
                                    <input type="tel" 
                                           id="phone" 
                                           name="phone" 
                                           class="form-input" 
                                           placeholder="+7 912 345-67-89"
                                           value="<?= htmlspecialchars($formData['phone']) ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="password" class="form-label">Пароль</label>
                                <div class="form-input-wrapper">
                                    <i class="fas fa-lock"></i>
                                    <input type="password" 
                                           id="password" 
                                           name="password" 
                                           class="form-input" 
                                           placeholder="Минимум 8 символов"
                                           required
                                           minlength="8">
                                    <button type="button" class="password-toggle" onclick="togglePassword('password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="password-strength" id="passwordStrength"></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="password_confirm" class="form-label">Подтвердите пароль</label>
                                <div class="form-input-wrapper">
                                    <i class="fas fa-lock"></i>
                                    <input type="password" 
                                           id="password_confirm" 
                                           name="password_confirm" 
                                           class="form-input" 
                                           placeholder="Повторите пароль"
                                           required
                                           minlength="8">
                                    <button type="button" class="password-toggle" onclick="togglePassword('password_confirm')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="agree" class="checkbox" required <?= $pdConsentGiven ? 'checked' : '' ?>>
                                    <span class="checkbox-custom"></span>
                                    Я согласен с <a href="/privacy.php?return=/register.php" target="_blank">условиями использования</a> и
                                    <a href="/privacy.php?return=/register.php" target="_blank">политикой конфиденциальности</a>
                                </label>
                            </div>
                            
                            <button type="submit" class="btn btn--primary btn--lg btn--full">
                                <i class="fas fa-user-plus"></i>
                                Зарегистрироваться
                            </button>
                        </form>
                        
                        <p class="auth-footer">
                            Уже есть аккаунт? <a href="login.php">Войдите</a>
                        </p>
                        
                        <div class="auth-divider"><span>или зарегистрироваться через</span></div>

                        <?php
                        // VK ID OneTap (плашка/кнопка «Войти с VK») — показывается
                        // если VK_CLIENT_ID заполнен в .env
                        include __DIR__ . '/includes/vk-onetap.php';
                        ?>

                        <div class="oauth-buttons">
                            <?php foreach ($oauthProviders as $providerKey => $provider):
                                // Провайдеров с OneTap-блоком (VK) не дублируем в обычном списке.
                                if (!empty($provider['onetap'])) continue;
                                $cls = 'oauth-btn--' . htmlspecialchars($providerKey);
                                if (!$provider['enabled']) {
                                    $cls .= ' oauth-btn--disabled';
                                }
                            ?>
                            <?php if ($provider['enabled']): ?>
                            <a href="<?= htmlspecialchars($provider['url']) ?>" class="oauth-btn <?= $cls ?>" aria-label="Зарегистрироваться через <?= htmlspecialchars($provider['label']) ?>">
                                <i class="<?= htmlspecialchars($provider['icon']) ?>"></i><span><?= htmlspecialchars($provider['label']) ?></span>
                            </a>
                            <?php else: ?>
                            <span class="oauth-btn <?= $cls ?>" aria-disabled="true" title="Провайдер не настроен администратором">
                                <i class="<?= htmlspecialchars($provider['icon']) ?>"></i><span><?= htmlspecialchars($provider['label']) ?></span>
                            </span>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </div>

                        <p class="auth-footer" style="margin-top: 16px;">
                            <a href="register-developer.php" style="display: inline-flex; align-items: center; gap: 8px;">
                                <i class="fas fa-building"></i>
                                Регистрация для застройщиков
                            </a>
                        </p>
                    </div>
                </div>
                
                <div class="auth-image" style="background-image: url('https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?w=1920&q=80');">
                    <div class="auth-image__content">
                        <h2>Присоединяйтесь к Elsesser & Co.</h2>
                        <p>Получите доступ к эксклюзивным предложениям элитной недвижимости</p>
                        <ul class="auth-benefits">
                            <li><i class="fas fa-check"></i> Сохраняйте избранные объекты</li>
                            <li><i class="fas fa-check"></i> Получайте персональные рекомендации</li>
                            <li><i class="fas fa-check"></i> Первыми узнавайте о новых объектах</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Scripts -->
    <script src="js/navigation.js"></script>
    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.parentElement.querySelector('.password-toggle i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Проверка силы пароля
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthEl = document.getElementById('passwordStrength');
            let strength = 0;
            let text = '';
            let colorClass = '';
            
            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            
            switch(strength) {
                case 0:
                case 1:
                    text = 'Слабый пароль';
                    colorClass = 'strength-weak';
                    break;
                case 2:
                    text = 'Средний пароль';
                    colorClass = 'strength-medium';
                    break;
                case 3:
                    text = 'Хороший пароль';
                    colorClass = 'strength-good';
                    break;
                case 4:
                    text = 'Отличный пароль';
                    colorClass = 'strength-strong';
                    break;
            }
            
            if (password.length > 0) {
                strengthEl.innerHTML = `<span class="${colorClass}">${text}</span>`;
            } else {
                strengthEl.innerHTML = '';
            }
        });
        
        // Проверка совпадения паролей
        document.getElementById('password_confirm').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirm = this.value;
            
            if (confirm.length > 0 && password !== confirm) {
                this.setCustomValidity('Пароли не совпадают');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
    <?php include __DIR__ . '/includes/cookie-banner.php'; ?>
</body>
</html>
