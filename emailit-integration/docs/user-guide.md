# User Guide

This guide covers how to use Emailit Integration on a daily basis, including monitoring emails, understanding the interface, and using all available features.

## Dashboard Overview

The Emailit Integration dashboard is organized into three main tabs, each serving a specific purpose:

### Tab Navigation

- **General** - Configuration and testing
- **Logs & Statistics** - Email monitoring and activity
- **Advanced** - Power user features and diagnostics

### Power User Mode

Located in the top-right corner of the dashboard, Power User Mode allows you to:

- **Toggle Interface Complexity** - Show or hide advanced features
- **User-Specific Settings** - Each user can set their own preference
- **Real-Time Switching** - Changes take effect immediately

## General Tab

The General tab contains your main configuration and testing tools.

### API Configuration Section

**Current Status Display:**
- **API Status** - Shows connection status to Emailit
- **Last Check** - When the connection was last verified
- **Response Time** - How quickly the API responds

**Configuration Fields:**
- **API Key** - Your Emailit authentication key
- **From Name** - Sender name for emails
- **From Email** - Your verified sending address
- **Reply-To** - Email address for replies

### Email Settings Section

Configure how emails are sent and handled:

**Default Settings:**
- **From Name** - Appears as the sender
- **From Email** - Must be verified in Emailit
- **Reply-To** - Where replies are sent

**Advanced Options** (Power User Mode):
- **Request Timeout** - How long to wait for API responses
- **Retry Attempts** - Number of retry attempts
- **Enable Fallback** - Use wp_mail() if API fails

### Test Email Section

**Send Test Emails:**
1. **Enter Email Address** - Recipient for test email
2. **Click "Send Test Email"** - Sends a test message
3. **Check Status** - View success/failure status
4. **Review Logs** - See detailed sending information

**Test Email Features:**
- **Real-Time Status** - Immediate feedback
- **Error Details** - Specific error messages if failed
- **Logging** - All tests are logged for review

### Real-Time Status Monitoring

**Live Status Indicators:**
- **API Connection** - Green (connected) or Red (disconnected)
- **Queue Processing** - Current queue status
- **Webhook Activity** - Recent webhook events
- **Last Activity** - Most recent email activity

**Status Details:**
- **Connection Time** - How long API has been connected
- **Queue Size** - Number of emails waiting to be sent
- **Webhook Events** - Recent status updates received

## Logs & Statistics Tab

Monitor your email activity and performance metrics.

### Email Statistics Overview

**Key Metrics Display:**
- **Total Sent** - Total number of emails sent
- **Success Rate** - Percentage of successful deliveries
- **Bounce Rate** - Percentage of bounced emails
- **Average Response Time** - API response performance

**Time Periods:**
- **Today** - Current day statistics
- **This Week** - Past 7 days
- **This Month** - Past 30 days
- **All Time** - Total statistics

### Email Logs

**Log Viewer Features:**
- **Pagination** - Browse through large numbers of emails
- **Filtering** - Filter by status, date, or recipient
- **Search** - Find specific emails quickly
- **Sorting** - Sort by date, status, or recipient

**Log Entry Details:**
- **Recipient** - Email address
- **Subject** - Email subject line
- **Status** - Current delivery status
- **Sent Date** - When email was sent
- **Response Time** - API response time
- **Details** - Additional information

**Status Types:**
- **Sent** - Email was sent successfully
- **Delivered** - Email was delivered to recipient
- **Bounced** - Email bounced back
- **Failed** - Email failed to send
- **Pending** - Email is waiting to be sent

### Filtering and Search

**Filter Options:**
- **Status Filter** - All, Sent, Delivered, Bounced, Failed
- **Date Range** - From and to dates
- **Recipient Search** - Find emails by recipient
- **Subject Search** - Find emails by subject

**Search Features:**
- **Real-Time Search** - Results update as you type
- **Case Insensitive** - Search works regardless of case
- **Partial Matches** - Find emails with partial text

### Webhook Activity

**Recent Webhook Events:**
- **Event Type** - Delivered, Bounced, Opened, Clicked
- **Email ID** - Reference to the original email
- **Timestamp** - When the event occurred
- **Status** - Success or failure processing

**Webhook Event Details:**
- **Raw Payload** - Complete webhook data
- **Processed Data** - Extracted information
- **Error Messages** - Any processing errors

## Advanced Tab

Power user features and system diagnostics.

### Power User Features

**Queue Management:**
- **Enable/Disable Queue** - Toggle background processing
- **Batch Size** - Number of emails per batch
- **Processing Interval** - How often to process queue
- **Retry Settings** - Retry attempts for failed emails

