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
				'title'       => __( 'Addressable API Key', 'aramex-shipping-aunz' ),
				'type'        => 'text',
				'desc'        => __( 'Enter your Addressable API Key for address autocomplete functionality.', 'aramex-shipping-aunz' ),
				'id'          => 'aramex_shipping_aunz_addressable_api_key',
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