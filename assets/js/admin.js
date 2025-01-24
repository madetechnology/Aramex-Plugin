jQuery(document).ready(function($) {
    // Test Connection Button Click Handler
    $('#aramex-test-connection-btn').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var statusDiv = $('#aramex-test-connection-status');
        
        // Disable button and show testing message
        button.prop('disabled', true);
        button.text(aramexAdmin.testing_text);
        statusDiv.html('');
        
        // Make the AJAX request
        $.ajax({
            url: aramexAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'aramex_shipping_aunz_test_connection_ajax',
                nonce: aramexAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    statusDiv.html('<div class="notice notice-success inline"><p>' + aramexAdmin.test_connection_success + '</p></div>');
                } else {
                    statusDiv.html('<div class="notice notice-error inline"><p>' + (response.data.message || aramexAdmin.test_connection_error) + '</p></div>');
                }
            },
            error: function() {
                statusDiv.html('<div class="notice notice-error inline"><p>' + aramexAdmin.test_connection_error + '</p></div>');
            },
            complete: function() {
                // Re-enable button and restore original text
                button.prop('disabled', false);
                button.text('Test Connection');
            }
        });
    });
}); 