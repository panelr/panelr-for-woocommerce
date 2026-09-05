<?php
/**
 * Apps are for members: ask them to sign in.
 * Override: woocommerce/panelr/apps-sign-in.php
 *
 * @var string $sign_in_url
 */
defined('ABSPATH') || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- this file is included inside wc_get_template(); its variables are local, not global
?>
<div class="panelr-apps panelr-apps--members">
	<p><?php esc_html_e('App downloads are for members.', 'panelr-for-woocommerce'); ?></p>
	<p><a class="button panelr-renew-btn" href="<?php echo esc_url($sign_in_url); ?>"><?php esc_html_e('Sign in', 'panelr-for-woocommerce'); ?></a></p>
</div>
