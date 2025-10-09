<?php
/**
 * MailPoet Integration Test Script
 *
 * This script tests the MailPoet integration functionality.
 * Run this from the WordPress admin or via WP-CLI.
 */

// Security check
if (!defined('ABSPATH')) {
    // If running from command line, load WordPress
    if (php_sapi_name() === 'cli') {
        $wp_load_path = dirname(__FILE__) . '/../../../../wp-load.php';
        if (file_exists($wp_load_path)) {
            require_once $wp_load_path;
        } else {
            die('WordPress not found. Please run this script from the WordPress root directory.');
        }
    } else {
        exit('Direct access not allowed');
    }
}

class Emailit_MailPoet_Integration_Test {

    private $logger;
    private $results = array();

    public function __construct() {
        $this->logger = emailit_get_component('logger');
    }

    /**
     * Run all integration tests
     */
    public function run_all_tests() {
        $this->results = array();
        
        echo "Starting MailPoet Integration Tests...\n\n";
        
        // Test 1: MailPoet Detection
        $this->test_mailpoet_detection();
        
        // Test 2: Integration Initialization
        $this->test_integration_initialization();
        
        // Test 3: Hook Priority
        $this->test_hook_priority();
        
        // Test 4: Conflict Prevention
        $this->test_conflict_prevention();
        
        // Test 5: Bounce Synchronization
        $this->test_bounce_synchronization();
        
        // Test 6: Settings Integration
        $this->test_settings_integration();
        
        // Test 7: Error Handling
        $this->test_error_handling();
        
        // Display results
        $this->display_results();
        
        return $this->results;
    }

    /**
     * Test MailPoet detection
     */
    private function test_mailpoet_detection() {
        echo "Test 1: MailPoet Detection\n";
        echo "========================\n";
        
        $test_name = 'MailPoet Detection';
        $passed = false;
        $message = '';
        
        try {
            // Check if MailPoet classes exist
            $mailpoet_available = class_exists('MailPoet\Mailer\MailerFactory');
            
            if ($mailpoet_available) {
                // Get MailPoet version
                $version = $this->get_mailpoet_version();
                $version_compatible = version_compare($version, '5.0.0', '>=');
                
                if ($version_compatible) {
                    $passed = true;
                    $message = "MailPoet {$version} detected and compatible";
                } else {
                    $message = "MailPoet {$version} detected but version too old (requires 5.0+)";
                }
            } else {
                $message = "MailPoet not installed or not compatible";
            }
            
        } catch (Exception $e) {
            $message = "Error detecting MailPoet: " . $e->getMessage();
        }
        
        $this->record_test_result($test_name, $passed, $message);
        echo ($passed ? "✓ PASS" : "✗ FAIL") . ": {$message}\n\n";
    }

    /**
     * Test integration initialization
     */
    private function test_integration_initialization() {
        echo "Test 2: Integration Initialization\n";
        echo "==================================\n";
        
        $test_name = 'Integration Initialization';
        $passed = false;
        $message = '';
        
        try {
            // Check if integration class exists
            if (class_exists('Emailit_MailPoet_Integration')) {
                $integration = new Emailit_MailPoet_Integration($this->logger);
                
                if ($integration->is_available()) {
                    $passed = true;
                    $message = "MailPoet integration initialized successfully";
                } else {
                    $message = "MailPoet integration not available";
                }
            } else {
                $message = "MailPoet integration class not found";
            }
            
        } catch (Exception $e) {
            $message = "Error initializing integration: " . $e->getMessage();
        }
        
        $this->record_test_result($test_name, $passed, $message);
        echo ($passed ? "✓ PASS" : "✗ FAIL") . ": {$message}\n\n";
    }

