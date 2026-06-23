<?php
/**
 * Admin Property Edit - Elsesser & Co.
 * Добавление/редактирование готового жилья (Екатеринбург)
 */

require_once __DIR__ . '/../includes/config/database.php';
require_once __DIR__ . '/../includes/auth/check_auth.php';

requireAdmin();

$pdo = getDBConnection();
$errors = [];
$message = '';
$propertyId = (int)($_GET['id'] ?? 0);
$isEdit = $propertyId > 0;
$category = $_GET['category'] ?? 'sale';

// Получаем данные объекта если редактирование
$property = [];
$images = [];

if ($isEdit) {
    $stmt = $pdo->prepare("SELECT * FROM properties WHERE id = ?");
    $stmt->execute([$propertyId]);
    $property = $stmt->fetch();
    
    if (!$property) {
        header("Location: properties.php");
        exit;
    }
    
    $category = $property['category'] ?? 'sale';
    
    // Получаем изображения
    $stmt = $pdo->prepare("SELECT * FROM property_images WHERE property_id = ? ORDER BY is_primary DESC, sort_order ASC");
    $stmt->execute([$propertyId]);
    $images = $stmt->fetchAll();
}

// Получаем справочники
$districts = $pdo->query("SELECT id, name FROM ekb_districts ORDER BY sort_order")->fetchAll();
$agents = $pdo->query("SELECT id, first_name, last_name FROM users WHERE role IN ('agent', 'admin') ORDER BY first_name")->fetchAll();
$amenitiesList = $pdo->query("SELECT * FROM amenities ORDER BY name_ru")->fetchAll();

