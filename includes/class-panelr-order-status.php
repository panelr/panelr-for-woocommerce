<?php
defined('ABSPATH') || exit;

class Panelr_Order_Status
{

	public static function register_query_vars(array $vars): array
	{
		$vars[] = 'panelr_product_id';
		$vars[] = 'panelr_email';
		$vars[] = 'panelr_intent';
		$vars[] = 'panelr_activation_id';
		$vars[] = 'panelr_username';
		$vars[] = 'panelr_first_name';
		$vars[] = 'panelr_last_name';
		$vars[] = 'panelr_ref';
		$vars[] = 'panelr_token';
		return $vars;
	}

	public static function init(): void
	{
		add_shortcode('panelr_order_status',                     [__CLASS__, 'render']);
		add_filter('query_vars',                                 [__CLASS__, 'register_query_vars']);
		add_action('wp_ajax_panelr_order_status_submit',        [__CLASS__, 'ajax_submit_payment']);
		add_action('wp_ajax_nopriv_panelr_order_status_submit', [__CLASS__, 'ajax_submit_payment']);
		add_action('woocommerce_payment_complete',              [__CLASS__, 'handle_balance_payment_complete']);
	}

	// ── Render shortcode ──────────────────────────────────────────────────────

	public static function render(): string
	{
		// ── Part 1: Bot checkout redirect ─────────────────────────────────────
		$product_id    = (int)    sanitize_text_field(wp_unslash($_GET['panelr_product_id']    ?? ''));
		$email         =          sanitize_email(wp_unslash($_GET['panelr_email']              ?? ''));
		$intent        =          sanitize_key(wp_unslash($_GET['panelr_intent']               ?? 'new_activation'));
		$activation_id = (int)    sanitize_text_field(wp_unslash($_GET['panelr_activation_id'] ?? ''));
		$username      =          sanitize_text_field(wp_unslash($_GET['panelr_username']       ?? ''));
		$first_name    =          sanitize_text_field(wp_unslash($_GET['panelr_first_name']     ?? ''));
		$last_name     =          sanitize_text_field(wp_unslash($_GET['panelr_last_name']      ?? ''));

		if ($product_id && $email) {
			return self::handle_bot_checkout($product_id, $email, $intent, $activation_id, $username, $first_name, $last_name);
		}

		// ── Part 2: Order status display ──────────────────────────────────────
		$ref   = sanitize_text_field(wp_unslash($_GET['panelr_ref']   ?? ''));
		$token = sanitize_text_field(wp_unslash($_GET['panelr_token'] ?? ''));

		if ($ref && $token) {
			return self::handle_order_lookup($ref, $token, null);
		}

		// ── Lookup form — ref + email (POST) ───────────────────────────────────
		if (
			isset($_POST['panelr_lookup_nonce']) &&
			wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['panelr_lookup_nonce'])), 'panelr_order_lookup')
		) {
			$lookup_ref   = sanitize_text_field(wp_unslash($_POST['panelr_ref']   ?? ''));
			$lookup_email = sanitize_email(wp_unslash($_POST['panelr_email']      ?? ''));

			if ($lookup_ref && $lookup_email) {
				return self::handle_order_lookup($lookup_ref, null, $lookup_email);
			}
		}

		return self::render_lookup_form();
	}

	// ── Bot checkout ───────────────────────────────────────────────────────────

	private static function handle_bot_checkout(
		int    $panelr_product_id,
		string $email,
		string $intent,
		int    $activation_id,
		string $username,
		string $first_name,
		string $last_name
	): string {
		if (!in_array($intent, ['new_activation', 'renewal', 'trial_upgrade'], true)) {
			$intent = 'new_activation';
		}

		$posts = get_posts([
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'meta_key'       => '_panelr_product_id',
			'meta_value'     => $panelr_product_id,
			'fields'         => 'ids',
		]);

		if (empty($posts)) {
			return '<div class="woocommerce-error">' . esc_html__('Product not found. Please contact support.', 'panelr-for-woocommerce') . '</div>';
		}

		if (WC()->session && !WC()->session->has_session()) {
			WC()->session->set_customer_session_cookie(true);
		}

		if (WC()->customer) {
			if ($email)      WC()->customer->set_billing_email($email);
			if ($first_name) WC()->customer->set_billing_first_name($first_name);
			if ($last_name)  WC()->customer->set_billing_last_name($last_name);
		}

		$cart_item_data = ['_panelr_intent' => $intent];
		if ($activation_id) $cart_item_data['_panelr_activation_id'] = $activation_id;
		if ($username)      $cart_item_data['_panelr_username']       = $username;

		WC()->cart->empty_cart();
		$key = WC()->cart->add_to_cart((int) $posts[0], 1, 0, [], $cart_item_data);

		if (!$key) {
			return '<div class="woocommerce-error">' . esc_html__('Could not add product to cart. Please contact support.', 'panelr-for-woocommerce') . '</div>';
		}

		wp_safe_redirect(wc_get_checkout_url());
		exit;
	}

	// ── Order lookup ───────────────────────────────────────────────────────────

	private static function handle_order_lookup(string $ref, ?string $token, ?string $email): string
	{
		$api    = new Panelr_API();
		$params = ['ref' => $ref];
		if ($token) $params['token'] = $token;
		if ($email) $params['email'] = $email;

		$result = $api->get_work_order($params);

		if (empty($result['success'])) {
			$msg = $result['error'] ?? 'Order not found.';
			return self::render_lookup_form($msg);
		}

		$order = $result['data'];
		return self::render_order_status($order, $token, $email);
	}

	// ── Render order status ────────────────────────────────────────────────────

	private static function render_order_status(array $order, ?string $token, ?string $email): string
	{
		$status     = $order['status']         ?? 'pending';
		$ref        = $order['reference_code'] ?? '';
		$cust_name  = $order['customer_name']  ?? '';
		$cust_email = $order['customer_email'] ?? '';
		$currency   = $order['currency']       ?? 'USD';
		$balance    = (float) ($order['balance_due']  ?? 0);
		$total      = (float) ($order['order_total']  ?? 0);
		$has_parts  = !empty($order['has_partials']);
		$pm         = $order['payment_method'] ?? null;
		$cart       = $order['cart']           ?? [];
		$adj        = $order['adjustment']     ?? null;
		$history    = $order['payment_history'] ?? [];
		$nonce      = wp_create_nonce('panelr_order_status_submit');
		$page_url   = get_permalink(get_option('panelr_page_order_status', 0)) ?: home_url('/');
		$amount_due = $has_parts ? $balance : $total;

		$display_username = '';
		foreach ($cart as $item) {
			if (!empty($item['editor_username'])) {
				$display_username = $item['editor_username'];
				break;
			} elseif (!empty($item['linked_username'])) {
				$display_username = $item['linked_username'];
			}
		}

		ob_start();
?>
		<div class="panelr-order-status" id="panelr-os-wrap">

			<?php if ($status === 'pending_payment'): ?>

				<h2><?php esc_html_e('Complete Your Payment', 'panelr-for-woocommerce'); ?></h2>

				<?php if ($cust_name || $cust_email || $display_username): ?>
					<div class="panelr-portal__section" style="margin-bottom:16px;">
						<table class="panelr-portal__table">
							<?php if ($cust_name): ?>
								<tr>
									<th><?php esc_html_e('Name', 'panelr-for-woocommerce'); ?></th>
									<td><?php echo esc_html($cust_name); ?></td>
								</tr>
							<?php endif; ?>
							<?php if ($cust_email): ?>
								<tr>
									<th><?php esc_html_e('Email', 'panelr-for-woocommerce'); ?></th>
									<td><?php echo esc_html($cust_email); ?></td>
								</tr>
							<?php endif; ?>
							<?php if ($display_username): ?>
								<tr>
									<th><?php esc_html_e('Account', 'panelr-for-woocommerce'); ?></th>
									<td><code class="panelr-portal__code"><?php echo esc_html($display_username); ?></code></td>
								</tr>
							<?php endif; ?>
						</table>
					</div>
				<?php endif; ?>

				<div class="panelr-reference-box">
					<p class="panelr-reference-label"><?php esc_html_e('Your Order Reference', 'panelr-for-woocommerce'); ?></p>
					<p class="panelr-reference-code"><?php echo esc_html($ref); ?></p>
					<p class="panelr-reference-note"><?php esc_html_e('Include this in your payment note / memo.', 'panelr-for-woocommerce'); ?></p>
				</div>

				<?php if (!empty($cart)): ?>
					<div class="panelr-os-summary">
						<table class="panelr-portal__table">
							<thead>
								<tr>
									<th><?php esc_html_e('Item', 'panelr-for-woocommerce'); ?></th>
									<th><?php esc_html_e('Qty', 'panelr-for-woocommerce'); ?></th>
									<th><?php esc_html_e('Price', 'panelr-for-woocommerce'); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($cart as $item): ?>
									<tr>
										<td><?php echo esc_html($item['name'] ?? ''); ?></td>
										<td>&times;<?php echo (int) ($item['qty'] ?? 1); ?></td>
										<td><?php echo wp_kses_post(wc_price((float) $item['price'] * (int) ($item['qty'] ?? 1), ['currency' => $currency])); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
							<tfoot>
								<?php if ($adj): ?>
									<tr>
										<td colspan="2"><?php esc_html_e('Subtotal', 'panelr-for-woocommerce'); ?></td>
										<td><?php echo wp_kses_post(wc_price($order['snap_total'] ?? $total, ['currency' => $currency])); ?></td>
									</tr>
									<tr>
										<td colspan="2" style="color:<?php echo $adj['direction'] === 'fee' ? 'var(--panelr-error, #dc2626)' : 'var(--panelr-success, #16a34a)'; ?>">
											<?php echo esc_html($adj['label'] ?? ucfirst($adj['direction'])); ?>
										</td>
										<td style="color:<?php echo $adj['direction'] === 'fee' ? 'var(--panelr-error, #dc2626)' : 'var(--panelr-success, #16a34a)'; ?>">
											<?php echo $adj['direction'] === 'fee' ? '+' : '&minus;'; ?><?php echo wp_kses_post(wc_price((float) $adj['amount'], ['currency' => $currency])); ?>
										</td>
									</tr>
								<?php endif; ?>
								<tr>
									<td colspan="2"><strong><?php esc_html_e('Order Total', 'panelr-for-woocommerce'); ?></strong></td>
									<td><strong><?php echo wp_kses_post(wc_price($total, ['currency' => $currency])); ?></strong></td>
								</tr>
								<?php if ($has_parts): ?>
									<?php foreach ($history as $ph): ?>
										<tr>
											<td colspan="2" style="color:var(--panelr-text-muted, #64748b);">
												<?php echo esc_html__('Payment', 'panelr-for-woocommerce') . ' ' . esc_html(date_i18n(get_option('date_format'), strtotime($ph['at'] ?? 'now'))); ?>
											</td>
											<td style="color:var(--panelr-text-muted, #64748b);">&minus;<?php echo wp_kses_post(wc_price((float) $ph['amount'], ['currency' => $currency])); ?></td>
										</tr>
									<?php endforeach; ?>
									<tr>
										<td colspan="2"><strong><?php esc_html_e('Balance Due', 'panelr-for-woocommerce'); ?></strong></td>
										<td><strong style="color:var(--panelr-accent, #6366f1);"><?php echo wp_kses_post(wc_price($balance, ['currency' => $currency])); ?></strong></td>
									</tr>
								<?php endif; ?>
							</tfoot>
						</table>
					</div>
				<?php endif; ?>

				<?php if ($pm && $pm['is_automated']): ?>
					<p class="panelr-amount-due">
						<?php esc_html_e('Amount Due:', 'panelr-for-woocommerce'); ?>
						<span class="panelr-amount"><?php echo wp_kses_post(wc_price($amount_due, ['currency' => $currency])); ?></span>
					</p>
					<form method="POST" action="<?php echo esc_url($page_url); ?>">
						<?php wp_nonce_field('panelr_balance_pay', 'panelr_balance_nonce'); ?>
						<input type="hidden" name="panelr_balance_ref" value="<?php echo esc_attr($ref); ?>">
						<input type="hidden" name="panelr_balance_amount" value="<?php echo esc_attr($has_parts ? $balance : $total); ?>">
						<input type="hidden" name="panelr_balance_token" value="<?php echo esc_attr($token ?? ''); ?>">
						<input type="hidden" name="panelr_balance_email" value="<?php echo esc_attr($email ?? $cust_email); ?>">
						<button type="submit" class="button panelr-renew-btn">
							<?php
							printf(
								wp_kses_post(
									/* translators: %1$s: formatted price, %2$s: payment method name */
									__('Pay %1$s via %2$s', 'panelr-for-woocommerce')
								),
								wp_kses_post(wc_price($amount_due, ['currency' => $currency])),
								esc_html($pm['name'] ?? '')
							);
							?>
						</button>
					</form>
				<?php else: ?>
					<?php if ($pm && !empty($pm['instructions'])): ?>
						<div class="panelr-payment-instructions">
							<p class="panelr-instructions-label"><?php esc_html_e('Payment Instructions', 'panelr-for-woocommerce'); ?> &mdash; <?php echo esc_html($pm['name']); ?></p>
							<p><?php echo esc_html($pm['instructions']); ?></p>
						</div>
					<?php endif; ?>

					<p class="panelr-amount-due">
						<?php esc_html_e('Amount Due:', 'panelr-for-woocommerce'); ?>
						<span class="panelr-amount"><?php echo wp_kses_post(wc_price($amount_due, ['currency' => $currency])); ?></span>
					</p>

					<div id="panelr-os-payment-form">
						<p id="panelr-os-error" class="panelr-portal__error" style="display:none;"></p>
						<p class="panelr-portal__field">
							<label for="panelr-os-txid"><strong><?php esc_html_e('Transaction ID / Reference Number', 'panelr-for-woocommerce'); ?></strong></label>
							<input type="text" id="panelr-os-txid" class="input-text" placeholder="<?php esc_attr_e('Enter your transaction ID', 'panelr-for-woocommerce'); ?>">
						</p>
						<p class="panelr-portal__field">
							<label for="panelr-os-note"><?php esc_html_e('Note (optional)', 'panelr-for-woocommerce'); ?></label>
							<textarea id="panelr-os-note" rows="2" class="input-text"></textarea>
						</p>
						<p>
							<button type="button" id="panelr-os-submit" class="button panelr-renew-btn">
								<?php esc_html_e('Confirm Payment', 'panelr-for-woocommerce'); ?> &rarr;
							</button>
							<span id="panelr-os-result" class="panelr-submit-result"></span>
						</p>
					</div>

					<div id="panelr-os-success" class="panelr-payment-success" style="display:none;">
						<?php esc_html_e('Payment confirmation received. Our team will verify and activate your service shortly.', 'panelr-for-woocommerce'); ?>
					</div>
				<?php endif; ?>

			<?php elseif ($status === 'payment_submitted'): ?>

				<div class="panelr-payment-success">
					<h3><?php esc_html_e('Payment Received', 'panelr-for-woocommerce'); ?></h3>
					<p><?php
						printf(
							wp_kses_post(
								/* translators: %1$s: order reference code in bold */
								__('Your payment confirmation for order %1$s has been received. Our team will verify and activate your service shortly.', 'panelr-for-woocommerce')
							),
							'<strong>' . esc_html($ref) . '</strong>'
						);
						?></p>
				</div>

			<?php elseif ($status === 'awaiting_webhook'): ?>

				<div class="panelr-payment-success">
					<h3><?php esc_html_e('Payment Processing', 'panelr-for-woocommerce'); ?></h3>
					<p><?php
						printf(
							wp_kses_post(
								/* translators: %1$s: order reference code in bold */
								__('Payment for order %1$s is being processed. Your service will be activated automatically once confirmed.', 'panelr-for-woocommerce')
							),
							'<strong>' . esc_html($ref) . '</strong>'
						);
						?></p>
				</div>

			<?php elseif (in_array($status, ['paid', 'completed', 'active'], true)): ?>

				<div class="panelr-payment-success">
					<h3><?php esc_html_e('Payment Confirmed!', 'panelr-for-woocommerce'); ?></h3>
					<p><?php
						printf(
							wp_kses_post(
								/* translators: %1$s: order reference code in bold, %2$s: customer email in bold */
								__('Your order %1$s is active. Check your email at %2$s for your connection details.', 'panelr-for-woocommerce')
							),
							'<strong>' . esc_html($ref) . '</strong>',
							'<strong>' . esc_html($cust_email) . '</strong>'
						);
						?></p>
				</div>

			<?php elseif ($status === 'payment_failed'): ?>

				<div class="panelr-portal__error">
					<h3><?php esc_html_e('Payment Failed', 'panelr-for-woocommerce'); ?></h3>
					<p><?php esc_html_e('We were unable to process your payment. Please contact support.', 'panelr-for-woocommerce'); ?></p>
				</div>

			<?php elseif (in_array($status, ['canceled', 'cancelled'], true)): ?>

				<div class="panelr-portal__error">
					<h3><?php esc_html_e('Order Cancelled', 'panelr-for-woocommerce'); ?></h3>
					<p><?php esc_html_e('This order has been cancelled. Please contact support if you have questions.', 'panelr-for-woocommerce'); ?></p>
				</div>

			<?php else: ?>

				<div class="woocommerce-info">
					<p><?php
						printf(
							wp_kses_post(
								/* translators: %1$s: order reference code, %2$s: order status */
								__('Order %1$s — Status: %2$s', 'panelr-for-woocommerce')
							),
							esc_html($ref),
							esc_html($status)
						);
						?></p>
				</div>

			<?php endif; ?>

		</div>

		<?php if ($status === 'pending_payment' && $pm && !$pm['is_automated']): ?>
			<script>
				jQuery(function($) {
					$('#panelr-os-submit').on('click', function() {
						var btn = $(this);
						var txid = $('#panelr-os-txid').val().trim();
						var note = $('#panelr-os-note').val().trim();
						var error = $('#panelr-os-error');
						var result = $('#panelr-os-result');

						if (!txid) {
							error.text('<?php echo esc_js(__('Please enter your transaction ID.', 'panelr-for-woocommerce')); ?>').show();
							return;
						}
						error.hide();
						btn.prop('disabled', true);
						result.text('<?php echo esc_js(__('Submitting…', 'panelr-for-woocommerce')); ?>');

						$.post('<?php echo esc_js(admin_url('admin-ajax.php')); ?>', {
								action: 'panelr_order_status_submit',
								nonce: '<?php echo esc_js($nonce); ?>',
								ref: '<?php echo esc_js($ref); ?>',
								token: '<?php echo esc_js($token ?? ''); ?>',
								email: '<?php echo esc_js($email ?? $cust_email); ?>',
								amount: '<?php echo esc_js($amount_due); ?>',
								transaction_id: txid,
								customer_note: note,
							})
							.done(function(res) {
								if (res.success) {
									$('#panelr-os-payment-form').hide();
									$('#panelr-os-success').show();
								} else {
									error.text(res.data.message || '<?php echo esc_js(__('An error occurred. Please try again.', 'panelr-for-woocommerce')); ?>').show();
									btn.prop('disabled', false);
									result.text('');
								}
							})
							.fail(function() {
								error.text('<?php echo esc_js(__('Request failed. Please try again.', 'panelr-for-woocommerce')); ?>').show();
								btn.prop('disabled', false);
								result.text('');
							});
					});
				});
			</script>
		<?php endif; ?>

		<?php
		// ── Balance pay — add custom cart item and redirect to WC checkout ────
		if (
			isset($_POST['panelr_balance_nonce']) &&
			wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['panelr_balance_nonce'])), 'panelr_balance_pay')
		) {
			$bal_ref    = sanitize_text_field(wp_unslash($_POST['panelr_balance_ref']    ?? ''));
			$bal_amount = (float) sanitize_text_field(wp_unslash($_POST['panelr_balance_amount'] ?? 0));
			$bal_token  = sanitize_text_field(wp_unslash($_POST['panelr_balance_token']  ?? ''));
			$bal_email  = sanitize_email(wp_unslash($_POST['panelr_balance_email']       ?? ''));

			if ($bal_amount > 0 && $bal_ref) {
				if (WC()->session && !WC()->session->has_session()) {
					WC()->session->set_customer_session_cookie(true);
				}
				WC()->cart->empty_cart();

				WC()->cart->add_to_cart(
					self::get_or_create_balance_product(),
					1,
					0,
					[],
					[
						'_panelr_intent'         => 'balance_payment',
						'_panelr_reference_code' => $bal_ref,
						'_panelr_balance_amount' => $bal_amount,
						'_panelr_token'          => $bal_token,
						'_panelr_email'          => $bal_email,
					]
				);

				wp_safe_redirect(wc_get_checkout_url());
				exit;
			}
		}

		return ob_get_clean();
	}

	// ── Get or create a hidden balance-due WC product ─────────────────────────

	private static function get_or_create_balance_product(): int
	{
		$existing = get_option('panelr_balance_product_id', 0);
		if ($existing && get_post_status($existing) !== false) {
			return (int) $existing;
		}

		$product = new WC_Product_Simple();
		$product->set_name(__('Balance Due', 'panelr-for-woocommerce'));
		$product->set_status('private');
		$product->set_virtual(true);
		$product->set_sold_individually(true);
		$product->set_regular_price('0');
		$product->set_catalog_visibility('hidden');
		$product->save();

		update_option('panelr_balance_product_id', $product->get_id());
		return $product->get_id();
	}

	// ── AJAX: submit manual payment confirmation ───────────────────────────────

	public static function ajax_submit_payment(): void
	{
		check_ajax_referer('panelr_order_status_submit', 'nonce');

		$ref            = sanitize_text_field(wp_unslash($_POST['ref']            ?? ''));
		$token          = sanitize_text_field(wp_unslash($_POST['token']          ?? ''));
		$email          = sanitize_email(wp_unslash($_POST['email']               ?? ''));
		$transaction_id = sanitize_text_field(wp_unslash($_POST['transaction_id'] ?? ''));
		$customer_note  = sanitize_textarea_field(wp_unslash($_POST['customer_note'] ?? ''));
		$amount         = !empty($_POST['amount']) ? (float) sanitize_text_field(wp_unslash($_POST['amount'])) : null;

		if (!$ref || (!$token && !$email)) {
			wp_send_json_error(['message' => __('Missing required fields.', 'panelr-for-woocommerce')]);
		}

		$payload = [
			'reference_code'         => $ref,
			'confirmation_token'     => $token ?: null,
			'customer_email'         => $email ?: null,
			'payment_transaction_id' => $transaction_id,
			'customer_note'          => $customer_note,
		];
		if ($amount !== null) $payload['amount'] = $amount;

		$api    = new Panelr_API();
		$result = $api->submit_payment_for_order($payload);

		if (!empty($result['success'])) {
			wp_send_json_success(['message' => __('Payment confirmation received.', 'panelr-for-woocommerce')]);
		} else {
			wp_send_json_error(['message' => $result['error'] ?? __('Could not submit payment. Please contact support.', 'panelr-for-woocommerce')]);
		}
	}

	// ── Handle balance payment completion (woocommerce_payment_complete) ──────

	public static function handle_balance_payment_complete(int $order_id): void
	{
		$order = wc_get_order($order_id);
		if (!$order) return;

		$ref = $order->get_meta('_panelr_reference_code');
		if (!$ref) return;

		$is_balance = false;
		foreach ($order->get_items() as $item) {
			if ($item->get_meta('_panelr_intent') === 'balance_payment') {
				$is_balance = true;
				break;
			}
		}
		if (!$is_balance) return;

		$amount = (float) $order->get_total();
		$txn_id = $order->get_transaction_id() ?: 'WC-' . $order_id;

		$api    = new Panelr_API();
		$result = $api->submit_payment_for_order([
			'reference_code'         => $ref,
			'payment_transaction_id' => $txn_id,
			'amount'                 => $amount,
			'customer_note'          => 'WooCommerce order #' . $order_id,
		]);

		if (!empty($result['success'])) {
			$order->add_order_note(
				sprintf(
					/* translators: %1$s: formatted price, %2$s: Panelr order reference code */
					__('Balance payment of %1$s applied to Panelr order %2$s.', 'panelr-for-woocommerce'),
					wc_price($amount),
					$ref
				)
			);
		} else {
			$order->add_order_note(
				sprintf(
					/* translators: %1$s: Panelr order reference code, %2$s: error message */
					__('Failed to record balance payment against Panelr order %1$s: %2$s', 'panelr-for-woocommerce'),
					$ref,
					$result['error'] ?? 'Unknown error'
				)
			);
		}
	}

	// ── Lookup form ───────────────────────────────────────────────────────────

	private static function render_lookup_form(string $error = ''): string
	{
		ob_start();
		?>
		<div class="panelr-order-status">
			<?php if ($error): ?>
				<div class="panelr-portal__error"><?php echo esc_html($error); ?></div>
			<?php endif; ?>
			<p><?php esc_html_e('Enter your order reference and email to view your order status.', 'panelr-for-woocommerce'); ?></p>
			<form method="POST">
				<?php wp_nonce_field('panelr_order_lookup', 'panelr_lookup_nonce'); ?>
				<p class="panelr-portal__field">
					<label for="panelr-lookup-ref"><?php esc_html_e('Order Reference', 'panelr-for-woocommerce'); ?></label>
					<input type="text" id="panelr-lookup-ref" name="panelr_ref" class="input-text" placeholder="WO-2026-XXXXX">
				</p>
				<p class="panelr-portal__field">
					<label for="panelr-lookup-email"><?php esc_html_e('Email Address', 'panelr-for-woocommerce'); ?></label>
					<input type="email" id="panelr-lookup-email" name="panelr_email" class="input-text">
				</p>
				<p>
					<button type="submit" class="button panelr-renew-btn"><?php esc_html_e('Look Up Order', 'panelr-for-woocommerce'); ?></button>
				</p>
			</form>
		</div>
<?php
		return ob_get_clean();
	}
}
