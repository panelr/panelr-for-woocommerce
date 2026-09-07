<?php
defined('ABSPATH') || exit;

/**
 * Order creation — one path, at the right moment.
 *
 * When WooCommerce creates the order, the mapped Panelr method's mode
 * decides: manual → create_work_order right then; automatic → complete_order
 * once payment_complete fires. If the method list cannot be read the order
 * is held and retried; nothing is guessed. Panelr never calls back, so the
 * order is polled afterwards (Panelr_Orders).
 */
class Panelr_Checkout
{
	public static function init(): void
	{
		add_action('woocommerce_checkout_order_processed',            [__CLASS__, 'on_order_created'], 10, 1);
		add_action('woocommerce_store_api_checkout_order_processed',  [__CLASS__, 'on_order_created_block'], 10, 1);
		add_action('woocommerce_payment_complete',                    [__CLASS__, 'on_payment_complete'], 10, 1);
		add_action('woocommerce_thankyou',                            [__CLASS__, 'render_thankyou'], 10);
		add_action('woocommerce_view_order',                          [__CLASS__, 'render_view_order'], 10);
		add_action('wp_ajax_panelr_submit_payment',                   [__CLASS__, 'ajax_submit_payment']);
		add_action('wp_ajax_nopriv_panelr_submit_payment',            [__CLASS__, 'ajax_submit_payment']);
		add_action('wp_ajax_panelr_order_poll',                       [__CLASS__, 'ajax_order_poll']);
		add_action('wp_ajax_nopriv_panelr_order_poll',                [__CLASS__, 'ajax_order_poll']);
		add_action('panelr_retry_held_order',                         [__CLASS__, 'retry_held_order'], 10, 2);
		add_filter('woocommerce_available_payment_gateways',          [__CLASS__, 'filter_gateways']);
		add_filter('woocommerce_cart_needs_payment',                  [__CLASS__, 'cart_needs_payment'], 10, 2);
		add_action('woocommerce_checkout_before_customer_details',    [__CLASS__, 'render_account_step'], 5);
		add_filter('woocommerce_checkout_get_value',                  [__CLASS__, 'prefill_from_account'], 10, 2);
		add_action('woocommerce_after_checkout_validation',           [__CLASS__, 'validate_account_step'], 10, 2);
		add_action('woocommerce_store_api_checkout_update_order_from_request', [__CLASS__, 'block_checkout_needs_account'], 10, 2);
		add_action('template_redirect',                               [__CLASS__, 'block_checkout_account_gate'], 20);
		add_action('wp_ajax_panelr_checkout_signout',                 [__CLASS__, 'ajax_checkout_signout']);
		add_action('wp_ajax_nopriv_panelr_checkout_signout',          [__CLASS__, 'ajax_checkout_signout']);
		add_filter('woocommerce_order_needs_payment',                 [__CLASS__, 'order_needs_payment'], 10, 2);
	}

	/** A cart paid entirely with credits totals zero in money but still needs its gateway. */
	public static function cart_needs_payment($needs, $cart): bool
	{
		return (bool) $needs || Panelr_Cart::all_lines_credit_paid();
	}

	public static function order_needs_payment($needs, $order): bool
	{
		if ($needs || !$order instanceof WC_Order) return (bool) $needs;
		if ($order->get_meta('_panelr_work_order_id') || !$order->has_status(['pending', 'failed'])) return false;
		$credit_items = 0;
		foreach ($order->get_items() as $item) {
			if (!is_a($item, 'WC_Order_Item_Product')) continue;
			if ($item->get_meta('_panelr_pay_with_points') !== '1') return false;
			$credit_items++;
		}
		return $credit_items > 0;
	}

	// ── The account step ──────────────────────────────────────────────────

	/** Does this cart need a Panelr login before the order is placed? */
	public static function checkout_needs_account(): bool
	{
		if (!WC()->cart || !Panelr_Cart::has_panelr_items()) return false;
		if (Panelr_Handoff::current()) return false;          // Panelr already made the login
		$setting = get_option('panelr_checkout_account', 'auto');
		if ($setting === 'off') return false;
		if ($setting === 'on') return true;
		return Panelr_Helpers::accounts_enabled();
	}

