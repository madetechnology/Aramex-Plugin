<?php
/**
 * Tracking related functions for Aramex Shipping AUNZ
 *
 * @package aramex_shipping_aunz
 */

defined('ABSPATH') || exit;

/**
 * Get tracking information for a label number
 *
 * @param string $label_number The tracking label number
 * @return array Tracking information with success status and events/error message
 */
function aramex_get_tracking_info($label_number) {
    if (empty($label_number)) {
        return array(
            'success' => false,
            'message' => 'No tracking number provided'
        );
    }

    // Get shipping method instance to access settings
    require_once ARAMEX_PLUGIN_DIR . 'src/class-aramex-shipping-method.php';
    $shipping_method = new My_Shipping_Method();
    $access_token = $shipping_method->get_access_token();

    if (!$access_token) {
        return array(
            'success' => false,
            'message' => 'Failed to authenticate with Aramex API'
        );
    }

    $origin_country = get_option('aramex_shipping_aunz_origin_country', 'nz');
    $api_base_url = aramex_shipping_aunz_get_api_base_url($origin_country);
    $tracking_url = $api_base_url . '/api/track/label/' . urlencode($label_number);

    $response = wp_remote_get(
        $tracking_url,
        array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Accept' => 'application/json',
            ),
            'timeout' => 30,
        )
    );

    if (is_wp_error($response)) {
        return array(
            'success' => false,
            'message' => 'Error connecting to Aramex API: ' . $response->get_error_message()
        );
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    $tracking_data = json_decode($response_body, true);

    if ($response_code !== 200) {
        return array(
            'success' => false,
            'message' => 'Failed to retrieve tracking information'
        );
    }

    $events = array();
    if (isset($tracking_data['data'])) {
        if (isset($tracking_data['data']['events'])) {
            $events = $tracking_data['data']['events'];
        } elseif (is_array($tracking_data['data'])) {
            $events = $tracking_data['data'];
        }
    }

    return array(
        'success' => true,
        'events' => $events
    );
}

/**
 * Get the current status of a shipment
 *
 * @param string $label_number The Aramex label/tracking number
 * @return string The current status or empty string if not found
 */
function aramex_get_shipment_status($label_number) {
    $tracking_info = aramex_get_tracking_info($label_number);
    
    if ($tracking_info['success'] && !empty($tracking_info['events'])) {
        // Return the status of the most recent event
        return $tracking_info['events'][0]['status'];
    }
    
    return '';
} 