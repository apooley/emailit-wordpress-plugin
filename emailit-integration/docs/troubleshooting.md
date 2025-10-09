# Troubleshooting Guide

This guide helps you diagnose and resolve common issues with Emailit Integration. Follow these steps to identify and fix problems.

## Quick Diagnostic Checklist

Before diving into specific issues, run through this quick checklist:

- [ ] **Plugin is activated** - Check WordPress plugins page
- [ ] **API key is configured** - Verify in General settings
- [ ] **Test email works** - Send a test email
- [ ] **WordPress version** - Must be 5.7 or higher
- [ ] **PHP version** - Must be 8.0 or higher
- [ ] **Server requirements** - Can make outgoing HTTP requests

## Common Issues and Solutions

### API Connection Problems

**Issue: API Status shows "Disconnected"**

**Possible Causes:**
- Invalid API key
- Network connectivity issues
- Emailit service outage
- Server firewall blocking requests

**Solutions:**
1. **Verify API Key**
   - Check API key in Emailit dashboard
   - Copy and paste key again
   - Ensure no extra spaces or characters

2. **Test Network Connectivity**
   - Try sending a test email
   - Check if other plugins can make HTTP requests
   - Contact your hosting provider

3. **Check Emailit Status**
   - Visit Emailit status page
   - Check for service outages
   - Contact Emailit support if needed

**Issue: "API Key Invalid" Error**

**Solutions:**
1. **Regenerate API Key**
   - Log in to Emailit dashboard
   - Go to Settings > API Keys
   - Generate a new key
   - Update WordPress settings

2. **Check Account Status**
   - Ensure Emailit account is active
   - Verify payment is current
   - Check for account restrictions

### Email Sending Issues

**Issue: Emails Not Sending**

**Possible Causes:**
- API connection problems
- Invalid email addresses
- Server configuration issues
- Plugin conflicts

**Solutions:**
1. **Check API Status**
   - Verify API connection in General tab
   - Test with a simple email
   - Review error messages

2. **Validate Email Addresses**
   - Check recipient email format
   - Ensure email addresses are valid
   - Test with your own email address

3. **Review Server Configuration**
   - Check PHP error logs
   - Verify server can make HTTP requests
   - Contact hosting provider if needed

4. **Check for Plugin Conflicts**
   - Deactivate other email plugins
   - Test email sending
   - Reactivate plugins one by one

**Issue: Emails Going to Spam**

**Solutions:**
1. **Check Sender Reputation**
   - Verify domain authentication
   - Set up SPF, DKIM, DMARC records
   - Monitor sender reputation

2. **Improve Email Content**
   - Avoid spam trigger words
   - Use proper HTML formatting
   - Include unsubscribe links

3. **List Management**
   - Remove invalid email addresses
   - Process bounces promptly
   - Use double opt-in

**Issue: Slow Email Delivery**

**Solutions:**
1. **Enable Queue System**
   - Go to Advanced tab
   - Enable queue processing
   - Adjust batch size

2. **Optimize Settings**
   - Increase timeout settings
   - Adjust retry attempts
   - Monitor server performance

3. **Check Server Resources**
   - Monitor memory usage
   - Check CPU usage
   - Optimize database

### Webhook Issues

**Issue: Webhooks Not Receiving**

**Possible Causes:**
- Webhook URL not accessible
- Incorrect webhook secret
- Server configuration issues
- Emailit webhook configuration

**Solutions:**
1. **Verify Webhook URL**
   - Check webhook URL is accessible
   - Test URL in browser
   - Ensure HTTPS is enabled

2. **Check Webhook Secret**
   - Verify secret matches in both systems
   - Generate new secret if needed
   - Update both Emailit and WordPress

3. **Test Webhook**
   - Use built-in webhook test
   - Check webhook logs
   - Review error messages

**Issue: Webhook Processing Errors**

**Solutions:**
1. **Check Webhook Logs**
   - Review webhook activity
   - Look for error messages
   - Check processing status

2. **Verify Webhook Data**
   - Check webhook payload format
   - Ensure data is valid
   - Review processing logic

3. **Test Webhook Endpoint**
   - Use webhook testing tools
   - Check server response
   - Verify error handling

### Queue Processing Problems

**Issue: Queue Not Processing**

**Possible Causes:**
- Cron jobs not running
- Queue system disabled
- Server resource issues
- Database problems

**Solutions:**
1. **Check Cron Jobs**
   - Verify WordPress cron is working
   - Check server cron configuration
   - Test cron functionality

2. **Enable Queue System**
   - Go to Advanced tab
   - Enable queue processing
   - Configure batch size

3. **Monitor Resources**
   - Check memory usage
   - Monitor CPU usage
   - Optimize server settings

**Issue: Queue Processing Slowly**

**Solutions:**
1. **Optimize Batch Size**
   - Increase batch size
   - Monitor server performance
   - Adjust based on capacity

2. **Check Database Performance**
   - Optimize database tables
   - Check for slow queries
   - Monitor database usage

3. **Server Optimization**
   - Increase memory limits
   - Optimize PHP settings
   - Check server resources

### FluentCRM Integration Issues

**Issue: Integration Not Working**

**Solutions:**
1. **Check FluentCRM Status**
   - Ensure FluentCRM is active
   - Verify integration is enabled
   - Check FluentCRM settings

2. **Verify Integration Settings**
   - Check bounce handling settings
   - Verify webhook configuration
   - Test integration functionality

3. **Review Error Logs**
   - Check integration logs
   - Look for error messages
   - Review processing status

