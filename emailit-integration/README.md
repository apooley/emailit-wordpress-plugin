# Emailit Integration for WordPress

A comprehensive WordPress plugin that replaces the default `wp_mail()` function with Emailit's API service, providing enhanced email delivery, logging, webhook status updates, and a complete admin interface.

## Features

- **Complete wp_mail() Replacement**: Seamlessly integrates with existing WordPress emails
- **Real-time Email Tracking**: Track email status through webhooks (sent, delivered, bounced, etc.)
- **Comprehensive Logging**: Database logging with detailed email history
- **Admin Dashboard**: Full management interface with statistics and email logs
- **Secure API Integration**: Encrypted API key storage and secure webhook handling
- **Fallback Support**: Automatic fallback to wp_mail() if API fails
- **Attachment Support**: Full support for email attachments
- **Multiple Recipients**: Support for CC, BCC, and multiple recipients
- **Rate Limiting**: Built-in webhook rate limiting for security
- **Retry Logic**: Automatic retry for failed API requests

## Requirements

- WordPress 5.7 or higher
- PHP 7.4 or higher
- Emailit API account and API key
- SSL certificate recommended for webhook endpoints

## Installation

1. Upload the plugin files to `/wp-content/plugins/emailit-integration/`
2. Activate the plugin through the WordPress admin
3. Go to Settings → Emailit to configure your API key
4. Test your configuration using the built-in test functionality

## Configuration

### API Settings

1. Navigate to **Settings → Emailit**
2. Enter your Emailit API key
3. Configure default sender information:
   - From Name
   - From Email
   - Reply-To Email (optional)

### Webhook Setup

1. Go to the **Webhook** tab in plugin settings
2. Copy the webhook URL provided
3. Add this URL to your Emailit dashboard webhook settings
4. Copy the webhook secret and add it to your Emailit webhook configuration
5. Test the webhook using the built-in test functionality

### Advanced Settings

- **Enable Fallback**: Automatically use wp_mail() if Emailit API fails
- **Retry Attempts**: Number of retry attempts for failed requests (1-10)
- **Timeout**: API request timeout in seconds (5-120)
- **Logging**: Enable/disable email logging and set retention period

## Usage

Once configured, the plugin automatically handles all WordPress emails without any code changes required. Your existing plugins and themes will continue to work normally.

### Programmatic Usage

You can also send emails directly using the plugin's functions:

```php
// Send email via Emailit
$result = emailit_send('user@example.com', 'Subject', 'Message body');

// Log custom messages
emailit_log('Custom log message', 'info');

// Get plugin components
$api = emailit_get_component('api');
$logger = emailit_get_component('logger');
```

## Email Status Tracking

The plugin automatically tracks email status through webhooks:

- **Pending**: Email queued for sending
- **Sent**: Email accepted by Emailit API
- **Delivered**: Email successfully delivered to recipient
- **Bounced**: Email bounced (invalid address, full mailbox, etc.)
- **Failed**: Email failed to send
- **Complained**: Recipient marked email as spam

## Admin Interface

### Email Logs

View and manage all sent emails:

- **Access**: Tools → Email Logs
- **Features**:
  - Search and filter by status, date, recipient
  - View detailed email content and headers
  - Track webhook events and status updates
  - Resend failed emails
  - Delete individual logs
  - Export functionality

### Statistics Dashboard

Monitor email performance:

- Total emails sent
- Delivery rates
- Status breakdown
- Performance metrics

## Webhook Events

The plugin handles the following webhook events:

- `email.sent` - Email accepted by API
- `email.delivered` - Email delivered to recipient
- `email.bounced` - Email bounced
- `email.complained` - Spam complaint received
- `email.failed` - Email failed to send
- `email.opened` - Email opened by recipient (if tracking enabled)
- `email.clicked` - Link clicked in email (if tracking enabled)

## Security Features

- **Encrypted API Keys**: API keys are encrypted before database storage
- **Webhook Signature Verification**: HMAC signature validation for webhook security
- **Rate Limiting**: Prevents webhook abuse with configurable limits
- **Input Sanitization**: All inputs properly sanitized and validated
- **Capability Checks**: Admin functions require proper WordPress permissions
- **Nonce Verification**: All forms protected with WordPress nonces

