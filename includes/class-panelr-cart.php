<?php
defined('ABSPATH') || exit;

/**
 * Cart lines that mean something to Panelr: their intent, the line they
 * renew, the service they are on, whether credits pay for them. Also the
 * Panelr coupon, the invite code on the cart, and the payment method's fee
 * or discount as WooCommerce fees — so what WooCommerce charges is what
 * Panelr expects.
 */
class Panelr_Cart
{
	const COUPON_KEY = 'panelr_coupon';

	public static function init(): void
	{
		add_filter('woocommerce_add_cart_item_data',                [__CLASS__, 'add_cart_item_data'], 10, 3);
		add_filter('woocommerce_get_item_data',                     [__CLASS__, 'display_cart_item_data'], 10, 2);
		add_action('woocommerce_checkout_create_order_line_item',   [__CLASS__, 'save_order_item_meta'], 10, 4);
		add_filter('woocommerce_order_item_get_formatted_meta_data', [__CLASS__, 'hide_panelr_meta_from_display'], 10, 2);
		add_filter('woocommerce_order_item_name',                   [__CLASS__, 'append_panelr_order_context'], 10, 2);
		add_action('woocommerce_check_cart_items',                  [__CLASS__, 'validate_cart']);
		add_action('woocommerce_before_calculate_totals',           [__CLASS__, 'zero_credit_lines'], 20);
		add_action('woocommerce_cart_calculate_fees',               [__CLASS__, 'add_fees'], 20);
		add_action('woocommerce_cart_totals_before_order_total',    [__CLASS__, 'render_cart_extras']);
		add_action('woocommerce_review_order_before_order_total',   [__CLASS__, 'render_cart_extras']);
		add_filter('woocommerce_coupons_enabled',                   [__CLASS__, 'maybe_disable_wc_coupons']);
		add_action('wp_ajax_panelr_apply_coupon',                   [__CLASS__, 'ajax_apply_coupon']);
		add_action('wp_ajax_nopriv_panelr_apply_coupon',            [__CLASS__, 'ajax_apply_coupon']);
		add_action('wp_ajax_panelr_remove_coupon',                  [__CLASS__, 'ajax_remove_coupon']);
		add_action('wp_ajax_nopriv_panelr_remove_coupon',           [__CLASS__, 'ajax_remove_coupon']);
		add_action('woocommerce_single_product_summary',            [__CLASS__, 'credits_button'], 35);
		add_action('wp_ajax_panelr_add_with_credits',               [__CLASS__, 'ajax_add_with_credits']);
		add_action('wp_ajax_nopriv_panelr_add_with_credits',        [__CLASS__, 'ajax_add_with_credits']);
		add_action('woocommerce_cart_loaded_from_session',          [__CLASS__, 'attach_plugin_ids']);
		add_action('wp_enqueue_scripts',                            [__CLASS__, 'enqueue']);
	}

	public static function enqueue(): void
	{
		if (is_cart() || is_checkout() || is_product()) {
			wp_enqueue_script('panelr-common');
			wp_enqueue_script('panelr-cart', PANELR_PLUGIN_URL . 'assets/js/cart.js', ['jquery', 'panelr-common'], PANELR_VERSION, true);
			wp_localize_script('panelr-cart', 'panelrCart', [
				'ajaxurl'      => admin_url('admin-ajax.php'),
				'nonce'        => wp_create_nonce('panelr_cart'),
				'cart_url'     => wc_get_cart_url(),
				'checkout_url' => wc_get_checkout_url(),
				'i18n'         => [
					'enter_code' => __('Enter a code first.', 'panelr-for-woocommerce'),
					'applying'   => __('Checking…', 'panelr-for-woocommerce'),
					'apply'      => __('Apply', 'panelr-for-woocommerce'),
				],
			]);
		}
	}

	// ── Cart item data ────────────────────────────────────────────────────

	/** Keep the Panelr keys that arrive with add_to_cart, and stamp the service on every synced product. */
	public static function add_cart_item_data(array $cart_item_data, int $product_id, int $variation_id = 0): array
	{
		$panelr_id = Panelr_Helpers::panelr_product_id($product_id);
		if ($panelr_id) {
			$product = wc_get_product($product_id);
			if (empty($cart_item_data['_panelr_intent'])) {
				$cart_item_data['_panelr_intent'] = 'new_activation';
			}
			if (empty($cart_item_data['_panelr_plugin_id']) && $product) {
				$cart_item_data['_panelr_plugin_id'] = (int) $product->get_meta('_panelr_plugin_id');
			}
		}
		return $cart_item_data;
	}

