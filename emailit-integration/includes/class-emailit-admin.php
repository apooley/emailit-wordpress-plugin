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
        
        
        // Monitor FluentCRM forward bounces setting changes
        add_action('update_option_emailit_fluentcrm_forward_bounces', array($this, 'handle_forward_bounces_setting_change'), 10, 2);
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
            add_action('wp_ajax_emailit_reset_fluentcrm_settings', array($this, 'ajax_reset_fluentcrm_settings'));
            add_action('wp_ajax_emailit_auto_select_bounce_handler', array($this, 'ajax_auto_select_bounce_handler'));
        }

        // Health monitoring AJAX handlers
        add_action('wp_ajax_emailit_health_check', array($this, 'ajax_health_check'));
        add_action('wp_ajax_emailit_cleanup_health_data', array($this, 'ajax_cleanup_health_data'));
        add_action('wp_ajax_emailit_optimize_health_tables', array($this, 'ajax_optimize_health_tables'));
        add_action('wp_ajax_emailit_get_health_metrics', array($this, 'ajax_get_health_metrics'));

        // Webhook monitoring AJAX handlers
        add_action('wp_ajax_emailit_dismiss_webhook_alert', array($this, 'ajax_dismiss_webhook_alert'));
        add_action('wp_ajax_emailit_get_webhook_details', array($this, 'ajax_get_webhook_details'));
        add_action('wp_ajax_emailit_clear_webhook_logs', array($this, 'ajax_clear_webhook_logs'));
        add_action('wp_ajax_emailit_get_missing_webhooks', array($this, 'ajax_get_missing_webhooks'));

        // New simplified interface AJAX handlers
        add_action('wp_ajax_emailit_get_recent_activity', array($this, 'ajax_get_recent_activity'));
        add_action('wp_ajax_emailit_get_queue_status', array($this, 'ajax_get_queue_status'));
        add_action('wp_ajax_emailit_get_quick_stats', array($this, 'ajax_get_quick_stats'));
        add_action('wp_ajax_emailit_get_webhook_status', array($this, 'ajax_get_webhook_status'));
        add_action('wp_ajax_emailit_get_recent_logs', array($this, 'ajax_get_recent_logs'));
        add_action('wp_ajax_emailit_get_health_status', array($this, 'ajax_get_health_status'));
        add_action('wp_ajax_emailit_toggle_power_user_mode', array($this, 'ajax_toggle_power_user_mode'));
        add_action('wp_ajax_emailit_clear_api_cache', array($this, 'ajax_clear_api_cache'));
        add_action('wp_ajax_emailit_clear_api_key', array($this, 'ajax_clear_api_key'));

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

        // Webhook Logs moved to Webhooks tab in settings

        // Health Monitor removed - functionality integrated into settings page

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


        // Advanced Settings (separate settings group)
        register_setting('emailit-advanced-settings', 'emailit_fallback_enabled', array(
            'type' => 'boolean',
            'default' => 1
        ));

        register_setting('emailit-advanced-settings', 'emailit_retry_attempts', array(
            'type' => 'integer',
            'sanitize_callback' => array($this, 'sanitize_retry_attempts'),
            'default' => 3
        ));

        register_setting('emailit-advanced-settings', 'emailit_timeout', array(
            'type' => 'integer',
            'sanitize_callback' => array($this, 'sanitize_timeout'),
            'default' => 30
        ));

        register_setting('emailit-advanced-settings', 'emailit_webhook_secret', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => wp_generate_password(32, false)
        ));

        register_setting('emailit-advanced-settings', 'emailit_enable_webhooks', array(
            'type' => 'boolean',
            'default' => 1
        ));

        // Queue/Async settings
        register_setting('emailit-advanced-settings', 'emailit_enable_queue', array(
            'type' => 'boolean',
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
            'default' => 0
        ));

        register_setting('emailit-advanced-settings', 'emailit_queue_batch_size', array(
            'type' => 'integer',
            'sanitize_callback' => array($this, 'sanitize_queue_batch_size'),
            'default' => 10
        ));

        register_setting('emailit-advanced-settings', 'emailit_queue_max_retries', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 3
        ));

        // FluentCRM Integration settings (separate settings group)
        // Note: We register these settings even if FluentCRM is not available yet
        // The field callbacks will handle the availability check
            register_setting('emailit-fluentcrm-settings', 'emailit_fluentcrm_integration', array(
                'type' => 'boolean',
                'sanitize_callback' => array($this, 'sanitize_checkbox'),
                'default' => 1
            ));

            register_setting('emailit-fluentcrm-settings', 'emailit_fluentcrm_forward_bounces', array(
                'type' => 'boolean',
                'sanitize_callback' => array($this, 'sanitize_checkbox'),
                'default' => 1
            ));

            register_setting('emailit-fluentcrm-settings', 'emailit_fluentcrm_suppress_default', array(
                'type' => 'boolean',
                'sanitize_callback' => array($this, 'sanitize_checkbox'),
                'default' => 0
            ));

            register_setting('emailit-fluentcrm-settings', 'emailit_fluentcrm_hard_bounce_action', array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'unsubscribe'
            ));

            register_setting('emailit-fluentcrm-settings', 'emailit_fluentcrm_soft_bounce_action', array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'track'
            ));

            register_setting('emailit-fluentcrm-settings', 'emailit_fluentcrm_soft_bounce_threshold', array(
                'type' => 'integer',
                'sanitize_callback' => 'absint',
                'default' => 5
            ));

            register_setting('emailit-fluentcrm-settings', 'emailit_fluentcrm_complaint_action', array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'unsubscribe'
            ));

            // Action mapping settings
            register_setting('emailit-fluentcrm-settings', 'emailit_fluentcrm_enable_action_mapping', array(
                'type' => 'boolean',
                'sanitize_callback' => array($this, 'sanitize_checkbox'),
                'default' => 1
            ));

            register_setting('emailit-fluentcrm-settings', 'emailit_fluentcrm_auto_create_subscribers', array(
                'type' => 'boolean',
                'sanitize_callback' => array($this, 'sanitize_checkbox'),
                'default' => 1
            ));

            register_setting('emailit-fluentcrm-settings', 'emailit_fluentcrm_confidence_threshold', array(
                'type' => 'integer',
                'sanitize_callback' => 'absint',
                'default' => 70
            ));

            // Soft bounce threshold management settings (removed duplicate)
            register_setting('emailit-fluentcrm-settings', 'emailit_fluentcrm_soft_bounce_window', array(
                'type' => 'integer',
                'sanitize_callback' => 'absint',
                'default' => 7
            ));

            register_setting('emailit-fluentcrm-settings', 'emailit_fluentcrm_soft_bounce_reset_on_success', array(
                'type' => 'boolean',
                'sanitize_callback' => array($this, 'sanitize_checkbox'),
                'default' => 1
            ));

            register_setting('emailit-fluentcrm-settings', 'emailit_fluentcrm_soft_bounce_history_limit', array(
                'type' => 'integer',
                'sanitize_callback' => 'absint',
                'default' => 10
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


        // Advanced settings section (separate settings group)
        add_settings_section(
            'emailit_advanced_section',
            __('Advanced Settings', 'emailit-integration'),
            array($this, 'advanced_section_callback'),
            'emailit-advanced-settings'
        );

        add_settings_section(
            'emailit_performance_section',
            __('Performance & Queue Settings', 'emailit-integration'),
            array($this, 'performance_section_callback'),
            'emailit-advanced-settings'
        );

        add_settings_section(
            'emailit_webhook_section',
            __('Webhook Settings', 'emailit-integration'),
            array($this, 'webhook_section_callback'),
            'emailit-advanced-settings'
        );

        // Always add FluentCRM section (separate settings group)
        add_settings_section(
            'emailit_fluentcrm_section',
            __('FluentCRM Integration', 'emailit-integration'),
            array($this, 'fluentcrm_section_callback'),
            'emailit-fluentcrm-settings'
        );

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
            'emailit-advanced-settings',
            'emailit_performance_section'
        );

        add_settings_field(
            'emailit_queue_batch_size',
            __('Queue Batch Size', 'emailit-integration'),
            array($this, 'queue_batch_size_field_callback'),
            'emailit-advanced-settings',
            'emailit_performance_section'
        );

        add_settings_field(
            'emailit_queue_max_retries',
            __('Queue Max Retries', 'emailit-integration'),
            array($this, 'queue_max_retries_field_callback'),
            'emailit-advanced-settings',
            'emailit_performance_section'
        );

        // Advanced fields
        add_settings_field(
            'emailit_fallback_enabled',
            __('Enable Fallback', 'emailit-integration'),
            array($this, 'fallback_enabled_field_callback'),
            'emailit-advanced-settings',
            'emailit_advanced_section'
        );

        add_settings_field(
            'emailit_retry_attempts',
            __('Retry Attempts', 'emailit-integration'),
            array($this, 'retry_attempts_field_callback'),
            'emailit-advanced-settings',
            'emailit_advanced_section'
        );

        add_settings_field(
            'emailit_timeout',
            __('Timeout (Seconds)', 'emailit-integration'),
            array($this, 'timeout_field_callback'),
            'emailit-advanced-settings',
            'emailit_advanced_section'
        );

        add_settings_field(
            'emailit_enable_webhooks',
            __('Enable Webhooks', 'emailit-integration'),
            array($this, 'enable_webhooks_field_callback'),
            'emailit-advanced-settings',
            'emailit_webhook_section'
        );

        add_settings_field(
            'emailit_webhook_secret',
            __('Webhook Secret', 'emailit-integration'),
            array($this, 'webhook_secret_field_callback'),
            'emailit-advanced-settings',
            'emailit_webhook_section'
        );

        // FluentCRM Integration fields (always add, check availability in field callbacks)
        // Note: We add these fields even if FluentCRM is not available yet
        // The field callbacks will handle the availability check and disable fields if needed
            add_settings_field(
                'emailit_fluentcrm_integration',
                __('Enable FluentCRM Integration', 'emailit-integration'),
                array($this, 'fluentcrm_integration_field_callback'),
                'emailit-fluentcrm-settings',
                'emailit_fluentcrm_section'
            );

            add_settings_field(
                'emailit_fluentcrm_forward_bounces',
                __('Forward Bounces to Emailit', 'emailit-integration'),
                array($this, 'fluentcrm_forward_bounces_field_callback'),
                'emailit-fluentcrm-settings',
                'emailit_fluentcrm_section'
            );

            add_settings_field(
                'emailit_fluentcrm_suppress_default',
                __('Suppress Default WordPress Emails', 'emailit-integration'),
                array($this, 'fluentcrm_suppress_default_field_callback'),
                'emailit-fluentcrm-settings',
                'emailit_fluentcrm_section'
            );

            add_settings_field(
                'emailit_fluentcrm_hard_bounce_action',
                __('Hard Bounce Action', 'emailit-integration'),
                array($this, 'fluentcrm_hard_bounce_action_field_callback'),
                'emailit-fluentcrm-settings',
                'emailit_fluentcrm_section'
            );

            add_settings_field(
                'emailit_fluentcrm_soft_bounce_action',
                __('Soft Bounce Action', 'emailit-integration'),
                array($this, 'fluentcrm_soft_bounce_action_field_callback'),
                'emailit-fluentcrm-settings',
                'emailit_fluentcrm_section'
            );

            add_settings_field(
                'emailit_fluentcrm_soft_bounce_threshold',
                __('Soft Bounce Threshold', 'emailit-integration'),
                array($this, 'fluentcrm_soft_bounce_threshold_field_callback'),
                'emailit-fluentcrm-settings',
                'emailit_fluentcrm_section'
            );

            add_settings_field(
                'emailit_fluentcrm_complaint_action',
                __('Complaint Action', 'emailit-integration'),
                array($this, 'fluentcrm_complaint_action_field_callback'),
                'emailit-fluentcrm-settings',
                'emailit_fluentcrm_section'
            );

            // Action mapping fields
            add_settings_field(
                'emailit_fluentcrm_enable_action_mapping',
                __('Enable Action Mapping', 'emailit-integration'),
                array($this, 'fluentcrm_enable_action_mapping_field_callback'),
                'emailit-fluentcrm-settings',
                'emailit_fluentcrm_section'
            );

            add_settings_field(
                'emailit_fluentcrm_auto_create_subscribers',
                __('Auto-Create Subscribers', 'emailit-integration'),
                array($this, 'fluentcrm_auto_create_subscribers_field_callback'),
                'emailit-fluentcrm-settings',
                'emailit_fluentcrm_section'
            );

            add_settings_field(
                'emailit_fluentcrm_confidence_threshold',
                __('Confidence Threshold', 'emailit-integration'),
                array($this, 'fluentcrm_confidence_threshold_field_callback'),
                'emailit-fluentcrm-settings',
                'emailit_fluentcrm_section'
            );

            // Soft bounce threshold management fields

            add_settings_field(
                'emailit_fluentcrm_soft_bounce_window',
                __('Soft Bounce Window (Days)', 'emailit-integration'),
                array($this, 'fluentcrm_soft_bounce_window_field_callback'),
                'emailit-fluentcrm-settings',
                'emailit_fluentcrm_section'
            );

            add_settings_field(
                'emailit_fluentcrm_soft_bounce_reset_on_success',
                __('Reset on Successful Delivery', 'emailit-integration'),
                array($this, 'fluentcrm_soft_bounce_reset_on_success_field_callback'),
                'emailit-fluentcrm-settings',
                'emailit_fluentcrm_section'
            );

            add_settings_field(
                'emailit_fluentcrm_soft_bounce_history_limit',
                __('Bounce History Limit', 'emailit-integration'),
                array($this, 'fluentcrm_soft_bounce_history_limit_field_callback'),
                'emailit-fluentcrm-settings',
                'emailit_fluentcrm_section'
            );

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
        // Make admin instance available to the template
        $admin = $this;
        include EMAILIT_PLUGIN_DIR . 'admin/views/settings.php';
    }

    /**
     * Logs page callback
     */
    public function logs_page_callback() {
        include EMAILIT_PLUGIN_DIR . 'admin/views/logs.php';
    }

    /**
     * Webhook logs page callback
     */
    // Webhook Logs page callback removed - now integrated into Webhooks tab

    // Health Monitor page callback removed - functionality integrated into settings page

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
            
            // Show bounce handler information only if integration is enabled
            if (get_option('emailit_fluentcrm_integration', 1)) {
                $bounce_handler_url = $this->get_fluentcrm_bounce_handler_url();
                if ($bounce_handler_url) {
                    // Check if Emailit is currently selected as bounce handler
                    $fluentcrm_settings = get_option('fluentcrm-global-settings', array());
                    $current_provider = isset($fluentcrm_settings['bounce_handler_provider']) ? $fluentcrm_settings['bounce_handler_provider'] : '';
                    $is_emailit_selected = ($current_provider === 'emailit');
                    
                    echo '<div class="notice notice-info inline"><p>';
                    echo '<strong>' . __('Bounce Handler Available', 'emailit-integration') . '</strong><br>';
                    echo __('Your Emailit bounce handler is now available in FluentCRM settings. ', 'emailit-integration');
                    echo '<a href="' . admin_url('admin.php?page=fluentcrm-admin#/settings/smtp_settings') . '" target="_blank">' . __('Go to FluentCRM Bounce Handler Settings', 'emailit-integration') . '</a>';
                    echo '</p></div>';
                    
                    // Show auto-selection status
                    if ($is_emailit_selected) {
                        echo '<div class="notice notice-success inline"><p>';
                        echo '<strong>' . __('✓ Emailit Auto-Selected', 'emailit-integration') . '</strong><br>';
                        echo __('Emailit has been automatically selected as the bounce handler in FluentCRM because "Forward bounce data to Emailit API" is enabled.', 'emailit-integration');
                        echo '</p></div>';
                    } else {
                        echo '<div class="notice notice-warning inline"><p>';
                        echo '<strong>' . __('⚠ Emailit Not Selected', 'emailit-integration') . '</strong><br>';
                        echo __('Emailit is not currently selected as the bounce handler in FluentCRM. ', 'emailit-integration');
                        echo __('It will be automatically selected when you enable "Forward bounce data to Emailit API" above.', 'emailit-integration');
                        echo '</p></div>';
                    }
                    
                    // Add auto-select button
                    echo '<div class="notice notice-success inline"><p>';
                    echo '<strong>' . __('Auto-Select Emailit', 'emailit-integration') . '</strong><br>';
                    echo __('Click the button below to automatically select Emailit as the bounce handler in FluentCRM.', 'emailit-integration');
                    echo '<br><br>';
                    echo '<button type="button" class="button button-primary" id="emailit-auto-select-bounce-handler">';
                    echo __('Auto-Select Emailit as Bounce Handler', 'emailit-integration');
                    echo '</button>';
                    echo '<span id="emailit-auto-select-status" style="margin-left: 10px;"></span>';
                    echo '</p></div>';
                    
                    // Add JavaScript for the auto-select button
                    echo '<script type="text/javascript">
                    jQuery(document).ready(function($) {
                        $("#emailit-auto-select-bounce-handler").click(function() {
                            var button = $(this);
                            var status = $("#emailit-auto-select-status");
                            
                            button.prop("disabled", true);
                            status.html("Processing...");
                            
                            $.post(ajaxurl, {
                                action: "emailit_auto_select_bounce_handler",
                                nonce: "' . wp_create_nonce('emailit_admin_nonce') . '"
                            }, function(response) {
                                if (response.success) {
                                    status.html("<span style=\"color: green;\">✓ " + response.message + "</span>");
                                } else {
                                    status.html("<span style=\"color: red;\">✗ " + response.message + "</span>");
                                }
                                button.prop("disabled", false);
                            });
                        });
                    });
                    </script>';
                } else {
                    echo '<div class="notice notice-warning inline"><p>';
                    echo '<strong>' . __('Bounce Handler URL Not Generated', 'emailit-integration') . '</strong><br>';
                    echo __('The bounce handler URL could not be generated. This might be due to missing webhook settings.', 'emailit-integration');
                    echo '</p></div>';
                }
            } else {
                echo '<div class="notice notice-warning inline"><p>';
                echo '<strong>' . __('FluentCRM Integration Disabled', 'emailit-integration') . '</strong><br>';
                echo __('Enable FluentCRM integration above to access bounce handler features.', 'emailit-integration');
                echo '</p></div>';
            }
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
                // Don't pass the raw encrypted key - let validate_api_key use the instance's decrypted key
                $validation_result = $this->api->validate_api_key();
                $is_valid = !is_wp_error($validation_result);
            } catch (Exception $e) {
                // Ignore validation errors in admin
                $is_valid = false;
            }
        }

        echo '<div class="emailit-api-key-container">';
        
        if ($has_key) {
            // Show status and clear button when key exists
            echo '<div class="emailit-existing-key-info">';
            echo '<span class="dashicons dashicons-lock"></span> ';
            echo '<strong>API Key is configured</strong>';
            if ($is_valid) {
                echo ' <span class="emailit-status delivered">✓ Valid</span>';
            } else {
                echo ' <span class="emailit-status failed">✗ Invalid</span>';
            }
            echo '</div>';
            
            echo '<div class="emailit-api-key-actions">';
            echo '<button type="button" id="clear-api-key" class="button button-secondary">';
            echo '<span class="dashicons dashicons-trash"></span> Clear API Key';
            echo '</button>';
            echo '<button type="button" id="replace-api-key" class="button button-primary" style="margin-left: 10px;">';
            echo '<span class="dashicons dashicons-edit"></span> Replace API Key';
            echo '</button>';
            echo '</div>';
            
            // Hidden field for form submission (empty to keep existing key)
            echo '<input type="hidden" id="emailit_api_key" name="emailit_api_key" value="" />';
            
        } else {
            // Show input field when no key exists
            echo '<input type="text" id="emailit_api_key" name="emailit_api_key" value="" placeholder="Enter your Emailit API key" class="regular-text" data-has-key="0" />';
        }
        
        echo '</div>';
        
        if ($has_key) {
            echo '<p class="description">' . __('API key is set and encrypted. Use the buttons above to clear or replace it.', 'emailit-integration') . '</p>';
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
        
        // Ensure value is within valid range
        $value = max(1, min(50, intval($value)));
        
        echo '<input type="number" id="emailit_queue_batch_size" name="emailit_queue_batch_size" value="' . esc_attr($value) . '" min="1" max="50" step="1" required class="small-text" />';
        echo '<p class="description">' . __('Number of emails to process in each batch (1-50). Lower values use less resources.', 'emailit-integration') . '</p>';
        
        // Add CSS and JavaScript validation
        echo '<style>
        #emailit_queue_batch_size.error {
            border-color: #dc3232 !important;
            box-shadow: 0 0 2px rgba(204, 74, 74, 0.8) !important;
        }
        </style>';
        
        echo '<script type="text/javascript">
        jQuery(document).ready(function($) {
            $("#emailit_queue_batch_size").on("input change", function() {
                var value = parseInt($(this).val());
                if (isNaN(value) || value < 1 || value > 50) {
                    $(this).addClass("error");
                    if (value < 1) {
                        $(this).val(1);
                    } else if (value > 50) {
                        $(this).val(50);
                    }
                } else {
                    $(this).removeClass("error");
                }
            });
        });
        </script>';
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

        // Use hidden input to ensure unchecked state is submitted
        echo '<input type="hidden" name="emailit_fluentcrm_integration" value="0" />';
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

        // Use hidden input to ensure unchecked state is submitted
        echo '<input type="hidden" name="emailit_fluentcrm_forward_bounces" value="0" />';
        echo '<input type="checkbox" id="emailit_fluentcrm_forward_bounces" name="emailit_fluentcrm_forward_bounces" value="1"' . checked(1, $value, false) . ($fluentcrm_status['available'] ? '' : ' disabled') . ' />';
        echo ' <label for="emailit_fluentcrm_forward_bounces">' . __('Forward bounce data to Emailit API', 'emailit-integration') . '</label>';
        echo '<p class="description">' . __('When enabled, bounce information from FluentCRM will be forwarded to Emailit for comprehensive tracking.', 'emailit-integration') . '</p>';
    }

    public function fluentcrm_suppress_default_field_callback() {
        $value = get_option('emailit_fluentcrm_suppress_default', 0);
        $webhook = emailit_get_component('webhook');
        $fluentcrm_status = $webhook ? $webhook->get_fluentcrm_integration_status() : array('available' => false);

        // Use hidden input to ensure unchecked state is submitted
        echo '<input type="hidden" name="emailit_fluentcrm_suppress_default" value="0" />';
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
        $value = get_option('emailit_fluentcrm_enable_action_mapping', 1);
        $webhook = emailit_get_component('webhook');
        $fluentcrm_status = $webhook ? $webhook->get_fluentcrm_integration_status() : array('available' => false);

        // Use hidden input to ensure unchecked state is submitted
        echo '<input type="hidden" name="emailit_fluentcrm_enable_action_mapping" value="0" />';
        echo '<input type="checkbox" id="emailit_fluentcrm_enable_action_mapping" name="emailit_fluentcrm_enable_action_mapping" value="1"' .
             checked(1, $value, false) . ($fluentcrm_status['available'] ? '' : ' disabled') . ' />';
        echo ' <label for="emailit_fluentcrm_enable_action_mapping">' . __('Enable automatic FluentCRM actions based on bounce classifications', 'emailit-integration') . '</label>';
        echo '<p class="description">' . __('Automatically update FluentCRM subscriber status based on Emailit bounce classifications.', 'emailit-integration') . '</p>';
    }

    public function fluentcrm_auto_create_subscribers_field_callback() {
        $value = get_option('emailit_fluentcrm_auto_create_subscribers', 1);
        $webhook = emailit_get_component('webhook');
        $fluentcrm_status = $webhook ? $webhook->get_fluentcrm_integration_status() : array('available' => false);

        // Use hidden input to ensure unchecked state is submitted
        echo '<input type="hidden" name="emailit_fluentcrm_auto_create_subscribers" value="0" />';
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
        $value = get_option('emailit_fluentcrm_soft_bounce_reset_on_success', 1);
        $webhook = emailit_get_component('webhook');
        $fluentcrm_status = $webhook ? $webhook->get_fluentcrm_integration_status() : array('available' => false);

        // Use hidden input to ensure unchecked state is submitted
        echo '<input type="hidden" name="emailit_fluentcrm_soft_bounce_reset_on_success" value="0" />';
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
                'From: ' . sanitize_text_field($site_name) . ' <' . sanitize_email(get_option('admin_email')) . '>'
            );

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Emailit] About to call wp_mail for test email to: ' . $test_email);
                error_log('[Emailit] Test email subject: ' . $subject);
                error_log('[Emailit] Test email headers: ' . print_r($headers, true));
            }

            // Send via wp_mail (which should be intercepted by our plugin)
            $result = wp_mail($test_email, $subject, $message, $headers);

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
            // Check if queue is enabled
            $queue_enabled = $this->queue->is_enabled();
            
            if (!$queue_enabled) {
                wp_send_json_success(array(
                    'queue_disabled' => true,
                    'pending' => 0,
                    'processing' => 0,
                    'failed' => 0,
                    'message' => __('Queue is disabled. Enable asynchronous sending to use the queue system.', 'emailit-integration')
                ));
                return;
            }

            $stats = $this->queue->get_stats();

            // Ensure we have valid statistics
            if (!is_array($stats) || empty($stats)) {
                wp_send_json_error(array(
                    'message' => __('Failed to retrieve queue statistics.', 'emailit-integration')
                ));
                return;
            }

            // Add queue enabled flag
            $stats['queue_disabled'] = false;
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

        if (!$this->queue) {
            wp_send_json_error(array(
                'message' => __('Queue system not available.', 'emailit-integration')
            ));
            return;
        }

        // Check if queue is enabled
        if (!$this->queue->is_enabled()) {
            wp_send_json_error(array(
                'message' => __('Queue is disabled. Enable asynchronous sending in the settings above to use the queue system.', 'emailit-integration')
            ));
            return;
        }

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

        try {
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

            // Test basic webhook functionality without full processing
            $webhook_secret = get_option('emailit_webhook_secret', '');
            $webhook_url = get_rest_url(null, 'emailit/v1/webhook');

            // Check if webhook secret is configured
            if (empty($webhook_secret)) {
                wp_send_json_error(array(
                    'message' => __('Webhook secret is not configured. Please set a webhook secret in the settings.', 'emailit-integration')
                ));
            }

            // Test webhook signature generation (using the same method as webhook handler)
            $timestamp = time();
            $payload_json = wp_json_encode($test_payload);
            $signature = hash_hmac('sha256', $payload_json, $webhook_secret);

            // Ensure REST API is loaded
            if (!function_exists('rest_get_server')) {
                wp_send_json_error(array(
                    'message' => __('REST API is not available.', 'emailit-integration')
                ));
            }

            // Check if REST API routes are registered
            $server = rest_get_server();
            $routes = $server->get_routes();
            $webhook_route_exists = false;
            $registered_webhook_routes = array();

            foreach ($routes as $route => $handlers) {
                if (strpos($route, 'emailit/v1/webhook') !== false) {
                    $webhook_route_exists = true;
                    $registered_webhook_routes[] = array(
                        'route' => $route,
                        'methods' => array_keys($handlers)
                    );
                }
            }

            if (!$webhook_route_exists) {
                // Try to manually register the routes
                $webhook = emailit_get_component('webhook');
                if ($webhook) {
                    $webhook->register_routes();
                    // Check again after manual registration
                    $routes = $server->get_routes();
                    foreach ($routes as $route => $handlers) {
                        if (strpos($route, 'emailit/v1/webhook') !== false) {
                            $webhook_route_exists = true;
                            break;
                        }
                    }
                }

                if (!$webhook_route_exists) {
                    wp_send_json_error(array(
                        'message' => __('Webhook REST API route is not registered. Please try refreshing the page.', 'emailit-integration')
                    ));
                }
            }

            // Test webhook URL accessibility with POST request (webhook endpoint only accepts POST)
            $response = wp_remote_post($webhook_url, array(
                'timeout' => 5,
                'sslverify' => false,
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'X-Emailit-Signature' => $signature,
                    'X-Emailit-Timestamp' => $timestamp
                ),
                'body' => wp_json_encode($test_payload)
            ));

            if (is_wp_error($response)) {
                wp_send_json_error(array(
                    'message' => sprintf(__('Webhook endpoint not accessible: %s', 'emailit-integration'), $response->get_error_message())
                ));
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            // Debug information
            $debug_info = array(
                'webhook_url' => $webhook_url,
                'response_code' => $response_code,
                'response_body' => $response_body,
                'routes_registered' => $webhook_route_exists,
                'registered_webhook_routes' => $registered_webhook_routes,
                'all_routes_with_emailit' => array_filter(array_keys($routes), function($route) {
                    return strpos($route, 'emailit') !== false;
                })
            );

            if ($response_code !== 200) { // Expect 200 for successful POST request
                wp_send_json_error(array(
                    'message' => sprintf(__('Webhook endpoint returned unexpected status code: %d. Debug info: %s', 'emailit-integration'), $response_code, wp_json_encode($debug_info))
                ));
            }

            wp_send_json_success(array(
                'message' => __('Webhook test successful! The webhook endpoint is accessible and properly configured.', 'emailit-integration')
            ));
        } catch (Exception $e) {
            // Log the error for debugging
            error_log('Emailit Webhook Test Error: ' . $e->getMessage());
            error_log('Emailit Webhook Test Stack Trace: ' . $e->getTraceAsString());

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
            // Don't pass the raw encrypted key - let validate_api_key use the instance's decrypted key
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
                if ($priority < 5) { // Our plugin uses priority 5
                    foreach ($callbacks as $callback) {
                        $function_name = $this->get_callback_name($callback['function']);
                        // Only flag non-Emailit functions with higher priority
                        if ($function_name && 
                            strpos($function_name, 'emailit') === false && 
                            strpos($function_name, 'Emailit') === false) {
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

        // Check if this is already an encrypted key (base64 encoded, contains +, /, =)
        // Encrypted keys are typically 80+ characters and contain base64 characters
        if (strlen($value) > 50 && preg_match('/[+\/=]/', $value)) {
            return $value; // Return the encrypted key as-is
        }

        // If we have a new actual API key, validate and encrypt it
        if (!empty($value) && $value !== get_option('emailit_api_key', '')) {
            // More permissive validation - just check for reasonable length and basic characters
            $trimmed_value = trim($value);
            if (strlen($trimmed_value) < 10) {
                add_settings_error(
                    'emailit_api_key',
                    'invalid_api_key_format',
                    __('API key appears to be too short. Please check your API key and try again.', 'emailit-integration')
                );
                return get_option('emailit_api_key', ''); // Keep existing key on error
            } elseif (!preg_match('/^[a-zA-Z0-9\-_\.]+$/', $trimmed_value)) {
                add_settings_error(
                    'emailit_api_key',
                    'invalid_api_key_format',
                    __('API key contains invalid characters. Please check your API key and try again.', 'emailit-integration')
                );
                return get_option('emailit_api_key', ''); // Keep existing key on error
            }

            // Test the API key if possible (but don't fail if API is down)
            try {
                // Pass the new API key to validate it before saving
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
            try {
                $this->api->set_api_key($value);
                return get_option('emailit_api_key', '');
            } catch (Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[Emailit] Exception during API key save: ' . $e->getMessage());
                }
                return get_option('emailit_api_key', ''); // Return existing key on error
            }
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
     * Sanitize queue batch size values
     */
    public function sanitize_queue_batch_size($value) {
        $value = absint($value);
        
        // Ensure value is within valid range
        if ($value < 1) {
            $value = 1;
        } elseif ($value > 50) {
            $value = 50;
        }
        
        return $value;
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
    <table cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #f8f9fa; padding: 5px 0;">
        <tr>
            <td align="center">
                <table cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width: 500px; background-color: #ffffff; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background: #667eea; padding: 12px 16px; text-align: center; border-radius: 4px 4px 0 0;">
                            <h1 style="color: #ffffff; margin: 0 0 4px 0; font-size: 18px; font-weight: 600;">🔧 ' . esc_html__('WordPress wp_mail() Test', 'emailit-integration') . '</h1>
                            <p style="color: #e8f4fd; margin: 0; font-size: 12px;">' . esc_html__('Testing email interception and processing', 'emailit-integration') . '</p>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 12px 16px;">
                            <!-- Success Badge -->
                            <table cellpadding="0" cellspacing="0" border="0" width="100%" style="background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; margin: 0 0 8px 0;">
                                <tr>
                                    <td style="padding: 8px; text-align: center;">
                                        <div style="font-size: 20px; margin-bottom: 4px; color: #28a745;">✅</div>
                                        <h2 style="color: #155724; margin: 0 0 4px 0; font-size: 14px;">' . esc_html__('Test Successful', 'emailit-integration') . '</h2>
                                        <p style="color: #155724; margin: 0; font-size: 12px;">' . esc_html__('This email was sent through WordPress wp_mail() function', 'emailit-integration') . '</p>
                                    </td>
                                </tr>
                            </table>

                            <p style="color: #495057; font-size: 13px; line-height: 1.4; margin: 0 0 8px 0;">' . sprintf(__('Hello! This is a test email sent from <strong>%s</strong> using the standard WordPress wp_mail() function. If you received this email, it means the Emailit plugin successfully intercepted the wp_mail() call and routed it through the Emailit API.', 'emailit-integration'), esc_html($site_name)) . '</p>

                            <!-- Info Card -->
                            <table cellpadding="0" cellspacing="0" border="0" width="100%" style="background: #f8f9fa; border-radius: 4px; margin: 0 0 8px 0;">
                                <tr>
                                    <td style="padding: 8px;">
                                        <h3 style="margin: 0 0 6px 0; color: #495057; font-size: 13px;">' . esc_html__('Test Details', 'emailit-integration') . '</h3>

                                        <table cellpadding="0" cellspacing="0" border="0" width="100%">
                                            <tr>
                                                <td style="padding: 3px 0; border-bottom: 1px solid #e9ecef;">
                                                    <strong style="color: #495057; font-size: 11px;">' . esc_html__('Website:', 'emailit-integration') . '</strong>
                                                </td>
                                                <td style="padding: 3px 0; border-bottom: 1px solid #e9ecef; text-align: right; color: #6c757d; font-family: monospace; font-size: 11px;">
                                                    ' . esc_html($site_name) . '
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 3px 0; border-bottom: 1px solid #e9ecef;">
                                                    <strong style="color: #495057; font-size: 11px;">' . esc_html__('URL:', 'emailit-integration') . '</strong>
                                                </td>
                                                <td style="padding: 3px 0; border-bottom: 1px solid #e9ecef; text-align: right; color: #6c757d; font-family: monospace; font-size: 11px;">
                                                    ' . esc_html($site_url) . '
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 3px 0; border-bottom: 1px solid #e9ecef;">
                                                    <strong style="color: #495057; font-size: 11px;">' . esc_html__('Sent At:', 'emailit-integration') . '</strong>
                                                </td>
                                                <td style="padding: 3px 0; border-bottom: 1px solid #e9ecef; text-align: right; color: #6c757d; font-family: monospace; font-size: 11px;">
                                                    ' . esc_html($current_time) . '
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 3px 0; border-bottom: 1px solid #e9ecef;">
                                                    <strong style="color: #495057; font-size: 11px;">' . esc_html__('Plugin Version:', 'emailit-integration') . '</strong>
                                                </td>
                                                <td style="padding: 3px 0; border-bottom: 1px solid #e9ecef; text-align: right; color: #6c757d; font-family: monospace; font-size: 11px;">
                                                    v' . esc_html(EMAILIT_VERSION) . '
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 3px 0;">
                                                    <strong style="color: #495057; font-size: 11px;">' . esc_html__('Method:', 'emailit-integration') . '</strong>
                                                </td>
                                                <td style="padding: 3px 0; text-align: right; color: #6c757d; font-family: monospace; font-size: 11px;">
                                                    wp_mail() → Emailit API
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <p style="color: #495057; font-size: 12px; line-height: 1.4; margin: 0 0 8px 0;">🎉 ' . esc_html__('Perfect! Your WordPress site is properly integrated with Emailit. All plugins and features that send emails through wp_mail() will now be delivered via the Emailit service, providing better deliverability and tracking capabilities.', 'emailit-integration') . '</p>

                            <!-- CTA Button -->
                            <table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin: 0;">
                                <tr>
                                    <td align="center">
                                        <a href="' . esc_url(admin_url('tools.php?page=emailit-logs')) . '" style="display: inline-block; background: #007cba; color: #ffffff; padding: 6px 12px; border-radius: 3px; text-decoration: none; font-weight: 600; font-size: 12px;">' . esc_html__('View Emailit Log', 'emailit-integration') . '</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background: #f8f9fa; padding: 8px; text-align: center; color: #6c757d; font-size: 10px; border-radius: 0 0 4px 4px;">
                            <p style="margin: 0;">' . sprintf(
                                __('This email was sent by the %s plugin. %s', 'emailit-integration'),
                                '<strong>Emailit Integration</strong>',
                                '<a href="https://emailit.com/" target="_blank" style="color: #007cba; text-decoration: none;">Learn more about Emailit</a>'
                            ) . '</p>
                            <p style="margin: 4px 0 0 0; font-size: 9px; color: #adb5bd;">' . esc_html__('This is an automated test email. Please do not reply.', 'emailit-integration') . '</p>
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

    /**
     * AJAX handler for getting health metrics with time period
     */
    public function ajax_get_health_metrics() {
        check_ajax_referer('emailit_health_metrics', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'emailit-integration'));
        }

        try {
            $time_period = sanitize_text_field($_POST['time_period'] ?? '1h');
            $health_monitor = emailit_get_component('health_monitor');

            if (!$health_monitor) {
                wp_send_json_error(array('message' => __('Health monitoring is not available.', 'emailit-integration')));
            }

            // Get metrics with time period
            $metrics = $health_monitor->get_metrics_with_time_period($time_period);
            wp_send_json_success($metrics);
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * AJAX handler for dismissing webhook alerts
     */
    public function ajax_dismiss_webhook_alert() {
        // Check nonce and user capabilities
        if (!wp_verify_nonce($_POST['nonce'], 'emailit_webhook_alerts') || !current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }

        $alert_index = intval($_POST['alert_index']);

        // Get webhook monitor component
        $webhook_monitor = emailit_get_component('webhook_monitor');
        if (!$webhook_monitor) {
            wp_send_json_error('Webhook monitoring not available');
            return;
        }

        // Dismiss the alert
        $result = $webhook_monitor->dismiss_webhook_alert($alert_index);

        if ($result) {
            wp_send_json_success('Alert dismissed');
        } else {
            wp_send_json_error('Failed to dismiss alert');
        }
    }

    /**
     * AJAX handler for getting webhook details
     */
    public function ajax_get_webhook_details() {
        // Check nonce and user capabilities
        if (!wp_verify_nonce($_POST['nonce'], 'emailit_webhook_details') || !current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }

        $webhook_id = intval($_POST['webhook_id']);

        global $wpdb;
        $webhook_logs_table = $wpdb->prefix . 'emailit_webhook_logs';

        // Get webhook details
        $webhook = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$webhook_logs_table} WHERE id = %d
        ", $webhook_id));

        if (!$webhook) {
            wp_send_json_error('Webhook not found');
            return;
        }

        // Check if this is a test webhook
        $is_test_webhook = (strpos($webhook->event_id ?: '', 'WEBHOOKTEST_') === 0) ||
                          (strpos($webhook->email_id ?: '', 'TEST_EMAIL_') === 0);

        // Format the details for display
        $html = '<div class="webhook-details">';

        if ($is_test_webhook) {
            $html .= '<div class="notice notice-info" style="margin: 0 0 20px 0; padding: 10px 15px;">';
            $html .= '<strong>' . __('Test Webhook', 'emailit-integration') . '</strong> - ';
            $html .= __('This webhook was generated by the webhook test function in the admin interface.', 'emailit-integration');
            $html .= '</div>';
        }

        $html .= '<h3>' . __('Webhook Information', 'emailit-integration') . '</h3>';
        $html .= '<table class="form-table">';
        $html .= '<tr><th>' . __('Webhook ID', 'emailit-integration') . '</th><td>' . esc_html($webhook->id) . '</td></tr>';
        $html .= '<tr><th>' . __('Request ID', 'emailit-integration') . '</th><td><code>' . esc_html($webhook->webhook_request_id ?: 'N/A') . '</code></td></tr>';
        $html .= '<tr><th>' . __('Event ID', 'emailit-integration') . '</th><td><code>' . esc_html($webhook->event_id ?: 'N/A') . '</code></td></tr>';
        $html .= '<tr><th>' . __('Event Type', 'emailit-integration') . '</th><td>' . esc_html($webhook->event_type ?: 'N/A') . '</td></tr>';
        $html .= '<tr><th>' . __('Email ID', 'emailit-integration') . '</th><td><code>' . esc_html($webhook->email_id ?: 'N/A') . '</code></td></tr>';
        $html .= '<tr><th>' . __('Status', 'emailit-integration') . '</th><td><span class="status status-' . esc_attr($webhook->status ?: 'unknown') . '">' . esc_html(ucfirst($webhook->status ?: 'Unknown')) . '</span></td></tr>';
        $html .= '<tr><th>' . __('Processed At', 'emailit-integration') . '</th><td>' . esc_html($webhook->processed_at) . '</td></tr>';
        $html .= '</table>';

        if (!empty($webhook->details)) {
            $details = json_decode($webhook->details, true);
            if ($details) {
                $html .= '<h3>' . __('Webhook Details', 'emailit-integration') . '</h3>';
                $html .= '<table class="form-table">';

                // Display extracted details in a more readable format
                if (!empty($details['from_email'])) {
                    $html .= '<tr><th>' . __('From Email', 'emailit-integration') . '</th><td>' . esc_html($details['from_email']) . '</td></tr>';
                }
                if (!empty($details['to_email'])) {
                    $html .= '<tr><th>' . __('To Email', 'emailit-integration') . '</th><td>' . esc_html($details['to_email']) . '</td></tr>';
                }
                if (!empty($details['subject'])) {
                    $html .= '<tr><th>' . __('Subject', 'emailit-integration') . '</th><td>' . esc_html($details['subject']) . '</td></tr>';
                }
                if (!empty($details['timestamp'])) {
                    $html .= '<tr><th>' . __('Event Timestamp', 'emailit-integration') . '</th><td>' . esc_html($details['timestamp']) . '</td></tr>';
                }
                if (!empty($details['bounce_reason'])) {
                    $html .= '<tr><th>' . __('Bounce Reason', 'emailit-integration') . '</th><td>' . esc_html($details['bounce_reason']) . '</td></tr>';
                }
                if (!empty($details['complaint_reason'])) {
                    $html .= '<tr><th>' . __('Complaint Reason', 'emailit-integration') . '</th><td>' . esc_html($details['complaint_reason']) . '</td></tr>';
                }
                if (!empty($details['failure_reason'])) {
                    $html .= '<tr><th>' . __('Failure Reason', 'emailit-integration') . '</th><td>' . esc_html($details['failure_reason']) . '</td></tr>';
                }

                $html .= '</table>';

                // Show full details as JSON for debugging
                $html .= '<h4>' . __('Full Details (JSON)', 'emailit-integration') . '</h4>';
                $html .= '<pre><code>' . esc_html(json_encode($details, JSON_PRETTY_PRINT)) . '</code></pre>';
            }
        }

        if (!empty($webhook->raw_payload)) {
            $raw_payload = json_decode($webhook->raw_payload, true);
            if ($raw_payload) {
                $html .= '<h3>' . __('Raw Webhook Payload', 'emailit-integration') . '</h3>';
                $html .= '<pre><code>' . esc_html(json_encode($raw_payload, JSON_PRETTY_PRINT)) . '</code></pre>';
            }
        }

        $html .= '</div>';

        wp_send_json_success(array('html' => $html));
    }

    /**
     * AJAX handler for clearing webhook logs
     */
    public function ajax_clear_webhook_logs() {
        // Check nonce and user capabilities
        if (!wp_verify_nonce($_POST['nonce'], 'emailit_webhook_logs') || !current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }

        $action = sanitize_text_field($_POST['action_type']);

        // Get webhook monitor component
        $webhook_monitor = emailit_get_component('webhook_monitor');
        if (!$webhook_monitor) {
            wp_send_json_error('Webhook monitoring not available');
            return;
        }

        $result = false;
        $message = '';

        switch ($action) {
            case 'clear_all':
                $result = $webhook_monitor->clear_webhook_logs();
                $message = $result ? __('All webhook logs cleared successfully.', 'emailit-integration') : __('Failed to clear webhook logs.', 'emailit-integration');
                break;

            case 'clear_old':
                $days = isset($_POST['days']) ? intval($_POST['days']) : 30;
                $result = $webhook_monitor->clear_old_webhook_logs($days);
                if ($result !== false) {
                    $message = sprintf(__('Cleared %d webhook logs older than %d days.', 'emailit-integration'), $result, $days);
                } else {
                    $message = __('Failed to clear old webhook logs.', 'emailit-integration');
                }
                break;

            default:
                wp_send_json_error('Invalid action type');
                return;
        }

        if ($result !== false) {
            wp_send_json_success(array('message' => $message));
        } else {
            wp_send_json_error(array('message' => $message));
        }
    }

    /**
     * AJAX handler for getting missing webhooks details
     */
    public function ajax_get_missing_webhooks() {
        // Check nonce and user capabilities
        if (!wp_verify_nonce($_POST['nonce'], 'emailit_missing_webhooks') || !current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }

        global $wpdb;
        $logs_table = $wpdb->prefix . 'emailit_logs';
        $webhook_logs_table = $wpdb->prefix . 'emailit_webhook_logs';

        // Get emails sent in the last 24 hours that should have webhooks
        $cutoff_time = date('Y-m-d H:i:s', time() - 86400); // 24 hours ago

        $missing_webhooks = $wpdb->get_results($wpdb->prepare("
            SELECT e.id, e.email_id, e.to_email, e.subject, e.sent_at, e.status
            FROM {$logs_table} e
            LEFT JOIN {$webhook_logs_table} w ON e.email_id = w.email_id
            WHERE e.sent_at >= %s
            AND e.status = 'sent'
            AND w.id IS NULL
            AND e.sent_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)
            ORDER BY e.sent_at DESC
            LIMIT 20
        ", $cutoff_time));

        if (empty($missing_webhooks)) {
            wp_send_json_success(array('html' => '<p style="color: #666; font-style: italic;">' . __('No missing webhooks found.', 'emailit-integration') . '</p>'));
            return;
        }

        // Generate HTML for missing webhooks list
        $html = '<div class="missing-webhooks-header">';
        $html .= '<h4>' . sprintf(__('Missing Webhooks (%d)', 'emailit-integration'), count($missing_webhooks)) . '</h4>';
        $html .= '<p class="description">' . __('Emails sent without webhook confirmations:', 'emailit-integration') . '</p>';
        $html .= '</div>';

        $html .= '<div class="missing-webhooks-items">';
        foreach ($missing_webhooks as $webhook) {
            $time_ago = human_time_diff(strtotime($webhook->sent_at), current_time('timestamp'));
            $formatted_time = date('Y-m-d H:i:s', strtotime($webhook->sent_at));

            $html .= '<div class="missing-webhook-item">';
            $html .= '<div class="missing-webhook-email-info">';
            $html .= '<div class="missing-webhook-email-id">' . esc_html($webhook->email_id) . '</div>';
            if (!empty($webhook->to_email)) {
                $html .= '<div class="missing-webhook-to-email">' . esc_html($webhook->to_email) . '</div>';
            }
            if (!empty($webhook->subject)) {
                $html .= '<div class="missing-webhook-subject">' . esc_html($webhook->subject) . '</div>';
            }
            $html .= '</div>';
            $html .= '<div class="missing-webhook-time-info">';
            $html .= '<div class="missing-webhook-time">' . esc_html($formatted_time) . '</div>';
            $html .= '<div class="missing-webhook-time-ago">' . sprintf(__('%s ago', 'emailit-integration'), $time_ago) . '</div>';
            $html .= '</div>';
            $html .= '</div>';
        }
        $html .= '</div>';

        wp_send_json_success(array('html' => $html));
    }


    /**
     * AJAX handler for getting recent activity
     */
    public function ajax_get_recent_activity() {
        check_ajax_referer('emailit_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'emailit-integration')));
        }

        try {
            $logger = emailit_get_component('logger');
            if (!$logger) {
                wp_send_json_success(array('message' => __('Logger not available.', 'emailit-integration')));
            }

            // Get recent email count (last 24 hours)
            $recent_count = $logger->get_recent_email_count(24);
            $message = sprintf(_n('%d email sent in the last 24 hours', '%d emails sent in the last 24 hours', $recent_count, 'emailit-integration'), $recent_count);

            wp_send_json_success(array('message' => $message));
        } catch (Exception $e) {
            wp_send_json_success(array('message' => __('Unable to load activity data.', 'emailit-integration')));
        }
    }

    /**
     * AJAX handler for getting queue status
     */
    public function ajax_get_queue_status() {
        check_ajax_referer('emailit_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'emailit-integration')));
        }

        try {
            $queue = emailit_get_component('queue');
            if (!$queue) {
                wp_send_json_success(array('message' => __('Queue not available.', 'emailit-integration')));
            }

            $stats = $queue->get_stats();
            $queue_enabled = get_option('emailit_enable_queue', 0);

            if (!$queue_enabled) {
                $message = __('Queue disabled', 'emailit-integration');
            } else {
                $pending = $stats['pending'] ?? 0;
                $message = sprintf(_n('%d email pending', '%d emails pending', $pending, 'emailit-integration'), $pending);
            }

            wp_send_json_success(array('message' => $message));
        } catch (Exception $e) {
            wp_send_json_success(array('message' => __('Unable to load queue data.', 'emailit-integration')));
        }
    }

    /**
     * AJAX handler for getting quick stats
     */
    public function ajax_get_quick_stats() {
        check_ajax_referer('emailit_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'emailit-integration')));
        }

        try {
            $logger = emailit_get_component('logger');
            $queue = emailit_get_component('queue');

            if (!$logger) {
                // Manual JSON response
                if (ob_get_level()) {
                    ob_clean();
                }
                
                if (!headers_sent()) {
                    header('Content-Type: application/json; charset=utf-8');
                    status_header(200);
                }
                
                $json_response = json_encode(array('success' => true, 'data' => array(
                    'today_emails' => 0,
                    'success_rate' => 0,
                    'failed_emails' => 0,
                    'queue_count' => 0
                )));
                echo $json_response;
                wp_die();
            }

            // Get today's stats
            $today_emails = $logger->get_recent_email_count(24);
            $today_stats = $logger->get_daily_stats(date('Y-m-d'));

            $success_rate = 0;
            $failed_emails = 0;

            if ($today_stats) {
                $total = $today_stats['sent'] ?? 0;
                $failed = $today_stats['failed'] ?? 0;
                $success_rate = $total > 0 ? round((($total - $failed) / $total) * 100, 1) : 0;
                $failed_emails = $failed;
            }

            // Get queue count
            $queue_count = 0;
            if ($queue) {
                $queue_stats = $queue->get_stats();
                $queue_count = $queue_stats['pending'] ?? 0;
            }

            // Manual JSON response
            if (ob_get_level()) {
                ob_clean();
            }
            
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
                status_header(200);
            }
            
            $response_data = array(
                'today_emails' => $today_emails,
                'success_rate' => $success_rate,
                'failed_emails' => $failed_emails,
                'queue_count' => $queue_count
            );
            
            $json_response = json_encode(array('success' => true, 'data' => $response_data));
            echo $json_response;
            wp_die();
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[Emailit ERROR] Quick stats AJAX error: ' . $e->getMessage());
                }
            }
            
            // Manual JSON response for error
            if (ob_get_level()) {
                ob_clean();
            }
            
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
                status_header(200);
            }
            
            $json_response = json_encode(array('success' => true, 'data' => array(
                'today_emails' => 0,
                'success_rate' => 0,
                'failed_emails' => 0,
                'queue_count' => 0
            )));
            echo $json_response;
            wp_die();
        }
    }

    /**
     * AJAX handler for getting webhook status
     */
    public function ajax_get_webhook_status() {
        check_ajax_referer('emailit_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'emailit-integration')));
        }

        try {
            $webhook_monitor = emailit_get_component('webhook_monitor');
            
            if (!$webhook_monitor) {
                wp_send_json_success(array(
                    'status' => __('Not Available', 'emailit-integration'),
                    'count' => '-',
                    'last_webhook' => '-'
                ));
                return;
            }

            // Get webhook health status with error handling
            $webhook_health = array('health_score' => 0);
            $webhook_stats = array('total_webhooks' => 0);
            $recent_webhooks = array();
            
            try {
                $webhook_health = $webhook_monitor->get_webhook_health_status();
            } catch (Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('[Emailit ERROR] Failed to get webhook health: ' . $e->getMessage());
                    }
                }
            }
            
            try {
                $webhook_stats = $webhook_monitor->get_webhook_statistics(7);
            } catch (Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('[Emailit ERROR] Failed to get webhook stats: ' . $e->getMessage());
                    }
                }
            }
            
            try {
                $recent_webhooks = $webhook_monitor->get_recent_webhook_activity(1);
            } catch (Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('[Emailit ERROR] Failed to get recent webhooks: ' . $e->getMessage());
                    }
                }
            }


            // Determine status based on health score
            $status = __('Unknown', 'emailit-integration');
            if ($webhook_health && isset($webhook_health['health_score'])) {
                $health_score = $webhook_health['health_score'];
                if ($health_score >= 80) {
                    $status = __('Active', 'emailit-integration');
                } elseif ($health_score >= 50) {
                    $status = __('Warning', 'emailit-integration');
                } else {
                    $status = __('Error', 'emailit-integration');
                }
            }

            // Get recent webhook count (last 7 days)
            $count = 0;
            if ($webhook_stats && isset($webhook_stats['total_webhooks'])) {
                $count = intval($webhook_stats['total_webhooks']);
            }
            
            // Debug: Log the count being returned
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Emailit DEBUG] AJAX webhook count: ' . $count);
                error_log('[Emailit DEBUG] Webhook stats: ' . print_r($webhook_stats, true));
            }

            // Get last webhook time
            if (is_array($recent_webhooks)) {
            }
            $last_webhook = '-';
            if ($recent_webhooks && !empty($recent_webhooks)) {
                try {
                    $last_webhook_data = $recent_webhooks[0];
                    
                    // Convert object to array if needed
                    if (is_object($last_webhook_data)) {
                        $last_webhook_data = (array) $last_webhook_data;
                    }
                    
                    if (is_array($last_webhook_data)) {
                    } else {
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log('[Emailit ERROR] Last webhook data is not an array after conversion: ' . gettype($last_webhook_data));
                        }
                    }
                } catch (Exception $e) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('[Emailit ERROR] Exception accessing webhook data: ' . $e->getMessage());
                    }
                    $last_webhook_data = null;
                }
                if (isset($last_webhook_data['processed_at'])) {
                    $processed_at = $last_webhook_data['processed_at'];
                    
                    try {
                        $timestamp = strtotime($processed_at);
                        if ($timestamp !== false && $timestamp > 0) {
                            $last_webhook = date('M j, Y g:i A', $timestamp);
                        } else {
                            if (defined('WP_DEBUG') && WP_DEBUG) {
                                error_log('[Emailit ERROR] Invalid timestamp for processed_at: ' . $processed_at);
                            }
                            $last_webhook = $processed_at; // Fallback to raw value
                        }
                    } catch (Exception $e) {
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log('[Emailit ERROR] Exception processing timestamp: ' . $e->getMessage());
                        }
                        $last_webhook = $processed_at; // Fallback to raw value
                    }
                } else {
                }
            } else {
            }

            $response_data = array(
                'status' => $status,
                'count' => $count,
                'last_webhook' => $last_webhook
            );
            
            
            // Try manual JSON response instead of wp_send_json_success
            if (ob_get_level()) {
                ob_clean();
            }
            
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
                status_header(200);
            }
            
            $json_response = json_encode(array('success' => true, 'data' => $response_data));
            echo $json_response;
            wp_die();
        } catch (Exception $e) {
            // Log the error for debugging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Emailit ERROR] Webhook status AJAX error: ' . $e->getMessage());
            }
            wp_send_json_success(array(
                'status' => __('Error', 'emailit-integration'),
                'count' => '-',
                'last_webhook' => '-'
            ));
        }
    }

    /**
     * AJAX handler for getting recent logs
     */
    public function ajax_get_recent_logs() {
        check_ajax_referer('emailit_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'emailit-integration')));
        }

        try {
            $logger = emailit_get_component('logger');
            
            if (!$logger) {
                wp_send_json_success(array('html' => '<p>' . __('Logger not available.', 'emailit-integration') . '</p>'));
            }

            // Get recent logs (last 10) with error handling
            $recent_logs = array();
            try {
                $logs_result = $logger->get_logs(array('per_page' => 10, 'order' => 'DESC'));
                $recent_logs = $logs_result['logs'] ?? array();
            } catch (Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[Emailit ERROR] Failed to get recent logs: ' . $e->getMessage());
                }
            }
            
            if (empty($recent_logs)) {
                $html = '<p>' . __('No recent email activity found.', 'emailit-integration') . '</p>';
            } else {
                $html = '<div class="emailit-recent-logs">';
                $html .= '<table class="wp-list-table widefat fixed striped">';
                $html .= '<thead><tr>';
                $html .= '<th>' . __('Time', 'emailit-integration') . '</th>';
                $html .= '<th>' . __('To', 'emailit-integration') . '</th>';
                $html .= '<th>' . __('Subject', 'emailit-integration') . '</th>';
                $html .= '<th>' . __('Status', 'emailit-integration') . '</th>';
                $html .= '</tr></thead><tbody>';
                
                foreach ($recent_logs as $log) {
                    $status_class = $log['status'] === 'sent' ? 'success' : 'error';
                    $status_text = $log['status'] === 'sent' ? __('Sent', 'emailit-integration') : __('Failed', 'emailit-integration');
                    
                    $html .= '<tr>';
                    $html .= '<td>' . esc_html(date('M j, Y g:i A', strtotime($log['created_at']))) . '</td>';
                    $html .= '<td>' . esc_html($log['to_email']) . '</td>';
                    $html .= '<td>' . esc_html($log['subject']) . '</td>';
                    $html .= '<td><span class="status-' . $status_class . '">' . $status_text . '</span></td>';
                    $html .= '</tr>';
                }
                
                $html .= '</tbody></table>';
                $html .= '</div>';
            }

            // Manual JSON response
            if (ob_get_level()) {
                ob_clean();
            }
            
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
                status_header(200);
            }
            
            $json_response = json_encode(array('success' => true, 'data' => array('html' => $html)));
            echo $json_response;
            wp_die();
        } catch (Exception $e) {
            // Log the error for debugging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Emailit ERROR] Recent logs AJAX error: ' . $e->getMessage());
            }
            
            // Manual JSON response for error
            if (ob_get_level()) {
                ob_clean();
            }
            
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
                status_header(200);
            }
            
            $json_response = json_encode(array('success' => true, 'data' => array('html' => '<p>' . __('Error loading recent logs.', 'emailit-integration') . '</p>')));
            echo $json_response;
            wp_die();
        }
    }

    /**
     * AJAX handler for getting health status
     */
    public function ajax_get_health_status() {
        
        check_ajax_referer('emailit_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'emailit-integration')));
        }

        try {
            $health_monitor = emailit_get_component('health_monitor');
            
            if (!$health_monitor) {
                wp_send_json_error(array('message' => __('Health monitor not available.', 'emailit-integration')));
            }

            // Get health status
            $health_status_response = $health_monitor->get_health_status();
            $health_status = array();
            
            if (is_wp_error($health_status_response)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Emailit ERROR] Health status WP_Error: ' . $health_status_response->get_error_message());
            }
            } else {
                $health_status = $health_status_response->get_data();
            }

            // Get health metrics for more detailed status
            $health_metrics_response = $health_monitor->get_health_metrics();
            $health_metrics = array();
            
            if (is_wp_error($health_metrics_response)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Emailit ERROR] Health metrics WP_Error: ' . $health_metrics_response->get_error_message());
            }
            } else {
                $health_metrics = $health_metrics_response->get_data();
            }

            // Determine individual component statuses
            $response_data = array();
            
            // System Health - based on overall health status
            if (isset($health_status['overall'])) {
                $overall_status = $health_status['overall'];
                if ($overall_status === 'success') {
                    $response_data['system_health'] = array('status' => 'Success', 'class' => 'success');
                } elseif ($overall_status === 'warning') {
                    $response_data['system_health'] = array('status' => 'Warning', 'class' => 'warning');
                } else {
                    $response_data['system_health'] = array('status' => 'Error', 'class' => 'error');
                }
            } else {
                $response_data['system_health'] = array('status' => 'Unknown', 'class' => 'error');
            }
            
            // API Connectivity - check if API is working
            $api_status = 'Error';
            $api_class = 'error';
            if (isset($health_metrics['api_metrics']['daily']['success_rate'])) {
                $success_rate = $health_metrics['api_metrics']['daily']['success_rate'];
                if ($success_rate >= 0.9) {
                    $api_status = 'Success';
                    $api_class = 'success';
                } elseif ($success_rate >= 0.7) {
                    $api_status = 'Warning';
                    $api_class = 'warning';
                }
            }
            $response_data['api_connectivity'] = array('status' => $api_status, 'class' => $api_class);
            
            // Database Health - check for database issues
            $db_status = 'Success';
            $db_class = 'success';
            if (isset($health_metrics['performance_metrics']['database_performance']['slow_queries'])) {
                $slow_queries = $health_metrics['performance_metrics']['database_performance']['slow_queries'];
                if ($slow_queries > 10) {
                    $db_status = 'Warning';
                    $db_class = 'warning';
                } elseif ($slow_queries > 50) {
                    $db_status = 'Error';
                    $db_class = 'error';
                }
            }
            $response_data['database_health'] = array('status' => $db_status, 'class' => $db_class);

            
            // Try manual JSON response instead of wp_send_json_success
            if (ob_get_level()) {
                ob_clean();
            }
            
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
                status_header(200);
            }
            
            $json_response = json_encode(array('success' => true, 'data' => $response_data));
            echo $json_response;
            wp_die();
            
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Emailit ERROR] Health status AJAX error: ' . $e->getMessage());
            }
            wp_send_json_error(array('message' => __('Failed to load health status.', 'emailit-integration')));
        }
    }


    /**
     * AJAX handler for toggling power user mode
     */
    public function ajax_toggle_power_user_mode() {
        check_ajax_referer('emailit_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'emailit-integration')));
        }

        $current_mode = get_user_meta(get_current_user_id(), 'emailit_power_user_mode', true);
        $new_mode = $current_mode ? 0 : 1;

        update_user_meta(get_current_user_id(), 'emailit_power_user_mode', $new_mode);

        wp_send_json_success(array(
            'power_user_mode' => (bool) $new_mode,
            'message' => $new_mode ?
                __('Power User Mode enabled. Advanced features are now visible.', 'emailit-integration') :
                __('Power User Mode disabled. Interface simplified for basic users.', 'emailit-integration')
        ));
    }

    /**
     * Check if current user has power user mode enabled
     */
    public function is_power_user_mode() {
        return (bool) get_user_meta(get_current_user_id(), 'emailit_power_user_mode', true);
    }


    /**
     * AJAX handler for clearing API cache
     */
    public function ajax_clear_api_cache() {
        check_ajax_referer('emailit_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'emailit-integration')));
        }

        try {
            $api = emailit_get_component('api');
            if ($api) {
                $api->clear_validation_cache();
            }

            // Clear all related transients
            delete_transient('emailit_api_key_validation');
            delete_transient('emailit_queue_stats');
            delete_transient('emailit_performance_stats');

            // Clear all API key validation caches
            global $wpdb;
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_emailit_api_key_valid_%'");
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_emailit_api_key_valid_%'");

            wp_send_json_success(array('message' => __('API cache cleared successfully.', 'emailit-integration')));
        } catch (Exception $e) {
            wp_send_json_error(array('message' => sprintf(__('Error clearing cache: %s', 'emailit-integration'), $e->getMessage())));
        }
    }

    /**
     * AJAX handler for clearing API key completely
     */
    public function ajax_clear_api_key() {
        check_ajax_referer('emailit_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'emailit-integration')));
        }

        try {
            $api = emailit_get_component('api');
            if ($api) {
                $api->clear_api_key();
            }

            // Also clear the static cache
            Emailit_API::clear_api_key_cache();

            wp_send_json_success(array('message' => __('API key cleared successfully.', 'emailit-integration')));
        } catch (Exception $e) {
            wp_send_json_error(array('message' => sprintf(__('Error clearing API key: %s', 'emailit-integration'), $e->getMessage())));
        }
    }

    /**
     * Register Emailit bounce handler with FluentCRM
     *
     * @param array $bounceSettings Current bounce settings
     * @param string $securityCode Security code for bounce handler
     * @return array Modified bounce settings
     */
    public function register_fluentcrm_bounce_handler($bounceSettings, $securityCode) {
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Emailit] register_fluentcrm_bounce_handler called');
            error_log('[Emailit] FluentCRM available: ' . ($this->is_fluentcrm_available() ? 'yes' : 'no'));
            error_log('[Emailit] Integration enabled: ' . (get_option('emailit_fluentcrm_integration', 1) ? 'yes' : 'no'));
        }

        // Only add if FluentCRM is available and integration is enabled
        if (!$this->is_fluentcrm_available() || !get_option('emailit_fluentcrm_integration', 1)) {
            return $bounceSettings;
        }

        // Add Emailit bounce handler
        $bounceSettings['emailit'] = array(
            'label'       => __('Emailit', 'emailit-integration'),
            'webhook_url' => get_rest_url(null, 'fluent-crm/v2/public/bounce_handler/emailit/handle/' . $securityCode),
            'doc_url'     => 'https://github.com/apooley/emailit-integration',
            'input_title' => __('Emailit Bounce Handler', 'emailit-integration'),
            'input_info'  => '<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px; text-align: center; margin: 10px 0; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
                <h3 style="margin: 0 0 15px 0; font-size: 24px; font-weight: bold; text-shadow: 2px 2px 4px rgba(0,0,0,0.3);">🎉 Congratulations! 🎉</h3>
                <p style="margin: 0; font-size: 18px; font-weight: 600; line-height: 1.4; text-shadow: 1px 1px 2px rgba(0,0,0,0.3);">Emailit\'s integration is handling this for you - just sit back and relax!</p>
                <p style="margin: 10px 0 0 0; font-size: 14px; opacity: 0.9; font-style: italic;">Bounce handling is fully automated and requires no additional configuration.</p>
            </div>'
        );

        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Emailit] Added Emailit to bounce handlers');
            error_log('[Emailit] Bounce handlers count: ' . count($bounceSettings));
        }

        return $bounceSettings;
    }

    /**
     * Auto-select Emailit as the FluentCRM bounce handler
     * 
     * @return array Result of the operation
     */
    public function auto_select_emailit_bounce_handler() {
        // Check if FluentCRM is available
        if (!$this->is_fluentcrm_available()) {
            return array('success' => false, 'message' => 'FluentCRM is not available');
        }

        // Check if integration is enabled
        if (!get_option('emailit_fluentcrm_integration', 1)) {
            return array('success' => false, 'message' => 'Emailit FluentCRM integration is not enabled');
        }

        // Get current FluentCRM settings
        $fluentcrm_settings = get_option('fluentcrm-global-settings', array());
        
        // Check if Emailit is already selected
        $current_provider = isset($fluentcrm_settings['bounce_handler_provider']) ? $fluentcrm_settings['bounce_handler_provider'] : '';
        
        if ($current_provider === 'emailit') {
            return array('success' => true, 'message' => 'Emailit is already selected as bounce handler');
        }
        
        // Auto-select Emailit
        $fluentcrm_settings['bounce_handler_provider'] = 'emailit';
        
        // Save the updated settings
        $result = update_option('fluentcrm-global-settings', $fluentcrm_settings);
        
        if ($result) {
            return array('success' => true, 'message' => 'Successfully selected Emailit as bounce handler');
        } else {
            return array('success' => false, 'message' => 'Failed to update FluentCRM settings');
        }
    }

    /**
     * Handle bounce processing for Emailit service
     *
     * @param array $response Current response data
     * @param object $request Request object
     * @param string $securityCode Security code
     * @return array Modified response
     */
    public function handle_fluentcrm_bounce($response, $request, $securityCode) {
        try {
            // Check if FluentCRM is available
            if (!$this->is_fluentcrm_available()) {
                return array(
                    'success' => 0,
                    'message' => __('FluentCRM is not available', 'emailit-integration'),
                    'service' => 'emailit',
                    'result' => '',
                    'time' => time()
                );
            }

            // Verify security code
            $expected_code = fluentcrm_get_option('_fc_bounce_key');
            if (!$expected_code || $securityCode !== $expected_code) {
                return array(
                    'success' => 0,
                    'message' => __('Invalid security code', 'emailit-integration'),
                    'service' => 'emailit',
                    'result' => '',
                    'time' => time()
                );
            }

            // Get the bounce data from request
            $bounce_data = $request->get();
            
            // Log the incoming bounce data
            $this->logger->log('FluentCRM bounce handler called', Emailit_Logger::LEVEL_INFO, array(
                'bounce_data' => $bounce_data,
                'security_code' => $securityCode
            ));

            // Process the bounce using our existing bounce classifier
            $fluentcrm_handler = emailit_get_component('fluentcrm_handler');
            if (!$fluentcrm_handler) {
                throw new Exception('FluentCRM handler not available');
            }

            // Extract email and bounce information
            $email_address = $this->extract_email_from_bounce_data($bounce_data);
            $bounce_reason = $this->extract_bounce_reason($bounce_data);
            $bounce_type = $this->extract_bounce_type($bounce_data);

            if (!$email_address) {
                throw new Exception('Email address not found in bounce data');
            }

            // Create a webhook-like data structure for our bounce classifier
            $webhook_data = array(
                'type' => $bounce_type,
                'bounce_reason' => $bounce_reason,
                'to_email' => $email_address,
                'email_id' => $bounce_data['email_id'] ?? uniqid('emailit_bounce_'),
                'timestamp' => current_time('mysql')
            );

            // Classify the bounce
            $bounce_classifier = new Emailit_Bounce_Classifier($this->logger);
            $classification = $bounce_classifier->classify_bounce($webhook_data);

            // Process the bounce action through FluentCRM handler
            $action_result = $fluentcrm_handler->handle_bounce_action(
                $webhook_data['email_id'],
                $bounce_type,
                array_merge($webhook_data, $classification)
            );

            return array(
                'success' => 1,
                'message' => __('Bounce processed successfully', 'emailit-integration'),
                'service' => 'emailit',
                'result' => array(
                    'email' => $email_address,
                    'classification' => $classification,
                    'action_result' => $action_result
                ),
                'time' => time()
            );

        } catch (Exception $e) {
            $this->logger->log('FluentCRM bounce handler error', Emailit_Logger::LEVEL_ERROR, array(
                'error' => $e->getMessage(),
                'bounce_data' => $request->get()
            ));

            return array(
                'success' => 0,
                'message' => sprintf(__('Error processing bounce: %s', 'emailit-integration'), $e->getMessage()),
                'service' => 'emailit',
                'result' => '',
                'time' => time()
            );
        }
    }

    /**
     * Extract email address from bounce data
     *
     * @param array $bounce_data Bounce data from request
     * @return string|null Email address or null if not found
     */
    private function extract_email_from_bounce_data($bounce_data) {
        // Try different possible keys for email address
        $email_keys = array('email', 'to_email', 'recipient', 'recipient_email', 'address');
        
        foreach ($email_keys as $key) {
            if (isset($bounce_data[$key]) && is_email($bounce_data[$key])) {
                return $bounce_data[$key];
            }
        }

        // Try nested structures
        if (isset($bounce_data['data']['email']) && is_email($bounce_data['data']['email'])) {
            return $bounce_data['data']['email'];
        }

        if (isset($bounce_data['event']['email']) && is_email($bounce_data['event']['email'])) {
            return $bounce_data['event']['email'];
        }

        return null;
    }

    /**
     * Extract bounce reason from bounce data
     *
     * @param array $bounce_data Bounce data from request
     * @return string Bounce reason
     */
    private function extract_bounce_reason($bounce_data) {
        $reason_keys = array('reason', 'bounce_reason', 'error', 'message', 'description');
        
        foreach ($reason_keys as $key) {
            if (isset($bounce_data[$key]) && !empty($bounce_data[$key])) {
                return $bounce_data[$key];
            }
        }

        return 'Unknown bounce reason';
    }

    /**
     * Extract bounce type from bounce data
     *
     * @param array $bounce_data Bounce data from request
     * @return string Bounce type
     */
    private function extract_bounce_type($bounce_data) {
        // Check for specific bounce types
        if (isset($bounce_data['type'])) {
            return $bounce_data['type'];
        }

        if (isset($bounce_data['event_type'])) {
            return $bounce_data['event_type'];
        }

        if (isset($bounce_data['status'])) {
            $status = strtolower($bounce_data['status']);
            if (strpos($status, 'bounce') !== false) {
                return 'email.bounced';
            }
            if (strpos($status, 'complaint') !== false) {
                return 'email.complained';
            }
            if (strpos($status, 'unsubscribe') !== false) {
                return 'email.unsubscribed';
            }
        }

        // Default to bounce
        return 'email.bounced';
    }

    /**
     * Get FluentCRM bounce handler URL
     *
     * @return string|null Bounce handler URL or null if not available
     */
    private function get_fluentcrm_bounce_handler_url() {
        if (!$this->is_fluentcrm_available()) {
            return null;
        }

        $security_code = fluentcrm_get_option('_fc_bounce_key');
        if (!$security_code) {
            return null;
        }

        return get_rest_url(null, 'fluent-crm/v2/public/bounce_handler/emailit/handle/' . $security_code);
    }

    /**
     * Reset FluentCRM settings to default values
     * This can be called via AJAX or directly
     */
    public function reset_fluentcrm_settings() {
        if (!current_user_can('manage_options')) {
            return array('success' => false, 'message' => 'Insufficient permissions');
        }

        if (!$this->is_fluentcrm_available()) {
            return array('success' => false, 'message' => 'FluentCRM is not available');
        }

        $fluentcrm_defaults = array(
            'emailit_fluentcrm_integration' => 1,
            'emailit_fluentcrm_forward_bounces' => 1,
            'emailit_fluentcrm_suppress_default' => 0,
            'emailit_fluentcrm_hard_bounce_action' => 'unsubscribe',
            'emailit_fluentcrm_soft_bounce_action' => 'track',
            'emailit_fluentcrm_soft_bounce_threshold' => 5,
            'emailit_fluentcrm_complaint_action' => 'unsubscribe',
            'emailit_fluentcrm_enable_action_mapping' => 1,
            'emailit_fluentcrm_auto_create_subscribers' => 1,
            'emailit_fluentcrm_confidence_threshold' => 70,
            'emailit_fluentcrm_soft_bounce_window' => 7,
            'emailit_fluentcrm_soft_bounce_reset_on_success' => 1,
            'emailit_fluentcrm_soft_bounce_history_limit' => 10,
        );

        $updated_count = 0;
        $errors = array();

        foreach ($fluentcrm_defaults as $option => $default_value) {
            $current_value = get_option($option, 'NOT_SET');
            
            if ($current_value === 'NOT_SET') {
                // Option doesn't exist, add it
                $result = add_option($option, $default_value);
                if ($result) {
                    $updated_count++;
                } else {
                    $errors[] = "Failed to add {$option}";
                }
            } else {
                // Option exists, update it
                $result = update_option($option, $default_value);
                if ($result || $current_value == $default_value) {
                    $updated_count++;
                } else {
                    $errors[] = "Failed to update {$option}";
                }
            }
        }

        return array(
            'success' => empty($errors),
            'updated_count' => $updated_count,
            'errors' => $errors,
            'message' => empty($errors) ? 'All settings reset successfully' : 'Some settings failed to update'
        );
    }

    /**
     * AJAX handler for auto-selecting Emailit as bounce handler
     */
    public function ajax_auto_select_bounce_handler() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'emailit_admin_nonce')) {
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $result = $this->auto_select_emailit_bounce_handler();
        
        wp_send_json($result);
    }

    /**
     * Handle FluentCRM forward bounces setting change
     */
    public function handle_forward_bounces_setting_change($old_value, $new_value) {
        // Only proceed if the new value is 1 (enabled) and FluentCRM is available
        if ($new_value == 1 && $this->is_fluentcrm_available()) {
            // Get current FluentCRM settings
            $fluentcrm_settings = get_option('fluentcrm-global-settings', array());
            
            // Check if Emailit is already selected
            $current_provider = isset($fluentcrm_settings['bounce_handler_provider']) ? $fluentcrm_settings['bounce_handler_provider'] : '';
            
            if ($current_provider !== 'emailit') {
                // Auto-select Emailit as bounce handler
                $fluentcrm_settings['bounce_handler_provider'] = 'emailit';
                
                // Save the updated settings
                $result = update_option('fluentcrm-global-settings', $fluentcrm_settings);
                
                // Log the auto-selection
                if ($result && defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[Emailit] Auto-selected Emailit as FluentCRM bounce handler (Forward bounces enabled)');
                }
            }
        }
    }


    /**
     * AJAX handler for resetting FluentCRM settings
     */
    public function ajax_reset_fluentcrm_settings() {
        check_ajax_referer('emailit_reset_fluentcrm_nonce', 'nonce');
        
        $result = $this->reset_fluentcrm_settings();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
}
