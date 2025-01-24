<?php
defined( 'ABSPATH' ) || exit;

/**
 * AJAX callback to test the connection.
 */
function aramex_shipping_aunz_test_connection_ajax_callback() {
	// Verify nonce
	check_ajax_referer('aramex_test_connection_nonce', 'nonce');

	// Get API credentials
	$api_key = get_option('aramex_shipping_aunz_api_key', '');
	$secret = get_option('aramex_shipping_aunz_api_secret', '');
	$origin_country = get_option('aramex_shipping_aunz_origin_country', 'nz');

	// Log the test attempt
	error_log('Aramex: Testing connection with API key: ' . substr($api_key, 0, 4) . '****');
	
	if (empty($api_key) || empty($secret)) {
		error_log('Aramex: Missing API credentials');
		wp_send_json_error(array('message' => __('API credentials are missing. Please enter your API key and secret.', 'aramex-shipping-aunz')));
		return;
	}

	// Try to get an access token
	$access_token = aramex_shipping_aunz_get_access_token($api_key, $secret, $origin_country);
	
	if ($access_token) {
		error_log('Aramex: Connection test successful - token received');
		wp_send_json_success(array('message' => __('API connection successful.', 'aramex-shipping-aunz')));
	} else {
		error_log('Aramex: Connection test failed - no token received');
		wp_send_json_error(array('message' => __('API connection failed. Please check your credentials.', 'aramex-shipping-aunz')));
	}
}
add_action('wp_ajax_aramex_shipping_aunz_test_connection_ajax', 'aramex_shipping_aunz_test_connection_ajax_callback');

