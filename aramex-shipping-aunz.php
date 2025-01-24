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
 * Register the aramex_tracking shortcode
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
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'track_shipment_action',
                    label_number: trackingNumber,
                    nonce: '<?php echo wp_create_nonce('track_shipment_nonce'); ?>'
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

/**
 * Add "Send Tracking Email" to order actions
 */
function aramex_add_order_action($actions) {
    global $theorder;
    
    // Check if we have a valid order and it has an Aramex label number
    if (!$theorder || !is_object($theorder)) {
        return $actions;
    }
    
    $label_number = $theorder->get_meta('aramex_label_number', true);
    if (!empty($label_number)) {
        $actions['aramex_send_tracking_email'] = __('Send Tracking Email Update to customer', 'aramex-shipping-aunz');
    }
    
    return $actions;
}
add_filter('woocommerce_order_actions', 'aramex_add_order_action');

/**
 * Handle the tracking email action
 */
function aramex_handle_tracking_email($order) {
    error_log('Aramex: Starting tracking email process for order ' . $order->get_id());
    
    if (!$order || !is_a($order, 'WC_Order')) {
        error_log('Aramex: Invalid order object');
        return;
    }

    $label_number = $order->get_meta('aramex_label_number', true);
    if (empty($label_number)) {
        error_log('Aramex: No label number found for order ' . $order->get_id());
        $order->add_order_note(__('Failed to send tracking email: No label number found', 'aramex-shipping-aunz'));
        return;
    }

    error_log('Aramex: Getting tracking info for label ' . $label_number);
    
    // Get tracking information
    $tracking_info = aramex_get_tracking_info($label_number);
    
    // Send email regardless of whether there are tracking events
    $email_sent = aramex_send_tracking_email($order, $tracking_info);
    if ($email_sent) {
        error_log('Aramex: Email sent successfully to ' . $order->get_billing_email());
        $order->add_order_note(__('Tracking information email sent to customer.', 'aramex-shipping-aunz'));
    } else {
        error_log('Aramex: Failed to send email to ' . $order->get_billing_email());
        $order->add_order_note(__('Failed to send tracking email. Please check server email configuration.', 'aramex-shipping-aunz'));
    }
}

/**
 * Send tracking email to customer
 */
function aramex_send_tracking_email($order, $tracking_info) {
    $to = $order->get_billing_email();
    $subject = sprintf(__('Tracking Update for Order #%s', 'aramex-shipping-aunz'), $order->get_order_number());
    
    error_log('Aramex: Preparing email for order ' . $order->get_id() . ' to ' . $to);
    
    // Get store info for the from address
    $from_name = get_bloginfo('name');
    $from_email = get_option('admin_email');
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    </head>
    <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
        <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
            <p style="margin-bottom: 20px;"><?php printf(__('Hello %s,', 'aramex-shipping-aunz'), $order->get_billing_first_name()); ?></p>
            
            <?php if (!$tracking_info['success'] || empty($tracking_info['events'])): ?>
                <p style="margin-bottom: 20px;"><?php _e('Your order has been processed and a shipping label has been created. The package is currently awaiting pickup by our courier partner.', 'aramex-shipping-aunz'); ?></p>
                
                <p style="margin-bottom: 20px;"><?php _e('Once the package is picked up, you will start seeing tracking updates here. This usually happens within 1-2 business days.', 'aramex-shipping-aunz'); ?></p>
                
                <div style="background-color: #f8f8f8; padding: 15px; border: 1px solid #ddd; border-radius: 4px; margin: 20px 0;">
                    <p style="margin: 0;"><strong><?php _e('Tracking Number:', 'aramex-shipping-aunz'); ?></strong> <?php echo esc_html($order->get_meta('aramex_label_number', true)); ?></p>
                </div>
            <?php else: ?>
                <p style="margin-bottom: 20px;"><?php _e('Here is the current tracking information for your order:', 'aramex-shipping-aunz'); ?></p>
                
                <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
                    <thead>
                        <tr style="background-color: #f8f8f8;">
                            <th style="padding: 10px; border: 1px solid #ddd; text-align: left;"><?php _e('Date/Time', 'aramex-shipping-aunz'); ?></th>
                            <th style="padding: 10px; border: 1px solid #ddd; text-align: left;"><?php _e('Status', 'aramex-shipping-aunz'); ?></th>
                            <th style="padding: 10px; border: 1px solid #ddd; text-align: left;"><?php _e('Description', 'aramex-shipping-aunz'); ?></th>
                            <th style="padding: 10px; border: 1px solid #ddd; text-align: left;"><?php _e('Location', 'aramex-shipping-aunz'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tracking_info['events'] as $event): ?>
                        <tr>
                            <td style="padding: 10px; border: 1px solid #ddd;"><?php echo esc_html($event['date']); ?></td>
                            <td style="padding: 10px; border: 1px solid #ddd;"><?php echo esc_html($event['status']); ?></td>
                            <td style="padding: 10px; border: 1px solid #ddd;"><?php echo esc_html($event['scan_description'] ?: $event['description']); ?></td>
                            <td style="padding: 10px; border: 1px solid #ddd;"><?php echo esc_html($event['location']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <p style="margin-top: 20px;"><?php _e('Thank you for choosing our service.', 'aramex-shipping-aunz'); ?></p>
            
            <p style="margin-top: 20px; font-size: 0.9em; color: #666;">
                <?php _e('If you have any questions about your shipment, please don\'t hesitate to contact us.', 'aramex-shipping-aunz'); ?>
            </p>
        </div>
    </body>
    </html>
    <?php
    $message = ob_get_clean();
    
    // Set up email headers
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $from_name . ' <' . $from_email . '>',
        'Reply-To: ' . $from_email,
    );

    error_log('Aramex: Attempting to send email with following details:');
    error_log('Aramex: To: ' . $to);
    error_log('Aramex: Subject: ' . $subject);
    error_log('Aramex: From: ' . $from_name . ' <' . $from_email . '>');
    
    // Send the email
    $sent = wp_mail($to, $subject, $message, $headers);
    
    if (!$sent) {
        error_log('Aramex: Email sending failed. wp_mail returned false');
        // Try to get more information about why the email failed
        global $phpmailer;
        if (isset($phpmailer) && is_object($phpmailer)) {
            error_log('Aramex: PHPMailer Error: ' . $phpmailer->ErrorInfo);
        }
    } else {
        error_log('Aramex: Email sent successfully');
    }
    
    return $sent;
}

/**
 * Add admin scripts for test connection functionality
 */
function aramex_admin_scripts($hook) {
    // Only load on WooCommerce shipping settings page
    if ('woocommerce_page_wc-settings' !== $hook) {
        return;
    }

    // Register and enqueue the script
    wp_register_script(
        'aramex-admin',
        ARAMEX_PLUGIN_URL . 'assets/js/admin.js',
        array('jquery'),
        '1.0.0',
        true
    );

    // Localize the script with data
    wp_localize_script('aramex-admin', 'aramexAdmin', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('aramex_test_connection_nonce'),
        'testing_text' => __('Testing...', 'aramex-shipping-aunz'),
        'test_connection_error' => __('Connection test failed. Please check your credentials.', 'aramex-shipping-aunz'),
        'test_connection_success' => __('Connection test successful!', 'aramex-shipping-aunz')
    ));

    wp_enqueue_script('aramex-admin');
}
add_action('admin_enqueue_scripts', 'aramex_admin_scripts');

