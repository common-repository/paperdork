<?php

/**
 * 
 * Main Paperdork Class
 *  
 */

require_once("PaperdorkInvoiceMetabox.php");
require_once("PaperdorkSettings.php");
class Paperdork {

	protected $client_id = '';
	protected $client_secret = '';
	protected $api_url = 'https://api.paperdork.nl';
	protected $template = 0;
	protected $vat_exempted = false;

	public function __construct() {
		if (property_exists($this, 'client_id') && $this->client_id !== '') update_option('paperdork_client_id', $this->client_id);
		if (property_exists($this, 'client_secret') && $this->client_secret !== '') update_option('paperdork_client_secret', $this->client_secret);
		$this->client_id = get_option('paperdork_client_id');
		$this->client_secret = get_option('paperdork_client_secret');
		if (get_option('paperdork_test_api_mode'))  $this->api_url = 'https://paperdorkapi-test.azurewebsites.net';
	}

	public function runCallback() {

		if (isset($_GET['?code'])) $_GET['code'] = $_GET['?code'];

		if (!current_user_can('manage_options') && !current_user_can('manage_woocommerce')) {
			die('Has no admin access');
			header('Location: /');
			exit();
		}

		if (!isset($_GET['code'])) {
			header('Location: ' . $this->getAuthUrl());
			exit();
		} elseif (empty($_GET['state']) || (isset($_SESSION['oauth2state']) && $_GET['state'] !== $_SESSION['oauth2state'])) {
			if (isset($_SESSION['oauth2state'])) {
				unset($_SESSION['oauth2state']);
			}
			header('Location: ' . $this->getAuthUrl());
			exit();
		} else {
			$this->getCallBackAccessToken();
		}
	}

	public function hasFirstConfig() {
		if ($this->client_id !== '' && $this->client_secret !== '' && strlen($this->client_id) >= 5 && strlen($this->client_secret) >= 5) return true;
		return false;
	}

	public function hasClientIdOrSecret() {
		if ($this->client_id !== '' || $this->client_secret !== '') return true;
		return false;
	}

	public function getAuthUrl() {
		$provider = $this->getOauthProvider();
		$authorizationUrl = $provider->getAuthorizationUrl();
		$_SESSION['oauth2state'] = $provider->getState();
		return $authorizationUrl;
	}

	protected function getOauthProvider() {
		$provider = new \League\OAuth2\Client\Provider\GenericProvider([
			'clientId'                => $this->client_id,    // The client ID assigned to you by the provider
			'clientSecret'            => $this->client_secret,    // The client password assigned to you by the provider
			'redirectUri'             => $this->getRedirectUrl(),
			'urlAuthorize'            => $this->api_url . '/oauth/authorize',
			'urlAccessToken'          => $this->api_url . '/oauth/token',
			'urlResourceOwnerDetails' => $this->api_url . '/oauth/token',
		]);

		return $provider;
	}

