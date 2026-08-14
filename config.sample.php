<?php

declare(strict_types=1);

/**
 * FormFlow sample configuration.
 *
 * Copy this file to config.php and fill in your values.
 * config.php is gitignored and must never be committed.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Application
    |--------------------------------------------------------------------------
    */
    'app' => [
        // Display name shown in templates and emails.
        'name' => 'FormFlow',

        // Public base URL (no trailing slash). Used for links and asset URLs.
        'url' => 'http://localhost:8000',

        // Random secret for signing tokens, CSRF, etc. Generate a long random string.
        'secret' => 'CHANGE_ME_TO_A_LONG_RANDOM_STRING',

        // Environment: "local" or "production".
        'env' => 'local',

        // When true, show detailed error messages. Always false in production.
        'debug' => true,

        // Set false for local HTTP dev; true in production (HTTPS).
        'session_secure' => false,

        // Default timezone for date handling.
        'timezone' => 'UTC',

        // Default dashboard language (ISO 639-1).
        'locale' => 'en',

        // PHP date() format for admin displays.
        'date_format' => 'Y-m-d',
    ],

    /*
    |--------------------------------------------------------------------------
    | Database (MySQL via PDO)
    |--------------------------------------------------------------------------
    */
    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'formflow',
        'user' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
        // Optional table prefix, e.g. "ff_"
        'prefix' => '',
    ],

    /*
    |--------------------------------------------------------------------------
    | SMTP (outbound mail — used in later phases)
    |--------------------------------------------------------------------------
    */
    'smtp' => [
        'host' => 'smtp.example.com',
        'port' => 587,
        'username' => 'smtp-user@example.com',
        'password' => 'smtp-password',
        // "tls", "ssl", or "" for none.
        'encryption' => 'tls',
        'from_email' => 'noreply@example.com',
        'from_name' => 'FormFlow',
    ],

    /*
    |--------------------------------------------------------------------------
    | Security (admin-configurable — PRD §5.8)
    |--------------------------------------------------------------------------
    */
    'security' => [
        'recaptcha_site_key' => '',
        'recaptcha_secret_key' => '',
        'rate_limit_per_minute' => 10,
        'ip_allowlist' => [],
        'ip_blocklist' => [],
        'session_timeout_minutes' => 120,
    ],

];
