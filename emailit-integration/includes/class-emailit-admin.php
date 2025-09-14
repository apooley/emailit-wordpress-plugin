<?php
/**
 * Emailit Admin Class
 *
 * Handles admin interface and settings management.
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

class Emailit_Admin {

    /**
     * API handler instance
     */
    private $api;

    /**
     * Logger instance
     */
    private $logger;

    /**
     * Queue instance
     */
    private $queue;

    /**
     * Settings page slug
     */
    private $settings_page = 'emailit-settings';

    /**
     * Logs page slug
     */
    private $logs_page = 'emailit-logs';

    /**
     * Constructor
     */
    public function __construct($api, $logger, $queue = null) {
        $this->api = $api;
        $this->logger = $logger;
        $this->queue = $queue;
    }

    /**
     * Initialize admin functionality
     */
    public function init() {
        // Only initialize admin functionality in admin context
        if (!is_admin()) {
            return;
        }

        // Register settings
        $this->register_settings();

        // Add AJAX handlers
        add_action('wp_ajax_emailit_send_test_email', array($this, 'ajax_send_test_email'));
        add_action('wp_ajax_emailit_send_wordpress_test', array($this, 'ajax_send_wordpress_test'));
        add_action('wp_ajax_emailit_diagnostic', array($this, 'ajax_diagnostic'));
        add_action('wp_ajax_emailit_get_queue_stats', array($this, 'ajax_get_queue_stats'));
        add_action('wp_ajax_emailit_process_queue_now', array($this, 'ajax_process_queue_now'));
        add_action('wp_ajax_emailit_get_log_details', array($this, 'ajax_get_log_details'));
        add_action('wp_ajax_emailit_delete_log', array($this, 'ajax_delete_log'));
        add_action('wp_ajax_emailit_resend_email', array($this, 'ajax_resend_email'));
        add_action('wp_ajax_emailit_bulk_resend', array($this, 'ajax_bulk_resend'));
        add_action('wp_ajax_emailit_cleanup_logs', array($this, 'ajax_cleanup_logs'));
        add_action('wp_ajax_emailit_get_stats', array($this, 'ajax_get_stats'));
        add_action('wp_ajax_emailit_export_logs', array($this, 'ajax_export_logs'));
        add_action('wp_ajax_emailit_test_webhook', array($this, 'ajax_test_webhook'));

        // Add admin notices
        add_action('admin_notices', array($this, 'admin_notices'));

        // Add links to plugin actions
        add_filter('plugin_action_links_' . EMAILIT_PLUGIN_BASENAME, array($this, 'add_action_links'));

        // Schedule log cleanup
        if (!wp_next_scheduled('emailit_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'emailit_cleanup_logs');
        }
        add_action('emailit_cleanup_logs', array($this->logger, 'cleanup_logs'));
    }

    /**
     * Add menu pages
     */
    public function add_menu_pages() {
        // Only add menu pages in admin context
        if (!is_admin()) {
            return;
        }
        // Main settings page
        add_options_page(
            __('Emailit Settings', 'emailit-integration'),
            __('Emailit', 'emailit-integration'),
            'manage_options',
            $this->settings_page,
            array($this, 'settings_page_callback')
        );

        // Logs submenu
        add_submenu_page(
            'tools.php',
            __('Email Logs', 'emailit-integration'),
            __('Email Logs', 'emailit-integration'),
            'manage_options',
            $this->logs_page,
            array($this, 'logs_page_callback')
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        // API Settings
        register_setting('emailit-settings', 'emailit_api_key', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_api_key'),
            'default' => ''
        ));

        // Email Settings
        register_setting('emailit-settings', 'emailit_from_name', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => get_bloginfo('name')
        ));

        register_setting('emailit-settings', 'emailit_from_email', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_email',
            'default' => get_bloginfo('admin_email')
        ));

        register_setting('emailit-settings', 'emailit_reply_to', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_email',
            'default' => ''
        ));

        // Logging Settings
        register_setting('emailit-settings', 'emailit_enable_logging', array(
            'type' => 'boolean',
            'default' => 1
        ));

        register_setting('emailit-settings', 'emailit_log_retention_days', array(
            'type' => 'integer',
            'sanitize_callback' => array($this, 'sanitize_retention_days'),
            'default' => 30
        ));

        // Advanced Settings
        register_setting('emailit-settings', 'emailit_fallback_enabled', array(
            'type' => 'boolean',
            'default' => 1
        ));

        register_setting('emailit-settings', 'emailit_retry_attempts', array(
            'type' => 'integer',
            'sanitize_callback' => array($this, 'sanitize_retry_attempts'),
            'default' => 3
        ));

        register_setting('emailit-settings', 'emailit_timeout', array(
            'type' => 'integer',
            'sanitize_callback' => array($this, 'sanitize_timeout'),
            'default' => 30
        ));

        register_setting('emailit-settings', 'emailit_webhook_secret', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => wp_generate_password(32, false)
        ));

        register_setting('emailit-settings', 'emailit_enable_webhooks', array(
            'type' => 'boolean',
            'default' => 1
        ));

        // Queue/Async settings
        register_setting('emailit-settings', 'emailit_enable_queue', array(
            'type' => 'boolean',
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
            'default' => 0
        ));

        register_setting('emailit-settings', 'emailit_queue_batch_size', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 10
        ));

        register_setting('emailit-settings', 'emailit_queue_max_retries', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 3
        ));

        // Add settings sections
        add_settings_section(
            'emailit_api_section',
            __('API Configuration', 'emailit-integration'),
            array($this, 'api_section_callback'),
            'emailit-settings'
        );

        add_settings_section(
            'emailit_email_section',
            __('Email Settings', 'emailit-integration'),
            array($this, 'email_section_callback'),
            'emailit-settings'
        );

        add_settings_section(
            'emailit_logging_section',
            __('Logging Settings', 'emailit-integration'),
            array($this, 'logging_section_callback'),
            'emailit-settings'
        );

        add_settings_section(
            'emailit_performance_section',
            __('Performance & Queue Settings', 'emailit-integration'),
            array($this, 'performance_section_callback'),
            'emailit-settings'
        );

        add_settings_section(
            'emailit_advanced_section',
            __('Advanced Settings', 'emailit-integration'),
            array($this, 'advanced_section_callback'),
            'emailit-settings'
        );

        add_settings_section(
            'emailit_webhook_section',
            __('Webhook Settings', 'emailit-integration'),
            array($this, 'webhook_section_callback'),
            'emailit-settings'
        );

        // Add settings fields
        $this->add_settings_fields();
    }

    /**
     * Add settings fields
     */
    private function add_settings_fields() {
        // API fields
        add_settings_field(
            'emailit_api_key',
            __('API Key', 'emailit-integration'),
            array($this, 'api_key_field_callback'),
            'emailit-settings',
            'emailit_api_section'
        );

        // Email fields
        add_settings_field(
            'emailit_from_name',
            __('From Name', 'emailit-integration'),
            array($this, 'from_name_field_callback'),
            'emailit-settings',
            'emailit_email_section'
        );

        add_settings_field(
            'emailit_from_email',
            __('From Email', 'emailit-integration'),
            array($this, 'from_email_field_callback'),
            'emailit-settings',
            'emailit_email_section'
        );

        add_settings_field(
            'emailit_reply_to',
            __('Reply-To Email', 'emailit-integration'),
            array($this, 'reply_to_field_callback'),
            'emailit-settings',
            'emailit_email_section'
        );

        // Logging fields
        add_settings_field(
            'emailit_enable_logging',
            __('Enable Logging', 'emailit-integration'),
            array($this, 'enable_logging_field_callback'),
            'emailit-settings',
            'emailit_logging_section'
        );

        add_settings_field(
            'emailit_log_retention_days',
            __('Log Retention (Days)', 'emailit-integration'),
            array($this, 'log_retention_field_callback'),
            'emailit-settings',
            'emailit_logging_section'
        );

        // Performance/Queue fields
        add_settings_field(
            'emailit_enable_queue',
            __('Enable Asynchronous Sending', 'emailit-integration'),
            array($this, 'enable_queue_field_callback'),
            'emailit-settings',
            'emailit_performance_section'
        );

        add_settings_field(
            'emailit_queue_batch_size',
            __('Queue Batch Size', 'emailit-integration'),
            array($this, 'queue_batch_size_field_callback'),
            'emailit-settings',
            'emailit_performance_section'
        );

        add_settings_field(
            'emailit_queue_max_retries',
            __('Queue Max Retries', 'emailit-integration'),
            array($this, 'queue_max_retries_field_callback'),
            'emailit-settings',
            'emailit_performance_section'
        );

        // Advanced fields
        add_settings_field(
            'emailit_fallback_enabled',
            __('Enable Fallback', 'emailit-integration'),
            array($this, 'fallback_enabled_field_callback'),
            'emailit-settings',
            'emailit_advanced_section'
        );

        add_settings_field(
            'emailit_retry_attempts',
            __('Retry Attempts', 'emailit-integration'),
            array($this, 'retry_attempts_field_callback'),
            'emailit-settings',
            'emailit_advanced_section'
        );

        add_settings_field(
            'emailit_timeout',
            __('Timeout (Seconds)', 'emailit-integration'),
            array($this, 'timeout_field_callback'),
            'emailit-settings',
            'emailit_advanced_section'
        );

        add_settings_field(
            'emailit_enable_webhooks',
            __('Enable Webhooks', 'emailit-integration'),
            array($this, 'enable_webhooks_field_callback'),
            'emailit-settings',
            'emailit_webhook_section'
        );

        add_settings_field(
            'emailit_webhook_secret',
            __('Webhook Secret', 'emailit-integration'),
            array($this, 'webhook_secret_field_callback'),
            'emailit-settings',
            'emailit_webhook_section'
        );
    }

    /**
     * Settings page callback
     */
    public function settings_page_callback() {
        include EMAILIT_PLUGIN_DIR . 'admin/views/settings.php';
    }

    /**
     * Logs page callback
     */
    public function logs_page_callback() {
        include EMAILIT_PLUGIN_DIR . 'admin/views/logs.php';
    }

    /**
     * Section callbacks
     */
    public function api_section_callback() {
        echo '<p>' . __('Configure your Emailit API credentials.', 'emailit-integration') . '</p>';
    }

    public function email_section_callback() {
        echo '<p>' . __('Default email settings for outgoing messages.', 'emailit-integration') . '</p>';
    }

    public function logging_section_callback() {
        echo '<p>' . __('Configure email logging and retention settings.', 'emailit-integration') . '</p>';
    }

    public function performance_section_callback() {
        echo '<p>' . __('Configure asynchronous email sending and queue processing for better performance.', 'emailit-integration') . '</p>';
    }

    public function advanced_section_callback() {
        echo '<p>' . __('Advanced configuration options.', 'emailit-integration') . '</p>';
    }

    public function webhook_section_callback() {
        echo '<p>' . __('Configure webhook settings for real-time email status updates.', 'emailit-integration') . '</p>';
    }

    /**
     * Field callbacks
     */
    public function api_key_field_callback() {
        $raw_value = get_option('emailit_api_key', '');
        $is_valid = false;
        $has_key = !empty($raw_value);

        // Check API key validity safely
        if ($has_key) {
            try {
                $validation_result = $this->api->validate_api_key($raw_value);
                $is_valid = !is_wp_error($validation_result);
            } catch (Exception $e) {
                // Ignore validation errors in admin
                $is_valid = false;
            }
        }

        // Show placeholder if key exists, empty if not (NEVER show actual key)
        $display_value = $has_key ? '••••••••••••••••••••••••••••••••' : '';
        $placeholder = $has_key ? 'Enter new API key to replace existing key' : 'Enter your Emailit API key';

        echo '<input type="password" id="emailit_api_key" name="emailit_api_key" value="' . esc_attr($display_value) . '" placeholder="' . esc_attr($placeholder) . '" class="regular-text" data-has-key="' . ($has_key ? '1' : '0') . '" />';

        if ($is_valid) {
            echo ' <span class="emailit-status delivered">✓ Valid</span>';
        } elseif ($has_key) {
            echo ' <span class="emailit-status failed">✗ Invalid</span>';
        }

        if ($has_key) {
            echo '<p class="description">' . __('API key is set and encrypted. Enter a new key to replace it, or leave unchanged to keep current key.', 'emailit-integration') . '</p>';
        } else {
            echo '<p class="description">' . __('Enter your Emailit API key. You can find this in your Emailit dashboard.', 'emailit-integration') . '</p>';
        }
    }

    public function from_name_field_callback() {
        $value = get_option('emailit_from_name', get_bloginfo('name'));
        echo '<input type="text" id="emailit_from_name" name="emailit_from_name" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Default sender name for outgoing emails.', 'emailit-integration') . '</p>';
    }

    public function from_email_field_callback() {
        $value = get_option('emailit_from_email', get_bloginfo('admin_email'));
        echo '<input type="email" id="emailit_from_email" name="emailit_from_email" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Default sender email address.', 'emailit-integration') . '</p>';
    }

    public function reply_to_field_callback() {
        $value = get_option('emailit_reply_to', '');
        echo '<input type="email" id="emailit_reply_to" name="emailit_reply_to" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Optional reply-to email address. Leave blank to use from email.', 'emailit-integration') . '</p>';
    }

    public function enable_logging_field_callback() {
        $value = get_option('emailit_enable_logging', 1);
        echo '<input type="checkbox" id="emailit_enable_logging" name="emailit_enable_logging" value="1"' . checked(1, $value, false) . ' />';
        echo ' <label for="emailit_enable_logging">' . __('Enable email logging', 'emailit-integration') . '</label>';
        echo '<p class="description">' . __('Log all outgoing emails for tracking and debugging.', 'emailit-integration') . '</p>';
    }

    public function log_retention_field_callback() {
        $value = get_option('emailit_log_retention_days', 30);
        echo '<input type="number" id="emailit_log_retention_days" name="emailit_log_retention_days" value="' . esc_attr($value) . '" min="0" max="365" class="small-text" />';
        echo '<p class="description">' . __('Number of days to keep email logs. Set to 0 to keep logs indefinitely.', 'emailit-integration') . '</p>';
    }

    public function enable_queue_field_callback() {
        $value = get_option('emailit_enable_queue', 0);
        echo '<input type="checkbox" id="emailit_enable_queue" name="emailit_enable_queue" value="1"' . checked(1, $value, false) . ' />';
        echo ' <label for="emailit_enable_queue">' . __('Enable asynchronous email sending', 'emailit-integration') . '</label>';
        echo '<p class="description">' . __('Process emails in the background for better performance. Emails are queued and sent via WordPress cron.', 'emailit-integration') . '</p>';
    }

    public function queue_batch_size_field_callback() {
        $value = get_option('emailit_queue_batch_size', 10);
        echo '<input type="number" id="emailit_queue_batch_size" name="emailit_queue_batch_size" value="' . esc_attr($value) . '" min="1" max="50" class="small-text" />';
        echo '<p class="description">' . __('Number of emails to process in each batch. Lower values use less resources.', 'emailit-integration') . '</p>';
    }

    public function queue_max_retries_field_callback() {
        $value = get_option('emailit_queue_max_retries', 3);
        echo '<input type="number" id="emailit_queue_max_retries" name="emailit_queue_max_retries" value="' . esc_attr($value) . '" min="0" max="10" class="small-text" />';
        echo '<p class="description">' . __('Maximum number of retry attempts for failed emails.', 'emailit-integration') . '</p>';
    }

    public function fallback_enabled_field_callback() {
        $value = get_option('emailit_fallback_enabled', 1);
        echo '<input type="checkbox" id="emailit_fallback_enabled" name="emailit_fallback_enabled" value="1"' . checked(1, $value, false) . ' />';
        echo ' <label for="emailit_fallback_enabled">' . __('Enable fallback to wp_mail()', 'emailit-integration') . '</label>';
        echo '<p class="description">' . __('Fallback to WordPress default email system if Emailit API fails.', 'emailit-integration') . '</p>';
    }

    public function retry_attempts_field_callback() {
        $value = get_option('emailit_retry_attempts', 3);
        echo '<input type="number" id="emailit_retry_attempts" name="emailit_retry_attempts" value="' . esc_attr($value) . '" min="1" max="10" class="small-text" />';
        echo '<p class="description">' . __('Number of retry attempts for failed API requests.', 'emailit-integration') . '</p>';
    }

    public function timeout_field_callback() {
        $value = get_option('emailit_timeout', 30);
        echo '<input type="number" id="emailit_timeout" name="emailit_timeout" value="' . esc_attr($value) . '" min="5" max="120" class="small-text" />';
        echo '<p class="description">' . __('API request timeout in seconds.', 'emailit-integration') . '</p>';
    }

    public function enable_webhooks_field_callback() {
        $value = get_option('emailit_enable_webhooks', 1);
        echo '<input type="checkbox" id="emailit_enable_webhooks" name="emailit_enable_webhooks" value="1"' . checked(1, $value, false) . ' />';
        echo ' <label for="emailit_enable_webhooks">' . __('Enable webhook status updates', 'emailit-integration') . '</label>';
        echo '<p class="description">' . __('When enabled, Emailit will send status updates (delivery, bounces, etc.) to your WordPress site. When disabled, emails will show as "Sent to API" without further status updates.', 'emailit-integration') . '</p>';
    }

    public function webhook_secret_field_callback() {
        $value = get_option('emailit_webhook_secret', '');
        $webhook_url = rest_url('emailit/v1/webhook');
        $webhooks_enabled = get_option('emailit_enable_webhooks', 1);

        echo '<input type="text" id="emailit_webhook_secret" name="emailit_webhook_secret" value="' . esc_attr($value) . '" class="regular-text"' . ($webhooks_enabled ? '' : ' disabled') . ' />';
        echo '<p class="description">' . __('Enter the webhook secret provided by Emailit. This is used to validate webhook requests.', 'emailit-integration') . '</p>';
        echo '<p class="description">' . sprintf(__('Webhook endpoint: <code>%s</code>', 'emailit-integration'), esc_url($webhook_url)) . '</p>';
        if (!$webhooks_enabled) {
            echo '<p class="description" style="color: #666;"><em>' . __('Webhook secret is disabled when webhooks are turned off.', 'emailit-integration') . '</em></p>';
        }
    }

    /**
     * AJAX handlers
     */
    public function ajax_send_test_email() {
        check_ajax_referer('emailit_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'emailit-integration'));
        }

        $test_email = sanitize_email($_POST['test_email']);
        if (empty($test_email)) {
            $test_email = get_bloginfo('admin_email');
        }

        $result = $this->api->test_connection($test_email);

        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message()
            ));
        } else {
            wp_send_json_success(array(
                'message' => __('Test email sent successfully!', 'emailit-integration')
            ));
        }
    }

    /**
     * Handle WordPress wp_mail test email AJAX request
     */
    public function ajax_send_wordpress_test() {
        // Set up error handling to ensure JSON response
        @ini_set('display_errors', 0);

        // Start output buffering to capture any stray output
        ob_start();

        // Register fatal error handler
        register_shutdown_function(array($this, 'handle_ajax_fatal_error'));

        // Wrap the entire function in try-catch to capture any fatal errors
        try {
            // Debug logging (only when WP_DEBUG is enabled)
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Emailit] WordPress test email handler started');
            }

            // Verify request has nonce
            if (!isset($_POST['nonce'])) {
                wp_send_json_error(array(
                    'message' => __('Missing nonce parameter.', 'emailit-integration')
                ));
                return;
            }

            check_ajax_referer('emailit_admin_nonce', 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error(array(
                    'message' => __('Insufficient permissions.', 'emailit-integration')
                ));
                return;
            }

            $test_email = sanitize_email($_POST['test_email']);
            if (empty($test_email)) {
                $test_email = get_bloginfo('admin_email');
            }

            // Get site info
            $site_name = get_bloginfo('name');
            $site_url = get_bloginfo('url');
            $timestamp = current_time('mysql');

            // Prepare email content
            $subject = sprintf(__('[%s] WordPress Email Test via Emailit', 'emailit-integration'), $site_name);

            $message = sprintf(
                __('This is a test email sent through WordPress wp_mail() function, intercepted by the Emailit plugin.

Site: %s
URL: %s
Timestamp: %s
Plugin Version: %s

If you receive this email, the WordPress integration is working correctly.

This email was sent via the Emailit WordPress plugin test function.', 'emailit-integration'),
                $site_name,
                $site_url,
                $timestamp,
                EMAILIT_VERSION
            );

            // Set headers to indicate HTML content
            $headers = array(
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . $site_name . ' <' . get_option('admin_email') . '>'
            );

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Emailit] About to call wp_mail for test email to: ' . $test_email);
                error_log('[Emailit] Test email subject: ' . $subject);
                error_log('[Emailit] Test email headers: ' . print_r($headers, true));
            }

            // Send via wp_mail (which should be intercepted by our plugin)
            $result = wp_mail($test_email, $subject, nl2br($message), $headers);

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Emailit] wp_mail returned: ' . ($result ? 'true' : 'false'));

                // Check if our mailer was used
                if (function_exists('emailit_get_component')) {
                    $mailer = emailit_get_component('mailer');
                    if ($mailer) {
                        error_log('[Emailit] Mailer component available');
                    } else {
                        error_log('[Emailit] WARNING: Mailer component not available');
                    }
                }
            }

            // Clean any stray output before JSON response
            if (ob_get_length()) {
                ob_clean();
            }

            if ($result) {
                wp_send_json_success(array(
                    'message' => __('WordPress test email sent successfully! Check the email logs for details.', 'emailit-integration'),
                    'details' => array(
                        'method' => 'wp_mail',
                        'to' => $test_email,
                        'subject' => $subject,
                        'timestamp' => $timestamp
                    )
                ));
            } else {
                wp_send_json_error(array(
                    'message' => __('WordPress test email failed. wp_mail() returned false. Check the error logs for details.', 'emailit-integration')
                ));
            }

        } catch (Exception $e) {
            // Clean any stray output before JSON response
            if (ob_get_length()) {
                ob_clean();
            }

            // Always log exceptions (critical errors)
            error_log('[Emailit] Exception in WordPress test: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

            if ($this->logger) {
                $this->logger->log('WordPress test email exception', Emailit_Logger::LEVEL_ERROR, array(
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => defined('WP_DEBUG') && WP_DEBUG ? $e->getTraceAsString() : 'Debug disabled'
                ));
            }

            wp_send_json_error(array(
                'message' => sprintf(__('WordPress test email failed with exception: %s', 'emailit-integration'), $e->getMessage()),
                'technical_details' => defined('WP_DEBUG') && WP_DEBUG ? array(
                    'file' => basename($e->getFile()),
                    'line' => $e->getLine()
                ) : null
            ));
        } catch (Throwable $t) {
            // Clean any stray output before JSON response
            if (ob_get_length()) {
                ob_clean();
            }

            // Always log fatal errors (critical)
            error_log('[Emailit] Fatal error in WordPress test: ' . $t->getMessage() . ' in ' . $t->getFile() . ':' . $t->getLine());

            wp_send_json_error(array(
                'message' => sprintf(__('WordPress test email failed with fatal error: %s', 'emailit-integration'), $t->getMessage()),
                'technical_details' => defined('WP_DEBUG') && WP_DEBUG ? array(
                    'file' => basename($t->getFile()),
                    'line' => $t->getLine(),
                    'type' => 'Fatal Error'
                ) : null
            ));
        }
    }

    /**
     * Simple diagnostic AJAX handler to test if AJAX is working
     */
    public function ajax_diagnostic() {
        check_ajax_referer('emailit_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'emailit-integration'));
        }

        $diagnostic_info = array(
            'plugin_loaded' => class_exists('Emailit_Integration'),
            'mailer_loaded' => class_exists('Emailit_Mailer'),
            'api_loaded' => class_exists('Emailit_API'),
            'logger_loaded' => class_exists('Emailit_Logger'),
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version'),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'plugin_version' => EMAILIT_VERSION,
            'wp_mail_function_exists' => function_exists('wp_mail'),
            'ajax_url' => admin_url('admin-ajax.php'),
            'current_user_id' => get_current_user_id(),
            'current_time' => current_time('mysql')
        );

        wp_send_json_success(array(
            'message' => 'Diagnostic completed successfully',
            'diagnostic_info' => $diagnostic_info
        ));
    }

    /**
     * Get queue statistics via AJAX
     */
    public function ajax_get_queue_stats() {
        check_ajax_referer('emailit_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'emailit-integration'));
        }

        if (!$this->queue) {
            wp_send_json_error(array(
                'message' => __('Queue system not available.', 'emailit-integration')
            ));
            return;
        }

        try {
            $stats = $this->queue->get_stats();

            // Ensure we have valid statistics
            if (!is_array($stats) || empty($stats)) {
                wp_send_json_error(array(
                    'message' => __('Failed to retrieve queue statistics.', 'emailit-integration')
                ));
                return;
            }

            wp_send_json_success($stats);

        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Emailit] Queue stats AJAX error: ' . $e->getMessage());
            }

            wp_send_json_error(array(
                'message' => __('Error retrieving queue statistics. Please try again.', 'emailit-integration')
            ));
        }
    }

    /**
     * Process queue manually via AJAX
     */
    public function ajax_process_queue_now() {
        check_ajax_referer('emailit_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'emailit-integration'));
        }

        if ($this->queue) {
            try {
                $this->queue->process_queue();
                wp_send_json_success(array(
                    'message' => __('Queue processed successfully.', 'emailit-integration')
                ));
            } catch (Exception $e) {
                wp_send_json_error(array(
                    'message' => sprintf(__('Queue processing failed: %s', 'emailit-integration'), $e->getMessage())
                ));
            }
        } else {
            wp_send_json_error(array(
                'message' => __('Queue system not available.', 'emailit-integration')
            ));
        }
    }

    public function ajax_get_log_details() {
        check_ajax_referer('emailit_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'emailit-integration'));
        }

        $log_id = intval($_POST['log_id']);
        $log = $this->logger->get_log($log_id);

        if (!$log) {
            wp_send_json_error(array(
                'message' => __('Log not found.', 'emailit-integration')
            ));
        }

        $webhook_logs = $this->logger->get_webhook_logs($log['email_id']);

        ob_start();
        include EMAILIT_PLUGIN_DIR . 'admin/views/log-details.php';
        $html = ob_get_clean();

        wp_send_json_success(array(
            'html' => $html
        ));
    }

    public function ajax_delete_log() {
        check_ajax_referer('emailit_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'emailit-integration'));
        }

        $log_id = intval($_POST['log_id']);
        $result = $this->logger->delete_log($log_id);

        if ($result) {
            wp_send_json_success(array(
                'message' => __('Log deleted successfully.', 'emailit-integration')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to delete log.', 'emailit-integration')
            ));
        }
    }

    public function ajax_resend_email() {
        check_ajax_referer('emailit_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'emailit-integration'));
        }

        $log_id = intval($_POST['log_id']);
        $mailer = emailit_get_component('mailer');
        $result = $mailer->resend_email($log_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message()
            ));
        } else {
            wp_send_json_success(array(
                'message' => __('Email resent successfully.', 'emailit-integration')
            ));
        }
    }

    public function ajax_get_stats() {
        check_ajax_referer('emailit_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'emailit-integration'));
        }

        $stats = $this->logger->get_stats();

        wp_send_json_success($stats);
    }

    /**
     * AJAX handler for bulk resend emails
     */
    public function ajax_bulk_resend() {
        check_ajax_referer('emailit_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'emailit-integration'));
        }

        $log_ids = isset($_POST['log_ids']) ? array_map('intval', $_POST['log_ids']) : array();

        if (empty($log_ids)) {
            wp_send_json_error(array(
                'message' => __('No emails selected for resend.', 'emailit-integration')
            ));
        }

        if (count($log_ids) > 50) {
            wp_send_json_error(array(
                'message' => __('Maximum 50 emails can be resent at once.', 'emailit-integration')
            ));
        }

        $mailer = emailit_get_component('mailer');
        $successes = 0;
        $failures = 0;
        $errors = array();

        foreach ($log_ids as $log_id) {
            $result = $mailer->resend_email($log_id);

            if (is_wp_error($result)) {
                $failures++;
                $errors[] = sprintf(__('Log ID %d: %s', 'emailit-integration'), $log_id, $result->get_error_message());
            } else {
                $successes++;
            }

            // Add small delay to prevent API rate limiting
            usleep(100000); // 0.1 second delay
        }

        $message = sprintf(
            __('Bulk resend completed: %d successful, %d failed.', 'emailit-integration'),
            $successes,
            $failures
        );

        if (!empty($errors)) {
            $message .= ' ' . __('Errors:', 'emailit-integration') . ' ' . implode('; ', array_slice($errors, 0, 3));
            if (count($errors) > 3) {
                $message .= sprintf(__(' and %d more...', 'emailit-integration'), count($errors) - 3);
            }
        }

        wp_send_json_success(array(
            'message' => $message,
            'successes' => $successes,
            'failures' => $failures
        ));
    }

    /**
     * AJAX handler for manual log cleanup
     */
    public function ajax_cleanup_logs() {
        check_ajax_referer('emailit_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'emailit-integration'));
        }

        $result = $this->logger->cleanup_logs();

        if ($result !== false) {
            $message = sprintf(
                __('Log cleanup completed: %d email logs and %d webhook logs deleted.', 'emailit-integration'),
                $result['email_logs_deleted'],
                $result['webhook_logs_deleted']
            );

            wp_send_json_success(array('message' => $message));
        } else {
            wp_send_json_error(array(
                'message' => __('Log cleanup failed. Please try again.', 'emailit-integration')
            ));
        }
    }

    /**
     * AJAX handler for exporting logs
     */
    public function ajax_export_logs() {
        check_ajax_referer('emailit_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'emailit-integration'));
        }

        $format = isset($_POST['format']) ? sanitize_text_field($_POST['format']) : 'csv';
        $status_filter = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
        $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';

        $args = array(
            'per_page' => 1000, // Limit export size
            'page' => 1,
            'status' => $status_filter,
            'date_from' => $date_from,
            'date_to' => $date_to
        );

        $logs_data = $this->logger->get_logs($args);
        $logs = $logs_data['logs'];

        if (empty($logs)) {
            wp_send_json_error(array(
                'message' => __('No logs found matching the criteria.', 'emailit-integration')
            ));
        }

        $filename = 'emailit-logs-' . date('Y-m-d-H-i-s') . '.' . $format;

        if ($format === 'csv') {
            $this->export_logs_csv($logs, $filename);
        } else {
            $this->export_logs_json($logs, $filename);
        }
    }

    /**
     * Export logs as CSV
     */
    private function export_logs_csv($logs, $filename) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // CSV headers
        fputcsv($output, array(
            'ID',
            'Date',
            'To',
            'From',
            'Subject',
            'Status',
            'Email ID',
            'Sent At'
        ));

        // CSV data
        foreach ($logs as $log) {
            fputcsv($output, array(
                $log['id'],
                $log['created_at'],
                $log['to_email'],
                $log['from_email'],
                $log['subject'],
                $log['status'],
                $log['email_id'],
                $log['sent_at']
            ));
        }

        fclose($output);
        exit;
    }

    /**
     * Export logs as JSON
     */
    private function export_logs_json($logs, $filename) {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo wp_json_encode($logs, JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * AJAX handler for testing webhook
     */
    public function ajax_test_webhook() {
        check_ajax_referer('emailit_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'emailit-integration'));
        }

        // Get webhook component
        $webhook = emailit_get_component('webhook');
        if (!$webhook) {
            wp_send_json_error(array(
                'message' => __('Webhook component not available.', 'emailit-integration')
            ));
        }

        // Create test payload using Emailit's actual webhook format
        $test_payload = array(
            'webhook_request_id' => '1234567890',
            'event_id' => 'test_' . time(),
            'type' => 'email.delivery.sent',
            'object' => array(
                'email' => array(
                    'id' => time(),
                    'token' => 'test_token_' . wp_generate_password(12, false),
                    'type' => 'outgoing',
                    'message_id' => '<test_' . time() . '@emailit.dev>',
                    'to' => get_bloginfo('admin_email'),
                    'from' => get_option('emailit_from_name', get_bloginfo('name')) . ' <' . get_option('emailit_from_email', get_bloginfo('admin_email')) . '>',
                    'subject' => 'Test Webhook - ' . get_bloginfo('name'),
                    'timestamp' => time() . '.123',
                    'spam_status' => 0,
                    'tag' => null
                ),
                'status' => 'sent',
                'details' => 'Test webhook event generated by Emailit Integration plugin',
                'sent_with_ssl' => true,
                'timestamp' => microtime(true),
                'time' => 0.56
            )
        );

        // Test the webhook by simulating a REST API request
        try {
            // Create a mock WP_REST_Request
            $mock_request = new WP_REST_Request('POST', '/emailit/v1/webhook');
            $mock_request->set_body(wp_json_encode($test_payload));
            $mock_request->add_header('Content-Type', 'application/json');

            // Add webhook secret header if configured
            $webhook_secret = get_option('emailit_webhook_secret', '');
            if (!empty($webhook_secret)) {
                $signature = 'sha256=' . hash_hmac('sha256', wp_json_encode($test_payload), $webhook_secret);
                $mock_request->add_header('X-Emailit-Signature', $signature);
            }

            $result = $webhook->handle_webhook($mock_request);

            if (is_wp_error($result)) {
                wp_send_json_error(array(
                    'message' => sprintf(__('Webhook test failed: %s', 'emailit-integration'), $result->get_error_message())
                ));
            } else {
                wp_send_json_success(array(
                    'message' => __('Webhook test successful! The webhook endpoint is working correctly.', 'emailit-integration')
                ));
            }
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => sprintf(__('Webhook test failed: %s', 'emailit-integration'), $e->getMessage())
            ));
        }
    }

    /**
     * Admin notices
     */
    public function admin_notices() {
        // Check if API key is configured
        $api_key = get_option('emailit_api_key', '');
        if (empty($api_key) && $this->is_emailit_page()) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p>' . sprintf(
                __('Emailit Integration is not configured. Please <a href="%s">enter your API key</a> to start sending emails.', 'emailit-integration'),
                admin_url('options-general.php?page=' . $this->settings_page)
            ) . '</p>';
            echo '</div>';
        }

        // Check API key validity
        if (!empty($api_key)) {
            $validation = $this->api->validate_api_key($api_key);
            if (is_wp_error($validation) && $this->is_emailit_page()) {
                echo '<div class="notice notice-error is-dismissible">';
                echo '<p>' . sprintf(
                    __('Emailit API key is invalid: %s', 'emailit-integration'),
                    $validation->get_error_message()
                ) . '</p>';
                echo '</div>';
            }
        }

        // Check for plugin conflicts
        $conflicts = $this->check_plugin_conflicts();
        if (!empty($conflicts)) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>' . __('Emailit Integration: Plugin Conflict Detected', 'emailit-integration') . '</strong></p>';
            echo '<p>' . __('The following plugins may interfere with email delivery:', 'emailit-integration') . '</p>';
            echo '<ul style="margin-left: 20px;">';
            foreach ($conflicts as $conflict) {
                echo '<li><strong>' . esc_html($conflict['name']) . '</strong> - ' . esc_html($conflict['reason']) . '</li>';
            }
            echo '</ul>';
            echo '<p>' . sprintf(
                __('Please review your email settings or <a href="%s">contact support</a> if you experience delivery issues.', 'emailit-integration'),
                admin_url('options-general.php?page=' . $this->settings_page . '&tab=test')
            ) . '</p>';
            echo '</div>';
        }
    }

    /**
     * Get plugin conflicts (public method for templates)
     */
    public function get_plugin_conflicts() {
        return $this->check_plugin_conflicts();
    }

    /**
     * Check for plugin conflicts with wp_mail
     */
    private function check_plugin_conflicts() {
        $conflicts = array();

        // Check for common SMTP/email plugins that might conflict
        $smtp_plugins = array(
            'wp-mail-smtp/wp_mail_smtp.php' => array(
                'name' => 'WP Mail SMTP',
                'reason' => __('May override wp_mail() function', 'emailit-integration')
            ),
            'easy-wp-smtp/easy-wp-smtp.php' => array(
                'name' => 'Easy WP SMTP',
                'reason' => __('SMTP configuration may conflict', 'emailit-integration')
            ),
            'wp-smtp/wp-smtp.php' => array(
                'name' => 'WP SMTP',
                'reason' => __('SMTP settings may interfere', 'emailit-integration')
            ),
            'post-smtp/postman-smtp.php' => array(
                'name' => 'Post SMTP Mailer',
                'reason' => __('May replace wp_mail() functionality', 'emailit-integration')
            ),
            'gmail-smtp/gmail-smtp.php' => array(
                'name' => 'Gmail SMTP',
                'reason' => __('Gmail SMTP may take precedence', 'emailit-integration')
            ),
            'wp-ses/wp-ses.php' => array(
                'name' => 'WP SES',
                'reason' => __('Amazon SES integration may conflict', 'emailit-integration')
            ),
            'sendgrid-email-delivery-simplified/sendgrid.php' => array(
                'name' => 'SendGrid',
                'reason' => __('SendGrid API may override emails', 'emailit-integration')
            ),
            'mailgun/mailgun.php' => array(
                'name' => 'Mailgun',
                'reason' => __('Mailgun API integration may conflict', 'emailit-integration')
            ),
            'wp-mailgun-smtp/wp-mailgun-smtp.php' => array(
                'name' => 'WP Mailgun SMTP',
                'reason' => __('Mailgun SMTP may interfere', 'emailit-integration')
            ),
            'fluent-smtp/fluent-smtp.php' => array(
                'name' => 'FluentSMTP',
                'reason' => __('SMTP configuration may conflict', 'emailit-integration')
            )
        );

        // Check if any conflicting plugins are active
        foreach ($smtp_plugins as $plugin_path => $plugin_info) {
            if (is_plugin_active($plugin_path)) {
                $conflicts[] = $plugin_info;
            }
        }

        // Check for functions that might override wp_mail
        $this->check_wp_mail_overrides($conflicts);

        // Check for PHPMailer modifications
        $this->check_phpmailer_modifications($conflicts);

        return $conflicts;
    }

    /**
     * Check for wp_mail function overrides
     */
    private function check_wp_mail_overrides(&$conflicts) {
        global $wp_filter;

        // Check if wp_mail function is overridden
        if (function_exists('wp_mail')) {
            $reflection = new ReflectionFunction('wp_mail');
            $filename = $reflection->getFileName();

            // If wp_mail is not in wp-includes/pluggable.php, it's been overridden
            if ($filename && strpos($filename, 'wp-includes/pluggable.php') === false) {
                $conflicts[] = array(
                    'name' => __('Custom wp_mail() Override', 'emailit-integration'),
                    'reason' => sprintf(__('wp_mail() function overridden in %s', 'emailit-integration'), basename($filename))
                );
            }
        }

        // Check for high-priority filters on pre_wp_mail
        if (isset($wp_filter['pre_wp_mail'])) {
            $high_priority_filters = array();
            foreach ($wp_filter['pre_wp_mail']->callbacks as $priority => $callbacks) {
                if ($priority < 10) { // Our plugin uses priority 10
                    foreach ($callbacks as $callback) {
                        $function_name = $this->get_callback_name($callback['function']);
                        if ($function_name && strpos($function_name, 'emailit') === false) {
                            $high_priority_filters[] = $function_name;
                        }
                    }
                }
            }

            if (!empty($high_priority_filters)) {
                $conflicts[] = array(
                    'name' => __('High Priority Email Filter', 'emailit-integration'),
                    'reason' => sprintf(__('Functions with higher priority: %s', 'emailit-integration'), implode(', ', $high_priority_filters))
                );
            }
        }
    }

    /**
     * Check for PHPMailer modifications
     */
    private function check_phpmailer_modifications(&$conflicts) {
        global $wp_filter;

        // Check for multiple phpmailer_init hooks
        if (isset($wp_filter['phpmailer_init'])) {
            $phpmailer_hooks = array();
            foreach ($wp_filter['phpmailer_init']->callbacks as $priority => $callbacks) {
                foreach ($callbacks as $callback) {
                    $function_name = $this->get_callback_name($callback['function']);
                    if ($function_name && strpos($function_name, 'emailit') === false) {
                        $phpmailer_hooks[] = $function_name;
                    }
                }
            }

            if (count($phpmailer_hooks) > 0) {
                $conflicts[] = array(
                    'name' => __('PHPMailer Modifications', 'emailit-integration'),
                    'reason' => sprintf(__('Other plugins modifying PHPMailer: %s', 'emailit-integration'), implode(', ', array_slice($phpmailer_hooks, 0, 3)) . (count($phpmailer_hooks) > 3 ? '...' : ''))
                );
            }
        }
    }

    /**
     * Get callback function name for debugging
     */
    private function get_callback_name($callback) {
        if (is_string($callback)) {
            return $callback;
        } elseif (is_array($callback) && count($callback) == 2) {
            if (is_object($callback[0])) {
                return get_class($callback[0]) . '::' . $callback[1];
            } else {
                return $callback[0] . '::' . $callback[1];
            }
        } elseif ($callback instanceof Closure) {
            return 'Closure';
        }

        return 'Unknown';
    }

    /**
     * Check if current page is an Emailit page
     */
    private function is_emailit_page() {
        $screen = get_current_screen();
        return in_array($screen->id, array(
            'settings_page_' . $this->settings_page,
            'tools_page_' . $this->logs_page
        ));
    }

    /**
     * Add action links to plugin page
     */
    public function add_action_links($links) {
        $settings_link = '<a href="' . admin_url('options-general.php?page=' . $this->settings_page) . '">' . __('Settings', 'emailit-integration') . '</a>';
        $logs_link = '<a href="' . admin_url('tools.php?page=' . $this->logs_page) . '">' . __('Logs', 'emailit-integration') . '</a>';

        array_unshift($links, $logs_link, $settings_link);

        return $links;
    }

    /**
     * Sanitization callbacks
     */
    public function sanitize_api_key($value) {
        $value = sanitize_text_field($value);

        // If the value is empty, keep the existing key
        if (empty($value)) {
            return get_option('emailit_api_key', '');
        }

        // If the value is our placeholder (dots), keep the existing key
        if ($value === '••••••••••••••••••••••••••••••••') {
            return get_option('emailit_api_key', '');
        }

        // If we have a new actual API key, validate and encrypt it
        if (!empty($value) && $value !== get_option('emailit_api_key', '')) {
            // Basic validation - API keys should be alphanumeric with some special chars
            if (!preg_match('/^[a-zA-Z0-9\-_\.]+$/', $value)) {
                add_settings_error(
                    'emailit_api_key',
                    'invalid_api_key_format',
                    __('API key contains invalid characters. Please check your API key and try again.', 'emailit-integration')
                );
                return get_option('emailit_api_key', ''); // Keep existing key on error
            }

            // Test the API key if possible (but don't fail if API is down)
            try {
                $validation_result = $this->api->validate_api_key($value);
                if (is_wp_error($validation_result)) {
                    add_settings_error(
                        'emailit_api_key',
                        'invalid_api_key',
                        sprintf(__('API key validation failed: %s', 'emailit-integration'), $validation_result->get_error_message())
                    );
                    // Continue anyway in case it's a temporary API issue
                }
            } catch (Exception $e) {
                // Log but don't fail - could be temporary network issue
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[Emailit] API key validation error during save: ' . $e->getMessage());
                }
            }

            // Encrypt and store the new key
            return $this->api->encrypt_api_key($value);
        }

        // Default: return the sanitized value
        return $value;
    }

    public function sanitize_retention_days($value) {
        $value = intval($value);
        return max(0, min(365, $value));
    }

    public function sanitize_retry_attempts($value) {
        $value = intval($value);
        return max(1, min(10, $value));
    }

    public function sanitize_timeout($value) {
        $value = intval($value);
        return max(5, min(120, $value));
    }

    public function sanitize_checkbox($value) {
        return !empty($value) ? 1 : 0;
    }

    /**
     * Handle fatal errors during AJAX requests to ensure JSON response
     */
    public function handle_ajax_fatal_error() {
        $error = error_get_last();
        if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_COMPILE_ERROR)) {
            // Clear any output that may have been sent
            while (ob_get_level()) {
                ob_end_clean();
            }

            // Log the fatal error
            error_log('[Emailit] Fatal error in AJAX: ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line']);

            // Only send JSON if we haven't sent any output yet
            if (!headers_sent()) {
                // Send JSON error response
                wp_send_json_error(array(
                    'message' => __('A fatal error occurred during WordPress test email. The database schema has been updated automatically. Please try again.', 'emailit-integration'),
                    'technical_details' => defined('WP_DEBUG') && WP_DEBUG ? array(
                        'error' => $error['message'],
                        'file' => basename($error['file']),
                        'line' => $error['line'],
                        'suggestion' => 'Database schema was automatically upgraded. The next request should work.'
                    ) : null
                ));
            }
        }
    }
}