	public static function render_account_step(): void
	{
		if (!self::checkout_needs_account()) return;
		wp_enqueue_script('panelr-common');
		Panelr_Template::output('checkout-account', [
			'signed_in'      => Panelr_Session::is_signed_in(),
			'email'          => Panelr_Session::email(),
			'name'           => Panelr_Session::name(),
			'require_invite' => Panelr_Portal::registration_needs_invite() && Panelr_Session::referral_code() === '',
			'referral_enabled' => Panelr_Helpers::referral_enabled(),
			'invite_code'    => Panelr_Session::referral_code(),
			'signup_points'  => Panelr_Helpers::referral_enabled() ? Panelr_Helpers::signup_points() : 0,
			'portal_url'     => Panelr_Helpers::portal_url(),
			'nonce'          => wp_create_nonce('panelr_checkout_signout'),
		]);
	}

	/** A signed-in member's checkout starts with their own details. */
	public static function prefill_from_account($value, $input)
	{
		if ($value !== null && $value !== '') return $value;
		if (!Panelr_Session::is_signed_in()) return $value;
		if ($input === 'billing_email') return Panelr_Session::email();
		if ($input === 'billing_first_name' || $input === 'billing_last_name') {
			$parts = preg_split('/\s+/', trim(Panelr_Session::name()), 2);
			return $input === 'billing_first_name' ? ($parts[0] ?? '') : ($parts[1] ?? '');
		}
		return $value;
	}

	/** Sign in or create the Panelr login from the checkout form; add errors to WooCommerce's list. */
	public static function validate_account_step($data, $errors): void
	{
		if (!self::checkout_needs_account()) return;
		$email = strtolower(trim((string) ($data['billing_email'] ?? '')));
		if ($email === '' || !is_email($email)) return; // WooCommerce reports the missing email itself

		if (Panelr_Session::is_signed_in()) {
			if (strtolower(Panelr_Session::email()) !== $email) {
				$errors->add('panelr_account', sprintf(
					/* translators: %s: account email */
					__('Use the email of the account you are signed in with, %s, or sign out to use another.', 'panelr-for-woocommerce'),
					Panelr_Session::email()
				));
			}
			return;
		}

		// WooCommerce verified the checkout nonce before this hook. Passwords are not sanitized: that would change them.
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$password = (string) wp_unslash($_POST['panelr_account_password'] ?? '');
		$confirm  = (string) wp_unslash($_POST['panelr_account_password2'] ?? '');
		$invite   = strtolower(preg_replace('/[^a-z0-9_\-]/', '', strtolower(sanitize_text_field(wp_unslash($_POST['panelr_account_invite'] ?? '')))));
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if (strlen($password) < 8) {
			$errors->add('panelr_account', __('Choose an account password of at least 8 characters.', 'panelr-for-woocommerce'));
			return;
		}
		if ($confirm !== '' && $confirm !== $password) {
			$errors->add('panelr_account', __('The two passwords do not match.', 'panelr-for-woocommerce'));
			return;
		}
		if (!Panelr_Helpers::rate_limit('checkout_account', 10, 15 * MINUTE_IN_SECONDS)) {
			$errors->add('panelr_account', __('Too many tries. Please wait a few minutes.', 'panelr-for-woocommerce'));
			return;
		}
		$name   = trim((string) ($data['billing_first_name'] ?? '') . ' ' . (string) ($data['billing_last_name'] ?? ''));
		$result = Panelr_Portal::account_for_checkout($email, $password, $name, $invite);
		if (empty($result['ok'])) {
			$errors->add('panelr_account', (string) $result['error']);
		}
	}

	/**
	 * Block checkout has no room for a password, so it opens the member area's
	 * sign-in / create-account form first and comes straight back.
	 */
	public static function block_checkout_account_gate(): void
	{
		if (is_admin() || !function_exists('is_checkout') || !is_checkout() || is_wc_endpoint_url('order-received') || is_wc_endpoint_url('order-pay')) return;
		if (!self::checkout_needs_account() || Panelr_Session::is_signed_in()) return;
		$page = get_post(get_queried_object_id());
		if (!$page || !has_block('woocommerce/checkout', $page)) return; // classic checkout has its own account step
		if (!Panelr_Helpers::page_url('portal')) return;
		wp_safe_redirect(Panelr_Helpers::portal_url(['view' => 'checkout', 'return' => rawurlencode(wc_get_checkout_url())]));
		exit;
	}

	/** Block checkout has no room for a password: the member area does the sign-in first. */
	public static function block_checkout_needs_account($order, $request): void
	{
		if (!self::checkout_needs_account() || Panelr_Session::is_signed_in()) return;
		$message = sprintf(
			/* translators: %s: member area URL */
			__('Please sign in or create your account first: %s', 'panelr-for-woocommerce'),
			Panelr_Helpers::portal_url(['return' => rawurlencode(wc_get_checkout_url())])
		);
		if (class_exists('\Automattic\WooCommerce\StoreApi\Exceptions\RouteException')) {
			throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException('panelr_account_required', esc_html($message), 400);
		}
		throw new \Exception(esc_html($message));
	}

