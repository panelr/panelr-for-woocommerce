/* Panelr for WooCommerce — order received page */
jQuery(function ($) {
	'use strict';
	if (typeof panelrThankyou === 'undefined') return;

	var i18n = panelrThankyou.i18n || {};

	// QR code for a payable address
	function renderQR() {
		var el = document.getElementById('panelr-qr-code');
		if (!el || !el.getAttribute('data-qr')) return;
		if (typeof QRCode === 'undefined') {
			if (!renderQR.tries) renderQR.tries = 0;
			if (renderQR.tries++ < 30) setTimeout(renderQR, 100);
			return;
		}
		new QRCode(el, { text: el.getAttribute('data-qr'), width: 180, height: 180, colorDark: '#000000', colorLight: '#ffffff', correctLevel: QRCode.CorrectLevel.M });
	}
	renderQR();

	// "I've paid" / add a note
	$('#panelr-submit-payment').on('click', function () {
		var $btn = $(this);
		var $result = $('#panelr-submit-result');
		var txid = ($('#panelr_transaction_id').val() || '').trim();
		var note = ($('#panelr_customer_note').val() || '').trim();
		var already = panelrThankyou.already_submitted === '1';

		if (!already && !txid) { panelr.result($result, i18n.enter_txid, false); return; }
		if (already && !note) return;

		panelr.busy($btn, true, i18n.submitting);
		$.post(panelrThankyou.ajaxurl, {
			action: 'panelr_submit_payment',
			nonce: panelrThankyou.nonce,
			confirmation_token: panelrThankyou.confirmation_token,
			transaction_id: txid,
			customer_note: note,
			order_id: panelrThankyou.order_id,
			order_key: panelrThankyou.order_key
		})
		.done(function (res) {
			if (res.success) {
				if (!already) {
					$('#panelr_transaction_id').closest('p').slideUp();
					panelrThankyou.already_submitted = '1';
					$('#panelr-payment-form').before('<div class="panelr-payment-success">' + panelr.escHtml(res.data.message) + '</div>');
					$btn.text(i18n.add_note || 'Add note');
				} else {
					panelr.result($result, res.data.message, true);
				}
				$('#panelr_customer_note').val('');
				panelr.busy($btn, false);
				if (!already) $btn.text(i18n.add_note || 'Add note');
			} else {
				panelr.result($result, res.data.message, false);
				panelr.busy($btn, false);
			}
		})
		.fail(function () { panelr.result($result, i18n.request_failed, false); panelr.busy($btn, false); });
	});

	// Automatic orders: ask whether it is ready yet.
	if (panelrThankyou.poll === '1') {
		var delays = [5000, 10000, 15000, 20000, 30000, 30000, 60000, 60000, 60000, 90000];
		var attempt = 0;
		var tick = function () {
			$.post(panelrThankyou.ajaxurl, {
				action: 'panelr_order_poll',
				nonce: panelrThankyou.poll_nonce,
				order_id: panelrThankyou.order_id,
				order_key: panelrThankyou.order_key
			}).done(function (res) {
				if (!res.success) return;
				var $box = $('#panelr-order-progress');
				$box.find('.panelr-thankyou__status-label').text(res.data.label);
				if (res.data.lines && res.data.lines.length) {
					var html = '';
					$.each(res.data.lines, function (i, l) {
						html += '<li><code>' + panelr.escHtml(l.username) + '</code>' + (l.service_name ? ' · ' + panelr.escHtml(l.service_name) : '') + '</li>';
					});
					$box.find('.panelr-thankyou__lines').html(html).prop('hidden', false);
				}
				if (res.data.done) {
					if (res.data.status === 'completed') {
						$box.removeClass('panelr-thankyou__pending').addClass('panelr-payment-success');
						$box.find('.panelr-thankyou__status-text').text($box.data('ready-text') || 'Your service is set up. Your connection details are in your inbox.');
					}
					return;
				}
				if (attempt < delays.length) setTimeout(tick, delays[attempt++]);
			});
		};
		setTimeout(tick, delays[attempt++]);
	}
});
