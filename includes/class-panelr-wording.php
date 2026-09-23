<?php
defined('ABSPATH') || exit;

/**
 * The words this store shows its customers.
 *
 * Panelr talks about connections, playlists, M3U and EPG links. A store may
 * not want any of that in front of its customers. Every customer-facing
 * label the plugin prints comes through here: the operator's word when one
 * is set under Panelr → Wording, the plugin's own otherwise. Nothing here
 * touches what is sent to Panelr — orders are matched by ids, never by words.
 */
class Panelr_Wording
{
	const OPTION       = 'panelr_wording';
	const SHOW_OPTION  = 'panelr_wording_show';

	private static ?array $words = null;
	private static ?array $show  = null;

	/**
	 * Every term: key => [group, what it is, the plugin's own wording, the neutral wording].
	 * %s / %d stay in place in the two sentences and the two counts.
	 */
	public static function terms(): array
	{
		return [
			// Names
			'connection'         => ['names', __('A connection (one thing a customer bought)', 'panelr-for-woocommerce'), __('Connection', 'panelr-for-woocommerce'), __('Subscription', 'panelr-for-woocommerce')],
			'connections'        => ['names', __('Connections (the member area tab)', 'panelr-for-woocommerce'),          __('Connections', 'panelr-for-woocommerce'), __('Subscriptions', 'panelr-for-woocommerce')],
			'connection_details' => ['names', __('Connection details (the button)', 'panelr-for-woocommerce'),             __('Connection details', 'panelr-for-woocommerce'), __('Access details', 'panelr-for-woocommerce')],
			'service'            => ['names', __('Service', 'panelr-for-woocommerce'),                                     __('Service', 'panelr-for-woocommerce'), __('Service', 'panelr-for-woocommerce')],
			'plan'               => ['names', __('Plan', 'panelr-for-woocommerce'),                                        __('Plan', 'panelr-for-woocommerce'), __('Plan', 'panelr-for-woocommerce')],
			'free_trial'         => ['names', __('Free trial', 'panelr-for-woocommerce'),                                  __('Free trial', 'panelr-for-woocommerce'), __('Free trial', 'panelr-for-woocommerce')],
			'credit_1'           => [
				'names',
				/* translators: %d is left as-is; it is where the number goes */
				__('One credit (keep %d)', 'panelr-for-woocommerce'),
				/* translators: %d: number of credits */
				__('%d credit', 'panelr-for-woocommerce'),
				/* translators: %d: number of credits */
				__('%d credit', 'panelr-for-woocommerce'),
			],
			'credit_n'           => [
				'names',
				/* translators: %d is left as-is; it is where the number goes */
				__('Several credits (keep %d)', 'panelr-for-woocommerce'),
				/* translators: %d: number of credits */
				__('%d credits', 'panelr-for-woocommerce'),
				/* translators: %d: number of credits */
				__('%d credits', 'panelr-for-woocommerce'),
			],
			'connection_count_1' => [
				'names',
				/* translators: %d is left as-is; it is where the number goes */
				__('One connection on a plan (keep %d)', 'panelr-for-woocommerce'),
				/* translators: %d: number of connections */
				__('%d connection', 'panelr-for-woocommerce'),
				/* translators: %d: number of devices */
				__('%d device', 'panelr-for-woocommerce'),
			],
			'connection_count_n' => [
				'names',
				/* translators: %d is left as-is; it is where the number goes */
				__('Several connections on a plan (keep %d)', 'panelr-for-woocommerce'),
				/* translators: %d: number of connections */
				__('%d connections', 'panelr-for-woocommerce'),
				/* translators: %d: number of devices */
				__('%d devices', 'panelr-for-woocommerce'),
			],
			// Order types
			'order_new'          => ['orders', __('Order type: a new purchase', 'panelr-for-woocommerce'),  __('New connection', 'panelr-for-woocommerce'), __('New subscription', 'panelr-for-woocommerce')],
			'order_renewal'      => ['orders', __('Order type: a renewal', 'panelr-for-woocommerce'),       __('Renewal', 'panelr-for-woocommerce'), __('Renewal', 'panelr-for-woocommerce')],
			'order_upgrade'      => ['orders', __('Order type: a trial upgrade', 'panelr-for-woocommerce'), __('Trial upgrade', 'panelr-for-woocommerce'), __('Upgrade', 'panelr-for-woocommerce')],
			'order_balance'      => ['orders', __('Order type: a balance payment', 'panelr-for-woocommerce'), __('Balance payment', 'panelr-for-woocommerce'), __('Balance payment', 'panelr-for-woocommerce')],
			'renew'              => ['orders', __('Renew (the button)', 'panelr-for-woocommerce'),          __('Renew', 'panelr-for-woocommerce'), __('Renew', 'panelr-for-woocommerce')],
			'upgrade'            => ['orders', __('Upgrade (the button)', 'panelr-for-woocommerce'),        __('Upgrade', 'panelr-for-woocommerce'), __('Upgrade', 'panelr-for-woocommerce')],
			// Details a member sees
			'host'               => ['details', __('Host', 'panelr-for-woocommerce'),            __('Host', 'panelr-for-woocommerce'), __('Server', 'panelr-for-woocommerce')],
			'username'           => ['details', __('Username', 'panelr-for-woocommerce'),        __('Username', 'panelr-for-woocommerce'), __('Username', 'panelr-for-woocommerce')],
			'password'           => ['details', __('Password', 'panelr-for-woocommerce'),        __('Password', 'panelr-for-woocommerce'), __('Password', 'panelr-for-woocommerce')],
			'm3u'                => ['details', __('M3U link', 'panelr-for-woocommerce'),        __('M3U link', 'panelr-for-woocommerce'), __('Sign-in link', 'panelr-for-woocommerce')],
			'epg'                => ['details', __('EPG link', 'panelr-for-woocommerce'),        __('EPG link', 'panelr-for-woocommerce'), __('Guide link', 'panelr-for-woocommerce')],
			'mac'                => ['details', __('MAC address', 'panelr-for-woocommerce'),     __('MAC address', 'panelr-for-woocommerce'), __('Device id', 'panelr-for-woocommerce')],
			'links'              => ['details', __('Playlist links (the heading)', 'panelr-for-woocommerce'), __('Playlist links', 'panelr-for-woocommerce'), __('Links', 'panelr-for-woocommerce')],
			'how_to'             => ['details', __('How to connect (the heading)', 'panelr-for-woocommerce'), __('How to connect', 'panelr-for-woocommerce'), __('Getting started', 'panelr-for-woocommerce')],
			'apps'               => ['details', __('Apps', 'panelr-for-woocommerce'),            __('Apps', 'panelr-for-woocommerce'), __('Apps', 'panelr-for-woocommerce')],
			'code_label'         => ['details', __('Downloader code', 'panelr-for-woocommerce'), __('Downloader code', 'panelr-for-woocommerce'), __('Setup code', 'panelr-for-woocommerce')],
			'groups'             => ['details', __('Groups (heading over channel groups)', 'panelr-for-woocommerce'), __('Groups', 'panelr-for-woocommerce'), __('Groups', 'panelr-for-woocommerce')],
			'individual'         => ['details', __('Individual (heading over single channel packages)', 'panelr-for-woocommerce'), __('Individual', 'panelr-for-woocommerce'), __('Individual', 'panelr-for-woocommerce')],
			// Sentences
			'ready_inbox'        => ['sentences', __('Order is set up (thank-you page)', 'panelr-for-woocommerce'),     __('Your service is set up. Your connection details are in your inbox.', 'panelr-for-woocommerce'), __('You are all set. Your access details are in your inbox.', 'panelr-for-woocommerce')],
			'ready_sent'         => [
				'sentences',
				/* translators: %s is left as-is; it is where the email address goes */
				__('Order is set up (order status page, keep %s)', 'panelr-for-woocommerce'),
				/* translators: %s: the customer's email address */
				__('Your service is set up. Your connection details went to %s.', 'panelr-for-woocommerce'),
				/* translators: %s: the customer's email address */
				__('You are all set. Your access details went to %s.', 'panelr-for-woocommerce'),
			],
			'trial_ready'        => ['sentences', __('Trial is ready', 'panelr-for-woocommerce'),                       __('Your trial is ready. Check your email for your connection details.', 'panelr-for-woocommerce'), __('Your trial is ready. Check your email for your access details.', 'panelr-for-woocommerce')],
			'no_connections'     => ['sentences', __('Member area with nothing on it', 'panelr-for-woocommerce'),       __('No connections on this account yet.', 'panelr-for-woocommerce'), __('Nothing on this account yet.', 'panelr-for-woocommerce')],
			'details_none'       => ['sentences', __('Details not ready yet', 'panelr-for-woocommerce'),                __('No connection details yet. Please try again shortly.', 'panelr-for-woocommerce'), __('No access details yet. Please try again shortly.', 'panelr-for-woocommerce')],
		];
	}

