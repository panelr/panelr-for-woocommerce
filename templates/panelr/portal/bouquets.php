<?php
/**
 * Channel packages for one line (loaded on request).
 * Override: woocommerce/panelr/portal/bouquets.php
 *
 * @var int          $activation_id
 * @var string       $mode      editor|panel
 * @var array|object $bouquets  editor: {live,vod,series}; panel: flat list
 * @var array        $current   bouquet ids on the line
 * @var array        $groups    editor mode: {id, name, category, icon_url, icon_text, bouquet_ids}[] — one choice standing for several bouquets
 * @var array        $current_groups  group ids picked on the line
 */
defined('ABSPATH') || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- this file is included inside wc_get_template(); its variables are local, not global
$is_editor = $mode === 'editor';
$groups = isset($groups) && is_array($groups) ? $groups : [];
$current_groups = isset($current_groups) && is_array($current_groups) ? array_map('intval', $current_groups) : [];

// Per section: its groups, the bouquet ids they stand for, and the bouquets left to list on their own.
$section_groups = [];
$hidden_ids     = [];
foreach ($groups as $g) {
	$cat = (string) ($g['category'] ?? '');
	$ids = array_map('intval', (array) ($g['bouquet_ids'] ?? []));
	if ($cat === '' || !$ids) continue;
	$section_groups[$cat][] = $g;
	$hidden_ids[$cat] = array_merge($hidden_ids[$cat] ?? [], $ids);
}
// A group is on when nothing is stored (everything on), when it was picked, or when every bouquet in it is on.
$group_on = function (array $g) use ($current, $current_groups): bool {
	if (empty($current)) return true;
	if (in_array((int) ($g['id'] ?? 0), $current_groups, true)) return true;
	$ids = array_map('intval', (array) ($g['bouquet_ids'] ?? []));
	return $ids && !array_diff($ids, $current);
};
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
				$singles = array_filter((array) $bouquets[$cat], fn($bq) => !in_array((int) $bq['id'], $hidden_ids[$cat] ?? [], true));
				$on = count(array_filter($singles, fn($bq) => empty($current) || in_array((int) $bq['id'], $current, true)))
					+ count(array_filter($section_groups[$cat] ?? [], $group_on));
				$total = count($singles) + count($section_groups[$cat] ?? []); ?>
				<button type="button" role="tab" class="button panelr-bouquets__tab <?php echo $cat === $first ? 'is-active' : ''; ?>" aria-selected="<?php echo $cat === $first ? 'true' : 'false'; ?>" data-category="<?php echo esc_attr($cat); ?>"><?php echo esc_html($label); ?> <span class="panelr-bouquets__count" data-total="<?php echo (int) $total; ?>"><?php echo (int) $on; ?>/<?php echo (int) $total; ?></span></button>
			<?php endforeach; ?>
		</p>
		<?php foreach ($categories as $cat => $label):
			$list = (array) $bouquets[$cat]; ?>
			<?php $cat_groups = $section_groups[$cat] ?? []; $singles = array_filter($list, fn($bq) => !in_array((int) $bq['id'], $hidden_ids[$cat] ?? [], true)); ?>
			<div class="panelr-wizard-step panelr-bouquets__group" data-category="<?php echo esc_attr($cat); ?>" <?php echo $cat === $first ? '' : 'hidden'; ?>>
				<h4><button type="button" class="button panelr-bouquets__all"><?php esc_html_e('All', 'panelr-for-woocommerce'); ?></button> <button type="button" class="button panelr-bouquets__none"><?php esc_html_e('None', 'panelr-for-woocommerce'); ?></button></h4>
				<?php if ($cat_groups): ?>
					<p class="panelr-bouquets__heading"><?php echo esc_html(Panelr_Wording::term('groups')); ?></p>
					<ul class="panelr-portal__bouquet-list panelr-portal__bouquet-list--groups">
						<?php foreach ($cat_groups as $g): $members = array_map('intval', (array) $g['bouquet_ids']); ?>
							<li class="panelr-bouquet-group"><label>
								<input type="checkbox" class="panelr-bouquet-cb panelr-bouquet-cb--group" data-category="<?php echo esc_attr($cat); ?>" data-group="1" value="<?php echo (int) $g['id']; ?>" data-members="<?php echo esc_attr(implode(',', $members)); ?>" <?php checked($group_on($g)); ?>>
								<?php if (!empty($g['icon_url'])): ?>
									<img class="panelr-bouquet-group__icon" src="<?php echo esc_url((string) $g['icon_url']); ?>" alt="">
								<?php elseif (!empty($g['icon_text'])): ?>
									<span class="panelr-bouquet-group__badge"><?php echo esc_html((string) $g['icon_text']); ?></span>
								<?php else: ?>
									<span class="panelr-bouquet-group__badge panelr-bouquet-group__badge--none" aria-hidden="true">&#9776;</span>
								<?php endif; ?>
								<strong class="panelr-bouquet-group__name"><?php echo esc_html((string) $g['name']); ?></strong>
								<span class="panelr-bouquet-group__count"><?php echo count($members); ?></span>
							</label></li>
						<?php endforeach; ?>
					</ul>
					<?php if ($singles): ?><p class="panelr-bouquets__heading"><?php echo esc_html(Panelr_Wording::term('individual')); ?></p><?php endif; ?>
				<?php endif; ?>
				<ul class="panelr-portal__bouquet-list">
					<?php foreach ($singles as $bq): ?>
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
