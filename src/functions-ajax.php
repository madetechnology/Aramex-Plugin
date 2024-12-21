<?php
defined( 'ABSPATH' ) || exit;

/**
 * AJAX callback to test the connection.
 */
function aramex_shipping_aunz_test_connection_ajax_callback() {
	check_ajax_referer( 'aramex_test_connection_nonce', 'nonce' );

	$api_key        = get_option( 'aramex_shipping_aunz_api_key', '' );
	$secret         = get_option( 'aramex_shipping_aunz_api_secret', '' );
	$origin_country = get_option( 'aramex_shipping_aunz_origin_country', 'nz' );

	$access_token = aramex_shipping_aunz_get_access_token( $api_key, $secret, $origin_country );
	if ( $access_token ) {
		wp_send_json_success( array( 'message' => __( 'API connection successful.', 'aramex-shipping-aunz' ) ) );
	} else {
		wp_send_json_error( array( 'message' => __( 'API connection failed. Check your credentials.', 'aramex-shipping-aunz' ) ) );
	}
}

function aramex_create_consignment_callback() {
    // Verify the nonce
    check_ajax_referer( 'aramex_create_consignment_nonce', 'nonce' );

    // Get the order ID
    $order_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : 0;
    if ( ! $order_id ) {
        wp_send_json_error( array( 'message' => 'Invalid order ID.' ) );
    }

    // Get the order object
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        wp_send_json_error( array( 'message' => 'Order not found.' ) );
    }

    // Get the shipping address
    $to_address = array(
        'ContactName'    => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
        'PhoneNumber'    => $order->get_billing_phone(),
        'Email'          => $order->get_billing_email(),
        'Address'        => array(
            'StreetAddress'   => $order->get_shipping_address_1(),
            'Locality'        => $order->get_shipping_city(),
            'StateOrProvince' => $order->get_shipping_state(),
            'PostalCode'      => "0610",
            'Country'         => $order->get_shipping_country(),
        ),
    );

    // Prepare the request body
    $body = array(
        'To'    => $to_address,
        'Items' => array(
            array(
                'Quantity'    => 1,
                'PackageType' => 'S',
                'SatchelSize' => 'A4',
            ),
        ),
    );

    // Include the shipping method class
    require_once ARAMEX_PLUGIN_DIR . 'src/class-aramex-shipping-method.php';

    // Get the access token
    $shipping_method = new My_Shipping_Method();
    $access_token = $shipping_method->get_access_token();

    if ( ! $access_token ) {
        wp_send_json_error( array( 'message' => 'Failed to retrieve access token.' ) );
    }

    // Make the API request
    $response = wp_remote_post( 'https://api.myfastway.co.nz/api/consignments', array(
        'method'    => 'POST',
        'body'      => json_encode( $body ),
        'headers'   => array(
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $access_token,
        ),
    ) );

    // Handle the API response
    if ( is_wp_error( $response ) ) {
        wp_send_json_error( array( 'message' => $response->get_error_message() ) );
    }

    $response_body = wp_remote_retrieve_body( $response );
    $response_data = json_decode( $response_body, true );

    if ( isset( $response_data['data'] ) ) {
        $con_id = $response_data['data']['conId'];

        // Save the conId as custom field in the order
        //update_post_meta( $order_id, '_aramex_conId', $con_id );
        //$success = update_post_meta($order_id, 'aramex_conId', $con_id);
        //$order->update_meta_data('aramex_conId', $con_id);

        // Save the conId as a custom field using the $order object
        $order->update_meta_data( 'aramex_conId', $con_id );
        $order->save(); // Save the order to persist the metadata

        // Add the ConID to the order notes
        $order->add_order_note( sprintf( __( 'Consignment created successfully. ConID: %s', 'aramex-shipping-aunz' ), $con_id ) );

        wp_send_json_success( array(
            'message' => 'Consignment created successfully!',
            'data'    => $response_data['data'],
        ) );
    } else {
        wp_send_json_error( array(
            'message' => 'Failed to create consignment.',
            'data'    => $response_data,
        ) );
    }
}
add_action( 'wp_ajax_aramex_create_consignment', 'aramex_create_consignment_callback' );