<?php
/**
 * The member's orders, newest first.
 * Override: woocommerce/panelr/portal/tab-orders.php
 *
 * @var array  $orders     get_work_orders rows
 * @var string $error
 * @var string $status_url
 * @var bool   $multi
 */
defined('ABSPATH') || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- this file is included inside wc_get_template(); its variables are local, not global
?>
<?php if ($error): ?>
	<div class="panelr-portal__error" role="alert"><?php echo esc_html($error); ?></div>
<?php elseif (!$orders): ?>
	<section class="panelr-portal__section"><p><?php esc_html_e('No orders yet.', 'panelr-for-woocommerce'); ?></p></section>
<?php endif; ?>

<?php foreach ($orders as $o):
	$currency = (string) ($o['currency'] ?? get_woocommerce_currency());
	$status   = (string) ($o['status'] ?? '');
	$link     = $status_url ? add_query_arg(['panelr_ref' => $o['reference_code']], $status_url) : '';
	?>
	<section class="panelr-portal__section panelr-order">
		<div class="panelr-order__head">
			<h3 class="panelr-order__ref"><?php echo esc_html($o['reference_code']); ?></h3>
			<span class="panelr-portal__status panelr-portal__status--<?php echo esc_attr($status); ?>"><?php echo esc_html(Panelr_Helpers::order_status_label($status)); ?></span>
			<span class="panelr-order__date"><?php echo esc_html(Panelr_Helpers::format_date($o['created_at'] ?? null, true)); ?></span>
		</div>
		<table class="panelr-portal__table">
			<?php foreach ((array) ($o['items'] ?? []) as $item): ?>
				<tr>
					<td><?php echo esc_html($item['name'] ?? ''); ?><?php if ($multi && !empty($item['plugin_name'])): ?> · <?php echo esc_html($item['plugin_name']); ?><?php endif; ?>
						<?php if (!empty($item['intent']) && $item['intent'] !== 'new_activation'): ?><br><small><?php echo esc_html(Panelr_Helpers::intent_label((string) $item['intent'])); ?></small><?php endif; ?>
					</td>
					<td>&times;<?php echo (int) ($item['qty'] ?? 1); ?></td>
					<td><?php echo !empty($item['credits_paid'])
						/* translators: %d: credits */
						? esc_html(sprintf(_n('%d credit', '%d credits', (int) $item['credits_paid'], 'panelr-for-woocommerce'), (int) $item['credits_paid']))
						: wp_kses_post(wc_price((float) ($item['price'] ?? 0) * (int) ($item['qty'] ?? 1), ['currency' => $currency])); ?></td>
				</tr>
			<?php endforeach; ?>
			<?php if (!empty($o['coupon'])): ?>
				<tr><td colspan="2"><?php echo esc_html(sprintf(
					/* translators: %s: coupon code */
					__('Coupon %s', 'panelr-for-woocommerce'),
					strtoupper((string) $o['coupon']['code'])
				)); ?></td><td>&minus;<?php echo wp_kses_post(wc_price((float) $o['coupon']['discount'], ['currency' => $currency])); ?></td></tr>
			<?php endif; ?>
			<?php if (!empty($o['adjustment'])): ?>
				<tr><td colspan="2"><?php echo esc_html($o['adjustment']['label'] ?? ucfirst((string) $o['adjustment']['direction'])); ?></td><td><?php echo $o['adjustment']['direction'] === 'fee' ? '+' : '&minus;'; ?><?php echo wp_kses_post(wc_price((float) $o['adjustment']['amount'], ['currency' => $currency])); ?></td></tr>
			<?php endif; ?>
			<tr>
				<td colspan="2"><strong><?php echo isset($o['credits_paid']) && $o['credits_paid'] !== null ? esc_html__('Paid with', 'panelr-for-woocommerce') : esc_html__('Total', 'panelr-for-woocommerce'); ?></strong></td>
				<td><strong><?php echo isset($o['credits_paid']) && $o['credits_paid'] !== null
					/* translators: %d: credits */
					? esc_html(sprintf(_n('%d credit', '%d credits', (int) $o['credits_paid'], 'panelr-for-woocommerce'), (int) $o['credits_paid']))
					: wp_kses_post(wc_price((float) ($o['order_total'] ?? 0), ['currency' => $currency])); ?></strong></td>
			</tr>
			<?php if ((float) ($o['balance_due'] ?? 0) > 0 && $status === 'pending_payment'): ?>
				<tr><td colspan="2"><?php esc_html_e('Still to pay', 'panelr-for-woocommerce'); ?></td><td><?php echo wp_kses_post(wc_price((float) $o['balance_due'], ['currency' => $currency])); ?></td></tr>
			<?php endif; ?>
		</table>
		<?php if ($status === 'pending_payment' && $link): ?>
			<p><a class="button panelr-renew-btn" href="<?php echo esc_url(add_query_arg('panelr_email', rawurlencode(Panelr_Session::email()), $link)); ?>"><?php esc_html_e('Pay now', 'panelr-for-woocommerce'); ?></a></p>
		<?php endif; ?>
	</section>
<?php endforeach; ?>
