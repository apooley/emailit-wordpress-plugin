# Emailit Integration for WordPress

**Version 2.6.0** - A comprehensive WordPress plugin that replaces the default `wp_mail()` function with Emailit's API service, providing enhanced email delivery, logging, webhook status updates, FluentCRM integration, database optimization, advanced error handling, and a complete admin interface with enterprise-grade security.

## 🚀 Recent Updates

### Version 2.6.0 - Advanced Error Handling System

### 🛡️ **Advanced Error Handling & Recovery**
- **Enhanced Circuit Breaker**: Improved failure detection and automatic recovery mechanisms
- **Intelligent Retry System**: Advanced retry mechanisms with exponential backoff and jitter
- **Error Analytics**: Comprehensive error tracking, pattern detection, and trend analysis
- **Multi-Channel Notifications**: Email, admin notices, webhooks, and Slack integration
- **Error Recovery**: Automated recovery strategies for different error types
- **Debugging Tools**: Enhanced debugging and troubleshooting capabilities

### 📊 **Error Analytics & Monitoring**
- **Pattern Detection**: Automatic detection of error patterns and correlations
- **Trend Analysis**: Real-time error trend monitoring and anomaly detection
- **Frequency Monitoring**: High-frequency error detection and alerting
- **Error Insights**: Intelligent recommendations based on error analysis
- **Performance Metrics**: Error resolution rates and response time tracking

### 🔧 **Admin Interface Enhancements**
- **Error Handling Settings**: New "Advanced Error Handling" settings section
- **Real-time Status**: Live error handling status and circuit breaker state
- **Error Statistics**: Comprehensive error statistics and insights dashboard
- **Configuration Options**: Granular control over error handling behavior
- **Notification Settings**: Configurable notification channels and preferences

### ⚡ **Production Reliability**
- **Cascading Failure Prevention**: Circuit breaker prevents system-wide failures
- **Automatic Recovery**: Self-healing mechanisms for transient errors
- **Data Retention**: Configurable error data retention and cleanup
- **Memory Optimization**: Efficient error tracking and storage
- **Cron Integration**: Automated error analysis and cleanup processes

---

### Version 2.5.0 - FluentCRM Action Mapping & Soft Bounce Management

### 🎯 **FluentCRM Action Mapping System**
- **Intelligent Bounce Processing**: Automatic FluentCRM subscriber actions based on bounce classifications
- **Action Mapping**: Maps bounce types to FluentCRM subscriber status updates
- **Confidence Thresholds**: Configurable confidence levels for automatic actions
- **Auto-Create Subscribers**: Optional automatic subscriber creation for bounced emails
- **Comprehensive Logging**: Detailed action logging and error tracking

### 📊 **Soft Bounce Threshold Management**
- **Configurable Thresholds**: Customizable soft bounce limits (1-50 bounces)
- **Time Window Management**: Configurable counting periods (1-30 days)
- **Automatic Escalation**: Soft bounces escalate to hard bounces after threshold
- **Success Reset**: Automatic bounce count reset on successful deliveries
- **Bounce History**: Detailed tracking of bounce events with timestamps and reasons

### 🔧 **Enhanced Admin Interface**
- **Real-time Statistics**: Live dashboard showing bounce metrics and threshold monitoring
- **Management Tools**: AJAX-powered bounce management and subscriber reset functions
- **Visual Indicators**: Color-coded warnings for subscribers approaching bounce limits
- **Settings Panel**: Comprehensive configuration interface for all bounce management options

### ⚡ **Performance & Reliability**
- **Conditional Loading**: All FluentCRM functionality only loads when FluentCRM is available
- **Error Handling**: Graceful degradation when FluentCRM is not installed
- **Database Optimization**: Efficient queries for bounce statistics and subscriber management
- **Memory Management**: Optimized data structures for large subscriber lists

### Version 2.4.0 - Database Optimization & Performance Enhancement

