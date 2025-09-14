# Emailit Integration for WordPress

**Version 2.3.0** - A comprehensive WordPress plugin that replaces the default `wp_mail()` function with Emailit's API service, providing enhanced email delivery, logging, webhook status updates, FluentCRM integration, and a complete admin interface with enterprise-grade security.

## 🚀 Recent Updates

### Version 2.3.0 - FluentCRM Integration & Email Template Enhancement

### 🤝 **FluentCRM Integration**
- **Automatic Detection**: Seamless integration that only activates when FluentCRM is installed
- **Bi-directional Bounce Sync**: FluentCRM bounces automatically update Emailit logs
- **Comprehensive Status Tracking**: Handles hard bounces, soft bounces, and spam complaints
- **Zero Impact**: No effect on existing functionality when FluentCRM is not present
- **Flexible Configuration**: Multiple configurable options for bounce handling behavior
- **Developer Hooks**: Extensive action and filter hooks for custom integration logic

### ✨ **Email Template Improvements**
- **Email Client Compatibility**: Fixed HTML email templates for proper rendering in all email clients
- **Inline CSS Styling**: Converted CSS to inline styles for maximum compatibility
- **Table-based Layout**: Professional email layout that works across Gmail, Outlook, and mobile clients
- **Enhanced Rendering**: Resolved styling issues that caused poor email display

### 🔧 **JavaScript Fixes**
- **Test Result Display**: Fixed WordPress Mail Test result div being hidden immediately after submission
- **Enhanced User Experience**: Test results now properly persist for user review
- **UI Consistency**: Improved admin interface reliability and visual feedback

### Version 2.1.0 - Security & Stability Release

### 🎯 **New Features**
- **Optional Webhook Listening**: Choose between real-time webhook updates or simplified "Sent to API" status
- **Enhanced Email Status Display**: Clear differentiation between webhook-enabled and disabled modes
- **Dynamic Webhook Registration**: Webhook endpoints only registered when needed, saving resources
### 🔒 **Critical Security Fixes**
- **SQL Injection Prevention**: Fixed uninstall script vulnerability with proper table name escaping
- **File Upload Security**: Comprehensive protection against malicious file uploads with path traversal prevention, MIME type validation, and dangerous extension blocking
- **Debug Data Protection**: API keys and sensitive data now properly redacted from debug logs
- **Enhanced IP Detection**: Secure client IP detection with proxy support and validation

### 🛡️ **Security Enhancements**
- **Multi-layered File Protection**: 10MB size limits, allowlist-based MIME validation, double extension detection
- **Debug Sanitization**: Recursive sensitive data redaction in API request logs
- **IP Security**: Proper handling of proxy headers with validation against private/reserved ranges
- **Uninstall Cleanup**: Complete removal of all plugin data including queue table

### 🐛 **Bug Fixes**
- Fixed queue table missing from uninstall cleanup
- Enhanced error logging with better context and debugging information
- Improved webhook IP logging security
- **Fixed blank confirmation popup** when deleting email log entries (missing JavaScript localization string)

### 🎯 **UI Improvements**
- **Enhanced User Experience**: Proper confirmation dialogs for email log deletion with clear messaging
- **JavaScript Localization**: Complete localization strings for all admin interface interactions
- **Queue Management**: Added comprehensive queue item management functionality with deletion capabilities

### Version 2.0.0 - Major Security & Performance Release

### 🔒 **Enterprise Security Enhancements**
- **AES-256-GCM Encryption**: Military-grade API key encryption with authenticated encryption tags
- **Secure Form Handling**: API keys never exposed in HTML source or browser debugging tools
- **Header Injection Prevention**: Comprehensive email header sanitization to prevent security exploits
- **Professional User Interface**: Visual security indicators and professional error handling

### ⚡ **Performance & Reliability**
- **Asynchronous Email Processing**: WordPress cron-based queue system for high-volume sending
- **Enhanced Webhook Processing**: Fixed signature verification with Emailit's actual webhook format
- **Improved Error Handling**: Better debugging and conditional logging based on WP_DEBUG
- **Backward Compatibility**: Legacy encrypted key support with automatic migration

