<?php
/**
 * Admin Settings Page Template
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Get current tab
$current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';

// Handle form submission - WordPress handles this automatically via options.php
?>

<div class="wrap emailit-admin-wrap">
    <div class="emailit-header">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <div class="emailit-power-user-toggle">
            <label class="emailit-toggle-switch">
                <input type="checkbox" id="power-user-mode" <?php echo $admin->is_power_user_mode() ? 'checked' : ''; ?>>
                <span class="slider round"></span>
            </label>
            <label for="power-user-mode" class="emailit-toggle-label">
                <span class="power-user-text"><?php _e('Power User Mode', 'emailit-integration'); ?></span>
                <span class="power-user-description"><?php _e('Show advanced features and detailed options', 'emailit-integration'); ?></span>
            </label>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <nav class="nav-tab-wrapper emailit-tab-nav">
        <a href="#general" data-tab="general"
           class="nav-tab <?php echo $current_tab === 'general' ? 'nav-tab-active active' : ''; ?>">
            <?php _e('General', 'emailit-integration'); ?>
        </a>
        <a href="#logs" data-tab="logs"
           class="nav-tab <?php echo $current_tab === 'logs' ? 'nav-tab-active active' : ''; ?>">
            <?php _e('Logs & Statistics', 'emailit-integration'); ?>
        </a>
        <a href="#advanced" data-tab="advanced"
           class="nav-tab <?php echo $current_tab === 'advanced' ? 'nav-tab-active active' : ''; ?>">
            <?php _e('Advanced', 'emailit-integration'); ?>
        </a>
        <?php
        // Only show FluentCRM tab if FluentCRM is installed and active
        $webhook = emailit_get_component('webhook');
        $fluentcrm_status = $webhook ? $webhook->get_fluentcrm_integration_status() : array('available' => false);
        if ($fluentcrm_status['available']) :
        ?>
        <a href="#fluentcrm" data-tab="fluentcrm"
           class="nav-tab <?php echo $current_tab === 'fluentcrm' ? 'nav-tab-active active' : ''; ?>">
            <?php _e('FluentCRM', 'emailit-integration'); ?>
        </a>
        <?php endif; ?>
    </nav>

    <form method="post" action="options.php">
        <?php
        settings_fields('emailit-settings');
        ?>

        <!-- General Settings Tab -->
        <div id="general" class="emailit-tab-pane <?php echo $current_tab === 'general' ? 'active' : ''; ?>">
            <!-- Health Score Dashboard -->
            <div class="emailit-health-score-dashboard">
                <div class="health-score-header">
                    <h2><?php _e('System Health', 'emailit-integration'); ?></h2>
                    <div class="health-score-badge">
                        <span class="score" id="health-score">-</span>
                        <span class="max">/100</span>
                    </div>
                </div>
                
                <div class="health-metrics-grid">
                    <div class="metric-card api-status">
                        <div class="metric-icon">🌐</div>
                        <div class="metric-content">
                            <h3><?php _e('API Connection', 'emailit-integration'); ?></h3>
                            <div class="metric-status" id="api-status">
                                <span class="status-text"><?php _e('Checking...', 'emailit-integration'); ?></span>
                            </div>
                            <div class="metric-detail" id="api-detail">-</div>
                        </div>
                    </div>
                    
                    <div class="metric-card queue-status">
                        <div class="metric-icon">⚡</div>
                        <div class="metric-content">
                            <h3><?php _e('Queue Processing', 'emailit-integration'); ?></h3>
                            <div class="metric-status" id="queue-status">
                                <span class="status-text"><?php _e('Checking...', 'emailit-integration'); ?></span>
                            </div>
                            <div class="metric-detail" id="queue-detail">-</div>
                        </div>
                    </div>
                    
                    <div class="metric-card error-rate">
                        <div class="metric-icon">🛡️</div>
                        <div class="metric-content">
                            <h3><?php _e('Error Rate', 'emailit-integration'); ?></h3>
                            <div class="metric-status" id="error-status">
                                <span class="status-text"><?php _e('Checking...', 'emailit-integration'); ?></span>
                            </div>
                            <div class="metric-detail" id="error-detail">-</div>
                        </div>
                    </div>
                    
                    <div class="metric-card webhook-status">
                        <div class="metric-icon">🔗</div>
                        <div class="metric-content">
                            <h3><?php _e('Webhook Status', 'emailit-integration'); ?></h3>
                            <div class="metric-status" id="webhook-status">
                                <span class="status-text"><?php _e('Checking...', 'emailit-integration'); ?></span>
                            </div>
                            <div class="metric-detail" id="webhook-detail">-</div>
                        </div>
                    </div>
                </div>
                
                <div class="health-recommendations">
                    <h3><?php _e('Recommendations', 'emailit-integration'); ?></h3>
                    <ul id="health-recommendations">
                        <li><?php _e('Loading recommendations...', 'emailit-integration'); ?></li>
                    </ul>
                </div>
            </div>

            <!-- API Configuration -->
            <h2>
                <?php _e('API Configuration', 'emailit-integration'); ?>
                <span class="emailit-help-tooltip">
                    <span class="help-icon">?</span>
                    <span class="tooltip-content">
                        <?php _e('Configure your Emailit API credentials. This is required for the plugin to send emails through Emailit\'s service.', 'emailit-integration'); ?>
                    </span>
                </span>
            </h2>
            <table class="form-table emailit-form-table" role="presentation">
                <tbody>
                    <?php do_settings_fields('emailit-settings', 'emailit_api_section'); ?>
                </tbody>
            </table>

            <!-- Email Settings -->
            <h2>
                <?php _e('Email Settings', 'emailit-integration'); ?>
                <span class="emailit-help-tooltip">
                    <span class="help-icon">?</span>
                    <span class="tooltip-content">
                        <?php _e('Configure how emails are sent and handled. These settings control the default behavior for all emails sent through WordPress.', 'emailit-integration'); ?>
                    </span>
                </span>
            </h2>
            <table class="form-table emailit-form-table" role="presentation">
                <tbody>
                    <?php do_settings_fields('emailit-settings', 'emailit_email_section'); ?>
                </tbody>
            </table>

            <!-- Test Email Functionality -->
            <div class="emailit-test-section">
                <h2><?php _e('Test Your Configuration', 'emailit-integration'); ?></h2>
                <p class="description"><?php _e('Send a test email to verify your Emailit integration is working correctly.', 'emailit-integration'); ?></p>
                
                <div class="emailit-test-email">
                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label for="emailit_test_email"><?php _e('Test Email Address', 'emailit-integration'); ?></label>
                                </th>
                                <td>
                                    <input type="email" id="emailit_test_email" class="regular-text"
                                           value="<?php echo esc_attr(get_bloginfo('admin_email')); ?>" />
                                    <p class="description">
                                        <?php _e('Email address to send the test email to.', 'emailit-integration'); ?>
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <p>
                        <button type="button" id="emailit-test-email" class="button button-primary">
                            <?php _e('Send Test Email', 'emailit-integration'); ?>
                        </button>
                        <button type="button" id="emailit-wordpress-test" class="button button-secondary">
                            <?php _e('Send WordPress Test', 'emailit-integration'); ?>
                        </button>
                    </p>

                    <div id="emailit-test-result" class="emailit-test-result" style="display: none;"></div>
                    <div id="emailit-wordpress-test-result" class="emailit-test-result" style="display: none;"></div>
                </div>
            </div>

            <!-- Logging Settings -->
            <h2>
                <?php _e('Logging Settings', 'emailit-integration'); ?>
                <span class="emailit-help-tooltip">
                    <span class="help-icon">?</span>
                    <span class="tooltip-content">
                        <?php _e('Configure email logging and debugging. Enable logging to track email delivery status and troubleshoot issues.', 'emailit-integration'); ?>
                    </span>
                </span>
            </h2>
            <table class="form-table emailit-form-table" role="presentation">
                <tbody>
                    <?php do_settings_fields('emailit-settings', 'emailit_logging_section'); ?>
                </tbody>
            </table>

            <?php submit_button(); ?>
        </div>

        <!-- Logs & Statistics Tab -->
        <div id="logs" class="emailit-tab-pane <?php echo $current_tab === 'logs' ? 'active' : ''; ?>">
            <h2><?php _e('Email Logs & Statistics', 'emailit-integration'); ?></h2>
            
            <!-- Quick Stats -->
            <div class="emailit-quick-stats">
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3><?php _e('Today\'s Emails', 'emailit-integration'); ?></h3>
                        <div class="stat-value" id="today-emails">-</div>
                    </div>
                    <div class="stat-card">
                        <h3><?php _e('Success Rate', 'emailit-integration'); ?></h3>
                        <div class="stat-value" id="success-rate">-</div>
                    </div>
                    <div class="stat-card">
                        <h3><?php _e('Failed Emails', 'emailit-integration'); ?></h3>
                        <div class="stat-value" id="failed-emails">-</div>
                    </div>
                    <div class="stat-card">
                        <h3><?php _e('Queue Status', 'emailit-integration'); ?></h3>
                        <div class="stat-value" id="queue-count">-</div>
                    </div>
                </div>
            </div>

            <!-- Email Logs -->
            <div class="emailit-logs-section">
                <h3><?php _e('Recent Email Activity', 'emailit-integration'); ?></h3>
                <p class="description"><?php _e('View and manage your recent email activity. For detailed logs, visit the dedicated Emailit Log page.', 'emailit-integration'); ?></p>
                
                <div class="emailit-logs-preview">
                    <div class="logs-loading"><?php _e('Loading recent emails...', 'emailit-integration'); ?></div>
                    <div id="recent-logs-content" style="display: none;">
                        <!-- Content will be loaded via AJAX -->
                    </div>
                </div>
                
                <p>
                    <a href="<?php echo admin_url('tools.php?page=emailit-logs'); ?>" class="button button-secondary">
                        <?php _e('View All Email Logs', 'emailit-integration'); ?>
                    </a>
                </p>
            </div>

            <!-- Webhook Activity -->
            <div class="emailit-webhook-section">
                <h3><?php _e('Webhook Activity', 'emailit-integration'); ?></h3>
                <p class="description"><?php _e('Monitor webhook activity and status updates from Emailit.', 'emailit-integration'); ?></p>
                
                <div class="webhook-status-overview">
                    <div class="webhook-status-cards">
                        <div class="webhook-card">
                            <h4><?php _e('Webhook Status', 'emailit-integration'); ?></h4>
                            <div class="webhook-indicator" id="webhook-status">
                                <span class="status-text"><?php _e('Checking...', 'emailit-integration'); ?></span>
                            </div>
                        </div>
                        <div class="webhook-card">
                            <h4><?php _e('Recent Webhooks', 'emailit-integration'); ?></h4>
                            <div class="webhook-count" id="webhook-count">-</div>
                        </div>
                        <div class="webhook-card">
                            <h4><?php _e('Last Webhook', 'emailit-integration'); ?></h4>
                            <div class="webhook-time" id="last-webhook">-</div>
                        </div>
                    </div>
                </div>
                
                <p>
                    <a href="<?php echo admin_url('tools.php?page=emailit-webhook-logs'); ?>" class="button button-secondary">
                        <?php _e('View Webhook Logs', 'emailit-integration'); ?>
                    </a>
                </p>
            </div>
        </div>

        <!-- Performance & Queue Settings Tab -->
        <div id="performance" class="emailit-tab-pane <?php echo $current_tab === 'performance' ? 'active' : ''; ?>">
            <h2><?php _e('Performance & Queue Settings', 'emailit-integration'); ?></h2>

            <div class="emailit-performance-info">
                <p><?php _e('Configure asynchronous email processing for improved site performance.', 'emailit-integration'); ?></p>
            </div>

            <table class="form-table emailit-form-table" role="presentation">
                <tbody>
                    <?php do_settings_fields('emailit-settings', 'emailit_performance_section'); ?>
                </tbody>
            </table>

            <div class="emailit-queue-status">
                <h3><?php _e('Queue Status', 'emailit-integration'); ?></h3>
                <div id="queue-status-info">
                    <div class="queue-stats">
                        <span class="queue-stat">
                            <strong><?php _e('Pending:', 'emailit-integration'); ?></strong>
                            <span id="queue-pending">-</span>
                        </span>
                        <span class="queue-stat">
                            <strong><?php _e('Processing:', 'emailit-integration'); ?></strong>
                            <span id="queue-processing">-</span>
                        </span>
                        <span class="queue-stat">
                            <strong><?php _e('Failed:', 'emailit-integration'); ?></strong>
                            <span id="queue-failed">-</span>
                        </span>
                    </div>
                    <p>
                        <button type="button" id="refresh-queue-stats" class="button button-secondary">
                            <?php _e('Refresh Stats', 'emailit-integration'); ?>
                        </button>
                        <button type="button" id="process-queue-now" class="button button-secondary">
                            <?php _e('Process Queue Now', 'emailit-integration'); ?>
                        </button>
                    </p>
                </div>
            </div>

            <!-- Performance Status -->
            <div class="emailit-performance-status">
                <h3><?php _e('Performance Status', 'emailit-integration'); ?></h3>
                <div class="emailit-status-grid">
                    <div class="emailit-status-item">
                        <span class="status-icon">✅</span>
                        <span class="status-text"><?php _e('Database optimized', 'emailit-integration'); ?></span>
                    </div>
                    <div class="emailit-status-item">
                        <span class="status-icon">✅</span>
                        <span class="status-text"><?php _e('Query caching enabled', 'emailit-integration'); ?></span>
                    </div>
                    <div class="emailit-status-item">
                        <span class="status-icon">✅</span>
                        <span class="status-text"><?php _e('Indexes up to date', 'emailit-integration'); ?></span>
                    </div>
                </div>
                
                <div class="emailit-maintenance-tools">
                    <h4><?php _e('Quick Maintenance', 'emailit-integration'); ?></h4>
                    <p>
                        <button type="button" id="clean-old-logs" class="button button-secondary">
                            <?php _e('Clean Old Logs', 'emailit-integration'); ?>
                        </button>
                        <button type="button" id="optimize-database" class="button button-secondary">
                            <?php _e('Optimize Database', 'emailit-integration'); ?>
                        </button>
                        <button type="button" id="clear-cache" class="button button-secondary">
                            <?php _e('Clear Cache', 'emailit-integration'); ?>
                        </button>
                    </p>
                </div>
            </div>

            <!-- Bounce Classification Statistics -->
            <?php
            $bounce_classifier = new Emailit_Bounce_Classifier();
            $bounce_summary = $bounce_classifier->get_bounce_summary(30);
            $webhook = emailit_get_component('webhook');
            $fluentcrm_status = $webhook ? $webhook->get_fluentcrm_integration_status() : array('available' => false);
            ?>
            <div class="emailit-bounce-stats">
                <h3><?php _e('Bounce Classification Statistics', 'emailit-integration'); ?></h3>
                <p class="description">
                    <?php _e('Bounce classification provides insights into email deliverability issues. This data helps identify and resolve delivery problems.', 'emailit-integration'); ?>
                    <?php if ($fluentcrm_status['available']) : ?>
                    <br><strong><?php _e('FluentCRM Integration:', 'emailit-integration'); ?></strong> <?php _e('This data is also used for advanced subscriber management and automated bounce handling.', 'emailit-integration'); ?>
                    <?php else : ?>
                    <br><em><?php _e('Install FluentCRM to enable advanced subscriber management and automated bounce handling.', 'emailit-integration'); ?></em>
                    <?php endif; ?>
                </p>
                
                <?php if (!empty($bounce_summary['bounce_stats'])) : ?>
                <div class="emailit-bounce-overview">
                    <div class="emailit-bounce-metrics">
                        <div class="emailit-metric-card">
                            <div class="metric-label"><?php _e('Total Bounces', 'emailit-integration'); ?></div>
                            <div class="metric-value"><?php echo esc_html($bounce_summary['total_bounces']); ?></div>
                        </div>
                        <div class="emailit-metric-card">
                            <div class="metric-label"><?php _e('Period', 'emailit-integration'); ?></div>
                            <div class="metric-value"><?php echo esc_html($bounce_summary['period_days']); ?> <?php _e('days', 'emailit-integration'); ?></div>
                        </div>
                    </div>
                </div>

                <div class="emailit-bounce-breakdown">
                    <h4><?php _e('Bounce Classification Breakdown', 'emailit-integration'); ?></h4>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Classification', 'emailit-integration'); ?></th>
                                <th><?php _e('Category', 'emailit-integration'); ?></th>
                                <th><?php _e('Severity', 'emailit-integration'); ?></th>
                                <th><?php _e('Count', 'emailit-integration'); ?></th>
                                <th><?php _e('Avg Confidence', 'emailit-integration'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bounce_summary['bounce_stats'] as $stat) : ?>
                            <tr>
                                <td>
                                    <span class="emailit-bounce-classification <?php echo esc_attr($stat->bounce_classification); ?>">
                                        <?php echo esc_html(ucfirst(str_replace('_', ' ', $stat->bounce_classification))); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $stat->bounce_category))); ?></td>
                                <td>
                                    <span class="emailit-severity <?php echo esc_attr($stat->bounce_severity); ?>">
                                        <?php echo esc_html(ucfirst($stat->bounce_severity)); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($stat->count); ?></td>
                                <td><?php echo esc_html(round($stat->avg_confidence, 1)); ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else : ?>
                <div class="emailit-no-bounce-data">
                    <p><?php _e('No bounce classification data available for the selected period.', 'emailit-integration'); ?></p>
                </div>
                <?php endif; ?>
            </div>

            <?php submit_button(); ?>
        </div>

        <!-- Advanced Settings Tab -->
        <div id="advanced" class="emailit-tab-pane <?php echo $current_tab === 'advanced' ? 'active' : ''; ?>">
            <div class="emailit-advanced-intro">
                <h2><?php _e('Advanced Settings', 'emailit-integration'); ?></h2>
                <p class="description"><?php _e('Advanced configuration options for power users. These settings are optional and can be left at their defaults for most users.', 'emailit-integration'); ?></p>
                
                <div class="emailit-mode-notice basic-user-mode">
                    <div class="notice notice-info">
                        <p>
                            <span class="dashicons dashicons-info"></span>
                            <?php _e('Enable Power User Mode in the header to access advanced features and detailed configuration options.', 'emailit-integration'); ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Performance & Queue Settings -->
            <div class="emailit-advanced-section collapsible power-user-only">
                <div class="section-header">
                    <h3>
                        <?php _e('Performance & Queue Settings', 'emailit-integration'); ?>
                        <span class="emailit-help-tooltip">
                            <span class="help-icon">?</span>
                            <span class="tooltip-content">
                                <?php _e('Configure asynchronous email processing to improve site performance. Enable queueing to send emails in the background without blocking page loads.', 'emailit-integration'); ?>
                            </span>
                        </span>
                    </h3>
                    <span class="toggle-icon">▼</span>
                </div>
                <div class="section-content">
                    <p class="description"><?php _e('Configure asynchronous email processing for improved site performance.', 'emailit-integration'); ?></p>
                
                <table class="form-table emailit-form-table" role="presentation">
                    <tbody>
                        <?php do_settings_fields('emailit-settings', 'emailit_performance_section'); ?>
                    </tbody>
                </table>

                <div class="emailit-queue-status">
                    <h4><?php _e('Queue Status', 'emailit-integration'); ?></h4>
                    <div id="queue-status-info">
                        <div class="queue-stats">
                            <span class="queue-stat">
                                <strong><?php _e('Pending:', 'emailit-integration'); ?></strong>
                                <span id="queue-pending">-</span>
                            </span>
                            <span class="queue-stat">
                                <strong><?php _e('Processing:', 'emailit-integration'); ?></strong>
                                <span id="queue-processing">-</span>
                            </span>
                            <span class="queue-stat">
                                <strong><?php _e('Failed:', 'emailit-integration'); ?></strong>
                                <span id="queue-failed">-</span>
                            </span>
                        </div>
                        <p>
                            <button type="button" id="refresh-queue-stats" class="button button-secondary">
                                <?php _e('Refresh Stats', 'emailit-integration'); ?>
                            </button>
                            <button type="button" id="process-queue-now" class="button button-secondary">
                                <?php _e('Process Queue Now', 'emailit-integration'); ?>
                            </button>
                        </p>
                    </div>
                </div>
                </div>
            </div>

            <!-- Webhook Configuration -->
            <div class="emailit-advanced-section collapsible power-user-only">
                <div class="section-header">
                    <h3>
                        <?php _e('Webhook Configuration', 'emailit-integration'); ?>
                        <span class="emailit-help-tooltip">
                            <span class="help-icon">?</span>
                            <span class="tooltip-content">
                                <?php _e('Webhooks allow Emailit to send real-time status updates about your emails (delivered, bounced, opened, etc.) directly to your WordPress site.', 'emailit-integration'); ?>
                            </span>
                        </span>
                    </h3>
                    <span class="toggle-icon">▼</span>
                </div>
                <div class="section-content">
                    <p class="description"><?php _e('Configure webhooks to receive real-time email status updates from Emailit.', 'emailit-integration'); ?></p>

                <table class="form-table emailit-form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label><?php _e('Webhook URL', 'emailit-integration'); ?></label>
                            </th>
                            <td>
                                <code id="webhook-url"><?php echo esc_url(rest_url('emailit/v1/webhook')); ?></code>
                                <button type="button" class="button button-secondary" onclick="copyToClipboard('webhook-url')">
                                    <?php _e('Copy', 'emailit-integration'); ?>
                                </button>
                                <p class="description">
                                    <?php _e('Use this URL in your Emailit dashboard to configure webhooks.', 'emailit-integration'); ?>
                                </p>
                            </td>
                        </tr>
                        <?php do_settings_fields('emailit-settings', 'emailit_webhook_section'); ?>
                    </tbody>
                </table>

                <div class="emailit-webhook-test">
                    <h4><?php _e('Test Webhook', 'emailit-integration'); ?></h4>
                    <p><?php _e('Test your webhook endpoint to ensure it\'s working correctly.', 'emailit-integration'); ?></p>
                    <button type="button" id="test-webhook" class="button button-secondary">
                        <?php _e('Test Webhook', 'emailit-integration'); ?>
                    </button>
                    <div id="webhook-test-result" class="emailit-test-result" style="display: none;"></div>
                </div>
                </div>
            </div>

            <!-- Advanced Settings -->
            <div class="emailit-advanced-section collapsible power-user-only">
                <div class="section-header">
                    <h3>
                        <?php _e('Advanced Configuration', 'emailit-integration'); ?>
                        <span class="emailit-help-tooltip">
                            <span class="help-icon">?</span>
                            <span class="tooltip-content">
                                <?php _e('Advanced settings for experienced users. These options control low-level behavior and should only be changed if you understand their impact.', 'emailit-integration'); ?>
                            </span>
                        </span>
                    </h3>
                    <span class="toggle-icon">▼</span>
                </div>
                <div class="section-content">
                    <p class="description"><?php _e('Advanced configuration options for experienced users.', 'emailit-integration'); ?></p>
                
                <table class="form-table emailit-form-table" role="presentation">
                    <tbody>
                        <?php do_settings_fields('emailit-settings', 'emailit_advanced_section'); ?>
                    </tbody>
                </table>
                </div>
            </div>

            <!-- Performance Status -->
            <div class="emailit-advanced-section collapsible power-user-only">
                <div class="section-header">
                    <h3>
                        <?php _e('Performance Status', 'emailit-integration'); ?>
                        <span class="emailit-help-tooltip">
                            <span class="help-icon">?</span>
                            <span class="tooltip-content">
                                <?php _e('Monitor and optimize database performance. These tools help maintain optimal plugin performance over time.', 'emailit-integration'); ?>
                            </span>
                        </span>
                    </h3>
                    <span class="toggle-icon">▼</span>
                </div>
                <div class="section-content">
                    <p class="description"><?php _e('Database optimization and performance monitoring tools.', 'emailit-integration'); ?></p>
                
                <div class="emailit-performance-status">
                    <div class="emailit-status-grid">
                        <div class="emailit-status-item">
                            <span class="status-icon">✅</span>
                            <span class="status-text"><?php _e('Database optimized', 'emailit-integration'); ?></span>
                        </div>
                        <div class="emailit-status-item">
                            <span class="status-icon">✅</span>
                            <span class="status-text"><?php _e('Query caching enabled', 'emailit-integration'); ?></span>
                        </div>
                        <div class="emailit-status-item">
                            <span class="status-icon">✅</span>
                            <span class="status-text"><?php _e('Indexes up to date', 'emailit-integration'); ?></span>
                        </div>
                    </div>
                    
                    <div class="emailit-maintenance-tools">
                        <h4><?php _e('Quick Maintenance', 'emailit-integration'); ?></h4>
                        <p>
                            <button type="button" id="clean-old-logs" class="button button-secondary">
                                <?php _e('Clean Old Logs', 'emailit-integration'); ?>
                            </button>
                            <button type="button" id="optimize-database" class="button button-secondary">
                                <?php _e('Optimize Database', 'emailit-integration'); ?>
                            </button>
                            <button type="button" id="clear-cache" class="button button-secondary">
                                <?php _e('Clear Cache', 'emailit-integration'); ?>
                            </button>
                        </p>
                    </div>
                </div>
                </div>
            </div>

            <!-- Health Monitor -->
            <div class="emailit-advanced-section collapsible power-user-only">
                <div class="section-header">
                    <h3>
                        <?php _e('Health Monitoring', 'emailit-integration'); ?>
                        <span class="emailit-help-tooltip">
                            <span class="help-icon">?</span>
                            <span class="tooltip-content">
                                <?php _e('Monitor system health and performance. Get alerts about issues and track overall plugin performance over time.', 'emailit-integration'); ?>
                            </span>
                        </span>
                    </h3>
                    <span class="toggle-icon">▼</span>
                </div>
                <div class="section-content">
                    <p class="description"><?php _e('System health monitoring and diagnostics.', 'emailit-integration'); ?></p>
                
                <div class="emailit-health-overview">
                    <div class="health-status-cards">
                        <div class="health-card">
                            <h4><?php _e('System Health', 'emailit-integration'); ?></h4>
                            <div class="status-indicator" id="system-health">
                                <span class="status-text"><?php _e('Checking...', 'emailit-integration'); ?></span>
                            </div>
                        </div>
                        <div class="health-card">
                            <h4><?php _e('API Connectivity', 'emailit-integration'); ?></h4>
                            <div class="status-indicator" id="api-connectivity">
                                <span class="status-text"><?php _e('Checking...', 'emailit-integration'); ?></span>
                            </div>
                        </div>
                        <div class="health-card">
                            <h4><?php _e('Database Health', 'emailit-integration'); ?></h4>
                            <div class="status-indicator" id="database-health">
                                <span class="status-text"><?php _e('Checking...', 'emailit-integration'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <p>
                    <a href="<?php echo admin_url('tools.php?page=emailit-health-monitor'); ?>" class="button button-secondary">
                        <?php _e('View Detailed Health Monitor', 'emailit-integration'); ?>
                    </a>
                </p>
                </div>
            </div>

            <?php submit_button(); ?>
        </div>

        <!-- Webhook Settings Tab -->
        <div id="webhook" class="emailit-tab-pane <?php echo $current_tab === 'webhook' ? 'active' : ''; ?>">
            <h2><?php _e('Webhook Configuration', 'emailit-integration'); ?></h2>

            <div class="emailit-webhook-info">
                <p><?php _e('Configure webhooks to receive real-time email status updates from Emailit.', 'emailit-integration'); ?></p>

                <table class="form-table emailit-form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label><?php _e('Webhook URL', 'emailit-integration'); ?></label>
                            </th>
                            <td>
                                <code id="webhook-url"><?php echo esc_url(rest_url('emailit/v1/webhook')); ?></code>
                                <button type="button" class="button button-secondary" onclick="copyToClipboard('webhook-url')">
                                    <?php _e('Copy', 'emailit-integration'); ?>
                                </button>
                                <p class="description">
                                    <?php _e('Use this URL in your Emailit dashboard to configure webhooks.', 'emailit-integration'); ?>
                                </p>
                            </td>
                        </tr>
                        <?php do_settings_fields('emailit-settings', 'emailit_webhook_section'); ?>
                    </tbody>
                </table>

                    <!-- Webhook Filtering Information -->
                    <div class="emailit-webhook-filtering">
                        <h3><?php _e('Site-Specific Webhook Filtering', 'emailit-integration'); ?></h3>
                        <div class="notice notice-info inline">
                            <p>
                                <span class="dashicons dashicons-filter" style="color: #0073aa;"></span>
                                <?php _e('This plugin automatically filters webhook events to only process emails sent from this site.', 'emailit-integration'); ?>
                            </p>
                        </div>

                        <?php
                        // Get webhook component to show filtering info
                        $webhook = emailit_get_component('webhook');
                        if ($webhook) {
                            // Get site domain and recognized emails
                            $site_url = get_site_url();
                            $parsed = parse_url($site_url);
                            $site_domain = isset($parsed['host']) ? $parsed['host'] : '';

                            // Get configured emails
                            $admin_email = get_bloginfo('admin_email');
                            $emailit_from = get_option('emailit_from_email', '');
                        ?>

                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Filtering Criteria', 'emailit-integration'); ?></th>
                                    <th><?php _e('Value', 'emailit-integration'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong><?php _e('Site Domain', 'emailit-integration'); ?></strong></td>
                                    <td><code><?php echo esc_html($site_domain); ?></code></td>
                                </tr>
                                <tr>
                                    <td><strong><?php _e('Admin Email', 'emailit-integration'); ?></strong></td>
                                    <td><code><?php echo esc_html($admin_email); ?></code></td>
                                </tr>
                                <?php if (!empty($emailit_from) && $emailit_from !== $admin_email) : ?>
                                <tr>
                                    <td><strong><?php _e('Configured From Email', 'emailit-integration'); ?></strong></td>
                                    <td><code><?php echo esc_html($emailit_from); ?></code></td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <td><strong><?php _e('Common Site Emails', 'emailit-integration'); ?></strong></td>
                                    <td>
                                        <?php
                                        $common_emails = array(
                                            'wordpress@' . $site_domain,
                                            'admin@' . $site_domain,
                                            'noreply@' . $site_domain,
                                            'no-reply@' . $site_domain
                                        );
                                        foreach ($common_emails as $email) {
                                            echo '<code>' . esc_html($email) . '</code><br>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <p class="description">
                            <?php _e('Only webhook events for emails matching these criteria will be processed and logged. Emails sent from other sites in your Emailit workspace will be ignored.', 'emailit-integration'); ?>
                        </p>

                        <?php } ?>
                    </div>

                    <div class="emailit-webhook-test">
                        <h3><?php _e('Test Webhook', 'emailit-integration'); ?></h3>
                        <p><?php _e('Test your webhook endpoint to ensure it\'s working correctly.', 'emailit-integration'); ?></p>
                        <button type="button" id="test-webhook" class="button button-secondary">
                            <?php _e('Test Webhook', 'emailit-integration'); ?>
                        </button>
                        <div id="webhook-test-result" class="emailit-test-result" style="display: none;"></div>
                    </div>
                </div>

                <?php submit_button(); ?>

                <!-- Webhook Logs Section -->
                <div class="emailit-webhook-logs-section" style="margin-top: 40px; padding-top: 30px; border-top: 1px solid #ddd;">
                    <h2><?php _e('Webhook Activity & Logs', 'emailit-integration'); ?></h2>
                    <p><?php _e('Monitor webhook activity and view recent webhook events.', 'emailit-integration'); ?></p>
                    
                    <?php
                    // Load webhook logs data for this tab
                    $webhook_monitor = emailit_get_component('webhook_monitor');
                    if ($webhook_monitor) {
                        $webhook_health = $webhook_monitor->get_webhook_health_status();
                        $webhook_stats = $webhook_monitor->get_webhook_statistics(7);
                        $recent_webhooks = $webhook_monitor->get_recent_webhook_activity(20); // Show fewer in settings
                        $webhook_alerts = $webhook_monitor->get_webhook_alerts();
                        
                        // Set embedded flag for webhook logs view
                        $is_embedded = true;
                        
                        // Include the webhook logs view
                        include EMAILIT_PLUGIN_DIR . 'admin/views/webhook-logs.php';
                    } else {
                        echo '<p>' . __('Webhook monitoring is not available.', 'emailit-integration') . '</p>';
                    }
                    ?>
                </div>
        </div>

        <!-- FluentCRM Integration Tab -->
        <?php if ($fluentcrm_status['available']) : ?>
        <div id="fluentcrm" class="emailit-tab-pane <?php echo $current_tab === 'fluentcrm' ? 'active' : ''; ?>">
            <h2><?php _e('FluentCRM Integration', 'emailit-integration'); ?></h2>

            <div class="emailit-fluentcrm-info">
                <p><?php _e('Configure FluentCRM integration for seamless bounce handling and subscriber management.', 'emailit-integration'); ?></p>

                <table class="form-table emailit-form-table" role="presentation">
                    <tbody>
                        <?php do_settings_fields('emailit-settings', 'emailit_fluentcrm_section'); ?>
                    </tbody>
                </table>

                <!-- FluentCRM Status Information -->
                <div class="emailit-fluentcrm-status">
                    <h3><?php _e('Integration Status', 'emailit-integration'); ?></h3>
                    <?php
                    $webhook = emailit_get_component('webhook');
                    $fluentcrm_status = $webhook ? $webhook->get_fluentcrm_integration_status() : array('available' => false);
                    
                    if ($fluentcrm_status['available']) {
                        echo '<div class="notice notice-success inline">';
                        echo '<p><span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> ';
                        echo sprintf(__('FluentCRM %s is active and ready for integration.', 'emailit-integration'), $fluentcrm_status['version'] ?: 'Unknown Version');
                        echo '</p></div>';
                        
                        // Show integration details
                        echo '<table class="widefat striped" style="margin-top: 15px;">';
                        echo '<thead><tr><th>' . __('Feature', 'emailit-integration') . '</th><th>' . __('Status', 'emailit-integration') . '</th></tr></thead>';
                        echo '<tbody>';
                        
                        $integration_enabled = get_option('emailit_fluentcrm_integration', 1);
                        $forward_bounces = get_option('emailit_fluentcrm_forward_bounces', 1);
                        $suppress_default = get_option('emailit_fluentcrm_suppress_default', 0);
                        
                        echo '<tr><td><strong>' . __('Integration Enabled', 'emailit-integration') . '</strong></td>';
                        echo '<td>' . ($integration_enabled ? '<span style="color: #46b450;">✓ ' . __('Yes', 'emailit-integration') . '</span>' : '<span style="color: #d63638;">✗ ' . __('No', 'emailit-integration') . '</span>') . '</td></tr>';
                        
                        echo '<tr><td><strong>' . __('Bounce Forwarding', 'emailit-integration') . '</strong></td>';
                        echo '<td>' . ($forward_bounces ? '<span style="color: #46b450;">✓ ' . __('Enabled', 'emailit-integration') . '</span>' : '<span style="color: #d63638;">✗ ' . __('Disabled', 'emailit-integration') . '</span>') . '</td></tr>';
                        
                        echo '<tr><td><strong>' . __('Suppress Default Emails', 'emailit-integration') . '</strong></td>';
                        echo '<td>' . ($suppress_default ? '<span style="color: #46b450;">✓ ' . __('Yes', 'emailit-integration') . '</span>' : '<span style="color: #666;">- ' . __('No', 'emailit-integration') . '</span>') . '</td></tr>';
                        
                        echo '<tr><td><strong>' . __('Hard Bounce Action', 'emailit-integration') . '</strong></td>';
                        echo '<td>' . esc_html(ucfirst(get_option('emailit_fluentcrm_hard_bounce_action', 'unsubscribe'))) . '</td></tr>';
                        
                        echo '<tr><td><strong>' . __('Soft Bounce Action', 'emailit-integration') . '</strong></td>';
                        echo '<td>' . esc_html(ucfirst(get_option('emailit_fluentcrm_soft_bounce_action', 'track'))) . '</td></tr>';
                        
                        echo '<tr><td><strong>' . __('Soft Bounce Threshold', 'emailit-integration') . '</strong></td>';
                        echo '<td>' . esc_html(get_option('emailit_fluentcrm_soft_bounce_threshold', 5)) . '</td></tr>';
                        
                        echo '<tr><td><strong>' . __('Complaint Action', 'emailit-integration') . '</strong></td>';
                        echo '<td>' . esc_html(ucfirst(get_option('emailit_fluentcrm_complaint_action', 'unsubscribe'))) . '</td></tr>';
                        
                        // Action mapping settings
                        $action_mapping_enabled = get_option('emailit_fluentcrm_enable_action_mapping', true);
                        $auto_create_enabled = get_option('emailit_fluentcrm_auto_create_subscribers', true);
                        $confidence_threshold = get_option('emailit_fluentcrm_confidence_threshold', 70);
                        
                        echo '<tr><td><strong>' . __('Action Mapping', 'emailit-integration') . '</strong></td>';
                        echo '<td>' . ($action_mapping_enabled ? '<span style="color: #46b450;">✓ ' . __('Enabled', 'emailit-integration') . '</span>' : '<span style="color: #d63638;">✗ ' . __('Disabled', 'emailit-integration') . '</span>') . '</td></tr>';
                        
                        echo '<tr><td><strong>' . __('Auto-Create Subscribers', 'emailit-integration') . '</strong></td>';
                        echo '<td>' . ($auto_create_enabled ? '<span style="color: #46b450;">✓ ' . __('Enabled', 'emailit-integration') . '</span>' : '<span style="color: #d63638;">✗ ' . __('Disabled', 'emailit-integration') . '</span>') . '</td></tr>';
                        
                        echo '<tr><td><strong>' . __('Confidence Threshold', 'emailit-integration') . '</strong></td>';
                        echo '<td>' . esc_html($confidence_threshold) . '%</td></tr>';
                        
                        echo '</tbody></table>';
                        
                        // Action mapping statistics
                        $fluentcrm_handler = emailit_get_component('fluentcrm_handler');
                        if ($fluentcrm_handler && $fluentcrm_handler->is_available()) {
                            $integration_stats = $fluentcrm_handler->get_integration_status();
                            
                            echo '<div class="emailit-fluentcrm-stats" style="margin-top: 20px;">';
                            echo '<h4>' . __('Action Mapping Statistics', 'emailit-integration') . '</h4>';
                            echo '<table class="widefat striped">';
                            echo '<thead><tr><th>' . __('Metric', 'emailit-integration') . '</th><th>' . __('Value', 'emailit-integration') . '</th></tr></thead>';
                            echo '<tbody>';
                            echo '<tr><td><strong>' . __('Total Subscribers', 'emailit-integration') . '</strong></td>';
                            echo '<td>' . number_format($integration_stats['subscriber_count']) . '</td></tr>';
                            echo '<tr><td><strong>' . __('Bounce Actions Today', 'emailit-integration') . '</strong></td>';
                            echo '<td>' . number_format($integration_stats['bounce_actions_today']) . '</td></tr>';
                            echo '<tr><td><strong>' . __('Bounce Actions This Week', 'emailit-integration') . '</strong></td>';
                            echo '<td>' . number_format($integration_stats['bounce_actions_week']) . '</td></tr>';
                            echo '</tbody></table>';
                            echo '</div>';

                            // Soft bounce management section
                            $soft_bounce_stats = $integration_stats['soft_bounce_stats'] ?? array();
                            if (!empty($soft_bounce_stats)) {
                                echo '<div class="emailit-soft-bounce-management" style="margin-top: 20px;">';
                                echo '<h4>' . __('Soft Bounce Management', 'emailit-integration') . '</h4>';
                                echo '<table class="widefat striped">';
                                echo '<thead><tr><th>' . __('Metric', 'emailit-integration') . '</th><th>' . __('Value', 'emailit-integration') . '</th><th>' . __('Action', 'emailit-integration') . '</th></tr></thead>';
                                echo '<tbody>';
                                
                                echo '<tr>';
                                echo '<td><strong>' . __('Subscribers with Soft Bounces', 'emailit-integration') . '</strong></td>';
                                echo '<td>' . number_format($soft_bounce_stats['subscribers_with_bounces']) . '</td>';
                                echo '<td><button type="button" class="button button-small" onclick="emailitRefreshSoftBounceStats()">' . __('Refresh', 'emailit-integration') . '</button></td>';
                                echo '</tr>';
                                
                                echo '<tr>';
                                echo '<td><strong>' . __('Approaching Threshold', 'emailit-integration') . '</strong></td>';
                                $approaching_count = $soft_bounce_stats['approaching_threshold'] ?? 0;
                                $threshold = $soft_bounce_stats['threshold'] ?? 5;
                                echo '<td><span style="color: ' . ($approaching_count > 0 ? '#f56e28' : '#46b450') . ';">' . number_format($approaching_count) . '</span></td>';
                                echo '<td>';
                                if ($approaching_count > 0) {
                                    echo '<span style="color: #f56e28;">⚠️ ' . sprintf(__('Monitor closely', 'emailit-integration'), $threshold - 1) . '</span>';
                                } else {
                                    echo '<span style="color: #46b450;">✓ ' . __('All good', 'emailit-integration') . '</span>';
                                }
                                echo '</td>';
                                echo '</tr>';
                                
                                echo '<tr>';
                                echo '<td><strong>' . __('Soft Bounces Today', 'emailit-integration') . '</strong></td>';
                                echo '<td>' . number_format($soft_bounce_stats['soft_bounces_today']) . '</td>';
                                echo '<td><span class="description">' . __('Current threshold: %d bounces', 'emailit-integration') . '</span></td>';
                                echo '</tr>';
                                
                                echo '</tbody></table>';
                                echo '</div>';
                            }
                        }
                        
                    } else {
                        echo '<div class="notice notice-warning inline">';
                        echo '<p><span class="dashicons dashicons-warning" style="color: #f56e28;"></span> ';
                        echo __('FluentCRM is not installed or active. Install and activate FluentCRM to enable advanced email management features.', 'emailit-integration');
                        echo '</p></div>';
                        
                        echo '<div class="emailit-fluentcrm-install-info">';
                        echo '<h4>' . __('How to Install FluentCRM', 'emailit-integration') . '</h4>';
                        echo '<ol>';
                        echo '<li>' . __('Go to Plugins → Add New in your WordPress admin', 'emailit-integration') . '</li>';
                        echo '<li>' . __('Search for "FluentCRM"', 'emailit-integration') . '</li>';
                        echo '<li>' . __('Install and activate the FluentCRM plugin', 'emailit-integration') . '</li>';
                        echo '<li>' . __('Return to this page to configure the integration', 'emailit-integration') . '</li>';
                        echo '</ol>';
                        echo '<p><a href="' . admin_url('plugin-install.php?s=fluentcrm&tab=search&type=term') . '" class="button button-primary">' . __('Install FluentCRM', 'emailit-integration') . '</a></p>';
                        echo '</div>';
                    }
                    ?>
                </div>

                <!-- FluentCRM Integration Benefits -->
                <div class="emailit-fluentcrm-benefits">
                    <h3><?php _e('Integration Benefits', 'emailit-integration'); ?></h3>
                    <div class="emailit-benefits-grid">
                        <div class="emailit-benefit-item">
                            <span class="dashicons dashicons-email-alt" style="color: #0073aa;"></span>
                            <h4><?php _e('Automatic Bounce Handling', 'emailit-integration'); ?></h4>
                            <p><?php _e('Automatically sync bounce data between FluentCRM and Emailit for better deliverability management.', 'emailit-integration'); ?></p>
                        </div>
                        <div class="emailit-benefit-item">
                            <span class="dashicons dashicons-chart-line" style="color: #0073aa;"></span>
                            <h4><?php _e('Enhanced Analytics', 'emailit-integration'); ?></h4>
                            <p><?php _e('Get detailed insights into email performance and subscriber engagement across both platforms.', 'emailit-integration'); ?></p>
                        </div>
                        <div class="emailit-benefit-item">
                            <span class="dashicons dashicons-shield" style="color: #0073aa;"></span>
                            <h4><?php _e('Improved Deliverability', 'emailit-integration'); ?></h4>
                            <p><?php _e('Better email reputation management through coordinated bounce handling and subscriber management.', 'emailit-integration'); ?></p>
                        </div>
                        <div class="emailit-benefit-item">
                            <span class="dashicons dashicons-admin-tools" style="color: #0073aa;"></span>
                            <h4><?php _e('Unified Management', 'emailit-integration'); ?></h4>
                            <p><?php _e('Manage all your email campaigns and subscriber data from a single, integrated platform.', 'emailit-integration'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <?php submit_button(); ?>
        </div>
        <?php endif; ?>

        <!-- Test Settings Tab -->
        <div id="test" class="emailit-tab-pane <?php echo $current_tab === 'test' ? 'active' : ''; ?>">
                <div class="emailit-test-email">
                    <h3><?php _e('Send Test Email', 'emailit-integration'); ?></h3>
                    <p><?php _e('Send a test email to verify your Emailit integration is working correctly.', 'emailit-integration'); ?></p>

                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label for="emailit_test_email"><?php _e('Test Email Address', 'emailit-integration'); ?></label>
                                </th>
                                <td>
                                    <input type="email" id="emailit_test_email" class="regular-text"
                                           value="<?php echo esc_attr(get_bloginfo('admin_email')); ?>" />
                                    <p class="description">
                                        <?php _e('Email address to send the test email to.', 'emailit-integration'); ?>
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <p>
                        <button type="button" id="emailit-test-email" class="button button-primary">
                            <?php _e('Send Test Email', 'emailit-integration'); ?>
                        </button>
                    </p>

                    <div id="emailit-test-result" class="emailit-test-result" style="display: none;"></div>
                </div>

                <!-- WordPress wp_mail Test -->
                <div class="emailit-wordpress-test">
                    <h3><?php _e('WordPress Email Test', 'emailit-integration'); ?></h3>
                    <p><?php _e('Send a test email through WordPress wp_mail() function to diagnose integration issues. This test uses the same path as contact forms and other WordPress features.', 'emailit-integration'); ?></p>

                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label for="emailit_wordpress_test_email"><?php _e('Test Email Address', 'emailit-integration'); ?></label>
                                </th>
                                <td>
                                    <input type="email" id="emailit_wordpress_test_email" class="regular-text"
                                           value="<?php echo esc_attr(get_bloginfo('admin_email')); ?>" />
                                    <p class="description">
                                        <?php _e('Email address to send the WordPress test email to.', 'emailit-integration'); ?>
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <p>
                        <button type="button" id="emailit-wordpress-test" class="button button-secondary">
                            <?php _e('Send WordPress Test Email', 'emailit-integration'); ?>
                        </button>
                        <span class="description" style="margin-left: 10px;">
                            <?php _e('This will help diagnose critical errors during email sending.', 'emailit-integration'); ?>
                        </span>
                    </p>

                    <div id="emailit-wordpress-test-result" class="emailit-test-result" style="display: none;"></div>
                </div>

                <?php if (defined('WP_DEBUG') && WP_DEBUG) : ?>
                <!-- Diagnostic Test -->
                <div class="emailit-diagnostic-test">
                    <h3><?php _e('Plugin Diagnostic', 'emailit-integration'); ?></h3>
                    <p><?php _e('Test if the plugin is loading correctly and AJAX functionality is working.', 'emailit-integration'); ?></p>

                    <p>
                        <button type="button" id="emailit-diagnostic" class="button button-secondary">
                            <?php _e('Run Diagnostic Test', 'emailit-integration'); ?>
                        </button>
                        <span class="description" style="margin-left: 10px;">
                            <?php _e('This will help identify plugin loading issues.', 'emailit-integration'); ?>
                        </span>
                    </p>

                    <div id="emailit-diagnostic-result" class="emailit-test-result" style="display: none;"></div>
                </div>
                <?php endif; ?>

                <!-- Email Statistics -->
                <div class="emailit-stats-section">
                    <h3><?php _e('Email Statistics (Last 30 Days)', 'emailit-integration'); ?></h3>
                    <div id="emailit-stats" class="emailit-stats">
                        <div class="emailit-loading"><?php _e('Loading statistics...', 'emailit-integration'); ?></div>
                    </div>
                </div>

                <!-- Plugin Conflict Check -->
                <div class="emailit-conflict-check">
                    <h3><?php _e('Plugin Compatibility Check', 'emailit-integration'); ?></h3>
                    <?php
                    $admin = emailit_get_component('admin');
                    $conflicts = $admin ? $admin->get_plugin_conflicts() : array();

                    if (empty($conflicts)) {
                        echo '<div class="notice notice-success inline">';
                        echo '<p><span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> ';
                        echo __('No email plugin conflicts detected.', 'emailit-integration') . '</p>';
                        echo '</div>';
                    } else {
                        echo '<div class="notice notice-warning inline">';
                        echo '<p><span class="dashicons dashicons-warning" style="color: #f56e28;"></span> ';
                        echo sprintf(_n('%d potential conflict detected:', '%d potential conflicts detected:', count($conflicts), 'emailit-integration'), count($conflicts)) . '</p>';
                        echo '<ul style="margin-left: 20px;">';
                        foreach ($conflicts as $conflict) {
                            echo '<li><strong>' . esc_html($conflict['name']) . '</strong> - ' . esc_html($conflict['reason']) . '</li>';
                        }
                        echo '</ul>';
                        echo '<p><em>' . __('These plugins may interfere with Emailit\'s email delivery. Consider deactivating them or testing thoroughly.', 'emailit-integration') . '</em></p>';
                        echo '</div>';
                    }
                    ?>
                </div>

                <!-- System Information -->
                <div class="emailit-system-info">
                    <h3><?php _e('System Information', 'emailit-integration'); ?></h3>
                    <table class="widefat striped">
                        <tbody>
                            <tr>
                                <td><strong><?php _e('Plugin Version', 'emailit-integration'); ?></strong></td>
                                <td><?php echo esc_html(EMAILIT_VERSION); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('WordPress Version', 'emailit-integration'); ?></strong></td>
                                <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('PHP Version', 'emailit-integration'); ?></strong></td>
                                <td><?php echo esc_html(PHP_VERSION); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('API Endpoint', 'emailit-integration'); ?></strong></td>
                                <td><code><?php echo esc_html(EMAILIT_API_ENDPOINT); ?></code></td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('Webhook Endpoint', 'emailit-integration'); ?></strong></td>
                                <td><code><?php echo esc_url(rest_url('emailit/v1/webhook')); ?></code></td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('Logging Enabled', 'emailit-integration'); ?></strong></td>
                                <td><?php echo get_option('emailit_enable_logging') ? __('Yes', 'emailit-integration') : __('No', 'emailit-integration'); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('Fallback Enabled', 'emailit-integration'); ?></strong></td>
                                <td><?php echo get_option('emailit_fallback_enabled') ? __('Yes', 'emailit-integration') : __('No', 'emailit-integration'); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('wp_mail() Status', 'emailit-integration'); ?></strong></td>
                                <td>
                                    <?php
                                    if (function_exists('wp_mail')) {
                                        $reflection = new ReflectionFunction('wp_mail');
                                        $filename = $reflection->getFileName();
                                        if ($filename && strpos($filename, 'wp-includes/pluggable.php') !== false) {
                                            echo '<span style="color: #46b450;">✓ ' . __('WordPress Default', 'emailit-integration') . '</span>';
                                        } else {
                                            echo '<span style="color: #f56e28;">⚠ ' . sprintf(__('Overridden in %s', 'emailit-integration'), basename($filename)) . '</span>';
                                        }
                                    } else {
                                        echo '<span style="color: #d63638;">✗ ' . __('Function not available', 'emailit-integration') . '</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('Email Hooks Active', 'emailit-integration'); ?></strong></td>
                                <td>
                                    <?php
                                    global $wp_filter;
                                    $pre_wp_mail_count = isset($wp_filter['pre_wp_mail']) ? count($wp_filter['pre_wp_mail']->callbacks) : 0;
                                    $phpmailer_count = isset($wp_filter['phpmailer_init']) ? count($wp_filter['phpmailer_init']->callbacks) : 0;

                                    printf(
                                        __('pre_wp_mail: %d, phpmailer_init: %d', 'emailit-integration'),
                                        $pre_wp_mail_count,
                                        $phpmailer_count
                                    );
                                    ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
        </div>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Test webhook functionality
    $('#test-webhook').on('click', function() {
        var $button = $(this);
        var $result = $('#webhook-test-result');

        $button.prop('disabled', true).text('<?php _e('Testing...', 'emailit-integration'); ?>');
        $result.hide().removeClass('success error');

        $.post(ajaxurl, {
            action: 'emailit_test_webhook',
            nonce: '<?php echo wp_create_nonce('emailit_admin_nonce'); ?>'
        })
        .done(function(response) {
            if (response.success) {
                $result.addClass('success').html('<strong><?php _e('Success:', 'emailit-integration'); ?></strong> ' + response.data.message);
            } else {
                $result.addClass('error').html('<strong><?php _e('Error:', 'emailit-integration'); ?></strong> ' + response.data.message);
            }
        })
        .fail(function() {
            $result.addClass('error').html('<strong><?php _e('Error:', 'emailit-integration'); ?></strong> <?php _e('Failed to test webhook.', 'emailit-integration'); ?>');
        })
        .always(function() {
            $button.prop('disabled', false).text('<?php _e('Test Webhook', 'emailit-integration'); ?>');
            $result.show().addClass('show');
        });
    });

    // Prevent form submission when pressing Enter on test email field
    $('#emailit_test_email').on('keypress', function(e) {
        if (e.which === 13 || e.keyCode === 13) { // Enter key
            e.preventDefault();
            $('#emailit-test-email').trigger('click'); // Trigger test email button instead
            return false;
        }
    });

    // Soft bounce management functionality
    window.emailitRefreshSoftBounceStats = function() {
        $.post(ajaxurl, {
            action: 'emailit_get_soft_bounce_stats',
            nonce: '<?php echo wp_create_nonce('emailit_admin_nonce'); ?>'
        })
        .done(function(response) {
            if (response.success) {
                // Update the soft bounce stats display
                var stats = response.data;
                $('.emailit-soft-bounce-management tbody tr').each(function() {
                    var $row = $(this);
                    var metric = $row.find('td:first strong').text();
                    
                    if (metric.includes('Subscribers with Soft Bounces')) {
                        $row.find('td:nth-child(2)').text(stats.subscribers_with_bounces.toLocaleString());
                    } else if (metric.includes('Approaching Threshold')) {
                        var approachingCount = stats.approaching_threshold || 0;
                        var threshold = stats.threshold || 5;
                        $row.find('td:nth-child(2)').html('<span style="color: ' + (approachingCount > 0 ? '#f56e28' : '#46b450') + ';">' + approachingCount.toLocaleString() + '</span>');
                        var actionText = approachingCount > 0 ? 
                            '⚠️ Monitor closely' : 
                            '✓ All good';
                        $row.find('td:nth-child(3)').html('<span style="color: ' + (approachingCount > 0 ? '#f56e28' : '#46b450') + ';">' + actionText + '</span>');
                    } else if (metric.includes('Soft Bounces Today')) {
                        $row.find('td:nth-child(2)').text(stats.soft_bounces_today.toLocaleString());
                        $row.find('td:nth-child(3)').html('<span class="description">Current threshold: ' + threshold + ' bounces</span>');
                    }
                });
            }
        })
        .fail(function() {
            alert('<?php _e('Failed to refresh soft bounce statistics.', 'emailit-integration'); ?>');
        });
    };

    // Copy to clipboard functionality
    window.copyToClipboard = function(elementId) {
        var element = document.getElementById(elementId);
        var text = element.textContent || element.innerText;

        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function() {
                alert('<?php _e('Copied to clipboard!', 'emailit-integration'); ?>');
            });
        } else {
            // Fallback for older browsers
            var textArea = document.createElement("textarea");
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            alert('<?php _e('Copied to clipboard!', 'emailit-integration'); ?>');
        }
    };

    // Regenerate webhook secret
    $('#regenerate-webhook-secret').on('click', function() {
        if (confirm('<?php _e('Are you sure you want to regenerate the webhook secret? You will need to update it in your Emailit dashboard.', 'emailit-integration'); ?>')) {
            var newSecret = generateRandomString(32);
            $('#emailit_webhook_secret').val(newSecret);
        }
    });

    function generateRandomString(length) {
        var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        var result = '';
        for (var i = 0; i < length; i++) {
            result += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        return result;
    }

    // Maintenance tools functionality
    $('#clean-old-logs').on('click', function() {
        var $button = $(this);
        $button.prop('disabled', true).text('<?php _e('Cleaning...', 'emailit-integration'); ?>');
        
        $.post(ajaxurl, {
            action: 'emailit_clean_old_logs',
            nonce: '<?php echo wp_create_nonce('emailit_admin_nonce'); ?>'
        })
        .done(function(response) {
            if (response.success) {
                alert('<?php _e('Old logs cleaned successfully!', 'emailit-integration'); ?>');
            } else {
                alert('<?php _e('Error cleaning logs:', 'emailit-integration'); ?> ' + response.data.message);
            }
        })
        .fail(function() {
            alert('<?php _e('Failed to clean logs.', 'emailit-integration'); ?>');
        })
        .always(function() {
            $button.prop('disabled', false).text('<?php _e('Clean Old Logs', 'emailit-integration'); ?>');
        });
    });

    $('#optimize-database').on('click', function() {
        var $button = $(this);
        $button.prop('disabled', true).text('<?php _e('Optimizing...', 'emailit-integration'); ?>');
        
        $.post(ajaxurl, {
            action: 'emailit_optimize_database',
            nonce: '<?php echo wp_create_nonce('emailit_admin_nonce'); ?>'
        })
        .done(function(response) {
            if (response.success) {
                alert('<?php _e('Database optimized successfully!', 'emailit-integration'); ?>');
            } else {
                alert('<?php _e('Error optimizing database:', 'emailit-integration'); ?> ' + response.data.message);
            }
        })
        .fail(function() {
            alert('<?php _e('Failed to optimize database.', 'emailit-integration'); ?>');
        })
        .always(function() {
            $button.prop('disabled', false).text('<?php _e('Optimize Database', 'emailit-integration'); ?>');
        });
    });

    $('#clear-cache').on('click', function() {
        var $button = $(this);
        $button.prop('disabled', true).text('<?php _e('Clearing...', 'emailit-integration'); ?>');
        
        $.post(ajaxurl, {
            action: 'emailit_clear_cache',
            nonce: '<?php echo wp_create_nonce('emailit_admin_nonce'); ?>'
        })
        .done(function(response) {
            if (response.success) {
                alert('<?php _e('Cache cleared successfully!', 'emailit-integration'); ?>');
            } else {
                alert('<?php _e('Error clearing cache:', 'emailit-integration'); ?> ' + response.data.message);
            }
        })
        .fail(function() {
            alert('<?php _e('Failed to clear cache.', 'emailit-integration'); ?>');
        })
        .always(function() {
            $button.prop('disabled', false).text('<?php _e('Clear Cache', 'emailit-integration'); ?>');
        });
    });

    // Load status data for the new interface
    function loadStatusData() {
        loadAPIStatus();
        loadRecentActivity();
        loadQueueStatus();
        loadQuickStats();
        loadWebhookStatus();
    }

    function loadAPIStatus() {
        $.post(ajaxurl, {
            action: 'emailit_get_api_status',
            nonce: '<?php echo wp_create_nonce('emailit_admin_nonce'); ?>'
        })
        .done(function(response) {
            if (response.success) {
                var status = response.data.status;
                var $indicator = $('#api-status .status-text');
                $indicator.removeClass('success warning error').addClass(status);
                $indicator.text(response.data.message);
            }
        });
    }

    function loadRecentActivity() {
        $.post(ajaxurl, {
            action: 'emailit_get_recent_activity',
            nonce: '<?php echo wp_create_nonce('emailit_admin_nonce'); ?>'
        })
        .done(function(response) {
            if (response.success) {
                $('#recent-activity').text(response.data.message);
            }
        });
    }

    function loadQueueStatus() {
        $.post(ajaxurl, {
            action: 'emailit_get_queue_status',
            nonce: '<?php echo wp_create_nonce('emailit_admin_nonce'); ?>'
        })
        .done(function(response) {
            if (response.success) {
                $('#queue-status').text(response.data.message);
            }
        });
    }

    function loadQuickStats() {
        $.post(ajaxurl, {
            action: 'emailit_get_quick_stats',
            nonce: '<?php echo wp_create_nonce('emailit_admin_nonce'); ?>'
        })
        .done(function(response) {
            if (response.success) {
                $('#today-emails').text(response.data.today_emails);
                $('#success-rate').text(response.data.success_rate + '%');
                $('#failed-emails').text(response.data.failed_emails);
                $('#queue-count').text(response.data.queue_count);
            }
        });
    }

    function loadWebhookStatus() {
        $.post(ajaxurl, {
            action: 'emailit_get_webhook_status',
            nonce: '<?php echo wp_create_nonce('emailit_admin_nonce'); ?>'
        })
        .done(function(response) {
            if (response.success) {
                $('#webhook-status .status-text').text(response.data.status);
                $('#webhook-count').text(response.data.count);
                $('#last-webhook').text(response.data.last_webhook);
            }
        });
    }

    // Load status data on page load
    loadStatusData();
    loadHealthScore();

    // Health Score Dashboard
    function loadHealthScore() {
        $.post(ajaxurl, {
            action: 'emailit_get_health_score',
            nonce: '<?php echo wp_create_nonce('emailit_admin_nonce'); ?>'
        })
        .done(function(response) {
            if (response.success) {
                updateHealthScore(response.data);
            }
        })
        .fail(function() {
            // Show error state
            $('#health-score').text('?');
            $('.metric-status').removeClass('excellent good warning error').addClass('error');
            $('.status-text').text('<?php _e('Error', 'emailit-integration'); ?>');
        });
    }

    function updateHealthScore(data) {
        // Update overall health score
        $('#health-score').text(data.overall_score);
        
        // Update individual metrics
        updateMetricCard('#api-status', '#api-detail', data.api_status);
        updateMetricCard('#queue-status', '#queue-detail', data.queue_status);
        updateMetricCard('#error-status', '#error-detail', data.error_rate);
        updateMetricCard('#webhook-status', '#webhook-detail', data.webhook_status);
        
        // Update recommendations
        updateRecommendations(data.recommendations);
    }

    function updateMetricCard(statusSelector, detailSelector, data) {
        var $status = $(statusSelector);
        var $detail = $(detailSelector);
        
        // Update status text and class
        $status.find('.status-text').text(data.status_text);
        $status.removeClass('excellent good warning error').addClass(data.status);
        
        // Update detail text
        $detail.text(data.detail);
    }

    function updateRecommendations(recommendations) {
        var $list = $('#health-recommendations');
        $list.empty();
        
        if (recommendations && recommendations.length > 0) {
            recommendations.forEach(function(rec) {
                $list.append('<li>' + rec + '</li>');
            });
        } else {
            $list.append('<li><?php _e('No recommendations available', 'emailit-integration'); ?></li>');
        }
    }

    // Auto-refresh health score every 30 seconds
    setInterval(loadHealthScore, 30000);

    // Power User Mode Toggle
    $('#power-user-mode').on('change', function() {
        var isPowerUser = $(this).is(':checked');
        
        $.post(ajaxurl, {
            action: 'emailit_toggle_power_user_mode',
            nonce: '<?php echo wp_create_nonce('emailit_admin_nonce'); ?>'
        })
        .done(function(response) {
            if (response.success) {
                // Toggle the body class to show/hide power user features
                if (isPowerUser) {
                    $('body').removeClass('basic-user-mode').addClass('power-user-mode');
                } else {
                    $('body').removeClass('power-user-mode').addClass('basic-user-mode');
                }
                
                // Show success message
                if (response.data.message) {
                    alert(response.data.message);
                }
            }
        })
        .fail(function() {
            // Revert the toggle if the request failed
            $('#power-user-mode').prop('checked', !isPowerUser);
        });
    });

    // Initialize power user mode on page load
    var isPowerUser = $('#power-user-mode').is(':checked');
    if (isPowerUser) {
        $('body').addClass('power-user-mode');
    } else {
        $('body').addClass('basic-user-mode');
    }

    // Collapsible sections functionality
    $('.emailit-advanced-section.collapsible .section-header').on('click', function() {
        var $section = $(this).closest('.emailit-advanced-section');
        var $content = $section.find('.section-content');
        var $icon = $(this).find('.toggle-icon');
        
        if ($section.hasClass('collapsed')) {
            $section.removeClass('collapsed');
            $content.slideDown(300);
            $icon.text('▼');
        } else {
            $section.addClass('collapsed');
            $content.slideUp(300);
            $icon.text('▶');
        }
    });

    // Initialize collapsible sections - all collapsed by default
    $('.emailit-advanced-section.collapsible').addClass('collapsed');
    $('.emailit-advanced-section.collapsible .section-content').hide();
    $('.emailit-advanced-section.collapsible .toggle-icon').text('▶');
});
</script>

<style>
.emailit-webhook-info {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
}

.emailit-webhook-test {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
}

.emailit-stats-section {
    margin-top: 40px;
}

.emailit-system-info {
    margin-top: 40px;
}

.emailit-system-info table {
    max-width: 600px;
}

#webhook-url {
    background: #f0f0f0;
    padding: 5px 8px;
    border-radius: 3px;
    font-family: monospace;
    margin-right: 10px;
}

/* FluentCRM Integration Styles */
.emailit-fluentcrm-info {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
}

