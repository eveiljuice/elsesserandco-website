-- ================================================
-- Обновление валюты с AED на RUB
-- Выполнить в phpMyAdmin: http://localhost/openserver/?phpMyAdmin
-- ================================================

USE `realestate_db`;

-- Обновить валюту во всех существующих объектах недвижимости
UPDATE `properties` 
SET `currency` = 'RUB' 
WHERE `currency` = 'AED' OR `currency` IS NULL;

-- Проверить результат (покажет первые 10 объектов)
SELECT id, title, price, currency, location
FROM `properties` 
LIMIT 10;

-- Показать статистику по валютам
SELECT currency, COUNT(*) as count
FROM `properties`
GROUP BY currency;


