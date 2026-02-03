<?php
/**
 * Admin Developers Management - Elsesser & Co.
 * Управление застройщиками
 */

require_once __DIR__ . '/../includes/config/database.php';
require_once __DIR__ . '/../includes/auth/check_auth.php';

requireAdmin();

$pdo = getDBConnection();
$message = '';
$messageType = '';

// Обработка действий
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'add':
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $website = trim($_POST['website'] ?? '');
                $logo = trim($_POST['logo'] ?? '');
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                if (empty($name)) {
                    throw new Exception('Введите название застройщика');
                }
                
                $stmt = $pdo->prepare("INSERT INTO developers (name, description, website, logo, is_active) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $description ?: null, $website ?: null, $logo ?: null, $isActive]);
                $message = 'Застройщик добавлен';
                $messageType = 'success';
                break;
                
            case 'edit':
                $id = (int)($_POST['id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $website = trim($_POST['website'] ?? '');
                $logo = trim($_POST['logo'] ?? '');
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                if (empty($name)) {
                    throw new Exception('Введите название застройщика');
                }
                
                $stmt = $pdo->prepare("UPDATE developers SET name = ?, description = ?, website = ?, logo = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$name, $description ?: null, $website ?: null, $logo ?: null, $isActive, $id]);
                $message = 'Застройщик обновлён';
                $messageType = 'success';
                break;
                
            case 'toggle':
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $pdo->prepare("UPDATE developers SET is_active = NOT is_active WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'Статус изменён';
                $messageType = 'success';
                break;
                
            case 'delete':
                $id = (int)($_POST['id'] ?? 0);
                
                // Проверяем, есть ли связанные ЖК
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM new_buildings WHERE developer_id = ?");
                $stmt->execute([$id]);
                $nbCount = $stmt->fetchColumn();
                
                if ($nbCount > 0) {
                    throw new Exception("Нельзя удалить застройщика: есть связанные ЖК ($nbCount)");
                }
                
                $stmt = $pdo->prepare("DELETE FROM developers WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'Застройщик удалён';
                $messageType = 'success';
                break;
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

// Фильтр активности
$showAll = isset($_GET['all']);

$whereClause = $showAll ? '' : 'WHERE is_active = 1';

// Получаем застройщиков с количеством ЖК
$stmt = $pdo->query("
    SELECT d.*, 
           (SELECT COUNT(*) FROM new_buildings WHERE developer_id = d.id) as buildings_count
    FROM developers d 
    $whereClause
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
    <title><?= $pageTitle ?> | Admin CRM</title>
    
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
                    <h1 class="admin-title"><?= $pageTitle ?></h1>
                    <div class="admin-breadcrumb">
                        <a href="index.php">Dashboard</a> / Справочники / Застройщики
                    </div>
                </div>
                <button type="button" class="btn btn--primary" onclick="showAddModal()">
                    <i class="fas fa-plus"></i> Добавить застройщика
                </button>
            </div>
            
            <?php if ($message): ?>
            <div class="alert alert--<?= $messageType ?>">
                <?= escape($message) ?>
            </div>
            <?php endif; ?>
            
            <div class="admin-card">
                <div class="admin-card__header">
                    <h2 class="admin-card__title">
                        <i class="fas fa-hard-hat"></i> Застройщики (<?= count($developers) ?>)
                    </h2>
                    <div class="status-filters">
                        <a href="?" class="status-filter <?= !$showAll ? 'active' : '' ?>">Активные</a>
                        <a href="?all=1" class="status-filter <?= $showAll ? 'active' : '' ?>">Все</a>
                    </div>
                </div>
                <div class="admin-card__body admin-card__body--no-padding">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th style="width: 60px;">Лого</th>
                                <th>Название</th>
                                <th>Описание</th>
                                <th>Сайт</th>
                                <th>ЖК</th>
                                <th>Статус</th>
                                <th style="width: 150px;">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($developers as $dev): ?>
                            <tr class="<?= !$dev['is_active'] ? 'row--inactive' : '' ?>">
                                <td>
                                    <?php if (!empty($dev['logo'])): ?>
                                    <img src="<?= escape($dev['logo']) ?>" alt="" style="width: 40px; height: 40px; object-fit: contain; border-radius: 4px;">
                                    <?php else: ?>
                                    <div style="width: 40px; height: 40px; background: var(--color-bg-secondary); border-radius: 4px; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-building text-muted"></i>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= escape($dev['name']) ?></strong>
                                </td>
                                <td>
                                    <span class="text-truncate" style="max-width: 200px; display: block;">
                                        <?= escape($dev['description'] ?? '-') ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($dev['website'])): ?>
                                    <a href="<?= escape($dev['website']) ?>" target="_blank" class="text-link">
                                        <i class="fas fa-external-link-alt"></i> Сайт
                                    </a>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($dev['buildings_count'] > 0): ?>
                                    <a href="new-buildings.php?developer=<?= $dev['id'] ?>" class="badge badge--info">
                                        <?= $dev['buildings_count'] ?>
                                    </a>
                                    <?php else: ?>
                                    <span class="text-muted">0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= $dev['id'] ?>">
                                        <button type="submit" class="badge badge--<?= $dev['is_active'] ? 'success' : 'secondary' ?>" style="cursor: pointer; border: none;">
                                            <?= $dev['is_active'] ? 'Активен' : 'Неактивен' ?>
                                        </button>
                                    </form>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <button type="button" class="btn btn--sm btn--secondary" 
                                                onclick="showEditModal(<?= htmlspecialchars(json_encode($dev), ENT_QUOTES) ?>)"
                                                title="Редактировать">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        
                                        <?php if ($dev['buildings_count'] == 0): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Удалить застройщика?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $dev['id'] ?>">
                                            <button type="submit" class="btn btn--sm btn--danger" title="Удалить">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Modal для добавления/редактирования -->
    <div id="modal" class="modal" style="display: none;">
        <div class="modal__overlay" onclick="hideModal()"></div>
        <div class="modal__content">
            <div class="modal__header">
                <h3 id="modalTitle">Добавить застройщика</h3>
                <button type="button" class="modal__close" onclick="hideModal()">&times;</button>
            </div>
            <form method="POST" id="developerForm">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="formId" value="">
                
                <div class="modal__body">
                    <div class="form-group">
                        <label class="form-label required">Название</label>
                        <input type="text" name="name" id="formName" class="form-input" required
                               placeholder="ГК Брусника">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Описание</label>
                        <textarea name="description" id="formDescription" class="form-textarea" rows="3"
                                  placeholder="Краткое описание застройщика..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Сайт</label>
                        <input type="url" name="website" id="formWebsite" class="form-input"
                               placeholder="https://brusnika.ru">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">URL логотипа</label>
                        <input type="url" name="logo" id="formLogo" class="form-input"
                               placeholder="https://example.com/logo.png">
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_active" id="formIsActive" value="1" checked>
                            Активен (отображается в списках)
                        </label>
                    </div>
                </div>
                
                <div class="modal__footer">
                    <button type="button" class="btn btn--secondary" onclick="hideModal()">Отмена</button>
                    <button type="submit" class="btn btn--primary">Сохранить</button>
                </div>
            </form>
        </div>
    </div>
    
    <style>
    .modal {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 1000;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .modal__overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
    }
    .modal__content {
        position: relative;
        background: var(--color-bg-primary);
        border-radius: 8px;
        width: 100%;
        max-width: 500px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    }
    .modal__header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem 1.5rem;
        border-bottom: 1px solid var(--color-border);
    }
    .modal__header h3 {
        margin: 0;
    }
    .modal__close {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: var(--color-text-muted);
    }
    .modal__body {
        padding: 1.5rem;
    }
    .modal__footer {
        display: flex;
        justify-content: flex-end;
        gap: 0.5rem;
        padding: 1rem 1.5rem;
        border-top: 1px solid var(--color-border);
    }
    .row--inactive {
        opacity: 0.6;
    }
    .text-truncate {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    </style>
    
    <script>
    function showModal() {
        document.getElementById('modal').style.display = 'flex';
    }
    
    function hideModal() {
        document.getElementById('modal').style.display = 'none';
    }
    
    function showAddModal() {
        document.getElementById('modalTitle').textContent = 'Добавить застройщика';
        document.getElementById('formAction').value = 'add';
        document.getElementById('formId').value = '';
        document.getElementById('formName').value = '';
        document.getElementById('formDescription').value = '';
        document.getElementById('formWebsite').value = '';
        document.getElementById('formLogo').value = '';
        document.getElementById('formIsActive').checked = true;
        showModal();
    }
    
    function showEditModal(dev) {
        document.getElementById('modalTitle').textContent = 'Редактировать застройщика';
        document.getElementById('formAction').value = 'edit';
        document.getElementById('formId').value = dev.id;
        document.getElementById('formName').value = dev.name;
        document.getElementById('formDescription').value = dev.description || '';
        document.getElementById('formWebsite').value = dev.website || '';
        document.getElementById('formLogo').value = dev.logo || '';
        document.getElementById('formIsActive').checked = dev.is_active == 1;
        showModal();
    }
    </script>
</body>
</html>

