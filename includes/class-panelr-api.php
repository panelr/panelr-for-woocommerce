<?php
defined('ABSPATH') || exit;

class Panelr_API
{

	private string $base_url;
	private string $api_key;

	public function __construct()
	{
		$this->base_url = rtrim((string) get_option('panelr_api_url', ''), '/');
		$this->api_key  = (string) get_option('panelr_api_key', '');
	}

	// ── Private helpers ────────────────────────────────────────────────────

	private function headers(): array
	{
		return [
			'X-Panelr-API-Key' => $this->api_key,
			'Content-Type'     => 'application/json',
			'Accept'           => 'application/json',
		];
	}

	private function endpoint(string $action): string
	{
		return $this->base_url . '/api/api.php?action=' . rawurlencode($action);
	}

	private function get(string $action): array
	{
		if (empty($this->base_url) || empty($this->api_key)) {
			return ['success' => false, 'error' => 'Panelr API is not configured.'];
		}

		$response = wp_remote_get($this->endpoint($action), [
			'headers' => $this->headers(),
			'timeout' => 15,
		]);

		return $this->parse($response);
	}

	private function post(string $action, array $body): array
	{
		if (empty($this->base_url) || empty($this->api_key)) {
			return ['success' => false, 'error' => 'Panelr API is not configured.'];
		}

		$response = wp_remote_post($this->endpoint($action), [
			'headers' => $this->headers(),
			'body'    => wp_json_encode($body),
			'timeout' => 20,
		]);

		return $this->parse($response);
	}

	private function parse(mixed $response): array
	{
		if (is_wp_error($response)) {
			return ['success' => false, 'error' => $response->get_error_message()];
		}

		$code = wp_remote_retrieve_response_code($response);
		$data = json_decode(wp_remote_retrieve_body($response), true);

		if (!is_array($data)) {
			return ['success' => false, 'error' => "Non-JSON response (HTTP $code)"];
		}

		return $data;
	}

	// ── Public methods ─────────────────────────────────────────────────────

	public function get_products(): array
	{
		return $this->get('get_products');
	}

	public function get_bouquets(): array
	{
		return $this->get('get_bouquets');
	}

	public function get_payment_methods(): array
	{
		return $this->get('get_payment_methods');
	}

	public function verify_login(string $username, string $password): array
	{
		return $this->post('verify_login', [
			'username' => $username,
			'password' => $password,
		]);
	}

	public function verify_trial(string $trial_code): array
	{
		return $this->post('verify_trial', [
			'trial_code' => $trial_code,
		]);
	}

	public function create_activation(array $data): array
	{
		return $this->post('create_activation', $data);
	}

	public function create_renewal(array $data): array
	{
		return $this->post('create_renewal', $data);
	}

	public function create_trial(array $data): array
	{
		return $this->post('create_trial', $data);
	}

	public function update_bouquets(int $activation_id, array $bouquets): array
	{
		if (array_keys($bouquets) !== range(0, count($bouquets) - 1)) {
			return $this->post('update_bouquets', [
				'activation_id' => $activation_id,
				'bouquets'      => $bouquets,
			]);
		}
		return $this->post('update_bouquets', [
			'activation_id' => $activation_id,
			'bouquet_ids'   => array_values(array_map('intval', $bouquets)),
		]);
	}

	public function update_customer(int $activation_id, array $data): array
	{
		return $this->post('update_customer', array_merge(
			['activation_id' => $activation_id],
			$data
		));
	}

	public function get_work_order(array $params): array
	{
		if (empty($this->base_url) || empty($this->api_key)) {
			return ['success' => false, 'error' => 'Panelr API is not configured.'];
		}

		$query = http_build_query($params);
		$url   = $this->endpoint('get_work_order') . '&' . $query;

		$response = wp_remote_get($url, [
			'headers' => $this->headers(),
			'timeout' => 15,
		]);

		return $this->parse($response);
	}

	public function submit_payment_for_order(array $data): array
	{
		return $this->post('submit_payment', $data);
	}

	public function complete_order(array $data): array
	{
		return $this->post('complete_order', $data);
	}

	public function create_work_order(array $data): array
	{
		return $this->post('create_work_order', $data);
	}

	public function submit_payment(string $confirmation_token, string $transaction_id = '', string $note = ''): array
	{
		return $this->post('submit_payment', [
			'confirmation_token'     => $confirmation_token,
			'payment_transaction_id' => $transaction_id,
			'customer_note'          => $note,
		]);
	}

	public function send_payment_instructions(int $work_order_id, string $confirm_url): array
	{
		return $this->post('send_payment_instructions', [
			'work_order_id' => $work_order_id,
			'confirm_url'   => $confirm_url,
		]);
	}

	public function test_connection(): bool
	{
		$result = $this->get_products();
		return !empty($result['success']);
	}
}