function aramex_create_consignment_callback() {
    // Verify the nonce
    check_ajax_referer('create_consignment_nonce', 'nonce');

    // Get the order ID
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    if (!$order_id) {
        wp_send_json_error(array('message' => 'Invalid order ID.'));
        return;
    }

    // Get the order object
    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error(array('message' => 'Order not found.'));
        return;
    }

    // Get shipping address details
    $to_address = array(
        'ContactName' => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
        'PhoneNumber' => $order->get_billing_phone(),
        'Email' => $order->get_billing_email(),
        'Address' => array(
            'StreetAddress' => $order->get_shipping_address_1(),
            'StreetAddress2' => $order->get_shipping_address_2(),
            'Locality' => $order->get_shipping_city(),
            'PostalCode' => $order->get_shipping_postcode(),
            'Country' => $order->get_shipping_country(),
        ),
    );

    // Get the origin country
    $origin_country = get_option('aramex_shipping_aunz_origin_country', 'nz');

    // Initialize the package calculator
    require_once ARAMEX_PLUGIN_DIR . 'src/class-aramex-package-calculator.php';
    $calculator = new Aramex_Package_Calculator($origin_country);

    // Get order items for package calculation
    $order_items = array();
    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        if ($product) {
            $order_items[] = array(
                'data' => $product,
                'quantity' => $item->get_quantity(),
            );
        }
    }

    // Calculate optimal packaging
    $packages = $calculator->calculate_optimal_packaging($order_items);

    if (empty($packages)) {
        wp_send_json_error(array('message' => 'Could not determine appropriate packaging for the order items.'));
        return;
    }

    // Get store address for From details
    $store_name = get_option('woocommerce_store_name', get_bloginfo('name'));
    $store_phone = get_option('woocommerce_store_phone', '');
    
    // If store phone is not set, try to get admin phone from user meta
    if (empty($store_phone)) {
        $admin_user = get_user_by('email', get_option('admin_email'));
        if ($admin_user) {
            $store_phone = get_user_meta($admin_user->ID, 'billing_phone', true);
        }
    }
    
    // If still no phone, use a default
    if (empty($store_phone)) {
        $store_phone = '0800000000'; // Default phone number
    }

    $from_address = array(
        'ContactName' => !empty($store_name) ? $store_name : 'Store Admin',
        'PhoneNumber' => $store_phone,
        'Email' => get_option('admin_email', ''),
        'Address' => array(
            'StreetAddress' => get_option('woocommerce_store_address', ''),
            'StreetAddress2' => get_option('woocommerce_store_address_2', ''),
            'Locality' => get_option('woocommerce_store_city', ''),
            'PostalCode' => get_option('woocommerce_store_postcode', ''),
            'Country' => substr(get_option('woocommerce_default_country', ''), 0, 2),
        ),
    );

    // Validate required fields before making the API call
    $required_fields = array(
        'From.ContactName' => $from_address['ContactName'],
        'From.PhoneNumber' => $from_address['PhoneNumber'],
        'From.Address.StreetAddress' => $from_address['Address']['StreetAddress'],
        'From.Address.Locality' => $from_address['Address']['Locality'],
        'From.Address.PostalCode' => $from_address['Address']['PostalCode'],
        'From.Address.Country' => $from_address['Address']['Country'],
        'To.ContactName' => $to_address['ContactName'],
        'To.PhoneNumber' => $to_address['PhoneNumber'],
        'To.Address.StreetAddress' => $to_address['Address']['StreetAddress'],
        'To.Address.Locality' => $to_address['Address']['Locality'],
        'To.Address.PostalCode' => $to_address['Address']['PostalCode'],
        'To.Address.Country' => $to_address['Address']['Country'],
    );

    $missing_fields = array();
    foreach ($required_fields as $field => $value) {
        if (empty($value)) {
            $missing_fields[] = $field;
        }
    }

    if (!empty($missing_fields)) {
        error_log('Aramex API Error: Missing required fields - ' . implode(', ', $missing_fields));
        wp_send_json_error(array(
            'message' => 'Missing required fields: ' . implode(', ', $missing_fields),
            'fields' => $missing_fields
        ));
        return;
    }

    // Prepare the request body
    $body = array(
        'From' => $from_address,
        'To' => $to_address,
        'Items' => array_map(function($package) {
            return array(
                'PackageType' => isset($package['PackageType']) ? $package['PackageType'] : 'P',
                'Quantity' => isset($package['Quantity']) ? $package['Quantity'] : 1,
                'WeightDead' => isset($package['WeightDead']) ? $package['WeightDead'] : 1,
                'Length' => isset($package['Length']) ? $package['Length'] : 1,
                'Width' => isset($package['Width']) ? $package['Width'] : 1,
                'Height' => isset($package['Height']) ? $package['Height'] : 1,
            );
        }, $packages),
        'Reference' => $order->get_order_number(),
        'IsSignatureRequired' => true,
        'IsSaturdayDelivery' => false,
        'IsRural' => false,
    );

    // Include the shipping method class
    require_once ARAMEX_PLUGIN_DIR . 'src/class-aramex-shipping-method.php';

    // Get the access token
    $shipping_method = new My_Shipping_Method();
    $access_token = $shipping_method->get_access_token();

    if (!$access_token) {
        wp_send_json_error(array('message' => 'Failed to retrieve access token.'));
        return;
    }

    // Get the API base URL
    $api_base_url = aramex_shipping_aunz_get_api_base_url($origin_country);

    // Log the request for debugging
    error_log('Aramex API Request URL: ' . $api_base_url . '/api/consignments');
    error_log('Aramex API Request Body: ' . json_encode($body, JSON_PRETTY_PRINT));

    // Make the API request
    $response = wp_remote_post($api_base_url . '/api/consignments', array(
        'method' => 'POST',
        'body' => json_encode($body),
        'headers' => array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $access_token,
        ),
        'timeout' => 30,
    ));

    // Handle the API response
    if (is_wp_error($response)) {
        error_log('Aramex API Error: ' . $response->get_error_message());
        wp_send_json_error(array('message' => $response->get_error_message()));
        return;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    $response_data = json_decode($response_body, true);

    // Log the response for debugging
    error_log('Aramex API Response Code: ' . $response_code);
    error_log('Aramex API Response Body: ' . $response_body);

    if ($response_code !== 200 && $response_code !== 201) {
        $error_message = isset($response_data['message']) ? $response_data['message'] : 'Unknown error occurred';
        wp_send_json_error(array(
            'message' => 'API Error: ' . $error_message,
            'code' => $response_code,
            'response' => $response_data
        ));
        return;
    }

    if (isset($response_data['data']) && isset($response_data['data']['conId'])) {
        $con_id = $response_data['data']['conId'];

        // Save the conId and packaging details
        $order->update_meta_data('aramex_conId', $con_id);
        $order->update_meta_data('aramex_packages', $packages);
        $order->save();

        // Add the ConID to the order notes
        $order->add_order_note(sprintf(
            __('Consignment created successfully. ConID: %s. Packages: %d', 'aramex-shipping-aunz'),
            $con_id,
            count($packages)
        ));

        wp_send_json_success(array(
            'message' => 'Consignment created successfully!',
            'data' => $response_data['data'],
        ));
    } else {
        error_log('Aramex API Error: Invalid response format - ' . print_r($response_data, true));
        wp_send_json_error(array(
            'message' => 'Failed to create consignment. Invalid response format.',
            'data' => $response_data,
        ));
    }
}
add_action('wp_ajax_create_consignment_action', 'aramex_create_consignment_callback');

