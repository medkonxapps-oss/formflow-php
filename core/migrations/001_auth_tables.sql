-- FormFlow auth & security tables (PRD §6.4)
-- Run once via installer or: php core/migrate.php

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'editor', 'viewer') NOT NULL DEFAULT 'admin',
    email_verified_at DATETIME NULL DEFAULT NULL,
    totp_secret VARCHAR(255) NULL DEFAULT NULL,
    totp_enabled TINYINT(1) NOT NULL DEFAULT 0,
    failed_login_count INT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL DEFAULT NULL,
    last_login_at DATETIME NULL DEFAULT NULL,
    last_login_ip VARCHAR(45) NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_locked_until (locked_until),
    KEY idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_password_resets_user_id (user_id),
    KEY idx_password_resets_token_hash (token_hash),
    KEY idx_password_resets_expires_at (expires_at),
    CONSTRAINT fk_password_resets_user_id
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_verifications (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_email_verifications_user_id (user_id),
    KEY idx_email_verifications_token_hash (token_hash),
    CONSTRAINT fk_email_verifications_user_id
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invites (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL,
    role ENUM('admin', 'editor', 'viewer') NOT NULL DEFAULT 'editor',
    token_hash VARCHAR(255) NOT NULL,
    invited_by_user_id INT UNSIGNED NOT NULL,
    expires_at DATETIME NOT NULL,
    accepted_at DATETIME NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_invites_email (email),
    KEY idx_invites_token_hash (token_hash),
    KEY idx_invites_invited_by (invited_by_user_id),
    CONSTRAINT fk_invites_invited_by_user_id
        FOREIGN KEY (invited_by_user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS remember_tokens (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_at DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_remember_tokens_user_id (user_id),
    KEY idx_remember_tokens_token_hash (token_hash),
    KEY idx_remember_tokens_expires_at (expires_at),
    CONSTRAINT fk_remember_tokens_user_id
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    identifier VARCHAR(255) NOT NULL,
    identifier_type ENUM('email', 'ip') NOT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(512) NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_login_attempts_identifier (identifier, identifier_type, created_at),
    KEY idx_login_attempts_ip (ip_address, created_at),
    KEY idx_login_attempts_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NULL DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    meta_json JSON NULL,
    ip_address VARCHAR(45) NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_log_user_id (user_id),
    KEY idx_audit_log_action (action),
    KEY idx_audit_log_created_at (created_at),
    CONSTRAINT fk_audit_log_user_id
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
