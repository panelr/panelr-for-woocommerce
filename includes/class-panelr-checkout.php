<?php
defined('ABSPATH') || exit;

class Panelr_Checkout
{

	public static function init(): void
	{
		add_filter('woocommerce_add_cart_item_data',          [__CLASS__, 'persist_cart_item_data'], 10, 2);
		add_filter('woocommerce_get_item_data',               [__CLASS__, 'display_cart_item_data'], 10, 2);
		add_action('woocommerce_checkout_create_order_line_item', [__CLASS__, 'save_order_item_meta'], 10, 4);
		add_action('woocommerce_payment_complete',           [__CLASS__, 'handle_automatic_payment']);
		add_action('woocommerce_thankyou',                   [__CLASS__, 'maybe_create_work_order'], 5);
		add_action('woocommerce_thankyou',                   [__CLASS__, 'render_thankyou'], 10);
		add_filter('woocommerce_order_item_get_formatted_meta_data', [__CLASS__, 'hide_panelr_meta_from_display'], 10, 2);
		add_filter('woocommerce_order_item_name',                    [__CLASS__, 'append_panelr_order_context'], 10, 2);
		add_action('wp_ajax_panelr_submit_payment',          [__CLASS__, 'ajax_submit_payment']);
		add_action('wp_ajax_nopriv_panelr_submit_payment',   [__CLASS__, 'ajax_submit_payment']);
	}

	// ── Hide internal Panelr meta from order display ─────────────────────

	public static function hide_panelr_meta_from_display(array $formatted_meta, $order_item): array
	{
		$intent_labels = [
			'renewal'       => __('Renewal', 'panelr-for-woocommerce'),
			'trial_upgrade' => __('Trial Upgrade', 'panelr-for-woocommerce'),
		];

		$intent        = null;
		$activation_id = null;

		foreach ($formatted_meta as $key => $meta) {
			if ($meta->key === '_panelr_intent') {
				$intent = $meta->value;
				unset($formatted_meta[$key]);
			} elseif ($meta->key === '_panelr_activation_id') {
				$activation_id = (int) $meta->value;
				unset($formatted_meta[$key]);
			}
		}

		if ($intent && $intent !== 'new_activation') {
			$label = $intent_labels[$intent] ?? ucfirst($intent);

			$formatted_meta[] = (object) [
				'key'           => 'panelr_order_type',
				'value'         => $label,
				'display_key'   => __('Order Type', 'panelr-for-woocommerce'),
				'display_value' => esc_html($label),
			];

			if ($activation_id) {
				$order       = $order_item->get_order();
				$editor_user = $order ? $order->get_meta('_panelr_editor_username') : '';
				$xtream_user = $order ? $order->get_meta('_panelr_xtream_username') : '';
				$display     = $editor_user ?: $xtream_user;

				if ($display) {
					$formatted_meta[] = (object) [
						'key'           => 'panelr_account',
						'value'         => $display,
						'display_key'   => __('Account', 'panelr-for-woocommerce'),
						'display_value' => esc_html($display),
					];
				}
			}
		}

		return $formatted_meta;
	}

	// ── Append order context to item name on order pages ─────────────────

	public static function append_panelr_order_context(string $name, $item): string
	{
		$intent        = $item->get_meta('_panelr_intent');
		$activation_id = (int) $item->get_meta('_panelr_activation_id');

		if (!$intent || $intent === 'new_activation') {
			return $name;
		}

		$intent_labels = [
			'renewal'       => __('Renewal', 'panelr-for-woocommerce'),
			'trial_upgrade' => __('Trial Upgrade', 'panelr-for-woocommerce'),
		];
		$label = $intent_labels[$intent] ?? ucfirst($intent);

		$order       = $item->get_order();
		$editor_user = $order ? $order->get_meta('_panelr_editor_username') : '';
		$xtream_user = $order ? $order->get_meta('_panelr_xtream_username') : '';
		$display     = $editor_user ?: $xtream_user;

		$context  = '<br><small class="panelr-order-context">';
		$context .= esc_html__('Order Type', 'panelr-for-woocommerce') . ': <strong>' . esc_html($label) . '</strong>';
		if ($display) {
			$context .= ' &mdash; ' . esc_html__('Account', 'panelr-for-woocommerce') . ': <strong>' . esc_html($display) . '</strong>';
		}
		$context .= '</small>';

		return $name . $context;
	}

