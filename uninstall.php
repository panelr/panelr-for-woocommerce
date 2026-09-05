<?php

/**
 * Panelr for WooCommerce — Uninstall
 * Runs when the plugin is deleted from the WordPress admin.
 * Removes the plugin's options and transients. Products are set to draft
 * only when the operator ticked that under Panelr → Advanced. Orders and
 * pages are never touched.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

function panelr_uninstall(): void
{
	global $wpdb;

	$draft_products = get_option('panelr_uninstall_draft_products', '0') === '1';

	if ($draft_products) {
		$ids = get_posts([
			'post_type'      => 'product',
			'post_status'    => ['publish', 'private', 'pending'],
			'posts_per_page' => -1,
			'meta_key'       => '_panelr_product_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- the join key; runs once, on uninstall
			'fields'         => 'ids',
		]);
		foreach ($ids as $id) {
			wp_update_post(['ID' => (int) $id, 'post_status' => 'draft']);
		}
	}

	$panelr_options = [
		'panelr_api_url',
		'panelr_api_key',
		'panelr_payment_map',
		'panelr_payment_mode_map',
		'panelr_page_portal',
		'panelr_page_trial',
		'panelr_page_upgrade',
		'panelr_page_order_status',
		'panelr_page_support',
		'panelr_page_apps',
		'panelr_page_checkout',
		'panelr_trial_product_id',
		'panelr_trials_enabled',
		'panelr_portal_allow_bouquets',
		'panelr_theme',
		'panelr_last_sync',
		'panelr_balance_product_id',
		'panelr_flush_rewrite_rules',
		'panelr_db_version',
		'panelr_upgrade_due',
		'panelr_upgrade_notes',
		'panelr_sync_notes',
		'panelr_cache_version',
		'panelr_sync_overwrite_edits',
		'panelr_product_categories',
		'panelr_service_names',
		'panelr_coupon_source',
		'panelr_auto_complete_orders',
		'panelr_turnstile_site_key',
		'panelr_turnstile_secret_key',
		'panelr_wp_user_link',
		'panelr_session_days',
		'panelr_require_invite',
		'panelr_checkout_account',
		'panelr_support_pages',
		'panelr_bots_enabled',
		'panelr_bot_telegram_url',
		'panelr_bot_discord_url',
		'panelr_bots_footer',
		'panelr_proxy_enabled',
		'panelr_proxy_header',
		'panelr_uninstall_draft_products',
	];

	foreach ($panelr_options as $panelr_option) {
		delete_option($panelr_option);
	}

	// Transients (cache, rate limits, error state). Their names carry a hash, so
	// they can only be found by prefix; this runs once, on uninstall.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_panelr\\_%' OR option_name LIKE '\\_transient\\_timeout\\_panelr\\_%'");

	// The optional WordPress-user link.
	delete_metadata('user', 0, '_panelr_customer_id', '', true);

	// Scheduled checks.
	if (function_exists('as_unschedule_all_actions')) {
		as_unschedule_all_actions('panelr_poll_order', [], 'panelr');
		as_unschedule_all_actions('panelr_retry_held_order', [], 'panelr');
	}
}

panelr_uninstall();
