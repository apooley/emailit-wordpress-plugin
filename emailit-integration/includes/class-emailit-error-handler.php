<?php
/**
 * Emailit Error Handler
 *
 * Advanced error handling, logging, and recovery system.
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

class Emailit_Error_Handler {

    /**
     * Logger instance
     */
    private $logger;

    /**
     * Error analytics instance
     */
    private $error_analytics;

    /**
     * Retry manager instance
     */
    private $retry_manager;

    /**
     * Error notifications instance
     */
    private $error_notifications;

    /**
     * Error codes and recovery strategies
     */
    private $error_strategies = array();

    /**
     * Circuit breaker status
     */
    private $circuit_breaker_key = 'emailit_circuit_breaker';

    /**
     * Max consecutive failures before circuit break
     */
    private $failure_threshold = 5;

    /**
     * Circuit breaker timeout (seconds)
     */
    private $circuit_timeout = 300; // 5 minutes

    /**
     * Constructor
     */
    public function __construct($logger = null) {
        $this->logger = $logger;
        $this->init_error_strategies();
        $this->init_advanced_error_handling();
    }

    /**
     * Initialize advanced error handling components
     */
    private function init_advanced_error_handling() {
        // Initialize error analytics
        if (class_exists('Emailit_Error_Analytics')) {
            $this->error_analytics = new Emailit_Error_Analytics($this->logger);
        }

        // Initialize retry manager
        if (class_exists('Emailit_Retry_Manager')) {
            $this->retry_manager = new Emailit_Retry_Manager($this->logger);
        }

        // Initialize error notifications
        if (class_exists('Emailit_Error_Notifications')) {
            $this->error_notifications = new Emailit_Error_Notifications($this->logger);
        }
    }

    /**
     * Initialize error handling strategies
     */
    private function init_error_strategies() {
        $this->error_strategies = array(
            // API Authentication Errors
            'invalid_api_key' => array(
                'level' => 'critical',
                'action' => 'disable_api',
                'message' => __('API key is invalid. Please check your Emailit dashboard and update the key.', 'emailit-integration'),
                'recovery' => array($this, 'handle_auth_error')
            ),
            'unauthorized' => array(
                'level' => 'critical',
                'action' => 'disable_api',
                'message' => __('Unauthorized access to Emailit API. Please verify your account status.', 'emailit-integration'),
                'recovery' => array($this, 'handle_auth_error')
            ),

            // Rate Limiting
            'rate_limit_exceeded' => array(
                'level' => 'warning',
                'action' => 'delay_retry',
                'message' => __('API rate limit exceeded. Emails will be delayed.', 'emailit-integration'),
                'recovery' => array($this, 'handle_rate_limit')
            ),

            // Network Issues
            'network_error' => array(
                'level' => 'warning',
                'action' => 'retry',
                'message' => __('Network connectivity issue. Will retry automatically.', 'emailit-integration'),
                'recovery' => array($this, 'handle_network_error')
            ),
            'timeout' => array(
                'level' => 'warning',
                'action' => 'retry',
                'message' => __('API request timeout. Will retry with extended timeout.', 'emailit-integration'),
                'recovery' => array($this, 'handle_timeout')
            ),

            // Validation Errors
            'invalid_email' => array(
                'level' => 'error',
                'action' => 'log_skip',
                'message' => __('Invalid email address format. Email skipped.', 'emailit-integration'),
                'recovery' => array($this, 'handle_validation_error')
            ),
            'missing_required_field' => array(
                'level' => 'error',
                'action' => 'log_skip',
                'message' => __('Required email field missing. Email skipped.', 'emailit-integration'),
                'recovery' => array($this, 'handle_validation_error')
            ),

            // Quota/Limits
            'quota_exceeded' => array(
                'level' => 'critical',
                'action' => 'disable_api',
                'message' => __('Emailit quota exceeded. Please upgrade your plan or wait for quota reset.', 'emailit-integration'),
                'recovery' => array($this, 'handle_quota_error')
            ),

            // Server Errors
            'server_error' => array(
                'level' => 'warning',
                'action' => 'retry',
                'message' => __('Emailit server error. Will retry automatically.', 'emailit-integration'),
                'recovery' => array($this, 'handle_server_error')
            )
        );
    }

    /**
     * Handle error with appropriate strategy
     */
    public function handle_error($error, $context = array()) {
        $error_code = is_wp_error($error) ? $error->get_error_code() : 'unknown_error';
        $error_message = is_wp_error($error) ? $error->get_error_message() : $error;

        // Get error strategy
        $strategy = isset($this->error_strategies[$error_code]) ?
            $this->error_strategies[$error_code] :
            $this->get_default_strategy();

        // Track error in analytics
        if ($this->error_analytics) {
            $this->error_analytics->track_error($error_code, $error_message, array_merge($context, array(
                'level' => $strategy['level']
            )));
        }

        // Log error with context
        $this->log_error($error_code, $error_message, $strategy['level'], $context);

        // Execute recovery action
        $recovery_result = null;
        if (isset($strategy['recovery']) && is_callable($strategy['recovery'])) {
            try {
                $recovery_result = call_user_func($strategy['recovery'], $error, $context);
            } catch (Exception $e) {
                $this->log_error('recovery_failed', $e->getMessage(), 'error', array(
                    'original_error' => $error_code,
                    'recovery_exception' => $e->getTraceAsString()
                ));
            }
        }

        // Update circuit breaker
        $this->update_circuit_breaker($error_code, $strategy['level']);

        // Handle retry logic
        $retry_result = null;
        if (in_array($strategy['action'], array('retry', 'delay_retry')) && $this->retry_manager) {
            $retry_result = $this->retry_manager->execute_retry(
                $context['operation'] ?? null,
                $error_code,
                $context
            );
        }

        // Trigger notifications
        if ($this->error_notifications) {
            if ($strategy['level'] === 'critical') {
                $this->error_notifications->handle_critical_error($error_code, $error_message, $context);
            } else {
                $this->error_notifications->handle_error_notification($error_code, $error_message, $context);
            }
        } else {
            // Fallback to old notification system
            if ($strategy['level'] === 'critical') {
                $this->trigger_admin_notification($error_code, $strategy['message'], $context);
            }
        }

        return array(
            'strategy' => $strategy,
            'recovery_result' => $recovery_result,
            'retry_result' => $retry_result,
            'should_retry' => in_array($strategy['action'], array('retry', 'delay_retry'))
        );
    }

    /**
     * Get default error strategy
     */
    private function get_default_strategy() {
        return array(
            'level' => 'error',
            'action' => 'log',
            'message' => __('An unexpected error occurred.', 'emailit-integration'),
            'recovery' => null
        );
    }

    /**
     * Enhanced error logging
     */
    private function log_error($code, $message, $level, $context = array()) {
        $log_entry = array(
            'error_code' => $code,
            'message' => $message,
            'level' => $level,
            'context' => $context,
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'request_uri' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '',
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            'ip_address' => $this->get_client_ip()
        );

        // Log to WordPress debug log
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[Emailit Error] ' . wp_json_encode($log_entry));
        }

        // Log via plugin logger
        if ($this->logger) {
            $this->logger->log(
                sprintf('[%s] %s', strtoupper($code), $message),
                $level,
                $log_entry
            );
        }

        // Store error history
        $this->store_error_history($log_entry);
    }

    /**
     * Store error history for analysis
     */
    private function store_error_history($log_entry) {
        $history_key = 'emailit_error_history';
        $history = get_transient($history_key) ?: array();

        // Add new entry
        $history[] = $log_entry;

        // Keep only last 100 errors
        if (count($history) > 100) {
            $history = array_slice($history, -100);
        }

        // Store for 24 hours
        set_transient($history_key, $history, 24 * HOUR_IN_SECONDS);
    }

    /**
     * Circuit breaker implementation
     */
    private function update_circuit_breaker($error_code, $level) {
        $breaker_data = get_transient($this->circuit_breaker_key) ?: array(
            'failures' => 0,
            'last_failure' => 0,
            'status' => 'closed' // closed, open, half-open
        );

        if ($level === 'critical' || $level === 'error') {
            $breaker_data['failures']++;
            $breaker_data['last_failure'] = time();

            if ($breaker_data['failures'] >= $this->failure_threshold) {
                $breaker_data['status'] = 'open';

                // Disable API temporarily
                update_option('emailit_circuit_breaker_active', true);

                $this->log_error('circuit_breaker_open',
                    sprintf('Circuit breaker opened after %d failures', $breaker_data['failures']),
                    'critical'
                );
            }
        } else {
            // Success or recoverable error - reset counter
            if ($breaker_data['status'] === 'half-open') {
                $breaker_data['status'] = 'closed';
                $breaker_data['failures'] = 0;
                delete_option('emailit_circuit_breaker_active');
            }
        }

        set_transient($this->circuit_breaker_key, $breaker_data, $this->circuit_timeout);
    }

    /**
     * Check if circuit breaker allows requests
     */
    public function is_circuit_breaker_open() {
        $breaker_data = get_transient($this->circuit_breaker_key);

        if (!$breaker_data || $breaker_data['status'] !== 'open') {
            return false;
        }

        // Check if timeout period has passed
        if (time() - $breaker_data['last_failure'] > $this->circuit_timeout) {
            $breaker_data['status'] = 'half-open';
            set_transient($this->circuit_breaker_key, $breaker_data, $this->circuit_timeout);
            return false;
        }

        return true;
    }

    /**
     * Recovery handlers
     */
    public function handle_auth_error($error, $context) {
        // Temporarily disable API
        update_option('emailit_api_disabled_until', time() + (30 * MINUTE_IN_SECONDS));

        return array('action' => 'api_disabled', 'duration' => '30 minutes');
    }

    public function handle_rate_limit($error, $context) {
        // Extract retry-after from headers if available
        $retry_after = isset($context['retry_after']) ? (int) $context['retry_after'] : 60;

        // Set rate limit flag
        set_transient('emailit_rate_limited', true, $retry_after);

        return array('action' => 'delayed', 'retry_after' => $retry_after);
    }

    public function handle_network_error($error, $context) {
        // Increase timeout for next attempt
        $current_timeout = get_option('emailit_timeout', 30);
        $new_timeout = min($current_timeout * 1.5, 120);

        set_transient('emailit_extended_timeout', $new_timeout, 10 * MINUTE_IN_SECONDS);

        return array('action' => 'timeout_extended', 'new_timeout' => $new_timeout);
    }

    public function handle_timeout($error, $context) {
        return $this->handle_network_error($error, $context);
    }

    public function handle_validation_error($error, $context) {
        // Log validation issue for review
        return array('action' => 'logged', 'skipped' => true);
    }

    public function handle_quota_error($error, $context) {
        // Disable API until manual intervention
        update_option('emailit_quota_exceeded', true);
        update_option('emailit_api_disabled_until', time() + (24 * HOUR_IN_SECONDS));

        return array('action' => 'quota_disabled', 'duration' => '24 hours');
    }

    public function handle_server_error($error, $context) {
        // Implement exponential backoff
        $backoff_key = 'emailit_server_error_backoff';
        $backoff_time = get_transient($backoff_key) ?: 1;

        $new_backoff = min($backoff_time * 2, 300); // Max 5 minutes
        set_transient($backoff_key, $new_backoff, $new_backoff);

        return array('action' => 'backoff', 'delay' => $new_backoff);
    }

    /**
     * Trigger admin notifications
     */
    private function trigger_admin_notification($error_code, $message, $context) {
        // Check if we've already sent this notification recently
        $notification_key = 'emailit_notification_' . md5($error_code . $message);

        if (get_transient($notification_key)) {
            return; // Already notified recently
        }

        // Store notification flag for 1 hour
        set_transient($notification_key, true, HOUR_IN_SECONDS);

        // Add admin notice
        $this->add_admin_notice($message, 'error');

        // Send email to admin if configured
        if (get_option('emailit_error_email_notifications', false)) {
            $this->send_error_email($error_code, $message, $context);
        }
    }

    /**
     * Add admin notice
     */
    private function add_admin_notice($message, $type = 'error') {
        add_action('admin_notices', function() use ($message, $type) {
            printf(
                '<div class="notice notice-%s is-dismissible"><p><strong>Emailit:</strong> %s</p></div>',
                esc_attr($type),
                esc_html($message)
            );
        });
    }

    /**
     * Send error notification email
     */
    private function send_error_email($error_code, $message, $context) {
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');

        $subject = sprintf('[%s] Emailit Error: %s', $site_name, $error_code);

        $body = sprintf(
            "An error occurred with the Emailit Integration plugin:\n\n" .
            "Error Code: %s\n" .
            "Message: %s\n" .
            "Time: %s\n" .
            "Site: %s\n\n" .
            "Context: %s\n\n" .
            "Please check your WordPress admin for more details.",
            $error_code,
            $message,
            current_time('mysql'),
            home_url(),
            wp_json_encode($context, JSON_PRETTY_PRINT)
        );

        // Use regular wp_mail to avoid recursion
        wp_mail($admin_email, $subject, $body);
    }

    /**
     * Get error statistics
     */
    public function get_error_stats($days = 7) {
        $history = get_transient('emailit_error_history') ?: array();

        $cutoff = time() - ($days * 24 * 3600);
        $recent_errors = array_filter($history, function($entry) use ($cutoff) {
            return strtotime($entry['timestamp']) >= $cutoff;
        });

        $stats = array();
        foreach ($recent_errors as $error) {
            $code = $error['error_code'];
            if (!isset($stats[$code])) {
                $stats[$code] = array('count' => 0, 'level' => $error['level']);
            }
            $stats[$code]['count']++;
        }

        return $stats;
    }

    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_headers = array(
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CF_CONNECTING_IP',
            'REMOTE_ADDR'
        );

        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = trim($_SERVER[$header]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
    }

    /**
     * Clear error history
     */
    public function clear_error_history() {
        delete_transient('emailit_error_history');
        delete_transient($this->circuit_breaker_key);
        delete_option('emailit_circuit_breaker_active');
        delete_option('emailit_api_disabled_until');
        delete_option('emailit_quota_exceeded');
    }

    /**
     * Get error analytics summary
     */
    public function get_error_analytics() {
        if ($this->error_analytics) {
            return $this->error_analytics->get_analytics_summary();
        }
        return null;
    }

    /**
     * Get error statistics
     */
    public function get_error_statistics($period = '24h') {
        if ($this->error_analytics) {
            return $this->error_analytics->get_error_statistics($period);
        }
        return null;
    }

    /**
     * Get retry statistics
     */
    public function get_retry_statistics() {
        if ($this->retry_manager) {
            return $this->retry_manager->get_retry_statistics();
        }
        return null;
    }

    /**
     * Get notification statistics
     */
    public function get_notification_statistics() {
        if ($this->error_notifications) {
            return $this->error_notifications->get_notification_statistics();
        }
        return null;
    }

    /**
     * Get comprehensive error handling status
     */
    public function get_error_handling_status() {
        return array(
            'circuit_breaker' => array(
                'is_open' => $this->is_circuit_breaker_open(),
                'status' => $this->get_circuit_breaker_status()
            ),
            'analytics' => $this->get_error_analytics(),
            'statistics' => $this->get_error_statistics(),
            'retry_stats' => $this->get_retry_statistics(),
            'notification_stats' => $this->get_notification_statistics()
        );
    }

    /**
     * Get circuit breaker status
     */
    public function get_circuit_breaker_status() {
        $breaker_data = get_transient($this->circuit_breaker_key);
        
        if (!$breaker_data) {
            return 'closed';
        }
        
        return $breaker_data['status'];
    }
}