	// ── Cart item data ────────────────────────────────────────────────────

	public static function persist_cart_item_data(array $cart_item_data, int $product_id): array
	{
		return $cart_item_data;
	}

	public static function display_cart_item_data(array $item_data, array $cart_item): array
	{
		$intent        = $cart_item['_panelr_intent']        ?? '';
		$activation_id = $cart_item['_panelr_activation_id'] ?? 0;

		if (!$intent || $intent === 'new_activation') {
			return $item_data;
		}

		$intent_labels = [
			'renewal'       => __('Renewal', 'panelr-for-woocommerce'),
			'trial_upgrade' => __('Trial Upgrade', 'panelr-for-woocommerce'),
		];

		$item_data[] = [
			'key'   => __('Order Type', 'panelr-for-woocommerce'),
			'value' => $intent_labels[$intent] ?? ucfirst($intent),
		];

		if ($activation_id) {
			$portal_username = WC()->session ? WC()->session->get('panelr_portal_username') : '';
			$portal_editor   = WC()->session ? WC()->session->get('panelr_portal_editor_username') : '';
			$display         = $portal_editor ?: $portal_username;
			if ($display) {
				$item_data[] = [
					'key'   => __('Account', 'panelr-for-woocommerce'),
					'value' => esc_html($display),
				];
			}
		}

		return $item_data;
	}

	public static function save_order_item_meta($item, string $cart_item_key, array $cart_item, WC_Order $order): void
	{
		if (!empty($cart_item['_panelr_intent'])) {
			$item->update_meta_data('_panelr_intent', $cart_item['_panelr_intent']);
		}
		if (!empty($cart_item['_panelr_activation_id'])) {
			$item->update_meta_data('_panelr_activation_id', (int) $cart_item['_panelr_activation_id']);
		}
		if (!empty($cart_item['_panelr_reference_code'])) {
			$item->update_meta_data('_panelr_intent',          'balance_payment');
			$item->update_meta_data('_panelr_reference_code',  $cart_item['_panelr_reference_code']);
			$order->update_meta_data('_panelr_reference_code', $cart_item['_panelr_reference_code']);
			if (!empty($cart_item['_panelr_balance_amount'])) {
				$item->set_subtotal($cart_item['_panelr_balance_amount']);
				$item->set_total($cart_item['_panelr_balance_amount']);
			}
		}
	}

	// ── Helpers ────────────────────────────────────────────────────────────

	private static function get_payment_map(): array
	{
		return json_decode((string) get_option('panelr_payment_map', '{}'), true) ?: [];
	}

	private static function get_panelr_method_id(string $gateway_id): ?int
	{
		$map = self::get_payment_map();
		return isset($map[$gateway_id]) ? (int) $map[$gateway_id] : null;
	}

	private static function is_manual_gateway(string $gateway_id): bool
	{
		$panelr_id = self::get_panelr_method_id($gateway_id);
		if (!$panelr_id) return false;

		$mode_map = get_option('panelr_payment_mode_map', []);
		if (is_array($mode_map) && isset($mode_map[$panelr_id])) {
			return $mode_map[$panelr_id] === 'manual';
		}

		$api    = new Panelr_API();
		$result = $api->get_payment_methods();
		if (empty($result['success']) || empty($result['data'])) {
			return true;
		}

		$mode_map = [];
		foreach ($result['data'] as $pm) {
			$mode_map[(int) $pm['id']] = $pm['mode'] ?? 'manual';
		}
		update_option('panelr_payment_mode_map', $mode_map);

		return ($mode_map[$panelr_id] ?? 'manual') === 'manual';
	}

