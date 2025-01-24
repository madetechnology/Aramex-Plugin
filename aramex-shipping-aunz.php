<?php
/**
 * Plugin Name: Aramex Shipping Aunz
 * Version: 1.0.0
 * Author: ADSO Developers
 * Author URI: https://adso.co.nz
 * Text Domain: aramex-shipping-aunz
 * Domain Path: /languages
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package aramex_shipping_aunz
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'ARAMEX_PLUGIN_FILE' ) ) {
	define( 'ARAMEX_PLUGIN_FILE', __FILE__ );
	define( 'ARAMEX_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
	define( 'ARAMEX_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

// Include helpers and other functions that don't depend on WC_Shipping_Method
require_once ARAMEX_PLUGIN_DIR . 'src/functions-helpers.php';
require_once ARAMEX_PLUGIN_DIR . 'src/functions-admin-notices.php';
require_once ARAMEX_PLUGIN_DIR . 'src/functions-ajax.php';
require_once ARAMEX_PLUGIN_DIR . 'src/functions-settings.php';

// Aramex Actions 
add_action( 'wp_ajax_aramex_shipping_aunz_test_connection_ajax', 'aramex_shipping_aunz_test_connection_ajax_callback' );
add_action( 'wp_ajax_create_consignment_action', 'aramex_create_consignment_callback' );
add_action( 'wp_ajax_delete_consignment_action', 'aramex_delete_consignment_callback' );
add_action( 'wp_ajax_print_label_action', 'aramex_print_label_callback' );

/**
 * Initialize the plugin and add WooCommerce settings tab and shipping method.
 */
function aramex_shipping_aunz_init() {
	add_filter( 'woocommerce_get_sections_shipping', 'aramex_shipping_aunz_add_settings_section' );
	add_filter( 'woocommerce_get_settings_shipping', 'aramex_shipping_aunz_get_settings', 10, 2 );

	// Delay loading the shipping class until WooCommerce shipping is initialized
	add_action( 'woocommerce_shipping_init', 'aramex_shipping_aunz_shipping_method_init' );
	add_filter( 'woocommerce_shipping_methods', 'aramex_shipping_aunz_add_my_shipping_method' );
}

add_action( 'plugins_loaded', 'aramex_shipping_aunz_init' );
add_action( 'woocommerce_order_item_add_action_buttons', 'add_custom_button_to_order_page', 10, 1 );
//add_action( 'woocommerce_order_item_add_action_buttons', 'add_custom_delete_button_to_order_page', 10, 1 );


/**
 * Load the shipping method class after WooCommerce shipping has initialized.
 */
function aramex_shipping_aunz_shipping_method_init() {
	// Now we can safely include the shipping method class.
	require_once ARAMEX_PLUGIN_DIR . 'src/class-aramex-shipping-method.php';
}

function aramex_shipping_aunz_add_my_shipping_method( $methods ) {
	$methods['my_shipping_method'] = 'My_Shipping_Method';
	return $methods;
}



function add_custom_button_to_order_page( $order ) {
    // Ensure the $order object is valid and is an instance of WC_Order
    if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
        return;
    }

    // Add the custom button
    echo '<button type="button" class="button custom-action-button" id="custom-action-button" data-order-id="' . esc_attr( $order->get_id() ) . '">' . __( 'Create Consignment', 'aramex-shipping-aunz' ) . '</button>';
    
    // Only show delete, print, and track buttons if aramex_conId exists
    $con_id = $order->get_meta('aramex_conId');
    if ($con_id) {
        $label_number = $order->get_meta('aramex_label_number', true) ?: $con_id;
        
        echo '<button type="button" class="button custom-action-delete-button" id="custom-action-delete-button" data-order-id="' . esc_attr( $order->get_id() ) . '" data-consignment-id="' . esc_attr( $con_id ) . '">' . __( 'Delete Consignment', 'aramex-shipping-aunz' ) . '</button>';
        echo '<button type="button" class="button custom-action-print-label" id="custom-action-print-label" data-order-id="' . esc_attr( $order->get_id() ) . '" data-consignment-id="' . esc_attr( $con_id ) . '">' . __( 'Print Label', 'aramex-shipping-aunz' ) . '</button>';
        echo '<button type="button" class="button custom-action-track-shipment" id="custom-action-track-shipment" data-order-id="' . esc_attr( $order->get_id() ) . '" data-consignment-id="' . esc_attr( $con_id ) . '" data-label-number="' . esc_attr( $label_number ) . '">' . __( 'Track Shipment', 'aramex-shipping-aunz' ) . '</button>';
        
        // Add tracking modal
        echo '<div id="aramex-tracking-modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);">';
        echo '<div style="background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 600px;">';
        echo '<span class="close" style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>';
        echo '<h2>' . __('Shipment Tracking', 'aramex-shipping-aunz') . '</h2>';
        echo '<div id="aramex-tracking-content"></div>';
        echo '</div>';
        echo '</div>';
    }

    // Include nonces for security
    wp_nonce_field( 'create_consignment_action', 'create_consignment_nonce' );
    wp_nonce_field( 'delete_consignment_action', 'delete_consignment_nonce' );
    wp_nonce_field( 'print_label_action', 'print_label_nonce' );
    wp_nonce_field( 'track_shipment_action', 'track_shipment_nonce' );
}

// Add AJAX action for tracking
add_action('wp_ajax_track_shipment_action', 'aramex_track_shipment_callback');