function aramex_delete_consignment_callback() {
    // Verify the nonce
    check_ajax_referer('delete_consignment_nonce', 'nonce');

    // Get the order ID and consignment ID
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    $con_id = isset($_POST['consignment_id']) ? sanitize_text_field($_POST['consignment_id']) : '';

    if (!$order_id || !$con_id) {
        wp_send_json_error(array('message' => 'Invalid order ID or consignment ID.'));
        return;
    }

    // Get the order object
    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error(array('message' => 'Order not found.'));
        return;
    }

    // Include the shipping method class
    require_once ARAMEX_PLUGIN_DIR . 'src/class-aramex-shipping-method.php';

    // Get the access token
    $shipping_method = new My_Shipping_Method();
    $access_token = $shipping_method->get_access_token();

    if (!$access_token) {
        wp_send_json_error(array('message' => 'Failed to retrieve access token.'));
        return;
    }

    // Get the origin country and API base URL
    $origin_country = get_option('aramex_shipping_aunz_origin_country', 'nz');
    $api_base_url = aramex_shipping_aunz_get_api_base_url($origin_country);

    // Prepare the API endpoint and delete reason
    $delete_reason_id = 3; // Reason ID for "Created in Error"
    $api_endpoint = $api_base_url . "/api/consignments/{$con_id}/reason/{$delete_reason_id}";

    // Log request for debugging
    error_log('Delete Consignment Request URL: ' . $api_endpoint);

    // Make the DELETE API request
    $response = wp_remote_request($api_endpoint, array(
        'method' => 'DELETE',
        'headers' => array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $access_token,
        ),
        'timeout' => 30,
    ));

    // Handle the API response
    if (is_wp_error($response)) {
        error_log('Delete Consignment Error: ' . $response->get_error_message());
        wp_send_json_error(array('message' => $response->get_error_message()));
        return;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);

    // Log response for debugging
    error_log('Delete Consignment Response Code: ' . $response_code);
    error_log('Delete Consignment Response Body: ' . $response_body);

    // Check if the response code indicates success
    if ($response_code === 204 || ($response_code >= 200 && $response_code < 300)) {
        // Remove the consignment ID from order meta
        $order->delete_meta_data('aramex_conId');
        $order->save();

        // Add a note to the order
        $order->add_order_note(__('Consignment deleted successfully.', 'aramex-shipping-aunz'));

        wp_send_json_success(array('message' => 'Consignment deleted successfully.'));
    } else {
        $response_data = json_decode($response_body, true);
        $error_message = isset($response_data['message']) ? $response_data['message'] : 'Unexpected error occurred.';
        wp_send_json_error(array(
            'message' => 'Failed to delete consignment: ' . $error_message,
            'response_code' => $response_code,
        ));
    }
}
add_action('wp_ajax_delete_consignment_action', 'aramex_delete_consignment_callback');

