<?php
defined('ABSPATH') || exit;

/**
 * Who the visitor is, as Panelr knows them.
 *
 * A signed-in member is a Panelr account: the WooCommerce session holds the
 * customer id, email, the account snapshot (never credentials) and a refresh
 * stamp. Line credentials open a limited session for a line whose owner has
 * no login yet. Nothing here is ever written to the database except the
 * optional link between a WordPress user and their Panelr account.
 */
class Panelr_Session
{
	const KEY_MEMBER = 'panelr_member';
	const KEY_LINE   = 'panelr_line';
	const KEY_REF    = 'panelr_ref';
	const REF_COOKIE = 'panelr_ref';
	const REFRESH_AFTER = 5 * MINUTE_IN_SECONDS;

	private static ?array $member_cache = null;

	public static function init(): void
	{
		add_action('wp', [__CLASS__, 'capture_referral_code']);
		add_action('woocommerce_init', [__CLASS__, 'maybe_sign_in_wp_user'], 20);
		add_action('template_redirect', [__CLASS__, 'never_cache'], 1);
	}

	/**
	 * Keep page caches (Breeze, Varnish, and the rest) away from anything that
	 * depends on a Panelr session. A member is a WordPress guest as far as a
	 * cache is concerned, so without this the signed-out copy of the member
	 * page is handed back after a successful sign-in and the form "resets".
	 */
	public static function never_cache(): void
	{
		if (is_admin()) return;

		$sensitive = self::is_signed_in()
			|| self::get(self::KEY_LINE) !== null
			|| self::is_panelr_page()
			|| isset($_GET['panelr_ref'], $_GET['panelr_reset'], $_GET['panelr_verify'], $_GET['panelr_t']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- presence only

		if (!$sensitive) return;

		// These two names are what caching plugins look for; they cannot carry our prefix.
		if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
		if (!defined('DONOTCACHEOBJECT')) define('DONOTCACHEOBJECT', true); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
		if (!headers_sent()) nocache_headers();
	}

	/** The pages Panelr owns: the ones picked under Panelr → Pages, plus any page carrying a Panelr shortcode. */
	public static function is_panelr_page(): bool
	{
		if (!is_singular()) return false;

		$post = get_post();
		if (!$post) return false;

		foreach (['portal', 'trial', 'upgrade', 'order_status', 'support', 'apps', 'checkout'] as $key) {
			if ((int) get_option('panelr_page_' . $key, 0) === (int) $post->ID) return true;
		}

		return has_shortcode((string) $post->post_content, 'panelr_portal')
			|| has_shortcode((string) $post->post_content, 'panelr_checkout')
			|| has_shortcode((string) $post->post_content, 'panelr_order_status')
			|| has_shortcode((string) $post->post_content, 'panelr_support')
			|| has_shortcode((string) $post->post_content, 'panelr_trial')
			|| has_shortcode((string) $post->post_content, 'panelr_upgrade')
			|| has_shortcode((string) $post->post_content, 'panelr_apps');
	}

	// ── Raw session access ────────────────────────────────────────────────

	public static function get(string $key)
	{
		if (!function_exists('WC') || !WC()->session) return null;
		return WC()->session->get($key);
	}

	public static function set(string $key, $value): void
	{
		if (!function_exists('WC') || !WC()->session) return;
		if (!WC()->session->has_session()) {
			WC()->session->set_customer_session_cookie(true);
		}
		WC()->session->set($key, $value);
	}

	public static function forget(string $key): void
	{
		if (!function_exists('WC') || !WC()->session) return;
		WC()->session->set($key, null);
	}

	// ── Member (account) session ──────────────────────────────────────────

	public static function is_signed_in(): bool
	{
		return self::member() !== null;
	}

	/** The member record, or null. Applies the session age limit. */
	public static function member(): ?array
	{
		if (self::$member_cache !== null) {
			return self::$member_cache ?: null;
		}
		$m = self::get(self::KEY_MEMBER);
		if (!is_array($m) || empty($m['customer_id'])) {
			self::$member_cache = [];
			return null;
		}
		$days = max(1, (int) get_option('panelr_session_days', 7));
		if (!empty($m['signed_in_at']) && time() - (int) $m['signed_in_at'] > $days * DAY_IN_SECONDS) {
			self::sign_out();
			return null;
		}
		self::$member_cache = $m;
		return $m;
	}

	public static function customer_id(): int
	{
		return (int) (self::member()['customer_id'] ?? 0);
	}

	public static function email(): string
	{
		return (string) (self::member()['email'] ?? '');
	}

	public static function name(): string
	{
		return (string) (self::member()['name'] ?? '');
	}

	/** Sign in from a snapshot Panelr returned (verify_customer_login, register, reset, get_customer). */
	public static function sign_in(array $snapshot): void
	{
		$clean = self::clean_snapshot($snapshot);
		$m = [
			'customer_id'  => (int) $clean['customer_id'],
			'email'        => (string) $clean['email'],
			'name'         => (string) ($clean['customer_name'] ?? ''),
			'snapshot'     => $clean,
			'refreshed_at' => time(),
			'signed_in_at' => time(),
		];
		self::$member_cache = $m;
		self::set(self::KEY_MEMBER, $m);
		self::forget(self::KEY_LINE);
		self::link_wp_user((int) $clean['customer_id'], (string) $clean['email']);
	}

	public static function sign_out(): void
	{
		self::$member_cache = [];
		self::forget(self::KEY_MEMBER);
		self::forget(self::KEY_LINE);
		self::forget('panelr_upgrade');
		self::forget('panelr_coupon');
		if (is_user_logged_in()) {
			delete_user_meta(get_current_user_id(), '_panelr_customer_id');
		}
	}

	/**
	 * The account snapshot: lines, referral standing, support counts.
	 * Refreshed from get_customer when older than five minutes, or when asked.
	 */
	public static function snapshot(bool $force = false): array
	{
		$m = self::member();
		if (!$m) return [];

		$stale = $force || (time() - (int) ($m['refreshed_at'] ?? 0)) > self::REFRESH_AFTER;
		if ($stale) {
			$result = Panelr_API::instance()->get_customer((int) $m['customer_id']);
			if ($result['ok'] && is_array($result['data'])) {
				$clean = self::clean_snapshot($result['data']);
				$m['snapshot']     = $clean;
				$m['email']        = (string) $clean['email'];
				$m['name']         = (string) ($clean['customer_name'] ?? '');
				$m['refreshed_at'] = time();
				self::$member_cache = $m;
				self::set(self::KEY_MEMBER, $m);
			} elseif ($result['status'] === 404) {
				// The login is gone on Panelr's side.
				self::sign_out();
				return [];
			}
		}
		return (array) ($m['snapshot'] ?? []);
	}

	/** Replace the snapshot after a change Panelr already answered with a fresh one. */
	public static function update_snapshot(array $snapshot): void
	{
		$m = self::member();
		if (!$m) return;
		$clean             = self::clean_snapshot($snapshot);
		$m['snapshot']     = $clean;
		$m['email']        = (string) ($clean['email'] ?? $m['email']);
		$m['name']         = (string) ($clean['customer_name'] ?? $m['name']);
		$m['refreshed_at'] = time();
		self::$member_cache = $m;
		self::set(self::KEY_MEMBER, $m);
	}

	/** Lines from the snapshot, keyed by activation id. */
	public static function lines(bool $force = false): array
	{
		$out = [];
		foreach ((array) (self::snapshot($force)['lines'] ?? []) as $line) {
			$out[(int) $line['activation_id']] = $line;
		}
		return $out;
	}

	public static function line(int $activation_id): ?array
	{
		return self::lines()[$activation_id] ?? null;
	}

	public static function owns_line(int $activation_id): bool
	{
		if (self::line($activation_id)) return true;
		$line = self::line_session();
		return $line && (int) $line['activation_id'] === $activation_id;
	}

	public static function credits_balance(): int
	{
		return (int) (self::snapshot()['referral']['balance'] ?? 0);
	}

	public static function invite_code(): string
	{
		return (string) (self::snapshot()['referral']['code'] ?? '');
	}

	/** Keep only what the member area shows — never a password or a stream credential. */
	private static function clean_snapshot(array $s): array
	{
		$allowed = ['customer_id', 'email', 'customer_name', 'has_login', 'verified', 'contact_preference', 'chats', 'suspended', 'created_at', 'last_login_at', 'referral', 'support', 'created', 'verification_sent'];
		$out = [];
		foreach ($allowed as $k) {
			if (array_key_exists($k, $s)) $out[$k] = $s[$k];
		}
		$out['lines'] = [];
		foreach ((array) ($s['lines'] ?? []) as $line) {
			$out['lines'][] = [
				'activation_id'   => (int) ($line['activation_id'] ?? 0),
				'username'        => (string) ($line['username'] ?? ''),
				'label'           => $line['label'] ?? null,
				'status'          => (string) ($line['status'] ?? ''),
				'expiration_date' => $line['expiration_date'] ?? null,
				'activated_at'    => $line['activated_at'] ?? null,
				'expired'         => !empty($line['expired']),
				'days_left'       => $line['days_left'] ?? null,
				'is_trial'        => !empty($line['is_trial']),
				'product_id'      => $line['product_id'] ?? null,
				'product_name'    => $line['product_name'] ?? null,
				'connections'     => $line['connections'] ?? null,
				'duration_months' => $line['duration_months'] ?? null,
				'connection_type' => $line['connection_type'] ?? null,
				'plugin_id'       => $line['plugin_id'] ?? null,
				'service_name'    => $line['service_name'] ?? null,
			];
		}
		return $out;
	}

	// ── Line-credential session (no account yet) ──────────────────────────

	public static function line_session(): ?array
	{
		$l = self::get(self::KEY_LINE);
		if (!is_array($l) || empty($l['activation_id'])) return null;
		$days = max(1, (int) get_option('panelr_session_days', 7));
		if (!empty($l['signed_in_at']) && time() - (int) $l['signed_in_at'] > $days * DAY_IN_SECONDS) {
			self::forget(self::KEY_LINE);
			return null;
		}
		return $l;
	}

	/** Open a limited session from a verify_login answer. Credentials are dropped on the floor. */
	public static function sign_in_line(array $data): void
	{
		self::set(self::KEY_LINE, [
			'activation_id'    => (int) $data['activation_id'],
			'username'         => (string) ($data['username'] ?? ''),
			'plugin_id'        => (int) ($data['plugin_id'] ?? 0),
			'service_name'     => (string) ($data['plugin_name'] ?? ''),
			'email'            => (string) ($data['customer_email'] ?? ''),
			'name'             => (string) ($data['customer_name'] ?? ''),
			'status'           => (string) ($data['status'] ?? ''),
			'expiration_date'  => $data['expiration_date'] ?? null,
			'product_name'     => (string) ($data['product']['name'] ?? ''),
			'connections'      => (int) ($data['product']['connections'] ?? 0),
			'is_trial'         => ($data['status'] ?? '') === 'trial_active',
			'account_required' => !empty($data['referral']['account_required']),
			'signed_in_at'     => time(),
		]);
		self::forget(self::KEY_MEMBER);
		self::$member_cache = [];
	}

	// ── Referral code capture ─────────────────────────────────────────────

	/** ?ref=CODE on any page → checked with Panelr, kept 30 days. A dead code is dropped silently. */
	public static function capture_referral_code(): void
	{
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- an invite link on any page; it is checked with Panelr and only remembered
		if (empty($_GET['ref']) || is_admin()) return;
		$code = strtolower(sanitize_text_field(wp_unslash($_GET['ref'])));
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$code = preg_replace('/[^a-z0-9_\-]/', '', $code);
		if ($code === '' || strlen($code) > 60) return;
		if (self::referral_code() === $code) return;

		// One check per code per hour, and a per-visitor budget, so a link
		// storm cannot spend the store's Panelr rate limit.
		$cache_key = 'panelr_refcode_' . md5($code);
		$valid     = get_transient($cache_key);
		if ($valid === false) {
			if (!Panelr_Helpers::rate_limit('ref', 5, 15 * MINUTE_IN_SECONDS)) return;
			$result = Panelr_API::instance()->validate_referral_code($code);
			if (!$result['ok']) return;
			$valid = !empty($result['data']['valid']) ? '1' : '0';
			set_transient($cache_key, $valid, HOUR_IN_SECONDS);
		}
		if ($valid !== '1') return;

		$value = ['code' => $code, 'at' => time()];
		self::set(self::KEY_REF, $value);
		if (!headers_sent()) {
			setcookie(self::REF_COOKIE, $code, [
				'expires'  => time() + 30 * DAY_IN_SECONDS,
				'path'     => COOKIEPATH ?: '/',
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			]);
		}
		$_COOKIE[self::REF_COOKIE] = $code;
	}

	public static function referral_code(): string
	{
		$s = self::get(self::KEY_REF);
		if (is_array($s) && !empty($s['code']) && time() - (int) $s['at'] < 30 * DAY_IN_SECONDS) {
			return (string) $s['code'];
		}
		if (!empty($_COOKIE[self::REF_COOKIE])) {
			$code = preg_replace('/[^a-z0-9_\-]/', '', strtolower(sanitize_text_field(wp_unslash($_COOKIE[self::REF_COOKIE]))));
			return $code;
		}
		return '';
	}

	public static function clear_referral_code(): void
	{
		self::forget(self::KEY_REF);
		if (!headers_sent()) {
			setcookie(self::REF_COOKIE, '', time() - HOUR_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN);
		}
		unset($_COOKIE[self::REF_COOKIE]);
	}

	// ── WordPress user link (optional) ────────────────────────────────────

	private static function link_wp_user(int $customer_id, string $email): void
	{
		if (!Panelr_Helpers::bool_option('panelr_wp_user_link', '0')) return;
		if (!is_user_logged_in()) return;
		$user = wp_get_current_user();
		if ($user && strtolower($user->user_email) === strtolower($email)) {
			update_user_meta($user->ID, '_panelr_customer_id', $customer_id);
		}
	}

	/** A logged-in WordPress user already linked to an account opens the member area without a second sign-in. */
	public static function maybe_sign_in_wp_user(): void
	{
		if (is_admin() || !is_user_logged_in()) return;
		if (!Panelr_Helpers::bool_option('panelr_wp_user_link', '0')) return;
		if (self::is_signed_in()) return;
		$customer_id = (int) get_user_meta(get_current_user_id(), '_panelr_customer_id', true);
		if (!$customer_id) return;
		$result = Panelr_API::instance()->get_customer($customer_id);
		if ($result['ok'] && is_array($result['data']) && strtolower((string) $result['data']['email']) === strtolower(wp_get_current_user()->user_email)) {
			self::sign_in($result['data']);
		} elseif ($result['status'] === 404) {
			delete_user_meta(get_current_user_id(), '_panelr_customer_id');
		}
	}

	// ── Nonces for ajax from the member area ──────────────────────────────

	public static function nonce(): string
	{
		return wp_create_nonce('panelr_portal_nonce');
	}
}
