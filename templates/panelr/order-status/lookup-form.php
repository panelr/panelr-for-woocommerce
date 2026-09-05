<?php
/**
 * Order lookup by reference and email.
 * Override: woocommerce/panelr/order-status/lookup-form.php
 *
 * @var string $error
 */
defined('ABSPATH') || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- this file is included inside wc_get_template(); its variables are local, not global
?>
<div class="panelr-order-status">
	<?php if ($error): ?>
		<div class="panelr-portal__error" role="alert"><?php echo esc_html($error); ?></div>
	<?php endif; ?>
	<form method="post" class="panelr-order-status__lookup">
		<?php wp_nonce_field('panelr_order_lookup', 'panelr_lookup_nonce'); ?>
		<p class="panelr-portal__field">
			<label for="panelr-lookup-ref"><?php esc_html_e('Order reference', 'panelr-for-woocommerce'); ?></label>
			<input type="text" id="panelr-lookup-ref" name="panelr_ref" class="input-text" placeholder="WO-2026-00000" required>
		</p>
		<p class="panelr-portal__field">
			<label for="panelr-lookup-email"><?php esc_html_e('Email', 'panelr-for-woocommerce'); ?></label>
			<input type="email" id="panelr-lookup-email" name="panelr_email" class="input-text" required>
		</p>
		<p>
			<button type="submit" class="button panelr-renew-btn"><?php esc_html_e('Find my order', 'panelr-for-woocommerce'); ?></button>
		</p>
	</form>
</div>
