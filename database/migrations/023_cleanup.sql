-- 023_cleanup.sql
-- Удаление функционала, который больше не используется:
--   • TOTP / 2FA (поля, индексы, файлы)
--   • Telegram Login (виджет + callback, колонки telegram_id/telegram_username,
--     значение 'telegram' из ENUM oauth_provider)
-- Регистрация developer/agency самостоятельно — закрыта в коде, в БД не трогаем.
-- VK / Yandex / Google OAuth — остаются.

USE `realestate_db`;

-- 1. Удалить уникальный индекс по telegram_id (нужно ДО удаления колонки)
ALTER TABLE `users` DROP INDEX `uniq_telegram_id`;

-- 2. Удалить колонки 2FA / Telegram
ALTER TABLE `users` DROP COLUMN `totp_secret`;
ALTER TABLE `users` DROP COLUMN `totp_enabled_at`;
ALTER TABLE `users` DROP COLUMN `telegram_id`;
ALTER TABLE `users` DROP COLUMN `telegram_username`;

-- 3. Убрать значение 'telegram' из ENUM oauth_provider.
-- MySQL 8.0 не поддерживает DROP VALUE напрямую — пересоздаём колонку.
-- Перед пересозданием снимаем UNIQUE KEY uniq_oauth_provider_id, иначе DROP COLUMN упадёт.
ALTER TABLE `users` DROP INDEX `uniq_oauth_provider_id`;

ALTER TABLE `users`
    MODIFY COLUMN `oauth_provider` ENUM('vk','yandex','google') NULL DEFAULT NULL;

-- 4. Вернуть уникальный ключ на (oauth_provider, oauth_id)
ALTER TABLE `users`
    ADD UNIQUE KEY `uniq_oauth_provider_id` (`oauth_provider`, `oauth_id`);
