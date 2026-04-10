jQuery(function ($) {

    // ── Utility ───────────────────────────────────────────────────────────
    function escHtml(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ── Show / hide API key ────────────────────────────────────────────────
    $('#panelr-toggle-key').on('click', function () {
        var field = $('#panelr_api_key');
        var isPass = field.attr('type') === 'password';
        field.attr('type', isPass ? 'text' : 'password');
        $(this).text(isPass ? 'Hide' : 'Show');
    });

    // ── Test connection ────────────────────────────────────────────────────
    $('#panelr-test-connection').on('click', function () {
        var btn = $(this), result = $('#panelr-test-result');
        btn.prop('disabled', true);
        result.text('Testing…').css('color', '');
        $.post(panelrAdmin.ajaxurl, { action: 'panelr_test_connection', nonce: panelrAdmin.nonce })
            .done(function (res) {
                result.text(res.success ? '✓ ' + res.data.message : '✗ ' + res.data.message)
                      .css('color', res.success ? 'green' : 'red');
            })
            .fail(function () { result.text('✗ Request failed.').css('color', 'red'); })
            .always(function () { btn.prop('disabled', false); });
    });

    // ── Create pages ───────────────────────────────────────────────────────
    $('#panelr-create-pages').on('click', function () {
        var btn = $(this), result = $('#panelr-create-pages-result');
        if (!confirm('This will create three new WordPress pages (My Account, Free Trial, Upgrade Trial). Continue?')) return;
        btn.prop('disabled', true);
        result.text('Creating…').css('color', '');
        $.post(panelrAdmin.ajaxurl, { action: 'panelr_create_pages', nonce: panelrAdmin.nonce })
            .done(function (res) {
                if (res.success) {
                    var pages = res.data.pages;
                    var msg = [];
                    $.each(pages, function (key, p) {
                        var $sel = $('#panelr-page-' + key);
                        if ($sel.find('option[value="' + p.id + '"]').length === 0) {
                            $sel.append('<option value="' + p.id + '">' + p.title + '</option>');
                        }
                        $sel.val(p.id);
                        msg.push(p.title + ' (' + p.status + ')');
                    });
                    result.text('✓ ' + msg.join(', ')).css('color', 'green');
                } else {
                    result.text('✗ ' + res.data.message).css('color', 'red');
                }
            })
            .fail(function () { result.text('✗ Request failed.').css('color', 'red'); })
            .always(function () { btn.prop('disabled', false); });
    });

    // ── Save page assignments ──────────────────────────────────────────────
    $('#panelr-save-pages').on('click', function () {
        var btn = $(this), result = $('#panelr-save-pages-result');
        btn.prop('disabled', true);
        result.text('Saving…').css('color', '');
        $.post(panelrAdmin.ajaxurl, {
            action:             'panelr_save_pages',
            nonce:              panelrAdmin.nonce,
            portal:             $('#panelr-page-portal').val(),
            trial:              $('#panelr-page-trial').val(),
            upgrade:            $('#panelr-page-upgrade').val(),
            order_status:       $('#panelr-page-order_status').val(),
        })
        .done(function (res) {
            result.text(res.success ? '✓ ' + res.data.message : '✗ ' + res.data.message)
                  .css('color', res.success ? 'green' : 'red');
        })
        .fail(function () { result.text('✗ Request failed.').css('color', 'red'); })
        .always(function () { btn.prop('disabled', false); });
    });

    // ── Load / refresh payment methods ─────────────────────────────────────
    $('#panelr-refresh-pm').on('click', function () {
        var btn = $(this), result = $('#panelr-refresh-pm-result'), wrap = $('#panelr-pm-mapping-wrap');
        btn.prop('disabled', true);
        result.text('Loading…').css('color', '');
        wrap.html('');
        $('#panelr-save-pm-wrap').hide();

        $.post(panelrAdmin.ajaxurl, { action: 'panelr_refresh_payment_methods', nonce: panelrAdmin.nonce })
            .done(function (res) {
                if (!res.success) {
                    result.text('✗ ' + res.data.message).css('color', 'red');
                    return;
                }

                result.text('').css('color', '');
                var panelrMethods = res.data.methods;
                var wcGateways    = res.data.gateways;
                var currentMap    = res.data.map;

                if (!wcGateways.length) {
                    wrap.html('<p>No active WooCommerce payment gateways found.</p>');
                    return;
                }

                var html = '<table class="widefat striped" style="max-width:600px;margin-top:8px;">';
                html += '<thead><tr><th>WooCommerce Gateway</th><th>Panelr Payment Method</th></tr></thead><tbody>';
                $.each(wcGateways, function (i, gw) {
                    html += '<tr><td>' + escHtml(gw.title) + ' <code style="font-size:11px">' + escHtml(gw.id) + '</code></td>';
                    html += '<td><select class="panelr-pm-map" data-gateway="' + escHtml(gw.id) + '">';
                    html += '<option value="">— Not mapped —</option>';
                    $.each(panelrMethods, function (j, pm) {
                        var label    = pm.display_label || pm.name;
                        var selected = (currentMap[gw.id] && parseInt(currentMap[gw.id]) === pm.id) ? ' selected' : '';
                        html += '<option value="' + pm.id + '"' + selected + '>' + escHtml(label) + ' (' + escHtml(pm.type) + ')</option>';
                    });
                    html += '</select></td></tr>';
                });
                html += '</tbody></table>';
                wrap.html(html);
                $('#panelr-save-pm-wrap').show();
            })
            .fail(function () { result.text('✗ Request failed.').css('color', 'red'); })
            .always(function () { btn.prop('disabled', false); });
    });

    // ── Save payment mapping ───────────────────────────────────────────────
    $('#panelr-save-pm').on('click', function () {
        var btn = $(this), result = $('#panelr-save-pm-result');
        var map = {};
        $('.panelr-pm-map').each(function () {
            var gw  = $(this).data('gateway');
            var val = $(this).val();
            if (val) map[gw] = val;
        });
        btn.prop('disabled', true);
        result.text('Saving…').css('color', '');
        $.post(panelrAdmin.ajaxurl, {
            action:      'panelr_save_payment_map',
            nonce:       panelrAdmin.nonce,
            payment_map: map,
        })
        .done(function (res) {
            result.text(res.success ? '✓ ' + res.data.message : '✗ ' + res.data.message)
                  .css('color', res.success ? 'green' : 'red');
        })
        .fail(function () { result.text('✗ Request failed.').css('color', 'red'); })
        .always(function () { btn.prop('disabled', false); });
    });

    // ── Auto-load payment methods on page open ────────────────────────────
    if ($('#panelr-pm-mapping-wrap').length) {
        $('#panelr-refresh-pm').trigger('click');
    }

    // ── Sync products ──────────────────────────────────────────────────────
    $('#panelr-sync-products').on('click', function () {
        var btn = $(this), result = $('#panelr-sync-result');
        btn.prop('disabled', true);
        result.text('Syncing…').css('color', '');
        $.post(panelrAdmin.ajaxurl, { action: 'panelr_sync_products', nonce: panelrAdmin.nonce })
            .done(function (res) {
                if (res.success) {
                    result.text('✓ ' + res.data.message).css('color', 'green');
                    setTimeout(function () { location.reload(); }, 1500);
                } else {
                    result.text('✗ ' + res.data.message).css('color', 'red');
                }
            })
            .fail(function () { result.text('✗ Request failed.').css('color', 'red'); })
            .always(function () { btn.prop('disabled', false); });
    });


    // ── Save trial product ─────────────────────────────────────────────────
    $('#panelr-save-trial-product').on('click', function () {
        var btn = $(this), result = $('#panelr-trial-product-result');
        btn.prop('disabled', true);
        result.text('Saving…').css('color', '');
        $.post(panelrAdmin.ajaxurl, {
            action:            'panelr_save_trial_product',
            nonce:             panelrAdmin.nonce,
            panelr_product_id: $('#panelr-trial-product').val(),
        })
        .done(function (res) {
            result.text(res.success ? '✓ ' + res.data.message : '✗ ' + res.data.message)
                  .css('color', res.success ? 'green' : 'red');
        })
        .fail(function () { result.text('✗ Request failed.').css('color', 'red'); })
        .always(function () { btn.prop('disabled', false); });
    });


    // ── Save trial settings ────────────────────────────────────────────────
    $('#panelr-save-trial-settings').on('click', function () {
        var btn = $(this), result = $('#panelr-trial-settings-result');
        btn.prop('disabled', true);
        result.text('Saving…').css('color', '');
        $.post(panelrAdmin.ajaxurl, {
            action:          'panelr_save_trial_settings',
            nonce:           panelrAdmin.nonce,
            trials_enabled:  $('#panelr-trials-enabled').is(':checked') ? '1' : '0',
        })
        .done(function (res) {
            result.text(res.success ? '✓ ' + res.data.message : '✗ ' + res.data.message)
                  .css('color', res.success ? 'green' : 'red');
        })
        .fail(function () { result.text('✗ Request failed.').css('color', 'red'); })
        .always(function () { btn.prop('disabled', false); });
    });

    // ── Save portal settings ───────────────────────────────────────────────
    $('#panelr-save-portal-settings').on('click', function () {
        var btn = $(this), result = $('#panelr-portal-settings-result');
        btn.prop('disabled', true);
        result.text('Saving…').css('color', '');
        $.post(panelrAdmin.ajaxurl, {
            action:          'panelr_save_portal_settings',
            nonce:           panelrAdmin.nonce,
            allow_bouquets:  $('#panelr-allow-bouquets').is(':checked') ? '1' : '0',
            theme:           $('#panelr-theme').val(),
        })
        .done(function (res) {
            result.text(res.success ? '✓ ' + res.data.message : '✗ ' + res.data.message)
                  .css('color', res.success ? 'green' : 'red');
        })
        .fail(function () { result.text('✗ Request failed.').css('color', 'red'); })
        .always(function () { btn.prop('disabled', false); });
    });

});