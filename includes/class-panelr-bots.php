<?php
defined('ABSPATH') || exit;

/**
 * The store's Telegram bot and Discord server: links the operator types in
 * (Panelr does not expose them), shown as buttons in the Account tab and,
 * when switched on, in a small footer bar.
 */
class Panelr_Bots
{
	public static function init(): void
	{
		add_action('wp_footer', [__CLASS__, 'footer']);
	}

	/** @return array<int, array{platform:string,label:string,url:string}> */
	public static function links(): array
	{
		if (!Panelr_Helpers::bool_option('panelr_bots_enabled', '0')) return [];
		$out = [];
		$tg = esc_url_raw((string) get_option('panelr_bot_telegram_url', ''));
		$dc = esc_url_raw((string) get_option('panelr_bot_discord_url', ''));
		if ($tg) $out[] = ['platform' => 'telegram', 'label' => __('Get messages in Telegram', 'panelr-for-woocommerce'), 'url' => $tg];
		if ($dc) $out[] = ['platform' => 'discord', 'label' => __('Get messages in Discord', 'panelr-for-woocommerce'), 'url' => $dc];
		return $out;
	}

	public static function footer(): void
	{
		if (!Panelr_Helpers::bool_option('panelr_bots_footer', '0')) return;
		$links = self::links();
		if (!$links) return;
		Panelr_Template::output('bots-footer', ['links' => $links]);
	}
}