### 🛠️ **Developer Experience**
- **Enhanced Admin Interface**: Professional queue management and real-time statistics
- **Comprehensive Logging**: Detailed debug information when WP_DEBUG is enabled
- **Better Conflict Detection**: Enhanced plugin compatibility checking and warnings

## Features

### Core Email Functionality
- **Complete wp_mail() Replacement**: Seamlessly integrates with existing WordPress emails
- **Real-time Email Tracking**: Track email status through webhooks (sent, delivered, bounced, etc.)
- **Asynchronous Processing**: Queue-based email sending for better performance
- **Comprehensive Logging**: Database logging with detailed email history
- **Fallback Support**: Automatic fallback to wp_mail() if API fails
- **Attachment Support**: Full support for email attachments with base64 encoding
- **Multiple Recipients**: Support for CC, BCC, and multiple recipients
- **Retry Logic**: Configurable automatic retry for failed API requests

### Security Features
- **🔐 Enterprise-Grade Encryption**: AES-256-GCM with random IVs and authentication tags
- **🛡️ Header Injection Prevention**: Comprehensive sanitization to prevent email header attacks
- **🔒 Secure API Key Storage**: Keys encrypted at rest and never exposed in HTML
- **✅ Webhook Signature Verification**: HMAC SHA256 signature validation for webhook security
- **🚫 Rate Limiting**: Built-in webhook rate limiting for security
- **🔍 Input Sanitization**: All inputs properly sanitized and validated
- **👤 Capability Checks**: Admin functions require proper WordPress permissions
- **🎫 Nonce Verification**: All forms protected with WordPress nonces

### Admin Interface
- **📊 Professional Dashboard**: Tabbed interface with comprehensive settings
- **📈 Real-time Statistics**: Email delivery rates and performance metrics
- **🔧 Queue Management**: Manual queue processing and statistics
- **📋 Email Logs**: Searchable logs with filtering and export functionality
- **🧪 Testing Tools**: Built-in email testing and diagnostic functionality
- **⚠️ Conflict Detection**: Automatic detection of conflicting plugins
- **🔄 Status Tracking**: Real-time webhook status updates

## Requirements

- WordPress 5.7 or higher
- PHP 8.0 or higher (PHP 8.4 compatible)
- Emailit API account and API key
- SSL certificate recommended for webhook endpoints
- OpenSSL extension for encryption functionality

## Installation

### Automatic Installation (Recommended)

1. Download the plugin zip file
2. Go to WordPress Admin → Plugins → Add New → Upload Plugin
3. Choose the zip file and click "Install Now"
4. Activate the plugin
5. Go to Settings → Emailit to configure your API key
6. Test your configuration using the built-in test functionality

### Manual Installation

1. Extract the plugin files to `/wp-content/plugins/emailit-integration/`
2. Ensure proper file permissions (644 for files, 755 for directories)
3. Activate the plugin through the WordPress admin
4. Configure your settings as described above

### Post-Installation Steps

1. **Configure API Key**: Enter your Emailit API key in Settings → Emailit
2. **Set Default Sender**: Configure your default "From Name" and "From Email"
3. **Enable Queue Processing** (optional): Enable asynchronous email sending for better performance
4. **Test Configuration**: Use the Test tab to send a test email
5. **Configure Webhooks**: Copy the webhook URL to your Emailit dashboard
6. **Review Logs**: Check the Email Logs page to monitor email delivery

## Configuration

### API Settings

1. Navigate to **Settings → Emailit**
2. Enter your Emailit API key (automatically encrypted and secured)
3. Configure default sender information:
   - From Name
   - From Email
   - Reply-To Email (optional)

### Performance & Queue Settings

1. Go to the **Performance** tab in plugin settings
2. **Enable Asynchronous Sending**: Process emails in background for better performance
3. **Queue Batch Size**: Number of emails to process in each batch (1-50)
4. **Queue Max Retries**: Maximum retry attempts for failed emails (0-10)
5. Monitor queue statistics and manually process queue if needed

### Webhook Setup

1. Go to the **Webhook** tab in plugin settings
2. **Enable Webhooks** checkbox controls real-time status updates:
   - **Enabled**: Receive detailed status updates (delivered, bounced, etc.) - emails show as "Sent" initially
   - **Disabled**: Simple "Sent to API" status only - no webhook endpoint registered