	/** Old carts (v1) carry no service id; fill it from the product so the rules can run. */
	public static function attach_plugin_ids(WC_Cart $cart): void
	{
		foreach ($cart->get_cart() as $key => $item) {
			if (isset($item['_panelr_intent']) && empty($item['_panelr_plugin_id'])) {
				$product = $item['data'] ?? null;
				if ($product instanceof WC_Product) {
					$cart->cart_contents[$key]['_panelr_plugin_id'] = (int) $product->get_meta('_panelr_plugin_id');
				}
			}
		}
	}

	public static function display_cart_item_data(array $item_data, array $cart_item): array
	{
		$intent = $cart_item['_panelr_intent'] ?? '';
		if (!$intent) return $item_data;

		$plugin_id = (int) ($cart_item['_panelr_plugin_id'] ?? 0);
		if ($plugin_id && Panelr_Helpers::multi_service()) {
			$item_data[] = ['key' => __('Service', 'panelr-for-woocommerce'), 'value' => Panelr_Helpers::service_name($plugin_id)];
		}

		if (in_array($intent, ['renewal', 'trial_upgrade'], true)) {
			$item_data[] = ['key' => __('Order type', 'panelr-for-woocommerce'), 'value' => Panelr_Helpers::intent_label($intent)];
			$label = self::line_label_for_cart_item($cart_item);
			if ($label) {
				$item_data[] = ['key' => __('Connection', 'panelr-for-woocommerce'), 'value' => esc_html($label)];
			}
		}

		if (!empty($cart_item['_panelr_credits_paid'])) {
			$paid = (int) $cart_item['_panelr_credits_paid'];
			$item_data[] = ['key' => __('Payment', 'panelr-for-woocommerce'), 'value' => sprintf(
				/* translators: %d: number of credits */
				_n('Paid with %d credit', 'Paid with %d credits', $paid, 'panelr-for-woocommerce'),
				$paid
			)];
		}

		if (!empty($cart_item['_panelr_pay_with_points'])) {
			$cost = (int) ($cart_item['data']->get_meta('_panelr_referral_cost_points') ?? 0);
			$item_data[] = ['key' => __('Payment', 'panelr-for-woocommerce'), 'value' => sprintf(
				/* translators: %d: number of credits */
				_n('Paid with %d credit', 'Paid with %d credits', $cost, 'panelr-for-woocommerce'),
				$cost
			)];
		}

		return $item_data;
	}

	/** "Living Room" (the member's own label) when known, else the username. */
	private static function line_label_for_cart_item(array $cart_item): string
	{
		if (!empty($cart_item['_panelr_line_label'])) return (string) $cart_item['_panelr_line_label'];
		$activation_id = (int) ($cart_item['_panelr_activation_id'] ?? 0);
		if ($activation_id) {
			$line = Panelr_Session::line($activation_id);
			if ($line) return (string) ($line['label'] ?: $line['username']);
			$ls = Panelr_Session::line_session();
			if ($ls && (int) $ls['activation_id'] === $activation_id) return (string) $ls['username'];
		}
		return (string) ($cart_item['_panelr_username'] ?? '');
	}

	public static function save_order_item_meta($item, string $cart_item_key, array $cart_item, WC_Order $order): void
	{
		if (!empty($cart_item['_panelr_intent'])) {
			$item->update_meta_data('_panelr_intent', $cart_item['_panelr_intent']);
		}
		if (!empty($cart_item['_panelr_activation_id'])) {
			$item->update_meta_data('_panelr_activation_id', (int) $cart_item['_panelr_activation_id']);
		}
		if (!empty($cart_item['_panelr_plugin_id'])) {
			$item->update_meta_data('_panelr_plugin_id', (int) $cart_item['_panelr_plugin_id']);
		}
		if (!empty($cart_item['_panelr_pay_with_points'])) {
			$item->update_meta_data('_panelr_pay_with_points', '1');
			$item->update_meta_data('_panelr_credits', (int) ($cart_item['data']->get_meta('_panelr_referral_cost_points') ?? 0));
		}
		$label = self::line_label_for_cart_item($cart_item);
		if ($label) {
			$item->update_meta_data('_panelr_line_label', $label);
		}
		if (!empty($cart_item['_panelr_reference_code'])) {
			// Balance payment for a Panelr order (v1 flow, kept).
			$item->update_meta_data('_panelr_intent',          'balance_payment');
			$item->update_meta_data('_panelr_reference_code',  $cart_item['_panelr_reference_code']);
			$order->update_meta_data('_panelr_reference_code', $cart_item['_panelr_reference_code']);
			$order->update_meta_data('_panelr_balance_payment', '1');
			if (!empty($cart_item['_panelr_balance_amount'])) {
				$item->set_subtotal($cart_item['_panelr_balance_amount']);
				$item->set_total($cart_item['_panelr_balance_amount']);
			}
		}
	}

