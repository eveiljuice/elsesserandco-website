-- ============================================================
--  Migration: FULLTEXT indexes for property search
--  Engine:    MySQL 8.0 (InnoDB, plain FULLTEXT — no ngram)
--  Date:      2026-06-12
--  Author:    Elsesser & Co.
-- ============================================================
--
-- WHY
--  Replace slow LIKE '%q%' queries on properties / districts / new_buildings
--  with native MySQL FULLTEXT (MATCH ... AGAINST ... IN BOOLEAN MODE).
--  Speedup: 10-100x on tables >10k rows. Adds relevance ranking.
--
-- HOW TO APPLY
--  1. Backup the database first:
--       mysqldump -u<user> -p<pass> <dbname> > backup_before_fulltext.sql
--  2. Apply this file via phpMyAdmin (Import tab) or:
--       mysql -u<user> -p<pass> <dbname> < 2026_06_12_fulltext_search.sql
--  3. Verify:
--       SHOW INDEX FROM properties WHERE Key_name = 'ft_search';
--  4. Code changes required (will follow in a separate patch):
--       - includes/properties/catalog_query.php  (LIKE -> MATCH AGAINST)
--       - php/search/autocomplete.php            (LIKE -> MATCH AGAINST)
--
-- SAFE TO RE-RUN
--  All ALTER statements use guard checks (column existence + index existence
--  via information_schema) so the script can be executed multiple times
--  without errors. Columns that don't exist in the current schema are
--  silently skipped from the index — only existing ones are used.
--
-- INDEX SIZE NOTE
--  Each FULLTEXT index on InnoDB lives inside the table's tablespace.
--  Expect ~5-15% increase in `properties` table size. For 50k rows
--  this is ~10-30 MB extra disk. Rebuild time: ~30-90 seconds.
--
-- ROLLBACK
--  ALTER TABLE properties     DROP INDEX ft_search;
--  ALTER TABLE ekb_districts  DROP INDEX ft_district;
--  ALTER TABLE new_buildings  DROP INDEX ft_nb;
-- ============================================================


-- ------------------------------------------------------------
-- Helper procedure: drop index if it exists
-- ------------------------------------------------------------
DROP PROCEDURE IF EXISTS drop_index_if_exists;
DELIMITER //
CREATE PROCEDURE drop_index_if_exists(
    IN p_table VARCHAR(128),
    IN p_index VARCHAR(128)
)
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name   = p_table
          AND index_name   = p_index
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE ', p_table, ' DROP INDEX ', p_index);
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END //
DELIMITER ;


-- ------------------------------------------------------------
-- Helper procedure: build a FULLTEXT index on the intersection
-- of a candidate column list and columns that actually exist in
-- the target table. Skips silently if none match.
-- ------------------------------------------------------------
DROP PROCEDURE IF EXISTS add_fulltext_if_columns_exist;
DELIMITER //
CREATE PROCEDURE add_fulltext_if_columns_exist(
    IN p_table VARCHAR(128),
    IN p_index VARCHAR(128),
    IN p_candidates TEXT             -- comma-separated candidate columns
)
BEGIN
    DECLARE v_existing TEXT DEFAULT '';
    DECLARE v_part VARCHAR(128);
    DECLARE v_rest TEXT;
    DECLARE v_count INT DEFAULT 0;
    DECLARE v_pos INT;

    -- Iterate candidate list, collect those that exist in the table
    SET v_rest = p_candidates;
    WHILE v_rest IS NOT NULL AND v_rest <> '' DO
        SET v_pos = LOCATE(',', v_rest);
        IF v_pos = 0 THEN
            SET v_part = TRIM(v_rest);
            SET v_rest = NULL;
        ELSE
            SET v_part = TRIM(SUBSTRING(v_rest, 1, v_pos - 1));
            SET v_rest = TRIM(SUBSTRING(v_rest, v_pos + 1));
        END IF;

        IF v_part <> '' AND EXISTS (
            SELECT 1 FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name   = p_table
              AND column_name  = v_part
        ) THEN
            IF v_existing = '' THEN
                SET v_existing = v_part;
            ELSE
                SET v_existing = CONCAT(v_existing, ',', v_part);
            END IF;
            SET v_count = v_count + 1;
        END IF;
    END WHILE;

    IF v_count = 0 THEN
        -- nothing to index, skip silently
        SELECT CONCAT('SKIP: no matching columns for ', p_table, '.', p_index) AS info;
    ELSE
        -- drop old index if any, then add the new one
        CALL drop_index_if_exists(p_table, p_index);
        SET @ddl = CONCAT('ALTER TABLE ', p_table,
                          ' ADD FULLTEXT INDEX ', p_index,
                          ' (', v_existing, ')');
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        SELECT CONCAT('OK: ', p_table, '.', p_index, ' on (', v_existing, ')') AS info;
    END IF;
END //
DELIMITER ;


-- ------------------------------------------------------------
-- 1. properties — main search index
--    Candidates: title_ru, street, location, building_name, description_ru, title, description
--    Only the columns that actually exist will be included.
-- ------------------------------------------------------------
CALL add_fulltext_if_columns_exist(
    'properties',
    'ft_search',
    'title_ru,street,location,building_name,description_ru,title,description'
);


-- ------------------------------------------------------------
-- 2. ekb_districts — district autocomplete
--    Candidates: name, name_ru  (name_ru is not in the base schema;
--    if you've added it later, it'll be picked up automatically)
-- ------------------------------------------------------------
CALL add_fulltext_if_columns_exist(
    'ekb_districts',
    'ft_district',
    'name,name_ru'
);


-- ------------------------------------------------------------
-- 3. new_buildings — ЖК search
--    Candidates: name, address, name_ru, description
-- ------------------------------------------------------------
CALL add_fulltext_if_columns_exist(
    'new_buildings',
    'ft_nb',
    'name,address,name_ru,description'
);


-- ------------------------------------------------------------
-- Cleanup helper procedures
-- ------------------------------------------------------------
DROP PROCEDURE drop_index_if_exists;
DROP PROCEDURE add_fulltext_if_columns_exist;


-- ------------------------------------------------------------
-- Verification queries (run manually if you want to confirm)
-- ------------------------------------------------------------
-- SHOW INDEX FROM properties     WHERE Key_name = 'ft_search';
-- SHOW INDEX FROM ekb_districts  WHERE Key_name = 'ft_district';
-- SHOW INDEX FROM new_buildings  WHERE Key_name = 'ft_nb';
--
-- Smoke test:
-- SELECT id, title_ru,
--        MATCH(title_ru, street, location, building_name)
--        AGAINST ('малышева центр' IN BOOLEAN MODE) AS rel
-- FROM properties
-- WHERE MATCH(title_ru, street, location, building_name)
--       AGAINST ('малышева центр' IN BOOLEAN MODE)
-- ORDER BY rel DESC
-- LIMIT 10;
