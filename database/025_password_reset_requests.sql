-- Миграция: ручной сброс пароля через админа
-- Дата: 2026-06-22
--
-- Заменяет email-flow с одноразовым токеном на ручное одобрение админом.
-- Пользователь вводит email на /forgot-password.php, заявка падает в таблицу
-- со статусом "pending". Админ одобряет/отклоняет в /admin/password-resets.php.
-- После одобрения пользователь возвращается на /forgot-password.php, вводит
-- email — попадает сразу на /reset-password.php (ввод нового пароля, без токена).
--
-- Защита от повторного использования: при первом успешном сбросе статус
-- переходит в "used". Повторно зайти в форму сброса по этой заявке нельзя.

CREATE TABLE IF NOT EXISTS password_reset_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    email VARCHAR(255) NOT NULL,
    status ENUM('pending','approved','rejected','used') NOT NULL DEFAULT 'pending',
    rejection_reason TEXT NULL,
    reviewed_by INT UNSIGNED NULL,
    reviewed_at TIMESTAMP NULL,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uniq_user_pending (user_id, status),
    KEY idx_status (status),
    KEY idx_created (created_at),
    KEY idx_reviewed_by (reviewed_by),

    CONSTRAINT fk_prr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_prr_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
