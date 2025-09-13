<?php
/**
 * Email Log Details Modal Template
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Variables $log and $webhook_logs are passed from the AJAX handler
?>

<div class="emailit-log-details">
    <dl class="emailit-email-details">
        <dt><?php _e('Email ID:', 'emailit-integration'); ?></dt>
        <dd><?php echo !empty($log['email_id']) ? esc_html($log['email_id']) : __('N/A', 'emailit-integration'); ?></dd>

        <dt><?php _e('To:', 'emailit-integration'); ?></dt>
        <dd><?php echo esc_html($log['to_email']); ?></dd>

        <dt><?php _e('From:', 'emailit-integration'); ?></dt>
        <dd><?php echo esc_html($log['from_email']); ?></dd>

        <?php if (!empty($log['reply_to'])) : ?>
        <dt><?php _e('Reply-To:', 'emailit-integration'); ?></dt>
        <dd><?php echo esc_html($log['reply_to']); ?></dd>
        <?php endif; ?>

        <dt><?php _e('Subject:', 'emailit-integration'); ?></dt>
        <dd><strong><?php echo esc_html($log['subject']); ?></strong></dd>

        <dt><?php _e('Status:', 'emailit-integration'); ?></dt>
        <dd>
            <span class="emailit-status <?php echo esc_attr($log['status']); ?>">
                <?php echo esc_html(ucfirst($log['status'])); ?>
            </span>
        </dd>

        <dt><?php _e('Created:', 'emailit-integration'); ?></dt>
        <dd><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log['created_at']))); ?></dd>

        <?php if (!empty($log['sent_at'])) : ?>
        <dt><?php _e('Sent:', 'emailit-integration'); ?></dt>
        <dd><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log['sent_at']))); ?></dd>
        <?php endif; ?>

        <dt><?php _e('Last Updated:', 'emailit-integration'); ?></dt>
        <dd><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log['updated_at']))); ?></dd>

        <?php if (!empty($log['token'])) : ?>
        <dt><?php _e('Token:', 'emailit-integration'); ?></dt>
        <dd><code><?php echo esc_html($log['token']); ?></code></dd>
        <?php endif; ?>

        <?php if (!empty($log['message_id'])) : ?>
        <dt><?php _e('Message ID:', 'emailit-integration'); ?></dt>
        <dd><code><?php echo esc_html($log['message_id']); ?></code></dd>
        <?php endif; ?>
    </dl>

    <!-- Email Content Tabs -->
    <div class="emailit-tabs">
        <ul class="emailit-tab-nav">
            <?php if (!empty($log['body_html'])) : ?>
            <li><a href="#email-html" class="active"><?php _e('HTML Content', 'emailit-integration'); ?></a></li>
            <?php endif; ?>

            <?php if (!empty($log['body_text'])) : ?>
            <li><a href="#email-text" <?php echo empty($log['body_html']) ? 'class="active"' : ''; ?>><?php _e('Text Content', 'emailit-integration'); ?></a></li>
            <?php endif; ?>

            <?php if (!empty($log['details'])) : ?>
            <li><a href="#email-details"><?php _e('Technical Details', 'emailit-integration'); ?></a></li>
            <?php endif; ?>

            <?php if (!empty($webhook_logs)) : ?>
            <li><a href="#webhook-events"><?php _e('Webhook Events', 'emailit-integration'); ?></a></li>
            <?php endif; ?>
        </ul>

        <div class="emailit-tab-content">
            <?php if (!empty($log['body_html'])) : ?>
            <div id="email-html" class="emailit-tab-pane <?php echo !empty($log['body_html']) ? 'active' : ''; ?>">
                <h4><?php _e('HTML Email Content', 'emailit-integration'); ?></h4>
                <div class="emailit-email-body">
                    <iframe srcdoc="<?php echo esc_attr($log['body_html']); ?>" style="width: 100%; min-height: 400px; border: 1px solid #ddd;"></iframe>
                </div>

                <h4><?php _e('Raw HTML', 'emailit-integration'); ?></h4>
                <textarea class="emailit-email-body" readonly style="width: 100%; height: 200px; font-family: monospace;"><?php echo esc_textarea($log['body_html']); ?></textarea>
            </div>
            <?php endif; ?>

            <?php if (!empty($log['body_text'])) : ?>
            <div id="email-text" class="emailit-tab-pane <?php echo empty($log['body_html']) ? 'active' : ''; ?>">
                <h4><?php _e('Plain Text Email Content', 'emailit-integration'); ?></h4>
                <div class="emailit-email-body">
                    <pre style="white-space: pre-wrap; font-family: inherit;"><?php echo esc_html($log['body_text']); ?></pre>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($log['details'])) : ?>
            <div id="email-details" class="emailit-tab-pane">
                <h4><?php _e('Technical Details', 'emailit-integration'); ?></h4>
                <?php
                $details = json_decode($log['details'], true);
                if (is_array($details)) {
                    echo '<div class="emailit-technical-details">';
                    $this->render_array_as_table($details);
                    echo '</div>';
                } else {
                    echo '<div class="emailit-email-body"><pre>' . esc_html($log['details']) . '</pre></div>';
                }
                ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($webhook_logs)) : ?>
            <div id="webhook-events" class="emailit-tab-pane">
                <h4><?php _e('Webhook Events', 'emailit-integration'); ?></h4>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('Date', 'emailit-integration'); ?></th>
                            <th><?php _e('Event Type', 'emailit-integration'); ?></th>
                            <th><?php _e('Status', 'emailit-integration'); ?></th>
                            <th><?php _e('Details', 'emailit-integration'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($webhook_logs as $webhook) : ?>
                        <tr>
                            <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($webhook['processed_at']))); ?></td>
                            <td><code><?php echo esc_html($webhook['event_type']); ?></code></td>
                            <td>
                                <?php if (!empty($webhook['status'])) : ?>
                                <span class="emailit-status <?php echo esc_attr($webhook['status']); ?>">
                                    <?php echo esc_html(ucfirst($webhook['status'])); ?>
                                </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($webhook['details'])) : ?>
                                <details>
                                    <summary><?php _e('View Details', 'emailit-integration'); ?></summary>
                                    <pre style="margin-top: 10px; max-height: 200px; overflow: auto; font-size: 11px;"><?php echo esc_html($webhook['details']); ?></pre>
                                </details>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="emailit-log-actions" style="margin-top: 20px; text-align: right;">
        <?php if (in_array($log['status'], array('failed', 'bounced'))) : ?>
        <button type="button" class="button button-primary emailit-resend-email" data-log-id="<?php echo esc_attr($log['id']); ?>">
            <?php _e('Resend Email', 'emailit-integration'); ?>
        </button>
        <?php endif; ?>

        <button type="button" class="button button-secondary emailit-modal-close">
            <?php _e('Close', 'emailit-integration'); ?>
        </button>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Initialize tabs
    $('.emailit-tab-nav a').on('click', function(e) {
        e.preventDefault();

        var target = $(this).attr('href');

        // Update nav
        $('.emailit-tab-nav a').removeClass('active');
        $(this).addClass('active');

        // Update content
        $('.emailit-tab-pane').removeClass('active');
        $(target).addClass('active');
    });
});
</script>

<?php
/**
 * Helper function to render array as table
 */
function render_array_as_table($array, $level = 0) {
    echo '<table class="widefat" style="margin-left: ' . ($level * 20) . 'px;">';
    foreach ($array as $key => $value) {
        echo '<tr>';
        echo '<th style="width: 200px;">' . esc_html($key) . '</th>';
        echo '<td>';
        if (is_array($value)) {
            render_array_as_table($value, $level + 1);
        } else {
            if (is_bool($value)) {
                echo $value ? 'true' : 'false';
            } elseif (is_null($value)) {
                echo '<em>null</em>';
            } else {
                echo esc_html($value);
            }
        }
        echo '</td>';
        echo '</tr>';
    }
    echo '</table>';
}
?>