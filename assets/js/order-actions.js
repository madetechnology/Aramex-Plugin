jQuery(document).ready(function($) {
    // Create Consignment button click handler
    $('.custom-action-button').on('click', function(e) {
        e.preventDefault();
        var orderId = $(this).data('order-id');
        
        // Show loading state
        $(this).prop('disabled', true).text('Processing...');
        var button = $(this);
        
        $.ajax({
            url: aramexOrderActions.ajax_url,
            type: 'POST',
            data: {
                action: 'create_consignment_action',
                order_id: orderId,
                nonce: aramexOrderActions.nonces.create
            },
            success: function(response) {
                if (response.success) {
                    // Get the consignment ID and label number from the response
                    var conId = response.data.data.conId;
                    var labelNo = response.data.data.labelNo;

                    // Create delete button
                    var deleteButton = $('<button type="button" class="button custom-action-delete-button">')
                        .attr('data-order-id', orderId)
                        .attr('data-consignment-id', conId)
                        .text('Delete Consignment');

                    // Create print button
                    var printButton = $('<button type="button" class="button custom-action-print-label">')
                        .attr('data-order-id', orderId)
                        .attr('data-consignment-id', labelNo)
                        .text('Print Label');

                    // Create track button
                    var trackButton = $('<button type="button" class="button custom-action-track-shipment">')
                        .attr('data-order-id', orderId)
                        .attr('data-label-number', labelNo)
                        .text('Track Shipment');

                    // Add tracking modal
                    var trackingModal = $('<div class="aramex-tracking-modal">')
                        .css({
                            'display': 'none',
                            'position': 'fixed',
                            'z-index': '1000',
                            'left': '0',
                            'top': '0',
                            'width': '100%',
                            'height': '100%',
                            'overflow': 'auto',
                            'background-color': 'rgba(0,0,0,0.4)'
                        })
                        .append(
                            $('<div>').css({
                                'background-color': '#fefefe',
                                'margin': '15% auto',
                                'padding': '20px',
                                'border': '1px solid #888',
                                'width': '80%',
                                'max-width': '600px'
                            }).append(
                                $('<span class="aramex-modal-close">').css({
                                    'color': '#aaa',
                                    'float': 'right',
                                    'font-size': '28px',
                                    'font-weight': 'bold',
                                    'cursor': 'pointer'
                                }).html('&times;'),
                                $('<h2>').text('Shipment Tracking'),
                                $('<div class="aramex-tracking-content">')
                            )
                        );

                    // Replace the create button with the new buttons
                    button.after(trackingModal);
                    button.after(trackButton);
                    button.after(printButton);
                    button.after(deleteButton);
                    button.remove();

                    // Reinitialize event handlers for the new buttons
                    initializeButtonHandlers();
                } else {
                    // Handle different types of errors
                    if (response.data.type === 'missing_dimensions') {
                        showDimensionsModal(response.data.products, button);
                    } else {
                        alert(response.data.message || 'An error occurred.');
                    }
                    button.prop('disabled', false).text('Create Consignment');
                }
            },
            error: function() {
                alert('An error occurred while processing your request.');
                button.prop('disabled', false).text('Create Consignment');
            }
        });
    });

    // Function to initialize event handlers for dynamically added buttons
    function initializeButtonHandlers() {
        // Delete button handler
        $(document).on('click', '.custom-action-delete-button', function(e) {
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

        // Print button handler
        $(document).on('click', '.custom-action-print-label', function(e) {
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

        // Track button handler
        $(document).on('click', '.custom-action-track-shipment', function(e) {
            e.preventDefault();
            var button = $(this);
            var order_id = button.data('order-id');
            var label_number = button.data('label-number');
            var modal = $('.aramex-tracking-modal');
            var content = modal.find('.aramex-tracking-content');

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

                        if (events && events.length > 0) {
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
        $(document).on('click', '.aramex-modal-close', function() {
            $('.aramex-tracking-modal').hide();
        });

        // Close modal when clicking outside
        $(document).on('click', '.aramex-tracking-modal', function(e) {
            if (e.target === this) {
                $(this).hide();
            }
        });
    }

    // Function to show the dimensions modal
    function showDimensionsModal(products, button) {
        // Remove any existing modal
        $('#aramex-dimensions-modal').remove();

        // Create modal HTML
        var modalHtml = '<div id="aramex-dimensions-modal" class="aramex-modal">' +
            '<div class="aramex-modal-content">' +
            '<span class="aramex-modal-close">&times;</span>' +
            '<h2>Missing Product Dimensions</h2>' +
            '<p>The following products are missing dimensions and cannot be shipped until dimensions are added:</p>' +
            '<ul class="aramex-missing-dimensions-list">';

        products.forEach(function(product) {
            var missingDims = [];
            if (product.missing.length) missingDims.push('length');
            if (product.missing.width) missingDims.push('width');
            if (product.missing.height) missingDims.push('height');

            modalHtml += '<li>' +
                '<strong>' + product.name + '</strong><br>' +
                'Missing: ' + missingDims.join(', ') + '<br>' +
                '<a href="' + product.edit_link + '" target="_blank" class="button">Edit Product</a>' +
                '</li>';
        });

        modalHtml += '</ul>' +
            '<p>Please add the missing dimensions to these products before creating the consignment.</p>' +
            '</div></div>';

        // Add modal to page
        $('body').append(modalHtml);

        // Add modal styles if not already present
        if ($('#aramex-modal-styles').length === 0) {
            var styles = '<style id="aramex-modal-styles">' +
                '.aramex-modal { display: block; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; ' +
                'overflow: auto; background-color: rgba(0,0,0,0.4); }' +
                '.aramex-modal-content { background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; ' +
                'width: 80%; max-width: 600px; position: relative; }' +
                '.aramex-modal-close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }' +
                '.aramex-modal-close:hover { color: #000; }' +
                '.aramex-missing-dimensions-list { margin: 20px 0; }' +
                '.aramex-missing-dimensions-list li { margin-bottom: 15px; padding: 10px; background: #f8f8f8; border: 1px solid #ddd; }' +
                '.aramex-missing-dimensions-list .button { margin-top: 5px; }' +
                '</style>';
            $('head').append(styles);
        }

        // Handle modal close
        $('.aramex-modal-close').on('click', function() {
            $('#aramex-dimensions-modal').remove();
        });

        // Close modal when clicking outside
        $(window).on('click', function(e) {
            if ($(e.target).is('#aramex-dimensions-modal')) {
                $('#aramex-dimensions-modal').remove();
            }
        });
    }

    // Initialize handlers when document is ready
    initializeButtonHandlers();
}); 