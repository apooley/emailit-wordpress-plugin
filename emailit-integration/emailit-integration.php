<?php
/**
 * Plugin Name: Emailit Integration
 * Plugin URI: https://github.com/your-username/emailit-integration
 * Description: Integrates WordPress with Emailit email service, replacing wp_mail() with API-based email sending, logging, and webhook status updates.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://your-website.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: emailit-integration
 * Domain Path: /languages
 * Requires at least: 5.7
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// Security check - Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('EMAILIT_VERSION', '1.0.0');
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
    private $webhook = null;
    private $admin = null;

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
        if (version_compare(PHP_VERSION, '7.4', '<')) {
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
            __('Emailit Integration requires WordPress 5.7+ and PHP 7.4+. You are running WordPress %s and PHP %s.', 'emailit-integration'),
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
        require_once EMAILIT_PLUGIN_DIR . 'includes/class-emailit-mailer.php';
        require_once EMAILIT_PLUGIN_DIR . 'includes/class-emailit-webhook.php';

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

        // Initialize API handler
        $this->api = new Emailit_API($this->logger);

        // Initialize mailer (wp_mail override)
        $this->mailer = new Emailit_Mailer($this->api, $this->logger);

        // Initialize webhook handler
        $this->webhook = new Emailit_Webhook($this->logger);

        // Initialize admin interface if in admin
        if (is_admin()) {
            $this->admin = new Emailit_Admin($this->api, $this->logger);
        }
    }

    /**
     * Setup WordPress hooks
     */
    private function setup_hooks() {
        // Admin init
        if (is_admin()) {
            add_action('admin_init', array($this->admin, 'init'));
            add_action('admin_menu', array($this->admin, 'add_menu_pages'));
        }

        // REST API init
        add_action('rest_api_init', array($this->webhook, 'register_routes'));

        // Enqueue scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
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
                'confirm_delete' => __('Are you sure you want to delete this log?', 'emailit-integration'),
                'test_email_sent' => __('Test email sent successfully!', 'emailit-integration'),
                'test_email_failed' => __('Test email failed. Please check your settings.', 'emailit-integration'),
            )
        ));
    }

    /**
     * Load plugin text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'emailit-integration',
            false,
            dirname(EMAILIT_PLUGIN_BASENAME) . '/languages/'
        );
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        $this->create_tables();

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

        // Email logs table
        $table_logs = $wpdb->prefix . 'emailit_logs';
        $sql_logs = "CREATE TABLE $table_logs (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            email_id varchar(255) DEFAULT NULL,
            token varchar(255) DEFAULT NULL,
            message_id varchar(255) DEFAULT NULL,
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
            KEY idx_created_at (created_at)
        ) $charset_collate;";

        // Webhook logs table
        $table_webhooks = $wpdb->prefix . 'emailit_webhook_logs';
        $sql_webhooks = "CREATE TABLE $table_webhooks (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            webhook_request_id varchar(255),
            event_id varchar(255),
            event_type varchar(100),
            email_id varchar(255),
            status varchar(50),
            details text,
            raw_payload longtext,
            processed_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_email_id (email_id),
            KEY idx_event_type (event_type)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql_logs);
        dbDelta($sql_webhooks);

        // Store database version
        update_option('emailit_db_version', '1.0.0');
    }

    /**
     * Set default plugin options
     */
    private function set_default_options() {
        $defaults = array(
            'emailit_api_key' => '',
            'emailit_from_name' => get_bloginfo('name'),
            'emailit_from_email' => get_bloginfo('admin_email'),
            'emailit_reply_to' => '',
            'emailit_enable_logging' => 1,
            'emailit_log_retention_days' => 30,
            'emailit_webhook_secret' => wp_generate_password(32, false),
            'emailit_fallback_enabled' => 1,
            'emailit_retry_attempts' => 3,
            'emailit_timeout' => 30,
        );

        foreach ($defaults as $option => $value) {
            if (false === get_option($option)) {
                update_option($option, $value);
            }
        }
    }

    /**
     * Get component instance
     */
    public function get_component($component) {
        switch ($component) {
            case 'api':
                return $this->api;
            case 'logger':
                return $this->logger;
            case 'mailer':
                return $this->mailer;
            case 'webhook':
                return $this->webhook;
            case 'admin':
                return $this->admin;
            default:
                return null;
        }
    }
}

/**
 * Initialize the plugin
 */
function emailit_integration() {
    return Emailit_Integration::get_instance();
}

// Start the plugin
emailit_integration();

/**
 * Helper functions for other plugins/themes
 */

/**
 * Get plugin instance
 */
function emailit() {
    return Emailit_Integration::get_instance();
}

/**
 * Get specific component
 */
function emailit_get_component($component) {
    return emailit()->get_component($component);
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