<?php
defined('ABSPATH') || exit;

/**
 * [panelr_upgrade] — trial to paid. ?panelr_t=<trial code> or ?t=<activation
 * token> may prefill, never auto-submit; Verify posts with a nonce and is
 * rate limited per IP. A trial upgrades to any plan on its own service.
 */
class Panelr_Upgrade
{
	public static function init(): void
	{
		add_shortcode('panelr_upgrade', [__CLASS__, 'render']);
		add_action('wp_ajax_panelr_upgrade_verify', [__CLASS__, 'ajax_verify']);
		add_action('wp_ajax_nopriv_panelr_upgrade_verify', [__CLASS__, 'ajax_verify']);
		add_action('wp_ajax_panelr_upgrade_add_to_cart', [__CLASS__, 'ajax_add_to_cart']);
		add_action('wp_ajax_nopriv_panelr_upgrade_add_to_cart', [__CLASS__, 'ajax_add_to_cart']);
	}

	public static function render($atts = []): string
	{
		if (WC()->session && !WC()->session->has_session()) {
			WC()->session->set_customer_session_cookie(true);
		}

		wp_enqueue_script('panelr-common');
		wp_enqueue_script('panelr-upgrade', PANELR_PLUGIN_URL . 'assets/js/upgrade.js', ['jquery', 'panelr-common'], PANELR_VERSION, true);
		wp_localize_script('panelr-upgrade', 'panelrUpgrade', [
			'ajaxurl' => admin_url('admin-ajax.php'),
			'nonce'   => wp_create_nonce('panelr_upgrade_nonce'),
			'i18n'    => [
				'need_code'      => __('Enter your trial code.', 'panelr-for-woocommerce'),
				'verifying'      => __('Checking…', 'panelr-for-woocommerce'),
				'continue'       => __('Continue', 'panelr-for-woocommerce'),
				'adding'         => __('Adding…', 'panelr-for-woocommerce'),
				'request_failed' => __('Something went wrong. Please try again.', 'panelr-for-woocommerce'),
			],
		]);

		// Prefill only from Panelr's emailed link; Verify is a nonce-checked request.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$trial_code = strtoupper(sanitize_text_field(wp_unslash($_GET['panelr_t'] ?? '')));
		$token      = sanitize_text_field(wp_unslash($_GET['t'] ?? ''));
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$verified   = Panelr_Session::get('panelr_upgrade');

		return Panelr_Template::render('upgrade', [
			'trial_code' => $trial_code,
			'token'      => $token,
			'verified'   => is_array($verified) ? $verified : null,
			'plans'      => is_array($verified) ? self::plans_for($verified) : [],
			'portal_url' => Panelr_Helpers::portal_url(),
			'multi'      => Panelr_Helpers::multi_service(),
		]);
	}

	/** Plans a verified trial may upgrade to: every plan on its service, from the synced products. */
	private static function plans_for(array $verified): array
	{
		$plugin_id = (int) ($verified['plugin_id'] ?? 0);
		$plans = Panelr_Helpers::renewal_products($plugin_id, 0, true);
		if (!$plans && !empty($verified['eligible_products'])) {
			// Fall back to Panelr's own list, mapped onto synced products.
			foreach ($verified['eligible_products'] as $p) {
				$row = Panelr_Helpers::product_row_by_panelr_id((int) $p['id']);
				if ($row && $row['status'] === 'publish') $plans[] = $row;
			}
		}
		return $plans;
	}

	public static function ajax_verify(): void
	{
		check_ajax_referer('panelr_upgrade_nonce', 'nonce');
		if (!Panelr_Helpers::rate_limit('trial_verify', 5, 15 * MINUTE_IN_SECONDS)) {
			wp_send_json_error(['message' => __('Too many tries. Please wait a few minutes.', 'panelr-for-woocommerce')]);
		}

		$code  = strtoupper(sanitize_text_field(wp_unslash($_POST['trial_code'] ?? '')));
		$token = sanitize_text_field(wp_unslash($_POST['activation_token'] ?? ''));
		if (!$code && strlen($token) < 32) {
			wp_send_json_error(['message' => __('Enter your trial code.', 'panelr-for-woocommerce')]);
		}

		$result = Panelr_API::instance()->verify_trial($code ? ['trial_code' => $code] : ['activation_token' => $token]);
		if (!$result['ok']) {
			wp_send_json_error(['message' => $result['status'] === 404 || $result['status'] === 422
				? __('We could not find a trial with that code.', 'panelr-for-woocommerce')
				: $result['error']]);
		}

		$d = $result['data'];
		Panelr_Session::set('panelr_upgrade', [
			'activation_id'     => (int) $d['activation_id'],
			'trial_request_id'  => (int) ($d['trial_request_id'] ?? 0),
			'customer_name'     => (string) ($d['customer_name'] ?? ''),
			'customer_email'    => (string) ($d['customer_email'] ?? ''),
			'expiration_date'   => $d['expiration_date'] ?? null,
			'connections'       => (int) ($d['connections'] ?? 0),
			'plugin_id'         => (int) ($d['plugin_id'] ?? 0),
			'plugin_name'       => (string) ($d['plugin_name'] ?? ''),
			'eligible_products' => (array) ($d['eligible_products'] ?? []),
			'verified_at'       => time(),
		]);
		wp_send_json_success(['reload' => true]);
	}

	public static function ajax_add_to_cart(): void
	{
		check_ajax_referer('panelr_upgrade_nonce', 'nonce');
		$verified = Panelr_Session::get('panelr_upgrade');
		$panelr_product_id = absint(wp_unslash($_POST['panelr_product_id'] ?? 0));
		if (!is_array($verified) || empty($verified['activation_id']) || !$panelr_product_id) {
			wp_send_json_error(['message' => __('Please check your trial code again.', 'panelr-for-woocommerce')]);
		}

		$row = Panelr_Helpers::product_row_by_panelr_id($panelr_product_id);
		if (!$row || $row['status'] !== 'publish' || $row['is_trial']) {
			wp_send_json_error(['message' => __('That plan is not available right now.', 'panelr-for-woocommerce')]);
		}
		if (!empty($verified['plugin_id']) && $row['plugin_id'] && (int) $verified['plugin_id'] !== $row['plugin_id']) {
			wp_send_json_error(['message' => __('That plan is on a different service than your trial.', 'panelr-for-woocommerce')]);
		}

		$key = WC()->cart->add_to_cart($row['wc_id'], 1, 0, [], [
			'_panelr_intent'        => 'trial_upgrade',
			'_panelr_activation_id' => (int) $verified['activation_id'],
			'_panelr_plugin_id'     => $row['plugin_id'],
			'_panelr_line_label'    => (string) ($verified['customer_email'] ?? ''),
		]);
		if (!$key) {
			wp_send_json_error(['message' => __('That plan could not be added to the cart.', 'panelr-for-woocommerce')]);
		}
		if (WC()->customer && !empty($verified['customer_email'])) {
			WC()->customer->set_billing_email((string) $verified['customer_email']);
		}
		wp_send_json_success(['checkout_url' => wc_get_checkout_url()]);
	}
}
