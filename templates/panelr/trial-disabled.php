<?php
/**
 * Shown when trials are off.
 * Override: woocommerce/panelr/trial-disabled.php
 */
defined('ABSPATH') || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- this file is included inside wc_get_template(); its variables are local, not global
?>
<p class="panelr-trial__disabled"><?php esc_html_e('Free trials are not available right now.', 'panelr-for-woocommerce'); ?></p>
