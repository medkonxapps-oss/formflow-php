-- View tracking, internal notes, and pipeline status (advanced analytics / inbox)

CREATE TABLE IF NOT EXISTS form_views (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    form_id INT UNSIGNED NOT NULL,
    ip_hash CHAR(64) NULL DEFAULT NULL,
    referrer VARCHAR(2048) NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_form_views_form_created (form_id, created_at),
    CONSTRAINT fk_form_views_form_id
        FOREIGN KEY (form_id) REFERENCES forms (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS submission_notes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    submission_id BIGINT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    body TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_submission_notes_submission (submission_id),
    CONSTRAINT fk_submission_notes_submission
        FOREIGN KEY (submission_id) REFERENCES submissions (id) ON DELETE CASCADE,
    CONSTRAINT fk_submission_notes_user
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
