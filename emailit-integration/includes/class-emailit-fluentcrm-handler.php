<?php
/**
 * Emailit FluentCRM Handler
 *
 * Handles FluentCRM subscriber actions based on Emailit bounce classifications.
 * Only active when FluentCRM is installed and available.
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

class Emailit_FluentCRM_Handler {

    /**
     * Logger instance
     */
    private $logger;

    /**
     * Bounce classifier instance
     */
    private $bounce_classifier;

    /**
     * FluentCRM integration status
     */
    private $is_fluentcrm_available = false;

    /**
     * Constructor
     */
    public function __construct($logger = null) {
        $this->logger = $logger ?: emailit_get_component('logger');
        $this->bounce_classifier = new Emailit_Bounce_Classifier($this->logger);
        $this->is_fluentcrm_available = $this->check_fluentcrm_availability();
        
        if ($this->is_fluentcrm_available) {
            $this->init_fluentcrm_integration();
        }
    }

    /**
     * Check if FluentCRM is available with version compatibility
     */
    private function check_fluentcrm_availability() {
        // Check if FluentCRM is active
        if (!class_exists('FluentCrm\App\App')) {
            return false;
        }

        // Check FluentCRM version compatibility
        $fluentcrm_version = $this->get_fluentcrm_version();
        if (!$fluentcrm_version || version_compare($fluentcrm_version, '2.0.0', '<')) {
            $this->logger->log('FluentCRM version too old for integration', Emailit_Logger::LEVEL_WARNING, array(
                'version' => $fluentcrm_version,
                'required' => '2.0.0'
            ));
            return false;
        }

        // Check required classes and functions
        $required_classes = array(
            'FluentCrm\App\Models\Subscriber',
            'FluentCrm\App\Models\Contact',
            'FluentCrm\App\Services\Helper'
        );

        foreach ($required_classes as $class) {
            if (!class_exists($class)) {
                $this->logger->log('FluentCRM class not found', Emailit_Logger::LEVEL_WARNING, array(
                    'class' => $class,
                    'version' => $fluentcrm_version
                ));
                return false;
            }
        }

        // Check namespace compatibility
        if (!$this->check_namespace_compatibility()) {
            return false;
        }

        return true;
    }

    /**
     * Get FluentCRM version
     */
    private function get_fluentcrm_version() {
        // Try to get version from plugin data
        if (function_exists('get_plugin_data')) {
            $plugin_file = WP_PLUGIN_DIR . '/fluent-crm/fluent-crm.php';
            if (file_exists($plugin_file)) {
                $plugin_data = get_plugin_data($plugin_file);
                return $plugin_data['Version'] ?? null;
            }
        }

        // Try to get version from FluentCRM constant
        if (defined('FLUENTCRM_VERSION')) {
            return FLUENTCRM_VERSION;
        }

        // Try to get version from FluentCRM class
        if (class_exists('FluentCrm\App\App')) {
            try {
                $app = FluentCrm\App\App::getInstance();
                if (method_exists($app, 'getVersion')) {
                    return $app->getVersion();
                }
            } catch (Exception $e) {
                $this->logger->log('Error getting FluentCRM version', Emailit_Logger::LEVEL_WARNING, array(
                    'error' => $e->getMessage()
                ));
            }
        }

        return null;
    }

    /**
     * Check namespace compatibility
     */
    private function check_namespace_compatibility() {
        try {
            // Test namespace compatibility by trying to instantiate a FluentCRM class
            if (class_exists('FluentCrm\App\Models\Subscriber')) {
                $reflection = new ReflectionClass('FluentCrm\App\Models\Subscriber');
                return $reflection->isInstantiable();
            }
        } catch (Exception $e) {
            $this->logger->log('FluentCRM namespace compatibility check failed', Emailit_Logger::LEVEL_WARNING, array(
                'error' => $e->getMessage()
            ));
            return false;
        }

        return true;
    }

    /**
     * Initialize FluentCRM integration with fallback handling
     */
    private function init_fluentcrm_integration() {
        try {
            // Test FluentCRM integration health
            $this->test_fluentcrm_integration();
            
            // Add hooks for FluentCRM integration
            add_action('emailit_bounce_processed', array($this, 'handle_bounce_event'), 10, 2);
            add_action('emailit_complaint_processed', array($this, 'handle_complaint_event'), 10, 2);
            add_action('emailit_unsubscribe_processed', array($this, 'handle_unsubscribe_event'), 10, 2);
            
            $this->logger->log('FluentCRM integration initialized successfully', Emailit_Logger::LEVEL_INFO, array(
                'version' => $this->get_fluentcrm_version()
            ));
            
        } catch (Exception $e) {
            $this->logger->log('FluentCRM integration initialization failed', Emailit_Logger::LEVEL_ERROR, array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
            
            // Disable FluentCRM integration
            $this->is_fluentcrm_available = false;
        }
    }

    /**
     * Test FluentCRM integration health
     */
    private function test_fluentcrm_integration() {
        // Test basic FluentCRM functionality
        if (!class_exists('FluentCrm\App\Models\Subscriber')) {
            throw new Exception('FluentCRM Subscriber class not available');
        }

        // Test database connectivity
        try {
            $subscriber_count = FluentCrm\App\Models\Subscriber::count();
            if ($subscriber_count === false) {
                throw new Exception('FluentCRM database connection failed');
            }
        } catch (Exception $e) {
            throw new Exception('FluentCRM database test failed: ' . $e->getMessage());
        }
    }

    /**
     * Get FluentCRM integration status
     */
    public function get_integration_status() {
        return array(
            'available' => $this->is_fluentcrm_available,
            'version' => $this->get_fluentcrm_version(),
            'health_check' => $this->is_fluentcrm_available ? $this->test_fluentcrm_health() : false
        );
    }

    /**
     * Test FluentCRM health without throwing exceptions
     */
    private function test_fluentcrm_health() {
        try {
            $this->test_fluentcrm_integration();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Handle bounce action based on classification
     */
    public function handle_bounce_action($email_id, $status, $details) {
        if (!$this->is_fluentcrm_available) {
            return;
        }

        // Check if action mapping is enabled
        if (!get_option('emailit_fluentcrm_enable_action_mapping', true)) {
            return;
        }

        // Only process bounce-related statuses
        if (!in_array($status, array('bounced', 'failed', 'complained'))) {
            return;
        }

        // Extract bounce classification data
        $bounce_classification = $details['bounce_classification'] ?? null;
        $bounce_category = $details['bounce_category'] ?? null;
        $bounce_severity = $details['bounce_severity'] ?? null;
        $bounce_confidence = $details['bounce_confidence'] ?? 0;

        if (!$bounce_classification) {
            return;
        }

        // Check confidence threshold
        $confidence_threshold = get_option('emailit_fluentcrm_confidence_threshold', 70);
        if ($bounce_confidence < $confidence_threshold) {
            $this->logger->log('Bounce classification below confidence threshold, skipping FluentCRM action', Emailit_Logger::LEVEL_DEBUG, array(
                'email_id' => $email_id,
                'classification' => $bounce_classification,
                'confidence' => $bounce_confidence,
                'threshold' => $confidence_threshold
            ));
            return;
        }

        // Get email address from email_id or details
        $email_address = $this->get_email_address_from_identifier($email_id, $details);
        if (!$email_address) {
            $this->logger->log('Could not determine email address for FluentCRM action', Emailit_Logger::LEVEL_WARNING, array(
                'email_id' => $email_id,
                'bounce_classification' => $bounce_classification
            ));
            return;
        }

        // Process the bounce action
        $this->process_bounce_action($email_address, $bounce_classification, $bounce_category, $bounce_severity, $bounce_confidence, $details);
    }

    /**
     * Get email address from identifier or details
     */
    private function get_email_address_from_identifier($email_id, $details) {
        // Try to extract from details first
        if (isset($details['to_email'])) {
            return $details['to_email'];
        }

        if (isset($details['recipient_email'])) {
            return $details['recipient_email'];
        }

        // Try to get from email logs
        global $wpdb;
        $logs_table = $wpdb->prefix . 'emailit_logs';
        
        $email_data = $wpdb->get_row($wpdb->prepare(
            "SELECT to_email, recipient_email FROM {$logs_table} WHERE email_id = %s OR id = %s LIMIT 1",
            $email_id,
            $email_id
        ));

        if ($email_data) {
            return $email_data->to_email ?: $email_data->recipient_email;
        }

        return null;
    }

    /**
     * Process bounce action based on classification
     */
    private function process_bounce_action($email_address, $classification, $category, $severity, $confidence, $details) {
        // Validate FluentCRM configuration before processing
        if (!$this->validate_fluentcrm_config()) {
            $this->logger->log('FluentCRM configuration validation failed, skipping bounce processing', Emailit_Logger::LEVEL_WARNING, array(
                'email_address' => $email_address,
                'classification' => $classification
            ));
            return;
        }

        // Use the enhanced version with hooks
        $this->process_bounce_action_with_hooks($email_address, $classification, $category, $severity, $confidence, $details);
    }

    /**
     * Get or create FluentCRM subscriber
     */
    private function get_or_create_subscriber($email_address) {
        if (!$this->is_fluentcrm_available) {
            return null;
        }

        try {
            // Try to get existing subscriber
            $subscriber = \FluentCrm\App\Models\Subscriber::where('email', $email_address)->first();
            
            if (!$subscriber) {
                // Check if auto-create is enabled
                if (!get_option('emailit_fluentcrm_auto_create_subscribers', true)) {
                    $this->logger->log('Subscriber not found and auto-create disabled', Emailit_Logger::LEVEL_DEBUG, array(
                        'email_address' => $email_address
                    ));
                    return null;
                }

                // Create new subscriber
                $subscriber_data = array(
                    'email' => $email_address,
                    'status' => 'subscribed',
                    'source' => 'emailit_integration',
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                );

                $subscriber = \FluentCrm\App\Models\Subscriber::create($subscriber_data);
                
                if ($subscriber) {
                    $this->logger->log('Created new FluentCRM subscriber', Emailit_Logger::LEVEL_INFO, array(
                        'email_address' => $email_address,
                        'subscriber_id' => $subscriber->id
                    ));
                }
            }

            return $subscriber;

        } catch (Exception $e) {
            $this->logger->log('Failed to get or create FluentCRM subscriber', Emailit_Logger::LEVEL_ERROR, array(
                'email_address' => $email_address,
                'error' => $e->getMessage()
            ));
            return null;
        }
    }

    /**
     * Handle hard bounce - unsubscribe subscriber
     */
    private function handle_hard_bounce($subscriber, $details) {
        $action = 'unsubscribed_hard_bounce';
        
        // Update subscriber status
        $subscriber->status = 'bounced';
        $subscriber->save();

        // Add bounce reason to subscriber meta
        $this->add_subscriber_meta($subscriber, 'emailit_bounce_reason', $details['bounce_reason'] ?? 'Hard bounce');
        $this->add_subscriber_meta($subscriber, 'emailit_bounce_classification', 'hard_bounce');
        $this->add_subscriber_meta($subscriber, 'emailit_bounce_date', current_time('mysql'));

        // Add tag for tracking
        $this->add_subscriber_tag($subscriber, 'Emailit Hard Bounce');

        return $action;
    }

    /**
     * Handle soft bounce - track in meta, don't unsubscribe yet
     */
    private function handle_soft_bounce($subscriber, $details) {
        $action = 'tracked_soft_bounce';
        
        // Get threshold settings
        $threshold = get_option('emailit_fluentcrm_soft_bounce_threshold', 5);
        $window_days = get_option('emailit_fluentcrm_soft_bounce_window', 7);
        $history_limit = get_option('emailit_fluentcrm_soft_bounce_history_limit', 10);
        
        // Get current bounce data
        $bounce_count = $this->get_subscriber_meta($subscriber, 'emailit_soft_bounce_count', 0);
        $last_bounce_date = $this->get_subscriber_meta($subscriber, 'emailit_last_soft_bounce', '');
        $bounce_history = $this->get_subscriber_meta($subscriber, 'emailit_soft_bounce_history', array());
        
        // Check if we're within the time window
        $current_time = current_time('mysql');
        $window_start = date('Y-m-d H:i:s', strtotime("-{$window_days} days"));
        
        // Reset count if outside window
        if ($last_bounce_date && $last_bounce_date < $window_start) {
            $bounce_count = 0;
            $bounce_history = array();
        }
        
        // Increment bounce count
        $bounce_count++;
        
        // Add to bounce history
        $bounce_record = array(
            'timestamp' => $current_time,
            'reason' => $details['bounce_reason'] ?? 'Soft bounce',
            'classification' => $details['bounce_classification'] ?? 'soft_bounce',
            'confidence' => $details['bounce_confidence'] ?? 0
        );
        
        $bounce_history[] = $bounce_record;
        
        // Trim history to limit
        if (count($bounce_history) > $history_limit) {
            $bounce_history = array_slice($bounce_history, -$history_limit);
        }
        
        // Update subscriber meta
        $this->add_subscriber_meta($subscriber, 'emailit_soft_bounce_count', $bounce_count);
        $this->add_subscriber_meta($subscriber, 'emailit_last_soft_bounce', $current_time);
        $this->add_subscriber_meta($subscriber, 'emailit_soft_bounce_reason', $details['bounce_reason'] ?? 'Soft bounce');
        $this->add_subscriber_meta($subscriber, 'emailit_soft_bounce_history', $bounce_history);
        
        // Check if we should escalate to hard bounce
        if ($bounce_count >= $threshold) {
            $this->handle_hard_bounce($subscriber, $details);
            $action = 'escalated_to_hard_bounce';
            
            // Log escalation
            $this->logger->log('Soft bounce threshold exceeded, escalated to hard bounce', Emailit_Logger::LEVEL_WARNING, array(
                'subscriber_id' => $subscriber->id,
                'email' => $subscriber->email,
                'bounce_count' => $bounce_count,
                'threshold' => $threshold,
                'window_days' => $window_days
            ));
        } else {
            // Add tag for tracking
            $this->add_subscriber_tag($subscriber, 'Emailit Soft Bounce');
            
            // Log soft bounce tracking
            $this->logger->log('Soft bounce tracked', Emailit_Logger::LEVEL_INFO, array(
                'subscriber_id' => $subscriber->id,
                'email' => $subscriber->email,
                'bounce_count' => $bounce_count,
                'threshold' => $threshold,
                'remaining' => $threshold - $bounce_count
            ));
        }

        return $action;
    }

    /**
     * Handle spam complaint - unsubscribe immediately
     */
    private function handle_spam_complaint($subscriber, $details) {
        $action = 'unsubscribed_spam_complaint';
        
        // Update subscriber status
        $subscriber->status = 'complained';
        $subscriber->save();

        // Add complaint reason to subscriber meta
        $this->add_subscriber_meta($subscriber, 'emailit_complaint_reason', $details['complaint_reason'] ?? 'Spam complaint');
        $this->add_subscriber_meta($subscriber, 'emailit_complaint_date', current_time('mysql'));

        // Add tag for tracking
        $this->add_subscriber_tag($subscriber, 'Emailit Spam Complaint');

        return $action;
    }

    /**
     * Handle unsubscribe - respect user request
     */
    private function handle_unsubscribe($subscriber, $details) {
        $action = 'unsubscribed_user_request';
        
        // Update subscriber status
        $subscriber->status = 'unsubscribed';
        $subscriber->save();

        // Add unsubscribe reason to subscriber meta
        $this->add_subscriber_meta($subscriber, 'emailit_unsubscribe_reason', $details['bounce_reason'] ?? 'User unsubscribe request');
        $this->add_subscriber_meta($subscriber, 'emailit_unsubscribe_date', current_time('mysql'));

        // Add tag for tracking
        $this->add_subscriber_tag($subscriber, 'Emailit Unsubscribed');

        return $action;
    }

    /**
     * Handle unknown bounce - log for review
     */
    private function handle_unknown_bounce($subscriber, $details) {
        $action = 'logged_for_review';
        
        // Add to subscriber meta for manual review
        $this->add_subscriber_meta($subscriber, 'emailit_unknown_bounce_reason', $details['bounce_reason'] ?? 'Unknown bounce');
        $this->add_subscriber_meta($subscriber, 'emailit_unknown_bounce_date', current_time('mysql'));

        // Add tag for manual review
        $this->add_subscriber_tag($subscriber, 'Emailit Unknown Bounce');

        return $action;
    }

    /**
     * Handle successful delivery - reset bounce counts if enabled
     */
    public function handle_successful_delivery($email_id, $status, $details) {
        if (!$this->is_fluentcrm_available) {
            return;
        }

        // Only process successful deliveries
        if ($status !== 'delivered' && $status !== 'sent') {
            return;
        }

        // Check if reset on success is enabled
        if (!get_option('emailit_fluentcrm_soft_bounce_reset_on_success', true)) {
            return;
        }

        // Get email address
        $email_address = $this->get_email_address_from_identifier($email_id, $details);
        if (!$email_address) {
            return;
        }

        try {
            // Get subscriber
            $subscriber = \FluentCrm\App\Models\Subscriber::where('email', $email_address)->first();
            if (!$subscriber) {
                return;
            }

            // Check if subscriber has soft bounce count
            $bounce_count = $this->get_subscriber_meta($subscriber, 'emailit_soft_bounce_count', 0);
            if ($bounce_count > 0) {
                // Reset bounce count and history
                $this->add_subscriber_meta($subscriber, 'emailit_soft_bounce_count', 0);
                $this->add_subscriber_meta($subscriber, 'emailit_soft_bounce_history', array());
                $this->add_subscriber_meta($subscriber, 'emailit_last_soft_bounce', '');
                $this->add_subscriber_meta($subscriber, 'emailit_soft_bounce_reason', '');

                // Remove soft bounce tag
                $this->remove_subscriber_tag($subscriber, 'Emailit Soft Bounce');

                // Log reset
                $this->logger->log('Soft bounce count reset due to successful delivery', Emailit_Logger::LEVEL_INFO, array(
                    'subscriber_id' => $subscriber->id,
                    'email' => $subscriber->email,
                    'previous_count' => $bounce_count
                ));
            }

        } catch (Exception $e) {
            $this->logger->log('Failed to reset bounce count on successful delivery', Emailit_Logger::LEVEL_ERROR, array(
                'email_address' => $email_address,
                'error' => $e->getMessage()
            ));
        }
    }

    /**
     * Add subscriber meta data
     */
    private function add_subscriber_meta($subscriber, $key, $value) {
        if (!$this->is_fluentcrm_available) {
            return false;
        }

        try {
            $subscriber->updateMeta($key, $value, 'emailit_integration');
            return true;
        } catch (Exception $e) {
            $this->logger->log('Failed to add subscriber meta', Emailit_Logger::LEVEL_ERROR, array(
                'subscriber_id' => $subscriber->id,
                'key' => $key,
                'error' => $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * Get subscriber meta data
     */
    private function get_subscriber_meta($subscriber, $key, $default = null) {
        if (!$this->is_fluentcrm_available) {
            return $default;
        }

        try {
            return $subscriber->getMeta($key, 'emailit_integration');
        } catch (Exception $e) {
            $this->logger->log('Failed to get subscriber meta', Emailit_Logger::LEVEL_ERROR, array(
                'subscriber_id' => $subscriber->id,
                'key' => $key,
                'error' => $e->getMessage()
            ));
            return $default;
        }
    }

    /**
     * Add tag to subscriber
     */
    private function add_subscriber_tag($subscriber, $tag_name) {
        if (!$this->is_fluentcrm_available) {
            return false;
        }

        try {
            // Get or create tag
            $tag = \FluentCrm\App\Models\Tag::where('title', $tag_name)->first();
            if (!$tag) {
                $tag = \FluentCrm\App\Models\Tag::create(array(
                    'title' => $tag_name,
                    'slug' => sanitize_title($tag_name),
                    'description' => 'Auto-generated by Emailit Integration',
                    'created_at' => current_time('mysql')
                ));
                
                if (!$tag || !$tag->id) {
                    throw new Exception('Failed to create tag');
                }
            }

            // Attach tag to subscriber
            $subscriber->attachTags(array($tag->id));
            return true;

        } catch (Exception $e) {
            $this->logger->log('Failed to add subscriber tag', Emailit_Logger::LEVEL_ERROR, array(
                'subscriber_id' => $subscriber->id,
                'tag_name' => $tag_name,
                'error' => $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * Remove tag from subscriber
     */
    private function remove_subscriber_tag($subscriber, $tag_name) {
        if (!$this->is_fluentcrm_available) {
            return false;
        }

        try {
            // Get tag
            $tag = \FluentCrm\App\Models\Tag::where('title', $tag_name)->first();
            if (!$tag) {
                return true; // Tag doesn't exist, consider it removed
            }

            // Detach tag from subscriber
            $subscriber->detachTags(array($tag->id));
            return true;

        } catch (Exception $e) {
            $this->logger->log('Failed to remove subscriber tag', Emailit_Logger::LEVEL_ERROR, array(
                'subscriber_id' => $subscriber->id,
                'tag_name' => $tag_name,
                'error' => $e->getMessage()
            ));
            return false;
        }
    }


    /**
     * Get soft bounce statistics
     */
    public function get_soft_bounce_statistics() {
        if (!$this->is_fluentcrm_available) {
            return array();
        }

        try {
            global $wpdb;
            
            // Get subscribers with soft bounce counts
            $subscribers_with_bounces = $wpdb->get_var("
                SELECT COUNT(DISTINCT subscriber_id) 
                FROM {$wpdb->prefix}fc_subscriber_meta 
                WHERE meta_key = 'emailit_soft_bounce_count' 
                AND CAST(meta_value AS UNSIGNED) > 0
            ");

            // Get subscribers approaching threshold
            $threshold = get_option('emailit_fluentcrm_soft_bounce_threshold', 5);
            $approaching_threshold = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(DISTINCT subscriber_id) 
                FROM {$wpdb->prefix}fc_subscriber_meta 
                WHERE meta_key = 'emailit_soft_bounce_count' 
                AND CAST(meta_value AS UNSIGNED) >= %d
            ", $threshold - 1));

            // Get total soft bounces today
            $soft_bounces_today = $wpdb->get_var("
                SELECT COUNT(*) 
                FROM {$wpdb->prefix}fc_subscriber_meta 
                WHERE meta_key = 'emailit_last_soft_bounce' 
                AND DATE(meta_value) = CURDATE()
            ");

            return array(
                'subscribers_with_bounces' => (int) $subscribers_with_bounces,
                'approaching_threshold' => (int) $approaching_threshold,
                'soft_bounces_today' => (int) $soft_bounces_today,
                'threshold' => $threshold
            );

        } catch (Exception $e) {
            $this->logger->log('Failed to get soft bounce statistics', Emailit_Logger::LEVEL_ERROR, array(
                'error' => $e->getMessage()
            ));
            return array();
        }
    }

    /**
     * Get subscriber bounce details
     */
    public function get_subscriber_bounce_details($subscriber_id) {
        if (!$this->is_fluentcrm_available) {
            return null;
        }

        try {
            $subscriber = \FluentCrm\App\Models\Subscriber::find($subscriber_id);
            if (!$subscriber) {
                return null;
            }

            $bounce_count = $this->get_subscriber_meta($subscriber, 'emailit_soft_bounce_count', 0);
            $last_bounce = $this->get_subscriber_meta($subscriber, 'emailit_last_soft_bounce', '');
            $bounce_reason = $this->get_subscriber_meta($subscriber, 'emailit_soft_bounce_reason', '');
            $bounce_history = $this->get_subscriber_meta($subscriber, 'emailit_soft_bounce_history', array());
            $threshold = get_option('emailit_fluentcrm_soft_bounce_threshold', 5);

            return array(
                'subscriber_id' => $subscriber->id,
                'email' => $subscriber->email,
                'bounce_count' => $bounce_count,
                'last_bounce' => $last_bounce,
                'bounce_reason' => $bounce_reason,
                'bounce_history' => $bounce_history,
                'threshold' => $threshold,
                'remaining' => max(0, $threshold - $bounce_count),
                'is_approaching_threshold' => $bounce_count >= ($threshold - 1),
                'is_at_threshold' => $bounce_count >= $threshold
            );

        } catch (Exception $e) {
            $this->logger->log('Failed to get subscriber bounce details', Emailit_Logger::LEVEL_ERROR, array(
                'subscriber_id' => $subscriber_id,
                'error' => $e->getMessage()
            ));
            return null;
        }
    }

    /**
     * Reset subscriber bounce count
     */
    public function reset_subscriber_bounce_count($subscriber_id) {
        if (!$this->is_fluentcrm_available) {
            return false;
        }

        try {
            $subscriber = \FluentCrm\App\Models\Subscriber::find($subscriber_id);
            if (!$subscriber) {
                return false;
            }

            // Reset all bounce-related meta
            $this->add_subscriber_meta($subscriber, 'emailit_soft_bounce_count', 0);
            $this->add_subscriber_meta($subscriber, 'emailit_soft_bounce_history', array());
            $this->add_subscriber_meta($subscriber, 'emailit_last_soft_bounce', '');
            $this->add_subscriber_meta($subscriber, 'emailit_soft_bounce_reason', '');

            // Remove soft bounce tag
            $this->remove_subscriber_tag($subscriber, 'Emailit Soft Bounce');

            $this->logger->log('Subscriber bounce count reset manually', Emailit_Logger::LEVEL_INFO, array(
                'subscriber_id' => $subscriber_id,
                'email' => $subscriber->email
            ));

            return true;

        } catch (Exception $e) {
            $this->logger->log('Failed to reset subscriber bounce count', Emailit_Logger::LEVEL_ERROR, array(
                'subscriber_id' => $subscriber_id,
                'error' => $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * Get subscriber count
     */
    private function get_subscriber_count() {
        if (!$this->is_fluentcrm_available) {
            return 0;
        }

        try {
            return \FluentCrm\App\Models\Subscriber::count();
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Get bounce actions count for period
     */
    private function get_bounce_actions_count($period = 'today') {
        global $wpdb;
        
        $logs_table = $wpdb->prefix . 'emailit_logs';
        $start_date = null;

        switch ($period) {
            case 'today':
                $start_date = date('Y-m-d 00:00:00');
                break;
            case 'week':
                $start_date = date('Y-m-d 00:00:00', strtotime('-7 days'));
                break;
            case 'month':
                $start_date = date('Y-m-d 00:00:00', strtotime('-30 days'));
                break;
        }

        if (!$start_date) {
            return 0;
        }

        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$logs_table} 
            WHERE bounce_classification IS NOT NULL 
            AND created_at >= %s
        ", $start_date));

        return (int) $count;
    }

    /**
     * Check if FluentCRM is available
     */
    public function is_available() {
        return $this->is_fluentcrm_available;
    }

    /**
     * Verify FluentCRM setup and configuration
     */
    public function verify_fluentcrm_setup() {
        if (!$this->is_fluentcrm_available) {
            return array(
                'status' => 'error',
                'message' => 'FluentCRM is not installed or active'
            );
        }

        try {
            // Check if FluentCRM tables exist
            global $wpdb;
            $subscribers_table = $wpdb->prefix . 'fc_subscribers';
            $tags_table = $wpdb->prefix . 'fc_tags';
            $meta_table = $wpdb->prefix . 'fc_subscriber_meta';

            $tables_exist = (
                $wpdb->get_var("SHOW TABLES LIKE '{$subscribers_table}'") &&
                $wpdb->get_var("SHOW TABLES LIKE '{$tags_table}'") &&
                $wpdb->get_var("SHOW TABLES LIKE '{$meta_table}'")
            );

            if (!$tables_exist) {
                return array(
                    'status' => 'error',
                    'message' => 'FluentCRM database tables are missing'
                );
            }

            // Test basic operations
            $test_subscriber = \FluentCrm\App\Models\Subscriber::first();
            $test_tag = \FluentCrm\App\Models\Tag::first();

            // Check required permissions
            $can_create_subscriber = current_user_can('fluentcrm_manage_contacts');
            $can_manage_tags = current_user_can('fluentcrm_manage_tags');

            return array(
                'status' => 'success',
                'message' => 'FluentCRM setup is valid',
                'details' => array(
                    'tables_exist' => $tables_exist,
                    'can_create_subscriber' => $can_create_subscriber,
                    'can_manage_tags' => $can_manage_tags,
                    'subscriber_count' => \FluentCrm\App\Models\Subscriber::count(),
                    'tag_count' => \FluentCrm\App\Models\Tag::count()
                )
            );

        } catch (Exception $e) {
            return array(
                'status' => 'error',
                'message' => 'FluentCRM setup verification failed: ' . $e->getMessage()
            );
        }
    }

    /**
     * Validate FluentCRM configuration
     */
    private function validate_fluentcrm_config() {
        $required_options = array(
            'emailit_fluentcrm_integration',
            'emailit_fluentcrm_confidence_threshold',
            'emailit_fluentcrm_soft_bounce_threshold'
        );

        $missing_options = array();
        foreach ($required_options as $option) {
            if (get_option($option) === false) {
                $missing_options[] = $option;
            }
        }

        if (!empty($missing_options)) {
            $this->logger->log('Missing FluentCRM configuration options', Emailit_Logger::LEVEL_WARNING, array(
                'missing_options' => $missing_options
            ));
            return false;
        }

        return true;
    }

    /**
     * Enhanced bounce processing with hooks
     */
    private function process_bounce_action_with_hooks($email_address, $classification, $category, $severity, $confidence, $details) {
        $action_taken = null;
        $subscriber_id = null;

        try {
            // Get or create FluentCRM subscriber
            $subscriber = $this->get_or_create_subscriber($email_address);
            if (!$subscriber) {
                throw new Exception('Could not get or create FluentCRM subscriber');
            }

            $subscriber_id = $subscriber->id;

            // Fire hook before processing
            do_action('emailit_fluentcrm_bounce_processing_start', $subscriber, $classification, $details);

            // Map bounce classification to FluentCRM action
            switch ($classification) {
                case 'hard_bounce':
                    $action_taken = $this->handle_hard_bounce($subscriber, $details);
                    break;

                case 'soft_bounce':
                    $action_taken = $this->handle_soft_bounce($subscriber, $details);
                    break;

                case 'spam_complaint':
                    $action_taken = $this->handle_spam_complaint($subscriber, $details);
                    break;

                case 'unsubscribe':
                    $action_taken = $this->handle_unsubscribe($subscriber, $details);
                    break;

                case 'unknown':
                    $action_taken = $this->handle_unknown_bounce($subscriber, $details);
                    break;

                default:
                    $this->logger->log('Unknown bounce classification for FluentCRM action', Emailit_Logger::LEVEL_WARNING, array(
                        'email_address' => $email_address,
                        'classification' => $classification
                    ));
                    return;
            }

            // Fire hook after processing
            do_action('emailit_fluentcrm_bounce_processed', $subscriber, $classification, $action_taken, $details);

            // Log the action
            $this->logger->log('FluentCRM action completed', Emailit_Logger::LEVEL_INFO, array(
                'email_address' => $email_address,
                'subscriber_id' => $subscriber_id,
                'classification' => $classification,
                'action_taken' => $action_taken,
                'confidence' => $confidence
            ));

        } catch (Exception $e) {
            // Fire error hook
            do_action('emailit_fluentcrm_bounce_error', $email_address, $classification, $e->getMessage(), $details);

            $this->logger->log('FluentCRM action failed', Emailit_Logger::LEVEL_ERROR, array(
                'email_address' => $email_address,
                'subscriber_id' => $subscriber_id,
                'classification' => $classification,
                'error' => $e->getMessage()
            ));
        }
    }

    /**
     * Enhanced subscriber creation with hooks
     */
    private function get_or_create_subscriber_with_hooks($email_address) {
        if (!$this->is_fluentcrm_available) {
            return null;
        }

        try {
            // Try to get existing subscriber
            $subscriber = \FluentCrm\App\Models\Subscriber::where('email', $email_address)->first();
            
            if (!$subscriber) {
                // Check if auto-create is enabled
                if (!get_option('emailit_fluentcrm_auto_create_subscribers', true)) {
                    $this->logger->log('Subscriber not found and auto-create disabled', Emailit_Logger::LEVEL_DEBUG, array(
                        'email_address' => $email_address
                    ));
                    return null;
                }

                // Fire hook before creating subscriber
                do_action('emailit_fluentcrm_subscriber_creating', $email_address);

                // Create new subscriber
                $subscriber_data = array(
                    'email' => $email_address,
                    'status' => 'subscribed',
                    'source' => 'emailit_integration',
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                );

                $subscriber = \FluentCrm\App\Models\Subscriber::create($subscriber_data);
                
                if ($subscriber) {
                    // Fire hook after creating subscriber
                    do_action('emailit_fluentcrm_subscriber_created', $subscriber);

                    $this->logger->log('Created new FluentCRM subscriber', Emailit_Logger::LEVEL_INFO, array(
                        'email_address' => $email_address,
                        'subscriber_id' => $subscriber->id
                    ));
                }
            }

            return $subscriber;

        } catch (Exception $e) {
            // Fire error hook
            do_action('emailit_fluentcrm_subscriber_error', $email_address, $e->getMessage());

            $this->logger->log('Failed to get or create FluentCRM subscriber', Emailit_Logger::LEVEL_ERROR, array(
                'email_address' => $email_address,
                'error' => $e->getMessage()
            ));
            return null;
        }
    }
}