3. If webhooks enabled, copy the webhook URL provided
4. Add this URL to your Emailit dashboard webhook settings
5. Copy the webhook secret and add it to your Emailit webhook configuration
6. Test the webhook using the built-in test functionality

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
$queue = emailit_get_component('queue');
```

### Queue Management

```php
// Check queue statistics
$stats = $queue->get_stats();
echo "Pending: " . $stats['pending'];
echo "Processing: " . $stats['processing'];
echo "Failed: " . $stats['failed'];

// Process queue manually
$queue->process_queue();

// Add email to queue
$queue->add_to_queue($email_data);
```

## Email Status Tracking

The plugin tracks email status with two different modes:

### With Webhooks Enabled (Default)
Real-time status updates through webhooks:
- **Pending**: Email queued for sending
- **Processing**: Email being sent via queue
- **Sent**: Email accepted by Emailit API (awaiting delivery updates)
- **Delivered**: Email successfully delivered to recipient
- **Bounced**: Email bounced (invalid address, full mailbox, etc.)
- **Failed**: Email failed to send
- **Complained**: Recipient marked email as spam
- **Opened**: Email opened by recipient (if tracking enabled)
- **Clicked**: Link clicked in email (if tracking enabled)

### With Webhooks Disabled
Simplified status tracking:
- **Pending**: Email queued for sending
- **Processing**: Email being sent via queue
- **Sent to API**: Email successfully sent to Emailit API (no further updates)
- **Failed**: Email failed to send

## Admin Interface

### Email Logs

View and manage all sent emails:

- **Access**: Tools → Email Logs
- **Features**:
  - Search and filter by status, date, recipient
  - View detailed email content and headers
  - Track webhook events and status updates
  - Resend failed emails (individual or bulk)
  - Delete individual logs
  - Export functionality (CSV/JSON)
  - Real-time status updates

### Performance Dashboard

Monitor email performance:

- **Queue Statistics**: Real-time queue status with pending/processing/failed counts
- **Manual Processing**: Process queue immediately with one-click
- **Batch Configuration**: Adjust processing batch sizes and retry limits
- **Performance Metrics**: Email sending rates and success statistics

### Statistics Dashboard

Monitor email performance:

- Total emails sent
- Delivery rates by status
- Status breakdown with charts
- Performance metrics and trends
- Queue processing statistics

## Webhook Events

The plugin handles the following webhook events with enhanced signature verification:

- `email.delivery.sent` - Email accepted by API
- `email.delivery.delivered` - Email delivered to recipient
- `email.delivery.bounced` - Email bounced
- `email.delivery.complained` - Spam complaint received
- `email.delivery.failed` - Email failed to send
- `email.delivery.opened` - Email opened by recipient
- `email.delivery.clicked` - Link clicked in email

### Webhook Security

- **HMAC SHA256 Verification**: Validates webhook authenticity using shared secret
- **Timestamp Validation**: Prevents replay attacks with timestamp checking
- **Domain Verification**: Ensures webhooks are for emails from your site
- **Rate Limiting**: Prevents webhook abuse with configurable limits
- **Debug Logging**: Comprehensive webhook debugging when WP_DEBUG enabled

## Security Features

### API Key Security
- **AES-256-GCM Encryption**: Military-grade encryption with authentication tags
- **Random IVs**: Each encryption uses a cryptographically random 12-byte IV
- **Never Exposed**: API keys never appear in HTML source or browser debugging
- **Backward Compatible**: Automatic migration from legacy encryption methods
- **Validation**: API key format validation and optional connectivity testing

### Email Security
- **Header Injection Prevention**: Comprehensive sanitization prevents email header attacks
- **CRLF Attack Protection**: Detects and blocks carriage return/line feed injections
- **Input Validation**: All email addresses and content validated before processing
- **Safe Fallback**: Secure fallback to wp_mail() with continued protection

### Administrative Security
- **Capability Checks**: All admin functions require `manage_options` capability
- **Nonce Verification**: All forms protected with WordPress nonces
- **CSRF Protection**: Cross-site request forgery protection throughout
- **SQL Injection Prevention**: Parameterized queries and proper escaping
- **XSS Protection**: All output properly escaped for security

## Plugin Conflict Detection

The plugin automatically detects and warns about potential conflicts:

### Detected Email Plugins
- WP Mail SMTP & WP Mail SMTP Pro
- Easy WP SMTP
- Post SMTP Mailer
- Gmail SMTP
- SendGrid Email Delivery
- Mailgun Official
- FluentSMTP
- WP SES (Amazon Simple Email Service)
- WP Mailgun SMTP
- And many more...

### Detection Features
- **Active Plugin Check**: Scans for known conflicting plugins
- **wp_mail() Override Detection**: Identifies if wp_mail() has been replaced
- **Hook Priority Analysis**: Detects high-priority email filters that might interfere
- **PHPMailer Modifications**: Identifies other plugins modifying PHPMailer
- **Admin Warnings**: Displays clear warnings with actionable recommendations
- **System Status**: Shows detailed information about email hook status

### Viewing Conflict Information
1. Go to Settings → Emailit → Test tab
2. View "Plugin Compatibility Check" section
3. Review any detected conflicts and recommendations
4. Check "System Information" for detailed hook analysis

## Logging and Debugging

### Debug Logging

Enhanced debugging capabilities:

```php
// Enable in wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Debug Features
- **Conditional Logging**: Debug messages only appear when WP_DEBUG is enabled
- **Webhook Debugging**: Detailed webhook signature verification logs
- **API Request Logging**: Complete API request and response logging
- **Queue Processing Logs**: Detailed queue processing information
- **Error Context**: File names, line numbers, and stack traces for errors

