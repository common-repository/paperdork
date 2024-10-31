/*

		Paperdork Connector

		author: Roefja
		email: info@roefja.com
		website: www.roefja.com

		© 2021 - All rights reserved

*/

jQuery(document).ready(function ($) {
	$(".woocommerce_page_paperdork .status_option input.parent_option").on('change', function () {
		let payment_options = $(this).closest(".status_option").find(".payment_options");
		if ($(this).prop('checked')) $(payment_options).addClass("show");
		else $(payment_options).removeClass("show");
	})
});