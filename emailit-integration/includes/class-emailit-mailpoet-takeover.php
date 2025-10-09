<?php
/**
 * Emailit MailPoet Takeover Class
 *
 * This class implements a complete takeover of MailPoet's email sending functionality.
 * Since MailPoet doesn't provide a public API for custom sending methods, we intercept
 * their internal email sending process and route all emails through Emailit.
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

class Emailit_MailPoet_Takeover {

    /**
     * Logger instance
     */
    private $logger;

    /**
     * API handler instance
     */
    private $api;

    /**
     * Original MailPoet mailer instance
     */
    private $original_mailer = null;

    /**
     * Original phpmailer data for interception
     */
    private $original_phpmailer_data = null;

    /**
     * Constructor
     */
    public function __construct($logger = null) {
        $this->logger = $logger ?: emailit_get_component('logger');
        $this->api = emailit_get_component('api');
    }

    /**
     * Initialize MailPoet takeover
     */
    public function init() {
        // Only proceed if MailPoet integration is enabled and takeover is requested
        if (!get_option('emailit_mailpoet_integration', 0) || 
            !get_option('emailit_mailpoet_override_transactional', 1)) {
            return;
        }

        // Hook into MailPoet's email sending process
        $this->hook_into_mailpoet_sending();
        
        // Log initialization
        $this->logger->log('MailPoet takeover initialized', Emailit_Logger::LEVEL_INFO);
    }

    /**
     * Hook into MailPoet's email sending process
     */
    private function hook_into_mailpoet_sending() {
        // Since MailPoet doesn't provide public hooks for email interception,
        // we need to use alternative approaches that actually exist:
        
        // 1. Intercept MailPoet's internal email sending via global phpmailer replacement
        add_action('phpmailer_init', array($this, 'intercept_phpmailer'), 1);
        
        // 2. Hook into MailPoet's WordPress mailer replacement (this actually exists)
        add_action('plugins_loaded', array($this, 'intercept_mailpoet_wordpress_mailer'), 5);
        
        // 3. Monitor MailPoet's sending process using existing hooks (if they exist)
        if (has_action('mailpoet_link_clicked')) {
            add_action('mailpoet_link_clicked', array($this, 'monitor_mailpoet_activity'), 10, 3);
        }
        
        // 4. Use WordPress core hooks for email interception
        add_action('wp_mail', array($this, 'intercept_wp_mail'), 1, 1);
    }

    /**
     * Intercept newsletter sending
     */
    public function intercept_newsletter_sending($newsletter, $subscriber) {
        try {
            // Convert MailPoet format to Emailit format
            $email_data = $this->convert_mailpoet_to_emailit($newsletter, $subscriber);
            
            // Send via Emailit
            $result = $this->api->send_email($email_data);
            
            if (is_wp_error($result)) {
                $this->logger->log('MailPoet newsletter send failed via Emailit', Emailit_Logger::LEVEL_ERROR, array(
                    'error' => $result->get_error_message(),
                    'newsletter_id' => $newsletter['id'] ?? null,
                    'subscriber' => $subscriber
                ));
                
                // Let MailPoet handle the failure
                return false;
            } else {
                $this->logger->log('MailPoet newsletter sent successfully via Emailit', Emailit_Logger::LEVEL_INFO, array(
                    'newsletter_id' => $newsletter['id'] ?? null,
                    'subscriber' => $subscriber
                ));
                
                // Prevent MailPoet from sending the email
                return true;
            }
            
        } catch (Exception $e) {
            $this->logger->log('Exception in MailPoet newsletter interception', Emailit_Logger::LEVEL_ERROR, array(
                'message' => $e->getMessage(),
                'newsletter_id' => $newsletter['id'] ?? null,
                'subscriber' => $subscriber
            ));
            
            return false;
        }
    }

    /**
     * Intercept transactional email sending
     */
    public function intercept_transactional_sending($email_data, $subscriber) {
        try {
            // Convert to Emailit format
            $emailit_data = $this->convert_transactional_to_emailit($email_data, $subscriber);
            
            // Send via Emailit
            $result = $this->api->send_email($emailit_data);
            
            if (is_wp_error($result)) {
                $this->logger->log('MailPoet transactional email send failed via Emailit', Emailit_Logger::LEVEL_ERROR, array(
                    'error' => $result->get_error_message(),
                    'email_data' => $email_data,
                    'subscriber' => $subscriber
                ));
                
                return false;
            } else {
                $this->logger->log('MailPoet transactional email sent successfully via Emailit', Emailit_Logger::LEVEL_INFO, array(
                    'email_data' => $email_data,
                    'subscriber' => $subscriber
                ));
                
                return true;
            }
            
        } catch (Exception $e) {
            $this->logger->log('Exception in MailPoet transactional email interception', Emailit_Logger::LEVEL_ERROR, array(
                'message' => $e->getMessage(),
                'email_data' => $email_data,
                'subscriber' => $subscriber
            ));
            
            return false;
        }
    }

    /**
     * Intercept MailPoet's mailer factory
     */
    public function intercept_mailer_factory($mailer) {
        // Store original mailer for fallback
        $this->original_mailer = $mailer;
        
        // Return our custom mailer that routes through Emailit
        return new Emailit_MailPoet_Custom_Mailer($this->api, $this->logger, $mailer);
    }

    /**
     * Intercept MailPoet's WordPress mailer replacement
     */
    public function intercept_wordpress_mailer($mailer) {
        // Store original mailer
        $this->original_mailer = $mailer;
        
        // Return our custom WordPress mailer
        return new Emailit_MailPoet_WordPress_Mailer($this->api, $this->logger, $mailer);
    }

    /**
     * Convert MailPoet newsletter to Emailit format
     */
    private function convert_mailpoet_to_emailit($newsletter, $subscriber) {
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
            'from' => get_option('emailit_from_email', get_option('admin_email')),
            'from_name' => get_option('emailit_from_name', get_bloginfo('name')),
            'headers' => $this->build_headers($newsletter),
            'attachments' => array()
        );
        
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
     * Convert MailPoet transactional email to Emailit format
     */
    private function convert_transactional_to_emailit($email_data, $subscriber) {
        // Format subscriber address
        $to_address = $this->format_subscriber_address($subscriber);
        
        // Build Emailit format
        $emailit_data = array(
            'to' => $to_address,
            'subject' => $email_data['subject'] ?? '',
            'message' => $email_data['body'] ?? '',
            'content_type' => $email_data['content_type'] ?? 'text/html',
            'from' => get_option('emailit_from_email', get_option('admin_email')),
            'from_name' => get_option('emailit_from_name', get_bloginfo('name')),
            'headers' => $email_data['headers'] ?? array(),
            'attachments' => $email_data['attachments'] ?? array()
        );
        
        // Add reply-to if available
        $reply_to = get_option('emailit_reply_to', '');
        if (!empty($reply_to)) {
            $emailit_data['reply_to'] = $reply_to;
        }
        
        // Add MailPoet-specific metadata
        $emailit_data['mailpoet_transactional'] = true;
        $emailit_data['mailpoet_subscriber_id'] = $subscriber['id'] ?? null;
        
        return $emailit_data;
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
     * Build headers for the email
     */
    private function build_headers($newsletter) {
        $headers = array();
        
        // Add MailPoet-specific headers
        if (isset($newsletter['id'])) {
            $headers['X-MailPoet-Newsletter-ID'] = $newsletter['id'];
        }
        
        if (isset($newsletter['type'])) {
            $headers['X-MailPoet-Campaign-Type'] = $newsletter['type'];
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
        // Generate MailPoet unsubscribe URL
        if (isset($newsletter['id']) && is_numeric($newsletter['id'])) {
            // Validate and sanitize the newsletter ID
            $newsletter_id = intval($newsletter['id']);
            if ($newsletter_id > 0) {
                // Use WordPress's built-in URL encoding for security
                $unsubscribe_url = home_url('/?mailpoet_router&endpoint=unsubscribe&action=unsubscribe&data=' . urlencode(base64_encode($newsletter_id)));
                return $unsubscribe_url;
            }
        }
        return null;
    }

    /**
     * Intercept phpmailer initialization
     */
    public function intercept_phpmailer($phpmailer) {
        // Check if this is a MailPoet email
        if ($this->is_mailpoet_email($phpmailer)) {
            // Store the original phpmailer data
            $this->original_phpmailer_data = array(
                'to' => $phpmailer->getAllRecipientAddresses(),
                'subject' => $phpmailer->getSubject(),
                'body' => $phpmailer->getBody(),
                'headers' => $phpmailer->getCustomHeaders(),
                'content_type' => $phpmailer->ContentType
            );
            
            // Send via Emailit first
            $result = $this->send_via_emailit_from_phpmailer($phpmailer);
            
            // Only prevent original sending if Emailit was successful
            if (!is_wp_error($result) && $result) {
                // Clear recipients to prevent duplicate sending
                $phpmailer->clearAllRecipients();
                $this->logger->log('MailPoet email intercepted and sent via Emailit successfully', Emailit_Logger::LEVEL_INFO);
            } else {
                // Log the failure but allow original sending to proceed
                $this->logger->log('MailPoet email interception failed, allowing original sending', Emailit_Logger::LEVEL_WARNING, array(
                    'error' => is_wp_error($result) ? $result->get_error_message() : 'Unknown error'
                ));
            }
        }
    }

    /**
     * Check if phpmailer instance is from MailPoet
     */
    private function is_mailpoet_email($phpmailer) {
        // Check for MailPoet-specific headers
        $headers = $phpmailer->getCustomHeaders();
        if (is_array($headers)) {
            foreach ($headers as $header) {
                if (is_array($header) && isset($header[0]) && strpos($header[0], 'X-MailPoet') !== false) {
                    return true;
                }
            }
        }
        
        // Check if MailPoet is currently sending
        if (did_action('mailpoet_sending_emails_starting')) {
            return true;
        }
        
        return false;
    }

    /**
     * Send email via Emailit from phpmailer data
     */
    private function send_via_emailit_from_phpmailer($phpmailer) {
        try {
            $email_data = array(
                'to' => implode(', ', $phpmailer->getAllRecipientAddresses()),
                'subject' => $phpmailer->getSubject(),
                'message' => $phpmailer->getBody(),
                'content_type' => $phpmailer->ContentType,
                'from' => get_option('emailit_from_email', get_option('admin_email')),
                'from_name' => get_option('emailit_from_name', get_bloginfo('name')),
                'headers' => $this->convert_phpmailer_headers($phpmailer->getCustomHeaders())
            );
            
            $result = $this->api->send_email($email_data);
            
            if (is_wp_error($result)) {
                $this->logger->log('MailPoet phpmailer interception failed', Emailit_Logger::LEVEL_ERROR, array(
                    'error' => $result->get_error_message(),
                    'email_data' => $email_data
                ));
            } else {
                $this->logger->log('MailPoet phpmailer intercepted and sent via Emailit', Emailit_Logger::LEVEL_INFO, array(
                    'to' => $email_data['to'],
                    'subject' => $email_data['subject']
                ));
            }
            
        } catch (Exception $e) {
            $this->logger->log('Exception in phpmailer interception', Emailit_Logger::LEVEL_ERROR, array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Convert phpmailer headers to Emailit format
     */
    private function convert_phpmailer_headers($custom_headers) {
        $headers = array();
        
        if (is_array($custom_headers)) {
            foreach ($custom_headers as $header) {
                if (is_array($header) && count($header) >= 2) {
                    $headers[] = $header[0] . ': ' . $header[1];
                }
            }
        }
        
        return $headers;
    }


    /**
     * Intercept MailPoet's WordPress mailer replacement
     */
    public function intercept_mailpoet_wordpress_mailer() {
        // This runs on plugins_loaded with priority 5, before MailPoet's priority 10
        // We can detect if MailPoet is about to replace the WordPress mailer
        if (class_exists('MailPoet\Mailer\WordPress\WordpressMailerReplacer')) {
            $this->logger->log('MailPoet WordPress mailer replacement detected', Emailit_Logger::LEVEL_DEBUG);
            
            // Hook into MailPoet's mailer replacement process
            add_action('mailpoet_wordpress_mailer_replace', array($this, 'intercept_wordpress_mailer'), 10, 1);
        }
    }

    /**
     * Intercept wp_mail calls
     */
    public function intercept_wp_mail($atts) {
        try {
            // Check if this is a MailPoet email
            if ($this->is_mailpoet_wp_mail($atts)) {
                $this->logger->log('Intercepted MailPoet wp_mail call', Emailit_Logger::LEVEL_DEBUG, array(
                    'to' => $atts['to'] ?? '',
                    'subject' => $atts['subject'] ?? ''
                ));
                
                // Send via Emailit instead
                $result = $this->api->send_email($atts);
                
                if (is_wp_error($result)) {
                    $this->logger->log('MailPoet wp_mail interception failed', Emailit_Logger::LEVEL_ERROR, array(
                        'error' => $result->get_error_message()
                    ));
                    return false;
                }
                
                return true;
            }
        } catch (Exception $e) {
            $this->logger->log('Exception in wp_mail interception', Emailit_Logger::LEVEL_ERROR, array(
                'message' => $e->getMessage()
            ));
        }
        
        return false;
    }

    /**
     * Check if wp_mail call is from MailPoet
     */
    private function is_mailpoet_wp_mail($atts) {
        try {
            // Check for MailPoet-specific indicators
            $headers = $atts['headers'] ?? array();
            
            if (is_array($headers)) {
                foreach ($headers as $header) {
                    if (strpos($header, 'X-MailPoet') !== false) {
                        return true;
                    }
                }
            }
            
            // Check if MailPoet is currently active
            if (did_action('mailpoet_newsletter_editor_after_javascript')) {
                return true;
            }
            
            // Check for MailPoet-specific subject patterns
            $subject = $atts['subject'] ?? '';
            if (strpos($subject, 'MailPoet') !== false) {
                return true;
            }
            
        } catch (Exception $e) {
            $this->logger->log('Error checking MailPoet wp_mail', Emailit_Logger::LEVEL_ERROR, array(
                'message' => $e->getMessage()
            ));
        }
        
        return false;
    }

    /**
     * Monitor MailPoet activity via existing hooks
     */
    public function monitor_mailpoet_activity($link, $subscriber, $wpUserPreview) {
        // This hook actually exists in MailPoet
        $this->logger->log('MailPoet activity detected', Emailit_Logger::LEVEL_DEBUG, array(
            'subscriber_id' => $subscriber->getId() ?? null,
            'link_url' => $link->getUrl() ?? null
        ));
    }
}

/**
 * Custom MailPoet Mailer that routes through Emailit
 */
class Emailit_MailPoet_Custom_Mailer {
    
    private $api;
    private $logger;
    private $original_mailer;
    
    public function __construct($api, $logger, $original_mailer) {
        $this->api = $api;
        $this->logger = $logger;
        $this->original_mailer = $original_mailer;
    }
    
    public function send($newsletter, $subscriber, $extra_params = array()) {
        try {
            // Convert to Emailit format
            $email_data = $this->convert_to_emailit_format($newsletter, $subscriber, $extra_params);
            
            // Send via Emailit
            $result = $this->api->send_email($email_data);
            
            if (is_wp_error($result)) {
                $this->logger->log('Custom MailPoet mailer send failed', Emailit_Logger::LEVEL_ERROR, array(
                    'error' => $result->get_error_message(),
                    'newsletter_id' => $newsletter['id'] ?? null
                ));
                
                // Fallback to original mailer
                return $this->original_mailer->send($newsletter, $subscriber, $extra_params);
            }
            
            // Return success format expected by MailPoet
            return array('response' => true);
            
        } catch (Exception $e) {
            $this->logger->log('Exception in custom MailPoet mailer', Emailit_Logger::LEVEL_ERROR, array(
                'message' => $e->getMessage(),
                'newsletter_id' => $newsletter['id'] ?? null
            ));
            
            // Fallback to original mailer
            return $this->original_mailer->send($newsletter, $subscriber, $extra_params);
        }
    }
    
    private function convert_to_emailit_format($newsletter, $subscriber, $extra_params) {
        // Implementation similar to the main conversion method
        // This is a simplified version
        return array(
            'to' => $subscriber['email'] ?? '',
            'subject' => $newsletter['subject'] ?? '',
            'message' => $newsletter['body']['html'] ?? $newsletter['body']['text'] ?? '',
            'content_type' => !empty($newsletter['body']['html']) ? 'text/html' : 'text/plain',
            'from' => get_option('emailit_from_email', get_option('admin_email')),
            'from_name' => get_option('emailit_from_name', get_bloginfo('name'))
        );
    }
}

/**
 * Custom WordPress Mailer for MailPoet that routes through Emailit
 */
class Emailit_MailPoet_WordPress_Mailer {
    
    private $api;
    private $logger;
    private $original_mailer;
    
    public function __construct($api, $logger, $original_mailer) {
        $this->api = $api;
        $this->logger = $logger;
        $this->original_mailer = $original_mailer;
    }
    
    public function send() {
        try {
            // Get email data from the mailer
            $to = $this->get_recipients();
            $subject = $this->get_subject();
            $message = $this->get_message();
            $headers = $this->get_headers();
            
            // Convert to Emailit format
            $email_data = array(
                'to' => $to,
                'subject' => $subject,
                'message' => $message,
                'content_type' => $this->get_content_type(),
                'headers' => $headers,
                'from' => get_option('emailit_from_email', get_option('admin_email')),
                'from_name' => get_option('emailit_from_name', get_bloginfo('name'))
            );
            
            // Send via Emailit
            $result = $this->api->send_email($email_data);
            
            if (is_wp_error($result)) {
                $this->logger->log('Custom WordPress mailer send failed', Emailit_Logger::LEVEL_ERROR, array(
                    'error' => $result->get_error_message(),
                    'to' => $to,
                    'subject' => $subject
                ));
                
                // Fallback to original mailer
                return $this->original_mailer->send();
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->log('Exception in custom WordPress mailer', Emailit_Logger::LEVEL_ERROR, array(
                'message' => $e->getMessage()
            ));
            
            // Fallback to original mailer
            return $this->original_mailer->send();
        }
    }
    
    private function get_recipients() {
        // Extract recipients from the original mailer
        if (method_exists($this->original_mailer, 'getAllRecipientAddresses')) {
            $recipients = $this->original_mailer->getAllRecipientAddresses();
            return is_array($recipients) ? implode(', ', $recipients) : $recipients;
        }
        
        if (method_exists($this->original_mailer, 'getToAddresses')) {
            $recipients = $this->original_mailer->getToAddresses();
            return is_array($recipients) ? implode(', ', $recipients) : $recipients;
        }
        
        // Fallback: try to get from global phpmailer
        global $phpmailer;
        if ($phpmailer && method_exists($phpmailer, 'getAllRecipientAddresses')) {
            $recipients = $phpmailer->getAllRecipientAddresses();
            return is_array($recipients) ? implode(', ', $recipients) : $recipients;
        }
        
        return '';
    }
    
    private function get_subject() {
        // Extract subject from the original mailer
        if (method_exists($this->original_mailer, 'getSubject')) {
            return $this->original_mailer->getSubject();
        }
        
        if (method_exists($this->original_mailer, 'Subject')) {
            return $this->original_mailer->Subject;
        }
        
        // Fallback: try to get from global phpmailer
        global $phpmailer;
        if ($phpmailer && method_exists($phpmailer, 'getSubject')) {
            return $phpmailer->getSubject();
        }
        
        return '';
    }
    
    private function get_message() {
        // Extract message from the original mailer
        if (method_exists($this->original_mailer, 'getBody')) {
            return $this->original_mailer->getBody();
        }
        
        if (method_exists($this->original_mailer, 'Body')) {
            return $this->original_mailer->Body;
        }
        
        // Fallback: try to get from global phpmailer
        global $phpmailer;
        if ($phpmailer && method_exists($phpmailer, 'getBody')) {
            return $phpmailer->getBody();
        }
        
        return '';
    }
    
    private function get_headers() {
        // Extract headers from the original mailer
        $headers = array();
        
        if (method_exists($this->original_mailer, 'getCustomHeaders')) {
            $custom_headers = $this->original_mailer->getCustomHeaders();
            if (is_array($custom_headers)) {
                foreach ($custom_headers as $header) {
                    if (is_array($header) && count($header) >= 2) {
                        $headers[] = $header[0] . ': ' . $header[1];
                    }
                }
            }
        }
        
        // Add common headers
        if (method_exists($this->original_mailer, 'getFromName')) {
            $from_name = $this->original_mailer->getFromName();
            if ($from_name) {
                $headers[] = 'From: ' . $from_name;
            }
        }
        
        if (method_exists($this->original_mailer, 'getReplyToAddresses')) {
            $reply_to = $this->original_mailer->getReplyToAddresses();
            if (is_array($reply_to) && !empty($reply_to)) {
                $headers[] = 'Reply-To: ' . implode(', ', $reply_to);
            }
        }
        
        return $headers;
    }
    
    private function get_content_type() {
        // Determine content type from the original mailer
        if (method_exists($this->original_mailer, 'ContentType')) {
            return $this->original_mailer->ContentType;
        }
        
        if (method_exists($this->original_mailer, 'getContentType')) {
            return $this->original_mailer->getContentType();
        }
        
        // Check if message contains HTML
        $message = $this->get_message();
        if (strpos($message, '<html') !== false || strpos($message, '<body') !== false) {
            return 'text/html';
        }
        
        return 'text/plain';
    }
}
