<?php
/**
 * Emailit Error Handling Database Migration
 *
 * Handles database schema for advanced error handling system.
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

class Emailit_Error_Migration {

    /**
     * Create error handling tables
     */
    public static function create_tables() {
        global $wpdb;

        // Check if tables already exist to avoid conflicts
        $tables_to_check = array(
            $wpdb->prefix . 'emailit_error_analytics',
            $wpdb->prefix . 'emailit_retries',
            $wpdb->prefix . 'emailit_error_notifications',
            $wpdb->prefix . 'emailit_error_patterns'
        );

        $existing_tables = array();
        foreach ($tables_to_check as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'")) {
                $existing_tables[] = $table;
            }
        }

        // If all tables exist, just create indexes
        if (count($existing_tables) === count($tables_to_check)) {
            self::create_indexes();
            return;
        }

        $charset_collate = $wpdb->get_charset_collate();

        // Error analytics table
        $error_analytics_table = $wpdb->prefix . 'emailit_error_analytics';
        $error_analytics_sql = "CREATE TABLE $error_analytics_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            error_code varchar(100) NOT NULL,
            error_message text NOT NULL,
            error_level varchar(20) NOT NULL,
            context longtext,
            user_id bigint(20) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text,
            request_uri text,
            status varchar(20) NOT NULL DEFAULT 'active',
            resolved_at datetime NULL,
            resolution_method varchar(100) DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY error_code (error_code),
            KEY error_level (error_level),
            KEY status (status),
            KEY created_at (created_at),
            KEY user_id (user_id)
        ) $charset_collate;";

        // Retry management table
        $retries_table = $wpdb->prefix . 'emailit_retries';
        $retries_sql = "CREATE TABLE $retries_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            retry_id varchar(36) NOT NULL UNIQUE,
            operation varchar(255) NOT NULL,
            error_code varchar(100) NOT NULL,
            context longtext,
            retry_count int(11) NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'pending',
            result longtext,
            error text,
            created_at datetime NOT NULL,
            completed_at datetime NULL,
            PRIMARY KEY (id),
            KEY error_code (error_code),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Error notifications table
        $notifications_table = $wpdb->prefix . 'emailit_error_notifications';
        $notifications_sql = "CREATE TABLE $notifications_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            error_code varchar(100) NOT NULL,
            channel varchar(50) NOT NULL,
            status varchar(20) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY error_code (error_code),
            KEY channel (channel),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Error patterns table
        $patterns_table = $wpdb->prefix . 'emailit_error_patterns';
        $patterns_sql = "CREATE TABLE $patterns_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            pattern_name varchar(100) NOT NULL,
            pattern_type varchar(50) NOT NULL,
            pattern_data longtext NOT NULL,
            frequency int(11) NOT NULL DEFAULT 0,
            last_seen datetime NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY pattern_name (pattern_name),
            KEY pattern_type (pattern_type),
            KEY frequency (frequency),
            KEY last_seen (last_seen)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        dbDelta($error_analytics_sql);
        dbDelta($retries_sql);
        dbDelta($notifications_sql);
        dbDelta($patterns_sql);

        // Create additional indexes for better performance
        self::create_indexes();
    }

    /**
     * Safely initialize error handling tables
     * This method can be called multiple times without errors
     */
    public static function safe_init() {
        // Only create tables if they don't exist
        self::create_tables();
    }

    /**
     * Create additional indexes
     */
    private static function create_indexes() {
        global $wpdb;

        $error_analytics_table = $wpdb->prefix . 'emailit_error_analytics';
        $retries_table = $wpdb->prefix . 'emailit_retries';
        $notifications_table = $wpdb->prefix . 'emailit_error_notifications';
        $patterns_table = $wpdb->prefix . 'emailit_error_patterns';

        // Define indexes to create
        $indexes = array(
            $error_analytics_table => array(
                'idx_error_analytics_code_status' => '(error_code, status)',
                'idx_error_analytics_level_created' => '(error_level, created_at)',
                'idx_error_analytics_user_created' => '(user_id, created_at)'
            ),
            $retries_table => array(
                'idx_retries_code_status' => '(error_code, status)',
                'idx_retries_operation_created' => '(operation, created_at)'
            ),
            $notifications_table => array(
                'idx_notifications_code_channel' => '(error_code, channel)',
                'idx_notifications_status_created' => '(status, created_at)'
            ),
            $patterns_table => array(
                'idx_patterns_type_frequency' => '(pattern_type, frequency)'
            )
        );

        // Create indexes only if they don't exist
        foreach ($indexes as $table => $table_indexes) {
            // Check if table exists first
            if (!$wpdb->get_var("SHOW TABLES LIKE '{$table}'")) {
                continue;
            }

            foreach ($table_indexes as $index_name => $index_columns) {
                // Check if index already exists using SHOW INDEX
                $index_exists = $wpdb->get_var($wpdb->prepare(
                    "SHOW INDEX FROM {$table} WHERE Key_name = %s",
                    $index_name
                ));

                if (!$index_exists) {
                    $result = $wpdb->query("CREATE INDEX {$index_name} ON {$table} {$index_columns}");
                    if ($result === false) {
                        // Log the error but don't stop execution
                        error_log("Failed to create index {$index_name} on {$table}: " . $wpdb->last_error);
                    }
                }
            }
        }
    }

    /**
     * Clean up old error data
     */
    public static function cleanup_old_data() {
        global $wpdb;

        $error_analytics_table = $wpdb->prefix . 'emailit_error_analytics';
        $retries_table = $wpdb->prefix . 'emailit_retries';
        $notifications_table = $wpdb->prefix . 'emailit_error_notifications';
        $patterns_table = $wpdb->prefix . 'emailit_error_patterns';

        // Get retention settings
        $analytics_retention = get_option('emailit_error_analytics_retention_days', 30);
        $retries_retention = get_option('emailit_retries_retention_days', 7);
        $notifications_retention = get_option('emailit_notifications_retention_days', 14);
        $patterns_retention = get_option('emailit_patterns_retention_days', 90);

        // Clean up error analytics older than retention period (only if table exists)
        if ($wpdb->get_var("SHOW TABLES LIKE '{$error_analytics_table}'")) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$error_analytics_table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $analytics_retention
            ));
        }

        // Clean up completed retries older than retention period (only if table exists)
        if ($wpdb->get_var("SHOW TABLES LIKE '{$retries_table}'")) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$retries_table} WHERE status IN ('success', 'failed') AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $retries_retention
            ));
        }

        // Clean up old notifications (only if table exists)
        if ($wpdb->get_var("SHOW TABLES LIKE '{$notifications_table}'")) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$notifications_table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $notifications_retention
            ));
        }

        // Clean up old patterns (only if table exists)
        if ($wpdb->get_var("SHOW TABLES LIKE '{$patterns_table}'")) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$patterns_table} WHERE last_seen < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $patterns_retention
            ));
        }
    }

    /**
     * Get error handling table sizes
     */
    public static function get_table_sizes() {
        global $wpdb;

        $tables = array(
            'emailit_error_analytics',
            'emailit_retries',
            'emailit_error_notifications',
            'emailit_error_patterns'
        );

        $sizes = array();

        foreach ($tables as $table) {
            $table_name = $wpdb->prefix . $table;
            
            $size = $wpdb->get_row($wpdb->prepare(
                "SELECT 
                    table_name,
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb,
                    table_rows
                FROM information_schema.tables 
                WHERE table_schema = %s AND table_name = %s",
                DB_NAME,
                $table_name
            ));

            if ($size) {
                $sizes[$table] = array(
                    'size_mb' => floatval($size->size_mb),
                    'rows' => intval($size->table_rows)
                );
            }
        }

        return $sizes;
    }

    /**
     * Optimize error handling tables
     */
    public static function optimize_tables() {
        global $wpdb;

        $tables = array(
            'emailit_error_analytics',
            'emailit_retries',
            'emailit_error_notifications',
            'emailit_error_patterns'
        );

        foreach ($tables as $table) {
            $table_name = $wpdb->prefix . $table;
            $wpdb->query("OPTIMIZE TABLE {$table_name}");
        }
    }

    /**
     * Get error handling statistics
     */
    public static function get_error_statistics() {
        global $wpdb;

        $error_analytics_table = $wpdb->prefix . 'emailit_error_analytics';
        $retries_table = $wpdb->prefix . 'emailit_retries';
        $notifications_table = $wpdb->prefix . 'emailit_error_notifications';

        // Error analytics statistics
        $error_stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_errors,
                COUNT(CASE WHEN status = 'resolved' THEN 1 END) as resolved_errors,
                COUNT(CASE WHEN error_level = 'critical' THEN 1 END) as critical_errors,
                COUNT(CASE WHEN error_level = 'error' THEN 1 END) as error_errors,
                COUNT(CASE WHEN error_level = 'warning' THEN 1 END) as warning_errors
             FROM {$error_analytics_table}"
        );

        // Retry statistics
        $retry_stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_retries,
                COUNT(CASE WHEN status = 'success' THEN 1 END) as successful_retries,
                COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_retries,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_retries
             FROM {$retries_table}"
        );

        // Notification statistics
        $notification_stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_notifications,
                COUNT(CASE WHEN status = 'sent' THEN 1 END) as sent_notifications,
                COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_notifications
             FROM {$notifications_table}"
        );

        return array(
            'errors' => array(
                'total' => intval($error_stats->total_errors),
                'resolved' => intval($error_stats->resolved_errors),
                'critical' => intval($error_stats->critical_errors),
                'error' => intval($error_stats->error_errors),
                'warning' => intval($error_stats->warning_errors)
            ),
            'retries' => array(
                'total' => intval($retry_stats->total_retries),
                'successful' => intval($retry_stats->successful_retries),
                'failed' => intval($retry_stats->failed_retries),
                'pending' => intval($retry_stats->pending_retries)
            ),
            'notifications' => array(
                'total' => intval($notification_stats->total_notifications),
                'sent' => intval($notification_stats->sent_notifications),
                'failed' => intval($notification_stats->failed_notifications)
            )
        );
    }

    /**
     * Get error trends
     */
    public static function get_error_trends($period = '24h') {
        global $wpdb;

        $error_analytics_table = $wpdb->prefix . 'emailit_error_analytics';
        
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

        $trends = $wpdb->get_results(
            "SELECT 
                DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as hour,
                error_level,
                COUNT(*) as count
             FROM {$error_analytics_table} 
             WHERE {$time_condition}
             GROUP BY hour, error_level
             ORDER BY hour DESC"
        );

        return $trends;
    }

    /**
     * Get top errors
     */
    public static function get_top_errors($limit = 10, $period = '24h') {
        global $wpdb;

        $error_analytics_table = $wpdb->prefix . 'emailit_error_analytics';
        
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

        $top_errors = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                error_code,
                error_level,
                COUNT(*) as count,
                MAX(created_at) as last_occurrence
             FROM {$error_analytics_table} 
             WHERE {$time_condition}
             GROUP BY error_code, error_level
             ORDER BY count DESC
             LIMIT %d",
            $limit
        ));

        return $top_errors;
    }
}
