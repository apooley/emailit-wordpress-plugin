# Getting Started

Use this guide to install, configure, and verify the Emailit Integration plugin.

## Prerequisites
- WordPress 5.7+ and PHP 8.0+
- Emailit account + API key; sending domain verified in Emailit
- HTTPS recommended for webhooks; outbound HTTP enabled
- WP-Cron enabled (for queue/retries) or a real cron hitting `wp-cron.php`

## Install
1) Copy `emailit-integration/` into `wp-content/plugins/`.  
2) Activate “Emailit Integration” in WP Admin → Plugins.  
3) Ensure `wp_remote_post` works (no firewall blocks outbound HTTP).

## Configure
1) **API key**: Go to Settings → Emailit → General, enter your API key, and save.  
2) **Webhooks** (required for status tracking):  
   - Enable webhooks and set a secret in Settings → Emailit → Webhooks.  
   - In the Emailit dashboard, point webhooks to `https://yoursite.com/wp-json/emailit/v1/webhook` and include the secret.  
3) **Queue** (optional): Enable under Advanced → Queue for background sending; set batch size and retries.  
4) **Logging**: Choose payload logging mode (full/truncated/hash) and payload retention (0–7 days) under Advanced → Webhooks. Enable minimal logging/body length cap under Advanced → Logging if you want smaller tables.  
5) **FluentCRM** (optional): If FluentCRM is active, enable integration and bounce forwarding under Emailit → FluentCRM.  
6) **WooCommerce** (optional): If WooCommerce is active, enable the checkout opt-in, set an audience ID, and run the migration tool to subscribe past buyers.

## Verify
- From Settings → Emailit, send a test email. Confirm delivery in Emailit and in the plugin logs.  
- Click “Test Webhook” (or run `wp emailit webhook test`) and confirm a 2xx response.  
- If queueing is enabled, check queue stats; run `wp emailit queue process` to drain locally.

## WP-CLI (optional)
- `wp emailit queue process|drain|stats`  
- `wp emailit email resend <id> | resend-failed`  
- `wp emailit logs clean [--dry-run] | export --format=csv|json`  
- `wp emailit stats [--days=<n>]`  
- `wp emailit test-email [email]`  
- `wp emailit webhook test`

## Next steps
- See `configuration.md` for option reference.  
- See `troubleshooting.md` for common issues (API key, webhooks, queue).  
- See `faq.md` and `best-practices.md` for opt-in guidance and logging recommendations.
