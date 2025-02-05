<?php
defined( 'ABSPATH' ) || exit;

/**
 * Debug logging wrapper (if not defined).
 */
if ( ! function_exists( 'aramex_debug_log' ) ) {
	function aramex_debug_log( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}

/**
 * AJAX callback to test the connection.
 */
function aramex_shipping_aunz_test_connection_ajax_callback() {
	check_ajax_referer( 'aramex_test_connection_nonce', 'nonce' );

	$api_key        = get_option( 'aramex_shipping_aunz_api_key', '' );
	$secret         = get_option( 'aramex_shipping_aunz_api_secret', '' );
	$origin_country = get_option( 'aramex_shipping_aunz_origin_country', 'nz' );

	aramex_debug_log( 'Aramex: Testing connection with API key: ' . substr( $api_key, 0, 4 ) . '****' );

	if ( empty( $api_key ) || empty( $secret ) ) {
		aramex_debug_log( 'Aramex: Missing API credentials' );
		wp_send_json_error(
			array(
				'message' => __( 'API credentials are missing. Please enter your API key and secret.', 'Aramex-Plugin' ),
			)
		);
	}

	$access_token = aramex_shipping_aunz_get_access_token( $api_key, $secret, $origin_country );
	
	if ( $access_token ) {
		aramex_debug_log( 'Aramex: Connection test successful - token received' );
		wp_send_json_success(
			array(
				'message' => __( 'API connection successful.', 'Aramex-Plugin' ),
			)
		);
	} else {
		aramex_debug_log( 'Aramex: Connection test failed - no token received' );
		wp_send_json_error(
			array(
				'message' => __( 'API connection failed. Please check your credentials.', 'Aramex-Plugin' ),
			)
		);
	}
}
add_action( 'wp_ajax_aramex_shipping_aunz_test_connection_ajax', 'aramex_shipping_aunz_test_connection_ajax_callback' );

function aramex_create_consignment_callback() {
	check_ajax_referer( 'create_consignment_nonce', 'nonce' );

	$order_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : 0;
	if ( ! $order_id ) {
		wp_send_json_error( array( 'message' => 'Invalid order ID.' ) );
	}

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		wp_send_json_error( array( 'message' => 'Order not found.' ) );
	}

	$to_address = array(
		'ContactName' => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
		'PhoneNumber' => $order->get_billing_phone(),
		'Email'       => $order->get_billing_email(),
		'Address'     => array(
			'StreetAddress'  => $order->get_shipping_address_1(),
			'StreetAddress2' => $order->get_shipping_address_2(),
			'Locality'       => $order->get_shipping_city(),
			'PostalCode'     => $order->get_shipping_postcode(),
			'Country'        => $order->get_shipping_country(),
		),
	);

	$origin_country = get_option( 'aramex_shipping_aunz_origin_country', 'nz' );

	require_once ARAMEX_PLUGIN_DIR . 'src/class-aramex-package-calculator.php';
	$calculator = new Aramex_Package_Calculator( $origin_country );

	$order_items = array();
	foreach ( $order->get_items() as $item ) {
		$product = $item->get_product();
		if ( $product ) {
			$order_items[] = array(
				'data'     => $product,
				'quantity' => $item->get_quantity(),
			);
		}
	}

	$packages = $calculator->calculate_optimal_packaging( $order_items );
	if ( empty( $packages ) ) {
		wp_send_json_error( array( 'message' => 'Could not determine appropriate packaging for the order items.' ) );
	}

	$store_name  = get_option( 'woocommerce_store_name', get_bloginfo( 'name' ) );
	$store_phone = get_option( 'woocommerce_store_phone', '' );

	if ( empty( $store_phone ) ) {
		$admin_user = get_user_by( 'email', get_option( 'admin_email' ) );
		if ( $admin_user ) {
			$store_phone = get_user_meta( $admin_user->ID, 'billing_phone', true );
		}
	}
	if ( empty( $store_phone ) ) {
		$store_phone = '0800000000'; // fallback phone
	}

	$from_address = array(
		'ContactName' => ! empty( $store_name ) ? $store_name : 'Store Admin',
		'PhoneNumber' => $store_phone,
		'Email'       => get_option( 'admin_email', '' ),
		'Address'     => array(
			'StreetAddress'  => get_option( 'woocommerce_store_address', '' ),
			'StreetAddress2' => get_option( 'woocommerce_store_address_2', '' ),
			'Locality'       => get_option( 'woocommerce_store_city', '' ),
			'PostalCode'     => get_option( 'woocommerce_store_postcode', '' ),
			'Country'        => substr( get_option( 'woocommerce_default_country', '' ), 0, 2 ),
		),
	);

	$required_fields = array(
		'From.ContactName'              => $from_address['ContactName'],
		'From.PhoneNumber'              => $from_address['PhoneNumber'],
		'From.Address.StreetAddress'    => $from_address['Address']['StreetAddress'],
		'From.Address.Locality'         => $from_address['Address']['Locality'],
		'From.Address.PostalCode'       => $from_address['Address']['PostalCode'],
		'From.Address.Country'          => $from_address['Address']['Country'],
		'To.ContactName'                => $to_address['ContactName'],
		'To.PhoneNumber'                => $to_address['PhoneNumber'],
		'To.Address.StreetAddress'      => $to_address['Address']['StreetAddress'],
		'To.Address.Locality'           => $to_address['Address']['Locality'],
		'To.Address.PostalCode'         => $to_address['Address']['PostalCode'],
		'To.Address.Country'            => $to_address['Address']['Country'],
	);

	$missing_fields = array();
	foreach ( $required_fields as $field => $value ) {
		if ( empty( $value ) ) {
			$missing_fields[] = $field;
		}
	}
	if ( ! empty( $missing_fields ) ) {
		aramex_debug_log( 'Aramex API Error: Missing required fields - ' . implode( ', ', $missing_fields ) );
		wp_send_json_error(
			array(
				'message' => 'Missing required fields: ' . implode( ', ', $missing_fields ),
				'fields'  => $missing_fields,
			)
		);
	}

	$body = array(
		'From'               => $from_address,
		'To'                 => $to_address,
		'Items'              => array_map(
			function( $package ) {
				return array(
					'PackageType' => isset( $package['PackageType'] ) ? $package['PackageType'] : 'P',
					'Quantity'    => isset( $package['Quantity'] ) ? $package['Quantity'] : 1,
					'WeightDead'  => isset( $package['WeightDead'] ) ? $package['WeightDead'] : 1,
					'Length'      => isset( $package['Length'] ) ? $package['Length'] : 1,
					'Width'       => isset( $package['Width'] ) ? $package['Width'] : 1,
					'Height'      => isset( $package['Height'] ) ? $package['Height'] : 1,
				);
			},
			$packages
		),
		'Reference'          => $order->get_order_number(),
		'IsSignatureRequired'=> true,
		'IsSaturdayDelivery' => false,
		'IsRural'            => false,
	);

	require_once ARAMEX_PLUGIN_DIR . 'src/class-aramex-shipping-method.php';
	$shipping_method = new My_Shipping_Method();
	$access_token   = $shipping_method->get_access_token();

	if ( ! $access_token ) {
		wp_send_json_error( array( 'message' => 'Failed to retrieve access token.' ) );
	}

	$api_base_url = aramex_shipping_aunz_get_api_base_url( $origin_country );

	aramex_debug_log( 'Aramex API Request URL: ' . $api_base_url . '/api/consignments' );
	aramex_debug_log( 'Aramex API Request Body: ' . wp_json_encode( $body, JSON_PRETTY_PRINT ) );

	$response = wp_remote_post(
		$api_base_url . '/api/consignments',
		array(
			'method'  => 'POST',
			'body'    => wp_json_encode( $body ),
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $access_token,
			),
			'timeout' => 30,
		)
	);

	if ( is_wp_error( $response ) ) {
		aramex_debug_log( 'Aramex API Error: ' . $response->get_error_message() );
		wp_send_json_error( array( 'message' => $response->get_error_message() ) );
	}

	$response_code = wp_remote_retrieve_response_code( $response );
	$response_body = wp_remote_retrieve_body( $response );
	$response_data = json_decode( $response_body, true );

	aramex_debug_log( 'Aramex API Response Code: ' . $response_code );
	aramex_debug_log( 'Aramex API Response Body: ' . $response_body );

	if ( 200 !== $response_code && 201 !== $response_code ) {
		$error_message = isset( $response_data['message'] ) ? $response_data['message'] : 'Unknown error occurred';
		wp_send_json_error(
			array(
				'message'  => 'API Error: ' . $error_message,
				'code'     => $response_code,
				'response' => $response_data,
			)
		);
	}

	if ( isset( $response_data['data'] ) && isset( $response_data['data']['conId'] ) ) {
		$con_id = $response_data['data']['conId'];

		$order->update_meta_data( 'aramex_conId', $con_id );
		$order->update_meta_data( 'aramex_packages', $packages );
		$order->save();

		/* translators: 1: Consignment ID, 2: Number of packages */
		$order->add_order_note(
			/* translators: 1: Consignment ID, 2: Number of packages */
			sprintf(
							/* translators: 1: Consignment ID, 2: Number of packages */
				__( 'Consignment created successfully. ConID: %1$s. Packages: %2$d', 'Aramex-Plugin' ),
				$con_id,
				count( $packages )
			)
		);

		wp_send_json_success(
			array(
				'message' => 'Consignment created successfully!',
				'data'    => $response_data['data'],
			)
		);
	} else {
		aramex_debug_log( 'Aramex API Error: Invalid response format - ' . wp_json_encode( $response_data, JSON_PRETTY_PRINT ) );
		wp_send_json_error(
			array(
				'message' => 'Failed to create consignment. Invalid response format.',
				'data'    => $response_data,
			)
		);
	}
}
add_action( 'wp_ajax_create_consignment_action', 'aramex_create_consignment_callback' );

