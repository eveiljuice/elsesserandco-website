-- ============================================
-- Elsesser & Co. - Миграция для рынка Екатеринбурга
-- Version: 2.0 (Минимальная - БЕЗ индексов)
-- Для старых версий MySQL
-- ============================================

USE `realestate_db`;

-- Временно отключаем проверку внешних ключей
SET FOREIGN_KEY_CHECKS = 0;

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

TRUNCATE TABLE `ekb_districts`;
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
('Чкаловский', 'chkalovskiy', 20);

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

INSERT IGNORE INTO `developers` (`name`, `description`) VALUES
('УГМК-Застройщик', 'Один из крупнейших застройщиков Екатеринбурга'),
('Атомстройкомплекс', 'Застройщик с 30-летней историей'),
('Брусника', 'Девелопер комфорт-класса'),
('Группа ЛСР', 'Федеральный застройщик'),
('Синара-Девелопмент', 'Застройщик премиальной недвижимости'),
('Форум-групп', 'Крупный уральский девелопер'),
('ЮИТ', 'Финский застройщик'),
('Prinzip', 'Застройщик элитной недвижимости'),
('TEN Девелопмент', 'Застройщик комфорт и бизнес-класса'),
('NOVA-Building', 'Современный застройщик');

-- ============================================
-- 2. ТАБЛИЦА НОВОСТРОЕК (ЖК)
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
  `completion_date` VARCHAR(50) DEFAULT NULL COMMENT 'Квартал и год',
  `construction_status` ENUM('project', 'foundation', 'walls', 'finishing', 'completed') DEFAULT 'project',
  
  -- Ценовой диапазон
  `min_price` DECIMAL(15,2) DEFAULT NULL,
  `max_price` DECIMAL(15,2) DEFAULT NULL,
  `price_per_sqm` DECIMAL(10,2) DEFAULT NULL,
  
  -- Характеристики квартир
  `min_area` DECIMAL(10,2) DEFAULT NULL,
  `max_area` DECIMAL(10,2) DEFAULT NULL,
  `rooms_available` VARCHAR(50) DEFAULT NULL,
  
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
  
  -- Описания
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
  
  -- SEO
  `featured` TINYINT(1) DEFAULT 0,
  `status` ENUM('active', 'completed', 'suspended', 'cancelled') DEFAULT 'active',
  `views_count` INT UNSIGNED DEFAULT 0,
  
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`developer_id`) REFERENCES `developers`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`district_id`) REFERENCES `ekb_districts`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Планировки в ЖК
CREATE TABLE IF NOT EXISTS `new_building_layouts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `new_building_id` INT UNSIGNED NOT NULL,
  `rooms` TINYINT UNSIGNED NOT NULL,
  `area_total` DECIMAL(10,2) NOT NULL,
  `price` DECIMAL(15,2) DEFAULT NULL,
  `floor_min` TINYINT UNSIGNED DEFAULT NULL,
  `floor_max` TINYINT UNSIGNED DEFAULT NULL,
  `layout_image` VARCHAR(500) DEFAULT NULL,
  `is_available` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`new_building_id`) REFERENCES `new_buildings`(`id`) ON DELETE CASCADE
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
  FOREIGN KEY (`new_building_id`) REFERENCES `new_buildings`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Включаем обратно проверку внешних ключей
SET FOREIGN_KEY_CHECKS = 1;

SELECT 'Миграция завершена! Теперь запустите ekb_add_columns.sql для добавления полей в properties' AS status;