### 🚀 **Database Performance Optimization**
- **Strategic Indexing**: Added 15+ performance indexes across all database tables
- **Query Optimization**: Implemented intelligent query caching and optimization
- **Database Tools**: New admin interface for database maintenance and monitoring
- **Performance Monitoring**: Real-time database statistics and slow query analysis
- **Automatic Cleanup**: Orphaned record removal and old data archiving tools

### 📊 **Enhanced Admin Interface**
- **Performance Status**: Simple performance indicators and quick maintenance tools in main settings
- **Performance Metrics**: Real-time database size, row counts, and query performance
- **Maintenance Tools**: Table optimization, index management, and cleanup utilities
- **Query Analysis**: Slow query detection and optimization recommendations

### ⚡ **Query Performance Improvements**
- **Intelligent Caching**: 2-5 minute cache for frequently accessed data
- **Optimized Queries**: 3-5x faster database queries with proper indexing
- **Full-Text Search**: Native MySQL full-text search for email content
- **Pagination Optimization**: Efficient handling of large datasets (100k+ records)

### 🔧 **Technical Enhancements**
- **Migration System**: Automatic database schema updates (Version 2.1.0 migration)
- **Backward Compatibility**: Safe migration with error handling and logging
- **Memory Optimization**: Reduced memory usage with optimized query patterns
- **Scalability**: Better performance with large email datasets

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
- **📋 Emailit Log**: Searchable logs with filtering and export functionality
- **🧪 Testing Tools**: Built-in email testing and diagnostic functionality
- **⚠️ Conflict Detection**: Automatic detection of conflicting plugins
- **🔄 Status Tracking**: Real-time webhook status updates
- **⚡ Performance Status**: Simple performance indicators and quick maintenance tools
- **📊 Performance Monitoring**: Real-time database statistics and query analysis

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
6. **Review Logs**: Check the Emailit Log page to monitor email delivery

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
$db_optimizer = emailit_get_component('db_optimizer');
$query_optimizer = emailit_get_component('query_optimizer');
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

### Database Optimization

