<?php
/**
 * Admin Email Logs Page Template
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Get logger instance
$logger = emailit_get_component('logger');

// Handle pagination and filtering
$per_page = 50;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';

// Get logs
$args = array(
    'per_page' => $per_page,
    'page' => $current_page,
    'status' => $status_filter,
    'search' => $search,
    'date_from' => $date_from,
    'date_to' => $date_to
);

$logs_data = $logger->get_logs($args);
$logs = $logs_data['logs'];
$total_logs = $logs_data['total'];
$total_pages = $logs_data['pages'];

// Get statistics for the overview
$stats = $logger->get_stats();
?>

<div class="wrap emailit-admin-wrap">
    <h1 class="wp-heading-inline"><?php echo esc_html(get_admin_page_title()); ?></h1>

    <!-- Statistics Overview -->
    <div class="emailit-stats">
        <div class="emailit-stat-card">
            <span class="emailit-stat-number"><?php echo number_format($stats['total_sent']['value']); ?></span>
            <span class="emailit-stat-label"><?php echo esc_html($stats['total_sent']['label']); ?></span>
        </div>
        <div class="emailit-stat-card">
            <span class="emailit-stat-number"><?php echo number_format($stats['sent']['value']); ?></span>
            <span class="emailit-stat-label"><?php echo esc_html($stats['sent']['label']); ?></span>
        </div>
        <div class="emailit-stat-card">
            <span class="emailit-stat-number"><?php echo number_format($stats['failed']['value']); ?></span>
            <span class="emailit-stat-label"><?php echo esc_html($stats['failed']['label']); ?></span>
        </div>
        <div class="emailit-stat-card">
            <span class="emailit-stat-number"><?php echo esc_html($stats['success_rate']['value']); ?></span>
            <span class="emailit-stat-label"><?php echo esc_html($stats['success_rate']['label']); ?></span>
        </div>
    </div>

    <!-- Filters -->
    <div class="tablenav top">
        <form method="get" action="">
            <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>" />

            <div class="alignleft actions">
                <!-- Status Filter -->
                <select name="status">
                    <option value=""><?php _e('All Statuses', 'emailit-integration'); ?></option>
                    <option value="pending" <?php selected($status_filter, 'pending'); ?>><?php _e('Pending', 'emailit-integration'); ?></option>
                    <option value="sent" <?php selected($status_filter, 'sent'); ?>><?php _e('Sent', 'emailit-integration'); ?></option>
                    <option value="sent_to_api" <?php selected($status_filter, 'sent_to_api'); ?>><?php _e('Sent to API', 'emailit-integration'); ?></option>
                    <option value="delivered" <?php selected($status_filter, 'delivered'); ?>><?php _e('Delivered', 'emailit-integration'); ?></option>
                    <option value="failed" <?php selected($status_filter, 'failed'); ?>><?php _e('Failed', 'emailit-integration'); ?></option>
                    <option value="bounced" <?php selected($status_filter, 'bounced'); ?>><?php _e('Bounced', 'emailit-integration'); ?></option>
                    <option value="complained" <?php selected($status_filter, 'complained'); ?>><?php _e('Complained', 'emailit-integration'); ?></option>
                </select>

                <!-- Date Range -->
                <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>"
                       placeholder="<?php _e('From Date', 'emailit-integration'); ?>" />
                <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>"
                       placeholder="<?php _e('To Date', 'emailit-integration'); ?>" />

                <input type="submit" class="button" value="<?php _e('Filter', 'emailit-integration'); ?>" />

                <!-- Clear Filters -->
                <?php if ($status_filter || $search || $date_from || $date_to) : ?>
                    <a href="<?php echo admin_url('tools.php?page=' . $_GET['page']); ?>" class="button">
                        <?php _e('Clear Filters', 'emailit-integration'); ?>
                    </a>
                <?php endif; ?>
            </div>

            <!-- Search -->
            <p class="search-box">
                <label class="screen-reader-text" for="email-search-input"><?php _e('Search Emails:', 'emailit-integration'); ?></label>
                <input type="search" id="email-search-input" name="s" value="<?php echo esc_attr($search); ?>"
                       placeholder="<?php _e('Search emails...', 'emailit-integration'); ?>" />
                <input type="submit" id="search-submit" class="button" value="<?php _e('Search Emails', 'emailit-integration'); ?>" />
            </p>
        </form>
    </div>

    <!-- Email Logs Table -->
    <?php if (!empty($logs)) : ?>
        <form id="emailit-bulk-form" method="post">
            <div class="tablenav top">
                <div class="alignleft actions bulkactions">
                    <label for="bulk-action-selector-top" class="screen-reader-text"><?php _e('Select bulk action', 'emailit-integration'); ?></label>
                    <select name="action" id="bulk-action-selector-top">
                        <option value="-1"><?php _e('Bulk actions', 'emailit-integration'); ?></option>
                        <option value="resend"><?php _e('Resend Failed Emails', 'emailit-integration'); ?></option>
                        <option value="delete"><?php _e('Delete Logs', 'emailit-integration'); ?></option>
                    </select>
                    <input type="submit" id="doaction" class="button action" value="<?php _e('Apply', 'emailit-integration'); ?>">
                </div>

                <div class="alignleft actions">
                    <select id="export-format">
                        <option value="csv"><?php _e('Export as CSV', 'emailit-integration'); ?></option>
                        <option value="json"><?php _e('Export as JSON', 'emailit-integration'); ?></option>
                    </select>
                    <input type="button" id="export-logs" class="button" value="<?php _e('Export', 'emailit-integration'); ?>">
                </div>
            </div>

        <table class="wp-list-table widefat fixed striped emailit-logs-table">
            <thead>
                <tr>
                    <td id="cb" class="manage-column column-cb check-column">
                        <label class="screen-reader-text" for="cb-select-all-1"><?php _e('Select All', 'emailit-integration'); ?></label>
                        <input id="cb-select-all-1" type="checkbox" />
                    </td>
                    <th scope="col" class="column-date"><?php _e('Date', 'emailit-integration'); ?></th>
                    <th scope="col" class="column-to"><?php _e('To', 'emailit-integration'); ?></th>
                    <th scope="col" class="column-from"><?php _e('From', 'emailit-integration'); ?></th>
                    <th scope="col" class="column-subject"><?php _e('Subject', 'emailit-integration'); ?></th>
                    <th scope="col" class="column-status"><?php _e('Status', 'emailit-integration'); ?></th>
                    <th scope="col" class="column-actions"><?php _e('Actions', 'emailit-integration'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log) : ?>
                    <tr>
                        <th scope="row" class="check-column">
                            <label class="screen-reader-text" for="cb-select-<?php echo $log['id']; ?>"><?php printf(__('Select %s', 'emailit-integration'), $log['subject']); ?></label>
                            <input id="cb-select-<?php echo $log['id']; ?>" type="checkbox" name="log_ids[]" value="<?php echo esc_attr($log['id']); ?>" />
                        </th>
                        <td class="column-date">
                            <strong><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log['created_at']))); ?></strong>
                            <?php if (!empty($log['sent_at'])) : ?>
                                <br><small><?php printf(__('Sent: %s', 'emailit-integration'), date_i18n(get_option('time_format'), strtotime($log['sent_at']))); ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="column-to">
                            <?php
                            $to_email = esc_html($log['to_email']);
                            if (strlen($to_email) > 50) {
                                echo substr($to_email, 0, 50) . '...';
                            } else {
                                echo $to_email;
                            }
                            ?>
                        </td>
                        <td class="column-from">
                            <?php echo esc_html($log['from_email']); ?>
                        </td>
                        <td class="column-subject">
                            <strong><?php echo esc_html($log['subject']); ?></strong>
                            <?php if (!empty($log['email_id'])) : ?>
                                <br><small>ID: <?php echo esc_html($log['email_id']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="column-status">
                            <span class="emailit-status <?php echo esc_attr($log['status']); ?>">
                                <?php
                                // Status display mapping
                                $status_labels = array(
                                    'pending' => __('Pending', 'emailit-integration'),
                                    'sent' => __('Sent', 'emailit-integration'),
                                    'sent_to_api' => __('Sent to API', 'emailit-integration'),
                                    'delivered' => __('Delivered', 'emailit-integration'),
                                    'failed' => __('Failed', 'emailit-integration'),
                                    'bounced' => __('Bounced', 'emailit-integration'),
                                    'complained' => __('Complained', 'emailit-integration'),
                                    'held' => __('Held', 'emailit-integration'),
                                    'delayed' => __('Delayed', 'emailit-integration')
                                );
                                $status_display = isset($status_labels[$log['status']]) ? $status_labels[$log['status']] : ucfirst($log['status']);
                                echo esc_html($status_display);
                                ?>
                            </span>
                        </td>
                        <td class="column-actions">
                            <div class="emailit-log-actions">
                                <a href="#" class="emailit-view-log" data-log-id="<?php echo esc_attr($log['id']); ?>"
                                   title="<?php _e('View Details', 'emailit-integration'); ?>">
                                    <?php _e('View', 'emailit-integration'); ?>
                                </a>

                                <?php if (in_array($log['status'], array('failed', 'bounced'))) : ?>
                                    <a href="#" class="emailit-resend-email" data-log-id="<?php echo esc_attr($log['id']); ?>"
                                       title="<?php _e('Resend Email', 'emailit-integration'); ?>">
                                        <?php _e('Resend', 'emailit-integration'); ?>
                                    </a>
                                <?php endif; ?>

                                <a href="#" class="emailit-delete-log" data-log-id="<?php echo esc_attr($log['id']); ?>"
                                   title="<?php _e('Delete Log', 'emailit-integration'); ?>" style="color: #d63638;">
                                    <?php _e('Delete', 'emailit-integration'); ?>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </form>

        <!-- Pagination -->
        <?php if ($total_pages > 1) : ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <span class="displaying-num">
                        <?php printf(_n('%s item', '%s items', $total_logs, 'emailit-integration'), number_format_i18n($total_logs)); ?>
                    </span>

                    <?php
                    $page_links = paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('&laquo;'),
                        'next_text' => __('&raquo;'),
                        'total' => $total_pages,
                        'current' => $current_page,
                        'type' => 'array'
                    ));

                    if ($page_links) {
                        echo '<span class="pagination-links">' . implode('', $page_links) . '</span>';
                    }
                    ?>
                </div>
            </div>
        <?php endif; ?>

    <?php else : ?>
        <!-- No logs found -->
        <div class="notice notice-info">
            <p><?php _e('No email logs found matching your criteria.', 'emailit-integration'); ?></p>
        </div>
    <?php endif; ?>

    <!-- Maintenance Tools -->
    <div class="emailit-bulk-actions" style="margin-top: 20px;">
        <h3><?php _e('Maintenance Tools', 'emailit-integration'); ?></h3>
        <p><?php _e('Use these tools to manage your email logs.', 'emailit-integration'); ?></p>

        <button type="button" id="cleanup-logs" class="button button-secondary">
            <?php _e('Clean Old Logs', 'emailit-integration'); ?>
        </button>
        <span class="description">
            <?php printf(__('Remove logs older than %d days (based on your retention settings).', 'emailit-integration'), get_option('emailit_log_retention_days', 30)); ?>
        </span>

        <br><br>

        <button type="button" id="resend-all-failed" class="button button-secondary">
            <?php _e('Resend All Recent Failed', 'emailit-integration'); ?>
        </button>
        <span class="description">
            <?php _e('Resend all failed emails from the last 24 hours.', 'emailit-integration'); ?>
        </span>
    </div>
</div>

<!-- Modal for log details -->
<div id="emailit-log-modal" class="emailit-modal-overlay" style="display: none;">
    <div class="emailit-modal">
        <div class="emailit-modal-header">
            <h3><?php _e('Email Log Details', 'emailit-integration'); ?></h3>
            <button type="button" class="emailit-modal-close">&times;</button>
        </div>
        <div class="emailit-modal-content">
            <!-- Content will be loaded via AJAX -->
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Cleanup logs functionality
    $('#cleanup-logs').on('click', function() {
        if (!confirm('<?php _e('Are you sure you want to clean up old logs? This action cannot be undone.', 'emailit-integration'); ?>')) {
            return;
        }

        var $button = $(this);
        $button.prop('disabled', true).text('<?php _e('Cleaning...', 'emailit-integration'); ?>');

        $.post(ajaxurl, {
            action: 'emailit_cleanup_logs',
            nonce: '<?php echo wp_create_nonce('emailit_admin_nonce'); ?>'
        })
        .done(function(response) {
            if (response.success) {
                alert(response.data.message);
                location.reload();
            } else {
                alert('<?php _e('Error:', 'emailit-integration'); ?> ' + response.data.message);
            }
        })
        .fail(function() {
            alert('<?php _e('Failed to cleanup logs. Please try again.', 'emailit-integration'); ?>');
        })
        .always(function() {
            $button.prop('disabled', false).text('<?php _e('Clean Old Logs', 'emailit-integration'); ?>');
        });
    });

    // Handle bulk actions
    $('#emailit-bulk-form').on('submit', function(e) {
        e.preventDefault();

        var action = $('#bulk-action-selector-top').val();
        var selectedIds = [];

        $('input[name="log_ids[]"]:checked').each(function() {
            selectedIds.push($(this).val());
        });

        if (action === '-1') {
            alert('<?php _e('Please select an action.', 'emailit-integration'); ?>');
            return;
        }

        if (selectedIds.length === 0) {
            alert('<?php _e('Please select at least one email.', 'emailit-integration'); ?>');
            return;
        }

        if (action === 'resend') {
            handleBulkResend(selectedIds);
        } else if (action === 'delete') {
            handleBulkDelete(selectedIds);
        }
    });

    // Handle export
    $('#export-logs').on('click', function() {
        var format = $('#export-format').val();
        var data = {
            action: 'emailit_export_logs',
            nonce: '<?php echo wp_create_nonce('emailit_admin_nonce'); ?>',
            format: format,
            status: '<?php echo esc_js($status_filter); ?>',
            date_from: '<?php echo esc_js($date_from); ?>',
            date_to: '<?php echo esc_js($date_to); ?>'
        };

        // Create form and submit for download
        var form = $('<form method="post" action="<?php echo admin_url('admin-ajax.php'); ?>">');
        $.each(data, function(key, value) {
            form.append('<input type="hidden" name="' + key + '" value="' + value + '">');
        });
        $('body').append(form);
        form.submit();
        form.remove();
    });

    // Handle bulk resend
    function handleBulkResend(selectedIds) {
        if (!confirm('<?php _e('Are you sure you want to resend the selected emails?', 'emailit-integration'); ?>')) {
            return;
        }

        var $button = $('.bulkactions .button');
        $button.prop('disabled', true).val('<?php _e('Processing...', 'emailit-integration'); ?>');

        $.post(ajaxurl, {
            action: 'emailit_bulk_resend',
            nonce: '<?php echo wp_create_nonce('emailit_admin_nonce'); ?>',
            log_ids: selectedIds
        })
        .done(function(response) {
            if (response.success) {
                alert(response.data.message);
                location.reload();
            } else {
                alert('<?php _e('Error:', 'emailit-integration'); ?> ' + response.data.message);
            }
        })
        .fail(function() {
            alert('<?php _e('Failed to resend emails. Please try again.', 'emailit-integration'); ?>');
        })
        .always(function() {
            $button.prop('disabled', false).val('<?php _e('Apply', 'emailit-integration'); ?>');
        });
    }

    // Handle bulk delete
    function handleBulkDelete(selectedIds) {
        if (!confirm('<?php _e('Are you sure you want to delete the selected email logs? This action cannot be undone.', 'emailit-integration'); ?>')) {
            return;
        }

        var $button = $('.bulkactions .button');
        $button.prop('disabled', true).val('<?php _e('Processing...', 'emailit-integration'); ?>');

        var completed = 0;
        var errors = 0;

        selectedIds.forEach(function(logId) {
            $.post(ajaxurl, {
                action: 'emailit_delete_log',
                nonce: '<?php echo wp_create_nonce('emailit_admin_nonce'); ?>',
                log_id: logId
            })
            .done(function(response) {
                if (response.success) {
                    $('tr:has(input[value="' + logId + '"])').fadeOut();
                } else {
                    errors++;
                }
            })
            .fail(function() {
                errors++;
            })
            .always(function() {
                completed++;
                if (completed === selectedIds.length) {
                    $button.prop('disabled', false).val('<?php _e('Apply', 'emailit-integration'); ?>');
                    if (errors > 0) {
                        alert('<?php _e('Some deletions failed. Please refresh the page.', 'emailit-integration'); ?>');
                    }
                }
            });
        });
    }

    // Resend all recent failed emails
    $('#resend-all-failed').on('click', function() {
        if (!confirm('<?php _e('Resend all failed emails from the last 24 hours?', 'emailit-integration'); ?>')) {
            return;
        }

        var $button = $(this);
        $button.prop('disabled', true).text('<?php _e('Processing...', 'emailit-integration'); ?>');

        // Get all failed email IDs from current view
        var failedIds = [];
        $('tr').each(function() {
            var $status = $(this).find('.emailit-status.failed');
            if ($status.length > 0) {
                var checkbox = $(this).find('input[name="log_ids[]"]');
                if (checkbox.length > 0) {
                    failedIds.push(checkbox.val());
                }
            }
        });

        if (failedIds.length === 0) {
            alert('<?php _e('No failed emails found in current view.', 'emailit-integration'); ?>');
            $button.prop('disabled', false).text('<?php _e('Resend All Recent Failed', 'emailit-integration'); ?>');
            return;
        }

        handleBulkResend(failedIds);
        $button.prop('disabled', false).text('<?php _e('Resend All Recent Failed', 'emailit-integration'); ?>');
    });

    // Select all checkbox functionality
    $('#cb-select-all-1').on('change', function() {
        $('input[name="log_ids[]"]').prop('checked', this.checked);
    });

    // Update select all when individual checkboxes change
    $('input[name="log_ids[]"]').on('change', function() {
        var total = $('input[name="log_ids[]"]').length;
        var checked = $('input[name="log_ids[]"]:checked').length;
        $('#cb-select-all-1').prop('checked', total === checked);
    });

    // Auto-refresh page every 30 seconds if there are pending emails
    <?php if (count(array_filter($logs, function($log) { return $log['status'] === 'pending'; })) > 0) : ?>
    setTimeout(function() {
        location.reload();
    }, 30000);
    <?php endif; ?>
});
</script>