	/** The switches: key => [what it does, default]. */
	public static function switches(): array
	{
		return [
			'order_type'        => [__('Show the order type (renewal, upgrade) on order lines, in the cart, on receipts and in emails', 'panelr-for-woocommerce'), true],
			'line_name'         => [__('Show which connection a renewal is for on order lines', 'panelr-for-woocommerce'), true],
			'service_line'      => [__('Show the service on order lines when the store sells more than one', 'panelr-for-woocommerce'), true],
			'product_service'   => [__('Show the service under the product on product pages', 'panelr-for-woocommerce'), true],
			'plan_connections'  => [__('Show the connection count next to plans', 'panelr-for-woocommerce'), true],
		];
	}

	public static function groups(): array
	{
		return [
			'names'     => __('Names', 'panelr-for-woocommerce'),
			'orders'    => __('Orders', 'panelr-for-woocommerce'),
			'details'   => __('Details a member sees', 'panelr-for-woocommerce'),
			'sentences' => __('Sentences', 'panelr-for-woocommerce'),
		];
	}

	/** The operator's wording, key => text. Only keys with a value. */
	public static function custom(): array
	{
		if (self::$words === null) {
			$raw = json_decode((string) get_option(self::OPTION, '{}'), true);
			self::$words = is_array($raw) ? array_filter(array_map('strval', $raw), fn($v) => $v !== '') : [];
		}
		return self::$words;
	}

