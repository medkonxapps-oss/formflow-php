# FormFlow Security Controls

This document summarizes the authentication and security controls implemented in FormFlow (PRD §5.2, §6.5 Layer 2). It is intended for operators and open-source contributors auditing the codebase.

> **Full hardening audit (Phases 0–5):** see [SECURITY_AUDIT.md](SECURITY_AUDIT.md) for the latest check results, IDOR matrix, and fixes applied.

## Account model

- **No public signup.** There is no `/signup` or `/register` route. The first admin is created by the installer (Phase 2). Additional accounts are invite-only (Phase 2; `/invite/{token}` currently returns 404).
- `Auth::register()` exists for **internal use only** (installer, invite acceptance).

## Password storage

- Passwords are hashed with **`password_hash()`** using **Argon2id** when available, falling back to bcrypt (`PASSWORD_DEFAULT`).
- Minimum length: **12 characters** (enforced on register and reset).
- Reset/install UI includes a **zxcvbn** strength meter (length + score, no arbitrary symbol rules).

## Sessions

- PHP session cookie: **`HttpOnly`**, **`Secure`** (configurable via `app.session_secure`), **`SameSite=Strict`**.
- Session ID is **regenerated on every successful login** (session fixation mitigation).
- Session binding via HMAC fingerprint (IP + user agent + app secret).
- Sessions are invalidated when the user's password changes (`updated_at` vs `authenticated_at`).

## Remember me

- Implemented as a **separate cookie** (`formflow_remember`), not extended session lifetime.
- Only a **HMAC hash** of the token is stored in `remember_tokens`.
- Tokens can be **individually revoked** on logout; all tokens revoked on password reset.
- Token rotation on each remember-me login.

## Login brute-force protection

Tracked in `login_attempts` **per email and per IP** separately:

| Threshold | Action |
|-----------|--------|
| 5 failures / hour | Exponential backoff before next attempt is processed |
| 10 failures / hour | Honeypot verification required on login form |
| 15 failures / hour | Account locked (`locked_until`, 1 hour) + email alert to owner |

- Generic error on failure: **"Invalid email or password"** (no account enumeration).
- All attempts (success and failure) logged to `login_attempts` and `audit_log`.

## Password reset

- Generic response regardless of whether the email exists.
- Token: **32 random bytes** (`random_bytes()`), **only SHA-256 HMAC hash stored** (keyed with app secret).
- Expires in **45 minutes**, single-use (`used_at`).
- Prior unused tokens invalidated when a new reset is requested.
- Rate-limited per email and per IP (5 requests/hour).
- On success: **all sessions and remember tokens invalidated**, confirmation email sent, audit entry written.

## CSRF

- Per-session CSRF token (`Csrf` helper).
- Verified on every state-changing auth POST: login, logout, forgot-password, reset-password.
- Token rotated after successful auth actions.

## Authorization middleware

- `requireAuth()` and `requireRole($role)` re-check credentials **on every request** server-side.
- Admin routes use middleware — UI hiding is not relied upon.
- Role hierarchy: `viewer` < `editor` < `admin`.

## Email verification

- Schema includes `email_verifications` table (Phase 2 invite flow).
- Install-created accounts are marked verified immediately; invited accounts must verify before login.

## Audit logging

`audit_log` records security-relevant events including:

- Login success / failure / lockout / remember-me
- Logout
- Password reset request and completion
- User registration (internal)

## Transport & infrastructure (related layers)

- `.htaccess` blocks direct access to `/core`, `/includes`, `config.php`.
- `/uploads` has PHP execution disabled.
- `config.php` is gitignored; sensitive values documented for encryption-at-rest in Phase 2 settings.

## Reporting issues

If you discover a security vulnerability, please report it responsibly via the project's GitHub Security Advisories (or contact the maintainer directly). Do not open public issues for undisclosed vulnerabilities.