	// ── Order item display (old orders keep rendering) ────────────────────

	public static function hide_panelr_meta_from_display(array $formatted_meta, $order_item): array
	{
		$intent = null;
		$label  = null;
		$credits = 0;
		$plugin_id = 0;

		foreach ($formatted_meta as $key => $meta) {
			if (strpos((string) $meta->key, '_panelr_') === 0) {
				if ($meta->key === '_panelr_intent')      $intent    = $meta->value;
				if ($meta->key === '_panelr_line_label')  $label     = $meta->value;
				if ($meta->key === '_panelr_credits')     $credits   = (int) $meta->value;
				if ($meta->key === '_panelr_plugin_id')   $plugin_id = (int) $meta->value;
				unset($formatted_meta[$key]);
			}
		}

		if ($intent && $intent !== 'new_activation') {
			$text = Panelr_Helpers::intent_label((string) $intent);
			$formatted_meta[] = (object) [
				'key'           => 'panelr_order_type',
				'value'         => $text,
				'display_key'   => __('Order type', 'panelr-for-woocommerce'),
				'display_value' => esc_html($text),
			];
			if (!$label) {
				$order = $order_item->get_order();
				$label = $order ? ($order->get_meta('_panelr_editor_username') ?: $order->get_meta('_panelr_xtream_username')) : '';
			}
			if ($label) {
				$formatted_meta[] = (object) [
					'key'           => 'panelr_account',
					'value'         => $label,
					'display_key'   => __('Connection', 'panelr-for-woocommerce'),
					'display_value' => esc_html($label),
				];
			}
		}
		if ($plugin_id && Panelr_Helpers::multi_service()) {
			$name = Panelr_Helpers::service_name($plugin_id);
			if ($name) {
				$formatted_meta[] = (object) [
					'key'           => 'panelr_service',
					'value'         => $name,
					'display_key'   => __('Service', 'panelr-for-woocommerce'),
					'display_value' => esc_html($name),
				];
			}
		}
		if ($credits) {
			$text = sprintf(
				/* translators: %d: number of credits */
				_n('Paid with %d credit', 'Paid with %d credits', $credits, 'panelr-for-woocommerce'),
				$credits
			);
			$formatted_meta[] = (object) [
				'key'           => 'panelr_credits',
				'value'         => $text,
				'display_key'   => __('Payment', 'panelr-for-woocommerce'),
				'display_value' => esc_html($text),
			];
		}

		return $formatted_meta;
	}

	public static function append_panelr_order_context(string $name, $item): string
	{
		if (!is_a($item, 'WC_Order_Item_Product')) return $name;
		$intent = $item->get_meta('_panelr_intent');
		if (!$intent || $intent === 'new_activation') return $name;

		$label = $item->get_meta('_panelr_line_label');
		if (!$label) {
			$order = $item->get_order();
			$label = $order ? ($order->get_meta('_panelr_editor_username') ?: $order->get_meta('_panelr_xtream_username')) : '';
		}

		$context  = '<br><small class="panelr-order-context">';
		$context .= esc_html__('Order type', 'panelr-for-woocommerce') . ': <strong>' . esc_html(Panelr_Helpers::intent_label((string) $intent)) . '</strong>';
		if ($label) {
			$context .= ' &mdash; ' . esc_html__('Connection', 'panelr-for-woocommerce') . ': <strong>' . esc_html($label) . '</strong>';
		}
		$context .= '</small>';
		return $name . $context;
	}

	// ── Rules Panelr enforces, checked here first ─────────────────────────