	private static function build_items(WC_Order $order): array
	{
		$items = [];
		foreach ($order->get_items() as $item) {
			$product_id = $item->get_product_id();
			$panelr_id  = (int) get_post_meta($product_id, '_panelr_product_id', true);
			if (!$panelr_id) continue;

			$intent        = $item->get_meta('_panelr_intent') ?: 'new_activation';
			$activation_id = (int) $item->get_meta('_panelr_activation_id');

			$cart_item = [
				'product_id' => $panelr_id,
				'intent'     => $intent,
				'qty'        => (int) $item->get_quantity(),
			];

			if ($activation_id) {
				$cart_item['activation_id'] = $activation_id;
			}

			$items[] = $cart_item;
		}
		return $items;
	}

	// ── Build payment instructions from Panelr payment method ───────────────

	private static function build_instructions(array $pm, float $amount, string $ref): array
	{
		$config    = $pm['config'] ?? [];
		$processor = $pm['processor'] ?? '';
		$res = [
			'type'        => 'text',
			'link'        => null,
			'link_label'  => null,
			'lines'       => [],
			'note'        => $pm['instructions'] ?? '',
			'copy_items'  => [],
			'qr_data'     => null,
			'txid_label'  => 'Transaction ID',
		];

		$amt     = '$' . number_format($amount, 2);
		$raw_amt = number_format($amount, 2, '.', '');

		switch ($processor) {
			case 'venmo':
				$handle = ltrim($config['handle'] ?? '', '@');
				if ($handle) {
					$note     = urlencode("Order {$ref}");
					$deeplink = "https://venmo.com/u/{$handle}?txn=pay&amount={$raw_amt}&note={$note}";
					$res['type']       = 'link';
					$res['link']       = $deeplink;
					$res['link_label'] = "Pay @{$handle} via Venmo";
					$res['qr_data']    = $deeplink;
					$res['lines']      = [
						"Send exactly <strong>{$amt}</strong> to <strong>@{$handle}</strong>",
						"Note is pre-filled with your order reference",
					];
					$res['copy_items'][] = ['label' => 'Venmo Handle',     'value' => '@' . $handle];
					$res['copy_items'][] = ['label' => 'Note / Reference',  'value' => "Order {$ref}"];
					$res['txid_label']   = 'Venmo Transaction ID';
				}
				break;

			case 'cashapp':
				$tag = ltrim($config['cashtag'] ?? '', '$');
				if ($tag) {
					$deeplink          = "https://cash.app/\${$tag}/{$raw_amt}";
					$res['type']       = 'link';
					$res['link']       = $deeplink;
					$res['link_label'] = "Pay \${$tag} via Cash App";
					$res['qr_data']    = $deeplink;
					$res['lines']      = [
						"Send exactly <strong>{$amt}</strong> to <strong>\${$tag}</strong>",
						"Add reference <strong>{$ref}</strong> in the note field",
					];
					$res['copy_items'][] = ['label' => 'Cashtag',          'value' => '$' . $tag];
					$res['copy_items'][] = ['label' => 'Reference / Note', 'value' => $ref];
					$res['txid_label']   = 'Cash App Transaction ID';
				}
				break;

			case 'zelle':
				$recipient = $config['recipient'] ?? '';
				$res['lines'] = [
					"Send exactly <strong>{$amt}</strong> via Zelle to: <strong>{$recipient}</strong>",
					"Add reference <strong>{$ref}</strong> in the memo field",
				];
				if ($recipient) $res['copy_items'][] = ['label' => 'Zelle Recipient', 'value' => $recipient];
				$res['copy_items'][] = ['label' => 'Memo / Reference', 'value' => $ref];
				$res['txid_label']   = 'Reference number or last 4 of sender account';
				break;

			case 'paypal':
				$me_link = $config['me_link'] ?? '';
				$email   = $config['email']   ?? '';
				if ($me_link) {
					$res['type']       = 'link';
					$res['link']       = rtrim($me_link, '/') . "/{$raw_amt}";
					$res['link_label'] = 'Pay via PayPal.me';
					$res['lines']      = ["Add reference <strong>{$ref}</strong> in the payment note"];
				} elseif ($email) {
					$res['lines'] = [
						"Send <strong>{$amt}</strong> to <strong>{$email}</strong> via PayPal",
						"Add reference <strong>{$ref}</strong> in the note",
					];
					$res['copy_items'][] = ['label' => 'PayPal Email', 'value' => $email];
				}
				$res['copy_items'][] = ['label' => 'Note / Reference', 'value' => $ref];
				$res['txid_label']   = 'PayPal Transaction ID';
				break;

			default:
				$res['lines'] = ["Send <strong>{$amt}</strong> and include reference <strong>{$ref}</strong>"];
				break;
		}

		return $res;
	}

