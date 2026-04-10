<?php
defined('ABSPATH') || exit;

class Panelr_Sync
{

	public static function init(): void
	{
		add_action('wp_ajax_panelr_sync_products', [__CLASS__, 'ajax_sync']);
	}

	// ── AJAX handler ───────────────────────────────────────────────────────

	public static function ajax_sync(): void
	{
		check_ajax_referer('panelr_admin_nonce', 'nonce');
		if (!current_user_can('manage_options')) {
			wp_send_json_error(['message' => 'Unauthorized']);
		}

		$result = self::sync();

		if ($result['success']) {
			wp_send_json_success($result);
		} else {
			wp_send_json_error($result);
		}
	}

	// ── Sync ───────────────────────────────────────────────────────────────

	public static function sync(): array
	{
		$api    = new Panelr_API();
		$result = $api->get_products();

		if (empty($result['success']) || empty($result['data'])) {
			return ['success' => false, 'message' => 'Could not fetch products from Panelr.'];
		}

		$synced  = 0;
		$created = 0;
		$updated = 0;

		foreach ($result['data'] as $panelr_product) {
			$panelr_id = (int) $panelr_product['id'];

			$existing = self::find_wc_product($panelr_id);

			if ($existing) {
				self::update_product($existing, $panelr_product);
				$updated++;
			} else {
				self::create_product($panelr_product);
				$created++;
			}

			$synced++;
		}

		update_option('panelr_last_sync', current_time('mysql'));

		return [
			'success' => true,
			'message' => "Sync complete. {$created} created, {$updated} updated.",
			'synced'  => $synced,
			'created' => $created,
			'updated' => $updated,
		];
	}

	// ── Find existing WC product by Panelr ID ──────────────────────────────

	private static function find_wc_product(int $panelr_id): ?WC_Product
	{
		$posts = get_posts([
			'post_type'      => 'product',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'meta_key'       => '_panelr_product_id',
			'meta_value'     => $panelr_id,
			'fields'         => 'ids',
		]);

		if (empty($posts)) {
			return null;
		}

		return wc_get_product($posts[0]);
	}

	// ── Create new WC product ──────────────────────────────────────────────

	private static function create_product(array $p): void
	{
		$product = new WC_Product_Simple();
		self::apply_product_data($product, $p);
		$product->save();
	}

	// ── Update existing WC product ─────────────────────────────────────────

	private static function update_product(WC_Product $product, array $p): void
	{
		self::apply_product_data($product, $p);
		$product->save();
	}

	// ── Apply Panelr data to WC product ───────────────────────────────────

	private static function apply_product_data(WC_Product $product, array $p): void
	{
		$product->set_name($p['name']);
		$product->set_regular_price((string) $p['price_decimal']);
		$product->set_virtual(true);
		$product->set_sold_individually(true);

		$trial_product_id = (int) get_option('panelr_trial_product_id', 0);
		$is_trial         = $trial_product_id && $trial_product_id === (int) $p['id'];
		$product->set_status($is_trial ? 'private' : 'publish');

		if (!empty($p['description'])) {
			$product->set_description($p['description']);
		}

		$product->update_meta_data('_panelr_product_id',       (int)    $p['id']);
		$product->update_meta_data('_panelr_alternate_name',   (string) ($p['alternate_name'] ?? ''));
		$product->update_meta_data('_panelr_panel_product_id', (string) $p['panel_product_id']);
		$product->update_meta_data('_panelr_connections',      (int)    $p['connections']);
		$product->update_meta_data('_panelr_duration_months',  (int)    $p['duration_months']);
		$product->update_meta_data('_panelr_currency',         (string) $p['currency']);
	}

	// ── Get all synced products for display ────────────────────────────────

	public static function get_synced_products(): array
	{
		$posts = get_posts([
			'post_type'      => 'product',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'meta_key'       => '_panelr_product_id',
			'fields'         => 'ids',
		]);

		$products = [];
		foreach ($posts as $post_id) {
			$products[] = [
				'wc_id'           => $post_id,
				'name'            => get_the_title($post_id),
				'panelr_id'       => get_post_meta($post_id, '_panelr_product_id',     true),
				'connections'     => get_post_meta($post_id, '_panelr_connections',     true),
				'duration_months' => get_post_meta($post_id, '_panelr_duration_months', true),
			];
		}

		return $products;
	}

	public static function update_trial_product_visibility(int $panelr_product_id): void
	{
		$posts = get_posts([
			'post_type'      => 'product',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'meta_key'       => '_panelr_product_id',
			'meta_value'     => $panelr_product_id,
			'fields'         => 'ids',
		]);

		if (empty($posts)) return;

		$product = wc_get_product($posts[0]);
		if (!$product) return;

		$product->set_status($panelr_product_id ? 'private' : 'publish');
		$product->set_catalog_visibility('hidden');
		$product->save();
	}
}
