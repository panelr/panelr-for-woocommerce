<?php
defined('ABSPATH') || exit;

/**
 * Every customer-facing block renders through wc_get_template() from the
 * plugin's templates/ folder; a theme overrides any of them by copying the
 * file to woocommerce/panelr/<name>.php.
 */
class Panelr_Template
{
	public static function render(string $name, array $args = []): string
	{
		ob_start();
		self::output($name, $args);
		return (string) ob_get_clean();
	}

	public static function output(string $name, array $args = []): void
	{
		wc_get_template(
			'panelr/' . $name . '.php',
			$args,
			'',
			PANELR_PLUGIN_DIR . 'templates/'
		);
	}

	/** A notice block in the plugin's own class names. */
	public static function notice(string $message, string $type = 'error'): string
	{
		$class = $type === 'success' ? 'panelr-notice panelr-notice--success woocommerce-message'
			: ($type === 'info' ? 'panelr-notice panelr-notice--info woocommerce-info' : 'panelr-notice panelr-notice--error woocommerce-error panelr-portal__error');
		return '<div class="' . esc_attr($class) . '" role="alert">' . esc_html($message) . '</div>';
	}
}
