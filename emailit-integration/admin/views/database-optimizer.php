<?php
/**
 * Database Optimizer Admin Page Template
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Get database optimizer
$db_optimizer = emailit_get_component('db_optimizer');
$query_optimizer = emailit_get_component('query_optimizer');

// Handle AJAX actions
if (isset($_POST['action'])) {
    $action = sanitize_text_field($_POST['action']);
    $nonce = sanitize_text_field($_POST['nonce'] ?? '');
    
    if (!wp_verify_nonce($nonce, 'emailit_db_optimizer_nonce')) {
        wp_die('Security check failed');
    }
    
    switch ($action) {
        case 'optimize_tables':
            $results = $db_optimizer->optimize_tables();
            echo '<div class="notice notice-success"><p>Tables optimized successfully!</p></div>';
            break;
            
        case 'add_indexes':
            $indexes = $db_optimizer->add_performance_indexes();
            if (!empty($indexes)) {
                echo '<div class="notice notice-success"><p>Added indexes: ' . implode(', ', $indexes) . '</p></div>';
            } else {
                echo '<div class="notice notice-info"><p>All indexes are already present.</p></div>';
            }
            break;
            
        case 'cleanup_orphaned':
            $cleaned = $db_optimizer->cleanup_orphaned_records();
            if (!empty($cleaned)) {
                echo '<div class="notice notice-success"><p>Cleaned up orphaned records: ' . json_encode($cleaned) . '</p></div>';
            } else {
                echo '<div class="notice notice-info"><p>No orphaned records found.</p></div>';
            }
            break;
            
        case 'archive_old':
            $days = (int) ($_POST['days'] ?? 90);
            $archived = $db_optimizer->archive_old_records($days);
            if (!empty($archived)) {
                echo '<div class="notice notice-success"><p>Archived old records: ' . json_encode($archived) . '</p></div>';
            } else {
                echo '<div class="notice notice-info"><p>No records to archive.</p></div>';
            }
            break;
            
        case 'clear_cache':
            $query_optimizer->clear_cache();
            echo '<div class="notice notice-success"><p>Query cache cleared successfully!</p></div>';
            break;
    }
}

// Get current statistics
$perf_stats = $db_optimizer->get_performance_stats();
$slow_queries = $db_optimizer->analyze_slow_queries();
?>

<div class="wrap emailit-db-optimizer">
    <h1><?php _e('Database Optimizer', 'emailit-integration'); ?></h1>
    
    <div class="emailit-db-overview">
        <h2><?php _e('Database Overview', 'emailit-integration'); ?></h2>
        
        <div class="emailit-stats-grid">
            <?php foreach ($perf_stats as $table_name => $stats): ?>
                <?php if (is_array($stats) && isset($stats['table_rows'])): ?>
                <div class="emailit-stat-card">
                    <h3><?php echo esc_html(ucfirst(str_replace('wp_', '', $table_name))); ?></h3>
                    <div class="stat-item">
                        <span class="stat-label"><?php _e('Rows:', 'emailit-integration'); ?></span>
                        <span class="stat-value"><?php echo number_format($stats['table_rows']); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label"><?php _e('Size:', 'emailit-integration'); ?></span>
                        <span class="stat-value"><?php echo $stats['size_mb']; ?> MB</span>
                    </div>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="emailit-optimization-tools">
        <h2><?php _e('Optimization Tools', 'emailit-integration'); ?></h2>
        
        <div class="emailit-tool-grid">
            <!-- Table Optimization -->
            <div class="emailit-tool-card">
                <h3><?php _e('Table Optimization', 'emailit-integration'); ?></h3>
                <p><?php _e('Optimize database tables to reclaim space and improve performance.', 'emailit-integration'); ?></p>
                <form method="post" style="display: inline;">
                    <input type="hidden" name="action" value="optimize_tables">
                    <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('emailit_db_optimizer_nonce'); ?>">
                    <button type="submit" class="button button-primary">
                        <?php _e('Optimize Tables', 'emailit-integration'); ?>
                    </button>
                </form>
            </div>

            <!-- Add Indexes -->
            <div class="emailit-tool-card">
                <h3><?php _e('Add Performance Indexes', 'emailit-integration'); ?></h3>
                <p><?php _e('Add missing database indexes to improve query performance.', 'emailit-integration'); ?></p>
                <form method="post" style="display: inline;">
                    <input type="hidden" name="action" value="add_indexes">
                    <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('emailit_db_optimizer_nonce'); ?>">
                    <button type="submit" class="button button-secondary">
                        <?php _e('Add Indexes', 'emailit-integration'); ?>
                    </button>
                </form>
            </div>

            <!-- Cleanup Orphaned Records -->
            <div class="emailit-tool-card">
                <h3><?php _e('Cleanup Orphaned Records', 'emailit-integration'); ?></h3>
                <p><?php _e('Remove orphaned webhook logs and queue items that no longer have corresponding email logs.', 'emailit-integration'); ?></p>
                <form method="post" style="display: inline;">
                    <input type="hidden" name="action" value="cleanup_orphaned">
                    <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('emailit_db_optimizer_nonce'); ?>">
                    <button type="submit" class="button button-secondary">
                        <?php _e('Cleanup Orphaned', 'emailit-integration'); ?>
                    </button>
                </form>
            </div>

            <!-- Archive Old Records -->
            <div class="emailit-tool-card">
                <h3><?php _e('Archive Old Records', 'emailit-integration'); ?></h3>
                <p><?php _e('Archive old email content to reduce database size while keeping essential data.', 'emailit-integration'); ?></p>
                <form method="post" style="display: inline;">
                    <input type="hidden" name="action" value="archive_old">
                    <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('emailit_db_optimizer_nonce'); ?>">
                    <label for="archive_days"><?php _e('Archive records older than:', 'emailit-integration'); ?></label>
                    <select name="days" id="archive_days">
                        <option value="30">30 days</option>
                        <option value="60">60 days</option>
                        <option value="90" selected>90 days</option>
                        <option value="180">180 days</option>
                        <option value="365">1 year</option>
                    </select>
                    <button type="submit" class="button button-secondary">
                        <?php _e('Archive Records', 'emailit-integration'); ?>
                    </button>
                </form>
            </div>

            <!-- Clear Cache -->
            <div class="emailit-tool-card">
                <h3><?php _e('Clear Query Cache', 'emailit-integration'); ?></h3>
                <p><?php _e('Clear cached query results to ensure fresh data.', 'emailit-integration'); ?></p>
                <form method="post" style="display: inline;">
                    <input type="hidden" name="action" value="clear_cache">
                    <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('emailit_db_optimizer_nonce'); ?>">
                    <button type="submit" class="button button-secondary">
                        <?php _e('Clear Cache', 'emailit-integration'); ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <?php if (!empty($slow_queries)): ?>
    <div class="emailit-performance-issues">
        <h2><?php _e('Performance Analysis', 'emailit-integration'); ?></h2>
        
        <?php foreach ($slow_queries as $issue_type => $issues): ?>
            <?php if (!empty($issues)): ?>
            <div class="emailit-issue-section">
                <h3><?php echo esc_html(ucwords(str_replace('_', ' ', $issue_type))); ?></h3>
                <ul>
                    <?php foreach ($issues as $issue): ?>
                        <li><?php echo esc_html($issue); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="emailit-db-recommendations">
        <h2><?php _e('Performance Recommendations', 'emailit-integration'); ?></h2>
        
        <div class="emailit-recommendations-grid">
            <div class="emailit-recommendation">
                <h4><?php _e('Regular Maintenance', 'emailit-integration'); ?></h4>
                <ul>
                    <li><?php _e('Run table optimization weekly', 'emailit-integration'); ?></li>
                    <li><?php _e('Clean up orphaned records monthly', 'emailit-integration'); ?></li>
                    <li><?php _e('Archive old records quarterly', 'emailit-integration'); ?></li>
                </ul>
            </div>
            
            <div class="emailit-recommendation">
                <h4><?php _e('Monitoring', 'emailit-integration'); ?></h4>
                <ul>
                    <li><?php _e('Monitor table sizes regularly', 'emailit-integration'); ?></li>
                    <li><?php _e('Check for slow queries', 'emailit-integration'); ?></li>
                    <li><?php _e('Review index usage', 'emailit-integration'); ?></li>
                </ul>
            </div>
            
            <div class="emailit-recommendation">
                <h4><?php _e('Best Practices', 'emailit-integration'); ?></h4>
                <ul>
                    <li><?php _e('Use pagination for large datasets', 'emailit-integration'); ?></li>
                    <li><?php _e('Implement proper caching', 'emailit-integration'); ?></li>
                    <li><?php _e('Regular database backups', 'emailit-integration'); ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
.emailit-db-optimizer {
    max-width: 1200px;
}

.emailit-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.emailit-stat-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.emailit-stat-card h3 {
    margin-top: 0;
    color: #333;
}

.stat-item {
    display: flex;
    justify-content: space-between;
    margin: 10px 0;
    padding: 5px 0;
    border-bottom: 1px solid #f0f0f0;
}

.stat-label {
    font-weight: 500;
    color: #666;
}

.stat-value {
    font-weight: bold;
    color: #333;
}

.emailit-tool-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.emailit-tool-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.emailit-tool-card h3 {
    margin-top: 0;
    color: #333;
}

.emailit-tool-card p {
    color: #666;
    margin-bottom: 15px;
}

.emailit-tool-card form {
    margin-top: 15px;
}

.emailit-tool-card select {
    margin: 0 10px;
    padding: 5px;
}

.emailit-performance-issues {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 20px;
    margin: 20px 0;
}

.emailit-issue-section {
    margin: 15px 0;
}

.emailit-issue-section h3 {
    color: #d63638;
    margin-bottom: 10px;
}

.emailit-issue-section ul {
    margin: 0;
    padding-left: 20px;
}

.emailit-issue-section li {
    margin: 5px 0;
    color: #666;
}

.emailit-db-recommendations {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 20px;
    margin: 20px 0;
}

.emailit-recommendations-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 15px;
}

.emailit-recommendation h4 {
    color: #333;
    margin-bottom: 10px;
}

.emailit-recommendation ul {
    margin: 0;
    padding-left: 20px;
}

.emailit-recommendation li {
    margin: 5px 0;
    color: #666;
}
</style>
