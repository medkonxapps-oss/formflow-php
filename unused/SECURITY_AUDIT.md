# FormFlow Security Audit

**Date:** 2026-08-14  
**Scope:** Phases 0–5 (installer, auth, forms, submissions, public endpoint, dashboard, analytics, templates, settings)  
**Auditor:** Automated + manual review during hardening pass

This document is the public trust signal for the FormFlow open-source project. For ongoing control descriptions see also [SECURITY.md](SECURITY.md).

Re-run automated checks:

```bash
php core/run-security-audit.php http://localhost:8000
php core/security-idor-test.php
php core/security-upload-test.php
```

---

## Executive summary

| Area | Result |
|------|--------|
| SQL injection (prepared statements) | **Pass** — all user input bound; see notes |
| Admin authentication / authorization | **Pass** — router middleware + controller `requireRole()` |
| IDOR (cross-account access) | **Pass** — manual matrix below |
| XSS (output encoding) | **Pass** — `e()` used on user/submission content |
| CSRF (state-changing POST) | **Pass** — verified on all admin/auth POST handlers |
| Sensitive path exposure | **Pass** (after fixes) — `/core`, `/includes`, `/uploads`, `config.php` return 403 |
| Error disclosure | **Pass** (after fixes) — `ErrorHandler` hides details when `app.debug` is false |
| Session cookies | **Pass** — HttpOnly, SameSite=Strict; Secure when configured |
| Rate limiting | **Pass** — login backoff + submit 429 confirmed |
| File uploads | **Pass** — finfo MIME, allow-list, PHP content scan, extension from MIME |

---

## 1. SQL / prepared statements

**Checked:** Grep of all `query()`, `fetchOne()`, and PDO usage under `/core`.

**Finding:** All user-controlled values use `?` placeholders via `Database::query()`.

**Acceptable exceptions (not user input):**

| Location | Pattern | Mitigation |
|----------|---------|------------|
| `AnalyticsRepository` | `DATE_FORMAT(..., '{$format}')` | `$format` from `match()` whitelist only (`daily`/`weekly`/`monthly`) |
| `StatsRepository`, `LoginActivityRepository`, etc. | `LIMIT {$n}` | `$n` cast with `max()`/`min()` to int |
| `Db::table()` in SQL | `{$tblForms}` etc. | Table names from config prefix only |
| `BackupExporter` | `` `{$table}` `` | Names from `SHOW TABLES` only (admin export) |
| `Migrator` | `pdo()->exec($statement)` | Static migration files only |

**Fixed:** None required for SQL layer.

---

## 2. Admin route authentication

**Checked:** Every route in `core/routes.php` under `/admin/*`.

**Pattern:** Router middleware runs **before** any handler:

- `middleware: ['auth', 'role:viewer|editor|admin']` on all admin GET/POST routes
- `auth` → `Auth::requireAuth()` (redirect to `/login`)
- `role:*` → `Auth::requireRole()` (403 + redirect)

**Controllers** additionally call `requireRole()` / `requireEditor()` / `requireAdmin()` at method entry before touching data:

- `FormController`, `SubmissionController`, `SettingsController`, `SubmissionFileController`

**Public routes (intentionally unauthenticated):** `/submit/{slug}`, `/login`, `/health`, installer.

---

## 3. IDOR test matrix

Two admin accounts created: `idor-a@formflow.test` and `idor-b@formflow.test`.  
User **B** attempts to access resources owned by User **A** via repository layer (same checks used by HTTP views/controllers).

| Resource | Attack | Expected | Result |
|----------|--------|----------|--------|
| Form | `findForUser(formA, userB)` | 404 / null | **BLOCKED** |
| Form list | `listForUser(userB)` contains A's form | No | **BLOCKED** |
| Submission | `findForForm(subA, formA, userB)` | null | **BLOCKED** |
| Submission inbox | `listForForm(formA, userB)` | total = 0 | **BLOCKED** |
| File download | SQL join `f.user_id = userB` for A's file | no row | **BLOCKED** |
| API keys | `listForUser(userB)` after key created for A | isolated | **BLOCKED** |

**HTTP verification:** Accessing `/admin/forms/{id}/edit` while logged in as another user redirects (auth) or returns "Form not found" after `findForUser()`.

**Fixed:** None — ownership checks were already in place.

---

## 4. XSS / output encoding

**Checked:** Admin views rendering submission data, form labels, search filters, settings fields.

**Finding:** User/submission-derived values use `e()` (`htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`) in:

- `views/admin/submissions/index.php`, `show.php`
- `views/admin/dashboard.php`
- `views/admin/forms/_form.php` (embed snippets escaped in textarea)
- `SubmissionService` HTML success/error responses

**Alpine/JSON:** Form builder uses `json_encode(..., JSON_HEX_*)` for field defaults.

**Fixed:** None required.

---

## 5. CSRF on state-changing POST

