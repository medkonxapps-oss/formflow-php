-- Submission endpoint support tables (PRD §5.9 / §6.4 / §6.5 Layer 5)

CREATE TABLE IF NOT EXISTS submission_files (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    submission_id BIGINT UNSIGNED NOT NULL,
    field_name VARCHAR(100) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_path VARCHAR(512) NOT NULL,
    mime_type VARCHAR(127) NOT NULL,
    size INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_submission_files_submission_id (submission_id),
    CONSTRAINT fk_submission_files_submission_id
        FOREIGN KEY (submission_id) REFERENCES submissions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS submission_rate_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    form_id INT UNSIGNED NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rate_log_form_ip_time (form_id, ip_address, created_at),
    KEY idx_rate_log_ip_time (ip_address, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS submission_dedup (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    dedup_hash CHAR(64) NOT NULL,
    submission_id BIGINT UNSIGNED NOT NULL,
    response_json TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_submission_dedup_hash (dedup_hash),
    KEY idx_submission_dedup_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
