<?php
/**
 * MailPoet Integration Settings Template
 *
 * This is a partial template for MailPoet-specific settings.
 * The main settings are integrated into the main settings.php template.
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Check if MailPoet is available
$mailpoet_available = class_exists('MailPoet\Mailer\MailerFactory');
$mailpoet_version = 'Unknown';

if ($mailpoet_available && function_exists('get_plugin_data')) {
    $plugin_file = WP_PLUGIN_DIR . '/mailpoet/mailpoet.php';
    if (file_exists($plugin_file)) {
        $plugin_data = get_plugin_data($plugin_file);
        $mailpoet_version = $plugin_data['Version'] ?? 'Unknown';
    }
}
?>

<div class="emailit-mailpoet-settings">
    <?php if (!$mailpoet_available): ?>
        <div class="notice notice-warning">
            <p><?php _e('MailPoet plugin is not installed or not compatible. Please install MailPoet 5.0 or higher to use the integration features.', 'emailit-integration'); ?></p>
        </div>
    <?php else: ?>
        <div class="notice notice-success">
            <p><?php printf(__('MailPoet %s detected and compatible.', 'emailit-integration'), esc_html($mailpoet_version)); ?></p>
        </div>
    <?php endif; ?>

    <h3><?php _e('MailPoet Integration Status', 'emailit-integration'); ?></h3>
    <table class="form-table">
        <tbody>
            <tr>
                <th scope="row"><?php _e('Plugin Status', 'emailit-integration'); ?></th>
                <td>
                    <?php if ($mailpoet_available): ?>
                        <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                        <?php printf(__('MailPoet %s is active and compatible', 'emailit-integration'), esc_html($mailpoet_version)); ?>
                    <?php else: ?>
                        <span class="dashicons dashicons-warning" style="color: #dc3232;"></span>
                        <?php _e('MailPoet is not installed or not compatible', 'emailit-integration'); ?>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Integration Status', 'emailit-integration'); ?></th>
                <td>
                    <?php
                    $integration_enabled = get_option('emailit_mailpoet_integration', 0);
                    if ($integration_enabled): ?>
                        <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                        <?php _e('MailPoet integration is enabled', 'emailit-integration'); ?>
                    <?php else: ?>
                        <span class="dashicons dashicons-minus" style="color: #ffb900;"></span>
                        <?php _e('MailPoet integration is disabled', 'emailit-integration'); ?>
                    <?php endif; ?>
                </td>
            </tr>
        </tbody>
    </table>

    <?php if ($mailpoet_available): ?>
        <h3><?php _e('Integration Features', 'emailit-integration'); ?></h3>
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row"><?php _e('Email Takeover', 'emailit-integration'); ?></th>
                    <td>
                        <?php
                        $override_transactional = get_option('emailit_mailpoet_override_transactional', 1);
                        if ($override_transactional): ?>
                            <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                            <?php _e('All MailPoet emails will be routed through Emailit', 'emailit-integration'); ?>
                        <?php else: ?>
                            <span class="dashicons dashicons-minus" style="color: #ffb900;"></span>
                            <?php _e('MailPoet will use its own sending method', 'emailit-integration'); ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Bounce Synchronization', 'emailit-integration'); ?></th>
                    <td>
                        <?php
                        $bounce_sync = get_option('emailit_mailpoet_sync_bounces', 1);
                        if ($bounce_sync): ?>
                            <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                            <?php _e('Bounce data will be synchronized to MailPoet subscribers', 'emailit-integration'); ?>
                        <?php else: ?>
                            <span class="dashicons dashicons-minus" style="color: #ffb900;"></span>
                            <?php _e('Bounce synchronization is disabled', 'emailit-integration'); ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Subscriber Engagement', 'emailit-integration'); ?></th>
                    <td>
                        <?php
                        $engagement_sync = get_option('emailit_mailpoet_sync_engagement', 1);
                        if ($engagement_sync): ?>
                            <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                            <?php _e('Subscriber engagement data will be tracked', 'emailit-integration'); ?>
                        <?php else: ?>
                            <span class="dashicons dashicons-minus" style="color: #ffb900;"></span>
                            <?php _e('Engagement tracking is disabled', 'emailit-integration'); ?>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <h3><?php _e('Configuration Summary', 'emailit-integration'); ?></h3>
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row"><?php _e('Hard Bounce Action', 'emailit-integration'); ?></th>
                    <td>
                        <?php
                        $hard_bounce_action = get_option('emailit_mailpoet_hard_bounce_action', 'mark_bounced');
                        $action_labels = array(
                            'mark_bounced' => __('Mark as Bounced', 'emailit-integration'),
                            'unsubscribe' => __('Unsubscribe', 'emailit-integration'),
                            'track_only' => __('Track Only', 'emailit-integration')
                        );
                        echo esc_html($action_labels[$hard_bounce_action] ?? $hard_bounce_action);
                        ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Soft Bounce Threshold', 'emailit-integration'); ?></th>
                    <td>
                        <?php
                        $soft_bounce_threshold = get_option('emailit_mailpoet_soft_bounce_threshold', 5);
                        printf(__('%d bounces before action', 'emailit-integration'), $soft_bounce_threshold);
                        ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Complaint Action', 'emailit-integration'); ?></th>
                    <td>
                        <?php
                        $complaint_action = get_option('emailit_mailpoet_complaint_action', 'mark_complained');
                        $action_labels = array(
                            'mark_complained' => __('Mark as Complained', 'emailit-integration'),
                            'unsubscribe' => __('Unsubscribe', 'emailit-integration'),
                            'track_only' => __('Track Only', 'emailit-integration')
                        );
                        echo esc_html($action_labels[$complaint_action] ?? $complaint_action);
                        ?>
                    </td>
                </tr>
            </tbody>
        </table>
    <?php endif; ?>
</div>
