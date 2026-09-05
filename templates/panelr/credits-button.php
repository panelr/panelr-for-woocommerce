<?php
/**
 * "Pay with N credits" under the add-to-cart button.
 * Override: woocommerce/panelr/credits-button.php
 *
 * @var WC_Product $product
 * @var int        $cost
 * @var int        $balance
 */
defined('ABSPATH') || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- this file is included inside wc_get_template(); its variables are local, not global
?>
<div class="panelr-credits-buy">
	<button type="button" class="button panelr-credits-buy__button" data-product-id="<?php echo (int) $product->get_id(); ?>">
		<?php
		printf(
			/* translators: %d: credits */
			esc_html(_n('Pay with %d credit', 'Pay with %d credits', $cost, 'panelr-for-woocommerce')),
			(int) $cost
		);
		?>
	</button>
	<span class="panelr-credits-buy__result" aria-live="polite"></span>
</div>
