# Troubleshooting (v3.1.0)

Common issues, symptoms, and fixes for Emailit Integration.

## API key / sending
- **Symptoms**: Test email fails; API status shows error; logs show “no_api_key” or “api_error”.  
- **Checks**:  
  - Settings → Emailit → General: API key present and saved.  
  - Verify the key in Emailit dashboard; ensure sending domain is verified.  
  - Confirm outbound HTTP isn’t blocked (firewall).  
  - Check WP error log for API request failures.  
- **Fix**: Re-enter/validate key; ensure correct region/endpoint; increase timeout if network is slow.

## Webhooks not updating statuses
- **Symptoms**: Emails stay at “Sent to API”; no delivered/bounce events.  
- **Checks**:  
  - Webhooks enabled and secret set in Settings → Emailit → Webhooks.  
  - Emailit dashboard is posting to `https://yoursite.com/wp-json/emailit/v1/webhook` with the secret.  
  - `wp_remote_post` reachable; no auth plugins blocking REST.  
  - Webhook test returns 2xx (UI or `wp emailit webhook test`).  
  - Server time reasonably accurate; HTTPS recommended.  
- **Fix**: Set/align secret in Emailit; clear caches; ensure no maintenance/firewall blocks; retry after enabling logging (truncated mode) to inspect incoming payload format.

## Queue not processing
- **Symptoms**: Pending queue count never drops; emails stay pending.  
- **Checks**:  
  - Queue enabled (Advanced → Queue); batch size > 0.  
  - WP-Cron running (or real cron hitting `wp-cron.php`).  
  - `wp emailit queue process` works via CLI.  
- **Fix**: Configure real cron; lower batch size if timeouts; inspect error log for queue worker failures.

## WooCommerce opt-in not subscribing
- **Symptoms**: Checkout opt-in does nothing; migration doesn’t subscribe.  
- **Checks**:  
  - WooCommerce active; feature enabled; audience ID set.  
  - API key decrypted (uses API component); check for errors in order meta `_emailit_subscription_error`.  
  - Valid emails on orders; opt-in checkbox/migration meta present.  
- **Fix**: Set audience ID; verify API key; rerun migration; review error log for HTTP status.

## FluentCRM bounce forwarding not working
- **Symptoms**: Bounces not reflected in FluentCRM; no status changes.  
- **Checks**:  
  - FluentCRM active; integration enabled; forward bounces enabled.  
  - Bounce handler registered in FluentCRM settings; webhook events include bounce/complaint.  
  - Valid webhook secret; payload processed (see webhook logs).  
- **Fix**: Re-enable integration; re-select Emailit as bounce handler; verify webhook payload contains matching email; check action mapping thresholds.

## Logs and storage growth
- **Symptoms**: Large DB tables; slow exports.  
- **Checks/Fix**:  
  - Enable minimal logging and cap body length.  
  - Use truncated/hash webhook payload logging; set retention days to 0–7.  
  - Set log retention days; run `wp emailit logs clean [--dry-run]`.

## Security/validation errors
- **Symptoms**: Webhook 401/403; signature errors.  
- **Checks**:  
  - Secret set on both sides; timestamp freshness; correct header names (`X-Emailit-Signature`, `X-Emailit-Timestamp`).  
  - No proxy stripping headers.  
- **Fix**: Recreate secret and update Emailit dashboard; ensure HTTPS and correct endpoint.***
