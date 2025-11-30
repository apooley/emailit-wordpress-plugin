# Repository Guidelines

## Project Structure & Module Organization
- Core plugin lives in `emailit-integration/`; activate it as `emailit-integration` inside a WordPress install.
- Admin UI assets and settings pages: `emailit-integration/admin/`.
- Core PHP logic (hooks, API client, queue, FluentCRM bridge): `emailit-integration/includes/`.
- Docs for setup, troubleshooting, and integrations: `emailit-integration/docs/` (start with `index.md`).
- Distribution zip for quick install: `emailit-integration.zip`. Root-level `README.md` gives the high-level overview.

## Build, Test, and Development Commands
- No build step is required; PHP runs as-is. Keep code compatible with WordPress 5.7+ and PHP 8.0+.
- Local install: copy `emailit-integration/` into `wp-content/plugins/` and run `wp plugin activate emailit-integration` (WP-CLI) or activate via WP Admin.
- Update from working tree without reinstalling: `wp plugin deactivate emailit-integration && wp plugin activate emailit-integration`.
- To prepare a release zip, compress the `emailit-integration/` directory (exclude `.git`, local cache files).

## Coding Style & Naming Conventions
- Follow WordPress PHP standards: 4-space indentation, snake_case functions, StudlyCaps classes, verbs-first method names (`send_email`, `register_hooks`).
- Escape and sanitize at boundaries (`esc_html`, `sanitize_text_field`, `wp_nonce_field`); prefer prepared statements for DB I/O.
- Inline filters/actions use descriptive hook names (`emailit_*` prefix). Keep translation-ready strings wrapped in `__()`/`_e()`.
- Keep admin UI markup minimal and accessible; ensure nonce checks for settings/actions.

## Testing Guidelines
- No automated test suite in-repo; rely on manual verification in a WordPress sandbox.
- Smoke test after changes: send a test email from the plugin settings, check webhook reception, and confirm FluentCRM sync (if installed).
- When altering hooks or queue logic, enable debug logging (see `docs/troubleshooting.md`) and review the log for errors/notices.

## Commit & Pull Request Guidelines
- Commits are short and imperative (see history: “Cleanup files”, “Update README”). Favor focused changes per commit.
- PRs should summarize scope, list manual test steps and results, and link related issues/tickets. Include screenshots/GIFs for admin UI tweaks.
- Note any schema/config changes (webhooks, settings defaults) in the PR description and update relevant docs in `emailit-integration/docs/`.

## Security & Configuration Tips
- Never commit API keys or webhook secrets; use environment-specific constants or WP config entries.
- Validate inbound webhook payloads and bail fast on unexpected event types; log safely without leaking PII.
- Maintain backwards compatibility with existing settings; provide migrations or fallbacks when adding options.