	// ── Calculate adjusted total ───────────────────────────────────────────

	private static function calc_adjusted_total(float $subtotal, array $pm): array
	{
		if (empty($pm['adjustment_enabled']) || !$pm['adjustment_direction'] || !$pm['adjustment_value']) {
			return ['total' => $subtotal, 'direction' => null, 'label' => null, 'amount' => null];
		}

		$direction = $pm['adjustment_direction'];
		$mode      = $pm['adjustment_mode'];
		$value     = (float) $pm['adjustment_value'];
		$adj_amt   = $mode === 'fixed' ? $value : round($subtotal * ($value / 100), 2);
		$label     = ($pm['display_label'] ?: $pm['name']) . ' ' . ucfirst($direction)
			. ' (' . ($mode === 'percent' ? $value . '%' : '$' . number_format($value, 2)) . ')';

		$total = $direction === 'fee'
			? round($subtotal + $adj_amt, 2)
			: max(0.0, round($subtotal - $adj_amt, 2));

		return [
			'total'     => $total,
			'direction' => $direction,
			'label'     => $label,
			'amount'    => $adj_amt,
		];
	}

	// ── Get full Panelr payment method by mapped ID ────────────────────────

	private static function get_panelr_payment_method(string $gateway_id): ?array
	{
		$panelr_id = self::get_panelr_method_id($gateway_id);
		if (!$panelr_id) return null;

		$api    = new Panelr_API();
		$result = $api->get_payment_methods();
		if (empty($result['success']) || empty($result['data'])) return null;

		foreach ($result['data'] as $pm) {
			if ((int) $pm['id'] === $panelr_id) return $pm;
		}
		return null;
	}

	// ── Manual payment — fires when order is created ───────────────────────