```php
// Get database optimizer
$db_optimizer = emailit_get_component('db_optimizer');
$query_optimizer = emailit_get_component('query_optimizer');

// Optimize database tables
$results = $db_optimizer->optimize_tables();

// Add performance indexes
$indexes = $db_optimizer->add_performance_indexes();

// Get performance statistics
$stats = $db_optimizer->get_performance_stats();

// Clean up orphaned records
$cleaned = $db_optimizer->cleanup_orphaned_records();

// Archive old records (90+ days)
$archived = $db_optimizer->archive_old_records(90);

// Get optimized email logs with caching
$logs = $query_optimizer->get_email_logs(array(
    'page' => 1,
    'per_page' => 20,
    'status' => 'sent',
    'search' => 'example.com'
));

// Get performance metrics
$metrics = $query_optimizer->get_performance_metrics();

// Clear query cache
$query_optimizer->clear_cache();
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

### Emailit Log

View and manage all sent emails:

- **Access**: Tools → Emailit Log
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

### Performance Status

Simple performance indicators and maintenance tools:

- **Access**: Settings → Emailit → Performance tab
- **Features**:
  - Database optimization status indicators
  - Query caching status
  - Index status monitoring
  - Quick maintenance tools (clean logs, optimize database, clear cache)

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
- **Strategic Indexing**: 15+ performance indexes across all tables for 3-5x faster queries
- **Query Optimization**: Intelligent caching and optimized SQL with minimal database impact
- **Full-Text Search**: Native MySQL full-text search for email content and subject lines
- **Log Rotation**: Automatic cleanup prevents database bloat with configurable retention
- **Pagination**: Admin interface uses pagination for large datasets (100k+ records)
- **Performance Monitoring**: Real-time database statistics and slow query analysis
- **Maintenance Tools**: Complete database optimization and cleanup utilities

### Caching
- **API Key Validation**: Cached for 5 minutes to reduce API calls
- **Transient Usage**: WordPress transients for temporary data storage
- **Memory Management**: Efficient memory usage for large email batches

## Advanced Error Handling

### Circuit Breaker Protection
- **Automatic Failure Detection**: Monitors consecutive failures and triggers circuit breaker
- **Configurable Thresholds**: Customizable failure limits and timeout periods
- **Automatic Recovery**: Self-healing mechanisms that restore service when conditions improve
- **Status Monitoring**: Real-time circuit breaker status in admin interface
- **Cascading Failure Prevention**: Prevents system-wide failures during API outages

### Intelligent Retry System
- **Exponential Backoff**: Smart retry delays that increase with each attempt
- **Jitter Implementation**: Random delay variation to prevent thundering herd problems
- **Error-Specific Strategies**: Different retry approaches for different error types
- **Retry Scheduling**: WordPress cron-based retry scheduling for failed operations
- **Retry Analytics**: Comprehensive tracking of retry success rates and patterns

### Error Analytics & Pattern Detection
- **Real-time Error Tracking**: Comprehensive error logging with context and metadata
- **Pattern Recognition**: Automatic detection of recurring error patterns and correlations
- **Trend Analysis**: Historical error trend monitoring and anomaly detection
- **Frequency Monitoring**: High-frequency error detection with automatic alerting
- **Error Insights**: Intelligent recommendations based on error analysis and patterns

### Multi-Channel Notifications
- **Email Notifications**: Rich HTML email alerts for critical errors and system issues
- **Admin Notices**: Real-time WordPress admin notices with dismissible alerts
- **Webhook Integration**: External system notifications via configurable webhooks
- **Slack Integration**: Direct Slack notifications for development and monitoring teams
- **Escalation Management**: Automatic escalation for unresolved critical errors

### Error Recovery Strategies
- **Automated Recovery**: Self-healing mechanisms for transient and recoverable errors
- **Context-Aware Handling**: Error-specific recovery strategies based on error type and context
- **Recovery Tracking**: Comprehensive logging of recovery attempts and outcomes
- **Fallback Mechanisms**: Graceful degradation when primary systems fail
- **Health Monitoring**: Integration with health monitoring system for proactive issue detection

### Debugging & Troubleshooting
- **Enhanced Error Context**: Detailed error information including stack traces and context
- **Error Statistics Dashboard**: Real-time error metrics and resolution rates
- **Pattern Analysis**: Visual representation of error patterns and trends
- **Debug Tools**: Comprehensive debugging utilities for development and troubleshooting
- **Error History**: Complete audit trail of all error events and recovery attempts

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

// Database optimization filters
add_filter('emailit_query_cache_duration', 'custom_cache_duration', 10, 1);
add_filter('emailit_database_optimization_settings', 'custom_optimization_settings', 10, 1);

// Error handling filters
add_filter('emailit_should_send_notification', 'custom_notification_logic', 10, 3);
add_filter('emailit_retry_strategy', 'custom_retry_strategy', 10, 2);
add_filter('emailit_error_context', 'add_custom_error_context', 10, 2);
add_filter('emailit_circuit_breaker_threshold', 'custom_circuit_breaker_threshold', 10, 1);
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

// Database optimization events
add_action('emailit_database_optimized', 'database_optimized', 10, 1);
add_action('emailit_indexes_added', 'indexes_added', 10, 1);
add_action('emailit_cache_cleared', 'cache_cleared', 10, 1);

// Error handling events
add_action('emailit_error_occurred', 'handle_error_event', 10, 3);
add_action('emailit_critical_error', 'handle_critical_error', 10, 3);
add_action('emailit_error_escalation', 'handle_error_escalation', 10, 2);
add_action('emailit_high_frequency_error', 'handle_high_frequency_error', 10, 2);
add_action('emailit_error_anomaly_detected', 'handle_error_anomaly', 10, 1);
add_action('emailit_circuit_breaker_opened', 'handle_circuit_breaker_opened', 10, 1);
add_action('emailit_circuit_breaker_closed', 'handle_circuit_breaker_closed', 10, 1);
add_action('emailit_retry_scheduled', 'handle_retry_scheduled', 10, 3);
add_action('emailit_retry_completed', 'handle_retry_completed', 10, 3);
add_action('emailit_retry_failed', 'handle_retry_failed', 10, 3);
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

6. **Database Performance Issues**
   - Use Performance Status tools (Settings → Emailit → Performance tab)
   - Run table optimization to reclaim space
   - Add missing performance indexes
   - Clean up orphaned records
   - Archive old email content
   - Monitor slow queries and optimize

### Debug Steps

1. **Enable Debug Logging**: Add `WP_DEBUG` constants to wp-config.php
2. **Use Built-in Tests**: Test API connectivity and webhook functionality
3. **Check Emailit Log**: Review detailed error messages and context
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

## Developer Reference

### Hooks and Filters

The Emailit Integration plugin provides extensive hooks and filters for developers to customize behavior and integrate with other plugins.

#### **Action Hooks**

##### **Email Processing Events**
```php
// Before email is sent via Emailit API
add_action('emailit_before_send', 'my_before_send_handler', 10, 1);
function my_before_send_handler($email_data) {
    // Modify email data before sending
    error_log('Sending email: ' . $email_data['to']);
}

