# MailPoet Integration Guide

## Overview

The Emailit MailPoet integration provides seamless email management between Emailit and MailPoet, enabling advanced bounce handling, subscriber synchronization, and transactional email management.

## Features

### ✅ Available Features

1. **MailPoet Detection & Compatibility**
   - Automatic detection of MailPoet plugin
   - Version compatibility checking (requires MailPoet 5.0+)
   - Integration status monitoring

2. **Hook Priority Management**
   - Emailit hooks fire before MailPoet's hooks
   - Prevents conflicts in email handling
   - Maintains proper email routing

3. **Conflict Prevention**
   - Detects MailPoet's transactional email settings
   - Provides admin notices for potential conflicts
   - Configurable override options

4. **Bounce Synchronization**
   - Syncs bounce data from Emailit to MailPoet subscribers
   - Configurable bounce actions (mark as bounced, unsubscribe, track only)
   - Soft bounce threshold management
   - Complaint handling

5. **Subscriber Engagement Tracking**
   - Updates MailPoet subscriber engagement data
   - Tracks delivery success/failure
   - Maintains bounce history

6. **Transactional Email Override**
   - Option to override MailPoet's transactional email handling
   - Ensures all WordPress emails go through Emailit
   - Maintains MailPoet's newsletter functionality

### ❌ Limitations

1. **Custom Sending Method Registration**
   - MailPoet does not provide a public API for custom sending methods
   - Cannot register Emailit as a MailPoet sending method option
   - This is a limitation of MailPoet's current architecture

## Installation & Setup

### Prerequisites

1. **MailPoet Plugin**
   - Version 5.0 or higher
   - Properly installed and activated
   - At least one subscriber list configured

2. **Emailit Integration**
   - Latest version with MailPoet integration support
   - API key configured
   - Webhook endpoint set up

### Configuration Steps

1. **Enable Integration**
   ```
   Go to Emailit Settings → MailPoet Tab
   Check "Enable MailPoet Integration"
   ```

2. **Configure Bounce Handling**
   ```
   - Enable "Sync Bounce Data to MailPoet Subscribers"
   - Set "Hard Bounce Action" (mark_bounced, unsubscribe, track_only)
   - Configure "Soft Bounce Threshold" (1-20)
   - Set "Complaint Action" (mark_complained, unsubscribe, track_only)
   ```

3. **Configure Transactional Emails**
   ```
   - Enable "Override MailPoet Transactional Emails" (recommended)
   - This ensures all WordPress emails go through Emailit
   ```

4. **Test Integration**
   ```
   - Click "Test MailPoet Integration"
   - Click "Test Bounce Synchronization"
   - Verify all tests pass
   ```

## Configuration Options

### Integration Settings

| Setting | Description | Default | Options |
|---------|-------------|---------|---------|
| Enable MailPoet Integration | Master switch for all MailPoet features | Disabled | Enabled/Disabled |
| Override MailPoet Transactional Emails | Route all WordPress emails through Emailit | Enabled | Enabled/Disabled |
| Sync Bounce Data to MailPoet Subscribers | Automatically update subscriber status on bounces | Enabled | Enabled/Disabled |
| Sync Subscriber Engagement | Update engagement data from webhook events | Enabled | Enabled/Disabled |

### Bounce Handling Settings

| Setting | Description | Default | Options |
|---------|-------------|---------|---------|
| Hard Bounce Action | Action for hard bounces | Mark as Bounced | Mark as Bounced, Unsubscribe, Track Only |
| Soft Bounce Threshold | Number of soft bounces before action | 5 | 1-20 |
| Complaint Action | Action for spam complaints | Mark as Complained | Mark as Complained, Unsubscribe, Track Only |

## How It Works

### Email Flow

1. **Newsletter Emails**
   - MailPoet sends newsletters using its configured sending method
   - Emailit does not interfere with newsletter sending
   - Bounce data is synchronized back to MailPoet

2. **Transactional Emails**
   - If override is enabled: All WordPress emails go through Emailit
   - If override is disabled: MailPoet handles its own transactional emails
   - Emailit handles other WordPress emails

3. **Bounce Processing**
   - Emailit receives bounce webhooks
   - Bounce data is classified (hard/soft)
   - MailPoet subscribers are updated based on configuration
   - Engagement data is tracked

### Hook Priority

```
Priority 1: Emailit pre_wp_mail filter
Priority 10: MailPoet plugins_loaded action
```

This ensures Emailit processes emails before MailPoet when both are active.

## Testing

### Integration Test

