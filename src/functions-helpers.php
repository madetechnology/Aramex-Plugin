<?php
defined( 'ABSPATH' ) || exit;

/**
 * Get the API base URL for the selected country.
 */
function aramex_shipping_aunz_get_api_base_url( $origin_country ) {
	aramex_debug_log( 'Getting API URL for origin country: ' . $origin_country );
	
	// Ensure we have a valid country code
	$origin_country = strtolower(trim($origin_country));
	
	// Strict comparison for country code
	if ($origin_country === 'au') {
		$api_url = 'https://api.aramexconnect.com.au';
	} else {
		$api_url = 'https://api.aramexconnect.co.nz';
	}
	
	aramex_debug_log( 'Using API URL: ' . $api_url );
	return $api_url;
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
	$calculator = new Aramex_Package_Calculator( $origin_country, 'product_dimensions', $shipping_method );
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
		'label'         => __( 'Packaging Type', 'Aramex-Plugin' ),
		'required'      => true,
		'class'         => array( 'form-row-wide' ),
		'options'       => array(
			'auto'           => __( 'Auto Select', 'Aramex-Plugin' ),
			'single_satchel' => __( 'Single Satchel', 'Aramex-Plugin' ),
			'single_box'     => __( 'Single Box', 'Aramex-Plugin' ),
			'multiple_boxes' => __( 'Multiple Boxes', 'Aramex-Plugin' ),
		),
	);

	$fields['shipping']['aramex_satchel_size'] = array(
		'type'          => 'select',
		'label'         => __( 'Satchel Size', 'Aramex-Plugin' ),
		'required'      => false,
		'class'         => array( 'form-row-wide', 'aramex-satchel-size' ),
		'options'       => $satchel_options,
	);

	return $fields;
}
add_filter( 'woocommerce_checkout_fields', 'aramex_shipping_aunz_add_checkout_fields' );

/**
 * Add nonce field to checkout form
 */
function aramex_shipping_aunz_add_checkout_nonce() {
    wp_nonce_field( 'aramex_packaging_choice', 'aramex_packaging_nonce' );
}
add_action( 'woocommerce_after_checkout_billing_form', 'aramex_shipping_aunz_add_checkout_nonce' );

/**
 * Add JavaScript to handle packaging field visibility and trigger shipping update
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
				$('#aramex_satchel_size').prop('required', true);
			} else {
				$('.aramex-satchel-size').hide();
				$('#aramex_satchel_size').prop('required', false);
			}
		}

		// Handle packaging type change
		$(document).on('change', '#aramex_packaging_type, #aramex_satchel_size', function() {
			toggleSatchelSize();
			// Trigger shipping calculation update
			$('body').trigger('update_checkout');
		});

		// Initial state
		$(document).ready(function() {
			toggleSatchelSize();
		});

		// Handle shipping method change
		$(document).on('change', 'input[name^="shipping_method"]', function() {
			var selectedMethod = $('input[name^="shipping_method"]:checked').val();
			if (selectedMethod && selectedMethod.indexOf('my_shipping_method') !== -1) {
				$('#aramex_packaging_type, #aramex_satchel_size').closest('.form-row').show();
				toggleSatchelSize();
			} else {
				$('#aramex_packaging_type, #aramex_satchel_size').closest('.form-row').hide();
			}
		});
	});
	</script>
	<?php
}
add_action( 'wp_footer', 'aramex_shipping_aunz_checkout_script' );

/**
 * Save packaging choice to order meta
 */
function aramex_shipping_aunz_save_packaging_choice( $order_id ) {
    // Verify nonce
    if ( ! isset( $_POST['aramex_packaging_nonce'] ) || 
         ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aramex_packaging_nonce'] ) ), 'aramex_packaging_choice' ) ) {
        return;
    }

    if ( isset( $_POST['aramex_packaging_type'] ) ) {
        update_post_meta( 
            $order_id, 
            '_aramex_packaging_type', 
            sanitize_text_field( wp_unslash( $_POST['aramex_packaging_type'] ) ) 
        );
    }
    
    if ( isset( $_POST['aramex_satchel_size'] ) ) {
        update_post_meta( 
            $order_id, 
            '_aramex_satchel_size', 
            sanitize_text_field( wp_unslash( $_POST['aramex_satchel_size'] ) ) 
        );
    }
}
add_action( 'woocommerce_checkout_update_order_meta', 'aramex_shipping_aunz_save_packaging_choice' );

/**
 * Debug logging wrapper
 */
if ( ! function_exists( 'aramex_debug_log' ) ) {
    function aramex_debug_log( $message ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[Aramex Debug] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }
    }
}

/**
 * Fetch access token.
 */
function aramex_shipping_aunz_get_access_token( $api_key, $secret, $origin_country ) {
	aramex_debug_log( 'Fetching access token...' );

	// Ensure we have a valid country code
	$origin_country = strtolower(trim($origin_country));

	// Set the correct token endpoint and scope based on country
	if ($origin_country === 'au') {
		$url = 'https://identity.aramexconnect.com.au/connect/token';
		$scope = 'ac-api-au';
	} else {
		$url = 'https://identity.aramexconnect.co.nz/connect/token';
		$scope = 'ac-api-nz';
	}

	aramex_debug_log( 'Using auth endpoint: ' . $url . ' with scope: ' . $scope );

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
		aramex_debug_log( 'Error fetching access token: ' . $response->get_error_message() );
		return null;
	}

	$response_body = wp_remote_retrieve_body( $response );
	aramex_debug_log( 'Access Token API Response: ' . $response_body );

	$data = json_decode( $response_body, true );
	return isset( $data['access_token'] ) ? $data['access_token'] : null;
}