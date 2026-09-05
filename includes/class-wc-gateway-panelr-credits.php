<?php
defined('ABSPATH') || exit;

if (!class_exists('WC_Payment_Gateway')) {
	return;
}

/**
 * The credits payment method. All the logic lives in Panelr_Credits_Gateway;
 * this class only gives WooCommerce something to list.
 */
class Panelr_WC_Gateway_Credits extends WC_Payment_Gateway
{
	public function __construct()
	{
		$this->id                 = Panelr_Credits_Gateway::ID;
		$this->has_fields         = false;
		$this->method_title       = __('Panelr credits', 'panelr-for-woocommerce');
		$this->method_description = __('Members pay with the credits they earned by inviting others. Shown only when the whole order can be paid with credits.', 'panelr-for-woocommerce');
		$this->title              = __('Pay with credits', 'panelr-for-woocommerce');
		$this->enabled            = 'yes';
		$this->supports           = ['products'];
		$this->init_form_fields();
		$this->init_settings();
	}

	public function init_form_fields()
	{
		$this->form_fields = [];
	}

	public function is_available()
	{
		return Panelr_Credits_Gateway::available();
	}

	public function process_payment($order_id)
	{
		return Panelr_Credits_Gateway::process((int) $order_id);
	}
}
