-- ============================================
-- Elsesser & Co. Real Estate Database
-- Version: 1.0
-- ============================================

-- Создание базы данных
CREATE DATABASE IF NOT EXISTS `realestate_db` 
DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `realestate_db`;

-- ============================================
-- Таблица пользователей
-- ============================================
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `avatar` VARCHAR(500) DEFAULT NULL,
  `role` ENUM('user', 'agent', 'admin') DEFAULT 'user',
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_email` (`email`),
  INDEX `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Таблица объектов недвижимости
-- ============================================
CREATE TABLE IF NOT EXISTS `properties` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `title_ru` VARCHAR(255) DEFAULT NULL,
  `description` TEXT,
  `description_ru` TEXT,
  `property_type` ENUM('apartment', 'villa', 'townhouse', 'penthouse', 'studio', 'commercial') NOT NULL,
  `listing_type` ENUM('sale', 'rent') NOT NULL,
  `price` DECIMAL(15,2) NOT NULL,
  `currency` VARCHAR(10) DEFAULT 'RUB',
  `location` VARCHAR(255) NOT NULL,
  `community` VARCHAR(100) DEFAULT NULL,
  `building_name` VARCHAR(150) DEFAULT NULL,
  `bedrooms` TINYINT UNSIGNED DEFAULT 0,
  `bathrooms` TINYINT UNSIGNED DEFAULT 0,
  `area_sqft` INT UNSIGNED NOT NULL,
  `floor_number` SMALLINT UNSIGNED DEFAULT NULL,
  `total_floors` SMALLINT UNSIGNED DEFAULT NULL,
  `parking_spaces` TINYINT UNSIGNED DEFAULT 0,
  `furnished` ENUM('furnished', 'semi-furnished', 'unfurnished') DEFAULT 'unfurnished',
  `agent_id` INT UNSIGNED DEFAULT NULL,
  `status` ENUM('available', 'sold', 'rented', 'pending', 'off-market') DEFAULT 'available',
  `featured` TINYINT(1) DEFAULT 0,
  `views_count` INT UNSIGNED DEFAULT 0,
  `latitude` DECIMAL(10,8) DEFAULT NULL,
  `longitude` DECIMAL(11,8) DEFAULT NULL,
  `permit_number` VARCHAR(50) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`agent_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_type` (`property_type`, `listing_type`),
  INDEX `idx_price` (`price`),
  INDEX `idx_status` (`status`),
  INDEX `idx_featured` (`featured`),
  INDEX `idx_location` (`location`),
  INDEX `idx_community` (`community`),
  FULLTEXT INDEX `idx_search` (`title`, `title_ru`, `description`, `description_ru`, `location`, `community`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Таблица изображений недвижимости
-- ============================================
CREATE TABLE IF NOT EXISTS `property_images` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `property_id` INT UNSIGNED NOT NULL,
  `image_url` VARCHAR(500) NOT NULL,
  `is_primary` TINYINT(1) DEFAULT 0,
  `sort_order` TINYINT UNSIGNED DEFAULT 0,
  `alt_text` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`property_id`) REFERENCES `properties`(`id`) ON DELETE CASCADE,
  INDEX `idx_property` (`property_id`),
  INDEX `idx_primary` (`is_primary`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Таблица удобств (amenities)
-- ============================================
CREATE TABLE IF NOT EXISTS `amenities` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `name_ru` VARCHAR(100) DEFAULT NULL,
  `icon` VARCHAR(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Связь объектов и удобств (many-to-many)
-- ============================================
CREATE TABLE IF NOT EXISTS `property_amenities` (
  `property_id` INT UNSIGNED NOT NULL,
  `amenity_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`property_id`, `amenity_id`),
  FOREIGN KEY (`property_id`) REFERENCES `properties`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`amenity_id`) REFERENCES `amenities`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Таблица избранного
-- ============================================
CREATE TABLE IF NOT EXISTS `favorites` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `property_id` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`property_id`) REFERENCES `properties`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_favorite` (`user_id`, `property_id`),
  INDEX `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Таблица запросов/заявок
-- ============================================
CREATE TABLE IF NOT EXISTS `inquiries` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `property_id` INT UNSIGNED DEFAULT NULL,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `message` TEXT,
  `inquiry_type` ENUM('general', 'viewing', 'offer', 'valuation') DEFAULT 'general',
  `status` ENUM('new', 'contacted', 'scheduled', 'completed', 'cancelled') DEFAULT 'new',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`property_id`) REFERENCES `properties`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_status` (`status`),
  INDEX `idx_property` (`property_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Вставка тестовых данных: Удобства
-- ============================================
INSERT INTO `amenities` (`name`, `name_ru`, `icon`) VALUES
('Swimming Pool', 'Бассейн', 'fa-swimming-pool'),
('Gym', 'Спортзал', 'fa-dumbbell'),
('Parking', 'Парковка', 'fa-parking'),
('Security', 'Охрана', 'fa-shield-alt'),
('Balcony', 'Балкон', 'fa-archway'),
('Garden', 'Сад', 'fa-tree'),
('Sea View', 'Вид на море', 'fa-water'),
('City View', 'Вид на город', 'fa-city'),
('Central AC', 'Центральный кондиционер', 'fa-snowflake'),
('Built-in Wardrobes', 'Встроенные шкафы', 'fa-door-closed'),
('Concierge', 'Консьерж', 'fa-concierge-bell'),
('Kids Play Area', 'Детская площадка', 'fa-child'),
('BBQ Area', 'Зона барбекю', 'fa-fire'),
('Private Beach', 'Частный пляж', 'fa-umbrella-beach'),
('Maid Room', 'Комната для прислуги', 'fa-broom');

-- ============================================
-- Вставка тестовых данных: Пользователи
-- Пароль для всех: password123
-- Hash generated via: password_hash('password123', PASSWORD_DEFAULT)
-- ============================================
INSERT INTO `users` (`email`, `password_hash`, `first_name`, `last_name`, `phone`, `role`) VALUES
('admin@elsesserandco.com', '$2y$10$dXJ3SW6G7P50lGmMkkmwe.20cQQubK3.HZWzG3YB1tlRy.fqvM/BG', 'Admin', 'Elsesser', '+971501234567', 'admin'),
('agent@elsesserandco.com', '$2y$10$dXJ3SW6G7P50lGmMkkmwe.20cQQubK3.HZWzG3YB1tlRy.fqvM/BG', 'Александр', 'Иванов', '+971502345678', 'agent'),
('agent2@elsesserandco.com', '$2y$10$dXJ3SW6G7P50lGmMkkmwe.20cQQubK3.HZWzG3YB1tlRy.fqvM/BG', 'Мария', 'Петрова', '+971503456789', 'agent'),
('user@example.com', '$2y$10$dXJ3SW6G7P50lGmMkkmwe.20cQQubK3.HZWzG3YB1tlRy.fqvM/BG', 'Иван', 'Сидоров', '+971504567890', 'user');

-- ============================================
-- Вставка тестовых данных: Объекты недвижимости
-- ============================================
INSERT INTO `properties` (`title`, `title_ru`, `description`, `description_ru`, `property_type`, `listing_type`, `price`, `location`, `community`, `building_name`, `bedrooms`, `bathrooms`, `area_sqft`, `floor_number`, `furnished`, `agent_id`, `status`, `featured`) VALUES
('Luxury Villa with Park View', 'Роскошная вилла с видом на парк', 'Stunning villa with spacious interiors, elegant finishes and an open floor plan ideal for modern living. Located on a green street and just minutes from the clubhouse, this home blends privacy, lifestyle, and convenience beautifully.', 'Потрясающая вилла с просторными интерьерами, элегантной отделкой и открытой планировкой, идеальной для современной жизни.', 'villa', 'sale', 5500000.00, 'Emirates Hills, Dubai', 'Emirates Hills', NULL, 5, 6, 8500, NULL, 'furnished', 2, 'available', 1),

('Modern Apartment with Burj Khalifa View', 'Апартаменты с видом на Бурдж-Халифа', 'Luxurious apartment with breathtaking views of Burj Khalifa and Dubai Fountain. Modern design, premium finishes and first-class amenities.', 'Роскошные апартаменты с захватывающим видом на Бурдж-Халифа и фонтан Дубай. Современный дизайн, премиальная отделка.', 'apartment', 'sale', 3200000.00, 'Downtown Dubai, Dubai', 'Downtown Dubai', 'The Address Downtown', 3, 4, 2850, 45, 'furnished', 2, 'available', 1),

('Waterfront Penthouse', 'Пентхаус на набережной', 'Exclusive penthouse on the waterfront with panoramic views of the marina and Persian Gulf. Spacious terraces, high ceilings and premium finishes.', 'Эксклюзивный пентхаус на набережной с панорамными видами на марину и Персидский залив.', 'penthouse', 'sale', 4800000.00, 'Dubai Marina, Dubai', 'Dubai Marina', 'Marina Tower', 4, 5, 4200, 55, 'furnished', 3, 'available', 1),

('Canal View Apartment', 'Апартаменты с видом на канал', 'Modern apartment with canal views in prestigious JLT complex. Ready for immediate move-in, excellent investment opportunity.', 'Современные апартаменты с видом на канал в престижном комплексе JLT.', 'apartment', 'sale', 2650000.00, 'JLT, Dubai', 'Jumeirah Lake Towers', 'JLT Cluster S', 2, 3, 1657, 28, 'semi-furnished', 2, 'available', 0),

('Premium Business Bay Apartment', 'Элитные апартаменты в Business Bay', 'Elite apartment in one of the most prestigious Business Bay addresses. Canal view, modern design and access to premium amenities.', 'Элитные апартаменты в одном из самых престижных адресов Business Bay.', 'apartment', 'sale', 4400000.00, 'Business Bay, Dubai', 'Business Bay', 'Trillionaire Residences', 2, 3, 1461, 32, 'furnished', 3, 'available', 0),

('Family Villa in The Valley', 'Семейная вилла в The Valley', 'New modern villa from Emaar with spacious interiors and park view. Single row with direct access to green area. Perfect for family.', 'Новая современная вилла от Emaar с просторными интерьерами и видом на парк.', 'villa', 'sale', 3600000.00, 'The Valley, Dubai', 'The Valley', 'Orania', 4, 5, 2252, NULL, 'unfurnished', 2, 'available', 0),

('Creek Harbour Studio', 'Студия в Creek Harbour', 'Stylish apartment with Creek Tower view on high floor. Excellent investment opportunity in fast-growing Dubai area.', 'Стильные апартаменты с видом на Creek Tower на высоком этаже.', 'studio', 'sale', 1850000.00, 'Creek Harbour, Dubai', 'Dubai Creek Harbour', 'Creek Rise', 1, 2, 892, 38, 'semi-furnished', 3, 'available', 0),

('Beachfront Villa Palm Jumeirah', 'Вилла на берегу Palm Jumeirah', 'Luxurious waterfront villa with private beach and jetty. Stunning Dubai skyline views, pool and garden on site.', 'Роскошная вилла на воде с частным пляжем и причалом. Потрясающие виды на горизонт Дубая.', 'villa', 'sale', 7200000.00, 'Palm Jumeirah, Dubai', 'Palm Jumeirah', 'Frond G', 6, 7, 10200, NULL, 'furnished', 2, 'available', 1),

('Cozy Studio for Rent', 'Уютная студия в аренду', 'Well-maintained studio apartment in prime location. Walking distance to metro, shops and restaurants.', 'Ухоженная квартира-студия в отличном месте. Пешая доступность до метро, магазинов и ресторанов.', 'studio', 'rent', 55000.00, 'Dubai Marina, Dubai', 'Dubai Marina', 'Marina Heights', 0, 1, 450, 12, 'furnished', 3, 'available', 0),

('Spacious 3BR for Rent', 'Просторные 3BR в аренду', 'Beautiful 3-bedroom apartment available for rent. Family-friendly community with parks and schools nearby.', 'Красивые 3-комнатные апартаменты в аренду. Семейный район с парками и школами поблизости.', 'apartment', 'rent', 180000.00, 'Arabian Ranches, Dubai', 'Arabian Ranches', 'Savanna', 3, 3, 2100, 2, 'semi-furnished', 2, 'available', 0);

-- ============================================
-- Вставка изображений для объектов
-- ============================================
INSERT INTO `property_images` (`property_id`, `image_url`, `is_primary`, `sort_order`) VALUES
(1, 'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?w=900&q=80', 1, 0),
(1, 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=900&q=80', 0, 1),
(1, 'https://images.unsplash.com/photo-1600047509807-ba8f99d2cdde?w=900&q=80', 0, 2),
(2, 'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?w=900&q=80', 1, 0),
(2, 'https://images.unsplash.com/photo-1600566753190-17f0baa2a6c3?w=900&q=80', 0, 1),
(3, 'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?w=900&q=80', 1, 0),
(3, 'https://images.unsplash.com/photo-1600573472591-ee6c8e695481?w=900&q=80', 0, 1),
(4, 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=900&q=80', 1, 0),
(5, 'https://images.unsplash.com/photo-1600566753190-17f0baa2a6c3?w=900&q=80', 1, 0),
(6, 'https://images.unsplash.com/photo-1600047509807-ba8f99d2cdde?w=900&q=80', 1, 0),
(7, 'https://images.unsplash.com/photo-1600573472556-e636c2acda88?w=900&q=80', 1, 0),
(8, 'https://images.unsplash.com/photo-1600585154526-990dced4db0d?w=900&q=80', 1, 0),
(8, 'https://images.unsplash.com/photo-1578894381163-e72c17f2d45f?w=900&q=80', 0, 1),
(9, 'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?w=900&q=80', 1, 0),
(10, 'https://images.unsplash.com/photo-1600566753086-00f18fb6b3ea?w=900&q=80', 1, 0);

-- ============================================
-- Привязка удобств к объектам
-- ============================================
INSERT INTO `property_amenities` (`property_id`, `amenity_id`) VALUES
(1, 1), (1, 2), (1, 3), (1, 4), (1, 6), (1, 9), (1, 10),
(2, 1), (2, 2), (2, 3), (2, 4), (2, 8), (2, 9), (2, 11),
(3, 1), (3, 2), (3, 3), (3, 4), (3, 5), (3, 7), (3, 9), (3, 11),
(4, 1), (4, 2), (4, 3), (4, 4), (4, 9),
(5, 1), (5, 2), (5, 3), (5, 4), (5, 9), (5, 11),
(6, 3), (6, 4), (6, 6), (6, 9), (6, 12),
(7, 1), (7, 2), (7, 3), (7, 9),
(8, 1), (8, 3), (8, 4), (8, 6), (8, 7), (8, 9), (8, 14), (8, 15),
(9, 1), (9, 2), (9, 3), (9, 9),
(10, 1), (10, 2), (10, 3), (10, 4), (10, 9), (10, 12);

-- ============================================
-- Phase 3: Таблица сообщений чата
-- ============================================
CREATE TABLE IF NOT EXISTS `messages` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `sender_id` INT UNSIGNED NOT NULL,
  `receiver_id` INT UNSIGNED NOT NULL,
  `property_id` INT UNSIGNED DEFAULT NULL,
  `message` TEXT NOT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`property_id`) REFERENCES `properties`(`id`) ON DELETE SET NULL,
  INDEX `idx_conversation` (`sender_id`, `receiver_id`),
  INDEX `idx_receiver` (`receiver_id`),
  INDEX `idx_unread` (`receiver_id`, `is_read`),
  INDEX `idx_property` (`property_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Phase 3: Таблица отзывов и рейтингов
-- ============================================
CREATE TABLE IF NOT EXISTS `reviews` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `property_id` INT UNSIGNED DEFAULT NULL,
  `agent_id` INT UNSIGNED DEFAULT NULL,
  `rating` TINYINT UNSIGNED NOT NULL CHECK (rating BETWEEN 1 AND 5),
  `comment` TEXT,
  `is_approved` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`property_id`) REFERENCES `properties`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`agent_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_property_review` (`user_id`, `property_id`),
  UNIQUE KEY `unique_agent_review` (`user_id`, `agent_id`),
  INDEX `idx_property` (`property_id`),
  INDEX `idx_agent` (`agent_id`),
  INDEX `idx_approved` (`is_approved`),
  INDEX `idx_rating` (`rating`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Phase 3: Таблица подписчиков рассылки
-- ============================================
CREATE TABLE IF NOT EXISTS `newsletter_subscribers` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `preferences` JSON DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `subscribed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `unsubscribed_at` TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_email` (`email`),
  INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Phase 3: Таблица календаря просмотров (viewings)
-- ============================================
CREATE TABLE IF NOT EXISTS `viewings` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `property_id` INT UNSIGNED NOT NULL,
  `agent_id` INT UNSIGNED NOT NULL,
  `client_id` INT UNSIGNED DEFAULT NULL,
  `client_name` VARCHAR(100) NOT NULL,
  `client_phone` VARCHAR(20) DEFAULT NULL,
  `client_email` VARCHAR(255) DEFAULT NULL,
  `viewing_date` DATE NOT NULL,
  `viewing_time` TIME NOT NULL,
  `status` ENUM('scheduled', 'completed', 'cancelled', 'no-show') DEFAULT 'scheduled',
  `notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`property_id`) REFERENCES `properties`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`agent_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`client_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_agent_date` (`agent_id`, `viewing_date`),
  INDEX `idx_property` (`property_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
