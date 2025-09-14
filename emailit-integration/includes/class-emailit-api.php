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
            $message = apply_filters('emailit_error_message', __('Emailit API temporarily disabled due to repeated failures.', 'emailit-integration'), 'circuit_breaker_open', array());
            return new WP_Error('circuit_breaker_open', $message);
        }

        // Check if API is temporarily disabled
        $disabled_until = get_option('emailit_api_disabled_until', 0);
        if ($disabled_until && time() < $disabled_until) {
            return new WP_Error('api_temporarily_disabled', __('Emailit API temporarily disabled due to errors.', 'emailit-integration'));
        }

        // Check rate limiting
        if (get_transient('emailit_rate_limited')) {
            $message = apply_filters('emailit_error_message', __('Emailit API rate limit in effect. Please try again later.', 'emailit-integration'), 'rate_limited', array());
            return new WP_Error('rate_limited', $message);
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

        // Debug logging (sanitized to prevent API key exposure)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Emailit] API Request URL: ' . $this->api_endpoint);
            error_log('[Emailit] API Request Args: ' . print_r($this->sanitize_debug_args($args), true));
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
            $default_message = sprintf(__('Emailit API error (%d): %s', 'emailit-integration'), $response_code, $error_message);

            // Apply filter to allow customization of error messages
            $filtered_message = apply_filters('emailit_error_message', $default_message, 'api_error', array(
                'response_code' => $response_code,
                'original_message' => $error_message,
                'parsed_response' => $parsed_response
            ));

            return new WP_Error(
                'api_error',
                $filtered_message,
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
            $file_path = null;
            $file_name = null;
            $file_type = null;

            // Extract file path and info
            if (is_string($attachment)) {
                $file_path = $attachment;
                $file_name = basename($attachment);
            } elseif (is_array($attachment) && isset($attachment['path'])) {
                $file_path = $attachment['path'];
                $file_name = isset($attachment['name']) ? $attachment['name'] : basename($attachment['path']);
                $file_type = isset($attachment['type']) ? $attachment['type'] : null;
            } else {
                continue; // Skip invalid attachment format
            }

            // Security checks
            if (!$this->is_safe_attachment($file_path, $file_name)) {
                continue; // Skip unsafe files
            }

            if (file_exists($file_path)) {
                $file_content = file_get_contents($file_path);
                if ($file_content !== false) {
                    $prepared[] = array(
                        'filename' => sanitize_file_name($file_name),
                        'content' => base64_encode($file_content),
                        'content_type' => $file_type ?: mime_content_type($file_path)
                    );
                }
            }
        }

        return $prepared;
    }

    /**
     * Check if attachment is safe to process
     */
    private function is_safe_attachment($file_path, $file_name) {
        // Resolve real path to prevent directory traversal
        $real_path = realpath($file_path);

        if (!$real_path) {
            return false; // File doesn't exist or path is invalid
        }

        // Get WordPress upload directory
        $upload_dir = wp_upload_dir();
        $allowed_base_dirs = array(
            realpath($upload_dir['basedir']),
            realpath(WP_CONTENT_DIR . '/uploads'),
            realpath(ABSPATH . 'wp-content/uploads')
        );

        // Add tmp directory for WordPress-generated attachments
        if (function_exists('sys_get_temp_dir')) {
            $allowed_base_dirs[] = realpath(sys_get_temp_dir());
        }

        // Check if file is within allowed directories
        $in_allowed_dir = false;
        foreach ($allowed_base_dirs as $allowed_dir) {
            if ($allowed_dir && strpos($real_path, $allowed_dir) === 0) {
                $in_allowed_dir = true;
                break;
            }
        }

        if (!$in_allowed_dir) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Emailit] Blocked file outside allowed directories: ' . $real_path);
            }
            return false;
        }

        // Check file size (10MB limit)
        $file_size = filesize($real_path);
        if ($file_size > 10 * 1024 * 1024) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Emailit] Blocked oversized file: ' . $real_path . ' (' . $file_size . ' bytes)');
            }
            return false;
        }

        // Check MIME type against allowlist
        $mime_type = mime_content_type($real_path);
        $allowed_types = apply_filters('emailit_allowed_attachment_types', array(
            // Images
            'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp',
            // Documents
            'application/pdf', 'text/plain', 'text/csv',
            'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            // Archives (be careful with these)
            'application/zip', 'application/x-zip-compressed'
        ));

        if (!in_array($mime_type, $allowed_types)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Emailit] Blocked file with disallowed MIME type: ' . $mime_type . ' for file: ' . $real_path);
            }
            return false;
        }

        // Check filename for dangerous extensions
        $dangerous_extensions = array('php', 'php3', 'php4', 'php5', 'phtml', 'exe', 'bat', 'com', 'cmd', 'scr', 'vbs', 'js', 'jar');
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if (in_array($file_extension, $dangerous_extensions)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Emailit] Blocked file with dangerous extension: ' . $file_extension . ' for file: ' . $file_name);
            }
            return false;
        }

        // Additional check for double extensions (file.txt.php)
        if (preg_match('/\.(php|exe|bat|com|cmd|scr|vbs|js)(\.|$)/i', $file_name)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Emailit] Blocked file with suspicious double extension: ' . $file_name);
            }
            return false;
        }

        return true;
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

        $site_name = get_bloginfo('name');
        $site_url = home_url();
        $current_time = current_time('F j, Y g:i A T');

        $test_data = array(
            'to' => $test_email ?: get_bloginfo('admin_email'),
            'subject' => sprintf(__('✅ Test Email from %s - Emailit Integration', 'emailit-integration'), $site_name),
            'message' => $this->get_html_test_email_template($site_name, $site_url, $current_time),
            'content_type' => 'text/html'
        );

        return $this->send_email($test_data);
    }

    /**
     * Get HTML template for test emails
     */
    private function get_html_test_email_template($site_name, $site_url, $current_time) {
        $logo_url = get_site_icon_url(64) ?: plugins_url('admin/assets/images/emailit-logo.png', EMAILIT_PLUGIN_FILE);

        return '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . esc_html(__('Test Email - Emailit Integration', 'emailit-integration')) . '</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; margin: 0; padding: 0; background-color: #f8f9fa;">
    <table cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #f8f9fa; padding: 20px 0;">
        <tr>
            <td align="center">
                <table cellpadding="0" cellspacing="0" border="0" width="600" style="background-color: #ffffff;">
                    <!-- Header -->
                    <tr>
                        <td style="background: #667eea; padding: 40px 20px; text-align: center;">
                            <h1 style="color: #ffffff; margin: 0 0 10px 0; font-size: 28px; font-weight: 600;">🚀 ' . esc_html__('Emailit Integration Test', 'emailit-integration') . '</h1>
                            <p style="color: #e8f4fd; margin: 0; font-size: 16px;">' . esc_html__('Your email integration is working perfectly!', 'emailit-integration') . '</p>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <!-- Success Badge -->
                            <table cellpadding="0" cellspacing="0" border="0" width="100%" style="background: #d4edda; border: 1px solid #c3e6cb; border-radius: 8px; margin: 20px 0;">
                                <tr>
                                    <td style="padding: 20px; text-align: center;">
                                        <div style="font-size: 48px; margin-bottom: 10px; color: #28a745;">✅</div>
                                        <h2 style="color: #155724; margin: 0 0 10px 0; font-size: 24px;">' . esc_html__('Test Successful', 'emailit-integration') . '</h2>
                                        <p style="color: #155724; margin: 0; font-size: 16px;">' . esc_html__('This email was sent successfully through the Emailit API', 'emailit-integration') . '</p>
                                    </td>
                                </tr>
                            </table>

                            <p style="color: #495057; font-size: 16px; line-height: 1.6; margin: 20px 0;">' . sprintf(__('Hello! This is a test email sent from <strong>%s</strong> to verify that your Emailit integration is working correctly.', 'emailit-integration'), esc_html($site_name)) . '</p>

                            <!-- Info Card -->
                            <table cellpadding="0" cellspacing="0" border="0" width="100%" style="background: #f8f9fa; border-radius: 8px; padding: 20px; margin: 25px 0;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <h3 style="margin: 0 0 20px 0; color: #495057; font-size: 18px;">' . esc_html__('Test Details', 'emailit-integration') . '</h3>

                                        <table cellpadding="0" cellspacing="0" border="0" width="100%">
                                            <tr>
                                                <td style="padding: 8px 0; border-bottom: 1px solid #e9ecef;">
                                                    <strong style="color: #495057;">' . esc_html__('Website:', 'emailit-integration') . '</strong>
                                                </td>
                                                <td style="padding: 8px 0; border-bottom: 1px solid #e9ecef; text-align: right; color: #6c757d; font-family: monospace;">
                                                    ' . esc_html($site_name) . '
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; border-bottom: 1px solid #e9ecef;">
                                                    <strong style="color: #495057;">' . esc_html__('URL:', 'emailit-integration') . '</strong>
                                                </td>
                                                <td style="padding: 8px 0; border-bottom: 1px solid #e9ecef; text-align: right; color: #6c757d; font-family: monospace;">
                                                    ' . esc_html($site_url) . '
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; border-bottom: 1px solid #e9ecef;">
                                                    <strong style="color: #495057;">' . esc_html__('Sent At:', 'emailit-integration') . '</strong>
                                                </td>
                                                <td style="padding: 8px 0; border-bottom: 1px solid #e9ecef; text-align: right; color: #6c757d; font-family: monospace;">
                                                    ' . esc_html($current_time) . '
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; border-bottom: 1px solid #e9ecef;">
                                                    <strong style="color: #495057;">' . esc_html__('Plugin Version:', 'emailit-integration') . '</strong>
                                                </td>
                                                <td style="padding: 8px 0; border-bottom: 1px solid #e9ecef; text-align: right; color: #6c757d; font-family: monospace;">
                                                    v' . esc_html(EMAILIT_VERSION) . '
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0;">
                                                    <strong style="color: #495057;">' . esc_html__('Service:', 'emailit-integration') . '</strong>
                                                </td>
                                                <td style="padding: 8px 0; text-align: right; color: #6c757d; font-family: monospace;">
                                                    Emailit API
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <p style="color: #495057; font-size: 16px; line-height: 1.6; margin: 30px 0;">🎉 ' . esc_html__('Congratulations! Your WordPress site is now successfully integrated with Emailit. All emails sent from your website will now be delivered through the Emailit service.', 'emailit-integration') . '</p>

                            <!-- CTA Button -->
                            <table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin: 30px 0;">
                                <tr>
                                    <td align="center">
                                        <a href="' . esc_url(admin_url('options-general.php?page=emailit-settings')) . '" style="display: inline-block; background: #007cba; color: #ffffff; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: 600;">' . esc_html__('View Plugin Settings', 'emailit-integration') . '</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background: #f8f9fa; padding: 30px; text-align: center; color: #6c757d; font-size: 14px;">
                            <p style="margin: 0;">' . sprintf(
                                __('This email was sent by the %s plugin. %s', 'emailit-integration'),
                                '<strong>Emailit Integration</strong>',
                                '<a href="https://emailit.com/" target="_blank" style="color: #007cba; text-decoration: none;">Learn more about Emailit</a>'
            ) . '</p>
                            <p style="margin: 10px 0 0 0; font-size: 12px; color: #adb5bd;">' . esc_html__('This is an automated test email. Please do not reply.', 'emailit-integration') . '</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
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

    /**
     * Sanitize debug arguments to prevent sensitive data exposure
     */
    private function sanitize_debug_args($args) {
        $sanitized = $args;

        // Redact Authorization header (contains API key)
        if (isset($sanitized['headers']['Authorization'])) {
            $auth_header = $sanitized['headers']['Authorization'];
            if (strpos($auth_header, 'Bearer ') === 0) {
                $sanitized['headers']['Authorization'] = 'Bearer [REDACTED]';
            } else {
                $sanitized['headers']['Authorization'] = '[REDACTED]';
            }
        }

        // Redact any other potentially sensitive headers
        $sensitive_headers = array('X-API-Key', 'X-Auth-Token', 'Authorization');
        foreach ($sensitive_headers as $header) {
            if (isset($sanitized['headers'][$header])) {
                $sanitized['headers'][$header] = '[REDACTED]';
            }
        }

        // Redact sensitive data in body if it's an array or object
        if (isset($sanitized['body'])) {
            if (is_string($sanitized['body'])) {
                // Try to decode JSON to sanitize
                $decoded = json_decode($sanitized['body'], true);
                if ($decoded !== null) {
                    $decoded = $this->redact_sensitive_data($decoded);
                    $sanitized['body'] = wp_json_encode($decoded);
                }
            } elseif (is_array($sanitized['body'])) {
                $sanitized['body'] = $this->redact_sensitive_data($sanitized['body']);
            }
        }

        return $sanitized;
    }

    /**
     * Redact sensitive data from arrays/objects
     */
    private function redact_sensitive_data($data) {
        if (!is_array($data)) {
            return $data;
        }

        $sensitive_keys = array('api_key', 'password', 'token', 'secret', 'key', 'auth', 'authorization');

        foreach ($data as $key => $value) {
            $lower_key = strtolower($key);

            // Check if key contains sensitive terms
            foreach ($sensitive_keys as $sensitive_key) {
                if (strpos($lower_key, $sensitive_key) !== false) {
                    $data[$key] = '[REDACTED]';
                    break;
                }
            }

            // Recursively process nested arrays
            if (is_array($value)) {
                $data[$key] = $this->redact_sensitive_data($value);
            }
        }

        return $data;
    }

}