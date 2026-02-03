-- =====================================================
-- OpenServer 6.4.0 - Test Database Connection
-- Elsesser & Co. Real Estate
-- =====================================================
-- 
-- Этот скрипт проверяет:
-- 1. Подключение к базе данных
-- 2. Наличие основных таблиц
-- 3. Кодировку UTF-8
-- 4. Версию MySQL
--
-- Использование:
-- 1. Откройте phpMyAdmin в OpenServer
-- 2. Выберите базу realestate_db
-- 3. Перейдите на вкладку "SQL"
-- 4. Вставьте и выполните этот скрипт
-- =====================================================

-- Показать версию MySQL/MariaDB
SELECT VERSION() AS mysql_version;

-- Показать текущую базу данных
SELECT DATABASE() AS current_database;

-- Показать кодировку базы данных
SELECT 
    DEFAULT_CHARACTER_SET_NAME AS charset,
    DEFAULT_COLLATION_NAME AS collation
FROM information_schema.SCHEMATA 
WHERE SCHEMA_NAME = 'realestate_db';

-- Показать список таблиц
SHOW TABLES;

-- Подсчитать количество записей в основных таблицах
SELECT 'users' AS table_name, COUNT(*) AS record_count FROM users
UNION ALL
SELECT 'properties', COUNT(*) FROM properties
UNION ALL
SELECT 'property_images', COUNT(*) FROM property_images
UNION ALL
SELECT 'amenities', COUNT(*) FROM amenities
UNION ALL
SELECT 'favorites', COUNT(*) FROM favorites
UNION ALL
SELECT 'inquiries', COUNT(*) FROM inquiries
UNION ALL
SELECT 'messages', COUNT(*) FROM messages
UNION ALL
SELECT 'reviews', COUNT(*) FROM reviews
UNION ALL
SELECT 'viewings', COUNT(*) FROM viewings
UNION ALL
SELECT 'newsletter_subscribers', COUNT(*) FROM newsletter_subscribers;

-- Проверить тестовые аккаунты
SELECT 
    id,
    name,
    email,
    role,
    created_at
FROM users 
WHERE email IN (
    'admin@elsesserandco.com',
    'agent@elsesserandco.com',
    'user@example.com'
)
ORDER BY role;

-- Проверить, что поля с русским текстом правильно отображаются
SELECT 
    id,
    title_ru,
    location,
    price,
    status
FROM properties 
LIMIT 5;

-- =====================================================
-- Если видите иероглифы вместо русского текста:
-- Запустите миграцию fix_search_index.sql
-- =====================================================

