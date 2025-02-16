<?php
/**
 * Email handling functions for the Aramex Shipping AUNZ plugin.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Send tracking email update to customer
 *
 * @param WC_Order $order The order object
 * @param string $tracking_number The tracking number to use for tracking
 * @param string $con_id The Aramex consignment ID (optional)
 * @return bool Whether the email was sent successfully
 */
function aramex_send_tracking_email($order, $tracking_number, $con_id = '') {
    if (!$order || !$tracking_number) {
        aramex_debug_log('Invalid order or tracking number provided for tracking email');
        return false;
    }

    aramex_debug_log('Getting tracking information for number: ' . $tracking_number);

    // Get tracking information
    $tracking_info = aramex_get_tracking_info($tracking_number);
    
    // Get customer email
    $to = $order->get_billing_email();
    
    // Set email subject
    /* translators: %s: Order number */
    $subject = sprintf(esc_html__('Tracking Update for Order #%s', 'Aramex-Plugin'), $order->get_order_number());
    
    aramex_debug_log('Preparing email template for order #' . $order->get_id());
    
    // Start output buffering to capture template content
    ob_start();
    
    // Set variables for the template
    $label_no = $tracking_number;
    
    // Include email template
    include plugin_dir_path(dirname(__FILE__)) . 'templates/emails/tracking-update.php';
    
    // Get template content
    $message = ob_get_clean();
    
    // Email headers
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . get_option('woocommerce_email_from_name') . ' <' . get_option('woocommerce_email_from_address') . '>',
    );
    
    aramex_debug_log('Attempting to send tracking email to ' . $to);
    
    // Send email
    $sent = wp_mail($to, $subject, $message, $headers);
    
    // Log the result
    if ($sent) {
        aramex_debug_log(sprintf(
            'Tracking email sent successfully to %s for order #%s with tracking number %s',
            $to,
            $order->get_order_number(),
            $tracking_number
        ));
        
        // Add order note
        $order->add_order_note(
            sprintf(
                /* translators: %s: tracking number */
                esc_html__('Tracking email sent to customer with tracking number: %s', 'Aramex-Plugin'),
                $tracking_number
            )
        );
    } else {
        aramex_debug_log(sprintf(
            'Failed to send tracking email to %s for order #%s with tracking number %s',
            $to,
            $order->get_order_number(),
            $tracking_number
        ));
        
        // Add order note about failure
        $order->add_order_note(
            sprintf(
                /* translators: %s: tracking number */
                esc_html__('Failed to send tracking email to customer for tracking number: %s', 'Aramex-Plugin'),
                $tracking_number
            )
        );
    }
    
    return $sent;
}

/**
 * Handle the tracking email action
 *
 * @param WC_Order $order The order object
 * @return void
 */
function aramex_handle_tracking_email($order) {
    if (!$order) {
        aramex_debug_log('Cannot send tracking email: Invalid order object');
        return;
    }
    
    // Get the tracking label number directly from the meta box field
    $label_no = $order->get_meta('aramex_label_no');
    aramex_debug_log('Retrieved tracking label: ' . $label_no);
    
    if (empty($label_no)) {
        aramex_debug_log('Cannot send tracking email: No tracking number found for order #' . $order->get_id());
        $order->add_order_note(esc_html__('Cannot send tracking email: No tracking number found.', 'Aramex-Plugin'));
        return;
    }
    
    aramex_debug_log('Preparing to send tracking email for order #' . $order->get_id() . ' with tracking number: ' . $label_no);
    
    // Send the tracking email with the label number
    aramex_send_tracking_email($order, $label_no);
}

/**
 * Add "Send Tracking Email" to order actions
 *
 * @param array $actions Array of available order actions
 * @param WC_Order $order The order object
 * @return array Modified array of order actions
 */
function aramex_add_order_action($actions, $order) {
    // Get the tracking label number from meta box
    $label_no = $order->get_meta('aramex_label_no');
    aramex_debug_log('Checking for tracking label in order actions: ' . $label_no);
    
    // Only add the action if we have a tracking number
    if (!empty($label_no)) {
        $actions['aramex_send_tracking_email'] = __('Send Aramex Tracking Email', 'Aramex-Plugin');
    }
    
    return $actions;
}
add_filter('woocommerce_order_actions', 'aramex_add_order_action', 10, 2);

/**
 * Handle the tracking email order action
 *
 * @param WC_Order $order The order object
 * @return void
 */
function aramex_process_tracking_email_action($order) {
    aramex_debug_log('Processing tracking email action for order #' . $order->get_id());
    aramex_handle_tracking_email($order);
}
add_action('woocommerce_order_action_aramex_send_tracking_email', 'aramex_process_tracking_email_action'); 