	public static function ajax_checkout_signout(): void
	{
		check_ajax_referer('panelr_checkout_signout', 'nonce');
		Panelr_Session::sign_out();
		wp_send_json_success();
	}

	// ── Gateways at checkout ──────────────────────────────────────────────

	/** An all-credits cart shows only the credits gateway; any other cart never shows it. */
	public static function filter_gateways(array $gateways): array
	{
		if ((is_admin() && !defined('DOING_AJAX')) || !WC()->cart) return $gateways;
		$handoff = Panelr_Handoff::current();
		if ($handoff) {
			unset($gateways[Panelr_Credits_Gateway::ID]);
			$allowed = Panelr_Handoff::allowed_gateways($handoff);
			$kept    = array_intersect_key($gateways, array_flip($allowed));
			return $kept ?: $gateways;
		}
		if (Panelr_Cart::all_lines_credit_paid() && Panelr_Session::is_signed_in()
			&& Panelr_Session::credits_balance() >= Panelr_Cart::credits_in_cart()) {
			return isset($gateways[Panelr_Credits_Gateway::ID]) ? [Panelr_Credits_Gateway::ID => $gateways[Panelr_Credits_Gateway::ID]] : $gateways;
		}
		unset($gateways[Panelr_Credits_Gateway::ID]);
		return $gateways;
	}

	// ── Order created ─────────────────────────────────────────────────────

	public static function on_order_created_block($order): void
	{
		if ($order instanceof WC_Order) {
			self::on_order_created($order->get_id());
		}
	}

	public static function on_order_created($order_id): void
	{
		$order = wc_get_order((int) $order_id);
		if (!$order) return;
		if ($order->get_meta('_panelr_work_order_id') || $order->get_meta('_panelr_balance_payment')) return;

		$items = Panelr_Cart::order_items($order);
		if (!$items) return;

		self::stamp_order_context($order);

		// Handed over by Panelr's checkout: the Panelr order already exists.
		$handoff = Panelr_Handoff::current();
		if ($handoff) {
			$order->update_meta_data('_panelr_reference_code',     (string) $handoff['ref']);
			$order->update_meta_data('_panelr_confirmation_token', (string) $handoff['token']);
			$order->update_meta_data('_panelr_method_id',          (int) $handoff['method_id']);
			$order->update_meta_data('_panelr_adjusted_total',     (float) $handoff['order_total']);
			if (!empty($handoff['manual'])) {
				$order->update_meta_data('_panelr_is_manual', '1');
				$order->update_meta_data('_panelr_mode', 'manual');
				$order->update_meta_data('_panelr_state', 'sent');
				$order->update_meta_data('_panelr_status', 'pending_payment');
			} else {
				$order->update_meta_data('_panelr_mode', 'automatic');
				$order->update_meta_data('_panelr_state', 'handoff');
			}
			$order->add_order_note(sprintf(
				/* translators: %s: Panelr reference */
				__('Started on Panelr as order %s; paid here.', 'panelr-for-woocommerce'),
				$handoff['ref']
			));
			$order->save();
			Panelr_Handoff::forget();
			return;
		}

		$gateway_id = (string) $order->get_payment_method();
		if ($gateway_id === Panelr_Credits_Gateway::ID) {
			// The credits gateway completes the order itself in process_payment.
			$order->update_meta_data('_panelr_state', 'credits');
			$order->save();
			return;
		}

		self::route_order($order, false);
	}

	/**
	 * Decide manual vs automatic from the mapped method's mode and act.
	 * Called on creation and from the held-order retry.
	 */
	public static function route_order(WC_Order $order, bool $is_retry): bool
	{
		$gateway_id = (string) $order->get_payment_method();
		$method_id  = Panelr_Helpers::mapped_method_id($gateway_id);

		if (!$method_id) {
			// Unmapped gateway: nothing Panelr can do with it until the operator maps it.
			$order->update_meta_data('_panelr_state', 'unmapped');
			$order->add_order_note(sprintf(
				/* translators: %s: gateway id */
				__('Panelr: the payment method "%s" is not mapped to a Panelr payment method. Map it under Panelr → Payments and press Send.', 'panelr-for-woocommerce'),
				$gateway_id
			));
			$order->save();
			return false;
		}

		$methods = Panelr_Helpers::payment_methods();
		if ($methods === null) {
			self::hold_order($order, __('Panelr could not be reached to read the payment methods.', 'panelr-for-woocommerce'), $is_retry);
			return false;
		}
		$pm = $methods[$method_id] ?? null;
		if (!$pm) {
			$order->update_meta_data('_panelr_state', 'unmapped');
			$order->add_order_note(__('Panelr: the mapped payment method is no longer enabled in Panelr.', 'panelr-for-woocommerce'));
			$order->save();
			return false;
		}

		$mode = ($pm['mode'] ?? 'manual') === 'automated' ? 'automatic' : 'manual';
		$order->update_meta_data('_panelr_mode', $mode);
		$order->update_meta_data('_panelr_method_id', (int) $pm['id']);

		if ($mode === 'manual') {
			return self::create_manual_order($order, $pm);
		}

		// Automatic: wait for payment_complete. If it already fired (retry), send now.
		$order->update_meta_data('_panelr_state', 'awaiting_payment');
		$order->save();
		if ($is_retry && $order->is_paid()) {
			return self::complete_automatic_order($order, $pm);
		}
		return true;
	}

