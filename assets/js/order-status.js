/* Panelr for WooCommerce — order status page */
jQuery(function ($) {
	'use strict';
	if (typeof panelrOrderStatus === 'undefined') return;

	var i18n = panelrOrderStatus.i18n || {};

	// QR code
	(function renderQR() {
		var el = document.getElementById('panelr-qr-code');
		if (!el || !el.getAttribute('data-qr')) return;
		if (typeof QRCode === 'undefined') {
			if (!renderQR.tries) renderQR.tries = 0;
			if (renderQR.tries++ < 30) setTimeout(renderQR, 100);
			return;
		}
		new QRCode(el, { text: el.getAttribute('data-qr'), width: 180, height: 180, colorDark: '#000000', colorLight: '#ffffff', correctLevel: QRCode.CorrectLevel.M });
	})();

	$('#panelr-os-submit').on('click', function () {
		var $btn = $(this);
		var txid = ($('#panelr-os-txid').val() || '').trim();
		var note = ($('#panelr-os-note').val() || '').trim();
		var amount = ($('#panelr-os-amount').val() || '').trim();
		var $error = $('#panelr-os-error');
		var $result = $('#panelr-os-result');

		panelr.hideError($error);
		if (!txid) { panelr.showError($error, i18n.enter_txid); return; }
		if (amount !== '' && (isNaN(parseFloat(amount)) || parseFloat(amount) <= 0)) { panelr.showError($error, i18n.bad_amount); return; }

		panelr.busy($btn, true, i18n.submitting);
		$.post(panelrOrderStatus.ajaxurl, {
			action: 'panelr_order_status_submit',
			nonce: panelrOrderStatus.nonce,
			ref: panelrOrderStatus.ref,
			token: panelrOrderStatus.token,
			email: panelrOrderStatus.email,
			amount: amount,
			transaction_id: txid,
			customer_note: note
		})
		.done(function (res) {
			if (res.success) {
				$('#panelr-os-payment-form').hide();
				$('#panelr-os-success').prop('hidden', false).show();
			} else {
				panelr.showError($error, res.data.message || i18n.request_failed);
				panelr.busy($btn, false);
			}
		})
		.fail(function () { panelr.showError($error, i18n.request_failed); panelr.busy($btn, false); });
	});
});
