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
     * Fallback enabled
     */
    private $fallback_enabled;

    /**
     * Constructor
     */
    public function __construct($api, $logger) {
        $this->api = $api;
        $this->logger = $logger;
        $this->fallback_enabled = (bool) get_option('emailit_fallback_enabled', 1);

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

        // Hook to allow bypassing Emailit for specific emails
        add_filter('emailit_should_send', array($this, 'should_send_via_emailit'), 10, 2);
    }

    /**
     * Handle pre_wp_mail filter (WordPress 5.7+)
     */
    public function pre_wp_mail_handler($null, $atts) {
        // Extract wp_mail arguments
        $to = $atts['to'];
        $subject = $atts['subject'];
        $message = $atts['message'];
        $headers = isset($atts['headers']) ? $atts['headers'] : '';
        $attachments = isset($atts['attachments']) ? $atts['attachments'] : array();

        // Send via Emailit
        $result = $this->send($to, $subject, $message, $headers, $attachments);

        // Return result to bypass wp_mail
        return $result;
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
        // Prepare email data
        $email_data = $this->prepare_email_data($to, $subject, $message, $headers, $attachments);

        // Apply filter to allow modification
        $email_data = apply_filters('emailit_email_data', $email_data, $to, $subject, $message, $headers, $attachments);

        // Check if we should send via Emailit
        if (!$this->should_send_via_emailit($email_data)) {
            return $this->fallback_to_wp_mail($to, $subject, $message, $headers, $attachments);
        }

        // Send email
        return $this->send_email_data($email_data);
    }

    /**
     * Send email data via API
     */
    private function send_email_data($email_data) {
        // Trigger before send action
        do_action('emailit_before_send', $email_data);

        // Send via API
        $api_response = $this->api->send_email($email_data);

        // Determine status based on response
        $status = is_wp_error($api_response) ? Emailit_Logger::STATUS_FAILED : Emailit_Logger::STATUS_SENT;

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

        // Build email data
        $email_data = array(
            'to' => $to,
            'subject' => $subject,
            'message' => $message,
            'content_type' => $content_type,
            'headers' => $parsed_headers,
            'attachments' => $attachments
        );

        // Add from information
        if (isset($parsed_headers['From'])) {
            if (preg_match('/^(.+?)\s*<(.+?)>$/', $parsed_headers['From'], $matches)) {
                $email_data['from_name'] = trim($matches[1], '"\'');
                $email_data['from'] = trim($matches[2]);
            } else {
                $email_data['from'] = $parsed_headers['From'];
            }
        }

        // Add reply-to
        if (isset($parsed_headers['Reply-To'])) {
            $email_data['reply_to'] = $parsed_headers['Reply-To'];
        }

        // Add CC/BCC
        if (isset($parsed_headers['Cc'])) {
            $email_data['cc'] = $parsed_headers['Cc'];
        }

        if (isset($parsed_headers['Bcc'])) {
            $email_data['bcc'] = $parsed_headers['Bcc'];
        }

        return $email_data;
    }

    /**
     * Parse email headers
     */
    private function parse_headers($headers) {
        $parsed = array();

        if (is_array($headers)) {
            foreach ($headers as $header) {
                if (strpos($header, ':') !== false) {
                    list($name, $value) = explode(':', $header, 2);
                    $parsed[trim($name)] = trim($value);
                }
            }
        } elseif (is_string($headers) && !empty($headers)) {
            $header_lines = explode("\n", str_replace("\r\n", "\n", $headers));
            foreach ($header_lines as $header) {
                $header = trim($header);
                if (!empty($header) && strpos($header, ':') !== false) {
                    list($name, $value) = explode(':', $header, 2);
                    $parsed[trim($name)] = trim($value);
                }
            }
        }

        return $parsed;
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
}