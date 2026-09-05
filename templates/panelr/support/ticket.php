<?php
/**
 * One ticket and its conversation.
 * Override: woocommerce/panelr/support/ticket.php
 *
 * @var array  $ticket
 * @var string $base
 * @var string $nonce
 */
defined('ABSPATH') || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- this file is included inside wc_get_template(); its variables are local, not global
$closed = in_array((string) $ticket['status'], ['closed', 'resolved'], true);
?>
<div class="panelr-support">
	<p><a href="<?php echo esc_url($base); ?>">&larr; <?php esc_html_e('All tickets', 'panelr-for-woocommerce'); ?></a></p>

	<section class="panelr-portal__section panelr-ticket">
		<div class="panelr-ticket__head">
			<h3><?php echo esc_html($ticket['subject']); ?></h3>
			<span class="panelr-portal__status panelr-portal__status--<?php echo esc_attr($ticket['status']); ?>"><?php echo esc_html($ticket['status_label']); ?></span>
		</div>
		<p class="panelr-ticket__meta"><?php echo esc_html($ticket['topic']); ?> · <?php echo esc_html($ticket['ticket_ref']); ?> · <?php echo esc_html(Panelr_Helpers::format_date($ticket['created_at'] ?? null, true)); ?></p>

		<ol class="panelr-ticket__thread">
			<?php foreach ((array) ($ticket['replies'] ?? []) as $reply): ?>
				<li class="panelr-ticket__message panelr-ticket__message--<?php echo esc_attr($reply['from']); ?>">
					<div class="panelr-ticket__from"><?php echo $reply['from'] === 'support' ? esc_html__('Support', 'panelr-for-woocommerce') : esc_html__('You', 'panelr-for-woocommerce'); ?> · <?php echo esc_html(Panelr_Helpers::format_date($reply['created_at'] ?? null, true)); ?></div>
					<div class="panelr-ticket__text"><?php echo nl2br(esc_html($reply['message'])); ?></div>
				</li>
			<?php endforeach; ?>
		</ol>

		<form method="post" class="panelr-support__form">
			<input type="hidden" name="panelr_support_action" value="reply">
			<input type="hidden" name="panelr_support_nonce" value="<?php echo esc_attr($nonce); ?>">
			<input type="hidden" name="panelr_support_base" value="<?php echo esc_attr($base); ?>">
			<input type="hidden" name="ticket_ref" value="<?php echo esc_attr($ticket['ticket_ref']); ?>">
			<p class="panelr-portal__field">
				<label for="panelr-reply-message"><?php echo $closed ? esc_html__('Reply to reopen', 'panelr-for-woocommerce') : esc_html__('Reply', 'panelr-for-woocommerce'); ?></label>
				<textarea id="panelr-reply-message" name="message" class="input-text" rows="4" required></textarea>
			</p>
			<p><button type="submit" class="button panelr-renew-btn"><?php echo $closed ? esc_html__('Reopen with this reply', 'panelr-for-woocommerce') : esc_html__('Send reply', 'panelr-for-woocommerce'); ?></button></p>
		</form>

		<?php if (!$closed): ?>
			<form method="post" class="panelr-support__close">
				<input type="hidden" name="panelr_support_action" value="close">
				<input type="hidden" name="panelr_support_nonce" value="<?php echo esc_attr($nonce); ?>">
				<input type="hidden" name="panelr_support_base" value="<?php echo esc_attr($base); ?>">
				<input type="hidden" name="ticket_ref" value="<?php echo esc_attr($ticket['ticket_ref']); ?>">
				<p><button type="submit" class="button"><?php esc_html_e('Close ticket', 'panelr-for-woocommerce'); ?></button></p>
			</form>
		<?php endif; ?>
	</section>
</div>
