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
    const STATUS_SENT_TO_API = 'sent_to_api';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_FAILED = 'failed';
    const STATUS_BOUNCED = 'bounced';
    const STATUS_COMPLAINED = 'complained';
    const STATUS_HELD = 'held';
    const STATUS_DELAYED = 'delayed';
    const STATUS_OPENED = 'opened';
    const STATUS_CLICKED = 'clicked';
    const STATUS_UNSUBSCRIBED = 'unsubscribed';

    /**
     * Database table names
     */
    private $logs_table;
    private $webhook_logs_table;

    /**
     * Constructor
     */
    public function __construct() {
        $wpdb = $GLOBALS['wpdb'];

        $this->logs_table = $wpdb->prefix . 'emailit_logs';
        $this->webhook_logs_table = $wpdb->prefix . 'emailit_webhook_logs';

        // Ensure database schema is up to date
        $this->maybe_upgrade_schema();
    }

    /**
     * Log email event
     */
    public function log_email(array $email_data, $api_response = null, ?string $status = null, ?float $response_time = null) {
        global $wpdb;

        // Skip logging if disabled
        if (!get_option('emailit_enable_logging', 1)) {
            return false;
        }

        // Set default status if not provided
        if ($status === null) {
            $status = self::STATUS_PENDING;
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

        // Add queue ID if available
        if (isset($email_data['queue_id']) && is_numeric($email_data['queue_id'])) {
            $log_data['queue_id'] = (int) $email_data['queue_id'];
        }

        // Add message content with optional truncation for minimal logging
        $minimal_logging = (bool) get_option('emailit_minimal_logging', false);
        $max_body_length = (int) get_option('emailit_log_body_max_length', 10000);
        $html_content = null;
        $text_content = null;
        $truncated_html = false;
        $truncated_text = false;

        if (isset($email_data['content_type']) && $email_data['content_type'] === 'text/html') {
            $html_content = $email_data['message'];
            $text_content = $this->html_to_text($html_content);
            
            if ($minimal_logging) {
                $original_html_len = strlen($html_content);
                $original_text_len = strlen($text_content);
                
                $log_data['body_html'] = $this->truncate_log_content($html_content, $max_body_length);
                $log_data['body_text'] = $this->truncate_log_content($text_content, $max_body_length);
                
                $truncated_html = strlen($log_data['body_html']) < $original_html_len;
                $truncated_text = strlen($log_data['body_text']) < $original_text_len;
            } else {
                $log_data['body_html'] = $html_content;
                $log_data['body_text'] = $text_content;
            }
        } else {
            $text_content = $email_data['message'];
            if ($minimal_logging) {
                $original_text_len = strlen($text_content);
                $log_data['body_text'] = $this->truncate_log_content($text_content, $max_body_length);
                $truncated_text = strlen($log_data['body_text']) < $original_text_len;
            } else {
                $log_data['body_text'] = $text_content;
            }
        }

        // Add truncation indicator to details if content was truncated
        if ($minimal_logging && ($truncated_html || $truncated_text)) {
            $existing_details = isset($log_data['details']) ? json_decode($log_data['details'], true) : array();
            if (!is_array($existing_details)) {
                $existing_details = array();
            }
            $existing_details['truncated'] = true;
            $existing_details['truncated_html'] = $truncated_html;
            $existing_details['truncated_text'] = $truncated_text;
            $existing_details['max_length'] = $max_body_length;
            $log_data['details'] = wp_json_encode($existing_details);
        }

        // Add response time if available
        if ($response_time !== null) {
            $log_data['response_time'] = $response_time;
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
            if ($status === self::STATUS_SENT || $status === self::STATUS_SENT_TO_API) {
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
    public function update_email_status(string $identifier, string $status, ?string $details = null) {
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

            // Extract bounce classification data if present
            if (is_array($details)) {
                if (isset($details['bounce_classification'])) {
                    $update_data['bounce_classification'] = $details['bounce_classification'];
                    $update_format[] = '%s';
                }
                if (isset($details['bounce_category'])) {
                    $update_data['bounce_category'] = $details['bounce_category'];
                    $update_format[] = '%s';
                }
                if (isset($details['bounce_severity'])) {
                    $update_data['bounce_severity'] = $details['bounce_severity'];
                    $update_format[] = '%s';
                }
                if (isset($details['bounce_confidence'])) {
                    $update_data['bounce_confidence'] = (int) $details['bounce_confidence'];
                    $update_format[] = '%d';
                }
            }
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
    public function log_webhook(array $event_data, ?string $email_id = null) {
        global $wpdb;

        // Get payload logging level setting (default: 'truncated')
        $payload_logging = get_option('emailit_webhook_payload_logging', 'truncated');
        $max_payload_length = (int) get_option('emailit_webhook_payload_max_length', 5000);

        // Process payload based on logging level
        $payload_result = $this->process_webhook_payload($event_data, $payload_logging, $max_payload_length);

        $log_data = array(
            'webhook_request_id' => isset($event_data['request_id']) ? sanitize_text_field($event_data['request_id']) : null,
            'event_id' => isset($event_data['event_id']) ? sanitize_text_field($event_data['event_id']) : null,
            'event_type' => isset($event_data['event_type']) ? sanitize_text_field($event_data['event_type']) : null,
            'email_id' => $email_id ? sanitize_text_field($email_id) : null,
            'status' => isset($event_data['status']) ? sanitize_text_field($event_data['status']) : null,
            'details' => isset($event_data['details']) ? wp_json_encode($event_data['details']) : null,
            'raw_payload' => $payload_result['payload'],
            'processed_at' => current_time('mysql')
        );

        // Add payload hash to details if truncated or hash-only mode
        if (!empty($payload_result['hash'])) {
            $details = isset($event_data['details']) ? $event_data['details'] : array();
            if (!is_array($details)) {
                $details = array();
            }
            $details['payload_hash'] = $payload_result['hash'];
            $details['payload_truncated'] = $payload_result['truncated'];
            $log_data['details'] = wp_json_encode($details);
        }

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
     * Process webhook payload based on logging level
     * 
     * @param array $event_data Full event data
     * @param string $logging_level 'full', 'truncated', or 'hash_only'
     * @param int $max_length Maximum payload length for truncated mode
     * @return array Array with 'payload', 'hash', and 'truncated' keys
     */
    private function process_webhook_payload(array $event_data, string $logging_level, int $max_length) {
        $full_payload = wp_json_encode($event_data);
        $payload_hash = hash('sha256', $full_payload);
        $truncated = false;

        // Full logging: store everything (only in debug mode or explicit setting)
        if ($logging_level === 'full') {
            // Only allow full logging if WP_DEBUG is enabled or explicitly set
            if (defined('WP_DEBUG') && WP_DEBUG) {
                return array(
                    'payload' => $full_payload,
                    'hash' => null,
                    'truncated' => false
                );
            }
            // Fall through to truncated if not in debug mode
            $logging_level = 'truncated';
        }

        // Hash-only mode: store only hash
        if ($logging_level === 'hash_only') {
            return array(
                'payload' => null,
                'hash' => $payload_hash,
                'truncated' => true
            );
        }

        // Truncated mode (default): store first N chars + hash
        if (strlen($full_payload) > $max_length) {
            $truncated_payload = substr($full_payload, 0, $max_length);
            $truncated_payload .= "\n... [TRUNCATED - Full payload hash: {$payload_hash}]";
            return array(
                'payload' => $truncated_payload,
                'hash' => $payload_hash,
                'truncated' => true
            );
        }

        // Payload fits within limit
        return array(
            'payload' => $full_payload,
            'hash' => $payload_hash,
            'truncated' => false
        );
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
        // Use query optimizer if available
        $query_optimizer = emailit_get_component('query_optimizer');
        if ($query_optimizer) {
            return $query_optimizer->get_email_logs($args);
        }

        // Fallback to original implementation
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

        // Build WHERE clause with proper sanitization
        $where_conditions = array('1=1');
        $where_values = array();

        if (!empty($args['status'])) {
            // Validate status against allowed values to prevent injection
            $allowed_statuses = array('pending', 'sent', 'sent_to_api', 'delivered', 'failed', 'bounced', 'complained', 'held', 'delayed', 'opened', 'clicked', 'unsubscribed');
            if (in_array($args['status'], $allowed_statuses)) {
                $where_conditions[] = 'status = %s';
                $where_values[] = $args['status'];
            }
        }

        if (!empty($args['search'])) {
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_conditions[] = '(to_email LIKE %s OR subject LIKE %s OR from_email LIKE %s)';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }

        if (!empty($args['date_from'])) {
            // Validate date format to prevent injection
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $args['date_from'])) {
                $where_conditions[] = 'created_at >= %s';
                $where_values[] = $args['date_from'] . ' 00:00:00';
            }
        }

        if (!empty($args['date_to'])) {
            // Validate date format to prevent injection
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $args['date_to'])) {
                $where_conditions[] = 'created_at <= %s';
                $where_values[] = $args['date_to'] . ' 23:59:59';
            }
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
        $payload_retention_days = (int) get_option('emailit_webhook_payload_retention_days', 0);
        $payload_retention_days = max(0, min(7, $payload_retention_days)); // clamp 0-7

        $deleted_logs = 0;
        $deleted_webhooks = 0;

        if ($retention_days > 0) {
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
        }

        // Truncate stored webhook payloads beyond the retention window (or all if disabled)
        if ($payload_retention_days === 0) {
            $truncated_payloads = $wpdb->query(
                "UPDATE {$this->webhook_logs_table} SET raw_payload = LEFT(raw_payload, 500) WHERE raw_payload IS NOT NULL"
            );
        } else {
            $payload_cutoff = date('Y-m-d H:i:s', strtotime("-{$payload_retention_days} days"));
            $truncated_payloads = $wpdb->query($wpdb->prepare(
                "UPDATE {$this->webhook_logs_table} SET raw_payload = LEFT(raw_payload, 500) WHERE processed_at < %s AND raw_payload IS NOT NULL",
                $payload_cutoff
            ));
        }

        if ($deleted_logs > 0 || $deleted_webhooks > 0) {
            $this->log(sprintf(
                'Log cleanup completed: %d email logs and %d webhook logs deleted',
                $deleted_logs,
                $deleted_webhooks
            ));
        }

        return array(
            'email_logs_deleted' => $deleted_logs,
            'webhook_logs_deleted' => $deleted_webhooks,
            'webhook_payloads_truncated' => $truncated_payloads
        );
    }

    /**
     * Get recent email count for a specific number of hours
     */
    public function get_recent_email_count($hours = 24) {
        global $wpdb;
        
        $date_from = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
        
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->logs_table} WHERE created_at >= %s",
            $date_from
        ));
        
        return $count;
    }

    /**
     * Get daily stats for a specific date
     */
    public function get_daily_stats($date) {
        global $wpdb;
        
        $date_start = $date . ' 00:00:00';
        $date_end = $date . ' 23:59:59';
        
        // Get total count for the day
        $total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->logs_table} WHERE created_at BETWEEN %s AND %s",
            $date_start, $date_end
        ));
        
        if ($total === 0) {
            return null;
        }
        
        // Get status breakdown for the day
        $status_stats = $wpdb->get_results($wpdb->prepare("
            SELECT status, COUNT(*) as count
            FROM {$this->logs_table}
            WHERE created_at BETWEEN %s AND %s
            GROUP BY status
        ", $date_start, $date_end), ARRAY_A);
        
        $stats = array(
            'sent' => 0,
            'failed' => 0,
            'bounced' => 0,
            'pending' => 0,
            'held' => 0,
            'delayed' => 0
        );
        
        foreach ($status_stats as $stat) {
            if (isset($stats[$stat['status']])) {
                $stats[$stat['status']] = (int) $stat['count'];
            }
        }
        
        return $stats;
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

        $raw_stats = array(
            'total_sent' => $total_sent,
            'sent' => 0,
            'failed' => 0,
            'bounced' => 0,
            'pending' => 0,
            'held' => 0,
            'delayed' => 0
        );

        foreach ($status_stats as $stat) {
            $raw_stats[$stat['status']] = (int) $stat['count'];
        }

        // Calculate success rate (sent emails)
        $success_rate = $total_sent > 0 ? round(($raw_stats['sent'] / $total_sent) * 100, 2) : 0;

        // Format for JavaScript consumption - only show stats that Emailit actually provides
        $stats = array(
            'total_sent' => array(
                'value' => $raw_stats['total_sent'],
                'label' => __('Total Emails', 'emailit-integration')
            ),
            'sent' => array(
                'value' => $raw_stats['sent'],
                'label' => __('Successfully Sent', 'emailit-integration')
            ),
            'failed' => array(
                'value' => $raw_stats['failed'],
                'label' => __('Failed', 'emailit-integration')
            ),
            'bounced' => array(
                'value' => $raw_stats['bounced'],
                'label' => __('Bounced', 'emailit-integration')
            ),
            'held' => array(
                'value' => $raw_stats['held'],
                'label' => __('Held', 'emailit-integration')
            ),
            'delayed' => array(
                'value' => $raw_stats['delayed'],
                'label' => __('Delayed', 'emailit-integration')
            ),
            'pending' => array(
                'value' => $raw_stats['pending'],
                'label' => __('Pending', 'emailit-integration')
            ),
            'success_rate' => array(
                'value' => $success_rate . '%',
                'label' => __('Success Rate', 'emailit-integration')
            )
        );

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

    /**
     * Truncate log content to specified length
     * 
     * @param string $content Content to truncate
     * @param int $max_length Maximum length in characters
     * @return string Truncated content with indicator if truncated
     */
    private function truncate_log_content($content, $max_length) {
        if (empty($content) || strlen($content) <= $max_length) {
            return $content;
        }

        // Truncate to max length, preserving word boundaries where possible
        $truncated = substr($content, 0, $max_length);
        
        // Try to truncate at a word boundary (space, newline, etc.)
        $last_space = strrpos($truncated, ' ');
        $last_newline = strrpos($truncated, "\n");
        $cut_point = max($last_space, $last_newline);
        
        if ($cut_point !== false && $cut_point > ($max_length * 0.8)) {
            // Use word boundary if it's not too close to the start
            $truncated = substr($truncated, 0, $cut_point);
        }

        // Add truncation indicator
        $truncated .= "\n\n[TRUNCATED - Original length: " . strlen($content) . " characters]";

        return $truncated;
    }

    /**
     * Check and upgrade database schema if needed
     */
    private function maybe_upgrade_schema() {
        global $wpdb;

        try {
            // Check if queue_id column exists
            $column_exists = $wpdb->get_results($wpdb->prepare(
                "SHOW COLUMNS FROM `{$this->logs_table}` LIKE %s",
                'queue_id'
            ));

            if (empty($column_exists)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[Emailit] Auto-upgrading database schema: adding queue_id column');
                }

                // Add queue_id column
                $result = $wpdb->query("ALTER TABLE `{$this->logs_table}` ADD COLUMN `queue_id` bigint(20) UNSIGNED DEFAULT NULL AFTER `message_id`");

                if ($result !== false) {
                    // Add index
                    $wpdb->query("ALTER TABLE `{$this->logs_table}` ADD INDEX `idx_queue_id` (`queue_id`)");

                    error_log('[Emailit] Successfully added queue_id column to email logs table');

                    // Update database version
                    update_option('emailit_db_version', '2.1.0');
                } else {
                    error_log('[Emailit] Failed to add queue_id column to email logs table: ' . $wpdb->last_error);
                }
            }
        } catch (Exception $e) {
            error_log('[Emailit] Exception during schema upgrade: ' . $e->getMessage());
        }
    }
}
