<?php
/**
 * Emailit MailPoet Error Mapper Class
 *
 * Maps Emailit API errors to MailPoet's error format for proper error handling
 * and user feedback within MailPoet's interface.
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

class Emailit_MailPoet_Error_Mapper {

    /**
     * Logger instance
     */
    private $logger;

    /**
     * Constructor
     */
    public function __construct($logger = null) {
        $this->logger = $logger ?: emailit_get_component('logger');
    }

    /**
     * Map Emailit error to MailPoet error format
     * 
     * @param WP_Error $emailit_error The error from Emailit API
     * @return MailPoet\Mailer\MailerError Mapped MailPoet error
     */
    public function map_error($emailit_error) {
        if (!is_wp_error($emailit_error)) {
            return $this->create_unknown_error(__('Unknown error occurred', 'emailit-integration'));
        }

        $error_code = $emailit_error->get_error_code();
        $error_message = $emailit_error->get_error_message();
        $error_data = $emailit_error->get_error_data();

        // Log the original error for debugging
        $this->logger->log('Mapping Emailit error to MailPoet format', Emailit_Logger::LEVEL_DEBUG, array(
            'code' => $error_code,
            'message' => $error_message,
            'data' => $error_data
        ));

        // Map based on error code
        switch ($error_code) {
            case 'api_key_invalid':
            case 'api_key_missing':
                return $this->create_hard_error(
                    \MailPoet\Mailer\MailerError::OPERATION_SEND,
                    __('Invalid or missing Emailit API key', 'emailit-integration')
                );

            case 'rate_limit_exceeded':
                return $this->create_soft_error(
                    \MailPoet\Mailer\MailerError::OPERATION_SEND,
                    __('Emailit rate limit exceeded. Please try again later.', 'emailit-integration')
                );

            case 'quota_exceeded':
                return $this->create_hard_error(
                    \MailPoet\Mailer\MailerError::OPERATION_SEND,
                    __('Emailit quota exceeded. Please upgrade your plan.', 'emailit-integration')
                );

            case 'invalid_recipient':
            case 'bounced_email':
                return $this->create_hard_error(
                    \MailPoet\Mailer\MailerError::OPERATION_SEND,
                    __('Invalid recipient email address', 'emailit-integration')
                );

            case 'blocked_email':
                return $this->create_hard_error(
                    \MailPoet\Mailer\MailerError::OPERATION_SEND,
                    __('Recipient email is blocked', 'emailit-integration')
                );

            case 'spam_detected':
                return $this->create_hard_error(
                    \MailPoet\Mailer\MailerError::OPERATION_SEND,
                    __('Email content flagged as spam', 'emailit-integration')
                );

            case 'network_error':
            case 'timeout':
                return $this->create_soft_error(
                    \MailPoet\Mailer\MailerError::OPERATION_SEND,
                    __('Network error occurred. Please try again.', 'emailit-integration')
                );

            case 'server_error':
            case 'service_unavailable':
                return $this->create_soft_error(
                    \MailPoet\Mailer\MailerError::OPERATION_SEND,
                    __('Emailit service temporarily unavailable', 'emailit-integration')
                );

            case 'validation_error':
                return $this->create_hard_error(
                    \MailPoet\Mailer\MailerError::OPERATION_SEND,
                    __('Email validation failed: ' . $error_message, 'emailit-integration')
                );

            case 'authentication_failed':
                return $this->create_hard_error(
                    \MailPoet\Mailer\MailerError::OPERATION_SEND,
                    __('Emailit authentication failed', 'emailit-integration')
                );

            case 'account_suspended':
                return $this->create_hard_error(
                    \MailPoet\Mailer\MailerError::OPERATION_SEND,
                    __('Emailit account is suspended', 'emailit-integration')
                );

            case 'domain_not_verified':
                return $this->create_hard_error(
                    \MailPoet\Mailer\MailerError::OPERATION_SEND,
                    __('Sender domain not verified in Emailit', 'emailit-integration')
                );

            case 'template_error':
                return $this->create_hard_error(
                    \MailPoet\Mailer\MailerError::OPERATION_SEND,
                    __('Email template error: ' . $error_message, 'emailit-integration')
                );

            default:
                // For unknown errors, try to determine severity based on message content
                return $this->map_unknown_error($error_message, $error_data);
        }
    }

    /**
     * Map unknown errors by analyzing the error message
     */
    private function map_unknown_error($message, $data = null) {
        $message_lower = strtolower($message);

        // Check for hard errors (permanent failures)
        $hard_error_indicators = array(
            'invalid',
            'blocked',
            'bounced',
            'spam',
            'suspended',
            'quota',
            'limit exceeded',
            'authentication',
            'unauthorized',
            'forbidden'
        );

        foreach ($hard_error_indicators as $indicator) {
            if (strpos($message_lower, $indicator) !== false) {
                return $this->create_hard_error(
                    \MailPoet\Mailer\MailerError::OPERATION_SEND,
                    $message
                );
            }
        }

        // Check for soft errors (temporary failures)
        $soft_error_indicators = array(
            'timeout',
            'network',
            'connection',
            'temporary',
            'retry',
            'service unavailable',
            'server error'
        );

        foreach ($soft_error_indicators as $indicator) {
            if (strpos($message_lower, $indicator) !== false) {
                return $this->create_soft_error(
                    \MailPoet\Mailer\MailerError::OPERATION_SEND,
                    $message
                );
            }
        }

        // Default to soft error for unknown cases
        return $this->create_soft_error(
            \MailPoet\Mailer\MailerError::OPERATION_SEND,
            $message
        );
    }

    /**
     * Create a hard error (permanent failure)
     */
    private function create_hard_error($operation, $message) {
        return new \MailPoet\Mailer\MailerError(
            $operation,
            \MailPoet\Mailer\MailerError::LEVEL_HARD,
            $message
        );
    }

    /**
     * Create a soft error (temporary failure)
     */
    private function create_soft_error($operation, $message) {
        return new \MailPoet\Mailer\MailerError(
            $operation,
            \MailPoet\Mailer\MailerError::LEVEL_SOFT,
            $message
        );
    }

    /**
     * Create an unknown error
     */
    private function create_unknown_error($message) {
        return new \MailPoet\Mailer\MailerError(
            \MailPoet\Mailer\MailerError::OPERATION_SEND,
            \MailPoet\Mailer\MailerError::LEVEL_SOFT,
            $message
        );
    }

    /**
     * Get error severity from HTTP status code
     */
    private function get_error_severity_from_status($status_code) {
        // 4xx errors are typically hard errors (client errors)
        if ($status_code >= 400 && $status_code < 500) {
            return \MailPoet\Mailer\MailerError::LEVEL_HARD;
        }

        // 5xx errors are typically soft errors (server errors)
        if ($status_code >= 500) {
            return \MailPoet\Mailer\MailerError::LEVEL_SOFT;
        }

        // Default to soft error
        return \MailPoet\Mailer\MailerError::LEVEL_SOFT;
    }

    /**
     * Get user-friendly error message
     */
    public function get_user_friendly_message($error_code, $original_message = '') {
        $messages = array(
            'api_key_invalid' => __('Please check your Emailit API key in the settings.', 'emailit-integration'),
            'rate_limit_exceeded' => __('You have exceeded your email sending rate limit. Please wait before sending more emails.', 'emailit-integration'),
            'quota_exceeded' => __('You have reached your email quota limit. Please upgrade your Emailit plan.', 'emailit-integration'),
            'invalid_recipient' => __('One or more recipient email addresses are invalid.', 'emailit-integration'),
            'blocked_email' => __('One or more recipient email addresses are blocked.', 'emailit-integration'),
            'spam_detected' => __('Your email content has been flagged as spam. Please review and modify your content.', 'emailit-integration'),
            'network_error' => __('A network error occurred while sending the email. Please try again.', 'emailit-integration'),
            'server_error' => __('Emailit service is temporarily unavailable. Please try again later.', 'emailit-integration'),
            'authentication_failed' => __('Authentication with Emailit failed. Please check your API credentials.', 'emailit-integration'),
            'account_suspended' => __('Your Emailit account has been suspended. Please contact support.', 'emailit-integration'),
            'domain_not_verified' => __('Your sender domain is not verified in Emailit. Please verify your domain.', 'emailit-integration')
        );

        if (isset($messages[$error_code])) {
            return $messages[$error_code];
        }

        // Return original message if no user-friendly version available
        return !empty($original_message) ? $original_message : __('An error occurred while sending the email.', 'emailit-integration');
    }

    /**
     * Check if error is retryable
     */
    public function is_retryable_error($error_code) {
        $retryable_errors = array(
            'rate_limit_exceeded',
            'network_error',
            'timeout',
            'server_error',
            'service_unavailable'
        );

        return in_array($error_code, $retryable_errors);
    }

    /**
     * Get retry delay for error
     */
    public function get_retry_delay($error_code) {
        $delays = array(
            'rate_limit_exceeded' => 300, // 5 minutes
            'network_error' => 60,        // 1 minute
            'timeout' => 30,              // 30 seconds
            'server_error' => 120,        // 2 minutes
            'service_unavailable' => 300  // 5 minutes
        );

        return isset($delays[$error_code]) ? $delays[$error_code] : 60; // Default 1 minute
    }
}
