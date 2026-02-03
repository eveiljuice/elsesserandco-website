-- Исправление процедуры add_column_if_not_exists для безопасного импорта
-- Решает проблему #1304 (PROCEDURE already exists)
-- Использует правильный синтаксис DELIMITER для phpMyAdmin

DELIMITER //

-- Удаляем процедуру если существует (предотвращает ошибку #1304)
DROP PROCEDURE IF EXISTS add_column_if_not_exists//

-- Создаем процедуру с правильным синтаксисом
CREATE DEFINER=`root`@`%` PROCEDURE `add_column_if_not_exists` (
    IN `p_table_name` VARCHAR(64), 
    IN `p_column_name` VARCHAR(64), 
    IN `p_column_definition` TEXT
) 
BEGIN 
    DECLARE column_count INT; 
    
    -- Проверяем существует ли колонка
    SELECT COUNT(*) INTO column_count 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = p_table_name 
      AND COLUMN_NAME = p_column_name; 
    
    -- Добавляем колонку только если не существует
    IF column_count = 0 THEN 
        SET @sql = CONCAT('ALTER TABLE `', p_table_name, '` ADD COLUMN `', p_column_name, '` ', p_column_definition); 
        PREPARE stmt FROM @sql; 
        EXECUTE stmt; 
        DEALLOCATE PREPARE stmt; 
    END IF; 
END//

-- Восстанавливаем стандартный разделитель
DELIMITER ;