function aramex_delete_consignment_callback() {
	check_ajax_referer( 'delete_consignment_nonce', 'nonce' );

	if ( ! isset( $_POST['consignment_id'] ) ) {
		wp_send_json_error( array( 'message' => 'Consignment ID is required.' ) );
	}

	$consignment_id = sanitize_text_field( wp_unslash( $_POST['consignment_id'] ) );
	$order_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : 0;

	if ( ! $order_id ) {
		wp_send_json_error( array( 'message' => 'Invalid order ID.' ) );
	}

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		wp_send_json_error( array( 'message' => 'Order not found.' ) );
	}

	require_once ARAMEX_PLUGIN_DIR . 'src/class-aramex-shipping-method.php';
	$shipping_method = new My_Shipping_Method();
	$access_token = $shipping_method->get_access_token();

	if ( ! $access_token ) {
		wp_send_json_error( array( 'message' => 'Failed to retrieve access token.' ) );
	}

	$origin_country = get_option( 'aramex_shipping_aunz_origin_country', 'nz' );
	$api_base_url = aramex_shipping_aunz_get_api_base_url( $origin_country );

	aramex_debug_log( 'Aramex API Delete Request URL: ' . $api_base_url . '/api/consignments/' . $consignment_id );

	$response = wp_remote_request(
		$api_endpoint,
		array(
			'method'  => 'DELETE',
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $access_token,
			),
			'timeout' => 30,
		)
	);

	if ( is_wp_error( $response ) ) {
		aramex_debug_log( 'Delete Consignment Error: ' . $response->get_error_message() );
		wp_send_json_error( array( 'message' => $response->get_error_message() ) );
	}

	$response_code = wp_remote_retrieve_response_code( $response );
	$response_body = wp_remote_retrieve_body( $response );

	aramex_debug_log( 'Delete Consignment Response Code: ' . $response_code );
	aramex_debug_log( 'Delete Consignment Response Body: ' . $response_body );

	if ( 204 === $response_code || ( $response_code >= 200 && $response_code < 300 ) ) {
		$order->delete_meta_data( 'aramex_conId' );
		$order->save();

		/* translators: %s: Consignment ID */
		$order->add_order_note(
			__( 'Consignment deleted successfully.', 'Aramex-Plugin' )
		);

		wp_send_json_success( array( 'message' => 'Consignment deleted successfully.' ) );
	} else {
		$response_data = json_decode( $response_body, true );
		$error_message = isset( $response_data['message'] ) ? $response_data['message'] : 'Unexpected error occurred.';
		wp_send_json_error(
			array(
				'message'       => 'Failed to delete consignment: ' . $error_message,
				'response_code' => $response_code,
			)
		);
	}
}
add_action( 'wp_ajax_delete_consignment_action', 'aramex_delete_consignment_callback' );