// After email is sent successfully
add_action('emailit_after_send', 'my_after_send_handler', 10, 3);
function my_after_send_handler($email_data, $response, $message_id) {
    // Handle successful email sending
    error_log('Email sent successfully: ' . $message_id);
}

// When email sending fails
add_action('emailit_send_failed', 'my_send_failed_handler', 10, 3);
function my_send_failed_handler($email_data, $error, $context) {
    // Handle email sending failures
    error_log('Email send failed: ' . $error->get_error_message());
}
```

##### **Webhook Events**
```php
// When webhook is received
add_action('emailit_webhook_received', 'my_webhook_handler', 10, 2);
function my_webhook_handler($webhook_data, $signature_valid) {
    // Process webhook data
    if ($signature_valid) {
        error_log('Valid webhook received: ' . $webhook_data['event']);
    }
}

// When email status is updated via webhook
add_action('emailit_status_updated', 'my_status_updated_handler', 10, 3);
function my_status_updated_handler($message_id, $status, $webhook_data) {
    // Handle status updates
    error_log("Email {$message_id} status updated to: {$status}");
}
```

##### **Queue Processing Events**
```php
// When queue batch is processed
add_action('emailit_queue_processed', 'my_queue_processed_handler', 10, 2);
function my_queue_processed_handler($batch_size, $processed_count) {
    // Handle queue processing completion
    error_log("Processed {$processed_count} emails from queue");
}

// When queue processing fails
add_action('emailit_queue_failed', 'my_queue_failed_handler', 10, 2);
function my_queue_failed_handler($batch_size, $error) {
    // Handle queue processing failures
    error_log('Queue processing failed: ' . $error->get_error_message());
}
```

##### **Error Handling Events**
```php
// When any error occurs
add_action('emailit_error_occurred', 'my_error_handler', 10, 3);
function my_error_handler($error_code, $error_message, $context) {
    // Handle general errors
    error_log("Error occurred: {$error_code} - {$error_message}");
}

// When critical error occurs
add_action('emailit_critical_error', 'my_critical_error_handler', 10, 3);
function my_critical_error_handler($error_code, $error_message, $context) {
    // Handle critical errors (send alerts, etc.)
    wp_mail(get_option('admin_email'), 'Critical Emailit Error', $error_message);
}

// When circuit breaker opens
add_action('emailit_circuit_breaker_opened', 'my_circuit_breaker_handler', 10, 1);
function my_circuit_breaker_handler($failure_count) {
    // Handle circuit breaker activation
    error_log("Circuit breaker opened after {$failure_count} failures");
}

// When retry is scheduled
add_action('emailit_retry_scheduled', 'my_retry_scheduled_handler', 10, 3);
function my_retry_scheduled_handler($operation, $error_code, $retry_count) {
    // Handle retry scheduling
    error_log("Retry scheduled for {$operation} (attempt {$retry_count})");
}
```

##### **FluentCRM Integration Events**
```php
// When FluentCRM subscriber bounces
add_action('emailit_fluentcrm_subscriber_bounced', 'my_bounce_handler', 10, 4);
function my_bounce_handler($subscriber, $old_status, $bounce_data, $webhook) {
    // Handle FluentCRM subscriber bounces
    error_log("Subscriber {$subscriber->email} bounced: {$bounce_data['reason']}");
}

