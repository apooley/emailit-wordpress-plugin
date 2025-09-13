<?php
/**
 * Emailit API Handler Class
 *
 * Handles all communication with the Emailit API service.
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

class Emailit_API {

    /**
     * Logger instance
     */
    private $logger;

    /**
     * Error handler instance
     */
    private $error_handler;

    /**
     * API endpoint
     */
    private $api_endpoint;

    /**
     * API key
     */
    private $api_key;

    /**
     * Request timeout
     */
    private $timeout;

    /**
     * Retry attempts
     */
    private $retry_attempts;

    /**
     * Constructor
     */
    public function __construct($logger = null) {
        $this->logger = $logger;
        $this->api_endpoint = EMAILIT_API_ENDPOINT;
        $this->timeout = (int) get_option('emailit_timeout', 30);
        $this->retry_attempts = (int) get_option('emailit_retry_attempts', 3);

        // Initialize error handler
        require_once EMAILIT_PLUGIN_DIR . 'includes/class-emailit-error-handler.php';
        $this->error_handler = new Emailit_Error_Handler($this->logger);

        // Get API key (encrypted)
        $this->api_key = $this->get_api_key();
    }

    /**
     * Send email via Emailit API
     */
    public function send_email($email_data) {
        // Check circuit breaker
        if ($this->error_handler->is_circuit_breaker_open()) {
            return new WP_Error('circuit_breaker_open', __('Emailit API temporarily disabled due to repeated failures.', 'emailit-integration'));
        }

        // Check if API is temporarily disabled
        $disabled_until = get_option('emailit_api_disabled_until', 0);
        if ($disabled_until && time() < $disabled_until) {
            return new WP_Error('api_temporarily_disabled', __('Emailit API temporarily disabled due to errors.', 'emailit-integration'));
        }

        // Check rate limiting
        if (get_transient('emailit_rate_limited')) {
            return new WP_Error('rate_limited', __('Emailit API rate limit in effect. Please try again later.', 'emailit-integration'));
        }

        // Validate API key
        if (empty($this->api_key)) {
            $error = new WP_Error('no_api_key', __('Emailit API key is not configured.', 'emailit-integration'));
            $this->error_handler->handle_error($error, array('email_data' => $email_data));
            return $error;
        }

        // Validate email data
        $validation = $this->validate_email_data($email_data);
        if (is_wp_error($validation)) {
            $this->error_handler->handle_error($validation, array('email_data' => $email_data));
            return $validation;
        }

        // Prepare API request
        $request_data = $this->prepare_request_data($email_data);

        // Apply filters to allow modification
        $request_data = apply_filters('emailit_api_request_data', $request_data, $email_data);

        // Send with retry logic
        $response = $this->send_with_retry($request_data);

        // Log the response
        $this->log_api_response($request_data, $response, $email_data);

        return $response;
    }

    /**
     * Validate email data
     */
    private function validate_email_data($email_data) {
        // Check required fields
        $required_fields = array('to', 'subject', 'message');

        foreach ($required_fields as $field) {
            if (empty($email_data[$field])) {
                return new WP_Error('missing_field', sprintf(__('Required field "%s" is missing.', 'emailit-integration'), $field));
            }
        }

        // Validate email addresses
        $to_emails = $this->parse_email_addresses($email_data['to']);
        if (empty($to_emails)) {
            return new WP_Error('invalid_to_email', __('No valid "to" email addresses found.', 'emailit-integration'));
        }

        // Validate from email if provided
        if (!empty($email_data['from']) && !is_email($email_data['from'])) {
            return new WP_Error('invalid_from_email', __('From email address is invalid.', 'emailit-integration'));
        }

        return true;
    }

    /**
     * Prepare request data for API
     */
    private function prepare_request_data($email_data) {
        // Get default settings
        $from_name = get_option('emailit_from_name', get_bloginfo('name'));
        $from_email = get_option('emailit_from_email', get_bloginfo('admin_email'));
        $reply_to = get_option('emailit_reply_to', '');

        // Format recipients (API expects single string, not array)
        $to_emails = $this->parse_email_addresses($email_data['to']);
        $to_string = is_array($to_emails) ? $to_emails[0] : $to_emails;

        // Format from address (API expects "Name <email>" format)
        $from_email_final = isset($email_data['from']) ? $email_data['from'] : $from_email;
        $from_name_final = isset($email_data['from_name']) ? $email_data['from_name'] : $from_name;
        $from_string = $from_name_final . ' <' . $from_email_final . '>';

        // Build request data
        $request_data = array(
            'to' => $to_string,
            'subject' => $email_data['subject'],
            'from' => $from_string
        );

        // Add reply-to if available
        if (!empty($reply_to) || !empty($email_data['reply_to'])) {
            $request_data['reply_to'] = !empty($email_data['reply_to']) ? $email_data['reply_to'] : $reply_to;
        }

        // Handle message content (API uses 'html' and 'text' fields)
        if (isset($email_data['content_type']) && $email_data['content_type'] === 'text/html') {
            $request_data['html'] = $email_data['message'];
            // Generate plain text version
            $request_data['text'] = $this->html_to_text($email_data['message']);
        } else {
            $request_data['text'] = $email_data['message'];
        }

        // Note: For now, we'll focus on the basic fields that match the API documentation
        // CC/BCC and attachments may need to be handled differently or may not be supported
        // Remove these for initial testing to match the curl example exactly

        return $request_data;
    }

    /**
     * Send request with retry logic
     */
    private function send_with_retry($request_data, $attempt = 1) {
        $response = $this->make_api_request($request_data);

        // If successful, handle success and return
        if (!is_wp_error($response)) {
            $this->error_handler->handle_error('success', array('attempt' => $attempt));
            return $response;
        }

        // Handle the error
        $error_context = array(
            'attempt' => $attempt,
            'max_attempts' => $this->retry_attempts,
            'request_data' => $request_data
        );

        $error_result = $this->error_handler->handle_error($response, $error_context);

        // If max attempts reached, return final error
        if ($attempt >= $this->retry_attempts) {
            return $response;
        }

        // Check if we should retry based on error strategy
        if (!$error_result['should_retry']) {
            return $response;
        }

        // Log retry attempt
        if ($this->logger) {
            $this->logger->log(
                sprintf('Emailit API attempt %d failed, retrying...', $attempt),
                'warning',
                array('error' => $response->get_error_message())
            );
        }

        // Wait before retry (exponential backoff)
        sleep(pow(2, $attempt - 1));

        // Retry
        return $this->send_with_retry($request_data, $attempt + 1);
    }

    /**
     * Make actual API request
     */
    private function make_api_request($request_data) {
        $args = array(
            'method' => 'POST',
            'timeout' => $this->timeout,
            'redirection' => 0,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; Emailit-Integration/' . EMAILIT_VERSION
            ),
            'body' => wp_json_encode($request_data),
            'data_format' => 'body'
        );

        // Apply filters
        $args = apply_filters('emailit_api_args', $args, $request_data);

        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Emailit API Request URL: ' . $this->api_endpoint);
            error_log('Emailit API Request Args: ' . print_r($args, true));
        }

        // Make request
        $response = wp_remote_request($this->api_endpoint, $args);

        // Handle response
        if (is_wp_error($response)) {
            return new WP_Error('api_request_failed', $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Emailit API Response Code: ' . $response_code);
            error_log('Emailit API Response Body: ' . $response_body);
        }

        // Parse JSON response
        $parsed_response = json_decode($response_body, true);

        if ($response_code >= 200 && $response_code < 300) {
            // Success
            return array(
                'success' => true,
                'data' => $parsed_response,
                'response_code' => $response_code
            );
        } else {
            // Error
            $error_message = isset($parsed_response['message']) ? $parsed_response['message'] : 'Unknown API error';
            return new WP_Error(
                'api_error',
                sprintf(__('Emailit API error (%d): %s', 'emailit-integration'), $response_code, $error_message),
                array(
                    'response_code' => $response_code,
                    'response_body' => $parsed_response
                )
            );
        }
    }

    /**
     * Parse email addresses from various formats
     */
    private function parse_email_addresses($emails) {
        if (is_array($emails)) {
            return array_filter(array_map('sanitize_email', $emails));
        }

        if (is_string($emails)) {
            // Handle comma-separated emails
            $email_list = array_map('trim', explode(',', $emails));

            $parsed = array();
            foreach ($email_list as $email) {
                // Handle "Name <email@domain.com>" format
                if (preg_match('/^(.+?)\s*<(.+?)>$/', $email, $matches)) {
                    $clean_email = sanitize_email(trim($matches[2]));
                    if (is_email($clean_email)) {
                        $parsed[] = array(
                            'email' => $clean_email,
                            'name' => sanitize_text_field(trim($matches[1], '"\''))
                        );
                    }
                } else {
                    // Plain email address
                    $clean_email = sanitize_email($email);
                    if (is_email($clean_email)) {
                        $parsed[] = $clean_email;
                    }
                }
            }

            return $parsed;
        }

        return array();
    }

    /**
     * Prepare attachments for API
     */
    private function prepare_attachments($attachments) {
        $prepared = array();

        foreach ($attachments as $attachment) {
            if (is_string($attachment) && file_exists($attachment)) {
                // File path
                $file_content = file_get_contents($attachment);
                if ($file_content !== false) {
                    $prepared[] = array(
                        'filename' => basename($attachment),
                        'content' => base64_encode($file_content),
                        'content_type' => mime_content_type($attachment)
                    );
                }
            } elseif (is_array($attachment) && isset($attachment['path'])) {
                // Array with file info
                if (file_exists($attachment['path'])) {
                    $file_content = file_get_contents($attachment['path']);
                    if ($file_content !== false) {
                        $prepared[] = array(
                            'filename' => isset($attachment['name']) ? $attachment['name'] : basename($attachment['path']),
                            'content' => base64_encode($file_content),
                            'content_type' => isset($attachment['type']) ? $attachment['type'] : mime_content_type($attachment['path'])
                        );
                    }
                }
            }
        }

        return $prepared;
    }

    /**
     * Parse headers from wp_mail format
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
        } elseif (is_string($headers)) {
            $header_lines = explode("\n", $headers);
            foreach ($header_lines as $header) {
                if (strpos($header, ':') !== false) {
                    list($name, $value) = explode(':', $header, 2);
                    $parsed[trim($name)] = trim($value);
                }
            }
        }

        // Remove headers handled elsewhere
        unset($parsed['Content-Type'], $parsed['From'], $parsed['Reply-To'], $parsed['Cc'], $parsed['Bcc']);

        return $parsed;
    }

    /**
     * Convert HTML to plain text
     */
    private function html_to_text($html) {
        // Remove scripts and styles
        $html = preg_replace('/<(script|style)[^>]*?>.*?<\/\\1>/si', '', $html);

        // Convert common HTML entities
        $html = str_replace(array('&nbsp;', '&amp;', '&lt;', '&gt;'), array(' ', '&', '<', '>'), $html);

        // Convert line breaks
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html);
        $html = preg_replace('/<\/p>/i', "\n\n", $html);

        // Strip remaining HTML tags
        $text = wp_strip_all_tags($html);

        // Clean up whitespace
        $text = preg_replace('/\n\s*\n/', "\n\n", $text);
        $text = trim($text);

        return $text;
    }

    /**
     * Get encrypted API key
     */
    private function get_api_key() {
        $key = get_option('emailit_api_key', '');

        if (empty($key)) {
            return '';
        }

        // Check if the key looks encrypted (base64 encoded)
        // If it's not encrypted, return as-is (for backward compatibility)
        if ($this->is_encrypted($key)) {
            $decrypted = $this->decrypt_string($key);

            // If new decryption fails, try legacy decryption
            if (empty($decrypted) && strlen($key) > 50) {
                $decrypted = $this->decrypt_string_legacy($key);

                // If legacy decryption works, re-encrypt with new method
                if (!empty($decrypted)) {
                    $this->set_api_key($decrypted); // This will re-encrypt with new method
                }
            }

            return $decrypted;
        }

        return $key;
    }

    /**
     * Check if a string appears to be encrypted (enhanced detection)
     */
    private function is_encrypted($string) {
        // First try new GCM format
        if (base64_encode(base64_decode($string, true)) === $string) {
            $decoded = base64_decode($string);
            // GCM format: 12 bytes IV + 16 bytes tag + ciphertext (minimum 28 bytes)
            if (strlen($decoded) >= 28) {
                return true;
            }
        }

        // Legacy detection for backward compatibility
        return base64_encode(base64_decode($string, true)) === $string && strlen($string) > 50;
    }

    /**
     * Set encrypted API key
     */
    public function set_api_key($api_key) {
        $encrypted_key = $this->encrypt_string($api_key);
        update_option('emailit_api_key', $encrypted_key);
        $this->api_key = $api_key;
    }

    /**
     * Test API connection
     */
    public function test_connection(?string $test_email = null) {
        // Refresh API key from database
        $this->api_key = $this->get_api_key();

        // Debug: Check what we got from database
        $raw_key = get_option('emailit_api_key', '');

        if (empty($this->api_key)) {
            $debug_message = sprintf(
                __('API key is required for testing. Debug: Raw key from DB: "%s" (length: %d), Processed key: "%s" (length: %d)', 'emailit-integration'),
                substr($raw_key, 0, 10) . '...',
                strlen($raw_key),
                substr($this->api_key, 0, 10) . '...',
                strlen($this->api_key)
            );
            return new WP_Error('no_api_key', $debug_message);
        }

        $test_data = array(
            'to' => $test_email ?: get_bloginfo('admin_email'),
            'subject' => sprintf(__('Test email from %s', 'emailit-integration'), get_bloginfo('name')),
            'message' => sprintf(
                __("This is a test email sent from the Emailit Integration plugin.\n\nSite: %s\nTime: %s\n\nIf you received this email, your Emailit integration is working correctly!", 'emailit-integration'),
                home_url(),
                current_time('mysql')
            ),
            'content_type' => 'text/plain'
        );

        return $this->send_email($test_data);
    }

    /**
     * Validate API key
     */
    public function validate_api_key(?string $api_key = null) {
        $key_to_test = $api_key ?: $this->api_key;

        if (empty($key_to_test)) {
            return new WP_Error('empty_key', __('API key cannot be empty.', 'emailit-integration'));
        }

        // Check transient cache first
        $cache_key = 'emailit_api_key_valid_' . md5($key_to_test);
        $cached_result = get_transient($cache_key);

        if ($cached_result !== false) {
            return $cached_result === 'valid';
        }

        // Make validation request to API
        $args = array(
            'method' => 'GET',
            'timeout' => 10,
            'headers' => array(
                'Authorization' => 'Bearer ' . $key_to_test,
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; Emailit-Integration/' . EMAILIT_VERSION
            )
        );

        $validation_endpoint = str_replace('/emails', '/account', $this->api_endpoint);
        $response = wp_remote_request($validation_endpoint, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $is_valid = $response_code >= 200 && $response_code < 300;

        // Cache result for 5 minutes
        set_transient($cache_key, $is_valid ? 'valid' : 'invalid', 300);

        if (!$is_valid) {
            $response_body = wp_remote_retrieve_body($response);
            $parsed_response = json_decode($response_body, true);
            $error_message = isset($parsed_response['message']) ? $parsed_response['message'] : 'Invalid API key';

            return new WP_Error('invalid_key', $error_message);
        }

        return true;
    }

    /**
     * Log API response
     */
    private function log_api_response($request_data, $response, $original_email_data) {
        if (!$this->logger) {
            return;
        }

        $log_data = array(
            'to_email' => is_array($original_email_data['to']) ? implode(', ', $original_email_data['to']) : $original_email_data['to'],
            'subject' => $original_email_data['subject'],
            'request_data' => $request_data,
            'response' => $response
        );

        if (is_wp_error($response)) {
            $this->logger->log('Emailit API request failed', 'error', $log_data);
        } else {
            $this->logger->log('Emailit API request successful', 'info', $log_data);
        }
    }

    /**
     * Encrypt string using secure AES-256-GCM encryption
     */
    private function encrypt_string($string) {
        if (empty($string)) {
            return '';
        }

        // Use AES-256-GCM for authenticated encryption
        $cipher = 'AES-256-GCM';

        // Generate proper 256-bit key from WordPress salts
        $key = hash('sha256', wp_salt('auth') . wp_salt('secure_auth'), true);

        // Generate random IV (12 bytes for GCM)
        $iv = openssl_random_pseudo_bytes(12);

        // Encrypt with authentication tag
        $tag = '';
        $encrypted = openssl_encrypt($string, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($encrypted === false) {
            return '';
        }

        // Combine IV + tag + ciphertext and encode
        $result = base64_encode($iv . $tag . $encrypted);

        return $result;
    }

    /**
     * Decrypt string using secure AES-256-GCM decryption
     */
    private function decrypt_string($encrypted_string) {
        if (empty($encrypted_string)) {
            return '';
        }

        $cipher = 'AES-256-GCM';

        // Generate the same key
        $key = hash('sha256', wp_salt('auth') . wp_salt('secure_auth'), true);

        // Decode the combined data
        $data = base64_decode($encrypted_string);
        if ($data === false || strlen($data) < 28) { // 12 IV + 16 tag minimum
            return '';
        }

        // Extract components
        $iv = substr($data, 0, 12);
        $tag = substr($data, 12, 16);
        $ciphertext = substr($data, 28);

        // Decrypt with authentication verification
        $decrypted = openssl_decrypt($ciphertext, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag);

        return $decrypted !== false ? $decrypted : '';
    }

    /**
     * Legacy decrypt function for backward compatibility
     */
    private function decrypt_string_legacy($encrypted_string) {
        if (empty($encrypted_string)) {
            return '';
        }

        $key = wp_salt('auth');
        $decrypted = openssl_decrypt(base64_decode($encrypted_string), 'AES-256-CBC', $key, 0, substr($key, 0, 16));

        return $decrypted !== false ? $decrypted : '';
    }

}