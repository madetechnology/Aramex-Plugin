jQuery(document).ready(function($) {
    $('#aramex-test-connection-btn').on('click', function() {
        var status = $('#aramex-test-connection-status');
        status.html('<span style="color:blue;">Testing connection...</span>');

        $.post(aramexAjax.ajax_url, {
            action: 'aramex_shipping_aunz_test_connection_ajax',
            nonce: aramexAjax.nonce
        }, function(response) {
            if (response.success) {
                status.html('<span style="color:green;">' + response.data.message + '</span>');
            } else {
                status.html('<span style="color:red;">' + response.data.message + '</span>');
            }
        }).fail(function() {
            status.html('<span style="color:red;">Error testing connection. Please try again.</span>');
        });
    });
}); 