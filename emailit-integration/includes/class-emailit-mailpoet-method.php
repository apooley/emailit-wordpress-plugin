<?php
/**
 * Emailit MailPoet Method Class
 *
 * Implements MailPoet's MailerMethod interface to provide Emailit as a sending method.
 * This class handles the actual sending of emails through Emailit's API when MailPoet
 * is configured to use Emailit as the sending method.
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

class Emailit_MailPoet_Method implements \MailPoet\Mailer\Methods\MailerMethod {

    /**
     * API key for Emailit
     */
    private $api_key;

    /**
     * Sender information
     */
    private $sender;

    /**
     * Reply-to information
     */
    private $reply_to;

    /**
     * Error mapper instance
     */
    private $error_mapper;

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
    public function __construct($api_key, $sender, $reply_to, $error_mapper) {
        $this->api_key = $api_key;
        $this->sender = $sender;
        $this->reply_to = $reply_to;
        $this->error_mapper = $error_mapper;
        
        // Get logger and API instances
        $this->logger = emailit_get_component('logger');
        $this->api = emailit_get_component('api');
    }

    /**
     * Send email via Emailit API
     * 
     * @param array $newsletter Newsletter data from MailPoet
     * @param array $subscriber Subscriber data from MailPoet
     * @param array $extra_params Extra parameters
     * @return array Result array with 'response' and optional 'error' keys
     */
    public function send(array $newsletter, array $subscriber, array $extra_params = []): array {
        try {
            // Validate API key
            if (empty($this->api_key)) {
                $error = new \MailPoet\Mailer\MailerError(
                    \MailPoet\Mailer\MailerError::OPERATION_SEND,
                    \MailPoet\Mailer\MailerError::LEVEL_HARD,
                    __('Emailit API key is not configured', 'emailit-integration')
                );
                return \MailPoet\Mailer\Mailer::formatMailerErrorResult($error);
            }

            // Convert MailPoet format to Emailit format
            $email_data = $this->convert_to_emailit_format($newsletter, $subscriber, $extra_params);

            // Send via Emailit API
            $result = $this->api->send_email($email_data);

            // Handle the result
            if (is_wp_error($result)) {
                $error = $this->error_mapper->map_error($result);
                return \MailPoet\Mailer\Mailer::formatMailerErrorResult($error);
            }

            // Log successful send
            $this->logger->log('Email sent successfully via Emailit MailPoet method', Emailit_Logger::LEVEL_INFO, array(
                'newsletter_id' => $newsletter['id'] ?? null,
                'subscriber_email' => $subscriber['email'] ?? $subscriber['address'] ?? 'unknown'
            ));

            return \MailPoet\Mailer\Mailer::formatMailerSendSuccessResult();

        } catch (Exception $e) {
            $this->logger->log('Exception in Emailit MailPoet method', Emailit_Logger::LEVEL_ERROR, array(
                'message' => $e->getMessage(),
                'newsletter_id' => $newsletter['id'] ?? null,
                'subscriber' => $subscriber
            ));

            $error = new \MailPoet\Mailer\MailerError(
                \MailPoet\Mailer\MailerError::OPERATION_SEND,
                \MailPoet\Mailer\MailerError::LEVEL_HARD,
                $e->getMessage()
            );
            return \MailPoet\Mailer\Mailer::formatMailerErrorResult($error);
        }
    }

    /**
     * Convert MailPoet newsletter format to Emailit format
     */
    private function convert_to_emailit_format($newsletter, $subscriber, $extra_params) {
        // Extract email content
        $body = $newsletter['body'] ?? array();
        $html_content = $body['html'] ?? '';
        $text_content = $body['text'] ?? '';

        // Determine content type and message
        $content_type = 'text/plain';
        $message = $text_content;
        
        if (!empty($html_content)) {
            $content_type = 'text/html';
            $message = $html_content;
        }

        // Format subscriber address
        $to_address = $this->format_subscriber_address($subscriber);

        // Build email data
        $email_data = array(
            'to' => $to_address,
            'subject' => $newsletter['subject'] ?? '',
            'message' => $message,
            'content_type' => $content_type,
            'from' => $this->sender['from_email'],
            'from_name' => $this->sender['from_name'],
            'headers' => $this->build_headers($newsletter, $extra_params),
            'attachments' => array()
        );

        // Add reply-to if available
        if (!empty($this->reply_to['reply_to_email'])) {
            $email_data['reply_to'] = $this->reply_to['reply_to_email'];
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
        $email = $subscriber['email'] ?? $subscriber['address'] ?? '';
        
        // Check if subscriber has name information
        $first_name = $subscriber['first_name'] ?? '';
        $last_name = $subscriber['last_name'] ?? '';
        $full_name = $subscriber['full_name'] ?? '';
        
        if (!empty($full_name)) {
            return sprintf('%s <%s>', $full_name, $email);
        } elseif (!empty($first_name) || !empty($last_name)) {
            $name = trim($first_name . ' ' . $last_name);
            return sprintf('%s <%s>', $name, $email);
        }
        
        return $email;
    }

    /**
     * Build headers for the email
     */
    private function build_headers($newsletter, $extra_params) {
        $headers = array();

        // Add MailPoet-specific headers
        if (isset($newsletter['id'])) {
            $headers['X-MailPoet-Newsletter-ID'] = $newsletter['id'];
        }

        if (isset($newsletter['type'])) {
            $headers['X-MailPoet-Campaign-Type'] = $newsletter['type'];
        }

        // Add meta information if available
        if (isset($extra_params['meta'])) {
            $meta = $extra_params['meta'];
            if (isset($meta['email_type'])) {
                $headers['X-MailPoet-Email-Type'] = $meta['email_type'];
            }
        }

        // Add List-Unsubscribe header if this is a newsletter
        if (isset($newsletter['type']) && $newsletter['type'] === 'newsletter') {
            $unsubscribe_url = $this->get_unsubscribe_url($newsletter);
            if ($unsubscribe_url) {
                $headers['List-Unsubscribe'] = '<' . $unsubscribe_url . '>';
            }
        }

        return $headers;
    }

    /**
     * Get unsubscribe URL for the newsletter
     */
    private function get_unsubscribe_url($newsletter) {
        // This would need to be implemented based on MailPoet's unsubscribe URL structure
        // For now, return null as this is not critical for basic functionality
        return null;
    }

    /**
     * Get the method name
     */
    public function get_method_name() {
        return 'Emailit';
    }

    /**
     * Get the method description
     */
    public function get_method_description() {
        return __('Send emails via Emailit API service', 'emailit-integration');
    }

    /**
     * Check if the method is properly configured
     */
    public function is_configured() {
        return !empty($this->api_key) && 
               !empty($this->sender['from_email']) && 
               is_email($this->sender['from_email']);
    }

    /**
     * Get configuration status
     */
    public function get_configuration_status() {
        $status = array(
            'api_key_configured' => !empty($this->api_key),
            'sender_configured' => !empty($this->sender['from_email']) && is_email($this->sender['from_email']),
            'reply_to_configured' => !empty($this->reply_to['reply_to_email']) && is_email($this->reply_to['reply_to_email'])
        );

        $status['fully_configured'] = $status['api_key_configured'] && $status['sender_configured'];

        return $status;
    }

    /**
     * Test the sending method
     */
    public function test_sending() {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', __('Emailit method is not properly configured', 'emailit-integration'));
        }

        // Create a test email
        $test_newsletter = array(
            'id' => 'test',
            'subject' => 'Emailit MailPoet Method Test',
            'body' => array(
                'html' => '<p>This is a test email from Emailit MailPoet method.</p>',
                'text' => 'This is a test email from Emailit MailPoet method.'
            ),
            'type' => 'test'
        );

        $test_subscriber = array(
            'email' => get_option('admin_email'),
            'first_name' => 'Test',
            'last_name' => 'User'
        );

        // Send test email
        $result = $this->send($test_newsletter, $test_subscriber);

        if (isset($result['response']) && $result['response'] === true) {
            return array(
                'success' => true,
                'message' => __('Test email sent successfully', 'emailit-integration')
            );
        } else {
            $error_message = isset($result['error']) ? $result['error']->getMessage() : __('Unknown error', 'emailit-integration');
            return new WP_Error('test_failed', $error_message);
        }
    }
}