	public static function handle_manual_order(WC_Order $order): void
	{
		$gateway_id = $order->get_payment_method();

		wc_get_logger()->debug(
			'Panelr handle_manual_order fired. Order #' . $order->get_id() . ' gateway: ' . $gateway_id . ' is_manual: ' . (self::is_manual_gateway($gateway_id) ? 'yes' : 'no'),
			['source' => 'panelr']
		);

		if (!self::is_manual_gateway($gateway_id)) {
			return;
		}

		$items = self::build_items($order);
		if (empty($items)) return;

		$pm = self::get_panelr_payment_method($gateway_id);
		if (!$pm) {
			wc_get_logger()->error(
				'Panelr: could not load payment method for gateway ' . $gateway_id,
				['source' => 'panelr']
			);
			return;
		}

		$subtotal = (float) $order->get_subtotal();
		$adj      = self::calc_adjusted_total($subtotal, $pm);

		$api    = new Panelr_API();
		$result = $api->create_work_order([
			'customer_email'       => $order->get_billing_email(),
			'customer_name'        => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
			'customer_phone'       => $order->get_billing_phone(),
			'payment_method_id'    => (int) $pm['id'],
			'wc_order_id'          => (string) $order->get_id(),
			'status'               => 'pending_payment',
			'items'                => $items,
			'adjustment_direction' => $adj['direction'],
			'adjustment_label'     => $adj['label'],
			'adjustment_amount'    => $adj['amount'],
		]);

		if (empty($result['success'])) {
			wc_get_logger()->error(
				'Panelr create_work_order failed for order #' . $order->get_id() . ': ' . ($result['error'] ?? 'unknown'),
				['source' => 'panelr']
			);
			return;
		}

		$work_order_id      = $result['data']['work_order_id'];
		$reference_code     = $result['data']['reference_code'];
		$confirmation_token = $result['data']['confirmation_token'];

		$order->update_meta_data('_panelr_work_order_id',      $work_order_id);
		$order->update_meta_data('_panelr_reference_code',     $reference_code);
		$order->update_meta_data('_panelr_confirmation_token', $confirmation_token);
		$order->update_meta_data('_panelr_is_manual',          '1');
		$order->update_meta_data('_panelr_adjusted_total',     $adj['total']);

		$editor_user = WC()->session ? WC()->session->get('panelr_portal_editor_username') : '';
		$xtream_user = WC()->session ? WC()->session->get('panelr_portal_username') : '';
		if ($editor_user) $order->update_meta_data('_panelr_editor_username', $editor_user);
		if ($xtream_user) $order->update_meta_data('_panelr_xtream_username', $xtream_user);
		$order->save();

		$confirm_url = $order->get_checkout_order_received_url();
		$api->send_payment_instructions($work_order_id, $confirm_url);

		wc_get_logger()->debug(
			'Panelr work order #' . $work_order_id . ' created for WC order #' . $order->get_id(),
			['source' => 'panelr']
		);
	}

	// ── Automatic payment — fires after payment confirmed ─────────────────

	public static function handle_automatic_payment(int $order_id): void
	{
		$order      = wc_get_order($order_id);
		$gateway_id = $order->get_payment_method();

		if (self::is_manual_gateway($gateway_id)) {
			return;
		}

		if ($order->get_meta('_panelr_work_order_id')) {
			return;
		}

		$items = self::build_items($order);
		if (empty($items)) return;

		$panelr_pm_id = self::get_panelr_method_id($gateway_id);

		$api    = new Panelr_API();
		$result = $api->complete_order([
			'customer_email'         => $order->get_billing_email(),
			'customer_name'          => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
			'customer_phone'         => $order->get_billing_phone(),
			'payment_method_id'      => $panelr_pm_id,
			'payment_transaction_id' => $order->get_transaction_id(),
			'payment_amount'         => (float) $order->get_total(),
			'wc_order_id'            => (string) $order_id,
			'items'                  => $items,
		]);

		if (!empty($result['success'])) {
			$order->update_meta_data('_panelr_work_order_id',  $result['data']['work_order_id']);
			$order->update_meta_data('_panelr_reference_code', $result['data']['reference_code']);
			$editor_user = WC()->session ? WC()->session->get('panelr_portal_editor_username') : '';
			$xtream_user = WC()->session ? WC()->session->get('panelr_portal_username') : '';
			if ($editor_user) $order->update_meta_data('_panelr_editor_username', $editor_user);
			if ($xtream_user) $order->update_meta_data('_panelr_xtream_username', $xtream_user);
			$order->save();
		} else {
			wc_get_logger()->error(
				'Panelr complete_order failed for order #' . $order_id . ': ' . ($result['error'] ?? 'unknown'),
				['source' => 'panelr']
			);
		}
	}

	// ── Fallback: create work order on thank you page if not yet created ────

	public static function maybe_create_work_order(int $order_id): void
	{
		$order = wc_get_order($order_id);
		if (!$order) return;

		if ($order->get_meta('_panelr_work_order_id')) return;

		wc_get_logger()->debug(
			'Panelr maybe_create_work_order fired for order #' . $order_id . ' gateway: ' . $order->get_payment_method(),
			['source' => 'panelr']
		);

		self::handle_manual_order($order);
	}

	// ── Thank you page — show payment instructions for manual orders ───────

