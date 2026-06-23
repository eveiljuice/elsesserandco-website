<?php
/**
 * Edit Property - Agent CRM
 * Редактирование объекта агентом (Екатеринбург)
 */

require_once __DIR__ . '/../includes/config/database.php';
require_once __DIR__ . '/../includes/auth/check_auth.php';

requireAgent();

$pdo = getDBConnection();
$userId = getCurrentUserId();
$errors = [];
$success = false;

$propertyId = intval($_GET['id'] ?? 0);
if (!$propertyId) {
    header("Location: dashboard.php");
    exit;
}

// Проверяем, что объект принадлежит этому агенту (или это админ)
$stmt = $pdo->prepare("SELECT * FROM properties WHERE id = ?");
$stmt->execute([$propertyId]);
$property = $stmt->fetch();

if (!$property) {
    header("Location: dashboard.php");
    exit;
}

// Агент может редактировать только свои объекты
if (!isAdmin() && $property['agent_id'] != $userId) {
    header("HTTP/1.1 403 Forbidden");
    die("Нет доступа к этому объекту");
}

// Получаем изображения
$stmt = $pdo->prepare("SELECT * FROM property_images WHERE property_id = ? ORDER BY sort_order");
$stmt->execute([$propertyId]);
$images = $stmt->fetchAll();

// Получаем удобства объекта
$stmt = $pdo->prepare("SELECT amenity_id FROM property_amenities WHERE property_id = ?");
$stmt->execute([$propertyId]);
$propertyAmenities = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Все удобства
$stmt = $pdo->query("SELECT * FROM amenities ORDER BY name_ru, name");
$amenities = $stmt->fetchAll();

// Получаем районы Екатеринбурга
$districts = [];
$stmt = $pdo->query("SELECT * FROM ekb_districts ORDER BY sort_order");
if ($stmt) {
    $districts = $stmt->fetchAll();
}

