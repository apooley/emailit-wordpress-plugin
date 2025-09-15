<?php
/**
 * Emailit Query Optimizer Class
 *
 * Provides optimized database queries with proper indexing and caching.
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

class Emailit_Query_Optimizer {

    /**
     * Database table names
     */
    private $logs_table;
    private $webhook_logs_table;
    private $queue_table;

    /**
     * Cache group for query results
     */
    const CACHE_GROUP = 'emailit_queries';

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;

        $this->logs_table = $wpdb->prefix . 'emailit_logs';
        $this->webhook_logs_table = $wpdb->prefix . 'emailit_webhook_logs';
        $this->queue_table = $wpdb->prefix . 'emailit_queue';
    }

    /**
     * Get optimized email logs with pagination
     */
    public function get_email_logs($args = array()) {
        global $wpdb;

        $defaults = array(
            'page' => 1,
            'per_page' => 20,
            'status' => '',
            'search' => '',
            'date_from' => '',
            'date_to' => '',
            'orderby' => 'created_at',
            'order' => 'DESC'
        );

        $args = wp_parse_args($args, $defaults);

        // Create cache key
        $cache_key = 'email_logs_' . md5(serialize($args));
        $cached_result = wp_cache_get($cache_key, self::CACHE_GROUP);

        if ($cached_result !== false) {
            return $cached_result;
        }

        // Build WHERE clause with optimized conditions
        $where_conditions = array('1=1');
        $where_values = array();

        // Status filter (use index)
        if (!empty($args['status'])) {
            $where_conditions[] = 'status = %s';
            $where_values[] = $args['status'];
        }

        // Date range filter (use created_at index)
        if (!empty($args['date_from'])) {
            $where_conditions[] = 'created_at >= %s';
            $where_values[] = $args['date_from'] . ' 00:00:00';
        }

        if (!empty($args['date_to'])) {
            $where_conditions[] = 'created_at <= %s';
            $where_values[] = $args['date_to'] . ' 23:59:59';
        }

        // Search filter (use full-text index if available)
        if (!empty($args['search'])) {
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_conditions[] = '(subject LIKE %s OR to_email LIKE %s OR from_email LIKE %s)';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }

        $where_clause = implode(' AND ', $where_conditions);

        // Validate orderby to prevent SQL injection
        $allowed_orderby = array('id', 'created_at', 'subject', 'status', 'to_email', 'from_email', 'sent_at');
        if (!in_array($args['orderby'], $allowed_orderby)) {
            $args['orderby'] = 'created_at';
        }

        // Validate order
        $args['order'] = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        // Calculate offset
        $offset = ($args['page'] - 1) * $args['per_page'];

        // Optimized query with proper indexing and webhook status
        $webhook_logs_table = $wpdb->prefix . 'emailit_webhook_logs';
        $query = $wpdb->prepare("
            SELECT e.id, e.email_id, e.token, e.message_id, e.queue_id, e.to_email, e.from_email, 
                   e.reply_to, e.subject, e.status, e.sent_at, e.created_at, e.updated_at,
                   w.status as webhook_status, w.event_type, w.processed_at as webhook_processed_at
            FROM {$this->logs_table} e
            LEFT JOIN {$webhook_logs_table} w ON e.email_id = w.email_id
            WHERE {$where_clause}
            ORDER BY {$args['orderby']} {$args['order']}
            LIMIT %d OFFSET %d
        ", array_merge($where_values, array($args['per_page'], $offset)));

        $results = $wpdb->get_results($query, ARRAY_A);

        // Get total count for pagination (cached separately)
        $count_cache_key = 'email_logs_count_' . md5(serialize($args));
        $total = wp_cache_get($count_cache_key, self::CACHE_GROUP);

        if ($total === false) {
            $count_query = $wpdb->prepare("
                SELECT COUNT(*) FROM {$this->logs_table}
                WHERE {$where_clause}
            ", $where_values);
            $total = (int) $wpdb->get_var($count_query);
            wp_cache_set($count_cache_key, $total, self::CACHE_GROUP, 300); // 5 minutes
        }

        $result = array(
            'logs' => $results,
            'total' => $total,
            'pages' => ceil($total / $args['per_page']),
            'current_page' => $args['page']
        );

        // Cache for 2 minutes
        wp_cache_set($cache_key, $result, self::CACHE_GROUP, 120);

        return $result;
    }

    /**
     * Get optimized email statistics
     */
    public function get_email_stats($days = 30) {
        global $wpdb;

        $cache_key = 'email_stats_' . $days;
        $cached_result = wp_cache_get($cache_key, self::CACHE_GROUP);

        if ($cached_result !== false) {
            return $cached_result;
        }

        $date_from = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        // Use optimized query with proper indexing
        $stats_query = $wpdb->prepare("
            SELECT 
                COUNT(*) as total_sent,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'bounced' THEN 1 ELSE 0 END) as bounced,
                SUM(CASE WHEN status = 'complained' THEN 1 ELSE 0 END) as complained,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'held' THEN 1 ELSE 0 END) as held,
                SUM(CASE WHEN status = 'delayed' THEN 1 ELSE 0 END) as delayed
            FROM {$this->logs_table}
            WHERE created_at >= %s
        ", $date_from);

        $raw_stats = $wpdb->get_row($stats_query, ARRAY_A);

        if (!$raw_stats) {
            $raw_stats = array(
                'total_sent' => 0,
                'sent' => 0,
                'delivered' => 0,
                'failed' => 0,
                'bounced' => 0,
                'complained' => 0,
                'pending' => 0,
                'held' => 0,
                'delayed' => 0
            );
        }

        // Calculate success rate
        $success_rate = $raw_stats['total_sent'] > 0 ? 
            round((($raw_stats['sent'] + $raw_stats['delivered']) / $raw_stats['total_sent']) * 100, 2) : 0;

        $result = array(
            'total_sent' => (int) $raw_stats['total_sent'],
            'sent' => (int) $raw_stats['sent'],
            'delivered' => (int) $raw_stats['delivered'],
            'failed' => (int) $raw_stats['failed'],
            'bounced' => (int) $raw_stats['bounced'],
            'complained' => (int) $raw_stats['complained'],
            'pending' => (int) $raw_stats['pending'],
            'held' => (int) $raw_stats['held'],
            'delayed' => (int) $raw_stats['delayed'],
            'success_rate' => $success_rate
        );

        // Cache for 5 minutes
        wp_cache_set($cache_key, $result, self::CACHE_GROUP, 300);

        return $result;
    }

    /**
     * Get optimized queue statistics
     */
    public function get_queue_stats() {
        global $wpdb;

        $cache_key = 'queue_stats';
        $cached_result = wp_cache_get($cache_key, self::CACHE_GROUP);

        if ($cached_result !== false) {
            return $cached_result;
        }

        // Optimized query using compound indexes
        $stats_query = "
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            FROM {$this->queue_table}
        ";

        $result = $wpdb->get_row($stats_query, ARRAY_A);

        if (!$result) {
            $result = array(
                'total' => 0,
                'pending' => 0,
                'processing' => 0,
                'completed' => 0,
                'failed' => 0
            );
        }

        // Cache for 1 minute
        wp_cache_set($cache_key, $result, self::CACHE_GROUP, 60);

        return $result;
    }

    /**
     * Get webhook logs for an email (optimized)
     */
    public function get_webhook_logs($email_id, $limit = 10) {
        global $wpdb;

        $cache_key = 'webhook_logs_' . $email_id . '_' . $limit;
        $cached_result = wp_cache_get($cache_key, self::CACHE_GROUP);

        if ($cached_result !== false) {
            return $cached_result;
        }

        // Use compound index (email_id, event_type)
        $query = $wpdb->prepare("
            SELECT id, event_type, status, details, processed_at
            FROM {$this->webhook_logs_table}
            WHERE email_id = %s
            ORDER BY processed_at DESC
            LIMIT %d
        ", $email_id, $limit);

        $result = $wpdb->get_results($query, ARRAY_A);

        // Cache for 2 minutes
        wp_cache_set($cache_key, $result, self::CACHE_GROUP, 120);

        return $result;
    }

    /**
     * Find email by identifier (optimized)
     */
    public function find_email_by_identifier($identifier) {
        global $wpdb;

        $cache_key = 'email_by_id_' . md5($identifier);
        $cached_result = wp_cache_get($cache_key, self::CACHE_GROUP);

        if ($cached_result !== false) {
            return $cached_result;
        }

        // Determine identifier type and use appropriate index
        if (is_numeric($identifier)) {
            $query = $wpdb->prepare("
                SELECT * FROM {$this->logs_table} 
                WHERE id = %d
            ", $identifier);
        } elseif (strpos($identifier, '@') !== false) {
            $query = $wpdb->prepare("
                SELECT * FROM {$this->logs_table} 
                WHERE email_id = %s
            ", $identifier);
        } else {
            $query = $wpdb->prepare("
                SELECT * FROM {$this->logs_table} 
                WHERE token = %s
            ", $identifier);
        }

        $result = $wpdb->get_row($query, ARRAY_A);

        // Cache for 5 minutes
        wp_cache_set($cache_key, $result, self::CACHE_GROUP, 300);

        return $result;
    }

    /**
     * Get recent activity (optimized)
     */
    public function get_recent_activity($limit = 50) {
        global $wpdb;

        $cache_key = 'recent_activity_' . $limit;
        $cached_result = wp_cache_get($cache_key, self::CACHE_GROUP);

        if ($cached_result !== false) {
            return $cached_result;
        }

        // Use created_at index for efficient ordering
        $query = $wpdb->prepare("
            SELECT id, to_email, subject, status, created_at, 'email' as type
            FROM {$this->logs_table}
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY created_at DESC
            LIMIT %d
        ", $limit);

        $result = $wpdb->get_results($query, ARRAY_A);

        // Cache for 1 minute
        wp_cache_set($cache_key, $result, self::CACHE_GROUP, 60);

        return $result;
    }

    /**
     * Get performance metrics
     */
    public function get_performance_metrics() {
        global $wpdb;

        $cache_key = 'performance_metrics';
        $cached_result = wp_cache_get($cache_key, self::CACHE_GROUP);

        if ($cached_result !== false) {
            return $cached_result;
        }

        // Get table sizes and row counts
        $metrics = array();

        $tables = array(
            'logs' => $this->logs_table,
            'webhooks' => $this->webhook_logs_table,
            'queue' => $this->queue_table
        );

        foreach ($tables as $name => $table) {
            $table_info = $wpdb->get_row("
                SELECT 
                    table_rows,
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
                FROM information_schema.tables 
                WHERE table_schema = DATABASE() 
                AND table_name = '$table'
            ", ARRAY_A);

            if ($table_info) {
                $metrics[$name] = array(
                    'rows' => (int) $table_info['table_rows'],
                    'size_mb' => (float) $table_info['size_mb']
                );
            }
        }

        // Get query performance stats
        $slow_queries = $wpdb->get_var("SHOW STATUS LIKE 'Slow_queries'");
        $metrics['slow_queries'] = (int) $slow_queries;

        // Cache for 10 minutes
        wp_cache_set($cache_key, $metrics, self::CACHE_GROUP, 600);

        return $metrics;
    }

    /**
     * Clear query cache
     */
    public function clear_cache($pattern = '') {
        if (empty($pattern)) {
            wp_cache_flush_group(self::CACHE_GROUP);
        } else {
            // Clear specific cache keys matching pattern
            // This is a simplified implementation
            wp_cache_flush_group(self::CACHE_GROUP);
        }
    }

    /**
     * Get database query execution plan
     */
    public function explain_query($query) {
        global $wpdb;

        return $wpdb->get_results("EXPLAIN $query", ARRAY_A);
    }

    /**
     * Optimize specific queries
     */
    public function optimize_query($query_type, $params = array()) {
        global $wpdb;

        switch ($query_type) {
            case 'email_search':
                // Use full-text search if available
                if (!empty($params['search'])) {
                    $search_term = $wpdb->esc_like($params['search']);
                    return $wpdb->prepare("
                        SELECT id, subject, to_email, status, created_at
                        FROM {$this->logs_table}
                        WHERE MATCH(subject, body_text) AGAINST (%s IN NATURAL LANGUAGE MODE)
                        ORDER BY created_at DESC
                        LIMIT %d
                    ", $search_term, $params['limit'] ?? 20);
                }
                break;

            case 'status_breakdown':
                // Use status index for efficient grouping
                return "
                    SELECT status, COUNT(*) as count
                    FROM {$this->logs_table}
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY status
                    ORDER BY count DESC
                ";

            case 'daily_stats':
                // Use date index for efficient date grouping
                return "
                    SELECT 
                        DATE(created_at) as date,
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
                    FROM {$this->logs_table}
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY DATE(created_at)
                    ORDER BY date DESC
                ";
        }

        return null;
    }
}
