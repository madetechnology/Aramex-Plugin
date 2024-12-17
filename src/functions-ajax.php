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