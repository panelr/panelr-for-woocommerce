<?php
defined('ABSPATH') || exit;

/**
 * Panelr never calls the site, so the plugin asks. After an order is sent
 * for provisioning it is checked at +1, +2, +5, +15 and +60 minutes until
 * Panelr says completed, canceled or payment_failed. On completion the
 * lines land on the order, the order moves to Completed (setting), and
 * nothing is emailed — Panelr already did that.
 */
class Panelr_Orders
{
	const HOOK = 'panelr_poll_order';
	const DELAYS = [60, 120, 300, 900, 3600];
	// After the first hour a still-open order is checked every six hours for a week.
	const SLOW_DELAY = 6 * HOUR_IN_SECONDS;
	const SLOW_ROUNDS = 28;

	public static function init(): void
	{
		add_action(self::HOOK, [__CLASS__, 'poll'], 10, 2);
		add_action('add_meta_boxes', [__CLASS__, 'add_meta_box']);
		add_action('wp_ajax_panelr_admin_check_order', [__CLASS__, 'ajax_admin_check']);
		add_action('wp_ajax_panelr_admin_send_order',  [__CLASS__, 'ajax_admin_send']);
	}

	public static function schedule_polling(int $order_id): void
	{
		if (!function_exists('as_schedule_single_action')) return;
		as_unschedule_all_actions(self::HOOK, [$order_id, 0], 'panelr');
		as_schedule_single_action(time() + self::DELAYS[0], self::HOOK, [$order_id, 0], 'panelr');
	}

	public static function poll($order_id, $attempt = 0): void
	{
		$order = wc_get_order((int) $order_id);
		if (!$order) return;
		$status = self::check_order($order, 'poll');
		if (in_array($status, ['completed', 'canceled', 'payment_failed', 'none'], true)) return;

		$next = (int) $attempt + 1;
		if (!function_exists('as_schedule_single_action')) return;
		if (isset(self::DELAYS[$next])) {
			as_schedule_single_action(time() + self::DELAYS[$next], self::HOOK, [(int) $order_id, $next], 'panelr');
		} elseif ($next < count(self::DELAYS) + self::SLOW_ROUNDS) {
			as_schedule_single_action(time() + self::SLOW_DELAY, self::HOOK, [(int) $order_id, $next], 'panelr');
		}
	}

	/**
	 * Ask Panelr about the order and write what it says onto the WC order.
	 * Returns Panelr's status ('none' when there is nothing to ask about).
	 */
	public static function check_order(WC_Order $order, string $who = 'poll'): string
	{
		$ref   = (string) $order->get_meta('_panelr_reference_code');
		$token = (string) $order->get_meta('_panelr_confirmation_token');
		$email = Panelr_Helpers::order_account_email($order);
		if (!$ref) return 'none';

		$result = Panelr_API::instance()->get_work_order($ref, $token ?: null, $token ? null : $email);
		if (!$result['ok']) {
			$order->update_meta_data('_panelr_last_check', time());
			$order->save();
			return (string) ($order->get_meta('_panelr_status') ?: 'unknown');
		}

		$d      = $result['data'];
		$status = (string) ($d['status'] ?? 'unknown');
		$prev   = (string) $order->get_meta('_panelr_status');

		$order->update_meta_data('_panelr_status', $status);
		$order->update_meta_data('_panelr_last_check', time());
		if ($status === 'completed') {
			$order->update_meta_data('_panelr_state', 'completed');
		} elseif (in_array($status, ['canceled', 'payment_failed'], true)) {
			$order->update_meta_data('_panelr_state', $status);
		}

		if ($status !== $prev) {
			$order->add_order_note(sprintf(
				/* translators: %s: status label */
				__('Panelr: %s', 'panelr-for-woocommerce'),
				Panelr_Helpers::order_status_label($status)
			));
		}

		if ($status === 'completed' && $prev !== 'completed') {
			self::record_lines($order, $d);
			if (Panelr_Helpers::bool_option('panelr_auto_complete_orders', '1') && !$order->has_status(['completed', 'refunded', 'cancelled'])) {
				$order->update_status('completed', __('Panelr set the service up.', 'panelr-for-woocommerce'));
			}
		}
		$order->save();

		return $status;
	}

