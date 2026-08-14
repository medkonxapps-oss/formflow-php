-- FormFlow forms & submissions (PRD §6.4)
-- JSON stored as LONGTEXT for broad MySQL/MariaDB compatibility.

CREATE TABLE IF NOT EXISTS forms (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    status ENUM('active', 'paused') NOT NULL DEFAULT 'active',
    fields_json LONGTEXT NOT NULL,
    settings_json LONGTEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_forms_slug (slug),
    KEY idx_forms_user_id (user_id),
    KEY idx_forms_status (status),
    CONSTRAINT fk_forms_user_id
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS submissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    form_id INT UNSIGNED NOT NULL,
    data_json LONGTEXT NOT NULL,
    ip_address VARCHAR(45) NULL DEFAULT NULL,
    user_agent VARCHAR(512) NULL DEFAULT NULL,
    referrer VARCHAR(2048) NULL DEFAULT NULL,
    is_spam TINYINT(1) NOT NULL DEFAULT 0,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    is_starred TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_submissions_form_id (form_id),
    KEY idx_submissions_created_at (created_at),
    KEY idx_submissions_is_spam (is_spam),
    KEY idx_submissions_is_read (is_read),
    KEY idx_submissions_form_created (form_id, created_at),
    CONSTRAINT fk_submissions_form_id
        FOREIGN KEY (form_id) REFERENCES forms (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
