# Emailit Integration Docs Index (v3.1.0)

Start here for installation, configuration, and operations guidance for the Emailit Integration plugin.

## Essentials
- **Overview & setup**: `../README.md`
- **Getting started**: `getting-started.md`
- **Configuration reference**: `configuration.md`
- **Troubleshooting**: `troubleshooting.md`
- **FAQ**: `faq.md`
- **Glossary**: `glossary.md`

## Features & integrations
- **FluentCRM integration**: `fluentcrm-integration.md` (bounce handling, action mapping; loads only when FluentCRM is active)
- **WooCommerce opt-in**: `../admin/views/woocommerce-settings.php` (UI), plus this doc: `best-practices.md` (opt-in language and compliance)
- **Webhook logging & retention**: see `configuration.md` (payload logging mode, retention days 0–7, minimal logging)
- **Queue & retry**: covered in `getting-started.md` and `configuration.md`

## Operations
- **WP-CLI commands**: `user-guide.md` (queue, resend, logs, webhook test, stats, test-email)
- **Health & monitoring**: `user-guide.md` (dashboard cards, webhook monitor, recent activity)
- **Log management**: `troubleshooting.md` and `user-guide.md` (cleanup, export, retention)

## Security & best practices
- Configure a **webhook secret** and HTTPS; unsigned requests are rejected.
- Keep **payload retention** low unless needed; default truncation minimizes PII exposure.
- API keys are encrypted; WooCommerce integration resolves the decrypted key before subscribing audiences.
- Rate limiting and replay protection are enabled on webhook ingestion.

## Version
- Plugin version: 3.1.0
