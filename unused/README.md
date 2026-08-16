# Unused (not required to run FormFlow)

These files are **not used by the live website**. The installer, admin, forms, and submit API work without this folder.

You can skip uploading `unused/` to Hostinger. If it is uploaded, Apache blocks it via `.htaccess`.

| Path | What it was |
|------|-------------|
| `docs/` | README screenshot placeholders |
| `github/` | GitHub issue templates (was `.github/`) |
| `scripts/` | CLI migrate/seed, release ZIP, security tests |
| `phpmailer-extra/` | PHPMailer POP3/OAuth/DSN files the app never loads |
| `CONTRIBUTING.md`, `SECURITY.md`, `SECURITY_AUDIT.md` | Project docs |

To restore GitHub issue forms, move `github/` back to `.github/` at the repo root.
