<?php
/**
 * "Add another service" on a product page.
 * Override: woocommerce/panelr/product-addons.php
 *
 * @var array  $groups   plugin_id => rows
 * @var array  $services plugin_id => service
 * @var string $nonce
 */
defined('ABSPATH') || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- this file is included inside wc_get_template(); its variables are local, not global
?>
<section class="panelr-addons" data-nonce="<?php echo esc_attr($nonce); ?>">
	<h2 class="panelr-addons__title"><?php esc_html_e('Add another service', 'panelr-for-woocommerce'); ?></h2>
	<?php foreach ($groups as $plugin_id => $rows): ?>
		<div class="panelr-addons__group">
			<h3 class="panelr-addons__service"><?php echo esc_html($services[$plugin_id]['name'] ?? ''); ?></h3>
			<ul class="panelr-addons__list">
				<?php foreach ($rows as $row): ?>
					<li class="panelr-addons__item">
						<span class="panelr-addons__name"><?php echo esc_html($row['name']); ?></span>
						<span class="panelr-addons__meta"><?php echo esc_html(Panelr_Helpers::plan_summary($row['connections'], $row['duration_months'])); ?></span>
						<button type="button" class="button panelr-addon-add" data-panelr-product-id="<?php echo (int) $row['panelr_id']; ?>">
							<?php
							printf(
								/* translators: %s: price */
								esc_html__('Add · %s', 'panelr-for-woocommerce'),
								wp_kses_post(wc_price($row['price']))
							);
							?>
						</button>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endforeach; ?>
	<p class="panelr-addons__result" aria-live="polite"></p>
</section>
