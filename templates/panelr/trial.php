<?php
/**
 * The free trial form.
 * Override: woocommerce/panelr/trial.php
 *
 * @var array  $services      plugin_id => service (those offering a trial)
 * @var string $turnstile_key
 * @var bool   $signed_in
 * @var string $email
 * @var string $name
 */
defined('ABSPATH') || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- this file is included inside wc_get_template(); its variables are local, not global
?>
<div class="panelr-trial" id="panelr-trial-wrap">
	<div id="panelr-trial-form">
		<p id="panelr-trial-error" class="panelr-trial__error woocommerce-error" hidden role="alert"></p>

		<?php if (count($services) > 1): ?>
			<div class="panelr-trial__field panelr-trial__services">
				<span class="panelr-trial__label"><?php esc_html_e('Service', 'panelr-for-woocommerce'); ?></span>
				<?php $first = true; foreach ($services as $id => $svc): ?>
					<label class="panelr-trial__service">
						<input type="radio" name="panelr_trial_service" value="<?php echo (int) $id; ?>" <?php checked($first); ?>>
						<?php echo esc_html($svc['name']); ?>
					</label>
				<?php $first = false; endforeach; ?>
			</div>
		<?php elseif ($services): ?>
			<input type="hidden" name="panelr_trial_service" value="<?php echo (int) array_key_first($services); ?>">
		<?php endif; ?>

		<p class="panelr-trial__field">
			<label for="panelr-trial-name"><?php esc_html_e('Name', 'panelr-for-woocommerce'); ?></label>
			<input type="text" id="panelr-trial-name" class="input-text" autocomplete="name" value="<?php echo esc_attr($name); ?>">
		</p>
		<p class="panelr-trial__field">
			<label for="panelr-trial-email"><?php esc_html_e('Email', 'panelr-for-woocommerce'); ?></label>
			<input type="email" id="panelr-trial-email" class="input-text" autocomplete="email" value="<?php echo esc_attr($email); ?>" required>
		</p>

		<?php if ($turnstile_key): ?>
			<div class="panelr-trial__field">
				<div class="cf-turnstile" data-sitekey="<?php echo esc_attr($turnstile_key); ?>"></div>
			</div>
		<?php endif; ?>

		<p class="panelr-trial__field">
			<button type="button" id="panelr-trial-submit" class="button"><?php esc_html_e('Start my free trial', 'panelr-for-woocommerce'); ?></button>
		</p>
	</div>
	<div id="panelr-trial-result" hidden aria-live="polite"></div>
</div>
