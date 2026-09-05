<?php
defined('ABSPATH') || exit;

/**
 * Small shared answers: services, products, pages, dates, wording.
 */
class Panelr_Helpers
{
	private static ?array $services = null;
	private static ?array $store_settings = null;
	private static ?array $payment_methods = null;

	// ── Services ──────────────────────────────────────────────────────────

	/** Every service Panelr offers, keyed by plugin_id. Empty when unreachable. */
	public static function services(): array
	{
		if (self::$services !== null) return self::$services;

		$result = Panelr_API::instance()->get_plugins();
		$out    = [];
		if ($result['ok'] && !empty($result['data']['plugins'])) {
			foreach ($result['data']['plugins'] as $svc) {
				$out[(int) $svc['plugin_id']] = $svc;
			}
		}
		self::$services = $out;
		return $out;
	}

	public static function service(int $plugin_id): ?array
	{
		return self::services()[$plugin_id] ?? null;
	}

	/** The name shown on this store: the operator's own name for the service when set, else Panelr's. */
	public static function service_name(?int $plugin_id): string
	{
		if (!$plugin_id) return '';
		$own = self::service_name_overrides()[$plugin_id] ?? '';
		if ($own !== '') return $own;
		$svc = self::service($plugin_id);
		return $svc ? (string) $svc['name'] : '';
	}

	/** Panelr's own name for the service, regardless of the override. */
	public static function service_panelr_name(int $plugin_id): string
	{
		$svc = self::service($plugin_id);
		return $svc ? (string) $svc['name'] : '';
	}

	/** @return array<int,string> plugin_id => name the store shows */
	public static function service_name_overrides(): array
	{
		$raw = json_decode((string) get_option('panelr_service_names', '{}'), true);
		$out = [];
		foreach ((array) $raw as $id => $name) {
			$name = trim((string) $name);
			if ((int) $id && $name !== '') $out[(int) $id] = $name;
		}
		return $out;
	}

	/** True when the store sells more than one service — the cue to show service names. */
	public static function multi_service(): bool
	{
		return count(self::services()) > 1;
	}

	public static function store_currency(): string
	{
		$result = Panelr_API::instance()->get_plugins();
		return strtoupper((string) ($result['data']['currency'] ?? get_woocommerce_currency()));
	}

	// ── Store settings ────────────────────────────────────────────────────

	public static function store_settings(): array
	{
		if (self::$store_settings !== null) return self::$store_settings;
		$result = Panelr_API::instance()->get_store_settings();
		self::$store_settings = ($result['ok'] && is_array($result['data'])) ? $result['data'] : [];
		return self::$store_settings;
	}

	public static function referral_enabled(): bool
	{
		return !empty(self::store_settings()['referral']['enabled']);
	}

	public static function signup_points(): int
	{
		return (int) (self::store_settings()['referral']['signup_points'] ?? 0);
	}

	public static function support_enabled(): bool
	{
		return !empty(self::store_settings()['support']['enabled']);
	}

	public static function support_topics(): array
	{
		return (array) (self::store_settings()['support']['topics'] ?? []);
	}

	public static function apps_page_mode(): string
	{
		$mode = (string) (self::store_settings()['features']['apps_page'] ?? 'off');
		return in_array($mode, ['off', 'members', 'public'], true) ? $mode : 'off';
	}

	public static function trial_services(): array
	{
		return array_map('intval', (array) (self::store_settings()['services']['trial_services'] ?? []));
	}

	public static function accounts_enabled(): bool
	{
		return !empty(self::store_settings()['features']['accounts']);
	}

	public static function require_invite(): bool
	{
		return !empty(self::store_settings()['gate']['require_invite']);
	}

	// ── Payment methods ───────────────────────────────────────────────────

	/** Panelr's enabled payment methods keyed by id; null when the list cannot be read. */
	public static function payment_methods(): ?array
	{
		if (self::$payment_methods !== null) return self::$payment_methods ?: null;

		$result = Panelr_API::instance()->get_payment_methods();
		if (!$result['ok'] || !is_array($result['data'])) {
			self::$payment_methods = [];
			return null;
		}
		$out = [];
		foreach ($result['data'] as $pm) {
			$out[(int) $pm['id']] = $pm;
		}
		self::$payment_methods = $out;
		return $out;
	}