	private static function hold_order(WC_Order $order, string $reason, bool $is_retry): void
	{
		$attempt = (int) $order->get_meta('_panelr_hold_attempt');
		$order->update_meta_data('_panelr_state', 'held');
		$order->update_meta_data('_panelr_hold_reason', $reason);
		if (!$is_retry) {
			$order->update_meta_data('_panelr_held_at', time());
			$order->add_order_note(sprintf(
				/* translators: %s: reason */
				__('Panelr: order held — %s It will be retried every 5 minutes for an hour.', 'panelr-for-woocommerce'),
				$reason
			));
		}
		$attempt++;
		$order->update_meta_data('_panelr_hold_attempt', $attempt);
		$order->save();

		if ($attempt <= 12 && function_exists('as_schedule_single_action')) {
			as_schedule_single_action(time() + 5 * MINUTE_IN_SECONDS, 'panelr_retry_held_order', [$order->get_id(), $attempt], 'panelr');
		} elseif ($attempt > 12) {
			$order->add_order_note(__('Panelr: still unreachable after an hour. Use Send on the Panelr → Orders page once the connection is back.', 'panelr-for-woocommerce'));
			$order->update_meta_data('_panelr_state', 'never_sent');
			$order->save();
		}
		set_transient('panelr_held_orders_notice', 1, HOUR_IN_SECONDS);
	}

	public static function retry_held_order($order_id, $attempt = 0): void
	{
		$order = wc_get_order((int) $order_id);
		if (!$order || $order->get_meta('_panelr_state') !== 'held') return;
		if ($order->get_meta('_panelr_confirmation_token') && !$order->get_meta('_panelr_work_order_id') && $order->is_paid()) {
			self::complete_handoff_order($order);
			return;
		}
		self::route_order($order, true);
	}

	/** Manual: the Panelr work order exists before the thank-you page loads. */
	public static function create_manual_order(WC_Order $order, array $pm): bool
	{
		$items = Panelr_Cart::order_items($order);
		if (!$items) return false;

		$body = self::common_body($order, $items);
		$body['status']            = 'pending_payment';
		$body['payment_method_id'] = (int) $pm['id'];

		// Panelr computes the method's own fee or discount on the total after
		// the coupon — the same sum WooCommerce showed at checkout.
		$result = Panelr_API::instance()->create_work_order($body);
		if (!$result['ok']) {
			if ($result['status'] === 422 || $result['status'] === 404) {
				$order->update_meta_data('_panelr_state', 'refused');
				$order->update_meta_data('_panelr_hold_reason', $result['error']);
				$order->add_order_note(sprintf(
					/* translators: %s: Panelr's message */
					__('Panelr refused the order: %s', 'panelr-for-woocommerce'),
					$result['error']
				));
				$order->save();
				return false;
			}
			self::hold_order($order, $result['error'], (bool) $order->get_meta('_panelr_hold_attempt'));
			return false;
		}

		$d = $result['data'];
		$order->update_meta_data('_panelr_work_order_id',      (int) $d['work_order_id']);
		$order->update_meta_data('_panelr_reference_code',     (string) $d['reference_code']);
		$order->update_meta_data('_panelr_confirmation_token', (string) $d['confirmation_token']);
		$order->update_meta_data('_panelr_is_manual',          '1');
		$order->update_meta_data('_panelr_state',              'sent');
		$order->update_meta_data('_panelr_status',             'pending_payment');
		$order->update_meta_data('_panelr_adjusted_total',     isset($d['order_total']) ? (float) $d['order_total'] : (float) $order->get_total());
		$order->update_meta_data('_panelr_credits_spent',      (int) ($d['credits_spent'] ?? 0));
		$order->delete_meta_data('_panelr_hold_reason');
		$order->add_order_note(sprintf(
			/* translators: %s: Panelr reference code */
			__('Panelr order %s created, waiting for payment.', 'panelr-for-woocommerce'),
			$d['reference_code']
		));
		$order->save();

		Panelr_API::instance()->send_payment_instructions((int) $d['work_order_id'], $order->get_checkout_order_received_url());
		Panelr_Orders::schedule_polling($order->get_id());
		return true;
	}