**Issue: Bounces Not Processing**

**Solutions:**
1. **Check Webhook Configuration**
   - Verify webhooks are enabled
   - Check webhook secret
   - Test webhook functionality

2. **Review Bounce Settings**
   - Check bounce handling configuration
   - Verify threshold settings
   - Test bounce processing

3. **Monitor Integration Logs**
   - Check bounce processing logs
   - Look for error messages
   - Review integration status

## Debug Mode

### Enabling Debug Logging

**Enable Debug Mode:**
1. **Go to Advanced Tab**
2. **Find "Debug Logging" section**
3. **Enable debug logging**
4. **Set log level to "Debug"**

**Debug Information Includes:**
- API request/response details
- Webhook processing information
- Error messages and stack traces
- Performance metrics
- Database queries

### Reading Debug Logs

**Log Locations:**
- **WordPress Debug Log** - `/wp-content/debug.log`
- **Plugin Logs** - Emailit Integration logs section
- **Server Error Logs** - Check with hosting provider

**Log Analysis:**
- **Look for ERROR messages** - Critical issues
- **Check WARNING messages** - Potential problems
- **Review DEBUG messages** - Detailed information
- **Monitor patterns** - Recurring issues

## System Diagnostics

### Built-in Diagnostic Tools

**Database Performance:**
- **Table Sizes** - Check database usage
- **Query Performance** - Identify slow queries
- **Index Status** - Verify database indexes
- **Optimization Tools** - Clean up old data

**Memory Usage:**
- **Current Usage** - Real-time memory consumption
- **Peak Usage** - Highest memory usage
- **Optimization Tips** - Performance recommendations
- **Resource Monitoring** - Track usage over time

**Error Tracking:**
- **Recent Errors** - Latest error messages
- **Error Patterns** - Recurring issue identification
- **Error Analytics** - Trend analysis
- **Resolution Suggestions** - Recommended fixes

### Manual Diagnostics

**Check WordPress Configuration:**
```php
// Check if wp_mail function is available
if (function_exists('wp_mail')) {
    echo "wp_mail function is available";
} else {
    echo "wp_mail function is not available";
}

// Check if plugin is loaded
if (class_exists('Emailit_Integration')) {
    echo "Emailit Integration is loaded";
} else {
    echo "Emailit Integration is not loaded";
}
```

**Test API Connection:**
```php
// Test API connection manually
$api_key = get_option('emailit_api_key');
$response = wp_remote_post('https://api.emailit.com/v1/emails', array(
    'headers' => array(
        'Authorization' => 'Bearer ' . $api_key,
        'Content-Type' => 'application/json'
    ),
    'body' => json_encode(array(
        'to' => 'test@example.com',
        'subject' => 'Test',
        'text' => 'Test message'
    ))
));

if (is_wp_error($response)) {
    echo "API Error: " . $response->get_error_message();
} else {
    echo "API Response: " . wp_remote_retrieve_response_code($response);
}
```

## Getting Support

### Before Contacting Support

**Gather Information:**
- **Plugin version** - Check in WordPress admin
- **WordPress version** - Check in WordPress admin
- **PHP version** - Check in WordPress admin
- **Error messages** - Copy exact error text
- **Debug logs** - Enable and collect logs
- **Steps to reproduce** - Detailed reproduction steps

**Test Basic Functionality:**
- **Send test email** - Verify basic functionality
- **Check API status** - Verify connection
- **Review error logs** - Look for specific errors
- **Test with default settings** - Reset to defaults

### Support Channels

**GitHub Repository:**
- **Bug Reports** - Report software bugs
- **Feature Requests** - Suggest new features
- **Documentation Issues** - Report documentation problems
- **Community Support** - Get help from community

**Professional Support:**
- **Priority Support** - Faster response times
- **Custom Development** - Custom solutions
- **Training** - Learn advanced features
- **Consulting** - Get expert advice

### Providing Support Information

**Include in Support Requests:**
- **Plugin version** - Current version number
- **WordPress version** - WordPress version
- **PHP version** - PHP version
- **Error messages** - Exact error text
- **Debug logs** - Relevant log entries
- **Steps to reproduce** - How to recreate the issue
- **Expected behavior** - What should happen
- **Actual behavior** - What actually happens

## Prevention and Maintenance

### Regular Maintenance

**Weekly Tasks:**
- **Check email logs** - Review for errors
- **Monitor bounce rates** - Keep them low
- **Review webhook activity** - Ensure processing
- **Check system performance** - Monitor resources

**Monthly Tasks:**
- **Clean up old logs** - Remove old data
- **Optimize database** - Run optimization tools
- **Review settings** - Update configuration
- **Check for updates** - Update plugin if needed

**Quarterly Tasks:**
- **Review bounce handling** - Update settings
- **Analyze performance** - Check trends
- **Update documentation** - Keep current
- **Test backup procedures** - Verify backups

### Best Practices

**Configuration:**
- **Use strong passwords** - Secure API keys
- **Enable HTTPS** - Secure connections
- **Regular backups** - Backup settings
- **Monitor logs** - Check for issues

**Performance:**
- **Optimize settings** - Use appropriate values
- **Monitor resources** - Watch usage
- **Clean up data** - Remove old logs
- **Update regularly** - Keep current

**Security:**
- **Use secure connections** - HTTPS only
- **Regular updates** - Keep current
- **Monitor access** - Check user access
- **Backup data** - Regular backups

---

**Still having issues?** Check the [FAQ](faq.md) for more answers or review the [Best Practices](best-practices.md) for optimization tips.