// When FluentCRM subscriber complains
add_action('emailit_fluentcrm_subscriber_complained', 'my_complaint_handler', 10, 4);
function my_complaint_handler($subscriber, $old_status, $bounce_data, $webhook) {
    // Handle FluentCRM subscriber complaints
    error_log("Subscriber {$subscriber->email} complained");
}

// When FluentCRM status changes
add_action('emailit_fluentcrm_status_changed', 'my_status_change_handler', 10, 4);
function my_status_change_handler($subscriber, $old_status, $new_status, $webhook) {
    // Handle FluentCRM status changes
    error_log("Subscriber {$subscriber->email} status changed: {$old_status} -> {$new_status}");
}
```

#### **Filter Hooks**

##### **Email Data Modification**
```php
// Modify email data before sending
add_filter('emailit_email_data', 'my_email_data_filter', 10, 6);
function my_email_data_filter($email_data, $to, $subject, $message, $headers, $attachments) {
    // Add custom headers or modify email data
    $email_data['custom_field'] = 'custom_value';
    return $email_data;
}

// Control which emails are sent via Emailit
add_filter('emailit_should_send', 'my_should_send_filter', 10, 2);
function my_should_send_filter($should_send, $email_data) {
    // Skip certain emails
    if (strpos($email_data['to'], '@example.com') !== false) {
        return false;
    }
    return $should_send;
}

// Modify API request arguments
add_filter('emailit_api_args', 'my_api_args_filter', 10, 2);
function my_api_args_filter($args, $endpoint) {
    // Add custom headers or modify API requests
    $args['headers']['X-Custom-Header'] = 'custom-value';
    return $args;
}
```

##### **Error Handling Filters**
```php
// Control error notifications
add_filter('emailit_should_send_notification', 'my_notification_filter', 10, 3);
function my_notification_filter($should_send, $error_code, $error_level) {
    // Skip notifications for certain errors
    if ($error_code === 'rate_limit_exceeded') {
        return false;
    }
    return $should_send;
}

// Customize retry strategies
add_filter('emailit_retry_strategy', 'my_retry_strategy_filter', 10, 2);
function my_retry_strategy_filter($strategy, $error_code) {
    // Custom retry strategy for specific errors
    if ($error_code === 'timeout') {
        $strategy['max_retries'] = 5;
        $strategy['base_delay'] = 10;
    }
    return $strategy;
}

// Add custom error context
add_filter('emailit_error_context', 'my_error_context_filter', 10, 2);
function my_error_context_filter($context, $error_code) {
    // Add custom context to error data
    $context['custom_data'] = 'custom_value';
    $context['user_id'] = get_current_user_id();
    return $context;
}

// Customize circuit breaker threshold
add_filter('emailit_circuit_breaker_threshold', 'my_circuit_breaker_filter', 10, 1);
function my_circuit_breaker_filter($threshold) {
    // Adjust circuit breaker threshold
    return 10; // Increase threshold to 10 failures
}
```

##### **Database and Performance Filters**
```php
// Customize query cache duration
add_filter('emailit_query_cache_duration', 'my_cache_duration_filter', 10, 1);
function my_cache_duration_filter($duration) {
    // Extend cache duration
    return 600; // 10 minutes
}

// Customize database optimization settings
add_filter('emailit_database_optimization_settings', 'my_optimization_filter', 10, 1);
function my_optimization_filter($settings) {
    // Modify optimization settings
    $settings['cleanup_days'] = 30;
    return $settings;
}

// Control email exclusions
add_filter('emailit_is_excluded_email', 'my_exclusion_filter', 10, 6);
function my_exclusion_filter($is_excluded, $to, $subject, $message, $headers, $attachments) {
    // Add custom email exclusions
    if (strpos($subject, '[TEST]') !== false) {
        return true;
    }
    return $is_excluded;
}

