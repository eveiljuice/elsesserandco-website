-- ============================================
-- Developer Registration System - Elsesser & Co.
-- Система регистрации застройщиков
-- ============================================

USE `realestate_db`;

-- ============================================
-- Таблица заявок на регистрацию застройщиковS
-- ============================================
CREATE TABLE IF NOT EXISTS `developer_applications` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  
  -- Данные об организации
  `company_name` VARCHAR(255) NOT NULL COMMENT 'Название компании (напр. Финпромстрой)',
  `legal_form` ENUM('ooo', 'ip', 'ao', 'zao', 'pao') NOT NULL COMMENT 'Организационно-правовая форма',
  `inn` VARCHAR(12) NOT NULL COMMENT 'ИНН (10 цифр для юрлиц, 12 для ИП)',
  `ogrn` VARCHAR(15) DEFAULT NULL COMMENT 'ОГРН (13 цифр) или ОГРНИП (15 цифр)',
  `legal_address` TEXT NOT NULL COMMENT 'Юридический адрес',
  `actual_address` TEXT DEFAULT NULL COMMENT 'Фактический адрес (если отличается)',
  
  -- Контактные данные
  `contact_person` VARCHAR(150) NOT NULL COMMENT 'ФИО контактного лица',
  `contact_position` VARCHAR(100) DEFAULT NULL COMMENT 'Должность контактного лица',
  `email` VARCHAR(255) NOT NULL COMMENT 'Email компании',
  `phone` VARCHAR(20) NOT NULL COMMENT 'Телефон',
  `website` VARCHAR(255) DEFAULT NULL COMMENT 'Сайт компании',
  
  -- Дополнительная информация
  `description` TEXT DEFAULT NULL COMMENT 'Описание деятельности компании',
  `specialization` SET('residential', 'commercial', 'mixed', 'luxury', 'economy') DEFAULT NULL COMMENT 'Специализация застройщика',
  `years_on_market` TINYINT UNSIGNED DEFAULT NULL COMMENT 'Лет на рынке',
  `completed_projects` SMALLINT UNSIGNED DEFAULT NULL COMMENT 'Количество завершенных проектов',
  
  -- Логотип компании
  `logo` VARCHAR(500) DEFAULT NULL COMMENT 'Путь к файлу логотипа',
  
  -- Документы (пути к файлам)
  `inn_document` VARCHAR(500) DEFAULT NULL COMMENT 'Скан ИНН/свидетельства',
  `registration_document` VARCHAR(500) DEFAULT NULL COMMENT 'Выписка из ЕГРЮЛ/ЕГРИП',
  `license_document` VARCHAR(500) DEFAULT NULL COMMENT 'Лицензия (если есть)',
  
  -- Статус заявки
  `status` ENUM('pending', 'reviewing', 'approved', 'rejected') DEFAULT 'pending',
  `rejection_reason` TEXT DEFAULT NULL COMMENT 'Причина отказа',
  `reviewed_by` INT UNSIGNED DEFAULT NULL COMMENT 'Кто проверил заявку',
  `reviewed_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Когда проверена',
  
  -- Связанный аккаунт (создается после одобрения)
  `user_id` INT UNSIGNED DEFAULT NULL COMMENT 'ID созданного аккаунта застройщика',
  
  -- Временные метки
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  -- Индексы
  UNIQUE KEY `unique_inn` (`inn`),
  UNIQUE KEY `unique_email` (`email`),
  INDEX `idx_status` (`status`),
  INDEX `idx_created` (`created_at`),
  
  FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Расширение таблицы users для застройщиков
-- ============================================

-- Добавляем колонки по одной (игнорируем ошибки если уже существуют)
-- Выполнять каждый запрос отдельно!

-- 1. Добавить is_developer
ALTER TABLE `users` ADD COLUMN `is_developer` TINYINT(1) DEFAULT 0 COMMENT 'Флаг: это застройщик';

-- 2. Добавить developer_application_id
ALTER TABLE `users` ADD COLUMN `developer_application_id` INT UNSIGNED DEFAULT NULL COMMENT 'Связь с заявкой на регистрацию застройщика';

-- 3. Добавить logo для застройщика
ALTER TABLE `users` ADD COLUMN `logo` VARCHAR(500) DEFAULT NULL COMMENT 'Логотип компании застройщика';

-- 4. Индекс для быстрого поиска застройщиков
ALTER TABLE `users` ADD INDEX `idx_developer` (`is_developer`);


