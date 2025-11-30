# Getting Started with Emailit Integration

This guide will walk you through installing and setting up the Emailit Integration plugin for WordPress.

## System Requirements

Before installing the plugin, ensure your WordPress site meets these requirements:

### Minimum Requirements
- **WordPress:** 5.7 or higher
- **PHP:** 8.0 or higher
- **MySQL:** 5.6 or higher (or MariaDB 10.1+)
- **Memory:** 128MB PHP memory limit (256MB recommended)
- **Disk Space:** 10MB for plugin files

### Server Requirements
- **Outgoing HTTP Requests:** Your server must support `wp_remote_post()` function
- **SSL Certificate:** Recommended for webhook endpoints
- **Cron Jobs:** Required for queue processing and scheduled tasks

### Emailit Account
- **Active Emailit Account:** You need an active Emailit account
- **API Key:** Your Emailit API key for authentication
- **Verified Domain:** Your sending domain should be verified in Emailit

## Installation Methods

### Method 1: WordPress Admin (Recommended)

1. **Download the Plugin**
   - Download the plugin ZIP file from the repository
   - Save it to your computer

2. **Upload to WordPress**
   - Log in to your WordPress admin dashboard
   - Navigate to **Plugins > Add New**
   - Click **Upload Plugin**
   - Choose the downloaded ZIP file
   - Click **Install Now**

3. **Activate the Plugin**
   - After installation, click **Activate Plugin**
   - The plugin is now active and ready for configuration

### Method 2: Manual Installation

1. **Extract Plugin Files**
   - Download and extract the plugin ZIP file
   - You should have an `emailit-integration` folder

2. **Upload to Server**
   - Upload the `emailit-integration` folder to `/wp-content/plugins/` on your server
   - Ensure the folder structure is: `/wp-content/plugins/emailit-integration/`

3. **Activate in WordPress**
   - Log in to your WordPress admin dashboard
   - Go to **Plugins > Installed Plugins**
   - Find "Emailit Integration" and click **Activate**

### Method 3: WP-CLI (Advanced Users)

If you have WP-CLI installed on your server:

```bash
# Navigate to your WordPress directory
cd /path/to/your/wordpress

# Install and activate the plugin
wp plugin install /path/to/emailit-integration.zip --activate
```

## First Configuration

After installation and activation, you'll need to configure the plugin with your Emailit API credentials.

### Step 1: Access Plugin Settings

1. In your WordPress admin dashboard, navigate to **Settings > Emailit Integration**
2. You'll see the plugin's main settings page with three tabs:
   - **General** - Basic configuration
   - **Logs & Statistics** - Email activity monitoring
   - **Advanced** - Power user features

### Step 2: Configure API Settings

1. **Click on the General tab** (should be selected by default)
2. **Find the "API Configuration" section**
3. **Enter your Emailit API Key:**
   - Log in to your Emailit account
   - Navigate to your API settings
   - Copy your API key
   - Paste it into the "API Key" field in WordPress

4. **Configure Email Settings:**
   - **From Name:** The name that appears as the sender (defaults to your site name)
   - **From Email:** Your verified sending email address
   - **Reply-To:** Email address for replies (optional)

### Step 3: Test Your Configuration

1. **Scroll down to the "Test Email" section**
2. **Enter a test email address** (your own email is recommended)
3. **Click "Send Test Email"**
4. **Check your email** - you should receive the test email within a few minutes

### Step 4: Verify Installation

If the test email was sent successfully, your basic configuration is complete! You should see:

- ✅ **API Status:** Connected
- ✅ **Test Email:** Sent successfully
- ✅ **Plugin Status:** Active and working

## Obtaining Your Emailit API Key

If you don't have an Emailit account or API key yet:

### Create Emailit Account

1. **Visit Emailit.com**
2. **Sign up for an account** using your email address
3. **Verify your email address** through the confirmation email
4. **Complete your profile** with necessary information

### Get Your API Key

1. **Log in to your Emailit dashboard**
2. **Navigate to Settings > API Keys**
3. **Click "Generate New API Key"**
4. **Copy the generated key** (you won't be able to see it again)
5. **Paste it into your WordPress plugin settings**

### Verify Your Domain

1. **In your Emailit dashboard, go to Domains**
2. **Add your sending domain** (e.g., yoursite.com)
3. **Follow the DNS verification steps**
4. **Wait for verification** (can take up to 24 hours)

## Next Steps

Once your basic configuration is working:

1. **Explore the Interface** - Check out the [User Guide](user-guide.md) to understand all features
2. **Set Up Webhooks** - Configure real-time email tracking (optional)
3. **Enable Power User Mode** - Access advanced features if needed
4. **Configure FluentCRM** - If you use FluentCRM, set up the integration
5. **Review Best Practices** - Optimize your setup for best performance

## Troubleshooting Installation

### Common Installation Issues

**Plugin won't activate:**
- Check PHP version (must be 8.0+)
- Verify WordPress version (must be 5.7+)
- Check for plugin conflicts

**API connection fails:**
- Verify your API key is correct
- Check if your server can make outgoing HTTP requests
- Ensure your domain is verified in Emailit

**Test email doesn't arrive:**
- Check your spam folder
- Verify the "From Email" address is correct
- Check the email logs in the plugin dashboard

### Getting Help

If you encounter issues during installation:

1. **Check the [Troubleshooting Guide](troubleshooting.md)**
2. **Review the [FAQ](faq.md)**
3. **Enable debug logging** in the plugin settings
4. **Contact support** through the GitHub repository

---

**Ready to configure advanced features?** Continue to the [Configuration Guide](configuration.md) to set up webhooks, queues, and other advanced options.


