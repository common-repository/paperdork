$ = jQuery.noConflict();

$( document ).ready(function() {
	if($('form.woocommerce-checkout').length){
		$("input#vatNumber").on('focusout', function(){			
			$.ajax({
				type: 'POST',
				url: wc_checkout_params.ajax_url,
				data: {
						'action': 'vatNumber',
						'vatNumber': $(this).val(),
						'fieldset' : 'billing'
				},
				success: function (result) {
						$(document.body).trigger('update_checkout', { update_shipping_method: true }); // Update checkout processes
				}
		}); 
		}) 

		$("input#vatNumber").trigger('focusout');
	}
});
