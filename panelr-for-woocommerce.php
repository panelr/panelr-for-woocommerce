<?php

/**
 * Plugin Name:       Panelr for WooCommerce
 * Plugin URI:        https://github.com/panelr/panelr-for-woocommerce
 * Description:       Connect your Panelr installation to WooCommerce: plans from every service, sign-in, renewals, trials, credits, coupons, support and apps.
 * Version:           2.0.1
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Panelr
 * Author URI:        https://panelr.app
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       panelr-for-woocommerce
 * Domain Path:       /languages
 * Requires Plugins:  woocommerce
 * WC requires at least: 7.2
 * WC tested up to:      9.9
 */

defined('ABSPATH') || exit;

define('PANELR_VERSION',    '2.0.1');
define('PANELR_DB_VERSION', '2.0.0');
define('PANELR_PLUGIN_FILE', __FILE__);
define('PANELR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PANELR_PLUGIN_URL', plugin_dir_url(__FILE__));

register_activation_hook(__FILE__, function () {
	update_option('panelr_flush_rewrite_rules', true);
	// The upgrade runner does its work on the first admin request, once
	// WooCommerce has loaded; activation only marks that it is due.
	update_option('panelr_upgrade_due', '1');
	if (!get_option('panelr_db_version')) {
		set_transient('panelr_activation_redirect', 1, MINUTE_IN_SECONDS);
	}
});

add_action('init', function () {
	if (get_option('panelr_flush_rewrite_rules')) {
		flush_rewrite_rules();
		delete_option('panelr_flush_rewrite_rules');
	}
});

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

	// Kept on purpose: this plugin is distributed directly, so its translations live in its own languages/ folder.
	// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound
	load_plugin_textdomain('panelr-for-woocommerce', false, dirname(plugin_basename(__FILE__)) . '/languages');

	$includes = [
		'class-panelr-api.php',
		'class-panelr-helpers.php',
		'class-panelr-template.php',
		'class-panelr-upgrade-runner.php',
		'class-panelr-sync.php',
		'class-panelr-session.php',
		'class-panelr-cart.php',
		'class-panelr-checkout.php',
		'class-panelr-orders.php',
		'class-panelr-credits-gateway.php',
		'class-panelr-order-status.php',
		'class-panelr-handoff.php',
		'class-panelr-trial.php',
		'class-panelr-upgrade.php',
		'class-panelr-portal.php',
		'class-panelr-support.php',
		'class-panelr-apps.php',
		'class-panelr-bots.php',
		'class-panelr-settings.php',
		'class-panelr-admin-orders.php',
	];
	foreach ($includes as $file) {
		require_once PANELR_PLUGIN_DIR . 'includes/' . $file;
	}

	Panelr_Upgrade_Runner::init();
	Panelr_Settings::init();
	Panelr_Sync::init();
	Panelr_Session::init();
	Panelr_Cart::init();
	Panelr_Checkout::init();
	Panelr_Orders::init();
	Panelr_Credits_Gateway::init();
	Panelr_Portal::init();
	Panelr_Trial::init();
	Panelr_Upgrade::init();
	Panelr_Order_Status::init();
	Panelr_Handoff::init();
	Panelr_Support::init();
	Panelr_Apps::init();
	Panelr_Bots::init();
	Panelr_Admin_Orders::init();

	// Front-end theme (none | light | dark) — the class names are the
	// contract merchants style against, so the sheet is optional.
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

	// Shared front-end script (copy buttons, reveal, tabs).
	add_action('wp_enqueue_scripts', function () {
		wp_register_script('panelr-common', PANELR_PLUGIN_URL . 'assets/js/common.js', ['jquery'], PANELR_VERSION, true);
		wp_localize_script('panelr-common', 'panelrCommon', [
			'ajaxurl' => admin_url('admin-ajax.php'),
			'i18n'    => [
				'copied'         => __('Copied', 'panelr-for-woocommerce'),
				'copy'           => __('Copy', 'panelr-for-woocommerce'),
				'show'           => __('Show', 'panelr-for-woocommerce'),
				'hide'           => __('Hide', 'panelr-for-woocommerce'),
				'request_failed' => __('Something went wrong. Please try again.', 'panelr-for-woocommerce'),
				'working'        => __('Working…', 'panelr-for-woocommerce'),
			],
		]);
	});
});

// ── Asset versions follow the file, so an update is never served from cache ──
add_filter('script_loader_src', 'panelr_asset_src', 10, 2);
add_filter('style_loader_src',  'panelr_asset_src', 10, 2);
function panelr_asset_src($src, $handle)
{
	if (strpos((string) $handle, 'panelr-') !== 0 || strpos((string) $src, PANELR_PLUGIN_URL) !== 0) {
		return $src;
	}
	$rel  = (string) wp_parse_url(substr($src, strlen(PANELR_PLUGIN_URL)), PHP_URL_PATH);
	$file = PANELR_PLUGIN_DIR . $rel;
	if ($rel && file_exists($file)) {
		$src = add_query_arg('ver', PANELR_VERSION . '.' . filemtime($file), remove_query_arg('ver', $src));
	}
	return $src;
}

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
	$content = '<p>' . __('This plugin connects to your Panelr installation. When customers request a free trial, their name, email address, IP address and browser identity are sent to your Panelr server for abuse screening. When customers sign in, their account details are read from your Panelr server and held in the WooCommerce session for the length of that session; connection passwords are fetched only when a customer asks to see them and are never stored in WordPress. WooCommerce orders keep a reference to the matching Panelr order.', 'panelr-for-woocommerce') . '</p>';
	wp_add_privacy_policy_content('Panelr for WooCommerce', wp_kses_post($content));
});

// ── Declare HPOS compatibility ─────────────────────────────────────────────
add_action('before_woocommerce_init', function () {
	if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
	}
});
