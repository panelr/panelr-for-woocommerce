<?php
/**
 * Connections, grouped by service.
 * Override: woocommerce/panelr/portal/tab-connections.php
 *
 * @var array  $groups   plugin_id => {name, lines[]}
 * @var bool   $multi
 * @var string $shop_url
 */
defined('ABSPATH') || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- this file is included inside wc_get_template(); its variables are local, not global
?>
<?php if (!$groups): ?>
	<section class="panelr-portal__section">
		<p><?php esc_html_e('No connections on this account yet.', 'panelr-for-woocommerce'); ?></p>
		<?php if ($shop_url): ?><p><a class="button panelr-renew-btn" href="<?php echo esc_url($shop_url); ?>"><?php esc_html_e('See plans', 'panelr-for-woocommerce'); ?></a></p><?php endif; ?>
	</section>
<?php endif; ?>

<?php foreach ($groups as $plugin_id => $group): ?>
	<?php if ($multi && !empty($group['name'])): ?>
		<h3 class="panelr-portal__service-heading"><?php echo esc_html($group['name']); ?></h3>
	<?php endif; ?>

	<?php foreach ($group['lines'] as $line):
		$aid   = (int) $line['activation_id'];
		$label = (string) (($line['label'] ?? '') ?: $line['username']);
		$days  = $line['days_left'];
		?>
		<section class="panelr-portal__section panelr-line panelr-line--<?php echo esc_attr($line['status']); ?>" id="panelr-line-<?php echo (int) $aid; ?>" data-activation-id="<?php echo (int) $aid; ?>" data-username="<?php echo esc_attr($line['username']); ?>">
			<div class="panelr-line__head">
				<h3 class="panelr-line__title">
					<span class="panelr-line__label"><?php echo esc_html($label); ?></span>
					<button type="button" class="panelr-line__rename button" aria-label="<?php esc_attr_e('Rename', 'panelr-for-woocommerce'); ?>" title="<?php esc_attr_e('Rename', 'panelr-for-woocommerce'); ?>">&#9998;</button>
				</h3>
				<span class="panelr-portal__status panelr-portal__status--<?php echo esc_attr($line['status']); ?>"><?php echo esc_html(Panelr_Helpers::line_status_label($line['status'])); ?></span>
				<?php if (!empty($line['is_trial'])): ?><span class="panelr-portal__status panelr-portal__status--trial_active"><?php esc_html_e('Trial', 'panelr-for-woocommerce'); ?></span><?php endif; ?>
			</div>
			<div class="panelr-line__rename-form" hidden>
				<input type="text" class="input-text panelr-line__rename-input" value="<?php echo esc_attr((string) ($line['label'] ?? '')); ?>" maxlength="100" placeholder="<?php echo esc_attr($line['username']); ?>">
				<button type="button" class="button panelr-line__rename-save"><?php esc_html_e('Save', 'panelr-for-woocommerce'); ?></button>
				<button type="button" class="button panelr-line__rename-cancel"><?php esc_html_e('Cancel', 'panelr-for-woocommerce'); ?></button>
			</div>

			<table class="panelr-portal__table">
				<?php if ($label !== $line['username']): ?>
					<tr><th><?php esc_html_e('Username', 'panelr-for-woocommerce'); ?></th><td><code class="panelr-portal__code"><?php echo esc_html($line['username']); ?></code></td></tr>
				<?php endif; ?>
				<?php if (!empty($line['product_name'])): ?>
					<tr><th><?php esc_html_e('Plan', 'panelr-for-woocommerce'); ?></th><td><?php echo esc_html($line['product_name']); ?><?php if (!empty($line['connections'])): ?> · <?php echo esc_html(Panelr_Helpers::plan_summary((int) $line['connections'], 0)); ?><?php endif; ?></td></tr>
				<?php endif; ?>
				<tr>
					<th><?php esc_html_e('Ends', 'panelr-for-woocommerce'); ?></th>
					<td><?php echo esc_html(Panelr_Helpers::format_date($line['expiration_date'])); ?>
						<?php if ($days !== null && !$line['expired']): ?> · <?php
							/* translators: %d: number of days */
							echo esc_html(sprintf(_n('%d day left', '%d days left', (int) $days, 'panelr-for-woocommerce'), (int) $days)); ?><?php endif; ?>
					</td>
				</tr>
			</table>

			<p class="panelr-line__actions" role="tablist">
				<?php if ($line['plans']): ?>
					<button type="button" role="tab" aria-selected="false" class="button panelr-line__tab panelr-line-renew-btn" data-panel="renew"><?php echo !empty($line['is_trial']) ? esc_html__('Upgrade', 'panelr-for-woocommerce') : esc_html__('Renew', 'panelr-for-woocommerce'); ?></button>
				<?php endif; ?>
				<?php if (!empty($line['bouquets_on'])): ?>
					<button type="button" role="tab" aria-selected="false" class="button panelr-line__tab panelr-line-channels-btn" data-panel="channels"><?php esc_html_e('Channels', 'panelr-for-woocommerce'); ?></button>
				<?php endif; ?>
				<button type="button" role="tab" aria-selected="false" class="button panelr-line__tab panelr-line-details-btn" data-panel="details"><?php esc_html_e('Details', 'panelr-for-woocommerce'); ?></button>
			</p>

			<div class="panelr-line__panel panelr-line__details" hidden aria-live="polite"></div>
			<div class="panelr-line__panel panelr-line__channels" hidden aria-live="polite"></div>
			<?php if ($line['plans']): ?>
				<div class="panelr-line__panel panelr-line__renew" hidden>
					<p class="panelr-line__error panelr-portal__error" hidden role="alert"></p>
					<table class="panelr-portal__table">
						<?php foreach ($line['plans'] as $plan): ?>
							<tr>
								<td><?php echo esc_html($plan['name']); ?></td>
								<td><?php echo esc_html(Panelr_Helpers::plan_summary($plan['connections'], $plan['duration_months'])); ?></td>
								<td class="panelr-line__renew-buttons">
									<button type="button" class="button panelr-renew-btn panelr-renew-choose" data-panelr-product-id="<?php echo (int) $plan['panelr_id']; ?>"><?php echo wp_kses_post(sprintf(
										/* translators: %s: price */
										!empty($line['is_trial']) ? __('Upgrade · %s', 'panelr-for-woocommerce') : __('Renew · %s', 'panelr-for-woocommerce'),
										wc_price($plan['price'])
									)); ?></button>
									<?php if ($plan['cost_points'] > 0 && Panelr_Session::credits_balance() >= $plan['cost_points']): ?>
										<button type="button" class="button panelr-renew-choose" data-panelr-product-id="<?php echo (int) $plan['panelr_id']; ?>" data-credits="1"><?php echo esc_html(sprintf(
											/* translators: %d: credits */
											_n('Pay with %d credit', 'Pay with %d credits', $plan['cost_points'], 'panelr-for-woocommerce'),
											$plan['cost_points']
										)); ?></button>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</table>
				</div>
			<?php endif; ?>
		</section>
	<?php endforeach; ?>
<?php endforeach; ?>
