<?php
/**
 * Emailit Integration - DEBUG VERSION (BACKUP)
 *
 * This is a debug backup version of the plugin with extensive logging.
 * DO NOT activate this version - use emailit-integration.php instead.
 *
 * This file is kept for debugging purposes only and should not be
 * detected as a plugin by WordPress.
 */

// Security check - Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}


// Define plugin constants
define('EMAILIT_VERSION', '1.0.1');
define('EMAILIT_PLUGIN_FILE', __FILE__);
define('EMAILIT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('EMAILIT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('EMAILIT_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('EMAILIT_API_ENDPOINT', 'https://api.emailit.com/v2/emails');
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

    /**
     * Get plugin instance
     */
    public static function get_instance() {
        emailit_debug_log('Getting plugin instance');
        if (null === self::$instance) {
            emailit_debug_log('Creating new plugin instance');
            self::$instance = new self();
            emailit_debug_log('Plugin instance created successfully');
        }
        return self::$instance;
    }

    /**
     * Constructor - Private to prevent direct instantiation
     */
    private function __construct() {
        emailit_debug_log('Plugin constructor called');
        $this->define_hooks();
        emailit_debug_log('Plugin constructor completed');
    }

    /**
     * Define WordPress hooks
     */
    private function define_hooks() {
        emailit_debug_log('Defining WordPress hooks');
        try {
            // Plugin lifecycle hooks
            emailit_debug_log('Registering activation hook');
            register_activation_hook(EMAILIT_PLUGIN_FILE, array($this, 'activate'));
            emailit_debug_log('Registering deactivation hook');
            register_deactivation_hook(EMAILIT_PLUGIN_FILE, array($this, 'deactivate'));

            // Initialize plugin
            emailit_debug_log('Adding plugins_loaded action');
            add_action('plugins_loaded', array($this, 'init'));

            // Load text domain
            emailit_debug_log('Adding init action for textdomain');
            add_action('init', array($this, 'load_textdomain'));

            emailit_debug_log('All hooks defined successfully');
        } catch (Exception $e) {
            emailit_debug_log('ERROR in define_hooks', $e->getMessage());
        }
    }

    /**
     * Initialize plugin components
     */
    public function init() {
        emailit_debug_log('Plugin init() called');
        try {
            // Check if WordPress and PHP versions are compatible
            emailit_debug_log('Checking compatibility');
            if (!$this->is_compatible()) {
                emailit_debug_log('Compatibility check failed');
                add_action('admin_notices', array($this, 'compatibility_notice'));
                return;
            }
            emailit_debug_log('Compatibility check passed');

            // Load required files
            emailit_debug_log('Loading dependencies');
            $this->load_dependencies();
            emailit_debug_log('Dependencies loaded');

            // Initialize components
            emailit_debug_log('Initializing components');
            $this->init_components();
            emailit_debug_log('Components initialized');

            // Hook into WordPress
            emailit_debug_log('Setting up hooks');
            $this->setup_hooks();
            emailit_debug_log('Hooks setup complete');

            // Plugin is fully loaded
            emailit_debug_log('Plugin fully loaded - firing emailit_loaded action');
            do_action('emailit_loaded');
            emailit_debug_log('Plugin init completed successfully');
        } catch (Exception $e) {
            emailit_debug_log('ERROR in init()', $e->getMessage());
        } catch (Error $e) {
            emailit_debug_log('FATAL ERROR in init()', $e->getMessage());
        }
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
        emailit_debug_log('Loading plugin dependencies');
        try {
            // Autoload classes
            emailit_debug_log('Registering autoloader');
            spl_autoload_register(array($this, 'autoload'));

            // Load core classes
            emailit_debug_log('Loading class-emailit-api.php');
            require_once EMAILIT_PLUGIN_DIR . 'includes/class-emailit-api.php';
            emailit_debug_log('Loading class-emailit-logger.php');
            require_once EMAILIT_PLUGIN_DIR . 'includes/class-emailit-logger.php';
            emailit_debug_log('Loading class-emailit-queue.php');
            require_once EMAILIT_PLUGIN_DIR . 'includes/class-emailit-queue.php';
            emailit_debug_log('Loading class-emailit-mailer.php');
            require_once EMAILIT_PLUGIN_DIR . 'includes/class-emailit-mailer.php';
            emailit_debug_log('Loading class-emailit-webhook.php');
            require_once EMAILIT_PLUGIN_DIR . 'includes/class-emailit-webhook.php';

            // Load admin classes if in admin
            if (is_admin()) {
                emailit_debug_log('Loading class-emailit-admin.php');
                require_once EMAILIT_PLUGIN_DIR . 'includes/class-emailit-admin.php';
            }

            emailit_debug_log('All dependencies loaded successfully');
        } catch (Exception $e) {
            emailit_debug_log('ERROR loading dependencies', $e->getMessage());
            throw $e;
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

        // Initialize admin interface if in admin
        if (is_admin()) {
            $this->admin = new Emailit_Admin($this->api, $this->logger, $this->queue);
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
        emailit_debug_log('ACTIVATION: Starting plugin activation');
        try {
            // Load dependencies first
            emailit_debug_log('ACTIVATION: Loading dependencies for activation');
            $this->load_dependencies();

            // Create database tables
            emailit_debug_log('ACTIVATION: Creating database tables');
            $this->create_tables();
            emailit_debug_log('ACTIVATION: Database tables created');

            // Set default options
            emailit_debug_log('ACTIVATION: Setting default options');
            $this->set_default_options();
            emailit_debug_log('ACTIVATION: Default options set');

            // Flush rewrite rules for REST API endpoints
            emailit_debug_log('ACTIVATION: Flushing rewrite rules');
            flush_rewrite_rules();
            emailit_debug_log('ACTIVATION: Rewrite rules flushed');

            // Log activation
            emailit_debug_log('ACTIVATION: Logging activation');
            if (class_exists('Emailit_Logger')) {
                $logger = new Emailit_Logger();
                $logger->log('Plugin activated', 'info');
            }

            // Hook for other plugins
            emailit_debug_log('ACTIVATION: Firing emailit_activated action');
            do_action('emailit_activated');

            emailit_debug_log('ACTIVATION: Plugin activation completed successfully');
        } catch (Exception $e) {
            emailit_debug_log('ACTIVATION ERROR: Exception caught', $e->getMessage());
            throw $e;
        } catch (Error $e) {
            emailit_debug_log('ACTIVATION FATAL ERROR: Error caught', $e->getMessage());
            throw $e;
        }
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

// Initialize plugin
emailit_debug_log('Bottom of file - initializing plugin instance');
try {
    Emailit_Integration::get_instance();
    emailit_debug_log('Plugin instance initialized successfully');
} catch (Exception $e) {
    emailit_debug_log('ERROR initializing plugin instance', $e->getMessage());
} catch (Error $e) {
    emailit_debug_log('FATAL ERROR initializing plugin instance', $e->getMessage());
}