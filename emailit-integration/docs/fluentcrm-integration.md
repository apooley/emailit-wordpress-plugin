# FluentCRM Integration Guide

This guide covers how to set up and use the FluentCRM integration with Emailit Integration. This powerful combination allows you to manage email bounces, track subscriber engagement, and maintain clean contact lists automatically.

## What is FluentCRM Integration?

FluentCRM Integration connects your Emailit email service with your FluentCRM contact management system. This integration provides:

- **Automatic Bounce Handling** - Process email bounces and update subscriber status
- **Subscriber Synchronization** - Keep your CRM data current with email activity
- **Advanced Bounce Classification** - Intelligent categorization of bounce types
- **Automated Actions** - Take action based on bounce types and frequency

## Prerequisites

Before setting up the integration, ensure you have:

- **FluentCRM Plugin** - Installed and activated
- **Emailit Integration** - Configured with valid API key
- **Webhook Support** - Enabled for real-time bounce processing
- **Admin Access** - To both FluentCRM and Emailit Integration settings

## Setting Up the Integration

### Step 1: Enable FluentCRM Integration

1. **Navigate to Emailit Settings**
   - Go to **Settings > Emailit Integration**
   - Click on the **FluentCRM** tab (only visible when FluentCRM is active)

2. **Enable Integration**
   - Check **"Enable FluentCRM Integration"**
   - This activates the connection between the two systems

3. **Configure Basic Settings**
   - **Forward Bounce Data to Emailit API** - Recommended to keep enabled
   - **Suppress Default FluentCRM Email** - Prevents duplicate email sending

### Step 2: Configure Bounce Handling

**Hard Bounce Actions:**
- **Unsubscribe** - Remove subscriber from all lists (recommended)
- **Mark as Bounced** - Keep subscriber but mark status
- **No Action** - Don't change subscriber status

**Soft Bounce Actions:**
- **Track** - Monitor soft bounces (recommended)
- **Mark as Bounced** - Treat as hard bounce
- **No Action** - Don't track soft bounces

**Complaint Actions:**
- **Unsubscribe** - Remove subscriber immediately (recommended)
- **Mark as Complained** - Keep subscriber but mark status
- **No Action** - Don't change subscriber status

### Step 3: Set Bounce Thresholds

**Soft Bounce Threshold:**
- **Default: 5** - Number of soft bounces before escalation
- **Recommended: 3-5** - Balance between tolerance and list hygiene
- **After threshold** - Subscriber is treated as hard bounce

**Confidence Threshold:**
- **Default: 70%** - Minimum confidence for bounce classification
- **Higher values** - More accurate but fewer classifications
- **Lower values** - More classifications but less accurate

### Step 4: Configure Advanced Settings

**Enable Action Mapping:**
- **Custom bounce handling** - Different actions for different bounce types
- **Advanced feature** - For power users who need fine control
- **Requires understanding** - Of bounce types and their meanings

**Auto-Create Subscribers:**
- **Automatically add** - New email addresses to FluentCRM
- **When emails are sent** - To addresses not in your CRM
- **Helps maintain** - Complete contact database

## How Bounce Handling Works

### Bounce Classification

The integration automatically classifies bounces into categories:

**Hard Bounces (Permanent):**
- **Invalid Email** - Email address doesn't exist
- **Domain Not Found** - Domain doesn't exist
- **Mailbox Full** - Recipient's mailbox is permanently full
- **Blocked** - Recipient's server permanently blocks emails

**Soft Bounces (Temporary):**
- **Server Unavailable** - Recipient's server is temporarily down
- **Message Too Large** - Email exceeds size limits
- **Rate Limited** - Too many emails sent to same domain
- **Greylisted** - Server is temporarily blocking emails

**Complaints:**
- **Spam Reports** - Recipient marked email as spam
- **Unsubscribe Requests** - Recipient requested to be removed
- **Abuse Reports** - Recipient reported email as abusive

### Automatic Processing

**Real-Time Processing:**
1. **Email Sent** - Through Emailit Integration
2. **Bounce Occurs** - Recipient's server rejects email
3. **Webhook Received** - Emailit sends bounce notification
4. **Classification** - System determines bounce type
5. **Action Taken** - Based on your configuration

**Soft Bounce Escalation:**
1. **First Soft Bounce** - Tracked and counted
2. **Subsequent Bounces** - Continue tracking
3. **Threshold Reached** - After configured number
4. **Escalation** - Treated as hard bounce
5. **Action Taken** - Based on hard bounce settings

## Managing Subscriber Status

### Status Updates

The integration automatically updates FluentCRM subscriber status:

**Active Status:**
- **Subscriber is active** - Can receive emails
- **No bounce issues** - Email delivery is successful
- **Engagement tracking** - Opens and clicks are recorded

**Bounced Status:**
- **Hard bounce** - Subscriber marked as bounced
- **Soft bounce threshold** - Exceeded soft bounce limit
- **No further emails** - Subscriber won't receive emails

**Complained Status:**
- **Spam complaint** - Subscriber marked as complained
- **Immediate action** - Based on complaint settings
- **List removal** - Usually removed from all lists

