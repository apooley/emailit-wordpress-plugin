<?php
/**
 * Emailit Mailer Class
 *
 * Replaces WordPress wp_mail() function with Emailit API integration.
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

class Emailit_Mailer {

    /**
     * API handler instance
     */
    private $api;

    /**
     * Logger instance
     */
    private $logger;

    /**
     * Queue instance
     */
    private $queue;

    /**
     * Fallback enabled
     */
    private $fallback_enabled;

    /**
     * Queue enabled
     */
    private $queue_enabled;

    /**
     * Constructor
     */
    public function __construct($api, $logger, $queue = null) {
        $this->api = $api;
        $this->logger = $logger;
        $this->queue = $queue;
        $this->fallback_enabled = (bool) get_option('emailit_fallback_enabled', 1);
        $this->queue_enabled = (bool) get_option('emailit_enable_queue', 0);

        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Use pre_wp_mail filter for WordPress 5.7+ for complete override
        if (version_compare(get_bloginfo('version'), '5.7', '>=')) {
            add_filter('pre_wp_mail', array($this, 'pre_wp_mail_handler'), 10, 2);
        } else {
            // Fallback to phpmailer_init for older versions
            add_action('phpmailer_init', array($this, 'phpmailer_init_handler'));
        }

        // Note: should_send_via_emailit is used internally, not as a hook
    }

    /**
     * Handle pre_wp_mail filter (WordPress 5.7+)
     */
    public function pre_wp_mail_handler($null, $atts) {
        try {
            // Debug logging (only when WP_DEBUG is enabled)
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $this->logger->log('Emailit attempting to send email via pre_wp_mail', Emailit_Logger::LEVEL_DEBUG, array(
                    'to' => isset($atts['to']) ? $atts['to'] : 'not_set',
                    'subject' => isset($atts['subject']) ? $atts['subject'] : 'not_set'
                ));
            }

            // Extract wp_mail arguments
            $to = $atts['to'];
            $subject = $atts['subject'];
            $message = $atts['message'];
            $headers = isset($atts['headers']) ? $atts['headers'] : '';
            $attachments = isset($atts['attachments']) ? $atts['attachments'] : array();

            // Send via Emailit
            $result = $this->send($to, $subject, $message, $headers, $attachments);

            // Debug logging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $this->logger->log('Emailit send result', Emailit_Logger::LEVEL_DEBUG, array(
                    'success' => !is_wp_error($result),
                    'error' => is_wp_error($result) ? $result->get_error_message() : null
                ));
            }

            // Return result to bypass wp_mail
            return $result;
        } catch (Exception $e) {
            // Always log critical errors
            $this->logger->log('CRITICAL: Exception in pre_wp_mail_handler', Emailit_Logger::LEVEL_ERROR, array(
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => defined('WP_DEBUG') && WP_DEBUG ? $e->getTraceAsString() : 'Debug disabled'
            ));

            // Fall back to WordPress default
            return null;
        }
    }

    /**
     * Handle phpmailer_init action (fallback for older WordPress)
     */
    public function phpmailer_init_handler(&$phpmailer) {
        // Store original wp_mail data
        $email_data = array(
            'to' => $phpmailer->getToAddresses(),
            'subject' => $phpmailer->Subject,
            'message' => $phpmailer->Body,
            'headers' => $this->extract_headers_from_phpmailer($phpmailer),
            'attachments' => $this->extract_attachments_from_phpmailer($phpmailer),
            'content_type' => $phpmailer->isHTML() ? 'text/html' : 'text/plain'
        );

        // Check if we should send via Emailit
        if (!$this->should_send_via_emailit($email_data, $phpmailer)) {
            return; // Let WordPress handle normally
        }

        // Clear PHPMailer settings to prevent normal sending
        $phpmailer->clearAllRecipients();
        $phpmailer->clearAttachments();
        $phpmailer->clearCustomHeaders();

        // Send via Emailit API
        $result = $this->send_email_data($email_data);

        // Set result status
        if (is_wp_error($result)) {
            $phpmailer->ErrorInfo = $result->get_error_message();
        }
    }

    /**
     * Main send method (public interface)
     */
    public function send($to, $subject, $message, $headers = '', $attachments = array()) {
        try {
            // Debug logging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $this->logger->log('Emailit send method called', Emailit_Logger::LEVEL_DEBUG, array(
                    'to' => $to,
                    'subject' => $subject
                ));
            }

            // Prepare email data
            $email_data = $this->prepare_email_data($to, $subject, $message, $headers, $attachments);

            // Apply filter to allow modification
            $email_data = apply_filters('emailit_email_data', $email_data, $to, $subject, $message, $headers, $attachments);

            // Check if we should send via Emailit
            if (!$this->should_send_via_emailit($email_data)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    $this->logger->log('Falling back to wp_mail', Emailit_Logger::LEVEL_DEBUG);
                }
                return $this->fallback_to_wp_mail($to, $subject, $message, $headers, $attachments);
            }

            // Debug logging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $this->logger->log('Sending via Emailit API', Emailit_Logger::LEVEL_DEBUG);
            }

            return $this->send_email_data($email_data);
        } catch (Exception $e) {
            // Always log critical errors
            $this->logger->log('CRITICAL: Exception in send method', Emailit_Logger::LEVEL_ERROR, array(
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => defined('WP_DEBUG') && WP_DEBUG ? $e->getTraceAsString() : 'Debug disabled'
            ));

            // Fall back to WordPress default
            return $this->fallback_to_wp_mail($to, $subject, $message, $headers, $attachments);
        }
    }

    /**
     * Send email data via API
     */
    private function send_email_data($email_data) {
        // Check if queue is enabled and should be used
        if ($this->should_use_queue($email_data)) {
            return $this->queue_email($email_data);
        }

        // Trigger before send action
        do_action('emailit_before_send', $email_data);

        // Send via API
        $api_response = $this->api->send_email($email_data);

        // Determine status based on response and webhook settings
        if (is_wp_error($api_response)) {
            $status = Emailit_Logger::STATUS_FAILED;
        } else {
            // Use different status based on webhook settings
            $webhooks_enabled = get_option('emailit_enable_webhooks', 1);
            $status = $webhooks_enabled ? Emailit_Logger::STATUS_SENT : Emailit_Logger::STATUS_SENT_TO_API;
        }

        // Log the email
        $log_id = $this->logger->log_email($email_data, $api_response, $status);

        // Trigger after send action
        do_action('emailit_after_send', $email_data, $api_response, $log_id);

        // Handle response
        if (is_wp_error($api_response)) {
            // Log error
            $this->logger->log(
                'Email send failed: ' . $api_response->get_error_message(),
                Emailit_Logger::LEVEL_ERROR,
                array(
                    'to' => $email_data['to'],
                    'subject' => $email_data['subject'],
                    'error' => $api_response->get_error_data()
                )
            );

            // Try fallback if enabled
            if ($this->fallback_enabled) {
                $this->logger->log('Attempting fallback to wp_mail', Emailit_Logger::LEVEL_WARNING);
                return $this->fallback_to_wp_mail(
                    $email_data['to'],
                    $email_data['subject'],
                    $email_data['message'],
                    $email_data['headers'],
                    $email_data['attachments']
                );
            }

            return false;
        }

        // Success
        $this->logger->log(
            'Email sent successfully via Emailit API',
            Emailit_Logger::LEVEL_INFO,
            array(
                'to' => $email_data['to'],
                'subject' => $email_data['subject'],
                'email_id' => isset($api_response['data']['id']) ? $api_response['data']['id'] : null
            )
        );

        return true;
    }

    /**
     * Prepare email data from wp_mail arguments
     */
    private function prepare_email_data($to, $subject, $message, $headers = '', $attachments = array()) {
        // Parse headers
        $parsed_headers = $this->parse_headers($headers);

        // Determine content type
        $content_type = 'text/plain';
        if (isset($parsed_headers['Content-Type'])) {
            if (strpos($parsed_headers['Content-Type'], 'text/html') !== false) {
                $content_type = 'text/html';
            }
        }

        // Build email data with sanitization
        $email_data = array(
            'to' => is_array($to) ? array_map('sanitize_email', array_filter($to, 'is_email')) : (is_email($to) ? sanitize_email($to) : ''),
            'subject' => $this->sanitize_subject($subject),
            'message' => $message, // Message content handled by content type
            'content_type' => $content_type,
            'headers' => $parsed_headers,
            'attachments' => $attachments
        );

        // Add from information with validation
        if (isset($parsed_headers['From'])) {
            if (preg_match('/^(.+?)\s*<(.+?)>$/', $parsed_headers['From'], $matches)) {
                $from_name = trim($matches[1], '"\'');
                $from_email = trim($matches[2]);

                // Validate email address
                if (is_email($from_email)) {
                    $email_data['from_name'] = sanitize_text_field($from_name);
                    $email_data['from'] = sanitize_email($from_email);
                }
            } else {
                $from_email = trim($parsed_headers['From']);
                if (is_email($from_email)) {
                    $email_data['from'] = sanitize_email($from_email);
                }
            }
        }

        // Add reply-to with validation
        if (isset($parsed_headers['Reply-To'])) {
            $reply_to = trim($parsed_headers['Reply-To']);
            if (is_email($reply_to)) {
                $email_data['reply_to'] = sanitize_email($reply_to);
            }
        }

        // Add CC/BCC with validation
        if (isset($parsed_headers['Cc'])) {
            $email_data['cc'] = $this->sanitize_email_list($parsed_headers['Cc']);
        }

        if (isset($parsed_headers['Bcc'])) {
            $email_data['bcc'] = $this->sanitize_email_list($parsed_headers['Bcc']);
        }

        return $email_data;
    }

    /**
     * Parse email headers with security sanitization
     */
    private function parse_headers($headers) {
        $parsed = array();

        if (is_array($headers)) {
            foreach ($headers as $header) {
                if (strpos($header, ':') !== false) {
                    list($name, $value) = explode(':', $header, 2);
                    $name = $this->sanitize_header_name(trim($name));
                    $value = $this->sanitize_header_value(trim($value));

                    if ($name && $value !== false) {
                        $parsed[$name] = $value;
                    }
                }
            }
        } elseif (is_string($headers) && !empty($headers)) {
            $header_lines = explode("\n", str_replace("\r\n", "\n", $headers));
            foreach ($header_lines as $header) {
                $header = trim($header);
                if (!empty($header) && strpos($header, ':') !== false) {
                    list($name, $value) = explode(':', $header, 2);
                    $name = $this->sanitize_header_name(trim($name));
                    $value = $this->sanitize_header_value(trim($value));

                    if ($name && $value !== false) {
                        $parsed[$name] = $value;
                    }
                }
            }
        }

        return $parsed;
    }

    /**
     * Sanitize header name to prevent injection
     */
    private function sanitize_header_name($name) {
        // Remove any characters that aren't allowed in header names
        $name = preg_replace('/[^\x21-\x39\x3B-\x7E]/', '', $name);

        // Validate against common header names
        $allowed_headers = array(
            'From', 'Reply-To', 'To', 'Cc', 'Bcc', 'Subject',
            'Content-Type', 'Content-Transfer-Encoding', 'MIME-Version',
            'X-Priority', 'X-Mailer', 'Date', 'Message-ID'
        );

        return in_array($name, $allowed_headers, true) ? $name : '';
    }

    /**
     * Sanitize header value to prevent injection attacks
     */
    private function sanitize_header_value($value) {
        // Remove or replace dangerous characters
        // \r and \n can be used for header injection
        if (preg_match('/[\r\n]/', $value)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $this->logger->log('Header injection attempt detected', Emailit_Logger::LEVEL_WARNING, array(
                    'value' => $value,
                    'ip' => $this->get_client_ip()
                ));
            }
            return false; // Reject the entire header
        }

        // Remove null bytes and other control characters except tab
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);

        // Limit length to prevent abuse
        if (strlen($value) > 1000) {
            $value = substr($value, 0, 1000);
        }

        return $value;
    }

    /**
     * Sanitize comma-separated email list
     */
    private function sanitize_email_list($email_list) {
        if (empty($email_list)) {
            return '';
        }

        $emails = explode(',', $email_list);
        $sanitized_emails = array();

        foreach ($emails as $email) {
            $email = trim($email);

            // Handle "Name <email@domain.com>" format
            if (preg_match('/^(.+?)\s*<(.+?)>$/', $email, $matches)) {
                $email_address = trim($matches[2]);
            } else {
                $email_address = $email;
            }

            // Validate and sanitize
            if (is_email($email_address)) {
                $sanitized_emails[] = $email;
            }
        }

        return implode(', ', $sanitized_emails);
    }

    /**
     * Sanitize email subject line
     */
    private function sanitize_subject($subject) {
        // Remove newlines that could be used for header injection
        $subject = preg_replace('/[\r\n]/', '', $subject);

        // Remove null bytes and other dangerous control characters
        $subject = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $subject);

        // Limit length to reasonable maximum
        if (strlen($subject) > 255) {
            $subject = substr($subject, 0, 255);
        }

        return trim($subject);
    }

    /**
     * Extract headers from PHPMailer object
     */
    private function extract_headers_from_phpmailer($phpmailer) {
        $headers = array();

        // From
        if (!empty($phpmailer->FromName)) {
            $headers['From'] = $phpmailer->FromName . ' <' . $phpmailer->From . '>';
        } else {
            $headers['From'] = $phpmailer->From;
        }

        // Reply-To
        $reply_to = $phpmailer->getReplyToAddresses();
        if (!empty($reply_to)) {
            $reply_to_address = array_keys($reply_to)[0];
            $reply_to_name = $reply_to[$reply_to_address];
            $headers['Reply-To'] = !empty($reply_to_name) ? $reply_to_name . ' <' . $reply_to_address . '>' : $reply_to_address;
        }

        // CC
        $cc_addresses = $phpmailer->getCcAddresses();
        if (!empty($cc_addresses)) {
            $cc_list = array();
            foreach ($cc_addresses as $address => $name) {
                $cc_list[] = !empty($name) ? $name . ' <' . $address . '>' : $address;
            }
            $headers['Cc'] = implode(', ', $cc_list);
        }

        // BCC
        $bcc_addresses = $phpmailer->getBccAddresses();
        if (!empty($bcc_addresses)) {
            $bcc_list = array();
            foreach ($bcc_addresses as $address => $name) {
                $bcc_list[] = !empty($name) ? $name . ' <' . $address . '>' : $address;
            }
            $headers['Bcc'] = implode(', ', $bcc_list);
        }

        // Content-Type
        if ($phpmailer->isHTML()) {
            $headers['Content-Type'] = 'text/html; charset=' . $phpmailer->CharSet;
        } else {
            $headers['Content-Type'] = 'text/plain; charset=' . $phpmailer->CharSet;
        }

        return $headers;
    }

    /**
     * Extract attachments from PHPMailer object
     */
    private function extract_attachments_from_phpmailer($phpmailer) {
        $attachments = array();

        foreach ($phpmailer->getAttachments() as $attachment) {
            $attachments[] = array(
                'path' => $attachment[0], // File path
                'name' => $attachment[1], // Filename
                'type' => $attachment[4]  // MIME type
            );
        }

        return $attachments;
    }

    /**
     * Check if email should be sent via Emailit
     */
    public function should_send_via_emailit($email_data, $phpmailer = null) {
        // Type safety check
        if (!is_array($email_data)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $this->logger->log('Invalid email_data type passed to should_send_via_emailit', Emailit_Logger::LEVEL_WARNING, array(
                    'type_received' => gettype($email_data),
                    'value' => $email_data
                ));
            }
            return false;
        }

        // Check if Emailit is properly configured
        if (empty(get_option('emailit_api_key'))) {
            return false;
        }

        // Apply filter to allow other plugins to decide
        $should_send = apply_filters('emailit_should_send', true, $email_data, $phpmailer);

        // Check for specific exclusions
        if ($this->is_excluded_email($email_data)) {
            return false;
        }

        return $should_send;
    }

    /**
     * Check if email should be excluded from Emailit
     */
    private function is_excluded_email($email_data) {
        // Exclude WordPress core emails that might cause issues
        $excluded_subjects = array(
            'New User Registration',
            'Password Reset',
            '[' . get_bloginfo('name') . '] Login Details'
        );

        foreach ($excluded_subjects as $excluded_subject) {
            if (strpos($email_data['subject'], $excluded_subject) !== false) {
                return true;
            }
        }

        // Check for test emails during plugin testing
        if (strpos($email_data['subject'], 'WordPress Test Email') !== false) {
            return false; // Allow test emails through Emailit
        }

        // Allow filtering of excluded emails
        return apply_filters('emailit_is_excluded_email', false, $email_data);
    }

    /**
     * Fallback to WordPress wp_mail
     */
    private function fallback_to_wp_mail($to, $subject, $message, $headers = '', $attachments = array()) {
        // Remove our hooks temporarily to avoid infinite loop
        $this->remove_hooks();

        // Log fallback attempt
        $this->logger->log(
            'Falling back to WordPress wp_mail',
            Emailit_Logger::LEVEL_WARNING,
            array(
                'to' => $to,
                'subject' => $subject
            )
        );

        // Use original wp_mail function
        $result = wp_mail($to, $subject, $message, $headers, $attachments);

        // Restore our hooks
        $this->init_hooks();

        return $result;
    }

    /**
     * Remove hooks temporarily
     */
    private function remove_hooks() {
        remove_filter('pre_wp_mail', array($this, 'pre_wp_mail_handler'), 10);
        remove_action('phpmailer_init', array($this, 'phpmailer_init_handler'));
    }

    /**
     * Resend email from log
     */
    public function resend_email($log_id) {
        $log = $this->logger->get_log($log_id);

        if (!$log) {
            return new WP_Error('log_not_found', __('Email log not found.', 'emailit-integration'));
        }

        // Prepare email data from log
        $email_data = array(
            'to' => $log['to_email'],
            'subject' => $log['subject'],
            'message' => !empty($log['body_html']) ? $log['body_html'] : $log['body_text'],
            'content_type' => !empty($log['body_html']) ? 'text/html' : 'text/plain',
            'from' => $log['from_email']
        );

        if (!empty($log['reply_to'])) {
            $email_data['reply_to'] = $log['reply_to'];
        }

        // Send email
        $result = $this->send_email_data($email_data);

        // Update original log status if successful
        if (!is_wp_error($result)) {
            $this->logger->update_email_status($log_id, Emailit_Logger::STATUS_SENT, array(
                'resent_at' => current_time('mysql'),
                'resent_by' => get_current_user_id()
            ));
        }

        return $result;
    }

    /**
     * Get API handler
     */
    public function get_api() {
        return $this->api;
    }

    /**
     * Get logger
     */
    public function get_logger() {
        return $this->logger;
    }

    /**
     * Enable/disable fallback
     */
    public function set_fallback_enabled($enabled) {
        $this->fallback_enabled = (bool) $enabled;
        update_option('emailit_fallback_enabled', $this->fallback_enabled);
    }

    /**
     * Check if fallback is enabled
     */
    public function is_fallback_enabled() {
        return $this->fallback_enabled;
    }

    /**
     * Check if email should use queue
     */
    private function should_use_queue($email_data) {
        // Don't queue if queue is disabled or not available
        if (!$this->queue_enabled || !$this->queue) {
            return false;
        }

        // Don't queue test emails
        if (isset($email_data['test_email'])) {
            return false;
        }

        // Don't queue password reset and critical emails
        if (isset($email_data['subject'])) {
            $critical_subjects = array(
                'Password Reset',
                'Login Details',
                'Account Activation',
                'Two-Factor Authentication'
            );

            foreach ($critical_subjects as $critical) {
                if (strpos($email_data['subject'], $critical) !== false) {
                    return false;
                }
            }
        }

        // Allow filtering
        return apply_filters('emailit_should_queue', true, $email_data);
    }

    /**
     * Add email to queue
     */
    private function queue_email($email_data) {
        if (!$this->queue) {
            return false;
        }

        // Determine priority based on email content
        $priority = $this->determine_email_priority($email_data);

        // Add to queue
        $queue_id = $this->queue->add_email($email_data, $priority);

        if (is_wp_error($queue_id)) {
            // If queueing fails, try to send immediately
            $this->logger->log(
                'Failed to queue email, sending immediately: ' . $queue_id->get_error_message(),
                Emailit_Logger::LEVEL_WARNING
            );

            return $this->send_email_immediately($email_data);
        }

        // Log the email as queued with queue reference
        $email_data['queue_id'] = $queue_id;
        $log_id = $this->logger->log_email($email_data, null, 'queued');

        $this->logger->log(
            'Email queued for processing',
            Emailit_Logger::LEVEL_INFO,
            array(
                'queue_id' => $queue_id,
                'log_id' => $log_id,
                'priority' => $priority
            )
        );

        return true;
    }

    /**
     * Determine email priority
     */
    private function determine_email_priority($email_data) {
        // Default priority
        $priority = 10;

        // High priority for transactional emails
        if (isset($email_data['subject'])) {
            $high_priority_keywords = array(
                'order',
                'purchase',
                'payment',
                'receipt',
                'invoice',
                'confirmation',
                'registration',
                'welcome'
            );

            $subject_lower = strtolower($email_data['subject']);
            foreach ($high_priority_keywords as $keyword) {
                if (strpos($subject_lower, $keyword) !== false) {
                    $priority = 5;
                    break;
                }
            }
        }

        // Low priority for newsletters and bulk emails
        if (isset($email_data['headers']['List-Unsubscribe']) ||
            isset($email_data['headers']['Precedence'])) {
            $priority = 20;
        }

        return apply_filters('emailit_email_priority', $priority, $email_data);
    }

    /**
     * Send email immediately (bypass queue)
     */
    private function send_email_immediately($email_data) {
        // Temporarily disable queue
        $original_queue_enabled = $this->queue_enabled;
        $this->queue_enabled = false;

        $result = $this->send_email_data($email_data);

        // Restore queue setting
        $this->queue_enabled = $original_queue_enabled;

        return $result;
    }

    /**
     * Get queue instance
     */
    public function get_queue() {
        return $this->queue;
    }

    /**
     * Get client IP address securely
     */
    private function get_client_ip() {
        try {
            $headers = array(
                'HTTP_X_FORWARDED_FOR',
                'HTTP_X_REAL_IP',
                'HTTP_CF_CONNECTING_IP',
                'REMOTE_ADDR'
            );

            foreach ($headers as $header) {
                if (!empty($_SERVER[$header])) {
                    $ip = trim($_SERVER[$header]);

                    // Handle comma-separated IPs (take first one)
                    if (strpos($ip, ',') !== false) {
                        $ip = trim(explode(',', $ip)[0]);
                    }

                    // Validate and ensure it's a real IP (not private/reserved)
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        return $ip;
                    }
                }
            }

            // Fallback - but validate it too
            $fallback = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            return filter_var($fallback, FILTER_VALIDATE_IP) ? $fallback : '127.0.0.1';

        } catch (Exception $e) {
            // If anything goes wrong, return safe default
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Emailit] Error in get_client_ip: ' . $e->getMessage());
            }
            return '127.0.0.1';
        }
    }

    /**
     * Check if queue is enabled
     */
    public function is_queue_enabled() {
        return $this->queue_enabled;
    }

    /**
     * Enable/disable queue
     */
    public function set_queue_enabled($enabled) {
        $this->queue_enabled = (bool) $enabled;
        update_option('emailit_enable_queue', $this->queue_enabled);

        if ($this->queue) {
            $this->queue->set_enabled($this->queue_enabled);
        }
    }
}