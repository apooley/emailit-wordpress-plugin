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
        },

        /**
         * Initialize tab functionality
         */
        initTabs: function() {
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
         * Format date for display
         */
        formatDate: function(dateString) {
            var date = new Date(dateString);
            return date.toLocaleString();
        }
    };

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
    });

    // Expose globally for other scripts
    window.EmailitAdmin = EmailitAdmin;

})(jQuery);