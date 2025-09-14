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
        $result = dbDelta($sql);

        // Log table creation result if debugging enabled
        if (defined('WP_DEBUG') && WP_DEBUG && !empty($result)) {
            error_log('[Emailit] Queue table creation result: ' . print_r($result, true));
        }

        return $result;
    }

    /**
     * Check if queue table exists and create it if needed
     */
    public function ensure_table_exists() {
        global $wpdb;

        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $this->queue_table)) !== $this->queue_table) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Emailit] Queue table missing, creating it now');
            }
            return $this->create_table();
        }

        return true;
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

        // Update email log status to processing
        $this->update_email_log_status($queue_id, 'processing');

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

        // Update email log status based on API response
        $email_status = 'sent'; // Default status
        if (is_array($api_response) && isset($api_response['data'])) {
            // Extract email_id and other info for the logger if needed
            $this->update_email_log_status($queue_id, $email_status, $api_response);
        } else {
            $this->update_email_log_status($queue_id, $email_status);
        }

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

        // Update email log status to failed
        $this->update_email_log_status($queue_id, 'failed');

        if ($this->logger) {
            $this->logger->log(
                sprintf('Queue item %d failed permanently', $queue_id),
                'error',
                array('queue_id' => $queue_id, 'error' => $error_message)
            );
        }
    }

    /**
     * Update email log status based on queue ID
     */
    private function update_email_log_status($queue_id, $status, $api_response = null) {
        global $wpdb;

        $logs_table = $wpdb->prefix . 'emailit_logs';

        $update_data = array(
            'status' => $status,
            'updated_at' => current_time('mysql')
        );

        // Add API response data if available
        if ($api_response && is_array($api_response) && isset($api_response['data'])) {
            if (isset($api_response['data']['id'])) {
                $update_data['email_id'] = sanitize_text_field($api_response['data']['id']);
            }
            if (isset($api_response['data']['token'])) {
                $update_data['token'] = sanitize_text_field($api_response['data']['token']);
            }
            if (isset($api_response['data']['message_id'])) {
                $update_data['message_id'] = sanitize_text_field($api_response['data']['message_id']);
            }

            // Set sent_at timestamp for successful sends
            if ($status === 'sent') {
                $update_data['sent_at'] = current_time('mysql');
            }
        }

        // Update email log record
        $updated = $wpdb->update(
            $logs_table,
            $update_data,
            array('queue_id' => $queue_id),
            array_fill(0, count($update_data), '%s'),
            array('%d')
        );

        if (defined('WP_DEBUG') && WP_DEBUG && $updated !== false) {
            error_log(sprintf('[Emailit] Updated email log status for queue_id %d to %s (affected rows: %d)', $queue_id, $status, $updated));
        }

        return $updated;
    }

    /**
     * Get queue statistics
     */
    public function get_stats() {
        global $wpdb;

        // Ensure table exists, create if missing
        if (!$this->ensure_table_exists()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Emailit] Failed to create queue table, returning empty stats');
            }
            return array(
                'total' => 0,
                'pending' => 0,
                'processing' => 0,
                'completed' => 0,
                'failed' => 0
            );
        }

        // Get current queue status (active items) and recent history
        $current_query = $wpdb->prepare("
            SELECT
                COUNT(*) as total_active,
                SUM(CASE WHEN status = %s THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = %s THEN 1 ELSE 0 END) as processing
            FROM {$this->queue_table}
            WHERE status IN ('pending', 'processing')
        ", 'pending', 'processing');

        $recent_query = $wpdb->prepare("
            SELECT
                COUNT(*) as total_recent,
                SUM(CASE WHEN status = %s THEN 1 ELSE 0 END) as completed_recent,
                SUM(CASE WHEN status = %s THEN 1 ELSE 0 END) as failed_recent
            FROM {$this->queue_table}
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            AND status IN ('completed', 'failed')
        ", 'completed', 'failed');

        $current_stats = $wpdb->get_row($current_query, ARRAY_A);
        $recent_stats = $wpdb->get_row($recent_query, ARRAY_A);

        // Handle database errors
        if ($wpdb->last_error) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Emailit] Queue stats query error: ' . $wpdb->last_error);
                error_log('[Emailit] Current query was: ' . $current_query);
                error_log('[Emailit] Recent query was: ' . $recent_query);
            }

            return array(
                'total' => 0,
                'pending' => 0,
                'processing' => 0,
                'completed' => 0,
                'failed' => 0
            );
        }

        // Combine current active queue and recent history
        $stats = array(
            'pending' => (int) ($current_stats['pending'] ?? 0),
            'processing' => (int) ($current_stats['processing'] ?? 0),
            'completed' => (int) ($recent_stats['completed_recent'] ?? 0),
            'failed' => (int) ($recent_stats['failed_recent'] ?? 0),
            'total' => (int) ($current_stats['total_active'] ?? 0) + (int) ($recent_stats['total_recent'] ?? 0)
        );

        return $stats;
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
    public function clear_queue(?string $status = null) {
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

    /**
     * Delete a specific queue item
     */
    public function delete_queue_item($queue_id) {
        global $wpdb;

        $queue_id = (int) $queue_id;
        if ($queue_id <= 0) {
            return new WP_Error('invalid_queue_id', __('Invalid queue ID provided.', 'emailit-integration'));
        }

        // Get queue item details for logging
        $queue_item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->queue_table} WHERE id = %d",
            $queue_id
        ), ARRAY_A);

        if (!$queue_item) {
            return new WP_Error('queue_item_not_found', __('Queue item not found.', 'emailit-integration'));
        }

        // Delete the queue item
        $deleted = $wpdb->delete(
            $this->queue_table,
            array('id' => $queue_id),
            array('%d')
        );

        if ($deleted === false) {
            return new WP_Error('delete_failed', __('Failed to delete queue item.', 'emailit-integration'));
        }

        // Also remove associated email log if it exists
        $logs_table = $wpdb->prefix . 'emailit_logs';
        $wpdb->delete(
            $logs_table,
            array('queue_id' => $queue_id),
            array('%d')
        );

        // Log the deletion
        if ($this->logger) {
            $this->logger->log(
                sprintf('Queue item %d deleted manually (status: %s)', $queue_id, $queue_item['status']),
                'info',
                array('queue_id' => $queue_id, 'status' => $queue_item['status'])
            );
        }

        return true;
    }

    /**
     * Get a single queue item by ID
     */
    public function get_queue_item($queue_id) {
        global $wpdb;

        $queue_id = (int) $queue_id;
        if ($queue_id <= 0) {
            return null;
        }

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->queue_table} WHERE id = %d",
            $queue_id
        ), ARRAY_A);
    }

    /**
     * Bulk delete queue items
     */
    public function bulk_delete_queue_items($queue_ids, $status_filter = '') {
        global $wpdb;

        if (empty($queue_ids) || !is_array($queue_ids)) {
            return new WP_Error('invalid_queue_ids', __('No valid queue IDs provided.', 'emailit-integration'));
        }

        // Sanitize queue IDs
        $queue_ids = array_map('intval', $queue_ids);
        $queue_ids = array_filter($queue_ids, function($id) { return $id > 0; });

        if (empty($queue_ids)) {
            return new WP_Error('no_valid_ids', __('No valid queue IDs provided.', 'emailit-integration'));
        }

        $placeholders = implode(',', array_fill(0, count($queue_ids), '%d'));
        $where_clause = "id IN ($placeholders)";
        $values = $queue_ids;

        // Add status filter if provided
        if (!empty($status_filter)) {
            $where_clause .= " AND status = %s";
            $values[] = $status_filter;
        }

        // Get items to be deleted for logging
        $query = $wpdb->prepare(
            "SELECT id, status FROM {$this->queue_table} WHERE $where_clause",
            $values
        );
        $items_to_delete = $wpdb->get_results($query, ARRAY_A);

        // Delete queue items
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->queue_table} WHERE $where_clause",
            $values
        ));

        if ($deleted === false) {
            return new WP_Error('bulk_delete_failed', __('Failed to delete queue items.', 'emailit-integration'));
        }

        // Also remove associated email logs
        $logs_table = $wpdb->prefix . 'emailit_logs';
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$logs_table} WHERE queue_id IN ($placeholders)",
            $queue_ids
        ));

        // Log the bulk deletion
        if ($this->logger) {
            $statuses = array_column($items_to_delete, 'status');
            $status_counts = array_count_values($statuses);
            $status_summary = array();
            foreach ($status_counts as $status => $count) {
                $status_summary[] = "$status: $count";
            }

            $this->logger->log(
                sprintf('Bulk deleted %d queue items (%s)', $deleted, implode(', ', $status_summary)),
                'info',
                array('deleted_count' => $deleted, 'queue_ids' => $queue_ids, 'status_filter' => $status_filter)
            );
        }

        return $deleted;
    }
}