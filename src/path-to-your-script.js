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

jQuery( document ).ready( function( $ ) {
      
    $( '#custom-action-button' ).on( 'click', function() {
        const orderId = $( this ).data( 'order-id' );
        console.log('Button clicked. Order ID:', orderId); // Debugging: Log the Order ID.

        $.ajax({
            url: customAdminData.ajax_url,
            method: 'POST',
            data: {
                action: 'create_consignment_action',
                nonce: customAdminData.nonce,
                order_id: orderId,
            },
            beforeSend: function() {
                console.log('AJAX request is about to be sent.'); // Debugging: Log before the request is sent.
                alert( 'Processing your request...' );
            },
            success: function( response ) {
                console.log('AJAX request succeeded. Response:', response); // Debugging: Log the successful response.

                if ( response.success ) {
                    alert( response.data.message );
                } else {
                    alert( response.data.message );
                }
            },
            error: function( jqXHR, textStatus, errorThrown ) {
                console.log('AJAX request failed. Error:', errorThrown); // Debugging: Log the error.
                console.log('Response text:', jqXHR.responseText); // Debugging: Log the response text.
                alert( 'An error occurred. Please try again.' );
            },
        });
    });
});


jQuery( document ).ready( function( $ ) {
      
  $( '#custom-action-delete-button' ).on( 'click', function() {
      const orderId = $( this ).data( 'order-id' );
      console.log('Delete Button clicked. Order ID:', orderId); // Debugging: Log the Order ID.

      $.ajax({
          url: customAdminDataDelete.ajax_url,
          method: 'POST',
          data: {
              action: 'delete_consignment_action',
              nonce: customAdminDataDelete.nonce,
              order_id: orderId,
          },
          beforeSend: function() {
              console.log('Delete AJAX request is about to be sent.'); // Debugging: Log before the request is sent.
              alert( 'Processing your request...' );
          },
          success: function( response ) {
              console.log('Delete AJAX request succeeded. Response:', response); // Debugging: Log the successful response.

              if ( response.success ) {
                  alert( response.data.message );
              } else {
                  alert( response.data.message );
              }
          },
          error: function( jqXHR, textStatus, errorThrown ) {
              console.log('AJAX request failed. Error:', errorThrown); // Debugging: Log the error.
              console.log('Response text:', jqXHR.responseText); // Debugging: Log the response text.
              alert( 'An error occurred. Please try Delete again.' );
          },
      });
  });
});



jQuery(document).ready(function ($) {
    $('#custom-action-print-label-button').on('click', function () {
        const orderId = $(this).data('order-id');

        $.ajax({
            url: customAdminDataPrint.ajax_url,
            method: 'POST',
            data: {
                action: 'print_label_action',
                nonce: customAdminDataPrint.nonce,
                order_id: orderId,
            },
            beforeSend: function () {
                alert('Fetching label...');
            },
            success: function (response) {
                if (response.success) {
                    // Open the PDF file in a new tab
                    window.open(response.data.label_url, '_blank');
                } else {
                    alert(response.data.message);
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                alert('An error occurred. Please try again.');
            },
        });
    });
});