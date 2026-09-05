<?php
/**
 * The member area frame: name, tabs, and the active tab's content.
 * Override: woocommerce/panelr/portal/dashboard.php
 *
 * @var array  $snapshot
 * @var array  $tabs       key => label
 * @var string $tab
 * @var string $content
 * @var string $name
 * @var string $email
 * @var bool   $verified
 * @var string $portal_url
 */
defined('ABSPATH') || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- this file is included inside wc_get_template(); its variables are local, not global
?>
<div class="panelr-portal panelr-portal--member" data-tab="<?php echo esc_attr($tab); ?>">
	<div class="panelr-portal__header">
		<div class="panelr-portal__who">
			<strong class="panelr-portal__name"><?php echo esc_html($name ?: $email); ?></strong>
			<?php if ($name): ?><span class="panelr-portal__email"><?php echo esc_html($email); ?></span><?php endif; ?>
		</div>
		<p class="panelr-portal__actions">
			<button type="button" id="panelr-logout-btn" class="button"><?php esc_html_e('Sign out', 'panelr-for-woocommerce'); ?></button>
		</p>
	</div>

	<?php if (!$verified): ?>
		<div class="panelr-portal__verify woocommerce-info">
			<span><?php esc_html_e('Confirm your email address using the link we sent you.', 'panelr-for-woocommerce'); ?></span>
			<button type="button" id="panelr-resend-verify" class="button"><?php esc_html_e('Resend', 'panelr-for-woocommerce'); ?></button>
			<span id="panelr-resend-result" aria-live="polite"></span>
		</div>
	<?php endif; ?>

	<nav class="panelr-portal__tabs" role="tablist">
		<?php foreach ($tabs as $key => $label): ?>
			<a role="tab" href="<?php echo esc_url(add_query_arg('tab', $key, $portal_url)); ?>" class="panelr-tab-btn <?php echo $key === $tab ? 'panelr-tab-btn--active' : ''; ?>" data-tab="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></a>
		<?php endforeach; ?>
	</nav>

	<div class="panelr-portal__tab-panel panelr-portal__tab-panel--toplevel panelr-portal__tab-panel--active" id="panelr-tab-<?php echo esc_attr($tab); ?>">
		<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered templates ?>
	</div>
</div>