function aramex_print_label_callback() {
    // Verify the nonce
    check_ajax_referer('print_label_nonce', 'nonce');

    // Get the order ID and consignment ID
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    $con_id = isset($_POST['con_id']) ? sanitize_text_field($_POST['con_id']) : '';

    if (!$order_id || !$con_id) {
        wp_send_json_error(array('message' => 'Invalid order ID or consignment ID.'));
        return;
    }

    // Get the order object
    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error(array('message' => 'Order not found.'));
        return;
    }

    // Include the shipping method class
    require_once ARAMEX_PLUGIN_DIR . 'src/class-aramex-shipping-method.php';

    // Get the access token
    $shipping_method = new My_Shipping_Method();
    $access_token = $shipping_method->get_access_token();

    if (!$access_token) {
        wp_send_json_error(array('message' => 'Failed to retrieve access token.'));
        return;
    }

    // Get the API base URL
    $origin_country = get_option('aramex_shipping_aunz_origin_country', 'nz');
    $api_base_url = aramex_shipping_aunz_get_api_base_url($origin_country);

    // First, try to get all labels for the consignment
    $labels_url = $api_base_url . "/api/consignments/{$con_id}/labels?pageSize=4x6";

    // Log request for debugging
    error_log('Print Label Request URL: ' . $labels_url);

    $response = wp_remote_get($labels_url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $access_token,
            'Accept' => 'application/pdf',
        ),
        'timeout' => 45,
    ));

    if (is_wp_error($response)) {
        error_log('Print Label Error: ' . $response->get_error_message());
        wp_send_json_error(array('message' => 'Error fetching labels: ' . $response->get_error_message()));
        return;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code !== 200) {
        // If getting all labels fails, try getting individual label
        $label_url = $api_base_url . "/api/consignments/{$con_id}/labels/{$con_id}?pageSize=4x6";
        
        error_log('Trying individual label URL: ' . $label_url);
        
        $response = wp_remote_get($label_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Accept' => 'application/pdf',
            ),
            'timeout' => 45,
        ));

        if (is_wp_error($response)) {
            error_log('Individual Label Error: ' . $response->get_error_message());
            wp_send_json_error(array('message' => 'Error fetching individual label: ' . $response->get_error_message()));
            return;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            error_log('Individual Label Response Code: ' . $response_code);
            wp_send_json_error(array('message' => 'Label not available. The package may have already been scanned.'));
            return;
        }
    }

    // Before getting PDF content, get the label number
    $label_info_url = $api_base_url . "/api/consignments/{$con_id}";
    $label_info_response = wp_remote_get($label_info_url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $access_token,
            'Accept' => 'application/json',
        ),
        'timeout' => 30,
    ));

    if (!is_wp_error($label_info_response) && wp_remote_retrieve_response_code($label_info_response) === 200) {
        $label_info = json_decode(wp_remote_retrieve_body($label_info_response), true);
        if (isset($label_info['data']['labelNo'])) {
            $order->update_meta_data('aramex_label_number', $label_info['data']['labelNo']);
            $order->save();
        }
    }

    // Get the PDF content
    $pdf_content = wp_remote_retrieve_body($response);

    // Generate a unique filename
    $upload_dir = wp_upload_dir();
    $filename = 'aramex-label-' . $con_id . '-' . time() . '.pdf';
    $filepath = $upload_dir['path'] . '/' . $filename;
    $fileurl = $upload_dir['url'] . '/' . $filename;

    // Save the PDF file
    if (file_put_contents($filepath, $pdf_content) === false) {
        error_log('Error saving PDF file to: ' . $filepath);
        wp_send_json_error(array('message' => 'Error saving label PDF file.'));
        return;
    }

    // Add note to order
    $order->add_order_note(sprintf(
        __('Aramex shipping label generated for consignment %s. <a href="%s" target="_blank">Download Label</a>', 'aramex-shipping-aunz'),
        $con_id,
        esc_url($fileurl)
    ));

    wp_send_json_success(array(
        'message' => 'Label generated successfully.',
        'pdf_url' => $fileurl
    ));
}
add_action('wp_ajax_print_label_action', 'aramex_print_label_callback');

