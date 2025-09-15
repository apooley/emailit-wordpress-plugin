<?php
/**
 * Health Monitor Admin View
 *
 * @package Emailit_Integration
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Health status and metrics are passed from the admin callback
// Get additional data if needed
$health_metrics = $health_monitor->get_health_metrics();

// Get health statistics
$health_stats = Emailit_Health_Migration::get_health_stats();
$table_sizes = Emailit_Health_Migration::get_table_sizes();
?>

<div class="wrap">
    <h1><?php _e('Emailit Health Monitor', 'emailit-integration'); ?></h1>
    
    <div class="emailit-health-monitor">
        <!-- Health Status Overview -->
        <div class="emailit-health-overview">
            <h2><?php _e('System Health Overview', 'emailit-integration'); ?></h2>
            
            <div class="health-status-cards">
                <div class="health-card overall-status">
                    <h3><?php _e('Overall Status', 'emailit-integration'); ?></h3>
                    <div class="status-indicator status-<?php echo esc_attr($health_status['overall']); ?>">
                        <?php
                        $status_icon = $health_status['overall'] === 'success' ? '✅' : 
                                     ($health_status['overall'] === 'warning' ? '⚠️' : '🚨');
                        echo $status_icon;
                        ?>
                        <span class="status-text">
                            <?php echo ucfirst(esc_html($health_status['overall'])); ?>
                        </span>
                    </div>
                </div>
                
                <div class="health-card last-updated">
                    <h3><?php _e('Last Updated', 'emailit-integration'); ?></h3>
                    <div class="update-time">
                        <?php echo esc_html($health_status['last_updated']); ?>
                    </div>
                </div>
                
                <div class="health-card active-alerts">
                    <h3><?php _e('Active Alerts', 'emailit-integration'); ?></h3>
                    <div class="alert-count">
                        <?php echo count($health_status['alerts']); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Health Checks -->
        <div class="emailit-health-checks">
            <h2><?php _e('Health Checks', 'emailit-integration'); ?></h2>
            
            <div class="health-checks-grid">
                <?php foreach ($health_status['checks'] as $check_type => $check): ?>
                <div class="health-check-card">
                    <div class="check-header">
                        <h4><?php echo esc_html(ucfirst(str_replace('_', ' ', $check_type))); ?></h4>
                        <div class="check-status status-<?php echo esc_attr($check['status']); ?>">
                            <?php
                            $icon = $check['status'] === 'success' ? '✅' : 
                                   ($check['status'] === 'warning' ? '⚠️' : '🚨');
                            echo $icon;
                            ?>
                        </div>
                    </div>
                    <div class="check-message">
                        <?php echo esc_html($check['message']); ?>
                    </div>
                    <div class="check-timestamp">
                        <?php echo esc_html($check['timestamp']); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Performance Metrics -->
        <div class="emailit-performance-metrics">
            <h2><?php _e('Performance Metrics', 'emailit-integration'); ?></h2>
            
            <div class="metrics-grid">
                <div class="metric-card">
                    <h3><?php _e('API Performance', 'emailit-integration'); ?></h3>
                    <div class="metric-value">
                        <?php echo number_format($health_metrics['api_metrics']['recent']['avg_response_time'], 2); ?>s
                    </div>
                    <div class="metric-label"><?php _e('Avg Response Time', 'emailit-integration'); ?></div>
                </div>
                
                <div class="metric-card">
                    <h3><?php _e('Email Delivery', 'emailit-integration'); ?></h3>
                    <div class="metric-value">
                        <?php echo number_format($health_metrics['email_metrics']['recent']['delivery_rate'] * 100, 1); ?>%
                    </div>
                    <div class="metric-label"><?php _e('Delivery Rate', 'emailit-integration'); ?></div>
                </div>
                
                <div class="metric-card">
                    <h3><?php _e('Queue Status', 'emailit-integration'); ?></h3>
                    <div class="metric-value">
                        <?php echo number_format($health_metrics['queue_metrics']['pending_emails']); ?>
                    </div>
                    <div class="metric-label"><?php _e('Pending Emails', 'emailit-integration'); ?></div>
                </div>
                
                <div class="metric-card">
                    <h3><?php _e('Memory Usage', 'emailit-integration'); ?></h3>
                    <div class="metric-value">
                        <?php echo number_format($health_metrics['performance_metrics']['memory_usage']['current'] / 1024 / 1024, 1); ?>MB
                    </div>
                    <div class="metric-label"><?php _e('Current Usage', 'emailit-integration'); ?></div>
                </div>
            </div>
        </div>

        <!-- System Statistics -->
        <div class="emailit-system-stats">
            <h2><?php _e('System Statistics', 'emailit-integration'); ?></h2>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><?php _e('Health Checks', 'emailit-integration'); ?></h3>
                    <div class="stat-value"><?php echo number_format($health_stats['health_checks']['total']); ?></div>
                    <div class="stat-details">
                        <span class="success"><?php echo number_format($health_stats['health_checks']['successful']); ?> <?php _e('Success', 'emailit-integration'); ?></span>
                        <span class="warning"><?php echo number_format($health_stats['health_checks']['warnings']); ?> <?php _e('Warnings', 'emailit-integration'); ?></span>
                        <span class="error"><?php echo number_format($health_stats['health_checks']['errors']); ?> <?php _e('Errors', 'emailit-integration'); ?></span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <h3><?php _e('Alerts', 'emailit-integration'); ?></h3>
                    <div class="stat-value"><?php echo number_format($health_stats['alerts']['total']); ?></div>
                    <div class="stat-details">
                        <span class="critical"><?php echo number_format($health_stats['alerts']['critical']); ?> <?php _e('Critical', 'emailit-integration'); ?></span>
                        <span class="warning"><?php echo number_format($health_stats['alerts']['warnings']); ?> <?php _e('Warnings', 'emailit-integration'); ?></span>
                        <span class="dismissed"><?php echo number_format($health_stats['alerts']['dismissed']); ?> <?php _e('Dismissed', 'emailit-integration'); ?></span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <h3><?php _e('Database Size', 'emailit-integration'); ?></h3>
                    <div class="stat-value">
                        <?php
                        $total_size = 0;
                        foreach ($table_sizes as $table => $size) {
                            $total_size += $size['size_mb'];
                        }
                        echo number_format($total_size, 2); ?>MB
                    </div>
                    <div class="stat-details">
                        <?php foreach ($table_sizes as $table => $size): ?>
                        <span><?php echo esc_html($table); ?>: <?php echo number_format($size['size_mb'], 2); ?>MB</span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="emailit-health-actions">
            <h2><?php _e('Health Monitor Actions', 'emailit-integration'); ?></h2>
            
            <div class="action-buttons">
                <button type="button" class="button button-primary" onclick="emailitRunHealthCheck()">
                    <?php _e('Run Health Check', 'emailit-integration'); ?>
                </button>
                
                <button type="button" class="button" onclick="emailitRefreshMetrics()">
                    <?php _e('Refresh Metrics', 'emailit-integration'); ?>
                </button>
                
                <button type="button" class="button" onclick="emailitCleanupOldData()">
                    <?php _e('Cleanup Old Data', 'emailit-integration'); ?>
                </button>
                
                <button type="button" class="button" onclick="emailitOptimizeTables()">
                    <?php _e('Optimize Tables', 'emailit-integration'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.emailit-health-monitor {
    max-width: 1200px;
}

.health-status-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.health-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
}

.health-card h3 {
    margin: 0 0 15px 0;
    color: #333;
}

.status-indicator {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    font-size: 18px;
    font-weight: bold;
}

.status-success { color: #46b450; }
.status-warning { color: #f56e28; }
.status-error { color: #d63638; }

.health-checks-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.health-check-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
}

.check-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.check-header h4 {
    margin: 0;
    color: #333;
}

.check-status {
    font-size: 20px;
}

.check-message {
    color: #666;
    margin-bottom: 10px;
}

.check-timestamp {
    font-size: 12px;
    color: #999;
}

.metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.metric-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
}

.metric-card h3 {
    margin: 0 0 15px 0;
    color: #333;
    font-size: 14px;
}

.metric-value {
    font-size: 24px;
    font-weight: bold;
    color: #0073aa;
    margin-bottom: 5px;
}

.metric-label {
    font-size: 12px;
    color: #666;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
}

.stat-card h3 {
    margin: 0 0 15px 0;
    color: #333;
}

.stat-value {
    font-size: 28px;
    font-weight: bold;
    color: #0073aa;
    margin-bottom: 10px;
}

.stat-details {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.stat-details span {
    font-size: 12px;
    padding: 2px 8px;
    border-radius: 3px;
    display: inline-block;
    width: fit-content;
}

.stat-details .success { background: #d4edda; color: #155724; }
.stat-details .warning { background: #fff3cd; color: #856404; }
.stat-details .error { background: #f8d7da; color: #721c24; }
.stat-details .critical { background: #f8d7da; color: #721c24; }
.stat-details .dismissed { background: #e2e3e5; color: #383d41; }

.action-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.action-buttons .button {
    margin: 0;
}
</style>

<script>
function emailitRunHealthCheck() {
    const button = event.target;
    const originalText = button.textContent;
    
    button.textContent = '<?php _e('Running...', 'emailit-integration'); ?>';
    button.disabled = true;
    
    fetch(ajaxurl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'emailit_health_check',
            nonce: '<?php echo wp_create_nonce('emailit_health_check'); ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('<?php _e('Health check completed successfully!', 'emailit-integration'); ?>');
            location.reload();
        } else {
            alert('<?php _e('Health check failed: ', 'emailit-integration'); ?>' + (data.data || '<?php _e('Unknown error', 'emailit-integration'); ?>'));
        }
    })
    .catch(error => {
        alert('<?php _e('Error running health check: ', 'emailit-integration'); ?>' + error.message);
    })
    .finally(() => {
        button.textContent = originalText;
        button.disabled = false;
    });
}

function emailitRefreshMetrics() {
    location.reload();
}

function emailitCleanupOldData() {
    if (confirm('<?php _e('This will clean up old health monitoring data. Continue?', 'emailit-integration'); ?>')) {
        const button = event.target;
        const originalText = button.textContent;
        
        button.textContent = '<?php _e('Cleaning...', 'emailit-integration'); ?>';
        button.disabled = true;
        
        fetch(ajaxurl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'emailit_cleanup_health_data',
                nonce: '<?php echo wp_create_nonce('emailit_cleanup_health_data'); ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('<?php _e('Old data cleaned up successfully!', 'emailit-integration'); ?>');
                location.reload();
            } else {
                alert('<?php _e('Cleanup failed: ', 'emailit-integration'); ?>' + (data.data || '<?php _e('Unknown error', 'emailit-integration'); ?>'));
            }
        })
        .catch(error => {
            alert('<?php _e('Error during cleanup: ', 'emailit-integration'); ?>' + error.message);
        })
        .finally(() => {
            button.textContent = originalText;
            button.disabled = false;
        });
    }
}

function emailitOptimizeTables() {
    if (confirm('<?php _e('This will optimize the health monitoring database tables. Continue?', 'emailit-integration'); ?>')) {
        const button = event.target;
        const originalText = button.textContent;
        
        button.textContent = '<?php _e('Optimizing...', 'emailit-integration'); ?>';
        button.disabled = true;
        
        fetch(ajaxurl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'emailit_optimize_health_tables',
                nonce: '<?php echo wp_create_nonce('emailit_optimize_health_tables'); ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('<?php _e('Tables optimized successfully!', 'emailit-integration'); ?>');
                location.reload();
            } else {
                alert('<?php _e('Optimization failed: ', 'emailit-integration'); ?>' + (data.data || '<?php _e('Unknown error', 'emailit-integration'); ?>'));
            }
        })
        .catch(error => {
            alert('<?php _e('Error during optimization: ', 'emailit-integration'); ?>' + error.message);
        })
        .finally(() => {
            button.textContent = originalText;
            button.disabled = false;
        });
    }
}
</script>