	// ── Payment complete (automatic) ──────────────────────────────────────

	public static function on_payment_complete($order_id): void
	{
		$order = wc_get_order((int) $order_id);
		if (!$order) return;
		if ($order->get_meta('_panelr_work_order_id')) return;
		if ($order->get_meta('_panelr_balance_payment')) return;
		if ((string) $order->get_payment_method() === Panelr_Credits_Gateway::ID) return;

		$items = Panelr_Cart::order_items($order);
		if (!$items) return;

		$state = (string) $order->get_meta('_panelr_state');
		if ($state === 'handoff') {
			self::complete_handoff_order($order);
			return;
		}
		if ($state === '' ) {
			// Created outside checkout (admin, API, an old cart): route now.
			self::stamp_order_context($order);
			if (!self::route_order($order, false)) return;
			$state = (string) $order->get_meta('_panelr_state');
		}
		if ($state !== 'awaiting_payment') return;

		$pm = Panelr_Helpers::mapped_method((string) $order->get_payment_method());
		if (!$pm) {
			self::hold_order($order, __('Panelr could not be reached to read the payment methods.', 'panelr-for-woocommerce'), false);
			return;
		}
		self::complete_automatic_order($order, $pm);
	}

	public static function complete_automatic_order(WC_Order $order, array $pm): bool
	{
		if ($order->get_meta('_panelr_work_order_id')) return true;

		$items = Panelr_Cart::order_items($order);
		$body  = self::common_body($order, $items);
		$body['payment_method_id']      = (int) $pm['id'];
		$body['payment_transaction_id'] = (string) ($order->get_transaction_id() ?: 'WC-' . $order->get_id());
		$body['payment_amount']         = (float) $order->get_total();

		$result = Panelr_API::instance()->complete_order($body);
		if (!$result['ok']) {
			if (in_array($result['status'], [422, 404], true)) {
				$order->update_meta_data('_panelr_state', 'refused');
				$order->update_meta_data('_panelr_hold_reason', $result['error']);
				$order->add_order_note(sprintf(
					/* translators: %s: Panelr's message */
					__('Panelr refused the order: %s', 'panelr-for-woocommerce'),
					$result['error']
				));
				$order->save();
				return false;
			}
			self::hold_order($order, $result['error'], (bool) $order->get_meta('_panelr_hold_attempt'));
			return false;
		}

		self::record_completed_send($order, $result['data']);
		return true;
	}

	/** An order Panelr created and this store paid: mark it paid on Panelr by its token. */
	public static function complete_handoff_order(WC_Order $order): bool
	{
		if ($order->get_meta('_panelr_work_order_id')) return true;
		$token = (string) $order->get_meta('_panelr_confirmation_token');
		if ($token === '') return false;

		$body = [
			'confirmation_token'     => $token,
			'payment_transaction_id' => (string) ($order->get_transaction_id() ?: 'WC-' . $order->get_id()),
			'payment_amount'         => (float) $order->get_total(),
		];
		$mapped = Panelr_Helpers::mapped_method_id((string) $order->get_payment_method());
		if ($mapped) $body['payment_method_id'] = $mapped;

		$result = Panelr_API::instance()->complete_order($body);
		if (!$result['ok']) {
			if (in_array($result['status'], [422, 404], true)) {
				$order->update_meta_data('_panelr_state', 'refused');
				$order->update_meta_data('_panelr_hold_reason', $result['error']);
				$order->add_order_note(sprintf(
					/* translators: %s: Panelr's message */
					__('Panelr refused the payment confirmation: %s', 'panelr-for-woocommerce'),
					$result['error']
				));
				$order->save();
				return false;
			}
			self::hold_order($order, $result['error'], (bool) $order->get_meta('_panelr_hold_attempt'));
			return false;
		}
		self::record_completed_send($order, $result['data']);
		return true;
	}

