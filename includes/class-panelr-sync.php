<?php
defined('ABSPATH') || exit;

/**
 * Panelr products ⇄ WooCommerce products, one to one, joined by
 * _panelr_product_id. Merchant edits survive a sync unless the operator
 * says otherwise; a plan Panelr no longer offers is drafted, never deleted;
 * a trial plan is never for sale.
 */
class Panelr_Sync
{
	public static function init(): void
	{
		add_action('wp_ajax_panelr_sync_products', [__CLASS__, 'ajax_sync']);
		add_action('wp_ajax_panelr_toggle_product', [__CLASS__, 'ajax_toggle_product']);
		add_action('wp_ajax_panelr_rename_product', [__CLASS__, 'ajax_rename_product']);
		// "Sell as" changed for a service: file its plans the new way straight away.
		add_action('update_option_panelr_service_modes', [__CLASS__, 'on_modes_changed'], 10, 2);
		add_action('add_option_panelr_service_modes', [__CLASS__, 'on_modes_added'], 10, 2);
		// A service's store name changed: its product (when it has one) follows.
		add_action('update_option_panelr_service_names', [__CLASS__, 'on_names_changed'], 10, 2);
		add_action('add_option_panelr_service_names', fn($option, $value) => self::on_names_changed('{}', $value), 10, 2);
		add_action('woocommerce_product_meta_end', [__CLASS__, 'show_service_on_product']);
		add_action('woocommerce_after_single_product_summary', [__CLASS__, 'show_addons'], 15);
		add_action('wp_ajax_panelr_add_addon', [__CLASS__, 'ajax_add_addon']);
		add_action('wp_ajax_nopriv_panelr_add_addon', [__CLASS__, 'ajax_add_addon']);
		add_action('admin_notices', [__CLASS__, 'notices']);
		add_shortcode('panelr_plans', [__CLASS__, 'shortcode_plans']);
		add_shortcode('panelr_services', [__CLASS__, 'shortcode_services']);
	}

	// ── Shortcodes: a service's plans on any page ─────────────────────────

	/** A service by id or by name (case-insensitive), or null. */
	private static function find_service(string $which): ?array
	{
		$which = trim($which);
		if ($which === '') return null;
		$services = Panelr_Helpers::services();
		if (ctype_digit($which) && isset($services[(int) $which])) return $services[(int) $which];
		foreach ($services as $svc) {
			if (strcasecmp((string) $svc['name'], $which) === 0 || sanitize_title((string) $svc['name']) === sanitize_title($which)) return $svc;
		}
		return null;
	}

	/**
	 * [panelr_plans service="Demo Service"]   one service's plans
	 * [panelr_plans]                          every service, grouped, with headings
	 * Attributes: service (name or id), columns (1–4, default 3), heading (yes|no), ids (Panelr plan ids, comma-separated)
	 */
	public static function shortcode_plans($atts = []): string
	{
		$atts = shortcode_atts(['service' => '', 'columns' => 3, 'heading' => 'yes', 'ids' => ''], (array) $atts, 'panelr_plans');
		wp_enqueue_script('panelr-common');
		wp_enqueue_script('panelr-cart', PANELR_PLUGIN_URL . 'assets/js/cart.js', ['jquery', 'panelr-common'], PANELR_VERSION, true);
		wp_localize_script('panelr-cart', 'panelrCart', [
			'ajaxurl'      => admin_url('admin-ajax.php'),
			'nonce'        => wp_create_nonce('panelr_cart'),
			'cart_url'     => wc_get_cart_url(),
			'checkout_url' => wc_get_checkout_url(),
			'i18n'         => ['enter_code' => __('Enter a code first.', 'panelr-for-woocommerce'), 'applying' => __('Adding…', 'panelr-for-woocommerce'), 'apply' => __('Apply', 'panelr-for-woocommerce')],
		]);

		$only_ids = array_filter(array_map('intval', explode(',', (string) $atts['ids'])));
		$groups   = [];
		$services = Panelr_Helpers::services();
		$wanted   = $atts['service'] !== '' ? self::find_service((string) $atts['service']) : null;
		if ($atts['service'] !== '' && !$wanted) {
			return current_user_can('manage_options')
				? Panelr_Template::notice(sprintf(
					/* translators: %s: the service given in the shortcode */
					__('No service called "%s". Use the name shown under Panelr → Products.', 'panelr-for-woocommerce'),
					(string) $atts['service']
				), 'info')
				: '';
		}

		foreach (Panelr_Helpers::synced_products(true) as $row) {
			if ($row['is_trial']) continue;
			if ($wanted && $row['plugin_id'] !== (int) $wanted['plugin_id']) continue;
			if ($only_ids && !in_array($row['panelr_id'], $only_ids, true)) continue;
			$product = wc_get_product($row['wc_id']);
			$row['short_description'] = $product ? $product->get_short_description() : '';
			$row['description']       = $product ? $product->get_description() : '';
			$groups[$row['plugin_id']]['name']    = $services[$row['plugin_id']]['name'] ?? '';
			$groups[$row['plugin_id']]['lines'][] = $row;
		}
		foreach ($groups as &$g) {
			usort($g['lines'], fn($a, $b) => [$a['connections'], $a['duration_months'], $a['price']] <=> [$b['connections'], $b['duration_months'], $b['price']]);
		}
		unset($g);

		return Panelr_Template::render('plans', [
			'groups'    => $groups,
			'columns'   => max(1, min(4, (int) $atts['columns'])),
			'heading'   => $atts['heading'] !== 'no' && (!$wanted || count($groups) > 1 ? true : $atts['heading'] === 'yes'),
			'single'    => (bool) $wanted,
			'signed_in' => Panelr_Session::is_signed_in(),
			'balance'   => Panelr_Session::is_signed_in() ? Panelr_Session::credits_balance() : 0,
			'nonce'     => wp_create_nonce('panelr_addon'),
		]);
	}

