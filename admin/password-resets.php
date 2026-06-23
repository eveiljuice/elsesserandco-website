<?php
/**
 * Admin: заявки на сброс пароля.
 * Заменяет email-flow на ручное одобрение админом.
 *
 * Поток:
 *  - Пользователь подаёт заявку на /forgot-password.php (status=pending)
 *  - Админ здесь видит список pending-заявок → одобряет / отклоняет
 *  - После одобрения пользователь возвращается на /forgot-password.php,
 *    вводит email — попадает на /reset-password.php (ввод нового пароля)
 */

require_once __DIR__ . '/../includes/config/database.php';
require_once __DIR__ . '/../includes/auth/check_auth.php';

requireAdmin();

$pdo = getDBConnection();
$adminId = getCurrentUserId();

$statusFilter = $_GET['status'] ?? 'pending';
$validStatuses = ['pending', 'approved', 'rejected', 'used'];
if (!in_array($statusFilter, $validStatuses, true)) {
    $statusFilter = 'pending';
}

// Обработка POST
$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $reqId  = (int)($_POST['req_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($reqId > 0) {
        $stmt = $pdo->prepare("SELECT * FROM password_reset_requests WHERE id = ?");
        $stmt->execute([$reqId]);
        $req = $stmt->fetch();

        if ($req) {
            if ($action === 'approve' && $req['status'] === 'pending') {
                $pdo->prepare("
                    UPDATE password_reset_requests
                    SET status = 'approved', reviewed_by = ?, reviewed_at = NOW()
                    WHERE id = ? AND status = 'pending'
                ")->execute([$adminId, $reqId]);
                $flash = ['type' => 'success', 'message' => 'Заявка #' . $reqId . ' одобрена. Пользователь сможет задать новый пароль.'];
            } elseif ($action === 'reject' && $req['status'] === 'pending') {
                $reason = trim($_POST['rejection_reason'] ?? '');
                $pdo->prepare("
                    UPDATE password_reset_requests
                    SET status = 'rejected', rejection_reason = ?, reviewed_by = ?, reviewed_at = NOW()
                    WHERE id = ? AND status = 'pending'
                ")->execute([$reason ?: null, $adminId, $reqId]);
                $flash = ['type' => 'success', 'message' => 'Заявка #' . $reqId . ' отклонена.'];
            } elseif ($action === 'reset_to_pending' && $req['status'] === 'rejected') {
                // Возможность «отменить отклонение» — сбросить обратно в pending
                $pdo->prepare("
                    UPDATE password_reset_requests
                    SET status = 'pending', rejection_reason = NULL, reviewed_by = NULL, reviewed_at = NULL
                    WHERE id = ?
                ")->execute([$reqId]);
                $flash = ['type' => 'success', 'message' => 'Заявка возвращена в очередь.'];
            }
        }
    }
}

// Статистика
$stats = [];
foreach (['pending', 'approved', 'rejected', 'used'] as $st) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM password_reset_requests WHERE status = ?");
    $stmt->execute([$st]);
    $stats[$st] = (int)$stmt->fetchColumn();
}
$stmt = $pdo->query("SELECT COUNT(*) FROM password_reset_requests");
$stats['all'] = (int)$stmt->fetchColumn();

// Список заявок
$sql = "
    SELECT r.*, u.first_name, u.last_name, u.role AS user_role,
           ru.first_name AS reviewer_first_name, ru.last_name AS reviewer_last_name
    FROM password_reset_requests r
    JOIN users u ON u.id = r.user_id
    LEFT JOIN users ru ON ru.id = r.reviewed_by
    WHERE r.status = ?
    ORDER BY r.created_at DESC
    LIMIT 200
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$statusFilter]);
$requests = $stmt->fetchAll();

