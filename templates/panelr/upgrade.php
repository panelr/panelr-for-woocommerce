<?php
/**
 * Trial upgrade: verify the code, pick a plan.
 * Override: woocommerce/panelr/upgrade.php
 *
 * @var string     $trial_code prefilled from ?panelr_t
 * @var string     $token      prefilled from ?t
 * @var array|null $verified
 * @var array      $plans
 * @var string     $portal_url
 * @var bool       $multi
 */
defined('ABSPATH') || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- this file is included inside wc_get_template(); its variables are local, not global
?>
<div class="panelr-upgrade" id="panelr-upgrade-wrap">

	<?php if (!$verified): ?>
		<div id="panelr-upgrade-form">
			<p id="panelr-upgrade-error" class="panelr-upgrade__error woocommerce-error" hidden role="alert"></p>
			<input type="hidden" id="panelr-upgrade-token" value="<?php echo esc_attr($token); ?>">
			<p class="panelr-upgrade__field">
				<label for="panelr-upgrade-code"><?php esc_html_e('Trial code', 'panelr-for-woocommerce'); ?></label>
				<input type="text" id="panelr-upgrade-code" class="input-text" value="<?php echo esc_attr($trial_code); ?>" autocomplete="off" placeholder="<?php echo $token ? esc_attr__('From your link', 'panelr-for-woocommerce') : 'ABC12345'; ?>">
			</p>
			<p class="panelr-upgrade__field">
				<button type="button" id="panelr-upgrade-verify" class="button"><?php esc_html_e('Continue', 'panelr-for-woocommerce'); ?></button>
			</p>
		</div>

	<?php else: ?>
		<div id="panelr-upgrade-dashboard">
			<section class="panelr-upgrade__account">
				<h3><?php esc_html_e('Your trial', 'panelr-for-woocommerce'); ?></h3>
				<table class="panelr-upgrade__table">
					<?php if ($multi && !empty($verified['plugin_name'])): ?>
						<tr><th><?php esc_html_e('Service', 'panelr-for-woocommerce'); ?></th><td><?php echo esc_html($verified['plugin_name']); ?></td></tr>
					<?php endif; ?>
					<tr><th><?php esc_html_e('Name', 'panelr-for-woocommerce'); ?></th><td><?php echo esc_html($verified['customer_name'] ?: '—'); ?></td></tr>
					<tr><th><?php esc_html_e('Email', 'panelr-for-woocommerce'); ?></th><td><?php echo esc_html($verified['customer_email'] ?: '—'); ?></td></tr>
					<tr><th><?php esc_html_e('Trial ends', 'panelr-for-woocommerce'); ?></th><td><?php echo esc_html(Panelr_Helpers::format_date($verified['expiration_date'] ?? null, true)); ?></td></tr>
				</table>
				<?php if ($portal_url): ?>
					<p><a href="<?php echo esc_url($portal_url); ?>" class="button"><?php esc_html_e('Open my account', 'panelr-for-woocommerce'); ?></a></p>
				<?php endif; ?>
			</section>

			<section class="panelr-upgrade__products">
				<h3><?php esc_html_e('Choose a plan', 'panelr-for-woocommerce'); ?></h3>
				<p id="panelr-upgrade-cart-error" class="panelr-upgrade__error woocommerce-error" hidden role="alert"></p>
				<?php if ($plans): ?>
					<table class="panelr-upgrade__table" id="panelr-upgrade-products-table">
						<?php foreach ($plans as $plan): ?>
							<tr>
								<td><?php echo esc_html($plan['name']); ?></td>
								<td><?php echo esc_html(Panelr_Helpers::plan_summary($plan['connections'], $plan['duration_months'])); ?></td>
								<td><button type="button" class="button panelr-upgrade-select" data-product-id="<?php echo (int) $plan['panelr_id']; ?>"><?php echo wp_kses_post(sprintf(
									/* translators: %s: price */
									__('Upgrade · %s', 'panelr-for-woocommerce'),
									wc_price($plan['price'])
								)); ?></button></td>
							</tr>
						<?php endforeach; ?>
					</table>
				<?php else: ?>
					<p><?php esc_html_e('No plans are available for this trial right now. Please contact us.', 'panelr-for-woocommerce'); ?></p>
				<?php endif; ?>
			</section>
		</div>
	<?php endif; ?>
</div>
