<?php
defined('ABSPATH') || exit;

/**
 * Panelr → settings: a top-level menu with tabs, every option through the
 * Settings API, ajax only for the buttons that talk to Panelr.
 */
class Panelr_Settings
{
	const PAGE = 'panelr';

	public static function init(): void
	{
		add_action('admin_menu',            [__CLASS__, 'add_menu']);
		add_action('admin_init',            [__CLASS__, 'register_settings']);
		add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
		add_action('admin_head',            [__CLASS__, 'menu_icon_css']);
		add_action('admin_notices',         [__CLASS__, 'notices']);
		add_action('admin_init',            [__CLASS__, 'maybe_redirect_after_activation'], 20);
		add_filter('plugin_action_links_' . plugin_basename(PANELR_PLUGIN_FILE), [__CLASS__, 'action_links']);
		add_action('updated_option',        [__CLASS__, 'on_option_updated'], 10, 3);
		add_action('added_option',          [__CLASS__, 'on_option_added'], 10, 2);

		add_action('wp_ajax_panelr_test_connection', [__CLASS__, 'ajax_test_connection']);
		add_action('wp_ajax_panelr_refresh_cache',   [__CLASS__, 'ajax_refresh_cache']);
		add_action('wp_ajax_panelr_create_pages',    [__CLASS__, 'ajax_create_pages']);
	}

	public static function tabs(): array
	{
		return [
			'connection' => __('Connection', 'panelr-for-woocommerce'),
			'services'   => __('Products', 'panelr-for-woocommerce'),
			'pages'      => __('Pages', 'panelr-for-woocommerce'),
			'payments'   => __('Payments', 'panelr-for-woocommerce'),
			'trials'     => __('Trials', 'panelr-for-woocommerce'),
			'member'     => __('Members', 'panelr-for-woocommerce'),
			'support'    => __('Support', 'panelr-for-woocommerce'),
			'apps'       => __('Apps', 'panelr-for-woocommerce'),
			'bots'       => __('Bots', 'panelr-for-woocommerce'),
			'advanced'   => __('Advanced', 'panelr-for-woocommerce'),
		];
	}

	/** "Settings" next to Deactivate on the Plugins page. */
	public static function action_links(array $links): array
	{
		array_unshift($links, '<a href="' . esc_url(admin_url('admin.php?page=panelr')) . '">' . esc_html__('Settings', 'panelr-for-woocommerce') . '</a>');
		return $links;
	}

	/** A fresh activation opens the settings page once. */
	public static function maybe_redirect_after_activation(): void
	{
		if (!get_transient('panelr_activation_redirect')) return;
		delete_transient('panelr_activation_redirect');
		if (wp_doing_ajax() || is_network_admin() || isset($_GET['activate-multi'])) return; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WordPress's own bulk-activation flag
		if (!current_user_can('manage_options')) return;
		wp_safe_redirect(admin_url('admin.php?page=panelr'));
		exit;
	}

	public static function page_defs(): array
	{
		return [
			'portal'       => ['option' => 'panelr_page_portal',       'title' => __('My Account', 'panelr-for-woocommerce'),   'shortcode' => '[panelr_portal]',       'label' => __('Member area', 'panelr-for-woocommerce')],
			'trial'        => ['option' => 'panelr_page_trial',        'title' => __('Free Trial', 'panelr-for-woocommerce'),   'shortcode' => '[panelr_trial]',        'label' => __('Free trial', 'panelr-for-woocommerce')],
			'upgrade'      => ['option' => 'panelr_page_upgrade',      'title' => __('Upgrade Trial', 'panelr-for-woocommerce'),'shortcode' => '[panelr_upgrade]',      'label' => __('Trial upgrade (optional)', 'panelr-for-woocommerce')],
			'order_status' => ['option' => 'panelr_page_order_status', 'title' => __('Order Status', 'panelr-for-woocommerce'), 'shortcode' => '[panelr_order_status]', 'label' => __('Order status', 'panelr-for-woocommerce')],
			'support'      => ['option' => 'panelr_page_support',      'title' => __('Support', 'panelr-for-woocommerce'),      'shortcode' => '[panelr_support]',      'label' => __('Support', 'panelr-for-woocommerce')],
			'apps'         => ['option' => 'panelr_page_apps',         'title' => __('Apps', 'panelr-for-woocommerce'),         'shortcode' => '[panelr_apps]',         'label' => __('Apps', 'panelr-for-woocommerce')],
			'checkout'     => ['option' => 'panelr_page_checkout',     'title' => __('Pay for your order', 'panelr-for-woocommerce'), 'shortcode' => '[panelr_checkout]', 'label' => __('Checkout from Panelr', 'panelr-for-woocommerce')],
		];
	}

	// ── Menu ──────────────────────────────────────────────────────────────

	public static function add_menu(): void
	{
		$icon = PANELR_PLUGIN_URL . 'assets/images/pr-icon.png';
		add_menu_page(__('Panelr', 'panelr-for-woocommerce'), __('Panelr', 'panelr-for-woocommerce'), 'manage_options', self::PAGE, [__CLASS__, 'render_page'], $icon, 56);
		add_submenu_page(self::PAGE, __('Settings', 'panelr-for-woocommerce'), __('Settings', 'panelr-for-woocommerce'), 'manage_options', self::PAGE, [__CLASS__, 'render_page']);
		add_submenu_page(self::PAGE, __('Orders', 'panelr-for-woocommerce'), __('Orders', 'panelr-for-woocommerce'), 'edit_shop_orders', 'panelr-orders', ['Panelr_Admin_Orders', 'render_page']);
		add_submenu_page(self::PAGE, __('Customers', 'panelr-for-woocommerce'), __('Customers', 'panelr-for-woocommerce'), 'manage_options', 'panelr-customers', [__CLASS__, 'render_customers']);
	}

	// ── Settings API ──────────────────────────────────────────────────────