	public function getCallBackAccessToken() {
		$provider = $this->getOauthProvider();
		try {

			// Try to get an access token using the authorization code grant.
			$accessToken = $provider->getAccessToken('authorization_code', [
				'code' => sanitize_text_field($_GET['code'])
			]);

			if ($accessToken->getToken() !== 'invalid') {
				list($header, $payload, $signature) = explode(".", $accessToken->getToken());
				$payload = json_decode(base64_decode($payload), true);
				update_option('paperdork_access', array('token' => $accessToken->getToken(), 'refresh' => $accessToken->getRefreshToken(), 'expire_time' => $payload['exp']));
				update_option('paperdork_connected_since', date('Y-m-d H:i:s'));
				$this->setDefaults();
				update_option('paperdork_refresh_error', false);
				header('Location: ' . admin_url('admin.php?page=paperdork'));
				exit();
			} else {
				header('Location: ' . $this->getRedirectUrl());
				exit();
			}
		} catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
			header('Location: ' . $this->getRedirectUrl());
			exit();
		}
	}

	public function setDefaults() {

		$defaults = [
			'auto_process_order' => 1,
			'auto_generate_invoice' => [
				'completed' => ['parent_enabled' => 1, 'all' => 1],
				'processing' => ['parent_enabled' => 1, 'all' => 1]
			],
			'auto_credit_invoice' => 1,
			'send_method' => 'manual_send',
			'payment_term' => '30'
		];

		update_option('paperdork-settings', $defaults);
	}

	public function getAuthToken() {
		$paperdork_access = get_option('paperdork_access');
		if (time() < ($paperdork_access['expire_time'])) return $paperdork_access['token'];
		else if ($paperdork_access['token'] !== '') return $this->refreshToken($paperdork_access);
		return;
	}

	public function getRedirectUrl() {
		return home_url() . $this->getRedirectPath();
	}

	protected function getRedirectPath() {
		return str_replace(home_url(), '', get_admin_url()) . 'admin.php?page=paperdork&callback=yes&';
	}

	public function sendLogs($action = '', $details = [], $data = '') {
		if (!get_option('paperdork_debug_mode')) return;

		$debug_end = get_option('paperdork_debug_mode_end');

		if ($debug_end == '')	update_option('paperdork_debug_mode_end', date('Y-m-d H:i:s', strtotime('5 days')));
		else if ($debug_end < date('Y-m-d H:i:s')) {
			update_option('paperdork_debug_mode', 0);
			update_option('paperdork_debug_mode_end', '');
			return;
		}

		$plugins = get_option('active_plugins');

		if (is_wp_error($details)) $details = ['error' => $details];
		if ($data != '') $details['post_data'] = $data;

		global $wpdb;
		$orders = $wpdb->get_results("SELECT ID, post_date, post_type FROM " . $wpdb->posts . " WHERE (post_type = 'shop_order' OR post_type = 'shop_order_placehold') AND ID not in (SELECT post_id FROM " . $wpdb->postmeta . " WHERE meta_key = 'paperdork_invoice')  AND ID in (SELECT post_id FROM " . $wpdb->postmeta . " WHERE meta_key = '_prepared_invoice')");
		$no_invoice_orders = $wpdb->get_results("SELECT ID, post_date, post_type FROM " . $wpdb->posts . " WHERE (post_type = 'shop_order' OR post_type = 'shop_order_placehold') AND ID not in (SELECT post_id FROM " . $wpdb->postmeta . " WHERE meta_key = 'paperdork_invoice')");

		foreach ($orders as $key => $order) {
			$orders[$key]->woo_status = (wc_get_order($order->ID))->get_status();
			$orders[$key]->woo_payment_method = (wc_get_order($order->ID))->get_payment_method_title();
			$orders[$key]->prepared = get_post_meta($order->ID, '_prepared_invoice', true);
		}
		foreach ($no_invoice_orders as $key => $order) {
			$no_invoice_orders[$key]->woo_status = (wc_get_order($order->ID))->get_status();
			$no_invoice_orders[$key]->woo_payment_method = (wc_get_order($order->ID))->get_payment_method_title();
			$no_invoice_orders[$key]->prepared = get_post_meta($order->ID, '_prepared_invoice', true);
		}

		$log_data = [
			'site' => $_SERVER["HTTP_HOST"],
			'action' => $action,
			'details' => array_merge(
				[
					'PAPERDORK_VERSION' => PAPERDORK_VERSION,
					'server_ip' => $_SERVER["SERVER_ADDR"],
					'client_ip' => $_SERVER["REMOTE_ADDR"],
					'plugins' => $plugins,
					'settings' => $this->plugin_admin->getSettings(),
					'debug_end' => $debug_end,
					'orders' => $orders,
					'no_invoice_orders' => $no_invoice_orders,
					'paperdork_connected_since' => get_option('paperdork_connected_since')
				],
				$details
			)
		];

		$args = [
			'headers' => [
				'Content-Type' => 'application/json'
			],
			'timeout' => 5,
			'user-agent' => 'PaperdorkWP/' . PAPERDORK_VERSION . " " . get_bloginfo('url'),
			'body' => json_encode($log_data),
			'redirection' => 0
		];

		wp_remote_post('https://koppelingen.paperdork.nl/webhooks/wordpress/errorlog', $args);
	}

	public function refreshToken($paperdork_access) {

		if ($paperdork_access == '') return null;

		$response = $this->callApi('/oauth/token', [
			'client_id' => $this->client_id,
			'refresh_token' => $paperdork_access['refresh'],
			'grant_type' => 'refresh_token',
			'client_secret' => $this->client_secret
		], 'POST');

		if (!is_array($response)) $response = [];

		if (array_key_exists('refresh_token', $response)) {
			update_option('paperdork_refresh_error', false);
			update_option('paperdork_refresh_error_send_mail', false);
			$expire_time = strtotime($response['expires']);
			update_option('paperdork_access', array('token' => $response['access_token'], 'refresh' => $response['refresh_token'], 'expire_time' => $expire_time));
			return $response['access_token'];
		} else {

			/*
			$paperdork_refresh_error = get_option('paperdork_refresh_error');
			$send_mail_delay = date('Y-m-d H:i:s', strtotime('-32 hour'));

			if ($paperdork_refresh_error != '' && $paperdork_refresh_error > $send_mail_delay && !get_option('paperdork_refresh_error_send_mail')) {
				$body = '
					<p>Oeps!</p>
					<p>De connectie met de Paperdork app is weggevallen, mogelijk door het gebruik van andere plugins op WordPress.</p>
					<p>Geen zorgen, bij de instellingen van de Paperdork plugin vind je een knop om opnieuw te koppelen. Je bestellingen worden dan (met terugwerkende kracht) in Paperdork verwerkt. Daar hoef je niets voor te doen! Dit kan eventjes duren.</p>			
					<p>Team Paperdork</p>
				';

				$site_admin = get_option('admin_email');

				$headers = array('Content-Type: text/html; charset=UTF-8');
				wp_mail($site_admin, 'Actie nodig: je Paperdork plug-in moet opnieuw gekoppeld worden', $body, $headers);
				update_option('paperdork_refresh_error_send_mail', true);
			}

			if ($paperdork_refresh_error == '')  update_option('paperdork_refresh_error', date('Y-m-d H:i:s'));
			*/
		}

		return '';
	}

	public function callApi($route = '', $data = '', $method = '', $headers = [], $auth_token = '') {

		if ($route == '') return 'Unknown route';
		if ($method == '') $method = 'GET';
		if ($headers == null) $headers = [];

		$method = strtoupper($method);

		if (is_array($data)) $content_type = 'application/x-www-form-urlencoded';
		else $content_type = 'application/json';

		if ($route !== '/oauth/token' && $auth_token == '') $auth_token = $this->getAuthToken();

		if ($auth_token != '') {
			$headers = array_merge([
				'Authorization' => 'Bearer ' . $auth_token,
				'Content-Type' => $content_type
			], $headers);
		} else if ($route == '/oauth/token') {
			$headers = array_merge([
				'Content-Type' => $content_type
			], $headers);
		} else 	return 'invalid';

		$args = [
			'headers' => $headers,
			'timeout' => 30,
			'user-agent' => 'PaperdorkWP/' . PAPERDORK_VERSION . " " . get_bloginfo('url')
		];

		if (!empty($data)) {
			$args['body'] = $data;
		}

		$url = $this->api_url;

		if (get_option('paperdork_proxy_mode'))  $url = 'https://koppelingen.paperdork.nl/api-proxy';

		$url .=  $route;

		if ($method == 'POST') {
			$response = wp_remote_post($url, $args);
		} else $response = wp_remote_get($url, $args);


		$this->sendLogs($route, $response, ($method == 'POST' ? 	$args['body'] : ''));
		if (is_array($response) && isset($response['body'])) {
			return json_decode($response['body'], true);
		} else return [];
	}

	public function on_init() {
		$this->registerMetaboxes();
		$this->registerAdminActions();
		add_action('woocommerce_order_status_changed', [$this, 'orderStatusHook'], 50, 4);

		$this->addRewriteUrls();
	}

	public function admin_init() {
		$this->addRewriteUrls();
	}

	protected function addRewriteUrls() {
		add_rewrite_rule(
			$this->getRedirectPath() . '/?$',
			'index.php',
			'top'
		);

		add_rewrite_rule(
			$this->getRedirectPath() . '?$',
			'index.php',
			'top'
		);

		flush_rewrite_rules();
	}


	public function template_redirect($template) {
		global $wp, $wp_rewrite;

		if ($_SERVER['REQUEST_URI'] == $this->getRedirectPath() || strpos($_SERVER['REQUEST_URI'], $this->getRedirectPath()) !== false) {
			return $this->runCallback();
		}
		return $template;
	}

	function getBaseInvoiceJson($order, $customer, $settings = [], $lines = [], $refund = false) {
		$order_id = $order->get_id();
		$order_data = $order->get_data();

		$date_paid = $order->get_date_paid();

		if ($date_paid) $date_paid = substr($date_paid->__toString(), 0, 10);


		$json = [
			'date' => substr($order->get_date_created()->__toString(), 0, 10),
			'description' => ($refund ? 'Terugbetaling' : 'Bestelling') . ' WooCommerce ' . $order_id,
			'reference' => ($refund ? 'Terugbetaling bestelling' : 'Bestelling') . ' ' . $order_id,
			'customer' => $customer->getArray(),
			'paymentTerm' => ($settings['payment_term'] == '' ? 30 : $settings['payment_term']),
			'lines' => $lines,
			'status' => 1
		];

		if ($this->template != '') $json['templateId'] = $this->template;

		$json['status'] = 3;

		if ($settings['send_method'] == 'send') {
			$json['emailMessage'] = [
				'email' => $order_data['billing']['email']
			];
		}

		if ($date_paid) {
			$json['datePaid'] = $date_paid;
			// Set status to paid
			$json['status'] = 4;
		}

		return $json;
	}

	public function createInvoice($order_id, $prepare = false, $sepa = false) {

		$order = wc_get_order($order_id);

		if (!$order) return;

		$paperdork_invoice = get_post_meta($order_id, 'paperdork_invoice', true);
		if ($paperdork_invoice != '') return;

		if (!$this->plugin_admin) $this->plugin_admin = $settings = (new PaperdorkSettings($this));

		$settings = $this->plugin_admin->getSettings();
		$this->template = $settings['invoice_template'];
		$this->vat_exempted = $settings['vat_exempt'];

		if (isset($settings['ignore_zero_amount']) && $settings['ignore_zero_amount'] && $order->get_total() == 0) {
			// Will ignore orders with a 0 amount
			update_post_meta($order_id, '_ignored_because_zero', 1);
			return;
		}

		$order_data = $order->get_data();
		$customer = $this->parseBillingToCustomer($order_data['billing']);

		$vatNumber = get_post_meta($order_id, 'vatNumber', true);
		if ($vatNumber !== '') $customer->setVatNumber($vatNumber);

		$lines = $this->getLines($order->get_items(), ['total' => $order->get_total_discount(), 'vat' => $order->get_discount_tax()], $customer);
		if (empty($lines)) return;
		$json = $this->getBaseInvoiceJson($order, $customer, $settings, $lines);

		$shipping_has_vat = (!$this->vat_exempted);

		if (($customer->is_company() && $customer->is_outside_NL()) ||
			($customer->is_private() && $customer->is_outside_EU())
		) {
			$item_rate = 0;
			$shipping = $order->get_shipping_total();
			$shipping_has_vat = false;
		} else $shipping = $order->get_shipping_total() + $order->get_shipping_tax();

		if ($shipping > 0) {
			$json['shippingCosts'] = [
				'amount' => round($shipping, 2),
				'includeVat' => $shipping_has_vat
			];
		}

		if ($sepa) $json['paymentMethod'] = 3;

		if ($prepare) update_post_meta($order_id, '_prepared_invoice', $json);
		else $this->pushInvoice($order_id, $json);
	}

	public function pushInvoice($id, $json, $token = '') {
		// Call API		

		if (!isset($json['lines']) || empty($json['lines'])) return;

		$json['customer'] = $this->checkCountryCodesCustomer($json['customer']);
		$response = $this->callApi('/v1/Invoices/create', json_encode($json), 'POST', [], $token);
		if (isset($response['id']) && $response['id'] != '' && is_numeric($response['id'])) update_post_meta($id, 'paperdork_invoice', $response['id']);
	}

	protected function checkCountryCodesCustomer($customer) {
		foreach (['invoiceAddress', 'contactAddress'] as $key) {
			if (strlen($customer[$key]['countryCode']) < 2) $customer[$key]['countryCode'] = 'NL';
			else if (strtolower($customer[$key]['countryCode']) == 'gb') $customer[$key]['countryCode'] = 'GB-ENG';
		}

		return $customer;
	}

	public function createCredit($order_id, $refund_id = '') {

		$order = wc_get_order($order_id);
		if (!$order) return;

		if ($refund_id) {
			return $this->createInvoiceFromRefund($order_id, $refund_id);
		} else {
			// Refund full invoice
			if ('refunded' == $order->get_status()) {
				return;
			}
			$paperdork_invoice = get_post_meta($order_id, 'paperdork_invoice', true);
			if ($paperdork_invoice == '') return;
			$this->createFullCreditInvoice($paperdork_invoice);
			$order->update_status('refunded');
		}
	}

	public function createInvoiceFromRefund($order_id, $refund_id) {

		$order = wc_get_order($order_id);
		if (!$order) return;

		$refund = wc_get_order($refund_id);
		if (!$refund) return;

		$paperdork_invoice = get_post_meta($refund_id, 'paperdork_invoice', true);
		if ($paperdork_invoice != '') return;

		$settings = $this->plugin_admin->getSettings();
		$this->template = $settings['invoice_template'];

		if (isset($settings['vat_exempt'])) $this->vat_exempted = $settings['vat_exempt'];
		else $this->vat_exempted = false;

		$order_data = $order->get_data();
		$customer = $this->parseBillingToCustomer($order_data['billing']);

		$vatNumber = get_post_meta($order_id, 'vatNumber', true);
		if ($vatNumber !== '') $customer->setVatNumber($vatNumber);

		$lines = $this->getLines($refund->get_items(), ['total' => $refund->get_total_discount(), 'vat' => $refund->get_discount_tax()], $customer);

		if (empty($lines)) return;

		$shipping = $refund->get_shipping_total() + $refund->get_shipping_tax();

		if ($shipping < 0) {
			$shipping_line = [
				'type' => 1,
				'description' => 'Verzendkosten',
				'includeVat' => true,
				'revenueType' => 2,
				'count' => '1',
				'amount' => round($shipping, 2)
			];

			if ($this->vat_exempted)	$shipping_line['vatType'] = 1;
			else {
				$shipping_line['vatType'] = 0;
				$shipping_line['vatPercentage'] = round($refund->get_shipping_tax() / $refund->get_shipping_total() * 100, 0);
			}

			$lines[] = $shipping_line;
		}

		$json = $this->getBaseInvoiceJson($order, $customer, $settings, $lines, true);

		$json['date'] = substr($refund->order_date, 0, 10);
		$json['datePaid'] = substr($refund->order_date, 0, 10);
		$json['status'] = 4;

		$response = $this->callApi('/v1/Invoices/create', json_encode($json), 'POST');
		if ($response['id'] !== '') update_post_meta($refund->ID, 'paperdork_invoice', $response['id']);
	}

	public function createFullCreditInvoice($invoice_number) {
		if ($invoice_number == '') return;
		$response = $this->callApi('/v1/Invoices/credit', json_encode(['id' => $invoice_number]), 'POST');
	}

	protected function parseBillingToCustomer($billing) {
		return new PaperdorkCustomer($this, $billing);
	}

	public function splitAddress($address) {
		if ($address == '') return ['original' => '', 'street' => '', 'housenumber' => ''];
		preg_match('/([^\d]+)\s?(.+)/i', $address, $splitted);
		if (empty($splitted))  return ['original' => $address, 'street' => '', 'housenumber' => ''];
		return array('original' => $splitted[0], 'street' => $splitted[1], 'housenumber' => $splitted[2]);
	}

	protected function getLines($items, $discount, $customer) {

		$lines = [];
		$tax = new WC_Tax();
		$vats_discount = [];

		$only_goods = true;

		foreach ($items as $item) {
			$product = wc_get_product($item['variation_id'] ? $item['variation_id'] : $item['product_id']);
			$taxes = $tax->get_rates($product->get_tax_class());
			$rates = [];

			$item_rate = 0;

			// Check if taxes are actually found
			if (count($taxes)) {
				$rates = array_shift($taxes);
				if (isset($rates['rate'])) $item_rate = $rates['rate'];
				else $item_rate = round(array_shift($rates));
			}


			$total = $item->get_total();
			$subtotal = $item->get_subtotal();

			// Fallback if no class can be found
			if ($product->get_tax_class() == '' && $item_rate == '') {
				$tax_amount = $item->get_subtotal_tax();
				if ($tax_amount > 0) $item_rate = round(((ceil($tax_amount * 100) / 100) / (ceil($subtotal * 100) / 100)) * 100);
			}

			$quantity = $item->get_quantity();
			if ($quantity == '' || $quantity == 0) $quantity = 1;

			$unit_amount = $subtotal / $quantity;

			/**
			 *  item_rated is forced to 0 if
			 * 
			 * 	Customer is a Company Outside NL
			 * 	Customer is a Private Customer Outside the EU, and if the product is not a virtual product
			 */

			if (($customer->is_company() && $customer->is_outside_NL()) ||
				($customer->is_private() && $customer->is_outside_EU() && !$product->is_virtual())
			) {
				$item_rate = 0;
			}

			if ($total !== $subtotal) $vats_discount[$item_rate] += ($subtotal - $total);
			if (!$product->is_virtual()) $only_goods = false;

			$line = [
				'type' => 1,
				'description' => $product->get_name(),
				'includeVat' => false,
				'revenueType' => ($product->is_virtual() ? 1 : 2),
				'count' => $quantity,
				'amount' => $unit_amount
			];

			if ($this->vat_exempted && $item_rate > 0) {
				$line['vatType'] = 1;
			} else {
				$line['vatType'] = 0;
				$line['vatPercentage'] = $item_rate;
			}

			$lines[] = $line;
		}

		if ($discount['total'] > 0) {
			if ($this->vat_exempted) {
				$lines[] = [
					'type' => 1,
					'description' => 'Korting',
					'includeVat' => false,
					'revenueType' => $only_goods ? 2 : 1,
					'count' => 1,
					'amount' => ($discount['total'] * -1),
					'vatType' => 1
				];
			} else {
				foreach ($vats_discount as $rate => $amount) {
					if ($amount == 0) continue;
					$line = [
						'type' => 1,
						'description' => (count($vats_discount) == 1 ? 'Korting' : 'Korting met ' . $rate . '% btw'),
						'includeVat' => false,
						'revenueType' => $only_goods ? 2 : 1,
						'count' => 1,
						'amount' => ($amount * -1),
						'vatType' => 0,
						'vatPercentage' => round($rate)
					];

					$lines[] = $line;
				}
			}
		}

		return $lines;
	}

	public function registerMetaboxes() {
		new PaperdorkInvoiceMetabox($this);
	}

	public function registerAdminActions() {
		add_action('wp_ajax_create_paperdork', array($this, 'adminCreatePaperdork'));
		$this->plugin_admin = $plugin_admin = new PaperdorkSettings($this);
		add_action('admin_menu', array($plugin_admin, 'adminMenu'));
	}

	public function adminCreatePaperdork() {

		if (!is_admin() || $_GET['order'] == '') {
			header("Location: /");
			exit();
		}

		if ($_GET['type'] == 'invoice') {
			$this->createInvoice(sanitize_text_field($_GET['order']));
			header('Location: ' . admin_url('post.php?post=' . sanitize_text_field($_GET['order']) . '&action=edit'));
			exit();
		} else if ($_GET['type'] == 'credit') {
			$this->createCredit(sanitize_text_field($_GET['order']), sanitize_text_field($_GET['refund']));
			header('Location: ' . admin_url('post.php?post=' . sanitize_text_field($_GET['order']) . '&action=edit'));
			exit();
		}
	}

	public function orderStatusHook($id, $from, $to, $order) {
		// We do not want the woocommerce prefix
		$to = str_replace('wc-', '', $to);
		$from = str_replace('wc-', '', $from);

		if ($this->checkIfValidOrderStatus($order, $to)) {
			$this->createInvoice($id, true);
			update_post_meta($id, 'auto_create', date("Y-m-d H:i:s"));
		}
	}

	public function checkIfValidOrderStatus($order, $status = null) {
		if (!$status || $status == '') $status = $order->get_status();

		$status = str_replace('wc-', '', $status);
		$settings = (new PaperdorkSettings($this))->getSettings();

		if (!$settings['auto_process_order']) return false;
		$order_gateway = wc_get_payment_gateway_by_order($order);


		if ($settings['auto_generate_invoice'][$status]["parent_enabled"]) {
			$allowed_gateways = $settings['auto_generate_invoice'][$status];
			if (!$allowed_gateways) $allowed_gateways = [];
			if ((isset($allowed_gateways['all']) && $allowed_gateways['all']) || (isset($allowed_gateways[$order_gateway->id]) && $allowed_gateways[$order_gateway->id])) return true;
		}

		return false;
	}

	public function isConnected() {
		$paperdork_access = get_option('paperdork_access');
		return !($paperdork_access == '' || $paperdork_access['token'] == '');
	}

	public function disConnect() {
		delete_option('paperdork_access');
	}

	public function needsReconnect() {
		return get_option('paperdork_refresh_error');
	}

	public function unhook_woo_emails($email_class) {
		if (!$this->plugin_admin) return;
		$settings = $this->plugin_admin->getSettings();
		if (!isset($settings['send_woo_invoice_mail']) || !$settings['send_woo_invoice_mail']) {
			remove_action('woocommerce_order_status_completed_notification', array($email_class->emails['WC_Email_Customer_Completed_Order'], 'trigger'));
		}

		remove_action('woocommerce_order_fully_refunded_notification', array($email_class->emails['WC_Email_Customer_Refunded_Order'], 'trigger_full'));
		remove_action('woocommerce_order_partially_refunded_notification', array($email_class->emails['WC_Email_Customer_Refunded_Order'], 'trigger_partial'));
	}

	public function taxexempt_checkout_update_order_review($post_data) {
		global $woocommerce;
		$settings = $this->plugin_admin->getSettings();
		$woocommerce->customer->set_is_vat_exempt(false);
		if (array_key_exists('vat_exempt', $settings) && $settings['vat_exempt']) $woocommerce->customer->set_is_vat_exempt(true);
	}

	public function add_billing_fields($old_fields) {
		$new_fields = [];
		foreach ($old_fields as $key => $value) {
			$new_fields[$key] = $value;
			if ($key == 'billing_company') {
				$new_fields['vatNumber'] = [
					'label' => __('BTW nummer')
				];
			}
		}
		return $new_fields;
	}

	public function add_billing_fields_admin($order) {
		$vatNumber = get_post_meta($order->get_id(), 'vatNumber', true);
		if ($vatNumber) echo '<p><strong>' . __('BTW nummer') . ':</strong> ' . $vatNumber . '</p>';
	}

	public function set_vatnumber_sesion() {
		$field_key = 'vatNumber';
		if (isset($_POST[$field_key]) && isset($_POST['fieldset'])) {
			// Get data from custom session variable
			$values = (array) WC()->session->get($field_key);

			// Initializing when empty
			if (!empty($values)) {
				$values = array(
					'billing' => WC()->customer->get_meta('billing_' . $field_key),
				);
			}
			$fieldset  = sanitize_text_field($_POST['fieldset']);
			$vatNumer = sanitize_text_field($_POST[$field_key]);
			$values[$fieldset] = strtoupper($vatNumer);
			WC()->session->set($field_key, wc_clean($values));
			echo json_encode(array($fieldset . '_' . $field_key => $vatNumer));

			wp_die();
		}
	}

	public function before_calculate_totals($cart_obj) {

		$customer = new PaperdorkCustomer($this, WC()->session->get('customer'));
		$vatNumber = WC()->session->get('vatNumber');
		$customer->setVatNumber($vatNumber);

		foreach ($cart_obj->get_cart() as $key => $value) {
			$product = wc_get_product($value['data']->get_id());
			if (($customer->is_company() && $customer->is_outside_NL()) ||
				($customer->is_private() && $customer->is_outside_EU() && !$product->is_virtual())
			) {
				$value['data']->set_tax_status('none');
				$value['data']->set_tax_class(__('Zero rate', 'woocommerce'));
			}
		}
	}

	public function woocommerce_package_rates($rates, $package = []) {
		$customer = new PaperdorkCustomer($this, WC()->session->get('customer'));
		$vatNumber = WC()->session->get('vatNumber');
		$customer->setVatNumber($vatNumber);

		$vat = true;

		if (($customer->is_company() && $customer->is_outside_NL()) ||
			($customer->is_private() && $customer->is_outside_EU())
		) {
			$vat = false;
		}

		foreach ($rates as $key => $rate) {
			if (!$vat) $rates[$key]->taxes = false;
		}

		return $rates;
	}

	public function validateVatNumber($vatNumber, $country_code = 'NL') {
		if ($vatNumber == '' || $vatNumber === null) return false;
		if ($country_code == '') $country_code = 'NL';
		$vatNumber = $this->cleanVatNumber($vatNumber, $country_code);
		if ($country_code == 'NL') return false;
		if (!$this->checkIfEU($country_code)) return true;
		if (substr($vatNumber, 0, 2) == sanitize_text_field($country_code)) $vatNumber = substr($vatNumber, 2);
		$response = $this->callApi('/v1/Taxes/checkvatnumber?countryCode=' . sanitize_text_field($country_code) . '&vatNumber=' . sanitize_text_field($vatNumber));
		if (!empty($response) && is_array($response)) return $response['valid'];
		return true;
	}

	public function checkIfEU($country_code) {
		if ($country_code == '' || strlen($country_code) < 2) $country_code = 'nl';
		$country_code = strtolower($country_code);
		if ($country_code == 'nl') return true;
		$response = $this->callApi('/v1/Countries/get/' . sanitize_text_field($country_code));
		if (!empty($response) && is_array($response)) return $response['isEu'];
		return true;
	}

	public function woocommerce_checkout_update_order_meta($order_id) {
		if (!empty($_POST['vatNumber'])) {
			update_post_meta($order_id, 'vatNumber', sanitize_text_field($this->cleanVatNumber($_POST['vatNumber'])));
		}
	}

	protected function cleanVatNumber($vatNumber, $country_code = 'NL') {
		$vatNumber = strtoupper($vatNumber);
		$vatNumber = str_replace(['.', ',', '-'], '', $vatNumber);
		$vatNumber = preg_replace('/\s+/', '', $vatNumber);
		if (substr($vatNumber, 0, 2) == sanitize_text_field($country_code)) $vatNumber = substr($vatNumber, 2);
		return $vatNumber;
	}

	public function woocommerce_order_refunded($order_id, $refund_id) {
		$settings = $this->plugin_admin->getSettings();
		if (isset($settings['auto_process_order']) && $settings['auto_process_order']) $this->createInvoiceFromRefund($order_id, $refund_id);
	}

	public function autoCreateInvoices() {

		update_option('paperdork_last_cron', date('Y-m-d H:i:s'));

		// Make sure the token always get refreshed
		$token = $this->getAuthToken();

		$this->checkSEPAOrders();

		global $wpdb;
		$results = $wpdb->get_results("SELECT * FROM " . $wpdb->posts . " WHERE (post_type = 'shop_order' OR post_type = 'shop_order_placehold') AND ID not in (SELECT post_id FROM " . $wpdb->postmeta . " WHERE meta_key = 'paperdork_invoice')  AND ID in (SELECT post_id FROM " . $wpdb->postmeta . " WHERE meta_key = '_prepared_invoice')");

		foreach ($results as $post) {
			$prepared_invoice = get_post_meta($post->ID, '_prepared_invoice', true);
			$this->pushInvoice($post->ID, $prepared_invoice, $token);
		}


		$this->checkCompletedOrders();
	}


	public function checkSEPAOrders() {
		global $wpdb;
		$connected_since = get_option('paperdork_connected_since');
		if (!$connected_since)		return;

		$filter = date('Y-m-d H:i:s', strtotime('-1 month'));
		if ($filter < $connected_since) $filter = $connected_since;

		$results = $wpdb->get_results("SELECT * FROM " . $wpdb->posts . " WHERE (post_type = 'shop_order' OR post_type = 'shop_order_placehold') AND post_date > '" . $filter . "' AND ID not in (SELECT post_id FROM " . $wpdb->postmeta . " WHERE meta_key = 'paperdork_invoice')  AND ID in (SELECT post_id FROM " . $wpdb->postmeta . " WHERE meta_key = '_payment_method' AND meta_value = 'mollie_wc_gateway_directdebit')  AND ID not in (SELECT post_id FROM " . $wpdb->postmeta . " WHERE meta_key = '_prepared_invoice') ");
		foreach ($results as $order) $this->createInvoice($order->ID, true, true);
	}

	protected function checkCompletedOrders() {
		global $wpdb;

		$connected_since = get_option('paperdork_connected_since');


		if (!$connected_since) {
			// First try to get first prepared order
			$results = $wpdb->get_results("SELECT * FROM " . $wpdb->posts . " WHERE (post_type = 'shop_order' OR post_type = 'shop_order_placehold') AND ID in (SELECT post_id FROM " . $wpdb->postmeta . " WHERE meta_key = 'paperdork_invoice') ORDER BY post_date ASC LIMIT 1 ");
			// If results
			if ($results && count($results))				update_option('paperdork_connected_since', $results[0]->post_date);

			// Fall back use current date
			update_option('paperdork_connected_since', date('Y-m-d H:i:s'));

			return;
		}


		// Get settings
		$settings = (new PaperdorkSettings($this))->getSettings();


		$filter = date('Y-m-d H:i:s', strtotime('-1 month'));
		if ($filter < $connected_since) $filter = $connected_since;

		$results = $wpdb->get_results("SELECT * FROM " . $wpdb->posts . " WHERE (post_type = 'shop_order' OR post_type = 'shop_order_placehold') AND post_date > '" . $filter . "' AND ID not in (SELECT post_id FROM " . $wpdb->postmeta . " WHERE meta_key = 'paperdork_invoice') AND ID not in (SELECT post_id FROM " . $wpdb->postmeta . " WHERE meta_key = '_prepared_invoice') ");

		foreach ($results as $result) {
			$order = wc_get_order($result->ID);
			if ($this->checkIfValidOrderStatus($order)) {
				$this->createInvoice($result->ID, true);
				update_post_meta($result->ID, 'auto_create', date("Y-m-d H:i:s"));
			}
		}
	}
}

