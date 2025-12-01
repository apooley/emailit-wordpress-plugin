# Configuration Reference

This document summarizes the key settings in Settings → Emailit.

## General
- **API Key (required)**: Enter your Emailit API key. Stored encrypted.  
- **From Name / From Email / Reply-To**: Defaults to site name/admin email; set to your verified sending identity.  
- **Test Email**: Send a test to verify delivery.

## Webhooks
- **Enable Webhooks**: Must be on for delivery/bounce/complaint tracking.  
- **Webhook Secret (required)**: Shared secret to sign incoming requests; unsigned/invalid requests are rejected.  
- **Payload Logging**: `Full` (debug only), `Truncated` (default), or `Hash Only`.  
- **Payload Max Length**: Used with Truncated (500–50,000 chars; default 5,000).  
- **Payload Retention (Days)**: 0–7. Payloads beyond this window are truncated; 0 truncates immediately.  
- **Webhook URL**: `https://yoursite.com/wp-json/emailit/v1/webhook` (read-only).

## Queue & Retry
- **Enable Queue**: Send via background queue instead of synchronous `wp_mail()`.  
- **Batch Size**: Emails per queue run.  
- **Max Retries**: Queue retry attempts.  
- **Retry Attempts (API)**: API-level retries before handing off to queue.  
- **Timeout**: API request timeout (seconds).  
- **Fallback to wp_mail()**: Optional; use only if you need a soft failover.

## Logging
- **Minimal Logging Mode**: Truncate stored email bodies to reduce DB size.  
- **Body Max Length**: Cap for stored email content when minimal logging is enabled.  
- **Log Retention (Days)**: Deletes email + webhook log rows older than this.

## Integrations (conditional)
- **FluentCRM** (visible only when FluentCRM is active): Enable integration, forward bounces, map actions (hard/soft/complaint), set thresholds and auto-create subscribers. No hooks are registered if FluentCRM is missing.  
- **WooCommerce** (visible only when WooCommerce is active): Enable checkout opt-in, set audience ID, checkbox label/default, custom opt-in meta key, and run migration for past buyers.

## Advanced / Security
- **Webhook signature required**; replay protection and rate limiting enabled.  
- API keys are encrypted; WooCommerce audience sync resolves the decrypted key before use.  
- Keep payload retention low unless required; truncation reduces PII exposure.
