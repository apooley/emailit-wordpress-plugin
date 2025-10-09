<?php
/**
 * Emailit MailPoet Subscriber Sync Class
 *
 * Handles synchronization of subscriber data between Emailit webhooks and MailPoet.
 * Updates subscriber status based on bounce, complaint, and delivery events.
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

class Emailit_MailPoet_Subscriber_Sync {

    /**
     * Logger instance
     */
    private $logger;

    /**
     * Bounce classifier instance
     */
    private $bounce_classifier;

    /**
     * Constructor
     */
    public function __construct($logger = null) {
        $this->logger = $logger ?: emailit_get_component('logger');
        $this->bounce_classifier = new Emailit_Bounce_Classifier($this->logger);
    }

    /**
     * Initialize subscriber synchronization
     */
    public function init() {
        // Hook into Emailit webhook events
        add_action('emailit_webhook_received', array($this, 'handle_webhook_event'), 10, 2);
        
        // Log initialization
        if ($this->logger) {
            $this->logger->log('MailPoet subscriber sync initialized', Emailit_Logger::LEVEL_INFO);
        }
    }

    /**
     * Handle webhook events for subscriber synchronization
     */
    public function handle_webhook_event($webhook_data, $event_type) {
        // Only process bounce, complaint, and delivery events
        if (!in_array($event_type, array('bounce', 'complaint', 'delivery'))) {
            return;
        }

        // Extract email address from webhook data
        $email = $this->extract_email_from_webhook($webhook_data);
        if (empty($email)) {
            if ($this->logger) {
                $this->logger->log('No email address found in webhook data', Emailit_Logger::LEVEL_WARNING, array(
                    'event_type' => $event_type,
                    'webhook_data' => $webhook_data
                ));
            }
            return;
        }

        // Find MailPoet subscriber
        $subscriber = $this->find_mailpoet_subscriber($email);
        if (!$subscriber) {
            if ($this->logger) {
                $this->logger->log('MailPoet subscriber not found for email', Emailit_Logger::LEVEL_DEBUG, array(
                    'email' => $email,
                    'event_type' => $event_type
                ));
            }
            return;
        }

        // Process the event based on type
        switch ($event_type) {
            case 'bounce':
                $this->handle_bounce_event($subscriber, $webhook_data);
                break;
            case 'complaint':
                $this->handle_complaint_event($subscriber, $webhook_data);
                break;
            case 'delivery':
                $this->handle_delivery_event($subscriber, $webhook_data);
                break;
        }
    }

    /**
     * Extract email address from webhook data
     */
    private function extract_email_from_webhook($webhook_data) {
        // Try different possible fields for email address
        $email_fields = array('email', 'recipient', 'to', 'address');
        
        foreach ($email_fields as $field) {
            if (isset($webhook_data[$field]) && is_email($webhook_data[$field])) {
                return sanitize_email($webhook_data[$field]);
            }
        }

        // Try nested email fields
        if (isset($webhook_data['data']['email']) && is_email($webhook_data['data']['email'])) {
            return sanitize_email($webhook_data['data']['email']);
        }

        if (isset($webhook_data['recipient']['email']) && is_email($webhook_data['recipient']['email'])) {
            return sanitize_email($webhook_data['recipient']['email']);
        }

        return '';
    }

    /**
     * Find MailPoet subscriber by email
     */
    private function find_mailpoet_subscriber($email) {
        try {
            if (!class_exists('MailPoet\Subscribers\SubscribersRepository')) {
                return null;
            }

            $subscribers_repo = \MailPoet\DI\ContainerWrapper::getInstance()->get(\MailPoet\Subscribers\SubscribersRepository::class);
            $subscriber = $subscribers_repo->findOneBy(array('email' => $email));

            return $subscriber;

        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->log('Error finding MailPoet subscriber', Emailit_Logger::LEVEL_ERROR, array(
                    'email' => $email,
                    'error' => $e->getMessage()
                ));
            }
            return null;
        }
    }

    /**
     * Handle bounce event
     */
    private function handle_bounce_event($subscriber, $webhook_data) {
        try {
            // Classify the bounce
            $bounce_classification = $this->bounce_classifier->classify_bounce($webhook_data);
            
            $bounce_type = $bounce_classification['type'] ?? 'unknown';
            $bounce_category = $bounce_classification['category'] ?? 'unknown';
            $bounce_severity = $bounce_classification['severity'] ?? 'medium';

            // Get bounce action setting
            $hard_bounce_action = get_option('emailit_mailpoet_hard_bounce_action', 'mark_bounced');
            $soft_bounce_threshold = get_option('emailit_mailpoet_soft_bounce_threshold', 5);

            // Handle based on bounce type
            if ($bounce_type === 'hard') {
                $this->handle_hard_bounce($subscriber, $bounce_classification, $hard_bounce_action);
            } else {
                $this->handle_soft_bounce($subscriber, $bounce_classification, $soft_bounce_threshold);
            }

            // Log the bounce handling
            if ($this->logger) {
                $this->logger->log('MailPoet subscriber bounce handled', Emailit_Logger::LEVEL_INFO, array(
                    'subscriber_id' => $subscriber->getId(),
                    'email' => $subscriber->getEmail(),
                    'bounce_type' => $bounce_type,
                    'bounce_category' => $bounce_category,
                    'action_taken' => $bounce_type === 'hard' ? $hard_bounce_action : 'tracked'
                ));
            }

        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->log('Error handling bounce event', Emailit_Logger::LEVEL_ERROR, array(
                    'subscriber_id' => $subscriber->getId(),
                    'email' => $subscriber->getEmail(),
                    'error' => $e->getMessage()
                ));
            }
        }
    }

    /**
     * Handle hard bounce
     */
    private function handle_hard_bounce($subscriber, $bounce_classification, $action) {
        switch ($action) {
            case 'mark_bounced':
                $this->mark_subscriber_bounced($subscriber, $bounce_classification);
                break;
            case 'unsubscribe':
                $this->unsubscribe_subscriber($subscriber, $bounce_classification);
                break;
            case 'track_only':
                $this->track_bounce_only($subscriber, $bounce_classification);
                break;
        }
    }

    /**
     * Handle soft bounce
     */
    private function handle_soft_bounce($subscriber, $bounce_classification, $threshold) {
        // Get current bounce count
        $current_bounces = $this->get_subscriber_bounce_count($subscriber);
        $new_bounce_count = $current_bounces + 1;

        // Update bounce count
        $this->update_subscriber_bounce_count($subscriber, $new_bounce_count);

        // Check if threshold exceeded
        if ($new_bounce_count >= $threshold) {
            $this->mark_subscriber_bounced($subscriber, $bounce_classification);
            
            if ($this->logger) {
                $this->logger->log('Subscriber marked as bounced due to soft bounce threshold', Emailit_Logger::LEVEL_INFO, array(
                    'subscriber_id' => $subscriber->getId(),
                    'email' => $subscriber->getEmail(),
                    'bounce_count' => $new_bounce_count,
                    'threshold' => $threshold
                ));
            }
        }
    }

    /**
     * Handle complaint event
     */
    private function handle_complaint_event($subscriber, $webhook_data) {
        try {
            $complaint_action = get_option('emailit_mailpoet_complaint_action', 'mark_complained');

            switch ($complaint_action) {
                case 'mark_complained':
                    $this->mark_subscriber_complained($subscriber, $webhook_data);
                    break;
                case 'unsubscribe':
                    $this->unsubscribe_subscriber($subscriber, $webhook_data);
                    break;
                case 'track_only':
                    $this->track_complaint_only($subscriber, $webhook_data);
                    break;
            }

            if ($this->logger) {
                $this->logger->log('MailPoet subscriber complaint handled', Emailit_Logger::LEVEL_INFO, array(
                    'subscriber_id' => $subscriber->getId(),
                    'email' => $subscriber->getEmail(),
                    'action_taken' => $complaint_action
                ));
            }

        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->log('Error handling complaint event', Emailit_Logger::LEVEL_ERROR, array(
                    'subscriber_id' => $subscriber->getId(),
                    'email' => $subscriber->getEmail(),
                    'error' => $e->getMessage()
                ));
            }
        }
    }

    /**
     * Handle delivery event
     */
    private function handle_delivery_event($subscriber, $webhook_data) {
        try {
            // Update last activity
            $this->update_subscriber_last_activity($subscriber);

            // Reset bounce count on successful delivery if configured
            if (get_option('emailit_mailpoet_soft_bounce_reset_on_success', 1)) {
                $this->reset_subscriber_bounce_count($subscriber);
            }

            if ($this->logger) {
                $this->logger->log('MailPoet subscriber delivery tracked', Emailit_Logger::LEVEL_DEBUG, array(
                    'subscriber_id' => $subscriber->getId(),
                    'email' => $subscriber->getEmail()
                ));
            }

        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->log('Error handling delivery event', Emailit_Logger::LEVEL_ERROR, array(
                    'subscriber_id' => $subscriber->getId(),
                    'email' => $subscriber->getEmail(),
                    'error' => $e->getMessage()
                ));
            }
        }
    }

    /**
     * Mark subscriber as bounced
     */
    private function mark_subscriber_bounced($subscriber, $bounce_data) {
        try {
            // Update subscriber status to bounced
            $subscriber->setStatus(\MailPoet\Entities\SubscriberEntity::STATUS_BOUNCED);
            
            // Add bounce metadata
            $this->add_subscriber_metadata($subscriber, 'bounce_data', $bounce_data);
            $this->add_subscriber_metadata($subscriber, 'bounced_at', current_time('mysql'));

            // Save changes
            $subscribers_repo = \MailPoet\DI\ContainerWrapper::getInstance()->get(\MailPoet\Subscribers\SubscribersRepository::class);
            $subscribers_repo->flush();

        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->log('Error marking subscriber as bounced', Emailit_Logger::LEVEL_ERROR, array(
                    'subscriber_id' => $subscriber->getId(),
                    'error' => $e->getMessage()
                ));
            }
        }
    }

    /**
     * Mark subscriber as complained
     */
    private function mark_subscriber_complained($subscriber, $complaint_data) {
        try {
            // Update subscriber status to complained
            $subscriber->setStatus(\MailPoet\Entities\SubscriberEntity::STATUS_COMPLAINED);
            
            // Add complaint metadata
            $this->add_subscriber_metadata($subscriber, 'complaint_data', $complaint_data);
            $this->add_subscriber_metadata($subscriber, 'complained_at', current_time('mysql'));

            // Save changes
            $subscribers_repo = \MailPoet\DI\ContainerWrapper::getInstance()->get(\MailPoet\Subscribers\SubscribersRepository::class);
            $subscribers_repo->flush();

        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->log('Error marking subscriber as complained', Emailit_Logger::LEVEL_ERROR, array(
                    'subscriber_id' => $subscriber->getId(),
                    'error' => $e->getMessage()
                ));
            }
        }
    }

    /**
     * Unsubscribe subscriber
     */
    private function unsubscribe_subscriber($subscriber, $reason_data) {
        try {
            // Update subscriber status to unsubscribed
            $subscriber->setStatus(\MailPoet\Entities\SubscriberEntity::STATUS_UNSUBSCRIBED);
            
            // Add unsubscribe metadata
            $this->add_subscriber_metadata($subscriber, 'unsubscribe_reason', $reason_data);
            $this->add_subscriber_metadata($subscriber, 'unsubscribed_at', current_time('mysql'));

            // Save changes
            $subscribers_repo = \MailPoet\DI\ContainerWrapper::getInstance()->get(\MailPoet\Subscribers\SubscribersRepository::class);
            $subscribers_repo->flush();

        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->log('Error unsubscribing subscriber', Emailit_Logger::LEVEL_ERROR, array(
                    'subscriber_id' => $subscriber->getId(),
                    'error' => $e->getMessage()
                ));
            }
        }
    }

    /**
     * Track bounce only (no status change)
     */
    private function track_bounce_only($subscriber, $bounce_data) {
        $this->add_subscriber_metadata($subscriber, 'bounce_tracked', $bounce_data);
        $this->add_subscriber_metadata($subscriber, 'bounce_tracked_at', current_time('mysql'));
    }

    /**
     * Track complaint only (no status change)
     */
    private function track_complaint_only($subscriber, $complaint_data) {
        $this->add_subscriber_metadata($subscriber, 'complaint_tracked', $complaint_data);
        $this->add_subscriber_metadata($subscriber, 'complaint_tracked_at', current_time('mysql'));
    }

    /**
     * Update subscriber last activity
     */
    private function update_subscriber_last_activity($subscriber) {
        try {
            $subscriber->setLastEngagementAt(new \DateTime());
            
            $subscribers_repo = \MailPoet\DI\ContainerWrapper::getInstance()->get(\MailPoet\Subscribers\SubscribersRepository::class);
            $subscribers_repo->flush();

        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->log('Error updating subscriber last activity', Emailit_Logger::LEVEL_ERROR, array(
                    'subscriber_id' => $subscriber->getId(),
                    'error' => $e->getMessage()
                ));
            }
        }
    }

    /**
     * Get subscriber bounce count
     */
    private function get_subscriber_bounce_count($subscriber) {
        $metadata = $this->get_subscriber_metadata($subscriber, 'bounce_count');
        return intval($metadata);
    }

    /**
     * Update subscriber bounce count
     */
    private function update_subscriber_bounce_count($subscriber, $count) {
        $this->add_subscriber_metadata($subscriber, 'bounce_count', $count);
        $this->add_subscriber_metadata($subscriber, 'last_bounce_at', current_time('mysql'));
    }

    /**
     * Reset subscriber bounce count
     */
    private function reset_subscriber_bounce_count($subscriber) {
        $this->add_subscriber_metadata($subscriber, 'bounce_count', 0);
        $this->add_subscriber_metadata($subscriber, 'bounce_reset_at', current_time('mysql'));
    }

    /**
     * Add metadata to subscriber
     */
    private function add_subscriber_metadata($subscriber, $key, $value) {
        try {
            // Use MailPoet's custom fields system if available
            if (class_exists('MailPoet\CustomFields\CustomFieldsRepository')) {
                $custom_fields_repo = new \MailPoet\CustomFields\CustomFieldsRepository();
                
                // Try to find existing custom field
                $custom_field = $custom_fields_repo->findOneBy(array('name' => $key));
                
                if (!$custom_field) {
                    // Create custom field if it doesn't exist
                    $custom_field = new \MailPoet\Entities\CustomFieldEntity();
                    $custom_field->setName($key);
                    $custom_field->setType('text');
                    $custom_fields_repo->persist($custom_field);
                    $custom_fields_repo->flush();
                }
                
                // Set the custom field value
                $subscriber->setCustomField($key, $value);
            } else {
                // Fallback: Store in WordPress user meta if subscriber has user ID
                if (method_exists($subscriber, 'getWpUserId') && $subscriber->getWpUserId()) {
                    update_user_meta($subscriber->getWpUserId(), 'emailit_' . $key, $value);
                }
            }
            
            if ($this->logger) {
                $this->logger->log('Subscriber metadata added', Emailit_Logger::LEVEL_DEBUG, array(
                    'subscriber_id' => $subscriber->getId(),
                    'key' => $key,
                    'value' => $value
                ));
            }

        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->log('Error adding subscriber metadata', Emailit_Logger::LEVEL_ERROR, array(
                    'subscriber_id' => $subscriber->getId(),
                    'key' => $key,
                    'error' => $e->getMessage()
                ));
            }
        }
    }

    /**
     * Get metadata from subscriber
     */
    private function get_subscriber_metadata($subscriber, $key) {
        try {
            // Use MailPoet's custom fields system if available
            if (class_exists('MailPoet\CustomFields\CustomFieldsRepository')) {
                $custom_field_value = $subscriber->getCustomField($key);
                return $custom_field_value;
            } else {
                // Fallback: Get from WordPress user meta if subscriber has user ID
                if (method_exists($subscriber, 'getWpUserId') && $subscriber->getWpUserId()) {
                    return get_user_meta($subscriber->getWpUserId(), 'emailit_' . $key, true);
                }
            }
            
            return null;

        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->log('Error getting subscriber metadata', Emailit_Logger::LEVEL_ERROR, array(
                    'subscriber_id' => $subscriber->getId(),
                    'key' => $key,
                    'error' => $e->getMessage()
                ));
            }
            return null;
        }
    }

    /**
     * Test subscriber synchronization
     */
    public function test_sync() {
        try {
            // Test finding a subscriber
            $test_email = get_option('admin_email');
            $subscriber = $this->find_mailpoet_subscriber($test_email);

            if (!$subscriber) {
                return new WP_Error('subscriber_not_found', __('Test subscriber not found in MailPoet', 'emailit-integration'));
            }

            return array(
                'success' => true,
                'message' => __('Subscriber sync test successful', 'emailit-integration'),
                'subscriber_id' => $subscriber->getId(),
                'email' => $subscriber->getEmail()
            );

        } catch (Exception $e) {
            return new WP_Error('sync_test_failed', $e->getMessage());
        }
    }
}
