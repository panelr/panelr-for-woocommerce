<?php
/**
 * The bot hand-off lands here: the plan, the name and email it carries,
 * and one button. Nothing changes until it is pressed.
 * Override: woocommerce/panelr/order-status/handoff-confirm.php
 *
 * @var array  $product      synced product row
 * @var string $service_name
 * @var string $email
 * @var string $first_name
 * @var string $last_name
 * @var string $intent
 * @var int    $activation_id
 * @var string $username
 * @var bool   $needs_sign_in
 * @var string $sign_in_url
 * @var bool   $multi
 */
defined('ABSPATH') || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- this file is included inside wc_get_template(); its variables are local, not global
?>
<div class="panelr-order-status panelr-handoff">
	<section class="panelr-portal__section">
		<h3><?php echo esc_html(Panelr_Helpers::intent_label($intent)); ?></h3>
		<table class="panelr-portal__table">
			<tr>
				<th><?php esc_html_e('Plan', 'panelr-for-woocommerce'); ?></th>
				<td><?php echo esc_html($product['name']); ?><?php if ($multi && $service_name): ?> · <?php echo esc_html($service_name); ?><?php endif; ?><br><small><?php echo esc_html(Panelr_Helpers::plan_summary($product['connections'], $product['duration_months'])); ?></small></td>
			</tr>
			<?php if ($username): ?>
				<tr><th><?php esc_html_e('Connection', 'panelr-for-woocommerce'); ?></th><td><code class="panelr-portal__code"><?php echo esc_html($username); ?></code></td></tr>
			<?php endif; ?>
			<?php if ($first_name || $last_name): ?>
				<tr><th><?php esc_html_e('Name', 'panelr-for-woocommerce'); ?></th><td><?php echo esc_html(trim($first_name . ' ' . $last_name)); ?></td></tr>
			<?php endif; ?>
			<tr><th><?php esc_html_e('Email', 'panelr-for-woocommerce'); ?></th><td><?php echo esc_html($email); ?></td></tr>
		</table>

		<?php if ($needs_sign_in): ?>
			<p><?php esc_html_e('Sign in first so this renewal goes on your own connection.', 'panelr-for-woocommerce'); ?></p>
			<p><a class="button panelr-renew-btn" href="<?php echo esc_url($sign_in_url); ?>"><?php esc_html_e('Sign in', 'panelr-for-woocommerce'); ?></a></p>
		<?php else: ?>
			<form method="post">
				<?php wp_nonce_field('panelr_handoff', 'panelr_handoff_nonce'); ?>
				<input type="hidden" name="panelr_product_id" value="<?php echo (int) $product['panelr_id']; ?>">
				<input type="hidden" name="panelr_email" value="<?php echo esc_attr($email); ?>">
				<input type="hidden" name="panelr_intent" value="<?php echo esc_attr($intent); ?>">
				<input type="hidden" name="panelr_activation_id" value="<?php echo (int) $activation_id; ?>">
				<input type="hidden" name="panelr_username" value="<?php echo esc_attr($username); ?>">
				<input type="hidden" name="panelr_first_name" value="<?php echo esc_attr($first_name); ?>">
				<input type="hidden" name="panelr_last_name" value="<?php echo esc_attr($last_name); ?>">
				<p><button type="submit" class="button panelr-renew-btn"><?php echo wp_kses_post(sprintf(
					/* translators: %s: price */
					__('Continue to checkout · %s', 'panelr-for-woocommerce'),
					wc_price($product['price'])
				)); ?></button></p>
			</form>
		<?php endif; ?>
	</section>
</div>
