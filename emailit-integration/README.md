# Emailit Integration for WordPress (v3.1.0)

Emailit Integration replaces WordPress `wp_mail()` with the Emailit API, adds webhook-driven delivery tracking, queue + retry, and operational tooling for admins and developers.

## Requirements
- WordPress 5.7+ and PHP 8.0+
- Emailit account and API key
- HTTPS strongly recommended for webhooks
- Outbound HTTP enabled (`wp_remote_post`)

## Installation
1) Copy `emailit-integration/` into `wp-content/plugins/`.  
2) Activate “Emailit Integration” in WP Admin.  
3) Go to **Settings → Emailit** and enter your API key.  
4) Enable webhooks and set a webhook secret (required; signature-verified).  
5) Send a test email from the settings page to confirm delivery.

## Core capabilities
- **wp_mail() replacement**: API-based sending with queue, retry, circuit breaker, and fallback to `wp_mail()` if enabled.
- **Webhooks**: Delivery/bounce/complaint/open/click; signature verification, replay protection, and rate limiting.
- **Logging**: Email + webhook logs with configurable payload retention and minimal-body mode; export to CSV/JSON.
- **Health & analytics**: Queue stats, webhook status, recent activity, and error insights in admin.
- **CLI**: `wp emailit queue process|drain|stats`, `wp emailit email resend|resend-failed`, `wp emailit logs clean|export`, `wp emailit stats`, `wp emailit test-email`, `wp emailit webhook test`.

## Configuration guide
1) **API key**: Enter and validate in Settings → Emailit → General.  
2) **Webhooks**: Enable, set secret, and configure the Emailit dashboard to POST to `https://yoursite.com/wp-json/emailit/v1/webhook`. Use “Test Webhook” in the UI or `wp emailit webhook test`.  
3) **Queue** (optional): Enable background queueing and set batch size/retries under Advanced → Queue.  
4) **Logging controls**: Choose payload logging mode (full/truncated/hash) and retention window (0–7 days) under Advanced → Webhooks; enable minimal logging and body length cap under Advanced → Logging.  
5) **FluentCRM** (optional): If FluentCRM is active, enable integration to forward bounces and map actions.  
6) **WooCommerce** (optional): If WooCommerce is active, enable checkout opt-in, set audience ID, and run the migration tool to subscribe past buyers.

## WooCommerce integration (conditional)
- Adds a checkout opt-in checkbox (label + default configurable).
- Subscribes buyers to a selected Emailit audience via API v2.
- Migration tool scans past orders (supports common meta keys from other email plugins) and queues subscriptions in batches.
- Deactivated entirely if WooCommerce is not active or the feature toggle is off.

## FluentCRM integration (conditional)
- Registers Emailit as a bounce handler only when FluentCRM is active and integration is enabled.
- Forwards bounce/complaint data and can auto-map actions (unsubscribe/hold/track).
- No handler is loaded or hooks registered when FluentCRM is missing.

## Security notes
- Webhooks require a secret; unsigned requests are rejected. Replay protection and rate limits are enabled.
- API keys are encrypted at rest; WooCommerce integration resolves the decrypted key before use.
- Payload retention defaults to truncation; configure 0–7 day full retention if needed.

## Changelog highlights (3.1.0)
- WooCommerce audience opt-in + migration tool (with decrypted key handling).
- Expanded WP-CLI commands for queue, resend, stats, webhook testing.
- Webhook payload retention/truncation setting and safer defaults.***
