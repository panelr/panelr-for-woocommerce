<?php

/**
 * Plugin Name:       Panelr for WooCommerce
 * Plugin URI:        https://panelr.app
 * Description:       Connect your Panelr IPTV management platform to WooCommerce. Handles new activations, renewals, free trials, trial upgrades, and customer self-service.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Panelr
 * Author URI:        https://panelr.app
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       panelr-for-woocommerce
 * Domain Path:       /languages
 * WC requires at least: 7.0
 * WC tested up to:      9.0
 */

defined('ABSPATH') || exit;

register_activation_hook(__FILE__, function () {
	update_option('panelr_flush_rewrite_rules', true);
});

add_action('init', function () {
	if (get_option('panelr_flush_rewrite_rules')) {
		flush_rewrite_rules();
		delete_option('panelr_flush_rewrite_rules');
	}
});

define('PANELR_VERSION',    '1.0.0');
define('PANELR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PANELR_PLUGIN_URL', plugin_dir_url(__FILE__));

// ── Check WooCommerce is active ────────────────────────────────────────────
add_action('admin_notices', function () {
	if (!current_user_can('manage_options')) return;
	if (!class_exists('WooCommerce')) {
		echo '<div class="notice notice-error"><p>'
			. '<strong>' . esc_html__('Panelr', 'panelr-for-woocommerce') . '</strong> '
			. esc_html__('requires WooCommerce to be installed and active.', 'panelr-for-woocommerce')
			. '</p></div>';
	}
});

// ── Boot ───────────────────────────────────────────────────────────────────
add_action('plugins_loaded', function () {
	if (!class_exists('WooCommerce')) {
		return;
	}

	require_once PANELR_PLUGIN_DIR . 'includes/class-panelr-api.php';
	require_once PANELR_PLUGIN_DIR . 'includes/class-panelr-settings.php';
	require_once PANELR_PLUGIN_DIR . 'includes/class-panelr-sync.php';
	require_once PANELR_PLUGIN_DIR . 'includes/class-panelr-checkout.php';
	require_once PANELR_PLUGIN_DIR . 'includes/class-panelr-portal.php';
	require_once PANELR_PLUGIN_DIR . 'includes/class-panelr-trial.php';
	require_once PANELR_PLUGIN_DIR . 'includes/class-panelr-upgrade.php';
	require_once PANELR_PLUGIN_DIR . 'includes/class-panelr-order-status.php';

	Panelr_Settings::init();

	// Enqueue selected frontend theme
	add_action('wp_enqueue_scripts', function () {
		$theme = get_option('panelr_theme', 'none');
		if (!in_array($theme, ['light', 'dark'], true)) return;
		wp_enqueue_style(
			'panelr-theme',
			PANELR_PLUGIN_URL . 'assets/css/theme-' . $theme . '.css',
			[],
			PANELR_VERSION
		);
	});

	Panelr_Sync::init();
	Panelr_Checkout::init();
	Panelr_Portal::init();
	Panelr_Trial::init();
	Panelr_Upgrade::init();
	Panelr_Order_Status::init();

	// Hide trial product from shop/archive pages
	add_action('pre_get_posts', function (WP_Query $q) {
		if (!$q->is_main_query()) return;
		if (!$q->is_post_type_archive('product') && !$q->is_tax('product_cat') && !$q->is_search()) return;

		$trial_panelr_id = (int) get_option('panelr_trial_product_id', 0);
		if (!$trial_panelr_id) return;

		$posts = get_posts([
			'post_type'      => 'product',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_panelr_product_id',
			'meta_value'     => $trial_panelr_id,
		]);

		if (empty($posts)) return;

		$excluded   = (array) $q->get('post__not_in');
		$excluded[] = $posts[0];
		$q->set('post__not_in', $excluded);
	});
});

// ── REST API ping endpoint ────────────────────────────────────────────────
add_action('rest_api_init', function () {
	register_rest_route('panelr/v1', '/ping', [
		'methods'             => 'GET',
		'callback'            => function () {
			return new WP_REST_Response(['status' => 'ok', 'version' => PANELR_VERSION], 200);
		},
		'permission_callback' => '__return_true',
	]);
});

// ── Privacy policy suggestion ─────────────────────────────────────────────
add_action('admin_init', function () {
	if (!function_exists('wp_add_privacy_policy_content')) return;
	$content = '<p>' . __('This plugin connects to your Panelr IPTV management installation. When customers submit free trial requests, their name, email address, IP address, and browser user agent are sent to your Panelr server for anti-abuse verification. Connection credentials are retrieved from your Panelr server and stored temporarily in the WooCommerce session. No customer data is stored permanently in WordPress beyond WooCommerce order references.', 'panelr-for-woocommerce') . '</p>';
	wp_add_privacy_policy_content('Panelr for WooCommerce', wp_kses_post($content));
});

// ── Declare HPOS compatibility ─────────────────────────────────────────────
add_action('before_woocommerce_init', function () {
	if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			__FILE__,
			true
		);
	}
});
