# Frequently Asked Questions (FAQ)

This FAQ covers the most common questions about Emailit Integration. If you don't find your answer here, check the [Troubleshooting Guide](troubleshooting.md) or contact support.

## Installation & Setup

### Q: What are the system requirements for Emailit Integration?

**A:** Emailit Integration requires:
- **WordPress:** 5.7 or higher
- **PHP:** 8.0 or higher
- **MySQL:** 5.6 or higher (or MariaDB 10.1+)
- **Memory:** 128MB PHP memory limit (256MB recommended)
- **Server:** Support for outgoing HTTP requests (`wp_remote_post`)

### Q: How do I install the plugin?

**A:** You can install Emailit Integration in three ways:
1. **WordPress Admin:** Upload the ZIP file through Plugins > Add New
2. **Manual Upload:** Extract and upload the folder to `/wp-content/plugins/`
3. **WP-CLI:** Use `wp plugin install` command

### Q: Do I need an Emailit account to use this plugin?

**A:** Yes, you need an active Emailit account with a valid API key. The plugin connects to Emailit's service to send emails, so an account is required.

### Q: How do I get my Emailit API key?

**A:** To get your API key:
1. Log in to your Emailit dashboard
2. Go to Settings > API Keys
3. Click "Generate New API Key"
4. Copy the key and paste it into WordPress settings

### Q: Can I use this plugin without configuring it?

**A:** No, the plugin requires basic configuration to work. You must enter your Emailit API key and configure at least the basic email settings before emails can be sent.

## Configuration

### Q: What is Power User Mode?

**A:** Power User Mode is a feature that shows/hides advanced configuration options. When enabled, you'll see additional settings for queue management, webhook configuration, and system diagnostics. Each user can set their own preference.

### Q: Should I enable the queue system?

**A:** Enable the queue system if you:
- Send bulk emails regularly
- Want to improve site performance
- Have a high-volume website
- Experience timeouts when sending emails

### Q: What is the difference between hard and soft bounces?

