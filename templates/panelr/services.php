<?php
/**
 * The services, each linking to its product category.
 * Override: woocommerce/panelr/services.php
 *
 * @var array $services [{name, category, plans, url}]
 */
defined('ABSPATH') || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- this file is included inside wc_get_template(); its variables are local, not global
?>
<ul class="panelr-services">
	<?php foreach ($services as $svc): ?>
		<li class="panelr-services__item">
			<?php if ($svc['url']): ?><a href="<?php echo esc_url($svc['url']); ?>"><?php echo esc_html($svc['name']); ?></a><?php else: ?><?php echo esc_html($svc['name']); ?><?php endif; ?>
			<span class="panelr-services__meta"><?php
				/* translators: %d: number of plans */
				echo esc_html(sprintf(_n('%d plan', '%d plans', $svc['plans'], 'panelr-for-woocommerce'), $svc['plans'])); ?><?php if ($svc['category']): ?> · <?php echo esc_html($svc['category']); ?><?php endif; ?></span>
		</li>
	<?php endforeach; ?>
</ul>
