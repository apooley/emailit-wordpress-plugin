<?php
/**
 * Emailit Error Analytics
 *
 * Advanced error tracking, analysis, and reporting system.
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

class Emailit_Error_Analytics {

    /**
     * Logger instance
     */
    private $logger;

    /**
     * Error patterns and trends
     */
    private $error_patterns = array();

    /**
     * Constructor
     */
    public function __construct($logger) {
        $this->logger = $logger;
        $this->init_error_analytics();
    }

    /**
     * Initialize error analytics
     */
    private function init_error_analytics() {
        // Schedule error analysis
        add_action('init', array($this, 'schedule_error_analysis'));
        add_action('emailit_error_analysis', array($this, 'analyze_error_patterns'));
        
        // Hook into error events
        add_action('emailit_error_occurred', array($this, 'track_error'), 10, 3);
        add_action('emailit_error_resolved', array($this, 'track_resolution'), 10, 2);
    }

    /**
     * Schedule error analysis
     */
    public function schedule_error_analysis() {
        if (!wp_next_scheduled('emailit_error_analysis')) {
            wp_schedule_event(time(), 'hourly', 'emailit_error_analysis');
        }
    }

    /**
     * Track error occurrence
     */
    public function track_error($error_code, $error_message, $context = array()) {
        global $wpdb;

        $table = $wpdb->prefix . 'emailit_error_analytics';
        
        $wpdb->insert($table, array(
            'error_code' => $error_code,
            'error_message' => $error_message,
            'error_level' => $context['level'] ?? 'error',
            'context' => wp_json_encode($context),
            'user_id' => get_current_user_id(),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'created_at' => current_time('mysql')
        ));

        // Update error frequency
        $this->update_error_frequency($error_code);
    }

    /**
     * Track error resolution
     */
    public function track_resolution($error_code, $resolution_method) {
        global $wpdb;

        $table = $wpdb->prefix . 'emailit_error_analytics';
        
        $wpdb->update(
            $table,
            array(
                'resolved_at' => current_time('mysql'),
                'resolution_method' => $resolution_method,
                'status' => 'resolved'
            ),
            array(
                'error_code' => $error_code,
                'status' => 'active'
            ),
            array('%s', '%s', '%s'),
            array('%s', '%s')
        );
    }

    /**
     * Analyze error patterns
     */
    public function analyze_error_patterns() {
        $this->analyze_error_frequency();
        $this->analyze_error_trends();
        $this->analyze_error_correlations();
        $this->detect_error_anomalies();
        $this->generate_error_insights();
    }

    /**
     * Analyze error frequency
     */
    private function analyze_error_frequency() {
        global $wpdb;

        $table = $wpdb->prefix . 'emailit_error_analytics';
        $one_hour_ago = date('Y-m-d H:i:s', time() - 3600);
        $one_day_ago = date('Y-m-d H:i:s', time() - 86400);

        // Get error frequency for last hour
        $hourly_errors = $wpdb->get_results($wpdb->prepare(
            "SELECT error_code, COUNT(*) as count 
             FROM {$table} 
             WHERE created_at >= %s 
             GROUP BY error_code 
             ORDER BY count DESC",
            $one_hour_ago
        ));

        // Get error frequency for last day
        $daily_errors = $wpdb->get_results($wpdb->prepare(
            "SELECT error_code, COUNT(*) as count 
             FROM {$table} 
             WHERE created_at >= %s 
             GROUP BY error_code 
             ORDER BY count DESC",
            $one_day_ago
        ));

        // Store frequency analysis
        update_option('emailit_error_frequency_hourly', $hourly_errors);
        update_option('emailit_error_frequency_daily', $daily_errors);

        // Check for high-frequency errors
        foreach ($hourly_errors as $error) {
            if ($error->count > 10) { // More than 10 errors per hour
                $this->trigger_high_frequency_alert($error->error_code, $error->count);
            }
        }
    }

    /**
     * Analyze error trends
     */
    private function analyze_error_trends() {
        global $wpdb;

        $table = $wpdb->prefix . 'emailit_error_analytics';
        
        // Get error trends over last 24 hours (hourly buckets)
        $trends = $wpdb->get_results(
            "SELECT 
                DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as hour,
                error_code,
                COUNT(*) as count
             FROM {$table} 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             GROUP BY hour, error_code
             ORDER BY hour DESC"
        );

        // Analyze trends
        $trend_analysis = array();
        foreach ($trends as $trend) {
            if (!isset($trend_analysis[$trend->error_code])) {
                $trend_analysis[$trend->error_code] = array();
            }
            $trend_analysis[$trend->error_code][] = array(
                'hour' => $trend->hour,
                'count' => intval($trend->count)
            );
        }

        // Calculate trend direction (increasing, decreasing, stable)
        foreach ($trend_analysis as $error_code => $data) {
            if (count($data) >= 3) {
                $recent = array_slice($data, 0, 3);
                $older = array_slice($data, 3, 3);
                
                $recent_avg = array_sum(array_column($recent, 'count')) / count($recent);
                $older_avg = array_sum(array_column($older, 'count')) / count($older);
                
                $trend_direction = 'stable';
                if ($recent_avg > $older_avg * 1.5) {
                    $trend_direction = 'increasing';
                } elseif ($recent_avg < $older_avg * 0.5) {
                    $trend_direction = 'decreasing';
                }
                
                $trend_analysis[$error_code]['trend'] = $trend_direction;
                $trend_analysis[$error_code]['recent_avg'] = $recent_avg;
                $trend_analysis[$error_code]['older_avg'] = $older_avg;
            }
        }

        update_option('emailit_error_trends', $trend_analysis);
    }

    /**
     * Analyze error correlations
     */
    private function analyze_error_correlations() {
        global $wpdb;

        $table = $wpdb->prefix . 'emailit_error_analytics';
        
        // Find errors that occur together
        $correlations = $wpdb->get_results(
            "SELECT 
                e1.error_code as error1,
                e2.error_code as error2,
                COUNT(*) as co_occurrence
             FROM {$table} e1
             JOIN {$table} e2 ON e1.created_at = e2.created_at 
                AND e1.error_code < e2.error_code
             WHERE e1.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             GROUP BY e1.error_code, e2.error_code
             HAVING co_occurrence > 2
             ORDER BY co_occurrence DESC"
        );

        update_option('emailit_error_correlations', $correlations);
    }

    /**
     * Detect error anomalies
     */
    private function detect_error_anomalies() {
        global $wpdb;

        $table = $wpdb->prefix . 'emailit_error_analytics';
        
        // Get baseline error rates (last 7 days)
        $baseline = $wpdb->get_results(
            "SELECT 
                error_code,
                AVG(hourly_count) as avg_hourly,
                STDDEV(hourly_count) as stddev_hourly
             FROM (
                 SELECT 
                     error_code,
                     DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as hour,
                     COUNT(*) as hourly_count
                 FROM {$table}
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                 GROUP BY error_code, hour
             ) hourly_stats
             GROUP BY error_code"
        );

        // Check current hour against baseline
        $current_hour = date('Y-m-d H:00:00');
        $current_errors = $wpdb->get_results($wpdb->prepare(
            "SELECT error_code, COUNT(*) as count
             FROM {$table}
             WHERE created_at >= %s
             GROUP BY error_code",
            $current_hour
        ));

        $anomalies = array();
        foreach ($current_errors as $error) {
            $baseline_data = array_filter($baseline, function($b) use ($error) {
                return $b->error_code === $error->error_code;
            });
            
            if (!empty($baseline_data)) {
                $baseline_data = array_values($baseline_data)[0];
                $threshold = $baseline_data->avg_hourly + (2 * $baseline_data->stddev_hourly);
                
                if ($error->count > $threshold) {
                    $anomalies[] = array(
                        'error_code' => $error->error_code,
                        'current_count' => $error->count,
                        'baseline_avg' => $baseline_data->avg_hourly,
                        'threshold' => $threshold,
                        'severity' => $error->count > ($threshold * 2) ? 'high' : 'medium'
                    );
                }
            }
        }

        if (!empty($anomalies)) {
            $this->trigger_anomaly_alert($anomalies);
        }

        update_option('emailit_error_anomalies', $anomalies);
    }

    /**
     * Generate error insights
     */
    private function generate_error_insights() {
        $insights = array();

        // Get recent error data
        $hourly_errors = get_option('emailit_error_frequency_hourly', array());
        $trends = get_option('emailit_error_trends', array());
        $correlations = get_option('emailit_error_correlations', array());
        $anomalies = get_option('emailit_error_anomalies', array());

        // Generate insights
        if (!empty($hourly_errors)) {
            $top_error = $hourly_errors[0];
            $insights[] = array(
                'type' => 'top_error',
                'message' => sprintf(
                    __('Most frequent error: %s (%d occurrences in last hour)', 'emailit-integration'),
                    $top_error->error_code,
                    $top_error->count
                ),
                'severity' => $top_error->count > 20 ? 'high' : 'medium'
            );
        }

        // Trend insights
        foreach ($trends as $error_code => $trend_data) {
            if (isset($trend_data['trend']) && $trend_data['trend'] === 'increasing') {
                $insights[] = array(
                    'type' => 'trending_error',
                    'message' => sprintf(
                        __('Error %s is trending upward (%.1f vs %.1f avg)', 'emailit-integration'),
                        $error_code,
                        $trend_data['recent_avg'],
                        $trend_data['older_avg']
                    ),
                    'severity' => 'medium'
                );
            }
        }

        // Correlation insights
        if (!empty($correlations)) {
            $top_correlation = $correlations[0];
            $insights[] = array(
                'type' => 'error_correlation',
                'message' => sprintf(
                    __('Errors %s and %s frequently occur together (%d times)', 'emailit-integration'),
                    $top_correlation->error1,
                    $top_correlation->error2,
                    $top_correlation->co_occurrence
                ),
                'severity' => 'low'
            );
        }

        // Anomaly insights
        foreach ($anomalies as $anomaly) {
            $insights[] = array(
                'type' => 'anomaly',
                'message' => sprintf(
                    __('Anomaly detected: %s has %d occurrences (normal: %.1f)', 'emailit-integration'),
                    $anomaly['error_code'],
                    $anomaly['current_count'],
                    $anomaly['baseline_avg']
                ),
                'severity' => $anomaly['severity']
            );
        }

        update_option('emailit_error_insights', $insights);
    }

    /**
     * Get error analytics summary
     */
    public function get_analytics_summary() {
        return array(
            'hourly_errors' => get_option('emailit_error_frequency_hourly', array()),
            'daily_errors' => get_option('emailit_error_frequency_daily', array()),
            'trends' => get_option('emailit_error_trends', array()),
            'correlations' => get_option('emailit_error_correlations', array()),
            'anomalies' => get_option('emailit_error_anomalies', array()),
            'insights' => get_option('emailit_error_insights', array())
        );
    }

    /**
     * Get error statistics
     */
    public function get_error_statistics($period = '24h') {
        global $wpdb;

        $table = $wpdb->prefix . 'emailit_error_analytics';
        
        $time_condition = '';
        switch ($period) {
            case '1h':
                $time_condition = "created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)";
                break;
            case '24h':
                $time_condition = "created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
                break;
            case '7d':
                $time_condition = "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case '30d':
                $time_condition = "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
        }

        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_errors,
                COUNT(DISTINCT error_code) as unique_errors,
                COUNT(CASE WHEN status = 'resolved' THEN 1 END) as resolved_errors,
                AVG(CASE WHEN resolved_at IS NOT NULL THEN 
                    TIMESTAMPDIFF(MINUTE, created_at, resolved_at) 
                END) as avg_resolution_time
             FROM {$table} 
             WHERE {$time_condition}",
            array()
        ));

        return array(
            'total_errors' => intval($stats->total_errors),
            'unique_errors' => intval($stats->unique_errors),
            'resolved_errors' => intval($stats->resolved_errors),
            'avg_resolution_time' => floatval($stats->avg_resolution_time),
            'resolution_rate' => $stats->total_errors > 0 ? 
                ($stats->resolved_errors / $stats->total_errors) * 100 : 0
        );
    }

    /**
     * Update error frequency
     */
    private function update_error_frequency($error_code) {
        $frequency = get_option('emailit_error_frequency', array());
        
        if (!isset($frequency[$error_code])) {
            $frequency[$error_code] = 0;
        }
        
        $frequency[$error_code]++;
        update_option('emailit_error_frequency', $frequency);
    }

    /**
     * Trigger high frequency alert
     */
    private function trigger_high_frequency_alert($error_code, $count) {
        do_action('emailit_high_frequency_error', $error_code, $count);
        
        $this->logger->log(
            "High frequency error detected: {$error_code} ({$count} occurrences)",
            Emailit_Logger::LEVEL_WARNING,
            array('error_code' => $error_code, 'count' => $count)
        );
    }

    /**
     * Trigger anomaly alert
     */
    private function trigger_anomaly_alert($anomalies) {
        do_action('emailit_error_anomaly_detected', $anomalies);
        
        foreach ($anomalies as $anomaly) {
            $this->logger->log(
                "Error anomaly detected: {$anomaly['error_code']} ({$anomaly['current_count']} vs {$anomaly['baseline_avg']} avg)",
                Emailit_Logger::LEVEL_WARNING,
                $anomaly
            );
        }
    }

    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $headers = array(
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = trim($_SERVER[$header]);
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}
