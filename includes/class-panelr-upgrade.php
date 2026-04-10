<?php
defined('ABSPATH') || exit;

class Panelr_Upgrade
{

	public static function init(): void
	{
		add_shortcode('panelr_upgrade', [__CLASS__, 'render']);
		add_action('wp_ajax_panelr_upgrade_verify',             [__CLASS__, 'ajax_verify']);
		add_action('wp_ajax_nopriv_panelr_upgrade_verify',      [__CLASS__, 'ajax_verify']);
		add_action('wp_ajax_panelr_upgrade_add_to_cart',        [__CLASS__, 'ajax_add_to_cart']);
		add_action('wp_ajax_nopriv_panelr_upgrade_add_to_cart', [__CLASS__, 'ajax_add_to_cart']);
	}

	// ── AJAX: verify trial code ────────────────────────────────────────────

	public static function ajax_verify(): void
	{
		check_ajax_referer('panelr_upgrade_nonce', 'nonce');

		$trial_code = strtoupper(sanitize_text_field(wp_unslash($_POST['trial_code'] ?? '')));
		if (!$trial_code) {
			wp_send_json_error(['message' => __('Please enter your trial code.', 'panelr-for-woocommerce')]);
		}

		$api    = new Panelr_API();
		$result = $api->verify_trial($trial_code);

		if (empty($result['success'])) {
			wp_send_json_error(['message' => $result['error'] ?? __('Invalid or expired trial code.', 'panelr-for-woocommerce')]);
		}

		$data = $result['data'];
		if (WC()->session) {
			WC()->session->set('panelr_upgrade_activation_id',    (int) $data['activation_id']);
			WC()->session->set('panelr_upgrade_trial_request_id', (int) $data['trial_request_id']);
			WC()->session->set('panelr_upgrade_customer_name',    $data['customer_name'] ?? '');
			WC()->session->set('panelr_upgrade_customer_email',   $data['customer_email'] ?? '');
			WC()->session->set('panelr_upgrade_expiration_date',  $data['expiration_date'] ?? '');
			WC()->session->set('panelr_upgrade_eligible_products', $data['eligible_products'] ?? []);
		}

		wp_send_json_success($data);
	}

	// ── AJAX: add upgrade product to cart ──────────────────────────────────

	public static function ajax_add_to_cart(): void
	{
		check_ajax_referer('panelr_upgrade_nonce', 'nonce');

		$panelr_product_id = (int) sanitize_text_field(wp_unslash($_POST['panelr_product_id'] ?? 0));
		$activation_id     = (int) (WC()->session ? WC()->session->get('panelr_upgrade_activation_id') : 0);

		if (!$panelr_product_id || !$activation_id) {
			wp_send_json_error(['message' => __('Invalid request. Please verify your trial code again.', 'panelr-for-woocommerce')]);
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
			wp_send_json_error(['message' => __('Product not found. Please contact support.', 'panelr-for-woocommerce')]);
		}

		$cart_item_key = WC()->cart->add_to_cart($posts[0], 1, 0, [], [
			'_panelr_intent'        => 'trial_upgrade',
			'_panelr_activation_id' => $activation_id,
		]);

		if (!$cart_item_key) {
			wp_send_json_error(['message' => __('Could not add product to cart.', 'panelr-for-woocommerce')]);
		}

		wp_send_json_success(['checkout_url' => wc_get_checkout_url()]);
	}

	// ── Render shortcode ───────────────────────────────────────────────────

	public static function render(): string
	{
		if (WC()->session && !WC()->session->has_session()) {
			WC()->session->set_customer_session_cookie(true);
		}

		wp_enqueue_script(
			'panelr-upgrade',
			PANELR_PLUGIN_URL . 'assets/js/upgrade.js',
			['jquery'],
			PANELR_VERSION,
			true
		);
		wp_localize_script('panelr-upgrade', 'panelrUpgrade', [
			'ajaxurl'    => admin_url('admin-ajax.php'),
			'nonce'      => wp_create_nonce('panelr_upgrade_nonce'),
			'portal_url' => get_permalink((int) get_option('panelr_page_portal', 0)) ?: '',
		]);

		// Auto-verify if trial code in URL (?panelr_t=TRIALCODE)
		// Note: nonce verification is not applicable here — this parameter arrives
		// via email link from an external source and is used only to pre-fill a form field.
		$token = sanitize_text_field(wp_unslash($_GET['panelr_t'] ?? ''));

		ob_start();
?>
		<div class="panelr-upgrade" id="panelr-upgrade-wrap">

			<!-- Code entry form -->
			<div id="panelr-upgrade-form" <?php echo $token ? 'style="display:none;"' : ''; ?>>
				<p id="panelr-upgrade-error" class="panelr-upgrade__error woocommerce-error" style="display:none;"></p>
				<p class="panelr-upgrade__field">
					<label for="panelr-upgrade-code"><?php esc_html_e('Enter your trial code', 'panelr-for-woocommerce'); ?></label>
					<input type="text" id="panelr-upgrade-code" class="input-text"
						placeholder="e.g. SKR72452"
						value="<?php echo esc_attr(strtoupper($token)); ?>">
				</p>
				<p class="panelr-upgrade__field">
					<button type="button" id="panelr-upgrade-verify" class="button">
						<?php esc_html_e('Continue', 'panelr-for-woocommerce'); ?>
					</button>
				</p>
			</div>

			<!-- Account + product selection (shown after verify) -->
			<div id="panelr-upgrade-dashboard" style="display:none;">
				<section class="panelr-upgrade__account">
					<h3><?php esc_html_e('Your Trial Account', 'panelr-for-woocommerce'); ?></h3>
					<table class="panelr-upgrade__table">
						<tr>
							<th><?php esc_html_e('Name', 'panelr-for-woocommerce'); ?></th>
							<td id="panelr-upgrade-name"></td>
						</tr>
						<tr>
							<th><?php esc_html_e('Email', 'panelr-for-woocommerce'); ?></th>
							<td id="panelr-upgrade-email"></td>
						</tr>
						<tr>
							<th><?php esc_html_e('Trial Expires', 'panelr-for-woocommerce'); ?></th>
							<td id="panelr-upgrade-expiry"></td>
						</tr>
					</table>
					<?php $portal_url = get_permalink((int) get_option('panelr_page_portal', 0)); ?>
					<?php if ($portal_url): ?>
						<p><a href="<?php echo esc_url($portal_url); ?>" class="button"><?php esc_html_e('View Connection Details', 'panelr-for-woocommerce'); ?></a></p>
					<?php endif; ?>
				</section>

				<section class="panelr-upgrade__products">
					<h3><?php esc_html_e('Choose a Plan', 'panelr-for-woocommerce'); ?></h3>
					<p id="panelr-upgrade-cart-error" class="panelr-upgrade__error woocommerce-error" style="display:none;"></p>
					<table class="panelr-upgrade__table" id="panelr-upgrade-products-table"></table>
				</section>
			</div>

		</div>
		<?php if ($token): ?>
			<script>
				document.addEventListener('DOMContentLoaded', function() {
					if (typeof jQuery !== 'undefined') {
						jQuery('#panelr-upgrade-code').val('<?php echo esc_js(strtoupper($token)); ?>');
						jQuery('#panelr-upgrade-verify').trigger('click');
					}
				});
			</script>
		<?php endif; ?>
<?php
		return ob_get_clean();
	}
}
