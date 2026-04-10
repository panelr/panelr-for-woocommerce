jQuery(function ($) {

    if (typeof panelrTrial === 'undefined') return;

    $(document).on('click', '#panelr-trial-submit', function () {
        var btn   = $(this);
        var name  = $('#panelr-trial-name').val().trim();
        var email = $('#panelr-trial-email').val().trim();
        var $err  = $('#panelr-trial-error');

        $err.hide();

        if (!email) {
            $err.text('Please enter your email address.').show();
            return;
        }

        btn.prop('disabled', true).text('Submitting\u2026');

        $.post(panelrTrial.ajaxurl, {
            action: 'panelr_request_trial',
            nonce:  panelrTrial.nonce,
            name:   name,
            email:  email,
        })
        .done(function (res) {
            if (res.success) {
                $('#panelr-trial-form').hide();
                $('#panelr-trial-result')
                    .html('<p class="woocommerce-message">' + res.data.message + '</p>')
                    .show();
            } else {
                $err.text(res.data.message).show();
                btn.prop('disabled', false).text('Request Free Trial');
            }
        })
        .fail(function () {
            $err.text('Request failed. Please try again.').show();
            btn.prop('disabled', false).text('Request Free Trial');
        });
    });

    // Allow Enter key
    $(document).on('keydown', '#panelr-trial-name, #panelr-trial-email', function (e) {
        if (e.key === 'Enter') $('#panelr-trial-submit').trigger('click');
    });

});