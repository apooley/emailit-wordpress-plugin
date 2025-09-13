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
    public function __construct($api, $logger) {
        $this->api = $api;
        $this->logger = $logger;
    }

    /**
     * Initialize admin functionality
     */
    public function init() {
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));

        // Add AJAX handlers
        add_action('wp_ajax_emailit_send_test_email', array($this, 'ajax_send_test_email'));
        add_action('wp_ajax_emailit_get_log_details', array($this, 'ajax_get_log_details'));
        add_action('wp_ajax_emailit_delete_log', array($this, 'ajax_delete_log'));
        add_action('wp_ajax_emailit_resend_email', array($this, 'ajax_resend_email'));
        add_action('wp_ajax_emailit_get_stats', array($this, 'ajax_get_stats'));

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
        register_setting('emailit_settings', 'emailit_api_key', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_api_key'),
            'default' => ''
        ));

        // Email Settings
        register_setting('emailit_settings', 'emailit_from_name', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => get_bloginfo('name')
        ));

        register_setting('emailit_settings', 'emailit_from_email', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_email',
            'default' => get_bloginfo('admin_email')
        ));

        register_setting('emailit_settings', 'emailit_reply_to', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_email',
            'default' => ''
        ));

        // Logging Settings
        register_setting('emailit_settings', 'emailit_enable_logging', array(
            'type' => 'boolean',
            'default' => 1
        ));

        register_setting('emailit_settings', 'emailit_log_retention_days', array(
            'type' => 'integer',
            'sanitize_callback' => array($this, 'sanitize_retention_days'),
            'default' => 30
        ));

        // Advanced Settings
        register_setting('emailit_settings', 'emailit_fallback_enabled', array(
            'type' => 'boolean',
            'default' => 1
        ));

        register_setting('emailit_settings', 'emailit_retry_attempts', array(
            'type' => 'integer',
            'sanitize_callback' => array($this, 'sanitize_retry_attempts'),
            'default' => 3
        ));

        register_setting('emailit_settings', 'emailit_timeout', array(
            'type' => 'integer',
            'sanitize_callback' => array($this, 'sanitize_timeout'),
            'default' => 30
        ));

        register_setting('emailit_settings', 'emailit_webhook_secret', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => wp_generate_password(32, false)
        ));

        // Add settings sections
        add_settings_section(
            'emailit_api_section',
            __('API Configuration', 'emailit-integration'),
            array($this, 'api_section_callback'),
            'emailit_settings'
        );

        add_settings_section(
            'emailit_email_section',
            __('Email Settings', 'emailit-integration'),
            array($this, 'email_section_callback'),
            'emailit_settings'
        );

        add_settings_section(
            'emailit_logging_section',
            __('Logging Settings', 'emailit-integration'),
            array($this, 'logging_section_callback'),
            'emailit_settings'
        );

        add_settings_section(
            'emailit_advanced_section',
            __('Advanced Settings', 'emailit-integration'),
            array($this, 'advanced_section_callback'),
            'emailit_settings'
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
            'emailit_settings',
            'emailit_api_section'
        );

        // Email fields
        add_settings_field(
            'emailit_from_name',
            __('From Name', 'emailit-integration'),
            array($this, 'from_name_field_callback'),
            'emailit_settings',
            'emailit_email_section'
        );

        add_settings_field(
            'emailit_from_email',
            __('From Email', 'emailit-integration'),
            array($this, 'from_email_field_callback'),
            'emailit_settings',
            'emailit_email_section'
        );

        add_settings_field(
            'emailit_reply_to',
            __('Reply-To Email', 'emailit-integration'),
            array($this, 'reply_to_field_callback'),
            'emailit_settings',
            'emailit_email_section'
        );

        // Logging fields
        add_settings_field(
            'emailit_enable_logging',
            __('Enable Logging', 'emailit-integration'),
            array($this, 'enable_logging_field_callback'),
            'emailit_settings',
            'emailit_logging_section'
        );

        add_settings_field(
            'emailit_log_retention_days',
            __('Log Retention (Days)', 'emailit-integration'),
            array($this, 'log_retention_field_callback'),
            'emailit_settings',
            'emailit_logging_section'
        );

        // Advanced fields
        add_settings_field(
            'emailit_fallback_enabled',
            __('Enable Fallback', 'emailit-integration'),
            array($this, 'fallback_enabled_field_callback'),
            'emailit_settings',
            'emailit_advanced_section'
        );

        add_settings_field(
            'emailit_retry_attempts',
            __('Retry Attempts', 'emailit-integration'),
            array($this, 'retry_attempts_field_callback'),
            'emailit_settings',
            'emailit_advanced_section'
        );

        add_settings_field(
            'emailit_timeout',
            __('Timeout (Seconds)', 'emailit-integration'),
            array($this, 'timeout_field_callback'),
            'emailit_settings',
            'emailit_advanced_section'
        );

        add_settings_field(
            'emailit_webhook_secret',
            __('Webhook Secret', 'emailit-integration'),
            array($this, 'webhook_secret_field_callback'),
            'emailit_settings',
            'emailit_advanced_section'
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

    public function advanced_section_callback() {
        echo '<p>' . __('Advanced configuration options.', 'emailit-integration') . '</p>';
    }

    /**
     * Field callbacks
     */
    public function api_key_field_callback() {
        $value = get_option('emailit_api_key', '');
        $is_valid = !empty($value) && !is_wp_error($this->api->validate_api_key());

        echo '<input type="password" id="emailit_api_key" name="emailit_api_key" value="' . esc_attr($value) . '" class="regular-text" />';

        if ($is_valid) {
            echo ' <span class="emailit-status delivered">✓ Valid</span>';
        } elseif (!empty($value)) {
            echo ' <span class="emailit-status failed">✗ Invalid</span>';
        }

        echo '<p class="description">' . __('Enter your Emailit API key. You can find this in your Emailit dashboard.', 'emailit-integration') . '</p>';
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

    public function webhook_secret_field_callback() {
        $value = get_option('emailit_webhook_secret', '');
        $webhook_url = rest_url('emailit/v1/webhook');

        echo '<input type="text" id="emailit_webhook_secret" name="emailit_webhook_secret" value="' . esc_attr($value) . '" class="regular-text" readonly />';
        echo ' <button type="button" id="regenerate-webhook-secret" class="button button-secondary">' . __('Regenerate', 'emailit-integration') . '</button>';
        echo '<p class="description">' . sprintf(__('Webhook endpoint: <code>%s</code>', 'emailit-integration'), esc_url($webhook_url)) . '</p>';
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
            $validation = $this->api->validate_api_key();
            if (is_wp_error($validation) && $this->is_emailit_page()) {
                echo '<div class="notice notice-error is-dismissible">';
                echo '<p>' . sprintf(
                    __('Emailit API key is invalid: %s', 'emailit-integration'),
                    $validation->get_error_message()
                ) . '</p>';
                echo '</div>';
            }
        }
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

        // Encrypt the API key before storing
        if (!empty($value)) {
            $this->api->set_api_key($value);
            return $value; // The API class handles encryption
        }

        return '';
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
}