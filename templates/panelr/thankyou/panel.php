<?php
/**
 * The Panelr block on the order-received page and the customer's order page.
 * Override: woocommerce/panelr/thankyou/panel.php
 *
 * @var WC_Order   $order
 * @var bool       $is_thankyou
 * @var string     $state
 * @var bool       $manual
 * @var string     $ref
 * @var array|null $panelr_order   get_work_order answer
 * @var array|null $pm             mapped Panelr payment method
 * @var array|null $instructions   {note,rows,qr,txid_label,amount,currency}
 * @var bool       $submitted
 * @var array      $lines
 * @var string     $status
 * @var string     $hold_reason
 * @var string     $portal_url
 */
defined('ABSPATH') || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- this file is included inside wc_get_template(); its variables are local, not global

$panelr_status = (string) ($panelr_order['status'] ?? $status);
$done = in_array($panelr_status, ['completed'], true) || $state === 'completed';
?>
<div class="panelr-thankyou" id="panelr-thankyou-wrap" data-state="<?php echo esc_attr($state); ?>">

	<?php if ($state === 'held' || $state === 'never_sent'): ?>
		<div class="panelr-payment-success panelr-thankyou__pending">
			<h2><?php esc_html_e('We have your order', 'panelr-for-woocommerce'); ?></h2>
			<p><?php esc_html_e('It is being passed along and you will get an email as soon as your service is ready.', 'panelr-for-woocommerce'); ?></p>
		</div>

	<?php elseif ($state === 'refused'): ?>
		<div class="panelr-portal__error">
			<h2><?php esc_html_e('We need to look at this order', 'panelr-for-woocommerce'); ?></h2>
			<p><?php esc_html_e('Something about it could not be processed automatically. Our team has it and will be in touch.', 'panelr-for-woocommerce'); ?></p>
		</div>

	<?php elseif ($manual && $ref): ?>
		<?php if ($panelr_status === 'completed'): ?>
			<div class="panelr-payment-success">
				<h2><?php esc_html_e('Ready', 'panelr-for-woocommerce'); ?></h2>
				<p><?php esc_html_e('Your service is set up. Your connection details are in your inbox.', 'panelr-for-woocommerce'); ?></p>
			</div>
		<?php elseif (in_array($panelr_status, ['canceled', 'payment_failed'], true)): ?>
			<div class="panelr-portal__error">
				<h2><?php echo esc_html(Panelr_Helpers::order_status_label($panelr_status)); ?></h2>
				<p><?php esc_html_e('Please contact us if you think this is a mistake.', 'panelr-for-woocommerce'); ?></p>
			</div>
		<?php else: ?>
			<h2><?php echo $submitted ? esc_html__('Payment confirmation received', 'panelr-for-woocommerce') : esc_html__('Complete your payment', 'panelr-for-woocommerce'); ?></h2>

			<div class="panelr-reference-box">
				<p class="panelr-reference-label"><?php esc_html_e('Your order reference', 'panelr-for-woocommerce'); ?></p>
				<p class="panelr-reference-code"><?php echo esc_html($ref); ?></p>
				<p class="panelr-reference-note"><?php esc_html_e('Include it in the note or memo of your payment.', 'panelr-for-woocommerce'); ?></p>
			</div>

			<?php if ($instructions): ?>
				<div class="panelr-payment-instructions">
					<p class="panelr-instructions-label"><?php esc_html_e('How to pay', 'panelr-for-woocommerce'); ?><?php if ($pm): ?> &mdash; <?php echo esc_html($pm['display_label'] ?: $pm['name']); ?><?php endif; ?></p>
					<?php if ($instructions['note']): ?>
						<p class="panelr-instructions-note"><?php echo nl2br(esc_html($instructions['note'])); ?></p>
					<?php endif; ?>
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
					<div class="panelr-qr-wrap">
						<p class="panelr-qr-label"><?php esc_html_e('Scan to pay', 'panelr-for-woocommerce'); ?></p>
						<div id="panelr-qr-code" data-qr="<?php echo esc_attr($instructions['qr']); ?>"></div>
					</div>
				<?php endif; ?>
				<p class="panelr-amount-due"><?php esc_html_e('Amount to send', 'panelr-for-woocommerce'); ?> <span class="panelr-amount"><?php echo wp_kses_post(wc_price($instructions['amount'], ['currency' => $instructions['currency']])); ?></span></p>
			<?php endif; ?>

			<?php if ($submitted): ?>
				<div class="panelr-payment-success"><?php esc_html_e('Payment confirmation received. We will check it and set up your service shortly.', 'panelr-for-woocommerce'); ?></div>
			<?php endif; ?>

			<div id="panelr-payment-form" class="panelr-payment-form">
				<?php if (!$submitted): ?>
					<p class="panelr-portal__field">
						<label for="panelr_transaction_id"><?php echo esc_html($instructions['txid_label'] ?? __('Transaction id or reference', 'panelr-for-woocommerce')); ?></label>
						<input type="text" id="panelr_transaction_id" name="panelr_transaction_id" class="input-text">
					</p>
				<?php endif; ?>
				<p class="panelr-portal__field">
					<label for="panelr_customer_note"><?php esc_html_e('Note', 'panelr-for-woocommerce'); ?></label>
					<textarea id="panelr_customer_note" name="panelr_customer_note" rows="2" class="input-text"></textarea>
				</p>
				<p>
					<button type="button" id="panelr-submit-payment" class="button alt"><?php echo $submitted ? esc_html__('Add note', 'panelr-for-woocommerce') : esc_html__("I've paid", 'panelr-for-woocommerce'); ?></button>
					<span id="panelr-submit-result" class="panelr-submit-result" aria-live="polite"></span>
				</p>
			</div>
		<?php endif; ?>

	<?php elseif ($ref && $state !== 'handoff'): ?>
		<div class="panelr-thankyou__status <?php echo $done ? 'panelr-payment-success' : 'panelr-thankyou__pending'; ?>" id="panelr-order-progress">
			<h2 class="panelr-thankyou__status-label"><?php echo esc_html($done ? __('Ready', 'panelr-for-woocommerce') : Panelr_Helpers::order_status_label($panelr_status ?: 'queued')); ?></h2>
			<p class="panelr-thankyou__status-text"><?php echo $done
				? esc_html__('Your service is set up. Your connection details are in your inbox.', 'panelr-for-woocommerce')
				: esc_html__('Your service is being set up. This usually takes a minute; you can wait here or check your inbox.', 'panelr-for-woocommerce'); ?></p>
			<ul class="panelr-thankyou__lines" <?php echo $lines ? '' : 'hidden'; ?>>
				<?php foreach ($lines as $line): ?>
					<li><code><?php echo esc_html($line['username']); ?></code><?php if (!empty($line['service_name'])): ?> · <?php echo esc_html($line['service_name']); ?><?php endif; ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<?php if ($portal_url): ?>
		<p class="panelr-thankyou__portal"><a class="button" href="<?php echo esc_url($portal_url); ?>"><?php esc_html_e('Open my account', 'panelr-for-woocommerce'); ?></a></p>
	<?php endif; ?>
</div>