## Plugin Conflict Detection

The plugin automatically detects and warns about potential conflicts with other email plugins:

### Detected Plugins
- WP Mail SMTP
- Easy WP SMTP
- Post SMTP Mailer
- Gmail SMTP
- SendGrid
- Mailgun
- FluentSMTP
- WP SES
- And more...

### Detection Features
- **Active Plugin Check**: Scans for known conflicting plugins
- **wp_mail() Override Detection**: Identifies if wp_mail() has been replaced
- **Hook Priority Analysis**: Detects high-priority email filters that might interfere
- **PHPMailer Modifications**: Identifies other plugins modifying PHPMailer
- **Admin Warnings**: Displays clear warnings in admin interface
- **System Status**: Shows detailed information about email hook status

### Viewing Conflict Information
1. Go to Settings → Emailit → Test tab
2. View "Plugin Compatibility Check" section
3. Review any detected conflicts and recommendations
4. Check "System Information" for detailed hook analysis

## Logging and Debugging

### Enable Debug Logging

Add to your `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Log Locations

- WordPress debug log: `/wp-content/debug.log`
- Plugin logs: Available through admin interface
- Database logs: Stored in custom tables

### Log Cleanup

- Automatic cleanup based on retention settings
- Manual cleanup tools in admin interface
- Configurable retention periods (1-365 days)

## Performance Considerations

- **Database Indexing**: Optimized database queries with proper indexing
- **Caching**: API key validation cached for 5 minutes
- **Background Processing**: Large email batches processed in background
- **Pagination**: Admin interface uses pagination for large datasets

## Compatibility

### Tested With

- WordPress core emails
- WooCommerce transactional emails
- Contact Form 7
- Gravity Forms
- WordPress multisite networks

### Known Exclusions

Some WordPress core emails are excluded by default to prevent authentication issues:

- Password reset emails
- New user registration emails
- Login details emails

You can customize exclusions using the `emailit_is_excluded_email` filter.

## Filters and Actions

### Filters

```php
// Modify email data before sending
add_filter('emailit_email_data', 'modify_email_data', 10, 6);

// Control which emails are sent via Emailit
add_filter('emailit_should_send', 'custom_should_send', 10, 2);

// Modify API request arguments
add_filter('emailit_api_args', 'modify_api_args', 10, 2);

// Customize log data before storage
add_filter('emailit_log_data', 'modify_log_data', 10, 1);
```

### Actions

```php
// Before email is sent
add_action('emailit_before_send', 'before_email_send', 10, 1);

// After email is sent
add_action('emailit_after_send', 'after_email_send', 10, 3);

// When webhook is received
add_action('emailit_webhook_received', 'handle_webhook', 10, 2);

// When email status is updated
add_action('emailit_status_updated', 'status_updated', 10, 3);
```

## Troubleshooting

### Common Issues

1. **API Key Invalid**
   - Verify API key in Emailit dashboard
   - Check for extra spaces or characters
   - Ensure proper account permissions

2. **Emails Not Sending**
   - Check API key configuration
   - Review error logs
   - Test API connectivity
   - Verify fallback settings

3. **Webhooks Not Working**
   - Confirm webhook URL accessibility
   - Check webhook secret configuration
   - Review webhook logs
   - Test webhook endpoint

4. **High Memory Usage**
   - Reduce log retention period
   - Enable log cleanup
   - Check for large email attachments

### Debug Steps

1. Enable WordPress debug logging
2. Use the built-in test functionality
3. Check the email logs for error details
4. Review webhook logs for delivery issues
5. Verify API connectivity in settings

## Uninstall

The plugin includes a complete uninstall process that removes:

- All database tables
- Plugin options and settings
- Scheduled cron jobs
- Transient data

## Support

For support and bug reports, please use the plugin's support forum or contact the developer.

## Changelog

### Version 1.0.0
- Initial release
- Complete wp_mail() integration
- Admin interface with email logs
- Webhook support for status updates
- Security features and error handling
- Comprehensive documentation

## License

This plugin is licensed under the GPL v2 or later.

## Credits

Developed for integration with Emailit email service API.