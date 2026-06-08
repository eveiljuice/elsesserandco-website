-- 022_batch_features.sql (MySQL 8.0 — без IF NOT EXISTS на ADD COLUMN)
USE `realestate_db`;

CREATE TABLE IF NOT EXISTS `saved_searches` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(120) NOT NULL DEFAULT 'Мой поиск',
    `filters_json` JSON NOT NULL,
    `notify_email` TINYINT(1) NOT NULL DEFAULT 1,
    `last_notified_at` DATETIME NULL DEFAULT NULL,
    `last_match_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `saved_search_sent` (
    `saved_search_id` INT UNSIGNED NOT NULL,
    `property_id` INT UNSIGNED NOT NULL,
    `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`saved_search_id`, `property_id`),
    FOREIGN KEY (`saved_search_id`) REFERENCES `saved_searches`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`property_id`) REFERENCES `properties`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `users` ADD COLUMN `totp_secret` VARCHAR(64) NULL DEFAULT NULL AFTER `locked_until`;
ALTER TABLE `users` ADD COLUMN `totp_enabled_at` DATETIME NULL DEFAULT NULL AFTER `totp_secret`;
