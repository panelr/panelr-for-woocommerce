<?php
defined('ABSPATH') || exit;

/**
 * [panelr_portal] — the member area. Sign in with the account (email and
 * password); connection details open a limited view and offer "Set up your
 * account". Tabs: Connections, Orders, Credits, Support, Apps, Account.
 */
class Panelr_Portal
{
	public static function init(): void
	{
		add_shortcode('panelr_portal', [__CLASS__, 'render']);

		$ajax = [
			'panelr_portal_login'            => 'ajax_login',
			'panelr_portal_line_login'       => 'ajax_line_login',
			'panelr_portal_logout'           => 'ajax_logout',
			'panelr_portal_register'         => 'ajax_register',
			'panelr_portal_setup_account'    => 'ajax_setup_account',
			'panelr_portal_forgot'           => 'ajax_forgot',
			'panelr_portal_reset'            => 'ajax_reset',
			'panelr_portal_resend_verify'    => 'ajax_resend_verify',
			'panelr_portal_rename_line'      => 'ajax_rename_line',
			'panelr_portal_line_details'     => 'ajax_line_details',
			'panelr_portal_bouquets'         => 'ajax_bouquets',
			'panelr_portal_update_bouquets'  => 'ajax_update_bouquets',
			'panelr_portal_add_renewal'      => 'ajax_add_renewal',
			'panelr_portal_update_account'   => 'ajax_update_account',
			'panelr_portal_update_customer'  => 'ajax_update_account',
			'panelr_portal_change_password'  => 'ajax_change_password',
			'panelr_portal_contact_pref'     => 'ajax_contact_pref',
			'panelr_portal_chat_link'        => 'ajax_chat_link',
			'panelr_portal_chat_unlink'      => 'ajax_chat_unlink',
			'panelr_portal_refresh'          => 'ajax_refresh',
		];
		foreach ($ajax as $action => $method) {
			add_action('wp_ajax_' . $action, [__CLASS__, $method]);
			add_action('wp_ajax_nopriv_' . $action, [__CLASS__, $method]);
		}
	}

	// ── Helpers ───────────────────────────────────────────────────────────

	private static function check(): void
	{
		check_ajax_referer('panelr_portal_nonce', 'nonce');
	}

	private static function require_member(): void
	{
		if (!Panelr_Session::is_signed_in()) {
			wp_send_json_error(['message' => __('Please sign in again.', 'panelr-for-woocommerce'), 'signed_out' => true]);
		}
	}

	// Every caller of these two has passed check_ajax_referer() first.
	// phpcs:disable WordPress.Security.NonceVerification.Missing
	private static function post(string $key): string
	{
		return isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
	}

	/** Passwords go through unchanged; only slashes are removed. Sanitizing a password would change it. */
	private static function post_password(string $key): string
	{
		return isset($_POST[$key]) ? (string) wp_unslash($_POST[$key]) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}
	// phpcs:enable WordPress.Security.NonceVerification.Missing

	/** A same-site path the sign-in returns to, or ''. */
	private static function safe_return(string $url): string
	{
		$url = rawurldecode($url);
		if ($url === '') return '';
		return (string) wp_validate_redirect($url, '');
	}

	public static function enqueue(): void
	{
		wp_enqueue_script('panelr-common');
		wp_enqueue_script('panelr-portal', PANELR_PLUGIN_URL . 'assets/js/portal.js', ['jquery', 'panelr-common'], PANELR_VERSION, true);
		wp_localize_script('panelr-portal', 'panelrPortal', [
			'ajaxurl'      => admin_url('admin-ajax.php'),
			'nonce'        => Panelr_Session::nonce(),
			'portal_url'   => Panelr_Helpers::portal_url(),
			'checkout_url' => wc_get_checkout_url(),
			'return'       => self::safe_return(sanitize_text_field(wp_unslash($_GET['return'] ?? ''))), // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- where to go after sign-in; validated
			'i18n'         => [
				'need_fields'    => __('Fill in every field.', 'panelr-for-woocommerce'),
				'need_email'     => __('Enter your email address.', 'panelr-for-woocommerce'),
				'password_short' => __('Use at least 8 characters.', 'panelr-for-woocommerce'),
				'password_match' => __('The two passwords do not match.', 'panelr-for-woocommerce'),
				'signing_in'     => __('Signing in…', 'panelr-for-woocommerce'),
				'sign_in'        => __('Sign in', 'panelr-for-woocommerce'),
				'saving'         => __('Saving…', 'panelr-for-woocommerce'),
				'save'           => __('Save', 'panelr-for-woocommerce'),
				'saved'          => __('Saved', 'panelr-for-woocommerce'),
				'loading'        => __('Loading…', 'panelr-for-woocommerce'),
				'request_failed' => __('Something went wrong. Please try again.', 'panelr-for-woocommerce'),
				'host'           => __('Host', 'panelr-for-woocommerce'),
				'username'       => __('Username', 'panelr-for-woocommerce'),
				'password'       => __('Password', 'panelr-for-woocommerce'),
				'm3u'            => __('M3U link', 'panelr-for-woocommerce'),
				'epg'            => __('EPG link', 'panelr-for-woocommerce'),
				'mac'            => __('MAC address', 'panelr-for-woocommerce'),
				'copy'           => __('Copy', 'panelr-for-woocommerce'),
				'show'           => __('Show', 'panelr-for-woocommerce'),
				'hide'           => __('Hide', 'panelr-for-woocommerce'),
				'details_none'   => __('No connection details yet. Please try again shortly.', 'panelr-for-woocommerce'),
				'reset_sent'     => __('If that address has an account, a link to choose a new password is on its way.', 'panelr-for-woocommerce'),
				'chat_linked'    => __('Linked', 'panelr-for-woocommerce'),
				'chat_not_linked' => __('Not linked', 'panelr-for-woocommerce'),
				'chat_blocked'   => __('Blocked. Unlink and link again', 'panelr-for-woocommerce'),
				'chat_link'      => __('Link', 'panelr-for-woocommerce'),
				'chat_unlink'    => __('Unlink', 'panelr-for-woocommerce'),
				'chat_linked_now' => __('Linked. You can be reached there now.', 'panelr-for-woocommerce'),
			],
		]);
	}

