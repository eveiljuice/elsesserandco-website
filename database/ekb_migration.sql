-- ============================================
-- Elsesser & Co. - Миграция для рынка Екатеринбурга
-- Version: 2.0
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

-- ШАГ 1: Расширяем ENUM, добавляя новые значения к старым
ALTER TABLE `properties` 
    MODIFY COLUMN `property_type` ENUM(
        'apartment', 'villa', 'townhouse', 'penthouse', 'studio', 'commercial',
        'room', 'house', 'cottage', 'land', 'parking'
    ) NOT NULL;

-- ШАГ 2: Мигрируем старые значения на новые
UPDATE `properties` SET `property_type` = 'house' WHERE `property_type` = 'villa';
UPDATE `properties` SET `property_type` = 'apartment' WHERE `property_type` = 'penthouse';

-- ШАГ 3: Удаляем старые значения из ENUM
ALTER TABLE `properties` 
    MODIFY COLUMN `property_type` ENUM(
        'apartment', 'studio', 'room', 'house', 'townhouse', 
        'cottage', 'commercial', 'land', 'parking'
    ) NOT NULL;

-- ШАГ 4: Добавляем новые поля
ALTER TABLE `properties` 
    -- Категория (sale/rent/new-building)
    ADD COLUMN `category` ENUM('sale', 'rent', 'new-building') DEFAULT 'sale' AFTER `id`,
    
    -- Район как FK
    ADD COLUMN `district_id` INT UNSIGNED DEFAULT NULL AFTER `community`,
    
    -- Адрес детализированный
    ADD COLUMN `street` VARCHAR(200) DEFAULT NULL AFTER `location`,
    ADD COLUMN `house_number` VARCHAR(20) DEFAULT NULL AFTER `street`,
    
    -- Площади разбивка (в м²)
    ADD COLUMN `area_total` DECIMAL(10,2) DEFAULT NULL AFTER `area_sqft`,
    ADD COLUMN `area_living` DECIMAL(10,2) DEFAULT NULL AFTER `area_total`,
    ADD COLUMN `area_kitchen` DECIMAL(10,2) DEFAULT NULL AFTER `area_living`,
    
    -- Комнаты расширенные
    MODIFY COLUMN `bedrooms` TINYINT UNSIGNED DEFAULT NULL COMMENT 'Количество комнат',
    ADD COLUMN `rooms_type` ENUM('isolated', 'adjacent', 'mixed') DEFAULT NULL AFTER `bedrooms`,
    
    -- Этажность
    MODIFY COLUMN `floor_number` SMALLINT UNSIGNED DEFAULT NULL,
    MODIFY COLUMN `total_floors` SMALLINT UNSIGNED DEFAULT NULL,
    
    -- Состояние квартиры
    ADD COLUMN `renovation` ENUM(
        'designer', 'euro', 'cosmetic', 'needs-repair', 
        'rough-finish', 'pre-finish', 'turnkey'
    ) DEFAULT NULL AFTER `furnished`,
    
    -- Балкон/Лоджия
    ADD COLUMN `balcony` ENUM('balcony', 'loggia', 'both', 'none') DEFAULT NULL AFTER `renovation`,
    ADD COLUMN `balcony_count` TINYINT UNSIGNED DEFAULT 0 AFTER `balcony`,
    
    -- Санузел
    ADD COLUMN `bathroom_type` ENUM('combined', 'separate', 'multiple') DEFAULT NULL AFTER `bathrooms`,
    
    -- Вид из окон
    ADD COLUMN `window_view` ENUM('yard', 'street', 'park', 'river', 'city', 'both') DEFAULT NULL AFTER `balcony_count`,
    
    -- Характеристики дома
    ADD COLUMN `house_type` ENUM(
        'panel', 'brick', 'monolith', 'monolith-brick', 
        'block', 'wood', 'stalin', 'khrushchev'
    ) DEFAULT NULL AFTER `building_name`,
    ADD COLUMN `build_year` SMALLINT UNSIGNED DEFAULT NULL AFTER `house_type`,
    ADD COLUMN `ceiling_height` DECIMAL(3,2) DEFAULT NULL AFTER `build_year`,
    ADD COLUMN `has_elevator` TINYINT(1) DEFAULT NULL AFTER `ceiling_height`,
    ADD COLUMN `has_garbage_chute` TINYINT(1) DEFAULT NULL AFTER `has_elevator`,
    ADD COLUMN `is_new_building` TINYINT(1) DEFAULT 0 AFTER `has_garbage_chute`,
    
    -- Транспортная доступность
    ADD COLUMN `metro_station` VARCHAR(100) DEFAULT NULL AFTER `is_new_building`,
    ADD COLUMN `metro_minutes` TINYINT UNSIGNED DEFAULT NULL AFTER `metro_station`,
    ADD COLUMN `metro_walk_type` ENUM('walk', 'transport') DEFAULT 'walk' AFTER `metro_minutes`,
    ADD COLUMN `transport_info` TEXT DEFAULT NULL AFTER `metro_walk_type`,
    
    -- Аренда специфичные
    ADD COLUMN `rent_period` ENUM('long', 'short', 'daily') DEFAULT 'long' AFTER `transport_info`,
    ADD COLUMN `deposit` DECIMAL(15,2) DEFAULT NULL AFTER `rent_period`,
    ADD COLUMN `utilities_included` TINYINT(1) DEFAULT 0 AFTER `deposit`,
    ADD COLUMN `prepayment_months` TINYINT UNSIGNED DEFAULT 1 AFTER `utilities_included`,
    ADD COLUMN `living_conditions` SET('no_animals', 'no_children', 'families_only', 'couples_only') DEFAULT NULL AFTER `prepayment_months`,
    
    -- SEO/Дополнительно
    ADD COLUMN `video_url` VARCHAR(500) DEFAULT NULL AFTER `longitude`,
    ADD COLUMN `virtual_tour_url` VARCHAR(500) DEFAULT NULL AFTER `video_url`,
    
    ADD CONSTRAINT `fk_district` FOREIGN KEY (`district_id`) REFERENCES `ekb_districts`(`id`) ON DELETE SET NULL,
    ADD INDEX `idx_category` (`category`),
    ADD INDEX `idx_district` (`district_id`),
    ADD INDEX `idx_rooms` (`bedrooms`),
    ADD INDEX `idx_area_total` (`area_total`),
    ADD INDEX `idx_metro` (`metro_station`);

