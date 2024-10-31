<?php

/**
 * 
 * PaperdorkSettings
 * 
 *  
 * @author Roefja | www.roefja.com
 * @copyright 2021
 * 
 * 
 * 
 */

define("PaperdorkSettingsID", "paperdork-settings");

class PaperdorkSettings {

	protected $Paperdork = null;

	public function __construct($Paperdork) {
		$this->Paperdork = $Paperdork;
		register_setting(PaperdorkSettingsID, PaperdorkSettingsID, [$this, 'validate']);
	}

	public function adminMenu() {
		$capability = $this->capability();
		$this->screen_id = add_submenu_page('woocommerce', 'Paperdork', 'Paperdork', $capability, 'paperdork', array($this, 'showPage'));
	}

	public function capability() {
		$allowed = array('manage_woocommerce', 'manage_options');
		$capability = apply_filters('discount-message_required_capability', 'manage_woocommerce');
		if (!in_array($capability, $allowed)) $capability = 'manage_woocommerce';
		return $capability;
	}

	public function getSettings() {
		$settings = get_option(PaperdorkSettingsID);

		if (empty($settings) || !array_key_exists('payment_term', $settings) || $settings['payment_term'] == '') $settings['payment_term'] = 30;
		if (empty($settings) || !array_key_exists('ignore_zero_amount', $settings) || $settings['ignore_zero_amount'] == '') $settings['ignore_zero_amount'] = 1;
		$settings['paperdork_last_cron'] = get_option('paperdork_last_cron');
		return $settings;
	}

	public function adminInit() {

		$this->addSettingsSection('general_settings', 'Algemene instellingen');
		$this->addSettingsSection('connect_settings', 'Koppeling instellingen');
		$this->addSettingsSection('debug_mode', 'Debug modus');

		$settings = $this->getSettings();

		new PaperdorkSettingsField('auto_process_order', 'general_settings', $settings, 'Bestellingen automatisch verwerken in Paperdork', 'checkbox');
		new PaperdorkAutoInvoiceSettingsField($settings);
		new PaperdorkSettingsFieldSendMethod('send_method', 'general_settings', $settings, 'Factuur verzending', 'select', [
			'manual_send' => 'Maak de factuur aan zonder te versturen (handmatig verstuurd)',
			'send' => 'Verstuur de factuur per e-mail',
		]);

		//new PaperdorkSettingsField('auto_credit_invoice', 'general_settings',$settings, 'Maak automatisch creditfacturen voor terugbetalingen', 'checkbox');
		$templates = $this->Paperdork->callApi('/v1/InvoiceTemplates/list');
		new PaperdorkInvoiceTemplateSettingsField($templates, $settings);
		new PaperdorkSettingsField('payment_term', 'general_settings', $settings, 'Betaaltermijn (in dagen)', 'number');
		new PaperdorkSettingsField('vat_exempt', 'general_settings', $settings, 'Ik ben vrijgesteld van btw', 'checkbox');

		new PaperdorkSettingsField('send_woo_invoice_mail', 'general_settings', $settings, 'WooCommerce besteloverzicht versturen', 'checkbox');
		new PaperdorkSettingsField('house_number_address_2', 'general_settings', $settings, 'Adresregel 2 gebruiken als het huisnummer', 'checkbox');

		new PaperdorkConnectorSettingsField('client_id', 'connect_settings', $settings, 'Client ID');
		new PaperdorkConnectorSettingsField('client_secret', 'connect_settings', $settings, 'Client Secret');

		new PaperdorkSettingsField('ignore_zero_amount', 'general_settings', $settings, 'Negeer bestellingen van 0 euro voor mijn administratie', 'checkbox');

		new PaperdorkConnectorSettingsField('debug_mode', 'debug_mode', $settings, 'Activeer de debugmodus <small style="font-weight:normal;display:block;">Deze wordt na 5 dagen automatisch uitgeschakeld</small>', 'checkbox');
		new PaperdorkConnectorSettingsField('proxy_mode', 'debug_mode', $settings, 'Activeer de proxymodus <small style="font-weight:normal;display:block;">Deze hoef je alleen te activeren als je Cijferbaas dat heeft aangeraden.</small>', 'checkbox');
		if (isset($_GET['show_test']) || (isset($settings['test_api_mode']) && $settings['test_api_mode'])) new PaperdorkConnectorSettingsField('test_api_mode', 'debug_mode', $settings, 'Activeer de testapi', 'checkbox');
		if (isset($_GET['connected_since']) || (isset($settings['connected_since']) && $settings['connected_since'])) new PaperdorkConnectorSettingsField('connected_since', 'debug_mode', $settings, 'Connected since', 'text');
	}

