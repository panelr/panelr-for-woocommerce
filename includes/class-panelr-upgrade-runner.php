<?php
defined('ABSPATH') || exit;

/**
 * Versioned upgrade steps, run once on activation or on the first admin
 * request after the plugin files change. Old-version steps keep running in
 * order for a store that skips releases.
 */
class Panelr_Upgrade_Runner
{
	const OPTION = 'panelr_db_version';

	public static function init(): void
	{
		add_action('admin_init', [__CLASS__, 'maybe_run'], 5);
		add_action('admin_notices', [__CLASS__, 'notices']);
		add_action('wp_ajax_panelr_dismiss_notice', [__CLASS__, 'ajax_dismiss_notice']);
	}

	public static function maybe_run(): void
	{
		$current = (string) get_option(self::OPTION, '');
		if ($current === PANELR_DB_VERSION && get_option('panelr_upgrade_due', '0') !== '1') {
			return;
		}
		if (get_transient('panelr_upgrade_running')) {
			return;
		}
		set_transient('panelr_upgrade_running', 1, 5 * MINUTE_IN_SECONDS);

		$steps = [
			'2.0.0' => [__CLASS__, 'to_2_0_0'],
		];

		foreach ($steps as $version => $callback) {
			if ($current === '' || version_compare($current, $version, '<')) {
				call_user_func($callback, $current);
			}
		}

		update_option(self::OPTION, PANELR_DB_VERSION, false);
		delete_option('panelr_upgrade_due');
		delete_transient('panelr_upgrade_running');
	}

	/**
	 * 1.x → 2.0.0
	 * - read the old options as they are (nothing renamed or deleted)
	 * - drop panelr_payment_mode_map (two writers corrupted each other)
	 * - api key autoload off
	 * - backfill _panelr_plugin_id and the other v2 meta on every synced product
	 * - un-hide the product v1 made private for the trial (Panelr decides now)
	 */
	public static function to_2_0_0(string $from): void
	{
		delete_option('panelr_payment_mode_map');

		// Move the key to a non-autoloaded option row.
		$key = get_option('panelr_api_key', null);
		if ($key !== null) {
			delete_option('panelr_api_key');
			add_option('panelr_api_key', $key, '', 'no');
		}

		$notes = [];
		$fresh = ($from === '' && get_option('panelr_api_url', '') === '');

		if (!$fresh) {
			$backfill = self::backfill_products();
			if ($backfill['drafted']) {
				$notes[] = sprintf(
					/* translators: %d: number of products */
					_n('%d product is no longer offered by Panelr and was set to draft.', '%d products are no longer offered by Panelr and were set to draft.', $backfill['drafted'], 'panelr-for-woocommerce'),
					$backfill['drafted']
				);
			}
			if ($backfill['error']) {
				$notes[] = __('Products could not be matched with Panelr during the update. Open Panelr → Services & Products and press Sync now.', 'panelr-for-woocommerce');
			}

			if ((int) get_option('panelr_trial_product_id', 0)) {
				$notes[] = __('The trial plan is now chosen in Panelr, per service. The old "Trial product" setting no longer does anything.', 'panelr-for-woocommerce');
			}
			$notes[] = __('Members sign in with their email and password now. Anyone who was signed in with connection details will sign in again.', 'panelr-for-woocommerce');
		}

		if ($notes) {
			update_option('panelr_upgrade_notes', $notes, false);
		}
		Panelr_API::flush_cache();
	}

	/**
	 * One get_products call; match by _panelr_product_id; write the v2 meta;
	 * draft what Panelr no longer has. Nothing is deleted.
	 * @return array{updated:int,drafted:int,error:bool}
	 */
	public static function backfill_products(): array
	{
		$api    = Panelr_API::instance();
		$result = $api->is_configured() ? $api->get_products() : ['ok' => false, 'data' => null];
		if (!$result['ok'] || !is_array($result['data'])) {
			return ['updated' => 0, 'drafted' => 0, 'error' => true];
		}

		$by_id = [];
		foreach ($result['data'] as $p) {
			$by_id[(int) $p['id']] = $p;
		}

		$updated = 0;
		$drafted = 0;
		$old_trial = (int) get_option('panelr_trial_product_id', 0);

		foreach (Panelr_Helpers::synced_products(false) as $row) {
			$product = wc_get_product($row['wc_id']);
			if (!$product) continue;

			$p = $by_id[$row['panelr_id']] ?? null;
			if (!$p) {
				if ($product->get_status() !== 'draft') {
					$product->set_status('draft');
					$product->update_meta_data('_panelr_removed_at', current_time('mysql', true));
					$product->save();
					$drafted++;
				}
				continue;
			}

			Panelr_Sync::write_panelr_meta($product, $p);

			// v1 made the trial product private by hand; Panelr's is_trial decides now.
			if ($old_trial && $old_trial === $row['panelr_id'] && empty($p['is_trial']) && $product->get_status() === 'private') {
				$product->set_status('publish');
				$product->set_catalog_visibility('visible');
			}
			if (!empty($p['is_trial']) && $product->get_status() === 'publish') {
				$product->set_status('private');
			}
			$product->save();
			$updated++;
		}

		return ['updated' => $updated, 'drafted' => $drafted, 'error' => false];
	}

	// ── Notices ───────────────────────────────────────────────────────────

	public static function notices(): void
	{
		if (!current_user_can('manage_options')) return;
		$notes = get_option('panelr_upgrade_notes', []);
		if (!is_array($notes) || !$notes) return;

		echo '<div class="notice notice-info is-dismissible panelr-upgrade-notice" data-panelr-notice="upgrade"><p><strong>'
			. esc_html__('Panelr for WooCommerce was updated.', 'panelr-for-woocommerce') . '</strong></p><ul>';
		foreach ($notes as $note) {
			echo '<li>' . esc_html($note) . '</li>';
		}
		echo '</ul><p><a class="button" href="' . esc_url(admin_url('admin.php?page=panelr')) . '">' . esc_html__('Open Panelr settings', 'panelr-for-woocommerce') . '</a></p></div>';
	}

	public static function ajax_dismiss_notice(): void
	{
		check_ajax_referer('panelr_admin_nonce', 'nonce');
		if (!current_user_can('manage_options')) wp_send_json_error();
		$which = sanitize_key(wp_unslash($_POST['notice'] ?? ''));
		if ($which === 'upgrade') delete_option('panelr_upgrade_notes');
		if ($which === 'sync') delete_option('panelr_sync_notes');
		wp_send_json_success();
	}
}
