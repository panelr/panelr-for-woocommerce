<?php
defined('ABSPATH') || exit;

/**
 * [panelr_trial] — a trial on one service. Panelr screens (daily cap,
 * already a customer, one per email / network, disposable, VPN) and its
 * words are shown as they come. The plugin checks Turnstile itself when
 * the operator set the keys.
 */
class Panelr_Trial
{
	public static function init(): void
	{
		add_shortcode('panelr_trial', [__CLASS__, 'render']);
		add_action('wp_ajax_panelr_request_trial', [__CLASS__, 'ajax_request_trial']);
		add_action('wp_ajax_nopriv_panelr_request_trial', [__CLASS__, 'ajax_request_trial']);
	}

	/** Services that offer a trial, keyed by plugin id, in Panelr's order. */
	public static function trial_services(): array
	{
		$ids = Panelr_Helpers::trial_services();
		$out = [];
		foreach (Panelr_Helpers::services() as $id => $svc) {
			if (in_array($id, $ids, true) || (!$ids && !empty($svc['trials_enabled']))) {
				$out[$id] = $svc;
			}
		}
		return $out;
	}

	public static function render($atts = []): string
	{
		if (!Panelr_Helpers::bool_option('panelr_trials_enabled', '0')) {
			return Panelr_Template::render('trial-disabled', []);
		}
		$services = self::trial_services();
		if (!$services && Panelr_API::instance()->is_configured()) {
			return Panelr_Template::render('trial-disabled', []);
		}

		wp_enqueue_script('panelr-common');
		wp_enqueue_script('panelr-trial', PANELR_PLUGIN_URL . 'assets/js/trial.js', ['jquery', 'panelr-common'], PANELR_VERSION, true);
		wp_localize_script('panelr-trial', 'panelrTrial', [
			'ajaxurl'   => admin_url('admin-ajax.php'),
			'nonce'     => wp_create_nonce('panelr_trial_nonce'),
			'turnstile' => Panelr_Helpers::turnstile_enabled() ? '1' : '0',
			'i18n'      => [
				'need_email'     => __('Enter your email address.', 'panelr-for-woocommerce'),
				'need_human'     => __('Please complete the check below.', 'panelr-for-woocommerce'),
				'sending'        => __('Sending…', 'panelr-for-woocommerce'),
				'button'         => __('Start my free trial', 'panelr-for-woocommerce'),
				'request_failed' => __('Something went wrong. Please try again.', 'panelr-for-woocommerce'),
			],
		]);
		if (Panelr_Helpers::turnstile_enabled()) {
			// Cloudflare Turnstile only exists as Cloudflare's own script; it loads solely when the operator has entered their Turnstile keys.
			// phpcs:ignore PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent
			wp_enqueue_script('cf-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', [], PANELR_VERSION, true);
		}

		return Panelr_Template::render('trial', [
			'services'      => $services,
			'turnstile_key' => Panelr_Helpers::turnstile_enabled() ? (string) get_option('panelr_turnstile_site_key', '') : '',
			'signed_in'     => Panelr_Session::is_signed_in(),
			'email'         => Panelr_Session::email(),
			'name'          => Panelr_Session::name(),
		]);
	}

	public static function ajax_request_trial(): void
	{
		check_ajax_referer('panelr_trial_nonce', 'nonce');

		if (!Panelr_Helpers::bool_option('panelr_trials_enabled', '0')) {
			wp_send_json_error(['message' => __('Free trials are not available right now.', 'panelr-for-woocommerce')]);
		}

		$name    = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
		$email   = sanitize_email(wp_unslash($_POST['email'] ?? ''));
		$service = absint(wp_unslash($_POST['service'] ?? 0));
		$token   = sanitize_text_field(wp_unslash($_POST['turnstile_token'] ?? ''));

		if (!$email || !is_email($email)) {
			wp_send_json_error(['message' => __('Enter a valid email address.', 'panelr-for-woocommerce')]);
		}
		if (!Panelr_Helpers::rate_limit('trial', 5, 15 * MINUTE_IN_SECONDS)) {
			wp_send_json_error(['message' => __('Too many tries. Please wait a few minutes.', 'panelr-for-woocommerce')]);
		}
		if (!Panelr_Helpers::verify_turnstile($token)) {
			wp_send_json_error(['message' => __('The check did not pass. Please try again.', 'panelr-for-woocommerce')]);
		}

		$services = self::trial_services();
		if ($service && !isset($services[$service])) {
			wp_send_json_error(['message' => __('That service is not offering trials.', 'panelr-for-woocommerce')]);
		}
		if (!$service && count($services) === 1) {
			$service = (int) array_key_first($services);
		}

		$body = [
			'email'       => $email,
			'name'        => $name,
			'customer_ip' => Panelr_Helpers::client_ip(),
			'user_agent'  => Panelr_Helpers::user_agent(),
		];
		if ($service) $body['service'] = $service;

		$result = Panelr_API::instance()->create_trial($body);
		if (!$result['ok']) {
			// Panelr's screening messages are written for the customer.
			wp_send_json_error(['message' => $result['error']]);
		}

		$status = (string) ($result['data']['status'] ?? '');
		if ($status === 'approved') {
			wp_send_json_success([
				'status'  => 'approved',
				'message' => __('Your trial is ready. Check your email for your connection details.', 'panelr-for-woocommerce'),
			]);
		}
		$ref = (string) ($result['data']['reference_code'] ?? '');
		wp_send_json_success([
			'status'    => 'pending',
			'reference' => $ref,
			'message'   => $ref
				? sprintf(
					/* translators: %s: reference code */
					__('We received your request. Your reference is %s. You will get an email once it is approved.', 'panelr-for-woocommerce'),
					$ref
				)
				: __('We received your request. You will get an email once it is approved.', 'panelr-for-woocommerce'),
		]);
	}
}
