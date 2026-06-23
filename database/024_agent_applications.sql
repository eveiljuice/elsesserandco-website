-- Миграция: заявки на роль агента для частных лиц
-- Дата: 2026-06-22
-- Описание: user с role=user может подать заявку "Хочу стать агентом"
-- без открытия ООО/ИП. Заявка попадает в /admin/agent-applications.php.
-- После одобрения админом у user автоматом ставится role=agent.

CREATE TABLE IF NOT EXISTS agent_applications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(255) NOT NULL,
    region VARCHAR(100) NULL,
    experience_years TINYINT UNSIGNED NULL,
    specialization SET('sale','rent','new_buildings','commercial','country') NULL,
    about TEXT NULL,
    motivation TEXT NULL,
    resume_path VARCHAR(500) NULL,
    resume_filename VARCHAR(255) NULL,
    resume_size INT UNSIGNED NULL,
    status ENUM('pending','reviewing','approved','rejected') NOT NULL DEFAULT 'pending',
    rejection_reason TEXT NULL,
    reviewed_by INT UNSIGNED NULL,
    reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uniq_user (user_id),
    KEY idx_status (status),
    KEY idx_created (created_at),
    KEY idx_reviewed_by (reviewed_by),

    CONSTRAINT fk_agent_app_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_agent_app_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
