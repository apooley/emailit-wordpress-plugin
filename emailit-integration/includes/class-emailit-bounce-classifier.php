<?php
/**
 * Emailit Bounce Classification Engine
 *
 * Classifies bounce events from Emailit webhooks into categories for email deliverability insights
 * and optional FluentCRM integration.
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

class Emailit_Bounce_Classifier {

    /**
     * Logger instance
     */
    private $logger;

    /**
     * Bounce classification categories
     */
    const CATEGORY_HARD_BOUNCE = 'hard_bounce';
    const CATEGORY_SOFT_BOUNCE = 'soft_bounce';
    const CATEGORY_SPAM_COMPLAINT = 'spam_complaint';
    const CATEGORY_UNSUBSCRIBE = 'unsubscribe';
    const CATEGORY_UNKNOWN = 'unknown';

    /**
     * Bounce reason patterns for classification
     */
    private $bounce_patterns = array(
        'hard_bounce' => array(
            'invalid',
            'not found',
            'does not exist',
            'no such user',
            'mailbox unavailable',
            'user unknown',
            'recipient rejected',
            'address not found',
            'mailbox not found',
            'user not found',
            'invalid recipient',
            'recipient address rejected',
            'permanent failure',
            '550',
            '554'
        ),
        'soft_bounce' => array(
            'temporary',
            'try again',
            'mailbox full',
            'quota exceeded',
            'over quota',
            'mailbox temporarily unavailable',
            'temporarily unavailable',
            'try again later',
            'service unavailable',
            'temporary failure',
            '421',
            '450',
            '451'
        ),
        'spam_complaint' => array(
            'spam',
            'complaint',
            'abuse',
            'spam complaint',
            'abuse complaint',
            'reported as spam',
            'marked as spam',
            'spam report',
            'abuse report'
        ),
        'unsubscribe' => array(
            'unsubscribe',
            'unsubscribed',
            'opt out',
            'opted out',
            'remove me',
            'stop',
            'unsubscribe request'
        )
    );

    /**
     * Constructor
     */
    public function __construct($logger = null) {
        $this->logger = $logger ?: emailit_get_component('logger');
    }

    /**
     * Classify bounce event from webhook data
     *
     * @param array $webhook_data Webhook payload from Emailit
     * @return array Classification result with category and details
     */
    public function classify_bounce($webhook_data) {
        $event_type = $this->get_event_type($webhook_data);
        $bounce_reason = $this->get_bounce_reason($webhook_data);
        $failure_reason = $this->get_failure_reason($webhook_data);
        $complaint_reason = $this->get_complaint_reason($webhook_data);

        // Log classification attempt
        $this->logger->log('Classifying bounce event', Emailit_Logger::LEVEL_DEBUG, array(
            'event_type' => $event_type,
            'bounce_reason' => $bounce_reason,
            'failure_reason' => $failure_reason,
            'complaint_reason' => $complaint_reason
        ));

        // Primary classification based on event type
        $classification = $this->classify_by_event_type($event_type);
        
        // Refine classification based on reason text
        if ($classification === self::CATEGORY_UNKNOWN || $classification === self::CATEGORY_SOFT_BOUNCE) {
            $reason_classification = $this->classify_by_reason_text($bounce_reason, $failure_reason, $complaint_reason);
            if ($reason_classification !== self::CATEGORY_UNKNOWN) {
                $classification = $reason_classification;
            }
        }

        // Get bounce category and severity
        $category = $this->get_bounce_category($classification);
        $severity = $this->get_bounce_severity($classification);

        $result = array(
            'classification' => $classification,
            'category' => $category,
            'severity' => $severity,
            'event_type' => $event_type,
            'reason' => $bounce_reason ?: $failure_reason ?: $complaint_reason,
            'confidence' => $this->calculate_confidence($classification, $event_type, $bounce_reason),
            'technical_hints' => $this->extract_technical_hints($bounce_reason, $failure_reason),
            'recommended_action' => $this->get_recommended_action($classification)
        );

        // Log classification result
        $this->logger->log('Bounce classification completed', Emailit_Logger::LEVEL_DEBUG, $result);

        return $result;
    }

    /**
     * Get event type from webhook data
     */
    private function get_event_type($webhook_data) {
        return $webhook_data['type'] ?? $webhook_data['event_type'] ?? 'unknown';
    }

    /**
     * Get bounce reason from webhook data
     */
    private function get_bounce_reason($webhook_data) {
        return $webhook_data['bounce_reason'] ?? 
               $webhook_data['object']['bounce_reason'] ?? 
               $webhook_data['object']['email']['bounce_reason'] ?? '';
    }

    /**
     * Get failure reason from webhook data
     */
    private function get_failure_reason($webhook_data) {
        return $webhook_data['failure_reason'] ?? 
               $webhook_data['object']['failure_reason'] ?? 
               $webhook_data['object']['email']['failure_reason'] ?? '';
    }

    /**
     * Get complaint reason from webhook data
     */
    private function get_complaint_reason($webhook_data) {
        return $webhook_data['complaint_reason'] ?? 
               $webhook_data['object']['complaint_reason'] ?? 
               $webhook_data['object']['email']['complaint_reason'] ?? '';
    }

    /**
     * Classify based on event type
     */
    private function classify_by_event_type($event_type) {
        $event_mapping = array(
            'email.delivery.hardfail' => self::CATEGORY_HARD_BOUNCE,
            'email.delivery.softfail' => self::CATEGORY_SOFT_BOUNCE,
            'email.delivery.bounce' => self::CATEGORY_SOFT_BOUNCE, // Default to soft, refine by reason
            'email.delivery.error' => self::CATEGORY_SOFT_BOUNCE,
            'email.complained' => self::CATEGORY_SPAM_COMPLAINT,
            'email.unsubscribed' => self::CATEGORY_UNSUBSCRIBE,
            'email.bounced' => self::CATEGORY_SOFT_BOUNCE, // Legacy support
            'email.failed' => self::CATEGORY_SOFT_BOUNCE
        );

        return $event_mapping[$event_type] ?? self::CATEGORY_UNKNOWN;
    }

    /**
     * Classify based on reason text analysis
     */
    private function classify_by_reason_text($bounce_reason, $failure_reason, $complaint_reason) {
        $combined_reason = strtolower(trim($bounce_reason . ' ' . $failure_reason . ' ' . $complaint_reason));
        
        if (empty($combined_reason)) {
            return self::CATEGORY_UNKNOWN;
        }

        // Check each category's patterns
        foreach ($this->bounce_patterns as $category => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($combined_reason, $pattern) !== false) {
                    return $category;
                }
            }
        }

        return self::CATEGORY_UNKNOWN;
    }

    /**
     * Get bounce category for FluentCRM integration
     */
    private function get_bounce_category($classification) {
        $category_mapping = array(
            self::CATEGORY_HARD_BOUNCE => 'permanent_failure',
            self::CATEGORY_SOFT_BOUNCE => 'temporary_failure',
            self::CATEGORY_SPAM_COMPLAINT => 'complaint',
            self::CATEGORY_UNSUBSCRIBE => 'unsubscribe',
            self::CATEGORY_UNKNOWN => 'unknown'
        );

        return $category_mapping[$classification] ?? 'unknown';
    }

    /**
     * Get bounce severity level
     */
    private function get_bounce_severity($classification) {
        $severity_mapping = array(
            self::CATEGORY_HARD_BOUNCE => 'high',
            self::CATEGORY_SPAM_COMPLAINT => 'high',
            self::CATEGORY_UNSUBSCRIBE => 'medium',
            self::CATEGORY_SOFT_BOUNCE => 'low',
            self::CATEGORY_UNKNOWN => 'unknown'
        );

        return $severity_mapping[$classification] ?? 'unknown';
    }

    /**
     * Calculate classification confidence (0-100)
     */
    private function calculate_confidence($classification, $event_type, $reason) {
        $confidence = 50; // Base confidence

        // Increase confidence based on event type match
        $event_confidence = array(
            'email.delivery.hardfail' => 90,
            'email.delivery.softfail' => 85,
            'email.complained' => 95,
            'email.unsubscribed' => 90
        );

        if (isset($event_confidence[$event_type])) {
            $confidence = $event_confidence[$event_type];
        }

        // Increase confidence if reason text matches patterns
        if (!empty($reason)) {
            $reason_lower = strtolower($reason);
            $pattern_matches = 0;
            $total_patterns = 0;

            if (isset($this->bounce_patterns[$classification])) {
                $total_patterns = count($this->bounce_patterns[$classification]);
                foreach ($this->bounce_patterns[$classification] as $pattern) {
                    if (strpos($reason_lower, $pattern) !== false) {
                        $pattern_matches++;
                    }
                }
            }

            if ($total_patterns > 0) {
                $pattern_confidence = ($pattern_matches / $total_patterns) * 30;
                $confidence = min(100, $confidence + $pattern_confidence);
            }
        }

        return round($confidence);
    }

    /**
     * Extract technical hints from bounce reasons
     */
    private function extract_technical_hints($bounce_reason, $failure_reason) {
        $hints = array();
        $combined_reason = strtolower($bounce_reason . ' ' . $failure_reason);

        // SMTP error codes
        if (preg_match('/\b(4\d{2}|5\d{2})\b/', $combined_reason, $matches)) {
            $hints['smtp_code'] = $matches[1];
        }

        // Emailit-specific error patterns
        if (strpos($combined_reason, 'emailit') !== false) {
            $hints['emailit_error'] = true;
        }

        return $hints;
    }

    /**
     * Get recommended action for FluentCRM (if available) or general guidance
     */
    private function get_recommended_action($classification) {
        // Check if FluentCRM is available for specific actions
        $is_fluentcrm_available = class_exists('FluentCrm\App\App');
        
        if ($is_fluentcrm_available) {
            $action_mapping = array(
                self::CATEGORY_HARD_BOUNCE => 'unsubscribe',
                self::CATEGORY_SPAM_COMPLAINT => 'unsubscribe',
                self::CATEGORY_UNSUBSCRIBE => 'unsubscribe',
                self::CATEGORY_SOFT_BOUNCE => 'retry_with_threshold',
                self::CATEGORY_UNKNOWN => 'log_for_review'
            );
        } else {
            // General guidance without FluentCRM
            $action_mapping = array(
                self::CATEGORY_HARD_BOUNCE => 'remove_from_list',
                self::CATEGORY_SPAM_COMPLAINT => 'remove_from_list',
                self::CATEGORY_UNSUBSCRIBE => 'remove_from_list',
                self::CATEGORY_SOFT_BOUNCE => 'retry_later',
                self::CATEGORY_UNKNOWN => 'investigate'
            );
        }

        return $action_mapping[$classification] ?? 'investigate';
    }

    /**
     * Get all supported bounce categories
     */
    public function get_supported_categories() {
        return array(
            self::CATEGORY_HARD_BOUNCE,
            self::CATEGORY_SOFT_BOUNCE,
            self::CATEGORY_SPAM_COMPLAINT,
            self::CATEGORY_UNSUBSCRIBE,
            self::CATEGORY_UNKNOWN
        );
    }

    /**
     * Get bounce patterns for a specific category
     */
    public function get_bounce_patterns($category = null) {
        if ($category) {
            return $this->bounce_patterns[$category] ?? array();
        }
        return $this->bounce_patterns;
    }

    /**
     * Add custom bounce pattern for a category
     */
    public function add_bounce_pattern($category, $pattern) {
        if (!isset($this->bounce_patterns[$category])) {
            $this->bounce_patterns[$category] = array();
        }
        
        if (!in_array($pattern, $this->bounce_patterns[$category])) {
            $this->bounce_patterns[$category][] = $pattern;
        }
    }

    /**
     * Get classification statistics
     */
    public function get_classification_stats($days = 30) {
        global $wpdb;
        
        $logs_table = $wpdb->prefix . 'emailit_logs';
        $start_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT 
                bounce_classification,
                COUNT(*) as count,
                AVG(CAST(JSON_EXTRACT(details, '$.bounce_confidence') AS UNSIGNED)) as avg_confidence
            FROM {$logs_table} 
            WHERE bounce_classification IS NOT NULL 
            AND created_at >= %s
            GROUP BY bounce_classification
            ORDER BY count DESC
        ", $start_date));

        $stats = array();
        foreach ($results as $result) {
            $stats[$result->bounce_classification] = array(
                'count' => (int) $result->count,
                'avg_confidence' => round((float) $result->avg_confidence, 2)
            );
        }

        return $stats;
    }

    /**
     * Get bounce classification summary for admin dashboard
     */
    public function get_bounce_summary($days = 30) {
        global $wpdb;
        
        $logs_table = $wpdb->prefix . 'emailit_logs';
        $start_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Get bounce statistics
        $bounce_stats = $wpdb->get_results($wpdb->prepare("
            SELECT 
                bounce_classification,
                bounce_category,
                bounce_severity,
                COUNT(*) as count,
                AVG(bounce_confidence) as avg_confidence
            FROM {$logs_table} 
            WHERE bounce_classification IS NOT NULL 
            AND created_at >= %s
            GROUP BY bounce_classification, bounce_category, bounce_severity
            ORDER BY count DESC
        ", $start_date));

        // Get total bounces by severity
        $severity_stats = $wpdb->get_results($wpdb->prepare("
            SELECT 
                bounce_severity,
                COUNT(*) as count
            FROM {$logs_table} 
            WHERE bounce_severity IS NOT NULL 
            AND created_at >= %s
            GROUP BY bounce_severity
            ORDER BY count DESC
        ", $start_date));

        // Get recent bounce trends (last 7 days)
        $trend_stats = $wpdb->get_results($wpdb->prepare("
            SELECT 
                DATE(created_at) as date,
                bounce_classification,
                COUNT(*) as count
            FROM {$logs_table} 
            WHERE bounce_classification IS NOT NULL 
            AND created_at >= %s
            GROUP BY DATE(created_at), bounce_classification
            ORDER BY date DESC, count DESC
        ", date('Y-m-d H:i:s', strtotime('-7 days'))));

        return array(
            'bounce_stats' => $bounce_stats,
            'severity_stats' => $severity_stats,
            'trend_stats' => $trend_stats,
            'total_bounces' => array_sum(array_column($severity_stats, 'count')),
            'period_days' => $days
        );
    }

    /**
     * Get bounce patterns for admin interface
     */
    public function get_bounce_patterns_summary() {
        $patterns = array();
        
        foreach ($this->bounce_patterns as $category => $pattern_list) {
            $patterns[$category] = array(
                'patterns' => $pattern_list,
                'count' => count($pattern_list),
                'description' => $this->get_category_description($category)
            );
        }
        
        return $patterns;
    }

    /**
     * Get category description for admin interface
     */
    private function get_category_description($category) {
        $descriptions = array(
            self::CATEGORY_HARD_BOUNCE => 'Permanent delivery failures - email address is invalid or does not exist. Remove from mailing lists.',
            self::CATEGORY_SOFT_BOUNCE => 'Temporary delivery failures - retry may succeed later. Monitor for patterns.',
            self::CATEGORY_SPAM_COMPLAINT => 'Recipient marked email as spam or abuse. Remove from mailing lists immediately.',
            self::CATEGORY_UNSUBSCRIBE => 'Recipient requested to be unsubscribed. Remove from mailing lists.',
            self::CATEGORY_UNKNOWN => 'Unable to classify bounce reason. Review manually for patterns.'
        );
        
        return $descriptions[$category] ?? 'Unknown category';
    }
}
