/* Panelr for WooCommerce — member area */
jQuery(function ($) {
	'use strict';
	if (typeof panelrPortal === 'undefined') return;

	var ajax = panelrPortal.ajaxurl;
	var nonce = panelrPortal.nonce;
	var i18n = panelrPortal.i18n || {};

	function post(data) {
		data.action = data.action;
		data.nonce = nonce;
		return $.post(ajax, data);
	}

	function go(url) {
		window.location.href = url || panelrPortal.portal_url;
	}

	function onSignedOut(res) {
		if (res && res.data && res.data.signed_out) { go(); return true; }
		return false;
	}

	// ── Sign-in views ─────────────────────────────────────────────────────
	$(document).on('click', '.panelr-view-link', function (e) {
		e.preventDefault();
		var view = $(this).data('view');
		$('.panelr-portal__view').prop('hidden', true).hide();
		$('#panelr-view-' + view).prop('hidden', false).show().find('input').first().trigger('focus');
	});

	function signIn() {
		var $btn = $('#panelr-login-btn');
		var $err = $('#panelr-login-error');
		var email = ($('#panelr-email').val() || '').trim();
		var password = $('#panelr-password').val() || '';
		panelr.hideError($err);
		if (!email || !password) { panelr.showError($err, i18n.need_fields); return; }
		panelr.busy($btn, true, i18n.signing_in);
		post({ action: 'panelr_portal_login', email: email, password: password, 'return': $('#panelr-return').val() || '' })
			.done(function (res) {
				if (res.success) go(res.data.redirect);
				else { panelr.showError($err, res.data.message); panelr.busy($btn, false); }
			})
			.fail(function () { panelr.showError($err, i18n.request_failed); panelr.busy($btn, false); });
	}
	$(document).on('click', '#panelr-login-btn', signIn);
	$(document).on('keydown', '#panelr-email, #panelr-password', function (e) { if (e.key === 'Enter') { e.preventDefault(); signIn(); } });

	function lineSignIn() {
		var $btn = $('#panelr-line-login-btn');
		var $err = $('#panelr-line-error');
		var user = ($('#panelr-username').val() || '').trim();
		var pass = $('#panelr-line-password').val() || '';
		panelr.hideError($err);
		if (!user || !pass) { panelr.showError($err, i18n.need_fields); return; }
		panelr.busy($btn, true, i18n.signing_in);
		post({ action: 'panelr_portal_line_login', username: user, password: pass, plugin_id: $('#panelr-line-service').val() || 0, 'return': $('#panelr-return').val() || '' })
			.done(function (res) {
				if (res.success) go(res.data.redirect);
				else { panelr.showError($err, res.data.message); panelr.busy($btn, false); }
			})
			.fail(function () { panelr.showError($err, i18n.request_failed); panelr.busy($btn, false); });
	}
	$(document).on('click', '#panelr-line-login-btn', lineSignIn);
	$(document).on('keydown', '#panelr-username, #panelr-line-password', function (e) { if (e.key === 'Enter') { e.preventDefault(); lineSignIn(); } });

	$(document).on('click', '#panelr-register-btn', function () {
		var $btn = $(this);
		var $err = $('#panelr-register-error');
		var password = $('#panelr-reg-password').val() || '';
		var confirm = $('#panelr-reg-password2').val() || '';
		panelr.hideError($err);
		if (!($('#panelr-reg-email').val() || '').trim()) { panelr.showError($err, i18n.need_email); return; }
		if (password.length < 8) { panelr.showError($err, i18n.password_short); return; }
		if (password !== confirm) { panelr.showError($err, i18n.password_match); return; }
		panelr.busy($btn, true, i18n.saving);
		post({
			action: 'panelr_portal_register',
			name: ($('#panelr-reg-name').val() || '').trim(),
			email: ($('#panelr-reg-email').val() || '').trim(),
			password: password,
			password_confirm: confirm,
			invite_code: ($('#panelr-reg-invite').val() || '').trim(),
			'return': $('#panelr-return').val() || ''
		})
		.done(function (res) {
			if (res.success) go(res.data.redirect);
			else { panelr.showError($err, res.data.message); panelr.busy($btn, false); }
		})
		.fail(function () { panelr.showError($err, i18n.request_failed); panelr.busy($btn, false); });
	});

	$(document).on('click', '#panelr-forgot-btn', function () {
		var $btn = $(this);
		var $err = $('#panelr-forgot-error');
		var $done = $('#panelr-forgot-done');
		var email = ($('#panelr-forgot-email').val() || '').trim();
		panelr.hideError($err);
		if (!email) { panelr.showError($err, i18n.need_email); return; }
		panelr.busy($btn, true, i18n.saving);
		post({ action: 'panelr_portal_forgot', email: email })
			.done(function (res) {
				if (res.success) { $done.text(res.data.message).prop('hidden', false).show(); $btn.closest('p').hide(); $('#panelr-forgot-email').closest('p').hide(); }
				else { panelr.showError($err, res.data.message); panelr.busy($btn, false); }
			})
			.fail(function () { panelr.showError($err, i18n.request_failed); panelr.busy($btn, false); });
	});

	$(document).on('click', '#panelr-reset-btn', function () {
		var $btn = $(this);
		var $err = $('#panelr-reset-error');
		var password = $('#panelr-reset-password').val() || '';
		var confirm = $('#panelr-reset-password2').val() || '';
		panelr.hideError($err);
		if (password.length < 8) { panelr.showError($err, i18n.password_short); return; }
		if (password !== confirm) { panelr.showError($err, i18n.password_match); return; }
		panelr.busy($btn, true, i18n.saving);
		post({ action: 'panelr_portal_reset', token: $('#panelr-reset-token').val(), password: password, password_confirm: confirm })
			.done(function (res) {
				if (res.success) go(res.data.redirect);
				else { panelr.showError($err, res.data.message); panelr.busy($btn, false); }
			})
			.fail(function () { panelr.showError($err, i18n.request_failed); panelr.busy($btn, false); });
	});

	$(document).on('click', '#panelr-setup-btn', function () {
		var $btn = $(this);
		var $err = $('#panelr-setup-error');
		var password = $('#panelr-setup-password').val() || '';
		var confirm = $('#panelr-setup-password2').val() || '';
		panelr.hideError($err);
		if (password.length < 8) { panelr.showError($err, i18n.password_short); return; }
		if (password !== confirm) { panelr.showError($err, i18n.password_match); return; }
		panelr.busy($btn, true, i18n.saving);
		post({ action: 'panelr_portal_setup_account', name: ($('#panelr-setup-name').val() || '').trim(), password: password, password_confirm: confirm })
			.done(function (res) {
				if (res.success) go(res.data.redirect);
				else { panelr.showError($err, res.data.message); panelr.busy($btn, false); }
			})
			.fail(function () { panelr.showError($err, i18n.request_failed); panelr.busy($btn, false); });
	});

	$(document).on('click', '#panelr-logout-btn, #panelr-logout-everywhere-btn', function () {
		var $btn = $(this);
		panelr.busy($btn, true);
		post({ action: 'panelr_portal_logout' }).always(function () { go(); });
	});

	$(document).on('click', '#panelr-resend-verify', function () {
		var $btn = $(this);
		var $res = $('#panelr-resend-result');
		panelr.busy($btn, true, i18n.saving);
		post({ action: 'panelr_portal_resend_verify' })
			.done(function (res) { panelr.result($res, res.data.message, !!res.success); })
			.fail(function () { panelr.result($res, i18n.request_failed, false); })
			.always(function () { panelr.busy($btn, false); });
	});

	// ── Connections ───────────────────────────────────────────────────────
	function togglePanel($line, which) {
		var $panel = $line.find('.panelr-line__' + which);
		var open = !$panel.is(':visible');
		$line.find('.panelr-line__panel').prop('hidden', true).hide();
		$line.find('.panelr-line__tab').removeClass('is-active').attr('aria-selected', 'false');
		if (open) {
			$panel.prop('hidden', false).show();
			$line.find('.panelr-line__tab[data-panel="' + which + '"]').addClass('is-active').attr('aria-selected', 'true');
		}
		return open ? $panel : null;
	}

	$(document).on('click', '.panelr-line-renew-btn', function () {
		togglePanel($(this).closest('.panelr-line'), 'renew');
	});

	$(document).on('click', '.panelr-line-details-btn', function () {
		var $btn = $(this);
		var $line = $btn.closest('.panelr-line');
		var $panel = togglePanel($line, 'details');
		if (!$panel || $panel.data('loaded')) return;
		$panel.text(i18n.loading);
		post({ action: 'panelr_portal_line_details', activation_id: $line.data('activation-id') })
			.done(function (res) {
				if (onSignedOut(res)) return;
				if (!res.success) { $panel.html('<p class="panelr-portal__error">' + panelr.escHtml(res.data.message) + '</p>'); return; }
				var d = res.data;
				var rows = '';
				var row = function (label, value, secret) {
					if (!value) return '';
					var id = 'panelr-secret-' + $line.data('activation-id') + '-' + Math.random().toString(36).slice(2, 7);
					var cell = secret
						? '<code class="panelr-portal__code" id="' + id + '" style="display:none">' + panelr.escHtml(value) + '</code><span id="' + id + '-mask">&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;</span>'
						: '<code class="panelr-portal__code">' + panelr.escHtml(value) + '</code>';
					var buttons = (secret ? '<button type="button" class="button panelr-reveal-btn" data-target="#' + id + '" data-mask="#' + id + '-mask">' + panelr.escHtml(i18n.show) + '</button> ' : '')
						+ '<button type="button" class="button panelr-copy-btn" data-copy="' + panelr.escHtml(value) + '">' + panelr.escHtml(i18n.copy) + '</button>';
					return '<tr><th>' + panelr.escHtml(label) + '</th><td>' + cell + '</td><td>' + buttons + '</td></tr>';
				};
				rows += row(i18n.host, d.host);
				rows += row(i18n.username, d.username);
				rows += row(i18n.password, d.password, true);
				rows += row(i18n.mac, d.mac);
				rows += row(i18n.m3u, d.m3u_url);
				rows += row(i18n.epg, d.epg_url);
				$panel.html(rows ? '<table class="panelr-portal__table panelr-line__credentials">' + rows + '</table>' : '<p>' + panelr.escHtml(i18n.details_none) + '</p>').data('loaded', true);
			})
			.fail(function () { $panel.html('<p class="panelr-portal__error">' + panelr.escHtml(i18n.request_failed) + '</p>'); });
	});

	$(document).on('click', '.panelr-line-channels-btn', function () {
		var $line = $(this).closest('.panelr-line');
		var $panel = togglePanel($line, 'channels');
		if (!$panel || $panel.data('loaded')) return;
		$panel.text(i18n.loading);
		post({ action: 'panelr_portal_bouquets', activation_id: $line.data('activation-id') })
			.done(function (res) {
				if (onSignedOut(res)) return;
				if (!res.success) { $panel.html('<p class="panelr-portal__error">' + panelr.escHtml(res.data.message) + '</p>'); return; }
				$panel.html(res.data.html).data('loaded', true);
			})
			.fail(function () { $panel.html('<p class="panelr-portal__error">' + panelr.escHtml(i18n.request_failed) + '</p>'); });
	});

	function bouquetCounts($box) {
		$box.find('.panelr-bouquets__tab').each(function () {
			var $tab = $(this);
			var cat = $tab.data('category');
			var on = $box.find('.panelr-bouquet-cb[data-category="' + cat + '"]:checked').length;
			$tab.find('.panelr-bouquets__count').text(on + '/' + $tab.find('.panelr-bouquets__count').data('total'));
		});
	}
	$(document).on('click', '.panelr-bouquets__tab', function () {
		var $tab = $(this);
		var $box = $tab.closest('.panelr-bouquets');
		$box.find('.panelr-bouquets__tab').removeClass('is-active').attr('aria-selected', 'false');
		$tab.addClass('is-active').attr('aria-selected', 'true');
		$box.find('.panelr-bouquets__group').prop('hidden', true).hide();
		$box.find('.panelr-bouquets__group[data-category="' + $tab.data('category') + '"]').prop('hidden', false).show();
	});
	$(document).on('click', '.panelr-bouquets__all', function () { $(this).closest('.panelr-bouquets__group').find('.panelr-bouquet-cb').prop('checked', true); bouquetCounts($(this).closest('.panelr-bouquets')); });
	$(document).on('click', '.panelr-bouquets__none', function () { $(this).closest('.panelr-bouquets__group').find('.panelr-bouquet-cb').prop('checked', false); bouquetCounts($(this).closest('.panelr-bouquets')); });
	$(document).on('change', '.panelr-bouquet-cb', function () { bouquetCounts($(this).closest('.panelr-bouquets')); });

	$(document).on('click', '.panelr-bouquets__save', function () {
		var $btn = $(this);
		var $box = $btn.closest('.panelr-bouquets');
		var $err = $box.find('.panelr-bouquets__error');
		var $res = $box.find('.panelr-bouquets__result');
		var data = { action: 'panelr_portal_update_bouquets', activation_id: $box.data('activation-id') };
		panelr.hideError($err);
		if ($box.data('mode') === 'editor') {
			$.each(['live', 'vod', 'series'], function (i, cat) {
				data[cat] = $box.find('.panelr-bouquet-cb[data-category="' + cat + '"]:checked').map(function () { return this.value; }).get();
			});
		} else {
			data.bouquet_ids = $box.find('.panelr-bouquet-cb:checked').map(function () { return this.value; }).get();
		}
		var before = $box.find('.panelr-bouquet-cb').map(function () { return this.checked ? '1' : '0'; }).get();
		panelr.busy($btn, true, i18n.saving);
		post(data)
			.done(function (res) {
				if (onSignedOut(res)) return;
				if (res.success) { panelr.result($res, res.data.message, true); }
				else {
					// A failed sync leaves the old choice in place.
					$box.find('.panelr-bouquet-cb').each(function (i) { this.checked = before[i] === '1'; });
					panelr.showError($err, res.data.message);
				}
			})
			.fail(function () { panelr.showError($err, i18n.request_failed); })
			.always(function () { panelr.busy($btn, false); });
	});

	$(document).on('click', '.panelr-renew-choose', function () {
		var $btn = $(this);
		var $line = $btn.closest('.panelr-line');
		var $err = $line.find('.panelr-line__error').first();
		panelr.hideError($err);
		panelr.busy($btn, true, i18n.saving);
		post({ action: 'panelr_portal_add_renewal', activation_id: $line.data('activation-id'), panelr_product_id: $btn.data('panelr-product-id'), credits: $btn.data('credits') ? '1' : '0' })
			.done(function (res) {
				if (onSignedOut(res)) return;
				if (res.success) go(res.data.checkout_url);
				else { panelr.showError($err, res.data.message); panelr.busy($btn, false); }
			})
			.fail(function () { panelr.showError($err, i18n.request_failed); panelr.busy($btn, false); });
	});

	// Rename in place
	$(document).on('click', '.panelr-line__rename', function () {
		var $line = $(this).closest('.panelr-line');
		$line.find('.panelr-line__rename-form').prop('hidden', false).show().find('input').trigger('focus');
	});
	$(document).on('click', '.panelr-line__rename-cancel', function () {
		$(this).closest('.panelr-line__rename-form').prop('hidden', true).hide();
	});
	$(document).on('keydown', '.panelr-line__rename-input', function (e) {
		if (e.key === 'Enter') { e.preventDefault(); $(this).siblings('.panelr-line__rename-save').trigger('click'); }
		if (e.key === 'Escape') { $(this).siblings('.panelr-line__rename-cancel').trigger('click'); }
	});
	$(document).on('click', '.panelr-line__rename-save', function () {
		var $btn = $(this);
		var $line = $btn.closest('.panelr-line');
		var $form = $btn.closest('.panelr-line__rename-form');
		var label = ($form.find('input').val() || '').trim();
		panelr.busy($btn, true, i18n.saving);
		post({ action: 'panelr_portal_rename_line', activation_id: $line.data('activation-id'), label: label })
			.done(function (res) {
				if (onSignedOut(res)) return;
				if (res.success) {
					$line.find('.panelr-line__label').text(res.data.display || $line.data('username'));
					$form.prop('hidden', true).hide();
				} else {
					alert(res.data.message);
				}
			})
			.fail(function () { alert(i18n.request_failed); })
			.always(function () { panelr.busy($btn, false); });
	});

	// ── Account ───────────────────────────────────────────────────────────
	$(document).on('click', '#panelr-save-account-btn', function () {
		var $btn = $(this);
		var $err = $('#panelr-account-edit-error');
		var $res = $('#panelr-account-result');
		panelr.hideError($err);
		panelr.busy($btn, true, i18n.saving);
		post({ action: 'panelr_portal_update_account', customer_name: ($('#panelr-edit-name').val() || '').trim(), customer_email: ($('#panelr-edit-email').val() || '').trim() })
			.done(function (res) {
				if (onSignedOut(res)) return;
				if (res.success) { panelr.result($res, res.data.message, true); $('.panelr-portal__name').text(res.data.name || res.data.email); $('.panelr-portal__email').text(res.data.email); }
				else panelr.showError($err, res.data.message);
			})
			.fail(function () { panelr.showError($err, i18n.request_failed); })
			.always(function () { panelr.busy($btn, false); });
	});

	$(document).on('click', '#panelr-change-password-btn', function () {
		var $btn = $(this);
		var $err = $('#panelr-password-error');
		var $res = $('#panelr-password-result');
		var password = $('#panelr-new-password').val() || '';
		var confirm = $('#panelr-new-password2').val() || '';
		panelr.hideError($err);
		if (password.length < 8) { panelr.showError($err, i18n.password_short); return; }
		if (password !== confirm) { panelr.showError($err, i18n.password_match); return; }
		panelr.busy($btn, true, i18n.saving);
		post({ action: 'panelr_portal_change_password', current_password: $('#panelr-current-password').val() || '', password: password, password_confirm: confirm })
			.done(function (res) {
				if (onSignedOut(res)) return;
				if (res.success) { panelr.result($res, res.data.message, true); $('#panelr-current-password, #panelr-new-password, #panelr-new-password2').val(''); }
				else panelr.showError($err, res.data.message);
			})
			.fail(function () { panelr.showError($err, i18n.request_failed); })
			.always(function () { panelr.busy($btn, false); });
	});

	$(document).on('change', 'input[name="panelr_contact_pref"]', function () {
		var $err = $('#panelr-contact-error');
		var $res = $('#panelr-contact-result');
		var value = this.value;
		var prev = $('input[name="panelr_contact_pref"]').filter(function () { return $(this).data('was'); }).val() || 'email';
		panelr.hideError($err);
		post({ action: 'panelr_portal_contact_pref', preference: value })
			.done(function (res) {
				if (onSignedOut(res)) return;
				if (res.success) { panelr.result($res, res.data.message, true); $('input[name="panelr_contact_pref"]').data('was', false); $('input[name="panelr_contact_pref"][value="' + value + '"]').data('was', true); }
				else { panelr.showError($err, res.data.message); $('input[name="panelr_contact_pref"][value="' + prev + '"]').prop('checked', true); }
			})
			.fail(function () { panelr.showError($err, i18n.request_failed); });
	});
	$('input[name="panelr_contact_pref"]:checked').data('was', true);

	// ── Chats ─────────────────────────────────────────────────────────────
	var chatPoll = null;

	function chatRow(platform) { return $('.panelr-chats__row[data-platform="' + platform + '"]'); }
	function chatCode(platform) { return $('.panelr-chats__code[data-platform="' + platform + '"]'); }

	function applyChats(chats, preference) {
		$.each(chats || {}, function (platform, c) {
			var $row = chatRow(platform);
			var $btn = $row.find('.button');
			$row.find('.panelr-chats__state').text(c.linked ? (c.blocked ? i18n.chat_blocked : i18n.chat_linked) : i18n.chat_not_linked);
			$btn.removeClass('panelr-chat-link-btn panelr-chat-unlink-btn is-busy').prop('disabled', false)
				.addClass(c.linked ? 'panelr-chat-unlink-btn' : 'panelr-chat-link-btn')
				.text(c.linked ? i18n.chat_unlink : i18n.chat_link);
			var $choice = $('.panelr-portal__choices label[data-platform="' + platform + '"]');
			$choice.toggleClass('is-unavailable', !c.linked).find('input').prop('disabled', !c.linked);
			$choice.find('.panelr-chats__hint').prop('hidden', !!c.linked);
			if (c.linked) chatCode(platform).prop('hidden', true).hide().empty();
		});
		if (preference) {
			$('input[name="panelr_contact_pref"]').data('was', false).prop('checked', false);
			$('input[name="panelr_contact_pref"][value="' + preference + '"]').prop('checked', true).data('was', true);
		}
	}

	function stopChatPoll() { if (chatPoll) { clearInterval(chatPoll); chatPoll = null; } }

	// While a code is out, ask every few seconds whether the bot has taken it.
	function startChatPoll(platform) {
		stopChatPoll();
		chatPoll = setInterval(function () {
			post({ action: 'panelr_portal_refresh' }).done(function (res) {
				if (onSignedOut(res)) return;
				var chats = res.success && res.data && res.data.chats;
				if (chats && chats[platform] && chats[platform].linked) {
					stopChatPoll();
					applyChats(chats, res.data.contact_preference);
					panelr.result($('#panelr-contact-result'), i18n.chat_linked_now, true);
				}
			});
		}, 4000);
	}

	$(document).on('click', '.panelr-chat-link-btn', function () {
		var $btn = $(this);
		var platform = $btn.data('platform');
		var $err = $('#panelr-chat-error');
		panelr.hideError($err);
		panelr.busy($btn, true, i18n.loading);
		post({ action: 'panelr_portal_chat_link', platform: platform, mode: 'start' })
			.done(function (res) {
				if (onSignedOut(res)) return;
				if (!res.success) { panelr.showError($err, res.data.message); return; }
				$('.panelr-chats__code').prop('hidden', true).hide().empty();
				chatCode(platform).html(res.data.html).prop('hidden', false).show();
				startChatPoll(platform);
			})
			.fail(function () { panelr.showError($err, i18n.request_failed); })
			.always(function () { panelr.busy($btn, false); });
	});

	$(document).on('click', '.panelr-chat-cancel-btn', function () {
		var $btn = $(this);
		var platform = $btn.data('platform');
		stopChatPoll();
		panelr.busy($btn, true);
		post({ action: 'panelr_portal_chat_link', platform: platform, mode: 'cancel' })
			.always(function () { chatCode(platform).prop('hidden', true).hide().empty(); });
	});

	$(document).on('click', '.panelr-chat-unlink-btn', function () {
		var $btn = $(this);
		var platform = $btn.data('platform');
		var $err = $('#panelr-chat-error');
		panelr.hideError($err);
		panelr.busy($btn, true, i18n.saving);
		post({ action: 'panelr_portal_chat_unlink', platform: platform })
			.done(function (res) {
				if (onSignedOut(res)) return;
				if (!res.success) { panelr.showError($err, res.data.message); panelr.busy($btn, false); return; }
				applyChats(res.data.chats, res.data.preference);
				panelr.result($('#panelr-contact-result'), res.data.message, true);
			})
			.fail(function () { panelr.showError($err, i18n.request_failed); panelr.busy($btn, false); });
	});
});