	/** Renewals and trial upgrades: same service, enough connections, a line the visitor owns. */
	public static function validate_cart(): void
	{
		if (!WC()->cart) return;
		foreach (WC()->cart->get_cart() as $key => $item) {
			$intent = $item['_panelr_intent'] ?? '';
			if (!in_array($intent, ['renewal', 'trial_upgrade'], true)) continue;
			// Lines handed over by Panelr's own checkout were validated there.
			if (!empty($item['_panelr_handoff_ref'])) continue;

			$activation_id = (int) ($item['_panelr_activation_id'] ?? 0);
			$product       = $item['data'] ?? null;
			$plugin_id     = (int) ($item['_panelr_plugin_id'] ?? 0);
			$line          = $activation_id ? Panelr_Session::line($activation_id) : null;
			$line_session  = Panelr_Session::line_session();
			$upgrade       = Panelr_Session::get('panelr_upgrade');

			$owned = $line
				|| ($line_session && (int) $line_session['activation_id'] === $activation_id)
				|| (is_array($upgrade) && (int) ($upgrade['activation_id'] ?? 0) === $activation_id);

			if (!$activation_id || !$owned) {
				WC()->cart->remove_cart_item($key);
				wc_add_notice(__('Sign in to renew a connection, then add it to your cart again.', 'panelr-for-woocommerce'), 'error');
				continue;
			}

			$line_plugin = (int) ($line['plugin_id'] ?? ($line_session['plugin_id'] ?? ($upgrade['plugin_id'] ?? 0)));
			if ($line_plugin && $plugin_id && $line_plugin !== $plugin_id) {
				WC()->cart->remove_cart_item($key);
				wc_add_notice(sprintf(
					/* translators: %s: service name */
					__('That plan is on a different service. Choose a plan on %s to renew this connection.', 'panelr-for-woocommerce'),
					Panelr_Helpers::service_name($line_plugin)
				), 'error');
				continue;
			}

			$is_trial = $line ? !empty($line['is_trial']) : ($line_session ? !empty($line_session['is_trial']) : $intent === 'trial_upgrade');
			$line_conn = (int) ($line['connections'] ?? ($line_session['connections'] ?? 0));
			$plan_conn = $product instanceof WC_Product ? (int) $product->get_meta('_panelr_connections') : 0;
			if (!$is_trial && $line_conn && $plan_conn && $plan_conn < $line_conn) {
				WC()->cart->remove_cart_item($key);
				wc_add_notice(sprintf(
					/* translators: %d: number of connections */
					__('A renewal needs a plan with at least %d connections.', 'panelr-for-woocommerce'),
					$line_conn
				), 'error');
				continue;
			}
		}

		// Credits: the balance must cover every credit-paid line.
		$credits_needed = self::credits_in_cart();
		if ($credits_needed > 0) {
			$balance = Panelr_Session::is_signed_in() ? Panelr_Session::credits_balance() : 0;
			if ($balance < $credits_needed) {
				foreach (WC()->cart->get_cart() as $key => $item) {
					if (!empty($item['_panelr_pay_with_points'])) {
						unset(WC()->cart->cart_contents[$key]['_panelr_pay_with_points']);
					}
				}
				wc_add_notice(__('Your credits no longer cover this order, so it will be paid in money.', 'panelr-for-woocommerce'), 'notice');
			}
		}
	}

	// ── Credits ───────────────────────────────────────────────────────────

	/** Credits every credit-paid line in the cart needs. */
	public static function credits_in_cart(): int
	{
		if (!WC()->cart) return 0;
		$sum = 0;
		foreach (WC()->cart->get_cart() as $item) {
			if (!empty($item['_panelr_pay_with_points']) && $item['data'] instanceof WC_Product) {
				$sum += (int) $item['data']->get_meta('_panelr_referral_cost_points') * max(1, (int) $item['quantity']);
			}
		}
		return $sum;
	}

	public static function all_lines_credit_paid(): bool
	{
		if (!WC()->cart || WC()->cart->is_empty()) return false;
		foreach (WC()->cart->get_cart() as $item) {
			if (empty($item['_panelr_pay_with_points'])) return false;
		}
		return true;
	}

	public static function has_panelr_items(): bool
	{
		if (!WC()->cart) return false;
		foreach (WC()->cart->get_cart() as $item) {
			if (!empty($item['_panelr_intent']) && $item['_panelr_intent'] !== 'balance_payment') return true;
		}
		return false;
	}