	public static function register_settings(): void
	{
		// Connection
		register_setting('panelr_connection', 'panelr_api_url', ['sanitize_callback' => [__CLASS__, 'sanitize_url']]);
		register_setting('panelr_connection', 'panelr_api_key', ['sanitize_callback' => [__CLASS__, 'sanitize_api_key']]);

		// Services & products
		register_setting('panelr_services', 'panelr_sync_overwrite_edits', ['sanitize_callback' => [__CLASS__, 'sanitize_bool']]);
		register_setting('panelr_services', 'panelr_product_categories',   ['sanitize_callback' => [__CLASS__, 'sanitize_bool']]);
		register_setting('panelr_services', 'panelr_service_names',        ['sanitize_callback' => [__CLASS__, 'sanitize_service_names']]);

		// Pages
		foreach (self::page_defs() as $def) {
			register_setting('panelr_pages', $def['option'], ['sanitize_callback' => 'absint']);
		}

		// Payments
		register_setting('panelr_payments', 'panelr_payment_map',          ['sanitize_callback' => [__CLASS__, 'sanitize_payment_map']]);
		register_setting('panelr_payments', 'panelr_coupon_source',        ['sanitize_callback' => fn($v) => $v === 'panelr' ? 'panelr' : 'woocommerce']);
		register_setting('panelr_payments', 'panelr_auto_complete_orders', ['sanitize_callback' => [__CLASS__, 'sanitize_bool']]);

		// Trials
		register_setting('panelr_trials', 'panelr_trials_enabled',        ['sanitize_callback' => [__CLASS__, 'sanitize_bool']]);
		register_setting('panelr_trials', 'panelr_turnstile_site_key',    ['sanitize_callback' => 'sanitize_text_field']);
		register_setting('panelr_trials', 'panelr_turnstile_secret_key',  ['sanitize_callback' => [__CLASS__, 'sanitize_secret']]);

		// Member area
		register_setting('panelr_member', 'panelr_theme',                 ['sanitize_callback' => fn($v) => in_array($v, ['none', 'light', 'dark'], true) ? $v : 'none']);
		register_setting('panelr_member', 'panelr_portal_allow_bouquets', ['sanitize_callback' => [__CLASS__, 'sanitize_bool']]);
		register_setting('panelr_member', 'panelr_wp_user_link',          ['sanitize_callback' => [__CLASS__, 'sanitize_bool']]);
		register_setting('panelr_member', 'panelr_session_days',          ['sanitize_callback' => fn($v) => max(1, min(90, (int) $v))]);
		register_setting('panelr_member', 'panelr_require_invite',        ['sanitize_callback' => fn($v) => in_array($v, ['auto', 'on', 'off'], true) ? $v : 'auto']);
		register_setting('panelr_member', 'panelr_checkout_account',      ['sanitize_callback' => fn($v) => in_array($v, ['auto', 'on', 'off'], true) ? $v : 'auto']);

		// Support
		register_setting('panelr_support', 'panelr_support_pages', ['sanitize_callback' => fn($v) => $v === 'site' ? 'site' : 'panelr']);

		// Bots
		register_setting('panelr_bots', 'panelr_bots_enabled',    ['sanitize_callback' => [__CLASS__, 'sanitize_bool']]);
		register_setting('panelr_bots', 'panelr_bot_telegram_url', ['sanitize_callback' => 'esc_url_raw']);
		register_setting('panelr_bots', 'panelr_bot_discord_url',  ['sanitize_callback' => 'esc_url_raw']);
		register_setting('panelr_bots', 'panelr_bots_footer',      ['sanitize_callback' => [__CLASS__, 'sanitize_bool']]);

		// Advanced
		register_setting('panelr_advanced', 'panelr_proxy_enabled',            ['sanitize_callback' => [__CLASS__, 'sanitize_bool']]);
		register_setting('panelr_advanced', 'panelr_proxy_header',             ['sanitize_callback' => fn($v) => preg_replace('/[^A-Z0-9_]/', '', strtoupper((string) $v)) ?: 'HTTP_CF_CONNECTING_IP']);
		register_setting('panelr_advanced', 'panelr_uninstall_draft_products', ['sanitize_callback' => [__CLASS__, 'sanitize_bool']]);
	}

	public static function sanitize_bool($v): string
	{
		return ($v === '1' || $v === 1 || $v === true || $v === 'on') ? '1' : '0';
	}

	/** Stored as the endpoint itself: https://host/api/api.php. A bare host or base URL is completed. */
	public static function sanitize_url($v): string
	{
		$v = trim((string) $v);
		if ($v === '') return '';
		if (!preg_match('#^https?://#i', $v)) {
			$v = 'https://' . $v;
		}
		$v = esc_url_raw($v);
		$v = preg_replace('#/api/(api|process)\.php.*$#i', '', $v);
		return rtrim($v, '/') . '/api/api.php';
	}

	/** The endpoint as shown in the field, for a value saved under v1 (base URL) or v2 (endpoint). */
	public static function endpoint_for_display(): string
	{
		$v = (string) get_option('panelr_api_url', '');
		if ($v === '') return '';
		$v = preg_replace('#/api/(api|process)\.php.*$#i', '', $v);
		return rtrim($v, '/') . '/api/api.php';
	}

	/** An empty submission keeps the saved key; the saved key never echoes into the page. */
	public static function sanitize_api_key($v): string
	{
		$v = trim((string) $v);
		if ($v === '') return (string) get_option('panelr_api_key', '');
		return sanitize_text_field($v);
	}

	public static function sanitize_secret($v): string
	{
		$v = trim((string) $v);
		if ($v === '') return (string) get_option('panelr_turnstile_secret_key', '');
		if ($v === '__clear__') return '';
		return sanitize_text_field($v);
	}

	public static function sanitize_service_names($v): string
	{
		if ($v === null || $v === '') return (string) get_option('panelr_service_names', '{}');
		if (is_string($v)) {
			$decoded = json_decode($v, true);
			$v = is_array($decoded) ? $decoded : [];
		}
		$clean = [];
		foreach ((array) $v as $id => $name) {
			$name = sanitize_text_field((string) $name);
			if ((int) $id && $name !== '') $clean[(int) $id] = mb_substr($name, 0, 80);
		}
		return wp_json_encode($clean);
	}

	public static function sanitize_payment_map($v): string
	{
		// Nothing posted (no gateways on the page): keep what is saved.
		if ($v === null || $v === '') {
			return (string) get_option('panelr_payment_map', '{}');
		}
		if (is_string($v)) {
			$decoded = json_decode($v, true);
			$v = is_array($decoded) ? $decoded : [];
		}
		$clean = [];
		foreach ((array) $v as $gateway => $method) {
			$gateway = sanitize_key((string) $gateway);
			$method  = (int) $method;
			if ($gateway && $method) $clean[$gateway] = $method;
		}
		return wp_json_encode($clean);
	}

	public static function on_option_added($option, $value): void
	{
		self::on_option_updated($option, null, $value);
	}

	/** Any Panelr option save empties the cached catalogue. */
	public static function on_option_updated($option, $old = null, $value = null): void
	{
		if (strpos((string) $option, 'panelr_') !== 0) return;
		if ($option === 'panelr_product_categories' && (string) $value === '1' && class_exists('Panelr_Sync')) {
			$n = Panelr_Sync::apply_categories();
			add_settings_error('panelr_services', 'panelr_categories', sprintf(
				/* translators: %d: count */
				_n('%d plan filed under its service category.', '%d plans filed under their service categories.', $n, 'panelr-for-woocommerce'),
				$n
			), 'success');
		}
		if (in_array($option, ['panelr_cache_version', 'panelr_last_sync', 'panelr_db_version', 'panelr_upgrade_notes', 'panelr_sync_notes', 'panelr_upgrade_due', 'panelr_flush_rewrite_rules'], true)) return;
		Panelr_API::flush_cache();
		Panelr_API::reset();
		if (in_array($option, ['panelr_api_url', 'panelr_api_key'], true)) {
			Panelr_API::clear_error_state();
		}
		if ($option === 'panelr_api_key' && function_exists('wp_set_option_autoload')) {
			wp_set_option_autoload('panelr_api_key', false);
		}
	}

	// ── Assets ────────────────────────────────────────────────────────────

	public static function admin_js_vars(): array
	{
		return [
			'ajaxurl' => admin_url('admin-ajax.php'),
			'nonce'   => wp_create_nonce('panelr_admin_nonce'),
			'i18n'    => [
				'working'        => __('Working…', 'panelr-for-woocommerce'),
				'done'           => __('Done', 'panelr-for-woocommerce'),
				'request_failed' => __('Something went wrong. Please try again.', 'panelr-for-woocommerce'),
				'sent'           => __('Sent to Panelr.', 'panelr-for-woocommerce'),
			],
		];
	}

	/** The menu icon is a PNG; WordPress sizes only dashicons, so pin it on every admin page. */
	public static function menu_icon_css(): void
	{
		echo '<style>#toplevel_page_panelr .wp-menu-image img{width:20px;height:20px;padding:7px 0 0}</style>';
	}

