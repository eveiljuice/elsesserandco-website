<?php
/**
 * Admin Districts Management - Elsesser & Co.
 * Управление районами Екатеринбурга
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
                $slug = trim($_POST['slug'] ?? '');
                $sortOrder = (int)($_POST['sort_order'] ?? 0);
                
                if (empty($name)) {
                    throw new Exception('Введите название района');
                }
                
                // Генерация slug если пусто
                if (empty($slug)) {
                    $slug = transliterate($name);
                }
                $slug = preg_replace('/[^a-z0-9-]/', '', strtolower($slug));
                
                $stmt = $pdo->prepare("INSERT INTO ekb_districts (name, slug, sort_order) VALUES (?, ?, ?)");
                $stmt->execute([$name, $slug, $sortOrder]);
                $message = 'Район добавлен';
                $messageType = 'success';
                break;
                
            case 'edit':
                $id = (int)($_POST['id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $slug = trim($_POST['slug'] ?? '');
                $sortOrder = (int)($_POST['sort_order'] ?? 0);
                
                if (empty($name)) {
                    throw new Exception('Введите название района');
                }
                
                $stmt = $pdo->prepare("UPDATE ekb_districts SET name = ?, slug = ?, sort_order = ? WHERE id = ?");
                $stmt->execute([$name, $slug, $sortOrder, $id]);
                $message = 'Район обновлён';
                $messageType = 'success';
                break;
                
            case 'delete':
                $id = (int)($_POST['id'] ?? 0);
                
                // Проверяем, есть ли связанные объекты
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM properties WHERE district_id = ?");
                $stmt->execute([$id]);
                $propCount = $stmt->fetchColumn();
                
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM new_buildings WHERE district_id = ?");
                $stmt->execute([$id]);
                $nbCount = $stmt->fetchColumn();
                
                if ($propCount > 0 || $nbCount > 0) {
                    throw new Exception("Нельзя удалить район: есть связанные объекты ($propCount) или ЖК ($nbCount)");
                }
                
                $stmt = $pdo->prepare("DELETE FROM ekb_districts WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'Район удалён';
                $messageType = 'success';
                break;
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

// Получаем районы с количеством объектов
$stmt = $pdo->query("
    SELECT d.*, 
           (SELECT COUNT(*) FROM properties WHERE district_id = d.id) as properties_count,
           (SELECT COUNT(*) FROM new_buildings WHERE district_id = d.id) as buildings_count
    FROM ekb_districts d 
    ORDER BY d.sort_order, d.name
");
$districts = $stmt->fetchAll();

// Функция транслитерации
function transliterate($text) {
    $table = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'yo',
        'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
        'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '',
        'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya', ' ' => '-'
    ];
    return strtr(mb_strtolower($text), $table);
}

$pageTitle = 'Районы Екатеринбурга';
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
                        <a href="index.php">Dashboard</a> / Справочники / Районы
                    </div>
                </div>
                <button type="button" class="btn btn--primary" onclick="showAddModal()">
                    <i class="fas fa-plus"></i> Добавить район
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
                        <i class="fas fa-map-marker-alt"></i> Районы (<?= count($districts) ?>)
                    </h2>
                </div>
                <div class="admin-card__body admin-card__body--no-padding">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th style="width: 60px;">Порядок</th>
                                <th>Название</th>
                                <th>Slug</th>
                                <th>Объектов</th>
                                <th>ЖК</th>
                                <th style="width: 120px;">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($districts as $district): ?>
                            <tr>
                                <td>
                                    <span class="badge badge--secondary"><?= $district['sort_order'] ?></span>
                                </td>
                                <td>
                                    <strong><?= escape($district['name']) ?></strong>
                                </td>
                                <td>
                                    <code><?= escape($district['slug']) ?></code>
                                </td>
                                <td>
                                    <?php if ($district['properties_count'] > 0): ?>
                                    <a href="properties.php?district=<?= $district['id'] ?>" class="badge badge--info">
                                        <?= $district['properties_count'] ?>
                                    </a>
                                    <?php else: ?>
                                    <span class="text-muted">0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($district['buildings_count'] > 0): ?>
                                    <a href="new-buildings.php?district=<?= $district['id'] ?>" class="badge badge--success">
                                        <?= $district['buildings_count'] ?>
                                    </a>
                                    <?php else: ?>
                                    <span class="text-muted">0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <button type="button" class="btn btn--sm btn--secondary" 
                                                onclick="showEditModal(<?= $district['id'] ?>, '<?= escape($district['name']) ?>', '<?= escape($district['slug']) ?>', <?= $district['sort_order'] ?>)"
                                                title="Редактировать">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        
                                        <?php if ($district['properties_count'] == 0 && $district['buildings_count'] == 0): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Удалить район?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $district['id'] ?>">
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
                <h3 id="modalTitle">Добавить район</h3>
                <button type="button" class="modal__close" onclick="hideModal()">&times;</button>
            </div>
            <form method="POST" id="districtForm">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="formId" value="">
                
                <div class="modal__body">
                    <div class="form-group">
                        <label class="form-label required">Название района</label>
                        <input type="text" name="name" id="formName" class="form-input" required
                               placeholder="Академический">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">URL-slug</label>
                        <input type="text" name="slug" id="formSlug" class="form-input"
                               placeholder="akademicheskiy (генерируется автоматически)">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Порядок сортировки</label>
                        <input type="number" name="sort_order" id="formSortOrder" class="form-input" 
                               value="0" min="0" max="100">
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
    </style>
    
    <script>
    function showModal() {
        document.getElementById('modal').style.display = 'flex';
    }
    
    function hideModal() {
        document.getElementById('modal').style.display = 'none';
    }
    
    function showAddModal() {
        document.getElementById('modalTitle').textContent = 'Добавить район';
        document.getElementById('formAction').value = 'add';
        document.getElementById('formId').value = '';
        document.getElementById('formName').value = '';
        document.getElementById('formSlug').value = '';
        document.getElementById('formSortOrder').value = '0';
        showModal();
    }
    
    function showEditModal(id, name, slug, sortOrder) {
        document.getElementById('modalTitle').textContent = 'Редактировать район';
        document.getElementById('formAction').value = 'edit';
        document.getElementById('formId').value = id;
        document.getElementById('formName').value = name;
        document.getElementById('formSlug').value = slug;
        document.getElementById('formSortOrder').value = sortOrder;
        showModal();
    }
    </script>
</body>
</html>

