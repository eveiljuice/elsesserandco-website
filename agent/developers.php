<?php
/**
 * Agent Developers List - Elsesser & Co.
 * Справочник застройщиков (только просмотр)
 */

require_once __DIR__ . '/../includes/config/database.php';
require_once __DIR__ . '/../includes/auth/check_auth.php';

requireAgent();

$pdo = getDBConnection();

// Получаем застройщиков с количеством ЖК
$stmt = $pdo->query("
    SELECT d.*, 
           (SELECT COUNT(*) FROM new_buildings WHERE developer_id = d.id) as buildings_count
    FROM developers d 
    WHERE d.is_active = 1 
    ORDER BY d.name
");
$developers = $stmt->fetchAll();

$pageTitle = 'Застройщики';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | Elsesser & Co.</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <link rel="stylesheet" href="../css/reset.css">
    <link rel="stylesheet" href="../css/variables.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/agent-dashboard.css">
</head>
<body class="admin-body">
    <?php include __DIR__ . '/includes/agent-header.php'; ?>
    
    <div class="admin-container">
        <?php include __DIR__ . '/includes/agent-sidebar.php'; ?>
        
        <main class="admin-main">
            <div class="admin-header">
                <div>
                    <h1 class="admin-title"><?= $pageTitle ?></h1>
                    <div class="admin-breadcrumb">
                        <a href="dashboard.php">Dashboard</a> / Справочники / Застройщики
                    </div>
                </div>
            </div>
            
            <div class="admin-card">
                <div class="admin-card__header">
                    <h2 class="admin-card__title">
                        <i class="fas fa-hard-hat"></i> Застройщики Екатеринбурга
                    </h2>
                </div>
                <div class="admin-card__body admin-card__body--no-padding">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Название</th>
                                <th>Описание</th>
                                <th>Сайт</th>
                                <th>ЖК в базе</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($developers as $dev): ?>
                            <tr>
                                <td>
                                    <strong><?= escape($dev['name']) ?></strong>
                                </td>
                                <td>
                                    <?= escape($dev['description'] ?? '-') ?>
                                </td>
                                <td>
                                    <?php if (!empty($dev['website'])): ?>
                                    <a href="<?= escape($dev['website']) ?>" target="_blank" class="text-link">
                                        <i class="fas fa-external-link-alt"></i> Сайт
                                    </a>
                                    <?php else: ?>
                                    -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge--info"><?= $dev['buildings_count'] ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

