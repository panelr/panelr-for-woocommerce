<?php
defined('ABSPATH') || exit;

class Panelr_Settings
{

	public static function init(): void
	{
		add_action('admin_menu',            [__CLASS__, 'add_menu']);
		add_action('admin_init',            [__CLASS__, 'register_settings']);
		add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
		add_action('init',                  [__CLASS__, 'register_ajax_hooks']);
	}

	public static function register_ajax_hooks(): void
	{
		add_action('wp_ajax_panelr_test_connection',          [__CLASS__, 'ajax_test_connection']);
		add_action('wp_ajax_panelr_refresh_payment_methods',  [__CLASS__, 'ajax_refresh_payment_methods']);
		add_action('wp_ajax_panelr_create_pages',             [__CLASS__, 'ajax_create_pages']);
		add_action('wp_ajax_panelr_save_payment_map',         [__CLASS__, 'ajax_save_payment_map']);
		add_action('wp_ajax_panelr_save_pages',               [__CLASS__, 'ajax_save_pages']);
		add_action('wp_ajax_panelr_save_trial_product',       [__CLASS__, 'ajax_save_trial_product']);
		add_action('wp_ajax_panelr_save_portal_settings',     [__CLASS__, 'ajax_save_portal_settings']);
		add_action('wp_ajax_panelr_save_trial_settings',      [__CLASS__, 'ajax_save_trial_settings']);
		add_action('wp_ajax_panelr_sync_products',            [__CLASS__, 'ajax_sync_products']);
	}

	public static function add_menu(): void
	{
		$icon = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><rect width="20" height="20" rx="4" fill="#1a1a2e"/><text x="10" y="14" text-anchor="middle" font-family="sans-serif" font-weight="700" font-size="10" fill="#f0a050">pr</text></svg>');
		add_options_page('Panelr', 'Panelr', 'manage_options', 'panelr-settings', [__CLASS__, 'render_page'], $icon);
	}

	public static function register_settings(): void
	{
		register_setting('panelr_settings_group', 'panelr_api_url', [
			'sanitize_callback' => 'esc_url_raw',
		]);
		register_setting('panelr_settings_group', 'panelr_api_key', [
			'sanitize_callback' => 'sanitize_text_field',
		]);
	}

