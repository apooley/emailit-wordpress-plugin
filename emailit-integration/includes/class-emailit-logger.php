<?php
/**
 * Emailit Logger Class
 *
 * Handles database logging of email events and activities.
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

class Emailit_Logger {

    /**
     * Log levels
     */
    const LEVEL_ERROR = 'error';
    const LEVEL_WARNING = 'warning';
    const LEVEL_INFO = 'info';
    const LEVEL_DEBUG = 'debug';

    /**
     * Email status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_SENT = 'sent';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_FAILED = 'failed';
    const STATUS_BOUNCED = 'bounced';
    const STATUS_COMPLAINED = 'complained';

    /**
     * Database table names
     */
    private $logs_table;
    private $webhook_logs_table;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;

        $this->logs_table = $wpdb->prefix . 'emailit_logs';
        $this->webhook_logs_table = $wpdb->prefix . 'emailit_webhook_logs';
    }

    /**
     * Log email event
     */
    public function log_email($email_data, $api_response = null, $status = self::STATUS_PENDING) {
        global $wpdb;

        // Skip logging if disabled
        if (!get_option('emailit_enable_logging', 1)) {
            return false;
        }

        // Prepare log data
        $log_data = array(
            'to_email' => $this->format_email_addresses($email_data['to']),
            'from_email' => $this->get_from_email($email_data),
            'reply_to' => $this->get_reply_to($email_data),
            'subject' => sanitize_text_field($email_data['subject']),
            'status' => $status,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );

        // Add message content
        if (isset($email_data['content_type']) && $email_data['content_type'] === 'text/html') {
            $log_data['body_html'] = $email_data['message'];
            $log_data['body_text'] = $this->html_to_text($email_data['message']);
        } else {
            $log_data['body_text'] = $email_data['message'];
        }

        // Add API response data if available
        if ($api_response && !is_wp_error($api_response)) {
            if (isset($api_response['data']['id'])) {
                $log_data['email_id'] = sanitize_text_field($api_response['data']['id']);
            }
            if (isset($api_response['data']['token'])) {
                $log_data['token'] = sanitize_text_field($api_response['data']['token']);
            }
            if (isset($api_response['data']['message_id'])) {
                $log_data['message_id'] = sanitize_text_field($api_response['data']['message_id']);
            }
            if ($status === self::STATUS_SENT) {
                $log_data['sent_at'] = current_time('mysql');
            }
        }

        // Add error details if response is an error
        if (is_wp_error($api_response)) {
            $log_data['status'] = self::STATUS_FAILED;
            $log_data['details'] = wp_json_encode(array(
                'error_code' => $api_response->get_error_code(),
                'error_message' => $api_response->get_error_message(),
                'error_data' => $api_response->get_error_data()
            ));
        }

        // Insert into database
        $result = $wpdb->insert(
            $this->logs_table,
            $log_data,
            array(
                '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
            )
        );

        if ($result === false) {
            error_log('Emailit Logger: Failed to insert email log - ' . $wpdb->last_error);
            return false;
        }

        $log_id = $wpdb->insert_id;

        // Trigger action hook
        do_action('emailit_email_logged', $log_id, $email_data, $api_response);

        return $log_id;
    }

    /**
     * Update email status
     */
    public function update_email_status($identifier, $status, $details = null) {
        global $wpdb;

        // Determine identifier type and build where clause
        if (is_numeric($identifier)) {
            $where = array('id' => $identifier);
            $where_format = array('%d');
        } elseif (strpos($identifier, '@') !== false) {
            // Email ID from API
            $where = array('email_id' => $identifier);
            $where_format = array('%s');
        } else {
            // Token
            $where = array('token' => $identifier);
            $where_format = array('%s');
        }

        // Prepare update data
        $update_data = array(
            'status' => $status,
            'updated_at' => current_time('mysql')
        );

        $update_format = array('%s', '%s');

        // Add details if provided
        if ($details !== null) {
            $update_data['details'] = is_array($details) ? wp_json_encode($details) : $details;
            $update_format[] = '%s';
        }

        // Update delivered status timestamp
        if ($status === self::STATUS_DELIVERED && !$this->has_delivered_timestamp($where)) {
            $update_data['sent_at'] = current_time('mysql');
            $update_format[] = '%s';
        }

        $result = $wpdb->update(
            $this->logs_table,
            $update_data,
            $where,
            $update_format,
            $where_format
        );

        if ($result !== false) {
            // Trigger action hook
            do_action('emailit_status_updated', $identifier, $status, $details);
        }

        return $result !== false;
    }

    /**
     * Check if email already has delivered timestamp
     */
    private function has_delivered_timestamp($where) {
        global $wpdb;

        $where_clause = '';
        $where_values = array();

        foreach ($where as $key => $value) {
            $where_clause .= $key . ' = %s';
            $where_values[] = $value;
        }

        $sent_at = $wpdb->get_var($wpdb->prepare(
            "SELECT sent_at FROM {$this->logs_table} WHERE {$where_clause}",
            $where_values
        ));

        return !empty($sent_at);
    }

    /**
     * Log webhook event
     */
    public function log_webhook($event_data, $email_id = null) {
        global $wpdb;

        $log_data = array(
            'webhook_request_id' => isset($event_data['request_id']) ? sanitize_text_field($event_data['request_id']) : null,
            'event_id' => isset($event_data['event_id']) ? sanitize_text_field($event_data['event_id']) : null,
            'event_type' => isset($event_data['event_type']) ? sanitize_text_field($event_data['event_type']) : null,
            'email_id' => $email_id ? sanitize_text_field($email_id) : null,
            'status' => isset($event_data['status']) ? sanitize_text_field($event_data['status']) : null,
            'details' => isset($event_data['details']) ? wp_json_encode($event_data['details']) : null,
            'raw_payload' => wp_json_encode($event_data),
            'processed_at' => current_time('mysql')
        );

        $result = $wpdb->insert(
            $this->webhook_logs_table,
            $log_data,
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if ($result === false) {
            error_log('Emailit Logger: Failed to insert webhook log - ' . $wpdb->last_error);
            return false;
        }

        $log_id = $wpdb->insert_id;

        // Trigger action hook
        do_action('emailit_webhook_logged', $log_id, $event_data, $email_id);

        return $log_id;
    }

    /**
     * Log general message
     */
    public function log($message, $level = self::LEVEL_INFO, $context = array()) {
        // Use WordPress logging if WP_DEBUG_LOG is enabled
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            $log_message = sprintf('[Emailit] %s: %s', strtoupper($level), $message);

            if (!empty($context)) {
                $log_message .= ' | Context: ' . wp_json_encode($context);
            }

            error_log($log_message);
        }

        // Trigger action hook for other logging systems
        do_action('emailit_log_message', $message, $level, $context);
    }

    /**
     * Get email logs with pagination
     */
    public function get_logs($args = array()) {
        global $wpdb;

        $defaults = array(
            'per_page' => 50,
            'page' => 1,
            'status' => '',
            'search' => '',
            'date_from' => '',
            'date_to' => '',
            'orderby' => 'created_at',
            'order' => 'DESC'
        );

        $args = wp_parse_args($args, $defaults);

        // Build WHERE clause
        $where_conditions = array('1=1');
        $where_values = array();

        if (!empty($args['status'])) {
            $where_conditions[] = 'status = %s';
            $where_values[] = $args['status'];
        }

        if (!empty($args['search'])) {
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_conditions[] = '(to_email LIKE %s OR subject LIKE %s OR from_email LIKE %s)';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }

        if (!empty($args['date_from'])) {
            $where_conditions[] = 'created_at >= %s';
            $where_values[] = $args['date_from'] . ' 00:00:00';
        }

        if (!empty($args['date_to'])) {
            $where_conditions[] = 'created_at <= %s';
            $where_values[] = $args['date_to'] . ' 23:59:59';
        }

        $where_clause = implode(' AND ', $where_conditions);

        // Validate orderby
        $allowed_orderby = array('id', 'created_at', 'subject', 'status', 'to_email', 'from_email');
        if (!in_array($args['orderby'], $allowed_orderby)) {
            $args['orderby'] = 'created_at';
        }

        // Validate order
        $args['order'] = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        // Calculate offset
        $offset = ($args['page'] - 1) * $args['per_page'];

        // Build query
        $query = $wpdb->prepare("
            SELECT * FROM {$this->logs_table}
            WHERE {$where_clause}
            ORDER BY {$args['orderby']} {$args['order']}
            LIMIT %d OFFSET %d
        ", array_merge($where_values, array($args['per_page'], $offset)));

        $results = $wpdb->get_results($query, ARRAY_A);

        // Get total count for pagination
        $count_query = $wpdb->prepare("
            SELECT COUNT(*) FROM {$this->logs_table}
            WHERE {$where_clause}
        ", $where_values);

        $total = (int) $wpdb->get_var($count_query);

        return array(
            'logs' => $results,
            'total' => $total,
            'pages' => ceil($total / $args['per_page']),
            'current_page' => $args['page']
        );
    }

    /**
     * Get single email log
     */
    public function get_log($log_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->logs_table} WHERE id = %d",
            $log_id
        ), ARRAY_A);
    }

    /**
     * Get webhook logs for an email
     */
    public function get_webhook_logs($email_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->webhook_logs_table} WHERE email_id = %s ORDER BY processed_at DESC",
            $email_id
        ), ARRAY_A);
    }

    /**
     * Delete log entry
     */
    public function delete_log($log_id) {
        global $wpdb;

        $result = $wpdb->delete(
            $this->logs_table,
            array('id' => $log_id),
            array('%d')
        );

        if ($result) {
            // Also delete related webhook logs
            $wpdb->delete(
                $this->webhook_logs_table,
                array('email_id' => $log_id),
                array('%s')
            );

            do_action('emailit_log_deleted', $log_id);
        }

        return $result !== false;
    }

    /**
     * Clean old logs based on retention policy
     */
    public function cleanup_logs() {
        global $wpdb;

        $retention_days = (int) get_option('emailit_log_retention_days', 30);

        if ($retention_days <= 0) {
            return; // Retention disabled
        }

        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));

        // Delete old email logs
        $deleted_logs = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->logs_table} WHERE created_at < %s",
            $cutoff_date
        ));

        // Delete old webhook logs
        $deleted_webhooks = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->webhook_logs_table} WHERE processed_at < %s",
            $cutoff_date
        ));

        if ($deleted_logs > 0 || $deleted_webhooks > 0) {
            $this->log(sprintf(
                'Log cleanup completed: %d email logs and %d webhook logs deleted',
                $deleted_logs,
                $deleted_webhooks
            ));
        }

        return array(
            'email_logs_deleted' => $deleted_logs,
            'webhook_logs_deleted' => $deleted_webhooks
        );
    }

    /**
     * Get email statistics
     */
    public function get_stats($days = 30) {
        global $wpdb;

        $date_from = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        // Total emails sent
        $total_sent = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->logs_table} WHERE created_at >= %s",
            $date_from
        ));

        // Status breakdown
        $status_stats = $wpdb->get_results($wpdb->prepare("
            SELECT status, COUNT(*) as count
            FROM {$this->logs_table}
            WHERE created_at >= %s
            GROUP BY status
        ", $date_from), ARRAY_A);

        $stats = array(
            'total_sent' => $total_sent,
            'delivered' => 0,
            'failed' => 0,
            'bounced' => 0,
            'complained' => 0,
            'pending' => 0
        );

        foreach ($status_stats as $stat) {
            $stats[$stat['status']] = (int) $stat['count'];
        }

        // Calculate delivery rate
        $stats['delivery_rate'] = $total_sent > 0 ? round(($stats['delivered'] / $total_sent) * 100, 2) : 0;

        return $stats;
    }

    /**
     * Format email addresses for storage
     */
    private function format_email_addresses($emails) {
        if (is_array($emails)) {
            $formatted = array();
            foreach ($emails as $email) {
                if (is_array($email) && isset($email['email'])) {
                    $formatted[] = isset($email['name']) ? $email['name'] . ' <' . $email['email'] . '>' : $email['email'];
                } else {
                    $formatted[] = $email;
                }
            }
            return implode(', ', $formatted);
        }

        return $emails;
    }

    /**
     * Get from email from data
     */
    private function get_from_email($email_data) {
        if (isset($email_data['from'])) {
            return sanitize_email($email_data['from']);
        }

        return get_option('emailit_from_email', get_bloginfo('admin_email'));
    }

    /**
     * Get reply-to email from data
     */
    private function get_reply_to($email_data) {
        if (isset($email_data['reply_to'])) {
            return sanitize_email($email_data['reply_to']);
        }

        $reply_to = get_option('emailit_reply_to', '');
        return !empty($reply_to) ? $reply_to : null;
    }

    /**
     * Convert HTML to plain text
     */
    private function html_to_text($html) {
        $html = preg_replace('/<(script|style)[^>]*?>.*?<\/\\1>/si', '', $html);
        $html = str_replace(array('&nbsp;', '&amp;', '&lt;', '&gt;'), array(' ', '&', '<', '>'), $html);
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html);
        $html = preg_replace('/<\/p>/i', "\n\n", $html);
        $text = wp_strip_all_tags($html);
        $text = preg_replace('/\n\s*\n/', "\n\n", $text);
        return trim($text);
    }
}