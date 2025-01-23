jQuery(document).ready(function($) {
    // Create consignment button click handler
    $('#custom-action-button').on('click', function(e) {
        e.preventDefault();
        var button = $(this);
        var order_id = button.data('order-id');

        button.prop('disabled', true);
        
        $.ajax({
            url: aramexOrderActions.ajax_url,
            type: 'POST',
            data: {
                action: 'create_consignment_action',
                order_id: order_id,
                nonce: aramexOrderActions.nonces.create
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert(response.data.message || 'Error creating consignment');
                    button.prop('disabled', false);
                }
            },
            error: function() {
                alert('Error communicating with server');
                button.prop('disabled', false);
            }
        });
    });

    // Delete consignment button click handler
    $('#custom-action-delete-button').on('click', function(e) {
        e.preventDefault();
        if (!confirm('Are you sure you want to delete this consignment?')) {
            return;
        }

        var button = $(this);
        var order_id = button.data('order-id');
        var consignment_id = button.data('consignment-id');

        button.prop('disabled', true);

        $.ajax({
            url: aramexOrderActions.ajax_url,
            type: 'POST',
            data: {
                action: 'delete_consignment_action',
                order_id: order_id,
                consignment_id: consignment_id,
                nonce: aramexOrderActions.nonces.delete
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert(response.data.message || 'Error deleting consignment');
                    button.prop('disabled', false);
                }
            },
            error: function() {
                alert('Error communicating with server');
                button.prop('disabled', false);
            }
        });
    });

    // Print label button click handler
    $('#custom-action-print-label').on('click', function(e) {
        e.preventDefault();
        var button = $(this);
        var order_id = button.data('order-id');
        var consignment_id = button.data('consignment-id');

        button.prop('disabled', true);

        $.ajax({
            url: aramexOrderActions.ajax_url,
            type: 'POST',
            data: {
                action: 'print_label_action',
                order_id: order_id,
                con_id: consignment_id,
                nonce: aramexOrderActions.nonces.print
            },
            success: function(response) {
                if (response.success) {
                    // Open the PDF in a new window
                    window.open(response.data.pdf_url, '_blank');
                } else {
                    alert(response.data.message || 'Error generating label');
                }
                button.prop('disabled', false);
            },
            error: function() {
                alert('Error communicating with server');
                button.prop('disabled', false);
            }
        });
    });
}); 