	public static function enqueue_assets(string $hook): void
	{
		if (strpos($hook, 'panelr') === false) return;
		wp_enqueue_script('panelr-admin', PANELR_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], PANELR_VERSION, true);
		wp_localize_script('panelr-admin', 'panelrAdmin', self::admin_js_vars());
		wp_enqueue_style('panelr-admin', PANELR_PLUGIN_URL . 'assets/css/admin.css', [], PANELR_VERSION);
	}

	// ── Notices ───────────────────────────────────────────────────────────

	public static function notices(): void
	{
		if (!current_user_can('manage_options')) return;
		$screen = get_current_screen();
		$on_panelr = $screen && strpos((string) $screen->id, 'panelr') !== false;

		wp_enqueue_script('panelr-admin', PANELR_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], PANELR_VERSION, true);
		wp_localize_script('panelr-admin', 'panelrAdmin', self::admin_js_vars());

		if (!Panelr_API::instance()->is_configured()) {
			if (!$on_panelr) {
				echo '<div class="notice notice-warning"><p><strong>' . esc_html__('Panelr', 'panelr-for-woocommerce') . '</strong> '
					. esc_html__('is not connected yet.', 'panelr-for-woocommerce')
					. ' <a href="' . esc_url(admin_url('admin.php?page=panelr')) . '">' . esc_html__('Open Connection', 'panelr-for-woocommerce') . '</a></p></div>';
			}
			return;
		}
		if (Panelr_API::is_rate_limited()) {
			echo '<div class="notice notice-warning"><p><strong>' . esc_html__('Panelr', 'panelr-for-woocommerce') . '</strong> '
				. esc_html__('is asking this site to slow down. Calls pause for a couple of minutes and then resume on their own.', 'panelr-for-woocommerce') . '</p></div>';
		}
		$err = Panelr_API::last_error();
		if ($err && Panelr_API::failure_count() >= 3) {
			echo '<div class="notice notice-error"><p><strong>' . esc_html__('Panelr cannot be reached.', 'panelr-for-woocommerce') . '</strong> '
				. esc_html(sprintf(
					/* translators: 1: error message, 2: time */
					__('Last error: %1$s at %2$s.', 'panelr-for-woocommerce'),
					$err['error'],
					wp_date(get_option('time_format'), (int) $err['at'])
				))
				. ' <a href="' . esc_url(admin_url('admin.php?page=panelr&tab=connection')) . '">' . esc_html__('Test connection', 'panelr-for-woocommerce') . '</a></p></div>';
		}
		if (get_transient('panelr_held_orders_notice')) {
			echo '<div class="notice notice-warning"><p><strong>' . esc_html__('Panelr', 'panelr-for-woocommerce') . '</strong> '
				. esc_html__('could not be reached when an order came in. The order is held and retried every 5 minutes.', 'panelr-for-woocommerce')
				. ' <a href="' . esc_url(admin_url('admin.php?page=panelr-orders')) . '">' . esc_html__('See orders', 'panelr-for-woocommerce') . '</a></p></div>';
		}
	}

	// ── AJAX ──────────────────────────────────────────────────────────────

	private static function guard(): void
	{
		check_ajax_referer('panelr_admin_nonce', 'nonce');
		if (!current_user_can('manage_options')) {
			wp_send_json_error(['message' => __('You are not allowed to do that.', 'panelr-for-woocommerce')]);
		}
	}

	public static function ajax_test_connection(): void
	{
		self::guard();
		Panelr_API::reset();
		$result = Panelr_API::instance()->test_connection();
		if ($result['ok']) {
			$name = (string) ($result['data']['company_name'] ?? '');
			wp_send_json_success(['message' => $name
				/* translators: %s: store name */
				? sprintf(__('Connected to %s.', 'panelr-for-woocommerce'), $name)
				: __('Connected.', 'panelr-for-woocommerce')]);
		}
		wp_send_json_error(['message' => $result['error']]);
	}

	public static function ajax_refresh_cache(): void
	{
		self::guard();
		Panelr_API::flush_cache();
		Panelr_API::reset();
		wp_send_json_success(['message' => __('Refreshed from Panelr.', 'panelr-for-woocommerce')]);
	}

	public static function ajax_create_pages(): void
	{
		self::guard();
		$which = array_map('sanitize_key', (array) wp_unslash($_POST['pages'] ?? [])); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- guard() verified the nonce
		$defs  = self::page_defs();
		$made  = [];
		foreach ($defs as $key => $def) {
			if ($which && !in_array($key, $which, true)) continue;
			$existing = (int) get_option($def['option'], 0);
			if ($existing && get_post_status($existing)) {
				$made[$key] = ['status' => 'exists', 'id' => $existing, 'title' => get_the_title($existing)];
				continue;
			}
			$page_id = wp_insert_post([
				'post_title'   => $def['title'],
				'post_content' => $def['shortcode'],
				'post_status'  => 'publish',
				'post_type'    => 'page',
			]);
			if (is_wp_error($page_id) || !$page_id) {
				wp_send_json_error(['message' => sprintf(
					/* translators: %s: page title */
					__('The page "%s" could not be created.', 'panelr-for-woocommerce'),
					$def['title']
				)]);
			}
			update_option($def['option'], (int) $page_id);
			$made[$key] = ['status' => 'created', 'id' => (int) $page_id, 'title' => $def['title']];
		}
		wp_send_json_success(['pages' => $made, 'message' => __('Pages are ready.', 'panelr-for-woocommerce')]);
	}

	// ── Render ────────────────────────────────────────────────────────────

	public static function render_page(): void
	{
		if (!current_user_can('manage_options')) return;
		$tabs = self::tabs();
		$tab  = sanitize_key(wp_unslash($_GET['tab'] ?? 'connection')); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which tab to show
		if (!isset($tabs[$tab])) $tab = 'connection';
		$api  = Panelr_API::instance();
		?>
		<div class="wrap panelr-settings-wrap">
			<div class="panelr-settings-header">
				<a href="https://panelr.app" target="_blank" rel="noopener" class="panelr-settings-logo">
					<img src="<?php echo esc_url(PANELR_PLUGIN_URL . 'assets/images/panelr-logo.png'); ?>" alt="Panelr" class="panelr-logo-img">
					<span class="panelr-logo-text"><?php esc_html_e('for WooCommerce', 'panelr-for-woocommerce'); ?></span>
				</a>
				<div class="panelr-settings-state">
					<span class="panelr-settings-version">v<?php echo esc_html(PANELR_VERSION); ?></span>
					<?php if ($api->is_configured()): ?>
						<span class="panelr-pill panelr-pill--ok"><?php esc_html_e('Connected', 'panelr-for-woocommerce'); ?></span>
					<?php else: ?>
						<span class="panelr-pill panelr-pill--warn"><?php esc_html_e('Not connected', 'panelr-for-woocommerce'); ?></span>
					<?php endif; ?>
				</div>
			</div>

			<nav class="nav-tab-wrapper panelr-tabs">
				<?php foreach ($tabs as $key => $label): ?>
					<a href="<?php echo esc_url(admin_url('admin.php?page=panelr&tab=' . $key)); ?>" class="nav-tab <?php echo $key === $tab ? 'nav-tab-active' : ''; ?>"><?php echo esc_html($label); ?></a>
				<?php endforeach; ?>
			</nav>

			<?php settings_errors(); ?>

			<div class="panelr-settings-section">
				<?php call_user_func([__CLASS__, 'tab_' . $tab]); ?>
			</div>
		</div>
		<?php
	}

	private static function field_open(string $label, string $for = ''): void
	{
		echo '<tr><th scope="row">' . ($for ? '<label for="' . esc_attr($for) . '">' : '') . esc_html($label) . ($for ? '</label>' : '') . '</th><td>';
	}

	private static function field_close(): void
	{
		echo '</td></tr>';
	}

	private static function checkbox(string $option, string $label, string $default = '0', string $help = ''): void
	{
		echo '<label><input type="hidden" name="' . esc_attr($option) . '" value="0">'
			. '<input type="checkbox" name="' . esc_attr($option) . '" value="1" ' . checked(get_option($option, $default), '1', false) . '> '
			. esc_html($label) . '</label>';
		self::help($help);
	}

	/** A "?" that explains the option on hover or tap. */
	private static function help(string $text): void
	{
		if ($text === '') return;
		echo ' <button type="button" class="panelr-help" aria-label="' . esc_attr__('What this does', 'panelr-for-woocommerce') . '" data-help="' . esc_attr($text) . '"><span class="dashicons dashicons-editor-help"></span></button>'
			. '<span class="panelr-help__text" hidden>' . esc_html($text) . '</span>';
	}

	// ── Tab: Connection ───────────────────────────────────────────────────

	public static function tab_connection(): void
	{
		$api = Panelr_API::instance();
		$err = Panelr_API::last_error();
		?>
		<form method="post" action="options.php">
			<?php settings_fields('panelr_connection'); ?>
			<table class="form-table" role="presentation">
				<?php self::field_open(__('Panelr API endpoint', 'panelr-for-woocommerce'), 'panelr_api_url'); ?>
					<?php if ($api->url_is_from_config()): ?>
						<input type="url" id="panelr_api_url" class="regular-text" value="<?php echo esc_attr($api->base_url() . '/api/api.php'); ?>" readonly>
						<span class="panelr-pill"><?php esc_html_e('Set in wp-config.php', 'panelr-for-woocommerce'); ?></span>
					<?php else: ?>
						<input type="url" id="panelr_api_url" name="panelr_api_url" class="regular-text" value="<?php echo esc_attr(self::endpoint_for_display()); ?>" placeholder="https://yourdomain.com/api/api.php" autocomplete="off">
					<?php endif; ?>
				<?php self::field_close(); ?>

				<?php self::field_open(__('API key', 'panelr-for-woocommerce'), 'panelr_api_key'); ?>
					<?php if ($api->key_is_from_config()): ?>
						<input type="text" id="panelr_api_key" class="regular-text" value="••••••••••••" readonly>
						<span class="panelr-pill"><?php esc_html_e('Set in wp-config.php', 'panelr-for-woocommerce'); ?></span>
					<?php else: ?>
						<div class="panelr-input-row">
							<?php if ($api->has_key()): ?>
								<span class="panelr-pill panelr-pill--ok"><?php esc_html_e('Saved', 'panelr-for-woocommerce'); ?></span>
							<?php endif; ?>
							<input type="password" id="panelr_api_key" name="panelr_api_key" class="regular-text" value="" autocomplete="new-password" placeholder="<?php echo $api->has_key() ? esc_attr__('Replace key', 'panelr-for-woocommerce') : esc_attr__('Paste your key', 'panelr-for-woocommerce'); ?>">
						</div>
					<?php endif; ?>
				<?php self::field_close(); ?>

				<?php self::field_open(__('Connection', 'panelr-for-woocommerce')); ?>
					<div class="panelr-input-row">
						<button type="button" id="panelr-test-connection" class="button"><?php esc_html_e('Test connection', 'panelr-for-woocommerce'); ?></button>
						<button type="button" id="panelr-refresh-cache" class="button"><?php esc_html_e('Refresh from Panelr', 'panelr-for-woocommerce'); ?></button>
						<span id="panelr-test-result" class="panelr-inline-result" aria-live="polite"></span>
					</div>
					<?php if ($err): ?>
						<p class="panelr-last-error"><?php echo esc_html(sprintf(
							/* translators: 1: error, 2: date and time */
							__('Last error: %1$s at %2$s', 'panelr-for-woocommerce'),
							$err['error'],
							wp_date(get_option('date_format') . ' ' . get_option('time_format'), (int) $err['at'])
						)); ?></p>
					<?php endif; ?>
				<?php self::field_close(); ?>
			</table>
			<?php if (!$api->url_is_from_config() || !$api->key_is_from_config()) submit_button(__('Save', 'panelr-for-woocommerce')); ?>
		</form>
		<?php
	}

	// ── Tab: Services & Products ──────────────────────────────────────────

	public static function tab_services(): void
	{
		$services = Panelr_Helpers::services();
		$products = Panelr_Sync::get_synced_products();
		$last     = get_option('panelr_last_sync', '');
		?>
		<h2><?php esc_html_e('Services', 'panelr-for-woocommerce'); ?></h2>
		<form method="post" action="options.php">
			<?php settings_fields('panelr_services'); ?>
		<?php $overrides = Panelr_Helpers::service_name_overrides(); ?>
		<?php if ($services): ?>
			<table class="widefat striped panelr-products-table">
				<thead><tr>
					<th><?php esc_html_e('Service', 'panelr-for-woocommerce'); ?></th>
					<th><?php esc_html_e('Shown on this store as', 'panelr-for-woocommerce'); ?></th>
					<th><?php esc_html_e('Category', 'panelr-for-woocommerce'); ?></th>
					<th><?php esc_html_e('Trials', 'panelr-for-woocommerce'); ?></th>
					<th><?php esc_html_e('Channels', 'panelr-for-woocommerce'); ?></th>
					<th><?php esc_html_e('Credits', 'panelr-for-woocommerce'); ?></th>
					<th><?php esc_html_e('Plans', 'panelr-for-woocommerce'); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ($services as $id => $svc):
					$count = count(array_filter($products, fn($p) => $p['plugin_id'] === (int) $id)); ?>
					<tr>
						<td><strong><?php echo esc_html($svc['name']); ?></strong></td>
						<td><input type="text" class="regular-text" name="panelr_service_names[<?php echo (int) $id; ?>]" value="<?php echo esc_attr($overrides[(int) $id] ?? ''); ?>" placeholder="<?php echo esc_attr($svc['name']); ?>" maxlength="80">
							<?php if (!empty($overrides[(int) $id]) && $overrides[(int) $id] !== $svc['name']): ?>
								<span class="panelr-real-name"><?php echo esc_html(sprintf(
									/* translators: 1: store name, 2: Panelr name */
									__('Customers see "%1$s"; Panelr calls it "%2$s".', 'panelr-for-woocommerce'),
									$overrides[(int) $id],
									$svc['name']
								)); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html($svc['category_name'] ?? '—'); ?></td>
						<td><?php echo !empty($svc['trials_enabled']) ? esc_html__('On', 'panelr-for-woocommerce') : esc_html__('Off', 'panelr-for-woocommerce'); ?></td>
						<td><?php echo !empty($svc['bouquets_enabled']) ? esc_html(ucfirst((string) $svc['bouquet_mode'])) : esc_html__('Off', 'panelr-for-woocommerce'); ?></td>
						<td><?php echo !empty($svc['referral_enabled']) ? esc_html__('On', 'panelr-for-woocommerce') : esc_html__('Off', 'panelr-for-woocommerce'); ?></td>
						<td><?php echo (int) $count; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php else: ?>
			<p><?php esc_html_e('No services yet. Connect to Panelr first.', 'panelr-for-woocommerce'); ?></p>
		<?php endif; ?>
		<?php if ($services): ?>
			<p><?php self::help(__('Type a name to show it instead of Panelr\'s wherever this store names the service: product pages, cart lines, the member area, the plans grid. Leave empty to use Panelr\'s name. Panelr itself is not changed.', 'panelr-for-woocommerce')); ?>
			<?php submit_button(__('Save names', 'panelr-for-woocommerce'), 'secondary', 'submit', false); ?></p>
		<?php endif; ?>
		</form>

		<h2><?php esc_html_e('Products', 'panelr-for-woocommerce'); ?></h2>
		<?php
		usort($products, function ($a, $b) {
			$sa = Panelr_Helpers::service_name($a['plugin_id']);
			$sb = Panelr_Helpers::service_name($b['plugin_id']);
			return [$sa, $a['connections'], $a['duration_months'], $a['name']] <=> [$sb, $b['connections'], $b['duration_months'], $b['name']];
		});
		$service_ids = array_values(array_unique(array_map(fn($p) => $p['plugin_id'], $products)));
		?>
		<p class="panelr-input-row">
			<button type="button" id="panelr-sync-products" class="button button-primary"><?php esc_html_e('Sync from Panelr', 'panelr-for-woocommerce'); ?></button>
			<span id="panelr-sync-result" class="panelr-inline-result" aria-live="polite"></span>
			<?php if ($last): ?>
				<span class="panelr-last-sync"><?php echo esc_html(sprintf(
					/* translators: %s: date and time */
					__('Last sync: %s', 'panelr-for-woocommerce'),
					$last
				)); ?></span>
			<?php endif; ?>
		</p>

		<?php if ($products): ?>
			<?php if (count($service_ids) > 1): ?>
				<p class="panelr-chips" role="group" aria-label="<?php esc_attr_e('Show plans on', 'panelr-for-woocommerce'); ?>">
					<button type="button" class="button panelr-chip is-active" data-service="all"><?php esc_html_e('All services', 'panelr-for-woocommerce'); ?></button>
					<?php foreach ($service_ids as $sid): ?>
						<?php $shown = Panelr_Helpers::service_name($sid); $real = Panelr_Helpers::service_panelr_name($sid); ?>
						<button type="button" class="button panelr-chip" data-service="<?php echo (int) $sid; ?>"><?php echo esc_html($shown ?: __('No service', 'panelr-for-woocommerce')); ?><?php if ($shown && $real && $shown !== $real): ?> <span class="panelr-chip__real"><?php echo esc_html($real); ?></span><?php endif; ?></button>
					<?php endforeach; ?>
				</p>
			<?php endif; ?>
			<table class="widefat striped panelr-products-table" id="panelr-products-table">
				<thead><tr>
					<th><?php esc_html_e('For sale', 'panelr-for-woocommerce'); ?></th>
					<th><?php esc_html_e('Plan', 'panelr-for-woocommerce'); ?></th>
					<th><?php esc_html_e('Service', 'panelr-for-woocommerce'); ?></th>
					<th><?php esc_html_e('Connections', 'panelr-for-woocommerce'); ?></th>
					<th><?php esc_html_e('Term', 'panelr-for-woocommerce'); ?></th>
					<th><?php esc_html_e('Price', 'panelr-for-woocommerce'); ?></th>
					<th><?php esc_html_e('Credits', 'panelr-for-woocommerce'); ?></th>
					<th></th>
				</tr></thead>
				<tbody>
				<?php foreach ($products as $p): ?>
					<tr data-service="<?php echo (int) $p['plugin_id']; ?>" data-product-id="<?php echo (int) $p['wc_id']; ?>">
						<td class="panelr-onoff-cell">
							<?php if ($p['is_trial']): ?>
								<span class="panelr-pill"><?php esc_html_e('Trial plan', 'panelr-for-woocommerce'); ?></span>
							<?php else: ?>
								<label class="panelr-switch">
									<input type="checkbox" class="panelr-product-toggle" <?php checked($p['status'], 'publish'); ?>>
									<span class="panelr-switch__track"></span>
									<span class="panelr-switch__label"><?php echo $p['status'] === 'publish' ? esc_html__('On', 'panelr-for-woocommerce') : esc_html__('Off', 'panelr-for-woocommerce'); ?></span>
								</label>
							<?php endif; ?>
						</td>
						<td><span title="<?php echo esc_attr(sprintf(
							/* translators: %d: Panelr plan number */
							__('Plan number %d', 'panelr-for-woocommerce'),
							$p['panelr_id']
						)); ?>"><?php echo esc_html($p['name']); ?></span>
							<?php if ($p['panelr_name'] !== '' && $p['panelr_name'] !== $p['name']): ?>
								<span class="panelr-real-name"><?php echo esc_html(sprintf(
									/* translators: %s: the plan's name in Panelr */
									__('In Panelr: %s', 'panelr-for-woocommerce'),
									$p['panelr_name']
								)); ?></span>
							<?php endif; ?>
						</td>
						<td><?php
							$shown = Panelr_Helpers::service_name($p['plugin_id']);
							$real  = Panelr_Helpers::service_panelr_name($p['plugin_id']);
							echo esc_html($shown ?: '—');
							if ($shown && $real && $shown !== $real) {
								echo '<span class="panelr-real-name">' . esc_html(sprintf(
									/* translators: %s: the service's name in Panelr */
									__('In Panelr: %s', 'panelr-for-woocommerce'),
									$real
								)) . '</span>';
							}
						?></td>
						<td><?php echo (int) $p['connections']; ?></td>
						<td><?php
							/* translators: %d: number of months */
							echo esc_html(sprintf(_n('%d month', '%d months', $p['duration_months'], 'panelr-for-woocommerce'), $p['duration_months'])); ?></td>
						<td><?php echo wp_kses_post(wc_price($p['price'])); ?></td>
						<td><?php echo $p['cost_points'] ? (int) $p['cost_points'] : '—'; ?></td>
						<td><a href="<?php echo esc_url(get_edit_post_link($p['wc_id'])); ?>"><?php esc_html_e('Edit', 'panelr-for-woocommerce'); ?></a></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<form method="post" action="options.php">
			<?php settings_fields('panelr_services'); ?>
			<table class="form-table" role="presentation">
				<?php self::field_open(__('Sync', 'panelr-for-woocommerce')); ?>
					<?php self::checkbox('panelr_sync_overwrite_edits', __('Replace my own edits with Panelr\'s values on every sync', 'panelr-for-woocommerce'), '0', __('Off: a plan you renamed, repriced or re-described in WooCommerce keeps your version; Panelr\'s value is only applied while you have not touched it. On: every sync copies Panelr\'s name, price and description over yours, the way version 1 did.', 'panelr-for-woocommerce')); ?>
				<?php self::field_close(); ?>
				<?php self::field_open(__('Categories', 'panelr-for-woocommerce')); ?>
					<?php self::checkbox('panelr_product_categories', __('File each plan under a product category named after its service', 'panelr-for-woocommerce'), '1', __('Creates one WooCommerce product category per service (for example "Demo Service") and puts that service\'s plans in it, so your theme can list plans per service and shop links can point at one service. Switch off to manage categories yourself.', 'panelr-for-woocommerce')); ?>
				<?php self::field_close(); ?>
			</table>
			<?php submit_button(__('Save', 'panelr-for-woocommerce')); ?>
		</form>
		<?php
	}

	// ── Tab: Pages ────────────────────────────────────────────────────────

	public static function tab_pages(): void
	{
		$pages = get_pages(['sort_column' => 'post_title', 'sort_order' => 'ASC']);
		?>
		<p class="panelr-input-row">
			<button type="button" id="panelr-create-pages" class="button button-primary"><?php esc_html_e('Create the missing pages', 'panelr-for-woocommerce'); ?></button>
			<span id="panelr-create-pages-result" class="panelr-inline-result" aria-live="polite"></span>
		</p>
		<form method="post" action="options.php">
			<?php settings_fields('panelr_pages'); ?>
			<table class="form-table" role="presentation">
				<?php foreach (self::page_defs() as $key => $def):
					$current = (int) get_option($def['option'], 0); ?>
					<tr>
						<th scope="row"><label for="panelr-page-<?php echo esc_attr($key); ?>"><?php echo esc_html($def['label']); ?></label><br><code class="panelr-shortcode"><?php echo esc_html($def['shortcode']); ?></code></th>
						<td>
							<select id="panelr-page-<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($def['option']); ?>" class="panelr-page-select" data-key="<?php echo esc_attr($key); ?>">
								<option value="0"><?php esc_html_e('— Choose a page —', 'panelr-for-woocommerce'); ?></option>
								<?php foreach ($pages as $p): ?>
									<option value="<?php echo (int) $p->ID; ?>" <?php selected($current, $p->ID); ?>><?php echo esc_html($p->post_title); ?></option>
								<?php endforeach; ?>
							</select>
							<?php if ($current && get_post_status($current)): ?>
								&nbsp;<a href="<?php echo esc_url(get_permalink($current)); ?>" target="_blank" rel="noopener"><?php esc_html_e('View', 'panelr-for-woocommerce'); ?></a>
								&nbsp;<a href="<?php echo esc_url(get_edit_post_link($current)); ?>"><?php esc_html_e('Edit', 'panelr-for-woocommerce'); ?></a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
			<?php submit_button(__('Save', 'panelr-for-woocommerce')); ?>
		</form>

		<h2><?php esc_html_e('Shortcodes', 'panelr-for-woocommerce'); ?></h2>
		<p><?php esc_html_e('Put any of these on any page. The pages above are the ones Panelr and the plugin link to; these two can go anywhere you like, as often as you like.', 'panelr-for-woocommerce'); ?></p>
		<table class="widefat striped panelr-products-table panelr-shortcodes">
			<thead><tr><th><?php esc_html_e('Shortcode', 'panelr-for-woocommerce'); ?></th><th><?php esc_html_e('What it shows', 'panelr-for-woocommerce'); ?></th><th></th></tr></thead>
			<tbody>
			<?php
			$services = Panelr_Helpers::services();
			$example  = $services ? (string) reset($services)['name'] : 'Service name';
			$rows = [
				['[panelr_plans service="' . $example . '"]', __('One service\'s plans as a pricing grid, each with an Add-to-cart button (and "Pay with credits" for members who can). Use the service\'s name as shown under Products.', 'panelr-for-woocommerce')],
				['[panelr_plans service="' . $example . '" columns="2"]', __('The same, two per row. columns can be 1 to 4.', 'panelr-for-woocommerce')],
				['[panelr_plans service="' . $example . '" ids="12,15"]', __('Only the plans with those Panelr plan numbers (the Products table shows them on hover of a plan name).', 'panelr-for-woocommerce')],
				['[panelr_plans]', __('Every service, grouped under its name.', 'panelr-for-woocommerce')],
				['[panelr_plans service="' . $example . '" heading="no"]', __('Without the service name above the grid, for a page that already has its own title.', 'panelr-for-woocommerce')],
				['[panelr_services]', __('The services as a list, each linking to its product category.', 'panelr-for-woocommerce')],
				['[products category="' . sanitize_title($example) . '"]', __('WooCommerce\'s own product grid for that service\'s category (each service\'s plans are filed in a category named after it when that setting is on).', 'panelr-for-woocommerce')],
			];
			foreach ($rows as $r): ?>
				<tr>
					<td><code class="panelr-shortcode"><?php echo esc_html($r[0]); ?></code></td>
					<td><?php echo esc_html($r[1]); ?></td>
					<td><button type="button" class="button panelr-copy-admin" data-copy="<?php echo esc_attr($r[0]); ?>"><?php esc_html_e('Copy', 'panelr-for-woocommerce'); ?></button></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	// ── Tab: Payments ─────────────────────────────────────────────────────

	public static function tab_payments(): void
	{
		$methods  = Panelr_Helpers::payment_methods();
		$map      = Panelr_Helpers::payment_map();
		$gateways = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : [];
		?>
		<form method="post" action="options.php">
			<?php settings_fields('panelr_payments'); ?>
			<h2><?php esc_html_e('Payment methods', 'panelr-for-woocommerce'); ?></h2>
			<?php $store_methods = $methods ? array_filter($methods, fn($pm) => ($pm['checkout_on'] ?? 'panelr') === 'store') : []; ?>
			<?php if ($store_methods): ?>
				<p><strong><?php esc_html_e('Paid on this store', 'panelr-for-woocommerce'); ?></strong>
					<?php self::help(__('These Panelr payment methods are set to "On the connected store" in Panelr → Settings → Payments. A customer who picks one on Panelr is sent here to pay, and only the WooCommerce methods mapped to it are offered. The receiving page is set under Pages ("Checkout from Panelr") and its address goes into Panelr → Settings → Website → Checkout.', 'panelr-for-woocommerce')); ?></p>
				<ul class="panelr-store-methods">
					<?php foreach ($store_methods as $pm):
						$mapped = array_keys(array_filter($map, fn($v) => (int) $v === (int) $pm['id']));
						$names  = [];
						foreach ($mapped as $gid) { if (isset($gateways[$gid])) $names[] = $gateways[$gid]->get_title(); }
						?>
						<li><?php echo esc_html($pm['display_label'] ?: $pm['name']); ?> &rarr; <?php echo $names ? esc_html(implode(', ', $names)) : '<span class="panelr-pill panelr-pill--warn">' . esc_html__('Not mapped yet', 'panelr-for-woocommerce') . '</span>'; ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php if ($methods === null): ?>
				<p><?php esc_html_e('Panelr\'s payment methods could not be read. Check the connection.', 'panelr-for-woocommerce'); ?></p>
			<?php else: ?>
				<table class="widefat striped panelr-products-table">
					<thead><tr><th><?php esc_html_e('WooCommerce', 'panelr-for-woocommerce'); ?></th><th><?php esc_html_e('Panelr', 'panelr-for-woocommerce'); ?></th></tr></thead>
					<tbody>
					<?php foreach ($gateways as $gw):
						if ($gw->enabled !== 'yes' || $gw->id === Panelr_Credits_Gateway::ID) continue; ?>
						<tr>
							<td><?php echo esc_html($gw->get_title()); ?></td>
							<td>
								<select name="panelr_payment_map[<?php echo esc_attr($gw->id); ?>]">
									<option value=""><?php esc_html_e('— Not mapped —', 'panelr-for-woocommerce'); ?></option>
									<?php foreach ($methods as $pm): ?>
										<option value="<?php echo (int) $pm['id']; ?>" <?php selected((int) ($map[$gw->id] ?? 0), (int) $pm['id']); ?>>
											<?php echo esc_html(($pm['display_label'] ?: $pm['name']) . ' · ' . (($pm['mode'] ?? '') === 'automated' ? __('automatic', 'panelr-for-woocommerce') : __('manual', 'panelr-for-woocommerce'))); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<table class="form-table" role="presentation">
				<?php self::field_open(__('Discount codes come from', 'panelr-for-woocommerce')); ?>
					<label><input type="radio" name="panelr_coupon_source" value="woocommerce" <?php checked(get_option('panelr_coupon_source', 'woocommerce'), 'woocommerce'); ?>> <?php esc_html_e('WooCommerce', 'panelr-for-woocommerce'); ?></label><br>
					<label><input type="radio" name="panelr_coupon_source" value="panelr" <?php checked(get_option('panelr_coupon_source', 'woocommerce'), 'panelr'); ?>> <?php esc_html_e('Panelr', 'panelr-for-woocommerce'); ?></label>
					<?php self::help(__('One source of discount per order. WooCommerce: the usual WooCommerce coupons apply and Panelr receives the amount charged. Panelr: the cart shows a code box checked against Panelr\'s coupons, and the code is sent with the order so Panelr records it. Never both.', 'panelr-for-woocommerce')); ?>
				<?php self::field_close(); ?>
				<?php self::field_open(__('Orders', 'panelr-for-woocommerce')); ?>
					<?php self::checkbox('panelr_auto_complete_orders', __('Mark the WooCommerce order Completed when Panelr has set the service up', 'panelr-for-woocommerce'), '1', __('The plugin checks each sent order with Panelr at 1, 2, 5, 15 and 60 minutes, then every six hours for a week. When Panelr reports the connection is ready, the WooCommerce order moves to Completed. Switch off to move orders by hand.', 'panelr-for-woocommerce')); ?>
				<?php self::field_close(); ?>
			</table>
			<?php submit_button(__('Save', 'panelr-for-woocommerce')); ?>
		</form>
		<?php
	}

	// ── Tab: Trials ───────────────────────────────────────────────────────

	public static function tab_trials(): void
	{
		$services = Panelr_Trial::trial_services();
		?>
		<form method="post" action="options.php">
			<?php settings_fields('panelr_trials'); ?>
			<table class="form-table" role="presentation">
				<?php self::field_open(__('Free trials', 'panelr-for-woocommerce')); ?>
					<?php self::checkbox('panelr_trials_enabled', __('Show the trial form', 'panelr-for-woocommerce'), '0'); ?>
					<?php if ($services): ?>
						<p><?php echo esc_html(sprintf(
							/* translators: %s: service names */
							__('Offering trials: %s', 'panelr-for-woocommerce'),
							implode(', ', array_map(fn($s) => $s['name'], $services))
						)); ?></p>
					<?php else: ?>
						<p><?php esc_html_e('No service is offering a trial in Panelr right now.', 'panelr-for-woocommerce'); ?></p>
					<?php endif; ?>
				<?php self::field_close(); ?>
				<?php self::field_open(__('Turnstile site key', 'panelr-for-woocommerce'), 'panelr_turnstile_site_key'); ?>
					<input type="text" id="panelr_turnstile_site_key" name="panelr_turnstile_site_key" class="regular-text" value="<?php echo esc_attr(get_option('panelr_turnstile_site_key', '')); ?>">
				<?php self::field_close(); ?>
				<?php self::field_open(__('Turnstile secret key', 'panelr-for-woocommerce'), 'panelr_turnstile_secret_key'); ?>
					<div class="panelr-input-row">
						<?php if (get_option('panelr_turnstile_secret_key', '')): ?><span class="panelr-pill panelr-pill--ok"><?php esc_html_e('Saved', 'panelr-for-woocommerce'); ?></span><?php endif; ?>
						<input type="password" id="panelr_turnstile_secret_key" name="panelr_turnstile_secret_key" class="regular-text" value="" autocomplete="new-password" placeholder="<?php echo get_option('panelr_turnstile_secret_key', '') ? esc_attr__('Replace key', 'panelr-for-woocommerce') : ''; ?>">
					</div>
				<?php self::field_close(); ?>
			</table>
			<?php submit_button(__('Save', 'panelr-for-woocommerce')); ?>
		</form>
		<?php
	}

	// ── Tab: Member area ──────────────────────────────────────────────────

	public static function tab_member(): void
	{
		?>
		<form method="post" action="options.php">
			<?php settings_fields('panelr_member'); ?>
			<table class="form-table" role="presentation">
				<?php self::field_open(__('Look', 'panelr-for-woocommerce'), 'panelr_theme'); ?>
					<select id="panelr_theme" name="panelr_theme">
						<option value="none" <?php selected(get_option('panelr_theme', 'none'), 'none'); ?>><?php esc_html_e('My theme\'s styles', 'panelr-for-woocommerce'); ?></option>
						<option value="light" <?php selected(get_option('panelr_theme', 'none'), 'light'); ?>><?php esc_html_e('Panelr light', 'panelr-for-woocommerce'); ?></option>
						<option value="dark" <?php selected(get_option('panelr_theme', 'none'), 'dark'); ?>><?php esc_html_e('Panelr dark', 'panelr-for-woocommerce'); ?></option>
					</select>
				<?php self::field_close(); ?>
				<?php self::field_open(__('Channels', 'panelr-for-woocommerce')); ?>
					<?php self::checkbox('panelr_portal_allow_bouquets', __('Members can choose their channel packages', 'panelr-for-woocommerce'), '0', __('Adds a Channels button to each connection in the member area. Only services that allow channel choices in Panelr show it.', 'panelr-for-woocommerce')); ?>
				<?php self::field_close(); ?>
				<?php self::field_open(__('WordPress accounts', 'panelr-for-woocommerce')); ?>
					<?php self::checkbox('panelr_wp_user_link', __('Remember the Panelr account of a signed-in WordPress user', 'panelr-for-woocommerce'), '0', __('When a visitor is logged in to WordPress with the same email as their Panelr account, the member area opens without a second sign-in. No WordPress users are ever created.', 'panelr-for-woocommerce')); ?>
				<?php self::field_close(); ?>
				<?php self::field_open(__('Stay signed in for', 'panelr-for-woocommerce'), 'panelr_session_days'); ?>
					<input type="number" id="panelr_session_days" name="panelr_session_days" min="1" max="90" value="<?php echo (int) get_option('panelr_session_days', 7); ?>" class="small-text"> <?php esc_html_e('days', 'panelr-for-woocommerce'); ?>
					<?php self::help(__('How long a member stays signed in to the member area before being asked again.', 'panelr-for-woocommerce')); ?>
				<?php self::field_close(); ?>
				<?php self::field_open(__('Checkout needs an account', 'panelr-for-woocommerce'), 'panelr_checkout_account'); ?>
					<select id="panelr_checkout_account" name="panelr_checkout_account">
						<option value="auto" <?php selected(get_option('panelr_checkout_account', 'auto'), 'auto'); ?>><?php echo esc_html(Panelr_Helpers::accounts_enabled() ? __('Same as Panelr (yes)', 'panelr-for-woocommerce') : __('Same as Panelr (no)', 'panelr-for-woocommerce')); ?></option>
						<option value="on" <?php selected(get_option('panelr_checkout_account', 'auto'), 'on'); ?>><?php esc_html_e('Yes', 'panelr-for-woocommerce'); ?></option>
						<option value="off" <?php selected(get_option('panelr_checkout_account', 'auto'), 'off'); ?>><?php esc_html_e('No', 'panelr-for-woocommerce'); ?></option>
					</select>
					<?php self::help(__('Yes: the checkout page asks for an account password. A new email gets its Panelr account right there (with the invite code it arrived with); an email that already has an account must give that account\'s password. Credits, invite codes and the member area all belong to the account, which is why Panelr\'s own checkout insists on it. Orders handed over by Panelr\'s checkout skip this — the account already exists.', 'panelr-for-woocommerce')); ?>
				<?php self::field_close(); ?>
				<?php self::field_open(__('Creating an account needs an invite code', 'panelr-for-woocommerce'), 'panelr_require_invite'); ?>
					<select id="panelr_require_invite" name="panelr_require_invite">
						<option value="auto" <?php selected(get_option('panelr_require_invite', 'auto'), 'auto'); ?>><?php echo esc_html(Panelr_Helpers::require_invite() ? __('Same as Panelr (yes)', 'panelr-for-woocommerce') : __('Same as Panelr (no)', 'panelr-for-woocommerce')); ?></option>
						<option value="on" <?php selected(get_option('panelr_require_invite', 'auto'), 'on'); ?>><?php esc_html_e('Yes', 'panelr-for-woocommerce'); ?></option>
						<option value="off" <?php selected(get_option('panelr_require_invite', 'auto'), 'off'); ?>><?php esc_html_e('No', 'panelr-for-woocommerce'); ?></option>
					</select>
					<?php self::help(__('Yes: the Create account form needs a valid invite code from a member, the way a private Panelr site with an invite-code door works. Same as Panelr follows whatever your Panelr site does.', 'panelr-for-woocommerce')); ?>
				<?php self::field_close(); ?>
			</table>
			<?php submit_button(__('Save', 'panelr-for-woocommerce')); ?>
		</form>
		<?php
	}

	// ── Tab: Support ──────────────────────────────────────────────────────

	public static function tab_support(): void
	{
		$page = (int) get_option('panelr_page_support', 0);
		?>
		<form method="post" action="options.php">
			<?php settings_fields('panelr_support'); ?>
			<table class="form-table" role="presentation">
				<?php self::field_open(__('Support pages', 'panelr-for-woocommerce')); ?>
					<label><input type="radio" name="panelr_support_pages" value="panelr" <?php checked(get_option('panelr_support_pages', 'panelr'), 'panelr'); ?>> <?php esc_html_e('Panelr\'s', 'panelr-for-woocommerce'); ?></label><br>
					<label><input type="radio" name="panelr_support_pages" value="site" <?php checked(get_option('panelr_support_pages', 'panelr'), 'site'); ?>> <?php esc_html_e('This site', 'panelr-for-woocommerce'); ?></label>
				<?php self::field_close(); ?>
				<?php self::field_open(__('Support page', 'panelr-for-woocommerce')); ?>
					<?php if ($page && get_post_status($page)): ?>
						<a href="<?php echo esc_url(get_permalink($page)); ?>" target="_blank" rel="noopener"><?php echo esc_html(get_the_title($page)); ?></a>
					<?php else: ?>
						<a href="<?php echo esc_url(admin_url('admin.php?page=panelr&tab=pages')); ?>"><?php esc_html_e('Choose or create it under Pages', 'panelr-for-woocommerce'); ?></a>
					<?php endif; ?>
				<?php self::field_close(); ?>
				<?php self::field_open(__('In Panelr', 'panelr-for-woocommerce')); ?>
					<?php echo Panelr_Helpers::support_enabled() ? esc_html__('Support is on', 'panelr-for-woocommerce') : esc_html__('Support is off', 'panelr-for-woocommerce'); ?>
				<?php self::field_close(); ?>
			</table>
			<?php submit_button(__('Save', 'panelr-for-woocommerce')); ?>
		</form>
		<?php
	}

	// ── Tab: Apps ─────────────────────────────────────────────────────────

	public static function tab_apps(): void
	{
		$page = (int) get_option('panelr_page_apps', 0);
		$mode = Panelr_Helpers::apps_page_mode();
		$labels = ['off' => __('Off', 'panelr-for-woocommerce'), 'members' => __('Members only', 'panelr-for-woocommerce'), 'public' => __('Everyone', 'panelr-for-woocommerce')];
		?>
		<table class="form-table" role="presentation">
			<?php self::field_open(__('In Panelr', 'panelr-for-woocommerce')); ?>
				<?php echo esc_html($labels[$mode] ?? $mode); ?>
				<?php if (Panelr_Helpers::admin_panelr_url()): ?>
					&nbsp;<a href="<?php echo esc_url(Panelr_Helpers::admin_panelr_url('apps.php')); ?>" target="_blank" rel="noopener"><?php esc_html_e('Change in Panelr', 'panelr-for-woocommerce'); ?></a>
				<?php endif; ?>
			<?php self::field_close(); ?>
			<?php self::field_open(__('Apps page', 'panelr-for-woocommerce')); ?>
				<?php if ($page && get_post_status($page)): ?>
					<a href="<?php echo esc_url(get_permalink($page)); ?>" target="_blank" rel="noopener"><?php echo esc_html(get_the_title($page)); ?></a>
				<?php else: ?>
					<a href="<?php echo esc_url(admin_url('admin.php?page=panelr&tab=pages')); ?>"><?php esc_html_e('Choose or create it under Pages', 'panelr-for-woocommerce'); ?></a>
				<?php endif; ?>
			<?php self::field_close(); ?>
		</table>
		<?php
	}

	// ── Tab: Bots ─────────────────────────────────────────────────────────

	public static function tab_bots(): void
	{
		?>
		<form method="post" action="options.php">
			<?php settings_fields('panelr_bots'); ?>
			<table class="form-table" role="presentation">
				<?php self::field_open(__('Bots', 'panelr-for-woocommerce')); ?>
					<?php self::checkbox('panelr_bots_enabled', __('Show the bot buttons in the member area', 'panelr-for-woocommerce'), '0'); ?>
				<?php self::field_close(); ?>
				<?php self::field_open(__('Telegram bot link', 'panelr-for-woocommerce'), 'panelr_bot_telegram_url'); ?>
					<input type="url" id="panelr_bot_telegram_url" name="panelr_bot_telegram_url" class="regular-text" value="<?php echo esc_attr(get_option('panelr_bot_telegram_url', '')); ?>" placeholder="https://t.me/…">
				<?php self::field_close(); ?>
				<?php self::field_open(__('Discord invite link', 'panelr-for-woocommerce'), 'panelr_bot_discord_url'); ?>
					<input type="url" id="panelr_bot_discord_url" name="panelr_bot_discord_url" class="regular-text" value="<?php echo esc_attr(get_option('panelr_bot_discord_url', '')); ?>" placeholder="https://discord.gg/…">
				<?php self::field_close(); ?>
				<?php self::field_open(__('Site footer', 'panelr-for-woocommerce')); ?>
					<?php self::checkbox('panelr_bots_footer', __('Show the bot buttons in the site footer', 'panelr-for-woocommerce'), '0', __('A small bar with the Telegram and Discord buttons at the bottom of every page.', 'panelr-for-woocommerce')); ?>
				<?php self::field_close(); ?>
			</table>
			<?php submit_button(__('Save', 'panelr-for-woocommerce')); ?>
		</form>
		<?php
	}

	// ── Tab: Advanced ─────────────────────────────────────────────────────

	public static function tab_advanced(): void
	{
		$headers = [
			'HTTP_CF_CONNECTING_IP' => 'CF-Connecting-IP (Cloudflare)',
			'HTTP_X_REAL_IP'        => 'X-Real-IP',
			'HTTP_X_FORWARDED_FOR'  => 'X-Forwarded-For',
			'HTTP_TRUE_CLIENT_IP'   => 'True-Client-IP',
		];
		$log_url = admin_url('admin.php?page=wc-status&tab=logs&source=panelr');
		?>
		<form method="post" action="options.php">
			<?php settings_fields('panelr_advanced'); ?>
			<table class="form-table" role="presentation">
				<?php self::field_open(__('Visitor addresses', 'panelr-for-woocommerce')); ?>
					<?php self::checkbox('panelr_proxy_enabled', __('Site is behind a proxy or Cloudflare', 'panelr-for-woocommerce'), '0', __('Trial requests send the visitor\'s address to Panelr for screening. Behind a proxy the real address arrives in a header; switch this on and choose the header, otherwise the header is ignored so it cannot be faked.', 'panelr-for-woocommerce')); ?>
				<?php self::field_close(); ?>
				<?php self::field_open(__('Header carrying the visitor address', 'panelr-for-woocommerce'), 'panelr_proxy_header'); ?>
					<select id="panelr_proxy_header" name="panelr_proxy_header">
						<?php foreach ($headers as $key => $label): ?>
							<option value="<?php echo esc_attr($key); ?>" <?php selected(get_option('panelr_proxy_header', 'HTTP_CF_CONNECTING_IP'), $key); ?>><?php echo esc_html($label); ?></option>
						<?php endforeach; ?>
					</select>
				<?php self::field_close(); ?>
				<?php self::field_open(__('When the plugin is deleted', 'panelr-for-woocommerce')); ?>
					<?php self::checkbox('panelr_uninstall_draft_products', __('Set the synced plans to draft', 'panelr-for-woocommerce'), '0', __('Only when the plugin is deleted from the Plugins page. Orders and pages are never touched.', 'panelr-for-woocommerce')); ?>
				<?php self::field_close(); ?>
				<?php self::field_open(__('Log', 'panelr-for-woocommerce')); ?>
					<a href="<?php echo esc_url($log_url); ?>"><?php esc_html_e('Open the Panelr log', 'panelr-for-woocommerce'); ?></a>
				<?php self::field_close(); ?>
				<?php self::field_open(__('Version', 'panelr-for-woocommerce')); ?>
					<?php echo esc_html(PANELR_VERSION); ?>
				<?php self::field_close(); ?>
			</table>
			<?php submit_button(__('Save', 'panelr-for-woocommerce')); ?>
		</form>
		<?php
	}

	// ── Customers (link out) ──────────────────────────────────────────────

	public static function render_customers(): void
	{
		$url = Panelr_Helpers::admin_panelr_url('customers.php');
		echo '<div class="wrap panelr-settings-wrap"><h1>' . esc_html__('Customers', 'panelr-for-woocommerce') . '</h1><div class="panelr-settings-section">';
		echo '<p>' . esc_html__('Customers, their connections, credits and tickets live in Panelr.', 'panelr-for-woocommerce') . '</p>';
		if ($url) {
			echo '<p><a class="button button-primary" href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html__('Open Customers in Panelr', 'panelr-for-woocommerce') . '</a></p>';
		}
		echo '</div></div>';
	}
}