### Log Locations

- WordPress debug log: `/wp-content/debug.log`
- Plugin logs: Available through admin interface
- Database logs: Stored in custom tables with full history
- Queue logs: Background processing status and errors

### Log Management

- **Automatic Cleanup**: Configurable retention periods (1-365 days)
- **Manual Cleanup**: One-click cleanup tools in admin interface
- **Export Functionality**: Export logs as CSV or JSON
- **Bulk Operations**: Delete or resend multiple emails at once

## Performance Considerations

### Queue System
- **Asynchronous Processing**: Background email sending via WordPress cron
- **Configurable Batching**: Process 1-50 emails per batch
- **Retry Logic**: Automatic retry with exponential backoff
- **Manual Processing**: Override automatic processing when needed
- **Statistics Tracking**: Monitor queue performance and bottlenecks

### Database Optimization
- **Proper Indexing**: Optimized database queries with strategic indexing
- **Efficient Queries**: Minimal database impact with optimized SQL
- **Log Rotation**: Automatic cleanup prevents database bloat
- **Pagination**: Admin interface uses pagination for large datasets

### Caching
- **API Key Validation**: Cached for 5 minutes to reduce API calls
- **Transient Usage**: WordPress transients for temporary data storage
- **Memory Management**: Efficient memory usage for large email batches

## Compatibility

### Tested With

- WordPress 5.7 - 6.4+
- PHP 8.0 - 8.4
- WordPress core emails
- WooCommerce transactional emails
- Contact Form 7
- Gravity Forms
- Ninja Forms
- WordPress multisite networks
- Popular newsletter plugins

### Known Exclusions

Some WordPress core emails are excluded by default to prevent authentication issues:

- Password reset emails (can be overridden)
- New user registration emails (can be overridden)
- Login details emails (can be overridden)

You can customize exclusions using the `emailit_is_excluded_email` filter.

## FluentCRM Integration

The Emailit plugin includes automatic integration with FluentCRM, a popular WordPress CRM and email marketing plugin. This integration provides seamless bounce handling and status synchronization between FluentCRM and Emailit.

### Automatic Detection

The FluentCRM integration is **completely optional** and only activates when FluentCRM is detected:

- ✅ **Zero Impact**: No effect on your site if FluentCRM is not installed
- ✅ **Automatic Activation**: Enables automatically when FluentCRM plugin is active
- ✅ **Safe Fallback**: Gracefully handles FluentCRM deactivation without errors
- ✅ **No Dependencies**: Emailit works perfectly with or without FluentCRM

### Features

#### 🔄 **Bi-directional Bounce Sync**
- FluentCRM bounce detection automatically updates Emailit logs
- Maintains synchronized bounce status across both systems
- Preserves bounce context and detailed error information

