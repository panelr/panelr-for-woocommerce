/* Panelr for WooCommerce — shared front-end helpers */
(function ($) {
	'use strict';

	var i18n = (window.panelrCommon && panelrCommon.i18n) || {};

	window.panelr = window.panelr || {};

	panelr.copy = function (text, $btn) {
		var done = function () {
			if (!$btn) return;
			var orig = $btn.data('orig') || $btn.text();
			$btn.data('orig', orig).text(i18n.copied || 'Copied');
			setTimeout(function () { $btn.text(orig); }, 1500);
		};
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(done).catch(function () { panelr.copyFallback(text); done(); });
		} else {
			panelr.copyFallback(text);
			done();
		}
	};

	panelr.copyFallback = function (text) {
		var ta = document.createElement('textarea');
		ta.value = text;
		ta.setAttribute('readonly', '');
		ta.style.position = 'absolute';
		ta.style.left = '-9999px';
		document.body.appendChild(ta);
		ta.select();
		try { document.execCommand('copy'); } catch (e) {}
		document.body.removeChild(ta);
	};

	panelr.escHtml = function (str) {
		return String(str === undefined || str === null ? '' : str)
			.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
	};

	panelr.showError = function ($el, msg) {
		$el.text(msg).prop('hidden', false).show();
	};

	panelr.hideError = function ($el) {
		$el.text('').prop('hidden', true).hide();
	};

	panelr.result = function ($el, msg, ok) {
		$el.text(msg).removeClass('is-ok is-error').addClass(ok ? 'is-ok' : 'is-error').prop('hidden', false).show();
		if (ok) setTimeout(function () { $el.fadeOut(); }, 3000);
	};

	panelr.busy = function ($btn, on, label) {
		if (on) {
			if ($btn.hasClass('is-busy')) return;
			$btn.data('orig', $btn.html()).prop('disabled', true).addClass('is-busy').attr('aria-busy', 'true');
			$btn.html('<span class="panelr-spinner" aria-hidden="true"></span>' + panelr.escHtml(label || $btn.text()));
		} else {
			$btn.prop('disabled', false).removeClass('is-busy').removeAttr('aria-busy');
			if ($btn.data('orig')) $btn.html($btn.data('orig'));
		}
	};

	// Plain forms (support tickets, order lookups, the hand-off confirm):
	// one submit, and the button shows it is working until the page changes.
	$(document).on('submit', '[class^="panelr-"] form, [class*=" panelr-"] form, form[data-panelr-once]', function (e) {
		var $form = $(this);
		if ($form.data('submitting')) { e.preventDefault(); return; }
		$form.data('submitting', true);
		$form.find('button[type="submit"], input[type="submit"]').each(function () {
			var $b = $(this);
			if ($b.is('input')) { $b.prop('disabled', true); return; }
			// Disabled submit buttons are dropped from the POST body; keep the
			// click's own button out of it only after the submit has started.
			setTimeout(function () { panelr.busy($b, true); }, 0);
		});
	});
	$(window).on('pageshow', function () {
		$('form').data('submitting', false);
		$('.is-loading').removeClass('is-loading');
		$('a.button.is-busy').removeClass('is-busy').find('.panelr-spinner').remove();
	});

	// A link that leads to another page: the click shows straight away, the
	// tab it came from goes quiet, and the page it is leaving dims.
	$(document).on('click', '.panelr-tab-btn', function (e) {
		var $tab = $(this);
		if (e.metaKey || e.ctrlKey || e.shiftKey || $tab.hasClass('panelr-tab-btn--active')) return;
		$('.panelr-tab-btn').removeClass('panelr-tab-btn--active');
		$tab.addClass('panelr-tab-btn--active is-loading').prepend('<span class="panelr-spinner" aria-hidden="true"></span>');
		$('.panelr-portal__tab-panel--toplevel').addClass('is-loading');
	});
	$(document).on('click', '[class^="panelr-"] a.button:not([target]):not(.is-busy), [class*=" panelr-"] a.button:not([target]):not(.is-busy)', function (e) {
		if (e.metaKey || e.ctrlKey || e.shiftKey) return;
		var $a = $(this);
		if (($a.attr('href') || '#').charAt(0) === '#') return;
		$a.addClass('is-busy').prepend('<span class="panelr-spinner" aria-hidden="true"></span>');
	});

	panelr.failed = function () {
		return i18n.request_failed || 'Something went wrong. Please try again.';
	};

	$(document).on('click', '.panelr-copy-btn', function () {
		panelr.copy($(this).data('copy'), $(this));
	});

	$(document).on('click', '.panelr-reveal-btn', function () {
		var $btn = $(this);
		var $target = $($btn.data('target'));
		var shown = $target.is(':visible');
		$target.toggle(!shown);
		$($btn.data('mask')).toggle(shown);
		$btn.text(shown ? (i18n.show || 'Show') : (i18n.hide || 'Hide'));
	});
})(jQuery);