### Subscriber Metadata

The integration adds metadata to FluentCRM subscribers:

**Bounce Information:**
- **Bounce Date** - When the bounce occurred
- **Bounce Reason** - Specific reason for bounce
- **Bounce Type** - Hard, soft, or complaint
- **Bounce Count** - Number of bounces for soft bounces

**Email Activity:**
- **Last Email Sent** - Date of last email
- **Email Count** - Total emails sent
- **Open Rate** - Percentage of emails opened
- **Click Rate** - Percentage of emails clicked

## Monitoring Integration Health

### FluentCRM Tab Overview

**Integration Status:**
- **Connection Status** - Whether integration is active
- **Last Sync** - When data was last synchronized
- **Bounce Processing** - Number of bounces processed
- **Subscriber Updates** - Number of status updates

**Recent Activity:**
- **Bounce Events** - Latest bounce notifications
- **Status Changes** - Recent subscriber updates
- **Error Messages** - Any processing errors
- **Success Rate** - Percentage of successful processing

### Troubleshooting Integration Issues

**Common Issues:**

**Integration Not Working:**
- Check if FluentCRM is active
- Verify integration is enabled
- Ensure webhooks are working
- Check for error messages

**Bounces Not Processing:**
- Verify webhook configuration
- Check bounce handling settings
- Ensure Emailit is sending webhooks
- Review error logs

**Subscriber Status Not Updating:**
- Check FluentCRM permissions
- Verify bounce action settings
- Ensure subscriber exists in CRM
- Review processing logs

**High Bounce Rates:**
- Review bounce reasons
- Check email list quality
- Verify sender reputation
- Consider list cleaning

## Best Practices

### List Management

**Regular Maintenance:**
- **Monitor bounce rates** - Keep them below 5%
- **Process bounces quickly** - Remove invalid addresses promptly
- **Clean email lists** - Remove inactive subscribers
- **Verify new subscribers** - Use double opt-in

**Bounce Handling:**
- **Set appropriate thresholds** - Balance tolerance with hygiene
- **Monitor soft bounces** - Watch for patterns
- **Process complaints immediately** - Remove complainers quickly
- **Document bounce reasons** - Track common issues

### Performance Optimization

**Database Management:**
- **Regular cleanup** - Remove old bounce data
- **Optimize queries** - Ensure fast processing
- **Monitor performance** - Watch for slowdowns
- **Archive old data** - Keep database lean

**Processing Efficiency:**
- **Batch processing** - Process bounces in batches
- **Error handling** - Graceful failure handling
- **Retry logic** - Retry failed operations
- **Monitoring** - Track processing performance

### Compliance and Deliverability

**Email Compliance:**
- **Permission-based sending** - Only send to opted-in subscribers
- **Clear unsubscribe** - Make it easy to opt out
- **Honor requests** - Process unsubscribes immediately
- **Monitor complaints** - Keep complaint rates low

**Deliverability Best Practices:**
- **Maintain clean lists** - Remove bounces promptly
- **Monitor reputation** - Watch sender reputation
- **Authenticate emails** - Use SPF, DKIM, DMARC
- **Engage subscribers** - Send relevant content

## Advanced Configuration

### Custom Bounce Actions

**Action Mapping:**
- **Specific bounce types** - Different actions for different bounces
- **Custom logic** - Advanced processing rules
- **Integration hooks** - Custom code integration
- **Workflow automation** - Automated follow-up actions

**Custom Fields:**
- **Additional metadata** - Store extra bounce information
- **Custom tags** - Label subscribers with bounce history
- **Segmentation** - Create lists based on bounce behavior
- **Reporting** - Custom bounce reports

### Integration Hooks

**WordPress Hooks:**
```php
// Custom bounce processing
add_action('emailit_fluentcrm_bounce_processed', 'my_custom_bounce_handler');

// Custom subscriber updates
add_filter('emailit_fluentcrm_subscriber_data', 'my_custom_subscriber_data');
```

**FluentCRM Hooks:**
```php
// Custom bounce actions
add_filter('fluent_crm/bounce_handlers', 'my_custom_bounce_handler');

// Custom subscriber status updates
add_action('fluent_crm_subscriber_status_changed', 'my_custom_status_handler');
```

## Getting Help

### Built-in Diagnostics

**Integration Health Check:**
- **Connection test** - Verify integration is working
- **Bounce processing test** - Test bounce handling
- **Subscriber sync test** - Verify data synchronization
- **Error analysis** - Review any issues

**Log Analysis:**
- **Bounce logs** - Review bounce processing
- **Error logs** - Check for processing errors
- **Performance logs** - Monitor processing speed
- **Integration logs** - Track integration activity

### Support Resources

**Documentation:**
- [Troubleshooting Guide](troubleshooting.md) - Common issues and solutions
- [FAQ](faq.md) - Frequently asked questions
- [Best Practices](best-practices.md) - Optimization tips

**Community Support:**
- GitHub repository for bug reports
- Community forums for questions
- Professional support options

---

**Need help with other aspects?** Check out the [Troubleshooting Guide](troubleshooting.md) for common issues or the [FAQ](faq.md) for frequently asked questions.


