<?php
/**
 * The Panelr coupon box and the "Invited by a member" line in cart totals.
 * Override: woocommerce/panelr/cart-extras.php
 *
 * @var string     $coupon_mode woocommerce|panelr
 * @var array|null $coupon      {code,label,discount}
 * @var bool       $invited
 * @var int        $credits
 */
defined('ABSPATH') || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- this file is included inside wc_get_template(); its variables are local, not global
?>
<?php if ($coupon_mode === 'panelr'): ?>
	<tr class="panelr-coupon-row">
		<th><?php esc_html_e('Discount code', 'panelr-for-woocommerce'); ?></th>
		<td>
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
		</td>
	</tr>
<?php endif; ?>
<?php if ($invited): ?>
	<tr class="panelr-invited-row">
		<th><?php esc_html_e('Invite', 'panelr-for-woocommerce'); ?></th>
		<td><?php esc_html_e('Invited by a member', 'panelr-for-woocommerce'); ?></td>
	</tr>
<?php endif; ?>
<?php if ($credits): ?>
	<tr class="panelr-credits-row">
		<th><?php esc_html_e('Credits', 'panelr-for-woocommerce'); ?></th>
		<td><?php
			printf(
				/* translators: %d: credits */
				esc_html(_n('%d credit will be used', '%d credits will be used', $credits, 'panelr-for-woocommerce')),
				(int) $credits
			);
		?></td>
	</tr>
<?php endif; ?>
