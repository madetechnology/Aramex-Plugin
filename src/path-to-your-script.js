jQuery(document).ready(function($) {
    $('#testForm button').on('click', function(e) {
        e.preventDefault(); // Prevent default form submission

        $.ajax({
            url: aramexAjax.ajaxUrl, // Localized in PHP
            type: 'POST',
            data: {
                action: 'test_connection', // The WordPress action
                security: aramexAjax.nonce // The nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Success: ' + response.data.message);
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            error: function(xhr, status, error) {
                alert('AJAX Request Failed: ' + error);
            }
        });
    });
});