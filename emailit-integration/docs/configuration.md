# Configuration Guide

This guide covers all the configuration options available in Emailit Integration. The plugin offers both simple settings for basic users and advanced options for power users.

## Accessing Settings

Navigate to **Settings > Emailit Integration** in your WordPress admin dashboard to access all configuration options.

## General Tab

The General tab contains the essential settings needed to get the plugin working.

### API Configuration

**API Key** (Required)
- Your Emailit API key for authentication
- Obtain this from your Emailit dashboard under Settings > API Keys
- This field is encrypted for security

**API Endpoint**
- The Emailit API endpoint (usually pre-configured)
- Only change this if instructed by Emailit support

### Email Settings

**From Name**
- The name that appears as the sender of your emails
- Defaults to your WordPress site name
- Example: "My Website" or "Support Team"

**From Email**
- Your verified sending email address
- Must be verified in your Emailit account
- Example: "noreply@yoursite.com"

**Reply-To Email**
- Email address for replies (optional)
- If not set, replies will go to the From Email
- Example: "support@yoursite.com"

### Test Email

Use this section to verify your configuration:

1. **Enter a test email address**
2. **Click "Send Test Email"**
3. **Check your email** for the test message
4. **Review the status** - should show "Sent successfully"

### Real-Time Status

This section shows the current status of your plugin:

- **API Status:** Connection status to Emailit
- **Queue Status:** Background processing status
- **Webhook Status:** Real-time tracking status
- **Last Activity:** Recent email activity

## Logs & Statistics Tab

Monitor your email activity and performance.

### Email Statistics

View key metrics about your email sending:

- **Total Sent:** Number of emails sent
- **Success Rate:** Percentage of successful deliveries
- **Bounce Rate:** Percentage of bounced emails
- **Recent Activity:** Latest email activity

### Email Logs

Browse and filter your email logs:

**Filter Options:**
- **Status:** All, Sent, Delivered, Bounced, Failed
- **Date Range:** Filter by specific dates
- **Search:** Find specific emails by recipient or subject

**Log Details:**
- **Recipient:** Email address
- **Subject:** Email subject line
- **Status:** Current delivery status
- **Sent Date:** When the email was sent
- **Response Time:** API response time

### Webhook Activity

Monitor real-time webhook events:

- **Recent Events:** Latest webhook notifications
- **Event Types:** Delivered, Bounced, Opened, Clicked
- **Processing Status:** Success or failure
- **Timestamp:** When the event occurred

## Advanced Tab

Power user features and advanced configuration options.

### Power User Mode

**Enable Power User Mode**
- Toggle to show/hide advanced features
- Settings are saved per user
- Provides access to all configuration options

### Performance & Queue Settings

**Enable Queue System**
- Process emails in the background
- Improves site performance for bulk emails
- Recommended for high-volume sites

**Queue Batch Size**
- Number of emails to process at once
- Default: 10 emails per batch
- Higher values = faster processing, more server load

**Queue Max Retries**
- Number of retry attempts for failed emails
- Default: 3 attempts
- Higher values = more delivery attempts

**Queue Processing Interval**
- How often to process the queue
- Options: Every 5, 10, 15, or 30 minutes
- More frequent = faster delivery, more server load

### Webhook Configuration

**Enable Webhooks**
- Receive real-time email status updates
- Provides detailed delivery tracking
- Required for bounce handling

**Webhook Secret**
- Security key for webhook verification
- Generate a random string for security
- Must match the secret in your Emailit dashboard

**Webhook URL**
- Your site's webhook endpoint
- Copy this URL to your Emailit dashboard
- Format: `https://yoursite.com/wp-json/emailit/v1/webhook`

**Event Types to Receive**
- **Delivered:** Email successfully delivered
- **Bounced:** Email bounced back
- **Opened:** Email was opened (if tracking enabled)
- **Clicked:** Link was clicked (if tracking enabled)

### Advanced Configuration

**Request Timeout**
- How long to wait for API responses
- Default: 30 seconds
- Increase for slow connections

**Retry Attempts**
- Number of retry attempts for failed API calls
- Default: 3 attempts
- Higher values = more reliability, slower failure detection

**Enable Fallback**
- Fall back to wp_mail() if API fails
- Ensures emails are still sent during outages
- Recommended to keep enabled

**Enable Logging**
- Log all email activity to database
- Required for statistics and troubleshooting
- Can be disabled to save database space