// Modify queue batch size
add_filter('emailit_queue_batch_size', 'my_batch_size_filter', 10, 1);
function my_batch_size_filter($batch_size) {
    // Adjust queue batch size
    return 25; // Process 25 emails per batch
}
```

##### **FluentCRM Integration Filters**
```php
// Customize bounce notification data
add_filter('emailit_fluentcrm_bounce_notification_data', 'my_bounce_data_filter', 10, 3);
function my_bounce_data_filter($data, $subscriber, $bounce_data) {
    // Add custom data to bounce notifications
    $data['custom_field'] = 'custom_value';
    $data['subscriber_tags'] = $subscriber->tags;
    return $data;
}

// Control FluentCRM action mapping
add_filter('emailit_fluentcrm_should_map_action', 'my_action_mapping_filter', 10, 4);
function my_action_mapping_filter($should_map, $bounce_type, $subscriber, $confidence) {
    // Skip action mapping for certain conditions
    if ($confidence < 80) {
        return false;
    }
    return $should_map;
}
```

#### **Hook Usage Examples**

##### **Custom Email Logging**
```php
// Log all emails to custom table
add_action('emailit_after_send', function($email_data, $response, $message_id) {
    global $wpdb;
    
    $wpdb->insert(
        $wpdb->prefix . 'custom_email_logs',
        array(
            'message_id' => $message_id,
            'to_email' => $email_data['to'],
            'subject' => $email_data['subject'],
            'sent_at' => current_time('mysql'),
            'status' => 'sent'
        )
    );
}, 10, 3);
```

##### **Custom Error Alerting**
```php
// Send custom alerts for critical errors
add_action('emailit_critical_error', function($error_code, $error_message, $context) {
    // Send to external monitoring service
    wp_remote_post('https://your-monitoring-service.com/alerts', array(
        'body' => array(
            'error_code' => $error_code,
            'message' => $error_message,
            'context' => $context,
            'timestamp' => current_time('mysql')
        )
    ));
}, 10, 3);
```

##### **Custom Retry Logic**
```php
// Implement custom retry logic
add_filter('emailit_retry_strategy', function($strategy, $error_code) {
    if ($error_code === 'quota_exceeded') {
        // Wait longer for quota errors
        $strategy['base_delay'] = 3600; // 1 hour
        $strategy['max_retries'] = 1;
    }
    return $strategy;
}, 10, 2);
```

## Changelog

### Version 2.6.0 - Advanced Error Handling System

### 🛡️ **Advanced Error Handling & Recovery**
- **Enhanced Circuit Breaker**: Improved failure detection and automatic recovery mechanisms
- **Intelligent Retry System**: Advanced retry mechanisms with exponential backoff and jitter
- **Error Analytics**: Comprehensive error tracking, pattern detection, and trend analysis
- **Multi-Channel Notifications**: Email, admin notices, webhooks, and Slack integration
- **Error Recovery**: Automated recovery strategies for different error types
- **Debugging Tools**: Enhanced debugging and troubleshooting capabilities

### 📊 **Error Analytics & Monitoring**
- **Pattern Detection**: Automatic detection of error patterns and correlations
- **Trend Analysis**: Real-time error trend monitoring and anomaly detection
- **Frequency Monitoring**: High-frequency error detection and alerting
- **Error Insights**: Intelligent recommendations based on error analysis
- **Performance Metrics**: Error resolution rates and response time tracking

### 🔧 **Admin Interface Enhancements**
- **Error Handling Settings**: New "Advanced Error Handling" settings section
- **Real-time Status**: Live error handling status and circuit breaker state
- **Error Statistics**: Comprehensive error statistics and insights dashboard
- **Configuration Options**: Granular control over error handling behavior
- **Notification Settings**: Configurable notification channels and preferences

### ⚡ **Production Reliability**
- **Cascading Failure Prevention**: Circuit breaker prevents system-wide failures
- **Automatic Recovery**: Self-healing mechanisms for transient errors
- **Data Retention**: Configurable error data retention and cleanup
- **Memory Optimization**: Efficient error tracking and storage
- **Cron Integration**: Automated error analysis and cleanup processes

---

### Version 2.5.0 - FluentCRM Action Mapping & Soft Bounce Management

**🎯 FluentCRM Action Mapping System:**
- **Intelligent Bounce Processing**: Automatic FluentCRM subscriber actions based on bounce classifications
- **Action Mapping**: Maps bounce types to FluentCRM subscriber status updates (hard bounces → unsubscribed, soft bounces → tracked, spam complaints → complained)
- **Confidence Thresholds**: Configurable confidence levels (0-100%) for automatic actions
- **Auto-Create Subscribers**: Optional automatic subscriber creation for bounced emails
- **Comprehensive Logging**: Detailed action logging and error tracking

**📊 Soft Bounce Threshold Management:**
- **Configurable Thresholds**: Customizable soft bounce limits (1-50 bounces, default: 5)
- **Time Window Management**: Configurable counting periods (1-30 days, default: 7)
- **Automatic Escalation**: Soft bounces escalate to hard bounces after threshold exceeded
- **Success Reset**: Automatic bounce count reset on successful deliveries (configurable)
- **Bounce History**: Detailed tracking of bounce events with timestamps, reasons, and classification data

**🔧 Enhanced Admin Interface:**
- **Real-time Statistics**: Live dashboard showing bounce metrics and threshold monitoring
- **Management Tools**: AJAX-powered bounce management and subscriber reset functions
- **Visual Indicators**: Color-coded warnings for subscribers approaching bounce limits
- **Settings Panel**: Comprehensive configuration interface for all bounce management options

**⚡ Performance & Reliability:**
- **Conditional Loading**: All FluentCRM functionality only loads when FluentCRM is available
- **Error Handling**: Graceful degradation when FluentCRM is not installed
- **Database Optimization**: Efficient queries for bounce statistics and subscriber management
- **Memory Management**: Optimized data structures for large subscriber lists

### Version 2.4.0 - Database Optimization & Performance Enhancement

**🚀 Database Performance Optimization:**
- **Strategic Indexing**: Added 15+ performance indexes across all database tables for 3-5x faster queries
- **Query Optimization**: Implemented intelligent query caching and optimization with 2-5 minute cache periods
- **Performance Status**: Simple performance indicators and maintenance tools in main settings
- **Performance Monitoring**: Real-time database statistics and slow query analysis
- **Automatic Cleanup**: Orphaned record removal and old data archiving tools

**📊 Enhanced Admin Interface:**
- **Performance Status Section**: Simple performance indicators and quick maintenance tools
- **Performance Metrics**: Database size, row counts, and query performance monitoring
- **Maintenance Tools**: Table optimization, index management, and cleanup utilities
- **Query Analysis**: Slow query detection and optimization recommendations

**⚡ Query Performance Improvements:**
- **Intelligent Caching**: 2-5 minute cache for frequently accessed data to reduce database load
- **Optimized Queries**: 3-5x faster database queries with proper indexing strategies
- **Full-Text Search**: Native MySQL full-text search for email content and subject lines
- **Pagination Optimization**: Efficient handling of large datasets (100k+ records)

**🔧 Technical Enhancements:**
- **Migration System**: Automatic database schema updates (Version 2.1.0 migration)
- **Backward Compatibility**: Safe migration with error handling and comprehensive logging
- **Memory Optimization**: Reduced memory usage with optimized query patterns
- **Scalability**: Better performance with large email datasets and high-volume sending

**🛠️ Developer Experience:**
- **New Classes**: `Emailit_Database_Optimizer` and `Emailit_Query_Optimizer` for advanced database management
- **Enhanced Logging**: Improved database operation logging and performance monitoring
- **API Extensions**: New methods for database optimization and query analysis
- **Documentation**: Complete documentation for all new database optimization features

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