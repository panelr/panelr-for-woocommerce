<?php
defined('ABSPATH') || exit;

class Panelr_Portal
{

	public static function init(): void
	{
		add_shortcode('panelr_portal', [__CLASS__, 'render']);
		add_action('wp_ajax_panelr_portal_login',                  [__CLASS__, 'ajax_login']);
		add_action('wp_ajax_nopriv_panelr_portal_login',           [__CLASS__, 'ajax_login']);
		add_action('wp_ajax_panelr_portal_logout',                 [__CLASS__, 'ajax_logout']);
		add_action('wp_ajax_nopriv_panelr_portal_logout',          [__CLASS__, 'ajax_logout']);
		add_action('wp_ajax_panelr_portal_update_customer',        [__CLASS__, 'ajax_update_customer']);
		add_action('wp_ajax_nopriv_panelr_portal_update_customer', [__CLASS__, 'ajax_update_customer']);
		add_action('wp_ajax_panelr_portal_update_bouquets',        [__CLASS__, 'ajax_update_bouquets']);
		add_action('wp_ajax_nopriv_panelr_portal_update_bouquets', [__CLASS__, 'ajax_update_bouquets']);
		add_action('wp_ajax_panelr_portal_add_renewal',            [__CLASS__, 'ajax_add_renewal']);
		add_action('wp_ajax_nopriv_panelr_portal_add_renewal',     [__CLASS__, 'ajax_add_renewal']);
	}

	// ── Session helpers ────────────────────────────────────────────────────

	private static function session_get(string $key): mixed
	{
		return WC()->session ? WC()->session->get('panelr_portal_' . $key) : null;
	}

	private static function session_set(string $key, mixed $value): void
	{
		if (WC()->session) {
			WC()->session->set('panelr_portal_' . $key, $value);
		}
	}

	private static function session_clear(): void
	{
		if (!WC()->session) return;
		$keys = [
			'logged_in',
			'activation_id',
			'username',
			'customer_email',
			'customer_name',
			'status',
			'expiration_date',
			'product',
			'eligible_products',
			'bouquets',
			'connection_type',
			'm3u_url',
			'epg_url',
			'xtream_host',
			'xtream_username',
			'xtream_password',
			'editor_username',
			'editor_password',
		];
		foreach ($keys as $key) {
			WC()->session->set('panelr_portal_' . $key, null);
		}
	}

	private static function is_logged_in(): bool
	{
		return (bool) self::session_get('logged_in');
	}

	private static function store_session(array $data): void
	{
		$has_editor = !empty($data['editor_username']) && !empty($data['editor_password']);
		self::session_set('logged_in',         true);
		self::session_set('activation_id',     $data['activation_id']);
		self::session_set('username',          $data['username']);
		self::session_set('customer_email',    $data['customer_email']);
		self::session_set('customer_name',     $data['customer_name'] ?? '');
		self::session_set('status',            $data['status']);
		self::session_set('expiration_date',   $data['expiration_date'] ?? '');
		self::session_set('product',           $data['product'] ?? null);
		self::session_set('eligible_products', $data['eligible_products'] ?? []);
		self::session_set('bouquets',          $data['bouquets'] ?? []);
		self::session_set('connection_type',   $data['connection_type'] ?? 'm3u');
		self::session_set('m3u_url',           $data['m3u_url'] ?? '');
		self::session_set('epg_url',           $data['epg_url'] ?? '');
		self::session_set('xtream_host',       $data['xtream_host'] ?? '');
		self::session_set('editor_username',   $data['editor_username'] ?? '');
		self::session_set('editor_password',   $data['editor_password'] ?? '');
		self::session_set('xtream_username',   $has_editor ? '' : ($data['xtream_username'] ?? ''));
		self::session_set('xtream_password',   $has_editor ? '' : ($data['xtream_password'] ?? ''));
	}

	// ── AJAX: login ────────────────────────────────────────────────────────

