jQuery(function ($) {

    if (typeof panelrUpgrade === 'undefined') return;

	function escHtml(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    var ajax  = panelrUpgrade.ajaxurl;
    var nonce = panelrUpgrade.nonce;

    function formatDate(str) {
        if (!str) return '—';
        var d = new Date(str.replace(' ', 'T'));
        return d.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
    }

    function showError($el, msg) { $el.text(msg).show(); }
    function hideError($el)      { $el.text('').hide(); }

    // ── Verify trial code ──────────────────────────────────────────────────

    function verify() {
        var code = $('#panelr-upgrade-code').val().trim().toUpperCase();
        var btn  = $('#panelr-upgrade-verify');
        var $err = $('#panelr-upgrade-error');

        hideError($err);

        if (!code) {
            showError($err, 'Please enter your trial code.');
            return;
        }

        btn.prop('disabled', true).text('Verifying\u2026');

        $.post(ajax, { action: 'panelr_upgrade_verify', nonce: nonce, trial_code: code })
            .done(function (res) {
                if (res.success) {
                    var d = res.data;
                    $('#panelr-upgrade-name').text(d.customer_name || '—');
                    $('#panelr-upgrade-email').text(d.customer_email || '—');
                    $('#panelr-upgrade-expiry').text(formatDate(d.expiration_date));

                    // Build products table
                    var rows = '';
                    if (d.eligible_products && d.eligible_products.length) {
                        $.each(d.eligible_products, function (i, p) {
                            rows += '<tr>' +
                                '<td>' + escHtml(p.name) + '</td>' +
                                '<td>$' + parseFloat(p.price_decimal).toFixed(2) + '</td>' +
                                '<td>' + p.connections + ' ' + (p.connections === 1 ? 'connection' : 'connections') +
                                    ' &middot; ' + p.duration_months + ' ' + (p.duration_months === 1 ? 'month' : 'months') + '</td>' +
                                '<td><button type="button" class="button panelr-upgrade-select" data-product-id="' + p.id + '">' +
                                    'Upgrade</button></td>' +
                                '</tr>';
                        });
                    } else {
                        rows = '<tr><td colspan="4">No plans available. Please contact support.</td></tr>';
                    }
                    $('#panelr-upgrade-products-table').html(rows);

                    $('#panelr-upgrade-form').hide();
                    $('#panelr-upgrade-dashboard').show();
                } else {
                    showError($err, res.data.message);
                    btn.prop('disabled', false).text('Continue');
                }
            })
            .fail(function () {
                showError($err, 'Request failed. Please try again.');
                btn.prop('disabled', false).text('Continue');
            });
    }

    $(document).on('click', '#panelr-upgrade-verify', verify);
    $(document).on('keydown', '#panelr-upgrade-code', function (e) {
        if (e.key === 'Enter') verify();
    });

    // ── Select plan ────────────────────────────────────────────────────────

    $(document).on('click', '.panelr-upgrade-select', function () {
        var btn       = $(this);
        var productId = btn.data('product-id');
        var $err      = $('#panelr-upgrade-cart-error');

        hideError($err);
        btn.prop('disabled', true).text('Adding\u2026');

        $.post(ajax, { action: 'panelr_upgrade_add_to_cart', nonce: nonce, panelr_product_id: productId })
            .done(function (res) {
                if (res.success) {
                    window.location.href = res.data.checkout_url;
                } else {
                    showError($err, res.data.message);
                    btn.prop('disabled', false).text('Upgrade');
                }
            })
            .fail(function () {
                showError($err, 'Request failed. Please try again.');
                btn.prop('disabled', false).text('Upgrade');
            });
    });

});