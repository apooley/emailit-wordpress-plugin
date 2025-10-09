<?php
/**
 * Emailit Log Archiver Class
 *
 * Provides log archiving, partitioning, and automatic purging functionality
 * for webhook logs and email logs.
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

class Emailit_Log_Archiver {

    /**
     * Database table names
     */
    private $logs_table;
    private $webhook_logs_table;
    private $queue_table;

    /**
     * Archive retention periods (in days)
     */
    const EMAIL_LOG_RETENTION = 90;
    const WEBHOOK_LOG_RETENTION = 30;
    const QUEUE_LOG_RETENTION = 7;

    /**
     * Archive batch size
     */
    const ARCHIVE_BATCH_SIZE = 1000;

    /**
     * Constructor
     */
    public function __construct() {
        $wpdb = $GLOBALS['wpdb'];
        
        $this->logs_table = $wpdb->prefix . 'emailit_logs';
        $this->webhook_logs_table = $wpdb->prefix . 'emailit_webhook_logs';
        $this->queue_table = $wpdb->prefix . 'emailit_queue';

        // Schedule archiving tasks
        $this->schedule_archiving_tasks();
    }

    /**
     * Schedule archiving tasks
     */
    private function schedule_archiving_tasks() {
        // Schedule daily archiving
        if (!wp_next_scheduled('emailit_archive_logs')) {
            wp_schedule_event(time(), 'daily', 'emailit_archive_logs');
        }
        add_action('emailit_archive_logs', array($this, 'archive_old_logs'));

        // Schedule weekly purging
        if (!wp_next_scheduled('emailit_purge_archives')) {
            wp_schedule_event(time(), 'weekly', 'emailit_purge_archives');
        }
        add_action('emailit_purge_archives', array($this, 'purge_old_archives'));

        // Schedule monthly partitioning
        if (!wp_next_scheduled('emailit_partition_tables')) {
            wp_schedule_event(time(), 'monthly', 'emailit_partition_tables');
        }
        add_action('emailit_partition_tables', array($this, 'create_monthly_partitions'));
    }

    /**
     * Archive old logs to separate tables
     */
    public function archive_old_logs() {
        $this->archive_email_logs();
        $this->archive_webhook_logs();
        $this->archive_queue_logs();

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Emailit] Log archiving completed');
        }
    }

    /**
     * Archive email logs older than retention period
     */
    private function archive_email_logs() {
        $wpdb = $GLOBALS['wpdb'];
        
        $retention_date = date('Y-m-d H:i:s', strtotime('-' . self::EMAIL_LOG_RETENTION . ' days'));
        $archive_table = $wpdb->prefix . 'emailit_logs_archive';

        // Create archive table if it doesn't exist
        $this->create_archive_table('emailit_logs_archive');

        // Archive logs in batches
        $offset = 0;
        do {
            $logs_to_archive = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$this->logs_table} 
                 WHERE created_at < %s 
                 ORDER BY created_at ASC 
                 LIMIT %d OFFSET %d",
                $retention_date,
                self::ARCHIVE_BATCH_SIZE,
                $offset
            ), ARRAY_A);

            if (empty($logs_to_archive)) {
                break;
            }

            // Insert into archive table
            foreach ($logs_to_archive as $log) {
                $wpdb->insert($archive_table, $log);
            }

            // Delete from main table
            $log_ids = array_column($logs_to_archive, 'id');
            $placeholders = implode(',', array_fill(0, count($log_ids), '%d'));
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$this->logs_table} WHERE id IN ($placeholders)",
                $log_ids
            ));

            $offset += self::ARCHIVE_BATCH_SIZE;

            // Prevent memory issues
            if ($offset % (self::ARCHIVE_BATCH_SIZE * 10) === 0) {
                wp_cache_flush();
            }

        } while (count($logs_to_archive) === self::ARCHIVE_BATCH_SIZE);
    }

    /**
     * Archive webhook logs older than retention period
     */
    private function archive_webhook_logs() {
        $wpdb = $GLOBALS['wpdb'];
        
        $retention_date = date('Y-m-d H:i:s', strtotime('-' . self::WEBHOOK_LOG_RETENTION . ' days'));
        $archive_table = $wpdb->prefix . 'emailit_webhook_logs_archive';

        // Create archive table if it doesn't exist
        $this->create_archive_table('emailit_webhook_logs_archive');

        // Archive logs in batches
        $offset = 0;
        do {
            $logs_to_archive = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$this->webhook_logs_table} 
                 WHERE processed_at < %s 
                 ORDER BY processed_at ASC 
                 LIMIT %d OFFSET %d",
                $retention_date,
                self::ARCHIVE_BATCH_SIZE,
                $offset
            ), ARRAY_A);

            if (empty($logs_to_archive)) {
                break;
            }

            // Insert into archive table
            foreach ($logs_to_archive as $log) {
                $wpdb->insert($archive_table, $log);
            }

            // Delete from main table
            $log_ids = array_column($logs_to_archive, 'id');
            $placeholders = implode(',', array_fill(0, count($log_ids), '%d'));
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$this->webhook_logs_table} WHERE id IN ($placeholders)",
                $log_ids
            ));

            $offset += self::ARCHIVE_BATCH_SIZE;

        } while (count($logs_to_archive) === self::ARCHIVE_BATCH_SIZE);
    }

    /**
     * Archive queue logs older than retention period
     */
    private function archive_queue_logs() {
        $wpdb = $GLOBALS['wpdb'];
        
        $retention_date = date('Y-m-d H:i:s', strtotime('-' . self::QUEUE_LOG_RETENTION . ' days'));
        $archive_table = $wpdb->prefix . 'emailit_queue_archive';

        // Create archive table if it doesn't exist
        $this->create_archive_table('emailit_queue_archive');

        // Archive completed/failed queue items
        $offset = 0;
        do {
            $logs_to_archive = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$this->queue_table} 
                 WHERE created_at < %s 
                 AND status IN ('completed', 'failed')
                 ORDER BY created_at ASC 
                 LIMIT %d OFFSET %d",
                $retention_date,
                self::ARCHIVE_BATCH_SIZE,
                $offset
            ), ARRAY_A);

            if (empty($logs_to_archive)) {
                break;
            }

            // Insert into archive table
            foreach ($logs_to_archive as $log) {
                $wpdb->insert($archive_table, $log);
            }

            // Delete from main table
            $log_ids = array_column($logs_to_archive, 'id');
            $placeholders = implode(',', array_fill(0, count($log_ids), '%d'));
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$this->queue_table} WHERE id IN ($placeholders)",
                $log_ids
            ));

            $offset += self::ARCHIVE_BATCH_SIZE;

        } while (count($logs_to_archive) === self::ARCHIVE_BATCH_SIZE);
    }

    /**
     * Create archive table with same structure as main table
     */
    private function create_archive_table($table_suffix) {
        $wpdb = $GLOBALS['wpdb'];
        $archive_table = $wpdb->prefix . $table_suffix;

        // Check if archive table exists
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES 
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
            DB_NAME,
            $archive_table
        ));

        if ($table_exists) {
            return; // Table already exists
        }

        // Get the structure of the main table
        $main_table = str_replace('_archive', '', $table_suffix);
        $main_table = $wpdb->prefix . $main_table;

        $create_sql = $wpdb->get_row("SHOW CREATE TABLE `$main_table`", ARRAY_A);
        
        if (isset($create_sql['Create Table'])) {
            // Modify the CREATE statement for archive table
            $archive_sql = str_replace(
                "CREATE TABLE `$main_table`",
                "CREATE TABLE `$archive_table`",
                $create_sql['Create Table']
            );

            // Remove AUTO_INCREMENT and PRIMARY KEY constraints for archive
            $archive_sql = preg_replace('/AUTO_INCREMENT=\d+/', '', $archive_sql);
            $archive_sql = preg_replace('/PRIMARY KEY \(`id`\)/', 'KEY `id` (`id`)', $archive_sql);

            $wpdb->query($archive_sql);

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("[Emailit] Created archive table: $archive_table");
            }
        }
    }

    /**
     * Purge old archives (older than 1 year)
     */
    public function purge_old_archives() {
        $wpdb = $GLOBALS['wpdb'];
        
        $purge_date = date('Y-m-d H:i:s', strtotime('-1 year'));
        $archive_tables = array(
            $wpdb->prefix . 'emailit_logs_archive',
            $wpdb->prefix . 'emailit_webhook_logs_archive',
            $wpdb->prefix . 'emailit_queue_archive'
        );

        foreach ($archive_tables as $table) {
            // Check if table exists
            $table_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES 
                 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
                DB_NAME,
                $table
            ));

            if (!$table_exists) {
                continue;
            }

            // Determine date column based on table
            $date_column = 'created_at';
            if (strpos($table, 'webhook') !== false) {
                $date_column = 'processed_at';
            }

            // Delete old records
            $deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM `$table` WHERE `$date_column` < %s",
                $purge_date
            ));

            if (defined('WP_DEBUG') && WP_DEBUG && $deleted > 0) {
                error_log("[Emailit] Purged $deleted old records from $table");
            }
        }
    }

    /**
     * Create monthly partitions for large tables
     */
    public function create_monthly_partitions() {
        $wpdb = $GLOBALS['wpdb'];
        
        // Only partition if table has more than 100k records
        $logs_count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->logs_table}");
        
        if ($logs_count < 100000) {
            return; // Table not large enough for partitioning
        }

        // Check if table is already partitioned
        $partition_info = $wpdb->get_row("SHOW CREATE TABLE {$this->logs_table}", ARRAY_A);
        
        if (strpos($partition_info['Create Table'], 'PARTITION BY') !== false) {
            return; // Already partitioned
        }

        // Create monthly partitions for the next 12 months
        $this->create_monthly_partitions_for_table($this->logs_table, 'created_at');
    }

    /**
     * Create monthly partitions for a specific table
     */
    private function create_monthly_partitions_for_table($table, $date_column) {
        $wpdb = $GLOBALS['wpdb'];
        
        $partitions = array();
        
        // Create partitions for the next 12 months
        for ($i = 0; $i < 12; $i++) {
            $month_start = date('Y-m-01', strtotime("+$i months"));
            $month_end = date('Y-m-t', strtotime("+$i months"));
            $partition_name = 'p' . date('Ym', strtotime("+$i months"));
            
            $partitions[] = "PARTITION $partition_name VALUES LESS THAN ('" . date('Y-m-01', strtotime("+" . ($i + 1) . " months")) . "')";
        }

        // Add a catch-all partition for future dates
        $partitions[] = "PARTITION p_future VALUES LESS THAN MAXVALUE";

        $partition_sql = "ALTER TABLE `$table` PARTITION BY RANGE (TO_DAYS(`$date_column`)) (" . implode(', ', $partitions) . ")";
        
        $result = $wpdb->query($partition_sql);
        
        if ($result !== false && defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[Emailit] Created monthly partitions for table $table");
        }
    }

    /**
     * Get archive statistics
     */
    public function get_archive_stats() {
        $wpdb = $GLOBALS['wpdb'];
        
        $stats = array();
        
        $archive_tables = array(
            'emailit_logs_archive' => 'created_at',
            'emailit_webhook_logs_archive' => 'processed_at',
            'emailit_queue_archive' => 'created_at'
        );

        foreach ($archive_tables as $table => $date_column) {
            $full_table_name = $wpdb->prefix . $table;
            
            // Check if table exists
            $table_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES 
                 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
                DB_NAME,
                $full_table_name
            ));

            if ($table_exists) {
                $count = $wpdb->get_var("SELECT COUNT(*) FROM `$full_table_name`");
                $oldest = $wpdb->get_var("SELECT MIN(`$date_column`) FROM `$full_table_name`");
                $newest = $wpdb->get_var("SELECT MAX(`$date_column`) FROM `$full_table_name`");
                
                $stats[$table] = array(
                    'count' => (int) $count,
                    'oldest_record' => $oldest,
                    'newest_record' => $newest,
                    'table_size' => $this->get_table_size($full_table_name)
                );
            } else {
                $stats[$table] = array(
                    'count' => 0,
                    'oldest_record' => null,
                    'newest_record' => null,
                    'table_size' => 0
                );
            }
        }

        return $stats;
    }

    /**
     * Get table size in bytes
     */
    private function get_table_size($table) {
        $wpdb = $GLOBALS['wpdb'];
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
             FROM information_schema.TABLES 
             WHERE table_schema = %s AND table_name = %s",
            DB_NAME,
            $table
        ), ARRAY_A);

        return $result ? (float) $result['size_mb'] : 0;
    }

    /**
     * Manual archive trigger (for admin use)
     */
    public function manual_archive($table_type = 'all') {
        switch ($table_type) {
            case 'email_logs':
                $this->archive_email_logs();
                break;
            case 'webhook_logs':
                $this->archive_webhook_logs();
                break;
            case 'queue_logs':
                $this->archive_queue_logs();
                break;
            case 'all':
            default:
                $this->archive_old_logs();
                break;
        }

        return array(
            'success' => true,
            'message' => "Archiving completed for $table_type",
            'timestamp' => current_time('mysql')
        );
    }

    /**
     * Get retention settings
     */
    public function get_retention_settings() {
        return array(
            'email_logs' => self::EMAIL_LOG_RETENTION,
            'webhook_logs' => self::WEBHOOK_LOG_RETENTION,
            'queue_logs' => self::QUEUE_LOG_RETENTION,
            'archive_purge' => 365 // 1 year
        );
    }

    /**
     * Update retention settings
     */
    public function update_retention_settings($settings) {
        $validated_settings = array();
        
        // Validate and sanitize settings
        if (isset($settings['email_logs']) && is_numeric($settings['email_logs'])) {
            $validated_settings['email_logs'] = max(30, min(365, (int) $settings['email_logs']));
        }
        
        if (isset($settings['webhook_logs']) && is_numeric($settings['webhook_logs'])) {
            $validated_settings['webhook_logs'] = max(7, min(90, (int) $settings['webhook_logs']));
        }
        
        if (isset($settings['queue_logs']) && is_numeric($settings['queue_logs'])) {
            $validated_settings['queue_logs'] = max(1, min(30, (int) $settings['queue_logs']));
        }

        // Store settings
        update_option('emailit_retention_settings', $validated_settings);
        
        return $validated_settings;
    }
}
