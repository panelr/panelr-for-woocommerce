<?php
defined('ABSPATH') || exit;

/**
 * "Panelr credits" — a payment method that only appears when every cart
 * line is credit-paid and the member's balance covers the sum. It calls
 * complete_order with pay_with_points and no money method.
 */
class Panelr_Credits_Gateway
{
	const ID = 'panelr_credits';

	public static function init(): void
	{
		add_filter('woocommerce_payment_gateways', [__CLASS__, 'register']);
	}

	public static function register(array $gateways): array
	{
		if (!class_exists('WC_Payment_Gateway')) return $gateways;
		require_once PANELR_PLUGIN_DIR . 'includes/class-wc-gateway-panelr-credits.php';
		if (class_exists('Panelr_WC_Gateway_Credits')) {
			$gateways[] = 'Panelr_WC_Gateway_Credits';
		}
		return $gateways;
	}

	public static function available(): bool
	{
		if (is_admin() && !defined('DOING_AJAX')) return true;
		if (!function_exists('WC') || !WC()->cart) return false;
		if (!Panelr_Session::is_signed_in()) return false;
		if (!Panelr_Cart::all_lines_credit_paid()) return false;
		$needed = Panelr_Cart::credits_in_cart();
		return $needed > 0 && Panelr_Session::credits_balance() >= $needed;
	}

	public static function process(int $order_id): array
	{
		$order = wc_get_order($order_id);
		if (!$order) {
			return ['result' => 'failure'];
		}
		if (!self::complete_with_credits($order)) {
			wc_add_notice((string) ($order->get_meta('_panelr_hold_reason') ?: __('Your credits could not be used for this order.', 'panelr-for-woocommerce')), 'error');
			return ['result' => 'failure'];
		}
		WC()->cart->empty_cart();
		return ['result' => 'success', 'redirect' => $order->get_checkout_order_received_url()];
	}

	public static function complete_with_credits(WC_Order $order): bool
	{
		if ($order->get_meta('_panelr_work_order_id')) return true;

		$items = Panelr_Cart::order_items($order);
		if (!$items) return false;
		foreach ($items as &$item) {
			$item['pay_with_points'] = true;
		}
		unset($item);

		$body = [
			'customer_email'         => $order->get_billing_email() ?: Panelr_Session::email(),
			'customer_name'          => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()) ?: Panelr_Session::name(),
			'customer_phone'         => $order->get_billing_phone(),
			'wc_order_id'            => (string) $order->get_id(),
			'source'                 => 'woocommerce',
			'pay_with_points'        => true,
			'payment_transaction_id' => 'WC-' . $order->get_id(),
			'items'                  => $items,
		];
		$ref = (string) $order->get_meta('_panelr_referral_code');
		if ($ref) $body['referral_code'] = $ref;

		$result = Panelr_API::instance()->complete_order($body);
		if (!$result['ok']) {
			$order->update_meta_data('_panelr_state', 'refused');
			$order->update_meta_data('_panelr_hold_reason', $result['error']);
			$order->add_order_note(sprintf(
				/* translators: %s: Panelr's message */
				__('Panelr refused the credits order: %s', 'panelr-for-woocommerce'),
				$result['error']
			));
			$order->save();
			return false;
		}

		$order->update_meta_data('_panelr_is_manual', '0');
		$order->update_meta_data('_panelr_mode', 'credits');
		Panelr_Checkout::record_completed_send($order, $result['data']);
		$order->payment_complete('WC-' . $order->get_id());
		$order->add_order_note(sprintf(
			/* translators: %d: credits spent */
			_n('Paid with %d credit.', 'Paid with %d credits.', (int) ($result['data']['credits_spent'] ?? 0), 'panelr-for-woocommerce'),
			(int) ($result['data']['credits_spent'] ?? 0)
		));
		$order->save();

		// The balance just changed.
		Panelr_Session::snapshot(true);
		return true;
	}
}