Run the comprehensive test suite:

```bash
# Via WP-CLI
wp eval-file emailit-integration/test-mailpoet-integration.php

# Via WordPress admin
Go to Emailit Settings → MailPoet Tab → Test Integration
```

### Test Scenarios

1. **MailPoet Detection**
   - Verifies MailPoet is installed and compatible
   - Checks version requirements

2. **Integration Initialization**
   - Tests integration class loading
   - Verifies component initialization

3. **Hook Priority**
   - Confirms Emailit hooks fire before MailPoet
   - Checks for potential conflicts

4. **Conflict Prevention**
   - Tests transactional email override
   - Verifies conflict detection

5. **Bounce Synchronization**
   - Tests subscriber lookup
   - Verifies bounce data processing

6. **Settings Integration**
   - Confirms all settings are registered
   - Tests configuration persistence

7. **Error Handling**
   - Tests error mapping functionality
   - Verifies proper error reporting

## Troubleshooting

### Common Issues

1. **MailPoet Not Detected**
   ```
   Issue: "MailPoet plugin is not installed or not compatible"
   Solution: 
   - Ensure MailPoet 5.0+ is installed and activated
   - Check plugin compatibility
   - Verify MailPoet is properly configured
   ```

2. **Integration Not Working**
   ```
   Issue: Integration enabled but not functioning
   Solution:
   - Check MailPoet version compatibility
   - Verify Emailit API key is configured
   - Run integration tests
   - Check error logs
   ```

3. **Bounce Sync Not Working**
   ```
   Issue: Bounces not syncing to MailPoet
   Solution:
   - Verify webhook endpoint is configured
   - Check bounce sync is enabled
   - Ensure MailPoet has subscribers
   - Test bounce synchronization
   ```

4. **Email Conflicts**
   ```
   Issue: Emails not routing correctly
   Solution:
   - Check hook priorities
   - Verify transactional email override setting
   - Review MailPoet's transactional email setting
   - Check for other email plugins
   ```

### Debug Mode

Enable debug logging to troubleshoot issues:

```php
// Add to wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Check logs in /wp-content/debug.log
```

### Support

For additional support:

1. Check the integration test results
2. Review error logs
3. Verify configuration settings
4. Test with default settings
5. Contact support with test results

## Best Practices

### Configuration

1. **Enable Integration Gradually**
   - Start with bounce sync only
   - Test thoroughly before enabling transactional override
   - Monitor email delivery

2. **Bounce Handling**
   - Use "Mark as Bounced" for hard bounces
   - Set appropriate soft bounce threshold (5-10)
   - Monitor bounce rates

3. **Transactional Emails**
   - Enable override for consistent email handling
   - Test all WordPress email functions
   - Monitor delivery rates

### Monitoring

1. **Regular Testing**
   - Run integration tests monthly
   - Monitor bounce rates
   - Check error logs

2. **Performance**
   - Monitor webhook processing time
   - Check database performance
   - Optimize bounce handling settings

## API Reference

### Classes

- `Emailit_MailPoet_Integration` - Main integration class
- `Emailit_MailPoet_Handler` - MailPoet-specific functionality
- `Emailit_MailPoet_Method` - Sending method implementation (unused)
- `Emailit_MailPoet_Error_Mapper` - Error handling
- `Emailit_MailPoet_Subscriber_Sync` - Subscriber synchronization

### Hooks

- `emailit_webhook_received` - Webhook event processing
- `pre_wp_mail` - Email interception (priority 1)
- `plugins_loaded` - Integration initialization

### Settings

All settings are prefixed with `emailit_mailpoet_` and stored in WordPress options.

## Changelog

### Version 1.0.0
- Initial MailPoet integration
- Bounce synchronization
- Subscriber engagement tracking
- Transactional email override
- Comprehensive testing suite
- Admin interface integration

## Future Enhancements

1. **Custom Sending Method**
   - If MailPoet adds public API support
   - Register Emailit as sending method option

2. **Advanced Analytics**
   - Detailed bounce reporting
   - Subscriber engagement metrics
   - Performance monitoring

3. **Automation**
   - Automated bounce handling rules
   - Subscriber segmentation
   - Campaign optimization

## Conclusion

The MailPoet integration provides powerful email management capabilities while respecting MailPoet's architecture limitations. The focus on bounce synchronization and transactional email handling ensures reliable email delivery and subscriber management.

For the best experience:
- Enable bounce synchronization
- Use transactional email override
- Monitor integration health regularly
- Keep both plugins updated
