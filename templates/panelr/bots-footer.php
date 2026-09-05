<?php
/**
 * The bot buttons in the site footer.
 * Override: woocommerce/panelr/bots-footer.php
 *
 * @var array $links [{platform,label,url}]
 */
defined('ABSPATH') || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- this file is included inside wc_get_template(); its variables are local, not global
?>
<div class="panelr-bots-footer">
	<?php foreach ($links as $link): ?>
		<a class="button panelr-bots-footer__link panelr-bots-footer__link--<?php echo esc_attr($link['platform']); ?>" href="<?php echo esc_url($link['url']); ?>" target="_blank" rel="noopener"><?php echo esc_html($link['label']); ?></a>
	<?php endforeach; ?>
</div>
