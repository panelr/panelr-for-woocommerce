<?php
defined('ABSPATH') || exit;

/**
 * [panelr_order_status] — three doors, one page:
 *  - Panelr's emailed link: ?panelr_ref&panelr_token (the token is the door)
 *  - the ref + email form (nonce-checked POST)
 *  - the bot hand-off: ?panelr_product_id&panelr_email… lands on a confirm
 *    page; the cart changes only on a nonce-checked POST.
 * Every redirect happens in template_redirect, never inside the shortcode.
 */
class Panelr_Order_Status
{
	public static function init(): void
	{
		add_shortcode('panelr_order_status', [__CLASS__, 'render']);
		add_filter('query_vars', [__CLASS__, 'register_query_vars']);
		add_action('template_redirect', [__CLASS__, 'handle_posts']);
		add_action('wp_ajax_panelr_order_status_submit', [__CLASS__, 'ajax_submit_payment']);
		add_action('wp_ajax_nopriv_panelr_order_status_submit', [__CLASS__, 'ajax_submit_payment']);
		add_action('woocommerce_payment_complete', [__CLASS__, 'handle_balance_payment_complete']);
	}

	public static function register_query_vars(array $vars): array
	{
		foreach (['panelr_product_id', 'panelr_email', 'panelr_intent', 'panelr_activation_id', 'panelr_username', 'panelr_first_name', 'panelr_last_name', 'panelr_ref', 'panelr_token'] as $v) {
			$vars[] = $v;
		}
		return $vars;
	}

	// ── POST handling before any output ──────────────────────────────────

