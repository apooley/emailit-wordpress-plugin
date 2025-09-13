<?php
/**
 * Emailit Integration Uninstall Script
 *
 * This file is executed when the plugin is deleted via the WordPress admin.
 * It removes all plugin data including database tables, options, and transients.
 */

// Security check - Only run if WordPress is calling this file
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Remove all plugin data
 */
class Emailit_Uninstaller {

    /**
     * Run uninstall process
     */
    public static function uninstall() {
        // Remove database tables
        self::drop_tables();

        // Remove plugin options
        self::remove_options();

        // Remove transients
        self::remove_transients();

        // Clear scheduled events
        self::clear_scheduled_events();

        // Remove user meta
        self::remove_user_meta();

        // Hook for other plugins to clean up
        do_action('emailit_uninstalling');
    }

    /**
     * Drop plugin database tables
     */
    private static function drop_tables() {
        global $wpdb;

        $tables = array(
            $wpdb->prefix . 'emailit_logs',
            $wpdb->prefix . 'emailit_webhook_logs'
        );

        foreach ($tables as $table) {
            $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS %i", $table));
        }
    }

    /**
     * Remove all plugin options
     */
    private static function remove_options() {
        global $wpdb;

        // Remove options with emailit_ prefix
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'emailit_%'");

        // Remove specific options that might not have the prefix
        $options = array(
            'emailit_db_version',
            'emailit_plugin_version',
        );

        foreach ($options as $option) {
            delete_option($option);
            delete_site_option($option); // For multisite
        }
    }

    /**
     * Remove plugin transients
     */
    private static function remove_transients() {
        global $wpdb;

        // Remove transients with emailit_ prefix
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_emailit_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_emailit_%'");

        // For multisite
        $wpdb->query("DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE '_site_transient_emailit_%'");
        $wpdb->query("DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE '_site_transient_timeout_emailit_%'");
    }

    /**
     * Clear scheduled events
     */
    private static function clear_scheduled_events() {
        // Clear cron jobs
        wp_clear_scheduled_hook('emailit_cleanup_logs');
        wp_clear_scheduled_hook('emailit_process_queue');
        wp_clear_scheduled_hook('emailit_retry_failed_emails');
    }

    /**
     * Remove user meta related to plugin
     */
    private static function remove_user_meta() {
        global $wpdb;

        // Remove user meta with emailit_ prefix
        $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'emailit_%'");
    }
}

// Run the uninstaller
Emailit_Uninstaller::uninstall();