	public static function ajax_login(): void
	{
		check_ajax_referer('panelr_portal_nonce', 'nonce');
		$username = sanitize_text_field(wp_unslash($_POST['username'] ?? ''));
		$password = sanitize_text_field(wp_unslash($_POST['password'] ?? ''));
		if (!$username || !$password) {
			wp_send_json_error(['message' => __('Username and password are required.', 'panelr-for-woocommerce')]);
		}
		$api    = new Panelr_API();
		$result = $api->verify_login($username, $password);
		if (empty($result['success'])) {
			wp_send_json_error(['message' => $result['error'] ?? __('Invalid credentials or account not active.', 'panelr-for-woocommerce')]);
		}
		self::store_session($result['data']);
		$data = $result['data'];
		WC()->session->set('panelr_portal_username',        $data['username'] ?? '');
		WC()->session->set('panelr_portal_editor_username', $data['editor_username'] ?? '');
		wp_send_json_success(['message' => __('Logged in.', 'panelr-for-woocommerce')]);
	}

	// ── AJAX: logout ───────────────────────────────────────────────────────

	public static function ajax_logout(): void
	{
		check_ajax_referer('panelr_portal_nonce', 'nonce');
		self::session_clear();
		wp_send_json_success();
	}

	// ── AJAX: update customer ──────────────────────────────────────────────

	public static function ajax_update_customer(): void
	{
		check_ajax_referer('panelr_portal_nonce', 'nonce');
		if (!self::is_logged_in()) {
			wp_send_json_error(['message' => __('Not logged in.', 'panelr-for-woocommerce')]);
		}
		$activation_id = (int) self::session_get('activation_id');
		$email         = sanitize_email(wp_unslash($_POST['customer_email'] ?? ''));
		$name          = sanitize_text_field(wp_unslash($_POST['customer_name'] ?? ''));
		if (!$email || !is_email($email)) {
			wp_send_json_error(['message' => __('A valid email address is required.', 'panelr-for-woocommerce')]);
		}
		$api    = new Panelr_API();
		$result = $api->update_customer($activation_id, ['customer_email' => $email, 'customer_name' => $name]);
		if (empty($result['success'])) {
			wp_send_json_error(['message' => $result['error'] ?? __('Could not update account.', 'panelr-for-woocommerce')]);
		}
		self::session_set('customer_email', $email);
		self::session_set('customer_name',  $name);
		wp_send_json_success(['message' => __('Account updated.', 'panelr-for-woocommerce')]);
	}

	// ── AJAX: update bouquets ──────────────────────────────────────────────

	public static function ajax_update_bouquets(): void
	{
		check_ajax_referer('panelr_portal_nonce', 'nonce');
		if (!self::is_logged_in()) {
			wp_send_json_error(['message' => __('Not logged in.', 'panelr-for-woocommerce')]);
		}
		if (!get_option('panelr_portal_allow_bouquets', '0')) {
			wp_send_json_error(['message' => __('Bouquet updates are not enabled.', 'panelr-for-woocommerce')]);
		}
		$activation_id = (int) self::session_get('activation_id');
		$mode          = sanitize_text_field(wp_unslash($_POST['mode'] ?? 'panel'));
		$api = new Panelr_API();
		if ($mode === 'editor') {
			$bouquets = [
				'live'   => array_map('intval', (array) wp_unslash($_POST['live']   ?? [])),
				'vod'    => array_map('intval', (array) wp_unslash($_POST['vod']    ?? [])),
				'series' => array_map('intval', (array) wp_unslash($_POST['series'] ?? [])),
			];
			$result = $api->update_bouquets($activation_id, $bouquets);
		} else {
			$bouquet_ids = array_map('intval', (array) wp_unslash($_POST['bouquet_ids'] ?? []));
			$result      = $api->update_bouquets($activation_id, $bouquet_ids);
		}
		if (empty($result['success'])) {
			wp_send_json_error(['message' => $result['error'] ?? __('Could not update bouquets.', 'panelr-for-woocommerce')]);
		}
		self::session_set('bouquets', $mode === 'editor' ? $bouquets : $bouquet_ids);
		// Flag success for display on next render
		self::session_set('bouquets_saved', true);
		wp_send_json_success(['message' => __('Channels saved.', 'panelr-for-woocommerce')]);
	}