	public static function enqueue_assets(string $hook): void
	{
		if ($hook !== 'settings_page_panelr-settings') return;
		wp_enqueue_script('panelr-admin', PANELR_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], PANELR_VERSION, true);
		wp_localize_script('panelr-admin', 'panelrAdmin', [
			'ajaxurl' => admin_url('admin-ajax.php'),
			'nonce'   => wp_create_nonce('panelr_admin_nonce'),
		]);
		wp_enqueue_style('panelr-admin', PANELR_PLUGIN_URL . 'assets/css/admin.css', [], PANELR_VERSION);
	}

	// ── AJAX: test connection ──────────────────────────────────────────────

	public static function ajax_test_connection(): void
	{
		check_ajax_referer('panelr_admin_nonce', 'nonce');
		if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Unauthorized']);
		$api    = new Panelr_API();
		$result = $api->test_connection();
		if ($result) {
			wp_send_json_success(['message' => 'Connection successful.']);
		} else {
			wp_send_json_error(['message' => 'Connection failed. Check your API URL and key.']);
		}
	}

	// ── AJAX: sync products ────────────────────────────────────────────────

	public static function ajax_sync_products(): void
	{
		check_ajax_referer('panelr_admin_nonce', 'nonce');
		if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Unauthorized']);
		$result = Panelr_Sync::sync();
		if ($result['success']) {
			update_option('panelr_last_sync', current_time('mysql'));
			wp_send_json_success($result);
		} else {
			wp_send_json_error($result);
		}
	}

	// ── AJAX: save trial product ───────────────────────────────────────────

	public static function ajax_save_trial_product(): void
	{
		check_ajax_referer('panelr_admin_nonce', 'nonce');
		if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Unauthorized']);
		$product_id = (int) sanitize_text_field(
			wp_unslash($_POST['trial_product_id'] ?? $_POST['panelr_product_id'] ?? 0)
		);
		update_option('panelr_trial_product_id', $product_id);
		Panelr_Sync::update_trial_product_visibility($product_id);
		wp_send_json_success(['message' => 'Trial product saved.']);
	}

	// ── AJAX: refresh payment methods ──────────────────────────────────────

	public static function ajax_refresh_payment_methods(): void
	{
		check_ajax_referer('panelr_admin_nonce', 'nonce');
		if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Unauthorized']);
		$api    = new Panelr_API();
		$result = $api->get_payment_methods();
		if (empty($result['success']) || empty($result['data'])) {
			wp_send_json_error(['message' => $result['error'] ?? 'Could not load payment methods.']);
		}

		// Load only enabled WC gateways via WooCommerce's own registry
		$registered      = [];
		$all_gateways    = WC()->payment_gateways()->payment_gateways();
		foreach ($all_gateways as $gateway) {
			if ($gateway->enabled !== 'yes') continue;
			$registered[] = ['id' => $gateway->id, 'title' => $gateway->get_title()];
		}

		$map      = json_decode((string) get_option('panelr_payment_map', '{}'), true) ?: [];
		$mode_map = json_decode((string) get_option('panelr_payment_mode_map', '{}'), true) ?: [];
		foreach ($result['data'] ?? [] as $pm) {
			$mode_map[$pm['id']] = $pm['mode'] ?? 'manual';
		}
		update_option('panelr_payment_mode_map', wp_json_encode($mode_map));
		wp_send_json_success([
			'methods'  => $result['data'] ?? [],
			'gateways' => $registered,
			'map'      => $map,
		]);
	}

	// ── AJAX: save payment map ─────────────────────────────────────────────

	public static function ajax_save_payment_map(): void
	{
		check_ajax_referer('panelr_admin_nonce', 'nonce');
		if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Unauthorized']);

		$raw_map = array_map(
			'sanitize_text_field',
			wp_unslash($_POST['payment_map'] ?? $_POST['map'] ?? [])
		);

		if (!is_array($raw_map)) wp_send_json_error(['message' => 'Invalid data.']);

		$clean = [];
		foreach ($raw_map as $gateway_id => $panelr_id) {
			$clean[sanitize_key($gateway_id)] = (int) $panelr_id;
		}

		update_option('panelr_payment_map', wp_json_encode($clean));
		wp_send_json_success(['message' => 'Payment mapping saved.']);
	}

	// ── AJAX: create pages ─────────────────────────────────────────────────

	public static function ajax_create_pages(): void
	{
		check_ajax_referer('panelr_admin_nonce', 'nonce');
		if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Unauthorized']);
		$defs = [
			'portal'       => ['option' => 'panelr_page_portal',       'title' => 'My Account',   'shortcode' => '[panelr_portal]'],
			'trial'        => ['option' => 'panelr_page_trial',        'title' => 'Free Trial',    'shortcode' => '[panelr_trial]'],
			'upgrade'      => ['option' => 'panelr_page_upgrade',      'title' => 'Upgrade Trial', 'shortcode' => '[panelr_upgrade]'],
			'order_status' => ['option' => 'panelr_page_order_status', 'title' => 'Order Status',  'shortcode' => '[panelr_order_status]'],
		];
		$result = [];
		foreach ($defs as $key => $def) {
			if (get_option($def['option'])) {
				$result[$key] = ['status' => 'exists'];
				continue;
			}
			$page_id = wp_insert_post([
				'post_title'   => $def['title'],
				'post_content' => $def['shortcode'],
				'post_status'  => 'publish',
				'post_type'    => 'page',
			]);
			if (is_wp_error($page_id)) {
				wp_send_json_error(['message' => 'Failed to create page: ' . $def['title']]);
			}
			update_option($def['option'], $page_id);
			$result[$key] = ['id' => $page_id, 'title' => $def['title'], 'url' => get_permalink($page_id), 'status' => 'created'];
		}
		wp_send_json_success(['pages' => $result]);
	}

	// ── AJAX: save pages ───────────────────────────────────────────────────

	public static function ajax_save_pages(): void
	{
		check_ajax_referer('panelr_admin_nonce', 'nonce');
		if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Unauthorized']);
		$map = ['portal' => 'panelr_page_portal', 'trial' => 'panelr_page_trial', 'upgrade' => 'panelr_page_upgrade', 'order_status' => 'panelr_page_order_status'];
		foreach ($map as $key => $option) {
			if (isset($_POST[$key])) {
				update_option($option, (int) sanitize_text_field(wp_unslash($_POST[$key])));
			}
		}
		wp_send_json_success(['message' => 'Pages saved.']);
	}

	// ── AJAX: save portal settings ─────────────────────────────────────────

	public static function ajax_save_portal_settings(): void
	{
		check_ajax_referer('panelr_admin_nonce', 'nonce');
		if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Unauthorized']);
		$allow_bouquets = isset($_POST['allow_bouquets']) && sanitize_text_field(wp_unslash($_POST['allow_bouquets'])) === '1' ? '1' : '0';
		update_option('panelr_portal_allow_bouquets', $allow_bouquets);
		$theme = sanitize_key(wp_unslash($_POST['theme'] ?? 'none'));
		if (!in_array($theme, ['none', 'light', 'dark'], true)) $theme = 'none';
		update_option('panelr_theme', $theme);
		wp_send_json_success(['message' => 'Portal settings saved.']);
	}

	// ── AJAX: save trial settings ──────────────────────────────────────────

	public static function ajax_save_trial_settings(): void
	{
		check_ajax_referer('panelr_admin_nonce', 'nonce');
		if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Unauthorized']);
		$trials_enabled = isset($_POST['trials_enabled']) && sanitize_text_field(wp_unslash($_POST['trials_enabled'])) === '1' ? '1' : '0';
		update_option('panelr_trials_enabled', $trials_enabled);
		wp_send_json_success(['message' => 'Trial settings saved.']);
	}

	// ── Render ─────────────────────────────────────────────────────────────

	public static function render_page(): void
	{
		if (!current_user_can('manage_options')) return;

		$api_url           = get_option('panelr_api_url', '');
		$api_key           = get_option('panelr_api_key', '');
		$page_portal       = (int) get_option('panelr_page_portal',       0);
		$page_trial        = (int) get_option('panelr_page_trial',        0);
		$page_upgrade      = (int) get_option('panelr_page_upgrade',      0);
		$page_order_status = (int) get_option('panelr_page_order_status', 0);
		$all_pages         = get_pages(['sort_column' => 'post_title', 'sort_order' => 'ASC']);
		$synced            = Panelr_Sync::get_synced_products();
		$last_sync         = get_option('panelr_last_sync', '');
		$trial_product_id  = (int) get_option('panelr_trial_product_id', 0);
		$trials_enabled    = get_option('panelr_trials_enabled', '0');
		$allow_bouquets    = get_option('panelr_portal_allow_bouquets', '0');

		$page_defs = [
			'portal'       => ['label' => 'Customer Portal', 'shortcode' => '[panelr_portal]',       'description' => 'Login, credentials, bouquets, renewals.',                          'option' => 'panelr_page_portal',       'current' => $page_portal],
			'trial'        => ['label' => 'Free Trial',      'shortcode' => '[panelr_trial]',        'description' => 'Free trial request form.',                                         'option' => 'panelr_page_trial',        'current' => $page_trial],
			'upgrade'      => ['label' => 'Trial Upgrade',   'shortcode' => '[panelr_upgrade]',      'description' => 'Trial-to-paid upgrade page.',                                      'option' => 'panelr_page_upgrade',      'current' => $page_upgrade],
			'order_status' => ['label' => 'Order Status',    'shortcode' => '[panelr_order_status]', 'description' => 'Order lookup, payment confirmation, and bot checkout.',             'option' => 'panelr_page_order_status', 'current' => $page_order_status],
		];
?>
		<div class="wrap panelr-settings-wrap">

			<!-- ── Header ──────────────────────────────────────────────── -->
			<div class="panelr-settings-header">
				<a href="https://panelr.app" target="_blank" class="panelr-settings-logo">
					<img src="<?php echo esc_url(PANELR_PLUGIN_URL . 'assets/images/panelr-logo.png'); ?>" alt="Panelr" class="panelr-logo-img">
					<span class="panelr-logo-text"><?php esc_html_e('for WooCommerce', 'panelr-for-woocommerce'); ?></span>
				</a>
				<div class="panelr-settings-version">v<?php echo esc_html(PANELR_VERSION); ?></div>
			</div>

			<!-- ── 1. Connection ────────────────────────────────────────── -->
			<div class="panelr-settings-section">
				<h2><?php esc_html_e('🔗 Connection', 'panelr-for-woocommerce'); ?></h2>
				<p class="panelr-section-desc"><?php esc_html_e('Connect this store to your Panelr installation.', 'panelr-for-woocommerce'); ?></p>
				<form method="post" action="options.php">
					<?php settings_fields('panelr_settings_group'); ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="panelr_api_url"><?php esc_html_e('API URL', 'panelr-for-woocommerce'); ?></label></th>
							<td>
								<input type="url" id="panelr_api_url" name="panelr_api_url"
									value="<?php echo esc_attr($api_url); ?>"
									class="regular-text" placeholder="https://yourdomain.com">
								<p class="description"><?php esc_html_e('Base URL of your Panelr installation. No trailing slash.', 'panelr-for-woocommerce'); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="panelr_api_key"><?php esc_html_e('API Key', 'panelr-for-woocommerce'); ?></label></th>
							<td>
								<div class="panelr-input-row">
									<input type="password" id="panelr_api_key" name="panelr_api_key"
										value="<?php echo esc_attr($api_key); ?>" class="regular-text">
									<button type="button" id="panelr-toggle-key" class="button"><?php esc_html_e('Show', 'panelr-for-woocommerce'); ?></button>
									<button type="button" id="panelr-test-connection" class="button button-secondary"><?php esc_html_e('Test Connection', 'panelr-for-woocommerce'); ?></button>
									<span id="panelr-test-result" class="panelr-inline-result"></span>
								</div>
								<p class="description"><?php esc_html_e('Found in your Panelr admin under Settings → API.', 'panelr-for-woocommerce'); ?></p>
							</td>
						</tr>
					</table>
					<?php submit_button(__('Save Connection', 'panelr-for-woocommerce')); ?>
				</form>
			</div>

			<!-- ── 2. Products ──────────────────────────────────────────── -->
			<div class="panelr-settings-section">
				<h2><?php esc_html_e('📦 Products', 'panelr-for-woocommerce'); ?></h2>
				<p class="panelr-section-desc"><?php esc_html_e('Sync Panelr products to WooCommerce and configure the free trial product.', 'panelr-for-woocommerce'); ?></p>

				<p>
					<button type="button" id="panelr-sync-products" class="button button-primary"><?php esc_html_e('Sync Products from Panelr', 'panelr-for-woocommerce'); ?></button>
					<span id="panelr-sync-result" class="panelr-inline-result"></span>
					<?php if ($last_sync): ?>
						<span class="panelr-last-sync"><?php echo esc_html(sprintf(
															/* translators: %s: date and time of last sync */
															__('Last synced: %s', 'panelr-for-woocommerce'),
															$last_sync
														)); ?></span>
					<?php endif; ?>
				</p>

				<div id="panelr-sync-table-wrap">
					<?php if (!empty($synced)): ?>
						<table class="widefat striped panelr-products-table">
							<thead>
								<tr>
									<th><?php esc_html_e('Product', 'panelr-for-woocommerce'); ?></th>
									<th><?php esc_html_e('Panelr ID', 'panelr-for-woocommerce'); ?></th>
									<th><?php esc_html_e('Connections', 'panelr-for-woocommerce'); ?></th>
									<th><?php esc_html_e('Duration', 'panelr-for-woocommerce'); ?></th>
									<th><?php esc_html_e('WC Product', 'panelr-for-woocommerce'); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($synced as $sp): ?>
									<tr>
										<td><?php echo esc_html($sp['name']); ?></td>
										<td><code><?php echo (int) $sp['panelr_id']; ?></code></td>
										<td><?php echo (int) $sp['connections']; ?></td>
										<td><?php echo (int) $sp['duration_months']; ?> mo</td>
										<td><a href="<?php echo esc_url(get_edit_post_link($sp['wc_id'])); ?>" target="_blank">#<?php echo (int) $sp['wc_id']; ?> Edit</a></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
			</div>

			<!-- ── 3. Pages ─────────────────────────────────────────────── -->
			<div class="panelr-settings-section">
				<h2><?php esc_html_e('📄 Pages', 'panelr-for-woocommerce'); ?></h2>
				<p class="panelr-section-desc"><?php esc_html_e('Assign WordPress pages for each Panelr shortcode.', 'panelr-for-woocommerce'); ?></p>
				<p>
					<button type="button" id="panelr-create-pages" class="button button-primary"><?php esc_html_e('Create Pages Automatically', 'panelr-for-woocommerce'); ?></button>
					<span id="panelr-create-pages-result" class="panelr-inline-result"></span>
				</p>
				<table class="form-table" role="presentation">
					<?php foreach ($page_defs as $key => $def): ?>
						<tr>
							<th scope="row">
								<?php echo esc_html($def['label']); ?><br>
								<code class="panelr-shortcode"><?php echo esc_html($def['shortcode']); ?></code>
							</th>
							<td>
								<select id="panelr-page-<?php echo esc_attr($key); ?>" class="panelr-page-select" data-key="<?php echo esc_attr($key); ?>">
									<option value="0"><?php esc_html_e('— Select a page —', 'panelr-for-woocommerce'); ?></option>
									<?php foreach ($all_pages as $p): ?>
										<option value="<?php echo (int) $p->ID; ?>" <?php selected($def['current'], $p->ID); ?>>
											<?php echo esc_html($p->post_title); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<?php if ($def['current']): ?>
									&nbsp;<a href="<?php echo esc_url(get_permalink($def['current'])); ?>" target="_blank"><?php esc_html_e('View', 'panelr-for-woocommerce'); ?></a>
									&nbsp;<a href="<?php echo esc_url(get_edit_post_link($def['current'])); ?>" target="_blank"><?php esc_html_e('Edit', 'panelr-for-woocommerce'); ?></a>
								<?php endif; ?>
								<p class="description"><?php echo esc_html($def['description']); ?></p>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
				<p>
					<button type="button" id="panelr-save-pages" class="button button-primary"><?php esc_html_e('Save Page Assignments', 'panelr-for-woocommerce'); ?></button>
					<span id="panelr-save-pages-result" class="panelr-inline-result"></span>
				</p>
			</div>

			<!-- ── 4. Payments ──────────────────────────────────────────── -->
			<div class="panelr-settings-section">
				<h2><?php esc_html_e('💳 Payment Methods', 'panelr-for-woocommerce'); ?></h2>
				<p class="panelr-section-desc"><?php esc_html_e('Map each WooCommerce gateway to a Panelr payment method.', 'panelr-for-woocommerce'); ?></p>
				<p>
					<button type="button" id="panelr-refresh-pm" class="button button-secondary"><?php esc_html_e('Load / Refresh Payment Methods', 'panelr-for-woocommerce'); ?></button>
					<span id="panelr-refresh-pm-result" class="panelr-inline-result"></span>
				</p>
				<div id="panelr-pm-mapping-wrap"></div>
				<p id="panelr-save-pm-wrap" style="display:none;">
					<button type="button" id="panelr-save-pm" class="button button-primary"><?php esc_html_e('Save Payment Mapping', 'panelr-for-woocommerce'); ?></button>
					<span id="panelr-save-pm-result" class="panelr-inline-result"></span>
				</p>
			</div>

			<!-- ── 5. Free Trials ────────────────────────────────────────── -->
			<div class="panelr-settings-section">
				<h2><?php esc_html_e('🎁 Free Trials', 'panelr-for-woocommerce'); ?></h2>
				<p class="panelr-section-desc"><?php esc_html_e('Control free trial availability via the [panelr_trial] shortcode.', 'panelr-for-woocommerce'); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e('Enable Free Trials', 'panelr-for-woocommerce'); ?></th>
						<td>
							<label>
								<input type="checkbox" id="panelr-trials-enabled" value="1" <?php checked($trials_enabled, '1'); ?>>
								<?php esc_html_e('Allow customers to request a free trial', 'panelr-for-woocommerce'); ?>
							</label>
						</td>
					</tr>
					<?php if (!empty($synced)): ?>
						<tr>
							<th scope="row"><label for="panelr-trial-product"><?php esc_html_e('Trial Product', 'panelr-for-woocommerce'); ?></label></th>
							<td>
								<div class="panelr-input-row">
									<select id="panelr-trial-product">
										<option value="0"><?php esc_html_e('— None —', 'panelr-for-woocommerce'); ?></option>
										<?php foreach ($synced as $sp): ?>
											<option value="<?php echo (int) $sp['panelr_id']; ?>"
												<?php selected($trial_product_id, (int) $sp['panelr_id']); ?>>
												<?php echo esc_html($sp['name']); ?>
												(<?php echo (int) $sp['connections']; ?> conn, <?php echo (int) $sp['duration_months']; ?> mo)
											</option>
										<?php endforeach; ?>
									</select>
									<button type="button" id="panelr-save-trial-product" class="button"><?php esc_html_e('Save Product', 'panelr-for-woocommerce'); ?></button>
									<span id="panelr-trial-product-result" class="panelr-inline-result"></span>
								</div>
								<p class="description"><?php esc_html_e('This product will be hidden from your shop and used only for free trial activations.', 'panelr-for-woocommerce'); ?></p>
							</td>
						</tr>
					<?php endif; ?>
				</table>
				<p>
					<button type="button" id="panelr-save-trial-settings" class="button button-primary"><?php esc_html_e('Save Trial Settings', 'panelr-for-woocommerce'); ?></button>
					<span id="panelr-trial-settings-result" class="panelr-inline-result"></span>
				</p>
			</div>

			<!-- ── 6. Portal ─────────────────────────────────────────────── -->
			<div class="panelr-settings-section">
				<h2><?php esc_html_e('👤 Customer Portal', 'panelr-for-woocommerce'); ?></h2>
				<p class="panelr-section-desc"><?php esc_html_e('Configure the customer-facing portal ([panelr_portal]).', 'panelr-for-woocommerce'); ?></p>
				<?php $active_theme = get_option('panelr_theme', 'none'); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="panelr-theme"><?php esc_html_e('Frontend Theme', 'panelr-for-woocommerce'); ?></label></th>
						<td>
							<select id="panelr-theme">
								<option value="none" <?php selected($active_theme, 'none');  ?>><?php esc_html_e('— None (use your theme\'s styles) —', 'panelr-for-woocommerce'); ?></option>
								<option value="light" <?php selected($active_theme, 'light'); ?>><?php esc_html_e('Panelr Light', 'panelr-for-woocommerce'); ?></option>
								<option value="dark" <?php selected($active_theme, 'dark');  ?>><?php esc_html_e('Panelr Dark', 'panelr-for-woocommerce'); ?></option>
							</select>
							<p class="description"><?php esc_html_e('Applies a pre-built stylesheet to the portal, trial, upgrade, and checkout pages. Set to None if you prefer to style these yourself.', 'panelr-for-woocommerce'); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Channel Management', 'panelr-for-woocommerce'); ?></th>
						<td>
							<label>
								<input type="checkbox" id="panelr-allow-bouquets" value="1" <?php checked($allow_bouquets, '1'); ?>>
								<?php esc_html_e('Allow customers to manage their channel selection', 'panelr-for-woocommerce'); ?>
							</label>
							<p class="description"><?php esc_html_e('Adds a Channels tab to the portal where customers can choose their active channel groups.', 'panelr-for-woocommerce'); ?></p>
						</td>
					</tr>
				</table>
				<p>
					<button type="button" id="panelr-save-portal-settings" class="button button-primary"><?php esc_html_e('Save', 'panelr-for-woocommerce'); ?></button>
					<span id="panelr-portal-settings-result" class="panelr-inline-result"></span>
				</p>
			</div>

		</div>
<?php
	}
}
