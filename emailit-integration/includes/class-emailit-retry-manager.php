<?php
/**
 * Emailit Retry Manager
 *
 * Advanced retry mechanisms with exponential backoff and intelligent retry strategies.
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

class Emailit_Retry_Manager {

    /**
     * Logger instance
     */
    private $logger;

    /**
     * Retry strategies
     */
    private $retry_strategies = array();

    /**
     * Retry configuration
     */
    private $retry_config = array(
        'max_retries' => 3,
        'base_delay' => 1, // seconds
        'max_delay' => 300, // 5 minutes
        'backoff_multiplier' => 2,
        'jitter' => true
    );

    /**
     * Constructor
     */
    public function __construct($logger) {
        $this->logger = $logger;
        $this->init_retry_strategies();
    }

    /**
     * Initialize retry strategies
     */
    private function init_retry_strategies() {
        $this->retry_strategies = array(
            'network_error' => array(
                'max_retries' => 5,
                'base_delay' => 2,
                'backoff_multiplier' => 2,
                'retry_condition' => array($this, 'is_retryable_network_error')
            ),
            'timeout' => array(
                'max_retries' => 3,
                'base_delay' => 5,
                'backoff_multiplier' => 1.5,
                'retry_condition' => array($this, 'is_retryable_timeout')
            ),
            'rate_limit' => array(
                'max_retries' => 2,
                'base_delay' => 60, // Wait 1 minute for rate limit
                'backoff_multiplier' => 1,
                'retry_condition' => array($this, 'is_retryable_rate_limit')
            ),
            'server_error' => array(
                'max_retries' => 3,
                'base_delay' => 10,
                'backoff_multiplier' => 2,
                'retry_condition' => array($this, 'is_retryable_server_error')
            ),
            'quota_exceeded' => array(
                'max_retries' => 1,
                'base_delay' => 3600, // Wait 1 hour for quota reset
                'backoff_multiplier' => 1,
                'retry_condition' => array($this, 'is_retryable_quota_error')
            )
        );
    }

    /**
     * Execute retry with intelligent strategy
     */
    public function execute_retry($operation, $error_code, $context = array()) {
        $strategy = $this->get_retry_strategy($error_code);
        $retry_count = $context['retry_count'] ?? 0;
        
        if ($retry_count >= $strategy['max_retries']) {
            $this->logger->log(
                "Max retries exceeded for {$error_code}",
                Emailit_Logger::LEVEL_ERROR,
                array('error_code' => $error_code, 'retry_count' => $retry_count)
            );
            return new WP_Error('max_retries_exceeded', 'Maximum retry attempts exceeded');
        }

        // Check if error is retryable
        if (!$this->is_error_retryable($error_code, $context)) {
            return new WP_Error('not_retryable', 'Error is not retryable');
        }

        // Calculate delay with exponential backoff
        $delay = $this->calculate_retry_delay($strategy, $retry_count);
        
        // Add jitter to prevent thundering herd
        if ($strategy['jitter'] ?? true) {
            $delay = $this->add_jitter($delay);
        }

        $this->logger->log(
            "Retrying {$error_code} in {$delay} seconds (attempt " . ($retry_count + 1) . "/{$strategy['max_retries']})",
            Emailit_Logger::LEVEL_INFO,
            array('error_code' => $error_code, 'delay' => $delay, 'retry_count' => $retry_count + 1)
        );

        // Schedule retry
        return $this->schedule_retry($operation, $error_code, $context, $delay, $retry_count + 1);
    }

    /**
     * Get retry strategy for error code
     */
    private function get_retry_strategy($error_code) {
        return $this->retry_strategies[$error_code] ?? array(
            'max_retries' => $this->retry_config['max_retries'],
            'base_delay' => $this->retry_config['base_delay'],
            'backoff_multiplier' => $this->retry_config['backoff_multiplier'],
            'retry_condition' => array($this, 'is_retryable_default')
        );
    }

    /**
     * Calculate retry delay with exponential backoff
     */
    private function calculate_retry_delay($strategy, $retry_count) {
        $delay = $strategy['base_delay'] * pow($strategy['backoff_multiplier'], $retry_count);
        return min($delay, $strategy['max_delay'] ?? $this->retry_config['max_delay']);
    }

    /**
     * Add jitter to prevent thundering herd
     */
    private function add_jitter($delay) {
        // Add random jitter of ±25%
        $jitter_factor = 0.75 + (mt_rand(0, 50) / 100); // 0.75 to 1.25
        return intval($delay * $jitter_factor);
    }

    /**
     * Schedule retry operation
     */
    private function schedule_retry($operation, $error_code, $context, $delay, $retry_count) {
        // Store retry data
        $retry_id = $this->store_retry_data($operation, $error_code, $context, $retry_count);
        
        // Schedule WordPress cron for retry
        $hook = 'emailit_retry_' . $error_code;
        $args = array($retry_id, $operation, $context);
        
        wp_schedule_single_event(time() + $delay, $hook, $args);
        
        // Hook into the retry event
        add_action($hook, array($this, 'execute_retry_operation'), 10, 3);
        
        return array(
            'retry_id' => $retry_id,
            'scheduled_at' => time() + $delay,
            'retry_count' => $retry_count
        );
    }

    /**
     * Execute retry operation
     */
    public function execute_retry_operation($retry_id, $operation, $context) {
        $retry_data = $this->get_retry_data($retry_id);
        
        if (!$retry_data) {
            $this->logger->log("Retry data not found for ID: {$retry_id}", Emailit_Logger::LEVEL_ERROR);
            return;
        }

        try {
            // Execute the operation
            $result = call_user_func($operation, $context);
            
            if (is_wp_error($result)) {
                // Still failing, try again if within retry limits
                $this->logger->log(
                    "Retry failed for {$retry_data['error_code']}",
                    Emailit_Logger::LEVEL_WARNING,
                    array('retry_id' => $retry_id, 'error' => $result->get_error_message())
                );
                
                $retry_result = $this->execute_retry($operation, $retry_data['error_code'], array_merge($context, array(
                    'retry_count' => $retry_data['retry_count']
                )));
                
                if (is_wp_error($retry_result)) {
                    $this->mark_retry_failed($retry_id, $result);
                }
            } else {
                // Success!
                $this->mark_retry_success($retry_id, $result);
                $this->logger->log(
                    "Retry successful for {$retry_data['error_code']}",
                    Emailit_Logger::LEVEL_INFO,
                    array('retry_id' => $retry_id)
                );
            }
        } catch (Exception $e) {
            $this->logger->log(
                "Retry exception: " . $e->getMessage(),
                Emailit_Logger::LEVEL_ERROR,
                array('retry_id' => $retry_id, 'exception' => $e->getTraceAsString())
            );
            $this->mark_retry_failed($retry_id, new WP_Error('retry_exception', $e->getMessage()));
        }
    }

    /**
     * Store retry data
     */
    private function store_retry_data($operation, $error_code, $context, $retry_count) {
        global $wpdb;

        $table = $wpdb->prefix . 'emailit_retries';
        
        $retry_id = wp_generate_uuid4();
        
        $wpdb->insert($table, array(
            'retry_id' => $retry_id,
            'operation' => $operation,
            'error_code' => $error_code,
            'context' => wp_json_encode($context),
            'retry_count' => $retry_count,
            'status' => 'pending',
            'created_at' => current_time('mysql')
        ));

        return $retry_id;
    }

    /**
     * Get retry data
     */
    private function get_retry_data($retry_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'emailit_retries';
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE retry_id = %s",
            $retry_id
        ));

        return $result ? (array) $result : null;
    }

    /**
     * Mark retry as successful
     */
    private function mark_retry_success($retry_id, $result) {
        global $wpdb;

        $table = $wpdb->prefix . 'emailit_retries';
        
        $wpdb->update(
            $table,
            array(
                'status' => 'success',
                'completed_at' => current_time('mysql'),
                'result' => wp_json_encode($result)
            ),
            array('retry_id' => $retry_id),
            array('%s', '%s', '%s'),
            array('%s')
        );
    }

    /**
     * Mark retry as failed
     */
    private function mark_retry_failed($retry_id, $error) {
        global $wpdb;

        $table = $wpdb->prefix . 'emailit_retries';
        
        $wpdb->update(
            $table,
            array(
                'status' => 'failed',
                'completed_at' => current_time('mysql'),
                'error' => is_wp_error($error) ? $error->get_error_message() : $error
            ),
            array('retry_id' => $retry_id),
            array('%s', '%s', '%s'),
            array('%s')
        );
    }

    /**
     * Check if error is retryable
     */
    private function is_error_retryable($error_code, $context) {
        $strategy = $this->get_retry_strategy($error_code);
        
        if (isset($strategy['retry_condition']) && is_callable($strategy['retry_condition'])) {
            return call_user_func($strategy['retry_condition'], $error_code, $context);
        }
        
        return true; // Default to retryable
    }

    /**
     * Retry condition: Network errors
     */
    private function is_retryable_network_error($error_code, $context) {
        // Check if it's a temporary network issue
        $http_code = $context['http_code'] ?? 0;
        return in_array($http_code, array(0, 500, 502, 503, 504, 522, 524));
    }

    /**
     * Retry condition: Timeout errors
     */
    private function is_retryable_timeout($error_code, $context) {
        // Always retry timeout errors
        return true;
    }

    /**
     * Retry condition: Rate limit errors
     */
    private function is_retryable_rate_limit($error_code, $context) {
        // Check if we have retry-after header
        $retry_after = $context['retry_after'] ?? 0;
        return $retry_after > 0 && $retry_after < 3600; // Only retry if less than 1 hour
    }

    /**
     * Retry condition: Server errors
     */
    private function is_retryable_server_error($error_code, $context) {
        $http_code = $context['http_code'] ?? 0;
        return in_array($http_code, array(500, 502, 503, 504));
    }

    /**
     * Retry condition: Quota errors
     */
    private function is_retryable_quota_error($error_code, $context) {
        // Only retry once for quota errors, and only if it's close to reset time
        $quota_reset = $context['quota_reset'] ?? 0;
        return $quota_reset > 0 && $quota_reset < time() + 3600; // Reset within 1 hour
    }

    /**
     * Retry condition: Default
     */
    private function is_retryable_default($error_code, $context) {
        // Default retryable errors
        $retryable_errors = array(
            'network_error',
            'timeout',
            'server_error',
            'temporary_failure'
        );
        
        return in_array($error_code, $retryable_errors);
    }

    /**
     * Get retry statistics
     */
    public function get_retry_statistics() {
        global $wpdb;

        $table = $wpdb->prefix . 'emailit_retries';
        
        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_retries,
                COUNT(CASE WHEN status = 'success' THEN 1 END) as successful_retries,
                COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_retries,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_retries,
                AVG(CASE WHEN completed_at IS NOT NULL THEN 
                    TIMESTAMPDIFF(SECOND, created_at, completed_at) 
                END) as avg_retry_time
             FROM {$table}"
        );

        return array(
            'total_retries' => intval($stats->total_retries),
            'successful_retries' => intval($stats->successful_retries),
            'failed_retries' => intval($stats->failed_retries),
            'pending_retries' => intval($stats->pending_retries),
            'success_rate' => $stats->total_retries > 0 ? 
                ($stats->successful_retries / $stats->total_retries) * 100 : 0,
            'avg_retry_time' => floatval($stats->avg_retry_time)
        );
    }

    /**
     * Clean up old retry data
     */
    public function cleanup_old_retries($days = 7) {
        global $wpdb;

        $table = $wpdb->prefix . 'emailit_retries';
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY) 
             AND status IN ('success', 'failed')",
            $days
        ));

        $this->logger->log(
            "Cleaned up {$deleted} old retry records",
            Emailit_Logger::LEVEL_INFO,
            array('deleted_count' => $deleted, 'days' => $days)
        );

        return $deleted;
    }
}
