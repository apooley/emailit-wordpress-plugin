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
        // Only hook if Emailit is properly configured
        if (empty(get_option('emailit_api_key'))) {
            return;
        }

        // Use phpmailer_init for all versions to intercept MailPoet's WordPressMailer
        // Priority 1 ensures we hook before MailPoet's mailer replacement
        add_action('phpmailer_init', array($this, 'phpmailer_init_handler'), 1);
        
        // Also use pre_wp_mail filter for WordPress 5.7+ as backup
        if (version_compare(get_bloginfo('version'), '5.7', '>=')) {
            add_filter('pre_wp_mail', array($this, 'pre_wp_mail_handler'), 1, 2);
        }

        // Add FluentCRM-specific filters to detect and handle bypass mechanisms
        $this->add_fluentcrm_debug_filters();

        // Hook into MailPoet's mailer replacement to intercept their WordPressMailer
        add_action('init', array($this, 'intercept_mailpoet_mailer'), 5);

        // Note: should_send_via_emailit is used internally, not as a hook
    }

    /**
     * Handle pre_wp_mail filter (WordPress 5.7+)
     */
    public function pre_wp_mail_handler($null, $atts) {
        try {
            // Check if this is a FluentCRM email for potential special handling
            $is_fluentcrm_email = $this->is_fluentcrm_email($atts);

            // Extract wp_mail arguments with validation
            $to = isset($atts['to']) ? $atts['to'] : '';
            $subject = isset($atts['subject']) ? $atts['subject'] : '';
            $message = isset($atts['message']) ? $atts['message'] : '';
            $headers = isset($atts['headers']) ? $atts['headers'] : '';
            $attachments = isset($atts['attachments']) ? $atts['attachments'] : array();

            // Validate required fields
            if (empty($to) || empty($subject) || empty($message)) {
                $this->logger->log('Missing required email fields', Emailit_Logger::LEVEL_ERROR);
                return false;
            }

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
     * Handle phpmailer_init action (primary method for MailPoet interception)
     */
    public function phpmailer_init_handler(&$phpmailer) {
        try {
            // Store original wp_mail data
            $email_data = array(
                'to' => $phpmailer->getToAddresses(),
                'subject' => $phpmailer->Subject,
                'message' => $phpmailer->Body,
                'headers' => $this->extract_headers_from_phpmailer($phpmailer),
                'attachments' => $this->extract_attachments_from_phpmailer($phpmailer),
                'content_type' => $phpmailer->isHTML() ? 'text/html' : 'text/plain'
            );

            // Enhanced MailPoet detection
            $is_mailpoet_email = $this->is_mailpoet_phpmailer_email($phpmailer);
            
            if ($is_mailpoet_email) {
                $this->logger->log('MailPoet email detected in phpmailer_init', Emailit_Logger::LEVEL_DEBUG, array(
                    'class' => get_class($phpmailer),
                    'subject' => $phpmailer->Subject,
                    'to' => $phpmailer->getToAddresses()
                ));
            }

            // Check if we should send via Emailit
            if (!$this->should_send_via_emailit($email_data, $phpmailer)) {
                if ($is_mailpoet_email) {
                    $this->logger->log('MailPoet email excluded from Emailit processing', Emailit_Logger::LEVEL_DEBUG);
                }
                return; // Let WordPress handle normally
            }

            // Log that we're intercepting this email
            $this->logger->log('Intercepting email via phpmailer_init', Emailit_Logger::LEVEL_DEBUG, array(
                'is_mailpoet' => $is_mailpoet_email,
                'subject' => $phpmailer->Subject,
                'to' => $phpmailer->getToAddresses()
            ));

            // Clear PHPMailer settings to prevent normal sending
            $phpmailer->clearAllRecipients();
            $phpmailer->clearAttachments();
            $phpmailer->clearCustomHeaders();

            // Send via Emailit API
            $result = $this->send_email_data($email_data);

            // Set result status
            if (is_wp_error($result)) {
                $phpmailer->ErrorInfo = $result->get_error_message();
                $this->logger->log('Emailit send failed in phpmailer_init', Emailit_Logger::LEVEL_ERROR, array(
                    'error' => $result->get_error_message(),
                    'subject' => $email_data['subject']
                ));
            } else {
                $this->logger->log('Email successfully sent via Emailit in phpmailer_init', Emailit_Logger::LEVEL_DEBUG, array(
                    'subject' => $email_data['subject'],
                    'to' => $email_data['to']
                ));
            }

        } catch (Exception $e) {
            $this->logger->log('Exception in phpmailer_init_handler', Emailit_Logger::LEVEL_ERROR, array(
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
        }
    }

    /**
     * Main send method (public interface)
     */
    public function send($to, $subject, $message, $headers = '', $attachments = array()) {
        try {

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

        // Extract response time from API response if available
        $response_time = null;
        if (is_array($api_response) && isset($api_response['response_time'])) {
            $response_time = $api_response['response_time'];
        }

        // Log the email
        $log_id = $this->logger->log_email($email_data, $api_response, $status, $response_time);

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

        // Apply content filter to allow modification of email content
        $filtered_message = apply_filters('emailit_email_content', $message, array(
            'to' => $to,
            'subject' => $subject,
            'content_type' => $content_type,
            'headers' => $parsed_headers
        ));

        // Apply attachment filter to allow modification of attachments
        $filtered_attachments = apply_filters('emailit_attachments', $attachments, array(
            'to' => $to,
            'subject' => $subject,
            'message' => $filtered_message,
            'content_type' => $content_type
        ));

        // Build email data with sanitization
        $sanitized_to = $this->sanitize_email_address($to);
        
        $email_data = array(
            'to' => $sanitized_to,
            'subject' => $this->sanitize_subject($subject),
            'message' => $filtered_message, // Use filtered content
            'content_type' => $content_type,
            'headers' => $parsed_headers,
            'attachments' => $filtered_attachments // Use filtered attachments
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
        // Check for header injection attempts first
        if ($this->detect_header_injection($name)) {
            $this->logger->log('Header injection attempt in header name', Emailit_Logger::LEVEL_WARNING, array(
                'header_name' => substr($name, 0, 50) . '...',
                'ip' => $this->get_client_ip()
            ));
            return '';
        }

        // Remove any characters that aren't allowed in header names
        $name = preg_replace('/[^\x21-\x39\x3B-\x7E]/', '', $name);

        // Check length (RFC 5322 limit)
        if (strlen($name) > 78) {
            return '';
        }

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
        // Check for header injection attempts first
        if ($this->detect_header_injection($value)) {
            $this->logger->log('Header injection attempt detected in value', Emailit_Logger::LEVEL_WARNING, array(
                'value' => substr($value, 0, 100) . '...',
                'ip' => $this->get_client_ip()
            ));
            return false; // Reject the entire header
        }

        // Remove null bytes and other control characters except tab
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);

        // Decode URL-encoded CRLF
        $value = str_replace(array('%0d%0a', '%0a%0d', '%0d', '%0a'), ' ', $value);

        // Limit length to prevent abuse (RFC 5322 limit is 998)
        if (strlen($value) > 998) {
            $value = substr($value, 0, 998);
        }

        return $value;
    }

    /**
     * Detect header injection attempts
     */
    private function detect_header_injection($header) {
        // Check for null bytes
        if (strpos($header, "\0") !== false) {
            return true;
        }
        
        // Check for CRLF injection
        if (preg_match('/[\r\n]/', $header)) {
            return true;
        }
        
        // Check for URL-encoded CRLF
        if (preg_match('/%0d%0a|%0a%0d|%0d|%0a/i', $header)) {
            return true;
        }
        
        // Check for suspicious patterns
        $suspicious_patterns = array(
            '/^[\r\n]/',  // Starts with newline
            '/[\r\n]$/',  // Ends with newline
            '/[\r\n]{2,}/', // Multiple consecutive newlines
            '/\b(cc|bcc|to|from|subject)\s*:/i', // Header injection keywords
        );
        
        foreach ($suspicious_patterns as $pattern) {
            if (preg_match($pattern, $header)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
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
     * Sanitize email address, handling "Name <email@domain.com>" format
     */
    private function sanitize_email_address($to) {
        if (is_array($to)) {
            $sanitized_emails = array();
            foreach ($to as $email) {
                $sanitized = $this->sanitize_single_email($email);
                if (!empty($sanitized)) {
                    $sanitized_emails[] = $sanitized;
                }
            }
            return $sanitized_emails;
        }

        return $this->sanitize_single_email($to);
    }

    /**
     * Sanitize a single email address
     */
    private function sanitize_single_email($email) {
        if (empty($email)) {
            return '';
        }

        // Handle "Name <email@domain.com>" format
        if (preg_match('/^(.+?)\s*<(.+?)>$/', $email, $matches)) {
            $name = trim($matches[1], '"\'');
            $email_address = trim($matches[2]);
            
            // Validate the email address part
            if (is_email($email_address)) {
                return sanitize_email($email_address);
            }
        } else {
            // Handle plain email address
            if (is_email($email)) {
                return sanitize_email($email);
            }
        }

        // If we get here, the email is invalid
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->logger->log('Invalid email address format detected', Emailit_Logger::LEVEL_WARNING, array(
                'original_email' => $email,
                'type' => gettype($email)
            ));
        }

        return '';
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

        // Check MailPoet integration settings
        if ($this->is_mailpoet_email_excluded($email_data)) {
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
     * Check if MailPoet email should be excluded from Emailit processing
     */
    private function is_mailpoet_email_excluded($email_data) {
        // Only check if MailPoet is available
        if (!class_exists('MailPoet\Mailer\MailerFactory')) {
            return false;
        }

        // Check if MailPoet integration is enabled
        if (!get_option('emailit_mailpoet_integration', 0)) {
            return false;
        }

        // Check if we should override MailPoet's transactional emails
        $override_transactional = get_option('emailit_mailpoet_override_transactional', 1);
        
        if (!$override_transactional) {
            // If not overriding, exclude MailPoet's internal emails
            return $this->is_mailpoet_internal_email($email_data);
        }

        // If overriding transactional emails, allow all emails through Emailit
        return false;
    }

    /**
     * Check if email is from MailPoet's internal system
     */
    private function is_mailpoet_internal_email($email_data) {
        // Check for MailPoet-specific headers or patterns
        $headers = isset($email_data['headers']) ? $email_data['headers'] : array();
        
        if (is_array($headers)) {
            foreach ($headers as $header) {
                if (is_string($header)) {
                    if (strpos($header, 'X-MailPoet') !== false || 
                        strpos($header, 'mailpoet') !== false) {
                        return true;
                    }
                } elseif (is_array($header) && isset($header[0])) {
                    if (strpos($header[0], 'X-MailPoet') !== false || 
                        strpos($header[0], 'mailpoet') !== false) {
                        return true;
                    }
                }
            }
        }

        // Check for MailPoet-specific subject patterns
        $subject = isset($email_data['subject']) ? $email_data['subject'] : '';
        if (strpos($subject, 'MailPoet') !== false || 
            strpos($subject, 'mailpoet') !== false) {
            return true;
        }

        // Check if MailPoet is currently sending emails
        if (function_exists('did_action') && did_action('mailpoet_sending_emails_starting')) {
            return true;
        }

        return false;
    }

    /**
     * Enhanced MailPoet email detection for phpmailer
     */
    private function is_mailpoet_phpmailer_email($phpmailer) {
        // Check if this is MailPoet's WordPressMailer class
        if (is_object($phpmailer) && get_class($phpmailer) === 'MailPoet\Mailer\WordPress\WordPressMailer') {
            return true;
        }

        // Check for MailPoet-specific headers
        $headers = $phpmailer->getCustomHeaders();
        if (is_array($headers)) {
            foreach ($headers as $header) {
                if (is_array($header) && isset($header[0])) {
                    if (strpos($header[0], 'X-MailPoet') !== false || 
                        strpos($header[0], 'mailpoet') !== false) {
                        return true;
                    }
                }
            }
        }

        // Check if MailPoet is currently active
        if (class_exists('MailPoet\Mailer\MailerFactory') && 
            class_exists('MailPoet\DI\ContainerWrapper')) {
            // Check if MailPoet's transactional emails are enabled
            try {
                $container = \MailPoet\DI\ContainerWrapper::getInstance();
                if ($container) {
                    $settings = \MailPoet\Settings\SettingsController::getInstance();
                    $send_transactional = $settings->get('send_transactional_emails', false);
                    if ($send_transactional) {
                        // If MailPoet is handling transactional emails, this could be from MailPoet
                        return true;
                    }
                }
            } catch (Exception $e) {
                // If we can't check settings, assume it could be MailPoet
                return true;
            } catch (Error $e) {
                // Handle fatal errors gracefully
                return true;
            }
        }

        return false;
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
        remove_filter('pre_wp_mail', array($this, 'pre_wp_mail_handler'), 5);
        remove_action('phpmailer_init', array($this, 'phpmailer_init_handler'));
        
        // Remove FluentCRM debug filters (only if FluentCRM is available)
        if ($this->is_fluentcrm_available()) {
            remove_filter('fluent_crm/is_simulated_mail', array($this, 'debug_fluentcrm_simulation'), 5);
            remove_filter('fluent_crm/disable_email_processing', array($this, 'debug_fluentcrm_processing_disable'), 5);
            
            if (defined('FLUENTMAIL') && function_exists('fluentmail_will_log_email')) {
                remove_filter('fluentmail_will_log_email', array($this, 'debug_fluentmail_logging'), 5);
            }
        }
    }

    /**
     * Reinitialize hooks (useful when API key is configured)
     */
    public function reinit_hooks() {
        $this->remove_hooks();
        $this->init_hooks();
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

        // Start with the default queue setting
        $use_queue = $this->queue_enabled;

        // Apply filter to allow developers to override queue decision
        $use_queue = apply_filters('emailit_use_queue', $use_queue, $email_data);

        // If filter says don't queue, respect that decision
        if (!$use_queue) {
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
     * Check if queue is enabled
     */
    public function is_queue_enabled() {
        return $this->queue_enabled;
    }

    /**
     * Add FluentCRM-specific debug filters to detect bypass mechanisms
     */
    private function add_fluentcrm_debug_filters() {
        // Only add these filters if FluentCRM is available and active
        if (!$this->is_fluentcrm_available()) {
            return;
        }

        // Monitor FluentCRM simulation mode
        add_filter('fluent_crm/is_simulated_mail', array($this, 'debug_fluentcrm_simulation'), 5, 3);
        
        // Monitor FluentCRM email processing disable
        add_filter('fluent_crm/disable_email_processing', array($this, 'debug_fluentcrm_processing_disable'), 5, 1);
        
        // Monitor FluentMail integration (only if FluentMail is also available)
        if (defined('FLUENTMAIL') && function_exists('fluentmail_will_log_email')) {
            add_filter('fluentmail_will_log_email', array($this, 'debug_fluentmail_logging'), 5, 2);
        }
    }

    /**
     * Check if FluentCRM is available and active
     */
    private function is_fluentcrm_available() {
        return class_exists('FluentCrm\App\App') && 
               class_exists('FluentCrm\App\Models\Subscriber') &&
               function_exists('fluentcrm_get_option');
    }

    /**
     * Debug FluentCRM simulation mode
     */
    public function debug_fluentcrm_simulation($is_simulated, $data, $headers) {
        // Only proceed if FluentCRM is available
        if (!$this->is_fluentcrm_available()) {
            return $is_simulated;
        }

        if ($is_simulated) {
            $this->logger->log('FluentCRM simulation mode detected - email bypassed', Emailit_Logger::LEVEL_WARNING);
        }
        return $is_simulated;
    }

    /**
     * Debug FluentCRM email processing disable
     */
    public function debug_fluentcrm_processing_disable($is_disabled) {
        // Only proceed if FluentCRM is available
        if (!$this->is_fluentcrm_available()) {
            return $is_disabled;
        }

        if ($is_disabled) {
            $this->logger->log('FluentCRM email processing disabled', Emailit_Logger::LEVEL_WARNING);
        }
        return $is_disabled;
    }

    /**
     * Debug FluentMail logging integration
     */
    public function debug_fluentmail_logging($will_log, $email_data) {
        // Only proceed if FluentMail is available
        if (!defined('FLUENTMAIL') || !function_exists('fluentmail_will_log_email')) {
            return $will_log;
        }

        $this->logger->log('FluentMail integration detected', Emailit_Logger::LEVEL_DEBUG);
        return $will_log;
    }

    /**
     * Check if email is from FluentCRM
     */
    private function is_fluentcrm_email($atts) {
        // Only check if FluentCRM is available
        if (!$this->is_fluentcrm_available()) {
            return false;
        }

        // Check for FluentCRM-specific headers or patterns
        $headers = isset($atts['headers']) ? $atts['headers'] : '';
        
        if (is_array($headers)) {
            foreach ($headers as $header) {
                if (strpos($header, 'List-Unsubscribe') !== false && strpos($header, 'fluentcrm') !== false) {
                    return true;
                }
            }
        } elseif (is_string($headers)) {
            if (strpos($headers, 'fluentcrm') !== false) {
                return true;
            }
        }
        
        // Check for FluentCRM-specific subject patterns
        $subject = isset($atts['subject']) ? $atts['subject'] : '';
        if (strpos($subject, 'FluentCRM') !== false || strpos($subject, 'fluentcrm') !== false) {
            return true;
        }
        
        // Check if FluentCRM is currently sending emails
        if (function_exists('did_action') && did_action('fluent_crm/sending_emails_starting')) {
            return true;
        }
        
        return false;
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

    /**
     * Intercept MailPoet's mailer replacement to route emails through Emailit
     */
    public function intercept_mailpoet_mailer() {
        // Only proceed if MailPoet is active and transactional emails are enabled
        if (!class_exists('MailPoet\Settings\SettingsController') || 
            !class_exists('MailPoet\DI\ContainerWrapper')) {
            return;
        }

        try {
            $container = \MailPoet\DI\ContainerWrapper::getInstance();
            if (!$container) {
                return;
            }

            $settings = \MailPoet\Settings\SettingsController::getInstance();
            $send_transactional = $settings->get('send_transactional_emails', false);
            
            if (!$send_transactional) {
                return; // MailPoet transactional emails are disabled
            }

            // Hook into MailPoet's mailer factory to intercept when it builds mailers
            add_filter('mailpoet_mailer_factory_build', array($this, 'intercept_mailpoet_factory'), 10, 2);
            
            // Also hook into the global $phpmailer replacement
            add_action('phpmailer_init', array($this, 'intercept_phpmailer_replacement'), 5);
            
            $this->logger->log('MailPoet interception hooks registered', Emailit_Logger::LEVEL_DEBUG);
            
        } catch (Exception $e) {
            $this->logger->log('Error setting up MailPoet interception: ' . $e->getMessage(), Emailit_Logger::LEVEL_ERROR);
        } catch (Error $e) {
            $this->logger->log('Fatal error setting up MailPoet interception: ' . $e->getMessage(), Emailit_Logger::LEVEL_ERROR);
        }
    }

    /**
     * Intercept MailPoet's mailer factory
     */
    public function intercept_mailpoet_factory($mailer, $config) {
        $this->logger->log('Intercepting MailPoet mailer factory', Emailit_Logger::LEVEL_DEBUG);
        return $mailer; // For now, just log and return the original mailer
    }

    /**
     * Intercept PHPMailer replacement
     */
    public function intercept_phpmailer_replacement(&$phpmailer) {
        if (is_object($phpmailer) && get_class($phpmailer) === 'MailPoet\Mailer\WordPress\WordPressMailer') {
            $this->logger->log('Intercepting MailPoet WordPressMailer in phpmailer_init', Emailit_Logger::LEVEL_DEBUG);
            
            // Store the original mailer and replace with our interceptor
            $original_mailer = $phpmailer;
            $phpmailer = new Emailit_MailPoet_Interceptor($original_mailer, $this);
        }
    }
}