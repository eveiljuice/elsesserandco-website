<?php
/**
 * Admin - Developer Applications Management
 * Управление заявками на регистрацию застройщиков
 */

require_once __DIR__ . '/../includes/config/database.php';
require_once __DIR__ . '/../includes/auth/check_auth.php';

// Только для администраторов
requireAdmin();

$pdo = getDBConnection();

// Обработка действий
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $applicationId = filter_var($_POST['application_id'] ?? 0, FILTER_VALIDATE_INT);
    $action = $_POST['action'];
    
    if ($applicationId) {
        try {
            if ($action === 'approve') {
                // Одобрение заявки и создание аккаунта
                $stmt = $pdo->prepare("SELECT * FROM developer_applications WHERE id = ? AND status IN ('pending', 'reviewing')");
                $stmt->execute([$applicationId]);
                $application = $stmt->fetch();
                
                if ($application) {
                    // Генерируем случайный пароль
                    $password = generateRandomPassword(12);
                    $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                    
                    // Создаём пользователя
                    $stmt = $pdo->prepare("
                        INSERT INTO users (
                            email, password_hash, first_name, last_name, phone, role, 
                            company_name, inn, is_developer, is_active, logo
                        ) VALUES (?, ?, ?, ?, ?, 'agent', ?, ?, 1, 1, ?)
                    ");
                    
                    // Имя и фамилия из контактного лица
                    $nameParts = explode(' ', $application['contact_person'], 2);
                    $firstName = $nameParts[1] ?? $application['company_name'];
                    $lastName = $nameParts[0] ?? '';
                    
                    $stmt->execute([
                        $application['email'],
                        $passwordHash,
                        $firstName,
                        $lastName,
                        $application['phone'],
                        $application['company_name'],
                        $application['inn'],
                        $application['logo']
                    ]);
                    
                    $userId = $pdo->lastInsertId();
                    
                    // Обновляем статус заявки
                    $stmt = $pdo->prepare("
                        UPDATE developer_applications 
                        SET status = 'approved', 
                            reviewed_by = ?, 
                            reviewed_at = NOW(),
                            user_id = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([getCurrentUserId(), $userId, $applicationId]);
                    
                    // Сохраняем данные для отображения
                    $_SESSION['approved_developer'] = [
                        'company_name' => $application['company_name'],
                        'email' => $application['email'],
                        'password' => $password
                    ];
                    
                    $message = "Заявка одобрена! Аккаунт для {$application['company_name']} создан.";
                    $messageType = 'success';
                    
                    error_log("Developer approved: {$application['company_name']} (ID: {$applicationId}) by admin ID: " . getCurrentUserId());
                } else {
                    $message = "Заявка не найдена или уже обработана";
                    $messageType = 'error';
                }
                
            } elseif ($action === 'reject') {
                $rejectionReason = trim($_POST['rejection_reason'] ?? '');
                
                $stmt = $pdo->prepare("
                    UPDATE developer_applications 
                    SET status = 'rejected', 
                        reviewed_by = ?, 
                        reviewed_at = NOW(),
                        rejection_reason = ?
                    WHERE id = ? AND status IN ('pending', 'reviewing')
                ");
                $stmt->execute([getCurrentUserId(), $rejectionReason, $applicationId]);
                
                if ($stmt->rowCount() > 0) {
                    $message = "Заявка отклонена";
                    $messageType = 'warning';
                    error_log("Developer rejected: ID {$applicationId} by admin ID: " . getCurrentUserId());
                } else {
                    $message = "Заявка не найдена или уже обработана";
                    $messageType = 'error';
                }
                
            } elseif ($action === 'review') {
                $stmt = $pdo->prepare("
                    UPDATE developer_applications 
                    SET status = 'reviewing'
                    WHERE id = ? AND status = 'pending'
                ");
                $stmt->execute([$applicationId]);
                
                if ($stmt->rowCount() > 0) {
                    $message = "Заявка взята на рассмотрение";
                    $messageType = 'info';
                }
            }
            
        } catch (PDOException $e) {
            error_log("Developer action error: " . $e->getMessage());
            $message = "Ошибка при обработке заявки";
            $messageType = 'error';
        }
    }
}

// Генератор случайного пароля
function generateRandomPassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

// Фильтры
$statusFilter = $_GET['status'] ?? 'pending';
$validStatuses = ['all', 'pending', 'reviewing', 'approved', 'rejected'];
if (!in_array($statusFilter, $validStatuses)) {
    $statusFilter = 'pending';
}

// Пагинация
$page = max(1, filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Получаем заявки
$whereClause = $statusFilter !== 'all' ? "WHERE da.status = ?" : "";
$params = $statusFilter !== 'all' ? [$statusFilter] : [];

// Общее количество
$countSql = "SELECT COUNT(*) FROM developer_applications da $whereClause";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalCount = $stmt->fetchColumn();
$totalPages = ceil($totalCount / $perPage);

// Заявки
$sql = "
    SELECT da.*, 
           u.first_name as reviewer_name,
           du.email as developer_email
    FROM developer_applications da
    LEFT JOIN users u ON da.reviewed_by = u.id
    LEFT JOIN users du ON da.user_id = du.id
    $whereClause
    ORDER BY 
        CASE da.status 
            WHEN 'pending' THEN 1 
            WHEN 'reviewing' THEN 2 
            WHEN 'approved' THEN 3 
            WHEN 'rejected' THEN 4 
        END,
        da.created_at DESC
    LIMIT $perPage OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$applications = $stmt->fetchAll();

// Статистика
$statsStmt = $pdo->query("
    SELECT 
        SUM(status = 'pending') as pending,
        SUM(status = 'reviewing') as reviewing,
        SUM(status = 'approved') as approved,
        SUM(status = 'rejected') as rejected
    FROM developer_applications
");
$stats = $statsStmt->fetch();

// Названия форм
$legalForms = [
    'ooo' => 'ООО',
    'ip' => 'ИП',
    'ao' => 'АО',
    'zao' => 'ЗАО',
    'pao' => 'ПАО'
];

// Специализации застройщиков
$specializationLabels = [
    'residential' => 'Жилая недвижимость',
    'commercial' => 'Коммерческая',
    'mixed' => 'Многофункциональные комплексы',
    'luxury' => 'Элитное жилье',
    'economy' => 'Эконом-класс'
];

$pageTitle = 'Заявки застройщиков';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | Elsesser & Co.</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Styles -->
    <link rel="stylesheet" href="../css/reset.css">
    <link rel="stylesheet" href="../css/variables.css">
    <link rel="stylesheet" href="../css/admin.css">
    
    <style>
        .developer-card {
            background: var(--color-white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            margin-bottom: var(--space-4);
            overflow: hidden;
        }
        
        .developer-card__header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: var(--space-5);
            border-bottom: 1px solid var(--color-border);
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        }
        
        .developer-card__header-content {
            flex: 1;
            display: flex;
            gap: var(--space-4);
            align-items: flex-start;
        }
        
        .developer-card__logo {
            width: 80px;
            height: 80px;
            border-radius: var(--radius-md);
            object-fit: contain;
            background: white;
            padding: var(--space-2);
            border: 1px solid var(--color-border);
        }
        
        .developer-card__title {
            font-size: var(--text-lg);
            font-weight: var(--font-semibold);
            color: var(--color-navy);
            margin-bottom: var(--space-1);
        }
        
        .developer-card__meta {
            font-size: var(--text-sm);
            color: var(--color-text-light);
        }
        
        .developer-card__body {
            padding: var(--space-5);
        }
        
        .developer-card__grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--space-5);
        }
        
        .developer-card__section {
            margin-bottom: var(--space-4);
        }
        
        .developer-card__section:last-child {
            margin-bottom: 0;
        }
        
        .developer-card__section-title {
            font-size: var(--text-xs);
            font-weight: var(--font-semibold);
            color: var(--color-accent);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: var(--space-2);
        }
        
        .developer-card__row {
            display: flex;
            margin-bottom: var(--space-2);
        }
        
        .developer-card__label {
            min-width: 140px;
            font-size: var(--text-sm);
            color: var(--color-text-light);
        }
        
        .developer-card__value {
            font-size: var(--text-sm);
            color: var(--color-text);
            word-break: break-word;
        }
        
        .developer-card__footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--space-4) var(--space-5);
            background: #f8fafc;
            border-top: 1px solid var(--color-border);
        }
        
        .developer-card__actions {
            display: flex;
            gap: var(--space-2);
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: var(--space-1);
            padding: var(--space-1) var(--space-3);
            border-radius: var(--radius-full);
            font-size: var(--text-xs);
            font-weight: var(--font-medium);
        }
        
        .status-badge--pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-badge--reviewing {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .status-badge--approved {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-badge--rejected {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .filter-tabs {
            display: flex;
            gap: var(--space-2);
            margin-bottom: var(--space-6);
            flex-wrap: wrap;
        }
        
        .filter-tab {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-2) var(--space-4);
            border-radius: var(--radius-md);
            font-size: var(--text-sm);
            font-weight: var(--font-medium);
            color: var(--color-text);
            background: var(--color-white);
            border: 1px solid var(--color-border);
            text-decoration: none;
            transition: all var(--transition-fast);
        }
        
        .filter-tab:hover {
            background: #f8fafc;
            border-color: var(--color-accent);
        }
        
        .filter-tab--active {
            background: var(--color-accent);
            border-color: var(--color-accent);
            color: var(--color-white);
        }
        
        .filter-tab__count {
            background: rgba(0,0,0,0.1);
            padding: 2px 8px;
            border-radius: var(--radius-full);
            font-size: var(--text-xs);
        }
        
        .filter-tab--active .filter-tab__count {
            background: rgba(255,255,255,0.2);
        }
        
        .empty-state {
            text-align: center;
            padding: var(--space-12);
            color: var(--color-text-light);
        }
        
        .empty-state i {
            font-size: 48px;
            color: var(--color-border);
            margin-bottom: var(--space-4);
        }
        
        .specialization-tags {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-1);
        }
        
        .specialization-tag {
            display: inline-block;
            padding: 2px 8px;
            background: #e2e8f0;
            border-radius: var(--radius-sm);
            font-size: var(--text-xs);
            color: var(--color-text);
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal__content {
            background: var(--color-white);
            border-radius: var(--radius-lg);
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow: auto;
        }
        
        .modal__header {
            padding: var(--space-5);
            border-bottom: 1px solid var(--color-border);
        }
        
        .modal__title {
            font-size: var(--text-lg);
            font-weight: var(--font-semibold);
            color: var(--color-navy);
        }
        
        .modal__body {
            padding: var(--space-5);
        }
        
        .modal__footer {
            padding: var(--space-4) var(--space-5);
            border-top: 1px solid var(--color-border);
            display: flex;
            justify-content: flex-end;
            gap: var(--space-2);
        }
        
        .credentials-box {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: var(--radius-md);
            padding: var(--space-4);
            margin-bottom: var(--space-4);
        }
        
        .credentials-box__title {
            font-weight: var(--font-semibold);
            color: #065f46;
            margin-bottom: var(--space-2);
        }
        
        .credentials-box__item {
            display: flex;
            margin-bottom: var(--space-2);
        }
        
        .credentials-box__label {
            min-width: 80px;
            font-size: var(--text-sm);
            color: #065f46;
        }
        
        .credentials-box__value {
            font-family: monospace;
            background: white;
            padding: 2px 8px;
            border-radius: var(--radius-sm);
            font-size: var(--text-sm);
        }
    </style>
</head>
<body class="admin-body">
    <?php include __DIR__ . '/includes/admin-header.php'; ?>
    
    <div class="admin-container">
        <?php include __DIR__ . '/includes/admin-sidebar.php'; ?>
        
        <main class="admin-main">
            <div class="admin-header">
                <h1 class="admin-title"><?= $pageTitle ?></h1>
                <div class="admin-breadcrumb">
                    <a href="index.php"><i class="fas fa-home"></i></a> / Заявки застройщиков
                </div>
            </div>
            
            <?php if ($message): ?>
            <div class="alert alert--<?= $messageType ?>">
                <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : ($messageType === 'error' ? 'exclamation-circle' : 'info-circle') ?>"></i>
                <?= htmlspecialchars($message) ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['approved_developer'])): ?>
            <div class="credentials-box">
                <div class="credentials-box__title">
                    <i class="fas fa-key"></i> Данные для входа застройщика:
                </div>
                <div class="credentials-box__item">
                    <span class="credentials-box__label">Компания:</span>
                    <span class="credentials-box__value"><?= htmlspecialchars($_SESSION['approved_developer']['company_name']) ?></span>
                </div>
                <div class="credentials-box__item">
                    <span class="credentials-box__label">Email:</span>
                    <span class="credentials-box__value"><?= htmlspecialchars($_SESSION['approved_developer']['email']) ?></span>
                </div>
                <div class="credentials-box__item">
                    <span class="credentials-box__label">Пароль:</span>
                    <span class="credentials-box__value"><?= htmlspecialchars($_SESSION['approved_developer']['password']) ?></span>
                </div>
                <p style="font-size: var(--text-sm); color: #065f46; margin-top: var(--space-3);">
                    <i class="fas fa-exclamation-triangle"></i> 
                    Скопируйте эти данные и отправьте застройщику. Пароль показывается только один раз!
                </p>
            </div>
            <?php unset($_SESSION['approved_developer']); ?>
            <?php endif; ?>
            
            <!-- Фильтры -->
            <div class="filter-tabs">
                <a href="?status=pending" class="filter-tab <?= $statusFilter === 'pending' ? 'filter-tab--active' : '' ?>">
                    <i class="fas fa-clock"></i>
                    Новые
                    <span class="filter-tab__count"><?= $stats['pending'] ?? 0 ?></span>
                </a>
                <a href="?status=reviewing" class="filter-tab <?= $statusFilter === 'reviewing' ? 'filter-tab--active' : '' ?>">
                    <i class="fas fa-search"></i>
                    На рассмотрении
                    <span class="filter-tab__count"><?= $stats['reviewing'] ?? 0 ?></span>
                </a>
                <a href="?status=approved" class="filter-tab <?= $statusFilter === 'approved' ? 'filter-tab--active' : '' ?>">
                    <i class="fas fa-check"></i>
                    Одобренные
                    <span class="filter-tab__count"><?= $stats['approved'] ?? 0 ?></span>
                </a>
                <a href="?status=rejected" class="filter-tab <?= $statusFilter === 'rejected' ? 'filter-tab--active' : '' ?>">
                    <i class="fas fa-times"></i>
                    Отклонённые
                    <span class="filter-tab__count"><?= $stats['rejected'] ?? 0 ?></span>
                </a>
                <a href="?status=all" class="filter-tab <?= $statusFilter === 'all' ? 'filter-tab--active' : '' ?>">
                    <i class="fas fa-list"></i>
                    Все
                </a>
            </div>
            
            <!-- Список заявок -->
            <?php if (empty($applications)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>Нет заявок в этой категории</p>
            </div>
            <?php else: ?>
            
            <?php foreach ($applications as $app): ?>
            <div class="developer-card">
                <div class="developer-card__header">
                    <div class="developer-card__header-content">
                        <?php if ($app['logo']): ?>
                        <img src="../<?= htmlspecialchars($app['logo']) ?>" alt="Логотип" class="developer-card__logo">
                        <?php endif; ?>
                        <div>
                            <div class="developer-card__title">
                                <?= htmlspecialchars($legalForms[$app['legal_form']] ?? '') ?> 
                                «<?= htmlspecialchars($app['company_name']) ?>»
                            </div>
                            <div class="developer-card__meta">
                                ИНН: <?= htmlspecialchars($app['inn']) ?>
                                <?php if ($app['ogrn']): ?>
                                • ОГРН: <?= htmlspecialchars($app['ogrn']) ?>
                                <?php endif; ?>
                                • Заявка от <?= date('d.m.Y H:i', strtotime($app['created_at'])) ?>
                            </div>
                        </div>
                    </div>
                    <span class="status-badge status-badge--<?= $app['status'] ?>">
                        <?= match($app['status']) {
                            'pending' => '<i class="fas fa-clock"></i> Ожидает',
                            'reviewing' => '<i class="fas fa-search"></i> На рассмотрении',
                            'approved' => '<i class="fas fa-check"></i> Одобрена',
                            'rejected' => '<i class="fas fa-times"></i> Отклонена',
                            default => $app['status']
                        } ?>
                    </span>
                </div>
                
                <div class="developer-card__body">
                    <div class="developer-card__grid">
                        <!-- Контакты -->
                        <div class="developer-card__section">
                            <div class="developer-card__section-title">Контактная информация</div>
                            <div class="developer-card__row">
                                <span class="developer-card__label">Контактное лицо:</span>
                                <span class="developer-card__value"><?= htmlspecialchars($app['contact_person']) ?></span>
                            </div>
                            <?php if ($app['contact_position']): ?>
                            <div class="developer-card__row">
                                <span class="developer-card__label">Должность:</span>
                                <span class="developer-card__value"><?= htmlspecialchars($app['contact_position']) ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="developer-card__row">
                                <span class="developer-card__label">Email:</span>
                                <span class="developer-card__value">
                                    <a href="mailto:<?= htmlspecialchars($app['email']) ?>"><?= htmlspecialchars($app['email']) ?></a>
                                </span>
                            </div>
                            <div class="developer-card__row">
                                <span class="developer-card__label">Телефон:</span>
                                <span class="developer-card__value">
                                    <a href="tel:<?= htmlspecialchars($app['phone']) ?>"><?= htmlspecialchars($app['phone']) ?></a>
                                </span>
                            </div>
                            <?php if ($app['website']): ?>
                            <div class="developer-card__row">
                                <span class="developer-card__label">Сайт:</span>
                                <span class="developer-card__value">
                                    <a href="<?= htmlspecialchars($app['website']) ?>" target="_blank"><?= htmlspecialchars($app['website']) ?></a>
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Адреса -->
                        <div class="developer-card__section">
                            <div class="developer-card__section-title">Адреса</div>
                            <div class="developer-card__row">
                                <span class="developer-card__label">Юридический:</span>
                                <span class="developer-card__value"><?= htmlspecialchars($app['legal_address']) ?></span>
                            </div>
                            <?php if ($app['actual_address']): ?>
                            <div class="developer-card__row">
                                <span class="developer-card__label">Фактический:</span>
                                <span class="developer-card__value"><?= htmlspecialchars($app['actual_address']) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- О компании -->
                        <div class="developer-card__section">
                            <div class="developer-card__section-title">О компании</div>
                            <?php if ($app['specialization']): ?>
                            <div class="developer-card__row">
                                <span class="developer-card__label">Специализация:</span>
                                <span class="developer-card__value">
                                    <div class="specialization-tags">
                                        <?php foreach (explode(',', $app['specialization']) as $spec): ?>
                                        <span class="specialization-tag"><?= htmlspecialchars($specializationLabels[$spec] ?? $spec) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </span>
                            </div>
                            <?php endif; ?>
                            <?php if ($app['years_on_market']): ?>
                            <div class="developer-card__row">
                                <span class="developer-card__label">На рынке:</span>
                                <span class="developer-card__value"><?= $app['years_on_market'] ?> лет</span>
                            </div>
                            <?php endif; ?>
                            <?php if ($app['completed_projects']): ?>
                            <div class="developer-card__row">
                                <span class="developer-card__label">Проектов:</span>
                                <span class="developer-card__value"><?= $app['completed_projects'] ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($app['description']): ?>
                    <div class="developer-card__section" style="margin-top: var(--space-4);">
                        <div class="developer-card__section-title">Описание</div>
                        <p style="font-size: var(--text-sm); color: var(--color-text); line-height: 1.6;">
                            <?= nl2br(htmlspecialchars($app['description'])) ?>
                        </p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($app['status'] === 'rejected' && $app['rejection_reason']): ?>
                    <div class="alert alert--error" style="margin-top: var(--space-4);">
                        <i class="fas fa-exclamation-circle"></i>
                        <strong>Причина отказа:</strong> <?= htmlspecialchars($app['rejection_reason']) ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="developer-card__footer">
                    <div>
                        <?php if ($app['reviewed_at']): ?>
                        <span style="font-size: var(--text-sm); color: var(--color-text-light);">
                            <?= $app['status'] === 'approved' ? 'Одобрена' : 'Обработана' ?>: 
                            <?= date('d.m.Y H:i', strtotime($app['reviewed_at'])) ?>
                            <?php if ($app['reviewer_name']): ?>
                            (<?= htmlspecialchars($app['reviewer_name']) ?>)
                            <?php endif; ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($app['status'] === 'pending' || $app['status'] === 'reviewing'): ?>
                    <div class="developer-card__actions">
                        <?php if ($app['status'] === 'pending'): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="application_id" value="<?= $app['id'] ?>">
                            <input type="hidden" name="action" value="review">
                            <button type="submit" class="btn btn--sm btn--secondary">
                                <i class="fas fa-search"></i> Взять на рассмотрение
                            </button>
                        </form>
                        <?php endif; ?>
                        
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="application_id" value="<?= $app['id'] ?>">
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" class="btn btn--sm btn--primary" 
                                    onclick="return confirm('Одобрить заявку и создать аккаунт для <?= htmlspecialchars(addslashes($app['company_name'])) ?>?')">
                                <i class="fas fa-check"></i> Одобрить
                            </button>
                        </form>
                        
                        <button type="button" class="btn btn--sm btn--danger" 
                                onclick="openRejectModal(<?= $app['id'] ?>, '<?= htmlspecialchars(addslashes($app['company_name'])) ?>')">
                            <i class="fas fa-times"></i> Отклонить
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            
            <!-- Пагинация -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?status=<?= $statusFilter ?>&page=<?= $i ?>" 
                   class="pagination__item <?= $i === $page ? 'pagination__item--active' : '' ?>">
                    <?= $i ?>
                </a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
            
            <?php endif; ?>
        </main>
    </div>
    
    <!-- Модальное окно отклонения -->
    <div class="modal" id="rejectModal">
        <div class="modal__content">
            <div class="modal__header">
                <h3 class="modal__title">Отклонить заявку</h3>
            </div>
            <form method="POST" id="rejectForm">
                <div class="modal__body">
                    <p style="margin-bottom: var(--space-4);">
                        Вы собираетесь отклонить заявку от компании <strong id="rejectCompanyName"></strong>.
                    </p>
                    <input type="hidden" name="application_id" id="rejectApplicationId">
                    <input type="hidden" name="action" value="reject">
                    
                    <div class="form-group">
                        <label for="rejection_reason" class="form-label">Причина отказа</label>
                        <textarea name="rejection_reason" 
                                  id="rejection_reason" 
                                  class="form-input" 
                                  rows="4" 
                                  placeholder="Укажите причину отклонения заявки..."></textarea>
                    </div>
                </div>
                <div class="modal__footer">
                    <button type="button" class="btn btn--secondary" onclick="closeRejectModal()">Отмена</button>
                    <button type="submit" class="btn btn--danger">Отклонить заявку</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openRejectModal(id, companyName) {
            document.getElementById('rejectApplicationId').value = id;
            document.getElementById('rejectCompanyName').textContent = companyName;
            document.getElementById('rejectModal').classList.add('active');
        }
        
        function closeRejectModal() {
            document.getElementById('rejectModal').classList.remove('active');
            document.getElementById('rejection_reason').value = '';
        }
        
        // Закрытие по клику вне модалки
        document.getElementById('rejectModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeRejectModal();
            }
        });
        
        // Закрытие по Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeRejectModal();
            }
        });
    </script>
</body>
</html>