class PaperdorkCustomer {

	protected $Paperdork;
	protected $type = 2;
	protected $company = '';
	protected $name = '';
	protected $contactFirstName = '';
	protected $contactLastName = '';
	protected $email = '';
	protected $phone = '';

	public function __construct(Paperdork $Paperdork, $fields = []) {

		$settings = $Paperdork->plugin_admin->getSettings();

		$this->Paperdork = $Paperdork;

		if (!is_array($fields)) {
			$fields = ((array) $fields);
			foreach ($fields as $key => $value) {
				if (strpos($key, 'data') !== false) {
					$fields = $fields[$key];
					break;
				}
			}
			if (isset($fields['billing'])) foreach ($fields['billing'] as $key => $value) $fields[$key] = $value;
		}

		if (!empty($fields)) {

			if (!isset($fields['country']) && isset($fields['shipping_country']) && $fields['shipping_country'] !== '') $fields['country'] = $fields['shipping_country'];
			if ($fields['country'] == '' || strlen($fields['country']) < 2) $fields['country'] = 'NL';

			if ($fields['company'] == '') $this->name = $fields['first_name'] . ' ' . $fields['last_name'];
			else {
				$this->name = $fields['company'];
				if ($fields['country'] == 'NL') $this->type = 1;
			}

			$this->contactFirstName = $fields['first_name'];
			$this->contactLastName = $fields['last_name'];
			$this->email = $fields['email'];
			$this->phone = $fields['phone'];

			if (!$settings['house_number_address_2']) {
				$splitted = $Paperdork->splitAddress($fields['address_1']);
			} else {
				$splitted['street'] = $fields['address_1'];
				$splitted['housenumber'] = $fields['address_2'];
			}

			$this->contactAddress = $this->invoiceAddress = [
				'street' => $splitted['street'],
				'homeNumber' => $splitted['housenumber'],
				'postalCode' => $fields['postcode'],
				'city' => $fields['city'],
				'countryCode' => $fields['country'],
			];

			$this->countryCode = $fields['country'];

			if (array_key_exists('vatNumber', $fields) && $fields['vatNumber'] != '') {
				$this->setVatNumber($fields['vatNumber']);
			}

			$this->setVATStatus();
		}
	}