function aramex_print_label_callback() {
	check_ajax_referer( 'print_label_nonce', 'nonce' );

	$order_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : 0;
	$con_id   = isset( $_POST['con_id'] ) ? sanitize_text_field( wp_unslash( $_POST['con_id'] ) ) : '';

	if ( ! $order_id || ! $con_id ) {
		wp_send_json_error( array( 'message' => 'Invalid order ID or consignment ID.' ) );
	}

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		wp_send_json_error( array( 'message' => 'Order not found.' ) );
	}

	require_once ARAMEX_PLUGIN_DIR . 'src/class-aramex-shipping-method.php';
	$shipping_method = new My_Shipping_Method();
	$access_token   = $shipping_method->get_access_token();

	if ( ! $access_token ) {
		wp_send_json_error( array( 'message' => 'Failed to retrieve access token.' ) );
	}

	$origin_country = get_option( 'aramex_shipping_aunz_origin_country', 'nz' );
	$api_base_url   = aramex_shipping_aunz_get_api_base_url( $origin_country );

	$labels_url = $api_base_url . "/api/consignments/{$con_id}/labels?pageSize=4x6";
	aramex_debug_log( 'Print Label Request URL: ' . $labels_url );

	$response = wp_remote_get(
		$labels_url,
		array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
				'Accept'        => 'application/pdf',
			),
			'timeout' => 45,
		)
	);

	if ( is_wp_error( $response ) ) {
		aramex_debug_log( 'Print Label Error: ' . $response->get_error_message() );
		wp_send_json_error( array( 'message' => 'Error fetching labels: ' . $response->get_error_message() ) );
	}

	$response_code = wp_remote_retrieve_response_code( $response );
	if ( 200 !== $response_code ) {
		// Try individual label.
		$label_url = $api_base_url . "/api/consignments/{$con_id}/labels/{$con_id}?pageSize=4x6";
		aramex_debug_log( 'Trying individual label URL: ' . $label_url );

		$response = wp_remote_get(
			$label_url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Accept'        => 'application/pdf',
				),
				'timeout' => 45,
			)
		);

		if ( is_wp_error( $response ) ) {
			aramex_debug_log( 'Individual Label Error: ' . $response->get_error_message() );
			wp_send_json_error( array( 'message' => 'Error fetching individual label: ' . $response->get_error_message() ) );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			aramex_debug_log( 'Individual Label Response Code: ' . $response_code );
			wp_send_json_error( array( 'message' => 'Label not available. The package may have already been scanned.' ) );
		}
	}

	// Fetch label info to get the label number.
	$label_info_url      = $api_base_url . "/api/consignments/{$con_id}";
	$label_info_response = wp_remote_get(
		$label_info_url,
		array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
				'Accept'        => 'application/json',
			),
			'timeout' => 30,
		)
	);

	if ( ! is_wp_error( $label_info_response )
		&& 200 === wp_remote_retrieve_response_code( $label_info_response ) ) {
		$label_info = json_decode( wp_remote_retrieve_body( $label_info_response ), true );
		if ( isset( $label_info['data']['labelNo'] ) ) {
			$order->update_meta_data( 'aramex_label_number', $label_info['data']['labelNo'] );
			$order->save();
		}
	}

	$pdf_content = wp_remote_retrieve_body( $response );

	$upload_dir = wp_upload_dir();
	$filename   = 'aramex-label-' . $con_id . '-' . time() . '.pdf';
	$filepath   = $upload_dir['path'] . '/' . $filename;
	$fileurl    = $upload_dir['url'] . '/' . $filename;

	global $wp_filesystem;
	if ( empty( $wp_filesystem ) ) {
		require_once ABSPATH . '/wp-admin/includes/file.php';
		WP_Filesystem();
	}
	if ( ! $wp_filesystem->put_contents( $filepath, $pdf_content, FS_CHMOD_FILE ) ) {
		aramex_debug_log( 'Error saving PDF file to: ' . $filepath );
		wp_send_json_error( array( 'message' => 'Error saving label PDF file.' ) );
	}

	/* translators: 1: Consignment ID, 2: Label download URL */
	$order->add_order_note(
			/* translators: 1: Consignment ID, 2: Label download URL */
		sprintf(
						/* translators: 1: Consignment ID, 2: Label download URL */
			__( 'Aramex shipping label generated for consignment %1$s. <a href="%2$s" target="_blank">Download Label</a>', 'Aramex-Plugin' ),
			$con_id,
			esc_url( $fileurl )
		)
	);

	wp_send_json_success(
		array(
			'message' => 'Label generated successfully.',
			'pdf_url' => $fileurl,
		)
	);
}
add_action( 'wp_ajax_print_label_action', 'aramex_print_label_callback' );

