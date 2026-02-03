-- Обновление валюты с AED на RUB
-- Выполнить в phpMyAdmin или MySQL клиенте

USE `realestate_db`;

-- Обновить валюту во всех существующих объектах
UPDATE `properties` 
SET `currency` = 'RUB' 
WHERE `currency` = 'AED' OR `currency` IS NULL;

-- Проверить результат
SELECT id, title, price, currency 
FROM `properties` 
LIMIT 10;

