<?php
/**
 * The page Panelr's checkout sends a customer to. Normally they never see
 * it (they are sent straight on to WooCommerce checkout); this shows when
 * the order could not be loaded, or someone opened the page by hand.
 * Override: woocommerce/panelr/checkout-landing.php
 *
 * @var string $error
 * @var string $panelr_url
 * @var string $shop_url
 */
defined('ABSPATH') || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- this file is included inside wc_get_template(); its variables are local, not global
?>
<div class="panelr-order-status panelr-checkout-landing">
	<?php if ($error): ?>
		<div class="panelr-portal__error" role="alert"><?php echo esc_html($error); ?></div>
	<?php else: ?>
		<p><?php esc_html_e('Nothing to pay here yet. Start from the store or from your order link.', 'panelr-for-woocommerce'); ?></p>
	<?php endif; ?>
	<p class="panelr-portal__actions">
		<?php if ($panelr_url): ?><a class="button panelr-renew-btn" href="<?php echo esc_url($panelr_url); ?>"><?php esc_html_e('Back to the store', 'panelr-for-woocommerce'); ?></a><?php endif; ?>
		<?php if ($shop_url): ?><a class="button" href="<?php echo esc_url($shop_url); ?>"><?php esc_html_e('See plans', 'panelr-for-woocommerce'); ?></a><?php endif; ?>
	</p>
</div>
