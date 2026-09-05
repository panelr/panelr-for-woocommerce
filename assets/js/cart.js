/* Panelr for WooCommerce — cart, checkout and product page extras */
jQuery(function ($) {
	'use strict';
	if (typeof panelrCart === 'undefined') return;

	var i18n = panelrCart.i18n || {};

	function refreshTotals() {
		if ($('form.woocommerce-checkout').length) {
			$(document.body).trigger('update_checkout');
		} else if ($('.woocommerce-cart-form').length) {
			$('.woocommerce-cart-form').find('button[name="update_cart"]').prop('disabled', false).trigger('click');
			if (!$('.woocommerce-cart-form button[name="update_cart"]').length) location.reload();
		} else {
			location.reload();
		}
	}

	// Panelr coupon
	$(document).on('click', '.panelr-coupon-apply', function () {
		var $btn = $(this);
		var $row = $btn.closest('.panelr-coupon-row');
		var code = $row.find('.panelr-coupon-code').val().trim();
		var $res = $row.find('.panelr-coupon-row__result');
		if (!code) { panelr.result($res, i18n.enter_code, false); return; }
		panelr.busy($btn, true, i18n.applying);
		$.post(panelrCart.ajaxurl, { action: 'panelr_apply_coupon', nonce: panelrCart.nonce, code: code })
			.done(function (res) {
				if (res.success) { panelr.result($res, res.data.message, true); refreshTotals(); }
				else { panelr.result($res, res.data.message, false); panelr.busy($btn, false); }
			})
			.fail(function () { panelr.result($res, panelr.failed(), false); panelr.busy($btn, false); });
	});

	$(document).on('keydown', '.panelr-coupon-code', function (e) {
		if (e.key === 'Enter') { e.preventDefault(); $(this).closest('.panelr-coupon-row').find('.panelr-coupon-apply').trigger('click'); }
	});

	$(document).on('click', '.panelr-coupon-remove', function () {
		var $btn = $(this);
		panelr.busy($btn, true);
		$.post(panelrCart.ajaxurl, { action: 'panelr_remove_coupon', nonce: panelrCart.nonce })
			.always(function () { refreshTotals(); });
	});

	// Checkout: sign out of the account step
	$(document).on('click', '.panelr-checkout-signout', function () {
		var $btn = $(this);
		panelr.busy($btn, true);
		$.post(panelrCart.ajaxurl, { action: 'panelr_checkout_signout', nonce: $btn.closest('.panelr-checkout-account').data('nonce') })
			.always(function () { location.reload(); });
	});

	// Add another service (product page)
	$(document).on('click', '.panelr-addon-add', function () {
		var $btn = $(this);
		var $res = $btn.closest('.panelr-addons').find('.panelr-addons__result');
		var nonce = $btn.closest('.panelr-addons').data('nonce');
		panelr.busy($btn, true, i18n.applying);
		$.post(panelrCart.ajaxurl, { action: 'panelr_add_addon', nonce: nonce, panelr_product_id: $btn.data('panelr-product-id') })
			.done(function (res) {
				if (res.success) {
					panelr.result($res, res.data.message, true);
					$res.append(' <a href="' + panelr.escHtml(res.data.cart_url) + '">' + panelr.escHtml($('.panelr-addons').data('cart-label') || 'View cart') + '</a>').show();
					$(document.body).trigger('wc_fragment_refresh');
				} else {
					panelr.result($res, res.data.message, false);
				}
			})
			.fail(function () { panelr.result($res, panelr.failed(), false); })
			.always(function () { panelr.busy($btn, false); });
	});

	// Pay with credits (product page)
	$(document).on('click', '.panelr-credits-buy__button', function () {
		var $btn = $(this);
		var $res = $btn.siblings('.panelr-credits-buy__result');
		panelr.busy($btn, true, i18n.applying);
		$.post(panelrCart.ajaxurl, { action: 'panelr_add_with_credits', nonce: panelrCart.nonce, product_id: $btn.data('product-id') })
			.done(function (res) {
				if (res.success) { window.location.href = res.data.checkout_url; }
				else { panelr.result($res, res.data.message, false); panelr.busy($btn, false); }
			})
			.fail(function () { panelr.result($res, panelr.failed(), false); panelr.busy($btn, false); });
	});
});
