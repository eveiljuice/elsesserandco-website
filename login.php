<?php
/**
 * Login Page - Elsesser & Co.
 * Страница входа в систему
 */

$data = require_once __DIR__ . '/includes/auth/login.php';
$errors = $data['errors'];
$email = $data['email'];
$csrf_token = $data['csrf_token'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Вход в личный кабинет Elsesser & Co. — управляйте избранным и получайте персональные предложения.">
    <title>Вход | Elsesser & Co.</title>

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
                        <li><a href="properties.php?type=sale" class="nav__link">Купить</a></li>
                        <li><a href="properties.php?type=rent" class="nav__link">Аренда</a></li>
                        <li><a href="contact.html" class="nav__link">Продать</a></li>
                        <li><a href="about.html" class="nav__link">О нас</a></li>
                    </ul>
                    <a href="register.php" class="btn btn--secondary">Регистрация</a>
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
                        <h1 class="auth-form__title">Вход в аккаунт</h1>
                        <p class="auth-form__subtitle">Войдите, чтобы управлять избранным и получать персональные предложения</p>
                        
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
                        
                        <form method="POST" action="login.php<?= isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>" class="form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            
                            <div class="form-group">
                                <label for="email" class="form-label">Email</label>
                                <div class="form-input-wrapper">
                                    <i class="fas fa-envelope"></i>
                                    <input type="email" 
                                           id="email" 
                                           name="email" 
                                           class="form-input" 
                                           placeholder="your@email.com"
                                           value="<?= htmlspecialchars($email) ?>"
                                           required 
                                           autofocus>
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
                                           placeholder="••••••••"
                                           required
                                           minlength="8">
                                    <button type="button" class="password-toggle" onclick="togglePassword('password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="form-group form-group--row">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="remember" class="checkbox">
                                    <span class="checkbox-custom"></span>
                                    Запомнить меня
                                </label>
                                <a href="#" class="form-link">Забыли пароль?</a>
                            </div>
                            
                            <button type="submit" class="btn btn--primary btn--lg btn--full">
                                <i class="fas fa-sign-in-alt"></i>
                                Войти
                            </button>
                        </form>
                       
                        <p class="auth-footer">
                            Ещё нет аккаунта? <a href="register.php">Зарегистрируйтесь</a>
                        </p>
                        
                        <div class="auth-divider"><span>или</span></div>
                        
                        <p class="auth-footer" style="margin-top: 0;">
                            <a href="register-developer.php" style="display: inline-flex; align-items: center; gap: 8px;">
                                <i class="fas fa-building"></i>
                                Регистрация для застройщиков
                            </a>
                        </p>
                    </div>
                </div>
                
                <div class="auth-image" style="background-image: url('https://images.unsplash.com/photo-1512453979798-5ea266f8880c?w=1920&q=80');">
                    <div class="auth-image__content">
                        <h2>Добро пожаловать в Elsesser & Co.</h2>
                        <p>Откройте для себя мир элитной недвижимости Екатеринбурга</p>
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
    </script>
</body>
</html>
