<?php
/**
 * Admin: заявки на роль агента (agent_applications).
 *
 * Список заявок, фильтр по статусу, кнопки "Одобрить" / "Отклонить".
 * Одобрение: UPDATE agent_applications SET status='approved' + users.role='agent'.
 */

require_once __DIR__ . '/../includes/config/database.php';
require_once __DIR__ . '/../includes/auth/check_auth.php';

requireAdmin();

$pdo = getDBConnection();
$adminId = getCurrentUserId();

// Текущая фильтрация
$statusFilter = $_GET['status'] ?? '';
$validStatuses = ['pending', 'reviewing', 'approved', 'rejected'];
if (!in_array($statusFilter, $validStatuses, true)) {
    $statusFilter = '';
}

// Поиск по тексту
$search = trim($_GET['q'] ?? '');

// Обработка POST (одобрить / отклонить)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $appId = (int)($_POST['app_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($appId > 0) {
        $stmt = $pdo->prepare("SELECT * FROM agent_applications WHERE id = ?");
        $stmt->execute([$appId]);
        $app = $stmt->fetch();

        if ($app) {
            if ($action === 'approve') {
                $pdo->beginTransaction();
                try {
                    $pdo->prepare("
                        UPDATE agent_applications SET
                            status = 'approved', reviewed_by = ?, reviewed_at = NOW()
                        WHERE id = ?
                    ")->execute([$adminId, $appId]);

                    // Поднимаем role до agent у этого user
                    $pdo->prepare("UPDATE users SET role = 'agent' WHERE id = ?")
                        ->execute([$app['user_id']]);

                    $pdo->commit();
                    $flash = ['type' => 'success', 'message' => 'Заявка #' . $appId . ' одобрена. Пользователь теперь агент.'];
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    error_log('approve error: ' . $e->getMessage());
                    $flash = ['type' => 'error', 'message' => 'Ошибка при одобрении заявки'];
                }
            } elseif ($action === 'reject') {
                $reason = trim($_POST['rejection_reason'] ?? '');
                $pdo->prepare("
                    UPDATE agent_applications SET
                        status = 'rejected', rejection_reason = ?, reviewed_by = ?, reviewed_at = NOW()
                    WHERE id = ?
                ")->execute([$reason ?: null, $adminId, $appId]);
                $flash = ['type' => 'success', 'message' => 'Заявка #' . $appId . ' отклонена.'];
            } elseif ($action === 'reviewing') {
                $pdo->prepare("UPDATE agent_applications SET status = 'reviewing' WHERE id = ?")
                    ->execute([$appId]);
                $flash = ['type' => 'success', 'message' => 'Заявка #' . $appId . ' взята в работу.'];
            }
        }
    }
}

// Статистика для фильтров
$stats = [];
foreach (['pending', 'reviewing', 'approved', 'rejected'] as $st) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM agent_applications WHERE status = ?");
    $stmt->execute([$st]);
    $stats[$st] = (int)$stmt->fetchColumn();
}
$stmt = $pdo->query("SELECT COUNT(*) FROM agent_applications");
$stats['all'] = (int)$stmt->fetchColumn();

