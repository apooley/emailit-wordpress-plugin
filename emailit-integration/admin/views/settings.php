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

// Handle form submission - WordPress handles this automatically via options.php
?>

<div class="wrap emailit-admin-wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <!-- Navigation Tabs -->
    <nav class="nav-tab-wrapper emailit-tab-nav">
        <a href="#general" data-tab="general"
           class="nav-tab <?php echo $current_tab === 'general' ? 'nav-tab-active active' : ''; ?>">
            <?php _e('General', 'emailit-integration'); ?>
        </a>
        <a href="#performance" data-tab="performance"
           class="nav-tab <?php echo $current_tab === 'performance' ? 'nav-tab-active active' : ''; ?>">
            <?php _e('Performance', 'emailit-integration'); ?>
        </a>
        <a href="#advanced" data-tab="advanced"
           class="nav-tab <?php echo $current_tab === 'advanced' ? 'nav-tab-active active' : ''; ?>">
            <?php _e('Advanced', 'emailit-integration'); ?>
        </a>
        <a href="#webhook" data-tab="webhook"
           class="nav-tab <?php echo $current_tab === 'webhook' ? 'nav-tab-active active' : ''; ?>">
            <?php _e('Webhook', 'emailit-integration'); ?>
        </a>
        <a href="#test" data-tab="test"
           class="nav-tab <?php echo $current_tab === 'test' ? 'nav-tab-active active' : ''; ?>">
            <?php _e('Test', 'emailit-integration'); ?>
        </a>
    </nav>

    <form method="post" action="options.php">
        <?php
        settings_fields('emailit-settings');
        ?>

        <!-- General Settings Tab -->
        <div id="general" class="emailit-tab-pane <?php echo $current_tab === 'general' ? 'active' : ''; ?>">
            <h2><?php _e('API Configuration', 'emailit-integration'); ?></h2>
            <table class="form-table emailit-form-table" role="presentation">
                <tbody>
                    <?php do_settings_fields('emailit-settings', 'emailit_api_section'); ?>
                </tbody>
            </table>

            <h2><?php _e('Email Settings', 'emailit-integration'); ?></h2>
            <table class="form-table emailit-form-table" role="presentation">
                <tbody>
                    <?php do_settings_fields('emailit-settings', 'emailit_email_section'); ?>
                </tbody>
            </table>

            <h2><?php _e('Logging Settings', 'emailit-integration'); ?></h2>
            <table class="form-table emailit-form-table" role="presentation">
                <tbody>
                    <?php do_settings_fields('emailit-settings', 'emailit_logging_section'); ?>
                </tbody>
            </table>

            <?php submit_button(); ?>
        </div>

        <!-- Performance & Queue Settings Tab -->
        <div id="performance" class="emailit-tab-pane <?php echo $current_tab === 'performance' ? 'active' : ''; ?>">
            <h2><?php _e('Performance & Queue Settings', 'emailit-integration'); ?></h2>

            <div class="emailit-performance-info">
                <p><?php _e('Configure asynchronous email processing for improved site performance.', 'emailit-integration'); ?></p>
            </div>

            <table class="form-table emailit-form-table" role="presentation">
                <tbody>
                    <?php do_settings_fields('emailit-settings', 'emailit_performance_section'); ?>
                </tbody>
            </table>

            <div class="emailit-queue-status">
                <h3><?php _e('Queue Status', 'emailit-integration'); ?></h3>
                <div id="queue-status-info">
                    <div class="queue-stats">
                        <span class="queue-stat">
                            <strong><?php _e('Pending:', 'emailit-integration'); ?></strong>
                            <span id="queue-pending">-</span>
                        </span>
                        <span class="queue-stat">
                            <strong><?php _e('Processing:', 'emailit-integration'); ?></strong>
                            <span id="queue-processing">-</span>
                        </span>
                        <span class="queue-stat">
                            <strong><?php _e('Failed:', 'emailit-integration'); ?></strong>
                            <span id="queue-failed">-</span>
                        </span>
                    </div>
                    <p>
                        <button type="button" id="refresh-queue-stats" class="button button-secondary">
                            <?php _e('Refresh Stats', 'emailit-integration'); ?>
                        </button>
                        <button type="button" id="process-queue-now" class="button button-secondary">
                            <?php _e('Process Queue Now', 'emailit-integration'); ?>
                        </button>
                    </p>
                </div>
            </div>

            <?php submit_button(); ?>
        </div>

        <!-- Advanced Settings Tab -->
        <div id="advanced" class="emailit-tab-pane <?php echo $current_tab === 'advanced' ? 'active' : ''; ?>">
            <h2><?php _e('Advanced Settings', 'emailit-integration'); ?></h2>
            <table class="form-table emailit-form-table" role="presentation">
                <tbody>
                    <?php do_settings_fields('emailit-settings', 'emailit_advanced_section'); ?>
                </tbody>
            </table>

            <?php submit_button(); ?>
        </div>

        <!-- Webhook Settings Tab -->
        <div id="webhook" class="emailit-tab-pane <?php echo $current_tab === 'webhook' ? 'active' : ''; ?>">
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
                        <?php do_settings_fields('emailit-settings', 'emailit_webhook_section'); ?>
                    </tbody>
                </table>

                    <!-- Webhook Filtering Information -->
                    <div class="emailit-webhook-filtering">
                        <h3><?php _e('Site-Specific Webhook Filtering', 'emailit-integration'); ?></h3>
                        <div class="notice notice-info inline">
                            <p>
                                <span class="dashicons dashicons-filter" style="color: #0073aa;"></span>
                                <?php _e('This plugin automatically filters webhook events to only process emails sent from this site.', 'emailit-integration'); ?>
                            </p>
                        </div>

                        <?php
                        // Get webhook component to show filtering info
                        $webhook = emailit_get_component('webhook');
                        if ($webhook) {
                            // Get site domain and recognized emails
                            $site_url = get_site_url();
                            $parsed = parse_url($site_url);
                            $site_domain = isset($parsed['host']) ? $parsed['host'] : '';

                            // Get configured emails
                            $admin_email = get_bloginfo('admin_email');
                            $emailit_from = get_option('emailit_from_email', '');
                        ?>

                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Filtering Criteria', 'emailit-integration'); ?></th>
                                    <th><?php _e('Value', 'emailit-integration'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong><?php _e('Site Domain', 'emailit-integration'); ?></strong></td>
                                    <td><code><?php echo esc_html($site_domain); ?></code></td>
                                </tr>
                                <tr>
                                    <td><strong><?php _e('Admin Email', 'emailit-integration'); ?></strong></td>
                                    <td><code><?php echo esc_html($admin_email); ?></code></td>
                                </tr>
                                <?php if (!empty($emailit_from) && $emailit_from !== $admin_email) : ?>
                                <tr>
                                    <td><strong><?php _e('Configured From Email', 'emailit-integration'); ?></strong></td>
                                    <td><code><?php echo esc_html($emailit_from); ?></code></td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <td><strong><?php _e('Common Site Emails', 'emailit-integration'); ?></strong></td>
                                    <td>
                                        <?php
                                        $common_emails = array(
                                            'wordpress@' . $site_domain,
                                            'admin@' . $site_domain,
                                            'noreply@' . $site_domain,
                                            'no-reply@' . $site_domain
                                        );
                                        foreach ($common_emails as $email) {
                                            echo '<code>' . esc_html($email) . '</code><br>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <p class="description">
                            <?php _e('Only webhook events for emails matching these criteria will be processed and logged. Emails sent from other sites in your Emailit workspace will be ignored.', 'emailit-integration'); ?>
                        </p>

                        <?php } ?>
                    </div>

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

        <!-- Test Settings Tab -->
        <div id="test" class="emailit-tab-pane <?php echo $current_tab === 'test' ? 'active' : ''; ?>">
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

                <!-- WordPress wp_mail Test -->
                <div class="emailit-wordpress-test">
                    <h3><?php _e('WordPress Email Test', 'emailit-integration'); ?></h3>
                    <p><?php _e('Send a test email through WordPress wp_mail() function to diagnose integration issues. This test uses the same path as contact forms and other WordPress features.', 'emailit-integration'); ?></p>

                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label for="emailit_wordpress_test_email"><?php _e('Test Email Address', 'emailit-integration'); ?></label>
                                </th>
                                <td>
                                    <input type="email" id="emailit_wordpress_test_email" class="regular-text"
                                           value="<?php echo esc_attr(get_bloginfo('admin_email')); ?>" />
                                    <p class="description">
                                        <?php _e('Email address to send the WordPress test email to.', 'emailit-integration'); ?>
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <p>
                        <button type="button" id="emailit-wordpress-test" class="button button-secondary">
                            <?php _e('Send WordPress Test Email', 'emailit-integration'); ?>
                        </button>
                        <span class="description" style="margin-left: 10px;">
                            <?php _e('This will help diagnose critical errors during email sending.', 'emailit-integration'); ?>
                        </span>
                    </p>

                    <div id="emailit-wordpress-test-result" class="emailit-test-result" style="display: none;"></div>
                </div>

                <?php if (defined('WP_DEBUG') && WP_DEBUG) : ?>
                <!-- Diagnostic Test -->
                <div class="emailit-diagnostic-test">
                    <h3><?php _e('Plugin Diagnostic', 'emailit-integration'); ?></h3>
                    <p><?php _e('Test if the plugin is loading correctly and AJAX functionality is working.', 'emailit-integration'); ?></p>

                    <p>
                        <button type="button" id="emailit-diagnostic" class="button button-secondary">
                            <?php _e('Run Diagnostic Test', 'emailit-integration'); ?>
                        </button>
                        <span class="description" style="margin-left: 10px;">
                            <?php _e('This will help identify plugin loading issues.', 'emailit-integration'); ?>
                        </span>
                    </p>

                    <div id="emailit-diagnostic-result" class="emailit-test-result" style="display: none;"></div>
                </div>
                <?php endif; ?>

                <!-- Email Statistics -->
                <div class="emailit-stats-section">
                    <h3><?php _e('Email Statistics (Last 30 Days)', 'emailit-integration'); ?></h3>
                    <div id="emailit-stats" class="emailit-stats">
                        <div class="emailit-loading"><?php _e('Loading statistics...', 'emailit-integration'); ?></div>
                    </div>
                </div>

                <!-- Plugin Conflict Check -->
                <div class="emailit-conflict-check">
                    <h3><?php _e('Plugin Compatibility Check', 'emailit-integration'); ?></h3>
                    <?php
                    $admin = emailit_get_component('admin');
                    $conflicts = $admin ? $admin->get_plugin_conflicts() : array();

                    if (empty($conflicts)) {
                        echo '<div class="notice notice-success inline">';
                        echo '<p><span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> ';
                        echo __('No email plugin conflicts detected.', 'emailit-integration') . '</p>';
                        echo '</div>';
                    } else {
                        echo '<div class="notice notice-warning inline">';
                        echo '<p><span class="dashicons dashicons-warning" style="color: #f56e28;"></span> ';
                        echo sprintf(_n('%d potential conflict detected:', '%d potential conflicts detected:', count($conflicts), 'emailit-integration'), count($conflicts)) . '</p>';
                        echo '<ul style="margin-left: 20px;">';
                        foreach ($conflicts as $conflict) {
                            echo '<li><strong>' . esc_html($conflict['name']) . '</strong> - ' . esc_html($conflict['reason']) . '</li>';
                        }
                        echo '</ul>';
                        echo '<p><em>' . __('These plugins may interfere with Emailit\'s email delivery. Consider deactivating them or testing thoroughly.', 'emailit-integration') . '</em></p>';
                        echo '</div>';
                    }
                    ?>
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
                            <tr>
                                <td><strong><?php _e('wp_mail() Status', 'emailit-integration'); ?></strong></td>
                                <td>
                                    <?php
                                    if (function_exists('wp_mail')) {
                                        $reflection = new ReflectionFunction('wp_mail');
                                        $filename = $reflection->getFileName();
                                        if ($filename && strpos($filename, 'wp-includes/pluggable.php') !== false) {
                                            echo '<span style="color: #46b450;">✓ ' . __('WordPress Default', 'emailit-integration') . '</span>';
                                        } else {
                                            echo '<span style="color: #f56e28;">⚠ ' . sprintf(__('Overridden in %s', 'emailit-integration'), basename($filename)) . '</span>';
                                        }
                                    } else {
                                        echo '<span style="color: #d63638;">✗ ' . __('Function not available', 'emailit-integration') . '</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('Email Hooks Active', 'emailit-integration'); ?></strong></td>
                                <td>
                                    <?php
                                    global $wp_filter;
                                    $pre_wp_mail_count = isset($wp_filter['pre_wp_mail']) ? count($wp_filter['pre_wp_mail']->callbacks) : 0;
                                    $phpmailer_count = isset($wp_filter['phpmailer_init']) ? count($wp_filter['phpmailer_init']->callbacks) : 0;

                                    printf(
                                        __('pre_wp_mail: %d, phpmailer_init: %d', 'emailit-integration'),
                                        $pre_wp_mail_count,
                                        $phpmailer_count
                                    );
                                    ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
        </div>
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

    // Prevent form submission when pressing Enter on test email field
    $('#emailit_test_email').on('keypress', function(e) {
        if (e.which === 13 || e.keyCode === 13) { // Enter key
            e.preventDefault();
            $('#emailit-test-email').trigger('click'); // Trigger test email button instead
            return false;
        }
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