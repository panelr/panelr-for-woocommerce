<?php
/**
 * Sign in, create an account, forgot password, or sign in with connection details.
 * Override: woocommerce/panelr/portal/sign-in.php
 *
 * @var bool   $accounts       email logins are offered
 * @var int    $signup_points  welcome credits on account creation (0 = none)
 * @var bool   $require_invite
 * @var string $invite_code    carried from ?ref
 * @var bool   $show_line      offer the connection-details door
 * @var array  $services
 * @var string $return
 * @var string $view           '' | register | forgot | line
 * @var bool   $for_checkout   opened from checkout: say so
 */
defined('ABSPATH') || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- this file is included inside wc_get_template(); its variables are local, not global
$view = in_array($view, ['register', 'forgot', 'line'], true) ? $view : 'signin';
?>
<div class="panelr-portal panelr-portal--signed-out">
	<div class="panelr-portal__login" data-view="<?php echo esc_attr($view); ?>">
		<input type="hidden" id="panelr-return" value="<?php echo esc_attr($return); ?>">

		<!-- Sign in -->
		<div class="panelr-portal__view" id="panelr-view-signin" <?php echo $view === 'signin' ? '' : 'hidden'; ?>>
			<p id="panelr-login-error" class="panelr-portal__error woocommerce-error" hidden role="alert"></p>
			<p class="panelr-portal__field">
				<label for="panelr-email"><?php esc_html_e('Email', 'panelr-for-woocommerce'); ?></label>
				<input type="email" id="panelr-email" class="input-text" autocomplete="username">
			</p>
			<p class="panelr-portal__field">
				<label for="panelr-password"><?php esc_html_e('Password', 'panelr-for-woocommerce'); ?></label>
				<input type="password" id="panelr-password" class="input-text" autocomplete="current-password">
			</p>
			<p class="panelr-portal__field">
				<button type="button" id="panelr-login-btn" class="button"><?php esc_html_e('Sign in', 'panelr-for-woocommerce'); ?></button>
			</p>
			<p class="panelr-portal__links">
				<a href="#" class="panelr-view-link" data-view="forgot"><?php esc_html_e('Forgot password', 'panelr-for-woocommerce'); ?></a>
				<?php if ($accounts): ?> · <a href="#" class="panelr-view-link" data-view="register"><?php esc_html_e('Create account', 'panelr-for-woocommerce'); ?></a><?php endif; ?>
				<?php if ($show_line): ?> · <a href="#" class="panelr-view-link" data-view="line"><?php esc_html_e('Sign in with connection details', 'panelr-for-woocommerce'); ?></a><?php endif; ?>
			</p>
		</div>

		<!-- Create account -->
		<?php if ($accounts): ?>
		<div class="panelr-portal__view" id="panelr-view-register" <?php echo $view === 'register' ? '' : 'hidden'; ?>>
			<?php if (!empty($for_checkout)): ?>
				<p class="panelr-portal__intro"><?php esc_html_e('Your order goes on an account. Create one, or sign in, and you will be taken back to checkout.', 'panelr-for-woocommerce'); ?></p>
			<?php endif; ?>
			<p id="panelr-register-error" class="panelr-portal__error woocommerce-error" hidden role="alert"></p>
			<?php if ($signup_points > 0): ?>
				<p class="panelr-portal__welcome"><?php echo esc_html(sprintf(
					/* translators: %d: credits */
					_n('New accounts start with %d credit.', 'New accounts start with %d credits.', $signup_points, 'panelr-for-woocommerce'),
					$signup_points
				)); ?></p>
			<?php endif; ?>
			<p class="panelr-portal__field">
				<label for="panelr-reg-name"><?php esc_html_e('Name', 'panelr-for-woocommerce'); ?></label>
				<input type="text" id="panelr-reg-name" class="input-text" autocomplete="name">
			</p>
			<p class="panelr-portal__field">
				<label for="panelr-reg-email"><?php esc_html_e('Email', 'panelr-for-woocommerce'); ?></label>
				<input type="email" id="panelr-reg-email" class="input-text" autocomplete="email">
			</p>
			<p class="panelr-portal__field">
				<label for="panelr-reg-password"><?php esc_html_e('Password', 'panelr-for-woocommerce'); ?></label>
				<input type="password" id="panelr-reg-password" class="input-text" autocomplete="new-password">
			</p>
			<p class="panelr-portal__field">
				<label for="panelr-reg-password2"><?php esc_html_e('Password again', 'panelr-for-woocommerce'); ?></label>
				<input type="password" id="panelr-reg-password2" class="input-text" autocomplete="new-password">
			</p>
			<?php if ($require_invite || $invite_code): ?>
				<p class="panelr-portal__field">
					<label for="panelr-reg-invite"><?php echo $require_invite ? esc_html__('Invite code', 'panelr-for-woocommerce') : esc_html__('Invite code (optional)', 'panelr-for-woocommerce'); ?></label>
					<input type="text" id="panelr-reg-invite" class="input-text" value="<?php echo esc_attr($invite_code); ?>" autocomplete="off">
				</p>
			<?php endif; ?>
			<p class="panelr-portal__field">
				<button type="button" id="panelr-register-btn" class="button"><?php esc_html_e('Create account', 'panelr-for-woocommerce'); ?></button>
			</p>
			<p class="panelr-portal__links"><a href="#" class="panelr-view-link" data-view="signin"><?php esc_html_e('I already have an account', 'panelr-for-woocommerce'); ?></a></p>
		</div>
		<?php endif; ?>

		<!-- Forgot password -->
		<div class="panelr-portal__view" id="panelr-view-forgot" <?php echo $view === 'forgot' ? '' : 'hidden'; ?>>
			<p id="panelr-forgot-error" class="panelr-portal__error woocommerce-error" hidden role="alert"></p>
			<p id="panelr-forgot-done" class="woocommerce-message" hidden role="status"></p>
			<p class="panelr-portal__field">
				<label for="panelr-forgot-email"><?php esc_html_e('Email', 'panelr-for-woocommerce'); ?></label>
				<input type="email" id="panelr-forgot-email" class="input-text" autocomplete="email">
			</p>
			<p class="panelr-portal__field">
				<button type="button" id="panelr-forgot-btn" class="button"><?php esc_html_e('Send me a link', 'panelr-for-woocommerce'); ?></button>
			</p>
			<p class="panelr-portal__links"><a href="#" class="panelr-view-link" data-view="signin"><?php esc_html_e('Back to sign in', 'panelr-for-woocommerce'); ?></a></p>
		</div>

		<!-- Connection details -->
		<?php if ($show_line): ?>
		<div class="panelr-portal__view" id="panelr-view-line" <?php echo $view === 'line' ? '' : 'hidden'; ?>>
			<p id="panelr-line-error" class="panelr-portal__error woocommerce-error" hidden role="alert"></p>
			<?php if (count($services) > 1): ?>
				<p class="panelr-portal__field">
					<label for="panelr-line-service"><?php esc_html_e('Service', 'panelr-for-woocommerce'); ?></label>
					<select id="panelr-line-service" class="input-text">
						<option value="0"><?php esc_html_e('Not sure', 'panelr-for-woocommerce'); ?></option>
						<?php foreach ($services as $id => $svc): ?>
							<option value="<?php echo (int) $id; ?>"><?php echo esc_html($svc['name']); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
			<?php endif; ?>
			<p class="panelr-portal__field">
				<label for="panelr-username"><?php esc_html_e('Username', 'panelr-for-woocommerce'); ?></label>
				<input type="text" id="panelr-username" class="input-text" autocomplete="username">
			</p>
			<p class="panelr-portal__field">
				<label for="panelr-line-password"><?php esc_html_e('Password', 'panelr-for-woocommerce'); ?></label>
				<input type="password" id="panelr-line-password" class="input-text" autocomplete="current-password">
			</p>
			<p class="panelr-portal__field">
				<button type="button" id="panelr-line-login-btn" class="button"><?php esc_html_e('Sign in', 'panelr-for-woocommerce'); ?></button>
			</p>
			<p class="panelr-portal__links"><a href="#" class="panelr-view-link" data-view="signin"><?php esc_html_e('Sign in with email instead', 'panelr-for-woocommerce'); ?></a></p>
		</div>
		<?php endif; ?>
	</div>
</div>
