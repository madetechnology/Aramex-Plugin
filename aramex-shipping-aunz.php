<?php
/**
 * Plugin Name: Aramex Shipping AUNZ
 * Plugin URI: https://github.com/madeinoz67/aramex-shipping-aunz
 * Description: Seamlessly integrate Aramex shipping services into your WooCommerce store. Features include real-time shipping rates, label generation, package tracking, and automated email notifications for Australia and New Zealand shipments.
 * Version: 1.0.0
 * Author: TBP
 * Text Domain: Aramex-Plugin
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Tested up to: 6.7
 * WC requires at least: 6.0
 * WC tested up to: 8.6
 *
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package aramex_shipping_aunz
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'ARAMEX_PLUGIN_FILE' ) ) {
	define( 'ARAMEX_PLUGIN_FILE', __FILE__ );
	define( 'ARAMEX_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
	define( 'ARAMEX_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

// Include helpers and other functions that don't depend on WC_Shipping_Method.
require_once ARAMEX_PLUGIN_DIR . 'src/functions-helpers.php';
require_once ARAMEX_PLUGIN_DIR . 'src/functions-admin-notices.php';
require_once ARAMEX_PLUGIN_DIR . 'src/functions-tracking.php';
require_once ARAMEX_PLUGIN_DIR . 'src/functions-email.php';
require_once ARAMEX_PLUGIN_DIR . 'src/functions-ajax.php';
require_once ARAMEX_PLUGIN_DIR . 'src/functions-settings.php';
require_once ARAMEX_PLUGIN_DIR . 'src/functions-admin.php';

/**
 * Add settings link on plugin page
 */
function aramex_shipping_aunz_settings_link($links) {
    $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=shipping&section=aramex_shipping')) . '">' . esc_html__('Settings', 'Aramex-Plugin') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'aramex_shipping_aunz_settings_link');

/**
 * Debug logging wrapper.
 * Only logs to error_log if WP_DEBUG is true.
 *
 * @param string $message The message to be logged.
 */
if ( ! function_exists( 'aramex_debug_log' ) ) {
	function aramex_debug_log( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}

// Aramex Actions.
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

	// Delay loading the shipping class until WooCommerce shipping is initialized.
	add_action( 'woocommerce_shipping_init', 'aramex_shipping_aunz_shipping_method_init' );
	add_filter( 'woocommerce_shipping_methods', 'aramex_shipping_aunz_add_my_shipping_method' );
}

add_action( 'plugins_loaded', 'aramex_shipping_aunz_init' );
add_action( 'woocommerce_order_item_add_action_buttons', 'add_custom_button_to_order_page', 10, 1 );

/**
 * Register the aramex_tracking shortcode.
 */
function aramex_tracking_shortcode() {
    ob_start();
    ?>
    <div class="aramex-tracking-form">
        <form id="aramex-tracking-form">
            <input type="text" id="aramex-tracking-number" placeholder="Enter tracking number" required>
            <button type="submit">Track Shipment</button>
        </form>
        <div id="aramex-tracking-results"></div>
    </div>
    <script>
    jQuery(document).ready(function($) {
        $('#aramex-tracking-form').on('submit', function(e) {
            e.preventDefault();
            var trackingNumber = $('#aramex-tracking-number').val();
            var resultsDiv = $('#aramex-tracking-results');
            
            resultsDiv.html('<p>Loading tracking information...</p>');
            
            $.ajax({
                url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                type: 'POST',
                data: {
                    action: 'track_shipment_action',
                    label_number: trackingNumber,
                    nonce: '<?php echo esc_js(wp_create_nonce('track_shipment_nonce')); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        var events = response.data.tracking_events;
                        var html = '<table class="aramex-tracking-table">';
                        html += '<thead><tr>';
                        html += '<th>Date/Time</th>';
                        html += '<th>Status</th>';
                        html += '<th>Description</th>';
                        html += '<th>Location</th>';
                        html += '</tr></thead><tbody>';

                        if (events && events.length > 0) {
                            events.forEach(function(event) {
                                html += '<tr>';
                                html += '<td>' + event.date + '</td>';
                                html += '<td>' + event.status + '</td>';
                                html += '<td>' + (event.scan_description || event.description) + '</td>';
                                html += '<td>' + event.location + '</td>';
                                html += '</tr>';
                            });
                        } else {
                            html += '<tr><td colspan="4">No tracking events found.</td></tr>';
                        }

                        html += '</tbody></table>';
                        resultsDiv.html(html);
                    } else {
                        resultsDiv.html('<p class="error">' + (response.data.message || 'Error retrieving tracking information') + '</p>');
                    }
                },
                error: function() {
                    resultsDiv.html('<p class="error">Error connecting to the server</p>');
                }
            });
        });
    });
    </script>
    <style>
    .aramex-tracking-form {
        max-width: 800px;
        margin: 20px auto;
    }
    .aramex-tracking-form form {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
    }
    .aramex-tracking-form input[type="text"] {
        flex: 1;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    .aramex-tracking-form button {
        padding: 8px 20px;
        background-color: #0073aa;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
    }
    .aramex-tracking-form button:hover {
        background-color: #005177;
    }
    .aramex-tracking-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }
    .aramex-tracking-table th,
    .aramex-tracking-table td {
        padding: 10px;
        border: 1px solid #ddd;
        text-align: left;
    }
    .aramex-tracking-table th {
        background-color: #f5f5f5;
    }
    .aramex-tracking-table tr:nth-child(even) {
        background-color: #f9f9f9;
    }
    .error {
        color: #dc3232;
        padding: 10px;
        background-color: #ffeaea;
        border-radius: 4px;
    }
    </style>
    <?php
    return ob_get_clean();
}
add_shortcode('aramex_tracking', 'aramex_tracking_shortcode');

