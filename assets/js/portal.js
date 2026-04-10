jQuery(function ($) {

    if (typeof panelrPortal === 'undefined') return;

    var ajax  = panelrPortal.ajaxurl;
    var nonce = panelrPortal.nonce;

    function showError($el, msg) { $el.text(msg).show(); }
    function hideError($el)      { $el.text('').hide(); }
    function showResult($el, msg, success) {
        $el.text(msg).css('color', success ? 'green' : 'red').show();
        if (success) setTimeout(function () { $el.hide(); }, 3000);
    }

    // ── Show bouquet save success message after redirect ─────────────────

    if (new URLSearchParams(window.location.search).get('panelr_bouquets_saved') === '1') {
        // Switch to channels tab first
        var $channelsBtn = $('.panelr-portal__tabs > .panelr-tab-btn[data-tab="panelr-tab-channels"]');
        if ($channelsBtn.length) {
            $channelsBtn.trigger('click');
        }
        // Show message after tab is visible
        var $res = $('#panelr-bouquet-result');
        $res.text('Channels saved and synced successfully.').css('color', 'green').show();
        setTimeout(function () { $res.fadeOut(); }, 4000);
        // Clean up URL
        if (window.history && window.history.replaceState) {
            window.history.replaceState({}, '', window.location.href.split('?')[0]);
        }
    }

    // ── Login ──────────────────────────────────────────────────────────────

    $(document).on('click', '#panelr-login-btn', function () {
        var btn  = $(this);
        var user = $('#panelr-username').val().trim();
        var pass = $('#panelr-password').val().trim();
        var $err = $('#panelr-login-error');
        hideError($err);
        if (!user || !pass) { showError($err, 'Username and password are required.'); return; }
        btn.prop('disabled', true).text('Signing in\u2026');
        $.post(ajax, { action: 'panelr_portal_login', nonce: nonce, username: user, password: pass })
            .done(function (res) {
                if (res.success) { location.reload(); }
                else { showError($err, res.data.message); btn.prop('disabled', false).text('Sign In'); }
            })
            .fail(function () { showError($err, 'Request failed. Please try again.'); btn.prop('disabled', false).text('Sign In'); });
    });

    $(document).on('keydown', '#panelr-username, #panelr-password', function (e) {
        if (e.key === 'Enter') $('#panelr-login-btn').trigger('click');
    });

    // ── Logout ─────────────────────────────────────────────────────────────

    $(document).on('click', '#panelr-logout-btn', function () {
        $.post(ajax, { action: 'panelr_portal_logout', nonce: nonce }).always(function () { location.reload(); });
    });

    // ── Portal tabs ────────────────────────────────────────────────────────

    $(document).on('click', '.panelr-portal__tabs > .panelr-tab-btn', function () {
        var $btn   = $(this);
        var target = $btn.data('tab');
        $('.panelr-portal__tabs > .panelr-tab-btn').removeClass('panelr-tab-btn--active');
        $btn.addClass('panelr-tab-btn--active');
        $('.panelr-portal__tab-panel--toplevel').hide();
        $('#' + target).show();
    });

    // ── Toggle password ────────────────────────────────────────────────────

    $(document).on('click', '#panelr-toggle-password-btn', function () {
        var $val    = $('#panelr-password-value');
        var $hidden = $('#panelr-password-hidden');
        var $btn    = $(this);
        if ($val.is(':hidden')) { $val.show(); $hidden.hide(); $btn.text('Hide'); }
        else                   { $val.hide(); $hidden.show(); $btn.text('Show'); }
    });

    // ── Copy buttons ───────────────────────────────────────────────────────

    $(document).on('click', '.panelr-copy-btn', function () {
        var val = $(this).data('copy');
        var btn = $(this);
        navigator.clipboard.writeText(val).then(function () {
            var orig = btn.text();
            btn.text('Copied!');
            setTimeout(function () { btn.text(orig); }, 1500);
        }).catch(function () {
            var ta = document.createElement('textarea');
            ta.value = val; document.body.appendChild(ta); ta.select();
            document.execCommand('copy'); document.body.removeChild(ta);
        });
    });

    // ── Edit account ───────────────────────────────────────────────────────

    $(document).on('click', '#panelr-edit-account-btn', function () {
        $('#panelr-account-display').hide();
        $('#panelr-account-edit').show();
    });

    $(document).on('click', '#panelr-cancel-account-btn', function () {
        $('#panelr-account-edit').hide();
        $('#panelr-account-display').show();
        hideError($('#panelr-account-edit-error'));
    });

    $(document).on('click', '#panelr-save-account-btn', function () {
        var btn   = $(this);
        var email = $('#panelr-edit-email').val().trim();
        var name  = $('#panelr-edit-name').val().trim();
        var $err  = $('#panelr-account-edit-error');
        var $res  = $('#panelr-account-result');
        hideError($err);
        if (!email) { showError($err, 'Email is required.'); return; }
        btn.prop('disabled', true);
        $.post(ajax, { action: 'panelr_portal_update_customer', nonce: nonce, customer_email: email, customer_name: name })
            .done(function (res) {
                if (res.success) {
                    $('#panelr-display-email').text(email);
                    $('#panelr-display-name').text(name);
                    $('#panelr-account-edit').hide();
                    $('#panelr-account-display').show();
                    showResult($res, res.data.message, true);
                } else {
                    showError($err, res.data.message);
                }
            })
            .fail(function () { showError($err, 'Request failed.'); })
            .always(function () { btn.prop('disabled', false); });
    });

    // ── Bouquet wizard — Next ──────────────────────────────────────────────

    $(document).on('click', '.panelr-wizard-next', function () {
        var $wizard     = $(this).closest('.panelr-bouquet-wizard');
        var totalSteps  = parseInt($wizard.data('total-steps'), 10);
        var $current    = $(this).closest('.panelr-wizard-step');
        var currentStep = parseInt($current.data('step'), 10);
        var nextStep    = currentStep + 1;

        if (nextStep >= totalSteps) return;

        // Build count summary on review step
        if (nextStep === totalSteps - 1) {
            var summary = '';
            $wizard.find('.panelr-wizard-step:not(.panelr-wizard-review)').each(function () {
                var label   = $(this).find('h4').text().trim();
                var total   = $(this).find('.panelr-bouquet-cb').length;
                var checked = $(this).find('.panelr-bouquet-cb:checked').length;
                var str     = checked === total ? 'All (' + total + ')' : checked + ' of ' + total + ' selected';
                summary += '<p><strong>' + label + ':</strong> ' + str + '</p>';
            });
            $('#panelr-bouquet-review').html(summary);
        }

        $current.hide();
        $wizard.find('.panelr-wizard-step[data-step="' + nextStep + '"]').show();
    });

    // ── Bouquet wizard — Back ──────────────────────────────────────────────

    $(document).on('click', '.panelr-wizard-back', function () {
        var $wizard  = $(this).closest('.panelr-bouquet-wizard');
        var $current = $(this).closest('.panelr-wizard-step');
        var prevStep = parseInt($current.data('step'), 10) - 1;
        $current.hide();
        $wizard.find('.panelr-wizard-step[data-step="' + prevStep + '"]').show();
    });

    // ── Save bouquets ──────────────────────────────────────────────────────

    $(document).on('click', '#panelr-save-bouquets-btn', function () {
        var btn  = $(this);
        var $res = $('#panelr-bouquet-result');
        var mode = $('#panelr-bouquet-mode').val();
        var data = { action: 'panelr_portal_update_bouquets', nonce: nonce, mode: mode };

        if (mode === 'editor') {
            ['live', 'vod', 'series'].forEach(function (cat) {
                var ids = [];
                $('.panelr-bouquet-cb[data-category="' + cat + '"]:checked').each(function () {
                    ids.push($(this).val());
                });
                data[cat] = ids;
            });
        } else {
            var selected = $('input[name="panelr_bouquet_flat"]:checked').val();
            data['bouquet_ids'] = selected ? [selected] : [];
        }

        btn.prop('disabled', true);
        $res.hide();

        $.post(ajax, data)
            .done(function (res) {
                if (res.success) {
                    window.location.href = window.location.pathname + '?panelr_bouquets_saved=1';
                } else {
                    showResult($res, res.data.message, false);
                    $res.show();
                    btn.prop('disabled', false);
                }
            })
            .fail(function () {
                showResult($res, 'Request failed.', false);
                $res.show();
                btn.prop('disabled', false);
            });
    });

    // ── Renewal ────────────────────────────────────────────────────────────

    $(document).on('click', '.panelr-renew-btn', function () {
        var btn       = $(this);
        var productId = btn.data('panelr-product-id');
        var $res      = $('#panelr-renewal-result');
        btn.prop('disabled', true);
        $res.hide();
        $.post(ajax, { action: 'panelr_portal_add_renewal', nonce: nonce, panelr_product_id: productId })
            .done(function (res) {
                if (res.success) { window.location.href = res.data.checkout_url; }
                else { showResult($res, res.data.message, false); $res.show(); btn.prop('disabled', false); }
            })
            .fail(function () { showResult($res, 'Request failed.', false); $res.show(); btn.prop('disabled', false); });
    });

});