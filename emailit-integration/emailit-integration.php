<?php
/**
 * Plugin Name: Emailit Integration
 * Plugin URI: https://github.com/apooley/emailit-integration
 * Description: Integrates WordPress with Emailit email service, replacing wp_mail() with API-based email sending, logging, and webhook status updates.
 * Version: 3.0.4
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
define('EMAILIT_VERSION', '3.0.4');
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
    private $webhook_monitor = null;

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

        // Always load admin class (needed for FluentCRM integration filters)
        require_once EMAILIT_PLUGIN_DIR . 'includes/class-emailit-admin.php';
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
        
        // Register FluentCRM integration filters immediately after admin class instantiation
        $this->register_fluentcrm_filters();

        // Initialize database optimizer
        $this->db_optimizer = new Emailit_Database_Optimizer();
        
        // Initialize query optimizer
        $this->query_optimizer = new Emailit_Query_Optimizer();
        
        // Initialize health monitor
        $this->health_monitor = new Emailit_Health_Monitor($this->logger);
        
        // Initialize webhook monitoring
        $this->webhook_monitor = new Emailit_Webhook_Monitor($this->logger);

        // Initialize advanced error handling
        $this->init_advanced_error_handling();
        
        // Add bounce classification columns if needed
        $this->add_bounce_classification_columns();
    }

    /**
     * Register FluentCRM integration filters
     */
    private function register_fluentcrm_filters() {
        // Only register if FluentCRM is available
        if (!class_exists('FluentCrm\App\App')) {
            return;
        }
        
        // Register FluentCRM bounce handler integration filters
        add_filter('fluent_crm/bounce_handlers', array($this->admin, 'register_fluentcrm_bounce_handler'), 20, 2);
        add_filter('fluent_crm_handle_bounce_emailit', array($this->admin, 'handle_fluentcrm_bounce'), 10, 3);
        
        // Auto-select Emailit as bounce handler when integration is active
        add_action('init', array($this, 'auto_select_emailit_bounce_handler'), 20);
    }

    /**
     * Auto-select Emailit as FluentCRM bounce handler
     */
    public function auto_select_emailit_bounce_handler() {
        // Only run if FluentCRM is available and integration is enabled
        if (!class_exists('FluentCrm\App\App') || !get_option('emailit_fluentcrm_integration', 1)) {
            return;
        }
        
        // Only run in admin context to avoid unnecessary processing
        if (!is_admin()) {
            return;
        }
        
        // Only auto-select if "Forward bounce data to Emailit API" is enabled
        if (!get_option('emailit_fluentcrm_forward_bounces', 1)) {
            return;
        }
        
        // Get current FluentCRM settings
        $fluentcrm_settings = get_option('fluentcrm-global-settings', array());
        
        // Check if Emailit is already selected
        $current_provider = isset($fluentcrm_settings['bounce_handler_provider']) ? $fluentcrm_settings['bounce_handler_provider'] : '';
        
        if ($current_provider === 'emailit') {
            return; // Already selected
        }
        
        // Auto-select Emailit
        $fluentcrm_settings['bounce_handler_provider'] = 'emailit';
        
        // Save the updated settings
        $result = update_option('fluentcrm-global-settings', $fluentcrm_settings);
        
        // Log the auto-selection
        if ($result && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Emailit] Auto-selected Emailit as FluentCRM bounce handler (Forward bounces enabled)');
        }
    }

    /**
     * Initialize advanced error handling components
     */
    private function init_advanced_error_handling() {
        // Only initialize error handling tables during admin or activation
        if (is_admin() || wp_doing_ajax()) {
            Emailit_Error_Migration::safe_init();
        }
        
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
     * Add bounce classification columns to logs table
     */
    private function add_bounce_classification_columns() {
        global $wpdb;
        
        $logs_table = $wpdb->prefix . 'emailit_logs';
        
        // Check if table exists
        if (!$wpdb->get_var("SHOW TABLES LIKE '{$logs_table}'")) {
            return;
        }
        
        // Check if columns already exist
        $columns = $wpdb->get_col("DESCRIBE {$logs_table}");
        
        $columns_to_add = array(
            'bounce_classification' => 'varchar(50) DEFAULT NULL',
            'bounce_category' => 'varchar(50) DEFAULT NULL', 
            'bounce_severity' => 'varchar(20) DEFAULT NULL',
            'bounce_confidence' => 'decimal(3,2) DEFAULT NULL',
            'bounce_reason' => 'text DEFAULT NULL',
            'bounce_technical_hints' => 'text DEFAULT NULL',
            'bounce_recommended_action' => 'varchar(100) DEFAULT NULL'
        );
        
        foreach ($columns_to_add as $column_name => $column_definition) {
            if (!in_array($column_name, $columns)) {
                $wpdb->query("ALTER TABLE {$logs_table} ADD COLUMN {$column_name} {$column_definition}");
            }
        }
        
        // Add indexes for bounce classification columns
        $indexes_to_add = array(
            'idx_bounce_classification' => 'bounce_classification',
            'idx_bounce_category' => 'bounce_category',
            'idx_bounce_severity' => 'bounce_severity'
        );
        
        foreach ($indexes_to_add as $index_name => $column_name) {
            // Check if index already exists
            $index_exists = $wpdb->get_var($wpdb->prepare(
                "SHOW INDEX FROM {$logs_table} WHERE Key_name = %s",
                $index_name
            ));
            
            if (!$index_exists) {
                $wpdb->query("CREATE INDEX {$index_name} ON {$logs_table} ({$column_name})");
            }
        }
    }

    /**
     * Setup WordPress hooks
     */
    private function setup_hooks() {
        // Admin init - let the admin class handle is_admin() checks internally
        add_action('admin_init', array($this->admin, 'init'));
        add_action('admin_menu', array($this->admin, 'add_menu_pages'));

        // REST API init - register webhook routes (default enabled)
        add_action('rest_api_init', array($this->webhook, 'register_routes'));

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
        
        // Create error handling tables safely
        Emailit_Error_Migration::safe_init();
        
        // Add bounce classification columns to logs table
        $this->add_bounce_classification_columns();

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
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Emailit] Added performance indexes: ' . implode(', ', $indexes_added));
            }
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
            'emailit_enable_webhooks' => 1,
            'emailit_webhook_secret' => '',
            // FluentCRM Integration Options
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
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Emailit DEBUG] Plugin get_component called for: ' . $component);
        }
        
        if (property_exists($this, $component)) {
            $result = $this->$component;
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Emailit DEBUG] Component ' . $component . ' found: ' . (is_object($result) ? get_class($result) : (is_null($result) ? 'null' : gettype($result))));
            }
            return $result;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Emailit DEBUG] Component ' . $component . ' not found');
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
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[Emailit DEBUG] Getting component: ' . $component);
    }
    
    $plugin = emailit_get_plugin();
    if (!$plugin) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Emailit DEBUG] Plugin instance not available');
        }
        return null;
    }
    
    $result = $plugin->get_component($component);
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[Emailit DEBUG] Component ' . $component . ' result: ' . (is_object($result) ? get_class($result) : (is_null($result) ? 'null' : gettype($result))));
    }
    
    return $result;
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