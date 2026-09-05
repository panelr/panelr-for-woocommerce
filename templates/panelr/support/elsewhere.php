<?php
/**
 * Support lives on Panelr's own pages.
 * Override: woocommerce/panelr/support/elsewhere.php
 *
 * @var string $url
 */
defined('ABSPATH') || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- this file is included inside wc_get_template(); its variables are local, not global
?>
<div class="panelr-support">
	<?php if ($url): ?>
		<p><a class="button panelr-renew-btn" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener"><?php esc_html_e('Open support', 'panelr-for-woocommerce'); ?></a></p>
	<?php else: ?>
		<p><?php esc_html_e('Support is not available right now.', 'panelr-for-woocommerce'); ?></p>
	<?php endif; ?>
</div>
