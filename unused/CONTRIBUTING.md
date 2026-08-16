# Contributing to FormFlow

Thank you for helping improve FormFlow! This project targets **shared PHP hosting** — keep dependencies minimal (no Composer for core, no Node build step).

## Code style

- Follow **[PSR-12](https://www.php-fig.org/psr/psr-12/)** for PHP.
- Use `declare(strict_types=1);` in new PHP files.
- Match existing naming: `FormRepository`, `findForUser()`, snake_case DB columns, camelCase PHP methods.
- Run `php -l` on changed files before submitting.

## Run locally

No Docker required.

```bash
git clone https://github.com/YOUR_ORG/formflow.git
cd formflow
php -S localhost:8000 router.php
```

1. Create an empty MySQL database.
2. Visit [http://localhost:8000/install/](http://localhost:8000/install/) and complete the wizard.

**Optional dev shortcuts** (after `config.php` exists):

```bash
php unused/scripts/migrate.php          # re-run migrations
php core/seed-templates.php   # seed template library
php unused/scripts/run-security-audit.php http://localhost:8000
```

Use `router.php` as the router script — it mirrors Apache `.htaccess` behaviour and blocks sensitive paths.

## Proposing changes

### New field type

1. Add the type to `FormDefaults::FIELD_TYPES` and `FormDefaults::field()`.
2. Extend `FormValidator::validate()` for server-side rules.
3. Update `EmbedGenerator` for HTML/fetch snippets.
4. Add UI in `views/admin/forms/_form.php` (builder tab).
5. Document in README if the type affects the public `/submit` API.

### New template

1. Add a definition to `core/TemplateDefinitions.php`.
2. Re-run `php core/seed-templates.php` (or add a migration INSERT).
3. Include a one-line description for the Templates gallery.

### New integration (webhook, CRM, etc.)

1. Prefer best-effort, non-blocking delivery (see `WebhookClient`, `SubmissionNotifier`).
2. Add settings to form or site config with sensible defaults.
3. Note any new secrets in SECURITY.md.

## Pull request checklist

- [ ] Focused diff — no unrelated refactors
- [ ] `php -l` passes on touched files
- [ ] Installer still works on a **fresh** database (no `config.php` committed)
- [ ] User/submission output uses `e()` / `htmlspecialchars`

### If your PR touches **auth**, **uploads**, or **SQL queries**

Also verify the [Phase 6 hardening checklist](SECURITY_AUDIT.md):

- [ ] All queries use PDO prepared statements (no user input in SQL strings)
- [ ] Admin routes enforce `Auth::requireAuth()` / `requireRole()` before data access
- [ ] Resources fetched by ID verify ownership (`findForUser`, equivalent joins)
- [ ] State-changing POST requests verify CSRF
- [ ] File uploads validated with `finfo` MIME (not extension alone)
- [ ] Run `php unused/scripts/run-security-audit.php` and `php unused/scripts/security-idor-test.php`

## Reporting issues

Use the GitHub issue templates:

- **Bug report** — reproducible steps, PHP/MySQL versions, host name
- **Feature request** — problem statement and proposed behaviour
- **Hosting compatibility** — confirm FormFlow works on a specific provider/plan

## License

By contributing, you agree that your contributions will be licensed under the [MIT License](LICENSE).