/**
 * Load the shipping method class after WooCommerce shipping has initialized.
 */
function aramex_shipping_aunz_shipping_method_init() {
	require_once ARAMEX_PLUGIN_DIR . 'src/class-aramex-shipping-method.php';
}

function aramex_shipping_aunz_add_my_shipping_method( $methods ) {
	$methods['aramex_shipping'] = 'My_Shipping_Method';
	return $methods;
}

function add_custom_button_to_order_page( $order ) {
    // Ensure the $order object is valid and is an instance of WC_Order
    if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
        return;
    }

    // Add custom button
    echo '<button type="button" class="button custom-action-button" id="custom-action-button" data-order-id="' . esc_attr( $order->get_id() ) . '">'
        . esc_html__( 'Create Consignment', 'Aramex-Plugin' ) . '</button>';
    
    // Get tracking label number and consignment ID
    $label_no = $order->get_meta('aramex_label_no');
    $con_id = $order->get_meta('aramex_conId');
    
    // Only show delete, print, and track buttons if tracking label exists
    if ( $label_no ) {
        echo '<button type="button" class="button custom-action-delete-button" id="custom-action-delete-button" data-order-id="' . esc_attr( $order->get_id() ) 
            . '" data-consignment-id="' . esc_attr( $con_id ) . '">' 
            . esc_html__( 'Delete Consignment', 'Aramex-Plugin' ) . '</button>';

        echo '<button type="button" class="button custom-action-print-label" id="custom-action-print-label" data-order-id="' . esc_attr( $order->get_id() ) 
            . '" data-consignment-id="' . esc_attr( $label_no ) . '">' 
            . esc_html__( 'Print Label', 'Aramex-Plugin' ) . '</button>';

        echo '<button type="button" class="button custom-action-track-shipment" id="custom-action-track-shipment" data-order-id="' . esc_attr( $order->get_id() ) 
            . '" data-label-number="' . esc_attr( $label_no ) . '">'
            . esc_html__( 'Track Shipment', 'Aramex-Plugin' ) . '</button>';
        
        // Add tracking modal
        echo '<div id="aramex-tracking-modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%;'
            . 'overflow: auto; background-color: rgba(0,0,0,0.4);">';
        echo '<div style="background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 600px;">';
        echo '<span class="close" style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>';
        echo '<h2>' . esc_html__( 'Shipment Tracking', 'Aramex-Plugin' ) . '</h2>';
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
add_action( 'wp_ajax_track_shipment_action', 'aramex_track_shipment_callback' );

/**
 * Add admin scripts for test connection functionality.
 */
function aramex_admin_scripts( $hook ) {
    // Only load on WooCommerce shipping settings page.
    if ( 'woocommerce_page_wc-settings' !== $hook ) {
        return;
    }

    // Register and enqueue the script.
    wp_register_script(
        'aramex-admin',
        ARAMEX_PLUGIN_URL . 'assets/js/admin.js',
        array( 'jquery' ),
        '1.0.0',
        true
    );

    // Localize the script with data.
    wp_localize_script(
        'aramex-admin',
        'aramexAdmin',
        array(
            'ajax_url'             => esc_url( admin_url( 'admin-ajax.php' ) ),
            'nonce'                => esc_js(wp_create_nonce('aramex_test_connection_nonce')),
            /* translators: Shown while testing API connection */
            'testing_text'         => esc_html__( 'Testing...', 'Aramex-Plugin' ),
            /* translators: Error message shown when API connection fails */
            'test_connection_error'=> esc_html__( 'Connection test failed. Please check your credentials.', 'Aramex-Plugin' ),
            /* translators: Success message shown when API connection succeeds */
            'test_connection_success' => esc_html__( 'Connection test successful!', 'Aramex-Plugin' ),
        )
    );

    wp_enqueue_script( 'aramex-admin' );
}
add_action( 'admin_enqueue_scripts', 'aramex_admin_scripts' );

/**
 * Enqueue scripts for address autocomplete on checkout.
 */
function aramex_shipping_aunz_enqueue_scripts() {
    // Only enqueue on checkout page.
    if ( ! is_checkout() ) {
        return;
    }

    // Enqueue CSS.
    wp_enqueue_style(
        'addressable-autocomplete',
        ARAMEX_PLUGIN_URL . 'assets/css/addressable-autocomplete.css',
        array(),
        '1.0.0'
    );

    wp_enqueue_script(
        'addressable-autocomplete',
        ARAMEX_PLUGIN_URL . 'assets/js/addressable-autocomplete.js',
        array( 'jquery' ),
        '1.0.0',
        true
    );

    // Get the API key from settings.
    $api_key = get_option( 'aramex_shipping_aunz_addressable_api_key', '' );
    $default_country = substr( get_option( 'woocommerce_default_country', '' ), 0, 2 );

    wp_localize_script(
        'addressable-autocomplete',
        'addressableConfig',
        array(
            'apiKey'         => $api_key,
            'defaultCountry' => $default_country,
        )
    );
}
add_action( 'wp_enqueue_scripts', 'aramex_shipping_aunz_enqueue_scripts' );

/**
 * Get order meta compatibility wrapper
 */
function aramex_get_order_meta($order, $key, $single = true) {
    if (is_numeric($order)) {
        $order = wc_get_order($order);
    }
    
    if (!$order) {
        return false;
    }

    if (method_exists($order, 'get_meta')) {
        return $order->get_meta($key, $single);
    }
    
    return get_post_meta($order->get_id(), $key, $single);
}

/**
 * Update order meta compatibility wrapper
 */
function aramex_update_order_meta($order, $key, $value) {
    if (is_numeric($order)) {
        $order = wc_get_order($order);
    }
    
    if (!$order) {
        return false;
    }

    if (method_exists($order, 'update_meta_data')) {
        $order->update_meta_data($key, $value);
        $order->save();
        return true;
    }
    
    return update_post_meta($order->get_id(), $key, $value);
}

/**
 * Delete order meta compatibility wrapper
 */
function aramex_delete_order_meta($order, $key) {
    if (is_numeric($order)) {
        $order = wc_get_order($order);
    }
    
    if (!$order) {
        return false;
    }

    if (method_exists($order, 'delete_meta_data')) {
        $order->delete_meta_data($key);
        $order->save();
        return true;
    }
    
    return delete_post_meta($order->get_id(), $key);
}

/**
 * Handle WordPress data store warnings
 */
function aramex_handle_wp_scripts() {
    // Only on admin pages
    if (!is_admin()) {
        return;
    }

    // Add script to handle deprecated warnings
    wp_add_inline_script('wp-data', '
        // Prevent "Store is already registered" warning
        wp.data && wp.data.dispatch && wp.data.dispatch( "core/notices" ) && 
        wp.data.dispatch( "core/notices" ).createNotice && 
        window.console.warn = (function(old_function) { 
            return function(text) {
                if (!text.includes("Store") && !text.includes("select")) {
                    old_function.apply(console, arguments);
                }
            }
        })(window.console.warn);
    ');
}
add_action('admin_enqueue_scripts', 'aramex_handle_wp_scripts', 100);