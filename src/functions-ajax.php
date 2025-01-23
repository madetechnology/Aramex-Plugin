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
            'PostalCode'      => $order->get_shipping_postcode(),
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

    // Get the origin country
    $origin_country = get_option( 'aramex_shipping_aunz_origin_country', 'nz' );
    $api_base_url = aramex_shipping_aunz_get_api_base_url( $origin_country );

    // Make the API request
    $response = wp_remote_post( $api_base_url . '/api/consignments', array(
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

function aramex_delete_consignment_callback() {
    // Verify the nonce
    check_ajax_referer('aramex_delete_consignment_nonce', 'nonce');

    // Get the order ID
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    if (!$order_id) {
        wp_send_json_error(array('message' => 'Invalid order ID.'));
    }

    // Get the order object
    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error(array('message' => 'Order not found.'));
    }

    // Check if the order has the 'aramex_conId' meta field
    $con_id = $order->get_meta('aramex_conId');
    if (!$con_id) {
        wp_send_json_error(array('message' => 'Consignment ID not found for this order.'));
    }

    // Include the shipping method class
    require_once ARAMEX_PLUGIN_DIR . 'src/class-aramex-shipping-method.php';

    // Get the access token
    $shipping_method = new My_Shipping_Method();
    $access_token = $shipping_method->get_access_token();

    if (!$access_token) {
        wp_send_json_error(array('message' => 'Failed to retrieve access token.'));
    }

    // Get the origin country and API base URL
    $origin_country = get_option( 'aramex_shipping_aunz_origin_country', 'nz' );
    $api_base_url = aramex_shipping_aunz_get_api_base_url( $origin_country );

    // Prepare the API endpoint and delete reason
    $delete_reason_id = 3; // Reason ID for "Created in Error"
    $api_endpoint = $api_base_url . "/api/consignments/{$con_id}/reason/{$delete_reason_id}";

    // Make the DELETE API request
    $response = wp_remote_request($api_endpoint, array(
        'method'    => 'DELETE',
        'headers'   => array(
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $access_token,
        ),
    ));

    // Handle the API response
    if (is_wp_error($response)) {
        wp_send_json_error(array('message' => $response->get_error_message()));
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    $response_data = json_decode($response_body, true);

    // Log response for debugging
    error_log('Response Code: ' . $response_code);
    error_log('Response Body: ' . print_r($response_data, true));

    // Check if the response code indicates success
    if ($response_code === 204 || ($response_code >= 200 && $response_code < 300)) {
        // Remove the 'aramex_conId' meta field
        $order->delete_meta_data('aramex_conId');
        $order->save();

        // Add a note to the order
        $order->add_order_note(__('Consignment deleted successfully.', 'aramex-shipping-aunz'));

        wp_send_json_success(array('message' => 'Consignment deleted successfully.'));
    } else {
        // Handle unexpected response
        $error_message = isset($response_data['message']) ? $response_data['message'] : 'Unexpected error occurred.';
        wp_send_json_error(array(
            'message' => 'Failed to delete consignment.',
            'error_details' => $error_message,
            'response_code' => $response_code,
        ));
    }
}


add_action( 'wp_ajax_aramex_delete_consignment', 'aramex_delete_consignment_callback' );