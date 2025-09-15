<?php
/**
 * Webhook Logs Admin View
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Decode MIME-encoded header
 */
if (!function_exists('decode_mime_header')) {
function decode_mime_header($header) {
    if (strpos($header, '=?') !== 0) {
        return $header;
    }
    
    // Handle multiple encoded parts
    $decoded = '';
    $parts = explode(' ', $header);
    
    foreach ($parts as $part) {
        if (strpos($part, '=?') === 0) {
            // Extract encoding and data
            if (preg_match('/=\?([^?]+)\?([BQ])\?([^?]+)\?=/', $part, $matches)) {
                $charset = $matches[1];
                $encoding = $matches[2];
                $data = $matches[3];
                
                if ($encoding === 'B') {
                    // Base64 decode
                    $decoded_data = base64_decode($data);
                } elseif ($encoding === 'Q') {
                    // Quoted-printable decode
                    $decoded_data = quoted_printable_decode(str_replace('_', ' ', $data));
                } else {
                    $decoded_data = $data;
                }
                
                // Convert charset if needed
                if (function_exists('mb_convert_encoding') && $charset !== 'UTF-8') {
                    $decoded_data = mb_convert_encoding($decoded_data, 'UTF-8', $charset);
                }
                
                $decoded .= $decoded_data;
            } else {
                $decoded .= $part;
            }
        } else {
            $decoded .= ' ' . $part;
        }
    }
    
    return $decoded;
}
}

// Ensure we have the required data
$webhook_health = isset($webhook_health) ? $webhook_health : array();
$webhook_stats = isset($webhook_stats) ? $webhook_stats : array();
$recent_webhooks = isset($recent_webhooks) ? $recent_webhooks : array();
$webhook_alerts = isset($webhook_alerts) ? $webhook_alerts : array();

// Check if this is being included in settings page or standalone
$is_standalone = !isset($is_embedded) || !$is_embedded;
?>

<?php if ($is_standalone): ?>
<div class="wrap">
    <h1><?php _e('Emailit Webhook Log', 'emailit-integration'); ?></h1>
