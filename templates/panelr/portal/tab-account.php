<?php
/**
 * Account: name and email, password, how to be reached, sign out everywhere.
 * Override: woocommerce/panelr/portal/tab-account.php
 *
 * @var array  $snapshot
 * @var string $name
 * @var string $email
 * @var bool   $verified
 * @var string $preference       email|telegram|discord
 * @var array  $chats            platform => {label, linked, blocked}
 */
defined('ABSPATH') || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- this file is included inside wc_get_template(); its variables are local, not global
?>
<section class="panelr-portal__section panelr-portal__account">
	<h3><?php esc_html_e('Your details', 'panelr-for-woocommerce'); ?></h3>
	<p id="panelr-account-edit-error" class="panelr-portal__error woocommerce-error" hidden role="alert"></p>
	<p class="panelr-portal__field">
		<label for="panelr-edit-name"><?php esc_html_e('Name', 'panelr-for-woocommerce'); ?></label>
		<input type="text" id="panelr-edit-name" class="input-text" value="<?php echo esc_attr($name); ?>" autocomplete="name">
	</p>
	<p class="panelr-portal__field">
		<label for="panelr-edit-email"><?php esc_html_e('Email', 'panelr-for-woocommerce'); ?></label>
		<input type="email" id="panelr-edit-email" class="input-text" value="<?php echo esc_attr($email); ?>" autocomplete="email">
	</p>
	<p>
		<button type="button" id="panelr-save-account-btn" class="button"><?php esc_html_e('Save', 'panelr-for-woocommerce'); ?></button>
		<span id="panelr-account-result" class="panelr-portal__result" aria-live="polite"></span>
	</p>
</section>

<section class="panelr-portal__section panelr-portal__password">
	<h3><?php esc_html_e('Password', 'panelr-for-woocommerce'); ?></h3>
	<p id="panelr-password-error" class="panelr-portal__error woocommerce-error" hidden role="alert"></p>
	<p class="panelr-portal__field">
		<label for="panelr-current-password"><?php esc_html_e('Current password', 'panelr-for-woocommerce'); ?></label>
		<input type="password" id="panelr-current-password" class="input-text" autocomplete="current-password">
	</p>
	<p class="panelr-portal__field">
		<label for="panelr-new-password"><?php esc_html_e('New password', 'panelr-for-woocommerce'); ?></label>
		<input type="password" id="panelr-new-password" class="input-text" autocomplete="new-password">
	</p>
	<p class="panelr-portal__field">
		<label for="panelr-new-password2"><?php esc_html_e('New password again', 'panelr-for-woocommerce'); ?></label>
		<input type="password" id="panelr-new-password2" class="input-text" autocomplete="new-password">
	</p>
	<p>
		<button type="button" id="panelr-change-password-btn" class="button"><?php esc_html_e('Change password', 'panelr-for-woocommerce'); ?></button>
		<span id="panelr-password-result" class="panelr-portal__result" aria-live="polite"></span>
	</p>
</section>

<?php if ($chats): ?>
<section class="panelr-portal__section panelr-portal__contact panelr-chats">
	<h3><?php esc_html_e('Chats', 'panelr-for-woocommerce'); ?></h3>
	<p id="panelr-chat-error" class="panelr-portal__error woocommerce-error" hidden role="alert"></p>
	<?php foreach ($chats as $platform => $chat): ?>
		<div class="panelr-chats__row" data-platform="<?php echo esc_attr($platform); ?>">
			<div class="panelr-chats__who">
				<strong><?php echo esc_html($chat['label']); ?></strong>
				<span class="panelr-chats__state"><?php echo $chat['linked'] ? ($chat['blocked'] ? esc_html__('Blocked. Unlink and link again', 'panelr-for-woocommerce') : esc_html__('Linked', 'panelr-for-woocommerce')) : esc_html__('Not linked', 'panelr-for-woocommerce'); ?></span>
			</div>
			<button type="button" class="button <?php echo $chat['linked'] ? 'panelr-chat-unlink-btn' : 'panelr-chat-link-btn'; ?>" data-platform="<?php echo esc_attr($platform); ?>"><?php echo $chat['linked'] ? esc_html__('Unlink', 'panelr-for-woocommerce') : esc_html__('Link', 'panelr-for-woocommerce'); ?></button>
		</div>
		<div class="panelr-chats__code" data-platform="<?php echo esc_attr($platform); ?>" hidden></div>
	<?php endforeach; ?>

	<h4><?php esc_html_e('Reach me on', 'panelr-for-woocommerce'); ?></h4>
	<p id="panelr-contact-error" class="panelr-portal__error woocommerce-error" hidden role="alert"></p>
	<div class="panelr-portal__choices">
		<label><input type="radio" name="panelr_contact_pref" value="email" <?php checked($preference, 'email'); ?>> <?php esc_html_e('Email', 'panelr-for-woocommerce'); ?></label>
		<?php foreach ($chats as $platform => $chat): ?>
			<label class="<?php echo $chat['linked'] ? '' : 'is-unavailable'; ?>" data-platform="<?php echo esc_attr($platform); ?>"><input type="radio" name="panelr_contact_pref" value="<?php echo esc_attr($platform); ?>" <?php checked($preference, $platform); ?> <?php disabled(!$chat['linked']); ?>> <?php echo esc_html($chat['label']); ?><span class="panelr-chats__hint" <?php echo $chat['linked'] ? 'hidden' : ''; ?>> (<?php esc_html_e('link it first', 'panelr-for-woocommerce'); ?>)</span></label>
		<?php endforeach; ?>
		<span id="panelr-contact-result" class="panelr-portal__result" aria-live="polite"></span>
	</div>
</section>
<?php endif; ?>

<section class="panelr-portal__section panelr-portal__signout">
	<p><button type="button" id="panelr-logout-everywhere-btn" class="button"><?php esc_html_e('Sign out everywhere', 'panelr-for-woocommerce'); ?></button></p>
</section>