    /**
     * Test hook priority
     */
    private function test_hook_priority() {
        echo "Test 3: Hook Priority\n";
        echo "====================\n";
        
        $test_name = 'Hook Priority';
        $passed = false;
        $message = '';
        
        try {
            // Check if Emailit hooks are registered with correct priority
            global $wp_filter;
            
            $pre_wp_mail_hooks = isset($wp_filter['pre_wp_mail']) ? $wp_filter['pre_wp_mail'] : null;
            
            if ($pre_wp_mail_hooks) {
                $emailit_priority = null;
                $mailpoet_priority = null;
                
                foreach ($pre_wp_mail_hooks->callbacks as $priority => $callbacks) {
                    foreach ($callbacks as $callback) {
                        if (is_array($callback['function']) && 
                            is_object($callback['function'][0]) && 
                            get_class($callback['function'][0]) === 'Emailit_Mailer') {
                            $emailit_priority = $priority;
                        }
                        
                        if (strpos(print_r($callback, true), 'MailPoet') !== false) {
                            $mailpoet_priority = $priority;
                        }
                    }
                }
                
                if ($emailit_priority !== null) {
                    if ($mailpoet_priority === null || $emailit_priority < $mailpoet_priority) {
                        $passed = true;
                        $message = "Emailit hook priority ({$emailit_priority}) is correct";
                    } else {
                        $message = "Emailit hook priority ({$emailit_priority}) may conflict with MailPoet ({$mailpoet_priority})";
                    }
                } else {
                    $message = "Emailit pre_wp_mail hook not found";
                }
            } else {
                $message = "pre_wp_mail filter not found";
            }
            
        } catch (Exception $e) {
            $message = "Error checking hook priority: " . $e->getMessage();
        }
        
        $this->record_test_result($test_name, $passed, $message);
        echo ($passed ? "✓ PASS" : "✗ FAIL") . ": {$message}\n\n";
    }

    /**
     * Test conflict prevention
     */
    private function test_conflict_prevention() {
        echo "Test 4: Conflict Prevention\n";
        echo "===========================\n";
        
        $test_name = 'Conflict Prevention';
        $passed = false;
        $message = '';
        
        try {
            // Check if conflict detection is working
            $integration_enabled = get_option('emailit_mailpoet_integration', 0);
            $override_transactional = get_option('emailit_mailpoet_override_transactional', 1);
            
            if ($integration_enabled) {
                // Check if MailPoet transactional setting is being handled
                $mailpoet_transactional = $this->get_mailpoet_transactional_setting();
                
                if ($override_transactional) {
                    $passed = true;
                    $message = "Conflict prevention active - Emailit will override MailPoet transactional emails";
                } else {
                    $message = "Transactional override disabled - potential conflict with MailPoet";
                }
            } else {
                $passed = true;
                $message = "Integration disabled - no conflicts expected";
            }
            
        } catch (Exception $e) {
            $message = "Error checking conflict prevention: " . $e->getMessage();
        }
        
        $this->record_test_result($test_name, $passed, $message);
        echo ($passed ? "✓ PASS" : "✗ FAIL") . ": {$message}\n\n";
    }

    /**
     * Test bounce synchronization
     */
    private function test_bounce_synchronization() {
        echo "Test 5: Bounce Synchronization\n";
        echo "=============================\n";
        
        $test_name = 'Bounce Synchronization';
        $passed = false;
        $message = '';
        
        try {
            // Check if bounce sync is configured
            $bounce_sync_enabled = get_option('emailit_mailpoet_sync_bounces', 1);
            
            if ($bounce_sync_enabled) {
                // Check if subscriber sync class exists
                if (class_exists('Emailit_MailPoet_Subscriber_Sync')) {
                    $subscriber_sync = new Emailit_MailPoet_Subscriber_Sync($this->logger);
                    
                    // Test finding a subscriber
                    $test_result = $subscriber_sync->test_sync();
                    
                    if (is_wp_error($test_result)) {
                        $message = "Bounce sync configured but test failed: " . $test_result->get_error_message();
                    } else {
                        $passed = true;
                        $message = "Bounce synchronization configured and working";
                    }
                } else {
                    $message = "Subscriber sync class not found";
                }
            } else {
                $passed = true;
                $message = "Bounce synchronization disabled";
            }
            
        } catch (Exception $e) {
            $message = "Error testing bounce synchronization: " . $e->getMessage();
        }
        
        $this->record_test_result($test_name, $passed, $message);
        echo ($passed ? "✓ PASS" : "✗ FAIL") . ": {$message}\n\n";
    }

