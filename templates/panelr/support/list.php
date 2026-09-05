<?php
/**
 * The member's tickets.
 * Override: woocommerce/panelr/support/list.php
 *
 * @var array  $open
 * @var array  $closed
 * @var string $base
 * @var string $error
 */
defined('ABSPATH') || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- this file is included inside wc_get_template(); its variables are local, not global
?>
<div class="panelr-support">
	<?php if ($error): ?><div class="panelr-portal__error" role="alert"><?php echo esc_html($error); ?></div><?php endif; ?>
	<p><a class="button panelr-renew-btn" href="<?php echo esc_url(add_query_arg('new', '1', $base)); ?>"><?php esc_html_e('New ticket', 'panelr-for-woocommerce'); ?></a></p>

	<section class="panelr-portal__section">
		<h3><?php esc_html_e('Open', 'panelr-for-woocommerce'); ?></h3>
		<?php if ($open): ?>
			<table class="panelr-portal__table panelr-support__table">
				<?php foreach ($open as $t): ?>
					<tr>
						<td><a href="<?php echo esc_url(add_query_arg('ticket', $t['ticket_ref'], $base)); ?>"><?php echo esc_html($t['subject']); ?></a><br><small><?php echo esc_html($t['topic']); ?> · <?php echo esc_html($t['ticket_ref']); ?></small></td>
						<td><span class="panelr-portal__status panelr-portal__status--<?php echo esc_attr($t['status']); ?>"><?php echo esc_html($t['status_label']); ?></span></td>
						<td><?php echo esc_html(Panelr_Helpers::format_date($t['updated_at'] ?? null, true)); ?></td>
					</tr>
				<?php endforeach; ?>
			</table>
		<?php else: ?>
			<p><?php esc_html_e('No open tickets.', 'panelr-for-woocommerce'); ?></p>
		<?php endif; ?>
	</section>

	<?php if ($closed): ?>
		<section class="panelr-portal__section">
			<h3><?php esc_html_e('Closed', 'panelr-for-woocommerce'); ?></h3>
			<table class="panelr-portal__table panelr-support__table">
				<?php foreach ($closed as $t): ?>
					<tr>
						<td><a href="<?php echo esc_url(add_query_arg('ticket', $t['ticket_ref'], $base)); ?>"><?php echo esc_html($t['subject']); ?></a><br><small><?php echo esc_html($t['topic']); ?> · <?php echo esc_html($t['ticket_ref']); ?></small></td>
						<td><span class="panelr-portal__status panelr-portal__status--<?php echo esc_attr($t['status']); ?>"><?php echo esc_html($t['status_label']); ?></span></td>
						<td><?php echo esc_html(Panelr_Helpers::format_date($t['updated_at'] ?? null, true)); ?></td>
					</tr>
				<?php endforeach; ?>
			</table>
		</section>
	<?php endif; ?>
</div>
