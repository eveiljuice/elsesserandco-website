<?php
/**
 * Add Property - Agent CRM
 * Добавление нового объекта агентом (Екатеринбург)
 */

require_once __DIR__ . '/../includes/config/database.php';
require_once __DIR__ . '/../includes/auth/check_auth.php';

requireAgent();

$pdo = getDBConnection();
$userId = getCurrentUserId();
$errors = [];
$success = false;

// Получаем список удобств
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
    // Валидация CSRF
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Ошибка безопасности. Обновите страницу и попробуйте снова.';
    }
    
    // Основные поля
    $titleRu = trim($_POST['title_ru'] ?? '');
    $descriptionRu = trim($_POST['description_ru'] ?? '');
    $propertyType = $_POST['property_type'] ?? '';
    $listingType = $_POST['listing_type'] ?? '';
    $category = $listingType; // sale или rent
    $price = floatval($_POST['price'] ?? 0);
    
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
    if (empty($listingType)) $errors[] = 'Выберите тип (продажа/аренда)';
    if ($price <= 0) $errors[] = 'Введите корректную цену';
    if ($areaTotal <= 0) $errors[] = 'Введите общую площадь';
    if ($bedrooms < 0) $errors[] = 'Укажите количество комнат';
    if (empty($street) && empty($location)) $errors[] = 'Укажите адрес объекта';
    
    // Если нет ошибок - создаём объект
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Формируем полный адрес для location
            $fullLocation = $street;
            if ($houseNumber) $fullLocation .= ', ' . $houseNumber;
            if (empty($fullLocation)) $fullLocation = $location;
            
            $stmt = $pdo->prepare("
                INSERT INTO properties 
                (title, title_ru, description, description_ru, property_type, listing_type, category, price, 
                 location, district_id, street, house_number, building_name,
                 area_sqft, area_total, area_living, area_kitchen,
                 bedrooms, rooms_type, bathrooms, bathroom_type,
                 floor_number, total_floors, furnished, renovation,
                 balcony, balcony_count, window_view,
                 house_type, build_year, ceiling_height, has_elevator, has_garbage_chute, is_new_building,
                 metro_station, metro_minutes, metro_walk_type, transport_info,
                 rent_deposit_months, rent_commission_type, min_rent_period, utilities_included, pets_allowed, children_allowed,
                 agent_id, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'available')
            ");
            
            $stmt->execute([
                $titleRu, $titleRu, $descriptionRu ?: null, $descriptionRu ?: null,
                $propertyType, $listingType, $category, $price,
                $fullLocation, $districtId, $street ?: null, $houseNumber ?: null, $buildingName ?: null,
                $areaTotal, $areaTotal, $areaLiving, $areaKitchen,
                $bedrooms, $roomsType, $bathrooms, $bathroomType,
                $floorNumber, $totalFloors, $furnished, $renovation,
                $balcony, $balconyCount, $windowView,
                $houseType, $buildYear, $ceilingHeight, $hasElevator, $hasGarbageChute, $isNewBuilding,
                $metroStation, $metroMinutes, $metroWalkType, $transportInfo ?: null,
                $rentDepositMonths, $rentCommissionType, $minRentPeriod, $utilitiesIncluded, $petsAllowed, $childrenAllowed,
                $userId
            ]);
            
            $propertyId = $pdo->lastInsertId();
            
            // Обработка изображений
            if (!empty($_FILES['property_images']['name'][0])) {
                $uploadDir = __DIR__ . '/../uploads/properties/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $sortOrder = 0;
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
            
            // Привязка удобств
            if (!empty($selectedAmenities)) {
                $stmt = $pdo->prepare("INSERT INTO property_amenities (property_id, amenity_id) VALUES (?, ?)");
                foreach ($selectedAmenities as $amenityId) {
                    $stmt->execute([$propertyId, intval($amenityId)]);
                }
            }
            
            $pdo->commit();
            $success = true;
            
            // Редирект на редактирование
            header("Location: edit-property.php?id=$propertyId&created=1");
            exit;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = 'Ошибка при создании объекта: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Добавить объект';
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
                    <h1 class="admin-title">Добавить объект</h1>
                    <div class="admin-breadcrumb">
                        <a href="dashboard.php">Dashboard</a> / Новый объект
                    </div>
                </div>
            </div>
            
            <?php if (!empty($errors)): ?>
            <div class="alert alert--error">
                <strong>Ошибка:</strong>
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
                                       value="<?= escape($_POST['title_ru'] ?? '') ?>" required
                                       placeholder="Например: 2-комн. квартира, 56 м²">
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label required">Тип недвижимости</label>
                                    <select name="property_type" class="form-select" required>
                                        <option value="">Выберите тип</option>
                                        <option value="apartment" <?= ($_POST['property_type'] ?? '') === 'apartment' ? 'selected' : '' ?>>Квартира</option>
                                        <option value="studio" <?= ($_POST['property_type'] ?? '') === 'studio' ? 'selected' : '' ?>>Студия</option>
                                        <option value="room" <?= ($_POST['property_type'] ?? '') === 'room' ? 'selected' : '' ?>>Комната</option>
                                        <option value="house" <?= ($_POST['property_type'] ?? '') === 'house' ? 'selected' : '' ?>>Дом</option>
                                        <option value="townhouse" <?= ($_POST['property_type'] ?? '') === 'townhouse' ? 'selected' : '' ?>>Таунхаус</option>
                                        <option value="cottage" <?= ($_POST['property_type'] ?? '') === 'cottage' ? 'selected' : '' ?>>Коттедж</option>
                                        <option value="commercial" <?= ($_POST['property_type'] ?? '') === 'commercial' ? 'selected' : '' ?>>Коммерческая</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label required">Тип сделки</label>
                                    <select name="listing_type" class="form-select" required>
                                        <option value="">Выберите</option>
                                        <option value="sale" <?= ($_POST['listing_type'] ?? '') === 'sale' ? 'selected' : '' ?>>Продажа</option>
                                        <option value="rent" <?= ($_POST['listing_type'] ?? '') === 'rent' ? 'selected' : '' ?>>Аренда</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label required">Цена (₽)</label>
                                <input type="number" name="price" class="form-input" 
                                       value="<?= escape($_POST['price'] ?? '') ?>" required min="1" step="1000"
                                       placeholder="5 500 000">
                                <span class="form-help">Для аренды — цена за месяц</span>
                            </div>
                        </div>
                        
                        <div class="admin-form-column">
                            <div class="form-group">
                                <label class="form-label required">Количество комнат</label>
                                <select name="bedrooms" class="form-select" required>
                                    <option value="0" <?= ($_POST['bedrooms'] ?? '') === '0' ? 'selected' : '' ?>>Студия</option>
                                    <option value="1" <?= ($_POST['bedrooms'] ?? '') === '1' ? 'selected' : '' ?>>1 комната</option>
                                    <option value="2" <?= ($_POST['bedrooms'] ?? '2') === '2' ? 'selected' : '' ?>>2 комнаты</option>
                                    <option value="3" <?= ($_POST['bedrooms'] ?? '') === '3' ? 'selected' : '' ?>>3 комнаты</option>
                                    <option value="4" <?= ($_POST['bedrooms'] ?? '') === '4' ? 'selected' : '' ?>>4 комнаты</option>
                                    <option value="5" <?= ($_POST['bedrooms'] ?? '') === '5' ? 'selected' : '' ?>>5+ комнат</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Планировка комнат</label>
                                <select name="rooms_type" class="form-select">
                                    <option value="">Не указано</option>
                                    <option value="isolated" <?= ($_POST['rooms_type'] ?? '') === 'isolated' ? 'selected' : '' ?>>Изолированные</option>
                                    <option value="adjacent" <?= ($_POST['rooms_type'] ?? '') === 'adjacent' ? 'selected' : '' ?>>Смежные</option>
                                    <option value="mixed" <?= ($_POST['rooms_type'] ?? '') === 'mixed' ? 'selected' : '' ?>>Смешанные</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">
                                    <input type="checkbox" name="is_new_building" value="1" <?= !empty($_POST['is_new_building']) ? 'checked' : '' ?>>
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
                                    <option value="<?= $district['id'] ?>" <?= ($_POST['district_id'] ?? '') == $district['id'] ? 'selected' : '' ?>>
                                        <?= escape($district['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label required">Улица</label>
                                    <input type="text" name="street" class="form-input" 
                                           value="<?= escape($_POST['street'] ?? '') ?>"
                                           placeholder="ул. Ленина">
                                </div>
                                
                                <div class="form-group" style="max-width: 120px;">
                                    <label class="form-label">Дом</label>
                                    <input type="text" name="house_number" class="form-input" 
                                           value="<?= escape($_POST['house_number'] ?? '') ?>"
                                           placeholder="25/1">
                                </div>
                            </div>
                        </div>
                        
                        <div class="admin-form-column">
                            <div class="form-group">
                                <label class="form-label">Название ЖК / здания</label>
                                <input type="text" name="building_name" class="form-input" 
                                       value="<?= escape($_POST['building_name'] ?? '') ?>"
                                       placeholder="ЖК Академический">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Локация (дополнительно)</label>
                                <input type="text" name="location" class="form-input" 
                                       value="<?= escape($_POST['location'] ?? '') ?>"
                                       placeholder="Академический район, рядом с парком">
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
                                   value="<?= escape($_POST['area_total'] ?? '') ?>" required min="1" step="0.1"
                                   placeholder="56.5">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Жилая площадь (м²)</label>
                            <input type="number" name="area_living" class="form-input" 
                                   value="<?= escape($_POST['area_living'] ?? '') ?>" min="0" step="0.1"
                                   placeholder="32.0">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Площадь кухни (м²)</label>
                            <input type="number" name="area_kitchen" class="form-input" 
                                   value="<?= escape($_POST['area_kitchen'] ?? '') ?>" min="0" step="0.1"
                                   placeholder="12.0">
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
                                   value="<?= escape($_POST['floor_number'] ?? '') ?>" min="1" max="100"
                                   placeholder="5">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Всего этажей</label>
                            <input type="number" name="total_floors" class="form-input" 
                                   value="<?= escape($_POST['total_floors'] ?? '') ?>" min="1" max="100"
                                   placeholder="16">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Высота потолков (м)</label>
                            <input type="number" name="ceiling_height" class="form-input" 
                                   value="<?= escape($_POST['ceiling_height'] ?? '') ?>" min="2" max="10" step="0.1"
                                   placeholder="2.7">
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
                                    <option value="designer" <?= ($_POST['renovation'] ?? '') === 'designer' ? 'selected' : '' ?>>Дизайнерский</option>
                                    <option value="euro" <?= ($_POST['renovation'] ?? '') === 'euro' ? 'selected' : '' ?>>Евроремонт</option>
                                    <option value="cosmetic" <?= ($_POST['renovation'] ?? '') === 'cosmetic' ? 'selected' : '' ?>>Косметический</option>
                                    <option value="needs-repair" <?= ($_POST['renovation'] ?? '') === 'needs-repair' ? 'selected' : '' ?>>Требует ремонта</option>
                                    <option value="rough-finish" <?= ($_POST['renovation'] ?? '') === 'rough-finish' ? 'selected' : '' ?>>Черновая отделка</option>
                                    <option value="pre-finish" <?= ($_POST['renovation'] ?? '') === 'pre-finish' ? 'selected' : '' ?>>Предчистовая</option>
                                    <option value="turnkey" <?= ($_POST['renovation'] ?? '') === 'turnkey' ? 'selected' : '' ?>>Под ключ</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Меблировка</label>
                                <select name="furnished" class="form-select">
                                    <option value="unfurnished" <?= ($_POST['furnished'] ?? '') === 'unfurnished' ? 'selected' : '' ?>>Без мебели</option>
                                    <option value="semi-furnished" <?= ($_POST['furnished'] ?? '') === 'semi-furnished' ? 'selected' : '' ?>>Частично меблирована</option>
                                    <option value="furnished" <?= ($_POST['furnished'] ?? '') === 'furnished' ? 'selected' : '' ?>>Полностью меблирована</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Вид из окон</label>
                                <select name="window_view" class="form-select">
                                    <option value="">Не указано</option>
                                    <option value="yard" <?= ($_POST['window_view'] ?? '') === 'yard' ? 'selected' : '' ?>>Во двор</option>
                                    <option value="street" <?= ($_POST['window_view'] ?? '') === 'street' ? 'selected' : '' ?>>На улицу</option>
                                    <option value="park" <?= ($_POST['window_view'] ?? '') === 'park' ? 'selected' : '' ?>>На парк</option>
                                    <option value="river" <?= ($_POST['window_view'] ?? '') === 'river' ? 'selected' : '' ?>>На реку</option>
                                    <option value="city" <?= ($_POST['window_view'] ?? '') === 'city' ? 'selected' : '' ?>>На город</option>
                                    <option value="both" <?= ($_POST['window_view'] ?? '') === 'both' ? 'selected' : '' ?>>На обе стороны</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="admin-form-column">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Балкон / Лоджия</label>
                                    <select name="balcony" class="form-select">
                                        <option value="">Не указано</option>
                                        <option value="balcony" <?= ($_POST['balcony'] ?? '') === 'balcony' ? 'selected' : '' ?>>Балкон</option>
                                        <option value="loggia" <?= ($_POST['balcony'] ?? '') === 'loggia' ? 'selected' : '' ?>>Лоджия</option>
                                        <option value="both" <?= ($_POST['balcony'] ?? '') === 'both' ? 'selected' : '' ?>>Балкон и лоджия</option>
                                        <option value="none" <?= ($_POST['balcony'] ?? '') === 'none' ? 'selected' : '' ?>>Нет</option>
                                    </select>
                                </div>
                                
                                <div class="form-group" style="max-width: 100px;">
                                    <label class="form-label">Кол-во</label>
                                    <input type="number" name="balcony_count" class="form-input" 
                                           value="<?= escape($_POST['balcony_count'] ?? '1') ?>" min="0" max="5">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Санузел</label>
                                    <select name="bathroom_type" class="form-select">
                                        <option value="">Не указано</option>
                                        <option value="combined" <?= ($_POST['bathroom_type'] ?? '') === 'combined' ? 'selected' : '' ?>>Совмещённый</option>
                                        <option value="separate" <?= ($_POST['bathroom_type'] ?? '') === 'separate' ? 'selected' : '' ?>>Раздельный</option>
                                        <option value="multiple" <?= ($_POST['bathroom_type'] ?? '') === 'multiple' ? 'selected' : '' ?>>Несколько</option>
                                    </select>
                                </div>
                                
                                <div class="form-group" style="max-width: 100px;">
                                    <label class="form-label">Кол-во</label>
                                    <input type="number" name="bathrooms" class="form-input" 
                                           value="<?= escape($_POST['bathrooms'] ?? '1') ?>" min="1" max="10">
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
                                    <option value="panel" <?= ($_POST['house_type'] ?? '') === 'panel' ? 'selected' : '' ?>>Панельный</option>
                                    <option value="brick" <?= ($_POST['house_type'] ?? '') === 'brick' ? 'selected' : '' ?>>Кирпичный</option>
                                    <option value="monolith" <?= ($_POST['house_type'] ?? '') === 'monolith' ? 'selected' : '' ?>>Монолитный</option>
                                    <option value="monolith-brick" <?= ($_POST['house_type'] ?? '') === 'monolith-brick' ? 'selected' : '' ?>>Монолитно-кирпичный</option>
                                    <option value="block" <?= ($_POST['house_type'] ?? '') === 'block' ? 'selected' : '' ?>>Блочный</option>
                                    <option value="wood" <?= ($_POST['house_type'] ?? '') === 'wood' ? 'selected' : '' ?>>Деревянный</option>
                                    <option value="stalin" <?= ($_POST['house_type'] ?? '') === 'stalin' ? 'selected' : '' ?>>Сталинка</option>
                                    <option value="khrushchev" <?= ($_POST['house_type'] ?? '') === 'khrushchev' ? 'selected' : '' ?>>Хрущёвка</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Год постройки</label>
                                <input type="number" name="build_year" class="form-input" 
                                       value="<?= escape($_POST['build_year'] ?? '') ?>" min="1900" max="2030"
                                       placeholder="2020">
                            </div>
                        </div>
                        
                        <div class="admin-form-column">
                            <div class="form-group">
                                <label class="form-label">Удобства дома</label>
                                <div class="checkbox-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="has_elevator" value="1" <?= !empty($_POST['has_elevator']) ? 'checked' : '' ?>>
                                        <i class="fas fa-elevator"></i> Лифт
                                    </label>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="has_garbage_chute" value="1" <?= !empty($_POST['has_garbage_chute']) ? 'checked' : '' ?>>
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
                                    <option value="<?= $station ?>" <?= ($_POST['metro_station'] ?? '') === $station ? 'selected' : '' ?>>
                                        <?= $station ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Минут до метро</label>
                                    <input type="number" name="metro_minutes" class="form-input" 
                                           value="<?= escape($_POST['metro_minutes'] ?? '') ?>" min="1" max="60"
                                           placeholder="10">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Способ</label>
                                    <select name="metro_walk_type" class="form-select">
                                        <option value="walk" <?= ($_POST['metro_walk_type'] ?? '') === 'walk' ? 'selected' : '' ?>>Пешком</option>
                                        <option value="transport" <?= ($_POST['metro_walk_type'] ?? '') === 'transport' ? 'selected' : '' ?>>На транспорте</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="admin-form-column">
                            <div class="form-group">
                                <label class="form-label">Дополнительно о транспорте</label>
                                <textarea name="transport_info" class="form-textarea" rows="3"
                                          placeholder="Рядом остановки автобусов 10, 25, трамвай 5..."><?= escape($_POST['transport_info'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Условия аренды (показывать только для аренды) -->
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
                                           value="<?= escape($_POST['rent_deposit_months'] ?? '1') ?>" min="0" max="12">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Мин. срок аренды (мес.)</label>
                                    <input type="number" name="min_rent_period" class="form-input" 
                                           value="<?= escape($_POST['min_rent_period'] ?? '') ?>" min="1" max="60"
                                           placeholder="12">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Комиссия</label>
                                <select name="rent_commission_type" class="form-select">
                                    <option value="">Не указано</option>
                                    <option value="no-commission" <?= ($_POST['rent_commission_type'] ?? '') === 'no-commission' ? 'selected' : '' ?>>Без комиссии</option>
                                    <option value="owner" <?= ($_POST['rent_commission_type'] ?? '') === 'owner' ? 'selected' : '' ?>>Комиссия от собственника</option>
                                    <option value="tenant" <?= ($_POST['rent_commission_type'] ?? '') === 'tenant' ? 'selected' : '' ?>>Комиссия от арендатора</option>
                                    <option value="shared" <?= ($_POST['rent_commission_type'] ?? '') === 'shared' ? 'selected' : '' ?>>50/50</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="admin-form-column">
                            <div class="form-group">
                                <label class="form-label">Условия проживания</label>
                                <div class="checkbox-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="utilities_included" value="1" <?= !empty($_POST['utilities_included']) ? 'checked' : '' ?>>
                                        КУ включены в стоимость
                                    </label>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="pets_allowed" value="1" <?= !empty($_POST['pets_allowed']) ? 'checked' : '' ?>>
                                        Можно с животными
                                    </label>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="children_allowed" value="1" <?= isset($_POST['children_allowed']) ? (!empty($_POST['children_allowed']) ? 'checked' : '') : 'checked' ?>>
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
                        <textarea name="description_ru" class="form-textarea" rows="6" 
                                  placeholder="Подробное описание объекта, его особенностей и преимуществ..."><?= escape($_POST['description_ru'] ?? '') ?></textarea>
                    </div>
                </div>
                
                <!-- Изображения -->
                <div class="admin-card__header" style="border-top: 1px solid var(--color-border);">
                    <h2 class="admin-card__title">
                        <i class="fas fa-images"></i> Изображения
                    </h2>
                </div>
                <div class="admin-card__body">
                    <div class="form-group">
                        <label class="form-label">Загрузить изображения (до 10 шт.)</label>
                        <input type="file" name="property_images[]" class="form-input" multiple accept="image/jpeg,image/jpg,image/png,image/webp">
                        <span class="form-help">Первое изображение будет главным. Форматы: JPG, PNG, WEBP. Максимум 10 фото.</span>
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
                                   <?= in_array($amenity['id'], $_POST['amenities'] ?? []) ? 'checked' : '' ?>>
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
                            <i class="fas fa-plus"></i> Создать объект
                        </button>
                        <a href="dashboard.php" class="btn btn--secondary btn--lg">Отмена</a>
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
