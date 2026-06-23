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

// OAuth-провайдеры. Каждый имеет SVG-иконку (inline) — стилизованные
// brand-кнопки вместо общих FontAwesome.
// 'onetap' = true — отдельный OneTap-блок (VK), не дублируем в списке.
$oauthProviders = [
    'vk' => [
        'label'   => 'VK ID',
        'url'     => '/oauth/vk/start.php',
        'enabled' => (bool)Config::get('VK_CLIENT_ID'),
        'onetap'  => true,
        'bg'      => '#0077FF',
        'fg'      => '#FFFFFF',
        'svg'     => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12.785 16.241s.288-.032.435-.193c.135-.148.131-.426.131-.426s-.019-1.302.583-1.495c.594-.19 1.356 1.27 2.165 1.831.612.422 1.077.33 1.077.33l2.165-.03s1.131-.07.595-.964c-.044-.073-.312-.658-1.611-1.872-1.359-1.269-1.177-1.063.461-3.247.998-1.331 1.398-2.144 1.273-2.493-.118-.331-.853-.244-.853-.244l-2.436.015s-.181-.025-.315.056c-.131.079-.215.262-.215.262s-.387 1.035-.905 1.916c-1.091 1.857-1.527 1.955-1.704 1.84-.412-.265-.31-1.064-.31-1.633 0-1.778.27-2.518-.526-2.711-.265-.064-.46-.107-1.137-.114-.871-.009-1.609.003-2.027.205-.278.135-.493.435-.362.452.162.022.529.099.722.364.25.341.241 1.107.241 1.107s.144 2.106-.336 2.368c-.331.18-.784-.187-1.756-1.867-.5-.864-.877-1.819-.877-1.819s-.072-.176-.201-.27c-.155-.113-.372-.149-.372-.149l-2.314.015s-.347.01-.475.162c-.114.135-.009.413-.009.413s1.812 4.249 3.864 6.39c1.881 1.962 4.018 1.833 4.018 1.833z"/></svg>',
    ],
    'yandex' => [
        'label'   => 'Яндекс',
        'url'     => '/oauth/yandex/start.php',
        'enabled' => false, // отключён; код /oauth/yandex/ сохранён на случай возврата
        'hidden'  => true,  // полностью скрыт из списка на login/register
        'bg'      => '#FFCC00',
        'fg'      => '#000000',
        'svg'     => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M2.04 12c0-5.523 4.477-10 10-10s10 4.477 10 10-4.477 10-10 10c-1.957 0-3.785-.562-5.336-1.535l-3.616 1.115c-.477.135-.838-.36-.589-.787l1.708-2.998C3.215 16.81 2.04 14.522 2.04 12Zm10-7.46a1.46 1.46 0 0 1 1.46 1.46v6.367a1.46 1.46 0 1 1-2.92 0V6A1.46 1.46 0 0 1 12.04 4.54ZM6.5 12a1.46 1.46 0 0 0-2.92 0 1.46 1.46 0 0 0 2.92 0Zm9.6 0a1.46 1.46 0 0 0-2.92 0 1.46 1.46 0 0 0 2.92 0Zm-2.06 5.876 1.276 2.522c.16.315-.063.687-.418.687h-1.46a.5.5 0 0 1-.447-.276l-1.277-2.527a3.42 3.42 0 0 0 3.602-.406Z"/></svg>',
    ],
    'google' => [
        'label'   => 'Google',
        'url'     => '/oauth/google/start.php',
        'enabled' => false, // отключён; код /oauth/google/ сохранён на случай возврата
        'hidden'  => true,  // полностью скрыт из списка на login/register
        'bg'      => '#FFFFFF',
        'fg'      => '#1F1F1F',
        'border'  => '#DADCE0',
        'svg'     => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.99.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84A11 11 0 0 0 12 23z"/><path fill="#FBBC05" d="M5.84 14.1A6.6 6.6 0 0 1 5.5 12c0-.73.13-1.44.34-2.1V7.07H2.18A11 11 0 0 0 1 12c0 1.78.43 3.47 1.18 4.93l3.66-2.83z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.83C6.71 7.31 9.14 5.38 12 5.38z"/></svg>',
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
                                // Полностью скрытые провайдеры (отключены администратором) — не рисуем.
                                if (!empty($provider['hidden'])) continue;
                                $cls = 'oauth-btn--' . htmlspecialchars($providerKey);
                                if (!$provider['enabled']) {
                                    $cls .= ' oauth-btn--disabled';
                                }
                                $style  = '--oa-bg:' . htmlspecialchars($provider['bg']) . ';';
                                $style .= '--oa-fg:' . htmlspecialchars($provider['fg']) . ';';
                                if (!empty($provider['border'])) {
                                    $style .= '--oa-border:' . htmlspecialchars($provider['border']) . ';';
                                }
                            ?>
                            <?php if ($provider['enabled']): ?>
                            <a href="<?= htmlspecialchars($provider['url']) ?>"
                               class="oauth-btn <?= $cls ?>"
                               style="<?= $style ?>"
                               aria-label="Зарегистрироваться через <?= htmlspecialchars($provider['label']) ?>">
                                <span class="oauth-btn__icon"><?= $provider['svg'] ?></span><span><?= htmlspecialchars($provider['label']) ?></span>
                            </a>
                            <?php else: ?>
                            <span class="oauth-btn <?= $cls ?>" style="<?= $style ?>" aria-disabled="true" title="Провайдер не настроен администратором">
                                <span class="oauth-btn__icon"><?= $provider['svg'] ?></span><span><?= htmlspecialchars($provider['label']) ?></span>
                            </span>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
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
