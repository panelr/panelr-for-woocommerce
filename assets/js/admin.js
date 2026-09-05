/* Panelr for WooCommerce — admin */
jQuery(function ($) {
	'use strict';
	if (typeof panelrAdmin === 'undefined') return;

	var i18n = panelrAdmin.i18n || {};

	function esc(str) {
		return String(str === undefined || str === null ? '' : str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
	}

	function show($el, msg, ok) {
		$el.text(msg).css('color', ok ? '#008a20' : '#d63638');
	}

	function call($btn, $result, data, done) {
		var orig = $btn.text();
		$btn.prop('disabled', true);
		show($result, i18n.working, true);
		data.nonce = panelrAdmin.nonce;
		$.post(panelrAdmin.ajaxurl, data)
			.done(function (res) {
				if (res.success) { show($result, (res.data && res.data.message) || i18n.done, true); if (done) done(res.data || {}); }
				else show($result, (res.data && res.data.message) || i18n.request_failed, false);
			})
			.fail(function () { show($result, i18n.request_failed, false); })
			.always(function () { $btn.prop('disabled', false).text(orig); });
	}

	// Connection
	$('#panelr-test-connection').on('click', function () {
		call($(this), $('#panelr-test-result'), { action: 'panelr_test_connection' });
	});
	$('#panelr-refresh-cache').on('click', function () {
		call($(this), $('#panelr-test-result'), { action: 'panelr_refresh_cache' }, function () { setTimeout(function () { location.reload(); }, 800); });
	});

	// Sync
	$('#panelr-sync-products').on('click', function () {
		call($(this), $('#panelr-sync-result'), { action: 'panelr_sync_products' }, function () { setTimeout(function () { location.reload(); }, 1200); });
	});

	// Pages
	$('#panelr-create-pages').on('click', function () {
		var missing = [];
		$('.panelr-page-select').each(function () { if (!parseInt($(this).val(), 10)) missing.push($(this).data('key')); });
		if (!missing.length) { show($('#panelr-create-pages-result'), i18n.done, true); return; }
		call($(this), $('#panelr-create-pages-result'), { action: 'panelr_create_pages', pages: missing }, function (data) {
			$.each(data.pages || {}, function (key, p) {
				var $sel = $('#panelr-page-' + key);
				if (!$sel.find('option[value="' + p.id + '"]').length) $sel.append('<option value="' + p.id + '">' + esc(p.title) + '</option>');
				$sel.val(p.id);
			});
			$('.panelr-page-select').first().closest('form').trigger('submit');
		});
	});

	// Orders: check now / send
	$(document).on('click', '.panelr-check-order', function () {
		var $btn = $(this);
		var $box = $btn.closest('[data-order-id]');
		call($btn, $box.find('.panelr-order-box__result').first(), { action: 'panelr_admin_check_order', order_id: $box.data('order-id') }, function (data) {
			$box.find('.panelr-order-box__status').first().text(data.label);
			show($box.find('.panelr-order-box__result').first(), data.label, true);
		});
	});
	$(document).on('click', '.panelr-send-order', function () {
		var $btn = $(this);
		var $box = $btn.closest('[data-order-id]');
		call($btn, $box.find('.panelr-order-box__result').first(), { action: 'panelr_admin_send_order', order_id: $box.data('order-id') }, function () {
			setTimeout(function () { location.reload(); }, 900);
		});
	});

	// Products: filter by service, switch a plan on or off
	$(document).on('click', '.panelr-chip', function () {
		var service = String($(this).data('service'));
		$('.panelr-chip').removeClass('is-active');
		$(this).addClass('is-active');
		$('#panelr-products-table tbody tr').each(function () {
			$(this).toggle(service === 'all' || String($(this).data('service')) === service);
		});
	});
	$(document).on('change', '.panelr-product-toggle', function () {
		var $cb = $(this);
		var $row = $cb.closest('tr');
		var $label = $cb.siblings('.panelr-switch__label');
		var on = $cb.is(':checked');
		$cb.prop('disabled', true);
		$.post(panelrAdmin.ajaxurl, { action: 'panelr_toggle_product', nonce: panelrAdmin.nonce, product_id: $row.data('product-id'), on: on ? '1' : '0' })
			.done(function (res) {
				if (res.success) { $label.text(res.data.label); }
				else { $cb.prop('checked', !on); alert(res.data.message || i18n.request_failed); }
			})
			.fail(function () { $cb.prop('checked', !on); alert(i18n.request_failed); })
			.always(function () { $cb.prop('disabled', false); });
	});

	// Copy a shortcode
	$(document).on('click', '.panelr-copy-admin', function () {
		var $btn = $(this), text = $btn.data('copy'), orig = $btn.text();
		var done = function () { $btn.text(i18n.done); setTimeout(function () { $btn.text(orig); }, 1200); };
		if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(text).then(done);
		else { var ta = document.createElement('textarea'); ta.value = text; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta); done(); }
	});

	// Help icons: click to open the explanation under the option, click again to close.
	$(document).on('click', '.panelr-help', function () {
		var $text = $(this).next('.panelr-help__text');
		$text.prop('hidden', !$text.prop('hidden'));
	});

	// Dismissible notices
	$(document).on('click', '.panelr-upgrade-notice .notice-dismiss', function () {
		$.post(panelrAdmin.ajaxurl, { action: 'panelr_dismiss_notice', nonce: panelrAdmin.nonce, notice: $(this).closest('.panelr-upgrade-notice').data('panelr-notice') });
	});
});
