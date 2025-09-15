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
        add_action('wp_ajax_emailit_clean_old_logs', array($this, 'ajax_clean_old_logs'));
        add_action('wp_ajax_emailit_optimize_database', array($this, 'ajax_optimize_database'));
        add_action('wp_ajax_emailit_clear_cache', array($this, 'ajax_clear_cache'));

        // FluentCRM soft bounce management AJAX handlers (only if FluentCRM is available)
        if ($this->is_fluentcrm_available()) {
            add_action('wp_ajax_emailit_get_soft_bounce_stats', array($this, 'ajax_get_soft_bounce_stats'));
            add_action('wp_ajax_emailit_reset_subscriber_bounce', array($this, 'ajax_reset_subscriber_bounce'));
            add_action('wp_ajax_emailit_get_subscriber_bounce_details', array($this, 'ajax_get_subscriber_bounce_details'));
        }

        // Health monitoring AJAX handlers
        add_action('wp_ajax_emailit_health_check', array($this, 'ajax_health_check'));
        add_action('wp_ajax_emailit_cleanup_health_data', array($this, 'ajax_cleanup_health_data'));
        add_action('wp_ajax_emailit_optimize_health_tables', array($this, 'ajax_optimize_health_tables'));

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
            __('Emailit Log', 'emailit-integration'),
            __('Emailit Log', 'emailit-integration'),
            'manage_options',
            $this->logs_page,
            array($this, 'logs_page_callback')
        );

        // Health Monitor submenu
        add_submenu_page(
            'tools.php',
            __('Emailit Health Monitor', 'emailit-integration'),
            __('Emailit Health Monitor', 'emailit-integration'),
            'manage_options',
            'emailit-health-monitor',
            array($this, 'health_monitor_page_callback')
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

        // FluentCRM Integration settings (only register if FluentCRM is available)
        if ($this->is_fluentcrm_available()) {
            register_setting('emailit-settings', 'emailit_fluentcrm_integration', array(
                'type' => 'boolean',
                'sanitize_callback' => array($this, 'sanitize_checkbox'),
                'default' => 1
            ));

            register_setting('emailit-settings', 'emailit_fluentcrm_forward_bounces', array(
                'type' => 'boolean',
                'sanitize_callback' => array($this, 'sanitize_checkbox'),
                'default' => 1
            ));

            register_setting('emailit-settings', 'emailit_fluentcrm_suppress_default', array(
                'type' => 'boolean',
                'sanitize_callback' => array($this, 'sanitize_checkbox'),
                'default' => 0
            ));

            register_setting('emailit-settings', 'emailit_fluentcrm_hard_bounce_action', array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'unsubscribe'
            ));

            register_setting('emailit-settings', 'emailit_fluentcrm_soft_bounce_action', array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'track'
            ));

            register_setting('emailit-settings', 'emailit_fluentcrm_soft_bounce_threshold', array(
                'type' => 'integer',
                'sanitize_callback' => 'absint',
                'default' => 5
            ));

            register_setting('emailit-settings', 'emailit_fluentcrm_complaint_action', array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'unsubscribe'
            ));

            // Action mapping settings
            register_setting('emailit-settings', 'emailit_fluentcrm_enable_action_mapping', array(
                'type' => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default' => true
            ));

            register_setting('emailit-settings', 'emailit_fluentcrm_auto_create_subscribers', array(
                'type' => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default' => true
            ));

            register_setting('emailit-settings', 'emailit_fluentcrm_confidence_threshold', array(
                'type' => 'integer',
                'sanitize_callback' => 'absint',
                'default' => 70
            ));

            // Soft bounce threshold management settings
            register_setting('emailit-settings', 'emailit_fluentcrm_soft_bounce_threshold', array(
                'type' => 'integer',
                'sanitize_callback' => 'absint',
                'default' => 5
            ));

            register_setting('emailit-settings', 'emailit_fluentcrm_soft_bounce_window', array(
                'type' => 'integer',
                'sanitize_callback' => 'absint',
                'default' => 7
            ));

            register_setting('emailit-settings', 'emailit_fluentcrm_soft_bounce_reset_on_success', array(
                'type' => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default' => true
            ));

            register_setting('emailit-settings', 'emailit_fluentcrm_soft_bounce_history_limit', array(
                'type' => 'integer',
                'sanitize_callback' => 'absint',
                'default' => 10
            ));
        }

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

        // Only add FluentCRM section if FluentCRM is available
        if ($this->is_fluentcrm_available()) {
            add_settings_section(
                'emailit_fluentcrm_section',
                __('FluentCRM Integration', 'emailit-integration'),
                array($this, 'fluentcrm_section_callback'),
                'emailit-settings'
            );
        }

        // Error handling section
        add_settings_section(
            'emailit_error_handling_section',
            __('Advanced Error Handling', 'emailit-integration'),
            array($this, 'error_handling_section_callback'),
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

        // FluentCRM Integration fields (only add if FluentCRM is available)
        if ($this->is_fluentcrm_available()) {
            add_settings_field(
                'emailit_fluentcrm_integration',
                __('Enable FluentCRM Integration', 'emailit-integration'),
                array($this, 'fluentcrm_integration_field_callback'),
                'emailit-settings',
                'emailit_fluentcrm_section'
            );

            add_settings_field(
                'emailit_fluentcrm_forward_bounces',
                __('Forward Bounces to Emailit', 'emailit-integration'),
                array($this, 'fluentcrm_forward_bounces_field_callback'),
                'emailit-settings',
                'emailit_fluentcrm_section'
            );

            add_settings_field(
                'emailit_fluentcrm_suppress_default',
                __('Suppress Default WordPress Emails', 'emailit-integration'),
                array($this, 'fluentcrm_suppress_default_field_callback'),
                'emailit-settings',
                'emailit_fluentcrm_section'
            );

            add_settings_field(
                'emailit_fluentcrm_hard_bounce_action',
                __('Hard Bounce Action', 'emailit-integration'),
                array($this, 'fluentcrm_hard_bounce_action_field_callback'),
                'emailit-settings',
                'emailit_fluentcrm_section'
            );

            add_settings_field(
                'emailit_fluentcrm_soft_bounce_action',
                __('Soft Bounce Action', 'emailit-integration'),
                array($this, 'fluentcrm_soft_bounce_action_field_callback'),
                'emailit-settings',
                'emailit_fluentcrm_section'
            );

            add_settings_field(
                'emailit_fluentcrm_soft_bounce_threshold',
                __('Soft Bounce Threshold', 'emailit-integration'),
                array($this, 'fluentcrm_soft_bounce_threshold_field_callback'),
                'emailit-settings',
                'emailit_fluentcrm_section'
            );

            add_settings_field(
                'emailit_fluentcrm_complaint_action',
                __('Complaint Action', 'emailit-integration'),
                array($this, 'fluentcrm_complaint_action_field_callback'),
                'emailit-settings',
                'emailit_fluentcrm_section'
            );

            // Action mapping fields
            add_settings_field(
                'emailit_fluentcrm_enable_action_mapping',
                __('Enable Action Mapping', 'emailit-integration'),
                array($this, 'fluentcrm_enable_action_mapping_field_callback'),
                'emailit-settings',
                'emailit_fluentcrm_section'
            );

            add_settings_field(
                'emailit_fluentcrm_auto_create_subscribers',
                __('Auto-Create Subscribers', 'emailit-integration'),
                array($this, 'fluentcrm_auto_create_subscribers_field_callback'),
                'emailit-settings',
                'emailit_fluentcrm_section'
            );

            add_settings_field(
                'emailit_fluentcrm_confidence_threshold',
                __('Confidence Threshold', 'emailit-integration'),
                array($this, 'fluentcrm_confidence_threshold_field_callback'),
                'emailit-settings',
                'emailit_fluentcrm_section'
            );

            // Soft bounce threshold management fields

            add_settings_field(
                'emailit_fluentcrm_soft_bounce_window',
                __('Soft Bounce Window (Days)', 'emailit-integration'),
                array($this, 'fluentcrm_soft_bounce_window_field_callback'),
                'emailit-settings',
                'emailit_fluentcrm_section'
            );

            add_settings_field(
                'emailit_fluentcrm_soft_bounce_reset_on_success',
                __('Reset on Successful Delivery', 'emailit-integration'),
                array($this, 'fluentcrm_soft_bounce_reset_on_success_field_callback'),
                'emailit-settings',
                'emailit_fluentcrm_section'
            );

            add_settings_field(
                'emailit_fluentcrm_soft_bounce_history_limit',
                __('Bounce History Limit', 'emailit-integration'),
                array($this, 'fluentcrm_soft_bounce_history_limit_field_callback'),
                'emailit-settings',
                'emailit_fluentcrm_section'
            );
        }

        // Error handling fields
        add_settings_field(
            'emailit_error_analytics_enabled',
            __('Error Analytics', 'emailit-integration'),
            array($this, 'error_analytics_enabled_field_callback'),
            'emailit-settings',
            'emailit_error_handling_section'
        );

        add_settings_field(
            'emailit_retry_enabled',
            __('Automatic Retry', 'emailit-integration'),
            array($this, 'retry_enabled_field_callback'),
            'emailit-settings',
            'emailit_error_handling_section'
        );

        add_settings_field(
            'emailit_error_notifications_enabled',
            __('Error Notifications', 'emailit-integration'),
            array($this, 'error_notifications_enabled_field_callback'),
            'emailit-settings',
            'emailit_error_handling_section'
        );

        add_settings_field(
            'emailit_circuit_breaker_enabled',
            __('Circuit Breaker', 'emailit-integration'),
            array($this, 'circuit_breaker_enabled_field_callback'),
            'emailit-settings',
            'emailit_error_handling_section'
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
     * Health Monitor page callback
     */
    public function health_monitor_page_callback() {
        // Initialize health monitor and get status
        $health_monitor = emailit_get_component('health_monitor');
        if (!$health_monitor) {
            echo '<div class="notice notice-error"><p>' . __('Health monitoring is not available.', 'emailit-integration') . '</p></div>';
            return;
        }
        
        $health_status = $health_monitor->get_health_status();
        $health_metrics = $health_monitor->get_health_metrics();
        
        include EMAILIT_PLUGIN_DIR . 'admin/views/health-monitor.php';
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

    public function fluentcrm_section_callback() {
        $webhook = emailit_get_component('webhook');
        $fluentcrm_status = $webhook ? $webhook->get_fluentcrm_integration_status() : array('available' => false);
        
        echo '<p>' . __('Configure FluentCRM integration for seamless bounce handling and subscriber management.', 'emailit-integration') . '</p>';
        
        if ($fluentcrm_status['available']) {
            echo '<div class="notice notice-success inline"><p><strong>' . __('FluentCRM Detected', 'emailit-integration') . '</strong> - ' . sprintf(__('Version %s is active and ready for integration.', 'emailit-integration'), $fluentcrm_status['version']) . '</p></div>';
        } else {
            echo '<div class="notice notice-warning inline"><p><strong>' . __('FluentCRM Not Detected', 'emailit-integration') . '</strong> - ' . __('Install and activate FluentCRM to enable advanced email management features.', 'emailit-integration') . '</p></div>';
        }
    }

    public function error_handling_section_callback() {
        echo '<p>' . __('Configure advanced error handling, retry mechanisms, and notification systems for improved reliability.', 'emailit-integration') . '</p>';
        
        // Get error handling status
        $error_handler = emailit_get_component('error_handler');
        if ($error_handler) {
            $status = $error_handler->get_error_handling_status();
            echo '<div class="error-handling-status">';
            echo '<h4>' . __('Error Handling Status', 'emailit-integration') . '</h4>';
            echo '<ul>';
            echo '<li><strong>' . __('Circuit Breaker:', 'emailit-integration') . '</strong> ' . ucfirst($status['circuit_breaker']['status']) . '</li>';
            if ($status['statistics']) {
                echo '<li><strong>' . __('Total Errors (24h):', 'emailit-integration') . '</strong> ' . $status['statistics']['total_errors'] . '</li>';
                echo '<li><strong>' . __('Resolution Rate:', 'emailit-integration') . '</strong> ' . round($status['statistics']['resolution_rate'], 1) . '%</li>';
            }
            echo '</ul>';
            echo '</div>';
        }
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

        echo '<div class="emailit-api-key-container">';
        echo '<input type="password" id="emailit_api_key" name="emailit_api_key" value="' . esc_attr($display_value) . '" placeholder="' . esc_attr($placeholder) . '" class="regular-text" data-has-key="' . ($has_key ? '1' : '0') . '" />';

        if ($is_valid) {
            echo '<span class="emailit-status delivered">✓ Valid</span>';
        } elseif ($has_key) {
            echo '<span class="emailit-status failed">✗ Invalid</span>';
        }
        echo '</div>';

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
     * Check if FluentCRM is available
     */
    private function is_fluentcrm_available() {
        return class_exists('FluentCrm\App\App');
    }

    /**
     * FluentCRM Integration field callbacks
     */
    public function fluentcrm_integration_field_callback() {
        $value = get_option('emailit_fluentcrm_integration', 1);
        $webhook = emailit_get_component('webhook');
        $fluentcrm_status = $webhook ? $webhook->get_fluentcrm_integration_status() : array('available' => false);
        
        echo '<input type="checkbox" id="emailit_fluentcrm_integration" name="emailit_fluentcrm_integration" value="1"' . checked(1, $value, false) . ($fluentcrm_status['available'] ? '' : ' disabled') . ' />';
        echo ' <label for="emailit_fluentcrm_integration">' . __('Enable FluentCRM integration', 'emailit-integration') . '</label>';
        
        if ($fluentcrm_status['available']) {
            echo '<p class="description">' . __('Automatically sync bounce data between FluentCRM and Emailit for better deliverability management.', 'emailit-integration') . '</p>';
        } else {
            echo '<p class="description" style="color: #666;"><em>' . __('FluentCRM integration is disabled because FluentCRM is not installed or active.', 'emailit-integration') . '</em></p>';
        }
    }

    public function fluentcrm_forward_bounces_field_callback() {
        $value = get_option('emailit_fluentcrm_forward_bounces', 1);
        $webhook = emailit_get_component('webhook');
        $fluentcrm_status = $webhook ? $webhook->get_fluentcrm_integration_status() : array('available' => false);
        
        echo '<input type="checkbox" id="emailit_fluentcrm_forward_bounces" name="emailit_fluentcrm_forward_bounces" value="1"' . checked(1, $value, false) . ($fluentcrm_status['available'] ? '' : ' disabled') . ' />';
        echo ' <label for="emailit_fluentcrm_forward_bounces">' . __('Forward bounce data to Emailit API', 'emailit-integration') . '</label>';
        echo '<p class="description">' . __('When enabled, bounce information from FluentCRM will be forwarded to Emailit for comprehensive tracking.', 'emailit-integration') . '</p>';
    }

    public function fluentcrm_suppress_default_field_callback() {
        $value = get_option('emailit_fluentcrm_suppress_default', 0);
        $webhook = emailit_get_component('webhook');
        $fluentcrm_status = $webhook ? $webhook->get_fluentcrm_integration_status() : array('available' => false);
        
        echo '<input type="checkbox" id="emailit_fluentcrm_suppress_default" name="emailit_fluentcrm_suppress_default" value="1"' . checked(1, $value, false) . ($fluentcrm_status['available'] ? '' : ' disabled') . ' />';
        echo ' <label for="emailit_fluentcrm_suppress_default">' . __('Suppress default WordPress emails when FluentCRM is active', 'emailit-integration') . '</label>';
        echo '<p class="description">' . __('Prevent WordPress from sending duplicate emails when FluentCRM is handling the same functionality.', 'emailit-integration') . '</p>';
    }

    public function fluentcrm_hard_bounce_action_field_callback() {
        $value = get_option('emailit_fluentcrm_hard_bounce_action', 'unsubscribe');
        $webhook = emailit_get_component('webhook');
        $fluentcrm_status = $webhook ? $webhook->get_fluentcrm_integration_status() : array('available' => false);
        
        $options = array(
            'unsubscribe' => __('Unsubscribe (Recommended)', 'emailit-integration'),
            'track' => __('Track Only', 'emailit-integration'),
            'ignore' => __('Ignore', 'emailit-integration')
        );
        
        echo '<select id="emailit_fluentcrm_hard_bounce_action" name="emailit_fluentcrm_hard_bounce_action"' . ($fluentcrm_status['available'] ? '' : ' disabled') . '>';
        foreach ($options as $option_value => $option_label) {
            echo '<option value="' . esc_attr($option_value) . '"' . selected($value, $option_value, false) . '>' . esc_html($option_label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . __('Action to take when FluentCRM detects a hard bounce (permanent failure).', 'emailit-integration') . '</p>';
    }

    public function fluentcrm_soft_bounce_action_field_callback() {
        $value = get_option('emailit_fluentcrm_soft_bounce_action', 'track');
        $webhook = emailit_get_component('webhook');
        $fluentcrm_status = $webhook ? $webhook->get_fluentcrm_integration_status() : array('available' => false);
        
        $options = array(
            'track' => __('Track Only (Recommended)', 'emailit-integration'),
            'unsubscribe' => __('Unsubscribe', 'emailit-integration'),
            'ignore' => __('Ignore', 'emailit-integration')
        );
        
        echo '<select id="emailit_fluentcrm_soft_bounce_action" name="emailit_fluentcrm_soft_bounce_action"' . ($fluentcrm_status['available'] ? '' : ' disabled') . '>';
        foreach ($options as $option_value => $option_label) {
            echo '<option value="' . esc_attr($option_value) . '"' . selected($value, $option_value, false) . '>' . esc_html($option_label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . __('Action to take when FluentCRM detects a soft bounce (temporary failure).', 'emailit-integration') . '</p>';
    }

    public function fluentcrm_complaint_action_field_callback() {
        $value = get_option('emailit_fluentcrm_complaint_action', 'unsubscribe');
        $webhook = emailit_get_component('webhook');
        $fluentcrm_status = $webhook ? $webhook->get_fluentcrm_integration_status() : array('available' => false);
        
        $options = array(
            'unsubscribe' => __('Unsubscribe (Recommended)', 'emailit-integration'),
            'track' => __('Track Only', 'emailit-integration'),
            'ignore' => __('Ignore', 'emailit-integration')
        );
        
        echo '<select id="emailit_fluentcrm_complaint_action" name="emailit_fluentcrm_complaint_action"' . ($fluentcrm_status['available'] ? '' : ' disabled') . '>';
        foreach ($options as $option_value => $option_label) {
            echo '<option value="' . esc_attr($option_value) . '"' . selected($value, $option_value, false) . '>' . esc_html($option_label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . __('Action to take when FluentCRM detects a spam complaint.', 'emailit-integration') . '</p>';
    }

    public function fluentcrm_enable_action_mapping_field_callback() {
        $value = get_option('emailit_fluentcrm_enable_action_mapping', true);
        $webhook = emailit_get_component('webhook');
        $fluentcrm_status = $webhook ? $webhook->get_fluentcrm_integration_status() : array('available' => false);
        
        echo '<input type="checkbox" id="emailit_fluentcrm_enable_action_mapping" name="emailit_fluentcrm_enable_action_mapping" value="1"' . 
             checked(1, $value, false) . ($fluentcrm_status['available'] ? '' : ' disabled') . ' />';
        echo ' <label for="emailit_fluentcrm_enable_action_mapping">' . __('Enable automatic FluentCRM actions based on bounce classifications', 'emailit-integration') . '</label>';
        echo '<p class="description">' . __('Automatically update FluentCRM subscriber status based on Emailit bounce classifications.', 'emailit-integration') . '</p>';
    }

    public function fluentcrm_auto_create_subscribers_field_callback() {
        $value = get_option('emailit_fluentcrm_auto_create_subscribers', true);
        $webhook = emailit_get_component('webhook');
        $fluentcrm_status = $webhook ? $webhook->get_fluentcrm_integration_status() : array('available' => false);
        
        echo '<input type="checkbox" id="emailit_fluentcrm_auto_create_subscribers" name="emailit_fluentcrm_auto_create_subscribers" value="1"' . 
             checked(1, $value, false) . ($fluentcrm_status['available'] ? '' : ' disabled') . ' />';
        echo ' <label for="emailit_fluentcrm_auto_create_subscribers">' . __('Automatically create FluentCRM subscribers for bounced emails', 'emailit-integration') . '</label>';
        echo '<p class="description">' . __('Create new FluentCRM subscribers when processing bounces for unknown email addresses.', 'emailit-integration') . '</p>';
    }

    public function fluentcrm_confidence_threshold_field_callback() {
        $value = get_option('emailit_fluentcrm_confidence_threshold', 70);
        $webhook = emailit_get_component('webhook');
        $fluentcrm_status = $webhook ? $webhook->get_fluentcrm_integration_status() : array('available' => false);
        
        echo '<input type="number" id="emailit_fluentcrm_confidence_threshold" name="emailit_fluentcrm_confidence_threshold" value="' . esc_attr($value) . '" min="0" max="100" class="small-text"' . ($fluentcrm_status['available'] ? '' : ' disabled') . ' />';
        echo ' <label for="emailit_fluentcrm_confidence_threshold">%</label>';
        echo '<p class="description">' . __('Minimum confidence level required to trigger FluentCRM actions (0-100%). Lower confidence bounces will be logged for manual review.', 'emailit-integration') . '</p>';
    }

    public function fluentcrm_soft_bounce_threshold_field_callback() {
        $value = get_option('emailit_fluentcrm_soft_bounce_threshold', 5);
        $webhook = emailit_get_component('webhook');
        $fluentcrm_status = $webhook ? $webhook->get_fluentcrm_integration_status() : array('available' => false);
        
        echo '<input type="number" id="emailit_fluentcrm_soft_bounce_threshold" name="emailit_fluentcrm_soft_bounce_threshold" value="' . esc_attr($value) . '" min="1" max="50" class="small-text"' . ($fluentcrm_status['available'] ? '' : ' disabled') . ' />';
        echo '<p class="description">' . __('Number of soft bounces before escalating to hard bounce (1-50).', 'emailit-integration') . '</p>';
    }

    public function fluentcrm_soft_bounce_window_field_callback() {
        $value = get_option('emailit_fluentcrm_soft_bounce_window', 7);
        $webhook = emailit_get_component('webhook');
        $fluentcrm_status = $webhook ? $webhook->get_fluentcrm_integration_status() : array('available' => false);
        
        echo '<input type="number" id="emailit_fluentcrm_soft_bounce_window" name="emailit_fluentcrm_soft_bounce_window" value="' . esc_attr($value) . '" min="1" max="30" class="small-text"' . ($fluentcrm_status['available'] ? '' : ' disabled') . ' />';
        echo '<p class="description">' . __('Time window in days for counting soft bounces (1-30).', 'emailit-integration') . '</p>';
    }

    public function fluentcrm_soft_bounce_reset_on_success_field_callback() {
        $value = get_option('emailit_fluentcrm_soft_bounce_reset_on_success', true);
        $webhook = emailit_get_component('webhook');
        $fluentcrm_status = $webhook ? $webhook->get_fluentcrm_integration_status() : array('available' => false);
        
        echo '<input type="checkbox" id="emailit_fluentcrm_soft_bounce_reset_on_success" name="emailit_fluentcrm_soft_bounce_reset_on_success" value="1"' . 
             checked(1, $value, false) . ($fluentcrm_status['available'] ? '' : ' disabled') . ' />';
        echo ' <label for="emailit_fluentcrm_soft_bounce_reset_on_success">' . __('Reset soft bounce count when email is successfully delivered', 'emailit-integration') . '</label>';
        echo '<p class="description">' . __('Automatically reset the soft bounce counter when a successful delivery is detected.', 'emailit-integration') . '</p>';
    }

    public function fluentcrm_soft_bounce_history_limit_field_callback() {
        $value = get_option('emailit_fluentcrm_soft_bounce_history_limit', 10);
        $webhook = emailit_get_component('webhook');
        $fluentcrm_status = $webhook ? $webhook->get_fluentcrm_integration_status() : array('available' => false);
        
        echo '<input type="number" id="emailit_fluentcrm_soft_bounce_history_limit" name="emailit_fluentcrm_soft_bounce_history_limit" value="' . esc_attr($value) . '" min="5" max="50" class="small-text"' . ($fluentcrm_status['available'] ? '' : ' disabled') . ' />';
        echo '<p class="description">' . __('Maximum number of bounce records to keep in history per subscriber (5-50).', 'emailit-integration') . '</p>';
    }

    /**
     * Error handling field callbacks
     */
    public function error_analytics_enabled_field_callback() {
        $value = get_option('emailit_error_analytics_enabled', true);
        
        echo '<input type="checkbox" id="emailit_error_analytics_enabled" name="emailit_error_analytics_enabled" value="1"' . checked(1, $value, false) . ' />';
        echo ' <label for="emailit_error_analytics_enabled">' . __('Enable error analytics and pattern detection', 'emailit-integration') . '</label>';
        echo '<p class="description">' . __('Track error patterns, trends, and correlations to help identify and prevent issues.', 'emailit-integration') . '</p>';
    }

    public function retry_enabled_field_callback() {
        $value = get_option('emailit_retry_enabled', true);
        
        echo '<input type="checkbox" id="emailit_retry_enabled" name="emailit_retry_enabled" value="1"' . checked(1, $value, false) . ' />';
        echo ' <label for="emailit_retry_enabled">' . __('Enable automatic retry for failed operations', 'emailit-integration') . '</label>';
        echo '<p class="description">' . __('Automatically retry failed API calls with exponential backoff and intelligent retry strategies.', 'emailit-integration') . '</p>';
    }

    public function error_notifications_enabled_field_callback() {
        $value = get_option('emailit_error_notifications_enabled', true);
        
        echo '<input type="checkbox" id="emailit_error_notifications_enabled" name="emailit_error_notifications_enabled" value="1"' . checked(1, $value, false) . ' />';
        echo ' <label for="emailit_error_notifications_enabled">' . __('Enable error notifications and alerts', 'emailit-integration') . '</label>';
        echo '<p class="description">' . __('Send email notifications and admin alerts for critical errors and system issues.', 'emailit-integration') . '</p>';
    }

    public function circuit_breaker_enabled_field_callback() {
        $value = get_option('emailit_circuit_breaker_enabled', true);
        
        echo '<input type="checkbox" id="emailit_circuit_breaker_enabled" name="emailit_circuit_breaker_enabled" value="1"' . checked(1, $value, false) . ' />';
        echo ' <label for="emailit_circuit_breaker_enabled">' . __('Enable circuit breaker protection', 'emailit-integration') . '</label>';
        echo '<p class="description">' . __('Automatically disable API calls when too many failures occur to prevent cascading failures.', 'emailit-integration') . '</p>';
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
            $subject = sprintf(__('🔧 WordPress Test Email via Emailit - %s', 'emailit-integration'), $site_name);
            $current_time = current_time('F j, Y g:i A T');

            $message = $this->get_wordpress_test_email_template($site_name, $site_url, $current_time);

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
     * AJAX handler for cleaning old logs
     */
    public function ajax_clean_old_logs() {
        check_ajax_referer('emailit_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'emailit-integration'));
        }

        try {
            $db_optimizer = emailit_get_component('db_optimizer');
            if (!$db_optimizer) {
                wp_send_json_error(array(
                    'message' => __('Database optimizer not available.', 'emailit-integration')
                ));
            }

            $cleaned = $db_optimizer->cleanup_orphaned_records();
            $message = sprintf(__('Cleaned up %d orphaned records.', 'emailit-integration'), count($cleaned));
            
            wp_send_json_success(array('message' => $message));
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => sprintf(__('Error cleaning logs: %s', 'emailit-integration'), $e->getMessage())
            ));
        }
    }

    /**
     * AJAX handler for optimizing database
     */
    public function ajax_optimize_database() {
        check_ajax_referer('emailit_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'emailit-integration'));
        }

        try {
            $db_optimizer = emailit_get_component('db_optimizer');
            if (!$db_optimizer) {
                wp_send_json_error(array(
                    'message' => __('Database optimizer not available.', 'emailit-integration')
                ));
            }

            $results = $db_optimizer->optimize_tables();
            $message = __('Database tables optimized successfully.', 'emailit-integration');
            
            wp_send_json_success(array('message' => $message));
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => sprintf(__('Error optimizing database: %s', 'emailit-integration'), $e->getMessage())
            ));
        }
    }

    /**
     * AJAX handler for clearing cache
     */
    public function ajax_clear_cache() {
        check_ajax_referer('emailit_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'emailit-integration'));
        }

        try {
            $query_optimizer = emailit_get_component('query_optimizer');
            if ($query_optimizer) {
                $query_optimizer->clear_cache();
            }

            // Clear WordPress transients
            delete_transient('emailit_api_key_validation');
            delete_transient('emailit_queue_stats');
            delete_transient('emailit_performance_stats');
            
            wp_send_json_success(array('message' => __('Cache cleared successfully.', 'emailit-integration')));
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => sprintf(__('Error clearing cache: %s', 'emailit-integration'), $e->getMessage())
            ));
        }
    }

    /**
     * AJAX handler for getting soft bounce statistics
     */
    public function ajax_get_soft_bounce_stats() {
        check_ajax_referer('emailit_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'emailit-integration'));
        }

        try {
            $fluentcrm_handler = emailit_get_component('fluentcrm_handler');
            if (!$fluentcrm_handler || !$fluentcrm_handler->is_available()) {
                wp_send_json_error(array('message' => __('FluentCRM handler not available.', 'emailit-integration')));
            }

            $stats = $fluentcrm_handler->get_soft_bounce_statistics();
            wp_send_json_success($stats);

        } catch (Exception $e) {
            wp_send_json_error(array('message' => sprintf(__('Error getting soft bounce stats: %s', 'emailit-integration'), $e->getMessage())));
        }
    }

    /**
     * AJAX handler for resetting subscriber bounce count
     */
    public function ajax_reset_subscriber_bounce() {
        check_ajax_referer('emailit_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'emailit-integration'));
        }

        $subscriber_id = intval($_POST['subscriber_id'] ?? 0);
        if (!$subscriber_id) {
            wp_send_json_error(array('message' => __('Invalid subscriber ID.', 'emailit-integration')));
        }

        try {
            $fluentcrm_handler = emailit_get_component('fluentcrm_handler');
            if (!$fluentcrm_handler || !$fluentcrm_handler->is_available()) {
                wp_send_json_error(array('message' => __('FluentCRM handler not available.', 'emailit-integration')));
            }

            $success = $fluentcrm_handler->reset_subscriber_bounce_count($subscriber_id);
            if ($success) {
                wp_send_json_success(array('message' => __('Subscriber bounce count reset successfully.', 'emailit-integration')));
            } else {
                wp_send_json_error(array('message' => __('Failed to reset subscriber bounce count.', 'emailit-integration')));
            }

        } catch (Exception $e) {
            wp_send_json_error(array('message' => sprintf(__('Error resetting bounce count: %s', 'emailit-integration'), $e->getMessage())));
        }
    }

    /**
     * AJAX handler for getting subscriber bounce details
     */
    public function ajax_get_subscriber_bounce_details() {
        check_ajax_referer('emailit_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'emailit-integration'));
        }

        $subscriber_id = intval($_POST['subscriber_id'] ?? 0);
        if (!$subscriber_id) {
            wp_send_json_error(array('message' => __('Invalid subscriber ID.', 'emailit-integration')));
        }

        try {
            $fluentcrm_handler = emailit_get_component('fluentcrm_handler');
            if (!$fluentcrm_handler || !$fluentcrm_handler->is_available()) {
                wp_send_json_error(array('message' => __('FluentCRM handler not available.', 'emailit-integration')));
            }

            $details = $fluentcrm_handler->get_subscriber_bounce_details($subscriber_id);
            if ($details) {
                wp_send_json_success($details);
            } else {
                wp_send_json_error(array('message' => __('Subscriber not found or no bounce data available.', 'emailit-integration')));
            }

        } catch (Exception $e) {
            wp_send_json_error(array('message' => sprintf(__('Error getting subscriber details: %s', 'emailit-integration'), $e->getMessage())));
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

    /**
     * Get HTML template for WordPress test emails
     */
    private function get_wordpress_test_email_template($site_name, $site_url, $current_time) {
        return '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . esc_html__('WordPress Test Email - Emailit Integration', 'emailit-integration') . '</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; margin: 0; padding: 0; background-color: #f8f9fa;">
    <table cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #f8f9fa; padding: 10px 0;">
        <tr>
            <td align="center">
                <table cellpadding="0" cellspacing="0" border="0" width="600" style="background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background: #d63384; padding: 24px 20px; text-align: center; border-radius: 8px 8px 0 0;">
                            <h1 style="color: #ffffff; margin: 0 0 8px 0; font-size: 24px; font-weight: 600;">🔧 ' . esc_html__('WordPress wp_mail() Test', 'emailit-integration') . '</h1>
                            <p style="color: #ffeef1; margin: 0; font-size: 14px;">' . esc_html__('Testing email interception and processing', 'emailit-integration') . '</p>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 24px 20px;">
                            <!-- Diagnostic Badge -->
                            <table cellpadding="0" cellspacing="0" border="0" width="100%" style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 6px; margin: 0 0 16px 0;">
                                <tr>
                                    <td style="padding: 16px; text-align: center;">
                                        <div style="font-size: 32px; margin-bottom: 8px; color: #fd7e14;">🔍</div>
                                        <h2 style="color: #856404; margin: 0 0 8px 0; font-size: 18px;">' . esc_html__('Diagnostic Test Complete', 'emailit-integration') . '</h2>
                                        <p style="color: #856404; margin: 0; font-size: 14px;">' . esc_html__('This email was sent through WordPress wp_mail() function', 'emailit-integration') . '</p>
                                    </td>
                                </tr>
                            </table>

                            <p style="color: #495057; font-size: 14px; line-height: 1.5; margin: 0 0 16px 0;">' . sprintf(__('This test email was sent from <strong>%s</strong> using the standard WordPress wp_mail() function. If you received this email, it means the Emailit plugin successfully intercepted the wp_mail() call and routed it through the Emailit API.', 'emailit-integration'), esc_html($site_name)) . '</p>

                            <!-- Technical Note -->
                            <table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin: 0 0 16px 0;">
                                <tr>
                                    <td style="background: #e3f2fd; border-left: 4px solid #2196f3; padding: 12px; border-radius: 0 4px 4px 0;">
                                        <h4 style="color: #1976d2; margin: 0 0 8px 0; font-size: 14px;">📋 ' . esc_html__('Technical Details', 'emailit-integration') . '</h4>
                                        <p style="color: #1565c0; margin: 0; font-size: 13px; line-height: 1.4;">' . esc_html__('This test helps verify that the plugin properly hooks into WordPress\'s email system. The wp_mail() function is used by contact forms, user registration, password resets, and other WordPress features.', 'emailit-integration') . '</p>
                                    </td>
                                </tr>
                            </table>

                            <!-- Info Card -->
                            <table cellpadding="0" cellspacing="0" border="0" width="100%" style="background: #f8f9fa; border-radius: 6px; margin: 0 0 16px 0;">
                                <tr>
                                    <td style="padding: 16px;">
                                        <h3 style="margin: 0 0 12px 0; color: #495057; font-size: 16px;">' . esc_html__('Test Information', 'emailit-integration') . '</h3>

                                        <table cellpadding="0" cellspacing="0" border="0" width="100%">
                                            <tr>
                                                <td style="padding: 6px 0; border-bottom: 1px solid #e9ecef;">
                                                    <strong style="color: #495057; font-size: 13px;">' . esc_html__('Website:', 'emailit-integration') . '</strong>
                                                </td>
                                                <td style="padding: 6px 0; border-bottom: 1px solid #e9ecef; text-align: right; color: #6c757d; font-family: monospace; font-size: 13px;">
                                                    ' . esc_html($site_name) . '
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 6px 0; border-bottom: 1px solid #e9ecef;">
                                                    <strong style="color: #495057; font-size: 13px;">' . esc_html__('URL:', 'emailit-integration') . '</strong>
                                                </td>
                                                <td style="padding: 6px 0; border-bottom: 1px solid #e9ecef; text-align: right; color: #6c757d; font-family: monospace; font-size: 13px;">
                                                    ' . esc_html($site_url) . '
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 6px 0; border-bottom: 1px solid #e9ecef;">
                                                    <strong style="color: #495057; font-size: 13px;">' . esc_html__('Sent At:', 'emailit-integration') . '</strong>
                                                </td>
                                                <td style="padding: 6px 0; border-bottom: 1px solid #e9ecef; text-align: right; color: #6c757d; font-family: monospace; font-size: 13px;">
                                                    ' . esc_html($current_time) . '
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 6px 0; border-bottom: 1px solid #e9ecef;">
                                                    <strong style="color: #495057; font-size: 13px;">' . esc_html__('Plugin Version:', 'emailit-integration') . '</strong>
                                                </td>
                                                <td style="padding: 6px 0; border-bottom: 1px solid #e9ecef; text-align: right; color: #6c757d; font-family: monospace; font-size: 13px;">
                                                    v' . esc_html(EMAILIT_VERSION) . '
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 6px 0; border-bottom: 1px solid #e9ecef;">
                                                    <strong style="color: #495057; font-size: 13px;">' . esc_html__('Method:', 'emailit-integration') . '</strong>
                                                </td>
                                                <td style="padding: 6px 0; border-bottom: 1px solid #e9ecef; text-align: right; color: #6c757d; font-family: monospace; font-size: 13px;">
                                                    wp_mail() → Emailit API
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 6px 0;">
                                                    <strong style="color: #495057; font-size: 13px;">' . esc_html__('Integration Status:', 'emailit-integration') . '</strong>
                                                </td>
                                                <td style="padding: 6px 0; text-align: right; color: #28a745; font-family: monospace; font-size: 13px;">
                                                    ✅ Working
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <p style="color: #495057; font-size: 14px; line-height: 1.5; margin: 0 0 16px 0;">🎯 ' . esc_html__('Perfect! Your WordPress site is properly integrated with Emailit. All plugins and features that send emails through wp_mail() will now be delivered via the Emailit service, providing better deliverability and tracking capabilities.', 'emailit-integration') . '</p>

                            <!-- CTA Button -->
                            <table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin: 0;">
                                <tr>
                                    <td align="center">
                                        <a href="' . esc_url(admin_url('tools.php?page=emailit-logs')) . '" style="display: inline-block; background: #d63384; color: #ffffff; padding: 10px 20px; border-radius: 4px; text-decoration: none; font-weight: 600; font-size: 14px;">' . esc_html__('View Emailit Log', 'emailit-integration') . '</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background: #f8f9fa; padding: 16px; text-align: center; color: #6c757d; font-size: 12px; border-radius: 0 0 8px 8px;">
                            <p style="margin: 0;">' . sprintf(
                                __('This diagnostic email was sent by the %s plugin. %s', 'emailit-integration'),
                                '<strong>Emailit Integration</strong>',
                                '<a href="https://emailit.com/docs" target="_blank" style="color: #007cba; text-decoration: none;">View Documentation</a>'
                            ) . '</p>
                            <p style="margin: 8px 0 0 0; font-size: 11px; color: #adb5bd;">' . esc_html__('This is an automated test email. Please do not reply.', 'emailit-integration') . '</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }

    /**
     * AJAX handler for health check
     */
    public function ajax_health_check() {
        check_ajax_referer('emailit_health_check', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'emailit-integration'));
        }

        try {
            $health_monitor = new Emailit_Health_Monitor($this->logger);
            $result = $health_monitor->trigger_manual_check(new WP_REST_Request('POST', '/emailit/v1/health/check'));
            
            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()));
            } else {
                wp_send_json_success(array('message' => __('Health check completed successfully.', 'emailit-integration')));
            }
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * AJAX handler for cleaning up health data
     */
    public function ajax_cleanup_health_data() {
        check_ajax_referer('emailit_cleanup_health_data', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'emailit-integration'));
        }

        try {
            Emailit_Health_Migration::cleanup_old_data();
            wp_send_json_success(array('message' => __('Old health data cleaned up successfully.', 'emailit-integration')));
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * AJAX handler for optimizing health tables
     */
    public function ajax_optimize_health_tables() {
        check_ajax_referer('emailit_optimize_health_tables', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'emailit-integration'));
        }

        try {
            Emailit_Health_Migration::optimize_tables();
            wp_send_json_success(array('message' => __('Health monitoring tables optimized successfully.', 'emailit-integration')));
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
}