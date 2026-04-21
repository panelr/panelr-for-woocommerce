jQuery(function($) {
	$('#panelr-os-submit').on('click', function() {
		var btn   = $(this);
		var txid  = $('#panelr-os-txid').val().trim();
		var note  = $('#panelr-os-note').val().trim();
		var error = $('#panelr-os-error');
		var result = $('#panelr-os-result');

		if (!txid) {
			error.text(panelrOrderStatus.i18n.enter_txid).show();
			return;
		}
		error.hide();
		btn.prop('disabled', true);
		result.text(panelrOrderStatus.i18n.submitting);

		$.post(panelrOrderStatus.ajaxurl, {
			action:         'panelr_order_status_submit',
			nonce:          panelrOrderStatus.nonce,
			ref:            panelrOrderStatus.ref,
			token:          panelrOrderStatus.token,
			email:          panelrOrderStatus.email,
			amount:         panelrOrderStatus.amount,
			transaction_id: txid,
			customer_note:  note,
		})
		.done(function(res) {
			if (res.success) {
				$('#panelr-os-payment-form').hide();
				$('#panelr-os-success').show();
			} else {
				error.text(res.data.message || panelrOrderStatus.i18n.generic_error).show();
				btn.prop('disabled', false);
				result.text('');
			}
		})
		.fail(function() {
			error.text(panelrOrderStatus.i18n.request_failed).show();
			btn.prop('disabled', false);
			result.text('');
		});
	});
});