-- Обновляем существующие записи
UPDATE `properties` SET 
    `category` = CASE 
        WHEN `listing_type` = 'rent' THEN 'rent'
        ELSE 'sale'
    END,
    `area_total` = `area_sqft`
WHERE `category` IS NULL;

-- ============================================
-- 3. ТАБЛИЦА НОВОСТРОЕК (ЖК)
-- ============================================

CREATE TABLE IF NOT EXISTS `new_buildings` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  
  -- Основная информация
  `name` VARCHAR(200) NOT NULL COMMENT 'Название ЖК',
  `slug` VARCHAR(200) NOT NULL UNIQUE,
  `developer_id` INT UNSIGNED DEFAULT NULL,
  `district_id` INT UNSIGNED DEFAULT NULL,
  `address` VARCHAR(300) NOT NULL,
  `latitude` DECIMAL(10,8) DEFAULT NULL,
  `longitude` DECIMAL(11,8) DEFAULT NULL,
  
  -- Сроки
  `completion_date` DATE DEFAULT NULL COMMENT 'Дата сдачи',
  `completion_quarter` TINYINT UNSIGNED DEFAULT NULL COMMENT 'Квартал сдачи (1-4)',
  `completion_year` SMALLINT UNSIGNED DEFAULT NULL,
  `is_completed` TINYINT(1) DEFAULT 0,
  `construction_stage` ENUM('project', 'foundation', 'construction', 'finishing', 'completed') DEFAULT 'construction',
  
  -- Цены
  `price_from` DECIMAL(15,2) DEFAULT NULL COMMENT 'Цена от',
  `price_per_sqm_from` DECIMAL(12,2) DEFAULT NULL COMMENT 'Цена за м² от',
  
  -- Характеристики дома
  `house_type` ENUM('panel', 'brick', 'monolith', 'monolith-brick', 'block') DEFAULT 'monolith',
  `floors_min` TINYINT UNSIGNED DEFAULT NULL,
  `floors_max` TINYINT UNSIGNED DEFAULT NULL,
  `sections_count` TINYINT UNSIGNED DEFAULT NULL COMMENT 'Кол-во секций/подъездов',
  `apartments_count` INT UNSIGNED DEFAULT NULL COMMENT 'Кол-во квартир',
  `ceiling_height` DECIMAL(3,2) DEFAULT NULL,
  `parking_type` SET('underground', 'ground', 'multilevel', 'open') DEFAULT NULL,
  `parking_price_from` DECIMAL(15,2) DEFAULT NULL,
  
  -- Отделка
  `finish_type` SET('rough', 'pre-finish', 'white-box', 'turnkey', 'design') DEFAULT NULL COMMENT 'Типы отделки',
  
  -- Площади квартир (диапазоны)
  `area_studio_from` DECIMAL(6,2) DEFAULT NULL,
  `area_studio_to` DECIMAL(6,2) DEFAULT NULL,
  `area_1room_from` DECIMAL(6,2) DEFAULT NULL,
  `area_1room_to` DECIMAL(6,2) DEFAULT NULL,
  `area_2room_from` DECIMAL(6,2) DEFAULT NULL,
  `area_2room_to` DECIMAL(6,2) DEFAULT NULL,
  `area_3room_from` DECIMAL(6,2) DEFAULT NULL,
  `area_3room_to` DECIMAL(6,2) DEFAULT NULL,
  `area_4room_from` DECIMAL(6,2) DEFAULT NULL,
  `area_4room_to` DECIMAL(6,2) DEFAULT NULL,
  
  -- Описания
  `description` TEXT,
  `about_house` TEXT COMMENT 'О доме',
  `about_area` TEXT COMMENT 'О районе',
  `advantages` TEXT COMMENT 'Преимущества (JSON или текст)',
  `purchase_conditions` TEXT COMMENT 'Условия покупки',
  
  -- Инфраструктура
  `infrastructure` TEXT COMMENT 'Инфраструктура ЖК (JSON)',
  `nearby_infrastructure` TEXT COMMENT 'Инфраструктура рядом (JSON)',
  
  -- Транспорт
  `metro_station` VARCHAR(100) DEFAULT NULL,
  `metro_minutes` TINYINT UNSIGNED DEFAULT NULL,
  `transport_info` TEXT,
  
  -- Медиа
  `video_url` VARCHAR(500) DEFAULT NULL,
  `virtual_tour_url` VARCHAR(500) DEFAULT NULL,
  `webcam_url` VARCHAR(500) DEFAULT NULL,
  
  -- Статус
  `status` ENUM('active', 'hidden', 'sold-out') DEFAULT 'active',
  `featured` TINYINT(1) DEFAULT 0,
  `views_count` INT UNSIGNED DEFAULT 0,
  `agent_id` INT UNSIGNED DEFAULT NULL,
  
  -- Timestamps
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`developer_id`) REFERENCES `developers`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`district_id`) REFERENCES `ekb_districts`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`agent_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  
  INDEX `idx_status` (`status`),
  INDEX `idx_completion` (`completion_year`, `completion_quarter`),
  INDEX `idx_price` (`price_from`),
  INDEX `idx_developer` (`developer_id`),
  INDEX `idx_district` (`district_id`),
  INDEX `idx_featured` (`featured`),
  FULLTEXT INDEX `idx_search` (`name`, `address`, `description`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4. ИЗОБРАЖЕНИЯ НОВОСТРОЕК
-- ============================================

CREATE TABLE IF NOT EXISTS `new_building_images` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `new_building_id` INT UNSIGNED NOT NULL,
  `image_url` VARCHAR(500) NOT NULL,
  `image_type` ENUM('exterior', 'interior', 'render', 'plan', 'construction', 'area') DEFAULT 'exterior',
  `is_primary` TINYINT(1) DEFAULT 0,
  `sort_order` TINYINT UNSIGNED DEFAULT 0,
  `alt_text` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`new_building_id`) REFERENCES `new_buildings`(`id`) ON DELETE CASCADE,
  INDEX `idx_building` (`new_building_id`),
  INDEX `idx_type` (`image_type`),
  INDEX `idx_primary` (`is_primary`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 5. ПЛАНИРОВКИ НОВОСТРОЕК
-- ============================================

CREATE TABLE IF NOT EXISTS `new_building_layouts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `new_building_id` INT UNSIGNED NOT NULL,
  `rooms` TINYINT UNSIGNED NOT NULL COMMENT '0 = студия, 1, 2, 3, 4+',
  `area_from` DECIMAL(6,2) NOT NULL,
  `area_to` DECIMAL(6,2) DEFAULT NULL,
  `price_from` DECIMAL(15,2) DEFAULT NULL,
  `floor_from` TINYINT UNSIGNED DEFAULT NULL,
  `floor_to` TINYINT UNSIGNED DEFAULT NULL,
  `available_count` INT UNSIGNED DEFAULT NULL COMMENT 'Кол-во доступных',
  `layout_image` VARCHAR(500) DEFAULT NULL,
  `description` TEXT,
  `is_available` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`new_building_id`) REFERENCES `new_buildings`(`id`) ON DELETE CASCADE,
  INDEX `idx_building` (`new_building_id`),
  INDEX `idx_rooms` (`rooms`),
  INDEX `idx_price` (`price_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 6. ИНФРАСТРУКТУРА (СПРАВОЧНИК)
-- ============================================

CREATE TABLE IF NOT EXISTS `infrastructure_items` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `icon` VARCHAR(50) DEFAULT NULL,
  `category` ENUM('internal', 'external') DEFAULT 'internal',
  `sort_order` TINYINT UNSIGNED DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `infrastructure_items` (`name`, `icon`, `category`, `sort_order`) VALUES
-- Внутренняя инфраструктура ЖК
('Подземный паркинг', 'fa-square-parking', 'internal', 1),
('Детская площадка', 'fa-child', 'internal', 2),
('Спортивная площадка', 'fa-futbol', 'internal', 3),
('Охрана/консьерж', 'fa-shield-alt', 'internal', 4),
('Видеонаблюдение', 'fa-video', 'internal', 5),
('Колясочная', 'fa-baby-carriage', 'internal', 6),
('Закрытый двор', 'fa-lock', 'internal', 7),
('Ландшафтный дизайн', 'fa-tree', 'internal', 8),
('Фитнес-зал', 'fa-dumbbell', 'internal', 9),
('Зона отдыха', 'fa-umbrella-beach', 'internal', 10),
('Велопарковка', 'fa-bicycle', 'internal', 11),
('Коммерческие помещения', 'fa-store', 'internal', 12),
-- Внешняя инфраструктура
('Детский сад', 'fa-school', 'external', 20),
('Школа', 'fa-graduation-cap', 'external', 21),
('Поликлиника', 'fa-hospital', 'external', 22),
('Торговый центр', 'fa-shopping-cart', 'external', 23),
('Супермаркет', 'fa-basket-shopping', 'external', 24),
('Парк', 'fa-tree', 'external', 25),
('Спорткомплекс', 'fa-dumbbell', 'external', 26),
('Остановка транспорта', 'fa-bus', 'external', 27),
('Метро', 'fa-subway', 'external', 28)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Связь новостроек и инфраструктуры
CREATE TABLE IF NOT EXISTS `new_building_infrastructure` (
  `new_building_id` INT UNSIGNED NOT NULL,
  `infrastructure_id` INT UNSIGNED NOT NULL,
  `distance_meters` INT UNSIGNED DEFAULT NULL COMMENT 'Расстояние в метрах',
  PRIMARY KEY (`new_building_id`, `infrastructure_id`),
  FOREIGN KEY (`new_building_id`) REFERENCES `new_buildings`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`infrastructure_id`) REFERENCES `infrastructure_items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 7. ОБНОВЛЕНИЕ УДОБСТВ (AMENITIES) ДЛЯ ЕКБ
-- ============================================

-- Очистка и добавление релевантных для Екатеринбурга
DELETE FROM `amenities`;

INSERT INTO `amenities` (`name`, `name_ru`, `icon`) VALUES
('Balcony', 'Балкон', 'fa-door-open'),
('Loggia', 'Лоджия', 'fa-archway'),
('Air Conditioning', 'Кондиционер', 'fa-snowflake'),
('Built-in Kitchen', 'Встроенная кухня', 'fa-sink'),
('Washing Machine', 'Стиральная машина', 'fa-soap'),
('Dishwasher', 'Посудомоечная машина', 'fa-soap'),
('Refrigerator', 'Холодильник', 'fa-temperature-low'),
('Internet', 'Интернет', 'fa-wifi'),
('TV', 'Телевизор', 'fa-tv'),
('Intercom', 'Домофон', 'fa-phone-volume'),
('Concierge', 'Консьерж', 'fa-concierge-bell'),
('Parking', 'Парковочное место', 'fa-square-parking'),
('Storage', 'Кладовка', 'fa-warehouse'),
('Warm Floors', 'Тёплые полы', 'fa-temperature-high'),
('Furniture', 'Мебель', 'fa-couch'),
('Elevator', 'Лифт', 'fa-elevator'),
('Security', 'Охрана', 'fa-shield-alt'),
('Playground', 'Детская площадка', 'fa-child'),
('Sports Ground', 'Спортплощадка', 'fa-futbol'),
('Closed Yard', 'Закрытый двор', 'fa-lock');

-- ============================================
-- 8. ТЕСТОВЫЕ ДАННЫЕ НОВОСТРОЕК
-- ============================================

INSERT INTO `new_buildings` (
    `name`, `slug`, `developer_id`, `district_id`, `address`,
    `completion_quarter`, `completion_year`, `construction_stage`, `is_completed`,
    `price_from`, `price_per_sqm_from`,
    `house_type`, `floors_min`, `floors_max`, `sections_count`, `apartments_count`,
    `ceiling_height`, `parking_type`, `finish_type`,
    `area_studio_from`, `area_studio_to`, `area_1room_from`, `area_1room_to`,
    `area_2room_from`, `area_2room_to`, `area_3room_from`, `area_3room_to`,
    `description`, `about_house`, `about_area`, `advantages`,
    `metro_station`, `metro_minutes`,
    `status`, `featured`
) VALUES
(
    'ЖК Светлый', 'zhk-svetliy', 1, 5, 'ул. Краснолесья, 123',
    4, 2025, 'construction', 0,
    4500000, 120000,
    'monolith-brick', 16, 25, 4, 800,
    2.70, 'underground,open', 'rough,turnkey',
    24, 32, 35, 45, 50, 70, 75, 100,
    'ЖК Светлый — современный жилой комплекс в развивающемся районе Академический. Удобная транспортная доступность, развитая инфраструктура, продуманные планировки.',
    'Монолитно-кирпичный дом с авторской архитектурой. Высота потолков 2.7 м, панорамное остекление, подземный паркинг.',
    'Академический район — один из самых молодых и динамично развивающихся районов Екатеринбурга. Рядом парк, школы, детские сады.',
    '["Закрытая территория", "Подземный паркинг", "Детские площадки", "Видеонаблюдение", "Близость к метро"]',
    'Ботаническая', 15,
    'active', 1
),
(
    'ЖК Высоцкий Парк', 'zhk-vysotsky-park', 2, 1, 'ул. Малышева, 51',
    2, 2024, 'completed', 1,
    6800000, 180000,
    'monolith', 20, 30, 2, 450,
    3.00, 'underground', 'turnkey,design',
    28, 38, 40, 55, 60, 85, 90, 130,
    'Премиальный жилой комплекс в самом центре Екатеринбурга с видом на городскую набережную. Авторская архитектура, премиальная отделка.',
    'Высотный монолитный дом бизнес-класса. Панорамные окна, умный дом, консьерж-сервис.',
    'Исторический центр города. Шаговая доступность до главных достопримечательностей, ресторанов, бизнес-центров.',
    '["Вид на город", "Консьерж-сервис", "Умный дом", "Премиальная отделка", "Центр города"]',
    'Площадь 1905 года', 5,
    'active', 1
),
(
    'ЖК Парковый Квартал', 'zhk-parkovy-kvartal', 3, 4, 'ул. 8 Марта, 200',
    3, 2026, 'foundation', 0,
    3900000, 105000,
    'monolith-brick', 12, 18, 6, 1200,
    2.65, 'underground,ground', 'pre-finish,turnkey',
    22, 30, 32, 42, 48, 65, 70, 95,
    'Масштабный жилой комплекс комфорт-класса от застройщика Брусника. Качественные материалы, европейские планировки.',
    'Квартальная застройка с собственной инфраструктурой. Закрытые дворы без машин, детские сады и школа на территории.',
    'Ботанический район с развитой инфраструктурой, рядом парк Лесоводов, ТРЦ.',
    '["Квартальная застройка", "Дворы без машин", "Школа на территории", "Рядом парк"]',
    'Ботаническая', 10,
    'active', 0
);

-- Изображения для новостроек
INSERT INTO `new_building_images` (`new_building_id`, `image_url`, `image_type`, `is_primary`, `sort_order`) VALUES
(1, 'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?w=1200', 'render', 1, 0),
(1, 'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?w=1200', 'exterior', 0, 1),
(1, 'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?w=1200', 'interior', 0, 2),
(2, 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?w=1200', 'exterior', 1, 0),
(2, 'https://images.unsplash.com/photo-1600566753086-00f18fb6b3ea?w=1200', 'interior', 0, 1),
(3, 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=1200', 'render', 1, 0);

-- Планировки для новостроек
INSERT INTO `new_building_layouts` (`new_building_id`, `rooms`, `area_from`, `area_to`, `price_from`, `floor_from`, `floor_to`, `available_count`) VALUES
(1, 0, 24, 32, 4500000, 2, 25, 50),
(1, 1, 35, 45, 5500000, 2, 25, 120),
(1, 2, 50, 70, 7200000, 2, 25, 80),
(1, 3, 75, 100, 9500000, 2, 25, 40),
(2, 1, 40, 55, 6800000, 5, 30, 30),
(2, 2, 60, 85, 10800000, 5, 30, 45),
(2, 3, 90, 130, 16200000, 5, 30, 25),
(3, 0, 22, 30, 3900000, 2, 18, 100),
(3, 1, 32, 42, 4800000, 2, 18, 200),
(3, 2, 48, 65, 6500000, 2, 18, 150),
(3, 3, 70, 95, 8500000, 2, 18, 60);

-- ============================================
-- 9. ОБНОВЛЕНИЕ ТЕСТОВЫХ ГОТОВЫХ ОБЪЕКТОВ
-- ============================================

UPDATE `properties` SET
    `category` = 'sale',
    `district_id` = 5,
    `street` = 'ул. Краснолесья',
    `house_number` = '12',
    `area_total` = 85.5,
    `area_living` = 52.0,
    `area_kitchen` = 15.0,
    `rooms_type` = 'isolated',
    `house_type` = 'monolith',
    `build_year` = 2020,
    `ceiling_height` = 2.70,
    `has_elevator` = 1,
    `has_garbage_chute` = 1,
    `renovation` = 'euro',
    `balcony` = 'loggia',
    `balcony_count` = 2,
    `bathroom_type` = 'separate',
    `window_view` = 'park',
    `metro_station` = 'Ботаническая',
    `metro_minutes` = 10,
    `metro_walk_type` = 'walk'
WHERE `id` = 1;

UPDATE `properties` SET
    `category` = 'sale',
    `district_id` = 1,
    `street` = 'ул. Малышева',
    `house_number` = '51',
    `area_total` = 120.0,
    `area_living` = 78.0,
    `area_kitchen` = 20.0,
    `rooms_type` = 'isolated',
    `house_type` = 'monolith',
    `build_year` = 2018,
    `ceiling_height` = 3.00,
    `has_elevator` = 1,
    `has_garbage_chute` = 1,
    `renovation` = 'designer',
    `balcony` = 'loggia',
    `balcony_count` = 1,
    `bathroom_type` = 'multiple',
    `window_view` = 'city',
    `metro_station` = 'Площадь 1905 года',
    `metro_minutes` = 5,
    `metro_walk_type` = 'walk'
WHERE `id` = 2;

-- Обновляем объекты аренды
UPDATE `properties` SET
    `category` = 'rent',
    `rent_period` = 'long',
    `deposit` = 50000,
    `utilities_included` = 0,
    `prepayment_months` = 1
WHERE `listing_type` = 'rent';

-- ============================================
-- 10. ИНДЕКСЫ ДЛЯ ПРОИЗВОДИТЕЛЬНОСТИ
-- ============================================

-- Индексы можно создать вручную позже, если нужно:
-- ALTER TABLE `properties` ADD INDEX `idx_prop_category_status` (`category`, `status`);
-- ALTER TABLE `properties` ADD INDEX `idx_prop_category_price` (`category`, `price`);
-- ALTER TABLE `properties` ADD INDEX `idx_prop_rooms_area` (`bedrooms`, `area_total`);

-- ============================================
-- ЗАВЕРШЕНИЕ МИГРАЦИИ
-- ============================================

SELECT 'Миграция для Екатеринбурга успешно выполнена!' AS status;

