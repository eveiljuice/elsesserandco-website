-- =====================================================
-- OpenServer 6.4.0 - Fix Database Charset
-- Elsesser & Co. Real Estate
-- =====================================================
--
-- Этот скрипт исправляет проблемы с кодировкой UTF-8
-- Запускать ТОЛЬКО если видите иероглифы вместо русского текста
--
-- Использование:
-- mysql -u root realestate_db < database/fix_charset.sql
--
-- Или через phpMyAdmin:
-- 1. Откройте базу realestate_db
-- 2. SQL → Вставьте содержимое файла → Вперед
-- =====================================================

-- Установить кодировку для текущей сессии
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- Изменить кодировку базы данных
ALTER DATABASE realestate_db 
CHARACTER SET = utf8mb4 
COLLATE = utf8mb4_unicode_ci;

-- Изменить кодировку таблицы users
ALTER TABLE users 
CONVERT TO CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

-- Изменить кодировку таблицы properties
ALTER TABLE properties 
CONVERT TO CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

-- Изменить кодировку таблицы property_images
ALTER TABLE property_images 
CONVERT TO CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

-- Изменить кодировку таблицы amenities
ALTER TABLE amenities 
CONVERT TO CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

-- Изменить кодировку таблицы property_amenities
ALTER TABLE property_amenities 
CONVERT TO CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

-- Изменить кодировку таблицы favorites
ALTER TABLE favorites 
CONVERT TO CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

-- Изменить кодировку таблицы inquiries
ALTER TABLE inquiries 
CONVERT TO CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

-- Изменить кодировку таблицы messages
ALTER TABLE messages 
CONVERT TO CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

-- Изменить кодировку таблицы reviews
ALTER TABLE reviews 
CONVERT TO CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

-- Изменить кодировку таблицы viewings
ALTER TABLE viewings 
CONVERT TO CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

-- Изменить кодировку таблицы newsletter_subscribers
ALTER TABLE newsletter_subscribers 
CONVERT TO CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

-- Проверить кодировку всех таблиц
SELECT 
    TABLE_NAME,
    TABLE_COLLATION
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'realestate_db'
ORDER BY TABLE_NAME;

SELECT '✅ Кодировка исправлена! Все таблицы теперь используют utf8mb4_unicode_ci' AS result;