	public static function payment_map(): array
	{
		$map = json_decode((string) get_option('panelr_payment_map', '{}'), true);
		return is_array($map) ? $map : [];
	}

	public static function mapped_method_id(string $gateway_id): ?int
	{
		$map = self::payment_map();
		return !empty($map[$gateway_id]) ? (int) $map[$gateway_id] : null;
	}

	/** The Panelr payment method mapped to a WooCommerce gateway; null when unmapped or unreadable. */
	public static function mapped_method(string $gateway_id): ?array
	{
		$id = self::mapped_method_id($gateway_id);
		if (!$id) return null;
		$methods = self::payment_methods();
		return $methods[$id] ?? null;
	}

	/**
	 * The fee or discount a Panelr method applies on a money total.
	 * @return array{direction:?string,label:?string,amount:?float,total:float}
	 */
	public static function method_adjustment(array $pm, float $total): array
	{
		$none = ['direction' => null, 'label' => null, 'amount' => null, 'total' => round($total, 2)];
		if (
			empty($pm['adjustment_enabled'])
			|| !in_array($pm['adjustment_direction'] ?? '', ['fee', 'discount'], true)
			|| empty($pm['adjustment_mode'])
			|| (float) ($pm['adjustment_value'] ?? 0) <= 0
			|| $total <= 0
		) {
			return $none;
		}

		$value  = (float) $pm['adjustment_value'];
		$amount = $pm['adjustment_mode'] === 'fixed' ? round($value, 2) : round($total * ($value / 100), 2);
		$dir    = $pm['adjustment_direction'];
		$name   = $pm['display_label'] ?: $pm['name'];
		$label  = $dir === 'fee'
			/* translators: %s: payment method name */
			? sprintf(__('%s fee', 'panelr-for-woocommerce'), $name)
			/* translators: %s: payment method name */
			: sprintf(__('%s discount', 'panelr-for-woocommerce'), $name);

		$new_total = $dir === 'fee' ? round($total + $amount, 2) : max(0.0, round($total - $amount, 2));

		return ['direction' => $dir, 'label' => $label, 'amount' => $amount, 'total' => $new_total];
	}

	// ── Products ──────────────────────────────────────────────────────────

	/** The WooCommerce product id synced from a Panelr product id, any status. */
	public static function wc_product_id_for(int $panelr_id, bool $published_only = false): int
	{
		if (!$panelr_id) return 0;
		$posts = get_posts([
			'post_type'      => 'product',
			'post_status'    => $published_only ? 'publish' : 'any',
			'posts_per_page' => 1,
			'meta_key'       => '_panelr_product_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- the join key between Panelr and WooCommerce
			'meta_value'     => $panelr_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'fields'         => 'ids',
			'no_found_rows'  => true,
		]);
		return $posts ? (int) $posts[0] : 0;
	}

	public static function panelr_product_id(int $wc_product_id): int
	{
		if (!$wc_product_id) return 0;
		$product = wc_get_product($wc_product_id);
		if (!$product) return 0;
		$id = (int) $product->get_meta('_panelr_product_id');
		if (!$id && $product->get_parent_id()) {
			$parent = wc_get_product($product->get_parent_id());
			$id     = $parent ? (int) $parent->get_meta('_panelr_product_id') : 0;
		}
		return $id;
	}

	/**
	 * Every synced product as a plain row, published ones only by default.
	 * @return array<int, array{wc_id:int,panelr_id:int,plugin_id:int,name:string,price:float,connections:int,duration_months:int,is_trial:bool,cost_points:int,earn_points:int,status:string}>
	 */
	public static function synced_products(bool $published_only = true): array
	{
		$posts = get_posts([
			'post_type'      => 'product',
			'post_status'    => $published_only ? 'publish' : 'any',
			'posts_per_page' => -1,
			'meta_key'       => '_panelr_product_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- the join key; a store has dozens of plans, not thousands
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
		]);

		$rows = [];
		foreach ($posts as $post_id) {
			$product = wc_get_product($post_id);
			if (!$product) continue;
			$rows[] = [
				'wc_id'           => (int) $post_id,
				'panelr_id'       => (int) $product->get_meta('_panelr_product_id'),
				'plugin_id'       => (int) $product->get_meta('_panelr_plugin_id'),
				'name'            => $product->get_name(),
				'panelr_name'     => (string) $product->get_meta('_panelr_synced_name'),
				'price'           => (float) $product->get_price(),
				'connections'     => (int) $product->get_meta('_panelr_connections'),
				'duration_months' => (int) $product->get_meta('_panelr_duration_months'),
				'is_trial'        => $product->get_meta('_panelr_is_trial') === '1',
				'cost_points'     => (int) $product->get_meta('_panelr_referral_cost_points'),
				'earn_points'     => (int) $product->get_meta('_panelr_referral_earn_points'),
				'status'          => $product->get_status(),
				'url'             => $product->get_permalink(),
			];
		}
		return $rows;
	}

