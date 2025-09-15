<?php
/**
 * Plugin Name: Emailit Integration
 * Plugin URI: https://github.com/apooley/emailit-integration
 * Description: Integrates WordPress with Emailit email service, replacing wp_mail() with API-based email sending, logging, and webhook status updates.
 * Version: 2.6.0
 * Author: Allen Pooley
 * Author URI: https://allenpooley.ca
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: emailit-integration
 * Domain Path: /languages
 * Requires at least: 5.7
 * Tested up to: 6.4
 * Requires PHP: 8.0
 * Network: false
 */

// Security check - Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('EMAILIT_VERSION', '2.6.0');
define('EMAILIT_PLUGIN_FILE', __FILE__);
define('EMAILIT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('EMAILIT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('EMAILIT_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('EMAILIT_API_ENDPOINT', 'https://api.emailit.com/v1/emails');
define('EMAILIT_WEBHOOK_ENDPOINT', 'emailit/v1/webhook');

/**
 * Main Emailit Integration Plugin Class
 */
class Emailit_Integration {

    /**
     * Plugin instance
     */
    private static $instance = null;

    /**
     * Plugin components
     */
    private $api = null;
    private $mailer = null;
    private $logger = null;
    private $queue = null;
    private $webhook = null;
    private $admin = null;
    private $db_optimizer = null;
    private $query_optimizer = null;
    private $fluentcrm_handler = null;
    private $health_monitor = null;

    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - Private to prevent direct instantiation
     */
    private function __construct() {
        $this->define_hooks();
    }

    /**
     * Define WordPress hooks
     */
    private function define_hooks() {
        // Plugin lifecycle hooks
        register_activation_hook(EMAILIT_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(EMAILIT_PLUGIN_FILE, array($this, 'deactivate'));

        // Initialize plugin
        add_action('plugins_loaded', array($this, 'init'));

        // Load text domain
        add_action('init', array($this, 'load_textdomain'));
    }

    /**
     * Initialize plugin components
     */
    public function init() {
        // Check if WordPress and PHP versions are compatible
        if (!$this->is_compatible()) {
            add_action('admin_notices', array($this, 'compatibility_notice'));
            return;
        }

        // Load required files
        $this->load_dependencies();

        // Initialize components
        $this->init_components();

        // Hook into WordPress
        $this->setup_hooks();

        // Plugin is fully loaded
        do_action('emailit_loaded');
    }

    /**
     * Check system compatibility
     */
    private function is_compatible() {
        global $wp_version;

        // Check WordPress version
        if (version_compare($wp_version, '5.7', '<')) {
            return false;
        }

        // Check PHP version
        if (version_compare(PHP_VERSION, '8.0', '<')) {
            return false;
        }

        return true;
    }

    /**
     * Display compatibility notice
     */
    public function compatibility_notice() {
        global $wp_version;

        $message = sprintf(
            __('Emailit Integration requires WordPress 5.7+ and PHP 8.0+. You are running WordPress %s and PHP %s.', 'emailit-integration'),
            $wp_version,
            PHP_VERSION
        );

        printf('<div class="notice notice-error"><p>%s</p></div>', esc_html($message));
    }

    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Autoload classes
        spl_autoload_register(array($this, 'autoload'));

        // Load core classes
        require_once EMAILIT_PLUGIN_DIR . 'includes/class-emailit-api.php';
        require_once EMAILIT_PLUGIN_DIR . 'includes/class-emailit-logger.php';
        require_once EMAILIT_PLUGIN_DIR . 'includes/class-emailit-queue.php';
        require_once EMAILIT_PLUGIN_DIR . 'includes/class-emailit-mailer.php';
        require_once EMAILIT_PLUGIN_DIR . 'includes/class-emailit-webhook.php';
        require_once EMAILIT_PLUGIN_DIR . 'includes/class-emailit-health-monitor.php';
        require_once EMAILIT_PLUGIN_DIR . 'includes/class-emailit-alert-manager.php';
        require_once EMAILIT_PLUGIN_DIR . 'includes/class-emailit-metrics-collector.php';
        require_once EMAILIT_PLUGIN_DIR . 'includes/class-emailit-health-migration.php';
        
        // Advanced error handling components
        require_once EMAILIT_PLUGIN_DIR . 'includes/class-emailit-error-analytics.php';
        require_once EMAILIT_PLUGIN_DIR . 'includes/class-emailit-retry-manager.php';
        require_once EMAILIT_PLUGIN_DIR . 'includes/class-emailit-error-notifications.php';
        require_once EMAILIT_PLUGIN_DIR . 'includes/class-emailit-error-migration.php';

        // Load admin classes if in admin
        if (is_admin()) {
            require_once EMAILIT_PLUGIN_DIR . 'includes/class-emailit-admin.php';
        }
    }

    /**
     * Autoload classes
     */
    public function autoload($class) {
        // Only autoload our plugin classes
        if (strpos($class, 'Emailit_') !== 0) {
            return;
        }

        // Convert class name to file name
        $file = strtolower(str_replace('_', '-', $class));
        $file = EMAILIT_PLUGIN_DIR . 'includes/class-' . $file . '.php';

        if (file_exists($file)) {
            require_once $file;
        }
    }

    /**
     * Initialize plugin components
     */
    private function init_components() {
        // Initialize logger first (other components may need it)
        $this->logger = new Emailit_Logger();

        // Initialize queue system
        $this->queue = new Emailit_Queue($this->logger);

        // Initialize API handler
        $this->api = new Emailit_API($this->logger);

        // Initialize mailer (wp_mail override)
        $this->mailer = new Emailit_Mailer($this->api, $this->logger, $this->queue);

        // Initialize webhook handler
        $this->webhook = new Emailit_Webhook($this->logger);

        // Initialize FluentCRM handler (only if FluentCRM is available)
        if (class_exists('FluentCrm\App\App')) {
            $this->fluentcrm_handler = new Emailit_FluentCRM_Handler($this->logger);
        }

        // Always initialize admin interface (it will handle admin-only functionality internally)
        $this->admin = new Emailit_Admin($this->api, $this->logger, $this->queue);

        // Initialize database optimizer
        $this->db_optimizer = new Emailit_Database_Optimizer();
        
        // Initialize query optimizer
        $this->query_optimizer = new Emailit_Query_Optimizer();
        
        // Initialize health monitor
        $this->health_monitor = new Emailit_Health_Monitor($this->logger);

        // Initialize advanced error handling
        $this->init_advanced_error_handling();
    }

    /**
     * Initialize advanced error handling components
     */
    private function init_advanced_error_handling() {
        // Create error handling tables
        Emailit_Error_Migration::create_tables();
        
        // Schedule error analysis
        if (!wp_next_scheduled('emailit_error_analysis')) {
            wp_schedule_event(time(), 'hourly', 'emailit_error_analysis');
        }
        
        // Schedule retry cleanup
        if (!wp_next_scheduled('emailit_retry_cleanup')) {
            wp_schedule_event(time(), 'daily', 'emailit_retry_cleanup');
        }
        
        // Schedule error data cleanup
        if (!wp_next_scheduled('emailit_error_cleanup')) {
            wp_schedule_event(time(), 'daily', 'emailit_error_cleanup');
        }
        
        // Hook into cleanup events
        add_action('emailit_retry_cleanup', array($this, 'cleanup_retry_data'));
        add_action('emailit_error_cleanup', array($this, 'cleanup_error_data'));
    }

    /**
     * Cleanup retry data
     */
    public function cleanup_retry_data() {
        if (class_exists('Emailit_Retry_Manager')) {
            $retry_manager = new Emailit_Retry_Manager($this->logger);
            $retry_manager->cleanup_old_retries();
        }
    }

    /**
     * Cleanup error data
     */
    public function cleanup_error_data() {
        Emailit_Error_Migration::cleanup_old_data();
    }

    /**
     * Setup WordPress hooks
     */
    private function setup_hooks() {
        // Admin init - let the admin class handle is_admin() checks internally
        add_action('admin_init', array($this->admin, 'init'));
        add_action('admin_menu', array($this->admin, 'add_menu_pages'));

        // REST API init - only register webhook routes if webhooks are enabled
        if (get_option('emailit_enable_webhooks', 1)) {
            add_action('rest_api_init', array($this->webhook, 'register_routes'));
        }

        // Enqueue scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Add custom cron intervals for health monitoring
        add_filter('cron_schedules', array($this, 'add_cron_intervals'));
    }

    /**
     * Add custom cron intervals for health monitoring
     */
    public function add_cron_intervals($schedules) {
        $schedules['emailit_5min'] = array(
            'interval' => 300, // 5 minutes
            'display' => __('Every 5 Minutes (Emailit)', 'emailit-integration')
        );
        
        $schedules['emailit_10min'] = array(
            'interval' => 600, // 10 minutes
            'display' => __('Every 10 Minutes (Emailit)', 'emailit-integration')
        );
        
        $schedules['emailit_15min'] = array(
            'interval' => 900, // 15 minutes
            'display' => __('Every 15 Minutes (Emailit)', 'emailit-integration')
        );
        
        $schedules['emailit_30min'] = array(
            'interval' => 1800, // 30 minutes
            'display' => __('Every 30 Minutes (Emailit)', 'emailit-integration')
        );
        
        return $schedules;
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'emailit') === false) {
            return;
        }

        wp_enqueue_style(
            'emailit-admin',
            EMAILIT_PLUGIN_URL . 'admin/assets/css/admin.css',
            array(),
            EMAILIT_VERSION
        );

        wp_enqueue_script(
            'emailit-admin',
            EMAILIT_PLUGIN_URL . 'admin/assets/js/admin.js',
            array('jquery'),
            EMAILIT_VERSION,
            true
        );

        // Localize script
        wp_localize_script('emailit-admin', 'emailit_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('emailit_admin_nonce'),
            'strings' => array(
                'sending' => __('Sending...', 'emailit-integration'),
                'sent' => __('Test email sent successfully!', 'emailit-integration'),
                'error' => __('Error sending test email. Check the logs for details.', 'emailit-integration'),
                'confirm_delete' => __('Are you sure you want to delete this email log? This action cannot be undone.', 'emailit-integration'),
                'admin_email' => get_option('admin_email', '')
            )
        ));
    }

    /**
     * Load text domain for translations
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'emailit-integration',
            false,
            dirname(EMAILIT_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Load dependencies first
        $this->load_dependencies();

        // Create database tables
        $this->create_tables();

        // Run database migrations
        $this->run_database_migrations();
        
        // Create health monitoring tables
        Emailit_Health_Migration::create_tables();
        
        // Create error handling tables
        Emailit_Error_Migration::create_tables();

        // Set default options
        $this->set_default_options();

        // Flush rewrite rules for REST API endpoints
        flush_rewrite_rules();

        // Log activation
        if (class_exists('Emailit_Logger')) {
            $logger = new Emailit_Logger();
            $logger->log('Plugin activated', 'info');
        }

        // Hook for other plugins
        do_action('emailit_activated');
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('emailit_process_queue');
        wp_clear_scheduled_hook('emailit_cleanup_logs');

        // Flush rewrite rules
        flush_rewrite_rules();

        // Log deactivation
        if (class_exists('Emailit_Logger')) {
            $logger = new Emailit_Logger();
            $logger->log('Plugin deactivated', 'info');
        }

        // Hook for other plugins
        do_action('emailit_deactivated');
    }

    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Initialize queue to create its table
        $queue = new Emailit_Queue();
        $queue->create_table();

        // Email logs table
        $table_logs = $wpdb->prefix . 'emailit_logs';
        $sql_logs = "CREATE TABLE $table_logs (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            email_id varchar(255) DEFAULT NULL,
            token varchar(255) DEFAULT NULL,
            message_id varchar(255) DEFAULT NULL,
            queue_id bigint(20) UNSIGNED DEFAULT NULL,
            to_email text NOT NULL,
            from_email varchar(255) NOT NULL,
            reply_to varchar(255) DEFAULT NULL,
            subject text NOT NULL,
            body_html longtext,
            body_text longtext,
            status varchar(50) DEFAULT 'pending',
            details text,
            sent_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_email_id (email_id),
            KEY idx_token (token),
            KEY idx_status (status),
            KEY idx_queue_id (queue_id),
            KEY idx_created_at (created_at)
        ) $charset_collate;";

        // Webhook logs table
        $table_webhooks = $wpdb->prefix . 'emailit_webhook_logs';
        $sql_webhooks = "CREATE TABLE $table_webhooks (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            webhook_request_id varchar(255) DEFAULT NULL,
            event_id varchar(255) DEFAULT NULL,
            event_type varchar(100) DEFAULT NULL,
            email_id varchar(255) DEFAULT NULL,
            status varchar(50) DEFAULT NULL,
            details text,
            raw_payload longtext,
            processed_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_email_id (email_id),
            KEY idx_event_type (event_type),
            KEY idx_event_id (event_id),
            KEY idx_status (status),
            KEY idx_processed_at (processed_at),
            KEY idx_webhook_request_id (webhook_request_id),
            KEY idx_email_id_event_type (email_id, event_type)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql_logs);
        dbDelta($sql_webhooks);

        // Store database version
        update_option('emailit_db_version', '1.0.0');
    }

    /**
     * Run database migrations
     */
    private function run_database_migrations() {
        $current_db_version = get_option('emailit_db_version', '1.0.0');

        // Migration for version 2.0.0 - Add queue_id to email logs
        if (version_compare($current_db_version, '2.0.0', '<')) {
            $this->migrate_to_2_0_0();
            update_option('emailit_db_version', '2.0.0');
        }

        // Migration for version 2.1.0 - Add performance indexes
        if (version_compare($current_db_version, '2.1.0', '<')) {
            $this->migrate_to_2_1_0();
            update_option('emailit_db_version', '2.1.0');
        }

        // Migration for version 2.5.0 - Add bounce classification columns
        if (version_compare($current_db_version, '2.5.0', '<')) {
            $this->migrate_to_2_5_0();
            update_option('emailit_db_version', '2.5.0');
        }
    }

    /**
     * Migrate database to version 2.0.0
     */
    private function migrate_to_2_0_0() {
        global $wpdb;

        $logs_table = $wpdb->prefix . 'emailit_logs';

        // Check if queue_id column exists
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM `{$logs_table}` LIKE %s",
            'queue_id'
        ));

        if (empty($column_exists)) {
            // Add queue_id column
            $wpdb->query("ALTER TABLE `{$logs_table}` ADD COLUMN `queue_id` bigint(20) UNSIGNED DEFAULT NULL AFTER `message_id`");
            $wpdb->query("ALTER TABLE `{$logs_table}` ADD INDEX `idx_queue_id` (`queue_id`)");

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Emailit] Added queue_id column to email logs table');
            }
        }
    }

    /**
     * Migrate database to version 2.1.0 - Add performance indexes
     */
    private function migrate_to_2_1_0() {
        // Use database optimizer to add indexes
        $db_optimizer = new Emailit_Database_Optimizer();
        $indexes_added = $db_optimizer->add_performance_indexes();
        
        if (!empty($indexes_added)) {
            error_log('[Emailit] Added performance indexes: ' . implode(', ', $indexes_added));
        }
    }

    /**
     * Migrate database to version 2.5.0 - Add bounce classification columns
     */
    private function migrate_to_2_5_0() {
        global $wpdb;

        $logs_table = $wpdb->prefix . 'emailit_logs';

        // Check if bounce_classification column exists
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM `{$logs_table}` LIKE %s",
            'bounce_classification'
        ));

        if (empty($column_exists)) {
            // Add bounce classification columns
            $wpdb->query("ALTER TABLE `{$logs_table}` ADD COLUMN `bounce_classification` VARCHAR(50) DEFAULT NULL AFTER `status`");
            $wpdb->query("ALTER TABLE `{$logs_table}` ADD COLUMN `bounce_category` VARCHAR(50) DEFAULT NULL AFTER `bounce_classification`");
            $wpdb->query("ALTER TABLE `{$logs_table}` ADD COLUMN `bounce_severity` VARCHAR(20) DEFAULT NULL AFTER `bounce_category`");
            $wpdb->query("ALTER TABLE `{$logs_table}` ADD COLUMN `bounce_confidence` TINYINT(3) UNSIGNED DEFAULT NULL AFTER `bounce_severity`");
            
            // Add indexes for bounce classification
            $wpdb->query("ALTER TABLE `{$logs_table}` ADD INDEX `idx_bounce_classification` (`bounce_classification`)");
            $wpdb->query("ALTER TABLE `{$logs_table}` ADD INDEX `idx_bounce_category` (`bounce_category`)");
            $wpdb->query("ALTER TABLE `{$logs_table}` ADD INDEX `idx_bounce_severity` (`bounce_severity`)");

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Emailit] Added bounce classification columns to email logs table');
            }
        }
    }

    /**
     * Set default plugin options
     */
    private function set_default_options() {
        $defaults = array(
            'emailit_api_key' => '',
            'emailit_from_name' => get_bloginfo('name'),
            'emailit_from_email' => get_option('admin_email'),
            'emailit_reply_to' => '',
            'emailit_enable_logging' => 1,
            'emailit_log_retention_days' => 30,
            'emailit_fallback_enabled' => 1,
            'emailit_timeout' => 30,
            'emailit_retry_attempts' => 3,
            'emailit_enable_queue' => 0,
            'emailit_queue_batch_size' => 10,
            'emailit_queue_max_retries' => 3,
            'emailit_webhook_secret' => '',
            // FluentCRM Integration Options
            'emailit_fluentcrm_integration' => 1,
            'emailit_fluentcrm_forward_bounces' => 1,
            'emailit_fluentcrm_suppress_default' => 0,
            'emailit_fluentcrm_hard_bounce_action' => 'unsubscribe',
            'emailit_fluentcrm_soft_bounce_action' => 'track',
            'emailit_fluentcrm_soft_bounce_threshold' => 5,
            'emailit_fluentcrm_complaint_action' => 'unsubscribe',
        );

        foreach ($defaults as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
            }
        }
    }

    /**
     * Get plugin component
     */
    public function get_component($component) {
        if (property_exists($this, $component)) {
            return $this->$component;
        }
        return null;
    }
}

/**
 * Get plugin instance
 */
function emailit_get_plugin() {
    return Emailit_Integration::get_instance();
}

/**
 * Get plugin component
 */
function emailit_get_component($component) {
    $plugin = emailit_get_plugin();
    return $plugin->get_component($component);
}

/**
 * Send email via Emailit API
 */
function emailit_send($to, $subject, $message, $headers = '', $attachments = array()) {
    $mailer = emailit_get_component('mailer');
    if ($mailer) {
        return $mailer->send($to, $subject, $message, $headers, $attachments);
    }
    return false;
}

/**
 * Log message
 */
function emailit_log($message, $level = 'info', $context = array()) {
    $logger = emailit_get_component('logger');
    if ($logger) {
        return $logger->log($message, $level, $context);
    }
}

// Initialize plugin
Emailit_Integration::get_instance();