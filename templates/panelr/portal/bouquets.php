<?php
/**
 * Channel packages for one line (loaded on request).
 * Override: woocommerce/panelr/portal/bouquets.php
 *
 * @var int          $activation_id
 * @var string       $mode      editor|panel
 * @var array|object $bouquets  editor: {live,vod,series}; panel: flat list
 * @var array        $current   bouquet ids on the line
 */
defined('ABSPATH') || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- this file is included inside wc_get_template(); its variables are local, not global
$is_editor = $mode === 'editor';
?>
<div class="panelr-bouquets" data-mode="<?php echo esc_attr($is_editor ? 'editor' : 'panel'); ?>" data-activation-id="<?php echo (int) $activation_id; ?>">
	<p class="panelr-bouquets__error panelr-portal__error" hidden role="alert"></p>
	<?php if ($is_editor): ?>
		<?php
		$categories = ['live' => __('Live TV', 'panelr-for-woocommerce'), 'vod' => __('Movies', 'panelr-for-woocommerce'), 'series' => __('Series', 'panelr-for-woocommerce')];
		$categories = array_filter($categories, fn($cat) => !empty($bouquets[$cat]), ARRAY_FILTER_USE_KEY);
		$first = (string) array_key_first($categories);
		?>
		<p class="panelr-bouquets__tabs" role="tablist">
			<?php foreach ($categories as $cat => $label):
				$on = count(array_filter((array) $bouquets[$cat], fn($bq) => empty($current) || in_array((int) $bq['id'], $current, true))); ?>
				<button type="button" role="tab" class="button panelr-bouquets__tab <?php echo $cat === $first ? 'is-active' : ''; ?>" aria-selected="<?php echo $cat === $first ? 'true' : 'false'; ?>" data-category="<?php echo esc_attr($cat); ?>"><?php echo esc_html($label); ?> <span class="panelr-bouquets__count" data-total="<?php echo count((array) $bouquets[$cat]); ?>"><?php echo (int) $on; ?>/<?php echo count((array) $bouquets[$cat]); ?></span></button>
			<?php endforeach; ?>
		</p>
		<?php foreach ($categories as $cat => $label):
			$list = (array) $bouquets[$cat]; ?>
			<div class="panelr-wizard-step panelr-bouquets__group" data-category="<?php echo esc_attr($cat); ?>" <?php echo $cat === $first ? '' : 'hidden'; ?>>
				<h4><button type="button" class="button panelr-bouquets__all"><?php esc_html_e('All', 'panelr-for-woocommerce'); ?></button> <button type="button" class="button panelr-bouquets__none"><?php esc_html_e('None', 'panelr-for-woocommerce'); ?></button></h4>
				<ul class="panelr-portal__bouquet-list">
					<?php foreach ($list as $bq): ?>
						<li><label><input type="checkbox" class="panelr-bouquet-cb" data-category="<?php echo esc_attr($cat); ?>" value="<?php echo (int) $bq['id']; ?>" <?php checked(empty($current) || in_array((int) $bq['id'], $current, true)); ?>> <?php echo esc_html($bq['display_name'] ?: $bq['name']); ?></label></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endforeach; ?>
	<?php else: ?>
		<ul class="panelr-portal__bouquet-list">
			<?php foreach ((array) $bouquets as $bq): ?>
				<li><label><input type="checkbox" class="panelr-bouquet-cb" value="<?php echo (int) $bq['id']; ?>" <?php checked(in_array((int) $bq['id'], $current, true)); ?>> <?php echo esc_html($bq['display_name'] ?: $bq['name']); ?></label></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
	<p>
		<button type="button" class="button panelr-renew-btn panelr-bouquets__save"><?php esc_html_e('Save channels', 'panelr-for-woocommerce'); ?></button>
		<span class="panelr-bouquets__result panelr-portal__result" aria-live="polite"></span>
	</p>
</div>
