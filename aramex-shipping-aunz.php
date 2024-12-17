<?php
/**
 * Plugin Name: Aramex Shipping Aunz
 * Description: Adds Aramex Shipping functionality to WooCommerce for accurate shipping calculations and label generation.
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

// Ensure the file is being accessed from within WordPress, exit if not.
defined( 'ABSPATH' ) || exit;

// Define constants for the plugin file, directory path, and URL if not already defined.
if ( ! defined( 'ARAMEX_PLUGIN_FILE' ) ) {
	define( 'ARAMEX_PLUGIN_FILE', __FILE__ ); // Path to the main plugin file.
	define( 'ARAMEX_PLUGIN_DIR', plugin_dir_path( __FILE__ ) ); // Directory path of the plugin.
	define( 'ARAMEX_PLUGIN_URL', plugin_dir_url( __FILE__ ) );  // URL of the plugin directory.
}

// Include helper files and functions.
// These files contain functions for admin notices, AJAX handlers, and settings.
require_once ARAMEX_PLUGIN_DIR . 'src/functions-helpers.php';       // General helper functions.
require_once ARAMEX_PLUGIN_DIR . 'src/functions-admin-notices.php'; // Admin notices handling.
require_once ARAMEX_PLUGIN_DIR . 'src/functions-ajax.php';          // AJAX-related functions.
require_once ARAMEX_PLUGIN_DIR . 'src/functions-settings.php';      // Settings page logic.

// Register an AJAX action for testing API connections.
add_action( 'wp_ajax_aramex_shipping_aunz_test_connection_ajax', 'aramex_shipping_aunz_test_connection_ajax_callback' );

/**
 * Initialize the plugin and add WooCommerce settings tabs and the shipping method.
 *
 * Hooks and filters are used to:
 * - Add custom shipping settings sections and fields in WooCommerce settings.
 * - Initialize the shipping method logic when WooCommerce is ready.
 */
function aramex_shipping_aunz_init() {
	// Add a custom section to the WooCommerce Shipping settings.
	add_filter( 'woocommerce_get_sections_shipping', 'aramex_shipping_aunz_add_settings_section' );

	// Add settings fields within the custom section created above.
	add_filter( 'woocommerce_get_settings_shipping', 'aramex_shipping_aunz_get_settings', 10, 2 );

	// Load the shipping method class after WooCommerce shipping initializes.
	add_action( 'woocommerce_shipping_init', 'aramex_shipping_aunz_shipping_method_init' );

	// Register the shipping method so WooCommerce recognizes it.
	add_filter( 'woocommerce_shipping_methods', 'aramex_shipping_aunz_add_my_shipping_method' );
}

// Hook the initialization function to the 'plugins_loaded' action.
// Ensures all WordPress and WooCommerce functionalities are loaded before initializing.
add_action( 'plugins_loaded', 'aramex_shipping_aunz_init' );

/**
 * Load the shipping method class.
 *
 * This function loads the My_Shipping_Method class that defines the shipping logic.
 * It is only loaded after WooCommerce's shipping functionality is initialized.
 */
function aramex_shipping_aunz_shipping_method_init() {
	// Include the shipping method class file.
	require_once ARAMEX_PLUGIN_DIR . 'src/class-aramex-shipping-method.php';
}

/**
 * Add the custom shipping method to WooCommerce.
 *
 * @param array $methods Existing registered WooCommerce shipping methods.
 * @return array Modified list of shipping methods including 'My_Shipping_Method'.
 */
function aramex_shipping_aunz_add_my_shipping_method( $methods ) {
	// Add 'My_Shipping_Method' class to the available shipping methods.
	$methods['my_shipping_method'] = 'My_Shipping_Method';
	return $methods;
}
