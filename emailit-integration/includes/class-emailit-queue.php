<?php
/**
 * Emailit Queue System
 *
 * Handles queuing and background processing of emails for better reliability.
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

class Emailit_Queue {

    /**
     * Queue table name
     */
    private $queue_table;

    /**
     * Logger instance
     */
    private $logger;

    /**
     * Max emails per batch
     */
    private $batch_size;

    /**
     * Max retry attempts
     */
    private $max_retries;

    /**
     * Constructor
     */
    public function __construct($logger = null) {
        global $wpdb;

        $this->queue_table = $wpdb->prefix . 'emailit_queue';
        $this->logger = $logger;
        $this->batch_size = (int) get_option('emailit_queue_batch_size', 10);
        $this->max_retries = (int) get_option('emailit_queue_max_retries', 3);

        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Schedule queue processing
        add_action('emailit_process_queue', array($this, 'process_queue'));

        // Schedule queue cleanup
        add_action('emailit_cleanup_queue', array($this, 'cleanup_queue'));

        // Register cron schedules
        add_filter('cron_schedules', array($this, 'add_cron_schedules'));

        // Initialize cron jobs
        if (!wp_next_scheduled('emailit_process_queue')) {
            wp_schedule_event(time(), 'every_minute', 'emailit_process_queue');
        }

        if (!wp_next_scheduled('emailit_cleanup_queue')) {
            wp_schedule_event(time(), 'hourly', 'emailit_cleanup_queue');
        }
    }

    /**
     * Add custom cron schedules
     */
    public function add_cron_schedules($schedules) {
        $schedules['every_minute'] = array(
            'interval' => 60,
            'display'  => __('Every Minute', 'emailit-integration')
        );

        return $schedules;
    }

    /**
     * Create queue table
     */
    public function create_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->queue_table} (
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
            KEY idx_attempts (attempts)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Add email to queue
     */
    public function add_email($email_data, $priority = 10, $delay = 0) {
        global $wpdb;

        // Validate email data
        if (empty($email_data['to']) || empty($email_data['subject'])) {
            return new WP_Error('invalid_email_data', __('Invalid email data provided.', 'emailit-integration'));
        }

        // Calculate scheduled time
        $scheduled_at = $delay > 0 ?
            date('Y-m-d H:i:s', time() + $delay) :
            current_time('mysql');

        $result = $wpdb->insert(
            $this->queue_table,
            array(
                'priority' => (int) $priority,
                'email_data' => wp_json_encode($email_data),
                'status' => 'pending',
                'scheduled_at' => $scheduled_at,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );

        if ($result === false) {
            return new WP_Error('queue_insert_failed', __('Failed to add email to queue.', 'emailit-integration'));
        }

        $queue_id = $wpdb->insert_id;

        // Log queue addition
        if ($this->logger) {
            $this->logger->log(
                sprintf('Email added to queue (ID: %d)', $queue_id),
                'info',
                array('queue_id' => $queue_id, 'priority' => $priority, 'delay' => $delay)
            );
        }

        // Trigger immediate processing for high priority emails
        if ($priority <= 5) {
            wp_schedule_single_event(time() + 5, 'emailit_process_queue');
        }

        return $queue_id;
    }

    /**
     * Process email queue
     */
    public function process_queue() {
        global $wpdb;

        // Prevent multiple simultaneous processing
        $lock_key = 'emailit_queue_processing';
        if (get_transient($lock_key)) {
            return;
        }

        set_transient($lock_key, 1, 300); // 5 minute lock

        try {
            // Get pending emails ordered by priority and scheduled time
            $emails = $wpdb->get_results($wpdb->prepare("
                SELECT * FROM {$this->queue_table}
                WHERE status = 'pending'
                AND scheduled_at <= %s
                ORDER BY priority ASC, scheduled_at ASC
                LIMIT %d
            ", current_time('mysql'), $this->batch_size), ARRAY_A);

            if (empty($emails)) {
                delete_transient($lock_key);
                return;
            }

            $api = emailit_get_component('api');
            if (!$api) {
                delete_transient($lock_key);
                return;
            }

            foreach ($emails as $queue_item) {
                $this->process_queue_item($queue_item, $api);

                // Small delay to prevent API rate limiting
                usleep(200000); // 0.2 second delay
            }

        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->log(
                    'Queue processing failed: ' . $e->getMessage(),
                    'error',
                    array('exception' => $e->getTraceAsString())
                );
            }
        }

        delete_transient($lock_key);
    }

    /**
     * Process individual queue item
     */
    private function process_queue_item($queue_item, $api) {
        global $wpdb;

        $queue_id = $queue_item['id'];
        $email_data = json_decode($queue_item['email_data'], true);

        if (!$email_data) {
            $this->mark_failed($queue_id, 'Invalid email data format');
            return;
        }

        // Mark as processing
        $wpdb->update(
            $this->queue_table,
            array('status' => 'processing'),
            array('id' => $queue_id),
            array('%s'),
            array('%d')
        );

        // Send email via API
        $result = $api->send_email($email_data);

        if (is_wp_error($result)) {
            $this->handle_failed_email($queue_id, $queue_item['attempts'], $result->get_error_message());
        } else {
            $this->mark_completed($queue_id, $result);
        }
    }

    /**
     * Handle failed email sending
     */
    private function handle_failed_email($queue_id, $attempts, $error_message) {
        global $wpdb;

        $new_attempts = $attempts + 1;

        if ($new_attempts >= $this->max_retries) {
            $this->mark_failed($queue_id, $error_message);
        } else {
            // Calculate retry delay using exponential backoff
            $delay = min(300, pow(2, $new_attempts) * 60); // Max 5 minutes
            $retry_time = date('Y-m-d H:i:s', time() + $delay);

            $wpdb->update(
                $this->queue_table,
                array(
                    'status' => 'pending',
                    'attempts' => $new_attempts,
                    'last_error' => $error_message,
                    'scheduled_at' => $retry_time
                ),
                array('id' => $queue_id),
                array('%s', '%d', '%s', '%s'),
                array('%d')
            );

            if ($this->logger) {
                $this->logger->log(
                    sprintf('Queue item %d scheduled for retry %d/%d at %s', $queue_id, $new_attempts, $this->max_retries, $retry_time),
                    'warning',
                    array('queue_id' => $queue_id, 'error' => $error_message)
                );
            }
        }
    }

    /**
     * Mark queue item as completed
     */
    private function mark_completed($queue_id, $api_response) {
        global $wpdb;

        $wpdb->update(
            $this->queue_table,
            array(
                'status' => 'completed',
                'processed_at' => current_time('mysql')
            ),
            array('id' => $queue_id),
            array('%s', '%s'),
            array('%d')
        );

        if ($this->logger) {
            $this->logger->log(
                sprintf('Queue item %d completed successfully', $queue_id),
                'info',
                array('queue_id' => $queue_id, 'api_response' => $api_response)
            );
        }
    }

    /**
     * Mark queue item as failed
     */
    private function mark_failed($queue_id, $error_message) {
        global $wpdb;

        $wpdb->update(
            $this->queue_table,
            array(
                'status' => 'failed',
                'last_error' => $error_message,
                'processed_at' => current_time('mysql')
            ),
            array('id' => $queue_id),
            array('%s', '%s', '%s'),
            array('%d')
        );

        if ($this->logger) {
            $this->logger->log(
                sprintf('Queue item %d failed permanently', $queue_id),
                'error',
                array('queue_id' => $queue_id, 'error' => $error_message)
            );
        }
    }

    /**
     * Get queue statistics
     */
    public function get_stats() {
        global $wpdb;

        $stats = $wpdb->get_row("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            FROM {$this->queue_table}
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAYS)
        ", ARRAY_A);

        return $stats ?: array(
            'total' => 0,
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0
        );
    }

    /**
     * Get queue items
     */
    public function get_queue_items($args = array()) {
        global $wpdb;

        $defaults = array(
            'status' => '',
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC'
        );

        $args = wp_parse_args($args, $defaults);

        $where = "1=1";
        $values = array();

        if (!empty($args['status'])) {
            $where .= " AND status = %s";
            $values[] = $args['status'];
        }

        $sql = $wpdb->prepare("
            SELECT * FROM {$this->queue_table}
            WHERE {$where}
            ORDER BY {$args['orderby']} {$args['order']}
            LIMIT %d OFFSET %d
        ", array_merge($values, array($args['limit'], $args['offset'])));

        return $wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * Cleanup old queue items
     */
    public function cleanup_queue() {
        global $wpdb;

        $retention_days = (int) get_option('emailit_queue_retention_days', 7);

        if ($retention_days <= 0) {
            return;
        }

        // Delete completed items older than retention period
        $deleted = $wpdb->query($wpdb->prepare("
            DELETE FROM {$this->queue_table}
            WHERE status IN ('completed', 'failed')
            AND processed_at < DATE_SUB(NOW(), INTERVAL %d DAY)
        ", $retention_days));

        if ($deleted && $this->logger) {
            $this->logger->log(
                sprintf('Queue cleanup: %d old items deleted', $deleted),
                'info',
                array('retention_days' => $retention_days)
            );
        }

        return $deleted;
    }

    /**
     * Clear all queue items (for testing/debugging)
     */
    public function clear_queue($status = null) {
        global $wpdb;

        if ($status) {
            $deleted = $wpdb->delete($this->queue_table, array('status' => $status), array('%s'));
        } else {
            $deleted = $wpdb->query("TRUNCATE TABLE {$this->queue_table}");
        }

        if ($this->logger) {
            $this->logger->log(
                sprintf('Queue cleared: %d items removed (status: %s)', $deleted, $status ?: 'all'),
                'warning',
                array('status' => $status)
            );
        }

        return $deleted;
    }

    /**
     * Retry failed queue items
     */
    public function retry_failed($limit = 10) {
        global $wpdb;

        $updated = $wpdb->update(
            $this->queue_table,
            array(
                'status' => 'pending',
                'attempts' => 0,
                'last_error' => null,
                'scheduled_at' => current_time('mysql')
            ),
            array('status' => 'failed'),
            array('%s', '%d', '%s', '%s'),
            array('%s')
        );

        if ($updated && $this->logger) {
            $this->logger->log(
                sprintf('Retrying %d failed queue items', $updated),
                'info'
            );
        }

        return $updated;
    }

    /**
     * Check if queue is enabled
     */
    public function is_enabled() {
        return (bool) get_option('emailit_enable_queue', 0);
    }

    /**
     * Enable/disable queue
     */
    public function set_enabled($enabled) {
        update_option('emailit_enable_queue', (bool) $enabled);

        if ($enabled && !wp_next_scheduled('emailit_process_queue')) {
            wp_schedule_event(time(), 'every_minute', 'emailit_process_queue');
        } elseif (!$enabled) {
            wp_clear_scheduled_hook('emailit_process_queue');
        }
    }

    /**
     * Get estimated processing time
     */
    public function get_processing_estimate() {
        global $wpdb;

        $pending_count = $wpdb->get_var("
            SELECT COUNT(*) FROM {$this->queue_table}
            WHERE status = 'pending'
        ");

        if (!$pending_count) {
            return 0;
        }

        // Estimate based on batch size and processing interval
        $batches = ceil($pending_count / $this->batch_size);
        $minutes = $batches; // Assuming 1 minute intervals

        return $minutes;
    }
}