<?php
/**
 * Emailit MailPoet Handler Class
 *
 * Handles MailPoet-specific functionality including method registration,
 * email format conversion, and integration coordination.
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

class Emailit_MailPoet_Handler {

    /**
     * Logger instance
     */
    private $logger;

    /**
     * API handler instance
     */
    private $api;

    /**
     * Constructor
     */
    public function __construct($logger = null) {
        $this->logger = $logger ?: emailit_get_component('logger');
        $this->api = emailit_get_component('api');
    }

    /**
     * Initialize the handler
     */
    public function init() {
        // Register hooks for MailPoet integration
        $this->register_hooks();

        // Log initialization
        $this->logger->log('MailPoet handler initialized', Emailit_Logger::LEVEL_INFO);
    }

    /**
     * Register WordPress hooks
     */
    private function register_hooks() {
        // MailPoet does not provide public hooks for custom sending methods
        // This is a limitation of MailPoet's current architecture
        $this->logger->log('MailPoet does not provide public hooks for custom sending methods', Emailit_Logger::LEVEL_INFO);

        // Hook into MailPoet's transactional email setting changes
        add_action('update_option_mailpoet_settings', array($this, 'handle_mailpoet_settings_change'), 10, 2);
    }

    /**
     * Register Emailit as a MailPoet sending method
     */
    public function register_emailit_method($methods) {
        if (!is_array($methods)) {
            $methods = array();
        }

        $methods['Emailit'] = array(
            'name' => 'Emailit',
            'description' => __('Send emails via Emailit API service', 'emailit-integration'),
            'class' => 'Emailit_MailPoet_Method',
            'config' => array(
                'api_key' => get_option('emailit_api_key', ''),
                'from_name' => get_option('emailit_from_name', ''),
                'from_email' => get_option('emailit_from_email', ''),
                'reply_to' => get_option('emailit_reply_to', '')
            )
        );

        return $methods;
    }

    /**
     * Handle MailPoet settings changes
     */
    public function handle_mailpoet_settings_change($old_value, $new_value) {
        // Check if transactional email setting changed
        $old_transactional = isset($old_value['send_transactional_emails']) ? $old_value['send_transactional_emails'] : false;
        $new_transactional = isset($new_value['send_transactional_emails']) ? $new_value['send_transactional_emails'] : false;

        if ($old_transactional !== $new_transactional) {
            $this->logger->log('MailPoet transactional email setting changed', Emailit_Logger::LEVEL_INFO, array(
                'old_value' => $old_transactional,
                'new_value' => $new_transactional
            ));

            // Show admin notice about the change
            add_action('admin_notices', array($this, 'transactional_setting_notice'));
        }
    }

    /**
     * Display notice about MailPoet transactional email setting change
     */
    public function transactional_setting_notice() {
        $message = __('MailPoet\'s transactional email setting has changed. This may affect how emails are routed through Emailit.', 'emailit-integration');
        printf('<div class="notice notice-info"><p>%s</p></div>', esc_html($message));
    }

    /**
     * Convert MailPoet email format to Emailit format
     */
    public function convert_mailpoet_to_emailit($newsletter, $subscriber, $extra_params = array()) {
        // Extract email content
        $email_data = array(
            'to' => $this->format_subscriber_address($subscriber),
            'subject' => $newsletter['subject'] ?? '',
            'message' => $this->extract_email_content($newsletter),
            'content_type' => $this->determine_content_type($newsletter),
            'headers' => $this->extract_headers($newsletter, $extra_params),
            'attachments' => $this->extract_attachments($newsletter)
        );

        // Add from information
        $email_data['from'] = get_option('emailit_from_email', get_option('admin_email'));
        $email_data['from_name'] = get_option('emailit_from_name', get_bloginfo('name'));

        // Add reply-to if available
        $reply_to = get_option('emailit_reply_to', '');
        if (!empty($reply_to)) {
            $email_data['reply_to'] = $reply_to;
        }

        // Add MailPoet-specific metadata
        $email_data['mailpoet_newsletter_id'] = $newsletter['id'] ?? null;
        $email_data['mailpoet_subscriber_id'] = $subscriber['id'] ?? null;
        $email_data['mailpoet_campaign_type'] = $newsletter['type'] ?? 'newsletter';

        return $email_data;
    }

    /**
     * Format subscriber address for Emailit
     */
    private function format_subscriber_address($subscriber) {
        if (is_string($subscriber)) {
            return $subscriber;
        }

        if (is_array($subscriber)) {
            $email = $subscriber['email'] ?? $subscriber['address'] ?? '';
            $name = $subscriber['first_name'] ?? '';
            $last_name = $subscriber['last_name'] ?? '';
            
            if (!empty($name) || !empty($last_name)) {
                $full_name = trim($name . ' ' . $last_name);
                return sprintf('%s <%s>', $full_name, $email);
            }
            
            return $email;
        }

        return '';
    }

    /**
     * Extract email content from MailPoet newsletter
     */
    private function extract_email_content($newsletter) {
        $body = $newsletter['body'] ?? array();
        
        // Check for HTML content
        if (isset($body['html']) && !empty($body['html'])) {
            return $body['html'];
        }
        
        // Check for text content
        if (isset($body['text']) && !empty($body['text'])) {
            return $body['text'];
        }
        
        // Fallback to empty string
        return '';
    }

    /**
     * Determine content type from MailPoet newsletter
     */
    private function determine_content_type($newsletter) {
        $body = $newsletter['body'] ?? array();
        
        if (isset($body['html']) && !empty($body['html'])) {
            return 'text/html';
        }
        
        return 'text/plain';
    }

    /**
     * Extract headers from MailPoet newsletter and extra params
     */
    private function extract_headers($newsletter, $extra_params) {
        $headers = array();

        // Add MailPoet-specific headers
        $headers['X-MailPoet-Newsletter-ID'] = $newsletter['id'] ?? '';
        $headers['X-MailPoet-Campaign-Type'] = $newsletter['type'] ?? 'newsletter';

        // Add meta information if available
        if (isset($extra_params['meta'])) {
            $meta = $extra_params['meta'];
            if (isset($meta['email_type'])) {
                $headers['X-MailPoet-Email-Type'] = $meta['email_type'];
            }
        }

        return $headers;
    }

    /**
     * Extract attachments from MailPoet newsletter
     */
    private function extract_attachments($newsletter) {
        // MailPoet doesn't typically include attachments in the newsletter array
        // This would need to be implemented if MailPoet supports attachments
        return array();
    }

    /**
     * Handle MailPoet email sending via Emailit
     */
    public function send_via_emailit($newsletter, $subscriber, $extra_params = array()) {
        try {
            // Convert MailPoet format to Emailit format
            $email_data = $this->convert_mailpoet_to_emailit($newsletter, $subscriber, $extra_params);

            // Send via Emailit API
            $result = $this->api->send_email($email_data);

            // Log the result
            if (is_wp_error($result)) {
                $this->logger->log('MailPoet email failed via Emailit', Emailit_Logger::LEVEL_ERROR, array(
                    'error' => $result->get_error_message(),
                    'newsletter_id' => $newsletter['id'] ?? null,
                    'subscriber' => $subscriber
                ));
            } else {
                $this->logger->log('MailPoet email sent successfully via Emailit', Emailit_Logger::LEVEL_INFO, array(
                    'newsletter_id' => $newsletter['id'] ?? null,
                    'subscriber' => $subscriber
                ));
            }

            return $result;

        } catch (Exception $e) {
            $this->logger->log('Exception in MailPoet email sending', Emailit_Logger::LEVEL_ERROR, array(
                'message' => $e->getMessage(),
                'newsletter_id' => $newsletter['id'] ?? null,
                'subscriber' => $subscriber
            ));

            return new WP_Error('mailpoet_send_exception', $e->getMessage());
        }
    }

    /**
     * Get MailPoet configuration for Emailit method
     */
    public function get_mailpoet_config() {
        return array(
            'method' => 'Emailit',
            'api_key' => get_option('emailit_api_key', ''),
            'from_name' => get_option('emailit_from_name', get_bloginfo('name')),
            'from_email' => get_option('emailit_from_email', get_option('admin_email')),
            'reply_to' => get_option('emailit_reply_to', ''),
            'timeout' => get_option('emailit_timeout', 30),
            'retry_attempts' => get_option('emailit_retry_attempts', 3)
        );
    }

    /**
     * Test MailPoet integration with Emailit
     */
    public function test_integration() {
        // Create a test newsletter
        $test_newsletter = array(
            'id' => 'test',
            'subject' => 'Emailit MailPoet Integration Test',
            'body' => array(
                'html' => '<p>This is a test email from Emailit MailPoet integration.</p>',
                'text' => 'This is a test email from Emailit MailPoet integration.'
            ),
            'type' => 'test'
        );

        // Create a test subscriber
        $test_subscriber = array(
            'email' => get_option('admin_email'),
            'first_name' => 'Test',
            'last_name' => 'User'
        );

        // Send test email
        $result = $this->send_via_emailit($test_newsletter, $test_subscriber);

        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'message' => $result->get_error_message()
            );
        }

        return array(
            'success' => true,
            'message' => __('Test email sent successfully via Emailit', 'emailit-integration')
        );
    }
}
