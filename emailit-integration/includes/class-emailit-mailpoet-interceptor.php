<?php
/**
 * MailPoet Interceptor for Emailit
 *
 * This class intercepts MailPoet's WordPressMailer and routes emails through Emailit
 * while maintaining compatibility with MailPoet's functionality.
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

class Emailit_MailPoet_Interceptor {

    /**
     * Original MailPoet WordPressMailer instance
     */
    private $original_mailer;

    /**
     * Emailit mailer instance
     */
    private $emailit_mailer;

    /**
     * Constructor
     */
    public function __construct($original_mailer, $emailit_mailer) {
        $this->original_mailer = $original_mailer;
        $this->emailit_mailer = $emailit_mailer;
    }


    /**
     * Override the send method to route through Emailit
     */
    public function send() {
        try {
            // Prepare email data for Emailit using the original mailer's properties
            $email_data = array(
                'to' => $this->original_mailer->getToAddresses(),
                'subject' => $this->original_mailer->Subject,
                'message' => $this->original_mailer->Body,
                'headers' => $this->extract_headers(),
                'attachments' => $this->extract_attachments(),
                'content_type' => $this->original_mailer->isHTML() ? 'text/html' : 'text/plain'
            );

            // Log that we're intercepting this MailPoet email
            if ($this->emailit_mailer->logger) {
                $this->emailit_mailer->logger->log('Intercepting MailPoet email via interceptor', Emailit_Logger::LEVEL_DEBUG, array(
                    'subject' => $this->original_mailer->Subject,
                    'to' => $this->original_mailer->getToAddresses(),
                    'content_type' => $email_data['content_type']
                ));
            }

            // Check if we should send via Emailit
            if (!$this->emailit_mailer->should_send_via_emailit($email_data)) {
                // Fall back to original MailPoet mailer
                return $this->original_mailer->send();
            }

            // Send via Emailit
            $result = $this->emailit_mailer->send_email_data($email_data);

            // Return success if Emailit accepted the email
            return true;

        } catch (Exception $e) {
            // Log error and fall back to original mailer
            if ($this->emailit_mailer->logger) {
                $this->emailit_mailer->logger->log('Error in MailPoet interceptor, falling back to original mailer: ' . $e->getMessage(), Emailit_Logger::LEVEL_ERROR);
            }
            
            return $this->original_mailer->send();
        }
    }

    /**
     * Extract headers from PHPMailer
     */
    private function extract_headers() {
        $headers = array();
        
        // Add custom headers
        if (is_array($this->original_mailer->CustomHeader)) {
            foreach ($this->original_mailer->CustomHeader as $header) {
                if (is_array($header) && count($header) >= 2) {
                    $headers[] = $header[0] . ': ' . $header[1];
                }
            }
        }

        // Add standard headers
        if (!empty($this->original_mailer->From)) {
            $from_name = !empty($this->original_mailer->FromName) ? $this->original_mailer->FromName : '';
            $headers[] = 'From: ' . ($from_name ? $from_name . ' <' . $this->original_mailer->From . '>' : $this->original_mailer->From);
        }

        if (!empty($this->original_mailer->ReplyTo)) {
            $headers[] = 'Reply-To: ' . $this->original_mailer->ReplyTo;
        }

        if (!empty($this->original_mailer->ContentType)) {
            $headers[] = 'Content-Type: ' . $this->original_mailer->ContentType;
        }

        return $headers;
    }

    /**
     * Extract attachments from PHPMailer
     */
    private function extract_attachments() {
        $attachments = array();
        
        if (is_array($this->original_mailer->attachment)) {
            foreach ($this->original_mailer->attachment as $attachment) {
                if (is_array($attachment) && isset($attachment[0])) {
                    $attachments[] = $attachment[0];
                }
            }
        }

        return $attachments;
    }

    /**
     * Delegate all other method calls to the original mailer
     */
    public function __call($method, $args) {
        if (method_exists($this->original_mailer, $method)) {
            return call_user_func_array(array($this->original_mailer, $method), $args);
        }
        
        // Return null for unknown methods
        return null;
    }

    /**
     * Delegate property access to the original mailer
     */
    public function __get($property) {
        if (property_exists($this->original_mailer, $property)) {
            return $this->original_mailer->$property;
        }
        
        return null;
    }

    /**
     * Delegate property setting to the original mailer
     */
    public function __set($property, $value) {
        if (property_exists($this->original_mailer, $property)) {
            $this->original_mailer->$property = $value;
        }
    }
}
