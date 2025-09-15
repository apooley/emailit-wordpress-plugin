<?php
/**
 * Emailit Alert Manager
 *
 * Handles alerting, notifications, and escalation for health monitoring.
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

class Emailit_Alert_Manager {

    /**
     * Logger instance
     */
    private $logger;

    /**
     * Alert types and their configurations
     */
    private $alert_types = array(
        'api_connectivity' => array(
            'severity' => 'critical',
            'email_enabled' => true,
            'admin_notice_enabled' => true,
            'escalation_delay' => 0 // Immediate
        ),
        'webhook_endpoint' => array(
            'severity' => 'critical',
            'email_enabled' => true,
            'admin_notice_enabled' => true,
            'escalation_delay' => 300 // 5 minutes
        ),
        'database_health' => array(
            'severity' => 'critical',
            'email_enabled' => true,
            'admin_notice_enabled' => true,
            'escalation_delay' => 600 // 10 minutes
        ),
        'queue_processing' => array(
            'severity' => 'warning',
            'email_enabled' => true,
            'admin_notice_enabled' => true,
            'escalation_delay' => 1800 // 30 minutes
        ),
        'fluentcrm_integration' => array(
            'severity' => 'warning',
            'email_enabled' => false,
            'admin_notice_enabled' => true,
            'escalation_delay' => 3600 // 1 hour
        ),
        'error_rate' => array(
            'severity' => 'critical',
            'email_enabled' => true,
            'admin_notice_enabled' => true,
            'escalation_delay' => 900 // 15 minutes
        ),
        'performance' => array(
            'severity' => 'warning',
            'email_enabled' => false,
            'admin_notice_enabled' => true,
            'escalation_delay' => 1800 // 30 minutes
        )
    );

    /**
     * Alert suppression settings
     */
    private $suppression_settings = array(
        'maintenance_mode' => false,
        'suppress_until' => null,
        'suppressed_types' => array()
    );

    /**
     * Constructor
     */
    public function __construct($logger) {
        $this->logger = $logger;
        $this->load_suppression_settings();
        
        // Hook into WordPress admin
        add_action('admin_notices', array($this, 'display_admin_notices'));
        add_action('wp_ajax_emailit_dismiss_alert', array($this, 'dismiss_alert'));
        add_action('wp_ajax_emailit_suppress_alerts', array($this, 'suppress_alerts'));
    }

    /**
     * Trigger an alert
     */
    public function trigger_alert($type, $severity, $message, $data = array()) {
        // Check if alerts are suppressed
        if ($this->is_alert_suppressed($type)) {
            $this->logger->log("Alert suppressed: {$type} - {$message}", Emailit_Logger::LEVEL_DEBUG);
            return false;
        }

        // Get alert configuration
        $config = isset($this->alert_types[$type]) ? $this->alert_types[$type] : $this->get_default_alert_config();
        
        // Override severity if provided
        if ($severity) {
            $config['severity'] = $severity;
        }

        // Create alert record
        $alert = array(
            'type' => $type,
            'severity' => $config['severity'],
            'message' => $message,
            'data' => $data,
            'timestamp' => current_time('mysql'),
            'status' => 'active',
            'dismissed' => false
        );

        // Store alert
        $alert_id = $this->store_alert($alert);

        // Send notifications
        $this->send_notifications($alert, $config);

        // Log the alert
        $this->logger->log("Alert triggered: {$type} ({$config['severity']}) - {$message}", 
            $config['severity'] === 'critical' ? Emailit_Logger::LEVEL_ERROR : Emailit_Logger::LEVEL_WARNING,
            $alert
        );

        return $alert_id;
    }

    /**
     * Send notifications for an alert
     */
    private function send_notifications($alert, $config) {
        // Send email notification
        if ($config['email_enabled']) {
            $this->send_email_notification($alert);
        }

        // Add admin notice
        if ($config['admin_notice_enabled']) {
            $this->add_admin_notice($alert);
        }

        // Schedule escalation if needed
        if ($config['escalation_delay'] > 0) {
            $this->schedule_escalation($alert, $config['escalation_delay']);
        }
    }

    /**
     * Send email notification
     */
    private function send_email_notification($alert) {
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        
        $subject = sprintf('[%s] Emailit Alert: %s', $site_name, ucfirst($alert['type']));
        
        $message = $this->format_email_message($alert);
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        wp_mail($admin_email, $subject, $message, $headers);
        
        $this->logger->log("Alert email sent to {$admin_email}", Emailit_Logger::LEVEL_INFO, array(
            'alert_type' => $alert['type'],
            'severity' => $alert['severity']
        ));
    }

    /**
     * Format email message
     */
    private function format_email_message($alert) {
        $site_name = get_bloginfo('name');
        $site_url = get_site_url();
        $admin_url = admin_url('admin.php?page=emailit-settings');
        
        $severity_color = $alert['severity'] === 'critical' ? '#d63638' : '#f56e28';
        $severity_icon = $alert['severity'] === 'critical' ? '🚨' : '⚠️';
        
        $html = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: {$severity_color}; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .alert-details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid {$severity_color}; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                .button { display: inline-block; padding: 10px 20px; background: #0073aa; color: white; text-decoration: none; border-radius: 3px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>{$severity_icon} Emailit Alert</h1>
                    <p>{$site_name}</p>
                </div>
                <div class='content'>
                    <div class='alert-details'>
                        <h2>Alert Details</h2>
                        <p><strong>Type:</strong> " . ucfirst(str_replace('_', ' ', $alert['type'])) . "</p>
                        <p><strong>Severity:</strong> " . ucfirst($alert['severity']) . "</p>
                        <p><strong>Time:</strong> " . $alert['timestamp'] . "</p>
                        <p><strong>Message:</strong> " . esc_html($alert['message']) . "</p>
                    </div>
                    
                    <p>Please check your Emailit plugin settings and take appropriate action.</p>
                    
                    <p style='text-align: center;'>
                        <a href='{$admin_url}' class='button'>View Plugin Settings</a>
                    </p>
                </div>
                <div class='footer'>
                    <p>This alert was generated by the Emailit Integration plugin on {$site_name}</p>
                    <p><a href='{$site_url}'>{$site_url}</a></p>
                </div>
            </div>
        </body>
        </html>";
        
        return $html;
    }

    /**
     * Add admin notice
     */
    private function add_admin_notice($alert) {
        $notice_id = 'emailit_alert_' . $alert['type'] . '_' . time();
        
        $severity_class = $alert['severity'] === 'critical' ? 'error' : 'warning';
        $severity_icon = $alert['severity'] === 'critical' ? '🚨' : '⚠️';
        
        $notice = array(
            'id' => $notice_id,
            'type' => $severity_class,
            'message' => $this->format_admin_notice_message($alert),
            'dismissible' => true,
            'data' => $alert
        );
        
        // Store notice in transient
        $notices = get_transient('emailit_admin_notices') ?: array();
        $notices[$notice_id] = $notice;
        set_transient('emailit_admin_notices', $notices, 3600); // 1 hour
    }

    /**
     * Format admin notice message
     */
    private function format_admin_notice_message($alert) {
        $admin_url = admin_url('admin.php?page=emailit-settings');
        
        return sprintf(
            '%s <strong>Emailit Alert:</strong> %s <a href="%s">View Settings</a> | <a href="#" onclick="emailitDismissAlert(\'%s\')">Dismiss</a>',
            $alert['severity'] === 'critical' ? '🚨' : '⚠️',
            esc_html($alert['message']),
            $admin_url,
            $alert['type']
        );
    }

    /**
     * Display admin notices
     */
    public function display_admin_notices() {
        $notices = get_transient('emailit_admin_notices') ?: array();
        
        foreach ($notices as $notice_id => $notice) {
            printf(
                '<div class="notice notice-%s is-dismissible" id="%s">%s</div>',
                esc_attr($notice['type']),
                esc_attr($notice_id),
                $notice['message']
            );
        }
        
        // Add JavaScript for dismissing alerts
        if (!empty($notices)) {
            ?>
            <script>
            function emailitDismissAlert(alertType) {
                jQuery.post(ajaxurl, {
                    action: 'emailit_dismiss_alert',
                    alert_type: alertType,
                    nonce: '<?php echo wp_create_nonce('emailit_dismiss_alert'); ?>'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    }
                });
            }
            </script>
            <?php
        }
    }

    /**
     * Dismiss alert via AJAX
     */
    public function dismiss_alert() {
        check_ajax_referer('emailit_dismiss_alert', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $alert_type = sanitize_text_field($_POST['alert_type']);
        
        // Remove from admin notices
        $notices = get_transient('emailit_admin_notices') ?: array();
        foreach ($notices as $notice_id => $notice) {
            if ($notice['data']['type'] === $alert_type) {
                unset($notices[$notice_id]);
            }
        }
        set_transient('emailit_admin_notices', $notices, 3600);
        
        // Mark as dismissed in database
        $this->dismiss_alert_in_database($alert_type);
        
        wp_send_json_success();
    }

    /**
     * Suppress alerts via AJAX
     */
    public function suppress_alerts() {
        check_ajax_referer('emailit_suppress_alerts', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $duration = intval($_POST['duration']); // minutes
        $types = array_map('sanitize_text_field', $_POST['types'] ?: array());
        
        $this->suppress_alerts_until(time() + ($duration * 60), $types);
        
        wp_send_json_success(array(
            'message' => sprintf('Alerts suppressed for %d minutes', $duration)
        ));
    }

    /**
     * Get active alerts
     */
    public function get_active_alerts() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'emailit_alerts';
        
        // Check if table exists before querying
        if (!$wpdb->get_var("SHOW TABLES LIKE '{$table}'")) {
            return array(); // Return empty array if table doesn't exist
        }
        
        $alerts = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE status = 'active' AND dismissed = 0 ORDER BY created_at DESC LIMIT 10"
        ));
        
        return $alerts ? $alerts : array();
    }

    /**
     * Store alert in database
     */
    private function store_alert($alert) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'emailit_alerts';
        
        // Check if table exists before inserting
        if (!$wpdb->get_var("SHOW TABLES LIKE '{$table}'")) {
            return false; // Skip storing if table doesn't exist
        }
        
        $result = $wpdb->insert($table, array(
            'alert_type' => $alert['type'],
            'severity' => $alert['severity'],
            'message' => $alert['message'],
            'data' => wp_json_encode($alert['data']),
            'status' => $alert['status'],
            'dismissed' => $alert['dismissed'],
            'created_at' => $alert['timestamp']
        ));
        
        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Dismiss alert in database
     */
    private function dismiss_alert_in_database($alert_type) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'emailit_alerts';
        
        $wpdb->update(
            $table,
            array('dismissed' => 1),
            array('alert_type' => $alert_type, 'status' => 'active'),
            array('%d'),
            array('%s', '%s')
        );
    }

    /**
     * Suppress alerts until a specific time
     */
    public function suppress_alerts_until($timestamp, $types = array()) {
        $this->suppression_settings['suppress_until'] = $timestamp;
        $this->suppression_settings['suppressed_types'] = $types;
        
        update_option('emailit_alert_suppression', $this->suppression_settings);
        
        $this->logger->log('Alerts suppressed', Emailit_Logger::LEVEL_INFO, array(
            'until' => date('Y-m-d H:i:s', $timestamp),
            'types' => $types
        ));
    }

    /**
     * Check if alert is suppressed
     */
    private function is_alert_suppressed($type) {
        // Check if all alerts are suppressed
        if ($this->suppression_settings['suppress_until'] && 
            time() < $this->suppression_settings['suppress_until']) {
            return true;
        }
        
        // Check if specific type is suppressed
        if (in_array($type, $this->suppression_settings['suppressed_types'])) {
            return true;
        }
        
        return false;
    }

    /**
     * Load suppression settings
     */
    private function load_suppression_settings() {
        $this->suppression_settings = get_option('emailit_alert_suppression', $this->suppression_settings);
    }

    /**
     * Get default alert configuration
     */
    private function get_default_alert_config() {
        return array(
            'severity' => 'warning',
            'email_enabled' => false,
            'admin_notice_enabled' => true,
            'escalation_delay' => 0
        );
    }

    /**
     * Schedule escalation
     */
    private function schedule_escalation($alert, $delay) {
        // Implementation for escalation scheduling
        // This could use WordPress cron or a custom scheduling system
    }
}
