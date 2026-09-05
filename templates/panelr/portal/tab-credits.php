<?php
/**
 * Credits: balance, invite link, who they invited, the ledger.
 * Override: woocommerce/panelr/portal/tab-credits.php
 *
 * @var int        $balance
 * @var string     $code
 * @var string     $link
 * @var array      $invited
 * @var array      $ledger
 * @var array|null $referred_by
 * @var string     $error
 */
defined('ABSPATH') || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- this file is included inside wc_get_template(); its variables are local, not global
$share_text = sprintf(
	/* translators: %s: invite link */
	__('Join me here: %s', 'panelr-for-woocommerce'),
	$link
);
?>
<?php if ($error): ?><div class="panelr-portal__error" role="alert"><?php echo esc_html($error); ?></div><?php endif; ?>

<section class="panelr-portal__section panelr-credits">
	<h3><?php esc_html_e('Your credits', 'panelr-for-woocommerce'); ?></h3>
	<p class="panelr-credits__balance"><strong><?php echo (int) $balance; ?></strong> <?php echo esc_html(_n('credit', 'credits', $balance, 'panelr-for-woocommerce')); ?></p>
</section>

<?php if ($code): ?>
	<section class="panelr-portal__section panelr-credits__share">
		<h3><?php esc_html_e('Invite a friend', 'panelr-for-woocommerce'); ?></h3>
		<p class="panelr-credits__code"><?php esc_html_e('Your invite code', 'panelr-for-woocommerce'); ?> <code class="panelr-portal__code"><?php echo esc_html($code); ?></code> <button type="button" class="button panelr-copy-btn" data-copy="<?php echo esc_attr($code); ?>"><?php esc_html_e('Copy', 'panelr-for-woocommerce'); ?></button></p>
		<p class="panelr-credits__link"><code class="panelr-portal__code"><?php echo esc_html($link); ?></code></p>
		<p class="panelr-credits__buttons">
			<button type="button" class="button panelr-copy-btn" data-copy="<?php echo esc_attr($link); ?>"><?php esc_html_e('Copy link', 'panelr-for-woocommerce'); ?></button>
			<a class="button" target="_blank" rel="noopener" href="<?php echo esc_url('https://wa.me/?text=' . rawurlencode($share_text)); ?>">WhatsApp</a>
			<a class="button" target="_blank" rel="noopener" href="<?php echo esc_url('https://t.me/share/url?url=' . rawurlencode($link) . '&text=' . rawurlencode(__('Join me here', 'panelr-for-woocommerce'))); ?>">Telegram</a>
			<a class="button" href="<?php echo esc_url('mailto:?subject=' . rawurlencode(__('An invite for you', 'panelr-for-woocommerce')) . '&body=' . rawurlencode($share_text)); ?>"><?php esc_html_e('Email', 'panelr-for-woocommerce'); ?></a>
		</p>
	</section>
<?php endif; ?>

<?php if ($referred_by): ?>
	<section class="panelr-portal__section">
		<p><?php echo esc_html(sprintf(
			/* translators: %s: date */
			__('You joined on an invite, %s.', 'panelr-for-woocommerce'),
			Panelr_Helpers::format_date($referred_by['since'] ?? null)
		)); ?></p>
	</section>
<?php endif; ?>

<section class="panelr-portal__section">
	<h3><?php esc_html_e('People you invited', 'panelr-for-woocommerce'); ?></h3>
	<?php if ($invited): ?>
		<table class="panelr-portal__table">
			<?php foreach ($invited as $i): ?>
				<tr><td><?php echo esc_html(Panelr_Helpers::format_date($i['since'] ?? null)); ?></td><td><?php echo esc_html((string) ($i['email'] ?? '')); ?></td></tr>
			<?php endforeach; ?>
		</table>
	<?php else: ?>
		<p><?php esc_html_e('Nobody yet. Share your link.', 'panelr-for-woocommerce'); ?></p>
	<?php endif; ?>
</section>

<section class="panelr-portal__section">
	<h3><?php esc_html_e('History', 'panelr-for-woocommerce'); ?></h3>
	<?php if ($ledger): ?>
		<table class="panelr-portal__table panelr-credits__ledger">
			<?php foreach ($ledger as $row): ?>
				<tr>
					<td><?php echo esc_html(Panelr_Helpers::format_date($row['created_at'] ?? null)); ?></td>
					<td><?php echo esc_html(ucfirst(str_replace('_', ' ', (string) ($row['reason'] ?? '')))); ?><?php if (!empty($row['service_name'])): ?> · <?php echo esc_html($row['service_name']); ?><?php endif; ?><?php if (!empty($row['note'])): ?><br><small><?php echo esc_html($row['note']); ?></small><?php endif; ?></td>
					<td class="panelr-credits__delta <?php echo (int) $row['delta'] >= 0 ? 'panelr-credits__delta--plus' : 'panelr-credits__delta--minus'; ?>"><?php echo (int) $row['delta'] >= 0 ? '+' : ''; ?><?php echo (int) $row['delta']; ?></td>
				</tr>
			<?php endforeach; ?>
		</table>
	<?php else: ?>
		<p><?php esc_html_e('Nothing yet.', 'panelr-for-woocommerce'); ?></p>
	<?php endif; ?>
</section>