	public function addSettingsSection($id, $title = '', $description = '') {
		add_settings_section($id, $title, '', $id);
	}

	public function addSettingsField($id, $section, $text = '') {
		add_settings_field($id, $text, [$this, $id . '_field'], $section, $section);
	}

	public function showPage() {

		if (isset($_GET['callback']) && $_GET['callback'] == 'yes') {
			return $this->Paperdork->runCallback();
		}

		$this->adminInit();

		if (isset($_GET['disconnect']) && $_GET['disconnect'] == 'disconnect-me') {
			$this->Paperdork->disConnect();
		}

?>
		<h1>Paperdork</h1>
		<div class="<?php echo esc_attr(PaperdorkSettingsID) ?>">
			<?php
			if (!$this->Paperdork->hasFirstConfig())  $this->showFirstConfigPage();
			else if (!$this->Paperdork->isConnected())  $this->showNotConnectedPage();
			else if ($this->Paperdork->needsReconnect())  $this->showReconnectPage();
			else $this->showConnectedPage();
			?>

			<form action="options.php" method="post">
				<?php
				settings_fields(PaperdorkSettingsID);
				do_settings_sections('debug_mode');
				?>
				<input name="submit" class="button button-primary" type="submit" value="<?php esc_attr_e('Save'); ?>" />
			</form>

		</div>
	<?php
	}

	public function showFirstConfigPage() {


	?>
		<form action="options.php" method="post">
			<?php
			settings_fields(PaperdorkSettingsID);
			echo 'Vul hier jouw persoonlijke Client ID en Client Secret, daarna kunnen we je automatisch koppelen.';
			do_settings_sections('connect_settings');
			?>


			<?php if (!$this->Paperdork->hasFirstConfig()) : ?>

				<?php if ($this->Paperdork->hasClientIdOrSecret()) : ?>

					<div class="paperdork-disclaimer notice notice-error">
						Het lijkt erop dat je nog geen geldige Client ID of Client Secret hebt. Als je een mailtje stuurt naar <a href="mailto:hello@paperdork.nl?subject=Client ID voor <?= get_site_url(); ?>">hello@paperdork.nl</a> met je website url, dan maken we er eentje voor je aan.
					</div>

				<?php else : ?>
					<div class="paperdork-disclaimer notice notice-warning">
						Als je nog geen Client ID en Client Secret hebt kan je die krijgen door een mailtje te sturen naar <a href="mailto:hello@paperdork.nl?subject=Client ID voor <?= get_site_url(); ?>">hello@paperdork.nl</a> met je website url, dan maken we er eentje voor je aan.
					</div>

				<?php endif; ?>

			<?php endif; ?>


			<br>
			<input name="submit" class="button button-primary" type="submit" value="<?php esc_attr_e('Save'); ?>" />
		</form>
		<br>
	<?php
	}

	public function showNotConnectedPage() {
		$this->showFirstConfigPage();
		if (!$this->Paperdork->hasFirstConfig()) return;
		$this->getDisclaimer();
	?>
		<a href="<?php echo esc_url($this->Paperdork->getAuthUrl()); ?>" class="button">Koppel je Paperdork Account</a>
	<?php
	}

