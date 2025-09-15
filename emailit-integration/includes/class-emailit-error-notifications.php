<?php
/**
 * Emailit Error Notifications
 *
 * Advanced error notification system with escalation and suppression.
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

class Emailit_Error_Notifications {

    /**
     * Logger instance
     */
    private $logger;

    /**
     * Notification channels
     */
    private $channels = array();

    /**
     * Notification rules
     */
    private $notification_rules = array();

    /**
     * Suppression settings
     */
    private $suppression_settings = array();

    /**
     * Constructor
     */
    public function __construct($logger) {
        $this->logger = $logger;
        $this->init_notification_system();
    }

    /**
     * Initialize notification system
     */
    private function init_notification_system() {
        $this->init_notification_channels();
        $this->init_notification_rules();
        $this->load_suppression_settings();
        
        // Hook into error events
        add_action('emailit_error_occurred', array($this, 'handle_error_notification'), 10, 3);
        add_action('emailit_critical_error', array($this, 'handle_critical_error'), 10, 3);
        add_action('emailit_error_escalation', array($this, 'handle_error_escalation'), 10, 2);
    }

    /**
     * Initialize notification channels
     */
    private function init_notification_channels() {
        $this->channels = array(
            'email' => array(
                'enabled' => true,
                'priority' => 'high',
                'handler' => array($this, 'send_email_notification')
            ),
            'admin_notice' => array(
                'enabled' => true,
                'priority' => 'medium',
                'handler' => array($this, 'send_admin_notice')
            ),
            'webhook' => array(
                'enabled' => get_option('emailit_error_webhook_enabled', false),
                'priority' => 'high',
                'handler' => array($this, 'send_webhook_notification')
            ),
            'slack' => array(
                'enabled' => get_option('emailit_error_slack_enabled', false),
                'priority' => 'medium',
                'handler' => array($this, 'send_slack_notification')
            )
        );
    }

    /**
     * Initialize notification rules
     */
    private function init_notification_rules() {
        $this->notification_rules = array(
            'critical' => array(
                'channels' => array('email', 'admin_notice', 'webhook'),
                'immediate' => true,
                'escalation_delay' => 0,
                'max_notifications' => 10
            ),
            'error' => array(
                'channels' => array('email', 'admin_notice'),
                'immediate' => false,
                'escalation_delay' => 300, // 5 minutes
                'max_notifications' => 5
            ),
            'warning' => array(
                'channels' => array('admin_notice'),
                'immediate' => false,
                'escalation_delay' => 1800, // 30 minutes
                'max_notifications' => 3
            )
        );
    }

    /**
     * Handle error notification
     */
    public function handle_error_notification($error_code, $error_message, $context = array()) {
        $error_level = $context['level'] ?? 'error';
        
        // Check if notification should be sent
        if (!$this->should_send_notification($error_code, $error_level, $context)) {
            return;
        }

        // Get notification rule
        $rule = $this->notification_rules[$error_level] ?? $this->notification_rules['error'];
        
        // Send immediate notifications
        if ($rule['immediate']) {
            $this->send_notifications($error_code, $error_message, $context, $rule['channels']);
        } else {
            // Schedule delayed notification
            $this->schedule_notification($error_code, $error_message, $context, $rule);
        }
    }

    /**
     * Handle critical error
     */
    public function handle_critical_error($error_code, $error_message, $context = array()) {
        // Always send critical errors immediately
        $this->send_notifications($error_code, $error_message, $context, array('email', 'admin_notice', 'webhook'));
        
        // Trigger escalation
        $this->trigger_escalation($error_code, $error_message, $context);
    }

    /**
     * Handle error escalation
     */
    public function handle_error_escalation($error_code, $context = array()) {
        // Send escalation notification
        $this->send_escalation_notification($error_code, $context);
    }

    /**
     * Check if notification should be sent
     */
    private function should_send_notification($error_code, $error_level, $context = array()) {
        // Check suppression settings
        if ($this->is_suppressed($error_code, $error_level)) {
            return false;
        }

        // Check notification frequency limits
        if (!$this->check_frequency_limits($error_code, $error_level)) {
            return false;
        }

        // Check custom rules
        return apply_filters('emailit_should_send_notification', true, $error_code, $error_level, $context);
    }

    /**
     * Check if error is suppressed
     */
    private function is_suppressed($error_code, $error_level) {
        // Check global suppression
        if ($this->suppression_settings['global_suppression']) {
            return true;
        }

        // Check error-specific suppression
        if (in_array($error_code, $this->suppression_settings['suppressed_errors'])) {
            return true;
        }

        // Check level-specific suppression
        if (in_array($error_level, $this->suppression_settings['suppressed_levels'])) {
            return true;
        }

        // Check time-based suppression
        if ($this->suppression_settings['suppress_until'] && 
            time() < $this->suppression_settings['suppress_until']) {
            return true;
        }

        return false;
    }

    /**
     * Check frequency limits
     */
    private function check_frequency_limits($error_code, $error_level) {
        $rule = $this->notification_rules[$error_level] ?? $this->notification_rules['error'];
        $max_notifications = $rule['max_notifications'];
        
        // Get notification count for this error in the last hour
        $count = $this->get_notification_count($error_code, 3600);
        
        return $count < $max_notifications;
    }

    /**
     * Get notification count for error
     */
    private function get_notification_count($error_code, $time_window) {
        global $wpdb;

        $table = $wpdb->prefix . 'emailit_error_notifications';
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} 
             WHERE error_code = %s 
             AND created_at >= DATE_SUB(NOW(), INTERVAL %d SECOND)",
            $error_code,
            $time_window
        ));

        return intval($count);
    }

    /**
     * Send notifications
     */
    private function send_notifications($error_code, $error_message, $context, $channels) {
        foreach ($channels as $channel) {
            if (isset($this->channels[$channel]) && $this->channels[$channel]['enabled']) {
                try {
                    call_user_func($this->channels[$channel]['handler'], $error_code, $error_message, $context);
                    $this->log_notification($error_code, $channel, 'sent');
                } catch (Exception $e) {
                    $this->logger->log(
                        "Failed to send {$channel} notification: " . $e->getMessage(),
                        Emailit_Logger::LEVEL_ERROR,
                        array('error_code' => $error_code, 'channel' => $channel)
                    );
                    $this->log_notification($error_code, $channel, 'failed');
                }
            }
        }
    }

    /**
     * Send email notification
     */
    public function send_email_notification($error_code, $error_message, $context = array()) {
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        
        $subject = sprintf('[%s] Emailit Error: %s', $site_name, ucfirst($error_code));
        
        $message = $this->format_email_message($error_code, $error_message, $context);
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        wp_mail($admin_email, $subject, $message, $headers);
    }

    /**
     * Send admin notice
     */
    public function send_admin_notice($error_code, $error_message, $context = array()) {
        $notice_id = 'emailit_error_' . $error_code . '_' . time();
        
        $notice = array(
            'id' => $notice_id,
            'type' => 'error',
            'message' => $this->format_admin_notice_message($error_code, $error_message, $context),
            'dismissible' => true,
            'data' => array(
                'error_code' => $error_code,
                'error_message' => $error_message,
                'context' => $context
            )
        );
        
        // Store notice
        $notices = get_transient('emailit_error_notices') ?: array();
        $notices[$notice_id] = $notice;
        set_transient('emailit_error_notices', $notices, 3600);
    }

    /**
     * Send webhook notification
     */
    public function send_webhook_notification($error_code, $error_message, $context = array()) {
        $webhook_url = get_option('emailit_error_webhook_url');
        
        if (!$webhook_url) {
            return;
        }

        $payload = array(
            'error_code' => $error_code,
            'error_message' => $error_message,
            'context' => $context,
            'timestamp' => current_time('mysql'),
            'site_url' => get_site_url(),
            'plugin_version' => EMAILIT_VERSION
        );

        wp_remote_post($webhook_url, array(
            'body' => wp_json_encode($payload),
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-Emailit-Error-Notification' => 'true'
            ),
            'timeout' => 10
        ));
    }

    /**
     * Send Slack notification
     */
    public function send_slack_notification($error_code, $error_message, $context = array()) {
        $webhook_url = get_option('emailit_error_slack_webhook');
        
        if (!$webhook_url) {
            return;
        }

        $site_name = get_bloginfo('name');
        $site_url = get_site_url();
        
        $payload = array(
            'text' => "🚨 Emailit Error on {$site_name}",
            'attachments' => array(
                array(
                    'color' => 'danger',
                    'fields' => array(
                        array(
                            'title' => 'Error Code',
                            'value' => $error_code,
                            'short' => true
                        ),
                        array(
                            'title' => 'Error Message',
                            'value' => $error_message,
                            'short' => false
                        ),
                        array(
                            'title' => 'Site',
                            'value' => "<{$site_url}|{$site_name}>",
                            'short' => true
                        ),
                        array(
                            'title' => 'Time',
                            'value' => current_time('mysql'),
                            'short' => true
                        )
                    )
                )
            )
        );

        wp_remote_post($webhook_url, array(
            'body' => wp_json_encode($payload),
            'headers' => array('Content-Type' => 'application/json'),
            'timeout' => 10
        ));
    }

    /**
     * Format email message
     */
    private function format_email_message($error_code, $error_message, $context) {
        $site_name = get_bloginfo('name');
        $site_url = get_site_url();
        $admin_url = admin_url('admin.php?page=emailit-settings');
        
        $html = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #d63638; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .error-details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #d63638; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                .button { display: inline-block; padding: 10px 20px; background: #0073aa; color: white; text-decoration: none; border-radius: 3px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🚨 Emailit Error Alert</h1>
                    <p>{$site_name}</p>
                </div>
                <div class='content'>
                    <div class='error-details'>
                        <h2>Error Details</h2>
                        <p><strong>Error Code:</strong> " . esc_html($error_code) . "</p>
                        <p><strong>Error Message:</strong> " . esc_html($error_message) . "</p>
                        <p><strong>Time:</strong> " . current_time('mysql') . "</p>
                        <p><strong>Site:</strong> <a href='{$site_url}'>{$site_url}</a></p>
                    </div>
                    
                    <p>Please check your Emailit plugin settings and take appropriate action.</p>
                    
                    <p style='text-align: center;'>
                        <a href='{$admin_url}' class='button'>View Plugin Settings</a>
                    </p>
                </div>
                <div class='footer'>
                    <p>This error notification was generated by the Emailit Integration plugin on {$site_name}</p>
                </div>
            </div>
        </body>
        </html>";
        
        return $html;
    }

    /**
     * Format admin notice message
     */
    private function format_admin_notice_message($error_code, $error_message, $context) {
        $admin_url = admin_url('admin.php?page=emailit-settings');
        
        return sprintf(
            '🚨 <strong>Emailit Error:</strong> %s - %s <a href="%s">View Settings</a> | <a href="#" onclick="emailitDismissErrorNotice(\'%s\')">Dismiss</a>',
            esc_html($error_code),
            esc_html($error_message),
            $admin_url,
            $error_code
        );
    }

    /**
     * Schedule notification
     */
    private function schedule_notification($error_code, $error_message, $context, $rule) {
        $hook = 'emailit_delayed_notification';
        $args = array($error_code, $error_message, $context, $rule['channels']);
        
        wp_schedule_single_event(time() + $rule['escalation_delay'], $hook, $args);
        
        add_action($hook, array($this, 'send_delayed_notification'), 10, 4);
    }

    /**
     * Send delayed notification
     */
    public function send_delayed_notification($error_code, $error_message, $context, $channels) {
        // Check if error still exists or has been resolved
        if ($this->is_error_resolved($error_code, $context)) {
            return;
        }
        
        $this->send_notifications($error_code, $error_message, $context, $channels);
    }

    /**
     * Check if error is resolved
     */
    private function is_error_resolved($error_code, $context) {
        // Simple check - can be enhanced with more sophisticated logic
        return false;
    }

    /**
     * Trigger escalation
     */
    private function trigger_escalation($error_code, $error_message, $context) {
        do_action('emailit_error_escalation', $error_code, $context);
    }

    /**
     * Send escalation notification
     */
    private function send_escalation_notification($error_code, $context) {
        $this->send_notifications(
            $error_code,
            'Error has escalated - immediate attention required',
            $context,
            array('email', 'webhook', 'slack')
        );
    }

    /**
     * Log notification
     */
    private function log_notification($error_code, $channel, $status) {
        global $wpdb;

        $table = $wpdb->prefix . 'emailit_error_notifications';
        
        $wpdb->insert($table, array(
            'error_code' => $error_code,
            'channel' => $channel,
            'status' => $status,
            'created_at' => current_time('mysql')
        ));
    }

    /**
     * Load suppression settings
     */
    private function load_suppression_settings() {
        $this->suppression_settings = get_option('emailit_error_suppression', array(
            'global_suppression' => false,
            'suppressed_errors' => array(),
            'suppressed_levels' => array(),
            'suppress_until' => null
        ));
    }

    /**
     * Get notification statistics
     */
    public function get_notification_statistics() {
        global $wpdb;

        $table = $wpdb->prefix . 'emailit_error_notifications';
        
        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_notifications,
                COUNT(CASE WHEN status = 'sent' THEN 1 END) as sent_notifications,
                COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_notifications,
                COUNT(DISTINCT error_code) as unique_errors
             FROM {$table}"
        );

        return array(
            'total_notifications' => intval($stats->total_notifications),
            'sent_notifications' => intval($stats->sent_notifications),
            'failed_notifications' => intval($stats->failed_notifications),
            'unique_errors' => intval($stats->unique_errors),
            'success_rate' => $stats->total_notifications > 0 ? 
                ($stats->sent_notifications / $stats->total_notifications) * 100 : 0
        );
    }
}
