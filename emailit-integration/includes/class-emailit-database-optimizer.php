<?php
/**
 * Emailit Database Optimizer Class
 *
 * Handles database optimization, indexing, and query improvements.
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

class Emailit_Database_Optimizer {

    /**
     * Database table names
     */
    private $logs_table;
    private $webhook_logs_table;
    private $queue_table;

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
     * Optimize all database tables
     */
    public function optimize_tables() {
        global $wpdb;

        $tables = array(
            $this->logs_table,
            $this->webhook_logs_table,
            $this->queue_table
        );

        $results = array();
        foreach ($tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
                $result = $wpdb->query("OPTIMIZE TABLE $table");
                $results[$table] = $result !== false ? 'optimized' : 'failed';
            }
        }

        return $results;
    }

    /**
     * Add missing indexes to improve query performance
     */
    public function add_performance_indexes() {
        global $wpdb;

        $indexes_added = array();

        // Email logs table indexes
        $logs_indexes = array(
            'idx_to_email' => "ALTER TABLE {$this->logs_table} ADD INDEX idx_to_email (to_email(100))",
            'idx_from_email' => "ALTER TABLE {$this->logs_table} ADD INDEX idx_from_email (from_email)",
            'idx_sent_at' => "ALTER TABLE {$this->logs_table} ADD INDEX idx_sent_at (sent_at)",
            'idx_status_created' => "ALTER TABLE {$this->logs_table} ADD INDEX idx_status_created (status, created_at)",
            'idx_message_id' => "ALTER TABLE {$this->logs_table} ADD INDEX idx_message_id (message_id)",
            'idx_created_at_status' => "ALTER TABLE {$this->logs_table} ADD INDEX idx_created_at_status (created_at, status)"
        );

        foreach ($logs_indexes as $index_name => $sql) {
            if ($this->add_index_if_not_exists($this->logs_table, $index_name, $sql)) {
                $indexes_added[] = $index_name;
            }
        }

        // Webhook logs table indexes
        $webhook_indexes = array(
            'idx_processed_at' => "ALTER TABLE {$this->webhook_logs_table} ADD INDEX idx_processed_at (processed_at)",
            'idx_status' => "ALTER TABLE {$this->webhook_logs_table} ADD INDEX idx_status (status)",
            'idx_event_id' => "ALTER TABLE {$this->webhook_logs_table} ADD INDEX idx_event_id (event_id)",
            'idx_webhook_request_id' => "ALTER TABLE {$this->webhook_logs_table} ADD INDEX idx_webhook_request_id (webhook_request_id)",
            'idx_email_id_event_type' => "ALTER TABLE {$this->webhook_logs_table} ADD INDEX idx_email_id_event_type (email_id, event_type)"
        );

        foreach ($webhook_indexes as $index_name => $sql) {
            if ($this->add_index_if_not_exists($this->webhook_logs_table, $index_name, $sql)) {
                $indexes_added[] = $index_name;
            }
        }

        // Queue table indexes
        $queue_indexes = array(
            'idx_created_at' => "ALTER TABLE {$this->queue_table} ADD INDEX idx_created_at (created_at)",
            'idx_processed_at' => "ALTER TABLE {$this->queue_table} ADD INDEX idx_processed_at (processed_at)",
            'idx_status_created' => "ALTER TABLE {$this->queue_table} ADD INDEX idx_status_created (status, created_at)",
            'idx_priority_scheduled' => "ALTER TABLE {$this->queue_table} ADD INDEX idx_priority_scheduled (priority, scheduled_at)"
        );

        foreach ($queue_indexes as $index_name => $sql) {
            if ($this->add_index_if_not_exists($this->queue_table, $index_name, $sql)) {
                $indexes_added[] = $index_name;
            }
        }

        return $indexes_added;
    }

    /**
     * Add index if it doesn't exist
     */
    private function add_index_if_not_exists($table, $index_name, $sql) {
        global $wpdb;

        // Check if index exists
        $existing_indexes = $wpdb->get_results("SHOW INDEX FROM $table", ARRAY_A);
        $index_exists = false;

        foreach ($existing_indexes as $index) {
            if ($index['Key_name'] === $index_name) {
                $index_exists = true;
                break;
            }
        }

        if (!$index_exists) {
            $result = $wpdb->query($sql);
            return $result !== false;
        }

        return false; // Index already exists
    }

    /**
     * Get database performance statistics
     */
    public function get_performance_stats() {
        global $wpdb;

        $stats = array();

        // Table sizes
        $tables = array($this->logs_table, $this->webhook_logs_table, $this->queue_table);
        foreach ($tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
                $table_stats = $wpdb->get_row("
                    SELECT 
                        table_name,
                        table_rows,
                        ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'size_mb',
                        ROUND((data_length / 1024 / 1024), 2) AS 'data_mb',
                        ROUND((index_length / 1024 / 1024), 2) AS 'index_mb'
                    FROM information_schema.tables 
                    WHERE table_schema = DATABASE() 
                    AND table_name = '$table'
                ", ARRAY_A);

                if ($table_stats) {
                    $stats[$table] = $table_stats;
                }
            }
        }

        // Index usage statistics
        $index_stats = $wpdb->get_results("
            SELECT 
                table_name,
                index_name,
                cardinality,
                sub_part,
                nullable
            FROM information_schema.statistics 
            WHERE table_schema = DATABASE() 
            AND table_name IN ('" . implode("','", $tables) . "')
            ORDER BY table_name, cardinality DESC
        ", ARRAY_A);

        $stats['indexes'] = $index_stats;

        return $stats;
    }

    /**
     * Analyze slow queries and suggest optimizations
     */
    public function analyze_slow_queries() {
        global $wpdb;

        $suggestions = array();

        // Check for common performance issues
        $common_issues = array(
            'missing_indexes' => $this->check_missing_indexes(),
            'large_tables' => $this->check_large_tables(),
            'inefficient_queries' => $this->check_inefficient_queries()
        );

        return $common_issues;
    }

    /**
     * Check for missing indexes
     */
    private function check_missing_indexes() {
        global $wpdb;

        $issues = array();

        // Check email logs table
        $logs_queries = array(
            "SELECT * FROM {$this->logs_table} WHERE to_email LIKE '%@example.com%'" => 'idx_to_email',
            "SELECT * FROM {$this->logs_table} WHERE status = 'sent' AND created_at > '2024-01-01'" => 'idx_status_created',
            "SELECT * FROM {$this->logs_table} WHERE from_email = 'admin@example.com'" => 'idx_from_email'
        );

        foreach ($logs_queries as $query => $suggested_index) {
            $explain = $wpdb->get_results("EXPLAIN $query", ARRAY_A);
            if ($explain && $explain[0]['type'] === 'ALL') {
                $issues[] = "Consider adding index: $suggested_index for query: " . substr($query, 0, 50) . "...";
            }
        }

        return $issues;
    }

    /**
     * Check for large tables that might need optimization
     */
    private function check_large_tables() {
        global $wpdb;

        $issues = array();

        $tables = array($this->logs_table, $this->webhook_logs_table, $this->queue_table);
        foreach ($tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
                $row_count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
                if ($row_count > 100000) { // More than 100k rows
                    $issues[] = "Table $table has $row_count rows - consider archiving old data";
                }
            }
        }

        return $issues;
    }

    /**
     * Check for inefficient queries
     */
    private function check_inefficient_queries() {
        $issues = array();

        // Common inefficient patterns
        $patterns = array(
            'LIKE queries without proper indexing' => 'Use full-text indexes for text searches',
            'ORDER BY without index' => 'Add indexes on ORDER BY columns',
            'COUNT(*) on large tables' => 'Consider using approximate counts or caching',
            'JOINs without proper indexes' => 'Ensure foreign key columns are indexed'
        );

        return $patterns;
    }

    /**
     * Create optimized database schema
     */
    public function create_optimized_schema() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Optimized email logs table
        $sql_logs = "CREATE TABLE {$this->logs_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            email_id varchar(255) DEFAULT NULL,
            token varchar(255) DEFAULT NULL,
            message_id varchar(255) DEFAULT NULL,
            queue_id bigint(20) UNSIGNED DEFAULT NULL,
            to_email text NOT NULL,
            from_email varchar(255) NOT NULL,
            reply_to varchar(255) DEFAULT NULL,
            subject text NOT NULL,
            body_html longtext,
            body_text longtext,
            status varchar(50) DEFAULT 'pending',
            details text,
            sent_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_email_id (email_id),
            KEY idx_token (token),
            KEY idx_message_id (message_id),
            KEY idx_queue_id (queue_id),
            KEY idx_status (status),
            KEY idx_created_at (created_at),
            KEY idx_sent_at (sent_at),
            KEY idx_to_email (to_email(100)),
            KEY idx_from_email (from_email),
            KEY idx_status_created (status, created_at),
            KEY idx_created_at_status (created_at, status),
            FULLTEXT KEY idx_subject (subject),
            FULLTEXT KEY idx_body_text (body_text)
        ) $charset_collate;";

        // Optimized webhook logs table
        $sql_webhooks = "CREATE TABLE {$this->webhook_logs_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            webhook_request_id varchar(255) DEFAULT NULL,
            event_id varchar(255) DEFAULT NULL,
            event_type varchar(100) DEFAULT NULL,
            email_id varchar(255) DEFAULT NULL,
            status varchar(50) DEFAULT NULL,
            details text,
            raw_payload longtext,
            processed_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_email_id (email_id),
            KEY idx_event_type (event_type),
            KEY idx_event_id (event_id),
            KEY idx_status (status),
            KEY idx_processed_at (processed_at),
            KEY idx_webhook_request_id (webhook_request_id),
            KEY idx_email_id_event_type (email_id, event_type)
        ) $charset_collate;";

        // Optimized queue table
        $sql_queue = "CREATE TABLE {$this->queue_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            priority int(11) DEFAULT 10,
            email_data longtext NOT NULL,
            status varchar(20) DEFAULT 'pending',
            attempts int(11) DEFAULT 0,
            last_error text DEFAULT NULL,
            scheduled_at datetime DEFAULT CURRENT_TIMESTAMP,
            processed_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_status_priority (status, priority),
            KEY idx_scheduled_at (scheduled_at),
            KEY idx_attempts (attempts),
            KEY idx_created_at (created_at),
            KEY idx_processed_at (processed_at),
            KEY idx_status_created (status, created_at),
            KEY idx_priority_scheduled (priority, scheduled_at)
        ) $charset_collate;";

        return array(
            'logs' => $sql_logs,
            'webhooks' => $sql_webhooks,
            'queue' => $sql_queue
        );
    }

    /**
     * Get query execution plan for optimization
     */
    public function explain_query($query) {
        global $wpdb;

        return $wpdb->get_results("EXPLAIN $query", ARRAY_A);
    }

    /**
     * Clean up orphaned records
     */
    public function cleanup_orphaned_records() {
        global $wpdb;

        $cleaned = array();

        // Clean up orphaned webhook logs
        $orphaned_webhooks = $wpdb->query("
            DELETE w FROM {$this->webhook_logs_table} w
            LEFT JOIN {$this->logs_table} l ON w.email_id = l.email_id
            WHERE l.email_id IS NULL
            AND w.processed_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");

        if ($orphaned_webhooks > 0) {
            $cleaned['orphaned_webhooks'] = $orphaned_webhooks;
        }

        // Clean up orphaned queue items
        $orphaned_queue = $wpdb->query("
            DELETE q FROM {$this->queue_table} q
            LEFT JOIN {$this->logs_table} l ON q.id = l.queue_id
            WHERE l.queue_id IS NULL
            AND q.created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");

        if ($orphaned_queue > 0) {
            $cleaned['orphaned_queue'] = $orphaned_queue;
        }

        return $cleaned;
    }

    /**
     * Archive old records to improve performance
     */
    public function archive_old_records($days = 90) {
        global $wpdb;

        $archived = array();
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        // Archive old email logs (keep only essential data)
        $archived_logs = $wpdb->query($wpdb->prepare("
            UPDATE {$this->logs_table} 
            SET body_html = NULL, 
                body_text = NULL, 
                details = CONCAT('ARCHIVED: ', details)
            WHERE created_at < %s
            AND status IN ('delivered', 'bounced', 'complained')
        ", $cutoff_date));

        if ($archived_logs > 0) {
            $archived['email_logs'] = $archived_logs;
        }

        // Archive old webhook logs
        $archived_webhooks = $wpdb->query($wpdb->prepare("
            UPDATE {$this->webhook_logs_table} 
            SET raw_payload = NULL,
                details = CONCAT('ARCHIVED: ', details)
            WHERE processed_at < %s
        ", $cutoff_date));

        if ($archived_webhooks > 0) {
            $archived['webhook_logs'] = $archived_webhooks;
        }

        return $archived;
    }
}