// Список заявок
$where = [];
$params = [];
if ($statusFilter) {
    $where[] = 'a.status = ?';
    $params[] = $statusFilter;
}
if ($search) {
    $where[] = '(a.full_name LIKE ? OR a.email LIKE ? OR a.phone LIKE ? OR u.email LIKE ?)';
    $s = '%' . $search . '%';
    $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "
    SELECT a.*, u.email AS user_email, u.first_name, u.last_name, u.role AS user_role
    FROM agent_applications a
    JOIN users u ON u.id = a.user_id
    $whereSql
    ORDER BY
        CASE a.status
            WHEN 'pending' THEN 1
            WHEN 'reviewing' THEN 2
            WHEN 'approved' THEN 3
            WHEN 'rejected' THEN 4
        END,
        a.created_at DESC
    LIMIT 200
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$applications = $stmt->fetchAll();

$pageTitle = 'Заявки на агента';
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
                    <h1 class="admin-title">Заявки на агента</h1>
                    <div class="admin-breadcrumb">
                        <i class="fas fa-user-plus"></i> Панель управления
                    </div>
                </div>
            </div>

            <?php if (!empty($flash)): ?>
            <div class="alert alert--<?= $flash['type'] === 'success' ? 'success' : 'error' ?>" style="margin-bottom: 16px;">
                <i class="fas fa-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                <span><?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <?php endif; ?>

            <div class="status-filters" style="margin-bottom: 16px;">
                <a href="?status=" class="status-filter <?= $statusFilter === '' ? 'active' : '' ?>">Все (<?= $stats['all'] ?>)</a>
                <a href="?status=pending" class="status-filter <?= $statusFilter === 'pending' ? 'active' : '' ?>">Ожидают (<?= $stats['pending'] ?>)</a>
                <a href="?status=reviewing" class="status-filter <?= $statusFilter === 'reviewing' ? 'active' : '' ?>">В работе (<?= $stats['reviewing'] ?>)</a>
                <a href="?status=approved" class="status-filter <?= $statusFilter === 'approved' ? 'active' : '' ?>">Одобрены (<?= $stats['approved'] ?>)</a>
                <a href="?status=rejected" class="status-filter <?= $statusFilter === 'rejected' ? 'active' : '' ?>">Отклонены (<?= $stats['rejected'] ?>)</a>
            </div>

            <form method="GET" class="admin-card" style="margin-bottom: 16px; padding: 12px 16px;">
                <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter, ENT_QUOTES, 'UTF-8') ?>">
                <div style="display:flex; gap:10px; align-items:center;">
                    <input type="text" name="q" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="Поиск по ФИО / email / телефону..." class="admin-input" style="flex:1; padding:8px 12px; border:1px solid var(--color-border); border-radius:6px;">
                    <button type="submit" class="btn btn--secondary btn--sm">Найти</button>
                </div>
            </form>

            <div class="admin-card">
                <div class="admin-card__body">
                    <?php if (empty($applications)): ?>
                    <div class="empty-state" style="padding: 40px 20px; text-align:center;">
                        <i class="fas fa-inbox" style="font-size:48px; color:var(--color-text-lighter);"></i>
                        <p style="margin-top:16px; color:var(--color-text-light);">Заявок не найдено</p>
                    </div>
                    <?php else: ?>
                    <div class="application-list">
                        <?php foreach ($applications as $app): ?>
                        <div class="application-card application-card--<?= htmlspecialchars($app['status'], ENT_QUOTES, 'UTF-8') ?>">
                            <div class="application-card__head">
                                <div>
                                    <h3 class="application-card__name"><?= htmlspecialchars($app['full_name'], ENT_QUOTES, 'UTF-8') ?></h3>
                                    <div class="application-card__contact">
                                        <a href="mailto:<?= htmlspecialchars($app['email'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($app['email'], ENT_QUOTES, 'UTF-8') ?></a>
                                        ·
                                        <a href="tel:<?= htmlspecialchars($app['phone'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($app['phone'], ENT_QUOTES, 'UTF-8') ?></a>
                                    </div>
                                </div>
                                <div class="application-card__status">
                                    <span class="badge badge--<?= match($app['status']) {
                                        'pending' => 'warning',
                                        'reviewing' => 'info',
                                        'approved' => 'success',
                                        'rejected' => 'danger',
                                        default => 'secondary'
                                    } ?>">
                                        <?= match($app['status']) {
                                            'pending' => 'Ожидает',
                                            'reviewing' => 'В работе',
                                            'approved' => 'Одобрена',
                                            'rejected' => 'Отклонена',
                                            default => $app['status']
                                        } ?>
                                    </span>
                                </div>
                            </div>
                            <div class="application-card__meta">
                                <?php if (!empty($app['region'])): ?>
                                <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($app['region'], ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                                <span><i class="fas fa-briefcase"></i> Опыт: <?= (int)$app['experience_years'] ?> лет</span>
                                <?php if (!empty($app['specialization'])): ?>
                                <span><i class="fas fa-tags"></i> <?= htmlspecialchars(str_replace(',', ', ', $app['specialization']), ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                                <span><i class="fas fa-clock"></i> Подана: <?= date('d.m.Y H:i', strtotime($app['created_at'])) ?></span>
                            </div>
                            <?php if (!empty($app['about'])): ?>
                            <div class="application-card__section">
                                <strong>О себе:</strong>
                                <p><?= nl2br(htmlspecialchars($app['about'], ENT_QUOTES, 'UTF-8')) ?></p>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($app['motivation'])): ?>
                            <div class="application-card__section">
                                <strong>Мотивация:</strong>
                                <p><?= nl2br(htmlspecialchars($app['motivation'], ENT_QUOTES, 'UTF-8')) ?></p>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($app['resume_filename'])): ?>
                            <div class="application-card__section">
                                <strong><i class="fas fa-paperclip"></i> Резюме:</strong>
                                <p>
                                    <a href="../<?= htmlspecialchars($app['resume_path'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                        <?= htmlspecialchars($app['resume_filename'], ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                    <span style="color: var(--color-text-light); margin-left: 8px;">
                                        (<?= round(((int)$app['resume_size']) / 1024, 1) ?> КБ)
                                    </span>
                                </p>
                            </div>
                            <?php endif; ?>
                            <?php if ($app['status'] === 'rejected' && !empty($app['rejection_reason'])): ?>
                            <div class="application-card__section application-card__section--reject">
                                <strong>Причина отклонения:</strong>
                                <p><?= nl2br(htmlspecialchars($app['rejection_reason'], ENT_QUOTES, 'UTF-8')) ?></p>
                            </div>
                            <?php endif; ?>

                            <?php if (in_array($app['status'], ['pending', 'reviewing'], true)): ?>
                            <div class="application-card__actions">
                                <?php if ($app['status'] === 'pending'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="app_id" value="<?= (int)$app['id'] ?>">
                                    <input type="hidden" name="action" value="reviewing">
                                    <button type="submit" class="btn btn--secondary btn--sm">
                                        <i class="fas fa-eye"></i> Взять в работу
                                    </button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Одобрить заявку? Пользователь станет агентом.');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="app_id" value="<?= (int)$app['id'] ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn--primary btn--sm">
                                        <i class="fas fa-check"></i> Одобрить
                                    </button>
                                </form>
                                <button type="button" class="btn btn--secondary btn--sm" onclick="openReject(<?= (int)$app['id'] ?>)">
                                    <i class="fas fa-times"></i> Отклонить
                                </button>
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
                <input type="hidden" name="app_id" id="rejectAppId" value="">
                <input type="hidden" name="action" value="reject">
                <textarea name="rejection_reason" rows="4" required
                          style="width:100%; padding:8px; border:1px solid var(--color-border); border-radius:6px; box-sizing:border-box;"
                          placeholder="Укажите причину отклонения. Пользователь увидит её в заявке."></textarea>
                <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:12px;">
                    <button type="button" class="btn btn--secondary btn--sm" onclick="closeReject()">Отмена</button>
                    <button type="submit" class="btn btn--primary btn--sm">Отклонить заявку</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openReject(id) {
        document.getElementById('rejectAppId').value = id;
        document.getElementById('rejectModal').style.display = 'flex';
    }
    function closeReject() {
        document.getElementById('rejectModal').style.display = 'none';
    }
    </script>
</body>
</html>