	/** [panelr_services] — the services with links to their category pages. */
	public static function shortcode_services($atts = []): string
	{
		$items = [];
		foreach (Panelr_Helpers::services() as $id => $svc) {
			$count = count(array_filter(Panelr_Helpers::synced_products(true), fn($p) => $p['plugin_id'] === (int) $id && !$p['is_trial']));
			$terms = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false, 'meta_query' => [['key' => '_panelr_plugin_id', 'value' => (string) (int) $id]], 'fields' => 'ids']); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- a handful of terms
			$url   = (!is_wp_error($terms) && $terms) ? get_term_link((int) $terms[0], 'product_cat') : '';
			$items[] = ['name' => $svc['name'], 'category' => $svc['category_name'] ?? '', 'plans' => $count, 'url' => is_string($url) ? $url : ''];
		}
		return Panelr_Template::render('services', ['services' => $items]);
	}

	// ── AJAX ──────────────────────────────────────────────────────────────

	public static function ajax_sync(): void
	{
		check_ajax_referer('panelr_admin_nonce', 'nonce');
		if (!current_user_can('manage_options')) {
			wp_send_json_error(['message' => __('You are not allowed to do that.', 'panelr-for-woocommerce')]);
		}
		$result = self::sync();
		if ($result['success']) {
			wp_send_json_success($result);
		}
		wp_send_json_error($result);
	}

	/** On = for sale (published). Off = draft. Trial plans stay Panelr's. */
	public static function ajax_toggle_product(): void
	{
		check_ajax_referer('panelr_admin_nonce', 'nonce');
		if (!current_user_can('manage_woocommerce')) {
			wp_send_json_error(['message' => __('You are not allowed to do that.', 'panelr-for-woocommerce')]);
		}
		$product = wc_get_product(absint(wp_unslash($_POST['product_id'] ?? 0)));
		if (!$product || !$product->get_meta('_panelr_product_id')) {
			wp_send_json_error(['message' => __('Plan not found.', 'panelr-for-woocommerce')]);
		}
		if ($product->get_meta('_panelr_is_trial') === '1') {
			wp_send_json_error(['message' => __('The trial plan is hidden by Panelr.', 'panelr-for-woocommerce')]);
		}
		$on = sanitize_text_field(wp_unslash($_POST['on'] ?? '')) === '1';
		if ($product->is_type('variation')) {
			// An option on the service's product: on = enabled, off = disabled.
			$product->set_status($on ? 'publish' : 'private');
			if ($on) {
				$product->delete_meta_data('_panelr_removed_at');
				$parent = wc_get_product($product->get_parent_id());
				if ($parent && $parent->get_status() !== 'publish') {
					$parent->set_status('publish');
					$parent->set_catalog_visibility('visible');
					$parent->save();
				}
			}
			$product->save();
			self::refresh_service_product($product->get_parent_id());
		} else {
			$product->set_status($on ? 'publish' : 'draft');
			if ($on) {
				$product->set_catalog_visibility('visible');
				$product->delete_meta_data('_panelr_removed_at');
			}
			$product->save();
		}
		Panelr_Helpers::flush_products();
		wp_send_json_success(['on' => $on, 'label' => $on ? __('On', 'panelr-for-woocommerce') : __('Off', 'panelr-for-woocommerce')]);
	}

	/**
	 * The name this store shows for a plan, typed in the Products table. It
	 * overrides Panelr's name everywhere the store names the plan and no sync
	 * touches it. Empty puts Panelr's name back.
	 */
	public static function ajax_rename_product(): void
	{
		check_ajax_referer('panelr_admin_nonce', 'nonce');
		if (!current_user_can('manage_woocommerce')) {
			wp_send_json_error(['message' => __('You are not allowed to do that.', 'panelr-for-woocommerce')]);
		}
		$product = wc_get_product(absint(wp_unslash($_POST['product_id'] ?? 0)));
		if (!$product || !$product->get_meta('_panelr_product_id')) {
			wp_send_json_error(['message' => __('Plan not found.', 'panelr-for-woocommerce')]);
		}
		$name        = self::clean_label(sanitize_text_field(wp_unslash($_POST['name'] ?? '')));
		$panelr_name = (string) $product->get_meta('_panelr_synced_name');
		if ($name === '' || $name === $panelr_name) {
			$name = '';
			$product->delete_meta_data('_panelr_store_name');
		} else {
			$product->update_meta_data('_panelr_store_name', $name);
		}
		self::apply_store_name($product);
		$product->save();
		if ($product->is_type('variation')) {
			self::refresh_service_product($product->get_parent_id());
		}
		Panelr_Helpers::flush_products();
		wp_send_json_success([
			'store_name'  => $name,
			'name'        => $name !== '' ? $name : $panelr_name,
			'panelr_name' => $panelr_name,
		]);
	}

	/** Make the product show its store name (or Panelr's when there is none). The product is not saved here. */
	private static function apply_store_name(WC_Product $product): void
	{
		$name = (string) $product->get_meta('_panelr_store_name');
		if ($name === '') $name = (string) $product->get_meta('_panelr_synced_name');
		if ($name === '') return;
		if ($product->is_type('variation')) {
			$product->set_attributes(['plan' => $name]);
		} else {
			$product->set_name($name);
		}
	}

	/** A plan or option label as WooCommerce can store it (a bar separates attribute options). */
	private static function clean_label(string $name): string
	{
		return trim(mb_substr(str_replace('|', '', trim($name)), 0, 80));
	}

	public static function on_modes_added($option, $value): void
	{
		self::on_modes_changed('{}', $value);
	}

	public static function on_modes_changed($old, $new): void
	{
		if ((string) $old === (string) $new) return;
		$result = self::sync();
		if (empty($result['success'])) {
			update_option('panelr_sync_notes', [sprintf(
				/* translators: %s: the error */
				__('The plans could not be refiled: %s Click "Sync from Panelr" to try again.', 'panelr-for-woocommerce'),
				(string) ($result['message'] ?? '')
			)], false);
		}
	}

	public static function on_names_changed($old, $new): void
	{
		if ((string) $old === (string) $new) return;
		foreach (Panelr_Helpers::service_modes() as $plugin_id => $mode) {
			$parent_id = Panelr_Helpers::service_product_id($plugin_id);
			if ($parent_id) self::refresh_service_product($parent_id);
		}
		Panelr_Helpers::flush_products();
	}

	/**
	 * The WooCommerce product for a Panelr plan, created on the spot from
	 * Panelr's data when the store does not have it yet. 0 when Panelr does
	 * not know the plan either.
	 */
	public static function ensure_product(int $panelr_id): int
	{
		if (!$panelr_id) return 0;
		$wc_id = Panelr_Helpers::wc_product_id_for($panelr_id);
		if ($wc_id) {
			$product = wc_get_product($wc_id);
			// A drafted or private plan can still be paid for when Panelr sold it.
			if ($product && $product->get_status() === 'trash') {
				$product->set_status('private');
				$product->save();
			}
			return $wc_id;
		}
		$result = Panelr_API::instance()->get_products();
		if (!$result['ok'] || !is_array($result['data'])) return 0;
		foreach ($result['data'] as $p) {
			if ((int) $p['id'] !== $panelr_id) continue;
			$product = self::new_product($p, self::mode_for($p));
			if (!empty($p['is_trial'])) {
				$product->set_status('private');
				$product->set_catalog_visibility('hidden');
			}
			self::write_panelr_meta($product, $p);
			$product->save();
			if ($product->is_type('variation')) {
				self::refresh_service_product($product->get_parent_id());
			}
			Panelr_Helpers::flush_products();
			return $product->get_id();
		}
		return 0;
	}

	/** 'options' when the plan's service is sold as one product with options; trials are always products of their own. */
	private static function mode_for(array $p): string
	{
		$plugin_id = (int) ($p['plugin_id'] ?? 0);
		if (!empty($p['is_trial']) || !$plugin_id) return 'plans';
		return Panelr_Helpers::service_mode($plugin_id);
	}

	/** A fresh WooCommerce product for a Panelr plan, not yet saved: a simple product, or an option on the service's product. */
	private static function new_product(array $p, string $mode): WC_Product
	{
		if ($mode === 'options') {
			$parent_id = self::service_product((int) $p['plugin_id'], (string) ($p['plugin_name'] ?? ''));
			if ($parent_id) {
				$product = new WC_Product_Variation();
				$product->set_parent_id($parent_id);
				$product->set_virtual(true);
				$product->set_status('publish');
				$product->set_attributes(['plan' => self::clean_label((string) $p['name'])]);
				$product->set_regular_price((string) $p['price_decimal']);
				if (!empty($p['description']) && self::sync_descriptions()) $product->set_description((string) $p['description']);
				return $product;
			}
		}
		$product = new WC_Product_Simple();
		$product->set_virtual(true);
		$product->set_sold_individually(true);
		$product->set_name((string) $p['name']);
		$product->set_regular_price((string) $p['price_decimal']);
		if (!empty($p['description']) && self::sync_descriptions()) $product->set_description((string) $p['description']);
		$product->set_status('publish');
		return $product;
	}

	/** The service's own product ("one product with options"), created when the service is first sold that way. */
	private static function service_product(int $plugin_id, string $service_name): int
	{
		if (!$plugin_id) return 0;
		$id = Panelr_Helpers::service_product_id($plugin_id);
		if ($id) return $id;
		$name = Panelr_Helpers::service_name($plugin_id) ?: $service_name;
		if ($name === '') return 0;
		$parent = new WC_Product_Variable();
		$parent->set_name($name);
		$parent->set_virtual(true);
		$parent->set_sold_individually(true);
		$parent->set_status('publish');
		$parent->set_catalog_visibility('visible');
		$parent->update_meta_data('_panelr_service_product', $plugin_id);
		$parent->update_meta_data('_panelr_plugin_id', $plugin_id);
		$parent->update_meta_data('_panelr_synced_name', $name);
		$attr = new WC_Product_Attribute();
		$attr->set_id(0);
		$attr->set_name('Plan');
		$attr->set_options([]);
		$attr->set_position(0);
		$attr->set_visible(false);
		$attr->set_variation(true);
		$parent->set_attributes(['plan' => $attr]);
		$parent->save();
		return $parent->get_id();
	}

	/**
	 * After its options changed: the option list on the service's product in
	 * plan order with no two options alike, its name and category, its price
	 * range, and draft when it has no options left.
	 */
	public static function refresh_service_product(int $parent_id, bool $overwrite = false): void
	{
		$parent = wc_get_product($parent_id);
		if (!$parent || !$parent->is_type('variable')) return;
		$plugin_id = (int) $parent->get_meta('_panelr_plugin_id');

		$options = [];
		foreach ($parent->get_children() as $vid) {
			$v = wc_get_product($vid);
			// Options that are off (or parked) are left out of the dropdown, not shown as unavailable.
			if (!$v || !$v->get_meta('_panelr_product_id') || $v->get_status() !== 'publish') continue;
			$options[] = $v;
		}
		usort($options, fn($a, $b) => [(int) $a->get_meta('_panelr_connections'), (int) $a->get_meta('_panelr_duration_months'), (float) $a->get_regular_price()]
			<=> [(int) $b->get_meta('_panelr_connections'), (int) $b->get_meta('_panelr_duration_months'), (float) $b->get_regular_price()]);

		$labels = [];
		$seen   = [];
		foreach ($options as $i => $v) {
			$label = self::clean_label((string) $v->get_meta('_panelr_store_name') ?: (string) $v->get_meta('_panelr_synced_name'));
			if ($label === '') $label = Panelr_Helpers::plan_summary((int) $v->get_meta('_panelr_connections'), (int) $v->get_meta('_panelr_duration_months'));
			if (isset($seen[$label])) {
				$label .= ' · ' . Panelr_Helpers::plan_summary((int) $v->get_meta('_panelr_connections'), (int) $v->get_meta('_panelr_duration_months'));
			}
			for ($n = 2; isset($seen[$label]); $n++) {
				$label = preg_replace('/ \(\d+\)$/', '', $label) . ' (' . $n . ')';
			}
			$seen[$label] = true;
			$labels[]     = $label;
			if ((string) ($v->get_attributes()['plan'] ?? '') !== $label || (int) $v->get_menu_order() !== $i) {
				$v->set_attributes(['plan' => $label]);
				$v->set_menu_order($i);
				$v->save();
			}
		}

		$attr = new WC_Product_Attribute();
		$attr->set_id(0);
		$attr->set_name('Plan');
		$attr->set_options($labels);
		$attr->set_position(0);
		$attr->set_visible(false);
		$attr->set_variation(true);
		$parent->set_attributes(['plan' => $attr]);

		// The service's store name, unless the operator renamed the product themselves.
		$service_name = Panelr_Helpers::service_name($plugin_id);
		$last_name    = (string) $parent->get_meta('_panelr_synced_name');
		if ($service_name !== '' && ($overwrite || $last_name === '' || $parent->get_name() === $last_name)) {
			$parent->set_name($service_name);
		}
		if ($service_name !== '') $parent->update_meta_data('_panelr_synced_name', $service_name);

		if (!$options) {
			if ($parent->get_status() === 'publish') {
				$parent->set_status('draft');
				$parent->update_meta_data('_panelr_drafted_empty', '1');
			}
		} elseif ($parent->get_status() === 'draft' && $parent->get_meta('_panelr_drafted_empty')) {
			$parent->set_status('publish');
			$parent->set_catalog_visibility('visible');
			$parent->delete_meta_data('_panelr_drafted_empty');
		}
		if (Panelr_Helpers::bool_option('panelr_product_categories', '1')) {
			self::file_under_service($parent, $plugin_id, $service_name);
		}
		$parent->save();
		WC_Product_Variable::sync($parent_id);
	}

	/** Park a product sold the other way now: it keeps everything but stops standing for the plan. Never deleted. */
	private static function retire(WC_Product $product): void
	{
		$product->update_meta_data('_panelr_product_id_was', (int) $product->get_meta('_panelr_product_id'));
		$product->delete_meta_data('_panelr_product_id');
		$product->set_status($product->is_type('variation') ? 'private' : 'draft');
		$product->save();
		if ($product->is_type('variation')) {
			self::$touched_parents[$product->get_parent_id()] = true;
		}
	}

	/** The product parked earlier for this plan, sold this way, brought back (not saved here). */
	private static function revive(int $panelr_id, string $mode, int $plugin_id): ?WC_Product
	{
		$posts = get_posts([
			'post_type'      => $mode === 'options' ? 'product_variation' : 'product',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'meta_key'       => '_panelr_product_id_was', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- a parked plan
			'meta_value'     => $panelr_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'fields'         => 'ids',
			'no_found_rows'  => true,
		]);
		$product = $posts ? wc_get_product($posts[0]) : null;
		if (!$product) return null;
		if ($product->is_type('variation') && (int) $product->get_parent_id() !== Panelr_Helpers::service_product_id($plugin_id)) return null;
		$product->update_meta_data('_panelr_product_id', $panelr_id);
		$product->delete_meta_data('_panelr_product_id_was');
		$product->set_status('publish');
		if (!$product->is_type('variation')) $product->set_catalog_visibility('visible');
		return $product;
	}

	private static array $touched_parents = [];

	// ── Sync ──────────────────────────────────────────────────────────────

	public static function sync(): array
	{
		Panelr_API::flush_cache();
		$api    = Panelr_API::instance();
		$result = $api->get_products();

		if (!$result['ok'] || !is_array($result['data'])) {
			return ['success' => false, 'message' => $result['error'] ?: __('Panelr did not answer. Check the connection and try again.', 'panelr-for-woocommerce')];
		}

		$overwrite    = Panelr_Helpers::bool_option('panelr_sync_overwrite_edits', '0');
		$use_cats     = Panelr_Helpers::bool_option('panelr_product_categories', '1');
		$seen         = [];
		$created      = 0;
		$updated      = 0;
		$drafted      = 0;
		$kept_private = 0;
		$categorised  = 0;

		self::$touched_parents = [];
		$moved = 0;

		foreach ($result['data'] as $p) {
			$panelr_id = (int) $p['id'];
			$seen[]    = $panelr_id;
			$plugin_id = (int) ($p['plugin_id'] ?? 0);
			$mode      = self::mode_for($p);
			$wc_id     = Panelr_Helpers::wc_product_id_for($panelr_id);
			$product   = $wc_id ? wc_get_product($wc_id) : null;

			// Sold the other way now ("Sell as" changed): park the old product and file the plan the new way.
			$carried_name = null;
			if ($product && $product->is_type('variation') !== ($mode === 'options')) {
				$carried_name = (string) $product->get_meta('_panelr_store_name');
				self::retire($product);
				$product = null;
				$moved++;
			}
			$is_new = !$product;

			if ($is_new) {
				$product = self::revive($panelr_id, $mode, $plugin_id);
				if ($product) {
					$is_new = false;
				} else {
					$product = self::new_product($p, $mode);
				}
				// The name the store gave the plan stays with the plan, however it is sold.
				if ($carried_name !== null) {
					if ($carried_name === '') $product->delete_meta_data('_panelr_store_name');
					else $product->update_meta_data('_panelr_store_name', $carried_name);
				}
				self::apply_store_name($product);
				if (!$is_new) self::apply_panelr_values($product, $p, $overwrite);
			} else {
				self::apply_panelr_values($product, $p, $overwrite);
			}

			// Brought back by Panelr after being drafted here.
			if ($product->get_meta('_panelr_removed_at')) {
				$product->delete_meta_data('_panelr_removed_at');
				if ($product->get_status() === 'draft' || ($product->is_type('variation') && $product->get_status() === 'private')) {
					$product->set_status('publish');
				}
			}

			$is_trial = !empty($p['is_trial']);
			if ($is_trial) {
				if ($product->get_status() !== 'private') {
					$product->set_status('private');
				}
				$product->set_catalog_visibility('hidden');
				$kept_private++;
			} elseif ($is_new || $product->get_status() === 'private' && $product->get_meta('_panelr_is_trial') === '1') {
				// A plan that stopped being the trial (or a brand-new one) is for sale.
				$product->set_status('publish');
				$product->set_catalog_visibility('visible');
			}

			self::write_panelr_meta($product, $p);

			if ($product->is_type('variation')) {
				self::$touched_parents[$product->get_parent_id()] = true;
				if ($use_cats) $categorised++;
			} elseif ($use_cats && self::file_under_service($product, $plugin_id, (string) ($p['plugin_name'] ?? ''))) {
				$categorised++;
			}

			$product->save();
			$is_new ? $created++ : $updated++;
		}
		Panelr_Helpers::flush_products();

		// Gone from Panelr → draft, with a note. Never deleted.
		$gone = [];
		foreach (Panelr_Helpers::synced_products(false) as $row) {
			if (in_array($row['panelr_id'], $seen, true)) continue;
			$product = wc_get_product($row['wc_id']);
			if (!$product || $product->get_status() === 'draft' || $row['is_variation'] && $product->get_status() === 'private') continue;
			$product->set_status($row['is_variation'] ? 'private' : 'draft');
			$product->update_meta_data('_panelr_removed_at', current_time('mysql', true));
			$product->save();
			if ($row['is_variation']) self::$touched_parents[$row['parent_id']] = true;
			$drafted++;
			$gone[] = $row['name'];
		}

		// Every service product whose options changed: option list, name, price range.
		foreach (array_keys(self::$touched_parents) as $parent_id) {
			self::refresh_service_product((int) $parent_id, $overwrite);
		}
		Panelr_Helpers::flush_products();

		if ($gone) {
			update_option('panelr_sync_notes', [sprintf(
				/* translators: %s: comma-separated product names */
				__('No longer offered by Panelr and set to draft: %s', 'panelr-for-woocommerce'),
				implode(', ', $gone)
			)], false);
		}

		update_option('panelr_last_sync', current_time('mysql'));
		Panelr_API::flush_cache();

		return [
			'success' => true,
			'message' => sprintf(
				/* translators: 1: created count, 2: updated count, 3: drafted count */
				__('Sync complete: %1$d added, %2$d updated, %3$d set to draft.', 'panelr-for-woocommerce'),
				$created,
				$updated,
				$drafted
			) . ($moved ? ' ' . sprintf(
				/* translators: %d: count */
				_n('%d plan refiled the way its service is sold now.', '%d plans refiled the way their services are sold now.', $moved, 'panelr-for-woocommerce'),
				$moved
			) : '') . ($use_cats ? ' ' . sprintf(
				/* translators: %d: count */
				_n('%d plan filed under its service category.', '%d plans filed under their service categories.', $categorised, 'panelr-for-woocommerce'),
				$categorised
			) : ''),
			'created' => $created,
			'updated' => $updated,
			'drafted' => $drafted,
			'trials'  => $kept_private,
		];
	}

	/**
	 * Price, name, description, connections and duration from Panelr — but
	 * only where the operator has not edited them since the last sync
	 * (compared with the last synced value kept in meta), unless the
	 * "Sync overwrites my edits" switch is on.
	 */
	private static function apply_panelr_values(WC_Product $product, array $p, bool $overwrite): void
	{
		$last_name  = (string) $product->get_meta('_panelr_synced_name');
		$last_price = (string) $product->get_meta('_panelr_synced_price');
		$last_desc  = (string) $product->get_meta('_panelr_synced_description');

		$new_name  = (string) $p['name'];
		$new_price = (string) $p['price_decimal'];
		$new_desc  = (string) ($p['description'] ?? '');

		// The store name typed in the Products table always wins; an option's
		// label is set from it (or Panelr's name) when its product is refreshed.
		if ((string) $product->get_meta('_panelr_store_name') === '' && !$product->is_type('variation')
			&& ($overwrite || $last_name === '' || $product->get_name() === $last_name)) {
			$product->set_name($new_name);
		}
		if ($overwrite || $last_price === '' || (string) $product->get_regular_price() === $last_price) {
			$product->set_regular_price($new_price);
		}
		if ($new_desc !== '' && self::sync_descriptions() && ($overwrite || $last_desc === '' || $product->get_description() === $last_desc)) {
			$product->set_description($new_desc);
		}
	}

	/** Off: plan descriptions are the store's own and a sync never writes them. */
	private static function sync_descriptions(): bool
	{
		return Panelr_Helpers::bool_option('panelr_sync_descriptions', '1');
	}

	/** The v2 meta, written on every sync and by the upgrade backfill. */
	public static function write_panelr_meta(WC_Product $product, array $p): void
	{
		$product->update_meta_data('_panelr_product_id',            (int) $p['id']);
		$product->update_meta_data('_panelr_plugin_id',             (int) ($p['plugin_id'] ?? 0));
		$product->update_meta_data('_panelr_alternate_name',        (string) ($p['alternate_name'] ?? ''));
		$product->update_meta_data('_panelr_panel_product_id',      (string) ($p['panel_product_id'] ?? ''));
		$product->update_meta_data('_panelr_connections',           (int) ($p['connections'] ?? 0));
		$product->update_meta_data('_panelr_duration_months',       (int) ($p['duration_months'] ?? 0));
		$product->update_meta_data('_panelr_currency',              (string) ($p['currency'] ?? ''));
		$product->update_meta_data('_panelr_is_trial',              !empty($p['is_trial']) ? '1' : '0');
		$product->update_meta_data('_panelr_referral_earn_points',  (int) ($p['referral_earn_points'] ?? 0));
		$product->update_meta_data('_panelr_referral_cost_points',  (int) ($p['referral_cost_points'] ?? 0));
		$product->update_meta_data('_panelr_synced_name',           (string) $p['name']);
		$product->update_meta_data('_panelr_synced_price',          (string) $p['price_decimal']);
		$product->update_meta_data('_panelr_synced_description',    (string) ($p['description'] ?? ''));
		$product->update_meta_data('_panelr_synced_at',             current_time('mysql', true));
	}

	/** Put one product in its service's category (the product object is not saved here). True when it was (already) filed. */
	private static function file_under_service(WC_Product $product, int $plugin_id, string $service_name): bool
	{
		if (!$plugin_id) return false;
		$service_name = Panelr_Helpers::service_name($plugin_id) ?: $service_name;
		if ($service_name === '') return false;
		$term_id = self::service_category($plugin_id, $service_name);
		if (!$term_id) return false;
		$cats = array_map('intval', (array) $product->get_category_ids());
		if (!in_array($term_id, $cats, true)) {
			$cats[] = $term_id;
			$product->set_category_ids($cats);
		}
		return true;
	}

	/** File every synced plan under its service. Runs when the setting is switched on. */
	public static function apply_categories(): int
	{
		$done = 0;
		$seen_parents = [];
		foreach (Panelr_Helpers::synced_products(false) as $row) {
			if ($row['is_variation']) {
				if (isset($seen_parents[$row['parent_id']])) continue;
				$seen_parents[$row['parent_id']] = true;
			}
			$product = wc_get_product($row['is_variation'] ? $row['parent_id'] : $row['wc_id']);
			if (!$product || !$row['plugin_id']) continue;
			if (self::file_under_service($product, $row['plugin_id'], '')) {
				$product->save();
				$done++;
			}
		}
		return $done;
	}

	/** The product category for a service, created on first use and remembered by plugin id. */
	private static function service_category(int $plugin_id, string $name): int
	{
		if (!taxonomy_exists('product_cat')) {
			wc_get_logger()->error('product_cat taxonomy not registered when filing plans', ['source' => 'panelr']);
			return 0;
		}
		// A term query only filters on meta through meta_query; a top-level
		// meta_key / meta_value pair is ignored and used to make a second
		// category whenever a service's store name changed.
		$terms = get_terms([
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'meta_query' => [['key' => '_panelr_plugin_id', 'value' => (string) $plugin_id]], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- a handful of terms
			'fields'     => 'ids',
		]);
		if (!is_wp_error($terms) && $terms) {
			return (int) $terms[0];
		}

		$existing = term_exists($name, 'product_cat');
		if ($existing && !is_wp_error($existing)) {
			$term_id = (int) (is_array($existing) ? $existing['term_id'] : $existing);
		} else {
			$created = wp_insert_term($name, 'product_cat');
			if (is_wp_error($created)) {
				wc_get_logger()->error('Could not create the category "' . $name . '": ' . $created->get_error_message(), ['source' => 'panelr']);
				return 0;
			}
			$term_id = (int) $created['term_id'];
		}
		update_term_meta($term_id, '_panelr_plugin_id', $plugin_id);
		return $term_id;
	}

	// ── Storefront ────────────────────────────────────────────────────────

	/** Service name under the product meta when the store has more than one service. */
	public static function show_service_on_product(): void
	{
		global $product;
		if (!$product instanceof WC_Product) return;
		$plugin_id = (int) $product->get_meta('_panelr_plugin_id');
		if (!$plugin_id || !Panelr_Helpers::multi_service() || !Panelr_Wording::show('product_service')) return;
		$name = Panelr_Helpers::service_name($plugin_id);
		if (!$name) return;
		echo '<span class="panelr-product-service"><span class="panelr-product-service__label">' . esc_html(Panelr_Wording::term('service')) . ':</span> ' . esc_html($name) . '</span>';
	}

	/** "Add another service" — the other services' plans, each its own cart line. */
	public static function show_addons(): void
	{
		global $product;
		if (!$product instanceof WC_Product) return;
		$plugin_id = (int) $product->get_meta('_panelr_plugin_id');
		if (!$plugin_id || !Panelr_Helpers::multi_service()) return;

		$groups = [];
		foreach (Panelr_Helpers::synced_products(true) as $row) {
			if ($row['is_trial'] || !$row['plugin_id'] || $row['plugin_id'] === $plugin_id) continue;
			$groups[$row['plugin_id']][] = $row;
		}
		if (!$groups) return;

		wp_enqueue_script('panelr-common');
		Panelr_Template::output('product-addons', [
			'groups'   => $groups,
			'services' => Panelr_Helpers::services(),
			'nonce'    => wp_create_nonce('panelr_addon'),
		]);
	}

	public static function ajax_add_addon(): void
	{
		check_ajax_referer('panelr_addon', 'nonce');
		$panelr_id = absint(wp_unslash($_POST['panelr_product_id'] ?? 0));
		$wc_id     = Panelr_Helpers::wc_product_id_for($panelr_id, true);
		if (!$wc_id) {
			wp_send_json_error(['message' => __('That plan is not available right now.', 'panelr-for-woocommerce')]);
		}
		$product = wc_get_product($wc_id);
		$key = Panelr_Helpers::add_plan_to_cart($wc_id, 1, [
			'_panelr_intent'    => 'new_activation',
			'_panelr_plugin_id' => (int) $product->get_meta('_panelr_plugin_id'),
		]);
		if (!$key) {
			wp_send_json_error(['message' => __('That plan could not be added to the cart.', 'panelr-for-woocommerce')]);
		}
		wp_send_json_success([
			'message'  => __('Added to your cart.', 'panelr-for-woocommerce'),
			'cart_url' => wc_get_cart_url(),
		]);
	}

	// ── Admin notices ─────────────────────────────────────────────────────

	public static function notices(): void
	{
		if (!current_user_can('manage_options')) return;
		$notes = get_option('panelr_sync_notes', []);
		if (!is_array($notes) || !$notes) return;
		echo '<div class="notice notice-warning is-dismissible panelr-upgrade-notice" data-panelr-notice="sync"><p><strong>' . esc_html__('Panelr products', 'panelr-for-woocommerce') . '</strong></p>';
		foreach ($notes as $note) {
			echo '<p>' . esc_html($note) . '</p>';
		}
		echo '</div>';
	}

	/** Rows for the settings page. */
	public static function get_synced_products(): array
	{
		return Panelr_Helpers::synced_products(false);
	}
}
