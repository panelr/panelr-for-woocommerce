<?php
defined('ABSPATH') || exit;

/**
 * Panelr's checkout hands an order to this store to be paid.
 *
 * The customer chose their plans and a payment method on Panelr; Panelr
 * created the order (coupon, credits, fee and invite code already settled)
 * and sent them here with ?panelr_ref and ?panelr_token. This page rebuilds
 * the WooCommerce cart from that order — same product ids, Panelr's prices —
 * fills in their details, locks the gateway to the method they chose, and
 * sends them to checkout. No sign-in: the token is the door to one order.
 *
 * When payment completes, Panelr_Checkout completes the existing order with
 * the token instead of creating a new one.
 */
class Panelr_Handoff
{
	const SESSION_KEY = 'panelr_handoff';

	public static function init(): void
	{
		add_shortcode('panelr_checkout', [__CLASS__, 'render']);
		add_action('template_redirect', [__CLASS__, 'receive'], 5);
		add_action('woocommerce_before_calculate_totals', [__CLASS__, 'apply_prices'], 25);
		add_action('woocommerce_cart_emptied', [__CLASS__, 'forget']);
		add_action('woocommerce_checkout_update_order_review', [__CLASS__, 'keep_customer_details']);
	}

	// ── State ─────────────────────────────────────────────────────────────

	/** @return array|null {ref, token, method_id, order_total, coupon, adjustment, email, name, phone, currency} */
	public static function current(): ?array
	{
		$h = Panelr_Session::get(self::SESSION_KEY);
		if (!is_array($h) || empty($h['ref']) || empty($h['token'])) return null;
		// Only while the cart still carries that order's lines.
		if (WC()->cart && !WC()->cart->is_empty()) {
			foreach (WC()->cart->get_cart() as $item) {
				if (($item['_panelr_handoff_ref'] ?? '') === $h['ref']) return $h;
			}
		}
		return null;
	}

	public static function forget(): void
	{
		Panelr_Session::forget(self::SESSION_KEY);
	}

	// ── Receiver ──────────────────────────────────────────────────────────

	/** ?panelr_ref&panelr_token on the checkout page → cart → WooCommerce checkout. */
	public static function receive(): void
	{
		if (is_admin() || !is_page()) return;
		$page_id = (int) get_option('panelr_page_checkout', 0);
		if (!$page_id || get_queried_object_id() !== $page_id) return;

		// The link Panelr's checkout sends the customer with: the token is the credential.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$ref   = sanitize_text_field(wp_unslash($_GET['panelr_ref'] ?? ''));
		$token = sanitize_text_field(wp_unslash($_GET['panelr_token'] ?? ''));
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ($ref === '' || $token === '') return;

		if (!Panelr_Helpers::rate_limit('handoff', 30, 15 * MINUTE_IN_SECONDS)) {
			self::fail(__('Too many tries. Please wait a few minutes.', 'panelr-for-woocommerce'));
			return;
		}

		$result = Panelr_API::instance()->get_work_order($ref, $token, null);
		if (!$result['ok']) {
			self::fail($result['status'] === 403 || $result['status'] === 404
				? __('We could not find that order. Please go back and try again.', 'panelr-for-woocommerce')
				: $result['error']);
			return;
		}
		$o = $result['data'];

		// Already paid or closed on Panelr's side. An order sent here reads as
		// "awaiting_webhook" (Panelr is waiting for this store) until it is paid.
		$status  = (string) ($o['status'] ?? '');
		$unpaid  = in_array($status, ['pending_payment', 'awaiting_webhook'], true) && (float) ($o['prev_paid'] ?? 0) <= 0;
		if (!$unpaid) {
			$existing = Panelr_Orders::order_by_reference($ref);
			if ($existing && (string) $existing->get_meta('_panelr_confirmation_token') === $token) {
				wp_safe_redirect($existing->get_checkout_order_received_url());
				exit;
			}
			$status_url = Panelr_Helpers::page_url('order_status');
			if ($status_url) {
				wp_safe_redirect(add_query_arg(['panelr_ref' => $ref, 'panelr_token' => $token], $status_url));
				exit;
			}
			self::fail(__('This order is not waiting for payment.', 'panelr-for-woocommerce'));
			return;
		}

		// The same order handed over twice while unpaid: reuse the WooCommerce order.
		$existing = Panelr_Orders::order_by_reference($ref);
		if ($existing && $existing->needs_payment() && $existing->get_meta('_panelr_confirmation_token') === $token) {
			wp_safe_redirect($existing->get_checkout_payment_url());
			exit;
		}

		$error = self::build_cart($o, $ref, $token);
		if ($error) {
			self::fail($error);
			return;
		}
		wp_safe_redirect(wc_get_checkout_url());
		exit;
	}

	private static function fail(string $message): void
	{
		Panelr_Session::set('panelr_handoff_error', $message);
	}