**A:** 
- **Hard bounces** are permanent failures (invalid email address, domain doesn't exist)
- **Soft bounces** are temporary failures (mailbox full, server temporarily unavailable)

### Q: How do I set up webhooks?

**A:** To set up webhooks:
1. Go to Advanced tab in plugin settings
2. Enable webhooks
3. Set a webhook secret
4. Copy the webhook URL
5. Add the URL to your Emailit dashboard

### Q: What is the recommended bounce threshold?

**A:** The recommended soft bounce threshold is 3-5 bounces. This balances between giving temporary issues time to resolve and maintaining list hygiene.

## Email Sending

### Q: Will this plugin work with all WordPress email functions?

**A:** Yes, Emailit Integration replaces the default `wp_mail()` function, so it works with:
- Contact forms
- User registration emails
- Password reset emails
- Plugin notifications
- Theme notifications
- Any WordPress function that uses `wp_mail()`

### Q: How do I test if emails are working?

**A:** Use the built-in test email feature:
1. Go to Settings > Emailit Integration
2. Scroll to the "Test Email" section
3. Enter your email address
4. Click "Send Test Email"
5. Check your email for the test message

### Q: Why are my emails going to spam?

**A:** Emails may go to spam due to:
- Poor sender reputation
- Missing domain authentication (SPF, DKIM, DMARC)
- Spam trigger words in content
- High bounce rates
- Poor list hygiene

### Q: Can I send HTML emails?

**A:** Yes, the plugin supports both HTML and plain text emails. WordPress will automatically detect the content type and send accordingly.

### Q: What happens if the Emailit API is down?

**A:** If you have fallback enabled (recommended), the plugin will automatically fall back to WordPress's default `wp_mail()` function. This ensures emails are still sent even during API outages.

### Q: How many emails can I send per day?

**A:** Your email limits depend on your Emailit account plan. Check your Emailit dashboard for current limits and usage.

## Webhooks

### Q: What are webhooks used for?

**A:** Webhooks provide real-time updates about email events:
- **Delivered:** Email was successfully delivered
- **Bounced:** Email bounced back
- **Opened:** Email was opened (if tracking enabled)
- **Clicked:** Link was clicked (if tracking enabled)

### Q: Do I need to enable webhooks?

**A:** Webhooks are optional but recommended for:
- Real-time email tracking
- Bounce handling
- FluentCRM integration
- Detailed email analytics

### Q: How do I know if webhooks are working?

**A:** Check the webhook activity in the Logs & Statistics tab. You should see recent webhook events and their processing status.

### Q: Can I customize webhook processing?

**A:** Yes, you can customize webhook processing using WordPress hooks and filters. Check the developer documentation for details.

## Performance & Scaling

### Q: Will this plugin slow down my website?

**A:** No, the plugin is designed for performance:
- Uses efficient database queries
- Processes emails in background (with queue)
- Minimal memory footprint
- Optimized for high-volume sites

### Q: How do I optimize performance for high-volume sites?

**A:** For high-volume sites:
- Enable the queue system
- Increase batch size (based on server capacity)
- Optimize database regularly
- Monitor memory usage
- Use appropriate server resources

### Q: What is the recommended batch size for queue processing?

**A:** Start with the default (10 emails per batch) and adjust based on your server capacity. Higher values process faster but use more resources.

### Q: How often should I clean up old logs?

**A:** Regular cleanup depends on your needs:
- **Small sites:** Monthly cleanup
- **Medium sites:** Weekly cleanup
- **High-volume sites:** Daily cleanup

## Security

### Q: How secure is my API key?

**A:** Your API key is encrypted using AES-256-GCM encryption before being stored in the database. This provides military-grade security for your credentials.

### Q: Can I use HTTPS for webhooks?

**A:** Yes, HTTPS is required for webhook security. The plugin will only work with HTTPS-enabled websites for webhook functionality.

### Q: Are there any security risks with this plugin?

**A:** The plugin follows WordPress security best practices:
- Input validation and sanitization
- SQL injection prevention
- XSS protection
- CSRF protection
- Encrypted API key storage

### Q: Can I restrict access to plugin settings?

**A:** Yes, only users with the `manage_options` capability can access plugin settings. This is typically administrators only.

## Compatibility

### Q: Does this plugin work with other email plugins?

**A:** Emailit Integration replaces the default `wp_mail()` function, so it may conflict with other email plugins. Deactivate other email plugins to avoid conflicts.

### Q: Is this plugin compatible with FluentCRM?

**A:** Yes, the plugin has built-in FluentCRM integration for:
- Automatic bounce handling
- Subscriber status synchronization
- Advanced bounce classification
- CRM data management

### Q: Does it work with WooCommerce?

**A:** Yes, the plugin works with WooCommerce and all other WordPress plugins that use the standard `wp_mail()` function.

### Q: Is it compatible with multisite WordPress?

**A:** Yes, the plugin works with WordPress multisite installations. Each site can have its own configuration.

### Q: Does it work with page builders?

**A:** Yes, the plugin works with all page builders and form plugins that use WordPress's standard email functions.

## Troubleshooting

### Q: My test email isn't arriving. What should I do?

**A:** If your test email isn't arriving:
1. Check your spam folder
2. Verify the API key is correct
3. Check the email logs for errors
4. Ensure your server can make HTTP requests
5. Contact your hosting provider if needed

### Q: I'm getting "API Key Invalid" errors. What's wrong?

**A:** "API Key Invalid" errors usually mean:
- The API key is incorrect or expired
- Your Emailit account is inactive
- There are extra spaces or characters in the key
- The key was regenerated in Emailit but not updated in WordPress

### Q: Why is my queue not processing?

**A:** If your queue isn't processing:
1. Check if WordPress cron is working
2. Verify the queue system is enabled
3. Check for PHP errors in logs
4. Ensure your server has sufficient resources
5. Test with a smaller batch size

### Q: How do I enable debug logging?

**A:** To enable debug logging:
1. Go to Advanced tab in plugin settings
2. Find the "Debug Logging" section
3. Enable debug logging
4. Set log level to "Debug"
5. Check the logs for detailed information

### Q: My webhooks aren't working. How do I fix this?

**A:** If webhooks aren't working:
1. Verify the webhook URL is accessible
2. Check that the webhook secret matches
3. Ensure HTTPS is enabled on your site
4. Test the webhook endpoint manually
5. Check webhook logs for errors

## Support

### Q: Where can I get help if I'm having issues?

**A:** You can get help from:
- **Documentation:** This FAQ and other guides
- **GitHub Repository:** Bug reports and community support
- **Troubleshooting Guide:** Common issues and solutions
- **Professional Support:** Priority support options

### Q: How do I report a bug?

**A:** To report a bug:
1. Check if it's already reported in the GitHub repository
2. Gather information (plugin version, WordPress version, error messages)
3. Create a new issue with detailed information
4. Include steps to reproduce the problem

### Q: Can I request new features?

**A:** Yes, you can request new features through the GitHub repository. Please provide:
- Detailed description of the feature
- Use case and benefits
- Any relevant examples or mockups

### Q: Is there professional support available?

**A:** Yes, professional support options are available for:
- Priority support with faster response times
- Custom development and integrations
- Training and consulting services
- Advanced troubleshooting assistance

---

**Still have questions?** Check the [Troubleshooting Guide](troubleshooting.md) for more detailed solutions or review the [Best Practices](best-practices.md) for optimization tips.