	public static function handle_posts(): void
	{
		if (sanitize_key(wp_unslash($_SERVER['REQUEST_METHOD'] ?? '')) !== 'post') return;

		// Bot hand-off: "Continue to checkout".
		if (isset($_POST['panelr_handoff_nonce'])) {
			if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['panelr_handoff_nonce'])), 'panelr_handoff')) {
				wc_add_notice(__('That link has expired. Please go back and try again.', 'panelr-for-woocommerce'), 'error');
				return;
			}
			$error = self::handoff_to_cart([
				'product_id'    => absint(wp_unslash($_POST['panelr_product_id'] ?? 0)),
				'email'         => sanitize_email(wp_unslash($_POST['panelr_email'] ?? '')),
				'intent'        => sanitize_key(wp_unslash($_POST['panelr_intent'] ?? 'new_activation')),
				'activation_id' => absint(wp_unslash($_POST['panelr_activation_id'] ?? 0)),
				'username'      => sanitize_text_field(wp_unslash($_POST['panelr_username'] ?? '')),
				'first_name'    => sanitize_text_field(wp_unslash($_POST['panelr_first_name'] ?? '')),
				'last_name'     => sanitize_text_field(wp_unslash($_POST['panelr_last_name'] ?? '')),
			]);
			if ($error) {
				wc_add_notice($error, 'error');
				return;
			}
			wp_safe_redirect(wc_get_checkout_url());
			exit;
		}

		// Balance payment by card: a hidden product for the remainder.
		if (isset($_POST['panelr_balance_nonce'])) {
			if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['panelr_balance_nonce'])), 'panelr_balance_pay')) {
				return;
			}
			$ref    = sanitize_text_field(wp_unslash($_POST['panelr_balance_ref'] ?? ''));
			$token  = sanitize_text_field(wp_unslash($_POST['panelr_balance_token'] ?? ''));
			$email  = sanitize_email(wp_unslash($_POST['panelr_balance_email'] ?? ''));
			if (!$ref || (!$token && !$email)) return;

			// The amount comes from Panelr, never from the form.
			$result = Panelr_API::instance()->get_work_order($ref, $token ?: null, $token ? null : $email);
			if (!$result['ok']) {
				wc_add_notice($result['error'], 'error');
				return;
			}
			$o      = $result['data'];
			$amount = (float) (!empty($o['has_partials']) ? $o['balance_due'] : $o['order_total']);
			if ($amount <= 0 || ($o['status'] ?? '') !== 'pending_payment') return;

			if (WC()->session && !WC()->session->has_session()) {
				WC()->session->set_customer_session_cookie(true);
			}
			WC()->cart->empty_cart();
			WC()->cart->add_to_cart(self::get_or_create_balance_product(), 1, 0, [], [
				'_panelr_intent'         => 'balance_payment',
				'_panelr_reference_code' => $ref,
				'_panelr_balance_amount' => $amount,
				'_panelr_token'          => $token,
				'_panelr_email'          => $email ?: (string) ($o['customer_email'] ?? ''),
			]);
			if (WC()->customer && !empty($o['customer_email'])) {
				WC()->customer->set_billing_email((string) $o['customer_email']);
			}
			wp_safe_redirect(wc_get_checkout_url());
			exit;
		}
	}

	// ── Render ────────────────────────────────────────────────────────────

	public static function render($atts = []): string
	{
		wp_enqueue_script('panelr-common');

		// Bot hand-off → confirm page (no state change on GET).
		// The links below arrive from Panelr's emails and bots: they read, they never change anything.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$product_id = absint(get_query_var('panelr_product_id', absint(wp_unslash($_GET['panelr_product_id'] ?? 0))));
		$email      = sanitize_email(wp_unslash($_GET['panelr_email'] ?? ''));
		if ($product_id && $email) {
			return self::render_handoff_confirm($product_id, $email);
		}

		$ref   = sanitize_text_field(wp_unslash($_GET['panelr_ref'] ?? ''));
		$token = sanitize_text_field(wp_unslash($_GET['panelr_token'] ?? ''));
		if ($ref && $token) {
			return self::render_lookup($ref, $token, null);
		}
		// A signed-in member following "Pay now" from their own orders.
		$member_email = Panelr_Session::email() ?: (string) (Panelr_Session::line_session()['email'] ?? '');
		if ($ref && $member_email && strtolower(sanitize_email(wp_unslash($_GET['panelr_email'] ?? ''))) === strtolower($member_email)) {
			return self::render_lookup($ref, null, $member_email);
		}

		if (isset($_POST['panelr_lookup_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['panelr_lookup_nonce'])), 'panelr_order_lookup')) {
			$lookup_ref   = sanitize_text_field(wp_unslash($_POST['panelr_ref'] ?? ''));
			$lookup_email = sanitize_email(wp_unslash($_POST['panelr_email'] ?? ''));
			if ($lookup_ref && $lookup_email) {
				if (!Panelr_Helpers::rate_limit('order_lookup', 20, 15 * MINUTE_IN_SECONDS)) {
					return Panelr_Template::render('order-status/lookup-form', ['error' => __('Too many tries. Please wait a few minutes.', 'panelr-for-woocommerce')]);
				}
				return self::render_lookup($lookup_ref, null, $lookup_email);
			}
		}

		return Panelr_Template::render('order-status/lookup-form', ['error' => '']);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	private static function render_lookup(string $ref, ?string $token, ?string $email): string
	{
		$result = Panelr_API::instance()->get_work_order($ref, $token, $email);
		if (!$result['ok']) {
			return Panelr_Template::render('order-status/lookup-form', ['error' => $result['status'] === 404 ? __('We could not find an order with that reference and email.', 'panelr-for-woocommerce') : $result['error']]);
		}
		$o = $result['data'];
		$pm = $o['payment_method'] ?? null;
		$status = (string) ($o['status'] ?? 'pending');
		$manual = $pm && empty($pm['is_automated']) && empty($o['credits_paid']);

		if ($status === 'pending_payment' && $manual) {
			wp_register_script('panelr-qrcode', PANELR_PLUGIN_URL . 'assets/js/qrcode.min.js', [], '1.0.0', true);
			wp_enqueue_script('panelr-order-status', PANELR_PLUGIN_URL . 'assets/js/order-status.js', ['jquery', 'panelr-common', 'panelr-qrcode'], PANELR_VERSION, true);
			wp_localize_script('panelr-order-status', 'panelrOrderStatus', [
				'ajaxurl' => admin_url('admin-ajax.php'),
				'nonce'   => wp_create_nonce('panelr_order_status_submit'),
				'ref'     => $ref,
				'token'   => $token ?? '',
				'email'   => $email ?? (string) ($o['customer_email'] ?? ''),
				'i18n'    => [
					'enter_txid'     => __('Enter the transaction id or reference from your payment.', 'panelr-for-woocommerce'),
					'bad_amount'     => __('Enter the amount you sent.', 'panelr-for-woocommerce'),
					'submitting'     => __('Sending…', 'panelr-for-woocommerce'),
					'request_failed' => __('Something went wrong. Please try again.', 'panelr-for-woocommerce'),
				],
			]);
		}

		$instructions = null;
		if ($pm && $manual) {
			$instructions = Panelr_Checkout::instructions([
				'processor'    => $pm['processor'] ?? '',
				'instructions' => $pm['instructions'] ?? '',
				'config'       => $pm['config'] ?? [],
			], $o, null);
		}

		return Panelr_Template::render('order-status/order', [
			'order'        => $o,
			'status'       => $status,
			'ref'          => $ref,
			'token'        => $token,
			'email'        => $email ?? (string) ($o['customer_email'] ?? ''),
			'pm'           => $pm,
			'manual'       => $manual,
			'instructions' => $instructions,
			'page_url'     => Panelr_Helpers::page_url('order_status') ?: home_url('/'),
			'multi'        => Panelr_Helpers::multi_service(),
		]);
	}

	// ── Bot hand-off ──────────────────────────────────────────────────────

	private static function render_handoff_confirm(int $panelr_product_id, string $email): string
	{
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- a link from Panelr's bot; shown, then confirmed by a nonce-checked POST
		$intent        = sanitize_key(wp_unslash($_GET['panelr_intent'] ?? 'new_activation'));
		$activation_id = absint(wp_unslash($_GET['panelr_activation_id'] ?? 0));
		$username      = sanitize_text_field(wp_unslash($_GET['panelr_username'] ?? ''));
		$first_name    = sanitize_text_field(wp_unslash($_GET['panelr_first_name'] ?? ''));
		$last_name     = sanitize_text_field(wp_unslash($_GET['panelr_last_name'] ?? ''));
		if (!in_array($intent, ['new_activation', 'renewal', 'trial_upgrade'], true)) {
			$intent = 'new_activation';
		}

		$row = Panelr_Helpers::product_row_by_panelr_id($panelr_product_id);
		if (!$row || $row['status'] !== 'publish') {
			return Panelr_Template::notice(__('That plan is not available right now. Please contact support.', 'panelr-for-woocommerce'));
		}

		// A renewal must be the customer's own line: they sign in first.
		$needs_sign_in = in_array($intent, ['renewal', 'trial_upgrade'], true)
			&& !Panelr_Session::owns_line($activation_id);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return Panelr_Template::render('order-status/handoff-confirm', [
			'product'       => $row,
			'service_name'  => Panelr_Helpers::service_name($row['plugin_id']),
			'email'         => $email,
			'first_name'    => $first_name,
			'last_name'     => $last_name,
			'intent'        => $intent,
			'activation_id' => $activation_id,
			'username'      => $username,
			'needs_sign_in' => $needs_sign_in,
			'sign_in_url'   => Panelr_Helpers::portal_url(['return' => rawurlencode(home_url(esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'] ?? '/'))))]),
			'multi'         => Panelr_Helpers::multi_service(),
		]);
	}

	/** Validate with Panelr and load the cart. Returns an error message, or '' on success. */
	private static function handoff_to_cart(array $h): string
	{
		if (!in_array($h['intent'], ['new_activation', 'renewal', 'trial_upgrade'], true)) {
			$h['intent'] = 'new_activation';
		}
		$row = Panelr_Helpers::product_row_by_panelr_id($h['product_id']);
		if (!$row || $row['status'] !== 'publish') {
			return __('That plan is not available right now. Please contact support.', 'panelr-for-woocommerce');
		}
		if (!$h['email'] || !is_email($h['email'])) {
			return __('That link is missing an email address.', 'panelr-for-woocommerce');
		}

		$cart_data = ['_panelr_intent' => $h['intent'], '_panelr_plugin_id' => $row['plugin_id']];

		if (in_array($h['intent'], ['renewal', 'trial_upgrade'], true)) {
			if (!$h['activation_id'] || !Panelr_Session::owns_line($h['activation_id'])) {
				return __('Sign in first so we can renew the right connection.', 'panelr-for-woocommerce');
			}
			// Ask Panelr the line and plan belong together.
			$line = Panelr_Session::line($h['activation_id']) ?: Panelr_Session::line_session();
			$line_plugin = (int) ($line['plugin_id'] ?? 0);
			if ($line_plugin && $row['plugin_id'] && $line_plugin !== $row['plugin_id']) {
				return __('That plan is on a different service than the connection.', 'panelr-for-woocommerce');
			}
			$cart_data['_panelr_activation_id'] = $h['activation_id'];
			$cart_data['_panelr_line_label']    = (string) ($line['label'] ?? $line['username'] ?? $h['username']);
		}

		if (WC()->session && !WC()->session->has_session()) {
			WC()->session->set_customer_session_cookie(true);
		}
		if (WC()->customer) {
			WC()->customer->set_billing_email($h['email']);
			if ($h['first_name']) WC()->customer->set_billing_first_name($h['first_name']);
			if ($h['last_name'])  WC()->customer->set_billing_last_name($h['last_name']);
			WC()->customer->save();
		}

		WC()->cart->empty_cart();
		$key = WC()->cart->add_to_cart($row['wc_id'], 1, 0, [], $cart_data);
		if (!$key) {
			return __('That plan could not be added to the cart. Please contact support.', 'panelr-for-woocommerce');
		}
		return '';
	}

	// ── Balance-due product (v1 flow, kept) ───────────────────────────────

	public static function get_or_create_balance_product(): int
	{
		$existing = (int) get_option('panelr_balance_product_id', 0);
		if ($existing && get_post_status($existing) !== false) {
			return $existing;
		}
		$product = new WC_Product_Simple();
		$product->set_name(__('Balance due', 'panelr-for-woocommerce'));
		$product->set_status('private');
		$product->set_virtual(true);
		$product->set_sold_individually(true);
		$product->set_regular_price('0');
		$product->set_catalog_visibility('hidden');
		$product->save();
		update_option('panelr_balance_product_id', $product->get_id());
		return $product->get_id();
	}

	// ── AJAX: "I've paid" on the order status page ────────────────────────

	public static function ajax_submit_payment(): void
	{
		check_ajax_referer('panelr_order_status_submit', 'nonce');

		$ref    = sanitize_text_field(wp_unslash($_POST['ref'] ?? ''));
		$token  = sanitize_text_field(wp_unslash($_POST['token'] ?? ''));
		$email  = sanitize_email(wp_unslash($_POST['email'] ?? ''));
		$txid   = sanitize_text_field(wp_unslash($_POST['transaction_id'] ?? ''));
		$note   = sanitize_textarea_field(wp_unslash($_POST['customer_note'] ?? ''));
		$amount = isset($_POST['amount']) && $_POST['amount'] !== '' ? (float) wc_format_decimal(sanitize_text_field(wp_unslash($_POST['amount']))) : null;

		if (!$ref || (!$token && !$email)) {
			wp_send_json_error(['message' => __('That order could not be found.', 'panelr-for-woocommerce')]);
		}
		if (!Panelr_Helpers::rate_limit('submit_payment', 20, 15 * MINUTE_IN_SECONDS)) {
			wp_send_json_error(['message' => __('Too many tries. Please wait a few minutes.', 'panelr-for-woocommerce')]);
		}

		$payload = ['payment_transaction_id' => $txid, 'customer_note' => $note];
		if ($token) {
			$payload['confirmation_token'] = $token;
		} else {
			$payload['reference_code'] = $ref;
			$payload['customer_email'] = $email;
		}
		if ($amount !== null && $amount > 0) $payload['amount'] = $amount;

		$result = Panelr_API::instance()->submit_payment($payload);
		if (!$result['ok']) {
			wp_send_json_error(['message' => $result['error']]);
		}

		$d = $result['data'] ?? [];
		$wc_order = Panelr_Orders::order_by_reference($ref);
		if ($wc_order) {
			$wc_order->update_meta_data('_panelr_payment_submitted', '1');
			$wc_order->update_meta_data('_panelr_status', 'payment_submitted');
			if ($txid) $wc_order->update_meta_data('_panelr_transaction_id', $txid);
			$wc_order->add_order_note(sprintf(
				/* translators: %s: transaction id */
				__('Customer confirmed payment on the order status page (%s).', 'panelr-for-woocommerce'),
				$txid ?: '—'
			));
			$wc_order->save();
			Panelr_Orders::schedule_polling($wc_order->get_id());
		}
		wp_send_json_success([
			'message'     => __('Payment confirmation received. We will check it and set up your service shortly.', 'panelr-for-woocommerce'),
			'fully_paid'  => !empty($d['fully_paid']),
			'balance_due' => isset($d['balance_due']) ? (float) $d['balance_due'] : null,
		]);
	}

	// ── Balance paid by card through WooCommerce ──────────────────────────

	public static function handle_balance_payment_complete($order_id): void
	{
		$order = wc_get_order((int) $order_id);
		if (!$order || $order->get_meta('_panelr_balance_payment') !== '1') return;
		if ($order->get_meta('_panelr_balance_recorded') === '1') return;

		$ref = (string) $order->get_meta('_panelr_reference_code');
		if (!$ref) return;

		$amount = (float) $order->get_total();
		$txid   = $order->get_transaction_id() ?: 'WC-' . $order->get_id();
		$result = Panelr_API::instance()->submit_payment([
			'reference_code'         => $ref,
			'customer_email'         => Panelr_Helpers::order_account_email($order),
			'payment_transaction_id' => $txid,
			'amount'                 => $amount,
			'customer_note'          => 'WooCommerce order #' . $order->get_id(),
		]);

		if ($result['ok']) {
			$order->update_meta_data('_panelr_balance_recorded', '1');
			$order->add_order_note(sprintf(
				/* translators: 1: formatted price, 2: Panelr reference */
				__('Balance payment of %1$s recorded on Panelr order %2$s.', 'panelr-for-woocommerce'),
				wp_strip_all_tags(wc_price($amount)),
				$ref
			));
		} else {
			$order->add_order_note(sprintf(
				/* translators: 1: Panelr reference, 2: error */
				__('Could not record the balance payment on Panelr order %1$s: %2$s', 'panelr-for-woocommerce'),
				$ref,
				$result['error']
			));
		}
		$order->save();
	}
}
