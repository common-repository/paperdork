<?php

/**
 * 
 * PaperdorkInvoiceMetabox
 *  
 */


require_once("PaperdorkMetaBox.php");


class PaperdorkInvoiceMetabox extends PaperdorkMetaBox {
	protected $Paperdork = null;

	public function __construct($Paperdork) {
		$this->Paperdork = $Paperdork;
		if (!$Paperdork->isConnected()) return;
		parent::__construct('invoice_metabox', 'Factuur', 'shop_order', 'side', 'high');
	}


	public function render_metabox($post) {
		parent::render_metabox($post);

		$meta = get_post_meta($post->ID, '');
		$order = wc_get_order($post->ID);

		$allowed = ['a' => ['href' => [], 'class' => []]];

		if (!array_key_exists('paperdork_invoice', $meta) || $meta['paperdork_invoice'] == '') {
			echo wp_kses($this->getCreateInvoiceButton(), $allowed);
		} else {
			echo esc_html('Factuur is al aangemaakt');
		}

		/*
		else if(empty($order->get_refunds())) {
			echo wp_kses($this->getCreateCreditButton(), $allowed);
		}
		

		else{
			echo esc_html('Factuur is al (gedeeltelijk) terugbetaald');
		}

		*/
	}

	public function getCreateInvoiceButton() {
		global $post;
		return $this->getButton($this->getAjaxUrl() . '?action=create_paperdork&type=invoice&order=' . esc_attr($post->ID), 'Factuur aanmaken');
	}

	public function getCreateCreditButton() {
		global $post;
		return $this->getButton($this->getAjaxUrl() . '?action=create_paperdork&type=credit&order=' . esc_attr($post->ID), 'Factuur crediteren');
	}

	public function getButton($url = '', $title = 'Button') {
		return '<a class="button" href="' . esc_url(wp_nonce_url($url)) . '">' . esc_html($title) . '</a>';
	}

	public function getAjaxUrl() {
		return admin_url('admin-ajax.php');
	}
}
