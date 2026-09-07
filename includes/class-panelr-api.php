<?php
defined('ABSPATH') || exit;

/**
 * The one client for Panelr's API.
 *
 * One store-wide key, one entry point, JSON in and out. Every call returns
 * ['ok', 'status', 'data', 'error'] and treats a non-2xx status or
 * success:false as failure. Catalogue reads are cached for ten minutes;
 * anything scoped to a customer never is.
 */
class Panelr_API
{
	const CACHE_TTL          = 10 * MINUTE_IN_SECONDS;
	const LOG_SOURCE         = 'panelr';
	const RATE_LIMIT_KEY     = 'panelr_rate_limited';
	const LAST_ERROR_KEY     = 'panelr_last_error';
	const FAILURE_COUNT_KEY  = 'panelr_failure_count';

	/** Actions whose answers may be cached (keyed by action + query). */
	const CACHEABLE = [
		'get_store_settings',
		'get_plugins',
		'get_products',
		'get_payment_methods',
		'get_bouquets',
		'get_apps',
		'get_support_quick_replies',
	];

	private static ?Panelr_API $instance = null;

	private string $base_url;
	private string $api_key;
	private bool   $key_from_config = false;
	private bool   $url_from_config = false;

	public static function instance(): Panelr_API
	{
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** Forget the instance so a settings save is picked up in the same request. */
	public static function reset(): void
	{
		self::$instance = null;
	}

	public function __construct()
	{
		if (defined('PANELR_API_URL') && PANELR_API_URL) {
			$this->base_url        = rtrim((string) PANELR_API_URL, '/');
			$this->url_from_config = true;
		} else {
			$this->base_url = rtrim((string) get_option('panelr_api_url', ''), '/');
		}

		if (defined('PANELR_API_KEY') && PANELR_API_KEY) {
			$this->api_key         = (string) PANELR_API_KEY;
			$this->key_from_config = true;
		} else {
			$this->api_key = (string) get_option('panelr_api_key', '');
		}

		// A base URL saved with the entry point on the end still works.
		$this->base_url = preg_replace('#/api/api\.php$#', '', $this->base_url);
	}

	// ── State ─────────────────────────────────────────────────────────────

	public function is_configured(): bool
	{
		return $this->base_url !== '' && $this->api_key !== '';
	}

	public function key_is_from_config(): bool
	{
		return $this->key_from_config;
	}

	public function url_is_from_config(): bool
	{
		return $this->url_from_config;
	}

	public function base_url(): string
	{
		return $this->base_url;
	}

	public function has_key(): bool
	{
		return $this->api_key !== '';
	}

	public static function is_rate_limited(): bool
	{
		return (bool) get_transient(self::RATE_LIMIT_KEY);
	}

	public static function last_error(): ?array
	{
		$err = get_transient(self::LAST_ERROR_KEY);
		return is_array($err) ? $err : null;
	}

	public static function failure_count(): int
	{
		return (int) get_transient(self::FAILURE_COUNT_KEY);
	}

	public static function clear_error_state(): void
	{
		delete_transient(self::LAST_ERROR_KEY);
		delete_transient(self::FAILURE_COUNT_KEY);
		delete_transient(self::RATE_LIMIT_KEY);
	}

	// ── Requests ──────────────────────────────────────────────────────────

	/**
	 * @param string $action  Panelr action name.
	 * @param string $method  GET or POST.
	 * @param array  $payload Query parameters (GET) or JSON body (POST).
	 * @return array{ok:bool,status:int,data:mixed,error:string}
	 */
	public function request(string $action, string $method = 'GET', array $payload = []): array
	{
		if (!$this->is_configured()) {
			return $this->fail(0, __('Panelr is not connected yet. Enter the address and key under Panelr → Connection.', 'panelr-for-woocommerce'));
		}

		if (self::is_rate_limited()) {
			return $this->fail(429, __('Panelr is asking us to slow down. Please try again in a few minutes.', 'panelr-for-woocommerce'));
		}

		$method    = strtoupper($method) === 'POST' ? 'POST' : 'GET';
		$cacheable = $method === 'GET' && in_array($action, self::CACHEABLE, true);
		$cache_key = $cacheable ? $this->cache_key($action, $payload) : '';

		if ($cacheable) {
			$cached = get_transient($cache_key);
			if (is_array($cached) && array_key_exists('data', $cached)) {
				return ['ok' => true, 'status' => 200, 'data' => $cached['data'], 'error' => ''];
			}
		}

		$url = $this->base_url . '/api/api.php?action=' . rawurlencode($action);
		$args = [
			'headers' => [
				'X-Panelr-API-Key' => $this->api_key,
				'Content-Type'     => 'application/json',
				'Accept'           => 'application/json',
			],
			'timeout' => $method === 'POST' ? 25 : 15,
		];

		if ($method === 'GET') {
			if ($payload) {
				$url .= '&' . http_build_query(array_map('strval', $payload), '', '&', PHP_QUERY_RFC3986);
			}
			$response = wp_remote_get($url, $args);
		} else {
			$args['body'] = wp_json_encode($payload);
			$response     = wp_remote_post($url, $args);
		}

		if (is_wp_error($response)) {
			return $this->fail(0, $response->get_error_message(), $action);
		}

		$status = (int) wp_remote_retrieve_response_code($response);
		$body   = wp_remote_retrieve_body($response);
		$json   = json_decode($body, true);

		if ($status === 429) {
			set_transient(self::RATE_LIMIT_KEY, time(), 2 * MINUTE_IN_SECONDS);
			return $this->fail(429, __('Panelr is asking us to slow down. Please try again in a few minutes.', 'panelr-for-woocommerce'), $action);
		}

		if (!is_array($json)) {
			return $this->fail($status, sprintf(
				/* translators: %d: HTTP status code */
				__('Panelr answered with something we could not read (HTTP %d).', 'panelr-for-woocommerce'),
				$status
			), $action);
		}

		// verify_trial uses an older envelope: no error key on success and no
		// data key on failure. Normalise it so callers never see the difference.
		$success = !empty($json['success']) && $status >= 200 && $status < 300;
		$data    = $json['data'] ?? null;
		$error   = (string) ($json['error'] ?? ($json['message'] ?? ''));

		if (!$success) {
			if ($error === '') {
				$error = __('Panelr could not complete that request.', 'panelr-for-woocommerce');
			}
			return $this->fail($status ?: 500, $error, $action, $data);
		}

		if ($cacheable) {
			set_transient($cache_key, ['data' => $data], self::CACHE_TTL);
		}

		if (self::failure_count() > 0) {
			delete_transient(self::FAILURE_COUNT_KEY);
		}

		return ['ok' => true, 'status' => $status, 'data' => $data, 'error' => ''];
	}

	public function get(string $action, array $query = []): array
	{
		return $this->request($action, 'GET', $query);
	}

	public function post(string $action, array $body = []): array
	{
		return $this->request($action, 'POST', $body);
	}

	private function fail(int $status, string $error, string $action = '', $data = null): array
	{
		if ($action !== '') {
			$this->log_failure($action, $status, $error);
		}
		return ['ok' => false, 'status' => $status, 'data' => $data, 'error' => $error];
	}

	private function log_failure(string $action, int $status, string $error): void
	{
		if (function_exists('wc_get_logger')) {
			wc_get_logger()->error(
				sprintf('%s failed (HTTP %d): %s', $action, $status, $error),
				['source' => self::LOG_SOURCE]
			);
		}

		// Customer-facing refusals (404 / 422 / 401) are answers, not outages.
		if (in_array($status, [401, 404, 422], true)) {
			return;
		}

		set_transient(self::LAST_ERROR_KEY, [
			'action' => $action,
			'status' => $status,
			'error'  => $error,
			'at'     => time(),
		], DAY_IN_SECONDS);
		set_transient(self::FAILURE_COUNT_KEY, self::failure_count() + 1, HOUR_IN_SECONDS);
	}

	// ── Cache ─────────────────────────────────────────────────────────────

	private function cache_key(string $action, array $query): string
	{
		ksort($query);
		return 'panelr_c_' . (int) get_option('panelr_cache_version', 1) . '_' . md5($action . '|' . wp_json_encode($query));
	}

	/** Forget every cached catalogue answer. */
	public static function flush_cache(): void
	{
		update_option('panelr_cache_version', (int) get_option('panelr_cache_version', 1) + 1, false);
	}

	// ── Convenience wrappers (names follow Panelr's actions) ──────────────

	public function get_store_settings(): array
	{
		return $this->get('get_store_settings');
	}

	public function get_plugins(): array
	{
		return $this->get('get_plugins');
	}

	public function get_products(int $plugin_id = 0): array
	{
		return $this->get('get_products', $plugin_id ? ['plugin_id' => $plugin_id] : []);
	}

	public function get_payment_methods(): array
	{
		return $this->get('get_payment_methods');
	}

	public function get_bouquets(int $plugin_id = 0): array
	{
		return $this->get('get_bouquets', $plugin_id ? ['plugin_id' => $plugin_id] : []);
	}

	public function get_apps(int $plugin_id = 0): array
	{
		return $this->get('get_apps', $plugin_id ? ['plugin_id' => $plugin_id] : []);
	}

	public function get_lines_for_customer(int $customer_id): array
	{
		return $this->get('get_lines', ['customer_id' => $customer_id]);
	}

	public function get_lines_for_email(string $email): array
	{
		return $this->get('get_lines', ['customer_email' => $email]);
	}

	public function get_customer(int $customer_id): array
	{
		return $this->get('get_customer', ['customer_id' => $customer_id]);
	}

	public function get_customer_by_email(string $email): array
	{
		return $this->get('get_customer', ['customer_email' => $email]);
	}

	public function verify_customer_login(string $email, string $password): array
	{
		// The visitor's own address lets Panelr throttle a guesser without
		// blocking every visitor of this store (they all share the store's IP).
		return $this->post('verify_customer_login', ['customer_email' => $email, 'password' => $password, 'customer_ip' => Panelr_Helpers::client_ip()]);
	}

	public function verify_login(string $username, string $password, int $plugin_id = 0): array
	{
		$body = ['username' => $username, 'password' => $password, 'customer_ip' => Panelr_Helpers::client_ip()];
		if ($plugin_id) $body['plugin_id'] = $plugin_id;
		return $this->post('verify_login', $body);
	}

	public function register_customer(array $body): array
	{
		return $this->post('register_customer', $body);
	}

	public function update_customer_account(array $body): array
	{
		return $this->post('update_customer_account', $body);
	}

	public function start_chat_link(int $customer_id, string $platform): array
	{
		return $this->post('start_chat_link', ['customer_id' => $customer_id, 'platform' => $platform]);
	}

	public function cancel_chat_link(int $customer_id): array
	{
		return $this->post('cancel_chat_link', ['customer_id' => $customer_id]);
	}

	public function unlink_chat(int $customer_id, string $platform): array
	{
		return $this->post('unlink_chat', ['customer_id' => $customer_id, 'platform' => $platform]);
	}

	public function set_customer_password(array $body): array
	{
		return $this->post('set_customer_password', $body);
	}

	public function request_password_reset(string $email, string $reset_url): array
	{
		return $this->post('request_password_reset', ['customer_email' => $email, 'reset_url' => $reset_url, 'hours' => 2]);
	}

	public function reset_customer_password(string $token, string $password): array
	{
		return $this->post('reset_customer_password', ['reset_token' => $token, 'password' => $password]);
	}

	public function confirm_customer_email(string $token): array
	{
		return $this->post('confirm_customer_email', ['verify_token' => $token]);
	}

	public function update_customer(int $activation_id, array $data): array
	{
		// Panelr needs to know whose line this is (it refuses a line that is
		// not the named customer's); the signed-in member is the owner.
		if (empty($data['customer_id']) && empty($data['customer_email']) && Panelr_Session::customer_id() > 0) {
			$data['customer_id'] = Panelr_Session::customer_id();
		}
		return $this->post('update_customer', array_merge(['activation_id' => $activation_id], $data));
	}

	/** Re-send the address confirmation email to a signed-in account. */
	public function resend_verification(int $customer_id, string $verify_url): array
	{
		return $this->post('update_customer_account', ['customer_id' => $customer_id, 'verify_url' => $verify_url, 'resend_verification' => true]);
	}

	public function update_bouquets(int $activation_id, array $payload): array
	{
		return $this->post('update_bouquets', array_merge(['activation_id' => $activation_id], $payload));
	}

	public function verify_trial(array $body): array
	{
		return $this->post('verify_trial', $body);
	}

	public function create_trial(array $body): array
	{
		return $this->post('create_trial', $body);
	}

	public function create_work_order(array $body): array
	{
		return $this->post('create_work_order', $body);
	}

	public function complete_order(array $body): array
	{
		return $this->post('complete_order', $body);
	}

	public function submit_payment(array $body): array
	{
		return $this->post('submit_payment', $body);
	}

	public function send_payment_instructions(int $work_order_id, string $confirm_url): array
	{
		return $this->post('send_payment_instructions', ['work_order_id' => $work_order_id, 'confirm_url' => $confirm_url]);
	}

	public function get_work_order(string $ref, ?string $token = null, ?string $email = null): array
	{
		$query = ['ref' => $ref];
		if ($token) $query['token'] = $token;
		if ($email) $query['email'] = $email;
		return $this->get('get_work_order', $query);
	}

	public function get_work_orders(int $customer_id, int $limit = 50): array
	{
		return $this->get('get_work_orders', ['customer_id' => $customer_id, 'limit' => $limit]);
	}

	public function validate_coupon(string $code, float $total, string $email = ''): array
	{
		$query = ['code' => $code, 'total' => number_format($total, 2, '.', '')];
		if ($email) $query['customer_email'] = $email;
		return $this->get('validate_coupon', $query);
	}

	public function validate_referral_code(string $code): array
	{
		return $this->get('validate_referral_code', ['code' => $code]);
	}

	public function get_referral_customer(int $customer_id): array
	{
		return $this->get('get_referral_customer', ['customer_id' => $customer_id, 'ledger_limit' => 100]);
	}

	public function get_support_tickets(string $email): array
	{
		return $this->get('get_support_tickets', ['email' => $email]);
	}

	public function get_support_ticket(string $ref, string $email): array
	{
		return $this->get('get_support_ticket', ['ticket_ref' => $ref, 'email' => $email]);
	}

	public function create_support_ticket(array $body): array
	{
		return $this->post('create_support_ticket', $body);
	}

	public function reply_support_ticket(string $ref, string $email, string $message): array
	{
		return $this->post('reply_support_ticket', ['ticket_ref' => $ref, 'email' => $email, 'message' => $message]);
	}

	public function close_support_ticket(string $ref, string $email): array
	{
		return $this->post('close_support_ticket', ['ticket_ref' => $ref, 'email' => $email]);
	}

	/** True when the key and address reach Panelr. Clears the error state on success. */
	public function test_connection(): array
	{
		$result = $this->get('get_store_settings');
		if ($result['ok']) {
			self::clear_error_state();
		}
		return $result;
	}
}
