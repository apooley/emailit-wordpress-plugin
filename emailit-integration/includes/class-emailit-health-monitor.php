<?php
/**
 * Emailit Health Monitor
 *
 * Comprehensive health monitoring, alerting, and performance tracking system.
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

class Emailit_Health_Monitor {

    /**
     * Logger instance
     */
    private $logger;

    /**
     * Alert manager instance
     */
    private $alert_manager;

    /**
     * Metrics collector instance
     */
    private $metrics_collector;

    /**
     * Health check results cache
     */
    private $health_cache = array();

    /**
     * Health check intervals (in seconds)
     */
    private $check_intervals = array(
        'api_connectivity' => 300,      // 5 minutes
        'webhook_endpoint' => 600,      // 10 minutes
        'database_health' => 3600,      // 1 hour
        'queue_processing' => 900,      // 15 minutes
        'fluentcrm_integration' => 1800, // 30 minutes
        'error_rates' => 3600,          // 1 hour
        'performance_metrics' => 300    // 5 minutes
    );

    /**
     * Alert thresholds
     */
    private $alert_thresholds = array(
        'api_response_time' => 5.0,     // seconds
        'error_rate' => 0.05,           // 5%
        'queue_backlog' => 100,         // emails
        'memory_usage' => 0.8,          // 80%
        'webhook_failures' => 10        // per hour
    );

    /**
     * Constructor
     */
    public function __construct($logger) {
        $this->logger = $logger;
        $this->alert_manager = new Emailit_Alert_Manager($logger);
        $this->metrics_collector = new Emailit_Metrics_Collector($logger);
        
        // Initialize health monitoring
        $this->init_health_monitoring();
    }

    /**
     * Initialize health monitoring
     */
    private function init_health_monitoring() {
        // Schedule health checks
        add_action('init', array($this, 'schedule_health_checks'));
        
        // Register REST API endpoints
        add_action('rest_api_init', array($this, 'register_health_endpoints'));
        
        // Hook into existing systems for real-time monitoring
        add_action('emailit_api_response', array($this, 'track_api_response'), 10, 2);
        add_action('emailit_webhook_received', array($this, 'track_webhook_processing'), 10, 2);
        add_action('emailit_queue_processed', array($this, 'track_queue_processing'), 10, 2);
    }

    /**
     * Schedule health checks
     */
    public function schedule_health_checks() {
        // Schedule API connectivity check
        if (!wp_next_scheduled('emailit_health_check_api')) {
            wp_schedule_event(time(), 'emailit_5min', 'emailit_health_check_api');
        }

        // Schedule webhook health check
        if (!wp_next_scheduled('emailit_health_check_webhook')) {
            wp_schedule_event(time(), 'emailit_10min', 'emailit_health_check_webhook');
        }

        // Schedule database health check
        if (!wp_next_scheduled('emailit_health_check_database')) {
            wp_schedule_event(time(), 'emailit_hourly', 'emailit_health_check_database');
        }

        // Schedule queue health check
        if (!wp_next_scheduled('emailit_health_check_queue')) {
            wp_schedule_event(time(), 'emailit_15min', 'emailit_health_check_queue');
        }

        // Schedule FluentCRM health check (if available)
        if (class_exists('FluentCrm\App\App') && !wp_next_scheduled('emailit_health_check_fluentcrm')) {
            wp_schedule_event(time(), 'emailit_30min', 'emailit_health_check_fluentcrm');
        }

        // Schedule error rate analysis
        if (!wp_next_scheduled('emailit_health_check_errors')) {
            wp_schedule_event(time(), 'emailit_hourly', 'emailit_health_check_errors');
        }

        // Schedule performance metrics collection
        if (!wp_next_scheduled('emailit_health_check_performance')) {
            wp_schedule_event(time(), 'emailit_5min', 'emailit_health_check_performance');
        }

        // Hook into scheduled events
        add_action('emailit_health_check_api', array($this, 'check_api_connectivity'));
        add_action('emailit_health_check_webhook', array($this, 'check_webhook_endpoint'));
        add_action('emailit_health_check_database', array($this, 'check_database_health'));
        add_action('emailit_health_check_queue', array($this, 'check_queue_processing'));
        add_action('emailit_health_check_fluentcrm', array($this, 'check_fluentcrm_integration'));
        add_action('emailit_health_check_errors', array($this, 'check_error_rates'));
        add_action('emailit_health_check_performance', array($this, 'check_performance_metrics'));
    }

    /**
     * Register health monitoring REST API endpoints
     */
    public function register_health_endpoints() {
        // Health status endpoint
        register_rest_route('emailit/v1', '/health/status', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_health_status'),
            'permission_callback' => array($this, 'check_health_permissions')
        ));

        // Health metrics endpoint
        register_rest_route('emailit/v1', '/health/metrics', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_health_metrics'),
            'permission_callback' => array($this, 'check_health_permissions')
        ));

        // Health history endpoint
        register_rest_route('emailit/v1', '/health/history', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_health_history'),
            'permission_callback' => array($this, 'check_health_permissions')
        ));

        // Manual health check trigger
        register_rest_route('emailit/v1', '/health/check', array(
            'methods' => 'POST',
            'callback' => array($this, 'trigger_manual_check'),
            'permission_callback' => array($this, 'check_health_permissions')
        ));
    }

    /**
     * Check API connectivity
     */
    public function check_api_connectivity() {
        $start_time = microtime(true);
        $api_key = get_option('emailit_api_key', '');
        
        if (empty($api_key)) {
            $this->record_health_check('api_connectivity', 'error', 'No API key configured');
            return;
        }

        // Test API connectivity with a simple request
        $test_data = array(
            'to' => 'test@example.com',
            'subject' => 'Health Check Test',
            'message' => 'This is a health check test email.'
        );

        $api = new Emailit_API($this->logger);
        $response = $api->send_email($test_data);

        $response_time = microtime(true) - $start_time;
        $status = is_wp_error($response) ? 'error' : 'success';
        $message = is_wp_error($response) ? $response->get_error_message() : 'API connectivity OK';

        $this->record_health_check('api_connectivity', $status, $message, array(
            'response_time' => $response_time,
            'error_code' => is_wp_error($response) ? $response->get_error_code() : null
        ));

        // Check if response time exceeds threshold
        if ($response_time > $this->alert_thresholds['api_response_time']) {
            $this->alert_manager->trigger_alert('performance', 'warning', 
                sprintf('API response time is slow: %.2fs', $response_time));
        }
    }

    /**
     * Check webhook endpoint health
     */
    public function check_webhook_endpoint() {
        $webhook_url = rest_url('emailit/v1/webhook');
        $health_url = rest_url('emailit/v1/webhook/health');

        // Test webhook endpoint accessibility
        $response = wp_remote_get($health_url, array(
            'timeout' => 10,
            'sslverify' => false
        ));

        if (is_wp_error($response)) {
            $this->record_health_check('webhook_endpoint', 'error', 
                'Webhook endpoint not accessible: ' . $response->get_error_message());
            return;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($status_code === 200) {
            $this->record_health_check('webhook_endpoint', 'success', 'Webhook endpoint accessible');
        } else {
            $this->record_health_check('webhook_endpoint', 'error', 
                sprintf('Webhook endpoint returned status %d', $status_code));
        }

        // Check webhook processing performance
        $this->check_webhook_processing_performance();
    }

    /**
     * Check database health
     */
    public function check_database_health() {
        global $wpdb;

        $issues = array();
        $status = 'success';

        // Check table integrity
        $tables = array('emailit_logs', 'emailit_webhook_logs', 'emailit_queue');
        
        foreach ($tables as $table) {
            $table_name = $wpdb->prefix . $table;
            $result = $wpdb->get_var("CHECK TABLE {$table_name}");
            
            if (strpos($result, 'OK') === false) {
                $issues[] = "Table {$table} has integrity issues: {$result}";
                $status = 'error';
            }
        }

        // Check index usage
        $index_issues = $this->check_database_indexes();
        if (!empty($index_issues)) {
            $issues = array_merge($issues, $index_issues);
            $status = 'warning';
        }

        // Check query performance
        $slow_queries = $this->check_slow_queries();
        if (!empty($slow_queries)) {
            $issues[] = 'Slow queries detected: ' . implode(', ', $slow_queries);
            $status = 'warning';
        }

        $message = empty($issues) ? 'Database health OK' : implode('; ', $issues);
        $this->record_health_check('database_health', $status, $message, array(
            'issues' => $issues,
            'slow_queries' => $slow_queries
        ));
    }

    /**
     * Check queue processing health
     */
    public function check_queue_processing() {
        global $wpdb;

        $queue_table = $wpdb->prefix . 'emailit_queue';
        
        // Get queue statistics
        $total_pending = $wpdb->get_var("SELECT COUNT(*) FROM {$queue_table} WHERE status = 'pending'");
        $total_processing = $wpdb->get_var("SELECT COUNT(*) FROM {$queue_table} WHERE status = 'processing'");
        $total_failed = $wpdb->get_var("SELECT COUNT(*) FROM {$queue_table} WHERE status = 'failed'");
        
        // Check for stuck emails (processing for more than 1 hour)
        $stuck_emails = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$queue_table} WHERE status = 'processing' AND updated_at < %s",
            date('Y-m-d H:i:s', time() - 3600)
        ));

        $status = 'success';
        $issues = array();

        if ($stuck_emails > 0) {
            $issues[] = "{$stuck_emails} emails stuck in processing";
            $status = 'error';
        }

        if ($total_pending > $this->alert_thresholds['queue_backlog']) {
            $issues[] = "Queue backlog high: {$total_pending} emails";
            $status = 'warning';
        }

        if ($total_failed > 50) {
            $issues[] = "High failure rate: {$total_failed} failed emails";
            $status = 'warning';
        }

        $message = empty($issues) ? 'Queue processing OK' : implode('; ', $issues);
        $this->record_health_check('queue_processing', $status, $message, array(
            'pending' => $total_pending,
            'processing' => $total_processing,
            'failed' => $total_failed,
            'stuck' => $stuck_emails
        ));
    }

    /**
     * Check FluentCRM integration health
     */
    public function check_fluentcrm_integration() {
        if (!class_exists('FluentCrm\App\App')) {
            $this->record_health_check('fluentcrm_integration', 'info', 'FluentCRM not installed');
            return;
        }

        $issues = array();
        $status = 'success';

        // Check FluentCRM database connectivity
        try {
            $subscriber_count = \FluentCrm\App\Models\Subscriber::count();
            if ($subscriber_count === false) {
                $issues[] = 'Cannot connect to FluentCRM database';
                $status = 'error';
            }
        } catch (Exception $e) {
            $issues[] = 'FluentCRM database error: ' . $e->getMessage();
            $status = 'error';
        }

        // Check bounce processing
        $bounce_processing = get_option('emailit_fluentcrm_enable_action_mapping', false);
        if ($bounce_processing) {
            // Check if bounce processing is working
            $recent_bounces = $this->get_recent_bounce_count();
            if ($recent_bounces > 0) {
                // Check if bounces are being processed
                $processed_bounces = $this->get_processed_bounce_count();
                if ($processed_bounces < $recent_bounces * 0.8) {
                    $issues[] = 'Bounce processing may be delayed';
                    $status = 'warning';
                }
            }
        }

        $message = empty($issues) ? 'FluentCRM integration OK' : implode('; ', $issues);
        $this->record_health_check('fluentcrm_integration', $status, $message, array(
            'subscriber_count' => $subscriber_count ?? 0,
            'bounce_processing_enabled' => $bounce_processing,
            'recent_bounces' => $recent_bounces ?? 0,
            'processed_bounces' => $processed_bounces ?? 0
        ));
    }

    /**
     * Check error rates
     */
    public function check_error_rates() {
        global $wpdb;

        $logs_table = $wpdb->prefix . 'emailit_logs';
        
        // Get error rates for the last hour
        $one_hour_ago = date('Y-m-d H:i:s', time() - 3600);
        
        $total_emails = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$logs_table} WHERE created_at >= %s",
            $one_hour_ago
        ));

        $failed_emails = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$logs_table} WHERE created_at >= %s AND status = 'failed'",
            $one_hour_ago
        ));

        $error_rate = $total_emails > 0 ? $failed_emails / $total_emails : 0;

        $status = 'success';
        $message = 'Error rate OK';

        if ($error_rate > $this->alert_thresholds['error_rate']) {
            $status = 'error';
            $message = sprintf('High error rate: %.2f%%', $error_rate * 100);
            
            $this->alert_manager->trigger_alert('error_rate', 'critical', $message);
        }

        $this->record_health_check('error_rates', $status, $message, array(
            'total_emails' => $total_emails,
            'failed_emails' => $failed_emails,
            'error_rate' => $error_rate
        ));
    }

    /**
     * Check performance metrics
     */
    public function check_performance_metrics() {
        // Memory usage
        $memory_usage = memory_get_usage(true);
        $memory_limit = ini_get('memory_limit');
        $memory_percentage = $memory_usage / $this->parse_memory_limit($memory_limit);

        // Database query performance
        $query_performance = $this->get_database_query_performance();

        $status = 'success';
        $issues = array();

        if ($memory_percentage > $this->alert_thresholds['memory_usage']) {
            $issues[] = sprintf('High memory usage: %.1f%%', $memory_percentage * 100);
            $status = 'warning';
        }

        if ($query_performance['slow_queries'] > 5) {
            $issues[] = "Many slow queries: {$query_performance['slow_queries']}";
            $status = 'warning';
        }

        $message = empty($issues) ? 'Performance OK' : implode('; ', $issues);
        $this->record_health_check('performance_metrics', $status, $message, array(
            'memory_usage' => $memory_percentage,
            'query_performance' => $query_performance
        ));
    }

    /**
     * Record health check result
     */
    private function record_health_check($check_type, $status, $message, $data = array()) {
        $result = array(
            'check_type' => $check_type,
            'status' => $status,
            'message' => $message,
            'timestamp' => current_time('mysql'),
            'data' => $data
        );

        // Store in cache
        $this->health_cache[$check_type] = $result;

        // Store in database
        $this->store_health_check_result($result);

        // Log the result
        $this->logger->log("Health check: {$check_type} - {$status}", 
            $status === 'success' ? Emailit_Logger::LEVEL_INFO : Emailit_Logger::LEVEL_WARNING,
            $result
        );
    }

    /**
     * Store health check result in database
     */
    private function store_health_check_result($result) {
        global $wpdb;

        $table = $wpdb->prefix . 'emailit_health_checks';
        
        $wpdb->insert($table, array(
            'check_type' => $result['check_type'],
            'status' => $result['status'],
            'message' => $result['message'],
            'data' => wp_json_encode($result['data']),
            'created_at' => $result['timestamp']
        ));
    }

    /**
     * Get health status summary
     */
    public function get_health_status() {
        $status = array(
            'overall' => 'success',
            'checks' => $this->health_cache,
            'last_updated' => current_time('mysql'),
            'alerts' => $this->alert_manager->get_active_alerts()
        );

        // Determine overall status
        $has_errors = false;
        $has_warnings = false;

        foreach ($this->health_cache as $check) {
            if ($check['status'] === 'error') {
                $has_errors = true;
            } elseif ($check['status'] === 'warning') {
                $has_warnings = true;
            }
        }

        if ($has_errors) {
            $status['overall'] = 'error';
        } elseif ($has_warnings) {
            $status['overall'] = 'warning';
        }

        return rest_ensure_response($status);
    }

    /**
     * Get health metrics
     */
    public function get_health_metrics() {
        return rest_ensure_response($this->metrics_collector->get_metrics());
    }

    /**
     * Get health history
     */
    public function get_health_history($request) {
        $days = $request->get_param('days') ?: 7;
        $check_type = $request->get_param('check_type');

        global $wpdb;
        $table = $wpdb->prefix . 'emailit_health_checks';
        
        $where_clause = "WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)";
        $params = array($days);

        if ($check_type) {
            $where_clause .= " AND check_type = %s";
            $params[] = $check_type;
        }

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} {$where_clause} ORDER BY created_at DESC LIMIT 1000",
            $params
        ));

        return rest_ensure_response($results);
    }

    /**
     * Trigger manual health check
     */
    public function trigger_manual_check($request) {
        $check_type = $request->get_param('check_type');
        
        if ($check_type && method_exists($this, "check_{$check_type}")) {
            call_user_func(array($this, "check_{$check_type}"));
            return rest_ensure_response(array('success' => true, 'message' => "Health check '{$check_type}' completed"));
        } else {
            // Run all checks
            $this->check_api_connectivity();
            $this->check_webhook_endpoint();
            $this->check_database_health();
            $this->check_queue_processing();
            $this->check_fluentcrm_integration();
            $this->check_error_rates();
            $this->check_performance_metrics();
            
            return rest_ensure_response(array('success' => true, 'message' => 'All health checks completed'));
        }
    }

    /**
     * Check health permissions
     */
    public function check_health_permissions($request) {
        return current_user_can('manage_options');
    }

    /**
     * Track API response for real-time monitoring
     */
    public function track_api_response($response, $email_data) {
        $this->metrics_collector->record_api_response($response, $email_data);
    }

    /**
     * Track webhook processing for real-time monitoring
     */
    public function track_webhook_processing($webhook_data, $result) {
        $this->metrics_collector->record_webhook_processing($webhook_data, $result);
    }

    /**
     * Track queue processing for real-time monitoring
     */
    public function track_queue_processing($processed_count, $failed_count) {
        $this->metrics_collector->record_queue_processing($processed_count, $failed_count);
    }

    /**
     * Helper methods
     */
    private function check_database_indexes() {
        // Implementation for checking database indexes
        return array();
    }

    private function check_slow_queries() {
        // Implementation for checking slow queries
        return array();
    }

    private function check_webhook_processing_performance() {
        // Implementation for checking webhook processing performance
    }

    private function get_recent_bounce_count() {
        global $wpdb;
        $logs_table = $wpdb->prefix . 'emailit_logs';
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$logs_table} WHERE status = 'bounced' AND created_at >= %s",
            date('Y-m-d H:i:s', time() - 3600)
        ));
    }

    private function get_processed_bounce_count() {
        // Implementation for getting processed bounce count
        return 0;
    }

    private function get_database_query_performance() {
        // Implementation for getting database query performance
        return array('slow_queries' => 0);
    }

    private function parse_memory_limit($limit) {
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        $limit = (int) $limit;

        switch ($last) {
            case 'g':
                $limit *= 1024;
            case 'm':
                $limit *= 1024;
            case 'k':
                $limit *= 1024;
        }

        return $limit;
    }
}
