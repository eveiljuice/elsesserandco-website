<?php
require_once __DIR__ . '/includes/config/database.php';
require_once __DIR__ . '/includes/auth/check_auth.php';
require_once __DIR__ . '/includes/auth/totp_helper.php';

requireLogin();
$userId = getCurrentUserId();
$pdo = getDBConnection();
$stmt = $pdo->prepare('SELECT email, role, totp_secret, totp_enabled_at FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!in_array($user['role'] ?? '', ['admin', 'agent'], true)) {
    header('Location: /dashboard.php');
    exit;
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken($_POST['csrf_token'] ?? '')) {
    if (isset($_POST['enable'])) {
        $secret = totpGenerateSecret();
        $_SESSION['totp_setup_secret'] = $secret;
    } elseif (isset($_POST['confirm']) && !empty($_SESSION['totp_setup_secret'])) {
        $code = preg_replace('/\D/', '', $_POST['code'] ?? '');
        if (totpVerify($_SESSION['totp_setup_secret'], $code)) {
            $pdo->prepare('UPDATE users SET totp_secret = ?, totp_enabled_at = NOW() WHERE id = ?')
                ->execute([$_SESSION['totp_setup_secret'], $userId]);
            unset($_SESSION['totp_setup_secret']);
            $message = '2FA включена';
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $message = 'Неверный код';
        }
    } elseif (isset($_POST['disable'])) {
        $pdo->prepare('UPDATE users SET totp_secret = NULL, totp_enabled_at = NULL WHERE id = ?')->execute([$userId]);
        $message = '2FA отключена';
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

$setupSecret = $_SESSION['totp_setup_secret'] ?? null;
$qr = $setupSecret ? totpQrDataUri($setupSecret, (string)$user['email']) : '';
$csrf = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Настройка 2FA</title>
    <link rel="stylesheet" href="/css/dashboard.css">
</head>
<body>
    <main style="max-width:520px;margin:2rem auto;padding:1rem;">
        <h1>Двухфакторная аутентификация</h1>
        <?php if ($message): ?><p><?= htmlspecialchars($message) ?></p><?php endif; ?>
        <?php if (!empty($user['totp_enabled_at'])): ?>
            <p>2FA активна с <?= htmlspecialchars((string)$user['totp_enabled_at']) ?></p>
            <form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <button name="disable" type="submit">Отключить</button>
            </form>
        <?php elseif ($setupSecret): ?>
            <?php if ($qr): ?><img src="<?= htmlspecialchars($qr) ?>" alt="QR"><?php endif; ?>
            <p>Секрет: <code><?= htmlspecialchars($setupSecret) ?></code></p>
            <form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input name="code" placeholder="6 цифр" required>
                <button name="confirm" type="submit">Подтвердить</button>
            </form>
        <?php else: ?>
            <form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <button name="enable" type="submit">Включить 2FA</button>
            </form>
        <?php endif; ?>
        <p><a href="/dashboard.php">← Назад</a></p>
    </main>
</body>
</html>
