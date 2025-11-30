<?php
/**
 * Emailit Memory Manager Class
 *
 * Provides memory management utilities including cache clearing, email compression,
 * and streaming for large attachments.
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

class Emailit_Memory_Manager {

    /**
     * Memory limit threshold (in MB)
     */
    const MEMORY_THRESHOLD = 128;

    /**
     * Large attachment threshold (in MB)
     */
    const LARGE_ATTACHMENT_THRESHOLD = 10;

    /**
     * Email compression threshold (in KB)
     */
    const EMAIL_COMPRESSION_THRESHOLD = 50;

    /**
     * Cache groups to manage
     */
    private $cache_groups = array(
        'emailit_queries',
        'emailit_api_responses',
        'emailit_webhook_cache',
        'emailit_metrics_cache'
    );

    /**
     * Constructor
     */
    public function __construct() {
        // Monitor memory usage
        add_action('init', array($this, 'monitor_memory_usage'));
        
        // Schedule cache cleanup
        if (!wp_next_scheduled('emailit_cleanup_caches')) {
            wp_schedule_event(time(), 'daily', 'emailit_cleanup_caches');
        }
        add_action('emailit_cleanup_caches', array($this, 'cleanup_caches'));
    }

    /**
     * Monitor memory usage and trigger cleanup if needed
     */
    public function monitor_memory_usage() {
        $memory_usage = memory_get_usage(true);
        $memory_limit = $this->get_memory_limit();
        $usage_percentage = ($memory_usage / $memory_limit) * 100;

        // If memory usage is above 80%, trigger cleanup
        if ($usage_percentage > 80) {
            $this->emergency_cleanup();
        }

        // Log high memory usage
        if ($usage_percentage > 60 && defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[Emailit] High memory usage: %s/%s (%d%%)',
                size_format($memory_usage),
                size_format($memory_limit),
                round($usage_percentage)
            ));
        }
    }

    /**
     * Get memory limit in bytes
     */
    private function get_memory_limit() {
        $memory_limit = ini_get('memory_limit');
        
        if ($memory_limit == -1) {
            return PHP_INT_MAX;
        }

        $unit = strtolower(substr($memory_limit, -1));
        $value = (int) $memory_limit;

        switch ($unit) {
            case 'g':
                return $value * 1024 * 1024 * 1024;
            case 'm':
                return $value * 1024 * 1024;
            case 'k':
                return $value * 1024;
            default:
                return $value;
        }
    }

    /**
     * Emergency memory cleanup
     */
    public function emergency_cleanup() {
        // Clear all caches
        $this->clear_all_caches();
        
        // Force garbage collection
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }

        // Clear WordPress object cache
        wp_cache_flush();

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Emailit] Emergency memory cleanup performed');
        }
    }

    /**
     * Clear all Emailit-related caches
     */
    public function clear_all_caches() {
        foreach ($this->cache_groups as $group) {
            wp_cache_flush_group($group);
        }

        // Clear transients
        $this->clear_transients();

        // Clear query cache
        if (class_exists('Emailit_Query_Optimizer')) {
            $optimizer = new Emailit_Query_Optimizer();
            $optimizer->clear_query_cache();
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Emailit] All caches cleared');
        }
    }

    /**
     * Clear Emailit transients
     */
    private function clear_transients() {
        global $wpdb;

        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_emailit_%'
        ));

        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_timeout_emailit_%'
        ));
    }

    /**
     * Compress email content if it exceeds threshold
     */
    public function compress_email_content($content, $type = 'html') {
        if (strlen($content) < (self::EMAIL_COMPRESSION_THRESHOLD * 1024)) {
            return $content;
        }

        // Compress HTML content
        if ($type === 'html') {
            return $this->compress_html($content);
        }

        // Compress text content
        if ($type === 'text') {
            return $this->compress_text($content);
        }

        return $content;
    }

    /**
     * Compress HTML content
     */
    private function compress_html($html) {
        // Remove unnecessary whitespace
        $html = preg_replace('/\s+/', ' ', $html);
        
        // Remove comments
        $html = preg_replace('/<!--.*?-->/s', '', $html);
        
        // Remove extra spaces around tags
        $html = preg_replace('/>\s+</', '><', $html);
        
        return trim($html);
    }

    /**
     * Compress text content
     */
    private function compress_text($text) {
        // Remove extra whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Remove empty lines
        $text = preg_replace('/\n\s*\n/', "\n", $text);
        
        return trim($text);
    }

    /**
     * Stream large attachments instead of loading into memory
     */
    public function stream_attachment($file_path, $chunk_size = 8192) {
        if (!file_exists($file_path)) {
            return false;
        }

        $file_size = filesize($file_path);
        
        // Only stream if file is larger than threshold
        if ($file_size < (self::LARGE_ATTACHMENT_THRESHOLD * 1024 * 1024)) {
            return file_get_contents($file_path);
        }

        // Stream file in chunks
        $content = '';
        $handle = fopen($file_path, 'rb');
        
        if ($handle === false) {
            return false;
        }

        while (!feof($handle)) {
            $chunk = fread($handle, $chunk_size);
            if ($chunk === false) {
                break;
            }
            $content .= $chunk;
            
            // Check memory usage during streaming
            if (memory_get_usage(true) > (self::MEMORY_THRESHOLD * 1024 * 1024)) {
                fclose($handle);
                return false; // Memory limit reached
            }
        }

        fclose($handle);
        return $content;
    }

    /**
     * Get memory usage statistics
     */
    public function get_memory_stats() {
        $memory_usage = memory_get_usage(true);
        $memory_peak = memory_get_peak_usage(true);
        $memory_limit = $this->get_memory_limit();

        return array(
            'current_usage' => $memory_usage,
            'current_usage_formatted' => size_format($memory_usage),
            'peak_usage' => $memory_peak,
            'peak_usage_formatted' => size_format($memory_peak),
            'memory_limit' => $memory_limit,
            'memory_limit_formatted' => size_format($memory_limit),
            'usage_percentage' => round(($memory_usage / $memory_limit) * 100, 2),
            'peak_percentage' => round(($memory_peak / $memory_limit) * 100, 2)
        );
    }

    /**
     * Optimize memory usage for bulk operations
     */
    public function optimize_for_bulk_operation($total_items, $batch_size = 100) {
        // Calculate optimal batch size based on available memory
        $available_memory = $this->get_memory_limit() - memory_get_usage(true);
        $estimated_memory_per_item = 1024 * 10; // 10KB per item estimate
        
        $optimal_batch_size = min(
            $batch_size,
            max(10, floor($available_memory / $estimated_memory_per_item))
        );

        return array(
            'batch_size' => $optimal_batch_size,
            'total_batches' => ceil($total_items / $optimal_batch_size),
            'estimated_memory_per_batch' => $optimal_batch_size * $estimated_memory_per_item
        );
    }

    /**
     * Cleanup caches (scheduled task)
     */
    public function cleanup_caches() {
        // Clear old caches
        $this->clear_old_caches();
        
        // Optimize database tables
        if (class_exists('Emailit_Query_Optimizer')) {
            $optimizer = new Emailit_Query_Optimizer();
            $optimizer->optimize_tables();
        }

        // Log cleanup
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Emailit] Scheduled cache cleanup completed');
        }
    }

    /**
     * Clear old caches based on age
     */
    private function clear_old_caches() {
        global $wpdb;

        // Clear transients older than 24 hours
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE %s 
             AND option_value < %d",
            '_transient_timeout_emailit_%',
            time() - (24 * 60 * 60)
        ));
    }

    /**
     * Get cache statistics
     */
    public function get_cache_stats() {
        global $wpdb;

        $stats = array();

        foreach ($this->cache_groups as $group) {
            $cache_keys = wp_cache_get('cache_keys_' . $group, $group);
            $stats[$group] = array(
                'keys' => is_array($cache_keys) ? count($cache_keys) : 0,
                'size' => $this->estimate_cache_size($group)
            );
        }

        // Get transient statistics
        $transient_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_emailit_%'
        ));

        $stats['transients'] = array(
            'count' => (int) $transient_count,
            'size' => $this->estimate_transient_size()
        );

        return $stats;
    }

    /**
     * Estimate cache size for a group
     */
    private function estimate_cache_size($group) {
        // This is a rough estimate - actual implementation would depend on cache backend
        return 0; // Placeholder
    }

    /**
     * Estimate transient size
     */
    private function estimate_transient_size() {
        global $wpdb;

        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_emailit_%'
        ));

        return (int) $result;
    }

    /**
     * Force garbage collection
     */
    public function force_garbage_collection() {
        if (function_exists('gc_collect_cycles')) {
            $collected = gc_collect_cycles();
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("[Emailit] Garbage collection completed, collected $collected cycles");
            }
            
            return $collected;
        }
        
        return 0;
    }

    /**
     * Check if system is under memory pressure
     */
    public function is_memory_pressure() {
        $memory_usage = memory_get_usage(true);
        $memory_limit = $this->get_memory_limit();
        $usage_percentage = ($memory_usage / $memory_limit) * 100;

        return $usage_percentage > 70;
    }

    /**
     * Get memory recommendations
     */
    public function get_memory_recommendations() {
        $stats = $this->get_memory_stats();
        $recommendations = array();

        if ($stats['usage_percentage'] > 80) {
            $recommendations[] = 'High memory usage detected. Consider increasing PHP memory limit or optimizing code.';
        }

        if ($stats['peak_percentage'] > 90) {
            $recommendations[] = 'Peak memory usage is very high. Review memory-intensive operations.';
        }

        if ($this->is_memory_pressure()) {
            $recommendations[] = 'System is under memory pressure. Cache cleanup recommended.';
        }

        return $recommendations;
    }
}


