/**
 * The credits payment method, drawn on WooCommerce's block checkout.
 *
 * Plain JavaScript against the globals WooCommerce already loads, so the
 * plugin ships without a build step. Availability is decided on the server
 * (Panelr_Credits_Gateway::available); by the time this runs, the method is
 * only in the list when the whole cart is credit-paid and the balance covers
 * it, so canMakePayment simply agrees.
 */
(function (window) {
	'use strict';

	var registry = window.wc && window.wc.wcBlocksRegistry;
	var element = window.wp && window.wp.element;
	var settings = window.wc && window.wc.wcSettings;

	if (!registry || !element || !settings) {
		return;
	}

	var data = settings.getSetting('panelr_credits_data', {});
	var createElement = element.createElement;
	var decode = (window.wp.htmlEntities && window.wp.htmlEntities.decodeEntities) || function (s) { return s; };

	var title = decode(data.title || 'Pay with credits');
	var description = decode(data.description || '');

	function Label(props) {
		var Text = props.components && props.components.PaymentMethodLabel;
		if (Text) {
			return createElement(Text, { text: title });
		}
		return createElement('span', null, title);
	}

	function Content() {
		return description ? createElement('p', null, description) : null;
	}

	registry.registerPaymentMethod({
		name: 'panelr_credits',
		label: createElement(Label, null),
		content: createElement(Content, null),
		edit: createElement(Content, null),
		// The server has already decided; nothing here can make it truer.
		canMakePayment: function () {
			return true;
		},
		ariaLabel: title,
		supports: {
			features: (data.supports && data.supports.length) ? data.supports : ['products']
		}
	});
})(window);
