<?php
/**
 * One Panelr order, rendered from get_work_order.
 * Override: woocommerce/panelr/order-status/order.php
 *
 * @var array       $order        get_work_order data
 * @var string      $status
 * @var string      $ref
 * @var string|null $token
 * @var string      $email
 * @var array|null  $pm
 * @var bool        $manual
 * @var array|null  $instructions
 * @var string      $page_url
 * @var bool        $multi
 */
defined('ABSPATH') || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- this file is included inside wc_get_template(); its variables are local, not global

$currency   = (string) ($order['currency'] ?? get_woocommerce_currency());
$cart       = (array) ($order['cart'] ?? []);
$adj        = $order['adjustment'] ?? null;
$coupon     = $order['coupon'] ?? null;
$credits    = $order['credits_paid'] ?? null;
$has_parts  = !empty($order['has_partials']);
$balance    = (float) ($order['balance_due'] ?? 0);
$total      = (float) ($order['order_total'] ?? 0);
$amount_due = $has_parts ? $balance : $total;
$history    = (array) ($order['payment_history'] ?? []);
$price      = fn(float $v) => wc_price($v, ['currency' => $currency]);
?>
<div class="panelr-order-status" id="panelr-os-wrap">

	<div class="panelr-reference-box">
		<p class="panelr-reference-label"><?php esc_html_e('Order', 'panelr-for-woocommerce'); ?></p>
		<p class="panelr-reference-code"><?php echo esc_html($ref); ?></p>
		<p class="panelr-reference-note"><span class="panelr-portal__status panelr-portal__status--<?php echo esc_attr($status); ?>"><?php echo esc_html(Panelr_Helpers::order_status_label($status)); ?></span></p>
	</div>

	<?php if ($cart): ?>
		<div class="panelr-os-summary panelr-portal__section">
			<table class="panelr-portal__table">
				<thead><tr>
					<th><?php esc_html_e('Plan', 'panelr-for-woocommerce'); ?></th>
					<th><?php esc_html_e('Qty', 'panelr-for-woocommerce'); ?></th>
					<th><?php esc_html_e('Price', 'panelr-for-woocommerce'); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ($cart as $item): ?>
					<tr>
						<td>
							<?php echo esc_html($item['name'] ?? ''); ?>
							<?php if ($multi && !empty($item['plugin_name'])): ?><br><small><?php echo esc_html($item['plugin_name']); ?></small><?php endif; ?>
							<?php if (!empty($item['intent']) && $item['intent'] !== 'new_activation'): ?><br><small><?php echo esc_html(Panelr_Helpers::intent_label((string) $item['intent'])); ?><?php if (!empty($item['linked_username'])): ?> · <?php echo esc_html($item['linked_username']); ?><?php endif; ?></small><?php endif; ?>
						</td>
						<td>&times;<?php echo (int) ($item['qty'] ?? 1); ?></td>
						<td><?php echo !empty($item['points_paid'])
							/* translators: %d: credits */
							? esc_html(sprintf(_n('%d credit', '%d credits', (int) $item['points_paid'], 'panelr-for-woocommerce'), (int) $item['points_paid']))
							: wp_kses_post($price((float) ($item['price'] ?? 0) * (int) ($item['qty'] ?? 1))); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
				<tfoot>
					<?php if ($coupon || $adj): ?>
						<tr><td colspan="2"><?php esc_html_e('Subtotal', 'panelr-for-woocommerce'); ?></td><td><?php echo wp_kses_post($price((float) ($order['snap_total'] ?? $total))); ?></td></tr>
					<?php endif; ?>
					<?php if ($coupon): ?>
						<tr><td colspan="2"><?php echo esc_html(sprintf(
							/* translators: %s: coupon code */
							__('Coupon %s', 'panelr-for-woocommerce'),
							strtoupper((string) $coupon['code'])
						)); ?></td><td>&minus;<?php echo wp_kses_post($price((float) $coupon['discount'])); ?></td></tr>
					<?php endif; ?>
					<?php if ($adj): ?>
						<tr>
							<td colspan="2"><?php echo esc_html($adj['label'] ?? ucfirst((string) $adj['direction'])); ?></td>
							<td><?php echo $adj['direction'] === 'fee' ? '+' : '&minus;'; ?><?php echo wp_kses_post($price((float) $adj['amount'])); ?></td>
						</tr>
					<?php endif; ?>
					<?php if ($credits !== null): ?>
						<tr><td colspan="2"><strong><?php esc_html_e('Paid with', 'panelr-for-woocommerce'); ?></strong></td><td><strong><?php echo esc_html(sprintf(
							/* translators: %d: credits */
							_n('%d credit', '%d credits', (int) $credits, 'panelr-for-woocommerce'),
							(int) $credits
						)); ?></strong></td></tr>
					<?php else: ?>
						<tr><td colspan="2"><strong><?php esc_html_e('Total', 'panelr-for-woocommerce'); ?></strong></td><td><strong><?php echo wp_kses_post($price($total)); ?></strong></td></tr>
					<?php endif; ?>
					<?php foreach ($history as $ph): ?>
						<tr>
							<td colspan="2"><?php echo esc_html(sprintf(
								/* translators: %s: date */
								__('Paid %s', 'panelr-for-woocommerce'),
								Panelr_Helpers::format_date($ph['at'] ?? ($ph['date'] ?? null))
							)); ?></td>
							<td>&minus;<?php echo wp_kses_post($price((float) ($ph['amount'] ?? 0))); ?></td>
						</tr>
					<?php endforeach; ?>
					<?php if ($has_parts): ?>
						<tr><td colspan="2"><strong><?php esc_html_e('Still to pay', 'panelr-for-woocommerce'); ?></strong></td><td><strong><?php echo wp_kses_post($price($balance)); ?></strong></td></tr>
					<?php endif; ?>
				</tfoot>
			</table>
		</div>
	<?php endif; ?>

	<?php if ($status === 'pending_payment' && $pm && !empty($pm['is_automated']) && $amount_due > 0): ?>
		<form method="post" action="<?php echo esc_url($page_url); ?>" class="panelr-os-balance">
			<?php wp_nonce_field('panelr_balance_pay', 'panelr_balance_nonce'); ?>
			<input type="hidden" name="panelr_balance_ref" value="<?php echo esc_attr($ref); ?>">
			<input type="hidden" name="panelr_balance_token" value="<?php echo esc_attr((string) $token); ?>">
			<input type="hidden" name="panelr_balance_email" value="<?php echo esc_attr($email); ?>">
			<button type="submit" class="button panelr-renew-btn"><?php echo wp_kses_post(sprintf(
				/* translators: 1: amount, 2: payment method */
				__('Pay %1$s with %2$s', 'panelr-for-woocommerce'),
				wc_price($amount_due, ['currency' => $currency]),
				esc_html($pm['name'] ?? '')
			)); ?></button>
		</form>

	<?php elseif ($status === 'pending_payment' && $manual): ?>
		<?php if ($instructions): ?>
			<div class="panelr-payment-instructions">
				<p class="panelr-instructions-label"><?php esc_html_e('How to pay', 'panelr-for-woocommerce'); ?><?php if ($pm): ?> &mdash; <?php echo esc_html($pm['name']); ?><?php endif; ?></p>
				<?php if ($instructions['note']): ?><p class="panelr-instructions-note"><?php echo nl2br(esc_html($instructions['note'])); ?></p><?php endif; ?>
				<?php if ($instructions['rows']): ?>
					<ul class="panelr-copy-list">
						<?php foreach ($instructions['rows'] as $row): ?>
							<li class="panelr-copy-item">
								<span class="panelr-copy-label"><?php echo esc_html($row['label']); ?></span>
								<code class="panelr-copy-value"><?php echo esc_html($row['value']); ?></code>
								<button type="button" class="panelr-copy-btn button" data-copy="<?php echo esc_attr($row['value']); ?>"><?php esc_html_e('Copy', 'panelr-for-woocommerce'); ?></button>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
			<?php if ($instructions['qr']): ?>
				<div class="panelr-qr-wrap"><p class="panelr-qr-label"><?php esc_html_e('Scan to pay', 'panelr-for-woocommerce'); ?></p><div id="panelr-qr-code" data-qr="<?php echo esc_attr($instructions['qr']); ?>"></div></div>
			<?php endif; ?>
		<?php endif; ?>

		<p class="panelr-amount-due"><?php esc_html_e('Amount to send', 'panelr-for-woocommerce'); ?> <span class="panelr-amount"><?php echo wp_kses_post($price($amount_due)); ?></span></p>

		<div id="panelr-os-payment-form" class="panelr-payment-form">
			<p id="panelr-os-error" class="panelr-portal__error" hidden></p>
			<p class="panelr-portal__field">
				<label for="panelr-os-txid"><?php echo esc_html($instructions['txid_label'] ?? __('Transaction id or reference', 'panelr-for-woocommerce')); ?></label>
				<input type="text" id="panelr-os-txid" class="input-text">
			</p>
			<p class="panelr-portal__field">
				<label for="panelr-os-amount"><?php esc_html_e('Amount sent', 'panelr-for-woocommerce'); ?></label>
				<input type="number" step="0.01" min="0" id="panelr-os-amount" class="input-text" value="<?php echo esc_attr(number_format($amount_due, 2, '.', '')); ?>">
			</p>
			<p class="panelr-portal__field">
				<label for="panelr-os-note"><?php esc_html_e('Note', 'panelr-for-woocommerce'); ?></label>
				<textarea id="panelr-os-note" rows="2" class="input-text"></textarea>
			</p>
			<p>
				<button type="button" id="panelr-os-submit" class="button panelr-renew-btn"><?php esc_html_e("I've paid", 'panelr-for-woocommerce'); ?></button>
				<span id="panelr-os-result" class="panelr-submit-result" aria-live="polite"></span>
			</p>
		</div>
		<div id="panelr-os-success" class="panelr-payment-success" hidden><?php esc_html_e('Payment confirmation received. We will check it and set up your service shortly.', 'panelr-for-woocommerce'); ?></div>

	<?php elseif ($status === 'payment_submitted'): ?>
		<div class="panelr-payment-success">
			<h3><?php esc_html_e('Payment confirmation received', 'panelr-for-woocommerce'); ?></h3>
			<p><?php esc_html_e('We are checking it and will set up your service shortly.', 'panelr-for-woocommerce'); ?></p>
		</div>

	<?php elseif (in_array($status, ['awaiting_webhook', 'queued'], true)): ?>
		<div class="panelr-payment-success">
			<h3><?php esc_html_e('Being set up', 'panelr-for-woocommerce'); ?></h3>
			<p><?php esc_html_e('Your payment is in and your service is being set up. You will get an email when it is ready.', 'panelr-for-woocommerce'); ?></p>
		</div>

	<?php elseif ($status === 'completed'): ?>
		<div class="panelr-payment-success">
			<h3><?php esc_html_e('Ready', 'panelr-for-woocommerce'); ?></h3>
			<p><?php echo esc_html(sprintf(
				/* translators: %s: email */
				__('Your service is set up. Your connection details went to %s.', 'panelr-for-woocommerce'),
				(string) ($order['customer_email'] ?? $email)
			)); ?></p>
		</div>

	<?php elseif ($status === 'payment_failed'): ?>
		<div class="panelr-portal__error">
			<h3><?php esc_html_e('Payment failed', 'panelr-for-woocommerce'); ?></h3>
			<p><?php esc_html_e('The payment did not go through. Please contact us.', 'panelr-for-woocommerce'); ?></p>
		</div>

	<?php elseif (in_array($status, ['canceled', 'cancelled'], true)): ?>
		<div class="panelr-portal__error">
			<h3><?php esc_html_e('Canceled', 'panelr-for-woocommerce'); ?></h3>
			<p><?php esc_html_e('This order was canceled. Please contact us if that is a surprise.', 'panelr-for-woocommerce'); ?></p>
		</div>

	<?php else: ?>
		<div class="woocommerce-info"><?php echo esc_html(Panelr_Helpers::order_status_label($status)); ?></div>
	<?php endif; ?>
</div>
