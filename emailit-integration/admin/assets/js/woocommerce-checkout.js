(function($) {
	'use strict';
	
	// Function to check custom checkbox and update hidden field
	function updateEmailItSubscribe() {
		var $customCheckbox = $('.form-row.mycheckbox input[type="checkbox"]');
		var $checkoutForm = $('form.checkout');
		
		// Make sure checkout form exists
		if ($checkoutForm.length === 0) {
			return false;
		}
		
		if ($customCheckbox.length > 0) {
			// Check if checkbox is checked
			var isChecked = $customCheckbox.is(':checked') ? 1 : 0;
			
			// Remove existing hidden field if present
			$('#emailit_subscribe').remove();
			
			// Add hidden field with checkbox state
			$('<input>', {
				type: 'hidden',
				id: 'emailit_subscribe',
				name: 'emailit_subscribe',
				value: isChecked
			}).appendTo($checkoutForm);
			
			return true;
		}
		
		return false;
	}
	
	// Initialize when document is ready
	$(document).ready(function() {
		// Check on page load
		updateEmailItSubscribe();
		
		// Check when custom checkbox changes
		$(document).on('change', '.form-row.mycheckbox input[type="checkbox"]', function() {
			updateEmailItSubscribe();
		});
		
		// Check before form submission
		$(document.body).on('checkout_place_order', function() {
			updateEmailItSubscribe();
		});
		
		// Also check on WooCommerce checkout update events (payment method changes, etc.)
		$(document.body).on('updated_checkout', function() {
			// Small delay to ensure DOM is updated
			setTimeout(function() {
				updateEmailItSubscribe();
			}, 100);
		});
	});
	
})(jQuery);

