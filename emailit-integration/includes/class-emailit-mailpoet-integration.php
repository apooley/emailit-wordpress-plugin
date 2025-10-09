<?php
/**
 * Emailit MailPoet Integration Class
 *
 * Main integration class that manages all MailPoet-specific functionality.
 * Handles initialization, method registration, and coordination between Emailit and MailPoet.
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

class Emailit_MailPoet_Integration {

    /**
     * Logger instance
     */
    private $logger;

    /**
     * MailPoet handler instance
     */
    private $handler;

    /**
     * Subscriber sync instance
     */
    private $subscriber_sync;

    /**
     * Integration status
     */
    private $is_available = false;

    /**
     * Constructor
     */
    public function __construct($logger = null) {
        $this->logger = $logger ?: emailit_get_component('logger');
        
        // Only check availability if logger is available
        if ($this->logger) {
            $this->is_available = $this->check_mailpoet_availability();
        } else {
            // Fallback: check availability without logging
            $this->is_available = $this->check_mailpoet_availability_silent();
        }
    }

    /**
     * Initialize MailPoet integration
     */
    public function init() {
        if (!$this->is_available) {
            return;
        }

        // Initialize components
        $this->init_components();

        // Register hooks
        $this->register_hooks();

        // Log successful initialization
        if ($this->logger) {
            $this->logger->log('MailPoet integration initialized successfully', Emailit_Logger::LEVEL_INFO);
        }
    }

    /**
     * Check if MailPoet is available and compatible
     */
    private function check_mailpoet_availability() {
        // Check if MailPoet is active
        if (!class_exists('MailPoet\Mailer\MailerFactory')) {
            return false;
        }

        // Check MailPoet version compatibility
        $mailpoet_version = $this->get_mailpoet_version();
        if (!$mailpoet_version || version_compare($mailpoet_version, '5.0.0', '<')) {
            if ($this->logger) {
                $this->logger->log('MailPoet version too old for integration', Emailit_Logger::LEVEL_WARNING, array(
                    'version' => $mailpoet_version,
                    'required' => '5.0.0'
                ));
            }
            return false;
        }

        // Check required classes and functions
        $required_classes = array(
            'MailPoet\Mailer\Methods\MailerMethod',
            'MailPoet\Mailer\MailerError',
            'MailPoet\Settings\SettingsController',
            'MailPoet\Subscribers\SubscribersRepository'
        );

        foreach ($required_classes as $class) {
            if (!class_exists($class)) {
                if ($this->logger) {
                    $this->logger->log('MailPoet class not found', Emailit_Logger::LEVEL_WARNING, array(
                        'class' => $class,
                        'version' => $mailpoet_version
                    ));
                }
                return false;
            }
        }

        return true;
    }

    /**
     * Check MailPoet availability without logging (for constructor use)
     */
    private function check_mailpoet_availability_silent() {
        // Check if MailPoet is active
        if (!class_exists('MailPoet\Mailer\MailerFactory')) {
            return false;
        }

        // Check MailPoet version compatibility
        $mailpoet_version = $this->get_mailpoet_version();
        if (!$mailpoet_version || version_compare($mailpoet_version, '5.0.0', '<')) {
            return false;
        }

        // Check required classes and functions
        $required_classes = array(
            'MailPoet\Mailer\Methods\MailerMethod',
            'MailPoet\Mailer\MailerError',
            'MailPoet\Settings\SettingsController',
            'MailPoet\Subscribers\SubscribersRepository'
        );

        foreach ($required_classes as $class) {
            if (!class_exists($class)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get MailPoet version
     */
    private function get_mailpoet_version() {
        if (function_exists('get_plugin_data')) {
            $plugin_file = WP_PLUGIN_DIR . '/mailpoet/mailpoet.php';
            if (file_exists($plugin_file)) {
                $plugin_data = get_plugin_data($plugin_file);
                return $plugin_data['Version'] ?? null;
            }
        }
        return null;
    }

    /**
     * Initialize integration components
     */
    private function init_components() {
        // Initialize handler
        $this->handler = new Emailit_MailPoet_Handler($this->logger);
        $this->handler->init();

        // Initialize subscriber sync if enabled
        if (get_option('emailit_mailpoet_sync_bounces', 1)) {
            $this->subscriber_sync = new Emailit_MailPoet_Subscriber_Sync($this->logger);
            $this->subscriber_sync->init();
        }

        // Initialize MailPoet takeover if enabled
        if (get_option('emailit_mailpoet_override_transactional', 1)) {
            require_once EMAILIT_PLUGIN_DIR . 'includes/class-emailit-mailpoet-takeover.php';
            $takeover = new Emailit_MailPoet_Takeover($this->logger);
            $takeover->init();
        }
    }

    /**
     * Register WordPress hooks
     */
    private function register_hooks() {
        // Register Emailit as MailPoet sending method if enabled
        if (get_option('emailit_mailpoet_register_method', 0)) {
            $this->register_mailpoet_sending_method();
        }

        // Hook into Emailit webhook events for subscriber sync
        if (get_option('emailit_mailpoet_sync_bounces', 1)) {
            add_action('emailit_webhook_received', array($this, 'handle_webhook_event'), 10, 2);
        }

        // Add admin notices for integration status
        add_action('admin_notices', array($this, 'admin_notices'));
    }

    /**
     * Register Emailit as a MailPoet sending method
     */
    private function register_mailpoet_sending_method() {
        // MailPoet does not provide a public filter for custom sending methods
        // This is a limitation of MailPoet's current architecture
        if ($this->logger) {
            $this->logger->log('MailPoet does not provide a public filter for custom sending methods', Emailit_Logger::LEVEL_INFO);
        }
        
        // We can still provide the integration for other features like:
        // - Bounce synchronization
        // - Subscriber management
        // - Transactional email override via phpmailer interception
    }

    /**
     * Add Emailit to MailPoet's sending methods list
     */
    public function add_emailit_sending_method($methods) {
        $methods['Emailit'] = array(
            'name' => 'Emailit',
            'description' => __('Send emails via Emailit API service', 'emailit-integration'),
            'class' => 'Emailit_MailPoet_Method'
        );
        return $methods;
    }


    /**
     * Handle webhook events for subscriber synchronization
     */
    public function handle_webhook_event($webhook_data, $event_type) {
        if (!$this->subscriber_sync) {
            return;
        }

        // Only handle bounce and complaint events
        if (!in_array($event_type, array('bounce', 'complaint', 'delivery'))) {
            return;
        }

        $this->subscriber_sync->handle_webhook_event($webhook_data, $event_type);
    }

    /**
     * Display admin notices for integration status
     */
    public function admin_notices() {
        // Only show on Emailit admin pages
        $current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
        if (empty($current_page) || strpos($current_page, 'emailit') === false) {
            return;
        }

        if (!$this->is_available) {
            echo '<div class="notice notice-warning"><p>';
            _e('MailPoet integration is not available. Please ensure MailPoet is installed and up to date.', 'emailit-integration');
            echo '</p></div>';
            return;
        }

        // Show integration status
        $integration_enabled = get_option('emailit_mailpoet_integration', 0);
        $bounce_sync = get_option('emailit_mailpoet_sync_bounces', 1);
        $transactional_override = get_option('emailit_mailpoet_override_transactional', 1);

        if ($integration_enabled) {
            echo '<div class="notice notice-success"><p>';
            printf(
                __('MailPoet integration is active. Bounce sync: %s, Transactional override: %s', 'emailit-integration'),
                $bounce_sync ? __('Enabled', 'emailit-integration') : __('Disabled', 'emailit-integration'),
                $transactional_override ? __('Enabled', 'emailit-integration') : __('Disabled', 'emailit-integration')
            );
            echo '</p></div>';
            
            // Note about sending method limitation
            echo '<div class="notice notice-info"><p>';
            _e('Note: MailPoet does not currently provide a public API for custom sending methods. The integration focuses on bounce synchronization and transactional email handling.', 'emailit-integration');
            echo '</p></div>';
        }
    }

    /**
     * Get integration status
     */
    public function get_status() {
        return array(
            'available' => $this->is_available,
            'version' => $this->get_mailpoet_version(),
            'integration_enabled' => get_option('emailit_mailpoet_integration', 0),
            'method_registered' => get_option('emailit_mailpoet_register_method', 0),
            'bounce_sync' => get_option('emailit_mailpoet_sync_bounces', 1),
            'transactional_override' => get_option('emailit_mailpoet_override_transactional', 1)
        );
    }

    /**
     * Test MailPoet integration
     */
    public function test_integration() {
        if (!$this->is_available) {
            return new WP_Error('mailpoet_not_available', __('MailPoet is not available or compatible', 'emailit-integration'));
        }

        try {
            // Test MailPoet's settings access with better error handling
            if (!class_exists('MailPoet\DI\ContainerWrapper')) {
                return new WP_Error('mailpoet_container_unavailable', __('MailPoet container is not available', 'emailit-integration'));
            }
            
            $container = \MailPoet\DI\ContainerWrapper::getInstance();
            if (!$container) {
                return new WP_Error('mailpoet_container_failed', __('Failed to get MailPoet container instance', 'emailit-integration'));
            }
            
            $settings = \MailPoet\Settings\SettingsController::getInstance();
            $sender = $settings->get('sender', array());
            
            if (empty($sender['address'])) {
                return new WP_Error('mailpoet_not_configured', __('MailPoet sender email is not configured', 'emailit-integration'));
            }

            // Test subscriber repository access
            $subscribers_repo = $container->get(\MailPoet\Subscribers\SubscribersRepository::class);
            
            return array(
                'success' => true,
                'message' => __('MailPoet integration test successful', 'emailit-integration'),
                'sender' => $sender['address'],
                'subscriber_count' => $subscribers_repo->countBy(array())
            );

        } catch (Exception $e) {
            return new WP_Error('mailpoet_test_failed', $e->getMessage());
        }
    }

    /**
     * Get MailPoet handler instance
     */
    public function get_handler() {
        return $this->handler;
    }

    /**
     * Get subscriber sync instance
     */
    public function get_subscriber_sync() {
        return $this->subscriber_sync;
    }

    /**
     * Check if integration is available
     */
    public function is_available() {
        return $this->is_available;
    }
}
