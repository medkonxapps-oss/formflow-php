# Changelog

All notable changes to FormFlow are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-08-14

First public release — self-hosted form backend for shared hosting (PHP + MySQL, no Composer).

### Added

- **Installer wizard** (`/install/`) — requirements check, database setup, SMTP, admin account, site settings
- **Authentication** — login, logout, forgot/reset password, CSRF, session hardening, login rate limiting, audit log
- **Form builder** — CRUD, field types, per-form settings (notifications, spam, CORS, webhooks)
- **Submissions inbox** — filters, bulk actions, CSV export, read/star/spam states
- **Public submission API** (`POST /submit/{slug}`) — HTML + JSON, CORS, honeypot, reCAPTCHA, rate limits, dedup, file uploads
- **Dashboard** — summary cards, 30-day Chart.js chart, cross-form recent activity
- **Analytics** — per-form submissions over time, top referrers
- **Templates** — seven bundled presets (Contact, Newsletter, Job Application, Event RSVP, NPS, Booking, Support Ticket)
- **Settings** (admin) — general, SMTP with encrypted password, security, API keys, DB backup export
- Installer runs `TemplateSeeder` after migrations (bundled templates on fresh install)
- PDO buffered queries fix (installer + migrate reliability on MySQL)

### Security

- PDO prepared statements throughout
- IDOR prevention on forms, submissions, and file downloads
- Blocked direct access to `/core`, `/includes`, `/uploads`, and `config.php`
- Production error handler (no stack traces when `app.debug` is false)

### Known limitations

- Team invite flow (`/invite/{token}`) is a stub (404)
- View-tracking / conversion analytics not yet implemented
- API key authentication for future REST endpoints not yet wired

[1.0.0]: https://github.com/formflow/formflow/releases/tag/v1.0.0