	/** The lines the order produced: from get_work_orders (activation_ids) and the customer's lines. */
	private static function record_lines(WC_Order $order, array $panelr_order): void
	{
		$email = (string) ($panelr_order['customer_email'] ?? $order->get_billing_email());
		$ids   = [];
		$lines = [];

		$orders = Panelr_API::instance()->get('get_work_orders', ['customer_email' => $email, 'limit' => 50]);
		if ($orders['ok'] && is_array($orders['data'])) {
			foreach ($orders['data'] as $wo) {
				if ((string) ($wo['reference_code'] ?? '') === (string) $order->get_meta('_panelr_reference_code')) {
					$ids = array_map('intval', (array) ($wo['activation_ids'] ?? []));
					break;
				}
			}
		}

		if ($ids) {
			$all = Panelr_API::instance()->get_lines_for_email($email);
			if ($all['ok'] && is_array($all['data'])) {
				foreach ($all['data'] as $line) {
					if (in_array((int) $line['id'], $ids, true)) {
						$lines[] = [
							'activation_id' => (int) $line['id'],
							'username'      => (string) ($line['editor_username'] ?: $line['xtream_username']),
							'service_name'  => (string) ($line['plugin_name'] ?? ''),
							'plugin_id'     => (int) ($line['plugin_id'] ?? 0),
							'product_name'  => (string) ($line['product_name'] ?? ''),
							'expiration'    => $line['expiration_date'] ?? null,
							'status'        => (string) ($line['status'] ?? ''),
						];
					}
				}
			}
		}

		$order->update_meta_data('_panelr_activation_ids', wp_json_encode($ids));
		$order->update_meta_data('_panelr_lines', wp_json_encode($lines));
		if ($lines) {
			$names = array_map(fn($l) => $l['username'] . ($l['service_name'] ? ' (' . $l['service_name'] . ')' : ''), $lines);
			$order->add_order_note(sprintf(
				/* translators: %s: comma-separated usernames */
				__('Connections: %s', 'panelr-for-woocommerce'),
				implode(', ', $names)
			));
		}
	}

	/** The WooCommerce order that carries a Panelr reference, if any. */
	public static function order_by_reference(string $ref): ?WC_Order
	{
		if ($ref === '') return null;
		// meta_query, not meta_key: WooCommerce's order-table storage ignores a
		// top-level meta_key and would hand back the newest order of all.
		$orders = wc_get_orders([
			'limit'      => 5,
			'return'     => 'objects',
			'orderby'    => 'date',
			'order'      => 'DESC',
			'meta_query' => [['key' => '_panelr_reference_code', 'value' => $ref, 'compare' => '=']], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- the reference is the only key an order is found by
		]);
		foreach ((array) $orders as $order) {
			// Never trust the filter alone: the order must really carry this reference.
			if ($order instanceof WC_Order && (string) $order->get_meta('_panelr_reference_code') === $ref) {
				return $order;
			}
		}
		return null;
	}

	// ── Admin order screen ────────────────────────────────────────────────

	public static function add_meta_box(): void
	{
		$screen = class_exists(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)
			&& wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
			? wc_get_page_screen_id('shop-order')
			: 'shop_order';
		add_meta_box('panelr-order', __('Panelr', 'panelr-for-woocommerce'), [__CLASS__, 'render_meta_box'], $screen, 'side', 'high');
	}

	public static function render_meta_box($post_or_order): void
	{
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order($post_or_order->ID ?? 0);
		if (!$order) return;
		$has_items = (bool) Panelr_Cart::order_items($order);
		$ref = (string) $order->get_meta('_panelr_reference_code');
		if (!$has_items && !$ref) {
			echo '<p>' . esc_html__('No Panelr plans on this order.', 'panelr-for-woocommerce') . '</p>';
			return;
		}
		wp_enqueue_script('panelr-admin', PANELR_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], PANELR_VERSION, true);
		wp_localize_script('panelr-admin', 'panelrAdmin', Panelr_Settings::admin_js_vars());