.emailit-fluentcrm-status {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
}

.emailit-fluentcrm-benefits {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
}

.emailit-benefits-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.emailit-benefit-item {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    padding: 20px;
    text-align: center;
    transition: box-shadow 0.3s ease;
}

.emailit-benefit-item:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.emailit-benefit-item .dashicons {
    font-size: 32px;
    margin-bottom: 15px;
    display: block;
}

.emailit-benefit-item h4 {
    margin: 0 0 10px 0;
    color: #333;
    font-size: 16px;
}

.emailit-benefit-item p {
    margin: 0;
    color: #666;
    font-size: 14px;
    line-height: 1.5;
}

.emailit-fluentcrm-install-info {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    padding: 20px;
    margin-top: 20px;
}

.emailit-fluentcrm-install-info h4 {
    margin-top: 0;
    color: #333;
}

.emailit-fluentcrm-install-info ol {
    margin: 15px 0;
    padding-left: 20px;
}

.emailit-fluentcrm-install-info li {
    margin-bottom: 8px;
    color: #666;
}

/* Performance Status Styles */
.emailit-performance-status {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
}

.emailit-status-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin: 15px 0;
}

.emailit-status-item {
    display: flex;
    align-items: center;
    padding: 10px;
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
}

.emailit-status-item .status-icon {
    font-size: 16px;
    margin-right: 10px;
}

