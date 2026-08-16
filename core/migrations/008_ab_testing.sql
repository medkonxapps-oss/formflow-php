-- FormFlow A/B Testing Tables
-- Migration 008: form_variants, form_variant_sessions, form_variant_conversions

CREATE TABLE IF NOT EXISTS form_variants (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    form_id     INT UNSIGNED NOT NULL,
    name        VARCHAR(100) NOT NULL DEFAULT 'Variant',
    is_control  TINYINT(1)  NOT NULL DEFAULT 0,
    traffic_pct TINYINT UNSIGNED NOT NULL DEFAULT 50 COMMENT '0-100 percent of traffic',
    fields_json LONGTEXT     NULL     DEFAULT NULL,
    settings_json LONGTEXT   NULL     DEFAULT NULL,
    created_at  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_fv_form_id (form_id),
    CONSTRAINT fk_fv_form_id
        FOREIGN KEY (form_id) REFERENCES forms (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS form_variant_sessions (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    form_id       INT UNSIGNED NOT NULL,
    variant_id    INT UNSIGNED NOT NULL,
    session_token VARCHAR(64)  NOT NULL,
    ip_hash       VARCHAR(64)  NULL DEFAULT NULL,
    created_at    DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fvs_form_token (form_id, session_token),
    KEY idx_fvs_variant_id (variant_id),
    CONSTRAINT fk_fvs_form_id    FOREIGN KEY (form_id)    REFERENCES forms          (id) ON DELETE CASCADE,
    CONSTRAINT fk_fvs_variant_id FOREIGN KEY (variant_id) REFERENCES form_variants  (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS form_variant_conversions (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    variant_id    INT UNSIGNED NOT NULL,
    submission_id BIGINT UNSIGNED NOT NULL,
    created_at    DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fvc_submission (submission_id),
    KEY idx_fvc_variant_id (variant_id),
    CONSTRAINT fk_fvc_variant_id    FOREIGN KEY (variant_id)    REFERENCES form_variants (id) ON DELETE CASCADE,
    CONSTRAINT fk_fvc_submission_id FOREIGN KEY (submission_id) REFERENCES submissions   (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