	public static function render_thankyou(int $order_id): void
	{
		$order = wc_get_order($order_id);

		if (!$order->get_meta('_panelr_is_manual')) {
			return;
		}

		$reference_code     = $order->get_meta('_panelr_reference_code');
		$confirmation_token = $order->get_meta('_panelr_confirmation_token');

		if (!$reference_code || !$confirmation_token) {
			return;
		}

		$already_submitted = $order->get_meta('_panelr_payment_submitted');
		$adjusted_total    = (float) $order->get_meta('_panelr_adjusted_total') ?: (float) $order->get_total();
		$gateway_id        = $order->get_payment_method();
		$pm                = self::get_panelr_payment_method($gateway_id);
		$inst              = $pm ? self::build_instructions($pm, $adjusted_total, $reference_code) : [];

		if (!empty($inst['qr_data'])) {
			wp_enqueue_script(
				'qrcodejs',
				PANELR_PLUGIN_URL . 'assets/js/qrcode.min.js',
				[],
				'1.0.0',
				true
			);
		}

		wp_enqueue_script(
			'panelr-thankyou',
			PANELR_PLUGIN_URL . 'assets/js/thankyou.js',
			!empty($inst['qr_data']) ? ['jquery', 'qrcodejs'] : ['jquery'],
			PANELR_VERSION,
			true
		);
		wp_localize_script('panelr-thankyou', 'panelrThankyou', [
			'ajaxurl'            => admin_url('admin-ajax.php'),
			'nonce'              => wp_create_nonce('panelr_submit_payment'),
			'confirmation_token' => $confirmation_token,
			'already_submitted'  => $already_submitted ? '1' : '0',
			'order_id'           => $order_id,
			'qr_data'            => $inst['qr_data'] ?? '',
		]);

		if ($already_submitted) {
			echo '<div class="panelr-thankyou panelr-submitted">';
			echo '<h2>' . esc_html__('Payment Confirmation Received', 'panelr-for-woocommerce') . '</h2>';
			echo '<p>' . esc_html__('Your payment confirmation has been received. Our team will verify and activate your service shortly.', 'panelr-for-woocommerce') . '</p>';
			echo '</div>';
			return;
		}

		$pm_name    = $pm ? ($pm['display_label'] ?: $pm['name']) : '';
		$txid_label = $inst['txid_label'] ?? 'Transaction ID';

		echo '<div class="panelr-thankyou" id="panelr-thankyou-wrap">';
		echo '<h2>' . esc_html__('Complete Your Payment', 'panelr-for-woocommerce') . '</h2>';

		// Reference box
		echo '<div class="panelr-reference-box">';
		echo '<p class="panelr-reference-label">' . esc_html__('Your Order Reference', 'panelr-for-woocommerce') . '</p>';
		echo '<p class="panelr-reference-code">' . esc_html($reference_code) . '</p>';
		echo '<p class="panelr-reference-note">' . esc_html__('Include this in your payment note / memo.', 'panelr-for-woocommerce') . '</p>';
		echo '</div>';

		// Payment instructions
		if (!empty($inst['lines'])) {
			echo '<div class="panelr-payment-instructions">';
			if ($pm_name) {
				echo '<p class="panelr-instructions-label">' . esc_html__('Payment Instructions', 'panelr-for-woocommerce') . ' &mdash; ' . esc_html($pm_name) . '</p>';
			}
			echo '<ul class="panelr-instructions-list">';
			foreach ($inst['lines'] as $line) {
				echo '<li>' . wp_kses($line, ['strong' => [], 'em' => []]) . '</li>';
			}
			echo '</ul>';

			if (!empty($inst['link'])) {
				echo '<div class="panelr-payment-link">';
				echo '<a href="' . esc_url($inst['link']) . '" target="_blank" class="button alt panelr-pay-btn">';
				echo esc_html($inst['link_label'] ?? 'Pay Now');
				echo '</a>';
				echo '</div>';
			}

			if (!empty($inst['copy_items'])) {
				echo '<ul class="panelr-copy-list">';
				foreach ($inst['copy_items'] as $ci) {
					echo '<li class="panelr-copy-item">';
					echo '<span class="panelr-copy-label">' . esc_html($ci['label']) . ':</span>';
					echo '<code class="panelr-copy-value">' . esc_html($ci['value']) . '</code>';
					echo '<button type="button" class="panelr-copy-btn button" data-copy="' . esc_attr($ci['value']) . '">' . esc_html__('Copy', 'panelr-for-woocommerce') . '</button>';
					echo '</li>';
				}
				echo '</ul>';
			}

			if (!empty($inst['note'])) {
				echo '<p class="panelr-instructions-note">' . esc_html($inst['note']) . '</p>';
			}
			echo '</div>';
		}

		if (!empty($inst['qr_data'])) {
			echo '<div class="panelr-qr-wrap">';
			echo '<p class="panelr-qr-label">' . esc_html__('Scan to pay:', 'panelr-for-woocommerce') . '</p>';
			echo '<div id="panelr-qr-code"></div>';
			echo '</div>';
		}

		echo '<p class="panelr-amount-due">' . esc_html__('Amount Due:', 'panelr-for-woocommerce') . ' <span class="panelr-amount">' . wp_kses_post(wc_price($adjusted_total)) . '</span></p>';

		echo '<div id="panelr-payment-form" class="panelr-payment-form">';
		echo '<p>';
		echo '<label for="panelr_transaction_id"><strong>' . esc_html($txid_label) . '</strong></label><br>';
		echo '<input type="text" id="panelr_transaction_id" name="panelr_transaction_id" class="input-text" placeholder="' . esc_attr__('Enter your transaction ID or reference number', 'panelr-for-woocommerce') . '">';
		echo '</p>';
		echo '<p>';
		echo '<label for="panelr_customer_note">' . esc_html__('Note (optional)', 'panelr-for-woocommerce') . '</label><br>';
		echo '<textarea id="panelr_customer_note" name="panelr_customer_note" rows="2"></textarea>';
		echo '</p>';
		echo '<p>';
		echo '<button type="button" id="panelr-submit-payment" class="button alt">' . esc_html__('Confirm Payment', 'panelr-for-woocommerce') . ' &rarr;</button>';
		echo '<span id="panelr-submit-result" class="panelr-submit-result"></span>';
		echo '</p>';
		echo '</div>';
		echo '</div>';
	}

