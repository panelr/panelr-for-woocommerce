<?php
defined('ABSPATH') || exit;

/**
 * [panelr_apps] — the App Downloads page as Panelr's own shows it: sections
 * per platform ("All Devices" first), each app with logo, description,
 * download button and downloader code. Honours apps_page (off / members /
 * public) and narrows to the customer's services when they hold lines.
 */
class Panelr_Apps
{
	public static function init(): void
	{
		add_shortcode('panelr_apps', [__CLASS__, 'render']);
	}

	public static function render($atts = []): string
	{
		return self::render_page(false);
	}

	public static function render_page(bool $inside_portal): string
	{
		wp_enqueue_script('panelr-common');
		$mode = Panelr_Helpers::apps_page_mode();
		if ($mode === 'off') {
			return $inside_portal ? '' : Panelr_Template::notice(__('App downloads are not available right now.', 'panelr-for-woocommerce'), 'info');
		}
		$signed_in = Panelr_Session::is_signed_in() || Panelr_Session::line_session() !== null;
		if ($mode === 'members' && !$signed_in) {
			return Panelr_Template::render('apps-sign-in', [
				'sign_in_url' => Panelr_Helpers::portal_url(['return' => rawurlencode(home_url(esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'] ?? '/'))))]),
			]);
		}

		$result = Panelr_API::instance()->get_apps();
		if (!$result['ok'] || !is_array($result['data'])) {
			return Panelr_Template::notice(__('App downloads could not be loaded right now.', 'panelr-for-woocommerce'), 'info');
		}
		$data = $result['data'];
		$apps = [];
		foreach ((array) ($data['apps'] ?? []) as $app) {
			$apps[(int) $app['app_id']] = $app;
		}

		// The customer's services, unless they asked for everything.
		$mine = [];
		$show_all = isset($_GET['all']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a view switch; read only
		if ($signed_in && !$show_all) {
			$lines = Panelr_Session::is_signed_in() ? Panelr_Session::lines() : [Panelr_Session::line_session()];
			foreach ($lines as $line) {
				if (!empty($line['plugin_id'])) $mine[] = (int) $line['plugin_id'];
			}
			$mine = array_unique($mine);
		}

		$sections = [];
		foreach ((array) ($data['sections'] ?? []) as $section) {
			$list = [];
			foreach ((array) ($section['app_ids'] ?? []) as $id) {
				$app = $apps[(int) $id] ?? null;
				if (!$app) continue;
				if ($mine) {
					$svc = array_map(fn($s) => (int) $s['plugin_id'], (array) ($app['services'] ?? []));
					if ($svc && !array_intersect($svc, $mine)) continue;
				}
				$list[] = $app;
			}
			if ($list) {
				$sections[] = ['name' => $section['name'], 'icon' => $section['icon'] ?? '', 'logo_url' => $section['logo_url'] ?? '', 'apps' => $list];
			}
		}

		return Panelr_Template::render('apps', [
			'sections'  => $sections,
			'filtered'  => (bool) $mine,
			'show_all_url' => add_query_arg('all', '1'),
			'multi'     => Panelr_Helpers::multi_service(),
		]);
	}
}