// Станции метро Екатеринбурга
$metroStations = [
    'Проспект Космонавтов', 'Уралмаш', 'Машиностроителей', 'Уральская',
    'Динамо', 'Площадь 1905 года', 'Геологическая', 'Чкаловская', 'Ботаническая'
];

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Ошибка безопасности. Обновите страницу.';
    }
    
    // Основные поля
    $titleRu = trim($_POST['title_ru'] ?? '');
    $descriptionRu = trim($_POST['description_ru'] ?? '');
    $propertyType = $_POST['property_type'] ?? '';
    $listingType = $_POST['listing_type'] ?? '';
    $category = $listingType;
    $price = floatval($_POST['price'] ?? 0);
    $status = $_POST['status'] ?? 'available';
    
    // Адрес
    $districtId = !empty($_POST['district_id']) ? intval($_POST['district_id']) : null;
    $street = trim($_POST['street'] ?? '');
    $houseNumber = trim($_POST['house_number'] ?? '');
    $location = trim($_POST['location'] ?? '');
    
    // Площади
    $areaTotal = floatval($_POST['area_total'] ?? 0);
    $areaLiving = !empty($_POST['area_living']) ? floatval($_POST['area_living']) : null;
    $areaKitchen = !empty($_POST['area_kitchen']) ? floatval($_POST['area_kitchen']) : null;
    
    // Комнаты
    $bedrooms = intval($_POST['bedrooms'] ?? 0);
    $roomsType = ($_POST['rooms_type'] ?? null) ?: null;
    $bathrooms = intval($_POST['bathrooms'] ?? 1);
    $bathroomType = ($_POST['bathroom_type'] ?? null) ?: null;
    
    // Этажность
    $floorNumber = !empty($_POST['floor_number']) ? intval($_POST['floor_number']) : null;
    $totalFloors = !empty($_POST['total_floors']) ? intval($_POST['total_floors']) : null;
    
    // Состояние квартиры
    $renovation = ($_POST['renovation'] ?? null) ?: null;
    $balcony = ($_POST['balcony'] ?? null) ?: null;
    $balconyCount = intval($_POST['balcony_count'] ?? 0);
    $windowView = ($_POST['window_view'] ?? null) ?: null;
    $furnished = $_POST['furnished'] ?? 'unfurnished';
    
    // Характеристики дома
    $houseType = ($_POST['house_type'] ?? null) ?: null;
    $buildYear = !empty($_POST['build_year']) ? intval($_POST['build_year']) : null;
    $ceilingHeight = !empty($_POST['ceiling_height']) ? floatval($_POST['ceiling_height']) : null;
    $hasElevator = isset($_POST['has_elevator']) ? 1 : 0;
    $hasGarbageChute = isset($_POST['has_garbage_chute']) ? 1 : 0;
    $isNewBuilding = isset($_POST['is_new_building']) ? 1 : 0;
    $buildingName = trim($_POST['building_name'] ?? '');
    
    // Транспорт
    $metroStation = ($_POST['metro_station'] ?? null) ?: null;
    $metroMinutes = !empty($_POST['metro_minutes']) ? intval($_POST['metro_minutes']) : null;
    $metroWalkType = $_POST['metro_walk_type'] ?? 'walk';
    $transportInfo = trim($_POST['transport_info'] ?? '');
    
    // Для аренды
    $rentDepositMonths = intval($_POST['rent_deposit_months'] ?? 1);
    $rentCommissionType = ($_POST['rent_commission_type'] ?? null) ?: null;
    $minRentPeriod = !empty($_POST['min_rent_period']) ? intval($_POST['min_rent_period']) : null;
    $utilitiesIncluded = isset($_POST['utilities_included']) ? 1 : 0;
    $petsAllowed = isset($_POST['pets_allowed']) ? 1 : 0;
    $childrenAllowed = isset($_POST['children_allowed']) ? 1 : 1;
    
    $selectedAmenities = $_POST['amenities'] ?? [];
    
    // Валидация
    if (empty($titleRu)) $errors[] = 'Введите название объекта';
    if (empty($propertyType)) $errors[] = 'Выберите тип недвижимости';
    if ($price <= 0) $errors[] = 'Введите корректную цену';
    if ($areaTotal <= 0) $errors[] = 'Введите общую площадь';
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Формируем полный адрес
            $fullLocation = $street;
            if ($houseNumber) $fullLocation .= ', ' . $houseNumber;
            if (empty($fullLocation)) $fullLocation = $location;
            
            $stmt = $pdo->prepare("
                UPDATE properties SET
                    title = ?, title_ru = ?, description = ?, description_ru = ?,
                    property_type = ?, listing_type = ?, category = ?, price = ?, status = ?,
                    location = ?, district_id = ?, street = ?, house_number = ?, building_name = ?,
                    area_sqft = ?, area_total = ?, area_living = ?, area_kitchen = ?,
                    bedrooms = ?, rooms_type = ?, bathrooms = ?, bathroom_type = ?,
                    floor_number = ?, total_floors = ?, furnished = ?, renovation = ?,
                    balcony = ?, balcony_count = ?, window_view = ?,
                    house_type = ?, build_year = ?, ceiling_height = ?, has_elevator = ?, has_garbage_chute = ?, is_new_building = ?,
                    metro_station = ?, metro_minutes = ?, metro_walk_type = ?, transport_info = ?,
                    rent_deposit_months = ?, rent_commission_type = ?, min_rent_period = ?, utilities_included = ?, pets_allowed = ?, children_allowed = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $titleRu, $titleRu, $descriptionRu ?: null, $descriptionRu ?: null,
                $propertyType, $listingType, $category, $price, $status,
                $fullLocation, $districtId, $street ?: null, $houseNumber ?: null, $buildingName ?: null,
                $areaTotal, $areaTotal, $areaLiving, $areaKitchen,
                $bedrooms, $roomsType, $bathrooms, $bathroomType,
                $floorNumber, $totalFloors, $furnished, $renovation,
                $balcony, $balconyCount, $windowView,
                $houseType, $buildYear, $ceilingHeight, $hasElevator, $hasGarbageChute, $isNewBuilding,
                $metroStation, $metroMinutes, $metroWalkType, $transportInfo ?: null,
                $rentDepositMonths, $rentCommissionType, $minRentPeriod, $utilitiesIncluded, $petsAllowed, $childrenAllowed,
                $propertyId
            ]);
            
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
                
                $stmt = $pdo->prepare("DELETE FROM property_images WHERE property_id = ?");
                $stmt->execute([$propertyId]);
            }
            
            // Обработка новых изображений
            if (!empty($_FILES['property_images']['name'][0])) {
                $uploadDir = __DIR__ . '/../uploads/properties/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                // Получаем текущее количество изображений
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM property_images WHERE property_id = ?");
                $stmt->execute([$propertyId]);
                $currentCount = $stmt->fetchColumn();
                $sortOrder = $currentCount;
                
                $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
                
                foreach ($_FILES['property_images']['tmp_name'] as $key => $tmpName) {
                    if (empty($tmpName) || $sortOrder >= 10) continue;
                    
                    $fileType = $_FILES['property_images']['type'][$key];
                    if (!in_array($fileType, $allowedTypes)) continue;
                    
                    $extension = pathinfo($_FILES['property_images']['name'][$key], PATHINFO_EXTENSION);
                    $fileName = 'property_' . $propertyId . '_' . uniqid() . '_' . time() . '.' . $extension;
                    $filePath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($tmpName, $filePath)) {
                        $imageUrl = '/uploads/properties/' . $fileName;
                        $stmt = $pdo->prepare("
                            INSERT INTO property_images (property_id, image_url, is_primary, sort_order)
                            VALUES (?, ?, ?, ?)
                        ");
                        $stmt->execute([$propertyId, $imageUrl, $sortOrder === 0 ? 1 : 0, $sortOrder]);
                        $sortOrder++;
                    }
                }
            }
            
            // Обновление удобств
            $stmt = $pdo->prepare("DELETE FROM property_amenities WHERE property_id = ?");
            $stmt->execute([$propertyId]);
            
            if (!empty($selectedAmenities)) {
                $stmt = $pdo->prepare("INSERT INTO property_amenities (property_id, amenity_id) VALUES (?, ?)");
                foreach ($selectedAmenities as $amenityId) {
                    $stmt->execute([$propertyId, intval($amenityId)]);
                }
            }
            
            $pdo->commit();
            $success = true;
            
            // Обновляем данные для отображения
            $stmt = $pdo->prepare("SELECT * FROM properties WHERE id = ?");
            $stmt->execute([$propertyId]);
            $property = $stmt->fetch();
            
            $stmt = $pdo->prepare("SELECT * FROM property_images WHERE property_id = ? ORDER BY sort_order");
            $stmt->execute([$propertyId]);
            $images = $stmt->fetchAll();
            
            $stmt = $pdo->prepare("SELECT amenity_id FROM property_amenities WHERE property_id = ?");
            $stmt->execute([$propertyId]);
            $propertyAmenities = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = 'Ошибка сохранения: ' . $e->getMessage();
        }
    }
}