**Webhook Configuration:**
- **Enable/Disable Webhooks** - Toggle real-time tracking
- **Webhook Secret** - Security key for verification
- **Event Types** - Which events to receive
- **Rate Limiting** - Prevent webhook spam

### System Diagnostics

**Database Performance:**
- **Table Sizes** - Database table information
- **Query Performance** - Slow query identification
- **Index Status** - Database index health
- **Optimization Tools** - Database cleanup utilities

**Error Tracking:**
- **Recent Errors** - Latest error messages
- **Error Patterns** - Recurring issue identification
- **Error Analytics** - Trend analysis
- **Resolution Suggestions** - Recommended fixes

**Memory Usage:**
- **Current Usage** - Real-time memory consumption
- **Peak Usage** - Highest memory usage
- **Optimization Tips** - Performance recommendations

## Understanding Email Statuses

### Status Types Explained

**Pending**
- Email is queued and waiting to be sent
- Normal status for queued emails
- Will change to "Sent" when processed

**Sent**
- Email was successfully sent to Emailit
- API call was successful
- Email is now in Emailit's system

**Delivered**
- Email was successfully delivered to recipient
- Confirmed by recipient's email server
- Best possible status

**Bounced**
- Email was rejected by recipient's server
- Could be hard bounce (permanent) or soft bounce (temporary)
- Check bounce reason for details

**Failed**
- Email failed to send through Emailit API
- Could be API error, network issue, or configuration problem
- Check error details for specific cause

### Bounce Classification

**Hard Bounces:**
- **Invalid Email** - Email address doesn't exist
- **Domain Not Found** - Domain doesn't exist
- **Mailbox Full** - Recipient's mailbox is full
- **Blocked** - Recipient's server blocked the email

**Soft Bounces:**
- **Temporary Failure** - Server temporarily unavailable
- **Message Too Large** - Email exceeds size limits
- **Rate Limited** - Too many emails to same domain
- **Greylisted** - Server is temporarily blocking

## Daily Usage Tips

### Monitoring Email Health

**Daily Checks:**
1. **Review Statistics** - Check success and bounce rates
2. **Monitor Errors** - Look for failed emails
3. **Check Webhook Activity** - Ensure real-time updates are working
4. **Review Queue Status** - Make sure emails are processing

**Weekly Reviews:**
1. **Analyze Trends** - Look for patterns in email performance
2. **Clean Up Logs** - Remove old log entries if needed
3. **Check Bounce Rates** - Ensure they're within acceptable limits
4. **Update Settings** - Adjust configuration as needed

### Troubleshooting Common Issues

**Emails Not Sending:**
1. Check API status in General tab
2. Verify API key is correct
3. Test with a simple email
4. Check error logs for details

**High Bounce Rates:**
1. Review bounce reasons in logs
2. Clean up email lists
3. Check sender reputation
4. Verify domain authentication

**Slow Performance:**
1. Enable queue system
2. Optimize batch size
3. Check database performance
4. Monitor memory usage

### Best Practices

**Email List Management:**
- **Regular Cleanup** - Remove invalid email addresses
- **Bounce Handling** - Process bounces promptly
- **List Segmentation** - Send relevant content
- **Permission-Based** - Only send to opted-in recipients

**Performance Optimization:**
- **Use Queue System** - For bulk emails
- **Optimize Batch Size** - Based on server capacity
- **Monitor Resources** - Watch memory and CPU usage
- **Regular Maintenance** - Clean up old data

**Monitoring and Alerts:**
- **Set Up Monitoring** - Track key metrics
- **Configure Alerts** - Get notified of issues
- **Regular Reviews** - Check performance regularly
- **Document Issues** - Keep track of problems and solutions

## Getting Help

### Built-in Help

**Tooltips:**
- Hover over help icons (?) for explanations
- Context-sensitive help throughout the interface
- Detailed descriptions for all settings

**Status Messages:**
- Real-time feedback on actions
- Clear error messages with suggestions
- Success confirmations for completed tasks

### Additional Resources

**Documentation:**
- [Troubleshooting Guide](troubleshooting.md) - Common issues and solutions
- [FAQ](faq.md) - Frequently asked questions
- [Best Practices](best-practices.md) - Optimization tips

**Support:**
- GitHub repository for bug reports
- Community forums for questions
- Professional support options

---

**Ready to set up FluentCRM integration?** Check out the [FluentCRM Integration Guide](fluentcrm-integration.md) to connect your CRM system.