	protected function getDisclaimer() {
	?>
		<div class="paperdork-disclaimer notice notice-warning">
			Deze koppeling is niet geschikt als jij digitale diensten (<a target="_blank" href="https://www.belastingdienst.nl/wps/wcm/connect/bldcontentnl/belastingdienst/zakelijk/btw/zakendoen_met_het_buitenland/goederen_en_diensten_naar_andere_eu_landen/btw_berekenen_bij_diensten/wijziging_in_digitale_diensten_vanaf_2015/#:~:text=Dit%20zijn%20diensten%20die%20via,niet%20zonder%20informatietechnologie%20worden%20geleverd">lees hier wat dat inhoudt</a> of neem contact met ons op) levert buiten de Europese Unie en/of als je de MOSS regeling of One Stop Shop (éénloketsysteem) toepast. Daarnaast ben je altijd zelf verantwoordelijk voor jouw administratie, ook als je deze gaat automatiseren. Verkeerde instellingen in WordPress, WooCommerce en/of de Paperdork plugin kunnen zorgen voor fouten in je administratie. Monitor dus regelmatig of het automatiseren helemaal goed gaat. En geef ons ook even een seintje, zodat wij ook met je mee kunnen kijken :)
		</div>
	<?php
	}

	public function showReconnectPage() {
	?>
		<div class="paperdork-disclaimer notice notice-error">
			<p>Oeps! De connectie met Paperdork is weggevallen, mogelijk door het gebruik van een andere plugin. Klik op de knop om opnieuw te koppelen. Je bestellingen worden dan (met terugwerkende kracht) in Paperdork verwerkt.</p>
			<a href="<?php echo esc_url($this->Paperdork->getAuthUrl()); ?>" class="button">Paperdork opnieuw koppelen</a>
		</div>
	<?php
	}

	public function showConnectedPage() {
	?>
		<a href="<?php echo esc_html($_SERVER["REQUEST_URI"]) ?>&disconnect=disconnect-me" class="button danger">Paperdork Loskopppelen</a>

		<form action="options.php" method="post">
			<?php
			settings_fields(PaperdorkSettingsID);
			?>
			<?php $this->getDisclaimer(); ?>

			<?php
			do_settings_sections('general_settings');
			?>
			<input name="submit" class="button button-primary" type="submit" value="<?php esc_attr_e('Save'); ?>" />
		</form>
		<?php
	}

	public function validate($input) {
		foreach (['client_id', 'client_secret', 'debug_mode', 'test_api_mode', 'proxy_mode', 'connected_since'] as $key) {
			if (isset($input[$key])) {
				update_option('paperdork_' . $key, $input[$key]);
				unset($input[$key]);
			}
		}

		if (empty($input)) return get_option('paperdork-settings');
		return $input;
	}
}

class PaperdorkSettingsField {

	protected $settings = [];

	public function __construct($id, $section, $settings = [], $text = '', $type = 'text', $options = []) {
		$this->id = $id;
		$this->section = $section;
		$this->text = $text;
		$this->type = $type;
		$this->settings = $settings;
		$this->options = $options;

		add_settings_field($id, $text, [$this, 'callback'], $section, $section);
	}

	public function getName() {
		return PaperdorkSettingsID . "[" . $this->id . "]";
	}

	public function getValue() {
		if (!array_key_exists($this->id, $this->settings)) return null;
		return $this->settings[$this->id];
	}

	public function callback() {
		if ($this->type == 'checkbox') {
		?>
			<input type="hidden" name="<?php echo esc_attr($this->getName()); ?>" value="0">
			<input type="checkbox" <?php echo ($this->getValue() ? 'checked' : '') ?> name="<?php echo esc_attr($this->getName()); ?>" value="1">
		<?php
		} else if ($this->type == 'select') {
		?>
			<select id="<?php echo esc_attr($this->id); ?>" name="<?php echo esc_attr($this->getName()); ?>">
				<?php
				$value = $this->getValue();
				foreach ($this->options as $option => $name) {
					echo '<option ' . ($value == $option ? "selected" : '') . ' value="' . esc_attr($option) . '">' . esc_html($name) . '</option>';
				}
				?>
			</select>
		<?php
		} else {
		?>
			<input type="<?php echo esc_attr($this->type); ?>" min="<?php echo esc_attr($this->type == 'number' ? '1' : ''); ?>" name=<?php echo esc_attr($this->getName()) ?> value="<?php echo esc_attr($this->getValue()); ?>">
		<?php
		}
	}
}

class PaperdorkSettingsFieldSendMethod extends PaperdorkSettingsField {
	public function callback() {
		parent::callback();
		?>
		<div class="warning" style="margin-top:10px;font-style:italic;max-width:400px;display:none">Let op: als de bestelling al is betaald wordt de factuur alsnog als definitieve factuur aangemaakt.</div>
	<?php
	}
}