	// ── AJAX: add renewal to cart ──────────────────────────────────────────

	public static function ajax_add_renewal(): void
	{
		check_ajax_referer('panelr_portal_nonce', 'nonce');
		if (!self::is_logged_in()) {
			wp_send_json_error(['message' => __('Not logged in.', 'panelr-for-woocommerce')]);
		}
		$panelr_product_id = (int) sanitize_text_field(wp_unslash($_POST['panelr_product_id'] ?? 0));
		$activation_id     = (int) self::session_get('activation_id');
		if (!$panelr_product_id || !$activation_id) {
			wp_send_json_error(['message' => __('Invalid product or session.', 'panelr-for-woocommerce')]);
		}
		$posts = get_posts([
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'meta_key'       => '_panelr_product_id',
			'meta_value'     => $panelr_product_id,
			'fields'         => 'ids',
		]);
		if (empty($posts)) {
			wp_send_json_error(['message' => __('Product not found in store. Please contact support.', 'panelr-for-woocommerce')]);
		}
		$cart_item_key = WC()->cart->add_to_cart($posts[0], 1, 0, [], [
			'_panelr_intent'        => 'renewal',
			'_panelr_activation_id' => $activation_id,
		]);
		if (!$cart_item_key) {
			wp_send_json_error(['message' => __('Could not add product to cart.', 'panelr-for-woocommerce')]);
		}
		wp_send_json_success([
			'message'      => __('Added to cart.', 'panelr-for-woocommerce'),
			'cart_url'     => wc_get_cart_url(),
			'checkout_url' => wc_get_checkout_url(),
		]);
	}

	// ── Render shortcode ───────────────────────────────────────────────────

	public static function render(): string
	{
		if (WC()->session && !WC()->session->has_session()) {
			WC()->session->set_customer_session_cookie(true);
		}

		wp_enqueue_script('panelr-portal', PANELR_PLUGIN_URL . 'assets/js/portal.js', ['jquery'], PANELR_VERSION, true);
		wp_localize_script('panelr-portal', 'panelrPortal', [
			'ajaxurl'        => admin_url('admin-ajax.php'),
			'nonce'          => wp_create_nonce('panelr_portal_nonce'),
			'allow_bouquets' => get_option('panelr_portal_allow_bouquets', '0'),
			'checkout_url'   => wc_get_checkout_url(),
		]);
		ob_start();
		if (!self::is_logged_in()) {
			self::render_login_form();
		} else {
			self::render_dashboard();
		}
		return ob_get_clean();
	}

	// ── Login form ─────────────────────────────────────────────────────────

	private static function render_login_form(): void
	{
?>
		<div class="panelr-portal">
			<div class="panelr-portal__login">
				<p class="panelr-portal__subtitle"><?php esc_html_e('Enter your IPTV credentials to manage your account.', 'panelr-for-woocommerce'); ?></p>
				<p id="panelr-login-error" class="panelr-portal__error woocommerce-error" style="display:none;"></p>
				<p class="panelr-portal__field">
					<label for="panelr-username"><?php esc_html_e('Username', 'panelr-for-woocommerce'); ?></label>
					<input type="text" id="panelr-username" name="username" class="input-text" autocomplete="username">
				</p>
				<p class="panelr-portal__field">
					<label for="panelr-password"><?php esc_html_e('Password', 'panelr-for-woocommerce'); ?></label>
					<input type="password" id="panelr-password" name="password" class="input-text" autocomplete="current-password">
				</p>
				<p class="panelr-portal__field">
					<button type="button" id="panelr-login-btn" class="button"><?php esc_html_e('Sign In', 'panelr-for-woocommerce'); ?></button>
				</p>
			</div>
		</div>
	<?php
	}

