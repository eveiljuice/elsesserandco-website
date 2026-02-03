-- Active: 1765175374523@@127.0.0.1@3306@realestate_db
-- ============================================
-- Fix Search Index for Russian Content
-- Добавляет title_ru, description_ru и community в полнотекстовый индекс
-- ============================================
-- 
-- ПРОБЛЕМА: Поиск не находил объекты, где:
-- - Название заполнено только на русском (title_ru)
-- - Описание только на русском (description_ru)  
-- - Название здания (building_name) не индексировалось
-- 
-- РЕШЕНИЕ: Расширенный FULLTEXT индекс для всех поисковых полей
-- 
-- ПРИМЕНЕНИЕ:
-- 1. Через phpMyAdmin: скопировать и выполнить в SQL вкладке
-- 2. Через консоль: mysql -u root realestate_db < fix_search_index.sql
-- 3. Через OSPanel: Дополнительно → MySQL консоль → SOURCE путь/к/файлу
-- 
-- ПРОВЕРКА: После применения протестировать поиск на properties.php
-- ============================================

USE `realestate_db`;

-- Удаляем старый индекс (только title, description, location)
ALTER TABLE `properties` DROP INDEX `idx_search`;

-- Создаем новый расширенный индекс с русскими полями
ALTER TABLE `properties` ADD FULLTEXT INDEX `idx_search` 
(`title`, `title_ru`, `description`, `description_ru`, `location`, `community`);

-- Перестраиваем индекс для оптимизации поиска
OPTIMIZE TABLE `properties`;

-- Готово! Теперь поиск работает по всем полям, включая русские названия

