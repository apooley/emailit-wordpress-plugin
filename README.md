# Emailit Email API Integration

![WordPress](https://img.shields.io/badge/WordPress-5.7+-blue.svg)
![PHP](https://img.shields.io/badge/PHP-8.0+-green.svg)
![License](https://img.shields.io/badge/License-GPL%20v2-blue.svg)
![Version](https://img.shields.io/badge/Version-3.1.0-green.svg)

Emailit Integration replaces `wp_mail()` with the Emailit API, adds webhook-driven delivery tracking, and includes admin/CLI tooling for day-to-day operations.

## What’s inside
- Plugin code: `emailit-integration/`
- Docs: `emailit-integration/docs/` (start at `index.md`)
- Admin: settings, logs, health, webhook monitoring, Power User mode
- Integrations: FluentCRM bounce sync (conditional), WooCommerce checkout opt-in + migration (conditional)
- Ops: WP-CLI commands for queue, resend, webhook test, stats, and log export/cleanup

## Quick start
1) Copy `emailit-integration/` to `wp-content/plugins/` and activate.  
2) In WP Admin → Settings → Emailit: enter your Emailit API key, enable webhooks, and set the webhook secret.  
3) Run a test email from the settings page.  
4) Optional: enable queueing, FluentCRM sync, or WooCommerce checkout opt-ins/migration.

## Key features
- wp_mail() replacement with queue + retry + circuit breaker.
- Webhooks for delivery/bounce/complaint/open/click; signature-verified and rate-limited.
- Logging with configurable payload retention and minimal body mode; export to CSV/JSON.
- FluentCRM: bounce forwarding and action mapping (only if FluentCRM is active).
- WooCommerce: checkout opt-in checkbox, subscriber migration from prior plugins (only if WooCommerce is active).
- CLI: `wp emailit queue|email|logs|stats|test-email|webhook test`.

## Requirements
- WordPress 5.7+, PHP 8.0+, HTTPS recommended for webhooks, Emailit account/API key, outbound HTTP enabled.

## Docs & support
- Plugin guide: `emailit-integration/README.md`  
- Full docs index: `emailit-integration/docs/index.md`  
- Issues/support: GitHub Issues

## Recent changes (3.1.0)
- WooCommerce audience opt-in and migration, with decrypted API key handling.
- Expanded WP-CLI coverage (queue, resend, stats, webhook testing).
- Webhook payload retention/truncation controls and safer defaults.
