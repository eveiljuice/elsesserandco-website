-- ============================================================
-- 020_auth_extensions.sql
-- Добавляет в users поля для:
--   - email verification (#2)
--   - OAuth (VK / Yandex / Google)
--   - Telegram Login (#20)
-- Создаёт таблицу password_resets для #1.
-- ============================================================

USE `realestate_db`;

-- ---------- USERS: новые поля ----------
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `email_verified_at` DATETIME NULL DEFAULT NULL AFTER `is_active`,
    ADD COLUMN IF NOT EXISTS `email_verification_token` VARCHAR(64) NULL DEFAULT NULL AFTER `email_verified_at`,
    ADD COLUMN IF NOT EXISTS `email_verification_expires_at` DATETIME NULL DEFAULT NULL AFTER `email_verification_token`,
    ADD COLUMN IF NOT EXISTS `oauth_provider` ENUM('vk','yandex','google','telegram') NULL DEFAULT NULL AFTER `email_verification_expires_at`,
    ADD COLUMN IF NOT EXISTS `oauth_id` VARCHAR(64) NULL DEFAULT NULL AFTER `oauth_provider`,
    ADD COLUMN IF NOT EXISTS `telegram_id` BIGINT NULL DEFAULT NULL AFTER `oauth_id`,
    ADD COLUMN IF NOT EXISTS `telegram_username` VARCHAR(64) NULL DEFAULT NULL AFTER `telegram_id`,
    ADD COLUMN IF NOT EXISTS `failed_login_attempts` SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER `telegram_username`,
    ADD COLUMN IF NOT EXISTS `locked_until` DATETIME NULL DEFAULT NULL AFTER `failed_login_attempts`;

-- Уникальная пара (provider, oauth_id), чтобы один аккаунт VK не привязался к двум юзерам
ALTER TABLE `users`
    ADD UNIQUE KEY IF NOT EXISTS `uniq_oauth_provider_id` (`oauth_provider`, `oauth_id`),
    ADD UNIQUE KEY IF NOT EXISTS `uniq_telegram_id` (`telegram_id`),
    ADD INDEX IF NOT EXISTS `idx_email_verification_token` (`email_verification_token`);

-- Если в БД были аккаунты со стандартной паролевой регистрацией — пометить их верифицированными
-- (чтобы не сломать существующий flow). Раскомментируйте на старой БД с реальными юзерами:
-- UPDATE `users` SET `email_verified_at` = `created_at` WHERE `email_verified_at` IS NULL;

-- ---------- PASSWORD RESETS ----------
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
