<?php
defined( 'ABSPATH' ) || exit;

/**
 * Add a new section to WooCommerce > Settings > Shipping.
 */
function aramex_shipping_aunz_add_settings_section( $sections ) {
	$sections['aramex_shipping_aunz'] = __( 'Aramex Shipping Settings', 'aramex-shipping-aunz' );
	return $sections;
}

/**
 * Custom field type for AJAX test connection button.
 */
function aramex_shipping_aunz_test_connection_field() {
	echo '<tr valign="top"><th scope="row" class="titledesc">' . __( 'Test Connection', 'aramex-shipping-aunz' ) . '</th><td class="forminp">';
	echo '<button type="button" class="button-secondary" id="aramex-test-connection-btn">' . __( 'Test Connection', 'aramex-shipping-aunz' ) . '</button>';
	echo '<p class="description">' . __( 'Click to test your Aramex API credentials.', 'aramex-shipping-aunz' ) . '</p>';
	echo '<div id="aramex-test-connection-status" style="margin-top:10px;"></div>';
	echo '</td></tr>';
}
add_action( 'woocommerce_admin_field_aramex_test_connection', 'aramex_shipping_aunz_test_connection_field' );

/**
 * Add settings fields to the Aramex Shipping section.
 */
function aramex_shipping_aunz_get_settings( $settings, $current_section ) {
	if ( 'aramex_shipping_aunz' === $current_section ) {
		$settings = array(
			array(
				'title' => __( 'Aramex Shipping Settings', 'aramex-shipping-aunz' ),
				'type'  => 'title',
				'id'    => 'aramex_shipping_aunz_settings',
			),
			array(
				'title'       => __( 'API Key', 'aramex-shipping-aunz' ),
				'type'        => 'text',
				'desc'        => __( 'Enter your Aramex API Key.', 'aramex-shipping-aunz' ),
				'id'          => 'aramex_shipping_aunz_api_key',
				'css'         => 'min-width:300px;',
				'default'     => '',
				'autoload'    => false,
			),
			array(
				'title'       => __( 'API Secret', 'aramex-shipping-aunz' ),
				'type'        => 'password',
				'desc'        => __( 'Enter your Aramex API Secret.', 'aramex-shipping-aunz' ),
				'id'          => 'aramex_shipping_aunz_api_secret',
				'css'         => 'min-width:300px;',
				'default'     => '',
				'autoload'    => false,
			),
			array(
				'title'       => __( 'Shipping Origin Country', 'aramex-shipping-aunz' ),
				'type'        => 'select',
				'desc'        => __( 'Select the shipping origin country.', 'aramex-shipping-aunz' ),
				'id'          => 'aramex_shipping_aunz_origin_country',
				'default'     => 'nz',
				'options'     => array(
					'nz' => __( 'New Zealand', 'aramex-shipping-aunz' ),
					'au' => __( 'Australia', 'aramex-shipping-aunz' ),
				),
				'autoload'    => false,
			),
			array(
				'type' => 'sectionend',
				'id'   => 'aramex_shipping_aunz_settings',
			),
			array(
				'title' => __( 'Test Connection', 'aramex-shipping-aunz' ),
				'type'  => 'title',
				'desc'  => __( 'Test your Aramex API connection.', 'aramex-shipping-aunz' ),
				'id'    => 'aramex_shipping_aunz_test_connection_section',
			),
			array(
				'type' => 'aramex_test_connection',
				'id'   => 'aramex_shipping_aunz_test_connection_button',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'aramex_shipping_aunz_test_connection_section_end',
			),
		);
	}
	return $settings;
}

/**
 * Enqueue admin scripts for AJAX test connection.
 */
function aramex_shipping_aunz_admin_scripts( $hook ) {
	if ( 'woocommerce_page_wc-settings' !== $hook ) {
		return;
	}

	wp_enqueue_script(
		'aramex-shipping-test-connection',
		ARAMEX_PLUGIN_URL . 'src/path-to-your-script.js',
		array( 'jquery' ),
		'1.0.0',
		true
	);

	wp_localize_script( 'aramex-shipping-test-connection', 'aramexAjax', array(
		'ajax_url' => admin_url( 'admin-ajax.php' ),
		'nonce'    => wp_create_nonce( 'aramex_test_connection_nonce' ),
	) );
}
add_action( 'admin_enqueue_scripts', 'aramex_shipping_aunz_admin_scripts' );


function aramex_enqueue_scripts( $hook ) {
    if ( 'woocommerce_page_wc-orders' !== $hook ) {
        return;
    }

    wp_enqueue_script(
        'aramex-custom-actions',
        ARAMEX_PLUGIN_URL . 'src/path-to-your-script.js', // Path to your JS file
        array( 'jquery' ),
        '1.0.0',
        true
    );

    wp_localize_script( 'aramex-custom-actions', 'customAdminData', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'aramex_create_consignment_nonce' ),
    ) );

	wp_localize_script( 'aramex-custom-actions', 'customAdminDataDelete', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'aramex_delete_consignment_nonce' ),
    ) );

	wp_localize_script( 'aramex-custom-actions', 'customAdminDataPrint', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'aramex_print_consignment_nonce' ),
    ) );
}
add_action( 'admin_enqueue_scripts', 'aramex_enqueue_scripts' );