<?php endif; ?>
    
    <!-- Webhook Health Status -->
    <div class="emailit-webhook-health">
        <h2><?php _e('Webhook Health Status', 'emailit-integration'); ?></h2>
        
        <div class="webhook-health-cards">
            <div class="health-card">
                <h3><?php _e('Overall Health', 'emailit-integration'); ?></h3>
                <div class="health-score">
                    <?php 
                    $health_score = isset($webhook_health['health_score']) ? $webhook_health['health_score'] : 0;
                    $health_class = $health_score >= 80 ? 'good' : ($health_score >= 60 ? 'warning' : 'error');
                    ?>
                    <span class="score <?php echo $health_class; ?>"><?php echo $health_score; ?>%</span>
                </div>
            </div>
            
            <div class="health-card">
                <h3><?php _e('Last Webhook', 'emailit-integration'); ?></h3>
                <div class="last-webhook">
                    <?php if (isset($webhook_health['last_webhook']) && $webhook_health['last_webhook']): ?>
                        <span class="time"><?php echo esc_html($webhook_health['last_webhook']['time_ago']); ?> ago</span>
                        <span class="event-type"><?php echo esc_html($webhook_health['last_webhook']['event_type']); ?></span>
                    <?php else: ?>
                        <span class="no-webhooks"><?php _e('No webhooks received', 'emailit-integration'); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="health-card">
                <h3><?php _e('Missing Webhooks', 'emailit-integration'); ?></h3>
                <div class="missing-count">
                    <?php 
                    $missing_count = isset($webhook_health['missing_webhooks_count']) ? $webhook_health['missing_webhooks_count'] : 0;
                    $missing_class = $missing_count > 0 ? 'error' : 'good';
                    ?>
                    <?php if ($missing_count > 0): ?>
                        <span class="count <?php echo $missing_class; ?> clickable-missing-count" data-count="<?php echo $missing_count; ?>">
                            <?php echo $missing_count; ?>
                        </span>
                        <div class="missing-webhooks-details" style="display: none;">
                            <div class="missing-webhooks-loading" style="display: none;">
                                <p><?php _e('Loading missing webhooks details...', 'emailit-integration'); ?></p>
                            </div>
                            <div class="missing-webhooks-list" style="display: none;">
                                <!-- Content will be loaded via AJAX -->
                            </div>
                        </div>
                    <?php else: ?>
                        <span class="count <?php echo $missing_class; ?>"><?php echo $missing_count; ?></span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="health-card">
                <h3><?php _e('Webhook Errors', 'emailit-integration'); ?></h3>
                <div class="error-count">
                    <?php 
                    $error_count = isset($webhook_health['error_count']) ? $webhook_health['error_count'] : 0;
                    $error_class = $error_count > 0 ? 'error' : 'good';
                    ?>
                    <span class="count <?php echo $error_class; ?>"><?php echo $error_count; ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Webhook Alerts -->
    <?php if (!empty($webhook_alerts) && (!isset($is_embedded) || !$is_embedded)): ?>
    <div class="emailit-webhook-alerts">
        <h2><?php _e('Webhook Alerts', 'emailit-integration'); ?></h2>
        
        <?php 
        // Remove duplicates by tracking unique alert messages
        $displayed_alerts = array();
        foreach ($webhook_alerts as $index => $alert): 
            if (!$alert['dismissed']) {
                $alert_key = md5($alert['data']['message'] . $alert['type']);
                if (!isset($displayed_alerts[$alert_key])) {
                    $displayed_alerts[$alert_key] = true;
        ?>
            <div class="notice notice-warning is-dismissible webhook-alert emailit-webhook-alert-item" data-alert-index="<?php echo $index; ?>">
                <div class="emailit-alert-content">
                    <p>
                        <strong><?php _e('Webhook Alert:', 'emailit-integration'); ?></strong>
                        <?php echo esc_html($alert['data']['message']); ?>
                    </p>
                </div>
                <button type="button" class="notice-dismiss dismiss-webhook-alert" data-alert-index="<?php echo $index; ?>">
                    <span class="screen-reader-text"><?php _e('Dismiss this notice.', 'emailit-integration'); ?></span>
                </button>
            </div>
            <?php 
                }
            }
        endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Webhook Statistics -->
    <?php if (!isset($is_embedded) || !$is_embedded): ?>
    <div class="emailit-webhook-stats">
        <h2><?php _e('Webhook Statistics (Last 7 Days)', 'emailit-integration'); ?></h2>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php _e('Total Webhooks', 'emailit-integration'); ?></h3>
                <div class="stat-value"><?php echo isset($webhook_stats['total_webhooks']) ? $webhook_stats['total_webhooks'] : 0; ?></div>
            </div>
            
            <div class="stat-card">
                <h3><?php _e('Processed', 'emailit-integration'); ?></h3>
                <div class="stat-value success"><?php echo isset($webhook_stats['processed_webhooks']) ? $webhook_stats['processed_webhooks'] : 0; ?></div>
            </div>
            
            <div class="stat-card">
                <h3><?php _e('Failed', 'emailit-integration'); ?></h3>
                <div class="stat-value error"><?php echo isset($webhook_stats['failed_webhooks']) ? $webhook_stats['failed_webhooks'] : 0; ?></div>
            </div>
            
            <div class="stat-card">
                <h3><?php _e('Success Rate', 'emailit-integration'); ?></h3>
                <div class="stat-value"><?php echo isset($webhook_stats['success_rate']) ? $webhook_stats['success_rate'] : 0; ?>%</div>
            </div>
            
            <div class="stat-card">
                <h3><?php _e('Delivery Events', 'emailit-integration'); ?></h3>
                <div class="stat-value"><?php echo isset($webhook_stats['delivery_webhooks']) ? $webhook_stats['delivery_webhooks'] : 0; ?></div>
            </div>
            
            <div class="stat-card">
                <h3><?php _e('Bounce Events', 'emailit-integration'); ?></h3>
                <div class="stat-value"><?php echo isset($webhook_stats['bounce_webhooks']) ? $webhook_stats['bounce_webhooks'] : 0; ?></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Webhook Log Management -->
    <div class="emailit-webhook-management">
        <h2><?php _e('Webhook Log Management', 'emailit-integration'); ?></h2>
        
        <div class="webhook-management-actions">
            <button type="button" id="clear-all-webhook-logs" class="button button-secondary">
                <?php _e('Clear All Webhook Logs', 'emailit-integration'); ?>
            </button>
            
            <div class="clear-old-logs">
                <label for="clear-days"><?php _e('Clear logs older than:', 'emailit-integration'); ?></label>
                <select id="clear-days">
                    <option value="7"><?php _e('7 days', 'emailit-integration'); ?></option>
                    <option value="30" selected><?php _e('30 days', 'emailit-integration'); ?></option>
                    <option value="90"><?php _e('90 days', 'emailit-integration'); ?></option>
                    <option value="365"><?php _e('1 year', 'emailit-integration'); ?></option>
                </select>
                <button type="button" id="clear-old-webhook-logs" class="button button-secondary">
                    <?php _e('Clear Old Logs', 'emailit-integration'); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Recent Webhook Activity -->
    <div class="emailit-webhook-activity">
        <h2><?php _e('Recent Webhook Activity', 'emailit-integration'); ?></h2>
        
        <?php if ($is_standalone): ?>
        <p class="description"><?php _e('View recent webhook events and their details.', 'emailit-integration'); ?></p>
        <?php else: ?>
        <p class="description">
            <?php _e('View recent webhook events and their details. This shows the last 20 webhook events.', 'emailit-integration'); ?>
        </p>
        <?php endif; ?>
        
        <?php if (empty($recent_webhooks)): ?>
            <div class="no-webhooks">
                <p><?php _e('No webhook activity found.', 'emailit-integration'); ?></p>
                <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
                    <p><small><?php _e('Debug: No webhook data returned from webhook monitor.', 'emailit-integration'); ?></small></p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="webhook-table-container">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Time', 'emailit-integration'); ?></th>
                            <th><?php _e('Event Type', 'emailit-integration'); ?></th>
                            <th><?php _e('Email ID', 'emailit-integration'); ?></th>
                            <th><?php _e('To Email', 'emailit-integration'); ?></th>
                            <th><?php _e('Subject', 'emailit-integration'); ?></th>
                            <th><?php _e('Status', 'emailit-integration'); ?></th>
                            <th><?php _e('Actions', 'emailit-integration'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_webhooks as $webhook): 
                            $is_test_webhook = (strpos($webhook->event_id ?: '', 'WEBHOOKTEST_') === 0) || 
                                              (strpos($webhook->email_id ?: '', 'TEST_EMAIL_') === 0);
                            
                            // Extract email details from webhook details field
                            $webhook_details = array();
                            if (!empty($webhook->details)) {
                                $webhook_details = json_decode($webhook->details, true);
                            }
                            
                            // Get email information from webhook details or fallback to joined data
                            $to_email = !empty($webhook_details['to_email']) ? $webhook_details['to_email'] : ($webhook->to_email ?: __('N/A', 'emailit-integration'));
                            $subject = !empty($webhook_details['subject']) ? $webhook_details['subject'] : ($webhook->subject ?: __('N/A', 'emailit-integration'));
                            
                            // Decode MIME-encoded subject lines
                            if ($subject !== __('N/A', 'emailit-integration') && strpos($subject, '=?') === 0) {
                                $subject = decode_mime_header($subject);
                            }
                            
                            // Skip test webhooks based on subject
                            if (strpos($subject, 'Test Webhook -') === 0) {
                                continue;
                            }
                            
                            // Handle timestamp display with better error checking
                            $time_ago = '';
                            $timestamp_display = '';
                            if (!empty($webhook->processed_at)) {
                                $timestamp = strtotime($webhook->processed_at);
                                if ($timestamp !== false && $timestamp > 0) {
                                    $time_ago = human_time_diff($timestamp, current_time('timestamp')) . ' ago';
                                    $timestamp_display = $webhook->processed_at;
                                } else {
                                    $time_ago = __('Invalid timestamp', 'emailit-integration');
                                    $timestamp_display = $webhook->processed_at;
                                }
                            } else {
                                $time_ago = __('No timestamp', 'emailit-integration');
                                $timestamp_display = __('N/A', 'emailit-integration');
                            }
                        ?>
                        <tr class="<?php echo $is_test_webhook ? 'test-webhook-row' : ''; ?>">
                            <td>
                                <?php if ($is_test_webhook): ?>
                                    <span class="test-webhook-badge" title="<?php _e('Test Webhook', 'emailit-integration'); ?>">🧪</span>
                                <?php endif; ?>
                                <?php echo esc_html($time_ago); ?>
                                <br>
                                <small><?php echo esc_html($timestamp_display); ?></small>
                            </td>
                            <td>
                                <span class="event-type event-<?php echo esc_attr(str_replace('.', '-', $webhook->event_type ?: 'unknown')); ?>">
                                    <?php echo esc_html($webhook->event_type ?: __('N/A', 'emailit-integration')); ?>
                                    <?php if ($is_test_webhook): ?>
                                        <small class="test-indicator">(TEST)</small>
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td>
                                <code><?php echo esc_html($webhook->email_id ?: __('N/A', 'emailit-integration')); ?></code>
                            </td>
                            <td>
                                <?php echo esc_html($to_email); ?>
                            </td>
                            <td>
                                <?php echo esc_html($subject); ?>
                            </td>
                            <td>
                                <span class="status status-<?php echo esc_attr($webhook->status ?: 'unknown'); ?>">
                                    <?php echo esc_html(ucfirst($webhook->status ?: 'Unknown')); ?>
                                </span>
                            </td>
                            <td>
                                <button type="button" class="button button-small view-webhook-details" data-webhook-id="<?php echo esc_attr($webhook->id); ?>">
                                    <?php _e('Details', 'emailit-integration'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Webhook Details Modal -->
<div id="webhook-details-modal" class="webhook-modal" style="display: none;">
    <div class="webhook-modal-content">
        <div class="webhook-modal-header">
            <h2><?php _e('Webhook Details', 'emailit-integration'); ?></h2>
            <span class="webhook-modal-close">&times;</span>
        </div>
        <div class="webhook-modal-body">
            <div id="webhook-details-content">
                <!-- Content will be loaded via AJAX -->
            </div>
        </div>
    </div>
</div>

<style>
.webhook-health-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
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
    font-size: 14px;
    color: #666;
    text-transform: uppercase;
}

