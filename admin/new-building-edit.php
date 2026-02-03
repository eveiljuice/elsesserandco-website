<?php
/**
 * Admin New Building Edit - Elsesser & Co.
 * Добавление/редактирование ЖК
 */

require_once __DIR__ . '/../includes/config/database.php';
require_once __DIR__ . '/../includes/auth/check_auth.php';

requireAdmin();

$pdo = getDBConnection();
$errors = [];
$message = '';
$buildingId = (int)($_GET['id'] ?? 0);
$isEdit = $buildingId > 0;

// Получаем данные ЖК если редактирование
$building = [];
$images = [];
$layouts = [];

if ($isEdit) {
    $stmt = $pdo->prepare("SELECT * FROM new_buildings WHERE id = ?");
    $stmt->execute([$buildingId]);
    $building = $stmt->fetch();
    
    if (!$building) {
        header("Location: new-buildings.php");
        exit;
    }
    
    // Изображения
    $stmt = $pdo->prepare("SELECT * FROM new_building_images WHERE new_building_id = ? ORDER BY is_primary DESC, sort_order ASC");
    $stmt->execute([$buildingId]);
    $images = $stmt->fetchAll();
    
    // Планировки
    $stmt = $pdo->prepare("SELECT * FROM new_building_layouts WHERE new_building_id = ? ORDER BY rooms ASC");
    $stmt->execute([$buildingId]);
    $layouts = $stmt->fetchAll();
}

