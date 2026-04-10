jQuery(function ($) {

    if (typeof panelrThankyou === 'undefined') return;

    // Extract order ID from URL
   var orderId = parseInt(panelrThankyou.order_id, 10) || 0;
    
    // Already submitted — disable form
    if (panelrThankyou.already_submitted === '1') {
        $('#panelr-payment-form').hide();
        $('#panelr-thankyou-wrap').append(
            '<div class="panelr-payment-success">' +
            '<strong>&#10003; Payment confirmation already received. Our team will verify and activate your service shortly.</strong>' +
            '</div>'
        );
        return;
    }

    // QR code — runs after DOM + scripts ready
    function renderQR() {
        var el = document.getElementById('panelr-qr-code');
        if (!el || !panelrThankyou.qr_data) return;
        if (typeof QRCode === 'undefined') {
            setTimeout(renderQR, 100);
            return;
        }
        new QRCode(el, {
            text:         panelrThankyou.qr_data,
            width:        180,
            height:       180,
            colorDark:    '#000000',
            colorLight:   '#ffffff',
            correctLevel: QRCode.CorrectLevel.M,
        });
    }
    renderQR();

    // Copy buttons
    $(document).on('click', '.panelr-copy-btn', function () {
        var val = $(this).data('copy');
        var btn = $(this);
        navigator.clipboard.writeText(val).then(function () {
            var orig = btn.text();
            btn.text('Copied!');
            setTimeout(function () { btn.text(orig); }, 1500);
        }).catch(function () {
            var ta = document.createElement('textarea');
            ta.value = val;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
        });
    });

    // Submit payment confirmation
    var submitted = false;

    $('#panelr-submit-payment').on('click', function () {
        if (submitted) return;

        var btn    = $(this);
        var result = $('#panelr-submit-result');
        var txId   = $('#panelr_transaction_id').val().trim();
        var note   = $('#panelr_customer_note').val().trim();

        if (!txId) {
            result.text('Please enter your transaction ID.').css('color', 'red');
            return;
        }

        submitted = true;
        btn.prop('disabled', true);
        result.text('Submitting...').css('color', '');

        $.post(panelrThankyou.ajaxurl, {
            action:             'panelr_submit_payment',
            nonce:              panelrThankyou.nonce,
            confirmation_token: panelrThankyou.confirmation_token,
            transaction_id:     txId,
            customer_note:      note,
            order_id:           orderId,
        })
        .done(function (res) {
            if (res.success) {
                $('#panelr-payment-form').slideUp();
                result.text('');
                $('#panelr-thankyou-wrap').append(
                    '<div class="panelr-payment-success">' +
                    '<strong>&#10003; ' + res.data.message + '</strong>' +
                    '</div>'
                );
            } else {
                result.text('&#10007; ' + res.data.message).css('color', 'red');
                submitted = false;
                btn.prop('disabled', false);
            }
        })
        .fail(function () {
            result.text('Request failed. Please try again or contact support.').css('color', 'red');
            submitted = false;
            btn.prop('disabled', false);
        });
    });

});