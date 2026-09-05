<?php
/**
 * The one-time code that links a chat to this account (loaded on request).
 * Override: woocommerce/panelr/portal/chat-link.php
 *
 * @var string $platform            telegram|discord
 * @var string $code
 * @var string $telegram_url
 * @var string $discord_invite_url
 */
defined('ABSPATH') || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- this file is included inside wc_get_template(); its variables are local, not global
?>
<div class="panelr-chat-link">
	<p class="panelr-chat-link__code"><code class="panelr-portal__code"><?php echo esc_html($code); ?></code> <button type="button" class="button panelr-copy-btn" data-copy="<?php echo esc_attr($code); ?>"><?php esc_html_e('Copy', 'panelr-for-woocommerce'); ?></button></p>
	<?php if ($platform === 'telegram'): ?>
		<?php if ($telegram_url): ?>
			<p><a class="button panelr-renew-btn" target="_blank" rel="noopener" href="<?php echo esc_url($telegram_url); ?>"><?php esc_html_e('Open Telegram and link', 'panelr-for-woocommerce'); ?></a></p>
			<p><?php echo esc_html(sprintf(
				/* translators: %s: the link code */
				__('Or open our Telegram bot and send /start link_%s', 'panelr-for-woocommerce'),
				$code
			)); ?></p>
		<?php else: ?>
			<p><?php echo esc_html(sprintf(
				/* translators: %s: the link code */
				__('Open our Telegram bot and send /start link_%s', 'panelr-for-woocommerce'),
				$code
			)); ?></p>
		<?php endif; ?>
	<?php else: ?>
		<?php if ($discord_invite_url): ?>
			<p><a class="button panelr-renew-btn" target="_blank" rel="noopener" href="<?php echo esc_url($discord_invite_url); ?>"><?php esc_html_e('Open our Discord server', 'panelr-for-woocommerce'); ?></a></p>
		<?php endif; ?>
		<p><?php esc_html_e('In our Discord server, in any channel, type /link, pick it from the popup, then paste the code above into the code box and press Enter. Only you can see the bot\'s reply.', 'panelr-for-woocommerce'); ?></p>
	<?php endif; ?>
	<p><?php esc_html_e('This code works once and expires in 15 minutes. This page updates itself once the chat is linked.', 'panelr-for-woocommerce'); ?></p>
	<p><button type="button" class="button panelr-chat-cancel-btn" data-platform="<?php echo esc_attr($platform); ?>"><?php esc_html_e('Cancel', 'panelr-for-woocommerce'); ?></button></p>
</div>