.emailit-status-item .status-text {
    font-size: 14px;
    color: #333;
    font-weight: 500;
}

.emailit-maintenance-tools {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
}

.emailit-maintenance-tools h4 {
    margin: 0 0 15px 0;
    color: #333;
    font-size: 16px;
}

.emailit-maintenance-tools p {
    margin: 0;
}

.emailit-maintenance-tools .button {
    margin-right: 10px;
    margin-bottom: 5px;
}

/* Bounce Classification Styles */
.emailit-bounce-stats {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
}

.emailit-bounce-overview {
    margin-bottom: 20px;
}

.emailit-bounce-metrics {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin: 15px 0;
}

.emailit-bounce-breakdown {
    margin-top: 20px;
}

.emailit-bounce-breakdown h4 {
    margin: 0 0 15px 0;
    color: #333;
    font-size: 16px;
}

.emailit-bounce-classification {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
    text-transform: uppercase;
}

.emailit-bounce-classification.hard_bounce {
    background: #ffebee;
    color: #c62828;
}

.emailit-bounce-classification.soft_bounce {
    background: #fff3e0;
    color: #ef6c00;
}

.emailit-bounce-classification.spam_complaint {
    background: #fce4ec;
    color: #ad1457;
}

.emailit-bounce-classification.unsubscribe {
    background: #e8f5e8;
    color: #2e7d32;
}