// Собираем URL изображений для textarea
$imageUrlsText = implode("\n", array_column($images, 'image_url'));

$pageTitle = 'Редактирование: ' . $property['title'];
$created = isset($_GET['created']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($pageTitle) ?> | Elsesser & Co.</title>
    
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
                    <h1 class="admin-title">Редактирование объекта</h1>
                    <div class="admin-breadcrumb">
                        <a href="dashboard.php">Dashboard</a> / <?= escape($property['title']) ?>
                    </div>
                </div>
                <a href="../property.php?id=<?= $propertyId ?>" class="btn btn--secondary" target="_blank">
                    <i class="fas fa-eye"></i> Просмотреть
                </a>
            </div>
            
            <?php if ($created): ?>
            <div class="alert alert--success">
                <i class="fas fa-check-circle"></i> Объект успешно создан!
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="alert alert--success">
                <i class="fas fa-check-circle"></i> Изменения сохранены
            </div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
            <div class="alert alert--error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                    <li><?= escape($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data" class="admin-card">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                
                <!-- Основная информация -->
                <div class="admin-card__header">
                    <h2 class="admin-card__title">
                        <i class="fas fa-info-circle"></i> Основная информация
                    </h2>
                </div>
                <div class="admin-card__body">
                    <div class="admin-form-grid">
                        <div class="admin-form-column">
                            <div class="form-group">
                                <label class="form-label required">Название объекта</label>
                                <input type="text" name="title_ru" class="form-input" 
                                       value="<?= escape($property['title_ru'] ?? $property['title']) ?>" required
                                       placeholder="Например: 2-комн. квартира, 56 м²">
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label required">Тип недвижимости</label>
                                    <select name="property_type" class="form-select" required>
                                        <option value="">Выберите тип</option>
                                        <option value="apartment" <?= $property['property_type'] === 'apartment' ? 'selected' : '' ?>>Квартира</option>
                                        <option value="studio" <?= $property['property_type'] === 'studio' ? 'selected' : '' ?>>Студия</option>
                                        <option value="room" <?= $property['property_type'] === 'room' ? 'selected' : '' ?>>Комната</option>
                                        <option value="house" <?= $property['property_type'] === 'house' ? 'selected' : '' ?>>Дом</option>
                                        <option value="townhouse" <?= $property['property_type'] === 'townhouse' ? 'selected' : '' ?>>Таунхаус</option>
                                        <option value="cottage" <?= $property['property_type'] === 'cottage' ? 'selected' : '' ?>>Коттедж</option>
                                        <option value="commercial" <?= $property['property_type'] === 'commercial' ? 'selected' : '' ?>>Коммерческая</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label required">Тип сделки</label>
                                    <select name="listing_type" class="form-select" required>
                                        <option value="sale" <?= $property['listing_type'] === 'sale' ? 'selected' : '' ?>>Продажа</option>
                                        <option value="rent" <?= $property['listing_type'] === 'rent' ? 'selected' : '' ?>>Аренда</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label required">Цена (₽)</label>
                                    <input type="number" name="price" class="form-input" 
                                           value="<?= $property['price'] ?>" required min="1" step="1000">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Статус</label>
                                    <select name="status" class="form-select">
                                        <option value="available" <?= $property['status'] === 'available' ? 'selected' : '' ?>>Активен</option>
                                        <option value="pending" <?= $property['status'] === 'pending' ? 'selected' : '' ?>>Ожидание</option>
                                        <option value="sold" <?= $property['status'] === 'sold' ? 'selected' : '' ?>>Продан</option>
                                        <option value="rented" <?= $property['status'] === 'rented' ? 'selected' : '' ?>>Арендован</option>
                                        <option value="off-market" <?= $property['status'] === 'off-market' ? 'selected' : '' ?>>Снят</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="admin-form-column">
                            <div class="form-group">
                                <label class="form-label required">Количество комнат</label>
                                <select name="bedrooms" class="form-select" required>
                                    <option value="0" <?= $property['bedrooms'] == 0 ? 'selected' : '' ?>>Студия</option>
                                    <option value="1" <?= $property['bedrooms'] == 1 ? 'selected' : '' ?>>1 комната</option>
                                    <option value="2" <?= $property['bedrooms'] == 2 ? 'selected' : '' ?>>2 комнаты</option>
                                    <option value="3" <?= $property['bedrooms'] == 3 ? 'selected' : '' ?>>3 комнаты</option>
                                    <option value="4" <?= $property['bedrooms'] == 4 ? 'selected' : '' ?>>4 комнаты</option>
                                    <option value="5" <?= $property['bedrooms'] >= 5 ? 'selected' : '' ?>>5+ комнат</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Планировка комнат</label>
                                <select name="rooms_type" class="form-select">
                                    <option value="">Не указано</option>
                                    <option value="isolated" <?= ($property['rooms_type'] ?? '') === 'isolated' ? 'selected' : '' ?>>Изолированные</option>
                                    <option value="adjacent" <?= ($property['rooms_type'] ?? '') === 'adjacent' ? 'selected' : '' ?>>Смежные</option>
                                    <option value="mixed" <?= ($property['rooms_type'] ?? '') === 'mixed' ? 'selected' : '' ?>>Смешанные</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">
                                    <input type="checkbox" name="is_new_building" value="1" <?= !empty($property['is_new_building']) ? 'checked' : '' ?>>
                                    Новостройка (первичный рынок)
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Адрес -->
                <div class="admin-card__header" style="border-top: 1px solid var(--color-border);">
                    <h2 class="admin-card__title">
                        <i class="fas fa-map-marker-alt"></i> Адрес
                    </h2>
                </div>
                <div class="admin-card__body">
                    <div class="admin-form-grid">
                        <div class="admin-form-column">
                            <div class="form-group">
                                <label class="form-label">Район Екатеринбурга</label>
                                <select name="district_id" class="form-select">
                                    <option value="">Выберите район</option>
                                    <?php foreach ($districts as $district): ?>
                                    <option value="<?= $district['id'] ?>" <?= ($property['district_id'] ?? '') == $district['id'] ? 'selected' : '' ?>>
                                        <?= escape($district['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Улица</label>
                                    <input type="text" name="street" class="form-input" 
                                           value="<?= escape($property['street'] ?? '') ?>"
                                           placeholder="ул. Ленина">
                                </div>
                                
                                <div class="form-group" style="max-width: 120px;">
                                    <label class="form-label">Дом</label>
                                    <input type="text" name="house_number" class="form-input" 
                                           value="<?= escape($property['house_number'] ?? '') ?>"
                                           placeholder="25/1">
                                </div>
                            </div>
                        </div>
                        
                        <div class="admin-form-column">
                            <div class="form-group">
                                <label class="form-label">Название ЖК / здания</label>
                                <input type="text" name="building_name" class="form-input" 
                                       value="<?= escape($property['building_name'] ?? '') ?>"
                                       placeholder="ЖК Академический">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Локация</label>
                                <input type="text" name="location" class="form-input" 
                                       value="<?= escape($property['location']) ?>">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Площадь -->
                <div class="admin-card__header" style="border-top: 1px solid var(--color-border);">
                    <h2 class="admin-card__title">
                        <i class="fas fa-ruler-combined"></i> Площадь
                    </h2>
                </div>
                <div class="admin-card__body">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label required">Общая площадь (м²)</label>
                            <input type="number" name="area_total" class="form-input" 
                                   value="<?= $property['area_total'] ?? $property['area_sqft'] ?>" required min="1" step="0.1">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Жилая площадь (м²)</label>
                            <input type="number" name="area_living" class="form-input" 
                                   value="<?= $property['area_living'] ?? '' ?>" min="0" step="0.1">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Площадь кухни (м²)</label>
                            <input type="number" name="area_kitchen" class="form-input" 
                                   value="<?= $property['area_kitchen'] ?? '' ?>" min="0" step="0.1">
                        </div>
                    </div>
                </div>
                
                <!-- Этажность -->
                <div class="admin-card__header" style="border-top: 1px solid var(--color-border);">
                    <h2 class="admin-card__title">
                        <i class="fas fa-building"></i> Этажность
                    </h2>
                </div>
                <div class="admin-card__body">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Этаж</label>
                            <input type="number" name="floor_number" class="form-input" 
                                   value="<?= $property['floor_number'] ?>" min="1" max="100">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Всего этажей</label>
                            <input type="number" name="total_floors" class="form-input" 
                                   value="<?= $property['total_floors'] ?? '' ?>" min="1" max="100">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Высота потолков (м)</label>
                            <input type="number" name="ceiling_height" class="form-input" 
                                   value="<?= $property['ceiling_height'] ?? '' ?>" min="2" max="10" step="0.1">
                        </div>
                    </div>
                </div>
                
                <!-- Состояние квартиры -->
                <div class="admin-card__header" style="border-top: 1px solid var(--color-border);">
                    <h2 class="admin-card__title">
                        <i class="fas fa-paint-roller"></i> Состояние квартиры
                    </h2>
                </div>
                <div class="admin-card__body">
                    <div class="admin-form-grid">
                        <div class="admin-form-column">
                            <div class="form-group">
                                <label class="form-label">Ремонт</label>
                                <select name="renovation" class="form-select">
                                    <option value="">Не указано</option>
                                    <option value="designer" <?= ($property['renovation'] ?? '') === 'designer' ? 'selected' : '' ?>>Дизайнерский</option>
                                    <option value="euro" <?= ($property['renovation'] ?? '') === 'euro' ? 'selected' : '' ?>>Евроремонт</option>
                                    <option value="cosmetic" <?= ($property['renovation'] ?? '') === 'cosmetic' ? 'selected' : '' ?>>Косметический</option>
                                    <option value="needs-repair" <?= ($property['renovation'] ?? '') === 'needs-repair' ? 'selected' : '' ?>>Требует ремонта</option>
                                    <option value="rough-finish" <?= ($property['renovation'] ?? '') === 'rough-finish' ? 'selected' : '' ?>>Черновая отделка</option>
                                    <option value="pre-finish" <?= ($property['renovation'] ?? '') === 'pre-finish' ? 'selected' : '' ?>>Предчистовая</option>
                                    <option value="turnkey" <?= ($property['renovation'] ?? '') === 'turnkey' ? 'selected' : '' ?>>Под ключ</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Меблировка</label>
                                <select name="furnished" class="form-select">
                                    <option value="unfurnished" <?= $property['furnished'] === 'unfurnished' ? 'selected' : '' ?>>Без мебели</option>
                                    <option value="semi-furnished" <?= $property['furnished'] === 'semi-furnished' ? 'selected' : '' ?>>Частично</option>
                                    <option value="furnished" <?= $property['furnished'] === 'furnished' ? 'selected' : '' ?>>С мебелью</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Вид из окон</label>
                                <select name="window_view" class="form-select">
                                    <option value="">Не указано</option>
                                    <option value="yard" <?= ($property['window_view'] ?? '') === 'yard' ? 'selected' : '' ?>>Во двор</option>
                                    <option value="street" <?= ($property['window_view'] ?? '') === 'street' ? 'selected' : '' ?>>На улицу</option>
                                    <option value="park" <?= ($property['window_view'] ?? '') === 'park' ? 'selected' : '' ?>>На парк</option>
                                    <option value="river" <?= ($property['window_view'] ?? '') === 'river' ? 'selected' : '' ?>>На реку</option>
                                    <option value="city" <?= ($property['window_view'] ?? '') === 'city' ? 'selected' : '' ?>>На город</option>
                                    <option value="both" <?= ($property['window_view'] ?? '') === 'both' ? 'selected' : '' ?>>На обе стороны</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="admin-form-column">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Балкон / Лоджия</label>
                                    <select name="balcony" class="form-select">
                                        <option value="">Не указано</option>
                                        <option value="balcony" <?= ($property['balcony'] ?? '') === 'balcony' ? 'selected' : '' ?>>Балкон</option>
                                        <option value="loggia" <?= ($property['balcony'] ?? '') === 'loggia' ? 'selected' : '' ?>>Лоджия</option>
                                        <option value="both" <?= ($property['balcony'] ?? '') === 'both' ? 'selected' : '' ?>>Оба</option>
                                        <option value="none" <?= ($property['balcony'] ?? '') === 'none' ? 'selected' : '' ?>>Нет</option>
                                    </select>
                                </div>
                                
                                <div class="form-group" style="max-width: 100px;">
                                    <label class="form-label">Кол-во</label>
                                    <input type="number" name="balcony_count" class="form-input" 
                                           value="<?= $property['balcony_count'] ?? 1 ?>" min="0" max="5">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Санузел</label>
                                    <select name="bathroom_type" class="form-select">
                                        <option value="">Не указано</option>
                                        <option value="combined" <?= ($property['bathroom_type'] ?? '') === 'combined' ? 'selected' : '' ?>>Совмещённый</option>
                                        <option value="separate" <?= ($property['bathroom_type'] ?? '') === 'separate' ? 'selected' : '' ?>>Раздельный</option>
                                        <option value="multiple" <?= ($property['bathroom_type'] ?? '') === 'multiple' ? 'selected' : '' ?>>Несколько</option>
                                    </select>
                                </div>
                                
                                <div class="form-group" style="max-width: 100px;">
                                    <label class="form-label">Кол-во</label>
                                    <input type="number" name="bathrooms" class="form-input" 
                                           value="<?= $property['bathrooms'] ?>" min="1" max="10">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Характеристики дома -->
                <div class="admin-card__header" style="border-top: 1px solid var(--color-border);">
                    <h2 class="admin-card__title">
                        <i class="fas fa-city"></i> Характеристики дома
                    </h2>
                </div>
                <div class="admin-card__body">
                    <div class="admin-form-grid">
                        <div class="admin-form-column">
                            <div class="form-group">
                                <label class="form-label">Тип дома</label>
                                <select name="house_type" class="form-select">
                                    <option value="">Не указано</option>
                                    <option value="panel" <?= ($property['house_type'] ?? '') === 'panel' ? 'selected' : '' ?>>Панельный</option>
                                    <option value="brick" <?= ($property['house_type'] ?? '') === 'brick' ? 'selected' : '' ?>>Кирпичный</option>
                                    <option value="monolith" <?= ($property['house_type'] ?? '') === 'monolith' ? 'selected' : '' ?>>Монолитный</option>
                                    <option value="monolith-brick" <?= ($property['house_type'] ?? '') === 'monolith-brick' ? 'selected' : '' ?>>Монолитно-кирпичный</option>
                                    <option value="block" <?= ($property['house_type'] ?? '') === 'block' ? 'selected' : '' ?>>Блочный</option>
                                    <option value="wood" <?= ($property['house_type'] ?? '') === 'wood' ? 'selected' : '' ?>>Деревянный</option>
                                    <option value="stalin" <?= ($property['house_type'] ?? '') === 'stalin' ? 'selected' : '' ?>>Сталинка</option>
                                    <option value="khrushchev" <?= ($property['house_type'] ?? '') === 'khrushchev' ? 'selected' : '' ?>>Хрущёвка</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Год постройки</label>
                                <input type="number" name="build_year" class="form-input" 
                                       value="<?= $property['build_year'] ?? '' ?>" min="1900" max="2030">
                            </div>
                        </div>
                        
                        <div class="admin-form-column">
                            <div class="form-group">
                                <label class="form-label">Удобства дома</label>
                                <div class="checkbox-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="has_elevator" value="1" <?= !empty($property['has_elevator']) ? 'checked' : '' ?>>
                                        <i class="fas fa-elevator"></i> Лифт
                                    </label>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="has_garbage_chute" value="1" <?= !empty($property['has_garbage_chute']) ? 'checked' : '' ?>>
                                        <i class="fas fa-trash"></i> Мусоропровод
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Транспортная доступность -->
                <div class="admin-card__header" style="border-top: 1px solid var(--color-border);">
                    <h2 class="admin-card__title">
                        <i class="fas fa-subway"></i> Транспортная доступность
                    </h2>
                </div>
                <div class="admin-card__body">
                    <div class="admin-form-grid">
                        <div class="admin-form-column">
                            <div class="form-group">
                                <label class="form-label">Станция метро</label>
                                <select name="metro_station" class="form-select">
                                    <option value="">Не указано</option>
                                    <?php foreach ($metroStations as $station): ?>
                                    <option value="<?= $station ?>" <?= ($property['metro_station'] ?? '') === $station ? 'selected' : '' ?>>
                                        <?= $station ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Минут до метро</label>
                                    <input type="number" name="metro_minutes" class="form-input" 
                                           value="<?= $property['metro_minutes'] ?? '' ?>" min="1" max="60">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Способ</label>
                                    <select name="metro_walk_type" class="form-select">
                                        <option value="walk" <?= ($property['metro_walk_type'] ?? '') === 'walk' ? 'selected' : '' ?>>Пешком</option>
                                        <option value="transport" <?= ($property['metro_walk_type'] ?? '') === 'transport' ? 'selected' : '' ?>>На транспорте</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="admin-form-column">
                            <div class="form-group">
                                <label class="form-label">Дополнительно о транспорте</label>
                                <textarea name="transport_info" class="form-textarea" rows="3"><?= escape($property['transport_info'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Условия аренды -->
                <div class="admin-card__header" style="border-top: 1px solid var(--color-border);" id="rent-section">
                    <h2 class="admin-card__title">
                        <i class="fas fa-file-contract"></i> Условия аренды
                    </h2>
                </div>
                <div class="admin-card__body" id="rent-fields">
                    <div class="admin-form-grid">
                        <div class="admin-form-column">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Залог (месяцев)</label>
                                    <input type="number" name="rent_deposit_months" class="form-input" 
                                           value="<?= $property['rent_deposit_months'] ?? 1 ?>" min="0" max="12">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Мин. срок аренды (мес.)</label>
                                    <input type="number" name="min_rent_period" class="form-input" 
                                           value="<?= $property['min_rent_period'] ?? '' ?>" min="1" max="60">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Комиссия</label>
                                <select name="rent_commission_type" class="form-select">
                                    <option value="">Не указано</option>
                                    <option value="no-commission" <?= ($property['rent_commission_type'] ?? '') === 'no-commission' ? 'selected' : '' ?>>Без комиссии</option>
                                    <option value="owner" <?= ($property['rent_commission_type'] ?? '') === 'owner' ? 'selected' : '' ?>>От собственника</option>
                                    <option value="tenant" <?= ($property['rent_commission_type'] ?? '') === 'tenant' ? 'selected' : '' ?>>От арендатора</option>
                                    <option value="shared" <?= ($property['rent_commission_type'] ?? '') === 'shared' ? 'selected' : '' ?>>50/50</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="admin-form-column">
                            <div class="form-group">
                                <label class="form-label">Условия проживания</label>
                                <div class="checkbox-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="utilities_included" value="1" <?= !empty($property['utilities_included']) ? 'checked' : '' ?>>
                                        КУ включены
                                    </label>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="pets_allowed" value="1" <?= !empty($property['pets_allowed']) ? 'checked' : '' ?>>
                                        Можно с животными
                                    </label>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="children_allowed" value="1" <?= ($property['children_allowed'] ?? 1) ? 'checked' : '' ?>>
                                        Можно с детьми
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Описание -->
                <div class="admin-card__header" style="border-top: 1px solid var(--color-border);">
                    <h2 class="admin-card__title">
                        <i class="fas fa-align-left"></i> Описание
                    </h2>
                </div>
                <div class="admin-card__body">
                    <div class="form-group">
                        <label class="form-label">Описание объекта</label>
                        <textarea name="description_ru" class="form-textarea" rows="6"><?= escape($property['description_ru'] ?? $property['description']) ?></textarea>
                    </div>
                </div>
                
                <!-- Изображения -->
                <div class="admin-card__header" style="border-top: 1px solid var(--color-border);">
                    <h2 class="admin-card__title">
                        <i class="fas fa-images"></i> Изображения
                    </h2>
                </div>
                <div class="admin-card__body">
                    <?php if (!empty($images)): ?>
                    <div class="image-preview-grid">
                        <?php foreach ($images as $image): ?>
                        <div class="image-preview <?= $image['is_primary'] ? 'image-preview--primary' : '' ?>">
                            <img src="<?= imgSrc($image['image_url']) ?>" alt="">
                            <?php if ($image['is_primary']): ?>
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
                    
                    <div class="form-group" style="margin-top: var(--space-4);">
                        <label class="form-label">Добавить новые изображения (до 10 шт.)</label>
                        <input type="file" name="property_images[]" class="form-input" multiple accept="image/jpeg,image/jpg,image/png,image/webp">
                        <span class="form-help">Новые изображения будут добавлены к существующим. Форматы: JPG, PNG, WEBP.</span>
                    </div>
                </div>
                
                <!-- Удобства -->
                <div class="admin-card__header" style="border-top: 1px solid var(--color-border);">
                    <h2 class="admin-card__title">
                        <i class="fas fa-list-check"></i> Удобства
                    </h2>
                </div>
                <div class="admin-card__body">
                    <div class="amenities-grid">
                        <?php foreach ($amenities as $amenity): ?>
                        <label class="checkbox-label">
                            <input type="checkbox" name="amenities[]" value="<?= $amenity['id'] ?>"
                                   <?= in_array($amenity['id'], $propertyAmenities) ? 'checked' : '' ?>>
                            <i class="fas <?= $amenity['icon'] ?>"></i>
                            <?= escape($amenity['name_ru'] ?? $amenity['name']) ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Submit -->
                <div class="admin-card__body" style="border-top: 1px solid var(--color-border);">
                    <div class="form-actions">
                        <button type="submit" class="btn btn--primary btn--lg">
                            <i class="fas fa-save"></i> Сохранить
                        </button>
                        <a href="dashboard.php" class="btn btn--secondary btn--lg">Назад</a>
                    </div>
                </div>
            </form>
        </main>
    </div>
    
    <script>
    // Показывать/скрывать секцию аренды
    document.querySelector('select[name="listing_type"]').addEventListener('change', function() {
        const rentSection = document.getElementById('rent-section');
        const rentFields = document.getElementById('rent-fields');
        if (this.value === 'rent') {
            rentSection.style.display = '';
            rentFields.style.display = '';
        } else {
            rentSection.style.display = 'none';
            rentFields.style.display = 'none';
        }
    });
    
    // Инициализация при загрузке
    document.addEventListener('DOMContentLoaded', function() {
        const listingType = document.querySelector('select[name="listing_type"]');
        if (listingType.value !== 'rent') {
            document.getElementById('rent-section').style.display = 'none';
            document.getElementById('rent-fields').style.display = 'none';
        }
    });
    </script>
</body>
</html>