	/** After complete_order (money or credits): stamp the order and start polling. */
	public static function record_completed_send(WC_Order $order, array $d): void
	{
		$order->update_meta_data('_panelr_work_order_id',  (int) $d['work_order_id']);
		$order->update_meta_data('_panelr_reference_code', (string) $d['reference_code']);
		$order->update_meta_data('_panelr_state',          'queued');
		$order->update_meta_data('_panelr_status',         'queued');
		$order->update_meta_data('_panelr_credits_spent',  (int) ($d['credits_spent'] ?? 0));
		$order->delete_meta_data('_panelr_hold_reason');
		$order->add_order_note(sprintf(
			/* translators: %s: Panelr reference code */
			__('Panelr order %s is being set up.', 'panelr-for-woocommerce'),
			$d['reference_code']
		));
		$order->save();
		Panelr_Orders::schedule_polling($order->get_id());
	}

	// ── Shared body ───────────────────────────────────────────────────────

	private static function common_body(WC_Order $order, array $items): array
	{
		// The signed-in Panelr account owns the order; billing details only
		// fill in when nobody was signed in at checkout.
		$accountEmail = (string) $order->get_meta('_panelr_customer_email');
		$accountName  = (string) $order->get_meta('_panelr_customer_name');
		$body = [
			'customer_email' => $accountEmail ?: $order->get_billing_email(),
			'customer_name'  => ($accountEmail ? $accountName : '') ?: trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
			'customer_phone' => $order->get_billing_phone(),
			'wc_order_id'    => (string) $order->get_id(),
			'source'         => 'woocommerce',
			'items'          => $items,
		];
		$coupon = (string) $order->get_meta('_panelr_coupon_code');
		if ($coupon) $body['coupon_code'] = $coupon;
		$ref = (string) $order->get_meta('_panelr_referral_code');
		if ($ref) $body['referral_code'] = $ref;
		return $body;
	}

	/** What the visitor carried at checkout: the Panelr coupon, the invite code, the account, the line names. */
	private static function stamp_order_context(WC_Order $order): void
	{
		if (Panelr_Cart::coupon_mode() === 'panelr') {
			$coupon = Panelr_Cart::applied_coupon();
			if ($coupon) {
				$order->update_meta_data('_panelr_coupon_code', $coupon['code']);
				Panelr_Session::forget(Panelr_Cart::COUPON_KEY);
			}
		}
		$ref = Panelr_Session::referral_code();
		if ($ref) $order->update_meta_data('_panelr_referral_code', $ref);
		if (Panelr_Session::is_signed_in()) {
			// The Panelr account that placed the order. WooCommerce's billing
			// fields can be prefilled from a different WordPress profile, so
			// the order is always filed under this account, never the billing email.
			$order->update_meta_data('_panelr_customer_id', Panelr_Session::customer_id());
			$order->update_meta_data('_panelr_customer_email', Panelr_Session::email());
			$order->update_meta_data('_panelr_customer_name', Panelr_Session::name());
		}
		$order->save();
	}

	// ── Thank-you page ────────────────────────────────────────────────────

	public static function render_thankyou($order_id): void
	{
		$order = wc_get_order((int) $order_id);
		if (!$order) return;
		if ($order->get_meta('_panelr_balance_payment')) return;
		if (!Panelr_Cart::order_items($order)) return;

		self::render_order_panel($order, true);
	}

	public static function render_view_order($order_id): void
	{
		$order = wc_get_order((int) $order_id);
		if (!$order || !Panelr_Cart::order_items($order)) return;
		self::render_order_panel($order, false);
	}