// Справочники
$districts = $pdo->query("SELECT id, name FROM ekb_districts ORDER BY sort_order")->fetchAll();
$developers = $pdo->query("SELECT id, name FROM developers WHERE is_active = 1 ORDER BY name")->fetchAll();
$agents = $pdo->query("SELECT id, first_name, last_name FROM users WHERE role IN ('agent', 'admin') ORDER BY first_name")->fetchAll();

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Основные данные
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $developerId = (int)($_POST['developer_id'] ?? 0) ?: null;
    $districtId = (int)($_POST['district_id'] ?? 0) ?: null;
    $address = trim($_POST['address'] ?? '');
    
    // Сроки
    $completionQuarter = (int)($_POST['completion_quarter'] ?? 0) ?: null;
    $completionYear = (int)($_POST['completion_year'] ?? 0) ?: null;
    $isCompleted = isset($_POST['is_completed']) ? 1 : 0;
    $constructionStage = $_POST['construction_stage'] ?? 'construction';
    
    // Цены
    $priceFrom = (float)($_POST['price_from'] ?? 0) ?: null;
    $pricePerSqmFrom = (float)($_POST['price_per_sqm_from'] ?? 0) ?: null;
    
    // Характеристики дома
    $houseType = $_POST['house_type'] ?? null;
    $floorsMin = (int)($_POST['floors_min'] ?? 0) ?: null;
    $floorsMax = (int)($_POST['floors_max'] ?? 0) ?: null;
    $sectionsCount = (int)($_POST['sections_count'] ?? 0) ?: null;
    $apartmentsCount = (int)($_POST['apartments_count'] ?? 0) ?: null;
    $ceilingHeight = (float)($_POST['ceiling_height'] ?? 0) ?: null;
    $parkingType = isset($_POST['parking_type']) ? implode(',', $_POST['parking_type']) : null;
    $parkingPriceFrom = (float)($_POST['parking_price_from'] ?? 0) ?: null;
    $finishType = isset($_POST['finish_type']) ? implode(',', $_POST['finish_type']) : null;
    
    // Описания
    $description = trim($_POST['description'] ?? '');
    $aboutHouse = trim($_POST['about_house'] ?? '');
    $aboutArea = trim($_POST['about_area'] ?? '');
    $advantages = trim($_POST['advantages'] ?? '');
    $purchaseConditions = trim($_POST['purchase_conditions'] ?? '');
    
    // Транспорт
    $metroStation = trim($_POST['metro_station'] ?? '');
    $metroMinutes = (int)($_POST['metro_minutes'] ?? 0) ?: null;
    $transportInfo = trim($_POST['transport_info'] ?? '');
    
    
    // Статус
    $status = $_POST['status'] ?? 'active';
    $featured = isset($_POST['featured']) ? 1 : 0;
    $agentId = (int)($_POST['agent_id'] ?? 0) ?: null;
    
    // Валидация
    if (empty($name)) $errors[] = 'Введите название ЖК';
    if (empty($address)) $errors[] = 'Введите адрес';
    
    // Генерация slug если пусто
    if (empty($slug)) {
        $slug = transliterate($name);
    }
    $slug = preg_replace('/[^a-z0-9-]/', '', strtolower($slug));
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            if ($isEdit) {
                $stmt = $pdo->prepare("
                    UPDATE new_buildings SET
                        name = ?, slug = ?, developer_id = ?, district_id = ?, address = ?,
                        completion_quarter = ?, completion_year = ?, is_completed = ?, construction_stage = ?,
                        price_from = ?, price_per_sqm_from = ?,
                        house_type = ?, floors_min = ?, floors_max = ?, sections_count = ?, apartments_count = ?,
                        ceiling_height = ?, parking_type = ?, parking_price_from = ?, finish_type = ?,
                        description = ?, about_house = ?, about_area = ?, advantages = ?, purchase_conditions = ?,
                        metro_station = ?, metro_minutes = ?, transport_info = ?,
                        status = ?, featured = ?, agent_id = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $name, $slug, $developerId, $districtId, $address,
                    $completionQuarter, $completionYear, $isCompleted, $constructionStage,
                    $priceFrom, $pricePerSqmFrom,
                    $houseType, $floorsMin, $floorsMax, $sectionsCount, $apartmentsCount,
                    $ceilingHeight, $parkingType, $parkingPriceFrom, $finishType,
                    $description ?: null, $aboutHouse ?: null, $aboutArea ?: null, $advantages ?: null, $purchaseConditions ?: null,
                    $metroStation ?: null, $metroMinutes, $transportInfo ?: null,
                    $status, $featured, $agentId,
                    $buildingId
                ]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO new_buildings (
                        name, slug, developer_id, district_id, address,
                        completion_quarter, completion_year, is_completed, construction_stage,
                        price_from, price_per_sqm_from,
                        house_type, floors_min, floors_max, sections_count, apartments_count,
                        ceiling_height, parking_type, parking_price_from, finish_type,
                        description, about_house, about_area, advantages, purchase_conditions,
                        metro_station, metro_minutes, transport_info,
                        status, featured, agent_id,
                        created_at, updated_at
                    ) VALUES (
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?,
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?, ?, ?,
                        ?, ?, ?,
                        ?, ?, ?,
                        NOW(), NOW()
                    )
                ");
                $stmt->execute([
                    $name, $slug, $developerId, $districtId, $address,
                    $completionQuarter, $completionYear, $isCompleted, $constructionStage,
                    $priceFrom, $pricePerSqmFrom,
                    $houseType, $floorsMin, $floorsMax, $sectionsCount, $apartmentsCount,
                    $ceilingHeight, $parkingType, $parkingPriceFrom, $finishType,
                    $description ?: null, $aboutHouse ?: null, $aboutArea ?: null, $advantages ?: null, $purchaseConditions ?: null,
                    $metroStation ?: null, $metroMinutes, $transportInfo ?: null,
                    $status, $featured, $agentId
                ]);
                $buildingId = $pdo->lastInsertId();
            }
            
            // Удаление старых изображений если указано
            if (!empty($_POST['delete_all_images'])) {
                $stmt = $pdo->prepare("SELECT image_url FROM new_building_images WHERE new_building_id = ?");
                $stmt->execute([$buildingId]);
                $oldImages = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                foreach ($oldImages as $oldImageUrl) {
                    $filePath = __DIR__ . '/..' . $oldImageUrl;
                    if (file_exists($filePath) && strpos($oldImageUrl, '/uploads/') === 0) {
                        unlink($filePath);
                    }
                }
                
                $pdo->prepare("DELETE FROM new_building_images WHERE new_building_id = ?")->execute([$buildingId]);
            }
            
            // Обработка новых изображений
            if (!empty($_FILES['building_images']['name'][0])) {
                $uploadDir = __DIR__ . '/../uploads/new-buildings/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                // Получаем текущее количество изображений
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM new_building_images WHERE new_building_id = ?");
                $stmt->execute([$buildingId]);
                $currentCount = $stmt->fetchColumn();
                $sortOrder = $currentCount;
                
                $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
                
                foreach ($_FILES['building_images']['tmp_name'] as $key => $tmpName) {
                    if (empty($tmpName)) continue;
                    
                    $fileType = $_FILES['building_images']['type'][$key];
                    if (!in_array($fileType, $allowedTypes)) continue;
                    
                    $extension = pathinfo($_FILES['building_images']['name'][$key], PATHINFO_EXTENSION);
                    $fileName = 'building_' . $buildingId . '_' . uniqid() . '_' . time() . '.' . $extension;
                    $filePath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($tmpName, $filePath)) {
                        $imageUrl = '/uploads/new-buildings/' . $fileName;
                        $stmt = $pdo->prepare("
                            INSERT INTO new_building_images (new_building_id, image_url, image_type, is_primary, sort_order)
                            VALUES (?, ?, 'exterior', ?, ?)
                        ");
                        $stmt->execute([$buildingId, $imageUrl, $sortOrder === 0 ? 1 : 0, $sortOrder]);
                        $sortOrder++;
                    }
                }
            }
            
            // Обработка планировок
            $pdo->prepare("DELETE FROM new_building_layouts WHERE new_building_id = ?")->execute([$buildingId]);
            
            if (!empty($_POST['layout_rooms'])) {
                $stmt = $pdo->prepare("
                    INSERT INTO new_building_layouts (new_building_id, rooms, area_from, area_to, price_from, available_count, is_available)
                    VALUES (?, ?, ?, ?, ?, ?, 1)
                ");
                
                foreach ($_POST['layout_rooms'] as $i => $rooms) {
                    $areaFrom = (float)($_POST['layout_area_from'][$i] ?? 0);
                    $areaTo = (float)($_POST['layout_area_to'][$i] ?? 0) ?: null;
                    $layoutPrice = (float)($_POST['layout_price'][$i] ?? 0) ?: null;
                    $availableCount = (int)($_POST['layout_count'][$i] ?? 0) ?: null;
                    
                    if ($areaFrom > 0) {
                        $stmt->execute([$buildingId, (int)$rooms, $areaFrom, $areaTo, $layoutPrice, $availableCount]);
                    }
                }
            }
            
            $pdo->commit();
            
            header("Location: new-building-edit.php?id=$buildingId&success=1");
            exit;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = 'Ошибка при сохранении: ' . $e->getMessage();
        }
    }
} else {
    if ($isEdit && isset($_GET['success'])) {
        $message = 'ЖК успешно сохранён';
    }
}

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

