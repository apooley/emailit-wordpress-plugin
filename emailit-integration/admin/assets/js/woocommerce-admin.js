(function($) {
	'use strict';
	
	$(document).ready(function() {
		var $checkPending = $('#emailit-wc-check-pending');
		var $syncStatus = $('#emailit-wc-sync-status');
		var $pendingCount = $('#emailit-wc-pending-count');
		var $processPending = $('#emailit-wc-process-pending');
		var $progress = $('#emailit-wc-progress');
		var $progressBar = $('#emailit-wc-progress-bar');
		var $progressText = $('#emailit-wc-progress-text');
		var $results = $('#emailit-wc-results');
		
		// Check for pending subscriptions (batched scanning)
		$checkPending.on('click', function() {
			var $button = $(this);
			$button.prop('disabled', true).text(emailitWcAdmin.i18n.processing);
			$syncStatus.hide();
			$results.empty();
			$progress.show();
			$progressBar.css('width', '0%');
			$progressText.text('Starting scan...');
			
			var offset = 0;
			var accumulatedCount = 0;
			var scannedTotal = 0;
			
			function scanBatch() {
				$.ajax({
					url: emailitWcAdmin.ajaxUrl,
					type: 'POST',
					data: {
						action: 'emailit_wc_get_pending_count',
						nonce: emailitWcAdmin.nonce,
						offset: offset,
						accumulated_count: accumulatedCount
					},
					success: function(response) {
						if (response.success) {
							if (response.data.completed) {
								// Scanning complete
								accumulatedCount = response.data.count;
								scannedTotal = offset + response.data.scanned;
								
								$progressBar.css('width', '100%');
								$progressText.text(response.data.message);
								$button.prop('disabled', false).text('Check for Pending Subscriptions');
								
								// Store total count for processing
								$.ajax({
									url: emailitWcAdmin.ajaxUrl,
									type: 'POST',
									data: {
										action: 'emailit_wc_store_total_count',
										nonce: emailitWcAdmin.nonce,
										total_count: accumulatedCount
									},
									success: function() {
										// Escape HTML to prevent XSS
										var message = $('<div>').text(response.data.message).html();
										$pendingCount.html('<strong>' + message + '</strong>');
										$syncStatus.show();
										
										if (accumulatedCount > 0) {
											$processPending.show().data('total-count', accumulatedCount);
										} else {
											$processPending.hide();
											var completedMsg = $('<div>').text(emailitWcAdmin.i18n.completed + ' ' + response.data.message).html();
											$results.html('<p style="color: #46b450;">' + completedMsg + '</p>');
										}
									}
								});
							} else {
								// Continue scanning
								accumulatedCount = response.data.accumulated_count;
								scannedTotal = response.data.offset;
								offset = response.data.offset;
								
								// Update progress (estimate based on scanned orders)
								var progressPercent = Math.min(95, (scannedTotal / 10000) * 100);
								$progressBar.css('width', progressPercent + '%');
								$progressText.text(response.data.message);
								
								// Continue scanning with small delay
								setTimeout(scanBatch, 300);
							}
						} else {
							$button.prop('disabled', false).text('Check for Pending Subscriptions');
							var errorMsg = $('<div>').text(response.data.message || emailitWcAdmin.i18n.error).html();
							$results.html('<p style="color: #dc3232;">' + errorMsg + '</p>');
						}
					},
					error: function() {
						$button.prop('disabled', false).text('Check for Pending Subscriptions');
						var errorMsg = $('<div>').text(emailitWcAdmin.i18n.error).html();
						$results.html('<p style="color: #dc3232;">' + errorMsg + '</p>');
					}
				});
			}
			
			// Start scanning
			scanBatch();
		});
		
		// Process pending subscriptions
		$processPending.on('click', function() {
			var $button = $(this);
			$button.prop('disabled', true).text(emailitWcAdmin.i18n.processing);
			$progress.show();
			$progressBar.css('width', '0%');
			$progressText.text('Starting...');
			$results.empty();
			
			var offset = 0;
			var processed = 0;
			var successCount = 0;
			var errorCount = 0;
			var totalCount = $button.data('total-count') || 0;
			
			if (!totalCount) {
				totalCount = 0;
			}
			
			function processBatch() {
				$.ajax({
					url: emailitWcAdmin.ajaxUrl,
					type: 'POST',
					data: {
						action: 'emailit_wc_process_pending',
						nonce: emailitWcAdmin.nonce,
						offset: offset,
						processed: processed,
						success_count: successCount,
						error_count: errorCount,
						total_count: totalCount
					},
					success: function(response) {
						if (response.success) {
							processed = response.data.processed;
							successCount = response.data.success_count;
							errorCount = response.data.error_count;
							
							if (response.data.total_count && response.data.total_count > 0) {
								totalCount = response.data.total_count;
							}
							
							var percent = totalCount > 0 ? Math.min(100, Math.round((processed / totalCount) * 100)) : 0;
							$progressBar.css('width', percent + '%');
							$progressText.text(response.data.message);
							
							if (response.data.completed) {
								$button.prop('disabled', false).text('Process Pending Subscriptions');
								$progressBar.css('width', '100%');
								var completedMsg = $('<div>').text(emailitWcAdmin.i18n.completed).html();
								var resultMsg = $('<div>').text(response.data.message).html();
								$results.html(
									'<div style="background: #fff; border-left: 4px solid #46b450; padding: 10px; margin-top: 10px;">' +
									'<p><strong>' + completedMsg + '</strong></p>' +
									'<p>' + resultMsg + '</p>' +
									'</div>'
								);
							} else {
								offset = response.data.offset;
								setTimeout(processBatch, 500);
							}
						} else {
							$button.prop('disabled', false).text('Process Pending Subscriptions');
							var errorMsg = $('<div>').text(response.data.message || emailitWcAdmin.i18n.error).html();
							$results.html('<p style="color: #dc3232;">' + errorMsg + '</p>');
						}
					},
					error: function() {
						$button.prop('disabled', false).text('Process Pending Subscriptions');
						var errorMsg = $('<div>').text(emailitWcAdmin.i18n.error).html();
						$results.html('<p style="color: #dc3232;">' + errorMsg + '</p>');
					}
				});
			}
			
			// Start processing
			processBatch();
		});
	});
	
})(jQuery);