.emailit-bounce-classification.unknown {
    background: #f5f5f5;
    color: #666;
}

.emailit-severity {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 2px;
    font-size: 11px;
    font-weight: 500;
}

.emailit-severity.high {
    background: #ffcdd2;
    color: #d32f2f;
}

.emailit-severity.medium {
    background: #fff9c4;
    color: #f57f17;
}

.emailit-severity.low {
    background: #c8e6c9;
    color: #388e3c;
}

.emailit-severity.unknown {
    background: #f5f5f5;
    color: #666;
}

.emailit-no-bounce-data {
    text-align: center;
    padding: 40px 20px;
    color: #666;
    font-style: italic;
}

.emailit-bounce-stats-disabled {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
}

.emailit-bounce-stats-disabled .notice {
    margin: 0;
    padding: 12px;
}

/* New Simplified Interface Styles */
.emailit-status-overview {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
}

.emailit-status-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.status-card {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    transition: box-shadow 0.3s ease;
}

.status-card:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.status-card .status-icon {
    font-size: 32px;
    flex-shrink: 0;
}

.status-card .status-content h3 {
    margin: 0 0 8px 0;
    font-size: 14px;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-card .status-text {
    font-size: 16px;
    font-weight: 600;
    color: #333;
}

.status-indicator {
    display: flex;
    align-items: center;
    gap: 8px;
}

.status-indicator.success {
    color: #46b450;
}

.status-indicator.warning {
    color: #f56e28;
}

.status-indicator.error {
    color: #d63638;
}

.emailit-test-section {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
}

.emailit-quick-stats {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.stat-card {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
}

.stat-card h3 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-value {
    font-size: 24px;
    font-weight: bold;
    color: #0073aa;
}

.emailit-logs-section,
.emailit-webhook-section {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
}

.webhook-status-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin: 15px 0;
}

.webhook-card {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    padding: 15px;
    text-align: center;
}

.webhook-card h4 {
    margin: 0 0 10px 0;
    font-size: 12px;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.webhook-indicator,
.webhook-count,
.webhook-time {
    font-size: 16px;
    font-weight: 600;
    color: #333;
}

.emailit-advanced-section {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
}

.emailit-advanced-section h3 {
    margin-top: 0;
    color: #333;
    border-bottom: 2px solid #0073aa;
    padding-bottom: 10px;
}

.emailit-advanced-section .description {
    color: #666;
    font-style: italic;
    margin-bottom: 20px;
}

.health-status-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin: 15px 0;
}

.health-card {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    padding: 15px;
    text-align: center;
}

.health-card h4 {
    margin: 0 0 10px 0;
    font-size: 12px;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.health-card .status-indicator {
    font-size: 16px;
    font-weight: 600;
}

/* Power User Mode Toggle */
.emailit-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid #ddd;
}

.emailit-power-user-toggle {
    display: flex;
    align-items: center;
    gap: 15px;
}

.emailit-toggle-switch {
    position: relative;
    display: inline-block;
    width: 60px;
    height: 34px;
}

.emailit-toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .4s;
    border-radius: 34px;
}