	/** The word customers see for a term. */
	public static function term(string $key): string
	{
		$custom = self::custom();
		if (isset($custom[$key])) return apply_filters('panelr_wording_term', $custom[$key], $key);
		$terms = self::terms();
		return apply_filters('panelr_wording_term', $terms[$key][2] ?? $key, $key);
	}

	/** A counted term: "%d connection" / "%d connections", with the number in. */
	public static function count(string $base, int $n): string
	{
		$pattern = self::term($base . ($n === 1 ? '_1' : '_n'));
		return strpos($pattern, '%d') !== false ? sprintf($pattern, $n) : $n . ' ' . $pattern;
	}

	public static function credits(int $n): string
	{
		return self::count('credit', $n);
	}

	public static function show(string $switch): bool
	{
		if (self::$show === null) {
			$raw = json_decode((string) get_option(self::SHOW_OPTION, '{}'), true);
			self::$show = is_array($raw) ? $raw : [];
		}
		if (array_key_exists($switch, self::$show)) return (string) self::$show[$switch] === '1';
		return (bool) (self::switches()[$switch][1] ?? true);
	}

	/** The neutral wording, key => text, for the one-click preset. */
	public static function neutral(): array
	{
		$out = [];
		foreach (self::terms() as $key => $def) $out[$key] = $def[3];
		return $out;
	}

	public static function flush(): void
	{
		self::$words = null;
		self::$show  = null;
	}
}