	private static function render_order_panel(WC_Order $order, bool $is_thankyou): void
	{
		$state = (string) $order->get_meta('_panelr_state');
		$ref   = (string) $order->get_meta('_panelr_reference_code');
		$token = (string) $order->get_meta('_panelr_confirmation_token');
		$manual = $order->get_meta('_panelr_is_manual') === '1';

		wp_enqueue_script('panelr-common');
		wp_register_script('panelr-qrcode', PANELR_PLUGIN_URL . 'assets/js/qrcode.min.js', [], '1.0.0', true);
		wp_enqueue_script('panelr-thankyou', PANELR_PLUGIN_URL . 'assets/js/thankyou.js', ['jquery', 'panelr-common', 'panelr-qrcode'], PANELR_VERSION, true);
		wp_localize_script('panelr-thankyou', 'panelrThankyou', [
			'ajaxurl'            => admin_url('admin-ajax.php'),
			'nonce'              => wp_create_nonce('panelr_submit_payment'),
			'poll_nonce'         => wp_create_nonce('panelr_order_poll_' . $order->get_id()),
			'confirmation_token' => $token,
			'order_id'           => $order->get_id(),
			'order_key'          => $order->get_order_key(),
			'already_submitted'  => $order->get_meta('_panelr_payment_submitted') === '1' ? '1' : '0',
			'poll'               => in_array($state, ['queued', 'sent'], true) && !$manual ? '1' : '0',
			'i18n'               => [
				'enter_txid'     => __('Enter the transaction id or reference from your payment.', 'panelr-for-woocommerce'),
				'submitting'     => __('Sending…', 'panelr-for-woocommerce'),
				'received'       => __('Payment confirmation received. We will check it and set up your service shortly.', 'panelr-for-woocommerce'),
				'add_note'       => __('Add note', 'panelr-for-woocommerce'),
				'request_failed' => __('Something went wrong. Please try again.', 'panelr-for-woocommerce'),
			],
		]);

		$panelr_order = null;
		if ($ref && ($token || $order->get_billing_email())) {
			$r = Panelr_API::instance()->get_work_order($ref, $token ?: null, $token ? null : $order->get_billing_email());
			if ($r['ok']) $panelr_order = $r['data'];
		}

		$pm = null;
		if ($manual) {
			$pm = Panelr_Helpers::mapped_method((string) $order->get_payment_method());
		}

		Panelr_Template::output('thankyou/panel', [
			'order'        => $order,
			'is_thankyou'  => $is_thankyou,
			'state'        => $state,
			'manual'       => $manual,
			'ref'          => $ref,
			'panelr_order' => $panelr_order,
			'pm'           => $pm,
			'instructions' => $pm ? self::instructions($pm, $panelr_order, $order) : null,
			'submitted'    => $order->get_meta('_panelr_payment_submitted') === '1',
			'lines'        => json_decode((string) $order->get_meta('_panelr_lines'), true) ?: [],
			'status'       => (string) $order->get_meta('_panelr_status'),
			'hold_reason'  => (string) $order->get_meta('_panelr_hold_reason'),
			'portal_url'   => Panelr_Helpers::portal_url(),
		]);
	}

	/**
	 * Panelr's instructions and the method's config, rendered by processor
	 * with copy rows. The QR only for a payable address. Amount in the store currency.
	 * @return array{note:string,rows:array,qr:?string,txid_label:string,amount:float,currency:string}
	 */
	public static function instructions(array $pm, ?array $panelr_order, ?WC_Order $order = null): array
	{
		$config   = is_array($pm['config'] ?? null) ? $pm['config'] : [];
		$currency = (string) ($panelr_order['currency'] ?? ($order ? $order->get_currency() : get_woocommerce_currency()));
		$amount   = $panelr_order
			? (float) (!empty($panelr_order['has_partials']) ? $panelr_order['balance_due'] : $panelr_order['order_total'])
			: (float) ($order ? ($order->get_meta('_panelr_adjusted_total') ?: $order->get_total()) : 0);
		$ref      = (string) ($panelr_order['reference_code'] ?? ($order ? $order->get_meta('_panelr_reference_code') : ''));

		$rows = [];
		$qr   = null;
		$txid = __('Transaction id or reference', 'panelr-for-woocommerce');

		$add = function (string $label, $value) use (&$rows) {
			$value = trim((string) $value);
			if ($value !== '') $rows[] = ['label' => $label, 'value' => $value];
		};

		switch ((string) ($pm['processor'] ?? '')) {
			case 'venmo':
				$add(__('Venmo', 'panelr-for-woocommerce'), !empty($config['handle']) ? '@' . ltrim($config['handle'], '@') : '');
				$txid = __('Venmo transaction id', 'panelr-for-woocommerce');
				if (!empty($config['handle'])) $qr = 'https://venmo.com/u/' . rawurlencode(ltrim($config['handle'], '@'));
				break;
			case 'cashapp':
				$add(__('Cash App', 'panelr-for-woocommerce'), !empty($config['cashtag']) ? '$' . ltrim($config['cashtag'], '$') : '');
				$txid = __('Cash App transaction id', 'panelr-for-woocommerce');
				if (!empty($config['cashtag'])) $qr = 'https://cash.app/$' . rawurlencode(ltrim($config['cashtag'], '$'));
				break;
			case 'zelle':
				$add(__('Zelle', 'panelr-for-woocommerce'), $config['recipient'] ?? '');
				break;
			case 'paypal':
				$add(__('PayPal', 'panelr-for-woocommerce'), $config['email'] ?? '');
				if (!empty($config['me_link'])) {
					$add(__('PayPal.me', 'panelr-for-woocommerce'), $config['me_link']);
					$qr = (string) $config['me_link'];
				}
				$txid = __('PayPal transaction id', 'panelr-for-woocommerce');
				break;
			case 'crypto':
			case 'bitcoin':
			case 'usdt':
				foreach (['address', 'wallet', 'wallet_address'] as $k) {
					if (!empty($config[$k])) {
						$add(__('Wallet address', 'panelr-for-woocommerce'), $config[$k]);
						$qr = (string) $config[$k];
						break;
					}
				}
				if (!empty($config['network'])) $add(__('Network', 'panelr-for-woocommerce'), $config['network']);
				$txid = __('Transaction hash', 'panelr-for-woocommerce');
				break;
			default:
				foreach ($config as $k => $v) {
					if (is_scalar($v) && $v !== '' && !in_array($k, ['secret', 'api_key', 'token', 'webhook_secret', 'client_secret'], true)) {
						$add(ucwords(str_replace('_', ' ', (string) $k)), $v);
					}
				}
				break;
		}
		if ($ref) $add(__('Reference', 'panelr-for-woocommerce'), $ref);

		return [
			'note'       => (string) ($pm['instructions'] ?? ''),
			'rows'       => $rows,
			'qr'         => $qr,
			'txid_label' => $txid,
			'amount'     => $amount,
			'currency'   => $currency,
		];
	}

