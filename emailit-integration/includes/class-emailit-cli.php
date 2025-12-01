<?php
/**
 * Emailit WP-CLI Commands
 *
 * Provides WP-CLI commands for managing emails, queue, logs, and webhooks.
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Only load if WP-CLI is available
if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

class Emailit_CLI {

    /**
     * Logger instance
     */
    private $logger;

    /**
     * API instance
     */
    private $api;

    /**
     * Queue instance
     */
    private $queue;

    /**
     * Admin instance
     */
    private $admin;

    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = emailit_get_component('logger');
        $this->api = emailit_get_component('api');
        $this->queue = emailit_get_component('queue');
        $this->admin = emailit_get_component('admin');
    }

    /**
     * Process email queue
     *
     * ## OPTIONS
     *
     * [--batch-size=<size>]
     * : Number of emails to process per batch
     * ---
     * default: 10
     * ---
     *
     * ## EXAMPLES
     *
     *     # Process queue with default batch size
     *     $ wp emailit queue process
     *
     *     # Process queue with custom batch size
     *     $ wp emailit queue process --batch-size=20
     *
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function queue_process($args, $assoc_args) {
        if (!$this->queue) {
            WP_CLI::error(__('Queue system is not available.', 'emailit-integration'));
            return;
        }

        $batch_size = isset($assoc_args['batch-size']) ? (int) $assoc_args['batch-size'] : 10;

        WP_CLI::log(__('Processing email queue...', 'emailit-integration'));

        $processed = 0;
        $max_iterations = 100; // Prevent infinite loops
        $iteration = 0;

        while ($iteration < $max_iterations) {
            $iteration++;
            $this->queue->process_queue();
            
            // Check if queue is empty
            $stats = $this->queue->get_stats();
            if ($stats['pending'] === 0) {
                break;
            }
            
            $processed += $stats['pending'];
            WP_CLI::log(sprintf(__('Processed batch %d, %d remaining...', 'emailit-integration'), $iteration, $stats['pending']));
        }

        $final_stats = $this->queue->get_stats();
        WP_CLI::success(sprintf(
            __('Queue processing complete. Processed: %d, Remaining: %d', 'emailit-integration'),
            $processed,
            $final_stats['pending']
        ));
    }

    /**
     * Drain email queue (process until empty)
     *
     * ## EXAMPLES
     *
     *     # Drain entire queue
     *     $ wp emailit queue drain
     *
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function queue_drain($args, $assoc_args) {
        if (!$this->queue) {
            WP_CLI::error(__('Queue system is not available.', 'emailit-integration'));
            return;
        }

        WP_CLI::log(__('Draining email queue (processing until empty)...', 'emailit-integration'));

        $total_processed = 0;
        $max_iterations = 1000; // Safety limit
        $iteration = 0;

        while ($iteration < $max_iterations) {
            $iteration++;
            
            $stats_before = $this->queue->get_stats();
            $pending_before = $stats_before['pending'];
            
            if ($pending_before === 0) {
                break;
            }

            $this->queue->process_queue();
            
            $stats_after = $this->queue->get_stats();
            $processed_this_iteration = $pending_before - $stats_after['pending'];
            $total_processed += $processed_this_iteration;

            if ($iteration % 10 === 0) {
                WP_CLI::log(sprintf(
                    __('Iteration %d: Processed %d, Remaining: %d', 'emailit-integration'),
                    $iteration,
                    $total_processed,
                    $stats_after['pending']
                ));
            }
        }

        $final_stats = $this->queue->get_stats();
        WP_CLI::success(sprintf(
            __('Queue drained. Total processed: %d, Remaining: %d', 'emailit-integration'),
            $total_processed,
            $final_stats['pending']
        ));
    }

    /**
     * Show queue statistics
     *
     * ## EXAMPLES
     *
     *     # Show queue stats
     *     $ wp emailit queue stats
     *
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function queue_stats($args, $assoc_args) {
        if (!$this->queue) {
            WP_CLI::error(__('Queue system is not available.', 'emailit-integration'));
            return;
        }

        $stats = $this->queue->get_stats();

        WP_CLI::log(__('Email Queue Statistics:', 'emailit-integration'));
        WP_CLI::log(sprintf(__('Pending: %d', 'emailit-integration'), $stats['pending']));
        WP_CLI::log(sprintf(__('Processing: %d', 'emailit-integration'), $stats['processing']));
        WP_CLI::log(sprintf(__('Completed: %d', 'emailit-integration'), $stats['completed']));
        WP_CLI::log(sprintf(__('Failed: %d', 'emailit-integration'), $stats['failed']));
    }

    /**
     * Resend failed email by log ID
     *
     * ## OPTIONS
     *
     * <id>
     * : Email log ID to resend
     *
     * ## EXAMPLES
     *
     *     # Resend email with log ID 123
     *     $ wp emailit email resend 123
     *
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function email_resend($args, $assoc_args) {
        if (empty($args[0])) {
            WP_CLI::error(__('Email log ID is required.', 'emailit-integration'));
            return;
        }

        $log_id = (int) $args[0];

        if (!$this->logger) {
            WP_CLI::error(__('Logger is not available.', 'emailit-integration'));
            return;
        }

        $log = $this->logger->get_log($log_id);
        if (!$log) {
            WP_CLI::error(sprintf(__('Email log with ID %d not found.', 'emailit-integration'), $log_id));
            return;
        }

        // Reconstruct email data from log
        $email_data = array(
            'to' => $log['to_email'],
            'subject' => $log['subject'],
            'from' => $log['from_email'],
            'message' => !empty($log['body_html']) ? $log['body_html'] : $log['body_text'],
            'content_type' => !empty($log['body_html']) ? 'text/html' : 'text/plain'
        );

        if (!empty($log['reply_to'])) {
            $email_data['reply_to'] = $log['reply_to'];
        }

        WP_CLI::log(sprintf(__('Resending email to %s...', 'emailit-integration'), $log['to_email']));

        $result = $this->api->send_email($email_data);

        if (is_wp_error($result)) {
            WP_CLI::error(sprintf(
                __('Failed to resend email: %s', 'emailit-integration'),
                $result->get_error_message()
            ));
        } else {
            WP_CLI::success(__('Email resent successfully.', 'emailit-integration'));
        }
    }

    /**
     * Resend all failed emails
     *
     * ## OPTIONS
     *
     * [--limit=<number>]
     * : Maximum number of emails to resend
     * ---
     * default: 100
     * ---
     *
     * ## EXAMPLES
     *
     *     # Resend all failed emails (max 100)
     *     $ wp emailit email resend-failed
     *
     *     # Resend up to 50 failed emails
     *     $ wp emailit email resend-failed --limit=50
     *
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function email_resend_failed($args, $assoc_args) {
        if (!$this->logger) {
            WP_CLI::error(__('Logger is not available.', 'emailit-integration'));
            return;
        }

        $limit = isset($assoc_args['limit']) ? (int) $assoc_args['limit'] : 100;

        WP_CLI::log(__('Finding failed emails...', 'emailit-integration'));

        $failed_logs = $this->logger->get_logs(array(
            'status' => 'failed',
            'per_page' => $limit,
            'orderby' => 'created_at',
            'order' => 'DESC'
        ));

        if (empty($failed_logs['logs'])) {
            WP_CLI::success(__('No failed emails found.', 'emailit-integration'));
            return;
        }

        $total = count($failed_logs['logs']);
        WP_CLI::log(sprintf(__('Found %d failed emails. Resending...', 'emailit-integration'), $total));

        $success = 0;
        $failed = 0;
        $progress = \WP_CLI\Utils\make_progress_bar(__('Resending emails', 'emailit-integration'), $total);

        foreach ($failed_logs['logs'] as $log) {
            $email_data = array(
                'to' => $log['to_email'],
                'subject' => $log['subject'],
                'from' => $log['from_email'],
                'message' => !empty($log['body_html']) ? $log['body_html'] : $log['body_text'],
                'content_type' => !empty($log['body_html']) ? 'text/html' : 'text/plain'
            );

            if (!empty($log['reply_to'])) {
                $email_data['reply_to'] = $log['reply_to'];
            }

            $result = $this->api->send_email($email_data);

            if (is_wp_error($result)) {
                $failed++;
            } else {
                $success++;
            }

            $progress->tick();
        }

        $progress->finish();

        WP_CLI::success(sprintf(
            __('Resend complete. Success: %d, Failed: %d', 'emailit-integration'),
            $success,
            $failed
        ));
    }

    /**
     * Test webhook endpoint
     *
     * ## EXAMPLES
     *
     *     # Test webhook endpoint
     *     $ wp emailit webhook test
     *
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function webhook_test($args, $assoc_args) {
        $webhook_url = rest_url('emailit/v1/webhook');
        
        WP_CLI::log(sprintf(__('Testing webhook endpoint: %s', 'emailit-integration'), $webhook_url));

        // Check if webhook secret is configured
        $webhook_secret = get_option('emailit_webhook_secret', '');
        if (empty($webhook_secret)) {
            WP_CLI::error(__('Webhook secret is not configured. Please set emailit_webhook_secret in the plugin settings before testing.', 'emailit-integration'));
            return;
        }

        // Create test payload
        $test_payload = array(
            'event_type' => 'test',
            'event_id' => 'cli-test-' . time(),
            'status' => 'test',
            'details' => array(
                'source' => 'wp-cli',
                'timestamp' => current_time('mysql')
            )
        );

        // Generate signature using the same method as Emailit_Webhook::verify_signature()
        $payload_json = wp_json_encode($test_payload);
        $timestamp = time();
        
        // Try multiple signature methods (matching webhook verification logic)
        $signatures_to_try = array();
        
        // Method 1: Just the body (original approach)
        $signatures_to_try[] = hash_hmac('sha256', $payload_json, $webhook_secret);
        
        // Method 2: timestamp + body (common approach)
        $signatures_to_try[] = hash_hmac('sha256', $timestamp . $payload_json, $webhook_secret);
        
        // Method 3: timestamp + "." + body (GitHub style)
        $signatures_to_try[] = hash_hmac('sha256', $timestamp . '.' . $payload_json, $webhook_secret);
        
        // Use the first signature method (body only) as primary, with timestamp header
        $signature = $signatures_to_try[0];

        // Prepare headers with signature and timestamp
        $headers = array(
            'Content-Type' => 'application/json',
            'X-Emailit-Signature' => $signature,
            'X-Emailit-Timestamp' => (string) $timestamp
        );

        $response = wp_remote_post($webhook_url, array(
            'body' => $payload_json,
            'headers' => $headers,
            'timeout' => 10
        ));

        if (is_wp_error($response)) {
            WP_CLI::error(sprintf(
                __('Webhook test failed: %s', 'emailit-integration'),
                $response->get_error_message()
            ));
        } else {
            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            if ($code >= 200 && $code < 300) {
                WP_CLI::success(sprintf(
                    __('Webhook test successful. Response code: %d', 'emailit-integration'),
                    $code
                ));
                if (!empty($body)) {
                    WP_CLI::log(__('Response:', 'emailit-integration'));
                    WP_CLI::log($body);
                }
            } else {
                // If signature verification failed, try other signature methods
                if ($code === 401) {
                    WP_CLI::warning(__('Signature verification failed with primary method. Trying alternative signature methods...', 'emailit-integration'));
                    
                    // Try method 2 (timestamp + body)
                    $headers['X-Emailit-Signature'] = $signatures_to_try[1];
                    $response2 = wp_remote_post($webhook_url, array(
                        'body' => $payload_json,
                        'headers' => $headers,
                        'timeout' => 10
                    ));
                    
                    if (!is_wp_error($response2)) {
                        $code2 = wp_remote_retrieve_response_code($response2);
                        if ($code2 >= 200 && $code2 < 300) {
                            WP_CLI::success(sprintf(
                                __('Webhook test successful with alternative signature method. Response code: %d', 'emailit-integration'),
                                $code2
                            ));
                            return;
                        }
                    }
                    
                    // Try method 3 (timestamp + "." + body)
                    $headers['X-Emailit-Signature'] = $signatures_to_try[2];
                    $response3 = wp_remote_post($webhook_url, array(
                        'body' => $payload_json,
                        'headers' => $headers,
                        'timeout' => 10
                    ));
                    
                    if (!is_wp_error($response3)) {
                        $code3 = wp_remote_retrieve_response_code($response3);
                        if ($code3 >= 200 && $code3 < 300) {
                            WP_CLI::success(sprintf(
                                __('Webhook test successful with alternative signature method. Response code: %d', 'emailit-integration'),
                                $code3
                            ));
                            return;
                        }
                    }
                }
                
                WP_CLI::warning(sprintf(
                    __('Webhook returned non-success code: %d', 'emailit-integration'),
                    $code
                ));
                WP_CLI::log(__('Response:', 'emailit-integration'));
                WP_CLI::log($body);
            }
        }
    }

    /**
     * Clean old logs
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Show what would be deleted without actually deleting
     *
     * ## EXAMPLES
     *
     *     # Clean old logs
     *     $ wp emailit logs clean
     *
     *     # Preview what would be cleaned
     *     $ wp emailit logs clean --dry-run
     *
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function logs_clean($args, $assoc_args) {
        if (!$this->logger) {
            WP_CLI::error(__('Logger is not available.', 'emailit-integration'));
            return;
        }

        $dry_run = isset($assoc_args['dry-run']);

        if ($dry_run) {
            WP_CLI::log(__('DRY RUN: No logs will be deleted.', 'emailit-integration'));
        }

        WP_CLI::log(__('Cleaning old logs...', 'emailit-integration'));

        $result = $this->logger->cleanup_logs();

        if ($dry_run) {
            WP_CLI::log(sprintf(
                __('Would delete %d email logs and %d webhook logs', 'emailit-integration'),
                $result['email_logs_deleted'],
                $result['webhook_logs_deleted']
            ));
        } else {
            WP_CLI::success(sprintf(
                __('Cleaned %d email logs and %d webhook logs', 'emailit-integration'),
                $result['email_logs_deleted'],
                $result['webhook_logs_deleted']
            ));
        }
    }

    /**
     * Export logs
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Export format (csv or json)
     * ---
     * default: csv
     * options:
     *   - csv
     *   - json
     * ---
     *
     * [--status=<status>]
     * : Filter by status
     *
     * [--limit=<number>]
     * : Maximum number of logs to export
     * ---
     * default: 1000
     * ---
     *
     * ## EXAMPLES
     *
     *     # Export logs as CSV
     *     $ wp emailit logs export
     *
     *     # Export failed emails as JSON
     *     $ wp emailit logs export --format=json --status=failed
     *
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function logs_export($args, $assoc_args) {
        if (!$this->logger) {
            WP_CLI::error(__('Logger is not available.', 'emailit-integration'));
            return;
        }

        $format = isset($assoc_args['format']) ? $assoc_args['format'] : 'csv';
        $status = isset($assoc_args['status']) ? $assoc_args['status'] : '';
        $limit = isset($assoc_args['limit']) ? (int) $assoc_args['limit'] : 1000;

        $args = array(
            'per_page' => $limit,
            'orderby' => 'created_at',
            'order' => 'DESC'
        );

        if (!empty($status)) {
            $args['status'] = $status;
        }

        $logs = $this->logger->get_logs($args);

        if (empty($logs['logs'])) {
            WP_CLI::warning(__('No logs found to export.', 'emailit-integration'));
            return;
        }

        if ($format === 'json') {
            echo wp_json_encode($logs['logs'], JSON_PRETTY_PRINT);
        } else {
            // CSV format
            $headers = array('ID', 'Date', 'To', 'From', 'Subject', 'Status', 'Sent At');
            echo implode(',', $headers) . "\n";

            foreach ($logs['logs'] as $log) {
                $row = array(
                    $log['id'],
                    $log['created_at'],
                    $log['to_email'],
                    $log['from_email'],
                    '"' . str_replace('"', '""', $log['subject']) . '"',
                    $log['status'],
                    $log['sent_at'] ?: ''
                );
                echo implode(',', $row) . "\n";
            }
        }
    }

    /**
     * Show email statistics
     *
     * ## OPTIONS
     *
     * [--days=<number>]
     * : Number of days to include in statistics
     * ---
     * default: 30
     * ---
     *
     * ## EXAMPLES
     *
     *     # Show stats for last 30 days
     *     $ wp emailit stats
     *
     *     # Show stats for last 7 days
     *     $ wp emailit stats --days=7
     *
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function stats($args, $assoc_args) {
        if (!$this->logger) {
            WP_CLI::error(__('Logger is not available.', 'emailit-integration'));
            return;
        }

        $days = isset($assoc_args['days']) ? (int) $assoc_args['days'] : 30;

        $stats = $this->logger->get_stats($days);

        WP_CLI::log(sprintf(__('Email Statistics (Last %d days):', 'emailit-integration'), $days));
        WP_CLI::log(sprintf(__('Total Emails: %d', 'emailit-integration'), $stats['total_sent']['value']));
        WP_CLI::log(sprintf(__('Successfully Sent: %d', 'emailit-integration'), $stats['sent']['value']));
        WP_CLI::log(sprintf(__('Failed: %d', 'emailit-integration'), $stats['failed']['value']));
        WP_CLI::log(sprintf(__('Bounced: %d', 'emailit-integration'), $stats['bounced']['value']));
        WP_CLI::log(sprintf(__('Held: %d', 'emailit-integration'), $stats['held']['value']));
        WP_CLI::log(sprintf(__('Delayed: %d', 'emailit-integration'), $stats['delayed']['value']));
        WP_CLI::log(sprintf(__('Pending: %d', 'emailit-integration'), $stats['pending']['value']));
        WP_CLI::log(sprintf(__('Success Rate: %s', 'emailit-integration'), $stats['success_rate']['value']));
    }

    /**
     * Send test email
     *
     * ## OPTIONS
     *
     * [<email>]
     * : Email address to send test to (defaults to admin email)
     *
     * ## EXAMPLES
     *
     *     # Send test email to admin
     *     $ wp emailit test-email
     *
     *     # Send test email to specific address
     *     $ wp emailit test-email user@example.com
     *
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function test_email($args, $assoc_args) {
        if (!$this->api) {
            WP_CLI::error(__('API is not available.', 'emailit-integration'));
            return;
        }

        $test_email = !empty($args[0]) ? sanitize_email($args[0]) : get_bloginfo('admin_email');

        if (!is_email($test_email)) {
            WP_CLI::error(__('Invalid email address.', 'emailit-integration'));
            return;
        }

        WP_CLI::log(sprintf(__('Sending test email to %s...', 'emailit-integration'), $test_email));

        $result = $this->api->test_connection($test_email);

        if (is_wp_error($result)) {
            WP_CLI::error(sprintf(
                __('Test email failed: %s', 'emailit-integration'),
                $result->get_error_message()
            ));
        } else {
            WP_CLI::success(__('Test email sent successfully!', 'emailit-integration'));
        }
    }
}

// Register WP-CLI commands
WP_CLI::add_command('emailit queue process', array(new Emailit_CLI(), 'queue_process'));
WP_CLI::add_command('emailit queue drain', array(new Emailit_CLI(), 'queue_drain'));
WP_CLI::add_command('emailit queue stats', array(new Emailit_CLI(), 'queue_stats'));
WP_CLI::add_command('emailit email resend', array(new Emailit_CLI(), 'email_resend'));
WP_CLI::add_command('emailit email resend-failed', array(new Emailit_CLI(), 'email_resend_failed'));
WP_CLI::add_command('emailit webhook test', array(new Emailit_CLI(), 'webhook_test'));
WP_CLI::add_command('emailit logs clean', array(new Emailit_CLI(), 'logs_clean'));
WP_CLI::add_command('emailit logs export', array(new Emailit_CLI(), 'logs_export'));
WP_CLI::add_command('emailit stats', array(new Emailit_CLI(), 'stats'));
WP_CLI::add_command('emailit test-email', array(new Emailit_CLI(), 'test_email'));

