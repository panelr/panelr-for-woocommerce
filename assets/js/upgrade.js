/* Panelr for WooCommerce — trial upgrade page */
jQuery(function ($) {
	'use strict';
	if (typeof panelrUpgrade === 'undefined') return;

	var i18n = panelrUpgrade.i18n || {};

	function verify() {
		var code = ($('#panelr-upgrade-code').val() || '').trim().toUpperCase();
		var token = ($('#panelr-upgrade-token').val() || '').trim();
		var $btn = $('#panelr-upgrade-verify');
		var $err = $('#panelr-upgrade-error');

		panelr.hideError($err);
		if (!code && !token) { panelr.showError($err, i18n.need_code); return; }

		panelr.busy($btn, true, i18n.verifying);
		$.post(panelrUpgrade.ajaxurl, { action: 'panelr_upgrade_verify', nonce: panelrUpgrade.nonce, trial_code: code, activation_token: code ? '' : token })
			.done(function (res) {
				if (res.success) {
					var url = window.location.href.split('#')[0];
					url = url.replace(/([?&])(panelr_t|t)=[^&]*/g, '$1').replace(/[?&]$/, '').replace(/\?&/, '?');
					window.location.href = url;
				} else {
					panelr.showError($err, res.data.message);
					panelr.busy($btn, false);
					$btn.text(i18n.continue);
				}
			})
			.fail(function () { panelr.showError($err, i18n.request_failed); panelr.busy($btn, false); $btn.text(i18n.continue); });
	}

	$(document).on('click', '#panelr-upgrade-verify', verify);
	$(document).on('keydown', '#panelr-upgrade-code', function (e) { if (e.key === 'Enter') { e.preventDefault(); verify(); } });

	$(document).on('click', '.panelr-upgrade-select', function () {
		var $btn = $(this);
		var $err = $('#panelr-upgrade-cart-error');
		panelr.hideError($err);
		panelr.busy($btn, true, i18n.adding);
		$.post(panelrUpgrade.ajaxurl, { action: 'panelr_upgrade_add_to_cart', nonce: panelrUpgrade.nonce, panelr_product_id: $btn.data('product-id') })
			.done(function (res) {
				if (res.success) { window.location.href = res.data.checkout_url; }
				else { panelr.showError($err, res.data.message); panelr.busy($btn, false); }
			})
			.fail(function () { panelr.showError($err, i18n.request_failed); panelr.busy($btn, false); });
	});
});
