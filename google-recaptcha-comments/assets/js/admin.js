(function($) {
    'use strict';

    $(function() {
        // Toggle v3 threshold row visibility
        var $versionSelect = $('#grc_recaptcha_version');
        var $thresholdRow = $('#grc-v3-threshold-row');

        $versionSelect.on('change', function() {
            if ($(this).val() === 'v3') {
                $thresholdRow.show();
            } else {
                $thresholdRow.hide();
            }
        });

        // Test connection button
        $('#grc-test-connection').on('click', function() {
            var $button = $(this);
            var $result = $('#grc-test-result');
            var secretKey = $('#grc_secret_key').val();
            var siteKey = $('#grc_site_key').val();

            if (!siteKey || !secretKey) {
                $result.html('<span style="color:#d63638;">' + grcAdmin.strings.noKeys + '</span>');
                return;
            }

            $button.prop('disabled', true);
            $result.html('<span style="color:#666;">' + grcAdmin.strings.testing + '</span>');

            $.post(grcAdmin.ajaxUrl, {
                action: 'grc_test_connection',
                nonce: grcAdmin.nonce,
                secret_key: secretKey
            })
            .done(function(response) {
                if (response.success) {
                    $result.html('<span style="color:#00a32a;">' + response.data.message + '</span>');
                } else {
                    $result.html('<span style="color:#d63638;">' + response.data.message + '</span>');
                }
            })
            .fail(function() {
                $result.html('<span style="color:#d63638;">' + grcAdmin.strings.error + '</span>');
            })
            .always(function() {
                $button.prop('disabled', false);
            });
        });
    });
})(jQuery);
