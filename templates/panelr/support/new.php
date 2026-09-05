<?php
/**
 * Open a ticket.
 * Override: woocommerce/panelr/support/new.php
 *
 * @var array  $topics
 * @var array  $lines   activation_id => line
 * @var string $base
 * @var string $nonce
 * @var bool   $multi
 */
defined('ABSPATH') || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- this file is included inside wc_get_template(); its variables are local, not global
?>
<div class="panelr-support">
	<section class="panelr-portal__section">
		<h3><?php esc_html_e('New ticket', 'panelr-for-woocommerce'); ?></h3>
		<form method="post" class="panelr-support__form">
			<input type="hidden" name="panelr_support_action" value="create">
			<input type="hidden" name="panelr_support_nonce" value="<?php echo esc_attr($nonce); ?>">
			<input type="hidden" name="panelr_support_base" value="<?php echo esc_attr($base); ?>">
			<p class="panelr-portal__field">
				<label for="panelr-ticket-topic"><?php esc_html_e('Topic', 'panelr-for-woocommerce'); ?></label>
				<select id="panelr-ticket-topic" name="topic" class="input-text" required>
					<?php foreach ($topics as $topic): ?>
						<option value="<?php echo esc_attr($topic); ?>"><?php echo esc_html($topic); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<?php if ($lines): ?>
				<p class="panelr-portal__field">
					<label for="panelr-ticket-line"><?php esc_html_e('Connection', 'panelr-for-woocommerce'); ?></label>
					<select id="panelr-ticket-line" name="activation_id" class="input-text">
						<option value="0"><?php esc_html_e('Not about one connection', 'panelr-for-woocommerce'); ?></option>
						<?php foreach ($lines as $id => $line): ?>
							<option value="<?php echo (int) $id; ?>"><?php echo esc_html(Panelr_Helpers::line_display($line)); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
			<?php endif; ?>
			<p class="panelr-portal__field">
				<label for="panelr-ticket-subject"><?php esc_html_e('Subject', 'panelr-for-woocommerce'); ?></label>
				<input type="text" id="panelr-ticket-subject" name="subject" class="input-text" maxlength="150" required>
			</p>
			<p class="panelr-portal__field">
				<label for="panelr-ticket-message"><?php esc_html_e('Message', 'panelr-for-woocommerce'); ?></label>
				<textarea id="panelr-ticket-message" name="message" class="input-text" rows="6" required></textarea>
			</p>
			<p>
				<button type="submit" class="button panelr-renew-btn"><?php esc_html_e('Send', 'panelr-for-woocommerce'); ?></button>
				<a class="button" href="<?php echo esc_url($base); ?>"><?php esc_html_e('Cancel', 'panelr-for-woocommerce'); ?></a>
			</p>
		</form>
	</section>
</div>
