<?php
/**
 * Signed in with connection details, no account yet: the one line, and
 * "Set up your account".
 * Override: woocommerce/panelr/portal/line-only.php
 *
 * @var array $line
 * @var array $plans         plans this line can renew onto
 * @var bool  $multi
 * @var int   $signup_points
 */
defined('ABSPATH') || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- this file is included inside wc_get_template(); its variables are local, not global
$aid = (int) $line['activation_id'];
?>
<div class="panelr-portal panelr-portal--line-only">
	<p class="panelr-portal__actions">
		<button type="button" id="panelr-logout-btn" class="button"><?php esc_html_e('Sign out', 'panelr-for-woocommerce'); ?></button>
	</p>

	<?php if (!empty($line['account_required'])): ?>
		<section class="panelr-portal__section panelr-portal__setup">
			<h3><?php esc_html_e('Set up your account', 'panelr-for-woocommerce'); ?></h3>
			<p><?php echo esc_html(sprintf(
				/* translators: %s: email */
				__('Choose a password for %s and you can renew, see every connection, and use credits from one place.', 'panelr-for-woocommerce'),
				(string) $line['email']
			)); ?></p>
			<?php if ($signup_points > 0): ?>
				<p class="panelr-portal__welcome"><?php echo esc_html(sprintf(
					/* translators: %d: credits */
					_n('New accounts start with %d credit.', 'New accounts start with %d credits.', $signup_points, 'panelr-for-woocommerce'),
					$signup_points
				)); ?></p>
			<?php endif; ?>
			<p id="panelr-setup-error" class="panelr-portal__error woocommerce-error" hidden role="alert"></p>
			<p class="panelr-portal__field">
				<label for="panelr-setup-name"><?php esc_html_e('Name', 'panelr-for-woocommerce'); ?></label>
				<input type="text" id="panelr-setup-name" class="input-text" value="<?php echo esc_attr((string) $line['name']); ?>" autocomplete="name">
			</p>
			<p class="panelr-portal__field">
				<label for="panelr-setup-password"><?php esc_html_e('Password', 'panelr-for-woocommerce'); ?></label>
				<input type="password" id="panelr-setup-password" class="input-text" autocomplete="new-password">
			</p>
			<p class="panelr-portal__field">
				<label for="panelr-setup-password2"><?php esc_html_e('Password again', 'panelr-for-woocommerce'); ?></label>
				<input type="password" id="panelr-setup-password2" class="input-text" autocomplete="new-password">
			</p>
			<p><button type="button" id="panelr-setup-btn" class="button panelr-renew-btn"><?php esc_html_e('Create my account', 'panelr-for-woocommerce'); ?></button></p>
		</section>
	<?php endif; ?>

	<section class="panelr-portal__section panelr-line" id="panelr-line-<?php echo (int) $aid; ?>" data-activation-id="<?php echo (int) $aid; ?>">
		<h3 class="panelr-line__title"><code class="panelr-portal__code"><?php echo esc_html($line['username'] ?: $line['email']); ?></code><?php if ($multi && $line['service_name']): ?> · <?php echo esc_html($line['service_name']); ?><?php endif; ?></h3>
		<table class="panelr-portal__table">
			<tr><th><?php esc_html_e('Status', 'panelr-for-woocommerce'); ?></th><td><span class="panelr-portal__status panelr-portal__status--<?php echo esc_attr($line['status']); ?>"><?php echo esc_html(Panelr_Helpers::line_status_label($line['status'])); ?></span></td></tr>
			<?php if ($line['product_name']): ?><tr><th><?php esc_html_e('Plan', 'panelr-for-woocommerce'); ?></th><td><?php echo esc_html($line['product_name']); ?></td></tr><?php endif; ?>
			<tr><th><?php esc_html_e('Ends', 'panelr-for-woocommerce'); ?></th><td><?php echo esc_html(Panelr_Helpers::format_date($line['expiration_date'])); ?></td></tr>
		</table>
		<p class="panelr-line__actions">
			<button type="button" class="button panelr-line-details-btn"><?php esc_html_e('Connection details', 'panelr-for-woocommerce'); ?></button>
			<?php if ($plans): ?><button type="button" class="button panelr-line-renew-btn"><?php echo !empty($line['is_trial']) ? esc_html__('Upgrade', 'panelr-for-woocommerce') : esc_html__('Renew', 'panelr-for-woocommerce'); ?></button><?php endif; ?>
		</p>
		<div class="panelr-line__panel panelr-line__details" hidden aria-live="polite"></div>
		<?php if ($plans): ?>
			<div class="panelr-line__panel panelr-line__renew" hidden>
				<p class="panelr-line__error panelr-portal__error" hidden role="alert"></p>
				<table class="panelr-portal__table">
					<?php foreach ($plans as $plan): ?>
						<tr>
							<td><?php echo esc_html($plan['name']); ?></td>
							<td><?php echo esc_html(Panelr_Helpers::plan_summary($plan['connections'], $plan['duration_months'])); ?></td>
							<td><button type="button" class="button panelr-renew-btn panelr-renew-choose" data-panelr-product-id="<?php echo (int) $plan['panelr_id']; ?>"><?php echo wp_kses_post(sprintf(
								/* translators: %s: price */
								!empty($line['is_trial']) ? __('Upgrade · %s', 'panelr-for-woocommerce') : __('Renew · %s', 'panelr-for-woocommerce'),
								wc_price($plan['price'])
							)); ?></button></td>
						</tr>
					<?php endforeach; ?>
				</table>
			</div>
		<?php endif; ?>
	</section>
</div>