	/** Rebuild the cart from a get_work_order answer. Returns an error message or ''. */
	private static function build_cart(array $o, string $ref, string $token): string
	{
		if (WC()->session && !WC()->session->has_session()) {
			WC()->session->set_customer_session_cookie(true);
		}
		$cart = (array) ($o['cart'] ?? []);
		if (!$cart) return __('That order has nothing in it.', 'panelr-for-woocommerce');

		$total = (float) ($o['order_total'] ?? 0);
		if (!empty($o['has_partials'])) $total = (float) ($o['balance_due'] ?? $total);

		WC()->cart->empty_cart();
		self::forget();

		$added = 0;
		foreach ($cart as $item) {
			$panelr_id = (int) ($item['product_id'] ?? 0);
			$wc_id     = Panelr_Sync::ensure_product($panelr_id);
			if (!$wc_id) {
				WC()->cart->empty_cart();
				return __('One of the plans on that order is not available on this store. Please contact us.', 'panelr-for-woocommerce');
			}
			$qty  = max(1, (int) ($item['qty'] ?? 1));
			$data = [
				'_panelr_intent'      => (string) ($item['intent'] ?? 'new_activation'),
				'_panelr_plugin_id'   => (int) ($item['plugin_id'] ?? 0),
				'_panelr_handoff_ref' => $ref,
				// Panelr's price for this line — what the customer saw there.
				'_panelr_price'       => !empty($item['points_paid']) ? 0.0 : (float) ($item['price'] ?? 0),
			];
			if (!empty($item['activation_id']))   $data['_panelr_activation_id'] = (int) $item['activation_id'];
			if (!empty($item['linked_username'])) $data['_panelr_line_label']    = (string) $item['linked_username'];
			if (!empty($item['points_paid']))     $data['_panelr_credits_paid']  = (int) $item['points_paid'];

			$key = WC()->cart->add_to_cart($wc_id, $qty, 0, [], $data);
			if ($key) $added++;
		}
		if (!$added) {
			return __('That order could not be loaded into the cart. Please contact us.', 'panelr-for-woocommerce');
		}

		$pm = $o['payment_method'] ?? null;
		Panelr_Session::set(self::SESSION_KEY, [
			'ref'         => $ref,
			'token'       => $token,
			'method_id'   => (int) ($pm['id'] ?? 0),
			'method_name' => (string) ($pm['name'] ?? ''),
			'manual'      => $pm && empty($pm['is_automated']) && (($pm['mode'] ?? '') === 'manual'),
			'order_total' => $total,
			'coupon'      => $o['coupon'] ?? null,
			'adjustment'  => $o['adjustment'] ?? null,
			'email'       => (string) ($o['customer_email'] ?? ''),
			'name'        => (string) ($o['customer_name'] ?? ''),
			'currency'    => (string) ($o['currency'] ?? ''),
			'at'          => time(),
		]);

		// Their details, so checkout is a matter of paying.
		if (WC()->customer) {
			$email = (string) ($o['customer_email'] ?? '');
			$name  = trim((string) ($o['customer_name'] ?? ''));
			if ($email) WC()->customer->set_billing_email($email);
			if ($name) {
				$parts = preg_split('/\s+/', $name, 2);
				WC()->customer->set_billing_first_name($parts[0]);
				WC()->customer->set_billing_last_name($parts[1] ?? '');
			}
			WC()->customer->save();
		}
		return '';
	}

	/** Block/classic checkout may re-save the customer; keep the handed-off email in place. */
	public static function keep_customer_details($post_data): void
	{
		$h = self::current();
		if (!$h || !WC()->customer || !$h['email']) return;
		if (!WC()->customer->get_billing_email()) {
			WC()->customer->set_billing_email($h['email']);
		}
	}

	// ── Prices and fees ───────────────────────────────────────────────────

	/** Each handed-off line costs what Panelr said it costs. */
	public static function apply_prices(WC_Cart $cart): void
	{
		if (is_admin() && !defined('DOING_AJAX')) return;
		foreach ($cart->get_cart() as $item) {
			if (isset($item['_panelr_price']) && $item['data'] instanceof WC_Product) {
				$item['data']->set_price((float) $item['_panelr_price']);
			}
		}
	}

	/** The order's own coupon and fee, as WooCommerce fees, so the total equals Panelr's. */
	public static function add_fees(WC_Cart $cart, array $h): void
	{
		$coupon = $h['coupon'] ?? null;
		if ($coupon && (float) ($coupon['discount'] ?? 0) > 0) {
			$cart->add_fee(sprintf(
				/* translators: %s: coupon code */
				__('Coupon %s', 'panelr-for-woocommerce'),
				strtoupper((string) $coupon['code'])
			), -(float) $coupon['discount'], false);
		}
		$adj = $h['adjustment'] ?? null;
		if ($adj && (float) ($adj['amount'] ?? 0) > 0) {
			$cart->add_fee(
				(string) ($adj['label'] ?: ucfirst((string) $adj['direction'])),
				$adj['direction'] === 'fee' ? (float) $adj['amount'] : -(float) $adj['amount'],
				false
			);
		}
	}

	/** Gateways mapped to the method the customer chose on Panelr. Empty = no restriction. */
	public static function allowed_gateways(array $h): array
	{
		$method_id = (int) ($h['method_id'] ?? 0);
		if (!$method_id) return [];
		$out = [];
		foreach (Panelr_Helpers::payment_map() as $gateway => $mapped) {
			if ((int) $mapped === $method_id) $out[] = (string) $gateway;
		}
		return $out;
	}

	// ── Shortcode ─────────────────────────────────────────────────────────

	/** Shown only when the receiver did not redirect: an error, or a bare visit. */
	public static function render($atts = []): string
	{
		wp_enqueue_script('panelr-common');
		$error = Panelr_Session::get('panelr_handoff_error');
		Panelr_Session::forget('panelr_handoff_error');
		return Panelr_Template::render('checkout-landing', [
			'error'      => is_string($error) ? $error : '',
			'panelr_url' => Panelr_API::instance()->base_url(),
			'shop_url'   => wc_get_page_permalink('shop'),
		]);
	}
}