| Endpoint | `csrf_field()` in form | `Csrf::verifyRequest()` in controller |
|----------|------------------------|--------------------------------------|
| `/login`, `/logout`, `/forgot-password`, `/reset-password` | Yes | `AuthController` |
| `/admin/forms` (store/update/duplicate/delete/toggle) | Yes | `FormController` |
| `/admin/forms/submissions/bulk` | Yes | `SubmissionController` |
| `/admin/templates/use` | Yes | `FormController` |
| `/admin/settings/*` POST | Yes | `SettingsController` |
| Installer POST steps | Yes | `InstallController` |

**Not CSRF-protected (by design):** `GET` exports, `POST /submit/{slug}` (public API; CORS + rate limit instead).

---

## 6. Sensitive path blocking

**Tested with HTTP GET** (`php core/run-security-audit.php`):

| Path | Expected | Result |
|------|----------|--------|
| `/core/bootstrap.php` | 403 | **403** |
| `/includes/PHPMailer/...` | 403 | **403** |
| `/config.php` | 403 | **403** |
| `/uploads/.htaccess` | 403 | **403** |

**Fixed during audit:**

1. **`router.php` (dev server)** — Previously served `/core`, `/includes`, `/uploads`, and `config.php` as static files. Now returns **403 Forbidden** before static file handling.
2. **`.htaccess` (Apache)** — Added `RewriteRule ^uploads/ - [F,L]` so uploaded files are only available via authenticated `GET /admin/submissions/{id}/files/{file_id}`.

`uploads/.htaccess` additionally disables PHP execution and denies script extensions.

---

## 7. Error display / logging

**Checked:** `app.debug` in `config.php`; deliberate exception with debug off.

**Fixed during audit:**

- Added `core/ErrorHandler.php` — registers when `FORMFLOW_DEBUG` is false:
  - `display_errors = 0`, `log_errors = 1`
  - Uncaught exceptions → generic HTML 500, details to `error_log` only
  - PHP errors converted to exceptions and logged

**Verified:** CLI test shows HTML body `An unexpected error occurred` with **no** `audit-test` string in the response body.

**Production checklist:** Set `app.debug` to `false` and `app.env` to `production` in `config.php`.

---

## 8. Session cookie flags

**Source:** `core/Session.php`

| Flag | Value |
|------|-------|
| `HttpOnly` | `true` |
| `SameSite` | `Strict` |
| `Secure` | `true` when `app.session_secure` or production HTTPS |
| Session lifetime | From `security.session_timeout_minutes` (default 120 min) |

**Verified:** `Set-Cookie` on `/login` includes `httponly` and `samesite=strict`.

---

## 9. Rate limiting

| Endpoint | Mechanism | Test result |
|----------|-----------|-------------|
| `POST /login` | `login_attempts` table; exponential backoff after 5 failures/hour | **Pass** — 7th attempt returns "Too many failed attempts" |
| `POST /submit/{slug}` | `submission_rate_log`; per-IP + per-form | **Pass** — HTTP **429** after ~10 requests/minute |

---

## 10. File upload security (Phase 4)

**Controls in `FileStorage`:**

1. `is_uploaded_file()` on store path
2. Size limit before move
3. **MIME from `finfo` (FILEINFO_MIME_TYPE)** — not client `Content-Type` or extension
4. Allow-list of MIME types
5. Stored filename = random hex + extension derived from detected MIME (not original name)
6. Original filename sanitized for DB/display

**Fixed during audit:**

- **PHP content scan** — Reject files whose first 8 KB contain `<?php` or `<?=`
- **Download header** — Sanitize `original_filename` in `Content-Disposition`

**Spoof test** (`php core/security-upload-test.php`):

| Test | Result |
|------|--------|
| File named `innocent.jpg.php`, spoofed `image/jpeg` header | **Rejected** — finfo detects `text/x-php` |
| PHP tags in content | **Rejected** — content scan |

---

## Additional fixes (defense in depth)

| Issue | Fix |
|-------|-----|
| Open redirect on validation error | `SubmissionService::safeReferer()` — only same-host referer paths |
| Regex delimiter injection in `FormValidator` | Reject patterns containing `/` |
| SMTP password at rest | Already encrypted via `Crypto` (Phase 5 settings) |

---

## Recommendations (not blockers)

1. **Production:** Use Apache/nginx with `.htaccess` equivalent; do not rely on `php -S` without `router.php`.
2. **HTTPS:** Set `app.session_secure` to `true` so the `Secure` cookie flag is sent.
3. **View tracking:** Conversion rate stub in analytics awaits embed snippet (documented TODO).
4. **API keys:** Generation UI exists; API authentication middleware for future API routes not yet implemented.
5. **Periodic re-audit:** Re-run scripts after major changes.

---

## Files changed in this hardening pass

- `core/ErrorHandler.php` (new)
- `core/bootstrap.php` — register error handler
- `router.php` — block sensitive paths on dev server
- `.htaccess` — block `/uploads/` direct access
- `core/SubmissionService.php` — safe referer redirect
- `core/FileStorage.php` — PHP content scan, filename sanitization
- `core/SubmissionFileController.php` — safe download filename
- `core/FormValidator.php` — regex hardening
- `core/run-security-audit.php`, `core/security-idor-test.php`, `core/security-upload-test.php` (new test harnesses)

---

*Last automated run: **10/10** checks passed via `php core/run-security-audit.php`.*