	// ── Render ────────────────────────────────────────────────────────────

	public static function render($atts = []): string
	{
		if (WC()->session && !WC()->session->has_session()) {
			WC()->session->set_customer_session_cookie(true);
		}
		self::enqueue();

		if (!Panelr_API::instance()->is_configured()) {
			return Panelr_Template::notice(__('The member area is not connected yet.', 'panelr-for-woocommerce'), 'info');
		}

		// Password reset link.
		// The links below come from Panelr's emails; they open a form or spend a token that is itself the credential.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$reset_token = sanitize_text_field(wp_unslash($_GET['panelr_reset'] ?? ''));
		if ($reset_token !== '') {
			return Panelr_Template::render('portal/reset', ['token' => $reset_token]);
		}

		// Confirm-your-email link.
		$verify_token = sanitize_text_field(wp_unslash($_GET['panelr_verify'] ?? ''));
		$verify_notice = '';
		if ($verify_token !== '') {
			$r = Panelr_API::instance()->confirm_customer_email($verify_token);
			$verify_notice = $r['ok']
				? Panelr_Template::notice(__('Your email address is confirmed.', 'panelr-for-woocommerce'), 'success')
				: Panelr_Template::notice(__('That confirmation link has already been used or has expired.', 'panelr-for-woocommerce'));
			if ($r['ok'] && Panelr_Session::is_signed_in()) {
				Panelr_Session::snapshot(true);
			}
		}

		// Panelr's trial link (?panelr_t=<code> or ?t=<token>): the code is the
		// door to that one line, so it opens the limited view with Upgrade on it.
		$trial_notice = self::maybe_open_trial_link();

		if (Panelr_Session::is_signed_in()) {
			return $verify_notice . $trial_notice . self::render_dashboard();
		}

		$line = Panelr_Session::line_session();
		if ($line) {
			return $verify_notice . self::render_line_only($line);
		}

		return $verify_notice . self::render_sign_in();
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/** Resolve a trial link into a line session when the visitor holds nothing yet. */
	private static function maybe_open_trial_link(): string
	{
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Panelr's trial link; the code is the credential and is verified with Panelr
		$code  = strtoupper(sanitize_text_field(wp_unslash($_GET['panelr_t'] ?? '')));
		$token = sanitize_text_field(wp_unslash($_GET['t'] ?? ''));
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ($code === '' && strlen($token) < 32) return '';
		if (!Panelr_Helpers::rate_limit('trial_verify', 5, 15 * MINUTE_IN_SECONDS)) {
			return Panelr_Template::notice(__('Too many tries. Please wait a few minutes.', 'panelr-for-woocommerce'));
		}
		$result = Panelr_API::instance()->verify_trial($code !== '' ? ['trial_code' => $code] : ['activation_token' => $token]);
		if (!$result['ok']) {
			return Panelr_Template::notice(__('We could not find a trial with that link. Sign in to see your connections.', 'panelr-for-woocommerce'));
		}
		$d = $result['data'];
		$activation_id = (int) ($d['activation_id'] ?? 0);
		if (Panelr_Session::is_signed_in()) {
			return Panelr_Session::line($activation_id)
				? ''
				: Panelr_Template::notice(__('That trial belongs to a different account.', 'panelr-for-woocommerce'), 'info');
		}
		$existing = Panelr_Session::line_session();
		if ($existing && (int) $existing['activation_id'] === $activation_id) return '';
		Panelr_Session::sign_in_line([
			'activation_id'   => $activation_id,
			'username'        => (string) ($d['customer_email'] ?? ''),
			'plugin_id'       => (int) ($d['plugin_id'] ?? 0),
			'plugin_name'     => (string) ($d['plugin_name'] ?? ''),
			'customer_email'  => (string) ($d['customer_email'] ?? ''),
			'customer_name'   => (string) ($d['customer_name'] ?? ''),
			'status'          => 'trial_active',
			'expiration_date' => $d['expiration_date'] ?? null,
			'product'         => ['name' => __('Free trial', 'panelr-for-woocommerce'), 'connections' => (int) ($d['connections'] ?? 0)],
			'referral'        => ['account_required' => true],
		]);
		return '';
	}

	private static function render_sign_in(): string
	{
		$store = Panelr_Helpers::store_settings();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which form to open; read only
		$for_checkout = sanitize_key(wp_unslash($_GET['view'] ?? '')) === 'checkout';
		return Panelr_Template::render('portal/sign-in', [
			// Accounts are created when an order is placed, never from the member
			// page on its own: the create-account form only opens for checkout.
			'accounts'       => $for_checkout && (!isset($store['features']['accounts']) || !empty($store['features']['accounts'])),
			'signup_points'  => Panelr_Helpers::referral_enabled() ? Panelr_Helpers::signup_points() : 0,
			'require_invite' => self::registration_needs_invite(),
			'invite_code'    => Panelr_Session::referral_code(),
			'show_line'      => true,
			'services'       => Panelr_Helpers::services(),
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- which form to open, and where to return; both read only
			'return'         => self::safe_return(sanitize_text_field(wp_unslash($_GET['return'] ?? ''))),
			'view'           => sanitize_key(wp_unslash($_GET['view'] ?? '')) === 'checkout' ? 'register' : sanitize_key(wp_unslash($_GET['view'] ?? '')),
			'for_checkout'   => sanitize_key(wp_unslash($_GET['view'] ?? '')) === 'checkout',
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
		]);
	}

	/**
	 * Checkout's account step: one email and one password. A new email gets
	 * its login (the invite code attaches here); an existing one must match
	 * its password. Returns ['ok' => bool, 'error' => string].
	 */
	public static function account_for_checkout(string $email, string $password, string $name, string $invite): array
	{
		$api = Panelr_API::instance();
		if ($invite === '') $invite = Panelr_Session::referral_code();

		if (self::registration_needs_invite()) {
			// Only a brand-new login needs the invite; an existing one just signs in.
			$existing = $api->get_customer_by_email($email);
			$has_login = $existing['ok'] && !empty($existing['data']['has_login']);
			if (!$has_login) {
				$check = $invite !== '' ? $api->validate_referral_code($invite) : ['ok' => false, 'data' => null];
				if (!$check['ok'] || empty($check['data']['valid'])) {
					return ['ok' => false, 'error' => __('An invite code from a member is needed to create an account.', 'panelr-for-woocommerce')];
				}
			}
		}

		$body = [
			'customer_email' => $email,
			'customer_name'  => $name,
			'password'       => $password,
			'verify_url'     => Panelr_Helpers::portal_url(['panelr_verify' => '{token}']),
		];
		if ($invite !== '') $body['referral_code'] = $invite;

		$result = $api->register_customer($body);
		if ($result['ok']) {
			// A brand-new login, or the existing login's own password: signed in.
			Panelr_Session::sign_in($result['data']);
			return ['ok' => true, 'created' => !empty($result['data']['created'])];
		}

		// 409: the address is already on file. Panelr says nothing about the
		// account; a member record with no password gets a link by email.
		if ($result['status'] === 409) {
			if (!empty($result['data']['claim_link_sent'])) {
				return ['ok' => false, 'error' => sprintf(
					/* translators: %s: email */
					__('%s is already on file with us. We have emailed you a link to choose a password — finish that first, then come back and sign in.', 'panelr-for-woocommerce'),
					$email
				)];
			}
			return ['ok' => false, 'error' => sprintf(
				/* translators: %s: email */
				__('There is already an account for %s and that password does not match it. Use that account\'s password, or reset it from the member area.', 'panelr-for-woocommerce'),
				$email
			)];
		}
		return ['ok' => false, 'error' => $result['error']];
	}

	public static function registration_needs_invite(): bool
	{
		$setting = get_option('panelr_require_invite', 'auto');
		if ($setting === 'on') return true;
		if ($setting === 'off') return false;
		return Panelr_Helpers::require_invite();
	}

	private static function render_line_only(array $line): string
	{
		return Panelr_Template::render('portal/line-only', [
			'line'          => $line,
			'plans'         => Panelr_Helpers::renewal_products((int) $line['plugin_id'], (int) $line['connections'], !empty($line['is_trial'])),
			'multi'         => Panelr_Helpers::multi_service(),
			'signup_points' => Panelr_Helpers::referral_enabled() ? Panelr_Helpers::signup_points() : 0,
		]);
	}

	private static function render_dashboard(): string
	{
		$snapshot = Panelr_Session::snapshot();
		if (!$snapshot) {
			return self::render_sign_in();
		}

		$tab  = sanitize_key(wp_unslash($_GET['tab'] ?? 'connections')); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which tab to show
		$tabs = self::tabs($snapshot);
		if (!isset($tabs[$tab])) $tab = 'connections';

		$content = '';
		switch ($tab) {
			case 'orders':
				$content = self::render_orders($snapshot);
				break;
			case 'credits':
				$content = self::render_credits($snapshot);
				break;
			case 'support':
				$content = Panelr_Support::render_for_member($snapshot, Panelr_Helpers::portal_url(['tab' => 'support']));
				break;
			case 'apps':
				$content = Panelr_Apps::render_page(true);
				break;
			case 'account':
				$content = self::render_account($snapshot);
				break;
			default:
				$content = self::render_connections($snapshot);
		}

		return Panelr_Template::render('portal/dashboard', [
			'snapshot' => $snapshot,
			'tabs'     => $tabs,
			'tab'      => $tab,
			'content'  => $content,
			'name'     => (string) ($snapshot['customer_name'] ?? ''),
			'email'    => (string) ($snapshot['email'] ?? ''),
			'verified' => !empty($snapshot['verified']),
			'portal_url' => Panelr_Helpers::portal_url(),
		]);
	}

	private static function tabs(array $snapshot): array
	{
		$tabs = ['connections' => __('Connections', 'panelr-for-woocommerce'), 'orders' => __('Orders', 'panelr-for-woocommerce')];
		if (!empty($snapshot['referral']['enabled'])) {
			$tabs['credits'] = __('Credits', 'panelr-for-woocommerce');
		}
		if (Panelr_Support::on_site() && !empty($snapshot['support']['enabled'])) {
			$open = (int) ($snapshot['support']['open_tickets'] ?? 0);
			$tabs['support'] = $open
				/* translators: %d: open ticket count */
				? sprintf(__('Support (%d)', 'panelr-for-woocommerce'), $open)
				: __('Support', 'panelr-for-woocommerce');
		}
		if (Panelr_Helpers::apps_page_mode() !== 'off') {
			$tabs['apps'] = __('Apps', 'panelr-for-woocommerce');
		}
		$tabs['account'] = __('Account', 'panelr-for-woocommerce');
		return $tabs;
	}

	// ── Tab: Connections ──────────────────────────────────────────────────

	private static function render_connections(array $snapshot): string
	{
		$groups = [];
		foreach ((array) ($snapshot['lines'] ?? []) as $line) {
			$key = (int) ($line['plugin_id'] ?? 0);
			$line['plans'] = Panelr_Helpers::renewal_products($key, (int) ($line['connections'] ?? 0), !empty($line['is_trial']));
			$line['service_name'] = Panelr_Helpers::service_name($key) ?: $line['service_name'];
			$line['bouquets_on'] = self::bouquets_allowed($key);
			$groups[$key]['name']    = $line['service_name'];
			$groups[$key]['lines'][] = $line;
		}
		return Panelr_Template::render('portal/tab-connections', [
			'groups'   => $groups,
			'multi'    => Panelr_Helpers::multi_service(),
			'shop_url' => wc_get_page_permalink('shop'),
		]);
	}

	private static function bouquets_allowed(int $plugin_id): bool
	{
		if (!Panelr_Helpers::bool_option('panelr_portal_allow_bouquets', '0')) return false;
		$svc = Panelr_Helpers::service($plugin_id);
		if (!$svc) return false;
		return !empty($svc['bouquets_enabled']) && in_array($svc['bouquet_mode'] ?? 'none', ['editor', 'panel'], true);
	}

	// ── Tab: Orders ───────────────────────────────────────────────────────

	private static function render_orders(array $snapshot): string
	{
		$result = Panelr_API::instance()->get_work_orders((int) $snapshot['customer_id']);
		$orders = $result['ok'] && is_array($result['data']) ? $result['data'] : [];
		return Panelr_Template::render('portal/tab-orders', [
			'orders'      => $orders,
			'error'       => $result['ok'] ? '' : $result['error'],
			'status_url'  => Panelr_Helpers::page_url('order_status'),
			'multi'       => Panelr_Helpers::multi_service(),
		]);
	}

	// ── Tab: Credits ──────────────────────────────────────────────────────

	private static function render_credits(array $snapshot): string
	{
		$result = Panelr_API::instance()->get_referral_customer((int) $snapshot['customer_id']);
		$data   = $result['ok'] && is_array($result['data']) ? $result['data'] : [];
		$code   = (string) ($snapshot['referral']['code'] ?? '');
		if (!$code && !empty($data['codes'])) {
			foreach ($data['codes'] as $c) {
				if (empty($c['disabled_at'])) { $code = (string) $c['code']; break; }
			}
		}
		$link = $code ? add_query_arg('ref', $code, home_url('/')) : '';
		return Panelr_Template::render('portal/tab-credits', [
			'balance'     => (int) ($data['balance'] ?? ($snapshot['referral']['balance'] ?? 0)),
			'code'        => $code,
			'link'        => $link,
			'invited'     => (array) ($data['invited'] ?? []),
			'ledger'      => (array) ($data['ledger'] ?? []),
			'referred_by' => $data['referred_by'] ?? null,
			'error'       => $result['ok'] ? '' : $result['error'],
		]);
	}

	// ── Tab: Account ──────────────────────────────────────────────────────

	private static function render_account(array $snapshot): string
	{
		// A chat linked from inside the bot shows up here without a wait.
		$snapshot = Panelr_Session::snapshot(true) ?: $snapshot;
		return Panelr_Template::render('portal/tab-account', [
			'snapshot'   => $snapshot,
			'name'       => (string) ($snapshot['customer_name'] ?? ''),
			'email'      => (string) ($snapshot['email'] ?? ''),
			'verified'   => !empty($snapshot['verified']),
			'preference' => (string) ($snapshot['contact_preference'] ?? 'email'),
			'chats'      => self::chats($snapshot),
		]);
	}

	/** The chats the store offers, with this login's standing on each: platform => {label, linked, blocked}. */
	private static function chats(array $snapshot): array
	{
		$labels = ['telegram' => 'Telegram', 'discord' => 'Discord'];
		$out = [];
		foreach ((array) ($snapshot['chats'] ?? []) as $platform => $c) {
			if (!isset($labels[$platform]) || empty($c['offered'])) continue;
			$out[$platform] = ['label' => $labels[$platform], 'linked' => !empty($c['linked']), 'blocked' => !empty($c['blocked'])];
		}
		return $out;
	}

	/** Start (or cancel) linking a chat: a one-time code for the bot, with the ways to hand it over. */
	public static function ajax_chat_link(): void
	{
		self::check();
		self::require_member();
		$platform = self::post('platform');
		$mode     = self::post('mode') === 'cancel' ? 'cancel' : 'start';
		$api      = Panelr_API::instance();

		if ($mode === 'cancel') {
			$api->cancel_chat_link(Panelr_Session::customer_id());
			wp_send_json_success(['message' => __('Cancelled.', 'panelr-for-woocommerce')]);
		}
		if (!in_array($platform, ['telegram', 'discord'], true)) {
			wp_send_json_error(['message' => __('Choose Telegram or Discord.', 'panelr-for-woocommerce')]);
		}
		$result = $api->start_chat_link(Panelr_Session::customer_id(), $platform);
		if (!$result['ok']) {
			wp_send_json_error(['message' => $result['error']]);
		}
		$d = $result['data'];
		wp_send_json_success([
			'platform'           => $platform,
			'code'               => (string) ($d['code'] ?? ''),
			'telegram_url'       => esc_url_raw((string) ($d['telegram_url'] ?? '')),
			'discord_invite_url' => esc_url_raw((string) ($d['discord_invite_url'] ?? '')),
			'html'               => Panelr_Template::render('portal/chat-link', [
				'platform'           => $platform,
				'code'               => (string) ($d['code'] ?? ''),
				'telegram_url'       => esc_url_raw((string) ($d['telegram_url'] ?? '')),
				'discord_invite_url' => esc_url_raw((string) ($d['discord_invite_url'] ?? '')),
			]),
		]);
	}

	/** Unlink a chat. The account snapshot comes back fresh, so the choices update in place. */
	public static function ajax_chat_unlink(): void
	{
		self::check();
		self::require_member();
		$platform = self::post('platform');
		if (!in_array($platform, ['telegram', 'discord'], true)) {
			wp_send_json_error(['message' => __('Choose Telegram or Discord.', 'panelr-for-woocommerce')]);
		}
		$result = Panelr_API::instance()->unlink_chat(Panelr_Session::customer_id(), $platform);
		if (!$result['ok']) {
			wp_send_json_error(['message' => $result['error']]);
		}
		Panelr_Session::update_snapshot($result['data']);
		wp_send_json_success([
			'message'    => __('Unlinked.', 'panelr-for-woocommerce'),
			'preference' => (string) ($result['data']['contact_preference'] ?? 'email'),
			'chats'      => self::chats($result['data']),
		]);
	}

	// ── AJAX: sign in / out / register ────────────────────────────────────

	public static function ajax_login(): void
	{
		self::check();
		$email    = sanitize_email(self::post('email'));
		$password = self::post_password('password');
		if (!$email || $password === '') {
			wp_send_json_error(['message' => __('Enter your email and password.', 'panelr-for-woocommerce')]);
		}
		if (!Panelr_Helpers::rate_limit('login', 10, 15 * MINUTE_IN_SECONDS)) {
			wp_send_json_error(['message' => __('Too many tries. Please wait a few minutes.', 'panelr-for-woocommerce')]);
		}
		$result = Panelr_API::instance()->verify_customer_login($email, $password);
		if (!$result['ok']) {
			wp_send_json_error(['message' => $result['status'] === 401
				? __('That email and password do not match.', 'panelr-for-woocommerce')
				: ($result['status'] === 429
					? __('Too many tries. Please wait a few minutes.', 'panelr-for-woocommerce')
					: $result['error'])]);
		}
		Panelr_Session::sign_in($result['data']);
		wp_send_json_success(['redirect' => self::after_sign_in_url()]);
	}

	private static function after_sign_in_url(): string
	{
		$return = self::safe_return(self::post('return'));
		return $return ?: Panelr_Helpers::portal_url();
	}

	/** Connection details as a sign-in. An account with that email opens fully; none yet → the limited view. */
	public static function ajax_line_login(): void
	{
		self::check();
		$username  = self::post('username');
		$password  = self::post_password('password');
		$plugin_id = (int) self::post('plugin_id');
		if ($username === '' || $password === '') {
			wp_send_json_error(['message' => __('Enter your username and password.', 'panelr-for-woocommerce')]);
		}
		if (!Panelr_Helpers::rate_limit('login', 10, 15 * MINUTE_IN_SECONDS)) {
			wp_send_json_error(['message' => __('Too many tries. Please wait a few minutes.', 'panelr-for-woocommerce')]);
		}
		$result = Panelr_API::instance()->verify_login($username, $password, $plugin_id);
		if (!$result['ok']) {
			wp_send_json_error(['message' => $result['status'] === 401
				? __('Those connection details do not match.', 'panelr-for-woocommerce')
				: ($result['status'] === 429
					? __('Too many tries. Please wait a few minutes.', 'panelr-for-woocommerce')
					: $result['error'])]);
		}
		$data = $result['data'];

		// The line's owner already has a login → sign them into it.
		if (!empty($data['customer_email'])) {
			$acct = Panelr_API::instance()->get_customer_by_email((string) $data['customer_email']);
			if ($acct['ok'] && !empty($acct['data']['has_login'])) {
				Panelr_Session::sign_in($acct['data']);
				wp_send_json_success(['redirect' => self::after_sign_in_url()]);
			}
			$data['referral'] = ['account_required' => true] + (array) ($data['referral'] ?? []);
		}
		Panelr_Session::sign_in_line($data);
		wp_send_json_success(['redirect' => Panelr_Helpers::portal_url()]);
	}

	public static function ajax_logout(): void
	{
		self::check();
		Panelr_Session::sign_out();
		wp_send_json_success(['redirect' => Panelr_Helpers::portal_url()]);
	}

	public static function ajax_register(): void
	{
		self::check();

		// An account comes with an order. This door only opens on the way to
		// checkout — a return address pointing there, or something in the cart.
		$return      = self::safe_return(self::post('return'));
		$to_checkout = $return !== '' && untrailingslashit($return) === untrailingslashit(wc_get_checkout_url());
		$has_cart    = function_exists('WC') && WC()->cart && !WC()->cart->is_empty();
		if (!$to_checkout && !$has_cart) {
			wp_send_json_error(['message' => __('Accounts are created when you place an order.', 'panelr-for-woocommerce')]);
		}

		$email    = sanitize_email(self::post('email'));
		$name     = self::post('name');
		$password = self::post_password('password');
		$confirm  = self::post_password('password_confirm');
		$invite   = strtolower(preg_replace('/[^a-z0-9_\-]/', '', strtolower(self::post('invite_code'))));

		if (!$email || !is_email($email)) {
			wp_send_json_error(['message' => __('Enter a valid email address.', 'panelr-for-woocommerce')]);
		}
		if (strlen($password) < 8) {
			wp_send_json_error(['message' => __('Use at least 8 characters for your password.', 'panelr-for-woocommerce')]);
		}
		if ($confirm !== '' && $confirm !== $password) {
			wp_send_json_error(['message' => __('The two passwords do not match.', 'panelr-for-woocommerce')]);
		}
		if (!Panelr_Helpers::rate_limit('register', 5, 15 * MINUTE_IN_SECONDS)) {
			wp_send_json_error(['message' => __('Too many tries. Please wait a few minutes.', 'panelr-for-woocommerce')]);
		}

		if (!$invite) $invite = Panelr_Session::referral_code();
		if (self::registration_needs_invite()) {
			$check = $invite ? Panelr_API::instance()->validate_referral_code($invite) : ['ok' => false, 'data' => null];
			if (!$check['ok'] || empty($check['data']['valid'])) {
				wp_send_json_error(['message' => __('An invite code from a member is needed to create an account.', 'panelr-for-woocommerce')]);
			}
		}

		$body = [
			'customer_email' => $email,
			'customer_name'  => $name,
			'password'       => $password,
			'verify_url'     => Panelr_Helpers::portal_url(['panelr_verify' => '{token}']),
		];
		if ($invite) $body['referral_code'] = $invite;

		$result = Panelr_API::instance()->register_customer($body);
		if (!$result['ok']) {
			if ($result['status'] === 409) {
				wp_send_json_error(['message' => !empty($result['data']['claim_link_sent'])
					? __('That email is already on file with us. We have emailed you a link to choose a password — finish that first, then sign in.', 'panelr-for-woocommerce')
					: __('There is already an account for that email. Sign in, or use "Forgot password".', 'panelr-for-woocommerce')]);
			}
			wp_send_json_error(['message' => $result['error']]);
		}
		Panelr_Session::sign_in($result['data']);
		wp_send_json_success(['redirect' => self::after_sign_in_url()]);
	}

	/** From the limited (line) view: create the login for this line's email. */
	public static function ajax_setup_account(): void
	{
		self::check();
		$line = Panelr_Session::line_session();
		if (!$line || empty($line['email'])) {
			wp_send_json_error(['message' => __('Please sign in again.', 'panelr-for-woocommerce'), 'signed_out' => true]);
		}
		$password = self::post_password('password');
		$confirm  = self::post_password('password_confirm');
		if (strlen($password) < 8) {
			wp_send_json_error(['message' => __('Use at least 8 characters for your password.', 'panelr-for-woocommerce')]);
		}
		if ($confirm !== $password) {
			wp_send_json_error(['message' => __('The two passwords do not match.', 'panelr-for-woocommerce')]);
		}
		$body = [
			'customer_email' => (string) $line['email'],
			'customer_name'  => self::post('name') ?: (string) $line['name'],
			'password'       => $password,
			'verify_url'     => Panelr_Helpers::portal_url(['panelr_verify' => '{token}']),
		];
		$invite = Panelr_Session::referral_code();
		if ($invite) $body['referral_code'] = $invite;

		$result = Panelr_API::instance()->register_customer($body);
		if (!$result['ok']) {
			if ($result['status'] === 409) {
				if (!empty($result['data']['claim_link_sent'])) {
					// A member record without a password: Panelr emailed the line's address a link to finish setting it up.
					wp_send_json_error(['message' => __('This email already has an account record with us. We have emailed it a link to choose a password — finish that, then sign in here.', 'panelr-for-woocommerce')]);
				}
				// A login already exists for this email; the line owner proved the line, so open it.
				$acct = Panelr_API::instance()->get_customer_by_email((string) $line['email']);
				if ($acct['ok']) {
					Panelr_Session::sign_in($acct['data']);
					wp_send_json_success(['redirect' => Panelr_Helpers::portal_url()]);
				}
				wp_send_json_error(['message' => __('There is already an account for that email. Sign in with it, or use "Forgot password".', 'panelr-for-woocommerce')]);
			}
			wp_send_json_error(['message' => $result['error']]);
		}
		Panelr_Session::sign_in($result['data']);
		wp_send_json_success(['redirect' => Panelr_Helpers::portal_url()]);
	}

	public static function ajax_forgot(): void
	{
		self::check();
		$email = sanitize_email(self::post('email'));
		$message = __('If that address has an account, a link to choose a new password is on its way.', 'panelr-for-woocommerce');
		if (!$email) {
			wp_send_json_error(['message' => __('Enter your email address.', 'panelr-for-woocommerce')]);
		}
		if (!Panelr_Helpers::rate_limit('forgot', 5, 15 * MINUTE_IN_SECONDS)) {
			wp_send_json_error(['message' => __('Too many tries. Please wait a few minutes.', 'panelr-for-woocommerce')]);
		}
		Panelr_API::instance()->request_password_reset($email, Panelr_Helpers::portal_url(['panelr_reset' => '{token}']));
		// Same answer whether or not the address exists.
		wp_send_json_success(['message' => $message]);
	}

	public static function ajax_reset(): void
	{
		self::check();
		$token    = self::post('token');
		$password = self::post_password('password');
		$confirm  = self::post_password('password_confirm');
		if (strlen($password) < 8) {
			wp_send_json_error(['message' => __('Use at least 8 characters for your password.', 'panelr-for-woocommerce')]);
		}
		if ($confirm !== $password) {
			wp_send_json_error(['message' => __('The two passwords do not match.', 'panelr-for-woocommerce')]);
		}
		if (!Panelr_Helpers::rate_limit('reset', 5, 15 * MINUTE_IN_SECONDS)) {
			wp_send_json_error(['message' => __('Too many tries. Please wait a few minutes.', 'panelr-for-woocommerce')]);
		}
		$result = Panelr_API::instance()->reset_customer_password($token, $password);
		if (!$result['ok']) {
			wp_send_json_error(['message' => __('That link has already been used or has expired. Ask for a new one.', 'panelr-for-woocommerce')]);
		}
		Panelr_Session::sign_in($result['data']);
		wp_send_json_success(['redirect' => Panelr_Helpers::portal_url()]);
	}

	public static function ajax_resend_verify(): void
	{
		self::check();
		self::require_member();
		if (!Panelr_Helpers::rate_limit('resend', 3, 15 * MINUTE_IN_SECONDS)) {
			wp_send_json_error(['message' => __('Please wait a few minutes before asking again.', 'panelr-for-woocommerce')]);
		}
		// The signed-in account asks Panelr to send its confirmation email again.
		$result = Panelr_API::instance()->resend_verification(
			Panelr_Session::customer_id(),
			Panelr_Helpers::portal_url(['panelr_verify' => '{token}'])
		);
		if (!$result['ok']) {
			wp_send_json_error(['message' => $result['error']]);
		}
		if (!empty($result['data']['verified'])) {
			Panelr_Session::update_snapshot($result['data']);
		}
		wp_send_json_success(['message' => !empty($result['data']['verification_sent'])
			? __('Confirmation email sent.', 'panelr-for-woocommerce')
			: (!empty($result['data']['verified']) ? __('Your address is already confirmed.', 'panelr-for-woocommerce') : __('The email could not be sent right now. Please try again later.', 'panelr-for-woocommerce'))]);
	}

	public static function ajax_refresh(): void
	{
		self::check();
		self::require_member();
		$snapshot = Panelr_Session::snapshot(true);
		wp_send_json_success([
			'chats'              => self::chats($snapshot),
			'contact_preference' => (string) ($snapshot['contact_preference'] ?? 'email'),
		]);
	}

	// ── AJAX: connections ─────────────────────────────────────────────────

	public static function ajax_rename_line(): void
	{
		self::check();
		self::require_member();
		$activation_id = (int) self::post('activation_id');
		$label = mb_substr(self::post('label'), 0, 100);
		if (!Panelr_Session::owns_line($activation_id)) {
			wp_send_json_error(['message' => __('That connection is not on your account.', 'panelr-for-woocommerce')]);
		}
		$result = Panelr_API::instance()->update_customer($activation_id, ['portal_label' => $label]);
		if (!$result['ok']) {
			wp_send_json_error(['message' => $result['error']]);
		}
		Panelr_Session::snapshot(true);
		$line = Panelr_Session::line($activation_id);
		wp_send_json_success([
			'label'   => (string) ($result['data']['portal_label'] ?? $label),
			'display' => (string) (($line['label'] ?? '') ?: ($line['username'] ?? '')),
		]);
	}

	/** Credentials for one line, fetched on demand and never stored. */
	public static function ajax_line_details(): void
	{
		self::check();
		$activation_id = (int) self::post('activation_id');
		if (!Panelr_Session::owns_line($activation_id)) {
			wp_send_json_error(['message' => __('That connection is not on your account.', 'panelr-for-woocommerce')]);
		}

		$api = Panelr_API::instance();
		if (Panelr_Session::is_signed_in()) {
			$result = $api->get_lines_for_customer(Panelr_Session::customer_id());
		} else {
			$line_session = Panelr_Session::line_session();
			$result = $api->get_lines_for_email((string) ($line_session['email'] ?? ''));
		}
		if (!$result['ok'] || !is_array($result['data'])) {
			wp_send_json_error(['message' => $result['error'] ?: __('No connection details yet. Please try again shortly.', 'panelr-for-woocommerce')]);
		}

		foreach ($result['data'] as $line) {
			if ((int) $line['id'] !== $activation_id) continue;
			$has_editor = !empty($line['editor_username']) && !empty($line['editor_password']);
			wp_send_json_success([
				'host'     => (string) ($line['xtream_host'] ?? ''),
				'username' => (string) ($has_editor ? $line['editor_username'] : ($line['xtream_username'] ?? '')),
				'password' => (string) ($has_editor ? $line['editor_password'] : ($line['xtream_password'] ?? '')),
				'm3u_url'  => (string) ($line['m3u_url'] ?? ''),
				'epg_url'  => (string) ($line['epg_url'] ?? ''),
				'mac'      => (string) ($line['mac_address'] ?? ''),
				'type'     => (string) ($line['connection_type'] ?? 'm3u'),
			]);
		}
		wp_send_json_error(['message' => __('No connection details yet. Please try again shortly.', 'panelr-for-woocommerce')]);
	}

	/** The channels panel for one line, rendered on request. */
	public static function ajax_bouquets(): void
	{
		self::check();
		$activation_id = (int) self::post('activation_id');
		if (!Panelr_Session::owns_line($activation_id)) {
			wp_send_json_error(['message' => __('That connection is not on your account.', 'panelr-for-woocommerce')]);
		}
		$line = Panelr_Session::line($activation_id) ?: Panelr_Session::line_session();
		$plugin_id = (int) ($line['plugin_id'] ?? 0);
		if (!self::bouquets_allowed($plugin_id)) {
			wp_send_json_error(['message' => __('Channel choices are not available for this connection.', 'panelr-for-woocommerce')]);
		}

		$api = Panelr_API::instance();
		$b = $api->get_bouquets($plugin_id);
		if (!$b['ok']) wp_send_json_error(['message' => $b['error']]);
		$data = $b['data'];
		$mode = (string) ($data['mode'] ?? 'none');
		$bouquets = $data['bouquets'] ?? [];
		if ($mode === 'per_plugin' && !empty($data['plugins'])) {
			foreach ($data['plugins'] as $svc) {
				if ((int) $svc['plugin_id'] === $plugin_id) {
					$mode = (string) $svc['mode'];
					$bouquets = $svc['bouquets'];
				}
			}
		}

		// The line's current choice.
		$current = [];
		$lines = Panelr_Session::is_signed_in()
			? $api->get_lines_for_customer(Panelr_Session::customer_id())
			: $api->get_lines_for_email((string) ($line['email'] ?? ''));
		if ($lines['ok'] && is_array($lines['data'])) {
			foreach ($lines['data'] as $l) {
				if ((int) $l['id'] !== $activation_id) continue;
				if (!empty($l['bouquets']) && is_array($l['bouquets'])) {
					foreach ($l['bouquets'] as $ids) $current = array_merge($current, array_map('intval', (array) $ids));
				} elseif (!empty($l['bouquet_ids'])) {
					$current = array_map('intval', (array) $l['bouquet_ids']);
				}
			}
		}

		wp_send_json_success(['html' => Panelr_Template::render('portal/bouquets', [
			'activation_id' => $activation_id,
			'mode'          => $mode,
			'bouquets'      => $bouquets,
			'current'       => $current,
		])]);
	}

	public static function ajax_update_bouquets(): void
	{
		self::check();
		$activation_id = (int) self::post('activation_id');
		if (!Panelr_Session::owns_line($activation_id)) {
			wp_send_json_error(['message' => __('That connection is not on your account.', 'panelr-for-woocommerce')]);
		}
		$line = Panelr_Session::line($activation_id) ?: Panelr_Session::line_session();
		$plugin_id = (int) ($line['plugin_id'] ?? 0);
		if (!self::bouquets_allowed($plugin_id)) {
			wp_send_json_error(['message' => __('Channel choices are not available for this connection.', 'panelr-for-woocommerce')]);
		}

		// Only this service's bouquets may be sent.
		$allowed = [];
		$b = Panelr_API::instance()->get_bouquets($plugin_id);
		$mode = 'panel';
		if ($b['ok']) {
			$data = $b['data'];
			$mode = (string) ($data['mode'] ?? 'panel');
			$list = $data['bouquets'] ?? [];
			if ($mode === 'per_plugin' && !empty($data['plugins'])) {
				foreach ($data['plugins'] as $svc) {
					if ((int) $svc['plugin_id'] === $plugin_id) { $mode = (string) $svc['mode']; $list = $svc['bouquets']; }
				}
			}
			if ($mode === 'editor') {
				foreach ((array) $list as $cat => $items) foreach ((array) $items as $bq) $allowed[] = (int) $bq['id'];
			} else {
				foreach ((array) $list as $bq) $allowed[] = (int) $bq['id'];
			}
		}
		$keep = fn(array $ids) => array_values(array_filter(array_map('intval', $ids), fn($id) => in_array($id, $allowed, true)));

		// Nonce checked at the top; $keep casts every id to int and drops any not on this service.
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ($mode === 'editor') {
			$payload = ['bouquets' => [
				'live'   => $keep(array_map('absint', (array) wp_unslash($_POST['live'] ?? []))),
				'vod'    => $keep(array_map('absint', (array) wp_unslash($_POST['vod'] ?? []))),
				'series' => $keep(array_map('absint', (array) wp_unslash($_POST['series'] ?? []))),
			]];
		} else {
			$ids = $keep(array_map('absint', (array) wp_unslash($_POST['bouquet_ids'] ?? [])));
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ($mode !== 'editor') {
			if (!$ids) {
				wp_send_json_error(['message' => __('Choose at least one package.', 'panelr-for-woocommerce')]);
			}
			$payload = ['bouquet_ids' => $ids];
		}

		$result = Panelr_API::instance()->update_bouquets($activation_id, $payload);
		if (!$result['ok']) {
			wp_send_json_error(['message' => $result['error'] ?: __('Your channels were not changed. Please try again.', 'panelr-for-woocommerce')]);
		}
		wp_send_json_success(['message' => __('Channels saved.', 'panelr-for-woocommerce')]);
	}

	public static function ajax_add_renewal(): void
	{
		self::check();
		$activation_id = (int) self::post('activation_id');
		$panelr_product_id = (int) self::post('panelr_product_id');
		if (!Panelr_Session::owns_line($activation_id)) {
			wp_send_json_error(['message' => __('That connection is not on your account.', 'panelr-for-woocommerce')]);
		}
		$line = Panelr_Session::line($activation_id) ?: Panelr_Session::line_session();
		$row  = Panelr_Helpers::product_row_by_panelr_id($panelr_product_id);
		if (!$row || $row['status'] !== 'publish' || $row['is_trial']) {
			wp_send_json_error(['message' => __('That plan is not available right now.', 'panelr-for-woocommerce')]);
		}
		$line_plugin = (int) ($line['plugin_id'] ?? 0);
		if ($line_plugin && $row['plugin_id'] && $line_plugin !== $row['plugin_id']) {
			wp_send_json_error(['message' => __('That plan is on a different service than this connection.', 'panelr-for-woocommerce')]);
		}
		$is_trial = !empty($line['is_trial']);
		if (!$is_trial && (int) ($line['connections'] ?? 0) > $row['connections'] && $row['connections']) {
			wp_send_json_error(['message' => sprintf(
				/* translators: %d: connections */
				__('A renewal needs a plan with at least %d connections.', 'panelr-for-woocommerce'),
				(int) $line['connections']
			)]);
		}
		$use_credits = self::post('credits') === '1' && Panelr_Session::is_signed_in()
			&& $row['cost_points'] > 0 && Panelr_Session::credits_balance() >= $row['cost_points'] + Panelr_Cart::credits_in_cart();

		$data = [
			'_panelr_intent'        => $is_trial ? 'trial_upgrade' : 'renewal',
			'_panelr_activation_id' => $activation_id,
			'_panelr_plugin_id'     => $row['plugin_id'],
			'_panelr_line_label'    => (string) (($line['label'] ?? '') ?: ($line['username'] ?? '')),
		];
		if ($use_credits) $data['_panelr_pay_with_points'] = 1;

		$key = WC()->cart->add_to_cart($row['wc_id'], 1, 0, [], $data);
		if (!$key) {
			wp_send_json_error(['message' => __('That plan could not be added to the cart.', 'panelr-for-woocommerce')]);
		}
		if (WC()->customer) {
			$email = Panelr_Session::email() ?: (string) ($line['email'] ?? '');
			if ($email && !WC()->customer->get_billing_email()) WC()->customer->set_billing_email($email);
		}
		wp_send_json_success(['checkout_url' => wc_get_checkout_url()]);
	}

	// ── AJAX: account ─────────────────────────────────────────────────────

	public static function ajax_update_account(): void
	{
		self::check();
		self::require_member();
		$name  = self::post('customer_name');
		$email = sanitize_email(self::post('customer_email') ?: self::post('new_email'));
		$body  = ['customer_id' => Panelr_Session::customer_id()];
		if ($name !== '') $body['customer_name'] = $name;
		if ($email && strtolower($email) !== strtolower(Panelr_Session::email())) $body['new_email'] = $email;
		if (count($body) === 1) {
			wp_send_json_success(['message' => __('Nothing changed.', 'panelr-for-woocommerce')]);
		}
		$result = Panelr_API::instance()->update_customer_account($body);
		if (!$result['ok']) {
			wp_send_json_error(['message' => $result['error']]);
		}
		Panelr_Session::update_snapshot($result['data']);
		wp_send_json_success([
			'message' => !empty($result['data']['email_changed'])
				? __('Saved. Your new address needs confirming — check your inbox.', 'panelr-for-woocommerce')
				: __('Saved.', 'panelr-for-woocommerce'),
			'name'  => (string) ($result['data']['customer_name'] ?? $name),
			'email' => (string) ($result['data']['email'] ?? $email),
		]);
	}

	public static function ajax_change_password(): void
	{
		self::check();
		self::require_member();
		$current = self::post_password('current_password');
		$new     = self::post_password('password');
		$confirm = self::post_password('password_confirm');
		if (strlen($new) < 8) {
			wp_send_json_error(['message' => __('Use at least 8 characters for your new password.', 'panelr-for-woocommerce')]);
		}
		if ($confirm !== $new) {
			wp_send_json_error(['message' => __('The two passwords do not match.', 'panelr-for-woocommerce')]);
		}
		$result = Panelr_API::instance()->set_customer_password([
			'customer_id'      => Panelr_Session::customer_id(),
			'current_password' => $current,
			'password'         => $new,
		]);
		if (!$result['ok']) {
			wp_send_json_error(['message' => $result['status'] === 401
				? __('Your current password is not right.', 'panelr-for-woocommerce')
				: $result['error']]);
		}
		wp_send_json_success(['message' => __('Password changed.', 'panelr-for-woocommerce')]);
	}

	public static function ajax_contact_pref(): void
	{
		self::check();
		self::require_member();
		$pref = self::post('preference');
		if (!in_array($pref, ['email', 'telegram', 'discord'], true)) {
			wp_send_json_error(['message' => __('Choose email, Telegram or Discord.', 'panelr-for-woocommerce')]);
		}
		$result = Panelr_API::instance()->update_customer_account([
			'customer_id'        => Panelr_Session::customer_id(),
			'contact_preference' => $pref,
		]);
		if (!$result['ok']) {
			wp_send_json_error(['message' => $result['error']]);
		}
		Panelr_Session::update_snapshot($result['data']);
		wp_send_json_success(['message' => __('Saved.', 'panelr-for-woocommerce')]);
	}
}