	// ── AJAX: submit transaction ID ────────────────────────────────────────

	public static function ajax_submit_payment(): void
	{
		check_ajax_referer('panelr_submit_payment', 'nonce');

		$confirmation_token = sanitize_text_field(wp_unslash($_POST['confirmation_token'] ?? ''));
		$transaction_id     = sanitize_text_field(wp_unslash($_POST['transaction_id']     ?? ''));
		$customer_note      = sanitize_textarea_field(wp_unslash($_POST['customer_note']  ?? ''));
		$order_id           = (int) sanitize_text_field(wp_unslash($_POST['order_id']     ?? 0));

		if (!$confirmation_token) {
			wp_send_json_error(['message' => __('Missing confirmation token.', 'panelr-for-woocommerce')]);
		}

		$api    = new Panelr_API();
		$result = $api->submit_payment($confirmation_token, $transaction_id, $customer_note);

		if (!empty($result['success'])) {
			if ($order_id) {
				$order = wc_get_order($order_id);
				if ($order) {
					$order->update_meta_data('_panelr_payment_submitted', '1');
					$order->update_meta_data('_panelr_transaction_id', $transaction_id);
					$order->save();
				}
			}
			wp_send_json_success(['message' => __('Payment confirmation received. Our team will activate your service shortly.', 'panelr-for-woocommerce')]);
		} else {
			wp_send_json_error(['message' => $result['error'] ?? __('Could not submit payment confirmation. Please contact support.', 'panelr-for-woocommerce')]);
		}
	}
}
