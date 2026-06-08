-- 020_auth_extensions.sql (MySQL 8.0 — без IF NOT EXISTS на ADD COLUMN/INDEX)
-- Повторный запуск: php database/migrate.php игнорирует «уже существует» (1060/1061).

USE `realestate_db`;

ALTER TABLE `users` ADD COLUMN `email_verified_at` DATETIME NULL DEFAULT NULL AFTER `is_active`;
ALTER TABLE `users` ADD COLUMN `email_verification_token` VARCHAR(64) NULL DEFAULT NULL AFTER `email_verified_at`;
ALTER TABLE `users` ADD COLUMN `email_verification_expires_at` DATETIME NULL DEFAULT NULL AFTER `email_verification_token`;
ALTER TABLE `users` ADD COLUMN `oauth_provider` ENUM('vk','yandex','google','telegram') NULL DEFAULT NULL AFTER `email_verification_expires_at`;
ALTER TABLE `users` ADD COLUMN `oauth_id` VARCHAR(64) NULL DEFAULT NULL AFTER `oauth_provider`;
ALTER TABLE `users` ADD COLUMN `telegram_id` BIGINT NULL DEFAULT NULL AFTER `oauth_id`;
ALTER TABLE `users` ADD COLUMN `telegram_username` VARCHAR(64) NULL DEFAULT NULL AFTER `telegram_id`;
ALTER TABLE `users` ADD COLUMN `failed_login_attempts` SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER `telegram_username`;
ALTER TABLE `users` ADD COLUMN `locked_until` DATETIME NULL DEFAULT NULL AFTER `failed_login_attempts`;

ALTER TABLE `users` ADD UNIQUE KEY `uniq_oauth_provider_id` (`oauth_provider`, `oauth_id`);
ALTER TABLE `users` ADD UNIQUE KEY `uniq_telegram_id` (`telegram_id`);
ALTER TABLE `users` ADD INDEX `idx_email_verification_token` (`email_verification_token`);

CREATE TABLE IF NOT EXISTS `password_resets` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`    INT UNSIGNED NOT NULL,
    `token_hash` CHAR(64) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `used_at`    DATETIME NULL DEFAULT NULL,
    `ip_address` VARCHAR(45) NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_token_hash` (`token_hash`),
    INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
