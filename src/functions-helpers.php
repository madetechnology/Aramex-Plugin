<?php
defined( 'ABSPATH' ) || exit;

/**
 * Fetch access token.
 */
function aramex_shipping_aunz_get_access_token( $api_key, $secret, $origin_country ) {
	error_log( 'Fetching access token...' );

	$url = 'https://identity.fastway.org/connect/token';
	$scope = ( $origin_country === 'au' ) ? 'fw-fl2-api-au' : 'fw-fl2-api-nz';

	$response = wp_remote_post(
		$url,
		array(
			'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
			'body'    => array(
				'grant_type'    => 'client_credentials',
				'client_id'     => $api_key,
				'client_secret' => $secret,
				'scope'         => $scope,
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
	return $data['access_token'] ?? null;
}