	// ── Dashboard ──────────────────────────────────────────────────────────

	private static function render_dashboard(): void
	{
		$customer_email  = (string) self::session_get('customer_email');
		$customer_name   = (string) self::session_get('customer_name');
		$status          = (string) self::session_get('status');
		$expiration_date = (string) self::session_get('expiration_date');
		$m3u_url         = (string) self::session_get('m3u_url');
		$epg_url         = (string) self::session_get('epg_url');
		$xtream_host     = (string) self::session_get('xtream_host');
		$editor_username = (string) self::session_get('editor_username');
		$editor_password = (string) self::session_get('editor_password');
		$eligible        = self::session_get('eligible_products') ?: [];
		$current_bouquets = self::session_get('bouquets') ?: [];

		// Consume bouquets-saved flag set by ajax_update_bouquets
		$bouquets_saved = (bool) self::session_get('bouquets_saved');
		if ($bouquets_saved) {
			self::session_set('bouquets_saved', false);
		}

		$display_username = $editor_username ?: '';
		$display_password = $editor_password ?: '';
		$allow_bouquets   = get_option('panelr_portal_allow_bouquets', '0') === '1';

		$expiry_formatted = $expiration_date
			? date_i18n(get_option('date_format'), strtotime($expiration_date)) : '—';

		$status_labels = [
			'active'       => __('Active', 'panelr-for-woocommerce'),
			'trial_active' => __('Trial Active', 'panelr-for-woocommerce'),
			'expired'      => __('Expired', 'panelr-for-woocommerce'),
			'suspended'    => __('Suspended', 'panelr-for-woocommerce'),
			'canceled'     => __('Canceled', 'panelr-for-woocommerce'),
		];
		$status_label = $status_labels[$status] ?? ucfirst($status);

		$bouquet_data = null;
		if ($allow_bouquets) {
			$api    = new Panelr_API();
			$result = $api->get_bouquets();
			if (!empty($result['success'])) {
				$bouquet_data = $result['data'];
			}
		}

		$current_bouquet_ids = [];
		if (is_array($current_bouquets)) {
			if (isset($current_bouquets['live']) || isset($current_bouquets['vod']) || isset($current_bouquets['series'])) {
				foreach ($current_bouquets as $cat_bouquets) {
					foreach ((array) $cat_bouquets as $b) {
						$current_bouquet_ids[] = is_array($b) ? (int) $b['id'] : (int) $b;
					}
				}
			} else {
				foreach ($current_bouquets as $b) {
					$current_bouquet_ids[] = is_array($b) ? (int) $b['id'] : (int) $b;
				}
			}
		}
	?>
		<div class="panelr-portal">

			<?php if ($bouquets_saved): ?>
				<p class="woocommerce-message"><?php esc_html_e('Channels saved and synced successfully.', 'panelr-for-woocommerce'); ?></p>
			<?php endif; ?>

			<p class="panelr-portal__actions">
				<button type="button" id="panelr-edit-account-btn" class="button"><?php esc_html_e('Edit', 'panelr-for-woocommerce'); ?></button>
				<button type="button" id="panelr-logout-btn" class="button"><?php esc_html_e('Sign Out', 'panelr-for-woocommerce'); ?></button>
			</p>

			<!-- Customer Information -->
			<section class="panelr-portal__section panelr-portal__account">
				<h3><?php esc_html_e('Customer Information', 'panelr-for-woocommerce'); ?></h3>
				<div id="panelr-account-display">
					<table class="panelr-portal__table">
						<tr>
							<th><?php esc_html_e('Name', 'panelr-for-woocommerce'); ?></th>
							<td id="panelr-display-name"><?php echo esc_html($customer_name); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e('Email', 'panelr-for-woocommerce'); ?></th>
							<td id="panelr-display-email"><?php echo esc_html($customer_email); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e('Status', 'panelr-for-woocommerce'); ?></th>
							<td><span class="panelr-portal__status panelr-portal__status--<?php echo esc_attr($status); ?>"><?php echo esc_html($status_label); ?></span></td>
						</tr>
						<tr>
							<th><?php esc_html_e('Expiration Date', 'panelr-for-woocommerce'); ?></th>
							<td><?php echo esc_html($expiry_formatted); ?></td>
						</tr>
					</table>
				</div>
				<div id="panelr-account-edit" style="display:none;">
					<p id="panelr-account-edit-error" class="panelr-portal__error woocommerce-error" style="display:none;"></p>
					<p class="panelr-portal__field">
						<label for="panelr-edit-email"><?php esc_html_e('Email', 'panelr-for-woocommerce'); ?></label>
						<input type="email" id="panelr-edit-email" class="input-text" value="<?php echo esc_attr($customer_email); ?>">
					</p>
					<p class="panelr-portal__field">
						<label for="panelr-edit-name"><?php esc_html_e('Name', 'panelr-for-woocommerce'); ?></label>
						<input type="text" id="panelr-edit-name" class="input-text" value="<?php echo esc_attr($customer_name); ?>">
					</p>
					<p>
						<button type="button" id="panelr-save-account-btn" class="button"><?php esc_html_e('Save', 'panelr-for-woocommerce'); ?></button>
						<button type="button" id="panelr-cancel-account-btn" class="button"><?php esc_html_e('Cancel', 'panelr-for-woocommerce'); ?></button>
						<span id="panelr-account-result" class="panelr-portal__result"></span>
					</p>
				</div>
			</section>

			<!-- Tabs -->
			<?php
			$tabs = ['connection' => __('Connection', 'panelr-for-woocommerce')];
			if ($allow_bouquets && $bouquet_data && $bouquet_data['mode'] !== 'none') {
				$tabs['channels'] = __('Channels', 'panelr-for-woocommerce');
			}
			if (!empty($eligible)) {
				$tabs['renewal'] = __('Renew', 'panelr-for-woocommerce');
			}
			?>
			<div class="panelr-portal__tabs">
				<?php foreach ($tabs as $tab_key => $tab_label): ?>
					<button type="button"
						class="button panelr-tab-btn <?php echo $tab_key === 'connection' ? 'panelr-tab-btn--active' : ''; ?>"
						data-tab="panelr-tab-<?php echo esc_attr($tab_key); ?>">
						<?php echo esc_html($tab_label); ?>
					</button>
				<?php endforeach; ?>
			</div>

			<!-- Tab: Connection -->
			<div class="panelr-portal__tab-panel panelr-portal__tab-panel--toplevel panelr-portal__tab-panel--active" id="panelr-tab-connection">
				<section class="panelr-portal__section panelr-portal__credentials">
					<h3><?php esc_html_e('Connection Information', 'panelr-for-woocommerce'); ?></h3>
					<table class="panelr-portal__table">
						<?php if ($xtream_host): ?>
							<tr>
								<th><?php esc_html_e('Host', 'panelr-for-woocommerce'); ?></th>
								<td><code class="panelr-portal__code"><?php echo esc_html($xtream_host); ?></code></td>
								<td><button type="button" class="button panelr-copy-btn" data-copy="<?php echo esc_attr($xtream_host); ?>"><?php esc_html_e('Copy', 'panelr-for-woocommerce'); ?></button></td>
							</tr>
						<?php endif; ?>
						<?php if ($display_username): ?>
							<tr>
								<th><?php esc_html_e('Username', 'panelr-for-woocommerce'); ?></th>
								<td><code class="panelr-portal__code"><?php echo esc_html($display_username); ?></code></td>
								<td><button type="button" class="button panelr-copy-btn" data-copy="<?php echo esc_attr($display_username); ?>"><?php esc_html_e('Copy', 'panelr-for-woocommerce'); ?></button></td>
							</tr>
						<?php endif; ?>
						<?php if ($display_password): ?>
							<tr>
								<th><?php esc_html_e('Password', 'panelr-for-woocommerce'); ?></th>
								<td>
									<code class="panelr-portal__code" id="panelr-password-value" style="display:none;"><?php echo esc_html($display_password); ?></code>
									<span id="panelr-password-hidden">&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;</span>
								</td>
								<td>
									<button type="button" id="panelr-toggle-password-btn" class="button"><?php esc_html_e('Show', 'panelr-for-woocommerce'); ?></button>
									<button type="button" class="button panelr-copy-btn" data-copy="<?php echo esc_attr($display_password); ?>"><?php esc_html_e('Copy', 'panelr-for-woocommerce'); ?></button>
								</td>
							</tr>
						<?php endif; ?>
					</table>
				</section>
				<section class="panelr-portal__section panelr-portal__urls">
					<h3><?php esc_html_e('URLs', 'panelr-for-woocommerce'); ?></h3>
					<table class="panelr-portal__table">
						<?php if ($m3u_url): ?>
							<tr>
								<th><?php esc_html_e('M3U URL', 'panelr-for-woocommerce'); ?></th>
								<td><code class="panelr-portal__code"><?php echo esc_html($m3u_url); ?></code></td>
								<td><button type="button" class="button panelr-copy-btn" data-copy="<?php echo esc_attr($m3u_url); ?>"><?php esc_html_e('Copy', 'panelr-for-woocommerce'); ?></button></td>
							</tr>
						<?php endif; ?>
						<?php if ($epg_url): ?>
							<tr>
								<th><?php esc_html_e('EPG URL', 'panelr-for-woocommerce'); ?></th>
								<td><code class="panelr-portal__code"><?php echo esc_html($epg_url); ?></code></td>
								<td><button type="button" class="button panelr-copy-btn" data-copy="<?php echo esc_attr($epg_url); ?>"><?php esc_html_e('Copy', 'panelr-for-woocommerce'); ?></button></td>
							</tr>
						<?php endif; ?>
					</table>
				</section>
			</div>

			<?php if ($allow_bouquets && $bouquet_data && $bouquet_data['mode'] !== 'none'): ?>
				<!-- Tab: Channels -->
				<div class="panelr-portal__tab-panel panelr-portal__tab-panel--toplevel" id="panelr-tab-channels" style="display:none;">
					<section class="panelr-portal__section panelr-portal__bouquets">
						<h3><?php esc_html_e('Channel Management', 'panelr-for-woocommerce'); ?></h3>
						<p id="panelr-bouquet-result" class="panelr-portal__result" style="display:none;"></p>

						<?php if ($bouquet_data['mode'] === 'editor'): ?>
							<?php
							$active_cats = [];
							foreach (['live' => __('Live TV', 'panelr-for-woocommerce'), 'vod' => __('Movies', 'panelr-for-woocommerce'), 'series' => __('Series', 'panelr-for-woocommerce')] as $cat => $cat_label) {
								if (!empty($bouquet_data['bouquets'][$cat])) $active_cats[$cat] = $cat_label;
							}
							$cat_keys    = array_keys($active_cats);
							$total_steps = count($cat_keys) + 1;
							?>
							<div class="panelr-bouquet-wizard" data-total-steps="<?php echo (int) $total_steps; ?>">

								<?php foreach ($cat_keys as $step_index => $cat): ?>
									<div class="panelr-wizard-step" data-step="<?php echo (int) $step_index; ?>" data-category="<?php echo esc_attr($cat); ?>" <?php echo $step_index > 0 ? 'style="display:none;"' : ''; ?>>
										<h4><?php echo esc_html($active_cats[$cat]); ?></h4>
										<?php
										$all_selected = empty($current_bouquet_ids);
										?>
										<ul class="panelr-portal__bouquet-list">
											<?php foreach ($bouquet_data['bouquets'][$cat] as $bouquet): ?>
												<li><label>
														<input type="checkbox" class="panelr-bouquet-cb" data-category="<?php echo esc_attr($cat); ?>"
															value="<?php echo (int) $bouquet['id']; ?>"
															<?php checked($all_selected || in_array((int) $bouquet['id'], $current_bouquet_ids, true)); ?>>
														<?php echo esc_html($bouquet['display_name'] ?: $bouquet['name']); ?>
													</label></li>
											<?php endforeach; ?>
										</ul>
										<p><button type="button" class="button panelr-wizard-next"><?php esc_html_e('Continue', 'panelr-for-woocommerce'); ?></button></p>
									</div>
								<?php endforeach; ?>

								<!-- Review step -->
								<div class="panelr-wizard-step panelr-wizard-review" data-step="<?php echo (int) count($cat_keys); ?>" style="display:none;">
									<h4><?php esc_html_e('Review Your Selection', 'panelr-for-woocommerce'); ?></h4>
									<div id="panelr-bouquet-review"></div>
									<p>
										<button type="button" class="button panelr-wizard-back"><?php esc_html_e('Back', 'panelr-for-woocommerce'); ?></button>
										<button type="button" id="panelr-save-bouquets-btn" class="button"><?php esc_html_e('Save &amp; Sync', 'panelr-for-woocommerce'); ?></button>
									</p>
								</div>

							</div>
							<input type="hidden" id="panelr-bouquet-mode" value="editor">

						<?php else: ?>
							<ul class="panelr-portal__bouquet-list">
								<?php foreach ($bouquet_data['bouquets'] as $i => $bouquet): ?>
									<li><label>
											<input type="radio" name="panelr_bouquet_flat" class="panelr-bouquet-radio"
												value="<?php echo (int) $bouquet['id']; ?>"
												<?php checked(
													(!empty($current_bouquet_ids) && $current_bouquet_ids[0] === (int) $bouquet['id'])
														|| ($i === 0 && empty($current_bouquet_ids))
												); ?>>
											<?php echo esc_html($bouquet['display_name'] ?: $bouquet['name']); ?>
										</label></li>
								<?php endforeach; ?>
							</ul>
							<input type="hidden" id="panelr-bouquet-mode" value="panel">
							<p><button type="button" id="panelr-save-bouquets-btn" class="button"><?php esc_html_e('Save Channels', 'panelr-for-woocommerce'); ?></button></p>
						<?php endif; ?>

					</section>
				</div>
			<?php endif; ?>

			<?php if (!empty($eligible)): ?>
				<!-- Tab: Renewal -->
				<div class="panelr-portal__tab-panel panelr-portal__tab-panel--toplevel" id="panelr-tab-renewal" style="display:none;">
					<section class="panelr-portal__section panelr-portal__renewal">
						<h3><?php esc_html_e('Renew Service', 'panelr-for-woocommerce'); ?></h3>
						<p id="panelr-renewal-result" class="panelr-portal__result" style="display:none;"></p>
						<table class="panelr-portal__table">
							<?php foreach ($eligible as $product): ?>
								<tr>
									<td><?php echo esc_html($product['name']); ?></td>
									<td><?php echo wp_kses_post(wc_price((float) $product['price_decimal'])); ?></td>
									<td><?php echo esc_html(
											$product['connections'] . ' ' . _n('connection', 'connections', $product['connections'], 'panelr-for-woocommerce')
												. ' · ' .
												$product['duration_months'] . ' ' . _n('month', 'months', $product['duration_months'], 'panelr-for-woocommerce')
										); ?></td>
									<td><button type="button" class="button panelr-renew-btn" data-panelr-product-id="<?php echo (int) $product['id']; ?>"><?php esc_html_e('Renew', 'panelr-for-woocommerce'); ?></button></td>
								</tr>
							<?php endforeach; ?>
						</table>
					</section>
				</div>
			<?php endif; ?>

		</div>
<?php
	}
}
