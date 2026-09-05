<?php
/**
 * Plans as a pricing grid, per service.
 * Override: woocommerce/panelr/plans.php
 *
 * @var array $groups    plugin_id => {name, lines[]}
 * @var int   $columns
 * @var bool  $heading
 * @var bool  $single
 * @var bool  $signed_in
 * @var int   $balance
 * @var string $nonce
 */
defined('ABSPATH') || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- this file is included inside wc_get_template(); its variables are local, not global
?>
<div class="panelr-plans panelr-addons" data-nonce="<?php echo esc_attr($nonce); ?>">
	<?php if (!$groups): ?>
		<p class="panelr-plans__empty"><?php esc_html_e('No plans to show yet.', 'panelr-for-woocommerce'); ?></p>
	<?php endif; ?>
	<?php foreach ($groups as $plugin_id => $group): ?>
		<section class="panelr-plans__service">
			<?php if ($heading && $group['name']): ?><h2 class="panelr-plans__title"><?php echo esc_html($group['name']); ?></h2><?php endif; ?>
			<ul class="panelr-plans__grid panelr-plans__grid--<?php echo (int) $columns; ?>">
				<?php foreach ($group['lines'] as $plan): ?>
					<li class="panelr-plan">
						<h3 class="panelr-plan__name"><a href="<?php echo esc_url($plan['url']); ?>"><?php echo esc_html($plan['name']); ?></a></h3>
						<p class="panelr-plan__summary"><?php echo esc_html(Panelr_Helpers::plan_summary($plan['connections'], $plan['duration_months'])); ?></p>
						<?php if ($plan['short_description']): ?>
							<div class="panelr-plan__text"><?php echo wp_kses_post(wpautop($plan['short_description'])); ?></div>
						<?php endif; ?>
						<p class="panelr-plan__buy">
							<button type="button" class="button panelr-addon-add panelr-plan__button" data-panelr-product-id="<?php echo (int) $plan['panelr_id']; ?>"><?php echo wp_kses_post(sprintf(
								/* translators: %s: price */
								__('Add to cart · %s', 'panelr-for-woocommerce'),
								wc_price($plan['price'])
							)); ?></button>
							<?php if ($signed_in && $plan['cost_points'] > 0 && $balance >= $plan['cost_points']): ?>
								<button type="button" class="button panelr-credits-buy__button" data-product-id="<?php echo (int) $plan['wc_id']; ?>"><?php echo esc_html(sprintf(
									/* translators: %d: credits */
									_n('Pay with %d credit', 'Pay with %d credits', $plan['cost_points'], 'panelr-for-woocommerce'),
									$plan['cost_points']
								)); ?></button>
							<?php endif; ?>
						</p>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endforeach; ?>
	<p class="panelr-addons__result" aria-live="polite"></p>
</div>
