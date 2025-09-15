<?php
/**
 * Emailit Health Monitoring Database Migration
 *
 * Handles database schema for health monitoring system.
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

class Emailit_Health_Migration {

    /**
     * Create health monitoring tables
     */
    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Health checks table
        $health_checks_table = $wpdb->prefix . 'emailit_health_checks';
        $health_checks_sql = "CREATE TABLE $health_checks_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            check_type varchar(50) NOT NULL,
            status varchar(20) NOT NULL,
            message text NOT NULL,
            data longtext,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY check_type (check_type),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Alerts table
        $alerts_table = $wpdb->prefix . 'emailit_alerts';
        $alerts_sql = "CREATE TABLE $alerts_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            alert_type varchar(50) NOT NULL,
            severity varchar(20) NOT NULL,
            message text NOT NULL,
            data longtext,
            status varchar(20) NOT NULL DEFAULT 'active',
            dismissed tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            dismissed_at datetime NULL,
            PRIMARY KEY (id),
            KEY alert_type (alert_type),
            KEY severity (severity),
            KEY status (status),
            KEY dismissed (dismissed),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Metrics table
        $metrics_table = $wpdb->prefix . 'emailit_metrics';
        $metrics_sql = "CREATE TABLE $metrics_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            metric_type varchar(50) NOT NULL,
            metric_name varchar(100) NOT NULL,
            metric_value decimal(15,4) NOT NULL,
            metric_data longtext,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY metric_type (metric_type),
            KEY metric_name (metric_name),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Performance logs table
        $performance_table = $wpdb->prefix . 'emailit_performance_logs';
        $performance_sql = "CREATE TABLE $performance_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            operation varchar(100) NOT NULL,
            duration decimal(10,4) NOT NULL,
            memory_usage bigint(20) NOT NULL,
            query_count int(11) NOT NULL DEFAULT 0,
            error_count int(11) NOT NULL DEFAULT 0,
            context longtext,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY operation (operation),
            KEY duration (duration),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        dbDelta($health_checks_table_sql);
        dbDelta($alerts_sql);
        dbDelta($metrics_sql);
        dbDelta($performance_sql);

        // Create indexes for better performance
        self::create_indexes();
    }

    /**
     * Create additional indexes
     */
    private static function create_indexes() {
        global $wpdb;

        $health_checks_table = $wpdb->prefix . 'emailit_health_checks';
        $alerts_table = $wpdb->prefix . 'emailit_alerts';
        $metrics_table = $wpdb->prefix . 'emailit_metrics';
        $performance_table = $wpdb->prefix . 'emailit_performance_logs';

        // Composite indexes for common queries
        $wpdb->query("CREATE INDEX idx_health_checks_type_status ON {$health_checks_table} (check_type, status)");
        $wpdb->query("CREATE INDEX idx_health_checks_type_created ON {$health_checks_table} (check_type, created_at)");
        
        $wpdb->query("CREATE INDEX idx_alerts_type_status ON {$alerts_table} (alert_type, status)");
        $wpdb->query("CREATE INDEX idx_alerts_severity_created ON {$alerts_table} (severity, created_at)");
        
        $wpdb->query("CREATE INDEX idx_metrics_type_name ON {$metrics_table} (metric_type, metric_name)");
        $wpdb->query("CREATE INDEX idx_metrics_type_created ON {$metrics_table} (metric_type, created_at)");
        
        $wpdb->query("CREATE INDEX idx_performance_operation_created ON {$performance_table} (operation, created_at)");
    }

    /**
     * Clean up old data
     */
    public static function cleanup_old_data() {
        global $wpdb;

        $health_checks_table = $wpdb->prefix . 'emailit_health_checks';
        $alerts_table = $wpdb->prefix . 'emailit_alerts';
        $metrics_table = $wpdb->prefix . 'emailit_metrics';
        $performance_table = $wpdb->prefix . 'emailit_performance_logs';

        // Get retention settings
        $health_retention = get_option('emailit_health_retention_days', 30);
        $alerts_retention = get_option('emailit_alerts_retention_days', 90);
        $metrics_retention = get_option('emailit_metrics_retention_days', 7);
        $performance_retention = get_option('emailit_performance_retention_days', 14);

        // Clean up health checks older than retention period
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$health_checks_table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $health_retention
        ));

        // Clean up dismissed alerts older than retention period
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$alerts_table} WHERE dismissed = 1 AND dismissed_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $alerts_retention
        ));

        // Clean up old metrics
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$metrics_table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $metrics_retention
        ));

        // Clean up old performance logs
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$performance_table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $performance_retention
        ));
    }

    /**
     * Get table sizes
     */
    public static function get_table_sizes() {
        global $wpdb;

        $tables = array(
            'emailit_health_checks',
            'emailit_alerts',
            'emailit_metrics',
            'emailit_performance_logs'
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
     * Optimize tables
     */
    public static function optimize_tables() {
        global $wpdb;

        $tables = array(
            'emailit_health_checks',
            'emailit_alerts',
            'emailit_metrics',
            'emailit_performance_logs'
        );

        foreach ($tables as $table) {
            $table_name = $wpdb->prefix . $table;
            $wpdb->query("OPTIMIZE TABLE {$table_name}");
        }
    }

    /**
     * Get health monitoring statistics
     */
    public static function get_health_stats() {
        global $wpdb;

        $health_checks_table = $wpdb->prefix . 'emailit_health_checks';
        $alerts_table = $wpdb->prefix . 'emailit_alerts';

        // Initialize default stats
        $health_stats = (object) array(
            'total_checks' => 0,
            'successful_checks' => 0,
            'warning_checks' => 0,
            'error_checks' => 0
        );

        $alert_stats = (object) array(
            'total_alerts' => 0,
            'critical_alerts' => 0,
            'warning_alerts' => 0,
            'dismissed_alerts' => 0
        );

        // Health check statistics - only if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$health_checks_table}'")) {
            $health_stats = $wpdb->get_row(
                "SELECT 
                    COUNT(*) as total_checks,
                    SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_checks,
                    SUM(CASE WHEN status = 'warning' THEN 1 ELSE 0 END) as warning_checks,
                    SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as error_checks
                FROM {$health_checks_table}"
            );
        }

        // Alert statistics - only if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$alerts_table}'")) {
            $alert_stats = $wpdb->get_row(
                "SELECT 
                    COUNT(*) as total_alerts,
                    SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical_alerts,
                    SUM(CASE WHEN severity = 'warning' THEN 1 ELSE 0 END) as warning_alerts,
                    SUM(CASE WHEN dismissed = 1 THEN 1 ELSE 0 END) as dismissed_alerts
                FROM {$alerts_table}"
            );
        }

        return array(
            'health_checks' => array(
                'total' => intval($health_stats->total_checks),
                'successful' => intval($health_stats->successful_checks),
                'warnings' => intval($health_stats->warning_checks),
                'errors' => intval($health_stats->error_checks)
            ),
            'alerts' => array(
                'total' => intval($alert_stats->total_alerts),
                'critical' => intval($alert_stats->critical_alerts),
                'warnings' => intval($alert_stats->warning_alerts),
                'dismissed' => intval($alert_stats->dismissed_alerts)
            )
        );
    }
}