	// ── AJAX: "I've paid" on the thank-you page ───────────────────────────

	public static function ajax_submit_payment(): void
	{
		check_ajax_referer('panelr_submit_payment', 'nonce');

		$token    = sanitize_text_field(wp_unslash($_POST['confirmation_token'] ?? ''));
		$txid     = sanitize_text_field(wp_unslash($_POST['transaction_id'] ?? ''));
		$note     = sanitize_textarea_field(wp_unslash($_POST['customer_note'] ?? ''));
		$order_id = absint(wp_unslash($_POST['order_id'] ?? 0));
		$order_key = sanitize_text_field(wp_unslash($_POST['order_key'] ?? ''));

		$order = $order_id ? wc_get_order($order_id) : null;
		if (!$order || $order->get_order_key() !== $order_key) {
			wp_send_json_error(['message' => __('That order could not be found.', 'panelr-for-woocommerce')]);
		}
		if (!$token || $order->get_meta('_panelr_confirmation_token') !== $token) {
			wp_send_json_error(['message' => __('That order could not be found.', 'panelr-for-woocommerce')]);
		}

		$already = $order->get_meta('_panelr_payment_submitted') === '1';
		if ($already) {
			if ($note !== '') {
				$order->add_order_note(sprintf(
					/* translators: %s: the customer's note */
					__('Customer note: %s', 'panelr-for-woocommerce'),
					$note
				));
				$order->save();
			}
			wp_send_json_success(['message' => __('Your note was added.', 'panelr-for-woocommerce')]);
		}
		$result = Panelr_API::instance()->submit_payment([
			'confirmation_token'     => $token,
			'payment_transaction_id' => $txid,
			'customer_note'          => $note,
		]);

		if (!$result['ok']) {
			wp_send_json_error(['message' => $result['error']]);
		}

		if (!$already) {
			$order->update_meta_data('_panelr_payment_submitted', '1');
			$order->update_meta_data('_panelr_transaction_id', $txid);
			$order->update_meta_data('_panelr_status', 'payment_submitted');
			$order->add_order_note(sprintf(
				/* translators: %s: transaction id */
				__('Customer confirmed payment (%s).', 'panelr-for-woocommerce'),
				$txid ?: '—'
			));
			$order->save();
			Panelr_Orders::schedule_polling($order->get_id());
		}

		wp_send_json_success(['message' => $already
			? __('Your note was added.', 'panelr-for-woocommerce')
			: __('Payment confirmation received. We will check it and set up your service shortly.', 'panelr-for-woocommerce')]);
	}

	/** The thank-you page asks whether the order is ready yet. */
	public static function ajax_order_poll(): void
	{
		$order_id  = absint(wp_unslash($_POST['order_id'] ?? 0));
		$order_key = sanitize_text_field(wp_unslash($_POST['order_key'] ?? ''));
		check_ajax_referer('panelr_order_poll_' . $order_id, 'nonce');

		$order = $order_id ? wc_get_order($order_id) : null;
		if (!$order || $order->get_order_key() !== $order_key) {
			wp_send_json_error();
		}
		$status = Panelr_Orders::check_order($order, 'customer');
		wp_send_json_success([
			'status' => $status,
			'label'  => Panelr_Helpers::order_status_label($status),
			'done'   => in_array($status, ['completed', 'canceled', 'payment_failed'], true),
			'lines'  => json_decode((string) $order->get_meta('_panelr_lines'), true) ?: [],
		]);
	}
}
