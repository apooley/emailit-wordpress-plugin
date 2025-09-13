<?php
/**
 * Admin Settings Page Template
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Get current tab
$current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';

// Handle form submission
if (isset($_POST['submit']) && check_admin_referer('emailit_settings_nonce')) {
    // WordPress will handle the settings saving
    echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully.', 'emailit-integration') . '</p></div>';
}
?>

<div class="wrap emailit-admin-wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <!-- Navigation Tabs -->
    <nav class="nav-tab-wrapper emailit-tab-nav">
        <a href="?page=<?php echo esc_attr($_GET['page']); ?>&tab=general"
           class="nav-tab <?php echo $current_tab === 'general' ? 'nav-tab-active' : ''; ?>">
            <?php _e('General', 'emailit-integration'); ?>
        </a>
        <a href="?page=<?php echo esc_attr($_GET['page']); ?>&tab=advanced"
           class="nav-tab <?php echo $current_tab === 'advanced' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Advanced', 'emailit-integration'); ?>
        </a>
        <a href="?page=<?php echo esc_attr($_GET['page']); ?>&tab=webhook"
           class="nav-tab <?php echo $current_tab === 'webhook' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Webhook', 'emailit-integration'); ?>
        </a>
        <a href="?page=<?php echo esc_attr($_GET['page']); ?>&tab=test"
           class="nav-tab <?php echo $current_tab === 'test' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Test', 'emailit-integration'); ?>
        </a>
    </nav>

    <form method="post" action="options.php">
        <?php
        settings_fields('emailit_settings');
        wp_nonce_field('emailit_settings_nonce');
        ?>

        <?php if ($current_tab === 'general') : ?>
            <!-- General Settings Tab -->
            <div class="emailit-tab-content">
                <h2><?php _e('API Configuration', 'emailit-integration'); ?></h2>
                <table class="form-table emailit-form-table" role="presentation">
                    <tbody>
                        <?php do_settings_fields('emailit_settings', 'emailit_api_section'); ?>
                    </tbody>
                </table>

                <h2><?php _e('Email Settings', 'emailit-integration'); ?></h2>
                <table class="form-table emailit-form-table" role="presentation">
                    <tbody>
                        <?php do_settings_fields('emailit_settings', 'emailit_email_section'); ?>
                    </tbody>
                </table>

                <h2><?php _e('Logging Settings', 'emailit-integration'); ?></h2>
                <table class="form-table emailit-form-table" role="presentation">
                    <tbody>
                        <?php do_settings_fields('emailit_settings', 'emailit_logging_section'); ?>
                    </tbody>
                </table>

                <?php submit_button(); ?>
            </div>

        <?php elseif ($current_tab === 'advanced') : ?>
            <!-- Advanced Settings Tab -->
            <div class="emailit-tab-content">
                <h2><?php _e('Advanced Settings', 'emailit-integration'); ?></h2>
                <table class="form-table emailit-form-table" role="presentation">
                    <tbody>
                        <?php do_settings_fields('emailit_settings', 'emailit_advanced_section'); ?>
                    </tbody>
                </table>

                <?php submit_button(); ?>
            </div>

        <?php elseif ($current_tab === 'webhook') : ?>
            <!-- Webhook Settings Tab -->
            <div class="emailit-tab-content">
                <h2><?php _e('Webhook Configuration', 'emailit-integration'); ?></h2>

                <div class="emailit-webhook-info">
                    <p><?php _e('Configure webhooks to receive real-time email status updates from Emailit.', 'emailit-integration'); ?></p>

                    <table class="form-table emailit-form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label><?php _e('Webhook URL', 'emailit-integration'); ?></label>
                                </th>
                                <td>
                                    <code id="webhook-url"><?php echo esc_url(rest_url('emailit/v1/webhook')); ?></code>
                                    <button type="button" class="button button-secondary" onclick="copyToClipboard('webhook-url')">
                                        <?php _e('Copy', 'emailit-integration'); ?>
                                    </button>
                                    <p class="description">
                                        <?php _e('Use this URL in your Emailit dashboard to configure webhooks.', 'emailit-integration'); ?>
                                    </p>
                                </td>
                            </tr>
                            <?php do_settings_fields('emailit_settings', 'emailit_advanced_section'); ?>
                        </tbody>
                    </table>

                    <div class="emailit-webhook-test">
                        <h3><?php _e('Test Webhook', 'emailit-integration'); ?></h3>
                        <p><?php _e('Test your webhook endpoint to ensure it\'s working correctly.', 'emailit-integration'); ?></p>
                        <button type="button" id="test-webhook" class="button button-secondary">
                            <?php _e('Test Webhook', 'emailit-integration'); ?>
                        </button>
                        <div id="webhook-test-result" class="emailit-test-result" style="display: none;"></div>
                    </div>
                </div>

                <?php submit_button(); ?>
            </div>

        <?php elseif ($current_tab === 'test') : ?>
            <!-- Test Tab -->
            <div class="emailit-tab-content">
                <div class="emailit-test-email">
                    <h3><?php _e('Send Test Email', 'emailit-integration'); ?></h3>
                    <p><?php _e('Send a test email to verify your Emailit integration is working correctly.', 'emailit-integration'); ?></p>

                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label for="emailit_test_email"><?php _e('Test Email Address', 'emailit-integration'); ?></label>
                                </th>
                                <td>
                                    <input type="email" id="emailit_test_email" class="regular-text"
                                           value="<?php echo esc_attr(get_bloginfo('admin_email')); ?>" />
                                    <p class="description">
                                        <?php _e('Email address to send the test email to.', 'emailit-integration'); ?>
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <p>
                        <button type="button" id="emailit-test-email" class="button button-primary">
                            <?php _e('Send Test Email', 'emailit-integration'); ?>
                        </button>
                    </p>

                    <div id="emailit-test-result" class="emailit-test-result" style="display: none;"></div>
                </div>

                <!-- Email Statistics -->
                <div class="emailit-stats-section">
                    <h3><?php _e('Email Statistics (Last 30 Days)', 'emailit-integration'); ?></h3>
                    <div id="emailit-stats" class="emailit-stats">
                        <div class="emailit-loading"><?php _e('Loading statistics...', 'emailit-integration'); ?></div>
                    </div>
                </div>

                <!-- System Information -->
                <div class="emailit-system-info">
                    <h3><?php _e('System Information', 'emailit-integration'); ?></h3>
                    <table class="widefat striped">
                        <tbody>
                            <tr>
                                <td><strong><?php _e('Plugin Version', 'emailit-integration'); ?></strong></td>
                                <td><?php echo esc_html(EMAILIT_VERSION); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('WordPress Version', 'emailit-integration'); ?></strong></td>
                                <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('PHP Version', 'emailit-integration'); ?></strong></td>
                                <td><?php echo esc_html(PHP_VERSION); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('API Endpoint', 'emailit-integration'); ?></strong></td>
                                <td><code><?php echo esc_html(EMAILIT_API_ENDPOINT); ?></code></td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('Webhook Endpoint', 'emailit-integration'); ?></strong></td>
                                <td><code><?php echo esc_url(rest_url('emailit/v1/webhook')); ?></code></td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('Logging Enabled', 'emailit-integration'); ?></strong></td>
                                <td><?php echo get_option('emailit_enable_logging') ? __('Yes', 'emailit-integration') : __('No', 'emailit-integration'); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('Fallback Enabled', 'emailit-integration'); ?></strong></td>
                                <td><?php echo get_option('emailit_fallback_enabled') ? __('Yes', 'emailit-integration') : __('No', 'emailit-integration'); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php endif; ?>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Test webhook functionality
    $('#test-webhook').on('click', function() {
        var $button = $(this);
        var $result = $('#webhook-test-result');

        $button.prop('disabled', true).text('<?php _e('Testing...', 'emailit-integration'); ?>');
        $result.hide().removeClass('success error');

        $.post(ajaxurl, {
            action: 'emailit_test_webhook',
            nonce: '<?php echo wp_create_nonce('emailit_admin_nonce'); ?>'
        })
        .done(function(response) {
            if (response.success) {
                $result.addClass('success').html('<strong><?php _e('Success:', 'emailit-integration'); ?></strong> ' + response.data.message);
            } else {
                $result.addClass('error').html('<strong><?php _e('Error:', 'emailit-integration'); ?></strong> ' + response.data.message);
            }
        })
        .fail(function() {
            $result.addClass('error').html('<strong><?php _e('Error:', 'emailit-integration'); ?></strong> <?php _e('Failed to test webhook.', 'emailit-integration'); ?>');
        })
        .always(function() {
            $button.prop('disabled', false).text('<?php _e('Test Webhook', 'emailit-integration'); ?>');
            $result.show();
        });
    });

    // Copy to clipboard functionality
    window.copyToClipboard = function(elementId) {
        var element = document.getElementById(elementId);
        var text = element.textContent || element.innerText;

        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function() {
                alert('<?php _e('Copied to clipboard!', 'emailit-integration'); ?>');
            });
        } else {
            // Fallback for older browsers
            var textArea = document.createElement("textarea");
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            alert('<?php _e('Copied to clipboard!', 'emailit-integration'); ?>');
        }
    };

    // Regenerate webhook secret
    $('#regenerate-webhook-secret').on('click', function() {
        if (confirm('<?php _e('Are you sure you want to regenerate the webhook secret? You will need to update it in your Emailit dashboard.', 'emailit-integration'); ?>')) {
            var newSecret = generateRandomString(32);
            $('#emailit_webhook_secret').val(newSecret);
        }
    });

    function generateRandomString(length) {
        var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        var result = '';
        for (var i = 0; i < length; i++) {
            result += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        return result;
    }
});
</script>

<style>
.emailit-webhook-info {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
}

.emailit-webhook-test {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
}

.emailit-stats-section {
    margin-top: 40px;
}

.emailit-system-info {
    margin-top: 40px;
}

.emailit-system-info table {
    max-width: 600px;
}

#webhook-url {
    background: #f0f0f0;
    padding: 5px 8px;
    border-radius: 3px;
    font-family: monospace;
    margin-right: 10px;
}
</style>