	/** Plans a line may renew onto: same service, at least as many connections (any plan for a trial). */
	public static function renewal_products(int $plugin_id, int $connections, bool $is_trial): array
	{
		$out = [];
		foreach (self::synced_products(true) as $row) {
			if ($row['is_trial']) continue;
			if ($plugin_id && $row['plugin_id'] && $row['plugin_id'] !== $plugin_id) continue;
			if (!$is_trial && $connections && $row['connections'] < $connections) continue;
			$out[] = $row;
		}
		return $out;
	}

	public static function product_row_by_panelr_id(int $panelr_id): ?array
	{
		foreach (self::synced_products(false) as $row) {
			if ($row['panelr_id'] === $panelr_id) return $row;
		}
		return null;
	}

	// ── Pages ─────────────────────────────────────────────────────────────

	public static function page_url(string $key): string
	{
		$page_id = (int) get_option('panelr_page_' . $key, 0);
		if (!$page_id) return '';
		$url = get_permalink($page_id);
		return $url ?: '';
	}

	public static function portal_url(array $args = []): string
	{
		$url = self::page_url('portal') ?: wc_get_page_permalink('myaccount');
		return $args ? add_query_arg($args, $url) : $url;
	}

	// ── Dates ─────────────────────────────────────────────────────────────

	/** A UTC datetime from Panelr, shown in the site's timezone and format. */
	public static function format_date(?string $utc, bool $with_time = false): string
	{
		if (!$utc) return '—';
		try {
			$dt = new DateTime($utc, new DateTimeZone('UTC'));
		} catch (Exception $e) {
			return '—';
		}
		$dt->setTimezone(wp_timezone());
		$format = get_option('date_format');
		if ($with_time) $format .= ' ' . get_option('time_format');
		return wp_date($format, $dt->getTimestamp(), wp_timezone());
	}

	public static function days_left(?string $utc): ?int
	{
		if (!$utc) return null;
		try {
			$dt = new DateTime($utc, new DateTimeZone('UTC'));
		} catch (Exception $e) {
			return null;
		}
		$diff = $dt->getTimestamp() - time();
		return max(0, (int) floor($diff / DAY_IN_SECONDS));
	}

	// ── Wording ───────────────────────────────────────────────────────────

	public static function intent_label(string $intent): string
	{
		switch ($intent) {
			case 'renewal':         return __('Renewal', 'panelr-for-woocommerce');
			case 'trial_upgrade':   return __('Trial upgrade', 'panelr-for-woocommerce');
			case 'balance_payment': return __('Balance payment', 'panelr-for-woocommerce');
			default:                return __('New connection', 'panelr-for-woocommerce');
		}
	}

	public static function line_status_label(string $status): string
	{
		switch ($status) {
			case 'active':       return __('Active', 'panelr-for-woocommerce');
			case 'trial_active': return __('Trial', 'panelr-for-woocommerce');
			case 'expired':      return __('Expired', 'panelr-for-woocommerce');
			case 'suspended':    return __('Suspended', 'panelr-for-woocommerce');
			case 'canceled':     return __('Canceled', 'panelr-for-woocommerce');
			default:             return ucfirst(str_replace('_', ' ', $status));
		}
	}

	public static function order_status_label(string $status): string
	{
		switch ($status) {
			case 'pending':           return __('Waiting for review', 'panelr-for-woocommerce');
			case 'pending_payment':   return __('Waiting for payment', 'panelr-for-woocommerce');
			case 'payment_submitted': return __('Payment confirmation received', 'panelr-for-woocommerce');
			case 'awaiting_webhook':  return __('Being set up', 'panelr-for-woocommerce');
			case 'queued':            return __('Being set up', 'panelr-for-woocommerce');
			case 'completed':         return __('Ready', 'panelr-for-woocommerce');
			case 'canceled':
			case 'cancelled':         return __('Canceled', 'panelr-for-woocommerce');
			case 'payment_failed':    return __('Payment failed', 'panelr-for-woocommerce');
			default:                  return ucfirst(str_replace('_', ' ', $status));
		}
	}

