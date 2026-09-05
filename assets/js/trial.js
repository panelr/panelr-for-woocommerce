/* Panelr for WooCommerce — free trial form */
jQuery(function ($) {
	'use strict';
	if (typeof panelrTrial === 'undefined') return;

	var i18n = panelrTrial.i18n || {};

	function submit() {
		var $btn = $('#panelr-trial-submit');
		var name = ($('#panelr-trial-name').val() || '').trim();
		var email = ($('#panelr-trial-email').val() || '').trim();
		var service = $('input[name="panelr_trial_service"]:checked').val() || $('input[name="panelr_trial_service"][type="hidden"]').val() || 0;
		var $err = $('#panelr-trial-error');
		var token = '';

		panelr.hideError($err);
		if (!email) { panelr.showError($err, i18n.need_email); return; }
		if (panelrTrial.turnstile === '1') {
			token = ($('[name="cf-turnstile-response"]').val() || '').trim();
			if (!token) { panelr.showError($err, i18n.need_human); return; }
		}

		panelr.busy($btn, true, i18n.sending);
		$.post(panelrTrial.ajaxurl, {
			action: 'panelr_request_trial',
			nonce: panelrTrial.nonce,
			name: name,
			email: email,
			service: service,
			turnstile_token: token
		})
		.done(function (res) {
			if (res.success) {
				$('#panelr-trial-form').hide();
				$('#panelr-trial-result').html('<p class="woocommerce-message panelr-trial__done">' + panelr.escHtml(res.data.message) + '</p>').prop('hidden', false).show();
			} else {
				panelr.showError($err, res.data.message);
				panelr.busy($btn, false);
				$btn.text(i18n.button);
				if (window.turnstile) { try { turnstile.reset(); } catch (e) {} }
			}
		})
		.fail(function () {
			panelr.showError($err, i18n.request_failed);
			panelr.busy($btn, false);
			$btn.text(i18n.button);
		});
	}

	$(document).on('click', '#panelr-trial-submit', submit);
	$(document).on('keydown', '#panelr-trial-name, #panelr-trial-email', function (e) {
		if (e.key === 'Enter') { e.preventDefault(); submit(); }
	});
});