	protected function setVATStatus() {
		$is_company = false;
		$is_outside_NL = false;
		$is_outside_EU = false;

		if ($this->type == '1') $is_company = true;
		if (isset($this->invoiceAddress) && $this->invoiceAddress['countryCode'] !== 'NL' && $this->invoiceAddress['countryCode'] !== '') {
			$is_outside_NL = true;
			$is_outside_EU = !$this->Paperdork->checkIfEU($this->invoiceAddress['countryCode']);
		}

		$this->is_company = $is_company;
		$this->is_private = !$is_company;
		$this->is_outside_NL = $is_outside_NL;
		$this->is_outside_EU = $is_outside_EU;
	}
	public function getArray() {
		$array = [];
		foreach ($this as $key => $value) {
			if (in_array($key, ['is_company', 'is_outside_NL', 'is_outside_EU'])) continue;
			$array[$key] = $value;
		}
		return $array;
	}

	public function is_company() {
		return $this->is_company;
	}
	public function is_private() {
		return $this->is_private;
	}
	public function is_outside_NL() {
		return $this->is_outside_NL;
	}
	public function is_outside_EU() {
		return $this->is_outside_EU;
	}

	public function setVatNumber($vatNumber) {
		if (is_array($vatNumber)) {
			$vatNumber = $vatNumber['billing'];
		}

		if ($this->Paperdork->validateVatNumber($vatNumber, $this->countryCode)) {
			$this->vatNumber = $vatNumber;
			$this->type = 1;
		} else $this->vatNumber = '';

		$this->setVATStatus();
	}
}
