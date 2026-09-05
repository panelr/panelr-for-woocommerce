<?php
/**
 * Choose a new password (from the emailed link).
 * Override: woocommerce/panelr/portal/reset.php
 *
 * @var string $token
 */
defined('ABSPATH') || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- this file is included inside wc_get_template(); its variables are local, not global
?>
<div class="panelr-portal panelr-portal--signed-out">
	<div class="panelr-portal__login">
		<h3><?php esc_html_e('Choose a new password', 'panelr-for-woocommerce'); ?></h3>
		<p id="panelr-reset-error" class="panelr-portal__error woocommerce-error" hidden role="alert"></p>
		<input type="hidden" id="panelr-reset-token" value="<?php echo esc_attr($token); ?>">
		<p class="panelr-portal__field">
			<label for="panelr-reset-password"><?php esc_html_e('New password', 'panelr-for-woocommerce'); ?></label>
			<input type="password" id="panelr-reset-password" class="input-text" autocomplete="new-password">
		</p>
		<p class="panelr-portal__field">
			<label for="panelr-reset-password2"><?php esc_html_e('New password again', 'panelr-for-woocommerce'); ?></label>
			<input type="password" id="panelr-reset-password2" class="input-text" autocomplete="new-password">
		</p>
		<p class="panelr-portal__field">
			<button type="button" id="panelr-reset-btn" class="button"><?php esc_html_e('Save and sign in', 'panelr-for-woocommerce'); ?></button>
		</p>
	</div>
</div>
