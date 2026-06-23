<?php
/**
 * Account Settings - личный кабинет с формой смены пароля.
 *
 * Доступно только залогиненным. Форма "Сменить пароль" требует
 * ввести текущий пароль + новый (с подтверждением).
 */

require_once __DIR__ . '/includes/config/database.php';
require_once __DIR__ . '/includes/auth/check_auth.php';

requireLogin('/account.php');

$pdo = getDBConnection();
$userId = getCurrentUserId();

$stmt = $pdo->prepare("SELECT id, first_name, last_name, email, phone, avatar, role FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userData = $stmt->fetch();

$errors = [];
$success = false;
$successMsg = '';

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Ошибка безопасности. Обновите страницу.';
    } else {
        $current = $_POST['current_password'] ?? '';
        $newPwd  = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (empty($current) || empty($newPwd) || empty($confirm)) {
            $errors[] = 'Заполните все поля';
        } else {
            // Проверяем текущий пароль
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $row = $stmt->fetch();

            if (!$row || !password_verify($current, $row['password_hash'])) {
                $errors[] = 'Текущий пароль введён неверно';
            } elseif (strlen($newPwd) < 8) {
                $errors[] = 'Новый пароль должен содержать минимум 8 символов';
            } elseif (!preg_match('/[A-Za-z]/', $newPwd) || !preg_match('/[0-9]/', $newPwd)) {
                $errors[] = 'Новый пароль должен содержать буквы и цифры';
            } elseif ($newPwd !== $confirm) {
                $errors[] = 'Новый пароль и подтверждение не совпадают';
            } elseif ($current === $newPwd) {
                $errors[] = 'Новый пароль должен отличаться от текущего';
            } else {
                try {
                    $hash = password_hash($newPwd, PASSWORD_BCRYPT, ['cost' => 12]);
                    $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
                        ->execute([$hash, $userId]);
                    $success = true;
                    $successMsg = 'Пароль успешно изменён. Используйте его при следующем входе.';
                } catch (PDOException $e) {
                    error_log('change password error: ' . $e->getMessage());
                    $errors[] = 'Не удалось обновить пароль. Попробуйте позже.';
                }
            }
        }
    }
}

$pageTitle = 'Аккаунт';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $pageTitle ?> | Elsesser & Co.</title>
    <link rel="icon" type="image/png" href="images/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="css/reset.css">
    <link rel="stylesheet" href="css/variables.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/auth.css">
    <link rel="stylesheet" href="css/responsive.css">
</head>
<body>
    <header class="header header--solid" id="header">
        <div class="container">
            <div class="header__inner">
                <a href="/index.php" class="header__logo">
                    <span class="header__logo-text">Elsesser & Co.</span>
                </a>
                <nav class="nav">
                    <ul class="nav__list">
                        <li><a href="/properties.php?category=sale" class="nav__link">Купить</a></li>
                        <li><a href="/properties.php?category=rent" class="nav__link">Аренда</a></li>
                        <li><a href="/favorites.php" class="nav__link">Избранное</a></li>
                    </ul>
                    <a href="/dashboard.php" class="btn btn--secondary">Личный кабинет</a>
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

    <main class="dashboard">
        <div class="container">
            <div class="dashboard__header">
                <div class="dashboard__welcome">
                    <h1>Аккаунт</h1>
                    <p>Безопасность и личные данные</p>
                </div>
                <div class="dashboard__actions">
                    <a href="/dashboard.php" class="btn btn--secondary">
                        <i class="fas fa-arrow-left"></i> Назад
                    </a>
                </div>
            </div>

            <div class="dashboard__grid">
                <!-- Профиль -->
                <section class="dashboard__section">
                    <div class="dashboard__section-header">
                        <h2><i class="fas fa-user"></i> Профиль</h2>
                    </div>

                    <div class="profile-card" style="margin-bottom: 24px;">
                        <div class="profile-card__avatar">
                            <?= strtoupper(substr($userData['first_name'], 0, 1) . substr($userData['last_name'] ?? '', 0, 1)) ?>
                        </div>
                        <h3 class="profile-card__name"><?= escape($userData['first_name'] . ' ' . $userData['last_name']) ?></h3>
                        <p class="profile-card__email"><?= escape($userData['email']) ?></p>
                        <p class="profile-card__date">
                            <?php if (!empty($userData['phone'])): ?>
                            Телефон: <?= escape($userData['phone']) ?><br>
                            <?php endif; ?>
                            Роль: <?= match($userData['role']) { 'admin' => 'Администратор', 'agent' => 'Агент', default => 'Пользователь' } ?>
                        </p>
                    </div>
                </section>

                <!-- Смена пароля -->
                <section class="dashboard__section">
                    <div class="dashboard__section-header">
                        <h2><i class="fas fa-key"></i> Сменить пароль</h2>
                    </div>

                    <?php if ($success): ?>
                    <div class="alert alert--success">
                        <i class="fas fa-check-circle"></i>
                        <div><strong><?= escape($successMsg) ?></strong></div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>
                    <div class="alert alert--error">
                        <i class="fas fa-exclamation-circle"></i>
                        <ul>
                            <?php foreach ($errors as $e): ?>
                            <li><?= escape($e) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <form method="POST" action="account.php" class="form" autocomplete="off" style="max-width: 480px;">
                        <input type="hidden" name="csrf_token" value="<?= escape(generateCSRFToken(), ENT_QUOTES, 'UTF-8') ?>">

                        <div class="form-group">
                            <label for="current_password" class="form-label">Текущий пароль <span style="color:#dc2626;">*</span></label>
                            <div class="form-input-wrapper">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="current_password" name="current_password"
                                       class="form-input" required minlength="1" autocomplete="current-password">
                                <button type="button" class="password-toggle" onclick="togglePassword('current_password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="new_password" class="form-label">Новый пароль <span style="color:#dc2626;">*</span></label>
                            <p class="form-hint" style="margin: 0 0 6px; color: var(--color-text-light); font-size: var(--text-sm);">Минимум 8 символов, должны быть буквы и цифры</p>
                            <div class="form-input-wrapper">
                                <i class="fas fa-key"></i>
                                <input type="password" id="new_password" name="new_password"
                                       class="form-input" required minlength="8"
                                       pattern="(?=.*[A-Za-z])(?=.*[0-9]).{8,}"
                                       title="Минимум 8 символов, буквы и цифры"
                                       autocomplete="new-password">
                                <button type="button" class="password-toggle" onclick="togglePassword('new_password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password" class="form-label">Подтверждение <span style="color:#dc2626;">*</span></label>
                            <div class="form-input-wrapper">
                                <i class="fas fa-key"></i>
                                <input type="password" id="confirm_password" name="confirm_password"
                                       class="form-input" required minlength="8" autocomplete="new-password">
                                <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="btn btn--primary btn--lg btn--full">
                            <i class="fas fa-save"></i> Сохранить новый пароль
                        </button>
                    </form>
                </section>
            </div>
        </div>
    </main>

    <?php include __DIR__ . '/includes/footer.php'; ?>

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
</body>
</html>
