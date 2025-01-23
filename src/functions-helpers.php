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
 * Add packaging options to checkout fields
 */
function aramex_shipping_aunz_add_checkout_fields( $fields ) {
	$chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
	$chosen_shipping = $chosen_methods[0] ?? '';

	// Only show if Aramex shipping is selected
	if ( strpos( $chosen_shipping, 'my_shipping_method' ) === false ) {
		return $fields;
	}

	// Get shipping method instance
	$shipping_method = new My_Shipping_Method();
	
	// Only show if customer packaging choice is enabled
	if ( $shipping_method->get_option( 'allow_customer_packaging' ) !== 'yes' ) {
		return $fields;
	}

	$origin_country = get_option( 'aramex_shipping_aunz_origin_country', 'nz' );
	$calculator = new Aramex_Package_Calculator( $origin_country );
	$available_satchels = $calculator->get_available_satchel_sizes();

	// Prepare satchel options
	$satchel_options = array();
	foreach ( $available_satchels as $size => $details ) {
		$satchel_options[ $size ] = sprintf(
			'%s (%dcm x %dcm, max %dkg)',
			$size,
			$details['length'],
			$details['width'],
			$details['weight']
		);
	}

	$fields['shipping']['aramex_packaging_type'] = array(
		'type'          => 'select',
		'label'         => __( 'Packaging Type', 'aramex-shipping-aunz' ),
		'required'      => true,
		'class'         => array( 'form-row-wide' ),
		'options'       => array(
			'auto'           => __( 'Auto Select', 'aramex-shipping-aunz' ),
			'single_satchel' => __( 'Single Satchel', 'aramex-shipping-aunz' ),
			'single_box'     => __( 'Single Box', 'aramex-shipping-aunz' ),
			'multiple_boxes' => __( 'Multiple Boxes', 'aramex-shipping-aunz' ),
		),
	);

	$fields['shipping']['aramex_satchel_size'] = array(
		'type'          => 'select',
		'label'         => __( 'Satchel Size', 'aramex-shipping-aunz' ),
		'required'      => false,
		'class'         => array( 'form-row-wide', 'aramex-satchel-size' ),
		'options'       => $satchel_options,
	);

	return $fields;
}
add_filter( 'woocommerce_checkout_fields', 'aramex_shipping_aunz_add_checkout_fields' );

/**
 * Add JavaScript to handle packaging field visibility
 */
function aramex_shipping_aunz_checkout_script() {
	if ( ! is_checkout() ) {
		return;
	}
	?>
	<script type="text/javascript">
	jQuery(function($) {
		function toggleSatchelSize() {
			var packagingType = $('#aramex_packaging_type').val();
			if (packagingType === 'single_satchel') {
				$('.aramex-satchel-size').show();
			} else {
				$('.aramex-satchel-size').hide();
			}
		}

		$(document).on('change', '#aramex_packaging_type', toggleSatchelSize);
		$(document).ready(toggleSatchelSize);
	});
	</script>
	<?php
}
add_action( 'wp_footer', 'aramex_shipping_aunz_checkout_script' );

/**
 * Save packaging choice to order meta
 */
function aramex_shipping_aunz_save_packaging_choice( $order_id ) {
	if ( isset( $_POST['aramex_packaging_type'] ) ) {
		update_post_meta( $order_id, '_aramex_packaging_type', sanitize_text_field( $_POST['aramex_packaging_type'] ) );
	}
	if ( isset( $_POST['aramex_satchel_size'] ) ) {
		update_post_meta( $order_id, '_aramex_satchel_size', sanitize_text_field( $_POST['aramex_satchel_size'] ) );
	}
}
add_action( 'woocommerce_checkout_update_order_meta', 'aramex_shipping_aunz_save_packaging_choice' );

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