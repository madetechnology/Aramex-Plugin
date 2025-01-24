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

    // Track shipment button click handler
    $('#custom-action-track-shipment').on('click', function(e) {
        e.preventDefault();
        var button = $(this);
        var order_id = button.data('order-id');
        var label_number = button.data('label-number');
        var modal = $('#aramex-tracking-modal');
        var content = $('#aramex-tracking-content');

        button.prop('disabled', true);
        content.html('<p>Loading tracking information...</p>');
        modal.show();

        $.ajax({
            url: aramexOrderActions.ajax_url,
            type: 'POST',
            data: {
                action: 'track_shipment_action',
                order_id: order_id,
                label_number: label_number,
                nonce: aramexOrderActions.nonces.track
            },
            success: function(response) {
                if (response.success) {
                    var events = response.data.tracking_events;
                    var html = '<table class="widefat" style="margin-top: 10px;">';
                    html += '<thead><tr>';
                    html += '<th>Date/Time</th>';
                    html += '<th>Status</th>';
                    html += '<th>Description</th>';
                    html += '<th>Location</th>';
                    html += '</tr></thead><tbody>';

                    if (events.length > 0) {
                        events.forEach(function(event) {
                            html += '<tr>';
                            html += '<td>' + event.date + '</td>';
                            html += '<td>' + event.status + '</td>';
                            html += '<td>' + (event.scan_description || event.description) + '</td>';
                            html += '<td>' + event.location + '</td>';
                            html += '</tr>';
                        });
                    } else {
                        html += '<tr><td colspan="4">No tracking events found.</td></tr>';
                    }

                    html += '</tbody></table>';
                    content.html(html);
                } else {
                    content.html('<p class="error">' + (response.data.message || 'Error retrieving tracking information') + '</p>');
                }
                button.prop('disabled', false);
            },
            error: function() {
                content.html('<p class="error">Error communicating with server</p>');
                button.prop('disabled', false);
            }
        });
    });

    // Modal close button handler
    $('.close').on('click', function() {
        $('#aramex-tracking-modal').hide();
    });

    // Close modal when clicking outside
    $(window).on('click', function(e) {
        var modal = $('#aramex-tracking-modal');
        if (e.target == modal[0]) {
            modal.hide();
        }
    });
}); 