class PaperdorkAutoInvoiceSettingsField extends PaperdorkSettingsField {
	public function __construct($settings = [], $id = 'auto_generate_invoice', $section = 'general_settings', $text = 'Genereer automatisch een factuur als de bestelling de volgende status krijgt') {
		parent::__construct($id, $section, $settings, $text);
	}
	public function callback() {
		//$status_options = ["pending" => "Pending payment", "processing" => "Processing", "on-hold" => "On hold", "completed" => "Completed"];
		$status_options = wc_get_order_statuses();
		foreach ($status_options as $status => $name) {
			$status = str_replace('wc-', '', $status);
			if (in_array($status, ['cancelled', 'refunded', 'failed'])) continue;
			$setting = [];
			if (array_key_exists($this->id, $this->settings) && array_key_exists($status, $this->settings[$this->id])) $setting = $this->settings[$this->id][$status];
			if (!array_key_exists('parent_enabled', $setting)) $setting['parent_enabled'] = false;
			if (!array_key_exists('all', $setting)) $setting['all'] = false;
			$this->get_status_block($status, $name, $setting);
		}
	}

	protected function getGatewayCheckbox($status, $id, $title, $settings = []) {
	?>
		<div class="payment_gateway"><input type="checkbox" <?php echo ($settings[$id] ? "checked" : "") ?> value=1 name="<?php echo esc_attr(PaperdorkSettingsID); ?>[<?php echo $this->id; ?>][<?php echo esc_attr($status); ?>][<?php echo esc_attr($id); ?>]"> <?php echo esc_html($title); ?></div>
	<?php
	}

	public function get_status_block($status, $title, $settings = []) {
		$gateways = WC()->payment_gateways->get_available_payment_gateways();

		$ids = [];

	?>
		<div class="status_option" id="status_<?php echo esc_attr($status); ?>">
			<input class="parent_option" type="checkbox" <?php echo ($settings["parent_enabled"] ? "checked" : "") ?> value=1 name="<?php echo esc_attr(PaperdorkSettingsID); ?>[<?php echo esc_attr($this->id); ?>][<?php echo esc_attr($status); ?>][parent_enabled]"> <?php echo esc_html_x($title, 'Order status', 'woocommerce'); ?>
			<div class="payment_options <?php echo ($settings["parent_enabled"] ? "show" : "") ?>">
				<div class="payment_gateway"><input type="checkbox" <?php echo ($settings['all'] ? "checked" : "") ?> value=1 name="<?php echo esc_attr(PaperdorkSettingsID); ?>[<?php echo esc_attr($this->id); ?>][<?php echo esc_attr($status); ?>][all]"> <?php echo esc_attr_e('Alle betaalmethodes'); ?></div>
				<?php
				foreach ($gateways as $id => $gateway) {
					if (!array_key_exists($id, $settings)) $settings[$id] = false;
					$ids[] = $id;
					$this->getGatewayCheckbox($status, $id, $gateway->title, $settings);
				}

				/*
								foreach(['mollie_wc_gateway_directdebit' => 'SEPA Incasso'] as $id => $title){
									if(in_array($id, $ids)) continue;
									if(!array_key_exists($id, $settings)) $settings[$id] = false;
										$ids [] = $id;
										$this->getGatewayCheckbox($status, $id, $title, $settings);
								}
								*/
				?>
			</div>
		</div>
	<?php
	}
}


class PaperdorkInvoiceTemplateSettingsField extends PaperdorkSettingsField {
	public function __construct($templates, $settings = [], $id = 'invoice_template', $section = 'general_settings', $text = 'Factuur template die je wilt gebruiken voor je facturen') {
		parent::__construct($id, $section, $settings, $text);
		$this->options = $templates;
	}
	public function callback() {

	?>
		<select name="<?php echo esc_attr($this->getName()); ?>">
			<?php
			$value = $this->getValue();
			foreach ($this->options as $option) {
				echo '<option ' . ($value == $option['id'] ? "selected" : '') . ' value="' . esc_attr($option['id']) . '">' . esc_html($option['name']) . '</option>';
			}
			?>
		</select>
<?php
	}
}

class PaperdorkConnectorSettingsField extends PaperdorkSettingsField {
	public function __construct($id, $section, $settings = [], $text = '', $type = 'text', $options = []) {
		if ($id == 'client_secret') $type = 'password';
		parent::__construct($id, $section, $settings, $text, $type, $options);
	}

	public function getValue() {
		return get_option('paperdork_' . $this->id);
	}
}

?>