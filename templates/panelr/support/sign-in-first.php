<?php
/**
 * Support needs a signed-in member.
 * Override: woocommerce/panelr/support/sign-in-first.php
 *
 * @var string $sign_in_url
 */
defined('ABSPATH') || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- this file is included inside wc_get_template(); its variables are local, not global
?>
<div class="panelr-support">
	<p><?php esc_html_e('Sign in to see your tickets or open a new one.', 'panelr-for-woocommerce'); ?></p>
	<p><a class="button panelr-renew-btn" href="<?php echo esc_url($sign_in_url); ?>"><?php esc_html_e('Sign in', 'panelr-for-woocommerce'); ?></a></p>
</div>