// Получаем выбранные удобства
$selectedAmenities = [];
if ($isEdit) {
    $stmt = $pdo->prepare("SELECT amenity_id FROM property_amenities WHERE property_id = ?");
    $stmt->execute([$propertyId]);
    $selectedAmenities = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Основные данные
    $category = $_POST['category'] ?? 'sale';
    $titleRu = trim($_POST['title_ru'] ?? '');
    $descriptionRu = trim($_POST['description_ru'] ?? '');
    $propertyType = $_POST['property_type'] ?? '';
    $price = (float)($_POST['price'] ?? 0);
    $districtId = (int)($_POST['district_id'] ?? 0) ?: null;
    $street = trim($_POST['street'] ?? '');
    $houseNumber = trim($_POST['house_number'] ?? '');
    $location = trim($_POST['location'] ?? '');
    
    // Площади
    $areaTotal = (float)($_POST['area_total'] ?? 0);
    $areaLiving = (float)($_POST['area_living'] ?? 0) ?: null;
    $areaKitchen = (float)($_POST['area_kitchen'] ?? 0) ?: null;
    
    // Комнаты
    $bedrooms = (int)($_POST['bedrooms'] ?? 0);
    $roomsType = ($_POST['rooms_type'] ?? null) ?: null;  // Конвертируем пустую строку в null
    $bathrooms = (int)($_POST['bathrooms'] ?? 0);
    $bathroomType = ($_POST['bathroom_type'] ?? null) ?: null;
    
    // Этажи
    $floorNumber = (int)($_POST['floor_number'] ?? 0) ?: null;
    $totalFloors = (int)($_POST['total_floors'] ?? 0) ?: null;
    
    // Характеристики квартиры
    $renovation = ($_POST['renovation'] ?? null) ?: null;
    $furnished = $_POST['furnished'] ?? 'unfurnished';
    $balcony = ($_POST['balcony'] ?? null) ?: null;
    $balconyCount = (int)($_POST['balcony_count'] ?? 0);
    $windowView = ($_POST['window_view'] ?? null) ?: null;
    
    // Характеристики дома
    $buildingName = trim($_POST['building_name'] ?? '');
    $houseType = ($_POST['house_type'] ?? null) ?: null;
    $buildYear = (int)($_POST['build_year'] ?? 0) ?: null;
    $ceilingHeight = (float)($_POST['ceiling_height'] ?? 0) ?: null;
    $hasElevator = isset($_POST['has_elevator']) ? 1 : 0;
    $hasGarbageChute = isset($_POST['has_garbage_chute']) ? 1 : 0;
    $isNewBuilding = isset($_POST['is_new_building']) ? 1 : 0;
    
    // Транспорт
    $metroStation = trim($_POST['metro_station'] ?? '');
    $metroMinutes = (int)($_POST['metro_minutes'] ?? 0) ?: null;
    $metroWalkType = $_POST['metro_walk_type'] ?? 'walk';
    $transportInfo = trim($_POST['transport_info'] ?? '');
    
    // Аренда
    $rentPeriod = $_POST['rent_period'] ?? 'long';
    $deposit = (float)($_POST['deposit'] ?? 0) ?: null;
    $utilitiesIncluded = isset($_POST['utilities_included']) ? 1 : 0;
    $prepaymentMonths = (int)($_POST['prepayment_months'] ?? 1);
    $livingConditions = isset($_POST['living_conditions']) ? implode(',', $_POST['living_conditions']) : null;
    
    // Дополнительно
    $status = $_POST['status'] ?? 'available';
    $featured = isset($_POST['featured']) ? 1 : 0;
    $agentId = (int)($_POST['agent_id'] ?? 0) ?: null;
    
    // Удобства
    $postAmenities = $_POST['amenities'] ?? [];
    
    // Валидация
    if (empty($titleRu) && empty($street)) $errors[] = 'Введите название или адрес объекта';
    if (empty($propertyType)) $errors[] = 'Выберите тип недвижимости';
    if ($price <= 0) $errors[] = 'Введите корректную цену';
    if ($areaTotal <= 0) $errors[] = 'Введите общую площадь';
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Формируем title если не указан
            if (empty($titleRu)) {
                $roomsText = $bedrooms == 0 ? 'Студия' : $bedrooms . '-комн. кв.';
                $titleRu = $roomsText . ', ' . $areaTotal . ' м², ' . $street;
            }
            
            // Устанавливаем listing_type на основе category для обратной совместимости
            $listingType = $category === 'rent' ? 'rent' : 'sale';
            
            if ($isEdit) {
                // Обновление
                $stmt = $pdo->prepare("
                    UPDATE properties SET
                        category = ?, title = ?, title_ru = ?, description = ?, description_ru = ?,
                        property_type = ?, listing_type = ?, price = ?, district_id = ?,
                        street = ?, house_number = ?, location = ?,
                        area_sqft = ?, area_total = ?, area_living = ?, area_kitchen = ?,
                        bedrooms = ?, rooms_type = ?, bathrooms = ?, bathroom_type = ?,
                        floor_number = ?, total_floors = ?,
                        renovation = ?, furnished = ?, balcony = ?, balcony_count = ?, window_view = ?,
                        building_name = ?, house_type = ?, build_year = ?, ceiling_height = ?,
                        has_elevator = ?, has_garbage_chute = ?, is_new_building = ?,
                        metro_station = ?, metro_minutes = ?, metro_walk_type = ?, transport_info = ?,
                        rent_period = ?, deposit = ?, utilities_included = ?, prepayment_months = ?, living_conditions = ?,
                        status = ?, featured = ?, agent_id = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $category, $titleRu, $titleRu, $descriptionRu, $descriptionRu,
                    $propertyType, $listingType, $price, $districtId,
                    $street ?: null, $houseNumber ?: null, $location ?: null,
                    $areaTotal, $areaTotal, $areaLiving, $areaKitchen,
                    $bedrooms, $roomsType, $bathrooms, $bathroomType,
                    $floorNumber, $totalFloors,
                    $renovation, $furnished, $balcony, $balconyCount, $windowView,
                    $buildingName ?: null, $houseType, $buildYear, $ceilingHeight,
                    $hasElevator, $hasGarbageChute, $isNewBuilding,
                    $metroStation ?: null, $metroMinutes, $metroWalkType, $transportInfo ?: null,
                    $rentPeriod, $deposit, $utilitiesIncluded, $prepaymentMonths, $livingConditions,
                    $status, $featured, $agentId,
                    $propertyId
                ]);
            } else {
                // Создание
                $stmt = $pdo->prepare("
                    INSERT INTO properties (
                        category, title, title_ru, description, description_ru,
                        property_type, listing_type, price, district_id,
                        street, house_number, location,
                        area_sqft, area_total, area_living, area_kitchen,
                        bedrooms, rooms_type, bathrooms, bathroom_type,
                        floor_number, total_floors,
                        renovation, furnished, balcony, balcony_count, window_view,
                        building_name, house_type, build_year, ceiling_height,
                        has_elevator, has_garbage_chute, is_new_building,
                        metro_station, metro_minutes, metro_walk_type, transport_info,
                        rent_period, deposit, utilities_included, prepayment_months, living_conditions,
                        status, featured, agent_id,
                        created_at, updated_at
                    ) VALUES (
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?,
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?, ?, ?,
                        ?, ?, ?,
                        NOW(), NOW()
                    )
                ");
                $stmt->execute([
                    $category, $titleRu, $titleRu, $descriptionRu, $descriptionRu,
                    $propertyType, $listingType, $price, $districtId,
                    $street ?: null, $houseNumber ?: null, $location ?: null,
                    $areaTotal, $areaTotal, $areaLiving, $areaKitchen,
                    $bedrooms, $roomsType, $bathrooms, $bathroomType,
                    $floorNumber, $totalFloors,
                    $renovation, $furnished, $balcony, $balconyCount, $windowView,
                    $buildingName ?: null, $houseType, $buildYear, $ceilingHeight,
                    $hasElevator, $hasGarbageChute, $isNewBuilding,
                    $metroStation ?: null, $metroMinutes, $metroWalkType, $transportInfo ?: null,
                    $rentPeriod, $deposit, $utilitiesIncluded, $prepaymentMonths, $livingConditions,
                    $status, $featured, $agentId
                ]);
                $propertyId = $pdo->lastInsertId();
            }
            
            // Обработка удобств
            $pdo->prepare("DELETE FROM property_amenities WHERE property_id = ?")->execute([$propertyId]);
            if (!empty($postAmenities)) {
                $stmt = $pdo->prepare("INSERT INTO property_amenities (property_id, amenity_id) VALUES (?, ?)");
                foreach ($postAmenities as $amenityId) {
                    $stmt->execute([$propertyId, (int)$amenityId]);
                }
            }
            
            // Удаление старых изображений если указано
            if (!empty($_POST['delete_all_images'])) {
                $stmt = $pdo->prepare("SELECT image_url FROM property_images WHERE property_id = ?");
                $stmt->execute([$propertyId]);
                $oldImages = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                foreach ($oldImages as $oldImageUrl) {
                    $filePath = __DIR__ . '/..' . $oldImageUrl;
                    if (file_exists($filePath) && strpos($oldImageUrl, '/uploads/') === 0) {
                        unlink($filePath);
                    }
                }
                
                $pdo->prepare("DELETE FROM property_images WHERE property_id = ?")->execute([$propertyId]);
            }
            
            // Обработка изображений из внутренних папок
            if (!empty($_POST['existing_images'])) {
                // Получаем текущее количество изображений
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM property_images WHERE property_id = ?");
                $stmt->execute([$propertyId]);
                $sortOrder = $stmt->fetchColumn();
                
                $existingImages = $_POST['existing_images'];
                foreach ($existingImages as $imagePath) {
                    if ($sortOrder >= 10) break;
                    
                    // Проверяем, что путь безопасный и файл существует ТОЛЬКО во внутренних папках
                    $fullPath = __DIR__ . '/../' . $imagePath;
                    if (file_exists($fullPath) && strpos($imagePath, 'images/properties/') === 0) {
                        $stmt = $pdo->prepare("
                            INSERT INTO property_images (property_id, image_url, is_primary, sort_order)
                            VALUES (?, ?, ?, ?)
                        ");
                        $stmt->execute([$propertyId, '/' . $imagePath, $sortOrder === 0 ? 1 : 0, $sortOrder]);
                        $sortOrder++;
                    }
                }
            }
            
            $pdo->commit();
            
            header("Location: property-edit.php?id=$propertyId&success=1");
            exit;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = 'Ошибка при сохранении: ' . $e->getMessage();
        }
    }
} else {
    if ($isEdit && isset($_GET['success'])) {
        $message = 'Объект успешно сохранён';
    }
}