.slider:before {
    position: absolute;
    content: "";
    height: 26px;
    width: 26px;
    left: 4px;
    bottom: 4px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

input:checked + .slider {
    background-color: #0073aa;
}

input:checked + .slider:before {
    transform: translateX(26px);
}

.emailit-toggle-label {
    cursor: pointer;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.power-user-text {
    font-weight: 600;
    color: #333;
}

.power-user-description {
    font-size: 12px;
    color: #666;
}

/* Progressive Disclosure */
.emailit-advanced-section {
    margin: 20px 0;
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
}

.emailit-advanced-section.collapsible .section-header {
    background: #f9f9f9;
    padding: 15px 20px;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #ddd;
    transition: background-color 0.3s ease;
}

.emailit-advanced-section.collapsible .section-header:hover {
    background: #f0f0f0;
}

.emailit-advanced-section.collapsible .section-header h3 {
    margin: 0;
    color: #333;
    border-bottom: none;
    padding-bottom: 0;
}

.emailit-advanced-section.collapsible .section-header .toggle-icon {
    font-size: 16px;
    color: #666;
    transition: transform 0.3s ease;
}

.emailit-advanced-section.collapsible.collapsed .section-header .toggle-icon {
    transform: rotate(-90deg);
}

.emailit-advanced-section.collapsible .section-content {
    padding: 20px;
    background: #fff;
}

.emailit-advanced-section.collapsible.collapsed .section-content {
    display: none;
}

/* Power User Mode Styles */
.power-user-only {
    display: none;
}

.power-user-mode .power-user-only {
    display: block;
}

.power-user-mode .emailit-advanced-section.collapsible {
    display: block;
}

.basic-user-mode .emailit-advanced-section.collapsible {
    display: none;
}

/* Contextual Help */
.emailit-help-tooltip {
    position: relative;
    display: inline-block;
    margin-left: 5px;
    cursor: help;
}

.emailit-help-tooltip .help-icon {
    color: #0073aa;
    font-size: 16px;
}

.emailit-help-tooltip .tooltip-content {
    visibility: hidden;
    width: 300px;
    background-color: #333;
    color: #fff;
    text-align: left;
    border-radius: 6px;
    padding: 10px;
    position: absolute;
    z-index: 1000;
    bottom: 125%;
    left: 50%;
    margin-left: -150px;
    opacity: 0;
    transition: opacity 0.3s;
    font-size: 12px;
    line-height: 1.4;
}

.emailit-help-tooltip .tooltip-content::after {
    content: "";
    position: absolute;
    top: 100%;
    left: 50%;
    margin-left: -5px;
    border-width: 5px;
    border-style: solid;
    border-color: #333 transparent transparent transparent;
}

.emailit-help-tooltip:hover .tooltip-content {
    visibility: visible;
    opacity: 1;
}

/* Health Score Dashboard */
.emailit-health-score-dashboard {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    padding: 25px;
    margin: 20px 0;
    color: white;
    box-shadow: 0 8px 32px rgba(0,0,0,0.1);
}

.health-score-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.health-score-header h2 {
    margin: 0;
    color: white;
    font-size: 24px;
    font-weight: 600;
}

.health-score-badge {
    background: rgba(255,255,255,0.2);
    border-radius: 50px;
    padding: 15px 25px;
    display: flex;
    align-items: center;
    gap: 5px;
    backdrop-filter: blur(10px);
}

.health-score-badge .score {
    font-size: 36px;
    font-weight: bold;
    color: white;
}

.health-score-badge .max {
    font-size: 18px;
    opacity: 0.8;
    color: white;
}

.health-metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.metric-card {
    background: rgba(255,255,255,0.1);
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    backdrop-filter: blur(10px);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.metric-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.metric-icon {
    font-size: 32px;
    flex-shrink: 0;
}

.metric-content h3 {
    margin: 0 0 8px 0;
    font-size: 14px;
    opacity: 0.9;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: white;
}

.metric-status {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 4px;
    color: white;
}

.metric-status.excellent { color: #4ade80; }
.metric-status.good { color: #60a5fa; }
.metric-status.warning { color: #fbbf24; }
.metric-status.error { color: #f87171; }

.metric-detail {
    font-size: 12px;
    opacity: 0.8;
    color: white;
}

.health-recommendations {
    background: rgba(255,255,255,0.1);
    border-radius: 8px;
    padding: 20px;
    backdrop-filter: blur(10px);
}

.health-recommendations h3 {
    margin: 0 0 15px 0;
    font-size: 16px;
    color: white;
}

.health-recommendations ul {
    margin: 0;
    padding-left: 20px;
    list-style: none;
}

.health-recommendations li {
    margin-bottom: 8px;
    font-size: 14px;
    color: white;
    opacity: 0.9;
}

.health-recommendations li:before {
    content: "•";
    color: #4ade80;
    font-weight: bold;
    display: inline-block;
    width: 1em;
    margin-left: -1em;
}
</style>