	/** A credit-paid line costs no money. */
	public static function zero_credit_lines(WC_Cart $cart): void
	{
		if (is_admin() && !defined('DOING_AJAX')) return;
		foreach ($cart->get_cart() as $item) {
			if (!empty($item['_panelr_pay_with_points']) && $item['data'] instanceof WC_Product) {
				$item['data']->set_price(0);
			}
		}
	}

	/** "Pay with N credits" under the add-to-cart button for a member whose balance covers it. */
	public static function credits_button(): void
	{
		global $product;
		if (!$product instanceof WC_Product) return;
		$cost = (int) $product->get_meta('_panelr_referral_cost_points');
		if (!$cost || !Panelr_Session::is_signed_in()) return;
		if ($product->get_meta('_panelr_is_trial') === '1') return;
		$balance = Panelr_Session::credits_balance();
		if ($balance < $cost) return;
		Panelr_Template::output('credits-button', [
			'product' => $product,
			'cost'    => $cost,
			'balance' => $balance,
		]);
	}

	public static function ajax_add_with_credits(): void
	{
		check_ajax_referer('panelr_cart', 'nonce');
		$wc_id = absint(wp_unslash($_POST['product_id'] ?? 0));
		$product = $wc_id ? wc_get_product($wc_id) : null;
		if (!$product || !Panelr_Session::is_signed_in()) {
			wp_send_json_error(['message' => __('Sign in to pay with credits.', 'panelr-for-woocommerce')]);
		}
		$cost = (int) $product->get_meta('_panelr_referral_cost_points');
		if (!$cost || Panelr_Session::credits_balance() < $cost + self::credits_in_cart()) {
			wp_send_json_error(['message' => __('Your credits do not cover this plan.', 'panelr-for-woocommerce')]);
		}
		$key = WC()->cart->add_to_cart($wc_id, 1, 0, [], [
			'_panelr_intent'          => 'new_activation',
			'_panelr_plugin_id'       => (int) $product->get_meta('_panelr_plugin_id'),
			'_panelr_pay_with_points' => 1,
		]);
		if (!$key) {
			wp_send_json_error(['message' => __('That plan could not be added to the cart.', 'panelr-for-woocommerce')]);
		}
		wp_send_json_success(['checkout_url' => wc_get_checkout_url()]);
	}

	// ── Fees: the Panelr coupon and the mapped method's fee or discount ──

	public static function add_fees(WC_Cart $cart): void
	{
		if (is_admin() && !defined('DOING_AJAX')) return;
		if (!self::has_panelr_items()) return;

		// An order handed over by Panelr carries its own coupon and fee.
		$handoff = Panelr_Handoff::current();
		if ($handoff) {
			Panelr_Handoff::add_fees($cart, $handoff);
			return;
		}

		$money = 0.0;
		foreach ($cart->get_cart() as $item) {
			if (empty($item['_panelr_pay_with_points'])) {
				$money += (float) $item['line_total'];
			}
		}

		// Panelr coupon (one source of discount per order).
		if (self::coupon_mode() === 'panelr') {
			$coupon = self::applied_coupon();
			if ($coupon && $money > 0) {
				$discount = min($money, (float) $coupon['discount']);
				if ($discount > 0) {
					$cart->add_fee(sprintf(
						/* translators: %s: coupon code */
						__('Coupon %s', 'panelr-for-woocommerce'),
						strtoupper($coupon['code'])
					), -$discount, false);
					$money -= $discount;
				}
			}
		}

		// Payment method fee / discount, from the mapped Panelr method's settings.
		$gateway_id = WC()->session ? (string) WC()->session->get('chosen_payment_method') : '';
		if ($gateway_id && $gateway_id !== Panelr_Credits_Gateway::ID) {
			$pm = Panelr_Helpers::mapped_method($gateway_id);
			if ($pm) {
				$adj = Panelr_Helpers::method_adjustment($pm, max(0.0, $money));
				if ($adj['amount']) {
					$cart->add_fee($adj['label'], $adj['direction'] === 'fee' ? $adj['amount'] : -$adj['amount'], false);
				}
			}
		}
	}

	// ── Coupons ───────────────────────────────────────────────────────────

	public static function coupon_mode(): string
	{
		return get_option('panelr_coupon_source', 'woocommerce') === 'panelr' ? 'panelr' : 'woocommerce';
	}

