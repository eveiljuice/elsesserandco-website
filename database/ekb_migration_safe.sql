-- ============================================
-- Elsesser & Co. - Миграция для рынка Екатеринбурга
-- Version: 2.0 (Safe - с проверкой существования колонок)
-- Три категории: Готовое жильё (продажа), Готовое жильё (аренда), Новостройки
-- ============================================

USE `realestate_db`;

-- ============================================
-- 1. СПРАВОЧНИКИ
-- ============================================

-- Районы Екатеринбурга
CREATE TABLE IF NOT EXISTS `ekb_districts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL UNIQUE,
  `sort_order` TINYINT UNSIGNED DEFAULT 0,
  INDEX `idx_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `ekb_districts` (`name`, `slug`, `sort_order`) VALUES
('Центр (ВИЗ)', 'center-viz', 1),
('Центр (Исторический)', 'center-historical', 2),
('Автовокзал', 'avtovokzal', 3),
('Ботанический', 'botanicheskiy', 4),
('Академический', 'akademicheskiy', 5),
('Уралмаш', 'uralmash', 6),
('Эльмаш', 'elmash', 7),
('Пионерский', 'pionerskiy', 8),
('Втузгородок', 'vtuzgorodok', 9),
('Парковый', 'parkoviy', 10),
('Юго-Западный', 'yugo-zapadniy', 11),
('Сортировка', 'sortirovka', 12),
('Широкая речка', 'shirokaya-rechka', 13),
('Верх-Исетский', 'verh-isetskiy', 14),
('Железнодорожный', 'zheleznodorozhniy', 15),
('Кировский', 'kirovskiy', 16),
('Ленинский', 'leninskiy', 17),
('Октябрьский', 'oktyabrskiy', 18),
('Орджоникидзевский', 'ordzhonikidzevskiy', 19),
('Чкаловский', 'chkalovskiy', 20)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Застройщики
CREATE TABLE IF NOT EXISTS `developers` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `logo` VARCHAR(500) DEFAULT NULL,
  `description` TEXT,
  `website` VARCHAR(255) DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `developers` (`name`, `description`) VALUES
('УГМК-Застройщик', 'Один из крупнейших застройщиков Екатеринбурга'),
('Атомстройкомплекс', 'Застройщик с 30-летней историей'),
('Брусника', 'Девелопер комфорт-класса'),
('Группа ЛСР', 'Федеральный застройщик'),
('Синара-Девелопмент', 'Застройщик премиальной недвижимости'),
('Форум-групп', 'Крупный уральский девелопер'),
('ЮИТ', 'Финский застройщик'),
('Prinzip', 'Застройщик элитной недвижимости'),
('TEN Девелопмент', 'Застройщик комфорт и бизнес-класса'),
('NOVA-Building', 'Современный застройщик')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- ============================================
-- 2. МОДИФИКАЦИЯ ТАБЛИЦЫ PROPERTIES (Готовое жильё)
-- ============================================

-- Используем динамический SQL для проверки существования колонок
-- Это более безопасно для повторного запуска

DELIMITER $$

