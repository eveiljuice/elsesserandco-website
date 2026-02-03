-- ============================================
-- Тест поискового функционала
-- ============================================

USE `realestate_db`;

-- Проверка существующего индекса
SHOW INDEX FROM properties WHERE Key_name = 'idx_search';

-- Тестовый поиск по разным полям
-- (замените 'тест' на реальное слово из вашей БД)

-- 1. Поиск по title_ru
SELECT id, title, title_ru, location, community
FROM properties 
WHERE title_ru LIKE '%арсений%'
LIMIT 5;

-- 2. Поиск по community
SELECT id, title, title_ru, location, community
FROM properties 
WHERE community LIKE '%центр%'
LIMIT 5;

-- 3. Поиск по building_name
SELECT id, title, title_ru, building_name, location
FROM properties 
WHERE building_name LIKE '%тауэр%' OR building_name LIKE '%tower%'
LIMIT 5;

-- 4. Полнотекстовый поиск (работает после применения миграции)
SELECT id, title, title_ru, location, community,
       MATCH(title, title_ru, description, description_ru, location, community) 
       AGAINST('арсений' IN NATURAL LANGUAGE MODE) AS relevance
FROM properties 
WHERE MATCH(title, title_ru, description, description_ru, location, community) 
      AGAINST('арсений' IN NATURAL LANGUAGE MODE)
ORDER BY relevance DESC
LIMIT 10;

-- 5. Проверка статистики
SELECT 
    COUNT(*) as total_properties,
    COUNT(CASE WHEN title_ru IS NOT NULL THEN 1 END) as with_title_ru,
    COUNT(CASE WHEN community IS NOT NULL THEN 1 END) as with_community
FROM properties;