/**
 * Track shipment AJAX.
 */
function aramex_track_shipment_callback() {
	check_ajax_referer( 'track_shipment_nonce', 'nonce' );

	$label_number = isset( $_POST['label_number'] ) ? sanitize_text_field( wp_unslash( $_POST['label_number'] ) ) : '';

	if ( ! $label_number ) {
		wp_send_json_error( array( 'message' => 'Invalid tracking number.' ) );
	}

	// Dummy simulation case.
	if ( 'DU12345678910' === $label_number ) {
		$current_time  = current_time( 'timestamp' );
		$dummy_events  = array(
			array(
				'date'            => gmdate( 'Y-m-d H:i:s', $current_time - ( 3600 * 24 ) ),
				'status'          => 'Order Created',
				'description'     => 'Shipping label created',
				'scan_description'=> 'Electronic shipping details received',
				'location'        => 'Online',
			),
			array(
				'date'            => gmdate( 'Y-m-d H:i:s', $current_time - ( 3600 * 20 ) ),
				'status'          => 'Picked Up',
				'description'     => 'Shipment picked up by courier',
				'scan_description'=> 'Picked up by courier',
				'location'        => 'Auckland Depot',
			),
			array(
				'date'            => gmdate( 'Y-m-d H:i:s', $current_time - ( 3600 * 16 ) ),
				'status'          => 'In Transit',
				'description'     => 'Arrived at sorting facility',
				'scan_description'=> 'Package received at facility',
				'location'        => 'Auckland Distribution Center',
			),
			array(
				'date'            => gmdate( 'Y-m-d H:i:s', $current_time - ( 3600 * 12 ) ),
				'status'          => 'In Transit',
				'description'     => 'Departed sorting facility',
				'scan_description'=> 'Package has left the facility',
				'location'        => 'Auckland Distribution Center',
			),
			array(
				'date'            => gmdate( 'Y-m-d H:i:s', $current_time - ( 3600 * 8 ) ),
				'status'          => 'In Transit',
				'description'     => 'Arrived at destination facility',
				'scan_description'=> 'Package arrived at destination facility',
				'location'        => 'Wellington Distribution Center',
			),
			array(
				'date'            => gmdate( 'Y-m-d H:i:s', $current_time - ( 3600 * 4 ) ),
				'status'          => 'Out for Delivery',
				'description'     => 'With delivery courier',
				'scan_description'=> 'Out for delivery',
				'location'        => 'Wellington Local Courier',
			),
			array(
				'date'            => gmdate( 'Y-m-d H:i:s', $current_time - ( 3600 * 1 ) ),
				'status'          => 'Delivery Attempted',
				'description'     => 'First delivery attempt',
				'scan_description'=> 'No one available to receive package',
				'location'        => 'Wellington',
			),
		);

		wp_send_json_success(
			array(
				'message'        => 'Tracking information retrieved successfully.',
				'tracking_events'=> $dummy_events,
			)
		);
	}

	require_once ARAMEX_PLUGIN_DIR . 'src/class-aramex-shipping-method.php';
	$shipping_method = new My_Shipping_Method();
	$access_token   = $shipping_method->get_access_token();

	if ( ! $access_token ) {
		wp_send_json_error( array( 'message' => 'Failed to retrieve access token.' ) );
	}

	$origin_country = get_option( 'aramex_shipping_aunz_origin_country', 'nz' );
	$api_base_url   = aramex_shipping_aunz_get_api_base_url( $origin_country );

	$tracking_url = $api_base_url . '/api/track/label/' . urlencode( $label_number );
	aramex_debug_log( 'Aramex Tracking Request URL: ' . $tracking_url );
	aramex_debug_log( 'Aramex Tracking Label Number: ' . $label_number );

	$response = wp_remote_get(
		$tracking_url,
		array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
			),
			'timeout' => 30,
		)
	);

	if ( is_wp_error( $response ) ) {
		aramex_debug_log( 'Aramex Tracking Error: ' . $response->get_error_message() );
		wp_send_json_error( array( 'message' => 'Error tracking shipment: ' . $response->get_error_message() ) );
	}

	$response_code = wp_remote_retrieve_response_code( $response );
	$response_body = wp_remote_retrieve_body( $response );
	aramex_debug_log( 'Aramex Tracking Response Code: ' . $response_code );
	aramex_debug_log( 'Aramex Tracking Response Body: ' . $response_body );

	$response_data = json_decode( $response_body, true );

	if ( 200 !== $response_code ) {
		$error_message = isset( $response_data['message'] ) ? $response_data['message'] : 'Failed to retrieve tracking information.';
		aramex_debug_log( 'Aramex Tracking Error Message: ' . $error_message );
		wp_send_json_error( array( 'message' => $error_message ) );
	}

	$tracking_events = array();

	// Format events for different possible data structures.
	if ( isset( $response_data['data'] ) && is_array( $response_data['data'] ) && ! isset( $response_data['data']['events'] ) ) {
		// Direct array of events in 'data'.
		$events = $response_data['data'];
		foreach ( $events as $event ) {
			$tracking_events[] = array(
				'date'            => isset( $event['scannedDateTime'] ) ? gmdate( 'Y-m-d H:i:s', strtotime( $event['scannedDateTime'] ) ) : '',
				'status'          => isset( $event['status'] ) ? $event['status'] : '',
				'description'     => isset( $event['description'] ) ? $event['description'] : '',
				'scan_description'=> isset( $event['scanTypeDescription'] ) ? $event['scanTypeDescription'] : '',
				'location'        => isset( $event['franchiseName'] ) ? $event['franchiseName'] : '',
			);
		}
	} elseif ( isset( $response_data['data']['events'] ) && is_array( $response_data['data']['events'] ) ) {
		// Nested 'events' array.
		foreach ( $response_data['data']['events'] as $event ) {
			$tracking_events[] = array(
				'date'            => isset( $event['timestamp'] ) ? gmdate( 'Y-m-d H:i:s', strtotime( $event['timestamp'] ) ) : '',
				'status'          => isset( $event['status'] ) ? $event['status'] : '',
				'description'     => isset( $event['description'] ) ? $event['description'] : '',
				'scan_description'=> isset( $event['scanDescription'] ) ? $event['scanDescription'] : '',
				'location'        => isset( $event['location'] ) ? $event['location'] : '',
			);
		}
	}

	// Sort events newest first.
	usort(
		$tracking_events,
		function( $a, $b ) {
			return strtotime( $b['date'] ) - strtotime( $a['date'] );
		}
	);

	if ( empty( $tracking_events ) ) {
		aramex_debug_log( 'Aramex Tracking: No events found in response' );
		wp_send_json_error( array( 'message' => 'No tracking events found for this shipment.' ) );
	}

	wp_send_json_success(
		array(
			'message'        => 'Tracking information retrieved successfully.',
			'tracking_events'=> $tracking_events,
		)
	);
}
add_action( 'wp_ajax_track_shipment_action', 'aramex_track_shipment_callback' );
add_action( 'wp_ajax_nopriv_track_shipment_action', 'aramex_track_shipment_callback' );