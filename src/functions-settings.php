<?php
defined( 'ABSPATH' ) || exit;

/**
 * Add a new section to WooCommerce > Settings > Shipping.
 */
function aramex_shipping_aunz_add_settings_section( $sections ) {
	$sections['aramex_shipping'] = __( 'Aramex Shipping Settings', 'Aramex-Plugin' );
	return $sections;
}

/**
 * Custom field type for AJAX test connection button.
 */
function aramex_shipping_aunz_test_connection_field() {
	echo '<tr valign="top"><th scope="row" class="titledesc">' . esc_html__('Test Connection', 'Aramex-Plugin') . '</th><td class="forminp">';
	echo '<button type="button" class="button-secondary" id="aramex-test-connection-btn">' . esc_html__('Test Connection', 'Aramex-Plugin') . '</button>';
	echo '<p class="description">' . esc_html__('Click to test your Aramex API credentials.', 'Aramex-Plugin') . '</p>';
	echo '<div id="aramex-test-connection-status" style="margin-top:10px;"></div>';
	echo '</td></tr>';
}
add_action( 'woocommerce_admin_field_aramex_test_connection', 'aramex_shipping_aunz_test_connection_field' );

/**
 * Add settings fields to the Aramex Shipping section.
 */
function aramex_shipping_aunz_get_settings( $settings, $current_section ) {
	if ( 'aramex_shipping' === $current_section ) {
		$settings = array(
			array(
				'title' => __( 'Aramex Shipping Settings', 'Aramex-Plugin' ),
				'type'  => 'title',
				'id'    => 'aramex_shipping_aunz_settings',
			),
			array(
				'title'       => __( 'API Key', 'Aramex-Plugin' ),
				'type'        => 'text',
				'desc'        => __( 'Enter your Aramex API Key.', 'Aramex-Plugin' ),
				'id'          => 'aramex_shipping_aunz_api_key',
				'css'         => 'min-width:300px;',
				'default'     => '',
				'autoload'    => false,
			),
			array(
				'title'       => __( 'Addressable API Key', 'Aramex-Plugin' ),
				'type'        => 'text',
				'desc'        => __( 'Enter your Addressable API Key for address autocomplete functionality.', 'Aramex-Plugin' ),
				'id'          => 'aramex_shipping_aunz_addressable_api_key',
				'css'         => 'min-width:300px;',
				'default'     => '',
				'autoload'    => false,
			),
			array(
				'title'       => __( 'API Secret', 'Aramex-Plugin' ),
				'type'        => 'password',
				'desc'        => __( 'Enter your Aramex API Secret.', 'Aramex-Plugin' ),
				'id'          => 'aramex_shipping_aunz_api_secret',
				'css'         => 'min-width:300px;',
				'default'     => '',
				'autoload'    => false,
			),
			array(
				'title'       => __( 'Shipping Origin Country', 'Aramex-Plugin' ),
				'type'        => 'select',
				'desc'        => __( 'Select the shipping origin country.', 'Aramex-Plugin' ),
				'id'          => 'aramex_shipping_aunz_origin_country',
				'default'     => 'nz',
				'options'     => array(
					'nz' => __( 'New Zealand', 'Aramex-Plugin' ),
					'au' => __( 'Australia', 'Aramex-Plugin' ),
				),
				'autoload'    => false,
			),
			array(
				'type' => 'sectionend',
				'id'   => 'aramex_shipping_aunz_settings',
			),
			array(
				'title' => __( 'Test Connection', 'Aramex-Plugin' ),
				'type'  => 'title',
				'desc'  => __( 'Test your Aramex API connection.', 'Aramex-Plugin' ),
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
 * Save settings.
 */
function aramex_shipping_aunz_update_settings() {
    global $current_section;
    
    if ('aramex_shipping' === $current_section) {
        aramex_debug_log('Saving Aramex settings...');
        
        // Get the current value before saving
        $old_country = get_option('aramex_shipping_aunz_origin_country', 'nz');
        
        // Save settings
        woocommerce_update_options(aramex_shipping_aunz_get_settings(array(), $current_section));
        
        // Get the new value after saving
        $new_country = get_option('aramex_shipping_aunz_origin_country', 'nz');
        
        aramex_debug_log(sprintf('Origin country changed from %s to %s', $old_country, $new_country));
    }
}
add_action('woocommerce_update_options_shipping_aramex_shipping', 'aramex_shipping_aunz_update_settings');