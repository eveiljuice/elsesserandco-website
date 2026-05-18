-- ============================================================
-- 021_web_push_subscriptions.sql
-- Хранение Web Push подписок браузера (фича #15).
-- ============================================================

USE `realestate_db`;

CREATE TABLE IF NOT EXISTS `push_subscriptions` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`     INT UNSIGNED NOT NULL,
    `endpoint`    VARCHAR(500) NOT NULL,
    `p256dh_key`  VARCHAR(255) NOT NULL,
    `auth_key`    VARCHAR(255) NOT NULL,
    `user_agent`  VARCHAR(255) NULL DEFAULT NULL,
    `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `last_seen_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `uniq_endpoint` (`endpoint`(255)),
    INDEX `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