$pageTitle = $isEdit ? 'Редактирование объекта' : 'Новый объект';
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
                        <a href="properties.php?category=<?= escape($category) ?>">
                            <?= $category === 'rent' ? 'Аренда' : 'Продажа' ?>
                        </a> / ID: <?= $propertyId ?>
                    </div>
                    <?php endif; ?>
                </div>
                <a href="properties.php?category=<?= escape($category) ?>" class="btn btn--secondary">
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
            
            <form method="POST" class="property-form">
                <!-- Категория -->
                <div class="admin-card">
                    <div class="admin-card__header">
                        <h3><i class="fas fa-tag"></i> Категория</h3>
                    </div>
                    <div class="admin-card__body">
                        <div class="form-group">
                            <div class="radio-group">
                                <label class="radio-label">
                                    <input type="radio" name="category" value="sale" <?= $category === 'sale' ? 'checked' : '' ?>>
                                    <span><i class="fas fa-home"></i> Продажа</span>
                                </label>
                                <label class="radio-label">
                                    <input type="radio" name="category" value="rent" <?= $category === 'rent' ? 'checked' : '' ?>>
                                    <span><i class="fas fa-key"></i> Аренда</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="admin-form-grid">
                    <!-- Левая колонка -->
                    <div class="admin-form-column">
                        
                        <!-- Адрес и расположение -->
                        <div class="admin-card">
                            <div class="admin-card__header">
                                <h3><i class="fas fa-map-marker-alt"></i> Адрес</h3>
                            </div>
                            <div class="admin-card__body">
                                <div class="form-row">
                                    <div class="form-group form-group--2">
                                        <label class="form-label required">Улица</label>
                                        <input type="text" name="street" class="form-input" 
                                               value="<?= escape($property['street'] ?? '') ?>"
                                               placeholder="ул. Ленина">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Дом</label>
                                        <input type="text" name="house_number" class="form-input" 
                                               value="<?= escape($property['house_number'] ?? '') ?>"
                                               placeholder="12А">
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Район</label>
                                        <select name="district_id" class="form-select">
                                            <option value="">Выберите район</option>
                                            <?php foreach ($districts as $d): ?>
                                            <option value="<?= $d['id'] ?>" <?= ($property['district_id'] ?? 0) == $d['id'] ? 'selected' : '' ?>>
                                                <?= escape($d['name']) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Название ЖК/здания</label>
                                        <input type="text" name="building_name" class="form-input" 
                                               value="<?= escape($property['building_name'] ?? '') ?>"
                                               placeholder="ЖК Светлый">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Полный адрес (для поиска)</label>
                                    <input type="text" name="location" class="form-input" 
                                           value="<?= escape($property['location'] ?? '') ?>"
                                           placeholder="Екатеринбург, Ленинский район, ул. Ленина, 12">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Основные параметры -->
                        <div class="admin-card">
                            <div class="admin-card__header">
                                <h3><i class="fas fa-home"></i> Параметры квартиры</h3>
                            </div>
                            <div class="admin-card__body">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label required">Тип</label>
                                        <select name="property_type" class="form-select" required>
                                            <option value="">Выберите</option>
                                            <option value="apartment" <?= ($property['property_type'] ?? '') === 'apartment' ? 'selected' : '' ?>>Квартира</option>
                                            <option value="studio" <?= ($property['property_type'] ?? '') === 'studio' ? 'selected' : '' ?>>Студия</option>
                                            <option value="room" <?= ($property['property_type'] ?? '') === 'room' ? 'selected' : '' ?>>Комната</option>
                                            <option value="house" <?= ($property['property_type'] ?? '') === 'house' ? 'selected' : '' ?>>Дом</option>
                                            <option value="townhouse" <?= ($property['property_type'] ?? '') === 'townhouse' ? 'selected' : '' ?>>Таунхаус</option>
                                            <option value="cottage" <?= ($property['property_type'] ?? '') === 'cottage' ? 'selected' : '' ?>>Коттедж</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label required">Комнат</label>
                                        <select name="bedrooms" class="form-select" required>
                                            <option value="0" <?= ($property['bedrooms'] ?? 0) == 0 ? 'selected' : '' ?>>Студия</option>
                                            <option value="1" <?= ($property['bedrooms'] ?? 0) == 1 ? 'selected' : '' ?>>1</option>
                                            <option value="2" <?= ($property['bedrooms'] ?? 0) == 2 ? 'selected' : '' ?>>2</option>
                                            <option value="3" <?= ($property['bedrooms'] ?? 0) == 3 ? 'selected' : '' ?>>3</option>
                                            <option value="4" <?= ($property['bedrooms'] ?? 0) == 4 ? 'selected' : '' ?>>4</option>
                                            <option value="5" <?= ($property['bedrooms'] ?? 0) == 5 ? 'selected' : '' ?>>5+</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Планировка</label>
                                        <select name="rooms_type" class="form-select">
                                            <option value="">—</option>
                                            <option value="isolated" <?= ($property['rooms_type'] ?? '') === 'isolated' ? 'selected' : '' ?>>Изолированные</option>
                                            <option value="adjacent" <?= ($property['rooms_type'] ?? '') === 'adjacent' ? 'selected' : '' ?>>Смежные</option>
                                            <option value="mixed" <?= ($property['rooms_type'] ?? '') === 'mixed' ? 'selected' : '' ?>>Смешанные</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label required">Общая, м²</label>
                                        <input type="number" name="area_total" class="form-input" step="0.1"
                                               value="<?= $property['area_total'] ?? $property['area_sqft'] ?? '' ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Жилая, м²</label>
                                        <input type="number" name="area_living" class="form-input" step="0.1"
                                               value="<?= $property['area_living'] ?? '' ?>">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Кухня, м²</label>
                                        <input type="number" name="area_kitchen" class="form-input" step="0.1"
                                               value="<?= $property['area_kitchen'] ?? '' ?>">
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Этаж</label>
                                        <input type="number" name="floor_number" class="form-input" min="0"
                                               value="<?= $property['floor_number'] ?? '' ?>">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Этажей в доме</label>
                                        <input type="number" name="total_floors" class="form-input" min="1"
                                               value="<?= $property['total_floors'] ?? '' ?>">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Санузлов</label>
                                        <input type="number" name="bathrooms" class="form-input" min="0"
                                               value="<?= $property['bathrooms'] ?? 1 ?>">
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Санузел</label>
                                        <select name="bathroom_type" class="form-select">
                                            <option value="">—</option>
                                            <option value="combined" <?= ($property['bathroom_type'] ?? '') === 'combined' ? 'selected' : '' ?>>Совмещённый</option>
                                            <option value="separate" <?= ($property['bathroom_type'] ?? '') === 'separate' ? 'selected' : '' ?>>Раздельный</option>
                                            <option value="multiple" <?= ($property['bathroom_type'] ?? '') === 'multiple' ? 'selected' : '' ?>>2 и более</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Балкон/Лоджия</label>
                                        <select name="balcony" class="form-select">
                                            <option value="">—</option>
                                            <option value="balcony" <?= ($property['balcony'] ?? '') === 'balcony' ? 'selected' : '' ?>>Балкон</option>
                                            <option value="loggia" <?= ($property['balcony'] ?? '') === 'loggia' ? 'selected' : '' ?>>Лоджия</option>
                                            <option value="both" <?= ($property['balcony'] ?? '') === 'both' ? 'selected' : '' ?>>Балкон + Лоджия</option>
                                            <option value="none" <?= ($property['balcony'] ?? '') === 'none' ? 'selected' : '' ?>>Нет</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Кол-во</label>
                                        <input type="number" name="balcony_count" class="form-input" min="0"
                                               value="<?= $property['balcony_count'] ?? 0 ?>">
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Вид из окон</label>
                                        <select name="window_view" class="form-select">
                                            <option value="">—</option>
                                            <option value="yard" <?= ($property['window_view'] ?? '') === 'yard' ? 'selected' : '' ?>>Во двор</option>
                                            <option value="street" <?= ($property['window_view'] ?? '') === 'street' ? 'selected' : '' ?>>На улицу</option>
                                            <option value="park" <?= ($property['window_view'] ?? '') === 'park' ? 'selected' : '' ?>>На парк</option>
                                            <option value="river" <?= ($property['window_view'] ?? '') === 'river' ? 'selected' : '' ?>>На реку</option>
                                            <option value="city" <?= ($property['window_view'] ?? '') === 'city' ? 'selected' : '' ?>>Панорамный</option>
                                            <option value="both" <?= ($property['window_view'] ?? '') === 'both' ? 'selected' : '' ?>>Двусторонний</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Ремонт</label>
                                        <select name="renovation" class="form-select">
                                            <option value="">—</option>
                                            <option value="designer" <?= ($property['renovation'] ?? '') === 'designer' ? 'selected' : '' ?>>Дизайнерский</option>
                                            <option value="euro" <?= ($property['renovation'] ?? '') === 'euro' ? 'selected' : '' ?>>Евроремонт</option>
                                            <option value="cosmetic" <?= ($property['renovation'] ?? '') === 'cosmetic' ? 'selected' : '' ?>>Косметический</option>
                                            <option value="needs-repair" <?= ($property['renovation'] ?? '') === 'needs-repair' ? 'selected' : '' ?>>Требует ремонта</option>
                                            <option value="turnkey" <?= ($property['renovation'] ?? '') === 'turnkey' ? 'selected' : '' ?>>Под ключ (новостр.)</option>
                                            <option value="pre-finish" <?= ($property['renovation'] ?? '') === 'pre-finish' ? 'selected' : '' ?>>Предчистовая</option>
                                            <option value="rough-finish" <?= ($property['renovation'] ?? '') === 'rough-finish' ? 'selected' : '' ?>>Черновая</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Мебель</label>
                                        <select name="furnished" class="form-select">
                                            <option value="unfurnished" <?= ($property['furnished'] ?? '') === 'unfurnished' ? 'selected' : '' ?>>Без мебели</option>
                                            <option value="semi-furnished" <?= ($property['furnished'] ?? '') === 'semi-furnished' ? 'selected' : '' ?>>Частично</option>
                                            <option value="furnished" <?= ($property['furnished'] ?? '') === 'furnished' ? 'selected' : '' ?>>С мебелью</option>
                                        </select>
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
                                            <option value="panel" <?= ($property['house_type'] ?? '') === 'panel' ? 'selected' : '' ?>>Панельный</option>
                                            <option value="brick" <?= ($property['house_type'] ?? '') === 'brick' ? 'selected' : '' ?>>Кирпичный</option>
                                            <option value="monolith" <?= ($property['house_type'] ?? '') === 'monolith' ? 'selected' : '' ?>>Монолитный</option>
                                            <option value="monolith-brick" <?= ($property['house_type'] ?? '') === 'monolith-brick' ? 'selected' : '' ?>>Монолит-кирпич</option>
                                            <option value="block" <?= ($property['house_type'] ?? '') === 'block' ? 'selected' : '' ?>>Блочный</option>
                                            <option value="wood" <?= ($property['house_type'] ?? '') === 'wood' ? 'selected' : '' ?>>Деревянный</option>
                                            <option value="stalin" <?= ($property['house_type'] ?? '') === 'stalin' ? 'selected' : '' ?>>Сталинка</option>
                                            <option value="khrushchev" <?= ($property['house_type'] ?? '') === 'khrushchev' ? 'selected' : '' ?>>Хрущёвка</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Год постройки</label>
                                        <input type="number" name="build_year" class="form-input" min="1900" max="2030"
                                               value="<?= $property['build_year'] ?? '' ?>">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Высота потолков, м</label>
                                        <input type="number" name="ceiling_height" class="form-input" step="0.01" min="2" max="5"
                                               value="<?= $property['ceiling_height'] ?? '' ?>" placeholder="2.70">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <div class="checkbox-group">
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="has_elevator" value="1" <?= ($property['has_elevator'] ?? 0) ? 'checked' : '' ?>>
                                            <span>Лифт</span>
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="has_garbage_chute" value="1" <?= ($property['has_garbage_chute'] ?? 0) ? 'checked' : '' ?>>
                                            <span>Мусоропровод</span>
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="is_new_building" value="1" <?= ($property['is_new_building'] ?? 0) ? 'checked' : '' ?>>
                                            <span>Новостройка</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Описание -->
                        <div class="admin-card">
                            <div class="admin-card__header">
                                <h3><i class="fas fa-align-left"></i> Описание</h3>
                            </div>
                            <div class="admin-card__body">
                                <div class="form-group">
                                    <label class="form-label">Заголовок (если пусто — сгенерируется автоматически)</label>
                                    <input type="text" name="title_ru" class="form-input" 
                                           value="<?= escape($property['title_ru'] ?? '') ?>"
                                           placeholder="2-комн. кв., 65 м², ул. Ленина">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Описание</label>
                                    <textarea name="description_ru" class="form-textarea" rows="6" 
                                              placeholder="Подробное описание объекта..."><?= escape($property['description_ru'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                    
                    <!-- Правая колонка -->
                    <div class="admin-form-column">
                        
                        <!-- Цена -->
                        <div class="admin-card">
                            <div class="admin-card__header">
                                <h3><i class="fas fa-ruble-sign"></i> Цена</h3>
                            </div>
                            <div class="admin-card__body">
                                <div class="form-group">
                                    <label class="form-label required">Цена, ₽</label>
                                    <input type="number" name="price" class="form-input form-input--lg" 
                                           value="<?= $property['price'] ?? '' ?>" required min="0" step="1000"
                                           placeholder="5 500 000">
                                    <span class="form-help">
                                        <?= $category === 'rent' ? 'Указывайте цену за месяц' : 'Полная стоимость объекта' ?>
                                    </span>
                                </div>
                                
                                <!-- Поля для аренды -->
                                <div class="rent-fields" style="<?= $category === 'rent' ? '' : 'display:none' ?>">
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label class="form-label">Залог, ₽</label>
                                            <input type="number" name="deposit" class="form-input" 
                                                   value="<?= $property['deposit'] ?? '' ?>">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Предоплата, мес</label>
                                            <select name="prepayment_months" class="form-select">
                                                <option value="1" <?= ($property['prepayment_months'] ?? 1) == 1 ? 'selected' : '' ?>>1 месяц</option>
                                                <option value="2" <?= ($property['prepayment_months'] ?? 1) == 2 ? 'selected' : '' ?>>2 месяца</option>
                                                <option value="3" <?= ($property['prepayment_months'] ?? 1) == 3 ? 'selected' : '' ?>>3 месяца</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label class="form-label">Срок аренды</label>
                                            <select name="rent_period" class="form-select">
                                                <option value="long" <?= ($property['rent_period'] ?? 'long') === 'long' ? 'selected' : '' ?>>Длительная</option>
                                                <option value="short" <?= ($property['rent_period'] ?? '') === 'short' ? 'selected' : '' ?>>Краткосрочная</option>
                                                <option value="daily" <?= ($property['rent_period'] ?? '') === 'daily' ? 'selected' : '' ?>>Посуточная</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label class="checkbox-label" style="margin-top: 28px;">
                                                <input type="checkbox" name="utilities_included" value="1" <?= ($property['utilities_included'] ?? 0) ? 'checked' : '' ?>>
                                                <span>КУ включены</span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Условия проживания</label>
                                        <div class="checkbox-group">
                                            <?php 
                                            $conditions = explode(',', $property['living_conditions'] ?? '');
                                            ?>
                                            <label class="checkbox-label">
                                                <input type="checkbox" name="living_conditions[]" value="no_animals" <?= in_array('no_animals', $conditions) ? 'checked' : '' ?>>
                                                <span>Без животных</span>
                                            </label>
                                            <label class="checkbox-label">
                                                <input type="checkbox" name="living_conditions[]" value="no_children" <?= in_array('no_children', $conditions) ? 'checked' : '' ?>>
                                                <span>Без детей</span>
                                            </label>
                                            <label class="checkbox-label">
                                                <input type="checkbox" name="living_conditions[]" value="families_only" <?= in_array('families_only', $conditions) ? 'checked' : '' ?>>
                                                <span>Только семьям</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
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
                                        <label class="form-label">Станция метро</label>
                                        <select name="metro_station" class="form-select">
                                            <option value="">—</option>
                                            <option value="Ботаническая" <?= ($property['metro_station'] ?? '') === 'Ботаническая' ? 'selected' : '' ?>>Ботаническая</option>
                                            <option value="Чкаловская" <?= ($property['metro_station'] ?? '') === 'Чкаловская' ? 'selected' : '' ?>>Чкаловская</option>
                                            <option value="Геологическая" <?= ($property['metro_station'] ?? '') === 'Геологическая' ? 'selected' : '' ?>>Геологическая</option>
                                            <option value="Площадь 1905 года" <?= ($property['metro_station'] ?? '') === 'Площадь 1905 года' ? 'selected' : '' ?>>Площадь 1905 года</option>
                                            <option value="Динамо" <?= ($property['metro_station'] ?? '') === 'Динамо' ? 'selected' : '' ?>>Динамо</option>
                                            <option value="Уральская" <?= ($property['metro_station'] ?? '') === 'Уральская' ? 'selected' : '' ?>>Уральская</option>
                                            <option value="Машиностроителей" <?= ($property['metro_station'] ?? '') === 'Машиностроителей' ? 'selected' : '' ?>>Машиностроителей</option>
                                            <option value="Уралмаш" <?= ($property['metro_station'] ?? '') === 'Уралмаш' ? 'selected' : '' ?>>Уралмаш</option>
                                            <option value="Проспект Космонавтов" <?= ($property['metro_station'] ?? '') === 'Проспект Космонавтов' ? 'selected' : '' ?>>Проспект Космонавтов</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Минут</label>
                                        <input type="number" name="metro_minutes" class="form-input" min="1" max="60"
                                               value="<?= $property['metro_minutes'] ?? '' ?>">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="radio-group">
                                        <label class="radio-label">
                                            <input type="radio" name="metro_walk_type" value="walk" <?= ($property['metro_walk_type'] ?? 'walk') === 'walk' ? 'checked' : '' ?>>
                                            <span>Пешком</span>
                                        </label>
                                        <label class="radio-label">
                                            <input type="radio" name="metro_walk_type" value="transport" <?= ($property['metro_walk_type'] ?? '') === 'transport' ? 'checked' : '' ?>>
                                            <span>На транспорте</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Доп. информация о транспорте</label>
                                    <textarea name="transport_info" class="form-textarea" rows="2" 
                                              placeholder="Рядом остановки трамвая, автобуса..."><?= escape($property['transport_info'] ?? '') ?></textarea>
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
                                        <img src="<?= imgSrc($img['image_url']) ?>" alt="">
                                        <?php if ($img['is_primary']): ?>
                                        <span class="image-preview__badge">Главное</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="form-group" style="margin-top: var(--space-4);">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="delete_all_images" value="1">
                                        Удалить все текущие изображения
                                    </label>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Выбор изображений из внутренних папок -->
                                <div class="form-group" style="margin-top: var(--space-4);">
                                    <label class="form-label">Выбрать изображения (до 10 шт.)</label>
                                    <span class="form-help">Изображения берутся только из внутренней папки images/properties/ для безопасности</span>
                                    <?php
                                    $stockImagesDir = __DIR__ . '/../images/properties/';
                                    $stockImages = [];
                                    if (is_dir($stockImagesDir)) {
                                        $files = scandir($stockImagesDir);
                                        foreach ($files as $file) {
                                            if ($file !== '.' && $file !== '..' && preg_match('/\.(jpg|jpeg|jfif|png|webp)$/i', $file)) {
                                                $stockImages[] = $file;
                                            }
                                        }
                                    }
                                    ?>
                                    <?php if (!empty($stockImages)): ?>
                                    <div class="stock-images-grid">
                                        <?php foreach ($stockImages as $img): ?>
                                        <label class="stock-image-item">
                                            <input type="checkbox" name="existing_images[]" value="images/properties/<?= escape($img) ?>">
                                            <div class="stock-image-preview">
                                                <img src="/images/properties/<?= escape($img) ?>" alt="<?= escape($img) ?>">
                                                <span class="stock-image-name"><?= escape($img) ?></span>
                                            </div>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <p class="form-help" style="margin-top: var(--space-2);">
                                        <i class="fas fa-info-circle"></i> Чтобы добавить новые фото в библиотеку, положите их в папку <code>images/properties/</code> на сервере
                                    </p>
                                    <?php else: ?>
                                    <div class="alert alert--warning">
                                        <i class="fas fa-exclamation-triangle"></i> Нет доступных изображений в папке images/properties/
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Удобства -->
                        <div class="admin-card">
                            <div class="admin-card__header">
                                <h3><i class="fas fa-check-circle"></i> Удобства</h3>
                            </div>
                            <div class="admin-card__body">
                                <div class="amenities-grid">
                                    <?php foreach ($amenitiesList as $a): ?>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="amenities[]" value="<?= $a['id'] ?>"
                                               <?= in_array($a['id'], $selectedAmenities) ? 'checked' : '' ?>>
                                        <i class="fas <?= $a['icon'] ?>"></i>
                                        <?= escape($a['name_ru'] ?? $a['name']) ?>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Статус -->
                        <div class="admin-card">
                            <div class="admin-card__header">
                                <h3><i class="fas fa-cog"></i> Настройки</h3>
                            </div>
                            <div class="admin-card__body">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Статус</label>
                                        <select name="status" class="form-select">
                                            <option value="available" <?= ($property['status'] ?? 'available') === 'available' ? 'selected' : '' ?>>Активен</option>
                                            <option value="pending" <?= ($property['status'] ?? '') === 'pending' ? 'selected' : '' ?>>В процессе</option>
                                            <option value="sold" <?= ($property['status'] ?? '') === 'sold' ? 'selected' : '' ?>>Продан</option>
                                            <option value="rented" <?= ($property['status'] ?? '') === 'rented' ? 'selected' : '' ?>>Сдан</option>
                                            <option value="off-market" <?= ($property['status'] ?? '') === 'off-market' ? 'selected' : '' ?>>Снят</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Агент</label>
                                        <select name="agent_id" class="form-select">
                                            <option value="">Не назначен</option>
                                            <?php foreach ($agents as $a): ?>
                                            <option value="<?= $a['id'] ?>" <?= ($property['agent_id'] ?? 0) == $a['id'] ? 'selected' : '' ?>>
                                                <?= escape($a['first_name'] . ' ' . $a['last_name']) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="featured" value="1" <?= ($property['featured'] ?? 0) ? 'checked' : '' ?>>
                                        <span><i class="fas fa-star"></i> Рекомендуемый объект</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Кнопка сохранения -->
                        <div class="admin-card">
                            <div class="admin-card__body">
                                <button type="submit" class="btn btn--primary btn--full btn--lg">
                                    <i class="fas fa-save"></i> <?= $isEdit ? 'Сохранить изменения' : 'Создать объект' ?>
                                </button>
                            </div>
                        </div>
                        
                    </div>
                </div>
            </form>
        </main>
    </div>
    
    <script>
        // Показ/скрытие полей аренды при смене категории
        document.querySelectorAll('input[name="category"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const rentFields = document.querySelector('.rent-fields');
                if (this.value === 'rent') {
                    rentFields.style.display = 'block';
                } else {
                    rentFields.style.display = 'none';
                }
            });
        });
    </script>
    
    <style>
        .property-form {
            max-width: 1400px;
        }
        .form-input--lg {
            font-size: var(--text-xl);
            font-weight: var(--font-bold);
        }
        .radio-group {
            display: flex;
            gap: var(--space-4);
            flex-wrap: wrap;
        }
        .radio-label {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-3) var(--space-4);
            background-color: var(--color-light-gray);
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all var(--transition-fast);
        }
        .radio-label:hover {
            background-color: var(--color-border);
        }
        .radio-label input:checked + span {
            color: var(--color-accent);
            font-weight: var(--font-medium);
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
            width: 20px;
        }
        .amenities-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: var(--space-2);
        }
        .image-preview-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: var(--space-2);
            margin-top: var(--space-4);
        }
        .image-preview {
            position: relative;
            aspect-ratio: 4/3;
            border-radius: var(--radius-sm);
            overflow: hidden;
        }
        .image-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .image-preview__badge {
            position: absolute;
            top: 4px;
            left: 4px;
            background: var(--color-accent);
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: var(--radius-sm);
        }
        .form-group--2 {
            flex: 2;
        }
        .rent-fields {
            margin-top: var(--space-4);
            padding-top: var(--space-4);
            border-top: 1px solid var(--color-border);
        }
        .stock-images-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: var(--space-3);
            max-height: 400px;
            overflow-y: auto;
            padding: var(--space-2);
            background: var(--color-light-gray);
            border-radius: var(--radius-md);
        }
        .stock-image-item {
            cursor: pointer;
            position: relative;
        }
        .stock-image-item input[type="checkbox"] {
            position: absolute;
            top: 4px;
            right: 4px;
            z-index: 2;
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        .stock-image-preview {
            position: relative;
            border-radius: var(--radius-sm);
            overflow: hidden;
            border: 3px solid transparent;
            transition: all var(--transition-fast);
            background: white;
        }
        .stock-image-item input[type="checkbox"]:checked ~ .stock-image-preview {
            border-color: var(--color-accent);
            box-shadow: 0 0 0 2px var(--color-accent);
        }
        .stock-image-preview img {
            width: 100%;
            height: 100px;
            object-fit: cover;
            display: block;
        }
        .stock-image-name {
            display: block;
            font-size: 10px;
            padding: 4px;
            background: white;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: var(--color-text-secondary);
        }
        .stock-image-item:hover .stock-image-preview {
            transform: scale(1.05);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
    </style>
</body>
</html>