	public static function maybe_disable_wc_coupons(bool $enabled): bool
	{
		if (is_admin() && !defined('DOING_AJAX')) return $enabled;
		if (Panelr_Handoff::current()) return false;
		return self::coupon_mode() === 'panelr' ? false : $enabled;
	}

	/** @return array{code:string,label:string,discount:float}|null */
	public static function applied_coupon(): ?array
	{
		$c = Panelr_Session::get(self::COUPON_KEY);
		return (is_array($c) && !empty($c['code'])) ? $c : null;
	}

	public static function ajax_apply_coupon(): void
	{
		check_ajax_referer('panelr_cart', 'nonce');
		if (self::coupon_mode() !== 'panelr') {
			wp_send_json_error(['message' => __('Codes are handled by WooCommerce on this store.', 'panelr-for-woocommerce')]);
		}
		$code = strtoupper(sanitize_text_field(wp_unslash($_POST['code'] ?? '')));
		if ($code === '') {
			wp_send_json_error(['message' => __('Enter a code first.', 'panelr-for-woocommerce')]);
		}
		if (!Panelr_Helpers::rate_limit('coupon', 15, 15 * MINUTE_IN_SECONDS)) {
			wp_send_json_error(['message' => __('Too many tries. Please wait a few minutes.', 'panelr-for-woocommerce')]);
		}

		$money = 0.0;
		if (WC()->cart) {
			foreach (WC()->cart->get_cart() as $item) {
				if (empty($item['_panelr_pay_with_points'])) {
					$money += (float) $item['line_total'];
				}
			}
		}
		$email  = Panelr_Session::email() ?: (WC()->customer ? WC()->customer->get_billing_email() : '');
		$result = Panelr_API::instance()->validate_coupon($code, $money, $email);

		if (!$result['ok']) {
			wp_send_json_error(['message' => $result['error']]);
		}
		if (empty($result['data']['valid'])) {
			wp_send_json_error(['message' => $result['data']['reason'] ?? __('That code does not work right now.', 'panelr-for-woocommerce')]);
		}

		Panelr_Session::set(self::COUPON_KEY, [
			'code'     => (string) ($result['data']['code'] ?? $code),
			'label'    => (string) ($result['data']['label'] ?? ''),
			'discount' => (float) ($result['data']['discount'] ?? 0),
		]);
		wp_send_json_success(['message' => sprintf(
			/* translators: 1: coupon code, 2: discount label */
			__('%1$s applied: %2$s', 'panelr-for-woocommerce'),
			$code,
			(string) ($result['data']['label'] ?? '')
		)]);
	}

	public static function ajax_remove_coupon(): void
	{
		check_ajax_referer('panelr_cart', 'nonce');
		Panelr_Session::forget(self::COUPON_KEY);
		wp_send_json_success(['message' => __('Code removed.', 'panelr-for-woocommerce')]);
	}

	/** The coupon box (Panelr mode) and the "Invited by a member" line, in the cart totals. */
	public static function render_cart_extras(): void
	{
		if (!self::has_panelr_items() || Panelr_Handoff::current()) return;
		Panelr_Template::output('cart-extras', [
			'coupon_mode' => self::coupon_mode(),
			'coupon'      => self::applied_coupon(),
			'invited'     => Panelr_Session::referral_code() !== '',
			'credits'     => self::credits_in_cart(),
		]);
	}

	// ── Items for Panelr ──────────────────────────────────────────────────

	/** The cart lines of an order in Panelr's item shape. */
	public static function order_items(WC_Order $order): array
	{
		$items = [];
		foreach ($order->get_items() as $item) {
			if (!is_a($item, 'WC_Order_Item_Product')) continue;
			$panelr_id = Panelr_Helpers::panelr_product_id($item->get_product_id());
			if (!$panelr_id) continue;
			$intent = $item->get_meta('_panelr_intent') ?: 'new_activation';
			if ($intent === 'balance_payment') continue;

			$row = [
				'product_id' => $panelr_id,
				'intent'     => $intent,
				'qty'        => max(1, (int) $item->get_quantity()),
			];
			$activation_id = (int) $item->get_meta('_panelr_activation_id');
			if ($activation_id) $row['activation_id'] = $activation_id;
			if ($item->get_meta('_panelr_pay_with_points') === '1') $row['pay_with_points'] = true;
			$items[] = $row;
		}
		return $items;
	}
}
