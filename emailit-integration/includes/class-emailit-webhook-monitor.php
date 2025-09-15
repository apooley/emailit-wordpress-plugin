<?php
/**
 * Emailit Webhook Monitor Class
 *
 * Monitors webhook activity and alerts when webhooks are missing or delayed.
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

class Emailit_Webhook_Monitor {

    /**
     * Logger instance
     */
    private $logger;

    /**
     * Webhook logs table
     */
    private $webhook_logs_table;

    /**
     * Email logs table
     */
    private $email_logs_table;

    /**
     * Constructor
     */
    public function __construct($logger) {
        global $wpdb;
        
        $this->logger = $logger;
        $this->webhook_logs_table = $wpdb->prefix . 'emailit_webhook_logs';
        $this->email_logs_table = $wpdb->prefix . 'emailit_logs';
        
        // Schedule webhook monitoring
        add_action('init', array($this, 'schedule_webhook_monitoring'));
        add_action('emailit_webhook_monitor_check', array($this, 'check_webhook_health'));
    }

    /**
     * Schedule webhook monitoring
     */
    public function schedule_webhook_monitoring() {
        if (!wp_next_scheduled('emailit_webhook_monitor_check')) {
            wp_schedule_event(time(), 'hourly', 'emailit_webhook_monitor_check');
        }
    }

    /**
     * Check webhook health and alert if issues detected
     */
    public function check_webhook_health() {
        if (!get_option('emailit_enable_webhooks', 1)) {
            return; // Webhooks disabled
        }

        $this->check_missing_webhooks();
        $this->check_webhook_delays();
        $this->check_webhook_errors();
    }

    /**
     * Check for emails that should have received webhooks but haven't
     */
    private function check_missing_webhooks() {
        global $wpdb;

        // Get emails sent in the last 24 hours that should have webhooks
        $cutoff_time = date('Y-m-d H:i:s', time() - 86400); // 24 hours ago
        
        $emails_without_webhooks = $wpdb->get_results($wpdb->prepare("
            SELECT e.id, e.email_id, e.to_email, e.subject, e.sent_at, e.status
            FROM {$this->email_logs_table} e
            LEFT JOIN {$this->webhook_logs_table} w ON e.email_id = w.email_id
            WHERE e.sent_at >= %s 
            AND e.status = 'sent'
            AND w.id IS NULL
            AND e.sent_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)
            ORDER BY e.sent_at DESC
            LIMIT 10
        ", $cutoff_time));

        if (!empty($emails_without_webhooks)) {
            $count = count($emails_without_webhooks);
            $this->logger->log(
                "Missing webhooks detected: {$count} emails sent without webhook confirmations",
                Emailit_Logger::LEVEL_WARNING,
                array(
                    'missing_webhooks_count' => $count,
                    'emails' => $emails_without_webhooks
                )
            );

            // Store alert for admin notification
            $this->store_webhook_alert('missing_webhooks', array(
                'count' => $count,
                'message' => sprintf(
                    __('%d emails sent in the last 24 hours have not received webhook confirmations. This may indicate an issue with Emailit webhook delivery.', 'emailit-integration'),
                    $count
                ),
                'emails' => $emails_without_webhooks
            ));
        }
    }

    /**
     * Check for delayed webhooks
     */
    private function check_webhook_delays() {
        global $wpdb;

        // Get webhooks that took more than 1 hour to arrive
        $delayed_webhooks = $wpdb->get_results($wpdb->prepare("
            SELECT w.id, w.email_id, w.event_type, w.processed_at, e.sent_at,
                   TIMESTAMPDIFF(MINUTE, e.sent_at, w.processed_at) as delay_minutes
            FROM {$this->webhook_logs_table} w
            JOIN {$this->email_logs_table} e ON w.email_id = e.email_id
            WHERE w.processed_at >= %s
            AND TIMESTAMPDIFF(MINUTE, e.sent_at, w.processed_at) > 60
            ORDER BY delay_minutes DESC
            LIMIT 5
        ", date('Y-m-d H:i:s', time() - 86400)));

        if (!empty($delayed_webhooks)) {
            $max_delay = max(array_column($delayed_webhooks, 'delay_minutes'));
            $this->logger->log(
                "Delayed webhooks detected: Maximum delay {$max_delay} minutes",
                Emailit_Logger::LEVEL_WARNING,
                array(
                    'delayed_webhooks' => $delayed_webhooks,
                    'max_delay_minutes' => $max_delay
                )
            );
        }
    }

    /**
     * Check for webhook processing errors
     */
    private function check_webhook_errors() {
        global $wpdb;

        // Get webhook processing errors in the last 24 hours
        $error_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$this->webhook_logs_table}
            WHERE processed_at >= %s
            AND status = 'failed'
        ", date('Y-m-d H:i:s', time() - 86400)));

        if ($error_count > 0) {
            $this->logger->log(
                "Webhook processing errors detected: {$error_count} failed webhooks in last 24 hours",
                Emailit_Logger::LEVEL_ERROR,
                array('error_count' => $error_count)
            );

            $this->store_webhook_alert('webhook_errors', array(
                'count' => $error_count,
                'message' => sprintf(
                    __('%d webhook processing errors occurred in the last 24 hours. Check webhook logs for details.', 'emailit-integration'),
                    $error_count
                )
            ));
        }
    }

    /**
     * Store webhook alert for admin notification
     */
    private function store_webhook_alert($type, $data) {
        $alerts = get_option('emailit_webhook_alerts', array());
        $alerts[] = array(
            'type' => $type,
            'data' => $data,
            'timestamp' => current_time('mysql'),
            'dismissed' => false
        );
        
        // Keep only last 10 alerts
        $alerts = array_slice($alerts, -10);
        update_option('emailit_webhook_alerts', $alerts);
    }

    /**
     * Get webhook health status
     */
    public function get_webhook_health_status() {
        global $wpdb;

        $status = array(
            'webhooks_enabled' => get_option('emailit_enable_webhooks', 1),
            'last_webhook' => null,
            'missing_webhooks_count' => 0,
            'delayed_webhooks_count' => 0,
            'error_count' => 0,
            'health_score' => 100
        );

        if (!$status['webhooks_enabled']) {
            $status['health_score'] = 0;
            return $status;
        }

        // Get last webhook received
        $last_webhook = $wpdb->get_row("
            SELECT processed_at, event_type, email_id
            FROM {$this->webhook_logs_table}
            WHERE status = 'processed'
            ORDER BY processed_at DESC
            LIMIT 1
        ");

        if ($last_webhook) {
            $status['last_webhook'] = array(
                'time' => $last_webhook->processed_at,
                'event_type' => $last_webhook->event_type,
                'email_id' => $last_webhook->email_id,
                'time_ago' => human_time_diff(strtotime($last_webhook->processed_at), current_time('timestamp'))
            );
        }

        // Count missing webhooks (last 24 hours)
        $status['missing_webhooks_count'] = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$this->email_logs_table} e
            LEFT JOIN {$this->webhook_logs_table} w ON e.email_id = w.email_id
            WHERE e.sent_at >= %s 
            AND e.status = 'sent'
            AND w.id IS NULL
            AND e.sent_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)
        ", date('Y-m-d H:i:s', time() - 86400)));

        // Count delayed webhooks (last 24 hours)
        $status['delayed_webhooks_count'] = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$this->webhook_logs_table} w
            JOIN {$this->email_logs_table} e ON w.email_id = e.email_id
            WHERE w.processed_at >= %s
            AND TIMESTAMPDIFF(MINUTE, e.sent_at, w.processed_at) > 60
        ", date('Y-m-d H:i:s', time() - 86400)));

        // Count webhook errors (last 24 hours)
        $status['error_count'] = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$this->webhook_logs_table}
            WHERE processed_at >= %s
            AND status = 'failed'
        ", date('Y-m-d H:i:s', time() - 86400)));

        // Calculate health score
        $status['health_score'] = 100;
        if ($status['missing_webhooks_count'] > 0) {
            $status['health_score'] -= min($status['missing_webhooks_count'] * 10, 50);
        }
        if ($status['delayed_webhooks_count'] > 0) {
            $status['health_score'] -= min($status['delayed_webhooks_count'] * 5, 25);
        }
        if ($status['error_count'] > 0) {
            $status['health_score'] -= min($status['error_count'] * 15, 30);
        }

        return $status;
    }

    /**
     * Get webhook statistics
     */
    public function get_webhook_statistics($days = 7) {
        global $wpdb;

        $cutoff_time = date('Y-m-d H:i:s', time() - ($days * 86400));

        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total_webhooks,
                SUM(CASE WHEN status = 'processed' THEN 1 ELSE 0 END) as processed_webhooks,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_webhooks,
                SUM(CASE WHEN event_type = 'email.delivery.sent' THEN 1 ELSE 0 END) as delivery_webhooks,
                SUM(CASE WHEN event_type = 'email.delivery.bounced' THEN 1 ELSE 0 END) as bounce_webhooks,
                SUM(CASE WHEN event_type = 'email.delivery.complained' THEN 1 ELSE 0 END) as complaint_webhooks,
                AVG(CASE WHEN status = 'processed' THEN 1 ELSE 0 END) * 100 as success_rate
            FROM {$this->webhook_logs_table}
            WHERE processed_at >= %s
        ", $cutoff_time));

        return array(
            'total_webhooks' => intval($stats->total_webhooks),
            'processed_webhooks' => intval($stats->processed_webhooks),
            'failed_webhooks' => intval($stats->failed_webhooks),
            'delivery_webhooks' => intval($stats->delivery_webhooks),
            'bounce_webhooks' => intval($stats->bounce_webhooks),
            'complaint_webhooks' => intval($stats->complaint_webhooks),
            'success_rate' => round($stats->success_rate, 1)
        );
    }

    /**
     * Get recent webhook activity
     */
    public function get_recent_webhook_activity($limit = 20) {
        global $wpdb;

        $webhooks = $wpdb->get_results($wpdb->prepare("
            SELECT 
                w.id,
                w.webhook_request_id,
                w.event_type,
                w.email_id,
                w.status,
                w.processed_at,
                w.details,
                e.to_email,
                e.subject
            FROM {$this->webhook_logs_table} w
            LEFT JOIN {$this->email_logs_table} e ON w.email_id = e.email_id
            ORDER BY w.processed_at DESC
            LIMIT %d
        ", $limit));

        return $webhooks;
    }

    /**
     * Get webhook alerts
     */
    public function get_webhook_alerts() {
        return get_option('emailit_webhook_alerts', array());
    }

    /**
     * Dismiss webhook alert
     */
    public function dismiss_webhook_alert($alert_index) {
        $alerts = get_option('emailit_webhook_alerts', array());
        if (isset($alerts[$alert_index])) {
            $alerts[$alert_index]['dismissed'] = true;
            update_option('emailit_webhook_alerts', $alerts);
            return true;
        }
        return false;
    }

    /**
     * Clear all webhook alerts
     */
    public function clear_all_webhook_alerts() {
        update_option('emailit_webhook_alerts', array());
    }

    /**
     * Clear all webhook logs
     */
    public function clear_webhook_logs() {
        global $wpdb;
        
        // Check if table exists before attempting to clear
        if (!$wpdb->get_var("SHOW TABLES LIKE '{$this->webhook_logs_table}'")) {
            return false;
        }
        
        $result = $wpdb->query("TRUNCATE TABLE {$this->webhook_logs_table}");
        
        if ($result !== false) {
            $this->logger->log('Webhook logs cleared', Emailit_Logger::LEVEL_INFO);
            return true;
        }
        
        return false;
    }

    /**
     * Clear webhook logs older than specified days
     */
    public function clear_old_webhook_logs($days = 30) {
        global $wpdb;
        
        // Check if table exists before attempting to clear
        if (!$wpdb->get_var("SHOW TABLES LIKE '{$this->webhook_logs_table}'")) {
            return false;
        }
        
        $cutoff_date = date('Y-m-d H:i:s', time() - ($days * 86400));
        
        $result = $wpdb->query($wpdb->prepare("
            DELETE FROM {$this->webhook_logs_table} 
            WHERE processed_at < %s
        ", $cutoff_date));
        
        if ($result !== false) {
            $this->logger->log("Cleared webhook logs older than {$days} days", Emailit_Logger::LEVEL_INFO, array(
                'deleted_count' => $result,
                'cutoff_date' => $cutoff_date
            ));
            return $result;
        }
        
        return false;
    }
}
