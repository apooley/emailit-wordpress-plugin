<?php
/**
 * Emailit Metrics Collector
 *
 * Collects and analyzes performance metrics for health monitoring.
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

class Emailit_Metrics_Collector {

    /**
     * Logger instance
     */
    private $logger;

    /**
     * Metrics storage
     */
    private $metrics = array();

    /**
     * Constructor
     */
    public function __construct($logger) {
        $this->logger = $logger;
        $this->init_metrics_collection();
    }

    /**
     * Initialize metrics collection
     */
    private function init_metrics_collection() {
        // Hook into existing systems for real-time metrics
        add_action('emailit_api_request', array($this, 'record_api_request'), 10, 2);
        add_action('emailit_api_response', array($this, 'record_api_response'), 10, 2);
        add_action('emailit_webhook_received', array($this, 'record_webhook_processing'), 10, 2);
        add_action('emailit_queue_processed', array($this, 'record_queue_processing'), 10, 2);
        add_action('emailit_email_sent', array($this, 'record_email_sent'), 10, 2);
        add_action('emailit_email_failed', array($this, 'record_email_failed'), 10, 2);
    }

    /**
     * Record API request
     */
    public function record_api_request($request_data, $email_data) {
        $this->record_metric('api_requests', array(
            'timestamp' => microtime(true),
            'request_size' => strlen(wp_json_encode($request_data)),
            'email_type' => $this->get_email_type($email_data),
            'recipient_count' => count($email_data['to'] ?? array())
        ));
    }

    /**
     * Record API response
     */
    public function record_api_response($response, $email_data) {
        $is_error = is_wp_error($response);
        $response_time = $this->get_response_time();
        
        $this->record_metric('api_responses', array(
            'timestamp' => microtime(true),
            'success' => !$is_error,
            'response_time' => $response_time,
            'error_code' => $is_error ? $response->get_error_code() : null,
            'email_type' => $this->get_email_type($email_data)
        ));

        // Update response time statistics
        $this->update_response_time_stats($response_time, !$is_error);
    }

    /**
     * Record webhook processing
     */
    public function record_webhook_processing($webhook_data, $result) {
        $processing_time = $this->get_processing_time();
        
        $this->record_metric('webhook_processing', array(
            'timestamp' => microtime(true),
            'event_type' => $webhook_data['type'] ?? 'unknown',
            'processing_time' => $processing_time,
            'success' => !is_wp_error($result),
            'email_id' => $webhook_data['email_id'] ?? null
        ));
    }

    /**
     * Record queue processing
     */
    public function record_queue_processing($processed_count, $failed_count) {
        $this->record_metric('queue_processing', array(
            'timestamp' => microtime(true),
            'processed_count' => $processed_count,
            'failed_count' => $failed_count,
            'success_rate' => $processed_count > 0 ? ($processed_count - $failed_count) / $processed_count : 0
        ));
    }

    /**
     * Record email sent
     */
    public function record_email_sent($email_id, $email_data) {
        $this->record_metric('email_sent', array(
            'timestamp' => microtime(true),
            'email_id' => $email_id,
            'email_type' => $this->get_email_type($email_data),
            'recipient_count' => count($email_data['to'] ?? array())
        ));
    }

    /**
     * Record email failed
     */
    public function record_email_failed($error, $email_data) {
        $this->record_metric('email_failed', array(
            'timestamp' => microtime(true),
            'error_code' => is_wp_error($error) ? $error->get_error_code() : 'unknown',
            'error_message' => is_wp_error($error) ? $error->get_error_message() : $error,
            'email_type' => $this->get_email_type($email_data)
        ));
    }

    /**
     * Get comprehensive metrics
     */
    public function get_metrics() {
        $metrics = array(
            'timestamp' => current_time('mysql'),
            'api_metrics' => $this->get_api_metrics(),
            'webhook_metrics' => $this->get_webhook_metrics(),
            'queue_metrics' => $this->get_queue_metrics(),
            'email_metrics' => $this->get_email_metrics(),
            'performance_metrics' => $this->get_performance_metrics(),
            'system_metrics' => $this->get_system_metrics()
        );

        return $metrics;
    }

    /**
     * Get API metrics
     */
    private function get_api_metrics() {
        global $wpdb;
        
        $logs_table = $wpdb->prefix . 'emailit_logs';
        $one_hour_ago = date('Y-m-d H:i:s', time() - 3600);
        $one_day_ago = date('Y-m-d H:i:s', time() - 86400);
        
        // Recent API statistics
        $recent_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_requests,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as successful_requests,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_requests
            FROM {$logs_table} 
            WHERE created_at >= %s",
            $one_hour_ago
        ));
        
        // Daily statistics
        $daily_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_requests,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as successful_requests,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_requests
            FROM {$logs_table} 
            WHERE created_at >= %s",
            $one_day_ago
        ));
        
        return array(
            'recent' => array(
                'total_requests' => intval($recent_stats->total_requests),
                'successful_requests' => intval($recent_stats->successful_requests),
                'failed_requests' => intval($recent_stats->failed_requests),
                'success_rate' => $recent_stats->total_requests > 0 ? 
                    $recent_stats->successful_requests / $recent_stats->total_requests : 0
            ),
            'daily' => array(
                'total_requests' => intval($daily_stats->total_requests),
                'successful_requests' => intval($daily_stats->successful_requests),
                'failed_requests' => intval($daily_stats->failed_requests),
                'success_rate' => $daily_stats->total_requests > 0 ? 
                    $daily_stats->successful_requests / $daily_stats->total_requests : 0
            )
        );
    }

    /**
     * Get webhook metrics
     */
    private function get_webhook_metrics() {
        global $wpdb;
        
        $webhook_table = $wpdb->prefix . 'emailit_webhook_logs';
        $one_hour_ago = date('Y-m-d H:i:s', time() - 3600);
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_webhooks,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_webhooks,
                SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as failed_webhooks
            FROM {$webhook_table} 
            WHERE created_at >= %s",
            $one_hour_ago
        ));
        
        return array(
            'total_webhooks' => intval($stats->total_webhooks),
            'successful_webhooks' => intval($stats->successful_webhooks),
            'failed_webhooks' => intval($stats->failed_webhooks),
            'success_rate' => $stats->total_webhooks > 0 ? 
                $stats->successful_webhooks / $stats->total_webhooks : 0
        );
    }

    /**
     * Get queue metrics
     */
    private function get_queue_metrics() {
        global $wpdb;
        
        $queue_table = $wpdb->prefix . 'emailit_queue';
        
        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_emails,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_emails,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_emails,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_emails,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_emails
            FROM {$queue_table}"
        );
        
        return array(
            'total_emails' => intval($stats->total_emails),
            'pending_emails' => intval($stats->pending_emails),
            'processing_emails' => intval($stats->processing_emails),
            'completed_emails' => intval($stats->completed_emails),
            'failed_emails' => intval($stats->failed_emails),
            'completion_rate' => $stats->total_emails > 0 ? 
                $stats->completed_emails / $stats->total_emails : 0
        );
    }

    /**
     * Get email metrics
     */
    private function get_email_metrics() {
        global $wpdb;
        
        $logs_table = $wpdb->prefix . 'emailit_logs';
        $one_hour_ago = date('Y-m-d H:i:s', time() - 3600);
        $one_day_ago = date('Y-m-d H:i:s', time() - 86400);
        
        // Recent email statistics
        $recent_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_emails,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_emails,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_emails,
                SUM(CASE WHEN status = 'bounced' THEN 1 ELSE 0 END) as bounced_emails,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_emails
            FROM {$logs_table} 
            WHERE created_at >= %s",
            $one_hour_ago
        ));
        
        // Daily email statistics
        $daily_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_emails,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_emails,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_emails,
                SUM(CASE WHEN status = 'bounced' THEN 1 ELSE 0 END) as bounced_emails,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_emails
            FROM {$logs_table} 
            WHERE created_at >= %s",
            $one_day_ago
        ));
        
        return array(
            'recent' => array(
                'total_emails' => intval($recent_stats->total_emails),
                'sent_emails' => intval($recent_stats->sent_emails),
                'delivered_emails' => intval($recent_stats->delivered_emails),
                'bounced_emails' => intval($recent_stats->bounced_emails),
                'failed_emails' => intval($recent_stats->failed_emails),
                'delivery_rate' => $recent_stats->sent_emails > 0 ? 
                    $recent_stats->delivered_emails / $recent_stats->sent_emails : 0
            ),
            'daily' => array(
                'total_emails' => intval($daily_stats->total_emails),
                'sent_emails' => intval($daily_stats->sent_emails),
                'delivered_emails' => intval($daily_stats->delivered_emails),
                'bounced_emails' => intval($daily_stats->bounced_emails),
                'failed_emails' => intval($daily_stats->failed_emails),
                'delivery_rate' => $daily_stats->sent_emails > 0 ? 
                    $daily_stats->delivered_emails / $daily_stats->sent_emails : 0
            )
        );
    }

    /**
     * Get performance metrics
     */
    private function get_performance_metrics() {
        return array(
            'memory_usage' => array(
                'current' => memory_get_usage(true),
                'peak' => memory_get_peak_usage(true),
                'limit' => ini_get('memory_limit')
            ),
            'response_times' => $this->get_response_time_stats(),
            'database_performance' => $this->get_database_performance_stats()
        );
    }

    /**
     * Get system metrics
     */
    private function get_system_metrics() {
        return array(
            'php_version' => PHP_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'plugin_version' => EMAILIT_VERSION,
            'server_time' => current_time('mysql'),
            'timezone' => wp_timezone_string(),
            'active_plugins' => count(get_option('active_plugins', array())),
            'theme' => get_template()
        );
    }

    /**
     * Record a metric
     */
    private function record_metric($type, $data) {
        if (!isset($this->metrics[$type])) {
            $this->metrics[$type] = array();
        }
        
        $this->metrics[$type][] = $data;
        
        // Keep only recent metrics (last 1000 entries per type)
        if (count($this->metrics[$type]) > 1000) {
            $this->metrics[$type] = array_slice($this->metrics[$type], -1000);
        }
    }

    /**
     * Get email type from email data
     */
    private function get_email_type($email_data) {
        if (isset($email_data['email_type'])) {
            return $email_data['email_type'];
        }
        
        if (isset($email_data['subject'])) {
            if (strpos($email_data['subject'], 'Test') !== false) {
                return 'test';
            }
            if (strpos($email_data['subject'], 'Notification') !== false) {
                return 'notification';
            }
        }
        
        return 'regular';
    }

    /**
     * Get response time
     */
    private function get_response_time() {
        static $start_time = null;
        
        if ($start_time === null) {
            $start_time = microtime(true);
            return 0;
        }
        
        $response_time = microtime(true) - $start_time;
        $start_time = null;
        return $response_time;
    }

    /**
     * Get processing time
     */
    private function get_processing_time() {
        static $start_time = null;
        
        if ($start_time === null) {
            $start_time = microtime(true);
            return 0;
        }
        
        $processing_time = microtime(true) - $start_time;
        $start_time = null;
        return $processing_time;
    }

    /**
     * Update response time statistics
     */
    private function update_response_time_stats($response_time, $success) {
        $stats = get_option('emailit_response_time_stats', array(
            'total_requests' => 0,
            'total_time' => 0,
            'successful_requests' => 0,
            'successful_time' => 0
        ));
        
        $stats['total_requests']++;
        $stats['total_time'] += $response_time;
        
        if ($success) {
            $stats['successful_requests']++;
            $stats['successful_time'] += $response_time;
        }
        
        update_option('emailit_response_time_stats', $stats);
    }

    /**
     * Get response time statistics
     */
    private function get_response_time_stats() {
        $stats = get_option('emailit_response_time_stats', array(
            'total_requests' => 0,
            'total_time' => 0,
            'successful_requests' => 0,
            'successful_time' => 0
        ));
        
        return array(
            'avg_response_time' => $stats['total_requests'] > 0 ? 
                $stats['total_time'] / $stats['total_requests'] : 0,
            'avg_successful_response_time' => $stats['successful_requests'] > 0 ? 
                $stats['successful_time'] / $stats['successful_requests'] : 0,
            'total_requests' => $stats['total_requests'],
            'successful_requests' => $stats['successful_requests']
        );
    }

    /**
     * Get database performance statistics
     */
    private function get_database_performance_stats() {
        global $wpdb;
        
        // Get slow query log (if available)
        $slow_queries = $wpdb->get_var("SHOW STATUS LIKE 'Slow_queries'");
        $slow_queries = $slow_queries ? intval($slow_queries) : 0;
        
        return array(
            'slow_queries' => $slow_queries,
            'queries_per_second' => $this->calculate_queries_per_second()
        );
    }

    /**
     * Calculate queries per second
     */
    private function calculate_queries_per_second() {
        global $wpdb;
        
        $queries = $wpdb->get_var("SHOW STATUS LIKE 'Queries'");
        $queries = $queries ? intval($queries) : 0;
        
        // This is a simplified calculation
        // In a real implementation, you'd track this over time
        return $queries;
    }
}
