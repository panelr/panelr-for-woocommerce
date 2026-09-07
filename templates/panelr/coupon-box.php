<?php
/**
 * The Panelr discount-code box above the block cart / block checkout, where
 * WooCommerce's classic totals hooks never fire.
 *
 * @var array|null $coupon {code,label,discount}
 * @var bool       $invited
 */
defined('ABSPATH') || exit;
?>
<div class="panelr-coupon-box panelr-coupon-row">
	<span class="panelr-coupon-box__label"><?php esc_html_e('Discount code', 'panelr-for-woocommerce'); ?></span>
	<?php if ($coupon): ?>
		<span class="panelr-coupon-row__applied"><strong><?php echo esc_html(strtoupper($coupon['code'])); ?></strong> · <?php echo esc_html($coupon['label']); ?></span>
		<button type="button" class="button panelr-coupon-remove"><?php esc_html_e('Remove', 'panelr-for-woocommerce'); ?></button>
	<?php else: ?>
		<span class="panelr-coupon-row__form">
			<input type="text" class="input-text panelr-coupon-code" placeholder="<?php esc_attr_e('Code', 'panelr-for-woocommerce'); ?>" autocomplete="off">
			<button type="button" class="button panelr-coupon-apply"><?php esc_html_e('Apply', 'panelr-for-woocommerce'); ?></button>
		</span>
	<?php endif; ?>
	<span class="panelr-coupon-row__result" aria-live="polite"></span>
	<?php if ($invited): ?>
		<span class="panelr-coupon-box__invited"><?php esc_html_e('Invited by a member — your invite code is on this order.', 'panelr-for-woocommerce'); ?></span>
	<?php endif; ?>
</div>
