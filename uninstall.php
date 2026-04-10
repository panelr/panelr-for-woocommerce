<?php

/**
 * Panelr for WooCommerce — Uninstall
 * Runs when the plugin is deleted from the WordPress admin.
 * Removes all plugin options from the database.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
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
	'panelr_trial_product_id',
	'panelr_trials_enabled',
	'panelr_portal_allow_bouquets',
	'panelr_theme',
	'panelr_last_sync',
	'panelr_balance_product_id',
	'panelr_flush_rewrite_rules',
];

foreach ($panelr_options as $panelr_option) {
	delete_option($panelr_option);
}
