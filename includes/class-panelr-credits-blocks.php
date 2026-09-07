<?php
defined('ABSPATH') || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * The credits method, told to the block checkout.
 *
 * WooCommerce's block checkout does not read classic gateways: a gateway that
 * has not registered itself here simply never appears, and a cart that can only
 * be paid with credits ends up with no payment option at all. This registers
 * the method and points at the small script that draws it.
 *
 * The script is plain JavaScript against the globals WooCommerce already ships
 * (wc.wcBlocksRegistry, wp.element) — no build step, nothing to compile before
 * the plugin can be released.
 */
final class Panelr_Credits_Blocks extends AbstractPaymentMethodType
{
	protected $name = 'panelr_credits';

	public function initialize()
	{
		$this->settings = [];
	}

	/** Whether the method may be offered at all. */
	public function is_active()
	{
		return Panelr_Credits_Gateway::available();
	}

	public function get_payment_method_script_handles()
	{
		$handle = 'panelr-blocks-credits';

		wp_register_script(
			$handle,
			PANELR_PLUGIN_URL . 'assets/js/blocks-credits.js',
			['wc-blocks-registry', 'wp-element', 'wp-html-entities', 'wp-i18n'],
			PANELR_VERSION,
			true
		);

		if (function_exists('wp_set_script_translations')) {
			wp_set_script_translations($handle, 'panelr-for-woocommerce');
		}

		return [$handle];
	}

	/**
	 * What the script needs to draw the option. The credit figures are read
	 * fresh on every checkout render, so a balance that changed mid-session is
	 * never shown stale.
	 */
	public function get_payment_method_data()
	{
		$needed  = class_exists('Panelr_Cart') ? (int) Panelr_Cart::credits_in_cart() : 0;
		$balance = class_exists('Panelr_Session') ? (int) Panelr_Session::credits_balance() : 0;

		return [
			'title'       => __('Pay with credits', 'panelr-for-woocommerce'),
			'description' => $needed > 0
				? sprintf(
					/* translators: 1: credits this order costs, 2: the member's balance */
					_n(
						'This order costs %1$d credit. You have %2$d.',
						'This order costs %1$d credits. You have %2$d.',
						$needed,
						'panelr-for-woocommerce'
					),
					$needed,
					$balance
				)
				: __('Pay with the credits on your account.', 'panelr-for-woocommerce'),
			'credits_needed'  => $needed,
			'credits_balance' => $balance,
			'supports'        => ['products'],
		];
	}
}