$pageTitle = $isEdit ? 'Редактирование ЖК' : 'Новый ЖК';
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
                    <?php if ($isEdit): ?>
                    <div class="admin-breadcrumb">
                        <a href="new-buildings.php">Новостройки</a> / <?= escape($building['name']) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <a href="new-buildings.php" class="btn btn--secondary">
                    <i class="fas fa-arrow-left"></i> К списку
                </a>
            </div>
            
            <?php if ($message): ?>
            <div class="alert alert--success"><?= escape($message) ?></div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
            <div class="alert alert--error">
                <strong>Ошибки:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                    <li><?= escape($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data" class="nb-form">
                <div class="admin-form-grid">
                    <!-- Левая колонка -->
                    <div class="admin-form-column">
                        
                        <!-- Основная информация -->
                        <div class="admin-card">
                            <div class="admin-card__header">
                                <h3><i class="fas fa-info-circle"></i> Основная информация</h3>
                            </div>
                            <div class="admin-card__body">
                                <div class="form-group">
                                    <label class="form-label required">Название ЖК</label>
                                    <input type="text" name="name" class="form-input" 
                                           value="<?= escape($building['name'] ?? '') ?>" required
                                           placeholder="ЖК Светлый">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">URL-slug</label>
                                    <input type="text" name="slug" class="form-input" 
                                           value="<?= escape($building['slug'] ?? '') ?>"
                                           placeholder="zhk-svetliy (генерируется автоматически)">
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Застройщик</label>
                                        <select name="developer_id" class="form-select">
                                            <option value="">Выберите</option>
                                            <?php foreach ($developers as $dev): ?>
                                            <option value="<?= $dev['id'] ?>" <?= ($building['developer_id'] ?? 0) == $dev['id'] ? 'selected' : '' ?>>
                                                <?= escape($dev['name']) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Район</label>
                                        <select name="district_id" class="form-select">
                                            <option value="">Выберите</option>
                                            <?php foreach ($districts as $d): ?>
                                            <option value="<?= $d['id'] ?>" <?= ($building['district_id'] ?? 0) == $d['id'] ? 'selected' : '' ?>>
                                                <?= escape($d['name']) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label required">Адрес</label>
                                    <input type="text" name="address" class="form-input" required
                                           value="<?= escape($building['address'] ?? '') ?>"
                                           placeholder="ул. Краснолесья, 123">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Сроки сдачи -->
                        <div class="admin-card">
                            <div class="admin-card__header">
                                <h3><i class="fas fa-calendar"></i> Сроки</h3>
                            </div>
                            <div class="admin-card__body">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Квартал сдачи</label>
                                        <select name="completion_quarter" class="form-select">
                                            <option value="">—</option>
                                            <option value="1" <?= ($building['completion_quarter'] ?? 0) == 1 ? 'selected' : '' ?>>Q1 (янв-мар)</option>
                                            <option value="2" <?= ($building['completion_quarter'] ?? 0) == 2 ? 'selected' : '' ?>>Q2 (апр-июн)</option>
                                            <option value="3" <?= ($building['completion_quarter'] ?? 0) == 3 ? 'selected' : '' ?>>Q3 (июл-сен)</option>
                                            <option value="4" <?= ($building['completion_quarter'] ?? 0) == 4 ? 'selected' : '' ?>>Q4 (окт-дек)</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Год</label>
                                        <select name="completion_year" class="form-select">
                                            <option value="">—</option>
                                            <?php for ($y = 2024; $y <= 2030; $y++): ?>
                                            <option value="<?= $y ?>" <?= ($building['completion_year'] ?? 0) == $y ? 'selected' : '' ?>><?= $y ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Стадия строительства</label>
                                        <select name="construction_stage" class="form-select">
                                            <option value="project" <?= ($building['construction_stage'] ?? '') === 'project' ? 'selected' : '' ?>>Проект</option>
                                            <option value="foundation" <?= ($building['construction_stage'] ?? '') === 'foundation' ? 'selected' : '' ?>>Фундамент</option>
                                            <option value="construction" <?= ($building['construction_stage'] ?? 'construction') === 'construction' ? 'selected' : '' ?>>Строительство</option>
                                            <option value="finishing" <?= ($building['construction_stage'] ?? '') === 'finishing' ? 'selected' : '' ?>>Отделка</option>
                                            <option value="completed" <?= ($building['construction_stage'] ?? '') === 'completed' ? 'selected' : '' ?>>Завершён</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="checkbox-label" style="margin-top: 28px;">
                                            <input type="checkbox" name="is_completed" value="1" <?= ($building['is_completed'] ?? 0) ? 'checked' : '' ?>>
                                            <span>Дом сдан</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Характеристики дома -->
                        <div class="admin-card">
                            <div class="admin-card__header">
                                <h3><i class="fas fa-building"></i> Характеристики дома</h3>
                            </div>
                            <div class="admin-card__body">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Тип дома</label>
                                        <select name="house_type" class="form-select">
                                            <option value="">—</option>
                                            <option value="panel" <?= ($building['house_type'] ?? '') === 'panel' ? 'selected' : '' ?>>Панельный</option>
                                            <option value="brick" <?= ($building['house_type'] ?? '') === 'brick' ? 'selected' : '' ?>>Кирпичный</option>
                                            <option value="monolith" <?= ($building['house_type'] ?? '') === 'monolith' ? 'selected' : '' ?>>Монолитный</option>
                                            <option value="monolith-brick" <?= ($building['house_type'] ?? '') === 'monolith-brick' ? 'selected' : '' ?>>Монолит-кирпич</option>
                                            <option value="block" <?= ($building['house_type'] ?? '') === 'block' ? 'selected' : '' ?>>Блочный</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Высота потолков, м</label>
                                        <input type="number" name="ceiling_height" class="form-input" step="0.01"
                                               value="<?= $building['ceiling_height'] ?? '' ?>" placeholder="2.70">
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Этажей от</label>
                                        <input type="number" name="floors_min" class="form-input" min="1"
                                               value="<?= $building['floors_min'] ?? '' ?>">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Этажей до</label>
                                        <input type="number" name="floors_max" class="form-input" min="1"
                                               value="<?= $building['floors_max'] ?? '' ?>">
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Секций/корпусов</label>
                                        <input type="number" name="sections_count" class="form-input" min="1"
                                               value="<?= $building['sections_count'] ?? '' ?>">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Квартир всего</label>
                                        <input type="number" name="apartments_count" class="form-input" min="1"
                                               value="<?= $building['apartments_count'] ?? '' ?>">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Паркинг</label>
                                    <div class="checkbox-group">
                                        <?php $parkingTypes = explode(',', $building['parking_type'] ?? ''); ?>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="parking_type[]" value="underground" <?= in_array('underground', $parkingTypes) ? 'checked' : '' ?>>
                                            <span>Подземный</span>
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="parking_type[]" value="ground" <?= in_array('ground', $parkingTypes) ? 'checked' : '' ?>>
                                            <span>Наземный</span>
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="parking_type[]" value="multilevel" <?= in_array('multilevel', $parkingTypes) ? 'checked' : '' ?>>
                                            <span>Многоуровневый</span>
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="parking_type[]" value="open" <?= in_array('open', $parkingTypes) ? 'checked' : '' ?>>
                                            <span>Открытый</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Отделка</label>
                                    <div class="checkbox-group">
                                        <?php $finishTypes = explode(',', $building['finish_type'] ?? ''); ?>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="finish_type[]" value="rough" <?= in_array('rough', $finishTypes) ? 'checked' : '' ?>>
                                            <span>Черновая</span>
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="finish_type[]" value="pre-finish" <?= in_array('pre-finish', $finishTypes) ? 'checked' : '' ?>>
                                            <span>Предчистовая</span>
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="finish_type[]" value="white-box" <?= in_array('white-box', $finishTypes) ? 'checked' : '' ?>>
                                            <span>White box</span>
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="finish_type[]" value="turnkey" <?= in_array('turnkey', $finishTypes) ? 'checked' : '' ?>>
                                            <span>Под ключ</span>
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="finish_type[]" value="design" <?= in_array('design', $finishTypes) ? 'checked' : '' ?>>
                                            <span>Дизайнерская</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Описания -->
                        <div class="admin-card">
                            <div class="admin-card__header">
                                <h3><i class="fas fa-align-left"></i> Описания</h3>
                            </div>
                            <div class="admin-card__body">
                                <div class="form-group">
                                    <label class="form-label">Общее описание</label>
                                    <textarea name="description" class="form-textarea" rows="4"><?= escape($building['description'] ?? '') ?></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">О доме</label>
                                    <textarea name="about_house" class="form-textarea" rows="4"><?= escape($building['about_house'] ?? '') ?></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">О районе</label>
                                    <textarea name="about_area" class="form-textarea" rows="4"><?= escape($building['about_area'] ?? '') ?></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Преимущества (каждое с новой строки)</label>
                                    <textarea name="advantages" class="form-textarea" rows="4" 
                                              placeholder="Закрытая территория&#10;Подземный паркинг&#10;Рядом парк"><?= escape($building['advantages'] ?? '') ?></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Условия покупки</label>
                                    <textarea name="purchase_conditions" class="form-textarea" rows="3"><?= escape($building['purchase_conditions'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                    
                    <!-- Правая колонка -->
                    <div class="admin-form-column">
                        
                        <!-- Цены -->
                        <div class="admin-card">
                            <div class="admin-card__header">
                                <h3><i class="fas fa-ruble-sign"></i> Цены</h3>
                            </div>
                            <div class="admin-card__body">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Цена от, ₽</label>
                                        <input type="number" name="price_from" class="form-input" step="10000"
                                               value="<?= $building['price_from'] ?? '' ?>" placeholder="4 500 000">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Цена за м² от, ₽</label>
                                        <input type="number" name="price_per_sqm_from" class="form-input" step="1000"
                                               value="<?= $building['price_per_sqm_from'] ?? '' ?>" placeholder="120 000">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Паркинг от, ₽</label>
                                    <input type="number" name="parking_price_from" class="form-input" step="10000"
                                           value="<?= $building['parking_price_from'] ?? '' ?>">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Планировки -->
                        <div class="admin-card">
                            <div class="admin-card__header">
                                <h3><i class="fas fa-th-large"></i> Планировки</h3>
                            </div>
                            <div class="admin-card__body">
                                <div class="layouts-list" id="layoutsList">
                                    <?php 
                                    $defaultLayouts = [
                                        ['rooms' => 0, 'label' => 'Студия'],
                                        ['rooms' => 1, 'label' => '1-комн.'],
                                        ['rooms' => 2, 'label' => '2-комн.'],
                                        ['rooms' => 3, 'label' => '3-комн.'],
                                        ['rooms' => 4, 'label' => '4-комн.'],
                                    ];
                                    
                                    foreach ($defaultLayouts as $dl):
                                        $existing = array_filter($layouts, fn($l) => $l['rooms'] == $dl['rooms']);
                                        $existing = reset($existing) ?: [];
                                    ?>
                                    <div class="layout-row">
                                        <div class="layout-row__label"><?= $dl['label'] ?></div>
                                        <input type="hidden" name="layout_rooms[]" value="<?= $dl['rooms'] ?>">
                                        <div class="layout-row__fields">
                                            <input type="number" name="layout_area_from[]" class="form-input" step="0.1" 
                                                   placeholder="от м²" value="<?= $existing['area_from'] ?? '' ?>">
                                            <input type="number" name="layout_area_to[]" class="form-input" step="0.1" 
                                                   placeholder="до м²" value="<?= $existing['area_to'] ?? '' ?>">
                                            <input type="number" name="layout_price[]" class="form-input" step="10000" 
                                                   placeholder="от ₽" value="<?= $existing['price_from'] ?? '' ?>">
                                            <input type="number" name="layout_count[]" class="form-input" 
                                                   placeholder="кол-во" value="<?= $existing['available_count'] ?? '' ?>">
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <span class="form-help">Заполните только те типы квартир, которые есть в ЖК</span>
                            </div>
                        </div>
                        
                        <!-- Транспорт -->
                        <div class="admin-card">
                            <div class="admin-card__header">
                                <h3><i class="fas fa-subway"></i> Транспорт</h3>
                            </div>
                            <div class="admin-card__body">
                                <div class="form-row">
                                    <div class="form-group form-group--2">
                                        <label class="form-label">Метро</label>
                                        <select name="metro_station" class="form-select">
                                            <option value="">—</option>
                                            <option value="Ботаническая" <?= ($building['metro_station'] ?? '') === 'Ботаническая' ? 'selected' : '' ?>>Ботаническая</option>
                                            <option value="Чкаловская" <?= ($building['metro_station'] ?? '') === 'Чкаловская' ? 'selected' : '' ?>>Чкаловская</option>
                                            <option value="Геологическая" <?= ($building['metro_station'] ?? '') === 'Геологическая' ? 'selected' : '' ?>>Геологическая</option>
                                            <option value="Площадь 1905 года" <?= ($building['metro_station'] ?? '') === 'Площадь 1905 года' ? 'selected' : '' ?>>Площадь 1905 года</option>
                                            <option value="Динамо" <?= ($building['metro_station'] ?? '') === 'Динамо' ? 'selected' : '' ?>>Динамо</option>
                                            <option value="Уральская" <?= ($building['metro_station'] ?? '') === 'Уральская' ? 'selected' : '' ?>>Уральская</option>
                                            <option value="Машиностроителей" <?= ($building['metro_station'] ?? '') === 'Машиностроителей' ? 'selected' : '' ?>>Машиностроителей</option>
                                            <option value="Уралмаш" <?= ($building['metro_station'] ?? '') === 'Уралмаш' ? 'selected' : '' ?>>Уралмаш</option>
                                            <option value="Проспект Космонавтов" <?= ($building['metro_station'] ?? '') === 'Проспект Космонавтов' ? 'selected' : '' ?>>Проспект Космонавтов</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Минут</label>
                                        <input type="number" name="metro_minutes" class="form-input" min="1"
                                               value="<?= $building['metro_minutes'] ?? '' ?>">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Дополнительно о транспорте</label>
                                    <textarea name="transport_info" class="form-textarea" rows="2"><?= escape($building['transport_info'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Изображения -->
                        <div class="admin-card">
                            <div class="admin-card__header">
                                <h3><i class="fas fa-images"></i> Изображения</h3>
                            </div>
                            <div class="admin-card__body">
                                <?php if (!empty($images)): ?>
                                <div class="image-preview-grid">
                                    <?php foreach ($images as $img): ?>
                                    <div class="image-preview">
                                        <img src="<?= escape($img['image_url']) ?>" alt="">
                                        <?php if ($img['is_primary']): ?>
                                        <span class="image-preview__badge">Главное</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="form-group" style="margin-top: var(--space-4);">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="delete_all_images" value="1">
                                        <span>Удалить все текущие изображения</span>
                                    </label>
                                </div>
                                <?php endif; ?>
                                
                                <div class="form-group" style="margin-top: var(--space-4);">
                                    <label class="form-label">Загрузить изображения (минимум 5 шт.)</label>
                                    <input type="file" name="building_images[]" class="form-input" multiple accept="image/jpeg,image/jpg,image/png,image/webp">
                                    <span class="form-help">Форматы: JPG, PNG, WEBP. Рекомендуется минимум 5-10 фото ЖК.</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Настройки -->
                        <div class="admin-card">
                            <div class="admin-card__header">
                                <h3><i class="fas fa-cog"></i> Настройки</h3>
                            </div>
                            <div class="admin-card__body">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Статус</label>
                                        <select name="status" class="form-select">
                                            <option value="active" <?= ($building['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Активен</option>
                                            <option value="hidden" <?= ($building['status'] ?? '') === 'hidden' ? 'selected' : '' ?>>Скрыт</option>
                                            <option value="sold-out" <?= ($building['status'] ?? '') === 'sold-out' ? 'selected' : '' ?>>Распродан</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Агент</label>
                                        <select name="agent_id" class="form-select">
                                            <option value="">Не назначен</option>
                                            <?php foreach ($agents as $a): ?>
                                            <option value="<?= $a['id'] ?>" <?= ($building['agent_id'] ?? 0) == $a['id'] ? 'selected' : '' ?>>
                                                <?= escape($a['first_name'] . ' ' . $a['last_name']) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="featured" value="1" <?= ($building['featured'] ?? 0) ? 'checked' : '' ?>>
                                        <span><i class="fas fa-star"></i> Рекомендуемый ЖК</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Сохранение -->
                        <div class="admin-card">
                            <div class="admin-card__body">
                                <button type="submit" class="btn btn--primary btn--full btn--lg">
                                    <i class="fas fa-save"></i> <?= $isEdit ? 'Сохранить изменения' : 'Создать ЖК' ?>
                                </button>
                            </div>
                        </div>
                        
                    </div>
                </div>
            </form>
        </main>
    </div>
    
    <style>
        .nb-form {
            max-width: 1400px;
        }
        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-3);
        }
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            cursor: pointer;
        }
        .checkbox-label i {
            color: var(--color-accent);
        }
        .layout-row {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-3) 0;
            border-bottom: 1px solid var(--color-border);
        }
        .layout-row:last-child {
            border-bottom: none;
        }
        .layout-row__label {
            width: 80px;
            font-weight: var(--font-medium);
            color: var(--color-navy);
        }
        .layout-row__fields {
            display: flex;
            gap: var(--space-2);
            flex: 1;
        }
        .layout-row__fields .form-input {
            flex: 1;
            min-width: 0;
        }
        .form-group--2 {
            flex: 2;
        }
    </style>
</body>
</html>

