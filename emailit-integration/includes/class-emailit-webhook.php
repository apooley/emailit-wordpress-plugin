<?php
/**
 * Emailit Webhook Handler Class
 *
 * Handles incoming webhook requests from Emailit API for email status updates.
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

class Emailit_Webhook {

    /**
     * Logger instance
     */
    private $logger;

    /**
     * Webhook secret
     */
    private $webhook_secret;

    /**
     * Rate limiting
     */
    private $rate_limit_requests = 100; // Requests per minute
    private $rate_limit_window = 60; // Seconds

    /**
     * Constructor
     */
    public function __construct($logger) {
        $this->logger = $logger;
        $this->webhook_secret = get_option('emailit_webhook_secret', '');
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        register_rest_route('emailit/v1', '/webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_webhook'),
            'permission_callback' => array($this, 'verify_webhook_permission'),
            'args' => array()
        ));

        // Health check endpoint
        register_rest_route('emailit/v1', '/webhook/health', array(
            'methods' => 'GET',
            'callback' => array($this, 'health_check'),
            'permission_callback' => '__return_true'
        ));
    }

    /**
     * Handle incoming webhook
     */
    public function handle_webhook($request) {
        // Rate limiting check
        if (!$this->check_rate_limit()) {
            return new WP_Error(
                'rate_limit_exceeded',
                __('Rate limit exceeded. Please try again later.', 'emailit-integration'),
                array('status' => 429)
            );
        }

        // Get request data
        $body = $request->get_body();
        $headers = $request->get_headers();

        // Verify webhook signature if secret is configured
        if (!empty($this->webhook_secret)) {
            if (!$this->verify_signature($body, $headers)) {
                $this->logger->log('Webhook signature verification failed', Emailit_Logger::LEVEL_ERROR, array(
                    'headers' => $headers,
                    'ip' => $this->get_client_ip()
                ));

                return new WP_Error(
                    'invalid_signature',
                    __('Webhook signature verification failed.', 'emailit-integration'),
                    array('status' => 401)
                );
            }
        }

        // Parse webhook data
        $webhook_data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error(
                'invalid_json',
                __('Invalid JSON in webhook payload.', 'emailit-integration'),
                array('status' => 400)
            );
        }

        // Validate required fields
        $validation = $this->validate_webhook_data($webhook_data);
        if (is_wp_error($validation)) {
            return $validation;
        }

        // Process webhook
        $result = $this->process_webhook($webhook_data);

        if (is_wp_error($result)) {
            return $result;
        }

        // Log successful webhook
        $this->logger->log('Webhook processed successfully', Emailit_Logger::LEVEL_INFO, array(
            'event_type' => $webhook_data['event_type'],
            'email_id' => isset($webhook_data['email_id']) ? $webhook_data['email_id'] : null
        ));

        // Trigger action hook
        do_action('emailit_webhook_received', $webhook_data, $result);

        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Webhook processed successfully'
        ));
    }

    /**
     * Verify webhook permission
     */
    public function verify_webhook_permission($request) {
        // For webhooks, we verify signature instead of WordPress permissions
        return true;
    }

    /**
     * Health check endpoint
     */
    public function health_check($request) {
        return rest_ensure_response(array(
            'status' => 'ok',
            'timestamp' => current_time('mysql'),
            'version' => EMAILIT_VERSION
        ));
    }

    /**
     * Verify webhook signature
     */
    private function verify_signature($body, $headers) {
        // Check for signature header
        $signature_header = isset($headers['x_emailit_signature']) ? $headers['x_emailit_signature'][0] :
                           (isset($headers['X-Emailit-Signature']) ? $headers['X-Emailit-Signature'][0] : null);

        if (empty($signature_header)) {
            return false;
        }

        // Extract signature from header (format: sha256=signature)
        if (!preg_match('/^sha256=(.+)$/', $signature_header, $matches)) {
            return false;
        }

        $expected_signature = $matches[1];

        // Calculate signature
        $calculated_signature = hash_hmac('sha256', $body, $this->webhook_secret);

        // Compare signatures securely
        return hash_equals($calculated_signature, $expected_signature);
    }

    /**
     * Validate webhook data
     */
    private function validate_webhook_data($data) {
        // Check required fields
        if (!isset($data['event_type'])) {
            return new WP_Error(
                'missing_event_type',
                __('Webhook missing event_type field.', 'emailit-integration'),
                array('status' => 400)
            );
        }

        // Validate event type
        $valid_events = array(
            'email.sent',
            'email.delivered',
            'email.bounced',
            'email.complained',
            'email.failed',
            'email.clicked',
            'email.opened'
        );

        if (!in_array($data['event_type'], $valid_events)) {
            return new WP_Error(
                'invalid_event_type',
                sprintf(__('Invalid event type: %s', 'emailit-integration'), $data['event_type']),
                array('status' => 400)
            );
        }

        return true;
    }

    /**
     * Process webhook data
     */
    private function process_webhook($webhook_data) {
        $event_type = $webhook_data['event_type'];
        $email_id = isset($webhook_data['email_id']) ? $webhook_data['email_id'] : null;

        // Check if this email belongs to our site
        if (!$this->is_email_from_this_site($webhook_data)) {
            // Log the ignored webhook for debugging
            $this->logger->log('Webhook ignored - email not from this site', Emailit_Logger::LEVEL_DEBUG, array(
                'event_type' => $event_type,
                'email_id' => $email_id,
                'from_email' => isset($webhook_data['from_email']) ? $webhook_data['from_email'] : null,
                'site_domain' => $this->get_site_domain()
            ));

            return array(
                'ignored' => true,
                'reason' => 'Email not from this site'
            );
        }

        // Log webhook event
        $webhook_log_id = $this->logger->log_webhook($webhook_data, $email_id);

        // Map event type to email status
        $status_mapping = array(
            'email.sent' => Emailit_Logger::STATUS_SENT,
            'email.delivered' => Emailit_Logger::STATUS_DELIVERED,
            'email.bounced' => Emailit_Logger::STATUS_BOUNCED,
            'email.complained' => Emailit_Logger::STATUS_COMPLAINED,
            'email.failed' => Emailit_Logger::STATUS_FAILED
        );

        // Update email status if it's a status-changing event
        if (isset($status_mapping[$event_type]) && !empty($email_id)) {
            $new_status = $status_mapping[$event_type];

            // Prepare additional details
            $details = array(
                'event_type' => $event_type,
                'webhook_id' => $webhook_log_id,
                'timestamp' => current_time('mysql')
            );

            // Add specific event data
            if (isset($webhook_data['bounce_reason'])) {
                $details['bounce_reason'] = $webhook_data['bounce_reason'];
            }

            if (isset($webhook_data['complaint_reason'])) {
                $details['complaint_reason'] = $webhook_data['complaint_reason'];
            }

            if (isset($webhook_data['failure_reason'])) {
                $details['failure_reason'] = $webhook_data['failure_reason'];
            }

            // Update email status
            $updated = $this->logger->update_email_status($email_id, $new_status, $details);

            if (!$updated) {
                $this->logger->log('Failed to update email status from webhook', Emailit_Logger::LEVEL_WARNING, array(
                    'email_id' => $email_id,
                    'event_type' => $event_type,
                    'webhook_data' => $webhook_data
                ));
            }

            // Trigger status-specific action hooks
            do_action('emailit_status_updated', $email_id, $new_status, $details);
            do_action("emailit_email_{$new_status}", $email_id, $webhook_data, $details);
        }

        // Handle tracking events (opens, clicks) differently
        if (in_array($event_type, array('email.opened', 'email.clicked'))) {
            $this->handle_tracking_event($event_type, $webhook_data);
        }

        return array(
            'webhook_log_id' => $webhook_log_id,
            'status_updated' => isset($updated) ? $updated : false
        );
    }

    /**
     * Handle tracking events (opens, clicks)
     */
    private function handle_tracking_event($event_type, $webhook_data) {
        // These events don't change email status but provide tracking data
        $email_id = isset($webhook_data['email_id']) ? $webhook_data['email_id'] : null;

        if (!$email_id) {
            return;
        }

        // Get existing email log to append tracking data
        global $wpdb;
        $logs_table = $wpdb->prefix . 'emailit_logs';

        $existing_details = $wpdb->get_var($wpdb->prepare(
            "SELECT details FROM {$logs_table} WHERE email_id = %s",
            $email_id
        ));

        $details = !empty($existing_details) ? json_decode($existing_details, true) : array();

        // Initialize tracking arrays if not exist
        if (!isset($details['tracking'])) {
            $details['tracking'] = array();
        }

        if (!isset($details['tracking'][$event_type])) {
            $details['tracking'][$event_type] = array();
        }

        // Add tracking event
        $tracking_event = array(
            'timestamp' => current_time('mysql'),
            'user_agent' => isset($webhook_data['user_agent']) ? $webhook_data['user_agent'] : null,
            'ip_address' => isset($webhook_data['ip_address']) ? $webhook_data['ip_address'] : null
        );

        // Add specific tracking data
        if ($event_type === 'email.clicked' && isset($webhook_data['link_url'])) {
            $tracking_event['link_url'] = $webhook_data['link_url'];
        }

        $details['tracking'][$event_type][] = $tracking_event;

        // Update email log with tracking data
        $wpdb->update(
            $logs_table,
            array(
                'details' => wp_json_encode($details),
                'updated_at' => current_time('mysql')
            ),
            array('email_id' => $email_id),
            array('%s', '%s'),
            array('%s')
        );

        // Trigger tracking-specific hooks
        do_action("emailit_email_{$event_type}", $email_id, $webhook_data, $tracking_event);
    }

    /**
     * Check rate limiting
     */
    private function check_rate_limit() {
        $client_ip = $this->get_client_ip();
        $cache_key = 'emailit_webhook_rate_limit_' . md5($client_ip);

        $requests = get_transient($cache_key);

        if ($requests === false) {
            // First request in window
            set_transient($cache_key, 1, $this->rate_limit_window);
            return true;
        }

        if ($requests >= $this->rate_limit_requests) {
            // Rate limit exceeded
            return false;
        }

        // Increment counter
        set_transient($cache_key, $requests + 1, $this->rate_limit_window);
        return true;
    }

    /**
     * Get client IP address
     */
    private function get_client_ip() {
        // Check for various headers that might contain the real IP
        $headers = array(
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = trim($_SERVER[$header]);

                // Handle comma-separated IPs (X-Forwarded-For can contain multiple IPs)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }

                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        // Fallback to REMOTE_ADDR
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
    }

    /**
     * Get webhook endpoint URL
     */
    public function get_webhook_url() {
        return rest_url('emailit/v1/webhook');
    }

    /**
     * Test webhook endpoint
     */
    public function test_webhook() {
        $test_data = array(
            'event_type' => 'email.delivered',
            'email_id' => 'test_' . time(),
            'timestamp' => current_time('mysql'),
            'test' => true
        );

        // Create test signature if secret is configured
        $headers = array();
        if (!empty($this->webhook_secret)) {
            $signature = hash_hmac('sha256', wp_json_encode($test_data), $this->webhook_secret);
            $headers['X-Emailit-Signature'] = 'sha256=' . $signature;
        }

        // Make request to our own webhook endpoint
        $response = wp_remote_post($this->get_webhook_url(), array(
            'headers' => array_merge($headers, array(
                'Content-Type' => 'application/json'
            )),
            'body' => wp_json_encode($test_data),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code === 200) {
            return array(
                'success' => true,
                'message' => 'Webhook test successful',
                'response' => json_decode($response_body, true)
            );
        } else {
            return new WP_Error(
                'webhook_test_failed',
                sprintf(__('Webhook test failed with status %d: %s', 'emailit-integration'), $response_code, $response_body)
            );
        }
    }

    /**
     * Register webhook with Emailit API
     */
    public function register_webhook_with_api($api) {
        $webhook_url = $this->get_webhook_url();

        // API call to register webhook (this would depend on Emailit's API)
        $registration_data = array(
            'url' => $webhook_url,
            'events' => array(
                'email.sent',
                'email.delivered',
                'email.bounced',
                'email.complained',
                'email.failed',
                'email.opened',
                'email.clicked'
            )
        );

        if (!empty($this->webhook_secret)) {
            $registration_data['secret'] = $this->webhook_secret;
        }

        // This would be implemented based on Emailit's webhook registration API
        // For now, we'll just log the attempt
        $this->logger->log(
            'Webhook registration attempted',
            Emailit_Logger::LEVEL_INFO,
            array(
                'webhook_url' => $webhook_url,
                'events' => $registration_data['events']
            )
        );

        return true;
    }

    /**
     * Update webhook secret
     */
    public function update_webhook_secret($secret = null) {
        if ($secret === null) {
            $secret = wp_generate_password(32, false);
        }

        update_option('emailit_webhook_secret', $secret);
        $this->webhook_secret = $secret;

        return $secret;
    }

    /**
     * Get webhook statistics
     */
    public function get_webhook_stats($days = 30) {
        global $wpdb;

        $date_from = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $stats = $wpdb->get_results($wpdb->prepare("
            SELECT event_type, COUNT(*) as count
            FROM {$this->logger->webhook_logs_table}
            WHERE processed_at >= %s
            GROUP BY event_type
            ORDER BY count DESC
        ", $date_from), ARRAY_A);

        return $stats;
    }

    /**
     * Check if the webhook email belongs to this site
     */
    private function is_email_from_this_site($webhook_data) {
        // Get the site domain for comparison
        $site_domain = $this->get_site_domain();

        // Check multiple ways the email could be identified as from this site
        $identifiers = array();

        // 1. Check from_email domain
        if (isset($webhook_data['from_email'])) {
            $from_domain = $this->extract_domain_from_email($webhook_data['from_email']);
            $identifiers[] = $from_domain;
        }

        // 2. Check if we have the email_id in our logs (most reliable)
        if (isset($webhook_data['email_id'])) {
            if ($this->email_exists_in_logs($webhook_data['email_id'])) {
                return true;
            }
        }

        // 3. Check against configured from_email addresses
        $configured_emails = $this->get_configured_from_emails();
        if (isset($webhook_data['from_email'])) {
            if (in_array(strtolower($webhook_data['from_email']), array_map('strtolower', $configured_emails))) {
                return true;
            }
        }

        // 4. Check domain match
        foreach ($identifiers as $identifier) {
            if (!empty($identifier) && $this->domains_match($identifier, $site_domain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the current site domain
     */
    private function get_site_domain() {
        $site_url = get_site_url();
        $parsed = parse_url($site_url);
        return isset($parsed['host']) ? strtolower($parsed['host']) : '';
    }

    /**
     * Extract domain from email address
     */
    private function extract_domain_from_email($email) {
        if (strpos($email, '@') === false) {
            return '';
        }

        $parts = explode('@', $email);
        return strtolower(trim(end($parts)));
    }

    /**
     * Check if domains match (including subdomain handling)
     */
    private function domains_match($domain1, $domain2) {
        $domain1 = strtolower(trim($domain1));
        $domain2 = strtolower(trim($domain2));

        // Exact match
        if ($domain1 === $domain2) {
            return true;
        }

        // Check if one is a subdomain of the other
        if (strpos($domain1, $domain2) !== false || strpos($domain2, $domain1) !== false) {
            // Remove www. prefix for comparison
            $clean_domain1 = preg_replace('/^www\./', '', $domain1);
            $clean_domain2 = preg_replace('/^www\./', '', $domain2);

            return $clean_domain1 === $clean_domain2;
        }

        return false;
    }

    /**
     * Check if email ID exists in our logs
     */
    private function email_exists_in_logs($email_id) {
        global $wpdb;

        $logs_table = $wpdb->prefix . 'emailit_logs';

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$logs_table} WHERE email_id = %s LIMIT 1",
            $email_id
        ));

        return !empty($exists);
    }

    /**
     * Get configured from email addresses for this site
     */
    private function get_configured_from_emails() {
        $emails = array();

        // Default WordPress admin email
        $admin_email = get_bloginfo('admin_email');
        if (!empty($admin_email)) {
            $emails[] = $admin_email;
        }

        // Configured Emailit from email
        $emailit_from = get_option('emailit_from_email', '');
        if (!empty($emailit_from)) {
            $emails[] = $emailit_from;
        }

        // WordPress default from email (wordpress@domain.com)
        $site_domain = $this->get_site_domain();
        if (!empty($site_domain)) {
            $emails[] = 'wordpress@' . $site_domain;
            $emails[] = 'admin@' . $site_domain;
            $emails[] = 'noreply@' . $site_domain;
            $emails[] = 'no-reply@' . $site_domain;
        }

        // Allow developers to add custom email addresses that should be recognized as from this site
        // Usage: add_filter('emailit_webhook_recognized_from_emails', function($emails) {
        //     $emails[] = 'custom@mydomain.com';
        //     $emails[] = 'notifications@mydomain.com';
        //     return $emails;
        // });
        $emails = apply_filters('emailit_webhook_recognized_from_emails', $emails);

        return array_unique(array_filter($emails));
    }
}