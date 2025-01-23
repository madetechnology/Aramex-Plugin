<?php
defined( 'ABSPATH' ) || exit;

/**
 * Get the API base URL for the selected country.
 */
function aramex_shipping_aunz_get_api_base_url( $origin_country ) {
	return ( $origin_country === 'au' ) 
		? 'https://api.aramexconnect.com.au'
		: 'https://api.aramexconnect.co.nz';
}

/**
 * Fetch access token.
 */
function aramex_shipping_aunz_get_access_token( $api_key, $secret, $origin_country ) {
	error_log( 'Fetching access token...' );

	// Set the correct token endpoint based on country
	$url = ( $origin_country === 'au' ) 
		? 'https://identity.aramexconnect.com.au/connect/token'
		: 'https://identity.aramexconnect.co.nz/connect/token';

	$response = wp_remote_post(
		$url,
		array(
			'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
			'body'    => array(
				'grant_type'    => 'client_credentials',
				'client_id'     => $api_key,
				'client_secret' => $secret,
				'scope'         => '',  // Empty scope as per API documentation
			),
			'timeout' => 45,
		)
	);

	if ( is_wp_error( $response ) ) {
		error_log( 'Error fetching access token: ' . $response->get_error_message() );
		return null;
	}

	$response_body = wp_remote_retrieve_body( $response );
	error_log( 'Access Token API Response: ' . $response_body );

	$data = json_decode( $response_body, true );
	return isset( $data['access_token'] ) ? $data['access_token'] : null;
}