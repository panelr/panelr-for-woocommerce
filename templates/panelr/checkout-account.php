<?php
/**
 * The account step on the checkout page: sign in or create the Panelr
 * account with the same email the order uses.
 * Override: woocommerce/panelr/checkout-account.php
 *
 * @var bool   $signed_in
 * @var string $email
 * @var string $name
 * @var bool   $require_invite
 * @var int    $signup_points
 * @var string $portal_url
 * @var string $nonce
 */
defined('ABSPATH') || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- this file is included inside wc_get_template(); its variables are local, not global
?>
<div class="panelr-checkout-account" data-nonce="<?php echo esc_attr($nonce); ?>">
	<h3><?php esc_html_e('Your account', 'panelr-for-woocommerce'); ?></h3>
	<?php if ($signed_in): ?>
		<p class="panelr-checkout-account__who">
			<?php echo esc_html(sprintf(
				/* translators: %s: email */
				__('Signed in as %s', 'panelr-for-woocommerce'),
				$email
			)); ?>
			<button type="button" class="button panelr-checkout-signout"><?php esc_html_e('Sign out', 'panelr-for-woocommerce'); ?></button>
		</p>
	<?php else: ?>
		<p class="panelr-checkout-account__intro"><?php esc_html_e('Your order goes on an account under the email below. New here? Choose a password. Already a member? Enter your password.', 'panelr-for-woocommerce'); ?></p>
		<?php if ($signup_points > 0): ?>
			<p class="panelr-portal__welcome"><?php echo esc_html(sprintf(
				/* translators: %d: credits */
				_n('Confirm your email and your new account starts with %d credit.', 'Confirm your email and your new account starts with %d credits.', $signup_points, 'panelr-for-woocommerce'),
				$signup_points
			)); ?></p>
		<?php endif; ?>
		<p class="form-row form-row-first">
			<label for="panelr_account_password"><?php esc_html_e('Account password', 'panelr-for-woocommerce'); ?> <span class="required">*</span></label>
			<input type="password" class="input-text" name="panelr_account_password" id="panelr_account_password" autocomplete="new-password">
		</p>
		<p class="form-row form-row-last">
			<label for="panelr_account_password2"><?php esc_html_e('Password again (new accounts)', 'panelr-for-woocommerce'); ?></label>
			<input type="password" class="input-text" name="panelr_account_password2" id="panelr_account_password2" autocomplete="new-password">
		</p>
		<?php if ($require_invite || !empty($referral_enabled)): ?>
			<p class="form-row form-row-wide">
				<label for="panelr_account_invite"><?php echo $require_invite ? esc_html__('Invite code (new accounts)', 'panelr-for-woocommerce') : esc_html__('Invite code (optional)', 'panelr-for-woocommerce'); ?></label>
				<input type="text" class="input-text" name="panelr_account_invite" id="panelr_account_invite" value="<?php echo esc_attr($invite_code ?? ''); ?>" autocomplete="off">
			</p>
		<?php endif; ?>
		<p class="form-row form-row-wide panelr-checkout-account__links">
			<a href="<?php echo esc_url(add_query_arg('view', 'forgot', $portal_url)); ?>"><?php esc_html_e('Forgot password', 'panelr-for-woocommerce'); ?></a>
		</p>
		<div class="clear"></div>
	<?php endif; ?>
</div>