.health-score .score {
    font-size: 36px;
    font-weight: bold;
    display: block;
}

.health-score .score.good { color: #46b450; }
.health-score .score.warning { color: #ffb900; }
.health-score .score.error { color: #dc3232; }

.last-webhook .time {
    font-size: 18px;
    font-weight: bold;
    display: block;
    color: #333;
}

.last-webhook .event-type {
    font-size: 12px;
    color: #666;
    background: #f0f0f0;
    padding: 2px 6px;
    border-radius: 3px;
}

.last-webhook .no-webhooks {
    color: #dc3232;
    font-style: italic;
}

.missing-count .count,
.error-count .count {
    font-size: 36px;
    font-weight: bold;
    display: block;
}

.missing-count .count.good,
.error-count .count.good { color: #46b450; }
.missing-count .count.error,
.error-count .count.error { color: #dc3232; }

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin: 20px 0;
}

.stat-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 15px;
    text-align: center;
}

.stat-card h3 {
    margin: 0 0 10px 0;
    font-size: 12px;
    color: #666;
    text-transform: uppercase;
}

.stat-value {
    font-size: 24px;
    font-weight: bold;
    color: #333;
}

.stat-value.success { color: #46b450; }
.stat-value.error { color: #dc3232; }

/* Hide any stray stat-value.error elements that appear outside their proper containers */
body > .stat-value.error,
.emailit-admin-wrap > .stat-value.error,
.emailit-tab-pane > .stat-value.error {
    display: none !important;
}

/* Ensure stat-value.error elements work properly within their containers */
.stat-card .stat-value.error,
.webhook-health-cards .stat-value.error {
    display: block !important;
}

.webhook-table-container {
    overflow-x: auto;
    margin: 20px 0;
}

.event-type {
    background: #f0f0f0;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    text-transform: uppercase;
}

.event-email-delivery-sent { background: #d4edda; color: #155724; }
.event-email-delivery-bounced { background: #f8d7da; color: #721c24; }
.event-email-delivery-complained { background: #fff3cd; color: #856404; }

.status {
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 11px;
    text-transform: uppercase;
    font-weight: bold;
}

.status-processed { background: #d4edda; color: #155724; }
.status-failed { background: #f8d7da; color: #721c24; }

.webhook-modal {
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.webhook-modal-content {
    background-color: #fff;
    margin: 5% auto;
    padding: 0;
    border-radius: 8px;
    width: 80%;
    max-width: 800px;
    max-height: 80vh;
    overflow: hidden;
}

.webhook-modal-header {
    background: #f1f1f1;
    padding: 20px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.webhook-modal-header h2 {
    margin: 0;
}

.webhook-modal-close {
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    color: #666;
}

.webhook-modal-close:hover {
    color: #000;
}

.webhook-modal-body {
    padding: 20px;
    max-height: 60vh;
    overflow-y: auto;
}

.no-webhooks {
    text-align: center;
    padding: 40px;
    color: #666;
    font-style: italic;
}

.webhook-management-actions {
    display: flex;
    gap: 20px;
    align-items: center;
    margin: 20px 0;
    padding: 20px;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 6px;
}

.clear-old-logs {
    display: flex;
    gap: 10px;
    align-items: center;
}

.clear-old-logs label {
    font-weight: 500;
    margin-right: 5px;
}

.clear-old-logs select {
    padding: 4px 8px;
    border: 1px solid #ddd;
    border-radius: 3px;
}

.webhook-management-actions .button {
    margin: 0;
}

.webhook-management-actions .button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Test webhook styling */
.test-webhook-row {
    background-color: #f0f8ff !important;
    border-left: 4px solid #0073aa;
}

.test-webhook-badge {
    font-size: 16px;
    margin-right: 5px;
    vertical-align: middle;
}

.test-indicator {
    color: #0073aa;
    font-weight: bold;
    font-style: italic;
    margin-left: 5px;
}

.test-webhook-row .event-type {
    position: relative;
}

.test-webhook-row .status {
    background-color: #e1f5fe;
    color: #01579b;
}

/* Missing webhooks expandable section */
.clickable-missing-count {
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
}

.clickable-missing-count:hover {
    transform: scale(1.05);
    text-shadow: 0 0 8px rgba(220, 50, 50, 0.3);
}

.clickable-missing-count::after {
    content: ' ▼';
    font-size: 12px;
    opacity: 0.7;
    transition: transform 0.2s ease;
}

.clickable-missing-count.expanded::after {
    transform: rotate(180deg);
}

.missing-webhooks-details {
    margin-top: 15px;
    padding: 15px;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    max-height: 300px;
    overflow-y: auto;
}

.missing-webhooks-loading {
    text-align: center;
    color: #666;
    font-style: italic;
}

.missing-webhooks-list {
    font-size: 13px;
}

.missing-webhook-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #e9ecef;
}

.missing-webhook-item:last-child {
    border-bottom: none;
}

.missing-webhook-email-id {
    font-family: monospace;
    background: #fff;
    padding: 4px 8px;
    border-radius: 3px;
    border: 1px solid #ddd;
    font-size: 12px;
}

.missing-webhook-time {
    color: #666;
    font-size: 11px;
}

.missing-webhook-time-ago {
    color: #999;
    font-style: italic;
}

.missing-webhooks-header {
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #e9ecef;
}

.missing-webhooks-header h4 {
    margin: 0 0 5px 0;
    color: #dc3232;
    font-size: 14px;
}

.missing-webhooks-header .description {
    margin: 0;
    color: #666;
    font-size: 12px;
}

.missing-webhook-email-info {
    flex: 1;
    min-width: 0;
}

.missing-webhook-email-id {
    font-family: monospace;
    background: #fff;
    padding: 4px 8px;
    border-radius: 3px;
    border: 1px solid #ddd;
    font-size: 12px;
    margin-bottom: 4px;
    word-break: break-all;
}

.missing-webhook-to-email {
    color: #666;
    font-size: 11px;
    margin-bottom: 2px;
    word-break: break-all;
}

.missing-webhook-subject {
    color: #999;
    font-size: 10px;
    font-style: italic;
    word-break: break-all;
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.missing-webhook-time-info {
    text-align: right;
    flex-shrink: 0;
    margin-left: 15px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Clickable missing webhooks count
    $('.clickable-missing-count').on('click', function() {
        var $this = $(this);
        var $details = $this.siblings('.missing-webhooks-details');
        var $loading = $details.find('.missing-webhooks-loading');
        var $list = $details.find('.missing-webhooks-list');
        
        if ($this.hasClass('expanded')) {
            // Collapse
            $details.slideUp();
            $this.removeClass('expanded');
        } else {
            // Expand
            $details.slideDown();
            $this.addClass('expanded');
            
            // Load data if not already loaded
            if ($list.html().trim() === '') {
                $loading.show();
                $list.hide();
                
                $.post(ajaxurl, {
                    action: 'emailit_get_missing_webhooks',
                    nonce: '<?php echo wp_create_nonce('emailit_missing_webhooks'); ?>'
                })
                .done(function(response) {
                    $loading.hide();
                    if (response.success) {
                        $list.html(response.data.html).show();
                    } else {
                        $list.html('<p style="color: #dc3232;"><?php _e('Error loading missing webhooks details.', 'emailit-integration'); ?></p>').show();
                    }
                })
                .fail(function() {
                    $loading.hide();
                    $list.html('<p style="color: #dc3232;"><?php _e('Error loading missing webhooks details.', 'emailit-integration'); ?></p>').show();
                });
            } else {
                $list.show();
            }
        }
    });
    
    // Dismiss webhook alerts
    $('.dismiss-webhook-alert').on('click', function() {
        var alertIndex = $(this).data('alert-index');
        var $alert = $('.webhook-alert[data-alert-index="' + alertIndex + '"]');
        
        $.post(ajaxurl, {
            action: 'emailit_dismiss_webhook_alert',
            alert_index: alertIndex,
            nonce: '<?php echo wp_create_nonce('emailit_webhook_alerts'); ?>'
        })
        .done(function(response) {
            if (response.success) {
                $alert.fadeOut();
            }
        });
    });
    
    // View webhook details
    $('.view-webhook-details').on('click', function() {
        var webhookId = $(this).data('webhook-id');
        var $modal = $('#webhook-details-modal');
        var $content = $('#webhook-details-content');
        
        $content.html('<p><?php _e('Loading...', 'emailit-integration'); ?></p>');
        $modal.show();
        
        $.post(ajaxurl, {
            action: 'emailit_get_webhook_details',
            webhook_id: webhookId,
            nonce: '<?php echo wp_create_nonce('emailit_webhook_details'); ?>'
        })
        .done(function(response) {
            if (response.success) {
                $content.html(response.data.html);
            } else {
                $content.html('<p><?php _e('Error loading webhook details.', 'emailit-integration'); ?></p>');
            }
        })
        .fail(function() {
            $content.html('<p><?php _e('Error loading webhook details.', 'emailit-integration'); ?></p>');
        });
    });
    
    // Close modal
    $('.webhook-modal-close, .webhook-modal').on('click', function(e) {
        if (e.target === this) {
            $('#webhook-details-modal').hide();
        }
    });
    
    // Clear all webhook logs
    $('#clear-all-webhook-logs').on('click', function() {
        if (!confirm('<?php _e('Are you sure you want to clear ALL webhook logs? This action cannot be undone.', 'emailit-integration'); ?>')) {
            return;
        }
        
        var $button = $(this);
        $button.prop('disabled', true).text('<?php _e('Clearing...', 'emailit-integration'); ?>');
        
        $.post(ajaxurl, {
            action: 'emailit_clear_webhook_logs',
            action_type: 'clear_all',
            nonce: '<?php echo wp_create_nonce('emailit_webhook_logs'); ?>'
        })
        .done(function(response) {
            if (response.success) {
                alert(response.data.message);
                location.reload(); // Refresh the page to show updated data
            } else {
                alert('<?php _e('Error:', 'emailit-integration'); ?> ' + (response.data.message || '<?php _e('Unknown error occurred.', 'emailit-integration'); ?>'));
            }
        })
        .fail(function() {
            alert('<?php _e('Error clearing webhook logs.', 'emailit-integration'); ?>');
        })
        .always(function() {
            $button.prop('disabled', false).text('<?php _e('Clear All Webhook Logs', 'emailit-integration'); ?>');
        });
    });
    
    // Clear old webhook logs
    $('#clear-old-webhook-logs').on('click', function() {
        var days = $('#clear-days').val();
        var daysText = $('#clear-days option:selected').text();
        
        if (!confirm('<?php _e('Are you sure you want to clear webhook logs older than', 'emailit-integration'); ?> ' + daysText + '?')) {
            return;
        }
        
        var $button = $(this);
        $button.prop('disabled', true).text('<?php _e('Clearing...', 'emailit-integration'); ?>');
        
        $.post(ajaxurl, {
            action: 'emailit_clear_webhook_logs',
            action_type: 'clear_old',
            days: days,
            nonce: '<?php echo wp_create_nonce('emailit_webhook_logs'); ?>'
        })
        .done(function(response) {
            if (response.success) {
                alert(response.data.message);
                location.reload(); // Refresh the page to show updated data
            } else {
                alert('<?php _e('Error:', 'emailit-integration'); ?> ' + (response.data.message || '<?php _e('Unknown error occurred.', 'emailit-integration'); ?>'));
            }
        })
        .fail(function() {
            alert('<?php _e('Error clearing old webhook logs.', 'emailit-integration'); ?>');
        })
        .always(function() {
            $button.prop('disabled', false).text('<?php _e('Clear Old Logs', 'emailit-integration'); ?>');
        });
    });
});
</script>

<?php if ($is_standalone): ?>
</div>
<?php endif; ?>
