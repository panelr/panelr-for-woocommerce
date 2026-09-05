<?php
defined('ABSPATH') || exit;

/**
 * Support tickets on this site: [panelr_support] and the member area's
 * Support tab. Plain forms, nonce-checked, handled before output. A
 * customer never touches update_support_ticket.
 */
class Panelr_Support
{
	public static function init(): void
	{
		add_shortcode('panelr_support', [__CLASS__, 'render']);
		add_action('template_redirect', [__CLASS__, 'handle_posts']);
	}

	public static function on_site(): bool
	{
		return get_option('panelr_support_pages', 'panelr') === 'site';
	}

	private static function email(): string
	{
		if (Panelr_Session::is_signed_in()) return Panelr_Session::email();
		$line = Panelr_Session::line_session();
		return (string) ($line['email'] ?? '');
	}

	private static function lines(): array
	{
		if (Panelr_Session::is_signed_in()) return Panelr_Session::lines();
		$line = Panelr_Session::line_session();
		return $line ? [(int) $line['activation_id'] => $line] : [];
	}

	// ── Shortcode ─────────────────────────────────────────────────────────

	public static function render($atts = []): string
	{
		wp_enqueue_script('panelr-common');
		if (!self::on_site()) {
			$url = Panelr_API::instance()->base_url() ? Panelr_API::instance()->base_url() . '/support.php' : '';
			return Panelr_Template::render('support/elsewhere', ['url' => $url]);
		}
		if (!Panelr_Helpers::support_enabled()) {
			return Panelr_Template::notice(__('Support tickets are not available right now.', 'panelr-for-woocommerce'), 'info');
		}
		if (!self::email()) {
			return Panelr_Template::render('support/sign-in-first', [
				'sign_in_url' => Panelr_Helpers::portal_url(['return' => rawurlencode(home_url(esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'] ?? '/'))))]),
			]);
		}
		return self::render_for_member(Panelr_Session::snapshot(), self::current_url());
	}

	private static function current_url(): string
	{
		return remove_query_arg(['ticket', 'new', 'panelr_notice'], home_url(esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'] ?? '/'))));
	}

	/** The support screens (list, new ticket, thread). $base is the page they live on. */
	public static function render_for_member(array $snapshot, string $base): string
	{
		wp_enqueue_script('panelr-common');
		$email  = self::email();
		$notice = self::consume_notice();
		$ticket = sanitize_text_field(wp_unslash($_GET['ticket'] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which ticket to show; read only

		if ($ticket !== '') {
			$result = Panelr_API::instance()->get_support_ticket($ticket, $email);
			if (!$result['ok']) {
				return $notice . Panelr_Template::notice(__('That ticket could not be found.', 'panelr-for-woocommerce'));
			}
			return $notice . Panelr_Template::render('support/ticket', [
				'ticket'   => $result['data'],
				'base'     => $base,
				'nonce'    => wp_create_nonce('panelr_support'),
			]);
		}

		if (isset($_GET['new'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- opens the form; read only
			return $notice . Panelr_Template::render('support/new', [
				'topics' => Panelr_Helpers::support_topics(),
				'lines'  => self::lines(),
				'base'   => $base,
				'nonce'  => wp_create_nonce('panelr_support'),
				'multi'  => Panelr_Helpers::multi_service(),
			]);
		}

		$result = Panelr_API::instance()->get_support_tickets($email);
		$data   = $result['ok'] && is_array($result['data']) ? $result['data'] : ['open' => [], 'closed' => []];
		return $notice . Panelr_Template::render('support/list', [
			'open'   => (array) ($data['open'] ?? []),
			'closed' => array_slice((array) ($data['closed'] ?? []), 0, 10),
			'base'   => $base,
			'error'  => $result['ok'] ? '' : $result['error'],
		]);
	}

	// ── Forms ─────────────────────────────────────────────────────────────

	public static function handle_posts(): void
	{
		if (sanitize_key(wp_unslash($_SERVER['REQUEST_METHOD'] ?? '')) !== 'post' || empty($_POST['panelr_support_action'])) return; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- the nonce is checked on the next line
		if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['panelr_support_nonce'] ?? '')), 'panelr_support')) {
			self::redirect_with(__('That form has expired. Please try again.', 'panelr-for-woocommerce'), 'error');
		}
		$email = self::email();
		if (!$email) {
			self::redirect_with(__('Please sign in again.', 'panelr-for-woocommerce'), 'error');
		}
		if (!Panelr_Helpers::rate_limit('support', 20, 15 * MINUTE_IN_SECONDS)) {
			self::redirect_with(__('Too many tries. Please wait a few minutes.', 'panelr-for-woocommerce'), 'error');
		}

		$action = sanitize_key(wp_unslash($_POST['panelr_support_action']));
		$base   = self::safe_base(sanitize_text_field(wp_unslash($_POST['panelr_support_base'] ?? '')));
		$api    = Panelr_API::instance();

		if ($action === 'create') {
			$topic   = sanitize_text_field(wp_unslash($_POST['topic'] ?? ''));
			$subject = sanitize_text_field(wp_unslash($_POST['subject'] ?? ''));
			$message = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));
			$line    = absint(wp_unslash($_POST['activation_id'] ?? 0));
			if ($subject === '' || $message === '' || $topic === '') {
				self::redirect_with(__('Choose a topic and write a subject and a message.', 'panelr-for-woocommerce'), 'error', add_query_arg('new', '1', $base));
			}
			// The same ticket sent twice (a double click, a reload of the POST) is one ticket.
			$dupe_key = 'panelr_ticket_' . md5($email . '|' . $topic . '|' . $subject . '|' . $message);
			$dupe_ref = get_transient($dupe_key);
			if (is_string($dupe_ref) && $dupe_ref !== '') {
				self::redirect_with(__('Ticket opened. We will reply by email and here.', 'panelr-for-woocommerce'), 'success', add_query_arg('ticket', $dupe_ref, $base));
			}

			$body = ['customer_email' => $email, 'subject' => $subject, 'topic' => $topic, 'message' => $message];
			if ($line && isset(self::lines()[$line])) $body['activation_id'] = $line;
			$result = $api->create_support_ticket($body);
			if (!$result['ok']) {
				self::redirect_with($result['error'], 'error', add_query_arg('new', '1', $base));
			}
			set_transient($dupe_key, (string) $result['data']['ticket_ref'], 2 * MINUTE_IN_SECONDS);
			Panelr_Session::snapshot(true);
			self::redirect_with(__('Ticket opened. We will reply by email and here.', 'panelr-for-woocommerce'), 'success', add_query_arg('ticket', (string) $result['data']['ticket_ref'], $base));
		}

		$ref = sanitize_text_field(wp_unslash($_POST['ticket_ref'] ?? ''));
		if ($ref === '') self::redirect_with(__('That ticket could not be found.', 'panelr-for-woocommerce'), 'error', $base);
		$ticket_url = add_query_arg('ticket', $ref, $base);

		if ($action === 'reply') {
			$message = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));
			if ($message === '') self::redirect_with(__('Write a message first.', 'panelr-for-woocommerce'), 'error', $ticket_url);
			$result = $api->reply_support_ticket($ref, $email, $message);
			if (!$result['ok']) self::redirect_with($result['error'], 'error', $ticket_url);
			Panelr_Session::snapshot(true);
			self::redirect_with(__('Reply sent.', 'panelr-for-woocommerce'), 'success', $ticket_url);
		}

		if ($action === 'close') {
			$result = $api->close_support_ticket($ref, $email);
			if (!$result['ok']) self::redirect_with($result['error'], 'error', $ticket_url);
			Panelr_Session::snapshot(true);
			self::redirect_with(__('Ticket closed. Reply any time to reopen it.', 'panelr-for-woocommerce'), 'success', $ticket_url);
		}
	}

	private static function safe_base(string $url): string
	{
		$host = wp_parse_url($url, PHP_URL_HOST);
		if (!$url || ($host && strtolower($host) !== strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST)))) {
			return Panelr_Helpers::page_url('support') ?: Panelr_Helpers::portal_url(['tab' => 'support']);
		}
		return $url;
	}

	private static function redirect_with(string $message, string $type, string $url = ''): void
	{
		Panelr_Session::set('panelr_support_notice', ['message' => $message, 'type' => $type]);
		wp_safe_redirect($url ?: self::current_url());
		exit;
	}

	private static function consume_notice(): string
	{
		$n = Panelr_Session::get('panelr_support_notice');
		if (!is_array($n)) return '';
		Panelr_Session::forget('panelr_support_notice');
		return Panelr_Template::notice((string) $n['message'], (string) $n['type']);
	}
}