#### 📊 **Comprehensive Status Tracking**
- **Hard Bounces**: Automatically marked in Emailit logs when FluentCRM detects permanent failures
- **Soft Bounces**: Tracked with escalation to hard bounce after configurable threshold
- **Spam Complaints**: Synchronized between FluentCRM and Emailit for reputation management
- **Unsubscribes**: Status changes tracked for comprehensive deliverability insights

#### ⚙️ **Flexible Configuration**
The integration includes several configurable options:

- `emailit_fluentcrm_integration` - Enable/disable integration (default: enabled)
- `emailit_fluentcrm_forward_bounces` - Forward bounce data to Emailit API (default: enabled)
- `emailit_fluentcrm_soft_bounce_threshold` - Soft bounce limit before marking as hard bounce (default: 5)

### How It Works

1. **FluentCRM Detection**: Plugin automatically detects when FluentCRM is active
2. **Bounce Events**: FluentCRM receives bounce notifications from email services (AWS SES, Mailgun, SendGrid, etc.)
3. **Status Updates**: FluentCRM updates subscriber status (bounced, complained, unsubscribed)
4. **Integration Trigger**: Our integration captures these status changes via WordPress action hooks:
   - `fluentcrm_subscriber_status_to_bounced`
   - `fluentcrm_subscriber_status_to_complained`
   - `fluent_crm/subscriber_status_changed`
5. **Data Sync**: Corresponding Emailit logs are updated with bounce information
6. **Logging**: All integration activities are logged for debugging and audit trails

### Developer Hooks

For advanced customization, the integration provides several action hooks:

```php
// Fired when a FluentCRM subscriber bounces
add_action('emailit_fluentcrm_subscriber_bounced', function($subscriber, $oldStatus, $bounceData, $webhook) {
    // Custom bounce handling logic
    error_log("Subscriber {$subscriber->email} bounced: {$bounceData['reason']}");
});

// Fired when a FluentCRM subscriber complaint is detected
add_action('emailit_fluentcrm_subscriber_complained', function($subscriber, $oldStatus, $bounceData, $webhook) {
    // Custom complaint handling logic
    // Send notification to admin, update custom fields, etc.
});

// General status change tracking
add_action('emailit_fluentcrm_status_changed', function($subscriber, $oldStatus, $newStatus, $webhook) {
    // Track all deliverability-related status changes
    if (in_array($newStatus, ['bounced', 'complained', 'unsubscribed'])) {
        // Custom logic for tracking deliverability issues
    }
});

// Customize bounce notification data sent to Emailit API
add_filter('emailit_fluentcrm_bounce_notification_data', function($data, $subscriber, $bounceData) {
    $data['custom_field'] = 'custom_value';
    $data['subscriber_tags'] = $subscriber->tags; // Add FluentCRM tags
    return $data;
}, 10, 3);
```

### Supported FluentCRM Versions

- **FluentCRM 2.9.65+**: Fully tested and supported
- **FluentCRM 2.0+**: Compatible with all modern FluentCRM versions
- **Earlier Versions**: May work but not officially supported

### Benefits

1. **Unified Bounce Management**: Single source of truth for bounce handling across both platforms
2. **Enhanced Deliverability**: Better reputation management through comprehensive bounce tracking
3. **Reduced Manual Work**: Automatic synchronization eliminates manual status updates
4. **Audit Trail**: Complete logging of all bounce-related activities
5. **Zero Maintenance**: Works automatically in the background with no configuration required

### Troubleshooting

If you experience issues with the FluentCRM integration:

1. **Check FluentCRM Status**: Ensure FluentCRM is active and functioning properly
2. **Enable Debug Logging**: Set `WP_DEBUG` to `true` to see integration activity logs
3. **Verify Settings**: Check that `emailit_fluentcrm_integration` option is enabled
4. **Review Logs**: Check both Emailit and FluentCRM logs for bounce processing

The integration is designed to be completely transparent and should require no manual intervention once both plugins are active.

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

// Control email exclusions
add_filter('emailit_is_excluded_email', 'custom_exclusions', 10, 6);

// Modify queue processing
add_filter('emailit_queue_batch_size', 'custom_batch_size', 10, 1);
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