		$state = (string) $order->get_meta('_panelr_state');
		$status = (string) $order->get_meta('_panelr_status');
		$lines = json_decode((string) $order->get_meta('_panelr_lines'), true) ?: [];
		$ids   = json_decode((string) $order->get_meta('_panelr_activation_ids'), true) ?: [];
		$link  = Panelr_Helpers::admin_panelr_url('work-orders.php' . ($ref ? '?search=' . rawurlencode($ref) : ''));
		?>
		<div class="panelr-order-box" data-order-id="<?php echo (int) $order->get_id(); ?>">
			<?php if ($ref): ?>
				<p><strong><?php esc_html_e('Reference', 'panelr-for-woocommerce'); ?></strong><br><code><?php echo esc_html($ref); ?></code></p>
				<p><strong><?php esc_html_e('Status', 'panelr-for-woocommerce'); ?></strong><br><span class="panelr-order-box__status"><?php echo esc_html(Panelr_Helpers::order_status_label($status ?: $state)); ?></span></p>
				<?php if ($ids): ?>
					<p><strong><?php esc_html_e('Connections', 'panelr-for-woocommerce'); ?></strong><br>
					<?php foreach ($lines ?: array_map(fn($id) => ['activation_id' => $id, 'username' => '#' . $id, 'service_name' => ''], $ids) as $l): ?>
						<code><?php echo esc_html($l['username']); ?></code> <?php echo esc_html($l['service_name'] ? '· ' . $l['service_name'] : ''); ?><br>
					<?php endforeach; ?>
					</p>
				<?php endif; ?>
				<p>
					<button type="button" class="button panelr-check-order"><?php esc_html_e('Check now', 'panelr-for-woocommerce'); ?></button>
					<?php if ($link): ?><a class="button" href="<?php echo esc_url($link); ?>" target="_blank" rel="noopener"><?php esc_html_e('Open in Panelr', 'panelr-for-woocommerce'); ?></a><?php endif; ?>
				</p>
			<?php else: ?>
				<p><strong><?php echo esc_html(self::state_label($state)); ?></strong></p>
				<?php if ($order->get_meta('_panelr_hold_reason')): ?>
					<p><?php echo esc_html((string) $order->get_meta('_panelr_hold_reason')); ?></p>
				<?php endif; ?>
				<p><button type="button" class="button button-primary panelr-send-order"><?php esc_html_e('Send to Panelr', 'panelr-for-woocommerce'); ?></button></p>
			<?php endif; ?>
			<p class="panelr-order-box__result" aria-live="polite"></p>
		</div>
		<?php
	}

	public static function state_label(string $state): string
	{
		switch ($state) {
			case 'held':            return __('Held: Panelr could not be reached. Retrying.', 'panelr-for-woocommerce');
			case 'never_sent':      return __('Never sent to Panelr', 'panelr-for-woocommerce');
			case 'unmapped':        return __('Payment method not mapped to Panelr', 'panelr-for-woocommerce');
			case 'refused':         return __('Refused by Panelr', 'panelr-for-woocommerce');
			case 'awaiting_payment': return __('Waiting for payment', 'panelr-for-woocommerce');
			case 'credits':         return __('Paying with credits', 'panelr-for-woocommerce');
			case '':                return __('Never sent to Panelr', 'panelr-for-woocommerce');
			default:                return Panelr_Helpers::order_status_label($state);
		}
	}

	public static function ajax_admin_check(): void
	{
		check_ajax_referer('panelr_admin_nonce', 'nonce');
		if (!current_user_can('edit_shop_orders')) wp_send_json_error(['message' => __('You are not allowed to do that.', 'panelr-for-woocommerce')]);
		$order = wc_get_order(absint(wp_unslash($_POST['order_id'] ?? 0)));
		if (!$order) wp_send_json_error(['message' => __('Order not found.', 'panelr-for-woocommerce')]);
		$status = self::check_order($order, 'admin');
		wp_send_json_success([
			'status' => $status,
			'label'  => Panelr_Helpers::order_status_label($status),
			'lines'  => json_decode((string) $order->get_meta('_panelr_lines'), true) ?: [],
		]);
	}

	/** Send an order that never reached Panelr. Sending twice is impossible: a sent order has a work order id. */
	public static function ajax_admin_send(): void
	{
		check_ajax_referer('panelr_admin_nonce', 'nonce');
		if (!current_user_can('edit_shop_orders')) wp_send_json_error(['message' => __('You are not allowed to do that.', 'panelr-for-woocommerce')]);
		$order = wc_get_order(absint(wp_unslash($_POST['order_id'] ?? 0)));
		if (!$order) wp_send_json_error(['message' => __('Order not found.', 'panelr-for-woocommerce')]);
		if ($order->get_meta('_panelr_work_order_id')) {
			wp_send_json_success(['message' => __('Already sent.', 'panelr-for-woocommerce'), 'label' => Panelr_Helpers::order_status_label((string) $order->get_meta('_panelr_status'))]);
		}
		$ok = self::send_now($order);
		if ($ok) {
			wp_send_json_success([
				'message' => __('Sent to Panelr.', 'panelr-for-woocommerce'),
				'ref'     => (string) $order->get_meta('_panelr_reference_code'),
				'label'   => Panelr_Helpers::order_status_label((string) $order->get_meta('_panelr_status')),
			]);
		}
		wp_send_json_error(['message' => (string) ($order->get_meta('_panelr_hold_reason') ?: __('Panelr did not accept the order. See the order notes.', 'panelr-for-woocommerce'))]);
	}

	public static function send_now(WC_Order $order): bool
	{
		$gateway = (string) $order->get_payment_method();
		if ($gateway === Panelr_Credits_Gateway::ID) {
			return Panelr_Credits_Gateway::complete_with_credits($order);
		}
		if ($order->get_meta('_panelr_confirmation_token') && $order->get_meta('_panelr_state') !== 'sent') {
			return $order->is_paid() ? Panelr_Checkout::complete_handoff_order($order) : false;
		}
		$order->delete_meta_data('_panelr_hold_attempt');
		$order->update_meta_data('_panelr_state', '');
		$order->save();
		if (!Panelr_Checkout::route_order($order, true)) {
			return (bool) $order->get_meta('_panelr_work_order_id');
		}
		if ((string) $order->get_meta('_panelr_mode') === 'automatic' && !$order->get_meta('_panelr_work_order_id')) {
			if ($order->is_paid()) {
				$pm = Panelr_Helpers::mapped_method($gateway);
				return $pm ? Panelr_Checkout::complete_automatic_order($order, $pm) : false;
			}
			return false;
		}
		return (bool) $order->get_meta('_panelr_work_order_id');
	}
}
