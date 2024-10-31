<?php

/**
 * Plugin Name:     Paperdork voor WooCommerce
 * Description:     Met de Paperdork plugin kun je jouw WooCommerce webshop automatisch koppelen aan je Paperdork boekhouding. Zet bestellingen automatisch in je administratie, verstuur Paperdork facturen naar je klanten en meer.
 * Version:         1.9.2
 * Requires PHP:    7.2
 * Author:          Paperdork
 * Author URI:      https://paperdork.nl/webshop-koppeling-woocommerce/
 * Requires Plugins: woocommerce
 */

if (is_readable(__DIR__ . '/vendor/autoload.php')) {
	require __DIR__ . '/vendor/autoload.php';
}

define('PAPERDORK_VERSION', '1.9.2');

require_once(__DIR__ . '/classes/Paperdork.php');

$Paperdork = new Paperdork();

add_action('template_redirect', [$Paperdork, 'template_redirect'], 1);
add_action('init', [$Paperdork, 'on_init'], 1);
add_action('admin_init', [$Paperdork, 'admin_init'], 1);

add_action('woocommerce_email', [$Paperdork, 'unhook_woo_emails']);
add_action('woocommerce_checkout_update_order_review', [$Paperdork, 'taxexempt_checkout_update_order_review']);

add_action('admin_print_styles', function () {
	wp_enqueue_style('paperdork-admin', plugin_dir_url(__FILE__) . 'dist/admin/css/style.css', '', PAPERDORK_VERSION);
	wp_enqueue_script('paperdork-admin', plugin_dir_url(__FILE__) . 'dist/admin/js/main.js', '', PAPERDORK_VERSION);
});

add_filter('woocommerce_billing_fields', [$Paperdork, 'add_billing_fields'], 99);
add_action('woocommerce_admin_order_data_after_billing_address', [$Paperdork, 'add_billing_fields_admin'], 10, 1);

add_action('wp_enqueue_scripts', function () {
	wp_enqueue_script('paperdork-main', plugin_dir_url(__FILE__) . 'dist/js/main.js', ['jquery'], PAPERDORK_VERSION);
}, 15);

add_action('wp_ajax_vatNumber', [$Paperdork, 'set_vatnumber_sesion']);
add_action('wp_ajax_nopriv_vatNumber', [$Paperdork, 'set_vatnumber_sesion']);

add_action('woocommerce_before_calculate_totals', [$Paperdork, 'before_calculate_totals'], 10, 1);
add_filter('woocommerce_package_rates', [$Paperdork, 'woocommerce_package_rates'], 10, 2);
add_action('woocommerce_checkout_update_order_meta', [$Paperdork, 'woocommerce_checkout_update_order_meta'], 10, 2);

add_filter('woocommerce_checkout_get_value', function ($value, $input) {
	if ($input == 'vatNumber' && !empty(WC()->session->get('vatNumber'))) $value = WC()->session->get('vatNumber')['billing'];
	return $value;
}, 10, 2);

add_action('woocommerce_checkout_update_order_review', function ($post_data) {
	$packages = WC()->cart->get_shipping_packages();
	foreach ($packages as $package_key => $package) {
		WC()->session->set('shipping_for_package_' . $package_key, false);
	}
}, 10, 1);

add_action('woocommerce_order_refunded', [$Paperdork, 'woocommerce_order_refunded'], 10, 2);

register_activation_hook(__FILE__, function () {
	$Paperdork = new Paperdork();
	$Paperdork->on_init();
});

if (!wp_next_scheduled('paperdork_autoCreateInvoice')) {
	wp_schedule_event(time(), 'hourly', 'paperdork_autoCreateInvoice');
}

add_action('paperdork_autoCreateInvoice', [$Paperdork, 'autoCreateInvoices']);