$pageTitle = 'Заявки на сброс пароля';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $pageTitle ?> | Elsesser & Co.</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../css/reset.css">
    <link rel="stylesheet" href="../css/variables.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body class="admin-body">
    <?php include __DIR__ . '/includes/admin-header.php'; ?>

    <div class="admin-container">
        <?php include __DIR__ . '/includes/admin-sidebar.php'; ?>

        <main class="admin-main">
            <div class="admin-header">
                <div>
                    <h1 class="admin-title">Заявки на сброс пароля</h1>
                    <div class="admin-breadcrumb">
                        <i class="fas fa-key"></i> Панель управления
                    </div>
                </div>
            </div>

            <?php if ($flash): ?>
            <div class="alert alert--<?= $flash['type'] === 'success' ? 'success' : 'error' ?>" style="margin-bottom: 16px;">
                <i class="fas fa-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                <span><?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <?php endif; ?>

            <div class="status-filters" style="margin-bottom: 16px;">
                <a href="?status=pending" class="status-filter <?= $statusFilter === 'pending' ? 'active' : '' ?>">Ожидают (<?= $stats['pending'] ?>)</a>
                <a href="?status=approved" class="status-filter <?= $statusFilter === 'approved' ? 'active' : '' ?>">Одобрены (<?= $stats['approved'] ?>)</a>
                <a href="?status=rejected" class="status-filter <?= $statusFilter === 'rejected' ? 'active' : '' ?>">Отклонены (<?= $stats['rejected'] ?>)</a>
                <a href="?status=used" class="status-filter <?= $statusFilter === 'used' ? 'active' : '' ?>">Использованы (<?= $stats['used'] ?>)</a>
            </div>

            <div class="admin-card">
                <div class="admin-card__body">
                    <?php if (empty($requests)): ?>
                    <div class="empty-state" style="padding: 40px 20px; text-align:center;">
                        <i class="fas fa-inbox" style="font-size:48px; color:var(--color-text-lighter);"></i>
                        <p style="margin-top:16px; color:var(--color-text-light);">Заявок не найдено</p>
                    </div>
                    <?php else: ?>
                    <div class="application-list">
                        <?php foreach ($requests as $r): ?>
                        <div class="application-card application-card--<?= htmlspecialchars($r['status'], ENT_QUOTES, 'UTF-8') ?>">
                            <div class="application-card__head">
                                <div>
                                    <h3 class="application-card__name">
                                        <?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name'], ENT_QUOTES, 'UTF-8') ?>
                                        <span class="badge badge--<?= $r['user_role'] === 'admin' ? 'danger' : ($r['user_role'] === 'agent' ? 'warning' : 'secondary') ?>" style="margin-left: 6px; font-size:11px;">
                                            <?= match($r['user_role']) { 'admin' => 'Админ', 'agent' => 'Агент', default => 'User' } ?>
                                        </span>
                                    </h3>
                                    <div class="application-card__contact">
                                        <a href="mailto:<?= htmlspecialchars($r['email'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($r['email'], ENT_QUOTES, 'UTF-8') ?></a>
                                    </div>
                                </div>
                                <div class="application-card__status">
                                    <span class="badge badge--<?= match($r['status']) {
                                        'pending' => 'warning',
                                        'approved' => 'success',
                                        'rejected' => 'danger',
                                        'used' => 'secondary',
                                        default => 'secondary'
                                    } ?>">
                                        <?= match($r['status']) {
                                            'pending' => 'Ожидает',
                                            'approved' => 'Одобрена',
                                            'rejected' => 'Отклонена',
                                            'used' => 'Использована',
                                            default => $r['status']
                                        } ?>
                                    </span>
                                </div>
                            </div>
                            <div class="application-card__meta">
                                <span><i class="fas fa-clock"></i> Подана: <?= date('d.m.Y H:i', strtotime($r['created_at'])) ?></span>
                                <?php if ($r['reviewed_at']): ?>
                                <span><i class="fas fa-user-shield"></i> Решение: <?= date('d.m.Y H:i', strtotime($r['reviewed_at'])) ?>
                                    <?php if ($r['reviewer_first_name']): ?>
                                    (<?= htmlspecialchars($r['reviewer_first_name'], ENT_QUOTES, 'UTF-8') ?>)
                                    <?php endif; ?>
                                </span>
                                <?php endif; ?>
                                <?php if ($r['used_at']): ?>
                                <span><i class="fas fa-check-double"></i> Использована: <?= date('d.m.Y H:i', strtotime($r['used_at'])) ?></span>
                                <?php endif; ?>
                            </div>

                            <?php if ($r['status'] === 'rejected' && !empty($r['rejection_reason'])): ?>
                            <div class="application-card__section application-card__section--reject">
                                <strong>Причина отклонения:</strong>
                                <p><?= nl2br(htmlspecialchars($r['rejection_reason'], ENT_QUOTES, 'UTF-8')) ?></p>
                            </div>
                            <?php endif; ?>

                            <?php if ($r['status'] === 'pending'): ?>
                            <div class="application-card__actions">
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Одобрить заявку? Пользователь сможет задать новый пароль на /forgot-password.php.');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="req_id" value="<?= (int)$r['id'] ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn--primary btn--sm">
                                        <i class="fas fa-check"></i> Одобрить
                                    </button>
                                </form>
                                <button type="button" class="btn btn--secondary btn--sm" onclick="openReject(<?= (int)$r['id'] ?>)">
                                    <i class="fas fa-times"></i> Отклонить
                                </button>
                            </div>
                            <?php endif; ?>

                            <?php if ($r['status'] === 'rejected'): ?>
                            <div class="application-card__actions">
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="req_id" value="<?= (int)$r['id'] ?>">
                                    <input type="hidden" name="action" value="reset_to_pending">
                                    <button type="submit" class="btn btn--secondary btn--sm">
                                        <i class="fas fa-undo"></i> Вернуть в очередь
                                    </button>
                                </form>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal: Reject form -->
    <div id="rejectModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
        <div style="background:white; border-radius:12px; padding:24px; max-width:480px; width:90%;">
            <h3 style="margin-top:0;">Причина отклонения</h3>
            <form method="POST" id="rejectForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="req_id" id="rejectReqId" value="">
                <input type="hidden" name="action" value="reject">
                <textarea name="rejection_reason" rows="4"
                          style="width:100%; padding:8px; border:1px solid var(--color-border); border-radius:6px; box-sizing:border-box; font-family:inherit;"
                          placeholder="Укажите причину отклонения (необязательно)."></textarea>
                <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:12px;">
                    <button type="button" class="btn btn--secondary btn--sm" onclick="closeReject()">Отмена</button>
                    <button type="submit" class="btn btn--primary btn--sm">Отклонить заявку</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openReject(id) {
        document.getElementById('rejectReqId').value = id;
        document.getElementById('rejectModal').style.display = 'flex';
    }
    function closeReject() {
        document.getElementById('rejectModal').style.display = 'none';
    }
    </script>
</body>
</html>