// Queue processing events
add_action('emailit_queue_processed', 'queue_processed', 10, 2);
add_action('emailit_queue_failed', 'queue_failed', 10, 2);
```

## Troubleshooting

### Common Issues

1. **API Key Invalid**
   - Verify API key in Emailit dashboard
   - Check for extra spaces or characters
   - Ensure proper account permissions
   - Use the built-in API key validation

2. **Emails Not Sending**
   - Check API key configuration
   - Review error logs with WP_DEBUG enabled
   - Test API connectivity using admin tools
   - Verify fallback settings
   - Check queue processing status

3. **Webhooks Not Working**
   - Confirm webhook URL accessibility
   - Check webhook secret configuration in both places
   - Review webhook signature verification logs
   - Test webhook endpoint using admin tools
   - Ensure proper SSL certificate

4. **Queue Processing Issues**
   - Check WordPress cron functionality
   - Verify queue settings and batch sizes
   - Monitor queue statistics in admin
   - Process queue manually to test
   - Check for plugin conflicts

5. **High Memory Usage**
   - Reduce log retention period
   - Enable automatic log cleanup
   - Check for large email attachments
   - Reduce queue batch sizes
   - Monitor queue processing frequency

### Debug Steps

1. **Enable Debug Logging**: Add `WP_DEBUG` constants to wp-config.php
2. **Use Built-in Tests**: Test API connectivity and webhook functionality
3. **Check Email Logs**: Review detailed error messages and context
4. **Monitor Webhooks**: Check webhook logs for delivery and signature issues
5. **Verify Settings**: Double-check all configuration settings
6. **Test Queue**: Use manual queue processing to isolate issues

## Uninstall

The plugin includes a comprehensive uninstall process that removes:

- All database tables (`emailit_logs`, `emailit_webhook_logs`, `emailit_queue`)
- Plugin options and settings (all `emailit_*` options)
- Scheduled cron jobs (`emailit_process_queue`, `emailit_cleanup_logs`)
- Transient data and caches
- User meta data related to the plugin

## Support

For support, bug reports, and feature requests:

- GitHub Issues: Use the plugin repository issue tracker
- WordPress Support Forum: Plugin support forum
- Developer Contact: Direct developer support for critical issues

## Changelog

### Version 2.3.0 - FluentCRM Integration & Email Template Enhancement

**🤝 FluentCRM Integration:**
- **Automatic FluentCRM Detection**: Seamless integration that only activates when FluentCRM plugin is detected
- **Bi-directional Bounce Synchronization**: FluentCRM bounce events automatically update Emailit email logs
- **Comprehensive Status Tracking**: Support for hard bounces, soft bounces, spam complaints, and unsubscribes
- **Zero-Impact Design**: No effect on plugin functionality when FluentCRM is not installed
- **Flexible Configuration Options**: Multiple settings for bounce handling behavior and thresholds
- **Developer Hook System**: Extensive action and filter hooks for custom integration and bounce handling logic
- **Soft Bounce Escalation**: Configurable threshold for escalating soft bounces to hard bounces
- **Meta Data Integration**: Full integration with FluentCRM's subscriber meta system for tracking bounce history

**✨ Email Template Improvements:**
- **Email Client Compatibility**: Complete rewrite of HTML email templates using inline CSS for universal email client support
- **Table-based Layout**: Professional responsive design that renders correctly in Gmail, Outlook, Apple Mail, and mobile clients
- **Inline CSS Conversion**: Moved all CSS styles inline to ensure proper rendering across all email platforms
- **Enhanced Visual Design**: Improved email aesthetics with proper spacing, colors, and typography

**🔧 User Interface Fixes:**
- **WordPress Mail Test Display**: Fixed issue where test result div was immediately hidden after form submission
- **Enhanced User Experience**: Test results now properly persist for user review and debugging
- **Admin Interface Reliability**: Improved JavaScript handling and visual feedback consistency

**🛠️ Developer Experience:**
- **Enhanced Filter System**: Added comprehensive filters for email content, attachments, error messages, and queue control
- **FluentCRM Hook Documentation**: Complete documentation of all available integration hooks and filters
- **Improved Code Architecture**: Better separation of concerns between core email functionality and CRM integration

### Version 2.1.0 - Security & Stability Release
**🎯 New Features:**
- **Optional Webhook Listening**: New checkbox to enable/disable webhook status updates
- **Simplified Status Tracking**: "Sent to API" status for webhook-disabled mode
- **Dynamic Resource Management**: Webhook endpoints only registered when needed
- **Enhanced Admin Interface**: Webhook secret field automatically disabled when webhooks off

**🔒 Critical Security Fixes:**
- **Fixed SQL injection vulnerability** in uninstall script using proper table name escaping
- **Comprehensive file upload security** with path traversal prevention, MIME validation, and extension blocking
- **Debug data protection** with API key redaction and sensitive information sanitization
- **Enhanced IP detection security** with proxy support and validation against private ranges

**🛡️ Security Enhancements:**
- **Multi-layered file protection**: Size limits (10MB), allowlist MIME validation, double extension detection
- **Debug sanitization system**: Recursive sensitive data scanning and redaction in API logs
- **Secure IP detection**: Proper proxy header handling with comprehensive validation
- **Complete uninstall cleanup**: All plugin data including queue table properly removed

**🐛 Bug Fixes:**
- Fixed missing queue table in uninstall cleanup process
- Improved error logging with better context and security-aware debugging
- Enhanced webhook IP logging with secure detection methods
- **Fixed blank confirmation popup** when deleting email log entries (missing JavaScript localization string)

**🎯 UI Improvements:**
- **Enhanced User Experience**: Proper confirmation dialogs for email log deletion with clear messaging
- **JavaScript Localization**: Complete localization strings for all admin interface interactions
- **Queue Management**: Added comprehensive queue item management functionality with deletion capabilities

**📊 Security Rating**: Upgraded from B+ to A+ (Excellent) after comprehensive security audit

### Version 2.0.0 - Major Security & Performance Release
**🔒 Security Enhancements:**
- **Enterprise-grade API key encryption** using AES-256-GCM with authentication tags
- **Secure form handling** - API keys never exposed in HTML source or browser tools
- **Email header injection prevention** with comprehensive sanitization
- **Enhanced webhook signature verification** fixed to work with Emailit's actual format
- **Professional security UI** with visual indicators and safe error handling

**⚡ Performance & Reliability:**
- **Asynchronous email processing** using WordPress cron-based queue system
- **Enhanced error handling** with conditional debug logging based on WP_DEBUG
- **Improved webhook processing** with proper domain extraction and signature verification
- **Queue management interface** with real-time statistics and manual processing
- **Background processing** for high-volume email sending

**🛠️ Developer Experience:**
- **Enhanced admin interface** with professional queue management
- **Comprehensive debugging** with detailed logging when WP_DEBUG enabled
- **Better plugin conflict detection** with enhanced compatibility warnings
- **Improved code organization** with better separation of concerns
- **Backward compatibility** for existing installations and legacy encrypted keys

**🐛 Bug Fixes:**
- Fixed critical webhook signature verification failure with Emailit's timestamp.body format
- Fixed email domain extraction from RFC format addresses (Name <email@domain.com>)
- Fixed critical error in WordPress email sending with proper type checking
- Fixed API key field security to never show actual keys in admin interface
- Enhanced error reporting with better context and user-friendly messages

### Version 1.0.3
- **Fixed webhook secret field to be user-editable**
- **Updated webhook secret to be provided by Emailit instead of auto-generated**
- **Enhanced webhook configuration with clearer instructions**

### Version 1.0.2
- **Fixed admin tab navigation jQuery syntax errors**
- **Resolved critical PHP parse errors in settings template**
- **Fixed empty admin panel content issue**
- **Improved admin class initialization and settings registration**
- **Enhanced tab switching functionality with proper URL handling**
- **Added safe API key validation with error handling**
- **Resolved duplicate plugin detection from debug backup files**
- **Enhanced admin interface user experience and stability**

### Version 1.0.1
- PHP 8.4 compatibility fixes
- Updated minimum PHP requirement to 8.0
- Fixed nullable parameter type declarations
- Improved type safety for method parameters
- Fixed class loading issues during plugin activation
- Enhanced debugging capabilities

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

Developed for integration with Emailit email service API by Allen Pooley.

---

**Need Help?** Check the troubleshooting section, enable debug logging, or use the built-in test functionality to diagnose issues.