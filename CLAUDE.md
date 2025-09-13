# Emailit WordPress Plugin Implementation Guide

## Project Overview
Create a WordPress plugin that replaces the default wp_mail() function with Emailit's API service, providing email logging, webhook status updates, and a comprehensive admin interface.

## Plugin Structure
```
emailit-integration/
├── emailit-integration.php      # Main plugin file
├── includes/
│   ├── class-emailit-api.php    # API handler class
│   ├── class-emailit-logger.php # Database logging class
│   ├── class-emailit-admin.php  # Admin interface class
│   ├── class-emailit-webhook.php # Webhook handler
│   └── class-emailit-mailer.php # wp_mail override
├── admin/
│   ├── views/
│   │   ├── settings.php         # Settings page template
│   │   └── logs.php             # Email logs page template
│   └── assets/
│       ├── css/
│       │   └── admin.css
│       └── js/
│           └── admin.js
└── uninstall.php                # Cleanup on uninstall
```

## Database Schema
Create two custom tables on activation:

### Table: {prefix}_emailit_logs
```sql
CREATE TABLE {prefix}_emailit_logs (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    email_id varchar(255) DEFAULT NULL,
    token varchar(255) DEFAULT NULL,
    message_id varchar(255) DEFAULT NULL,
    to_email text NOT NULL,
    from_email varchar(255) NOT NULL,
    reply_to varchar(255) DEFAULT NULL,
    subject text NOT NULL,
    body_html longtext,
    body_text longtext,
    status varchar(50) DEFAULT 'pending',
    details text,
    sent_at datetime DEFAULT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_email_id (email_id),
    KEY idx_token (token),
    KEY idx_status (status),
    KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Table: {prefix}_emailit_webhook_logs
```sql
CREATE TABLE {prefix}_emailit_webhook_logs (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    webhook_request_id varchar(255),
    event_id varchar(255),
    event_type varchar(100),
    email_id varchar(255),
    status varchar(50),
    details text,
    raw_payload longtext,
    processed_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_email_id (email_id),
    KEY idx_event_type (event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## Main Plugin File (emailit-integration.php)
```php
<?php
/**
 * Plugin Name: Emailit Integration
 * Description: Integrates WordPress with Emailit email service
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: emailit-integration
 */

// Security check
if (!defined('ABSPATH')) exit;

// Define constants
define('EMAILIT_VERSION', '1.0.0');
define('EMAILIT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('EMAILIT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('EMAILIT_API_ENDPOINT', 'https://api.emailit.com/v1/emails');

// Autoload classes
spl_autoload_register(function ($class) {
    // Implementation
});

// Activation/Deactivation hooks
register_activation_hook(__FILE__, 'emailit_activate');
register_deactivation_hook(__FILE__, 'emailit_deactivate');

// Initialize plugin
add_action('plugins_loaded', 'emailit_init');
```

## Core Implementation Details

### 1. API Handler Class (class-emailit-api.php)
- Send emails via Emailit API using wp_remote_post()
- Handle authentication with Bearer token
- Process attachments (base64 encode content)
- Return response with email ID and token for tracking
- Implement retry logic for failed sends (max 3 attempts)
- Add timeout handling (30 seconds default)

### 2. wp_mail Override (class-emailit-mailer.php)
- Use 'phpmailer_init' action to replace PHPMailer with custom implementation
- OR use 'pre_wp_mail' filter (WordPress 5.7+) to completely bypass wp_mail
- Parse wp_mail arguments to Emailit format
- Handle both HTML and plain text emails
- Support CC/BCC headers
- Maintain backwards compatibility with wp_mail return values

### 3. Admin Settings Page
Settings to implement:
- API Key (encrypted in database using WordPress salts)
- From Name (default sender name)
- From Email (default sender email)
- Reply-To Email (default reply-to)
- Enable/Disable logging
- Log retention period (days)
- Webhook secret (for validation)
- Test email functionality

### 4. Webhook Handler (class-emailit-webhook.php)
- Register REST API endpoint: /wp-json/emailit/v1/webhook
- Validate webhook signatures if implemented
- Parse webhook payload
- Update email log status based on event type
- Store webhook event in separate log table
- Implement rate limiting to prevent abuse

### 5. Email Logs Interface
- WP_List_Table implementation for logs display
- Columns: Date, To, From, Subject, Status, Actions
- Filters: Status, Date range, Search
- Bulk actions: Delete, Resend
- Detail view: Show full email content and webhook history
- Export functionality (CSV)

## Security Considerations
1. Sanitize all inputs using WordPress functions:
   - sanitize_email() for email addresses
   - sanitize_text_field() for text inputs
   - wp_kses_post() for HTML content
2. Escape all outputs:
   - esc_html() for text
   - esc_attr() for attributes
   - esc_url() for URLs
3. Use nonces for all forms and AJAX requests
4. Capability checks: require 'manage_options' for settings
5. Validate webhook requests (IP whitelist or signature)
6. Rate limit webhook endpoint
7. Encrypt API key in database

## Performance Optimizations
1. Use WordPress transients for caching:
   - Cache API key validation (5 minutes)
   - Cache email templates if used
2. Implement background processing for:
   - Large email batches
   - Log cleanup
3. Database indexes on frequently queried columns
4. Pagination for log displays (50 per page default)
5. AJAX loading for log details

## Error Handling
1. Fallback to wp_mail() if Emailit API fails
2. Log all API errors with full context
3. Admin notices for configuration issues
4. User-friendly error messages
5. Implement exponential backoff for retries

## Testing Checklist
- [ ] Single email send
- [ ] Bulk email send (newsletter plugins)
- [ ] Attachments handling
- [ ] HTML vs plain text emails
- [ ] International characters (UTF-8)
- [ ] Webhook processing
- [ ] Log rotation
- [ ] API key validation
- [ ] Fallback mechanism
- [ ] WordPress multisite compatibility

## WordPress Hooks to Implement

### Actions
- `emailit_before_send` - Before sending email
- `emailit_after_send` - After email sent
- `emailit_webhook_received` - When webhook received
- `emailit_status_updated` - When email status changes

### Filters
- `emailit_api_args` - Modify API request args
- `emailit_log_data` - Modify data before logging
- `emailit_should_send` - Allow/prevent sending
- `emailit_from_email` - Override from email
- `emailit_from_name` - Override from name

## Admin Notices
Display notices for:
- Missing API key
- Invalid API key
- Failed webhook URL registration
- Log table creation failures
- Low API quota (if applicable)

## Uninstall Cleanup
On plugin deletion:
1. Remove all database tables
2. Delete all options with prefix 'emailit_'
3. Clear any scheduled cron jobs
4. Remove transients

## CLI Commands (Optional)
Register WP-CLI commands:
- `wp emailit test` - Send test email
- `wp emailit logs clean` - Clean old logs
- `wp emailit stats` - Show email statistics
- `wp emailit webhook test` - Test webhook endpoint

## Implementation Notes
1. Use WordPress coding standards
2. Implement proper internationalization (i18n)
3. Add inline documentation for all methods
4. Include README.txt for WordPress.org repository
5. Consider adding support for popular form plugins
6. Implement email template system (future enhancement)

## Code Quality Requirements
- PHPDoc blocks for all functions/methods
- Escape late, sanitize early principle
- Single responsibility for each class
- Use dependency injection where appropriate
- Follow WordPress naming conventions
- Minimum PHP version: 7.4
- Minimum WordPress version: 5.7

## Deployment Considerations
1. Test with popular plugins:
   - WooCommerce
   - Contact Form 7
   - Gravity Forms
   - Newsletter plugins
2. Check memory usage with large recipient lists
3. Verify multisite network compatibility
4. Test with various hosting environments
5. Include upgrade routines for database changes

## Implementation TODO List ✅ **COMPLETED**

### Phase 1: Core Structure & Database ✅
- [x] Set up plugin structure and main plugin file
- [x] Create database schema and activation hooks

### Phase 2: Core Functionality ✅
- [x] Implement API handler class for Emailit integration
- [x] Create wp_mail override functionality

### Phase 3: Admin Interface ✅
- [x] Build admin settings interface
- [x] Implement email logging system

### Phase 4: Webhook Integration ✅
- [x] Create webhook handler for status updates
- [x] Build email logs admin interface

### Phase 5: Security & Polish ✅
- [x] Add security measures and error handling
- [x] Implement uninstall cleanup functionality

### Phase 6: Testing & Validation ✅
- [x] Test plugin functionality and WordPress compatibility

## 🎉 **Implementation Status: COMPLETE**

All planned features have been successfully implemented:

**✅ Core Files Created:**
- `emailit-integration.php` - Main plugin file with complete lifecycle management
- `uninstall.php` - Comprehensive cleanup script
- 5 core classes in `includes/` directory
- 3 admin view templates with full functionality
- Professional CSS and JavaScript assets

**✅ Key Features Delivered:**
- Complete wp_mail() replacement with Emailit API
- Real-time webhook status tracking
- Comprehensive admin interface with email logs
- Professional security measures throughout
- Fallback mechanisms and error handling
- Database logging with retention management
- Email statistics and performance tracking

**✅ Ready for Production:**
- WordPress coding standards compliance
- Security best practices implemented
- Comprehensive error handling and logging
- Complete documentation (README.md)
- Professional user interface design
