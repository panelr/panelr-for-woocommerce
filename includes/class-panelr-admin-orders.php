<?php
defined('ABSPATH') || exit;

/**
 * Panelr → Orders: every WooCommerce order that carries Panelr plans, its
 * Panelr status, what never got sent, and one button to send or check.
 */
class Panelr_Admin_Orders
{
	public static function init(): void
	{
	}

	/** Recent order ids that contain a Panelr line item, newest first. */
	private static function order_ids(int $limit = 150): array
	{
		global $wpdb;
		$cached = wp_cache_get('panelr_order_ids_' . $limit, 'panelr');
		if (is_array($cached)) return $cached;
		// Order items are joined to their meta directly: WooCommerce offers no
		// query for "orders whose line items carry this meta", on either storage.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids = $wpdb->get_col($wpdb->prepare(
			"SELECT DISTINCT oi.order_id
			 FROM {$wpdb->prefix}woocommerce_order_items oi
			 INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oim.order_item_id = oi.order_item_id
			 WHERE oim.meta_key = '_panelr_intent'
			 ORDER BY oi.order_id DESC
			 LIMIT %d",
			$limit
		));
		$ids = array_map('intval', $ids);
		wp_cache_set('panelr_order_ids_' . $limit, $ids, 'panelr', MINUTE_IN_SECONDS);
		return $ids;
	}

	public static function render_page(): void
	{
		if (!current_user_can('edit_shop_orders')) return;
		$orders = [];
		foreach (self::order_ids() as $id) {
			$order = wc_get_order($id);
			if ($order) $orders[] = $order;
		}
		$never = array_filter($orders, fn($o) => !$o->get_meta('_panelr_work_order_id') && !$o->get_meta('_panelr_balance_payment') && !$o->has_status(['cancelled', 'refunded', 'failed']));
		?>
		<div class="wrap panelr-settings-wrap">
			<h1><?php esc_html_e('Panelr orders', 'panelr-for-woocommerce'); ?></h1>

			<?php if ($never): ?>
				<div class="panelr-settings-section">
					<h2><?php esc_html_e('Never sent to Panelr', 'panelr-for-woocommerce'); ?></h2>
					<?php self::table($never, true); ?>
				</div>
			<?php endif; ?>

			<div class="panelr-settings-section">
				<h2><?php esc_html_e('All orders with Panelr plans', 'panelr-for-woocommerce'); ?></h2>
				<?php if ($orders): ?>
					<?php self::table($orders, false); ?>
				<?php else: ?>
					<p><?php esc_html_e('No orders yet.', 'panelr-for-woocommerce'); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	private static function table(array $orders, bool $never_sent): void
	{
		?>
		<table class="widefat striped panelr-products-table panelr-orders-table">
			<thead><tr>
				<th><?php esc_html_e('Order', 'panelr-for-woocommerce'); ?></th>
				<th><?php esc_html_e('Date', 'panelr-for-woocommerce'); ?></th>
				<th><?php esc_html_e('Customer', 'panelr-for-woocommerce'); ?></th>
				<th><?php esc_html_e('WooCommerce', 'panelr-for-woocommerce'); ?></th>
				<th><?php esc_html_e('Panelr', 'panelr-for-woocommerce'); ?></th>
				<th><?php esc_html_e('Reference', 'panelr-for-woocommerce'); ?></th>
				<th></th>
			</tr></thead>
			<tbody>
			<?php foreach ($orders as $order):
				$ref   = (string) $order->get_meta('_panelr_reference_code');
				$state = (string) $order->get_meta('_panelr_state');
				$status = (string) $order->get_meta('_panelr_status');
				$link  = $ref ? Panelr_Helpers::admin_panelr_url('work-orders.php?search=' . rawurlencode($ref)) : '';
				?>
				<tr data-order-id="<?php echo (int) $order->get_id(); ?>">
					<td><a href="<?php echo esc_url($order->get_edit_order_url()); ?>">#<?php echo (int) $order->get_id(); ?></a></td>
					<td><?php echo esc_html($order->get_date_created() ? $order->get_date_created()->date_i18n(get_option('date_format') . ' ' . get_option('time_format')) : '—'); ?></td>
					<td><?php echo esc_html($order->get_formatted_billing_full_name() ?: $order->get_billing_email()); ?><br><small><?php echo esc_html($order->get_billing_email()); ?></small></td>
					<td><?php echo esc_html(wc_get_order_status_name($order->get_status())); ?></td>
					<td class="panelr-order-box__status"><?php echo esc_html($ref ? Panelr_Helpers::order_status_label($status ?: $state) : Panelr_Orders::state_label($state)); ?>
						<?php if (!$ref && $order->get_meta('_panelr_hold_reason')): ?><br><small><?php echo esc_html((string) $order->get_meta('_panelr_hold_reason')); ?></small><?php endif; ?>
					</td>
					<td><?php if ($ref): ?><code><?php echo esc_html($ref); ?></code><?php else: ?>—<?php endif; ?></td>
					<td class="panelr-input-row">
						<?php if ($ref): ?>
							<button type="button" class="button panelr-check-order"><?php esc_html_e('Check now', 'panelr-for-woocommerce'); ?></button>
							<?php if ($link): ?><a class="button" href="<?php echo esc_url($link); ?>" target="_blank" rel="noopener"><?php esc_html_e('Open in Panelr', 'panelr-for-woocommerce'); ?></a><?php endif; ?>
						<?php elseif (!$order->get_meta('_panelr_balance_payment')): ?>
							<button type="button" class="button button-primary panelr-send-order"><?php esc_html_e('Send', 'panelr-for-woocommerce'); ?></button>
						<?php endif; ?>
						<span class="panelr-order-box__result panelr-inline-result" aria-live="polite"></span>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
