# User Guide (v3.1.0)

This guide covers day-to-day use of Emailit Integration: navigating the admin UI, sending/tests, viewing logs, using webhooks, queue/ops, and CLI.

## Admin navigation
- **General**: API key, from/reply-to, test email, basic status.  
- **Webhooks**: Enable/secret, payload logging/retention, webhook test/status.  
- **Logs & Stats**: Email logs, webhook logs, recent activity, quick stats.  
- **Advanced**: Queue settings, retries, minimal logging, payload options.  
- **Integrations**: FluentCRM (conditional), WooCommerce (conditional).  
- **Power User mode**: Toggle to show advanced sections; per-user preference.

## Common tasks
- **Send test email**: General tab → Test Email. Confirms API key + delivery.  
- **View delivery status**: Logs & Stats → Email logs; click a row for details (subject, body, webhook events).  
- **Export logs**: Logs & Stats → Export (CSV/JSON) or CLI (`wp emailit logs export`).  
- **Clean old logs**: Advanced → Logging (retention), or CLI `wp emailit logs clean [--dry-run]`.  
- **Test webhook**: Webhooks tab → “Test Webhook” or CLI `wp emailit webhook test`.  
- **Check health**: Logs & Stats / Webhooks cards: API status, queue stats, last webhook, recent activity.  
- **Toggle queue**: Advanced → Queue; enable for background sending, set batch size/retries.

## Webhooks
- Require **Enable Webhooks** + **Webhook Secret**.  
- Configure Emailit dashboard to POST to `https://yoursite.com/wp-json/emailit/v1/webhook` with the secret.  
- Signature-verified, replay-protected, rate-limited.  
- Payload logging: Full (debug), Truncated (default, with max length), or Hash Only.  
- Payload retention: 0–7 days; beyond this window payloads are truncated (0 truncates immediately).

## Queue & retries
- Queue enabled: emails sent via background worker (WP-Cron/real cron).  
- Queue batch size + max retries control throughput and resilience.  
- API retry attempts (per-request) before offloading to queue; circuit breaker protects against sustained failures.

## Logging
- Minimal logging mode to reduce DB size (truncates stored bodies).  
- Body max length (when minimal logging is on).  
- Log retention (days) deletes old email + webhook rows.  
- Webhook payload retention/logging as above.

## Integrations
- **FluentCRM**: Visible only when FluentCRM is active. Enable integration, bounce forwarding, and action mapping; no handler loaded when FluentCRM is absent.  
- **WooCommerce**: Visible only when WooCommerce is active. Enable checkout opt-in, set audience ID, label/default, custom opt-in meta key, and run the migration tool to subscribe past buyers.

## CLI reference
- Queue: `wp emailit queue process|drain|stats`  
- Email resend: `wp emailit email resend <id>`; `wp emailit email resend-failed [--limit=<n>]`  
- Logs: `wp emailit logs clean [--dry-run]`; `wp emailit logs export --format=csv|json [--status=<status>] [--limit=<n>]`  
- Stats: `wp emailit stats [--days=<n>]`  
- Test email: `wp emailit test-email [email]`  
- Webhook test: `wp emailit webhook test`

## Tips
- Keep webhook payload retention low unless you need full payloads; truncation minimizes PII and DB growth.  
- Use queueing on production to avoid slow page loads during sends.  
- For WooCommerce/FluentCRM features, enable only when the respective plugin is active to avoid unnecessary hooks.  
- Use `--dry-run` when cleaning logs to see what would be removed.***