	public static function plan_summary(int $connections, int $months): string
	{
		$parts = [];
		if ($connections) {
			/* translators: %d: number of connections */
			$parts[] = sprintf(_n('%d connection', '%d connections', $connections, 'panelr-for-woocommerce'), $connections);
		}
		if ($months) {
			/* translators: %d: number of months */
			$parts[] = sprintf(_n('%d month', '%d months', $months, 'panelr-for-woocommerce'), $months);
		}
		return implode(' · ', $parts);
	}

	/** "Living Room · Service A" or "username · Service A". */
	public static function line_display(array $line): string
	{
		$name = $line['label'] ?? $line['portal_label'] ?? '';
		if (!$name) $name = $line['username'] ?? ($line['editor_username'] ?? ($line['xtream_username'] ?? ''));
		$service = self::service_name((int) ($line['plugin_id'] ?? 0)) ?: ($line['service_name'] ?? $line['plugin_name'] ?? '');
		if ($service && self::multi_service()) {
			return $name . ' · ' . $service;
		}
		return (string) $name;
	}

	// ── Request details ───────────────────────────────────────────────────

	/** The visitor's address: REMOTE_ADDR unless the operator has said the site sits behind a proxy. */
	public static function client_ip(): string
	{
		$ip = '';
		if (get_option('panelr_proxy_enabled', '0') === '1') {
			$header = (string) get_option('panelr_proxy_header', 'HTTP_CF_CONNECTING_IP');
			$header = preg_replace('/[^A-Z0-9_]/', '', strtoupper($header));
			if ($header && !empty($_SERVER[$header])) {
				$raw = sanitize_text_field(wp_unslash($_SERVER[$header]));
				if (strpos($raw, ',') !== false) {
					$raw = trim(explode(',', $raw)[0]);
				}
				$ip = $raw;
			}
		}
		if (!$ip && !empty($_SERVER['REMOTE_ADDR'])) {
			$ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
		}
		return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
	}

	public static function user_agent(): string
	{
		return isset($_SERVER['HTTP_USER_AGENT']) ? substr(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])), 0, 500) : '';
	}

	/** Per-IP attempt counter in a transient. Returns false when over the budget. */
	public static function rate_limit(string $bucket, int $max, int $window): bool
	{
		$key   = 'panelr_rl_' . md5($bucket . '|' . self::client_ip());
		$count = (int) get_transient($key);
		if ($count >= $max) return false;
		set_transient($key, $count + 1, $window);
		return true;
	}

	// ── Turnstile ─────────────────────────────────────────────────────────

	public static function turnstile_enabled(): bool
	{
		return get_option('panelr_turnstile_site_key', '') !== '' && get_option('panelr_turnstile_secret_key', '') !== '';
	}

	public static function verify_turnstile(string $token): bool
	{
		if (!self::turnstile_enabled()) return true;
		if ($token === '') return false;

		// Turnstile's answer can only be checked with Cloudflare; no asset is served from there.
		// phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent
		$response = wp_remote_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
			'timeout' => 10,
			'body'    => [
				'secret'   => (string) get_option('panelr_turnstile_secret_key', ''),
				'response' => $token,
				'remoteip' => self::client_ip(),
			],
		]);
		if (is_wp_error($response)) return false;
		$json = json_decode(wp_remote_retrieve_body($response), true);
		return !empty($json['success']);
	}

	// ── Misc ──────────────────────────────────────────────────────────────

	public static function bool_option(string $name, string $default = '0'): bool
	{
		$v = get_option($name, $default);
		return in_array($v, ['1', 1, true, 'yes', 'true', 'on'], true);
	}

	/** wc_price without the wrapping span markup surprises, in the store currency. */
	public static function price(float $amount, ?string $currency = null): string
	{
		$args = [];
		if ($currency) $args['currency'] = strtoupper($currency);
		return wc_price($amount, $args);
	}

	public static function admin_panelr_url(string $path = ''): string
	{
		$base = Panelr_API::instance()->base_url();
		return $base ? $base . '/admin/' . ltrim($path, '/') : '';
	}
}