**Log Retention Days**
- How long to keep email logs
- Default: 30 days
- Longer retention = more data, larger database

### System Diagnostics

**Database Optimization**
- View database performance metrics
- Optimize database tables
- Clean up old data

**Error Tracking**
- View recent errors and issues
- Analyze error patterns
- Get recommendations for fixes

**Memory Usage**
- Monitor plugin memory consumption
- Identify performance bottlenecks
- Optimize resource usage

## FluentCRM Integration Tab

Available only when FluentCRM is installed and active.

### Integration Settings

**Enable FluentCRM Integration**
- Connect Emailit with FluentCRM
- Automatic bounce handling
- Subscriber status synchronization

**Forward Bounce Data to Emailit API**
- Send bounce information to Emailit
- Improves bounce classification
- Recommended for better deliverability

**Suppress Default FluentCRM Email**
- Prevent FluentCRM from sending emails directly
- All emails go through Emailit
- Ensures consistent delivery

### Bounce Handling

**Hard Bounce Action**
- What to do when emails hard bounce
- Options: Unsubscribe, Mark as Bounced, No Action
- Recommended: Unsubscribe

**Soft Bounce Action**
- What to do when emails soft bounce
- Options: Track, Mark as Bounced, No Action
- Recommended: Track

**Soft Bounce Threshold**
- Number of soft bounces before escalation
- Default: 5 soft bounces
- After threshold, treat as hard bounce

**Complaint Action**
- What to do when emails are marked as spam
- Options: Unsubscribe, Mark as Complained, No Action
- Recommended: Unsubscribe

### Advanced Bounce Settings

**Enable Action Mapping**
- Map bounce types to specific actions
- Customize handling for different bounce reasons
- Advanced feature for power users

**Auto-Create Subscribers**
- Automatically create FluentCRM subscribers
- When emails are sent to new addresses
- Helps maintain CRM database

**Confidence Threshold**
- Minimum confidence level for bounce classification
- Default: 70%
- Higher values = more accurate classification

## Settings Reference

### Recommended Settings by Use Case

**Small Business Website:**
- Enable Queue: No
- Webhooks: Yes
- Log Retention: 30 days
- Fallback: Yes

**High-Volume Site:**
- Enable Queue: Yes
- Batch Size: 20
- Webhooks: Yes
- Log Retention: 90 days

**E-commerce Store:**
- Enable Queue: Yes
- FluentCRM Integration: Yes
- Bounce Handling: Unsubscribe
- Webhooks: Yes

**Newsletter Site:**
- Enable Queue: Yes
- Batch Size: 50
- FluentCRM Integration: Yes
- Log Retention: 180 days

### Security Best Practices

1. **Use Strong Webhook Secret**
   - Generate a random 32-character string
   - Don't use common words or phrases

2. **Enable HTTPS**
   - Ensure your site uses SSL
   - Required for webhook security

3. **Regular API Key Rotation**
   - Change your API key periodically
   - Update in both Emailit and WordPress

4. **Monitor Access Logs**
   - Check who has access to settings
   - Use proper user roles and permissions

### Performance Optimization

1. **Enable Queue for Bulk Emails**
   - Improves site performance
   - Prevents timeouts

2. **Optimize Batch Size**
   - Start with default (10)
   - Increase if server can handle it

3. **Regular Database Cleanup**
   - Use the database optimizer
   - Remove old logs periodically

4. **Monitor Memory Usage**
   - Check memory consumption
   - Optimize if needed

## Troubleshooting Configuration

### Common Configuration Issues

**API Key Not Working:**
- Verify key is correct in Emailit dashboard
- Check for extra spaces or characters
- Ensure account is active

**Webhooks Not Receiving:**
- Verify webhook URL is accessible
- Check webhook secret matches
- Ensure HTTPS is enabled

**Queue Not Processing:**
- Check if cron jobs are running
- Verify queue is enabled
- Check for PHP errors

**FluentCRM Integration Issues:**
- Ensure FluentCRM is active
- Check integration is enabled
- Verify bounce handling settings

### Getting Help

If you need help with configuration:

1. **Check the [Troubleshooting Guide](troubleshooting.md)**
2. **Review the [FAQ](faq.md)**
3. **Enable debug logging** for detailed information
4. **Contact support** through the GitHub repository

---

**Ready to start using the plugin?** Check out the [User Guide](user-guide.md) to learn how to monitor emails and use all the features.