-- Процедура для безопасного добавления колонки
DROP PROCEDURE IF EXISTS add_column_if_not_exists$$
CREATE PROCEDURE add_column_if_not_exists(
    IN table_name VARCHAR(128),
    IN column_name VARCHAR(128),
    IN column_definition TEXT
)
BEGIN
    IF NOT EXISTS(
        SELECT * FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = table_name
        AND COLUMN_NAME = column_name
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE `', table_name, '` ADD COLUMN `', column_name, '` ', column_definition);
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

DELIMITER ;

-- ШАГ 1: Сначала обновляем ENUM для property_type если нужно
-- Проверяем текущие значения в таблице
SET @has_villa = (SELECT COUNT(*) FROM `properties` WHERE `property_type` = 'villa');
SET @has_penthouse = (SELECT COUNT(*) FROM `properties` WHERE `property_type` = 'penthouse');

-- Если есть старые значения, мигрируем их
SET @ddl = "
    ALTER TABLE `properties` 
    MODIFY COLUMN `property_type` ENUM(
        'apartment', 'villa', 'townhouse', 'penthouse', 'studio', 'commercial',
        'room', 'house', 'cottage', 'land', 'parking'
    ) NOT NULL
";
SET @stmt = IF(@has_villa > 0 OR @has_penthouse > 0, @ddl, 'SELECT "No migration needed" AS status');
PREPARE migration_stmt FROM @stmt;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

-- Обновляем старые значения
UPDATE `properties` SET `property_type` = 'house' WHERE `property_type` = 'villa';
UPDATE `properties` SET `property_type` = 'apartment' WHERE `property_type` = 'penthouse';

-- Финальный ENUM без старых значений
ALTER TABLE `properties` 
    MODIFY COLUMN `property_type` ENUM(
        'apartment', 'studio', 'room', 'house', 'townhouse', 
        'cottage', 'commercial', 'land', 'parking'
    ) NOT NULL;

-- ШАГ 2: Добавляем новые колонки через процедуру
CALL add_column_if_not_exists('properties', 'category', "ENUM('sale', 'rent', 'new-building') DEFAULT 'sale' AFTER `id`");
CALL add_column_if_not_exists('properties', 'district_id', 'INT UNSIGNED DEFAULT NULL AFTER `community`');
CALL add_column_if_not_exists('properties', 'street', 'VARCHAR(200) DEFAULT NULL AFTER `location`');
CALL add_column_if_not_exists('properties', 'house_number', 'VARCHAR(20) DEFAULT NULL AFTER `street`');
CALL add_column_if_not_exists('properties', 'area_total', 'DECIMAL(10,2) DEFAULT NULL AFTER `area_sqft`');
CALL add_column_if_not_exists('properties', 'area_living', 'DECIMAL(10,2) DEFAULT NULL AFTER `area_total`');
CALL add_column_if_not_exists('properties', 'area_kitchen', 'DECIMAL(10,2) DEFAULT NULL AFTER `area_living`');
CALL add_column_if_not_exists('properties', 'rooms_type', "ENUM('isolated', 'adjacent', 'mixed') DEFAULT NULL AFTER `bedrooms`");
CALL add_column_if_not_exists('properties', 'renovation', "ENUM('designer', 'euro', 'cosmetic', 'needs-repair', 'rough-finish', 'pre-finish', 'turnkey') DEFAULT NULL AFTER `furnished`");
CALL add_column_if_not_exists('properties', 'balcony', "ENUM('balcony', 'loggia', 'both', 'none') DEFAULT NULL");
CALL add_column_if_not_exists('properties', 'balcony_count', 'TINYINT UNSIGNED DEFAULT 0');
CALL add_column_if_not_exists('properties', 'bathroom_type', "ENUM('combined', 'separate', 'multiple') DEFAULT NULL");
CALL add_column_if_not_exists('properties', 'window_view', "ENUM('yard', 'street', 'park', 'river', 'city', 'both') DEFAULT NULL");
CALL add_column_if_not_exists('properties', 'house_type', "ENUM('panel', 'brick', 'monolith', 'monolith-brick', 'block', 'wood', 'stalin', 'khrushchev') DEFAULT NULL");
CALL add_column_if_not_exists('properties', 'build_year', 'SMALLINT UNSIGNED DEFAULT NULL');
CALL add_column_if_not_exists('properties', 'ceiling_height', 'DECIMAL(3,2) DEFAULT NULL');
CALL add_column_if_not_exists('properties', 'has_elevator', 'TINYINT(1) DEFAULT NULL');
CALL add_column_if_not_exists('properties', 'has_garbage_chute', 'TINYINT(1) DEFAULT NULL');
CALL add_column_if_not_exists('properties', 'is_new_building', 'TINYINT(1) DEFAULT 0');
CALL add_column_if_not_exists('properties', 'metro_station', 'VARCHAR(100) DEFAULT NULL');
CALL add_column_if_not_exists('properties', 'metro_minutes', 'TINYINT UNSIGNED DEFAULT NULL');
CALL add_column_if_not_exists('properties', 'metro_walk_type', "ENUM('walk', 'transport') DEFAULT 'walk'");
CALL add_column_if_not_exists('properties', 'transport_info', 'TEXT DEFAULT NULL');
CALL add_column_if_not_exists('properties', 'rent_deposit_months', 'TINYINT UNSIGNED DEFAULT 1');
CALL add_column_if_not_exists('properties', 'rent_commission_type', "ENUM('owner', 'tenant', 'shared', 'no-commission') DEFAULT NULL");
CALL add_column_if_not_exists('properties', 'min_rent_period', 'TINYINT UNSIGNED DEFAULT NULL');
CALL add_column_if_not_exists('properties', 'utilities_included', 'TINYINT(1) DEFAULT 0');
CALL add_column_if_not_exists('properties', 'pets_allowed', 'TINYINT(1) DEFAULT 0');
CALL add_column_if_not_exists('properties', 'children_allowed', 'TINYINT(1) DEFAULT 1');

-- Удаляем процедуру после использования
DROP PROCEDURE IF EXISTS add_column_if_not_exists;

-- ============================================
-- 3. ТАБЛИЦА НОВОСТРОЕК (ЖК)
-- ============================================

CREATE TABLE IF NOT EXISTS `new_buildings` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(200) NOT NULL,
  `name_ru` VARCHAR(200) DEFAULT NULL,
  `developer_id` INT UNSIGNED DEFAULT NULL,
  `district_id` INT UNSIGNED DEFAULT NULL,
  `address` VARCHAR(300) NOT NULL,
  `description` TEXT,
  `description_ru` TEXT,
  
  -- Сроки строительства
  `construction_start` DATE DEFAULT NULL,
  `completion_date` VARCHAR(50) DEFAULT NULL COMMENT 'Квартал и год, например "4 квартал 2025"',
  `construction_status` ENUM('project', 'foundation', 'walls', 'finishing', 'completed') DEFAULT 'project',
  
  -- Ценовой диапазон
  `min_price` DECIMAL(15,2) DEFAULT NULL,
  `max_price` DECIMAL(15,2) DEFAULT NULL,
  `price_per_sqm` DECIMAL(10,2) DEFAULT NULL,
  
  -- Характеристики квартир в ЖК
  `min_area` DECIMAL(10,2) DEFAULT NULL,
  `max_area` DECIMAL(10,2) DEFAULT NULL,
  `rooms_available` VARCHAR(50) DEFAULT NULL COMMENT 'Например "Студия, 1, 2, 3"',
  
  -- О доме
  `building_material` ENUM('monolith', 'brick', 'monolith-brick', 'panel', 'block') DEFAULT NULL,
  `building_class` ENUM('economy', 'comfort', 'comfort-plus', 'business', 'premium', 'elite') DEFAULT 'comfort',
  `total_floors_min` TINYINT UNSIGNED DEFAULT NULL,
  `total_floors_max` TINYINT UNSIGNED DEFAULT NULL,
  `total_buildings` TINYINT UNSIGNED DEFAULT 1,
  `total_apartments` SMALLINT UNSIGNED DEFAULT NULL,
  
  -- Отделка
  `finish_type` ENUM('rough', 'pre-finish', 'turnkey', 'designer', 'optional') DEFAULT NULL,
  
  -- Инфраструктура
  `has_parking` TINYINT(1) DEFAULT 0,
  `has_playground` TINYINT(1) DEFAULT 0,
  `has_sports_ground` TINYINT(1) DEFAULT 0,
  `has_security` TINYINT(1) DEFAULT 0,
  `has_concierge` TINYINT(1) DEFAULT 0,
  `infrastructure_description` TEXT,
  
  -- Преимущества и описание района
  `advantages_description` TEXT,
  `district_description` TEXT,
  
  -- Условия покупки
  `mortgage_available` TINYINT(1) DEFAULT 1,
  `installment_available` TINYINT(1) DEFAULT 0,
  `military_mortgage` TINYINT(1) DEFAULT 0,
  `maternity_capital` TINYINT(1) DEFAULT 1,
  `purchase_conditions` TEXT,
  
  -- Документы и контакты
  `construction_progress_url` VARCHAR(500) DEFAULT NULL,
  `project_declaration_url` VARCHAR(500) DEFAULT NULL,
  `sales_office_phone` VARCHAR(20) DEFAULT NULL,
  `sales_office_email` VARCHAR(255) DEFAULT NULL,
  `sales_office_address` VARCHAR(300) DEFAULT NULL,
  
  -- Карта
  `latitude` DECIMAL(10,8) DEFAULT NULL,
  `longitude` DECIMAL(11,8) DEFAULT NULL,
  
  -- SEO и статус
  `featured` TINYINT(1) DEFAULT 0,
  `status` ENUM('active', 'completed', 'suspended', 'cancelled') DEFAULT 'active',
  `views_count` INT UNSIGNED DEFAULT 0,
  
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`developer_id`) REFERENCES `developers`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`district_id`) REFERENCES `ekb_districts`(`id`) ON DELETE SET NULL,
  INDEX `idx_status` (`status`),
  INDEX `idx_featured` (`featured`),
  INDEX `idx_completion` (`completion_date`),
  FULLTEXT INDEX `idx_search` (`name`, `name_ru`, `address`, `description`, `description_ru`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Планировки в ЖК
CREATE TABLE IF NOT EXISTS `new_building_layouts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `new_building_id` INT UNSIGNED NOT NULL,
  `rooms` TINYINT UNSIGNED NOT NULL COMMENT 'Количество комнат (0 = студия)',
  `area_total` DECIMAL(10,2) NOT NULL,
  `price` DECIMAL(15,2) DEFAULT NULL,
  `floor_min` TINYINT UNSIGNED DEFAULT NULL,
  `floor_max` TINYINT UNSIGNED DEFAULT NULL,
  `layout_image` VARCHAR(500) DEFAULT NULL,
  `is_available` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`new_building_id`) REFERENCES `new_buildings`(`id`) ON DELETE CASCADE,
  INDEX `idx_building_rooms` (`new_building_id`, `rooms`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Изображения ЖК
CREATE TABLE IF NOT EXISTS `new_building_images` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `new_building_id` INT UNSIGNED NOT NULL,
  `image_url` VARCHAR(500) NOT NULL,
  `image_type` ENUM('exterior', 'interior', 'infrastructure', 'construction', 'visualization') DEFAULT 'exterior',
  `is_primary` TINYINT(1) DEFAULT 0,
  `sort_order` TINYINT UNSIGNED DEFAULT 0,
  `alt_text` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`new_building_id`) REFERENCES `new_buildings`(`id`) ON DELETE CASCADE,
  INDEX `idx_building` (`new_building_id`),
  INDEX `idx_primary` (`is_primary`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4. ИНДЕКСЫ ДЛЯ ПРОИЗВОДИТЕЛЬНОСТИ
-- ============================================

-- Процедура для безопасного удаления индекса
DELIMITER $$

DROP PROCEDURE IF EXISTS drop_index_if_exists$$
CREATE PROCEDURE drop_index_if_exists(
    IN table_name VARCHAR(128),
    IN index_name VARCHAR(128)
)
BEGIN
    IF EXISTS(
        SELECT * FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = table_name
        AND INDEX_NAME = index_name
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE `', table_name, '` DROP INDEX `', index_name, '`');
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

DELIMITER ;

-- Удаляем старые индексы если существуют
CALL drop_index_if_exists('properties', 'idx_prop_category_status');
CALL drop_index_if_exists('properties', 'idx_prop_category_price');
CALL drop_index_if_exists('properties', 'idx_prop_rooms_area');

-- Создаём новые индексы
ALTER TABLE `properties` ADD INDEX `idx_prop_category_status` (`category`, `status`);
ALTER TABLE `properties` ADD INDEX `idx_prop_category_price` (`category`, `price`);
ALTER TABLE `properties` ADD INDEX `idx_prop_rooms_area` (`bedrooms`, `area_total`);

-- Удаляем процедуру после использования
DROP PROCEDURE IF EXISTS drop_index_if_exists;

-- ============================================
-- ЗАВЕРШЕНИЕ МИГРАЦИИ
-- ============================================

SELECT 'Миграция для Екатеринбурга успешно выполнена!' AS status;

