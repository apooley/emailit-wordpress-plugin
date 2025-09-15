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
     * Bounce classifier instance
     */
    private $bounce_classifier;

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

        // Initialize bounce classifier
        $this->bounce_classifier = new Emailit_Bounce_Classifier($logger);

        // Initialize FluentCRM integration if available
        $this->init_fluentcrm_integration();
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
        // Check if webhooks are enabled
        if (!get_option('emailit_enable_webhooks', 1)) {
            return new WP_REST_Response(array(
                'message' => 'Webhooks are disabled',
                'status' => 'disabled'
            ), 200);
        }

        // Log all incoming webhook requests for debugging
        $body = $request->get_body();
        $headers = $request->get_headers();
        $method = $request->get_method();
        $client_ip = $this->get_client_ip();

        $this->logger->log('Incoming webhook request received', Emailit_Logger::LEVEL_INFO, array(
            'method' => $method,
            'client_ip' => $client_ip,
            'headers' => $headers,
            'body_length' => strlen($body),
            'body_preview' => substr($body, 0, 500), // First 500 chars for debugging
            'full_body' => $body, // Full body for complete debugging
            'timestamp' => current_time('mysql')
        ));

        // Rate limiting check
        if (!$this->check_rate_limit()) {
            $this->logger->log('Webhook rejected - rate limit exceeded', Emailit_Logger::LEVEL_WARNING, array(
                'client_ip' => $client_ip
            ));
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
            $this->logger->log('Webhook secret is configured, verifying signature', Emailit_Logger::LEVEL_INFO, array(
                'webhook_secret_length' => strlen($this->webhook_secret),
                'signature_header_present' => isset($headers['x-emailit-signature']) || isset($headers['X-Emailit-Signature'])
            ));

            if (!$this->verify_signature($body, $headers)) {
                $this->logger->log('Webhook signature verification failed', Emailit_Logger::LEVEL_ERROR, array(
                    'headers' => $headers,
                    'body_hash' => hash('sha256', $body),
                    'ip' => $client_ip,
                    'webhook_secret_configured' => !empty($this->webhook_secret)
                ));

                return new WP_Error(
                    'invalid_signature',
                    __('Webhook signature verification failed.', 'emailit-integration'),
                    array('status' => 401)
                );
            } else {
                $this->logger->log('Webhook signature verification successful', Emailit_Logger::LEVEL_INFO);
            }
        } else {
            $this->logger->log('No webhook secret configured, skipping signature verification', Emailit_Logger::LEVEL_INFO);
        }

        // Parse webhook data
        $webhook_data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->log('Webhook JSON parsing failed', Emailit_Logger::LEVEL_ERROR, array(
                'json_error' => json_last_error_msg(),
                'body' => $body
            ));
            return new WP_Error(
                'invalid_json',
                __('Invalid JSON in webhook payload.', 'emailit-integration'),
                array('status' => 400)
            );
        }

        $this->logger->log('Webhook JSON parsed successfully', Emailit_Logger::LEVEL_INFO, array(
            'parsed_data' => $webhook_data,
            'is_array' => is_array($webhook_data),
            'array_count' => is_array($webhook_data) ? count($webhook_data) : 'N/A'
        ));

        // Handle Emailit's array format - they send an array containing webhook objects
        if (is_array($webhook_data) && isset($webhook_data[0])) {
            $this->logger->log('Webhook data is an array, processing each webhook event', Emailit_Logger::LEVEL_INFO, array(
                'webhook_count' => count($webhook_data)
            ));

            $results = array();
            foreach ($webhook_data as $index => $single_webhook) {
                $this->logger->log('Processing webhook ' . ($index + 1) . ' of ' . count($webhook_data), Emailit_Logger::LEVEL_INFO, array(
                    'webhook_data' => $single_webhook
                ));

                // Validate required fields for this webhook
                $validation = $this->validate_webhook_data($single_webhook);
                if (is_wp_error($validation)) {
                    $this->logger->log('Webhook validation failed for webhook ' . ($index + 1), Emailit_Logger::LEVEL_ERROR, array(
                        'error_code' => $validation->get_error_code(),
                        'error_message' => $validation->get_error_message(),
                        'webhook_data' => $single_webhook
                    ));
                    $results[] = $validation;
                    continue;
                }

                // Process this webhook
                $result = $this->process_webhook($single_webhook);
                $results[] = $result;
            }

            // Return success if at least one webhook was processed successfully
            $successful = array_filter($results, function($result) {
                return !is_wp_error($result);
            });

            if (count($successful) > 0) {
                $this->logger->log('Webhook batch processed successfully', Emailit_Logger::LEVEL_INFO, array(
                    'total_webhooks' => count($webhook_data),
                    'successful' => count($successful),
                    'failed' => count($webhook_data) - count($successful)
                ));
            }

            $result = count($successful) > 0 ? $successful[0] : $results[0];
        } else {
            // Handle single webhook object (legacy format)
            $this->logger->log('Processing single webhook object', Emailit_Logger::LEVEL_INFO);

            // Validate required fields
            $validation = $this->validate_webhook_data($webhook_data);
            if (is_wp_error($validation)) {
                $this->logger->log('Webhook validation failed', Emailit_Logger::LEVEL_ERROR, array(
                    'error_code' => $validation->get_error_code(),
                    'error_message' => $validation->get_error_message(),
                    'webhook_data' => $webhook_data
                ));
                return $validation;
            }

            $this->logger->log('Webhook validation passed', Emailit_Logger::LEVEL_INFO);

            // Process webhook
            $result = $this->process_webhook($webhook_data);
        }

        if (is_wp_error($result)) {
            return $result;
        }

        // Log successful webhook
        $event_type = isset($webhook_data['type']) ? $webhook_data['type'] : $webhook_data['event_type'];
        $email_id = null;
        if (isset($webhook_data['object']['email']['id'])) {
            $email_id = $webhook_data['object']['email']['id'];
        } elseif (isset($webhook_data['email_id'])) {
            $email_id = $webhook_data['email_id'];
        }

        $this->logger->log('Webhook processed successfully', Emailit_Logger::LEVEL_INFO, array(
            'event_type' => $event_type,
            'email_id' => $email_id
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

        // Get timestamp header
        $timestamp = isset($headers['x_emailit_timestamp']) ? $headers['x_emailit_timestamp'][0] :
                    (isset($headers['X-Emailit-Timestamp']) ? $headers['X-Emailit-Timestamp'][0] : null);

        // Handle signature format - Emailit sends direct hash, not sha256=hash format
        $expected_signature = $signature_header;

        // If it's in sha256=hash format, extract just the hash
        if (preg_match('/^sha256=(.+)$/', $signature_header, $matches)) {
            $expected_signature = $matches[1];
        }

        // Try different signature calculation methods
        $signatures_to_try = array();

        // Method 1: Just the body (original approach)
        $signatures_to_try[] = array(
            'method' => 'body_only',
            'signature' => hash_hmac('sha256', $body, $this->webhook_secret)
        );

        // Method 2: timestamp + body (common approach)
        if ($timestamp) {
            $signatures_to_try[] = array(
                'method' => 'timestamp_body',
                'signature' => hash_hmac('sha256', $timestamp . $body, $this->webhook_secret)
            );

            // Method 3: timestamp + "." + body (GitHub style)
            $signatures_to_try[] = array(
                'method' => 'timestamp_dot_body',
                'signature' => hash_hmac('sha256', $timestamp . '.' . $body, $this->webhook_secret)
            );
        }

        $signature_matched = false;
        $matched_method = null;

        foreach ($signatures_to_try as $attempt) {
            if (hash_equals($attempt['signature'], $expected_signature)) {
                $signature_matched = true;
                $matched_method = $attempt['method'];
                break;
            }
        }

        // Debug logging (only when WP_DEBUG is enabled)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->logger->log('Webhook signature verification details', Emailit_Logger::LEVEL_DEBUG, array(
                'signature_header' => $signature_header,
                'expected_signature' => $expected_signature,
                'timestamp' => $timestamp,
                'body_length' => strlen($body),
                'webhook_secret_length' => strlen($this->webhook_secret),
                'signatures_tried' => $signatures_to_try,
                'signature_matched' => $signature_matched,
                'matched_method' => $matched_method
            ));
        }

        return $signature_matched;
    }

    /**
     * Validate webhook data
     */
    private function validate_webhook_data($data) {
        // Check for event type field (handle both Emailit format 'type' and legacy 'event_type')
        $event_type = null;
        if (isset($data['type'])) {
            $event_type = $data['type'];
        } elseif (isset($data['event_type'])) {
            $event_type = $data['event_type'];
        } else {
            return new WP_Error(
                'missing_event_type',
                __('Webhook missing event type field (type or event_type).', 'emailit-integration'),
                array('status' => 400)
            );
        }

        // Validate event type using actual Emailit event types
        $valid_events = array(
            // Official Emailit event types from documentation
            'email.delivery.sent',
            'email.delivery.delivered',
            'email.delivery.bounced',
            'email.delivery.complained',
            'email.delivery.failed',
            'email.delivery.hardfail',
            'email.delivery.softfail',
            'email.delivery.error',
            'email.delivery.held',
            'email.delivery.delayed',
            'email.delivery.opened',
            'email.delivery.clicked',
            'email.delivery.unsubscribed',
            
            // Legacy event types for backwards compatibility
            'email.sent',
            'email.delivered',
            'email.bounced',
            'email.complained',
            'email.failed',
            'email.clicked',
            'email.opened',
            'email.unsubscribed'
        );

        if (!in_array($event_type, $valid_events)) {
            return new WP_Error(
                'invalid_event_type',
                sprintf(__('Invalid event type: %s', 'emailit-integration'), $event_type),
                array('status' => 400)
            );
        }

        return true;
    }

    /**
     * Process webhook data
     */
    private function process_webhook($webhook_data) {
        // Handle Emailit's official webhook format
        // According to EmailIt docs: { "event_id": "...", "type": "...", "object": { "id": "..." } }
        $event_type = isset($webhook_data['type']) ? $webhook_data['type'] : $webhook_data['event_type'];
        $event_id = isset($webhook_data['event_id']) ? $webhook_data['event_id'] : null;
        $email_id = null;

        // Extract email ID from Emailit's nested structure
        if (isset($webhook_data['object']['id'])) {
            $email_id = $webhook_data['object']['id'];
        } elseif (isset($webhook_data['object']['email']['id'])) {
            $email_id = $webhook_data['object']['email']['id'];
        } elseif (isset($webhook_data['email_id'])) {
            $email_id = $webhook_data['email_id'];
        }

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

        // Check if this is a test webhook
        $is_test_webhook = (isset($webhook_data['test']) && $webhook_data['test'] === true) || 
                          (isset($webhook_data['event_id']) && strpos($webhook_data['event_id'], 'WEBHOOKTEST_') === 0);
        
        if ($is_test_webhook) {
            $this->logger->log('TEST WEBHOOK RECEIVED - This is a test webhook from the admin interface', Emailit_Logger::LEVEL_INFO, array(
                'event_id' => $event_id,
                'event_type' => $event_type,
                'email_id' => $email_id,
                'test_data' => $webhook_data
            ));
        }

        // Log webhook event - format data properly for logging
        $formatted_webhook_data = array(
            'request_id' => isset($webhook_data['request_id']) ? $webhook_data['request_id'] : 
                           (isset($webhook_data['id']) ? $webhook_data['id'] : null),
            'event_id' => $event_id, // Use the event_id from EmailIt format
            'event_type' => $event_type,
            'status' => 'processed', // Webhook was successfully processed
            'details' => array(
                // Extract email details from EmailIt's object structure
                'from_email' => isset($webhook_data['object']['email']['from']) ? $webhook_data['object']['email']['from'] : 
                               (isset($webhook_data['object']['from']) ? $webhook_data['object']['from'] : 
                               (isset($webhook_data['from_email']) ? $webhook_data['from_email'] : null)),
                'to_email' => isset($webhook_data['object']['email']['to']) ? $webhook_data['object']['email']['to'] : 
                             (isset($webhook_data['object']['to']) ? $webhook_data['object']['to'] : 
                             (isset($webhook_data['to_email']) ? $webhook_data['to_email'] : null)),
                'subject' => isset($webhook_data['object']['email']['subject']) ? $webhook_data['object']['email']['subject'] : 
                            (isset($webhook_data['object']['subject']) ? $webhook_data['object']['subject'] : 
                            (isset($webhook_data['subject']) ? $webhook_data['subject'] : null)),
                'timestamp' => isset($webhook_data['timestamp']) ? $webhook_data['timestamp'] : 
                              (isset($webhook_data['created_at']) ? $webhook_data['created_at'] : 
                              (isset($webhook_data['object']['created_at']) ? $webhook_data['object']['created_at'] : current_time('mysql'))),
                'bounce_reason' => isset($webhook_data['object']['bounce_reason']) ? $webhook_data['object']['bounce_reason'] : 
                                  (isset($webhook_data['bounce_reason']) ? $webhook_data['bounce_reason'] : null),
                'complaint_reason' => isset($webhook_data['object']['complaint_reason']) ? $webhook_data['object']['complaint_reason'] : 
                                     (isset($webhook_data['complaint_reason']) ? $webhook_data['complaint_reason'] : null),
                'failure_reason' => isset($webhook_data['object']['failure_reason']) ? $webhook_data['object']['failure_reason'] : 
                                   (isset($webhook_data['failure_reason']) ? $webhook_data['failure_reason'] : null),
                'raw_webhook' => $webhook_data
            )
        );
        $webhook_log_id = $this->logger->log_webhook($formatted_webhook_data, $email_id);

        // Map event type to email status using actual Emailit status codes
        $status_mapping = array(
            // Official Emailit status codes from documentation
            'email.delivery.sent' => Emailit_Logger::STATUS_SENT,
            'email.delivery.delivered' => Emailit_Logger::STATUS_DELIVERED,
            'email.delivery.bounced' => Emailit_Logger::STATUS_BOUNCED,
            'email.delivery.complained' => Emailit_Logger::STATUS_COMPLAINED,
            'email.delivery.failed' => Emailit_Logger::STATUS_FAILED,
            'email.delivery.hardfail' => Emailit_Logger::STATUS_FAILED,
            'email.delivery.softfail' => Emailit_Logger::STATUS_FAILED,
            'email.delivery.error' => Emailit_Logger::STATUS_FAILED,
            'email.delivery.held' => Emailit_Logger::STATUS_HELD,
            'email.delivery.delayed' => Emailit_Logger::STATUS_DELAYED,
            'email.delivery.opened' => Emailit_Logger::STATUS_OPENED,
            'email.delivery.clicked' => Emailit_Logger::STATUS_CLICKED,
            'email.delivery.unsubscribed' => Emailit_Logger::STATUS_UNSUBSCRIBED,

            // Legacy/backwards compatibility
            'email.sent' => Emailit_Logger::STATUS_SENT,
            'email.delivered' => Emailit_Logger::STATUS_DELIVERED,
            'email.bounced' => Emailit_Logger::STATUS_BOUNCED,
            'email.complained' => Emailit_Logger::STATUS_COMPLAINED,
            'email.failed' => Emailit_Logger::STATUS_FAILED,
            'email.opened' => Emailit_Logger::STATUS_OPENED,
            'email.clicked' => Emailit_Logger::STATUS_CLICKED,
            'email.unsubscribed' => Emailit_Logger::STATUS_UNSUBSCRIBED
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

            // Classify bounce events for general email deliverability insights
            if (in_array($event_type, array('email.delivery.hardfail', 'email.delivery.softfail', 'email.delivery.bounce', 'email.complained', 'email.unsubscribed'))) {
                $bounce_classification = $this->bounce_classifier->classify_bounce($webhook_data);
                
                // Add classification data to details
                $details['bounce_classification'] = $bounce_classification['classification'];
                $details['bounce_category'] = $bounce_classification['category'];
                $details['bounce_severity'] = $bounce_classification['severity'];
                $details['bounce_confidence'] = $bounce_classification['confidence'];
                $details['bounce_recommended_action'] = $bounce_classification['recommended_action'];
                
                if (!empty($bounce_classification['technical_hints'])) {
                    $details['bounce_technical_hints'] = $bounce_classification['technical_hints'];
                }
            }

            // Update email status (convert details array to JSON string)
            $details_json = !empty($details) ? wp_json_encode($details) : null;
            $updated = $this->logger->update_email_status($email_id, $new_status, $details_json);

            if (!$updated) {
                $this->logger->log('Failed to update email status from webhook - email not found in database', Emailit_Logger::LEVEL_WARNING, array(
                    'email_id' => $email_id,
                    'event_type' => $event_type,
                    'token' => isset($webhook_data['object']['email']['token']) ? $webhook_data['object']['email']['token'] : 'not_found'
                ));
            }

            // Trigger status-specific action hooks
            do_action('emailit_status_updated', $email_id, $new_status, $details);
            do_action("emailit_email_{$new_status}", $email_id, $webhook_data, $details);

            // Forward bounce/complaint events to FluentCRM if integration is enabled
            if (in_array($event_type, array('email.delivery.bounced', 'email.delivery.complained', 'email.delivery.hardfail', 'email.delivery.softfail', 'email.bounced', 'email.complained'))) {
                $this->forward_bounce_to_fluentcrm($email_id, $new_status, $details, $webhook_data);
            }
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
        // Create test data in EmailIt's official webhook format
        $test_data = array(
            'event_id' => 'WEBHOOKTEST_' . time(), // Clearly identifiable test event ID
            'type' => 'email.delivery.delivered',
            'object' => array(
                'id' => 'TEST_EMAIL_' . time(),
                'email' => array(
                    'from' => 'test@' . parse_url(get_site_url(), PHP_URL_HOST),
                    'to' => 'test-recipient@example.com',
                    'subject' => 'EmailIt Webhook Test - ' . current_time('Y-m-d H:i:s'),
                    'created_at' => current_time('c')
                )
            ),
            'timestamp' => current_time('c'),
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
                // Official EmailIt event types from documentation
                'email.delivery.sent',
                'email.delivery.delivered',
                'email.delivery.bounced',
                'email.delivery.complained',
                'email.delivery.failed',
                'email.delivery.hardfail',
                'email.delivery.softfail',
                'email.delivery.error',
                'email.delivery.held',
                'email.delivery.delayed',
                'email.delivery.opened',
                'email.delivery.clicked',
                'email.delivery.unsubscribed'
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
    public function update_webhook_secret(?string $secret = null) {
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

        // Extract from_email from Emailit's format
        $from_email = null;
        if (isset($webhook_data['object']['email']['from'])) {
            $from_email = $webhook_data['object']['email']['from'];
        } elseif (isset($webhook_data['from_email'])) {
            $from_email = $webhook_data['from_email'];
        }

        // Extract email_id from Emailit's format
        $email_id = null;
        if (isset($webhook_data['object']['email']['id'])) {
            $email_id = $webhook_data['object']['email']['id'];
        } elseif (isset($webhook_data['email_id'])) {
            $email_id = $webhook_data['email_id'];
        }

        // 1. Check from_email domain
        if ($from_email) {
            $from_domain = $this->extract_domain_from_email($from_email);
            $identifiers[] = $from_domain;

            // Debug logging for domain extraction (only when WP_DEBUG is enabled)
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $this->logger->log('Extracting domain from from_email', Emailit_Logger::LEVEL_DEBUG, array(
                    'original_from_email' => $from_email,
                    'extracted_domain' => $from_domain,
                    'site_domain' => $site_domain
                ));
            }
        }

        // 2. Check if we have the email_id in our logs (most reliable)
        if ($email_id) {
            if ($this->email_exists_in_logs($email_id)) {
                return true;
            }
        }

        // 3. Check against configured from_email addresses
        $configured_emails = $this->get_configured_from_emails();
        if ($from_email) {
            if (in_array(strtolower($from_email), array_map('strtolower', $configured_emails))) {
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
        // Handle RFC format like "Name <email@domain.com>" or just "email@domain.com"
        $email = trim($email);

        // Extract email from RFC format (Name <email@domain.com>)
        if (preg_match('/<([^>]+)>/', $email, $matches)) {
            $email = $matches[1];
        }

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

    /**
     * Get FluentCRM integration status
     */
    public function get_fluentcrm_integration_status() {
        $status = array(
            'available' => false,
            'version' => null,
            'active' => false
        );

        if (class_exists('FluentCrm\App\App')) {
            $status['available'] = true;
            $status['active'] = true;
            
            // Try to get version using multiple methods
            $version = null;
            
            // Method 1: Check for FLUENTCRM_PLUGIN_VERSION constant
            if (defined('FLUENTCRM_PLUGIN_VERSION')) {
                $version = FLUENTCRM_PLUGIN_VERSION;
            }
            // Method 2: Check for FLUENTCRM_VERSION constant
            elseif (defined('FLUENTCRM_VERSION')) {
                $version = FLUENTCRM_VERSION;
            }
            // Method 3: Try to get version from FluentCRM app
            elseif (function_exists('fluentCrm')) {
                try {
                    $app = fluentCrm();
                    if (is_object($app) && method_exists($app, 'getVersion')) {
                        $version = $app->getVersion();
                    }
                } catch (Exception $e) {
                    // Ignore errors and try next method
                }
            }
            // Method 4: Try to get version from plugin data
            if (!$version && function_exists('get_plugin_data')) {
                $plugin_file = WP_PLUGIN_DIR . '/fluent-crm/fluent-crm.php';
                if (file_exists($plugin_file)) {
                    $plugin_data = get_plugin_data($plugin_file);
                    if (isset($plugin_data['Version'])) {
                        $version = $plugin_data['Version'];
                    }
                }
            }
            
            $status['version'] = $version ?: 'Unknown';
        }

        return $status;
    }

    /**
     * Initialize FluentCRM integration
     * Detects FluentCRM and sets up bounce handling integration
     */
    private function init_fluentcrm_integration() {
        // Check if FluentCRM is active and available
        if (!$this->is_fluentcrm_available()) {
            return;
        }

        $this->logger->log('FluentCRM detected - initializing bounce integration', Emailit_Logger::LEVEL_INFO, array(
            'fluentcrm_version' => $this->get_fluentcrm_version(),
            'integration_enabled' => get_option('emailit_fluentcrm_integration', 1)
        ));

        // Only integrate if enabled in settings
        if (!get_option('emailit_fluentcrm_integration', 1)) {
            $this->logger->log('FluentCRM integration disabled in settings', Emailit_Logger::LEVEL_INFO);
            return;
        }

        // Add our custom bounce handler to FluentCRM's actual bounce action
        add_action('fluentcrm_subscriber_status_to_bounced', array($this, 'handle_fluentcrm_subscriber_bounced'), 10, 2);
        add_action('fluentcrm_subscriber_status_to_complained', array($this, 'handle_fluentcrm_subscriber_complained'), 10, 2);

        // Also hook into general status changes for comprehensive tracking
        add_action('fluent_crm/subscriber_status_changed', array($this, 'handle_fluentcrm_status_change'), 10, 3);

        $this->logger->log('FluentCRM bounce integration initialized successfully', Emailit_Logger::LEVEL_INFO);
    }

    /**
     * Check if FluentCRM is available and active
     */
    public function is_fluentcrm_available() {
        // Check if FluentCRM plugin is active
        if (!function_exists('fluentCrm') && !defined('FLUENTCRM')) {
            return false;
        }

        // Check if FluentCRM Subscriber model is available
        if (!class_exists('FluentCrm\\App\\Models\\Subscriber')) {
            return false;
        }

        // Check if the helper functions exist
        if (!function_exists('fluentcrm_get_subscriber_meta')) {
            return false;
        }

        return true;
    }

    /**
     * Get FluentCRM version
     */
    private function get_fluentcrm_version() {
        // Method 1: Check for FLUENTCRM_PLUGIN_VERSION constant
        if (defined('FLUENTCRM_PLUGIN_VERSION')) {
            return FLUENTCRM_PLUGIN_VERSION;
        }

        // Method 2: Check for FLUENTCRM_VERSION constant
        if (defined('FLUENTCRM_VERSION')) {
            return FLUENTCRM_VERSION;
        }

        // Method 3: Try to get version from FluentCRM app
        if (function_exists('fluentCrm')) {
            try {
                $app = fluentCrm();
                if (is_object($app) && method_exists($app, 'getVersion')) {
                    return $app->getVersion();
                }
            } catch (Exception $e) {
                // Ignore errors and try next method
            }
        }

        // Method 4: Try to get version from plugin data
        if (function_exists('get_plugin_data')) {
            $plugin_file = WP_PLUGIN_DIR . '/fluent-crm/fluent-crm.php';
            if (file_exists($plugin_file)) {
                $plugin_data = get_plugin_data($plugin_file);
                if (isset($plugin_data['Version'])) {
                    return $plugin_data['Version'];
                }
            }
        }

        return 'unknown';
    }

    /**
     * Handle FluentCRM subscriber bounce events
     * This is called when a FluentCRM subscriber status changes to 'bounced'
     *
     * @param \FluentCrm\App\Models\Subscriber $subscriber The FluentCRM subscriber instance
     * @param string $oldStatus The previous status
     */
    public function handle_fluentcrm_subscriber_bounced($subscriber, $oldStatus) {
        try {
            // Get bounce reason from meta if available
            $bounceReason = fluentcrm_get_subscriber_meta($subscriber->id, 'reason', 'FluentCRM bounce detected');

            $this->logger->log('FluentCRM subscriber bounced', Emailit_Logger::LEVEL_INFO, array(
                'subscriber_id' => $subscriber->id,
                'subscriber_email' => $subscriber->email,
                'old_status' => $oldStatus,
                'new_status' => 'bounced',
                'bounce_reason' => $bounceReason
            ));

            // Create bounce data structure for consistency with our existing methods
            $bounceData = array(
                'reason' => $bounceReason,
                'code' => 'unknown',
                'type' => 'hard', // FluentCRM typically marks hard bounces
                'source' => 'fluentcrm'
            );

            // Forward bounce data to Emailit if integration is enabled
            if (get_option('emailit_fluentcrm_forward_bounces', 1)) {
                $this->forward_subscriber_bounce_to_emailit($subscriber, $bounceData);
            }

            // Update Emailit logs if we can find the corresponding email
            $this->sync_subscriber_bounce_to_emailit_logs($subscriber, $bounceData);

            // Handle bounce type-specific actions
            $this->handle_subscriber_bounce_by_type($subscriber, $bounceData);

            // Fire our own action for extensibility
            do_action('emailit_fluentcrm_subscriber_bounced', $subscriber, $oldStatus, $bounceData, $this);

        } catch (Exception $e) {
            $this->logger->log('Error processing FluentCRM subscriber bounce', Emailit_Logger::LEVEL_ERROR, array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'subscriber_id' => $subscriber->id ?? 'unknown'
            ));
        }
    }

    /**
     * Handle FluentCRM subscriber complaint events
     * This is called when a FluentCRM subscriber status changes to 'complained'
     *
     * @param \FluentCrm\App\Models\Subscriber $subscriber The FluentCRM subscriber instance
     * @param string $oldStatus The previous status
     */
    public function handle_fluentcrm_subscriber_complained($subscriber, $oldStatus) {
        try {
            // Get complaint reason from meta if available
            $complaintReason = fluentcrm_get_subscriber_meta($subscriber->id, 'reason', 'FluentCRM complaint detected');

            $this->logger->log('FluentCRM subscriber complained', Emailit_Logger::LEVEL_INFO, array(
                'subscriber_id' => $subscriber->id,
                'subscriber_email' => $subscriber->email,
                'old_status' => $oldStatus,
                'new_status' => 'complained',
                'complaint_reason' => $complaintReason
            ));

            // Create bounce data structure for consistency
            $bounceData = array(
                'reason' => $complaintReason,
                'code' => 'complaint',
                'type' => 'complaint',
                'source' => 'fluentcrm'
            );

            // Forward complaint data to Emailit if integration is enabled
            if (get_option('emailit_fluentcrm_forward_bounces', 1)) {
                $this->forward_subscriber_bounce_to_emailit($subscriber, $bounceData);
            }

            // Update Emailit logs
            $this->sync_subscriber_bounce_to_emailit_logs($subscriber, $bounceData);

            // Handle complaint type-specific actions
            $this->handle_subscriber_bounce_by_type($subscriber, $bounceData);

            // Fire our own action for extensibility
            do_action('emailit_fluentcrm_subscriber_complained', $subscriber, $oldStatus, $bounceData, $this);

        } catch (Exception $e) {
            $this->logger->log('Error processing FluentCRM subscriber complaint', Emailit_Logger::LEVEL_ERROR, array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'subscriber_id' => $subscriber->id ?? 'unknown'
            ));
        }
    }

    /**
     * Handle general FluentCRM subscriber status changes
     * This provides comprehensive tracking of all status changes
     *
     * @param \FluentCrm\App\Models\Subscriber $subscriber The FluentCRM subscriber instance
     * @param string $oldStatus The previous status
     * @param string $newStatus The new status
     */
    public function handle_fluentcrm_status_change($subscriber, $oldStatus, $newStatus) {
        // Only log status changes relevant to email deliverability
        $relevantStatuses = ['bounced', 'complained', 'unsubscribed', 'spammed'];

        if (!in_array($newStatus, $relevantStatuses)) {
            return;
        }

        $this->logger->log('FluentCRM subscriber status changed', Emailit_Logger::LEVEL_INFO, array(
            'subscriber_id' => $subscriber->id,
            'subscriber_email' => $subscriber->email,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'timestamp' => current_time('mysql')
        ));

        // Fire general status change action for extensibility
        do_action('emailit_fluentcrm_status_changed', $subscriber, $oldStatus, $newStatus, $this);
    }

    /**
     * Forward bounce from Emailit to FluentCRM
     */
    private function forward_bounce_to_fluentcrm($email_id, $status, $details, $webhook_data) {
        // Check if FluentCRM integration is enabled
        if (!get_option('emailit_fluentcrm_integration', 1)) {
            return;
        }

        // Check if FluentCRM is available
        if (!class_exists('FluentCrm\App\Models\Subscriber')) {
            return;
        }

        // Get the email address from the webhook data
        $email_address = $this->extract_email_address_from_webhook($webhook_data);
        if (!$email_address) {
            $this->logger->log('Cannot forward bounce to FluentCRM - no email address found', Emailit_Logger::LEVEL_WARNING);
            return;
        }

        // Find the FluentCRM subscriber
        $subscriber = \FluentCrm\App\Models\Subscriber::where('email', $email_address)->first();
        if (!$subscriber) {
            $this->logger->log('Cannot forward bounce to FluentCRM - subscriber not found', Emailit_Logger::LEVEL_WARNING);
            return;
        }

        // Determine the bounce type and reason
        $bounce_type = $this->determine_bounce_type_from_status($status, $details);
        $bounce_reason = $this->extract_bounce_reason($details, $webhook_data);

        // Update FluentCRM subscriber status
        $this->update_fluentcrm_subscriber_status($subscriber, $bounce_type, $bounce_reason, $details);

        $this->logger->log('Bounce forwarded to FluentCRM', Emailit_Logger::LEVEL_INFO);
    }

    /**
     * Extract email address from webhook data
     */
    private function extract_email_address_from_webhook($webhook_data) {
        // Try different possible locations for the email address
        $possible_locations = array(
            'object.email.to',
            'object.to',
            'to_email',
            'recipient_email'
        );

        foreach ($possible_locations as $location) {
            $value = $this->get_nested_value($webhook_data, $location);
            if ($value && is_email($value)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Get nested value from array using dot notation
     */
    private function get_nested_value($array, $key) {
        $keys = explode('.', $key);
        $value = $array;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return null;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Determine bounce type from status and details
     */
    private function determine_bounce_type_from_status($status, $details) {
        // Check if it's a complaint
        if (strpos($status, 'complained') !== false || isset($details['complaint_reason'])) {
            return 'complaint';
        }

        // Check if it's a hard bounce
        if (isset($details['bounce_classification']) && $details['bounce_classification'] === 'hard_bounce') {
            return 'hard';
        }

        // Check if it's a soft bounce
        if (isset($details['bounce_classification']) && $details['bounce_classification'] === 'soft_bounce') {
            return 'soft';
        }

        // Default to hard bounce for bounced status
        if (strpos($status, 'bounced') !== false) {
            return 'hard';
        }

        return 'hard'; // Default fallback
    }

    /**
     * Extract bounce reason from details and webhook data
     */
    private function extract_bounce_reason($details, $webhook_data) {
        // Try to get bounce reason from various sources
        $possible_reasons = array(
            $details['bounce_reason'] ?? null,
            $details['complaint_reason'] ?? null,
            $details['failure_reason'] ?? null,
            $webhook_data['bounce_reason'] ?? null,
            $webhook_data['complaint_reason'] ?? null,
            $webhook_data['failure_reason'] ?? null
        );

        foreach ($possible_reasons as $reason) {
            if (!empty($reason)) {
                return $reason;
            }
        }

        return 'Email bounced - reason not specified';
    }

    /**
     * Update FluentCRM subscriber status based on bounce type
     */
    private function update_fluentcrm_subscriber_status($subscriber, $bounce_type, $bounce_reason, $details) {
        $old_status = $subscriber->status;

        switch ($bounce_type) {
            case 'complaint':
                // Update to complained status
                $subscriber->status = 'complained';
                $subscriber->save();
                
                // Add complaint reason to meta
                fluentcrm_update_subscriber_meta($subscriber->id, 'reason', $bounce_reason);
                fluentcrm_update_subscriber_meta($subscriber->id, 'emailit_complaint_reason', $bounce_reason);
                fluentcrm_update_subscriber_meta($subscriber->id, 'emailit_complaint_date', current_time('mysql'));
                break;

            case 'hard':
                // Update to bounced status
                $subscriber->status = 'bounced';
                $subscriber->save();
                
                // Add bounce reason to meta
                fluentcrm_update_subscriber_meta($subscriber->id, 'reason', $bounce_reason);
                fluentcrm_update_subscriber_meta($subscriber->id, 'emailit_bounce_reason', $bounce_reason);
                fluentcrm_update_subscriber_meta($subscriber->id, 'emailit_bounce_date', current_time('mysql'));
                fluentcrm_update_subscriber_meta($subscriber->id, 'emailit_bounce_type', 'hard');
                break;

            case 'soft':
                // Track soft bounce count
                $current_count = fluentcrm_get_subscriber_meta($subscriber->id, 'emailit_soft_bounce_count', 0);
                $new_count = intval($current_count) + 1;
                
                fluentcrm_update_subscriber_meta($subscriber->id, 'emailit_soft_bounce_count', $new_count);
                fluentcrm_update_subscriber_meta($subscriber->id, 'emailit_last_soft_bounce', current_time('mysql'));
                fluentcrm_update_subscriber_meta($subscriber->id, 'emailit_soft_bounce_reason', $bounce_reason);
                fluentcrm_update_subscriber_meta($subscriber->id, 'emailit_bounce_type', 'soft');
                
                // Check if we should escalate to hard bounce
                $threshold = get_option('emailit_fluentcrm_soft_bounce_threshold', 5);
                if ($new_count >= $threshold) {
                    $subscriber->status = 'bounced';
                    $subscriber->save();
                    fluentcrm_update_subscriber_meta($subscriber->id, 'reason', 'Soft bounce threshold reached (' . $new_count . ' bounces)');
                }
                break;
        }

        // Fire FluentCRM status change action
        do_action('fluent_crm/subscriber_status_changed', $subscriber, $old_status, $subscriber->status);
    }

    /**
     * Forward subscriber bounce data to Emailit API
     * This helps keep Emailit's bounce tracking in sync
     */
    private function forward_subscriber_bounce_to_emailit($subscriber, $bounceData) {
        // Get Emailit API instance
        $api = emailit_get_component('api');
        if (!$api) {
            $this->logger->log('Cannot forward bounce to Emailit - API component not available', Emailit_Logger::LEVEL_WARNING);
            return;
        }

        // Prepare bounce notification data
        $notification_data = array(
            'event_type' => $bounceData['type'] ?? 'bounce',
            'contact_email' => $subscriber->email,
            'bounce_type' => $bounceData['type'],
            'bounce_reason' => $bounceData['reason'],
            'bounce_code' => $bounceData['code'],
            'source' => 'fluentcrm',
            'timestamp' => current_time('c'),
            'fluentcrm_subscriber_id' => $subscriber->id,
            'fluentcrm_subscriber_status' => $subscriber->status
        );

        // Apply filters to allow customization
        $notification_data = apply_filters('emailit_fluentcrm_bounce_notification_data', $notification_data, $subscriber, $bounceData);

        $this->logger->log('Forwarding subscriber bounce to Emailit API', Emailit_Logger::LEVEL_INFO, array(
            'notification_data' => $notification_data
        ));

        // Send to Emailit (this would require an API endpoint for bounce notifications)
        // For now, we'll just log it - in a real implementation, you'd call an Emailit API endpoint
        $this->logger->log('Subscriber bounce forwarded to Emailit API', Emailit_Logger::LEVEL_INFO, array(
            'forward_data' => $notification_data
        ));
    }

    /**
     * Sync subscriber bounce information to Emailit logs
     */
    private function sync_subscriber_bounce_to_emailit_logs($subscriber, $bounceData) {
        global $wpdb;

        $logs_table = $wpdb->prefix . 'emailit_logs';
        $contact_email = $subscriber->email;

        if (empty($contact_email)) {
            return;
        }

        // Try to find corresponding Emailit log entries
        $emailit_logs = $wpdb->get_results($wpdb->prepare(
            "SELECT id, email_id, token FROM {$logs_table}
             WHERE to_email LIKE %s
             AND status IN ('sent', 'delivered', 'pending')
             ORDER BY created_at DESC LIMIT 5",
            '%' . $wpdb->esc_like($contact_email) . '%'
        ));

        foreach ($emailit_logs as $log) {
            // Determine the new status based on bounce type
            $new_status = 'bounced';
            if ($bounceData['type'] === 'complaint') {
                $new_status = 'complained';
            }

            // Update status to bounced/complained
            $updated = $wpdb->update(
                $logs_table,
                array(
                    'status' => $new_status,
                    'details' => wp_json_encode(array(
                        'bounce_source' => 'fluentcrm',
                        'bounce_reason' => $bounceData['reason'],
                        'bounce_code' => $bounceData['code'],
                        'bounce_type' => $bounceData['type'],
                        'fluentcrm_subscriber_id' => $subscriber->id,
                        'fluentcrm_subscriber_status' => $subscriber->status,
                        'synced_at' => current_time('mysql')
                    )),
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $log->id),
                array('%s', '%s', '%s'),
                array('%d')
            );

            if ($updated) {
                $this->logger->log('Synced subscriber bounce status to Emailit log', Emailit_Logger::LEVEL_INFO, array(
                    'emailit_log_id' => $log->id,
                    'email_id' => $log->email_id,
                    'contact_email' => $contact_email,
                    'new_status' => $new_status,
                    'bounce_reason' => $bounceData['reason']
                ));
            }
        }
    }

    /**
     * Handle different types of bounces for FluentCRM subscribers
     * Note: FluentCRM typically handles hard bounces automatically,
     * but we can track additional metrics here
     */
    private function handle_subscriber_bounce_by_type($subscriber, $bounceData) {
        $bounce_type = $bounceData['type'];

        switch ($bounce_type) {
            case 'hard':
                // Hard bounces - FluentCRM already set status to 'bounced'
                $this->logger->log('Hard bounce processed for subscriber', Emailit_Logger::LEVEL_INFO, array(
                    'subscriber_id' => $subscriber->id,
                    'subscriber_email' => $subscriber->email,
                    'bounce_type' => $bounce_type
                ));
                break;

            case 'soft':
                // Soft bounces - track count for potential escalation
                if (get_option('emailit_fluentcrm_soft_bounce_action', 'track') === 'track') {
                    $this->track_soft_bounce_subscriber($subscriber, $bounceData);
                }
                break;

            case 'complaint':
                // Spam complaints - FluentCRM already set status to 'complained'
                $this->logger->log('Complaint processed for subscriber', Emailit_Logger::LEVEL_INFO, array(
                    'subscriber_id' => $subscriber->id,
                    'subscriber_email' => $subscriber->email,
                    'bounce_type' => $bounce_type
                ));
                break;
        }

        // Fire type-specific action
        do_action("emailit_fluentcrm_subscriber_{$bounce_type}", $subscriber, $bounceData, $this);
    }

    /**
     * Classify bounce type based on bounce data
     */
    private function classify_bounce_type($bounceData) {
        $reason = strtolower($bounceData['reason'] ?? '');
        $code = $bounceData['code'] ?? '';

        // Hard bounce indicators
        $hard_bounce_patterns = array(
            'user unknown',
            'no such user',
            'invalid recipient',
            'recipient address rejected',
            'user not found',
            'mailbox unavailable',
            'account disabled'
        );

        // Soft bounce indicators
        $soft_bounce_patterns = array(
            'mailbox full',
            'message too large',
            'temporary failure',
            'try again later',
            'server busy'
        );

        // Spam complaint indicators
        $complaint_patterns = array(
            'spam',
            'complaint',
            'abuse',
            'block',
            'blacklist'
        );

        foreach ($complaint_patterns as $pattern) {
            if (strpos($reason, $pattern) !== false) {
                return 'complaint';
            }
        }

        foreach ($hard_bounce_patterns as $pattern) {
            if (strpos($reason, $pattern) !== false) {
                return 'hard';
            }
        }

        foreach ($soft_bounce_patterns as $pattern) {
            if (strpos($reason, $pattern) !== false) {
                return 'soft';
            }
        }

        // Check SMTP codes
        if (preg_match('/^5\d\d$/', $code)) {
            return 'hard'; // 5xx codes are permanent failures
        }

        if (preg_match('/^4\d\d$/', $code)) {
            return 'soft'; // 4xx codes are temporary failures
        }

        return 'unknown';
    }


    /**
     * Track soft bounce for FluentCRM subscriber
     */
    private function track_soft_bounce_subscriber($subscriber, $bounceData) {
        if (!$subscriber || !isset($subscriber->id)) {
            return;
        }

        try {
            // Increment soft bounce counter using FluentCRM meta system
            $subscriber_id = $subscriber->id;
            $current_count = fluentcrm_get_subscriber_meta($subscriber_id, 'emailit_soft_bounce_count', 0);
            $new_count = intval($current_count) + 1;

            fluentcrm_update_subscriber_meta($subscriber_id, 'emailit_soft_bounce_count', $new_count);
            fluentcrm_update_subscriber_meta($subscriber_id, 'emailit_last_soft_bounce', current_time('mysql'));

            // Mark as bounced if soft bounce threshold is reached
            $threshold = get_option('emailit_fluentcrm_soft_bounce_threshold', 5);
            if ($new_count >= $threshold) {
                // Update subscriber status to bounced
                $subscriber->updateStatus('bounced');
                fluentcrm_update_subscriber_meta($subscriber_id, 'reason', 'Soft bounce threshold reached (' . $new_count . ' bounces)');

                $this->logger->log('Subscriber marked as bounced - soft bounce threshold reached', Emailit_Logger::LEVEL_INFO, array(
                    'subscriber_id' => $subscriber_id,
                    'bounce_count' => $new_count,
                    'threshold' => $threshold
                ));
            } else {
                $this->logger->log('Soft bounce tracked for subscriber', Emailit_Logger::LEVEL_INFO, array(
                    'subscriber_id' => $subscriber_id,
                    'bounce_count' => $new_count,
                    'threshold' => $threshold
                ));
            }
        } catch (Exception $e) {
            $this->logger->log('Error tracking soft bounce for subscriber', Emailit_Logger::LEVEL_ERROR, array(
                'error' => $e->getMessage(),
                'subscriber_id' => $subscriber->id ?? 'unknown'
            ));
        }
    }

}