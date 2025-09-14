/**
 * Emailit Integration Admin JavaScript
 */

(function($) {
    'use strict';

    /**
     * Main Admin Object
     */
    var EmailitAdmin = {

        /**
         * Initialize admin functionality
         */
        init: function() {
            this.bindEvents();
            this.initTabs();
            this.initModals();
        },

        /**
         * Bind event listeners
         */
        bindEvents: function() {
            // Test email functionality
            $(document).on('click', '#emailit-test-email', this.sendTestEmail);
            $(document).on('click', '#emailit-wordpress-test', this.sendWordPressTest);
            $(document).on('click', '#emailit-diagnostic', this.runDiagnostic);

            // Queue management
            $(document).on('click', '#refresh-queue-stats', this.refreshQueueStats);
            $(document).on('click', '#process-queue-now', this.processQueueNow);

            // Log actions
            $(document).on('click', '.emailit-view-log', this.viewLogDetails);
            $(document).on('click', '.emailit-delete-log', this.deleteLog);
            $(document).on('click', '.emailit-resend-email', this.resendEmail);

            // Modal close
            $(document).on('click', '.emailit-modal-close, .emailit-modal-overlay', this.closeModal);

            // Form validation
            $(document).on('submit', '#emailit-settings-form', this.validateForm);

            // Settings changes
            $(document).on('change', '#emailit_enable_logging', this.toggleLoggingOptions);
            $(document).on('change', '#emailit_enable_webhooks', this.toggleWebhookOptions);

            // API key field enhancements
            $(document).on('focus', '#emailit_api_key', this.handleApiKeyFocus);
            $(document).on('input', '#emailit_api_key', this.handleApiKeyInput);
        },

        /**
         * Initialize tab functionality
         */
        initTabs: function() {
            $('.emailit-tab-nav a').on('click', function(e) {
                e.preventDefault();

                var target = $(this).attr('href');
                var tabName = $(this).data('tab');

                // Update nav - remove both WordPress and custom active classes
                $('.emailit-tab-nav a').removeClass('nav-tab-active active');
                $(this).addClass('nav-tab-active active');

                // Update content
                $('.emailit-tab-pane').removeClass('active');
                $(target).addClass('active');

                // Update URL without page reload
                if (history.pushState) {
                    var newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?page=' + encodeURIComponent(getUrlParameter('page')) + '&tab=' + encodeURIComponent(tabName);
                    window.history.pushState({}, '', newUrl);
                }
            });
        },

        /**
         * Initialize modal functionality
         */
        initModals: function() {
            // Prevent modal content clicks from closing modal
            $(document).on('click', '.emailit-modal', function(e) {
                e.stopPropagation();
            });
        },

        /**
         * Send test email
         */
        sendTestEmail: function(e) {
            e.preventDefault();

            var $button = $(this);
            var $result = $('#emailit-test-result');

            // Get form data
            var data = {
                action: 'emailit_send_test_email',
                nonce: emailit_ajax.nonce,
                test_email: $('#emailit_test_email').val() || emailit_ajax.strings.admin_email
            };

            // Show loading
            $button.prop('disabled', true).text('Sending...');
            $result.hide().removeClass('success error');

            $.post(emailit_ajax.ajax_url, data)
                .done(function(response) {
                    if (response.success) {
                        $result
                            .addClass('success')
                            .html('<strong>Success:</strong> ' + response.data.message)
                            .show();
                    } else {
                        $result
                            .addClass('error')
                            .html('<strong>Error:</strong> ' + response.data.message)
                            .show();
                    }
                })
                .fail(function() {
                    $result
                        .addClass('error')
                        .html('<strong>Error:</strong> Failed to send test email. Please try again.')
                        .show();
                })
                .always(function() {
                    $button.prop('disabled', false).text('Send Test Email');
                });
        },

        /**
         * Send WordPress wp_mail test email
         */
        sendWordPressTest: function(e) {
            e.preventDefault();

            var $button = $(this);
            var $result = $('#emailit-wordpress-test-result');

            // Get form data
            var data = {
                action: 'emailit_send_wordpress_test',
                nonce: emailit_ajax.nonce,
                test_email: $('#emailit_wordpress_test_email').val() || emailit_ajax.strings.admin_email
            };

            // Show loading
            $button.prop('disabled', true).text('Sending WordPress Test...');
            $result.hide().removeClass('success error');

            $.post(emailit_ajax.ajax_url, data)
                .done(function(response) {
                    if (response.success) {
                        var message = '<strong>Success:</strong> ' + response.data.message;
                        if (response.data.details) {
                            message += '<br><small><strong>Details:</strong> Sent via ' + response.data.details.method +
                                      ' to ' + response.data.details.to + ' at ' + response.data.details.timestamp + '</small>';
                        }
                        $result
                            .addClass('success')
                            .html(message)
                            .show();
                    } else {
                        var errorMessage = '<strong>Error:</strong> ' + response.data.message;
                        if (response.data.technical_details) {
                            errorMessage += '<br><small><strong>Technical Details:</strong> ' +
                                          response.data.technical_details.file + ':' + response.data.technical_details.line + '</small>';
                        }
                        $result
                            .addClass('error')
                            .html(errorMessage)
                            .show();
                    }
                })
                .fail(function(jqXHR, textStatus, errorThrown) {
                    $result
                        .addClass('error')
                        .html('<strong>Critical Error:</strong> WordPress test email failed completely. ' +
                              'Error: ' + textStatus + ' - ' + errorThrown + '<br>' +
                              '<small>Check the WordPress error logs and Emailit logs for more details.</small>')
                        .show();
                })
                .always(function() {
                    $button.prop('disabled', false).text('Send WordPress Test Email');
                });
        },

        /**
         * Run plugin diagnostic test
         */
        runDiagnostic: function(e) {
            e.preventDefault();

            var $button = $(this);
            var $result = $('#emailit-diagnostic-result');

            // Get form data
            var data = {
                action: 'emailit_diagnostic',
                nonce: emailit_ajax.nonce
            };

            // Show loading
            $button.prop('disabled', true).text('Running Diagnostic...');
            $result.hide().removeClass('success error');

            $.post(emailit_ajax.ajax_url, data)
                .done(function(response) {
                    if (response.success) {
                        var message = '<strong>Success:</strong> ' + response.data.message;
                        message += '<br><strong>Diagnostic Information:</strong><ul>';
                        $.each(response.data.diagnostic_info, function(key, value) {
                            var displayValue = (typeof value === 'boolean') ? (value ? 'Yes' : 'No') : value;
                            message += '<li><strong>' + key + ':</strong> ' + displayValue + '</li>';
                        });
                        message += '</ul>';
                        $result
                            .addClass('success')
                            .html(message)
                            .show();
                    } else {
                        $result
                            .addClass('error')
                            .html('<strong>Error:</strong> ' + response.data.message)
                            .show();
                    }
                })
                .fail(function(jqXHR, textStatus, errorThrown) {
                    $result
                        .addClass('error')
                        .html('<strong>Critical Error:</strong> Diagnostic test failed completely. ' +
                              'Error: ' + textStatus + ' - ' + errorThrown + '<br>' +
                              '<strong>Status Code:</strong> ' + jqXHR.status + '<br>' +
                              '<strong>Response:</strong> ' + jqXHR.responseText.substring(0, 500) + '<br>' +
                              '<small>This indicates a fundamental plugin loading issue or PHP fatal error.</small>')
                        .show();
                })
                .always(function() {
                    $button.prop('disabled', false).text('Run Diagnostic Test');
                });
        },

        /**
         * Refresh queue statistics
         */
        refreshQueueStats: function(e) {
            e.preventDefault();

            var $button = $(this);
            var data = {
                action: 'emailit_get_queue_stats',
                nonce: emailit_ajax.nonce
            };

            $button.prop('disabled', true).text('Refreshing...');

            $.post(emailit_ajax.ajax_url, data)
                .done(function(response) {
                    if (response.success) {
                        $('#queue-pending').text(response.data.pending || 0);
                        $('#queue-processing').text(response.data.processing || 0);
                        $('#queue-failed').text(response.data.failed || 0);
                    }
                })
                .fail(function() {
                    // Handle failure silently, just reset button
                })
                .always(function() {
                    $button.prop('disabled', false).text('Refresh Stats');
                });
        },

        /**
         * Process queue manually
         */
        processQueueNow: function(e) {
            e.preventDefault();

            var $button = $(this);
            var data = {
                action: 'emailit_process_queue_now',
                nonce: emailit_ajax.nonce
            };

            $button.prop('disabled', true).text('Processing...');

            $.post(emailit_ajax.ajax_url, data)
                .done(function(response) {
                    if (response.success) {
                        // Refresh stats after processing
                        $('#refresh-queue-stats').click();
                    }
                })
                .fail(function() {
                    // Handle failure silently
                })
                .always(function() {
                    $button.prop('disabled', false).text('Process Queue Now');
                });
        },

        /**
         * View log details in modal
         */
        viewLogDetails: function(e) {
            e.preventDefault();

            var logId = $(this).data('log-id');
            var $modal = $('#emailit-log-modal');
            var $content = $modal.find('.emailit-modal-content');

            // Show loading
            $content.html('<div class="emailit-loading"></div> Loading...');
            EmailitAdmin.showModal($modal);

            // Get log details
            var data = {
                action: 'emailit_get_log_details',
                nonce: emailit_ajax.nonce,
                log_id: logId
            };

            $.post(emailit_ajax.ajax_url, data)
                .done(function(response) {
                    if (response.success) {
                        $content.html(response.data.html);
                    } else {
                        $content.html('<p>Error loading log details: ' + response.data.message + '</p>');
                    }
                })
                .fail(function() {
                    $content.html('<p>Error loading log details. Please try again.</p>');
                });
        },

        /**
         * Delete log entry
         */
        deleteLog: function(e) {
            e.preventDefault();

            if (!confirm(emailit_ajax.strings.confirm_delete)) {
                return;
            }

            var logId = $(this).data('log-id');
            var $row = $(this).closest('tr');

            var data = {
                action: 'emailit_delete_log',
                nonce: emailit_ajax.nonce,
                log_id: logId
            };

            $.post(emailit_ajax.ajax_url, data)
                .done(function(response) {
                    if (response.success) {
                        $row.fadeOut(function() {
                            $(this).remove();
                        });
                    } else {
                        alert('Error deleting log: ' + response.data.message);
                    }
                })
                .fail(function() {
                    alert('Error deleting log. Please try again.');
                });
        },

        /**
         * Resend email
         */
        resendEmail: function(e) {
            e.preventDefault();

            var logId = $(this).data('log-id');
            var $button = $(this);
            var originalText = $button.text();

            $button.text('Sending...').prop('disabled', true);

            var data = {
                action: 'emailit_resend_email',
                nonce: emailit_ajax.nonce,
                log_id: logId
            };

            $.post(emailit_ajax.ajax_url, data)
                .done(function(response) {
                    if (response.success) {
                        // Refresh the page to show updated status
                        window.location.reload();
                    } else {
                        alert('Error resending email: ' + response.data.message);
                    }
                })
                .fail(function() {
                    alert('Error resending email. Please try again.');
                })
                .always(function() {
                    $button.text(originalText).prop('disabled', false);
                });
        },

        /**
         * Show modal
         */
        showModal: function($modal) {
            $modal.show();
            $('body').addClass('emailit-modal-open');

            // Focus trap
            $modal.find('input, button, a').first().focus();
        },

        /**
         * Close modal
         */
        closeModal: function(e) {
            if (e.target !== this && !$(e.target).hasClass('emailit-modal-close')) {
                return;
            }

            $('.emailit-modal-overlay').hide();
            $('body').removeClass('emailit-modal-open');
        },

        /**
         * Validate settings form
         */
        validateForm: function(e) {
            var $form = $(this);
            var $apiKey = $form.find('#emailit_api_key');
            var $fromEmail = $form.find('#emailit_from_email');

            var errors = [];

            // Validate API key
            if (!$apiKey.val().trim()) {
                errors.push('API Key is required.');
                $apiKey.addClass('error');
            } else {
                $apiKey.removeClass('error');
            }

            // Validate from email
            if (!EmailitAdmin.isValidEmail($fromEmail.val())) {
                errors.push('From Email must be a valid email address.');
                $fromEmail.addClass('error');
            } else {
                $fromEmail.removeClass('error');
            }

            if (errors.length > 0) {
                e.preventDefault();
                alert('Please fix the following errors:\n\n' + errors.join('\n'));
                return false;
            }

            return true;
        },

        /**
         * Toggle logging options visibility
         */
        toggleLoggingOptions: function() {
            var $loggingOptions = $('.emailit-logging-options');

            if ($(this).is(':checked')) {
                $loggingOptions.show();
            } else {
                $loggingOptions.hide();
            }
        },

        /**
         * Toggle webhook-related options
         */
        toggleWebhookOptions: function() {
            var $webhookSecret = $('#emailit_webhook_secret');
            var isEnabled = $(this).is(':checked');

            if (isEnabled) {
                $webhookSecret.prop('disabled', false);
                $webhookSecret.parent().find('.description').show();
            } else {
                $webhookSecret.prop('disabled', true);
                $webhookSecret.parent().find('.description').hide();
            }
        },

        /**
         * Validate email address
         */
        isValidEmail: function(email) {
            var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return regex.test(email);
        },

        /**
         * Show notification
         */
        showNotification: function(message, type) {
            type = type || 'success';

            var $notice = $('<div class="notice notice-' + type + ' is-dismissible">' +
                '<p>' + message + '</p>' +
                '<button type="button" class="notice-dismiss">' +
                    '<span class="screen-reader-text">Dismiss this notice.</span>' +
                '</button>' +
            '</div>');

            $('.emailit-admin-wrap h1').after($notice);

            // Auto dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut();
            }, 5000);
        },

        /**
         * Handle API key field focus
         */
        handleApiKeyFocus: function() {
            var $field = $(this);
            var hasKey = $field.data('has-key') === '1';

            if (hasKey) {
                // Clear placeholder dots when focusing to enter new key
                if ($field.val() === '••••••••••••••••••••••••••••••••') {
                    $field.val('');
                    $field.attr('placeholder', 'Enter new API key to replace existing key');
                }
            }
        },

        /**
         * Handle API key field input
         */
        handleApiKeyInput: function() {
            var $field = $(this);
            var value = $field.val();
            var hasKey = $field.data('has-key') === '1';

            // Update data attribute to reflect that we're entering a new key
            if (value.length > 0 && value !== '••••••••••••••••••••••••••••••••') {
                $field.data('has-key', '0');
                $field.attr('placeholder', 'Enter your Emailit API key');
            }
        },

        /**
         * Format date for display
         */
        formatDate: function(dateString) {
            var date = new Date(dateString);
            return date.toLocaleString();
        }
    };

    /**
     * Helper function to get URL parameter
     */
    function getUrlParameter(name) {
        name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
        var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
        var results = regex.exec(location.search);
        return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
    }

    /**
     * Statistics Chart (if needed)
     */
    var EmailitStats = {

        init: function() {
            this.loadStats();
        },

        loadStats: function() {
            var $statsContainer = $('#emailit-stats');

            if (!$statsContainer.length) {
                return;
            }

            var data = {
                action: 'emailit_get_stats',
                nonce: emailit_ajax.nonce
            };

            $.post(emailit_ajax.ajax_url, data)
                .done(function(response) {
                    if (response.success) {
                        EmailitStats.renderStats(response.data);
                    }
                });
        },

        renderStats: function(stats) {
            var html = '';

            for (var key in stats) {
                if (stats.hasOwnProperty(key)) {
                    html += '<div class="emailit-stat-card">' +
                        '<span class="emailit-stat-number">' + stats[key].value + '</span>' +
                        '<span class="emailit-stat-label">' + stats[key].label + '</span>' +
                    '</div>';
                }
            }

            $('#emailit-stats').html(html);
        }
    };

    /**
     * Initialize when document ready
     */
    $(document).ready(function() {
        EmailitAdmin.init();
        EmailitStats.init();

        // Initialize logging options visibility
        EmailitAdmin.toggleLoggingOptions.call($('#emailit_enable_logging'));

        // Initialize webhook options visibility
        EmailitAdmin.toggleWebhookOptions.call($('#emailit_enable_webhooks'));
    });

    // Expose globally for other scripts
    window.EmailitAdmin = EmailitAdmin;

})(jQuery);