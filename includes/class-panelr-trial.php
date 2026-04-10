<?php
defined('ABSPATH') || exit;

class Panelr_Trial
{

	public static function init(): void
	{
		add_shortcode('panelr_trial', [__CLASS__, 'render']);
		add_action('wp_ajax_panelr_request_trial',        [__CLASS__, 'ajax_request_trial']);
		add_action('wp_ajax_nopriv_panelr_request_trial', [__CLASS__, 'ajax_request_trial']);
	}

	// ── AJAX: request trial ────────────────────────────────────────────────

	public static function ajax_request_trial(): void
	{
		check_ajax_referer('panelr_trial_nonce', 'nonce');

		if (!get_option('panelr_trials_enabled', '0')) {
			wp_send_json_error(['message' => __('Free trials are not currently available.', 'panelr-for-woocommerce')]);
		}

		$name  = sanitize_text_field(wp_unslash($_POST['name']  ?? ''));
		$email = sanitize_email(wp_unslash($_POST['email'] ?? ''));

		if (!$email || !is_email($email)) {
			wp_send_json_error(['message' => __('A valid email address is required.', 'panelr-for-woocommerce')]);
		}

		// Collect real customer IP server-side
		$raw_ip = sanitize_text_field(wp_unslash(
			$_SERVER['HTTP_CF_CONNECTING_IP']
				?? $_SERVER['HTTP_X_FORWARDED_FOR']
				?? $_SERVER['REMOTE_ADDR']
				?? ''
		));

		// Take first IP if comma-separated list (X-Forwarded-For)
		if (strpos($raw_ip, ',') !== false) {
			$raw_ip = trim(explode(',', $raw_ip)[0]);
		}

		$customer_ip = filter_var($raw_ip, FILTER_VALIDATE_IP) ? $raw_ip : '';
		$user_agent  = sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? ''));

		$api    = new Panelr_API();
		$result = $api->create_trial([
			'email'       => $email,
			'name'        => $name,
			'customer_ip' => $customer_ip,
			'user_agent'  => $user_agent,
		]);

		if (empty($result['success'])) {
			wp_send_json_error(['message' => $result['error'] ?? __('Could not process your trial request. Please try again.', 'panelr-for-woocommerce')]);
		}

		$status = $result['data']['status'] ?? '';

		if ($status === 'approved') {
			wp_send_json_success([
				'status'  => 'approved',
				'message' => __('Your free trial has been activated! Check your email for connection details.', 'panelr-for-woocommerce'),
			]);
		} else {
			wp_send_json_success([
				'status'  => 'pending',
				'message' => __('Your trial request has been received and is pending approval. You will receive an email once it is approved.', 'panelr-for-woocommerce'),
			]);
		}
	}

	// ── Render shortcode ───────────────────────────────────────────────────

	public static function render(): string
	{
		if (!get_option('panelr_trials_enabled', '0')) {
			return '<p class="panelr-trial__disabled">' . esc_html__('Free trials are not currently available.', 'panelr-for-woocommerce') . '</p>';
		}

		wp_enqueue_script(
			'panelr-trial',
			PANELR_PLUGIN_URL . 'assets/js/trial.js',
			['jquery'],
			PANELR_VERSION,
			true
		);
		wp_localize_script('panelr-trial', 'panelrTrial', [
			'ajaxurl' => admin_url('admin-ajax.php'),
			'nonce'   => wp_create_nonce('panelr_trial_nonce'),
		]);

		ob_start();
?>
		<div class="panelr-trial" id="panelr-trial-wrap">
			<div id="panelr-trial-form">
				<p id="panelr-trial-error" class="panelr-trial__error woocommerce-error" style="display:none;"></p>
				<p class="panelr-trial__field">
					<label for="panelr-trial-name"><?php esc_html_e('Name', 'panelr-for-woocommerce'); ?></label>
					<input type="text" id="panelr-trial-name" class="input-text" autocomplete="name">
				</p>
				<p class="panelr-trial__field">
					<label for="panelr-trial-email"><?php esc_html_e('Email Address', 'panelr-for-woocommerce'); ?> <span class="required">*</span></label>
					<input type="email" id="panelr-trial-email" class="input-text" autocomplete="email">
				</p>
				<p class="panelr-trial__field">
					<button type="button" id="panelr-trial-submit" class="button"><?php esc_html_e('Request Free Trial', 'panelr-for-woocommerce'); ?></button>
				</p>
			</div>
			<div id="panelr-trial-result" style="display:none;"></div>
		</div>
<?php
		return ob_get_clean();
	}
}
