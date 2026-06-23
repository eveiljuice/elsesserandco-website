-- 026_reviews_reviewer_name.sql
-- Добавляем колонку reviewer_name (имя автора на момент отзыва).
-- add_review.php пишет в неё, без неё INSERT падает с 1054 "Unknown column".
-- Колонка nullable: если оставим null — берём имя из users при показе.

ALTER TABLE `reviews`
    ADD COLUMN `reviewer_name` VARCHAR(150) NULL AFTER `user_id`;