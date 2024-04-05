jQuery(document).ready(function($) {
    $('#generate_access_token').click(function(e) {
        e.preventDefault();
        generateAccessToken();
    });

    $('#woocommerce-aramex-settings').submit(function(e) {
        e.preventDefault();
        generateAccessToken();
        $(this).unbind('submit').submit();
    });

    function generateAccessToken() {
        $.ajax({
            url: ajaxurl, // WordPress AJAX URL
            type: 'POST',
            data: {
                action: 'generate_access_token' // AJAX action hook
            },
            success: function(response) {
                alert('Access token generated successfully.');
                // You can update the token field with the response here
            },
            error: function(xhr, status, error) {
                alert('Failed to generate access token. Error: ' + error);
            }
        });
    }
});
