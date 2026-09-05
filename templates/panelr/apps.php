<?php
/**
 * The Apps page.
 * Override: woocommerce/panelr/apps.php
 *
 * @var array  $sections     [{name, icon, logo_url, apps[]}]
 * @var bool   $filtered     narrowed to the member's services
 * @var string $show_all_url
 * @var bool   $multi
 */
defined('ABSPATH') || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- this file is included inside wc_get_template(); its variables are local, not global
?>
<div class="panelr-apps">
	<?php if ($filtered): ?>
		<p class="panelr-apps__filter"><?php esc_html_e('Showing apps for your services.', 'panelr-for-woocommerce'); ?> <a href="<?php echo esc_url($show_all_url); ?>"><?php esc_html_e('Show all', 'panelr-for-woocommerce'); ?></a></p>
	<?php endif; ?>

	<?php if (!$sections): ?>
		<p><?php esc_html_e('No apps to show yet.', 'panelr-for-woocommerce'); ?></p>
	<?php endif; ?>

	<?php foreach ($sections as $section): ?>
		<section class="panelr-apps__section panelr-portal__section">
			<h3 class="panelr-apps__platform">
				<?php if (!empty($section['logo_url'])): ?><img class="panelr-apps__platform-logo" src="<?php echo esc_url($section['logo_url']); ?>" alt=""><?php endif; ?>
				<?php echo esc_html($section['name']); ?>
			</h3>
			<ul class="panelr-apps__list">
				<?php foreach ($section['apps'] as $app): ?>
					<li class="panelr-app">
						<?php if (!empty($app['logo_url'])): ?>
							<img class="panelr-app__logo" src="<?php echo esc_url($app['logo_url']); ?>" alt="">
						<?php endif; ?>
						<div class="panelr-app__body">
							<h4 class="panelr-app__name"><?php echo esc_html($app['name']); ?></h4>
							<?php if (!empty($app['description'])): ?><p class="panelr-app__description"><?php echo esc_html($app['description']); ?></p><?php endif; ?>
							<?php if ($multi && !empty($app['services'])): ?>
								<p class="panelr-app__services"><?php echo esc_html(implode(', ', array_map(fn($s) => $s['name'], $app['services']))); ?></p>
							<?php endif; ?>
							<p class="panelr-app__actions">
								<?php if (!empty($app['download_url'])): ?>
									<a class="button panelr-app__download" href="<?php echo esc_url($app['download_url']); ?>" target="_blank" rel="noopener"><?php esc_html_e('Download', 'panelr-for-woocommerce'); ?></a>
								<?php endif; ?>
								<?php if (!empty($app['downloader_code'])): ?>
									<span class="panelr-app__code"><?php esc_html_e('Downloader code', 'panelr-for-woocommerce'); ?> <code class="panelr-portal__code"><?php echo esc_html($app['downloader_code']); ?></code>
									<button type="button" class="button panelr-copy-btn" data-copy="<?php echo esc_attr($app['downloader_code']); ?>"><?php esc_html_e('Copy', 'panelr-for-woocommerce'); ?></button></span>
								<?php endif; ?>
							</p>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endforeach; ?>
</div>