function aramex_track_shipment_callback() {
    // Verify the nonce
    check_ajax_referer('track_shipment_nonce', 'nonce');

    // Get the label number
    $label_number = isset($_POST['label_number']) ? sanitize_text_field($_POST['label_number']) : '';

    if (!$label_number) {
        wp_send_json_error(array('message' => 'Invalid tracking number.'));
        return;
    }

    // Simulate tracking for dummy tracking number
    if ($label_number === 'DU12345678910') {
        $current_time = current_time('timestamp');
        $dummy_events = array(
            array(
                'date' => date('Y-m-d H:i:s', $current_time - (3600 * 24)), // 24 hours ago
                'status' => 'Order Created',
                'description' => 'Shipping label created',
                'scan_description' => 'Electronic shipping details received',
                'location' => 'Online'
            ),
            array(
                'date' => date('Y-m-d H:i:s', $current_time - (3600 * 20)), // 20 hours ago
                'status' => 'Picked Up',
                'description' => 'Shipment picked up by courier',
                'scan_description' => 'Picked up by courier',
                'location' => 'Auckland Depot'
            ),
            array(
                'date' => date('Y-m-d H:i:s', $current_time - (3600 * 16)), // 16 hours ago
                'status' => 'In Transit',
                'description' => 'Arrived at sorting facility',
                'scan_description' => 'Package received at facility',
                'location' => 'Auckland Distribution Center'
            ),
            array(
                'date' => date('Y-m-d H:i:s', $current_time - (3600 * 12)), // 12 hours ago
                'status' => 'In Transit',
                'description' => 'Departed sorting facility',
                'scan_description' => 'Package has left the facility',
                'location' => 'Auckland Distribution Center'
            ),
            array(
                'date' => date('Y-m-d H:i:s', $current_time - (3600 * 8)), // 8 hours ago
                'status' => 'In Transit',
                'description' => 'Arrived at destination facility',
                'scan_description' => 'Package arrived at destination facility',
                'location' => 'Wellington Distribution Center'
            ),
            array(
                'date' => date('Y-m-d H:i:s', $current_time - (3600 * 4)), // 4 hours ago
                'status' => 'Out for Delivery',
                'description' => 'With delivery courier',
                'scan_description' => 'Out for delivery',
                'location' => 'Wellington Local Courier'
            ),
            array(
                'date' => date('Y-m-d H:i:s', $current_time - (3600 * 1)), // 1 hour ago
                'status' => 'Delivery Attempted',
                'description' => 'First delivery attempt',
                'scan_description' => 'No one available to receive package',
                'location' => 'Wellington'
            ),
        );

        wp_send_json_success(array(
            'message' => 'Tracking information retrieved successfully.',
            'tracking_events' => $dummy_events
        ));
        return;
    }

    // Rest of the original tracking code...
    require_once ARAMEX_PLUGIN_DIR . 'src/class-aramex-shipping-method.php';

    // Get the access token
    $shipping_method = new My_Shipping_Method();
    $access_token = $shipping_method->get_access_token();

    if (!$access_token) {
        wp_send_json_error(array('message' => 'Failed to retrieve access token.'));
        return;
    }

    // Get the API base URL
    $origin_country = get_option('aramex_shipping_aunz_origin_country', 'nz');
    $api_base_url = aramex_shipping_aunz_get_api_base_url($origin_country);

    // Prepare tracking URL
    $tracking_url = $api_base_url . '/api/track/label/' . urlencode($label_number);
    
    // Log the request URL and details
    error_log('Aramex Tracking Request URL: ' . $tracking_url);
    error_log('Aramex Tracking Label Number: ' . $label_number);

    // Make the API request to track the shipment
    $response = wp_remote_get(
        $tracking_url,
        array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ),
            'timeout' => 30,
        )
    );

    if (is_wp_error($response)) {
        error_log('Aramex Tracking Error: ' . $response->get_error_message());
        wp_send_json_error(array('message' => 'Error tracking shipment: ' . $response->get_error_message()));
        return;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    
    // Log the complete response
    error_log('Aramex Tracking Response Code: ' . $response_code);
    error_log('Aramex Tracking Response Body: ' . $response_body);

    $response_data = json_decode($response_body, true);

    if ($response_code !== 200) {
        $error_message = isset($response_data['message']) ? $response_data['message'] : 'Failed to retrieve tracking information.';
        error_log('Aramex Tracking Error Message: ' . $error_message);
        wp_send_json_error(array('message' => $error_message));
        return;
    }

    // Format tracking events
    $tracking_events = array();
    
    // Check both possible response formats
    if (isset($response_data['data']) && is_array($response_data['data'])) {
        // Direct events array format
        $events = $response_data['data'];
        foreach ($events as $event) {
            $tracking_events[] = array(
                'date' => isset($event['scannedDateTime']) ? date('Y-m-d H:i:s', strtotime($event['scannedDateTime'])) : '',
                'status' => isset($event['status']) ? $event['status'] : '',
                'description' => isset($event['description']) ? $event['description'] : '',
                'scan_description' => isset($event['scanTypeDescription']) ? $event['scanTypeDescription'] : '',
                'location' => isset($event['franchiseName']) ? $event['franchiseName'] : '',
            );
        }
    } elseif (isset($response_data['data']['events']) && is_array($response_data['data']['events'])) {
        // Nested events array format
        foreach ($response_data['data']['events'] as $event) {
            $tracking_events[] = array(
                'date' => isset($event['timestamp']) ? date('Y-m-d H:i:s', strtotime($event['timestamp'])) : '',
                'status' => isset($event['status']) ? $event['status'] : '',
                'description' => isset($event['description']) ? $event['description'] : '',
                'scan_description' => isset($event['scanDescription']) ? $event['scanDescription'] : '',
                'location' => isset($event['location']) ? $event['location'] : '',
            );
        }
    }

    // Sort events by date (newest first)
    usort($tracking_events, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });

    if (empty($tracking_events)) {
        error_log('Aramex Tracking: No events found in response');
        wp_send_json_error(array('message' => 'No tracking events found for this shipment.'));
        return;
    }

    wp_send_json_success(array(
        'message' => 'Tracking information retrieved successfully.',
        'tracking_events' => $tracking_events
    ));
}
add_action('wp_ajax_track_shipment_action', 'aramex_track_shipment_callback');
add_action('wp_ajax_nopriv_track_shipment_action', 'aramex_track_shipment_callback'); // Allow non-logged in users to track