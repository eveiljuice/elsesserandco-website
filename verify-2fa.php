<?php
require_once __DIR__ . '/includes/config/database.php';
require_once __DIR__ . '/includes/auth/check_auth.php';
require_once __DIR__ . '/includes/auth/totp_helper.php';

if (empty($_SESSION['pending_2fa_user_id'])) {
    header('Location: /login.php');
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = preg_replace('/\D/', '', $_POST['code'] ?? '');
    $pdo = getDBConnection();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([(int)$_SESSION['pending_2fa_user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && totpVerify((string)$user['totp_secret'], $code)) {
        unset($_SESSION['pending_2fa_user_id']);
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['first_name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        $pdo->prepare('UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE id = ?')->execute([$user['id']]);
        $redirect = $_SESSION['pending_2fa_redirect'] ?? '/dashboard.php';
        unset($_SESSION['pending_2fa_redirect']);
        header('Location: ' . $redirect);
        exit;
    }
    $errors[] = 'Неверный код';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>2FA | Elsesser & Co.</title>
    <link rel="stylesheet" href="/css/auth.css">
</head>
<body class="auth-page">
    <main class="auth-card">
        <h1>Двухфакторная аутентификация</h1>
        <?php foreach ($errors as $e): ?><p class="auth-error"><?= htmlspecialchars($e) ?></p><?php endforeach; ?>
        <form method="post">
            <label>Код из приложения</label>
            <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required>
            <button type="submit" class="auth-btn">Войти</button>
        </form>
    </main>
</body>
</html>
