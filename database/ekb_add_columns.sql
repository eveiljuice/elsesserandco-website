-- ============================================
-- Добавление новых полей в таблицу properties
-- Для рынка недвижимости Екатеринбурга
-- ============================================

USE `realestate_db`;

-- ВНИМАНИЕ: Этот скрипт добавляет колонки БЕЗ проверки их существования
-- Перед повторным запуском нужно удалить добавленные колонки или пересоздать БД

-- ШАГ 1: Обновляем ENUM для property_type
ALTER TABLE `properties` 
    MODIFY COLUMN `property_type` ENUM(
        'apartment', 'villa', 'townhouse', 'penthouse', 'studio', 'commercial',
        'room', 'house', 'cottage', 'land', 'parking'
    ) NOT NULL;

-- Мигрируем старые значения
UPDATE `properties` SET `property_type` = 'house' WHERE `property_type` = 'villa';
UPDATE `properties` SET `property_type` = 'apartment' WHERE `property_type` = 'penthouse';

-- Финальный ENUM
ALTER TABLE `properties` 
    MODIFY COLUMN `property_type` ENUM(
        'apartment', 'studio', 'room', 'house', 'townhouse', 
        'cottage', 'commercial', 'land', 'parking'
    ) NOT NULL;

-- ШАГ 2: Добавляем основные поля
ALTER TABLE `properties` 
    ADD COLUMN `category` ENUM('sale', 'rent', 'new-building') DEFAULT 'sale' AFTER `id`;

ALTER TABLE `properties` 
    ADD COLUMN `district_id` INT UNSIGNED DEFAULT NULL AFTER `community`,
    ADD FOREIGN KEY (`district_id`) REFERENCES `ekb_districts`(`id`) ON DELETE SET NULL;

ALTER TABLE `properties` 
    ADD COLUMN `street` VARCHAR(200) DEFAULT NULL AFTER `location`,
    ADD COLUMN `house_number` VARCHAR(20) DEFAULT NULL AFTER `street`;

-- ШАГ 3: Площади
ALTER TABLE `properties` 
    ADD COLUMN `area_total` DECIMAL(10,2) DEFAULT NULL AFTER `area_sqft`,
    ADD COLUMN `area_living` DECIMAL(10,2) DEFAULT NULL AFTER `area_total`,
    ADD COLUMN `area_kitchen` DECIMAL(10,2) DEFAULT NULL AFTER `area_living`;

-- ШАГ 4: Комнаты
ALTER TABLE `properties` 
    MODIFY COLUMN `bedrooms` TINYINT UNSIGNED DEFAULT NULL COMMENT 'Количество комнат',
    ADD COLUMN `rooms_type` ENUM('isolated', 'adjacent', 'mixed') DEFAULT NULL AFTER `bedrooms`;

-- ШАГ 5: Состояние и удобства квартиры
ALTER TABLE `properties` 
    ADD COLUMN `renovation` ENUM(
        'designer', 'euro', 'cosmetic', 'needs-repair', 
        'rough-finish', 'pre-finish', 'turnkey'
    ) DEFAULT NULL AFTER `furnished`;

ALTER TABLE `properties` 
    ADD COLUMN `balcony` ENUM('balcony', 'loggia', 'both', 'none') DEFAULT NULL,
    ADD COLUMN `balcony_count` TINYINT UNSIGNED DEFAULT 0;

ALTER TABLE `properties` 
    ADD COLUMN `bathroom_type` ENUM('combined', 'separate', 'multiple') DEFAULT NULL AFTER `bathrooms`;

ALTER TABLE `properties` 
    ADD COLUMN `window_view` ENUM('yard', 'street', 'park', 'river', 'city', 'both') DEFAULT NULL;

-- ШАГ 6: Характеристики дома
ALTER TABLE `properties` 
    ADD COLUMN `house_type` ENUM(
        'panel', 'brick', 'monolith', 'monolith-brick', 
        'block', 'wood', 'stalin', 'khrushchev'
    ) DEFAULT NULL AFTER `building_name`;

ALTER TABLE `properties` 
    ADD COLUMN `build_year` SMALLINT UNSIGNED DEFAULT NULL,
    ADD COLUMN `ceiling_height` DECIMAL(3,2) DEFAULT NULL,
    ADD COLUMN `has_elevator` TINYINT(1) DEFAULT NULL,
    ADD COLUMN `has_garbage_chute` TINYINT(1) DEFAULT NULL,
    ADD COLUMN `is_new_building` TINYINT(1) DEFAULT 0;

-- ШАГ 7: Транспортная доступность
ALTER TABLE `properties` 
    ADD COLUMN `metro_station` VARCHAR(100) DEFAULT NULL,
    ADD COLUMN `metro_minutes` TINYINT UNSIGNED DEFAULT NULL,
    ADD COLUMN `metro_walk_type` ENUM('walk', 'transport') DEFAULT 'walk',
    ADD COLUMN `transport_info` TEXT DEFAULT NULL;

-- ШАГ 8: Специфичные поля для аренды
ALTER TABLE `properties` 
    ADD COLUMN `rent_deposit_months` TINYINT UNSIGNED DEFAULT 1,
    ADD COLUMN `rent_commission_type` ENUM('owner', 'tenant', 'shared', 'no-commission') DEFAULT NULL,
    ADD COLUMN `min_rent_period` TINYINT UNSIGNED DEFAULT NULL COMMENT 'В месяцах',
    ADD COLUMN `utilities_included` TINYINT(1) DEFAULT 0,
    ADD COLUMN `pets_allowed` TINYINT(1) DEFAULT 0,
    ADD COLUMN `children_allowed` TINYINT(1) DEFAULT 1;

SELECT 'Все колонки успешно добавлены в таблицу properties!' AS status;

