# Changelog

All notable changes to the Emailit Integration plugin will be documented in this file.

## [3.0.4] - 2025-01-15

### Fixed
- **Hook Priority Conflict**: Resolved false "High Priority Email Filter" warnings in admin dashboard
- **Conflict Detection Logic**: Fixed conflict detection to properly exclude Emailit's own functions
- **Priority Check Accuracy**: Updated priority comparison from `< 10` to `< 5` to match actual implementation

### Enhanced
- **Conditional Hook Registration**: Hooks now only register when Emailit is properly configured (API key present)
- **Performance Optimization**: Reduced unnecessary hook processing when plugin isn't configured
- **Hook Management**: Added `reinit_hooks()` method for dynamic hook management

### Technical Details
- Added API key check in `init_hooks()` to prevent unnecessary hook registration
- Improved conflict detection logic to exclude both `emailit` and `Emailit` function names
- Updated priority comparison logic to match actual hook priority (5)
- Added case-insensitive filtering for better conflict detection accuracy

## [3.0.3] - 2025-01-15

### Fixed
- **FluentCRM Bounce Forwarding**: Fixed critical issue where bounce events from Emailit were not being forwarded to FluentCRM subscribers
- **Email Address Parsing**: Resolved parsing issues with FluentCRM's "Name <email@domain.com>" format emails
- **Hook Priority**: Lowered `pre_wp_mail` hook priority from 10 to 5 to ensure proper interception of FluentCRM emails

### Added
- **Comprehensive Bounce Handling**: Added complete bounce forwarding system from Emailit to FluentCRM
- **Bounce Type Classification**: Implemented hard/soft/complaint bounce type detection and handling
- **FluentCRM Subscriber Lookup**: Added robust email address extraction and subscriber matching
- **Metadata Storage**: Enhanced FluentCRM subscriber meta with bounce reasons, dates, and types
- **Soft Bounce Escalation**: Added configurable soft bounce threshold with automatic escalation to hard bounce

### Enhanced
- **FluentCRM Integration**: Improved compatibility with FluentCRM's bounce handling system
- **Error Handling**: Streamlined validation and error messages for better reliability
- **Debug Logging**: Cleaned up excessive debug output while maintaining essential error logging
- **Code Structure**: Optimized code organization and removed redundant logging

### Technical Details
- Added `forward_bounce_to_fluentcrm()` method for processing Emailit bounce webhooks
- Implemented `extract_email_address_from_webhook()` for robust email extraction
- Created `determine_bounce_type_from_status()` for intelligent bounce classification
- Added `update_fluentcrm_subscriber_status()` for proper FluentCRM integration
- Enhanced `sanitize_email_address()` to handle complex email formats

## [3.0.2] - 2024-12-XX

### Fixed
- Squished some bugs: Fixed various issues and improved overall stability

## [3.0.1] - 2024-12-XX

### Fixed
- **Header Congestion**: Resolved duplicate webhook alert messages in admin settings header
- **Layout Issues**: Enhanced message box widths and spacing for better readability
- **Admin-AJAX Calls**: Fixed issues related to admin-ajax calls and health views
- **Responsive Design**: Better mobile layout and responsive design improvements
- **Webhook Alerts**: Added proper dismissal functionality and duplicate prevention

## [3.0.0] - 2024-12-XX

### Added
- **Power User Mode System**: Customizable interface complexity with user-specific preferences
- **Progressive Disclosure Design**: Collapsible advanced sections with smooth animations
- **Simplified Tab Structure**: Consolidated navigation with 3 clear sections
- **Real-Time Status Monitoring**: Live API status, queue processing, and webhook activity
- **Contextual Help System**: Smart tooltips and user-friendly explanations
- **Enhanced Webhook Management**: Improved webhook configuration and monitoring
- **Advanced Logging**: Comprehensive email logging with detailed status tracking
- **Queue Management**: Email queue system with retry logic and failure handling
- **Health Monitoring**: System health checks and performance monitoring
- **Error Analytics**: Advanced error tracking and reporting
- **Database Optimization**: Query optimization and performance improvements

### Changed
- **Complete UI Overhaul**: Modern, responsive interface with improved usability
- **Enhanced Admin Experience**: Streamlined settings and better organization
- **Improved Performance**: Optimized code structure and database queries
- **Better Error Handling**: More robust error management and user feedback

### Technical Improvements
- **WordPress 5.7+ Compatibility**: Full support for modern WordPress features
- **PHP 8.0+ Support**: Optimized for latest PHP versions
- **Enhanced Security**: Improved input validation and sanitization
- **Better Integration**: Seamless WordPress and third-party plugin compatibility
## [3.1.0] - 2025-11-30

### Added
- WooCommerce audience opt-in (checkout checkbox) and migration tool to subscribe past buyers.
- WP-CLI coverage for queue, resend, stats, webhook testing, logs clean/export, and test email.
- Configurable webhook payload retention (0–7 days) with truncation by default.

### Changed
- Webhook ingestion hardened: signature required, replay protection, rate limiting; safer payload logging defaults.
- WooCommerce fallback now resolves decrypted API key via the API component before subscribing.

### Fixed
- Retry flow retains original email payload for queuing in request-thread failures.