    /**
     * Test settings integration
     */
    private function test_settings_integration() {
        echo "Test 6: Settings Integration\n";
        echo "===========================\n";
        
        $test_name = 'Settings Integration';
        $passed = false;
        $message = '';
        
        try {
            // Check if MailPoet settings are registered
            $settings_registered = true;
            $required_settings = array(
                'emailit_mailpoet_integration',
                'emailit_mailpoet_override_transactional',
                'emailit_mailpoet_sync_bounces',
                'emailit_mailpoet_sync_engagement',
                'emailit_mailpoet_hard_bounce_action',
                'emailit_mailpoet_soft_bounce_threshold',
                'emailit_mailpoet_complaint_action'
            );
            
            foreach ($required_settings as $setting) {
                if (get_option($setting) === false) {
                    $settings_registered = false;
                    break;
                }
            }
            
            if ($settings_registered) {
                $passed = true;
                $message = "All MailPoet settings registered and accessible";
            } else {
                $message = "Some MailPoet settings not registered";
            }
            
        } catch (Exception $e) {
            $message = "Error testing settings integration: " . $e->getMessage();
        }
        
        $this->record_test_result($test_name, $passed, $message);
        echo ($passed ? "✓ PASS" : "✗ FAIL") . ": {$message}\n\n";
    }

    /**
     * Test error handling
     */
    private function test_error_handling() {
        echo "Test 7: Error Handling\n";
        echo "======================\n";
        
        $test_name = 'Error Handling';
        $passed = false;
        $message = '';
        
        try {
            // Check if error mapper exists
            if (class_exists('Emailit_MailPoet_Error_Mapper')) {
                $error_mapper = new Emailit_MailPoet_Error_Mapper($this->logger);
                
                // Test error mapping
                $test_error = new WP_Error('test_error', 'Test error message');
                $mapped_error = $error_mapper->map_error($test_error);
                
                if ($mapped_error instanceof \MailPoet\Mailer\MailerError) {
                    $passed = true;
                    $message = "Error mapping working correctly";
                } else {
                    $message = "Error mapping not working correctly";
                }
            } else {
                $message = "Error mapper class not found";
            }
            
        } catch (Exception $e) {
            $message = "Error testing error handling: " . $e->getMessage();
        }
        
        $this->record_test_result($test_name, $passed, $message);
        echo ($passed ? "✓ PASS" : "✗ FAIL") . ": {$message}\n\n";
    }

    /**
     * Get MailPoet version
     */
    private function get_mailpoet_version() {
        if (function_exists('get_plugin_data')) {
            $plugin_file = WP_PLUGIN_DIR . '/mailpoet/mailpoet.php';
            if (file_exists($plugin_file)) {
                $plugin_data = get_plugin_data($plugin_file);
                return $plugin_data['Version'] ?? 'Unknown';
            }
        }
        return 'Unknown';
    }

    /**
     * Get MailPoet's transactional email setting
     */
    private function get_mailpoet_transactional_setting() {
        try {
            if (class_exists('MailPoet\Settings\SettingsController')) {
                $settings = \MailPoet\Settings\SettingsController::getInstance();
                return $settings->get('send_transactional_emails', false);
            }
        } catch (Exception $e) {
            return false;
        }
        return false;
    }

    /**
     * Record test result
     */
    private function record_test_result($test_name, $passed, $message) {
        $this->results[] = array(
            'test' => $test_name,
            'passed' => $passed,
            'message' => $message
        );
    }

    /**
     * Display test results
     */
    private function display_results() {
        echo "Test Results Summary\n";
        echo "===================\n";
        
        $total_tests = count($this->results);
        $passed_tests = array_filter($this->results, function($result) {
            return $result['passed'];
        });
        $passed_count = count($passed_tests);
        
        echo "Total Tests: {$total_tests}\n";
        echo "Passed: {$passed_count}\n";
        echo "Failed: " . ($total_tests - $passed_count) . "\n";
        echo "Success Rate: " . round(($passed_count / $total_tests) * 100, 1) . "%\n\n";
        
        if ($passed_count === $total_tests) {
            echo "🎉 All tests passed! MailPoet integration is working correctly.\n";
        } else {
            echo "⚠️  Some tests failed. Please review the issues above.\n";
        }
        
        // Log results
        $this->logger->log('MailPoet integration test completed', Emailit_Logger::LEVEL_INFO, array(
            'total_tests' => $total_tests,
            'passed_tests' => $passed_count,
            'success_rate' => round(($passed_count / $total_tests) * 100, 1),
            'results' => $this->results
        ));
    }
}

// Run tests if called directly
if (php_sapi_name() === 'cli') {
    $test = new Emailit_MailPoet_Integration_Test();
    $test->run_all_tests();
}
