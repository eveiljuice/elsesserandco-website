<?php
/**
 * Agent Districts List - Elsesser & Co.
 * Справочник районов Екатеринбурга (только просмотр)
 */

require_once __DIR__ . '/../includes/config/database.php';
require_once __DIR__ . '/../includes/auth/check_auth.php';

requireAgent();

$pdo = getDBConnection();

// Получаем районы с количеством объектов
$stmt = $pdo->query("
    SELECT d.*, 
           (SELECT COUNT(*) FROM properties WHERE district_id = d.id) as properties_count,
           (SELECT COUNT(*) FROM new_buildings WHERE district_id = d.id) as buildings_count
    FROM ekb_districts d 
    ORDER BY d.sort_order
");
$districts = $stmt->fetchAll();

$pageTitle = 'Районы Екатеринбурга';
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
                        <a href="dashboard.php">Dashboard</a> / Справочники / Районы
                    </div>
                </div>
            </div>
            
            <div class="admin-card">
                <div class="admin-card__header">
                    <h2 class="admin-card__title">
                        <i class="fas fa-map-marker-alt"></i> Районы
                    </h2>
                </div>
                <div class="admin-card__body admin-card__body--no-padding">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Район</th>
                                <th>Slug</th>
                                <th>Объектов</th>
                                <th>ЖК</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($districts as $district): ?>
                            <tr>
                                <td><?= $district['sort_order'] ?></td>
                                <td>
                                    <strong><?= escape($district['name']) ?></strong>
                                </td>
                                <td>
                                    <code><?= escape($district['slug']) ?></code>
                                </td>
                                <td>
                                    <span class="badge badge--info"><?= $district['properties_count'] ?></span>
                                </td>
                                <td>
                                    <span class="badge badge--success"><?= $district['buildings_count'] ?></span>
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

